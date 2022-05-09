<?php

namespace Integrations\Controller;

use Exception;
use Oxzion\Controller\AbstractApiController;
use Oxzion\Controller\AbstractApiControllerHelper;
use Zend\Db\Adapter\AdapterInterface;
use Oxzion\Integrations\DeltaService;

class TriumphController extends AbstractApiControllerHelper
{
    /**
     * @var deltaService Instance of Delta Service
     */
    private $deltaService;
    /**
     * @ignore __construct
     */
    public function __construct(DeltaService $deltaService)
    {
        $this->deltaService = $deltaService;
    }


    public function testEndpointAction()
    {
        $data = $this->params()->fromPost();
        try {
            $this->deltaService->testEndpoint($data);
            return $this->getSuccessResponseWithData($data, 201);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }
}
