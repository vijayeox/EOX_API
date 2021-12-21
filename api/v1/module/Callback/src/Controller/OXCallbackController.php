<?php
namespace Callback\Controller;

use Oxzion\Controller\AbstractApiControllerHelper;
use Oxzion\Messaging\MessageProducer;
use Oxzion\Service\TemplateService;

class OXCallbackController extends AbstractApiControllerHelper
{
    private $messageProducer;
    private $templateService;
    private $config;
    private $log;

    /**
     * @ignore __construct
     */
    public function __construct(TemplateService $templateService, $config, MessageProducer $messageProducer)
    {
        $this->templateService = $templateService;
        $this->messageProducer = $messageProducer;
        $this->config = $config;
        $this->log = $this->getLogger();
    }

    /**
     * User created API
     * @api
     * @link /callback/ox/createuser
     * @method POST
     * @return array Returns a Status Code</br>
     * <code> status : "success|error",
     * </code>
     */
    public function userCreatedAction()
    {
        $params = $this->extractPostData();
        $params['baseurl'] = $this->config['applicationUrl'];
        $params['passwordResetUrl'] = $this->config['applicationUrl'] . "/?resetpassword=" . $params['resetCode'];
        $bcc = " ";
        if (isset($params['bcc'])) {
            $bcc = $params['bcc'];
        }
        if (isset($params['customTemplate'])) {
            $body = $this->templateService->getContent('newUser', $params);
        } else {
            $body = $this->templateService->getContent('newUser', $params);
        }
        $this->messageProducer->sendQueue(json_encode(array(
            'to' => $params['email'],
            'from' => 'support@eoxvantage.com',
            'bcc' => $bcc,
            'subject' => isset($params['subject']) ? $params['subject'] : 'Your Login Credentials.',
            'body' => $body,
        )), 'mail');
        return $this->getSuccessResponse();
    }
}
