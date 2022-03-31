<?php
namespace Oxzion\AppDelegate;

use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;

abstract class SmsDelegate extends CommunicationDelegate
{
    private $config;

    public function __construct()
    {
        parent::__construct();
        $this->setSmsConfig([
            'from' => '+16606386121',
            'accountSid' => 'AC95e7fff441af372f5c0a6e8185ba3859',
            'authToken' => '690d4de8fb14bd53847efa30c862db99'
        ]);
    }

    protected function setSmsConfig($config)
    {
        $this->config = $config;
    }

    protected function sendSms(array $data, string $template, array $smsOptions)
    {
        $this->logger->info(get_class()." data - ".print_r($data, true));
        if (empty($smsOptions['config'])) {
            $smsOptions['config'] = $this->config;
        }
        if (empty($smsOptions['body'])) {
            $orgUuid = isset($data['orgUuid']) ? $data['orgUuid'] : (isset($data['orgId']) ? $data['orgId'] : AuthContext::get(AuthConstants::ORG_UUID));
            $data['orgUuid'] = $orgUuid;
            $smsOptions['body'] = $this->templateService->getContent($template, $data);
        }
        $response = $this->sendMessage($smsOptions, 'twillio_sms');
        return $response;
    }
}
