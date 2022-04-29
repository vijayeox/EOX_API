<?php

namespace App\Controller;

use App\Service\ImportService;
use Exception;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\Controller\AbstractApiController;
use Oxzion\Service\UserCacheService;
use Zend\Db\Adapter\AdapterInterface;

class CacheController extends AbstractApiController
{
    /**
     * @var ImportService Instance of ImportService Service
     */
    private $userCacheService;

    /**
     * @ignore __construct
     */
    public function __construct(UserCacheService $UserCacheService, AdapterInterface $dbAdapter)
    {
        parent::__construct(null, null);
        $this->setIdentifierName('appId');
        $this->userCacheService = $UserCacheService;
        $this->log = $this->getLogger();
    }

    /*
     * POST Import the CSV fuction
     * @api
     * @link /app/appId/cache
     * @method POST
     * @return Status mesassge based on success and failure
     * <code>status : "success|error",
     *       data :  {
     * String stored_procedure_name
     * int: account_id
     * string: app_id
     * string: app_name
     * }
     * </code>
     */
    /**
     * Create Entity API
     * @api
     * @link /app/appId/cache
     * @method POST
     * @param array $data Array of elements as shown
     * <code> {
     *               data : integer,
     *               name : string,
     *               Fields from Entity
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created Entity.
     */
    public function storeAction()
    {
        $data = array_merge($this->extractPostData(), $this->params()->fromRoute());
        $appUuid = $this->params()->fromRoute()['appId'];
        $this->log->info(__CLASS__ . "-> \n Store Cache - " . print_r($data, true));
        try {
            $this->userCacheService->storeUserCache($appUuid, $data);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseWithData($data, 201);
    }

    public function cacheAction()
    {
        $appId = $this->params()->fromRoute()['appId'];
        if (isset($this->params()->fromRoute()['cacheId'])) {
            $cacheId = $this->params()->fromRoute()['cacheId'];
        } else {
            $cacheId = null;
        }
        $this->log->info(__CLASS__ . "-> \n Get Cache - " . print_r($appId, true));
        try {
            $result = $this->userCacheService->getCache($cacheId, $appId, AuthContext::get(AuthConstants::USER_ID));
            return $this->getSuccessResponseWithData($result);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    public function formcacheAction()
    {
        $routeParams = $this->params()->fromRoute();
        $this->log->info(__CLASS__ . "-> \n Get Form Cache - " . print_r($routeParams, true));
        try {
            $result = $this->userCacheService->getCache(null, $routeParams['appId'], AuthContext::get(AuthConstants::USER_ID), $routeParams);
            return $this->getSuccessResponseWithData($result);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    public function cacheDeleteAction()
    {
        $appId = $this->params()->fromRoute()['appId'];
        try {
            $result = $this->userCacheService->deleteUserCache($appId);
            return $this->getSuccessResponse("The cache has been successfully deleted");
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }
}
