<?php
namespace Account\Controller;

use Oxzion\Controller\AbstractApiController;
use Oxzion\Model\Account;
use Oxzion\Model\AccountTable;
use Oxzion\Service\AccountService;
use Zend\Db\Adapter\AdapterInterface;
use Exception;

class AccountController extends AbstractApiController
{
    private $accountService;

    /**
     * @ignore __construct
     */
    public function __construct(AccountTable $table, AccountService $accountService, AdapterInterface $dbAdapter)
    {
        parent::__construct($table, Account::class);
        $this->setIdentifierName('accountId');
        $this->accountService = $accountService;
    }

    /**
     * Create Account API
     * @api
     * @method POST
     * @link /account
     * @param array $data Array of elements as shown
     * <code> {
     *               id : integer,
     *               name : string,
     *               logo : string,
     *               status : String(Active|Inactive),
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created Account.
     */
    public function create($data)
    {
        $files = $this->params()->fromFiles('logo') ? $this->params()->fromFiles('logo') : null;
        $id = $this->params()->fromRoute();
        $this->log->info("Create Account - " . print_r($data, true) . "\n Files - " . print_r($files, true));
        try {
            if (!isset($id['accountId'])) {
                $this->accountService->createAccount($data, $files);
            } else {
                $this->accountService->updateAccount($id['accountId'], $data, $files);
            }
            return $this->getSuccessResponseWithData($data, 201);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * GET List Account API
     * @api
     * @link /account
     * @method GET
     * @return array Returns a JSON Response with Invalid Method/
     */
    public function getList()
    {
        $filterParams = $this->params()->fromQuery(); // empty method call
        $this->log->info("Get Account List - " . print_r($filterParams, true));
        try {
            $result = $this->accountService->getAccounts($filterParams);
            if ($result) {
                for ($x = 0; $x < sizeof($result['data']); $x++) {
                    $baseUrl = $this->getBaseUrl();
                    $result['data'][$x]['logo'] = $baseUrl . "/account/logo/" . $result['data'][$x]['uuid'];
                    $result['data'][$x]['preferences'] = json_decode($result['data'][$x]['preferences'], true);
                }
            }
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseDataWithPagination($result['data'], $result['total']);
    }

    /**
     * Update Account API
     * @api
     * @link /account[/:accountId]
     * @method PUT
     * @param array $id ID of Account to update
     * @param array $data
     * @return array Returns a JSON Response with Status Code and Created Account.
     */
    public function update($id, $data)
    {
        $files = $this->params()->fromFiles('logo');
        try {
            $this->accountService->updateAccount($id, $data, $files);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        
        return $this->getSuccessResponseWithData($data, 200);
    }

    /**
     * Delete Account API
     * @api
     * @link /account[/:accountId]
     * @method DELETE
     * @param $id ID of Account to Delete
     * @return array success|failure response
     */
    public function delete($id)
    {
        try {
            $this->accountService->deleteAccount($id);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponse();
    }

    /**
     * GET Account API
     * @api
     * @link /account[/:accountId]
     * @method GET
     * @param array $dataget of Account
     * @return array $data
     * <code> {
     *               id : integer,
     *               name : string,
     *               logo : string,
     *               status : String(Active|Inactive),
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created Account.
     */
    public function get($id)
    {
        try {
            $result = $this->accountService->getAccountByUuid($id);
            if (!$result) {
                return $this->getErrorResponse("Account not found", 404, ['id' => $id]);
            } else {
                $baseUrl = $this->getBaseUrl();
                $result['logo'] = $baseUrl . "/account/logo/" . $result["uuid"];
                $result['preferences'] = json_decode($result['preferences'], true);
            }
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseWithData($result);
    }

    /**
     * Add User To Account API
     * @api
     * @link /account/:accountId/save'
     * @method POST
     * @param $id and $accountid that adds a particular user to a account
     * @return array success|failure response
     */
    public function addUsersToAccountAction()
    {
        $params = $this->params()->fromRoute();
        $data = $this->extractPostData();
        try {
            $this->accountService->saveUser($params['accountId'], $data);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseWithData($data, 200);
    }

    /**
     * GET all users in a particular Account API
     * @api
     * @link /account/:accountId/users
     * @method GET
     * @return array $dataget list of Account by User
     * <code>status : "success|error",
     *       data : all user id's in the Account passed back in json format
     * </code>
     */
    public function getListOfAccountUsersAction()
    {
        $account = $this->params()->fromRoute();
        $id = $account[$this->getIdentifierName()];
        $filterParams = $this->params()->fromQuery(); // empty method call
        try {
            $result = $this->accountService->getAccountUserList($account[$this->getIdentifierName()], $filterParams, $this->getBaseUrl());
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        
        return $this->getSuccessResponseDataWithPagination($result['data'], $result['total']);
    }

    public function getListofAdminUsersAction()
    {
        $data = $this->params()->fromRoute();
        $filterParams = $this->params()->fromQuery();
        $accountId = isset($data['accountId']) ? $data['accountId'] : null;
        try {
            $result = $this->accountService->getAdminUsers($filterParams, $accountId);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseDataWithPagination($result['data'], $result['total']);
    }

    /**
     * Check Account User API
     * @api
     * @link /account/:accountId/usercheck/:username
     * @method GET
     **/
    public function checkAccountUserAction()
    {
        $params = $this->params()->fromRoute();
        try {
            $result = $this->accountService->checkAccountUser($params);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessStringResponse("No user found");
    }

    /**
     * GET Account Teams API
     * @api
     * @link /account/:accountId/teams
     * @method GET
     **/
    public function getListofAccountTeamsAction()
    {
        $params = $this->params()->fromRoute();
        $filterParams = $this->params()->fromQuery();
        $accountId = isset($params['accountId']) ? $params['accountId'] : null;
        try {
            $result = $this->accountService->getAccountTeamsList($accountId, $filterParams);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseDataWithPagination($result['data'], $result['total']);
    }

    /**
     * GET Account Projects API
     * @api
     * @link /account/:accountId/projects
     * @method GET
     **/
    public function getListofAccountProjectsAction()
    {
        $params = $this->params()->fromRoute();
        $filterParams = $this->params()->fromQuery();
        $accountId = isset($params['accountId']) ? $params['accountId'] : null;
        try {
            $result = $this->accountService->getAccountProjectsList($accountId, $filterParams);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseDataWithPagination($result['data'], $result['total']);
    }

    /**
     * GET Account Announcements API
     * @api
     * @link /account/:accountId/announcements
     * @method GET
     **/
    public function getListofAccountAnnouncementsAction()
    {
        $params = $this->params()->fromRoute();
        $filterParams = $this->params()->fromQuery();
        $accountId = isset($params['accountId']) ? $params['accountId'] : null;
        try {
            $result = $this->accountService->getAccountAnnouncementsList($accountId, $filterParams);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseDataWithPagination($result['data'], $result['total']);
    }

    /**
     * GET Account Roles API
     * @api
     * @link /account/:accountId/roles
     * @method GET
     **/
    public function getListofAccountRolesAction()
    {
        $params = $this->params()->fromRoute();
        $filterParams = $this->params()->fromQuery();
        $accountId = isset($params['accountId']) ? $params['accountId'] : null;
        try {
            $result = $this->accountService->getAccountRolesList($accountId, $filterParams);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseDataWithPagination($result['data'], $result['total']);
    }
    
    /**
     * GET Account Roles API
     * @api
     * @link /account/subordinates/:managerId
     * @method GET
     **/
    public function getSubordinatesAction()
    {
        $params = $this->params()->fromRoute();
        $managerId = isset($params['managerId']) ? $params['managerId'] : null;
        try {
            $result = $this->accountService->getSubordinates($managerId);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseWithData($result);
    }
}
