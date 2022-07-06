<?php
/**
 * File Api
 */
namespace Oxzion\Service;

use Exception;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\EntityNotFoundException;
use Oxzion\InvalidParameterException;
use Oxzion\AccessDeniedException;
use Oxzion\ServiceException;
use Oxzion\Model\ActivityInstance;
use Oxzion\Model\ActivityInstanceTable;
use Oxzion\Service\AbstractService;
use Oxzion\Workflow\WorkFlowFactory;
use Oxzion\Service\FileService;

class ActivityInstanceService extends AbstractService
{
    /**
     * @var ActivityInstanceService Instance of Task Service
     */
    private $fileExt = ".json";
    protected $workflowFactory;
    protected $activityEngine;
    protected $fileService;
    /**
     * @ignore __construct
     */

    public function __construct($config, $dbAdapter, ActivityInstanceTable $table, WorkflowFactory $workflowFactory, FileService $fileService)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
        $this->formsFolder = $this->config['FORM_FOLDER'];
        $this->workFlowFactory = $workflowFactory;
        $this->activityEngine = $this->workFlowFactory->getActivity();
        $this->fileService = $fileService;
    }

    public function setActivityEngine($activityEngine)
    {
        $this->activityEngine = $activityEngine;
    }
    public function getActivityInstanceForm($data)
    {
        $selectQuery = "SELECT oxa.* from ox_file_assignee as oxa
                        join ox_activity_instance as oxi on oxa.activity_instance_id = oxi.id
                        LEFT JOIN ox_user_team as oug on oxa.team_id = oug.team_id
                        LEFT JOIN ox_user_role as our on oxa.role_id = our.role_id
                        LEFT JOIN ox_account_user au on au.id = our.account_user_id
                        WHERE oxi.status = 'In Progress' and oxi.activity_instance_id =:activityInstanceId AND
                        (oxa.user_id =:userId OR oug.avatar_id =:userId OR au.user_id = :userId)";
        $queryParams = array("activityInstanceId" => $data['activityInstanceId'], 'userId' => AuthContext::get(AuthConstants::USER_ID));
        $this->logger->info("Executing query - ". $selectQuery . " with params - " . json_encode($queryParams));
        $result = $this->executeQueryWithBindParameters($selectQuery, $queryParams)->toArray();
        if (empty($result)) {
            throw new AccessDeniedException("Do not have access to this form");
        }
        $activityQuery = "SELECT ox_workflow_instance.process_instance_id as workflow_instance_id,
        ox_activity_instance.activity_instance_id,
        ox_activity_instance.status as status,
        ox_file.data,ox_app.uuid as app_id,ox_file.uuid,
        ox_activity_instance.account_id,ox_activity_instance.activity_id,ox_form.uuid as form_id,ox_activity.task_id as task_id,
        ox_form.name as formName FROM `ox_activity_instance`
        LEFT JOIN ox_activity on ox_activity.id = ox_activity_instance.activity_id
        LEFT JOIN ox_activity_form on ox_activity.id=ox_activity_form.activity_id
        LEFT JOIN ox_form on ox_form.id=ox_activity_form.form_id
        LEFT JOIN ox_workflow_instance on ox_workflow_instance.id = ox_activity_instance.workflow_instance_id
        LEFT JOIN ox_file on ox_file.id=ox_workflow_instance.file_id
        LEFT JOIN ox_app on ox_app.id = ox_form.app_id
        WHERE ox_activity_instance.account_id =:accountId AND ox_workflow_instance.app_id=:appId AND
        ox_activity_instance.activity_instance_id=:activityInstanceId;";
    
        $activityParams = array("accountId" => AuthContext::get(AuthConstants::ACCOUNT_ID), "appId" => $this->getIdFromUuid('ox_app', $data['appId']), "activityInstanceId" => $data['activityInstanceId']);
        $this->logger->info("EXECUTING GET ACTIVITY INSTANCE QUERY $activityQuery WITH ".print_r($activityParams, true));
        $activityInstance = $this->executeQueryWithBindParameters($activityQuery, $activityParams)->toArray();
        if (count($activityInstance) == 0) {
            throw new EntityNotFoundException("No Form found for the activity");
        }

        $filePath = $this->formsFolder . $data['appId'] . "/" . $activityInstance[0]['formName'] . $this->fileExt;
        if (file_exists($filePath)) {
            $activityInstance[0]['template'] = file_get_contents($filePath);
        }

        $activityform = $activityInstance[0];
        $data = json_decode($activityform['data'], true);
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $tempValue = json_decode($value, true);
                if (isset($tempValue)) {
                    $data[$key] = $tempValue;
                } elseif ($value === "false") {
                    $data[$key] = 0;
                }
            }
        }
        $data['fileId'] = isset($activityform['uuid']) ? $activityform['uuid'] : (isset($data['fileId']) ? $data['fileId'] : $data['uuid']);
        unset($activityform['uuid']);
        $activityform['data'] = json_encode($data);
        return $activityform;
    }
    public function createActivityInstance(&$data)
    {
        $activityInstance = new ActivityInstance();
        $data['start_date'] = date('Y-m-d H:i:s');
        $this->logger->info("ActivityInstance BEFCHANGE" . print_r($data, true));
        $activityInstance->exchangeArray($data);
        $activityInstance->validate();
        $this->logger->info("ActivityInstance AFTERFCHANGE" . print_r($activityInstance, true));
        $this->beginTransaction();
        $count = 0;
        try {
            $count = $this->table->save($activityInstance);
            $this->logger->info("ActivityInstance CREATED");
            if ($count == 0) {
                throw new ServiceException("Failed to create Activity Instance", 'activity.instance.create.failed');
            }
            if (!isset($data['id'])) {
                $id = $this->table->getLastInsertValue();
                $data['id'] = $id;
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    public function claimActivityInstance($data)
    {
        $activityQuery = "SELECT ox_activity_instance.*,ox_activity.task_id as task_id
        FROM `ox_activity_instance`
        INNER JOIN ox_activity on ox_activity.id = ox_activity_instance.activity_id
        INNER JOIN ox_workflow_instance on ox_workflow_instance.id = ox_activity_instance.workflow_instance_id
        INNER JOIN ox_app on ox_app.id = ox_workflow_instance.app_id
        WHERE ox_activity_instance.account_id =:accountId
        AND ox_app.uuid=:appId
        AND ox_activity_instance.activity_instance_id=:activityInstanceId;";
        $activityParams = array("accountId" => AuthContext::get(AuthConstants::ACCOUNT_ID),
            "appId" => $data['appId'],
            "activityInstanceId" => $data['activityInstanceId']);
        $this->logger->info("Executing query - $activityQuery with params " . json_encode($activityParams));
        $activityInstance = $this->executeQueryWithBindParameters($activityQuery, $activityParams)->toArray();

        if (isset($activityInstance) && is_array($activityInstance) && !empty($activityInstance)) {
            $taskId = str_replace($activityInstance[0]["task_id"] . ":", "", $activityInstance[0]['activity_instance_id']);
        //print "taskId - $taskId";
        } else {
            throw new EntityNotFoundException("No Activity to claim");
        }
        $test = $this->activityEngine->claimActivity($taskId, AuthContext::get(AuthConstants::USERNAME));
        
        $selectQuery = "SELECT aia.* FROM `ox_file_assignee` as aia
                        inner join ox_activity_instance ai on ai.id = aia.activity_instance_id
                        WHERE ai.activity_instance_id=:activityInstanceId and aia.user_id=:userId;";
        $selectParams = array("userId" => AuthContext::get(AuthConstants::USER_ID), "activityInstanceId" => $data['activityInstanceId']);
        $activityInstanceAssignee = $this->executeQueryWithBindParameters($selectQuery, $selectParams)->toArray();

        $cacheCheck = $this->getUnAssignedUserCache($data['activityInstanceId']);

        if (!empty($activityInstanceAssignee)) {
            try {
                $this->beginTransaction();
                $updateQuery = "UPDATE ox_file_assignee 
                                SET assignee = :assignee, user_id=:userId
                                where id = :id;";
                $updateParams = array("assignee" => 1, "userId" => AuthContext::get(AuthConstants::USER_ID), "id" => $activityInstanceAssignee[0]['id']);
                $update = $this->executeUpdateWithBindParameters($updateQuery, $updateParams);
                if (isset($cacheCheck) && !empty($cacheCheck[0])) {
                    $id = $cacheCheck[0]['id'];
                    $updateQuery = "UPDATE ox_user_cache SET user_id = :userId WHERE id = :id";
                    $updateParams = array("id" => $id,"userId" => AuthContext::get(AuthConstants::USER_ID));
                    $update = $this->executeUpdateWithBindParameters($updateQuery, $updateParams);
                }
                $this->commit();
            } catch (Exception $e) {
                $this->rollback();
                throw $e;
            }
        } else {
            $selectQuery = "SELECT * FROM ox_activity_instance
                            where activity_instance_id =:activityInstanceId
                            AND account_id=:accountId;";
            $selectParams = array("activityInstanceId" => $data['activityInstanceId'], "accountId" => AuthContext::get(AuthConstants::ACCOUNT_ID));
            $resultSelect = $this->executeQueryWithBindParameters($selectQuery, $selectParams)->toArray();
            $insert = "INSERT INTO `ox_file_assignee` (`activity_instance_id`,`user_id`,`assignee`)
            VALUES (:activityInstanceId,:userId,:assignee)";
            $insertParams = array("activityInstanceId" => $resultSelect[0]['id'], "userId" => AuthContext::get(AuthConstants::USER_ID), "assignee" => 1);
            $resultSet = $this->executeUpdateWithBindParameters($insert, $insertParams);
        }
    }

    public function unclaimActivityInstance($data)
    {
        $activityQuery = "SELECT ox_activity_instance.*,ox_activity.task_id as task_id
        FROM `ox_activity_instance`
        INNER JOIN ox_activity on ox_activity.id = ox_activity_instance.activity_id
        INNER JOIN ox_workflow_instance on ox_workflow_instance.id = ox_activity_instance.workflow_instance_id
        INNER JOIN ox_app on ox_app.id = ox_workflow_instance.app_id
        WHERE ox_activity_instance.account_id =:accountId
        AND ox_app.uuid=:appId
        AND ox_activity_instance.activity_instance_id=:activityInstanceId;";
        $activityParams = array("accountId" => AuthContext::get(AuthConstants::ACCOUNT_ID),
            "appId" => $data['appId'],
            "activityInstanceId" => $data['activityInstanceId']);
        $this->logger->info("Executing query - $activityQuery with params " . json_encode($activityParams));
        $activityInstance = $this->executeQueryWithBindParameters($activityQuery, $activityParams)->toArray();

        $cacheCheck = $this->getUnAssignedUserCache($data['activityInstanceId']);

        if (isset($activityInstance) && is_array($activityInstance) && !empty($activityInstance)) {
            $taskId = str_replace($activityInstance[0]["task_id"] . ":", "", $activityInstance[0]['activity_instance_id']);
            $test = $this->activityEngine->unclaimActivity($taskId, AuthContext::get(AuthConstants::USERNAME));
        } else {
            throw new EntityNotFoundException("Activity not found");
        }
        try {
            $this->beginTransaction();
            $updateQuery = "UPDATE ox_file_assignee SET assignee = :assignee WHERE activity_instance_id = (SELECT id from ox_activity_instance WHERE activity_instance_id = :activityInstanceId)";
            $updateParams = array("assignee" => 0, "activityInstanceId" => $data['activityInstanceId']);
            $update = $this->executeUpdateWithBindParameters($updateQuery, $updateParams);
            if (isset($cacheCheck) && !empty($cacheCheck[0])) {
                $id = $cacheCheck[0]['id'];
                $updateQuery = "UPDATE ox_user_cache SET user_id = NULL WHERE id = :id";
                $updateParams = array("id" => $id);
                $update = $this->executeUpdateWithBindParameters($updateQuery, $updateParams);
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    private function getUnAssignedUserCache($activityInstanceId)
    {
        $checkForCacheQuery = "SELECT uc.id from ox_user_cache uc
                                inner join ox_activity_instance ai on uc.activity_instance_id = ai.id
                                where ai.activity_instance_id=:activityInstanceId 
                                and uc.deleted = 0 and uc.user_id is not null";
        $checkParams = array('activityInstanceId' => $activityInstanceId);
        $cacheCheck = $this->executeQueryWithBindParameters($checkForCacheQuery, $checkParams)->toArray();
        return $cacheCheck;
    }


    public function reclaimActivityInstance($data)
    {
        try {
            $this->unclaimActivityInstance($data);
            $this->claimActivityInstance($data);
        } catch (Exception $e) {
            throw $e;
        }
    }


    public function createActivityInstanceEntry(&$data, $commandService)
    {
        $this->logger->info("CREATE ACTIVITY INSTANCE - - ".print_r($data, true));
        if (!isset($data['processInstanceId'])) {
            throw new InvalidParameterException("Workflow Instance Id not provided");
        }
        // Account Id from workflow instance based on the Id
        $query = "SELECT * FROM `ox_workflow_instance` WHERE process_instance_id = :processInstanceId;";
        $queryParams = array("processInstanceId" => $data['processInstanceId']);
        $activityId = null;
        $resultSet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
        if (count($resultSet) == 0) {
            throw new EntityNotFoundException("WorkflowInstance Not Found");
        }

        $workflowInstanceId = $resultSet[0]['id'];
        $accountId = $resultSet[0]['account_id'];
        if (isset($data['taskId'])) {
            $activityQuery = "SELECT * FROM `ox_activity` WHERE workflow_deployment_id = :workflowId and task_id=:taskId;";
            $activityParams = array("workflowId" => $resultSet[0]['workflow_deployment_id'], "taskId" => $data['taskId']);
            $activity = $this->executeQueryWithBindParameters($activityQuery, $activityParams)->toArray();
            $activityId = $activity[0]['id'];
        }

        try {
            $this->beginTransaction();
            if (isset($data['activityInstanceId'])) {
                $activity_instance_id = $data['activityInstanceId'];
            } else {
                $activity_instance_id = $data['executionActivityinstanceId'];
            }
            $fileRecord = "SELECT data from ox_file where id=:fileID";
            $fileParams = array('fileID' => $resultSet[0]['file_id']);
            $fileResult = $this->executeQueryWithBindParameters($fileRecord, $fileParams)->toArray();

            $activityInstance = array('workflow_instance_id' => $workflowInstanceId, 'activity_id' => $activityId, 'activity_instance_id' => $activity_instance_id, 'status' => 'In Progress', 'account_id' => $accountId, 'start_data' => $fileResult[0]['data']);
            $this->createActivityInstance($activityInstance);
            if (isset($data['assignee'])) {
                if (!isset($data['candidates'])) {
                    $data['candidates'] = array();
                }
                $data['candidates'][] = array('type' => 'assignee', 'userid' => $data['assignee']);
            }
            if (isset($data['candidates'])) {
                foreach ($data['candidates'] as $candidate) {
                    $assignee = 0;
                    if (isset($candidate['roleid'])) {
                        $this->logger->info("Creation of Activity Instance role".print_r($candidate['roleid'], true));
                        if ($candidate['type'] == 'assignee') {
                            $assignee = 1;
                        }
                        $teamQuery = $this->executeQuerywithParams("SELECT * FROM `ox_role` WHERE `name` = '" . $candidate['roleid'] . "' AND account_id = ".$accountId.";")->toArray();
                        if ($teamQuery) {
                            $insert = "INSERT INTO `ox_file_assignee` (`activity_instance_id`,`role_id`)
                            VALUES (:activityInstanceId,:roleId)";
                            $insertParams = array("activityInstanceId" => $activityInstance['id'], "roleId" => $teamQuery[0]['id']);
                            $resultSet = $this->executeUpdateWithBindParameters($insert, $insertParams);
                            unset($resultSet);
                            unset($insert);
                        }
                    }
                    if (isset($candidate['teamid'])) {
                        $this->logger->info("Creation of Activity Instance team".print_r($candidate['teamid'], true));
                        if ($candidate['type'] == 'assignee') {
                            $assignee = 1;
                        }
                        $teamQuery = $this->executeQuerywithParams("SELECT * FROM `ox_team` WHERE `name` = '" . $candidate['teamid'] . "';")->toArray();
                        if ($teamQuery) {
                            $insert = "INSERT INTO `ox_file_assignee` (`activity_instance_id`,`team_id`)
                            VALUES (:activityInstanceId,:teamId)";
                            $insertParams = array("activityInstanceId" => $activityInstance['id'], "teamId" => $teamQuery[0]['id']);
                            $resultSet = $this->executeUpdateWithBindParameters($insert, $insertParams);
                            unset($resultSet);
                            unset($insert);
                        }
                    }
                    if (isset($candidate['userid'])) {
                        $this->logger->info("Creation of Activity Instance user".print_r($candidate['userid'], true));
                        $userId = null;
                        if ($candidate['type'] == 'assignee') {
                            $assignee = 1;
                        }
                        $userQuery = $this->executeQuerywithParams("SELECT * FROM `ox_user` WHERE `username` = '" . $candidate['userid'] . "';")->toArray();
                        if (isset($userQuery) && count($userQuery) > 0) {
                            $userId = $userQuery[0]['id'];
                        }
                        if ($candidate['userid'] == 'owner') {
                            $getOwner = $this->executeQuerywithParams("SELECT ox_file.created_by FROM `ox_file` join ox_workflow_instance on ox_workflow_instance.file_id = ox_file.id WHERE ox_workflow_instance.`id` = '" . $workflowInstanceId . "';")->toArray();
                            if (isset($getOwner) && count($getOwner) > 0) {
                                $userId = $getOwner[0]['created_by'];
                            }
                        }
                        if ($candidate['userid'] == 'manager') {
                            //TODO this query is wrong we cannot go by the created by of the file here
                            //ALSO the where clause is wrong
                            $manager = $this->executeQuerywithParams("SELECT manager_id FROM `ox_employee` inner join ox_user on ox_employee.person_id=ox_user.person_id inner join ox_file on ox_user.id=ox_file.created_by WHERE `user_id` = '" . $workflowInstanceId . "';")->toArray();
                            if (isset($manager) && count($manager) > 0) {
                                $userId = $manager[0]['manager_id'];
                            }
                        }
                        if (isset($userId)) {
                            $this->logger->info("Creation of Activity Instance userID".print_r($userId, true));
                            $this->setUserAssignee($activityInstance['id'], $userId, $assignee);
                        }
                    }
                    if (isset($candidate['participant'])) {
                        if ($candidate['type'] == 'assignee') {
                            $assignee = 1;
                        }
                        $identifierField = $data['variables'][$candidate['participant']];
                        $identifier = $data['variables'][$identifierField];
                        $select = "SELECT user_id from ox_wf_user_identifier WHERE identifier_name = :identifierField AND identifier = :identifier";
                        $params = array("identifierField" => $identifierField,"identifier" => $identifier);
                        $resultSet = $this->executeQueryWithBindParameters($select, $params)->toArray();
                        if (count($resultSet) > 0) {
                            $userId = $resultSet[0]['user_id'];
                            $this->setUserAssignee($activityInstance['id'], $userId, $assignee);
                        }
                        unset($resultSet);
                        unset($select);
                    }
                }
            }
            if (isset($data['variables']) && isset($data['variables']['postCreate'])) {
                $commandData = $data['variables'];
                $fileQuery = "SELECT ox_file.data FROM `ox_file` join ox_workflow_instance on ox_workflow_instance.file_id = ox_file.id WHERE ox_workflow_instance.id = :workflow_instance_id;";
                $resultSet = $this->executeQueryWithBindParameters($fileQuery, array("workflow_instance_id" => $commandData['workflow_instance_id']))->toArray();
                if (count($resultSet) > 0) {
                    $fileData = json_decode($resultSet[0]['data'], true);
                    $commandData = array_merge($commandData, $fileData);
                    $commandData['activityInstanceId'] = $activity_instance_id;
                    $commandData['workflowInstanceId'] = $data['processInstanceId'];
                    $commandData['commands'] = $data['variables']['postCreate'];
                    $data['variables'] = $commandService->runCommand($commandData, null);
                }
            }
            $this->commit();
        } catch (Exception $e) {
            $this->logger->info("Creation of Activity Instance Entry Failed" . $e->getMessage());
            $this->rollback();
            throw $e;
        }
    }


    private function setUserAssignee($activityInstanceId, $userId, $assignee)
    {
        $insert = "INSERT INTO `ox_file_assignee` (`activity_instance_id`,`user_id`,`assignee`)
                            VALUES (:activityInstanceId,:userId,:assignee)";
        $insertParams = array("activityInstanceId" => $activityInstanceId, "userId" => $userId, "assignee" => $assignee);
        $resultSet = $this->executeUpdateWithBindParameters($insert, $insertParams);
    }

    public function completeActivityInstance(&$data)
    {
        $query = "SELECT * FROM `ox_workflow_instance` WHERE process_instance_id = :processInstanceId;";
        $queryParams = array("processInstanceId" => $data['processInstanceId']);
        $activityId = null;
        $this->logger->info("Executing workflow instance query - $query with params " . json_encode($queryParams));
        $resultSet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
        if (empty($resultSet)) {
            throw new EntityNotFoundException("Workflow instance not found");
        }
        $workflowInstanceId = $resultSet[0]['id'];
    
        // Account Id from workflow instance based on the Id
        if (isset($data['processVariables'])) {
            $variables = $data['processVariables'];
            $activityQuery = "SELECT a.* FROM `ox_activity` as a
                    inner join ox_activity_instance as ai on ai.activity_id = a.id
                    WHERE ai.activity_instance_id = :activityInstanceId and a.task_id=:taskId;";
            $queryParams = array("activityInstanceId" => $data['activityInstanceId'], "taskId" => $data['taskId']);
            $this->logger->info("Executing Activity query - $activityQuery with params " . json_encode($queryParams));
            $activity = $this->executeQueryWithBindParameters($activityQuery, $queryParams)->toArray();
            if (count($activity) > 0) {
                $activityId = $activity[0]['id'];
            } else {
                throw new EntityNotFoundException("Activity Instance not found");
            }
        
            if (isset($variables['accountId'])) {
                if ($account = $this->getIdFromUuid('ox_account', $variables['accountId'])) {
                    $accountId = $account;
                } else {
                    $accountId = $variables['acccountId'];
                }
            }
        }
        $selectQuery = "SELECT * FROM `ox_activity_instance`
                        WHERE activity_instance_id=:activityInstanceId
                        and workflow_instance_id=:workflowInstanceId;";
        $selectParams = array("activityInstanceId" => $data['activityInstanceId'], "workflowInstanceId" => $workflowInstanceId);
        $this->logger->info("Executing Activity instance query - $selectQuery with params " . json_encode($selectParams));
        $activityInstance = $this->executeQueryWithBindParameters($selectQuery, $selectParams)->toArray();
        if (empty($activityInstance)) {
            throw new EntityNotFoundException("Activity not found");
        }
        try {
            $this->beginTransaction();
            $updateQuery = "UPDATE ox_activity_instance SET status =:instanceStatus where id =:activityInstanceId;";
            $updateParams = array("instanceStatus" => 'Completed', "activityInstanceId" => $activityInstance[0]['id']);
            $this->logger->info("Updating Activity instance - $updateQuery with params " . json_encode($updateParams));
            $update = $this->executeUpdateWithBindParameters($updateQuery, $updateParams);
            $this->logger->info("Updated Records - " . $update->getAffectedRows());
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            return $e;
        }
    }

    public function getActivityInstance($activityInstanceId, $workflowInstanceId)
    {
        $this->logger->info("getActivityInstance - ");
        $query = "SELECT ox_activity_instance.*, ox_activity.task_id as task_id
                  FROM `ox_activity_instance`
                  LEFT JOIN ox_activity on ox_activity.id = ox_activity_instance.activity_id
                  LEFT JOIN ox_workflow_instance on ox_workflow_instance.id = ox_activity_instance.workflow_instance_id
                  WHERE ox_activity_instance.activity_instance_id=? and ox_activity_instance.account_id=? and ox_workflow_instance.process_instance_id=?";
        $queryParams = array($activityInstanceId, AuthContext::get(AuthConstants::ACCOUNT_ID), $workflowInstanceId);
        $this->logger->info("query " .$query);
        $this->logger->info("query params" . print_r($queryParams, true));
        $activityInstance = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
        $this->logger->info("getActivityInstance -> activityInstance - " . print_r($activityInstance, true));

        if (!empty($activityInstance)) {
            return $activityInstance[0];
        } else {
            throw new EntityNotFoundException("activity instance not found for " . $activityInstanceId);
        }
    }


    public function getActivityChangeLog($activityInstanceId, $labelMapping=null)
    {
        $selectQuery = "SELECT oai.start_data, oai.completion_data ,owi.file_id 
                        from ox_activity_instance oai 
                        inner join ox_workflow_instance owi on oai.workflow_instance_id = owi.id 
                        where oai.activity_instance_id = :activityInstanceId ";
        $selectQueryParams = array('activityInstanceId' => $activityInstanceId);
        $result = $this->executeQueryWithBindParameters($selectQuery, $selectQueryParams)->toArray();
        if (count($result) > 0) {
            $recordSet = $this->fileService->getWorkflowInstanceByFileId($this->getUuidFromId('ox_file', $result[0]['file_id']));
            $startData = json_decode($result[0]['start_data'], true);
            $completionData = json_decode($result[0]['completion_data'], true);
            $resultData = $this->fileService->getChangeLog($recordSet[0]['entity_id'], $startData, $completionData, $labelMapping);
            return $resultData;
        } else {
            return $result;
        }
    }

    // USED IN DELEGATE
    public function getFileDataByActivityInstanceId($activityInstanceId)
    {
        $selectQuery = "SELECT of.data from ox_file of
                        INNER JOIN ox_workflow_instance owi on owi.file_id = of.id
                        INNER JOIN ox_activity_instance oai on oai.workflow_instance_id = owi.id where oai.activity_instance_id = :activityInstanceId ";
        $selectQueryParams = array('activityInstanceId' => $activityInstanceId);
        $result = $this->executeQueryWithBindParameters($selectQuery, $selectQueryParams)->toArray();
        if (isset($result[0])) {
            return $result[0];
        } else {
            return;
        }
    }

    public function removeActivityInstanceRecords($workflowDepId)
    {
        try {
            $this->beginTransaction();
            $update = "UPDATE ox_activity_instance SET isdeleted=:deleted WHERE workflow_instance_id IN(SELECT id from ox_workflow_instance where workflow_deployment_id=:workflowDepId)";
            $updateParams = array('deleted' => 1, 'workflowDepId' => $workflowDepId);
            $this->executeUpdateWithBindParameters($update, $updateParams);
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }
}
