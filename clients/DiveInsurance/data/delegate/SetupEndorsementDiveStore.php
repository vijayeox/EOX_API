<?php

use Oxzion\AppDelegate\AbstractAppDelegate;
use Oxzion\Db\Persistence\Persistence;

class SetupEndorsementDiveStore extends AbstractAppDelegate
{
    public function __construct()
    {
        parent::__construct();
        $this->unsetOptions = array(
            'excessLiabilityLimit',
            'totalLiabilityLimit',
            'LiabilityPremiumCost',
            'ExcessLiabilityPremium',
            'HullPremium',
            'DingyTenderPremium',
            'TrailerPremium',
            'CrewOnBoatPremium',
            'CrewMembersinWaterPremium',
            'PropertySubTotal',
            'PropertySubTotalProRated',
            'LiabilitySubTotal',
            'LiabilitySubTotalProRated',
            'premiumTotalProRated',
            'groupCoverage',
            'csrApproved',
            'quoteRequirement',
            'quote_due_date',
            'groupPadiFee',
            'groupExcessLiability9M',
            'groupExcessLiability4M',
            'groupExcessLiability3M',
            'groupExcessLiability2M',
            'groupExcessLiability1M',
            'groupCoverageMoreThan500000',
            'groupCoverageMoreThan350000',
            'groupCoverageMoreThan250000',
            'groupCoverageMoreThan200000',
            'groupCoverageMoreThan150000',
            'groupCoverageMoreThan100000',
            'groupCoverageMoreThan50000',
            'groupCoverageMoreThan25000',
            'groupCoverageMoreThan0',
            'stateTaxData',
            'SuperiorRisk',
            'DingyLiabilityPremium',
            'ProRataDays',
            'DateEffective',
            'excessLiabilityCoverage9000000',
            'excessLiabilityCoverage4000000',
            'excessLiabilityCoverage3000000',
            'excessLiabilityCoverage2000000',
            'excessLiabilityCoverage1000000',
            'excessLiabilityCoverageDeclined',
            'CrewInBoat',
            'CrewInWater',
            'PassengerPremium',
            'DeductibleGreaterthan24',
            'DeductibleLessthan25',
            'Layup2',
            'Layup1',
            'LayupA',
            'PortRisk',
            'Navigation',
            'NavWaterSurcharge',
            'FL-HISurcharge',
            'boat_age',
            'total',
            'padiFee',
            'groupProfessionalLiabilityPrice',
            'premiumTotalProRated',
            'PropertySubTotalProRated',
            'PropertySubTotal',
            'SuperiorRiskCredit',
            'NavigationCredit',
            'PortRiskCredit',
            'NavWaterSurchargePremium',
            'Age25Surcharge',
            'PropertyBasePremium',
            'hullRate',
            'ProRataFactor',
            'LiabilityPremium1M',
            'primaryLimit',
            'DingyLiability',
            'PassengerPremiumCost',
            'totalLiability',
            'FlHiSurchargePremium',
            'hull_age',
            'layupDeductible',
            'layup_period',
            'hull25000LessThan5',
            'hull25000LessThan11',
            'hull25000LessThan25',
            'hull25000GreaterThan25',
            'hull50000LessThan5',
            'hull50000LessThan11',
            'hull50000LessThan25',
            'hull50000GreaterThan25',
            'hull100000LessThan5',
            'hull100000LessThan11',
            'hull100000LessThan25',
            'hull100000GreaterThan25',
            'hull150000LessThan5',
            'hull150000LessThan11',
            'hull150000LessThan25',
            'hull150000GreaterThan25',
            'hull200000LessThan5',
            'hull200000LessThan11',
            'hull200000LessThan25',
            'hull200000GreaterThan25',
            'hull250000LessThan5',
            'hull250000LessThan11',
            'hull250000LessThan25',
            'hull250000GreaterThan25',
            'hull300000LessThan5',
            'hull300000LessThan11',
            'hull300000LessThan25',
            'hull300000GreaterThan25',
            'hull350000LessThan5',
            'hull350000LessThan11',
            'hull350000LessThan25',
            'hull350000GreaterThan25',
            'hull400000LessThan5',
            'hull400000LessThan11',
            'hull400000LessThan25',
            'hull400000GreaterThan25',
            'hull500000LessThan5',
            'hull500000LessThan11',
            'hull500000LessThan25',
            'hull500000GreaterThan25',
            'hull600000LessThan5',
            'hull600000LessThan11',
            'hull600000LessThan25',
            'hull600000GreaterThan25',
            'groupTotalAmount',
            'groupPAORfee',
            'groupPadiFeeAmount',
            'groupTaxAmount',
            'groupTaxPercentage', 'paymentVerified', 'premiumFinanceSelect', 'finalAmountPayable', 'paymentOptions', 'chequeNumber', 'orderId'
        );
    }

