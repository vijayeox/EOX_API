<?php

namespace Oxzion\Service;

use Exception;
use Oxzion\AccessDeniedException;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\EntityNotFoundException;
use Oxzion\InsertFailedException;
use Oxzion\Messaging\MessageProducer;
use Oxzion\Model\Account;
use Oxzion\Model\User;
use Oxzion\Model\UserTable;
use Oxzion\OxServiceException;
use Oxzion\Search\Elastic\IndexerImpl;
use Oxzion\Security\SecurityManager;
use Oxzion\ServiceException;
use Oxzion\Service\AbstractService;
use Oxzion\Service\AddressService;
use Oxzion\Service\EmailService;
use Oxzion\Service\EmployeeService;
use Oxzion\Service\PersonService;
use Oxzion\Service\RoleService;
use Oxzion\Service\TemplateService;
use Oxzion\Utils\BosUtils;
use Oxzion\Utils\FilterUtils;
use Oxzion\Utils\UuidUtil;
use Oxzion\ValidationException;

class UserService extends AbstractService
{
    const ROLES = '_roles';
    const TEAMS = '_teams';
    const USER_FOLDER = "/users/";
    private $id;

    /**
     * @ignore table
     */
    protected $table;
    private $cacheService;
    private $emailService;
    private $messageProducer;
    private $templateService;
    private $addressService;
    private $personService;
    private $empService;
    public static $userField = array('uuid' => 'ou.uuid', 'username' => 'ou.username', 'firstname' => 'usrp.firstname', 'lastname' => 'usrp.lastname', 'name' => 'ou.name', 'email' => 'usrp.email', 'account_id' => 'ou.account_id', 'date_of_birth' => 'usrp.date_of_birth', 'designation' => 'oxemp.designation', 'phone' => 'usrp.phone', 'address1' => 'oa.address1', 'address2' => 'oa.address2', 'city' => 'oa.city', 'state' => 'oa.state', 'country' => 'oa.country', 'zip' => 'oa.zip', 'id' => 'ou.id', 'gender' => 'usrp.gender', 'website' => 'oxemp.website', 'about' => 'oxemp.about', 'managerid' => 'oxemp.managerid', 'timezone' => 'ou.timezone', 'date_of_join' => 'oxemp.date_of_join', 'interest' => 'oxemp.interest', 'preferences' => 'ou.preferences');

    public function setMessageProducer($messageProducer)
    {
        $this->messageProducer = $messageProducer;
    }

