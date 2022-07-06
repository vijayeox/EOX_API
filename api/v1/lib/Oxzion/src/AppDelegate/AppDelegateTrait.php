<?php
namespace Oxzion\AppDelegate;

use Oxzion\AppDelegate\AppDelegateService;
use Logger;

trait AppDelegateTrait
{
    protected $logger;
    private $appDelegateService;
    private $appId;

    public function __construct()
    {
        $this->logger = Logger::getLogger(__CLASS__);
    }

    public function setAppDelegateService(AppDelegateService $appDelegateService, $appId)
    {
        $this->appId = $appId;
        $this->appDelegateService = $appDelegateService;
    }

    public function getAppDelegateService()
    {
        return $this->appDelegateService;
    }

    protected function executeDelegate($delegateName, $data)
    {
        return $this->appDelegateService->execute($this->appId, $delegateName, $data);
    }
}
