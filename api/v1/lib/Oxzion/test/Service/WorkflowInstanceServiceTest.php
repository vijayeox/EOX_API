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
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\DbUnit\DataSet\ArrayDataSet;
use Oxzion\Utils\ArrayUtils;

class WorkflowInstanceServiceTest extends AbstractServiceTest
{
    public $adapter = null;
    private $processId = '8ddf83c0-4971-4bac-9bf7-49264db1172e';

    protected function setUp(): void
    {
        $this->loadConfig();
        parent::setUp();
        $this->workflowInstanceService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\WorkflowInstanceService::class);
        AuthContext::put(AuthConstants::ACCOUNT_ID, 1);
        AuthContext::put(AuthConstants::ACCOUNT_UUID, '53012471-2863-4949-afb1-e69b0891c98a');
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
        switch ($this->getName()) {
            case "testStartWorkflowSetupIdentityField":
            case "testStartWorkflowSetupIdentityFieldAsPolicyHolder":
                $dataset->addYamlFile(dirname(__FILE__) . "/Dataset/businessRole.yml");
                break;
        }
        $dataset->addYamlFile(dirname(__FILE__) . "/../../../../module/User/test/Dataset/User.yml");
        $customDataSet = array();
        $oxRole = "";
        $keys= array();
        $tempSet = array();
        foreach ($dataset as $k => $value) {
            if (in_array($k, $keys)) {
                print_r($k);
                exit;
            } else {
                $keys[] = $k;
            }
        }
        foreach ($dataset as $k => $value) {
            $columns = $value->getTableMetaData()->getColumns();
            $tblName = $value->getTableMetaData()->getTableName();
            $rowCount = $value->getRowCount();
            $tableValues = array();
            for ($i = 0; $i < $rowCount; $i++) {
                foreach ($columns as $columnName) {
                    $tableValues[$i][$columnName] = $value->getValue($i, $columnName);
                }
            }
            $customDataSet[$tblName] = $tableValues;
        }
        $finalDataSet = ArrayUtils::moveKeyBefore($customDataSet, 'ox_role', 'ox_business_role');
        return new ArrayDataSet($finalDataSet);
    }

    private function performAsserts($params)
    {
        $accountId = 1;
        if (isset($params['accountId'])) {
            $sqlQuery = "SELECT id from ox_account where uuid = '".$params['accountId']."'";
            $queryResult = $this->runQuery($sqlQuery);
            $accountId = $queryResult[0]['id'];
        }
        $sqlQuery = "SELECT * FROM ox_workflow_instance where process_instance_id = '".$this->processId."'";
        $queryResult = $this->runQuery($sqlQuery);
        $this->assertEquals(1, count($queryResult));
        $sqlQuery = "SELECT * FROM ox_file where id = ".$queryResult[0]['file_id'];
        $fileResult = $this->runQuery($sqlQuery);
        $this->assertEquals(1, count($fileResult));
        $this->assertEquals($accountId, $queryResult[0]['account_id']);
        $this->assertEquals(99, $queryResult[0]['app_id']);
        $this->assertEquals('In Progress', $queryResult[0]['status']);
        switch ($this->getName()) {
            case "testStartWorkflowWithCreatedBy":
                $this->assertEquals(51, $queryResult[0]['created_by']);
                break;
            default:
                $this->assertEquals(AuthContext::get(AuthConstants::USER_ID), $queryResult[0]['created_by']);
                break;
        }
        $startData = json_decode($fileResult[0]['data'], true);
        if (isset($startData['entity_id'])) {
            $this->assertEquals(1, $startData['entity_id']);
            unset($startData['entity_id']);
        }
        if (isset($startData['start_date']) || empty($startData['status'])) {
            unset($startData['start_date']);
        }
        if (isset($startData['end_date']) || empty($startData['status'])) {
            unset($startData['end_date']);
        }
        if (isset($startData['status']) || empty($startData['status'])) {
            unset($startData['status']);
        }
        $fileResult[0]['data'] = json_encode($startData);
        // $this->assertEquals($fileResult[0]['data'], $queryResult[0]['start_data']);
        $this->assertEquals(1, $queryResult[0]['entity_id']);
        $data = json_decode($fileResult[0]['data'], true);
        foreach ($params as $key => $value) {
            switch ($key) {
                case 'workflowId':
                case 'app_id':
                case 'appId':
                case 'entity_id':
                case 'uuid':
                case 'accountId':
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

    private function setupMockProcessEngine()
    {
        if (enableCamunda == 0) {
            $mockProcessEngine = Mockery::mock('\Oxzion\Workflow\Camunda\ProcessEngineImpl');
            $workflowService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\WorkflowInstanceService::class);
            $mockProcessEngine->expects('startProcess')->withAnyArgs()->once()->andReturn(array('id' => $this->processId));
            $workflowService->setProcessEngine($mockProcessEngine);
        }
    }

    private function runQuery($query)
    {
        $statement = $this->adapter->query($query);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        return $result;
    }

    public function testStartWorkflowSetupIdentityField()
    {
        $params = array('appId' => '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4', 'field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4' ,'identifier_field' =>'id_field','id_field' => '2020', 'email' => 'brian@gmail.com', 'address1' => 'addr1', 'type' => 'INDIVIDUAL', "businessRole" => "Policy Holder",
          'address2' => "", 'city' => 'city', 'state' => 'state', 'country' => 'country', 'zip' => 2323 , 'firstname' => 'brian', 'lastname' => 'test');
        $processId = '8ddf83c0-4971-4bac-9bf7-49264db1172e';
        $this->setupMockProcessEngine();
        
        $result = $this->workflowInstanceService->startWorkflow($params);
        
        $this->performAsserts($params);

        $sqlQuery = 'SELECT u.id, up.firstname, up.lastname, up.email, u.account_id FROM ox_user u inner join ox_person up on up.id = u.person_id order by u.id DESC LIMIT 1';
        $newQueryResult = $this->runQuery($sqlQuery);
        $this->assertEquals('brian', $newQueryResult[0]['firstname']);
        $this->assertEquals('test', $newQueryResult[0]['lastname']);
        $this->assertEquals('brian@gmail.com', $newQueryResult[0]['email']);

        $accountId = $newQueryResult[0]['account_id'];
        $sqlQuery = 'SELECT * FROM ox_account where id = '.$accountId;
        $acctResult = $this->runQuery($sqlQuery);
        $this->assertEquals($newQueryResult[0]['firstname']." ".$newQueryResult[0]['lastname'], $acctResult[0]['name']);
        $this->assertEquals($newQueryResult[0]['id'], $acctResult[0]['contactid']);
        $this->assertEquals($params['type'], $acctResult[0]['type']);

        $sqlQuery = 'SELECT * FROM ox_account_user where account_id = '.$accountId.' and user_id = '.$newQueryResult[0]['id'];
        $acctUserResult = $this->runQuery($sqlQuery);
        $this->assertEquals(1, count($acctUserResult));

        $sqlQuery = 'SELECT br.* FROM ox_account_business_role obr inner join ox_business_role br on obr.business_role_id = br.id where obr.account_id = '.$accountId;
        $bussRoleResult = $this->runQuery($sqlQuery);
        $this->assertEquals($params['businessRole'], $bussRoleResult[0]['name']);

        $sqlQuery = 'SELECT oxr.* FROM ox_role oxr inner join ox_account_business_role obr on obr.business_role_id = oxr.business_role_id and oxr.account_id = "'.$accountId.'" where obr.account_id = '.$accountId;
        $roleResult = $this->runQuery($sqlQuery);
        $this->assertEquals(1, count($roleResult));
        $this->assertEquals('Policy Holder', $roleResult[0]['name']);

        $sqlQuery = 'SELECT oxrp.* FROM ox_role_privilege oxrp where oxrp.role_id = '.$roleResult[0]['id'];
        $rolePrivResult = $this->runQuery($sqlQuery);
        $this->assertEquals(1, count($rolePrivResult));
        $this->assertEquals('MANAGE MY POLICY', $rolePrivResult[0]['privilege_name']);
        $this->assertEquals(3, $rolePrivResult[0]['permission']);
        $this->assertEquals($accountId, $rolePrivResult[0]['account_id']);
        $this->assertEquals(99, $rolePrivResult[0]['app_id']);

        $sqlQuery = "SELECT * from ox_user_role where role_id= ".$roleResult[0]['id']."  and account_user_id=".$acctUserResult[0]['id'];
        $userRoleResult = $this->runQuery($sqlQuery);
        $this->assertEquals(1, count($userRoleResult));
        $this->assertEquals($roleResult[0]['id'], $userRoleResult[0]['role_id']);
        $this->assertEquals($acctUserResult[0]['id'], $userRoleResult[0]['account_user_id']);

        $sqlQuery = "SELECT ar.* from ox_app_registry ar inner join ox_app a on a.id = ar.app_id 
                        where a.uuid = '".$params['appId']."' AND account_id = $accountId";

        $result = $this->runQuery($sqlQuery);
        $this->assertEquals(1, count($result));
        $this->assertEquals(date('Y-m-d'), date_create($result[0]['date_created'])->format('Y-m-d'));
    }

    public function testStartWorkflowWithWrongAppId()
    {
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4', 'app_id' => '8ab30b2d-d1da-427a-8e40-bc954b2b0f87');
        $this->setupMockProcessEngine();
        try {
            $result = $this->workflowInstanceService->startWorkflow($params);
        } catch (EntityNotFoundException $e) {
            $this->assertEquals('No workflow found for workflow '.$params['workflowId'], $e->getMessage());
        }
        $this->assertEquals($result, 1);
    }

    public function testStartWorkflowWithCorrectAppId()
    {
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4', 'app_id' => '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4');
        $processId = '8ddf83c0-4971-4bac-9bf7-49264db1172e';
        $this->setupMockProcessEngine();
        $result = $this->workflowInstanceService->startWorkflow($params);
        $this->performAsserts($params);
    }

    public function testStartWorkflowWithoutWorkflowId()
    {
        $params = array('field1' => 1, 'field2' => 2);
        try {
            $result = $this->workflowInstanceService->startWorkflow($params);
        } catch (EntityNotFoundException $e) {
            $this->assertEquals('No workflow or workflow instance id provided', $e->getMessage());
        }
    }

    public function testStartWorkflowWithInvalidWorkflowId()
    {
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => 'b3d97877-9e1f-484a-907d-fb798179e43a');
        try {
            $result = $this->workflowInstanceService->startWorkflow($params);
        } catch (EntityNotFoundException $e) {
            $this->assertEquals('No workflow found for workflow '.$params['workflowId'], $e->getMessage());
        }
    }

    public function testStartWorkflowWithWorkflowId()
    {
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4');
        $processId = '8ddf83c0-4971-4bac-9bf7-49264db1172e';
        $this->setupMockProcessEngine();
        $result = $this->workflowInstanceService->startWorkflow($params);
        $this->performAsserts($params);
    }

    public function testStartWorkflowWithAccountId()
    {
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4', 'accountId' => 'b0971de7-0387-48ea-8f29-5d3704d96a46');
        $this->setupMockProcessEngine();
        $result = $this->workflowInstanceService->startWorkflow($params);
        $this->performAsserts($params);
    }

    public function testStartWorkflowWithoutAccountId()
    {
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

    public function testStartWorkflowWithCreatedBy()
    {
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4', 'created_by' => 'd9890624-8f42-4201-bbf9-675ec5dc8400');
        $this->setupMockProcessEngine();
        $result = $this->workflowInstanceService->startWorkflow($params);
        $this->performAsserts($params);
    }

    public function testStartWorkflowWithoutCreatedBy()
    {
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4');
        $this->setupMockProcessEngine();
        $result = $this->workflowInstanceService->startWorkflow($params);
        $this->performAsserts($params);
    }

    public function testStartWorkflowWithEntityId()
    {
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4', 'entity_id' => 2);
        $this->setupMockProcessEngine();
        $result = $this->workflowInstanceService->startWorkflow($params);
        $this->performAsserts($params);
    }

    public function testStartWorkflowWithoutEntityId()
    {
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4');
        $this->setupMockProcessEngine();
        $result = $this->workflowInstanceService->startWorkflow($params);
        $this->performAsserts($params);
    }

    public function testStartWorkflowCleanData()
    {
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4', 'uuid' => '31447b1d-c49a-4545-9b26-8d6873a0c5b9');
        $this->setupMockProcessEngine();
        $result = $this->workflowInstanceService->startWorkflow($params);
        $this->performAsserts($params);
    }

    public function testStartWorkflowWithParentWorkflowInstance()
    {
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4', 'parentWorkflowInstanceId' => 'd321b276-9e1c-4bdf-8238-7340f9599383');
        $this->setupMockProcessEngine();
        $result = $this->workflowInstanceService->startWorkflow($params);
        $this->performAsserts($params);
    }

    public function testStartWorkflowUpdateWorkflowInstanceScenario()
    {
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4');
        $this->setupMockProcessEngine();
        $result = $this->workflowInstanceService->startWorkflow($params);
        $this->performAsserts($params);
    }

    public function testStartWorkFlowWithTimeoutError()
    {
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4');
        if (enableCamunda == 0) {
            $mockProcessEngine = Mockery::mock('\Oxzion\Workflow\Camunda\ProcessEngineImpl');
            $workflowService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\WorkflowInstanceService::class);
            $req = new Request('GET', '/');
            $prev = new \Exception();
            $mockProcessEngine->expects('startProcess')->withAnyArgs()->once()->andThrow(new ConnectException('error', $req, $prev, ['foo' => 'bar']));
            $workflowService->setProcessEngine($mockProcessEngine);
        }
        $this->expectException(ConnectException::class);
        $result = $this->workflowInstanceService->startWorkflow($params);
    }

    public function testStartWorkFlowWithTimeoutWithNoParentWorkflow()
    {
        // CHECK TIMEOUT ON NEW WORKFLOW WITH NO PARENT
        $this->getTransactionManager()->setForceRollback(true); //Ensures rollback is not used
        $query = "SELECT 'ox_file' AS table_name, COUNT(*) as count FROM ox_file where ox_file.is_active=1
                    UNION
                SELECT 'ox_workflow_instance' AS table_name, COUNT(*) as count FROM ox_workflow_instance";
        $queryResult = $this->runQuery($query);
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4');
        if (enableCamunda == 0) {
            $mockProcessEngine = Mockery::mock('\Oxzion\Workflow\Camunda\ProcessEngineImpl');
            $workflowService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\WorkflowInstanceService::class);
            $req = new Request('GET', '/');
            $prev = new \Exception();
            $mockProcessEngine->expects('startProcess')->withAnyArgs()->once()->andThrow(new ConnectException('error', $req, $prev, ['foo' => 'bar']));
            $workflowService->setProcessEngine($mockProcessEngine);
        }
        try {
            $result = $this->workflowInstanceService->startWorkflow($params);
        } catch (ConnectException $e) {
            $queryResultAfterExecution = $this->runQuery($query);
            $this->assertEquals([], array_diff_key($queryResult, $queryResultAfterExecution));
        }
    }

    public function testStartWorkFlowWithTimeoutUsingParentWorkflow()
    {
        // CHECK TIMEOUT ON NEW WORKFLOW WITH NO PARENT
        $this->getTransactionManager()->setForceRollback(true);
        $query = "SELECT 'ox_file' AS table_name, COUNT(*) as count FROM ox_file where ox_file.is_active=1
                    UNION
                SELECT 'ox_workflow_instance' AS table_name, COUNT(*) as count FROM ox_workflow_instance";
        $queryResult = $this->runQuery($query);
        $updateQuery = "update ox_workflow_instance set completion_data = 'COMPLETION' where id = 1";
        $this->executeUpdate($updateQuery);
        $params = array('field1' => 1, 'field2' => 2, 'workflowId' => '1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4','uuid' => 'd13d0c68-98c9-11e9-adc5-308d99c9145b','parentWorkflowInstanceId' => '3f20b5c5-0124-11ea-a8a0-22e8105c0778');
        if (enableCamunda == 0) {
            $mockProcessEngine = Mockery::mock('\Oxzion\Workflow\Camunda\ProcessEngineImpl');
            $workflowService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\WorkflowInstanceService::class);
            $req = new Request('GET', '/');
            $prev = new \Exception();
            $mockProcessEngine->expects('startProcess')->withAnyArgs()->once()->andThrow(new ConnectException('error', $req, $prev, ['foo' => 'bar']));
            $workflowService->setProcessEngine($mockProcessEngine);
        }
        try {
            $result = $this->workflowInstanceService->startWorkflow($params);
        } catch (ConnectException $e) {
            $queryResultAfterExecution = $this->runQuery($query);
            $this->assertEquals([], array_diff_key($queryResult, $queryResultAfterExecution));
            $fileDataQuery = "Select data from ox_file where last_workflow_instance_id = 1";
            $fileDataQueryResult = $this->runQuery($fileDataQuery);
            $this->assertEquals('COMPLETION', $fileDataQueryResult[0]['data']);
        }
    }


    public function testSubmitActivityWithTimeoutError()
    {
        $params = array('field1' => 1, 'field2' => 2, 'fileId' => 'f13d0c68-98c9-11e9-adc5-308d99c91478' ,'activityInstanceId' => '346622fd-0124-11ea-a8a0-22e8105c0766','workflowInstanceId' => '3f20b5c5-0124-11ea-a8a0-22e8105c0778');
        if (enableCamunda == 0) {
            $mockActivityEngine = Mockery::mock('\Oxzion\Workflow\Camunda\ActivityImpl');
            $workflowService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\WorkflowInstanceService::class);
            $req = new Request('GET', '/');
            $prev = new \Exception();
            $mockActivityEngine->expects('completeActivity')->withAnyArgs()->once()->andThrow($prev);
            $workflowService->setActivityEngine($mockActivityEngine);
        }
        try {
            $result = $this->workflowInstanceService->submitActivity($params);
        } catch (Exception $e) {
            $this->assertEquals('Unable to execute workflow'.$params['workflowInstanceId'], $e->getMessage());
        }
    }

    public function testSubmitActivityTimeoutError()
    {
        $params = array('field1' => 1, 'field2' => 2, 'fileId' => 'f13d0c68-98c9-11e9-adc5-308d99c91478' ,'activityInstanceId' => '346622fd-0124-11ea-a8a0-22e8105c0766');
        if (enableCamunda == 0) {
            $mockActivityEngine = Mockery::mock('\Oxzion\Workflow\Camunda\ActivityImpl');
            $workflowService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\WorkflowInstanceService::class);
            $prev = new \Exception();
            $mockActivityEngine->expects('completeActivity')->withAnyArgs()->once()->andThrow(new Exception($prev));
            $workflowService->setActivityEngine($mockActivityEngine);
        }
        try {
            $result = $this->workflowInstanceService->submitActivity($params);
        } catch (Exception $e) {
            $fileDataQuery = "Select status from ox_activity_instance where activity_instance_id = '".$params['activityInstanceId']."'";
            $fileDataQueryResult = $this->runQuery($fileDataQuery);
            $this->assertEquals('In Progress', $fileDataQueryResult[0]['status']);
        }
    }
}
