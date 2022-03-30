<?php
namespace Oxzion\AppDelegate;

use Exception;
use Oxzion\AppDelegate\DocumentAppDelegate;
use Oxzion\AppDelegate\CommunicationDelegate;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\Db\Persistence\Persistence;
use Oxzion\Document\DocumentBuilder;
use Oxzion\Messaging\MessageProducer;
use Oxzion\Service\AbstractService;
use Oxzion\Service\FileService;
use Team\Service\TeamService;
use Oxzion\Service\FieldService;
use Oxzion\Service\WorkflowInstanceService;
use Oxzion\Service\ActivityInstanceService;
use Oxzion\Service\TemplateService;
use Oxzion\Utils\FileUtils;
use Oxzion\Service\UserService;
use Oxzion\Service\CommentService;
use Oxzion\Service\EsignService;
use Oxzion\Service\AccountService;
use Oxzion\EntityNotFoundException;
use Oxzion\Service\BusinessParticipantService;
use \Analytics\Service\QueryService;
use Oxzion\Service\ElasticService;
use oxzion\Insurance\InsuranceService;
use Oxzion\Service\AppService;
use App\Service\AppArtifactService;

class AppDelegateService extends AbstractService
{
    private $fileExt = ".php";
    private $persistenceServices = array();
    private $documentBuilder;
    private $messageProducer;
    private $templateService;
    private $workflowInstanceService;
    private $activityInstanceService;

    public function __construct(
        $config,
        $dbAdapter,
        DocumentBuilder $documentBuilder = null,
        TemplateService $templateService = null,
        MessageProducer $messageProducer,
        FileService $fileService,
        WorkflowInstanceService $workflowInstanceService,
        ActivityInstanceService $activityInstanceService,
        UserService $userService,
        CommentService $commentService,
        EsignService $esignService,
        FieldService $fieldService,
        AccountService $accountService,
        BusinessParticipantService $businessParticipantService,
        QueryService $queryService,
        ElasticService $elasticService,
        InsuranceService $insuranceService,
        TeamService $teamService,
        AppService $appService,
        AppArtifactService $appArtifactService
    )
    {
        $this->templateService = $templateService;
        $this->fileService = $fileService;
        $this->fieldService = $fieldService;
        $this->workflowInstanceService = $workflowInstanceService;
        $this->activityInstanceService = $activityInstanceService;
        $this->messageProducer = $messageProducer;
        $this->userService = $userService;
        $this->commentService = $commentService;
        $this->appService = $appService;
        $this->appArtifactService = $appArtifactService;
        $this->teamService = $teamService;
        $this->esignService = $esignService;
        parent::__construct($config, $dbAdapter);
        $this->documentBuilder = $documentBuilder;
        $this->delegateDir = $this->config['DELEGATE_FOLDER'];
        if (!is_dir($this->delegateDir)) {
            mkdir($this->delegateDir, 0777, true);
        }
        $this->accountService = $accountService;
        $this->businessParticipantService = $businessParticipantService;
        $this->queryService = $queryService;
        $this->elasticService = $elasticService;
        $this->insuranceService = $insuranceService;
    }

    public function setPersistence($appId, $persistence)
    {
        $this->persistenceServices[$appId] = $persistence;
    }

    public function setMessageProducer(MessageProducer $messageProducer)
    {
        $this->messageProducer = $messageProducer;
    }

    public function setFileService($fileService)
    {
        $this->fileService = $fileService;
    }

    public function setInsuranceService($insuranceService)
    {
        $this->insuranceService = $insuranceService;
    }

    public function setAppDelegateService()
    {
        $appDelegateService = new AppDelegateService($this->config, $this->dbAdapter, $this->documentBuilder, $this->templateService, $this->messageProducer, $this->fileService, $this->workflowInstanceService, $this->activityInstanceService, $this->userService, $this->commentService, $this->esignService, $this->fieldService, $this->accountService,$this->businessParticipantService, $this->queryService, $this->elasticService, $this->insuranceService,$this->teamService, $this->appService, $this->appArtifactService);
        return $appDelegateService;
    }

