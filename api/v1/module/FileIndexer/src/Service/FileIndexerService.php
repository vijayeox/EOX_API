<?php
namespace FileIndexer\Service;

use Oxzion\Service\AbstractService;
use Zend\Db\Adapter\AdapterInterface;
use Oxzion\Messaging\MessageProducer;
use Oxzion\ServiceException;
use Zend\Log\Logger;
use Oxzion\Utils\ArrayUtils;
use Exception;

class FileIndexerService extends AbstractService
{
    protected $restClient;
    protected $messageProducer;

    public function __construct($config, AdapterInterface $dbAdapter, MessageProducer $messageProducer)
    {
        parent::__construct($config, $dbAdapter);
        $this->messageProducer = $messageProducer;
    }
    public function setRestClient($restClient)
    {
        $this->restClient = $restClient;
    }

    public function setMessageProducer($messageProducer)
    {
        $this->messageProducer = $messageProducer;
    }

    public function getRelevantDetails($fileId,$searchIndex = false)
    {
        if (isset($fileId)) {
            $where = "";
            if($searchIndex){
                $where = " AND field.search_index = 1 ";
            }
            $select = "SELECT file.id as id,app.name as app_name, entity.id as entity_id, entity.name as entityName,
            file.data as file_data, file.uuid as file_uuid, file.version , file.is_active, file.account_id,
            CONCAT('{', GROUP_CONCAT(CONCAT('\"', field.name, '\" : \"',COALESCE(field.text, field.name),'\"') SEPARATOR ','), '}') as fields,
            CONCAT('[',GROUP_CONCAT(DISTINCT ofp.account_id SEPARATOR ','),']') as participants,
            CONCAT('{' , '\"', 'form', '\" :\"' , GROUP_CONCAT(DISTINCT(form.uuid)), '\"}') as form_data
            from ox_file as file
            left join ox_file_participant ofp on ofp.file_id = file.id
            INNER JOIN ox_app_entity as entity ON file.entity_id = entity.id
            INNER JOIN ox_app as app on entity.app_id = app.id
            INNER JOIN ox_field as field ON field.entity_id = entity.id
            INNER JOIN ox_form AS form ON form.entity_id = file.entity_id 
            where file.id = ".$fileId.$where." GROUP BY file.id,app_name,entity.id, entity.name,file_data,file_uuid,file.is_active, file.account_id";

       
            $this->runGenericQuery("SET SESSION group_concat_max_len = 1000000;");
            $this->logger->info("Executing Query - $select");
            $body=$this->executeQuerywithParams($select)->toArray();
            if (isset($body[0])) {
                $databody = $this->flattenAndModify($body[0]);
            }
            if (isset($databody['app_name'])) {
                $app_name = $databody['app_name'];
            }
            if (isset($app_name)&&isset($databody) && count($databody) > 0) {
                $this->messageProducer->sendQueue(json_encode(array('index'=>  $app_name.'_index','body' => $databody,'id' => $fileId, 'operation' => 'Index', 'type' => '_doc')), 'elastic');
                return $databody;
            }
        }
        return null;
    }

    public function processBatchIndex($data)
    {
        try {
            $this->messageProducer->sendTopic(json_encode($data), 'PROCESS_BATCH_INDEX');
        } catch (Exception $e) {
            $this->logger->error('Exception occured in async batch index :');
            $this->logger->error($e);
            throw $e;
        }
    }

    public function indexFile($fileUuid,$searchIndex = false)
    {
        //Get all file data and relevant parameters
        $where = "";
        if($searchIndex){
            $where = " AND field.search_index = 1";
        }
        $select = "SELECT file.id as id,app.name as app_name, entity.id as entity_id, entity.name as entityName,
        file.data as file_data, file.uuid as file_uuid, file.is_active, file.version, file.account_id,file.date_created,file.date_modified,
        CONCAT('{', GROUP_CONCAT(CONCAT('\"', field.name, '\" : \"',COALESCE(field.text, field.name),'\"') SEPARATOR ','), '}') as fields,
        CONCAT('[',GROUP_CONCAT(DISTINCT ofp.account_id SEPARATOR ','),']') as participants,
        CONCAT('{' , '\"', 'form', '\" :\"' , GROUP_CONCAT(DISTINCT(form.uuid)), '\"}') as form_data
        from ox_file as file
        left join ox_file_participant ofp on ofp.file_id = file.id
        INNER JOIN ox_app_entity as entity ON file.entity_id = entity.id
        INNER JOIN ox_app as app on entity.app_id = app.id
        INNER JOIN ox_field as field ON field.entity_id = entity.id
        INNER JOIN ox_form AS form ON form.entity_id = file.entity_id 
        where file.uuid = :uuid".$where;
        $this->runGenericQuery("SET SESSION group_concat_max_len = 1000000;");
        $params = array('uuid' => $fileUuid);
        $result = $this->executeQuerywithBindParameters($select, $params)->toArray();

        //Need to store file data seperately as its a json string and perform actions on the same
        $data = $indexedData = null;

        if (isset($result[0]) && isset($result[0]['id'])) {
            $app_name = $result[0]['app_name'];
            if ($result[0]['fields'] != "") {
                $indexedData = $this->getAllFieldsWithCorrespondingValues($result[0],$searchIndex);
                $this->logger->info("\nINDEXED DATA :".print_r($indexedData, true));
                //Sending it to the elastic queue
                $this->messageProducer->sendQueue(json_encode(array('index'=>  $app_name.'_index','body' => $indexedData,'id' => $indexedData['id'], 'operation' => 'Index', 'type' => '_doc')), 'elastic');
                return $indexedData;
            }else{
                throw new ServiceException("Incorrect file uuid specified", "file.uuid.incorrect");
            }
        } else {
            // Handle empty file data in case of some error
            throw new ServiceException("Incorrect file uuid specified", "file.uuid.incorrect");
        }
    }

