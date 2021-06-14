<?php
namespace Oxzion\Insurance;

use Oxzion\ServiceException;
use Oxzion\OxServiceException;
use Oxzion\Service\AbstractService;
use Oxzion\Messaging\MessageProducer;
use Oxzion\Insurance\Ims\Service as ImsService;

class Service extends AbstractService
{
    private $services;

    public function __construct($config, $dbAdapter, MessageProducer $messageProducer)
    {
        parent::__construct($config, $dbAdapter);
        $this->services = [
            "IMS" => new ImsService($config)
        ];
    }

    public function getService(String $service, $config)
    {
        if (isset($this->services[$service])) {
            return $this->setConfig($this->services[$service], $config);
        }
        throw new ServiceException("Service not avaliable for " . $service, 'service.not.found', OxServiceException::ERR_CODE_NOT_FOUND);
    }
    private function setConfig($service, $config)
    {
        if (method_exists($service, 'setConfig')) {
            $service->setConfig($config);
        }
        return $service;
    }

}