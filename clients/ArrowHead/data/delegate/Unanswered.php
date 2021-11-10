<?php

use Oxzion\AppDelegate\AbstractDocumentAppDelegate;
use Oxzion\Db\Persistence\Persistence;
use Oxzion\AppDelegate\FieldTrait;
use Oxzion\AppDelegate\FileTrait;
use Oxzion\Utils\FileUtils;
use Oxzion\Utils\UuidUtil;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;

class Unanswered extends AbstractDocumentAppDelegate
{
    use FieldTrait;
    use FileTrait;

    protected $template;

    public function __construct()
    {
        parent::__construct();
    }

    private function isJson($string) {
        $array = json_decode($string, true);
        if(is_array($array) && json_last_error() == JSON_ERROR_NONE){
            return true;
        }
        return false;
    }

    private function getLabel($fieldList,$name) {
        $label = $name;
        $arr = [];
        foreach ($fieldList as $field) {
            if($name == $field['name']) {
                if($field['type'] == 'hidden' || $field['type'] == 'button' || $field['type'] == 'htmlelement') {
                    continue;
                }
                if($field['type'] == 'file' || $field['type'] == 'document') {
                    return false;
                }
                if($field['template']) {
                    $label = json_decode($field['template'],true);
                    $label = $label['label'];
                }
                else {
                    $label = $field['text'];
                }
            }
        }
        return $label;
    }

    private function getFieldLabel($field) {
        $label = $field['name'];
        if($field['template']) {
            $label = json_decode($field['template'],true);
            $label = $label['label'];
        }
        else {
            $label = $field['text'];
        }
        return $label;
    }

    private function preProcessFields($fieldList) {
        $finalFieldList = [];
        //Array to identify the drop down fields that require further mapping
        //Example: 10000 -> $10000 or 0.85 -> 85%   
        $dropDownMappings = [];
        foreach ($fieldList as $field) {
            $label = $field['name'];
            if( ($field['type'] == 'hidden') || ($field['type'] == 'file') || ($field['type'] == 'document') || ($field['type'] == 'button') || ($field['type'] == 'htmlelement') ) {
                continue;
            }
            elseif($field['type'] == 'select') {
                //Flatten select component
                $finalFieldList[$label] = $this->getFieldLabel($field);
                $options = json_decode($field['options'],true);
                if(isset($options['values']) && !empty($options['values'])) {
                    $dropDownMappings[$label] = true;
                    foreach ($options['values'] as $key => $value) {
                        $finalFieldList[$value['value']] = $value['label'];
                    }
                }
            }
            elseif($field['type'] == 'selectboxes') {
                //Flatten select component
                $finalFieldList[$label] = $this->getFieldLabel($field);
                $template = json_decode($field['template'],true);
                if(isset($template['values']) && !empty($template['values'])) {
                    $dropDownMappings[$label] = true;
                    foreach ($template['values'] as $key => $value) {
                        $finalFieldList[$value['value']] = $value['label'];
                    }
                }
            }

            elseif($field['type'] == 'survey') {
                // Flatten survey component
                $finalFieldList[$label] = $this->getFieldLabel($field);
                $template = json_decode($field['template'],true);
                if(isset($template['questions']) && !empty($template['questions'])) {
                    $dropDownMappings[$label] = true;
                    foreach ($template['questions'] as $key => $value) {
                        $finalFieldList[$value['value']] = $value['label'];
                    }
                }
            }
            else {
                $finalFieldList[$label] = $this->getFieldLabel($field);
            }
        }
        foreach ($finalFieldList as $key => $value) {
            // Remove empty nodes
            if($value == '') {
                unset($finalFieldList[$key]);
            }
        }
        return Array(
            'finalFieldList' => $finalFieldList,
            'dropDownMappings' => $dropDownMappings
        );
    }

    private function getTempLocation($name,$uuid) {
        $dest =  $this->destination.AuthContext::get(AuthConstants::ORG_UUID)."/temp/".$uuid."/".$name;
        $dest = $dest.".pdf";
        $dest = strval($dest);
        $fileCheck = FileUtils::fileExists($dest);
        if($fileCheck) {
            unlink($dest);
        }
        return $dest;
    }