    public function deleteDocument($fileUUId)
    {
        $this->logger->info("In FileIndexer Delete. Id:".$fileUUId);

        $select = "SELECT file.id as id,app.name as name
        from ox_file as file
        INNER JOIN ox_app_entity as entity ON file.entity_id = entity.id
        INNER JOIN ox_app as app on entity.app_id = app.id
        where file.uuid = '".$fileUUId."'";
        $params = array('uuid' => $fileUUId);
        $response = $this->executeQuerywithBindParameters($select, $params)->toArray();
        if (count($response) == 0) {
            return 0;
        }
        $app_name = $response[0]['name'];
        if (isset($app_name)) {
            $fileId = $response[0]['id'];
            $this->logger->info("Sennding Elastic Delete Message for fileid:".$fileId);
            $this->messageProducer->sendQueue(json_encode(array('index'=>  $app_name.'_index','id' => $fileId, 'operation' => 'Delete', 'type' => '_doc')), 'elastic');
            return array('fileId' => $fileId);
        }
        return null;
    }

    public function batchIndexer($appUuid, $startdate = null, $enddate = null, array $batchSizeMap,$searchIndex = false)
    {
        $batchSize = $this->config['batch_size'];

        if (!isset($appUuid)) {
            throw new Exception("Incorrect App Id Specified", 1);
        }

        $appID = $this->getIdFromUuid('ox_app', $appUuid);
        $select = "SELECT ofi.id from ox_file ofi
                inner join ox_app_entity oae on oae.id = ofi.entity_id
                inner join ox_app oa on oa.id = oae.app_id ";
        if (isset($startdate) && !isset($enddate)) {
            $where ="WHERE ofi.date_created > '$startdate'";
        } elseif (isset($startdate) && isset($enddate)) {
            $where ="WHERE ofi.date_created > '$startdate' && ofi.date_created < '$enddate'";
        } elseif (!isset($startdate) && isset($enddate)) {
            $where ="WHERE ofi.date_created < '$enddate'";
        } else {
            return 0;
        }
        $query = $select.$where." AND oa.uuid ='".$appUuid."'";

        try {
            $resultSet = $this->executeQuerywithParams($query)->toArray();
            $idlist = $batches = $fileIdsArray =array();
            $total = count($resultSet);
            if ($total > 0) {
                $idlist = array_column($resultSet, 'id');
                $batches = array_chunk($idlist, $batchSize);
                foreach ($batches as $batch) {
                    $fileIdsArray = $batch;
                    $fileIds = implode(',', $batch);
                    $bodys = $this->getFileDataFromFileIds($appID,$fileIds,$searchIndex);
                    $arraySize = mb_strlen(serialize((array)$bodys), '8bit');
                    if($arraySize > 6500000) {
                        $differentialFactor = $arraySize / 6500000;
                        $newBatchSize = ceil($batchSize / $differentialFactor);
                        $this->logger->info("the new batch size is ---".$newBatchSize);
                        $newBatches = array_chunk($batch,$newBatchSize);
                        foreach ($newBatches as $newBatch) {
                            $fileIds = implode(',',$newBatch);
                            $bodys = $this->getFileDataFromFileIds($appID,$fileIds,$searchIndex);
                            $arraySize = mb_strlen(serialize((array)$bodys), '8bit');
                            $this->logger->info("The new array size is --$arraySize");
                            $this->sendDataToElasticForBulk($fileIds,$bodys,$appID);
                        }
                        continue;
                    }
                    $this->sendDataToElasticForBulk($fileIds,$bodys,$appID);
                }
                return $bodys;
            }
        } catch (ZendDbException $e) {
            $this->logger->error('Database exception occurred.');
            $this->logger->error($e);
            $this->logger->error('Query and params:');
            $this->logger->error($query);
            $this->logger->error($queryParams);
        } catch (Exception $e) {
            throw $e;
        }
    }

