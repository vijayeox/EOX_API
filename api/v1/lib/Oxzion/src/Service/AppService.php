<?php
namespace Oxzion\Service;

use Exception;
use FileSystemIterator;
use Oxzion\App\AppArtifactNamingStrategy;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\Db\Migration\Migration;
use Oxzion\Document\Parser\Form\FormRowMapper;
use Oxzion\Document\Parser\Spreadsheet\SpreadsheetFilter;
use Oxzion\Document\Parser\Spreadsheet\SpreadsheetParserImpl;
use Oxzion\DuplicateEntityException;
use Oxzion\EntityNotFoundException;
use Oxzion\FileContentException;
use Oxzion\FileNotFoundException;
use Oxzion\InvalidParameterException;
use Oxzion\Messaging\MessageProducer;
use Oxzion\Model\App;
use Oxzion\Model\AppTable;
use Oxzion\ServiceException;
use Oxzion\Service\AbstractService;
use Oxzion\Service\AppRegistryService;
use Oxzion\Service\BusinessRoleService;
use Oxzion\Service\FieldService;
use Oxzion\Service\FormService;
use Oxzion\Service\JobService;
use Oxzion\Service\UserService;
use Oxzion\Service\WorkflowService;
use Oxzion\Utils\ArrayUtils;
use Oxzion\Utils\FileUtils;
use Oxzion\Utils\FilterUtils;
use Oxzion\Utils\RestClient;
use Oxzion\Utils\UuidUtil;
use Oxzion\ValidationException;
use Symfony\Component\Yaml\Yaml;

class AppService extends AbstractService
{
    const EOX_RESERVED_APP_NAME = 'SampleApp';
    const APPLICATION_DESCRIPTOR_FILE_NAME = 'application.yml';

    private $table;
    protected $workflowService;
    protected $fieldService;
    protected $formService;
    protected $accountService;
    protected $entityService;
    private $privilegeService;
    private $menuItemService;
    private $pageService;
    private $jobService;
    private $appDeployOptions;
    private $roleService;
    private $userService;
    private $businessRoleService;
    private $appRegistryService;
    private $messageProducer;

    public function setMessageProducer($messageProducer)
    {
        $this->messageProducer = $messageProducer;
    }

