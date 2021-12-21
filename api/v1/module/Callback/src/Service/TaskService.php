<?php
namespace Callback\Service;

use Exception;
use Oxzion\Service\AbstractService;
use Oxzion\Utils\RestClient;
use Zend\Log\Logger;

class TaskService extends AbstractService
{
    protected $dbAdapter;

    public function setRestClient($restClient)
    {
        $this->restClient = $restClient;
    }

    public function __construct($config)
    {
        parent::__construct($config, null);
        $this->restClient = new RestClient($this->config['task']['taskServerUrl'], array('auth' => array($this->config['task']['username'], $this->config['task']['authToken'])));
    }

    public function addProjectToTask($name, $description, $uuid, $parentIdentifier, $manager_login = null)
    {
        try {
            $uuid = isset($uuid) ? "p".$uuid : NULL; //Openproject has issues when the identifier does not begin with an alphabet
            $response = $this->restClient->postWithHeader('projects', array('name' => $name, 'description' => $description, 'uuid' => $uuid, 'manager_login' => $manager_login, 'parent_identifier' => $parentIdentifier));
            $projectData = json_decode($response['body'], true);
            return $projectData;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->info("Failed to create new entity" . $e);
        }
    }

    public function deleteProjectFromTask($uuid)
    {
        try {
            $uuid = isset($uuid) ? "p".$uuid : NULL; //Openproject has issues when the identifier does not begin with an alphabet
            $response = $this->restClient->deleteWithHeader('projects/' . $uuid);
            $projectData = json_decode($response['body'], true);
            return $projectData;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->info("Failed to Delete entity" . $e);
        }
    }

    public function updateProjectInTask($name, $description, $uuid, $parentIdentifier, $manager_login = null)
    {
        try {
            $uuid = isset($uuid) ? "p".$uuid : NULL; //Openproject has issues when the identifier does not begin with an alphabet
            if ($manager_login) {
                $response = $this->restClient->updateWithHeader('projects/' . $uuid, array('name' => $name, 'description' => $description, 'parent_identifier' => $parentIdentifier, 'manager_login' => $manager_login));
            } else {
                $response = $this->restClient->updateWithHeader('projects/' . $uuid, array('name' => $name, 'description' => $description, 'parent_identifier' => $parentIdentifier));
            }

            $projectData = json_decode($response['body'], true);
            return $projectData;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->info("Failed to Update entity" . $e);
        }
    }

    public function addUserToTask($projectUuid, $username, $firstname, $lastname, $email, $timezone)
    {
        try {
            $projectUuid = isset($projectUuid) ? "p".$projectUuid : NULL; //Openproject has issues when the identifier does not begin with an alphabet
            $response = $this->restClient->postWithHeader('oxusers', array('username' => $username, 'firstname' => $firstname, 'lastname' => $lastname, 'email' => $email, 'timezone' => $timezone, 'projectUuid' => $projectUuid));
            if ($response != 0) {
                $projectData = json_decode($response['body'], true);
                return $projectData;
            }
        } catch (Exception $e) {
            $this->logger->info("Failed to create new entity" . $e);
        }
        return 0;
    }

    public function addTeamToTask($teamname)
    {
        try {
            $response = $this->restClient->post('teams', array('name' => $teamname));
            return json_decode($response, true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->info(TaskService::class . "Failed to create new entity" . $e);
        }
    }

    public function updateTeamInTask($teamname, $new_teamname)
    {
        try {
            $response = $this->restClient->put('teams', array('name' => $teamname, 'newname' => $new_teamname));
            return json_decode($response, true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->info(TaskService::class . "Failed to create new entity" . $e);
        }
    }

    public function deleteTeamFromTask($teamname)
    {
        try {
            $response = $this->restClient->delete('teams', array('name' => $teamname));
            return json_decode($response, true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->info(TaskService::class . "Failed to create new entity" . $e);
        }
    }

    public function deleteUserFromTask($projectUuid, $username)
    {
        try {
            $projectUuid = isset($projectUuid) ? "p".$projectUuid : NULL; //Openproject has issues when the identifier does not begin with an alphabet
            $response = $this->restClient->deleteWithHeader('oxusers', array('username' => $username, 'projectUuid' => $projectUuid));
            $projectData = json_decode($response['body'], true);
            return $projectData;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->error("Failed to create new entity", $e);
        }
    }

    public function addUsersToTeam($teamname, $usernames)
    {
        try {
            $response = $this->restClient->put('team_members', array('name' => $teamname, 'usernames' => $usernames));
            return json_decode($response, true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->info(TaskService::class . "Failed to create new entity" . $e);
        }
    }
}
