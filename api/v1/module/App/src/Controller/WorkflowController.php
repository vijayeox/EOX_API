<?php
namespace App\Controller;
/**
* Workflow Api
*/
use Zend\Log\Logger;
use Oxzion\Model\Workflow;
use Oxzion\Model\WorkflowTable;
use Oxzion\Service\WorkflowService;
use Oxzion\Controller\AbstractApiController;
use Bos\ValidationException;
use Zend\Db\Adapter\AdapterInterface;
use Zend\View\Model\JsonModel;
/**
 * Workflow Controller
 */
class WorkflowController extends AbstractApiController
{
    /**
    * @ignore WorkflowService
    */
    private $workflowService;
    /**
    * @ignore __construct
    */
	public function __construct(WorkflowTable $table, WorkflowService $workflowService, Logger $log, AdapterInterface $dbAdapter) {
		parent::__construct($table, $log, __CLASS__, Workflow::class);
		$this->setIdentifierName('workflowId');
		$this->workflowService = $workflowService;
	}
    /**
    * Create Workflow API
    * @api
    * @link /Workflow
    * @method POST
    * @param array $data Array of elements as shown
    * <code> {
    *               id : integer,
    *               name : string,
    *               formid : integer,
    *   } </code>
    * @return array Returns a JSON Response with Status Code and Created Workflow.
    */
    public function create($data){
        $appId = $this->params()->fromRoute()['appId'];
        try{
            $count = $this->workflowService->saveWorkflow($appId,$data);
        }catch(ValidationException $e){
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors",404, $response);
        }
        if($count == 0){
            return $this->getFailureResponse("Failed to create a new entity", $data);
        }
        return $this->getSuccessResponseWithData($data,201);
    }
    
    /**
    * GET List Workflows API
    * @api
    * @link /Workflow
    * @method GET
    * @return array Returns a JSON Response list of Workflows based on Form id.
    */
    public function getList() {
        $appId = $this->params()->fromRoute()['appId'];
        $result = $this->workflowService->getWorkflows($appId);
        return $this->getSuccessResponseWithData($result['data']);
    }
    /**
    * Update Workflow API
    * @api
    * @link /Workflow[/:WorkflowId]
    * @method PUT
    * @param array $id ID of Workflow to update 
    * @param array $data 
    * @return array Returns a JSON Response with Status Code and Created Workflow.
    */
    public function update($id, $data){
        try{
            $count = $this->workflowService->updateWorkflow($id,$data);
        }catch(ValidationException $e){
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors",404, $response);
        }
        if($count == 0){
            return $this->getErrorResponse("Entity not found for id - $id", 404);
        }
        return $this->getSuccessResponseWithData($data,200);
    }
    /**
    * Delete Workflow API
    * @api
    * @link /Workflow[/:WorkflowId]
    * @method DELETE
    * @param $id ID of Workflow to Delete
    * @return array success|failure response
    */
    public function delete($id){
        $appId = $this->params()->fromRoute()['appId'];
        $response = $this->workflowService->deleteWorkflow($appId,$id);
        if($response == 0){
            return $this->getErrorResponse("Workflow not found", 404, ['id' => $id]);
        }
        return $this->getSuccessResponse();
    }
    /**
    * GET Workflow API
    * @api
    * @link /Workflow[/:WorkflowId]
    * @method GET
    * @param $id ID of Workflow
    * @return array $data 
    * @return array Returns a JSON Response with Status Code and Created Workflow.
    */
    public function get($id){
        $appId = $this->params()->fromRoute()['appId'];
        $result = $this->workflowService->getWorkflow($appId,$id);
        if($result == 0){
            return $this->getErrorResponse("Workflow not found", 404, ['id' => $id]);
        }
        return $this->getSuccessResponseWithData($result);
    }
    /**
     * Upload the app from the UI and extracting the zip file in a folder that will start the installation of app.
     * @api
     * @link /app/:appId/deployworkflow
     * @method POST
     * @param null </br>
     * <code>
     * </code>
     * @return array Returns a JSON Response with Status Code.</br>
     * <code> status : "success|error"
     * </code>
     */
    public function workflowFieldsAction()
    {
        $params = array_merge($this->params()->fromPost(),$this->params()->fromRoute());
        try {
            $response = $this->workflowService->getFields($params['appId'],$params['workflowId']);
            return $this->getSuccessResponseWithData($response);
        } catch (Exception $e) {
            return $this->getErrorResponse("Files cannot be uploaded!");
        }
    }
    /**
     * Upload the app from the UI and extracting the zip file in a folder that will start the installation of app.
     * @api
     * @link /app/:appId/deployworkflow
     * @method POST
     * @param null </br>
     * <code>
     * </code>
     * @return array Returns a JSON Response with Status Code.</br>
     * <code> status : "success|error"
     * </code>
     */
    public function workflowFormsAction()
    {
        $params = array_merge($this->params()->fromPost(),$this->params()->fromRoute());
        try {
            $response = $this->workflowService->getForms($params['appId'],$params['workflowId']);
            return $this->getSuccessResponseWithData($response);
        } catch (Exception $e) {
            return $this->getErrorResponse("Files cannot be uploaded!");
        }
    }
    public function fieldDataAction(){
        $params = array_merge($this->params()->fromPost(),$this->params()->fromRoute());
        switch ($this->request->getMethod()) {
            case 'POST':
                if(isset($params['fileId'])){
                    return $this->saveFieldData($params,$params['fileId']);
                } else {
                    return $this->saveFieldData($params);
                }
                break;
            case 'GET':
                return $this->getFieldData($params);
                break;
            case 'DELETE':
                return $this->deleteFieldData($params);
                break;
            default:
                return $this->getErrorResponse("Not Sure what you are upto");
                break;
        }
    }
    /**
    * Create File API
    * @api
    * @link /workflow/:workflowId/form/:formId/fielddata
    * @method POST
    * @param array $data Array of elements as shown
    * <code> {
    *               id : integer,
    *               name : string,
    *               status : string,
    *               formid : integer,
    *               Fields from Form
    *   } </code>
    * @return array Returns a JSON Response with Status Code and Created File.
    */
    private function saveFieldData($params,$id = null){
        try{
            $count = $this->workflowService->saveFile($params,$id);
        } catch (ValidationException $e){
            $response = ['data' => $params, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors",404, $response);
        }
        if($count == 0){
            return $this->getFailureResponse("Failed to create a new entity", $params);
        }
        if(isset($id)){
            return $this->getSuccessResponseWithData($params,200);
        } else {
            return $this->getSuccessResponseWithData($params,201);
        }
    }
    private function getFieldData($params){
        if(!isset($params['fileId'])){
            return $this->getInvalidMethod();
        }
        $result = $this->workflowService->getFile($params);
        if($result == 0){
            return $this->getErrorResponse("File not found", 404, ['id' => $id]);
        }
        return $this->getSuccessResponseWithData($result);
    }
    private function deleteFieldData($params){
        $response = $this->workflowService->deleteFile($params);
        if($response == 0){
            return $this->getErrorResponse("File not found", 404, ['id' => $id]);
        }
        return $this->getSuccessResponse();
    }
}