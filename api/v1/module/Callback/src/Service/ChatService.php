<?php
namespace Callback\Service;

use Exception;
use Oxzion\Service\AbstractService;
use Oxzion\Utils\RestClient;
use Oxzion\Service\FileService;
use Oxzion\Service\SubscriberService;
use Oxzion\Service\UserService;

class ChatService extends AbstractService
{
    private $restClient;
    private $authToken;
    protected $dbAdapter;

    public function setRestClient($restClient)
    {
        $this->restClient = $restClient;
    }

    public function __construct($config, $dbAdapter, FileService $fileService, SubscriberService $subscriberService, UserService $userService)
    {
        parent::__construct($config, $dbAdapter);
        $chatServerUrl = $this->config['chat']['chatServerUrl'];
        $this->restClient = new RestClient($this->config['chat']['chatServerUrl']);
        $this->authToken = $this->config['chat']['authToken']; //PAT
        $this->appBotUrl = $this->config['chat']['appBotUrl'];
        $this->applicationUrl = $this->config['applicationUrl'];
        $this->fileService = $fileService;
        $this->subscriberService = $subscriberService;
        $this->userService = $userService;
        $this->dbAdapter = $dbAdapter;
    }

    private function getAuthHeader()
    {
        $headers = array("Authorization" => "Bearer $this->authToken");
        return $headers;
    }

    private function sanitizeName($name)
    {
        return strtolower(trim(preg_replace("/[^A-Za-z0-9]/", "", $name)));
    }

    private function getTeamByName($accountName, $forceCreateteam = false)
    {
        try {
            $headers = $this->getAuthHeader();
            $accountName = $this->sanitizeName($accountName);
            $response = $this->restClient->get('api/v4/teams/name/' . $accountName, array(), $headers);
            $json = json_decode($response, true);
            return $json;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($forceCreateteam && ($e->getCode() == 404) && (strpos($e->getMessage(), 'store.sql_team.get_by_name.app_error'))) {
                $this->logger->info("Team Doesn't exist, Creting the team");
                $team = $this->createTeam($accountName);
                $team = json_decode($team['body'], true);
                return $team;
            }
            $this->logger->info("Team Doesn't exist");
        }
    }

    private function searchTeam($accountName)
    {
        $headers = $this->getAuthHeader();
        $response = $this->restClient->postWithHeader('api/v4/teams/search', array('term' => $accountName), $headers);
        $result = json_decode($response['body'], true);
        return $result;
    }

