<?php
namespace Oxzion\FormEngine\Formio;

use Oxzion\FormEngine\Engine;
use Logger;

class EngineImpl implements Engine
{
    protected $logger;

    public function __construct()
    {
        $this->initLogger();
    }

    protected function initLogger()
    {
        $this->logger = Logger::getLogger(__CLASS__);
    }

    public function parseForm($form, $fieldReference, &$errors)
    {
        $template = json_decode($form, true);
        if (isset($template)) {
            $formTemplate['form']['name'] = isset($template['name'])?$template['name']:null;
            $formTemplate['form']['description'] = $template['title'];
            $formTemplate['form']['template'] = json_encode($template);
            $itemslist = array();
            $fieldList = $this->searchNodes($itemslist, $template['components']);
            $oxFieldArray = array();
            if ($fieldList) {
                foreach ($fieldList as $field) {
                    $generatedField = $this->generateField($field, $fieldReference, $errors);
                    if (isset($generatedField)) {
                        $oxFieldArray[] = $generatedField;
                    }
                    unset($generatedField);
                }
                $formTemplate['fields'] = $oxFieldArray;
            } else {
                return 0;
            }
        } else {
            return 0;
        }
        return $formTemplate;
    }
    protected function searchNodes($itemlist =array(), $items, $parent=null)
    {
        foreach ($items as $item) {
            if (isset($item['input']) && $item['input']==1) {
                if (isset($item['tree']) && $item['tree']) {
                    $temp = $parent;
                    if ((isset($item['type']) == 'datagrid' || isset($item['type']) == 'editgrid' ||
                        isset($item['type']) == 'survey') && (isset($item['components']))) {
                        $temp = $item;
                    }
                    $itemlist[] = $item;
                    $itemlist = $this->searchNodes($itemlist, $item['components'],$temp);
                } else {
                    if (isset($item['type']) && $item['type']!='button') {
                        if ((isset($item['type']) == 'datagrid' || isset($item['type']) == 'editgrid' || isset($item['type']) == 'survey') && (isset($item['components']))) {
                            $this->logger->info("DATA GRID-----".json_encode($item));
                            $itemlist = $this->searchNodes($itemlist, $item['components'], $item);
                        }
                        if (isset($parent)) {
                            $item['parent'] = $parent;
                        }
                        $itemlist[] = $item;
                    }
                }
            } else {
                $flag =0;
                if (isset($item['components'])) {
                    $flag =1;
                    $itemlist = $this->searchNodes($itemlist, $item['components'], $parent);
                }
                if (isset($item['columns']) && is_array($item['columns'])) {
                    $this->logger->info("EngineImpl------columns".json_encode($item['columns']));
                    $itemlist = $this->searchNodes($itemlist, $item['columns'], $parent);
                    $flag =1;
                }
                if (isset($item['rows']) && is_array($item['rows'])) {
                    $itemlist = $this->searchNodes($itemlist, $item['rows'], $parent);
                    $flag =1;
                }
                if (!$flag) {
                    if (isset($item['input']) && $item['input'] && isset($item['type']) && $item['type']!='button') {
                        if (isset($parent)) {
                            $item['parent'] = $parent;
                        }
                        $this->logger->info("Item------4".json_encode($item));
                        $itemlist[] = $item;
                    } else {
                        if (!isset($item['type'])) {
                            if (is_array($item)) {
                                $itemlist = $this->searchNodes($itemlist, $item, $parent);
                            } else {
                                return $itemlist;
                            }
                        } else {
                            // print_r($item);exit;
                        }
                    }
                }
            }
        }
        return $itemlist;
    }
    protected function generateField($field, $fieldReference, &$errors)
    {
        $field = new FormioField($field, $fieldReference);
        if ($error = $field->getError()) {
            foreach ($error as $value) {
                $errors[] = $value;
            }
        }
        return $field->toArray();
    }
}
