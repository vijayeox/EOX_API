<?php

use Oxzion\AppDelegate\AbstractDocumentAppDelegate;
use Oxzion\Db\Persistence\Persistence;
use Oxzion\Utils\ArtifactUtils;
use Oxzion\Utils\YMLUtils;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;

use Oxzion\AppDelegate\HttpClientTrait;
use Oxzion\AppDelegate\HTTPMethod;

class GenerateWorkbook extends AbstractDocumentAppDelegate
{

    use HttpClientTrait;
    protected $ExcelTemplateMapperServiceURL = "http://54.161.224.59:5000/api/FileUpload";

    protected $carrierTemplateList = array(
        "harco" => array(
            "type" => "excel",
            "excelFile" => "Harco.xlsm",
            "template" => "harco.yaml",
            "customData" => "harcoExcelData"
        ),
        "dealerGuard_ApplicationOpenLot" => array(
            "type" => "excel",
            "template" => "dealerGuard_ApplicationOpenLot.yaml",
            "excelFile" => "DealerGuard_Application_Open_Lot.xlsx",
            "customData" => "dealerguardOpenLot"
        ),
        "victor_FranchisedAutoDealer" => array(
            "type" => "excel",
            "template" => "victor_FranchisedAutoDealer.yaml",
            "excelFile" => "Victor_FranchisedAutoDealer.xls",
            "customData" => "franchisedAutoDealer"
        ),
        "victor_AutoPhysDamage" => array(
            "type" => "excel",
            "template" => "victor_AutoPhysDamage.yaml",
            "excelFile" => "Victor_AutoPhysDamage.xls",
            "customData" => "victorAutoPhysicalDamage"
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

    public function __construct()
    {
        parent::__construct();
    }

    public function execute(array $data, Persistence $persistenceService)
    {
        $this->logger->info("Executing GenerateWorkbook with data- " . json_encode($data));
        $fieldTypeMappingPDF = include(__DIR__ . "/fieldMappingPDF.php");
        $fileUUID = isset($data['fileId']) ? $data['fileId'] : $data['uuid'];
        $orgUuid = isset($data['orgId']) ? $data['orgId'] : AuthContext::get(AuthConstants::ORG_UUID);
        $fileDestination =  ArtifactUtils::getDocumentFilePath($this->destination, $fileUUID, array('orgUuid' => $orgUuid));
        $this->logger->info("GenerateWorkbook Dest" . json_encode($fileDestination));
        $generatedDocumentsList = array();
        $excelData = array();
        if (isset($data['genericData'])) {
            foreach ($this->checkJSON(
                $data['genericData']
            ) as $customKey => $customValue) {
                if (isset($customValue) && !empty($customValue) && !isset($data[$customKey])) {
                    $data["genericData" . "*" . $customKey] = $customValue;
                }
            }
            unset($data["genericData"]);
        }

        foreach ($this->checkJSON($data['workbooksToBeGenerated']) as  $key => $templateSelected) {
            if ($templateSelected) {
                $selectedTemplate = $this->carrierTemplateList[$key];
                $documentDestination = $fileDestination['absolutePath'] .  $selectedTemplate["template"];
                if ($selectedTemplate["type"] == "excel") {
                    $templateData = array();
                    $fieldMappingExcel = file_get_contents(__DIR__ . "/../template/" . $selectedTemplate["template"]);
                    $fieldMappingExcel = YMLUtils::ymlToArray($fieldMappingExcel);
                    if (isset($data[$selectedTemplate["customData"]])) {
                        foreach ($this->checkJSON(
                            $data[$selectedTemplate["customData"]]
                        ) as $customKey => $customValue) {
                            if (isset($customValue) && !empty($customValue) && !isset($data[$customKey])) {
                                $data[$selectedTemplate["customData"] . "*" . $customKey] = $customValue;
                            }
                        }
                        unset($data[$selectedTemplate["customData"]]);
                    }
                    foreach ($fieldMappingExcel as $fieldConfig) {

                        $formFieldKey = str_contains($fieldConfig["key"], "_") ?
                            explode("_", $fieldConfig["key"])[0]
                            : $fieldConfig["key"];
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
                            } else {
                                $tempFieldConfig['value'] = $userInputValue;
                            }
                            if (!isset($tempFieldConfig['type'])) {
                                $tempFieldConfig['type'] = "";
                            }
                            if (!$tempFieldConfig['value'] == "") {
                                array_push($templateData, [
                                    "pageName" => $tempFieldConfig['pageName'],
                                    "cell" => $tempFieldConfig['cell'],
                                    "key" => $tempFieldConfig['key'],
                                    "type" => $tempFieldConfig['type'],
                                    "value" => $tempFieldConfig['value']
                                ]);
                            }
                        }
                    }

                    array_push(
                        $excelData,
                        array(
                            "filename" => $selectedTemplate["excelFile"],
                            "data" => $templateData
                        )
                    );

                    $response = $this->makeRequest(
                        HTTPMethod::POST,
                        $this->ExcelTemplateMapperServiceURL,
                        [
                            "fileId" => $fileUUID,
                            "appId" => $data['appId'],
                            "orgId" => $orgUuid,
                            "mapping" => [
                                "filename" => $selectedTemplate["excelFile"],
                                "data" => $templateData
                            ]

                        ]
                    );
                    $this->logger->info("Excel Mapper POST Request\n" . $response);
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
                                if (!empty($parentValues[$formChildField]) && $parentValues[$formChildField] == true) {
                                    $pdfData[$fieldNamePDFData] =  $fieldOptions["true"];
                                } else {
                                    $pdfData[$fieldNamePDFData] =  $fieldOptions["false"];
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
                        foreach ($customTemplateData as  $field => $value) {
                            $pdfData[$field] = $value;
                        }
                    }
                    $pdfData = array_filter($pdfData);
                    $this->logger->info("PDF Filling Data \n" . json_encode($pdfData, JSON_PRETTY_PRINT));
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

        // print_r(json_encode($excelData, JSON_PRETTY_PRINT));
        // exit();

        if (count($excelData) > 0) {
            file_put_contents($fileDestination['absolutePath'] . "excelMapperInput.json", json_encode($excelData, JSON_PRETTY_PRINT));
            array_push(
                $generatedDocumentsList,
                array(
                    "fullPath" => $fileDestination['absolutePath'] . "excelMapperInput.json",
                    "file" => $fileDestination['relativePath'] . "excelMapperInput.json",
                    "originalName" => "excelMapperInput.json",
                    "type" => "file/json"
                )
            );
            $data['documentsToBeGenerated'] = count($excelData);
            $data["status"] = "Processing";
        } else {
            $data["status"] = "Generated";
        }
        $data["documents"] = $generatedDocumentsList;
        $this->logger->info("Completed GenerateWorkbook with data- " . json_encode($data, JSON_PRETTY_PRINT));
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
        return date(
            "m-d-Y",
            strtotime($data)
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
                array_push($parsedData, [$value[$childKey] . ""]);
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