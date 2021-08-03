<?php
namespace Oxzion\Service;

use Exception;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\Model\Activity;
use Oxzion\Model\Form;
use Oxzion\Model\Workflow;
use Oxzion\Model\WorkflowDeployment;
use Oxzion\Model\WorkflowDeploymentTable;
use Oxzion\Model\WorkflowTable;
use Oxzion\ServiceException;
use Oxzion\Service\AbstractService;
use Oxzion\Service\ActivityService;
use Oxzion\Service\FieldService;
use Oxzion\Service\FileService;
use Oxzion\Service\FormService;
use Oxzion\Utils\UuidUtil;
use Oxzion\Workflow\WorkFlowFactory;
use Oxzion\Service\ActivityInstanceService;

class WorkflowService extends AbstractService
{
    private $id;
    private $baseFolder;

    /**
     * @ignore table
     */
    private $table;
    private $fileExt = ".json";
    protected $config;
    protected $processManager;
    protected $fileService;
    protected $formService;
    protected $fieldService;
    protected $processEngine;
    protected $activityEngine;
    protected $activityService;
    public static $field = array('workflow_name' => 'ox_workflow.name');

    public function __construct($config, $dbAdapter, WorkflowTable $table, FormService $formService, FieldService $fieldService, FileService $fileService, WorkflowFactory $workflowFactory, ActivityService $activityService, WorkflowDeploymentTable $workflowDeploymentTable, ActivityInstanceService $activityInstanceService)
    {
        parent::__construct($config, $dbAdapter);
        $this->baseFolder = $this->config['UPLOAD_FOLDER'];
        $this->formsFolder = $this->config['FORM_FOLDER'];
        $this->table = $table;
        $this->workflowDeploymentTable = $workflowDeploymentTable;
        $this->config = $config;
        $this->workFlowFactory = $workflowFactory;
        $this->processManager = $this->workFlowFactory->getProcessManager();
        $this->formService = $formService;
        $this->fieldService = $fieldService;
        $this->fileService = $fileService;
        $this->processEngine = $this->workFlowFactory->getProcessEngine();
        $this->activityEngine = $this->workFlowFactory->getActivity();
        $this->activityService = $activityService;
        $this->activityInstanceService = $activityInstanceService;
    }
    public function setProcessEngine($processEngine)
    {
        $this->processEngine = $processEngine;
    }
    public function setProcessManager($processManager)
    {
        $this->processManager = $processManager;
    }
    public function getProcessManager()
    {
        return $this->processManager;
    }
    public function deploy($file, $appUuid, $data, $entityId)
    {
        $this->logger->info("Executing deploy for entity id -> $entityId");
        $query = "SELECT * FROM `ox_app` WHERE uuid = :appUuid;";
        $queryParams = array("appUuid" => $appUuid);
        $resultSet = $this->executeQuerywithBindParameters($query, $queryParams)->toArray();
        $appId = $resultSet[0]['id'];
        $baseFolder = $this->config['UPLOAD_FOLDER'];
        $workflowName = $data['name'];
        if (!isset($appId)) {
            return 0;
        }
        $data['entity_id'] = $entityId;

        try {
            $this->logger->info("Executing deploy of workflow : $workflowName");
            $processIds = $this->getProcessManager()->deploy($workflowName, array($file));
            $processId = null;
            if ($processIds) {
                if (count($processIds) == 1) {
                    $processDefinitionId = $processIds[0];
                    $processId = explode(":", $processDefinitionId)[0];
                    $data['process_id'] = $processId;
                    $data['process_definition_id'] = $processDefinitionId;
                }
            }
            if (!$processId) {
                throw new ServiceException("Process Could not be created", "process.creation.failed");
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
        $this->beginTransaction();
        try {
            $this->saveWorkflow($appId, $data);
            $workflow = $data;
            $workFlowId = $data['id'];
            $workflowDeploymentId = $data['workflow_deployment_id'];
            $processes = $this->getProcessManager()->parseBPMN($file, $appId);
            $path = dirname($file) . "/../forms/";
            $startFormId = null;
            $workFlowList = array();
            $workFlowFormIds = array();
            $fields = null;
            if (isset($processes)) {
                //CAUTION: Current deployment expects only one process in a file
                foreach ($processes as $process) {
                    $activityData = array();
                    if (isset($process['form']['properties'])) {
                        $formProperties = json_decode($process['form']['properties'], true);
                    }
                    $oxForm = new Form();
                    $oxForm->exchangeArray($process['form']);
                    $oxFormProperties = $oxForm->getKeyArray();
                    if (isset($formProperties)) {
                        foreach ($formProperties as $formKey => $formValue) {
                            if (in_array($formKey, $oxFormProperties)) {
                                $oxForm->__set($formKey, $formValue);
                            }
                        }
                    }
                    $formData = $oxForm->toArray();
                    $formData['entity_id'] = $entityId;
                    if (isset($formProperties['template'])) {
                        $filePath = $path . $formProperties['template'] . $this->fileExt;
                        $this->logger->info("File Path ------ " . print_r($filePath, true));
                        if (file_exists($filePath)) {
                            $formData['template'] = file_get_contents($filePath);
                            $formResult = $this->formService->createForm($appUuid, $formData);
                        }
                    }
                    if (isset($formProperties['fields'])) {
                        $fields = json_encode(array_map('trim', explode(",", $formProperties['fields'])));
                    }
                    $startFormId = $formData['id'];
                    if (isset($process['activity'])) {
                        foreach ($process['activity'] as $activity) {
                            $oxActivity = new Activity();
                            $oxActivity->exchangeArray($activity);
                            $oxFormProperties = $oxActivity->getKeyArray();
                            if (isset($activityProperties)) {
                                foreach ($activityProperties as $activityKey => $activityValue) {
                                    if (in_array($activityKey, $activityProperties)) {
                                        $oxActivity->__set($key, $activityValue);
                                    }
                                }
                            }
                            $activityData = $oxActivity->toArray();
                            try {
                                if (isset($activity['form'])) {
                                    $formTemplate = json_decode($activity['form'], true);
                                    $activityFilePath = $path . $formTemplate['template'] . $this->fileExt;
                                    if (file_exists($activityFilePath)) {
                                        $activityData['template'] = file_get_contents($activityFilePath);
                                    }
                                }
                                $activityData['entity_id'] = $entityId;
                                $activityData['workflow_deployment_id'] = $workflowDeploymentId;
                                $activityResult = $this->activityService->createActivity($appUuid, $activityData);
                                $activityIdArray[] = $activityData['id'];
                            } catch (Exception $e) {
                                throw $e;
                            }
                        }
                    }
                }
            }
            if (isset($workflowName)) {
                $deployedData = array('id' => $workFlowId, 'workflow_deployment_id' => $workflowDeploymentId, 'app_id' => $appId, 'name' => $workflowName, 'process_id' => $processId, 'process_definition_id' => $processDefinitionId, 'form_id' => $startFormId, 'file' => $file, 'fields' => $fields, 'entity_id' => $entityId, 'uuid' => $workflow['uuid']);
                $this->logger->info("Deployed Data-" . json_encode($deployedData));
                try {
                    $workFlow = $this->saveWorkflow($appId, $deployedData);
                } catch (Exception $e) {
                    throw $e;
                }
            }
            $this->commit();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            $this->rollback();
            throw $e;
        }
        return $deployedData ? $deployedData : 0;
    }
    public function saveWorkflow($appId, &$data)
    {
        if (isset($appId)) {
            if ($app = $this->getIdFromUuid('ox_app', $appId)) {
                $appId = $app;
            }
        } else {
            return 0;
        }
        $data['app_id'] = $appId;
        if (!isset($data['id']) || $data['id'] == 0) {
            $data['created_by'] = AuthContext::get(AuthConstants::USER_ID);
            $data['date_created'] = date('Y-m-d H:i:s');
        }
        if (isset($data['uuid'])) {
            $data['uuid'] = $data['uuid'];
            $id = $this->getIdFromUuid('ox_workflow', $data['uuid']);
            if ($id) {
                $data['id'] = $id;
            }
        } else {
            $data['uuid'] = UuidUtil::uuid();
        }
        if (!isset($data['id']) && isset($data['process_id'])) {
            $query = "select id from ox_workflow where process_id=:processId";
            $params = array("processId" => $data['process_id']);
            $result = $this->executeQueryWithBindParameters($query, $params)->toArray();
            if (count($result) > 0) {
                $data['id'] = $result[0]['id'];
            }
        }
        $data['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['created_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_modified'] = date('Y-m-d H:i:s');
        $data['date_created'] = date('Y-m-d H:i:s');
        $form = new Workflow();
        $form->exchangeArray($data);
        $form->validate();
        $this->beginTransaction();
        $count = 0;
        try {
            $count = $this->table->save($form);
            if ($count == 0) {
                throw new ServiceException("Workflow not saved", 'workflow.save.failed');
            }
            if (!isset($data['id'])) {
                $id = $this->table->getLastInsertValue();
                $data['id'] = $id;
            }
            $temp = $this->saveWorkflowDeployment($data);
            if ($temp) {
                $data['workflow_deployment_id'] = $temp['id'];
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
        return $count;
    }

    /**
     * Create Fomr Field Entry
     * @param array $data Array of elements as shown
     * ! Deprecated - This private function is not being called in this Class, we need to remove this
     */
    private function createFormFieldEntry($formId, $fieldId)
    {
        $this->beginTransaction();
        try {
            $insert = "INSERT INTO `ox_form_field` (`form_id`,`field_id`) VALUES (:formId,:fieldId)";
            $insertParams = array("formId" => $formId, "fieldId" => $fieldId);
            $resultSet = $this->executeQuerywithBindParameters($insert, $insertParams);
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }

    public function updateWorkflow($appUuid, $id, &$data)
    {
        $obj = $this->table->getByUuid($id, array());
        if (is_null($obj)) {
            return 0;
        }
        $data['id'] = $this->getIdFromUuid('ox_workflow', $id);
        $data['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_modified'] = date('Y-m-d H:i:s');
        // $data['app_id'] = $this->getIdFromUuid('ox_app',$appUuid);
        $workflow = new Workflow();
        $changedArray = array_merge($obj->toArray(), $data);
        $workflow->exchangeArray($changedArray);
        $workflow->validate();
        $this->beginTransaction();
        $count = 0;
        try {
            $count = $this->table->save($workflow);
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

    public function deleteWorkflow($appUuid, $workflowUuid)
    {
        $data['id'] = $this->getIdFromUuid('ox_workflow', $workflowUuid);
        if (!isset($data['id']) || $data['id'] == 0) {
            $data['id'] = $workflowUuid;
        }
        $obj = $this->table->getByUuid($workflowUuid, array());
        if (is_null($obj)) {
            return 0;
        }
        $data['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_modified'] = date('Y-m-d H:i:s');
        $data['isdeleted'] = 1;
        $workflow = new Workflow();
        $changedArray = array_merge($obj->toArray(), $data);
        $workflow->exchangeArray($changedArray);
        $workflow->validate();
        $this->beginTransaction();
        try {
            $this->table->save($workflow);
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }

        return $workflow->toArray();
    }

    public function getWorkflows($appUuid = null, $filterArray = array())
    {
        if (isset($appUuid)) {
            $filterArray['app_id'] = $this->getIdFromUuid('ox_app', $appUuid);
        }
        $filterArray['isdeleted'] = 0;
        $resultSet = $this->getDataByParams('ox_workflow', array("*"), $filterArray, null);
        $response = array();
        $response['data'] = $resultSet->toArray();
        return $response;
    }

    public function getWorkflow($id, $appId = null)
    {
        $params = array();
        $where = "where wf.uuid = :id and wd.latest=1";
        $params['id'] = $id;
        if (isset($appId)) {
            $where .= " and app.uuid = :appId";
            $params['appId'] = $appId;
        }
        $query = "select app.uuid as app_id, wf.uuid as id, wf.name, wd.form_id, wd.process_definition_id, wf.entity_id
    from ox_workflow wf inner join ox_workflow_deployment wd on wd.workflow_id = wf.id
    inner join ox_app as app on app.id = wf.app_id
    $where";

        $response = $this->executeQueryWithBindParameters($query, $params)->toArray();
        if (count($response) == 0) {
            return 0;
        }

        return $response[0];
    }

    public function getStartForm($appId, $workflowId)
    {
        $workflowUuid = $workflowId;
        $sql = $this->getSqlObject();
        if ($app = $this->getIdFromUuid('ox_app', $appId)) {
            $appId = $app;
        } else {
            $appId = $appId;
        }
        if ($workflow = $this->getIdFromUuid('ox_workflow', $workflowId)) {
            $workflowId = $workflow;
        } else {
            $workflowId = $workflowId;
        }

        $select = "select ox_form.name as formName,ox_form.uuid as id
    from ox_form
    left join ox_workflow_deployment on ox_workflow_deployment.form_id = ox_form.id and ox_workflow_deployment.latest=1
    left join ox_workflow on ox_workflow.id=ox_workflow_deployment.workflow_id
    left join ox_app on ox_app.id=ox_workflow.app_id
    where ox_workflow.id=:workflowId and ox_app.id=:appId;";
        $queryParams = array("workflowId" => $workflowId, "appId" => $appId);
        $response = $this->executeQueryWithBindParameters($select, $queryParams)->toArray();
        $filePath = $this->formsFolder . $this->getUuidFromId('ox_app', $appId) . "/" . $response[0]['formName'] . $this->fileExt;
        if (file_exists($filePath)) {
            $response[0]['template'] = file_get_contents($filePath);
        }
        if (isset($response[0])) {
            $response[0]['workflow_uuid'] = $workflowUuid;
            return $response[0];
        }
        throw new ServiceException("Start form not found for the workflow", "workflow.startform.not.found");
    }

    private function saveWorkflowDeployment($data)
    {
        $this->logger->info("Workflow Deployment - " . json_encode($data));
        if (!isset($data['process_definition_id'])) {
            return;
        }
        $data['workflow_id'] = $data['id'];
        $query = "UPDATE ox_workflow_deployment SET latest=0 where workflow_id=:workflowId and latest=1";
        $params = array("workflowId" => $data['workflow_id']);
        $result = $this->executeUpdateWithBindParameters($query, $params);
        if (!isset($data['workflow_deployment_id'])) {
            unset($data['id']);
        } else {
            $data['id'] = $data['workflow_deployment_id'];
        }
        $data['latest'] = 1;
        $workflowDeploy = new WorkflowDeployment();
        $workflowDeploy->exchangeArray($data);
        $workflowDeploy->validate();
        try {
            $count = $this->workflowDeploymentTable->save($workflowDeploy);
            if ($count == 0) {
                throw new ServiceException("SAVE WORKFLOW DEPLOYMENT FAILED", "save.workflow.deployement.failed");
            }
            if (!isset($data['id'])) {
                $id = $this->workflowDeploymentTable->getLastInsertValue();
                $data['id'] = $id;
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
        return $data;
    }

    public function deleteWorkflowLinkedToApp($appId)
    {
        $workflowRes = $this->getWorkflows($appId);
        if (count($workflowRes) > 0) {
            foreach ($workflowRes['data'] as $key => $value) {
                $this->deleteWorkflowInstance($value['id']);
                $this->activityService->deleteActivitiesLinkedToApp($appId);
                $this->activityInstanceService->removeActivityInstanceRecords($value['id']);
                $this->fileService->deleteFilesLinkedToApp($appId);
                $this->deleteWorkflow($appId, $value['uuid']);
                $this->deleteWorkflowDeployement($value['id']);
            }
        }
    }
    
    private function deleteWorkflowDeployement($workflowId)
    {
        try {
            $this->beginTransaction();
            $update = "UPDATE ox_workflow_deployment SET isdeleted =:deleted where workflow_id=:workflowId and latest=:latest";
            $updateParams = array('deleted' => 1, 'workflowId' => $workflowId, 'latest' => 1);
            $result = $this->executeUpdateWithBindParameters($update, $updateParams);
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    private function deleteWorkflowInstance($workflowDepId)
    {
        try {
            $this->beginTransaction();
            $update = "UPDATE ox_workflow_instance SET isdeleted =:deleted where workflow_deployment_id=:workflowDepId";
            $updateParams = array('deleted' => 1, 'workflowDepId' => $workflowDepId);
            $this->executeUpdateWithBindParameters($update, $updateParams);
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    private function buildSortQuery($sortOptions, &$field)
    {
        $sortCount = 0;
        $sortTable = "tblf" . $sortCount;
        $sort = " ORDER BY ";
        foreach ($sortOptions as $key => $value) {
            if ($value['field'] == 'entity_name') {
                if ($sortCount > 0) {
                    $sort .= ", ";
                }
                $sort .= " ox_app_entity.name ";
                $sortCount++;
                continue;
            }
            if ($sortCount == 0) {
                $sort .= $value['field'] . " " . $value['dir'];
            } else {
                $sort .= "," . $value['field'] . " " . $value['dir'];
            }
            $field .= " , (select " . $sortTable . ".field_value from ox_file_attribute as " . $sortTable . " inner join ox_field as " . $value['field'] . $sortTable . " on( " . $value['field'] . $sortTable . ".id = " . $sortTable . ".field_id)  WHERE " . $value['field'] . $sortTable . ".name='" . $value['field'] . "' AND " . $sortTable . ".file_id=ox_file.id) as " . $value['field'];
            $sortCount += 1;
        }
        return $sort;
    }
}
