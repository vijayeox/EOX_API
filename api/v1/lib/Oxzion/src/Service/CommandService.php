<?php

/**
 * ServiceTask Callback Api
 */

namespace Oxzion\Service;

use Exception;
use Oxzion\AppDelegate\AppDelegateService;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\Document\DocumentGeneratorImpl;
use Oxzion\EntityNotFoundException;
use Oxzion\InvalidParameterException;
use Oxzion\Messaging\MessageProducer;
use Oxzion\ServiceException;
use Oxzion\OxServiceException;
use Oxzion\Service\AbstractService;
use Oxzion\Service\FileService;
use Oxzion\Service\JobService;
use Oxzion\Service\TemplateService;
use Oxzion\Service\UserService;
use Oxzion\Service\WorkflowInstanceService;
use Oxzion\Utils\RestClient;
use Oxzion\ValidationException;
use Oxzion\Utils\UuidUtil;
use Oxzion\Service\UserCacheService;
use Oxzion\Service\RegistrationService;
use Oxzion\Utils\ArrayUtils;
use Oxzion\Service\BusinessParticipantService;

class CommandService extends AbstractService
{
    /**
     * @var CommandService Instance of Task Service
     */
    private $templateService;
    protected $fileService;
    private $workflowInstanceService;
    private $userService;
    private $jobService;
    private $userCacheService;
    private $registrationService;
    private $businessParticipantService;
    /**
     * @ignore __construct
     */

    public function setRestClient($restClient)
    {
        $this->restClient = $restClient;
    }

    public function __construct($config, $dbAdapter, TemplateService $templateService, AppDelegateService $appDelegateService, FileService $fileService, JobService $jobService, MessageProducer $messageProducer, WorkflowInstanceService $workflowInstanceService, WorkflowService $workflowService, UserService $userService, UserCacheService $userCacheService, RegistrationService $registrationService, BusinessParticipantService $businessParticipantService)
    {
        $this->messageProducer = $messageProducer;
        $this->templateService = $templateService;
        $this->fileService = $fileService;
        $this->appDelegateService = $appDelegateService;
        $this->workflowInstanceService = $workflowInstanceService;
        parent::__construct($config, $dbAdapter);
        $this->fileService = $fileService;
        $this->workflowService = $workflowService;
        $this->restClient = new RestClient($this->config['job']['jobUrl'], array());
        $this->userService = $userService;
        $this->jobService = $jobService;
        $this->userCacheService = $userCacheService;
        $this->registrationService = $registrationService;
        $this->businessParticipantService = $businessParticipantService;
    }

    public function setMessageProducer($messageProducer)
    {
        $this->messageProducer = $messageProducer;
    }

