<?php
use Oxzion\Db\Persistence\Persistence;
use Oxzion\AppDelegate\FileDelegate;

class PolicyCheck extends FileDelegate
{

    public function __construct()
    {
        parent::__construct();
    }

    public function execute(array $data, Persistence $persistenceService)
    {
        $this->logger->info("Policy Check".print_r($data,true));
        if(isset($data['product']) && $data['product'] == 'Dive Store' || $data['product'] == 'Group Professional Liability'){
            $padi = $data['business_padi'];
            $field = 'business_padi';                    
        }else{
            $padi = $data['padi'];
            $field = 'padi';
        }

        if(!isset($padi) || (isset($padi) && trim($padi) == '')){
            return $data;
        }
        $params = array();
        $filterParams = array();
        $today = date('Y-m-d');
        $params['status'] = 'Completed';
        $params['entityName'] = $data['product'];
        $filterParams['filter'][0]['filter']['filters'][] = array('field'=>'end_date','operator'=>'gt','value'=>$today);
        $filterParams['filter'][0]['filter']['filters'][] = array('field'=>$field,'operator'=>'eq','value'=>$padi);
        // $filterParams['filter'][0]['filter']['filters'][] = array('field'=>'policyStatus','operator'=>'neq','value'=> 'Cancelled');
        $policyList = $this->getFileList($params,$filterParams);
        if(count($policyList['data']) > 0){
            $data['policy_exists'] = true;
            if(count($policyList['data']) == 1){
                $fileData = json_decode($policyList['data'][0]['data'],true);
                if(isset($fileData['padiEmployee']) && ($fileData['padiEmployee'] == "true" || $fileData['padiEmployee'] == true)){
                    $data['policy_exists'] = false;           
                }
            }
        } else {
            $data['policy_exists'] = false;
        }
        if(isset($data['efrToIPLUpgrade']) && !$data['efrToIPLUpgrade']){
            $data['firstname'] = "";
            $data['lastname'] = "";
            $data['initial'] = "";
        }
        return $data;
    }
}
