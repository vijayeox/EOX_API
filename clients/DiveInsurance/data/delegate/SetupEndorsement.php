<?php

use Oxzion\AppDelegate\AbstractAppDelegate;
use Oxzion\Db\Persistence\Persistence;
use Oxzion\AppDelegate\UserContextTrait;
use Zend\Db\ResultSet\ResultSet;

class SetupEndorsement extends AbstractAppDelegate
{
    use UserContextTrait;
    public function __construct(){
        parent::__construct();
    }

    public function execute(array $data,Persistence $persistenceService) {
        $privileges = $this->getPrivilege();
        $premiumRateCardDetails = array();
        $this->logger->info("Executing Endorsement Setup".json_encode($data));
        $data['initiatedByUser'] = isset($data['initiatedByUser']) ? $data['initiatedByUser'] : false;
        $data['endorsementInProgress'] = isset($data['endorsementInProgress']) ? $data['endorsementInProgress'] : false;
        if(!$data['initiatedByUser'] && $data['initiatedByUser'] != 'false'){
            if($data['endorsementInProgress'] == false){
                $policy =  array();
                $update_date =  date("Y-m-d");
                $start_date = date($data['start_date']);
                if(isset($privileges['MANAGE_POLICY_APPROVAL_WRITE']) && 
                    $privileges['MANAGE_POLICY_APPROVAL_WRITE'] == true){
                    $data['initiatedByCsr'] = true;
                }else{
                    $data['initiatedByCsr'] = false;
                }
                if($start_date  > $update_date){
                    $policy['update_date'] = $data['update_date'] = $data['start_date'];
                }else{
                    $policy['update_date'] = $data['update_date'] = $update_date;
                }
                $data['endoEffectiveDate'] = $data['update_date'];
                $data['previous_policy_data'] = isset($data['previous_policy_data']) ? $data['previous_policy_data'] : array();
                if(isset($data['single_limit'])){
                    $policy['prevSingleLimit'] = $data['single_limit'];
                }
                if(isset($data['annual_aggregate'])){
                    $policy['prevAnnualAggregate'] = $data['annual_aggregate'];
                }
                if(isset($data['careerCoverage'])){
                    $policy['previous_careerCoverage'] = $data['careerCoverage'];
                }
                if(isset($data['tecRecEndorsment'])){
                    $policy['previous_tecRecEndorsment'] = $data['tecRecEndorsment'];
                }
                if(isset($data['scubaFit'])){
                    $policy['previous_scubaFit'] = $data['scubaFit'];
                }
                if(isset($data['cylinder'])){
                    $policy['previous_cylinder'] = $data['cylinder'];
                }
                if(isset($data['equipment'])){
                    $policy['previous_equipment'] = $data['equipment'];
                }
                if(isset($data['excessLiability'])){
                    $policy['previous_excessLiability'] = $data['excessLiability'];
                }
                if(isset($data['excessLiabilityPrice'])){
                    $policy['prevExcessLiabiltyPrice'] = $data['excessLiabilityPrice'];
                }
                if(isset($data['careerCoveragePrice'])){
                    $policy['previous_careerCoveragePrice'] = $data['careerCoveragePrice'];
                }
                if(isset($data['techRecPrice'])){
                    $policy['previous_techRecPrice'] = $data['techRecPrice'];
                }
                if(isset($data['scubaFitPrice'])){
                    $policy['previous_scubaFitPrice'] = $data['scubaFitPrice'];
                }
                if(isset($data['cylinderPrice'])){
                    $policy['previous_cylinderPrice'] = $data['cylinderPrice'];
                }
                if(isset($data['equipmentPrice'])){
                    $policy['previous_equipmentPrice'] = $data['equipmentPrice'];
                }
                $this->endorsementRates($data,$policy,$privileges,$premiumRateCardDetails,$persistenceService);
                array_unshift($data['previous_policy_data'],$policy);
                $data['endorsementInProgress'] = true;
                if(isset($data['additionalInsured'])){
                    if($data['additionalInsured'] != ""){
                        foreach ($data['additionalInsured'] as $key => $value) {
                            if(!isset($value['effective_date'])){
                                $data['additionalInsured'][$key]['effective_date'] = isset($data['start_date']) ? $data['start_date'] : $data['update_date'];
                            }
                        }
                    }
                }
                if(isset($data['transaction_status'])){
                    $data['transaction_status'] = ($data['transaction_status'] == "Action Not Permitted") ? "" : $data['transaction_status'];
                }
            }else{
                $this->endorsementRates($data,$data['previous_policy_data'][0],$privileges,$premiumRateCardDetails,$persistenceService);
            }
        } else {
            $selectExcessLiability = "select rc.* from premium_rate_card rc WHERE product = '".$data['product']."' and is_upgrade = 0 and coverage_category='EXCESS_LIABILITY' and start_date <= '".$data['update_date']."' AND end_date >= '".$data['update_date']."' order by CAST(rc.previous_key as UNSIGNED) DESC";
            $this->logger->info("Executing Endorsement Rate Card ExcessLiability Query ".$selectExcessLiability);
            $resultExcessLiability = $persistenceService->selectQuery($selectExcessLiability);
            while ($resultExcessLiability->next()) {
                $rate = $resultExcessLiability->current();
                $endorsementExcessLiability[$rate['key']] = $rate['coverage'];
                unset($rate);
            }
            $data['endorsementExcessLiability'] = $endorsementExcessLiability;

            if($data['product'] == 'Individual Professional Liability'){
                $endorsementCoverages = $this->coverageSelection($data,$data['previous_policy_data'][0],$data['previous_policy_data'][0]['previous_careerCoverage'],$persistenceService,$premiumRateCardDetails,$privileges);
                $data['endorsementCoverage'] = $endorsementCoverages;
                $this->getExcessLiabilityRates($data,$premiumRateCardDetails,$data['previous_policy_data'][0],$privileges,$persistenceService);
                $this->getCylinderRates($data,$premiumRateCardDetails,$data['previous_policy_data'][0],$privileges,$persistenceService);
                $this->getEquipmentRates($data,$premiumRateCardDetails,$data['previous_policy_data'][0],$privileges,$persistenceService);
                $this->getTechRecRates($data,$premiumRateCardDetails,$data['previous_policy_data'][0],$privileges,$persistenceService);
                $this->getScubaFitRates($data,$premiumRateCardDetails,$data['previous_policy_data'][0],$privileges,$persistenceService);
            }
            $data['previous_policy_data'][0]['update_date'] = $data['update_date'];
        }
        $returnArray = array_merge($data,$premiumRateCardDetails);
        unset($privileges);
        $this->logger->info("SETUP ENDOR".print_r($data,true));
        return $returnArray;
    }


