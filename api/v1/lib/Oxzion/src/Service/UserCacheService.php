<?php
/**
 * File Api
 */
namespace Oxzion\Service;

use Exception;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\Model\UserCache;
use Oxzion\Model\UserCacheTable;
use Oxzion\Service\AbstractService;
use Oxzion\ServiceException;
use Oxzion\OxServiceException;

/**
 * UserCache Controller
 */
class UserCacheService extends AbstractService
{
    /**
     * @var UserCacheService Instance of UserCache Service
     */
    private $commentService;
    /**
     * @ignore __construct
     */

    public function __construct($config, $dbAdapter, UserCacheTable $table)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
    }

    public function storeUserCache($appUuId, &$params)
    {
        $this->logger->info("STORE USER CACHE DATA ---".print_r($params, true));
        if ($app = $this->getIdFromUuid('ox_app', $appUuId)) {
            $appId = $app;
        } else {
            $appId = $appUuId;
        }
        $workflowId = $formId = $activityInstanceId = $workflowInstanceId = null;
        if (isset($params['formId'])) {
            $formId = $this->getIdFromUuid('ox_form', $params['formId']);
            // print_r($formId);exit;
        }
        if (isset($params['activityInstanceId'])) {
            $select = "select ox_form.id, ox_activity_instance.id as activity_instance_id
            from ox_form
            inner join ox_activity_form on ox_form.id = ox_activity_form.form_id
            inner join ox_activity on ox_activity_form.activity_id = ox_activity.id
            inner join ox_activity_instance on ox_activity.id = ox_activity_instance.activity_id
            where ox_activity_instance.activity_instance_id =:activityInstanceId";
            $queryParams = array("activityInstanceId" => $params['activityInstanceId']);
            $response = $this->executeQueryWithBindParameters($select, $queryParams)->toArray();
            $formId = $response[0]['id'];
            $activityInstanceId = $response[0]['activity_instance_id'];
        }

        $workflowId = (isset($params['workflow_uuid']) && !empty($params['workflow_uuid']))?$params['workflow_uuid']:null;
        $workflowId = (isset($params['workflowId']) && !empty($params['workflowId']))?$params['workflowId']:$workflowId;
        $this->logger->info("STORE USER CACHE -- workflowId : $workflowId");
        if ($workflowId) {
            $select = "select ox_form.id, ox_workflow.id as workflow_id
            from ox_form
            left join ox_workflow_deployment on ox_workflow_deployment.form_id = ox_form.id and ox_workflow_deployment.latest=1
            left join ox_workflow on ox_workflow.id=ox_workflow_deployment.workflow_id
            left join ox_app on ox_app.id=ox_workflow.app_id
            where ox_workflow.uuid=:workflowId and ox_app.id=:appId;";
            $queryParams = array("workflowId" => $workflowId, "appId" => $appId);
            $response = $this->executeQueryWithBindParameters($select, $queryParams)->toArray();
            $formId = $response[0]['id'];
            $workflowId = $response[0]['workflow_id'];
        }
        if (!isset($formId)) {
            $this->logger->warn("Cache not stored as Form was not found");
            return $params;
        }

        if (isset($params['workflowInstanceId'])) {
            $select = "select ox_workflow_instance.id
            from ox_workflow_instance
            where ox_workflow_instance.process_instance_id =:workflowInstanceId";
            $queryParams = array("workflowInstanceId" => $params['workflowInstanceId']);
            $response = $this->executeQueryWithBindParameters($select, $queryParams)->toArray();
            $workflowInstanceId = $response[0]['id'];
        }
        if (isset($params['cacheId'])) {
            $obj = $this->table->get($params['cacheId'], array());
            if (count($obj->toArray()) > 0) {
                $data['id'] = $params['cacheId'];
            }
        }
        $form = new UserCache();
        $data['app_id'] = $appId;
        $data['content'] = isset($params['content']) ? $params['content'] : json_encode($params);
        $data['user_id'] = isset($params['user_id']) ? $params['user_id'] : AuthContext::get(AuthConstants::USER_ID);
        $data['workflow_id'] = $workflowId;
        $data['workflow_instance_id'] = $workflowInstanceId;
        $data['activity_instance_id'] = $activityInstanceId;
        $data['date_created'] = date('Y-m-d H:i:s');
        $data['form_id'] = $formId;
        $form->exchangeArray($data);
        $this->logger->info("STORE USER CACHE -- Data : ".print_r($data, true));
        $form->validate();
        $count = 0;
        try {
            $this->beginTransaction();
            $count = $this->table->save($form);
            if ($count != 0) {
                if (!isset($data['id'])) {
                    $id = $this->table->getLastInsertValue();
                    $params['cacheId'] = $id;
                } else {
                    $id = $data['id'];
                }
            } else {
                throw new ServiceException("Failed to save cache", 'cache.save.failed');
            }
            if (is_string($data['content'])) {
                $content = json_decode($data['content'], true);
            } else {
                $content = $params['content'];
            }
            $content['cacheId'] = $id;
            $params['cacheId'] = $id;
            $this->updateCacheData($id, $content);
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }
    private function updateCacheData($id, $data)
    {
        $query = "update ox_user_cache set content = :content where id = :id";
        $params = array('content' => json_encode($data), 'id' => $id);
        $result = $this->executeUpdateWithBindParameters($query, $params);
    }

    public function updateUserCache($id, &$data)
    {
        $obj = $this->table->get($id, array());
        if (is_null($obj)) {
            return 0;
        }
        $form = new UserCache();
        $data = array_merge($obj->toArray(), $data); //Merging the data from the db for the ID
        $data['id'] = $id;
        $data['date_modified'] = date('Y-m-d H:i:s');
        $form->exchangeArray($data);
        $form->validate();
        $count = 0;
        try {
            $this->beginTransaction();
            $count = $this->table->save($form);
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function deleteUserCache($appId = null, $cacheParams = null)
    {
        $sql = $this->getSqlObject();
        $params = array();
        $userId = AuthContext::get(AuthConstants::USER_ID);
        if (isset($userId)) {
            $params['user_id'] = $userId;
        }
        if (isset($appId)) {
            $params['app_id'] = $this->getIdFromUuid('ox_app', $appId);
            if ($params['app_id'] == 0) {
                throw new ServiceException("Invalid AppId - Cache not deleted", 'invalid.appid', OxServiceException::ERR_CODE_NOT_FOUND);
            }
        }
        if (isset($cacheParams['cacheId'])) {
            $obj = $this->table->get($cacheParams['cacheId'], array());
            if (count($obj->toArray()) > 0) {
                $params['id'] = $cacheParams['cacheId'];
            }
        }
        
        try {
            $this->beginTransaction();
            $update = $sql->update();
            $update->table('ox_user_cache')
                ->set(array('deleted' => 1))
                ->where($params);
            $response = $this->executeUpdate($update);
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function getCache($id = null, $appId = null, $userId = null, $data = [])
    {
        $params = array();
        if (!empty($id)) {
            $params['id'] = $id;
        }
        if (!empty($appId)) {
            if ($app = $this->getIdFromUuid('ox_app', $appId)) {
                $appId = $app;
            }
            $params['app_id'] = $appId;
        }
        if (!empty($userId)) {
            $params['user_id'] = $userId;
        }
        if (!empty($data['formId'])) {
            if ($form = $this->getIdFromUuid('ox_form', $data['formId'])) {
                $data['formId'] = $form;
            }
            $params['form_id'] = $data['formId'];
        }
        if (!empty($data['workflowId'])) {
            if ($workflow = $this->getIdFromUuid('ox_workflow', $data['workflowId'])) {
                $data['workflowId'] = $workflow;
            }
            $params['workflow_id'] = $data['workflowId'];
        }
        $params['deleted'] = 0;
        $select = $this->getSqlObject()->select();
        $select->from('ox_user_cache')
            ->columns(array("*"))
            ->where($params);
        $response = $this->executeQuery($select)->toArray();
        if (count($response) == 0) {
            return $response;
        }
        return ($content = json_decode($response[0]['content'], true)) ? $content : array('content' => $response[0]['content']);
    }

}