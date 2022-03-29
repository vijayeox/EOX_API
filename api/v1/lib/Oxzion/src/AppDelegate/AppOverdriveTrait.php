<?php
namespace Oxzion\AppDelegate;

use Oxzion\Service\OverdriveService;
 
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

}
