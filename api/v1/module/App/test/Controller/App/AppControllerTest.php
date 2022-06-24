<?php
namespace App;

use App\Controller\AppController;
use App\Controller\AppRegisterController;
use Oxzion\Service\AppService;
use Oxzion\Service\RegistrationService;
use Oxzion\Service\FileService;
use Mockery;
use Oxzion\Test\ControllerTest;
use Oxzion\Utils\FileUtils;
use Oxzion\App\AppArtifactNamingStrategy;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Symfony\Component\Yaml\Yaml;
use Exception;
use AppTest\AppTestSetUpTearDownHelper;

class AppControllerTest extends ControllerTest
{
    private $setUpTearDownHelper = null;
    private $config = null;

    public function __construct()
    {
        parent::__construct();
        $this->loadConfig();
        $this->config = $this->getApplicationConfig();
        $this->setUpTearDownHelper = new AppTestSetUpTearDownHelper($this->config);
    }

    public function setUp(): void
    {
        $conn=$this->getConnection();
        $conn->getConnection()->query("SET FOREIGN_KEY_CHECKS=0");
        parent::setUp();
        $conn->getConnection()->query("SET FOREIGN_KEY_CHECKS=1");
        
        if ($this->getName() == 'testCopyOnlyNewTemplatesOnDeploy') {
            $this->cleanFile();
        }
        $this->setUpTearDownHelper->cleanAll();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        if ($this->getName() == 'testCopyOnlyNewTemplatesOnDeploy') {
            $this->cleanFile();
        }
        $this->setUpTearDownHelper->cleanAll();
    }

    public function getDataSet()
    {
        //These tests don't need data set.
        switch ($this->getName()) {
            case 'testCreateWithUserGeneratedUuid':
            case 'testCreateWithServerGeneratedUuid':
            case 'testCreateWithoutRequiredData':
                //Return empty data set to keep framework happy!
                return new YamlDataSet(dirname(__FILE__) . "/../../Dataset/EmptyDataSet.yml");;
            break;
        }

        switch ($this->getName()) {
            case 'testGetAccountOnForInstall':
            case 'testGetAccountOnInstalled':
            case 'testGetAccountProperties':
                //Return empty data set to keep framework happy!
                return new YamlDataSet(dirname(__FILE__) . "/../../Dataset/fileattachment.yaml");;
            break;
        }

        $dataset = new YamlDataSet(dirname(__FILE__) . "/../../Dataset/Workflow.yml");
        switch ($this->getName()) {
            case 'testDeployAppWithWrongUuidAndDuplicateNameInDatabase':
            case 'testDeployAppWithWrongUuidAndUniqueNameInDatabase':
            case 'testDeployAppWithWrongNameInDatabase':
            case 'testDeployAppWithNameAndNoUuidInYMLButNameandUuidInDatabase':
            case 'testDeployAppAddExtraPrivilegesInDatabaseFromYml':
            case 'testDeployAppDeleteExtraPrivilegesInDatabaseNotInYml':
                $dataset->addYamlFile(dirname(__FILE__) . "/../../Dataset/App2.yml");
            break;
        }

        return $dataset;
    }

