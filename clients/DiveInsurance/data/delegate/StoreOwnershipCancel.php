<?php

use Oxzion\AppDelegate\AbstractAppDelegate;
use Oxzion\Db\Persistence\Persistence;
use Oxzion\AppDelegate\AppDelegateTrait;
use Oxzion\AppDelegate\FileTrait;


class StoreOwnershipCancel extends AbstractAppDelegate
{
	use FileTrait;
	use AppDelegateTrait;
    public function __construct()
    {
        parent::__construct();
    }

    public function execute(array $data, Persistence $persistenceService)
    {
        $data['changeOfOwnership'] = isset($data['changeOfOwnership']) ? $data['changeOfOwnership'] : "no";
        if($data['changeOfOwnership'] == "yes"){
            if(isset($data['iterations'])){	
                if($data['iterations'] != 0){
                    $cancelData = array();
                    $cancelData = is_string($data['old_data']) ? json_decode($data['old_data'],true) : $data['old_data'];
                    $cancelData['fileId'] = $data['assocId'];
                    $cancelData['cancellationStatus'] = 'approved';
                    if($data['iterations'] == 1){
                        $cancelData['transfer'] = false;
                    }
                    unset($data['old_data']);
                    $cancelData['reasonforCsrCancellation'] = '{"value": "Change Of Ownership"}';
                    $this->executeDelegate('CancelPolicy',$cancelData); 
                    $this->executeDelegate('DispatchCancelPolicyNotification',$cancelData); 
                }
            }
        }
        return $data;
    }
}