    /**
     * @ignore __construct
     */
    public function __construct($config, $dbAdapter, AppTable $table, WorkflowService $workflowService, FormService $formService, FieldService $fieldService, JobService $jobService, $accountService, $entityService, $privilegeService, $roleService, $menuItemService, $pageService, UserService $userService, BusinessRoleService $businessRoleService, AppRegistryService $appRegistryService, MessageProducer $messageProducer)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
        $this->workflowService = $workflowService;
        $this->formService = $formService;
        $this->fieldService = $fieldService;
        $this->accountService = $accountService;
        $this->entityService = $entityService;
        $this->privilegeService = $privilegeService;
        $this->roleService = $roleService;
        $this->menuItemService = $menuItemService;
        $this->pageService = $pageService;
        $this->jobService = $jobService;
        $this->businessRoleService = $businessRoleService;
        $this->appRegistryService = $appRegistryService;
        $this->userService = $userService;
        $this->messageProducer = $messageProducer;
        $this->restClient = new RestClient(null);
        $this->appDeployOptions = array("initialize", "entity", "workflow", "form", "page", "menu", "job", "migration", "view", "symlink");
    }

    public function setRestClient($restClient)
    {
        $this->restClient = $restClient;
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
        ap.date_modified, ap.created_by, ap.modified_by, ap.status, a.uuid as accountId, ar.start_options
        FROM ox_app AS ap
        LEFT JOIN ox_app_registry AS ar ON ap.id = ar.app_id
        LEFT JOIN ox_account a on a.id = ar.account_id
        WHERE ar.account_id=:accountId AND ap.status <> :status AND ap.name <> \'' . AppService::EOX_RESERVED_APP_NAME . '\'';
        $queryParams = [
            'accountId' => AuthContext::get(AuthConstants::ACCOUNT_ID),
            'status' => App::DELETED
        ];
        $resultSet = $this->executeQueryWithBindParameters($queryString, $queryParams)->toArray();
        if (empty($resultSet)) {
            throw new EntityNotFoundException('No active registered apps found for logged-in user\'s account.', null);
        }
        return $resultSet;
    }

    public function getApp($uuid, $viewPath = null)
    {
        $queryString = 'SELECT ap.name, ap.uuid
        FROM ox_app AS ap
        LEFT JOIN ox_app_registry AS ar ON ap.id = ar.app_id AND ar.account_id=:accountId
        LEFT JOIN ox_account a on a.id = ar.account_id
        WHERE ap.status <> :statusDeleted AND ap.uuid=:uuid';
        $queryParams = [
            'accountId' => AuthContext::get(AuthConstants::ACCOUNT_ID),
            'statusDeleted' => App::DELETED,
            'uuid' => $uuid
        ];
        $resultSet = $this->executeQueryWithBindParameters($queryString, $queryParams)->toArray();
        if (is_null($resultSet) || empty($resultSet)) {
            throw new EntityNotFoundException('Entity not found.', ['entity' => 'Active registered app for the logged-in user\'s account', 'uuid' => $uuid]);
        }
        $appData = $resultSet[0];
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $appData);
        if ($viewPath) {
            if (file_exists($appSourceDir . '/view/apps/' . $appData['name'])) {
                return $appSourceDir . '/view/apps/' . $appData['name'];
            } else {
                return $appSourceDir . '/view/apps/eoxapps';
            }
        }
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
        $appData['app_properties'] = json_encode(array("chat_notification" => isset($appData['chat_notification']) ? $appData['chat_notification'] : ""));
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
            if (isset($data['app']['app_properties'])) {
                $appProperties = json_decode($data['app']['app_properties'], true);
                $chatNotification = $appProperties['chat_notification'];
            }
            if ($chatNotification === true) {
                $this->messageProducer->sendTopic(json_encode(array('appName' => $data['app']['name'], 'displayName' => $data['app']['title'])), 'SAVE_CHAT_BOT');
            }
            //Commit database transaction only after application setup is successful.
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        $appData = array('app' => $data['app']);
        return $appData;
    }

    //Creates the source directory for the application.
    //Copies contents of template application (<DATA DIRECTORY>/eoxapps) to source directory.
    //Creates application.yml file in the source directory.
    //Writes $appData contents to application.yml file.
    public function setupOrUpdateApplicationDirectoryStructure($descriptorData)
    {
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

    private function createOrUpdateApplicationDescriptor($dirPath, $descriptorData)
    {
        $descriptorFilePath = $dirPath .
        ((DIRECTORY_SEPARATOR == substr($dirPath, -1)) ? '' : DIRECTORY_SEPARATOR) . self::APPLICATION_DESCRIPTOR_FILE_NAME;
        if (file_exists($descriptorFilePath)) {
            $descriptorDataFromFile = self::loadAppDescriptor($dirPath);
            ArrayUtils::merge($descriptorDataFromFile, $descriptorData);
            $yamlText = Yaml::dump($descriptorDataFromFile, 20);
        } else {
            $yamlText = Yaml::dump($descriptorData, 20);
        }
        $yamlWriteResult = file_put_contents($descriptorFilePath, $yamlText);
        if (!$yamlWriteResult) {
            $this->logger->error("Failed to create application YAML file ${descriptorFilePath}.");
        }
        return $descriptorData;
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
                'Application information not found in application descriptor YAML file.',
                $filePath
            );
        }
        return $yaml;
    }

    public function deployApp($path, $params = null)
    {
        $ymlData = $this->cleanApplicationDescriptorData(self::loadAppDescriptor($path));
        $this->logger->info("\n Yaml Data " . print_r($ymlData, true));
        if (!isset($params)) {
            $params = $this->appDeployOptions;
        }
        try {
            foreach ($this->appDeployOptions as $key => $value) {
                if (!in_array($value, $params)) {
                    continue;
                }
                $this->logger->info("\n App Data processing - " . print_r($value, true));
                switch ($value) {
                    case 'initialize':
                        $temp = $this->createOrUpdateApp($ymlData);
                        if ($temp) {
                            FileUtils::copyDir($path, $temp);
                            $originalPath = $path;
                            $path = $temp;
                        }
                        $this->processBusinessRoles($ymlData);
                        $this->createAppPrivileges($ymlData);
                        $this->createRole($ymlData);
                        $this->performMigration($ymlData, $path);
                        break;
                    case 'entity':
                        $this->processEntity($ymlData);
                        break;
                    case 'migration':
                        $this->performMigration($ymlData, $path);
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
                        $this->saveAppCss($ymlData);
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
            $this->processInstalledTemplates($appData['uuid'], $path);
            $this->updateApp($appData['uuid'], $ymlData); //Update is needed because app status changed to PUBLISHED.
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            $this->removeViewAppOnError($path);
            throw $e;
        } finally {
            $this->setupOrUpdateApplicationDirectoryStructure($ymlData);
            if (isset($originalPath)) {
                $this->createOrUpdateApplicationDescriptor($originalPath, $ymlData);
            }
        }
        return $ymlData;
    }

    public function saveAppCss($ymlData)
    {
        $data = ['uuid' => $ymlData['app']['uuid'],
            'name' => $ymlData['app']['name']];
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data);
        if (file_exists($appSourceDir . '/view/apps/' . $data['name'])) {
            $res = $appSourceDir . '/view/apps/' . $data['name'];
        } else {
            $res = $appSourceDir . '/view/apps/eoxapps';
        }
        if (file_exists($res . '/index.scss')) {
            $path = pathinfo($res . '/index.scss');
            FileUtils::copy($res . '/index.scss', $path['filename'] . '_' . date("Y-m-d H:i:s") . '.' . $path['extension'], $res);
        }
        $this->logger->info("RED---" . print_r($res, true));
        if (isset($ymlData['cssContent']) && !empty($ymlData['cssContent'])) {
            file_put_contents($res . '/index.scss', $ymlData['cssContent']);
            // unset($ymlData['cssContent']);
        }
    }

    private function removeViewAppOnError($path)
    {
        $targetPath = FileUtils::joinPath($path) . "view/apps/eoxapps";
        $this->logger->info("TARGET PATH---" . print_r($targetPath, true));
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
        try {
            $result = $this->deployApp($appDeployDir, $params);
        } finally {
            FileUtils::copy($appDeployDir . "application.yml", "application.yml", $appSourceDir);
        }
        return $result;
    }

    private function getAppSourceAndDeployDirectory($appId)
    {
        $query = 'SELECT name, type FROM ox_app WHERE uuid=:appId';
        $queryParams = array('appId' => $appId);
        $result = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
        if (!isset($result) || empty($result) || (count($result) != 1)) {
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
        return array('sourceDir' => $appSourceDir, 'deployDir' => $appDeployDir);
    }

    public function processSymlinks($yamlData, $path)
    {
        $this->setupLinksAndBuild($path, $yamlData['app']['uuid']);
    }

    public function setupOrg(&$yamlData, $path = null)
    {
        if (isset($yamlData['org'])) {
            $appId = $yamlData['app']['uuid'];
            $orgType = $this->checkSingleOrMultipleOrg($yamlData['org']);
            if ($orgType === 'Single') {
                $data = $this->processOrg($yamlData['org'], $appId);
                $orgId = $yamlData['org']['uuid'];
                $this->installApp($orgId, $yamlData, $path);
            } elseif ($orgType === 'Multiple') {
                $ymlDataCopy = $yamlData;
                foreach ($yamlData['org'] as $org) {
                    unset($ymlDataCopy['org']);
                    $ymlDataCopy['org'] = $org;
                    $data = $this->processOrg($org, $appId);
                    $orgId = $org['uuid'];
                    $this->installApp($orgId, $ymlDataCopy, $path);
                }
            } else {
                throw new ServiceException('Failed Installing Organisation', 'org.install.failed');
            }
        } elseif (isset($yamlData['app']['autoinstall']) && $yamlData['app']['autoinstall'] == true && App::PRE_BUILT == $yamlData['app']['type']) {
            $this->installApp(AuthContext::get(AuthConstants::ACCOUNT_UUID), $yamlData, $path);
        }
    }

    private function checkSingleOrMultipleOrg($org)
    {
        if (array_key_exists(0, $org)) {
            return 'Multiple';
        } else {
            return 'Single';
        }
    }

    public function installAppToOrg($appId, $accountId, $serviceType)
    {
        $destination = $this->getAppSourceAndDeployDirectory($appId);
        $ymlData = self::loadAppDescriptor($destination['deployDir']);
        switch ($serviceType) {
            case 'install':
                $this->installApp($accountId, $ymlData, $destination['deployDir']);
                break;
            case 'uninstall':
                $this->uninstallApp($accountId, $ymlData, $destination['deployDir']);
                break;
            default:
                # code...
                break;
        }
    }

    private function installApp($accountId, $yamlData, $path)
    {
        try {
            $this->beginTransaction();
            $appId = $yamlData['app']['uuid'];
            $bRoleResult = ((isset($yamlData['org']))) ? $this->accountService->setupBusinessOfferings($yamlData['org'], $accountId, $appId) : null;
            $this->createRole($yamlData, false, $accountId, $bRoleResult);
            $user = $this->accountService->getContactUserForAccount($accountId);
            $this->userService->addAppRolesToUser($user['accountUserId'], $appId);
            $startOptions = $this->getAppStartOptions($appId, $yamlData['org']);
            $result = $this->appRegistryService->createAppRegistry($appId, $accountId, $startOptions);
            $this->logger->info("PATH--- $path");
            $this->setupAccountFiles($path, $accountId, $appId);
            // Assign AppRoles to Logged in User if Logged in Org and Installed Org are same
            if (AuthContext::get(AuthConstants::ACCOUNT_UUID) == $accountId) {
                $user = $this->getDataByParams('ox_account_user', array('id'), array('user_id' => AuthContext::get(AuthConstants::USER_ID), 'account_id' => AuthContext::get(AuthConstants::ACCOUNT_ID)))->toArray();
                $this->userService->addAppRolesToUser($user[0]['id'], $appId);
            }
            $this->processJobsForAccount($appId, $accountId);
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function getAppStartOptions($appId, $yamlOrgData)
    {
        $appStartOptions = $this->getDataByParams('ox_app', array('start_options'), array('uuid' => $appId))->toArray();
        if (count($appStartOptions) > 0) {
            if (is_string($appStartOptions[0]['start_options'])) {
                $startOptions = json_decode($appStartOptions[0]['start_options'], true);
            } else {
                $startOptions = $appStartOptions[0]['start_options'];
            }
        }
        if (!isset($startOptions)) {

            $startOptions = [];
        }
        if (isset($yamlOrgData['start_options'])) {
            if (is_string($yamlOrgData['start_options'])) {
                $ymlOrgStartOptions = json_decode($yamlOrgData['start_options'], true);
            } else {
                $ymlOrgStartOptions = $yamlOrgData['start_options'];
            }
        }
        if (!isset($ymlOrgStartOptions)) {
            $ymlOrgStartOptions = [];
        }

        return array_merge($startOptions, $ymlOrgStartOptions);
    }

    public function processJobsForAccount($appId, $accountId)
    {
        $appId = is_numeric($appId) ? $appId : $this->getIdFromUuid('ox_app', $appId);
        $accountId = is_numeric($accountId) ? $accountId : $this->getIdFromUuid('ox_account', $accountId);
        $select = "SELECT * from ox_job where app_id=:appId and account_id IS NULL";
        $params = ['appId' => $appId];
        $result = $this->executeQueryWithBindParameters($select, $params)->toArray();
        foreach ($result as $jobData) {
            $config = json_decode($jobData['config'], true);
            $this->logger->info("OOOO---\n" . print_r($config, true));
            $this->jobService->scheduleNewJob($jobData['name'], $jobData['group_name'], $config, $config['schedule']['cron'], $appId, $accountId);
        }
    }

    private function uninstallApp($accountId, $yamlData, $path)
    {
        try {
            $this->beginTransaction();
            $appId = $yamlData['app']['uuid'];
            $this->accountService->removeBusinessOfferings($accountId);
            $this->removeRoleData($appId, $accountId);
            $this->removeAccountFiles($accountId);
            $this->uninstallJobsForAccount($appId, $accountId);
            $this->commit();
        } catch (Exception $e) {
            $this->logger->info("there is an uninstallexception: ");
            $this->rollback();
            throw $e;
        }
    }

    public function uninstallJobsForAccount($appId, $accountId)
    {
        $appId = is_numeric($appId) ? $appId : $this->getIdFromUuid('ox_app', $appId);
        $accountId = is_numeric($accountId) ? $accountId : $this->getIdFromUuid('ox_account', $accountId);
        $select = "SELECT * from ox_job where app_id=:appId and account_id=:accountId";
        $params = ['appId' => $appId, 'accountId' => $accountId];
        $result = $this->executeQueryWithBindParameters($select, $params)->toArray();
        foreach ($result as $jobs) {
            $this->jobService->cancelJob($jobs['name'], $jobs['group_name'], $appId, $accountId);
        }
    }

    private function removeRoleData($appId, $accountId = null)
    {
        $appId = $this->getIdFromUuid('ox_app', $appId);
        $accountId = ($accountId) ? $this->getIdFromUuid('ox_account', $accountId) : 0;
        $accountId = ($accountId == 0) ? null : $accountId;
        $deleteParams = ['app_id' => $appId];
        if ($accountId) {
            $deleteParams['account_id'] = $accountId;
        }
        $roleResult = $this->getDataByParams('ox_role', array(), $deleteParams)->toArray();
        if (count($roleResult) > 0) {
            $this->deleteData($roleResult, 'ox_role_privilege', 'role_id', 'id');
            $this->deleteData($roleResult, 'ox_user_role', 'role_id', 'id');
            $this->deleteData($roleResult, 'ox_role', 'id', 'id');
        }
        $result = $this->getDataByParams('ox_app_registry', array(), $deleteParams)->toArray();
        if (count($result) > 0) {
            $this->deleteInfo('ox_app_registry', $deleteParams['app_id'], $deleteParams['account_id']);
        }
    }

    private function deleteData(array $result, string $tableName, string $columnName, string $uniqueColumn)
    {
        $appSingleArray = array_unique(array_column($result, $uniqueColumn));
        $deleteQuery = "DELETE FROM $tableName where $columnName in ('" . implode("','", $appSingleArray) . "')";
        $this->logger->info("QUERY---- $deleteQuery");
        $deleteResult = $this->executeQuerywithParams($deleteQuery);
    }

    private function deleteInfo(string $tableName, $appId, int $accountId = null)
    {
        $appId = is_numeric($appId) ? $appId : $this->getIdFromUuid('ox_app', $appId);
        $deleteParams = ['appId' => $appId];
        $where = "WHERE app_id=:appId";
        if ($accountId) {
            $where .= " and account_id=:accountId";
            $deleteParams['accountId'] = $accountId;
        }
        $deleteQuery = "DELETE FROM $tableName $where";
        $this->logger->info("STATEMENT delq $deleteQuery" . print_r($deleteParams, true));
        $this->executeUpdateWithBindParameters($deleteQuery, $deleteParams);
    }

    private function removeAccountFiles($accountId)
    {
        if ($accountId) {
            $link = $this->config['TEMPLATE_FOLDER'] . $accountId;
            FileUtils::rmDir($link);
        }
    }

    public function processJob(&$yamlData)
    {
        $this->logger->info("Deploy App - Process Job with YamlData ");
        if (isset($yamlData['job'])) {
            $appUuid = $yamlData['app']['uuid'];
            $this->processDeletedJobs($yamlData['job'], $appUuid);
            foreach ($yamlData['job'] as &$job) {
                try {
                    $job['uuid'] = isset($job['uuid']) ? $job['uuid'] : UuidUtil::uuid();
                    $data = $job;
                    if (!isset($data['name']) || !isset($data['url']) || !isset($data['uuid']) || !isset($data['cron'])) {
                        throw new ServiceException('Job Name/url/uuid/cron not specified', 'job.details.not.specified');
                    }
                    $jobName = $data['uuid'];
                    $jobTeam = $data['name'];
                    if (!isset($data['data'])) {
                        $data['data'] = [];
                    }
                    if (!isset($data['data']['appId'])) {
                        $data['data']['appId'] = $appUuid;
                    }
                    $appId = $this->getIdFromUuid('ox_app', $appUuid);
                    $jobPayload = array("job" => array("url" => $this->config['internalBaseUrl'] . $data['url'], "data" => $data['data']), "schedule" => array("cron" => $data['cron']));
                    $cron = $data['cron'];
                    $response = $this->jobService->scheduleNewJob($jobName, $jobTeam, $jobPayload, $cron, $appUuid);
                    $this->processJobsForInstalledAccount($jobName, $jobTeam, $jobPayload, $cron, $appUuid);
                } catch (Exception $e) {
                    $this->logger->info("there is an exception: ");
                    $response = json_decode($e->getCode());
                    if ($response == 404) {
                        $this->logger->info("deleting from db ");
                        $query = "DELETE from ox_job where name = :jobName and group_name = :teamName and app_id = :appId";
                        $params = array('jobName' => $jobName, 'teamName' => $jobTeam, 'appId' => $appId);
                        $result = $this->executeQueryWithBindParameters($query, $params);
                        $this->logger->info("executing schedule job - ");
                        $response = $this->jobService->scheduleNewJob($jobName, $jobTeam, $jobPayload, $cron, $appUuid);
                    } else {
                        $this->logger->info("Process Job ---- Exception" . print_r($e->getMessage(), true));
                        throw $e;
                    }
                }
            }
        }
    }

    private function processDeletedJobs($jobData, $appId)
    {
        $appId = !is_numeric($appId) ? $this->getIdFromUuid('ox_app', $appId) : $appId;
        $yamlJobData = array_unique(array_column($jobData, 'uuid'));
        $list = "'" . implode("', '", $yamlJobData) . "'";
        $select = "SELECT * from ox_job where app_id=:appId and name NOT IN ($list)";
        $params = ['appId' => $appId];
        $result = $this->executeQueryWithBindParameters($select, $params)->toArray();
        foreach ($result as $jobs) {
            if (is_null($jobs['account_id'])) {
                $query = 'DELETE from ox_job where id = :Id';
                $params = array('Id' => $jobs['id']);
                $this->executeUpdateWithBindParameters($query, $params);
            } else {
                $this->jobService->cancelJob($jobs['name'], $jobs['group_name'], $appId, $jobs['account_id']);
            }
        }
    }
    private function processJobsForInstalledAccount($jobName, $jobTeam, $jobPayload, $cron, $appId)
    {
        $this->logger->info("CROn---\n" . print_r($cron, true));
        $appId = !is_numeric($appId) ? $this->getIdFromUuid('ox_app', $appId) : $appId;
        $select = "SELECT oxar.account_id from ox_app_registry oxar where oxar.app_id=:appId";
        $params = ['appId' => $appId];
        $result = $this->executeQueryWithBindParameters($select, $params)->toArray();
        $this->logger->info("DRF---" . print_r($result, true));
        foreach ($result as $account) {
            $this->jobService->scheduleNewJob($jobName, $jobTeam, $jobPayload, $cron, $appId, $account['account_id']);
        }
    }

    public function processMenu(&$yamlData, $path)
    {
        $this->logger->info("Deploy App - Process Menu with YamlData ");
        if (isset($yamlData['menu'])) {
            $appUuid = $yamlData['app']['uuid'];
            $sequence = 0;
            $yamlMenuList = array_unique(array_column($yamlData['menu'], 'uuid'));
            $list = "'" . implode("', '", $yamlMenuList) . "'";
            $menuList = $this->getDataByParams('ox_app_menu', array(), array('app_id' => $this->getIdFromUuid('ox_app', $yamlData['app']['uuid'])))->toArray();
            if (count($menuList) > count($yamlData['menu'])) {
                $deleteQuery = "DELETE FROM ox_app_menu WHERE app_id=:appId and uuid NOT IN ($list)";
                $deleteParams = array('appId' => $this->getIdFromUuid('ox_app', $appUuid));
                $deleteResult = $this->executeUpdateWithBindParameters($deleteQuery, $deleteParams);
            }
            foreach ($yamlData['menu'] as &$menuData) {
                $menu = $menuData;
                $menu['sequence'] = $sequence++;
                $menu['privilege_name'] = isset($menu['privilege']) ? $menu['privilege'] : null;
                $menu['uuid'] = (isset($menu['uuid']) && !empty($menu['uuid'])) ? $menu['uuid'] : UuidUtil::uuid();
                $menuUpdated = $this->menuItemService->updateMenuItem($menu['uuid'], $menu);
                if ($menuUpdated == 0) {
                    $count = $this->menuItemService->saveMenuItem($appUuid, $menu);
                }
                if (isset($menu['page_uuid'])) {
                    $menu['page_id'] = $this->getIdFromUuid('ox_app_page', $menu['page_uuid']);
                } elseif (isset($menu['page'])) {
                    $page = $this->pageService->getPageByName($appUuid, $menu['page']);
                    if ($page) {
                        $menu['page_id'] = $page['id'];
                    } else {
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
                if (isset($pageData['page_name']) && !empty($pageData['page_name']) && file_exists($path . 'content/pages/' . $pageData['page_name'])) {
                    $page = Yaml::parse(file_get_contents($path . 'content/pages/' . $pageData['page_name']));
                } else {
                    $page = $pageData;
                }
                $pageId = isset($pageData['uuid']) ? $pageData['uuid'] : UuidUtil::uuid();
                $this->logger->info('the page data is: ' . print_r($page, true));
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
                if (isset($data['template']) && is_array($data['template'])) {
                    $data['template'] = json_encode($data['template']);
                }
                $fieldReference = null;
                if ($entityReferences && isset($entityReferences[$data['entity']])) {
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

    private function getFieldReference($path)
    {
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

    private function getEntityFieldReferences($yamlData, $path)
    {
        $entityReferences = array();
        if (isset($yamlData['entity']) && !empty($yamlData['entity'])) {
            foreach ($yamlData['entity'] as $entity) {
                if (isset($entity['fieldReference'])) {
                    $fileRefPath = $path . 'content/entity/' . $entity['fieldReference'];
                    if (FileUtils::fileExists($fileRefPath)) {
                        $entityReferences[$entity['name']] = $fileRefPath;
                    }
                }
            }
        }
        return count($entityReferences) > 0 ? $entityReferences : null;
    }

    public function setupAppView($yamlData, $path)
    {
        if (!is_dir($path . 'view')) {
            FileUtils::createDirectory($path . 'view');
        }
        if (!is_dir($path . 'view/apps/')) {
            FileUtils::createDirectory($path . 'view/apps/');
        }
        $this->logger->info("ppp--" . print_r($path, true));
        if (isset($yamlData['app']['oldAppName']) && !empty($yamlData['app']['oldAppName']) && $yamlData['app']['name'] != $yamlData['app']['oldAppName']) {
            $this->logger->info("OLDNME---" . print_r($path . 'view/apps/' . $yamlData['app']['oldAppName'], true));
            if (is_dir($path . 'view/apps/' . $yamlData['app']['oldAppName'])) {
                FileUtils::rmDir($path . 'view/apps/' . $yamlData['app']['oldAppName']);
            }
            $this->removeAppAndExecutePackageDiscover($yamlData['app']['oldAppName']);
            if (isset($ymlData['app']['oldAppName'])) {
                unset($ymlData['app']['oldAppName']);
            }
        }
        $appName = $path . 'view/apps/' . $yamlData['app']['name'];
        $metadataPath = $appName . '/metadata.json';
        $eoxapp = $this->config['DATA_FOLDER'] . 'eoxapps';
        if (!FileUtils::fileExists($appName) && !FileUtils::fileExists($metadataPath)) {
            FileUtils::renameFile($path . 'view/apps/eoxapps', $path . 'view/apps/' . $yamlData['app']['name']);
        } else {
            if (is_dir($path . 'view/apps/eoxapps')) {
                FileUtils::rmDir($path . 'view/apps/eoxapps');
            }
            $srcIconPath = $path . '../../AppSource/' . $yamlData['app']['uuid'] . '/view/apps/eoxapps/';
            if (is_dir($srcIconPath)) {
                FileUtils::copy($srcIconPath . 'icon.png', "icon.png", $appName);
                FileUtils::copy($srcIconPath . 'icon_white.png', "icon_white.png", $appName);
                FileUtils::copy($srcIconPath . 'index.scss', "index.scss", $appName); // Copy css from Source to Deploy directory
            }
        }
        $jsonData = json_decode(file_get_contents($metadataPath), true);
        $jsonData['name'] = $yamlData['app']['name'];
        $jsonData['appId'] = $yamlData['app']['uuid'];
        $jsonData['category'] = isset($yamlData['app']['category']) ? $yamlData['app']['category'] : null;
        $displayName = $jsonData['title']['en_EN'] = ($yamlData['app']['name'] == 'EOXAppBuilder') ? 'AppBuilder' : (isset($yamlData['app']['title']) ? $yamlData['app']['title'] : $yamlData['app']['name']);
        if (isset($yamlData['app']['description'])) {
            $jsonData['description']['en_EN'] = $yamlData['app']['description'];
        }
        if (isset($yamlData['app']['autostart'])) {
            $jsonData['autostart'] = $yamlData['app']['autostart'];
        }
        $jsonData['singleton'] = true;
        file_put_contents($appName . '/metadata.json', json_encode($jsonData, JSON_PRETTY_PRINT));
        $packagePath = $appName . '/package.json';
        $jsonData = json_decode(file_get_contents($packagePath), true);
        $jsonData['name'] = $yamlData['app']['name'];
        file_put_contents($appName . '/package.json', json_encode($jsonData));
        $indexScssPath = $appName . '/index.scss';
        $indexfileData = file_get_contents($indexScssPath);
        $indexfileData2 = str_replace('{AppName}', $yamlData['app']['name'], $indexfileData);
        file_put_contents($appName . '/index.scss', $indexfileData2);
        FileUtils::chmod_r($path . 'view', 0777);
        $this->logger->info("\n View json data " . print_r($displayName, true));
        $chatNotification = "";
        if (isset($yamlData['app']['app_properties'])) {
            $appProperties = json_decode($yamlData['app']['app_properties'], true);
            $chatNotification = $appProperties['chat_notification'];
        }
        if ($chatNotification === true) {
            $imagePath = $path . '../../AppSource/' . $yamlData['app']['uuid'] . '/view/apps/eoxapps/';
            $this->messageProducer->sendTopic(json_encode(array('appName' => $jsonData['name'], 'displayName' => $displayName, "profileImage" => $imagePath . 'icon.png')), 'SAVE_CHAT_BOT');
        }
        if ($chatNotification === false) {
            $this->messageProducer->sendTopic(json_encode(array('appName' => $jsonData['name'])), 'DISABLE_CHAT_BOT');
        }
    }

    public function processWorkflow(&$yamlData, $path)
    {
        if (isset($yamlData['workflow'])) {
            $appUuid = $yamlData['app']['uuid'];
            $workflowData = $yamlData['workflow'];
            foreach ($workflowData as $value) {
                $entityName = null;
                if (isset($value['entity'])) {
                    $entityName = is_array($value['entity']) ? $value['entity'][0] : $value['entity'];
                }
                $result = 0;
                $result = $this->checkWorkflowData($value, $appUuid);
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
                    if (isset($value['entity'])) {
                        $this->assignWorkflowToEntityMapping($value['entity'], $value['uuid'], $appUuid);
                    }
                }
            }
        }
    }

    private function checkWorkflowData(&$data, $appUuid)
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
    }

    private function setLinkAndRunBuild($appPath, $appId)
    {
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
            $this->logger->info("\nRunning App installer (View) - " . json_encode(
                ["folders" => $buildFolders],
                JSON_PRETTY_PRINT
            ));
            $restClient = $this->restClient;
            $output = json_decode($restClient->post(
                ($this->config['applicationUrl'] . "/installer"),
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
        $target = $path . "/data/delegate";
        if (is_link($link)) {
            FileUtils::unlink($link);
        }
        if (file_exists($target)) {
            $this->setupLink($target, $link);
        }
        $formlink = $this->config['FORM_FOLDER'] . $appId;
        $formsTarget = $path . "/content/forms";
        if (is_link($formlink)) {
            FileUtils::unlink($formlink);
        }
        if (file_exists($formsTarget)) {
            $this->setupLink($formsTarget, $formlink);
        }

        $formlink = $this->config['PAGE_FOLDER'] . $appId;
        $formsTarget = $path . "/content/pages";
        if (is_link($formlink)) {
            FileUtils::unlink($formlink);
        }
        if (file_exists($formsTarget)) {
            $this->setupLink($formsTarget, $formlink);
        }
        $formlink = $this->config['ENTITY_FOLDER'] . $appId;
        $formsTarget = $path . "/content/entity";
        if (is_link($formlink)) {
            FileUtils::unlink($formlink);
        }
        if (file_exists($formsTarget)) {
            $this->setupLink($formsTarget, $formlink);
        }

        $this->setLinkAndRunBuild($path, $appId);
    }

    private function setupAccountFiles($path, $accountId, $appId, $update = false)
    {
        if ($accountId && $path) {
            $link = $this->config['TEMPLATE_FOLDER'] . $accountId . "/" . $appId;
            $this->logger->info("linkkk---$link");
            $source = rtrim($path, "/") . "/data/template";
            if (!$update) {
                if (!file_exists($link)) {
                    FileUtils::createDirectory($link);
                }
                FileUtils::chmod_r($link, 0777);
                FileUtils::copyDir($source, $link);
            } else {
                FileUtils::copyOnlyNewFiles($source, $link);
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
        if (isset($yamlData['businessRole']) && !empty($yamlData['businessRole'][0]['name'])) {
            $appId = $yamlData['app']['uuid'];
            foreach ($yamlData['businessRole'] as &$businessRole) {
                $bRole = $businessRole;
                $result = $this->businessRoleService->saveBusinessRole($appId, $bRole);
                $businessRole['uuid'] = $bRole['uuid'];
            }
        }
    }
    public function createRole(&$yamlData, $templateRole = true, $orgId = null, $bRole = null)
    {
        if (isset($yamlData['role'])) {
            $params = null;
            if (!$templateRole) {
                if (!$orgId) {
                    $this->logger->warn("Organization not provided not processing roles!");
                    return;
                }
                $params['accountId'] = $orgId;
            }
            $appId = $yamlData['app']['uuid'];
            foreach ($yamlData['role'] as &$roleData) {
                $role = $roleData;
                if (!isset($role['name'])) {
                    $this->logger->warn("Role name not provided continuing!");
                    continue;
                }
                $role['uuid'] = isset($role['uuid']) ? $role['uuid'] : UuidUtil::uuid();
                if ((!empty($role['businessRole']['name']) && isset($role['businessRole']['name']) && $templateRole) ||
                    (!empty($role['businessRole']['name']) && isset($role['businessRole']['name']) && $bRole &&
                        in_array($role['businessRole']['name'], $bRole['businessRole']))) {
                    $temp = $this->businessRoleService->getBusinessRoleByName($appId, $role['businessRole']['name']);
                    if (count($temp) > 0) {
                        $role['business_role_id'] = $temp[0]['id'];
                    }
                } elseif (isset($role['businessRole']['name']) && !empty($role['businessRole']['name'])) {
                    $temp = $this->businessRoleService->getBusinessRoleByName($appId, $role['businessRole']['name']);
                    if (count($temp) > 0) {
                        $role['business_role_id'] = $temp[0]['id'];
                    }
                }
                $role['app_id'] = $params['app_id'] = $this->getIdFromUuid('ox_app', $appId);
                if ($templateRole) {
                    $role['uuid'] = isset($role['uuid']) ? $role['uuid'] : UuidUtil::uuid();
                    $result = $this->roleService->saveTemplateRole($role, $role['uuid']);
                    $roleData['uuid'] = $role['uuid'];
                } else {
                    unset($role['uuid']);
                    $result = $this->roleService->saveRole($params, $role);
                }
            }
        }
    }

    private function processOrg(&$orgData, $appId)
    {
        if (!isset($orgData['uuid'])) {
            if (!empty($orgData['name'])) {
                $accountUuid = $this->accountService->getAccountByName($orgData['name']);
                $orgData['uuid'] = $accountUuid;
            } else {
                $orgData['uuid'] = UuidUtil::uuid();
            }
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
        $this->accountService->saveAccount($orgdata);
        //setup business offering
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
        if (!isset($appData['title'])) {
            $appData['title'] = $appData['name'];
        }

        //Create the app if not found.
        if (0 == count($queryResult)) {
            $temp = ['app' => $appData];
            $createResult = $this->createApp($temp);
            ArrayUtils::merge($appData, $createResult['app']);
            return AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $appData) . DIRECTORY_SEPARATOR;
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
            $appId = $this->getIdFromUuid('ox_app', $appUuid);
            $this->privilegeService->saveAppPrivileges($appId, $privilegedata);
        }
    }

    public function getAppList($filterParams = null)
    {
        $pageSize = 20;
        $offset = 0;
        $sort = "name";
        $where = " WHERE ox_app.status != 1";

        if (count($filterParams) > 0 || sizeof($filterParams) > 0) {
            $filterArray = json_decode($filterParams['filter'], true);
            if (isset($filterArray[0]['filter'])) {
                $filterlogic = isset($filterArray[0]['filter']['filters'][1]['logic']) ? $filterArray[0]['filter']['filters'][1]['logic'] : "AND";
                $filterdefaultParams = $filterArray[0]['filter']['filters'];
                $defaultFilterList[] = $filterdefaultParams[0];
                array_shift($filterdefaultParams);
                if ($filterdefaultParams) {
                    foreach ($filterdefaultParams as $filterindex => $filterValues) {
                        foreach ($filterValues as $key => $value) {
                            if ($key == 'filters') {
                                $filterList = array_merge($value, $defaultFilterList);
                            } else {
                                $filterList = $filterArray[0]['filter']['filters'];
                            }
                        }
                    }
                } else {
                    $filterList = $filterArray[0]['filter']['filters'];
                }
                $filter = FilterUtils::filterArray($filterList, $filterlogic, array('name' => 'ox_app.name', 'date_modified' => 'DATE(ox_app.date_modified)', 'modified_user' => 'om.name', 'created_user' => 'oc.name'));
                $where .= " AND " . $filter;
            }
            if (isset($filterArray[0]['sort']) && count($filterArray[0]['sort']) > 0) {
                $sort = $filterArray[0]['sort'];
                $sort = FilterUtils::sortArray($sort);
            }
            $pageSize = isset($filterArray[0]['take']) ? $filterArray[0]['take'] : 10;
            $offset = isset($filterArray[0]['skip']) ? $filterArray[0]['skip'] : 0;
        }
        $fromTable = " FROM `ox_app` inner join ox_user oc on oc.id=ox_app.created_by left join ox_user om on om.id=ox_app.modified_by";
        $cntQuery = "SELECT count(ox_app.id) as count" . $fromTable;
        $resultSet = $this->executeQuerywithParams($cntQuery . $where);
        $count = $resultSet->toArray()[0]['count'];
        if (0 == $count) {
            return;
        }
        $sort = " ORDER BY " . $sort;
        $limit = " LIMIT " . $pageSize . " offset " . $offset;
        $query = "SELECT ox_app.*,oc.name as created_user,om.name as modified_user" . $fromTable . $where . $sort . $limit;
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
        $appData['app_properties'] = json_encode(array("chat_notification" => isset($appData['chat_notification']) ? $appData['chat_notification'] : "", "appIdentifiers" => isset($appData['appIdentifiers']) ? $appData['appIdentifiers'] : ""));
        if (array_key_exists('uuid', $appData) && ($uuid != $appData['uuid'])) {
            throw new InvalidParameterException('UUID in URL and UUID in data set are not matching.');
        }
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $appData);
        if (!file_exists($appSourceDir)) {
            throw new FileNotFoundException(
                "Application source directory is not found.",
                ['directory' => $appSourceDir]
            );
        }
        $app = new App($this->table);
        $app->loadByUuid($uuid);
        if (array_key_exists('type', $appData)) {
            if ($app->getProperty('type') != $appData['type']) {
                throw new InvalidParameterException(
                    "Application 'type' cannot be changed after creating the app."
                );
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
        } catch (Exception $e) {
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
            'status' => App::DELETED,
            'name' => $app->toArray()['id'] . '_' . $app->toArray()['name'],
            'uuid' => UuidUtil::uuid()
        ]);
        try {
            $this->beginTransaction();
            $app->save();
            $this->commit();
        } catch (Exception $e) {
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
            $this->updateAppStatus($appSingleArray, APP::PRE_BUILT, APP::DELETED);
            $this->updateAppStatus($appSingleArray, APP::MY_APP, APP::IN_DRAFT);
            $select = "SELECT name FROM ox_app where name in ('" . implode("','", $appSingleArray) . "')";
            $result = $this->executeQuerywithParams($select)->toArray();
            $result = array_unique(array_map('current', $result));
            for ($x = 0; $x < sizeof($apps); $x++) {
                $app = $apps[$x];
                if (!in_array($app['name'], $result)) {
                    if (!isset($app['uuid']) || (isset($app['uuid']) && $app['uuid'] == "NULL")) {
                        $appObj = new App($this->table);
                    } else {
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
                $insert = "INSERT INTO `ox_app_registry` (`account_id`,`app_id`,`date_created`)
                SELECT account.id, '" . $idList[$i] . "', now() from ox_account as account
                where account.id not in(SELECT account_id FROM ox_app_registry WHERE app_id ='" . $idList[$i] . "')";
                $result = $this->runGenericQuery($insert);
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        return 1;
    }

    private function updateAppStatus($appSingleArray, $appType, $appStatus)
    {
        $update = "UPDATE ox_app SET status = $appStatus where ox_app.name NOT IN ('" . implode("','", $appSingleArray) . "') and ox_app.type=" . $appType;
        $this->runGenericQuery($update);
    }
    public function addToAppRegistry($data)
    {
        $this->logger->debug("Adding App to registry");
        $data['accountId'] = isset($data['accountId']) ? $data['accountId'] : AuthContext::get(AuthConstants::ACCOUNT_UUID);
        $app = $this->table->getByName($data['app_name']);
        return $this->appRegistryService->createAppRegistry($app->uuid, $data['accountId']);
    }

    public function processEntity(&$yamlData, $assoc_id = null)
    {
        if (isset($yamlData['entity']) && !empty($yamlData['entity'])) {
            $appId = $yamlData['app']['uuid'];
            $sequence = 0;
            foreach ($yamlData['entity'] as &$entityData) {
                $entity = $entityData;
                $entity['generic_attachment_config'] = json_encode(array("attachmentField" => isset($entity['chatAttachmentField']) ? $entity['chatAttachmentField'] : ""));
                $entity['assoc_id'] = $assoc_id;
                $entityRec = $this->entityService->getEntityByName($appId, $entity['name']);
                if (!$entityRec) {
                    $result = $this->entityService->saveEntity($appId, $entity);
                } else {
                    $entity['id'] = $entityRec['id'];
                    $entity['uuid'] = $entityRec['uuid'];
                    $result = $this->entityService->saveEntity($appId, $entity);
                }
                if (isset($entity['identifiers'])) {
                    $result = $this->entityService->saveIdentifiers($entity['id'], $entity['identifiers']);
                }
                if (isset($entity['participantRole'])) {
                    $result = $this->entityService->saveParticipantRoles($entity['id'], $appId, $entity['participantRole']);
                }
                if (isset($entity['field'])) {
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
                    $childEntityData = ['entity' => $entityData['child'], 'app' => ['uuid' => $appId]];
                    $this->processEntity($childEntityData, $entity['id']);
                    $entityData['child'] = $childEntityData['entity'];
                }
                if ((isset($entity['enable_view']) && $entity['enable_view'] && isset($entity['pageContent']) && !empty($entity['pageContent'])) || isset($entity['enable_documents']) && $entity['enable_documents']) {
                    $pageId = isset($entity['page_uuid']) ? $entity['page_uuid'] : UuidUtil::uuid();
                    $page = (isset($entity['pageContent']) && !empty($entity['pageContent'])) ? $entity['pageContent']['data'] : [];
                    $page['name'] = $entity['name'];
                    $routedata = array("appId" => $appId);
                    $result = $this->pageService->savePage($routedata, $page, $pageId);
                    $entityData['page_uuid'] = $page['uuid'];
                    $entity['page_id'] = $page['id'];
                    $result = $this->entityService->saveEntity($appId, $entity);
                }
            }
        }
    }

    private function assignWorkflowToEntityMapping($entityArray, $workflowUuid, $appUuid)
    {
        try {
            $data = array();
            $workflowId = $this->getIdFromUuid('ox_workflow', $workflowUuid);
            $entityArray = is_array($entityArray) ? $entityArray : array($entityArray);
            foreach ($entityArray as $entityName) {
                $entityData = $this->entityService->getEntityByName($appUuid, $entityName);
                if ($entityData == null) {
                    continue;
                }
                $individualEntry = array(
                    'workflow_id' => $workflowId,
                    'entity_id' => $entityData['id']
                );
                array_push($data, $individualEntry);
            }
            if (!empty($data)) {
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
            $descEntity = [];
            foreach ($descriptorData["entity"] as &$value) {
                if (isset($value["formFieldsValidationExcel"]) && empty($value['formFieldsValidationExcel'])) {
                    unset($value["formFieldsValidationExcel"]);
                }
                if (isset($value['field']) && array_key_exists('name', $value['field'][0]) && empty($value['field'][0]['name'])) {
                    unset($value['field']);
                }

                if (isset($value['name']) && !empty($value['name'])) {
                    array_push($descEntity, $value);
                }
            }
            $descriptorData["entity"] = $descEntity;
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
        if (isset($descriptorData["job"]) && empty($descriptorData['job'][0]['name'])) {
            unset($descriptorData["job"]);
        }
        if (isset($descriptorData["org"])) {
            //Handle single and multiple org definitions
            if (array_key_exists(0, $descriptorData["org"])) {
                foreach ($descriptorData["org"] as $key => $value) {
                    if (empty($descriptorData["org"][$key]["name"])) {
                        unset($descriptorData["org"][$key]);
                    }
                }
            } else {
                if (empty($descriptorData["org"]["name"])) {
                    unset($descriptorData["org"]);
                }
            }
        }
        if (isset($descriptorData["workflow"]) && empty($descriptorData['workflow'][0]['name'])) {
            unset($descriptorData["workflow"]);
        }
        return $descriptorData;
    }

    public function removeDeployedApp($appId)
    {
        try {
            $this->beginTransaction();
            // Remove Symlinks
            $appDetails = $this->getDataByParams('ox_app', array(), array('uuid' => $appId))->toArray();
            $this->removeAppAndExecutePackageDiscover($appDetails[0]['name']);
            $this->jobService->cancelAppJobs($appId);

            // Page
            $resultPage = $this->getDataByParams('ox_app_page', array(), array('app_id' => $this->getIdFromUuid('ox_app', $appId)))->toArray();
            if (count($resultPage) > 0) {
                foreach ($resultPage as $key => $value) {
                    $this->pageService->deletePage($appId, $value['uuid']);
                }
            }
            // Menu
            $resultMenu = $this->getDataByParams('ox_app_menu', array(), array('app_id' => $this->getIdFromUuid('ox_app', $appId)))->toArray();
            if (count($resultMenu) > 0) {
                foreach ($resultMenu as $key => $value) {
                    $this->menuItemService->deleteMenuItem($appId, $value['uuid']);
                }
            }

            // ENTITY
            $entityRes = $this->entityService->getEntitys($appId);
            if (count($entityRes) > 0) {
                $this->workflowService->deleteWorkflowLinkedToApp($appId);
                $this->entityService->removeEntityLinkedToApps($appId);
                $deleteQuery = "DELETE oei FROM ox_entity_identifier oei
                                right outer join ox_app_entity oxe on oei.entity_id = oxe.id
                                inner join ox_app oxa on oxa.id = oxe.app_id
                                where oxa.uuid = :appId";
                $deleteParams = array('appId' => $appId);
                $this->logger->info("STATEMENT DELETE $deleteQuery" . print_r($deleteParams, true));
                $this->executeUpdateWithBindParameters($deleteQuery, $deleteParams);
            }
            $this->businessRoleService->deleteBusinessRoleBasedOnAppId($appId);
            $this->removeRoleData($appId);
            $result = $this->getDataByParams('ox_privilege', array(), array('app_id' => $this->getIdFromUuid('ox_app', $appId)))->toArray();
            if (count($result) > 0) {
                $this->deleteInfo('ox_privilege', $this->getIdFromUuid('ox_app', $appId));
            }
            $this->deleteApp($appId);
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    private function removeAppAndExecutePackageDiscover($appName)
    {
        $link = $this->config['APPS_FOLDER'] . $appName;
        if (is_link($link)) {
            FileUtils::unlink($link);
        }
        $request = array();
        array_push($request, [
            "path" => $this->config['APPS_FOLDER'] . "../bos/",
            "type" => "bos"
        ]);
        $restClient = $this->restClient;
        $output = json_decode($restClient->post(
            ($this->config['applicationUrl'] . "/installer"),
            ["folders" => $request]
        ), true);
        if ($output["status"] != "Success") {
            $this->logger->info("\n Package Discover Failed " . $output);
            throw new ServiceException('Failed to complete package discover for the application.', 'E_APP_PACKAGE_DISCOVER_FAIL', 0);
        }
        $this->logger->info("\n Finished package discover " . print_r($output, true));
    }

    private function processInstalledTemplates($appId, $path)
    {
        $select = "SELECT oxac.uuid as accountId FROM ox_app_registry oxar
                    INNER JOIN ox_app oxa on oxa.id = oxar.app_id
                    INNER JOIN ox_account oxac on oxac.id = oxar.account_id
                    WHERE oxa.uuid =:appId";
        $params = ['appId' => $appId];
        $result = $this->executeQueryWithBindParameters($select, $params)->toArray();
        foreach ($result as $accountId) {
            $this->setupAccountFiles($path, $accountId['accountId'], $appId, true);
        }
    }
}
