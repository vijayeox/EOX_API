<?php

use Oxzion\AppDelegate\AbstractAppDelegate;
use Oxzion\Db\Persistence\Persistence;
use Oxzion\Utils\UuidUtil;

class SaveDriverUnit extends AbstractAppDelegate
{
    public function __construct()
    {
        parent::__construct();
    }

    public function execute(array $data, Persistence $persistenceService)
    {


        
            $data['driverDataGrid'] = !is_array($data['driverDataGrid'])?json_decode($data['driverDataGrid'],true):$data['driverDataGrid'];
            $data['unitDataGrid'] = !is_array($data['unitDataGrid'])?json_decode($data['unitDataGrid'],true):$data['unitDataGrid'];
            
           

            if($this->isDataValid($data))
            {
                $this->cleanDriverUnits($data,$persistenceService);
                foreach($data['driverDataGrid'] as $driverIndex => $driverDetails)
                {
                    $driverDetails = $this->processDataGrid($driverDetails);
                    $driverParams = [];
                    $uuid = UuidUtil::uuid();
                    $driverParams['uuid'] = $uuid;
                    $driverParams['firstName'] = $driverDetails['driverFirstName'];
                    $driverParams['middleName'] = $driverDetails['driverMiddleName'];
                    $driverParams['lastName'] = $driverDetails['driverLastName'];
                    $driverParams['dateOfBirth'] = explode("T",$driverDetails['driverDateofBirth'])[0];
                    $driverParams['ssn'] = $driverDetails['driverSsn'];
                    $driverParams['licenseNum'] = $driverDetails['driverLicense'];
                    $driverParams['hasExperience'] = $driverDetails['doesthedriverhave2YearsofcommercialdrivingexperienceinNorthAmerica'];
                    $driverParams['driverType'] = $driverDetails['pleaseindicatetypeofdriver'];
                    $driverParams['paidByOption'] = $driverDetails['pleaseselectthepaidbyoption'];
                    
                    $selectQuery = "SELECT * FROM `driver` WHERE ssn = :ssn";
                    $resultArr = $persistenceService->selectQuery($selectQuery,[
                        "ssn"=>$driverParams['ssn']
                    ],true);
                    if(count($resultArr) == 0){
                        $columns = "(`uuid`, `first_name`,`middle_name`,`last_name`,`date_of_birth`,`ssn`,`license_num`,`has_experience`,`driver_type`,`paid_by_option`) ";
                        $values = "VALUES (:uuid,:firstName,:middleName,:lastName,:dateOfBirth,:ssn,:licenseNum,:hasExperience,:driverType,:paidByOption)";
                        $insertQuery = "INSERT INTO driver ".$columns.$values;
                        $driverSelect = $persistenceService->insertQuery($insertQuery, $driverParams);   
                        $data['driverDataGrid'][$driverIndex]['driverId'] = $driverSelect->getGeneratedValue();
    
                    }
                    else {
                        unset($driverParams['uuid']);
                        $setStatement = "`first_name`=:firstName, `middle_name` = :middleName, `last_name`=:lastName, `date_of_birth`=:dateOfBirth,`license_num`=:licenseNum,`has_experience`=:hasExperience,`driver_type`=:driverType,`paid_by_option`=:paidByOption ";
                        $whereStatement = "WHERE `ssn` = :ssn";
                        $updateQuery = "UPDATE `driver` SET ".$setStatement.$whereStatement;
                        $persistenceService->updateQuery($updateQuery, $driverParams); 
                        $data['driverDataGrid'][$driverIndex]['driverId'] = $resultArr[0]['id'];
    
                    }
                    
                }
        
                foreach($data['unitDataGrid'] as $unitIndex => $unitDetails)
                {
                    $unitDetails = $this->processDataGrid($unitDetails);
                    $unitParams = [];
                    $unitUuid = UuidUtil::uuid();
                    $unitParams['uuid'] = $unitUuid;
                    $unitParams['make'] = $unitDetails['unitMake'];
                    $unitParams['year'] = $unitDetails['unitYear'];
                    $unitParams['model'] = $unitDetails['unitModel'];
                    $unitParams['vin'] = $unitDetails['unitVin'];
                    $unitParams['garagingCity'] = $unitDetails['unitGaragingCity'];
                    $unitParams['garagingAddress'] = $unitDetails['addresswheretheunitisgaraged'];
                    $unitParams['garagingState'] = $unitDetails['unitGaragingState'];
                    $unitParams['zipCode'] = $unitDetails['zipCode'];
                    $unitParams['registeredOwner'] = $unitDetails['registeredownerfullname'];
                    $unitParams['isLeased'] = $unitDetails['isthisunitleasedorfinanced'];
                    $unitParams['leasedDetails'] = $unitParams['isLeased'] == 1?$unitDetails['leasedorFinancedDetailsDataGrid']:null;
                    $unitParams['hasInsured'] = $unitDetails['doYouWantToAddAdditionalInsured'];
                    $unitParams['insuredDetails'] = $unitParams['hasInsured'] == 1 ?$unitDetails['additionalInsuredDetailsDataGrid']:null;
                    $unitParams['hasDriver'] = $unitDetails['doesTheUnitHaveADriver'];
    
                    $selectQuery = "SELECT * FROM `unit` WHERE vin = :vin";
                    $resultArr = $persistenceService->selectQuery($selectQuery,[
                        "vin"=>$unitParams['vin']
                    ],true);
    
                    if(count($resultArr) == 0)
                    {
                        $columns = "(`uuid`, `make`,`year`,`model`,`vin`,`garaging_city`,`garaging_address`,`garaging_state`,`zip_code`,`registered_owner`,`is_leased`,`leased_details`,`has_insured`,`insured_details`,`has_driver`) ";
                        $values = "VALUES (:uuid,:make,:year,:model,:vin,:garagingCity,:garagingAddress,:garagingState,:zipCode,:registeredOwner,:isLeased,:leasedDetails,:hasInsured,:insuredDetails,:hasDriver)";
                        $insertQuery = "INSERT INTO unit ".$columns.$values;
                        $unitSelect = $persistenceService->insertQuery($insertQuery, $unitParams);   
                        $unitId = $unitSelect->getGeneratedValue();
                    }
                    else 
                    {
                        unset($unitParams['uuid']);
                        $setStatement = "`make`=:make, `year` = :year, `model`=:model,`garaging_city`=:garagingCity,`garaging_address`=:garagingAddress,`garaging_state`=:garagingState,`zip_code`=:zipCode,`registered_owner`=:registeredOwner,`is_leased`=:isLeased,`leased_details`=:leasedDetails,`has_insured`=:hasInsured,`insured_details`=:insuredDetails,`has_driver`=:hasDriver ";
                        $whereStatement = "WHERE `vin` = :vin";
                        $updateQuery = "UPDATE `unit` SET ".$setStatement.$whereStatement;
                        $persistenceService->updateQuery($updateQuery, $unitParams); 
                        $unitId = $resultArr[0]['id'];
                    }
                    $unitDetails['driverSelect'] = is_array($unitDetails['driverSelect'])?$unitDetails['driverSelect']:json_decode($unitDetails['driverSelect'],true);
                    if(count($unitDetails['driverSelect'])>0)
                    {
                        foreach($unitDetails['driverSelect'] as $driverIndex)
                        {
                            $driverId = $data['driverDataGrid'][$driverIndex-1]['driverId'];
                            $selectQuery = "SELECT * FROM `driver_unit` WHERE unit_id = :unitId AND driver_id = :driverId";
                            $resultArr = $persistenceService->selectQuery($selectQuery,[
                                "unitId"=> $unitId,
                                "driverId" => $driverId
                            ],true);

                            if(count($resultArr) == 0)
                            {
                                $insertQuery = "INSERT INTO `driver_unit` (`unit_id`, `driver_id`) VALUES (:unitId,:driverId)";
                                $persistenceService->insertQuery($insertQuery, [
                                    "unitId"=> $unitId,
                                    "driverId" => $driverId
                                ]);   

                            }
                        }
                    }
                    
                }


            }

    }

