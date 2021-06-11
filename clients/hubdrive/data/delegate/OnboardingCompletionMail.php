<?php

use Oxzion\AppDelegate\MailDelegate;
use Oxzion\Db\Persistence\Persistence;
use Oxzion\AppDelegate\AccountTrait;

class OnboardingCompletionMail extends MailDelegate
{

    use AccountTrait;
    public function setDocumentPath($destination)
    {
        $this->destination = $destination;
    }

    public function execute(array $data, Persistence $persistenceService)
    {
        $this->logger->info("Executing Onboarding Completion Mail with data- " . print_r($data,true));
        $mailOptions = array();    
        $mailOptions['to'] = 'support@eoxvantage.com';
        $mailOptions['subject'] = 'Onboarding Completion';
        $template = 'OnboardingCompletionMail';
        if (isset($data['attachments'])) {
            if(is_string($data['attachments'])){
            $data['attachments'] = json_decode($data['attachments'],true);
            }
            foreach ($data['attachments'] as $key => $value) {
                $mailOptions['attachments'][$key] = $value['fullPath'];
            }
        }
        $currentAccount = isset($data['accountId']) ? $data['accountId'] : null;
        $data['accountId'] = $this->getAccountByName($data['accountName']) ? $this->getAccountByName($data['accountName']) : (isset($currentAccount) ? $currentAccount : AuthContext::get(AuthConstants::ACCOUNT_UUID));
        $response = $this->sendMail($data, $template, $mailOptions);
            $this->logger->info("Mail Response" . $response);
        $data['accountId'] = $currentAccount;
        return $data;
    }
}
