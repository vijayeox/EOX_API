<?php

use Oxzion\AppDelegate\AbstractDocumentAppDelegate;
use Oxzion\Db\Persistence\Persistence;

class UserListing extends AbstractDocumentAppDelegate
{
    protected $template;

    public function __construct()
    {
        parent::__construct();
    }

    public function execute(array $data, Persistence $persistenceService)
    {
        $this->logger->info("User Listing Delegate ---".print_r($data,true));
        try {
            $selectQuery = "SELECT * FROM user WHERE role LIKE '%Account Executive%'";
            $result = $persistenceService->selectQuery($selectQuery);
            $resultArr = array();
            while ($result->next()) {
                array_push($resultArr, $result->current());
            }
            return $resultArr;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            $persistenceService->rollback();
            throw $e;
        }
    }
}
