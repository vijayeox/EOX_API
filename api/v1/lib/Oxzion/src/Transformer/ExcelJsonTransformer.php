<?php
namespace Oxzion\Transformer;

use Exception;
use Logger;
use Oxzion\EntityNotFoundException;

class ExcelJsonTransformer
{
    public function __construct()
    {
        $this->logger = Logger::getLogger(__CLASS__);
    }

    public function transformData($data,$dataArray) {
        $templateData = array();
        foreach ($data as $fieldConfig) {
            if (isset($fieldConfig["key"])) {
                $formFieldKey = str_contains($fieldConfig["key"], "_") ?
                    explode("_", $fieldConfig["key"])[0]
                    : $fieldConfig["key"];
            }

            if (isset($dataArray[$formFieldKey]) && !empty($dataArray[$formFieldKey]) && $dataArray[$formFieldKey] !== "[]") {
                $userInputValue = $dataArray[$formFieldKey];
                $tempFieldConfig = $fieldConfig;
                if (isset($fieldConfig["method"])) {
                    $processMethod = $fieldConfig["method"];
                    if(!method_exists($this,$processMethod)) {
                        throw new EntityNotFoundException("Method does not exist");
                    }
                    $tempFieldConfig['value'] = $this->$processMethod($userInputValue, $fieldConfig, $dataArray);
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
        return $templateData;
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