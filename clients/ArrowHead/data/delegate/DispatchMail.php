<?php

use Oxzion\Db\Persistence\Persistence;
use Oxzion\AppDelegate\FieldTrait;
use Oxzion\Utils\FileUtils;
use Oxzion\Utils\ZipUtils;

use Oxzion\AppDelegate\MailDelegate;

class DispatchMail extends MailDelegate
{
    use FieldTrait;

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
        $selectQuery = "Select value FROM applicationConfig WHERE type ='arrowHeadInboxMail'";
        $submissionEmail = ($persistenceService->selectQuery($selectQuery))->current()["value"];

        $emailAttachments = [];
        foreach ($this->checkJSON($data['documents']) as $doc) {
            if ($doc['originalName'] !== "excelMapperInput.json") {
                if (isset($doc["fullPath"])) {
                    array_push($emailAttachments, $doc['fullPath']);
                } else {
                    array_push($emailAttachments, $doc['path']);
                }
            }
        }
        $attachmentFieldsArray = include(__DIR__ . "/fieldMappingAttachments.php");
        $fileDocs =  $this->destination . $data["orgId"] . DIRECTORY_SEPARATOR . $data["fileId"] . DIRECTORY_SEPARATOR;
        $mailDocumentsDir = $fileDocs . "mailDocuments";

        $attachmentsAvailable = false;
        foreach ($attachmentFieldsArray as $attachmentField) {
            if (
                isset($data[$attachmentField])
            ) {
                if (count($this->checkJSON($data[$attachmentField])) > 0) {
                    $attachmentsAvailable = true;
                    $fieldConfig = $this->getFieldByName("Dealer Policy", $attachmentField);
                    $folderPath = $mailDocumentsDir . DIRECTORY_SEPARATOR . $fieldConfig["text"];
                    foreach ($this->checkJSON($data[$attachmentField]) as $fileIndex => $attachmentFile) {
                        if (FileUtils::fileExists($attachmentFile["path"])) {
                            FileUtils::copy(
                                $attachmentFile["path"],
                                $attachmentFile["originalName"],
                                $folderPath
                            );
                        }
                    }
                }
            }
        }

        $mailTemplateFlag = 0;
        if ($attachmentsAvailable) {
            FileUtils::fileExists($mailDocumentsDir . ".zip") ?
                FileUtils::deleteFile("mailDocuments.zip", $fileDocs) : null;
            ZipUtils::zipDir($mailDocumentsDir, $mailDocumentsDir . ".zip");
            $size = FileUtils::fileExists($mailDocumentsDir . ".zip") ? filesize($mailDocumentsDir . ".zip") : 0;
            if($size > 10000000) {
                $mailTemplateFlag = 1;
                FileUtils::rmDir($mailDocumentsDir);
            } else {
                array_push($emailAttachments,  $mailDocumentsDir . ".zip");
            }
        }


        $mailOptions = array();
        $mailOptions['to'] = $submissionEmail;
        $mailOptions['subject'] = "New business – " . $data['namedInsured'] . " - " . $this->formatDate($data['effectiveDate']) . " - " . $data['producername'];
        $mailOptions['attachments'] = $emailAttachments;
        $this->logger->info("Arrowhead Policy Mail " . print_r($mailOptions, true));
        $data['orgUuid'] = "34bf01ab-79ca-42df-8284-965d8dbf290e";
        // $data['orgUuid'] = isset($data['orgId']) ? $data['orgId'] : AuthContext::get(AuthConstants::ORG_UUID);
        $response = array();
        $responseMail1 = $this->sendMail($data, "finalSubmissionMail", $mailOptions);
        $response['Mail1'] = $responseMail1;
        $this->logger->info("Mail 1 has " . ($responseMail1 ? "been sent." : "not been sent."));

        if($mailTemplateFlag == 1) {
            $mailOptions['subject'] = "Attachment failed for new business – " . $data['namedInsured'] . " - " . $this->formatDate($data['effectiveDate']) . " - " . $data['producername'];
            $mailOptions['attachments'] = [];
            $data['mailTemplateFlag'] = $mailTemplateFlag;
            $responseMail2 = $this->sendMail($data, "finalSubmissionMail", $mailOptions);
            $response['Mail2'] = $responseMail2;
            $this->logger->info("Mail 2 has " . ($responseMail2 ? "been sent." : "not been sent."));
        }
        return $response;
    }


    private function formatDate($data)
    {
        $date = strpos($data, "T") ? explode("T", $data)[0] : $data;
        return date(
            "m-d-Y",
            strtotime($date)
        );
    }

    private function checkJSON($data)
    {
        if (!is_array($data)) {
            $data = json_decode($data, true);
        }
        return $data;
    }
}
