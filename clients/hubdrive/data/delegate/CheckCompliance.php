<?php

use Oxzion\AppDelegate\AbstractAppDelegate;
use Oxzion\Db\Persistence\Persistence;

class CheckCompliance extends AbstractAppDelegate
{
    public function __construct()
    {
        parent::__construct();
    }

    public function execute(array $data, Persistence $persistenceService)
    {
        $this->logger->info("Executing Check compliance with data- " . json_encode($data, JSON_UNESCAPED_SLASHES));
        if(
            (isset($data['autoLiability']) && ($data['autoLiability'] == true || $data['autoLiability'] == "true")) 
            && (isset($data['cargoInsurance']) && 
            ($data['cargoInsurance'] == true || $data['cargoInsurance'] == "true"))) {
            $data['status'] = 'Compliant';
        } else {
            $data['status'] = 'Non-Compliant';
        }
        return $data;
    }

}