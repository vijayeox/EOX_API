<?php

namespace Oxzion\AppDelegate;

use Oxzion\Db\Persistence\Persistence;
use Oxzion\Service\UserService;

trait UserContextTrait
{
    private $userId;
    private $username;
    private $orgId;
    private $privilege;
    private $userService;

    public function setUserContext($userId, $username, $orgId, $privilege)
    {
        $this->username = $username;
        $this->userId = $userId;
        $this->orgId = $orgId;
        $this->privilege = $privilege;
    }
    public function getUserId()
    {
        return $this->userId;
    }
    public function getUserByUuid($uuid)
    {
        return $this->userService->getUserByUuid($uuid);
    }
    public function getusername()
    {
        return $this->username;
    }
    public function getOrgId()
    {
        return $this->orgId;
    }
    public function getPrivilege()
    {
        return $this->privilege;
    }
    public function getUser($userId, $getAllFields)
    {
        return $this->userService->getUser($userId, $getAllFields);
    }
    public function getUsers($filterParams = null, $baseUrl = '', $params = null)
    {
        return $this->userService->getUsers($filterParams = null, $baseUrl = '', $params = null);
    }
    public function getUserIdList($uuidList)
    {
        return $this->userService->getUserIdList($uuidList);
    }
    public function setUserService(UserService $userService)
    {
        $this->logger->info("SET User SERVICE");
        $this->userService = $userService;
    }
    public function getUserDetailsByIdentifier($identifier, $identifierName)
    {
        return $this->userService->getUserDetailsByIdentifier($identifier, $identifierName);
    }

    public function getUserDataByIdentifier($appId, $identifier, $identifierField)
    {
        return $this->userService->getUserDataByIdentifier($appId, $identifier, $identifierField);
    }

    public function getUserWithMinimumDetails($id, $accountId = null)
    {
        return $this->userService->getUserWithMinimumDetails($id, $accountId = null);
    }
}