    private function getUserByUsername($userName, $forceCreateUser = false)
    {
        try {
            $headers = $this->getAuthHeader();
            $userName = $this->sanitizeName($userName);
            $userData = $this->restClient->get('api/v4/users/username/' . $userName, array(), $headers);
            return json_decode($userData, true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($forceCreateUser && ($e->getCode() == 404) && (strpos($e->getMessage(), 'store.sql_user.get_by_username.app_error'))) {
                $this->logger->info("Unable to find an existing account matching your username, hence creating.");
                $userData = $this->addUser($userName);
                return $userData;
            }
            return $e->getCode();
        }
    }

    private function addUser($user)
    {
        try {
            $headers = $this->getAuthHeader();
            $user = $this->sanitizeName($user);
            $response = $this->restClient->postWithHeader('api/v4/users', array('email' => $user . '@gmail.com', 'username' => $user, 'first_name' => $user, 'password' => md5($user)), $headers);
            $userData = json_decode($response['body'], true);
            return $this->updateUser($user);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->error("Username doesn't exist/Username validation failure", $e);
        }
    }

    private function getChannelByName($channel, $accountName, $channelNameflag = false)
    {
        try {
            $team = $accountName;
            if (!is_array($accountName)) {
                $accountName = $this->sanitizeName($accountName);
                $team = $this->getTeamByName($accountName);
            }
            $channel = $this->sanitizeName($channel);
            $headers = $this->getAuthHeader();
            $response = $this->restClient->get('api/v4/teams/' . $team['id'] . '/channels/name/' . $channel, array(), $headers);
            $channelData = json_decode($response, true);
            return $channelData;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($channelNameflag && ($e->getCode() == 404) && (strpos($e->getMessage(), 'store.sql_channel.get_by_name.missing.app_error'))) {
                $this->logger->info("Channel does not belong to team, hence ceating channel");
                $channelData = $this->createChannel($channel, $accountName);
                $channelData = json_decode($channelData['body'], true);
                return $channelData;
            }
            $this->logger->error("Channel does not exist", $e);
        }
    }

    private function getTeamMember($userId, $teamId)
    {
        try {
            $headers = $this->getAuthHeader();
            $response = $this->restClient->get('api/v4/teams/' . $teamId . '/members/' . $userId, array(), $headers);
            $teamMember = json_decode($response, true);
            return json_decode($response, true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->error("User not in Team", $e);
        }
    }

    public function createTeam($accountName)
    {
        try {
            $headers = $this->getAuthHeader();
            $headers["Content-type"] = "application/json";
            $accountName = $this->sanitizeName($accountName);
            if (empty($accountName)) {
                $this->logger->info(" Account Name is missing");
                return;
            }
            $response = $this->restClient->postWithHeader('api/v4/teams', array('name' => $accountName, 'display_name' => $accountName, 'type' => 'O'), $headers);
            return $response;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->error("A team with that name already exists", $e);
        }
    }

    public function updateTeam($oldName, $newName)
    {
        try {
            $headers = $this->getAuthHeader();
            $oldName = $this->sanitizeName($oldName);
            $newName = $this->sanitizeName($newName);
            if (empty($newName)) {
                $this->logger->info("New Team Name is missing");
                return;
            }
            if (empty($oldName)) {
                $this->logger->info("Old Team Name is missing");
                return;
            }
            $json = $this->getTeamByName($oldName);
            $response = $this->restClient->put('api/v4/teams/' . $json['id'], array('name' => $newName, 'display_name' => $newName, 'id' => $json['id']), $headers);
            return $response;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->error("Account Does not exist", $e);
        }
    }

    public function deleteAccount($accountName)
    {
        try {
            $headers = $this->getAuthHeader();
            $accountName = $this->sanitizeName($accountName);
            $json = $this->searchTeam($accountName);
            if (empty($json)) {
                $this->logger->info("Account with the given name does not exist");
                return;
            }
            $response = $this->restClient->delete('api/v4/teams/' . $json[0]['id'], array('permanent' => 'false'), $headers);
            return $response;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->error("Account Deletion Failed", $e);
        }
    }

    public function addUserToTeam($user, $accountName)
    {
        try {
            $user = $this->sanitizeName($user);
            $accountName = $this->sanitizeName($accountName);
            $headers = $this->getAuthHeader();
            if (empty($user)) {
                $this->logger->info("No User Name Found To Add to team");
                return;
            }
            if (empty($accountName)) {
                $this->logger->info("No Team Name Found To Add the user");
                return;
            }
            // Checking if team exists, if not create team
            $team = $this->getTeamByName($accountName, true);

            // Check if user exists, if not create user
            $userData = $this->getUserByUsername($user, true);
            $response = $this->restClient->postWithHeader('api/v4/teams/' . $team['id'] . '/members', array('team_id' => $team['id'], 'user_id' => $userData['id']), $headers);
            $response = json_decode($response['body'], true);
            return $response;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->error("User Already in team", $e);
        }
    }

    public function removeUserFromTeam($user, $accountName)
    {
        try {
            $headers = $this->getAuthHeader();
            $user = $this->sanitizeName($user);
            $accountName = $this->sanitizeName($accountName);
            if (empty($user)) {
                $this->logger->info("No User Name Found To Remove from team");
                return;
            }
            if (empty($accountName)) {
                $this->logger->info("No Team Name Found To Remove user");
                return;
            }
            $userData = $this->getUserByUsername($user);
            $team = $this->getTeamByName($accountName);
            $response = $this->restClient->delete('api/v4/teams/' . $team['id'] . '/members/' . $userData['id'], array(), $headers);
            return $response;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->error("User is not in the Team/User Or Team name is missing", $e);
        }
    }

    public function createChannel($channel, $accountName)
    {
        try {
            if (empty($channel)) {
                $this->logger->info("No Channel Name Found To create");
                return;
            }
            if (empty($accountName)) {
                $this->logger->info("No Team Name Found To create");
                return;
            }
            $team = $accountName;
            $headers = $this->getAuthHeader();
            $channel = $this->sanitizeName($channel);
            if (!is_array($accountName)) {
                $accountName = $this->sanitizeName($accountName);
                $team = $this->getTeamByName($accountName, true);
            }
            $response = $this->restClient->postWithHeader('api/v4/channels', array('team_id' => $team['id'], 'name' => $channel, 'display_name' => $channel, 'type' => 'P'), $headers);
            return $response;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->error("Create Channel Failed", $e);
        }
    }

    public function deleteChannel($channel, $accountName)
    {
        try {
            $headers = $this->getAuthHeader();
            $channel = $this->sanitizeName($channel);
            $accountName = $this->sanitizeName($accountName);
            if (empty($channel)) {
                $this->logger->info("Deletion Failed - Channel Name not specified");
                return;
            }
            if (empty($accountName)) {
                $this->logger->info("Deletion Failed - Team Name not specified");
                return;
            }
            $channelData = $this->getChannelByName($channel, $accountName);
            $response = $this->restClient->delete('api/v4/channels/' . $channelData['id'], array(), $headers);
            return $response;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->error("Channel/Team Doesn't exist", $e);
        }
    }

    public function updateChannel($oldChannel, $newChannel, $accountName)
    {
        try {
            $headers = $this->getAuthHeader();
            $accountName = $this->sanitizeName($accountName);
            $oldChannel = $this->sanitizeName($oldChannel);
            $newChannel = $this->sanitizeName($newChannel);
            if (empty($oldChannel)) {
                $this->logger->info("No Channel Name specified to Update");
                return;
            }
            if (empty($newChannel)) {
                $this->logger->info("No Name Found To Update");
                return;
            }
            $channelData = $this->getChannelByName($oldChannel, $accountName);
            $response = $this->restClient->put('api/v4/channels/' . $channelData['id'], array('id' => $channelData['id'], 'name' => $newChannel, 'display_name' => $newChannel), $headers);
            return $response;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->error("Update Channel Failed", $e);
        }
    }

    public function addUserToChannel($user, $channel, $accountName)
    {
        try {
            $headers = $this->getAuthHeader();
            $user = $this->sanitizeName($user);
            $channel = $this->sanitizeName($channel);
            $accountName = $this->sanitizeName($accountName);

            if (empty($user)) {
                $this->logger->info("No User to Add to the channel ");
                return;
            }
            $team = $this->getTeamByName($accountName, true);
            $channelData = $this->getChannelByName($channel, $team, true);
            $userData = $this->getUserByUsername($user, true);
            $teamMember = $this->getTeamMember($userData['id'], $team['id']);
            if (!isset($teamMember['user_id'])) {
                $this->logger->info("User not part of team, adding to the team");
                $teamMember = $this->addUserToTeam($user, $accountName);
            }
            $response = $this->restClient->postWithHeader('api/v4/channels/' . $channelData['id'] . '/members', array('user_id' => $userData['id']), $headers);
            return $response;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->error("Adding User to channel Failed", $e);
        }
    }

    public function removeUserFromChannel($user, $channel, $accountName)
    {
        try {
            $headers = $this->getAuthHeader();
            $user = $this->sanitizeName($user);
            $channel = $this->sanitizeName($channel);
            $accountName = $this->sanitizeName($accountName);
            if (empty($user)) {
                $this->logger->info("No User Name Found To Remove from the team");
                return;
            }
            $team = $this->getTeamByName($accountName);

            $channelData = $this->getChannelByName($channel, $accountName);

            $userData = $this->getUserByUsername($user);

            $teamMember = $this->getTeamMember($userData['id'], $team['id']);

            // User in channel check
            $channelMember = $this->restClient->get('api/v4/channels/' . $channelData['id'] . '/members/' . $userData['id'], array(), $headers);
            if (!isset($channelMember)) {
                $this->logger->info("Removal Failed - User not in channel");
                return;
            }
            $response = $this->restClient->delete('api/v4/channels/' . $channelData['id'] . '/members/' . $userData['id'], array(), $headers);
            return $response;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->error($e->getMessage(), $e);
            $this->logger->info("Removing User from channel Failed");
        }
    }

    public function saveBot($botParams)
    {
        try {
            $headers = $this->getAuthHeader();
            $headers["Content-type"] = "application/json";
            $botName = $this->sanitizeName($botParams['botName']);
            if (empty($botName)) {
                $this->logger->info("Bot Name is missing");
                return;
            }
            $displayName = isset($botParams['displayName']) ? $botParams['displayName'] : $botName;
            $userDetails = $this->getUserByUsername($botName, false);
            $this->logger->info("USER DETILS--".print_r($userDetails, true));
            if (isset($userDetails) && $userDetails != 404) {
                if (count($userDetails) > 0) {
                    if ($userDetails['delete_at'] == 0) {
                        //Update bot
                        $response = $this->restClient->put('api/v4/bots/' . $userDetails['id'], array('display_name' => $displayName), $headers);
                        if (isset($botParams['profileImage']) && !empty($botParams['profileImage'])) {
                            $this->updateProfileImage($botName, $botParams['profileImage']);
                        }
                    } else {
                        //Enable bot
                        $response = $this->enableBot($botName);
                    }
                }
            } else {
                //Create bot
                $response = $this->restClient->postWithHeader('api/v4/bots', array('username' => $botName, 'display_name' => $displayName, 'description' => 'BOT for '.$botName), $headers);
            }
            return $response;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }

    public function updateProfileImage($botName, $img)
    {
        try {
            $headers = $this->getAuthHeader();
            $botName = $this->sanitizeName($botName);
            if (empty($botName)) {
                $this->logger->info("Bot Name is missing");
                return;
            }
            $userDetails = $this->getUserByUsername($botName, false);
            $response = $this->restClient->postMultiPart('api/v4/users/' . $userDetails['id'].'/image', array(), array("image" => $img), $headers);
            return $response;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }

    public function disableBot($botName)
    {
        try {
            $headers = $this->getAuthHeader();
            $headers["Content-type"] = "application/json";
            $botName = $this->sanitizeName($botName);
            if (empty($botName)) {
                $this->logger->info("Bot Name is missing");
                return;
            }
            $botDetails = $this->getUserByUsername($botName, false);
            if (isset($botDetails) && count($botDetails) > 0) {
                $response = $this->restClient->postWithHeader('api/v4/bots/' . $botDetails['id'].'/disable', array(), $headers);
                return $response;
            } else {
                $this->logger->info("No Bot with the specified name was found");
                return 0;
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }

    private function enableBot($botName)
    {
        try {
            $headers = $this->getAuthHeader();
            $headers["Content-type"] = "application/json";
            $botName = $this->sanitizeName($botName);
            if (empty($botName)) {
                $this->logger->info("Bot Name is missing");
                return;
            }
            $botDetails = $this->getUserByUsername($botName, false);
            if (isset($botDetails) && count($botDetails) > 0) {
                $response = $this->restClient->postWithHeader('api/v4/bots/' . $botDetails['id'].'/enable', array(), $headers);
                return $response;
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }

    public function appBotNotification($params)
    {
        $this->logger->info("appBotNotification--".print_r($params, true));
        try {
            $headers = $this->getAuthHeader();
            $appDetails = $this->fileService->getAppDetailsBasedOnFileId($params['fileId']);
            $fileDetails = $this->fileService->getFile($params['fileId'], false,$appDetails['account_id']);
            $title = $fileDetails['title'];
            $this->logger->info("APP Title--".print_r($title,true));
            $url = "<a eoxapplication=" .'"'.$appDetails['appName']. '"'. " "."file-id=" .'"'.$params['fileId'] . '"'. "/>";
            $this->logger->info("APP URL--".print_r($url, true));
            $subscribers =  $this->subscriberService->getSubscribers($params['fileId']);
            $subscribersToList = array_column($subscribers, 'username');
            $subscribersList = implode(',', $subscribersToList);
            $this->logger->info("appBotUrl---".print_r($this->appBotUrl, true));
            $botName = $this->sanitizeName($appDetails['appName']);
            $payLoad = array('botName' => $botName, 'message' => $params['message'],'from' => $params['from'],'toList' => $subscribersList, 'identifier' =>$params['fileId'] , 'title' => rtrim($title,"-"), 'url' => $url, 'commentId' => $params['commentId'], 'fileIds' => $params['fileIds']);
            $this->logger->info("Payload---".print_r($payLoad,true));
            $response = $this->restClient->postWithHeader($this->appBotUrl. 'appbot', $payLoad, $headers);
            $this->logger->info("App Bot response---".print_r($response, true));
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }

    public function getUser($userId){
        try {
            $headers = $this->getAuthHeader();
            $userData = $this->restClient->get('api/v4/users/' . $userId, array(), $headers);
            return json_decode($userData, true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return $e->getCode();
        }
    }

    private function updateUser($user){
        try {
            $headers = $this->getAuthHeader();
            $userID = $this->getUserByUsername($user);
            $userInfo = $this->userService->getUserBaseProfile($user);
            if ($userInfo) {
                $userData = $this->restClient->put('api/v4/users/' . $userID, array('first_name' =>$userInfo['firstname'] ,'last_name' => $userInfo['lastname'], 'email' => $userInfo['email']), $headers);
                return json_decode($userData['body'], true);
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }

}