    public function execute(array $data, Persistence $persistenceService)
    {
        $this->logger->info("Executing Endorsement Setup - Dive Store" . print_r($data, true));
        $data['initiatedByUser'] = isset($data['initiatedByUser']) ? $data['initiatedByUser'] : false;
        $data['upgradeStatus'] = true;
        $data['endorsementInProgress'] = isset($data['endorsementInProgress']) ? $data['endorsementInProgress'] : false;
        $data['entity_name'] = $data['product'];
        if ($data['initiatedByUser'] == false) {
            if ($data['endorsementInProgress'] == false) {
                $policy =  array();
                $update_date =  date("Y-m-d");
                $start_date = date($data['start_date']);
                if ($start_date  > $update_date) {
                    $policy['update_date'] = $data['update_date'] = $data['start_date'];
                } else {
                    $policy['update_date'] = $data['update_date'] = $update_date;
                }
                $data['endoEffectiveDate'] = $data['update_date'];
                if ($data['start_date']) {
                    $data['start_date'] = date_format(date_create($data['start_date']), "Y-m-d");
                }
                if ($data['end_date']) {
                    $data['end_date'] = date_format(date_create($data['end_date']), "Y-m-d");
                }
                $policy['previous_country'] = $data['country'];
                $policy['previous_address1'] = $data['address1'];
                if(!empty($data['address2'])) {
                    $policy['previous_address2'] = $data['address2'];
                }
                $policy['previous_city'] = $data['city'];
                $policy['previous_state'] = $data['state'];
                $policy['previous_zip'] = $data['zip'];
                $policy['previous_sameasmailingaddress'] = $data['sameasmailingaddress'];
                if($data['sameasmailingaddress'] === "false" || $data['sameasmailingaddress'] === false) {
                    $policy['previous_physical_country'] =$data['physical_country'];
                    $policy['previous_mailaddress1'] =$data['mailaddress1'];
                    if(!empty($data['mailaddress2'])) {
                        $policy['previous_mailaddress2'] =$data['mailaddress2'];
                    }
                    $policy['previous_physical_city'] =$data['physical_city'];
                    if(!empty($data['physical_state'])) {
                        $policy['previous_physical_state'] =$data['physical_state'];
                    }
                    $policy['previous_physical_zip'] =$data['physical_zip'];
                }
                if ($data['additional_insured_select'] == "addAdditionalInsureds") {
                    foreach ($data['additionalInsured'] as $key => $value) {
                        if (!isset($value['effective_date'])) {
                            $data['additionalInsured'][$key]['effective_date'] = isset($data['start_date']) ? $data['start_date'] : $data['update_date'];
                        }
                        if (!isset($value['existingAddInsured'])){
                            $data['additionalInsured'][$key]['existingAddInsured'] = uniqid();
                        }
                    }
                    $data['previous_additionalInsured'] = $data['additionalInsured'];
                    $policy['previous_additionalInsured'] = $data['additionalInsured'];
                } else {
                    $data['previous_additionalInsured'] = array();
                    $policy['previous_additionalInsured'] = array();
                }
                if ($data['lossPayeesSelect'] == "yes") {
                    foreach ($data['lossPayees'] as $key => $value) {
                        if (!isset($value['existingLossPayee'])){
                            $data['lossPayees'][$key]['existingLossPayee'] = uniqid();
                        }
                    }
                    $data['previous_lossPayees'] = $data['lossPayees'];
                    $policy['previous_lossPayees'] = $data['lossPayees'];
                } else {
                    $data['previous_lossPayees'] = array();
                    $policy['previous_lossPayees'] = array();
                }
                if ($data['additional_named_insureds_option'] == "yes") {
                    foreach ($data['additionalNamedInsured'] as $key => $value) {
                        if (!isset($value['existingNamedInsured'])){
                            $data['additionalNamedInsured'][$key]['existingNamedInsured'] = uniqid();
                        }
                    }
                    $data['previous_additionalNamedInsured'] = $data['additionalNamedInsured'];
                    $policy['previous_additionalNamedInsured'] = $data['additionalNamedInsured'];
                } else {
                    $data['previous_additionalNamedInsured'] = array();
                    $policy['previous_additionalNamedInsured'] = array();
                }
                $data['previous_policy_data'] = isset($data['previous_policy_data']) ? $data['previous_policy_data'] : array();
                if ($data['groupProfessionalLiabilitySelect'] == 'yes') {
                    $policy['previous_groupCoverageAmount'] = isset($data['groupCoverageAmount']) ? $data['groupCoverageAmount'] : 0;
                    $policy['previous_groupExcessLiabilityAmount'] = isset($data['groupExcessLiabilityAmount']) ? $data['groupExcessLiabilityAmount'] : 0;
                    $policy['previous_groupCoverage'] = isset($data['groupCoverage']) ? $data['groupCoverage'] : 0;
                    $policy['previous_groupTaxAmount'] = isset($data['groupTaxAmount']) ? $data['groupTaxAmount'] : 0;
                    $policy['previous_groupPadiFeeAmount'] = isset($data['groupPadiFeeAmount']) ? $data['groupPadiFeeAmount'] : 0;
                    $policy['previous_groupTaxPercentage'] = isset($data['groupTaxPercentage']) ? $data['groupTaxPercentage'] : 0;
                    $policy['previous_groupPAORfee'] = isset($data['groupPAORfee']) ? $data['groupPAORfee'] : 0;
                    $policy['previous_groupProfessionalLiability'] = isset($data['groupProfessionalLiability']) ? $data['groupProfessionalLiability'] : 0;
                    $policy['previous_groupTotalAmount'] = isset($data['groupTotalAmount']) ? $data['groupTotalAmount'] : 0;
                } else {
                    $policy['previous_groupCoverageAmount'] = 0;
                    $policy['previous_groupExcessLiabilityAmount'] = 0;
                    $policy['previous_groupCoverage'] = 0;
                    $policy['previous_groupTaxAmount'] = 0;
                    $policy['previous_groupPadiFeeAmount'] = 0;
                    $policy['previous_groupTaxPercentage'] = 0;
                    $policy['previous_groupPAORfee'] = 0;
                    $policy['previous_groupProfessionalLiability'] = 0;
                    $policy['previous_groupTotalAmount'] = 0;
                }
                $policy['previous_groupCoverageSelect'] = $data['groupCoverageSelect'];
                $policy['previous_groupProfessionalLiabilitySelect'] = $data['groupProfessionalLiabilitySelect'];
                $policy['previous_groupExcessLiabilitySelect'] = $groupExcessLiability = $data['groupExcessLiabilitySelect'];
                $policy['previous_groupProfessionalLiabilitySelect'] = $data['groupProfessionalLiabilitySelect'];
                $policy['previous_groupPL'] =  isset($data['groupPL']) ? $data['groupPL'] : array();
                $policy['previous_groupAdditionalInsured'] = isset($data['groupAdditionalInsured']) ? $data['groupAdditionalInsured'] : array();
                $policy['previous_groupAdditionalNamedInsured'] = isset($data['groupAdditionalNamedInsured']) ? $data['groupAdditionalNamedInsured'] : array();
                $policy['previous_propertyDeductibles'] = $data['propertyDeductibles'];
                $policy['previous_excessLiabilityCoverage'] = $data['excessLiabilityCoverage'];
                $policy['previous_nonDivingPoolAmount'] = $data['nonDivingPoolAmount'];
                $policy['previous_CoverageFP'] = $data['CoverageFP'];
                $policy['previous_proRataPercentage'] = isset($data['proRataPercentage']) ? $data['proRataPercentage'] : 0;
                if ($data['excessLiabilityCoverage'] == 'excessLiabilityCoverage1M') {
                    $policy['previous_combinedSingleLimitDS'] = 2000000;
                    $policy['previous_annualAggregateDS'] = 3000000;
                } else if ($data['excessLiabilityCoverage'] == 'excessLiabilityCoverage2M') {
                    $policy['previous_combinedSingleLimitDS'] = 3000000;
                    $policy['previous_annualAggregateDS'] = 4000000;
                } else if ($data['excessLiabilityCoverage'] == 'excessLiabilityCoverage3M') {
                    $policy['previous_combinedSingleLimitDS'] = 4000000;
                    $policy['previous_annualAggregateDS'] = 5000000;
                } else if ($data['excessLiabilityCoverage'] == 'excessLiabilityCoverage4M') {
                    $policy['previous_combinedSingleLimitDS'] = 5000000;
                    $policy['previous_annualAggregateDS'] = 6000000;
                } else if ($data['excessLiabilityCoverage'] == 'excessLiabilityCoverage9M') {
                    $policy['previous_combinedSingleLimitDS'] = 10000000;
                    $policy['previous_annualAggregateDS'] = 11000000;
                } else {
                    $policy['previous_combinedSingleLimitDS'] = 1000000;
                    $policy['previous_annualAggregateDS'] = 2000000;
                }
                $policy['previous_propertyCoverageSelect'] = $data['propertyCoverageSelect'];
                $policy['previous_dspropreplacementvalue'] = isset($data['dspropreplacementvalue']) ? $data['dspropreplacementvalue'] : 0;
                $policy['previous_lossOfBusIncome'] = isset($data['lossOfBusIncome']) ? $data['lossOfBusIncome'] : 0;
                $policy['previous_dspropTotal'] = isset($data['dspropTotal']) ? $data['dspropTotal'] : 0;
                $policy['previous_lossPayees'] = isset($data['lossPayees']) ? $data['lossPayees'] : array();
                $policy['previous_additionalNamedInsured'] = isset($data['additionalNamedInsured']) ? $data['additionalNamedInsured'] : array();
                $policy['previous_nonOwnedAutoLiabilityPL'] = $data['nonOwnedAutoLiabilityPL'];
                $policy['previous_liabilityCoverageOption'] = $data['liabilityCoverageOption'];
                $policy['previous_liabilityCoveragesTotalPL'] = $data['liabilityCoveragesTotalPL'];
                $policy['previous_ExcessLiabilityFP'] = $data['ExcessLiabilityFP'];
                $policy['previous_propertyCoveragesTotalPL'] = isset($data['propertyCoveragesTotalPL']) ? $data['propertyCoveragesTotalPL'] : 0;
                $policy['previous_liabilityPropertyCoveragesTotalPL'] = $data['liabilityPropertyCoveragesTotalPL'];
                $policy['previous_liabilityProRataPremium'] = isset($data['liabilityProRataPremium']) ? $data['liabilityProRataPremium'] : 0;
                $policy['previous_propertyProRataPremium'] = isset($data['propertyProRataPremium']) ? $data['propertyProRataPremium'] : 0;
                $policy['previous_ProRataPremium'] = $data['ProRataPremium'];
                $policy['previous_PropTax'] = $data['PropTax'];
                $policy['previous_propertyCoverageSelect'] = $data['propertyCoverageSelect'];
                if ($data['propertyCoverageSelect'] == 'yes') {
                    $policy['previous_propertyCoverageSelect'] = $data['propertyCoverageSelect'];
                    $policy['previous_BuildingLimitFP'] = $data['BuildingLimitFP'];
                } else {
                    $policy['previous_BuildingLimitFP'] = 0;
                }
                $policy['previous_LossofBusIncomeFP'] = $data['LossofBusIncomeFP'];
                $policy['previous_Non-OwnedAutoFP'] = $data['Non-OwnedAutoFP'];
                $policy['previous_LiaTax'] = $data['LiaTax'];
                $policy['previous_AddILocPremium'] = $data['AddILocPremium'];
                $policy['previous_AddILocTax'] = $data['AddILocTax'];
                $policy['previous_propertyDeductiblesPercentage'] = $data['propertyDeductiblesPercentage'];
                $policy['previous_travelEnO'] = $data['TravelAgentEOFP'];
                $policy['previous_travelAgentEOReceiptsPL'] = isset($data['travelAgentEOReceiptsPL']) ? $data['travelAgentEOReceiptsPL'] : 0;
                $policy['previous_travelAgentEoPL'] = $data['travelAgentEoPL'];
                $policy['previous_MedicalExpenseFP'] = isset($data['MedicalExpenseFP']) ? $data['MedicalExpenseFP'] : 0;
                $policy['previous_padiFeePL'] = $data['padiFeePL'];
                $policy['previous_TravelAgentEOFP'] = isset($data['TravelAgentEOFP']) ? $data['TravelAgentEOFP'] : 0;
                $policy['previous_medicalPayment'] = $data['medicalPayment'];
                $policy['previous_doYouWantToApplyForNonOwnerAuto'] = $data['doYouWantToApplyForNonOwnerAuto'];
                $policy['previous_storeExcessLiabilitySelect'] = $data['excessLiabilityCoveragePrimarylimit1000000PL'];
                $policy['previous_poolLiability'] = isset($data['poolLiability']) ? $data['poolLiability'] : 0;
                $policy['previous_dspropFurniturefixturesandequip'] = isset($data['dspropFurniturefixturesandequip']) ? $data['dspropFurniturefixturesandequip'] : 0;
                $policy['previous_dspropofothers'] = isset($data['dspropofothers']) ? $data['dspropofothers'] : 0;
                $policy['previous_dspropinventory'] = isset($data['dspropinventory']) ? $data['dspropinventory'] : 0;
                $policy['previous_dspropsignsattachedordetached'] = isset($data['dspropsignsattachedordetached']) ? $data['dspropsignsattachedordetached'] : 0;
                $policy['previous_dspropTennantImprv'] = isset($data['dspropTennantImprv']) ? $data['dspropTennantImprv'] : 0;
                $policy['previous_dspropother'] = isset($data['dspropother']) ? $data['dspropother'] : 0;
                $policy['previous_dspropTotal'] = isset($data['dspropTotal']) ? $data['dspropTotal'] : 0;
                $policy['previous_lossOfBusIncome'] = isset($data['lossOfBusIncome']) ? $data['lossOfBusIncome'] : 0;
                $policy['previous_dspropreplacementvalue'] = isset($data['dspropreplacementvalue']) ? $data['dspropreplacementvalue'] : 0;
                $policy['previous_dsglestmonthretailreceipt'] = isset($data['dsglestmonthretailreceipt']) ? $data['dsglestmonthretailreceipt'] : 0;
                $policy['previous_totalAddPremium'] = isset($data['totalAddPremium']) ? $data['totalAddPremium'] : 0;
                $policy['previous_ContentsFP'] = isset($data['ContentsFP']) ? $data['ContentsFP'] : 0;
                $policy['previous_excludedOperation'] = isset($data['excludedOperation']) ? $data['excludedOperation'] : "";
                if (isset($data['additionalLocations']) && $data['additionalLocationsSelect'] == "yes") {
                    foreach ($data['additionalLocations'] as $key => $value) {
                        $additionalLocations = $data['additionalLocations'][$key];
                        if (!isset($value['existingAddLocation'])){
                            $additionalLocations['existingAddLocation'] = uniqid();
                        }
                        $additionalLocations['previous_ALCoverageFP'] = isset($additionalLocations['ALCoverageFP']) ? $additionalLocations['ALCoverageFP'] : 0;
                        $additionalLocations['previous_ALPoolLiability'] = isset($additionalLocations['ALpoolLiability']) ? $additionalLocations['ALpoolLiability'] : 0;
                        $additionalLocations['previous_ALTravelAgentEOFP'] = isset($additionalLocations['ALTravelAgentEOFP']) ? $additionalLocations['ALTravelAgentEOFP'] : 0;
                        $additionalLocations['previous_ALMedicalExpenseFP'] = isset($additionalLocations['ALMedicalExpenseFP']) ? $additionalLocations['ALMedicalExpenseFP'] : 0;
                        $additionalLocations['previous_ALNonOwnedAutoFP'] = isset($additionalLocations['ALNonOwnedAutoFP']) ? $additionalLocations['ALNonOwnedAutoFP'] : 0;
                        $additionalLocations['previous_ALExcessLiabilityFP'] = isset($additionalLocations['ALExcessLiabilityFP']) ? $additionalLocations['ALExcessLiabilityFP'] : 0;
                        $additionalLocations['previous_ALlakeQuarry'] = isset($additionalLocations['ALlakeQuarry']) ? $additionalLocations['ALlakeQuarry'] : 0;
                        $additionalLocations['previous_ALContentsFP'] = isset($additionalLocations['ALContentsFP']) ? $additionalLocations['ALContentsFP'] : 0;
                        $additionalLocations['previous_ALlakeQuarry'] = isset($additionalLocations['ALlakeQuarry']) ? $additionalLocations['ALlakeQuarry'] : 0;
                        $additionalLocations['previous_ALLossofBusIncomeFP'] = isset($additionalLocations['ALLossofBusIncomeFP']) ? $additionalLocations['ALLossofBusIncomeFP'] : 0;
                        $additionalLocations['ALBuildingReplacementValue'] = isset($additionalLocations['ALBuildingReplacementValue']) ? $additionalLocations['ALBuildingReplacementValue'] : 0;
                        $additionalLocations['previous_ALBuildingReplacementValue'] = isset($additionalLocations['ALBuildingReplacementValue']) ? $additionalLocations['ALBuildingReplacementValue'] : 0;
                        $additionalLocations['previous_additionalLocationPropertyTotal'] = isset($additionalLocations['additionalLocationPropertyTotal']) ? $additionalLocations['additionalLocationPropertyTotal'] : 0;
                        $additionalLocations['previous_ALLossofBusIncome'] = isset($additionalLocations['ALLossofBusIncome']) ? $additionalLocations['ALLossofBusIncome'] : 0;
                        $additionalLocations['previous_ALnonDivingPoolAmount'] = isset($additionalLocations['ALnonDivingPoolAmount']) ? $additionalLocations['ALnonDivingPoolAmount'] : 0;
                        $additionalLocations['previous_ALlakequarrypondContactVicenciaBuckleyforsupplementalformPL'] = isset($additionalLocations['ALlakequarrypondContactVicenciaBuckleyforsupplementalformPL']) ? $additionalLocations['ALlakequarrypondContactVicenciaBuckleyforsupplementalformPL'] : false;
                        $data['additionalLocations'][$key] = $additionalLocations;
                    }
                }
                $policy['previous_additionalLocations'] = isset($data['additionalLocations']) ? $data['additionalLocations'] : array();
                $policy['previous_annualAggregate'] = isset($data['annualAggregate']) ? $data['annualAggregate'] : 2000000;
                $policy['previous_combinedSingleLimit'] = isset($data['combinedSingleLimit']) ? $data['combinedSingleLimit'] : 1000000;
                $policy['previous_PropDeductibleCredit'] = $data['PropDeductibleCredit'];
                if (isset($data['PAORFee'])) {
                    $policy['previous_PAORFee'] = $data['PAORFee'];
                }
                $policy['previous_totalAmount'] = isset($data['totalAmount']) ? $data['totalAmount'] : 0;

                array_push($data['previous_policy_data'], $policy);

                $this->logger->info("Set UP Edorsement Dive Store - END", print_r($data, true));
                $data['endorsementInProgress'] = true;
            }
            $this->getRates($data, $persistenceService);
        }
        return $data;
    }

