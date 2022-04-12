<?php

namespace Oxzion\Controller;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\Event;
use User\Model\UserTable;
use Zend\Db\Sql\Sql;
use Oxzion\Auth\AuthConstants;
use Oxzion\Security\SecurityManager;
use Zend\Mvc\MvcEvent;
use Oxzion\Utils\ValidationResult;
use Oxzion\Auth\AuthSuccessListener;
use Oxzion\Service\UserService;
use Oxzion\Service\UserTokenService;
use Oxzion\Auth\AuthContext;

abstract class AbstractApiController extends AbstractApiControllerHelper
{
    //TODO: Remove $table variable - it is not used anywhere! Also, controllers should not directly talk to database. (RaviH).
    protected $table;
    protected $log;
    protected $logClass;
    protected $modelClass;
    protected $parentId;
    protected $username;
    protected $jwtToken;

    public function __construct($table = null, $modelClass = null, $parentId = null)
    {
        $this->table = $table;
        $this->log = $this->getLogger();
        $this->logClass = get_class($this);
        $this->modelClass = $modelClass;
        $this->parentId = $parentId;
    }


    protected function validate($model)
    {
        return new ValidationResult(ValidationResult::SUCCESS);
    }

    private function getParentFilter()
    {
        $filter = null;
        if (!is_null($this->parentId)) {
            $pid = $this->params()->fromRoute()[$this->parentId];

            if ($pid !== false) {
                $filter = [$this->parentId => $pid];
            }
        }
        return $filter;
    }

    public function setEventManager(EventManagerInterface $events)
    {
        parent::setEventManager($events);
        $events->attach(MvcEvent::EVENT_DISPATCH, array($this, 'checkAuthorization'), 100);
        $events->attach(MvcEvent::EVENT_DISPATCH, array(SecurityManager::getInstance(), 'checkAccess'), 90);
    }

    public function checkAuthorization($event)
    {
        $config = $event->getApplication()->getServiceManager()->get('Config');
        $request = $event->getRequest();
        $jwtToken = $this->jwtToken = $this->findJwtToken($request);
        if ($jwtToken) {
            try {
                $token = $jwtToken;
                $tokenPayload = $this->decodeJwtToken($token);
                if (is_object($tokenPayload)) {
                    $authSuccessListener = $this->getEvent()->getApplication()->getServiceManager()->get(AuthSuccessListener::class);
                    if ($tokenPayload->data && isset($tokenPayload->data->username)) {
                        $params = [AuthConstants::USERNAME => $tokenPayload->data->username];
                        if (isset($tokenPayload->data->accountId)) {
                            $params[AuthConstants::ACCOUNT_ID] = $tokenPayload->data->accountId;
                        }
                        $userdetail = $authSuccessListener->loadUserDetails($params);
                    }
                    if ($tokenPayload->data && isset($tokenPayload->data->apikey)) {
                        $userdetail = $authSuccessListener->loadUserDetails([AuthConstants::API_KEY => $tokenPayload->data->apikey]);
                    }
                    if (is_array($userdetail) && !count($userdetail)) {
                        return $this->getErrorResponse("invalid username.", 401);
                    }
                    return;
                }
                $jsonModel = $this->getErrorResponse("Token Invalid.", 400);
            } catch (\Exception $e) {
                return $this->getErrorResponse("Token Invalid. Please login again.", 401);
            }
        } else {
            $jsonModel = $this->getErrorResponse($config['authRequiredText'], 401);
        }
        $response = $event->getResponse();
        $response->getHeaders()->addHeaderLine('Content-Type', 'application/json');
        $response->setContent($jsonModel->serialize());
        return $response;
    }


    //GET /{controller}/{id]
    public function get($id)
    {
        $this->log->info($this->logClass . ": get for id - $id");
        $filter = $this->getParentFilter();
        $form = $this->table->get($id, $filter);
        if (is_null($form)) {
            return $this->getErrorResponse("Entity not found for id - $id", 404);
        }
        return $this->getSuccessResponseWithData($form->toArray());
    }

    //GET /{controller}
    public function getList()
    {
        $this->log->info($this->logClass . ": getList");
        $filter = $this->getParentFilter();
        $result = $this->table->fetchAll($filter);
        $data = array();

        while ($result->valid()) {
            $value = $result->current();
            if (isset($value)) {
                $data[] = $value->toArray();
            }
            $result->next();
        }

        return $this->getSuccessResponseWithData($data);
    }

    //POST /controller
    public function create($data)
    {
        $this->log->info($this->logClass . ": create - ");
        $filter = $this->getParentFilter();
        if (!is_null($filter)) {
            $data[$this->parentId] = $filter[$this->parentId];
        }
        $form = new $this->modelClass;
        $form->exchangeArray($data);
        try {
            $validationResult = $this->validate($form);
            if (!$validationResult->isValid()) {
                return $this->getErrorResponse($validationResult->getMessage(), 404, $data);
            }
            $count = $this->table->save($form);
            if ($count == 0) {
                return $this->getFailureResponse("Failed to create a new entity", $data);
            }
            $id = $this->table->getLastInsertValue();
            $form->id = $id;
            return $this->getSuccessResponseWithData($form->toArray(), 201);
        } catch (\Exception $e) {
            return $this->getFailureResponse("Failed to create a new entity", $e->getMessage());
        }
    }

    //PUT /controller/{id}
    public function update($id, $data)
    {
        $this->log->info($this->logClass . ": update for id - $id ");
        $filter = $this->getParentFilter();
        $obj = $this->table->get($id, $filter);
        if (is_null($obj)) {
            return $this->getErrorResponse("Entity not found for id - $id", 404);
        }
        if (!is_null($filter)) {
            $data[$this->parentId] = $filter[$this->parentId];
        }
        $obj = new $this->modelClass;
        $obj->exchangeArray($data);
        $obj->id = $id;
        $validationResult = $this->validate($obj);
        if (!$validationResult->isValid()) {
            return $this->getErrorResponse($validationResult->getMessage(), 404, $data);
        }
        $count = $this->table->save($obj);
        if ($count == 0) {
            return $this->getFailureResponse("Failed to update data for id - $id", $data);
        }

        return $this->getSuccessResponseWithData($obj->toArray());
    }

    //DELETE /{controller}/{id}
    public function delete($id)
    {
        $this->log->info($this->logClass . ": delete for id - $id");
        $filter = $this->getParentFilter();
        $count = $this->table->delete($id, $filter);
        if ($count == 0) {
            return $this->getErrorResponse("No entity found for id - $id", 404);
        }

        return $this->getSuccessResponse();
    }

    protected function getConfig()
    {
        return $this->getEvent()->getApplication()->getServiceManager()->get('Config');
    }
}
