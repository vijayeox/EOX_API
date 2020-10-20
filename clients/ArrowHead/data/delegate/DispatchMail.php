<?php

use Oxzion\Db\Persistence\Persistence;

use Oxzion\AppDelegate\MailDelegate;

class DispatchMail extends MailDelegate
{

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
        $mailOptions = array();
        $mailOptions['to'] = $submissionEmail;
        $mailOptions['subject'] = "New business – " . $data['namedInsured'] . " - " . $this->formatDate($data['effectiveDate']) . " - " . $data['producername'];
        $mailOptions['attachments'] = $emailAttachments;
        $this->logger->info("Arrowhead Policy Mail " . print_r($mailOptions, true));
        $data['orgUuid'] = "34bf01ab-79ca-42df-8284-965d8dbf290e";
        // $data['orgUuid'] = isset($data['orgId']) ? $data['orgId'] : AuthContext::get(AuthConstants::ORG_UUID);
        $response = $this->sendMail($data, "finalSubmissionMail", $mailOptions);
        $this->logger->info("Mail has " . $response ? "been sent." : "not been sent.");
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
