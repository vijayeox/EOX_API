<?php
namespace Oxzion\FormEngine\Formio;

use Logger;

class FormioField
{
    protected $data;
    protected $logger;
    protected $error;
    private $propMap = array("LABEL" => "text",
                          "TYPE" => "type",
                          "DATA_TYPE" => "data_type",
                          "ITEMS" => "values",
                          "PLACEHOLDER" => "placeholder",
                          "DEFAULT" => "defaultValue",
                          "REQUIRED" => "required",
                          "MIN" => array("textfield" => "minLength",
                                          "email" => "minLength",
                                          "url" => "minLength",
                                          "password" => "minLength",
                                          "currency" => "min",
                                          "number" => "min",
                                          "textarea" => "minLength",
                                          "phoneNumber" => "minLength",
                                          "datetime" => "minDate",
                                          "time" => "minLength",
                                          "signature" => "minWidth",
                                          "selectboxes" => "minSelectedCount",
                                          "datagrid" => "minLength",
                                          "year" => "minYear"),
                          "MAX" => array("textfield" => "maxLength",
                                          "file" => "fileMaxSize",
                                          "email" => "maxLength",
                                          "url" => "maxLength",
                                          "password" => "maxLength",
                                          "currency" => "max",
                                          "number" => "max",
                                          "textarea" => "maxLength",
                                          "phoneNumber" => "maxLength",
                                          "datetime" => "maxDate",
                                          "time" => "maxLength",
                                          "file" => "fileMaxSize",
                                          "signature" => "maxWidth",
                                          "selectboxes" => "maxSelectedCount",
                                          "datagrid" => "maxLength",
                                          "year" => "maxYear"),
                          'DECIMAL_PLACES' => 'decimalLimit',
                          "PATTERN" => "pattern",
                          "FORMAT" => array("datetime" => "format",
                                            "time" => "format",
                                            "file" => "filePattern",
                                            "default" => "format"),
                          "ERROR_MSG" => "customMessage",
                          "MASK" => "inputMask");
    protected function initLogger()
    {
        $this->logger = Logger::getLogger(__CLASS__);
    }
    public function __construct($field, $fieldReference)
    {
        $this->initLogger();
        $this->error = array();
        $this->data['name'] = $field['key'];
        $this->data['text'] = $field['label'];
        if (isset($field['parent'])) {
            $parent = new FormioField($field['parent'], null);
            $this->data['parent'] = $parent->toArray();
        }
        $this->data['type'] = isset($field['type']) ? $field['type'] : $field['inputType'];

        switch ($this->data['type']) {
            case 'day':
                $props = $this->getDayProps($field);
                $this->data = array_merge($this->data, $props);
                break;
            case 'select':
                $this->data['data_type'] = isset($field['multiple']) && $field['multiple'] ? 'list':'text';
                break;
            case 'checkbox':
                $this->data['data_type'] = 'boolean';
                break;
            case 'number':
            case 'currency':
                $this->data['data_type'] = 'numeric';
                break;
            case 'datetime':
                $this->data['data_type'] = 'datetime';
                break;
            case 'time':
                $this->data['data_type'] = isset($field['multiple']) && $field['multiple'] ? 'list' : 'time';
                break;
            case 'Date':
            case 'date':
                $this->data['data_type'] = 'date';
                break;
            case 'file':
            case 'document':
            case 'signature':
                $this->data['data_type'] = isset($field['multiple']) && $field['multiple'] ? 'list' : 'file';
                break;
            case 'selectboxes':
            case 'tags':
                $this->data['data_type'] = 'list';
                break;
            case 'textarea':
                $this->data['data_type'] = 'longtext';
                break;
            case 'datagrid':
            case 'editgrid':
            case 'survey':
            case 'datamap':
            case 'tree':
                $this->data['data_type'] = 'json';
                break;
            default:
                if (!isset($this->data['data_type'])) {
                    $this->data['data_type'] = 'text';
                }
                break;
        }

        if (isset($field['properties']) && count($field['properties'])) {
            if (isset($field['properties']['data_type'])) {
                $this->data['data_type'] = strtolower($field['properties']['data_type']);
            }
        }

        $this->data['template'] = json_encode($field);
        if (isset($field['data'])) {
            $this->data['options'] = json_encode($field['data']);
        }
        if (isset($field['placeholder'])) {
            $this->data['helpertext'] = $field['placeholder'];
        }
        if (isset($field['validate'])) {
            $this->data['required'] = isset($field['validate']['required'])?$field['validate']['required']:false;
        }
        if ($field['type'] == 'form') {
            $this->data = null;
        }
        if (isset($field['protected']) && ($field['protected']==1 || $field['protected']==true)) {
            $this->data = null;
        }
        if (isset($field['persistent']) && ($field['persistent']==false || $field['persistent']==0  || $field['persistent']=='')) {
            $this->data = null;
        }
        if ($fieldReference && $this->data && !empty($fieldReference)) {
            $this->validateField($this->data, $fieldReference);
        }
    }

