<?php

use Oxzion\AppDelegate\AbstractAppDelegate;
use Oxzion\Db\Persistence\Persistence;

class SetupEndorsement extends AbstractAppDelegate
{
    public function __construct(){
        parent::__construct();
    }

    public function execute(array $data,Persistence $persistenceService)
    {
        $this->logger->info("Executing Endorsement Setup".json_encode($data));
        $rates = $this->getRates($data,$persistenceService);
        $data = array_merge($data,$rates);
        if(isset($data['careerCoverage'])){
            $data['previous_careerCoverage'] = $data['careerCoverage'];
            $data['careerCoveragePrice'] = 0;
        }
        if(isset($data['scubaFit']) && isset($data[$data['scubaFit']])){
            $data['scubaFitPrice'] = 0;
            $data['previousScubafit'] = $data['scubaFit'];
        }
        if(isset($data['cylinder']) && isset($data[$data['cylinder']])){
            $data['cylinderPrice'] = 0;
            $data['previous_cylinder'] = $data['cylinder'];
        }
        if(isset($data['equipment']) && isset($data[$data['equipment']])){
            $data['equipmentPrice'] = 0;
            $data['previous_equipmentLiability'] = $data['equipment'];
        }

        if(isset($data['excessLiability']) && isset($data[$data['excessLiability']])){
            $data['excessLiabilityPrice'] = 0;
            $data['previous_excessLiability'] = $data['excessLiability'];
        }
        $data['update_date'] = date("Y-m-d");
        if(isset($data['start_date_range'])){
            if(is_string($data['start_date_range'])){
                $startDateRange = json_decode($data['start_date_range'],true);
            } else {
                $startDateRange = $data['start_date_range'];
            }
            $data['startDateRange'] = $startDateRange['label'];
        }
        return $data;
    }
    protected function getRates($data,$persistenceService){
        $select = "Select * FROM premium_rate_card WHERE product ='".$data['product']."' AND start_date <= '".$data['start_date']."' AND is_upgrade = 0 AND end_date >= '".$data['start_date']."'";
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
}