    private function getAllUnansweredAndAnsweredQuestions(&$fileData,&$unansweredQuestions,&$answeredQuestions) {
        foreach ($fileData as $key => &$value) {
            if(empty($value) && $value !== 0) {
                $unansweredQuestions[$key] = null;
            } elseif (is_array($value)) {
                $this->getAllUnansweredAndAnsweredQuestions($value,$unansweredQuestions[$key],$answeredQuestions[$key]);
                //remove empty nodes
                if (!$unansweredQuestions[$key]) {
                    unset($unansweredQuestions[$key]);
                }
                if (!$answeredQuestions[$key]) {
                    unset($answeredQuestions[$key]);
                }
            } elseif($this->isJson($value)){
                $value = json_decode($value,true);
                $this->getAllUnansweredAndAnsweredQuestions($value,$unansweredQuestions[$key],$answeredQuestions[$key]);
                //remove empty nodes
                if (!$unansweredQuestions[$key]) {
                    unset($unansweredQuestions[$key]);
                }
                if (!$answeredQuestions[$key]) {
                    unset($answeredQuestions[$key]);
                }
            } else {
                // Handle 1 and 0 showing up
                if(is_bool($value)) {
                    if($value) {
                        $value = "yes";
                    } else {
                        $value = "no";
                    }
                }
                $answeredQuestions[$key] = $value;
            }
        }
    }

    private function removeRequiredFromUnanswered(&$unansweredQuestions,&$requiredUnansweredQuestions) {
        foreach ($unansweredQuestions as $key => $value) {
            if(isset($requiredUnansweredQuestions[$key])) {
                if(is_array($requiredUnansweredQuestions[$key])) {
                    $this->removeRequiredFromUnanswered($unansweredQuestions[$key],$requiredUnansweredQuestions[$key]);
                } else {
                    unset($unansweredQuestions[$key]);
                }
            }
        }
    }

    private function getUnansweredQuestionPrintReady($finalFieldList,&$unansweredQuestions,$fieldList) {
        foreach ($unansweredQuestions as $key => $value) {
            if(is_array($value)) {
                if(!is_numeric($key)) {
                    $unansweredQuestions[$key]['label'] = isset($finalFieldList[$key]) ? $finalFieldList[$key] : null;
                    if($unansweredQuestions[$key]['label'] == null) {
                        unset($unansweredQuestions[$key]);
                        continue;
                    }
                }
                $this->getUnansweredQuestionPrintReady($finalFieldList,$unansweredQuestions[$key],$fieldList);
            } else {
                if($key != "label") {
                    $unansweredQuestions[$key] = isset($finalFieldList[$key]) ? $finalFieldList[$key] : null;
                }
            }
            if(!$unansweredQuestions[$key]) {
                unset($unansweredQuestions[$key]); //remove node elements
            }
            if(array_key_exists($key, $unansweredQuestions)) {
                //Remove empty arrays with only labels
                $temp = $unansweredQuestions;
                if(array_key_exists('label', $temp)) {
                    unset($temp['label']);
                    if(empty($temp)) {
                        unset($unansweredQuestions[$key]);
                    }
                }
            }
        }
    }

    private function getAnsweredQuestionsPrintReady($finalFieldList,&$answeredQuestions,$dropDownMappings) {
        $counters = array();
        foreach ($answeredQuestions as $key => $value) {
            if(isset($finalFieldList[$key]) || is_numeric($key)){
                $label = is_numeric($key) ? $key : $finalFieldList[$key];
                if(isset($answeredQuestions[$label]) && !is_numeric($label)) {
                    $counters[$label] = isset($counters[$label]) ? $counters[$label] + 1 : 1;
                    $label = strval($label."-(".$counters[$label].")");
                }
                if(is_array($value)) {
                    $answeredQuestions[$label] = $value;
                    $this->getAnsweredQuestionsPrintReady($finalFieldList,$answeredQuestions[$label],$dropDownMappings);
                } else {
                    $value = is_numeric($value)?(string)$value:$value;
                    if(isset($dropDownMappings[$key]) && isset($finalFieldList[$value])){
                        $answeredQuestions[$label] = $finalFieldList[$value];
                    }
                    else{
                        $answeredQuestions[$label] = $value;
                    }
                    
                }
                if($key != $label) {
                    unset($answeredQuestions[$key]);
                }
            } else {
                unset($answeredQuestions[$key]);
            }
        }
    }

    private function decodeFileData(&$data) {
        foreach ($data as $key => $value) {
            if(is_string($value) && $this->isJson($value)) {
                $data[$key] = json_decode($value,true);
            } if(is_array($value)) {
                $this->decodeFileData($data[$key]);
            }
        }
    }

