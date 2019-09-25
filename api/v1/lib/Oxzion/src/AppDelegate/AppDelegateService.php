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
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;
use Oxzion\Utils\FileUtils;


class AppDelegateService extends AbstractService
{
    private $fileExt = ".php";
    private $persistenceServices = array();
    private $documentBuilder;
    private $messageProducer;
    private $templateService;

    public function __construct($config, $dbAdapter, DocumentBuilder $documentBuilder = null, TemplateService $templateService = null)
    {
        $logger = new Logger();
        $writer = new Stream(__DIR__ . '/../../../../logs/Delegate.log');
        $logger->addWriter($writer);
        $this->templateService = $templateService;
        $this->messageProducer = MessageProducer::getInstance();
        parent::__construct($config, $dbAdapter, $logger);
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
        try {
            $result = $this->delegateFile($appId, $delegate);
            if ($result) { 
                $obj = new $delegate;
                $obj->setLogger($this->logger);
                if (is_a($obj, DocumentAppDelegate::class)) {
                    $obj->setDocumentBuilder($this->documentBuilder);
                    $destination = $this->config['APP_DOCUMENT_FOLDER'];
                    if (!file_exists($destination)) {
                        FileUtils::createDirectory($destination);
                    }
                    $obj->setTemplatePath($destination);
                } else if (is_a($obj, MailDelegate::class)) {
                    $obj->setTemplateService($this->templateService);
                    $obj->setMessageProducer($this->messageProducer);
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
            $this->logger->err($e->getMessage());
            throw $e;
        }
        return 2;
    }

    private function delegateFile($appId, $className)
    {
        $file = $className . $this->fileExt;
        $path = $this->delegateDir . $appId . "/" . $file;
        if ((file_exists($path))) {
            // include $path;
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
