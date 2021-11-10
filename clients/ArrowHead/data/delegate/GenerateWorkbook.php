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

class GenerateWorkbook extends AbstractDocumentAppDelegate
{

    use HttpClientTrait;
    use FileTrait;
    use AppDelegateTrait;

    protected $carrierTemplateList = array(
        "dealerGuard_ApplicationOpenLot" => array(
            "type" => "excel",
            "template" => "dealerGuard_ApplicationOpenLot.yaml",
            "excelFile" => "DealerGuard_Application_Open_Lot.xlsx",
            "customData" => "dealerguardOpenLot"
        ),
        "victor_AutoPhysDamage" => array(
            "type" => "excel",
            "template" => "victor_AutoPhysDamage.yaml",
            "excelFile" => "Victor_AutoPhysDamage.xls",
            "customData" => "victorAutoPhysicalDamage"
        ),
        "victor_FranchisedAutoDealer" => array(
            "type" => "excel",
            "template" => "victor_FranchisedAutoDealer.yaml",
            "excelFile" => "Victor_FranchisedAutoDealer.xls",
            "customData" => "franchisedAutoDealer"
        ),
        "harco" => array(
            "type" => "excel",
            "excelFile" => "Harco.xlsm",
            "template" => "harco.yaml",
            "customData" => "harcoExcelData"
        ),
        "epli" => array(
            "type" => "pdf",
            "template" => "GAIC - EPLI.pdf",
            "customData" => "gaicEpliPdfData"
        ),
        "rpsCyber" => array(
            "type" => "pdf",
            "template" => "rpsCyber.pdf",
            "customData" => "rpsCyberPDFData"
        )
    );

    const EXCELTEMPLATE = "DOC_and_UM_CarPort_Design.xlsx";

    public function __construct()
    {
        parent::__construct();
    }