    private function appendMissingKey($keyList, &$masterList, $conditional = false) {
        if($conditional) {
            foreach ($keyList as $key) {
                if(!array_key_exists($key, $masterList)){
                    $masterList[$key] = null;
                }
            }
        } else {
            foreach ($keyList as $key) {
                $masterList[$key] = null;
            }
        }
    }

    private function removeKeyValueIfExists($keyList, &$masterList) {
        foreach ($keyList as $key) {
            if(array_key_exists($key, $masterList)) {
                unset($masterList[$key]);
            }
        }
    }

    private function fileDataMassaging(&$fileData) {
        //Coinsurance radio button custom logic
        if(isset($fileData['buildings'])) {
            if(isset($fileData['locations'])) {
                $count = count($fileData['locations']);
                if($fileData['locations'][$count - 1]['occupancyType'] == null || $fileData['locations'][$count - 1]['occupancyType'] == '') {
                    unset($fileData['buildings'][$count - 1]);
                }
            }
            foreach ($fileData['buildings'] as $key1 => $value1) {
                if(isset($value1['coinsuranceform'])) {
                    if($value1['coinsuranceform'] == 'yes') {
                        $fileData['buildings'][$key1]['coinsuranceform'] = "Coinsurance";
                    } else {
                        $fileData['buildings'][$key1]['coinsuranceform'] = "Monthly Limit";
                    }
                }
                if(array_key_exists('description', $value1) && ($value1['description'] == null || $value1['description'] == '')) {
                    unset($fileData['buildings'][$key1]['description']);
                }
                if(array_key_exists('classcode', $value1) && ($value1['classcode'] == null || $value1['classcode'] == '')) {
                    unset($fileData['buildings'][$key1]['classcode']);
                }
                if(array_key_exists('ratingbasis', $value1) && ($value1['classcode'] == null || $value1['classcode'] == '')) {
                    unset($fileData['buildings'][$key1]['ratingbasis']);
                }
            }
        }

        if(isset($fileData['epaDetails'])) {
            $keyListEPA = array('epaNumber','pollutionname');
            foreach ($fileData['epaDetails'] as $key => $value) {
                $this->appendMissingKey($keyListEPA,$fileData['epaDetails'][$key],true);
            }
        }
        /*
            Add keys to file data in case its not being passed back.
            Failsafe.
            Usually incases where default value is removed to avoid UI errors
        */
        $keyListCleanData = array('textField1','validationMessage','textField','submissionTime','workFlowId','documentsToBeGenerated','umbrellaWarning','locationNum','textFieldIgnore','managementSubmitApplication');
        $this->removeKeyValueIfExists($keyListCleanData, $fileData);

        $keyList = array('packageTargetPremium','dolTargetPremium','numYearsOfOwnership','numberOfOwners','numberofemployeeshandling','totalemployees','totalassetvalue','planparticipants','numberofTrusteesHandlingPlanAsset','annualsales','garageumUim','employeetwo','employeethree');
        $this->appendMissingKey($keyList,$fileData);

        //Remove location summary from financials
        $keyListFinancials = array('total_newAutos','total_usedAutos','total_fAndI','total_rentalLeasing','total_service','total_bodyShop','total_parts','total_total');
        $keyListMissingFinancials = array('newAutos','usedAutos','fAndI','rentalLeasing','service','bodyShop','parts');
        if(isset($fileData['financialsYTDSales'])) {
            foreach($fileData['financialsYTDSales'] as $key => $value) {
                $this->removeKeyValueIfExists($keyListFinancials,$fileData['financialsYTDSales'][$key]);
                $this->appendMissingKey($keyListMissingFinancials,$fileData['financialsYTDSales'][$key],true);
            }
        }
        if(isset($fileData['financialsYTDGrossProfits'])) {
            foreach($fileData['financialsYTDGrossProfits'] as $key => $value) {
                $this->removeKeyValueIfExists($keyListFinancials,$fileData['financialsYTDGrossProfits'][$key]);
                $this->appendMissingKey($keyListMissingFinancials,$fileData['financialsYTDGrossProfits'][$key],true);
            }
        }
        if(isset($fileData['financialsYTDSalesAnnualized'])) {
            foreach($fileData['financialsYTDSalesAnnualized'] as $key => $value) {
                $this->removeKeyValueIfExists($keyListFinancials,$fileData['financialsYTDSalesAnnualized'][$key]);
            }
        }
        if(isset($fileData['financialsYTDGrossProfitsAnnualized'])) {
            foreach($fileData['financialsYTDGrossProfitsAnnualized'] as $key => $value) {
                $this->removeKeyValueIfExists($keyListFinancials,$fileData['financialsYTDGrossProfitsAnnualized'][$key]);
            }
        }

        //Unnecessary hidden fields in location schedule
        $keyListLocation = array('buildinginterest','d');
        if(isset($fileData['locations'])) {
            foreach ($fileData['locations'] as $key => $value) {
                $this->removeKeyValueIfExists($keyListLocation,$fileData['locations'][$key]);
            }
        }

        $keyListEmployee = array('total_fTEmployeesFurnishedAnAuto','total_pTEmployeesFurnishedAnAuto','total_fTEmployeesWhoAreNotFurnished','total_pTEmployeesWhoAreNotFurnished','total_fTAllOtherEmployees','total_pTAllOtherEmployees','total_nonEmployeesUnderTheAge','total_nonEmployeesYearsOldorolder','total_contractDriversNonEmployees','total_total');
        $keyListMissingEmployee = array('fTEmployeesFurnishedAnAuto','pTEmployeesFurnishedAnAuto','fTEmployeesWhoAreNotFurnished','pTEmployeesWhoAreNotFurnished','fTAllOtherEmployees','pTAllOtherEmployees','nonEmployeesUnderTheAge','nonEmployeesYearsOldorolder','contractDriversNonEmployees');
        if(isset($fileData['employeeList'])) {
            foreach ($fileData['employeeList'] as $key => $value) {
                $this->removeKeyValueIfExists($keyListEmployee,$fileData['employeeList'][$key]);
                $this->appendMissingKey($keyListMissingEmployee,$fileData['employeeList'][$key],true);
            }
        }

        $keyListUnderWriteSurvey = array('isThereADifferentEmployeeHandlingEachAspectOfInventoryOrderingReceivingAuditing','areBankCreditCardStatementsReconciledMonthlyBySomeoneWithNoAccessToCashCreditCardReceiptsDisbursements','areTwo2EmployeesRequiredToSignOffOnAllPurchaseOrders','areMonthlyAccountsReceivableStatementsReviewedByManagementAndMailedToCustomersBySomeoneOtherThanThePersonResponsible');
        if(isset($fileData['underwritesurvey'])) {
            $this->appendMissingKey($keyListUnderWriteSurvey,$fileData['underwritesurvey'],true);
        }

        $keyListStorageTank = array('tankIndex','locationNum');
        if(isset($fileData['storageTanks'])) {
            foreach ($fileData['storageTanks'] as $key => $value) {
                $this->removeKeyValueIfExists($keyListStorageTank,$fileData['storageTanks'][$key]);
            }
        }

        $keyListDOL12MonthAvg = array('new','used','demosFurnishedAutos','loanersShopService','floor_new','floor_used','floor_demosFurnishedAutos','floor_loanersShopService');
        if(isset($fileData['dol_12MonthAvg'])) {
            //Lack of default values
            foreach ($fileData['dol_12MonthAvg'] as $key => $value) {
                $this->appendMissingKey($keyListDOL12MonthAvg,$fileData['dol_12MonthAvg'][$key],true);
            }
        }

        $keyListDOL12MonthAvgTotal = array('acc_new','acc_used','acc_demosFurnishedAutos','acc_loanersShopService');
        if(isset($fileData['dol_12MonthAvgTotal'])) {
            $this->appendMissingKey($keyListDOL12MonthAvgTotal,$fileData['dol_12MonthAvgTotal'],true);
        }

        $keyListDOLInventory = array('maxInventory','maxUnits','maxIndoor','floor_maxInventory','floor_maxUnits','floor_maxIndoor','standardOpenLot','nonStandardOpenLot','inBuilding');
        if(isset($fileData['dol_inventory'])) {
            foreach ($fileData['dol_inventory'] as $key => $value) {
                $this->appendMissingKey($keyListDOLInventory,$fileData['dol_inventory'],true);
            }
        }

        $keyListPollutionLiabilityUnderwritingSurvey = array('applicantHasAnyUndergroundStorageTanks','applicantHasAnyAboveGroundTanks','haveAnyOfTheTanksFailedAnInspection','federalStateReport','withinTheLastFive5YearsHasTheApplicantBeenTheSubjectOfThirdPartyLiabilityClaims','areThereAnyAbandonedTanksOnSite');
        if(isset($fileData['pollutionLiabilityUnderwritingSurvey'])) {
            $this->appendMissingKey($keyListPollutionLiabilityUnderwritingSurvey,$fileData['pollutionLiabilityUnderwritingSurvey'],true);
        }

        $keyListWebsiteContent = array('noWebsite','streamingVideoOrMusicContent','blogMessageBoardsCustomerReviews','informationCreatedByTheApplicant','contentUnderLicenseFromAThirdParty');
        if(isset($fileData['websitecontent'])) {
            $this->appendMissingKey($keyListWebsiteContent,$fileData['websitecontent'],true);
        }
    }

