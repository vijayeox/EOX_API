<?php
namespace Oxzion\AppDelegate;

use Oxzion\Messaging\MessageProducer;
use Oxzion\Service\TemplateService;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;

abstract class MailDelegate extends CommunicationDelegate
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function sendMail(array $data, string $template, array $mailOptions)
    {
        $this->logger->info("SEND MAIL ----".print_r($data, true));
        $accountId = isset($data['orgUuid']) ? $data['orgUuid'] : (isset($data['accountId']) ? $data['accountId'] : AuthContext::get(AuthConstants::ACCOUNT_UUID));
        $data['accountId'] = $accountId;

        $mailOptions['body'] = $this->templateService->getContent($template, $data);
        $mailOptions['from'] = "support@eoxvantage.com";
        $userMail = $this->sendMessage($mailOptions, 'mail');
        return $userMail;
    }
}
