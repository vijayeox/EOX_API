<?php

use Oxzion\AppDelegate\AbstractAppDelegate;
use Oxzion\Db\Persistence\Persistence;
use Oxzion\AppDelegate\FileTrait;
use Oxzion\DelegateException;

class ICEmailValidation extends AbstractAppDelegate
{
    use FileTrait;

    public function __construct()
    {
        parent::__construct();
    }

    public function execute(array $data, Persistence $persistenceService)
    {
        $this->logger->info("Executing ICEmail validation with data- " . print_r($data, true));
        $filterParams = array();
        $filterParams['filter'][0]['filter']['filters'][] = array('field'=>'iCEmail','operator'=>'eq','value'=> $data['iCEmail']);

        $data['filterParams'] = $filterParams;
        $data['entityName'] = 'Compliance';

        $pageSize = 1000;
        $filterParams['filter'][0]['take'] = $pageSize;
        $skip =  0;
        $filterParams['filter'][0]['skip'] = $skip;

        $fileList = $this->getFileList($data,$filterParams);
        $total = $fileList['total'];
        $this->logger->info("Executing ICEmail validation with data file- " . print_r($total, true));
        if($total > 1)
        {
            throw new DelegateException("Username/Email Used","record.exists");
        }
        return $data;
    } 


}