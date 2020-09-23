<?php
namespace Oxzion\Service;

use Oxzion\Model\App;
use Oxzion\Model\AppTable;
use Exception;
use FileSystemIterator;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\Db\Migration\Migration;
use Oxzion\ServiceException;
use Oxzion\Service\AbstractService;
use Oxzion\Service\JobService;
use Oxzion\Service\FieldService;
use Oxzion\Service\FormService;
use Oxzion\Service\WorkflowService;
use Oxzion\Service\BusinessRoleService;
use Oxzion\Utils\ExecUtils;
use Oxzion\Utils\FileUtils;
use Oxzion\Utils\FilterUtils;
use Oxzion\Utils\UuidUtil;
use Oxzion\Utils\RestClient;
use Oxzion\EntityNotFoundException;
use Oxzion\FileNotFoundException;
use Oxzion\FileContentException;
use Oxzion\DuplicateEntityException;
use Symfony\Component\Yaml\Yaml;
use Oxzion\Document\Parser\Spreadsheet\SpreadsheetParserImpl;
use Oxzion\Document\Parser\Spreadsheet\SpreadsheetFilter;
use Oxzion\Document\Parser\Form\FormRowMapper;
use Oxzion\Utils\ArrayUtils;
use Oxzion\InvalidParameterException;
use Oxzion\App\AppArtifactNamingStrategy;
use Oxzion\Model\Entity;
use Oxzion\ValidationException;
use Oxzion\Service\UserService;

class AppService extends AbstractService
{
    const EOX_RESERVED_APP_NAME = 'SampleApp';
    const APPLICATION_DESCRIPTOR_FILE_NAME = 'application.yml';

    private $table;
    protected $workflowService;
    protected $fieldService;
    protected $formService;
    protected $organizationService;
    protected $entityService;
    private $privilegeService;
    private $menuItemService;
    private $pageService;
    private $jobService;
    private $appDeployOptions;
    private $roleService;
    private $userService;
    private $businessRoleService;

