<?php
namespace Workflow;

use Mockery;
use Oxzion\Test\ControllerTest;
use Oxzion\Workflow\WorkflowFactory;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Workflow\Controller\ActivityInstanceController;
use Workflow\Controller\WorkflowInstanceController;
use Zend\Db\Adapter\AdapterInterface;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;

class WorkflowInstanceControllerTest extends ControllerTest
{
    private $processId;
    public function setUp(): void
    {
        $this->loadConfig();
        parent::setUp();
        if (enableCamunda == 1) {
            $workflowFactory = WorkflowFactory::getInstance();
            $processManager = $workflowFactory->getProcessManager();
            $data = $processManager->deploy('TestProcess1', array(__DIR__ . "/../Dataset/ScriptTaskTest.bpmn"));
            $dbAdapter = $this->getApplicationServiceLocator()->get(AdapterInterface::class);
            $sqlQuery1 = "Update ox_workflow set process_ids='" . $data[0] . "' where id=1";
            $statement1 = $dbAdapter->query($sqlQuery1);
            $result1 = $statement1->execute();
            $this->processId = $data[0];
        }
    }

    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__) . "/../Dataset/Workflow.yml");
        switch ($this->getName()) {
            case 'testSubmitTaskWithDefinedFields':
                $dataset->addYamlFile(dirname(__FILE__) . "/../Dataset/Activity.yml");
                break;
            case 'testGetListActivityLogByFileIdWithOutWorkflow':
                $dataset->addYamlFile(dirname(__FILE__) . "/../Dataset/FileWithoutWorkflow.yml");
                break;
        }
        return $dataset;
    }

    /**
     * Restore params
     */
    protected function tearDown(): void
    {
        $tm = $this->getTransactionManager();
        $tm->rollback();
        parent::tearDown();
        $_REQUEST = [];
    }

    public function testGetList()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/workflow/1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4/activity/2', 'GET');
        $this->assertResponseStatusCode(500);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('WorkflowInstanceController');
        $this->assertMatchedRouteName('workflowInstance');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testCreate()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'workflow3', 'app_id' => '9fc99df0-d91b-11e9-8a34-2a2ae2dbcce4', 'fax' => "34343434343"];
        $fileCount = $this->getConnection()->getRowCount('ox_file');
        $fileAttributeCount = $this->getConnection()->getRowCount('ox_file_attribute');
        $this->setJsonContent(json_encode($data));
        if (enableCamunda == 0) {
            $mockProcessEngine = Mockery::mock('\Oxzion\Workflow\Camunda\ProcessEngineImpl');
            $workflowService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\WorkflowInstanceService::class);
            $mockProcessEngine->expects('startProcess')->withArgs(function ($arg1, $arg2) {
                $result = $arg1 == 'Process_1dx3jli:931b7c8b-fef7-11e9-89d4-0294414e067f';
                $result = $result && count($arg2) == 11;
                $result = $result && isset($arg2['fileId']);
                $result = $result && isset($arg2['workflow_instance_id']);
                return $result;
            })->once()->andReturn(array('id' => 1));
            $workflowService->setProcessEngine($mockProcessEngine);
            $this->processId = 1;
        }
        $this->dispatch('/workflow/1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($fileAttributeCount, $this->getConnection()->getRowCount('ox_file_attribute'));
        $this->assertEquals($fileCount + 1, $this->getConnection()->getRowCount('ox_file'));
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('WorkflowInstanceController');
        $this->assertMatchedRouteName('workflowInstance');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
    }

    public function testCreateWithPredefinedFields()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'workflow3',
                 'app_id' => '9fc99df0-d91b-11e9-8a34-2a2ae2dbcce4',
                 'padi_number' => 22333,
                 'firstname' => 'Mohan',
                 'fax' => "34343434343"];
        $fileCount = $this->getConnection()->getRowCount('ox_file');
        $fileAttributeCount = $this->getConnection()->getRowCount('ox_file_attribute');
        $processInstanceId = '12121212eeffabc23323add';
        $query = "select * from ox_workflow_deployment where id = 3";
        $workflowDeployment = $this->executeQueryTest($query);
        $workflowId = 'ef41cd2e-cb14-11e9-a32f-2a2ae2dbcc11';
        $this->setJsonContent(json_encode($data));
        if (enableCamunda == 0) {
            $mockProcessEngine = Mockery::mock('\Oxzion\Workflow\Camunda\ProcessEngineImpl');
            $workflowService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\WorkflowInstanceService::class);
            $mockProcessEngine->expects('startProcess')->withArgs(function ($arg1, $arg2) {
                $result = $arg1 == 'Process_1dx3w3e:ef1b7c8b-fef7-11e9-89d4-0294414e0633';
                $result = $result && count($arg2) == 7;
                $result = $result && $arg2['app_id'] == '9fc99df0-d91b-11e9-8a34-2a2ae2dbcce4';
                $result = $result && $arg2['padi_number'] == 22333;
                $result = $result && $arg2['firstname'] == 'Mohan';
                $result = $result && $arg2['workflowId'] == 'ef41cd2e-cb14-11e9-a32f-2a2ae2dbcc11';
                $result = $result && $arg2['accountId'] == AuthContext::get(AuthConstants::ACCOUNT_UUID);
                $result = $result && isset($arg2['fileId']);
                $result = $result && isset($arg2['workflow_instance_id']);
                return $result;
            })->once()->andReturn(array('id' => $processInstanceId));
            $workflowService->setProcessEngine($mockProcessEngine);
        }
        $this->dispatch("/workflow/$workflowId", 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($fileAttributeCount, $this->getConnection()->getRowCount('ox_file_attribute'));
        $this->assertEquals($fileCount + 1, $this->getConnection()->getRowCount('ox_file'));
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('WorkflowInstanceController');
        $this->assertMatchedRouteName('workflowInstance');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
    }

    public function testCreateFailure()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['sequence' => 1];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/workflow/1141cd2e-cb14-11e9-a32f-2a2ae2dbcc89', 'POST', null);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('WorkflowInstanceController');
        $this->assertMatchedRouteName('workflowInstance');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testCreateWithParentWorkflowInstance()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'workflow3', 'app_id' => 1, 'fax' => "34343434343", 'parentWorkflowInstanceId' => '3f20b5c5-0124-11ea-a8a0-22e8105c0798'];
        $query = "update ox_file set last_workflow_instance_id = 2 where id = 12";
        $result = $this->executeUpdate($query);
        $fileCount = $this->getConnection()->getRowCount('ox_file');
        $fileAttributeCount = $this->getConnection()->getRowCount('ox_file_attribute');
        $this->setJsonContent(json_encode($data));
        if (enableCamunda == 0) {
            $mockProcessEngine = Mockery::mock('\Oxzion\Workflow\Camunda\ProcessEngineImpl');
            $workflowService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\WorkflowInstanceService::class);
            $mockProcessEngine->expects('startProcess')->withAnyArgs()->once()->andReturn(array('id' => 1));
            $workflowService->setProcessEngine($mockProcessEngine);
            $this->processId = 1;
        }
        $this->dispatch('/workflow/1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($fileAttributeCount, $this->getConnection()->getRowCount('ox_file_attribute'));
        $newFileCount = $this->getConnection()->getRowCount('ox_file');
        $this->assertEquals($fileCount, $newFileCount);
        $query = "select * from ox_workflow_instance where id > 5";
        $result = $this->executeQueryTest($query);
        $this->assertEquals(1, count($result));
        $this->assertEquals(12, $result[0]['file_id']);
        $workflowInstanceId = $result[0]['id'];
        $query = "select * from ox_file where id = 12";
        $result = $this->executeQueryTest($query);
        $this->assertEquals($workflowInstanceId, $result[0]['last_workflow_instance_id']);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('WorkflowInstanceController');
        $this->assertMatchedRouteName('workflowInstance');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
    }

    public function testCreateWithParentWorkflowInstanceExistingProcess()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'workflow3', 'app_id' => 1, 'fax' => "34343434343", 'parentWorkflowInstanceId' => '3f20b5c5-0124-11ea-a8a0-22e8105c0790'];
        $query = "update ox_file set last_workflow_instance_id = 3 where id = 11";
        $result = $this->executeUpdate($query);
        $fileCount = $this->getConnection()->getRowCount('ox_file');
        $fileAttributeCount = $this->getConnection()->getRowCount('ox_file_attribute');
        $this->setJsonContent(json_encode($data));
        if (enableCamunda == 0) {
            $mockProcessEngine = Mockery::mock('\Oxzion\Workflow\Camunda\ProcessEngineImpl');
            $workflowService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\WorkflowInstanceService::class);
            $mockProcessEngine->expects('startProcess')->withAnyArgs()->once()->andReturn(array('id' => 1));
            $workflowService->setProcessEngine($mockProcessEngine);
            $this->processId = 1;
        }
        $this->dispatch('/workflow/1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(412);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('WorkflowInstanceController');
        $this->assertMatchedRouteName('workflowInstance');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'A Process is already underway for this file');
    }

    public function testCreateByLinkingToExistingFile()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'workflow3', 'app_id' => 1, 'fax' => "34343434343", 'uuid' => 'd13d0c68-98c9-11e9-adc5-308d99c9146d'];
        $fileCount = $this->getConnection()->getRowCount('ox_file');
        $fileAttributeCount = $this->getConnection()->getRowCount('ox_file_attribute');
        $this->setJsonContent(json_encode($data));
        if (enableCamunda == 0) {
            $mockProcessEngine = Mockery::mock('\Oxzion\Workflow\Camunda\ProcessEngineImpl');
            $workflowService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\WorkflowInstanceService::class);
            $mockProcessEngine->expects('startProcess')->withAnyArgs()->once()->andReturn(array('id' => 1));
            $workflowService->setProcessEngine($mockProcessEngine);
            $this->processId = 1;
        }
        $this->dispatch('/workflow/1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($fileAttributeCount, $this->getConnection()->getRowCount('ox_file_attribute'));
        $newFileCount = $this->getConnection()->getRowCount('ox_file');
        $this->assertEquals($fileCount, $newFileCount);
        $query = "select * from ox_workflow_instance where id > 5";
        $result = $this->executeQueryTest($query);
        $this->assertEquals(1, count($result));
        $this->assertEquals(14, $result[0]['file_id']);
        $workflowInstanceId = $result[0]['id'];
        $query = "select * from ox_file where id = 14";
        $result = $this->executeQueryTest($query);
        $this->assertEquals($workflowInstanceId, $result[0]['last_workflow_instance_id']);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('WorkflowInstanceController');
        $this->assertMatchedRouteName('workflowInstance');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
    }

    public function testCreateByLinkingToExistingFileWithOngoingProcess()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'workflow3', 'app_id' => 1, 'fax' => "34343434343", 'uuid' => 'd13d0c68-98c9-11e9-adc5-308d99c9145b'];
        $query = "update ox_file set last_workflow_instance_id = 3 where id = 11";
        $result = $this->executeUpdate($query);
        $fileCount = $this->getConnection()->getRowCount('ox_file');
        $fileAttributeCount = $this->getConnection()->getRowCount('ox_file_attribute');
        $this->setJsonContent(json_encode($data));
        if (enableCamunda == 0) {
            $mockProcessEngine = Mockery::mock('\Oxzion\Workflow\Camunda\ProcessEngineImpl');
            $workflowService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\WorkflowInstanceService::class);
            $mockProcessEngine->expects('startProcess')->withAnyArgs()->once()->andReturn(array('id' => 1));
            $workflowService->setProcessEngine($mockProcessEngine);
            $this->processId = 1;
        }
        $this->dispatch('/workflow/1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(412);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('WorkflowInstanceController');
        $this->assertMatchedRouteName('workflowInstance');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'A Process is already underway for this file');
    }

    public function testCreateWithOngoingProcess()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'workflow3', 'appId' => 1, 'fax' => "34343434343", "identifier_field" => "padi_number", "padi_number" => "1234", "email" => "test@eoxvantage.in", "firstname" => "Test", "lastname" => "User","address1" => "Address 1", "city" => "Bengaluru", "state" => "Karnataka", "country" => "India", "zip" => "560085", "type" => "INDIVIDUAL"];
        $query = "update ox_file set last_workflow_instance_id = 3 where id = 11";
        $result = $this->executeUpdate($query);
        $fileCount = $this->getConnection()->getRowCount('ox_file');
        $fileAttributeCount = $this->getConnection()->getRowCount('ox_file_attribute');
        $this->setJsonContent(json_encode($data));
        if (enableCamunda == 0) {
            $mockProcessEngine = Mockery::mock('\Oxzion\Workflow\Camunda\ProcessEngineImpl');
            $workflowService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\WorkflowInstanceService::class);
            $mockProcessEngine->expects('startProcess')->withAnyArgs()->once()->andReturn(array('id' => 1));
            $workflowService->setProcessEngine($mockProcessEngine);
            $this->processId = 1;
        }
        $this->dispatch('/workflow/1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(412);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('WorkflowInstanceController');
        $this->assertMatchedRouteName('workflowInstance');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'A Process is already underway for this file');
    }

    // Code commented
    //     public function testUpdate(){
    //         $this->initAuthToken($this->adminUser);
    //         $data = ['name' => 'Sample2','app_id' => 1];
    //         $this->setJsonContent(json_encode($data));
    //         $this->dispatch('/workflow/1', 'PUT', null);
    //         $this->assertResponseStatusCode(200);
    //         $this->assertModuleName('App');
    //         $this->assertControllerName(WorkflowController::class); // as specified in router's controller name alias
    //         $this->assertControllerClass('WorkflowController');
    //         $this->assertMatchedRouteName('appworkflow');
    //         $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    //         $content = (array)json_decode($this->getResponse()->getContent(), true);
    //         $this->assertEquals($content['status'], 'success');
    //         $this->assertEquals($content['data']['id'], 1);
    //         $this->assertEquals($content['data']['name'], $data['name']);
    //     }

