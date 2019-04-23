<?php

namespace Project\Controller;

use Zend\Log\Logger;
use Oxzion\Controller\AbstractApiController;
use Project\Model\ProjectTable;
use Project\Model\Project;
use Project\Service\ProjectService;
use Zend\Db\Adapter\AdapterInterface;
use Bos\ValidationException;
use Zend\InputFilter\Input;

class ProjectController extends AbstractApiController {	
    /**
    * @var ProjectService Instance of Project Service
    */
	private $projectService;
    /**
    * @ignore __construct
    */
    public function __construct(ProjectTable $table, ProjectService $projectService, Logger $log, AdapterInterface $dbAdapter)
    {
    	parent::__construct($table, $log, __CLASS__, Project::class);
    	$this->setIdentifierName('projectId');
    	$this->projectService = $projectService;
    }

    /**
    * Create Project API
    * @api
    * @link /project
    * @method POST
    * @param array $data Array of elements as shown</br>
    * <code> name : string,
             description : string,
    * </code>
    * @return array Returns a JSON Response with Status Code and Created Project.</br>
    * <code> status : "success|error",
    *        data : array Created Project Object
                    string name,
                    string description,
                    integer orgid,
                    integer created_by,
                    integer modified_by,
                    dateTime date_created (ISO8601 format yyyy-mm-ddThh:mm:ss),
                    dateTime date_modified (ISO8601 format yyyy-mm-ddThh:mm:ss),
                    boolean isdeleted,
                    integer id,
    * </code>
    */
    public function create($data) {
    	$data = $this->params()->fromPost();
    	try {
    		$count = $this->projectService->createProject($data);
    	} catch(ValidationException $e) {	
    		$response = ['data' => $data, 'errors' => $e->getErrors()];
    		return $this->getErrorResponse("Validation Errors",404, $response);
    	}
    	if($count == 0) {
    		return $this->getFailureResponse("Failed to create a new entity", $data);
    	}
    	return $this->getSuccessResponseWithData($data,201);
    }
     /**
    * Update Project API
    * @api
    * @link /project[/:projectId]
    * @method PUT
    * @param array $id ID of Project to update 
    * @param array $data 
    * <code> status : "success|error",
    *        data : {
                    string name,
                    string description,
                    integer orgid,
                    integer created_by,
                    integer modified_by,
                    dateTime date_created (ISO8601 format yyyy-mm-ddThh:mm:ss),
                    dateTime date_modified (ISO8601 format yyyy-mm-ddThh:mm:ss),
                    boolean isdeleted,
                    integer id,
                    }
    * </code>
    * @return array Returns a JSON Response with Status Code and Created Project.
    */
    public function update($id, $data) {
    	try {
    		$count = $this->projectService->updateProject($id, $data);
    	} catch (ValidationException $e) {
    		$response = ['data' => $data, 'errors' => $e->getErrors()];
    		return $this->getErrorResponse("Validation Errors",404, $response);
    	}
    	if($count == 0) {
    		return $this->getErrorResponse("Entity not found for id - $id", 404);
    	}
    	return $this->getSuccessResponseWithData($data,200);
    }
    /**
    * Delete Project API
    * @api
    * @link /project[/:projectId]
    * @method DELETE
    * @param $id ID of Project to Delete
    * @return array success|failure response
    */
    public function delete($id) {
    	$response = $this->projectService->deleteProject($id);
    	if($response == 0) {
		return $this->getErrorResponse("Project not found", 404, ['id' => $id]);
    	}
    	return $this->getSuccessResponse();
    }
    /**
    * GET List Project API
    * @api
    * @link /project
    * @method GET
    * @return array $dataget list of Projects by User
    * <code>status : "success|error",
    *       data :  {
                    string name,
                    string description,
                    integer orgid,
                    integer created_by,
                    integer modified_by,
                    dateTime date_created (ISO8601 format yyyy-mm-ddThh:mm:ss),
                    dateTime date_modified (ISO8601 format yyyy-mm-ddThh:mm:ss),
                    boolean isdeleted,
                    integer id,
                    }
    * </code>
    */
    public function getList(){
        $params = $this->params()->fromQuery(); // empty method call
        if(!isset($params['q'])){
            $params['q'] = "";
        }

        if(!isset($params['f'])){
            $params['f'] = "name";
        }
        if(!isset($params['pg'])){
            $params['pg'] = 1;
        }
        if(!isset($params['psz'])){
            $params['psz'] = 20;
        }
        if(!isset($params['sort'])){
            $params['sort'] = "name";
        }  
        $result = $this->projectService->getProjectList($params['q'],$params['f'],$params['pg'],$params['psz'],$params['sort']);
        return $this->getSuccessResponseWithData($result);
    }
    /**
    * GET List Project of Current User API
    * @api
    * @link /project
    * @method GET
    * @return array $dataget list of Projects by User
    * <code>status : "success|error",
    *       data :  {
                    string name,
                    string description,
                    integer orgid,
                    integer created_by,
                    integer modified_by,
                    dateTime date_created (ISO8601 format yyyy-mm-ddThh:mm:ss),
                    dateTime date_modified (ISO8601 format yyyy-mm-ddThh:mm:ss),
                    boolean isdeleted,
                    integer id,
                    }
    * </code>
    */
    public function getListOfMyProjectAction(){
        $result = $this->projectService->getProjectsOfUser();
        return $this->getSuccessResponseWithData($result);
    }
    /**
    * Save users in a Project API
    * @api
    * @link /project/:projectid/save
    * @method Post
    * @param json object of userid
    * @return array $dataget list of Projects by User
    * <code>status : "success|error",
    *       data : all user id's passed back in json format
    * </code>
    */
    public function saveUserAction() {
        $params = $this->params()->fromRoute();
        $id=$params[$this->getIdentifierName()];
        $data = $this->params()->fromPost();
        try {
            $count = $this->projectService->saveUser($params[$this->getIdentifierName()],$data);
        } catch (ValidationException $e) {
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors",404, $response);
        }
        if($count == 0) {
            return $this->getErrorResponse("Entity not found", 404);
        }
        if($count == 2) {
            return $this->getErrorResponse("Enter User Ids", 404);
        }
        return $this->getSuccessResponseWithData($data,200);
    }
    /**
    * GET all users in a particular Project API
    * @api
    * @link /project/:projectid/users
    * @method GET
    * @return array $dataget list of Projects by User
    * <code>status : "success|error",
    *       data : all user id's in the project passed back in json format
    * </code>
    */
    public function getListOfUsersAction() {
        $project = $this->params()->fromRoute();
        $id=$project[$this->getIdentifierName()];
        $params = $this->params()->fromQuery(); // empty method call
        if(!isset($params['q'])){
            $params['q'] = "";
        }

        if(!isset($params['f'])){
            $params['f'] = "name";
        }
        if(!isset($params['pg'])){
            $params['pg'] = 1;
        }
        if(!isset($params['psz'])){
            $params['psz'] = 20;
        }
        if(!isset($params['sort'])){
            $params['sort'] = "name";
        }  
        try {
            $count = $this->projectService->getUserList($project[$this->getIdentifierName()],$params['q'],$params['f'],$params['pg'],$params['psz'],$params['sort']);
        } catch (ValidationException $e) {
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors",404, $response);
        }
        if($count == 0) {
            return $this->getErrorResponse("Entity not found for id - $id", 404);
        }
        return $this->getSuccessResponseWithData($count,200);
    }
}