    public function getMockProcessManager()
    {
        $mockProcessManager = Mockery::mock('\Oxzion\Workflow\Camunda\ProcessManagerImpl');
        $workflowService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\WorkflowService::class);
        $workflowService->setProcessManager($mockProcessManager);
        return $mockProcessManager;
    }

    private function getMockRestClientForScheduleService()
    {
        $taskService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\JobService::class);
        $mockRestClient = Mockery::mock('Oxzion\Utils\RestClient');
        $taskService->setRestClient($mockRestClient);
        return $mockRestClient;
    }

    private function getMockRestClientForAppService()
    {
        $mockRestClient = Mockery::mock('Oxzion\Utils\RestClient');
        $appService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\AppService::class);
        $appService->setRestClient($mockRestClient);
        return $mockRestClient;
    }
    
    public function getRegistrationService(){
        return $this->getApplicationServiceLocator()->get(RegistrationService::class);
    }

    public function getFileService(){
        return $this->getApplicationServiceLocator()->get(FileService::class);
    }


    private function cleanFile()
    {
        if (file_exists(__DIR__ . '/../../sampleapp/data/template/COINewFooter.htm')) {
            copy(__DIR__ . '/../../sampleapp/data/template/COINewFooter.html', __DIR__ . '/../../sampleapp/data/template/COIfooter.html');
            FileUtils::deleteFile('COINewFooter.html', __DIR__ . '/../../sampleapp/data/template/');
        }
    }
    protected function setDefaultAsserts()
    {
        $this->assertModuleName('App');
        $this->assertControllerName(AppController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AppController');
        $contentTypeHeader = $this->getResponseHeader('content-type')->toString();
        $contentTypeRegex = '/application\/json(;? *?charset=utf-8)?/i';
        $this->assertTrue(preg_match($contentTypeRegex, $contentTypeHeader) ? true : false);
    }

    protected function setDefaultAssertsRegister()
    {
        $this->assertModuleName('App');
        $this->assertControllerName(AppRegisterController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AppRegisterController');
        $contentTypeHeader = $this->getResponseHeader('content-type')->toString();
        $contentTypeRegex = '/application\/json(;? *?charset=utf-8)?/i';
        $this->assertTrue(preg_match($contentTypeRegex, $contentTypeHeader) ? true : false);
    }

    public function testDeployAppWithWrongNameInDatabase()
    {
    $this->setUpTearDownHelper->setupAppDescriptor('application9.yml');
    $this->initAuthToken($this->adminUser);
    if (enableCamundaForDeployApp == 0) {
        $mockProcessManager = $this->getMockProcessManager();
        $mockProcessManager->expects('deploy')->withAnyArgs()->once()->andReturn(array('Process_1dx3jli:1eca438b-007f-11ea-a6a0-bef32963d9ff'));
        $mockProcessManager->expects('parseBPMN')->withAnyArgs()->once()->andReturn(null);
    }
    if (enableExecUtils == 0) {
        $mockRestClient = $this->getMockRestClientForAppService();
        $mockRestClient->expects('post')->with(($this->config['applicationUrl'] . "/installer"), Mockery::any())->once()->andReturn('{"status":"Success"}');
    }
    if (enableCamel == 0) {
        $mockRestClient = $this->getMockRestClientForScheduleService();
        $mockRestClient->expects('postWithHeader')->with("setupjob", Mockery::any())->once()->andReturn(array('body' => '{"Success":true,"Message":"Job Scheduled Successfully!","JobId":"3a289705-763d-489a-b501-0755b9d4b64b","JobGroup":"autoRenewalJob"}'));
    }
    $path = __DIR__ . '/../../sampleapp/';
    $path = $this->setupAppFolder($path);
    $data = ['path' => $path];
    $this->dispatch('/app/deployapp', 'POST', $data);
    $content = (array) json_decode($this->getResponse()->getContent(), true);
    
    $this->assertResponseStatusCode(200);
    $this->setDefaultAsserts();
    $filename = "application.yml";
    $yaml = Yaml::parse(file_get_contents($path . $filename));
    $appName = $yaml['app']['name'];
    $YmlappUuid = $yaml['app']['uuid'];
    $query = "SELECT name, uuid from ox_app where name = '" . $appName . "'";
    $appdata = $this->executeQueryTest($query);
    $this->assertEquals($appdata[0]['name'], $appName);
    $this->assertEquals($appdata[0]['uuid'], $YmlappUuid);
    $this->assertEquals($content['status'], 'success');
    $query = "SELECT count(name),status,uuid from ox_account where name = '" . $yaml['org']['name'] . "' GROUP BY name,status,uuid";
    $account = $this->executeQueryTest($query);
    $this->assertEquals($account[0]['uuid'], $yaml['org']['uuid']);
    $template = $this->config['TEMPLATE_FOLDER'] . $account[0]['uuid'];
    $delegate = $this->config['DELEGATE_FOLDER'] . $YmlappUuid;
    $this->assertEquals(file_exists($template), true);
    $this->assertEquals(file_exists($delegate), true);
    if (!isset($yaml['org']['uuid'])) {
        $yaml['org']['uuid'] = null;
    }
    unlink(__DIR__ . '/../../sampleapp/application.yml');
    $appname = $path . 'view/apps/' . $yaml['app']['name'];
    try {
        FileUtils::rmDir($appname);
    } catch (Exception $e) {
    }
    $this->unlinkFolders($YmlappUuid, $appName, $yaml['org']['uuid']);
}

    public function testDeployAppWithCreateFile()
    {
        $this->setUpTearDownHelper->setupAppDescriptor('applicationhdowithforms.yml');
        $this->initAuthToken($this->adminUser);
        if (enableCamundaForDeployApp == 0) {
            $mockProcessManager = $this->getMockProcessManager();
            $mockProcessManager->expects('deploy')->withAnyArgs()->once()->andReturn(array('Process_1dx3jli:1eca438b-007f-11ea-a6a0-bef32963d9ff'));
            $mockProcessManager->expects('parseBPMN')->withAnyArgs()->once()->andReturn(null);
        }
        if (enableExecUtils == 0) {
            $mockRestClient = $this->getMockRestClientForAppService();
            $mockRestClient->expects('post')->with(($this->config['applicationUrl'] . "/installer"), Mockery::any())->once()->andReturn('{"status":"Success"}');
        }
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $mockRestClient->expects('postWithHeader')->with("setupjob", Mockery::any())->once()->andReturn(array('body' => '{"Success":true,"Message":"Job Scheduled Successfully!","JobId":"3a289705-763d-489a-b501-0755b9d4b64b","JobGroup":"autoRenewalJob"}'));
        }
        $path = __DIR__ . '/../../sampleapp/';
        $data = ['path' => $path];
        $this->dispatch('/app/deployapp', 'POST', $data);
        $sel = "select * from ox_app where uuid = 'a4b1f073-fc20-477f-a804-1aa206938c42'";
        $res1 = $this->executeQueryTest($sel);
        $data = '{"IcLastName":"Gordon","autoInsuranceCompany":"","autoInsuranceExpirationDate":"","autoLiability":false,"autoPolicy":"","cargoInsurance":false,"cargoInsuranceCompany":"","cargoInsuranceExpirationDate":"","cargoInsurancePolicy":"","city1IC":"Quae eos alias obcaecati dignissimos unde vero con","companyName":"","dataGrid":[{"nameDriverUnit":"Nehru","driverLastName":"Gordon","street1DriverUnitInfo":"87 Green Oak Lane","city1DriverUnitInfo":"d","stateDriverUnitInfo":{"name":"Alabama","abbreviation":"AL"},"driverEmail":"tazocaj@mailinator.com","pleaseSelectDriverType":{"":false,"rsp":false,"rspEmployee":false},"rspContactInformation":"","fmcsaMc":"","dot":"","zipCode1DriverUnitInfo":10906}],"dataGrid1":[{"vinDriverInfo":"","makeVin":"","modelVin":""}],"documents":[],"driverEmailWarning":"Please Note : Driver email must be unique","effectiveDate":"2021-05-20T00:00:00+05:30","iCEmail":"vitufunaj@mailinator.com","iCFirstName":"Nehru","identifier_field":"iCEmail","name":"Nehru Gordon","nonOwnedAndHiredAutoInsuranceCompany":"","nonOwnedAndHiredAutoInsuranceExpirationDate":"","pleaseSelectTheFacility":"","state":{"name":"California","abbreviation":"CA"},"stateJsonList":[{"name":"Alabama","abbreviation":"AL"},{"name":"Arizona","abbreviation":"AZ"},{"name":"Arkansas","abbreviation":"AR"},{"name":"California","abbreviation":"CA"},{"name":"Colorado","abbreviation":"CO"},{"name":"Connecticut","abbreviation":"CT"},{"name":"Delaware","abbreviation":"DE"},{"name":"District Of Columbia","abbreviation":"DC"},{"name":"Florida","abbreviation":"FL"},{"name":"Georgia","abbreviation":"GA"},{"name":"Hawaii","abbreviation":"HI"},{"name":"Idaho","abbreviation":"ID"},{"name":"Illinois","abbreviation":"IL"},{"name":"Indiana","abbreviation":"IN"},{"name":"Iowa","abbreviation":"IA"},{"name":"Kansas","abbreviation":"KS"},{"name":"Kentucky","abbreviation":"KY"},{"name":"Louisiana","abbreviation":"LA"},{"name":"Maine","abbreviation":"ME"},{"name":"Maryland","abbreviation":"MD"},{"name":"Massachusetts","abbreviation":"MA"},{"name":"Michigan","abbreviation":"MI"},{"name":"Minnesota","abbreviation":"MN"},{"name":"Mississippi","abbreviation":"MS"},{"name":"Missouri","abbreviation":"MO"},{"name":"Montana","abbreviation":"MT"},{"name":"Nebraska","abbreviation":"NE"},{"name":"Nevada","abbreviation":"NV"},{"name":"New Hampshire","abbreviation":"NH"},{"name":"New Jersey","abbreviation":"NJ"},{"name":"New Mexico","abbreviation":"NM"},{"name":"New York","abbreviation":"NY"},{"name":"North Carolina","abbreviation":"NC"},{"name":"North Dakota","abbreviation":"ND"},{"name":"Ohio","abbreviation":"OH"},{"name":"Oklahoma","abbreviation":"OK"},{"name":"Oregon","abbreviation":"OR"},{"name":"Pennsylvania","abbreviation":"PA"},{"name":"Rhode Island","abbreviation":"RI"},{"name":"South Carolina","abbreviation":"SC"},{"name":"South Dakota","abbreviation":"SD"},{"name":"Tennessee","abbreviation":"TN"},{"name":"Texas","abbreviation":"TX"},{"name":"Utah","abbreviation":"UT"},{"name":"Vermont","abbreviation":"VT"},{"name":"Virginia","abbreviation":"VA"},{"name":"Washington","abbreviation":"WA"},{"name":"West Virginia","abbreviation":"WV"},{"name":"Wisconsin","abbreviation":"WI"},{"name":"Wyoming","abbreviation":"WY"}],"status":"Non-Compliant","street1IC":"87 Green Oak Lane","textFieldNonOwnedAndHiredAutoInsurancePolicy":"","zipCode1IC":10906,"appId":"a4b1f073-fc20-477f-a804-1aa206938c42","app_id":"'.$res1[0]['id'].'","entity_name":"Compliance","accountId":null,"workFlowId":null,"filterParams":{"filter":[{"filter":{"filters":[{"field":"iCEmail","operator":"eq","value":"vitufunaj@mailinator.com"}]}}]},"entityName":"Compliance"}';
        $data = json_decode($data, true);
        $fileService = $this->getFileService();
        $fileService->createFile($data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $sel = "select * from ox_file order by id desc";
        $resFile = $this->executeQueryTest($sel);
        $sel = "select * from ox_file_participant";
        $resFileParticipant = $this->executeQueryTest($sel);
        $this->assertEquals(1, count($resFileParticipant));       
        $this->assertEquals($resFileParticipant[0]['file_id'], $resFile[0]['id']); 
        $this->assertEquals(1, $resFileParticipant[0]['account_id']); 
        $entityId = $resFile[0]['entity_id'];
        $accountId = $resFileParticipant[0]['account_id'];
        $sel = "SELECT obr.account_id 
                   from ox_account_offering oof 
                   inner join ox_account_business_role obr on obr.id = oof.account_business_role_id
                   where oof.entity_id = $entityId and obr.account_id = $accountId";
        $resEntitySellerAccount = $this->executeQueryTest($sel);
        $this->assertEquals(1, count($resEntitySellerAccount)); 
    }


    public function testGetCssFileNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $sampleAppUuidFromWorkflowYml = '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        $appName = 'SampleApp';
        $appSourceDir = $this->config['EOX_APP_SOURCE_DIR'] . "${sampleAppUuidFromWorkflowYml}".'/view/apps/'."eoxapps/";
        try {
            if (file_exists($appSourceDir.'index.scss')) {
                FileUtils::deleteFile('index.scss', $appSourceDir);
            }
            $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/cssFile', 'GET');
            $content = (array)json_decode($this->getResponse()->getContent(), true);
            $this->assertEquals($content['status'], 'error');
            $this->assertEquals($content['message'], 'Css File not Found');
        } catch (Exception $e) {
            throw $e;
        } finally {
            if (file_exists($appSourceDir)) {
                FileUtils::rmDir($appSourceDir);
            }
        }
    }

    public function testGetCssFile()
    {
        $this->initAuthToken($this->adminUser);
        $sampleAppUuidFromWorkflowYml = '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        $appName = 'SampleApp';
        $appSourceDir = $this->config['EOX_APP_SOURCE_DIR'] . "${sampleAppUuidFromWorkflowYml}".'/view/apps/'."${appName}";
        try {
            if (file_exists($appSourceDir)) {
                FileUtils::rmDir($appSourceDir);
                mkdir($appSourceDir);
            }
            $eoxSampleApp = dirname(__FILE__) . '/../../Dataset/SampleApp';
            FileUtils::copyDir($eoxSampleApp, $appSourceDir);
            $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/cssFile', 'GET');
            $content = (array)json_decode($this->getResponse()->getContent(), true);
            $this->assertEquals($content['status'], 'success');
            $this->assertNotEmpty($content['data']['cssContent']);
        } catch (Exception $e) {
            throw $e;
        } finally {
            if (file_exists($appSourceDir)) {
                FileUtils::rmDir($appSourceDir);
            }
        }
    }

    public function testGetListOfUserAssignments()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/assignmentList', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(AppController::class);
        $this->assertControllerClass('AppController');
        $this->assertMatchedRouteName('assignmentList');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['total'], 1);
    }

    public function testGetListOfAssignments()
    {
        $this->initAuthToken($this->adminUser);
        $product = 'Individual Professional Liability';
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/assignments?filter=[{"filter":{"filters":[{"field":"product","operator":"eq","value":"' . $product . '"}]},"skip":0,"take":10}]', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(AppController::class);
        $this->assertControllerClass('AppController');
        $this->assertMatchedRouteName('assignments');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['product'], $product);
        $this->assertEquals($content['total'], 1);
    }

    public function testGetList()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 13);
        $this->assertEquals($content['data'][0]['name'], 'Admin');
        $this->assertEquals($content['total'], 13);
    }

    public function testGet()
    {
        $this->initAuthToken($this->adminUser);
        $this->setUpTearDownHelper->setupAppInSourceLocation('sample.yml');
        $appId = '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        $this->dispatch("/app/$appId", 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        $this->assertNotEmpty($content['data']['app']['uuid']);
        $this->assertEquals($content['data']['app']['name'], 'SampleApp');
    }

    public function testGetNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbijkop', 'GET');
        $this->assertResponseStatusCode(404);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testGetAppList()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/a', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(AppController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AppController');
        $this->assertMatchedRouteName('applist');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertNotEquals($content['data'], array());
    }

    public function testGetAppListWithQuery()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app?filter=[{"filter":{"logic":"and","filters":[{"field":"name","operator":"startswith","value":"a"},{"field":"category","operator":"contains","value":"utilities"}]},"sort":[{"field":"id","dir":"asc"}],"skip":0,"take":1}]', 'GET');

        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        //$this->assertEquals(count($content['data']), 1);
        //$this->assertEquals($content['data'][0]['name'], 'Admin');
        //$this->assertEquals($content['total'], 1);
    }

    public function testGetAppListWithPageSize()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app?filter=[{"skip":0,"take":2}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 2);
        $this->assertEquals($content['data'][0]['name'], 'Admin');
        $this->assertEquals($content['data'][1]['name'], 'Analytics');
        $this->assertEquals($content['total'], 13);
    }

    public function testGetAppListWithPageSize2()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app?filter=[{"skip":2,"take":2}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 2);
        $this->assertEquals($content['data'][0]['name'], 'AppBuilder');
        $this->assertEquals($content['data'][1]['name'], 'CRM');
        $this->assertEquals($content['total'], 13);
    }

    public function testCreateWithUserGeneratedUuid()
    {
        $this->initAuthToken($this->adminUser);
        $uuid = '11111111-1111-1111-1111-111111111111';
        $query = "SELECT id, uuid, name FROM ox_app WHERE uuid='${uuid}'";
        //Ensure there is no ox_app record matching given UUID.
        $existingRecordSet = $this->executeQueryTest($query);
        $this->assertTrue(empty($existingRecordSet));
        //Send request and create the record.
        $data = [
            'app' => [
                'name' => 'TestApp-1',
                'uuid' => $uuid,
                'description' => 'App for testing App API',
                'category' => 'EXAMPLE_CATEGORY',
                'type' => 2,
                'autostart' => true
            ]
        ];
        $this->dispatch('/app', 'POST', $data);
        //Assert response status etc.
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        //Check new record is created in the database.
        $newRecordSet = $this->executeQueryTest($query);
        $this->assertEquals(1, count($newRecordSet));
        $newRecord = $newRecordSet[0];
        $this->assertFalse(empty($newRecord));
        $this->assertEquals($uuid, $newRecord['uuid']);
        $this->assertNotEmpty($newRecord['id']);
        $this->assertEquals($data['app']['name'], $newRecord['name']);
        //Check returned data is as expected.
        $returnData = $content['data'];
        $this->assertTrue(array_key_exists('app', $returnData));
        $appData = $returnData['app'];
        $this->assertEquals($data['app']['name'], $appData['name']);
        $this->assertEquals($uuid, $appData['uuid']);
        $this->assertEquals('default_app.png', $appData['logo']);
        $this->assertEquals(2, $appData['status']);
        $this->assertEquals('', $appData['start_options']);
        //Check application descriptor is created and is as expected.
        $srcAppDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $this->assertTrue(file_exists($srcAppDir));
        $appDescriptorFilePath = $srcAppDir . DIRECTORY_SEPARATOR . AppService::APPLICATION_DESCRIPTOR_FILE_NAME;
        $yamlData = Yaml::parse(file_get_contents($appDescriptorFilePath));
        $this->assertEquals($returnData, $yamlData);
    }

    public function testCreateWithServerGeneratedUuid()
    {
        $this->initAuthToken($this->adminUser);
        $data = [
            'app' => [
                'name' => 'App3',
                'type' => 2,
                'category' => 'EXAMPLE_CATEGORY1'
            ]
        ];
        $query = "SELECT id, uuid, name FROM ox_app WHERE name='" . $data['app']['name'] . "'";
        //Ensure there is no ox_app record matching given UUID.
        $existingRecordSet = $this->executeQueryTest($query);
        
        //$this->assertTrue(empty($existingRecordSet));
        //Send request and create the record.
        $this->dispatch('/app', 'POST', $data);
        //Assert response status etc.
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        //Check new record is created in the database.
        $newRecordSet = $this->executeQueryTest($query);
        $this->assertEquals(1, count($newRecordSet));
        $newRecord = $newRecordSet[0];
        $this->assertFalse(empty($newRecord));
        $this->assertNotEmpty($newRecord['uuid']);
        $this->assertEquals(36, strlen($newRecord['uuid']));
        $this->assertNotEmpty($newRecord['id']);
        $this->assertEquals($data['app']['name'], $newRecord['name']);
        //Check returned data is as expected.
        $returnData = $content['data'];
        $this->assertTrue(array_key_exists('app', $returnData));
        $appData = $returnData['app'];
        $this->assertEquals($data['app']['name'], $appData['name']);
        $this->assertEquals($data['app']['type'], $appData['type']);
        $this->assertEquals($data['app']['category'], $appData['category']);
        $this->assertEquals('', $appData['description']);
        $this->assertEquals(0, $appData['isdefault']);
        $this->assertEquals('default_app.png', $appData['logo']);
        $this->assertEquals(2, $appData['status']);
        $this->assertEquals('', $appData['start_options']);
        //Check application descriptor is created and is as expected.
        $srcAppDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $appData);
        $this->assertTrue(file_exists($srcAppDir));
        $appDescriptorFilePath = $srcAppDir . DIRECTORY_SEPARATOR . AppService::APPLICATION_DESCRIPTOR_FILE_NAME;
        $yamlData = Yaml::parse(file_get_contents($appDescriptorFilePath));
        $this->assertEquals($returnData, $yamlData);
    }

    public function testCreateWithoutRequiredData()
    {
        $this->initAuthToken($this->adminUser);
        $data['app'] = ['type' => 2, 'account_id' => 4];
        $query = "SELECT id, name FROM ox_app ORDER BY id ASC";
        //Take a snapshot of ox_app records.
        $existingRecordSet = $this->executeQueryTest($query);
        $this->dispatch('/app', 'POST', $data);
        $newRecordSet = $this->executeQueryTest($query);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        //Assert response status etc.
        $this->assertResponseStatusCode(406);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Validation error(s).');
        $this->assertEquals($content['data']['errors']['name']['error'], 'required');
        //Take new shapshot of ox_app and ensure no deletions and additions have happened.
        $this->assertEquals($existingRecordSet, $newRecordSet);
    }

    public function testCreateWithoutAccessPermission()
    {
        $this->initAuthToken($this->employeeUser);
        $data = [
            'app' => [
                'name' => 'AccessPermissionCheckApp',
                'type' => 2,
                'category' => 'EXAMPLE_CATEGORY',
                'logo' => 'app.png'
            ]
        ];
        $query = "SELECT id, name FROM ox_app ORDER BY id ASC";
        //Take a snapshot of ox_app records.
        $existingRecordSet = $this->executeQueryTest($query);
        $this->dispatch('/app', 'POST', $data);
        //Assert response status etc.
        $this->assertResponseStatusCode(401);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You have no Access to this API');
        //Take new shapshot of ox_app and ensure no deletions and additions have happened.
        $newRecordSet = $this->executeQueryTest($query);
        $this->assertEquals($existingRecordSet, $newRecordSet);
    }

    public function testCopyOnlyNewTemplatesOnDeploy()
    {
        $this->testDeployApp();
        copy(__DIR__ . '/../../sampleapp/data/template/COIfooter.html', __DIR__ . '/../../sampleapp/data/template/COINewFooter.html');
        copy(__DIR__ . '/../../sampleapp/sampleTemplate.html', __DIR__ . '/../../sampleapp/data/template/COIfooter.html');
        try {
            $this->testDeployApp();
            $this->markTestSkipped('Skipping Test');
            $template = $this->config['TEMPLATE_FOLDER'] . 'faffaf17-00b1-4a92-9ae6-7d04545457fe/6f6c35fe-2e3e-4c6f-ad15-a61d98e8d641/COINewFooter.html';
            $this->assertEquals(file_exists($template), true);
            $this->assertFileEquals($this->config['TEMPLATE_FOLDER'] . 'faffaf17-00b1-4a92-9ae6-7d04545457fe/6f6c35fe-2e3e-4c6f-ad15-a61d98e8d641/COIfooter.html', $this->config['TEMPLATE_FOLDER'] . 'faffaf17-00b1-4a92-9ae6-7d04545457fe/6f6c35fe-2e3e-4c6f-ad15-a61d98e8d641/COINewFooter.html');
        } finally {
            copy(__DIR__ . '/../../sampleapp/data/template/COINewFooter.html', __DIR__ . '/../../sampleapp/data/template/COIfooter.html');
            FileUtils::deleteFile('COINewFooter.html', __DIR__ . '/../../sampleapp/data/template/');
        }
    }


