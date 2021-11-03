<?php
namespace Oxzion\AppDelegate;

use Oxzion\Service\FileService;
use Logger;

trait FileTrait
{
    protected $logger;
    private $fileService;
    private $appId;
    
    public function __construct()
    {
        $this->logger = Logger::getLogger(__CLASS__);
    }
    public function setFileService(FileService $fileService)
    {
        $this->logger->info("SET FILE SERVICE");
        $this->fileService = $fileService;
    }
    public function setAppId($appId)
    {
        $this->logger->info("SET APP ID");
        $this->appId = $appId;
    }

    protected function getFile($id, $latest = false, $orgId = null)
    {
        $this->logger->info("GET FILE");
        return $this->fileService->getFile($id, $latest, $orgId);
    }

    protected function getFileList($params, $filterparams = null)
    {
        $this->logger->info("GET FILE LIST");
        return $this->fileService->getFileList($this->appId, $params, $filterparams);
    }

    protected function saveFile(&$params, $fileId = null)
    {
        $this->logger->info("SAVE FILE");
        return $this->fileService->updateFile($params, $fileId);
    }

    protected function deleteFile($id, $version)
    {
        $this->logger->info("delete FILE");
        return $this->fileService->deleteFile($id, $version);
    }

    protected function getWorkflowInstanceByFileId($fileId)
    {
        $this->logger->info("GET FILE BY WORKFLOW INSTANCE ID");
        return $this->fileService->getWorkflowInstanceByFileId($fileId);
    }

    protected function startBatchProcessing()
    {
        $this->fileService->startBatchProcessing();
    }

    protected function completeBatchProcessing()
    {
        $this->fileService->completeBatchProcessing();
    }

    protected function addAttachment($params, $file)
    {
        return $this->fileService->addAttachment($params, $file);
    }

    protected function updateFieldValueOnFiles($data, $fieldName, $oldFieldValue, $newFieldValue, $filterparams = null)
    {
        $this->fileService->updateFieldValueOnFiles($this->appId, $data, $fieldName, $oldFieldValue, $newFieldValue, $filterparams);
    }
    protected function getFileVersionChangeLog($fileId, $version)
    {
        $this->logger->info("File Version Change Log");
        return $this->fileService->getFileVersionChangeLog($fileId, $version);
    }

    protected function getWorkflowInstanceStartDataFromFileId($fileId)
    {
        return $this->fileService->getWorkflowInstanceStartDataFromFileId($fileId);
    }
}
