<?php


use Oxzion\AppDelegate\AbstractAppDelegate;
use Oxzion\Db\Persistence\Persistence;


require_once __DIR__."/RateCard.php";

class AutoRenewalRateCard extends RateCard{
    public function __construct(){
        parent::__construct();
        $this->unsetVariables = array(
            'uuid',
            'entity_name',
            'AdditionalInsuredOption',
            'TechRec_attachments',
            'additionalInsured',
            'additional_email',
            'address1',
            'address2',
            'appId',
            'automatic_renewal',
            'businessPadiEmpty',
            'businessPadiNotFound',
            'businessPadiVerified',
            'careerCoverage',
            'city',
            'country',
            'cylinder',
            'cylinderInspector_attachments',
            'cylinderInstructor_attachments',
            'email',
            'equipment',
            'excessLiability',
            'excludedOperation',
            'fax',
            'firstname',
            'home_country_code',
            'home_phone',
            'home_phone_number',
            'identifier_field',
            'initial',
            'lastname',
            'name',
            'padi',
            'padiEmployee',
            'padiNotApplicable',
            'padiNotFound',
            'padiNotFoundCsrReview',
            'padiVerified',
            'padiVerifiedCSRCheck',
            'padi_empty',
            'phone',
            'phone_country_code',
            'phone_number',
            'physical_country',
            'physical_state',
            'physical_zip',
            'product',
            'product_email_id',
            'scubaFit',
            'scubaFit_attachments',
            'state',
            'tecRecEndorsment',
            'username',
            'zip',
            'orgId',
            'state_in_short',
            'transaction_status',
        );
    }
    // Premium Calculation values are fetched here
    public function execute(array $data,Persistence $persistenceService)
    {  
        $this->logger->info("AutoRenewal Rate Card");
        $endDate = $data['end_date'];
        $data = $this->cleanData($data);
        $startYear = date_parse($endDate)['year'];
        $endYear = $startYear + 1;
        
        $data['csrPolicyPeriod'] = $data['start_date'] = $startYear."-06-30";
        $data['end_date'] = $endYear."-06-30";
        $policy_period = "July 01,".$startYear." - June 30,".$endYear;
        $data['start_date_range'] = array("label" => $policy_period,"value" => $data['start_date']); 


        $this->logger->info("AUTO RATE CARD PERSISTENCE".print_r($data,true));
        
        $data = parent::execute($data,$persistenceService);
        $this->logger->info("PRESENT RATE card".print_r($data,true));
        if($data['product'] == 'Individual Professional Liability'){
            $coverageList = array();
            array_push($coverageList,$data['careerCoverage']);
            array_push($coverageList,$data['scubaFit']);
            array_push($coverageList,$data['cylinder']);
            array_push($coverageList,$data['excessLiability']);
            $result = $this->getCoverageName($coverageList,$data['product'],$persistenceService);
            $result = json_decode($result,true);
            $this->IPLRates($data,$result);
        } else if($data['product'] == 'Emergency First Response'){
            $coverageList = array();
            array_push($coverageList,$data['excessLiability']);
            $result = $this->getCoverageName($coverageList,$data['product'],$persistenceService);
            $result = json_decode($result,true);
            $this->EFRRates($data,$result);
        }else if($data['product'] == 'Dive Boat'){
            $this->DiveBoatRates($data);
        }else if($data['product'] == 'Dive Store'){
            $this->DiveStoreRates($data);
        }
        $data['CSRReviewRequired'] = false;
        $data['verified'] = true;
        $data['efrToIPLUpgrade'] = false;
        $this->logger->info("AutoRenewalRateCard Final DATA".print_r($data,true));
        return $data;
    }

    private function IPLRates(&$data,$coverages){
        $this->logger->info("IPL RATES");
        $data['careerCoveragePrice'] = $data[$data['careerCoverage']];
        $data['scubaFitPrice'] = $data[$data['scubaFit']];
        $data['equipmentPrice'] = $data[$data['equipment']];
        $data['cylinderPrice'] = $data[$data['cylinder']];
        $data['excessLiabilityPrice'] = $data[$data['excessLiability']];
        if(isset($coverages[$data['careerCoverage']]) && !isset($data['careerCoverageVal'])){
            $data['careerCoverageVal'] = $coverages[$data['careerCoverage']];
        }
        if(isset($coverages[$data['scubaFit']]) && !isset($data['scubaFitVal'])){
            $data['scubaFitVal'] = $coverages[$data['scubaFit']];
        }
        if(isset($coverages[$data['tecRecEndorsment']]) && !isset($data['tecRecVal'])){
            $data['tecRecVal'] = $coverages[$data['tecRecEndorsment']];
        }
        if(isset($coverages[$data['cylinder']]) && !isset($data['cylinderPriceVal'])){
            $data['cylinderPriceVal'] = $coverages[$data['cylinder']];
        }
        if(isset($coverages[$data['excessLiability']])&& !isset($data['excessLiabilityVal'])){
            $data['excessLiabilityVal'] = $coverages[$data['excessLiability']];
        }
        $data['amount'] = (float)$data['careerCoveragePrice']+ (float)$data['scubaFitPrice']+ (float)$data['equipmentPrice'] +  (float)$data['cylinderPrice'] +(float)$data['excessLiabilityPrice'];
    }
    private function getCoverageName($data,$product,$persistenceService){
        $selectQuery = "SELECT group_concat(distinct concat('\"',`key`,'\":\"',coverage,'\"')) as name FROM premium_rate_card WHERE `key` in ('".implode("','", $data) . "')  AND product = '".$product."'";
        $resultQuery = $persistenceService->selectQuery($selectQuery);
        while ($resultQuery->next()) {
            $coverageName[] = $resultQuery->current();
        }
        if($resultQuery->count()!=0){
            return '{'.$coverageName[0]['name'].'}';
        }
    }

    private function EFRRates(&$data,$coverages){
        $this->logger->info("EFR RATES");
        if(isset($coverages[$data['liabilityCoverage1000000']])&& !isset($data['liabilityCoverage1000000'])){
            $data['liabilityVal'] = $coverages['liabilityCoverage1000000'];
        } else {
            $data['liabilityVal'] = $data['liabilityCoverage1000000'];
        }
        if(isset($coverages[$data['excessLiability']])&& !isset($data['excessLiabilityVal'])){
            $data['excessLiabilityVal'] = $coverages[$data['excessLiability']];
        } else {
            $data['excessLiabilityVal'] = $data[$data['excessLiability']];
        }
        $data['coverageAmount'] = (float) $data['liabilityVal']+ (float) $data['excessLiabilityVal'];
        $data['amount'] = (float) $data['coverageAmount'];
    }