public function testDeployAppWithBusinessOffering()
{
    $directoryName = __DIR__ . '/../../sampleapp/view/apps/DummyDive';
    if (is_dir($directoryName)) {
        FileUtils::deleteDirectoryContents($directoryName);
    }
    $directoryName = __DIR__ . '/../../sampleapp/view/apps/DiveInsuranceSample';
    if (is_dir($directoryName)) {
        FileUtils::deleteDirectoryContents($directoryName);
    }
    copy(__DIR__ . '/../../sampleapp/application15.yml', __DIR__ . '/../../sampleapp/application.yml');
    $this->initAuthToken($this->adminUser);
    if (enableCamundaForDeployApp == 0) {
        $mockProcessManager = $this->getMockProcessManager();
        $mockProcessManager->expects('deploy')->withAnyArgs()->once()->andReturn(array('Process_1dx3jli:1eca438b-007f-11ea-a6a0-bef32963d9ff'));
        $mockProcessManager->expects('parseBPMN')->withAnyArgs()->once()->andReturn(null);
    }
    if (enableExecUtils == 0) {
        $mockRestClient = $this->getMockRestClientForAppService();
        $mockRestClient->expects('post')->with(($this->config['applicationUrl'] . "/installer"), Mockery::any())->once()->andReturn('{"status":"Success"}');
    }
    $data = ['path' => __DIR__ . '/../../sampleapp/'];
    $this->dispatch('/app/deployapp', 'POST', $data);
    $content = (array) json_decode($this->getResponse()->getContent(), true);
    $this->assertResponseStatusCode(200);
    $this->assertEquals($content['status'], 'success');
    $this->setDefaultAsserts();
    $filename = "application.yml";
    $path = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $content['data']['app'])."/";
    $yaml = Yaml::parse(file_get_contents($path . $filename));
    $appName = $yaml['app']['name'];
    $YmlappUuid = $yaml['app']['uuid'];
    $query = "SELECT * from ox_app where name = '" . $appName . "'";
    $queryResult = $this->executeQueryTest($query);
    $this->assertEquals(1, count($queryResult));
    $this->assertEquals($YmlappUuid, $queryResult[0]['uuid']);
    $appId = $queryResult[0]['id'];
    $query = "SELECT name,status,uuid,id from ox_account where name = '" . $yaml['org']['name'] . "'";
    $acctResult = $this->executeQueryTest($query);
    $this->assertEquals(1, count($acctResult));
    $this->assertEquals($yaml['org']['uuid'], $acctResult[0]['uuid']);
    $this->assertEquals($yaml['org']['name'], $acctResult[0]['name']);
    $this->assertEquals('Active', $acctResult[0]['status']);
    $query = "SELECT * from ox_app_registry where app_id = '" . $appId . "'";
    $appRegistryResult = $this->executeQueryTest($query);
    $accountId = $acctResult[0]['id'];
    $this->assertEquals(1, count($appRegistryResult));
    $this->assertEquals($accountId, $appRegistryResult[0]['account_id']);
    $query = "SELECT name, permission_allowed as permission FROM ox_privilege WHERE app_id = '" . $appId . "'";
    $privilege = $this->executeQueryTest($query);
    $this->assertEquals(3, count($privilege));
    $this->assertEquals($yaml['privilege'], $privilege);
    $query = "select * from ox_business_role where app_id = $appId";
    $businessRole = $this->executeQueryTest($query);
    $this->assertEquals(2, count($businessRole));
    $this->assertEquals($yaml['businessRole'][0]['name'], $businessRole[0]['name']);
    $this->assertEquals($yaml['businessRole'][1]['name'], $businessRole[1]['name']);
    $query = "SELECT * from ox_role
                WHERE business_role_id is not null OR account_id = $accountId ORDER BY name";
    $role = $this->executeQueryTest($query);
    $this->assertEquals(6, count($role));
    $this->assertEquals($yaml['role'][0]['name'], $role[1]['name']);
    $this->assertEquals(null, $role[1]['account_id']);
    $this->assertEquals($businessRole[0]['id'], $role[1]['business_role_id']);
    $this->assertEquals($role[1]['name'], $role[2]['name']);
    $this->assertEquals($accountId, $role[2]['account_id']);
    $this->assertEquals($role[1]['business_role_id'], $role[2]['business_role_id']);
    $this->assertEquals($yaml['role'][1]['name'], $role[5]['name']);
    $this->assertEquals(null, $role[5]['account_id']);
    $this->assertEquals($businessRole[1]['id'], $role[5]['business_role_id']);

    $query = "SELECT rp.*,r.name,r.business_role_id from ox_role_privilege rp
                inner join ox_role r on r.id = rp.role_id WHERE r.business_role_id is not null and rp.account_id is not null and rp.app_id=".$appId." order by r.name";
    $rolePrivilege = $this->executeQueryTest($query);
    $this->assertEquals(1, count($rolePrivilege));
    $this->assertEquals($yaml['role'][0]['privileges'][0]['privilege_name'], $rolePrivilege[0]['privilege_name']);
    $this->assertEquals($yaml['role'][0]['privileges'][0]['permission'], $rolePrivilege[0]['permission']);
    $this->assertEquals($role[2]['id'], $rolePrivilege[0]['role_id']);
    $this->assertEquals($appId, $rolePrivilege[0]['app_id']);

    $query = "select * from ox_account_business_role where account_id = $accountId";
    $accountBusinessRole = $this->executeQueryTest($query);
    $this->assertEquals(1, count($accountBusinessRole));
    $this->assertEquals($businessRole[0]['id'], $accountBusinessRole[0]['business_role_id']);

    $query = "select * from ox_app_entity where app_id = $appId order by name";
    $entity = $this->executeQueryTest($query);
    $this->assertEquals(2, count($entity));
    foreach ($entity as $key => $value) {
        $this->assertEquals($yaml['entity'][$key]['name'], $value['name']);
        $this->assertEquals($yaml['entity'][$key]['uuid'], $value['uuid']);
        $this->assertEquals($yaml['entity'][$key]['start_date_field'], $value['start_date_field']);
        $this->assertEquals($yaml['entity'][$key]['end_date_field'], $value['end_date_field']);
        $this->assertEquals($yaml['entity'][$key]['status_field'], $value['status_field']);
        $this->assertEquals(1, $value['created_by']);
        $this->assertEquals(date('Y-m-d'), date_create($value['date_created'])->format('Y-m-d'));
        $this->assertEquals(null, $value['modified_by']);
        $this->assertEquals(null, $value['date_modified']);
        $this->assertEquals(0, $value['override_data']);
    }
    $query = "SELECT ei.* from ox_entity_identifier ei
                inner join ox_app_entity e on e.id = ei.entity_id
                where e.app_id = $appId order by e.name";
    $entityIdentifier = $this->executeQueryTest($query);
    $this->assertEquals(2, count($entityIdentifier));
    foreach ($entityIdentifier as $key => $value) {
        $this->assertEquals($entity[$key]['id'], $value['entity_id']);
        $this->assertEquals($yaml['entity'][$key]['identifiers'][0]['identifier'], $value['identifier']);
    }
    $query = "SELECT ei.* from ox_entity_participant_role ei
                inner join ox_app_entity e on e.id = ei.entity_id
                 order by e.name";
    $participantRoles = $this->executeQueryTest($query);
    $this->assertEquals(2, count($participantRoles));
    foreach ($participantRoles as $key => $value) {
        $this->assertEquals($entity[$key]['id'], $value['entity_id']);
        $this->assertEquals($businessRole[1]['id'], $value['business_role_id']);
    }
    $query = "SELECT * from ox_account_offering oo
                inner join ox_app_entity ae on ae.id = oo.entity_id order by ae.name";
    $acctOffering = $this->executeQueryTest($query);
    $this->assertEquals(2, count($acctOffering));
    foreach ($acctOffering as $key => $value) {
        $this->assertEquals($entity[$key]['id'], $value['entity_id']);
        $this->assertEquals($accountBusinessRole[0]['id'], $value['account_business_role_id']);
    }

    $config = $this->getApplicationConfig();
    $template = $config['TEMPLATE_FOLDER'] . $acctResult[0]['uuid'];
    $delegate = $config['DELEGATE_FOLDER'] . $YmlappUuid;
    $this->assertEquals(file_exists($template), true);
    $this->assertEquals(file_exists($delegate), true);
    $apps = $config['APPS_FOLDER'];
    if (enableExecUtils != 0) {
        if (file_exists($apps) && is_dir($apps)) {
            if (is_link($apps . "/$appName")) {
                $dist = "/dist/";
                $nodemodules = "/node_modules/";
                $this->assertEquals(file_exists($apps . "/$appName" . $dist), true);
                $this->assertEquals(file_exists($apps . "/$appName" . $nodemodules), true);
            }
        }
    }
}