    public function runCommand(&$data, $request)
    {
        $this->logger->info("RUN COMMAND  ------" . json_encode($data));
        //TODO Execute Command Service Methods
        if (isset($data['appId'])) {
            $appId = $this->getIdFromUuid('ox_app', $data['appId']);
            $this->logger->info("RUN COMMAND appid form query" . $appId);
            $accountId = isset($data['accountId']) && !empty($data['accountId']) ? $this->getIdFromUuid('ox_account', $data['accountId']) : AuthContext::get(AuthConstants::ACCOUNT_ID);
            if (empty($accountId) && isset($data['accountName'])) {
                $select = "SELECT id from ox_account where `name` = :accountName";
                $selectQuery = array("accountName" => $data['accountName']);
                $this->logger->info("Executing query $select with params - " . json_encode($selectQuery));
                $result = $this->executeQuerywithBindParameters($select, $selectQuery)->toArray();
                $accountId = !empty($result) ? $result[0]['id'] : null;
            }
            if ($accountId) {
                $select = "SELECT * from ox_app_registry where account_id = :accountId AND app_id = :appId";
                $selectQuery = array("accountId" => $accountId, "appId" => $appId);
                $this->logger->info("Executing query $select with params - " . json_encode($selectQuery));
                $result = $this->executeQuerywithBindParameters($select, $selectQuery)->toArray();
                if (count($result) == 0) {
                    throw new ServiceException("App Does not belong to the account", "app.for.account.not.found");
                }
            } elseif ((isset($data['command']) && $data['command'] != 'register_account') || (isset($data['commands']) && $data['commands'][0] != 'register_account')) {
                throw new ServiceException("You are not authorized to use this command", "app.for.register account");
            }
            $data['app_id'] = $appId;
        }
        if (isset($data['command'])) {
            $this->logger->info("COMMAND  ------" . print_r($data['command'], true));
            $command = $data['command'];
            unset($data['command']);
            $response = $this->processCommand($data, $command, $request);
            unset($response['app_id']);
            return $response;
        } elseif (isset($data['commands'])) {
            $this->logger->info("Command Service - Comamnds");
            $commands = $data['commands'];
            $this->logger->info("COMMAND LIST ------" . print_r($commands, true));
            unset($data['commands']);
            $inputData = $data;
            foreach ($commands as $index => $value) {
                $isArray = is_array($value);
                if (!$isArray) {
                    $commandJson = json_decode($value, true);
                    if (empty($commandJson)) {
                        $commandJson = array("command" => $value);
                    }
                } else {
                    $commandJson = $value;
                }
                $this->logger->info("Command JSON------", print_r($commandJson, true));
                if (isset($commandJson['command'])) {
                    $this->logger->info("COMMAND------", print_r($commandJson, true));
                    $command = $commandJson['command'];
                    unset($commandJson['command']);
                    $outputData = array_merge($inputData, $commandJson);
                    $this->logger->info(CommandService::class . print_r($outputData, true));
                    $this->logger->info("COMMAND LIST ------" . $command);
                    $result = $this->processCommand($outputData, $command, $request);
                    $this->logger->info("Process Command Result" . print_r($result, true));
                    if (is_array($result)) {
                        $inputData = $result;
                        $inputData['app_id'] = isset($data['app_id']) ? $data['app_id'] : null;
                        $inputData['accountId'] = isset($data['accountId']) ? $data['accountId'] : (isset($inputData['accountId']) ? $inputData['accountId'] : null);
                        $inputData['workFlowId'] = isset($data['workFlowId']) ? $data['workFlowId'] : null;
                        $outputData = array_merge($outputData, $result);
                    }
                }
                $this->logger->info(CommandService::class . print_r($inputData, true));
            }
            if (isset($outputData)) {
                $this->logger->info("Process Command Data" . print_r($outputData, true));
                unset($outputData['app_id']);
                return $outputData;
            }
        }
    }

