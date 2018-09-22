<?php
namespace Alert\Service;

use Oxzion\Service\AbstractService;
use Alert\Model\AlertTable;
use Alert\Model\Alert;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Oxzion\ValidationException;
use Exception;

class AlertService extends AbstractService{
    const ANNOUNCEMENT_FOLDER = "/announcements/";

    private $table;

    public function __construct($config, $dbAdapter, AlertTable $table){
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
    }
    public function createAlert(&$data){
        $form = new Alert();
        $data['org_id'] = AuthContext::get(AuthConstants::ORG_ID);
        $data['created_id'] = AuthContext::get(AuthConstants::USER_ID);
        $data['status'] = $data['status']?$data['status']:1;
        $data['created_date'] = date('Y-m-d H:i:s');
        $form->exchangeArray($data);
        $form->validate();
        $this->beginTransaction();
        $count = 0;
        try{
            $count = $this->table->save($form);
            if($count == 0){
                $this->rollback();
                return 0;
            }
            $id = $this->table->getLastInsertValue();
            $data['id'] = $id;
            $this->commit();
        }catch(Exception $e){
            $this->rollback();
            return 0;
        }
        return $count;
    }
    public function updateAlertStatus($status,$id){
        $obj = $this->table->get($id,array());
        if(is_null($obj)){
            return 0;
        }
        $data['user_id'] = AuthContext::get(AuthConstants::USER_ID);
        $data['alert_id'] = $id;
        $data['status'] = $status;
        $sql = $this->getSqlObject();
        $select = $sql->update('user_alert_verfication')->set($data)
                ->where(array('user_alert_verfication.alert_id' => $data['alert_id'],'user_alert_verfication.user_id' => $data['user_id']));
        $result = $this->executeUpdate($select);
        if($result->getAffectedRows() == 0){
            return 0;
        } else {
            return $id;
        }
    }
    public function updateAlert($id,&$data){
        $obj = $this->table->get($id,array());
        if(is_null($obj)){
            return 0;
        }
        $originalArray = $obj->toArray();
        $form = new Alert();
        $data = array_merge($originalArray, $data);
        $data['id'] = $id;
        $form->exchangeArray($data);
        $form->validate();
        $this->beginTransaction();
        $count = 0;
        try{
            $count = $this->table->save($form);
            if($count == 0){
                $this->rollback();
                return 0;
            }
            $this->commit();
        }catch(Exception $e){
            $this->rollback();
            return 0;
        }
        return $id;
    }


    public function deleteAlert($id){
        $this->beginTransaction();
        $count = 0;
        try{
            $count = $this->table->delete($id, ['org_id' => AuthContext::get(AuthConstants::ORG_ID)]);
            if($count == 0){
                $this->rollback();
                return 0;
            }
            $sql = $this->getSqlObject();
            $delete = $sql->delete('user_alert_verfication');
            $delete->where(['alert_id' => $id]);
            $result = $this->executeUpdate($delete);
            if($result->getAffectedRows() == 0){
                $this->rollback();
                return 0;
            }
            $this->commit();
        }catch(Exception $e){
            $this->rollback();
        }
        return $count;
    }

    public function getAlerts() {
        $sql = $this->getSqlObject();
        $select = $sql->select()
                ->from('ox_alert')
                ->columns(array("*"))
                ->where(array('ox_alert.org_id' => AuthContext::get(AuthConstants::ORG_ID)));
        return $this->executeQuery($select)->toArray();
    }
}
?>