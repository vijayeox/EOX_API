<?php
namespace Workflow;

use Workflow\Controller\WorkflowInstanceCallbackController;
use Workflow\Controller\ActivityInstanceController;
use App\Controller\WorkflowController;
use Zend\Stdlib\ArrayUtils;
use Oxzion\Test\ControllerTest;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Adapter\Platform\Mysql;
use Zend\Db\Adapter\Adapter;
use PHPUnit\DbUnit\TestCaseTrait;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Oxzion\Workflow\WorkflowFactory;
use Zend\Db\Adapter\AdapterInterface;
use Mockery;

class WorkflowInstanceCallbackControllerTest extends ControllerTest
{
    private $processId;
    public function setUp() : void
    {
        $this->loadConfig();
        parent::setUp();
        if (enableCamunda == 1) {
            $workflowFactory = WorkflowFactory::getInstance();
            $processManager = $workflowFactory->getProcessManager();
            $data = $processManager->deploy('TestProcess1', array(__DIR__."/../Dataset/ScriptTaskTest.bpmn"));
            $dbAdapter = $this->getApplicationServiceLocator()->get(AdapterInterface::class);
            $sqlQuery1 = "Update ox_workflow set process_ids='".$data[0]."' where id=1";
            $statement1 = $dbAdapter->query($sqlQuery1);
            $result1 = $statement1->execute();
            $this->processId = $data[0];
        }
    }
    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__)."/../Dataset/Workflow.yml");
        return $dataset;
    }

    public function testcompleteWorkflowInstance()
    {
        $data = ['processInstanceId'=>1];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/workflowinstance/complete', 'POST',$data);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceCallbackController::class); // as specified in router's controller name alias
        $this->assertControllerClass('WorkflowInstanceCallbackController');
        $this->assertMatchedRouteName('completeWorkflowInstance');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }
    public function testcompleteWorkflowInstanceFail()
    {
        $data = ['processInstanceId'=>5];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/workflowinstance/complete', 'POST',$data);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceCallbackController::class); // as specified in router's controller name alias
        $this->assertControllerClass('WorkflowInstanceCallbackController');
        $this->assertMatchedRouteName('completeWorkflowInstance');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testinitiateWorkflow()
    {
        $data = $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd","processInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd","variables" => array("firstname" => "Neha","policy_period" => "1year","card_expiry_date" => "10/24","city" => "Bangalore","orgUuid" => "53012471-2863-4949-afb1-e69b0891c98a","isequipmentliability" => "1","card_no" => "1234","state" => "karnataka","app_id" => "ec8942b7-aa93-4bc6-9e8c-e1371988a5d4","zip" => "560030","coverage" => "100000","product" => "Individual Professional Liability","address2" => "dhgdhdh","address1" => "hjfjhfjfjfhfg","expiry_date" => "2020-06-30","form_id" =>"0","entity_id" => "1","created_by"=> "1","expiry_year" => "2019","orgid" => "53012471-2863-4949-afb1-e69b0891c98a","lastname" => "Rai","isexcessliability" => "1","workflow_instance_id" => "1","credit_card_type" => "credit","workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925","email" => 'bharat@gmail.com'),"parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd","parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/workflowinstance/start', 'POST',$data);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceCallbackController::class); // as specified in router's controller name alias
        $this->assertControllerClass('WorkflowInstanceCallbackController');
        $this->assertMatchedRouteName('initiateWorkflow');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testinitiateWorkflowInvalidData()
    {
        $data = $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd","processInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd","parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd","parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/workflowinstance/start', 'POST',$data);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceCallbackController::class); // as specified in router's controller name alias
        $this->assertControllerClass('WorkflowInstanceCallbackController');
        $this->assertMatchedRouteName('initiateWorkflow');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }
}
