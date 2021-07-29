<?php
namespace Oxzion\Service;

use Oxzion\Model\FormTable;
use Oxzion\Model\Form;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Oxzion\Service\AbstractService;
use Oxzion\ValidationException;
use Oxzion\ServiceException;
use Zend\Db\Sql\Expression;
use Exception;
use Oxzion\Service\FieldService;
use Oxzion\Model\Field;
use Oxzion\Model\FieldTable;
use Oxzion\FormEngine\FormFactory;
use Oxzion\Utils\ArrayUtils;
use Oxzion\Utils\UuidUtil;

class FormService extends AbstractService
{
    private $formFileExt = ".json";
    
    public function __construct($config, $dbAdapter, FormTable $table, FormFactory $formEngineFactory, FieldService $fieldService)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
        $this->formEngineFactory = $formEngineFactory;
        $this->formEngine = $this->formEngineFactory->getFormEngine();
        $this->fieldService = $fieldService;
    }

    public function createForm($appUuid, &$data, $fieldReference = null)
    {
        $this->logger->info("EXECUTING CREATE FORM ");
        $form = new Form();
        $data['uuid'] = (isset($data['uuid']) && !empty($data['uuid'])) ? $data['uuid'] :  UuidUtil::uuid();
        $template = $this->parseForm($data, $fieldReference);
        if ($template == 0) {
            return 0;
        }
        if (isset($data['entity_id'])) {
            $template['form']['entity_id'] = $data['entity_id'];
        }
        if ($app = $this->getIdFromUuid('ox_app', $appUuid)) {
            $appId = $app;
        } else {
            throw new Exception("Invalid AppId $appUuid passed");
        }
        $template['form']['app_id'] = $appId;
        $template['form']['uuid'] = $data['uuid'];
        $template['form']['created_by'] = AuthContext::get(AuthConstants::USER_ID);
        $template['form']['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $template['form']['date_created'] = date('Y-m-d H:i:s');
        $template['form']['date_modified'] = date('Y-m-d H:i:s');
        $form->exchangeArray($template['form']);
        $form->validate();
        $count = 0;
        $this->beginTransaction();
        try {
            $count = $this->table->save($form);
            $id = $this->table->getLastInsertValue();
            $data['id'] = $id;
            $generateFields = $this->generateFields($template['fields'], $appId, $id, $template['form']['entity_id']);
            $data['fields'] = $generateFields;
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
        return $count;
    }
    public function updateForm($appUuid, $formUuid, &$data, $fieldReference = null)
    {
        $this->logger->info("EXECUTING UPDATE FORM");
        $obj = $this->table->getByUuid($formUuid);
        if (is_null($obj)) {
            return 0;
        }
        $template = $this->parseForm($data, $fieldReference);
        if ($template == 0) {
            return 0;
        }
        $form = new Form();
        $existingForm = $obj->toArray();
        $changedArray = array_merge($obj->toArray(), $template['form']);
        $changedArray['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $changedArray['date_modified'] = date('Y-m-d H:i:s');
        $form->exchangeArray($changedArray);
        $form->validate();
        $this->beginTransaction();
        $count = 0;
        try {
            $count = $this->table->save($form);
            if ($count == 0) {
                $this->rollback();
                return 0;
            }
            $generateFields = $this->generateFields($template['fields'], $this->getIdFromUuid('ox_app', $appUuid), $this->getIdFromUuid('ox_form', $formUuid), $existingForm['entity_id']);
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
        return $count;
    }

    public function deleteForm($formUuid)
    {
        $this->logger->info("EXECUTING DELETE FORM");

        $obj = $this->table->getByUuid($formUuid, array());
        if (is_null($obj)) {
            return 0;
        }
        $originalArray = $obj->toArray();
        $form = new Form();
        $originalArray['isdeleted'] = 1;
        $form->exchangeArray($originalArray);
        try {
            $this->beginTransaction();
            $count = $this->table->save($form);
            if ($count == 0) {
                $this->rollback();
                return 0;
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
        return $count;
    }

    public function getForms($appUuid=null, $filterArray=array())
    {
        $this->logger->info("EXECUTING GET FORMS");
        try {
            $where = "";
            $params = array();
            if (isset($appUuid)) {
                $where .="where app.uuid = :appId";
                $params['appId'] = $appUuid;
            }
            //TODO handle the $filterArray using FilterUtils
            $query = "select f.name, e.uuid as entity_id, f.uuid as form_id from 
                      ox_form as f inner join ox_app_entity as e on e.id = f.entity_id
                      inner join ox_app as app on app.id = f.app_id
                      $where and f.isdeleted=0";
            $response = array();
            $this->logger->info("GET FORM QUERY-- $query with params--".print_r($params, true));
            $response['data'] = $this->executeQueryWithBindParameters($query, $params)->toArray();
            return $response;
        } catch (Exception $e) {
            $this->rollback();
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }
    public function getForm($uuid)
    {
        $this->logger->info("EXECUTING GET FORM");
        try {
            $queryString = "SELECT
            of.name,
            of.app_id,
            of.uuid,
            ae.name as entity_name
          FROM
            ox_form of
          JOIN ox_app_entity ae
           ON of.entity_id = ae.id 
          where of.uuid=? and of.isdeleted=?";
            $queryParams = array($uuid, 0);
            $resultSet = $this->executeQueryWithBindParameters($queryString, $queryParams)->toArray();
            if (count($resultSet)==0) {
                return 0;
            }
            $data = $resultSet[0];
            $appId = $this->getUuidFromId("ox_app", $data['app_id']);
            $path = $this->config['FORM_FOLDER'].$appId."/".$data['name'].$this->formFileExt;
            $this->logger->info("Form template - $path");
            if (file_exists($path)) {
                $data['template'] = file_get_contents($path);
            }
            unset($data['app_id']);
            return $data;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }

    public function getWorkflow($formId)
    {
        $this->logger->info("EXECUTING GET WORKFLOW");
        $select = "SELECT f.*, a.id as activity_id, w.uuid as workflow_id from ox_form f
                 left join ox_activity_form af on af.form_id = f.id
                 left join ox_activity a on a.id = af.form_id
                 inner join ox_workflow_deployment wd on wd.form_id = f.id
                 inner join ox_workflow w on wd.workflow_id = w.id
                 where f.uuid=:formId and wd.latest=1 and f.isdeleted=0";
        $params = array("formId" => $formId);
        $this->logger->info("Executing query - $select with params - ".json_encode($params));
        $response = $this->executeQueryWithBindParameters($select, $params)->toArray();
        if (count($response)==0) {
            return 0;
        }
        return $response[0];
    }
    
    private function parseForm(&$data, $fieldReference)
    {
        if (isset($data['template']) && is_array($data['template'])) {
            $data['template'] = json_encode($data['template']);
        } else {
            if (isset($data['template'])&&is_string($data['template'])) {
                $data['template'] = $data['template'];
            } else {
                throw new ServiceException("Template not provided", 'template.required');
            }
        }
        $errors = array();
        $template = $this->formEngine->parseForm($data['template'], $fieldReference, $errors);
        if (!is_array($template)) {
            return 0;
        }
        if (count($errors) > 0) {
            $this->logger->info("Form Field mapping Errors ".json_encode($errors, JSON_PRETTY_PRINT));
            $validationException = new ValidationException();
            $validationException->setErrors($errors);
            throw $validationException;
        }

        return $template;
    }

    private function generateFields($fieldsList = array(), $appId, $formId, $entityId)
    {
        try {
            $existingFieldsQuery = "select ox_field.* from ox_field where ox_field.entity_id=".$entityId.";";
            $existingFields = $this->executeQuerywithParams($existingFieldsQuery);
            $existingFields = $existingFields->toArray();
        } catch (Exception $e) {
            throw $e;
        }
        $fieldsCreated = array();
        $fieldIdArray = array();
        foreach ($fieldsList as $field) {
            $this->saveField($existingFields, $field, $fieldsCreated, $fieldIdArray, $appId, $formId, $entityId);
        }
        $existingFormFieldsQuery = "select ox_field.* from ox_field INNER JOIN ox_form_field ON ox_form_field.field_id=ox_field.id where ox_form_field.form_id=".$formId.";";
        $existingFormFields = $this->executeQuerywithParams($existingFormFieldsQuery);
        $existingFormFields = $existingFormFields->toArray();
        foreach ($existingFormFields as $existingField) {
            $fieldDeleted =  ArrayUtils::multiDimensionalSearch($fieldsList, 'name', $existingField['name']);
            if (!isset($fieldDeleted)) {
                $deleteFormFields = "DELETE from ox_form_field where form_id=".$formId." and field_id=".$existingField['id'].";";
                $result = $this->executeQuerywithParams($deleteFormFields);
            }
        }
        return $fieldsCreated;
    }
    private function createFormFieldEntry($formId, $fieldId)
    {
        $select = "SELECT * FROM `ox_form_field` WHERE form_id=:formId AND field_id=:fieldId";
        $insertParams = array("formId" => $formId,"fieldId" =>$fieldId);
        $result = $this->executeQueryWithBindParameters($select, $insertParams)->toArray();
        if (count($result) > 0) {
            return;
        }
        $this->beginTransaction();
        try {
            $insert = "INSERT INTO `ox_form_field` (`form_id`,`field_id`) VALUES (:formId,:fieldId)";
            $resultSet = $this->executeQueryWithBindParameters($insert, $insertParams);
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }

    private function saveField(&$existingFields, &$field, &$fieldsCreated, &$fieldIdArray, $appId, $formId, $entityId)
    {
        $foundField = false;
        if (isset($field['parent'])) {
            $parentField =  ArrayUtils::multiDimensionalSearch($existingFields, 'name', $field['parent']['name']);
            $this->logger->info("PARENT FIELD----".json_encode($parentField));
            if (!$parentField) {
                $this->saveField($existingFields, $field['parent'], $fieldsCreated, $fieldIdArray, $appId, $formId, $entityId);
                $parentField = $field['parent'];
            }
            if (isset($parentField['id'])) {
                $field['parent_id'] = $parentField['id'];
            }
            unset($field['parent']);
            if (isset($field['parent_id'])) {
                $foundField = ArrayUtils::multiFieldSearch($existingFields, array('name' => $field['name'],'parent_id' => $field['parent_id']));
            }
        } else {
            $foundField =  ArrayUtils::multiDimensionalSearch($existingFields, 'name', $field['name']);
        }
        $field['app_id'] = $appId;
        $field['entity_id'] = $entityId;
        $oxField = new Field();
        if (isset($foundField) && is_array($foundField)) {
            $oxField->exchangeArray($foundField);
        }
        $oxField->exchangeArray($field);
        $fieldData = $oxField->toArray();
        try {
            $fieldResult = $this->fieldService->saveField($appId, $fieldData);
            $fieldIdArray[] = $fieldData['id'];
            $fieldsCreated[] = $fieldData;
            if (isset($foundField) && !$foundField) {
                $existingFields[] = $fieldData;
                $field['id'] = $fieldData['id'];
            }
            $createFormFieldEntry = $this->createFormFieldEntry($formId, $fieldData['id']);
        } catch (Exception $e) {
            foreach ($fieldIdArray as $fieldId) {
                $id = $this->fieldService->deleteField($appId, $fieldId);
                return 0;
            }
        }
    }

    public function deleteFormsLinkedToApp($appId)
    {
        $formsRes = $this->getForms($appId);
        if (count($formsRes) > 0) {
            foreach ($formsRes['data'] as $key => $value) {
                $this->logger->info("FORM ID FOR DELETION---".print_r($value['form_id'], true));
                $this->deleteForm($value['form_id']);
            }
        }
    }
}
