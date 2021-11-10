<?php

use Oxzion\Db\Persistence\Persistence;
use Oxzion\AppDelegate\FileTrait;
require_once __DIR__."/PolicyDocument.php";


class StoreOwnershipTransfer extends PolicyDocument
{
    use FileTrait;

    public function __construct(){
        parent::__construct();
        $this->unsetVar = array(
            'fileId',
            'workflowInstanceId',
            'quoteDocuments',
            'proposalCount',
            'csrApprovalAttachments',
            'previous_policy_data',
            'endoAddILocPremium',
            'endoAddILocTax',
            'endoAdditionalLocation',
            'endoBuildingLimitFP',
            'endoContentsFP',
            'endoDiffCoverage',
            'endoEffectiveDate',
            'endoExcessLiabilityFP',
            'endoGroupProfessionalLiability',
            'endoLossofBusIncomeFP',
            'endoMedicalExpenseFp',
            'endoNon-OwnedAutoFP',
            'endoPropDeductibleCredit',
            'endoTotalAddPremium',
            'endoTravelAgentEOFP',
            'endogroupCoverage',
            'endogroupExcessLiability',
            'endorsementExcessLiabilityCoverage',
            'endorsementGroupCoverage',
            'endorsementGroupLiability',
            'endorsementInProgress',
            'endorsementLiabilityCoverage',
            'endorsementLiabilityCoverageOption',
            'endorsementNonDivingPool',
            'endorsementNonOwnedAutoLiabilityPL',
            'endorsementPropertyDeductibles',
            'endorsement_options',
            'mailDocuments',
            'payment_fields',
            'previousPolicyLength',
            'previous_AddILocPremium',
            'previous_AddILocTax',
            'previous_BuildingLimitFP',
            'previous_ContentsFP',
            'previous_CoverageFP',
            'previous_ExcessLiabilityFP',
            'previous_LiaTax',
            'previous_LossofBusIncomeFP',
            'previous_MedicalExpenseFP',
            'previous_Non-OwnedAutoFP',
            'previous_PAORFee',
            'previous_ProRataPremium',
            'previous_PropDeductibleCredit',
            'previous_PropTax',
            'previous_TravelAgentEOFP',
            'previous_additionalInsured',
            'previous_additionalLocations',
            'previous_additionalNamedInsured',
            'previous_address1',
            'previous_address2',
            'previous_annualAggregate',
            'previous_annualAggregateDS',
            'previous_city',
            'previous_combinedSingleLimit',
            'previous_combinedSingleLimitDS',
            'previous_country',
            'previous_doYouWantToApplyForNonOwnerAuto',
            'previous_dsglestmonthretailreceipt',
            'previous_dspropFurniturefixturesandequip',
            'previous_dspropTennantImprv',
            'previous_dspropTotal',
            'previous_dspropinventory',
            'previous_dspropofothers',
            'previous_dspropother',
            'previous_dspropreplacementvalue',
            'previous_dspropsignsattachedordetached',
            'previous_excessLiabilityCoverage',
            'previous_excludedOperation',
            'previous_groupAdditionalInsured',
            'previous_groupAdditionalNamedInsured',
            'previous_groupCoverage',
            'previous_groupCoverageAmount',
            'previous_groupCoverageSelect',
            'previous_groupExcessLiabilityAmount',
            'previous_groupExcessLiabilitySelect',
            'previous_groupPAORfee',
            'previous_groupPL',
            'previous_groupPadiFeeAmount',
            'previous_groupProfessionalLiability',
            'previous_groupProfessionalLiabilitySelect',
            'previous_groupTaxAmount',
            'previous_groupTaxPercentage',
            'previous_groupTotalAmount',
            'previous_liabilityCoverageOption',
            'previous_liabilityCoveragesTotalPL',
            'previous_liabilityProRataPremium',
            'previous_liabilityPropertyCoveragesTotalPL',
            'previous_lossOfBusIncome',
            'previous_lossPayees',
            'previous_medicalPayment',
            'previous_nonDivingPoolAmount',
            'previous_nonOwnedAutoLiabilityPL',
            'previous_padiFeePL',
            'previous_poolLiability',
            'previous_proRataPercentage',
            'previous_propertyCoverageSelect',
            'previous_propertyCoveragesTotalPL',
            'previous_propertyDeductibles',
            'previous_propertyDeductiblesPercentage',
            'previous_propertyProRataPremium',
            'previous_sameasmailingaddress',
            'previous_state',
            'previous_storeExcessLiabilitySelect',
            'previous_totalAddPremium',
            'previous_totalAmount',
            'previous_travelAgentEOReceiptsPL',
            'previous_travelAgentEoPL',
            'previous_travelEnO',
            'previous_zip',
            'email'
        );
    }

    public function execute(array $data,Persistence $persistenceService)
    {
        $this->logger->info("Executing StoreOwnershipTransfer with data- ".json_encode($data));
        if(isset($data['data'])) {
            $fileData = json_decode($data['data'],true);
            unset($data['data']);
            $data = array_merge($fileData,$data);
        }
        $data['assocId'] = $data['fileId'];
    
        foreach($this->unsetVar as $key=>$value){
            if(isset($data[$value])){
                unset($data[$value]);
            }
        }
        if(isset($data['iterations'])){
            if(isset($data['transfer']) && ($data['transfer'] === true || $data['transfer'] === 'true')) {
                $data['iterations'] = $data['iterations'] + 1;
            } else {
                $data['iterations'] = 1;
            }
        }else {
            $data['iterations'] = 1;
        }

        //Flag for change of ownership
        $data['transfer'] = true;

        //Dynamic flag for new account creation
        $data['CreateNewUser'] = true;

        //New account name suffixed with R + iteration
        if(isset($data['username'])) {
            $partsArray = explode("R", $data['username']);
            $data['username'] = $partsArray[0]."R".$data['iterations'];
        } else {
            $data['username'] = null;
        }
        $data['data'] = json_encode($data);

        return $data;
    }
}