    private function endorsementRates(&$data,&$policy,$privileges,&$premiumRateCardDetails,$persistenceService){
        $endorsementCoverage = array();
        if(isset($policy['previous_careerCoverage'])){
            $premiumRateCardDetails = array();
            $endorsementCoverages = $this->coverageSelection($data,$policy,$data['careerCoverage'],$persistenceService,$premiumRateCardDetails,$privileges);
            $data['endorsementCoverage'] = $endorsementCoverages;
        }

        $this->getExcessLiabilityRates($data,$premiumRateCardDetails,$policy,$privileges,$persistenceService);

        $this->getCylinderRates($data,$premiumRateCardDetails,$policy,$privileges,$persistenceService);

        $this->getEquipmentRates($data,$premiumRateCardDetails,$policy,$privileges,$persistenceService);

        $this->getTechRecRates($data,$premiumRateCardDetails,$policy,$privileges,$persistenceService);

        $this->getScubaFitRates($data,$premiumRateCardDetails,$policy,$privileges,$persistenceService);
        
        $this->logger->info("Set UP Edorsement Dive Store - END",print_r($data,true));
        if(isset($data['paymentOptions'])){
            $data['paymentOptions'] = "";
        }
        if(isset($data['chequeNumber'])){
            $data['chequeNumber'] = "";
        }
        if(isset($data['chequeConsentFile'])){
            $data['chequeConsentFile'] = array();
        }
        if(isset($data['orderId'])){
            $data['orderId'] = "";
        }
        if(isset($data['transactionId'])){
            $data['transactionId'] = "";
        }
        if(isset($data['approved'])){
            $data['approved'] = "";
        }
        if(isset($data['endorsement_options'])){
            $data['endorsement_options'] = "";
        }
        if(isset($data['disableOptions'])){
            $data['disableOptions'] = "";
        }
        if(isset($data['amountPayable'])){
            $data['amountPayable'] = 0;
        }
        if(isset($data['balanceEndor'])){
            $data['balanceEndor'] = 0;
        }
        if(isset($data['endorAmount'])){
            $data['endorAmount'] = 0;
        }
         if(isset($data['csrGrandTotal'])){
            $data['csrGrandTotal'] = 0;
        }
         if(isset($data['userGrandTotal'])){
            $data['userGrandTotal'] = 0;
        }
        if(isset($data['endor_cylinderInstructor_attachments'])){
            $data['endor_cylinderInstructor_attachments']=array();
        }
        if(isset($data['endor_cylinderInspector_attachments'])){
            $data['endor_cylinderInspector_attachments']=array();
        }
        if(isset($data['endor_techRec_attachments'])){
            $data['endor_techRec_attachments']=array();
        }
        if(isset($data['endor_scubaFit_attachments'])){
            $data['endor_scubaFit_attachments']=array();
        }
        if(isset($data['endor_attachments'])){
            $data['endor_attachments']=array();
        }
        if(isset($data['endorsementTotal'])){
            $data['endorsementTotal']= 0;
        }
        if(isset($data['amount'])){
            $data['amount']= 0;
        }
        $data['initiatedByUser'] = true;
        $policy['update_date'] = $data['update_date'];
    }
    protected function getRates($data,$persistenceService){
        $select = "Select * FROM premium_rate_card WHERE product ='".$data['product']."' AND start_date <= '".$data['update_date']."' AND is_upgrade = 0 AND end_date >= '".$data['update_date']."'";
        $selectTax = "Select state, coverage, percentage FROM state_tax WHERE product = '".$data['product']."' AND start_date <= '".$data['start_date']."' AND end_date >= '".$data['start_date']."'";
        $result = $persistenceService->selectQuery($select);
        $this->logger->info("Rate Card query -> $select");
        $stateTaxResult = $persistenceService->selectQuery($selectTax);
        while ($result->next()) {
            $rate = $result->current();
            if(isset($rate['key'])){
                if(isset($rate['total'])){
                    $premiumRateCardDetails[$rate['key']] = $rate['total'];
                } else {
                    if(isset($rate['tax'])){
                        $total = $rate['tax'] + $rate['premium'];
                        if(isset($rate['padi_fee'])){
                            $total = $rate['padi_fee'] + $total;
                        }
                        $premiumRateCardDetails[$rate['key']] = $total;
                    } else {
                        $premiumRateCardDetails[$rate['key']] = $rate['premium'];
                    }
                }
            }
            unset($rate);
        }
        $stateTaxData = [];
        while ($stateTaxResult->next()) {
            $rate = $stateTaxResult->current();
            array_push($stateTaxData, $rate);
        }

        foreach ($data as $key => $value) {
            if(is_string($value))
            {
                $result = json_decode($value);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data[$key] = $result;
                }
            }
        }
        if(isset($stateTaxData)){
            $premiumRateCardDetails['stateTaxData'] = $stateTaxData;
        }
        if(isset($premiumRateCardDetails)){
            return $premiumRateCardDetails;
        } else {
            return $data;
        }
    }

    protected function coverageSelection(&$data,&$policy,$previous_key,$persistenceService,&$premiumRateCardDetails,$privileges){
            $endorsementCoverages = array();
            $whereClause = "";
            if(isset($privileges['MANAGE_MY_POLICY_READ']) && $privileges['MANAGE_MY_POLICY_READ'] == true && isset($policy['previous_careerCoverage'])){
                $whereClause = "and premium >=0";
            } 
            $select = "Select * FROM premium_rate_card WHERE product ='".$data['product']."' 
                       AND is_upgrade = 1 AND previous_key = '".$previous_key."'  
                       AND start_date <= '".$data['update_date']."' 
                       AND end_date >= '".$data['update_date']."' $whereClause";                
            $this->logger->info("Executing Endorsement Rate Card Query".$select);
            $result = $persistenceService->selectQuery($select);
            while ($result->next()) {
                $rate = $result->current();
                if(isset($rate['key'])){
                    if(isset($rate['total'])){
                        $premiumRateCardDetails[$rate['key']] = $rate['total'];
                    } else {
                        $premiumRateCardDetails[$rate['key']] = $rate['premium'];
                    }
                    if($rate['key'] == $policy['previous_careerCoverage']){
                        $policy['previous_careerCoverageLabel'] = $rate['coverage'];
                        $premiumRateCardDetails[$rate['key']] = 0;
                        $data['careerCoveragePrice'] = 0;
                    }
                    $endorsementCoverages[$rate['key']] = $rate['coverage'];
                }
            }
            return $endorsementCoverages;
    }


    private function getExcessLiabilityRates(&$data,&$premiumRateCardDetails,&$policy,$privileges,$persistenceService){
        if(isset($policy['previous_excessLiability'])){
            $endorsementExcessLiability = array();
            $fromClause = "";
            $phWhereClause = "";
            $endorsementExcessLiability = array();
            if(isset($privileges['MANAGE_MY_POLICY_READ']) && $privileges['MANAGE_MY_POLICY_READ'] == true && isset($policy['previous_excessLiability'])){
                $fromClause = ",(select distinct previous_key from premium_rate_card where `key` =  '".$policy['previous_excessLiability']."' and is_upgrade=0  and product = '".$data['product']."' ) pkc";
                $phWhereClause = " and CAST(rc.previous_key as UNSIGNED)>= CAST(pkc.previous_key as UNSIGNED)";
                $selectExcessLiability = "select rc.* from premium_rate_card rc $fromClause  WHERE product = '".$data['product']."' and is_upgrade = 0 and coverage_category='EXCESS_LIABILITY' and start_date <= '".$data['update_date']."' AND end_date >= '".$data['update_date']."' $phWhereClause order by CAST(rc.previous_key as UNSIGNED) ASC";
            } else {
                $selectExcessLiability = "select rc.* from premium_rate_card rc WHERE product = '".$data['product']."' and is_upgrade = 0 and coverage_category='EXCESS_LIABILITY' and start_date <= '".$data['update_date']."' AND end_date >= '".$data['update_date']."' order by CAST(rc.previous_key as UNSIGNED) ASC";
            }

            $this->logger->info("Executing Endorsement Rate Card ExcessLiability Query ".$selectExcessLiability);
            $resultExcessLiability = $persistenceService->selectQuery($selectExcessLiability);
            while ($resultExcessLiability->next()) {
                $rate = $resultExcessLiability->current();
                if(isset($rate['key'])){
                    if(isset($rate['total'])){
                        $premiumRateCardDetails[$rate['key']] = $rate['total'];
                    } else {
                        $premiumRateCardDetails[$rate['key']] = $rate['premium'];
                    }
                    if($rate['key'] == $policy['previous_excessLiability']){
                        $policy['previous_excessLiabilityLabel'] = $rate['coverage'];
                        $endorsementExcessLiability[$rate['key']] = $rate['coverage'];
                        $data['excessLiabilityPrice'] = $rate['total'];
                    } else {
                        $endorsementExcessLiability[$rate['key']] = $rate['coverage'];
                    }
                }
                unset($rate);
            }
            $data['endorsementExcessLiability'] = $endorsementExcessLiability;
        }
    }

    private function getCylinderRates(&$data,&$premiumRateCardDetails,&$policy,$privileges,$persistenceService){
        if(isset($policy['previous_cylinder'])){
            $this->logger->info("PREVIOUS CYLINDER VALUE ---".print_r($policy['previous_cylinder'],true));
            $endorsementCylinder = array();
            $selectCylinder = "Select * FROM premium_rate_card WHERE product ='".$data['product']."' AND is_upgrade = 1 AND previous_key = '".$policy['previous_cylinder']."' AND start_date <= '".$data['update_date']."' AND end_date >= '".$data['update_date']."'";
            $this->logger->info("Executing Endorsement Rate Card Cylinder Query ".$selectCylinder);
            $resultCylinder = $persistenceService->selectQuery($selectCylinder);
            while ($resultCylinder->next()) {
                $rate = $resultCylinder->current();
                if(isset($rate['key'])){
                    if(isset($rate['total'])){
                        $premiumRateCardDetails[$rate['key']] = $rate['total'];
                    } else {
                        $premiumRateCardDetails[$rate['key']] = $rate['premium'];
                    }
                    $this->logger->info("RATE CYLINDER -- ".print_r($rate['key'],true));
                    if($rate['key'] == $policy['previous_cylinder']){
                        $policy['previous_cylinderLabel'] = $rate['coverage'];
                        $premiumRateCardDetails[$rate['key']] = 0;
                        $data['cylinderPrice'] = 0;
                    }
                    $endorsementCylinder[$rate['key']] = $rate['coverage'];
                }
                unset($rate);
            }
            $data['endorsementCylinder'] = $endorsementCylinder;
        }
    }

    private function getEquipmentRates(&$data,&$premiumRateCardDetails,&$policy,$privileges,$persistenceService){
        if(isset($policy['previous_equipment'])){
            $endorsementEquipment = array();
            $selectEquipment = "Select * FROM premium_rate_card WHERE product ='".$data['product']."' AND is_upgrade = 1 AND previous_key = '".$policy['previous_equipment']."' AND start_date <= '".$data['update_date']."' AND end_date >= '".$data['update_date']."'";
            $this->logger->info("Executing Endorsement Rate Card Equipment Query".$selectEquipment);
            $resultEquipment= $persistenceService->selectQuery($selectEquipment);
            if($resultEquipment->count() == 0){
                $premiumRateCardDetails[$data['equipment']] = 0;
                $data['equipmentPrice'] = 0;
            }
            while ($resultEquipment->next()) {
                $rate = $resultEquipment->current();
                if(isset($rate['key'])){
                    if(isset($rate['total'])){
                        $premiumRateCardDetails[$rate['key']] = $rate['total'];
                    } else {
                        $premiumRateCardDetails[$rate['key']] = $rate['premium'];
                    }
                    if($rate['key'] == $policy['previous_equipment']){
                        $policy['previous_equipmentLabel'] = $rate['coverage'];
                        $premiumRateCardDetails[$rate['key']] = 0;
                        $data['equipmentPrice'] = 0;
                    }
                    $endorsementEquipment[$rate['key']] = $rate['coverage'];
                }
                unset($rate);
            }
            $data['endorsementEquipment'] = $endorsementEquipment;
        }
    }


    private function getTechRecRates(&$data,&$premiumRateCardDetails,&$policy,$privileges,$persistenceService){
         if(isset($policy['previous_tecRecEndorsment'])){
            $endorsementTecRec = array();
            $selectTecRec = "Select * FROM premium_rate_card WHERE product ='".$data['product']."' AND is_upgrade = 1 AND previous_key = '".$policy['previous_tecRecEndorsment']."' AND start_date <= '".$data['update_date']."' AND end_date >= '".$data['update_date']."'";
            $this->logger->info("Executing Endorsement Rate Card TecRec Query".$selectTecRec);
            $resultTecRec= $persistenceService->selectQuery($selectTecRec);
            if($resultTecRec->count() == 0){
                $premiumRateCardDetails[$data['tecRecEndorsment']] = 0;
                $data['techRecPrice'] = 0;
            }
            while ($resultTecRec->next()) {
                $rate = $resultTecRec->current();
                if(isset($rate['key'])){
                    if(isset($rate['total'])){
                        $premiumRateCardDetails[$rate['key']] = $rate['total'];
                    } else {
                        $premiumRateCardDetails[$rate['key']] = $rate['premium'];
                    }
                    if($rate['key'] == $policy['previous_tecRecEndorsment']){
                        $policy['previous_tecRecEndorsmentLabel'] = $rate['coverage'];
                        $premiumRateCardDetails[$rate['key']] = 0;
                        $data['techRecPrice'] = 0;
                    }
                    $endorsementTecRec[$rate['key']] = $rate['coverage'];
                }
                unset($rate);
            }
            $data['endorsementTecRec'] = $endorsementTecRec;
        }
    }

    private function getScubaFitRates(&$data,&$premiumRateCardDetails,&$policy,$privileges,$persistenceService){
        if(isset($policy['previous_scubaFit'])){
            $endorsementScubaFit = array();
            $selectScubafit = "Select * FROM premium_rate_card WHERE product ='".$data['product']."' AND is_upgrade = 1 AND previous_key = '".$policy['previous_scubaFit']."' AND start_date <= '".$data['update_date']."' AND end_date >= '".$data['update_date']."'";
            $this->logger->info("Executing Endorsement Rate Card Scuba fit Query".$selectScubafit);
            $resultScubafit = $persistenceService->selectQuery($selectScubafit);
            if($resultScubafit->count() == 0){
                $premiumRateCardDetails[$data['scubaFit']] = 0;
                $data['scubaFitPrice'] = 0;
            }
            while ($resultScubafit->next()) {
                $rate = $resultScubafit->current();
                if(isset($rate['key'])){
                    if(isset($rate['total'])){
                        $premiumRateCardDetails[$rate['key']] = $rate['total'];
                    } else {
                        $premiumRateCardDetails[$rate['key']] = $rate['premium'];
                    }
                    if($rate['key'] == $policy['previous_scubaFit']){
                        $policy['previous_scubaFitLabel'] = $rate['coverage'];
                        $premiumRateCardDetails[$rate['key']] = 0;
                        $data['scubaFitPrice'] = 0;
                    }
                    $endorsementScubaFit[$rate['key']] = $rate['coverage'];
                }
                unset($rate);
            }
            $data['endorsementScubaFit'] = $endorsementScubaFit;
        }
    }
}
