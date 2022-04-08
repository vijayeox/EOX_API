<?php

namespace Integrations\Controller;

use OverdriveIntegrations\Service\OverdriveService;
use Oxzion\Controller\AbstractApiController;
use Zend\Db\Adapter\AdapterInterface;

/**
 * OverDrive Integration Controller
 */
class OverdriveapiController extends AbstractApiController
{
    /**
     * @var overdriveService Instance of OverdriveService Service
     */
    private $overdriveService;
    /**
     * @ignore __construct
     */

    public function __construct(OverdriveService $overdriveService, AdapterInterface $dbAdapter)
    {
        $this->overdriveService = $overdriveService;
        $this->log = $this->getLogger();
    }

    public function getContractor($data)
    {
        return $this->overdriveService->getContractor($data);
    }

    public function addContractor($data)
    {
        return $this->overdriveService->addContractor($data);
    }

    public function addDriver($data)
    {
        return $this->overdriveService->addDriver($data);
    }
}
