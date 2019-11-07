<?php
namespace Workflow;

use Workflow\Controller\WorkflowInstanceController;
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

class WorkflowInstanceControllerTest extends ControllerTest
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

    public function testCreate()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'workflow3','app_id'=>1,'field2'=>1];
        $fileCount = $this->getConnection()->getRowCount('ox_file');
        $fileAttributeCount = $this->getConnection()->getRowCount('ox_file_attribute');

        $this->setJsonContent(json_encode($data));
        if (enableCamunda == 0) {
            $mockProcessEngine = Mockery::mock('\Oxzion\Workflow\Camunda\ProcessEngineImpl');
            $workflowService = $this->getApplicationServiceLocator()->get(\Workflow\Service\WorkflowInstanceService::class);
            $mockProcessEngine->expects('startProcess')->withAnyArgs()->once()->andReturn(array('id'=>1));
            $workflowService->setProcessEngine($mockProcessEngine);
            $this->processId = 1;
        }
        $this->dispatch('/workflow/1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4', 'POST', $data);

        $this->assertEquals($fileAttributeCount+3, $this->getConnection()->getRowCount('ox_file_attribute'));
        $this->assertEquals($fileCount+1, $this->getConnection()->getRowCount('ox_file'));
        $content = json_decode($this->getResponse()->getContent(), true);
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
        $data = ['sequence'=>1];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/workflow/1141cd2e-cb14-11e9-a32f-2a2ae2dbcc89', 'POST', null);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('WorkflowInstanceController');
        $this->assertMatchedRouteName('workflowInstance');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testUpdate(){
        $this->initAuthToken($this->adminUser);
        $data = ['workflow_instance_id' => 1, 'activityInstanceId' =>'3f6622fd-0124-11ea-a8a0-22e8105c0778','activityId'=>1 , 'candidates' => array(array('groupid'=>'HR Group','type'=>'candidate'),array('userid'=>'bharatgtest','type'=>'assignee')),'processInstanceId'=>'3f20b5c5-0124-11ea-a8a0-22e8105c0778','name'=>'Recruitment Request Created', 'status' => 'Active','taskId'=>1,'processVariables'=>array('workflow_id'=>'1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4','orgid'=>$this->testOrgId)];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/workflow/activitycomplete', 'POST',$data);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(ActivityInstanceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('ActivityInstanceController');
        $this->assertMatchedRouteName('completeActivityInstance');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }
   
    public function testcompleteActivityInstanceFail()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['workflow_instance_id' => 1, 'activityInstanceId' =>'csasdassd','activityId'=>1 , 'candidates' => array(array('groupid'=>'HR Group','type'=>'candidate'),array('userid'=>'bharatgtest','type'=>'assignee')),'processInstanceId'=>1,'name'=>'Recruitment Request Created', 'status' => 'Active','taskId'=>1,'processVariables'=>array('workflowId'=>1,'orgid'=>$this->testOrgId)];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/workflow/activitycomplete'
            , 'POST',$data);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(ActivityInstanceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('ActivityInstanceController');
        $this->assertMatchedRouteName('completeActivityInstance');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testgetFileDocumentList(){
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/9fc99df0-d91b-11e9-8a34-2a2ae2dbcce4/file/d13d0c68-98c9-11e9-adc5-308d99c9145b/document', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('WorkflowInstanceController');
        $this->assertMatchedRouteName('filedocumentlisting');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data'])>0, true);
    }

    public function testgetFileDocumentListNotFound(){
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/9fc99df0-d91b-11e9-8a34-2a2ae2dbcce4/file/d13d0c68-98c9-11e9-adc5-308d99c91422/document', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Workflow');
        $this->assertControllerName(WorkflowInstanceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('WorkflowInstanceController');
        $this->assertMatchedRouteName('filedocumentlisting');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
    }


    public function testSubmitTask(){
        $this->initAuthToken($this->adminUser);
        $data =  ["val"=>"91.00","padiVerified"=>true,"padi"=>2141,"work_phone"=>null,"internationalNonteachingSupervisoryInstructor"=>"0","withTecRecEndorsementForSelectionAboveDeclined"=>"0","equipmentLiabilityCoverage"=>"275.00","approved"=>true,'action' => 'submit',"state"=>"FL","app_id"=>"5c5b2544-a501-416c-98da-38af2cf3ff1a","zip"=>"32904","scubaFit"=>"scubaFitInstructorDeclined","method"=>"POST","grandTotal"=>0,"tecRecEndorsment"=>"withTecRecEndorsementForSelectionAbove","entity_id"=>"1","lastname"=>"METCALF","excessLiability"=>"excessLiabilityCoverage9000000","internationalDivemasterAssistantInstructorAssistingOnly"=>"127.00","page4Panel2PanelIagree"=>true,"panelColumnsValidatePadiMembership"=>false,"end_date"=>"2020-06-30","access"=>[],"city"=>"MELBOURNE","nonteachingSupervisoryInstructor"=>"371.00","freediveInstructor"=>"371.00","phonenumber"=>"(962) 035-7215","withTecRecEndorsementForSelectionAbove"=>"0","orgId"=>"0e956d4a-108a-4a8c-9921-646ed322026e","equipmentLiabilityCoverageDeclined"=>"0.00","excessLiabilityCoverage4000000"=>"1459.00","scubaFitPrice"=>"0.00","physical_zipcode"=>"560027","email"=>"cativoire@aol.com","start_date"=>"2019-08-01","physical_country"=>"","product"=>"Individual Professional Liability","excessLiabilityPrice"=>"3258.00","controller"=>"Workflow\\Controller\\WorkflowInstanceController","initial"=>"S","expiry_date"=>"2019-10-18","page5Select"=>"noAdditionalInsureds","notSelected"=>"0","retiredInstructor"=>"253.00","panelPanel3ColumnsFax"=>"","physical_city"=>"Bengaluru","internationalDivemaster"=>"218.00","country"=>"United States of America","swimInstructor"=>"348.00","cylinderPrice"=>"269.00","internationalAssistantInstructor"=>"218.00","physical_state"=>"Karnataka","careerCoverage"=>"assistantInstructor","page4Panel2Iagree"=>true,"mobilephone"=>"(132) 131-2312","cylinderInspector"=>"216.00","physical_address2"=>"","physical_address1"=>"Sadhitha","MI"=>"G","excessLiabilityCoverage9000000"=>"3258.00","home_phone"=>"321 952 1621","identity_field"=>"padi","equipment"=>"equipmentLiabilityCoverage","careerCoveragePrice"=>"371.00","created_by"=>"7","country_code"=>"US","panelRegister"=>false,"cylinderInstructor"=>"114.00","excessLiabilityCoverage3000000"=>"1162.00","instructor"=>"643.00","cylinderInspectorOrInstructorDeclined"=>"0.00","equipmentPrice"=>"275.00","panelPanel3ColumnsEmail2"=>"","divemaster"=>"371.00","scubaFitInstructorDeclined"=>"0.00","cylinderInspectorAndInstructor"=>"269.00","workflowId"=>"4347ec07-88c2-4e84-846d-a45e59039150","sameasmailingaddress"=>false,"fileId"=>"ce2b5638-bf7b-4b2d-ba85-e5397847ec79","firstname"=>"Rakshith","excessLiabilityCoverage1000000"=>"447.00","divemasterAssistantInstructorAssistingOnly"=>"253.00","excessLiabilityCoverageDeclined"=>"0.00","page4PanelIAgree"=>true,"tecRecEndorsmentPrice"=>"0","member_number"=>2141,"address2"=>"","address1"=>"6100 LIVE OAK AVE","page3Panel4Bycheckingthisbox"=>true,"form_id"=>"1","excessLiabilityCoverage2000000"=>"895.00","workflow_instance_id"=>"1","scubaFitInstructor"=>"60.00","cylinder"=>"cylinderInspectorAndInstructor","assistantInstructor"=>"371.00","internationalInstructor"=>"341.00","automatic_renewal" => false];
        $this->setJsonContent(json_encode($data));
        if (enableCamunda == 0) {
            $mockProcessEngine = Mockery::mock('\Oxzion\Workflow\Camunda\ActivityImpl');
            $workflowService = $this->getApplicationServiceLocator()->get(\Workflow\Service\WorkflowInstanceService::class);
            $mockProcessEngine->expects('submitTaskForm')->withAnyArgs()->once()->andReturnUsing(function (){
                $activityService = $this->getApplicationServiceLocator()->get(\Workflow\Service\ActivityInstanceService::class);
                $data['processInstanceId'] = "3f20b5c5-0124-11ea-a8a0-22e8105c0778";
                $data['activityInstanceId'] = "3f6622fd-0124-11ea-a8a0-22e8105c0778";
                $activityService->completeActivityInstance($data);
            } );
            $workflowService->setActivityEngine($mockProcessEngine);
        }
        $this->dispatch('/workflowinstance/3f20b5c5-0124-11ea-a8a0-22e8105c0778/activity/3f6622fd-0124-11ea-a8a0-22e8105c0778/submit', 'POST',$data);
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
}