    /**
     * @ignore __construct
     */
    public function __construct($config, $dbAdapter, AppTable $table, WorkflowService $workflowService, FormService $formService, FieldService $fieldService, JobService $jobService, $organizationService, $entityService, $privilegeService, $roleService, $menuItemService, $pageService,UserService $userService, BusinessRoleService $businessRoleService)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
        $this->workflowService = $workflowService;
        $this->formService = $formService;
        $this->fieldService = $fieldService;
        $this->organizationService = $organizationService;
        $this->entityService = $entityService;
        $this->privilegeService = $privilegeService;
        $this->roleService = $roleService;
        $this->menuItemService = $menuItemService;
        $this->pageService = $pageService;
        $this->jobService = $jobService;
        $this->businessRoleService = $businessRoleService;
        $this->userService = $userService;
        $this->restClient = new RestClient(null);
        $this->appDeployOptions = array("initialize", "symlink", "entity", "workflow", "form", "page", "menu", "job", "migration");
    }

    /**
     * GET List App Service
     * @method getApps
     * @return array $data get list of Apps by User
     * <code>
     * {
     * }
     * </code>
     */
    public function getApps()
    {
        $queryString = 'SELECT ap.name, ap.uuid, ap.description, ap.type, ap.logo, ap.category, ap.date_created, 
        ap.date_modified, ap.created_by, ap.modified_by, ap.status, ar.org_id, ar.start_options 
        FROM ox_app AS ap
        LEFT JOIN ox_app_registry AS ar ON ap.id = ar.app_id 
        WHERE ar.org_id=:orgId AND ap.status!=:status AND ap.name <> \'' . AppService::EOX_RESERVED_APP_NAME . '\'';
        $queryParams = [
            'orgId' => AuthContext::get(AuthConstants::ORG_ID),
            'status' => App::DELETED
        ];
        $resultSet = $this->executeQueryWithBindParameters($queryString, $queryParams)->toArray();
        if (empty($resultSet)) {
            throw new EntityNotFoundException('No active registered apps found for logged-in user\'s organization.', NULL);
        }
        return $resultSet;
    }

    public function getApp($uuid)
    {
        $queryString = 'SELECT ap.name, ap.uuid 
        FROM ox_app AS ap
        LEFT JOIN ox_app_registry AS ar ON ap.id=ar.app_id AND ar.org_id=:orgId
        WHERE ap.status!=:statusDeleted AND ap.uuid=:uuid';
        $queryParams = [
            'orgId' => AuthContext::get(AuthConstants::ORG_ID),
            'statusDeleted' => App::DELETED, 
            'uuid' => $uuid
        ];
        $resultSet = $this->executeQueryWithBindParameters($queryString, $queryParams)->toArray();
        if (is_null($resultSet) || empty($resultSet)) {
            throw new EntityNotFoundException('Entity not found.', 
                ['entity' => 'Active registered app for the logged-in user\'s organization', 'uuid' => $uuid]);
        }
        $appData = $resultSet[0];
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $appData);
        return $this->loadAppDescriptor($appSourceDir);
    }

    public function createApp(&$data)
    {
        $app = new App($this->table);
        //Assign default values.
        $app->assign([
            'type' => App::MY_APP,
            'isdefault' => false,
            'category' => 'Unassigned',
            'status' => App::IN_DRAFT
        ]);
        //Assign user input values AFTER assigning default values.
        $appData = $data['app'];
        $app->assign($appData);
        if (array_key_exists(Entity::COLUMN_UUID, $appData) && !empty($appData[Entity::COLUMN_UUID])) {
            $app->setUserGeneratedUuid($appData[Entity::COLUMN_UUID]);
        }
        try {
            $this->beginTransaction();
            $app->save();
            //IMPORTANT: Don't commit database transaction here.
            $appProperties = $app->getProperties();
            ArrayUtils::merge($appData, $appProperties);
            $data['app'] = $appData;
            if (App::MY_APP == $app->getProperty('type')) {
                $this->setupOrUpdateApplicationDirectoryStructure($data);
            }
            //Commit database transaction only after application setup is successful.
            $this->commit();
        }
        catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        return $data;
    }

    //Creates the source directory for the application.
    //Copies contents of template application (<DATA DIRECTORY>/eoxapps) to source directory.
    //Creates application.yml file in the source directory.
    //Writes $appData contents to application.yml file.
    public function setupOrUpdateApplicationDirectoryStructure($descriptorData) {
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $descriptorData['app']);
        if (!file_exists($appSourceDir)) {
            if (!mkdir($appSourceDir)) {
                $this->logger->error("Failed to create application source directory ${appSourceDir}.");
                throw new ServiceException('Failed to create application source directory.', 'E_APP_SOURCE_DIR_CREATE_FAIL', 0);
            }
            $appTemplateDir = AppArtifactNamingStrategy::getTemplateAppDirectory($this->config);
            if (!file_exists($appTemplateDir)) {
                $this->logger->error("Template application directory ${appTemplateDir} not found.");
                throw new FileNotFoundException('Template application not found.', $appTemplateDir);
            }
            FileUtils::copyDir($appTemplateDir, $appSourceDir);
        }
        $this->createOrUpdateApplicationDescriptor($appSourceDir, $descriptorData);
        return $appSourceDir;
    }

    private function createOrUpdateApplicationDescriptor($dirPath, $descriptorData) {
        $descriptorFilePath = $dirPath . 
        ((DIRECTORY_SEPARATOR == substr($dirPath, -1 )) ? '' : DIRECTORY_SEPARATOR) . self::APPLICATION_DESCRIPTOR_FILE_NAME;
        if (file_exists($descriptorFilePath)) {
            $descriptorDataFromFile = self::loadAppDescriptor($dirPath);
            ArrayUtils::merge($descriptorDataFromFile, $descriptorData);
            $yamlText = Yaml::dump($descriptorDataFromFile,20);
        }
        else {
            $yamlText = Yaml::dump($descriptorData,20);
        }
        $yamlWriteResult = file_put_contents($descriptorFilePath, $yamlText);
        if (!$yamlWriteResult) {
            $this->logger->error("Failed to create application YAML file ${descriptorFilePath}.");
        }
        return $data;
    }

    public static function loadAppDescriptor($path)
    {
        //check if directory exists
        if (!(file_exists($path))) {
            throw new FileNotFoundException('Directory not found.', $path);
        }
        if (substr($path, -1) != '/') {
            $path = $path . '/';
        }
        $filePath = $path . self::APPLICATION_DESCRIPTOR_FILE_NAME;
        //check if filename exists
        if (!(file_exists($filePath))) {
            throw new FileNotFoundException('File not found.', $filePath);
        }
        $yaml = Yaml::parse(file_get_contents($filePath));
        if (empty($yaml)) {
            throw new FileContentException('File is empty.', $filePath);
        }
        if (!(isset($yaml['app']))) {
            throw new FileContentException(
                'Application information not found in application descriptor YAML file.', $filePath);
        }
        return $yaml;
    }

    public function deployApp($path, $params = null)
    {
        $ymlData =  $this->cleanApplicationDescriptorData(self::loadAppDescriptor($path));
        $this->logger->info("\n Yaml Data " . print_r($ymlData, true));
        if(!isset($params)){
            $params = $this->appDeployOptions;
        }
        try{
            foreach ($this->appDeployOptions as $key => $value) {
                if(!in_array($value, $params)){
                    continue;
                }
                $this->logger->info("\n App Data processing - " . print_r($value, true));
                switch ($value) {
                    case 'initialize':
                    $this->createOrUpdateApp($ymlData);
                    $this->createAppPrivileges($ymlData);
                    $this->createRole($ymlData);
                    $this->performMigration($ymlData, $path);
                    break;
                    case 'entity':
                    $this->processEntity($ymlData);
                    break;
                    case 'workflow':
                    $this->processWorkflow($ymlData, $path);
                    break;
                    case 'form':
                    $this->processForm($ymlData, $path);
                    break;
                    case 'page':
                    $this->processPage($ymlData, $path);
                    break;
                    case 'menu':
                    $this->processMenu($ymlData, $path);
                    break;
                    case 'job':
                    $this->processJob($ymlData);
                    break;
                    case 'symlink':
                    $this->processSymlinks($ymlData, $path);
                    break;
                    case 'view':                
                    $this->setupAppView($ymlData, $path);
                    break;
                    default:
                    $this->logger->error("Unhandled deploy option '${value}'");
                    break;
                }
            }

            $this->setupOrg($ymlData, $path);
            $appData = &$ymlData['app'];
            $appData['status'] = App::PUBLISHED;
            $this->logger->info("\n App Data before app update - " . print_r($appData, true));
        $this->updateApp($appData['uuid'], $ymlData); //Update is needed because app status changed to PUBLISHED.    
    }catch(Exception $e){
        $this->removeViewAppOnError($path);
    }finally{        
        $this->createOrUpdateApplicationDescriptor($path, $ymlData);
    }
    return $ymlData;
}

