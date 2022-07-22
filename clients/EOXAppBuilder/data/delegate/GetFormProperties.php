
<?php

use Oxzion\Db\Persistence\Persistence;
use Oxzion\AppDelegate\AbstractAppDelegate;
use Oxzion\AppDelegate\AppTrait;
use Oxzion\DelegateException;

class GetFormProperties extends AbstractAppDelegate
{
    use AppTrait;

    public function __construct()
    {
        parent::__construct();
    }

    public function execute(array $data, Persistence $persistenceService)
    {
        $this->logger->info("Data in the delegate----".print_r($data,true));
        if(!isset($data['app']['uuid'])){
            throw new DelegateException("App does not exists", 'app_not_exist');
        }
        $appId = $data['app']['uuid'];
        try{
            foreach($data['formDuplicate'] as $value){
                $property = $this->getAppFormProperties($value['template_file'],$appId);
                $submissionCommands = json_decode($property['submission_commands'],true);
                foreach($submissionCommands as $value1){
                    if(isset($value1['entity_name']) && $value1['entity_name'] != $value['entity']){
                        throw new DelegateException("Entity doesnt match with entity defined in the form", 'app_not_exist');
                    }
                }
            }
            $this->logger->info("Data out of delegate----".print_r($data,true));
        }catch(Exception $e){
            throw $e;
        }
        return $data;
    }
}
