<?php
namespace User\Controller;

use Exception;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\Controller\AbstractApiController;
use Oxzion\Service\UserSessionService;
use Zend\Db\Adapter\AdapterInterface;

class UserSessionController extends AbstractApiController
{
    private $sessionService;

    /**
     * @ignore __construct
     */
    public function __construct(UserSessionService $sessionService, AdapterInterface $dbAdapter)
    {
        $this->setIdentifierName('userId');
        $this->sessionService = $sessionService;
    }

    /**
     * GET Session API
     * @api
     * @link /user/session
     * @method GET
     * @param array $dataget of User
     * @return array $data
     * @return array Returns a JSON Response with Status Code and Created User.
     */
    public function getSessionAction()
    {
        $result = $this->sessionService->getSessionData();
        if (!empty($result[0])) {
            return $this->getSuccessResponseWithData(json_decode($result[0], true), 200);
        } else {
            return $this->getSuccessResponseWithData(array(), 200);
        }
    }

    /**
     * Update User Session API
     * @api
     * @link /user/session
     * @method POST
     * @param array $id ID of User to update
     * @param array $data
     * @return array Returns a JSON Response with Status Code and Created User.
     */
    public function updateSessionAction()
    {
        if (AuthContext::get(AuthConstants::USER_ID)) {
            $data = $this->extractPostData();
            try {
                $count = $this->sessionService->updateSessionData($data);
            } catch (Exception $e) {
                return $this->getErrorResponse("Update Failure", 404, array("message"->$e->getMessage()));
            }
            if (!empty(($data['data']))) {
                return $this->getSuccessResponseWithData(json_decode($data['data'], true), 200);
            } else {
                return $this->getSuccessResponseWithData(array(), 200);
            }
        } else {
            return $this->getErrorResponse("invalid username.", 401);
        }
    }

}
