
<?php

use Oxzion\Db\Persistence\Persistence;
use Oxzion\App\AbstractAppUpgrade;

class AppUpgrade1_1 extends AbstractAppUpgrade
{

    public function __construct()
    {
        parent::__construct();
    }

    public function upgrade(array $data)
    {
        $this->logger->info("Data in the AbstractAppUpgrade----".print_r($data,true));
        $data['app']['previousVersion'] = $data['appVersion'];
        return $data;
    }

   
}