public function testDeployAppOnSaveAppCss()
    {
        $this->setUpTearDownHelper->setupAppDescriptor('application16.yml');
        $this->initAuthToken($this->adminUser);
        if (enableCamundaForDeployApp == 0) {
            $mockProcessManager = $this->getMockProcessManager();
            $mockProcessManager->expects('deploy')->withAnyArgs()->once()->andReturn(array('Process_1dx3jli:1eca438b-007f-11ea-a6a0-bef32963d9ff'));
            $mockProcessManager->expects('parseBPMN')->withAnyArgs()->once()->andReturn(null);
        }
        if (enableExecUtils == 0) {
            $mockRestClient = $this->getMockRestClientForAppService();
            $mockRestClient->expects('post')->with(($this->config['applicationUrl'] . "/installer"), Mockery::any())->once()->andReturn('{"status":"Success"}');
        }
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $mockRestClient->expects('postWithHeader')->with("setupjob", Mockery::any())->once()->andReturn(array('body' => '{"Success":true,"Message":"Job Scheduled Successfully!","JobId":"3a289705-763d-489a-b501-0755b9d4b64b","JobGroup":"autoRenewalJob"}'));
        }
        $path = __DIR__ . '/../../sampleapp/';
        $data = ['path' => $path];
        $this->dispatch('/app/deployapp', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $filename = "application.yml";
        $yaml = Yaml::parse(file_get_contents($path . $filename));
        $appName = $yaml['app']['name'];
        $YmlappUuid = $yaml['app']['uuid'];
        $query = "SELECT name from ox_app where name = '" . $appName . "'";
        $appname = $this->executeQueryTest($query);
        $query = "SELECT uuid from ox_app where name = '" . $appName . "'";
        $appUuid = $this->executeQueryTest($query);
        $appUuidCount = count($appUuid[0]);
        $appUuid = $appUuid[0]['uuid'];
        $query = "SELECT id from ox_app where uuid = '" . $appUuid . "'";
        $appId = $this->executeQueryTest($query);
        $appId = $appId[0]['id'];
        $query = "SELECT count(name),status,uuid,id from ox_account where name = '" . $yaml['org']['name'] . "' GROUP BY name,status,uuid,id";
        $account = $this->executeQueryTest($query);
        $query = "SELECT count(id) as count from ox_app_registry where app_id = '" . $appId . "'";
        $appRegistryResult = $this->executeQueryTest($query);
        $query = "SELECT count(name) as count FROM ox_privilege WHERE app_id = '" . $appId . "'";
        $privilege = $this->executeQueryTest($query);
        $query = "SELECT count(privilege_name) as count from ox_role_privilege WHERE app_id = '" . $appId . "'";
        $rolePrivilege = $this->executeQueryTest($query);
        $query = "SELECT count(id) as count from ox_role WHERE account_id = '" . $account[0]['id'] . "'";
        $role = $this->executeQueryTest($query);
        $query = "SELECT count(role_id) as count FROM ox_role_privilege WHERE privilege_name = 'MANAGE_MY_POLICY2' and app_id = '" . $appId . "'";
        $roleprivilege1 = $this->executeQueryTest($query);
        $query = "SELECT count(role_id) as count FROM ox_role_privilege WHERE privilege_name = 'MANAGE_MY_POLICY' and app_id = '" . $appId . "'";
        $roleprivilege2 = $this->executeQueryTest($query);
        $query = "SELECT * FROM ox_role_privilege rp
                    inner join ox_role r on r.id = rp.role_id
                    WHERE privilege_name = 'MANAGE_POLICY_APPROVAL'";//" and app_id = '" . $appId . "'";
        $roleprivilege3 = $this->executeQueryTest($query);
        $query = "SELECT count(id) as count FROM ox_form WHERE app_id = " . $appId . " and name = 'sampleFormForTests'";
        $form = $this->executeQueryTest($query);
        $query = "SELECT count(id) as count FROM ox_app_menu WHERE app_id = " . $appId;
        $menu = $this->executeQueryTest($query);
        $this->assertEquals($menu[0]['count'], 6);
        $this->assertEquals($form[0]['count'], 1);
        $this->assertEquals($roleprivilege1[0]['count'], 2);
        $this->assertEquals($roleprivilege2[0]['count'], 2);
        $this->assertEquals(count($roleprivilege3), 2);
        $this->assertEquals($role[0]['count'], 5);
        $this->assertEquals($privilege[0]['count'], 3);
        $this->assertEquals($rolePrivilege[0]['count'], 6);
        $this->assertEquals($account[0]['uuid'], $yaml['org']['uuid']);
        $this->assertEquals($appname[0]['name'], $appName);
        $this->assertEquals($appUuid, $YmlappUuid);
        $this->assertEquals($appUuidCount, 1);
        $this->assertEquals($appRegistryResult[0]['count'], 1);
        $this->assertEquals($content['status'], 'success');
        $template = $this->config['TEMPLATE_FOLDER'] . $account[0]['uuid'];
        $delegate = $this->config['DELEGATE_FOLDER'] . $appUuid;
        $this->assertEquals(file_exists($template), true);
        $this->assertEquals(file_exists($delegate), true);
        $apps = $this->config['APPS_FOLDER'];
        $fGet = file_get_contents($apps.$appName.'/index.scss');
        //$this->assertEquals($fGet, $yaml['cssContent']);
        if (enableExecUtils != 0) {
            if (file_exists($apps) && is_dir($apps)) {
                if (is_link($apps . "/$appName")) {
                    $dist = "/dist/";
                    $nodemodules = "/node_modules/";
                    $this->assertEquals(file_exists($apps . "/$appName" . $dist), true);
                    $this->assertEquals(file_exists($apps . "/$appName" . $nodemodules), true);
                }
            }
        }
        $query = "SELECT * from ox_workflow where app_id = " . $appId;
        $workflow = $this->executeQueryTest($query);
        if (enableCamundaForDeployApp == 1) {
            $this->assertEquals(count($workflow), 3);
            foreach ($workflow as $wf) {
                $this->assertNotEmpty($wf['process_id']);
            }
        }
    }


    public function testDeployApp()
    {
        $this->setUpTearDownHelper->setupAppDescriptor('application1.yml');
        $this->initAuthToken($this->adminUser);
        if (enableCamundaForDeployApp == 0) {
            $mockProcessManager = $this->getMockProcessManager();
            $mockProcessManager->expects('deploy')->withAnyArgs()->once()->andReturn(array('Process_1dx3jli:1eca438b-007f-11ea-a6a0-bef32963d9ff'));
            $mockProcessManager->expects('parseBPMN')->withAnyArgs()->once()->andReturn(null);
        }
        if (enableExecUtils == 0) {
            $mockRestClient = $this->getMockRestClientForAppService();
            $mockRestClient->expects('post')->with(($this->config['applicationUrl'] . "/installer"), Mockery::any())->once()->andReturn('{"status":"Success"}');
        }
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $mockRestClient->expects('postWithHeader')->with("setupjob", Mockery::any())->once()->andReturn(array('body' => '{"Success":true,"Message":"Job Scheduled Successfully!","JobId":"3a289705-763d-489a-b501-0755b9d4b64b","JobGroup":"autoRenewalJob"}'));
        }
        $path = __DIR__ . '/../../sampleapp/';
        $data = ['path' => $path];
        $this->dispatch('/app/deployapp', 'POST', $data); 
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $filename = "application.yml";
        $yaml = Yaml::parse(file_get_contents($path . $filename));
        $appName = $yaml['app']['name'];
        $YmlappUuid = $yaml['app']['uuid'];
        $query = "SELECT name from ox_app where name = '" . $appName . "'";
        $appname = $this->executeQueryTest($query);
        
        $query = "SELECT uuid from ox_app where name = '" . $appName . "'";
        $appUuid = $this->executeQueryTest($query);
        
        $appUuidCount = count($appUuid[0]);
        $appUuid = $appUuid[0]['uuid'];
        $query = "SELECT id from ox_app where uuid = '" . $appUuid . "'";
        $appId = $this->executeQueryTest($query);
        $appId = $appId[0]['id'];
        $query = "SELECT count(name),status,uuid,id from ox_account where name = '" . $yaml['org']['name'] . "' GROUP BY name,status,uuid,id";
        $account = $this->executeQueryTest($query);
        
        $query = "SELECT count(id) as count from ox_app_registry where app_id = '" . $appId . "'";
        $appRegistryResult = $this->executeQueryTest($query);
        $query = "SELECT count(name) as count FROM ox_privilege WHERE app_id = '" . $appId . "'";
        $privilege = $this->executeQueryTest($query);
        $query = "SELECT count(privilege_name) as count from ox_role_privilege WHERE app_id = '" . $appId . "'";
        $rolePrivilege = $this->executeQueryTest($query);
        $query = "SELECT count(id) as count from ox_role WHERE account_id = '" . $account[0]['id'] . "'";
        $role = $this->executeQueryTest($query);
        $query = "SELECT count(role_id) as count FROM ox_role_privilege WHERE privilege_name = 'MANAGE_MY_POLICY2' and app_id = '" . $appId . "'";
        $roleprivilege1 = $this->executeQueryTest($query);
        $query = "SELECT count(role_id) as count FROM ox_role_privilege WHERE privilege_name = 'MANAGE_MY_POLICY' and app_id = '" . $appId . "'";
        $roleprivilege2 = $this->executeQueryTest($query);
        $query = "SELECT * FROM ox_role_privilege rp
                    inner join ox_role r on r.id = rp.role_id
                    WHERE privilege_name = 'MANAGE_POLICY_APPROVAL'";//" and app_id = '" . $appId . "'";
        $roleprivilege3 = $this->executeQueryTest($query);
        $query = "SELECT count(id) as count FROM ox_form WHERE app_id = " . $appId . " and name = 'sampleFormForTests'";
        $form = $this->executeQueryTest($query);
        $query = "SELECT count(id) as count FROM ox_app_menu WHERE app_id = " . $appId;
        $menu = $this->executeQueryTest($query);
        $this->assertEquals($menu[0]['count'], 6);
        $this->assertEquals($form[0]['count'], 1);
        $this->assertEquals($roleprivilege1[0]['count'], 2);
        $this->assertEquals($roleprivilege2[0]['count'], 2);
        $this->assertEquals(count($roleprivilege3), 2);
        $this->assertEquals($role[0]['count'], 5);
        $this->assertEquals($privilege[0]['count'], 3);
        $this->assertEquals($rolePrivilege[0]['count'], 6);
        //$this->assertEquals($account[0]['uuid'], $yaml['org']['uuid']);
        $this->assertEquals($appname[0]['name'], $appName);
        $this->assertEquals($appUuid, $YmlappUuid);
        $this->assertEquals($appUuidCount, 1);
        $this->assertEquals($appRegistryResult[0]['count'], 1);
        $this->assertEquals($content['status'], 'success');
        $template = $this->config['TEMPLATE_FOLDER'] . $account[0]['uuid'];
        $delegate = $this->config['DELEGATE_FOLDER'] . $appUuid;
        $this->assertEquals(file_exists($template), true);
        $this->assertEquals(file_exists($delegate), true);
        $apps = $this->config['APPS_FOLDER'];
        if (enableExecUtils != 0) {
            if (file_exists($apps) && is_dir($apps)) {
                if (is_link($apps . "/$appName")) {
                    $dist = "/dist/";
                    $nodemodules = "/node_modules/";
                    $this->assertEquals(file_exists($apps . "/$appName" . $dist), true);
                    $this->assertEquals(file_exists($apps . "/$appName" . $nodemodules), true);
                }
            }
        }
        $query = "SELECT * from ox_workflow where app_id = " . $appId;
        $workflow = $this->executeQueryTest($query);
        if (enableCamundaForDeployApp == 1) {
            $this->assertEquals(count($workflow), 3);
            foreach ($workflow as $wf) {
                $this->assertNotEmpty($wf['process_id']);
            }
        }
    }

    public function testDeployAppWithFieldValidation()
    {
        $this->setUpTearDownHelper->setupAppDescriptor('application12.yml');
        $this->initAuthToken($this->adminUser);
        if (enableExecUtils == 0) {
            $mockRestClient = $this->getMockRestClientForAppService();
            $mockRestClient->expects('post')->with(($this->config['applicationUrl'] . "/installer"), Mockery::any())->once()->andReturn('{"status":"Success"}');
        }
        $data = ['path' => __DIR__ . '/../../sampleapp/'];
        $this->dispatch('/app/deployapp', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $filename = "application.yml";
        $path = __DIR__ . '/../../sampleapp/';
        $yaml = Yaml::parse(file_get_contents($path . $filename));
        $appName = $yaml['app']['name'];
        $YmlappUuid = $yaml['app']['uuid'];
        $query = "SELECT name, uuid from ox_app where name = '" . $appName . "'";
        $appdata = $this->executeQueryTest($query);
        $this->assertEquals($appdata[0]['name'], $appName);
        $this->assertEquals($appdata[0]['uuid'], $YmlappUuid);
        $this->assertEquals($content['status'], 'success');
        $delegate = $this->config['DELEGATE_FOLDER'] . $YmlappUuid;
        $query = "SELECT uuid from ox_app where name = '" . $appName . "'";
        $appUuid = $this->executeQueryTest($query);
        $appUuidCount = count($appUuid[0]);
        $appUuid = $appUuid[0]['uuid'];
        $query = "SELECT id from ox_app where uuid = '" . $appUuid . "'";
        $appId = $this->executeQueryTest($query);
        $appId = $appId[0]['id'];
        $query = "SELECT count(id) as count FROM ox_form WHERE app_id = " . $appId;
        $form = $this->executeQueryTest($query);
        $this->assertEquals($form[0]['count'], 1);
    }

    private function unlinkFolders($appUuid, $appName, $orgUuid = null)
    {
        $file = $this->config['DELEGATE_FOLDER'] . $appUuid;
        if (is_link($file)) {
            unlink($file);
        }
        if ($orgUuid) {
            $file = $this->config['TEMPLATE_FOLDER'] . $orgUuid;
            if (is_link($file)) {
                unlink($file);
            }
        }
        $appName = str_replace(' ', '', $appName);
        $app = $this->config['APPS_FOLDER'] . $appName;
        if (is_link($app)) {
            unlink($app);
        }
        $appData = [
            'name' => $appName,
            'uuid' => $appUuid
        ];
        $appSrcDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $appData);
        if (file_exists($appSrcDir)) {
            FileUtils::rmDir($appSrcDir);
        }
        $appDestDir = AppArtifactNamingStrategy::getDeployAppDirectory($this->config, $appData);
        if (file_exists($appDestDir)) {
            FileUtils::rmDir($appDestDir);
        }
    }

    public function testDeployAppWithoutOptionalFieldsInYml()
    {
        $this->setUpTearDownHelper->setupAppDescriptor('application5.yml');
        $this->initAuthToken($this->adminUser);
        if (enableExecUtils == 0) {
            $mockRestClient = $this->getMockRestClientForAppService();
            $mockRestClient->expects('post')->with(($this->config['applicationUrl'] . "/installer"), Mockery::any())->once()->andReturn('{"status":"Success"}');
        }
        $data = ['path' => __DIR__ . '/../../sampleapp/'];
        $this->dispatch('/app/deployapp', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $filename = "application.yml";
        $path = __DIR__ . '/../../sampleapp/';
        $yaml = Yaml::parse(file_get_contents($path . $filename));
        $appName = $yaml['app']['name'];
        $YmlappUuid = $yaml['app']['uuid'];
        $query = "SELECT name, uuid from ox_app where name = '" . $appName . "'";
        $appdata = $this->executeQueryTest($query);
        $this->assertEquals($appdata[0]['name'], $appName);
        $this->assertEquals($appdata[0]['uuid'], $YmlappUuid);
        $this->assertEquals($content['status'], 'success');
        $delegate = $this->config['DELEGATE_FOLDER'] . $YmlappUuid;
        $this->assertEquals(file_exists($delegate), true);
        $apps = $this->config['APPS_FOLDER'];
        if (file_exists($apps) && is_dir($apps)) {
            if (is_link($apps . "/$appName")) {
                $dist = "/dist/";
                $nodemodules = "/node_modules/";
                $this->assertEquals(file_exists($apps . "/$appName" . $dist), false);
                $this->assertEquals(file_exists($apps . "/$appName" . $nodemodules), false);
            }
        }
        unlink(__DIR__ . '/../../sampleapp/application.yml');
        $appname = $path . 'view/apps/' . $yaml['app']['name'];
        try {
            FileUtils::rmDir($appname);
        } catch (Exception $e) {
        }
        $this->unlinkFolders($YmlappUuid, $appname);
    }

    public function testDeployAppWithWrongUuidAndUniqueNameInDatabase()
    {
        $this->setUpTearDownHelper->setupAppDescriptor('application14.yml');
        $this->initAuthToken($this->adminUser);
        $data = ['path' => __DIR__ . '/../../sampleapp/'];
        if (enableExecUtils == 0) {
            $mockRestClient = $this->getMockRestClientForAppService();
            $mockRestClient->expects('post')->with(($this->config['applicationUrl'] . "/installer"), Mockery::any())->once()->andReturn('{"status":"Success"}');
        }
        if (enableCamundaForDeployApp == 0) {
            $mockProcessManager = $this->getMockProcessManager();
            $mockProcessManager->expects('deploy')->withAnyArgs()->once()->andReturn(array('Process_1dx3jli:1eca438b-007f-11ea-a6a0-bef32963d9ff'));
            $mockProcessManager->expects('parseBPMN')->withAnyArgs()->once()->andReturn(null);
        }
        $this->dispatch('/app/deployapp', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        unlink(__DIR__ . '/../../sampleapp/application.yml');

        $query = 'SELECT name, uuid FROM ox_app WHERE id=(SELECT max(id) from ox_app)';
        $latestAppData = $this->executeQueryTest($query)[0];
    } 

    private function setupAppFolder($path)
    {
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $appData = $appService->loadAppDescriptor($path);
        $path = $appService->setupOrUpdateApplicationDirectoryStructure($appData);
        return $path."/";
    }

    

    public function testDeployAppWithNameAndNoUuidInYMLButNameandUuidInDatabase()
    {
        $this->setUpTearDownHelper->setupAppDescriptor('application10.yml');
        $this->initAuthToken($this->adminUser);
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $mockRestClient->expects('postWithHeader')->with("setupjob", Mockery::any())->once()->andReturn(array('body' => '{"Success":true,"Message":"Job Scheduled Successfully!","JobId":"3a289705-763d-489a-b501-0755b9d4b64b","JobGroup":"autoRenewalJob"}'));
        }
        if (enableExecUtils == 0) {
            $mockRestClient = $this->getMockRestClientForAppService();
            $mockRestClient->expects('post')->with(($this->config['applicationUrl'] . "/installer"), Mockery::any())->once()->andReturn('{"status":"Success"}');
        }
        $data = ['path' => __DIR__ . '/../../sampleapp/'];
        $this->dispatch('/app/deployapp', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $filename = "application.yml";
        $path = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $content['data']['app'])."/";
        $yaml = Yaml::parse(file_get_contents($path . $filename));
        $this->assertEquals(isset($yaml['app']['uuid']), true);
        $appName = $yaml['app']['name'];
        $YmlappUuid = $yaml['app']['uuid'];
        $query = "SELECT name, uuid from ox_app where name = '" . $appName . "'";
        $appdata = $this->executeQueryTest($query);
        $this->assertEquals($appdata[0]['name'], $appName);
        $this->assertEquals($appdata[0]['uuid'], $YmlappUuid);
        $this->assertEquals($content['status'], 'success');
        $query = "SELECT count(name),status,uuid from ox_account where name = '" . $yaml['org']['name'] . "' GROUP BY name,status,uuid";
        $account = $this->executeQueryTest($query);
        $this->assertEquals($account[0]['uuid'], $yaml['org']['uuid']);
        $template = $this->config['TEMPLATE_FOLDER'] . $account[0]['uuid'];
        $delegate = $this->config['DELEGATE_FOLDER'] . $YmlappUuid;
        $this->assertEquals(file_exists($template), true);
        $this->assertEquals(file_exists($delegate), true);
        unlink(__DIR__ . '/../../sampleapp/application.yml');
        $appname = $path . 'view/apps/' . $yaml['app']['name'];
        try {
            FileUtils::rmDir($appname);
        } catch (Exception $e) {
        }
        $this->unlinkFolders($YmlappUuid, $appName, $yaml['org']['uuid']);
    }

    public function testDeployAppNoDirectory()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['path' => __DIR__ . '/../../sampleapp1/'];
        $this->dispatch('/app/deployapp', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testDeployAppNoFile()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['path' => __DIR__ . '/../../sampleapp2/'];
        $this->dispatch('/app/deployapp', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testDeployAppNoFileData()
    {
        $this->setUpTearDownHelper->setupAppDescriptor('application2.yml');
        $this->initAuthToken($this->adminUser);
        $data = ['path' => __DIR__ . '/../../sampleapp/'];
        $this->dispatch('/app/deployapp', 'POST', $data);
        $this->assertResponseStatusCode(500);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        unlink(__DIR__ . '/../../sampleapp/application.yml');
    }

    public function testDeployAppNoAppData()
    {
        $this->setUpTearDownHelper->setupAppDescriptor('application3.yml');
        $this->initAuthToken($this->adminUser);
        $data = ['path' => __DIR__ . '/../../sampleapp/'];
        $this->dispatch('/app/deployapp', 'POST', $data);
        $this->assertResponseStatusCode(500);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        unlink(__DIR__ . '/../../sampleapp/application.yml');
    }

    public function testDeployAppOrgDataWithoutUuidAndContactAndPreferencesInYml()
    {
        $this->setUpTearDownHelper->setupAppDescriptor('application4.yml');
        $this->initAuthToken($this->adminUser);
        $data = ['path' => __DIR__ . '/../../sampleapp/'];
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $mockRestClient->expects('postWithHeader')->with("setupjob", Mockery::any())->once()->andReturn(array('body' => '{"Success":true,"Message":"Job Scheduled Successfully!","JobId":"3a289705-763d-489a-b501-0755b9d4b64b","JobGroup":"autoRenewalJob"}'));
        }
        if (enableExecUtils == 0) {
            $mockRestClient = $this->getMockRestClientForAppService();
            $mockRestClient->expects('post')->with(($this->config['applicationUrl'] . "/installer"), Mockery::any())->once()->andReturn('{"status":"Success"}');
        }
        $this->dispatch('/app/deployapp', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $filename = "application.yml";
        $path = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $content['data']['app'])."/";
        $yaml = Yaml::parse(file_get_contents($path . $filename));
        $appName = $yaml['app']['name'];
        $YmlappUuid = $yaml['app']['uuid'];
        $this->assertNotEmpty($yaml['org']['uuid']);
        $this->assertNotEmpty($yaml['org']['contact']);
        $this->assertEquals($yaml['org']['preferences'], '{}');
        $this->assertEquals($content['status'], 'success');
        $query = "SELECT count(name),status,uuid from ox_account where name = '" . $yaml['org']['name'] . "' GROUP BY name,status,uuid";
        $account = $this->executeQueryTest($query);
        $this->assertEquals($account[0]['uuid'], $yaml['org']['uuid']);
        $template = $this->config['TEMPLATE_FOLDER'] . $account[0]['uuid'];
        $delegate = $this->config['DELEGATE_FOLDER'] . $YmlappUuid;
        $this->assertEquals(file_exists($template), true);
        $this->assertEquals(file_exists($delegate), true);
        unlink(__DIR__ . '/../../sampleapp/application.yml');
        $appname = $path . 'view/apps/' . $yaml['app']['name'];
        try {
            FileUtils::rmDir($appname);
        } catch (Exception $e) {
        }
        $this->unlinkFolders($YmlappUuid, $appName, $yaml['org']['uuid']);
    }

    public function testDeployAppAddExtraPrivilegesInDatabaseFromYml()
    {
        $this->setUpTearDownHelper->setupAppDescriptor('application6.yml');
        $this->initAuthToken($this->adminUser);
        $path = __DIR__ . '/../../sampleapp/';
        $path = $this->setupAppFolder($path);
        $data = ['path' => $path];

        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $mockRestClient->expects('postWithHeader')->with("setupjob", Mockery::any())->once()->andReturn(array('body' => '{"Success":true,"Message":"Job Scheduled Successfully!","JobId":"3a289705-763d-489a-b501-0755b9d4b64b","JobGroup":"autoRenewalJob"}'));
        }
        if (enableExecUtils == 0) {
            $mockRestClient = $this->getMockRestClientForAppService();
            $mockRestClient->expects('post')->with(($this->config['applicationUrl'] . "/installer"), Mockery::any())->once()->andReturn('{"status":"Success"}');
        }

        $this->dispatch('/app/deployapp', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $filename = "application.yml";
        $path = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $content['data']['app'])."/";
        $yaml = Yaml::parse(file_get_contents($path . $filename));
        $appName = $yaml['app']['name'];
        $YmlappUuid = $yaml['app']['uuid'];
        $privilegearray = array_unique(array_column($yaml['privilege'], 'name'));
        $appid = "SELECT id FROM ox_app WHERE name = '" . $yaml['app']['name'] . "'";
        $idresult = $this->executeQueryTest($appid);
        $queryString = "SELECT name FROM ox_privilege WHERE app_id = '" . $idresult[0]['id'] . "'";
        $result = $this->executeQueryTest($queryString);
        $DBprivilege = array_unique(array_column($result, 'name'));
        $query = "SELECT count(name),status,uuid from ox_account where name = '" . $yaml['org']['name'] . "' GROUP BY name,status,uuid";
        $account = $this->executeQueryTest($query);
        $this->assertEquals($account[0]['uuid'], $yaml['org']['uuid']);
        $this->assertEquals($privilegearray, $DBprivilege);
        $this->assertEquals($content['status'], 'success');
        $template = $this->config['TEMPLATE_FOLDER'] . $account[0]['uuid'];
        $delegate = $this->config['DELEGATE_FOLDER'] . $YmlappUuid;
        $this->assertEquals(file_exists($template), true);
        $this->assertEquals(file_exists($delegate), true);
        unlink(__DIR__ . '/../../sampleapp/application.yml');
        $appname = $path . 'view/apps/' . $yaml['app']['name'];
        try {
            FileUtils::rmDir($appname);
        } catch (Exception $e) {
        }
        $this->unlinkFolders($YmlappUuid, $appName, $yaml['org']['uuid']);
    }

    public function testDeployAppDeleteExtraPrivilegesInDatabaseNotInYml()
    {
        $this->setUpTearDownHelper->setupAppDescriptor('application6.yml');
        $this->initAuthToken($this->adminUser);
        $path = __DIR__ . '/../../sampleapp/';
        $path = $this->setupAppFolder($path);
        $data = ['path' => $path];

        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $mockRestClient->expects('postWithHeader')->with("setupjob", Mockery::any())->once()->andReturn(array('body' => '{"Success":true,"Message":"Job Scheduled Successfully!","JobId":"3a289705-763d-489a-b501-0755b9d4b64b","JobGroup":"autoRenewalJob"}'));
        }
        if (enableExecUtils == 0) {
            $mockRestClient = $this->getMockRestClientForAppService();
            $mockRestClient->expects('post')->with(($this->config['applicationUrl'] . "/installer"), Mockery::any())->once()->andReturn('{"status":"Success"}');
        }
        $this->dispatch('/app/deployapp', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $filename = "application.yml";
        $path = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $content['data']['app'])."/";
        $yaml = Yaml::parse(file_get_contents($path . $filename));
        $appName = $yaml['app']['name'];
        $YmlappUuid = $yaml['app']['uuid'];
        $appid = "SELECT id FROM ox_app WHERE name = '" . $yaml['app']['name'] . "'";
        $idresult = $this->executeQueryTest($appid);
        $queryString = "SELECT name FROM ox_privilege WHERE app_id = '" . $idresult[0]['id'] . "'";
        $result = $this->executeQueryTest($queryString);
        $DBprivilege = array_unique(array_column($result, 'name'));
        $list = "'" . implode("', '", $DBprivilege) . "'";
        $query = "SELECT count(name),status,uuid from ox_account where name = '" . $yaml['org']['name'] . "' GROUP BY name,status,uuid";
        $account = $this->executeQueryTest($query);
        $this->assertEquals($account[0]['uuid'], $yaml['org']['uuid']);
        $this->assertNotEquals($list, 'MANAGE');
        $this->assertEquals($content['status'], 'success');
        $template = $this->config['TEMPLATE_FOLDER'] . $account[0]['uuid'];
        $delegate = $this->config['DELEGATE_FOLDER'] . $YmlappUuid;
        $this->assertEquals(file_exists($template), true);
        $this->assertEquals(file_exists($delegate), true);
        unlink(__DIR__ . '/../../sampleapp/application.yml');
        $appname = $path . 'view/apps/' . $yaml['app']['name'];
        try {
            FileUtils::rmDir($appname);
        } catch (Exception $e) {
        }
        $this->unlinkFolders($YmlappUuid, $appName, $yaml['org']['uuid']);
    }

    public function testDeployAppWithNoEntityInYml()
    {
        $this->setUpTearDownHelper->setupAppDescriptor('application7.yml');
        $this->initAuthToken($this->adminUser);
        if (enableCamundaForDeployApp == 0) {
            $mockProcessManager = $this->getMockProcessManager();
            $mockProcessManager->expects('deploy')->withAnyArgs()->once()->andReturn(array('Process_1dx3jli:1eca438b-007f-11ea-a6a0-bef32963d9ff'));
            $mockProcessManager->expects('parseBPMN')->withAnyArgs()->once()->andReturn(null);
        }
        $data = ['path' => __DIR__ . '/../../sampleapp/'];
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $mockRestClient->expects('postWithHeader')->with("setupjob", Mockery::any())->once()->andReturn(array('body' => '{"Success":true,"Message":"Job Scheduled Successfully!","JobId":"3a289705-763d-489a-b501-0755b9d4b64b","JobGroup":"autoRenewalJob"}'));
        }
        if (enableExecUtils == 0) {
            $mockRestClient = $this->getMockRestClientForAppService();
            $mockRestClient->expects('post')->with(($this->config['applicationUrl'] . "/installer"), Mockery::any())->once()->andReturn('{"status":"Success"}');
        }
        $this->dispatch('/app/deployapp', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $filename = "application.yml";
        $path = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $content['data']['app'])."/";
        $yaml = Yaml::parse(file_get_contents($path . $filename));
        $appName = $yaml['app']['name'];
        $YmlappUuid = $yaml['app']['uuid'];
        $this->assertEquals($content['status'], 'success');
        unlink(__DIR__ . '/../../sampleapp/application.yml');
        $appname = $path . 'view/apps/' . $yaml['app']['name'];
        try {
            FileUtils::rmDir($appname);
        } catch (Exception $e) {
        }
        $this->unlinkFolders($YmlappUuid, $appName, $yaml['org']['uuid']);
    }

    
    public function testDeployApplication()
    {
        $sampleAppUuidFromWorkflowYml = '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        $appName = 'SampleApp';
        $appSourceDir = $this->config['EOX_APP_SOURCE_DIR'] . "${appName}_${sampleAppUuidFromWorkflowYml}";
        $appDestDir = $this->config['EOX_APP_DEPLOY_DIR'] . "${appName}_${sampleAppUuidFromWorkflowYml}";
        try {
            if (file_exists($appSourceDir)) {
                FileUtils::rmDir($appSourceDir);
                mkdir($appSourceDir);
            }
            $eoxSampleApp = dirname(__FILE__) . '/../../Dataset/SampleApp';
            FileUtils::copyDir($eoxSampleApp, $appSourceDir);
            $this->testDeployApp();
        } catch (Exception $e) {
            throw $e;
        } finally {
            try {
                if (file_exists($appSourceDir)) {
                    FileUtils::rmDir($appSourceDir);
                }
            } catch (Exception $e) {
                print($e);
            }
            try {
                if (file_exists($appDestDir)) {
                    FileUtils::rmDir($appDestDir);
                }
            } catch (Exception $e) {
                print($e);
            }
        }
    }

    public function testDeployApplicationWithoutAppInDatabase()
    {
        $this->initAuthToken($this->adminUser);
        $notExistingAppUuid = '11111111-1111-1111-1111-111111111111';
        $this->dispatch("/app/${notExistingAppUuid}/deploy", 'POST');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals('Entity not found.', $content['message']);
        $data = $content['data'];
        $this->assertEquals('App', $data['entity']);
        $this->assertEquals($notExistingAppUuid, $data['uuid']);
    }

    public function testDeployApplicationWithoutSourceAppDir()
    {
        $sampleAppUuidFromWorkflowYml = '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        $appName = 'SampleApp';
        $appSourceDir = $this->config['EOX_APP_SOURCE_DIR'] . "${sampleAppUuidFromWorkflowYml}";
        //Ensure source directory does not exist.
        if (file_exists($appSourceDir)) {
            FileUtils::rmDir($appSourceDir);
        }

        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${sampleAppUuidFromWorkflowYml}/deploy", 'POST');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Application source directory not found.');
    }

    //-----------------------------------------------------------------------------------------------
    //IMPORTANT: This test is not implemented because it needs intrusive changes (deleting/moving)
    //to the template application.
    //-----------------------------------------------------------------------------------------------
   public function testDeployApplicationWithoutTemplateApp() {
       $sampleAppUuidFromWorkflowYml = '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
       $this->initAuthToken($this->adminUser);
       $this->dispatch("/app/${sampleAppUuidFromWorkflowYml}/deploy", 'POST');
       $this->assertResponseStatusCode(404);
       $content = (array) json_decode($this->getResponse()->getContent(), true);
       $this->assertEquals($content['status'], 'error');
       $this->assertEquals($content['message'], 'Application source directory not found.');
   }

    private function setupAppSourceDir($ymlData)
    {
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $appService->setupOrUpdateApplicationDirectoryStructure($ymlData);
    }

    public function testUpdate()
    {
        $uuid = '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        $query = "SELECT * FROM ox_app WHERE uuid='${uuid}'";
        //Take snapshot of database record.
        $recordSetBeforeUpdate = $this->executeQueryTest($query);
        $recordBeforeUpdate = $recordSetBeforeUpdate[0];
        //Setup data and update.
        $data = [
            'app' => [
                'name' => 'Admin App',
                'uuid' => $uuid,
                'type' => 2,
                'category' => 'Admin',
                'logo' => 'app.png'
            ]
        ];
        $this->setupAppSourceDir($data);
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch("/app/${uuid}", 'PUT', null);
        //Assert the results.
        $this->assertResponseStatusCode(200);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->setDefaultAsserts();
        //Assert database record is updated.
        $recordSetAfterUpdate = $this->executeQueryTest($query);
        $recordAfterUpdate = $recordSetAfterUpdate[0];
        $this->assertNotEquals($recordBeforeUpdate['name'], $recordAfterUpdate['name']);
        $this->assertNotEquals($recordBeforeUpdate['category'], $recordAfterUpdate['category']);
        $this->assertNotEquals($recordBeforeUpdate['logo'], $recordAfterUpdate['logo']);
        $this->assertEquals($data['app']['name'], $recordAfterUpdate['name']);
        $this->assertEquals($data['app']['category'], $recordAfterUpdate['category']);
        $this->assertEquals($data['app']['logo'], $recordAfterUpdate['logo']);
        //Assert returned data matches.
        $returnData = $content['data'];
        $appData = $returnData['app'];
        $this->assertEquals($data['app']['name'], $appData['name']);
        $this->assertEquals($data['app']['category'], $appData['category']);
        $this->assertEquals($data['app']['logo'], $appData['logo']);
        //Check application descriptor is created and is as expected.
        $srcAppDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->getApplicationConfig(), $appData);
        $this->assertTrue(file_exists($srcAppDir));
        $appDescriptorFilePath = $srcAppDir . DIRECTORY_SEPARATOR . AppService::APPLICATION_DESCRIPTOR_FILE_NAME;
        $yamlData = Yaml::parse(file_get_contents($appDescriptorFilePath));
        $this->assertEquals($returnData, $yamlData);
    }

    public function testUpdateWithoutAccessPermission()
    {
        $uuid = '11111111-1111-1111-1111-111111111111';
        $query = "SELECT id, name FROM ox_app";
        //Take snapshot of database record.
        $recordSetBeforeUpdate = $this->executeQueryTest($query);
        //Setup data and invoke the test.
        $data = [
            'app' => [
                'name' => 'Admin App',
                'uuid' => $uuid,
                'type' => 2,
                'category' => 'EXAMPLE_CATEGORY',
                'logo' => 'app.png'
            ]
        ];
        $this->initAuthToken($this->employeeUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch("/app/${uuid}", 'PUT', $data);
        //Run post test assertions.
        $this->assertResponseStatusCode(401);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You have no Access to this API');
        //Take database record snapshot after test.
        $recordSetAfterUpdate = $this->executeQueryTest($query);
        $this->assertEquals($recordSetBeforeUpdate, $recordSetAfterUpdate);
    }

    public function testUpdateWithoutExistingAppSrcDir()
    {
        $uuid = '11111111-1111-1111-1111-111111111111';
        $query = "SELECT id, name FROM ox_app";
        //Take snapshot of database record.
        $recordSetBeforeUpdate = $this->executeQueryTest($query);
        //Setup data and invoke the test.
        $data = [
            'app' => [
                'name' => 'Admin App',
                'uuid' => $uuid,
                'type' => 2,
                'category' => 'EXAMPLE_CATEGORY',
                'logo' => 'app.png'
            ]
        ];
        //Make sure app source directory does not exist.
        $appSrcDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->getApplicationConfig(), $data['app']);
        if (file_exists($appSrcDir)) {
            FileUtils::rmDir($appSrcDir);
        }
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch("/app/${uuid}", 'PUT', null);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Application source directory is not found.');
        $errorContext = $content['data'];
        $this->assertEquals($appSrcDir, $errorContext['directory']);
        //Take database record snapshot after test.
        $recordSetAfterUpdate = $this->executeQueryTest($query);
        $this->assertEquals($recordSetBeforeUpdate, $recordSetAfterUpdate);
    }

    public function testUpdateEntityNotFound()
    {
        $uuid = '11111111-1111-1111-1111-111111111111';
        $query = "SELECT id, name FROM ox_app";
        //Take snapshot of database record.
        $recordSetBeforeUpdate = $this->executeQueryTest($query);
        //Setup data and invoke the test.
        $data = [
            'app' => [
                'name' => 'Admin App',
                'uuid' => $uuid,
                'type' => 2,
                'category' => 'EXAMPLE_CATEGORY',
                'logo' => 'app.png'
            ]
        ];
        $this->setupAppSourceDir($data);
        //Ensure entity with given UUID does not exist in the database.
        $entityRecordSet = $this->executeQueryTest("SELECT id FROM ox_app WHERE uuid='${uuid}'");
        $this->assertEmpty($entityRecordSet);
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch("/app/${uuid}", 'PUT', null);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Entity not found.');
        $errorContext = $content['data'];
        $this->assertEquals('ox_app', $errorContext['entity']);
        $this->assertEquals($uuid, $errorContext['uuid']);
        //Take database record snapshot after test.
        $recordSetAfterUpdate = $this->executeQueryTest($query);
        $this->assertEquals($recordSetBeforeUpdate, $recordSetAfterUpdate);
    }

    public function testUpdateWithUuidMismatch()
    {
        $uuid1 = '11111111-1111-1111-1111-111111111111';
        $uuid2 = '22222222-2222-2222-2222-222222222222';
        $query = "SELECT id, name FROM ox_app";
        //Take snapshot of database record.
        $recordSetBeforeUpdate = $this->executeQueryTest($query);
        //Setup data and invoke the test.
        $data = [
            'app' => [
                'name' => 'Admin App',
                'uuid' => $uuid1,
                'type' => 2,
                'category' => 'EXAMPLE_CATEGORY',
                'logo' => 'app.png'
            ]
        ];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch("/app/${uuid2}", 'PUT', null);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(406);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'UUID in URL and UUID in data set are not matching.');
        //Take database record snapshot after test.
        $recordSetAfterUpdate = $this->executeQueryTest($query);
        $this->assertEquals($recordSetBeforeUpdate, $recordSetAfterUpdate);
    }

    public function testDelete()
    {
        $this->initAuthToken($this->adminUser);
        $entityRecordSetBeforeDeletion = $this->executeQueryTest("SELECT id, name FROM ox_app WHERE uuid='1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4'");
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4', 'DELETE', null);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $entityRecordSetAfterDeletion = $this->executeQueryTest("SELECT name,uuid FROM ox_app WHERE id=199");
        $this->assertEquals($entityRecordSetAfterDeletion[0]['name'], $entityRecordSetBeforeDeletion[0]['id'].'_'.$entityRecordSetBeforeDeletion[0]['name']);
        $this->assertNotEquals($entityRecordSetAfterDeletion[0]['uuid'], '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4');
    }

    public function testDeleteNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/11111111-1111-1111-1111-111111111111', 'DELETE', null);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testGetListOfAssignmentsWithoutFiltersValues()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/assignments?filter=[{"skip":0,"take":1}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(AppController::class);
        $this->assertControllerClass('AppController');
        $this->assertMatchedRouteName('assignments');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['total'], 1);
    }

    public function testGetListOfAssignmentsWithoutFilters()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/assignments', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(AppController::class);
        $this->assertControllerClass('AppController');
        $this->assertMatchedRouteName('assignments');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['total'], 1);
    }

    public function testGetListOfAssignmentsWithFilterAndSort()
    {
        $this->initAuthToken($this->adminUser);
        $product = 'Individual Professional Liability';
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/assignments?filter=[{"filter":{"logic":"and","filters":[{"field":"product","operator":"eq","value":"' . $product . '"}]},"sort":[{"field":"product","dir":"desc"}]}]', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(AppController::class);
        $this->assertControllerClass('AppController');
        $this->assertMatchedRouteName('assignments');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['product'], $product);
        $this->assertEquals($content['total'], 1);
    }

    public function testDeployAppWithRegisterUser()
    {
        $this->setUpTearDownHelper->setupAppDescriptor('applicationhubdrive.yml');
        $this->initAuthToken($this->adminUser);
        if (enableCamundaForDeployApp == 0) {
            $mockProcessManager = $this->getMockProcessManager();
            $mockProcessManager->expects('deploy')->withAnyArgs()->once()->andReturn(array('Process_1dx3jli:1eca438b-007f-11ea-a6a0-bef32963d9ff'));
            $mockProcessManager->expects('parseBPMN')->withAnyArgs()->once()->andReturn(null);
        }
        if (enableExecUtils == 0) {
            $mockRestClient = $this->getMockRestClientForAppService();
            $mockRestClient->expects('post')->with(($this->config['applicationUrl'] . "/installer"), Mockery::any())->once()->andReturn('{"status":"Success"}');
        }
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $mockRestClient->expects('postWithHeader')->with("setupjob", Mockery::any())->once()->andReturn(array('body' => '{"Success":true,"Message":"Job Scheduled Successfully!","JobId":"3a289705-763d-489a-b501-0755b9d4b64b","JobGroup":"autoRenewalJob"}'));
        }
        $path = __DIR__ . '/../../sampleapp/';
        $data = ['path' => $path];
        $this->dispatch('/app/deployapp', 'POST', $data);
        $sel = "select * from ox_app where uuid = 'a4b1f073-fc20-477f-a804-1aa206938c42'";
        $res = $this->executeQueryTest($sel);
        $data = '{"firstname":"Solomon","lastname":"Yates","address1":"Test Address","country":"India","email":"xapil@mailinator.com","type" :"BUSINESS","businessRole":"Independent Contractor","IcLastName":"Yates","autoLiability":true,"cargoInsurance":true,"city":"Obcaecati facere dol","dataGrid":"[{\"nameDriverUnit\":\"Cain\",\"driverLastName\":\"Rosario\",\"street1DriverUnitInfo\":\"Dolores quidem non q\",\"city1DriverUnitInfo\":\"Dolorum ut excepturi\",\"stateDriverUnitInfo\":{\"name\":\"California\",\"abbreviation\":\"CA\"},\"driverEmail\":\"xonu@mailinator.com\",\"zipCode1DriverUnitInfo\":23990}]","dataGrid1":"[{\"vinDriverInfo\":\"Temporibus aliquam i\",\"makeVin\":\"Rem voluptas et itaq\",\"modelVin\":\"Praesentium obcaecat\",\"yearVin\":1974}]","dataGridtwo":"[{\"documents\":[]}]","effectiveDate":"","iCEmail":"xapil@mailinator.com","iCFirstName":"Solomon","identifier_field":"iCEmail","name":"Solomon Yates","state":"Colorado","street1IC":"Laboriosam modi cum","zip":63284,"appId":"a4b1f073-fc20-477f-a804-1aa206938c42","entity_name":"On Trac Compliance","fileId":"5849fa67-ad9a-4a23-a343-ae3ae5e99761","uuid":"5849fa67-ad9a-4a23-a343-ae3ae5e99761","accountId":null,"app_id":"'.$res[0]['id'].'","workFlowId":null,"attachments":[{"fullPath":"/app/api/v1/config/autoload/../../data/file_docs/6b88905a-fa7b-47a4-af18-a5eed6ade5c5/5849fa67-ad9a-4a23-a343-ae3ae5e99761/OnTracRSPComplianceChecklistTemplate.pdf","file":"6b88905a-fa7b-47a4-af18-a5eed6ade5c5/5849fa67-ad9a-4a23-a343-ae3ae5e99761/OnTracRSPComplianceChecklistTemplate.pdf","originalName":"OnTracRSPComplianceChecklistTemplate.pdf","type":"file/pdf"}],"version":2}';
        $data = json_decode($data, true);
        $registrationService = $this->getRegistrationService();
        $registrationService->registerAccount($data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->performAssertions($data);
        
    }


    private function performAssertions($data)
    {
        // Role privilege check
        $sqlQuery = 'SELECT u.id, up.firstname, up.lastname, up.email, u.account_id FROM ox_user u inner join ox_person up on up.id = u.person_id order by u.id DESC LIMIT 1';
        $newQueryResult = $this->executeQueryTest($sqlQuery);
        $accountId = $newQueryResult[0]['account_id'];
        $sqlQuery = 'SELECT * FROM ox_account where id = '.$accountId;
        $acctResult = $this->executeQueryTest($sqlQuery);
        $sqlQuery = 'SELECT br.* FROM ox_account_business_role obr inner join ox_business_role br on obr.business_role_id = br.id where obr.account_id = '.$accountId;
        $bussRoleResult = $this->executeQueryTest($sqlQuery);
        $sqlQuery = 'SELECT * FROM ox_role where account_id = '.$accountId;
        $roleResult = $this->executeQueryTest($sqlQuery);
        $this->assertEquals(4, count($roleResult));
        $this->assertEquals($roleResult[0]['name'], 'ADMIN');
        $this->assertEquals($roleResult[1]['name'], 'MANAGER');
        $this->assertEquals($roleResult[2]['name'], 'EMPLOYEE');
        $this->assertEquals($roleResult[3]['name'], 'Manage Drivers');
        $sqlQuery = 'SELECT * FROM ox_role_privilege where account_id = '.$accountId.' AND role_id = '.$roleResult[3]['id']; 
        $rolePriviResult = $this->executeQueryTest($sqlQuery);
        $this->assertEquals(1, count($rolePriviResult));
        $this->assertEquals($rolePriviResult[0]['privilege_name'], 'MANAGE_EMPLOYEE');
        $this->assertEquals($rolePriviResult[0]['role_id'], $roleResult[3]['id']);
        $sqlQuery = "SELECT ur.*,oxr.name as roleName FROM ox_user_role ur 
                        INNER JOIN ox_account_user au on au.id = ur.account_user_id
                        INNER JOIN ox_user u on u.id = au.user_id
                        inner join ox_role oxr on oxr.id = ur.role_id
                    where u.id = ".$newQueryResult[0]['id'];
        $urResult = $this->executeQueryTest($sqlQuery);
        $this->assertEquals($data['iCFirstName'], $newQueryResult[0]['firstname']);
        $this->assertEquals($data['IcLastName'], $newQueryResult[0]['lastname']);
        $this->assertEquals($data['iCEmail'], $newQueryResult[0]['email']);
        if ($data['type'] == 'INDIVIDUAL') {
            $this->assertEquals($data['iCFirstName']." ".$data['IcLastName'], $acctResult[0]['name']);
        } else {
            $this->assertEquals($data['name'], $acctResult[0]['name']);
        }
        
        $this->assertEquals($data['type'], $acctResult[0]['type']);
        $this->assertEquals($newQueryResult[0]['id'], $acctResult[0]['contactid']);
        if (isset($data['identifier_field'])) {
            $sqlQuery = "SELECT * FROM ox_wf_user_identifier where identifier_name = '".$data['identifier_field']."' AND identifier = '".$data[$data['identifier_field']]."'";
            $identifierResult = $this->executeQueryTest($sqlQuery);
            $this->assertEquals(1, count($identifierResult));
            $this->assertEquals($acctResult[0]['id'], $identifierResult[0]['account_id']);
            $this->assertEquals($newQueryResult[0]['id'], $identifierResult[0]['user_id']);
        }
        if (isset($data['businessRole'])) {
            $this->assertEquals($data['businessRole'], $bussRoleResult[0]['name']);
            $this->assertEquals("ADMIN", $roleResult[0]['name']);            
            $this->assertEquals(2, count($urResult));
            $this->assertEquals($urResult[1]['role_id'], $roleResult[3]['id']);
            $this->assertEquals($urResult[1]['roleName'], 'Manage Drivers');
        } else {
            $this->assertEquals(3, count($roleResult));
            $this->assertEquals(1, count($urResult));
        }
        $sqlQuery = "SELECT ar.* from ox_app_registry ar inner join ox_app a on a.id = ar.app_id 
                        where a.uuid = '".$data['appId']."' AND account_id = $accountId";

        $result = $this->executeQueryTest($sqlQuery);
        $this->assertEquals(1, count($result));
        $this->assertEquals(date('Y-m-d'), date_create($result[0]['date_created'])->format('Y-m-d'));
    }
    //NEED TO ADD INSTALL/UNINSTALL TESTS -SADHITHA

    public function testDeployAppWithBusinessRole()
    {
        $this->setUpTearDownHelper->setupAppDescriptor('applicationhubdrive.yml');
        $this->initAuthToken($this->adminUser);
        $path = __DIR__ . '/../../sampleapp/';
        $data = ['path' => $path];
        $this->dispatch('/app/deployapp', 'POST', $data);
        $select = "SELECT * from ox_business_role";
        $result = $this->executeQueryTest($select);
     
        $select = "SELECT * from ox_role where account_id is NULL and business_role_id = ".$result[0]['id'];
        $result1 = $this->executeQueryTest($select);
        $this->assertEquals($result1[0]['name'], "Manage Executive");
        $this->assertEquals($result1[0]['uuid'], "caddaf9f-64eb-4e57-8176-3647a59ecbc4");
        $this->assertEquals($result1[1]['name'], "Safety Director");
        $this->assertEquals($result1[1]['uuid'], "18ed4d29-7188-482e-b544-67797c319935");
        $this->assertEquals(count($result1),6);

        $select = "SELECT * from ox_role where account_id is NULL and business_role_id = ".$result[1]['id'];
        $result2 = $this->executeQueryTest($select);
        $this->assertEquals($result2[0]['name'], "Manage Executives");
        $this->assertEquals($result2[0]['uuid'], "efcbf9d6-b867-4810-a88d-8c5d01c11067");
        $this->assertEquals($result2[1]['name'], "Compliance Manager");
        $this->assertEquals($result2[1]['uuid'], "7d73ada4-8e1c-436d-8c27-17503ac7e254");
        $this->assertEquals(count($result2),5);
        
        $select = "SELECT * from ox_role where account_id is NULL and business_role_id = ".$result[2]['id'];
        $result3 = $this->executeQueryTest($select);
        $this->assertEquals($result3[0]['name'], "Manage Drivers");
        $this->assertEquals($result3[0]['uuid'], "d3ef9902-3bf8-4d70-8c79-129e6bc4e45c");
        $this->assertEquals(count($result3),1);
    }

    public function testDeployAppWithSameBusinessRoleforTwoAccount()
    {
        $this->setUpTearDownHelper->setupAppDescriptor('applicationhubdrive.yml');
        $this->initAuthToken($this->adminUser);
        $path = __DIR__ . '/../../sampleapp/';
        $data = ['path' => $path];
        $this->dispatch('/app/deployapp', 'POST', $data);
        $select = "SELECT * from ox_business_role";
        $result = $this->executeQueryTest($select);
     
        $select = "SELECT * from ox_role where account_id is NULL and business_role_id = ".$result[0]['id'];
        $result1 = $this->executeQueryTest($select);
        $this->assertEquals($result1[0]['name'], "Manage Executive");
        $this->assertEquals($result1[0]['uuid'], "caddaf9f-64eb-4e57-8176-3647a59ecbc4");

        $select = "SELECT * from ox_role where account_id is NULL and business_role_id = ".$result[1]['id'];
        $result2 = $this->executeQueryTest($select);
        $this->assertEquals($result2[0]['name'], "Manage Executives");
        $this->assertEquals($result2[0]['uuid'], "efcbf9d6-b867-4810-a88d-8c5d01c11067");
    }

    
    /* HARI */
    public function testGetAccountOnForInstall()
    {
        //$uuid = 'b0971de7-0387-48ea-8f29-5d3704d96a46';
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/getAccounts/forInstall", 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAssertsRegister();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success'); 
    }

    public function testGetAccountOnInstalled()
    {
        //$uuid = 'b0971de7-0387-48ea-8f29-5d3704d96a46';
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/getAccounts/Installed", 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAssertsRegister();
        $content = json_decode($this->getResponse()->getContent(), true); 
        $this->assertEquals($content['status'], 'success'); 
    }

    public function testGetAccountProperties()
    {
        $accountid = 'b6499a34-c100-4e41-bece-5822adca3844';
        $uuid='1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/account/${accountid}/appProperties", 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAssertsRegister();
        $content = json_decode($this->getResponse()->getContent(), true); 
        $this->assertEquals($content['status'], 'success'); 
    }

    public function testGetBusinessRoles()
    {
        $uuid='1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/appBusinessRoles", 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true); 
        $this->assertEquals($content['status'], 'success'); 
    }

    public function testDeployAppWithAccount()
    {
        $this->setUpTearDownHelper->setupAppDescriptor('applicationhubdrive.yml');
        $this->initAuthToken($this->adminUser);
        $path = __DIR__ . '/../../sampleapp/';
        $data = ['path' => $path];
        $this->dispatch('/app/deployapp', 'POST', $data);

        $select = "SELECT * from ox_business_role";
        $result = $this->executeQueryTest($select);
        $select = "SELECT oxr.name as roleName,oa.name as accountName,oxr.business_role_id from ox_role as oxr join ox_account as oa on oa.id = oxr.account_id where oa.name = 'HUB' and oxr.business_role_id = ".$result[0]['id'];
        $result1 = $this->executeQueryTest($select);
        $this->assertEquals(count($result1), 6); 

        $select = "SELECT oxr.name as roleName,oa.name as accountName,oxr.business_role_id from ox_role as oxr join ox_account as oa on oa.id = oxr.account_id where oa.name = 'OnTrac' and oxr.business_role_id = ".$result[1]['id'];
        $result1 = $this->executeQueryTest($select);
        $this->assertEquals(count($result1), 5); 
    }

    public function testRegisterUserWithBusinessRole()
    {
        $this->setUpTearDownHelper->setupAppDescriptor('applicationhubdrive.yml');
        $this->initAuthToken($this->adminUser);
        $path = __DIR__ . '/../../sampleapp/';
        $data = ['path' => $path];
        $this->dispatch('/app/deployapp', 'POST', $data);
        $sel = "select * from ox_app where uuid = 'a4b1f073-fc20-477f-a804-1aa206938c42'";
        $res = $this->executeQueryTest($sel);
        $data = '{"firstname":"Solomon","lastname":"Yates","address1":"Test Address","country":"India","email":"xapil@mailinator.com","type" :"BUSINESS","businessRole":"Independent Contractor","IcLastName":"Yates","autoLiability":true,"cargoInsurance":true,"city":"Obcaecati facere dol","effectiveDate":"","iCEmail":"xapil@mailinator.com","iCFirstName":"Solomon","identifier_field":"iCEmail","name":"Solomon Yates","state":"Colorado","street1IC":"Laboriosam modi cum","zip":63284,"appId":"a4b1f073-fc20-477f-a804-1aa206938c42","entity_name":"On Trac Compliance","fileId":"5849fa67-ad9a-4a23-a343-ae3ae5e99761","uuid":"5849fa67-ad9a-4a23-a343-ae3ae5e99761","accountId":null,"app_id":"'.$res[0]['id'].'","workFlowId":null,"attachments":[{"fullPath":"/app/api/v1/config/autoload/../../data/file_docs/6b88905a-fa7b-47a4-af18-a5eed6ade5c5/5849fa67-ad9a-4a23-a343-ae3ae5e99761/OnTracRSPComplianceChecklistTemplate.pdf","file":"6b88905a-fa7b-47a4-af18-a5eed6ade5c5/5849fa67-ad9a-4a23-a343-ae3ae5e99761/OnTracRSPComplianceChecklistTemplate.pdf","originalName":"OnTracRSPComplianceChecklistTemplate.pdf","type":"file/pdf"}],"version":2}';
        $data = json_decode($data, true);
        $registrationService = $this->getRegistrationService();
        $registrationService->registerAccount($data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $select = "SELECT id from ox_account where name = 'Solomon Yates'";
        $result = $this->executeQueryTest($select);

        $select = "SELECT * from ox_role where account_id = ".$result[0]['id'];
        $result = $this->executeQueryTest($select);
        $this->assertEquals(count($result), 4);

        $select = "SELECT DISTINCT oabr.*,obr.name,obr.uuid,oxr.name as roleName from ox_account_business_role as oabr join ox_account as oa on oa.id = oabr.account_id join ox_business_role as obr on obr.id = oabr.business_role_id join ox_role as oxr on obr.id = oxr.business_role_id where oa.name = 'Solomon Yates'";  
        $result = $this->executeQueryTest($select);

        $this->assertEquals($result[0]['name'], 'Independent Contractor');
        $this->assertEquals($result[0]['uuid'], 'f915fe2a-790e-4940-a576-5b1001febdd3');
        $this->assertEquals($result[0]['roleName'], 'Manage Drivers');
        $this->assertEquals(count($result), 1);
    }

    public function testRegisterUserWithBusinessRoleAndMultipleRoles()
    {
        $this->setUpTearDownHelper->setupAppDescriptor('applicationhubdrive.yml');
        $this->initAuthToken($this->adminUser);
        $path = __DIR__ . '/../../sampleapp/';
        $data = ['path' => $path];
        $this->dispatch('/app/deployapp', 'POST', $data);
        $sel = "select * from ox_app where uuid = 'a4b1f073-fc20-477f-a804-1aa206938c42'";
        $res = $this->executeQueryTest($sel);
        $data = '{"firstname":"Solomon","lastname":"Yates","address1":"Test Address","country":"India","email":"xapil@mailinator.com","type" :"BUSINESS","businessRole":"Insurance Carrier","IcLastName":"Yates","autoLiability":true,"cargoInsurance":true,"city":"Obcaecati facere dol","effectiveDate":"","iCEmail":"xapil@mailinator.com","iCFirstName":"Solomon","identifier_field":"iCEmail","name":"Solomon Yates","state":"Colorado","street1IC":"Laboriosam modi cum","zip":63284,"appId":"a4b1f073-fc20-477f-a804-1aa206938c42","entity_name":"On Trac Compliance","fileId":"5849fa67-ad9a-4a23-a343-ae3ae5e99761","uuid":"5849fa67-ad9a-4a23-a343-ae3ae5e99761","accountId":null,"app_id":"'.$res[0]['id'].'","workFlowId":null,"attachments":[{"fullPath":"/app/api/v1/config/autoload/../../data/file_docs/6b88905a-fa7b-47a4-af18-a5eed6ade5c5/5849fa67-ad9a-4a23-a343-ae3ae5e99761/OnTracRSPComplianceChecklistTemplate.pdf","file":"6b88905a-fa7b-47a4-af18-a5eed6ade5c5/5849fa67-ad9a-4a23-a343-ae3ae5e99761/OnTracRSPComplianceChecklistTemplate.pdf","originalName":"OnTracRSPComplianceChecklistTemplate.pdf","type":"file/pdf"}],"version":2}';
        $data = json_decode($data, true);
        $registrationService = $this->getRegistrationService();
        $registrationService->registerAccount($data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $select = "SELECT id from ox_account where name = 'Solomon Yates'";
        $result = $this->executeQueryTest($select);

        $select = "SELECT * from ox_role where account_id = ".$result[0]['id'];
        $result = $this->executeQueryTest($select);
        $this->assertEquals(count($result), 9);

        $select = "SELECT DISTINCT oabr.*,obr.name,obr.uuid,oxr.name as roleName from ox_account_business_role as oabr join ox_account as oa on oa.id = oabr.account_id join ox_business_role as obr on obr.id = oabr.business_role_id join ox_role as oxr on obr.id = oxr.business_role_id where oa.name = 'Solomon Yates'";  
        $result = $this->executeQueryTest($select);
        $this->assertEquals($result[0]['name'], 'Insurance Carrier');
        $this->assertEquals($result[0]['uuid'], '19d8f4c3-e79d-425b-9e23-e1fc3808cfdd');
        $this->assertEquals($result[0]['roleName'], 'Manage Executive');
        $this->assertEquals($result[1]['uuid'], '19d8f4c3-e79d-425b-9e23-e1fc3808cfdd');
        $this->assertEquals($result[1]['roleName'], 'Safety Director');
        $this->assertEquals($result[2]['uuid'], '19d8f4c3-e79d-425b-9e23-e1fc3808cfdd');
        $this->assertEquals($result[2]['roleName'], 'Manage Underwriting');
        $this->assertEquals($result[3]['uuid'], '19d8f4c3-e79d-425b-9e23-e1fc3808cfdd');
        $this->assertEquals($result[3]['roleName'], 'Account Manager');
        $this->assertEquals(count($result), 6);
    }

    public function testdeployAppWithNewBusinessRole()
    {
        $this->setUpTearDownHelper->setupAppDescriptor('applicationhubdrive.yml');
        $this->initAuthToken($this->adminUser);
        $path = __DIR__ . '/../../sampleapp/';
        $data = ['path' => $path];
        $this->dispatch('/app/deployapp', 'POST', $data);
        $sel = "select * from ox_app where uuid = 'a4b1f073-fc20-477f-a804-1aa206938c42'";
        $res = $this->executeQueryTest($sel);
        $data = '{"firstname":"Solomon","lastname":"Yates","address1":"Test Address","country":"India","email":"xapil@mailinator.com","type" :"BUSINESS","businessRole":"Contract Carrier","IcLastName":"Yates","autoLiability":true,"cargoInsurance":true,"city":"Obcaecati facere dol","effectiveDate":"","iCEmail":"xapil@mailinator.com","iCFirstName":"Solomon","identifier_field":"iCEmail","name":"Solomon Yates","state":"Colorado","street1IC":"Laboriosam modi cum","zip":63284,"appId":"a4b1f073-fc20-477f-a804-1aa206938c42","entity_name":"On Trac Compliance","fileId":"5849fa67-ad9a-4a23-a343-ae3ae5e99761","uuid":"5849fa67-ad9a-4a23-a343-ae3ae5e99761","accountId":null,"app_id":"'.$res[0]['id'].'","workFlowId":null,"attachments":[{"fullPath":"/app/api/v1/config/autoload/../../data/file_docs/6b88905a-fa7b-47a4-af18-a5eed6ade5c5/5849fa67-ad9a-4a23-a343-ae3ae5e99761/OnTracRSPComplianceChecklistTemplate.pdf","file":"6b88905a-fa7b-47a4-af18-a5eed6ade5c5/5849fa67-ad9a-4a23-a343-ae3ae5e99761/OnTracRSPComplianceChecklistTemplate.pdf","originalName":"OnTracRSPComplianceChecklistTemplate.pdf","type":"file/pdf"}],"version":2}';
        $data = json_decode($data, true);
        $registrationService = $this->getRegistrationService();
        $registrationService->registerAccount($data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $select = "SELECT id from ox_account where name = 'Solomon Yates'";
        $result = $this->executeQueryTest($select);

        $select = "SELECT * from ox_role where account_id = ".$result[0]['id'];
        $result = $this->executeQueryTest($select);
        $this->assertEquals(count($result), 8);

        $select = "SELECT DISTINCT oabr.*,obr.name,obr.uuid,oxr.name as roleName from ox_account_business_role as oabr join ox_account as oa on oa.id = oabr.account_id join ox_business_role as obr on obr.id = oabr.business_role_id join ox_role as oxr on obr.id = oxr.business_role_id where oa.name = 'Solomon Yates'";  
        $result = $this->executeQueryTest($select);
        $this->assertEquals($result[0]['name'], 'Contract Carrier');
        $this->assertEquals($result[0]['uuid'], 'f55f36c4-00d8-48f3-851d-793274658b37');
        $this->assertEquals($result[0]['roleName'], 'Manage Executives');
        $this->assertEquals($result[1]['uuid'], 'f55f36c4-00d8-48f3-851d-793274658b37');
        $this->assertEquals($result[1]['roleName'], 'Compliance Manager');
        $this->assertEquals($result[2]['uuid'], 'f55f36c4-00d8-48f3-851d-793274658b37');
        $this->assertEquals($result[2]['roleName'], 'Safety Director');
        $this->assertEquals($result[3]['uuid'], 'f55f36c4-00d8-48f3-851d-793274658b37');
        $this->assertEquals($result[3]['roleName'], 'Manage Independent Contractor');
        $this->assertEquals(count($result), 5);
    }

    public function testdeployAppWithRoleAndPrivileges()
    {
        $this->setUpTearDownHelper->setupAppDescriptor('applicationhubdrive.yml');
        $this->initAuthToken($this->adminUser);
        $path = __DIR__ . '/../../sampleapp/';
        $data = ['path' => $path];
        $this->dispatch('/app/deployapp', 'POST', $data);
        
        $select = "SELECT * from ox_role_privilege where account_id IS NULL";
        $result = $this->executeQueryTest($select);
        $this->assertEquals(count($result), 70);
    }

    public function testdeployAppWithAccountRolePrivileges()
    {
        $this->setUpTearDownHelper->setupAppDescriptor('applicationhubdrive.yml');
        $this->initAuthToken($this->adminUser);
        $path = __DIR__ . '/../../sampleapp/';
        $data = ['path' => $path];
        $this->dispatch('/app/deployapp', 'POST', $data);
        $sel = "select * from ox_app where uuid = 'a4b1f073-fc20-477f-a804-1aa206938c42'";
        $res = $this->executeQueryTest($sel);
        $data = '{"firstname":"Solomon","lastname":"Yates","address1":"Test Address","country":"India","email":"xapil@mailinator.com","type" :"BUSINESS","businessRole":"Contract Carrier","IcLastName":"Yates","autoLiability":true,"cargoInsurance":true,"city":"Obcaecati facere dol","effectiveDate":"","iCEmail":"xapil@mailinator.com","iCFirstName":"Solomon","identifier_field":"iCEmail","name":"Solomon Yates","state":"Colorado","street1IC":"Laboriosam modi cum","zip":63284,"appId":"a4b1f073-fc20-477f-a804-1aa206938c42","entity_name":"On Trac Compliance","fileId":"5849fa67-ad9a-4a23-a343-ae3ae5e99761","uuid":"5849fa67-ad9a-4a23-a343-ae3ae5e99761","accountId":null,"app_id":"'.$res[0]['id'].'","workFlowId":null,"attachments":[{"fullPath":"/app/api/v1/config/autoload/../../data/file_docs/6b88905a-fa7b-47a4-af18-a5eed6ade5c5/5849fa67-ad9a-4a23-a343-ae3ae5e99761/OnTracRSPComplianceChecklistTemplate.pdf","file":"6b88905a-fa7b-47a4-af18-a5eed6ade5c5/5849fa67-ad9a-4a23-a343-ae3ae5e99761/OnTracRSPComplianceChecklistTemplate.pdf","originalName":"OnTracRSPComplianceChecklistTemplate.pdf","type":"file/pdf"}],"version":2}';
        $data = json_decode($data, true);
        $registrationService = $this->getRegistrationService();
        $registrationService->registerAccount($data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $select = "SELECT id from ox_account where name = 'Solomon Yates'";
        $result = $this->executeQueryTest($select);
        $accountId = $result[0]['id'];
        $select = "SELECT * from ox_role where account_id = ".$accountId;
        $result = $this->executeQueryTest($select);
        $this->assertEquals(count($result), 8);

        $select = "SELECT * from ox_role_privilege as orp where orp.account_id = ".$accountId;
        $result = $this->executeQueryTest($select);
        $this->assertEquals(count($result), 59);

        $select = "SELECT * from ox_role_privilege as orp join ox_role as oxr on orp.role_id = oxr.id where orp.account_id = ".$accountId." and oxr.name = 'Manage Executives'";
        $result = $this->executeQueryTest($select);
        $this->assertEquals(count($result), 1);

        $select = "SELECT * from ox_role_privilege as orp join ox_role as oxr on orp.role_id = oxr.id where orp.account_id = ".$accountId." and oxr.name = 'Compliance Manager'";
        $result = $this->executeQueryTest($select);
        $this->assertEquals(count($result), 1);        
    }

    public function testAccountCreationRoleBusinnessRole()
    {
        $this->setUpTearDownHelper->setupAppDescriptor('applicationhubdrive1.yml');
        $this->initAuthToken($this->adminUser);
        $path = __DIR__ . '/../../sampleapp/';
        $data = ['path' => $path];
        $this->dispatch('/app/deployapp', 'POST', $data);
        $sel = "select * from ox_app where uuid = 'a4b1f073-fc20-477f-a804-1aa206938c42'";
        $res = $this->executeQueryTest($sel);
        $data = '{"firstname":"Solomon","lastname":"Yates","address1":"Test Address","country":"India","email":"xapil@mailinator.com","type" :"BUSINESS","businessRole":"Contract Carrier","IcLastName":"Yates","autoLiability":true,"cargoInsurance":true,"city":"Obcaecati facere dol","effectiveDate":"","iCEmail":"xapil@mailinator.com","iCFirstName":"Solomon","identifier_field":"iCEmail","name":"Solomon Yates","state":"Colorado","street1IC":"Laboriosam modi cum","zip":63284,"appId":"a4b1f073-fc20-477f-a804-1aa206938c42","entity_name":"On Trac Compliance","fileId":"5849fa67-ad9a-4a23-a343-ae3ae5e99761","uuid":"5849fa67-ad9a-4a23-a343-ae3ae5e99761","accountId":null,"app_id":"'.$res[0]['id'].'","workFlowId":null,"attachments":[{"fullPath":"/app/api/v1/config/autoload/../../data/file_docs/6b88905a-fa7b-47a4-af18-a5eed6ade5c5/5849fa67-ad9a-4a23-a343-ae3ae5e99761/OnTracRSPComplianceChecklistTemplate.pdf","file":"6b88905a-fa7b-47a4-af18-a5eed6ade5c5/5849fa67-ad9a-4a23-a343-ae3ae5e99761/OnTracRSPComplianceChecklistTemplate.pdf","originalName":"OnTracRSPComplianceChecklistTemplate.pdf","type":"file/pdf"}],"version":2}';
        $data = json_decode($data, true);
        $registrationService = $this->getRegistrationService();
        $registrationService->registerAccount($data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $select = "SELECT id from ox_account where name = 'Solomon Yates'";
        $result = $this->executeQueryTest($select);
        $accountId = $result[0]['id'];
        $select = "SELECT * from ox_role where account_id = ".$accountId;
        $result = $this->executeQueryTest($select);
        $this->assertEquals(count($result), 3);

        $select = "SELECT * from ox_role_privilege as orp where orp.account_id = ".$accountId;
        $result = $this->executeQueryTest($select);
        $this->assertEquals(count($result), 54);   
    }

    public function testAccountCreationRoleSameBusinnessRole()
    {
        $this->setUpTearDownHelper->setupAppDescriptor('applicationhubdrive1.yml');
        $this->initAuthToken($this->adminUser);
        $path = __DIR__ . '/../../sampleapp/';
        $data = ['path' => $path];
        $this->dispatch('/app/deployapp', 'POST', $data);
        $sel = "select * from ox_app where uuid = 'a4b1f073-fc20-477f-a804-1aa206938c42'";
        $res = $this->executeQueryTest($sel);
        $data = '{"firstname":"Solomon","lastname":"Yates","address1":"Test Address","country":"India","email":"xapil@mailinator.com","type" :"BUSINESS","businessRole":"Insurance Carrier","IcLastName":"Yates","autoLiability":true,"cargoInsurance":true,"city":"Obcaecati facere dol","effectiveDate":"","iCEmail":"xapil@mailinator.com","iCFirstName":"Solomon","identifier_field":"iCEmail","name":"Solomon Yates","state":"Colorado","street1IC":"Laboriosam modi cum","zip":63284,"appId":"a4b1f073-fc20-477f-a804-1aa206938c42","entity_name":"On Trac Compliance","fileId":"5849fa67-ad9a-4a23-a343-ae3ae5e99761","uuid":"5849fa67-ad9a-4a23-a343-ae3ae5e99761","accountId":null,"app_id":"'.$res[0]['id'].'","workFlowId":null,"attachments":[{"fullPath":"/app/api/v1/config/autoload/../../data/file_docs/6b88905a-fa7b-47a4-af18-a5eed6ade5c5/5849fa67-ad9a-4a23-a343-ae3ae5e99761/OnTracRSPComplianceChecklistTemplate.pdf","file":"6b88905a-fa7b-47a4-af18-a5eed6ade5c5/5849fa67-ad9a-4a23-a343-ae3ae5e99761/OnTracRSPComplianceChecklistTemplate.pdf","originalName":"OnTracRSPComplianceChecklistTemplate.pdf","type":"file/pdf"}],"version":2}';
        $data = json_decode($data, true);
        $registrationService = $this->getRegistrationService();
        $registrationService->registerAccount($data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $select = "SELECT id from ox_account where name = 'Solomon Yates'";
        $result = $this->executeQueryTest($select);
        $accountId = $result[0]['id'];
        $select = "SELECT * from ox_role where account_id = ".$accountId;
        $result = $this->executeQueryTest($select);
        $this->assertEquals(count($result), 4);

        $select = "SELECT * from ox_role_privilege as orp where orp.account_id = ".$accountId;
        $result = $this->executeQueryTest($select);
        $this->assertEquals(count($result), 55);   
    }
}
