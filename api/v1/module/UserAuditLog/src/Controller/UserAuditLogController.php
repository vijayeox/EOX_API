<?php

namespace UserAuditLog\Controller;

use Exception;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use UserAuditLog\Model\UserAuditLog;
use UserAuditLog\Model\UserAuditLogTable;
use UserAuditLog\Service\UserAuditLogService;
use Oxzion\AccessDeniedException;
use Oxzion\Controller\AbstractApiController;
use Oxzion\ServiceException;
use Oxzion\ValidationException;
use Zend\Db\Adapter\AdapterInterface;

class UserAuditLogController extends AbstractApiController
{
    private $UserAuditLogService;
    private $accountService;

    /**
     * @ignore __construct
     */
    public function __construct(UserAuditLogTable $table, UserAuditLogService $userauditlogService, AdapterInterface $dbAdapter)
    {
        parent::__construct($table, UserAuditLog::class);
        $this->userauditlogService = $userauditlogService;
        $this->log = $this->getLogger();
    }

    /**
     * Insert activity time of an user like login, logout, application close & open
     * @api
     * @link /user/me/activity/:activity
     * @method POST
     * @param jwtToken of user login & activity of the user
     * @return array Returns a JSON Response with Status Code
     */
    public function insertActivityTimeAction()
    {
        $params = $this->params()->fromRoute();
        if (AuthContext::get(AuthConstants::USER_ID)) {
            try {
                $count = $this->userauditlogService->insertActivityTime($params['activity'], $this->jwtToken);
            } catch (Exception $e) {
                return $this->getErrorResponse("Insert log Failure", 404, array("message" => $e->getMessage()));
            }
            return $this->getSuccessResponse();
        } else {
            return $this->getErrorResponse("Invalid Username", 401);
        }
    }
}
