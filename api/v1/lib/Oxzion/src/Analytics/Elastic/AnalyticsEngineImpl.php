<?php
namespace Oxzion\Analytics\Elastic;

use Exception;
use Oxzion\Analytics\AnalyticsEngine;
use Elasticsearch\ClientBuilder;
use Oxzion\Service\ElasticService;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Oxzion\Analytics\AnalyticsAbstract;

class AnalyticsEngineImpl extends AnalyticsAbstract
{
    private $hasGroup;
    private $elasticService;
    private $query;

    public function __construct($appDBAdapter, $appConfig, ElasticService $elasticService)
    {
        parent::__construct($appDBAdapter, $appConfig);
        $this->elasticService = $elasticService;
    }

    public function setConfig($config)
    {
        parent::setConfig($config);
        $this->elasticService->setConfig($config);
    }
    
    public function getQuery()
    {
        return $this->query;
    }

    public function getData($app_name, $entity_name, $parameters)
    {
        $this->logger->debug('Run query parameters:');
        $this->logger->debug([
            'app_name' => $app_name,
            'entity_name' => $entity_name,
            'parameters' => $parameters
        ]);
        try {
            $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
            $query = $this->formatQuery($parameters);
            if ($entity_name) {
                $query['filter'][]=['entity_name','==',$entity_name];
            }
            $elasticService = $this->elasticService;
            $result = $elasticService->getQueryResults($accountId, $app_name, $query);
            $this->query = $elasticService->getElasticQuery();
            $finalResult['meta'] = $query;
            $finalResult['meta']['type'] = $result['type'];
            $finalResult['meta']['query'] = $result['query'];
            if (isset($result['total_count'])) {
                $finalResult['total_count'] = $result['total_count'];
            }
            if ($result['type']=='group') {
                $finalResult['data'] = $this->flattenResult($result['data'], $query);
            } else {
                if (isset($result['data'])) {
                    if (isset($result['data']['value'])) {
                        $finalResult['data']  = $result['data']['value'];
                    } else {
                        $finalResult['data']  = $result['data'];
                    }
                }
                if (isset($query['select'])) {
                    $finalResult['meta']['list'] = $query['select'];
                }
                if (isset($query['displaylist'])) {
                    $finalResult['meta']['displaylist'] = $query['displaylist'];
                }
            }
            return $finalResult;
        } catch (Exception $e) {
            throw new Exception("Error performing Elastic Search", 0, $e);
        }
    }


    private function formatQuery($parameters)
    {
        $frequency=null;
        $field = null;
        $dateperiod = null;
        $filter =array();
        $inline_filter = array();
        $datetype = (!empty($parameters['date_type']))?$parameters['date_type']:null;
        if (!empty($parameters['date-period'])) {
            $dateperiod = $parameters['date-period'];
        }
        if (!empty($parameters['date_period'])) {
            $dateperiod =  $parameters['date_period'];
        }

        if ($dateperiod) {
            $period = explode('/', $dateperiod);
            $startdate = date('Y-m-d', strtotime($period[0]));
            $enddate =  date('Y-m-d', strtotime($period[1]));
            //		} else {
//			$startdate = date('Y').'-01-01';
//			$enddate = date('Y').'-12-31';
        }
        if (!empty($parameters['field'])) {
            if (substr(strtolower($parameters['field']), 0, 5) == 'date(') {
                $parameters['field'] = substr($parameters['field'], 5, -1);
            }
            $field = $parameters['field'];
        }

        if (!isset($parameters['operation'])) {
            $parameters['operation'] = 'count';
        }

        $parameters['operation'] = strtolower($parameters['operation']);
        if ($parameters['operation'] != 'count_distinct') {
            $operation = explode('_', $parameters['operation']);
        } else {
            $operation[0] = $parameters['operation'];
        }

        $group = array();
        $aggregates = array();
        if (!empty($parameters['group'])) {
            $parameters['frequency'] = null;  //frequency 4 is to override time frequecy by group
            if (is_array($parameters['group'])) {
                $group = $parameters['group'];
            } else {
                $group = explode(',', $parameters['group']);
            }
        }
        if ($field) {
            $aggregates[$operation[0]] = $field;
        } else {
            if (!isset($parameters['list'])) {
                if (!empty($group)) {
                    $aggregates[$operation[0]] = $group[0];
                } else {
                    $aggregates[$operation[0]] = '_id';
                }
            }
        }
        if (!empty($parameters['frequency'])) {
            switch ($parameters['frequency']) {
                case 1:
                    $group[] = 'period-day';
                    break;
                case 2:
                    $group[] = 'period-month';
                    break;
                case 3:
                    $group[] = 'period-quarter';
                    break;
                case 4:
                    $group[] = 'period-year';
                    break;
            }
            $frequency = $datetype;
        }
        if (!isset($parameters['skipdate']) && $datetype && isset($startdate)) {
            $filter[] = [[$datetype,'>=','date:'.$startdate],'AND',[$datetype,'<=','date:'.$enddate]];
        }
        // $range[$datetype] = $startdate . "/" . $enddate;
        if (isset($parameters['filter'])) {
            $filter[] = $parameters['filter'];
        }
        if (isset($parameters['inline_filter'])) {
            foreach ($parameters['inline_filter'] as $inlineArry) {
                $inlineArry[3] = 'inline';
                array_unshift($filter, $inlineArry);
            }
        }
        $this->hasGroup = (empty($group)) ? 0 : 1;
        if (!empty($group)) {
            $group = array_map('strtolower', $group);
        }

        $returnarray = array('inline_filter'=>$inline_filter,'filter' => $filter, 'group' => $group, 'aggregates' => $aggregates, 'frequency'=>$frequency);
        if (isset($parameters['pagesize'])) {
            $returnarray['pagesize'] = $parameters['pagesize'];
        }
        if (isset($parameters['start'])) {
            $returnarray['start'] = $parameters['start'];
        }
        if (isset($parameters['excludes'])) {
            $returnarray['excludes'] = $parameters['excludes'];
        }
        if (isset($parameters['append_account_id'])) {
            $returnarray['append_account_id'] = $parameters['append_account_id'];
        }
        if (isset($parameters['list'])) {
            if ($parameters['list']=="*") {
                $returnarray['select'] = ['display_id', 'name', 'status', 'created_by', 'date_created','modified_by','date_modified'];
            } else {
                $listConfig=explode(",", $parameters['list']);
                foreach ($listConfig as $k => $v) {
                    if (strpos($v, "=")!==false) {
                        $listitem = explode("=", $v);
                        $returnarray['select'][] = $listitem[0];
                        $returnarray['displaylist'][] = $listitem[1];
                    } else {
                        $returnarray['select'][] = $v;
                        $returnarray['displaylist'][] = $v;
                    }
                }
            }
        }
        if (isset($parameters['sort'])) {
            $returnarray['sort'] = $parameters['sort'];
        }
        return $returnarray;
    }
    

