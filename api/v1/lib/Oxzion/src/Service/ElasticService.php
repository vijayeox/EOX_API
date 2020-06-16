<?php
namespace Oxzion\Service;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Logger;

use function GuzzleHttp\json_encode;

ini_set("memory_limit", -1);

class ElasticService
{
    private $avatarobj;
    private $elasticaddress;
    private $type;
    private $core;
    private $onlyaggs;
    private $config;
    private $client;
    private $logger;
    private $filterFields;
    private $filterTmpFields;

    public function __construct()
    {
        $this->logger = Logger::getLogger(get_class($this));
    }

    public function setConfig($config){
        $this->config = $config;
        $clientsettings = array();
        $clientsettings['host'] = $config['elasticsearch']['serveraddress'];
        $clientsettings['user'] = $config['elasticsearch']['user'];
        $clientsettings['pass'] = $config['elasticsearch']['password'];
    //    $clientsettings['type'] = $config['elasticsearch']['type'];
        $clientsettings['port'] = $config['elasticsearch']['port'];
        $clientsettings['scheme'] = $config['elasticsearch']['scheme'];
        $this->core = $config['elasticsearch']['core'];
        $this->logger->info("core to be used - ".$this->core);
        $this->type = $config['elasticsearch']['type'];
        $clientbuilder = ClientBuilder::create(); 
        if(isset($_ENV['ENV']) && $_ENV['ENV'] == 'test' && !$this->client){
            $this->client = $clientbuilder->setHosts(array($clientsettings))->build();
        }
    }
    public function setElasticClient($client){
        $this->client = $client;
    }

    public function getElasticClient(){
        return $this->client;
    }

    public function create($indexName,$fieldList,$settings) {

        $typemapper = ['int'=>'integer','text'=>'text'];

        if (isset($settings['shrads'])) {
            $shrads = $settings['shrads'];
        } else {
            $shrads = 1;
        }
        if (isset($settings['replicas'])) {
            $replicas = $settings['replicas'];
        } else {
            $replicas = 1;
        }


       foreach ($fieldList as $field) {
           $type =  (isset($typemapper[$field['type']])) ? $typemapper[$field['type']]:$field['type'];
           $fieldProperties[$field['name']]=['type'=>$type];
       }
       $client = ClientBuilder::create()->build();
       $params = [
            'index' => $indexName,
            'body' => [
                'settings' => [
                    'number_of_shards' => $shrads,
                    'number_of_replicas' => $replicas
                ],
                'mappings' => [
                    '_source' => [
                        'enabled' => true
                    ],
                    'properties' => $fieldProperties
                ]
            ]
        ];

        // Create the index with mappings and settings now
        $response = $client->indices()->create($params);
    }

    public function getSettings()
    {
        return array('index' => $this->core, 'type' => $this->type);
    }

    public function setIsOnlyAggs()
    {
        $this->onlyaggs = 1;
    }

    public function FilterDirect($entity, $bodyjson)
    {
        $body = json_decode($bodyjson, true);
        $params = array('index' => $this->core . '_' . $entity, 'type' => $this->type, 'body' => $body, "size" => 0);
        $result = $this->search($params);
        $result_obj = $result['data'];
        if (isset($body['aggs']) && isset($result_obj['aggregations']['groupdata']['buckets'])) {
            $results = array('data' => $result_obj['aggregations']['groupdata']['buckets']);
        } else if (isset($result_obj['aggregations'])) {
            $results = array('data' => $result_obj['aggregations']['value']['value']);

        } else {
            $results = array('data' => $result_obj['hits']['total']);
        }
        return $results;
    }

    public function getSearchResults($index, $body, $source, $start, $pagesize)
    {
        // print_r($body);exit;
        $params = ['index' => $index, 'body' => $body, "_source" => $source, 'from' => $start ? $start : 0, "size" => $pagesize];
        $result = $this->search($params);
        return $result['data'];
    }

    public function getQueryResults($orgId, $app_name, $params)
    {
        $result = $this->filterData($orgId, $app_name, $params);
        return $result;

    }

