<?php
namespace Callback\Controller;

use Callback\Service\TaskService;
use Exception;
use Oxzion\Controller\AbstractApiControllerHelper;

class TaskCallbackController extends AbstractApiControllerHelper
{
    private $taskService;
    private $log;
    /**
     * @ignore __construct
     */
    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
        $this->log = $this->getLogger();
    }

    // public function setTaskService($taskService)
    // {
    //     $this->taskService = $taskService;
    // }

    public function addProjectAction()
    {
        $params = $this->extractPostData();
        $this->log->info(__CLASS__ . "-> Callback - Add Project to task - " . json_encode($params, true));
        try {
            $params['projectname'] = isset($params['projectname']) ? $params['projectname'] : null;
            $params['projectdata'] = ($params['projectname']) ? ($params['projectname']) : "No Project to ADD";
            $params['manager_login'] = isset($params['manager_login']) ? $params['manager_login'] : null;
            $params['parent_identifier'] = isset($params['parent_identifier']) ? $params['parent_identifier'] : null;
            $this->log->info(TaskCallbackController::class . ":Project Data- " . $params['projectdata']);
            $response = $this->taskService->addProjectToTask($params['projectname'], $params['description'], $params['uuid'], $params['parent_identifier'], $params['manager_login']);
            // print_r($response);exit;
            if ($response) {
                $this->log->info(TaskCallbackController::class . ":Added project to task");
                return $this->getSuccessResponseWithData($response['data']);
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->log->error("Callback Error! While trying to add project to task" . print_r($response, true));
            return $this->getErrorResponse($e->getMessage(), 500, $response);
        }
        return $this->getErrorResponse("Adding Project To Task Failure ", 400);
    }

    public function deleteProjectAction()
    {
        $params = $this->extractPostData();
        $this->log->info(__CLASS__ . "-> Callback - Delete Project from Task- " . json_encode($params, true));
        try {
            $params['projectdata'] = isset($params['uuid']) ? ($params['uuid']) : "No Project to Delete";
            $this->log->info(TaskCallbackController::class . ":Project Data- " . $params['projectdata']);
            $response = $this->taskService->deleteProjectFromTask($params['uuid']);
            if ($response) {
                $this->log->info(TaskCallbackController::class . ":Project Deleted Successfully");
                return $this->getSuccessResponseWithData($response['data']);
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->log->error("Callback Error! While trying to delete the project from task" . print_r($response, true));
            return $this->getErrorResponse($e->getMessage(), 500, $response);
        }
        return $this->getErrorResponse("Delete Project From Task Failure ", 400);
    }

    public function updateProjectAction()
    {
        $params = $this->extractPostData();
        $params['parent_identifier'] = isset($params['parent_identifier']) ? $params['parent_identifier'] : null;
        $this->log->info(__CLASS__ . "-> Callback - Update project in task - " . json_encode($params, true));
        try {
            $params['projectdata'] = isset($params['new_projectname']) ? ($params['new_projectname']) : "No Project to Update";
            if (isset($params['manager_login'])) {
                $response = $this->taskService->updateProjectInTask($params['new_projectname'], $params['description'], $params['uuid'], $params['parent_identifier'], $params['manager_login']);
            } else {
                $response = $this->taskService->updateProjectInTask($params['new_projectname'], $params['description'], $params['uuid'], $params['parent_identifier']);
            }
            if ($response) {
                $this->log->info(TaskCallbackController::class . ":Project Updated Successfully");
                return $this->getSuccessResponseWithData($response['data']);
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->log->error("Callback Error! While trying to update the project in task" . print_r($response, true));
            return $this->getErrorResponse($e->getMessage(), 500, $response);
        }
        return $this->getErrorResponse("Update Project Failed ", 400);
    }

    public function createUserAction()
    {
        $params = $this->extractPostData();
        $this->log->info(__CLASS__ . "-> Callback - Add user to tasks - " . json_encode($params, true));
        try {
            $params['projectUuid'] = isset($params['projectUuid']) ? $params['projectUuid'] : null;
            $params['userData'] = isset($params['username']) ? ($params['username']) : "No User to ADD";
            $this->log->info(TaskCallbackController::class . ":User Data- " . $params['userData']);
            $response = $this->taskService->addUserToTask($params['projectUuid'], $params['username'], $params['firstname'], $params['lastname'], $params['email'], $params['timezone']);
            if ($response['status'] == "success") {
                $this->log->info(TaskCallbackController::class . ":Added user to task");
                return $this->getSuccessResponseWithData($response['data']);
            } else {
                $this->log->info(TaskCallbackController::class . ":Deletion of User from task failed");
                return $this->getErrorResponse("failed to delete user", 400, $response['data']);
            }
        } catch (Exception $e) {
            $this->log->error("Error while creating the user" . json_encode($response, true));
            return $this->getErrorResponse($e->getMessage(), 500, $response);
        }
        return $this->getErrorResponse("Adding User To Task Failure ", 400);
    }

    public function deleteUserAction()
    {
        $params = $this->extractPostData();
        $params['userData'] = isset($params['username']) ? ($params['username']) : "No User to DELETE";
        $params['projectUuid'] = isset($params['projectUuid']) ? $params['projectUuid'] : null;
        $this->log->info(TaskCallbackController::class . ":User Data- " . $params['userData']);
        $response = $this->taskService->deleteUserFromTask($params['projectUuid'], $params['username']);
        if ($response['status'] == "success") {
            $this->log->info(TaskCallbackController::class . ":Deleted User from task");
            return $this->getSuccessResponseWithData($response['data']);
        } else {
            $this->log->info(TaskCallbackController::class . ":Deletion of User from task failed");
            return $this->getErrorResponse("failed to delete user", 400, $response['data']);
        }
        return $this->getErrorResponse("Deleting User from Task Failure ", 400);
    }

    public function createTeamAction()
    {
        $params = $this->extractPostData();
        $params['teamData'] = isset($params['teamname']) ? ($params['teamname']) : "No Team to ADD";
        $this->log->info(TaskCallbackController::class . ":Team Data- " . $params['teamData']);
        $response = $this->taskService->addTeamToTask($params['teamname']);
        if ($response['success']) {
            $this->log->info(TaskCallbackController::class . ":Added team to task");
            return $this->getSuccessResponseWithData($response['result']);
        } else {
            $this->log->info(TaskCallbackController::class . ":Addition of Team to task failed");
            return $this->getErrorResponse("failed to add team", 400, $response['errors']);
        }
        return $this->getErrorResponse("Adding Team To Task Failure ", 400);
    }

    public function updateTeamAction()
    {
        $params = $this->extractPostData();
        $params['teamdata'] = isset($params['new_teamname']) ? ($params['new_teamname']) : "No Team to Update";
        $this->log->info(TaskCallbackController::class . ":Team Data- " . $params['teamdata']);
        $response = $this->taskService->updateTeamInTask($params['old_teamname'], $params['new_teamname']);
        if ($response['success']) {
            $this->log->info(TaskCallbackController::class . ":Team Updated Successfully");
            return $this->getSuccessResponseWithData($response['result']);
        } else {
            $this->log->info(TaskCallbackController::class . ":Updation of Team in task failed");
            return $this->getErrorResponse("failed to update team", 400, $response['errors']);
        }
        return $this->getErrorResponse("Update Team Failed ", 400);
    }

    public function deleteTeamAction()
    {
        $params = $this->extractPostData();
        $params['teamdata'] = isset($params['teamname']) ? ($params['teamname']) : "No Team to Delete";
        $this->log->info(TaskCallbackController::class . ":Team Data- " . $params['teamdata']);
        $response = $this->taskService->deleteTeamFromTask($params['teamname']);
        if ($response['success']) {
            $this->log->info(TaskCallbackController::class . ":Team Deleted Successfully");
            return $this->getSuccessResponseWithData($response['result']);
        } else {
            $this->log->info(TaskCallbackController::class . ":Deletion of Team in task failed");
            return $this->getErrorResponse("failed to delete team", 400, $response['errors']);
        }
        return $this->getErrorResponse("Delete Team From Task Failure ", 400);
    }

    public function updateTeamUsersAction()
    {
        $params = $this->extractPostData();
        $params['userData'] = isset($params['users']) ? ($params['users']) : "No User to ADD";
        $this->log->info(TaskCallbackController::class . ":Users to team Data- " . json_encode($params['userData']));
        $response = $this->taskService->addUsersToTeam($params['teamname'], $params['users']);
        if ($response['success']) {
            $this->log->info(TaskCallbackController::class . ":Added users to team");
            return $this->getSuccessResponseWithData($response['result']);
        } else {
            $this->log->info(TaskCallbackController::class . ":Addition of Users to team failed");
            return $this->getErrorResponse("failed to add users to team", 400, $response['errors']);
        }
        return $this->getErrorResponse("Adding User To Team Failure ", 400);
    }
}
