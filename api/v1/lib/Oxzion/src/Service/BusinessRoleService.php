<?php
namespace Oxzion\Service;

use Exception;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\Model\BusinessRole;
use Oxzion\Model\BusinessRoleTable;
use Oxzion\ServiceException;
use Oxzion\Service\AbstractService;
use Oxzion\EntityNotFoundException;

class BusinessRoleService extends AbstractService
{
    protected $table;
    
    public function __construct($config, $dbAdapter, BusinessRoleTable $table)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
    }

    public function saveBusinessRole($appId, &$businessRole)
    {
        $count = 0;
        $data = $businessRole;
        $data['app_id'] = $this->getIdFromUuid('ox_app', $appId);
        $bRole = new BusinessRole($this->table);
        $name = isset($data['name']) ? $data['name'] : null;

        $existingBusinessRole = $this->getBusinessRoleByName($appId,$name);
        if(!empty($existingBusinessRole)) {
            //Prevent creation of duplicate record
            $businessRole['uuid'] = $existingBusinessRole[0]['uuid'];
            $businessRole['version'] = $existingBusinessRole[0]['version'];
        } 
        else { 
            if (isset($data['uuid'])) {
                try {
                    //update using uuid
                    $bRole->loadByUuid($data['uuid']);
                    if (!isset($data['version'])) {
                        $data['version'] = $bRole->getProperty('version');
                    }
                } catch (EntityNotFoundException $e) {
                    //Needs to create with uuid. If block is not specified the exception is not skipped
                }
            }
            $bRole->assign($data);
            try {
                $this->beginTransaction();
                $bRole->save();
                $this->commit();
            } catch (Exception $e) {
                $this->logger->error($e->getMessage(), $e);
                $this->rollback();
                throw $e;
            }
            $data = $bRole->getGenerated();
            $businessRole['uuid'] = $data['uuid'];
            $businessRole['version'] = $data['version'];
        }
    }

    public function getBusinessRole($id)
    {
        $businessRole = new BusinessRole($this->table);
        $businessRole->loadByUuid($id);
        return $businessRole->toArray();
    }

    public function getBusinessRoleByName($appId, $name)
    {
        $query = "select br.* from ox_business_role br 
                    inner join ox_app a on a.id = br.app_id 
                    where a.uuid = :appId and br.name = :name";
        $params = ["appId" => $appId, "name" => $name];
        $result = $this->executeQueryWithBindParameters($query, $params)->toArray();
        return $result;
    }

    public function deleteBusinessRoleBasedOnAppId($appId)
    {
        $result = $this->getDataByParams('ox_business_role', array(), array('app_id' => $appId))->toArray();
        if (count($result) > 0) {
            $deleteQuery = "DELETE oxof, oxbr, br FROM ox_account_offering oxof 
                            right outer join ox_org_business_role oxbr on oxbr.id = oxof.org_business_role_id 
                            right outer join ox_business_role br on br.id = oxbr.business_role_id 
                            right outer join ox_entity_participant_role oepr on oepr.business_role_id = br.id
                            right outer join ox_app a on a.id = br.app_id 
                            WHERE a.uuid=:appId";
            $deleteParams = array('appId' => $appId);
            $deleteResult = $this->executeUpdateWithBindParameters($deleteQuery, $deleteParams);
        }
    }
}
