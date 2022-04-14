<?php
namespace Project\Service;

use Exception;
use Oxzion\AccessDeniedException;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\Messaging\MessageProducer;
use Oxzion\Security\SecurityManager;
use Oxzion\ServiceException;
use Oxzion\OxServiceException;
use Oxzion\Service\AbstractService;
use Oxzion\Service\AccountService;
use Oxzion\Service\UserService;
use Oxzion\Utils\FilterUtils;
use Oxzion\Utils\UuidUtil;
use Project\Model\Project;
use Project\Model\ProjectTable;

class ProjectService extends AbstractService
{
    private $table;
    private $accountService;
    public static $fieldName = array('name' => 'ox_user.name', 'id' => 'ox_user.id');
    public static $projectFields = array("name" => "p.name", "description" => "p.description");

    public function setMessageProducer($messageProducer)
    {
        $this->messageProducer = $messageProducer;
    }

    public function __construct($config, $dbAdapter, ProjectTable $table, $accountService, $userService, MessageProducer $messageProducer)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
        $this->messageProducer = $messageProducer;
        $this->accountService = $accountService;
        $this->userService = $userService;
    }

    public function getProjectList($filterParams = null, $params = null)
    {
        $errorMessage = "You do not have permissions to get the project list";
        $accountId = $this->checkProjectAccount($params, $errorMessage);
        $pageSize = 200;
        $offset = 0;
        $where = "";
        $sort = "name";
        try {
            $cntQuery = "SELECT count(p.id) as total FROM `ox_project` as p ";
            if (count($filterParams) > 0 || sizeof($filterParams) > 0) {
                $filterArray = json_decode($filterParams['filter'], true);
                if (isset($filterArray[0]['filter'])) {
                    $filterlogic = isset($filterArray[0]['filter']['logic']) ? $filterArray[0]['filter']['logic'] : "AND";
                    $filterList = $filterArray[0]['filter']['filters'];
                    $where = " WHERE " . FilterUtils::filterArray($filterList, $filterlogic, self::$projectFields);
                }
                if (isset($filterArray[0]['sort']) && count($filterArray[0]['sort']) > 0) {
                    $sort = $filterArray[0]['sort'];
                    $sort = FilterUtils::sortArray($sort);
                }
                $pageSize = $filterArray[0]['take'];
                $offset = $filterArray[0]['skip'];
            }
            $where .= strlen($where) > 0 ? " AND " : "WHERE ";
            $where .= "isdeleted!=1 AND p.account_id =" . $accountId;

            $sort = " ORDER BY p." . $sort;
            $limit = " LIMIT " . $pageSize . " offset " . $offset;
            $resultSet = $this->executeQuerywithParams($cntQuery . $where);
            $count = $resultSet->toArray()[0]['total'];

            $query = "SELECT p.uuid, p.name, u.uuid as manager_id, p.description FROM `ox_project` as p inner join ox_user as u on u.id = p.manager_id " . $where . " " . $sort . " " . $limit;
            $resultSet = $this->executeQuerywithParams($query);
            $resultSet = $resultSet->toArray();
        } catch (Exception $e) {
            throw $e;
        }
        return array('data' => $resultSet, 'total' => $count);
    }

    /**
     * GET Project Service
     * @method getProject
     * @param $id UUID of Project to GET
     * @return array $data
     * <code> {
     *               id : integer,
     *               name : string,
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created Project.
     */
    public function getProjectByUuid($id, $params)
    {
        $errorMessage = "You do not have permissions to get the project list";
        $accountId = $this->checkProjectAccount($params, $errorMessage);
        
        $sql = $this->getSqlObject();
        $select = $sql->select();
        $select->from('ox_project')
            ->columns(array("*"))
            ->where(array('ox_project.uuid' => $id, 'ox_project.account_id' => $accountId, 'isdeleted' => 0));
        $response = $this->executeQuery($select)->toArray();
        if (empty($response)) {
            return array();
        }
        return $response[0];
    }

    public function createProject(&$inputData, $params = null)
    {
        $data = $inputData;
        $errorMessage = "You do not have permissions create project";
  
        $accountId = $this->checkProjectAccount($params, $errorMessage);
        $data['account_id'] = $accountId;
        $accountId = $this->getUuidFromId('ox_account', $accountId);
        try {
            $data['name'] = isset($data['name']) ? $data['name'] : null;

            $select = "SELECT count(id),name,uuid,isdeleted from ox_project where name = '" . $data['name'] . "' AND account_id = " . $data['account_id'] ." GROUP BY name,uuid,isdeleted ";

            $result = $this->executeQuerywithParams($select)->toArray();
            if (count($result)>0 && $result[0]['count(id)'] > 0) {
                if ($data['name'] == $result[0]['name'] && $result[0]['isdeleted'] == 0) {
                    throw new ServiceException("Project already exists", "project.exists", OxServiceException::ERR_CODE_PRECONDITION_FAILED);
                } elseif ($result[0]['isdeleted'] == 1) {
                    $data['reactivate'] = isset($data['reactivate']) ? $data['reactivate'] : null;
                    if ($data['reactivate'] == 1) {
                        $data['isdeleted'] = 0;
                        $count = $this->updateProject($result[0]['uuid'], $data, $accountId);
                        $inputData['uuid'] = $result[0]['uuid'];
                        return;
                    } else {
                        throw new ServiceException("Project already exists would you like to reactivate?", "project.already.exists", OxServiceException::ERR_CODE_NOT_ACCEPTABLE);
                    }
                }
            }
            $sql = $this->getSqlObject();
            $form = new Project();
            $parent_uuid = null;
            if (isset($data['parentId'])) {
                $parentId = $this->getIdFromUuid('ox_project', $data['parentId']);
                $parent_uuid = $data['parentId'];
                $data['parent_id'] = $parentId;
                $result = $this->getProjectByUuid($parent_uuid, $params);
                $data['parent_manager_id'] = $result['manager_id'];
                if ($parentId == 0) {
                    throw new ServiceException("Project parent is invalid", "project.parent.invalid", OxServiceException::ERR_CODE_NOT_FOUND);
                }
            }else{
                $data['parent_id'] = (isset($data['parent_id']) && !empty($data['parent_id']))? (!is_numeric($data['parent_id']) ? $this->getIdFromUuid('ox_project', $data['parent_id']) : $data['parent_id'] ): null;
            }
            //Additional fields that are needed for the create
            $inputData['uuid'] = $data['uuid'] = UuidUtil::uuid();
            $projectData = $data;
            $projectData['created_by'] = AuthContext::get(AuthConstants::USER_ID);
            $projectData['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
            $projectData['date_created'] = date('Y-m-d H:i:s');
            $projectData['date_modified'] = date('Y-m-d H:i:s');
            $projectData['isdeleted'] = false;
            $select = "SELECT id,username from ox_user where uuid = '" . $projectData['managerId'] . "'";
            $result = $this->executeQueryWithParams($select)->toArray();
            $projectData['manager_id'] = $result[0]["id"];
            $projectData['manager_login'] = $result[0]["username"];
            $account = $this->accountService->getAccount($projectData['account_id']);
            $form->exchangeArray($projectData);
            $form->validate();
            $this->beginTransaction();
            $count = 0;
            $count = $this->table->save($form);
            if ($count == 0) {
                throw new ServiceException("Failed to create a new entity", "failed.project.create");
            }
            $id = $this->table->getLastInsertValue();
            $insert = $sql->insert('ox_user_project');
            $insert_data = array('user_id' => $projectData['manager_id'], 'project_id' => $id);
            $insert->values($insert_data);
            $result = $this->executeUpdate($insert);

            //If the subproject and parent projects have different managers
            //Two users need to be inserted into ox_user_projects
            if (isset($projectData['manager_id']) && isset($data['parent_manager_id']) && $projectData['manager_id'] != $data['parent_manager_id']) {
                $insert = $sql->insert('ox_user_project');
                $insert_data = array('user_id' => $data['parent_manager_id'], 'project_id' => $id);
                $insert->values($insert_data);
                $result = $this->executeUpdate($insert);
            }
            $this->commit();
            if (isset($projectData['name'])) {
                $this->messageProducer->sendTopic(json_encode(array('accountName' => $account['name'], 'projectname' => $projectData['name'], 'description' => $projectData['description'], 'uuid' => $projectData['uuid'], 'parent_identifier' => $parent_uuid, 'manager_login' => $projectData['manager_login'])), 'PROJECT_ADDED');
            }
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function updateProject($id, $data, $accountId = null)
    {
        if (isset($accountId)) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') &&
                ($accountId != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You do not have permissions to edit the project");
            } else {
                $data['account_id'] = $this->getIdFromUuid('ox_account', $accountId);
            }
        }
        $parent_uuid = null;
        if (isset($data['parentId'])) {
            $projParentId = $data['parentId'];
        }
        if (isset($data['parent_id'])) {
            $projParentId = $data['parent_id'];
        }

        if (isset($projParentId)) {
            $parentId = $this->getIdFromUuid('ox_project', $projParentId);
            $parent_uuid = $projParentId;
            $data['parent_id'] = $parentId;
            if ($parentId === 0) {
                throw new ServiceException("Project parent is invalid", "project.parent.invalid", OxServiceException::ERR_CODE_NOT_FOUND);
            }
        } else {
            $data['parent_id'] = NULL;
        }
        
        $obj = $this->table->getByUuid($id, array());
        if (is_null($obj)) {
            throw new ServiceException("Updating non-existent Project", "non.existent.project", OxServiceException::ERR_CODE_NOT_FOUND);
        }
        if (isset($accountId)) {
            if ($data['account_id'] != $obj->account_id) {
                throw new ServiceException("Project does not belong to the account", "project.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
            }
        }
        $form = new Project();
        if (isset($data['managerId'])) {
            $select = "SELECT id,username from ox_user where uuid = '" . $data['managerId'] . "'";
            $result = $this->executeQueryWithParams($select)->toArray();
            $data['manager_id'] = $result[0]["id"];
            $data['manager_login'] = $result[0]["username"];
        }
        $data = array_merge($obj->toArray(), $data); //Merging the data from the db for the ID
        $data['modified_id'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_modified'] = date('Y-m-d H:i:s');
        $form->exchangeArray($data);
        $form->validate();
        $count = 0;
        $account = $this->accountService->getAccount($obj->account_id);
        try {
            $this->beginTransaction();
            $count = $this->table->save($form);
            if ($count === 1) {
                $select = "SELECT count(id) as users from ox_user_project where user_id =" . $data['manager_id'] . " AND project_id = (SELECT id from ox_project where uuid = '" . $id . "')";
                $query = $this->executeQuerywithParams($select)->toArray();
                if ($query[0]['users'] === '0') {
                    $insert = "INSERT INTO ox_user_project (`user_id`,`project_id`) VALUES (" . $data['manager_id'] . ",(SELECT id from ox_project where uuid = '" . $id . "'))";
                    $query1 = $this->executeQuerywithParams($insert);
                }
            } else {
                throw new ServiceException("Failed to Update", "failed.update.project");
            }
            $this->commit();
            if (isset($data['manager_login'])) {
                $this->messageProducer->sendTopic(json_encode(array('accountName' => $account['name'], 'old_projectname' => $obj->name, 'new_projectname' => $data['name'], 'description' => $data['description'], 'uuid' => $data['uuid'],'parent_identifier' => $parent_uuid, 'manager_login' => $data['manager_login'])), 'PROJECT_UPDATED');
            } else {
                $this->messageProducer->sendTopic(json_encode(array('accountName' => $account['name'], 'old_projectname' => $obj->name, 'new_projectname' => $data['name'], 'description' => $data['description'], 'uuid' => $data['uuid'],'parent_identifier' => $parent_uuid)), 'PROJECT_UPDATED');
            }
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function deleteProject($params)
    {
        $errorMessage = "You do not have permissions to delete the project";
        $accountId = $this->checkProjectAccount($params, $errorMessage);
        $obj = $this->table->getByUuid($params['projectUuid'], array());
        if (is_null($obj)) {
            throw new ServiceException("Project not found", "project.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
        }
        if ($accountId != $obj->account_id) {
            throw new ServiceException("Project does not belong to the account", "project.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
        }
        $form = new Project();
        $data = $obj->toArray();
        if (!isset($data['parent_id']) || empty($data['parent_id'])) {
            $select = "SELECT id from ox_project where parent_id = '" . $data['id'] . "' and isdeleted <> 1";
            $result = $this->executeQueryWithParams($select)->toArray();
            if ($result) {
                if (!(isset($params['force_flag']) && ($params['force_flag'] == true || $params['force_flag'] == "true"))) {
                    throw new ServiceException("Project has subprojects", "project.has.subprojects", OxServiceException::ERR_CODE_PRECONDITION_FAILED);
                }
            }
        }
        $data['uuid'] = $params['projectUuid'];
        $data['modified_id'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_modified'] = date('Y-m-d H:i:s');
        $data['isdeleted'] = 1;
        $form->exchangeArray($data);
        $form->validate();
        $count = 0;
        $account = $this->accountService->getAccount($obj->account_id);
        try {
            $this->beginTransaction();
            $count = $this->table->save($form);
            if ($count == 0) {
                throw new ServiceException("Failed to Delete", "failed.project.delete");
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        $this->messageProducer->sendTopic(json_encode(array('accountName' => $account['name'], 'projectname' => $data['name'], 'uuid' => $data['uuid'])), 'PROJECT_DELETED');
        return $count;
    }

    public function getProjectsOfUser($params)
    {
        $errorMessage = "You do not have permissions to get the users of project";
        $accountId = $this->checkProjectAccount($params, $errorMessage);
        try {
            $userId = AuthContext::get(AuthConstants::USER_ID);
            $queryString = "select * from ox_project
            left join ox_user_project on ox_user_project.project_id = ox_project.id";
            $where = "where ox_user_project.user_id = " . $userId . " AND ox_project.account_id=" . $accountId . " AND ox_project.isdeleted!=1";
            $order = "order by ox_project.id";
            $resultSet = $this->executeQuerywithParams($queryString, $where, null, $order);
        } catch (Exception $e) {
            throw $e;
        }
        return $resultSet->toArray();
    }

    public function getProjectsOfUserById($userId, $accountId = null)
    {
        $accountId = isset($accountId) ? $this->getIdFromUuid('ox_account', $accountId) :  AuthContext::get(AuthConstants::ACCOUNT_ID);
        $queryString = "SELECT ox_project.id,ox_project.uuid,ox_project.name,a.uuid as accountId, 
                                ox_project.manager_id,ox_project.description,
                                ox_project.isdeleted,parent.uuid as parent_identifier,
                                ox_project.created_by, ox_user.username as manager_username, 
                                ox_user.uuid as manager_uuid from ox_project
                        inner join ox_account a on a.id = ox_project.account_id
                        inner join ox_user_project on ox_user_project.project_id = ox_project.id 
                        inner join ox_user on ox_project.manager_id = ox_user.id 
                        left join ox_project as parent on ox_project.parent_id = parent.id ";
        $where = "where ox_user_project.user_id ='" . $userId . "' AND ox_project.isdeleted!=1";
        $order = "order by ox_project.id";
        $resultSet = $this->executeQuerywithParams($queryString, $where, null, $order);
        return $resultSet->toArray();
    }

    public function getUserList($params, $filterParams = null)
    {
        $errorMessage = "You do not have permissions to get the user list of project";
        $accountId = $this->checkProjectAccount($params, $errorMessage);
        $pageSize = 200;
        $offset = 0;
        $where = "";
        $sort = "ox_user.name";
        $query = "SELECT ox_user.uuid,ox_user.name,
                                case when (ox_project.manager_id = ox_user.id)
                                    then 1
                                end as is_manager";
        $from = " FROM ox_user left join ox_user_project on ox_user.id = ox_user_project.user_id left join ox_project on ox_project.id = ox_user_project.project_id";
        $cntQuery = "SELECT count(ox_user.id)" . $from;

        if (count($filterParams) > 0 || sizeof($filterParams) > 0) {
            $filterArray = json_decode($filterParams['filter'], true);
            if (isset($filterArray[0]['filter'])) {
                $filterlogic = isset($filterArray[0]['filter']['logic']) ? $filterArray[0]['filter']['logic'] : " AND ";
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
        $where .= strlen($where) > 0 ? " AND " : "WHERE ";
        $where .= "ox_project.uuid = '" . $params['projectUuid'] . "' AND ox_project.isdeleted!=1 AND ox_project.account_id = " . $accountId;
        $sort = " ORDER BY " . $sort;
        $limit = " LIMIT " . $pageSize . " offset " . $offset;
        $resultSet = $this->executeQuerywithParams($cntQuery . " ".$where);
        $count = $resultSet->toArray()[0]['count(ox_user.id)'];
        $query = $query . " " . $from . " " . $where . " " . $sort . " " . $limit;
        $resultSet = $this->executeQuerywithParams($query);
        return array('data' => $resultSet->toArray(),
            'total' => $count);
    }

    //Writing this incase we need to get all projects later. Please do not delete - Brian
    // public function getProject($id)
    // {
    //     $userId = AuthContext::get(AuthConstants::USER_ID);
    //     $queryString = "select * from ox_project
    // left join ox_user_project on ox_user_project.project_id = ox_project.id";
    //     $where = "where ox_user_project.user_id = " . $userId . " AND ox_project.account_id=" . AuthContext::get(AuthConstants::ACCOUNT_ID) . " AND ox_project.id=" . $id;
    //     $order = "order by ox_project.id";
    //     $resultSet = $this->executeQuerywithParams($queryString, $where, null, $order);
    //     return $resultSet->toArray();
    // }

    public function saveUser($params, $data)
    {
        $errorMessage = "You do not have permissions to add users to project";
        $params['account_id'] = $this->checkProjectAccount($params, $errorMessage);
        $obj = $this->table->getByUuid($params['projectId'], array());
        if (is_null($obj)) {
            throw new ServiceException("Project not found", "project.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
        }
        $account = $this->accountService->getAccount($obj->account_id);
        if (isset($params['account_id'])) {
            if ($params['account_id'] != $obj->account_id) {
                throw new ServiceException("Project does not belong to the account", "project.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
            }
        } else {
            throw new ServiceException("Invalid account", "invalid.account", OxServiceException::ERR_CODE_NOT_FOUND);
        }
        if (!isset($data['userIdList']) || empty($data['userIdList'])) {
            throw new ServiceException("Users not selected", "select.user", OxServiceException::ERR_CODE_NOT_FOUND);
        }
        $userArray = $this->userService->getUserIdList($data['userIdList']);
        if (empty($userArray)) {
            throw new ServiceException("Users not found", "project.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
        }
        $projectId = $obj->id;
        $userSingleArray = array_map('current', $userArray);
        $queryString = "SELECT ox_user.id,ox_user.uuid, ox_user.username 
                        FROM ox_user_project 
                        inner join ox_user on ox_user.id = ox_user_project.user_id 
                        where ox_user_project.project_id = $projectId
                         and ox_user_project.user_id not in (" . implode(',', $userSingleArray) . ")";
        $deletedUser = $this->executeQuerywithParams($queryString)->toArray();
        $query = "SELECT u.id,u.uuid, u.username, up.user_id, oup.firstname, oup.lastname, 
                        oup.email , u.timezone 
                    FROM ox_user_project up 
                    right join ox_user u on u.id = up.user_id and up.project_id = $projectId 
                    right join ox_person oup on oup.id = u.person_id 
                    where u.id in (" . implode(',', $userSingleArray) . ") and up.user_id is null";
        $insertedUser = $this->executeQuerywithParams($query)->toArray();
        try {
            $this->beginTransaction();
            $delete = $this->getSqlObject()
                ->delete('ox_user_project')
                ->where(['project_id' => $projectId]);
            $result = $this->executeQuery($delete);
            $query = "INSERT into ox_user_project(user_id,project_id) 
                        (Select ox_user.id, $projectId AS project_id 
                        from ox_user 
                        where ox_user.id in (" . implode(',', $userSingleArray) . "))";
            $resultInsert = $this->runGenericQuery($query);
            if (count($resultInsert) != count($userArray)) {
                throw new ServiceException("Failed to add", "failed.to.add");
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        foreach ($deletedUser as $key => $value) {
            $this->messageProducer->sendTopic(json_encode(array('accountName' => $account['name'], 'projectname' => $obj->name, 'username' => $value['username'])), 'USERTOPROJECT_DELETED');
            $test = $this->messageProducer->sendTopic(json_encode(array('username' => $value['username'], 'projectUuid' => $obj->uuid)), 'DELETION_USERFROMPROJECT');
        }
        foreach ($insertedUser as $key => $value) {
            $this->messageProducer->sendTopic(json_encode(array('accountName' => $account['name'], 'projectname' => $obj->name, 'username' => $value['username'])), 'USERTOPROJECT_ADDED');
            $test = $this->messageProducer->sendTopic(json_encode(array('username' => $value['username'], 'firstname' => $value['firstname'], 'lastname' => $value['lastname'], 'email' => $value['email'], 'timezone' => $value['timezone'], 'projectUuid' => $obj->uuid)), 'ADDITION_USERTOPROJECT');
        }
    }

    private function checkProjectAccount($params, $errorMessage)
    {
        if (isset($params['accountId'])) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') &&
                ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException($errorMessage);
            } else {
                $newaccountId = $this->getIdFromUuid('ox_account', $params['accountId']);
            }
        } else {
            $newaccountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }
        return $newaccountId;
    }

    public function getSubprojects($params)
    {
        if (!isset($params['projectId'])) {
            throw new ServiceException("Project not provided", "project.required", OxServiceException::ERR_CODE_NOT_FOUND);
        }
        $id = $this->getIdFromUuid('ox_project', $params['projectId']);
        // Done Twice  - one for admin and one for PPM App
        $queryString = "SELECT oxp.name,oxp.description,oxp.uuid,oxp.date_created,
                        ou.uuid as managerId,sub.uuid as parentId,ou.uuid as manager_id,sub.uuid as parent_id 
                        from ox_project as oxp 
                        INNER JOIN ox_user as ou on oxp.manager_id = ou.id 
                        INNER JOIN ox_project as sub on sub.id = oxp.parent_id 
                        where oxp.parent_id = $id and oxp.isdeleted <> 1";
        $resultSet = $this->executeQuerywithParams($queryString);
        return $resultSet->toArray();
    }

    /**
     * Delete user from project API
     * ! Deprecated method, not in use at the moment
     * @param $id ID of Project and $data which contains the user info to Delete
     * ? Should we completely remove this method from here, there is another method which does similar functionality
     */

    // public function deleteUser($project_id, $data)
    // {
    //     $queryString = "select id from ox_project";
    //     $order = "order by ox_project.id";
    //     $where = "where ox_project.isdeleted!=1";
    //     $resultSet_temp = $this->executeQuerywithParams($queryString, $where, null, $order)->toArray();
    //     $resultSet = array_map('current', $resultSet_temp);

    //     $query = "select id from user";
    //     $order = "order by user.id";
    //     $resultSet_User_temp = $this->executeQuerywithParams($query, null, null, $order)->toArray();
    //     $resultSet_User = array_map('current', $resultSet_User_temp);
    //     $userArray = json_decode($data['userid'], true);

    //     if ($userArray) {
    //         $userSingleArray = array_map('current', $userArray);
    //         if ((in_array($project_id, $resultSet)) && (count(array_intersect($userSingleArray, $resultSet_User)) == count($userSingleArray))) {
    //             $sql = $this->getSqlObject();
    //             $delete = $sql->delete('ox_user_project');
    //             $delete->where(['user_id' => array_column($userArray, 'id'), 'project_id' => $project_id]);
    //             $result = $this->executeUpdate($delete);
    //         } else {
    //             return 0;
    //         }
    //     } else {
    //         return 0;
    //     }
    //     return 1;
    // }
}