    public function filterData($orgId, $app_name, $searchconfig)
    {
        $boolfilter = array();
        $tmpfilter = $this->getFilters($searchconfig, $orgId);
        
		if ($tmpfilter) {
			$boolfilterquery['query']['bool'] = $tmpfilter;
		}	
		$boolfilterquery['_source'] = (isset($searchconfig['select']))?$searchconfig['select']:array('*');
		$pagesize = isset($searchconfig['pagesize'])?$searchconfig['pagesize']:10000;
		if(!empty($searchconfig['aggregates'])) {
			if (!isset($searchconfig['select'])) {
				$pagesize=0;
			}
			$aggs=$this->getAggregate($searchconfig['aggregates'],$boolfilterquery);	
			if($searchconfig['group'] && !empty($searchconfig['group'])) {
				$this->getGroups($searchconfig,$boolfilterquery,$aggs);
			} else {
				if($aggs){
					$pagesize=0;
					$boolfilterquery['aggs']=$aggs;
				} 
			}
		} 
        
		$boolfilterquery['explain'] = true;
        $params = array('index'=>$app_name.'_index','body'=>$boolfilterquery,"_source"=>$boolfilterquery['_source'],'from'=>(!empty($searchconfig['start']))?$searchconfig['start']:0,"size"=>$pagesize);
        if(empty($searchconfig['aggregates'])) {
            if (isset($searchconfig['sort'])) {
                if (is_array($searchconfig['sort'])) {
                    $params['body']['sort'] = $searchconfig['sort'];
                }
            }
        }

        $result_obj = $this->search($params);
		if ($searchconfig['group'] && !isset($searchconfig['select'])) {
			$results = array('data'=>$result_obj['data']['aggregations']['groupdata']['buckets']);
			$results['type']='group';
		} else if(key($searchconfig['aggregates'])=='count' && !isset($searchconfig['select'])){
			$results = array('data'=>$result_obj['data']['hits']['total']);
			$results['type']='value';
		} else if (isset($result_obj['data']['aggregations'])){
			$results = array('data'=>$result_obj['data']['aggregations']['value']['value']);
			$results['type']='value';
		}  else {
			$results = array();
			foreach($result_obj['data']['hits']['hits'] as $key=>$value){
				$results['data'][$key] = $value['_source'];
			//	$results['data'][$key]['id'] = $value['_source']['_id'];
			}
			$results['type']='list';
        }
        $results['query']=$result_obj['query'];
		return $results;
    }
    
//    {"OR",{"==",["department","DEP1"},{"==",["department","DEP2"}}
//    {"==", ["department", "DEP1" ]},
//    {">=", ["sale_date", "2019-10-01"]},
 //   {"<=", ["sale_date", "2019-10-31"]},


    protected function createFilter($filter) {
        $subQuery = null;
        $symMapping = ['>'=>'gt','>='=>'gte','<'=>'lt','<='=>'lte'];
        $boolMapping = ['OR'=>'should','AND'=>'must'];
        if (!isset($filter[1]) && is_array($filter)) {
            $filter = $filter[0];
        }
        $column = $filter[0];
        if (isset($filter[2])) {
            $value = $filter[2];
            $condition = $filter[1];
        } else {
            $condition = "==";
            $value = $filter[1];
        }
        if (strtoupper($condition)=='OR' OR strtoupper($condition)=='AND') {
                 $tempQuery1 = $this->createFilter($column);
                 $tempQuery2 = $this->createFilter($value);
                 if ($tempQuery1) {
                    $subQuery['bool'][$boolMapping[$condition]][] = $tempQuery1;
                 }
                 if ($tempQuery2) {
                    $subQuery['bool'][$boolMapping[$condition]][] = $tempQuery2;
                 }
                 
        } else {
        //    echo $column.' '.$condition.' '.$value;
            if (!in_array($column,$this->filterFields)) {
                if ($condition=="=="){                
                        if (!is_array($value)) {
                        $subQuery['match'] = array($column => array('query' => $value, 'operator' => 'and'));
                        } else {
                            $subQuery['terms'] = array($column => array_values($value));
                        }    
                } elseif ($condition=="<>" || $condition=="!=") {
                        $subQuery['bool']['must_not'][] =  ["term"=>[ $column=>$value ]];
                }  else {
                        if (strtolower(substr($value,0,5))=="date:") {
                            $value = date("Y-m-d",strtotime(substr($value,5)));
                            $subQuery['range'] = array($column => array($symMapping[$condition] => $value,"format" => "yyyy-MM-dd"));
                        } else {
                            $subQuery['range'] = array($column => array($symMapping[$condition] => $value));
                        }
                        
                }
                $this->filterTmpFields[] = $column;
            }
         }
        return $subQuery;
    }

