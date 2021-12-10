<?php
namespace Oxzion\AppDelegate;

use Oxzion\Service\AppService;
use Logger;

trait AppTrait
{
    protected $logger;
    private $appService;
    
    public function __construct()
    {
        $this->logger = Logger::getLogger(__CLASS__);
    }
    
    public function setAppService(AppService $appService)
    {
        $this->logger->info("SET ACCOUNT SERVICE");
        $this->appService = $appService;
    }
    protected function CreateAppWithOnlyAppDetails(&$data){
        return $this->appService->createApp($data);
    }

    protected function updateApp($uuid, &$data)
    {
        return $this->appService->updateApp($uuid, $data);
    }

    protected function getApp($uuid, $viewPath = null){
        return $this->appService->getApp($uuid, $viewPath);   
    }

}