    public function execute($appId, $delegate, $dataArray = array())
    {
        $this->logger->info(AppDelegateService::class . "EXECUTE DELEGATE ---");
        try {
            $result = $this->delegateFile($appId, $delegate);
            if ($result) {
                $obj = new $delegate;
                if (is_a($obj, DocumentAppDelegate::class)) {
                    $obj->setDocumentBuilder($this->documentBuilder);
                    $destination = $this->config['APP_DOCUMENT_FOLDER'];
                    if (!file_exists($destination)) {
                        FileUtils::createDirectory($destination);
                    }
                    $this->logger->info("Document template location - $destination");
                    $obj->setDocumentPath($destination);
                    $obj->setBaseUrl($this->config['internalBaseUrl']);
                } elseif (is_a($obj, CommunicationDelegate::class)) {
                    $this->logger->info(AppDelegateService::class . "MAIL DELEGATE ---");
                    $destination = $this->config['APP_DOCUMENT_FOLDER'];
                    $obj->setDocumentPath($destination);
                    $obj->setBaseUrl($this->config['applicationUrl']);
                } elseif (is_a($obj, TemplateAppDelegate::class)) {
                    $destination = $this->config['TEMPLATE_FOLDER'];
                    if (!file_exists($destination)) {
                        FileUtils::createDirectory($destination);
                    }
                    $this->logger->info("Template location - $destination");
                    $obj->setTemplatePath($destination);
                }
                if (method_exists($obj, "setFileService")) {
                    $obj->setFileService($this->fileService);
                }
                if (method_exists($obj, "setFieldService")) {
                    $obj->setFieldService($this->fieldService);
                }
               
                if (method_exists($obj, "setTemplateService")) {
                    $obj->setTemplateService($this->templateService);
                }
                if (method_exists($obj, "setMessageProducer")) {
                    $obj->setMessageProducer($this->messageProducer);
                }
                if (method_exists($obj, "setWorkflowInstanceService")) {
                    $obj->setWorkflowInstanceService($this->workflowInstanceService);
                }
                if (method_exists($obj, "setActivityInstanceService")) {
                    $obj->setActivityInstanceService($this->activityInstanceService);
                }
                if (method_exists($obj, "setAppArtifactService")) {
                    $obj->setAppArtifactService($this->appArtifactService);
                }
                if (method_exists($obj, "setAppId")) {
                    $obj->setAppId($appId);
                }
                if (method_exists($obj, "setUserContext")) {
                    $obj->setUserContext(
                        AuthContext::get(AuthConstants::USER_UUID),
                        AuthContext::get(AuthConstants::NAME),
                        AuthContext::get(AuthConstants::ORG_UUID),
                        AuthContext::get(AuthConstants::PRIVILEGES)
                    );
                }
                if (method_exists($obj, "setUserService")) {
                    $obj->setUserService($this->userService);
                }
                if (method_exists($obj, "setCommentService")) {
                    $obj->setCommentService($this->commentService);
                }
                if (method_exists($obj, "setAppService")) {
                    $obj->setAppService($this->appService);
                }
                if (method_exists($obj, "setTeamService")) {
                    $obj->setTeamService($this->teamService);
                }
                if (method_exists($obj, "setEsignService")) {
                    $obj->setEsignService($this->esignService);
                }
                if (method_exists($obj, "setAccountService")) {
                    $obj->setAccountService($this->accountService);
                }
                if (method_exists($obj, "setBusinessParticipantService")) {
                    $obj->setBusinessParticipantService($this->businessParticipantService);
                }
                if (method_exists($obj, "setQueryService")) {
                    $obj->setQueryService($this->queryService);
                }
                if (method_exists($obj, "setElasticService")) {
                    $obj->setElasticService($this->elasticService);
                }
                if (method_exists($obj, "setInsuranceService")) {
                    $obj->setInsuranceService($this->insuranceService);
                }
                if (method_exists($obj, "setAppDelegateService")) {
                    $obj->setAppDelegateService($this->setAppDelegateService(), $appId);
                }
                $persistenceService = $this->getPersistence($appId);

                $output = $obj->execute($dataArray, $persistenceService);
                if (!$output) {
                    $output = array();
                }
                return $output;
            }
            return 1;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
        return 2;
    }

    private function delegateFile($appId, $className)
    {
        $file = $className . $this->fileExt;
        $path = $this->delegateDir . $appId . "/" . $file;
        $this->logger->info(AppDelegateService::class . "Delegate File Path ---\n" . $path);
        if ((file_exists($path))) {
            // include $path;
            $this->logger->info("Loading Delegate");
            require_once $path;
        } else {
            throw new EntityNotFoundException("Delegate not found");
        }
        return true;
    }

    private function getPersistence($appId)
    {
        $persistence = isset($this->persistenceServices[$appId]) ? $this->persistenceServices[$appId] : null;
        if (isset($persistence)) {
            return $persistence;
        } else {
            $name = $this->getAppName($appId);
            if ($name) {
                $persistence = new Persistence($this->config, $name, $appId);
                return $persistence;
            }
        }
        return null;
    }

    private function getAppName($appId)
    {
        $queryString = "Select ap.name from ox_app as ap";
        $where = "where ap.uuid = '" . $appId . "'";
        $resultSet = $this->executeQuerywithParams($queryString, $where);
        $result = $resultSet->toArray();
        if (count($result) > 0) {
            return $result[0]['name'];
        }
        return null;
    }
}
