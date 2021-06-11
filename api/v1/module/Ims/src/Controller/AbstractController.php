<?php
namespace Ims\Controller;

use Oxzion\Controller\AbstractApiController;
use Oxzion\Insurance\Ims\Service as ImsService;

class AbstractController extends AbstractApiController
{
    protected $imsService;
    public function __construct(ImsService $imsService, string $functionClass)
    {
        parent::__construct();
        $this->imsService = $imsService;
        $this->imsService->setSoapClient($functionClass);
    }

    public function getFunctionStructureAction()
    {
        try {
            $params = $this->params()->fromRoute();
            $response = $this->imsService->getFunctionStructure($params['operation']);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseWithData($response, 201);
    }

    public function getList()
    {
        try {
            $params = $this->params()->fromQuery();
            $response = $this->imsService->search($params);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseWithData($response, 201);
    }

    public function create($data)
    {
        try {
            $response = $this->imsService->create($data);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseWithData($response, 201);
    }

    public function createAPI($functionName, $data)
    {
        try {
            $response = $this->imsService->createAPI($functionName, $data);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseWithData($response, 201);
    }

}