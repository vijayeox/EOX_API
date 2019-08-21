<?php
namespace Oxzion\Service;

use Oxzion\Model\ActivityTable;
use Oxzion\Model\Activity;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Oxzion\Service\AbstractService;
use Oxzion\ValidationException;
use Zend\Db\Sql\Expression;
use Exception;

class ActivityService extends AbstractService
{
    public function __construct($config, $dbAdapter, ActivityTable $table)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
    }

    public function createActivity($appId, &$data)
    {
        $activity = new Activity();
        $data['created_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_created'] = date('Y-m-d H:i:s');
        $data['date_modified'] = date('Y-m-d H:i:s');
        $activity->exchangeArray($data);
        $activity->validate();
        $this->beginTransaction();
        $count = 0;
        try {
            $count = $this->table->save($activity);
            if ($count == 0) {
                $this->rollback();
                return 0;
            }
            $id = $this->table->getLastInsertValue();
            $data['id'] = $id;
            $this->commit();
        } catch (Exception $e) {
            switch (get_class($e)) {
             case "Oxzion\ValidationException":
                $this->rollback();
                throw $e;
                break;
             default:
                $this->rollback();
                return 0;
                break;
            }
        }
        return $count;
    }
    public function updateActivity($id, &$data)
    {
        $obj = $this->table->get($id, array());
        if (is_null($obj)) {
            return 0;
        }
        $file = $obj->toArray();
        $activity = new Activity();
        $changedArray = array_merge($obj->toArray(), $data);
        $changedArray['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $changedArray['date_modified'] = date('Y-m-d H:i:s');
        $activity->exchangeArray($changedArray);
        $activity->validate();
        $this->beginTransaction();
        $count = 0;
        try {
            $count = $this->table->save($activity);
            if ($count == 0) {
                $this->rollback();
                return 0;
            }
            $this->commit();
        } catch (Exception $e) {
            switch (get_class($e)) {
             case "Oxzion\ValidationException":
                $this->rollback();
                throw $e;
                break;
             default:
                $this->rollback();
                return 0;
                break;
            }
        }
        return $count;
    }

    public function deleteActivity($id)
    {
        $this->beginTransaction();
        $count = 0;
        try {
            $count = $this->table->delete($id, []);
            if ($count == 0) {
                $this->rollback();
                return 0;
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
        }
        
        return $count;
    }

    public function getActivitys($appId=null, $filterArray=array())
    {
        if (isset($appId)) {
            $filterArray['app_id'] = $appId;
        }
        $resultSet = $this->getDataByParams('ox_activity', array("*"), $filterArray, null);
        $response = array();
        $response['data'] = $resultSet->toArray();
        return $response;
    }
    public function getActivity($id)
    {
        $sql = $this->getSqlObject();
        $select = $sql->select();
        $select->from('ox_activity')
        ->columns(array("*"))
        ->where(array('ox_activity.id' => $id));
        $response = $this->executeQuery($select)->toArray();
        if (count($response)==0) {
            return 0;
        }
        return $response[0];
    }
}
