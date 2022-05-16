<?php
namespace Oxzion\Service;

use Oxzion\Model\App\Entity;
use Oxzion\Model\App\EntityTable;
use Exception;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\EntityNotFoundException;
use Oxzion\ServiceException;
use Oxzion\Service\AbstractService;
use Oxzion\Utils\FileUtils;
use Oxzion\Utils\UuidUtil;
use Oxzion\Service\FormService;
use App\Service\PageContentService;

class EntityService extends AbstractService
{
    protected $formService;
    protected $pageContentService;
    private $formFileExt = ".json";

    public function __construct($config, $dbAdapter, EntityTable $table, FormService $formService, PageContentService $pageContentService)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
        $this->formService = $formService;
        $this->pageContentService = $pageContentService;
    }

    public function saveEntity($appUuid, &$inputData, $createIfNotFound = true)
    {
        $data = $inputData;
        $count = 0;
        $data['app_id'] = $this->getIdFromUuid('ox_app', $appUuid);
        $data['uuid'] = isset($data['uuid']) ? $data['uuid'] : UuidUtil::uuid();
        $entity = new Entity($this->table);
        try {
            $entity->loadByUuid($data['uuid']);
            $data['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
            $data['date_modified'] = date('Y-m-d H:i:s');
        } catch (EntityNotFoundException $e) {
            if (!$createIfNotFound) {
                throw $e;
            }
            $data['created_by'] = AuthContext::get(AuthConstants::USER_ID);
            $data['date_created'] = date('Y-m-d H:i:s');
        }

        $this->logger->info(__CLASS__ . "-> \n Data Modified before the transaction - " . print_r($data, true));
        $entity->assign($data);
        try {
            $this->beginTransaction();
            $entity->save();
            $this->commit();
            $temp = $entity->getGenerated(true);
            $inputData['id'] = $temp['id'];
            $inputData['uuid'] = $temp['uuid'];

        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            $this->rollback();
            throw $e;
        }
    }

    public function deleteEntity($appUuid, $id)
    {
        $result = $this->getEntity($id, $appUuid);
        if ($result) {
            $entity = new Entity($this->table);
            $entity->loadByUuid($id);
            $data = ["isdeleted" => 1];
            $entity->assign($data);
            try {
                $this->beginTransaction();
                $entity->save($entity);
                $this->commit();
            } catch (Exception $e) {
                $this->rollback();
                $this->logger->error($e->getMessage(), $e);
                throw $e;
            }
        } else {
            throw new ServiceException("Entity Not Found", "entity.not.found");
        }
    }

    public function getEntitys($appUuid = null, $filterArray = array())
    {
        $query = "SELECT ox_app_entity.*
                    from ox_app_entity
                    left join ox_app on ox_app.id=ox_app_entity.app_id
                    where (ox_app.id=? or ox_app.uuid=?)";
        $queryParams = array($appUuid, $appUuid);
        $resultSet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
        return $resultSet;
    }

    public function getEntity($id, $appId = null)
    {
        $where = "";
        $queryParams = [];
        if ($appId) {
            $where .= is_numeric($appId) ? "ox_app.id = ?" : "ox_app.uuid = ?";
            $where .= " AND ";
            $queryParams[] = $appId;
        }
        $where .= is_numeric($id) ? "ox_app_entity.id=?" :"ox_app_entity.uuid=?";
        $query = "SELECT ox_app_entity.*
                    from ox_app_entity
                    left join ox_app on ox_app.id=ox_app_entity.app_id
                    where $where";
        $queryParams[] = $id;
        $this->logger->info("STATEMENT $query".print_r($queryParams, true));
        $resultSet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
        if (count($resultSet) == 0) {
            throw new EntityNotFoundException("Entity not found for the App");
        }
        return $resultSet[0];
    }

    public function getEntityByName($appId, $entityName)
    {
        $queryString = "SELECT en.*
                        FROM ox_app_entity AS en
                        INNER JOIN ox_app AS ap ON ap.id = en.app_id
                        WHERE ap.uuid = :appId and en.name = :entityName";
        $params = array("entityName" => $entityName, "appId" => $appId);
        $result = $this->executeQueryWithBindParameters($queryString, $params)->toArray();
        if (count($result) == 0) {
            return null;
        }
        return $result[0];
    }

    public function saveIdentifiers($entityId, $identifiers)
    {
        $this->logger->info("Save Entity Identifiers - $entityId ".print_r($identifiers, true));
        try {
            $this->beginTransaction();
            $delete = "DELETE FROM ox_entity_identifier WHERE entity_id= :entityId";
            $params = array("entityId" => $entityId);
            $result = $this->executeUpdateWithBindParameters($delete, $params);
            foreach ($identifiers as $value) {
                if (isset($value['identifier']) && is_string($value['identifier'])) {
                    $insert = "INSERT INTO ox_entity_identifier(`entity_id`,`identifier`)
                    VALUES (:entityId,:identifier)";
                    $params["identifier"] = $value['identifier'];
                    $result = $this->executeUpdateWithBindParameters($insert, $params);
                }
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function saveParticipantRoles($entityId, $appId, $data)
    {
        $this->logger->info("Save Participant Roles for Entity - $entityId ".print_r($data, true));
        try {
            $this->beginTransaction();
            $delete = "DELETE FROM ox_entity_participant_role WHERE entity_id= :entityId";
            $params = array("entityId" => $entityId);
            $result = $this->executeUpdateWithBindParameters($delete, $params);
            $params['appId'] = $appId;
            foreach ($data as $value) {
                if (isset($value['businessRole']) && is_string($value['businessRole'])) {
                    $insert = "INSERT INTO ox_entity_participant_role(`entity_id`,`business_role_id`)
                                (SELECT :entityId, br.id from ox_business_role br
                                INNER JOIN ox_app a on a.id = br.app_id
                                where a.uuid = :appId and br.name = :bRole)";
                    $params["bRole"] = $value['businessRole'];
                }
                $result = $this->executeUpdateWithBindParameters($insert, $params);
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function getEntityOfferingAccount($entityId)
    {
        $select = "SELECT account_id from ox_account_offering oof
                    inner join ox_account_business_role obr on obr.id = oof.account_business_role_id
                    where oof.entity_id = :entityId";
        $params = ["entityId" => $entityId];
        $result = $this->executeQueryWithBindParameters($select, $params)->toArray();
        if (count($result) > 0) {
            return $result[0]['account_id'];
        }

        return null;
    }

    public function removeEntityLinkedToApps($appId)
    {
        $entityRes = $this->getEntitys($appId);
        if (count($entityRes) > 0) {
            foreach ($entityRes as $key => $value) {
                $this->formService->deleteFormsLinkedToApp($appId);
                $this->deleteEntity($appId, $value['uuid']);
            }
        }
    }
    public function getEntityPage($id, $appId = null)
    {
        $where = "";
        $queryParams = [];
        if ($appId) {
            $where .= is_numeric($appId) ? "ox_app.id = ?" : "ox_app.uuid = ?";
            $where .= " AND ";
            $queryParams[] = $appId;
        }
        $entityId = $this->getIdFromUuid('ox_app_entity', $id);
        $where .= "ox_app_entity.id=?";
        $query = "SELECT ox_app_page.uuid,ox_app.name as app_name,ox_app_entity.enable_comments,ox_app_entity.enable_documents,ox_app_entity.enable_view,ox_app_entity.enable_auditlog,ox_app_entity.name from ox_app_entity
                    right join ox_app on ox_app.id=ox_app_entity.app_id
                    right join ox_app_page on ox_app_page.id=ox_app_entity.page_id
                    where $where";
        $queryParams[] = $entityId;
        $this->logger->info("STATEMENT $query".print_r($queryParams, true));
        $resultSet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
        if (count($resultSet) == 0) {
            throw new EntityNotFoundException("Entity not found for the App");
        }
        $result = $resultSet[0];
        $result['enable_comments'] = (int) $result['enable_comments'];
        $result['enable_documents'] = (int) $result['enable_documents'];
        $result['enable_view'] = (int) $result['enable_view'];
        $result['enable_auditlog'] = (int) $result['enable_auditlog'];
        $content = $this->pageContentService->getPageContent($appId, $resultSet[0]['uuid']);
        $workFlowQuery = "SELECT ox_workflow.id
                    from ox_app_entity
                    right join ox_workflow on ox_app_entity.id=ox_workflow.entity_id
                    right join ox_app on ox_app.id=ox_app_entity.app_id
                    where $where";
        $this->logger->info("STATEMENT $query".print_r($queryParams, true));
        $workflow = $this->executeQueryWithBindParameters($workFlowQuery, $queryParams)->toArray();
        if (count($workflow) > 0) {
            $result['has_workflow'] = 1;
        } else {
            $result['has_workflow'] = 0;
            $formQuery = "Select name, app_id, uuid from ox_form where entity_id=? and isdeleted=?";
            $formQueryParams = array($entityId, 0);
            $entityForm = $this->executeQueryWithBindParameters($formQuery, $formQueryParams)->toArray();
            if (count($entityForm) == 0 || count($entityForm) > 1) {
                $result['has_workflow'] = 1;
            } else {
                $form = $entityForm[0];
                $path = $this->config['FORM_FOLDER'].$appId."/".$form['name'].$this->formFileExt;
                $this->logger->info("Form template - $path");
                $result['form_uuid'] = $form['uuid'];
                $result['form_name'] = $form['name'];
                if (file_exists($path)) {
                    $result['form'] = file_get_contents($path);
                }
            }
        }
        $result['content'] = isset($content['content'])?$content['content']:null;
        return $result;
    }
}
