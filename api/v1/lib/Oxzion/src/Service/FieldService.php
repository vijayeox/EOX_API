<?php
namespace Oxzion\Service;

use Exception;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\Model\Field;
use Oxzion\Model\FieldTable;
use Oxzion\ServiceException;
use Oxzion\Service\AbstractService;

class FieldService extends AbstractService
{
    public function __construct($config, $dbAdapter, FieldTable $table)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
    }
    public function saveField($appUUId, &$data)
    {
        $this->logger->info("Entering to saveField method in FieldService");
        $field = new Field();
        $data['app_id'] = $this->getIdFromUuid('ox_app', $appUUId);
        if ($app = $this->getIdFromUuid('ox_app', $appUUId)) {
            $appId = $app;
        } else {
            $appId = $appUUId;
        }
        $data['app_id'] = $appId;
        $data['data_type'] = isset($data['data_type']) ? trim($data['data_type']) : "";
        if (isset($data['data_type']) && !isset($data['type'])) {
            $data['type'] = $this->getFieldTypeByDataType($data['data_type']);
        }
        if (!isset($data['id']) || $data['id'] == 0) {
            $data['created_by'] = AuthContext::get(AuthConstants::USER_ID);
            $data['date_created'] = date('Y-m-d H:i:s');
        }
        $data['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_modified'] = date('Y-m-d H:i:s');
        $this->logger->info(__CLASS__ . "-> Data modified before create - " . print_r($data, true));
        $field->exchangeArray($data);
        $field->validate();
        $this->beginTransaction();
        $count = 0;
        try {
            $count = $this->table->save($field);
            if ($count == 0) {
                $this->commit();
                return 0;
            }
            if (!isset($data['id']) || $data['id'] == 0) {
                $id = $this->table->getLastInsertValue();
                $data['id'] = $id;
            }
            $this->commit();
            $this->logger->info(__CLASS__ . "-> Field Created - " . print_r($count, true));
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        return $count;
    }

    public function updateField($id, &$data)
    {
        $this->logger->info("Entering to updateField method in FieldService ");
        $obj = $this->table->getByUuid($id);
        if (is_null($obj)) {
            return 0;
        }
        $data['id'] = $this->getIdFromUuid('ox_field', $id);
        $data['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_modified'] = date('Y-m-d H:i:s');
        if (isset($data['data_type']) && !isset($data['type'])) {
            $data['type'] = $this->getFieldTypeByDataType($data['data_type']);
        }
        $file = $obj->toArray();
        $changedArray = array_merge($obj->toArray(), $data);
        $this->logger->info(__CLASS__ . "-> Data modified before Update - " . print_r($changedArray, true));
        $field = new Field();
        $field->exchangeArray($changedArray);
        $field->validate();
        $this->beginTransaction();
        $count = 0;
        try {
            $count = $this->table->save($field);
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

    public function deleteField($appId, $id)
    {
        $this->logger->info("Entering to deleteField method in FieldService");
        $id = $this->getIdFromUuid('ox_field', $id);
        $this->beginTransaction();
        $count = 0;
        try {
            $select = "SELECT * FROM ox_file_attribute WHERE field_id = :fieldId";
            $selectParams = array('fieldId' => $id);
            $result = $this->executeQueryWithBindParameters($select, $selectParams)->toArray();
            if (!empty($result)) {
                throw new ServiceException("Field Cannot be deleted", "cannot.delete.field");
            } else {
                $count = $this->table->delete($id, ['app_id' => $this->getIdFromUuid('ox_app', $appId)]);
                if ($count == 0) {
                    $this->rollback();
                    return 0;
                }
                $this->commit();
            }
        } catch (Exception $e) {
            $this->rollback();
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
        return $count;
    }

    public function getFields($appId = null, $filterArray = array())
    {
        $this->logger->info("Entering to getFields method in FieldService");
        try {
            if (isset($appId)) {
                $filterArray['app_id'] = $this->getIdFromUuid('ox_app', $appId);
            }
            if (isset($filterArray['entityName'])) {
                $queryString = "Select ox_app_entity.id from ox_app_entity
                where ox_app_entity.name = :entityName";
                $queryParams = array('entityName' => $filterArray['entityName']);
                $resultSet = $this->executeQueryWithBindParameters($queryString, $queryParams)->toArray();
                if(count($resultSet) > 0) {
                    $filterArray['entity_id'] = $resultSet[0]['id'];
                }
                unset($filterArray['entityName']);
            }
            $resultSet = $this->getDataByParams('ox_field', array("*"), $filterArray, null, null, null, null, null, false);
            $response = array();
            $response['data'] = $resultSet->toArray();
            return $response;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }

    public function getField($appId, $id)
    {
        $this->logger->info("Entering to getField method in FieldService");
        try {
            $queryString = "Select ox_field.* from ox_field
            inner join ox_app_entity as en on ox_field.entity_id = en.id
            left join ox_app on ox_app.id = en.app_id
            where ox_app.uuid=? and ox_field.uuid=? and en.isdeleted=?";
            $queryParams = array($appId, $id,0);
            $resultSet = $this->executeQueryWithBindParameters($queryString, $queryParams)->toArray();
            if (count($resultSet) == 0) {
                return 0;
            }
            return $resultSet[0];
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }

    public function getFieldByName($entityId, $fieldName)
    {
        $this->logger->info("EntityId = $entityId, FieldName = $fieldName");
        try {
            $queryString = "Select oxf.* from ox_field as oxf
                            inner join ox_app_entity as en on oxf.entity_id = en.id
            where en.uuid=? and oxf.name=? and en.isdeleted=?";
            $queryParams = array($entityId, $fieldName, 0);
            $resultSet = $this->executeQueryWithBindParameters($queryString, $queryParams)->toArray();
            if (count($resultSet) == 0) {
                return 0;
            }
            return $resultSet[0];
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }

    private function getFieldTypeByDataType($dataType)
    {
        switch ($dataType) {
            case 'document':
                $type = 'document';
                break;
            
            default:
                $type = 'text';
                break;
        }
        return $type;
    }
}