    private function getFileDataFromFileIds($appID,$fileIds,$searchIndex) {
        $where = "";
        if($searchIndex){
            $where = " AND field.search_index = 1 ";
        }
        $select = "SELECT file.id as id,app.name as app_name, entity.id as entity_id, file.version , entity.name as entity_name,file.data as file_data, file.uuid as file_uuid, file.is_active,file.account_id,CONCAT('{', GROUP_CONCAT(CONCAT('\"', field.name, '\" : \"',COALESCE(field.text, field.name),'\"') SEPARATOR ','), '}') as fields,CONCAT('[',GROUP_CONCAT(DISTINCT ofp.account_id SEPARATOR ','),']') as participants,
        CONCAT('{' , '\"', 'form', '\" :\"' , GROUP_CONCAT(DISTINCT(form.uuid)), '\"}') as form_data
        from ox_file as file
        left join ox_file_participant ofp on ofp.file_id = file.id
        INNER JOIN ox_app_entity as entity ON file.entity_id = entity.id
        INNER JOIN ox_app as app on entity.app_id = app.id
        INNER JOIN ox_field as field ON field.entity_id = entity.id
        INNER JOIN ox_form AS form ON form.entity_id = file.entity_id
        where file.id in (".$fileIds.") AND app.id =".$appID.$where." GROUP BY file.id,app_name,entity.id, entity.name,file_data,file_uuid,file.is_active, file.account_id";
        $this->runGenericQuery("SET SESSION group_concat_max_len = 1000000;");
        $this->logger->info("Executing Query - $select");
        $bodys=$this->executeQuerywithParams($select)->toArray();
        foreach ($bodys as $key => $value) {
            $bodys[$key] = $this->getAllFieldsWithCorrespondingValues($value,$searchIndex);
        }
        return $bodys;
    }

    private function sendDataToElasticForBulk ($fileIds,$bodys,$appID) {
        $indexIdList = array_column($bodys, 'id');

        $select = 'SELECT file.id from ox_file as file
        INNER JOIN ox_app_entity as entity ON file.entity_id = entity.id
        INNER JOIN ox_app as app on entity.app_id = app.id
        where file.id in ('.$fileIds.') AND app.id ='.$appID.' and file.is_active = 0';
        $list = $this->executeQuerywithParams($select)->toArray();
        $deleteIdList = array_column($list, 'id');

        if (isset($bodys[0]['app_name'])) {
            $app_name = $bodys[0]['app_name'];
        }
        if (isset($app_name)&&isset($bodys)) {
            $this->messageProducer->sendQueue(json_encode(array('index'=>  $app_name.'_index', 'operation' => 'Batch', 'type' => '_doc', 'idlist' => $indexIdList, 'deleteList' => $deleteIdList,'body' => $bodys)), 'elastic');
        }
    }

    public function flattenAndModify($data)
    {
        $databody = array();
        if (!empty($data)) {
            if (isset($data['file_data'])) {
                $file_data = json_decode($data['file_data'], true);
                $databody = array_merge($data, $file_data);
            }
            unset($databody['file_data']);
        }
        foreach ($databody as $key => $value) {
            if (is_string($value)) {
                $result = json_decode($value);
                if (json_last_error() === JSON_ERROR_NONE) {
                    if (is_array($result)) {
                        $databody[$key] = $result;
                    }
                }
            }
        }
        return $databody;
    }

    private function checkTypeAndReturnDefault($value) {
        switch($value) {
            case 'date':
            case 'boolean':
            case 'datetime':
            case 'numeric':
                return null;
                break;
            case 'document':
            case 'json':
            case 'list':
            case 'longtext':
                return '';
                break;
            default:
                return null;
                break;
        }
    }

    private function typeCastToSting($value ,$dataType) {
        switch($dataType) {
            case 'date':
            case 'text':
            case 'datetime':
                return (string) $value;
                break;
            default:
                return $value;
                break;
        }
    }

    public function getAllFieldsWithCorrespondingValues($result,$searchIndex = false)
    {
        $entityId = $result['entity_id'];
        $app_name = $result['app_name'];
        $data = json_decode($result['file_data'], true);
        if($result['participants']){
            $result['participants'] = json_decode($result['participants'], true);;
        }
        $where = "";
        if($searchIndex){
            $where = " AND search_index = 1";
        }
        //get all fields for a particular entity
        $selectFields = "Select name,data_type from ox_field where entity_id = :entity_id AND parent_id IS NULL AND data_type NOT IN ('file') AND isdeleted = 0".$where;
        $params = array('entity_id' => $entityId);
        $fieldResult = $this->executeQuerywithBindParameters($selectFields, $params)->toArray();
        $fieldArray = array_column($fieldResult, 'data_type','name');
        $toBeIndexedArray = array();
        //storing the values for each field from the file data
        foreach ($fieldArray as $key => $value) {
            if (array_key_exists($key, $data)) {
                //remove old data with no type - before data types was introduced
                if (is_array($data[$key]) || $data[$key] === '[]') {
                    $toBeIndexedArray[$key] = null;
                    unset($data[$key]);
                    continue;
                }
                if(isset($data[$key]) && !empty($data[$key])) {
                    $toBeIndexedArray[$key] = $this->typeCastToSting($data[$key],$value);
                } else {
                    $toBeIndexedArray[$key] = $this->checkTypeAndReturnDefault($value);
                }
            } else {
                $toBeIndexedArray[$key] = null;
            }
        }
        unset($result['file_data']);

        //flattening the result
        $indexedData = array_merge($result, $toBeIndexedArray);
        return $indexedData;
    }
}