    public function execute(array $data, Persistence $persistenceService)
    {
        // $this->logger->info("Executing Unanswered Delegate with Data" . print_r($data,true));
        $unansweredQuestionArr = $unansweredQuestionFields = array();
        $sequence = $data['sequence'];
        $uuid = UuidUtil::uuid();
        //Get temp location to save file
        $dest = $this->getTempLocation('Unanswered',$uuid);
        $dest2 = $this->getTempLocation('Answered',$uuid);

        //List of all field for dealer policy
        $fieldListArr = $this->getFields($data['appId'],array('entityName' => 'Dealer Policy'));
        $fieldList = isset($fieldListArr['data']) ? $fieldListArr['data'] : array();
        $processedFields = $this->preProcessFields($fieldList);
        $finalFieldList = $processedFields['finalFieldList'];
        $dropDownMappings = $processedFields['dropDownMappings'];
        $fileDataRaw = $this->getFile($data['fileId']);
        $fileData = $fileDataRaw['data'];

        $answeredQuestions = [];
        $unansweredQuestions = [];
        //For file data when it gets encoded and stored in the db
        $this->decodeFileData($fileData);
        $this->fileDataMassaging($fileData);
        //Sequence outer keys
        $temp = array();
        foreach ($sequence as $key => $value) {
            foreach ($fileData as $key1 => $value1) {
                if($value == $key1) {
                    $temp[$value] = $value1;
                }
            }
        }
        $fileData = $temp;

        $this->getAllUnansweredAndAnsweredQuestions($fileData,$unansweredQuestions, $answeredQuestions);

        //Data grids not filled to be added which can't be done for the children as the leaf nodes need to be removed
        foreach ($fileData as $key => $value) {
            if(is_string($value)) {
                $temp = json_decode($value,true);
                if(empty($temp) && is_array($temp)) {
                    $unansweredQuestions[$key] = null;
                }
            }
        }

        if(isset($data['unansweredQuestions'])) {
            $unansweredQuestionFields = array_column($data['unansweredQuestions'], 'api');
            $requiredUnansweredQuestions = [];
            //Required unanswered questions
            foreach (array_column($data['unansweredQuestions'],'api') as $api) {
                $rowData = &$requiredUnansweredQuestions;
                foreach (explode('.', $api) as $api_part) {
                    if (preg_match_all('/^(\w+)\[(\d+)\]$/', $api_part, $api_parts)) {
                        $rowData[$api_parts[1][0]]['label'] = isset($finalFieldList[$api_part]) ? $finalFieldList[$api_part] : $this->getLabel($fieldList,$api_parts[1][0]);
                        $rowData = &$rowData[$api_parts[1][0]][$api_parts[2][0]];
                    } else {
                        $label = isset($finalFieldList[$api_part]) ? $finalFieldList[$api_part] : $this->getLabel($fieldList,$api_part);
                        $rowData[$api_part] = $label;
                    }
                }
            }

            $this->removeRequiredFromUnanswered($unansweredQuestions,$requiredUnansweredQuestions);
            $this->getUnansweredQuestionPrintReady($finalFieldList,$unansweredQuestions,$fieldList);
            $unansweredQuestionsArray = array('unansweredQuestions' => $unansweredQuestions, 'requiredUnansweredQuestions' => $requiredUnansweredQuestions );
            $generatedDocument = $this->documentBuilder->generateDocument('UnansweredQuestions',array('data' => json_encode($unansweredQuestionsArray)),$dest);
            $data['unansweredQuestionsDocument'] = $this->baseUrl."/".$data['appId']."/data/".AuthContext::get(AuthConstants::ORG_UUID)."/temp/".$uuid."/Unanswered.pdf";
        }

        $this->getAnsweredQuestionsPrintReady($finalFieldList,$answeredQuestions,$dropDownMappings);
        $generatedDocument = $this->documentBuilder->generateDocument('AnsweredQuestions',array('data' => json_encode($answeredQuestions)),$dest2);
        $data['answeredQuestionsDocument'] = $this->baseUrl."/".$data['appId']."/data/".AuthContext::get(AuthConstants::ORG_UUID)."/temp/".$uuid."/Answered.pdf";
        return $data;
    }
}