    protected function processCommand(&$data, $command, $request)
    {
        $this->logger->info("PROCESS COMMAND : command --- " . $command);
        switch ($command) {
            case 'register_account':
                return $this->registerAccount($data);
                break;
            case 'sign_in':
                $data['auto_login'] = 1;
                return $data;
                break;
            case 'store_cache_data':
                return $this->storeCacheData($data);
                break;
            case 'mail':
                $this->logger->info("SEND MAIL");
                return $this->sendMail($data);
                break;
            case 'route':
                $this->logger->info("Get Route Data");
                $this->getRouteData($data, $request);
                break;
            case 'schedule':
                $this->logger->info("SCHEDULE JOB");
                return $this->scheduleJob($data);
                break;
            case 'cancelJob':
                $this->logger->info("CLEAR JOB");
                return $this->cancelJob($data);
                break;
            case 'delegate':
                $this->logger->info("DELEGATE");
                return $this->executeDelegate($data);
                break;
            case 'pdf':
                $this->logger->info("PDF");
                return $this->generatePDF($data);
                break;
            case 'fileSave':
                $this->logger->info("FILE SAVE");
                return $this->fileSave($data);
                break;
            case 'file':
                $this->logger->info("FILE DATA");
                return $this->extractFileData($data);
                break;
            case 'filelist':
                $this->logger->info("FILE LIST");
                return $this->getFileWithParams($data);
                break;
            case 'startform':
                $this->logger->info("START FORM");
                return $this->getStartForm($data);
                break;
            case 'register_user':
            case 'create_user':
                $this->logger->info("START FORM");
                return $this->registerUser($data);
                break;
            case 'verify_user':
                $this->logger->info("Verify User");
                return $this->verifyUser($data);
                break;
            case 'get_user':
                $this->logger->info("Get User Identifiers By UserId");
                return $this->getUserIdentifier($data);
                break;
            case 'getuserdata':
                $this->logger->info("GET User Data");
                return $this->getUserData($data);
                break;
            case 'getuserlist':
                $this->logger->info("GET User LIST");
                return $this->getUserList($data);
                break;
            case 'startWorkflow':
                $this->logger->info("START WORKFLOW");
                return $this->startWorkflow($data);
                break;
            case 'submitActivity':
                $this->logger->info("SUBMIT ACTIVITY");
                return $this->submitActivity($data);
                break;
            case 'processFileData':
                $this->logger->info("Process File Data");
                return $this->processFileData($data);
                break;
            case 'submitWorkflow':
                $this->logger->info("SUBMIT WORKFLOW");
                return $this->submitWorkflow($data);
                break;
            case 'claimForm':
                $this->logger->info("Claim Form");
                return $this->claimActivityInstance($data);
                break;
            case 'activityInstanceForm':
                $this->logger->info("Activity Instance Form");
                return $this->getActivityInstanceForm($data);
                break;
            case 'snooze':
                $this->logger->info("Snooze File");
                return $this->snoozeFile($data);
            case 'setupBusinessRelationship':
                return $this->setupBusinessRelationship($data);
            default:
                break;
        };
    }

    private function setupBusinessRelationship($data)
    {
        if (isset($data['sellerAccountName'])) {
            $response =  $this->getDataByParams('ox_account', array('uuid'), array('name' => $data['sellerAccountName']))->toArray();
            if (count($response) > 0) {
                $sellerAccountId = $response[0]['uuid'];
            } else {
                throw new ServiceException("Seller Account Not Found", "seller.account.notfound", OxServiceException::ERR_CODE_NOT_FOUND);
            }
        } else if (isset($data['sellerAccountId'])) {
            $sellerAccountId = $data['sellerAccountId'];
        } else {
            $sellerAccountId = AuthContext::get(AuthConstants::ACCOUNT_UUID);
        }
        $buyerAccountId = isset($data['buyerAccountId']) ? $data['buyerAccountId'] : (isset($data['accountId']) ? $data['accountId'] : null);
        $buyerBusinessRole  = $data['businessRole'];
        $sellerBusinessRole = $data['sellerBusinessRole'];
        $appId = $data['appId'];
        return $this->businessParticipantService->setupBusinessRelationship($buyerAccountId, $sellerAccountId, $buyerBusinessRole, $sellerBusinessRole, $appId);
    }

    private function snoozeFile($data)
    {
        if (isset($data['fileId']) && isset($data['snoozePipeline'])) {
            $result = $this->fileService->snoozeFile([
                'fileId' => $data['fileId'],
                'snooze' => $data['snoozePipeline']
            ]);
            unset($data['snoozePipeline']);
            return $data;
        } else {
            throw new EntityNotFoundException("fileId or snoozePipeline not specified");
        }
    }

    private function registerAccount($data)
    {
        $this->registrationService->registerAccount($data);
        $params = $data;
        $params['user'] = $data['contact'];
        return $params;
    }

