<?php
namespace Oxzion\Service;

use Exception;
use Oxzion\Model\Kra;
use Oxzion\Model\KraTable;
use Oxzion\AccessDeniedException;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\Security\SecurityManager;
use Oxzion\ServiceException;
use Oxzion\OxServiceException;
use Oxzion\Service\AbstractService;
use Oxzion\Service\AccountService;
use Analytics\Service\QueryService;
use Oxzion\Utils\FileUtils;
use Oxzion\Utils\FilterUtils;
use Oxzion\Utils\UuidUtil;

class KraService extends AbstractService
{
    private $table;
    private $accountService;
    private $userService;
    private $queryService;

    public static $fieldName = array('name' => 'ox_user.name', 'id' => 'ox_user.id');

    public function __construct($config, $dbAdapter, KraTable $table, $accountService, $userService, $queryService)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
        $this->accountService = $accountService;
        $this->userService = $userService;
        $this->queryService = $queryService;
    }

    public function getKrasforUser($userId, $data)
    {
        try {
            if (isset($params['accountId'])) {
                if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') && ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                    throw new AccessDeniedException("You do not have permissions to get the kra list");
                } else {
                    $accountId = $this->getIdFromUuid('ox_account', $params['accountId']);
                }
            } else {
                $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
            }
            $queryString = "SELECT kra.*,
                ot.`type` as target_type, ot.period_type as target_period_type, ot.red_limit, ot.green_limit, ot.yellow_limit,
                oq.configuration as query_config, od.uuid as datasource_id
                from ox_kra as kra
                inner join ox_query oq on oq.id = kra.query_id
                inner join ox_target ot on ot.id = kra.target_id
                inner join ox_datasource od on od.id = oq.datasource_id";
            $where = "where kra.user_id = (SELECT id from ox_user where uuid = '" . $userId . "') AND kra.status = 'Active' AND kra.account_id = " . $accountId;
            $order = "order by kra.name";
            $resultSet = $this->executeQuerywithParams($queryString, $where, null, $order);
            $kraDataArray = array();
            if($resultSet){
                $kraDataArray = $resultSet->toArray();
                $this->evaluateKra($kraDataArray);
            } else {
                throw new ServiceException("KRA does not exist", "kra.doesnt.exist", OxServiceException::ERR_CODE_NOT_FOUND);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return $kraDataArray;
    }

    private function evaluateKra(&$kraDataArray) {
        foreach ($kraDataArray as $key => $value) {
            //Get Query Result or achieved result
            $params = array('datasource_id' => $value['datasource_id'], 'configuration' => $value['query_config']);
            $queryResult = $this->queryService->previewQuery($params);
            $achievedResult = $queryResult['data'];

            if($achievedResult <= $value['red_limit']) {
                $kraDataArray[$key]['limit_achieved'] = 'red';
            } elseif($achievedResult > $value['red_limit'] && $achievedResult <= $value['yellow_limit']) {
                $kraDataArray[$key]['limit_achieved'] = 'yellow';
            } else {
                $kraDataArray[$key]['limit_achieved'] = 'green';
            }

            $kraDataArray[$key]['goal_achieved'] = $achievedResult;

            //Clean output data
            unset($kraDataArray[$key]['id']);
            unset($kraDataArray[$key]['query_config']);
            unset($kraDataArray[$key]['datasource_id']);
            $kraDataArray[$key]['account_id'] = $this->getUuidFromId('ox_account',$value['account_id']);
            $kraDataArray[$key]['user_id'] = $kraDataArray[$key]['user_id'] ? $this->getUuidFromId('ox_user',$value['user_id']) : NULL;
            $kraDataArray[$key]['business_role_id'] = $kraDataArray[$key]['business_role_id'] ? $this->getUuidFromId('ox_business_role',$value['business_role_id']) : NULL;
            $kraDataArray[$key]['team_id'] = $kraDataArray[$key]['team_id'] ? $this->getUuidFromId('ox_team',$value['team_id']) : NULL;
        }
    }

    public function getKrasforBusinessRole($businessRole, $data)
    {
        try {
            if (isset($params['accountId'])) {
                if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') && ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                    throw new AccessDeniedException("You do not have permissions to get the kra list");
                } else {
                    $accountId = $this->getIdFromUuid('ox_account', $params['accountId']);
                }
            } else {
                $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
            }
            $queryString = "SELECT kra.*,
                ot.`type` as target_type, ot.period_type as target_period_type, ot.red_limit, ot.green_limit, ot.yellow_limit,
                oq.configuration as query_config, od.uuid as datasource_id
                from ox_kra as kra
                inner join ox_query oq on oq.id = kra.query_id
                inner join ox_target ot on ot.id = kra.target_id
                inner join ox_datasource od on od.id = oq.datasource_id";
            $where = "where kra.business_role_id = (SELECT id from ox_business_role where uuid = '" . $businessRole . "') AND kra.status = 'Active' AND kra.account_id = " . $accountId;
            $order = "order by kra.name";
            $resultSet = $this->executeQuerywithParams($queryString, $where, null, $order);
            $kraDataArray = array();
            if($resultSet){
                $kraDataArray = $resultSet->toArray();
                $this->evaluateKra($kraDataArray);
            } else {
                throw new ServiceException("KRA does not exist", "kra.doesnt.exist", OxServiceException::ERR_CODE_NOT_FOUND);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return $kraDataArray;
    }
    /**
     * GET Kra Service
     * @method getKra
     * @param $id ID of Kra to GET
     * @return array $data
     * <code> {
     *               id : integer,
     *               name : string,
     *               status : String(Active|Inactive),
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created Kra.
     */

    public function getKraByUuid($id, $params)
    {
        if (isset($params['accountId'])) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') && ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You do not have permissions to get the kra list");
            } else {
                $accountId = $this->getIdFromUuid('ox_account', $params['accountId']);
            }
        } else {
            $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }
        try {
            $sql = "SELECT g.uuid, g.name,  a.uuid as accountId, u.uuid as userId,t.uuid as targetId,t.red_limit,t.green_limit,t.yellow_limit,t.type,t.red_workflow_id,t.yellow_workflow_id,t.green_workflow_id
                        FROM ox_kra g
                        inner join ox_account a on a.id = g.account_id
                        inner join ox_query q on g.query_id=q.id
                        inner join ox_target t on t.id = g.target_id
                        left join ox_user u on g.user_id = u.id
                        where g.uuid =:id and g.status = 'Active' and a.id = :accountId";
            $params = ['id' => $id, 'accountId' => $accountId];
            $response = $this->executeQueryWithBindParameters($sql, $params)->toArray();
            if (count($response) == 0) {
                return array();
            }
        } catch (Exception $e) {
            throw $e;
        }
        return $response[0];
    }

    public function createKra(&$inputData, $accountId = null)
    {
        $data = $inputData;
        if (isset($accountId)) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') && ($accountId != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You do not have permissions create kra");
            } else {
                $data['account_id'] = $this->getIdFromUuid('ox_account', $accountId);
            }
        } else {
            $data['account_id'] = AuthContext::get(AuthConstants::ACCOUNT_ID);
            $accountId = AuthContext::get(AuthConstants::ACCOUNT_UUID);
        }
        try {
            $data['name'] = isset($data['name']) ? $data['name'] : null;
            $select = "SELECT name,uuid,status from ox_kra where name = '" . $data['name'] . "' AND account_id = " . $data['account_id'];
            $result = $this->executeQuerywithParams($select)->toArray();
            if (count($result) > 0) {
                if ($data['name'] == $result[0]['name'] && $result[0]['status'] == 'Active') {
                    throw new ServiceException("Kra already exists", "kra.exists", OxServiceException::ERR_CODE_NOT_ACCEPTABLE);
                } elseif ($result[0]['status'] == 'Inactive') {
                    $data['reactivate'] = isset($data['reactivate']) ? $data['reactivate'] : null;
                    if ($data['reactivate'] == 1) {
                        $data['status'] = 'Active';
                        $inputData['status'] = 'Active';
                        unset($inputData['reactivate']);
                        $accountId = $this->getUuidFromId('ox_account', $data['account_id']);
                        $count = $this->updateKra($result[0]['uuid'], $data, $accountId);
                        return;
                    } else {
                        throw new ServiceException("Kra already exists would you like to reactivate?", "inactive.kra.already.exists", OxServiceException::ERR_CODE_NOT_ACCEPTABLE);
                    }
                }
            }
            $form = new Kra();
            $data['uuid'] = UuidUtil::uuid();
            $data['created_id'] = AuthContext::get(AuthConstants::USER_ID);
            $data['date_created'] = date('Y-m-d H:i:s');
            $data['status'] = 'Active';
            $data['user_id'] = isset($data['userId']) ? $this->getIdFromUuid('ox_user', $data['userId']) : null;
            $data['team_id'] = isset($data['teamId']) ? $this->getIdFromUuid('ox_team', $data['teamId']) : null;
            $data['target_id'] = isset($data['targetId']) ? $this->getIdFromUuid('ox_target', $data['targetId']) : null;
            $data['query_id'] = isset($data['queryId']) ? $this->getIdFromUuid('ox_query', $data['queryId']) : null;
            $account = $this->accountService->getAccount($data['account_id']);
            $form->exchangeArray($data);
            $form->validate();
            $this->beginTransaction();
            $count = 0;
            $count = $this->table->save($form);
            if ($count == 0) {
                throw new ServiceException("Failed to create a new entity", "failed.kra.create", OxServiceException::ERR_CODE_UNPROCESSABLE_ENTITY);
            }
            $id = $this->table->getLastInsertValue();
            $data['id'] = $id;
            $inputData['uuid'] = $data['uuid'];
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * GET Kra Service
     * @method getKra
     * @return array $data
     * <code> {
     *               id : integer,
     *               name : string,
     *               status : String(Active|Inactive),
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created Kra.
     */

    public function getKraList($filterParams = null, $params = null)
    {
        if (isset($params['accountId'])) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') && ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You do not have permissions to get the kras list");
            } else {
                $accountId = $this->getIdFromUuid('ox_account', $params['accountId']);
            }
        } else {
            $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }
        $where = "";
        $pageSize = 20;
        $offset = 0;
        $sort = "name";
        $fieldMap = ['name' => 'g.name','date_created'=>'g.date_created'];
        try {
            $cntQuery = "SELECT count(g.id) as count FROM `ox_kra` g inner join ox_query q on q.id = g.query_id
                        inner join ox_target t on t.id = g.target_id inner join ox_account a on a.id = g.account_id
                        left join ox_team team on team.id = g.team_id
                        left join ox_user user on user.id = g.user_id ";
            if (count($filterParams) > 0 || sizeof($filterParams) > 0) {
                if (isset($filterParams['filter'])) {
                    $filterArray = json_decode($filterParams['filter'], true);
                    if (isset($filterArray[0]['filter'])) {
                        $filterlogic = isset($filterArray[0]['filter']['logic']) ? $filterArray[0]['filter']['logic'] : "AND";
                        $filterList = $filterArray[0]['filter']['filters'];
                        $where = " WHERE " . FilterUtils::filterArray($filterList, $filterlogic, $fieldMap);
                    }
                    if (isset($filterArray[0]['sort']) && count($filterArray[0]['sort']) > 0) {
                        $sort = $filterArray[0]['sort'];
                        $sort = FilterUtils::sortArray($sort,$fieldMap);
                    }
                    if(isset($filterArray[0]['take'])){
                        $pageSize = $filterArray[0]['take'];
                    } else {
                        $pageSize = 20;
                    }
                    if(isset($filterArray[0]['skip'])){
                        $offset = $filterArray[0]['skip'];
                    } else {
                        $offset = 0;
                    }
                }
                if (isset($filterParams['exclude'])) {
                    $where .= strlen($where) > 0 ? " AND g.uuid NOT in ('" . implode("','", $filterParams['exclude']) . "') " : " WHERE g.uuid NOT in ('" . implode("','", $filterParams['exclude']) . "') ";
                }
            }
            $where .= strlen($where) > 0 ? " AND " : " WHERE ";
            $where .= " g.status = 'Active' AND g.account_id = " . $accountId;
            $sort = " ORDER BY " . $sort;
            $limit = " LIMIT " . $pageSize . " offset " . $offset;
            $countQuery = $this->executeQuerywithParams($cntQuery . $where);
            $count = $countQuery->toArray()[0]['count'];
            $query = "SELECT g.uuid,g.name,a.uuid as accountId,q.uuid as queryId,user.uuid as userId,team.uuid as teamId,t.uuid as targetId FROM `ox_kra` g
                        inner join ox_query q on q.id = g.query_id
                        inner join ox_target t on t.id = g.target_id
                        inner join ox_account a on a.id = g.account_id
                        left join ox_team team on team.id = g.team_id
                        left join ox_user user on user.id = g.user_id " . $where . " " . $sort . " " . $limit;
            $resultSet = $this->executeQuerywithParams($query)->toArray();
            return array('data' => $resultSet, 'total' => $count);
        } catch (Exception $e) {
            throw $e;
        }
        return array('data' => $resultSet, 'total' => $count);
    }

    public function updateKra($id, &$inputData, $accountId = null)
    {
        $data = $inputData;
        if (isset($accountId)) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') && ($accountId != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You have no Access to this API");
            } else {
                $data['account_id'] = $this->getIdFromUuid('ox_account', $accountId);
            }
        }
        $obj = $this->table->getByUuid($id, array());
        if (is_null($obj)) {
            throw new ServiceException("Updating non existent Kra", "non.existent.kra", OxServiceException::ERR_CODE_NOT_FOUND);
        }
        if (isset($accountId)) {
            if ($data['account_id'] != $obj->account_id) {
                throw new ServiceException("Kra does not belong to the account", "Kra.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
            }
        }
        $form = new Kra();
        $data = array_merge($obj->toArray(), $data);
        $data['modified_id'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_modified'] = date('Y-m-d H:i:s');
        $data['userId'] = isset($data['userId']) ? $data['userId'] : null;
        $select = "SELECT id from ox_user where uuid = '" . $data['userId'] . "'";
        $result = $this->executeQueryWithParams($select)->toArray();
        if ($result) {
            $data['user_id'] = $result[0]["id"];
        }
        $form->exchangeArray($data);
        $form->validate();
        try {
            $count = $this->table->save($form);
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function deleteKra($params)
    {
        if (isset($params['accountId'])) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') && ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You do not have permissions to delete the kra");
            } else {
                $accountId = $this->getIdFromUuid('ox_account', $params['accountId']);
            }
        } else {
            $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }
        try {
            $obj = $this->table->getByUuid($params['kraId'], array());
            if (is_null($obj)) {
                throw new ServiceException("Entity not found", "kra.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
            }
            if ($accountId != $obj->account_id) {
                throw new ServiceException("Kra does not belong to the account", "kra.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
            }
            $originalArray = $obj->toArray();
            $form = new Kra();
            $originalArray['status'] = 'Inactive';
            $form->exchangeArray($originalArray);
            $result = $this->table->save($form);
        } catch (Exception $e) {
            throw $e;
        }
        return $result;
    }
}
