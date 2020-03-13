<?php

use Oxzion\Db\Persistence\Persistence;
use Oxzion\Utils\ArtifactUtils;
use Oxzion\AppDelegate\UserContextTrait;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\AppDelegate\FileTrait;
use Oxzion\AppDelegate\AbstractAppDelegate;

class PocketCardMenu extends AbstractAppDelegate
{
    use FileTrait;

    public function __construct(){
        parent::__construct();
    }

    public function execute(array $data, Persistence $persistenceService) 
    {
        $this->logger->info("Executing Pocket Card Generation with data- ".json_encode($data));
        $params['entityName'] = 'Pocket Card Job';
        $sortParams = array("field" => "date_created", "dir" => "desc");
        $filterParams = array("filters" => array());
        $finalFilterParams = array(array("start" => "0", "limit" => "10", "filter" => $filterParams, "sort" => array($sortParams)));
        $files = $this->getFileList($params, $finalFilterParams);
        $this->logger->info("the total number of files fetched is : ".print_r($files['total'], true));
        $this->logger->info("the file details of get file is : ".print_r($files['data'], true));
        foreach($files['data'] as $key => &$value){
            $this->logger->info("key is : ".$key);
            $this->logger->info("value is : ".print_r($value, true));
            unset($value['data']);
            if(isset($value['padiNumber'])){
                $value['generationType'] = $value['padiNumber'];
                $value['pocketCardProductType'] = isset($value['product']) ? $value['product'] : "";
            }
            else {
                $time = strtotime($value['pocketCardStartDate']);
                $value['pocketCardStartDate'] = date('Y-m-d', $time);
                $time = strtotime($value['pocketCardEndDate']);
                unset($value['pocketCardEndDate']);
                $value['pocketCardEndDate'] = date('Y-m-d', $time);
                $value['generationType'] = $value['pocketCardStartDate'].' to '.$value['pocketCardEndDate'];

                if(isset($value['pocketCardProductType'])){
                    $productName = json_decode($value['pocketCardProductType'], true);
                    unset($value['pocketCardProductType']);
                    $value['pocketCardProductType'] = '';
                    if(isset($productName['individualProfessionalLiability']) && ($productName['individualProfessionalLiability'] == 1)){
                        $value['pocketCardProductType'] .= 'Individual Professional Liability , ';
                    }
                    if(isset($productName['emergencyFirstResponse']) && ($productName['emergencyFirstResponse'] == 1)){
                        $value['pocketCardProductType'] .= 'Emergency First Response , ';
                    }
                    if(isset($productName['diveBoat']) && ($productName['diveBoat'] == 1)){
                        $value['pocketCardProductType'] .= 'Dive Boat , ';
                    }
                    if(isset($productName['diveStore']) && ($productName['diveStore'] == 1)){
                        $value['pocketCardProductType'] .= ' Dive Store , ';
                    }
                    $value['pocketCardProductType'] = rtrim($value['pocketCardProductType'], ", ");
                    $this->logger->info($value['pocketCardProductType']);
                }
            }
            
        }
        return $files;
    }
}


?>