private function removeViewAppOnError($path){
    $targetPath = FileUtils::joinPath($path)."view/apps/eoxapps";
    $this->logger->info("TARGET PATH---".print_r($targetPath,true));
    if (file_exists($targetPath) && is_dir($targetPath)) {
        if (is_link($targetPath)) {
            FileUtils::unlink($targetPath);
        }
        FileUtils::rmDir($targetPath);
    }
}

    /**
     * Deploy App service for AppBuilder. AppBuilder creates the application in <EOX_APP_SOURCE_DIR> 
     * on the server and assigns a UUID for the application in OX_APP table in database. This service  
     * uses the UUID of the application for deployment. This service copies the application from 
     * <EOX_APP_SOURCE_DIR> to <EOX_APP_DEPLOY_DIR> and then calls deployApp method of this service 
     * to deploy the application.
     */
    public function deployApplication($appId, $params = null)
    {           
        $destination = $this->getAppSourceAndDeployDirectory($appId);
        $appSourceDir = $destination['sourceDir'];
        $appDeployDir = $destination['deployDir'];
        FileUtils::copyDir($appSourceDir, $appDeployDir);
        $appDeployDir = FileUtils::joinPath($appDeployDir);
        try{
           $result = $this->deployApp($appDeployDir, $params);  
       }finally{
           FileUtils::copy($appDeployDir."application.yml","application.yml",$appSourceDir); 
       }   
       return $result;
   }

   private function getAppSourceAndDeployDirectory($appId){
    $query = 'SELECT name, type FROM ox_app WHERE uuid=:appId';
    $queryParams = array('appId' => $appId);
    $result = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
    if(!isset($result) || empty($result) || (count($result) != 1)) {
        $this->logger->error("Application with APP ID ${appId} not found.");
        throw new EntityNotFoundException('Entity not found.', ['entity' => 'App', 'uuid' => $appId]);
    }
    $appData = $result[0];
    $appData['uuid'] = $appId;

    $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $appData);
    if (!file_exists($appSourceDir)) {
        $this->logger->error("Application source directory ${appSourceDir} not found.");
        throw new FileNotFoundException('Application source directory not found.', $appSourceDir);
    }
    $appDeployDir = AppArtifactNamingStrategy::getDeployAppDirectory($this->config, $appData);
    if ((App::MY_APP == $appData['type']) && !file_exists($appDeployDir)) {
        if (!mkdir($appDeployDir)) {
            $this->logger->error("Failed to create application deployment directory ${appDeployDir}.");
            throw new ServiceException('Failed to create application deployment directory.', 'E_APP_DEPLOY_DIR_CREATE_FAIL', 0);
        }
    }
    return array('sourceDir' => $appSourceDir,'deployDir' => $appDeployDir);
}

public function processSymlinks($yamlData, $path){
    $this->setupLinksAndBuild($path, $yamlData['app']['uuid']);
}

public function setupOrg(&$yamlData, $path = null){
    if (isset($yamlData['org'])) {
        $appId = $yamlData['app']['uuid'];
        $data = $this->processOrg($yamlData['org'], $appId);
        $orgId = $yamlData['org']['uuid'];
        $this->installApp($orgId, $yamlData, $path);
    }
}

public function installAppToOrg($appId,$orgId){
    $destination = $this->getAppSourceAndDeployDirectory($appId);
    $ymlData = self::loadAppDescriptor($destination['deployDir']);
    $this->installApp($orgId,$ymlData,$destination['deployDir']);
}

private function installApp($orgId, $yamlData, $path){
    try{
        $this->beginTransaction();
        $appId = $yamlData['app']['uuid'];
        $this->createRole($yamlData, false,$orgId);
        $user = $this->userService->getContactUserForOrg($orgId);
        $this->userService->addAppRolesToUser($user['userId'],$appId);
        $result = $this->createAppRegistry($appId, $orgId);
        $this->setupOrgLinks($path, $appId, $orgId);
        $this->commit();
    }catch(Exception $e){
        $this->rollback();
        throw $e;        
    }
    
}

public function processJob(&$yamlData) {
    $this->logger->info("Deploy App - Process Job with YamlData ");
    if(isset($yamlData['job'])){
        $appUuid = $yamlData['app']['uuid'];
        foreach ($yamlData['job'] as $data) {
            try {
                if(!isset($data['name']) || !isset($data['url']) || !isset($data['uuid']) || !isset($data['cron']) || !isset($data['data']))
                {
                    throw new ServiceException('Job Name/url/uuid/cron/data not specified', 'job.details.not.specified');                    
                }
                $jobName = $data['uuid'];
                $jobGroup = $data['name'];
                $jobPayload = array("job" => array("url" => $this->config['internalBaseUrl'] . $data['url'], "data" => $data['data']), "schedule" => array("cron" => $data['cron']));
                $cron = $data['cron'];
                $appId = isset($data['appId']) ? $data['appId'] : $appUuid;
                $appId = $this->getIdFromUuid('ox_app', $appId);
                $query = "SELECT id from ox_job where name = :jobName and group_name = :groupName and app_id = :appId";
                $params = array('jobName' => $jobName, 'groupName' => $jobGroup, 'appId' => $appId);
                $result = $this->executeQueryWithBindParameters($query, $params)->toArray();
                if(isset($result) && !empty($result))
                {
                    $cancel = $this->jobService->cancelJob($jobName, $jobGroup, $appUuid);
                }
                $this->logger->info("executing schedule job ");
                $response = $this->jobService->scheduleNewJob($jobName, $jobGroup, $jobPayload, $cron, $appUuid);
            }
            catch (Exception $e) {
                $this->logger->info("there is an exception: ");
                $response = json_decode($e->getCode());
                if($response == 404){
                    $this->logger->info("deleting from db ");
                    $query = "DELETE from ox_job where name = :jobName and group_name = :groupName and app_id = :appId";
                    $params = array('jobName' => $jobName, 'groupName' => $jobGroup, 'appId' => $appId);
                    $result = $this->executeQueryWithBindParameters($query, $params);
                    $this->logger->info("executing schedule job - ");
                    $response = $this->jobService->scheduleNewJob($jobName, $jobGroup, $jobPayload, $cron, $appUuid);
                }
                else
                {
                    $this->logger->info("Process Job ---- Exception" . print_r($e->getMessage(), true));
                    throw $e;
                }
            }
        }
    }     
}

public function processMenu(&$yamlData, $path)
{
    $this->logger->info("Deploy App - Process Menu with YamlData ");
    if (isset($yamlData['menu'])) {
        $appUuid = $yamlData['app']['uuid'];
        $sequence = 0;
        foreach ($yamlData['menu'] as &$menuData) {
            $menu = $menuData;
            $menu['sequence'] = $sequence++;
            $menu['privilege_name'] = isset($menu['privilege']) ? $menu['privilege'] : null;
            $menu['uuid'] = (isset($menu['uuid']) && !empty($menu['uuid'])) ? $menu['uuid'] : UuidUtil::uuid();
            $menuUpdated = $this->menuItemService->updateMenuItem($menu['uuid'], $menu);
            if ($menuUpdated == 0) {
                $count = $this->menuItemService->saveMenuItem($appUuid, $menu);
            }
            if(isset($menu['page_uuid'])){
                $menu['page_id'] = $this->getIdFromUuid('ox_app_page', $menu['page_uuid']);
            }else if (isset($menu['page'])) {
                $page = $this->pageService->getPageByName($appUuid,$menu['page']);
                if ($page) {
                    $menu['page_id'] = $page['id'];
                }else{
                    throw new ValidationException(['page or page_uuid' => 'required']);

                }
            }
            $count = $this->menuItemService->updateMenuItem($menu['uuid'], $menu);
            $menuData['uuid'] = (isset($menuData['uuid']) && !empty($menuData['uuid'])) ? $menuData['uuid'] : $menu['uuid'];
        }
    }
}