    private function storeCacheData($data)
    {
        $rawData = $data;

        if (isset($data['user']) && is_array($data['user']) && ArrayUtils::isKeyDefined($data['user'], 'username')) {
            $user = $this->userService->getUserDetailsbyUserName($data['user']['username']);
        } elseif (ArrayUtils::isKeyDefined($data, 'username')) {
            $user = $this->userService->getUserDetailsbyUserName($data['username']);
        } else {
            throw new ServiceException("username is required", '.username.required', OxServiceException::ERR_CODE_NOT_ACCEPTABLE);
        }
        if (!isset($user)) {
            throw new ServiceException("User not found", 'user.not.found', OxServiceException::ERR_CODE_NOT_FOUND);
        }
        if (isset($data['appId'])) {
            if ($app = $this->getIdFromUuid('ox_app', $data['appId'])) {
                $appId = $app;
            }
        } else {
            $appId = null;
        }
        $rawData['user_id'] = $user['id'];
        $rawData['app_id'] = $appId;
        $userCache = $this->userCacheService->storeUserCache($appId, $rawData);
        $cacheData = array('user_id' => $user['id'], 'content' => json_encode($userCache), 'app_id' => $appId);
        $data['cache_data'] = $cacheData;
        return $data;
    }

    private function enqueue($data, $topic, $queue = null)
    {
        $this->logger->info("ENQUEUE ------ DATA IS:  " . print_r($data, true));
        $this->logger->info("ENQUEUE ------ TOPIC IS:  " . print_r($topic, true));
        $accountId = AuthContext::get(AuthConstants::ACCOUNT_UUID);
        $accountIdAdded = false;
        if (isset($accountId)) {
            $data['accountId'] = $accountId;
            $accountIdAdded = true;
        }
        if ($topic) {
            $this->logger->info("ENQUEUE ------ send topic ");
            $this->messageProducer->sendTopic(json_encode($data), $topic);
        } elseif ($queue) {
            $this->logger->info("ENQUEUE ------ sendqueue ");
            $this->messageProducer->sendQueue(json_encode($data), $queue);
        }
        if ($accountIdAdded) {
            unset($data['accountId']);
        }
        return $data;
    }

    protected function getRouteData(&$data, $request)
    {
        $this->logger->info("EXECUTE DELEGATE inside route data ---- " . print_r($data, true));
        if (isset($data['app_id']) && isset($data['route'])) {
            $app_id = $data['app_id'];
        } else {
            $this->logger->info("App Id or Delegate Not Specified");
            throw new EntityNotFoundException("App Id or Delegate Not Specified");
        }
        $this->logger->info("DELEGATE ---- " . print_r($route, true));
        $this->logger->info("DELEGATE APP ID---- " . print_r($app_id, true));
        $headers = $request->getHeaders()->toArray();
        $restClient = new RestClient($headers['Host'], array('exceptions' => false, 'timeout' => 0));
        $route = "/app/" . $data['app_uuid'] . "/" . $route;
        $response = json_decode($restClient->get($route, array(), $headers), true);
        if ($response['status'] == 'success') {
            $data['result'] = $response['data'];
            if (isset($response['total'])) {
                $data['total'] = $response['total'];
            }
        }
    }

    protected function scheduleJob(&$data)
    {
        $this->logger->info("DATA  ------" . json_encode($data));
        if (!isset($data['jobUrl']) || !isset($data['cron']) || !isset($data['jobName'])) {
            $this->logger->info("jobUrl/Cron/JobName Not Specified");
            throw new EntityNotFoundException("JobUrl or Cron Expression or JobName Not Specified");
        }

        $jobUrl = $data['jobUrl'];
        $cron = $data['cron'];
        $jobGroup = $data['jobName'];
        if (isset($data['fileId'])) {
            $jobName = $data['fileId'];
        } else {
            $jobName = $data['uuid'];
        }
        $appId = isset($data['app_id']) ? $data['app_id'] : null;
        if (!$appId) {
            throw new InvalidParameterException("App Id not provided");
        }

        $accountId = isset($data['account_id']) ? $data['account_id'] : AuthContext::get(AuthConstants::ACCOUNT_ID);
        unset($data['jobUrl'], $data['cron'], $data['command'], $data['url']);
        $this->logger->info("JOB DATA ------" . json_encode($data));
        $jobPayload = array("job" => array("url" => $this->config['internalBaseUrl'] . $jobUrl, "data" => $data), "schedule" => array("cron" => $cron));
        $this->logger->info("JOB PAYLOAD ------" . print_r($jobPayload, true));
        $response = $this->jobService->scheduleNewJob($jobName, $jobGroup, $jobPayload, $cron, $appId, $accountId);
        if (isset($response) && isset($response['job_id'])) {
            $jobData = array("jobId" => $response['job_id'], "jobGroup" => $response['group_name']);
            $data[$jobGroup] = json_encode($jobData);
        }
        $this->logger->info("Schedule JOB DATA - " . print_r($data, true));
        $this->fileService->updateFile($data, $data['fileId']);
        return $data;
    }

