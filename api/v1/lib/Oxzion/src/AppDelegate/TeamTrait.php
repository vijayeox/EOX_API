<?php
namespace Oxzion\AppDelegate;

use Team\Service\TeamService;
use Logger;

trait TeamTrait
{
    protected $logger;
    private $teamService;
    
    public function __construct()
    {
        $this->logger = Logger::getLogger(__CLASS__);
    }
    
    public function setTeamService(TeamService $teamService)
    {
        $this->logger->info("SET Team Service");
        $this->teamService = $teamService;
    }

    protected function getUserList($params, $filterParams = null)
    {
        return $this->teamService->getUserList($params, $filterParams = null);
    }
}