public function processPage(&$yamlData, $path)
{
    $this->logger->info("Deploy App - Process Page with YamlData ");
    if (isset($yamlData['pages'])) {
        $appUuid = $yamlData['app']['uuid'];
        $sequence = 0;
        foreach ($yamlData['pages'] as &$pageData) {
            if (isset($pageData['page_name']) && !empty($pageData['page_name'])) {
                $page = Yaml::parse(file_get_contents($path . 'content/pages/' . $pageData['page_name']));
            }else{
                $page = $pageData;
            }
            $pageId = isset($pageData['uuid']) ? $pageData['uuid'] : UuidUtil::uuid();
            $this->logger->info('the page data is: '.print_r($page, true));
            $routedata = array("appId" => $appUuid);
            $result = $this->pageService->savePage($routedata, $page, $pageId);
            $pageData['uuid'] = $page['uuid'];
        }
    }
}

public function processForm(&$yamlData, $path)
{
    if (isset($yamlData['form'])) {
        $appUuid = $yamlData['app']['uuid'];
        $entityReferences = $this->getEntityFieldReferences($yamlData, $path);
        foreach ($yamlData['form'] as &$form) {
            $form['uuid'] = isset($form['uuid']) ? $form['uuid'] : UuidUtil::uuid();
            $data = $form;
            $entity = $this->entityService->getEntityByName($appUuid, $data['entity']);
            if (!$entity) {
                $entity = array('name' => $data['entity']);
                $result = $this->entityService->saveEntity($appUuid, $entity);
            }
            $data['entity_id'] = $entity['id'];
            if (isset($data['template_file'])) {
                $data['template'] = file_get_contents($path . 'content/forms/' . $data['template_file']);
            }
            $fieldReference = NULL;
            if($entityReferences && isset($entityReferences[$data['entity']])){
                $fieldReference = $this->getFieldReference($entityReferences[$data['entity']]);
            }

            $count = $this->formService->updateForm($appUuid, $data['uuid'], $data, $fieldReference);
            if ($count == 0) {
                $this->formService->createForm($appUuid, $data, $fieldReference);
            }
            $form['uuid'] = isset($form['uuid']) ? $form['uuid'] : $data['uuid'];
        }
    }
}

private function getFieldReference($path){
    $parser = new SpreadsheetParserImpl();
    $parser->init($path);
    $sheetNames = $parser->getSheetNames();
    $rowMapper = new FormRowMapper();
    $filter = new SpreadsheetFilter();
    $filter->setRows(2);
    $fieldReference = $parser->parseDocument(array('rowMapper' => $rowMapper,
     'filter' => $filter));

    $fieldReference = $fieldReference[$sheetNames[0]];
    return $fieldReference;
}

private function getEntityFieldReferences($yamlData, $path){
    $entityReferences = array();
    if(isset($yamlData['entity']) && !empty($yamlData['entity'])){
        foreach ($yamlData['entity'] as $entity) {
            if(isset($entity['fieldReference'])){
                $fileRefPath = $path . 'content/entity/'.$entity['fieldReference'];
                if (FileUtils::fileExists($fileRefPath)) {
                    $entityReferences[$entity['name']] = $fileRefPath;
                }
            }
        }
    }
    return count($entityReferences) > 0 ? $entityReferences : NULL;
}

public function setupAppView($yamlData, $path)
{
    if (!is_dir($path . 'view')) {
        FileUtils::createDirectory($path . 'view');
    }
    if (!is_dir($path . 'view/apps/')) {
        FileUtils::createDirectory($path . 'view/apps/');
    }
    $appName = $path . 'view/apps/' . $yamlData['app']['name'];
    $metadataPath = $appName . '/metadata.json';
    $eoxapp = $this->config['DATA_FOLDER'] . 'eoxapps';
    if (!FileUtils::fileExists($appName) && !FileUtils::fileExists($metadataPath)) {
        FileUtils::copyDir($eoxapp, $path);
        FileUtils::renameFile($path . 'view/apps/eoxapps' ,$path . 'view/apps/' . $yamlData['app']['name']);
    }
    $jsonData = json_decode(file_get_contents($metadataPath),true);
    $jsonData['name'] = $yamlData['app']['name'];
    $jsonData['appId'] = $yamlData['app']['uuid'];
    $jsonData['title']['en_EN'] = $yamlData['app']['name'];
    if (isset($yamlData['app']['description'])) {
        $jsonData['description']['en_EN'] = $yamlData['app']['description'];
    }
    if (isset($yamlData['app']['autostart'])) {
        $jsonData['autostart'] = $yamlData['app']['autostart'];
    }
    file_put_contents($appName . '/metadata.json', json_encode($jsonData, JSON_PRETTY_PRINT));
    $packagePath = $appName . '/package.json';
    $jsonData = json_decode(file_get_contents($packagePath), true);
    $jsonData['name'] = $yamlData['app']['name'];
    file_put_contents($appName . '/package.json', json_encode($jsonData));
    $indexScssPath = $appName . '/index.scss';
    $indexfileData = file_get_contents($indexScssPath);
    $indexfileData2 = str_replace('{AppName}', $yamlData['app']['name'], $indexfileData);
    file_put_contents($appName . '/index.scss', $indexfileData2);
}


