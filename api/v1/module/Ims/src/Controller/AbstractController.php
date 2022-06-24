<?php
namespace Ims\Controller;

use Oxzion\Insurance\InsuranceService;
use Oxzion\Controller\AbstractApiController;

class AbstractController extends AbstractApiController
{
    protected $imsService;
    public function __construct(InsuranceService $insuranceService, String $handle)
    {
        parent::__construct();
        $this->imsService = $insuranceService->getService('IMS', ['defaultConfig' => true, 'handle' => $handle]);
    }

    public function getFunctionStructureAction()
    {
        try {
            $route = $this->params()->fromRoute();
            $response = $this->imsService->getFunctionStructure($route['operation']);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseWithData(($response) ? $response : [], 201);
    }

    public function getList()
    {
        try {
            $route = $this->params()->fromRoute();
            $params = $this->params()->fromQuery();
            if (isset($route['operation'])) {
                $response = $this->imsService->perform($route['operation'], $params);
            } else {
                $response = $this->imsService->search($params);
            }
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseWithData(($response) ? $response : [], 201);
    }

    public function create($data)
    {
        try {
            $route = $this->params()->fromRoute();
            if (isset($route['operation'])) {
                $response = $this->imsService->perform($route['operation'], $data);
            } else {
                $response = $this->imsService->create($data);
            }
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponseWithData(($response) ? $response : [], 201);
    }

}