    protected function cancelJob(&$data)
    {
        $this->logger->info("DATA  ------" . json_encode($data));
        if (!isset($data['jobName'])) {
            $this->logger->warn("Job Name not specified, so job not cancelled");
            return $data;
        }
        $jobName = $data['jobName'];
        if (!isset($data[$jobName])) {
            $this->logger->warn("Job Details not found, so job not cancelled");
            return $data;
        }
        $JobData = (is_array($data[$jobName]) ? $data[$jobName] : json_decode($data[$jobName], true));

        if ((isset($JobData['jobId']) && isset($JobData['jobGroup'])) || (isset($JobData['jobName']) && isset($JobData['jobGroup']))) {

            $appId = isset($data['appId']) ? $data['appId'] : null;
            if (!$appId) {
                throw new InvalidParameterException("App Id not provided");
            }

            $groupName = $JobData['jobGroup'];
            $data[$jobName] = array();

            if (isset($JobData['jobId'])) {
                $this->jobService->cancelJobId($JobData['jobId'], $appId, $groupName);
            } else if (isset($JobData['jobName'])) {
                $accountId = isset($data['account_id']) ? $data['account_id'] : AuthContext::get(AuthConstants::ACCOUNT_ID);
                $this->jobService->cancelJob($JobData['jobName'], $groupName, $appId, $accountId);
            }

            $this->logger->info("Cancel Job Data - " . print_r($data, true));
            return $data;
        } else {
            $this->logger->warn("Invalid set of parameters specified, so job not cancelled");
            return $data;
        }
    }

    protected function fileSave(&$data)
    {
        $this->logger->info("File Save Service Start" . print_r($data, true));
        $fileId = isset($data['fileId']) ? $data['fileId'] : (isset($data['uuid']) ? $data['uuid'] : null);
        if ($fileId) {
            $this->logger->info("File id" . print_r($fileId, true));
            $file = $this->fileService->updateFile($data, $fileId);
            $this->logger->info("File id" . print_r($file, true));
        } elseif (isset($data['workflow_instance_id'])) {
            $select = "SELECT ox_file.uuid from ox_file 
                        join ox_workflow_instance on ox_workflow_instance.file_id = ox_file.id 
                        where ox_workflow_instance.id=:workflowInstanceId;";
            $selectParams = array("workflowInstanceId" => $data['workflow_instance_id']);
            $this->logger->info("Executing query $select using params - " . json_encode($selectParams));
            $result = $this->executeQueryWithBindParameters($select, $selectParams)->toArray();
            if (count($result) == 0) {
                $this->logger->info("File Save ---- Workflow Instance Id Not Found");
                throw new EntityNotFoundException("Workflow Instance Id Not Found");
            }
            $file = $this->fileService->updateFile($data, $result[0]['uuid']);
        } else {
            $filedata = $data;
            $file = $this->fileService->createFile($filedata);
            $data['fileId'] = $filedata['uuid'];
            $data['uuid'] = $filedata['uuid'];
            $this->logger->info("File data" . print_r($data, true));
        }
        $this->logger->info("File Save Service end" . print_r($data, true));
        return $data;
    }

