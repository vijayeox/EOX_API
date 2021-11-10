<?php

use Oxzion\AppDelegate\AbstractDocumentAppDelegate;
use Oxzion\Db\Persistence\Persistence;
use Oxzion\Utils\ArtifactUtils;
use Oxzion\Utils\YMLUtils;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\AppDelegate\FileTrait;
use Oxzion\AppDelegate\AppDelegateTrait;
use Oxzion\AppDelegate\HttpClientTrait;
use Oxzion\AppDelegate\HTTPMethod;

class GeneratePdfDelegate extends AbstractDocumentAppDelegate
{

    use HttpClientTrait;
    use FileTrait;
    use AppDelegateTrait;

    protected $carrierTemplateList = array(
        "driverEmploymentApplication" => array(
            "type" => "pdf",
            "template" => "driverEmploymentApplication.pdf",
        )
    );

    public function __construct()
    {
        parent::__construct();
    }

    public function execute(array $data, Persistence $persistenceService)
    {
        $this->logger->info("Executing GenerateWorkbook with data- " . json_encode($data, JSON_UNESCAPED_SLASHES));
        // Add logs for created by id and producer name who triggered submission
        $fieldTypeMappingPDF = include(__DIR__ . "/fieldMappingPDF.php");

        $fileUUID = isset($data['fileId']) ? $data['fileId'] : $data['uuid'];
        $orgUuid = isset($data['orgId']) ? $data['orgId'] : AuthContext::get(AuthConstants::ORG_UUID);
        $fileDestination =  ArtifactUtils::getDocumentFilePath($this->destination, $fileUUID, array('orgUuid' => $orgUuid));
        $this->logger->info("GenerateWorkbook Dest" . json_encode($fileDestination, JSON_UNESCAPED_SLASHES));
        $generatedDocumentsList = array();
        $excelData = array();
        $tempData = $data;
                
        $templateSelected = $data['downloadApplication'];
            if ($templateSelected) {
                $key = $templateSelected;
                $selectedTemplate = $this->carrierTemplateList[$key];
                $documentDestination = $fileDestination['absolutePath'] .  $selectedTemplate["template"];
                
                    $pdfData = array();
                    foreach ($fieldTypeMappingPDF[$key]["text"] as  $formField => $pdfField) {
                        isset($data[$formField]) ? $pdfData[$pdfField] = $data[$formField] : null;
                        
                    }

                    $addAnother = $data['addAnother'];
                    $i=0;

                        foreach ($fieldTypeMappingPDF[$key]['addAnother'] as $row  ) {
                            foreach ($row as $formField => $pdfField) {
                                if($formField == 'radioYN'){
                                    
                                }else{
                                    if(isset($addAnother[$i][$formField])){
                                        isset($addAnother[$i][$formField]) ? $pdfData[$pdfField] = $addAnother[$i][$formField] : null;
                                        
                                    }
                                }
                            }

                            foreach ($row['radioYN'] as $formFieldPDF => $pdfFieldData) {
                                if (isset($addAnother[$i][$formFieldPDF])) {
                                    $fieldNamePDFData = $pdfFieldData["fieldname"];
                                    if (isset($pdfFieldData["options"])) {

                                        $fieldOption = $pdfFieldData["options"];
                                        $optionKeys = array_keys($fieldOption);
                                        $pdfData[$fieldNamePDFData] = ($addAnother[$i][$formFieldPDF] == $optionKeys[0]) ?
                                            $fieldOption[array_key_first($fieldOption)] : $fieldOption[array_key_last($fieldOption)];
                                    } else {
                                        $pdfData[$fieldNamePDFData] = $addAnother[$i][$formFieldPDF];
                                    }
                                }
                            }
                            $i++;
                        }

                        
                        $dataGrid1 = $data['dataGrid1'];
                        $j=0;

                        foreach ($fieldTypeMappingPDF[$key]['dataGrid1'] as $row  ) {
                            foreach ($row as $formField => $pdfField) {
                                isset($dataGrid1[$j][$formField]) ? $pdfData[$pdfField] = $dataGrid1[$j][$formField] : null;
                            
                            }
                            $j++;
                        }


                        $dataGrid2 = $data['dataGrid2'];
                        $k=0;

                        foreach ($fieldTypeMappingPDF[$key]['dataGrid2'] as $row  ) {
                            foreach ($row as $formField => $pdfField) {
                                isset($dataGrid2[$k][$formField]) ? $pdfData[$pdfField] = $dataGrid2[$k][$formField] : null;
                                
                            }
                            $k++;
                        }

                        $dataGridDrivingExperience = $data['dataGridDrivingExperience'];
                        $l=0;

                        foreach ($fieldTypeMappingPDF[$key]['dataGridDrivingExperience'] as $row  ) {
                            foreach ($row as $formField => $pdfField) {
                                isset($dataGridDrivingExperience[$l][$formField]) ? $pdfData[$pdfField] = $dataGridDrivingExperience[$l][$formField] : null;
                                
                            }
                            $l++;
                        }

                        $dataGridLicenseInformation = $data['dataGridLicenseInformation'];
                        $m=0;

                        foreach ($fieldTypeMappingPDF[$key]['dataGridLicenseInformation'] as $row  ) {
                            foreach ($row as $formField => $pdfField) {
                                isset($dataGridLicenseInformation[$m][$formField]) ? $pdfData[$pdfField] = $dataGridLicenseInformation[$m][$formField] : null;
                               
                            }
                            $m++;
                        }

                        $previousThreeYearsResidencyGrid = $data['previousThreeYearsResidencyGrid'];
                        $n=0;

                        foreach ($fieldTypeMappingPDF[$key]['previousThreeYearsResidencyGrid'] as $row  ) {
                            foreach ($row as $formField => $pdfField) {
                                isset($previousThreeYearsResidencyGrid[$n][$formField]) ? $pdfData[$pdfField] = $previousThreeYearsResidencyGrid[$n][$formField] : null;
                              
                            }
                            $n++;
                        }


                        $educationQualificationGrid = $data['educationQualificationGrid'];
                        $o=0;

                        foreach ($fieldTypeMappingPDF[$key]['educationQualificationGrid'] as $row  ) {
                            foreach ($row as $formField => $pdfField) {
                                isset($educationQualificationGrid[$o][$formField]) ? $pdfData[$pdfField] = $educationQualificationGrid[$o][$formField] : null;
                                       
                            }
                            $o++;
                        }


                    foreach ($fieldTypeMappingPDF[$key]["radioYN"] as  $formFieldPDF => $pdfFieldData) {
                        if (isset($data[$formFieldPDF])) {
                            $fieldNamePDFData = $pdfFieldData["fieldname"];
                            if (isset($pdfFieldData["options"])) {
                                $fieldOption = $pdfFieldData["options"];
                                $optionKeys = array_keys($fieldOption);
                                $pdfData[$fieldNamePDFData] = ($data[$formFieldPDF] == $optionKeys[0]) ?
                                    $fieldOption[array_key_first($fieldOption)] : $fieldOption[array_key_last($fieldOption)];
                            } else {
                                $pdfData[$fieldNamePDFData] = $data[$formFieldPDF];
                            }
                        }
                    }

                    if (isset($fieldTypeMappingPDF[$key]["checkbox"])) {
                        foreach ($fieldTypeMappingPDF[$key]["checkbox"] as  $formChildField => $fieldProps) {
                            if (isset($data[$fieldProps["parentKey"]]) && !empty($data[$fieldProps["parentKey"]])) {
                                $fieldNamePDFData = $fieldProps["fieldname"];
                                $fieldOptions = $fieldProps["options"];
                                $parentValues = $this->checkJSON($data[$fieldProps["parentKey"]]);
                                if (!empty($parentValues[$formChildField]) && ($parentValues[$formChildField] == 'true')) {
                                    $pdfData[$fieldNamePDFData] =  $fieldOptions["true"];
                                }
                            }
                        }
                    }

                    

                    if (isset($fieldTypeMappingPDF[$key]["date"])) {
                        foreach ($fieldTypeMappingPDF[$key]["date"] as  $formField => $pdfField) {
                            isset($data[$formField]) ?
                                $pdfData[$pdfField] = $this->formatDate($data[$formField]) : null;
                        }
                    }
                    
                    $pdfData = array_filter($pdfData);
                    $this->logger->info("PDF Filling Data \n" . json_encode($pdfData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    $this->documentBuilder->fillPDFForm(
                        $selectedTemplate["template"],
                        $pdfData,
                        $documentDestination
                    );
                    array_push(
                        $generatedDocumentsList,
                        array(
                            "fullPath" => $documentDestination,
                            "file" => $fileDestination['relativePath'] . $selectedTemplate["template"],
                            "originalName" => $selectedTemplate["template"],
                            "type" => "file/pdf"
                        )
                    );
                
            }
        

        $data = $tempData;

        $data["workflowInitiatedBy"] = "management";
        if (isset($data['submittedBy']) && !empty($data['submittedBy'])) {
            if ($data['submittedBy'] == 'accountExecutive') {
                $data["workflowInitiatedBy"] = "accountExecutive";
            }
        }

        date_default_timezone_set('UTC');
        $data['submissionTime'] = (new DateTime)->format('c');
        $data["documents"] = $generatedDocumentsList;

        if (count($excelData) > 0) {
            file_put_contents($fileDestination['absolutePath'] . "excelMapperInput.json", json_encode($excelData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            array_push(
                $generatedDocumentsList,
                array(
                    "fullPath" => $fileDestination['absolutePath'] . "excelMapperInput.json",
                    "file" => $fileDestination['relativePath'] . "excelMapperInput.json",
                    "originalName" => "excelMapperInput.json",
                    "type" => "file/json"
                )
            );
            $data["documents"] = $generatedDocumentsList;
            $data['documentsToBeGenerated'] = count($excelData);
            $data['documentsSelectedCount'] = count($excelData) + count($generatedDocumentsList) - 1;
            $data["status"] = "Processing";
        } else {
            $data["status"] = "Generated";
        }
        $this->logger->info("Completed GenerateWorkbook with data- " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->saveFile($data, $fileUUID);

        if (count($excelData) > 0) {
            $selectQuery = "Select value FROM applicationConfig WHERE type ='excelMapperURL'";
            $ExcelTemplateMapperServiceURL = ($persistenceService->selectQuery($selectQuery))->current()["value"];

            $selectQuery = "Select value FROM applicationConfig WHERE type ='callbackURL'";
            $callbackURL = ($persistenceService->selectQuery($selectQuery))->current()["value"];

            foreach ($excelData as $excelItem) {
                $excelItem["postURL"] = $callbackURL;
                $response = $this->makeRequest(
                    HTTPMethod::POST,
                    $ExcelTemplateMapperServiceURL,
                    $excelItem
                );
                $this->logger->info("Excel Mapper POST Request for " . $excelItem["fileId"] . "\n" . $response);
                sleep(5);
            }
        }

        return $data;
    }

    private function checkJSON($data)
    {
        if (!is_array($data)) {
            $data = json_decode($data, true);
        }
        return $data;
    }

    private function formatDate($data, $fieldConfig = null, $formData = null)
    {
        $date = strpos($data, "T") ? explode("T", $data)[0] : $data;
        return date(
            "m-d-Y",
            strtotime($date)
        );
    }

    private function checkValue($data, $fieldConfig, $formData)
    {
        $childValue = explode("_", $fieldConfig["key"])[1];
        if ($childValue == $data) {
            if (isset($fieldConfig['returnBoolean'])) {
                $trueValue = explode("|", $fieldConfig["returnBoolean"])[0];
                return $trueValue;
            }
            return 'true';
        } else {
            if (isset($fieldConfig['returnBoolean'])) {
                $falseValue = explode("|", $fieldConfig["returnBoolean"])[1];
                return $falseValue;
            }
            return "";
        }
    }

    private function checkInArray($data, $fieldConfig, $formData)
    {
        $childValue = explode("_", $fieldConfig["key"])[1];
        if (in_array($childValue, $this->checkJSON($data))) {
            if (isset($fieldConfig['returnBoolean'])) {
                $trueValue = explode("|", $fieldConfig["returnBoolean"])[0];
                return $trueValue;
            }
            return 'true';
        } else {
            if (isset($fieldConfig['returnBoolean'])) {
                $falseValue = explode("|", $fieldConfig["returnBoolean"])[1];
                return $falseValue;
            }
            return "";
        }
    }

    private function pulloutChild($data, $fieldConfig, $formData)
    {
        $data = $this->checkJSON($data);
        $childKey = explode("_", $fieldConfig["key"])[1];

        if (isset($fieldConfig['returnBoolean'])) {
            $trueValue = explode("|", $fieldConfig["returnBoolean"])[0];
            $falseValue = explode("|", $fieldConfig["returnBoolean"])[1];
        }

        if (isset($data[$childKey]) && !empty($data[$childKey])) {
            $value = $data[$childKey];
            $valueType = gettype($data[$childKey]);
            if ($valueType == "boolean") {
                if (isset($fieldConfig['returnBoolean'])) {
                    $value = $value ? $trueValue : $falseValue;
                } else {
                    $value = $value ? "true" : "false";
                }
            } else if ($value == 'true' || $value ==  'yes') {
                if (isset($fieldConfig['returnBoolean'])) {
                    $value = $trueValue;
                } else {
                    $value = 'true';
                }
            } else if ($value == 'false' || $value ==  'no') {
                if (isset($fieldConfig['returnBoolean'])) {
                    $value = $falseValue;
                } else {
                    $value = 'false';
                }
            }
            return $value;
        } else {
            return "";
        }
    }

    private function simpleDatagrid($data, $fieldConfig, $formData)
    {
        if (str_contains($fieldConfig["key"], "_")) {
            $childKey = explode("_", $fieldConfig["key"])[1];
        } else {
            return [];
        }
        if (isset($fieldConfig["skip"]) && str_contains($fieldConfig["skip"], "_")) {
            $rows = explode("_", $fieldConfig["skip"])[0];
            $skip = explode("_", $fieldConfig["skip"])[1];
            $tempSkip = $skip;
        }
        $parsedData = array();
        foreach ($this->checkJSON($data) as  $key => $value) {
            if (isset($rows) && (!$key == 0) && ($key % $rows == 0)) {
                while ($tempSkip > 0) {
                    array_push($parsedData, []);
                    --$tempSkip;
                }
                $tempSkip = $skip;
            }
            if (isset($value[$childKey]) && !empty($value[$childKey])) {
                if (isset($fieldConfig['returnValue'])) {
                    $temp = $value[$childKey] . "";
                    if (isset($fieldConfig['returnValue'][$temp])) {
                        array_push(
                            $parsedData,
                            [$fieldConfig['returnValue'][$temp] . ""]
                        );
                    }
                } else  if (isset($fieldConfig['method2'])) {
                    $temp = $value[$childKey] . "";
                    $processMethod = $fieldConfig["method2"];
                    array_push(
                        $parsedData,
                        [$this->$processMethod($temp, $fieldConfig, $formData)]
                    );
                } else {
                    array_push($parsedData, [$value[$childKey] . ""]);
                }
            } else {
                array_push($parsedData, []);
            }
        }
        return $parsedData;
    }

    private function checkbox_X($data, $fieldConfig, $formData)
    {
        $data = $this->checkJSON($data);
        if (str_contains($fieldConfig["key"], "_")) {
            $childKey = explode("_", $fieldConfig["key"])[1];
            if (isset($data[$childKey]) && !empty($data[$childKey])) {
                $value = $data[$childKey];
            } else {
                return "";
            }
        } else {
            $formKey = $fieldConfig["key"];
            $value = $formData[$formKey];
        }

        $valueType = gettype($value);
        if ($valueType == "boolean") {
            $value = $value ? "X" : "";
        } else {
            $value = trim($value);
            if ($value == 'true' || $value ==  'yes') {
                $value = 'X';
            } else if ($value == 'false' || $value ==  'no') {
                $value = "";
            }
        }
        return $value;
    }
}
