<?php

namespace Oxzion\Service;

use Exception;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\EntityNotFoundException;
use Oxzion\InvalidParameterException;
use Oxzion\ServiceException;
use Oxzion\OxServiceException;
use Oxzion\Service\AbstractService;
use Oxzion\Service\FileService;
use Oxzion\Service\EntityService;
use Oxzion\Service\WorkflowService;
use Oxzion\Workflow\WorkFlowFactory;
use Oxzion\Model\WorkflowInstance;
use Oxzion\Model\WorkflowInstanceTable;
use Oxzion\Service\ActivityInstanceService;
use Oxzion\Service\RegistrationService;
use Oxzion\Utils\ArrayUtils;
use GuzzleHttp\Exception\RequestException;

class WorkflowInstanceService extends AbstractService
{
    protected $workflowService;
    protected $fileService;
    protected $processEngine;
    protected $activityEngine;
    protected $registratinService;
    protected $entityService;

    public function __construct(
        $config,
        $dbAdapter,
        WorkflowInstanceTable $table,
        FileService $fileService,
        EntityService $entityService,
        WorkflowService $workflowService,
        WorkflowFactory $workflowFactory,
        ActivityInstanceService $activityInstanceService,
        RegistrationService $registrationService
    ) {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
        $this->fileService = $fileService;
        $this->entityService = $entityService;
        $this->workflowService = $workflowService;
        $this->workFlowFactory = $workflowFactory;
        $this->processEngine = $this->workFlowFactory->getProcessEngine();
        $this->activityEngine = $this->workFlowFactory->getActivity();
        $this->activityInstanceService = $activityInstanceService;
        $this->registrationService = $registrationService;
    }
    public function setProcessEngine($processEngine)
    {
        $this->processEngine = $processEngine;
    }

