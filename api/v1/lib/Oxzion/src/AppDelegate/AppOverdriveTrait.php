<?php
namespace Oxzion\AppDelegate;

use Oxzion\Service\OverdriveService;
use Zend\View\Model\JsonModel;
use Exception;
 
trait AppOverdriveTrait
{
    private $appOverdriveService;

    public function setAppOverdriveService(OverdriveService $appOverdriveService)
    {
        $this->appOverdriveService = $appOverdriveService;
    }

    public function getAppOverdriveService()
    {
        return $this->appOverdriveService;
    }

    protected function getContractor($data)
    {
        try{
            return $this->appOverdriveService->getContractor($data);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    protected function addContractor($data)
    {
        try{
            return $this->appOverdriveService->addContractor($data);
            } catch (Exception $e) {
                $this->log->error($e->getMessage(), $e);
                return $this->exceptionToResponse($e);
            }
    }

    protected function addDriver($data)
    {
        try{
            return $this->appOverdriveService->addDriver($data);
            } catch (Exception $e) {
                $this->log->error($e->getMessage(), $e);
                return $this->exceptionToResponse($e);
            }
    }
}
