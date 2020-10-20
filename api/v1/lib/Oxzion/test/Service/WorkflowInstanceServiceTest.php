<?php
namespace Oxzion\Service;

use Oxzion\Test\AbstractServiceTest;
use Zend\Db\Adapter\Adapter;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Symfony\Component\Yaml\Yaml;
use Oxzion\ServiceException;
use Oxzion\Workflow\WorkflowFactory;
use Oxzion\ValidationException;
use Oxzion\EntityNotFoundException;
use Zend\Db\Adapter\Exception\InvalidQueryException;
use \Exception;
use Mockery;
use Zend\Db\ResultSet\ResultSet;

class WorkflowInstanceServiceTest extends AbstractServiceTest
{
    public $adapter = null;
    private $processId = '8ddf83c0-4971-4bac-9bf7-49264db1172e';
    protected function setUp(): void
    {
        $this->loadConfig();
        parent::setUp();
        $this->workflowInstanceService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\WorkflowInstanceService::class);
        AuthContext::put(AuthConstants::ORG_ID, 1);
        AuthContext::put(AuthConstants::ORG_UUID, '53012471-2863-4949-afb1-e69b0891c98a');
        AuthContext::put(AuthConstants::USER_ID, 1);
        if (enableCamunda == 1) {
            $workflowFactory = WorkflowFactory::getInstance();
            $processManager = $workflowFactory->getProcessManager();
            $data = $processManager->deploy('TestProcess1', array(dirname(__FILE__) . "/Dataset/testBpmn.bpmn"));
            $dbAdapter = $this->getApplicationServiceLocator()->get(AdapterInterface::class);
            $sqlQuery1 = "Update ox_workflow set process_ids='" . $data[0] . "' where id=1";
            $this->runQuery($sqlQuery1);
            $this->processId = $data[0];
        }
        $this->adapter = $this->getDbAdapter();
        $this->adapter->getDriver()->getConnection()->setResource(static::$pdo);
    }

    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__)."/Dataset/File.yml");
        $dataset->addYamlFile(dirname(__FILE__) . "/../../../../module/User/test/Dataset/User.yml");
        switch($this->getName()){
            case "testStartWorkflowSetupIdentityField":
            case "testStartWorkflowSetupIdentityFieldAsPolicyHolder":
                $dataset->addYamlFile(dirname(__FILE__) . "/Dataset/businessRole.yml");
                break;
        }
        return $dataset;
    }

    private function runQuery($query) {
        $statement = $this->adapter->query($query);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        return $result;
    }

	private function performAsserts($params){
        $orgId = 1;
        if(isset($params['orgId'])){
            $sqlQuery = "SELECT id from ox_organization where uuid = '".$params['orgId']."'";
            $queryResult = $this->runQuery($sqlQuery);    
            $orgId = $queryResult[0]['id'];
        }
        $sqlQuery = "SELECT * FROM ox_workflow_instance where process_instance_id = '".$this->processId."'";
        $queryResult = $this->runQuery($sqlQuery);
        $this->assertEquals(1, count($queryResult));
        $sqlQuery = "SELECT * FROM ox_file where id = ".$queryResult[0]['file_id'];
        $fileResult = $this->runQuery($sqlQuery);
        $this->assertEquals(1, count($fileResult));
        $this->assertEquals($orgId, $queryResult[0]['org_id']);
        $this->assertEquals(99, $queryResult[0]['app_id']);
        $this->assertEquals('In Progress', $queryResult[0]['status']);
        $this->assertEquals(AuthContext::get(AuthConstants::USER_ID), $queryResult[0]['created_by']);
        $this->assertEquals($fileResult[0]['data'], $queryResult[0]['start_data']);
        $this->assertEquals(1, $queryResult[0]['entity_id']);
        $data = json_decode($fileResult[0]['data'], true);
        foreach ($params as $key => $value) {
            switch($key){
                case 'workflowId':
                case 'app_id':
                case 'entity_id':
                case 'uuid':
                case 'orgId':
                case 'created_by':
                case 'parentWorkflowInstanceId':
                case 'type':
                case 'businessRole':
                    break;
                default:
                    $this->assertEquals($value, $data[$key]);
            }
        }
    }

    private function setupMockProcessEngine(){
        if (enableCamunda == 0) {
            $mockProcessEngine = Mockery::mock('\Oxzion\Workflow\Camunda\ProcessEngineImpl');
            $workflowService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\WorkflowInstanceService::class);
            $mockProcessEngine->expects('startProcess')->withAnyArgs()->once()->andReturn(array('id' => $this->processId));
            $workflowService->setProcessEngine($mockProcessEngine);
        }
    }
    
    public function testStartWorkflowSetupIdentityField() {
        $params = array('app_id' => '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4', 'field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4' ,'identifier_field' =>'id_field','id_field' => '2020', 'email' => 'brian@gmail.com', 'address1' => 'addr1', 'type' => 'INDIVIDUAL', "businessRole" => "Policy Holder",
          'address2' => "", 'city' => 'city', 'state' => 'state', 'country' => 'country', 'zip' => 2323 , 'firstname' => 'brian', 'lastname' => 'test');
        $processId = '8ddf83c0-4971-4bac-9bf7-49264db1172e';
        $this->setupMockProcessEngine();
        
        $result = $this->workflowInstanceService->startWorkflow($params);
        
        $this->performAsserts($params);

        $sqlQuery = 'SELECT u.id, up.firstname, up.lastname, up.email, u.orgid as org_id FROM ox_user u inner join ox_user_profile up on up.id = u.user_profile_id order by u.id DESC LIMIT 1';
        $newQueryResult = $this->runQuery($sqlQuery);
        $orgId = $newQueryResult[0]['org_id'];
        $sqlQuery = 'SELECT * FROM ox_organization where id = '.$orgId;
        $orgResult = $this->runQuery($sqlQuery);
        $sqlQuery = 'SELECT br.* FROM ox_org_business_role obr inner join ox_business_role br on obr.business_role_id = br.id where obr.org_id = '.$orgId;
        $bussRoleResult = $this->runQuery($sqlQuery);
        
        $this->assertEquals('brian',$newQueryResult[0]['firstname']);
        $this->assertEquals('test',$newQueryResult[0]['lastname']);
        $this->assertEquals('brian@gmail.com',$newQueryResult[0]['email']);
        $this->assertEquals($newQueryResult[0]['firstname']." ".$newQueryResult[0]['lastname'], $orgResult[0]['name']);
        $this->assertEquals($newQueryResult[0]['id'], $orgResult[0]['contactid']);
        $this->assertEquals($params['type'], $orgResult[0]['type']);
        $this->assertEquals($params['businessRole'], $bussRoleResult[0]['name']);
        $sqlQuery = "SELECT ar.* from ox_app_registry ar inner join ox_app a on a.id = ar.app_id 
                        where a.uuid = '".$params['app_id']."' AND org_id = $orgId";

        $result = $this->runQuery($sqlQuery);
        $this->assertEquals(1, count($result));
        $this->assertEquals(date('Y-m-d'), date_create($result[0]['date_created'])->format('Y-m-d'));
    }

    public function testStartWorkflowSetupIdentityFieldAsPolicyHolder() {
        AuthContext::put(AuthConstants::ORG_ID, 100);
        AuthContext::put(AuthConstants::ORG_UUID, 'fa371de7-0387-48ea-8f29-5d3704d96ac5');
        AuthContext::put(AuthConstants::USER_ID, 100);
        $params = array('app_id' => '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4', 'field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4' ,'identifier_field' =>'id_field','id_field' => '2020', 'email' => 'brian@gmail.com', 'address1' => 'addr1', 'type' => 'INDIVIDUAL', "businessRole" => "Policy Holder",
          'address2' => "", 'city' => 'city', 'state' => 'state', 'country' => 'country', 'zip' => 2323 , 'firstname' => 'brian', 'lastname' => 'test');
        $processId = '8ddf83c0-4971-4bac-9bf7-49264db1172e';
        $this->setupMockProcessEngine();
        
        $result = $this->workflowInstanceService->startWorkflow($params);
        $this->performAsserts($params);

        $sqlQuery = 'SELECT u.id, up.firstname, up.lastname, up.email, u.orgid as org_id FROM ox_user u inner join ox_user_profile up on up.id = u.user_profile_id order by u.id DESC LIMIT 1';
        $newQueryResult = $this->runQuery($sqlQuery);
        $orgId = $newQueryResult[0]['org_id'];
        $sqlQuery = 'SELECT * FROM ox_organization where id = '.$orgId;
        $orgResult = $this->runQuery($sqlQuery);
        $sqlQuery = 'SELECT br.* FROM ox_org_business_role obr inner join ox_business_role br on obr.business_role_id = br.id where obr.org_id = '.$orgId;
        $bussRoleResult = $this->runQuery($sqlQuery);
        
        $this->assertEquals('brian',$newQueryResult[0]['firstname']);
        $this->assertEquals('test',$newQueryResult[0]['lastname']);
        $this->assertEquals('brian@gmail.com',$newQueryResult[0]['email']);
        $this->assertEquals($newQueryResult[0]['firstname']." ".$newQueryResult[0]['lastname'], $orgResult[0]['name']);
        $this->assertEquals($newQueryResult[0]['id'], $orgResult[0]['contactid']);
        $this->assertEquals($params['type'], $orgResult[0]['type']);
        $this->assertEquals($params['businessRole'], $bussRoleResult[0]['name']);
        $sqlQuery = "SELECT ar.* from ox_app_registry ar inner join ox_app a on a.id = ar.app_id 
                        where a.uuid = '".$params['app_id']."' AND org_id = $orgId";

        $result = $this->runQuery($sqlQuery);
        $this->assertEquals(1, count($result));
        $this->assertEquals(date('Y-m-d'), date_create($result[0]['date_created'])->format('Y-m-d'));
    }

    public function testStartWorkflowWithWrongAppId() {
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4', 'app_id' => '8ab30b2d-d1da-427a-8e40-bc954b2b0f87');
        $this->setupMockProcessEngine();
        try {
            $result = $this->workflowInstanceService->startWorkflow($params);
        }
        catch(EntityNotFoundException $e) {
            $this->assertEquals('No workflow found for workflow '.$params['workflowId'],$e->getMessage());
        }
        $this->assertEquals($result,1);
    }

    public function testStartWorkflowWithCorrectAppId() {
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4', 'app_id' => '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4');
        $processId = '8ddf83c0-4971-4bac-9bf7-49264db1172e';
        $this->setupMockProcessEngine();
        $result = $this->workflowInstanceService->startWorkflow($params);
        $this->performAsserts($params);

    }

    public function testStartWorkflowWithoutWorkflowId() {
        $params = array('field1' => 1, 'field2' => 2);
        try{
            $result = $this->workflowInstanceService->startWorkflow($params);
        }
        catch(EntityNotFoundException $e) {
            $this->assertEquals('No workflow or workflow instance id provided', $e->getMessage());
        }
    }

    public function testStartWorkflowWithInvalidWorkflowId() {
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => 'b3d97877-9e1f-484a-907d-fb798179e43a');
        try{
            $result = $this->workflowInstanceService->startWorkflow($params);
        }
        catch(EntityNotFoundException $e) {
            $this->assertEquals('No workflow found for workflow '.$params['workflowId'], $e->getMessage());
        }
    }

    public function testStartWorkflowWithWorkflowId() {
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4');
        $processId = '8ddf83c0-4971-4bac-9bf7-49264db1172e';
        $this->setupMockProcessEngine();
        $result = $this->workflowInstanceService->startWorkflow($params);
        $this->performAsserts($params);
    }

    public function testStartWorkflowWithOrgId(){
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4', 'orgId' => 'b0971de7-0387-48ea-8f29-5d3704d96a46');
        $this->setupMockProcessEngine();
        $result = $this->workflowInstanceService->startWorkflow($params);
        $this->performAsserts($params);

    }

    public function testStartWorkflowWithoutOrgId(){
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4');
        $processId = '8ddf83c0-4971-4bac-9bf7-49264db1172e';
        if (enableCamunda == 0) {
            $mockProcessEngine = Mockery::mock('\Oxzion\Workflow\Camunda\ProcessEngineImpl');
            $workflowService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\WorkflowInstanceService::class);
            $mockProcessEngine->expects('startProcess')->withAnyArgs()->once()->andReturn(array('id' => $processId));
            $workflowService->setProcessEngine($mockProcessEngine);
        }
        $result = $this->workflowInstanceService->startWorkflow($params);
        $this->performAsserts($params);

    }

    public function testStartWorkflowWithCreatedBy(){
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4', 'created_by' => 'd9890624-8f42-4201-bbf9-675ec5dc8400');
        $this->setupMockProcessEngine();
        $result = $this->workflowInstanceService->startWorkflow($params);
        $this->performAsserts($params);
    }

    public function testStartWorkflowWithoutCreatedBy(){
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4');
        $this->setupMockProcessEngine();
        $result = $this->workflowInstanceService->startWorkflow($params);
        $this->performAsserts($params);
    }

    public function testStartWorkflowWithEntityId(){
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4', 'entity_id' => 2);
        $this->setupMockProcessEngine();
        $result = $this->workflowInstanceService->startWorkflow($params);
        $this->performAsserts($params);
    }

    public function testStartWorkflowWithoutEntityId(){
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4');
        $this->setupMockProcessEngine();
        $result = $this->workflowInstanceService->startWorkflow($params);
        $this->performAsserts($params);
    }

    public function testStartWorkflowCleanData() {
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4', 'uuid' => '31447b1d-c49a-4545-9b26-8d6873a0c5b9');
        $this->setupMockProcessEngine();
        $result = $this->workflowInstanceService->startWorkflow($params);
        $this->performAsserts($params);
    }

    public function testStartWorkflowWithParentWorkflowInstance() {
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4', 'parentWorkflowInstanceId' => 'd321b276-9e1c-4bdf-8238-7340f9599383');
        $this->setupMockProcessEngine();
        $result = $this->workflowInstanceService->startWorkflow($params);
        $this->performAsserts($params);
    }

    public function testStartWorkflowUpdateWorkflowInstanceScenario(){
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4');
        $this->setupMockProcessEngine();
        $result = $this->workflowInstanceService->startWorkflow($params);
        $this->performAsserts($params);
    }

}