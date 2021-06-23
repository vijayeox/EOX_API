<?php
namespace Oxzion\AppDelegate;

use Oxzion\ServiceException;
use Oxzion\OxServiceException;
use Oxzion\Insurance\InsuranceService;
use Logger;

trait InsuranceTrait
{
    protected $logger;
    private $service;
    private $insuranceService;

    public function __construct()
    {
        $this->logger = Logger::getLogger(__CLASS__);
    }
    public function setInsuranceService(InsuranceService $insuranceService)
    {
        $this->insuranceService = $insuranceService;
    }
    // $data can have ["service" => "IMS", "config" => "ProducerFunctions"]
    public function setServiceType($data)
    {
        $this->logger->info("Set Service -> " . print_r($data, true));
        $this->service = $this->insuranceService->getService($data['service'], $data['config']);
    }

    // eg. call $service->search(["searchString" => "demo", "startWith" => true]);
    public function __call($method, $params)
    {
        $this->logger->info("Call Service -> " . print_r([$method, $params], true));
        if (method_exists($this, $method)) {
            call_user_func_array($this->$method, $params);
        } elseif (method_exists($this->service, $method)) {
            return call_user_func_array($this->service->$method, $params);
        } else {
            throw new ServiceException("Method not avaliable for " . get_class($this->service), 'method.not.found', OxServiceException::ERR_CODE_NOT_FOUND);
        }
    }

}