    private function DiveBoatRates(&$data){
        $this->logger->info("Dive Boat RATES");
        $data['groupTaxAmount'] = ((float)($data["groupCoverage"])+(float)($data["groupExcessLiability"]))*(float)(isset($data["groupTaxPercentage"])?$data["groupTaxPercentage"] : 0)/100 ;
        $data['groupPadiFeeAmount'] = (float)($data["groupPadiFee"]);
        $data['groupTotalAmount'] = (float)($data["groupTaxAmount"])+(float)($data["groupPadiFeeAmount"])+((float)(isset($data['groupPAORfee'])? ($data['groupPAORfee']) : 0))+(float)($data["groupCoverage"])+(float)($data["groupExcessLiability"]);
        $layUpPeriodTo = DateTime::createFromFormat(DateTime::ISO8601, $data['layup_period_to_date_time']);
        $layUpPeriodFrom = DateTime::createFromFormat(DateTime::ISO8601, $data['layup_period_from_date_time']);
        $data['layup_period'] = ($layUpPeriodTo->diff($layUpPeriodFrom))->format("%d");
        if($data['layup_period']<30){
            $data['layupDeductible'] = 0;
        } else if($data['layup_period'] > 30 && $data['layup_period'] <= 60) {
            $data['layupDeductible'] = ($data['PropertyBasePremium'] * $data['LayupA'])/100;
        } else if($data['layup_period'] > 60 && $data['layup_period'] <= 90) {
            $data['layupDeductible'] = ($data['PropertyBasePremium'] * $data['Layup1'])/100;
        }  else {
            $data['layupDeductible'] = ($data['PropertyBasePremium'] * $data['Layup2'])/100;
        }
        if((float)($data['boat_age'])<5){
            if((float)($data['hull_market_value']) >= 0 && (float)($data['hull_market_value']) <= 25000){
                $data['hullRate']  = (float)($data['hull25000LessThan5']);
            } else if((float)($data['hull_market_value']) > 25000 && (float)($data['hull_market_value']) <= 50000) {
                $data['hullRate'] = (float)($data['hull50000LessThan5']);
            } else if((float)($data['hull_market_value']) > 50000 && (float)($data['hull_market_value']) <= 100000) {
                $data['hullRate'] = (float)($data['hull100000LessThan5']);
            } else if((float)($data['hull_market_value']) > 100000 && (float)($data['hull_market_value']) <= 150000) {
                $data['hullRate'] = (float)($data['hull150000LessThan5']);
            } else if((float)($data['hull_market_value']) > 150000 && (float)($data['hull_market_value']) <= 200000) {
                $data['hullRate'] = (float)($data['hull200000LessThan5']);
            } else if((float)($data['hull_market_value']) > 200000 && (float)($data['hull_market_value']) <= 250000) {
                $data['hullRate'] = (float)($data['hull250000LessThan5']);
            } else if((float)($data['hull_market_value']) > 250000 && (float)($data['hull_market_value']) <= 300000) {
                $data['hullRate'] = (float)($data['hull300000LessThan5']);
            } else if((float)($data['hull_market_value']) > 300000 && (float)($data['hull_market_value']) <= 400000) {
                $data['hullRate'] = (float)($data['hull350000LessThan5']);
            } else if((float)($data['hull_market_value']) > 400000 && (float)($data['hull_market_value']) <= 500000) {
                $data['hullRate'] = (float)($data['hull400000LessThan5']);
            } else if((float)($data['hull_market_value']) > 500000 && (float)($data['hull_market_value']) <= 600000) {
                $data['hullRate'] = (float)($data['hull500000LessThan5']);
            } else {
                $data['hullRate'] = (float)($data['hull600000LessThan5']);
            }
        } else if((float)($data['boat_age']) > 5 && (float)($data['boat_age']) <= 11) {
            if((float)($data['hull_market_value']) >= 0 && (float)($data['hull_market_value']) <= 25000){
                $data['hullRate'] = (float)($data['hull25000LessThan11']);
            } else if((float)($data['hull_market_value']) > 25000 && (float)($data['hull_market_value']) <= 50000) {
                $data['hullRate'] = (float)($data['hull50000LessThan11']);
            } else if((float)($data['hull_market_value']) > 50000 && (float)($data['hull_market_value']) <= 100000) {
                $data['hullRate'] = (float)($data['hull100000LessThan11']);
            } else if((float)($data['hull_market_value']) > 100000 && (float)($data['hull_market_value']) <= 150000) {
                $data['hullRate'] = (float)($data['hull150000LessThan11']);
            } else if((float)($data['hull_market_value']) > 150000 && (float)($data['hull_market_value']) <= 200000) {
                $data['hullRate'] = (float)($data['hull200000LessThan11']);
            } else if((float)($data['hull_market_value']) > 200000 && (float)($data['hull_market_value']) <= 250000) {
                $data['hullRate'] = (float)($data['hull250000LessThan11']);
            } else if((float)($data['hull_market_value']) > 250000 && (float)($data['hull_market_value']) <= 300000) {
                $data['hullRate'] = (float)($data['hull300000LessThan11']);
            } else if((float)($data['hull_market_value']) > 300000 && (float)($data['hull_market_value']) <= 400000) {
                $data['hullRate'] = (float)($data['hull350000LessThan11']);
            } else if((float)($data['hull_market_value']) > 400000 && (float)($data['hull_market_value']) <= 500000) {
                $data['hullRate'] = (float)($data['hull400000LessThan11']);
            } else if((float)($data['hull_market_value']) > 500000 && (float)($data['hull_market_value']) <= 600000) {
                $data['hullRate'] = (float)($data['hull500000LessThan11']);
            } else {
                $data['hullRate'] = (float)($data['hull600000LessThan11']);
            }
        }else if((float)($data['boat_age']) > 11 && (float)($data['boat_age']) <= 25) {
            if((float)($data['hull_market_value']) >= 0 && (float)($data['hull_market_value']) <= 25000){
                $data['hullRate'] = (float)($data['hull25000LessThan25']);
            } else if((float)($data['hull_market_value']) > 25000 && (float)($data['hull_market_value']) <= 50000) {
                $data['hullRate'] = (float)($data['hull50000LessThan25']);
            } else if((float)($data['hull_market_value']) > 50000 && (float)($data['hull_market_value']) <= 100000) {
                $data['hullRate'] = (float)($data['hull100000LessThan25']);
            } else if((float)($data['hull_market_value']) > 100000 && (float)($data['hull_market_value']) <= 150000) {
                $data['hullRate'] = (float)($data['hull150000LessThan25']);
            } else if((float)($data['hull_market_value']) > 150000 && (float)($data['hull_market_value']) <= 200000) {
                $data['hullRate'] = (float)($data['hull200000LessThan25']);
            } else if((float)($data['hull_market_value']) > 200000 && (float)($data['hull_market_value']) <= 250000) {
                $data['hullRate'] = (float)($data['hull250000LessThan25']);
            } else if((float)($data['hull_market_value']) > 250000 && (float)($data['hull_market_value']) <= 300000) {
                $data['hullRate'] = (float)($data['hull300000LessThan25']);
            } else if((float)($data['hull_market_value']) > 300000 && (float)($data['hull_market_value']) <= 400000) {
                $data['hullRate'] = (float)($data['hull350000LessThan25']);
            } else if((float)($data['hull_market_value']) > 400000 && (float)($data['hull_market_value']) <= 500000) {
                $data['hullRate'] = (float)($data['hull400000LessThan25']);
            } else if((float)($data['hull_market_value']) > 500000 && (float)($data['hull_market_value']) <= 600000) {
                $data['hullRate'] = (float)($data['hull500000LessThan25']);
            } else {
                $data['hullRate'] = (float)($data['hull600000LessThan25']);
            }
        } else {
            if((float)($data['hull_market_value']) >= 0 && (float)($data['hull_market_value']) <= 25000){
                $data['hullRate'] = (float)($data['hull25000GreaterThan25']);
            } else if((float)($data['hull_market_value']) > 25000 && (float)($data['hull_market_value']) <= 50000) {
                $data['hullRate'] = (float)($data['hull50000GreaterThan25']);
            } else if((float)($data['hull_market_value']) > 50000 && (float)($data['hull_market_value']) <= 100000) {
                $data['hullRate'] = (float)($data['hull100000GreaterThan25']);
            } else if((float)($data['hull_market_value']) > 100000 && (float)($data['hull_market_value']) <= 150000) {
                $data['hullRate'] = (float)($data['hull150000GreaterThan25']);
            } else if((float)($data['hull_market_value']) > 150000 && (float)($data['hull_market_value']) <= 200000) {
                $data['hullRate'] = (float)($data['hull200000GreaterThan25']);
            } else if((float)($data['hull_market_value']) > 200000 && (float)($data['hull_market_value']) <= 250000) {
                $data['hullRate'] = (float)($data['hull250000GreaterThan25']);
            } else if((float)($data['hull_market_value']) > 250000 && (float)($data['hull_market_value']) <= 300000) {
                $data['hullRate'] = (float)($data['hull300000GreaterThan25']);
            } else if((float)($data['hull_market_value']) > 300000 && (float)($data['hull_market_value']) <= 400000) {
                $data['hullRate'] = (float)($data['hull350000GreaterThan25']);
            } else if((float)($data['hull_market_value']) > 400000 && (float)($data['hull_market_value']) <= 500000) {
                $data['hullRate'] = (float)($data['hull400000GreaterThan25']);
            } else if((float)($data['hull_market_value']) > 500000 && (float)($data['hull_market_value']) <= 600000) {
                $data['hullRate'] = (float)($data['hull500000GreaterThan25']);
            } else {
                $data['hullRate'] = (float)($data['hull600000GreaterThan25']);
            }
        }
        if(((float)($data['hull_market_value']) * (float)($data['hullRate'])/100)<500){
            $data['HullPremium'] = 500;
        }else {
            $data['HullPremium'] = ((float)($data['hull_market_value']) * (float)($data['hullRate'])/100);
        }
        $data['DingyTenderPremium'] = ((float)($data['dingy_value']) * (float)($data['hullRate'])/100);
        $data['TrailerPremium'] = ((float)($data['trailer_value']) * (float)($data['hullRate'])/100);
        $data['PropertyBasePremium'] = ((float)($data['TrailerPremium'])+ (float)($data['DingyTenderPremium'])+(float)($data['HullPremium']));
        if((float)($data['boat_age']>24)){
            $data['Age25Surcharge'] =  ((float)($data['PropertyBasePremium']) * (float)($data['DeductibleGreaterthan24'])/100);
        } else {
            $data['Age25Surcharge'] =  0;
        }
        $data['NavWaterSurchargePremium'] = $data['NavWaterSurchargeYN']?((float)($data['PropertyBasePremium'])*(float)($data['NavWaterSurcharge'])/100):0;
        $data['PortRiskCredit'] = $data['PortRiskYN']?((float)($data['PropertyBasePremium'])*(float)($data['PortRisk'])/100):0;
        $data['NavigationCredit'] = $data['NavigationCreditYN']?((float)($data['PropertyBasePremium'])*(float)($data['Navigation'])/100):0;
        $data['SuperiorRiskCredit'] = $data['SuperiorRiskCreditYN']?((float)($data['PropertyBasePremium'])*(float)($data['SuperiorRisk'])/100):0;
        $data['PropertySubTotal'] = ((float)($data['PropertyBasePremium'])+((float)($data['Age25Surcharge']) || 0)+((float)($data['NavWaterSurchargePremium']) || 0)) -  (((float)($data['PortRiskCredit']) || 0) + ((float)($data['SuperiorRiskCredit']) || 0) + ((float)($data['NavigationCredit']) || 0));
        $data['PropertySubTotalProRated'] = ((float)($data['ProRataFactor'])*(float)($data['PropertySubTotal']));
        $data['ProRataFactor'] = ((float)($data['ProRataDays'])/365);
        $data['LiabilityPremiumCost'] = (float)($data['LiabilityPremium1M']);
        $data['ExcessLiabilityPremium'] = $data[$data['excess_liability_coverage']] ;
        if((float)($data['DingyTenderPremium'])>0){
            $data['DingyLiability'] =  (float)($data['DingyLiabilityPremium']);
        } else {
            $data['DingyLiability'] =  0;
        }
        $data['PassengerPremiumCost'] = (float)($data['PassengerPremium']) * ((float)($data['certified_for_max_number_of_passengers']) || 0);
        $data['CrewOnBoatPremium'] = (((float)(isset($data['CrewInBoatCount']))?$data['CrewInBoatCount'] : 0)* ((float)(isset($data['CrewInBoat']))? $data['CrewInBoat']: 0));
        $data['CrewMembersinWaterPremium'] = (((float)(isset($data['CrewInWaterCount']))?$data['CrewInWaterCount']: 0) * ((float)(isset($data['CrewInWater']))?($data['CrewInWater']):0));
        $data['totalLiability'] = ((float)(isset($data['CrewMembersinWaterPremium']))?$data['CrewMembersinWaterPremium']:0) + ((float)(isset($data['CrewOnBoatPremium']))? $data['CrewOnBoatPremium']:0) + ((float)(isset($data['PassengerPremiumCost']))? $data['PassengerPremiumCost']:0) + ((float)(isset($data['DingyLiability']))?$data['DingyLiability']:0) + ((float)(isset($data['ExcessLiabilityPremium']))?$data['ExcessLiabilityPremium']:0) + ((float)(isset($data['LiabilityPremiumCost']))?$data['LiabilityPremiumCost']:0);
        if($data['FL-HISurchargeYN']){
            $data['FlHiSurchargePremium'] = ((float)($data['totalLiability'])*(float)($data['FL-HISurcharge']))/100;
        } else {
            $data['FlHiSurchargePremium'] = 0;
        }
        $data['LiabilitySubTotal'] = (((float)($data['FlHiSurchargePremium']) || 0) + ((float)($data['CrewMembersinWaterPremium']) || 0) + ((float)($data['CrewOnBoatPremium']) || 0) + ((float)($data['PassengerPremiumCost']) || 0) + ((float)($data['DingyLiability']) | 0) + ((float)($data['ExcessLiabilityPremium']) || 0) + ((float)($data['LiabilityPremiumCost']) || 0));
        $data['LiabilitySubTotalProRated'] = ((float)($data['ProRataFactor'])*(float)($data['LiabilitySubTotal']));
        $data['premiumTotalProRated'] = ((float)($data['PropertySubTotalProRated']) || 0)+((float)($data['LiabilitySubTotalProRated']) || 0);
        $data['groupProfessionalLiabilityPrice'] = ((float)($data["groupTotalAmount"]) || 0);

        $data['total'] = (((float)($data['premiumTotalProRated']) || 0)+((float)($data['groupProfessionalLiabilityPrice']) || 0)+((float)($data['padiFee']) || 0));
        $data['boat_age'] = date('YYYY') - $data['built_year'];
        $data['DateEffective'] = date('Y-m-d');

        $endDate = DateTime::createFromFormat('Y-m-d', $data['end_date']);
        $effectiveDate = DateTime::createFromFormat('Y-m-d', $data['DateEffective']);
        $data['ProRataDays'] = $endDate->diff($effectiveDate)->format("%d");
        $data['amount'] = (float)$data['total'];
    }

