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
use Oxzion\Service\FieldService;
use Oxzion\Utils\UuidUtil;

class FileService extends AbstractService
{
    protected $fieldService;
    /**
     * @ignore __construct
     */
    public function __construct($config, $dbAdapter, FileTable $table, FormService $formService, MessageProducer $messageProducer, FieldService $fieldService)
    {
        parent::__construct($config, $dbAdapter);
        $this->messageProducer = $messageProducer;
        $this->table = $table;
        $this->config = $config;
        $this->dbAdapter = $dbAdapter;
        $this->fieldService = $fieldService;
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
    public function createFile(&$data, $workflowInstanceId = null, $ensureDir = false)
    {
        $baseFolder = $this->config['APP_DOCUMENT_FOLDER'];
        $this->logger->info("Data CreateFile- " . json_encode($data));
        $parentId = isset($data['parent_id']) ? $data['parent_id'] : null;
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
        if (isset($data['activity_id'])) {
            $activityId = $data['activity_id'];
        } else {
            $activityId = null;
        }
        $data['uuid'] = $uuid = isset($data['uuid']) ? $data['uuid'] : UuidUtil::uuid();

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
        $fields = $data = $this->cleanData($data);
        $this->logger->info("Data From Fileservice before encoding - " . print_r($data, true));
        $jsonData = json_encode($data);
        $data['uuid'] = $uuid;
        $data['workflow_instance_id'] = isset($workflowInstanceId) ? $workflowInstanceId : null;
        $data['org_id'] = AuthContext::get(AuthConstants::ORG_ID);
        $data['created_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_created'] = date('Y-m-d H:i:s');
        $data['form_id'] = $formId;
        $data['parent_id'] = $parentId;
        $data['date_modified'] = date('Y-m-d H:i:s');
        $data['entity_id'] = $entityId;

        $data['data'] = $jsonData;
        $file = new File();
        $file->exchangeArray($data);
        $this->logger->info("Data From Fileservice - " . print_r($data, true));
        $this->logger->info("File data From Fileservice - " . print_r($file->toArray(), true));
        // $fields = array_diff_assoc($data, $file->toArray());
        $file->validate();
        $this->beginTransaction();

        $count = 0;
        try {
            if ($parentId) {
                if (!$this->setFileLatest($parentId, 0)) {
                    throw new Exception("Could not update latest for parent file " . $data['parent_id']);
                }
            }
            $this->logger->info("FILE DATA BEFORE SAVE----" . print_r($file, true));
            $count = $this->table->save($file);

            $this->logger->info("COUNT  FILE DATA----" . $count);
            if ($count == 0) {
                throw new ServiceException("File Creation Failed", "file.create.failed");
            }
            $id = $this->table->getLastInsertValue();
            $this->logger->info("FILE ID DATA" . $id);
            $data['id'] = $id;
            $this->logger->info("FILE DATA ----- " . json_encode($data));
            $validFields = $this->checkFields($data['entity_id'], $fields, $id);
            $this->updateFileData($id, $fields);
            if (!$validFields || empty($validFields)) {
                $this->logger->info("FILE Validation ----- ");
                throw new ValidationException("Validation Errors" . json_encode($fields));
            }
            $this->logger->info("Check Fields - " . json_encode($validFields));
            $this->multiInsertOrUpdate('ox_file_attribute', $validFields, ['id']);
            $this->logger->info("Created successfully  - file record");
            $this->commit();
            // IF YOU DELETE THE BELOW TWO LINES MAKE SURE YOU ARE PREPARED TO CHECK THE ENTIRE INDEXER FLOW
            if (isset($data['id'])) {
                $this->messageProducer->sendTopic(json_encode(array('id' => $data['id'])), 'FILE_ADDED');
            }

        } catch (Exception $e) {
            $this->logger->info("erorororor  - file record");
            $this->rollback();
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
        return $count;
    }

    private function updateFileData($id, $data)
    {
        $query = "update ox_file set data = :data where id = :id";
        $params = array('data' => json_encode($data), 'id' => $id);
        $result = $this->executeUpdateWithBindParameters($query, $params);
        return $result->getAffectedRows() > 0;
    }

    private function setFileLatest($fileId, $isLatest)
    {

        // $selectQuery = "Select data_type from ox_field where id=:fieldId;";
        // $queryParams = array("fieldId" => );
        // $resultSet = $this->executeQueryWithBindParameters($selectQuery,$queryParams)->toArray();
        $query = "update ox_file set latest = :latest where id = :fileId";
        $params = array('latest' => $isLatest, 'fileId' => $fileId);
        $this->logger->info("Executing query - $query with params - " . json_encode($params));
        $result = $this->executeUpdateWithBindParameters($query, $params);
        // print_r("UpdateFileLatest - \n");
        // print_r($result->getAffectedRows());
        $this->logger->info("Affected Rows - " . print_r($result, true));
        return $result->getAffectedRows() > 0;
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
        // print_r($data['workflow_instance_id']);exit;
        $baseFolder = $this->config['APP_DOCUMENT_FOLDER'];
        if (isset($data['workflow_instance_id'])) {
            $select = "SELECT ox_file.* from ox_file where ox_file.workflow_instance_id = " . $data['workflow_instance_id'];
            $obj = $this->executeQuerywithParams($select)->toArray()[0];
            if (is_null($obj)) {
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
        if (isset($data['islatest']) && $data['islatest'] == 0) {
            $latestcheck = 1;
        }
        if (isset($data['form_uuid'])) {
            $data['form_id'] = $this->getIdFromUuid('ox_form', $data['form_uuid']);
            unset($data['form_uuid']);
        }
        if (isset($data['app_uuid'])) {
            $data['app_id'] = $this->getIdFromUuid('ox_app', $data['app_uuid']);
            unset($data['app_uuid']);
        }
        if (isset($data['form_id'])) {
            $formId = $data['form_id'];
        } else {
            $formId = null;
        }
        if (isset($data['activity_id'])) {
            $activityId = $data['activity_id'];
        } else {
            $activityId = null;
        }

        $fileObject = json_decode($obj['data'], true);

        foreach ($fileObject as $key => $fileObjectValue) {
            if (is_array($fileObjectValue)) {
                $fileObject[$key] = json_encode($fileObjectValue);
            }
        }

        foreach ($data as $key => $dataelement) {
            if (is_array($dataelement) || is_bool($dataelement)) {
                $data[$key] = json_encode($dataelement);
            }
        }

        //TODO avoid doing array merge here instead replace the incoming data as is
        $fields = array_merge($fileObject, $data);
        $file = new File();
        $id = $this->getIdFromUuid('ox_file', $id);
        $validFields = $this->checkFields(isset($obj['entity_id']) ? $obj['entity_id'] : null, $fields, $id);
        $dataArray = array_merge($fileObject, $fields);

        $fileObject = $obj;
        $dataArray = $this->cleanData($dataArray);
        $fileObject['data'] = json_encode($dataArray);
        $fileObject['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $fileObject['date_modified'] = date('Y-m-d H:i:s');

        $this->beginTransaction();
        try {
            $this->logger->info("Entering to Update File -" . json_encode($fileObject) . "\n");

            $file->exchangeArray($fileObject);
            $file->validate();
            $count = $this->table->save($file);

            $this->logger->info(json_encode($validFields) . "are the list of valid fields.\n");
            if ($validFields && !empty($validFields)) {
                $query = "Delete from ox_file_attribute where file_id = :fileId";
                $queryWhere = array("fileId" => $id);
                $result = $this->executeQueryWithBindParameters($query, $queryWhere);
                $this->multiInsertOrUpdate('ox_file_attribute', $validFields);
            }
            $this->logger->info("Leaving the updateFile method \n");
            $this->commit();
            // IF YOU DELETE THE BELOW TWO LINES MAKE SURE YOU ARE PREPARED TO CHECK THE ENTIRE INDEXER FLOW
            if (($latestcheck == 1) && isset($id)) {
                $this->messageProducer->sendTopic(json_encode(array('id' => $id)), 'FILE_DELETED');
            } else {
                if (isset($id)) {
                    $this->messageProducer->sendTopic(json_encode(array('id' => $id)), 'FILE_UPDATED');
                }
            }

        } catch (Exception $e) {
            $this->rollback();
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
        return $id;
    }

    /**
     * Delete File Service
     * @method deleteFile
     * @param $id ID of File to Delete
     * @return array success|failure response
     */
    public function deleteFile($id)
    {
        $params['org_id'] = AuthContext::get(AuthConstants::ORG_ID);
        $sql = $this->getSqlObject();
        $params = array();
        try {
            $params['uuid'] = $id;
            $update = $sql->update();
            $update->table('ox_file')
                ->set(array('is_active' => 0))
                ->where($params);
            $response = $this->executeUpdate($update);
            $id = $this->getIdFromUuid('ox_file', $id);
            // IF YOU DELETE THE BELOW TWO LINES MAKE SURE YOU ARE PREPARED TO CHECK THE ENTIRE INDEXER FLOW
            if (isset($id)) {
                $this->messageProducer->sendTopic(json_encode(array('id' => $id)), 'FILE_DELETED');
            }

            return 1;
        } catch (Exception $e) {
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
    public function getFile($id, $latest = false)
    {
        try {
            $this->logger->info("FILE ID  ------" . json_encode($id));
            $params = array('id' => $id,
                'orgId' => AuthContext::get(AuthConstants::ORG_ID));
            $select = "SELECT id, uuid, data, latest  from ox_file where uuid = :id AND org_id = :orgId";
            $this->logger->info("Executing query $select with params " . json_encode($params));
            $result = $this->executeQueryWithBindParameters($select, $params)->toArray();
            $this->logger->info("FILE DATA ------" . json_encode($result));
            if (count($result) > 0) {
                if (!$latest || ($latest && $result[0]['latest'])) {
                    $this->logger->info("FILE ID  ------" . json_encode($result));
                    if ($result[0]['data']) {
                        $result[0]['data'] = json_decode($result[0]['data'], true);
                    }
                    unset($result[0]['latest']);
                    unset($result[0]['id']);
                    $this->logger->info("FILE DATA SUCCESS ------" . json_encode($result));
                    return $result[0];
                } else {
                    $select = "SELECT uuid from ox_file where parent_id = :id";
                    $params = array('id' => $result[0]['id']);
                    $this->logger->info("Executing query $select with params " . json_encode($params));
                    $result2 = $this->executeQueryWithBindParameters($select, $params)->toArray();
                    if (count($result2) > 0) {
                        return $this->getFile($result2[0]['uuid'], $latest);
                    }
                }
            }
            return 0;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }

    public function getFileByWorkflowInstanceId($workflowInstanceId, $isProcessInstanceId = true)
    {
        if ($isProcessInstanceId) {
            $where = "ox_workflow_instance.process_instance_id=:workflowInstanceId";
        } else {
            $where = "ox_workflow_instance.id=:workflowInstanceId";
        }
        try {
            $select = "SELECT ox_file.id,ox_file.uuid as fileId,ox_file.data from ox_file
            inner join ox_workflow_instance on ox_workflow_instance.id = ox_file.workflow_instance_id
            where ox_file.org_id=:orgId and $where and ox_file.is_active =:isActive";
            $whereQuery = array("orgId" => AuthContext::get(AuthConstants::ORG_ID),
                "workflowInstanceId" => $workflowInstanceId,
                "isActive" => 1);
            $result = $this->executeQueryWithBindParameters($select, $whereQuery)->toArray();
            if (count($result) > 0) {
                $result[0]['data'] = json_decode($result[0]['data'], true);
                $result[0]['data']['fileId'] = $result[0]['fileId'];
                $result[0]['data'] = json_encode($result[0]['data']);
                return $result[0];
            }
            return 0;
        } catch (Exception $e) {
            $this->logger->log(Logger::ERR, $e->getMessage());
            throw $e;
        }
    }

    /**
     * @ignore checkFields
     */
    protected function checkFields($entityId, &$fieldData, $fileId)
    {
        $this->logger->info("Entering into checkFields method---EntityId : " . $entityId);
        $required = array();
        if (isset($entityId)) {
            $query = "SELECT ox_field.* from ox_field
            left join ox_app_entity on ox_app_entity.id = ox_field.entity_id
            where ox_app_entity.id=?";
            $where = array($entityId);
            $this->logger->info("Executing query - $query with  params" . json_encode($where));
            $fields = $this->executeQueryWithBindParameters($query, $where)->toArray();
            $this->logger->info("Query result got " . count($fields) . " fields");
        } else {
            $this->logger->info("No Entity ID");
            return 0;
        }
        $sqlQuery = "SELECT * from ox_file_attribute where ox_file_attribute.file_id=?";
        $whereParams = array($fileId);
        $this->logger->info("Executing query - $sqlQuery with  params" . json_encode($whereParams));
        $fileArray = $this->executeQueryWithBindParameters($sqlQuery, $whereParams)->toArray();
        $this->logger->info("Query result got " . count($fileArray) . " records");
        $keyValueFields = array();
        $i = 0;
        if (!empty($fields)) {
            foreach ($fields as $field) {
                if (($key = array_search($field['id'], array_column($fileArray, 'field_id'))) > -1) {
                    // Update the existing record
                    $keyValueFields[$i]['id'] = $fileArray[$key]['id'];
                } else {
                    // Insert the Record
                    $keyValueFields[$i]['id'] = null;
                }
                $fieldProperties = json_decode($field['template'], true);
                //$this->logger->info("FIELD PROPERTIES - " . json_encode($fieldProperties));
                if (isset($fieldProperties['persistent']) && !$fieldProperties['persistent']) {
                    if (isset($fieldData[$field['name']])) {
                        unset($fieldData[$field['name']]);
                    }
                    continue;
                }
                if (isset($fieldData[$field['name']]) && is_array($fieldData[$field['name']])) {
                    $fieldData[$field['name']] = json_encode($fieldData[$field['name']]);
                }
                $keyValueFields[$i]['org_id'] = (empty($fileArray[$key]['org_id']) ? AuthContext::get(AuthConstants::ORG_ID) : $fileArray[$key]['org_id']);
                $keyValueFields[$i]['created_by'] = (empty($fileArray[$key]['created_by']) ? AuthContext::get(AuthConstants::USER_ID) : $fileArray[$key]['created_by']);
                $keyValueFields[$i]['date_created'] = (!isset($fileArray[$key]['date_created']) ? date('Y-m-d H:i:s') : $fileArray[$key]['date_created']);
                $keyValueFields[$i]['date_modified'] = date('Y-m-d H:i:s');
                $keyValueFields[$i]['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
                $keyValueFields[$i]['field_value'] = isset($fieldData[$field['name']]) ? $fieldData[$field['name']] : null;
                $keyValueFields[$i]['field_id'] = $field['id'];
                $keyValueFields[$i]['file_id'] = $fileId;
                $i++;
            }
        }
        $this->logger->info("Key Values - " . json_encode($keyValueFields));
        return $keyValueFields;
    }

    public function checkFollowUpFiles($appId, $data)
    {
        try {
            $fieldWhereQuery = $this->generateFieldWhereStatement($data);
            // print_r($fieldWhereQuery);exit;
            if (!empty($fieldWhereQuery['joinQuery'] && !empty($fieldWhereQuery['whereQuery']))) {
                $queryStr = "Select * from ox_file as a
                join ox_form as b on (a.entity_id = b.entity_id)
                join ox_form_field as c on (c.form_id = b.id)
                join ox_field as d on (c.field_id = d.id)
                join ox_app as f on (f.id = b.app_id)
                " . $fieldWhereQuery['joinQuery'] . "
                where f.id = " . $data['app_id'] . " and b.id = " . $data['form_id'] . " and (" . $fieldWhereQuery['whereQuery'] . ") group by a.id";
                $this->logger->info("Executing query - $queryStr");
                $resultSet = $this->executeQuerywithParams($queryStr);
                return $dataSet = $resultSet->toArray();
            } else {
                return 0;
            }
            // $this->email->sendRemainderEmail($appId, $dataList); //Commenting this line
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
        return $dataList;
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
                    $fieldId = $this->getFieldDetails($key, $data['entity_id']);
                    if (!empty($val) && !empty($fieldId)) {
                        $joinQuery .= "left join ox_file_attribute as " . $tablePrefix . " on (a.id =" . $tablePrefix . ".file_id) ";
                        $whereQuery .= $tablePrefix . ".field_id =" . $fieldId['id'] . " and " . $tablePrefix . ".field_value ='" . $val . "' and ";
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

    public function getFieldDetails($fieldName, $entityId = null)
    {
        try {
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
        return $dataSet[0];
    }

    public function getFileList($appUUid, $params, $filterParams = null)
    {
        $this->logger->info("Inside File List API - with params - " . json_encode($params));
        $orgId = isset($params['orgId']) ? $this->getIdFromUuid('ox_organization', $params['orgId']) : AuthContext::get(AuthConstants::ORG_ID);
        $appId = $this->getIdFromUuid('ox_app', $appUUid);
        if (!isset($orgId)) {
            $orgId = $params['orgId'];
        }
        $select = "SELECT * from ox_app_registry where org_id = :orgId AND app_id = :appId";
        $selectQuery = array("orgId" => $orgId, "appId" => $appId);
        $result = $this->executeQuerywithBindParameters($select, $selectQuery)->toArray();
        if (count($result) > 0) {
            $queryParams = array();
            $appFilter = "oa.id = :appId";
            $queryParams['appId'] = $appId;
            $statusFilter = "";
            $createdFilter = "";
            $entityFilter = "";
            if (isset($params['workflowStatus'])) {
                $statusFilter = " AND wi.status = '" . $params['workflowStatus'] . "'";
            }
            if (isset($params['entityName'])) {
                $entityFilter = " AND en.name = :entityName";
                $queryParams['entityName'] = $params['entityName'];
                if (isset($params['assocId'])) {
                    if ($queryParams['assocId'] = $this->getIdFromUuid('ox_file', $params['assocId'])) {
                        $entityFilter .= " AND of.assoc_id = :assocId";
                    }

                }
            }
            if (isset($params['workflowId'])) {

                // Code to get the entityID from appId, we need this to get the correct fieldId for the filters
                $select1 = "SELECT * from ox_workflow where uuid = :uuid";
                $selectQuery1 = array("uuid" => $params['workflowId']);
                $worflowArray = $this->executeQuerywithBindParameters($select1, $selectQuery1)->toArray();

                $workflowId = $this->getIdFromUuid('ox_workflow', $params['workflowId']);
                if (!$workflowId) {
                    throw new ServiceException("Workflow Does not Exist", "app.forworkflownot.found");
                } else {
                    $appFilter .= " AND ow.id = :workflowId";
                    $queryParams['workflowId'] = $workflowId;
                }
            }
            if (isset($params['gtCreatedDate'])) {
                $createdFilter .= " AND of.date_created >= :gtCreatedDate";
                $params['gtCreatedDate'] = str_replace('-', '/', $params['gtCreatedDate']);
                // strtotime converts the date given in the UI to -1 day.
                // UI Date: 2020-02-11 00:00:00 hours , strtotime function result is: 2020-02-10 12:59:59 hours
                // +1 converts the date to 2020-02-11 00:00:00 hours so the date in Y-m-d format uses the
                // right date selected in the UI.
                $queryParams['gtCreatedDate'] = date('Y-m-d', strtotime($params['gtCreatedDate'] . "+1 days"));
            }
            if (isset($params['ltCreatedDate'])) {
                $createdFilter .= " AND of.date_created <= :ltCreatedDate";
                $params['ltCreatedDate'] = str_replace('-', '/', $params['ltCreatedDate']);
                // strtotime converts the date given in the UI to -1 day.
                // UI Date: 2020-02-11 00:00:00 hours , strtotime function result is: 2020-02-10 12:59:59 hours
                // +1 results in modified date: 2020-02-11 00:00:00 hours
                // Then, modified date: 2020-02-11, today's date: 2020-02-11, if we use the '<=' operator then
                // the modified date converts to 2020-02-11 00:00:00 hours. Inorder to get all the records
                // till EOD of 2020-02-11, we need to use 2020-02-12 hence [+2] added to the date.
                $queryParams['ltCreatedDate'] = date('Y-m-d', strtotime($params['ltCreatedDate'] . "+2 days"));
            }
            $where = " $appFilter $statusFilter $entityFilter $createdFilter and of.latest=1";
            $fromQuery = " from ox_file as of
            inner join ox_app_entity as en on en.id = of.entity_id
            inner join ox_app as oa on (oa.id = en.app_id)";
            if (isset($params['userId'])) {
                if ($params['userId'] == 'me') {
                    $userId = AuthContext::get(AuthConstants::USER_ID);
                } else {
                    $userId = $this->getIdFromUuid('ox_user', $params['userId']);
                    if (!$userId) {
                        throw new ServiceException("User Does not Exist", "app.forusernot.found");
                    }
                }
                $fromQuery .= " inner join ox_field as d on (en.id = d.entity_id) inner join (select * from ox_wf_user_identifier where ox_wf_user_identifier.user_id = :userId) as owufi ON owufi.identifier_name=d.name AND owufi.app_id=oa.id
                INNER JOIN ox_file_attribute ofa on ofa.file_id = of.id and ofa.field_id = d.id and ofa.field_value = owufi.identifier ";
                $userWhere = " and owufi.user_id = :userId and owufi.org_id = :orgId";
                $queryParams['userId'] = $userId;
                $queryParams['orgId'] = $orgId;
            } else {
                $userWhere = "";
            }

            $fromQuery .= " left join ox_workflow_instance as wi on of.workflow_instance_id = wi.id
            left join ox_workflow_deployment as wd on wd.id = wi.workflow_deployment_id
            left join ox_workflow as ow on ow.id = wd.workflow_id";
            $prefix = 1;
            $whereQuery = "";
            $joinQuery = "";
            $sort = "";
            $field = "";
            $pageSize = " LIMIT 10";
            $offset = " OFFSET 0";
            $sortjoinQuery = "";
            if (!empty($filterParams)) {
                if (isset($filterParams['filter']) && !is_array($filterParams['filter'])) {
                    $jsonParams = json_decode($filterParams['filter'], true);
                    if (isset($filterParamsArray['filter'])) {
                        $filterParamsArray[0] = $jsonParams;
                    } else {
                        $filterParamsArray = $jsonParams;
                    }
                } else {
                    if (isset($filterParams['filter'])) {
                        $filterParamsArray = $filterParams['filter'];
                    } else {
                        $filterParamsArray = $filterParams;
                    }
                }
                if (isset($filterParamsArray[0]) && is_array($filterParamsArray[0])) {
                    if (array_key_exists("sort", $filterParamsArray[0])) {
                        $sortParam = $filterParamsArray[0]['sort'];
                    }
                }
                $filterlogic = isset($filterParamsArray[0]['filter']['logic']) ? $filterParamsArray[0]['filter']['logic'] : " AND ";
                $cnt = 1;
                $fieldParams = array();
                $tableFilters = "";
                if (isset($filterParamsArray[0]['filter'])) {
                    $filterData = $filterParamsArray[0]['filter']['filters'];
                    $subQuery = "";
                    foreach ($filterData as $val) {
                        $tablePrefix = "tblf" . $prefix;
                        if (!empty($val)) {
                            if ($subQuery != '') {
                                $subQuery .= " " . $filterlogic . " of.id in ";
                            } else {
                                $subQuery = " of.id in ";
                            }
                            $subQuery .= " (select distinct ox_file.id from ox_file inner join ox_file_attribute as " . $tablePrefix . " on (ox_file.id =" . $tablePrefix . ".file_id) inner join ox_field as " . $val['field'] . $tablePrefix . " on( " . $val['field'] . $tablePrefix . ".id = " . $tablePrefix . ".field_id )";
                            $filterOperator = $this->processFilters($val);
                            $queryString = $filterOperator["operation"] . "'" . $filterOperator["operator1"] . "" . $val['value'] . "" . $filterOperator["operator2"] . "'";
                            $subQuery .= " WHERE ";
                            $subQuery .= " (" . $val['field'] . $tablePrefix . ".entity_id = ox_file.entity_id and " . $val['field'] . $tablePrefix . ".name ='" . $val['field'] . "' and (CASE WHEN (" . $val['field'] . $tablePrefix . ".data_type='date') THEN CAST(" . $tablePrefix . ".field_value AS DATETIME) $queryString WHEN (" . $val['field'] . $tablePrefix . ".data_type='int') THEN " . $tablePrefix . ".field_value " . (($filterOperator['integerOperation'])) . " '" . $val['value'] . "' ELSE (" . $tablePrefix . ".field_value $queryString) END )))";
                        }
                        $prefix += 1;
                    }
                    if (isset($filterParamsArray[0]['sort']) && !empty($filterParamsArray[0]['sort'])) {
                        $sortCount = 0;
                        $sortTable = "tblf" . $sortCount;
                        $sort = " ORDER BY ";
                        foreach ($filterParamsArray[0]['sort'] as $key => $value) {
                            if ($sortCount == 0) {
                                $sort .= $value['field'] . " " . $value['dir'];
                            } else {
                                $sort .= "," . $value['field'] . " " . $value['dir'];
                            }
                            $field .= " , (select " . $sortTable . ".field_value from ox_file_attribute as " . $sortTable . " inner join ox_field as " . $value['field'] . $sortTable . " on( " . $value['field'] . $sortTable . ".id = " . $sortTable . ".field_id)  WHERE " . $value['field'] . $sortTable . ".name='" . $value['field'] . "' AND " . $sortTable . ".file_id=of.id) as " . $value['field'];
                            $sortCount += 1;
                        }
                    }
                    if ($subQuery != "") {
                        $whereQuery = " AND (" . $subQuery . ")";
                    }
                }
                $pageSize = " LIMIT " . (isset($filterParamsArray[0]['take']) ? $filterParamsArray[0]['take'] : 10);
                $offset = " OFFSET " . (isset($filterParamsArray[0]['skip']) ? $filterParamsArray[0]['skip'] : 0);
            }
            $where .= " " . $whereQuery . "";
            $fromQuery .= " " . $joinQuery . " " . $sortjoinQuery;
            try {
                $countQuery = "select DISTINCT count(of.uuid) as `count` $fromQuery  WHERE ($where) $userWhere";
                $this->logger->info("Executing query - $countQuery with params - " . json_encode($queryParams));
                $countResultSet = $this->executeQueryWithBindParameters($countQuery, $queryParams)->toArray();
                $this->logger->info("Executing COUNT query - $select with params - " . json_encode($queryParams));
                $select = "SELECT of.data, of.uuid, wi.status, wi.process_instance_id as workflowInstanceId, en.name as entity_name $field $fromQuery WHERE $where $userWhere $sort $pageSize $offset";
                $this->logger->info("Executing query - $select with params - " . json_encode($queryParams));
                $resultSet = $this->executeQueryWithBindParameters($select, $queryParams)->toArray();
                if ($resultSet) {
                    $i = 0;
                    foreach ($resultSet as $file) {
                        if ($file['data']) {
                            $content = json_decode($file['data'], true);
                            if ($content) {
                                $resultSet[$i] = array_merge($file, $content);
                            }
                        }
                        $i++;
                    }
                }
                return array('data' => $resultSet, 'total' => $countResultSet[0]['count']);
            } catch (Exception $e) {
                throw new ServiceException($e->getMessage(), "app.mysql.error");
            }
        } else {
            throw new ServiceException("App Does not belong to the org", "app.fororgnot.found");
        }
    }

    public function getFileDocumentList($params)
    {
        $selectQuery = 'select ox_field.name, ox_file_attribute.* from ox_file
        inner join ox_file_attribute on ox_file_attribute.file_id = ox_file.id
        inner join ox_field on ox_field.id = ox_file_attribute.field_id
        inner join ox_app on ox_field.app_id = ox_app.id
        where ox_file.org_id=:organization and ox_app.uuid=:appUuid and ox_field.data_type=:dataType
        and ox_file.uuid=:fileUuid';
        $selectQueryParams = array('organization' => AuthContext::get(AuthConstants::ORG_ID),
            'appUuid' => $params['appId'],
            'fileUuid' => $params['fileId'],
            'dataType' => 'document');
        $this->logger->info("Executing query $selectQuery with params - " . json_encode($selectQueryParams));
        try {
            $selectResultSet = $this->executeQueryWithBindParameters($selectQuery, $selectQueryParams)->toArray();
            if (count($selectResultSet) > 0 && isset($selectResultSet[0])) {
                $selectResultSet[0]['field_value'] = json_decode($selectResultSet[0]['field_value'], true);
                return $selectResultSet[0];
            } else {
                return array();
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            return 0;
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
            $integerOperation = "=";
        }

        return $returnData = array(
            "operation" => $operation,
            "operator1" => $operatorp1,
            "operator2" => $operatorp2,
            "integerOperation" => $integerOperation,
        );
    }

    private function cleanData($params)
    {
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
        unset($params['org_id']);
        unset($params['orgId']);
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
}
