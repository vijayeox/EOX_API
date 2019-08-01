<?php

namespace Project\Controller;

use Zend\Log\Logger;
use Oxzion\Controller\AbstractApiController;
use Project\Model\ProjectTable;
use Project\Model\Project;
use Project\Service\ProjectService;
use Zend\Db\Adapter\AdapterInterface;
use Oxzion\ValidationException;
use Zend\InputFilter\Input;
use Oxzion\AccessDeniedException;

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
    	$this->setIdentifierName('projectUuid');
    	$this->projectService = $projectService;
    }

    /**
    * Create Project API
    * @api
    * @link /project
    * @method POST
    * @param array $data Array of elements as shown</br>
    * <code> name : string,
    *         description : string,
    * </code>
    * @return array Returns a JSON Response with Status Code and Created Project.</br>
    * <code> status : "success|error",
    *        data : array Created Project Object
    *                string name,
    *                string description,
    *                integer orgid,
    *                integer created_by,
    *                integer modified_by,
    *                dateTime date_created (ISO8601 format yyyy-mm-ddThh:mm:ss),
    *                dateTime date_modified (ISO8601 format yyyy-mm-ddThh:mm:ss),
    *                boolean isdeleted,
    *                integer id,
    * </code>
    */
    public function create($data) {
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
    * @link /project[/:projectUuid]
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
        catch(AccessDeniedException $e) {
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse($e->getMessage(),403, $response);
        }
    	if($count == 0) {
    		return $this->getErrorResponse("Entity not found for id - $id", 404);
    	}
        if($count == 2) {
            return $this->getErrorResponse("Failed To Update", 404);
        }
    	return $this->getSuccessResponseWithData($data,200);
    }



     /**
     * GET project API
     * @api
     * @link /project[/:projectUuid]
     * @method GET
     * @param array $dataget of project
     * @return array $data
     * <code> {
     *               id : integer,
     *               name : string,
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created Group.
     */
    public function get($id)
    {
        $data = $this->params()->fromQuery();
        try{
            $result = $this->projectService->getProjectByUuid($id,$data);
            if ($result == 0) {
                return $this->getErrorResponse("Project not found", 404, ['id' => $id]);
            }
        }
        catch(AccessDeniedException $e) {
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse($e->getMessage(),403, $response);
        }
        return $this->getSuccessResponseWithData($result);
    }


    /**
    * Delete Project API
    * @api
    * @link /project[/:projectUuid]
    * @method DELETE
    * @param $id ID of Project to Delete
    * @return array success|failure response
    */
    public function delete($id) {
        $data = $this->params()->fromQuery();
        try{
        	$response = $this->projectService->deleteProject($id,$data);
        	if($response == 0) {
    		return $this->getErrorResponse("Project not found", 404, ['id' => $id]);
        	}
        }
        catch(AccessDeniedException $e) {
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse($e->getMessage(),403, $response);
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
        try{
            $filterParams = $this->params()->fromQuery(); // empty method call
            $result = $this->projectService->getProjectList($filterParams);
        }
        catch(AccessDeniedException $e) {
            $response = ['errors' => $e->getErrors()];
            return $this->getErrorResponse($e->getMessage(),403, $response);
        }
        return $this->getSuccessResponseDataWithPagination($result['data'],$result['total']);
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
        try{
            $data = $this->params()->fromQuery();
            $result = $this->projectService->getProjectsOfUser($data);
        }
        catch(AccessDeniedException $e) {
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse($e->getMessage(),403, $response);
        }
        return $this->getSuccessResponseWithData($result);
    }
    /**
    * Save users in a Project API
    * @api
    * @link /project/:projectUuid/save
    * @method Post
    * @param json object of userid
    * @return array $dataget list of Projects by User
    * <code>status : "success|error",
    *       data : all user id's passed back in json format
    * </code>
    */
    public function saveUserAction() {
        $params = $this->params()->fromRoute();
        $id=$params['projectUuid'];
        $data = $this->extractPostData();
        try {
            $count = $this->projectService->saveUser($id,$data);
        } catch (ValidationException $e) {
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors",404, $response);
        }
        catch(AccessDeniedException $e) {
            return $this->getErrorResponse($e->getMessage(),403);
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
    * @link /project/:projectuuid/users
    * @method GET
    * @return array $dataget list of Projects by User
    * <code>status : "success|error",
    *       data : all user id's in the project passed back in json format
    * </code>
    */
    public function getListOfUsersAction() {
        $project = $this->params()->fromRoute();
        $id=$project[$this->getIdentifierName()];
        $filterParams = $this->params()->fromQuery(); // empty method call
        try {
            $count = $this->projectService->getUserList($project[$this->getIdentifierName()],$filterParams);
        } catch (ValidationException $e) {
            $response = ['errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors",404, $response);
        }
        catch(AccessDeniedException $e) {
            $response = ['errors' => $e->getErrors()];
            return $this->getErrorResponse($e->getMessage(),403, $response);
        }
        if($count == 0) {
            return $this->getErrorResponse("Entity not found for id - $id", 404);
        }
        return $this->getSuccessResponseDataWithPagination($count['data'],$count['total']);
    }
}