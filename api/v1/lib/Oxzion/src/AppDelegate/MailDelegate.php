<?php
namespace Oxzion\AppDelegate;

use Oxzion\Messaging\MessageProducer;
use Oxzion\Service\TemplateService;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;

use Zend\Mail\Storage;
use Zend\Mime;

abstract class MailDelegate extends CommunicationDelegate
{
    protected $imapClient;
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
        $userMail = $this->sendMessage($mailOptions, 'mail');
        return $userMail;
    }

    /**
     * $config = [
     *      'host'     => 'imap.gmail.com',     [required]
     *      'user'     => 'email',              [required]
     *      'password' => 'password',           [required]
     *      'ssl'      => 'ssl',                [optional]
     *      'folder'   => 'folder'              [optional]
     *  ];
     */
    protected function setMailConfig(array $config)
    {
        try {
            $this->imapClient = new Storage\Imap($config);
            return true;
        } catch (\Exception $e) {
            $this->logger->info("Unable to set Email Client ---- " . $e->getMessage() . " ---- ".print_r($config, true));
            return false;
        }
    }

    protected function getMail(array $filters)
    {
        $data = [];
        $count = 0;
        $filters = $this->getValidFilters($filters);
        try {
            if ($filters['folder'] && $this->imapClient->getCurrentFolder() != $filters['folder']) {
                $this->imapClient->selectFolder($filters['folder']);
            }
            $count = $this->imapClient->countMessages($filters['flags']);
            if ($count) {
                foreach ($this->imapClient as $mail) {
                    if (!$filters['flags'] || !$mail->getFlags() || array_intersect($mail->getFlags(), $filters['flags'])) {
                        if ($filters['limit'] === 0) { break; }
                        elseif ($filters['limit']) { $filters['limit']--; }

                        $from = $mail->getHeaders()->get('From')->getAddressList()->current();
                        $mailBody = (new Mime\Message())::createFromMessage(
                            $mail->getContent(),
                            $mail->getHeader('Content-Type')->getParameter('boundary')
                        );
                        foreach ($mailBody->getParts() as $part) {
                            if ($part->getType() == $filters['bodyType']) break;
                        }
                        $data[] = [
                            'messageid' => $mail->messageid,
                            'fromName' => $from->getName(),
                            'fromEmail' => $from->getEmail(),
                            'subject' => $mail->subject,
                            'body' => $part->getRawContent()
                        ];
                        // $this->updateMailFlag($mail->messageid, 'flags', [Storage::FLAG_UNSEEN]);
                    }
                }
            }
            $this->logger->info("Email list found ----".print_r($data, true));
        } catch (\Exception $e) {
            $this->logger->info("Something went wrong in mail delegate - ".$e->getMessage());
            echo "<pre>";print_r($e->getMessage());exit;
        }
        return ['count' => $count, 'data' => $data];
    }

    protected function updateMailFlag(String $messageid, String $operation, $data)
    {
        try {
            switch ($operation) {
                case 'flags':
                    $this->imapClient->setFlags($messageid, $data);
                    break;
                default:
                    $this->logger->info("updateMailFlag ".$operation." not implemented.");
                    throw new \Exception("updateMailFlag ".$operation." not implemented.", 404);
                    break;
            }
        } catch (\Exception $e) {
            $this->logger->info("Something went wrong in mail delegate - ".$e->getMessage());
            return false;
        }
    }

    private function getValidFilters($filters)
    {
        // find flags from Storage class
        // find bodyType from Mime class

        $flags = new \ReflectionClass(new Storage());
        $bodyType = new \ReflectionClass(new Mime\Mime());

        $filters = [
            'limit' => is_int($filters['limit']) ? $filters['limit'] : null,
            'flags' => !empty($filters['flags']) ? $filters['flags'] : null,
            'folder' => !empty($filters['folder']) ? $filters['folder'] : null,
            'bodyType' => !empty($filters['bodyType']) ? $filters['bodyType'] : null
        ];
        if ($filters['flags']) {
            $filters['flags'] = array_intersect($filters['flags'], $flags->getConstants());
        }
        if (!$filters['bodyType'] || !in_array($filters['bodyType'], $bodyType->getConstants())) {
            $filters['bodyType'] = (new Mime\Mime)::TYPE_HTML;
        }
        return $filters;
    }

}