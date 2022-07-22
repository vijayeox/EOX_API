<?php

namespace Oxzion\AppDelegate;

use Oxzion\Service\AppService;
use App\Service\AppArtifactService;
use Logger;

trait AppTrait
{
    protected $logger;
    private $appService;
    private $apArtifactService;

    public function __construct()
    {
        $this->logger = Logger::getLogger(__CLASS__);
    }

    public function setAppService(AppService $appService)
    {
        $this->logger->info("SET APP SERVICE");
        $this->appService = $appService;
    }

    public function setAppArtifactService(AppArtifactService $appArtifactService)
    {
        $this->logger->info("SET APP ARTIFACT SERVICE");
        $this->appArtifactService = $appArtifactService;
    }

    protected function CreateAppWithOnlyAppDetails(&$data)
    {
        return $this->appService->createApp($data);
    }

    protected function updateApp($uuid, &$data)
    {
        return $this->appService->updateApp($uuid, $data);
    }

    protected function getArtifacts($appUuid, $artifactType)
    {
        return $this->appArtifactService->getArtifacts($appUuid, $artifactType);
    }

    protected function getAppFormFields($formName,$appId){
        return $this->appService->getAppFormFields($formName,$appId);
    }

    protected function getAppFormProperties($formName,$appId){
        return $this->appService->getAppFormProperties($formName,$appId);
    }

}