    public function flattenmultigroups(&$finalresult, $result, $config, $count, $index, $keys=array())
    {
        $operation = key($config['aggregates']);
        $field = $config['aggregates'][$operation];
        if ($index==$count) {
            foreach ($result as $data) {
                $tempresult = array();
                for ($i=0;$i<$count;$i++) {
                    $tempresult[$config['group'][$i]] = $keys[$i];
                }
                $tempresult[$config['group'][$count]] = $data['key'];
                if ($operation=='count') {
                    $value = $data['doc_count'];
                    $tempresult['count']= $value;
                    $finalresult[] = $tempresult;
                //					$finalresult[] = array(implode(" - ",$config['group'])=>$key.' - '.$data['key'],'count'=>$value);
                } else {
                    $value = $data['value']['value'];
                    if ($operation=="count_distinct") {
                        $tempresult['count']= $value;
                    } else {
                        $tempresult[$field]= $value;
                    }
                    $finalresult[] = $tempresult;
                    //					$finalresult[] = array(implode(" - ",$config['group'])=>$key.' - '.$data['key'],$field=>$value);
                }
            }
        } else {
            foreach ($result as $data) {
                $groupname = 'groupdata'.$index;
                //	$keytemp = ($key) ? $key.' - '.$data['key']:$data['key'];
                $tempkeys = $keys;
                $tempkeys[] = $data['key'];
                $this->flattenmultigroups($finalresult, $data['groupdata'.(string)$index]['buckets'], $config, $count, $index+1, $tempkeys);
            }
        }
    }

    public function flattenResult($resultData, $config)
    {
        $finalresult = array();
        $operation = key($config['aggregates']);
        $field = $config['aggregates'][$operation];
        if ($operation=='count_distinct') {
            $field = 'count';
        }
        $qtrtranslate = array('Jan'=>'Q1','Apr'=>'Q2','Jul'=>'Q3','Oct'=>'Q4');
        if (isset($config['group'])) {
            if (count($config['group'])==1) {
                foreach ($resultData as $data) {
                    if (substr($config['group'][0], 0, 7)=='period-') {
                        $name = $data['key_as_string'];
                        if ($config['group'][0]=='period-quarter') {
                            $month = substr($name, 0, 3);
                            $name=$qtrtranslate[$month].substr($name, 3);
                        }
                    } else {
                        $name = $data['key'];
                    }
                    if ($operation=='count') {
                        $value = $data['doc_count'];
                        $finalresult[]=array($config['group'][0]=>$name,'count'=>$value);
                    } else {
                        $value = $data['value']['value'];
                        $finalresult[]=array($config['group'][0]=>$name,$field=>$value);
                    }
                }
            } else {
                $this->flattenmultigroups($finalresult, $resultData, $config, count($config['group'])-1, 0);
            }
        } else {
            $finalresult[] = array('name'=>$config['field'],'value'=>$resultData);
        }
        return $finalresult;
    }

    public function getFields($index)
    {
        $elasticService = $this->elasticService;
        $data = $elasticService->getMappings($index);
        $fields = $data[$index]['mappings']['properties'];
        return $fields;
    }

    public function getDataEntities()
    {
        $elasticService = $this->elasticService;
        $data = $elasticService->getIndexes();
        $indexes = array_column($data, 'index');
        return $indexes;
    }

    public function getValues($index, $field)
    {
        if (substr($index, -6)=="_index") {
            $index = substr($index, 0, strlen($index)-6);
        }
        $this->elasticService->setNoCore();
        $data = $this->getData($index, null, ['group'=>$field]);
        $values = array_column($data['data'], $field);
        return $values;
    }
}