    protected function executeDelegate(&$data)
    {
        $this->logger->info("EXECUTE DELEGATE inside execute delegate ---- " . print_r($data, true));
        $data['appId'] = !empty($data['appId']) ? $data['appId'] : $data['app_id'];
        if (!empty($data['appId']) && isset($data['delegate'])) {
            $app_id = $data['appId'];
            $delegate = $data['delegate'];
            unset($data['delegate']);
        } else {
            $this->logger->info("App Id or Delegate Not Specified");
            throw new EntityNotFoundException("App Id or Delegate Not Specified");
        }
        if (isset($data['async']) && $data['async'] == 'true') {
            unset($data['async']);
            $temp = $data;
            $temp['commands'] = [array('command' => 'delegate', 'delegate' => $delegate)];
            $this->logger->info("EXECUTE DELEGATE ---- enqueue");
            $this->enqueue($temp, 'COMMANDS');
            return $data;
        } else {
            $this->logger->info("DELEGATE ---- " . print_r($delegate, true));
            $this->logger->info("DELEGATE APP ID---- " . print_r($app_id, true));
            $this->logger->info("DELEGATE DATA ---- " . print_r($data, true));
            $response = $this->appDelegateService->execute($app_id, $delegate, $data);
            return $response;
        }
    }

    protected function sendMail($params)
    {
        if (!$params || empty($params)) {
            return $params;
        }

        $template = isset($params['template']) ? $params['template'] : 0;
        if ($template) {
            $body = $this->templateService->getContent($template, $params);
        } else {
            if (isset($params['body'])) {
                $body = $params['body'];
            } else {
                $body = null;
            }
        }
        $errors = array();
        if (isset($params['to'])) {
            $recipients = $params['to'];
        } else {
            $errors['to'] = 'required';
        }
        if (isset($params['subject'])) {
            $subject = $params['subject'];
        } else {
            $errors['subject'] = 'required';
        }
        if (isset($params['attachments'])) {
            $attachments = $params['attachments'];
        }
        if (count($errors) > (int) 0) {
            $validationException = new ValidationException();
            $validationException->setErrors($errors);
            throw $validationException;
        }
        $payload = json_encode(array(
            'to' => $recipients,
            'subject' => $subject,
            'body' => $body,
            'attachments' => isset($attachments) ? $attachments : null,
        ));
        $this->logger->info("Payload for mail -> $payload");
        $this->messageProducer->sendQueue($payload, 'mail');
    }

    protected function generatePDF(&$params)
    {
        if (!$params || empty($params)) {
            return $params;
        }
        $template = isset($params['template']) ? $params['template'] : 0;
        if ($template) {
            $body = $this->templateService->getContent($template, $params);
        } else {
            if (isset($params['body'])) {
                $body = $params['body'];
            } else {
                $body = null;
            }
        }
        if (!$body) {
            return;
        }
        if (isset($params['options'])) {
            $options = $params['options'];
        } else {
            $options = null;
        }
        if (isset($params['destination'])) {
            $destination = $params['destination'];
        } else {
            return;
        }
        $generatePdf = new DocumentGeneratorImpl();
        $params['document_path'] = $generatePdf->generateDocument($body, $destination, $options);
        return $params;
    }

    protected function extractFileData(&$data)
    {
        $this->logger->info("File Data  ------" . print_r($data, true));
        if (isset($data['fileId_fieldName']) && isset($data[$data['fileId_fieldName']])) {
            $fileId = $data[$data['fileId_fieldName']];
        } elseif (isset($data['fileId'])) {
            $fileId = $data['fileId'];
        } elseif (isset($data['workflowInstanceId'])) {
            $file = $this->fileService->getFileByWorkflowInstanceId($data['workflowInstanceId']);
            if (isset($file)) {
                $data['data'] = $file['data'];
                $data['fileId'] = $file['fileId'];
                return $data;
            } else {
                throw new EntityNotFoundException("File not Found");
            }
        } else {
            throw new EntityNotFoundException("File Id not provided");
        }

        $result = $this->fileService->getFile($fileId, true);
        $this->logger->info("EXTRACT FILE DATA result" . print_r($result, true));
        if ($result == 0) {
            throw new EntityNotFoundException("File " . $fileId . " not found");
        }
        $data = array_merge($data, $result['data']);
        return $data;
    }

