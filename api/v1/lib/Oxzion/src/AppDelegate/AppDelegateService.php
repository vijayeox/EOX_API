<?php
namespace Oxzion\AppDelegate;

use Exception;
use Oxzion\AppDelegate\DocumentAppDelegate;
use Oxzion\AppDelegate\MailDelegate;
use Oxzion\Db\Persistence\Persistence;
use Oxzion\Document\DocumentBuilder;
use Oxzion\Messaging\MessageProducer;
use Oxzion\Service\AbstractService;
use Oxzion\Service\TemplateService;
use Oxzion\Service\FileService;
use Oxzion\Utils\FileUtils;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;


class AppDelegateService extends AbstractService
{
    private $fileExt = ".php";
    private $persistenceServices = array();
    private $documentBuilder;
    private $messageProducer;
    private $templateService;
    private $organizationService;

    public function __construct($config, $dbAdapter, DocumentBuilder $documentBuilder = null, TemplateService $templateService = null, MessageProducer $messageProducer,FileService $fileService)
    {
        $this->templateService = $templateService;
        $this->fileService = $fileService;
        $this->messageProducer = $messageProducer;
        parent::__construct($config, $dbAdapter);
        $this->documentBuilder = $documentBuilder;
        $this->delegateDir = $this->config['DELEGATE_FOLDER'];
        if (!is_dir($this->delegateDir)) {
            mkdir($this->delegateDir, 0777, true);
        }
    }

    public function setPersistence($appId, $persistence)
    {
        $this->persistenceServices[$appId] = $persistence;
    }

    public function setMessageProducer(MessageProducer $messageProducer)
    {
        $this->messageProducer = $messageProducer;
    }

    public function execute($appId, $delegate, $dataArray = array())
    {
        $this->logger->info(AppDelegateService::class . "EXECUTE DELEGATE ---");
        try {
            $result = $this->delegateFile($appId, $delegate);
            if ($result) {
                $obj = new $delegate;
                // $obj->setLogger($this->logger);
                if (is_a($obj, DocumentAppDelegate::class)) {
                    $obj->setDocumentBuilder($this->documentBuilder);
                    $destination = $this->config['APP_DOCUMENT_FOLDER'];
                    if (!file_exists($destination)) {
                        FileUtils::createDirectory($destination);
                    }
                    $this->logger->info("Document template location - $destination");
                    $obj->setTemplatePath($destination);
                } else if (is_a($obj, MailDelegate::class)) {
                    $this->logger->info(AppDelegateService::class . "MAIL DELEGATE ---");
                    $destination = $this->config['APP_DOCUMENT_FOLDER'];
                    $obj->setTemplateService($this->templateService);
                    $obj->setMessageProducer($this->messageProducer);
                    $obj->setDocumentPath($destination);
                    $obj->setBaseUrl($this->config['applicationUrl']);
                } 
                if(method_exists($obj, "setFileService")){
                    $obj->setFileService($this->fileService);
                }
                if(method_exists($obj, "setAppId")){
                    $obj->setAppId($appId);                
                }
                if(method_exists($obj, "setUserContext")){
                    $obj->setUserContext(AuthContext::get(AuthConstants::USER_UUID),
                                         AuthContext::get(AuthConstants::NAME),
                                         AuthContext::get(AuthConstants::ORG_UUID));
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
        $this->logger->info(AppDelegateService::class."Delegate File Path ---\n".$path);
        if ((file_exists($path))) {
            // include $path;
            $this->logger->info("Loading Delegate");
            require_once $path;

        } else {
            return false;
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