//     public function testUpdateNotFound(){
    //         $this->initAuthToken($this->adminUser);
    //         $data = ['name' => 'Sample2'];
    //         $this->setJsonContent(json_encode($data));
    //         $this->dispatch('/workflow/122', 'PUT', null);
    //         $this->assertResponseStatusCode(404);
    //         $this->assertModuleName('App');
    //         $this->assertControllerName(WorkflowController::class); // as specified in router's controller name alias
    //         $this->assertControllerClass('WorkflowController');
    //         $this->assertMatchedRouteName('appworkflow');
    //         $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    //         $content = (array)json_decode($this->getResponse()->getContent(), true);
    //         $this->assertEquals($content['status'], 'error');
    //     }
    //     End of commented code

    public function testClaimActivityInstance()
    {
        $this->initAuthToken($this->adminUser);
        if (enableCamunda == 0) {
            $mockActivityEngine = Mockery::mock('\Oxzion\Workflow\Camunda\ActivityImpl');
            $activityInstanceService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\ActivityInstanceService::class);
            $mockActivityEngine->expects('claimActivity')->with('3f6622fd-0124-11ea-a8a0-22e8105c0778', $this->adminUser)->once()->andReturn(1);
            $activityInstanceService->setActivityEngine($mockActivityEngine);
        }
        $this->dispatch('/app/9fc99df0-d91b-11e9-8a34-2a2ae2dbcce4/workflowinstance/1/activityinstance/3f6622fd-0124-11ea-a8a0-22e8105c0778/claim', 'POST');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('WorkflowInstanceController');
        $this->assertMatchedRouteName('claimActivityInstance');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testClaimActivityInstanceNotFound()
    {
        $this->initAuthToken($this->employeeUser);
        if (enableCamunda == 0) {
            $mockActivityEngine = Mockery::mock('\Oxzion\Workflow\Camunda\ActivityImpl');
            $activityInstanceService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\ActivityInstanceService::class);
            $mockActivityEngine->expects('claimActivity')->with('[activityInstanceId]', $this->employeeUser)->once()->andReturn(1);
            $activityInstanceService->setActivityEngine($mockActivityEngine);
        }
        $this->dispatch('/app/9fc99df0-d91b-11e9-8a34-2a2ae2dbcce4/workflowinstance/1/activityinstance/2/claim', 'POST');
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('WorkflowInstanceController');
        $this->assertMatchedRouteName('claimActivityInstance');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testClaimActivityInstanceNewUser()
    {
        $this->initAuthToken($this->employeeUser);
        if (enableCamunda == 0) {
            $mockActivityEngine = Mockery::mock('\Oxzion\Workflow\Camunda\ActivityImpl');
            $activityInstanceService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\ActivityInstanceService::class);
            $mockActivityEngine->expects('claimActivity')->with('3f6622fd-0124-11ea-a8a0-22e8105c0778', $this->employeeUser)->once()->andReturn(1);
            $activityInstanceService->setActivityEngine($mockActivityEngine);
        }
        $this->dispatch('/app/9fc99df0-d91b-11e9-8a34-2a2ae2dbcce4/workflowinstance/1/activityinstance/3f6622fd-0124-11ea-a8a0-22e8105c0778/claim', 'POST');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('WorkflowInstanceController');
        $this->assertMatchedRouteName('claimActivityInstance');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testUnclaimActivityInstance()
    {
        $this->initAuthToken($this->adminUser);
        if (enableCamunda == 0) {
            $mockActivityEngine = Mockery::mock('\Oxzion\Workflow\Camunda\ActivityImpl');
            $activityInstanceService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\ActivityInstanceService::class);
            $mockActivityEngine->expects('unclaimActivity')->with('3f6622fd-0124-11ea-a8a0-22e8105c0778', $this->adminUser)->once()->andReturn(1);
            $activityInstanceService->setActivityEngine($mockActivityEngine);
        }
        $this->dispatch('/app/9fc99df0-d91b-11e9-8a34-2a2ae2dbcce4/workflowinstance/1/activityinstance/3f6622fd-0124-11ea-a8a0-22e8105c0778/unclaim', 'POST');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('WorkflowInstanceController');
        $this->assertMatchedRouteName('unclaimActivityInstance');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testReclaimActivityInstance()
    {
        $this->initAuthToken($this->adminUser);
        if (enableCamunda == 0) {
            $mockActivityEngine = Mockery::mock('\Oxzion\Workflow\Camunda\ActivityImpl');
            $activityInstanceService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\ActivityInstanceService::class);
            $mockActivityEngine->expects('unclaimActivity')->with('3f6622fd-0124-11ea-a8a0-22e8105c0778', $this->adminUser)->once()->andReturn(1);
            $activityInstanceService->setActivityEngine($mockActivityEngine);
            $mockActivityEngine->expects('claimActivity')->with('3f6622fd-0124-11ea-a8a0-22e8105c0778', $this->adminUser)->once()->andReturn(1);
            $activityInstanceService->setActivityEngine($mockActivityEngine);
        }
        $this->dispatch('/app/9fc99df0-d91b-11e9-8a34-2a2ae2dbcce4/workflowinstance/1/activityinstance/3f6622fd-0124-11ea-a8a0-22e8105c0778/reclaim', 'POST');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('WorkflowInstanceController');
        $this->assertMatchedRouteName('reclaimActivityInstance');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }
    public function testGetActivityInstance()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/9fc99df0-d91b-11e9-8a34-2a2ae2dbcce4/workflowinstance/3f20b5c5-0124-11ea-a8a0-22e8105c0998/activityinstance/3f6622fd-0124-11ea-a8a0-22e8105c0723/form', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('WorkflowInstanceController');
        $this->assertMatchedRouteName('activityInstanceForm');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']) > 0, true);
    }

    public function testGetActivityInstanceNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/9fc99df0-d91b-11e9-8a34-2a2ae2dbcce4/workflowinstance/1/activityinstance/99999/form', 'GET');
        $this->assertResponseStatusCode(500);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('WorkflowInstanceController');
        $this->assertMatchedRouteName('activityInstanceForm');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testcompleteActivityInstance()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['activityInstanceId' => '3f6622fd-0124-11ea-a8a0-22e8105c0723', 'candidates' => array(array('teamid' => 'HR Team', 'type' => 'candidate'), array('userid' => 'admintest', 'type' => 'assignee')), 'processInstanceId' => "3f20b5c5-0124-11ea-a8a0-22e8105c0998", 'name' => 'Recruitment Request Created', 'status' => 'Active', 'taskId' => "Task_1s7qzh3", 'processVariables' => array('workflowId' => "1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4", 'accountid' => $this->testAccountUuid)];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/workflow/activitycomplete', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(ActivityInstanceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('ActivityInstanceController');
        $this->assertMatchedRouteName('completeActivityInstance');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
    }

    public function testcompleteActivityInstanceFail()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['workflow_instance_id' => 1, 'activityInstanceId' => 'csasdassd', 'activityId' => 1, 'candidates' => array(array('teamid' => 'HR Team', 'type' => 'candidate'), array('userid' => 'admintest', 'type' => 'assignee')), 'processInstanceId' => 1, 'name' => 'Recruitment Request Created', 'status' => 'Active', 'taskId' => 1, 'processVariables' => array('workflowId' => 1, 'accountid' => $this->testAccountUuid)];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/workflow/activitycomplete', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(ActivityInstanceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('ActivityInstanceController');
        $this->assertMatchedRouteName('completeActivityInstance');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testSubmitTask()
    {
        $this->initAuthToken($this->adminUser);
        $data = ["val" => "91.00", "padiVerified" => true, "padi" => 2141, "work_phone" => null, "internationalNonteachingSupervisoryInstructor" => "0", "withTecRecEndorsementForSelectionAboveDeclined" => "0", "equipmentLiabilityCoverage" => "275.00", "approved" => true, 'action' => 'submit', "state" => "FL", "app_id" => "5c5b2544-a501-416c-98da-38af2cf3ff1a", "zip" => "32904", "scubaFit" => "scubaFitInstructorDeclined", "method" => "POST", "grandTotal" => 0, "tecRecEndorsment" => "withTecRecEndorsementForSelectionAbove", "entity_id" => "1", "lastname" => "METCALF", "excessLiability" => "excessLiabilityCoverage9000000", "internationalDivemasterAssistantInstructorAssistingOnly" => "127.00", "page4Panel2PanelIagree" => true, "panelColumnsValidatePadiMembership" => false, "end_date" => "2020-06-30", "access" => [], "city" => "MELBOURNE", "nonteachingSupervisoryInstructor" => "371.00", "freediveInstructor" => "371.00", "phonenumber" => "(962) 035-7215", "withTecRecEndorsementForSelectionAbove" => "0", "accountId" => "53012471-2863-4949-afb1-e69b0891c98a", "equipmentLiabilityCoverageDeclined" => "0.00", "excessLiabilityCoverage4000000" => "1459.00", "scubaFitPrice" => "0.00", "physical_zipcode" => "560027", "email" => "cativoire@aol.com", "start_date" => "2019-08-01", "physical_country" => "", "product" => "Individual Professional Liability", "excessLiabilityPrice" => "3258.00", "controller" => "Workflow\\Controller\\WorkflowInstanceController", "initial" => "S", "expiry_date" => "2019-10-18", "page5Select" => "noAdditionalInsureds", "notSelected" => "0", "retiredInstructor" => "253.00", "panelPanel3ColumnsFax" => "", "physical_city" => "Bengaluru", "internationalDivemaster" => "218.00", "country" => "United States of America", "swimInstructor" => "348.00", "cylinderPrice" => "269.00", "internationalAssistantInstructor" => "218.00", "physical_state" => "Karnataka", "careerCoverage" => "assistantInstructor", "page4Panel2Iagree" => true, "mobilephone" => "(132) 131-2312", "cylinderInspector" => "216.00", "physical_address2" => "", "physical_address1" => "Sadhitha", "MI" => "G", "excessLiabilityCoverage9000000" => "3258.00", "home_phone" => "321 952 1621", "identity_field" => "padi", "equipment" => "equipmentLiabilityCoverage", "careerCoveragePrice" => "371.00", "created_by" => "1", "country_code" => "US", "panelRegister" => false, "cylinderInstructor" => "114.00", "excessLiabilityCoverage3000000" => "1162.00", "instructor" => "643.00", "cylinderInspectorOrInstructorDeclined" => "0.00", "equipmentPrice" => "275.00", "panelPanel3ColumnsEmail2" => "", "divemaster" => "371.00", "scubaFitInstructorDeclined" => "0.00", "cylinderInspectorAndInstructor" => "269.00", "workflowId" => "4347ec07-88c2-4e84-846d-a45e59039150", "sameasmailingaddress" => false, "fileId" => "d13d0c68-98c9-11e9-adc5-308d99c9145d", "firstname" => "Rakshith", "excessLiabilityCoverage1000000" => "447.00", "divemasterAssistantInstructorAssistingOnly" => "253.00", "excessLiabilityCoverageDeclined" => "0.00", "page4PanelIAgree" => true, "tecRecEndorsmentPrice" => "0", "member_number" => 2141, "address2" => "", "address1" => "6100 LIVE OAK AVE", "page3Panel4Bycheckingthisbox" => true, "form_id" => "1", "excessLiabilityCoverage2000000" => "895.00", "workflow_instance_id" => "1", "scubaFitInstructor" => "60.00", "cylinder" => "cylinderInspectorAndInstructor", "assistantInstructor" => "371.00", "internationalInstructor" => "341.00", "automatic_renewal" => false];
        $this->setJsonContent(json_encode($data));
        if (enableCamunda == 0) {
            $mockProcessEngine = Mockery::mock('\Oxzion\Workflow\Camunda\ActivityImpl');
            $workflowService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\WorkflowInstanceService::class);
            $mockProcessEngine->expects('completeActivity')->withArgs(function ($arg1, $arg2) {
                $result = $arg1 == '3f6622fd-0124-11ea-a8a0-22e8105c0723';
                $result = $result && count($arg2) == 101;
                return $result;
            })->once()->andReturnUsing(function () {
                $activityService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\ActivityInstanceService::class);
                $data['processInstanceId'] = "3f20b5c5-0124-11ea-a8a0-22e8105c0998";
                $data['activityInstanceId'] = "3f6622fd-0124-11ea-a8a0-22e8105c0723";
                $activityService->completeActivityInstance($data);
            });
            $workflowService->setActivityEngine($mockProcessEngine);
        }
        $this->dispatch('/workflowinstance/3f20b5c5-0124-11ea-a8a0-22e8105c0998/activity/3f6622fd-0124-11ea-a8a0-22e8105c0723/submit', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('WorkflowInstanceController');
        $this->assertMatchedRouteName('workflowActivityInstance');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(is_array($content['data']), true);
    }

    public function testSubmitTaskWithDefinedFields()
    {
        $this->initAuthToken($this->adminUser);
        $data = ["val" => "91.00", "padiVerified" => true, "padi_number" => 2141, "work_phone" => null, "internationalNonteachingSupervisoryInstructor" => "0", "withTecRecEndorsementForSelectionAboveDeclined" => "0", "equipmentLiabilityCoverage" => "275.00", "approved" => true, 'action' => 'submit', "state" => "FL", "app_id" => "9fc99df0-d91b-11e9-8a34-2a2ae2dbcce4", "zip" => "32904", "scubaFit" => "scubaFitInstructorDeclined", "method" => "POST", "grandTotal" => 0, "tecRecEndorsment" => "withTecRecEndorsementForSelectionAbove", "entity_id" => "1", "lastname" => "METCALF", "excessLiability" => "excessLiabilityCoverage9000000", "internationalDivemasterAssistantInstructorAssistingOnly" => "127.00", "page4Panel2PanelIagree" => true, "panelColumnsValidatePadiMembership" => false, "end_date" => "2020-06-30", "access" => [], "city" => "MELBOURNE", "nonteachingSupervisoryInstructor" => "371.00", "freediveInstructor" => "371.00", "phonenumber" => "(962) 035-7215", "withTecRecEndorsementForSelectionAbove" => "0", "equipmentLiabilityCoverageDeclined" => "0.00", "excessLiabilityCoverage4000000" => "1459.00", "scubaFitPrice" => "0.00", "physical_zipcode" => "560027", "email" => "cativoire@aol.com", "start_date" => "2019-08-01", "physical_country" => "", "product" => "Individual Professional Liability", "excessLiabilityPrice" => "3258.00", "controller" => "Workflow\\Controller\\WorkflowInstanceController", "initial" => "S", "expiry_date" => "2019-10-18", "page5Select" => "noAdditionalInsureds", "notSelected" => "0", "retiredInstructor" => "253.00", "panelPanel3ColumnsFax" => "", "physical_city" => "Bengaluru", "internationalDivemaster" => "218.00", "country" => "United States of America", "swimInstructor" => "348.00", "cylinderPrice" => "269.00", "internationalAssistantInstructor" => "218.00", "physical_state" => "Karnataka", "careerCoverage" => "assistantInstructor", "page4Panel2Iagree" => true, "mobilephone" => "(132) 131-2312", "cylinderInspector" => "216.00", "physical_address2" => "", "physical_address1" => "Sadhitha", "MI" => "G", "excessLiabilityCoverage9000000" => "3258.00", "home_phone" => "321 952 1621", "identity_field" => "padi", "equipment" => "equipmentLiabilityCoverage", "careerCoveragePrice" => "371.00", "created_by" => "7", "country_code" => "US", "panelRegister" => false, "cylinderInstructor" => "114.00", "excessLiabilityCoverage3000000" => "1162.00", "instructor" => "643.00", "cylinderInspectorOrInstructorDeclined" => "0.00", "equipmentPrice" => "275.00", "panelPanel3ColumnsEmail2" => "", "divemaster" => "371.00", "scubaFitInstructorDeclined" => "0.00", "cylinderInspectorAndInstructor" => "269.00", "workflowId" => "ef41cd2e-cb14-11e9-a32f-2a2ae2dbcc11", "sameasmailingaddress" => false, "fileId" => "ee3d0c68-98c9-11e9-adc5-308d99c914ca", "firstname" => "Rakshith", "excessLiabilityCoverage1000000" => "447.00", "divemasterAssistantInstructorAssistingOnly" => "253.00", "excessLiabilityCoverageDeclined" => "0.00", "page4PanelIAgree" => true, "tecRecEndorsmentPrice" => "0", "member_number" => 2141, "address2" => "", "address1" => "6100 LIVE OAK AVE", "page3Panel4Bycheckingthisbox" => true, "form_id" => "1", "excessLiabilityCoverage2000000" => "895.00", "workflow_instance_id" => "6", "scubaFitInstructor" => "60.00", "cylinder" => "cylinderInspectorAndInstructor", "assistantInstructor" => "371.00", "internationalInstructor" => "341.00", "automatic_renewal" => false];
        $this->setJsonContent(json_encode($data));
        if (enableCamunda == 0) {
            $mockProcessEngine = Mockery::mock('\Oxzion\Workflow\Camunda\ActivityImpl');
            $workflowService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\WorkflowInstanceService::class);
            $mockProcessEngine->expects('completeActivity')->withArgs(function ($arg1, $arg2) {
                $result = $arg1 == 'e36622fd-0124-11ea-a8a0-22e8105c07af';
                $result = $result && count($arg2) == 8;
                $result = $result && $arg2['app_id'] == '9fc99df0-d91b-11e9-8a34-2a2ae2dbcce4';
                $result = $result && $arg2['padi_number'] == 2141;
                $result = $result && $arg2['firstname'] == 'Rakshith';
                $result = $result && $arg2['workflowId'] == 'ef41cd2e-cb14-11e9-a32f-2a2ae2dbcc11';
                $result = $result && $arg2['accountId'] == AuthContext::get(AuthConstants::ACCOUNT_UUID);
                $result = $result && $arg2['fileId'] == 'ee3d0c68-98c9-11e9-adc5-308d99c914ca';
                $result = $result && $arg2['workflow_instance_id'] == 6;
                $result = $result && $arg2['workflowInstanceId'] == 'de20b5c5-0124-11ea-a8a0-22e8105c07fe';
                return $result;
            })->once()->andReturnUsing(function () {
                $activityService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\ActivityInstanceService::class);
                $data['processInstanceId'] = "de20b5c5-0124-11ea-a8a0-22e8105c07fe";
                $data['activityInstanceId'] = "e36622fd-0124-11ea-a8a0-22e8105c07af";
                $data['taskId'] = 'Task_1s7qwett4';
                $data['processVariables'] = ['workflowId' => 'ef41cd2e-cb14-11e9-a32f-2a2ae2dbcc11',
                                             'accountId' => AuthContext::get(AuthConstants::ACCOUNT_UUID)];
                $activityService->completeActivityInstance($data);
            });
            $workflowService->setActivityEngine($mockProcessEngine);
        }
        $this->dispatch('/workflowinstance/de20b5c5-0124-11ea-a8a0-22e8105c07fe/activity/e36622fd-0124-11ea-a8a0-22e8105c07af/submit', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('WorkflowInstanceController');
        $this->assertMatchedRouteName('workflowActivityInstance');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(is_array($content['data']), true);
        $query = "select * from ox_activity_instance where workflow_instance_id = 6 order by submitted_date";
        $result = $this->executeQueryTest($query);
        $this->assertEquals(1, count($result));
        $this->assertEquals(5, $result[0]['id']);
        $this->assertEquals('Completed', $result[0]['status']);
    }
    
    // public function testGetListActivityLogByFileIdWithOutWorkflow()
    // {
    //     $this->initAuthToken($this->adminUser);
    //     $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/d13d0c68-98c9-22e9-adc5-308d99c9145c/activitylog', 'GET');
    //     $content = json_decode($this->getResponse()->getContent(), true);
    //     $this->assertResponseStatusCode(200);
    //     $this->assertModuleName('Workflow');
    //     $this->assertControllerName(WorkflowInstanceController::class);
    //     $this->assertControllerClass('WorkflowInstanceController');
    //     $this->assertMatchedRouteName('activitylog');
    //     $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    //     $this->assertEquals($content['status'], 'success');
    //     $select = "select * from ox_file_audit_log WHERE uuid IN ('d13d0c68-98c9-22e9-adc5-308d99c9145c')";
    //     $query = $this->executeQueryTest($select);
    //     $this->assertEquals($content['data'][0]['data'], $query[0]['data']);

    // }
    
    public function testGetListActivityLogByActivityInstanceId()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/activity/3f6622fd-0124-11ea-a8a0-22e8105c0778', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceController::class);
        $this->assertControllerClass('WorkflowInstanceController');
        $this->assertMatchedRouteName('fielddiff');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }
}
