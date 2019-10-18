<?php
namespace Oxzion\Service;

use Oxzion\Model\FieldTable;
use Oxzion\Model\Field;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Oxzion\Service\AbstractService;
use Oxzion\ValidationException;
use Zend\Db\Sql\Expression;
use Exception;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;
use Oxzion\ServiceException;

class FieldService extends AbstractService
{
    public function __construct($config, $dbAdapter, FieldTable $table)
    {
        $logger = new Logger();
        $writer = new Stream(__DIR__ . '/../../../../logs/field.log');
        $logger->addWriter($writer);
        parent::__construct($config, $dbAdapter,$logger);
        $this->table = $table;
    }
    public function saveField($appUUId, &$data)
    {
        $this->logger->info("Entering to saveField method in FieldService");
        $field = new Field();
        $data['app_id'] = $this->getIdFromUuid('ox_app',$appUUId);
        if($app = $this->getIdFromUuid('ox_app',$appUUId)){
            $appId = $app;
        } else {
            $appId = $appUUId;
        }
        $data['app_id'] = $appId;
        if (!isset($data['id']) || $data['id']==0) {
            $data['created_by'] = AuthContext::get(AuthConstants::USER_ID);
            $data['date_created'] = date('Y-m-d H:i:s');
        }
        $data['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_modified'] = date('Y-m-d H:i:s');
        $field->exchangeArray($data);
        $field->validate();
        $this->beginTransaction();
        $count = 0;
        try {
            $count = $this->table->save($field);
            if ($count == 0) {
                $this->rollback();
                return 0;
            }
            if (!isset($data['id']) || $data['id']==0) {
                $id = $this->table->getLastInsertValue();
                $data['id'] = $id;
            }
            $this->commit();
        } catch (Exception $e) {
            switch (get_class($e)) {
                case "Oxzion\ValidationException":
                $this->rollback();
                $this->logger->log(Logger::ERR, $e->getMessage());
                throw $e;
                break;
                default:
                $this->rollback();
                $this->logger->log(Logger::ERR, $e->getMessage());
                throw $e;
                break;
            }
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
        $data['id'] = $this->getIdFromUuid('ox_field',$id);
        $data['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_modified'] = date('Y-m-d H:i:s');
        $file = $obj->toArray();
        $changedArray = array_merge($obj->toArray(), $data);
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
            $this->logger->log(Logger::ERR, $e->getMessage());
            throw $e;
        }
        return $count;
    }
    
    
    public function deleteField($appId, $id)
    {       
        $this->logger->info("Entering to deleteField method in FieldService");
        $id = $this->getIdFromUuid('ox_field',$id);
        $this->beginTransaction();
        $count = 0;
        try {
            $select = "SELECT * FROM ox_file_attribute WHERE field_id = :fieldId";
            $selectParams = array('fieldId' => $id);
            $result = $this->executeQueryWithBindParameters($select,$selectParams)->toArray();
            if(!empty($result)){
                throw new ServiceException("Field Cannot be deleted","cannot.delete.field");
            }else{
                $count = $this->table->delete($id, ['app_id'=>$this->getIdFromUuid('ox_app',$appId)]);
                if ($count == 0) {
                    $this->rollback();
                    return 0;
                }
                $this->commit();
            }
        } catch (Exception $e) {
            $this->rollback();
            $this->logger->log(Logger::ERR, $e->getMessage());
            throw $e;
        }
        
        return $count;
    }
    
    public function getFields($appId=null, $filterArray = array())
    {
        $this->logger->info("Entering to getFields method in FieldService");
        try{
            if (isset($appId)) {
                $filterArray['app_id'] = $this->getIdFromUuid('ox_app',$appId);
            }
            $resultSet = $this->getDataByParams('ox_field', array("*"), $filterArray, null);
            $response = array();
            $response['data'] = $resultSet->toArray();
            return $response;
        }catch(Exception $e){
            $this->logger->log(Logger::ERR, $e->getMessage());
            throw $e;
        }
    }
    public function getField($appId, $id)
    { 
        $this->logger->info("Entering to getField method in FieldService");
        try{
            $queryString = "Select ox_field.* from ox_field 
            left join ox_app on ox_app.id = ox_field.app_id
            where ox_app.uuid=? and ox_field.uuid=?";
            $queryParams = array($appId,$id); 
            $resultSet = $this->executeQueryWithBindParameters($queryString, $queryParams)->toArray();
            if (count($resultSet)==0) {
                return 0;
            }
            return $resultSet[0];
        }catch(Exception $e){
            $this->logger->log(Logger::ERR, $e->getMessage());
            throw $e;
        }
    }
}
