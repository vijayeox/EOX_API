<?php
namespace Oxzion\Service;

use Exception;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\EntityNotFoundException;
use Oxzion\Messaging\MessageProducer;
use Oxzion\Model\File;
use Oxzion\Model\FileTable;
use Oxzion\ServiceException;
use Oxzion\OxServiceException;
use Oxzion\Service\FieldService;
use Oxzion\Service\EntityService;
use Oxzion\Utils\UuidUtil;
use Oxzion\Utils\ArrayUtils;
use Oxzion\Model\FileAttachment;
use Oxzion\Model\FileAttachmentTable;
use Oxzion\Utils\FileUtils;
use Oxzion\Service\SubscriberService;
use Oxzion\Utils\StringUtils;
use Oxzion\Utils\FilterUtils;
use Oxzion\Service\BusinessParticipantService;

class FileService extends AbstractService
{
    protected $fieldService;
    protected $fieldDetails;
    protected $entityService;
    protected $subscriberService;
    protected $businessParticipantService;
    /**
     * @ignore __construct
     */
    public function __construct($config, $dbAdapter, FileTable $table, FormService $formService, MessageProducer $messageProducer, FieldService $fieldService, EntityService $entityService, FileAttachmentTable $attachmentTable, SubscriberService $subscriberService, BusinessParticipantService $businessParticipantService)
    {
        parent::__construct($config, $dbAdapter);
        $this->messageProducer = $messageProducer;
        $this->table = $table;
        $this->config = $config;
        $this->dbAdapter = $dbAdapter;
        $this->fieldService = $fieldService;
        $this->fieldDetails=[];
        $this->attachmentTable = $attachmentTable;
        $this->entityService = $entityService;
        $this->subscriberService = $subscriberService;
        $this->businessParticipantService = $businessParticipantService;
        // $emailService = new EmailService($config, $dbAdapter, Oxzion\Model\Email);
    }

