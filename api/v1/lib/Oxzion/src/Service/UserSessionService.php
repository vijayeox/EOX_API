<?php
namespace Oxzion\Service;

use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\Service\AbstractService;

class UserSessionService extends AbstractService
{
    public function __construct($config, $dbAdapter)
    {
        parent::__construct($config, $dbAdapter);
    }

    public function updateSessionData($data)
    {
        $resultSet = $this->getSessionData();
        $sql = $this->getSqlObject();
        if (count($resultSet) == 0) {
            $insert = $sql->insert('ox_user_session');
            $insertData = array('user_id' => AuthContext::get(AuthConstants::USER_ID), 'account_id' => AuthContext::get(AuthConstants::ACCOUNT_ID), 'data' => $data['data'], 'date_modified' => date('Y-m-d H:i:s'));
            $insert->values($insertData);
            $result = $this->executeUpdate($insert);
        } else {
            $updatedData['data'] = $data['data'];
            $updatedData['date_modified'] = date('Y-m-d H:i:s');
            $update = $sql->update('ox_user_session')->set($updatedData)
                ->where(array('ox_user_session.user_id' => AuthContext::get(AuthConstants::USER_ID), 'ox_user_session.account_id' => AuthContext::get(AuthConstants::ACCOUNT_ID)));
            $result = $this->executeUpdate($update);
        }
    }

    public function getSessionData()
    {
        $sql = $this->getSqlObject();
        $select = $sql->select();
        $select->from('ox_user_session')
            ->columns(array('data'))
            ->where(array('user_id' => AuthContext::get(AuthConstants::USER_ID), 'account_id' => AuthContext::get(AuthConstants::ACCOUNT_ID)));
        $result = $this->executeQuery($select)->toArray();
        return array_column($result, 'data');
    }
}
