<?php

use Oxzion\Db\Persistence\Persistence;
use Oxzion\AppDelegate\MailDelegate;

class SendComplianceMail extends MailDelegate
{
    private $abstractService;
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
        $selectQuery = "Select value FROM applicationConfig WHERE type = 'complianceGroupMail'";
        $complianceEmail = ($persistenceService->selectQuery($selectQuery))->current()["value"];
        $mailOptions = [];
        $mailOptions['to'] = $complianceEmail;
        $mailOptions['subject'] = "New producer registration details";
        $mailOptions['attachments'] = [];
        $this->logger->info("Compliance Group Email" . print_r($mailOptions, true));
        $this->sendMail($data, "complianceGroup", $mailOptions);
        return $data;
    }
}