    public function setActivityEngine($activityEngine)
    {
        $this->activityEngine = $activityEngine;
    }
    public function saveWorkflowInstance($appId, &$data)
    {
        $WorkflowInstance = new WorkflowInstance();
        $data['app_id'] = $appId;
        if (!isset($data['id'])) {
            $data['created_by'] = AuthContext::get(AuthConstants::USER_ID);
            $data['date_created'] = date('Y-m-d H:i:s');
        }
        $data['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_modified'] = date('Y-m-d H:i:s');
        $WorkflowInstance->exchangeArray($data);
        $WorkflowInstance->validate();
        $count = 0;
        $this->beginTransaction();
        try {
            $count = $this->table->save($WorkflowInstance);
            if ($count == 0) {
                $this->rollback();
                return 0;
            }
            if (!isset($data['id'])) {
                $id = $this->table->getLastInsertValue();
                $data['id'] = $id;
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            switch (get_class($e)) {
                case "Oxzion\ValidationException":
                    throw $e;
                    break;
                default:
                    return 0;
                    break;
            }
        }
        return $count;
    }
    public function updateWorkflowInstance($id, &$data)
    {
        $obj = $this->table->get($id, array());
        if (is_null($obj)) {
            return 0;
        }
        $data['id'] = $id;
        $data['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_modified'] = date('Y-m-d H:i:s');
        $file = $obj->toArray();
        $changedArray = array_merge($obj->toArray(), $data);
        $WorkflowInstance = new WorkflowInstance();
        $WorkflowInstance->exchangeArray($changedArray);
        $WorkflowInstance->validate();
        $count = 0;
        try {
            $this->beginTransaction();
            $count = $this->table->save($WorkflowInstance);
            if ($count == 0) {
                $this->rollback();
                return 0;
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
        return $count;
    }

    public function deleteWorkflowInstance($id)
    {
        $count = 0;
        try {
            $this->beginTransaction();
            $count = $this->table->delete($id);
            $this->logger->info("deleteWorkflowInstance - count - $count");
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }

        return $count;
    }

    public function getWorkflowInstances($appId = null, $filterArray = array())
    {
        try {
            $query = "select * from ox_workflow_instance where app_id=?";
            $queryParams = array($appId);
            $resultSet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
            return $resultSet;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }
    public function getWorkflowInstance($id)
    {
        try {
            $query = "SELECT oxi.id,oxi.process_instance_id ,oxi.app_id,oxi.account_id,ow.uuid as workflow_id 
            from ox_workflow_instance as oxi
            join ox_workflow_deployment as wd on wd.id = oxi.workflow_deployment_id
             join ox_workflow as ow on wd.workflow_id = ow.id
             where oxi.account_id=? and oxi.process_instance_id=?";

            // $query = "SELECT * from ox_workflow_instance where org_id=? and process_instance_id=?";
            $queryParams = array(AuthContext::get(AuthConstants::ACCOUNT_ID), $id);
            $resultSet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
            $this->logger->info("WorkflowInstance ----------" . print_r($resultSet, true));
            return $resultSet;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }


    public function getWorkflowInstanceById($id)
    {
        try {
            $query = "select * from ox_workflow_instance where id=?";
            $queryParams = array($id);
            $resultSet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
            return $resultSet;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }

    public function startWorkflow($params)
    {
        $this->logger->info("Starting StartWorkflow method params - " . json_encode($params));
        if (!isset($params['workflowId'])) {
            throw new EntityNotFoundException("No workflow or workflow instance id provided");
        }
        if (!isset($params['accountId'])) {
            $params['accountId'] = AuthContext::get(AuthConstants::ACCOUNT_UUID);
        }
        if (!isset($params['created_by'])) {
            $params['created_by'] = AuthContext::get(AuthConstants::USER_ID);
        }
        $workflowId = $params['workflowId'];

        if (!isset($params['app_id'])) {
            $params['app_id'] = null;
        }
        if (!isset($params['appId'])) {
            $params['appId'] = null;
        }

        $this->setupIdentityField($params);
        if (isset($params['CreateNewUser'])) {
            unset($params['CreateNewUser']);
        }
        if (isset($params['appId'])) {
            $params['app_id'] = $params['appId'];
            $params['appId'] = null;
        }
        $workflow = $this->workflowService->getWorkflow($workflowId, $params['appId']);
        if (empty($workflow)) {
            $this->logger->info("EMPTY WORKFLOW --- ");
            throw new EntityNotFoundException("No workflow found for workflow $workflowId");
        }
        $params['form_id'] = $workflow['form_id'];
        $activityId = $params['form_id'];
        if (!isset($params['app_id'])) {
            $params['app_id'] = $workflow['app_id'];
        }
        if (!isset($params['entity_id'])) {
            $params['entity_id'] = $workflow['entity_id'];
        }
        $workflowInstance = $this->setupWorkflowInstance($workflowId, null, $params);
        $this->logger->info("SETUP WORKFLOW RESPONSE DATA ----- " . print_r($workflowInstance, true));
        $params = $this->cleanData($params);
        $fileData = $params;
        $fileData['last_workflow_instance_id'] = $workflowInstance['id'];
        if (isset($workflowInstance['parent_workflow_instance_id'])) {
            $fileDataResult = $this->fileService->getFileByWorkflowInstanceId($workflowInstance['parent_workflow_instance_id'], false);
            if ($this->checkIfWorkflowInProgress($fileDataResult['last_workflow_instance_id'])) {
                throw new ServiceException("A Process is already underway for this file", "process.already.underway.for.file", OxServiceException::ERR_CODE_PRECONDITION_FAILED);
            }
            $oldFileData = json_decode($fileDataResult['data'], true);
            $fileData = array_merge($oldFileData, $fileData);
            $file = $this->fileService->updateFile($fileData, $fileDataResult['fileId']);
            if (!isset($fileData['uuid'])) {
                $fileData['uuid'] = $fileDataResult['fileId'];
            }
            $fileData['data'] = !isset($fileData['data']) ? $fileData : $fileData['data'];
        } else {
            if (isset($fileData['uuid'])) {
                $select  = "SELECT of.id, of.last_workflow_instance_id from ox_file as of join ox_workflow_instance as owi on owi.id = of.last_workflow_instance_id WHERE of.uuid = :fileId";
                $queryParams = array('fileId' => $fileData['uuid']);
                $result = $this->executeQueryWithBindParameters($select, $queryParams)->toArray();
                if (count($result) == 0) {
                    $file = $this->fileService->updateFile($fileData, $fileData['uuid']);
                    $fileData['data'] = !isset($fileData['data']) ? $fileData : $fileData['data'];
                } else {
                    if ($this->checkIfWorkflowInProgress($result[0]['last_workflow_instance_id'])) {
                        throw new ServiceException("A Process is already underway for this file", "process.already.underway.for.file", OxServiceException::ERR_CODE_PRECONDITION_FAILED);
                    }
                    unset($fileData['uuid']);
                }
            }
            if (!isset($fileData['uuid'])) {
                if ($this->checkIfWorkflowInProgress(null, $fileData)) {
                    throw new ServiceException("A Process is already underway for this file", "process.already.underway.for.file", OxServiceException::ERR_CODE_PRECONDITION_FAILED);
                }
                $file = $this->fileService->createFile($fileData);
            }
        }
        $this->logger->info("File created -" . json_encode($fileData));
        $params = $this->pruneFields($params, $workflowInstance['id']);
        $params['fileId'] = $fileData['uuid'];
        $params['workflow_instance_id'] = $workflowInstance['id'];
        $this->logger->info("Checking something" . print_r($workflow['process_definition_id'], true));
        $this->logger->info("Checking Params" . print_r($params, true));
        try {
            $workflowInstanceId = $this->processEngine->startProcess($workflow['process_definition_id'], $params);
        } catch (RequestException $e) {
            $this->rollbackWorkflowInstanceAndFile($params);
            throw $e;
        }
        $this->beginTransaction();
        try {
            $this->logger->info("WorkflowInstanceId created" . print_r($workflowInstanceId, true));
            $updateQuery = "UPDATE ox_workflow_instance SET process_instance_id=:process_instance_id, file_id=:fileId, start_data=:startData where id = :workflowInstanceId";
            $updateParams = array(
                'process_instance_id' => $workflowInstanceId['id'],
                'workflowInstanceId' => $workflowInstance['id'],
                'fileId' => $this->getIdFromUuid('ox_file', $params['fileId']),
                'startData' => is_array($fileData['data']) ? json_encode($fileData['data']) : $fileData['data']
            );
            $this->logger->info("Query1 - $updateQuery with Parametrs - " . print_r($updateParams, true));
            $update = $this->executeUpdateWithBindParameters($updateQuery, $updateParams);
            $this->commit();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            $this->rollback();
            throw $e;
        }
        $this->logger->info("file - " . print_r($file, true));
        return $file;
    }

    public function pruneFields($data, $workflowInstanceId)
    {
        if (ArrayUtils::isList($data)) {
            return $data;
        }
        if (!is_numeric($workflowInstanceId)) {
            $query = "select id from ox_workflow_instance where process_instance_id = :workflowInstanceId";
            $params = ["workflowInstanceId" => $workflowInstanceId];
            $workflowInstance = $this->executeQueryWithBindParameters($query, $params)->toArray();
            if (count($workflowInstance) > 0) {
                $workflowInstanceId = $workflowInstance[0]['id'];
            } else {
                return $data;
            }
        }
        $query = "SELECT wd.fields from ox_workflow_deployment wd 
                    inner join ox_workflow_instance wi on wi.workflow_deployment_id = wd.id
                    where wi.id = :workflowInstanceId";
        $params = ['workflowInstanceId' => $workflowInstanceId];
        $result = $this->executeQueryWithBindParameters($query, $params)->toArray();
        if (count($result) > 0 && $fields = $result[0]['fields']) {
            $fields = json_decode($fields, true);
            $fields[] = 'appId';
            $fields[] = 'app_id';
            $fields[] = 'workflowId';
            $fields[] = 'workflow_id';
            $fields[] = 'accountId';
            $fields[] = 'fileId';
            $fields[] = 'uuid';
            $fields[] = 'workflowInstanceId';
            $fields[] = 'workflow_instance_id';
            $newData = array();
            foreach ($fields as $value) {
                if (isset($data[$value])) {
                    $newData[$value] = $data[$value];
                }
            }
            $data = $newData;
        }

        return $data;
    }

    private function checkIfWorkflowInProgress($workflowInstanceId, $fileData = null)
    {
        if ($workflowInstanceId) {
            $query = "select id from ox_workflow_instance where id = :workflowInstanceId and status = 'In Progress'";
            $params = array("workflowInstanceId" => $workflowInstanceId);
            $result = $this->executeQueryWithBindParameters($query, $params);
        } elseif ($fileData && isset($fileData['identifier_field'])) {
            $query = "select wi.id from ox_file as f 
                      inner join ox_indexed_file_attribute fa on fa.file_id = f.id
                      inner join ox_field fd on fd.id = fa.field_id
                      inner join ox_workflow_instance wi on wi.id = f.last_workflow_instance_id
                      where fd.name = :identifierField and fa.field_value_text = :identifier and wi.status = 'In Progress'";
            $params = array(
                "identifierField" => $fileData['identifier_field'],
                "identifier" => $fileData[$fileData['identifier_field']]
            );
            $result = $this->executeQueryWithBindParameters($query, $params);
        } else {
            return false;
        }
        return count($result) == 1;
    }


    private function setupIdentityField($params)
    {
        $this->logger->info("setupIdentityField");
        if (isset($params['identifier_field'])) {
            $data = $params;
            $this->registrationService->registerAccount($data);
        }
    }

    private function rollbackWorkflowInstanceAndFile($params)
    {
        $this->logger->info("rollbackWorkflowInstanceAndFile");
        try {
            $workflowInstanceId = isset($params['workflow_instance_id']) ? $params['workflow_instance_id'] : null;
            $this->logger->info("rollbackWorkflowInstanceAndFile - WorkflowInstanceId - $workflowInstanceId");
            $selectQuery = "SELECT * from ox_workflow_instance where id=:id";
            $queryParams = array('id' => $workflowInstanceId);
            $workflowInstance = $this->executeQueryWithBindParameters($selectQuery, $queryParams)->toArray();
            $this->logger->info("rollbackWorkflowInstanceAndFile - WorkflowInstance" . print_r($workflowInstance, true));
            if (isset($workflowInstance[0]['parent_workflow_instance_id']) && !empty($workflowInstance[0]['parent_workflow_instance_id'])) {
                $updateQuery = "update ox_file set data = (select completion_data from ox_workflow_instance where id = :workflow_id), last_workflow_instance_id = :workflow_id where uuid = :id";
                $updateQueryWhere = array("id" => $params['fileId'], "workflow_id" => $workflowInstance[0]['parent_workflow_instance_id']);
                $updateResult = $this->executeUpdateWithBindParameters($updateQuery, $updateQueryWhere);
                $deleteQuery = "delete from ox_activity_instance where workflow_instance_id = $workflowInstanceId";
                $deleteResult = $this->executeUpdateWithBindParameters($deleteQuery);
                $this->deleteWorkflowInstance($workflowInstanceId);
            } else {
                $query = "update ox_file set last_workflow_instance_id = null where uuid = :id";
                $queryWhere = array("id" => $params['fileId']);
                $result = $this->executeUpdateWithBindParameters($query, $queryWhere);
                $this->deleteWorkflowInstance($workflowInstanceId);
                $fileId = $this->getIdFromUuid('ox_file', $params['fileId']);
                //Please note there is an issue with the prepared statement and cascaded delete which is why its written so primitively
                $queryWhere = array("fileId" => $fileId);
                $query = "delete from ox_indexed_file_attribute where file_id = $fileId";
                $result = $this->executeUpdateWithBindParameters($query);
                $query = "delete from ox_file_attribute where file_id = $fileId";
                $result = $this->executeUpdateWithBindParameters($query);
                $query = "delete from ox_file_document where file_id = $fileId";
                $result = $this->executeUpdateWithBindParameters($query);
                $query = "delete from ox_file where id = :fileId";
                $result = $this->executeUpdateWithBindParameters($query, $queryWhere);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function submitActivity($params)
    {
        $this->logger->info("submitActivity method - ".print_r($params, true));
        if (!isset($params['workflowInstanceId'])) {
            throw new InvalidParameterException("No workflow or workflow instance id provided");
        }
        if (!isset($params['activityInstanceId'])) {
            throw new InvalidParameterException("Activity instance id required");
        }
        if (!isset($params['accountId'])) {
            $params['accountId'] = AuthContext::get(AuthConstants::ACCOUNT_UUID);
        }

        // SADHITHA CHANGE CREATED BY TO SUBMITTED BY
        if (!isset($params['created_by'])) {
            $params['created_by'] = AuthContext::get(AuthConstants::USER_ID);
        }

        $workflowInstanceId = $params['workflowInstanceId'];
        $workflowInstance = $this->getWorkflowInstance($workflowInstanceId);
        $this->logger->info(WorkflowInstanceService::class . "Get WorkflowInstance -----" . print_r($workflowInstance, true));
        if (isset($workflowInstance[0])) {
            $workflowId = $workflowInstance[0]['workflow_id'];
            $workflow = $this->workflowService->getWorkflow($workflowId);
            $this->logger->info(WorkflowInstanceService::class . "Get Workflow -----" . print_r($workflow, true));
        } else {
            throw new EntityNotFoundException("workflow instance not found for $workflowInstanceId");
        }

        $activityInstance = $this->activityInstanceService->getActivityInstance($params['activityInstanceId'], $workflowInstanceId);
        $this->logger->info("Activity Instance Value - " . print_r($activityInstance, true));
        $activityId = $activityInstance['activity_instance_id'];
        if (!isset($params['app_id'])) {
            $params['app_id'] = $workflow['app_id'];
        }
        if (!isset($params['entity_id'])) {
            $params['entity_id'] = $workflow['entity_id'];
        }
        $params['workflow_instance_id'] = $workflowInstance[0]['id'];
        $query = "select ox_file.* from ox_file join ox_workflow_instance on ox_workflow_instance.file_id =  ox_file.id where ox_workflow_instance.id=?";
        $queryParams = array($workflowInstance[0]['id']);
        $existingFile = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
        if (isset($existingFile[0])) {
            $this->beginTransaction();
            try {
                $this->logger->info(WorkflowInstanceService::class . "FILE UPDATE-----" . print_r($existingFile, true));
                $file = $this->fileService->updateFile($params, $existingFile[0]['uuid']);
                $pruneParams = $this->pruneFields($params, $params['workflow_instance_id']);
                $workflowInstanceId = $this->activityEngine->completeActivity($activityId, $pruneParams);
                $this->beginTransaction();
                $updateQuery = "UPDATE ox_activity_instance SET completion_data=:completionData,submitted_date=:submittedDate,modified_by=:modifiedBy where workflow_instance_id=:workflowInstanceId and id = :activityInstanceId";
                $updateQueryParams = array(
                    'completionData' => json_encode($params), 'submittedDate' => date('Y-m-d H:i:s'), 'modifiedBy' => AuthContext::get(AuthConstants::USER_ID), 'workflowInstanceId' => $workflowInstance[0]['id'],
                    "activityInstanceId" => $activityInstance['id']
                );
                $updateQueryResult = $this->executeUpdateWithBindParameters($updateQuery, $updateQueryParams);
                $this->commit();
            } catch (Exception $e) {
                $this->rollback();
                throw new EntityNotFoundException("Unable to execute workflow" . $workflowInstanceId);
            }
        } else {
            throw new EntityNotFoundException("No data for workflow instance " . $workflowInstanceId);
        }
        $this->logger->info("Submit activity Completed- " . print_r($file, true));
        return $file;
    }

    public function rollbackActivityInstance($workflowInstanceId,$activityInstanceId){
        $this->logger->info("Rollback Activity Instance -- 'WorkflowInstanceId' : $workflowInstanceId,'ActivityInstanceId':$activityInstanceId");
        try{
            $updateQuery = "UPDATE ox_activity_instance SET completion_data = NULL,submitted_date = NULL,modified_by = NULL,status = 'In Progress' where workflow_instance_id=:workflowInstanceId and id = :activityInstanceId";   
            $updateQueryParams = array(
               'workflowInstanceId' => $workflowInstanceId,
                "activityInstanceId" => $activityInstanceId
            );
            $updateQueryResult = $this->executeUpdateWithBindParameters($updateQuery, $updateQueryParams);   
        }catch(Exception $e){
            throw $e;
        }
    }

    public function initiateWorkflow($data)
    {
        $this->logger->info("Workflow Instance Start" . print_r($data, true));
        try {
            if (isset($data['variables']) && isset($data['variables']['workflowId'])) {
                $workflowInstance = $this->setupWorkflowInstance($data['variables']['workflowId'], $data['processInstanceId'], $data['variables']);
            } else {
                $this->logger->info("Invalid Data ----- " . print_r($data, true));
                throw new InvalidParameterException("Invalid Data - " . json_encode($data));
            }
            $this->logger->info("Initiate Workflow Data ----- " . print_r($workflowInstance, true));
            return $workflowInstance;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            $this->logger->info("Workflow Instance Start Failed" . $e->getMessage() . "Trace ---- " . $e->getTraceAsString());
            throw $e;
        }
    }

    public function completeWorkflow($params)
    {
        $this->logger->info("Complete Workflow Params------" . print_r($params, true));
        $this->beginTransaction();
        try {
            $selectQuery = "SELECT ox_file.data from ox_file inner join ox_workflow_instance on ox_workflow_instance.file_id = ox_file.id where process_instance_id = :workflowInstanceId";
            $updateQueryParams = array('workflowInstanceId' => $params['processInstanceId']);
            $select = $this->executeQueryWithBindParameters($selectQuery, $updateQueryParams)->toArray();
            $this->logger->info("QUERY------- $selectQuery with params" . print_r($updateQueryParams, true));
            if (count($select) > 0) {
                $updateQuery = "UPDATE ox_workflow_instance owi join ox_file of on of.id = owi.file_id SET owi.status=:status,owi.completion_data=:fileData,owi.date_modified=:modifiedDate where process_instance_id = :workflowInstanceId";
                $updateParams = array('status' => 'Completed', 'workflowInstanceId' => $params['processInstanceId'], 'fileData' => $select[0]['data'], 'modifiedDate' => date('Y-m-d H:i:s'));
                $update = $this->executeUpdateWithBindParameters($updateQuery, $updateParams);
                $this->commit();
                return $update->getAffectedRows();
            } else {
                throw new ServiceException("Invalid entity property set", "workflow.instance.entity.not.set", OxServiceException::ERR_CODE_NOT_FOUND);
            }
        } catch (Exception $e) {
            $this->logger->info(ActivityInstanceService::class . "Workflow Instance Entry Failed" . $e->getMessage());
            $this->logger->error($e->getMessage(), $e);
            $this->rollback();
            throw $e;
        }
    }

    public function setupWorkflowInstance($workflowId, $processInstanceId = null, $params = null)
    {
        $this->logger->info("SET UP Workflow Instance --- " . print_r($params, true));
        $entityId = null;
        $query = "SELECT w.app_id, w.entity_id, wd.id 
                    from ox_workflow as w
                    inner join ox_workflow_deployment as wd on w.id = wd.workflow_id 
                    where w.uuid=:uuid and wd.latest=:latest";
        $queryParams = array("uuid" => $workflowId,"latest" => 1);
        $workflowResultSet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();

        if (isset($params['entity_name']) && !empty($params['entity_name'])) {
            $select  = "SELECT ox_app_entity.id from ox_app_entity 
                        WHERE ox_app_entity.name = :name";
            $queryParams = array('name' => $params['entity_name']);
            $result = $this->executeQueryWithBindParameters($select, $queryParams)->toArray();
            if (isset($result[0]['id'])) {
                $entityId = $result[0]['id'];
            } else {
                throw new ServiceException("Invalid entity property set", "workflow.instance.entity.not.set", OxServiceException::ERR_CODE_NOT_FOUND);
            }
        } elseif (isset($workflowResultSet) && !empty($workflowResultSet)) {
            $entityId = $workflowResultSet[0]['entity_id'];
        } else {
            throw new ServiceException("WorkFlow Instance entity failed to be set", "workflow.instance.entity.not.set", OxServiceException::ERR_CODE_NOT_FOUND);
        }
        $accountId = $this->entityService->getEntityOfferingAccount($entityId);
        if (!$accountId && isset($params['accountId'])) {
            if ($account = $this->getIdFromUuid('ox_account', $params['accountId'])) {
                $accountId = $account;
            } else {
                $accountId = $params['accountId'];
            }
        }
        if (!$accountId) {
            $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }
        $this->logger->info("SET UP Workflow Instance (accountID) --- " . $accountId);
        if (isset($params['created_by'])) {
            if ($userId = $this->getIdFromUuid('ox_user', $params['created_by'])) {
                $createdBy = $userId;
            } else {
                $createdBy = $params['created_by'];
            }
        } else {
            $createdBy = AuthContext::get(AuthConstants::USER_ID);
        }
        if ($processInstanceId) {
            $this->logger->info("SET UP Workflow Instance (ProcessInstanceID) --- " . $processInstanceId);
            if (isset($params['workflow_instance_id'])) {
                $fileId = isset($params['fileId']) ? $params['fileId'] : (isset($params['uuid']) ? $params['uuid'] : null);
                $updateParams = array('process_instance_id' => $processInstanceId, 'workflowInstanceId' => $params['workflow_instance_id']);
                $fileSet = "";
                if ($fileId) {
                    $fileSet = ", file_id = :fileId";
                    $updateParams["fileId"] = $this->getIdFromUuid('ox_file', $fileId);
                }
                $updateQuery = "UPDATE ox_workflow_instance SET process_instance_id=:process_instance_id $fileSet where id = :workflowInstanceId";
                $update = $this->executeUpdateWithBindParameters($updateQuery, $updateParams);
            }
            $query = "select * from ox_workflow_instance where process_instance_id=?";
            $queryParams = array($processInstanceId);
            $resultSet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
            if (count($resultSet) > 0) {
                $this->logger->info("SET UP Workflow Instance Result --- " . print_r($processInstanceId, true));
                return $resultSet[0];
            }
        }
        $this->logger->info("SET UP Workflow Instance (CREATE NEW WORKFLOW INSTANCE)");
        $form = new WorkflowInstance();
        $dateCreated = date('Y-m-d H:i:s');
        $query = "select w.app_id, w.entity_id, wd.id from ox_workflow as w
                    inner join ox_workflow_deployment as wd on w.id = wd.workflow_id 
                    where w.uuid=:uuid and wd.latest=:latest";
        $queryParams = array("uuid" => $workflowId, "latest" => 1);
        $workflowResultSet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
        $entityId = null;
        if (isset($params['entity_name']) && !empty($params['entity_name'])) {
            $select  = "SELECT ox_app_entity.id from ox_app_entity WHERE ox_app_entity.name = :name";
            $queryParams = array('name' => $params['entity_name']);
            $result = $this->executeQueryWithBindParameters($select, $queryParams)->toArray();
            if (isset($result[0]['id'])) {
                $entityId = $result[0]['id'];
            } else {
                throw new ServiceException("Invalid entity property set", "workflow.instance.failed", OxServiceException::ERR_CODE_PRECONDITION_FAILED);
            }
        } elseif (isset($workflowResultSet) && !empty($workflowResultSet)) {
            $entityId = $workflowResultSet[0]['entity_id'];
        } else {
            throw new ServiceException("WorkFlow Instance entity failed to be set", "workflow.instance.failed", OxServiceException::ERR_CODE_PRECONDITION_FAILED);
        }

        if (count($workflowResultSet)) {
            $data = array('workflow_deployment_id' => $workflowResultSet[0]['id'], 'app_id' => $workflowResultSet[0]['app_id'], 'account_id' => $accountId, 'process_instance_id' => $processInstanceId, 'status' => "In Progress", 'date_created' => $dateCreated, 'created_by' => $createdBy, 'entity_id' => $entityId);
            if (isset($params['parentWorkflowInstanceId'])) {
                $resultParentWorkflow = $this->getIdFromProcessInstanceId($params['parentWorkflowInstanceId']);
                if (count($resultParentWorkflow) > 0) {
                    $data['parent_workflow_instance_id'] = $resultParentWorkflow[0]['id'];
                }
            }
            $this->logger->info("WorkFlow Instance Insert DATA --- " . print_r($data, true));
            $form->exchangeArray($data);
            $this->logger->info("WorkFlow Instance Form DATA --- " . print_r($form, true));
            $form->validate();
            $this->beginTransaction();
            try {
                $count = $this->table->save($form);
                $this->logger->info("WorkFlow Instance Form DATA INSERTED--- " . print_r($count, true));
                if ($count == 0) {
                    throw new ServiceException("WorkFlow Instance Create Failed", "workflow.instance.failed", OxServiceException::ERR_CODE_PRECONDITION_FAILED);
                }
                $this->commit();
                $id = $this->table->getLastInsertValue();
                $data['id'] = $id;
                $this->logger->info("SET UP WORKFLOW DATA--- " . print_r($data, true));
            } catch (Exception $e) {
                $this->logger->info("SET UP WORKFLOW Exception -- " . $e->getMessage() . " Trace -- " . $e->getTraceAsString());
                $this->rollback();
                $this->logger->error($e->getMessage(), $e);
                throw $e;
            }
            return $data;
        }
    }

    private function getIdFromProcessInstanceId($processInstanceId)
    {
        $query = "Select id from ox_workflow_instance where process_instance_id=:processInstanceId;";
        $params = array("processInstanceId" => $processInstanceId);
        return $result = $this->executeQueryWithBindParameters($query, $params)->toArray();
    }

    private function cleanData($params)
    {
        unset($params['submit']);
        unset($params['controller']);
        unset($params['method']);
        unset($params['action']);
        unset($params['access']);
        return $params;
    }

    public function claimActivityInstance(&$data)
    {
        $result = $this->activityInstanceService->claimActivityInstance($data);
        return $result;
    }

    public function getActivityInstanceForm(&$data)
    {
        $result = $this->activityInstanceService->getActivityInstanceForm($data);
        return $result;
    }

    public function getActivityLog($fileId, $appId, $filterParams = null)
    {
        $selectQuery = "select * from ((SELECT owi.process_instance_id as workflowInstanceId, ow.name as workflowName,oai.start_date, owi.date_created as workflowInstanceCreatedDate,owi.date_modified as workflowInstanceSubmissionDate,owi.created_by,ouu.name as ModifiedBy,ou.name as activityModifiedBy,oa.name as activityName, oai.submitted_date as activitySubmittedDate,oai.modified_by,oai.activity_instance_id as activityInstanceId from ox_workflow_instance owi inner join ox_workflow_deployment owd on owd.id = owi.workflow_deployment_id inner join ox_workflow ow on ow.id = owd.workflow_id inner join ox_activity_instance oai on oai.workflow_instance_id = owi.id inner join ox_activity oa on oa.id = oai.activity_id inner join ox_file of on of.id = owi.file_id inner join ox_user ou on ou.id = oai.modified_by inner join ox_user ouu on ouu.id = owi.created_by where of.uuid = :fileId and owi.app_id = :appId) UNION (SELECT owi.process_instance_id as workflowInstanceId, ow.name as workflowName, owi.date_created, owi.date_created as workflowInstanceCreatedDate, owi.date_modified as workflowInstanceSubmissionDate, owi.created_by,ouu.name as ModifiedBy,ouu.name as activityModifiedBy,'Initiated' as activityName , owi.date_created as activitySubmittedDate,owi.created_by,'' as activityInstanceId from ox_workflow_instance owi inner join ox_workflow_deployment owd on owd.id = owi.workflow_deployment_id inner join ox_workflow ow on ow.id = owd.workflow_id inner join ox_file of on of.id = owi.file_id inner join ox_user ouu on ouu.id = owi.created_by where of.uuid = :fileId and owi.app_id = :appId)) x order by start_date asc";
        $selectQueryParams = array('fileId' => $fileId, 'appId' => $this->getIdFromUuid('ox_app', $appId));
        $result = $this->executeQueryWithBindParameters($selectQuery, $selectQueryParams)->toArray();
        return $result;
    }

    public function getWorkflowSubmissionData($workflowInstanceId)
    {
        try {
            $selectQuery = "SELECT of.data from ox_file of
                            INNER JOIN ox_workflow_instance owi on owi.file_id = of.id where owi.process_instance_id = :workflowInstanceId ";
            $selectQueryParams = array('workflowInstanceId' => $workflowInstanceId);
            $result = $this->executeQueryWithBindParameters($selectQuery, $selectQueryParams)->toArray();
            if (isset($result[0])) {
                return $result[0];
            } else {
                return;
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }

    public function getWorkflowChangeLog($workflowInstanceId, $labelMapping = null)
    {
        $selectQuery = "SELECT owi.start_data, owi.file_id ,pwi.completion_data as initialData from ox_workflow_instance owi left join ox_workflow_instance as pwi on owi.parent_workflow_instance_id = pwi.id where owi.process_instance_id = :workflowInstanceId ";
        $selectQueryParams = array('workflowInstanceId' => $workflowInstanceId);
        $result = $this->executeQueryWithBindParameters($selectQuery, $selectQueryParams)->toArray();
        if (count($result) > 0) {
            $recordSet = $this->fileService->getWorkflowInstanceByFileId($this->getUuidFromId('ox_file', $result[0]['file_id'])); // get entityId
            $startData = json_decode($result[0]['initialData'], true);
            $completionData = json_decode($result[0]['start_data'], true);
            $resultData = $this->fileService->getChangeLog($recordSet[0]['entity_id'], $startData, $completionData, $labelMapping);
            return $resultData;
        } else {
            return $result;
        }
    }

    public function getWorkflowInstanceDataFromFileId($fileId)
    {
        $select = "SELECT oxwi.id,start_data,completion_data,parent_workflow_instance_id from ox_workflow_instance oxwi inner join ox_file on ox_file.id = oxwi.file_id where ox_file.uuid=:fileId";
        $params = array("fileId" => $fileId);
        $result = $this->executeQuerywithBindParameters($select, $params)->toArray();
        if (count($result) == 0) {
            return 0;
        }
        return $result;
    }
    public function getWorkflowCompletedData($params, $filterParams)
    {
        $createdFilter = "";
        $pageSize = "";
        $offset = "";
        $entityFilter = "";
        $field = "";
        $sort = "";
        $queryParams['workflowStatus'] = $params['workflowStatus'];
        try {
            if (isset($params['gtCreatedDate'])) {
                $createdFilter .= " wi.date_modified >= :gtCreatedDate AND ";
                $params['gtCreatedDate'] = str_replace('-', '/', $params['gtCreatedDate']);
                $queryParams['gtCreatedDate'] = date('Y-m-d', strtotime($params['gtCreatedDate']));
            }
            if (isset($params['ltCreatedDate'])) {
                $createdFilter .= " wi.date_modified < :ltCreatedDate AND ";
                $params['ltCreatedDate'] = str_replace('-', '/', $params['ltCreatedDate']);
                $queryParams['ltCreatedDate'] = date('Y-m-d', strtotime($params['ltCreatedDate'] . "+1 days"));
            }
            $filterlogic = " ";
            if (isset($params['entityName'])) {
                $this->fileService->getEntityFilter($params, $entityFilter, $queryParams);
                $entityFilter = rtrim($entityFilter, " AND ");
                $filterlogic = isset($filterParams[0]['filter']['logic']) ? $filterParams[0]['filter']['logic'] : " AND ";
            }
            $whereQuery = " $createdFilter $entityFilter $filterlogic";
            $fromQuery = "from ox_file as of 
                inner join ox_indexed_file_attribute fa on fa.file_id = of.id
                inner join ox_field fd on fd.id = fa.field_id
                inner join ox_workflow_instance wi on wi.file_id = of.id
                inner join ox_workflow_deployment owd on owd.id = wi.workflow_deployment_id 
              	inner join ox_workflow ow on ow.id = owd.workflow_id 
                inner join ox_app_entity as en on en.id = of.entity_id";
            if (isset($filterParams)) {
                $this->fileService->processFilterParams($fromQuery, $whereQuery, $sort, $pageSize, $offset, $field, $filterParams);
            }
            $whereQuery = rtrim($whereQuery, " AND ");
            $where = trim($whereQuery) != "" ? "WHERE $whereQuery AND wi.status = :workflowStatus AND wi.completion_data IS NOT NULL" : "";
            $where = rtrim($where, " AND ");
            $selectQuery = "SELECT DISTINCT SQL_CALC_FOUND_ROWS wi.completion_data,wi.date_modified ,ow.name $field $fromQuery $where $sort";
            $this->logger->info("Executing query - $selectQuery with params - " . json_encode($queryParams));
            $resultSet = $this->executeQueryWithBindParameters($selectQuery, $queryParams)->toArray();
            $countQuery = "SELECT FOUND_ROWS();";
            $this->logger->info("Executing query - $countQuery with params - " . json_encode($queryParams));
            $countResultSet = $this->executeQueryWithBindParameters($countQuery, $queryParams)->toArray();

            if ($resultSet) {
                $i = 0;
                foreach ($resultSet as $file) {
                    if ($file['completion_data']) {
                        $content = json_decode($file['completion_data'], true);
                        if ($content) {
                            $resultSet[$i] = array_merge($file, $content);
                            if ($file['name']) {
                                $resultSet[$i]['workflow_name'] = $file['name'];
                            }
                            if ($file['date_modified']) {
                                $resultSet[$i]['modifiedDate'] = $file['date_modified'];
                            }
                        }
                    }
                    $i++;
                }
            }
            return array('data' => $resultSet, 'total' => $countResultSet[0]['FOUND_ROWS()']);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }
}