    /**
     * Create File Service
     * @method createFile
     * @param array $data Array of elements as shown
     * <code> {
     *               id : integer,
     *               name : string,
     *               formid : integer,
     *               Fields from Form
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created File.
     */
    public function createFile(&$data, $ensureDir = false)
    {
        $baseFolder = $this->config['APP_DOCUMENT_FOLDER'];
        $this->logger->info("Data CreateFile- " . json_encode($data));
        if (isset($data['uuid'])) {
            $fileId = $this->getIdFromUuid('ox_file', $data['uuid']);
            if ($fileId) {
                unset($data['uuid']);
            }
        }
        if (isset($data['form_id'])) {
            $formId = $this->getIdFromUuid('ox_form', $data['form_id']);
        } else {
            $formId = null;
        }

        if (isset($data['assocId'])) {
            $assocId = $this->getIdFromUuid('ox_file', $data['assocId']);
        } else if(isset($data['bos']['assoc_id']) && UuidUtil::isValidUuid($data['bos']['assoc_id'])){
            $assocId = $this->getIdFromUuid('ox_file', $data['bos']['assoc_id']);
        }else {
            $assocId = null;
        }

        $data['uuid'] = $uuid = isset($data['uuid']) && UuidUtil::isValidUuid($data['uuid']) ? $data['uuid'] : UuidUtil::uuid();

        $entityId = isset($data['entity_id']) ? $data['entity_id'] : null;

        if (!$entityId && isset($data['entity_name'])) {
            $select = "select id from ox_app_entity where name = :entityName";
            $params = array('entityName' => $data['entity_name']);
            $result = $this->executeQuerywithBindParameters($select, $params)->toArray();
            if (count($result) > 0) {
                $entityId = $result[0]['id'];
            }
        }
        unset($data['uuid']);
        $oldData = $data;
        $fields = $data = $this->cleanData($data);
        $jsonData = json_encode($data);
        $this->logger->info("Data From Fileservice after encoding - " . print_r($jsonData, true));
        $accountId = $this->businessParticipantService->getEntitySellerAccount($entityId);
        $data['uuid'] = $uuid;
        $data['account_id'] = $accountId ? $accountId : AuthContext::get(AuthConstants::ACCOUNT_ID);
        $data['created_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_created'] = date('Y-m-d H:i:s');
        $data['form_id'] = $formId;
        $data['data'] = $jsonData;
        
        $entityConfig = $this->setupEntityFields($entityId, $data);
        $subscribers['subscribers'] = isset($entityConfig['subscribersList']) ? $entityConfig['subscribersList']: null;
        $titleConfig = $entityConfig['title'];
        $entityName = $entityConfig['name'];
        $rygRule = $entityConfig['ryg_rule'];
        unset($entityConfig['subscribersList']);
        unset($entityConfig['subscribersList']);
        unset($entityConfig['title']);
        unset($entityConfig['name']);
        unset($entityConfig['ryg_rule']);
        $data['fileTitle'] = $this->evaluateFileTitle($titleConfig, $data, $entityName);
        $data['rygStatus'] = $this->evaluateRyg($data, json_decode($rygRule, true));
        $data = array_merge($data, $entityConfig);
        $data['assoc_id'] = isset($assocId) ? $assocId : (isset($oldData['bos']['assoc_id']) ? $oldData['bos']['assoc_id'] : null);
        $data['last_workflow_instance_id'] = isset($oldData['last_workflow_instance_id']) ? $oldData['last_workflow_instance_id'] : null;
        $file = new File($this->table);
        if (isset($data['id'])) {
            unset($data['id']);
        }
        $this->logger->info("File data From Fileservice - " . print_r($file->toArray(), true));
        $count = 0;
        try {
            $this->beginTransaction();
            $file->assign($data);
            $file->save();
            $result = $file->getGenerated(true);
            $data['version'] = $result['version'];
            $data['uuid'] = $result['uuid'];
            $this->logger->info("COUNT  FILE DATA----" . $count);
            $id = $result['id'];
            if ($id == 0) {
                throw new ServiceException("File Creation Failed", "file.create.failed");
            }
            $count++;
            $this->logger->info("FILE ID DATA" . $id);
            if (!empty($subscribers['subscribers'])) {
                $subscribers['account_id'] = $data['account_id'];
                $this->subscriberService->updateSubscriber($subscribers, $data['uuid']);
            }
            $validFields = $this->checkFields($data['entity_id'], $fields, $id, false);
            $this->updateFileData($id, $fields);
            $this->logger->debug("Check Fields Data ----- " . print_r($validFields, true));
            $this->logger->info("Checking Index Fields ---- " . print_r($validFields['indexedFields'], true));
            if (count($validFields['indexedFields']) > 0) {
                $this->multiInsertOrUpdate('ox_indexed_file_attribute', $validFields['indexedFields']);
            }
            $this->logger->info("Checking Document Fields ---- " . json_encode($validFields['documentFields']));
            if (count($validFields['documentFields']) > 0) {
                $this->multiInsertOrUpdate('ox_file_document', $validFields['documentFields']);
            }
            $this->logger->info("Created successfully  - file record");
            $this->setupFileParticipants($result['id'], $data);
            $this->setupFileAssignee($result['id'], $data);
            $this->commit();
            // IF YOU DELETE THE BELOW TWO LINES MAKE SURE YOU ARE PREPARED TO CHECK THE ENTIRE INDEXER FLOW

            if (isset($result['id'])) {
                $this->logger->info("THE FILE ID TO BE INDEXED IS ".$result['uuid']);
                $this->messageProducer->sendQueue(json_encode(array('uuid' => $result['uuid'])), 'FILE_ADDED');
            }
        } catch (Exception $e) {
            $this->rollback();
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
        return $count;
    }
    private function setupEntityFields($entityId, $data)
    {
        $returnResult = [];
        $returnResult['entity_id'] = $entityId;
        $selectQuery = "SELECT name,start_date_field,end_date_field,status_field,subscriber_field,ryg_rule,title from ox_app_entity WHERE id =:entityId";
        $parameters = array('entityId' => $entityId);
        $returnResult['ryg_rule'] = $returnResult['title'] = $returnResult['name'] = null;
        $resultQuery = $this->executeQuerywithBindParameters($selectQuery, $parameters)->toArray();
        if (count($resultQuery) > 0) {
            $returnResult['start_date'] = date_format(date_create(isset($data[$resultQuery[0]['start_date_field']])?$data[$resultQuery[0]['start_date_field']]:null), 'Y-m-d');
            $returnResult['end_date'] = date_format(date_create(isset($data[$resultQuery[0]['end_date_field']])?$data[$resultQuery[0]['end_date_field']]:null), 'Y-m-d');
            $returnResult['status'] = isset($data[$resultQuery[0]['status_field']])?$data[$resultQuery[0]['status_field']]:null;
            $returnResult['subscribersList'] = array();
            $returnResult['ryg_rule'] = $resultQuery[0]['ryg_rule'];
            $returnResult['title'] = $resultQuery[0]['title'];
            $returnResult['name'] = $resultQuery[0]['name'];
            $subsc = explode(",", $resultQuery[0]['subscriber_field']);
            for ($i=0; $i < count($subsc) ; $i++) {
                $this->logger->info("Subsc-----".print_r($subsc, true));

                if (isset($data[$subsc[$i]]) && is_string($data[$subsc[$i]])) {
                    $resSubs = json_decode($data[$subsc[$i]], true);
                    if ($resSubs) {
                        $returnResult['subscribersList'] = array_merge($returnResult['subscribersList'], $resSubs);
                    } else {
                        $returnResult['subscribersList'][] = $data[$subsc[$i]];
                    }
                } elseif (isset($data[$subsc[$i]]) && is_array($data[$subsc[$i]])) {
                    $returnResult['subscribersList'] = array_merge($returnResult['subscribersList'], $data[$subsc[$i]]);
                }
            }
        }
        $this->logger->info("Subsc result----".print_r($returnResult, true));
        return $returnResult;
    }
    private function setupFileAssignee($fileId, $file)
    {
        $query = "delete from ox_file_assignee where file_id = :fileId";
        $queryWhere = array("fileId" => $fileId);
        $result = $this->executeUpdateWithBindParameters($query, $queryWhere);
        $this->setUserAssignees($fileId, $file);
        $this->setTeamAssignees($fileId, $file);
        $this->setRoleAssignees($fileId, $file);
    }
    private function setTeamAssignees($fileId, $file)
    {
        $fileData = json_decode($file['data'], true);
        if (isset($fileData['assigned_team'])) {
            try {
                $assignedList  = json_decode($fileData['assigned_team'], true);
                if (is_null($assignedList)) {
                    $this->setTeamAssignee($fileId, $fileData['assigned_team'], 1);
                } else {
                    if (isset($assignedList) && is_array($assignedList)) {
                        foreach ($assignedList as $assignee) {
                            $this->setTeamAssignee($fileId, $assignee, 0);
                        }
                    }
                }
            } catch (Exception $e) {
                $this->logger->info("Error Setting Team Assignee---".$e->getMessage());
            }
        }
        if (isset($fileData['observer_team'])) {
            try {
                if (is_string($fileData['observer_team'])) {
                    $observers_teamList  = json_decode($fileData['observer_team'], true);
                } else {
                    $observers_teamList  = $fileData['observer_team'];
                }
                if (!is_null($observers_teamList)) {
                    foreach ($observers_teamList as $observer) {
                        $this->setTeamAssignee($fileId, $observer, 0);
                    }
                }
            } catch (Exception $e) {
                $this->logger->info("Error Setting Team Assignee---".$e->getMessage());
            }
        }
    }
    private function setRoleAssignees($fileId, $file)
    {
        $fileData = json_decode($file['data'], true);
        if (isset($fileData['assigned_role'])) {
            try {
                $assignedList  = json_decode($fileData['assigned_role'], true);
                if (is_null($assignedList)) {
                    $this->setRoleAssignee($fileId, $fileData['assigned_role'], 1);
                } else {
                    if (isset($assignedList) && is_array($assignedList)) {
                        foreach ($assignedList as $assignee) {
                            $this->setRoleAssignee($fileId, $assignee, 0);
                        }
                    }
                }
            } catch (Exception $e) {
                $this->logger->info("Error Setting Role Assignee---".$e->getMessage());
            }
        }
        if (isset($fileData['observer_role'])) {
            try {
                if (is_string($fileData['observer_role'])) {
                    $observer_roleList  = json_decode($fileData['observer_role'], true);
                } else {
                    $observer_roleList  = $fileData['observer_role'];
                }
                if (!is_null($observer_roleList)) {
                    foreach ($observer_roleList as $observer) {
                        $this->setRoleAssignee($fileId, $observer, 0);
                    }
                }
            } catch (Exception $e) {
                $this->logger->info("Error Setting Role Assignee---".$e->getMessage());
            }
        }
    }
    private function setUserAssignees($fileId, $file)
    {
        $fileData = json_decode($file['data'], true);
        if (isset($fileData['assignedto'])) {
            try {
                if (is_string($fileData['assignedto'])) {
                    $assignedList  = json_decode($fileData['assignedto'], true);
                } else {
                    $assignedList  = $fileData['assignedto'];
                }
                if (is_null($assignedList)) {
                    $this->setUserAssignee($fileId, $fileData['assignedto'], 1);
                } else {
                    if (isset($assignedList) && is_array($assignedList)) {
                        foreach ($assignedList as $assignee) {
                            $this->setUserAssignee($fileId, $assignee, 1);
                        }
                    } else {
                        $this->setUserAssignee($fileId, $fileData['assignedto'], 1);
                    }
                }
            } catch (Exception $e) {
                $this->logger->info("Error Setting Assignee---".$e->getMessage());
            }
        }
        if (isset($fileData['observers'])) {
            try {
                if (is_string($fileData['observers'])) {
                    $observersList  = json_decode($fileData['observers'], true);
                } else {
                    $observersList  = $fileData['observers'];
                }
                if (!is_null($observersList)) {
                    if (isset($observersList) && is_array($observersList)) {
                        foreach ($observersList as $observer) {
                            $this->setUserAssignee($fileId, $observer, 0);
                        }
                    } else {
                        $this->setUserAssignee($fileId, $fileData['observers'], 0);
                    }
                }
            } catch (Exception $e) {
                $this->logger->info("Error Setting Assignee---".$e->getMessage());
            }
        }
    }
    private function setUserAssignee($fileId, $user, $assignee)
    {
        if ($user == 'owner') {
            $getOwner = $this->executeQuerywithParams("SELECT ox_file.created_by FROM `ox_file` WHERE ox_file.`id` = '" . $fileId . "';")->toArray();
            if (isset($getOwner) && count($getOwner) > 0) {
                $userId = $getOwner[0]['created_by'];
            } else {
                return;
            }
        } elseif ($user == 'manager') {
            $manager = $this->executeQuerywithParams("SELECT manager_id FROM `ox_employee_manager` inner join ox_user on ox_employee_manager.employee_id=ox_user.id inner join ox_file on ox_user.id=ox_file.created_by WHERE `ox_file`.`id` = " . $fileId . ";")->toArray();
            if (isset($manager) && count($manager) > 0) {
                $userId = $manager[0]['manager_id'];
            } else {
                return;
            }
        } else {
            $userId = $this->getIdFromUuid('ox_user', $user);
        }
        if ($userId) {
            $insertParams = array("fileId" => $fileId, "userId" => $userId, "assignee" => $assignee);
            $insert = "INSERT INTO `ox_file_assignee` (`file_id`,`user_id`,`assignee`) VALUES (:fileId,:userId,:assignee)";
            $resultSet = $this->executeQuerywithBindParameters($insert, $insertParams);
        }
    }
    private function setRoleAssignee($fileId, $role, $assignee)
    {
        $roleId = $this->getIdFromUuid('ox_role', $role);
        if ($roleId) {
            $insert = "INSERT INTO `ox_file_assignee` (`file_id`,`role_id`,`assignee`) VALUES (:fileId,:roleId,:assignee)";
            $insertParams = array("fileId" => $fileId, "roleId" => $roleId, "assignee" => $assignee);
            $resultSet = $this->executeQuerywithBindParameters($insert, $insertParams);
        }
    }
    private function setTeamAssignee($fileId, $role, $assignee)
    {
        $teamId = $this->getIdFromUuid('ox_team', $role);
        if ($teamId) {
            $insert = "INSERT INTO `ox_file_assignee` (`file_id`,`team_id`,`assignee`) VALUES (:fileId,:teamId,:assignee)";
            $insertParams = array("fileId" => $fileId, "teamId" => $teamId, "assignee" => $assignee);
            $resultSet = $this->executeQuerywithBindParameters($insert, $insertParams);
        }
    }

    private function setupFileParticipants($fileId, $file)
    {
        $entityId = $file['entity_id'];
        $fileData = json_decode($file['data'], true);
        $accountId = $file['account_id'];
        $query = "INSERT IGNORE INTO ox_file_participant (file_id, account_id, business_role_id) 
                  (SELECT $fileId, $accountId, ob.business_role_id
                  from ox_account_business_role ob inner join ox_account_offering oo on ob.id = oo.account_business_role_id
                  WHERE oo.entity_id = :entityId)";
        $queryParams = ['entityId' => $entityId];
        $this->logger->info("File Part-- $query with params---".print_r($queryParams,true));
        $this->executeUpdateWithBindParameters($query, $queryParams);
        $query = "select identifier from ox_entity_identifier where entity_id = :entityId";
        $result = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
        $identifier = null;
        $identifierField = null;
        foreach ($result as $value) {
            $identifierField = $value['identifier'];
            if (isset($fileData[$identifierField])) {
                $identifier = $fileData[$identifierField];
                break;
            }
        }

        if ($identifier) {
            $query = "INSERT IGNORE INTO ox_file_participant (file_id, account_id, business_role_id)
                       (SELECT $fileId, ui.account_id, ep.business_role_id
                        FROM ox_wf_user_identifier ui inner join ox_entity_identifier ei on ei.identifier = ui.identifier_name
                        inner join ox_entity_participant_role ep on ep.entity_id = ei.entity_id
                        inner join ox_app_entity ae on ae.id = ep.entity_id and ui.app_id = ae.app_id
                        inner join ox_account_business_role oxabr on oxabr.account_id = ui.account_id AND oxabr.business_role_id = ep.business_role_id 
                        where ep.entity_id = :entityId and ui.identifier_name = :identifierField 
                        and ui.identifier = :identifier)";
            $queryParams['identifierField'] = $identifierField;
            $queryParams['identifier'] = $identifier;
            $this->logger->info("UPDATES qUERY $query-----".print_r($queryParams,true));
            $res = $this->executeUpdateWithBindParameters($query, $queryParams);
            $this->logger->info("INSERTED ROWS-----".print_r($res->getAffectedRows(),true));
        }
    }

    private function updateFileData($id, $data)
    {
        $query = "update ox_file set data = :data where id = :id";
        $params = array('data' => json_encode($data), 'id' => $id);
        $result = $this->executeUpdateWithBindParameters($query, $params);
        return $result->getAffectedRows() > 0;
    }

    public function updateFileAttributes($fileId)
    {
        $this->logger->info("FILEID xx---".$fileId);
        $obj = $this->table->get($fileId);
        if (is_null($obj)) {
            throw new EntityNotFoundException("Invalid File Id");
        }
        $obj = $obj->toArray();
        $this->updateFileUserContext($obj);
        $fields = json_decode($obj['data'], true);
        $this->updateFileAttributesInternal($obj['entity_id'], $fields, $fileId);
    }
    
    private function updateFileUserContext($obj)
    {
        $accountId = $obj['account_id'];
        $userId = $obj['modified_by'] ? $obj['modified_by'] : $obj['created_by'];
        $accountUuid = $this->getUuidFromId('ox_account', $accountId);
        $userUuid = $this->getUuidFromId('ox_user', $userId);
        $context = ['accountId' => $accountUuid, 'userId' => $userUuid];
        $this->updateAccountContext($context);
    }
    private function updateFileAttributesInternal($entityId, $fileData, $fileId)
    {
        $validFields = $this->checkFields($entityId, $fileData, $fileId);
        $validFields = $validFields['validFields'];
        $fields = $validFields['data'];
        unset($validFields['data']);
        $this->logger->info(json_encode($validFields) . "are the list of valid fields.\n");
        try {
            $this->beginTransaction();
            if ($validFields && !empty($validFields)) {
                $query = "delete from ox_file_attribute where file_id = :fileId";
                $queryWhere = array("fileId" => $fileId);
                $result = $this->executeUpdateWithBindParameters($query, $queryWhere);
                $this->multiInsertOrUpdate('ox_file_attribute', $validFields);
                $this->logger->info("Checking Fields update ---- " . print_r($validFields, true));
                $query = "update ox_indexed_file_attribute ifa 
                            inner join ox_file_attribute fa on ifa.file_id = fa.file_id and ifa.field_id = fa.field_id 
                            inner join ox_field f on fa.field_id = f.id
                            set ifa.field_value_text = fa.field_value_text, ifa.field_value_numeric = fa.field_value_numeric,
                                ifa.field_value_boolean = fa.field_value_boolean, ifa.field_value_date = fa.field_value_date,
                                ifa.field_value_type = fa.field_value_type, ifa.modified_by = fa.modified_by, ifa.date_modified = fa.date_modified
                            where fa.file_id = :fileId and f.index = 1";
                $this->logger->info("Executing query $query with params - ". json_encode($queryWhere));
                $this->executeUpdateWithBindParameters($query, $queryWhere);
                $query = "INSERT INTO ox_indexed_file_attribute (file_id, field_id, account_id, field_value_text, 
                            field_value_date, field_value_numeric, field_value_boolean, field_value_type, date_created, 
                            created_by, date_modified, modified_by)
                          (SELECT fa.file_id, fa.field_id, fa.account_id, fa.field_value_text, 
                            fa.field_value_date, fa.field_value_numeric, fa.field_value_boolean, fa.field_value_type,
                            fa.date_created, fa.created_by, fa.date_modified, fa.modified_by from ox_file_attribute fa
                            inner join ox_field f on fa.field_id = f.id
                            left outer join ox_indexed_file_attribute ifa on ifa.file_id = fa.file_id and ifa.field_id = fa.field_id
                            where fa.file_id = :fileId and f.index = 1 and ifa.id is null)";
                $this->logger->info("Executing query $query with params - ". json_encode($queryWhere));
                $this->executeUpdateWithBindParameters($query, $queryWhere);
                $query = "update ox_file_document ifa
                            inner join ox_file_attribute fa on ifa.file_id = fa.file_id and ifa.field_id = fa.field_id and (ifa.sequence = fa.sequence or (fa.sequence is null and ifa.sequence is null))
                            inner join ox_field f on f.id = fa.field_id
                            set ifa.field_value = fa.field_value, ifa.modified_by = fa.modified_by, ifa.date_modified = fa.date_modified
                            where fa.file_id = :fileId and f.type IN('document','file')";
                $this->logger->info("Executing query $query with params - ". json_encode($queryWhere));
                $this->executeUpdateWithBindParameters($query, $queryWhere);
                $query = "INSERT INTO ox_file_document (file_id, field_id, account_id, field_value, sequence,
                            date_created, created_by, date_modified, modified_by)
                          (SELECT fa.file_id, fa.field_id, fa.account_id, fa.field_value, fa.sequence, 
                            fa.date_created, fa.created_by, fa.date_modified, fa.modified_by from ox_file_attribute fa 
                            inner join ox_field f on f.id = fa.field_id
                            left outer join ox_file_document ifa on ifa.file_id = fa.file_id and ifa.field_id = fa.field_id and (ifa.sequence = fa.sequence or (fa.sequence is null and ifa.sequence is null))
                            where fa.file_id = :fileId and f.type IN ('document','file')and ifa.id is null)";
                $this->logger->info("Executing query $query with params - ". json_encode($queryWhere));
                $this->executeUpdateWithBindParameters($query, $queryWhere);
                $fields = $this->processMergeData($entityId, $fileData, $fields);
                $this->updateFileData($fileId, $fields);
            }
            $this->logger->info("Update File Data after checkFields ---- " . json_encode($fields));
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    public function startBatchProcessing()
    {
        $this->beginTransaction();
    }

    public function completeBatchProcessing()
    {
        $this->commit();
    }
    /**
     * Update File Service
     * @method updateFile
     * @param array $id ID of File to update
     * @param array $data
     * @return array Returns a JSON Response with Status Code and Created File.
     */
    public function updateFile(&$data, $id)
    {
        $baseFolder = $this->config['APP_DOCUMENT_FOLDER'];
        if (isset($data['workflow_instance_id'])) {
            $select = "SELECT ox_file.* from ox_file join ox_workflow_instance on ox_workflow_instance.file_id = ox_file.id where ox_workflow_instance.id = " . $data['workflow_instance_id'];
            $obj = $this->executeQuerywithParams($select)->toArray();
            if (!empty($obj)&& !is_null($obj)) {
                $obj = $obj[0];
            } else {
                throw new EntityNotFoundException("File Id not found -- " . $id);
            }
        } else {
            $obj = $this->table->getByUuid($id);
            if (is_null($obj)) {
                return $this->createFile($data);
            }
            $obj = $obj->toArray();
        }
        $latestcheck = 0;
        if (isset($data['is_active']) && $data['is_active'] == 0) {
            $latestcheck = 1;
        }
        
        $fileObject = json_decode($obj['data'], true);
        
        foreach ($fileObject as $key => $fileObjectValue) {
            if (is_array($fileObjectValue)) {
                $fileObject[$key] = json_encode($fileObjectValue);
            }
        }
        foreach ($data as $key => $dataelement) {
            if (is_array($dataelement)) {
                $data[$key] = json_encode($dataelement);
            }
        }

        if (isset($obj['entity_id'])) {
            $entityId = $obj['entity_id'];
        } else {
            throw new ServiceException("Invalid Entity", "entity.invalid");
        }

        $fields = $this->processMergeData($entityId, $fileObject, $data);
        $file = new File($this->table);
        $file->loadByUuid($id);
        $result = $file->getGenerated(true);
        $uuid = $id;
        $id = $result['id'];
        $validFields = $this->checkFields($entityId, $fields, $id, false);
        $dataArray = $this->processMergeData($entityId, $fileObject, $fields);
        $fileObject = $obj;
        $dataArray = $this->cleanData($dataArray);
        if (isset($data['version'])) {
            $fileObject['version'] = $data['version'];
        }
        if (!empty($dataArray['assocId'])) {
            if (UuidUtil::isValidUuid($dataArray['assocId'])) {
                $fileObject['assoc_id'] = $this->getIdFromUuid('ox_file', $dataArray['assocId']);
            } else {
                $fileObject['assoc_id'] = $dataArray['assocId'];
            }
        }
        $fileObject['data'] = json_encode($dataArray);
        $entityConfig = $this->setupEntityFields($entityId, $dataArray);
        $titleConfig = $entityConfig['title'];
        $entityName = $entityConfig['name'];
        $rygRule = $entityConfig['ryg_rule'];
        $subscribers['subscribers'] = $entityConfig['subscribersList'];
        unset($entityConfig['subscribersList']);
        unset($entityConfig['title']);
        unset($entityConfig['name']);
        unset($entityConfig['ryg_rule']);
        $fileObject = array_merge($fileObject, $entityConfig);
        $fileObject['fileTitle'] = $this->evaluateFileTitle($titleConfig, $dataArray, $entityName);
        $fileObject['rygStatus'] = $this->evaluateRyg($dataArray, json_decode($rygRule, true));
        $fileObject['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $fileObject['date_modified'] = date('Y-m-d H:i:s');
        if (isset($data['last_workflow_instance_id'])) {
            $fileObject['last_workflow_instance_id'] = $data['last_workflow_instance_id'];
        }
        $count = 0;
        $version = $file->getProperty('version');
        try {
            $this->beginTransaction();
            $this->logger->info("Entering to Update File -" . json_encode($fileObject) . "\n");
            $file->assign($fileObject);
            $file->save();
            $result = $file->getGenerated();
            $data['version'] = $result['version'];
            $count = $result['version'] - $version;
            $this->logger->info(json_encode($validFields) . "are the list of valid fields.\n");
            if (!empty($subscribers['subscribers']) && ($subscribers['subscribers'] != '[]')) {
                $subscribers['account_id'] = $file->getProperty('account_id');
                $this->subscriberService->updateSubscriber($subscribers, $result['uuid']);
            }
            if ($validFields && !empty($validFields)) {
                $queryWhere = array("fileId" => $id);
                $query = "delete from ox_indexed_file_attribute where file_id = :fileId";
                $result = $this->executeQueryWithBindParameters($query, $queryWhere);
                $this->logger->info("Checking Fields update ---- " . print_r($validFields, true));
                if ($validFields['indexedFields'] && count($validFields['indexedFields']) > 0) {
                    $this->multiInsertOrUpdate('ox_indexed_file_attribute', $validFields['indexedFields']);
                }
                $query = "delete from ox_file_document where file_id = :fileId";
                $result = $this->executeQueryWithBindParameters($query, $queryWhere);
                $this->logger->info("Checking Fields update ---- " . print_r($validFields, true));
                if ($validFields['documentFields'] && count($validFields['documentFields']) > 0) {
                    $this->multiInsertOrUpdate('ox_file_document', $validFields['documentFields']);
                }
            }
            $this->setupFileAssignee($id, $file->toArray());
            $this->logger->info("Leaving the updateFile method \n");
            $this->commit();
            $select = "SELECT * from ox_file where id = '".$id."'";
            $result = $this->executeQuerywithParams($select)->toArray();
            $this->logger->info("FILE DATA CHECK AFTER DATA --".print_r($result, true));
            // IF YOU DELETE THE BELOW TWO LINES MAKE SURE YOU ARE PREPARED TO CHECK THE ENTIRE INDEXER FLOW
            if (($latestcheck == 1) && isset($id)) {
                $this->messageProducer->sendQueue(json_encode(array('id' => $id)), 'FILE_DELETED');
            } else {
                if (isset($id)) {
                    $this->messageProducer->sendQueue(json_encode(array('id' => $id)), 'FILE_UPDATED');
                    $this->messageProducer->sendQueue(json_encode(array('uuid' => $uuid)), 'FILE_UPDATED_WITH_UUID');
                }
            }
        } catch (Exception $e) {
            $this->rollback();
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
        return $count;
    }

    private function processMergeData($entityId, $fileObject, $data)
    {
        $override_data = false;
        $sql = $this->getSqlObject();
        $select = $sql->select();
        $select->from('ox_app_entity')
            ->columns(array("override_data"))
            ->where(array('ox_app_entity.id' => $entityId));
        $response = $this->executeQuery($select)->toArray();
        if (count($response) > 0) {
            $override_data =  $response[0]['override_data'];
        } else {
            throw new ServiceException("Invalid Entity", "entity.invalid");
        }

        if ($override_data) {
            $fields = $data;
        } else {
            $fields = array_merge($fileObject, $data);
        }
        return $fields;
    }

    /**
     * Delete File Service
     * @method deleteFile
     * @param $id ID of File to Delete
     * @return array success|failure response
     */
    public function deleteFile($id, $version)
    {
        $file = new File($this->table);
        $file->loadByUuid($id);
        if (!isset($version)) {
            throw new Exception("Version is not specified, please specify the version");
        }
        $data = array('version' => $version, 'is_active' => 0);
        $file->assign($data);
        try {
            $selectFile = "SELECT * FROM `ox_file` WHERE uuid=:uuid";
            $selectParams = ["uuid" => $id];
            $result = $this->executeQueryWithBindParameters($selectFile, $selectParams)->toArray();
 
            if (!empty($result)) {
                if ($result[0]['created_by'] != AuthContext::get(AuthConstants::USER_ID)) {
                    $this->logger->info("Only user who created task can delete the record");
                    throw new ServiceException("Delete operation cannot be performed", "access.denied", OxServiceException::ERR_CODE_UNAUTHORIZED);
                }
            }
            $this->beginTransaction();
            $file->save();
            $fileInfo = $file->toArray();
            $this->commit();
            // IF YOU DELETE THE BELOW TWO LINES MAKE SURE YOU ARE PREPARED TO CHECK THE ENTIRE INDEXER FLOW
            if (isset($id)) {
                $this->messageProducer->sendQueue(json_encode(array('id' => $id)), 'FILE_DELETED');
            }
        } catch (Exception $e) {
            $this->rollback();
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }

    /**
     * GET File Service
     * @method getFile
     * @param $id ID of File
     * @return array $data
     * @return array Returns a JSON Response with Status Code and Created File.
     */
    public function getFile($id, $latest = false, $accountId = null)
    {
        try {
            $this->logger->info("FILE ID  ------" . json_encode($id));
            if (isset($accountId) && !is_numeric($accountId)) {
                $accountId = $this->getIdFromUuid('ox_account', $accountId);
            } elseif (!isset($accountId)) {
                $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
            } else {
                $accountId = $accountId;
            }

            $this->logger->info("ACCOUNT ID-----".json_encode($accountId));
            // $accountId = isset($accountId) ? $this->getIdFromUuid('ox_account', $accountId) :
            // AuthContext::get(AuthConstants::ACCOUNT_ID);
            $params = array('id' => $id, 'isActive' => 1);
            // 
            $select = "SELECT oxf.id, oxf.uuid, oxf.data, ou.uuid as ownerId, oae.uuid as entity_id,oae.id as entityId, oxf.fileTitle as title from ox_file oxf 
            inner join ox_user as ou on `oxf`.created_by = `ou`.id
                        inner join ox_app_entity oae on oae.id = oxf.entity_id";
            $where = " where oxf.uuid = :id and oxf.is_active = :isActive ";
            $result = $this->executeQueryWithBindParameters($select . $where, $params)->toArray();
            if (count($result) == 0) {
                return 0;
            }
            $res = $this-> checkIfEntityIsOfferedByBusiness($result[0]['entityId']);
            if($res){
                $select .= " inner join ox_file_participant oxfp on oxfp.file_id = oxf.id";
                $where .= "AND oxfp.account_id = :accountId";
            }else{
                $where .= "AND oxf.account_id = :accountId";
            }
            $params['accountId'] = $accountId;
            $selectQuery = $select.$where;
            $this->logger->info("Executing query $selectQuery with params " . json_encode($params));
            $result = $this->executeQueryWithBindParameters($selectQuery, $params)->toArray();
            $this->logger->info("FILE DATA ------" . json_encode($result));
            if (count($result) > 0) {
                $this->logger->info("FILE ID  ------" . json_encode($result));
                if ($result[0]['data']) {
                        $result[0]['data'] = json_decode($result[0]['data'], true);
                        $result[0]['data']['myId'] = $result[0]['id'];
                    }
                    unset($result[0]['id']);
                    $this->logger->info("FILE DATA SUCCESS ------" . print_r($result[0], true));
                    return $result[0];
            }
            return 0;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }

    private function checkIfEntityIsOfferedByBusiness($entityId){
        $select = "SELECT entity_id from ox_account_offering where entity_id =:entityId";
        $params = ['entityId' => $entityId];
        $response = $this->executeQueryWithBindParameters($select, $params)->toArray();
        return count($response) > 0;
    }

    public function getFileByWorkflowInstanceId($workflowInstanceId, $isProcessInstanceId = true)
    {
        if ($isProcessInstanceId) {
            $where = "ox_workflow_instance.process_instance_id=:workflowInstanceId";
        } else {
            $where = "ox_workflow_instance.id=:workflowInstanceId";
        }
        try {
            $select = "SELECT ox_file.id,ox_file.uuid as fileId, ox_file.data, ox_file.last_workflow_instance_id from ox_file
            inner join ox_workflow_instance on ox_workflow_instance.file_id = ox_file.id
            where ox_file.account_id=:accountId and $where and ox_file.is_active =:isActive";
            $whereQuery = array("accountId" => AuthContext::get(AuthConstants::ACCOUNT_ID),
                "workflowInstanceId" => $workflowInstanceId,
                "isActive" => 1);
            $result = $this->executeQueryWithBindParameters($select, $whereQuery)->toArray();
            if (count($result) > 0) {
                $result[0]['data'] = json_decode($result[0]['data'], true);
                $result[0]['data']['fileId'] = $result[0]['fileId'];
                foreach ($result[0]['data'] as $key => $value) {
                    if (is_string($value)) {
                        $tempValue = json_decode($value, true);
                        if (isset($tempValue)) {
                            $result[0]['data'][$key] = $tempValue;
                        }
                    }
                }
                $result[0]['data'] = json_encode($result[0]['data']);
                return $result[0];
            }
            return 0;
        } catch (Exception $e) {
            $this->logger->log(Logger::ERR, $e->getMessage());
            throw $e;
        }
    }

    // TODO: Additional Insured - Difference cannot be found. fileAttribute ->index column
    /**
     * @ignore checkFields
     * @param entityId
     * @param fieldData
     * @param fileId
     * @param allFields - default true includes all fields
     *                            false includes only indexedFields and document fields
     */
    protected function checkFields($entityId, &$fieldData, $fileId, $allFields = true)
    {
        $this->logger->debug("Entering into checkFields method---EntityId : " . $entityId);
        $required = array();
        if (isset($entityId)) {
            $filter = "";
            if (!$allFields) {
                $filter = " and (ox_field.index = 1 OR ox_field.type IN('file','document')) OR childFieldsTable.type IN('document','file')";
            }
            $query = "SELECT ox_field.*,group_concat(childFieldsTable.name order by childFieldsTable.name separator ',') child_fields from ox_field
            inner join ox_app_entity on ox_app_entity.id = ox_field.entity_id
            left join ox_field childFieldsTable on ox_field.id = childFieldsTable.parent_id
            where ox_app_entity.id=? and ox_field.parent_id is NULL $filter group by ox_field.id;";

            $where = array($entityId);
            $this->logger->debug("Executing query - $query with  params" . json_encode($where));
            $fields = $this->executeQueryWithBindParameters($query, $where)->toArray();
            $this->logger->debug("Query result got " . count($fields) . " fields");
        } else {
            $this->logger->debug("No Entity ID");
            throw new ServiceException("Invalid Entity", "entity.invalid");
        }
        $fileArray = null;
        $indexedFileArray = null;
        $documentArray = null;
        $keyValueFields = null;
        $indexedFields = null;
        $documentFields = null;
        if ($allFields) {
            $fileArray = $this->getFileAttributes($fileId, 'ox_file_attribute');
            $keyValueFields = array();
        } else {
            $indexedFileArray = $this->getFileAttributes($fileId, 'ox_indexed_file_attribute');
            $documentArray = $this->getFileAttributes($fileId, 'ox_file_document');
            $indexedFields = array();
            $documentFields = array();
        }

        $i = 0;

        $childFields = array();
        if (!empty($fields)) {
            foreach ($fields as $field) {
                if (!in_array($field['name'], array_keys($fieldData))) {
                    continue;
                }
                if (!$allFields && ($field['index'] != 0 || $field['type'] == 'document' || $field['type'] == 'file'|| $field['child_fields'])) {
                    $indexedField = array();
                        
                    if ($field['index'] == 0) {
                        $fileDataArray =  &$documentArray;
                        $fileFields = &$documentFields;
                    } else {
                        $fileDataArray =  &$indexedFileArray;
                        $fileFields = &$indexedFields;
                    }
                    $fieldvalue = isset($fieldData[$field['name']]) ? (is_array($fieldData[$field['name']]) ? json_encode($fieldData[$field['name']]) : $fieldData[$field['name']]) : null;
                    $indexedField = array_merge($indexedField, $this->generateFieldPayload($field, $fieldvalue, $entityId, $fileId, $fileDataArray, $allFields));
                    if ($field['index'] == 1 && $indexedField['field_value_type'] == 'OTHER') {
                        throw new ServiceException("Unsupported data type for indexing for field - ".$field['name']." with dataType -".$field['data_type'], "invalid.datatype");
                    }
                    unset($indexedField[$field['name']]);
                    $childFieldsPresent = false;
                    if ($field['index'] == 1) {
                        unset($indexedField['sequence']);
                        unset($indexedField['childFields']);
                    } else {
                        $indexedField['field_value']=is_array($fieldvalue) ? json_encode($fieldvalue):$fieldvalue;
                        unset($indexedField['field_value_text']);
                        unset($indexedField['field_value_type']);
                        unset($indexedField['field_value_numeric']);
                        unset($indexedField['field_value_boolean']);
                        unset($indexedField['field_value_date']);
                        if (isset($indexedField['childFields']) && count($indexedField['childFields']) > 0) {
                            foreach ($indexedField['childFields'] as $childField) {
                                array_push($childFields, $childField);
                            }
                            $childFieldsPresent = true;
                        }
                        unset($indexedField['childFields']);
                    }
                    if ($field['type'] == 'document' || $field['type'] == 'file' || $field['index'] == 1) {
                        $fileFields[] = $indexedField;
                    }
                    $fieldData[$field['name']] = $fieldvalue;
                    unset($indexedField);
                }
                if ($allFields) {
                    $fieldvalue = isset($fieldData[$field['name']]) ? (is_array($fieldData[$field['name']]) ? json_encode($fieldData[$field['name']]) : $fieldData[$field['name']]) : null;
                    $keyValueFields[$i]['field_value']=$fieldvalue;
                    $keyValueFields[$i] = array_merge($keyValueFields[$i], $this->generateFieldPayload($field, $fieldvalue, $entityId, $fileId, $fileArray, $allFields));

                    if ($field['type'] == 'file') {
                        $keyValueFields['data'][$field['name']] = isset($keyValueFields[$i][$field['name']]) ? $keyValueFields[$i][$field['name']] : array();
                    } else {
                        $keyValueFields['data'][$field['name']] = isset($fieldData[$field['name']]) ? $fieldData[$field['name']] : null;
                    }

                    if (isset($keyValueFields[$i]['childFields']) && count($keyValueFields[$i]['childFields']) > 0) {
                        foreach ($keyValueFields[$i]['childFields'] as $childField) {
                            array_push($childFields, $childField);
                        }
                    }
                    if (isset($keyValueFields[$i]['data'])) {
                        $keyValueFields['data'][$field['name']] = $keyValueFields[$i]['data'];
                    }
                    unset($keyValueFields[$i]['data']);
                    unset($keyValueFields[$i]['childFields']);
                    unset($keyValueFields[$i][$field['name']]);
                }
                unset($fieldvalue);
                $i++;
            }
        }
        if (!empty($childFields)) {
            if (!$allFields) {
                $fileFields = &$documentFields;
            } else {
                $fileFields = &$keyValueFields;
            }

            $this->collateChildFields($childFields, $fileFields, $allFields);
        }
        $this->logger->debug("Key Values - " . json_encode($keyValueFields));
        $this->logger->debug("Indexed Values - " . json_encode($indexedFields));
        return array('validFields' => $keyValueFields,'indexedFields' => $indexedFields, 'documentFields' => $documentFields);
    }

    private function collateChildFields($childFields, &$fileFields, $allFields)
    {
        $index = count($fileFields);
        foreach ($childFields as $child) {
            if ($allFields) {
                if (isset($child['data'])) {
                    $keyValueFields['data'][$field['name']] = $child['data'];
                    unset($child['data']);
                } elseif (array_key_exists('data', $child)) {
                    unset($child['data']);
                }
            } else {
                unset($child['field_value_type']);
                unset($child['field_value_text']);
                unset($child['field_value_numeric']);
                unset($child['field_value_boolean']);
                unset($child['field_value_date']);
            }
            $fileFields[$index] = $child;
            if (isset($child['childFields']) && !empty($child['childFields'])) {
                unset($fileFields[$index]['childFields']);
                $this->collateChildFields($child['childFields'], $fileFields, $allFields);
            }
            $index++;
        }
    }

    private function getFileAttributes($fileId, $attributeTable, $parentId = null)
    {
        $filter = "";
        $join = "";
        $whereParams = array('fileId' => $fileId);
        if ($attributeTable == 'ox_file_attribute' && !$parentId) {
            $filter = "and fa.sequence is null";
        } elseif ($attributeTable != 'ox_indexed_file_attribute' && $parentId) {
            $filter = "and fa.sequence is not null and f.parent_id = :parentId order by fa.sequence asc";
            if ($attributeTable == 'ox_file_document') {
                $filter = "and f.type IN ('document','file') $filter";
            }
            $join = "inner join ox_field f on f.id = fa.field_id";
            $whereParams['parentId'] = $parentId;
        }
        $sqlQuery = "SELECT fa.* from $attributeTable fa $join where fa.file_id=:fileId $filter";
        $this->logger->debug("Executing query - $sqlQuery with  params" . json_encode($whereParams));
        $fileArray = $this->executeQueryWithBindParameters($sqlQuery, $whereParams)->toArray();
        $this->logger->debug("Query result got " . count($fileArray) . " records");
        if (!$parentId) {
            return $fileArray;
        }
        $result = array();
        $sequenceArray = null;
        foreach ($fileArray as $value) {
            $sequence = $value['sequence'];
            if (!isset($result[$sequence])) {
                $result[$sequence] = array();
            }
            $sequenceArray = &$result[$sequence];
            $sequenceArray[] = $value;
        }

        return $result;
    }
    private function generateFieldPayload($field, &$fieldvalue, $entityId, $fileId, $fileArray, $allFields, &$rowNumber = -1)
    {
        $fieldData = array();
        if (($key = array_search($field['id'], array_column($fileArray, 'field_id'))) > -1) {
            $fieldData['id'] = $fileArray[$key]['id'];
        } else {
            $fieldData['id'] = null;
        }
        $fieldData['sequence'] = null;
        if ($rowNumber > -1) {
            $fieldData['sequence'] = $rowNumber;
        } else {
            $rowNumber = 0;
        }
        $fieldData['file_id'] = $fileId;
        $fieldData['field_id'] = $field['id'];
        $fieldData['account_id'] = (empty($fileArray[$key]['account_id']) ? AuthContext::get(AuthConstants::ACCOUNT_ID) : $fileArray[$key]['account_id']);
        $fieldData['created_by'] = (empty($fileArray[$key]['created_by']) ? AuthContext::get(AuthConstants::USER_ID) : $fileArray[$key]['created_by']);
        $fieldData['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $fieldData['date_created'] = (!isset($fileArray[$key]['date_created']) ? date('Y-m-d H:i:s') : $fileArray[$key]['date_created']);
        $fieldData['date_modified'] = date('Y-m-d H:i:s');
        $dataType = $field['data_type'];
        switch ($dataType) {
            case 'text':
                $fieldData['field_value_type'] = 'TEXT';
                $fieldData['field_value_text'] = $fieldvalue;
                $fieldData['field_value_numeric'] = null;
                $fieldData['field_value_boolean'] = null;
                $fieldData['field_value_date'] = null;
                $fieldData[$field['name']] = $fieldvalue;
                break;
            case 'numeric':
                $fieldData['field_value_type'] = 'NUMERIC';
                $fieldData['field_value_text'] = null;
                $fieldData['field_value_numeric'] = (double)$fieldvalue;
                $fieldData[$field['name']] = $fieldData['field_value_numeric'];
                $fieldData['field_value_boolean'] = null;
                $fieldData['field_value_date'] = null;
                break;
            case 'boolean':
                if (isset($boolVal)) {
                    unset($boolVal);
                }
                $boolVal = false;
                if ((is_bool($fieldvalue) && $fieldvalue == true) || (is_string($fieldvalue) && $fieldvalue == "true") || (is_int($fieldvalue) && $fieldvalue == 1)) {
                    $boolVal = true;
                    $fieldvalue = 1;
                } else {
                    $boolVal = false;
                    $fieldvalue = 0;
                }
                $fieldData['field_value_type'] = 'BOOLEAN';
                $fieldData['field_value_text'] = null;
                $fieldData['field_value_numeric'] = null;
                $fieldData['field_value_boolean'] = $fieldvalue;
                $fieldData['field_value_date'] = null;
                $fieldData[$field['name']] = $boolVal;
                break;
            case 'date':
            case 'datetime':
                $fieldData['field_value_type'] = 'DATE';
                $fieldData['field_value_text'] = null;
                $fieldData['field_value_numeric'] = null;
                $fieldData['field_value_boolean'] = null;
                $format = $dataType == 'date' ? 'Y-m-d' : 'Y-m-d H:i:s';
                if (is_string($fieldvalue) && date_create($fieldvalue)) {
                    $fieldData['field_value_date'] = date_format(date_create($fieldvalue), $format);
                } else {
                    $fieldData['field_value_date'] = date_format(date_create(), $format);
                    ;
                }
                $fieldData[$field['name']] = $fieldData['field_value_date'];
                break;
            case 'list':
                $fieldData['field_value_type'] = 'OTHER';
                $fieldData['field_value_text'] = null;
                $fieldData['field_value_numeric'] = null;
                $fieldData['field_value_boolean'] = null;
                $fieldData['field_value_date'] = null;
                if ($field['type']=='file') {
                    $attachmentsArray = is_string($fieldvalue) ? json_decode($fieldvalue, true) : $fieldvalue;
                    $finalAttached = array();
                    if (!isset($attachmentsArray)) {
                        $attachmentsArray = array();
                    }
                    if (is_array($attachmentsArray) && !empty($attachmentsArray)) {
                        foreach ($attachmentsArray as $attachment) {
                            $attachment = is_string($attachment) ? json_decode($attachment, true) : $attachment;
                            if (!empty($attachment)) {
                                $finalAttached[] = $this->appendAttachmentToFile($attachment, $field, $fileId);
                            }
                        }
                    }
                    $fieldData['field_value']=json_encode($finalAttached);
                    $fieldData[$field['name']] = $finalAttached;
                    break;
                } else {
                    $fieldData[$field['name']] = $fieldvalue;
                    break;
                }
                // no break
            default:
                $fieldData['field_value_type'] = 'OTHER';
                $fieldData['field_value_text'] = null;
                $fieldData['field_value_numeric'] = null;
                $fieldData['field_value_boolean'] = null;
                $fieldData['field_value_date'] = null;
                if ($field['type']=='file') {
                    if (is_string($fieldvalue)) {
                        $attachmentsArray = json_decode($fieldvalue, true);
                    } else {
                        $attachmentsArray = $fieldvalue;
                    }
                    $finalAttached = array();
                    if (!isset($attachmentsArray)) {
                        $attachmentsArray = array();
                    }
                    if (is_array($attachmentsArray) && !empty($attachmentsArray)) {
                        $finalAttached = array();
                        foreach ($attachmentsArray as $attachment) {
                            $attachment = is_string($attachment) ? json_decode($attachment, true) : $attachment;
                            if (!empty($attachment)) {
                                $finalAttached[] = $this->appendAttachmentToFile($attachment, $field, $fileId);
                            }
                        }
                    }
                    $fieldData['field_value']=json_encode($finalAttached);
                    $fieldvalue = $finalAttached;
                    $fieldData[$field['name']] = $finalAttached;
                } else {
                    $fieldData[$field['name']] = $fieldvalue;
                }
            break;
        }
        $fieldvalue = isset($fieldData[$field['name']]) ? $fieldData[$field['name']] : null;
        if (isset($field['child_fields']) && !empty($field['child_fields'])) {
            if (is_string($fieldvalue)) {
                $fieldvalue = json_decode($fieldvalue, true);
            }
            $fldValue = $fieldvalue;
            $fieldData['childFields'] = $this->getChildFieldsData($field, $fldValue, $field['child_fields'], $entityId, $fileId, $rowNumber, $allFields);
            if (is_array($fldValue)) {
                foreach ($fldValue as $i => $value) {
                    foreach ($value as $key => $fVal) {
                        $temp = !is_array($fVal) ? json_decode($fVal) : $fVal;
                        $fieldvalue[$i][$key] = $temp ? $temp : $fVal;
                    }
                }
                
                if (isset($fieldData['childFields']['childFields']) && count($fieldData['childFields']['childFields'])>0) {
                    foreach ($fieldData['childFields']['childFields'] as $childfield) {
                        array_push($fieldData['childFields'], $childfield);
                    }
                    unset($fieldData['childFields']['childFields']);
                }
            }
        } else {
            $fieldData['childFields'] = array();
        }

        return $fieldData;
    }

    public function getChildFieldsData($parentField, &$fieldvalue, $fieldsString, $entityId, $fileId, &$rowNumber, $allFields)
    {
        $filter = "";
        if (!$allFields) {
            $filter = "and ox_field.type IN ('document','file')";
        }
        $query = "SELECT ox_field.*,group_concat(childFieldsTable.name order by childFieldsTable.name separator ',') child_fields from ox_field
            inner join ox_app_entity on ox_app_entity.id = ox_field.entity_id
            left join ox_field childFieldsTable on childFieldsTable.parent_id=ox_field.id
            where ox_app_entity.id=:entityId and ox_field.parent_id =:parentId $filter group by ox_field.id";
        $where = array('entityId'=>$entityId,'parentId'=>$parentField['id']);
        $this->logger->info("Executing query - $query with  params" . json_encode($where));
        $childFields = $this->executeQueryWithBindParameters($query, $where)->toArray();
        $childFieldsArray = array();
        $grandChildren = array();
        if ($allFields) {
            $fileAttributes = $this->getFileAttributes($fileId, 'ox_file_attribute', $parentField['id']);
        } else {
            $fileAttributes = $this->getFileAttributes($fileId, 'ox_file_document', $parentField['id']);
        }
        
        if (count($childFields) > 0) {
            if (is_array($fieldvalue)) {
                $i = 0;
                foreach ($fieldvalue as $k => $value) {
                    $childFieldValues = array();
                    $fileArray = isset($fileAttributes[$rowNumber]) ? $fileAttributes[$rowNumber] : array();
                    foreach ($childFields as $field) {
                        $val = isset($value[$field['name']]) ? (is_array($value[$field['name']]) ? json_encode($value[$field['name']]) : $value[$field['name']]) : null;
                        if ($allFields) {
                            $childFieldsArray[$i]['field_value']=$val;
                        } else {
                            $childFieldsArray[] = array();
                        }
                        $childFieldsArray[$i] = array_merge($childFieldsArray[$i], $this->generateFieldPayload($field, $val, $entityId, $fileId, $fileArray, $allFields, $rowNumber));
                        if (count($childFieldsArray[$i]['childFields']) > 0) {
                            foreach ($childFieldsArray[$i]['childFields'] as $childField) {
                                array_push($grandChildren, $childField);
                            }
                        } else {
                            unset($childFieldsArray[$i]['childFields']);
                        }
                        $childFieldValues[$field['name']] = isset($value[$field['name']]) ? $value[$field['name']] : null;
                            
                        if ($field['type'] == 'file') {
                            $childFieldValues[$field['name']] = is_array($val) ? json_encode($val) : $val;
                        }
                        unset($childFieldsArray[$i][$field['name']]);
                        $i++;
                    }
                    $fieldvalue[$k] = $childFieldValues;
                    $rowNumber ++;
                }
            }
            if (isset($grandChildren) && count($grandChildren)>0) {
                $childFieldsArray['childFields'] = $grandChildren;
            }
        }
        return $childFieldsArray;
    }

    public function checkFollowUpFiles($appId, $data)
    {
        try {
            $fieldWhereQuery = $this->generateFieldWhereStatement($data);
            if (!empty($fieldWhereQuery['joinQuery'] && !empty($fieldWhereQuery['whereQuery']))) {
                $queryStr = "Select *,f.name as appName from ox_file as a
                join ox_form as b on (a.entity_id = b.entity_id)
                join ox_form_field as c on (c.form_id = b.id)
                join ox_field as d on (c.field_id = d.id)
                join ox_app as f on (f.id = b.app_id)
                " . $fieldWhereQuery['joinQuery'] . "
                where f.id = " . $data['app_id'] . " and b.id = " . $data['form_id'] . " and (" . $fieldWhereQuery['whereQuery'] . ") group by a.id";
                $this->logger->info("Executing query - $queryStr");
                $resultSet = $this->executeQuerywithParams($queryStr);
                return $resultSet->toArray();
            } else {
                return 0;
            }
            // $this->email->sendRemainderEmail($appId, $dataList); //Commenting this line
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
        return array();
    }

    private function generateFieldWhereStatement($data)
    {
        $prefix = 1;
        $whereQuery = "";
        $joinQuery = "";
        $returnQuery = array();
        $fieldList = $data['field_list'];
        try {
            if (!empty($fieldList)) {
                foreach ($fieldList as $key => $val) {
                    $tablePrefix = "tblf" . $prefix;
                    $fieldDetails = $this->getFieldDetails($key, $data['entity_id']);
                    $valueColumn = $this->getValueColumn($fieldDetails);
                    if (!empty($val) && !empty($fieldId)) {
                        $joinQuery .= "left join ox_file_attribute as " . $tablePrefix . " on (a.id =" . $tablePrefix . ".file_id) ";
                        $whereQuery .= $tablePrefix . ".field_id =" . $fieldDetails['id'] . " and " . $tablePrefix . ".".$valueColumn." ='" . $val . "' and ";
                    }
                    $prefix += 1;
                }
            }
            $whereQuery .= '1';
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
        return $returnQuery = array("joinQuery" => $joinQuery, "whereQuery" => $whereQuery);
    }

    public function getValueColumn($field)
    {
        $type = $field['data_type'];
        if ($type=='numeric' || $type=='Date') {
            $valueColumn = 'field_value_'.strtolower($type);
        } elseif ($type=='textarea' || $type=='form') {
            $valueColumn='field_value';
        } else {
            $valueColumn= 'field_value_text';
        }
        return $valueColumn;
    }

    public function getFieldDetails($fieldName, $entityId = null)
    {
        try {
            if (isset($this->fieldDetails[$fieldName])) {
                return $this->fieldDetails[$fieldName];
            }
            if ($entityId) {
                $entityWhere = "entity_id = " . $entityId . "";
            } else {
                $entityWhere = "1";
            }
            $queryStr = "select * from ox_field where name = '" . $fieldName . "' and " . $entityWhere . "";
            $resultSet = $this->executeQuerywithParams($queryStr);
            $dataSet = $resultSet->toArray();
            if (count($dataSet) == 0) {
                return 0;
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
        $this->fieldDetails[$fieldName]=$dataSet[0];
        return $dataSet[0];
    }

    private function processWorkflowFilter($params, &$workflowJoin, &$workflowFilter, &$queryParams)
    {
        if (isset($params['workflowId'])) {
            // Code to get the entityID from appId, we need this to get the correct fieldId for the filters
            $select1 = "SELECT * from ox_workflow where uuid = :uuid";
            $selectQuery1 = array("uuid" => $params['workflowId']);
            $worflowArray = $this->executeQuerywithBindParameters($select1, $selectQuery1)->toArray();

            $workflowId = $this->getIdFromUuid('ox_workflow', $params['workflowId']);
            if (!$workflowId) {
                throw new ServiceException("Workflow Does not Exist", "app.forworkflownot.found");
            } else {
                $workflowFilter = " ow.id = :workflowId AND ";
                $queryParams['workflowId'] = $workflowId;
                $workflowJoin = "left join ox_workflow_deployment as wd on wd.id = wi.workflow_deployment_id left join ox_workflow as ow on ow.id = wd.workflow_id";
            }
        }
    }

    private function processCreatedDateFilter(&$params, &$createdFilter, &$queryParams)
    {
        if (isset($params['gtCreatedDate'])) {
            $createdFilter .= " of.date_created >= :gtCreatedDate AND ";
            $params['gtCreatedDate'] = str_replace('-', '/', $params['gtCreatedDate']);
            $queryParams['gtCreatedDate'] = date('Y-m-d', strtotime($params['gtCreatedDate']));
        }
        if (isset($params['ltCreatedDate'])) {
            $createdFilter .= " of.date_created < :ltCreatedDate AND ";
            $params['ltCreatedDate'] = str_replace('-', '/', $params['ltCreatedDate']);
            /* modified date: 2020-02-11, today's date: 2020-02-11, if we use the '<=' operator then
             the modified date converts to 2020-02-11 00:00:00 hours. Inorder to get all the records
             till EOD of 2020-02-11, we need to use 2020-02-12 hence [+1] added to the date. */
            $queryParams['ltCreatedDate'] = date('Y-m-d', strtotime($params['ltCreatedDate'] . "+1 days"));
        }
    }
    private function processParticipantFiltering($accountId, &$fromQuery, &$whereQuery, &$queryParams)
    {
        $query = "SELECT id from ox_account_business_role where account_id = :accountId";
        $params = ["accountId" => $accountId];
        $result = $this->executeQueryWithBindParameters($query, $params)->toArray();
        if (count($result) == 0) {
            return false;
        }
        $fromQuery .= " INNER JOIN ox_file_participant ofp on `of`.id = ofp.file_id";
        if ($whereQuery != "") {
            $whereQuery .= " AND ";
        }

        $whereQuery .= " ofp.account_id = :accountId";
        $queryParams['accountId'] = $accountId;
        return true;
    }

    private function processUserFilter($params, $appId, &$fromQuery, &$whereQuery, &$queryParams)
    {
        if (isset($params['userId'])) {
            if ($params['userId'] == 'me') {
                $userId = AuthContext::get(AuthConstants::USER_ID);
            } else {
                $userId = $this->getIdFromUuid('ox_user', $params['userId']);
                if (!$userId) {
                    throw new ServiceException("User Does not Exist", "app.forusernot.found");
                }
            }
            $appFilter = "";
            if (isset($appId)) {
                $appFilter = "and app_id = :appId";
                $identifierParams['appId'] = $appId;
            }
            $identifierQuery = "select identifier_name,identifier from ox_wf_user_identifier where user_id=:userId $appFilter";
            $identifierParams['userId'] = $userId;
            $getIdentifier = $this->executeQueryWithBindParameters($identifierQuery, $identifierParams)->toArray();
            if ($whereQuery != "") {
                $whereQuery .= " AND ";
            }
            if (isset($getIdentifier) && count($getIdentifier)>0) {
                $fromQuery .= " INNER JOIN ox_indexed_file_attribute ofa on (ofa.file_id = of.id) inner join ox_field as d on (ofa.field_id = d.id and d.name= :fieldName)
                    INNER join ox_entity_identifier as oei on oei.identifier = '".$getIdentifier[0]['identifier_name']."' AND oei.entity_id = en.id ";
                $queryParams['fieldName'] = $getIdentifier[0]['identifier_name'];
                $queryParams['identifier'] = $getIdentifier[0]['identifier'];
                $whereQuery .= " ofa.field_value_text = :identifier ";
            } else {
                $whereQuery .= " `of`.created_by = :userId";
                $queryParams['userId'] = $userId;
            }
        }
    }

    public function getFileList($appUUid, $params, $filterParams = null)
    {
        $this->logger->info("Inside File List API - with params - " . json_encode($params));
        $accountId = isset($params['accountId']) ? $this->getIdFromUuid('ox_account', $params['accountId']) : AuthContext::get(AuthConstants::ACCOUNT_ID);
        $snooze = false;

        if(isset($filterParams['filter']))
        {
            $filtersCopy = is_string($filterParams['filter'])? json_decode($filterParams['filter'],true):$filterParams['filter'];
            if(isset($filtersCopy[0]['snooze']))
            {
                $snooze = $filtersCopy[0]['snooze'];
                if($snooze == '0' || strtolower($snooze) == 'false')
                {
                    $snooze = false;
                }
                elseif($snooze == '1' || strtolower($snooze) == 'true')
                {
                    $snooze = true;
                }
    
            }
        }
        $appFilter = "";
        $appIdClause = "";
        $queryParams = array();
        if (isset($appUUid)) {
            $appId = $this->getIdFromUuid('ox_app', $appUUid);
            $appIdClause = "AND app_id = :appId";
            $selectQuery['appId'] = $appId;
            $appFilter = "inner join ox_app as oa on (oa.id = en.app_id AND oa.id = :appId)";
            $queryParams['appId'] = $appId;
        }
        $select = "SELECT * from ox_app_registry where account_id = :accountId $appIdClause";
        $selectQuery["accountId"] = $accountId;
        $result = $this->executeQuerywithBindParameters($select, $selectQuery)->toArray();
        
        if (count($result) == 0) {
            throw new ServiceException("App Does not belong to the account", "app.for.account.not.found");
        }

        $statusFilter = "";
        $createdFilter = "";
        $entityFilter = "";
        $whereQuery = "";
        $this->getFileFilters($params, $entityFilter, $queryParams);
        $workflowJoin = "";
        $workflowFilter = "";
        
        $this->processWorkflowFilter($params, $workflowJoin, $workflowFilter, $queryParams);
        $this->processCreatedDateFilter($params, $createdFilter, $queryParams);
        if ($appFilter == "") {
            $appQuery = " inner join ox_app as oa on (oa.id = en.app_id)";
        } else {
            $appQuery = $appFilter;
        }
        $where = " $workflowFilter $entityFilter $createdFilter";
        $fromQuery = " from ox_file as `of`
        inner join ox_user as ou on `of`.created_by = `ou`.id
        left join ox_file_assignee as oxfa on oxfa.file_id = `of`.id and oxfa.assignee = 1
        left join ox_user as oxu on oxu.id = oxfa.user_id
        inner join ox_app_entity as en on en.id = `of`.entity_id $appQuery ";
        if (!$this->processParticipantFiltering($accountId, $fromQuery, $whereQuery, $queryParams)) {
            if ($whereQuery != "") {
                $whereQuery .= " AND ";
            }
            $whereQuery .= " `of`.account_id = :accountId";
            $queryParams['accountId'] = $accountId;
        }
        if (!isset($appId)) {
            $appId = null;
        }
        $this->processUserFilter($params, $appId, $fromQuery, $whereQuery, $queryParams);
        
        //TODO INCLUDING WORKFLOW INSTANCE SHOULD BE REMOVED. THIS SHOULD BE PURELY ON FILE TABLE
        $fromQuery .= " left join ox_workflow_instance as wi on (`of`.last_workflow_instance_id = wi.id) $workflowJoin";
        if (isset($params['workflowStatus'])) {
            $fromQuery .= " left join (select max(id) as id, workflow_instance_id from ox_activity_instance group by workflow_instance_id) lai on lai.workflow_instance_id = wi.id
                            left join ox_activity_instance ai on ai.id = lai.id ";
            if ($whereQuery != "") {
                $whereQuery .= " AND ";
            }
            $whereQuery .= " (ai.status = '" . $params['workflowStatus'] . "' OR (ai.status is null AND wi.status = '".$params['workflowStatus']."' )) ";
        }
        $sort = "";
        $field = "";
        $pageSize = " LIMIT 10";
        $offset = " OFFSET 0";
        $this->processFilterParams($fromQuery, $whereQuery, $sort, $pageSize, $offset, $field, $filterParams);
        $this->getFileFilterClause($whereQuery, $where);
        $where .= $snooze==false?" AND COALESCE(is_snoozed,0) !=1 ":" AND COALESCE(is_snoozed,0) !=0 ";
        try {
            $select = "SELECT DISTINCT SQL_CALC_FOUND_ROWS of.data,of.start_date,of.end_date,of.status, of.id as myId, of.account_id,of.rygStatus as rygStatus,of.uuid,of.version as version,  wi.status as workflowStatus, wi.process_instance_id as workflowInstanceId,of.date_created,oxu.name as assignedUser,of.date_modified,ou.name as created_by,en.name as entity_name,en.uuid as entity_id,oa.name as appName $field $fromQuery $where $sort $pageSize $offset";
            $this->logger->info("Executing query - $select with params - " . json_encode($queryParams));
            $resultSet = $this->executeQueryWithBindParameters($select, $queryParams)->toArray();
            $countQuery = "SELECT FOUND_ROWS();";
            $this->logger->info("Executing File listquery - $countQuery with params - " . json_encode($queryParams));
            $countResultSet = $this->executeQueryWithBindParameters($countQuery, $queryParams)->toArray();
            if (isset($filterParams['columns'])) {
                $filterParams['columns'] = json_decode($filterParams['columns'], true);
            }
            if ($resultSet) {
                $i = 0;
                foreach ($resultSet as $file) {
                    if ($file['data']) {
                        $content = json_decode($file['data'], true);
                        if ($content) {
                            if (isset($filterParams['columns'])) {
                                foreach ($filterParams['columns'] as $column) {
                                    isset($content[$column]) ? $file[$column] = $content[$column] : null;
                                }
                                if (isset($file["data"])) {
                                    unset($file["data"]);
                                }
                                $resultSet[$i] = ($file);
                            } else {
                                $resultSet[$i] = array_merge($file, $content);
                            }
                        }
                    }
                    $i++;
                }
            }
            return array('data' => $resultSet, 'total' => $countResultSet[0]['FOUND_ROWS()']);
        } catch (Exception $e) {
            print_r($e->getMessage());
            throw new ServiceException($e->getMessage(), "app.mysql.error");
        }
    }

    public function getFileDocumentList($params)
    {
        $selectQuery = 'select distinct ox_field.text,ox_field.data_type, fd.* from ox_file
        inner join ox_file_document fd on fd.file_id = ox_file.id
        inner join ox_field on ox_field.id = fd.field_id
        where ox_field.type in (:dataType1 , :dataType2)
        and ox_file.uuid=:fileUuid';
        $selectQueryParams = array(
            'fileUuid' => $params['fileId'],
            'dataType1' => 'document',
            'dataType2' => 'file');
        $this->logger->info("Executing query $selectQuery File with params - " . json_encode($selectQueryParams));
        $documentsArray = array();
        try {
            $selectResultSet = $this->executeQueryWithBindParameters($selectQuery, $selectQueryParams)->toArray();
            $this->logger->info("GET Document List- " . json_encode($selectResultSet));
            foreach ($selectResultSet as $result) {
                if (!empty($result['field_value'])) {
                    $jsonValue =  json_decode($result['field_value'], true);
                    if (!isset($documentsArray[$result['text']])) {
                        $documentsArray[$result['text']] =  $jsonValue;
                    } else {
                        $documentsArray[$result['text']] = array_merge($documentsArray[$result['text']], $jsonValue);
                    }
                }
            }
            foreach ($documentsArray as $key=>$docItem) {
                if (isset($docItem) && !isset($docItem[0]['file']) && !empty($docItem)) {
                    $parseDocData = array();
                    foreach ($docItem as $document) {
                        if (is_array($document) && isset($document[0])) {
                            foreach ($document as $doc) {
                                $this->parseDocumentData($parseDocData, $doc);
                            }
                        } else {
                            $this->parseDocumentData($parseDocData, $document);
                        }
                    }
                    $documentsArray[$key] =array('value' => $parseDocData,'type' => isset($document) ? 'document' : 'file');
                } else {
                    $documentsArray[$key] =array('value' => $docItem,'type' => 'file');
                }
            }
            return $documentsArray;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            return 0;
        }
    }

    private function parseDocumentData(&$parseArray, $documentItem)
    {
        if (empty($documentItem)) {
            return;
        }
        if (is_string($documentItem)) {
            $fileType = explode(".", $documentItem);
            $fileName = explode("/", $documentItem);
            if (isset($fileType[1])) {
                array_push(
                    $parseArray,
                    array('file' => $documentItem,
                      'type'=> 'file/' . $fileType[1],
                      'originalName'=> end($fileName)
                  )
                );
            }
        } else {
            $this->logger->info("ParseDocument data- " . json_encode($documentItem));
            array_push($parseArray, $documentItem);
        }
    }

    public function getFieldType($value, $prefix)
    {
        switch ($value['data_type']) {
            case 'Date':
            case 'date':
                $castString = "CAST($prefix.field_value AS DATETIME)";
                break;
            case 'int':
                $castString = "CAST($prefix.field_value AS INT)";
                break;
            default:
                $castString = "($prefix.field_value)";
        }
        return $castString;
    }

    public function processFilters($filterList)
    {
        $operator = $filterList['operator'];
        $field = $filterList['field'];
        $operatorp1 = '';
        $operatorp2 = '';
        if ($operator == 'startswith') {
            $operatorp2 = '%';
            $operation = ' like ';
            $integerOperation = "=";
        } elseif ($operator == 'endswith') {
            $operatorp1 = '%';
            $operation = ' like ';
            $integerOperation = "=";
        } elseif ($operator == 'eq') {
            $operation = ' = ';
            $integerOperation = "=";
        } elseif ($operator == 'neq') {
            $operation = ' <> ';
            $integerOperation = "<>";
        } elseif ($operator == 'contains') {
            $operatorp1 = '%';
            $operatorp2 = '%';
            $operation = ' like ';
            $integerOperation = "=";
        } elseif ($operator == 'doesnotcontain') {
            $operatorp1 = '%';
            $operatorp2 = '%';
            $operation = ' NOT LIKE ';
            $integerOperation = "<>";
        } elseif ($operator == 'isnull' || $operator == 'isempty') {
            $value = '';
            $operation = ' = ';
            $integerOperation = "=";
        } elseif ($operator == 'isnotnull' || $operator == 'isnotempty') {
            $value = '';
            $operation = ' <> ';
            $integerOperation = "=";
        } elseif ($operator == 'lte') {
            $operation = ' <= ';
            $integerOperation = "<=";
        } elseif ($operator == 'lt') {
            $operation = ' < ';
            $integerOperation = "<";
        } elseif ($operator == 'gt') {
            $operation = ' > ';
            $integerOperation = ">";
        } elseif ($operator == 'gte') {
            $operation = ' >= ';
            $integerOperation = ">=";
        } else {
            $operatorp1 = '%';
            $operatorp2 = '%';
            $operation = ' like ';
        }

        return $returnData = array(
            "operation" => $operation,
            "operator1" => $operatorp1,
            "operator2" => $operatorp2,
            "integerOperation" => $integerOperation,
        );
    }

    public function cleanData($params)
    {
        unset($params['bos']);
        unset($params['workflowInstanceId']);
        unset($params['activityInstanceId']);
        unset($params['workflow_instance_id']);
        unset($params['formId']);
        unset($params['workflow_uuid']);
        unset($params['page']);
        unset($params['parentWorkflowInstanceId']);
        unset($params['activityId']);
        unset($params['workflowId']);
        unset($params['form_id']);
        unset($params['fileId']);
        unset($params['app_id']);
        unset($params['appId']);
        unset($params['org_id']);
        unset($params['account_id']);
        unset($params['type']);
        unset($params['business_role']);
        unset($params['orgId']);
        unset($params['accountId']);
        unset($params['created_by']);
        unset($params['date_modified']);
        unset($params['entity_id']);
        unset($params['parent_id']);
        unset($params['submit']);
        unset($params['controller']);
        unset($params['method']);
        unset($params['action']);
        unset($params['access']);
        unset($params['uuid']);
        unset($params['commands']);
        unset($params['last_workflow_instance_id']);
        unset($params['inDraft']);
        unset($params['entity_name']);
        unset($params['version']);
        return $params;
    }

    private function transformValue($value, $fieldDetail)
    {
        $fieldType = $fieldDetail['data_type'];
        if (strtolower($fieldType) === 'date') {
            switch ($value) { //Based on the type of value, we can fetch the date
                case 'today':
                    return Date("Y-m-d");
                    break;
                default:
                    return $value;
            }
        }
        return $value;
    }

    public function getWorkflowInstanceByFileId($fileId, $status=null)
    {
        $select = " SELECT ox_workflow_instance.process_instance_id,ox_workflow_instance.status,ox_workflow_instance.date_created,ox_file.entity_id from ox_workflow_instance INNER JOIN ox_file on ox_file.id = ox_workflow_instance.file_id WHERE ox_file.uuid =:fileId";
        $params = array('fileId' => $fileId);
        if ($status) {
            $select .= " AND ox_workflow_instance.status =:status";
            $params['status'] = $status;
        }
        $select .= " ORDER BY ox_workflow_instance.date_created DESC";
        $result = $this->executeQuerywithBindParameters($select, $params)->toArray();
        return $result;
    }

    public function getChangeLog($entityId, $startData, $completionData, $labelMapping=null,$fileId=null)
    {
        $fieldSelect = "SELECT ox_field.name,ox_field.template,ox_field.type,ox_field.text,ox_field.data_type,COALESCE(parent.name,'') as parentName,COALESCE(parent.text,'') as parentText,parent.data_type as parentDataType FROM ox_field 
                    left join ox_field as parent on ox_field.parent_id = parent.id WHERE ox_field.entity_id=:entityId AND ox_field.type NOT IN ('hidden','file','document','documentviewer') ORDER BY parentName, ox_field.name ASC";

        $fieldParams = array('entityId' => $entityId);
        $resultSet = $this->executeQueryWithBindParameters($fieldSelect, $fieldParams)->toArray();
        $resultData = array();
        $gridResult = array();
        foreach ($resultSet as $key => $value) {
            if ($value['data_type'] == 'json') {
                continue;
            }
            $initialparentData = null;
            $submissionparentData = null;
            if ($value['parentName'] !="") {
                if (isset($gridResult[$value['parentName']])) {
                    $gridResult[$value['parentName']]['fields'][] = $value;
                } else {
                    $initialParentData =  isset($startData[$value['parentName']]) ? $startData[$value['parentName']] : '[]';
                    $initialParentData =   is_string($initialParentData) ? json_decode($initialParentData, true) : $initialParentData;
                    // checkbox check
                    // coverage check within grid
                    $submissionparentData = isset($completionData[$value['parentName']]) ? $completionData[$value['parentName']] : '[]';
                    $submissionparentData =   is_string($submissionparentData) ? json_decode($submissionparentData, true) : $submissionparentData;
                    $gridResult[$value['parentName']] = array("initial" => $initialParentData, "submission" => $submissionparentData, 'fields' => array($value));
                }
            } else {
                $this->buildChangeLog($startData, $completionData, $value, $labelMapping, $resultData,$fileId=$fileId);
            }
        }
        if (count($gridResult) > 0) {
            foreach ($gridResult as $parentName => $data) {
                $initialDataset = $data['initial'];
                $submissionDataset = $data['submission'];
                if (is_array($initialDataset) && is_array($submissionDataset)) {
                    $count = max(count($initialDataset), count($submissionDataset));
                    for ($i = 0; $i < $count; $i++) {
                        $initialRowData = isset($initialDataset[$i]) ? $initialDataset[$i] : array();
                        $submissionRowData = isset($submissionDataset[$i]) ? $submissionDataset[$i] : array();
                        foreach ($data['fields'] as $key => $field) {
                            $this->buildChangeLog($initialRowData, $submissionRowData, $field, $labelMapping, $resultData, $i+1,$fileId=$fileId);
                        }
                    }
                }
            }
        }

        //Remove duplicate entries from result as a result of UUID conversions
        return $resultData;
    }

    public function getFieldValue($startDataTemp, $value, $labelMapping=null,$fileSubscribers=[])
    {

        if (!isset($startDataTemp[$value['name']])) {
            return "";
        }
        $initialData = $startDataTemp[$value['name']];

        if(is_string($initialData)){
            $initialData = strip_tags($initialData);
        }

        if(UuidUtil::isValidUuid($initialData))
        {
            if(array_key_exists($initialData,$fileSubscribers)){
                $initialData = $fileSubscribers[$initialData];
            }
            else {
                return "";
            }
        }
        

        if ($value['data_type'] == 'text') {
            //handle string data being sent
            if (is_string($initialData)) {
                $fieldValue = json_decode($initialData, true);
            } else {
                $fieldValue = $initialData;
            }
            //handle select component values having an object with keys value and label
            if (!empty($fieldValue) && is_array($fieldValue)) {
                //Add Handler for default Labels
                if (isset($fieldValue['label'])) {
                    $initialData = $fieldValue['label'];
                } 


                // else if(isset($fieldValue['username'])){
                //     $initialData = $fieldValue['username'];
                // }
                else {
                    // Add for single values array
                    // if (isset($fieldValue[0]) && count($fieldValue) == 1) {
                    //     $initialData = $fieldValue[0];
                    // } else {
                        //Case multiple values allowed
                    if (count($fieldValue) >= 1) {
                        $initialData ="";
                        foreach ($fieldValue as $k => $v) {
                            if(UuidUtil::isValidUuid($v)){
                                if(array_key_exists($v,$fileSubscribers)){
                                    $initialData .= $fileSubscribers[$v] . ', ';
                                }
                            } else {
                                if (!is_array($v)) {
                                    $initialData .= $v . ', ';
                                } else {
                                //Need to  handle
                                }
                            }
                        }
                        $initialData = substr($initialData, 0, strlen($initialData) - 2);
                    } else {
                        $initialData = "";
                    }
                    // }
                }
            }
        } elseif ($value['data_type'] == 'boolean') {
            if ((is_bool($initialData) && $initialData == false) || (is_string($initialData) && ($initialData=="false" || $initialData=="0"))) {
                $initialData = "No";
            } else {
                $initialData = "Yes";
            }
        } elseif ($value['data_type'] =='list') {
            $radioFields =json_decode($value['template'], true);
            if (is_string($initialData)) {
                $selectValues = json_decode($initialData, true);
            } else {
                if (is_array($initialData)) {
                    $selectValues = $initialData;
                }
            }
            $initialData = "";
            $processed =0;
            if (isset($selectValues) && is_string($selectValues)) {
                $selectValues = json_decode($selectValues, true);
            }
            if (isset($selectValues) && is_array($selectValues)) {
                foreach ($selectValues as $key => $value) {
                    if ($value == 1) {
                        if ($processed == 0) {
                            $radioFields = ArrayUtils::convertListToMap($radioFields['values'], 'value', 'label');
                            $processed = 1;
                        }
                        if (isset($radioFields[$key])) {
                            if ($initialData !="") {
                                $initialData = $initialData . ",";
                            }
                            $initialData .= $radioFields[$key];
                        }
                    }
                }
            }
        } elseif ($value['data_type'] == 'datetime') {
            $initialData = date("Y-m-d", strtotime($initialData));
        }elseif ($value['data_type'] == 'longtext') {
            $initialData = str_replace("&nbsp;", '', $initialData);
        }
        if ((isset($value['type']) && $value['type'] =='radio')|| (isset($value['data_type']) && $value['data_type'] =='radio')) {
            $radioFields =json_decode($value['template'], true);
            if (isset($radioFields['values'])) {
                foreach ($radioFields['values'] as $key => $radiovalues) {
                    if ($initialData == $radiovalues['value']) {
                        $initialData = $radiovalues['label'];
                        break;
                    }
                }
            }
        }
        if ($labelMapping && !empty($initialData) && isset($labelMapping[$initialData])) {
          if ($value['data_type'] == 'numeric') {
            $initialData = $initialData;
          }else {
            $initialData = $labelMapping[$initialData];
          }
        }
        return $initialData;
    }

    private function buildChangeLog($startData, $completionData, $value, $labelMapping, &$resultData, $rowNumber="",$fileId=null)
    {
        $fileSubscribersMapping = [];
        if($fileId)
        {
            $subscribers = $this->subscriberService->getSubscribers($fileId);
            if(count($subscribers) != 0) 
            {
                foreach($subscribers as $subscriber)
                {
                    $fileSubscribersMapping[$subscriber['user_id']] = $subscriber['firstname'] . ' ' . $subscriber['lastname'];
                }
            }
        }

        $initialData =  $this->getFieldValue($startData, $value, $labelMapping,$fileSubscribersMapping);
        $submissionData = $this->getFieldValue($completionData, $value, $labelMapping,$fileSubscribersMapping);
        if ((isset($initialData) && ($initialData != '[]') && (!empty($initialData))) ||
                (isset($submissionData) && ($submissionData != '[]') && (!empty($submissionData)))) {
            $resultData[] = array('name' => $value['name'],
                                       'text' => $value['text'],
                                       'dataType' => $value['data_type'],
                                       'parentName' => $value['parentName'],
                                       'parentText' => $value['parentText'],
                                       'parentDataType' => $value['parentDataType'],
                                       'initialValue' => $initialData,
                                       'submittedValue' => $submissionData,
                                        'rowNumber' => $rowNumber);
        }
    }
    
    public function addAttachment($params,$file, $subFolder = null)
    {
        try {
            $this->logger->info("P---files--".print_r($file,true));
            $fileArray = array();
            $data = array();
            $fileStorage = AuthContext::get(AuthConstants::ACCOUNT_UUID) . "/temp/";
            $data['account_id'] = AuthContext::get(AuthConstants::ACCOUNT_ID);
            $data['created_id'] = AuthContext::get(AuthConstants::USER_ID);
            $data['uuid'] = UuidUtil::uuid();
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $tempname = str_replace(".".$ext, "", $file['name']);
            $data['name'] = $tempname.".".$ext;
            $data['originalName'] = $tempname.".".$ext;
            $data['extension'] = $ext;
            $form = new FileAttachment();
            $data['created_date'] = isset($data['start_date']) ? $data['start_date'] : date('Y-m-d H:i:s');
            $data['type'] = $file['type'];
            if (!preg_match('/^[\w .-]+$/i', $file['name'])) {
                throw new ServiceException("Unsupported Filename.\nFilename cannot contain special characters except -_ and space", "attachment.filename.invalid");
            }
            if (!isset($params['fileId'])) {
                $folderPath = $this->config['APP_DOCUMENT_FOLDER'].$fileStorage.$data['uuid']."/";
                if(isset($subFolder)){
                    $folderPath .= $subFolder . '/';
                }
                $path = realpath($folderPath . $data['name']) ? realpath($folderPath.$data['name']) : FileUtils::truepath($folderPath.$data['name']);
                $data['path'] = $path;
                $data['url'] = $this->config['baseUrl'].(isset($params['appId'])? "/".$params['appId']:"")."/data/".$fileStorage.$data['uuid']."/".$data['name'];
            }else{
                $folderPath = $this->config['APP_DOCUMENT_FOLDER'].AuthContext::get(AuthConstants::ACCOUNT_UUID) . '/' . $params['fileId'] . '/';
                if(isset($subFolder)){
                    $folderPath .= $subFolder . '/';
                }
                $data['file'] = AuthContext::get(AuthConstants::ACCOUNT_UUID) . '/' . $params['fileId'] .(isset($subFolder)? "/".$subFolder:""). '/'.$file['name'];
                $data['url'] = $this->config['baseUrl'].(isset($params['appId'])? "/".$params['appId']:"")."/".AuthContext::get(AuthConstants::ACCOUNT_UUID) . '/' . $params['fileId'] . '/'.$file['name']; // Check nd remove column
                $data['path'] = FileUtils::truepath($folderPath.'/'.$file['name']);
            }
            // -- trim upto filedocx for path nd save
            //Check for similar file
            $attachmentFilter['url'] = $data['url'];
            $attachmentRecord = $this->getDataByParams('ox_file_attachment', array("url","name"), $attachmentFilter, null)->toArray();
            if (count($attachmentRecord) > 0) {
                throw new ServiceException("Another file with a similar name exists for this record.\n Please attach a file with a different name", "attachment.filename.invalid");
            }
            $this->logger->info("Attachmnet data---".print_r($data,true));
            $form->exchangeArray($data);
            $form->validate();
            $count = $this->attachmentTable->save($form);
            $id = $this->attachmentTable->getLastInsertValue();
            $data['id'] = $id;
            $file['name'] = $data['name'];
            $this->logger->info("Attachmnet files---".print_r($file,true));
            $fileStored = FileUtils::storeFile($file, $folderPath);
            $data['size'] = filesize($data['path']);
            $this->logger->info("Attachmnet params---".print_r($params,true));
            $this->logger->info("Attachmnet paramsff---".print_r($data,true));
            if (isset($params['fileId'])) {
                $filterArray['text'] = $params['fieldLabel'];
                $filter['uuid'] = $params['fileId'];
                $fileRecord = $this->getDataByParams('ox_file', array("entity_id","data"), $filter, null)->toArray();
                $fileArray['entity_id'] = $fileRecord[0]['entity_id'];
                $filterArray['entity_id'] = $fileRecord[0]['entity_id'];
                $fieldName = $this->getDataByParams('ox_field', array("name"), $filterArray, null)->toArray();
                if (count($fileRecord) > 0) {
                    $fileData = json_decode($fileRecord[0]['data'], true);
                    $check = $this->processFileDataList($fileData, $fieldName[0]['name'], $data);
                    if (!$check) {
                        //When the field doesn't exist in file data
                        $fileData[$fieldName[0]['name']] = array($data);
                    }
                    $this->updateFile($fileData, $params['fileId']);
                }
            }
            return $data;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function deleteAttachment($params)
    {
        try {
            $attachmentFilter['uuid'] = $params['attachmentId'];
            $attachmentRecord = $this->getDataByParams('ox_file_attachment', array("name",'id'), $attachmentFilter, null)->toArray();
            if (!empty($attachmentRecord) && !is_null($attachmentRecord)) {
                $this->beginTransaction();
                $attachmentName = $attachmentRecord[0]['name'];
                $delete = $this->getSqlObject()
                    ->delete('ox_file_attachment')
                    ->where(['id' => $attachmentRecord[0]['id']]);
                $result = $this->executeQuery($delete);
                $fileFilter['uuid'] = $params['fileId'];
                $fileRecord = $this->getDataByParams('ox_file', array("entity_id","data"), $fileFilter, null)->toArray();
                if (!empty($fileRecord) && !is_null($fileRecord)) {
                    $folderPath = $this->config['APP_DOCUMENT_FOLDER'].AuthContext::get(AuthConstants::ACCOUNT_UUID) . '/' . $params['fileId'].'/';
                    if (is_dir($folderPath.$attachmentName)) {
                        FileUtils::rmDir($folderPath.$attachmentName);
                    } elseif (file_exists($folderPath.$attachmentName)) {
                        FileUtils::deleteFile($attachmentName, $folderPath);
                    }
                    $fileData = json_decode($fileRecord[0]['data'], true);
                    $this->deleteAttachmentRecordWithUuid($fileData, $attachmentFilter['uuid']);
                    $this->updateFile($fileData, $fileFilter['uuid']);
                    $this->commit();
                } else {
                    throw new ServiceException("Incorrect file uuid specified", "file.uuid.incorrect");
                }
            } else {
                throw new ServiceException("Incorrect attachment uuid specified", "attachment.uuid.incorrect");
            }
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    private function isJson($string) {
        $array = json_decode($string, true);
        if(is_array($array) && json_last_error() == JSON_ERROR_NONE){
            return true;
        }
        return false;
    }

    private function deleteAttachmentRecordWithUuid(&$data, $uuid)
    {
        if (isset($data['uuid']) && $data['uuid'] == $uuid) {
            return true;
        }
        foreach ($data as $key => &$value) {
            if(gettype($value) == 'string' && $this->isJson($value)) {
                $data[$key] = json_decode($value,true);
            }
            if (is_array($value) && $this->deleteAttachmentRecordWithUuid($value, $uuid)) {
                unset($data[$key]);
                $data = array_filter($data);
            }
        }
    }

    public function renameAttachment($data)
    {
        try {
            if (isset($data['name'])) {
                $newName = $data['name'];
                if (!preg_match('/^[\w .-]+$/i', pathinfo($newName)['filename'])) {
                    throw new ServiceException("Unsupported Filename.\nFilename cannot contain special characters except -_ and space", "attachment.filename.invalid");
                }
            } else {
                throw new ServiceException("name is required and not specified", "attachment.newName.unspecified");
            }
            $attachmentFilter['uuid'] = $data['attachmentId'];
            $attachmentRecord = $this->getDataByParams('ox_file_attachment', array("url", "path", "originalName", "name", 'id'), $attachmentFilter)->toArray();
            if (!empty($attachmentRecord) && !is_null($attachmentRecord)) {
                $this->beginTransaction();
                $attachmentName = $attachmentRecord[0]['name'];
                $url = isset($attachmentRecord[0]['url']) ? str_replace($attachmentName, $newName, $attachmentRecord[0]['url']) : null;
                $path = isset($attachmentRecord[0]['path']) ? str_replace($attachmentName, $newName, $attachmentRecord[0]['path']) : null;
                $update = $this->getSqlObject()
                    ->update('ox_file_attachment')
                    ->set(['name' => $newName,'originalName' => $newName, 'url' => $url, 'path' => $path])
                    ->where(['id' => $attachmentRecord[0]['id']]);
                $result = $this->executeQuery($update);
                $folderPath = $this->config['APP_DOCUMENT_FOLDER'].AuthContext::get(AuthConstants::ACCOUNT_UUID) . '/' . $data['fileId'].'/';
                if (is_file($folderPath.$attachmentName) && file_exists($folderPath.$attachmentName)) {
                    rename($folderPath.$attachmentName, $folderPath.$newName);
                } elseif (is_dir($folderPath.$attachmentName)) {
                    FileUtils::renameFile($folderPath.$attachmentName, $folderPath.$newName);
                }
                $fileFilter['uuid'] = $data['fileId'];
                $fileRecord = $this->getDataByParams('ox_file', array("entity_id","data"), $fileFilter, null)->toArray();
                if (!empty($fileRecord) && !is_null($fileRecord)) {
                    $fileData = json_decode($fileRecord[0]['data'], true);
                    $this->renameAttachmentRecordWithUuid($fileData, $attachmentFilter['uuid'], $newName, $attachmentName);
                    $this->updateFile($fileData, $fileFilter['uuid']);
                    $this->commit();
                } else {
                    throw new ServiceException("Incorrect file uuid specified", "file.uuid.incorrect");
                }
            } else {
                throw new ServiceException("Incorrect attachment uuid specified", "attachment.uuid.incorrect");
            }
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    private function renameAttachmentRecordWithUuid(&$data, $uuid, $newName, $oldName)
    {
        if (isset($data['uuid']) && $data['uuid'] == $uuid) {
            return true;
        }
        foreach ($data as $key => &$value) {
            if (is_array($value) && $this->renameAttachmentRecordWithUuid($value, $uuid, $newName, $oldName)) {
                $data[$key]['name'] = $newName;
                $data[$key]['originalName'] = $newName;
                $data[$key]['file'] = isset($data[$key]['file']) ? str_replace($oldName, $newName, $data[$key]['file']) : null;
                $data[$key]['url'] = isset($data[$key]['url']) ? str_replace($oldName, $newName, $data[$key]['url']) : null;
                $data[$key]['path'] = isset($data[$key]['path']) ? str_replace($oldName, $newName, $data[$key]['path']) : null;
            }
        }
    }

    public function appendAttachmentToFile($fileAttachment, $field, $fileId)
    {
        if (!isset($fileAttachment['file']) && isset($fileAttachment['name'])) {
            $accountId = isset($accountId) ? $accountId : AuthContext::get(AuthConstants::ACCOUNT_UUID);
            $fileUuid = $this->getUuidFromId('ox_file', $fileId);
            $fileLocation = $fileAttachment['path'];
            $targetLocation = $this->config['APP_DOCUMENT_FOLDER']. $accountId . '/' . $fileUuid . '/';
            $this->logger->info("Data CreateFile- " . json_encode($fileLocation));
            $tempname = str_replace(".".$fileAttachment['extension'], "", $fileAttachment['name']);
            $fileAttachment['name'] = $tempname."-".$fileAttachment['uuid'].".".$fileAttachment['extension'];
            $fileAttachment['originalName'] = $tempname.".".$fileAttachment['extension'];
            $this->logger->info("attachment- " . json_encode($fileAttachment));
            if (file_exists($fileLocation)) {
                FileUtils::copy($fileLocation, $fileAttachment['name'], $targetLocation);
                // FileUtils::deleteFile($fileAttachment['originalName'],dirname($fileLocation)."/");
                $fileAttachment['file'] = $accountId . '/' . $fileUuid . '/'.$fileAttachment['name'];
                $fileAttachment['url'] = $this->config['baseUrl']."/". $accountId . '/' . $fileUuid . '/'.$fileAttachment['name'];
                $fileAttachment['path'] = FileUtils::truepath($targetLocation.$fileAttachment['name']);
                $this->logger->info("File Moved- " . json_encode($fileAttachment));
                // $count = $this->attachmentTable->delete($fileAttachment['id'], []);
            }
            $this->logger->info("File Deleted- " . json_encode($fileAttachment));
        }
        return $fileAttachment;
    }

    public function buildSortQuery($sortOptions, &$field)
    {
        $sortCount = 0;
        $sortTable = "tblf" . $sortCount;
        $sort = " ORDER BY ";
        foreach ($sortOptions as $key => $value) {
            $dir = isset($value['dir']) ? $value['dir'] : "";
            if ($value['field'] == 'entity_name') {
                if ($sortCount > 0) {
                    $sort .= ", ";
                }
                $sort .= " ox_app_entity.name ".$dir;
                $sortCount++;
                continue;
            }
            if ($value['field'] == 'date_created') {
                if ($sortCount > 0) {
                    $sort .= ", ";
                }
                $sort .= " date_created ".$dir;
                $sortCount++;
                continue;
            }
            if ($sortCount == 0) {
                $sort .= $value['field'] . " " . $dir;
            } else {
                $sort .= "," . $value['field'] . " " . $dir;
            }
            $field .= " , (select CASE WHEN " . $sortTable . ".field_value_type = 'TEXT' THEN ". $sortTable .".field_value_text WHEN ". $sortTable . ".field_value_type = 'DATE' THEN ". $sortTable .".field_value_date WHEN " . $sortTable . ".field_value_type = 'NUMERIC' THEN ". $sortTable .".field_value_numeric WHEN ". $sortTable . ".field_value_type = 'BOOLEAN' THEN ". $sortTable .".field_value_boolean END as field_value from ox_indexed_file_attribute as " . $sortTable . " inner join ox_field as " . $value['field'] . $sortTable . " on( " . $value['field'] . $sortTable . ".id = " . $sortTable . ".field_id)  WHERE " . $value['field'] . $sortTable . ".name='" . $value['field'] . "' AND " . $sortTable . ".file_id=of.id) as " . $value['field'];
            $sortCount += 1;
        }
        return $sort;
    }

    public function getFileFilters(&$params, &$where, &$queryParams)
    {
        if (isset($params['entityName'])) {
            if (is_array($params['entityName'])) {
                $where .= " (";
                foreach (array_values($params['entityName']) as $key => $entityName) {
                    $where .= "en.name = :entityName".$key." OR ";
                    $queryParams['entityName'.$key] = $entityName;
                }
                $where = rtrim($where, " OR ");
                $where .= ") AND ";
            } else {
                $where .= " en.name = :entityName AND ";
                $queryParams['entityName'] = $params['entityName'];
            }
        }
        if (isset($params['assocId'])) {
            $queryParams['assocId'] = $this->getIdFromUuid('ox_file', $params['assocId']);
            $where .= " of.assoc_id = :assocId AND ";
        }
        if (isset($params['gtCreatedDate'])) {
            $where .= " of.date_created >= :gtCreatedDate AND ";
            $params['gtCreatedDate'] = str_replace('-', '/', $params['gtCreatedDate']);
            $queryParams['gtCreatedDate'] = date('Y-m-d', strtotime($params['gtCreatedDate']));
        }
        if (isset($params['ltCreatedDate'])) {
            $where .= " of.date_created < :ltCreatedDate AND ";
            $params['ltCreatedDate'] = str_replace('-', '/', $params['ltCreatedDate']);
            /* modified date: 2020-02-11, today's date: 2020-02-11, if we use the '<=' operator then
             the modified date converts to 2020-02-11 00:00:00 hours. Inorder to get all the records
             till EOD of 2020-02-11, we need to use 2020-02-12 hence [+1] added to the date. */
            $queryParams['ltCreatedDate'] = date('Y-m-d', strtotime($params['ltCreatedDate'] . "+1 days"));
        }
        if (isset($params['createdBy'])) {
            $queryParams['createdBy'] = $this->getIdFromUuid('ox_user', $params['createdBy']);
            $where .= " of.created_by =:createdBy AND ";
        }
    }

    public function getEntityFilter(&$params, &$entityFilter, &$queryParams)
    {
        if (isset($params['entityName'])) {
            if (is_array($params['entityName'])) {
                $entityFilter = " (";
                foreach ($params['entityName'] as $value) {
                    $entityFilter .= " en.name = '".$value."' OR ";
                }
                $entityFilter = rtrim($entityFilter, " OR ");
                $entityFilter .= ")  AND ";
            } else {
                $entityFilter = " en.name = :entityName AND ";
                $queryParams['entityName'] = $params['entityName'];
            }
            if (isset($params['assocId'])) {
                if ($queryParams['assocId'] = $this->getIdFromUuid('ox_file', $params['assocId'])) {
                    $entityFilter .= " of.assoc_id = :assocId AND ";
                }
            }
        }
    }

    public function updateFieldValueOnFiles($appUUid, $data, $fieldName, $initialFieldValue, $newFieldValue, $filterParams)
    {
        $whereQuery = "";
        $sort = "";
        $field = "";
        $pageSize = " ";
        $offset = " ";
        $entityFilter = " ";
        $queryParams = array();
        $appId = $this->getIdFromUuid('ox_app', $appUUid);
        $fromQuery = "
            inner join ox_app_entity as en on en.id = `of`.entity_id
            inner join ox_app as oa on (oa.id = en.app_id AND oa.id = :appId) ";
        $this->getEntityFilter($data, $entityFilter, $queryParams);
        $this->processFilterParams($fromQuery, $whereQuery, $sort, $pageSize, $offset, $field, $filterParams);
        $this->beginTransaction();
        try {
            $updateFile = 'UPDATE ox_file as of '.$fromQuery.'SET data = REPLACE(data,'."'".'"'.$fieldName.'":"'.$initialFieldValue.'"'."','".'"'.$fieldName.'":"'.$newFieldValue.'"'."'".') WHERE '.$entityFilter.' '.$whereQuery;
            $queryParams['appId'] = $appId;
            $this->logger->info("Update File Attribute Query -- $updateFile with params - ".print_r($queryParams, true));
            $resultSet = $this->executeUpdateWithBindParameters($updateFile, $queryParams);

            unset($queryParams['entityName']);

            $fromClause = "";
            $whereClause = " WHERE oxf.app_id = :appId AND oxf.name = :fieldName ";
            if (isset($data['entityName'])) {
                $fromClause .= " inner join ox_app_entity as oxe on oxe.id = oxf.entity_id ";
                $whereClause .= " AND oxe.name = :entityName ";
                $queryParams['entityName'] = $data['entityName'];
            }
            $queryParams['fieldName'] = $fieldName;
            $selectField = "SELECT oxf.* from ox_field as oxf $fromClause $whereClause";
            $this->logger->info("GET FIELD DATA  -- $selectField with params - ".print_r($queryParams, true));
            $resultSet = $this->executeQueryWithBindParameters($selectField, $queryParams)->toArray();

            foreach ($resultSet as $value) {
                $this->updateFileAttribute($appId, $fieldName, $newFieldValue, $value['data_type'], $fromQuery, $whereQuery, $value['entity_id'], 'ox_file_attribute');
                if ($value['index'] == 1) {
                    $this->updateFileAttribute($appId, $fieldName, $newFieldValue, $value['data_type'], $fromQuery, $whereQuery, $value['entity_id'], 'ox_indexed_file_attribute');
                }
            }
            $this->commit();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            $this->rollback();
            throw $e;
        }
        return 1;
    }

    private function updateFileAttribute($appId, $fieldName, $fieldValue, $dataType, $fromQuery, $whereQuery, $entityId, $tableName)
    {
        $queryParams =
                array("appId" => $appId,
                      "fieldName" => $fieldName,
                      "fieldValue" => $fieldValue,
                      "entityId" => $entityId
                );

        $fileAttributeFromQuery = "
                inner join ox_file as of on of.id = ofa.file_id
                inner join ox_field as oxf on oxf.id = ofa.field_id ".$fromQuery;
        $whereQuery .= ' AND oxf.name = :fieldName AND oxf.entity_id = :entityId';

        $setQuery = "";
        if ($tableName == 'ox_file_attribute') {
            $setQuery = " SET ofa.field_value = :fieldValue ";
        }
        switch ($dataType) {
            case "date":
                $setQuery .= (strlen($setQuery) > 0) ? ", ofa.field_value_date = :fieldValue" : "SET ofa.field_value_date = :fieldValue";
                break;
            case "numeric":
                $setQuery .= (strlen($setQuery) > 0) ? ", ofa.field_value_numeric = :fieldValue" : "SET ofa.field_value_numeric = :fieldValue";
                break;
            case "boolean":
                $setQuery .= (strlen($setQuery) > 0) ? ", ofa.field_value_boolean = :fieldValue" : "SET ofa.field_value_boolean = :fieldValue";
                break;
            default:
                $setQuery .= (strlen($setQuery) > 0) ? ", ofa.field_value_text = :fieldValue" : "SET ofa.field_value_text = :fieldValue";
                break;
        }
        $updateField = "UPDATE $tableName as ofa $fileAttributeFromQuery $setQuery WHERE $whereQuery";
        $this->logger->info("Update File Attribute Field Value Query -- $updateField with params - ".print_r($queryParams, true));
        $resultSet = $this->executeUpdateWithBindParameters($updateField, $queryParams);
    }

    public function processFilterParams(&$fromQuery, &$whereQuery, &$sort, &$pageSize, &$offset, &$field, $filterParams)
    {
        $prefix = 1;
        if (!empty($filterParams)) {
            if (isset($filterParams['filter']) && !is_array($filterParams['filter'])) {
                $filterParamsArray = json_decode($filterParams['filter'], true);
            } else {
                if (isset($filterParams['filter'])) {
                    $filterParamsArray = $filterParams['filter'];
                } else {
                    $filterParamsArray = $filterParams;
                }
            }
            if ($whereQuery != "") {
                $whereQuery .= " AND ";
            }
            $filterlogic = isset($filterParamsArray[0]['filter']['logic']) ? $filterParamsArray[0]['filter']['logic'] : " AND ";
            $cnt = 1;
            $fieldParams = array();
            $tableFilters = "";
            if (isset($filterParamsArray[0]['filter'])) {
                $filterData = $filterParamsArray[0]['filter']['filters'];
                $this->processFiltersParam($filterlogic, $filterData, $whereQuery, $fromQuery, $prefix);
            }
            if (isset($filterParamsArray[0]['sort']) && !empty($filterParamsArray[0]['sort'])) {
                $sort = $this->buildSortQuery($filterParamsArray[0]['sort'], $field);
            }
            $pageSize = " LIMIT " . (isset($filterParamsArray[0]['take']) ? $filterParamsArray[0]['take'] : 10);
            $offset = " OFFSET " . (isset($filterParamsArray[0]['skip']) ? $filterParamsArray[0]['skip'] : 0);
            $whereQuery = rtrim($whereQuery, " AND ");
        }
    }

    private function processFileDataList(&$fileData, $searchKey, $data)
    {
        $return = false;
        if (isset($fileData[$searchKey])) {
            if (!is_array($fileData[$searchKey])) {
                $fileData[$searchKey] = json_decode($fileData[$searchKey], true);
            }
            array_push($fileData[$searchKey], $data);
            $return = true;
        } else {
            foreach ($fileData as $key => $value) {
                if (is_string($value)) {
                    $value = json_decode($value, true);
                }
                if (is_array($value)) {
                    $return = $this->processFileDataList($value, $searchKey, $data);
                }
                if ($return) {
                    $fileData[$key] = $value;
                    break;
                }
            }
        }
        return $return;
    }
    public function reIndexFile($params)
    {
        $whereQuery = "";
        $queryParams = array();
        if (isset($params['entity_id'])) {
            $entityId = isset($params['entity_id']) ? $params['entity_id'] : null;
        }
        if (!isset($entityId) && isset($params['entity_name'])) {
            $entitySelect = "select id from ox_app_entity where name = :entityName";
            $entityParams = array('entityName' => $params['entity_name']);
            $result = $this->executeQuerywithBindParameters($entitySelect, $entityParams)->toArray();
            if (count($result) > 0) {
                $entityId = $result[0]['id'];
            }
        }
        if (isset($entityId)) {
            $whereQuery = "where f.entity_id=:entityId";
            $queryParams['entityId'] = $entityId;
        }
        // print_r($whereQuery);
        $select = "SELECT f.*  from ox_file f $whereQuery";
        $files = $this->executeQuerywithBindParameters($select, $queryParams)->toArray();
        foreach ($files as $k => $file) {
            $this->updateFileUserContext($file);
            $fileData = json_decode($file['data'], true);
            $this->updateFileAttributesInternal($entityId, $fileData, $file['id']);
            unset($files[$k]['data']);
            unset($files[$k]['id']);
        }
        return $files;
    }

    public function getWorkflowInstanceStartDataFromFileId($fileId)
    {
        $select = "SELECT start_data from ox_workflow_instance oxwi inner join ox_file on ox_file.last_workflow_instance_id = oxwi.id where ox_file.uuid=:fileId";
        $params = array("fileId" => $fileId);
        $result = $this->executeQuerywithBindParameters($select, $params)->toArray();
        if (count($result) == 0) {
            return 0;
        }
        return $result[0];
    }

    public function getAuditLog($fileId, $filterParams = null)
    {
        try {
            $where = " WHERE ofal.uuid = '$fileId' ";
            if (count($filterParams) > 0 || sizeof($filterParams) > 0) {
                $filterArray = json_decode($filterParams['filter'], true);
                if (isset($filterArray[0]['filter'])) {
                    $filterlogic = isset($filterArray[0]['filter']['filters'][0]['logic']) ? $filterArray[0]['filter']['filters'][0]['logic'] : "AND";
                    $filterList = $filterArray[0]['filter']['filters'][0]['filters'];
                    $filter = FilterUtils::filterArray($filterList, $filterlogic, array('version'=>'ofal.version','modifiedUser'=>'mu.name','modified_by'=>'ofal.modified_by','action'=>'ofal.action', 'file_date_modified'=>'DATE(ofal.date_created)'));
                    $where .= " AND " . $filter;
                }
            }
            $select = " SELECT DISTINCT ofal.version,ofal.action,ofal.status,ofal.is_active,case when ofal.date_modified is NOT NULL then ofal.date_modified else ofal.date_created end as file_date_modified ,ofal.created_by,ofal.modified_by,ofal.date_modified,cu.name as createdUser,mu.name as modifiedUser ,ofal.id as fileId FROM ox_file_audit_log ofal inner join ox_user as cu on ofal.created_by = cu.id left join ox_user as mu on ofal.modified_by = mu.id $where ";
            $resultSet = $this->executeQuerywithParams($select);
            if (count($resultSet) == 0) {
                return 0;
            }
            $fileData = array();
            foreach ($resultSet as $value) {
                $fileDataArray = [];
                if ($value['version'] ==1 && $value['action']=='update') {
                    continue;
                }
                $fileDataArray = $value;
                $fileDataArray['fields'] = $this->getFileVersionChangeLog($fileId, $value['version']);
                $fileData[] = $fileDataArray;
            }
            return array('data'=>$fileData,'total'=>count($fileData));
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }



    public function getFileVersionChangeLog($fileId, $version)
    {
        try {
            $previousFileVersion = $version - 1;
            // $selectQuery = " SELECT * FROM ox_file_audit_log ofal WHERE ofal.uuid = :uuid and ofal.version =:version";
            // $params = array('uuid' => $fileId,'version'=>$version);
            // $this->logger->info("getFileVersionChangeLog----$select".print_r($params,true));
            // $resultSet = $this->executeQuerywithBindParameters($selectQuery,$params)->toArray();
            // print_r($resultSet);exit;
            $select = " SELECT ofal.entity_id,ofal.data,ofal.created_by,ofal.modified_by,ofal.date_modified,ofal.date_created FROM ox_file_audit_log ofal WHERE ofal.uuid = :uuid and ofal.version =:version";
            $params = array('uuid' => $fileId,'version'=>$version);
            $this->logger->info("getFileVersionChangeLog----$select".print_r($params, true));
            $resultSet = $this->executeQuerywithBindParameters($select, $params)->toArray();
            // echo "-----\n";print_r($resultSet);
            $selectQuery = " SELECT ofal.entity_id,ofal.data,ofal.created_by,ofal.modified_by,ofal.date_modified,ofal.date_created FROM ox_file_audit_log ofal WHERE (ofal.id = :fileId or ofal.uuid = :uuid) and ofal.version =:version";
            $paramsQuery = array('fileId' => $fileId,'uuid' => $fileId,'version'=>$previousFileVersion);
            $resultQuery = $this->executeQuerywithBindParameters($selectQuery, $paramsQuery)->toArray();
            if (count($resultSet) > 0) {
                $entityId = $resultSet[0]['entity_id'];
                $completionData = json_decode($resultSet[0]['data'], true);
            }
            if (count($resultQuery) > 0) {
                $startData = json_decode($resultQuery[0]['data'], true);
            } else {
                $startData = json_decode($resultSet[0]['data'], true);
            }
            // print_r("expression---\n");  print_r($completionData);
            $resultData = $this->getChangeLog($entityId, $startData, $completionData,$fileId=$fileId);
            return $resultData;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }

    private function processFileFilters($val, $filterOperator, $filterLogic, &$whereQuery)
    {
        if ($val['field'] == 'entity_name') {
            $whereQuery .= " en.name " . $filterOperator["operation"] . "'" . $filterOperator["operator1"] . "" . $val['value'] . "" . $filterOperator["operator2"] . "' $filterLogic";
            return true;
        }
        if ($val['field'] == 'status') {
            $whereQuery .= " of.status " . $filterOperator["operation"] . "'" . $filterOperator["operator1"] . "" . $val['value'] . "" . $filterOperator["operator2"] . "' $filterLogic";
            return true;
        }
        if ($val['field'] == 'start_date') {
            // Use BETWEEN for Time Stamp Comparision if the operator is =(equalTo)
            $whereQuery .= " of.start_date " . $filterOperator["operation"] . "'" . $val['value'] . "' $filterLogic";
            return true;
        }
        if ($val['field'] == 'end_date') {
            $whereQuery .= " of.end_date " . $filterOperator["operation"] . "'" . $val['value'] . "' $filterLogic";
            return true;
        }
        if ($val['field'] == 'rygStatus') {
            $whereQuery .= " of.rygStatus " . $filterOperator["operation"] . "'" . $filterOperator["operator1"] . "" . $val['value'] . "" . $filterOperator["operator2"] . "' $filterLogic";
            return true;
        }
        if ($val['field'] == 'created_by') {
            $whereQuery .= " ou.name " . $filterOperator["operation"] . "'" . $filterOperator["operator1"] . "" . $val['value'] . "" . $filterOperator["operator2"] . "' $filterLogic";
            return true;
        }
        return false;
    }

    private function generateQueryString($filterOperator, $subFilter, &$subQuery, $subFilterLogic)
    {
        $queryString = $filterOperator["operation"] . "'" . $filterOperator["operator1"] . "" . $subFilter['value'] . "" . $filterOperator["operator2"] . "'";
        $fieldNamesArray[] = '"'.$subFilter['field'].'"';
        $subQuery .= " (CASE WHEN (fileAttributes.field_value_type='DATE') THEN fileAttributes.field_value_date $queryString";
        if (date('Y-m-d', strtotime($subFilter['value'])) === $subFilter['value']) {
            $subQuery .= "  WHEN (fileAttributes.field_value_type='DATE') THEN fileAttributes.field_value_date $queryString ";
        }
        if (is_numeric($subFilter['value'])) {
            $subQuery .= " WHEN (fileAttributes.field_value_type='NUMERIC') THEN fileAttributes.field_value_numeric $queryString ";
        }
        if (is_bool($subFilter['value'])) {
            $subQuery .= " WHEN (fileAttributes.field_value_type='BOOLEAN') THEN fileAttributes.field_value_boolean $queryString  ";
        }

        $subQuery .= " END ) $subFilterLogic ";
    }
    public function getAssignments($appId, $filterParams)
    {
        $userId = AuthContext::get(AuthConstants::USER_ID);
        $field = "";
        $where = "";
        $whereQuery = "";
        $filterFromQuery = "";
        $sort = "ORDER BY date_created desc";
        $pageSize = " LIMIT 10";
        $offset = " OFFSET 0";
        $appFilter = "";
        if (isset($appId)) {
            $appFilter = "AND ox_app.uuid ='" . $appId . "'";
        }
        $whereQuery = " WHERE ((ox_user_team.avatar_id = $userId  OR au.user_id = $userId)
                                OR ox_file_assignee.user_id = $userId)
                                $appFilter";
        $this->processFilterParams($filterFromQuery, $whereQuery, $sort, $pageSize, $offset, $field, $filterParams);
        $fromQuery = "FROM ox_workflow
            INNER JOIN ox_app on ox_app.id = ox_workflow.app_id
            INNER JOIN ox_workflow_deployment on ox_workflow_deployment.workflow_id = ox_workflow.id
            INNER JOIN ox_workflow_instance on ox_workflow_instance.workflow_deployment_id = ox_workflow_deployment.id AND ox_workflow_instance.account_id =" . AuthContext::get(AuthConstants::ACCOUNT_ID) . "
            INNER JOIN ox_file as of on of.id = ox_workflow_instance.file_id
            INNER JOIN ox_app_entity as en on en.id = of.entity_id
            INNER JOIN ox_activity on ox_activity.workflow_deployment_id = ox_workflow_deployment.id
            INNER JOIN ox_activity_instance ON ox_activity_instance.workflow_instance_id = ox_workflow_instance.id and ox_activity.id = ox_activity_instance.activity_id AND ox_activity_instance.status = 'In Progress'
            LEFT JOIN (SELECT oxi.id,oxi.activity_instance_id,oxi.file_id,oxi.user_id,ox2.assignee,CASE WHEN ox2.assignee = 1 THEN ox2.role_id ELSE oxi.role_id END as role_id,CASE WHEN ox2.assignee = 1 THEN ox2.team_id ELSE oxi.team_id END as team_id FROM  ox_file_assignee as oxi INNER JOIN (SELECT activity_instance_id,max(assignee) as assignee,max(role_id) as role_id,max(team_id) as team_id From ox_file_assignee WHERE activity_instance_id is not null GROUP BY activity_instance_id) as ox2 on oxi.activity_instance_id = ox2.activity_instance_id AND oxi.assignee = ox2.assignee) as ox_file_assignee ON ox_file_assignee.activity_instance_id = ox_activity_instance.id
            LEFT JOIN ox_user_team ON ox_file_assignee.team_id = ox_user_team.team_id
            LEFT JOIN ox_file as oxf ON oxf.id = ox_file_assignee.file_id
            LEFT JOIN ox_user_role ON ox_file_assignee.role_id = ox_user_role.role_id 
            LEFT JOIN ox_account_user au on au.id = ox_user_role.account_user_id
            inner join ox_user as ou on of.created_by = ou.id
            LEFT JOIN ox_user ON ox_file_assignee.user_id = ox_user.id";

    $fileQuery = "FROM ox_file as `of` 
        INNER JOIN ox_app_entity as en on en.id = `of`.entity_id
        INNER JOIN ox_app on ox_app.id = en.app_id
        LEFT JOIN (SELECT oxi.id,oxi.file_id,oxi.user_id,oxi.assignee,CASE WHEN ox3.assignee = 1 THEN ox3.role_id ELSE oxi.role_id END as role_id,CASE WHEN ox3.assignee = 1 THEN ox3.team_id ELSE oxi.team_id END as team_id FROM ox_file_assignee as oxi INNER JOIN (SELECT file_id,max(assignee) as assignee,max(role_id) as role_id,max(team_id) as team_id From ox_file_assignee WHERE file_id is not null GROUP BY file_id) as ox3 on (oxi.file_id = ox3.file_id AND oxi.assignee = ox3.assignee)) as ox_file_assignee ON (ox_file_assignee.file_id = `of`.id)
        LEFT JOIN ox_user_team ON ox_file_assignee.team_id = ox_user_team.team_id
        LEFT JOIN ox_user_role ON ox_file_assignee.role_id = ox_user_role.role_id 
        LEFT JOIN ox_account_user au on au.id = ox_user_role.account_user_id
        inner join ox_user as ou on `of`.created_by = `ou`.id
        LEFT JOIN ox_user ON ox_file_assignee.user_id = ox_user.id";
    if (!empty($filterParams)) {
        $cacheQuery = '';
    } else {
        $cacheQuery = " UNION
        SELECT ow.name as workflow_name,ofile.uuid,ofile.start_date,ofile.end_date,ofile.status as fileStatus,ouc.content as data,oai.activity_instance_id as activityInstanceId,owi.process_instance_id as workflowInstanceId,
        oai.start_date,oae.name as entity_name,NULL as id,
        oa.name as activityName,ouc.date_created,'in_draft' as to_be_claimed,ou.name as assigned_user
        FROM ox_user_cache as ouc
        LEFT JOIN ox_workflow_instance as owi ON ouc.workflow_instance_id = owi.id
        LEFT JOIN ox_workflow_deployment as owd on owi.workflow_deployment_id = owd.id
        LEFT JOIN ox_workflow as ow on owd.workflow_id = ow.id
        LEFT JOIN ox_file as ofile ON ofile.id = owi.file_id
        INNER JOIN ox_form as oxf on ouc.form_id = oxf.id
        INNER JOIN ox_app_entity as en on en.app_id = oxf.app_id and oxf.entity_id = en.id
        INNER JOIN ox_app on ox_app.id = en.app_id
        LEFT JOIN ox_activity_instance as oai on ouc.activity_instance_id = oai.activity_instance_id
        LEFT JOIN ox_activity as oa on oai.activity_id = oa.id
        LEFT JOIN ox_user as ou on ouc.user_id = ou.id
        WHERE ouc.user_id =$userId and ouc.deleted = 0 and ouc.activity_instance_id IS NULL and $appFilter";
    }

    if (strlen($whereQuery) > 0) {
        $whereQuery .= " " . $where . " AND ";
    } else {
        $whereQuery = "WHERE ";
    }
    $whereQuery .= 'of.is_active = 1  AND COALESCE(of.is_snoozed,0) !=1 ';
    $pageSize = "LIMIT " . (isset($filterParamsArray[0]['take']) ? $filterParamsArray[0]['take'] : 20);
    $offset = "OFFSET " . (isset($filterParamsArray[0]['skip']) ? $filterParamsArray[0]['skip'] : 0);
    $fieldList2 = "distinct ox_app.name as appName,`of`.id,NULL as workflow_name, `of`.uuid,`of`.data,`of`.start_date as startDate,`of`.end_date as endDate,`of`.status as fileStatus,ou.name as created_by,`of`.rygStatus,`of`.version,
    NULL as activityInstanceId,NULL as workflowInstanceId, `of`.date_created as created_date,en.name as entity_name,
    NULL as activityName, `of`.date_created,
    CASE WHEN ox_file_assignee.assignee = 0 then 1
    WHEN ox_file_assignee.assignee = 1 AND ox_file_assignee.user_id = $userId then 0 else 2
    end as to_be_claimed,ox_user.name as assigned_user $field";
    $countQuery = "SELECT count(id) as `count` 
                    from ((SELECT distinct ox_file_assignee.id $fromQuery $filterFromQuery $whereQuery) UNION all (SELECT distinct ox_file_assignee.id $fileQuery $filterFromQuery $whereQuery)) as t1";
    $countResultSet = $this->executeQuerywithParams($countQuery)->toArray();
    $fieldList = "distinct ox_app.name as appName,`of`.id as myId,ox_workflow.name as workflow_name, `of`.uuid,`of`.data,`of`.start_date as startDate,`of`.end_date as endDate,`of`.status as fileStatus,ou.name as created_by,`of`.rygStatus,`of`.version,
    ox_activity_instance.activity_instance_id as activityInstanceId,ox_workflow_instance.process_instance_id as workflowInstanceId, ox_activity_instance.start_date as created_date,en.name as entity_name,
    ox_activity.name as activityName, `of`.date_created,
    CASE WHEN ox_file_assignee.assignee = 0 then 1
    WHEN ox_file_assignee.assignee = 1 AND ox_file_assignee.user_id = $userId then 0 else 2
    end as to_be_claimed,ox_user.name as assigned_user $field";
    $querySet = "select * from ((SELECT $fieldList $fromQuery $filterFromQuery $whereQuery) UNION (SELECT $fieldList2 $fileQuery $filterFromQuery $whereQuery)) as assigneeList $sort $pageSize $offset";
    $this->logger->info("Executing Assignment listing query - $querySet");
    $resultSet = $this->executeQuerywithParams($querySet)->toArray();
    $result = array();
    foreach ($resultSet as $key => $value) {
        $data = json_decode($value['data'], true);
        unset($value['data']);
        if ($value['to_be_claimed']  == 'in_draft') {
            //TODO this is hardcoding for hub NEED to be REMOVED and changed to STATUS field
            $data['policyStatus'] = 'In Draft';
        }
        $result[] = array_merge($value, $data);
    }
    $this->logger->info("ASSIGNMENT RESULT -- " . print_r($result, true));
    return array('data' => $result, 'total' => $countResultSet[0]['count']);
}

    public function deleteFilesLinkedToApp($appId)
    {
        $select = "SELECT oxf.* from ox_file oxf inner join ox_app_entity oxae on oxae.id = oxf.entity_id inner join ox_app oxa on oxa.id = oxae.app_id where oxa.uuid=:appId";
        $params = array('appId' => $appId);
        $resultSet = $this->executeQueryWithBindParameters($select, $params)->toArray();
        if (count($resultSet) > 0) {
            foreach ($resultSet as $key => $value) {
                $this->deleteFile($value['uuid'], $value['version']);
            }
        }
    }
    
    public function getAppDetailsBasedOnFileId($fileId)
    {
        try {
            $querySet = "SELECT oxa.name as appName, oxa.app_properties, oxf.account_id
                        FROM ox_file oxf
                        INNER JOIN ox_app_entity oxe ON oxe.id = oxf.entity_id
                        INNER JOIN ox_app oxa on oxa.id = oxe.app_id
                        WHERE oxf.uuid = :fileId";
            $queryParams = array('fileId' => $fileId);
            $resultSet = $this->executeQueryWithBindParameters($querySet, $queryParams)->toArray();
            if (count($resultSet) > 0) {
                return $resultSet[0];
            }
            return 0;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }

    public function updateRyg($data)
    {
        try {
            $this->beginTransaction();
            $select = "SELECT oxe.ryg_rule 
                        From ox_app_entity oxe
                        inner join ox_file oxf on oxf.entity_id = oxe.id
                        where oxf.uuid =:fileId";
            $params = ['fileId' => $data['uuid']];
            $result = $this->executeQueryWithBindParameters($select, $params)->toArray();
            if (!empty($result[0]['ryg_rule'])) {
                $fileRyg = $this->evaluateRyg(json_decode($data['data'], true), json_decode($result[0]['ryg_rule'], true));
                $updateFile = "UPDATE ox_file SET rygStatus=:fileRyg where uuid=:fileId";
                $params['fileRyg'] = $fileRyg;
                $this->executeUpdateWithBindParameters($updateFile, $params);
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    private function evaluateRyg($data, $entityRule)
    {
        $ryg = [File::RED, File::YELLOW, File::GREEN];
        $status = File::GREEN;
        foreach ($ryg as $value) {
            if (isset($entityRule[$value])) {
                $rule = $entityRule[$value];
                $result = $this->verifyFieldRule($data, $rule);
                if ($result) {
                    $status = $value;
                    break;
                }
            }
        }
        $this->logger->info("FILE STATUS--".print_r($status, true));
        return $status;
    }

    private function verifyFieldRule($data, $rule)
    {
        $rule = $this->preProcessRygRule($rule);
        $logic = isset($rule['logic']) ? strtoupper($rule['logic']) : "AND";
        $filters = $rule['filters'];
        $result = $logic == "AND" ? true : false;
        foreach ($filters as $value) {
            if (isset($value['field'])) {
                $field = $value['field'];
                $operator = $value['operator'];
                $expected =  isset($value['value']) ? $value['value'] : null;
                $actual = isset($data[$field]) ? $data[$field]: null;
                if ($logic == "AND") {
                    $result = $this->processCondition($operator, $actual, $expected) && $result ;
                } else {
                    $result = $this->processCondition($operator, $actual, $expected) || $result;
                }
            } else {
                if (isset($value['filter']) && isset($value['filter']['filters'])) {
                    $result .= "(".$this->verifyFieldRule($data, $value['filter']).")";
                }
            }
        }
        return $result;
    }

    private function processCondition($operator, $actual, $expected)
    {
        $result = false;
        switch ($operator) {
            case 'startswith':
                $result = startsWith($actual, $expected);
                break;
            case 'endswith':
                $result = endsWith($actual, $expected);
                break;
            case 'eq':
                $result = (trim($actual) === trim($expected));
                break;
            case 'neq':
                $result = ($actual !== $expected);
                break;
            case 'doesnotcontain':
                $result = strpos($actual, $expected) === false ? true : false;
                break;
            case 'isnull':
                $result = is_null($actual);
                break;
            case 'isempty':
                $result = empty($actual);
                break;
            case 'isnotnull':
                $result = !is_null($actual);
                break;
            case 'isnotempty':
                $result = !empty($actual);
                break;
            case 'lte':
                $result = $actual <= $expected;
                break;
            case 'lt':
                $result = $actual < $expected;
                break;
            case 'gte':
                $result = $actual >= $expected;
                break;
            case 'gt':
                $result = $actual > $expected;
                break;
            case 'contains':
            default:
            $result = strpos($actual, $expected) === false ? false : true;
                break;
        }
        return $result;
    }

    public function bulkUpdateFileRygStatus($data)
    {
        try {
            // Get Entity Data
            $entityCondition = "";
            $appId = is_numeric($data['appId']) ? $this->getUuidFromId('ox_app', $data['appId']): $data['appId'];
            $queryParams = [];
            if (isset($data['entityName'])) {
                $entityCondition = " AND ox_app_entity.name=:entityName";
                $queryParams['entityName'] = $data['entityName'];
            }
            $query = "SELECT ox_app_entity.name as entityName,ox_app_entity.ryg_rule,ox_app_entity.id 
                    from ox_app_entity 
                    left join ox_app on ox_app.id=ox_app_entity.app_id 
                    where ox_app.uuid=:appUuid $entityCondition";
            $queryParams['appUuid'] = $appId;
            $this->logger->info("Query--- $query with Paramss--".print_r($queryParams, true));
            $resultSet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
            // print_r($resultSet);exit;
            // Get Files linked to the App and Entity
            if (count($resultSet) > 0) {
                $sort = "";
                $pageSize = "";
                $offset = "";
                foreach ($resultSet as $key => $value) {
                    $entityFilter = "";
                    $queryParams = ['appId' => $appId];
                    $this->getFileFilters($value, $entityFilter, $queryParams);
                    $entityRule = json_decode($value['ryg_rule'], true);
                    if (!$entityRule) {
                        continue;
                    }
                    foreach ($entityRule as $ryg => $rule) {
                        $this->beginTransaction();
                        $whereQuery = "";
                        $field = "";
                        $where = "$entityFilter of.rygStatus !=:rygStatus AND ";
                        $update = "UPDATE ox_file of 
                        inner join ox_app_entity as en on en.id = `of`.entity_id
                        inner join ox_app as oa on (oa.id = en.app_id AND oa.uuid = :appId)";
                        $setClause = "SET of.rygStatus=:rygStatus";
                        $rule = $this->preProcessRygRule($rule);
                        $this->processFilterParams($update, $whereQuery, $sort, $pageSize, $offset, $field, ['filter' => [['filter' =>$rule]]]);
                        $this->getFileFilterClause($whereQuery, $where);
                        $queryParams['rygStatus'] = $ryg;
                        $update = "$update $setClause $where";
                        $this->logger->info("FILE STATUS UPDATE QUERY-- $update with Params--".print_r($queryParams, true));
                        $this->executeUpdateWithBindParameters($update, $queryParams);
                        $this->commit();
                    }
                }
            }
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    private function getFileFilterClause($whereQuery, &$where)
    {
        $this->logger->info("WHERE CONDTN---".print_r($whereQuery, true));
        $this->logger->info("WHERE CONDTN 111---".print_r($where, true));
        $whereQuery = rtrim($whereQuery, " AND ");
        $whereQuery = ltrim($whereQuery, " and ");
        if ($whereQuery==" WHERE ") {
            $where = "";
        } else {
            $where .= " " . $whereQuery ;
        }
        $where = rtrim($where, " AND ");
        $where = trim($where) != "" ? "WHERE $where AND " : " WHERE ";
        $where .= "of.is_active =1";
    }

    private function updateFileTitle($data)
    {
        try {
            $this->beginTransaction();
            $select = "SELECT oxe.title,oxe.name as entity_name 
                        From ox_app_entity oxe
                        inner join ox_file oxf on oxf.entity_id = oxe.id
                        where oxf.uuid =:fileId";
            $params = ['fileId' => $data['uuid']];
            $result = $this->executeQueryWithBindParameters($select, $params)->toArray();
            if (!empty($result[0]['title'])) {
                $title = $result[0]['title'];
                $fileData = json_decode($data['data'], true);
                $updateFile = "UPDATE ox_file SET fileTitle=:fileTitle where uuid=:fileId";
                $params['fileTitle'] = $this->evaluateFileTitle($title, $fileData, $result[0]['entity_name']);
                $this->executeUpdateWithBindParameters($updateFile, $params);
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    private function evaluateFileTitle($title, $fileData, $entityName)
    {
        $fileData['entity_name'] = $entityName;
        preg_match_all('/[$][{](.*?)\}/', $title, $matches);
        if (is_array($matches[0]) && count($matches[0]) > 0) {
            for ($i=0; $i < count($matches[0]); $i++) {
                $title = str_replace($matches[0][$i], (isset($fileData[$matches[1][$i]]) ? $fileData[$matches[1][$i]] : null), $title);
            }
        }
        $this->logger->info("FILE TITLE---".print_r($title, true));
        return $title;
    }

    public function getFieldDataTypes($entityId, $appId)
    {
        $select = "SELECT ox_field.name,ox_field.data_type from ox_field inner join ox_app_entity on ox_field.entity_id = ox_app_entity.id where ox_field.entity_id=:entityId and ox_field.app_id=:appId";
        $params = array("entityId" => $entityId,"appId" => $appId);
        $result = $this->executeQuerywithBindParameters($select, $params)->toArray();
        if (count($result) == 0) {
            return 0;
        }
        return $result[0];
    }

    private function processFiltersParam($filterlogic, $filterData, &$whereQuery, &$fromQuery, &$prefix, $sameField = false)
    {
        $processFromQuery = true;
        foreach ($filterData as $val) {
            $tablePrefix = "tblf" . $prefix;
            if (!empty($val)) {
                if (isset($val['filter'])) {
                    if (isset($val['filter']['logic'])) {
                        $subFilterLogic = $val['filter']['logic'];
                    } else {
                        $subFilterLogic = " OR ";
                    }
                    if (isset($val['filter']['filters'])) {
                        $subQuery = "";
                        $subFromQuery = "";
                        // Add Index to Fileattribute - Include nested filter testcase
                        foreach ($val['filter']['filters'] as $subFilter) {
                            $filterOperator = $this->processFilters($subFilter);
                            $fileFiltersVal = $this->processFileFilters($subFilter, $filterOperator, $subFilterLogic, $whereQuery);
                            if ($fileFiltersVal) {
                                continue;
                            }
                            $subTablePrefix = $tablePrefix.$subFilter['field'];
                            $queryString = $filterOperator["operation"] . "'" . $filterOperator["operator1"] . "" . $subFilter['value'] . "" . $filterOperator["operator2"] . "'";
                            if ($subFilterLogic=='or') {
                                $fieldNamesArray[] = '"'.$subFilter['field'].'"';
                                $subQuery .= " (CASE WHEN (fileAttributes.field_value_type='TEXT') THEN fileAttributes.field_value_text $queryString ";
                                if (date('Y-m-d', strtotime($subFilter['value'])) === $subFilter['value']) {
                                    $subQuery .= "  WHEN (fileAttributes.field_value_type='DATE') THEN fileAttributes.field_value_date $queryString ";
                                }
                                if (is_numeric($subFilter['value'])) {
                                    $subQuery .= " WHEN (fileAttributes.field_value_type='NUMERIC') THEN fileAttributes.field_value_numeric $queryString ";
                                }
                                if (is_bool($subFilter['value'])) {
                                    $subQuery .= " WHEN (fileAttributes.field_value_type='BOOLEAN') THEN fileAttributes.field_value_boolean $queryString  ";
                                }
                                $subQuery .= " END ) $subFilterLogic ";

                                $subFromQuery = "inner join ox_indexed_file_attribute as fileAttributes on (`of`.id =fileAttributes.file_id) inner join ox_field as fieldsTable on(fieldsTable.entity_id = `of`.entity_id and fieldsTable.id=fileAttributes.field_id and fieldsTable.name in (".implode(',', $fieldNamesArray)."))";
                            } else {
                                $subFromQuery .= " inner join ox_indexed_file_attribute as ".$subTablePrefix." on (`of`.id =" . $subTablePrefix . ".file_id) inner join ox_field as ".$subFilter['field'].$subTablePrefix." on(".$subFilter['field'].$subTablePrefix.".id = ".$subTablePrefix.".field_id and ". $subFilter['field'].$subTablePrefix.".name='".$subFilter['field']."')";
                                $subQuery .= " (CASE WHEN (" .$subTablePrefix . ".field_value_type='TEXT') THEN " . $subTablePrefix . ".field_value_text $queryString ";

                                if (date('Y-m-d', strtotime($subFilter['value'])) === $subFilter['value']) {
                                    $subQuery .= "  WHEN (" .$subTablePrefix . ".field_value_type='DATE') THEN " . $subTablePrefix . ".field_value_date $queryString ";
                                }
                                if (is_numeric($subFilter['value'])) {
                                    $subQuery .= "  WHEN (" .$subTablePrefix . ".field_value_type='NUMERIC') THEN " . $subTablePrefix . ".field_value_numeric $queryString ";
                                }
                                if (is_bool($subFilter['value'])) {
                                    $subQuery .= " WHEN (" .$subTablePrefix . ".field_value_type='BOOLEAN') THEN " . $subTablePrefix . ".field_value_boolean $queryString  ";
                                }

                                $subQuery .= " END ) $subFilterLogic ";
                            }
                        }
                        $fromQuery .= $subFromQuery;
                        $subQuery = rtrim($subQuery, $subFilterLogic." ");
                        $whereQuery .= " ( ".$subQuery." ) $filterlogic ";
                    }
                } elseif (isset($val['filters'])) {
                    $logic = $val['logic'];
                    $data = $val['filters'];
                    $whr = "";
                    $this->processFiltersParam($logic, $data, $whr, $fromQuery, $prefix, true);
                    if (!StringUtils::endsWith(trim($whereQuery), $filterlogic)) {
                        $whereQuery .= " $filterlogic";
                    }
                    $whereQuery .= " ($whr)";
                } else {
                    $filterOperator = $this->processFilters($val);
                    $fileFiltersVal = $this->processFileFilters($val, $filterOperator, $filterlogic, $whereQuery);
                    if ($fileFiltersVal) {
                        continue;
                    }
                    $this->logger->info("ProcessFromQuery- $processFromQuery");
                    $this->logger->info("sameField- $sameField");
                    $this->logger->info("tablePrefix- $tablePrefix");
                    if ($processFromQuery) {
                        $fromQuery .= " inner join ox_indexed_file_attribute as ".$tablePrefix." on (`of`.id =" . $tablePrefix . ".file_id) inner join ox_field as ".$val['field'].$tablePrefix." on(".$val['field'].$tablePrefix.".id = ".$tablePrefix.".field_id and ". $val['field'].$tablePrefix.".name='".$val['field']."')";
                        if ($sameField) {
                            $processFromQuery = false;
                        }
                    }
                    $filterOperator = $this->processFilters($val);
                    $queryString = $filterOperator["operation"] . "'" . $filterOperator["operator1"] . "" . $val['value'] . "" . $filterOperator["operator2"] . "'";
                    $whereQuery .= " (CASE  WHEN (" .$tablePrefix . ".field_value_type='TEXT') THEN " . $tablePrefix . ".field_value_text $queryString ";

                    if (date('Y-m-d', strtotime($val['value'])) === $val['value']) {
                        $whereQuery .= " WHEN (" .$tablePrefix . ".field_value_type='DATE') THEN " . $tablePrefix . ".field_value_date $queryString ";
                    }
                    if (is_numeric($val['value'])) {
                        $whereQuery .= " WHEN (" .$tablePrefix . ".field_value_type='NUMERIC') THEN " . $tablePrefix . ".field_value_numeric $queryString ";
                    }
                    if (is_bool($val['value'])) {
                        $whereQuery .= "  WHEN (" .$tablePrefix . ".field_value_type='BOOLEAN') THEN " . $tablePrefix . ".field_value_boolean $queryString  ";
                    }
                    $whereQuery .= " END ) $filterlogic ";
                }
                if ($processFromQuery) {
                    $prefix += 1;
                }
            }
        }
        $whereQuery = rtrim($whereQuery, $filterlogic." ");
    }

    private function preProcessRygRule($rule)
    {
        $ruleArray = array_merge([], $rule);
        foreach ($ruleArray['filters'] as $key => $filterValue) {
            if (isset($filterValue['value']) && strtolower(substr($filterValue['value'], 0, 5))=="date:") {
                $filterValue['value'] = date("Y-m-d", strtotime(substr($filterValue['value'], 5)));
            } else {
                if (isset($filterValue['value'])) {
                    $filterValue['value'] = $filterValue['value'];
                }
            }
            $ruleArray['filters'][$key] = $filterValue;
        }
        return $ruleArray;
    }

    public function snoozeFile($params)
    {

        if(!isset($params['snooze'])){
            throw new ServiceException("Invalid parameters specified",'params.snooze');
        }

        $isSnoozed = $params['snooze'];
        $fileId = $params['fileId'];

        if($isSnoozed !="1"){
            $isSnoozed = "0";
        }

        $select = "SELECT oxf.uuid,oxf.account_id FROM ox_file oxf WHERE oxf.uuid =:fileId";       
        $params = ['fileId' => $fileId];
        $result = $this->executeQueryWithBindParameters($select, $params)->toArray();
        if(count($result)>0){

            $updateFile = "UPDATE ox_file SET is_snoozed=:is_snoozed where uuid=:fileId";
            $params['is_snoozed'] = (int) $isSnoozed;
            $this->executeUpdateWithBindParameters($updateFile, $params);
            return $isSnoozed;

        }
        else{
            throw new EntityNotFoundException("File Id not found -- " . $fileId);
        }


    }
}
