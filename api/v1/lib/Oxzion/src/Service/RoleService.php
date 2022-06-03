<?php
namespace Oxzion\Service;

use Exception;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\Model\PrivilegeTable;
use Oxzion\Model\Role;
use Oxzion\Model\RoleTable;
use Oxzion\Security\SecurityManager;
use Oxzion\ServiceException;
use Oxzion\OxServiceException;
use Oxzion\Service\AbstractService;
use Oxzion\Utils\FilterUtils;
use Oxzion\Utils\UuidUtil;
use Oxzion\AccessDeniedException;

class RoleService extends AbstractService
{
    protected $table;
    protected $modelClass;
    public function __construct($config, $dbAdapter, RoleTable $table, PrivilegeTable $privilegeTable)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
        $this->modelClass = new Role();
    }

    public function saveRole($params, &$data, $roleId = null)
    {
        if (isset($params['accountId'])) {
            if (!AuthContext::get(AuthConstants::REGISTRATION) && (!SecurityManager::isGranted('MANAGE_INSTALL_APP_WRITE') && (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') &&
                ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))))) {
                throw new AccessDeniedException("You do not have permissions create/update role");
            } else {
                $accountId = $this->getIdFromUuid('ox_account', $params['accountId']);
            }
        } else {
            $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }
        $this->saveRoleInternal($data, $roleId, $accountId);
    }

    public function saveTemplateRole(&$data, $roleId = null)
    {
        $this->saveRoleInternal($data, $roleId);
    }

    private function saveRoleInternal(&$inputData, $roleId, $accountId = null)
    {
        $data = $inputData;
        if (isset($roleId)) {
            $obj = $this->table->getByUuid($roleId, array());
            if (isset($obj)) {
                $roleId = $obj->id;
            } else {
                $roleId = null;
            }
        } else {
            $roleId = null;
        }
        $rolename = $data['name'];
        $data['description'] = isset($data['description']) ? $data['description'] : null;
        $data['privileges'] = isset($data['privileges']) ? $data['privileges'] : array();
        $data['default'] = isset($data['default']) && $data['default'] ? $data['default'] : (isset($data['default_role']) ? $data['default_role'] : 0);
        $this->logger->info("\n Data modified before the transaction - " . print_r($data, true));
        $count = 0;
        try {
            //First, all the other roles are changed to default role 0, and the new record added will be default role 1
            $this->beginTransaction();
            $clause = "";
            $params = [];
            if ($accountId) {
                $clause .= " AND account_id =" . $accountId;
                $params["accountId"] = $accountId;
            }
            if (isset($data['app_id'])) {
                $clause .= " AND app_id =" . $data['app_id'];
            }else{
                $clause .= " AND app_id IS NULL";
            }
            if ($data['default'] == 1 && $clause != "") {
                $queryString = "UPDATE ox_role set default_role = 0 where id != 0 $clause";
                $result = $this->executeQueryWithBindParameters($queryString, $params);
            }

            $businessRoleId = isset($data['business_role_id'])? $data['business_role_id']: 'NULL';

            if (!isset($roleId) && isset($rolename)) {
                $select = "SELECT id,uuid,business_role_id,account_id from ox_role where name = '" . $rolename . "' $clause";
                $result = $this->executeQueryWithBindParameters($select, $params)->toArray();
                if (count($result) > 0) {
                    $roleId = $result[0]['id'];
                    $data['id'] = $roleId;
                    $roleUuid = $result[0]['uuid'];
                    if(isset($businessRoleId) && $businessRoleId != $result[0]['business_role_id']){
                        $roleId = NULL;
                        $data['id'] = NULL;
                        $roleUuid = isset($data['uuid']) ? $data['uuid'] : UuidUtil::uuid();
                    }
                    $data['uuid'] = $roleUuid;
                }
            }
            if (isset($roleId) && $roleId != null) {
                $update = "UPDATE `ox_role` SET `name`= '" . $data['name'] . "', business_role_id = $businessRoleId, `description`= '" .
                            $data['description'] . "', `default_role`= '" . $data['default'] . "' WHERE `id` = '" . $roleId . "' AND name not in ('ADMIN', 'MANAGER', 'EMPLOYEE') $clause";
                $this->logger->info("UPATED QUERY ----".$update);
                $result1 = $this->executeUpdateWithBindParameters($update, $params);
                $count = $result1->getAffectedRows();
            } else {
                if (!isset($rolename)) {
                    throw new ServiceException("Role name cannot be empty", "role.name.empty");
                }
                $inputData['uuid'] = $data['uuid'] = isset($data['uuid']) ? $data['uuid'] : UuidUtil::uuid();

                $this->logger->info("FINAL INSERT VALUE \n".print_r($data,true). "\n AccountID ----".$accountId);
                
                $data['is_system_role'] = isset($data['is_system_role']) ? $data['is_system_role'] : 0;
                $data['default'] = $data['default'] ? 1 :0;

                $executeInsert = "Yes";

                if(isset($businessRoleId) && $businessRoleId != "" && $businessRoleId != 'NULL' && isset($accountId) && $accountId != "") { 
                    $select  = "SELECT * from ox_account_business_role where account_id =:accountId and business_role_id =:businessRoleId";
                    $queryparams = array("accountId" => $accountId,"businessRoleId" => $businessRoleId);
                    $result = $this->executeQueryWithBindParameters($select, $queryparams)->toArray();
                    $this->logger->info("DUPLICATE ENTRY ISSUE \n".count($result));
                    if(count($result) == 0){
                        $this->logger->info("UNWANTED DATA .... \n ".print_r($data,true));
                        $executeInsert = "No";
                    }
                }
                $this->logger->info("EXEUTEINSERT VALUE DATA .... \n ".$executeInsert);
                if($executeInsert == "Yes"){
                    $this->logger->info("VALID DATA .... \n ".print_r($data,true));
                    $insert = "INSERT into `ox_role` (`name`,`description`,`uuid`,`account_id`,`is_system_role`, `default_role`, business_role_id, app_id) 
                            VALUES ('" . $rolename . "','" . $data['description'] . "','" . $data['uuid'] . "'," . ($accountId ? $accountId : 'NULL') . ",'" . $data['is_system_role'] . "','" . $data['default'] . "', $businessRoleId," . (isset($data['app_id']) ? $data['app_id'] : 'NULL') . ")";
                    $result1 = $this->runGenericQuery($insert);
                    $count = $result1->getAffectedRows();
                    if ($count > 0) {
                        $roleId = $result1->getGeneratedValue();
                    } else {
                        throw new ServiceException("Could not save Role", 'role.save.failed');
                    }
                    if (isset($data['privileges'])) {
                        $privileges = $data['privileges'];
                        $appId = isset($data['app_id']) ? $data['app_id'] : null;
                        $this->updateRolePrivileges($roleId, $data['privileges'], $accountId, $appId);
                    }
                } 
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    protected function updateRolePrivileges($roleId, &$privileges, $accountId = null, $appId = null)
    {
        try {
            $delete = "DELETE from `ox_role_privilege` where role_id =" . $roleId . "";
            $result = $this->runGenericQuery($delete);
            for ($i = 0; $i < sizeof($privileges); $i++) {
                if (isset($privileges[$i]['privilege_name']) && isset($privileges[$i]['permission']) ) {
                    $insert = "INSERT INTO `ox_role_privilege` (`role_id`,`privilege_name`,`permission`,`account_id`,`app_id`)
                            SELECT " . $roleId . ",'" . $privileges[$i]['privilege_name'] . "', CASE WHEN permission_allowed >" . $privileges[$i]['permission'] . " THEN " . $privileges[$i]['permission'] . " ELSE permission_allowed END ," . ($accountId ? $accountId : 'NULL') .
                        ", app_id from ox_privilege where name = '" . $privileges[$i]['privilege_name'] . "'";
                    $this->logger->info("Executing query $insert");
                    $resultSet = $this->runGenericQuery($insert);
                    $privilegeId = $resultSet->getGeneratedValue();
                    $privileges[$i]['id'] = $privilegeId;
                }
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    protected function createSystemRoleForAccount(&$data)
    {
        if (!$data['account_id']) {
            $data['account_id'] = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }
        $data['privileges'] = array();
        $this->beginTransaction();
        $count = 0;
        try {
            $params['accountId'] = $this->getUuidFromId('ox_account', $data['account_id']);
            $this->saveRole($params, $data);
            $this->updateDefaultRolePrivileges($data);
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function deleteRole($id, $params)
    {
        if (isset($params['accountId'])) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') &&
                ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You do not have permissions to delete the role");
            } else {
                $accountId = $this->getIdFromUuid('ox_account', $params['accountId']);
            }
        }

        $obj = $this->table->getByUuid($id, array());
        if (is_null($obj)) {
            throw new ServiceException("Role not found", "role.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
        }

        if (isset($accountId)) {
            if ($accountId != $obj->account_id) {
                throw new ServiceException("Role does not belong to the account", "role.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
            }
        }
        if ($obj->default_role == 1) {
            throw new ServiceException("System Roles cannot be deleted", "role.cannot.delete", OxServiceException::ERR_CODE_NOT_FOUND);
        }

        $this->beginTransaction();
        $count = 0;
        try {
            $deleteAccess = "DELETE from `ox_user_role` where role_id =" . $obj->id . "";
            $this->runGenericQuery($deleteAccess);
            $delete = "DELETE from `ox_role_privilege` where role_id =" . $obj->id . "";
            $this->runGenericQuery($delete);
            $count = $this->table->deleteByUuid($id);
            if ($count == 0) {
                throw new ServiceException("Role not found", "role.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function getRoles($filterParams = null, $params)
    {
        if (isset($params['accountId'])) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') &&
                ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You do not have permissions get the role list");
            } else {
                $accountId = $this->getIdFromUuid('ox_account', $params['accountId']);
            }
        } else {
            $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }

        $pageSize = 10000;
        $offset = 0;
        $where = "";
        $sort = "name";

        $cntQuery = "SELECT count(id) FROM `ox_role`";

        if (count($filterParams) > 0 || sizeof($filterParams) > 0) {
            $filterArray = json_decode($filterParams['filter'], true);
            if (isset($filterArray[0]['filter'])) {
                $filterlogic = isset($filterArray[0]['filter']['logic']) ? $filterArray[0]['filter']['logic'] : "AND";
                $filterList = $filterArray[0]['filter']['filters'];
                $where = " WHERE " . FilterUtils::filterArray($filterList, $filterlogic);
            }
            if (isset($filterArray[0]['sort']) && count($filterArray[0]['sort']) > 0) {
                $sort = $filterArray[0]['sort'];
                $sort = FilterUtils::sortArray($sort);
            }
            $pageSize = $filterArray[0]['take'];
            $offset = $filterArray[0]['skip'];
        }

        $where .= strlen($where) > 0 ? " AND " : "WHERE ";
        $where .= "account_id =" . $accountId;
        
        if (isset($params['app_id'])) {
            $where .= " AND app_id=". $params['app_id'];
        } else {
            $where .= " AND app_id IS NULL";
        }

        $sort = " ORDER BY " . $sort;
        $limit = " LIMIT " . $pageSize . " offset " . $offset;
        $resultSet = $this->executeQuerywithParams($cntQuery . $where);
        $count = $resultSet->toArray()[0]['count(id)'];
        $query = "SELECT * FROM `ox_role`" . $where . " " . $sort . " " . $limit;
        $resultSet = $this->executeQuerywithParams($query);
        return array('data' => $resultSet->toArray(),
            'total' => $count);
    }

    public function getRole($params)
    {
        if (isset($params['accountId'])) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') &&
                ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You do not have permissions get the role list");
            } else {
                $accountId = $this->getIdFromUuid('ox_account', $params['accountId']);
            }
        } else {
            $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }
        $roleId = $this->getIdFromUuid('ox_role', $params['roleId']);
        if (!$roleId) {
            throw new ServiceException("Invalid Role", 'invalid.role', OxServiceException::ERR_CODE_NOT_FOUND);
        }
        $query = "SELECT * FROM ox_role 
                    WHERE ox_role.id =" . $roleId . " AND ox_role.account_id=" . $accountId;

        $result = $this->executeQuerywithParams($query);
        $queryString = "SELECT ox_role_privilege.id, ox_role_privilege.privilege_name,ox_role_privilege.permission, ox_role_privilege.app_id,ox_app.name
                            from ox_role_privilege left outer join ox_app on ox_role_privilege.app_id = ox_app.id
                            where ox_role_privilege.role_id = " . $roleId . " AND ox_role_privilege.account_id=" . $accountId .
            " order by ox_role_privilege.privilege_name";
        $result1 = $this->executeQuerywithParams($queryString);
        $resp = $result->toArray();

        if (count($resp) > 0) {
            $resp = $resp[0];
            $resp['privileges'] = $result1->toArray();
        } else {
            $resp = array();
        }

        return $resp;
    }

    public function getRolePrivilege($params)
    {
        if (isset($params['accountId'])) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') &&
                ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You do not have permissions get the role privilege list");
            } else {
                $accountId = $this->getIdFromUuid('ox_account', $params['accountId']);
            }
        } else {
            $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }
        $roleId = $this->getIdFromUuid('ox_role', $params['roleId']);

        $queryString = "SELECT ox_role_privilege.id,ox_role_privilege.role_id, 
                        ox_role_privilege.privilege_name,ox_role_privilege.permission,
                        ox_role_privilege.account_id, ox_role_privilege.app_id,ox_app.name 
                        from ox_role_privilege
                        left join ox_app on ox_role_privilege.app_id = ox_app.id
                        where ox_role_privilege.role_id = :roleId 
                        AND ox_role_privilege.account_id= :accountId
                        order by ox_role_privilege.role_id";
        $queryParams = ['roleId' => $roleId,
                        'accountId' => $accountId];
        $resultSet = $this->executeQueryWithBindParameters($queryString, $queryParams);
        return $resultSet->toArray();
    }

    public function getRolesByAccountId($accountid, array $businessRoleId = null)
    {
        if (!$businessRoleId || ($businessRoleId && count($businessRoleId) == 1)) {
            return $this->getDataByParams('ox_role', array(), array('account_id' => $accountid, "business_role_id" => $businessRoleId ? $businessRoleId[0] : $businessRoleId))->toArray();
        }

        $bRole = "";
        $queryParams = $accountid ? ["accountId" => $accountid] : [];
        foreach ($businessRoleId as $key => $value) {
            if ($bRole != "") {
                $bRole .= ", ";
            } else {
                $bRole ="(";
            }
            $bRole.=":param$key";
            $queryParams["param$key"] = $value;
        }
        $bRole .= ")";
        $acctClause = $accountid ? " and account_id = :accountId" : "";
        $query = "select * from ox_role where business_role_id in $bRole $acctClause";
        $result = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
        return $result;
    }

    public function getRolesByAppId($appId, $accountId = null)
    {
        $acctClause = "rp.account_id";
        $params = ["appId" => $appId];
        if ($accountId) {
            $acctClause .= " = :accountId";
            $params['accountId'] = $accountId;
        } else {
            $acctClause .= " IS NULL";
        }
        $query = "SELECT distinct r.* from ox_role r 
                  inner join ox_role_privilege rp on rp.role_id = r.id
                  where rp.app_id = :appId and $acctClause";
        $this->logger->info("Excecuting Query $query with params--".print_r($params, true));
        $result = $this->executeQueryWithBindParameters($query, $params)->toArray();
        return $result;
    }

    public function createBasicRoles($accountId)
    {
        $basicRoles = [];
        $basicRoles = $this->getRolesByAccountId(null);
        try {
            foreach ($basicRoles as $basicRole) {
                unset($basicRole['uuid']);
                $basicRole['account_id'] = $accountId;
                $this->createSystemRoleForAccount($basicRole);
            }
            return count($basicRoles);
        } catch (Exception $e) {
            throw $e;
        }
    }

    protected function updateDefaultRolePrivileges($role)
    {
        if (isset($role['uuid']) && UuidUtil::isValidUuid($role['uuid'])) {
            $roleId = $this->getIdFromUuid('ox_role', $role['uuid']);
        } else {
            $roleId = $role['id'];
        }
        $query = "INSERT IGNORE into ox_role_privilege (role_id, privilege_name, permission, account_id, app_id)
                    SELECT $roleId, rp.privilege_name,rp.permission," . $role['account_id'] .
            ",rp.app_id from ox_role_privilege rp 
                        left join ox_role r on rp.role_id = r.id
                    where r.name = '" . $role['name'] . "' and r.account_id is NULL";
        if (isset($role['business_role_id'])) {
            $query .= " AND r.business_role_id = ".$role['business_role_id'];
        }
        $this->logger->info("updateDefaultRolePrivileges --------\n".print_r($role,true),"--------\n-------Query---\n".$query);
        $count = $this->runGenericQuery($query);
        if (!$count) {
            throw new ServiceException("Failed to update role privileges", "failed.update.default.privileges");
        }
    }

    public function createRolesByBusinessRole($accountId, $appId)
    {
        try {
            $this->beginTransaction();
            $sqlQuery = "INSERT INTO ox_role(`name`,`description`,`account_id`,
                    `is_system_role`,`uuid`,`default_role`,`business_role_id`,`app_id`) 
                    (SELECT oxr.name,oxr.description,obr.account_id,oxr.is_system_role,UUID(),oxr.default_role,oxr.business_role_id ,$appId
                    FROM ox_role oxr 
                    inner join ox_account_business_role obr on obr.business_role_id = oxr.business_role_id AND oxr.account_id IS NULL
                    inner join ox_business_role br on  br.id = obr.business_role_id
                    where obr.account_id = :accountId and br.app_id = :appId)";
            $params = ["accountId" => is_numeric($accountId) ? $accountId : $this->getIdFromUuid('ox_account', $accountId), "appId" => $appId];
            $this->logger->info("ROLE QUERY--$sqlQuery with params---".print_r($params, true));
            $this->executeUpdateWithBindParameters($sqlQuery, $params);
            $sqlQuery = "INSERT INTO ox_role_privilege (`role_id`,`privilege_name`,
                        `permission`,`account_id`,`app_id`)
                        (SELECT distiNCT acr.id,oxrp.privilege_name,oxrp.permission,acr.account_id,oxrp.app_id
                        FROM ox_role_privilege oxrp 
                        inner join ox_role oxr on oxr.id = oxrp.role_id and oxrp.app_id = :appId And oxrp.account_id IS NULL AND oxr.account_id IS NULL
                        inner join ox_role acr on acr.name = oxr.name and acr.business_role_id = oxr.business_role_id
                        inner join ox_account_business_role obr on obr.business_role_id = oxr.business_role_id AND obr.Account_id = acr.account_id
                        inner join ox_business_role br on  br.id = obr.business_role_id
                        WHERE obr.account_id = :accountId and br.app_id = :appId)";
            $this->logger->info("ROLE Privilege QUERY--$sqlQuery with params---".print_r($params, true));
            $this->executeUpdateWithBindParameters($sqlQuery, $params);
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
}
