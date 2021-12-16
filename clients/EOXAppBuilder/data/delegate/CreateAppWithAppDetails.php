
<?php

use Oxzion\Db\Persistence\Persistence;
use Oxzion\AppDelegate\AbstractAppDelegate;
use Oxzion\AppDelegate\AppTrait;
use Oxzion\AppDelegate\AppDelegateTrait;



class CreateAppWithAppDetails extends AbstractAppDelegate
{
    use AppTrait;
    use AppDelegateTrait;

    public function __construct()
    {
        parent::__construct();
    }

    public function execute(array $data, Persistence $persistenceService)
    {
        $this->logger->info("Data in the delegate----".print_r($data,true));
        $this->cleanDataBeforeCreation($data);
        $this->CreateAppWithOnlyAppDetails($data);
        $this->logger->info("Data out of delegate----".print_r($data,true));
        return $data;
    }

    private function cleanDataBeforeCreation(&$data){
        $whitelist = ['app'];
        $data = array_intersect_key( $data, array_flip( $whitelist ) );
    }

   
}
