<?php

namespace Oxzion\Analytics;

use Oxzion\Service\TemplateService;
use Logger;

abstract class AnalyticsAbstract implements AnalyticsEngine
{
    protected $logger;
    protected $config; //This config is config for connection not the app config.
    protected $appConfig;
    protected $appDBAdapter;

    public function __construct($appDBAdapter, $appConfig)
    {
        $this->appDBAdapter = $appDBAdapter;
        $this->appConfig = $appConfig;
        $this->logger = Logger::getLogger(get_class($this));
    }

    public function setConfig($config)
    {
        $this->config=$config;
    }

    public function runQuery($app_name, $entity_name, $parameters)
    {
        if (isset($parameters['top'])) {
            $parameters['pagesize']=$parameters['top'];
        }
        if (isset($parameters['skip'])) {
            $parameters['start']=$parameters['skip'];
        }
        if (isset($parameters['filter_grid'])) {
            $filtergrid = $parameters['filter_grid'];
            $parameters['inline_filter'][]=json_decode($filtergrid);
        }

        if (isset($parameters['orderby'])) {
            $sortpara = explode(" ",$parameters['orderby']);
            if (isset($parameters['columns'][$sortpara[0]])) {
                $keyword = ($parameters['columns'][$sortpara[0]]=='text' || $parameters['columns'][$sortpara[0]]=='string') ? '.keyword':'';
            }
            if (isset($sortpara[1])){
                $parameters['sort']=[$sortpara[0].$keyword=>$sortpara[1]];
            } else {
                $parameters['sort']=[$parameters['orderby'].$keyword=>'asc'];
            }
        } 
        $finalResult = $this->getData($app_name,$entity_name,$parameters);
        if (isset($parameters['expression']) ||  isset($parameters['round']) ) {
            $finalResult['data'] = $this->postProcess($finalResult['data'],$parameters);
        }
        if (isset($parameters['target'])) {
            $finalResult['target'] = $parameters['target'];
        }
        if (!empty($parameters['pivot'])) {
            $finalResult['data'] = $this->pivot($finalResult['data'], $parameters);
        }
        if (isset($parameters['template'])) {
            $finalResult['data'] = $this->applyTemplate($finalResult, $parameters);
        }
        if (!empty($parameters['debug'])) {
            $finalResult['targetquery'] = $this->getQuery();
        }
        return $finalResult;
    }

    public function pivot($data, $parameters)
    {
        $groupArray=explode(',', $parameters['group']);
        if (!isset($groupArray[0]) || !isset($groupArray[1])) {
            throw new \Exception('Please check query. Two Groups and Field should Exist');
        }
        $pivotResult = [];
        $group1 = $groupArray[0];
        $group2 = $groupArray[1];
        $tmpArray=[];
        $columnKeys = [];
        if (isset($data[0]['count'])) {
            $valueColumn = 'count';
        } else {
            $valueColumn = $parameters['field'];
        }
        foreach ($data as $row) {
            $tmpArray[$row[$group1]][$row[$group2]] = $row[$valueColumn];
            if (!isset($columnKeys[$row[$group2]])) {
                $columnKeys[$row[$group2]] = null;
            }
        }
        foreach ($tmpArray as $key=>$groupArray) {
            $array1 = $groupArray+$columnKeys;
            $pivotResult[] = array_merge([$group1=>$key], $array1);
        }
        return $pivotResult;
    }


    public function postProcess($resultData, $parameters)
    {
        $expression = "";
        $round = null;

        if (isset($parameters['expression'])) {
            $expression = $parameters['expression'];
        }
        if (isset($parameters['round'])) {
            $round = $parameters['round'];
        }
        $finalResults = $resultData;
        if (is_array($resultData)) {
            $field = isset($parameters['field']) ?$parameters['field']:'count';
            foreach ($resultData as $key => $data) {
                $value = $data[$field];
                eval("\$value=\$value" . $expression . ";");
                if ($round !== null && is_numeric($value)) {
                    $value = round($value, $round);
                }
                $finalResults[$key][$field] = $value;
            }
        } else {
            $value = $resultData;
            eval("\$value=\$value" . $expression . ";");
            if ($round !== null && is_numeric($value)) {
                $value = round($value, $round);
            }
            $finalResults = $value;
        }
        return $finalResults;
    }


    public function applyTemplate($resultData, $parameters)
    {
        $templateName = $parameters['template'];
        $templateEngine = new TemplateService($this->appConfig, $this->appDBAdapter);
        $templateEngine->init();
        $result = $templateEngine->getContent($templateName, $resultData);
        $result = str_replace(array("\r\n","\r","\n","\t"), '', $result);
        return $result;
    }
}