    protected function getGroups($searchconfig, &$boolfilterquery, $aggs)
    {

        $grouparray = null;
        $size = (isset($searchconfig['pagesize'])) ? $searchconfig['pagesize'] : 10000;
        for ($i = count($searchconfig['group']) - 1; $i >= 0; $i--) {
            $grouptext = $searchconfig['group'][$i];
            if (substr($grouptext, 0, 7) == "period-") {
                $interval = substr($grouptext, 7);
                if ($interval == "day") {
                    $format = "yyyy-MM-dd";
                } elseif ($interval == "year") {
                    $format = "yyyy";
                } else {
                    $format = "MMM-yyyy";
                }
                $grouparraytmp = array('date_histogram' => array('field' => key($searchconfig['range']), 'interval' => $interval, 'format' => $format));
            } else {
                $grouparraytmp = array('terms' => array('field' => $grouptext . '.keyword', 'size' => $size));
                $boolfilterquery['_source'][] = $grouptext;
            }

            if ($grouparray) {
                $grouparray = array_merge($grouparraytmp, array('aggs' => array('groupdata' . $i => $grouparray)));
            } else {
                if (isset($searchconfig['sort'])) {
                    if (!is_array($searchconfig['sort'])) {
                        if ($aggs) {
                            $grouparraytmp['terms']['order'] = array("value" => $searchconfig['sort']);
                        } else {
                            $grouparraytmp['terms']['order'] = array("_count" => $searchconfig['sort']);
                        }    
                    } else {
                        $searchkey = key($searchconfig['sort']);
                        $searchdir = $searchconfig['sort'][key($searchconfig['sort'])];
                        if ($searchkey == 'count' || $searchkey == 'term') {
                            $searchkey = '_'.$searchkey;
                        }
                        if ($searchkey!='_count' && $searchkey!='_term' && $searchkey!='value') {
                            $searchkey = '_term';
                        }
                        $grouparraytmp['terms']['order'] = [$searchkey=>$searchdir];
                    }
                }
                if ($aggs) {
                    $grouparray = array_merge($grouparraytmp, array('aggs' => $aggs));
                } else {
                    $grouparray = $grouparraytmp;
                }
            }
        }

        $boolfilterquery['aggs'] = array('groupdata' => $grouparray);
//        print_r($boolfilterquery);exit;
    }

    protected function generateHighlightingFields($query, $fields)
    {
        return array('order' => 'score', "require_field_match" => 'true', 'fields' => array("*" => array('force_source' => false, "pre_tags" => array("<b class='highlight'>"), "post_tags" => array("</b>"), 'number_of_fragments' => 3, 'fragment_size' => 100)), 'encoder' => 'html');
    }

    protected function getTextQuery($query, $text, $entity, $fields)
    {
        if (strpos($text, 'query:') !== false) {
            $query['bool']['must'][] = array("query_string" => (array("query" => str_replace('query:', "", $text))));
        } else {
            $query['bool']['should'][] = array("multi_match" => array("fields" => $this->getBoostFields($entity, $fields), "query" => $text, "fuzziness" => "AUTO"));
        }
        return $query;
    }
    protected function getAggregate($aggregates, $filter)
    {
        $aggs = null;
        if (key($aggregates) == 'count_distinct') {
            $aggs = array('value' => array("cardinality" => array("field" => $aggregates[key($aggregates)])));
        } else if (key($aggregates) != "count") {
            $aggs = array('value' => array(key($aggregates) => array('field' => $aggregates[key($aggregates)])));
        }
        return $aggs;
    }