    private function getDayProps($field)
    {
        $required = false;
        if ((isset($field['fields']['day']['required']) == 1) ||
                (isset($field['fields']['month']['required']) == 1) ||
                (isset($field['fields']['year']['required']) == 1)) {
            $required = true;
        }
        if ((isset($field['fields']['day']['hide']) &&
                    (($field['fields']['day']['hide']) || ($field['fields']['day']['hide'] == true) ||
                        ($field['fields']['day']['hide'] == 'true'))) ||
                (isset($field['fields']['month']['hide']) &&
                    (($field['fields']['month']['hide'] == 1) || ($field['fields']['month']['hide'] == true) ||
                        ($field['fields']['month']['hide'] == 'true'))) ||
                (isset($field['fields']['year']['hide']) &&
                    (($field['fields']['year']['hide'] == 1) || ($field['fields']['year']['hide'] == true) ||
                        ($field['fields']['year']['hide'] == 'true')))) {
            $dataType = 'text';
        } else {
            $dataType = 'date';
        }
        return array('required' => $required, 'data_type' => $dataType);
    }
    private function validateField($field, $fieldReference)
    {
        if (isset($field['parent']) && ($field['parent']['type'] == 'datagrid' ||
                                        $field['parent']['type'] == 'editgrid' ||
                                        $field['parent']['type'] == 'survey' ||
                                        $field['parent']['type'] == 'tree')) {
            if (!isset($fieldReference[$field['parent']['name']])) {
                $this->error[] = "Field ".$field['name']." - Unexpected Parent Field '".$field['parent']['name']."'";
                return;
            }
            $parent = $fieldReference[$field['parent']['name']];
            if (!isset($parent['FIELDS'][$field['name']])) {
                $this->error[] = "Field ".$field['name']." - Unexpected";
                return;
            }
            $fieldRef = $parent['FIELDS'][$field['name']];
        } elseif (isset($fieldReference[$field['name']])) {
            $fieldRef = $fieldReference[$field['name']];
        } else {
            $this->error[] = "Field ".$field['name']." - Unexpected";
            return;
        }
        $name = $field['name'];
        $fieldTemplate = json_decode($field['template'], true);
        $this->validateAllProperties($name, $field, $fieldTemplate, $fieldRef);
    }
    private function validateAllProperties($name, $field, $fieldTemplate, $fieldRef)
    {
        foreach ($this->propMap as $refKey => $prop) {
            $fieldObj = array_merge($field, $fieldTemplate);
            $type = $fieldObj['type'];
            if (is_string($prop)) {
                $fieldProp = $prop;
            } else {
                $fieldProp = isset($prop[$type]) ? $prop[$type] : (isset($prop["default"]) ? $prop["default"] : "");
            }

            if ($refKey == 'MASK') {
                if ($field['type'] == 'phoneNumber' && !isset($fieldObj[$fieldProp])) {
                    if (isset($fieldObj['inputMasks'])) {
                        $masks = $fieldObj['inputMasks'];
                        $items = isset($fieldRef['ITEMS']) ? $fieldRef['ITEMS'] : array();
                        if (count($items) != count($masks)) {
                            $this->error[] = "Field $name - Number of input Masks provided is ".count($masks)." expected ".count($items);
                        }
                        foreach ($masks as $value) {
                            if (!isset($items[$value['label']])) {
                                $this->error[] = "Field $name - Unexpected inputMask ".$value['label']." with value ".$value['mask'];
                            } else {
                                $this->validateFieldProperty($name, $items[$value['label']], $value, $refKey, 'mask');
                            }
                        }
                        continue;
                    }
                }
            }
            if ($refKey == "FORMAT") {
                if ($field['type'] == 'datetime') {
                    $fieldObj = $fieldObj['widget'];
                }
                if ($field['type'] == 'time') {
                    //This property is not included in the form json so skipping
                    continue;
                }
            } elseif ($refKey == 'REQUIRED' || $refKey == 'MIN' || $refKey == 'MAX' || $refKey == 'PATTERN' || $refKey == 'ERROR_MSG') {
                if ($field['data_type'] == 'datetime') {
                    $fieldObj =  ($refKey == 'MIN' || $refKey == 'MAX') ? $fieldTemplate['datePicker'] :
                                (isset($fieldTemplate['validate']) ? $fieldTemplate['validate'] : array());
                } elseif ($field['type'] == 'day' || ($field['type'] == 'file' && $refKey != 'ERROR_MSG')) {
                    $fieldObj = $fieldObj;
                } elseif ($field['type'] != 'signature'  || ($field['type'] == 'signature' && $refKey != 'MIN' && $refKey != 'MAX')) {
                    $fieldObj = isset($fieldTemplate['validate']) ? $fieldTemplate['validate'] : array();
                }
            } elseif ($refKey == 'ITEMS') {
                if (!isset($fieldRef['ITEMS'])) {
                    continue;
                }
                $items = $fieldRef['ITEMS'];
                $dataValues = null;
                if ($field['type'] == 'day') {
                    foreach ($items as $key => $value) {
                        foreach ($value as $dayProp => $dayVal) {
                            if ($key == 'year' && $dayProp == 'REQUIRED') {
                                //There is not required for year instead has only min and max
                                continue;
                            }
                            $dayRef = $this->propMap[$dayProp];
                            if (is_array($dayRef)) {
                                if (!isset($dayRef[$key])) {
                                    continue;
                                }
                                $dayRef = $dayRef[$key];
                            }
                            $this->validateFieldProperty($name.'-'.$key, $value, $fieldTemplate['fields'][$key], $dayProp, $dayRef);
                        }
                    }
                    continue;
                } elseif ($field['type'] == 'select') {
                    if (!isset($fieldObj['data']['values'])) {
                        $this->error[] = "Field $name - No options provcided expected ".count($items);
                        continue;
                    }
                    $dataValues = $fieldObj['data']['values'];
                } elseif ($field['type'] == 'radio' || $field['type'] == 'selectboxes' || $field['type'] == 'survey') {
                    if (!isset($fieldObj['values'])) {
                        $this->error[] = "Field $name - No options provcided expected ".count($items);
                        continue;
                    }
                    $dataValues = $fieldObj['values'];
                } elseif ($field['type'] == 'phoneNumber') {
                    //This happens only when mask values are provided which is handled separately
                    continue;
                }
                $itemFields = array_keys($items);
                $options = array();
                if (!$dataValues) {
                    $this->error[] = "Field $name - No/Invalid Options given Expected one of -".implode($itemFields, ',');
                    continue;
                }
                foreach ($dataValues as $key => $value) {
                    if (!isset($value['value']) || empty($value['value'])) {
                        $this->error[] = "Field $name - Empty option given expecting one of - ".implode($itemFields, ',');
                        continue;
                    }
                    if (empty($items[$value['value']])) {
                        $this->error[] = "Field $name - Unexpected Option '".$value['value'];
                        continue;
                    }
                    $this->validateFieldProperty($name, $items[$value['value']], $value, 'LABEL', 'label');
                    $options[] = $value['value'];
                }
                if (count($itemFields) > count($options)) {
                    $diff = array_diff($itemFields, $options);
                    $missing = 1;
                } elseif (count($itemFields) < count($options)) {
                    $diff = array_diff($options, $itemFields);
                    $missing = 0;
                }
                if (isset($diff)) {
                    foreach ($diff as $value) {
                        $this->error[] = $missing == 1 ? "Field $name - expected option $value is missing" : "Field $name - unexpected option" ;
                    }
                }
                continue;
            }
            if ($refKey == 'DEFAULT') {
                if (isset($fieldObj[$fieldProp]) && $fieldObj[$fieldProp] == "null") {
                    $fieldObj[$fieldProp] = "";
                }
                if (isset($fieldObj[$fieldProp]) && is_array($fieldObj[$fieldProp])) {
                    if ($fieldObj['type'] == 'datagrid') {
                        continue;
                    }
                    $items = $fieldRef['ITEMS'];
                    foreach ($fieldObj[$fieldProp] as $key => $value) {
                        if (!isset($items[$key])) {
                            continue;
                        }
                        $this->validateFieldProperty($name, $items[$key], $fieldObj[$fieldProp], 'DEFAULT', $key);
                    }
                    continue;
                }
            }

            $this->validateFieldProperty($name, $fieldRef, $fieldObj, $refKey, $fieldProp);
        }
    }
    private function validateFieldProperty($fieldName, $fieldRef, $field, $refKey, $fieldKey)
    {
        $fieldProp = $this->checkPropertyValue($field, $fieldKey);
        $reference = $this->checkPropertyValue($fieldRef, $refKey);

        if ($reference != $fieldProp) {
            $this->error[] = $this->getErrorMessage($fieldKey, $fieldName, $reference, $fieldProp);
        }
    }
    private function checkPropertyValue($obj, $key)
    {
        return isset($obj[$key]) ? trim($obj[$key]) : "";
    }
    private function getErrorMessage($property, $fieldName, $reference, $fieldProp)
    {
        return "Field $fieldName - Value of property '$property' is '$fieldProp' expected '$reference'";
    }
    public function toArray()
    {
        return $this->data;
    }

    public function getError()
    {
        return $this->error;
    }
}