    public function __construct($config, $dbAdapter, UserTable $table = null, AddressService $addressService, EmailService $emailService, TemplateService $templateService, MessageProducer $messageProducer, RoleService $roleService, PersonService $personService, EmployeeService $empService)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
        $this->addressService = $addressService;
        $this->emailService = $emailService;
        $this->templateService = $templateService;
        $this->addressService = $addressService;
        $this->cacheService = CacheService::getInstance();
        $this->messageProducer = $messageProducer;
        $this->roleService = $roleService;
        $this->personService = $personService;
        $this->empService = $empService;
    }

    /**
     * Get User's Account
     * @param string $username Username of user to Login
     * @return integer account_id of the user
     */
    public function getUserAccount($username)
    {
        $sql = $this->getSqlObject();
        $select = $sql->select();
        $select->from('ox_user')
            ->columns(array("account_id"))
            ->where(array('ox_user.username' => $username));
        $response = $this->executeQuery($select)->toArray();
        return $response[0]['account_id'];
    }

    public function getRolesofUser($accountId, $id)
    {
        $userId = $this->getIdFromUuid('ox_user', $id);
        $accountId = $this->getIdFromUuid('ox_account', $accountId);
        $select = "SELECT oro.uuid, oro.name
                    from ox_user_role as ouo
                    inner join ox_account_user au on ouo.account_user_id = au.id
                    inner join ox_role as oro on ouo.role_id = oro.id and oro.account_id = au.account_id
                    where au.user_id = :userId and oro.account_id = :accountId";
        $params = ['userId' => $userId, 'accountId' => $accountId];
        $resultSet = $this->executeQueryWithBindParameters($select, $params)->toArray();
        return $resultSet;
    }

    public function getUserContextDetails($userName, $accountId = null)
    {
        $accountClause = "";
        $params = ['userName' => $userName];
        if ($accountId) {
            $accountClause = " AND acct.id = :accountId";
            $params['accountId'] = $accountId;
        }
        $select = "SELECT ou.id, ou.name, ou.uuid as userId, acct.id as account_id, acct.uuid as accountId, acct.organization_id, org.uuid as organizationId
            from ox_user as ou
            inner join ox_account_user au on au.user_id = ou.id
            inner join ox_account as acct on au.account_id = acct.id
            LEFT OUTER JOIN ox_organization as org on org.id = acct.organization_id
            where ou.username = :userName $accountClause";
        $results = $this->executeQueryWithBindParameters($select, $params)->toArray();
        return (!empty($results)) ? $results[0] : $results;
    }

    public function getTeams($userName)
    {
        $data = $this->getTeamsFromDb($userName);
        return $data;
    }

    public function getTeamsFromDb($id)
    {
        $sql = $this->getSqlObject();
        $select = $sql->select()
            ->from('ox_team')
            ->columns(array('id', 'name'))
            ->join('ox_user_team', 'ox_user_team.team_id = ox_team.id', array())
            ->where(array('ox_user_team.avatar_id' => $id));
        return $this->executeQuery($select)->toArray();
    }

    /**
     * @method createUser
     * @param array $data Array of elements as shown</br>
     * <code>
     *        username : string,
     *        password : string,
     *        firstname : string,
     *        lastname : string,
     *        name : string,
     *        role : string,
     *        email : string,
     *        status : string,
     *        dob : string,
     *        designation : string,
     *        sex : string,
     *        managerId : string,
     * </code>
     * @return array Returns a JSON Response with Status Code and Created User.</br>
     * <code> status : "success|error",
     *        data : array Created User Object
     * </code>
     */
    public function createUser($params, &$userData, $register = false)
    {
        $data = $userData;
        unset($userData['password']);

        if (!$register) {
            if (isset($params['accountId']) && $params['accountId'] != '') {
                if (
                    !SecurityManager::isGranted('MANAGE_INSTALL_APP_WRITE') &&
                    !AuthContext::get(AuthConstants::REGISTRATION) &&
                    (!SecurityManager::isGranted('MANAGE_USER_WRITE') &&
                        (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') &&
                            $params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID)))
                ) {
                    throw new AccessDeniedException("You do not have permissions to create user");
                } else {
                    $data['account_id'] = $this->getIdFromUuid('ox_account', $params['accountId']);
                }
            } else {
                $data['account_id'] = AuthContext::get(AuthConstants::ACCOUNT_ID);
            }
        } else {
            if (isset($params['accountId']) && $account = $this->getIdFromUuid('ox_account', $params['accountId'])) {
                $accountId = $account;
            } else {
                if (isset($data['accountId']) && $data['accountId'] != '') {
                    $accountId = $params['accountId'];
                } else {
                    if (AuthContext::get(AuthConstants::ACCOUNT_ID) != null) {
                        $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
                    }
                }
            }
            $data['account_id'] = $accountId;
        }
        if (!isset($params['accountId']) || $params['accountId'] == '') {
            $params['accountId'] = AuthContext::get(AuthConstants::ACCOUNT_UUID);
        }
        if (isset($data['id'])) {
            unset($data['id']);
        }
        $params = [
            'username' => $data['username'],
            'email' => $data['email'],
        ];

        $select = "SELECT ou.id, ou.uuid, count(ou.id) as account_count, ou.status, ou.username, per.email, GROUP_CONCAT(au.account_id) as account_id
            from ox_user as ou
            inner join ox_account_user as au on au.user_id = ou.id
            inner join ox_person as per on per.id = ou.person_id
            where ou.username = :username OR per.email = :email
            GROUP BY ou.id,ou.uuid,ou.status,ou.username, per.email";
        $result = $this->executeQueryWithBindParameters($select, $params)->toArray();
        /*
        ? Is this required?????
         */
        if (count($result) > 1) {
            throw new ServiceException("Username or Email Exists in other Account", "user.email.exists", OxServiceException::ERR_CODE_PRECONDITION_FAILED);
        }
        if (count($result) == 1) {
            $result[0]['account_id'] = isset($result[0]['account_id']) ? $result[0]['account_id'] : null;
            $accountList = explode(',', $result[0]['account_id']);
            $result[0]['account_count'] = isset($result[0]['account_count']) ? $result[0]['account_count'] : 0;
            if (in_array($data['account_id'], $accountList)) {
                $countval = 0;
                if ($result[0]['username'] == $data['username'] && $result[0]['status'] == 'Active') {
                    throw new ServiceException("Username/Email Exists", "duplicate.username", OxServiceException::ERR_CODE_PRECONDITION_FAILED);
                } elseif ($result[0]['email'] == $data['email'] && $result[0]['status'] == 'Active') {
                    throw new ServiceException("Email Exists", "duplicate.email", OxServiceException::ERR_CODE_PRECONDITION_FAILED);
                } elseif ($result[0]['status'] == "Inactive") {
                    $data['reactivate'] = isset($data['reactivate']) ? $data['reactivate'] : 0;
                    if ($data['reactivate'] == 0) {
                        throw new ServiceException("user already exists and is inactive. Please contact the admin to activate", "user.already.exists", OxServiceException::ERR_CODE_PRECONDITION_FAILED);
                    }
                    $this->reactivateUserAccount($result[0]['id'], $data);
                    return $result[0]['uuid'];
                }
            } else {
                throw new InsertFailedException("Username or Email Exists in other Account", OxServiceException::ERR_CODE_PRECONDITION_FAILED);
            }
        }
        try {
            $accountId = $this->getUuidFromId('ox_account', $data['account_id']);
            if (!isset($data['address1']) || empty($data['address1'])) {
                $addressData = $this->addressService->getOrganizationAddress($accountId);
                $this->unsetAddressData($addressData, $data);
                $data = array_merge($data, $addressData);
            }
            $this->beginTransaction();
            $this->personService->addPerson($data);
            $data['name'] = $data['firstname'] . " " . $data['lastname'];
            $userData['uuid'] = $data['uuid'] = UuidUtil::uuid();
            $data['date_created'] = date('Y-m-d H:i:s');
            $setPasswordCode = UuidUtil::uuid();
            $data['password_reset_code'] = $setPasswordCode;
            $data['created_by'] = AuthContext::get(AuthConstants::USER_ID) ? AuthContext::get(AuthConstants::USER_ID) : 1;
            if (isset($data['date_of_birth'])) {
                $data['date_of_birth'] = date_format(date_create($data['date_of_birth']), "Y-m-d");
            }
            $this->empService->addEmployeeRecord($data);
            if (isset($data['preferences'])) {
                if (is_string($data['preferences'])) {
                    $preferences = json_decode($data['preferences'], true);
                } else {
                    $preferences = $data['preferences'];
                }
                $data['timezone'] = $preferences['timezone'];
                unset($preferences['timezone']);
                $data['preferences'] = json_encode($preferences);
            }
            $password = isset($data['password']) ? $data['password'] : BosUtils::randomPassword();
            if (isset($password)) {
                $data['password'] = md5(sha1($password));
            }
            if (!isset($data['status'])) {
                $data['status'] = 'Active';
            }
            $form = new User($this->table);
            $form->assign($data);
            $form->save();
            $accountId = $this->getUuidFromId('ox_account', $data['account_id']);
            $accountUserId = $this->addUserToAccount($form->id, $form->account_id);
            if (isset($data['project']) && (!empty($data['project']))) {
                $this->addUserToProject($form->uuid, $data['project']);
            }
            if (isset($data['role']) && is_array($data['role'])) {
                $skipRoleByUuid = 1;
                foreach ($data['role'] as $roleItem) {
                    if (is_string($roleItem)) {
                        $roleQuery = "select ox_role.id,ox_role.name,oa.id as appId from ox_role left join ox_app as oa on ox_role.app_id=oa.id where ox_role.account_id=:accountId and ox_role.name =:roleName";
                        $this->logger->info("Executing Query $roleQuery with params--" . print_r(array('accountId' => $data['account_id'], 'roleName' => $roleItem), true));
                        $role = $this->executeQueryWithBindParameters($roleQuery, array('accountId' => $data['account_id'], 'roleName' => $roleItem))->toArray();
                        if (!empty($role) && $role[0]) {
                            if (isset($role[0]['appId']) && $role[0]['appId'] != null) {
                                $this->addUserRole($accountUserId, $roleItem, $role[0]['appId']);
                            } else {
                                $this->addUserRole($accountUserId, $roleItem);
                            }
                        }
                        $skipRoleByUuid = 0;
                    }
                }
                if ($skipRoleByUuid) {
                    $this->addRoleToUser($accountUserId, $data['role'], $form->account_id);
                }
            }

            $this->commit();
            $newUserMailParams = array_merge($data, array(
                'username' => $data['username'],
                'firstname' => $data['firstname'],
                'lastname' => $data['lastname'],
                'name' => $data['name'],
                'email' => $data['email'],
                'accountId' => $accountId,
                'password' => $password,
                'uuid' => $data['uuid'],
                'resetCode' => $setPasswordCode,
                'subject' => isset($data['subject']) ? $data['subject'] : null,
                'REGISTRATION_EMAIL_SEND' => isset($data['REGISTRATION_EMAIL_SEND']) ? $data['REGISTRATION_EMAIL_SEND'] : 'true',
            ));
            if($newUserMailParams['REGISTRATION_EMAIL_SEND'] == 'true'){
                $this->messageProducer->sendTopic(json_encode($newUserMailParams), 'USER_ADDED');
            }
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    private function reactivateUserAccount($userId, $data)
    {
        $data['status'] = 'Active';
        $params = [
            'userId' => $userId,
            'accountId' => $data['account_id'],
        ];
        $select = "SELECT id
                    from ox_account_user
                    where user_id = :userId
                    and account_id = :accountId";
        $accountUsers = $this->executeQueryWithBindParameters($select, $params)->toArray();
        try {
            $this->beginTransaction();
            if (empty($accountUsers)) {
                $accountUserId = $this->addUserToAccount($userId, $data['account_id']);
            } else {
                $accountUserId = $accountUsers[0]['id'];
            }
            $accountUuid = $this->getUuidFromId('ox_account', $data['account_id']);
            $accountId = $data['account_id'];
            $this->updateUser($userId, $data, $accountUuid);
            if (isset($data['role'])) {
                $this->addRoleToUser($accountUserId, $data['role'], $accountId);
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    private function updateUserProjects($userId, $project, $accountId)
    {
        $projectSingleArray = array_map('current', $project);
        $params = [
            'userId' => $userId,
            'accountId' => !is_numeric($accountId) ? $accountId : $this->getUuidFromId('ox_account', $accountId),
        ];
        $delete = "DELETE oxup FROM ox_user_project as oxup
                    inner join ox_project as oxp on oxup.project_id = oxp.id
                    inner join ox_account acct on acct.id = oxp.account_id
                    where oxp.uuid not in
                    ('" . implode("','", $projectSingleArray) . "') and oxup.user_id = :userId and acct.uuid = :accountId";
        $this->executeUpdateWithBindParameters($delete, $params);
        $query = "INSERT into ox_user_project(user_id,project_id)
                    SELECT :userId,oxp.id from ox_project as oxp
                    inner join ox_account acct on acct.id = oxp.account_id
                    LEFT OUTER JOIN ox_user_project as oxup on oxp.id = oxup.project_id
                                                            and oxup.user_id = :userId
                    where oxp.uuid in ('" . implode("','", $projectSingleArray) .
            "') and acct.uuid = :accountId and oxup.user_id is null";
        $this->executeUpdateWithBindParameters($query, $params);
    }

    private function is_numeric_array($array)
    {
        foreach ($array as $key => $value) {
            if (!is_numeric($value)) {
                return false;
            }

        }
        return true;
    }

    private function addRoleToUser($accountUserId, $role, $accountId)
    {
        $roleSingleArray = array_map('current', $role);
        if ($this->is_numeric_array($roleSingleArray)) {
            $roleSingleArray = array_map(function ($item) {
                return $item['uuid'];
            }, $role);
        }

        $params = [
            'accountUserId' => $accountUserId,
            'accountId' => $accountId,
        ];
        $delete = "DELETE our FROM ox_user_role as our
                    inner join ox_role as oro on our.role_id = oro.id where oro.uuid not in ('" . implode("','", $roleSingleArray) . "') and our.account_user_id = :accountUserId and oro.account_id = :accountId";

        $this->executeQuerywithBindParameters($delete, $params);

        $query = "INSERT into ox_user_role(account_user_id,role_id)
                    SELECT :accountUserId, oro.id
                    from ox_role as oro
                    LEFT OUTER JOIN ox_user_role as our on oro.id = our.role_id
                                    and our.account_user_id = :accountUserId
                    where oro.uuid in ('" . implode("','", $roleSingleArray) . "') and oro.account_id = :accountId and our.account_user_id is null";
        $resultInsert = $this->executeQuerywithBindParameters($query, $params);
    }

    private function getRoleIdList($uuidList)
    {
        $uuidList = array_unique(array_map('current', $uuidList));
        $query = "SELECT id from ox_role where uuid in ('" . implode("','", $uuidList) . "')";
        $result = $this->executeQueryWithParams($query)->toArray();
        return $result;
    }

    public function createAdminForAccount($account, $contactPerson, $accountPreferences)
    {
        $params = array();
        $contactPerson = (object) $contactPerson;
        $accountPreferences = (object) $accountPreferences;
        $preferences = array(
            "soundnotification" => "true",
            "emailalerts" => "false",
            "timezone" => isset($accountPreferences->timezone) ? $accountPreferences->timezone : '',
            "dateformat" => isset($accountPreferences->dateformat) ? $accountPreferences->dateformat : '',
        );
        $data = array(
            "firstname" => $contactPerson->firstname,
            "lastname" => $contactPerson->lastname,
            "email" => $contactPerson->email,
            "phone" => isset($contactPerson->phone) ? $contactPerson->phone : '',
            "company_name" => $account['name'],
            "address1" => $account['address1'],
            "address2" => isset($account['address2']) ? $account['address2'] : null,
            "city" => $account['city'],
            "state" => $account['state'],
            "country" => $account['country'],
            "zip" => $account['zip'],
            "preferences" => json_encode($preferences),
            "username" => $contactPerson->username,
            "date_of_birth" => date('Y-m-d'),
            "account_id" => $account['id'],
            "status" => "Active",
            "timezone" => $preferences['timezone'],
            "gender" => " ",
            "password" => BosUtils::randomPassword(),
        );
        if ($account['type'] == Account::BUSINESS) {
            $data["designation"] = "Admin";
            $data["date_of_join"] = date('Y-m-d');
        }
        $params['accountId'] = $account['uuid'];
        $password = $data['password'];
        try {
            $this->beginTransaction();
            $account['addExistingUserToNewAccount'] = isset($account['addExistingUserToNewAccount']) ? $account['addExistingUserToNewAccount'] :false;
            if($account['addExistingUserToNewAccount']){
                $data['uuid'] = $account['userUuid'];
                $data['accountId'] = $account['uuid'];
                $this->addExistingUserToNewAccount($data,$account['userUuid']);
            }else{
                $result = $this->createUser($params, $data);
                $select = "SELECT au.id from `ox_account_user` au
                            inner join ox_user u on u.id = au.user_id
                            where u.uuid = :userId and au.account_id = :accountId";
                $queryParams = ['userId' => $data['uuid'], 'accountId' => $account['id']];
                $resultSet = $this->executeQueryWithBindParameters($select, $queryParams)->toArray();
                $response = $this->addUserRole($resultSet[0]['id'], 'ADMIN');
                if ($response == 2) {
                    //Did not find admin role so add Add all roles of account
                    $roles = $this->getDataByParams('ox_role', array('name'), array('account_id' => $account['id']))->toArray();
                    foreach ($roles as $key => $value) {
                        $this->addUserRole($resultSet[0]['id'], $value);
                    }
                }
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        $data['password'] = $password;
        $data['accountId'] = $account['uuid']; // overriding uuid for Template Service
        $this->messageProducer->sendQueue(json_encode(array(
            'To' => $data['email'],
            'Subject' => $account['name'] . ' created!',
            'body' => $this->templateService->getContent('newAdminUser', $data),
        )), 'mail');
        return $data['uuid'];
    }

    public function addAppRolesToUser($accountUserId, $appId)
    {
        if (isset($appId)) {
            $appId = is_numeric($appId) ? $appId : $this->getIdFromUuid('ox_app', $appId);
            $result = $this->roleService->getRolesByAppId($appId);
            foreach ($result as $role) {
                $this->addUserRole($accountUserId, $role['name'], $appId);
            }
        }
    }

    private function addUserRole($accountUserId, $roleName, $appId = null)
    {
        if (!is_numeric($accountUserId)) {
            throw new ServiceException('Invalid Parameter passed', 'invalid.parameter', OxServiceException::ERR_CODE_PRECONDITION_FAILED);
        }
        $user = $this->getDataByParams('ox_account_user', array('id', 'account_id'), array('id' => $accountUserId))->toArray();
        if ($user) {
            $params = ['accountId' => $user[0]['account_id'], 'roleName' => $roleName];
            if (isset($appId)) {
                $appClause = " And app_id=:appId";
                $params['appId'] = $appId;
            } else {
                $appClause = " And app_id IS NULL";
            }
            $select = "select id,name from ox_role where account_id=:accountId and name =:roleName $appClause";
            $this->logger->info("Executing Query $select with params--" . print_r($params, true));
            $role = $this->executeQueryWithBindParameters($select, $params)->toArray();
            if (!empty($role)) {
                if (!$this->getDataByParams('ox_user_role', array(), array('account_user_id' => $user[0]['id'], 'role_id' => $role[0]['id']))->toArray()) {
                    $data = array(array(
                        'account_user_id' => $user[0]['id'],
                        'role_id' => $role[0]['id'],
                    ));
                    $this->logger->info("Executing Data---" . print_r($data, true));
                    $result = $this->multiInsertOrUpdate('ox_user_role', $data);
                    if ($result->getAffectedRows() == 0) {
                        return $result;
                    }
                    return 1;
                }
            } else {
                return 2;
            }
        }
        return 0;
    }

    private function generateUserIndexForElastic($data)
    {
        $elasticIndex = new IndexerImpl($this->config);
        $appId = 'user';
        $id = $data['id'];
        return $elasticIndex->index($appId, $id, 'type', $data);
    }

    /**
     * @method updateUser
     * @param array $id ID of User to update
     * @param array $data
     * <code>
     *        username : string,
     *        password : string,
     *        firstname : string,
     *        lastname : string,
     *        name : string,
     *        role : string,
     *        email : string,
     *        status : string,
     *        dob : string,
     *        designation : string,
     *        sex : string,
     *        managerId : string,
     * </code>
     * @return array Returns a JSON Response with Status Code and Created User.
     */
    public function updateUser($id, &$data, $accountId = null)
    {
        if (isset($accountId)) {
            if (
                !SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') &&
                ($accountId != AuthContext::get(AuthConstants::ACCOUNT_UUID))
            ) {
                throw new AccessDeniedException("You do not have permissions to assign role to user");
            } else {
                $accountId = $this->getIdFromUuid('ox_account', $accountId);
            }
        }
        $form = new User($this->table);
        if (is_numeric($id)) {
            $form->loadById($id);
        } else {
            $form->loadByUuid($id);
        }

        if (isset($accountId)) {
            $select = "SELECT account_id from ox_account_user where user_id = " . $form->id;
            $result = $this->executeQuerywithParams($select)->toArray();
            $acctArray = array_map('current', $result);
            if (!in_array($accountId, $acctArray)) {
                throw new ServiceException('User does not belong to the Account', 'user.not.found', OxServiceException::ERR_CODE_PRECONDITION_FAILED);
            }
        }
        $userdata = array_merge($form->getProperties(), $data); //Merging the data from the db for the ID
        if (isset($userdata['date_of_birth'])) {
            $userdata['date_of_birth'] = date_format(date_create($userdata['date_of_birth']), "Y-m-d");
        }
        try {
            $this->beginTransaction();
            $this->logger->info("USER-DATA--------\n" . print_r($userdata, true));
            if (!isset($userdata['address1']) || empty($userdata['address1'])) {
                $accountId = AuthContext::get(AuthConstants::ACCOUNT_UUID);
                $addressData = $this->addressService->getOrganizationAddress($accountId);
                $this->unsetAddressData($addressData, $userdata);
                $userdata = array_merge($userdata, $addressData);
            }
            $this->personService->updatePerson($userdata['person_id'], $userdata);
            $userdata['name'] = $userdata['firstname'] . " " . $userdata['lastname'];
            $userdata['uuid'] = $form->uuid;

            $userdata['modified_id'] = isset($userdata['modified_id']) ? $userdata['modified_id'] : AuthContext::get(AuthConstants::USER_ID);
            $userdata['date_modified'] = date('Y-m-d H:i:s');
            if (isset($userdata['preferences'])) {
                if (!is_array($userdata['preferences'])) {
                    $preferences = json_decode($userdata['preferences'], true);
                } else {
                    $preferences = $userdata['preferences'];
                }
                if (isset($preferences['timezone'])) {
                    $userdata['timezone'] = $preferences['timezone'];
                    unset($preferences['timezone']);
                }
                $userdata['preferences'] = json_encode($preferences);
            }
            $form->assign($userdata);
            $form->save();
            if (isset($data['role'])) {
                $accountUsers = $this->getDataByParams('ox_account_user', array('id'), array('user_id' => $form->id, 'account_id' => $form->account_id))->toArray();
                $this->addRoleToUser($accountUsers[0]['id'], $data['role'], $form->account_id);
            }
            $this->empService->updateEmployeeDetails($userdata);
            if (isset($data['project']) && $userdata['account_id']) {
                $this->updateUserProjects($form->id, $data['project'], $userdata['account_id']);
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    private function getAccount($id)
    {
        $select = "SELECT oxo.id,oxo.name from ox_account oxo where oxo.id =:id";
        $params = array("id" => $id);
        $response = $this->executeQueryWithBindParameters($select, $params)->toArray();
        if (empty($response)) {
            throw new EntityNotFoundException("Invalid Account");
        }
        return $response[0];
    }

    /**
     * Delete User Service
     * @method deleteUser
     * @param $id ID of User to Delete
     * @return array success|failure response
     */
    public function deleteUser($id)
    {
        if (isset($id['accountId'])) {
            if (
                !SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') &&
                ($id['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))
            ) {
                throw new AccessDeniedException("You do not have permissions to delete the user");
            } else {
                $accountId = $this->getIdFromUuid('ox_account', $id['accountId']);
            }
        } else {
            $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }
        $form = new User($this->table);
        $form->loadByUuid($id['userId']);
        $select = "SELECT account_id from ox_account_user where user_id = " . $form->id;
        $result = $this->executeQuerywithParams($select)->toArray();
        $acctArray = array_map('current', $result);
        if (!in_array($accountId, $acctArray)) {
            throw new ServiceException('User does not belong to the account', 'user.not.found', OxServiceException::ERR_CODE_PRECONDITION_FAILED);
        }
        $select = "SELECT contactid from ox_account where id = " . $accountId;
        $result1 = $this->executeQuerywithParams($select)->toArray();
        if ($result1[0]['contactid'] == $form->id) {
            throw new ServiceException('Not allowed to delete Admin user', 'admin.user', OxServiceException::ERR_CODE_FORBIDDEN);
        }
        $select = "SELECT count(id) from ox_team where manager_id = " . $form->id;
        $result2 = $this->executeQuerywithParams($select)->toArray();
        if ($result2[0]['count(id)'] > 0) {
            throw new ServiceException('Not allowed to delete the team manager', 'team.manager', OxServiceException::ERR_CODE_FORBIDDEN);
        }
        $select = "SELECT count(id) from ox_project where manager_id = " . $form->id;
        $result3 = $this->executeQuerywithParams($select)->toArray();
        if ($result3[0]['count(id)'] > 0) {
            throw new ServiceException('Not allowed to delete the project manager', 'project.manager', OxServiceException::ERR_CODE_FORBIDDEN);
        }
        $account = $this->getAccount($form->account_id);
        $queryString = "SELECT e.* FROM ox_employee e
        INNER JOIN ox_user u ON u.person_id = e.manager_id
        WHERE u.uuid = :userId";
        $params = ['userId' => $id['userId']];
        $resultSet = $this->executeQueryWithBindParameters($queryString, $params)->toArray();
        if (isset($resultSet[0]['manager_id'])) {
            $sql = $this->getSqlObject();
            $updatedData['manager_id'] = null;
            $update = $sql->update('ox_employee')->set($updatedData)
                ->where(array('ox_employee.manager_id' => $resultSet[0]['manager_id']));
            $this->executeUpdate($update);
        }
        $originalArray = array();
        $originalArray['status'] = 'Inactive';
        $originalArray['modified_id'] = AuthContext::get(AuthConstants::USER_ID);
        $originalArray['date_modified'] = date('Y-m-d H:i:s');
        $form->assign($originalArray);
        try {
            $this->beginTransaction();
            $form->save();
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        $payload = json_encode(array('username' => $form->username, 'accountName' => $account['name']));
        $this->messageProducer->sendTopic($payload, 'USER_DELETED');
    }

    public function getUserIdList($uuidList)
    {
        $uuidList = array_unique(array_map('current', $uuidList));
        $query = "SELECT id from ox_user where uuid in ('" . implode("','", $uuidList) . "')";
        $result = $this->executeQueryWithParams($query)->toArray();
        return $result;
    }

    /**
     * GET List User API
     * @api
     * @link /user
     * @method GET
     * @return array $dataget list of Users
     */
    public function getUsers($filterParams = null, $baseUrl = '', $params = null)
    {
        if (isset($params['accountId'])) {
            if (
                !SecurityManager::isGranted('MANAGE_ACCOUNT_READ') &&
                ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))
            ) {
                throw new AccessDeniedException("You do not have permissions get the users list");
            } else {
                $accountId = $this->getIdFromUuid('ox_account', $params['accountId']);
            }
        } else {
            $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }
        $where = $pageSize = $offset = $limit = "";
        $sort = "name";
        $select = "SELECT ou.uuid, ou.username, ou.name, ou.icon, ou.timezone, ou.preferences,
        	au.uuid as accountId, usrp.firstname, usrp.lastname, usrp.email, usrp.date_of_birth, usrp.phone, usrp.gender,
        	oxemp.designation, oxemp.website, oxemp.about, oxemp.id as employee_id, oxemp.date_of_join, oxemp.interest,
        	oa.address1, oa.address2, oa.city, oa.state, oa.country, oa.zip,
        	manager_user.uuid as managerId,manager_user.name as manager_name";
        $from = " FROM `ox_user` as ou
                  inner join ox_account au on au.id = ou.account_id
                  join ox_person usrp on usrp.id = ou.person_id
                  inner join ox_employee oxemp on oxemp.person_id = usrp.id
                  LEFT JOIN ox_employee man on man.id = oxemp.manager_id
                  LEFT JOIN ox_user manager_user on manager_user.person_id = man.person_id
                  left join ox_address as oa on usrp.address_id = oa.id ";
        $cntQuery = "SELECT count(ou.id) as count " . $from;
        if (count($filterParams) > 0 || sizeof($filterParams) > 0) {
            if (isset($filterParams['filter'])) {
                $filterArray = json_decode($filterParams['filter'], true);
                if (isset($filterArray[0]['filter'])) {
                    $filterlogic = isset($filterArray[0]['filter']['logic']) ? $filterArray[0]['filter']['logic'] : "AND";
                    $filterList = $filterArray[0]['filter']['filters'];
                    $clause = FilterUtils::filterArray($filterList, $filterlogic, self::$userField);
                    if (!empty($clause)) {
                        $where = " WHERE " . $clause;
                    }
                }
                if (isset($filterArray[0]['sort']) && count($filterArray[0]['sort']) > 0) {
                    $sort = $filterArray[0]['sort'];
                    $sort = FilterUtils::sortArray($sort, self::$userField);
                }
                $pageSize = isset($filterArray[0]['take']) ? $filterArray[0]['take'] : 20;
                $offset = isset($filterArray[0]['skip']) ? $filterArray[0]['skip'] : 0;
                $limit = " LIMIT " . $pageSize . " offset " . $offset;
            }
            if (isset($filterParams['exclude'])) {
                $where .= (strlen($where) > 0 ? " AND " : " WHERE ") . "ou.uuid NOT in ('" . implode("','", $filterParams['exclude']) . "') ";
            }
        }

        $where .= (strlen($where) > 0 ? " AND " : " WHERE ") . "ou.status = 'Active' AND ou.account_id = " . $accountId;
        if (!empty($sort)) {
            $sort = " ORDER BY " . $sort;
        }
        $resultSet = $this->executeQuerywithParams($cntQuery . $where);
        $count = $resultSet->toArray()[0]['count'];
        $query = $select . " " . $from . " " . $where . " " . $sort . " " . $limit;
        $this->logger->info("Executing GET LIST Query - $query");
        $resultSet = $this->executeQuerywithParams($query);
        $result = $resultSet->toArray();
        for ($x = 0; $x < sizeof($result); $x++) {
            $result[$x]['preferences'] = json_decode($result[$x]['preferences'], true);
            $result[$x]['preferences']['timezone'] = $result[$x]['timezone'];
            $result[$x]['icon'] = $baseUrl . "/user/profile/" . $result[$x]['uuid'];
        }
        return array(
            'data' => $result,
            'total' => $count,
        );
    }

    /**
     * GET User Service
     * @method  getUser
     * @param $id ID of User to View
     * @return array $data
     * @return array Returns a JSON Response with Status Code and Created User.
     */
    public function getUser($id, $getAllFields = false)
    {
        $id = (is_numeric($id)) ? $id : $this->getIdFromUuid('ox_user', $id);
        $select = "SELECT ou.uuid,ou.username,per.firstname,per.lastname,ou.name,
                          per.email,au.uuid as accountId,ou.icon,oa.address1,oa.address2,oa.city,
                          oa.state, oa.country,oa.zip,per.date_of_birth,oxemp.designation,
                          per.phone,per.gender,oxemp.website,oxemp.about,
                          manager_user.uuid as managerId,manager_user.name as manager_name,ou.timezone,oxemp.date_of_join,
                          oxemp.interest,ou.preferences,ou.password, ou.password_reset_expiry_date,
                          ou.password_reset_code, ou.cleared_browser_cache
                    from ox_user as ou
                    inner join ox_account au on au.id = ou.account_id
                    inner join ox_person as per on per.id = ou.person_id
                    left join ox_employee as oxemp on oxemp.person_id = per.id
                    left join ox_address as oa on per.address_id = oa.id
                    left join ox_employee as man on man.id = oxemp.manager_id
                    left join ox_user as manager_user on manager_user.person_id = man.person_id
                    where ou.id =" . $id . " and ou.status = 'Active'";
        $response = $this->executeQuerywithParams($select)->toArray();
        if (empty($response)) {
            return $response;
        }
        $result = $response[0];
        if (!$getAllFields) {
            unset($result['password']);
            unset($result['password_reset_expiry_date']);
            unset($result['password_reset_code']);
        }
        $activeAccount = $this->getActiveAccount(AuthContext::get(AuthConstants::ACCOUNT_ID));
        if ($activeAccount) {
            $activeAccount['preferences'] = json_decode($activeAccount['preferences'], true);
            $result['active_account'] = $activeAccount;
            $result['accountId'] = $activeAccount['accountId'];
            $result['id'] = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }
        $result['preferences'] = json_decode($response[0]['preferences'], true);
        $result['preferences']['timezone'] = $response[0]['timezone'];
        return $result;
    }

    public function getUserByUuid($uuid)
    {
        $select = "SELECT id from `ox_user` where uuid = '" . $uuid . "'";
        $result = $this->executeQueryWithParams($select)->toArray();
        if (!empty($result)) {
            return $result[0]['id'];
        } else {
            return null;
        }
    }

    public function getActiveAccount($accountId)
    {
        $select = "SELECT au.uuid as accountId, au.name, au.preferences
                    from ox_account au
                    where au.id =:id";
        $params = array("id" => $accountId);
        $response = $this->executeQueryWithBindParameters($select, $params)->toArray();
        if (!empty($response)) {
            return $response[0];
        }

        return null;
    }

    public function getAccounts($userId)
    {
        $select = "SELECT uuid as accountId, au.name
                    from ox_account au
                    INNER join ox_account_user oau on oau.account_id = au.id
                    where oau.user_id = :user_id AND au.status = 'Active'";
        $params = array("user_id" => $userId);
        $response = $this->executeQueryWithBindParameters($select, $params)->toArray();
        if (!empty($response)) {
            return $response;
        }
        return null;
    }

    public function hasAccount(&$accountId, $userId = null)
    {
        if (!is_numeric($accountId)) {
            $accountId = $this->getIdFromUuid('ox_account', $accountId);
        }
        if ($userId === null) {
            $userId = AuthContext::get(AuthConstants::USER_ID);
        } elseif (!is_numeric($userId)) {
            $userId = $this->getIdFromUuid('ox_user', $userId);
        }
        $select = "SELECT count(id) as count
        from ox_account_user oau
        where oau.user_id = :user_id AND oau.account_id = :account_id";
        $params = array("user_id" => $userId, 'account_id' => $accountId);
        $response = $this->executeQueryWithBindParameters($select, $params)->toArray();
        return ($response[0]['count']) ? true : false;
    }

    public function getPrivileges($userId, $accountId = null)
    {
        if (!isset($accountId)) {
            $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }
        $data = $this->getPrivilegesFromDb($userId, $accountId);
        return $data;
    }

    private function getPrivilegesFromDb($userId, $accountId)
    {
        $query = "SELECT privilege_name, permission
                    from ox_role_privilege rp
                    INNER join ox_user_role ur on ur.role_id = rp.role_id
                    INNER JOIN ox_account_user au on au.id = ur.account_user_id
                                                  and rp.account_id = au.account_id
                    where au.user_id = :userId and rp.account_id = :accountId";
        $params = ['userId' => $userId, 'accountId' => $accountId];
        $results = $this->executeQueryWithBindParameters($query, $params)->toArray();
        $permissions = array();
        foreach ($results as $key => $value) {
            $permissions = array_merge($permissions, $this->addPermissions($value['privilege_name'], $value['permission']));
        }
        return $permissions;
    }

    public function addPermissions($privilegeName, $permission)
    {
        $permissionArray = array();
        if (($permission & 1) != 0) {
            $permissionData = $privilegeName . "_" . 'READ';
            $permissionArray[$permissionData] = true;
        }
        if (($permission & 2) != 0) {
            $permissionData = $privilegeName . "_" . 'WRITE';
            $permissionArray[$permissionData] = true;
        }
        if (($permission & 4) != 0) {
            $permissionData = $privilegeName . "_" . 'CREATE';
            $permissionArray[$permissionData] = true;
        }
        if (($permission & 8) != 0) {
            $permissionData = $privilegeName . "_" . 'DELETE';
            $permissionArray[$permissionData] = true;
        }
        return $permissionArray;
    }

    /**
     * GET User Service
     * @method  getUserWithMinimumDetails
     * @param $id ID of User to View
     * @return array with minumum information required to use for the User.
     * @return array Returns a JSON Response with Status Code and Created User.
     */
    public function getUserWithMinimumDetails($id, $accountId = null)
    {
        $accountId = ($accountId != null) ? $accountId : AuthContext::get(AuthConstants::ACCOUNT_UUID);
        $id = (is_numeric($id)) ? $id : $this->getIdFromUuid('ox_user', $id);
        $select = "SELECT ou.id,ou.password, ou.uuid, ou.username,
                        per.firstname,per.lastname,ou.name,per.email,oxemp.designation,
                        au.uuid as accountId, per.phone, per.date_of_birth, oxemp.date_of_join,
                        oa.address1, oa.address2, oa.city, oa.state, oa.country, oa.zip,
                        oxemp.website, oxemp.about, per.gender,
                        manager_user.uuid as managerId,manager_user.name as manager_name,
                        oxemp.interest,ou.icon,ou.preferences,ou.cleared_browser_cache
                    from ox_user as ou
                    inner join ox_account_user oau on oau.user_id = ou.id
                    inner join ox_account au on au.id = oau.account_id
                    inner join ox_person as per on per.id = ou.person_id
                    inner join ox_employee as oxemp on oxemp.person_id = per.id
                    left join ox_address as oa on per.address_id = oa.id
                    left join ox_employee man on man.id = oxemp.manager_id
                    left join ox_user manager_user on manager_user.person_id = man.person_id
                    where au.uuid = :accountId AND ou.id = :userId AND ou.status = 'Active'";
        $params = ['accountId' => $accountId, 'userId' => $id];
        $response = $this->executeQueryWithBindParameters($select, $params)->toArray();
        if (empty($response)) {
            throw new EntityNotFoundException("User not found for userId - $id");
        }
        $result = $response[0];
        $result['preferences'] = json_decode($response[0]['preferences'], true);
        if (isset($result['timezone'])) {
            $result['preferences']['timezone'] = $result['timezone'];
        }
        return $result;
    }

    /**
     * GET User Profile
     * @method  getUserBaseProfile
     * @param $username USERNAME or EMAIL  of User to View
     * @return array with base information required to use for the User login.
     * @return array Returns a JSON Response with Status Code and Existing User data.
     */
    public function getUserBaseProfile($username)
    {
        $select = "SELECT ou.uuid,ou.username,per.firstname,per.lastname,
                          ou.name,per.email,au.uuid as accountId,
                          oa.address1,oa.address2,oa.city,oa.state,oa.country,oa.zip
                    from ox_user as ou
                    inner join ox_account au on au.id = ou.account_id
                    inner join ox_person per on per.id = ou.person_id
                    LEFT join ox_address as oa on per.address_id = oa.id
                    where ou.username = :username OR per.email = :username";
        $params = ['username' => $username];
        $response = $this->executeQueryWithBindParameters($select, $params)->toArray();
        if (!empty($response)) {
            return $response[0];
        }
        throw new EntityNotFoundException("User not found with username or email for $username");
    }

    private function addUserToProject($userId, $projectList)
    {
        $sql = $this->getSqlObject();
        $queryString = "SELECT id from ox_user
                        where uuid = :userId and status='Active'";
        $params = ["userId" => $userId];
        $resultSet = $this->executeQueryWithBindParameters($queryString, $params)->toArray();
        if (empty($resultSet)) {
            throw new EntityNotFoundException("Invalid User");
        }
        $userId = $resultSet[0]['id'];
        $oxUserProject = array();
        foreach ($projectList as $key => $value) {
            if (!empty($value)) {
                $oxUserProject[$key]['user_id'] = $userId;
                $oxUserProject[$key]['project_id'] = $this->getIdFromUuid('ox_project', $value['uuid']);
            }
        }
        if (!empty($oxUserProject)) {
            $this->multiInsertOrUpdate('ox_user_project', $oxUserProject, array());
        }
    }

    public function removeUserFromTeam($userid)
    {
        $sql = $this->getSqlObject();
        $queryString = "select avatar_id from ox_user_team";
        $where = "where avatar_id =" . $userid;
        $resultSet = $this->executeQuerywithParams($queryString, $where, null, null)->toArray();
        if (empty($resultSet)) {
            throw new EntityNotFoundException("User not in team");
        }
        $delete = $sql->delete('ox_user_team');
        $delete->where(['avatar_id' => $userid]);
        $result = $this->executeUpdate($delete);
    }

    public function removeUserFromProject($userid, $projectid)
    {
        $sql = $this->getSqlObject();
        $queryString = "select user_id from ox_user_project";
        $where = "where user_id =" . $userid . " and project_id =" . $projectid;
        $resultSet = $this->executeQuerywithParams($queryString, $where, null, null)->toArray();
        if (empty($resultSet)) {
            throw new EntityNotFoundException("User not in project");
        }
        $delete = $sql->delete('ox_user_project');
        $delete->where(['user_id' => $userid, 'project_id' => $projectid]);
        $result = $this->executeUpdate($delete);
    }

    public function getUserNameFromAuth()
    {
        return $userName = AuthContext::get(AuthConstants::USERNAME);
    }

    // check this
    /**
     * @param $searchVal
     * @return array
     */
    public function getUserBySearchName($searchVal)
    {
        $query = "SELECT u.uuid, p.firstname, p.lastname
                    FROM ox_user u
                    inner join ox_person p on p.id = u.person_id
                    where p.firstname LIKE :search OR lastname LIKE :search";
        $params = ['search' => "%$searchVal%"];
        return $this->executeQueryWithBindParameters($query, $params)->toArray();
    }

    /**
     * @param $userName
     * @return array|\Zend\Db\ResultSet\ResultSet
     */
    public function getUserDetailsbyUserName($userName, $columns = null)
    {
        $whereCondition = "username = '" . $userName . "'";
        if ($columns) {
            $columnList = $columns;
        } else {
            $columnList = array('*');
        }
        return $userDetail = $this->getUserContextDetailsByParams($whereCondition, $columnList);
    }

    /**
     * @param $whereCondition
     * @param $columnList
     * @return array|\Zend\Db\ResultSet\ResultSet
     */
    public function getUserContextDetailsByParams($whereCondition, $columnList)
    {
        $sql = $this->getSqlObject();
        $select = $sql->select()
            ->from('ox_user')
            ->columns($columnList)
            ->where(array($whereCondition))
            ->limit(1);
        $results = $this->executeQuery($select);
        $results = $results->toArray();
        if (!empty($results)) {
            $results = $results[0];
        }
        return $results;
    }

    private function addUserToAccount($userId, $accountId)
    {
        $this->logger->info("USERID--- $userId with Account --- $accountId");
        $user = $this->getDataByParams('ox_user', array('id', 'username'), array('id' => $userId))->toArray();
        if (empty($user)) {
            throw new EntityNotFoundException("User you are trying to add is invalid");
        }
        $account = $this->getDataByParams('ox_account', array('id', 'name'), array('id' => $accountId, 'status' => 'Active'))->toArray();
        if (empty($account)) {
            throw new EntityNotFoundException("Trying to add user to an invalid Account");
        }
        $accountUsers = $this->getDataByParams('ox_account_user', array(), array('user_id' => $userId, 'account_id' => $accountId))->toArray();
        if (!empty($accountUsers)) {
            throw new InsertFailedException("User already part of the account");
        }
        $params = array(
            'userId' => $userId,
            'accountId' => $accountId,
            'default' => 1,
        );
        $query = "INSERT into ox_account_user (`user_id`, `account_id`, `default`)
                  VALUES (:userId, :accountId, :default)";
        $result = $this->executeUpdateWithBindParameters($query, $params);
        $accountUserId = $result->getGeneratedValue();
        $message = json_encode(array('accountName' => $account[0]['name'], 'status' => 'Active', 'username' => $user[0]["username"]));
        $this->logger->info("USERTOACCOUNT_ADDED-----\n", print_r($message, true));
        $this->messageProducer->sendTopic($message, 'USERTOACCOUNT_ADDED');
        return $accountUserId;
    }

    public function getUserAppsAndPrivileges()
    {
        $privilege = AuthContext::get(AuthConstants::PRIVILEGES);
        foreach ($privilege as $key => $value) {
            $privilege[$key] = strtolower($value);
            $privilege[$key] = ucfirst($privilege[$key]);
            $privilege[$key] = implode('_', array_map('ucfirst', explode('_', $privilege[$key])));
            $privilege[$key] = str_replace('_', '', $privilege[$key]);
            $privilege[$key] = 'priv' . $privilege[$key] . ':true';
        }
        $whiteListedApps = $this->getAppsByUserId();
        $responseArray = array('privilege' => $privilege, 'whiteListedApps' => $whiteListedApps);
        return $responseArray;
    }

    /**
     * @return \Oxzion\Utils\Array
     */
    public function getAppsWithoutAccessForUser()
    {
        $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        $userId = AuthContext::get(AuthConstants::USER_ID);
        $query = "SELECT uuid, name
                    FROM (
                        SELECT DISTINCT app.uuid, app.name, COUNT( NULLIF(urp.privilege_name, NULL) ) AS app_count
                        FROM (
                            SELECT DISTINCT ap.uuid, ap.name, op.name AS privilege_name, ar.account_id
                            FROM ox_app AS ap
                            INNER JOIN ox_app_registry AS ar ON ap.id = ar.app_id
                            INNER JOIN ox_privilege AS op ON ar.app_id = op.app_id
                            WHERE ar.account_id = :accountId
                        ) app
                        LEFT JOIN(
                            SELECT DISTINCT orp.privilege_name
                            FROM ox_role_privilege AS orp
                            INNER JOIN ox_user_role AS ou ON orp.role_id = ou.role_id
                            INNER JOIN ox_account_user au ON au.id = ou.account_user_id AND au.account_id = orp.account_id
                            WHERE au.user_id = :userId AND orp.account_id = :accountId
                        ) urp ON app.privilege_name = urp.privilege_name
                        GROUP BY app.uuid, app.name
                    ) a
                    WHERE a.app_count = 0
                UNION
                    SELECT oa.uuid, oa.name
                    FROM ox_app oa
                    LEFT JOIN `ox_app_registry` ar ON oa.id = ar.app_id AND ar.account_id = :accountId
                    WHERE account_id IS NULL";
        $params = ['userId' => $userId, 'accountId' => $accountId];
        $this->logger->info("Query - $query with params - " . print_r($params, true));
        $result = $this->executeQueryWithBindParameters($query, $params);
        $result = $result->toArray();
        $arr = array();
        for ($i = 0; $i < sizeof($result); $i++) {
            $arr[$result[$i]['name']] = $result[$i]['uuid'];
        }
        return $arr;
    }

    public function resetPassword($data)
    {
        $resetCode = $data['password_reset_code'];
        $password = md5(sha1($data['new_password']));
        $expiry = date("Y-m-d H:i:s");
        $query = "SELECT id from ox_user
                    where (password_reset_expiry_date > :expiry OR
                    password_reset_expiry_date is NULL) and
                    password_reset_code = :resetCode";
        $params = ['expiry' => $expiry, 'resetCode' => $resetCode];
        $result = $this->executeQueryWithBindParameters($query, $params);
        $result = $result->toArray();
        if (empty($result)) {
            throw new EntityNotFoundException("Invalid or Expired Reset Code");
        }
        unset($params['expiry']);
        $params['password'] = $password;
        $query = "UPDATE ox_user set password = :password, password_reset_code = NULL, password_reset_expiry_date = NULL where password_reset_code = :resetCode";
        $this->executeUpdateWithBindParameters($query, $params);
    }

    public function sendResetPasswordCode($username)
    {
        $resetPasswordCode = UuidUtil::uuid();
        $userDetails = $this->getUserBaseProfile($username);
        $userRecord = $userDetails['firstname'] . "_" . $userDetails['username'] . "@eoxvantage.";
        if (($userDetails['email'] == $userRecord . "com") || ($userDetails['email'] == $userRecord . "in")) {
            throw new ValidationException("Invalid Email");
        }
        if ($username === $userDetails['username']) {
            $userReset['username'] = $userDetails['username'];
            $userReset['email'] = $userDetails['email'];
            $userReset['firstname'] = $userDetails['firstname'];
            $userReset['lastname'] = $userDetails['lastname'];
            $userReset['url'] = $this->config['applicationUrl'] . "/?resetpassword=" . $resetPasswordCode;
            $userReset['password_reset_expiry_date'] = date("Y-m-d H:i:s", strtotime("+24 hours"));
            $userReset['accountId'] = $userDetails['accountId'];
            $userDetails['password_reset_expiry_date'] = $userReset['password_reset_expiry_date'];
            $userDetails['password_reset_code'] = $resetPasswordCode;

            //Code to update the password reset and expiration time
            $this->updateUser($userDetails['uuid'], $userDetails);
            $subject = $userReset['firstname'] . ', Your login details for EOX vantage!';
            $bcc = " ";
            if (isset($this->config['emailConfig'])) {
                $emailConfig = $this->config['emailConfig'];
                if (isset($emailConfig['resetPassword'])) {
                    $subject = isset($emailConfig['resetPassword']['subject']) ? $userReset['firstname'] . ', ' . $emailConfig['resetPassword']['subject'] : $subject;
                }
            }
            $this->messageProducer->sendQueue(json_encode(array(
                'to' => $userReset['email'],
                'subject' => $subject,
                'body' => $this->templateService->getContent('resetPassword', $userReset),
            )), 'mail');
            $userReset['email'] = $this->hideEmailAddress($userReset['email']);
            return $userReset;
        }

        throw new ServiceException("Password reset failed", 'password.reset.failed', OxServiceException::ERR_CODE_UNPROCESSABLE_ENTITY);
    }

    public function getOrganizationByUserId($id = null)
    {
        if (empty($id)) {
            $id = AuthContext::get(AuthConstants::USER_UUID);
        }
        $query = "SELECT acct.name,acct.uuid as accountId, oxa.address1,oxa.address2,oxa.city,
                            oxa.state,oxa.country,oxa.zip,acct.logo, org.uuid, org.labelfile,
                            org.languagefile,acct.status
                    from ox_account as acct
                    LEFT join ox_organization org on org.id=acct.organization_id
                    LEFT join ox_address as oxa on oxa.id = org.address_id
                    LEFT JOIN ox_account_user as uo ON uo.account_id=acct.id
                    LEFT JOIN ox_user as u on u.id = uo.user_id
                    where u.uuid = :userId AND acct.status='Active'";
        $params = ["userId" => $id];
        $resultSet = $this->executeQueryWithBindParameters($query, $params);
        return $resultSet->toArray();
    }

    public function getAppsByUserId($id = null)
    {
        $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        $userId = $id;
        if (!isset($userId)) {
            $userId = AuthContext::get(AuthConstants::USER_ID);
        }
        $query = "SELECT * from
                  (SELECT DISTINCT oa.name,oa.description, oa.uuid, oa.type, oa.logo, oa.category,oar.start_options
                    from ox_app as oa
                    INNER JOIN ox_app_registry as oar ON oa.id = oar.app_id
                    INNER JOIN ox_privilege as op on oar.app_id = op.app_id
                    INNER JOIN ox_role_privilege as orp ON op.name = orp.privilege_name
                    INNER JOIN ox_account_user au on orp.account_id = au.account_id
                    INNER JOIN ox_user_role as our ON orp.role_id = our.role_id AND
                                                      our.account_user_id = au.id
                    WHERE oar.account_id = :accountId  AND au.user_id = :userId
                 union
                 SELECT DISTINCT name,description, uuid, type, logo, category,oar.start_options
                    FROM ox_app as oa
                    INNER JOIN ox_app_registry as oar ON oa.id= oar.app_id
                    WHERE oa.id NOT IN (SELECT app_id FROM ox_privilege WHERE app_id IS NOT NULL)
                    AND oar.account_id = :accountId) a
                 ORDER BY a.name";
        $params = ['userId' => $userId, 'accountId' => $accountId];
        $result = $this->executeQueryWithBindParameters($query, $params);
        return $result->toArray();
    }

    public function getUserProfile($params)
    {
        $select = "SELECT
        emp.about,
        user.username,
        usrp.firstname,
        usrp.lastname,
        user.name,
        usrp.email,
        usrp.date_of_birth,
        emp.designation,
        usrp.gender,
        manUser.uuid as managerId,
        acc.uuid as accountId,
        emp.date_of_join,
        usrp.phone,
        user.preferences,
        user.timezone,
        addr.address1,
        addr.address2,
        addr.city,
        addr.state,
        addr.country,
        addr.zip
    FROM
        ox_user as user
        JOIN ox_person as usrp ON usrp.id = user.person_id
        INNER JOIN ox_account_user au ON user.id = au.user_id
        JOIN ox_account as acc ON au.account_id = acc.id
        LEFT JOIN ox_employee as emp ON emp.person_id = usrp.id
        LEFT JOIN ox_employee as man on man.id = emp.manager_id
        LEFT JOIN ox_user manUser on manUser.person_id = man.person_id
        LEFT JOIN ox_address as addr ON usrp.address_id = addr.id
    WHERE
        user.uuid = :userId AND acc.uuid = :accountId";
        $queryParams = ['userId' => $params['userId'], 'accountId' => $params['accountId']];
        $userData = $this->executeQueryWithBindParameters($select, $queryParams)->toArray();
        if (empty($userData)) {
            return array('data' => array(), 'role' => array());
        }
        $responseUserData = $userData[0];
        $responseUserData['manager_name'] = "";
        $responseUserData['preferences'] = json_decode($responseUserData['preferences'], true);
        if (isset($responseUserData['managerId']) && ($responseUserData['managerId'] !== 0)) {
            try {
                $result = $this->getUserWithMinimumDetails($responseUserData['managerId'], $params['accountId']);
                $responseUserData['manager_name'] = $result['firstname'] . " " . $result['lastname'];
            } catch (Exception $e) {
                unset($responseUserData['managerId']);
            }
        }
        $responseUserData['role'] = $this->getRolesofUser($params['accountId'], $params['userId']);
        return $responseUserData;
    }

    public function checkUserExists($data)
    {
        $from = "FROM ox_user as u
                 INNER join ox_person per on per.id = u.person_id ";
        $where = "WHERE (username=:username OR email=:email)";
        $queryParams = array(
            "username" => $data['username'],
            "email" => $data['email'],
        );
        $handleUserIdentifier = false;
        if (isset($data['app_id']) && isset($data['identifier_field'])) {
            if ($app = $this->getIdFromUuid('ox_app', $data['app_id'])) {
                $appId = $app;
            } else {
                $appId = $data['app_id'];
            }
            $from .= " INNER JOIN ox_wf_user_identifier ui ON ui.user_id = u.id";
            $where .= " AND ui.app_id = :appId
                        OR (ui.identifier = :identifier AND ui.identifier_name = :identifierName)";
            $queryParams = array_merge($queryParams, array(
                "appId" => $appId,
                "identifier" => $data[$data['identifier_field']],
                "identifierName" => $data['identifier_field'],
            ));
        }
        if (isset($data['date_of_birth'])) {
            $data['date_of_birth'] = date_format(date_create($data['date_of_birth']), "Y-m-d");
        }
        $query = "SELECT u.id,u.uuid,u.username,per.email $from $where";
        $this->logger->info("Check user query $query with Params" . json_encode($queryParams));
        $result = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
        if (!empty($result)) {
            if (
                $data['username'] == $result[0]['username'] &&
                $data['email'] == $result[0]['email']
            ) {
                $data['username'] = $result[0]['username'];
                $data['id'] = $result[0]['id'];
                $data['uuid'] = $result[0]['uuid'];
                return 1;
            } else {
                throw new ServiceException("Username/Email Used", "username.exists", OxServiceException::ERR_CODE_PRECONDITION_FAILED);
            }
        }
        return 0;
    }

    public function getUsersList($appUUid, $params)
    {
        try {
            $accountId = isset($params['accountId']) ? $this->getIdFromUuid('ox_account', $params['accountId']) : AuthContext::get(AuthConstants::ACCOUNT_ID);
            $appId = $this->getIdFromUuid('ox_app', $appUUid);
            if (!isset($accountId)) {
                $accountId = $params['accountId'];
            } elseif ($accountId == 0) {
                throw new EntityNotFoundException("Account does not exist");
            }
            $select = "SELECT * from ox_app_registry where account_id = :accountId AND app_id = :appId";
            $selectQuery = array("accountId" => $accountId, "appId" => $appId);
            $result = $this->executeQueryWithBindParameters($select, $selectQuery)->toArray();
            if (!empty($result)) {
                $where = "";
                $pageSize = 20;
                $offset = 0;
                $sort = "name";
                $select = "SELECT ou.uuid, ou.username, per.firstname, per.lastname,
                                    ou.name,a.uuid as accountId";
                $from = " FROM `ox_user` as ou
                          INNER JOIN ox_account a on a.id = ou.account_id
                          inner join ox_person per on per.id = ou.person_id";
                $where .= "WHERE ou.status = 'Active' AND ou.account_id = :accountId";
                $sort = " ORDER BY " . $sort;
                $limit = " LIMIT " . $pageSize . " offset " . $offset;
                $query = $select . " " . $from . " " . $where . " " . $sort . " " . $limit;
                $queryParams = ['accountId' => $accountId];
                $this->logger->info("Executing Query - $query");
                $resultSet = $this->executeQueryWithBindParameters($query, $queryParams);
                $result = $resultSet->toArray();
                for ($x = 0; $x < sizeof($result); $x++) {
                    $result[$x]['icon'] = $this->getBaseUrl() . "/user/profile/" . $result[$x]['uuid'];
                }
                return $result;
            } else {
                throw new ServiceException("App Does not belong to the account", "app.for.account.not.found", OxServiceException::ERR_CODE_FORBIDDEN);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    private function hideEmailAddress($email)
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            list($first, $last) = explode('@', $email);
            $first = str_replace(substr($first, '3'), str_repeat('*', strlen($first) - 3), $first);
            $last = explode('.', $last);
            $last_domain = str_replace(substr($last['0'], '1'), str_repeat('*', strlen($last['0']) - 1), $last['0']);
            $hideEmailAddress = $first . '@' . $last_domain . '.' . $last['1'];
            return $hideEmailAddress;
        }
    }

    public function getUserDetailsByIdentifier($identifier, $identifierName)
    {
        $select = "SELECT ou.* from ox_user as ou
                    join ox_wf_user_identifier as owi on ou.id = owi.user_id
                    WHERE owi.identifier = :identifier AND owi.identifier_name = :identifierName";
        $selectParams = array("identifier" => $identifier, "identifierName" => $identifierName);
        $result = $this->executeQueryWithBindParameters($select, $selectParams)->toArray();
        if (!empty($result)) {
            return $result[0];
        }

        return null;
    }

    public function hasLoggedIn()
    {
        $sql = $this->getSqlObject();
        $select = $sql->select();
        $select->from('ox_user')
            ->columns(array('has_logged_in', 'verification_pending'))
            ->where(array('id' => AuthContext::get(AuthConstants::USER_ID), 'account_id' => AuthContext::get(AuthConstants::ACCOUNT_ID)));
        $result = $this->executeQuery($select)->toArray();
        if (count($result)) {
            return $result[0];
        }
    }

    public function updateLoggedInStatus()
    {
        $sql = $this->getSqlObject();
        $updatedData['has_logged_in'] = "1";
        $update = $sql->update('ox_user')->set($updatedData)
            ->where(array('ox_user.id' => AuthContext::get(AuthConstants::USER_ID), 'ox_user.account_id' => AuthContext::get(AuthConstants::ACCOUNT_ID)));
        $result = $this->executeUpdate($update);
    }
    private function unsetAddressData(&$addressData, $userdata)
    {
        unset($addressData['id']);
        if (isset($userdata['country'])) {
            unset($addressData['country']);
        }
        if (isset($userdata['state'])) {
            unset($addressData['state']);
        }
    }

    private function getRoleFromUuid($uuid)
    {
        $select = "SELECT name from ox_role as oxr WHERE oxr.uuid = :uuid";
        $selectParams = array("uuid" => $uuid);
        $result = $this->executeQuerywithBindParameters($select, $selectParams)->toArray();
        if (count($result) > 0) {
            return $result[0]['name'];
        } else {
            return null;
        }
    }

    public function getUserDataByIdentifier($appId, $identifier, $identifierField)
    {
        $select = "SELECT oxu.uuid as userId, oxa.uuid as accountId,oxa.id as account_id, oxae.id as entityId
                    FROM ox_wf_user_identifier owui
                    INNER JOIN ox_user oxu ON oxu.id = owui.user_id
                    INNER JOIN ox_account oxa ON oxa.id = owui.account_id
                    INNER JOIN ox_app app ON app.id = owui.app_id
                    INNER JOIN ox_app_entity oxae ON oxae.app_id = app.id
                    WHERE owui.identifier_name = :identityField AND
                    app.uuid = :appId AND owui.identifier = :identifier";
        $selectQuery = array("identityField" => $identifierField, "appId" => $appId, "identifier" => $identifier);
        $this->logger->info("INFO---$select with Parasm--" . print_r($selectQuery, true));
        return $this->executeQuerywithBindParameters($select, $selectQuery)->toArray();
    }

    public function updateUserCacheData($value = null)
    {
        $sql = $this->getSqlObject();
        $updatedData['cleared_browser_cache'] = $value;
        $update = $sql->update('ox_user')->set($updatedData);
        $result = $this->executeUpdate($update);
        return $updatedData;
	}

    public function addExistingUserToNewAccount($data,$useruuid)
    {
        $accountId = $this->getIdFromUuid('ox_account', $data['accountId']);
        $updateQuery = "UPDATE ox_user SET account_id =:accountId where uuid =:uuid";
        $params1 = array("accountId" => $accountId,"uuid" => $useruuid);
        $this->executeUpdateWithBindParameters($updateQuery, $params1);

        $userId = $this->getIdFromUuid('ox_user',$useruuid);
        $updateQuery2 = "UPDATE ox_account_user SET account_id =".$accountId." where user_id =".$userId;
        $this->executeUpdateWithBindParameters($updateQuery2, array());

        $select = "SELECT * from ox_account_user where user_id =".$userId;
        $result1 = $this->executeQueryWithBindParameters($select, array())->toArray();
        
        $form = new User($this->table);
        $form->loadByUuid($useruuid);
        $userdata = array_merge($form->getProperties(), $data);

        if (!isset($userdata['address1']) || empty($userdata['address1'])) {
            $addressData = $this->addressService->getOrganizationAddress($data['accountId']);
            $this->unsetAddressData($addressData, $data);
            $userdata = array_merge($data, $addressData);
        }
        $this->personService->updatePerson($userdata['person_id'], $userdata);
        $response = $this->addUserRole($result1[0]['id'], 'ADMIN');
        
        $this->empService->updateEmployeeDetails($userdata);
    }
}
