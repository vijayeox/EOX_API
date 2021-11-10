<?php

use Oxzion\AppDelegate\FileTrait;
use Oxzion\Db\Persistence\Persistence;
use Oxzion\AppDelegate\AppDelegateTrait;
use Oxzion\AppDelegate\MailDelegate;

class ProcessGeneratedFiles extends MailDelegate
{
    use FileTrait;
    use AppDelegateTrait;

    public function __construct()
    {
        parent::__construct();
    }

    public function setDocumentPath($destination)
    {
        $this->destination = $destination;
    }

    public function execute(array $data, Persistence $persistenceService)
    {
        try {
            if (isset($data['fileId'])) {
                if ($data['status'] == 1) {
                    if (isset($data['_FILES'])) {
                        $fileData = $this->saveAndUpdateFile($data, $persistenceService);
                        $this->saveFile($fileData["fileData"], $data['fileId']);
                        $data['status'] = empty($fileData['status']) ? $data['status'] : $fileData['status'];
                    } else {
                        $data['status'] = 'No file/document provided';
                        // ErrorLog No file attached for status 1
                    }
                    if (isset($data['_errorlist']) && count($data['_errorlist'] > 0)) {
                        // $fileData['_errorList'] = $data['_errorList'];
                        // Do error log stuff
                    }
                } else {
                    // Do error log stuff

                }
            } else {
                $data['status'] = 'No fileId provided';
            }
        } catch (\Throwable $e) {
            $errorMessage = "Error Processing Request. In Catch";
            if (!empty($e->getMessage())) {
                $errorMessage = $e->getMessage();
            }
            throw new Exception($errorMessage, 1);
        }
        unset($data['orgId']);
        unset($data['errorlist']);
        return $data;
    }

    private function saveAndUpdateFile($data, $persistenceService)
    {
        $status = "";

        $data['fieldLabel'] = "documents";
        $attachment = $this->addAttachment($data, $data['_FILES']);
        if (!isset($attachment['created_id'])) {
            $status = "Failed to add attachment to file.";
        } else {
            $status = "Attachment Added to file.";
        }

        $fileData = $this->getFile($data['fileId'],  true, $data['orgId'])['data'];
        $this->logger->info("Check Count and Time - ". $fileData['documentsToBeGenerated'] . " -- " . date('Y-m-d H:i:s'));
        // if ($fileData == 0) {
        //     throw new Exception("Cannot update unknown file. Please check the fileId", 1);
        // }
        if (isset($fileData['documentsToBeGenerated'])) {
            if ($fileData['documentsToBeGenerated'] == 1) {
                $this->logger->info("Documents Generation Completed");
                $fileData['documentsToBeGenerated'] = 0;

                if ($fileData["workflowInitiatedBy"] == "accountExecutive") {
                    $fileData['status'] = 'Review';
                    $policyMail = $this->executeDelegate('DispatchMail', $fileData);
                    $fileData['mailStatus'] = $policyMail;
                } else {
                    $fileData['status'] = 'Generated';
                }

                $status = $status . "File status is " . $fileData['status'] . ($policyMail ? " and Mail sent" : " and Mail not sent");

            } else {
                $fileData['documentsToBeGenerated'] = $fileData['documentsToBeGenerated'] - 1;
                $status = $status . " File status is Processing (" . $fileData['documentsToBeGenerated'] . ")";
            }
        } else {
            throw new Exception("No documents to be generated", 1);
        }
        return ["fileData" => $fileData, "status" => $status];
    }
}
