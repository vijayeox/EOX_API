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
use Oxzion\Utils\ArrayUtils;
use Oxzion\Model\FileAttachment;
use Oxzion\Model\FileAttachmentTable;
use Oxzion\Utils\FileUtils;

class FileService extends AbstractService
{
    protected $fieldService;
    protected $fieldDetails;
    /**
     * @ignore __construct
     */
    public function __construct($config, $dbAdapter, FileTable $table, FormService $formService, MessageProducer $messageProducer, FieldService $fieldService,FileAttachmentTable $attachmentTable)
    {
        parent::__construct($config, $dbAdapter);
        $this->messageProducer = $messageProducer;
        $this->table = $table;
        $this->config = $config;
        $this->dbAdapter = $dbAdapter;
        $this->fieldService = $fieldService;
        $this->fieldDetails=[];
        $this->attachmentTable = $attachmentTable;
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

        $data['uuid'] = $uuid;
        $data['org_id'] = AuthContext::get(AuthConstants::ORG_ID);
        $data['created_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_created'] = date('Y-m-d H:i:s');
        $data['form_id'] = $formId;
        $data['date_modified'] = date('Y-m-d H:i:s');
        $data['entity_id'] = $entityId;
        if(isset($oldData['bos']['assoc_id'])){
            $data['assoc_id'] = $this->getIdFromUuid('ox_file', $oldData['bos']['assoc_id']);
            if($data['assoc_id'] == 0){
                throw new EntityNotFoundException("File Id not found -- " . $oldData['bos']['assoc_id']);
            }
        } else {
            $data['assoc_id'] = null;
        }
        $data['data'] = $jsonData;
        $data['last_workflow_instance_id'] = isset($oldData['last_workflow_instance_id']) ? $oldData['last_workflow_instance_id'] : null;
        $file = new File();
        if(isset($data['id'])){
            unset($data['id']);
        }
        $file->exchangeArray($data);
        $this->logger->info("File data From Fileservice - " . print_r($file->toArray(), true));
        $file->validate();

        $count = 0;
        try {
            $this->beginTransaction();
            $count = $this->table->save($file);
            $this->logger->info("COUNT  FILE DATA----" . $count);
            if ($count == 0) {
                throw new ServiceException("File Creation Failed", "file.create.failed");
            }
            $id = $this->table->getLastInsertValue();
            $this->logger->info("FILE ID DATA" . $id);
            $data['id'] = $id;
            $this->logger->info("FILE DATA ----- " . json_encode($data));
            $validFields = $this->checkFields($data['entity_id'], $fields, $id, false);
            $this->updateFileData($id, $fields);
            $this->logger->debug("Check Fields Data ----- " . print_r($validFields,true));
            $this->logger->info("Checking Index Fields ---- " . print_r($validFields['indexedFields'],true));
            if(count($validFields['indexedFields']) > 0 ){
                $this->multiInsertOrUpdate('ox_indexed_file_attribute', $validFields['indexedFields']);
            }
            $this->logger->info("Checking Document Fields ---- " . json_encode($validFields['documentFields']));
            if(count($validFields['documentFields']) > 0 ){
                $this->multiInsertOrUpdate('ox_file_document', $validFields['documentFields']);
            }
            $this->logger->info("Created successfully  - file record");
            $this->commit();
            // IF YOU DELETE THE BELOW TWO LINES MAKE SURE YOU ARE PREPARED TO CHECK THE ENTIRE INDEXER FLOW
            if (isset($data['id'])) {
                $this->messageProducer->sendQueue(json_encode(array('id' => $data['id'])), 'FILE_ADDED');
            }
        } catch (Exception $e) {
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

    public function updateFileAttributes($fileId){
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

    private function updateFileUserContext($obj){
        $orgId = $obj['org_id'];
        $userId = $obj['modified_by'] ? $obj['modified_by'] : $obj['created_by'];
        $orgUuid = $this->getUuidFromId('ox_organization', $orgId);
        $userUuid = $this->getUuidFromId('ox_user', $userId);
        $context = ['orgId' => $orgUuid, 'userId' => $userUuid];
        $this->updateOrganizationContext($context);
    }
    private function updateFileAttributesInternal($entityId, $fields, $fileId){
        $validFields = $this->checkFields($entityId ,$fields, $fileId);
        $validFields = $validFields['validFields'];
        $fields = $validFields['data'];
        unset($validFields['data']);
        $this->logger->info(json_encode($validFields) . "are the list of valid fields.\n");
        try{
            $this->beginTransaction();
            if ($validFields && !empty($validFields)) {
                $query = "delete from ox_file_attribute where file_id = :fileId";
                $queryWhere = array("fileId" => $fileId);
                $result = $this->executeUpdateWithBindParameters($query, $queryWhere);
                $this->multiInsertOrUpdate('ox_file_attribute', $validFields);
                $this->logger->info("Checking Fields update ---- " . print_r($validFields,true));
                $query = "update ox_indexed_file_attribute ifa
                            inner join ox_file_attribute fa on ifa.file_id = fa.file_id and ifa.field_id = fa.field_id
                            inner join ox_field f on fa.field_id = f.id
                            set ifa.field_value_text = fa.field_value_text, ifa.field_value_numeric = fa.field_value_numeric,
                                ifa.field_value_boolean = fa.field_value_boolean, ifa.field_value_date = fa.field_value_date,
                                ifa.field_value_type = fa.field_value_type, ifa.modified_by = fa.modified_by, ifa.date_modified = fa.date_modified
                            where fa.file_id = :fileId and f.index = 1";
                $this->logger->info("Executing query $query with params - ". json_encode($queryWhere));
                $this->executeUpdateWithBindParameters($query, $queryWhere);
                $query = "INSERT INTO ox_indexed_file_attribute (file_id, field_id, org_id, field_value_text,
                            field_value_date, field_value_numeric, field_value_boolean, field_value_type, date_created,
                            created_by, date_modified, modified_by)
                          (SELECT fa.file_id, fa.field_id, fa.org_id, fa.field_value_text,
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
                $query = "INSERT INTO ox_file_document (file_id, field_id, org_id, field_value, sequence,
                            date_created, created_by, date_modified, modified_by)
                          (SELECT fa.file_id, fa.field_id, fa.org_id, fa.field_value, fa.sequence,
                            fa.date_created, fa.created_by, fa.date_modified, fa.modified_by from ox_file_attribute fa
                            inner join ox_field f on f.id = fa.field_id
                            left outer join ox_file_document ifa on ifa.file_id = fa.file_id and ifa.field_id = fa.field_id and (ifa.sequence = fa.sequence or (fa.sequence is null and ifa.sequence is null))
                            where fa.file_id = :fileId and f.type IN ('document','file')and ifa.id is null)";
                $this->logger->info("Executing query $query with params - ". json_encode($queryWhere));
                $this->executeUpdateWithBindParameters($query, $queryWhere);
            }
            $this->logger->info("Update File Data after checkFields ---- " . json_encode($fields));
            // The next line needs to be removed for file save to work
            // $this->updateFileData($fileId, $fields);
            $this->commit();
        }catch(Exception $e){
            $this->rollback();
            throw $e;
        }
    }
    public function startBatchProcessing(){
        $this->beginTransaction();
    }

    public function completeBatchProcessing(){
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
            if(!empty($obj)&& !is_null($obj)) {
                $obj = $obj[0];
            }
            else {
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

        if(isset($obj['entity_id'])){
            $entityId = $obj['entity_id'];
        } else {
            throw new ServiceException("Invalid Entity", "entity.invalid");
        }


        $fields = $this->processMergeData($entityId, $fileObject, $data);
        $file = new File();
        $id = $this->getIdFromUuid('ox_file', $id);
        $validFields = $this->checkFields($entityId ,$fields, $id, false);
        $dataArray = $this->processMergeData($entityId, $fileObject, $fields);
        $fileObject = $obj;
        $dataArray = $this->cleanData($dataArray);
        $fileObject['data'] = json_encode($dataArray);
        $fileObject['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $fileObject['date_modified'] = date('Y-m-d H:i:s');
        if (isset($data['last_workflow_instance_id'])) {
           $fileObject['last_workflow_instance_id'] = $data['last_workflow_instance_id'];
        }
        $count = 0;
        try {
            $this->beginTransaction();
            $this->logger->info("Entering to Update File -" . json_encode($fileObject) . "\n");
            $file->exchangeArray($fileObject);
            $file->validate();
            $count = $this->table->save($file);
            $this->logger->info(json_encode($validFields) . "are the list of valid fields.\n");
            if ($validFields && !empty($validFields)) {
                $queryWhere = array("fileId" => $id);
                $query = "delete from ox_indexed_file_attribute where file_id = :fileId";
                $result = $this->executeQueryWithBindParameters($query, $queryWhere);
                $this->logger->info("Checking Fields update ---- " . print_r($validFields,true));
                if($validFields['indexedFields'] && count($validFields['indexedFields']) > 0 ){
                    $this->multiInsertOrUpdate('ox_indexed_file_attribute', $validFields['indexedFields']);
                }
                $query = "delete from ox_file_document where file_id = :fileId";
                $result = $this->executeQueryWithBindParameters($query, $queryWhere);
                $this->logger->info("Checking Fields update ---- " . print_r($validFields,true));
                if($validFields['documentFields'] && count($validFields['documentFields']) > 0 ){
                    $this->multiInsertOrUpdate('ox_file_document', $validFields['documentFields']);
                }
            }
            $this->logger->info("Leaving the updateFile method \n");
            $this->commit();
            // IF YOU DELETE THE BELOW TWO LINES MAKE SURE YOU ARE PREPARED TO CHECK THE ENTIRE INDEXER FLOW
            if (($latestcheck == 1) && isset($id)) {
                $this->messageProducer->sendQueue(json_encode(array('id' => $id)), 'FILE_DELETED');
            } else {
                if (isset($id)) {
                    $this->messageProducer->sendQueue(json_encode(array('id' => $id)), 'FILE_UPDATED');
                }
            }

        } catch (Exception $e) {
            $this->rollback();
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
        return $count;
    }

    private function processMergeData($entityId, $fileObject, $data){
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
    public function deleteFile($id)
    {
        $params = array();
        $params['orgId'] = AuthContext::get(AuthConstants::ORG_ID);
        $sql = $this->getSqlObject();
        try {
            $params['uuid'] = $id;

            //Delete all children along with parent
            $update = "UPDATE ox_file of1
                left join ox_file of2 on of1.id = of2.assoc_id
                set of1.is_active = 0,of2.is_active = 0
                where of1.uuid = :uuid and of1.org_id = :orgId";
            $this->logger->info("Executing query $update with params " . json_encode($params));
            $result = $this->executeUpdateWithBindParameters($update, $params);

            $id = $this->getIdFromUuid('ox_file', $id);
            // IF YOU DELETE THE BELOW TWO LINES MAKE SURE YOU ARE PREPARED TO CHECK THE ENTIRE INDEXER FLOW
            if (isset($id)) {
                $this->messageProducer->sendQueue(json_encode(array('id' => $id)), 'FILE_DELETED');
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
    public function getFile($id, $latest = false, $orgId = null)
    {
        try {
            $this->logger->info("FILE ID  ------" . json_encode($id));
            $orgId = isset($orgId) ? $this->getIdFromUuid('ox_organization', $orgId) :
                                    AuthContext::get(AuthConstants::ORG_ID);
            $params = array('id' => $id,
                'orgId' => $orgId);
            $select = "SELECT id, uuid, data, entity_id  from ox_file where uuid = :id AND org_id = :orgId";
            $this->logger->info("Executing query $select with params " . json_encode($params));
            $result = $this->executeQueryWithBindParameters($select, $params)->toArray();
            $this->logger->info("FILE DATA ------" . json_encode($result));
            if (count($result) > 0) {
                    $this->logger->info("FILE ID  ------" . json_encode($result));
                    if ($result[0]['data']) {
                        $result[0]['data'] = json_decode($result[0]['data'], true);
                    }
                    unset($result[0]['id']);
                    $this->logger->info("FILE DATA SUCCESS ------" . json_encode($result));
                    return $result[0];
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
            $select = "SELECT ox_file.id,ox_file.uuid as fileId, ox_file.data, ox_file.last_workflow_instance_id from ox_file
            inner join ox_workflow_instance on ox_workflow_instance.file_id = ox_file.id
            where ox_file.org_id=:orgId and $where and ox_file.is_active =:isActive";
            $whereQuery = array("orgId" => AuthContext::get(AuthConstants::ORG_ID),
                "workflowInstanceId" => $workflowInstanceId,
                "isActive" => 1);
            $result = $this->executeQueryWithBindParameters($select, $whereQuery)->toArray();
            if (count($result) > 0) {
                $result[0]['data'] = json_decode($result[0]['data'], true);
                $result[0]['data']['fileId'] = $result[0]['fileId'];
                foreach ($result[0]['data'] as $key => $value) {
                    if(is_string($value)){
                        $tempValue = json_decode($value,true);
                        if(isset($tempValue)){
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
            if(!$allFields){
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
        if($allFields){
            $fileArray = $this->getFileAttributes($fileId, 'ox_file_attribute');
            $keyValueFields = array();
        }else{
            $indexedFileArray = $this->getFileAttributes($fileId, 'ox_indexed_file_attribute');
            $documentArray = $this->getFileAttributes($fileId, 'ox_file_document');
            $indexedFields = array();
            $documentFields = array();
        }

        $i = 0;

        $childFields = array();
        if (!empty($fields)) {
            foreach ($fields as $field) {

                if(!in_array($field['name'], array_keys($fieldData)) ){
                    continue;
                }
                if (!$allFields && ($field['index'] != 0 || $field['type'] == 'document' || $field['type'] == 'file'|| $field['child_fields'])) {
                    $indexedField = array();

                    if($field['index'] == 0){
                        $fileDataArray =  &$documentArray;
                        $fileFields = &$documentFields;
                    }else{
                        $fileDataArray =  &$indexedFileArray;
                        $fileFields = &$indexedFields;
                    }
                    $fieldvalue = isset($fieldData[$field['name']]) ? (is_array($fieldData[$field['name']]) ? json_encode($fieldData[$field['name']]) : $fieldData[$field['name']]) : null;
                    $indexedField = array_merge($indexedField, $this->generateFieldPayload($field, $fieldvalue, $entityId, $fileId, $fileDataArray,$allFields));
                    if ($field['index'] == 1 && $indexedField['field_value_type'] == 'OTHER') {
                       throw new ServiceException("Unsupported data type for indexing for field - ".$field['name']." with dataType -".$field['data_type'],"invalid.datatype");
                    }
                    unset($indexedField[$field['name']]);
                    $childFieldsPresent = false;
                    if($field['index'] == 1){
                        unset($indexedField['sequence']);
    					unset($indexedField['childFields']);
                    }else{
                        $indexedField['field_value']=is_array($fieldvalue) ? json_encode($fieldvalue):$fieldvalue;
                        unset($indexedField['field_value_text']);
                        unset($indexedField['field_value_type']);
                        unset($indexedField['field_value_numeric']);
                        unset($indexedField['field_value_boolean']);
                        unset($indexedField['field_value_date']);
                        if( isset($indexedField['childFields']) && count($indexedField['childFields']) > 0){
                            foreach ($indexedField['childFields'] as $childField) {
                                array_push($childFields, $childField);
                            }
                            $childFieldsPresent = true;
                        }
                        unset($indexedField['childFields']);
                    }
                    if($field['type'] == 'document' || $field['type'] == 'file' || $field['index'] == 1){
                        $fileFields[] = $indexedField;
                    }
                    $fieldData[$field['name']] = $fieldvalue;
                    unset($indexedField);
                }
                if($allFields){
                    $fieldvalue = isset($fieldData[$field['name']]) ? (is_array($fieldData[$field['name']]) ? json_encode($fieldData[$field['name']]) : $fieldData[$field['name']]) : null;
                    $keyValueFields[$i]['field_value']=$fieldvalue;
                    $keyValueFields[$i] = array_merge($keyValueFields[$i],$this->generateFieldPayload($field,$fieldvalue,$entityId,$fileId,$fileArray, $allFields));

                    if($field['type'] == 'file'){
                        $keyValueFields['data'][$field['name']] = isset($keyValueFields[$i][$field['name']]) ? $keyValueFields[$i][$field['name']] : array();
                    }else{
                        $keyValueFields['data'][$field['name']] = isset($fieldData[$field['name']]) ? $fieldData[$field['name']] : null;
                    }

                    if( isset($keyValueFields[$i]['childFields']) && count($keyValueFields[$i]['childFields']) > 0){
                        foreach ($keyValueFields[$i]['childFields'] as $childField) {
                            array_push($childFields, $childField);
                        }
                    }
                    if(isset($keyValueFields[$i]['data'])){
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
        if(!empty($childFields)){
            if(!$allFields){
                $fileFields = &$documentFields;
            }else{
                $fileFields = &$keyValueFields;
            }

            $this->collateChildFields($childFields, $fileFields, $allFields);

        }
        $this->logger->debug("Key Values - " . json_encode($keyValueFields));
        $this->logger->debug("Indexed Values - " . json_encode($indexedFields));
        return array('validFields' => $keyValueFields,'indexedFields' => $indexedFields, 'documentFields' => $documentFields);
    }

    private function collateChildFields($childFields, &$fileFields, $allFields){
        $index = count($fileFields);
        foreach ($childFields as $child) {
            if($allFields){
                if(isset($child['data'])){
                    $keyValueFields['data'][$field['name']] = $child['data'];
                    unset($child['data']);
                }else if(array_key_exists('data',$child)){
                     unset($child['data']);
                }
            }else{
                unset($child['field_value_type']);
                unset($child['field_value_text']);
                unset($child['field_value_numeric']);
                unset($child['field_value_boolean']);
                unset($child['field_value_date']);
            }
            $fileFields[$index] = $child;
            if(isset($child['childFields']) && !empty($child['childFields'])){
                unset($fileFields[$index]['childFields']);
                $this->collateChildFields($child['childFields'], $fileFields, $allFields);
            }
            $index++;
        }
    }

    private function getFileAttributes($fileId, $attributeTable, $parentId = null){
        $filter = "";
        $join = "";
        $whereParams = array('fileId' => $fileId);
        if($attributeTable == 'ox_file_attribute' && !$parentId ){
            $filter = "and fa.sequence is null";
        }else if ($attributeTable != 'ox_indexed_file_attribute' && $parentId ){
            $filter = "and fa.sequence is not null and f.parent_id = :parentId order by fa.sequence asc";
            if($attributeTable == 'ox_file_document'){
                $filter = "and f.type IN ('document','file') $filter";
            }
            $join = "inner join ox_field f on f.id = fa.field_id";
            $whereParams['parentId'] = $parentId;
        }
        $sqlQuery = "SELECT fa.* from $attributeTable fa $join where fa.file_id=:fileId $filter";
        $this->logger->debug("Executing query - $sqlQuery with  params" . json_encode($whereParams));
        $fileArray = $this->executeQueryWithBindParameters($sqlQuery, $whereParams)->toArray();
        $this->logger->debug("Query result got " . count($fileArray) . " records");
        if(!$parentId){
            return $fileArray;
        }
        $result = array();
        $sequenceArray = null;
        foreach ($fileArray as $value) {
            $sequence = $value['sequence'];
            if(!isset($result[$sequence])){
                $result[$sequence] = array();
            }
            $sequenceArray = &$result[$sequence];
            $sequenceArray[] = $value;
        }

        return $result;
    }
    private function generateFieldPayload($field,&$fieldvalue,$entityId,$fileId,$fileArray,$allFields, &$rowNumber = -1){
        $fieldData = array();
        if (($key = array_search($field['id'], array_column($fileArray, 'field_id'))) > -1) {
            $fieldData['id'] = $fileArray[$key]['id'];
        } else {
            $fieldData['id'] = null;
        }
        $fieldData['sequence'] = null;
        if($rowNumber > -1){
            $fieldData['sequence'] = $rowNumber;
        }else{
            $rowNumber = 0;
        }
        $fieldData['file_id'] = $fileId;
        $fieldData['field_id'] = $field['id'];
        $fieldData['org_id'] = (empty($fileArray[$key]['org_id']) ? AuthContext::get(AuthConstants::ORG_ID) : $fileArray[$key]['org_id']);
        $fieldData['created_by'] = (empty($fileArray[$key]['created_by']) ? AuthContext::get(AuthConstants::USER_ID) : $fileArray[$key]['created_by']);
        $fieldData['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $fieldData['date_created'] = (!isset($fileArray[$key]['date_created']) ? date('Y-m-d H:i:s') : $fileArray[$key]['date_created']);
        $fieldData['date_modified'] = date('Y-m-d H:i:s');
        $dataType = $field['data_type'];
        switch ($dataType) {
            case 'text':
                $fieldData['field_value_type'] = 'TEXT';
                $fieldData['field_value_text'] = $fieldvalue;
                $fieldData['field_value_numeric'] = NULL;
                $fieldData['field_value_boolean'] = NULL;
                $fieldData['field_value_date'] = NULL;
                $fieldData[$field['name']] = $fieldvalue;
                break;
            case 'numeric':
                $fieldData['field_value_type'] = 'NUMERIC';
                $fieldData['field_value_text'] = NULL;
                $fieldData['field_value_numeric'] = (double)$fieldvalue;
                $fieldData[$field['name']] = $fieldData['field_value_numeric'];
                $fieldData['field_value_boolean'] = NULL;
                $fieldData['field_value_date'] = NULL;
                break;
            case 'boolean':
                if(isset($boolVal)){
                    unset($boolVal);
                }
                $boolVal = false;
                if((is_bool($fieldvalue) && $fieldvalue == true) || (is_string($fieldvalue) && $fieldvalue == "true") || (is_int($fieldvalue) && $fieldvalue == 1)) {
                    $boolVal = true;
                    $fieldvalue = 1;
                } else {
                    $boolVal = false;
                    $fieldvalue = 0;
                }
                $fieldData['field_value_type'] = 'BOOLEAN';
                $fieldData['field_value_text'] = NULL;
                $fieldData['field_value_numeric'] = NULL;
                $fieldData['field_value_boolean'] = $fieldvalue;
                $fieldData['field_value_date'] = NULL;
                $fieldData[$field['name']] = $boolVal;
                break;
            case 'date':
            case 'datetime':
                $fieldData['field_value_type'] = 'DATE';
                $fieldData['field_value_text'] = NULL;
                $fieldData['field_value_numeric'] = NULL;
                $fieldData['field_value_boolean'] = NULL;
                if(empty($fieldvalue)){
                    $fieldData['field_value_date'] = NULL;
                } else if(is_string($fieldvalue) && date_create($fieldvalue)){
                    $fieldData['field_value_date'] = date_format(date_create($fieldvalue),'Y-m-d H:i:s');
                } else {
                    $fieldData['field_value_date'] = date_format(date_create(),'Y-m-d H:i:s');;
                }
                $fieldData[$field['name']] = $fieldData['field_value_date'];
                break;
            case 'list':
                $fieldData['field_value_type'] = 'OTHER';
                $fieldData['field_value_text'] = NULL;
                $fieldData['field_value_numeric'] = NULL;
                $fieldData['field_value_boolean'] = NULL;
                $fieldData['field_value_date'] = NULL;
                if($field['type']=='file'){
                    $attachmentsArray = is_string($fieldvalue) ? json_decode($fieldvalue,true) : $fieldvalue;
                    $finalAttached = array();
                    if(!isset($attachmentsArray)){
                        $attachmentsArray = array();
                    }
                    if(is_array($attachmentsArray)){
                        foreach ($attachmentsArray as $attachment) {
                            $finalAttached[] = $this->appendAttachmentToFile($attachment,$field,$fileId);
                        }
                        $fieldData['field_value']=json_encode($finalAttached);
                    }
                    $fieldData[$field['name']] = $finalAttached;
                    $this->logger->info("Field Created with File- " . json_encode($fieldData));
                    break;
                } else {
                    $fieldData[$field['name']] = $fieldvalue;
                    break;
                }
            default:
                $fieldData['field_value_type'] = 'OTHER';
                $fieldData['field_value_text'] = NULL;
                $fieldData['field_value_numeric'] = NULL;
                $fieldData['field_value_boolean'] = NULL;
                $fieldData['field_value_date'] = NULL;
                if($field['type']=='file'){
                    if(is_string($fieldvalue)){
                        $attachmentsArray = json_decode($fieldvalue,true);
                    } else {
                        $attachmentsArray = $fieldvalue;
                    }
                    if(!isset($attachmentsArray)){
                        $attachmentsArray = array();
                    }
                    if(is_array($attachmentsArray)){
                        $finalAttached = array();
                        foreach ($attachmentsArray as $attachment) {
                            $finalAttached[] = $this->appendAttachmentToFile($attachment,$field,$fileId);
                        }
                        $fieldData['field_value']=json_encode($finalAttached);
                        $fieldvalue = $finalAttached;
                        $fieldData[$field['name']] = $finalAttached;
                    }
                } else {
                    $fieldData[$field['name']] = $fieldvalue;
                }
            break;
        }
        $fieldvalue = isset($fieldData[$field['name']]) ? $fieldData[$field['name']] : null;
        if(isset($field['child_fields'])  && !empty($field['child_fields']) ){
            if(is_string($fieldvalue)){
                $fieldvalue = json_decode($fieldvalue,true);
            }
            $fldValue = $fieldvalue;
            $fieldData['childFields'] = $this->getChildFieldsData($field,$fldValue,$field['child_fields'],$entityId,$fileId,$rowNumber, $allFields);
            foreach ($fldValue as $i => $value) {
                foreach ($value as $key => $fVal) {
                    $temp = !is_array($fVal) ? json_decode($fVal) : $fVal;
                    $fieldvalue[$i][$key] = $temp ? $temp : $fVal;
                }
            }

            if(isset($fieldData['childFields']['childFields']) && count($fieldData['childFields']['childFields'])>0){
                foreach ($fieldData['childFields']['childFields'] as $childfield) {
                    array_push($fieldData['childFields'],$childfield);
                }
                unset($fieldData['childFields']['childFields']);
            }
        } else {
            $fieldData['childFields'] = array();
        }

        return $fieldData;
    }

    public function getChildFieldsData($parentField,&$fieldvalue,$fieldsString,$entityId,$fileId,&$rowNumber, $allFields){
        $filter = "";
        if(!$allFields){
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
        if($allFields){
            $fileAttributes = $this->getFileAttributes($fileId, 'ox_file_attribute', $parentField['id']);
        }else{
            $fileAttributes = $this->getFileAttributes($fileId, 'ox_file_document', $parentField['id']);
        }

        if(count($childFields) > 0){
            if(is_array($fieldvalue)){
                $i = 0;
                foreach ($fieldvalue as $k => $value) {
                    $childFieldValues = array();
                    $fileArray = isset($fileAttributes[$rowNumber]) ? $fileAttributes[$rowNumber] : array();
                    foreach ($childFields as $field) {
                        $val = isset($value[$field['name']]) ? (is_array($value[$field['name']]) ? json_encode($value[$field['name']]) : $value[$field['name']]) : null;
                        if ($allFields) {
                            $childFieldsArray[$i]['field_value']=$val;
                        }else{
                            $childFieldsArray[] = array();
                        }
                        $childFieldsArray[$i] = array_merge($childFieldsArray[$i],$this->generateFieldPayload($field,$val,$entityId,$fileId,$fileArray, $allFields, $rowNumber));
                        if(count($childFieldsArray[$i]['childFields']) > 0){
                            foreach ($childFieldsArray[$i]['childFields'] as $childField) {
                                array_push($grandChildren, $childField);
                            }
                        } else {
                            unset($childFieldsArray[$i]['childFields']);
                        }
                        $childFieldValues[$field['name']] = isset($value[$field['name']]) ? $value[$field['name']] : null;

                        if($field['type'] == 'file'){
                            $childFieldValues[$field['name']] = is_array($val) ? json_encode($val) : $val;
                        }
                        unset($childFieldsArray[$i][$field['name']]);
                        $i++;
                    }
                    $fieldvalue[$k] = $childFieldValues;
                    $rowNumber ++;
                }
            }
            if(isset($grandChildren) && count($grandChildren)>0){
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

    public function getValueColumn($field) {
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
            $queryParams['appId'] = $appId;
            $where = "";
            $whereQuery = "";
            $workflowJoin = "";
            $workflowFilter = "";
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
            $this->getFileFilters($params, $where, $queryParams);
            $where = " $workflowFilter $where";
            $fromQuery = " from ox_file as `of`
            inner join ox_app_entity as en on en.id = `of`.entity_id
            inner join ox_app as oa on (oa.id = en.app_id AND oa.id = :appId) ";
            if (isset($params['userId'])) {
                if ($params['userId'] == 'me') {
                    $userId = AuthContext::get(AuthConstants::USER_ID);
                } else {
                    $userId = $this->getIdFromUuid('ox_user', $params['userId']);
                    if (!$userId) {
                        throw new ServiceException("User Does not Exist", "app.forusernot.found");
                    }
                }
                $identifierQuery = "select identifier_name,identifier from ox_wf_user_identifier where user_id=:userId and app_id = :appId";
                $identifierParams = array('userId'=>$userId,'appId'=>$appId);
                $getIdentifier = $this->executeQueryWithBindParameters($identifierQuery, $identifierParams)->toArray();
                if(isset($getIdentifier) && count($getIdentifier)>0){
                    $fromQuery .= " INNER JOIN ox_indexed_file_attribute ofa on (ofa.file_id = of.id) inner join ox_field as d on (ofa.field_id = d.id and d.name= :fieldName)
                        INNER join ox_entity_identifier as oei on oei.identifier = '".$getIdentifier[0]['identifier_name']."' AND oei.entity_id = en.id ";
                    $queryParams['fieldName'] = $getIdentifier[0]['identifier_name'];
                    $queryParams['identifier'] = $getIdentifier[0]['identifier'];
                    $whereQuery = " ofa.field_value_text = :identifier AND ";
                }else{
                    $whereQuery .= "`of`.created_by = :userId AND ";
                    $queryParams['userId'] = $userId;
                }
            } else {
                $whereQuery = "";
            }
        //TODO INCLUDING WORKFLOW INSTANCE SHOULD BE REMOVED. THIS SHOULD BE PURELY ON FILE TABLE
            $fromQuery .= "left join ox_workflow_instance as wi on (`of`.last_workflow_instance_id = wi.id) $workflowJoin";
            if (isset($params['workflowStatus'])) {
                $whereQuery .= " wi.status = '" . $params['workflowStatus'] . "'  AND ";
            } else {
                $whereQuery .= "";
            }
            $sort = "";
            $field = "";
            $pageSize = " LIMIT 10";
            $offset = " OFFSET 0";
            $this->processFilterParams($fromQuery,$whereQuery,$sort,$pageSize,$offset,$field,$filterParams);
            $whereQuery = rtrim($whereQuery, " AND ");
            if($whereQuery==" WHERE "){
                $where = "";
            } else {
                $where .= " " . $whereQuery ;
            }
            $where = trim($where) != "" ? "WHERE $where" : "";
            $where = rtrim($where, " AND ");
            $where = $where . " AND of.is_active = 1";
            try {
                $select = "SELECT DISTINCT SQL_CALC_FOUND_ROWS of.data, of.uuid, wi.status, wi.process_instance_id as workflowInstanceId,of.date_created,en.name as entity_name,en.uuid as entity_id $field $fromQuery $where $sort $pageSize $offset";
                $this->logger->info("Executing query - $select with params - " . json_encode($queryParams));
                $resultSet = $this->executeQueryWithBindParameters($select, $queryParams)->toArray();
                $countQuery = "SELECT FOUND_ROWS();";
                $this->logger->info("Executing query - $countQuery with params - " . json_encode($queryParams));
                $countResultSet = $this->executeQueryWithBindParameters($countQuery, $queryParams)->toArray();
                if (isset($filterParams['columns'])) {
                    $filterParams['columns'] = json_decode($filterParams['columns'],true);
                }
                if ($resultSet) {
                    $i = 0;
                    foreach ($resultSet as $file) {
                        if ($file['data']) {
                            $content = json_decode($file['data'], true);
                            if ($content) {
                                if (isset($filterParams['columns'])) {
                                    foreach ($filterParams['columns'] as $column){
                                        isset($content[$column]) ? $file[$column] = $content[$column] : null;
                                    }
                                    if(isset($file["data"])){
                                        unset($file["data"]);
                                    }
                                    $resultSet[$i] = ($file);
                                } else{
                                    $resultSet[$i] = array_merge($file, $content);
                                }
                            }
                        }
                        $i++;
                    }
                }
                return array('data' => $resultSet, 'total' => $countResultSet[0]['FOUND_ROWS()']);
            } catch (Exception $e) {
                throw new ServiceException($e->getMessage(), "app.mysql.error");
            }
        } else {
            throw new ServiceException("App Does not belong to the org", "app.fororgnot.found");
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
                if(!empty($result['field_value'])){
                    $jsonValue =  json_decode($result['field_value'], true);
                    if(!isset($documentsArray[$result['text']])){
                        $documentsArray[$result['text']] =  $jsonValue;
                    }
                    else{
                        $documentsArray[$result['text']] = array_merge($documentsArray[$result['text']],$jsonValue);
                    }
                }
            }
            foreach ($documentsArray as $key=>$docItem) {
                if(isset($docItem) && !isset($docItem[0]['file']) ){
                     $parseDocData = array();
                    foreach ($docItem as $document) {
                        if(is_array($document) && isset($document[0])){
                            foreach ($document as $doc) {
                                $this->parseDocumentData($parseDocData,$doc);
                            }
                        } else{
                            $this->parseDocumentData($parseDocData,$document);
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

    private function parseDocumentData(&$parseArray,$documentItem)
    {
        if(empty($documentItem)){
            return;
        }
        if(is_string($documentItem)){
            $fileType = explode(".", $documentItem);
            $fileName = explode("/", $documentItem);
            if(isset($fileType[1])){
                array_push($parseArray,
                    array('file' => $documentItem,
                      'type'=> 'file/' . $fileType[1],
                      'originalName'=> end($fileName)
                  ));
            }
        } else{
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

    private function cleanData($params)
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
        unset($params['last_workflow_instance_id']);
        unset($params['inDraft']);
        unset($params['entity_name']);
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

    public function getWorkflowInstanceByFileId($fileId,$status=null){
        $select = " SELECT ox_workflow_instance.process_instance_id,ox_workflow_instance.status,ox_workflow_instance.date_created,ox_file.entity_id from ox_workflow_instance INNER JOIN ox_file on ox_file.id = ox_workflow_instance.file_id WHERE ox_file.uuid =:fileId";
        $params = array('fileId' => $fileId);
        if($status){
            $select .= " AND ox_workflow_instance.status =:status";
            $params['status'] = $status;
        }
        $select .= " ORDER BY ox_workflow_instance.date_created DESC";
        $result = $this->executeQuerywithBindParameters($select,$params)->toArray();
        return $result;
    }

    public function getChangeLog($entityId,$startData,$completionData,$labelMapping){
        $fieldSelect = "SELECT ox_field.name,ox_field.template,ox_field.type,ox_field.text,ox_field.data_type,COALESCE(parent.name,'') as parentName,COALESCE(parent.text,'') as parentText,parent.data_type as parentDataType FROM ox_field
                    left join ox_field as parent on ox_field.parent_id = parent.id WHERE ox_field.entity_id=:entityId AND ox_field.type NOT IN ('hidden','file','document','documentviewer') ORDER BY parentName, ox_field.name ASC";

        $fieldParams = array('entityId' => $entityId);
        $resultSet = $this->executeQueryWithBindParameters($fieldSelect,$fieldParams)->toArray();

        $resultData = array();
        $gridResult = array();
        foreach ($resultSet as $key => $value) {
            if($value['data_type'] == 'json'){
                continue;
            }
            $initialparentData = null;
            $submissionparentData = null;
            if($value['parentName'] !="") {
                if(isset($gridResult[$value['parentName']])){
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

            } else{
                $this->buildChangeLog($startData, $completionData, $value, $labelMapping, $resultData);
            }
        }
        if(count($gridResult) > 0){
            foreach($gridResult as $parentName => $data){
                $initialDataset = $data['initial'];
                $submissionDataset = $data['submission'];
                if(is_array($initialDataset) && is_array($submissionDataset)){
                    $count = max(count($initialDataset), count($submissionDataset));
                    for($i = 0; $i < $count; $i++) {
                        $initialRowData = isset($initialDataset[$i]) ? $initialDataset[$i] : array();
                        $submissionRowData = isset($submissionDataset[$i]) ? $submissionDataset[$i] : array();
                        foreach($data['fields'] as $key => $field) {
                            $this->buildChangeLog($initialRowData, $submissionRowData, $field, $labelMapping, $resultData,$i+1);
                        }
                    }
                }
            }
         }
        return $resultData;
    }

    public function getFieldValue($startDataTemp,$value,$labelMapping=null){
        if(!isset($startDataTemp[$value['name']])){
            return "";
        }
        $initialData = $startDataTemp[$value['name']];
        if($value['data_type'] == 'text'){
            //handle string data being sent
            if(is_string($initialData)){
                $fieldValue = json_decode($initialData, true);
            } else {
                $fieldValue = $initialData;
            }
            //handle select component values having an object with keys value and label
            if(!empty($fieldValue) && is_array($fieldValue)){
                //Add Handler for default Labels
                if(isset($fieldValue['label'])){
                    $initialData = $fieldValue['label'];
                } else {
                    // Add for single values array
                    if(isset($fieldValue[0]) && count($fieldValue) == 1){
                        $initialData = $fieldValue[0];
                    } else {
                        //Case multiple values allowed
                        if(count($fieldValue) > 1){
                            foreach ($fieldValue as $k => $v) {
                                $initialData .= $v;
                            }
                        }
                    }
                }
            }

        }else if($value['data_type'] == 'boolean'){
            if((is_bool($initialData) && $initialData == false) || (is_string($initialData) && ($initialData=="false" || $initialData=="0"))){
                $initialData = "No";
            } else {
                $initialData = "Yes";
            }
        }else if($value['data_type'] =='list'){
            $radioFields =json_decode($value['template'],true);
            if(is_string($initialData)){
                $selectValues = json_decode($initialData,true);
            } else {
                if(is_array($initialData)){
                    $selectValues = $initialData;
                }
            }
            $initialData = "";
            $processed =0;
            if(isset($selectValues) && is_string($selectValues)){
                $selectValues = json_decode($selectValues,true);
            }
            if(isset($selectValues) && is_array($selectValues)){
                foreach ($selectValues as $key => $value) {
                    if($value == 1){
                        if($processed == 0){
                         $radioFields = ArrayUtils::convertListToMap($radioFields['values'],'value','label');
                         $processed = 1;
                     }
                     if(isset($radioFields[$key])){
                        if($initialData !=""){
                            $initialData = $initialData . ",";
                        }
                        $initialData .= $radioFields[$key];
                    }
                }
            }
            }
        }

        if($value['type'] =='radio'){
            $radioFields =json_decode($value['template'],true);
            if(isset($radioFields['values'])){
                foreach ($radioFields['values'] as $key => $radiovalues) {
                    if($initialData == $radiovalues['value']){
                        $initialData = $radiovalues['label'];
                        break;
                    }
                }
            }
        }
        if($labelMapping && !empty($initialData) && isset($labelMapping[$initialData])){
            $initialData = $labelMapping[$initialData];
        }
        return $initialData;
    }

    private function buildChangeLog($startData, $completionData, $value, $labelMapping, &$resultData,$rowNumber=""){
        $initialData =  $this->getFieldValue($startData,$value,$labelMapping);
        $submissionData = $this->getFieldValue($completionData,$value,$labelMapping);
        if((isset($initialData) && ($initialData != '[]') && (!empty($initialData))) ||
                (isset($submissionData) && ($submissionData != '[]') && (!empty($submissionData)))){
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
    public function addAttachment($params,$file)
    {
        $fileArray = array();
        $data = array();
        $fileStorage = AuthContext::get(AuthConstants::ORG_UUID) . "/temp/";
        $data['org_id'] = AuthContext::get(AuthConstants::ORG_ID);
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
        if (!isset($params['fileId'])) {
            $folderPath = $this->config['APP_DOCUMENT_FOLDER'].$fileStorage.$data['uuid']."/";
            $path = realpath($folderPath . $data['name']) ? realpath($folderPath.$data['name']) : FileUtils::truepath($folderPath.$data['name']);
            $data['path'] = $path;
            $data['url'] = $this->config['baseUrl']."/data/".$fileStorage.$data['uuid']."/".$data['name'];
        }else{
            $folderPath = $this->config['APP_DOCUMENT_FOLDER'].AuthContext::get(AuthConstants::ORG_UUID) . '/' . $params['fileId'] . '/';
            $data['file'] = AuthContext::get(AuthConstants::ORG_UUID) . '/' . $params['fileId'] . '/'.$file['name'];
            $data['url'] = $this->config['baseUrl']."/".AuthContext::get(AuthConstants::ORG_UUID) . '/' . $params['fileId'] . '/'.$file['name'];
            $data['path'] = FileUtils::truepath($folderPath.'/'.$file['name']);
        }
        $form->exchangeArray($data);
        $form->validate();
        $count = $this->attachmentTable->save($form);
        $id = $this->attachmentTable->getLastInsertValue();
        $data['id'] = $id;
        $file['name'] = $data['name'];
        $fileStored = FileUtils::storeFile($file, $folderPath);
        $data['size'] = filesize($data['path']);
        if (isset($params['fileId'])) {
            $filterArray['text'] = $params['fieldLabel'];
            $filter['uuid'] = $params['fileId'];
            $fileRecord = $this->getDataByParams('ox_file', array("entity_id","data"), $filter, null)->toArray();
            $fileArray['entity_id'] = $fileRecord[0]['entity_id'];
            $fieldName = $this->getDataByParams('ox_field', array("name"), $filterArray, null)->toArray();
            if (count($fileRecord) > 0) {
               $fileData = json_decode($fileRecord[0]['data'],true);
               $this->processFileDataList($fileData,$fieldName[0]['name'],$data);
           	   $this->updateFile($fileData,$params['fileId']);
            }
        }
        return $data;
    }
    public function appendAttachmentToFile($fileAttachment,$field,$fileId,$orgId = null){
        if(!isset($fileAttachment['file'])) {
            $orgId = isset($orgId) ? $orgId : AuthContext::get(AuthConstants::ORG_UUID);
            $fileUuid = $this->getUuidFromId('ox_file', $fileId);
            $fileLocation = $fileAttachment['path'];
            $targetLocation = $this->config['APP_DOCUMENT_FOLDER']. $orgId . '/' . $fileUuid . '/';
            $this->logger->info("Data CreateFile- " . json_encode($fileLocation));
            $tempname = str_replace(".".$fileAttachment['extension'], "", $fileAttachment['name']);
            $fileAttachment['name'] = $tempname."-".$fileAttachment['uuid'].".".$fileAttachment['extension'];
            $fileAttachment['originalName'] = $tempname.".".$fileAttachment['extension'];
            $this->logger->info("attachment- " . json_encode($fileAttachment));
            if(file_exists($fileLocation)){
                FileUtils::copy($fileLocation,$fileAttachment['name'],$targetLocation);
                // FileUtils::deleteFile($fileAttachment['originalName'],dirname($fileLocation)."/");
                $fileAttachment['file'] = $orgId . '/' . $fileUuid . '/'.$fileAttachment['name'];
                $fileAttachment['url'] = $this->config['baseUrl']."/". $orgId . '/' . $fileUuid . '/'.$fileAttachment['name'];
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
                $sort .= " of.date_created ".$dir;
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

    public function getFileFilters(&$params, &$where, &$queryParams){
        if (isset($params['entityName'])) {
            if(is_array($params['entityName'])){
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
            if ($queryParams['assocId'] = $this->getIdFromUuid('ox_file', $params['assocId']))
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
    }

    public function getEntityFilter(&$params, &$entityFilter, &$queryParams){
        if (isset($params['entityName'])) {
            if(is_array($params['entityName'])){
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
        }
    }

    public function updateFieldValueOnFiles($appUUid,$data,$fieldName,$initialFieldValue,$newFieldValue,$filterParams){

        $whereQuery = " ";
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
        $this->getEntityFilter($data,$entityFilter,$queryParams);
        $this->processFilterParams($fromQuery,$whereQuery,$sort,$pageSize,$offset,$field,$filterParams);
        $this->beginTransaction();
        try {
            $updateFile = 'UPDATE ox_file as of '.$fromQuery.'SET data = REPLACE(data,'."'".'"'.$fieldName.'":"'.$initialFieldValue.'"'."','".'"'.$fieldName.'":"'.$newFieldValue.'"'."'".') WHERE '.$entityFilter.' '.$whereQuery;
            $queryParams['appId'] = $appId;
            $this->logger->info("Update File Attribute Query -- $updateFile with params - ".print_r($queryParams,true));
            $resultSet = $this->executeUpdateWithBindParameters($updateFile,$queryParams);

            unset($queryParams['entityName']);

            $fromClause = "";
            $whereClause = " WHERE oxf.app_id = :appId AND oxf.name = :fieldName ";
            if(isset($data['entityName'])){
                $fromClause .= " inner join ox_app_entity as oxe on oxe.id = oxf.entity_id ";
                $whereClause .= " AND oxe.name = :entityName ";
                $queryParams['entityName'] = $data['entityName'];
            }
            $queryParams['fieldName'] = $fieldName;
            $selectField = "SELECT oxf.* from ox_field as oxf $fromClause $whereClause";
            $this->logger->info("GET FIELD DATA  -- $selectField with params - ".print_r($queryParams,true));
            $resultSet = $this->executeQueryWithBindParameters($selectField,$queryParams)->toArray();

            foreach ($resultSet as $value) {
                $this->updateFileAttribute($appId,$fieldName,$newFieldValue,$value['data_type'],$fromQuery,$whereQuery,$value['entity_id'],'ox_file_attribute');
                if($value['index'] == 1) {
                    $this->updateFileAttribute($appId,$fieldName,$newFieldValue,$value['data_type'],$fromQuery,$whereQuery,$value['entity_id'],'ox_indexed_file_attribute');
                }
            }
            $this->commit();
        }catch(Exception $e){
            $this->logger->error($e->getMessage(),$e);
            $this->rollback();
            throw $e;
        }
        return 1;
    }

    private function updateFileAttribute($appId,$fieldName,$fieldValue,$dataType,$fromQuery,$whereQuery,$entityId,$tableName){
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
        if($tableName == 'ox_file_attribute'){
            $setQuery = " SET ofa.field_value = :fieldValue ";
        }
        switch($dataType){
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
        $this->logger->info("Update File Attribute Field Value Query -- $updateField with params - ".print_r($queryParams,true));
        $resultSet = $this->executeUpdateWithBindParameters($updateField,$queryParams);
    }

    public function processFilterParams(&$fromQuery,&$whereQuery,&$sort,&$pageSize,&$offset,&$field,$filterParams){
        $prefix = 1;
        if (!empty($filterParams)) {
                if (isset($filterParams['filter']) && !is_array($filterParams['filter'])) {
                    $jsonParams = json_decode($filterParams['filter'], true);
                    if (isset($filterParamsArray['filter'])) {   // This is not correct. Please check
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

                $filterlogic = isset($filterParamsArray[0]['filter']['logic']) ? $filterParamsArray[0]['filter']['logic'] : " AND ";
                $cnt = 1;
                $fieldParams = array();
                $tableFilters = "";
                if (isset($filterParamsArray[0]['filter'])) {
                    $filterData = $filterParamsArray[0]['filter']['filters'];
                    if($filterlogic == 'or'){
                        $subQuery = "";
                        $fieldNamesArray = array();
                        foreach ($filterData as $val) {
                            if (!empty($val)) {
                                if(isset($val['filter'])){
                                    if(isset($val['filter']['logic'])){
                                        $subFilterLogic = $val['filter']['logic'];
                                    } else {
                                        $subFilterLogic = " OR ";
                                    }
                                    if(isset($val['filter']['filters'])){
                                        $subQuery = "";
                                        foreach ($val['filter']['filters'] as $subFilter) {
                                            $filterOperator = $this->processFilters($subFilter);
                                            $queryString = $filterOperator["operation"] . "'" . $filterOperator["operator1"] . "" . $subFilter['value'] . "" . $filterOperator["operator2"] . "'";
                                            $fieldNamesArray[] = '"'.$subFilter['field'].'"';

                                            $subQuery .= " (CASE WHEN (fileAttributes.field_value_type='TEXT') THEN fileAttributes.field_value_text $queryString ";

                                            if (date('Y-m-d', strtotime($subFilter['value'])) === $subFilter['value']) {
                                                $subQuery .= "  WHEN (fileAttributes.field_value_type='DATE') THEN fileAttributes.field_value_date $queryString ";
                                            }
                                            if(is_numeric($subFilter['value'])){
                                                $subQuery .= " WHEN (fileAttributes.field_value_type='NUMERIC') THEN fileAttributes.field_value_numeric $queryString ";
                                            }
                                            if(is_bool($subFilter['value'])){
                                                $subQuery .= " WHEN (fileAttributes.field_value_type='BOOLEAN') THEN fileAttributes.field_value_boolean $queryString  ";
                                            }

                                            $subQuery .= " END ) $subFilterLogic ";
                                        }
                                        $subQuery = rtrim($subQuery, $subFilterLogic." ");
                                        $whereQuery .= " ( ".$subQuery." ) $filterlogic ";
                                    }
                                } else {
                                    $filterOperator = $this->processFilters($val);
                                    $queryString = $filterOperator["operation"] . "'" . $filterOperator["operator1"] . "" . $val['value'] . "" . $filterOperator["operator2"] . "'";
                                    $fieldNamesArray[] = '"'.$val['field'].'"';
                                    $whereQuery .= " (CASE WHEN (fileAttributes.field_value_type='TEXT' AND fieldsTable.name = '".$val['field']."' ) THEN fileAttributes.field_value_text $queryString ";
                                    if (date('Y-m-d', strtotime($val['value'])) === $val['value']) {
                                        $whereQuery .= " WHEN (fileAttributes.field_value_type='DATE' AND fieldsTable.name = '".$val['field']."' ) THEN fileAttributes.field_value_date $queryString ";
                                    }
                                    if(is_numeric($val['value'])){
                                        $whereQuery .= " WHEN (fileAttributes.field_value_type='NUMERIC' AND fieldsTable.name = '".$val['field']."' ) THEN fileAttributes.field_value_numeric $queryString ";
                                    }
                                    if(is_bool($val['value'])){
                                        $whereQuery .= " WHEN (fileAttributes.field_value_type='BOOLEAN' AND fieldsTable.name = '".$val['field']."' ) THEN fileAttributes.field_value_boolean $queryString ";
                                    }
                                    $whereQuery .= " END ) $filterlogic ";
                                }
                            }
                        }
                        $fromQuery .= "inner join ox_indexed_file_attribute as fileAttributes on (`of`.id =fileAttributes.file_id) inner join ox_field as fieldsTable on(fieldsTable.entity_id = `of`.entity_id and fieldsTable.id=fileAttributes.field_id and fieldsTable.name in (".implode(',',$fieldNamesArray)."))";
                        $whereQuery = rtrim($whereQuery, $filterlogic." ");
                    } else {
                        foreach ($filterData as $val) {
                            $tablePrefix = "tblf" . $prefix;
                            if (!empty($val)) {
                                if(isset($val['filter'])){
                                    if(isset($val['filter']['logic'])){
                                        $subFilterLogic = $val['filter']['logic'];
                                    } else {
                                        $subFilterLogic = " OR ";
                                    }
                                    if(isset($val['filter']['filters'])){
                                        $subQuery = "";
                                        $subFromQuery = "";
                                        foreach ($val['filter']['filters'] as $subFilter) {
                                            $filterOperator = $this->processFilters($subFilter);
                                            $subTablePrefix = $tablePrefix.$subFilter['field'];
                                            $queryString = $filterOperator["operation"] . "'" . $filterOperator["operator1"] . "" . $subFilter['value'] . "" . $filterOperator["operator2"] . "'";
                                            if($subFilterLogic=='or'){
                                                $fieldNamesArray[] = '"'.$subFilter['field'].'"';
                                                $subQuery .= " (CASE WHEN (fileAttributes.field_value_type='TEXT') THEN fileAttributes.field_value_text $queryString ";
                                                if (date('Y-m-d', strtotime($subFilter['value'])) === $subFilter['value']) {
                                                    $subQuery .= "  WHEN (fileAttributes.field_value_type='DATE') THEN fileAttributes.field_value_date $queryString ";
                                                }
                                                if(is_numeric($subFilter['value'])){
                                                    $subQuery .= " WHEN (fileAttributes.field_value_type='NUMERIC') THEN fileAttributes.field_value_numeric $queryString ";
                                                }
                                                if(is_bool($subFilter['value'])){
                                                    $subQuery .= " WHEN (fileAttributes.field_value_type='BOOLEAN') THEN fileAttributes.field_value_boolean $queryString  ";
                                                }
                                                $subQuery .= " END ) $subFilterLogic ";

                                                $subFromQuery = "inner join ox_indexed_file_attribute as fileAttributes on (`of`.id =fileAttributes.file_id) inner join ox_field as fieldsTable on(fieldsTable.entity_id = `of`.entity_id and fieldsTable.id=fileAttributes.field_id and fieldsTable.name in (".implode(',',$fieldNamesArray)."))";
                                            } else {
                                                $subFromQuery .= " inner join ox_indexed_file_attribute as ".$subTablePrefix." on (`of`.id =" . $subTablePrefix . ".file_id) inner join ox_field as ".$subFilter['field'].$subTablePrefix." on(".$subFilter['field'].$subTablePrefix.".id = ".$subTablePrefix.".field_id and ". $subFilter['field'].$subTablePrefix.".name='".$subFilter['field']."')";
                                                $subQuery .= " (CASE WHEN (" .$subTablePrefix . ".field_value_type='TEXT') THEN " . $subTablePrefix . ".field_value_text $queryString ";

                                                if (date('Y-m-d', strtotime($subFilter['value'])) === $subFilter['value']) {
                                                    $subQuery .= "  WHEN (" .$subTablePrefix . ".field_value_type='DATE') THEN " . $subTablePrefix . ".field_value_date $queryString ";
                                                }
                                                if(is_numeric($subFilter['value'])){
                                                    $subQuery .= "  WHEN (" .$subTablePrefix . ".field_value_type='NUMERIC') THEN " . $subTablePrefix . ".field_value_numeric $queryString ";
                                                }
                                                if(is_bool($subFilter['value'])){
                                                    $subQuery .= " WHEN (" .$subTablePrefix . ".field_value_type='BOOLEAN') THEN " . $subTablePrefix . ".field_value_boolean $queryString  ";
                                                }

                                                $subQuery .= " END ) $subFilterLogic ";
                                            }
                                        }
                                        $fromQuery .= $subFromQuery;
                                        $subQuery = rtrim($subQuery, $subFilterLogic." ");
                                        $whereQuery .= " ( ".$subQuery." ) $filterlogic ";
                                    }
                                } else {
                                    $fromQuery .= " inner join ox_indexed_file_attribute as ".$tablePrefix." on (`of`.id =" . $tablePrefix . ".file_id) inner join ox_field as ".$val['field'].$tablePrefix." on(".$val['field'].$tablePrefix.".id = ".$tablePrefix.".field_id and ". $val['field'].$tablePrefix.".name='".$val['field']."')";
                                    $filterOperator = $this->processFilters($val);
                                    $queryString = $filterOperator["operation"] . "'" . $filterOperator["operator1"] . "" . $val['value'] . "" . $filterOperator["operator2"] . "'";
                                    $whereQuery .= " (CASE  WHEN (" .$tablePrefix . ".field_value_type='TEXT') THEN " . $tablePrefix . ".field_value_text $queryString ";

                                    if (date('Y-m-d', strtotime($val['value'])) === $val['value']) {
                                        $whereQuery .= " WHEN (" .$tablePrefix . ".field_value_type='DATE') THEN " . $tablePrefix . ".field_value_date $queryString ";
                                    }
                                    if(is_numeric($val['value'])){
                                        $whereQuery .= " WHEN (" .$tablePrefix . ".field_value_type='NUMERIC') THEN " . $tablePrefix . ".field_value_numeric $queryString ";
                                    }
                                    if(is_bool($val['value'])){
                                        $whereQuery .= "  WHEN (" .$tablePrefix . ".field_value_type='BOOLEAN') THEN " . $tablePrefix . ".field_value_boolean $queryString  ";
                                    }
                                    $whereQuery .= " END ) $filterlogic ";
                                }
                                $prefix += 1;
                            }
                        }
                        $whereQuery = rtrim($whereQuery, $filterlogic." ");
                    }
                }
                if (isset($filterParamsArray[0]['sort']) && !empty($filterParamsArray[0]['sort'])) {
                    $sort = $this->buildSortQuery($filterParamsArray[0]['sort'], $field);
                }
                $pageSize = " LIMIT " . (isset($filterParamsArray[0]['take']) ? $filterParamsArray[0]['take'] : 10);
                $offset = " OFFSET " . (isset($filterParamsArray[0]['skip']) ? $filterParamsArray[0]['skip'] : 0);
        }
    }

    private function processFileDataList(&$fileData,$searchKey,$data){
        $return = false;
        if (isset($fileData[$searchKey])) {
            if(!is_array($fileData[$searchKey])) {
                $fileData[$searchKey] = json_decode($fileData[$searchKey], true);
            }
            array_push($fileData[$searchKey], $data);
            $return = true;
        }else{
             foreach ($fileData as $key => $value) {
                if (is_string($value)) {
                    $value = json_decode($value,true);
                }
                if (is_array($value)) {
                   $return = $this->processFileDataList($value,$searchKey,$data);
                }
                if ($return) {
                    $fileData[$key] = $value;
                    break;
                }
            }
        }
        return $return;
    }
    public function reIndexFile($params){
        $whereQuery = "";
        $queryParams = array();
        if(isset($params['entity_id'])){
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
        if(isset($entityId)){
            $whereQuery = "where f.entity_id=:entityId";
            $queryParams['entityId'] = $entityId;
        }
        // print_r($whereQuery);
        $select = "SELECT f.*  from ox_file f $whereQuery";
        $files = $this->executeQuerywithBindParameters($select,$queryParams)->toArray();
        foreach ($files as $k => $file) {
            $this->updateFileUserContext($file);
            $fileData = json_decode($file['data'],true);
            $this->updateFileAttributesInternal($entityId, $fileData, $file['id']);
            unset($files[$k]['data']);
            unset($files[$k]['id']);
        }
        return $files;
    }

    public function getWorkflowInstanceStartDataFromFileId($fileId){
        $select = "SELECT start_data from ox_workflow_instance oxwi inner join ox_file on ox_file.last_workflow_instance_id = oxwi.id where ox_file.uuid=:fileId";
        $params = array("fileId" => $fileId);
        $result = $this->executeQuerywithBindParameters($select,$params)->toArray();
        if (count($result) == 0) {
            return 0;
        }
        return $result[0];

    }

}