    private function DiveStoreRates(&$data){
        $this->logger->info("DIVE STORE RATES");
        $this->logger->info("DIVE STORE RATES RATE card".print_r($data,true));     
        $stateTaxData = $data["stateTaxData"];
        $data['dspropTotal'] = ((float)(isset($data['dspropFurniturefixturesandequip']))?$data['dspropFurniturefixturesandequip']:0)+((float)(isset($data['dspropinventory']))?$data['dspropinventory']:0)+((float)(isset($data['dspropofothers']))?$data['dspropofothers']:0)+((float)(isset($data['dspropsignsattachedordetached']))?$data['dspropsignsattachedordetached']:0)+((float)(isset($data['dspropTennantImprv']))?$data['dspropTennantImprv']:0)+((float)(isset($data['dspropother']))?$data['dspropother']:0)+((float)(isset($data['dspropownpropincludingcompr']))?$data['dspropownpropincludingcompr']:0);

        $data['additionalLocationPropertyTotal'] = ((float)(isset($data["additionalLocationFurniturefixturesAndEquipment"])?$data["additionalLocationFurniturefixturesAndEquipment"]:0))+((float)(isset($data["additionalLocationTableInventoryStock"])?$data["additionalLocationTableInventoryStock"]:0))+((float)(isset($data["additionalLocationPropertyOfOthers"])?$data["additionalLocationPropertyOfOthers"]:0))+((float)(isset($data["additionalLocationSignsAttachedOrDetached"])?$data["additionalLocationSignsAttachedOrDetached"]:0))+(float)(isset($data["additionalLocationTenantImprovements"])?$data["additionalLocationTenantImprovements"]:0)+((float)(isset($data["additionalLocationOther"])?$data["additionalLocationOther"]:0))+((float)(isset($data["additionalLocationDoYouOwnPropertyIncludingCompressorsOffPremises"])?$data["additionalLocationDoYouOwnPropertyIncludingCompressorsOffPremises"]:0));

        for ($i = 0; $i < sizeof($data['additionalLocations']); $i++) {

           $data['ALliabilityTaxPercentage'] = $this->getLiability($data['additionalLocations'][$i],$stateTaxData);
           $data['ALPropertyTaxPercentage'] = $this->getValueProperty($data['additionalLocations'][$i],$stateTaxData);

            if($data['additionalLocations'][$i]['ALCoverageCheckBox'] == "true" || $data['additionalLocations'][$i]['ALCoverageCheckBox'] == true){
                $data['ALCoverageFP']=(float)(isset($data["CoverageFP"])?$data['CoverageFP']:0)/10;
            }
            else{
                $data['ALCoverageFP'] = 0;
            }

            if (((float)(isset($data['additionalLocations'][$i]['ALPoolLiability']))?$data['additionalLocations'][$i]['ALPoolLiability']:0) > 50000) {
              $data['ALnonDivingPoolAmount'] = $data["poolLiabilityOver50k"];
          } else if (((float)(isset($data['additionalLocations'][$i]['ALPoolLiability']))?$data['additionalLocations'][$i]['ALPoolLiability']:0) > 20000) {
              $data['ALnonDivingPoolAmount'] = $data["poolLiabilityOver20k"];
          } else if (((float)(isset($data['additionalLocations'][$i]['ALPoolLiability']))?$data['additionalLocations'][$i]['ALPoolLiability']:0) > 0) {
              $data['ALnonDivingPoolAmount'] = $data["poolLiabilityOver0"];
          } else {
              $data['ALnonDivingPoolAmount'] = 0;
          }

          if($data['additionalLocations'][$i]['ALMedicalExpenseCheckBox'] == "true" || $data['additionalLocations'][$i]['ALMedicalExpenseCheckBox'] == true){
            $data['ALMedicalExpenseFP'] = ((float)(isset($data["MedicalExpenseFP"]))?$data['MedicalExpenseFP']:0)/10;
        }
        else{
            $data['ALMedicalExpenseFP'] = 0;
        }

        if($data['additionalLocations'][$i]['ALNonOwnedAutoCheckBox'] == "true" || $data['additionalLocations'][$i]['ALNonOwnedAutoCheckBox'] == true){
            $data['ALNonOwnedAutoFP'] =((float)(isset($data["Non-OwnedAutoFP"]))?$data["Non-OwnedAutoFP"]:0)/10;
        }
        else{
            $data['ALNonOwnedAutoFP'] = 0;
        }

        if($data['additionalLocations'][$i]['ALExcessLiabilityCheckBox'] == "true" || $data['additionalLocations'][$i]['ALExcessLiabilityCheckBox'] == true){
            $data['ALExcessLiabilityFP'] = ((float)(isset($data["ExcessLiabilityFP"]))?$data["ExcessLiabilityFP"]:0)/10;
        }
        else{
            $data['ALExcessLiabilityFP'] = 0;
        }
        
        $amount = ((float)(isset($data['additionalLocations'][$i]['ReceiptsAmont']))?$data['additionalLocations'][$i]['ReceiptsAmont']:0);
        if($amount > 100000){
            $data['ALTravelAgentEOFP'] = $data["TAEO100kTo500k"];
        }
        else if($amount > 0){
            $data['ALTravelAgentEOFP'] = $data["TAEOunder100k"];
        }

        $locAmount = ((float)(isset($data['additionalLocations'][$i]['additionalLocationPropertyTotal']))?$data['additionalLocations'][$i]['additionalLocationPropertyTotal']:0);
        $option = $data['additionalLocations'][$i]['ALPropertyCoverageOption'];
        if ($locAmount > 500000 && $option == "nonCat") 
        {
            $data['ALContentsFP'] = ((float)(isset($data["limitOver500000CoverBuildingNonCat"]))?$data["limitOver500000CoverBuildingNonCat"]:0) * $locAmount;
        } else if (
            $locAmount > 500000 && $option == "cat") 
        {
            $data['ALContentsFP'] = ((float)(isset($data["limitOver500000CoverBuildingCat"]))?$data["limitOver500000CoverBuildingCat"]:0) * $locAmount;
        } else if (
            $locAmount > 250000 && $option == "nonCat")
        {
            $data['ALContentsFP'] = ((float)(isset($data["limitOver250000CoverBuildingNonCat"]))?$data["limitOver250000CoverBuildingNonCat"]:0) * $locAmount;
        } else if (
            $locAmount > 250000 &&
            $option == "cat"
        ) {
            $data['ALContentsFP'] = ((float)(isset($data["limitOver250000CoverBuildingCat"]))?$data["limitOver250000CoverBuildingCat"]:0) * $locAmount;
        } else if (
            $locAmount > 100000 &&
            $option == "nonCat"
        ) {
            $data['ALContentsFP'] = ((float)(isset($data["limitOver100000CoverBuildingNonCat"]))?$data["limitOver100000CoverBuildingNonCat"]:0) * $locAmount;
        } else if (
            $locAmount > 100000 &&
            $option == "cat"
        ) {
            $data['ALContentsFP'] = ((float)(isset($data["limitOver100000CoverBuildingCat"]))?$data["limitOver100000CoverBuildingCat"]:0) * $locAmount;
        } else if (
            $locAmount > 0 &&
            $option == "nonCat"
        ) {
            $data['ALContentsFP'] = ((float)(isset($data["limitOver0CoverBuildingNonCat"]))?$data["limitOver0CoverBuildingNonCat"]:0) * $locAmount;
        } else if ($locAmount > 0 && $option == "cat") {
            $data['ALContentsFP'] = ((float)(isset($data["limitOver0CoverBuildingCat"]))?$data["limitOver0CoverBuildingCat"]:0) * $locAmount;
        } else {
            $data['ALContentsFP'] = 0;
        }

        $busIncomeAmount = ((float)(isset($data['additionalLocations'][$i]['ALLossofBusIncome']))?$data['additionalLocations'][$i]['ALLossofBusIncome']:0);
        $busOption = $data['additionalLocations'][$i]['ALPropertyCoverageOption'];

        if (
          $busIncomeAmount > 500000 &&
          $busOption == "nonCat"
      ) {
          $data['ALLossofBusIncomeFP'] = ((float)(isset($data["limitOver500000CoverBusIncomeNonCat"]))?$data["limitOver500000CoverBusIncomeNonCat"]:0) * $busIncomeAmount;
      } else if (
          $busIncomeAmount > 500000 &&
          $busOption == "cat"
      ) {
          $data['ALLossofBusIncomeFP'] = ((float)(isset($data["limitOver500000CoverBusIncomeCat"]))?$data["limitOver500000CoverBusIncomeCat"]:0) * $busIncomeAmount;
      } else if (
          $busIncomeAmount > 250000 &&
          $busOption == "nonCat"
      ) {
          $data['ALLossofBusIncomeFP'] = ((float)(isset($data["limitOver250000CoverBusIncomeNonCat"]))?$data["limitOver250000CoverBusIncomeNonCat"]:0) * $busIncomeAmount;
      } else if (
          $busIncomeAmount > 250000 &&
          $busOption == "cat"
      ) {
          $data['ALLossofBusIncomeFP'] = ((float)(isset($data["limitOver250000CoverBusIncomeCat"]))?$data["limitOver250000CoverBusIncomeCat"]:0) * $busIncomeAmount;
      } else if (
          $busIncomeAmount > 100000 &&
          $busOption == "nonCat"
      ) {
          $data['ALLossofBusIncomeFP'] = ((float)(isset($data["limitOver100000CoverBusIncomeNonCat"]))?$data["limitOver100000CoverBusIncomeNonCat"]:0) * $busIncomeAmount;
      } else if (
          $busIncomeAmount > 100000 &&
          $busOption == "cat"
      ) {
          $data['ALLossofBusIncomeFP'] = ((float)(isset($data["limitOver100000CoverBusIncomeCat"]))?$data["limitOver100000CoverBusIncomeCat"]:0) * $busIncomeAmount;
      } else if (
          $busIncomeAmount > 0 &&
          $busOption == "nonCat"
      ) {
          $data['ALLossofBusIncomeFP'] = ((float)(isset($data["limitOver0CoverBusIncomeNonCat"]))?$data["limitOver0CoverBusIncomeNonCat"]:0) * $busIncomeAmount;
      } else if (
          $busIncomeAmount > 0 &&
          $busOption == "cat"
      ) {
          $data['ALLossofBusIncomeFP'] = ((float)(isset($data["limitOver0CoverBusIncomeCat"]))?$data["limitOver0CoverBusIncomeCat"]:0) * $busIncomeAmount;
      } else {
          $data['ALLossofBusIncomeFP'] = 0;
      } 

      $buildingAmount = ((float)(isset($data['additionalLocations'][$i]['ALBuildingReplacementValue']))?$data['additionalLocations'][$i]['ALBuildingReplacementValue']:0);
      $buildingOption = $data['additionalLocations'][$i]['ALPropertyCoverageOption'];


      if (
        $buildingAmount > 500000 &&
        $buildingOption == "nonCat"
    ) {
        $data['ALBuildingLimitFP'] =
        ((float)(isset($data["limitOver500000CoverBuildingNonCat"]))?$data["limitOver500000CoverBuildingNonCat"]:0) * $buildingAmount;
    } else if (
        $buildingAmount > 500000 &&
        $buildingOption == "cat"
    ) {
        $data['ALBuildingLimitFP'] =
        ((float)(isset($data["limitOver500000CoverBuildingCat"]))?$data["limitOver500000CoverBuildingCat"]:0) * $buildingAmount;
    } else if (
        $buildingAmount > 250000 &&
        $buildingOption == "nonCat"
    ) {
        $data['ALBuildingLimitFP'] =
        ((float)(isset($data["limitOver250000CoverBuildingNonCat"]))?$data["limitOver250000CoverBuildingNonCat"]:0) * $buildingAmount;
    } else if (
        $buildingAmount > 250000 &&
        $buildingOption == "cat"
    ) {
        $data['ALBuildingLimitFP'] =
        ((float)(isset($data["limitOver250000CoverBuildingCat"]))?$data["limitOver250000CoverBuildingCat"]:0) * $buildingAmount;
    } else if (
        $buildingAmount > 100000 &&
        $buildingOption == "nonCat"
    ) {
        $data['ALBuildingLimitFP'] =
        ((float)(isset($data["limitOver100000CoverBuildingNonCat"]))?$data["limitOver100000CoverBuildingNonCat"]:0) * $buildingAmount;
    } else if (
        $buildingAmount > 100000 &&
        $buildingOption == "cat"
    ) {
        $data['ALBuildingLimitFP'] =
        ((float)(isset($data["limitOver100000CoverBuildingCat"]))?$data["limitOver100000CoverBuildingCat"]:0) * $buildingAmount;
    } else if (
        $buildingAmount > 0 &&
        $buildingOption == "nonCat"
    ) {
        $data['ALBuildingLimitFP'] =
        ((float)(isset($data["limitOver0CoverBuildingNonCat"]))?$data["limitOver0CoverBuildingNonCat"]:0) * $buildingAmount;
    } else if (
        $buildingAmount > 0 &&
        $buildingOption == "cat"
    ) {
        $data['ALBuildingLimitFP'] = ((float)isset($data["limitOver0CoverBuildingCat"])?$data["limitOver0CoverBuildingCat"]:0) * $buildingAmount;
    } else {
        $data['ALBuildingLimitFP'] = 0;
    }


    $data['ALliabilityCoveragesTotal'] = ((float)(isset($data['additionalLocations'][$i]['ALCoverageFP']))?$data['additionalLocations'][$i]['ALCoverageFP']:0) + ((float)(isset($data['additionalLocations'][$i]['ALnonDivingPoolAmount']))?$data['additionalLocations'][$i]['ALnonDivingPoolAmount']:0) + ((float)(isset($data['additionalLocations'][$i]['ALMedicalExpenseFP']))?$data['additionalLocations'][$i]['ALMedicalExpenseFP']:0) + ((float)(isset($data['additionalLocations'][$i]['ALNonOwnedAutoFP']))?$data['additionalLocations'][$i]['ALNonOwnedAutoFP']:0) + ((float)(isset($data['additionalLocations'][$i]['ALExcessLiabilityFP']))?$data['additionalLocations'][$i]['ALExcessLiabilityFP']:0) + ((float)(isset($data['additionalLocations'][$i]['ALTravelAgentEOFP']))?$data['additionalLocations'][$i]['ALTravelAgentEOFP']:0);

    $data['ALPropertyCoveragesTotal'] = ((float)(isset($data['additionalLocations'][$i]['ALContentsFP']))?$data['additionalLocations'][$i]['ALContentsFP']:0) + ((float)(isset($data['additionalLocations'][$i]['ALLossofBusIncomeFP']))?$data['additionalLocations'][$i]['ALLossofBusIncomeFP']:0) + ((float)(isset($data['additionalLocations'][$i]['ALBuildingLimitFP']))?$data['additionalLocations'][$i]['ALBuildingLimitFP']:0);

    $data['ALLiabilityPropertyCoveragesTotal'] = ((float)(isset($data['additionalLocations'][$i]['ALliabilityCoveragesTotal']))?$data['additionalLocations'][$i]['ALliabilityCoveragesTotal']:0) + ((float)(isset($data['additionalLocations'][$i]['ALPropertyCoveragesTotal']))?$data['additionalLocations'][$i]['ALPropertyCoveragesTotal']:0);

    $data['ALProRataPremiumPercentage'] = $data["proRataPercentage"];
    $data['ALProRataPremium'] = ((float)(isset($data['additionalLocations'][$i]['ALLiabilityPropertyCoveragesTotal']))?$data['additionalLocations'][$i]['ALLiabilityPropertyCoveragesTotal']:0)*((float)(isset($data['additionalLocations'][$i]['ALProRataPremiumPercentage']))?$data['additionalLocations'][$i]['ALProRataPremiumPercentage']:0);
        // $data['ALliabilityTaxPercentage'] = check
    $data['ALliabilityTaxTotal'] = ((float)(isset($data['additionalLocations'][$i]['ALliabilityCoveragesTotal']))?$data['additionalLocations'][$i]['ALliabilityCoveragesTotal']:0)*((float)(isset($data['additionalLocations'][$i]['ALliabilityTaxPercentage']))?$data['additionalLocations'][$i]['ALliabilityTaxPercentage']:0)/100;
        // property tax
    $data['ALPropertyTaxTotal'] = ((float)(isset($data['additionalLocations'][$i]['ALPropertyCoveragesTotal']))?$data['additionalLocations'][$i]['ALPropertyCoveragesTotal']:0)*((float)(isset($data['additionalLocations'][$i]['ALPropertyTaxPercentage']))?$data['additionalLocations'][$i]['ALPropertyTaxPercentage']:0)/100;
    $data['ALTotalTax'] = ((float)(isset($data['additionalLocations'][$i]['ALliabilityTaxTotal']))?$data['additionalLocations'][$i]['ALliabilityTaxTotal']:0)+((float)(isset($data['additionalLocations'][$i]['ALPropertyTaxTotal']))?$data['additionalLocations'][$i]['ALPropertyTaxTotal']:0);
    $data['locationTotal'] = ((float)(isset($data['additionalLocations'][$i]['ALProRataPremium']))?$data['additionalLocations'][$i]['ALProRataPremium']:0)+((float)(isset($data['additionalLocations'][$i]['ALTotalTax']))?$data['additionalLocations'][$i]['ALTotalTax']:0);
}

$data['groupCoverage'] = (isset($data[$data["groupCoverageSelect"]])?$data[$data["groupCoverageSelect"]]:0);
$data['groupExcessLiability'] = (isset($data[$data["groupExcessLiabilitySelect"]])?$data[$data["groupExcessLiabilitySelect"]]:0)*$data["groupCoverage"]/100;
        // Taxes

$data['groupTaxAmount'] = (((float)(isset($data["groupCoverage"])?$data["groupCoverage"]:0))+((float)(isset($data["groupExcessLiability"])?$data["groupExcessLiability"]:0)))*((float)(isset($data["groupTaxPercentage"])?$data["groupTaxPercentage"]:0))/100;

if(sizeof($data["groupPL"]) > 0){
   $data['groupPadiFeeAmount'] = $data["groupPadiFee"];
}
else{
   $data['groupPadiFeeAmount'] = 0.00;
}
$data['groupTotalAmount'] = ((float)(isset($data["groupTaxAmount"])?$data["groupTaxAmount"]:0))+((float)(isset($data["groupPadiFeeAmount"])?$data["groupPadiFeeAmount"]:0))+((float)(isset($data["groupPAORfee"])?$data["groupPAORfee"]:0))+((float)(isset($data["groupCoverage"])?$data["groupCoverage"]:0))+((float)(isset($data["groupExcessLiability"])?$data["groupExcessLiability"]:0));
$data['CoverageFP'] = (isset($data[$data["page5PanelColumns2Columns4Coverage"]])?$data[$data["page5PanelColumns2Columns4Coverage"]]:0);

if(((float)(isset($data["poolLiability"])?$data["poolLiability"]:0))>50000){
    $data['nonDivingPoolAmount'] = ((float)(isset($data["poolLiabilityOver50k"])?$data["poolLiabilityOver50k"]:0));
}
else if(((float)(isset($data["poolLiability"])?$data["poolLiability"]:0))>20000){
    $data['nonDivingPoolAmount'] = ((float)(isset($data["poolLiabilityOver20k"])?$data["poolLiabilityOver20k"]:0));
}
else if(((float)(isset($data["poolLiability"])?$data["poolLiability"]:0))>0){
    $data['nonDivingPoolAmount'] = ((float)(isset($data["poolLiabilityOver0"])?$data["poolLiabilityOver0"]:0));
}
else {
    $data['nonDivingPoolAmount'] = 0;
}

if($data["medicalPayment"] == "true" || $data["medicalPayment"] == true){
    $data['MedicalExpenseFP'] = (isset($data["medicalExpense"])?$data["medicalExpense"]:0);
}
else{
    $data['MedicalExpenseFP'] = 0;
}

if($data["nonOwnedAutoLiabilityPL"]=="no"){
    $data['Non-OwnedAutoFP']=0;
}
else if($data["nonOwnedAutoLiabilityPL"]=="100K"){
    $data['Non-OwnedAutoFP']=(isset($data["nonOwnedAutoLiability100K"])?$data["nonOwnedAutoLiability100K"]:0);
}
else if($data["nonOwnedAutoLiabilityPL"]=="1M"){
    $data['Non-OwnedAutoFP']=(isset($data["nonOwnedAutoLiability1M"])?$data["nonOwnedAutoLiability1M"]:0);
}
if($data["excessLiabilityCoverage"]=="no"){
    $data['ExcessLiabilityFP']=0;
}
else if($data["excessLiabilityCoverage"]=="1M"){
    $data['ExcessLiabilityFP']=(isset($data["excessLiabilityCoverage1M"])?$data["excessLiabilityCoverage1M"]:0);
}
else if($data["excessLiabilityCoverage"]=="2M"){
    $data['ExcessLiabilityFP']=(isset($data["excessLiabilityCoverage2M"])?$data["excessLiabilityCoverage2M"]:0);
}
else if($data["excessLiabilityCoverage"]=="3M"){
    $data['ExcessLiabilityFP']=(isset($data["excessLiabilityCoverage3M"])?$data["excessLiabilityCoverage3M"]:0);
}
else if($data["excessLiabilityCoverage"]=="4M"){
    $data['ExcessLiabilityFP']=(isset($data["excessLiabilityCoverage4M"])?$data["excessLiabilityCoverage4M"]:0);
}
else if($data["excessLiabilityCoverage"]=="9M"){
    $data['ExcessLiabilityFP']=(isset($data["excessLiabilityCoverage9M"])?$data["excessLiabilityCoverage9M"]:0);
}
if(((float)(isset($data["travelAgentEOReceiptsPL"])?$data["travelAgentEOReceiptsPL"]:0))>0){
    $data['TravelAgentEOFP'] = ((float)(isset($data["TAEOunder100k"])?$data["TAEOunder100k"]:0));
}
else if(((float)(isset($data["travelAgentEOReceiptsPL"])?$data["travelAgentEOReceiptsPL"]:0))>100000){
    $data['TravelAgentEOFP'] = ((float)(isset($data["TAEO100kTo500k"])?$data["TAEO100kTo500k"]:0));
}

if((((float)(isset($data["dspropTotal"])?$data["dspropTotal"]:0)) > 500000) && ($data["propertyCoverageOption"] == "nonCat")){
    $data['ContentsFP'] = ((float)(isset($data["limitOver500000CoverBuildingNonCat"])?$data["limitOver500000CoverBuildingNonCat"]:0)) * ((float)(isset($data["dspropTotal"])?$data["dspropTotal"]:0));
} else if (
  (((float)(isset($data["dspropTotal"])?$data["dspropTotal"]:0)) > 500000) &&
  ($data["propertyCoverageOption"] == "cat")
) {
  $data['ContentsFP'] = ((float)(isset($data["limitOver500000CoverBuildingCat"])?$data["limitOver500000CoverBuildingCat"]:0)) * ((float)(isset($data["dspropTotal"])?$data["dspropTotal"]:0));
} else if (
  (((float)(isset($data["dspropTotal"])?$data["dspropTotal"]:0)) > 250000) &&
  ($data["propertyCoverageOption"] == "nonCat")
) {
  $data['ContentsFP'] = ((float)(isset($data["limitOver250000CoverBuildingNonCat"])?$data["limitOver250000CoverBuildingNonCat"]:0)) * ((float)(isset($data["dspropTotal"])?$data["dspropTotal"]:0));
} else if (
  (((float)(isset($data["dspropTotal"])?$data["dspropTotal"]:0)) > 250000) &&
  ($data["propertyCoverageOption"] == "cat")
) {
  $data['ContentsFP'] = ((float)(isset($data["limitOver250000CoverBuildingCat"])?$data["limitOver250000CoverBuildingCat"]:0)) * ((float)(isset($data["dspropTotal"])?$data["dspropTotal"]:0));
} else if (
  (((float)(isset($data["dspropTotal"])?$data["dspropTotal"]:0)) > 100000) &&
  ($data["propertyCoverageOption"] == "nonCat")
) {
  $data['ContentsFP'] = ((float)(isset($data["limitOver100000CoverBuildingNonCat"])?$data["limitOver100000CoverBuildingNonCat"]:0)) * ((float)(isset($data["dspropTotal"])?$data["dspropTotal"]:0));
} else if (
  (((float)(isset($data["dspropTotal"])?$data["dspropTotal"]:0)) > 100000) &&
  ($data["propertyCoverageOption"] == "cat")
) {
  $data['ContentsFP'] = ((float)(isset($data["limitOver100000CoverBuildingCat"])?$data["limitOver100000CoverBuildingCat"]:0)) * ((float)(isset($data["dspropTotal"])?$data["dspropTotal"]:0));
} else if (
  (((float)(isset($data["dspropTotal"])?$data["dspropTotal"]:0)) > 0) &&
  ($data["propertyCoverageOption"] == "nonCat")
) {
  $data['ContentsFP'] = ((float)(isset($data["limitOver0CoverBuildingNonCat"])?$data["limitOver0CoverBuildingNonCat"]:0)) * ((float)(isset($data["dspropTotal"])?$data["dspropTotal"]:0));
} else if ((((float)(isset($data["dspropTotal"])?$data["dspropTotal"]:0)) > 0) && (data["propertyCoverageOption"] == "cat")) {
  $data['ContentsFP'] = ((float)(isset($data["limitOver0CoverBuildingCat"])?$data["limitOver0CoverBuildingCat"]:0)) * ((float)(isset($data["dspropTotal"])?$data["dspropTotal"]:0));
} else {
  $data['ContentsFP'] = 0;
}

if (
  ((float)(isset($data["lossOfBusIncome"])?$data["lossOfBusIncome"]:0)) > 500000 &&
  $data["propertyCoverageOption"] == "nonCat"
) {
  $data['LossofBusIncomeFP'] = ((float)(isset($data["limitOver500000CoverBusIncomeNonCat"])?$data["limitOver500000CoverBusIncomeNonCat"]:0)) * ((float)(isset($data["lossOfBusIncome"])?$data["lossOfBusIncome"]:0));
} else if (
  ((float)($data["lossOfBusIncome"])||0) > 500000 &&
  $data["propertyCoverageOption"] == "cat"
) {
  $data['LossofBusIncomeFP'] = ((float)(isset($data["limitOver500000CoverBusIncomeCat"])?$data["limitOver500000CoverBusIncomeCat"]:0)) * ((float)(isset($data["lossOfBusIncome"])?$data["lossOfBusIncome"]:0));
} else if (
  ((float)($data["lossOfBusIncome"])||0) > 250000 &&
  $data["propertyCoverageOption"] == "nonCat"
) {
  $data['LossofBusIncomeFP'] = ((float)(isset($data["limitOver250000CoverBusIncomeNonCat"])?$data["limitOver250000CoverBusIncomeNonCat"]:0)) * ((float)(isset($data["lossOfBusIncome"])?$data["lossOfBusIncome"]:0));
} else if (
  ((float)($data["lossOfBusIncome"])||0) > 250000 &&
  $data["propertyCoverageOption"] == "cat"
) {
  $data['LossofBusIncomeFP'] = ((float)(isset($data["limitOver250000CoverBusIncomeCat"])?$data["limitOver250000CoverBusIncomeCat"]:0)) * ((float)(isset($data["lossOfBusIncome"])?$data["lossOfBusIncome"]:0));
} else if (
  ((float)($data["lossOfBusIncome"])||0) > 100000 &&
  $data["propertyCoverageOption"] == "nonCat"
) {
  $data['LossofBusIncomeFP'] = ((float)(isset($data["limitOver100000CoverBusIncomeNonCat"])?$data["limitOver100000CoverBusIncomeNonCat"]:0)) * ((float)(isset($data["lossOfBusIncome"])?$data["lossOfBusIncome"]:0));
} else if (
  ((float)($data["lossOfBusIncome"])||0) > 100000 &&
  $data["propertyCoverageOption"] == "cat"
) {
  $data['LossofBusIncomeFP'] = ((float)(isset($data["limitOver100000CoverBusIncomeCat"])?$data["limitOver100000CoverBusIncomeCat"]:0)) * ((float)(isset($data["lossOfBusIncome"])?$data["lossOfBusIncome"]:0));
} else if (
  ((float)($data["lossOfBusIncome"])||0) > 0 &&
  $data["propertyCoverageOption"] == "nonCat"
) {
  $data['LossofBusIncomeFP'] = ((float)(isset($data["limitOver0CoverBusIncomeNonCat"])?$data["limitOver0CoverBusIncomeNonCat"]:0)) * ((float)(isset($data["lossOfBusIncome"])?$data["lossOfBusIncome"]:0));
} else if (
  ((float)($data["lossOfBusIncome"])||0) > 0 &&
  $data["propertyCoverageOption"] == "cat"
) {
  $data['LossofBusIncomeFP'] = ((float)(isset($data["limitOver0CoverBusIncomeCat"])?$data["limitOver0CoverBusIncomeCat"]:0)) * ((float)(isset($data["lossOfBusIncome"])?$data["lossOfBusIncome"]:0));
} else {
  $data['LossofBusIncomeFP'] = 0;
}  
if (
  ((float)(isset($data["dspropreplacementvalue"])?$data["dspropreplacementvalue"]:0)) > 500000 &&
  $data["propertyCoverageOption"] == "nonCat"
) {
  $data['BuildingLimitFP'] = 
  ((float)(isset($data["limitOver500000CoverBuildingNonCat"])?$data["limitOver500000CoverBuildingNonCat"]:0)) * ((float)(isset($data["dspropreplacementvalue"])?$data["dspropreplacementvalue"]:0));
} else if (
  ((float)(isset($data["dspropreplacementvalue"])?$data["dspropreplacementvalue"]:0)) > 500000 &&
  $data["propertyCoverageOption"] == "cat"
) {
  $data['BuildingLimitFP'] = 
  ((float)(isset($data["limitOver500000CoverBuildingCat"])?$data["limitOver500000CoverBuildingCat"]:0)) * ((float)(isset($data["dspropreplacementvalue"])?$data["dspropreplacementvalue"]:0));
} else if (
  ((float)(isset($data["dspropreplacementvalue"])?$data["dspropreplacementvalue"]:0)) > 250000 &&
  $data["propertyCoverageOption"] == "nonCat"
) {
  $data['BuildingLimitFP'] = 
  ((float)(isset($data["limitOver250000CoverBuildingNonCat"])?$data["limitOver250000CoverBuildingNonCat"]:0)) * ((float)(isset($data["dspropreplacementvalue"])?$data["dspropreplacementvalue"]:0));
} else if (
  ((float)(isset($data["dspropreplacementvalue"])?$data["dspropreplacementvalue"]:0)) > 250000 &&
  $data["propertyCoverageOption"] == "cat"
) {
  $data['BuildingLimitFP'] = 
  ((float)(isset($data["limitOver250000CoverBuildingCat"])?$data["limitOver250000CoverBuildingCat"]:0)) * ((float)(isset($data["dspropreplacementvalue"])?$data["dspropreplacementvalue"]:0));
} else if (
  ((float)(isset($data["dspropreplacementvalue"])?$data["dspropreplacementvalue"]:0)) > 100000 &&
  $data["propertyCoverageOption"] == "nonCat"
) {
  $data['BuildingLimitFP'] = 
  ((float)(isset($data["limitOver100000CoverBuildingNonCat"])?$data["limitOver100000CoverBuildingNonCat"]:0)) * ((float)(isset($data["dspropreplacementvalue"])?$data["dspropreplacementvalue"]:0));
} else if (
  ((float)(isset($data["dspropreplacementvalue"])?$data["dspropreplacementvalue"]:0)) > 100000 &&
  $data["propertyCoverageOption"] == "cat"
) {
  $data['BuildingLimitFP'] = 
  ((float)(isset($data["limitOver100000CoverBuildingCat"])?$data["limitOver100000CoverBuildingCat"]:0)) * ((float)(isset($data["dspropreplacementvalue"])?$data["dspropreplacementvalue"]:0));
} else if (
  ((float)(isset($data["dspropreplacementvalue"])?$data["dspropreplacementvalue"]:0)) > 0 &&
  $data["propertyCoverageOption"] == "nonCat"
) {
  $data['BuildingLimitFP'] = 
  ((float)(isset($data["limitOver0CoverBuildingNonCat"])?$data["limitOver0CoverBuildingNonCat"]:0)) * ((float)(isset($data["dspropreplacementvalue"])?$data["dspropreplacementvalue"]:0));
} else if (
  ((float)(isset($data["dspropreplacementvalue"])?$data["dspropreplacementvalue"]:0)) > 0 &&
  $data["propertyCoverageOption"] == "cat"
) {
  $data['BuildingLimitFP'] =  ((float)(isset($data["limitOver0CoverBuildingCat"])?$data["limitOver0CoverBuildingCat"]:0)) * ((float)(isset($data["dspropreplacementvalue"])?$data["dspropreplacementvalue"]:0));
} else {
  $data['BuildingLimitFP'] =  0;
}
$data['liabilityCoveragesTotalPL'] =((float)(isset($data["CoverageFP"])?$data["CoverageFP"]:0))+((float)(isset($data["nonDivingPoolAmount"])?$data["nonDivingPoolAmount"]:0))+((float)(isset($data["MedicalExpenseFP"])?$data["MedicalExpenseFP"]:0))+((float)(isset($data["Non-OwnedAutoFP"])?$data["Non-OwnedAutoFP"]:0))+((float)(isset($data["ExcessLiabilityFP"])?$data["ExcessLiabilityFP"]:0))+((float)(isset($data["TravelAgentEOFP"])?$data["TravelAgentEOFP"]:0));

$data['propertyCoveragesTotalPL'] = ((float)(isset($data["ContentsFP"])?$data["ContentsFP"]:0))+((float)(isset($data["LossofBusIncomeFP"])?$data["LossofBusIncomeFP"]:0))+((float)(isset($data["BuildingLimitFP"])?$data["BuildingLimitFP"]:0));

$data['liabilityPropertyCoveragesTotalPL'] = ((float)(isset($data["liabilityCoveragesTotalPL"])?$data["liabilityCoveragesTotalPL"]:0))+((float)(isset($data["propertyCoveragesTotalPL"])?$data["propertyCoveragesTotalPL"]:0));

$data['liabilityProRataPremium'] = ((float)(isset($data["liabilityCoveragesTotalPL"])?$data["liabilityCoveragesTotalPL"]:0))*((float)(isset($data["proRataPercentage"])?$data["proRataPercentage"]:0));

$selectedDate = date_parse_from_format("Y-m-d",$data['dsbireqpolicyperiod']);
$month = $selectedDate["month"]+1;
$data['proRataPercentage'] = $this->monthDiveStore($data,$month);

$data['propertyProRataPremium'] = ((float)(isset($data["propertyCoveragesTotalPL"])?$data["propertyCoveragesTotalPL"]:0))*((float)(isset($data["proRataPercentage"])?$data["proRataPercentage"]:0));
$data['ProRataPremium'] =((float)(isset($data["liabilityPropertyCoveragesTotalPL"])?$data["liabilityPropertyCoveragesTotalPL"]:0))*((float)(isset($data["proRataPercentage"])?$data["proRataPercentage"]:0));
$data['propertyTaxPL'] =$this->getValue($data,$stateTaxData);
$data['liabilityTaxPL'] = $this->getValueLiability($data,$stateTaxData);
$data['groupTaxPercentage'] = $this->getGroupValue($data,$stateTaxData);

$data['PropTax'] = ((float)(isset($data["propertyCoveragesTotalPL"])?$data["propertyCoveragesTotalPL"]:0))*((float)(isset($data["propertyTaxPL"])?$data["propertyTaxPL"]:0))/100;
$data['LiaTax'] = ((float)(isset($data["liabilityCoveragesTotalPL"])?$data["liabilityCoveragesTotalPL"]:0))*((float)(isset($data["liabilityTaxPL"])?$data["liabilityTaxPL"]:0))/100;

$data['AddILocPremium'] = 0;
for ($i = 0; $i < sizeof($data['additionalLocations']); $i++) {
  $data['AddILocPremium'] += ((float)(isset($data['additionalLocations'][$i]['ALProRataPremium']))?$data['additionalLocations'][$i]['ALProRataPremium']:0);
}
$data['AddILocTax'] = 0;
for ($i = 0; $i < sizeof($data['additionalLocations']); $i++) {
  $data['AddILocTax'] += ((float)(isset($data['additionalLocations'][$i]['ALTotalTax']))?$data['additionalLocations'][$i]['ALTotalTax']:0);
}

$data['groupProfessionalLiability'] = ((float)(isset($data["groupTotalAmount"])?$data["groupTotalAmount"]:0));
$data['padiFeePL'] = $data["padiFee"];
$data['propertyDeductiblesPercentage'] = (isset($data[$data["propertyDeductibles"]])?$data[$data["propertyDeductibles"]]:0);

$data['PropDeductibleCredit'] = ((float)(isset($data["propertyCoveragesTotalPL"])?$data["propertyCoveragesTotalPL"]:0))*((float)(isset($data["propertyDeductiblesPercentage"])?$data["propertyDeductiblesPercentage"]:0))/100;

$data['totalAmount'] = ((float)(isset($data["ProRataPremium"])?$data["ProRataPremium"]:0)) + ((float)(isset($data["PropTax"])?$data["PropTax"]:0)) + ((float)(isset($data["LiaTax"])?$data["LiaTax"]:0)) + ((float)(isset($data["AddILocPremium"])?$data["AddILocPremium"]:0)) + ((float)(isset($data["AddILocTax"])?$data["AddILocTax"]:0)) + ((float)(isset($data["padiFeePL"])?$data["padiFeePL"]:0) )-((float)(isset($data["PropDeductibleCredit"])?$data["PropDeductibleCredit"]:0)) + ((float)(isset($data["PAORFee"])?$data["PAORFee"]:0)) + ((float)(isset($data["groupProfessionalLiability"])?$data["groupProfessionalLiability"]:0));
$data['amount'] = $data['totalAmount'];

}

// Data Cleanup is done here
private function cleanData($data){
    $cleanData = array();
    $this->logger->info("CLEAN DATA");
    $unsetVar = $this->unsetVariables;
    $this->logger->info("UNSET VARIABLES".print_r($unsetVar,true));
    for($i=0;$i< sizeof($unsetVar);$i++){
        $this->logger->info("CLEAN DATA FOR");
        if(isset($data[$unsetVar[$i]])){
            $cleanData[$unsetVar[$i]] = $data[$unsetVar[$i]];
        }
    }
    $this->logger->info("CLEAN DATA END".print_r($data,true));
    return $cleanData;
}

private function monthDiveStore(&$data,$month){
  $proRata = 0;
  switch ($month) {
    case 1:
    $proRata = ((float)(isset($data["proRataFactorsJan"]))?$data["proRataFactorsJan"]:0);
    break;
    case 2:
    $proRata = ((float)(isset($data["proRataFactorsFeb"]))?$data["proRataFactorsFeb"]:0);
    break;
    case 3:
    $proRata = ((float)(isset($data["proRataFactorsMar"]))?$data["proRataFactorsMar"]:0);
    break;
    case 4:
    $proRata = ((float)(isset($data["proRataFactorsApr"]))?$data["proRataFactorsApr"]:0);
    break;
    case 5:
    $proRata = ((float)(isset($data["proRataFactorsMay"]))?$data["proRataFactorsMay"]:0);
    break;
    case 6:
    $proRata = ((float)(isset($data["proRataFactorsJun"]))?$data["proRataFactorsJun"]:0);
    break;
    case 7:
    $proRata = ((float)(isset($data["proRataFactorsJul"]))?$data["proRataFactorsJul"]:0);
    break;
    case 8:
    $proRata = ((float)(isset($data["proRataFactorsAug"]))?$data["proRataFactorsAug"]:0);
    break;
    case 9:
    $proRata = ((float)(isset($data["proRataFactorsSep"]))?$data["proRataFactorsSep"]:0);
    break;
    case 10:
    $proRata = ((float)(isset($data["proRataFactorsOct"]))?$data["proRataFactorsOct"]:0);
    break;
    case 11:
    $proRata = ((float)(isset($data["proRataFactorsNov"]))?$data["proRataFactorsNov"]:0);
    break;
    case 12:
    $proRata = ((float)(isset($data["proRataFactorsDec"]))?$data["proRataFactorsDec"]:0);
    break;
}
return $proRata;
}

private function getValue($data,$states) {
  for ($i = 0; $i < sizeof($states); $i++) {
    if ($states[$i]['state'] == $data["state"] && $states[$i]['coverage'] == "property") 
    {
      return $states[$i]['percentage'];
    }
  }
}

private function getValueLiability($data,$states) {
    for ($i = 0; $i < sizeof($states); $i++) {
    if ($states[$i]['state'] == $data["state"] && $states[$i]['coverage'] == "liability") 
    {
      return $states[$i]['percentage'];
    }
  }
}

function getGroupValue($data,$states) {
  for ($i = 0; $i < sizeof($states); $i++) {
    if ($states[$i]['state'] == $data["state"] && $states[$i]['coverage'] == "group") 
    {
      return $states[$i]['percentage'];
    }
  }
}

private function getLiability($datas,$states) {
      for ($i = 0; $i < sizeof($states); $i++) {
    if ($states[$i]['state'] == $datas['additionalLocationState'] && $states[$i]['coverage'] == "liability") 
    {
      return $states[$i]['percentage'];
    }
  }
}


private function getValueProperty($datas,$states) {
  for ($i = 0; $i < sizeof($states); $i++) {
    if ($states[$i]['state'] == $datas['additionalLocationState'] && $states[$i]['coverage'] == "property") 
    {
      return $states[$i]['percentage'];
    }
  }
}

}
