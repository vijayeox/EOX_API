<?php

/**
 * Overdrive Api
 */
namespace Integrations\Controller;

use Oxzion\Service\OverdriveService;
use Exception;
use Oxzion\Controller\AbstractApiControllerHelper;
use Oxzion\ValidationException;
use Zend\Db\Adapter\AdapterInterface;

/**
 * Overdriveapi Controller
 */
class OverdriveapiController extends AbstractApiControllerHelper
{
    /**
     * @var OverdriveService Instance of Overdrive Service
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
        try{
            return $this->overdriveService->getContractor($data);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    public function addContractor($data)
    {
        try{
        return $this->overdriveService->addContractor($data);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    public function addDriver($data)
    {
        try{
        return $this->overdriveService->addDriver($data);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }
}
