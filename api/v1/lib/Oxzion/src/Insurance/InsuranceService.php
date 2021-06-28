<?php
namespace Oxzion\Insurance;

use Oxzion\ServiceException;
use Oxzion\OxServiceException;
use Oxzion\Service\AbstractService;
use Oxzion\Messaging\MessageProducer;

class InsuranceService extends AbstractService
{
    private $services;

    public function __construct($config, $dbAdapter, MessageProducer $messageProducer)
    {
        parent::__construct($config, $dbAdapter);
        $this->services = [
            "IMS" => new Ims\ImsEngineImpl($config)
        ];
    }

    public function getService(String $client, Array $data)
    {
        if (isset($this->services[$client])) {
            return $this->setConfig($this->services[$client], $data);
        }
        throw new ServiceException("Service not avaliable for " . $client, 'service.not.found', OxServiceException::ERR_CODE_NOT_FOUND);
    }
    private function setConfig($service, Array $data)
    {
        $service->setConfig($data);
        return $service;
    }

}