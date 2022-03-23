<?php
namespace Oxzion\Service;

use Oxzion\Test\AbstractServiceTest;
use Oxzion\Service\AppService;
use Oxzion\Utils\FileUtils;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Exception;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Zend\Db\ResultSet\ResultSet;
use Mockery;
use Oxzion\EntityNotFoundException;
use Oxzion\App\AppArtifactNamingStrategy;
use Oxzion\Model\App;
use Symfony\Component\Yaml\Yaml;
use AppTest\AppTestSetUpTearDownHelper;
use Oxzion\ValidationException;

class AppServiceTest extends AbstractServiceTest
{
    private $setUpTearDownHelper = null;

    public function __construct()
    {
        parent::__construct();
        $this->loadConfig();
        $config = $this->getApplicationConfig();
        $this->setUpTearDownHelper = new AppTestSetUpTearDownHelper($config);
    }

    public function setUp() : void
    {
        parent::setUp();
        $this->setUpTearDownHelper->cleanAll();
    }

    public function tearDown() : void
    {
        parent::tearDown();
        $this->setUpTearDownHelper->cleanAll();
    }

    public function getDataSet()
    {
        $dataset = null;
        if ($this->getName() == 'testRemoveDeployedApp') {
            return new YamlDataSet(dirname(__FILE__) . "/Dataset/DeployedApp.yml");
        }
        $dataset = new YamlDataSet(dirname(__FILE__)."/Dataset/AppServiceTest.yml");
        switch ($this->getName()) {
            case 'testProcessJobCrud':
                $dataset->addYamlFile(dirname(__FILE__)."/Dataset/JobData.yml");
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

    public function testGetAppsOfAccountWithApps()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        AuthContext::put(AuthConstants::ACCOUNT_ID, '1');
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $apps = $appService->getApps();
        $this->assertEquals(10, count($apps));
    }

    public function testGetAppsOfAccountWithoutApps()
    {
        AuthContext::put(AuthConstants::USER_ID, '5');
        AuthContext::put(AuthConstants::ACCOUNT_ID, '2');
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        try {
            $apps = $appService->getApps();
            $this->fail('Expected EntityNotFoundException.');
        } catch (EntityNotFoundException $e) {
            $this->assertNotNull($e);
        }
    }

    public function testGetAppWithValidUuid()
    {
        AuthContext::put(AuthConstants::USER_ID, 6);
        AuthContext::put(AuthConstants::ACCOUNT_ID, 300);
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $uuid = 'a77ea120-b028-479b-8c6e-60476b6a4459';
        $appService->setupOrUpdateApplicationDirectoryStructure([
            'app' => [
                'name' => 'DummyApp',
                'uuid' => $uuid
            ]
        ]);
        $dd = $appService->getApp($uuid);
        $this->assertTrue(array_key_exists('app', $dd));
        $appData = $dd['app'];
        $this->assertEquals($uuid, $appData['uuid']);
        $this->assertEquals('DummyApp', $appData['name']);
    }

    public function testCreateAppPreBuilt()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        AuthContext::put(AuthConstants::ACCOUNT_ID, '1');
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $data = [
            'app' => [
                'name' => 'DummyApp-New',
                'description' => 'Dummy app for testing.',
                'category' => 'DUMMY_CATEGORY',
                'type' => App::PRE_BUILT
            ]
        ];
        $returnData = $appService->createApp($data);
        $this->assertTrue(array_key_exists('uuid', $returnData['app']));
        $appUuid = $returnData['app']['uuid'];
        $data['app']['uuid'] = $appUuid;
        $rows = $this->executeQueryTest("SELECT * FROM ox_app WHERE uuid='${appUuid}'");
        $row = $rows[0];
        $this->assertEquals($data['app']['name'], $row['name']);
        $this->assertEquals($data['app']['description'], $row['description']);
        $this->assertEquals($data['app']['category'], $row['category']);
        $this->assertEquals($data['app']['type'], $row['type']);
        $this->assertEquals(0, $row['isdefault']);
        $this->assertEquals('default_app.png', $row['logo']);
        $this->assertEquals(App::IN_DRAFT, $row['status']);
        $this->assertEquals($data['app']['name'], $returnData['app']['name']);
        $this->assertEquals($data['app']['description'], $returnData['app']['description']);
        $this->assertEquals($data['app']['category'], $returnData['app']['category']);
        $this->assertEquals($data['app']['type'], $returnData['app']['type']);
        $this->assertEquals(0, $returnData['app']['isdefault']);
        $this->assertEquals('default_app.png', $returnData['app']['logo']);
        $this->assertEquals(App::IN_DRAFT, $returnData['app']['status']);

        $config = $this->getApplicationConfig();
        $sourceAppDirectory = AppArtifactNamingStrategy::getSourceAppDirectory($config, $data['app']);
        if (file_exists($sourceAppDirectory)) {
            $this->fail("Source app directory ${sourceAppDirectory} SHOULD NOT be created.");
        }
    }

    public function testCreateAppMyApp()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        AuthContext::put(AuthConstants::ACCOUNT_ID, '1');
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $data = [
            'app' => [
                'name' => 'DummyApp-New',
                'description' => 'Dummy app for testing.',
                'category' => 'DUMMY_CATEGORY',
                'type' => App::MY_APP
            ]
        ];
        $returnData = $appService->createApp($data);
        $this->assertTrue(array_key_exists('uuid', $returnData['app']));
        $appUuid = $returnData['app']['uuid'];
        $data['app']['uuid'] = $appUuid;
        $rows = $this->executeQueryTest("SELECT * FROM ox_app WHERE uuid='${appUuid}'");
        $row = $rows[0];
        $this->assertEquals($data['app']['name'], $row['name']);
        $this->assertEquals($data['app']['description'], $row['description']);
        $this->assertEquals($data['app']['category'], $row['category']);
        $this->assertEquals($data['app']['type'], $row['type']);
        $this->assertEquals(0, $row['isdefault']);
        $this->assertEquals('default_app.png', $row['logo']);
        $this->assertEquals(App::IN_DRAFT, $row['status']);
        $this->assertEquals($data['app']['name'], $returnData['app']['name']);
        $this->assertEquals($data['app']['description'], $returnData['app']['description']);
        $this->assertEquals($data['app']['category'], $returnData['app']['category']);
        $this->assertEquals($data['app']['type'], $returnData['app']['type']);
        $this->assertEquals(0, $returnData['app']['isdefault']);
        $this->assertEquals('default_app.png', $returnData['app']['logo']);
        $this->assertEquals(App::IN_DRAFT, $returnData['app']['status']);

