<?php
namespace Callback\Controller;

use Callback\Service\ChatService;
use Exception;
use Oxzion\Controller\AbstractApiControllerHelper;

class ChatCallbackController extends AbstractApiControllerHelper
{
    private $chatService;
    protected $log;
    /**
     * @ignore __construct
     */
    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
        $this->log = $this->getLogger();
    }

    public function addAccountAction()
    {
        $params = $this->extractPostData();
        $this->log->info("Account Add Params- " . json_encode($params));
        $params['accountName'] = isset($params['accountName']) ? $params['accountName'] : null;
        $response = $this->chatService->createTeam($params['accountName']);
        if ($response) {
            $this->log->info(ChatCallbackController::class . ":Account Added");
            return $this->getSuccessResponseWithData(json_decode($response['body'], true));
        }
        return $this->getErrorResponse("Account Creation Failed", 400);
    }

    public function updateAccountAction()
    {
        $params = $this->extractPostData();
        $params['old_accountName'] = isset($params['old_accountName']) ? $params['old_accountName'] : null;
        $params['new_accountName'] = isset($params['new_accountName']) ? $params['new_accountName'] : null;
        $response = $this->chatService->updateTeam($params['old_accountName'], $params['new_accountName']);
        if ($response) {
            $this->log->info("Account Updated");
            return $this->getSuccessResponseWithData(json_decode($response, true));
        }
        return $this->getErrorResponse("Account Update Failure", 404);
    }

    public function deleteAccountAction()
    {
        $params = $this->extractPostData();
        $response = $this->chatService->deleteAccount($params['accountName']);
        if ($response) {
            $this->log->info("Account Deleted");
            return $this->getSuccessResponseWithData(json_decode($response, true));
        }
        return $this->getErrorResponse("Account Deletion Failed", 400);
    }

    public function addUserAction()
    {
        $params = $this->extractPostData();
        $params['username'] = isset($params['username']) ? $params['username'] : null;
        $params['accountName'] = isset($params['accountName']) ? $params['accountName'] : null;
        $response = $this->chatService->addUserToTeam($params['username'], $params['accountName']);
        if ($response) {
            $this->log->info("Added user to Account");
            return $this->getSuccessResponseWithData($response);
        }
        return $this->getErrorResponse("Adding User To Team Failure ", 400);
    }

    public function removeUserAction()
    {
        $params = $this->extractPostData();
        $params['username'] = isset($params['username']) ? $params['username'] : null;
        $params['accountName'] = isset($params['accountName']) ? $params['accountName'] : null;

        $response = $this->chatService->removeUserFromTeam($params['username'], $params['accountName']);
        if ($response) {
            $this->log->info("Removed user from Account");
            return $this->getSuccessResponseWithData(json_decode($response, true));
        }
        return $this->getErrorResponse("Remove User From Team Failure ", 404);
    }

    public function createChannelAction()
    {
        $params = $this->extractPostData();
        $params['teamname'] = isset($params['teamname']) ? $params['teamname'] : null;
        $params['accountName'] = isset($params['accountName']) ? $params['accountName'] : null;
        $params['channelname'] = isset($params['projectname']) ? ($params['projectname']) : ($params['teamname']);
        $this->log->info(":Channel Name- " . $params['channelname']);
        $response = $this->chatService->createChannel($params['channelname'], $params['accountName']);
        if ($response) {
            $this->log->info(ChatCallbackController::class . ":Project/Team Creation Successful");
            return $this->getSuccessResponseWithData(json_decode($response['body'], true));
        }
        return $this->getErrorResponse("Creation of Channel Failed", 400);
    }

    public function deleteChannelAction()
    {
        $params = $this->extractPostData();
        $params['teamname'] = isset($params['teamname']) ? $params['teamname'] : null;
        $params['accountName'] = isset($params['accountName']) ? $params['accountName'] : null;
        $params['channelname'] = isset($params['projectname']) ? ($params['projectname']) : ($params['teamname']);
        $response = $this->chatService->deleteChannel($params['channelname'], $params['accountName']);
        if ($response) {
            $this->log->info(":Project/Team Deleted");
            return $this->getSuccessResponseWithData(json_decode($response, true));
        }
        return $this->getErrorResponse("Channel Deletion Failed", 400);
    }

    public function updateChannelAction()
    {
        $params = $this->extractPostData();
        $params['old_teamname'] = isset($params['old_teamname']) ? $params['old_teamname'] : null;
        $params['new_teamname'] = isset($params['new_teamname']) ? $params['new_teamname'] : null;
        $params['old_channelname'] = isset($params['old_projectname']) ? ($params['old_projectname']) : ($params['old_teamname']);

        $params['new_channelname'] = isset($params['new_projectname']) ? ($params['new_projectname']) : ($params['new_teamname']);
        $response = $this->chatService->updateChannel($params['old_channelname'], $params['new_channelname'], $params['accountName']);
        if ($response) {
            $this->log->info(":Project/Team Updated Successful");
            return $this->getSuccessResponseWithData(json_decode($response, true));
        }
        return $this->getErrorResponse("Update to Channel Failed", 404);
    }

    public function adduserToChannelAction()
    {
        $params = $this->extractPostData();
        $params['username'] = isset($params['username']) ? $params['username'] : null;
        $params['channelname'] = isset($params['projectname']) ? ($params['projectname']) : ($params['teamname']);
        $response = $this->chatService->addUserToChannel($params['username'], $params['channelname'], $params['accountName']);
        if ($response) {
            $this->log->info("User to Project/Team added successfully");
            return $this->getSuccessResponseWithData(json_decode($response['body'], true));
        }
        return $this->getErrorResponse("Add User to Channel Failed", 400);
    }

    public function removeUserFromChannelAction()
    {
        $params = $this->extractPostData();
        $params['username'] = isset($params['username']) ? $params['username'] : null;
        $params['channelname'] = isset($params['projectname']) ? ($params['projectname']) : ($params['teamname']);
        $response = $this->chatService->removeUserFromChannel($params['username'], $params['channelname'], $params['accountName']);
        if ($response) {
            $this->log->info("User from Project/Team removed successfully");
            return $this->getSuccessResponseWithData(json_decode($response, true));
        }
        return $this->getErrorResponse("Removing User from Channel Failed", 400);
    }

    public function saveBotAction()
    {
        $params = $this->extractPostData();
        $this->log->info("Save Bot Params- " . json_encode($params));
        try {
            $params['botName'] = isset($params['appName']) ? $params['appName'] : null;
            $response = $this->chatService->saveBot($params);
            if ($response) {
                $this->log->info(ChatCallbackController::class . ":Bot User Created / Updated");
                $response = is_array($response) ? $response['body'] : $response;
                return $this->getSuccessResponseWithData(json_decode($response, true));
            } else {
                return $this->getErrorResponse("Bot Name is missing", 400);
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->getErrorResponse($e->getMessage(), 400);
        }
    }

    public function disableBotAction()
    {
        $params = $this->extractPostData();
        $this->log->info("Disable Bot Params- " . json_encode($params));
        try {
            $botName = isset($params['appName']) ? $params['appName'] : null;
            $response = $this->chatService->disableBot($botName);
            if ($response) {
                return $this->getSuccessResponseWithData(json_decode($response['body'], true));
            } else {
                return $this->getErrorResponse("No Bot with the specified name was found", 400);
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->getErrorResponse($e->getMessage(), 400);
        }
    }

    public function appBotNotificationAction()
    {
        $params = $this->extractPostData();
        $this->log->info("appBotNotification Params- " . json_encode($params));
        try {
            $this->chatService->appBotNotification($params);
            return $this->getSuccessResponse();
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->getErrorResponse($e->getMessage(), $e->getCode());
        }
    }
}
