<?php

namespace Project\Controller;

use Exception;
use Oxzion\Controller\AbstractApiController;
use Project\Model\Project;
use Project\Model\ProjectTable;
use Project\Service\ProjectService;
use Zend\Db\Adapter\AdapterInterface;

class ProjectController extends AbstractApiController
{
    /**
     * @var ProjectService Instance of Project Service
     */
    private $projectService;
    /**
     * @ignore __construct
     */
    public function __construct(ProjectTable $table, ProjectService $projectService, AdapterInterface $dbAdapter)
    {
        parent::__construct($table, Project::class);
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
    public function create($data)
    {
        $params = $this->params()->fromRoute();
        $this->log->info(__CLASS__ . "-> \nCreate Project - " . print_r($params, true) . "Parameters - " . print_r($params, true));
        try {
            $this->projectService->createProject($data, $params);
            return $this->getSuccessResponseWithData($data, 201);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
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
     *     string name,
     *     string description,
     *     integer orgid,
     *     integer created_by,
     *     integer modified_by,
     *     dateTime date_modified (ISO8601 format yyyy-mm-ddThh:mm:ss),
     *     boolean isdeleted,
     *     integer id,
     *     }
     * </code>
     * @return array Returns a JSON Response with Status Code and Created Project.
     */
    public function update($id, $data)
    {
        $params = $this->params()->fromRoute();
        $this->log->info(__CLASS__ . "-> \nGet Project - " . print_r($params, true) . "Parameters - " . print_r($params, true));
        try {
            $params['accountId'] = isset($params['accountId']) ? $params['accountId'] : null;
            $this->projectService->updateProject($id, $data, $params['accountId']);
            return $this->getSuccessResponseWithData($data, 200);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
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
     * @return array Returns a JSON Response with Status Code and Created Team.
     */
    public function get($id)
    {
        $params = $this->params()->fromRoute();
        $this->log->info(__CLASS__ . "-> \nGet Project - " . print_r($params, true) . "Parameters - " . print_r($params, true));
        try {
            $result = $this->projectService->getProjectByUuid($id, $params);
            return $this->getSuccessResponseWithData($result);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Delete Project API
     * @api
     * @link /project[/:projectUuid]
     * @method DELETE
     * @param $id ID of Project to Delete
     * @return array success|failure response
     */
    public function delete($id)
    {
        $params = $this->params()->fromRoute();
        $this->log->info(__CLASS__ . "-> \nDelete Project - " . print_r($params, true) . "Parameters - " . print_r($params, true));
        try {
            $this->projectService->deleteProject($params);
            return $this->getSuccessResponse();
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * GET List Project API
     * @api
     * @link /project
     * @method GET
     * @return array $dataget list of Projects by User
     * <code>status : "success|error",
     *       data :  {
     *          string name,
     *          string description,
     *          integer orgid,
     *          integer created_by,
     *          integer modified_by,
     *          dateTime date_created (ISO8601 format yyyy-mm-ddThh:mm:ss),
     *          dateTime date_modified (ISO8601 format yyyy-mm-ddThh:mm:ss),
     *          boolean isdeleted,
     *          integer id,
     *      }
     * </code>
     */
    public function getList()
    {
        $params = $this->params()->fromRoute();
        $filterParams = $this->params()->fromQuery(); // empty method call
        $this->log->info(__CLASS__ . "-> \nGet Project List - " . print_r($params, true) . "Query Parameters - " . print_r($filterParams, true));
        try {
            $result = $this->projectService->getProjectList($filterParams, $params);
            return $this->getSuccessResponseDataWithPagination($result['data'], $result['total']);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * GET List Project of Current User API
     * @api
     * @link /project
     * @method GET
     * @return array $dataget list of Projects by User
     * <code>status : "success|error",
     *       data :  {
     *           string name,
     *           string description,
     *           integer orgid,
     *           integer created_by,
     *           integer modified_by,
     *           dateTime date_created (ISO8601 format yyyy-mm-ddThh:mm:ss),
     *           dateTime date_modified (ISO8601 format yyyy-mm-ddThh:mm:ss),
     *           boolean isdeleted,
     *           integer id,
     *       }
     * </code>
     */
    public function getListOfMyProjectAction()
    {
        $params = $this->params()->fromRoute();
        $this->log->info(__CLASS__ . "-> \nGet My Project List - " . print_r($params, true));
        try {
            $result = $this->projectService->getProjectsOfUser($params);
            return $this->getSuccessResponseWithData($result);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
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
    public function saveUserAction()
    {
        $params = $this->params()->fromRoute();
        $data = $this->extractPostData();
        $this->log->info(__CLASS__ . "-> \nSave User - " . print_r($params, true) . "Parameters - " . print_r($data, true));
        try {
            $this->projectService->saveUser($params, $data);
            return $this->getSuccessResponseWithData($data, 200);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
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
    public function getListOfUsersAction()
    {
        $params = $this->params()->fromRoute();
        $filterParams = $this->params()->fromQuery(); // empty method call
        try {
            $result = $this->projectService->getUserList($params, $filterParams);
            return $this->getSuccessResponseDataWithPagination($result['data'], $result['total']);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    public function getSubprojectsAction()
    {
        $params = $this->params()->fromRoute();
        $filterParams = $this->params()->fromQuery();
        $this->log->info(__CLASS__ . "-> \nGet Project - " . print_r($params, true) . "Parameters - " . print_r($params, true));
        try {
            $result = $this->projectService->getSubprojects($params, $filterParams);
            return $this->getSuccessResponseWithData($result);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }
}