public function processWorkflow(&$yamlData, $path)
{
    if (isset($yamlData['workflow'])) {
        $appUuid = $yamlData['app']['uuid'];
        $workflowData = $yamlData['workflow'];
        foreach ($workflowData as $value) {
            $entityName = null;
            if(isset($value['entity'])) {
                $entityName = is_array($value['entity']) ? $value['entity'][0] : $value['entity'];
            }
            $result = 0;
            $result = $this->checkWorkflowData($value,$appUuid);
            if ($result == 0) {
                $entity = $this->entityService->getEntityByName($yamlData['app']['uuid'], $entityName);
                if (!$entity) {
                    $entity = array('name' => $entityName);
                    $result = $this->entityService->saveEntity($yamlData['app']['uuid'], $entity);
                }
                if (isset($value['uuid']) && isset($entity['id'])) {
                    $bpmnFilePath = $path . "content/workflows/" . $value['bpmn_file'];
                    $result = $this->workflowService->deploy($bpmnFilePath, $appUuid, $value, $entity['id']);
                }
            }
        }
    }
}

private function checkWorkflowData(&$data,$appUuid)
{
    $data['uuid'] = isset($data['uuid']) ? $data['uuid'] : UuidUtil::uuid();
    if (!(isset($data['bpmn_file']))) {
        $this->logger->warn("BPMN file not specified, hence deploy failed! ");
        return 1;
    }
    if (!isset($data['name'])) {
            $data['name'] = str_replace('.bpmn', '', $data['bpmn_file']); // Replaces all .bpmn with no space.
            $data['name'] = str_replace(' ', '_', $data['bpmn_file']); // Replaces all spaces
            $data['name'] = preg_replace('/[^A-Za-z0-9_]/', '', $data['name'], -1); // Removes special chars.
        }
        if (isset($data['entity'])) {
            $this->assignWorkflowToEntityMapping($data['entity'], $data['uuid'],$appUuid);
        } else {
            $this->logger->warn("Entity not given, deploy failed ! ");
            return 1;
        }
    }

    private function setLinkAndRunBuild($appPath, $appId){
        $defaultFolders = array(
            [
                "type" => "app",
                "sourceFolder" => $appPath . "view/apps/",
                "viewLink" => $this->config['APPS_FOLDER']
            ],
            [
                "type" => "theme",
                "sourceFolder" => $appPath . "view/themes/",
                "viewLink" => $this->config['THEME_FOLDER']
            ],
            [
                "type" => "gui",
                "sourceFolder" => $appPath . "view/gui/",
                "viewLink" => $this->config['GUI_FOLDER']
            ]
        );
        $buildFolders = [];
        foreach ($defaultFolders as $folderConfig) {
            if (file_exists($folderConfig["sourceFolder"]) && is_dir($folderConfig["sourceFolder"])) {
                if ($folderConfig["type"] == "gui") {
                    $files = array($folderConfig["sourceFolder"]);
                } else {
                    $files = new FileSystemIterator($folderConfig["sourceFolder"]);
                }

                foreach ($files as $file) {
                    $this->logger->info("\n Symlinking files - " . print_r($folderConfig["type"] . "->" . $file, true));
                    try {
                        $checkDirectory = $file->isDir();
                    } catch (\Throwable $th) {
                        $checkDirectory = is_dir($file);
                    }
                    if ($checkDirectory) {

                        if ($folderConfig["type"] == "gui") {
                            $targetName = $folderConfig["sourceFolder"];
                            $linkName = $folderConfig["viewLink"] . $appId;
                        } else {
                            $targetName = $file->getPathName();
                            $linkName = $folderConfig["viewLink"] . $file->getFilename();
                        }

                        if (is_link($linkName)) {
                            unlink($linkName);
                        }
                        if (file_exists($targetName)) {
                            $this->setupLink($targetName, $linkName);
                            array_push($buildFolders, [
                                "path" => $targetName,
                                "type" => $folderConfig["type"]
                            ]);
                        }
                    }
                }
            }
        }

        if (count($buildFolders) > 0) {
            array_push($buildFolders, [
                "path" => $this->config['APPS_FOLDER'] . "../bos/",
                "type" => "bos"
            ]);
            $restClient = $this->restClient;
            $output = json_decode($restClient->post(($this->config['applicationUrl'] . "/installer"),
                ["folders" => $buildFolders]
            ), true);
            if ($output["status"] != "Success") {
                $this->logger->info("\n View Build Failed - App Path " . $appPath);
                throw new ServiceException('Failed to complete view build for the application.', 'E_APP_VIEW_BUILD_FAIL', 0);
            }
            $this->logger->info("\n Finished Building App (View) - " . print_r($output, true));
        }
    }

    private function setupLinksAndBuild($path, $appId)
    {
        $link = $this->config['DELEGATE_FOLDER'] . $appId;
        $target = $path . "data/delegate";
        if (is_link($link)) {
            FileUtils::unlink($link);
        }
        if (file_exists($target)) {
            $this->setupLink($target, $link);
        }
        $formlink = $this->config['FORM_FOLDER'] . $appId;
        $formsTarget = $path . "content/forms";
        if (is_link($formlink)) {
            FileUtils::unlink($formlink);
        }
        if (file_exists($formsTarget)) {
            $this->setupLink($formsTarget, $formlink);
        }

        $formlink = $this->config['PAGE_FOLDER'] . $appId;
        $formsTarget = $path . "content/pages";
        if (is_link($formlink)) {
            FileUtils::unlink($formlink);
        }
        if (file_exists($formsTarget)) {
            $this->setupLink($formsTarget, $formlink);
        }
        $formlink = $this->config['ENTITY_FOLDER'] . $appId;
        $formsTarget = $path . "content/entity";
        if (is_link($formlink)) {
            FileUtils::unlink($formlink);
        }
        if (file_exists($formsTarget)) {
            $this->setupLink($formsTarget, $formlink);
        }
      
        $this->setLinkAndRunBuild($path, $appId);
    }

    private function setupOrgLinks($path, $appId, $orgId){
        if ($orgId && $path) {
            $link = $this->config['TEMPLATE_FOLDER'] . $orgId;
            $target = $path . "data/template";
            if (is_link($link)) {
                FileUtils::unlink($link);
            }
            if (file_exists($target)) {
                $this->setupLink($target, $link);
            }
        }
        
    }

    private function setupLink($target, $link)
    {
        if (file_exists($link)) {
            $this->logger->error("Directory ${link} already exists. Cannot setup ${target}.");
            throw new DuplicateEntityException("Directory ${link} already exists. Cannot setup ${target}.", ['directory' => $link]);
        }
        if (!is_link($link)) {
            $this->logger->info("setting up link $link with $target");
            FileUtils::symlink($target, $link);
        }
    }

    public function performMigration($yamlData, $path)
    {
        $data = $yamlData['app'];
        $appName = $data['name'];
        $appId = $data['uuid'];
        $description = isset($data['description']) ? $data['description'] : $appName;
        $migration = new Migration($this->config, $appName, $appId, $description);
        if (file_exists($path . "/data/migrations/")) {
            $migrationFolder = $path . "/data/migrations/";
            $fileList = array_diff(scandir($migrationFolder), array(".", ".."));
            if (count($fileList) > 0) {
                $migration->migrate(realpath($migrationFolder));
            }
        }
    }

    public function processBusinessRoles(&$yamlData)
    {   
        if (isset($yamlData['businessRole']) && !empty($yamlData['businessRole'])) {
            $appId = $yamlData['app']['uuid'];
            foreach ($yamlData['businessRole'] as &$businessRole) {
                $bRole = $businessRole;
                $result = $this->businessRoleService->saveBusinessRole($appId, $bRole);
                $businessRole['uuid'] = $bRole['uuid'];
            }
        }
    }
    public function createRole(&$yamlData, $templateRole = true,$orgId = null)
    {
        if (isset($yamlData['role'])) {
            $params = null;
            if(!$templateRole){
                if (!$orgId) {
                    $this->logger->warn("Organization not provided not processing roles!");
                    return;
                }
                $params['orgId'] = $orgId;
            }
            $appId = $yamlData['app']['uuid'];
            foreach ($yamlData['role'] as &$roleData) {
                $role = $roleData;
                if (!isset($role['name'])) {
                    $this->logger->warn("Role name not provided continuing!");
                    continue;
                }
                $role['uuid'] = isset($role['uuid']) ? $role['uuid'] : UuidUtil::uuid();
                if(isset($role['businessRole'])){
                    $temp = $this->businessRoleService->getBusinessRoleByName($appId, $role['businessRole']);
                    if(count($temp) > 0){
                        $role['business_role_id'] = $temp[0]['id'];
                    }
                }
                $role['app_id'] = $this->getIdFromUuid('ox_app',$appId);
                if($templateRole){
                    $role['uuid'] = isset($role['uuid']) ? $role['uuid'] : UuidUtil::uuid();
                    $result = $this->roleService->saveTemplateRole($role, $role['uuid']);
                    $roleData['uuid'] = $role['uuid'];
                }else{
                    unset($role['uuid']);
                    $result = $this->roleService->saveRole($params, $role);
                }
                
            }
        }
    }

    private function processOrg(&$orgData, $appId)
    {
        if (!isset($orgData['uuid'])) {
            $orgData['uuid'] = UuidUtil::uuid();
        }
        if (!isset($orgData['contact'])) {
            $orgData['contact'] = array();
            $orgData['contact']['username'] = str_replace('@', '.', $orgData['email']);
            $orgData['contact']['firstname'] = 'Admin';
            $orgData['contact']['lastname'] = 'User';
            $orgData['contact']['email'] = $orgData['email'];
        }
        if (!isset($orgData['preferences'])) {
            $orgData['preferences'] = '{}';
        }
        $orgdata = $orgData;
        $orgdata['app_id'] = $appId;
        $result = $this->organizationService->saveOrganization($orgdata);
        //setup business offering
        if ($result == 0) {
            throw new ServiceException("Organization could not be saved", 'org.not.saved');
        }
        $orgData['uuid'] = $orgdata['uuid'];
        return $orgData;
    }

    private function createOrUpdateApp(&$ymlData)
    {
        $appData = &$ymlData['app'];
        //UUID takes precedence over name. Therefore UUID is checked first.
        if (array_key_exists('uuid', $appData) && !empty($appData['uuid'])) {
            $queryString = 'SELECT uuid, name FROM ox_app AS app WHERE uuid=:uuid';
            $queryParams = ['uuid' => $appData['uuid']];
        }
        //Application is queried by name only if UUID is not given.
        else {
            if (!array_key_exists('name', $appData) || empty($appData['name'])) {
                throw new ServiceException('Application UUID or name must be given.', 'ERR_INVALID_INPUT');
            }
            $queryString = 'SELECT uuid, name FROM ox_app AS app WHERE name=:name';
            $queryParams = ['name' => $appData['name']];
        }
        $queryResult = $this->executeQueryWithBindParameters($queryString, $queryParams)->toArray();

        //Create the app if not found.
        if (0 == count($queryResult)) {
            //UUID is invalid. Threfore remove it.
            unset($appData['uuid']);
            $temp = ['app' => $appData];
            $createResult = $this->createApp($temp);
            ArrayUtils::merge($appData, $createResult['app']);
            return;
        }

        //App is found. Update the app.
        $dbRow = $queryResult[0];
        if (isset($appData['name']) && !isset($appData['uuid'])) {
            $appData['uuid'] = $dbRow['uuid'];
        }
        if (isset($appData['uuid']) && !isset($appData['name'])) {
            $appData['name'] = $dbRow['name'];
        }

        //Ensure app source directory is setup before calling update.
        $this->setupOrUpdateApplicationDirectoryStructure($ymlData);
        $updateResult = $this->updateApp($appData['uuid'], $ymlData);
        ArrayUtils::merge($appData, $updateResult['app']);
        return;
    }

    public function createAppPrivileges($yamlData)
    {        
        if (isset($yamlData['privilege'])) {
            $appUuid = $yamlData['app']['uuid'];
            $privilegedata = $yamlData['privilege'];
            $privilegearray = array_unique(array_column($privilegedata, 'name'));
            $list = "'" . implode("', '", $privilegearray) . "'";
            $appId = $this->getIdFromUuid('ox_app', $appUuid);
            $this->privilegeService->saveAppPrivileges($appId, $privilegedata);
        }
    }

    public function getAppList($filterParams = null)
    {
        $pageSize = 20;
        $offset = 0;
        $where = "";
        $sort = "name";

        $cntQuery = "SELECT count(id) FROM `ox_app`";
        if (count($filterParams) > 0 || sizeof($filterParams) > 0) {
            $filterArray = json_decode($filterParams['filter'], true);
            if (isset($filterArray[0]['filter'])) {
                $filterlogic = isset($filterArray[0]['filter']['logic']) ? $filterArray[0]['filter']['logic'] : "AND";
                $filterList = $filterArray[0]['filter']['filters'];
                $where = " WHERE " . FilterUtils::filterArray($filterList, $filterlogic);
            }
            if (isset($filterArray[0]['sort']) && count($filterArray[0]['sort']) > 0) {
                $sort = $filterArray[0]['sort'];
                $sort = FilterUtils::sortArray($sort);
            }
            $pageSize = isset($filterArray[0]['take']) ? $filterArray[0]['take'] : 10;
            $offset = isset($filterArray[0]['skip']) ? $filterArray[0]['skip'] : 0;
        }
        $where .= strlen($where) > 0 ? " AND status!=1" : "WHERE status!=1";
        $sort = " ORDER BY " . $sort;
        $limit = " LIMIT " . $pageSize . " offset " . $offset;
        $resultSet = $this->executeQuerywithParams($cntQuery . $where);
        $count = $resultSet->toArray()[0]['count(id)'];
        if (0 == $count) {
            return;
        }
        $query = "SELECT * FROM `ox_app` " . $where . " " . $sort . " " . $limit;
        $resultSet = $this->executeQuerywithParams($query);
        $result = $resultSet->toArray();
        for ($x = 0; $x < sizeof($result); $x++) {
            $result[$x]['start_options'] = json_decode($result[$x]['start_options'], true);
        }

        return array('data' => $result, 'total' => $count);
    }

    public function updateApp($uuid, &$data)
    {
        $appData = $data['app'];
        if (array_key_exists('uuid', $appData) && ($uuid != $appData['uuid'])) {
            throw new InvalidParameterException('UUID in URL and UUID in data set are not matching.');
        }
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $appData);
        if (!file_exists($appSourceDir)) {
            throw new FileNotFoundException("Application source directory is not found.",
                ['directory' => $appSourceDir]);
        }
        $app = new App($this->table);
        $app->loadByUuid($uuid);
        if (array_key_exists('type', $appData)) {
            if ($app->getProperty('type') != $appData['type']) {
                throw new InvalidParameterException(
                    "Application 'type' cannot be changed after creating the app.");
            }
        }
        $app->assign($appData);
        try {
            $this->beginTransaction();
            $app->save();
            //IMPORTANT: Don't commit database transaction here.
            $appProperties = $app->getProperties();
            ArrayUtils::merge($appData, $appProperties);
            $data['app'] = $appData;
            if (App::MY_APP == $app->getProperty('type')) {
                $this->setupOrUpdateApplicationDirectoryStructure($data);
            }
            //Commit database transaction only after application setup is successful.
            $this->commit();
        }
        catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        return $data;
    }

    public function deleteApp($uuid)
    {
        $app = new App($this->table);
        $app->loadByUuid($uuid);
        $app->assign([
            'status' => App::DELETED
        ]);
        try {
            $this->beginTransaction();
            $app->save();
            $this->commit();
        }
        catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * @param $formArray
     * @return int|string
     * @throws Exception
     */
    public function getFormInsertFormat($formArray)
    {
        if (!empty($formArray['app-form-assign'])) {
            $formData['app_id'] = $formArray['app-id'];
            $formData['name'] = $formArray['app-form-assign']['form-name'];
            $formData['description'] = $formArray['app-form-assign']['form-description'];
            if (!empty($formArray['app-form-assign']['form-statuslist'])) {
                foreach ($formArray['app-form-assign']['form-statuslist'] as $statusArray) {
                    $stsData[$statusArray['status-value']] = $statusArray['status-text'];
                    $sts['data'] = $stsData;
                }
            }
            $formData['statuslist'] = json_encode($sts);
        }

        return $this->formService->createForm($formData);
    }

    public function createAppRegistry($appId, $orgId)
    {
        $sql = $this->getSqlObject();
        //Code to check if the app is already registered for the organization
        $queryString = "select count(ar.id) as count
        from ox_app_registry as ar
        inner join ox_app ap on ap.id = ar.app_id
        inner join ox_organization org on org.id = ar.org_id
        where ap.uuid = :appId and org.uuid = :orgId";
        $params = array("appId" => is_array($appId) ? $appId['value'] : $appId, "orgId" => $orgId);
        $resultSet = $this->executeQueryWithBindParameters($queryString, $params)->toArray();
        if ($resultSet[0]['count'] == 0) {
            try {
                $this->beginTransaction();
                $insert = "INSERT into ox_app_registry (app_id, org_id, start_options)
                select ap.id, org.id, ap.start_options from ox_app as ap, ox_organization as org where ap.uuid = :appId and org.uuid = :orgId";
                $params = array("appId" => $appId, "orgId" => $orgId);
                $result = $this->executeUpdateWithBindParameters($insert, $params);
                $this->commit();
                return $result->getAffectedRows();
            } catch (Exception $e) {
                $this->rollback();
                throw $e;
            }
        }
        return 0;
    }

    public function registerApps($data)
    {
        $apps = json_decode($data['applist'], true);
        unset($data);
        $list = array();
        for ($x = 0; $x < sizeof($apps); $x++) {
            $data['name'] = isset($apps[$x]['name']) ? $apps[$x]['name'] : null;
            array_push($list, $data);
        }
        $this->logger->info("Data modified before the transaction", $data);
        try {
            $appSingleArray = array_unique(array_map('current', $list));
            $this->beginTransaction();
            $this->updateAppStatus($appSingleArray,APP::PRE_BUILT,APP::DELETED);
            $this->updateAppStatus($appSingleArray,APP::MY_APP,APP::IN_DRAFT);
            $select = "SELECT name FROM ox_app where name in ('" . implode("','", $appSingleArray) . "')";
            $result = $this->executeQuerywithParams($select)->toArray();
            $result = array_unique(array_map('current', $result));
            for ($x = 0; $x < sizeof($apps); $x++) {
                $app = $apps[$x];
                if (!in_array($app['name'], $result)) {
                    if (!isset($app['uuid']) || (isset($app['uuid']) && $app['uuid'] == "NULL")) {
                        $appObj = new App($this->table);
                    }
                    else {
                        $appObj = new App($this->table);
                        $appObj->loadByUuid($app['uuid']);
                    }
                    $appObj->assign($app);
                    $appObj->assign([
                        'start_options' => isset($app['options']) ? json_encode($app['options']) : null,
                        'status' => App::PUBLISHED,
                        'type' => App::PRE_BUILT
                    ]);
                    $appObj->setCreatedBy(1);
                    $appObj->setCreatedDate(date('Y-m-d H:i:s'));
                    $appObj->save();
                } else {
                    $start_options = isset($app['options']) ? json_encode($app['options']) : null;
                    $category = isset($app['category']) ? $app['category'] : null;
                    $isdefault = isset($app['isdefault']) ? $app['isdefault'] : 0;
                    $modified_by = 1; //Why 1 is hard coded here?
                    $update = "UPDATE ox_app SET `start_options` = '" . $start_options . "', `category` = '" . $category . "',`isdefault` = " . $isdefault . ", `date_modified` = '" . date('Y-m-d H:i:s') . "',`modified_by` = " . $modified_by . " WHERE name = '" . $app['name'] . "'";
                    $updatequery = $this->executeQuerywithParams($update);
                }
            }
            $query = "SELECT id from `ox_app` WHERE isdefault = 1";
            $selectquery = $this->executeQuerywithParams($query)->toArray();
            $idList = array_unique(array_map('current', $selectquery));
            for ($i = 0; $i < sizeof($idList); $i++) {
                $insert = "INSERT INTO `ox_app_registry` (`org_id`,`app_id`,`date_created`)
                SELECT org.id, '" . $idList[$i] . "', now() from ox_organization as org
                where org.id not in(SELECT org_id FROM ox_app_registry WHERE app_id ='" . $idList[$i] . "')";
                $result = $this->runGenericQuery($insert);
            }
            $this->commit();
        }
        catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        return 1;
    }

    private function updateAppStatus($appSingleArray,$appType,$appStatus){
       $update = "UPDATE ox_app SET status = $appStatus where ox_app.name NOT IN ('" . implode("','", $appSingleArray) . "') and ox_app.type=".$appType;
       $this->runGenericQuery($update);
   }

   public function processEntity(&$yamlData, $assoc_id = null)
   {
    if (isset($yamlData['entity']) && !empty($yamlData['entity'])) {
        $appId = $yamlData['app']['uuid'];
        $sequence = 0;
        foreach ($yamlData['entity'] as &$entityData) {
            $entity = $entityData;
            $entity['assoc_id'] = $assoc_id;
            $entityRec = $this->entityService->getEntityByName($appId, $entity['name']);
            if (!$entityRec || $entityRec['assoc_id'] != $assoc_id) {
                $result = $this->entityService->saveEntity($appId, $entity);
            } else {
                $entity['id'] = $entityRec['id'];
                $entity['uuid'] = $entityRec['uuid'];
            }
            if(isset($entity['identifiers'])){
                $result = $this->entityService->saveIdentifiers($entity['id'], $entity['identifiers']);
            }
            if(isset($entity['field'])){
                foreach ($entity['field'] as $field) {
                    $result = $this->fieldService->getFieldByName($entity['uuid'], $field['name']);
                    if ($result == 0) {
                        $field['entity_id'] = $entity['id'];
                        $this->fieldService->saveField($appId, $field);
                    } else {
                        $this->fieldService->updateField($result['uuid'], $field);
                    }
                }
            }
            if (isset($entity['child']) && $entity['child']) {
                $childEntityData = ['entity' => $entityData['child'], 'app' => [['uuid' => $appId]]];
                $this->processEntity($childEntityData, $entity['id']);
                $entityData['child'] = $childEntityData['entity'];
            }
        }
    }
}

private function assignWorkflowToEntityMapping($entityArray ,$workflowUuid, $appUuid) {
    try {
        $data = array();
        $workflowId = $this->getIdFromUuid('ox_workflow',$workflowUuid);
        $entityArray = is_array($entityArray) ? $entityArray : array($entityArray);
        foreach ($entityArray as $entityName) {
            $entityData = $this->entityService->getEntityByName($appUuid,$entityName);
            if($entityData == null)
                continue;
            $individualEntry = array(
                'workflow_id' => $workflowId,
                'entity_id' => $entityData['id']
            );
            array_push($data, $individualEntry);
        }
        if(!empty($data)) {
            $this->multiInsertOrUpdate('ox_workflow_entity_mapper', $data);
        }
    } catch (Exception $e) {
        $this->logger->error($e->getMessage(), $e);
        throw $e;
    }
}

private function cleanApplicationDescriptorData($descriptorData)
{
    if (isset($descriptorData["entity"])) {
        $descriptorData["entity"] = array_map(function ($entity) {
            if (isset($entity["formFieldsValidationExcel"]) && empty($entity['formFieldsValidationExcel'])) {
                unset($entity["formFieldsValidationExcel"]);
            }
            if (isset($entity['field']) && array_key_exists('name',$entity['field'][0])&& empty($entity['field'][0]['name'])) {
                unset($entity['field']);
            }
            return $entity;
        }, $descriptorData["entity"]);
    }
     if (isset($descriptorData["menu"])) {
        $descriptorData["menu"] = array_map(function ($menu) {
            if (isset($menu["privilege"]) && empty($menu['privilege'])) {
                unset($menu["privilege"]);
            }
            if (isset($menu["parent"]) && empty($menu['parent'])) {
                unset($menu["parent"]);
            }
            return $menu;
        }, $descriptorData["menu"]);
    }
    
    if (isset($descriptorData["form"]) && empty($descriptorData['form'][0]['name'])) {
        unset($descriptorData["form"]);
    }
    if (isset($descriptorData["org"]) && empty($descriptorData['org']['name'])) {
        unset($descriptorData["org"]);
    }
    if (isset($descriptorData["workflow"]) && empty($descriptorData['workflow'][0]['name'])) {
        unset($descriptorData["workflow"]);
    }
    return $descriptorData;
}
}