<?php

use Oxzion\AppDelegate\AbstractDocumentAppDelegate;
use Oxzion\Db\Persistence\Persistence;

class UpdateProducerCode extends AbstractDocumentAppDelegate
{
    protected $template;

    public function __construct()
    {
        parent::__construct();
    }

    public function execute(array $data, Persistence $persistenceService)
    {
        $this->logger->info("Update Producer Code Delegate ---".print_r($data,true));
        try {
            if(!isset($data['producer_code'])) {
                throw new DelegateException("Producer Code has not been entered", "missing.producer_code");
            }
            if(!isset($data['uuid'])) {
                throw new DelegateException("UUID has not been entered", "missing.uuid");
            }
            $updateQuery = "UPDATE `user` SET `producer_code` = '".$data['producer_code']."' WHERE `uuid` = '".$data['uuid']."';";
            $result = $persistenceService->updateQuery($updateQuery);
            return [];
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            $persistenceService->rollback();
            throw $e;
        }
    }
}