    public function execute(array $data, Persistence $persistenceService)
    {
        $this->logger->info("Executing GenerateWorkbook with data- " . json_encode($data, JSON_UNESCAPED_SLASHES));
        // Add logs for created by id and producer name who triggered submission
        $fieldTypeMappingPDF = include(__DIR__ . "/fieldMappingPDF.php");
        $fileId = isset($data['fileId']) ? $data['fileId'] : $data['uuid'];
        $orgId = isset($data['orgId']) ? $data['orgId'] : AuthContext::get(AuthConstants::ORG_UUID);
        $fileDestination =  ArtifactUtils::getDocumentFilePath($this->destination, $fileId, array('orgUuid' => $orgId));
        $this->logger->info("GenerateWorkbook Dest" . json_encode($fileDestination, JSON_UNESCAPED_SLASHES));
        $generatedDocumentsList = array();
        $excelData = array();
        $tempData = $data;
        if (isset($data['genericData']) && !empty($data['genericData'])) {
            foreach ($this->checkJSON(
                $data['genericData']
            ) as $customKey => $customValue) {
                if (isset($customValue) && !empty($customValue) && !isset($data[$customKey])) {
                    $data["genericData" . "*" . $customKey] = $customValue;
                }
            }
            unset($data["genericData"]);
        }
        $template['templateNameWithExt'] = self::EXCELTEMPLATE;
        $template['templatePath'] = __DIR__."/../template";
        $documentDestination = $fileDestination['absolutePath'].self::EXCELTEMPLATE;
        $requiredData = $this->getNecessaryDataForMails($data,$persistenceService);
        $response = $this->documentBuilder->fillExcelTemplate(
            $template['templateNameWithExt'],
            $requiredData,
            $documentDestination,
            array("Sheet1")
        );
        array_push(
            $generatedDocumentsList,
            array(
                "fullPath" => $documentDestination,
                "file" => $fileDestination['relativePath'] . self::EXCELTEMPLATE,
                "originalName" => self::EXCELTEMPLATE,
                "type" => "excel/xlsx"
            )
        );
        foreach ($this->checkJSON($data['workbooksToBeGenerated']) as  $key => $templateSelected) {
            if ($templateSelected) {
                $selectedTemplate = $this->carrierTemplateList[$key];
                $documentDestination = $fileDestination['absolutePath'] .  $selectedTemplate["template"];
                if ($selectedTemplate["type"] == "excel") {
                    $templateData = array();
                    $fieldMappingExcel = file_get_contents(__DIR__ . "/../template/" . $selectedTemplate["template"]);
                    $fieldMappingExcel = YMLUtils::ymlToArray($fieldMappingExcel);
                    if (isset($data[$selectedTemplate["customData"]])) {
                        try {
                            foreach ((array)$this->checkJSON(
                                $data[$selectedTemplate["customData"]]
                            ) as $customKey => $customValue) {
                                if (isset($customValue) && !empty($customValue) && !isset($data[$customKey])) {
                                    $data[$selectedTemplate["customData"] . "*" . $customKey] = $customValue;
                                }
                            }
                        } catch (Exception $e) {
                        }

                        unset($data[$selectedTemplate["customData"]]);
                    }
                    foreach ($fieldMappingExcel as $fieldConfig) {
                        if (isset($fieldConfig["key"])) {
                            $formFieldKey = str_contains($fieldConfig["key"], "_") ?
                                explode("_", $fieldConfig["key"])[0]
                                : $fieldConfig["key"];
                        }
                        if (isset($data[$formFieldKey]) && !empty($data[$formFieldKey]) && $data[$formFieldKey] !== "[]") {
                            $userInputValue = $data[$formFieldKey];
                            $tempFieldConfig = $fieldConfig;
                            if (isset($fieldConfig["method"])) {
                                $processMethod = $fieldConfig["method"];
                                $tempFieldConfig['value'] = $this->$processMethod($userInputValue, $fieldConfig, $data);
                            } else if (isset($fieldConfig['returnBoolean'])) {
                                $trueValue = explode("|", $fieldConfig["returnBoolean"])[0];
                                $falseValue = explode("|", $fieldConfig["returnBoolean"])[1];
                                $valueType = gettype($userInputValue);
                                if ($valueType == "boolean") {
                                    $tempFieldConfig['value'] =  $userInputValue ? $trueValue : $falseValue;
                                } else {
                                    if ($userInputValue == 'true' || $userInputValue ==  'yes') {
                                        $tempFieldConfig['value'] = $trueValue;
                                    } else if ($userInputValue == 'false' || $userInputValue ==  'no') {
                                        $tempFieldConfig['value'] = $falseValue;
                                    } else {
                                        $tempFieldConfig['value'] = $trueValue;
                                    }
                                }
                            } else if (isset($fieldConfig['returnValue'])) {
                                if (!is_string($userInputValue)) {
                                    $userInputValue = "" . $userInputValue;
                                }
                                if (array_key_exists($userInputValue, $fieldConfig['returnValue'])) {
                                    $tempFieldConfig['value'] = $fieldConfig['returnValue'][$userInputValue];
                                } else {
                                    $tempFieldConfig['value'] = $userInputValue;
                                }
                            } else {
                                $tempFieldConfig['value'] = $userInputValue;
                            }
                            if (!isset($tempFieldConfig['type'])) {
                                $tempFieldConfig['type'] = "";
                            }
                            if (!isset($tempFieldConfig['macro'])) {
                                $tempFieldConfig['macro'] = "";
                            }
                            if (!isset($tempFieldConfig['offset'])) {
                                $tempFieldConfig['offset'] = "";
                            }
                            if (!$tempFieldConfig['value'] == "") {
                                array_push($templateData, [
                                    "pageName" => $tempFieldConfig['pageName'],
                                    "cell" => $tempFieldConfig['cell'],
                                    "key" => $tempFieldConfig['key'],
                                    "macro" => $tempFieldConfig['macro'],
                                    "type" => $tempFieldConfig['type'],
                                    "value" => $tempFieldConfig['value'],
                                    "offset" => $tempFieldConfig['offset']
                                ]);
                            }
                        }
                    }

                    array_push(
                        $excelData,
                        [
                            "fileId" => $fileId,
                            "appId" => $data['appId'],
                            "orgId" => $orgId,
                            "mapping" => [
                                "filename" => $selectedTemplate["excelFile"],
                                "data" => $templateData
                            ]

                        ]
                    );
                } else {
                    $pdfData = array();
                    foreach ($fieldTypeMappingPDF[$key]["text"] as  $formField => $pdfField) {
                        isset($data[$formField]) ? $pdfData[$pdfField] = $data[$formField] : null;
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
                    if (isset($fieldTypeMappingPDF[$key]["survey"])) {
                        foreach ($fieldTypeMappingPDF[$key]["survey"] as  $formChildField => $fieldProps) {
                            if (isset($data[$fieldProps["parentKey"]]) && !empty($data[$fieldProps["parentKey"]])) {
                                $fieldNamePDFData = $fieldProps["fieldname"];
                                $fieldOptions = $fieldProps["options"];
                                $parentValues = $this->checkJSON($data[$fieldProps["parentKey"]]);
                                if (!empty($parentValues[$formChildField]) && $parentValues[$formChildField] == 'yes') {
                                    $pdfData[$fieldNamePDFData] =  $fieldOptions["yes"];
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
                    if (isset($selectedTemplate["customData"])) {
                        $customTemplateData = $this->checkJSON($data[$selectedTemplate["customData"]]);
                        try {
                            foreach ((array)$customTemplateData as  $field => $value) {
                                $pdfData[$field] = $value;
                            }
                        } catch (\Throwable $th) {
                            //throw $th;
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
            }
        }

        // print_r(json_encode($excelData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        // exit();

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
                    "created_date" => date('Y-m-d H:i:s'),
                    "originalName" => "excelMapperInput.json",
                    "type" => "file/json"
                )
            );
            $data["documents"] = $generatedDocumentsList;
            $data['documentsToBeGenerated'] = count($excelData);
            $data['documentsSelectedCount'] = count($excelData) + count($generatedDocumentsList) - 1;
            $data["status"] = "Processing";
        } else {
            $sendNotificationMail = false;
            if (isset($data['managementSubmitApplication']) && !empty($data['managementSubmitApplication'])) {
                if ($data['managementSubmitApplication'] == true || $data['managementSubmitApplication'] == "true") {
                    $sendNotificationMail = true;
                }
            }
            if ($sendNotificationMail == true) {
                $tempFileData = $data;
                $tempFileData["fileId"] = $fileId;
                $tempFileData["orgId"] = $orgId;
                $mailResponse = $this->executeDelegate("DispatchMail", $tempFileData);
                $data['mailStatus'] = $mailResponse['Mail1'];
                if(isset($mailResponse['Mail2'])) {
                    $data['mailStatus2'] = $mailResponse['Mail2'];
                }
            }
            $data["status"] = "Generated";
        }
        $this->logger->info("Completed GenerateWorkbook with data- " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->saveFile($data, $fileId);

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
                sleep(7);
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

    private function addSymbolToAllElements(&$data,$symbol,$prepend = true) {
        foreach ($data as $key => $value) {
            if($prepend) {
                $data[$key] = strval($symbol).strval($value);
            } else {
                $data[$key] = strval($value).strval($symbol);
            }
        }
    }

    private function getNecessaryDataForMails($data,$persistenceService)
    {
        $name = $data['producername'];
        $selectQuery = "SELECT * FROM user WHERE name='".$name."'";
        $producerCode = $persistenceService->selectQuery($selectQuery)->current() ? $persistenceService->selectQuery($selectQuery)->current()['producer_code'] : null;
        $requiredData['producerCode'] = $producerCode;
        $requiredData['umim'] = isset($data['garageumUim']) ? $data['garageumUim'] : 0;
        $requiredData['medicalExpense'] = $data['garageLiabilityMedicalExpense'];
        $requiredData['compDeductible'] = 0;
        $requiredData['collDeductible'] = 0;
        $requiredData['driverName'] = $requiredData['umim1'] = $requiredData['medicaL'] = $requiredData['comP'] = $requiredData['colL'] = array();
        if(isset($data['driverOtherCargrid'])) {
            $data['driverOtherCargrid'] = is_array($data['driverOtherCargrid']) ? $data['driverOtherCargrid'] : json_decode($data['driverOtherCargrid'],true);
            $requiredData['compDeductible'] = isset($data['driverOtherCargrid'][0]['compdeductible']) ? $data['driverOtherCargrid'][0]['compdeductible'] : 1000;
            $requiredData['collDeductible'] = isset($data['driverOtherCargrid'][0]['colldeductible']) ? $data['driverOtherCargrid'][0]['colldeductible'] : 1000;
            $requiredData['driverName'] = array_column($data['driverOtherCargrid'], 'nameofdrivertobecovered');
            $requiredData['umim1'] = array_column($data['driverOtherCargrid'], 'driveumuim');
            $this->addSymbolToAllElements($requiredData['umim1'],'$');
            //Last letter of the following 3 variables is uppercased at the end to avoid conflict and incorrect data being added to excel
            $requiredData['medicaL'] = array_column($data['driverOtherCargrid'], 'medicalexpenceautopremises');
            $this->addSymbolToAllElements($requiredData['medicaL'],'$');
            $requiredData['comP'] = array_column($data['driverOtherCargrid'], 'compdeductible');
            $this->addSymbolToAllElements($requiredData['comP'],'$');
            $requiredData['colL'] = array_column($data['driverOtherCargrid'], 'colldeductible');
            $this->addSymbolToAllElements($requiredData['colL'],'$');
        }
        $requiredData['umbrellaCoverageName'] = array();
        $requiredData['umbrellaCoveragePercentage'] = array();
        if(isset($data['UmbrellaCoverageAdditional'])) {
            $data['UmbrellaCoverageAdditional'] = is_array($data['UmbrellaCoverageAdditional']) ? $data['UmbrellaCoverageAdditional'] : json_decode($data['UmbrellaCoverageAdditional'],true);
            $requiredData['umbrellaCoverageName'] = array_column($data['UmbrellaCoverageAdditional'], 'umbrellaCoverageAdditionalName');
            $requiredData['umbrellaCoveragePercentage'] = array_column($data['UmbrellaCoverageAdditional'], 'ownershipPercentageUmbrella');
            $this->addSymbolToAllElements($requiredData['umbrellaCoveragePercentage'],'%',false);
        }
        $this->logger->info("Required data for email- " . print_r($requiredData,true));
        return $requiredData;
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
            if (count(explode("_", $fieldConfig["key"])) > 2) {
                $childKey1 = explode("_", $fieldConfig["key"])[1];
                $childKey2 = explode("_", $fieldConfig["key"])[2];
                if (isset($data[$childKey1][$childKey2]) && !empty($data[$childKey1][$childKey2])) {
                    $value = $data[$childKey1][$childKey2];
                } else {
                    return "";
                }
            } else {
                $childKey = explode("_", $fieldConfig["key"])[1];
                if (isset($data[$childKey]) && !empty($data[$childKey])) {
                    $value = $data[$childKey];
                } else {
                    return "";
                }
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
