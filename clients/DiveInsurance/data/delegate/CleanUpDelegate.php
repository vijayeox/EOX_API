<?php

use Oxzion\AppDelegate\AbstractAppDelegate;
use Oxzion\Db\Persistence\Persistence;

class CleanUpDelegate extends AbstractAppDelegate
{
    public function __construct()
    {
        parent::__construct();
    }

    // Premium Calculation values are fetched here
    public function execute(array $data, Persistence $persistenceService)
    {
    	if($data['product'] == 'Individual Professional Liability' || $data['product'] == 'Emergency First Response'){
    		$this->cleanUpDataIplorEfr($data);
    	}else{
    		$this->cleanUpDiveStoreData($data);
    	}
    	return $data;
    }

    private function cleanUpDataIplorEfr(&$data){
    		if(isset($data['business_padi'])){
    			unset($data['business_padi']);
    		}
    		if(isset($data['business_name'])){
    			unset($data['business_name']);
    		}
    		if(isset($data['business_address'])){
    			unset($data['business_address']);
    		}
    		if(isset($data['business_country'])){
    			unset($data['business_country']);	
    		}
    		if(isset($data['business_city'])){
    			unset($data['business_city']);	
    		}
    		if(isset($data['business_state'])){
    			unset($data['business_state']);	
    		}
    		if(isset($data['business_zip'])){
    			unset($data['business_zip']);	
    		}
    		if(isset($data['dba'])){
    			unset($data['dba']);	
    		}
    		if(isset($data['entity_type'])){
    			unset($data['entity_type']);	
    		}
    		if(isset($data['who_are_the_partners'])){
    			unset($data['who_are_the_partners']);	
    		}
    		if(isset($data['who_are_the_shareholders'])){
    			unset($data['who_are_the_shareholders']);	
    		}
    		if(isset($data['others_please_explain'])){
    			unset($data['others_please_explain']);	
    		}
    		if(isset($data['current_insurance_company'])){
    			unset($data['current_insurance_company']);	
    		}
    		if(isset($data['business_website'])){
    			unset($data['business_website']);	
    		}
            if(isset($data['CSRReviewRequired']) && empty($data['approved'])){
                $data['approved'] = "onHold";
                $data['emptyapprove'] = true;
            }
    }

    private function cleanUpDiveStoreData(&$data){
    	if(isset($data['padi'])){
    		unset($data['padi']);
    	}
        if(isset($data['paymentVerified'])){
            $data['paymentVerified'] = "";
        }
        if(isset($data['paymentOptions'])){
            $data['paymentOptions'] = "";
		}
		
		if(isset($data['endorsement_options']) && isset($data['endoAdditionalLocation'])){
            if($data['policyStatus'] != "In Force"){
    			$data['additionalLocations'] = $data['endoAdditionalLocation'];
    			unset($data['endoAdditionalLocation']);
            }
		}
    }
 }