    protected function getFileWithParams(&$data)
    {
        $params = array();
        $filterParams = array();
        if (isset($data['workflowId'])) {
            $params['workflowId'] = $data['workflowId'];
        }
        if (isset($data['accountId'])) {
            $params['accountId'] = $data['accountId'];
        }
        if (isset($data['userId'])) {
            $params['userId'] = $data['userId'];
        }
        if (isset($data['appId']) && (UuidUtil::isValidUuid($data['appId']))) {
            $params['app_id'] = $data['appId'];
        } elseif (isset($data['app_id'])) {
            $params['app_id'] = $data['app_id'];
        }
        if (isset($data['workflowStatus'])) {
            $params['workflowStatus'] = $data['workflowStatus'];
        }
        if (isset($data['filter'])) {
            $filterParams['filter'] = $data['filter'];
        }
        $fileList = $this->fileService->getFileList($params['app_id'], $params, $filterParams);
        $data['data'] = $fileList['data'];
        return $data;
    }

    protected function getStartForm(&$data)
    {
        if (isset($data['workflow_id']) && isset($data['appId'])) {
            $workFlowId = $data['workflow_id'];
            $result = $this->workflowService->getStartForm($data['appId'], $workFlowId);
            if (isset($result['template'])) {
                $data['template'] = $result['template'];
            }
            $data['formName'] = $result['formName'];
            $data['id'] = $result['id'];
            return $data;
        } else {
            throw new ServiceException("App and Workflow not Found", "app.for.workflow.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
        }
    }

    protected function verifyUser(&$data)
    {
        // TODO IF IDENTIFIER FIELD IS NOT SET GET IT FROM ox_entity_user_relation LIKE IN setupIdentityField METHOD
        if (isset($data['identifier_field']) && isset($data['appId']) && isset($data[$data['identifier_field']])) {
            $appId = UuidUtil::isValidUuid($data['appId']) ? $this->getIdFromUuid('ox_app', $data['appId']) : $data['appId'];
            $select = "SELECT * from ox_wf_user_identifier where identifier_name = :identityField AND app_id = :appId AND identifier = :identifier";
            $selectQuery = array("identityField" => $data['identifier_field'], "appId" => $appId, "identifier" => $data[$data['identifier_field']]);
            $result = $this->executeQuerywithBindParameters($select, $selectQuery)->toArray();
            if (count($result) > 0) {
                $data['user_exists'] = '1';
                return $data;
            } else {
                $data['user_exists'] = '0';
                return $data;
            }
        }
        if (isset($data['email'])) {
            $select = "SELECT ox_user.*,oxup.firstname,oxup.lastname,oxup.date_of_birth,oxup.phone,oxup.gender,oxup.signature,oxup.address_id from ox_user inner join ox_person oxup on oxup.id = ox_user.person_id  where oxup.email = :email";
            $selectQuery = array("email" => $data['email']);
            $result = $this->executeQuerywithBindParameters($select, $selectQuery)->toArray();
            if (count($result) > 0) {
                $data['user_exists'] = '1';
                return $data;
            }
        }
    }
    protected function getUserIdentifier(&$data)
    {
        $userId = isset($data['user_id']) ? $this->getIdFromUuid('ox_user', $data['user_id']) : AuthContext::get(AuthConstants::USER_ID);
        if (isset($data['appId']) && isset($userId)) {
            $select = "SELECT * from ox_wf_user_identifier where app_id = :appId AND user_id = :user_id";
            $selectQuery = array("appId" => $data['app_id'], "user_id" => $userId);
            $result = $this->executeQuerywithBindParameters($select, $selectQuery)->toArray();
            if (count($result) > 0) {
                foreach ($result as $key => $value) {
                    $data[$value['identifier_name']] = $value['identifier'];
                }
                return $data;
            } else {
                $data['user_exists'] = '0';
                return $data;
            }
        } else {
            return $data;
        }
    }

    public function getUserData(&$data)
    {
        $userId = isset($data['user_id']) ? $this->getIdFromUuid('ox_user', $data['user_id']) : AuthContext::get(AuthConstants::USER_ID);
        if (isset($data['appId']) && isset($userId)) {
            $select = "SELECT * from ox_wf_user_identifier where app_id = :appId AND user_id = :user_id";
            $selectQuery = array("appId" => $data['app_id'], "user_id" => $userId);
            $result = $this->executeQuerywithBindParameters($select, $selectQuery)->toArray();
            if (count($result) > 0) {
                $result = $this->userService->getUserWithMinimumDetails($userId);
                unset($result['uuid']);
                unset($result['username']);
                $data = array_merge($data, $result);
                return $data;
            } else {
                $data['user_exists'] = '0';
                return $data;
            }
        } else {
            return $data;
        }
    }

    protected function getUserList(&$data)
    {
        if (isset($data['appId'])) {
            $data['userlist'] = $this->userService->getUsersList($data['appId'], $data);
        }
    }

    protected function startWorkflow(&$data)
    {
        $startWorkflow = $this->workflowInstanceService->startWorkflow($data);
        return $startWorkflow;
    }

    protected function submitActivity(&$data)
    {
        $submitActivity = $this->workflowInstanceService->submitActivity($data);
        return $submitActivity;
    }

    protected function submitWorkflow(&$data)
    {
        if (isset($data['activityInstanceId'])) {
            return $this->submitActivity($data);
        }
        return $this->startWorkflow($data);
    }

    // verify
    protected function processFileData(&$data)
    {
        $this->logger->info("Process File Data--");
        if (isset($data['data'])) {
            $this->logger->info("Process File Data --");
            $fileData = $data['data'];
            if (isset($fileData['uuid'])) {
                unset($fileData['uuid']);
            }
            unset($data['data']);
            $processedData = array_merge($data, $fileData);
            $this->logger->info("Processed Data" . print_r($processedData, true));
            foreach ($processedData as $key => $value) {
                if (is_array($value)) {
                    $processedData[$key] = json_encode($value);
                }
            }
            return $processedData;
        } else {
            return $data;
        }
    }

    protected function claimActivityInstance(&$data)
    {
        $this->logger->info("claimForm");
        if (isset($data['workflowInstanceId']) && isset($data['activityInstanceId'])) {
            $this->workflowInstanceService->claimActivityInstance($data);
        }
    }

    protected function getActivityInstanceForm(&$data)
    {
        $this->logger->info("InstanceForm");
        if (isset($data['workflowInstanceId']) && isset($data['activityInstanceId'])) {
            $result = $this->workflowInstanceService->getActivityInstanceForm($data);
            return $result;
        }
    }

    //Register a new user
    protected function registerUser(&$data)
    {
        $this->logger->info("Register User");
        try {
            $tempUser = $data;
            $this->userService->createUser(array(), $tempUser, true);
            $data['user'] = $tempUser;
            return $data;
        } catch (Exception $e) {
            $this->logger->info("Register User Exception" . print_r($e->getMessage(), true));
            throw $e;
        }
    }

    public function batchProcess(&$data, $request)
    {
        try {
            if (isset($data['fileList'])) {
                $fileList = $data['fileList'];
                foreach ($fileList as $key => $value) {
                    $data['fileId'] = $value;
                    try {
                        $this->runCommand($data, $request);
                    } catch (ValidationException $e) {
                        $this->logger->error("FILE - $value - :Exception while Performing Service Task-" . $e->getMessage(), $e);
                        continue;
                    } catch (EntityNotFoundException $e) {
                        $this->logger->info("FILE - $value -:Entity Not found -" . $e->getMessage());
                        continue;
                    } catch (WorkflowException $e) {
                        $this->logger->info("FILE - $value -Error while claiming - " . $e->getReason() . ": " . $e->getMessage());
                        continue;
                    } catch (Exception $e) {
                        $this->logger->error("FILE - $value - :Error -" . $e->getMessage(), $e);
                        continue;
                    }
                }
            } else {
                $validationException = new ValidationException();
                $validationException->setErrors(["fileList is missing"]);
                throw $validationException;
            }
        } catch (Exception $e) {
            $this->logger->info("Batch Process Exception" . print_r($e->getMessage(), true));
            throw $e;
        }
    }
}