    private function getRates(&$data, $persistenceService)
    {
        $length = sizeof($data['previous_policy_data']) - 1;
        $policy = $data['previous_policy_data'][$length];
        $endorsementGroupLiability = array();
        $endorsementExcessLiabilityCoverage = array();
        $endorsementLiabilityCoverageOption = array();
        $unsetOptions = $this->unsetOptions;
        for ($i = 0; $i < sizeof($unsetOptions); $i++) {
            if (isset($data[$unsetOptions[$i]])) {
                unset($data[$unsetOptions[$i]]);
            }
        }
        if (isset($policy['previous_excessLiabilityCoverage'])) {
            $selectCoverage = "select rc.* from premium_rate_card rc WHERE product = '" . $data['product'] . "' and is_upgrade = 0 and coverage_category='EXCESS_LIABILITY' and start_date <= '" . $data['update_date'] . "' AND end_date >= '" . $data['update_date'] . "' order by CAST(rc.previous_key as UNSIGNED) DESC";
            $resultCoverage = $persistenceService->selectQuery($selectCoverage);
            while ($resultCoverage->next()) {
                $rate = $resultCoverage->current();
                if (isset($rate['key'])) {
                    if ($rate['key'] == $policy['previous_excessLiabilityCoverage']) {
                        $data['excessLiabilityCoverage'] = $policy['previous_excessLiabilityCoverage'];
                    }
                    $endorsementExcessLiabilityCoverage[$rate['key']] = $rate['coverage'];
                }
                unset($rate);
            }
        }
        if (isset($policy['previous_liabilityCoverageOption'])) {
            $selectCoverage = "Select * FROM premium_rate_card WHERE product ='" . $data['product'] . "' AND is_upgrade = 1 AND previous_key = '" . $policy['previous_liabilityCoverageOption'] . "' AND start_date <= '" . $data['update_date'] . "' AND end_date >= '" . $data['update_date'] . "'";
            $this->logger->info("Executing Endorsement Rate Card Coverage - Dive Store" . $selectCoverage);
            $resultCoverage = $persistenceService->selectQuery($selectCoverage);
            while ($resultCoverage->next()) {
                $rate = $resultCoverage->current();
                if (isset($rate['key'])) {
                    if ($rate['key'] == $policy['previous_liabilityCoverageOption']) {
                        $data['liabilityCoverageOption'] = $policy['previous_liabilityCoverageOption'];
                    }
                    $endorsementLiabilityCoverageOption[$rate['key']] = $rate['coverage'];
                }
                unset($rate);
            }
        }
        $selectGroupCoverage = "select rc.* from premium_rate_card rc WHERE product = '" . $data['product'] . "' and coverage_category='GROUP_COVERAGE' and start_date <= '" . $data['update_date'] . "' AND end_date >= '" . $data['update_date'] . "'";
        $this->logger->info("Executing Endorsement Rate Card Coverage - Group Coverage" . $selectGroupCoverage);
        $resultGroupCoverage = $persistenceService->selectQuery($selectGroupCoverage);
        while ($resultGroupCoverage->next()) {
            $rate = $resultGroupCoverage->current();
            if (isset($rate['key'])) {
                $data[$rate['key']] = $rate['premium'];
            }
        }
        $selectGroupExcessLiability = "select rc.* from premium_rate_card rc WHERE product = '" . $data['product'] . "' and coverage_category='GROUP_EXCESS_LIABILITY' and start_date <= '" . $data['update_date'] . "' AND end_date >= '" . $data['update_date'] . "'";
        $this->logger->info("Executing Endorsement Rate Card Coverage - Group Excess" . $selectGroupExcessLiability);
        $resultGroupExcessLiability = $persistenceService->selectQuery($selectGroupExcessLiability);
        while ($resultGroupExcessLiability->next()) {
            $rate = $resultGroupExcessLiability->current();
            if (isset($rate['key'])) {
                $data[$rate['key']] = $rate['premium'];
            }
        }
        foreach ($policy as $key => $value) {
            if ($key != 'update_date') {
                $data[$key] = $value;
            }
        }
        $data['endorsementExcessLiabilityCoverage'] = $endorsementExcessLiabilityCoverage;
        $data['endorsementLiabilityCoverageOption'] = $endorsementLiabilityCoverageOption;
        $data['initial_combinedSingleLimit'] = $data['previous_policy_data'][0]['previous_combinedSingleLimit'];
        $data['initial_annualAggregate'] = $data['previous_policy_data'][0]['previous_annualAggregate'];
        if (isset($data['groupPL'])) {
            if ($data['groupPL'] != "") {
                foreach ($data['groupPL'] as $key => $value) {
                    if (!isset($value['effectiveDate'])) {
                        $data['groupPL'][$key]['effectiveDate'] = isset($value['start_date']) ? $value['start_date'] : $data['update_date'];
                    } else if ($value['effectiveDate'] == "") {
                        $value['start_date'] = $data['groupPL'][$key]['start_date'] = ($value['start_date'] == 'Invalid Date' || $value['start_date'] == 'Invalid date') ? $data['start_date'] : $value['start_date'];
                        $data['groupPL'][$key]['effectiveDate'] = $value['start_date'];
                        $data['groupPL'][$key]['existingEffectiveDate'] = date_format(date_create($value['start_date']), 'm-d-Y');
                        $data['groupPL'][$key]['newMembereffectiveDate'] = date_format(date_create($value['start_date']), 'm-d-Y');
                    } else if (isset($value['padi']) && $value['padi'] == "") {
                        $data['groupPL'][$key]['effectiveDate'] = $data['update_date'];
                    } else {
                        $value['start_date'] = $data['groupPL'][$key]['start_date'] = ($value['start_date'] == 'Invalid Date' || $value['start_date'] == 'Invalid date') ? $data['start_date'] : $value['start_date'];
                        $data['groupPL'][$key]['effectiveDate'] = $value['start_date'];
                        $data['groupPL'][$key]['existingEffectiveDate'] = date_format(date_create($value['start_date']), 'm-d-Y');
                        $data['groupPL'][$key]['newMembereffectiveDate'] = date_format(date_create($value['start_date']), 'm-d-Y');
                    }
                    if (is_string($value['documentattach'])) {
                        $data['groupPL'][$key]['documentattach'] = json_decode($value['documentattach'], true);
                    }
                    if (isset($data['groupAdditionalInsured'])) {
                        if ($data['groupAdditionalInsured'] != "") {
                            foreach ($data['groupAdditionalInsured'] as $key2 => $value2) {
                                //add here
                                if (!isset($value2['effective_date'])) {
                                    $data['groupAdditionalInsured'][$key2]['effective_date'] = isset($data['start_date']) ? $data['start_date'] : $data['update_date'];
                                }
                            }
                        }
                    }
                    if (isset($value['padi'])) {
                        $select = "Select firstname, MI as initial, lastname,rating FROM padi_data WHERE member_number ='" . $value['padi'] . "'";
                        $result = $persistenceService->selectQuery($select);
                        if ($result->count() > 0) {
                            $response = array();
                            while ($result->next()) {
                                $response[] = $result->current();
                            }
                            if (count($response) > 0) {
                                $response[0]['rating'] = implode(",", array_column($response, 'rating'));
                            }
                            $data['groupPL'][$key]['rating'] = $response[0]['rating'];
                        } else {
                            // $data['groupPL'][$key]['rating'] = $response[0]['rating'];
                        }
                    }
                }
            }
        }

        $data['stateTaxData'] = $this->getStateTaxData($data, $persistenceService);
        if (isset($data['paymentOptions'])) {
            unset($data['paymentOptions']);
        }
        if (isset($data['chequeNumber'])) {
            unset($data['chequeNumber']);
        }
        if (isset($data['chequeConsentFile'])) {
            unset($data['chequeConsentFile']);
        }
        if (isset($data['orderId'])) {
            unset($data['orderId']);
        }
        if (isset($data['transactionId'])) {
            unset($data['transactionId']);
        }
        $data['previous_policy_data'][$length]['update_date'] = $data['update_date'];
    }

    private function getStateTaxData($data, $persistenceService)
    {
        $year = date('Y');
        if ($data['product'] == 'Dive Boat') {
            $selectTax = "Select state, coverage, percentage FROM state_tax WHERE coverage = 'group' and `year` = " . $year;
        } else if ($data['product'] == 'Dive Store' || $data['product'] == 'Group Professional Liability') {
            $selectTax = "Select state, coverage, percentage FROM state_tax WHERE `year` = " . $year;
        }
        $stateTaxResult = $persistenceService->selectQuery($selectTax);

        $stateTaxData = [];
        while ($stateTaxResult->next()) {
            $rate = $stateTaxResult->current();
            array_push($stateTaxData, $rate);
        }
        return $stateTaxData;
    }
}
