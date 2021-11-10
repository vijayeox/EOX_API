<?php
namespace Oxzion\AppDelegate;

use Oxzion\Service\FieldService;
use Logger;

trait FieldTrait
{
    protected $logger;
    private $fieldService;
    
    public function __construct()
    {
        $this->logger = Logger::getLogger(__CLASS__);
    }

    public function setFieldService(FieldService $fieldService)
    {
        $this->fieldService = $fieldService;
    }

    protected function getFields($appId,$filterArray){
        $this->logger->info("GET FIELD LIST");
        return $this->fieldService->getFields($appId,$filterArray);
    }

    protected function getFieldByName($entityId, $fieldName){
        return $this->fieldService->getFieldByName($entityId, $fieldName);
    }
}
