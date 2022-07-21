
<?php

use Oxzion\Db\Persistence\Persistence;
use Oxzion\AppDelegate\AbstractAppDelegate;
use Oxzion\AppDelegate\AppTrait;
use Oxzion\DelegateException;
// use Exception;

class GetFormFields extends AbstractAppDelegate
{
    use AppTrait;

    public function __construct()
    {
        parent::__construct();
    }

    public function execute(array $data, Persistence $persistenceService)
    {
        $this->logger->info("Data in the delegate----".print_r($data,true));
        if(!isset($data['formAppId'])){
            throw new DelegateException("App does not exists", 'app_not_exist');
        }
        $appId = $data['formAppId'];
        try{
            $data['fieldsList'] = $this->getAppFormFields($data['template_file'],$appId);
            print_r($data['fieldsList']);
            $this->logger->info("Data out of delegate----".print_r($data,true));
        }catch(Exception $e){
            throw $e;
        }
        return $data;
    }
}
