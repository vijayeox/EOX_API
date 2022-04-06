<?php

/**
 * Attachment Api
 */

namespace OverdriveIntegrations\Controller;

use Oxzion\Service\OverdriveService;
use Exception;
use Oxzion\Controller\AbstractApiControllerHelper;
use Oxzion\ValidationException;
use Zend\Db\Adapter\AdapterInterface;

/**
 * Attachment Controller
 */
class OverdriveapiController extends AbstractApiControllerHelper
{
    /**
     * @var AttachmentService Instance of Attchment Service
     */
    private $overdriveService;
    /**
     * @ignore __construct
     */

    public function __construct(OverdriveService $overdriveService, AdapterInterface $dbAdapter)
    {
        //parent::__construct($table, AttachmentController::class);
        $this->overdriveService = $overdriveService;
        //$this->setIdentifierName('attachmentId');
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