    protected function getFilters($searchconfig, $orgId)
    {
        $mustquery['must'][] = ['term' => ['org_id' => $orgId]];
        if (!empty($searchconfig['aggregates'])) {
            $aggregates = $searchconfig['aggregates'];
            $mustquery['must'][] = array('exists' => array('field' => $aggregates[key($aggregates)]));
        }
        $this->filterFields = array();
        if (!empty($searchconfig['filter'])) {
            foreach ($searchconfig['filter'] as $filter) {
           //     echo 'filter:';print_r($filter); echo '--';
                $this->filterTmpFields = array();
                $filterArry = $this->createFilter($filter);
                if ($filterArry) {
                    $mustquery['must'][] = $filterArry;
                }
                $this->filterFields=array_merge($this->filterFields,$this->filterTmpFields);
            }
        }
        if ($searchconfig['range']) {
            $daterange = $searchconfig['range'][key($searchconfig['range'])];
            $dates = explode("/", $daterange);
            $mustquery['must'][] = array('range' => array(key($searchconfig['range']) => array("gte" => $dates[0], "lte" => $dates[1], "format" => "yyyy-MM-dd")));

        }
        return $mustquery;

    }

    protected function getFiltersByEntity($entity)
    {
        $avatar_groups = $this->avatarobj->getGroupArray();
        switch ($entity) {
            case 'instanceforms':
            case 'formcomments':
                $mustquery['bool']['should'][] = array('terms' => array('ownergroupid' => array_values($avatar_groups)));
                $mustquery['bool']['should'][] = array('terms' => array('assignedgroupid' => array_values($avatar_groups)));
                break;
            case 'messages':
                $mustquery['bool']['should'][] = array('match' => array('recipient_list' => $this->avatarobj->name));
                $mustquery['bool']['should'][] = array('term' => array('from_user' => $this->avatarobj->name));
                break;
            case 'ole':
                $mustquery[] = array('terms' => array('groupid' => array_values($avatar_groups)));
                break;
            case 'user':
                $mustquery[] = array('match' => array('status' => 'Active'));
                $mustquery[] = array('match' => array('role' => 'employee'));
                break;
            case 'timesheet':
                $mustquery[] = array('terms' => array('group_id' => array_values($avatar_groups)));
                break;
            case 'attachments':
                $mustquery['bool']['should'][] = array('terms' => array('ownergroupid' => array_values($avatar_groups)));
                $mustquery['bool']['should'][] = array('terms' => array('assignedgroupid' => array_values($avatar_groups)));
                $mustquery['bool']['should'][] = array('match' => array('recipient_list' => $this->avatarobj->name));
                $mustquery['bool']['should'][] = array('term' => array('from_user' => $this->avatarobj->name));
                break;
            default:
                break;
        }
        return $mustquery;
    }

    public function search($q)
    {
        if  ($this->core) {
            $q['index'] = $this->core.'_'.$q['index'];
        }
        $this->logger->debug('Elastic query:');
        $this->logger->debug(json_encode($q, JSON_PRETTY_PRINT));
        $data['query'] = json_encode($q);
        $data["data"] = $this->client->search($q);
        $this->logger->debug('Data from elastic:');
        $this->logger->debug(json_encode($data, JSON_PRETTY_PRINT));
        return $data;
    }

    public function bulk($body) {
        $responses = $this->client->bulk($body);
    }

    public function index($index, $id, $body)
    {
        if (substr($index,-6)!="_index") {
            $index = $index."_index";
        }
        $index = ($this->core) ? $this->core.'_'.$index:$index;
        $params['index'] = $index;
        $params['id'] = $id;
        $params['body'] = $body;
        return $this->client->index($params);
    }

    public function delete($index, $id)
    {
        if (substr($index,-6)!="_index") {
            $index = $index."_index";
        }
        $index = ($this->core) ? $this->core.'_'.$index:$index;
        if ($id == 'all') {
            return $this->client->indices()->delete(['index' => $index]);
        } else {
            return $this->client->delete(['index' => $index, 'type' => $this->type, 'id' => $id]);
        }
	}
	
	public function getBoostFields($entity){
		switch ($entity) {
			case 'files':
				return array('id^6','name^4','desc_raw^0.1','assignedto^2','createdby^2');
				break;
			case 'formcomments':
				return array('id^6','comment^4','title^4');
				break;
			case 'messages':
				return array('id^6','subject^4','message^2');
				break;
			case 'ole':
				return array('id^4','ole^6','group^3');
				break;
			case 'user':
				return array('id^6','firstname^2','lastname^2','name^4','about^0.1', 'email^1', 'country^1', 'designation^1', 'company_name^1', 'address_1^1', 'address_2^1');
				break;
			case 'attachments':
				return array('id^6','attachment.content^4','filename^2');
				break;
			default:
				return ;
				break;
		}
	}

}
