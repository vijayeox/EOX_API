<?php
use Oxzion\AppDelegate\MailDelegate;

abstract class DispatchNotification extends MailDelegate
{

    public function __construct()
    {
        parent::__construct();
    }

    public function setDocumentPath($destination)
    {
        $this->destination = $destination;
    }

    protected function dispatch(array $data)
    {
        $this->logger->info("DISPATCH DATA" . print_r($data, true));
        $mailOptions = array();
        $mailOptions['to'] = $data['email'];
        $mailOptions['subject'] = $data['subject'];
        $template = $data['template'];
        $response = $this->sendMail($data, $template, $mailOptions);
        return $response;
    }
}
?>