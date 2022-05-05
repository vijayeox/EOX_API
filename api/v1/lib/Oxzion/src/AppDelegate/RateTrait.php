<?php
namespace Oxzion\AppDelegate;

use Rate\Service\RateService;
use Logger;

trait RateTrait
{
    protected $logger;
    private $rateService;

    public function __construct()
    {
        $this->logger = Logger::getLogger(__CLASS__);
    }
    public function setRateService(RateService $rateService)
    {
        $this->logger->info("SET RATE SERVICE");
        $this->rateService = $rateService;
    }

    protected function getRateList($params)
    {
        return $this->rateService->getRateList($params);
    }

}
