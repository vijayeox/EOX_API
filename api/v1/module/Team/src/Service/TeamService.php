<?php
namespace Team\Service;

use Exception;
use Team\Model\Team;
use Team\Model\TeamTable;
use Oxzion\AccessDeniedException;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\Messaging\MessageProducer;
use Oxzion\Security\SecurityManager;
use Oxzion\ServiceException;
use Oxzion\OxServiceException;
use Oxzion\Service\AbstractService;
use Oxzion\Service\AccountService;
use Oxzion\Utils\FileUtils;
use Oxzion\Utils\FilterUtils;
use Oxzion\Utils\UuidUtil;

class TeamService extends AbstractService
{
    private $table;
    private $accountService;
    private $userService;

    public static $fieldName = array('name' => 'ox_user.name', 'id' => 'ox_user.id');

    public function __construct($config, $dbAdapter, TeamTable $table, $accountService, $messageProducer, $userService)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
        $this->messageProducer = $messageProducer;
        $this->accountService = $accountService;
        $this->userService = $userService;
    }

    public function setMessageProducer($messageProducer)
    {
        $this->messageProducer = $messageProducer;
    }

    /**
     * ! DEPRECATED
     * This function will not work as expected since the $params are not even used in the function. Also the parent function which calls this function at this time is deprecated.
     *
     **/
    public function getTeamsforUser($userId, $data)
    {
        try {
            if (isset($params['accountId'])) {
                if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') && ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                    throw new AccessDeniedException("You do not have permissions to get the team list");
                } else {
                    $accountId = $this->getIdFromUuid('ox_account', $params['accountId']);
                }
            } else {
                $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
            }
            $queryString = "select usr_team.id, usr_team.avatar_id, usr_team.team_id, team.name, team.manager_id, team.parent_id from ox_user_team as usr_team left join ox_team as team on usr_team.team_id = team.id";
            $where = "where avatar_id = (SELECT id from ox_user where uuid = '" . $userId . "') AND ox_team.account_id = " . $accountId;
            $order = "order by team.name";
            $resultSet = $this->executeQuerywithParams($queryString, $where, null, $order);
        } catch (Exception $e) {
            throw $e;
        }
        return $resultSet->toArray();
    }
    /**
     * GET Team Service
     * @method getTeam
     * @param $id ID of Team to GET
     * @return array $data
     * <code> {
     *               id : integer,
     *               name : string,
     *               logo : string,
     *               status : String(Active|Inactive),
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created Team.
     */

    public function getTeamByUuid($id, $params)
    {
        if (isset($params['accountId'])) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') && ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You do not have permissions to get the team list");
            } else {
                $accountId = $this->getIdFromUuid('ox_account', $params['accountId']);
            }
        } else {
            $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }
        try {
            $sql = "SELECT g.uuid, g.name, g.description, g.logo, a.uuid as accountId,
                        p.uuid as parentId, u.uuid as managerId 
                        FROM ox_team g 
                        inner join ox_account a on a.id = g.account_id 
                        left join ox_team p on p.id = g.parent_id
                        left join ox_user u on g.manager_id = u.id
                        where g.uuid =:id and g.status = 'Active' and g.account_id = :accountId";
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

    public function createTeam(&$inputData, $files, $accountId = null)
    {
        $data = $inputData;
        if (isset($accountId)) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') && ($accountId != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You do not have permissions create team");
            } else {
                $data['account_id'] = $this->getIdFromUuid('ox_account', $accountId);
            }
        } else {
            $data['account_id'] = AuthContext::get(AuthConstants::ACCOUNT_ID);
            $accountId = AuthContext::get(AuthConstants::ACCOUNT_UUID);
        }
        try {
            $data['name'] = isset($data['name']) ? $data['name'] : null;
            $select = "SELECT name,uuid,status from ox_team where name = '" . $data['name'] . "' AND account_id = " . $data['account_id'];
            $result = $this->executeQuerywithParams($select)->toArray();
            if (count($result) > 0) {
                if ($data['name'] == $result[0]['name'] && $result[0]['status'] == 'Active') {
                    throw new ServiceException("Team already exists", "team.exists", OxServiceException::ERR_CODE_NOT_ACCEPTABLE);
                } elseif ($result[0]['status'] == 'Inactive') {
                    $data['reactivate'] = isset($data['reactivate']) ? $data['reactivate'] : null;
                    if ($data['reactivate'] == 1) {
                        $data['status'] = 'Active';
                        unset($inputData['reactivate']);
                        $accountId = $this->getUuidFromId('ox_account', $data['account_id']);
                        $count = $this->updateTeam($result[0]['uuid'], $data, $files, $accountId);
                        return;
                    } else {
                        throw new ServiceException("Team already exists would you like to reactivate?", "inactive.team.already.exists", OxServiceException::ERR_CODE_NOT_ACCEPTABLE);
                    }
                }
            }
            $form = new Team();
            $data['uuid'] = UuidUtil::uuid();
            $data['created_id'] = AuthContext::get(AuthConstants::USER_ID);
            $data['date_created'] = date('Y-m-d H:i:s');
            $data['managerId'] = isset($data['managerId']) ? $data['managerId'] : null;
            $select = "SELECT id from ox_user where uuid = '" . $data['managerId'] . "'";
            $result = $this->executeQueryWithParams($select)->toArray();
            if ($result) {
                $data['manager_id'] = $result[0]["id"];
            }
            if (isset($data['parentId'])) {
                $data['parent_id'] = $this->getIdFromUuid('ox_team', $data['parentId']);
            }
            $account = $this->accountService->getAccount($data['account_id']);
            $sql = $this->getSqlObject();
            $form->exchangeArray($data);
            $form->validate();
            $this->beginTransaction();
            $count = 0;
            $count = $this->table->save($form);
            if ($count == 0) {
                throw new ServiceException("Failed to create a new entity", "failed.team.create", OxServiceException::ERR_CODE_UNPROCESSABLE_ENTITY);
            }
            $id = $this->table->getLastInsertValue();
            $data['id'] = $id;
            $insert = $sql->insert('ox_user_team');
            $insert_data = array('avatar_id' => $data['manager_id'], 'team_id' => $data['id']);
            $insert->values($insert_data);
            $result = $this->executeUpdate($insert);
            $this->uploadTeamLogo($accountId, $data['uuid'], $files);
            $inputData['uuid'] = $data['uuid'];
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        $this->messageProducer->sendTopic(json_encode(array('teamname' => $data['name'], 'accountName' => $account['name'])), 'GROUP_ADDED');
    }

    public function getTeamLogoPath($accountId, $id, $ensureDir = false)
    {
        $baseFolder = $this->config['UPLOAD_FOLDER'];
        //TODO : Replace the User_ID with USER uuid
        $folder = $baseFolder . "account/" . $accountId . "/team/";
        if (isset($id)) {
            $folder = $folder . $id . "/";
        }
        if ($ensureDir && !file_exists($folder)) {
            FileUtils::createDirectory($folder);
        }
        return $folder;
    }

    /**
     * createUpload
     * Upload files from Front End and store it in temp Folder
     *  @param files Array of files to upload
     *  @return JSON array of filenames
     * ? Not sure what this function is doing, It will cause exception. We need to revisit this and find out what this the purpose of this method
     */
    public function uploadTeamLogo($accountId, $id, $file)
    {
        if (isset($file)) {
            $image = FileUtils::convetImageTypetoPNG($file);
            if ($image) {
                imagepng($image, $this->getTeamLogoPath($accountId, $id, true) . '/logo.png');
                $image = null;
            }
        }
    }
    /**
     * GET Team Service
     * @method getTeam
     * @return array $data
     * <code> {
     *               id : integer,
     *               name : string,
     *               logo : string,
     *               status : String(Active|Inactive),
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created Team.
     */

    public function getTeamList($filterParams = null, $params = null)
    {
        if (isset($params['accountId'])) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') && ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You do not have permissions to get the teams list");
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
        $fieldMap = ['name' => 'g.name', 'description' => 'g.description'];
        try {
            $cntQuery = "SELECT count(g.id) as count FROM `ox_team` g";
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
                        $sort = FilterUtils::sortArray($sort);
                    }
                    $pageSize = $filterArray[0]['take'];
                    $offset = $filterArray[0]['skip'];
                }
                if (isset($filterParams['exclude'])) {
                    $where .= strlen($where) > 0 ? " AND g.uuid NOT in ('" . implode("','", $filterParams['exclude']) . "') " : " WHERE g.uuid NOT in ('" . implode("','", $filterParams['exclude']) . "') ";
                }
            }
            $where .= strlen($where) > 0 ? " AND " : " WHERE ";
            $where .= " g.status = 'Active' AND g.account_id = " . $accountId;
            $sort = " ORDER BY " . $sort;
            $limit = " LIMIT " . $pageSize . " offset " . $offset;
            $resultSet = $this->executeQuerywithParams($cntQuery . $where);
            $count = $resultSet->toArray()[0]['count'];
            $query = "SELECT g.uuid,g.name,parent.uuid as parentId,a.uuid as accountId,u.uuid as managerId,g.description,g.logo 
                        FROM `ox_team` g 
                        INNER JOIN ox_user u on g.manager_id = u.id
                        inner join ox_account a on a.id = g.account_id
                        LEFt OUTER JOIN ox_team parent on parent.id = g.parent_id" . $where . " " . $sort . " " . $limit;
            $resultSet = $this->executeQuerywithParams($query);
            $resultSet = $resultSet->toArray();
        } catch (Exception $e) {
            throw $e;
        }
        return array('data' => $resultSet, 'total' => $count);
    }

    public function updateTeam($id, &$inputData, $files = null, $accountId = null)
    {
        $data = $inputData;
        if (isset($accountId)) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') && ($accountId != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You do not have permissions to edit the team");
            } else {
                $data['account_id'] = $this->getIdFromUuid('ox_account', $accountId);
            }
        }
        $obj = $this->table->getByUuid($id, array());
        if (is_null($obj)) {
            throw new ServiceException("Updating non existent Team", "non.existent.team", OxServiceException::ERR_CODE_NOT_FOUND);
        }
        if (isset($accountId)) {
            if ($data['account_id'] != $obj->account_id) {
                throw new ServiceException("Team does not belong to the account", "Team.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
            }
        }
        $account = $this->accountService->getAccount($obj->account_id);
        $form = new Team();
        $data = array_merge($obj->toArray(), $data);
        $data['modified_id'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_modified'] = date('Y-m-d H:i:s');
        $data['managerId'] = isset($data['managerId']) ? $data['managerId'] : null;
        $user_manager_uuid = $data['managerId'];
        $select = "SELECT id from ox_user where uuid = '" . $data['managerId'] . "'";
        $result = $this->executeQueryWithParams($select)->toArray();
        if ($result) {
            $data['manager_id'] = $result[0]["id"];
        }
        if (isset($data['parentId'])) {
            $data['parent_id'] = $this->getIdFromUuid('ox_team', $data['parentId']);
            $data['parent_id'] = $data['parent_id'] == 0 ? null : $data['parent_id'];
        }else{
            $data['parent_id'] = (isset($data['parent_id']) && !is_numeric($data['parent_id'])) ?  $this->getIdFromUuid('ox_team', $data['parent_id']) : $data['parent_id'];
        }
        $form->exchangeArray($data);
        $form->validate();
        $count = 0;
        try {
            $count = $this->table->save($form);
            if (isset($files)) {
                $this->uploadTeamLogo($account['uuid'], $id, $files);
            }
            $this->messageProducer->sendTopic(json_encode(array('old_teamName' => $obj->name, 'accountName' => $account['name'], 'new_teamName' => $data['name'])), 'GROUP_UPDATED');
            if ($count === 1) {
                $select = "SELECT count(id) as users from ox_user_team where avatar_id =" . $data['manager_id'] . " AND team_id = (SELECT id from ox_team where uuid = '" . $id . "')";
                $query = $this->executeQuerywithParams($select)->toArray();
                if ($query[0]['users'] === '0') {
                    $this->saveUser(["accountId" => $account['uuid'], "teamId" => $id], ["userIdList" => [["uuid" => $user_manager_uuid]]], true);
                }
            } else {
                throw new ServiceException("Failed to Update", "failed.update.team", OxServiceException::ERR_CODE_UNPROCESSABLE_ENTITY);
            }
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function deleteTeam($params)
    {
        if (isset($params['accountId'])) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') && ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You do not have permissions to delete the team");
            } else {
                $accountId = $this->getIdFromUuid('ox_account', $params['accountId']);
            }
        } else {
            $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }
        try {
            $obj = $this->table->getByUuid($params['teamId'], array());
            if (is_null($obj)) {
                throw new ServiceException("Entity not found", "team.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
            }
            if ($accountId != $obj->account_id) {
                throw new ServiceException("Team does not belong to the account", "team.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
            }
            $select = "SELECT count(id) from ox_team where status<> 'Inactive' and parent_id = " . $obj->id;
            $result = $this->executeQuerywithParams($select)->toArray();
            if ($result[0]['count(id)'] > 0) {
                throw new ServiceException("Please remove the child teams before deleting the parent team", "delete.parent.team", OxServiceException::ERR_CODE_NOT_ACCEPTABLE);
            }
            $account = $this->accountService->getAccount($obj->account_id);
            $originalArray = $obj->toArray();
            $form = new Team();
            $originalArray['status'] = 'Inactive';
            $form->exchangeArray($originalArray);
            $result = $this->table->save($form);
        } catch (Exception $e) {
            throw $e;
        }
        $this->messageProducer->sendTopic(json_encode(array('teamName' => $obj->name, 'accountName' => $account['name'])), 'GROUP_DELETED');
        return $result;
    }

    public function getUserList($params, $filterParams = null)
    {
        if (isset($params['accountId'])) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') && ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You do not have permissions to get the user list of team");
            } else {
                $accountId = $this->getIdFromUuid('ox_account', $params['accountId']);
            }
        } else {
            $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }
        $pageSize = 20;
        $offset = 0;
        $where = "";
        try {
            $sort = "ox_user.name";
            $query = "SELECT ox_user.uuid,ox_user.name,oxp.email,case when (ox_team.manager_id = ox_user.id)
                then 1
                end as is_manager";
            $from = " FROM ox_user left join ox_user_team on ox_user.id = ox_user_team.avatar_id left join ox_team on ox_team.id = ox_user_team.team_id left join ox_person oxp on oxp.id=ox_user.person_id";
            $cntQuery = "SELECT count(ox_user.id)" . $from;
            if (isset($filterParams)) {
            if (count($filterParams) > 0 || sizeof($filterParams) > 0) {
                $filterArray = json_decode($filterParams['filter'], true);
                if (isset($filterArray[0]['filter'])) {
                    $filterlogic = isset($filterArray[0]['filter']['logic']) ? $filterArray[0]['filter']['logic'] : "AND";
                    $filterList = $filterArray[0]['filter']['filters'];
                    $where = " WHERE " . FilterUtils::filterArray($filterList, $filterlogic, self::$fieldName);
                }
                if (isset($filterArray[0]['sort']) && count($filterArray[0]['sort']) > 0) {
                    $sort = $filterArray[0]['sort'];
                    $sort = FilterUtils::sortArray($sort, self::$fieldName);
                }
                $pageSize = $filterArray[0]['take'];
                $offset = $filterArray[0]['skip'];
            }
        }
            $where .= strlen($where) > 0 ? " AND ox_team.uuid = '" . $params['teamId'] . "' AND ox_team.status = 'Active' AND ox_team.account_id = " . $accountId : " WHERE ox_team.uuid = '" . $params['teamId'] . "' AND ox_team.status = 'Active' AND ox_team.account_id = " . $accountId;
            // $group = " GROUP BY ox_user.uuid";
            $sort = " ORDER BY " . $sort;
            $limit = " LIMIT " . $pageSize . " offset " . $offset;
            // $this->logger->info("Print query......" . print_r($cntQuery . $where));
            $resultSet = $this->executeQuerywithParams($cntQuery . $where);
            $count = $resultSet->toArray()[0]['count(ox_user.id)'];
            $query = $query . " " . $from . " " . $where . " " .$sort . " " . $limit;
            $resultSet = $this->executeQuerywithParams($query);
        } catch (Exception $e) {
            throw $e;
        }
        return array('data' => $resultSet->toArray(), 'total' => $count);
    }
    public function getSubteams($params, $filterParams = array())
    {
        if (!isset($params['teamId'])) {
            throw new ServiceException("Team not provided", "team.required", OxServiceException::ERR_CODE_NOT_FOUND);
        }
        if (isset($params['accountId'])) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') && ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You do not have permissions to get the teams list");
            } else {
                $accountId = $this->getIdFromUuid('ox_account', $params['accountId']);
            }
        } else {
            $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }
        $where = "";
        $sort = "name";
        $fieldMap = ['name' => 'oxg.name', 'description' => 'oxg.description'];
        $id = $this->getIdFromUuid('ox_team', $params['teamId']);
        if (isset($filterParams['filter'])) {
            $filterArray = json_decode($filterParams['filter'], true);
            if (isset($filterArray[0]['filter'])) {
                $filterlogic = isset($filterArray[0]['filter']['logic']) ? $filterArray[0]['filter']['logic'] : "AND";
                $filterList = $filterArray[0]['filter']['filters'];
                $where = " WHERE " . FilterUtils::filterArray($filterList, $filterlogic, $fieldMap);
            }
            if (isset($filterArray[0]['sort']) && count($filterArray[0]['sort']) > 0) {
                $sort = $filterArray[0]['sort'];
                $sort = FilterUtils::sortArray($sort);
            }
        }
        if (isset($filterParams['exclude'])) {
            $where .= strlen($where) > 0 ? " AND g.uuid NOT in ('" . implode("','", $filterParams['exclude']) . "') " : " WHERE oxg.uuid NOT in ('" . implode("','", $filterParams['exclude']) . "') ";
        }
        $where .= strlen($where) > 0 ? " AND " : " WHERE ";
        $where .= " oxg.parent_id = $id AND oxg.status = 'Active' AND oxg.account_id = " . $accountId;
        $sort = " ORDER BY " . $sort;
        // Done Twice  - one for admin and one for PPM App
        $queryString = "SELECT oxg.name,oxg.description,oxg.uuid,oxg.date_created,sub.uuid as parentId, a.uuid as accountId,sub.uuid as parent_id , u.uuid as managerId 
                        from ox_team as oxg 
                        inner join ox_account a on a.id = oxg.account_id 
                        INNER JOIN ox_team as sub on sub.id = oxg.parent_id 
                        left join ox_user u on oxg.manager_id = u.id".
                        $where . " " . $sort;
        $resultSet = $this->executeQuerywithParams($queryString);
        return $resultSet->toArray();
    }

    public function saveUser($params, $data, $addUsers = false)
    {
        if (isset($params['accountId'])) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') && ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You do not have permissions to add users to team");
            } else {
                $params['accountId'] = $this->getIdFromUuid('ox_account', $params['accountId']);
            }
        }
        $obj = $this->table->getByUuid($params['teamId'], array());
        if (is_null($obj)) {
            $this->logger->info("Invalid team id - " . $params['teamId']);
            throw new ServiceException("Entity not found", "team.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
        }
        if (isset($params['accountId'])) {
            if ($params['accountId'] != $obj->account_id) {
                throw new ServiceException("Team does not belong to the account", "team.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
            }
        }
        $account = $this->accountService->getAccount($obj->account_id);
        if (!isset($data['userIdList']) || empty($data['userIdList'])) {
            throw new ServiceException("Select Users", "select.user", OxServiceException::ERR_CODE_NOT_ACCEPTABLE);
        }
        if ($addUsers) {
            $query = "SELECT ox_user.uuid FROM ox_user_team " .
            "inner join ox_user on ox_user.id = ox_user_team.avatar_id " .
            "where ox_user_team.id = " . $obj->id;
            $teamUsers = $this->executeQuerywithParams($query)->toArray();
            foreach (array_diff(array_column($data['userIdList'], 'uuid'), array_column($teamUsers, 'uuid')) as $userUuid) {
                $teamUsers[] = array('uuid' => $userUuid);
            }
            $data['userIdList'] = $teamUsers;
        }
        $userArray = $this->userService->getUserIdList($data['userIdList']);
        $team_id = $obj->id;
        if ($userArray) {
            $userSingleArray = array_unique(array_map('current', $userArray));
            $queryString = "SELECT ox_user.id, ox_user.username FROM ox_user_team " .
            "inner join ox_user on ox_user.id = ox_user_team.avatar_id " .
            "where ox_user_team.id = " . $team_id .
            " and ox_user_team.avatar_id not in (" . implode(',', $userSingleArray) . ")";
            $deletedUser = $this->executeQuerywithParams($queryString)->toArray();
            $query = "SELECT u.id, u.username FROM ox_user_team ug " .
            "right join ox_user u on u.id = ug.avatar_id and ug.team_id = " . $team_id .
            " where u.id in (" . implode(',', $userSingleArray) . ") and ug.avatar_id is null";
            $insertedUser = $this->executeQuerywithParams($query)->toArray();
            $this->beginTransaction();
            try {
                $delete = "DELETE FROM ox_user_team where avatar_id not in (" . implode(',', $userSingleArray) . ") and team_id = " . $team_id;
                $result = $this->executeQuerywithParams($delete);
                $query = "INSERT into ox_user_team(avatar_id,team_id) SELECT ou.id," . $team_id . " from ox_user as ou LEFT OUTER JOIN ox_user_team as our on ou.id = our.avatar_id AND our.team_id = " . $team_id . " WHERE ou.id in (" . implode(',', $userSingleArray) . ") AND our.team_id  is null";
                $resultInsert = $this->executeQuerywithParams($query);
                $this->commit();
            } catch (Exception $e) {
                $this->rollback();
                throw $e;
            }
            foreach ($deletedUser as $key => $value) {
                $this->messageProducer->sendTopic(json_encode(array('teamName' => $obj->name, 'accountName' => $account['name'], 'username' => $value['username'])), 'USERTOGROUP_DELETED');
            }
            foreach ($insertedUser as $key => $value) {
                $this->messageProducer->sendTopic(json_encode(array('teamName' => $obj->name, 'accountName' => $account['name'], 'username' => $value['username'])), 'USERTOGROUP_ADDED');
            }
            $queryString = "SELECT ox_user.username, up.firstname, up.lastname, up.email 
                            FROM ox_user_team 
                            inner join ox_user on ox_user.id = ox_user_team.avatar_id 
                            inner join ox_person up on up.id = ox_user.person_id
                            where ox_user_team.team_id = " . $team_id;
            $teamUsers = $this->executeQuerywithParams($queryString)->toArray();
            if (count($teamUsers) > 0) {
                $this->messageProducer->sendTopic(json_encode(array('teamName' => $obj->name, 'usernames' => array_column($teamUsers, 'username'))), 'USERTOGROUP_UPDATED');
            }
            return 1;
        }
        throw new ServiceException("Entity not found", "team.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
    }
}
