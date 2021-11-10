<?php
namespace Oxzion\Service;

use Exception;
use Oxzion\Model\Profile;
use Oxzion\Model\ProfileTable;
use Oxzion\AccessDeniedException;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\ServiceException;
use Oxzion\OxServiceException;
use Oxzion\Service\AbstractService;


class ProfileService extends AbstractService
{
    private $table;

    public function __construct($config, $dbAdapter, ProfileTable $table)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
    }

    public function getProfilesforUser($userIdGuid)
    {
        if (!empty($userIdGuid)) {
            $userId = $this->getIdFromUuid('ox_user', $userIdGuid);
        } else {
            $userId = AuthContext::get(AuthConstants::USER_ID);
        }
        try {
            $select = "SELECT pr.*
                from ox_profile as pr
                inner join ox_role as ro on pr.role_id = ro.id
                inner join ox_user_role as ur on ur.role_id = ro.id
                inner join ox_account_user au on ur.account_user_id = au.id
                where au.user_id = :userId
                order by pr.precedence desc;";
        $params = ['userId' => $userId];
        $resultSet = $this->executeQueryWithBindParameters($select, $params);
        $profileDataArray = $resultSet->toArray();
            if (empty($profileDataArray)) {
                $select = "SELECT ox_profile.* from ox_profile where role_id is null or role_id=''"; //default profile
                $resultSet = $this->executeQueryWithBindParameters($select, $params);
                $profileDataArray = $resultSet->toArray();
                if (empty($profileDataArray)) {
                    throw new ServiceException("PROFILE does not exist", "profile.doesnt.exist", OxServiceException::ERR_CODE_NOT_FOUND);
                }
            }
        } catch (Exception $e) {
            throw $e;
        }
        $data = ['dashboard_uuid'=>$profileDataArray[0]['dashboard_uuid'],'html'=>$profileDataArray[0]['html'],'type'=>$profileDataArray[0]['type']];
        return $data;
    }


    public function addProfile(&$data)
    {
        $this->logger->info("Adding Profile - " . print_r($data, true));
        $profileData = $data;
        $profileData['created_by'] = AuthContext::get(AuthConstants::USER_ID) ? AuthContext::get(AuthConstants::USER_ID) : 1;
        $profileData['date_created'] = date('Y-m-d H:i:s');
        $form = new Profile($this->table);
        $form->assign($profileData);
        try {
            $this->beginTransaction();
            $form->save();
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }


    public function updateProfile($uuid, $data)
    {
        $form = new Profile($this->table);
        $form->loadByUuid($uuid);
        $data['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_modified'] = date('Y-m-d H:i:s');
        $form->assign($data);
        try {
            $this->beginTransaction();
            $form->save();
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function deleteProfile($uuid)
    {
        try {
            $this->beginTransaction();
            $sql = $this->getSqlObject();
            $delete = $sql->delete('ox_profile');
            $delete->where(['uuid' => $uuid]);
            $result = $this->executeUpdate($delete);
            if ($result->getAffectedRows() == 0) {
                throw new ServiceException("Profile not found", "profile.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
     
    }

 
}
