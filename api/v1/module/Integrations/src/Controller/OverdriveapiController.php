<?php

/**
 * Overdrive Api
 */
namespace Integrations\Controller;

use Oxzion\Service\OverdriveService;
use Exception;
use Oxzion\Controller\AbstractApiControllerHelper;
use Oxzion\Controller\AbstractApiController;
use Oxzion\ValidationException;
use Zend\Db\Adapter\AdapterInterface;

/**
 * Overdriveapi Controller
 */
class OverdriveapiController extends AbstractApiController
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

    public function getContractorAction($data)
    {
        try{
            $result=$this->overdriveService->getContractor($data);
            return $this->getSuccessResponseWithData($result);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    public function addContractorAction($data)
    {
        try{
        $result=$this->overdriveService->addContractor($data);
        return $this->getSuccessResponseWithData($result);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    public function addDriverAction($data)
    {
        try{
        $result=$this->overdriveService->addDriver($data);
        return $this->getSuccessResponseWithData($result);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    public function tchoiceRegistrationAction()
    {
        $params = array_merge($this->extractPostData(), $this->params()->fromRoute());
        print_r($params);
    }
}
