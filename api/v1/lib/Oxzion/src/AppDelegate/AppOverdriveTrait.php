<?php
namespace Oxzion\AppDelegate;

use Oxzion\Service\OverdriveService;
use Zend\View\Model\JsonModel;
use Exception;
 
trait AppOverdriveTrait
{
    private $appOverdriveService;

    public function setAppOverdriveService(OverdriveService $appOverdriveService)
    {
        $this->appOverdriveService = $appOverdriveService;
    }

    public function getAppOverdriveService()
    {
        return $this->appOverdriveService;
    }

    protected function getContractor($data)
    {
        return $this->appOverdriveService->getContractor($data);
    }

    protected function addContractor($data)
    {
        return $this->appOverdriveService->addContractor($data);
    }

    protected function addDriver($data)
    {
        return $this->appOverdriveService->addDriver($data);
    }
}
