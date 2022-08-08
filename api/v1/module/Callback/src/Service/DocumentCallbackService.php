<?php
namespace Callback\Service;

use Exception;
use Oxzion\Service\AbstractService;
use Zend\Log\Logger;
use Oxzion\Service\FileService;

class DocumentCallbackService extends AbstractService
{

    protected $fileService;

    public function __construct($config, FileService $fileService)
    {
        $this->fileService = $fileService;
        parent::__construct($config, null);
    }

    public function saveExcel() {
        try {
            if (isset($data['fileId'])) {
                if ($data['status'] == 1) {
                    if (isset($data['_FILES'])) {
                        $fileData = $this->saveAndUpdateFile($data);
                        $this->fileSerice->saveFile($fileData["fileData"], $data['fileId']);
                    } else {
                        $data['status'] = 'No file/document provided';
                        $this->logger->error("No file/document provided");
                    }
                    if (isset($data['_errorlist']) && count($data['_errorlist'] > 0)) {
                        $this->logger->error("No file/document provided ".print_r($data['_errorlist'],true));
                    }
                } else {
                    $this->logger->error("Status error");
                }
            } else {
                $this->logger->error("No fileId provided");
                $data['status'] = 'No fileId provided';
            }
        } catch (Exception $e) {
            if (!empty($e->getMessage())) {
                $errorMessage = $e->getMessage();
            }
            throw new Exception($errorMessage, 1);
        }
        unset($data['orgId']);
        unset($data['errorlist']);
        return $data;
    }

    private function saveAndUpdateFile($data)
    {
        $status = "";

        $data['fieldLabel'] = "documents";
        $attachment = $this->fileSerice->addAttachment($data, $data['_FILES']);
        if (!isset($attachment['created_id'])) {
            $status = "Failed to add attachment to file.";
        } else {
            $status = "Attachment Added to file.";
        }

        $fileData = $this->fileSerice->getFile($data['fileId'],  true, $data['orgId'])['data'];
        $this->logger->info("Check Count and Time - ". $fileData['documentsToBeGenerated'] . " -- " . date('Y-m-d H:i:s'));

        if (isset($fileData['documentsToBeGenerated'])) {
            if ($fileData['documentsToBeGenerated'] == 1) {
                $this->logger->info("Documents Generation Completed");
                $fileData['documentsToBeGenerated'] = 0;
                $fileData['status'] = 'Generated';
            } else {
                $fileData['documentsToBeGenerated'] = $fileData['documentsToBeGenerated'] - 1;
            }
        } else {
            throw new Exception("No documents to be generated", 1);
        }
        return ["fileData" => $fileData, "status" => $status];
    }
}