    private function processDataGrid(array $dataGrid)
    {
        foreach($dataGrid as $key=>$value)
        {
            if($dataGrid[$key] == "")
            {
                $dataGrid[$key] = null;
            }
            else if(is_array($value))
            {
                $dataGrid[$key] = json_encode($value);
            }
            else if($dataGrid[$key] == "yes")
            {
                $dataGrid[$key] = 1;
            }
            else if($dataGrid[$key] == "no")
            {
                $dataGrid[$key] = 0;
            }
        }
        return $dataGrid;
    }

    private function isDriverValid(array $driverDetails)
    {
        if($driverDetails['driverFirstName'] != "" && $driverDetails['driverLastName'] != "" && $driverDetails['driverSsn'] != "" && $driverDetails['driverLicense'] != "")
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    private function isUnitValid(array $unitDetails)
    {
        if($unitDetails['unitMake'] != "" && $unitDetails['unitModel'] != "" && $unitDetails['unitVin'] != "")
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    private function isDataValid(array $data)
    {
        if(isset($data['driverDataGrid']) && isset($data['unitDataGrid']))
        {
            foreach($data['driverDataGrid'] as $driverDetails)
            {
                if(!$this->isDriverValid($driverDetails))
                {
                    return false;
                }
            }
     
            foreach($data['unitDataGrid'] as $unitDetails)
            {
                if(!$this->isUnitValid($unitDetails))
                {
                    return false;
                }
            }
            
            return true;
        }
        return false;
    }

    private function cleanDriverUnits(array $data,Persistence $persistenceService)
    {
        $select = "SELECT * FROM `unit`";
        $resultArr = $persistenceService->selectQuery($select,[],true);
        if(count($resultArr) > 0)
        {
            //Check if user has removed any Drivers/Units while resubmitting the form and make appropriate changes in the database
            $this->cleanUnits($resultArr,$data,$persistenceService);
        }

        $select = "SELECT * FROM `driver`";
        $resultArr = $persistenceService->selectQuery($select,[],true);

        if(count($resultArr) > 0)
        {
            $this->cleanDrivers($resultArr,$data,$persistenceService);
        }


    }

    private function cleanUnits(array $unitsArray,array $data,Persistence $persistenceService)
    {
        foreach ($unitsArray as $existingUnit)
        {
            $doesUnitExist = false;

            //Check if unit exists in the newly submitted form
            foreach($data['unitDataGrid'] as $newUnit)
            {
                //If unit exists check if the any drivers have been removed and make appropriate changes
                if($existingUnit['vin'] == $newUnit['unitVin'])
                {
                    $doesUnitExist = true;
                    $select = "SELECT * FROM `driver_unit` as `du` INNER JOIN `driver` as `d` on `du`.driver_id = `d`.id  WHERE `du`.unit_id = :unitId";
                    $driverUnitDetails = $persistenceService->selectQuery($select,[
                        'unitId' => $existingUnit['id']
                    ],true);

                    foreach($driverUnitDetails as $existingDriverForUnit)
                    {
                        $doesDriverExist = false;
                        $driverSsn = $existingDriverForUnit['ssn'];
                        $newUnit['driverSelect'] = is_array($newUnit['driverSelect'])?$newUnit['driverSelect']:json_decode($newUnit['driverSelect'],true);
                        foreach($newUnit['driverSelect'] as $driverIndex)
                        {
                            if($driverSsn == $data['driverDataGrid'][$driverIndex-1]['driverSsn'])
                            {
                                $doesDriverExist = true;
                                break;
                            }
                        }
                        if(!$doesDriverExist)
                        {
                            $delete = "DELETE FROM `driver_unit` WHERE driver_id = :driverId AND unit_id=:unitId";
                            $persistenceService->deleteQuery($delete,[
                                'driverId' => $existingDriverForUnit['driver_id'],
                                'unitId' => $existingUnit['id']
                            ]);
                        }
                    }

                    break;

                }

            }

            //If unit doesn not exist then delete it from the database
            if(!$doesUnitExist)
            {
                $delete = "DELETE FROM `driver_unit` where unit_id = :unitId";
                $persistenceService->deleteQuery($delete,[
                    'unitId' => $existingUnit['id']
                ]);

                $delete = "DELETE FROM `unit` where id=:unitId";
                $persistenceService->deleteQuery($delete,[
                    'unitId' => $existingUnit['id']
                ]);
            }
        }
    }

    private function cleanDrivers(array $driverArray, array $data, Persistence $persistenceService)
    {
        foreach($driverArray as $existingDriver)
        {
            $doesDriverExist = false;
            foreach($data['driverDataGrid'] as $newDriver)
            {
                if($existingDriver['ssn'] == $newDriver['driverSsn'])
                {
                    $doesDriverExist = true;
                    break;
                }
            }

            if(!$doesDriverExist)
            {
                $delete = "DELETE FROM `driver` WHERE id=:driverId";
                $persistenceService->deleteQuery($delete,[
                    "driverId" => $existingDriver['id']
                ]);

            }
        }
    }

}