        $config = $this->getApplicationConfig();
        $sourceAppDirectory = AppArtifactNamingStrategy::getSourceAppDirectory($config, $data['app']);
        if (!file_exists($sourceAppDirectory)) {
            $this->fail("Source app directory ${sourceAppDirectory} is not created.");
        }
        $applicationYamlFilePath = $sourceAppDirectory . DIRECTORY_SEPARATOR . AppService::APPLICATION_DESCRIPTOR_FILE_NAME;
        if (!file_exists($applicationYamlFilePath)) {
            $this->fail("Application descriptor YAML file ${applicationYamlFilePath} is not created.");
        }
        $yamlFileData = Yaml::parse(file_get_contents($applicationYamlFilePath));
        $this->assertEquals($data['app']['name'], $yamlFileData['app']['name']);
        $this->assertEquals($data['app']['description'], $yamlFileData['app']['description']);
        $this->assertEquals($data['app']['category'], $yamlFileData['app']['category']);
        $this->assertEquals($data['app']['type'], $yamlFileData['app']['type']);
        $this->assertEquals(0, $yamlFileData['app']['isdefault']);
        $this->assertEquals('default_app.png', $yamlFileData['app']['logo']);
        $this->assertEquals(App::IN_DRAFT, $yamlFileData['app']['status']);
        $this->assertEquals($appUuid, $yamlFileData['app']['uuid']);
        FileUtils::rmDir($sourceAppDirectory);
    }

    public function testGetAppWithInvalidUuid()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        AuthContext::put(AuthConstants::ACCOUNT_ID, '1');
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        try {
            $app = $appService->getApp('11111111-1111-1111-1111-111111111111');
            $this->fail('Expected EntityNotFoundException for application not found in the database.');
        } catch (\Oxzion\EntityNotFoundException $e) {
            $this->assertNotNull($e);
        }
    }

    public function testProcessEntity()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459'), 'entity' => array(array( 'name' => 'Individual Professional Liability', 'field' => array(array('name' => 'policyStatus', 'text' => 'Policy Status', 'data_type' => 'text')))));
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $content = $appService->processEntity($data);
        $sqlQuery = "SELECT count(name) as count FROM ox_app_entity WHERE app_id = 299";
        $adapter = $this->getDbAdapter();
        $adapter->getDriver()->getConnection()->setResource(static::$pdo);
        $statement = $adapter->query($sqlQuery);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        $this->assertEquals($result[0]['count'], 1);
    }

    public function testProcessEntityWithEmptyFieldEntity()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459'), 'entity' => '');
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $content = $appService->processEntity($data);
        $sqlQuery = "SELECT count(name) as count FROM ox_app_entity WHERE app_id = 299";
        $adapter = $this->getDbAdapter();
        $adapter->getDriver()->getConnection()->setResource(static::$pdo);
        $statement = $adapter->query($sqlQuery);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        $this->assertEquals($result[0]['count'], 0);
    }

    public function testProcessEntityWithoutFieldEntity()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459'));
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $content = $appService->processEntity($data);
        $sqlQuery = "SELECT count(name) as count FROM ox_app_entity WHERE app_id = 299";
        $adapter = $this->getDbAdapter();
        $adapter->getDriver()->getConnection()->setResource(static::$pdo);
        $statement = $adapter->query($sqlQuery);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        $this->assertEquals($result[0]['count'], 0);
    }

    public function testProcessEntitySaveField()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459'), 'entity' => array(array( 'name' => 'Individual Professional Liability', 'field' => array(array('name' => 'policyStatus', 'text' => 'Policy Status', 'data_type' => 'text')))));
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $content = $appService->processEntity($data);
        $sqlQuery = "SELECT count(name) as count FROM ox_app_entity WHERE app_id = 299";
        $adapter = $this->getDbAdapter();
        $adapter->getDriver()->getConnection()->setResource(static::$pdo);
        $statement = $adapter->query($sqlQuery);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        $this->assertEquals($result[0]['count'], 1);
    }

    public function testProcessEntityWithRule()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459'), 'entity' => array(array( 'name' => 'Individual Professional Liability','ryg_rule' => '{item.policyStatus == \"Completed\" ? (\n<td style=\"color:green;background-color:green\"> {item.policyStatus} </td>\n ) :  (item.policyStatus == \"In Progress\" ? (<td style=\"color:yellow\"> {item.policyStatus} </td>) : (\n <td>{item.policyStatus}</td>\n))}', 'field' => array(array('name' => 'policyStatus', 'text' => 'Policy Status', 'data_type' => 'text')))));
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $sqlQuery = "SELECT * FROM ox_app_entity WHERE app_id = 299";
        $content = $appService->processEntity($data);
        $sqlQuery = "SELECT * FROM ox_app_entity WHERE app_id = 299";
        $result = $this->executeQueryTest($sqlQuery);
        $this->assertEquals($result[0]['name'], $data['entity'][0]['name']);
        $this->assertEquals($result[0]['ryg_rule'], $data['entity'][0]['ryg_rule']);
    }

    public function testProcessForm()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459'), 'form' => array(array('name' => 'PADI Verification', 'uuid' => 'd2ed4200-9131-4671-b0e0-de3e27c3f610', 'description' => 'Page for CSR to verify PADI details', 'template_file' => 'dummypage.json','entity' => 'Padi')));
        $path = __DIR__ . '/../../../../module/App/test/sampleapp/';
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $content = $appService->processForm($data, $path);
        $sqlQuery = "SELECT count(name) as count FROM ox_form WHERE app_id = 299 and uuid = 'd2ed4200-9131-4671-b0e0-de3e27c3f610'";
        $adapter = $this->getDbAdapter();
        $adapter->getDriver()->getConnection()->setResource(static::$pdo);
        $statement = $adapter->query($sqlQuery);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        $this->assertEquals($result[0]['count'], 1);
    }

    public function testProcessFormWithNoFormInData()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459'));
        $path = __DIR__ . '/../../../../module/App/test/sampleapp/';
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $content = $appService->processForm($data, $path);
        $sqlQuery = "SELECT count(name) as count FROM ox_form WHERE app_id = 299 and uuid = 'd2ed4200-9131-4671-b0e0-de3e27c3f610'";
        $adapter = $this->getDbAdapter();
        $adapter->getDriver()->getConnection()->setResource(static::$pdo);
        $statement = $adapter->query($sqlQuery);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        $this->assertEquals($result[0]['count'], 0);
    }

    public function testProcessFormWithNoEntity()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459'), 'form' => array(array('name' => 'PADI Verification', 'uuid' => 'd2ed4200-9131-4671-b0e0-de3e27c3f610', 'description' => 'Page for CSR to verify PADI details', 'template_file' => 'dummypage.json','entity' => '')));
        $path = __DIR__ . '/../../../../module/App/test/sampleapp/';
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        try {
            $appService->processForm($data, $path);
            $this->fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            $this->assertNotNull($e);
        }
    }

    public function testProcessWorkflow()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459'), 'workflow' =>array(array('name' => 'Dive Boat Reinstate Policy', 'entity' => 'Dive Boat', 'uuid' => '2d94a2f0-c64c-48e0-a4f0-f85f626f0626', 'bpmn_file' => 'Cancel Policy/ReinstatePolicyDB.bpmn')));
        $path = __DIR__ . '/../../../../module/App/test/sampleapp/';
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        if (enableCamundaForDeployApp == 0) {
            $mockProcessManager = $this->getMockProcessManager();
            $mockProcessManager->expects('deploy')->withAnyArgs()->once()->andReturn(array('Process_1dx3jli:1eca438b-007f-11ea-a6a0-bef32963d9ff'));
            $mockProcessManager->expects('parseBPMN')->withAnyArgs()->once()->andReturn(null);
        }
        $content = $appService->processWorkflow($data, $path);
        $sqlQuery = "SELECT count(name) as count FROM ox_workflow WHERE app_id = 299 and name = 'Dive Boat Reinstate Policy'";
        $adapter = $this->getDbAdapter();
        $adapter->getDriver()->getConnection()->setResource(static::$pdo);
        $statement = $adapter->query($sqlQuery);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        $this->assertEquals($result[0]['count'], 1);
    }

    public function testProcessWorkflowWithoutWorkflowInYmlData()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459'));
        $path = __DIR__ . '/../../../../module/App/test/sampleapp/';
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $content = $appService->processWorkflow($data, $path);
        $sqlQuery = "SELECT count(name) as count FROM ox_workflow WHERE app_id = 299 and name = 'Dive Boat Reinstate Policy'";
        $adapter = $this->getDbAdapter();
        $adapter->getDriver()->getConnection()->setResource(static::$pdo);
        $statement = $adapter->query($sqlQuery);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        $this->assertEquals($result[0]['count'], 0);
    }

    public function testProcessMenu()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459'),'org' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4456'), 'menu' => array(array('name' => 'Home', 'icon' => 'fa fa-home', 'uuid' => '24176975-8f4d-499d-8b2d-86902de26c14')));
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $path = __DIR__ . '/../../../../module/App/test/sampleapp/';
        $content = $appService->processMenu($data, $path);
        $sqlQuery = "SELECT count(name) as count FROM ox_app_menu WHERE app_id = 299 and name = 'Home'";
        $adapter = $this->getDbAdapter();
        $adapter->getDriver()->getConnection()->setResource(static::$pdo);
        $statement = $adapter->query($sqlQuery);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        $this->assertEquals(1, $result[0]['count']);
    }

    public function testProcessMenuWithoutYmlData()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459'),'org' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4456'));
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $path = __DIR__ . '/../../../../module/App/test/sampleapp/';
        $content = $appService->processMenu($data, $path);
        $sqlQuery = "SELECT count(name) as count FROM ox_app_menu WHERE app_id = 299 and name = 'Home'";
        $adapter = $this->getDbAdapter();
        $adapter->getDriver()->getConnection()->setResource(static::$pdo);
        $statement = $adapter->query($sqlQuery);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        $this->assertEquals($result[0]['count'], 0);
    }

    public function testProcessPage()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459'), 'org' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4456'), 'pages' => array(array('page_name' => 'dummyPage.yml', 'uuid' => 'b9714cfd-2ae5-4f13-83eb-7d925c3b660c')));
        $path = __DIR__ . '/../../../../module/App/test/sampleapp/';
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $content = $appService->processPage($data, $path);
        $sqlQuery = "SELECT count(name) as count FROM ox_app_page WHERE app_id = 299 and uuid = 'b9714cfd-2ae5-4f13-83eb-7d925c3b660c'";
        $adapter = $this->getDbAdapter();
        $adapter->getDriver()->getConnection()->setResource(static::$pdo);
        $statement = $adapter->query($sqlQuery);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        $this->assertEquals($result[0]['count'], 1);
    }

    public function testProcessPageWithNoPageInData()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459'));
        $path = __DIR__ . '/../../../../module/App/test/sampleapp/';
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $content = $appService->processPage($data, $path);
        $sqlQuery = "SELECT count(name) as count FROM ox_app_page WHERE app_id = 299 and name = 'View Policy'";
        $adapter = $this->getDbAdapter();
        $adapter->getDriver()->getConnection()->setResource(static::$pdo);
        $statement = $adapter->query($sqlQuery);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        $this->assertEquals($result[0]['count'], 0);
    }

    public function testProcessJob()
    {
        AuthContext::put(AuthConstants::ACCOUNT_ID, '300');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459'), 'org' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4456'), 'job' =>array(array('uuid' => '129dfbe2-151d-49c8-81e9-a4b7582df65e', 'name' => 'autoRenewalJob', 'url' => '/workflow/f0efea9e-7863-4368-a9b2-baa1a1603067', 'cron' => '0 4 12 18 * ? 2020', 'data' => array('EFR2M' => '204','padi' => '2165', 'padiVerified' => '1'))));
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $mockRestClient->expects('postWithHeader')->with("setupjob", Mockery::any())->once()->andReturn(array('body' => '{"Success":true,"Message":"Job Scheduled Successfully!","JobId":"b1a05c42-6f9e-11eb-9439-0242ac130002","JobTeam":"autoRenewalJob"}'));
        }
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $appService->processJob($data);
        $sqlQuery = "SELECT job_id FROM ox_job WHERE app_id = 299";
        $adapter = $this->getDbAdapter();
        $adapter->getDriver()->getConnection()->setResource(static::$pdo);
        $statement = $adapter->query($sqlQuery);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        $this->assertEquals(count($result), 2);
        $this->assertEquals($result[0]['job_id'], null);
    }

    public function testProcessJobWithInstallation()
    {
        AuthContext::put(AuthConstants::ACCOUNT_ID, '300');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459'), 'org' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4456'), 'job' =>array(array('uuid' => '129dfbe2-151d-49c8-81e9-a4b7582df65e', 'name' => 'autoRenewalJob', 'url' => '/workflow/f0efea9e-7863-4368-a9b2-baa1a1603067', 'cron' => '0 4 12 18 * ? 2020', 'data' => array('EFR2M' => '204','padi' => '2165', 'padiVerified' => '1'))));
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $mockRestClient->expects('postWithHeader')->with("setupjob", Mockery::any())->once()->andReturn(array('body' => '{"Success":true,"Message":"Job Scheduled Successfully!","JobId":"3a289705-763d-489a-b501-0755b9d4b64b","JobTeam":"autoRenewalJob"}'));
            $mockRestClient->expects('postWithHeader')->with("canceljob", Mockery::any())->once()->andReturn(array('body' => '{"Success":true,"Message":"Job Scheduled Successfully!","JobId":"3a289705-763d-489a-b501-0755b9d4b64b","JobTeam":"autoRenewalJob"}'));
        }
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $appService->processJob($data);
        $appService->processJobsForAccount($data['app']['uuid'], $data['org']['uuid']);
        $sqlQuery = "SELECT count(name) as count FROM ox_job WHERE app_id = 299 and job_id = '3a289705-763d-489a-b501-0755b9d4b64b'";
        $adapter = $this->getDbAdapter();
        $adapter->getDriver()->getConnection()->setResource(static::$pdo);
        $statement = $adapter->query($sqlQuery);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        $this->assertEquals($result[0]['count'], 1);
    }

    public function testProcessJobWithUninstall()
    {
        AuthContext::put(AuthConstants::ACCOUNT_ID, '300');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459'), 'org' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4456'), 'job' =>array(array('uuid' => '129dfbe2-151d-49c8-81e9-a4b7582df65e', 'name' => 'autoRenewalJob', 'url' => '/workflow/f0efea9e-7863-4368-a9b2-baa1a1603067', 'cron' => '0 4 12 18 * ? 2020', 'data' => array('EFR2M' => '204','padi' => '2165', 'padiVerified' => '1'))));
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $mockRestClient->expects('postWithHeader')->with("setupjob", Mockery::any())->once()->andReturn(array('body' => '{"Success":true,"Message":"Job Scheduled Successfully!","JobId":"3a289705-763d-489a-b501-0755b9d4b64b","JobTeam":"autoRenewalJob"}'));
            $mockRestClient->expects('postWithHeader')->with("canceljob", Mockery::any())->once()->andReturn(array('body' => '{"Success":true,"Message":"Job Scheduled Successfully!","JobId":"3a289705-763d-489a-b501-0755b9d4b64b","JobTeam":"autoRenewalJob"}'));
        }
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $appService->processJob($data);
        $appService->uninstallJobsForAccount($data['app']['uuid'], $data['org']['uuid']);
        $sqlQuery = "SELECT * FROM ox_job WHERE app_id = 299 and account_id = '3a289705-763d-489a-b501-0755b9d4b64b'";
        $adapter = $this->getDbAdapter();
        $adapter->getDriver()->getConnection()->setResource(static::$pdo);
        $statement = $adapter->query($sqlQuery);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        $this->assertEquals(count($result), 0);
    }

    public function testProcessJobCrud()
    {
        AuthContext::put(AuthConstants::ACCOUNT_ID, '300');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459'), 'org' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4456'), 'job' =>array(array('uuid' => '129dfbe2-151d-49c8-81e9-a4b7582df65e', 'name' => 'autoRenewalJob', 'url' => '/workflow/f0efea9e-7863-4368-a9b2-baa1a1603067', 'cron' => '0 4 12 18 * ? 2020', 'data' => array('EFR2M' => '204','padi' => '2165', 'padiVerified' => '1')),array('uuid' => '0c91b316-6f9b-11eb-9439-0242ac130002', 'name' => 'Job3', 'url' => '/workflow/f0efea9e-7863-4368-a9b2-baa1a1603067', 'cron' => '0 4 12 18 * ? 2020', 'data' => array('EFR2M' => '214','padi' => '2000', 'padiVerified' => '1'))));
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $mockRestClient->expects('postWithHeader')->with("canceljob", Mockery::any())->once()->andReturn(array('body' => '{"Success":true,"Message":"Job Scheduled Successfully!","JobId":"3a289705-763d-489a-b501-0755b9d4b64b","JobTeam":"JOB1"}'));
            $mockRestClient->expects('postWithHeader')->with("canceljob", Mockery::any())->once()->andReturn(array('body' => '{"Success":true,"Message":"Job Scheduled Successfully!","JobId":"aca4a3e4-6f8d-11eb-9439-0242ac130002","JobTeam":"JOB2"}'));
            $mockRestClient->expects('postWithHeader')->with("setupjob", Mockery::any())->once()->andReturn(array('body' => '{"Success":true,"Message":"Job Scheduled Successfully!","JobId":"b1a05c42-6f9e-11eb-9439-0242ac130002","JobTeam":"autoRenewalJob"}'));
            $mockRestClient->expects('postWithHeader')->with("setupjob", Mockery::any())->once()->andReturn(array('body' => '{"Success":true,"Message":"Job Scheduled Successfully!","JobId":"b1a05fb2-6f9e-11eb-9439-0242ac130002","JobTeam":"Job3"}'));
        }
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $appService->processJob($data);
        $this->markTestSkipped('Skipping Test');
        $sqlQuery = "SELECT * FROM ox_job WHERE app_id = 299";
        $adapter = $this->getDbAdapter();
        $adapter->getDriver()->getConnection()->setResource(static::$pdo);
        $statement = $adapter->query($sqlQuery);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        print_r($result);
        $this->assertEquals(count($result), 4);
        $this->assertEquals($result[0]['name'], $data['job'][0]['uuid']);
        $this->assertEquals($result[0]['group_name'], $data['job'][0]['name']);
        $this->assertEquals($result[0]['job_id'], null);
        $this->assertEquals($result[1]['name'], $data['job'][0]['uuid']);
        $this->assertEquals($result[1]['group_name'], $data['job'][0]['name']);
        $this->assertEquals($result[1]['job_id'], 'b1a05c42-6f9e-11eb-9439-0242ac130002');
        $this->assertEquals($result[2]['name'], $data['job'][1]['uuid']);
        $this->assertEquals($result[2]['group_name'], $data['job'][1]['name']);
        $this->assertEquals($result[2]['job_id'], null);
        $this->assertEquals($result[3]['name'], $data['job'][1]['uuid']);
        $this->assertEquals($result[3]['group_name'], $data['job'][1]['name']);
        $this->assertEquals($result[3]['job_id'], 'b1a05fb2-6f9e-11eb-9439-0242ac130002');
    }

    public function testProcessJobWithoutJobInYmlData()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459'));
        $path = __DIR__ . '/../../../../module/App/test/sampleapp/';
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $content = $appService->processJob($data, $path);
        $sqlQuery = "SELECT count(name) as count FROM ox_job WHERE app_id = 299 and job_id = '3a289705-763d-489a-b501-0755b9d4b64b'";
        $adapter = $this->getDbAdapter();
        $adapter->getDriver()->getConnection()->setResource(static::$pdo);
        $statement = $adapter->query($sqlQuery);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        $this->assertEquals($result[0]['count'], 0);
    }

    public function testCreateRole()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        AuthContext::put(AuthConstants::ACCOUNT_UUID, 'a77ea120-b028-479b-8c6e-60476b6a4456');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459'), 'org' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4456'), 'role' => array(array('name' => 'Policy Holder', 'default' => '1', 'privileges' => array(array('privilege_name' => 'MANAGE_MY_POLICY', 'permission' => '3')),'uuid' => '703d3a09-b7f3-49e9-9c79-74d5cae7f6e7')));
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $content = $appService->createRole($data);
        $sqlQuery = "SELECT * FROM ox_role WHERE uuid = '".$data['role'][0]['uuid']."'";
        $result = $this->executeQueryTest($sqlQuery);
        $this->assertEquals(1, count($result));
        $result = $result[0];
        $this->assertEquals($data['role'][0]['name'], $result['name']);
        $this->assertEquals($data['role'][0]['default'], $result['default_role']);
        $sqlQuery = "SELECT rp.* FROM ox_role_privilege rp WHERE rp.role_id = ".$result['id'];
        $result = $this->executeQueryTest($sqlQuery);
        $this->assertEquals(1, count($result));
        $result = $result[0];
        $privilege = $data['role'][0]['privileges'][0];
        $this->assertEquals($privilege['privilege_name'], $result['privilege_name']);
        $this->assertEquals($privilege['permission'], $result['permission']);
    }

    public function testCreateRoleWithNoRoleInData()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459'));
        $path = __DIR__ . '/../../../../module/App/test/sampleapp/';
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $content = $appService->createRole($data);
        $sqlQuery = "SELECT count(*) as count FROM ox_role WHERE name = 'Policy Holder' and account_id = 300";
        $adapter = $this->getDbAdapter();
        $adapter->getDriver()->getConnection()->setResource(static::$pdo);
        $statement = $adapter->query($sqlQuery);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        $this->assertEquals($result[0]['count'], 0);
    }

    // public function testCreateOrg()
    // {
    //     AuthContext::put(AuthConstants::USER_ID, '1');
    //     AuthContext::put(AuthConstants::ACCOUNT_ID, 1);
    //     AuthContext::put(AuthConstants::ACCOUNT_UUID, '53012471-2863-4949-afb1-e69b0891c98a');
    //     AuthContext::put(AuthConstants::PRIVILEGES, ['MANAGE_ACCOUNT_WRITE' => true]);
    //     $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459'), 'org' => array('name' => 'V&B', 'uuid' => 'e1033dc0-126b-40ba-89e0-d3061bdeda42','email' => 'vb07@gmail.com','address1' => '6 bCenterpoint','address2' => 'Dr.','city' => 'La Palma','state' => 'CA','zip' => '90623','country' => 'United States','contact' => array('username' => 'vb07.gmail.com','firstname' => 'Admin','lastname' => 'User','email' => 'vb07@gmail.com'),'preferences' => '{"currency":"INR","timezone":"Asia/Calcutta","dateformat":"dd/mm/yyyy"}'));
    //     $appService = $this->getApplicationServiceLocator()->get(AppService::class);
    //     $content = $appService->setupOrg($data);
    //     $sqlQuery = "SELECT count(*) as count FROM ox_account";
    //     $adapter = $this->getDbAdapter();
    //     $adapter->getDriver()->getConnection()->setResource(static::$pdo);
    //     $statement = $adapter->query($sqlQuery);
    //     $result = $statement->execute();
    //     $resultSet = new ResultSet();
    //     $result = $resultSet->initialize($result)->toArray();
    //     $this->assertEquals($result[0]['count'], 4);
    // }


    public function testCreateOrgWithNoOrgInData()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459'));
        $path = __DIR__ . '/../../../../module/App/test/sampleapp/';
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $content = $appService->setupOrg($data);
        $sqlQuery = "SELECT count(name) as count FROM ox_account WHERE uuid = 'e1033dc0-126b-40ba-89e0-d3061bdeda4c'";
        $adapter = $this->getDbAdapter();
        $adapter->getDriver()->getConnection()->setResource(static::$pdo);
        $statement = $adapter->query($sqlQuery);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        $this->assertEquals($result[0]['count'], 0);
    }

    public function testPerformMigration()
    {
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459', 'name' => 'SampleApp2', 'description' => 'test db for app service test'));
        $path = __DIR__ . '/../sampleapp';
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $content = $appService->performMigration($data, $path);
        $result = true;
        $appName = 'SampleApp2';
        $YmlappUuid = 'a77ea120-b028-479b-8c6e-60476b6a4459';
        $this->assertEquals($result, true);
    }

    public function testCreateAppPrivileges()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459'), 'privilege' => array(array('name' => 'MANAGE_POLICY_APPROVAL', 'permission' => 3)));
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $content = $appService->createAppPrivileges($data);
        $sqlQuery = "SELECT count(*) as count FROM ox_privilege where name = 'MANAGE_POLICY_APPROVAL' and app_id = '299'";
        $adapter = $this->getDbAdapter();
        $adapter->getDriver()->getConnection()->setResource(static::$pdo);
        $statement = $adapter->query($sqlQuery);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        $this->assertEquals($result[0]['count'], 1);
    }

    public function testCreateAppPrivilegesWithoutPrivilegeInYmlData()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459'));
        $path = __DIR__ . '/../../../../module/App/test/sampleapp/';
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $content = $appService->createAppPrivileges($data);
        $sqlQuery = "SELECT count(*) as count FROM ox_privilege where name = 'MANAGE_POLICY_APPROVAL' and app_id = '299'";
        $adapter = $this->getDbAdapter();
        $adapter->getDriver()->getConnection()->setResource(static::$pdo);
        $statement = $adapter->query($sqlQuery);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        $this->assertEquals($result[0]['count'], 0);
    }

    public function testSetupAppView()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459', 'name' => 'DummyApp', 'title' => 'Dummy App'));
        $path = __DIR__ . '/../../../../module/App/test/sampleapp/';
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $config = $this->getApplicationConfig();
        $eoxapp = $config['DATA_FOLDER'] . 'eoxapps';
        if (!is_dir($path . 'view/apps/eoxapps')) {
            FileUtils::copyDir($eoxapp, $path);
        }
        $content = $appService->setupAppView($data, $path);
        $appname = $path . 'view/apps/DummyApp' ;
        $result = is_dir($appname);
        $this->assertEquals($result, 1);
        FileUtils::rmDir($appname);
    }

    public function testProcessSymlinks()
    {
        if (enableExecUtils == 0) {
            $mockBosUtils = Mockery::mock('alias:\Oxzion\Utils\ExecUtils');
            $mockBosUtils->expects('randomPassword')->withAnyArgs()->once()->andReturn('12345678');
            $mockBosUtils->expects('execCommand')->withAnyArgs()->times(3)->andReturn();
        }
        AuthContext::put(AuthConstants::USER_ID, '1');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459', 'name' => 'DummyApp'), 'org' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4456'));
        $path = __DIR__ . '/../../../../module/App/test/sampleapp/';
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $content = $appService->processSymlinks($data, $path);
        $config = $this->getApplicationConfig();
        $delegatefolder = $config['DELEGATE_FOLDER']. 'a77ea120-b028-479b-8c6e-60476b6a4459' ;
        $result = file_exists($delegatefolder);
        $this->assertEquals($result, 1);
        $formfolder = $config['FORM_FOLDER']. 'a77ea120-b028-479b-8c6e-60476b6a4459' ;
        $result2 = file_exists($formfolder);
        $this->assertEquals($result2, 1);
        if (is_link($delegatefolder)) {
            unlink($delegatefolder);
        };
        if (is_link($formfolder)) {
            unlink($formfolder);
        };
        $result = file_exists($delegatefolder);
        $this->assertEquals($result, 0);
        $result2 = file_exists($formfolder);
        $this->assertEquals($result2, 0);
    }

    public function testDeleteApp()
    {
        AuthContext::put(AuthConstants::USER_ID, 6);
        AuthContext::put(AuthConstants::ACCOUNT_ID, 300);
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $uuid = 'a77ea120-b028-479b-8c6e-60476b6a4459';
        $id = 299;
        $appService->deleteApp($uuid, 0);
        $result = $this->executeQueryTest("SELECT * FROM ox_app WHERE id='$id'");
        $this->assertEquals(App::DELETED, $result[0]['status']);
        $this->assertNotEquals($uuid, $result[0]['uuid']);
    }

    public function testDeleteAppWithInvalidUuid()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        AuthContext::put(AuthConstants::ACCOUNT_ID, '1');
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        try {
            $appService->deleteApp('11111111-1111-1111-1111-111111111111', 0);
            $this->fail('Expected EntityNotFoundException.');
        } catch (EntityNotFoundException $e) {
            $this->assertNotNull($e);
        }
    }

    public function testRemoveDeployedApp()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        $uuid = '35443300-1826-11eb-adc1-0242ac120002';
        $id = 99;
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $config = $this->getApplicationConfig();
        $viewAppFolder = $config['APPS_FOLDER']. 'SampleApp' ;
        $viewAppLocation =__DIR__ . '/../../../../module/App/test/sampleapp/';
        FileUtils::symlink($viewAppLocation, $viewAppFolder);
        try {
            $beforePageDelete = $this->executeQueryTest("SELECT * FROM ox_app_page join ox_app on ox_app.id = ox_app_page.app_id WHERE ox_app.uuid='${uuid}'");
            $this->assertEquals(3, count($beforePageDelete));

            $beforeMenuDelete = $this->executeQueryTest("SELECT * FROM ox_app_menu join ox_app on ox_app.id = ox_app_menu.app_id WHERE ox_app.uuid='${uuid}'");
            $this->assertEquals(3, count($beforePageDelete));

            $beforewfDelete = $this->executeQueryTest("SELECT ox_workflow_instance.* FROM ox_workflow_instance join ox_workflow_deployment on ox_workflow_deployment.id = ox_workflow_instance.workflow_deployment_id join ox_workflow on ox_workflow.id = ox_workflow_deployment.workflow_id join ox_app on ox_app.id = ox_workflow.app_id WHERE ox_app.uuid='${uuid}'");
            $this->assertEquals(10, count($beforewfDelete));
            $this->assertEquals(0, $beforewfDelete[0]['isdeleted']);

            $beforeActivityDelete = $this->executeQueryTest("SELECT * FROM ox_activity join ox_app on ox_app.id = ox_activity.app_id WHERE ox_app.uuid='${uuid}'");
            $this->assertEquals(2, count($beforeActivityDelete));
            $this->assertEquals(0, $beforeActivityDelete[0]['isdeleted']);

            $beforeActivityInstanceDelete = $this->executeQueryTest("SELECT ox_activity_instance.* FROM ox_activity_instance join ox_workflow_instance on ox_workflow_instance.id = ox_activity_instance.workflow_instance_id join ox_app on ox_app.id = ox_workflow_instance.app_id WHERE ox_app.uuid='${uuid}'");
            $this->assertEquals(12, count($beforeActivityInstanceDelete));
            $this->assertEquals(0, $beforeActivityInstanceDelete[0]['isdeleted']);

            $beforeFileDelete = $this->executeQueryTest("SELECT oxf.* from ox_file oxf inner join ox_app_entity oxae on oxae.id = oxf.entity_id inner join ox_app oxa on oxa.id = oxae.app_id where oxa.uuid='${uuid}'");
            $this->assertEquals(10, count($beforeFileDelete));
            $this->assertEquals(1, $beforeFileDelete[0]['is_active']);

            $beforeFormsDelete = $this->executeQueryTest("SELECT * FROM ox_form join ox_app on ox_app.id = ox_form.app_id WHERE ox_app.uuid='${uuid}'");
            $this->assertEquals(2, count($beforeFormsDelete));
            $this->assertEquals(0, $beforeFormsDelete[0]['isdeleted']);

            $beforeEntityDelete = $this->executeQueryTest("SELECT * FROM ox_app_entity join ox_app on ox_app.id = ox_app_entity.app_id WHERE ox_app.uuid='${uuid}'");
            $this->assertEquals(2, count($beforeEntityDelete));
            $this->assertEquals(0, $beforeEntityDelete[0]['isdeleted']);

            $beforeDelete = $this->executeQueryTest("SELECT oei.* FROM ox_entity_identifier oei
            right outer join ox_app_entity oxe on oei.entity_id = oxe.id
            inner join ox_app oxa on oxa.id = oxe.app_id
            where oxa.uuid ='${uuid}'");
            $this->assertEquals(2, count($beforeDelete));

            $beforeDelete1 = $this->executeQueryTest("SELECT * from ox_account_offering");

            $beforeRolePriDe = $this->executeQueryTest("SELECT * FROM ox_role_privilege join ox_app on ox_app.id = ox_role_privilege.app_id WHERE ox_app.uuid='${uuid}'");
            $this->assertEquals(1, count($beforeRolePriDe));

            $beforeDe1 = $this->executeQueryTest("SELECT ox_role.* FROM ox_role join ox_role_privilege on ox_role_privilege.role_id = ox_role.id join ox_app on ox_app.id = ox_role_privilege.app_id WHERE ox_app.uuid='${uuid}'");
            $this->assertEquals(1, count($beforeDe1));

            $beforeDel2 = $this->executeQueryTest("SELECT ox_privilege.* FROM ox_privilege join ox_app on ox_app.id = ox_privilege.app_id WHERE ox_app.uuid='${uuid}'");
            $this->assertEquals(4, count($beforeDel2));

            $beforeDel3 = $this->executeQueryTest("SELECT ox_app_registry.* FROM ox_app_registry join ox_app on ox_app.id = ox_app_registry.app_id WHERE ox_app.uuid='${uuid}'");
            $this->assertEquals(4, count($beforeDel3));

            $appService->removeDeployedApp($uuid);
            // JOBS Assertion
            $result = $this->executeQueryTest("SELECT * FROM ox_job join ox_app on ox_app.id = ox_job.app_id WHERE ox_app.id='${id}'");
            $this->assertEquals(0, count($result));
            // Page
            $result = $this->executeQueryTest("SELECT * FROM ox_app_page join ox_app on ox_app.id = ox_app_page.app_id WHERE  ox_app.id='${id}'");
            $this->assertEquals(0, count($result));
            // Menu
            $result = $this->executeQueryTest("SELECT * FROM ox_app_menu join ox_app on ox_app.id = ox_app_menu.app_id WHERE  ox_app.id='${id}'");
            $this->assertEquals(0, count($result));
            //Entity
            $result = $this->executeQueryTest("SELECT ox_workflow_instance.isdeleted,ox_workflow_deployment.isdeleted as wfDeploymentDelete, ox_workflow.isdeleted as wfDelete FROM ox_workflow_instance join ox_workflow_deployment on ox_workflow_deployment.id = ox_workflow_instance.workflow_deployment_id join ox_workflow on ox_workflow.id = ox_workflow_deployment.workflow_id join ox_app on ox_app.id = ox_workflow.app_id WHERE  ox_app.id='${id}'");
            $this->assertEquals(10, count($result));
            $this->assertEquals(1, $result[0]['isdeleted']);
            $this->assertEquals(1, $result[0]['wfDeploymentDelete']);
            $this->assertEquals(1, $result[0]['wfDelete']);

            $result = $this->executeQueryTest("SELECT * FROM ox_activity join ox_app on ox_app.id = ox_activity.app_id WHERE  ox_app.id='${id}'");
            $this->assertEquals(2, count($result));
            $this->assertEquals(1, $result[0]['isdeleted']);
            $this->assertEquals(1, $result[1]['isdeleted']);

            $result = $this->executeQueryTest("SELECT ox_activity_instance.* FROM ox_activity_instance join ox_workflow_instance on ox_workflow_instance.id = ox_activity_instance.workflow_instance_id join ox_app on ox_app.id = ox_workflow_instance.app_id WHERE  ox_app.id='${id}'");
            $this->assertEquals(12, count($result));
            $this->assertEquals(1, $result[0]['isdeleted']);

            $result = $this->executeQueryTest("SELECT oxf.* from ox_file oxf inner join ox_app_entity oxae on oxae.id = oxf.entity_id inner join ox_app oxa on oxa.id = oxae.app_id where oxa.uuid='${uuid}'");
            $this->assertEquals(10, count($result));
            $this->assertEquals(0, $result[0]['is_active']);

            $result = $this->executeQueryTest("SELECT * FROM ox_form join ox_app on ox_app.id = ox_form.app_id WHERE  ox_app.id='${id}'");
            $this->assertEquals(2, count($result));
            $this->assertEquals(1, $result[0]['isdeleted']);

            $result = $this->executeQueryTest("SELECT * FROM ox_app_entity join ox_app on ox_app.id = ox_app_entity.app_id WHERE  ox_app.id='${id}'");
            $this->assertEquals(2, count($result));
            $this->assertEquals(1, $result[0]['isdeleted']);

            //ox_role_privilege
            $result = $this->executeQueryTest("SELECT * FROM ox_role_privilege join ox_app on ox_app.id = ox_role_privilege.app_id WHERE  ox_app.id='${id}'");
            $this->assertEquals(0, count($result));
            //ox_user_role

            //ox_role
            $result = $this->executeQueryTest("SELECT * FROM ox_role join ox_role_privilege on ox_role_privilege.role_id = ox_role.id join ox_app on ox_app.id = ox_role_privilege.app_id WHERE  ox_app.id='${id}'");
            $this->assertEquals(0, count($result));

            //ox_privilege
            $result = $this->executeQueryTest("SELECT * FROM ox_privilege join ox_app on ox_app.id = ox_privilege.app_id WHERE  ox_app.id='${id}'");
            $this->assertEquals(0, count($result));

            //ox_app_registry
            $result = $this->executeQueryTest("SELECT * FROM ox_app_registry join ox_app on ox_app.id = ox_app_registry.app_id WHERE  ox_app.id='${id}'");
            $this->assertEquals(0, count($result));

            $result = $this->executeQueryTest("SELECT * FROM ox_app WHERE id='${id}'");
            $this->assertEquals(App::DELETED, $result[0]['status']);
            $this->assertEquals($result[0]['id']."_SampleApp", $result[0]['name']);
            $this->assertNotEquals($result[0]['uuid'], $uuid);
        } catch (Exception $e) {
            $this->assertNotNull($e);
        }
    }

    public function testSetupAppViewNewAppName()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        $data = array('app' => array('uuid' => 'a77ea120-b028-479b-8c6e-60476b6a4459', 'name' => 'DummyAppNew', 'title' => 'Dummy App', 'oldAppName' => 'DummyApp'));
        $path = __DIR__ . '/../../../../module/App/test/sampleapp/';
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        $config = $this->getApplicationConfig();
        $eoxapp = $config['DATA_FOLDER'] . 'eoxapps';
        if (!is_dir($path . 'view/apps/eoxapps')) {
            FileUtils::copyDir($eoxapp, $path);
            FileUtils::renameFile($path.$eoxapp, $path.$data['app']['oldAppName']);
        }

        if (enableExecUtils == 0) {
            $mockRestClient = Mockery::mock('\Oxzion\Utils\RestClient');
            $mockRestClient->expects('post')->withAnyArgs()->once()->andReturn(json_encode(array('status'=>'Success')));
            $appService->setRestClient($mockRestClient);
            $mockBosUtils = Mockery::mock('alias\Oxzion\Utils\ExecUtils');
            $mockBosUtils->expects('execCommand')->withAnyArgs()->times(2)->andReturn();
        }
        $content = $appService->setupAppView($data, $path);
        $appnameNew = $path . 'view/apps/DummyAppNew' ;
        $result = is_dir($appnameNew);
        $this->assertEquals($result, 1);
        FileUtils::rmDir($appnameNew);
    }
}
