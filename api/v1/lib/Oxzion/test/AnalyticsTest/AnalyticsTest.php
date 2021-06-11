<?php
namespace Analytics;

use Oxzion\Test\ControllerTest;
use Oxzion\Db\ModelTable;
use PHPUnit\DbUnit\TestCaseTrait;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Zend\Db\Sql\Sql;
use Zend\Db\Adapter\Adapter;
use Oxzion\Analytics\Elastic\AnalyticsEngineImpl;
use Oxzion\Search;
use Oxzion\Search\Indexer;
use Oxzion\Analytics;
use PHPUnit\DbUnit\DataSet\SymfonyYamlParser;
use Oxzion\Test\MainControllerTest;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Mockery;
use Exception;

use function GuzzleHttp\json_encode;

class AnalyticsTest extends MainControllerTest
{
    private $dataset;
    private $searchFactory;
    private $analyticsFactory;
    private $index_pre;
    private $client;

    public function setUp() : void
    {
        $this->loadConfig();
        parent::setUp();
        $config = $this->getApplicationConfig();
        if (isset($config['elasticsearch']['core'])) {
            $this->index_pre = $config['elasticsearch']['core'].'_';
        } else {
            $this->index_pre = '';
        }
        if (enableElastic!=0) {
            $this->setSearchData();
            $this->setupData();
            sleep(1) ;
        }
        $this->elasticService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\ElasticService::class);

        $this->client = $this->elasticService->getElasticClient();
    }


    private function setMockData($input, $output)
    {
        $clientMock = Mockery::mock('Elasticsearch\Client');
        $this->elasticService->setElasticClient($clientMock);
        $clientMock->shouldReceive('search')
        ->with(json_decode($input, true))
        ->andReturn(json_decode($output, true));
    }

    public function setSearchData()
    {
        $parser = new SymfonyYamlParser();
        $this->dataset = $parser->parseYaml(dirname(__FILE__)."/Dataset/Analytics.yml");
    }

    public function createIndex($indexer, $body)
    {
        $app_name = $body['app_name'];
        $id = $body['id'];
        AuthContext::put(AuthConstants::ACCOUNT_ID, $body['account_id']);
        $return=$indexer->index($app_name, $id, null, $body);   //entity_name is taken from the body, so passing null
    }

    public function setupData()
    {
        $indexer=  $this->getApplicationServiceLocator()->get(Indexer::class);
        $dataset = $this->dataset['ox_analysis'];
        foreach ($dataset as $body) {
            $this->createIndex($indexer, $body);
        }
    }

    public function testGrouping()
    {
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngineImpl::class);
        $ae->setConfig($this->getApplicationConfig());
        if (enableElastic==0) {
            //  $this->markTestSkipped('Only Integration Test');
            $input = '{"index":"core_11_test_index","body":{"query":{"bool":{"must":[{"term":{"account_id":1}},{"exists":{"field":"amount"}},{"bool":{"must":[{"range":{"date_created":{"gte":"2018-01-01","format":"yyyy-MM-dd"}}},{"range":{"date_created":{"lte":"2018-12-12","format":"yyyy-MM-dd"}}}]}}]}},"_source":["*","created_by"],"aggs":{"groupdata":{"terms":{"field":"created_by.keyword","size":10000},"aggs":{"value":{"sum":{"field":"amount"}}}}},"explain":true},"_source":["*","created_by"],"from":0,"size":0,"track_total_hits":true}';
            $output = '{"took":3,"timed_out":false,"_shards":{"total":1,"successful":1,"skipped":0,"failed":0},"hits":{"total":{"value":3,"relation":"eq"},"max_score":null,"hits":[]},"aggregations":{"groupdata":{"doc_count_error_upper_bound":0,"sum_other_doc_count":0,"buckets":[{"key":"John Doe","doc_count":2,"value":{"value":800}},{"key":"Mike Price","doc_count":1,"value":{"value":50.5}}]}}}';
            $this->setMockData($input, $output);
        }
        AuthContext::put(AuthConstants::ACCOUNT_ID, 1);
        $parameters = ['group'=>'created_by','field'=>'amount','operation'=>'sum','date-period'=>'2018-01-01/2018-12-12','date_type'=>'date_created'];
        $results = $ae->runQuery('11_test', null, $parameters);
        $results = $results['data'];
        $this->assertEquals($results[0]['created_by'], "John Doe");
        $this->assertEquals($results[0]['amount'], "800");
        $this->assertEquals($results[1]['created_by'], "Mike Price");
        $this->assertEquals($results[1]['amount'], "50.5");
    }

    public function testDoubleGrouping()
    {
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngineImpl::class);
        $ae->setConfig($this->getApplicationConfig());
        if (enableElastic==0) {
            $this->markTestSkipped('Only Integration Test');
            $input = '{"index":"'.$this->index_pre.'11_test_index","body":{"query":{"bool":{"must":[{"term":{"account_id":1}},{"exists":{"field":"amount"}},{"range":{"date_created":{"gte":"2018-01-01","lte":"2018-12-12","format":"yyyy-MM-dd"}}}]}},"_source":["*","category","created_by"],"aggs":{"groupdata":{"terms":{"field":"created_by.keyword","size":10000},"aggs":{"groupdata0":{"terms":{"field":"category.keyword","size":10000},"aggs":{"value":{"sum":{"field":"amount"}}}}}}},"explain":true},"_source":["*","category","created_by"],"from":0,"size":0}';
            $output = '{"took":7,"timed_out":false,"_shards":{"total":1,"successful":1,"skipped":0,"failed":0},"hits":{"total":{"value":3,"relation":"eq"},"max_score":null,"hits":[]},"aggregations":{"groupdata":{"doc_count_error_upper_bound":0,"sum_other_doc_count":0,"buckets":[{"key":"John Doe","doc_count":2,"groupdata0":{"doc_count_error_upper_bound":0,"sum_other_doc_count":0,"buckets":[{"key":"A","doc_count":1,"value":{"value":200}},{"key":"B","doc_count":1,"value":{"value":600}}]}},{"key":"Mike Price","doc_count":1,"groupdata0":{"doc_count_error_upper_bound":0,"sum_other_doc_count":0,"buckets":[{"key":"A","doc_count":1,"value":{"value":50.5}}]}}]}}}';
            $this->setMockData($input, $output);
        }
        AuthContext::put(AuthConstants::ACCOUNT_ID, 1);
        $parameters = ['group'=>'created_by,category','field'=>'amount','operation'=>'sum','date-period'=>'2018-01-01/2018-12-12','date_type'=>'date_created'];
        $results = $ae->runQuery('11_test', null, $parameters);
        $results = $results['data'];
        $this->assertEquals($results[0]['created_by'], "John Doe");
        $this->assertEquals($results[0]['category'], "A");
        $this->assertEquals($results[0]['amount'], "200");
        $this->assertEquals($results[1]['created_by'], "John Doe");
        $this->assertEquals($results[1]['category'], "B");
        $this->assertEquals($results[1]['amount'], "600");
        $this->assertEquals($results[2]['created_by'], "Mike Price");
        $this->assertEquals($results[2]['category'], "A");
        $this->assertEquals($results[2]['amount'], "50.5");
    }

    public function testTripleGroupingCount()
    {
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngineImpl::class);
        $ae->setConfig($this->getApplicationConfig());
        if (enableElastic==0) {
            $this->markTestSkipped('Only Integration Test');
            $input = '{"index":"'.$this->index_pre.'11_test_index","body":{"query":{"bool":{"must":[{"term":{"account_id":1}},{"exists":{"field":"amount"}},{"range":{"date_created":{"gte":"2018-01-01","lte":"2018-12-12","format":"yyyy-MM-dd"}}}]}},"_source":["*","modified_by","category","created_by"],"aggs":{"groupdata":{"terms":{"field":"created_by.keyword","size":10000},"aggs":{"groupdata0":{"terms":{"field":"category.keyword","size":10000},"aggs":{"groupdata1":{"terms":{"field":"modified_by.keyword","size":10000}}}}}}},"explain":true},"_source":["*","modified_by","category","created_by"],"from":0,"size":0}';
            $output = '{"took":9,"timed_out":false,"_shards":{"total":1,"successful":1,"skipped":0,"failed":0},"hits":{"total":{"value":3,"relation":"eq"},"max_score":null,"hits":[]},"aggregations":{"groupdata":{"doc_count_error_upper_bound":0,"sum_other_doc_count":0,"buckets":[{"key":"John Doe","doc_count":2,"groupdata0":{"doc_count_error_upper_bound":0,"sum_other_doc_count":0,"buckets":[{"key":"A","doc_count":1,"groupdata1":{"doc_count_error_upper_bound":0,"sum_other_doc_count":0,"buckets":[{"key":"Jane Doe","doc_count":1}]}},{"key":"B","doc_count":1,"groupdata1":{"doc_count_error_upper_bound":0,"sum_other_doc_count":0,"buckets":[{"key":"Jane Doe","doc_count":1}]}}]}},{"key":"Mike Price","doc_count":1,"groupdata0":{"doc_count_error_upper_bound":0,"sum_other_doc_count":0,"buckets":[{"key":"A","doc_count":1,"groupdata1":{"doc_count_error_upper_bound":0,"sum_other_doc_count":0,"buckets":[{"key":"Mark Doe","doc_count":1}]}}]}}]}}}';
            $this->setMockData($input, $output);
        }
        AuthContext::put(AuthConstants::ACCOUNT_ID, 1);
        $parameters = ['group'=>'created_by,category,modified_by','field'=>'amount','operation'=>'count','date-period'=>'2018-01-01/2018-12-12','date_type'=>'date_created'];
        $results = $ae->runQuery('11_test', null, $parameters);
        $results = $results['data'];
        $this->assertEquals($results[0]['created_by'], "John Doe");
        $this->assertEquals($results[0]['category'], "A");
        $this->assertEquals($results[0]['modified_by'], "Jane Doe");
        $this->assertEquals($results[0]['count'], "1");

        $this->assertEquals($results[1]['created_by'], "John Doe");
        $this->assertEquals($results[1]['category'], "B");
        $this->assertEquals($results[1]['modified_by'], "Jane Doe");
        $this->assertEquals($results[1]['count'], "1");


        $this->assertEquals($results[2]['created_by'], "Mike Price");
        $this->assertEquals($results[2]['category'], "A");
        $this->assertEquals($results[2]['modified_by'], "Mark Doe");
        $this->assertEquals($results[2]['count'], "1");
    }

    public function testLists()
    {
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngineImpl::class);
        $ae->setConfig($this->getApplicationConfig());
        if (enableElastic==0) {
            $this->markTestSkipped('Only Integration Test');
            $input = '{"index":"'.$this->index_pre.'11_test_index","body":{"query":{"bool":{"must":[{"term":{"account_id":1}},{"exists":{"field":"amount"}},{"range":{"date_created":{"gte":"2018-01-01","lte":"2019-12-12","format":"yyyy-MM-dd"}}}]}},"_source":["name","created_by","category"],"explain":true},"_source":["name","created_by","category"],"from":0,"size":10000}';
            $output = '{"took":12,"timed_out":false,"_shards":{"total":1,"successful":1,"skipped":0,"failed":0},"hits":{"total":{"value":4,"relation":"eq"},"max_score":3,"hits":[{"_shard":"[11_test_index][0]","_node":"jDmSXFh4Qni0KdDbDecfKw","_index":"11_test_index","_type":"_doc","_id":"1","_score":3,"_source":{"name":"test document","category":"A","created_by":"John Doe"},"_explanation":{"value":3,"description":"sum of:","details":[{"value":1,"description":"account_id:[1 TO 1]","details":[]},{"value":1,"description":"ConstantScore(DocValuesFieldExistsQuery [field=amount])","details":[]},{"value":1,"description":"ConstantScore(DocValuesFieldExistsQuery [field=date_created])","details":[]}]}},{"_shard":"[11_test_index][0]","_node":"jDmSXFh4Qni0KdDbDecfKw","_index":"11_test_index","_type":"_doc","_id":"2","_score":3,"_source":{"name":"testing document","category":"A","created_by":"Mike Price"},"_explanation":{"value":3,"description":"sum of:","details":[{"value":1,"description":"account_id:[1 TO 1]","details":[]},{"value":1,"description":"ConstantScore(DocValuesFieldExistsQuery [field=amount])","details":[]},{"value":1,"description":"ConstantScore(DocValuesFieldExistsQuery [field=date_created])","details":[]}]}},{"_shard":"[11_test_index][0]","_node":"jDmSXFh4Qni0KdDbDecfKw","_index":"11_test_index","_type":"_doc","_id":"3","_score":3,"_source":{"name":"different document","category":"A","created_by":"John Doe"},"_explanation":{"value":3,"description":"sum of:","details":[{"value":1,"description":"account_id:[1 TO 1]","details":[]},{"value":1,"description":"ConstantScore(DocValuesFieldExistsQuery [field=amount])","details":[]},{"value":1,"description":"ConstantScore(DocValuesFieldExistsQuery [field=date_created])","details":[]}]}},{"_shard":"[11_test_index][0]","_node":"jDmSXFh4Qni0KdDbDecfKw","_index":"11_test_index","_type":"_doc","_id":"6","_score":3,"_source":{"name":"New document","category":"B","created_by":"John Doe"},"_explanation":{"value":3,"description":"sum of:","details":[{"value":1,"description":"account_id:[1 TO 1]","details":[]},{"value":1,"description":"ConstantScore(DocValuesFieldExistsQuery [field=amount])","details":[]},{"value":1,"description":"ConstantScore(DocValuesFieldExistsQuery [field=date_created])","details":[]}]}}]}}';
            $this->setMockData($input, $output);
        }
        AuthContext::put(AuthConstants::ACCOUNT_ID, 1);
        $parameters = ['date-period'=>'2018-01-01/2019-12-12','date_type'=>'date_created','list'=>'name,created_by,category'];
        $results = $ae->runQuery('11_test', null, $parameters);
        $results = $results['data'];
        $this->assertEquals($results[0]['name'], "test document");
        $this->assertEquals($results[0]['category'], "A");
        $this->assertEquals($results[0]['created_by'], "John Doe");
    }

    public function testAggregatesNoGroups()
    {
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngineImpl::class);
        $ae->setConfig($this->getApplicationConfig());
        if (enableElastic==0) {
            $this->markTestSkipped('Only Integration Test');
            $input = '{"index":"'.$this->index_pre.'11_test_index","body":{"query":{"bool":{"must":[{"term":{"account_id":1}},{"exists":{"field":"amount"}},{"range":{"date_created":{"gte":"2018-01-01","lte":"2019-12-12","format":"yyyy-MM-dd"}}}]}},"_source":["*"],"aggs":{"value":{"sum":{"field":"amount"}}},"explain":true},"_source":["*"],"from":0,"size":0}';
            $output = '{"took":3,"timed_out":false,"_shards":{"total":1,"successful":1,"skipped":0,"failed":0},"hits":{"total":{"value":4,"relation":"eq"},"max_score":null,"hits":[]},"aggregations":{"value":{"value":950.5}}}';
            $this->setMockData($input, $output);
        }
        AuthContext::put(AuthConstants::ACCOUNT_ID, 1);
        $parameters = ['operation'=>'sum','field'=>'amount','date-period'=>'2018-01-01/2019-12-12','date_type'=>'date_created'];
        $results = $ae->runQuery('11_test', null, $parameters);
        $results = $results['data'];

        $this->assertEquals($results, 950.5);
    }

    public function testOnlyFilters()
    {
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngineImpl::class);
        $ae->setConfig($this->getApplicationConfig());
        if (enableElastic==0) {
            $this->markTestSkipped('Only Integration Test');
            $input = '{"index":"'.$this->index_pre.'11_test_index","body":{"query":{"bool":{"must":[{"term":{"account_id":1}},{"exists":{"field":"_id"}},{"range":{"date_created":{"gte":"2018-01-01","lte":"2019-12-12","format":"yyyy-MM-dd"}}}]}},"_source":["*"],"explain":true},"_source":["*"],"from":0,"size":0}';
            $output = '{"took":4,"timed_out":false,"_shards":{"total":1,"successful":1,"skipped":0,"failed":0},"hits":{"total":{"value":4,"relation":"eq"},"max_score":null,"hits":[]}}';
            $this->setMockData($input, $output);
        }
        AuthContext::put(AuthConstants::ACCOUNT_ID, 1);
        $parameters = ['date-period'=>'2018-01-01/2019-12-12','date_type'=>'date_created'];
        $results = $ae->runQuery('11_test', null, $parameters);
        $results = $results['data'];
        $this->assertEquals($results, 4);
    }

    public function testDefaultField()
    {
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngineImpl::class);
        $ae->setConfig($this->getApplicationConfig());
        if (enableElastic==0) {
            $this->markTestSkipped('Only Integration Test');
            $input = '{"index":"'.$this->index_pre.'11_test_index","body":{"query":{"bool":{"must":[{"term":{"account_id":1}},{"exists":{"field":"created_by"}},{"range":{"date_created":{"gte":"2018-01-01","lte":"2019-12-12","format":"yyyy-MM-dd"}}}]}},"_source":["*","created_by"],"aggs":{"groupdata":{"terms":{"field":"created_by.keyword","size":10000}}},"explain":true},"_source":["*","created_by"],"from":0,"size":0}';
            $output = '{"took":7,"timed_out":false,"_shards":{"total":1,"successful":1,"skipped":0,"failed":0},"hits":{"total":{"value":4,"relation":"eq"},"max_score":null,"hits":[]},"aggregations":{"groupdata":{"doc_count_error_upper_bound":0,"sum_other_doc_count":0,"buckets":[{"key":"John Doe","doc_count":3},{"key":"Mike Price","doc_count":1}]}}}';
            $this->setMockData($input, $output);
        }
        AuthContext::put(AuthConstants::ACCOUNT_ID, 1);
        $parameters = ['group'=>'created_by','operation'=>'count','date-period'=>'2018-01-01/2019-12-12','date_type'=>'date_created'];
        $results = $ae->runQuery('11_test', null, $parameters);
        $results = $results['data'];
        $this->assertEquals($results[0]['created_by'], "John Doe");
        $this->assertEquals($results[0]['count'], "3");
        $this->assertEquals($results[1]['created_by'], "Mike Price");
        $this->assertEquals($results[1]['count'], "1");
    }

    public function testWorkflowData()
    {
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngineImpl::class);
        $ae->setConfig($this->getApplicationConfig());
        if (enableElastic==0) {
            $this->markTestSkipped('Only Integration Test');
            $input = '{"index":"'.$this->index_pre.'sampleapp_index","body":{"query":{"bool":{"must":[{"term":{"account_id":1}},{"exists":{"field":"field5"}},{"match":{"entity_name":{"query":"TaskSystem","operator":"and"}}}]}},"_source":["*","field3"],"aggs":{"groupdata":{"terms":{"field":"field3.keyword","size":10000},"aggs":{"value":{"avg":{"field":"field5"}}}}},"explain":true},"_source":["*","field3"],"from":0,"size":0}';
            $output = '{"took":7,"timed_out":false,"_shards":{"total":1,"successful":1,"skipped":0,"failed":0},"hits":{"total":{"value":3,"relation":"eq"},"max_score":null,"hits":[]},"aggregations":{"groupdata":{"doc_count_error_upper_bound":0,"sum_other_doc_count":0,"buckets":[{"key":"field3text","doc_count":2,"value":{"value":15}},{"key":"cfield3text","doc_count":1,"value":{"value":30}}]}}}';
            $this->setMockData($input, $output);
        }
        AuthContext::put(AuthConstants::ACCOUNT_ID, 1);
        $parameters = ['group'=>'field3','field'=>'field5','operation'=>'avg'];
        $results = $ae->runQuery('sampleapp', 'TaskSystem', $parameters);
        $results = $results['data'];
        $this->assertEquals($results[0]['field3'], "field3text");
        $this->assertEquals($results[0]['field5'], "15");
        $this->assertEquals($results[1]['field3'], "cfield3text");
        $this->assertEquals($results[1]['field5'], "30");
    }

    public function testCrmDataWithFilter()
    {
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngineImpl::class);
        $ae->setConfig($this->getApplicationConfig());
        if (enableElastic==0) {
            $this->markTestSkipped('Only Integration Test');
            $input = '{"index":"'.$this->index_pre.'crm_index","body":{"query":{"bool":{"must":[{"term":{"account_id":1}},{"exists":{"field":"_id"}},{"bool":{"must":[{"range":{"numberOfEmployees":{"lte":5}}},{"bool":{"should":[{"match":{"owner_username":{"query":"bharatg","operator":"and"}}},{"match":{"owner_username":{"query":"mehul","operator":"and"}}}]}}]}},{"match":{"entity_name":{"query":"Lead","operator":"and"}}}]}},"_source":["*"],"explain":true},"_source":["*"],"from":0,"size":0}';
            $output = '{"took":803,"timed_out":false,"_shards":{"total":1,"successful":1,"skipped":0,"failed":0},"hits":{"total":{"value":2,"relation":"eq"},"max_score":null,"hits":[]}}';
            $this->setMockData($input, $output);
        }
        AuthContext::put(AuthConstants::ACCOUNT_ID, 1);
        $parameters = ['filter'=>
                 [
                      ['numberOfEmployees','<=',5],
                        'AND',
                        [
                            ['owner_username','bharatg'],'OR',['owner_username','mehul']
                        ]
                ],'operation'=>'count'];
        $results = $ae->runQuery('crm', 'Lead', $parameters);
        $results = $results['data'];
        $this->assertEquals($results, 2);
    }


    public function testCrmComplexFilterNot()
    {
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngineImpl::class);
        $ae->setConfig($this->getApplicationConfig());
        if (enableElastic==0) {
            $this->markTestSkipped('Only Integration Test');
            $input = '{"index":"'.$this->index_pre.'crm_index","body":{"query":{"bool":{"must":[{"term":{"account_id":1}},{"exists":{"field":"_id"}},{"range":{"numberOfEmployees":{"lt":5}}},{"match":{"entity_name":{"query":"Lead","operator":"and"}}}]}},"_source":["*"],"explain":true},"_source":["*"],"from":0,"size":0}';
            $output = '{"took":1,"timed_out":false,"_shards":{"total":1,"successful":1,"skipped":0,"failed":0},"hits":{"total":{"value":1,"relation":"eq"},"max_score":null,"hits":[]}}';
            $this->setMockData($input, $output);
        }
        AuthContext::put(AuthConstants::ACCOUNT_ID, 1);
        $parameters = ['filter'=>[
                     ['numberOfEmployees','<',5]
                ],'operation'=>'count'];
        $results = $ae->runQuery('crm', 'Lead', $parameters);
        $results = $results['data'];
        $this->assertEquals($results, 1);
    }

    public function testCrmComplexFilterSymbols()
    {
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngineImpl::class);
        $ae->setConfig($this->getApplicationConfig());
        if (enableElastic==0) {
            $this->markTestSkipped('Only Integration Test');
            $input = '{"index":"'.$this->index_pre.'crm_index","body":{"query":{"bool":{"must":[{"term":{"account_id":1}},{"exists":{"field":"numberOfEmployees"}},{"bool":{"must":[{"range":{"numberOfEmployees":{"gt":4}}},{"range":{"numberOfEmployees":{"lt":10}}}]}},{"match":{"entity_name":{"query":"Lead","operator":"and"}}}]}},"_source":["*"],"aggs":{"value":{"sum":{"field":"numberOfEmployees"}}},"explain":true},"_source":["*"],"from":0,"size":0}';
            $output = '{"took":11,"timed_out":false,"_shards":{"total":1,"successful":1,"skipped":0,"failed":0},"hits":{"total":{"value":2,"relation":"eq"},"max_score":null,"hits":[]},"aggregations":{"value":{"value":13}}}';
            $this->setMockData($input, $output);
        }
        AuthContext::put(AuthConstants::ACCOUNT_ID, 1);
        $parameters = ['filter'=>[
                 
                     ['numberOfEmployees','>',4],'AND',
                     ['numberOfEmployees','<',10]
                ],'operation'=>'sum','field'=>'numberOfEmployees'];
        $results = $ae->runQuery('crm', 'Lead', $parameters);
        $results = $results['data'];
        $this->assertEquals($results, 13.0);
    }

    public function testCrmComplexFilterDate()
    {
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngineImpl::class);
        $ae->setConfig($this->getApplicationConfig());
        if (enableElastic==0) {
            $this->markTestSkipped('Only Integration Test');
            $input = '{"index":"'.$this->index_pre.'crm_index","body":{"query":{"bool":{"must":[{"term":{"account_id":1}},{"exists":{"field":"_id"}},{"bool":{"must":[{"range":{"createdAt":{"gte":"'.date("Y", strtotime("-1 year")).'-06-01","format":"yyyy-MM-dd"}}},{"range":{"createdAt":{"lte":"'.date("Y").'-07-15","format":"yyyy-MM-dd"}}}]}},{"match":{"entity_name":{"query":"Lead","operator":"and"}}}]}},"_source":["*"],"explain":true},"_source":["*"],"from":0,"size":0}';
            $output = '{"took":7,"timed_out":false,"_shards":{"total":1,"successful":1,"skipped":0,"failed":0},"hits":{"total":{"value":1,"relation":"eq"},"max_score":null,"hits":[]}}';
            $this->setMockData($input, $output);
        }
        AuthContext::put(AuthConstants::ACCOUNT_ID, 1);
        $parameters = ['filter'=>[
                    ['createdAt','>=','date:01 June Last Year'],'AND',
                    ['createdAt','<=','date:15 July This year']
                ],'operation'=>'count'];
        $results = $ae->runQuery('crm', 'Lead', $parameters);
        $query = $results['meta']['query'];
        $this->assertEquals($query, '{"index":"'.$this->index_pre.'crm_index","body":{"query":{"bool":{"must":[{"term":{"account_id":1}},{"exists":{"field":"_id"}},{"bool":{"must":[{"range":{"createdAt":{"gte":"'.date("Y", strtotime("-1 year")).'-06-01","format":"yyyy-MM-dd"}}},{"range":{"createdAt":{"lte":"2021-07-15","format":"yyyy-MM-dd"}}}]}},{"match":{"entity_name":{"query":"Lead","operator":"and"}}}]}},"_source":["*"],"aggs":{"value":{"value_count":{"field":"_id"}}},"explain":true},"_source":["*"],"from":0,"size":0}');
    }

    public function testCrmComplexFilterNotNoArray()
    {
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngineImpl::class);
        $ae->setConfig($this->getApplicationConfig());
        if (enableElastic==0) {
            $this->markTestSkipped('Only Integration Test');
            $input = '{"index":"'.$this->index_pre.'crm_index","body":{"query":{"bool":{"must":[{"term":{"account_id":1}},{"exists":{"field":"_id"}},{"bool":{"must_not":[{"term":{"owner_username":"bharatg"}}]}},{"match":{"entity_name":{"query":"Lead","operator":"and"}}}]}},"_source":["*"],"explain":true},"_source":["*"],"from":0,"size":0}';
            $output = '{"took":7,"timed_out":false,"_shards":{"total":1,"successful":1,"skipped":0,"failed":0},"hits":{"total":{"value":1,"relation":"eq"},"max_score":null,"hits":[]}}';
            $this->setMockData($input, $output);
        }
        AuthContext::put(AuthConstants::ACCOUNT_ID, 1);
        $parameters = ['filter'=>[
                     'owner_username','<>','bharatg'
                ],'operation'=>'count'];
        $results = $ae->runQuery('crm', 'Lead', $parameters);
        $results = $results['data'];
        $this->assertEquals($results, 1);
    }

    public function testExpressionWithGrouping()
    {
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngineImpl::class);
        $ae->setConfig($this->getApplicationConfig());
        if (enableElastic==0) {
            $this->markTestSkipped('Only Integration Test');
            $input = '{"index":"'.$this->index_pre.'11_test_index","body":{"query":{"bool":{"must":[{"term":{"account_id":1}},{"exists":{"field":"amount"}},{"range":{"date_created":{"gte":"2018-01-01","lte":"2018-12-12","format":"yyyy-MM-dd"}}}]}},"_source":["*","created_by"],"aggs":{"groupdata":{"terms":{"field":"created_by.keyword","size":10000},"aggs":{"value":{"sum":{"field":"amount"}}}}},"explain":true},"_source":["*","created_by"],"from":0,"size":0}';
            $output = '{"took":2,"timed_out":false,"_shards":{"total":1,"successful":1,"skipped":0,"failed":0},"hits":{"total":{"value":3,"relation":"eq"},"max_score":null,"hits":[]},"aggregations":{"groupdata":{"doc_count_error_upper_bound":0,"sum_other_doc_count":0,"buckets":[{"key":"John Doe","doc_count":2,"value":{"value":800}},{"key":"Mike Price","doc_count":1,"value":{"value":50.5}}]}}}';
            $this->setMockData($input, $output);
        }
        AuthContext::put(AuthConstants::ACCOUNT_ID, 1);
        $parameters = ['group'=>'created_by','field'=>'amount','operation'=>'sum','date-period'=>'2018-01-01/2018-12-12','date_type'=>'date_created','expression'=>'/10'];
        $results = $ae->runQuery('11_test', null, $parameters);
        $results = $results['data'];
        $this->assertEquals($results[0]['created_by'], "John Doe");
        $this->assertEquals($results[0]['amount'], "80");
        $this->assertEquals($results[1]['created_by'], "Mike Price");
        $this->assertEquals($results[1]['amount'], "5.05");
    }

    public function testRoundingWithGrouping()
    {
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngineImpl::class);
        $ae->setConfig($this->getApplicationConfig());
        if (enableElastic==0) {
            $this->markTestSkipped('Only Integration Test');
            $input = '{"index":"'.$this->index_pre.'11_test_index","body":{"query":{"bool":{"must":[{"term":{"account_id":1}},{"exists":{"field":"amount"}},{"range":{"date_created":{"gte":"2018-01-01","lte":"2018-12-12","format":"yyyy-MM-dd"}}}]}},"_source":["*","created_by"],"aggs":{"groupdata":{"terms":{"field":"created_by.keyword","size":10000},"aggs":{"value":{"sum":{"field":"amount"}}}}},"explain":true},"_source":["*","created_by"],"from":0,"size":0}';
            $output = '{"took":7,"timed_out":false,"_shards":{"total":1,"successful":1,"skipped":0,"failed":0},"hits":{"total":{"value":3,"relation":"eq"},"max_score":null,"hits":[]},"aggregations":{"groupdata":{"doc_count_error_upper_bound":0,"sum_other_doc_count":0,"buckets":[{"key":"John Doe","doc_count":2,"value":{"value":800}},{"key":"Mike Price","doc_count":1,"value":{"value":50.5}}]}}}';
            $this->setMockData($input, $output);
        }
        AuthContext::put(AuthConstants::ACCOUNT_ID, 1);
        $parameters = ['group'=>'created_by','field'=>'amount','operation'=>'sum','date-period'=>'2018-01-01/2018-12-12','date_type'=>'date_created','expression'=>'*23/53','round'=>'2'];
        $results = $ae->runQuery('11_test', null, $parameters);
        $results = $results['data'];
        $this->assertEquals($results[0]['created_by'], "John Doe");
        $this->assertEquals($results[0]['amount'], "347.17");
        $this->assertEquals($results[1]['created_by'], "Mike Price");
        $this->assertEquals($results[1]['amount'], "21.92");
    }

    public function testExpressionsNoGroups()
    {
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngineImpl::class);
        $ae->setConfig($this->getApplicationConfig());
        if (enableElastic==0) {
            $this->markTestSkipped('Only Integration Test');
            $input = '{"index":"'.$this->index_pre.'11_test_index","body":{"query":{"bool":{"must":[{"term":{"account_id":1}},{"exists":{"field":"amount"}},{"range":{"date_created":{"gte":"2018-01-01","lte":"2019-12-12","format":"yyyy-MM-dd"}}}]}},"_source":["*"],"aggs":{"value":{"sum":{"field":"amount"}}},"explain":true},"_source":["*"],"from":0,"size":0}';
            $output = '{"took":2,"timed_out":false,"_shards":{"total":1,"successful":1,"skipped":0,"failed":0},"hits":{"total":{"value":4,"relation":"eq"},"max_score":null,"hits":[]},"aggregations":{"value":{"value":950.5}}}';
            $this->setMockData($input, $output);
        }
        AuthContext::put(AuthConstants::ACCOUNT_ID, 1);
        $parameters = ['operation'=>'sum','field'=>'amount','date-period'=>'2018-01-01/2019-12-12','date_type'=>'date_created','expression'=>'*10'];
        $results = $ae->runQuery('11_test', null, $parameters);
        $results = $results['data'];
        $this->assertEquals($results, 9505);
    }

    // THE FOLLOWING NEED TO BE MOVED OUT

    public function testHubSubmissions()
    {
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngineImpl::class);
        $ae->setConfig($this->getApplicationConfig());
        if (enableElastic==0) {
            $this->markTestSkipped('Only Integration Test');
            $input = '{"index":"'.$this->index_pre.'diveinsurance_index","body":{"query":{"bool":{"must":[{"term":{"account_id":3}},{"exists":{"field":"workflow_name"}},{"bool":{"must":[{"match":{"workflow_name":{"query":"New Policy","operator":"and"}}},{"range":{"end_date":{"gt":"'.date("Y/m/d h:i:s").'"}}}]}}]}},"_source":["*"],"explain":true},"_source":["*"],"from":0,"size":0}';
            $output = '{"took":721,"timed_out":false,"_shards":{"total":1,"successful":1,"skipped":0,"failed":0},"hits":{"total":{"value":1,"relation":"eq"},"max_score":null,"hits":[]}}';
            $this->setMockData($input, $output);
        }
        AuthContext::put(AuthConstants::ACCOUNT_ID, 3);
        $parameters = ['filter'=>[
                    ['workflow_name','==','New Policy'],'AND',
                    ['end_date','>',date("Y/m/d h:i:s")]
                ],'operation'=>'count','field'=>'workflow_name'];
        $results = $ae->runQuery('diveinsurance', null, $parameters);
        $results = $results['data'];
        $this->assertEquals($results, 1);
    }

    public function testHubPolicies()
    {
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngineImpl::class);
        $ae->setConfig($this->getApplicationConfig());
        if (enableElastic==0) {
            $this->markTestSkipped('Only Integration Test');
            $input = '{"index":"'.$this->index_pre.'diveinsurance_index","body":{"query":{"bool":{"must":[{"term":{"account_id":3}},{"exists":{"field":"entity_id"}},{"range":{"end_date":{"gt":"'.date("Y/m/d h:i:s").'"}}}]}},"_source":["*"],"explain":true},"_source":["*"],"from":0,"size":0}';
            $output = '{"took":7,"timed_out":false,"_shards":{"total":1,"successful":1,"skipped":0,"failed":0},"hits":{"total":{"value":1,"relation":"eq"},"max_score":null,"hits":[]}}';
            $this->setMockData($input, $output);
        }
        AuthContext::put(AuthConstants::ACCOUNT_ID, 3);
        $parameters = ['filter'=>[
                    ['end_date','>',date("Y/m/d h:i:s")]
                ],'operation'=>'count','field'=>'entity_id'];
        $results = $ae->runQuery('diveinsurance', null, $parameters);
        $results = $results['data'];
        $this->assertEquals($results, 1);
    }

    public function testHubWrittenPremium()
    {
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngineImpl::class);
        $ae->setConfig($this->getApplicationConfig());
        if (enableElastic==0) {
            $this->markTestSkipped('Only Integration Test');
            $input = '{"index":"'.$this->index_pre.'diveinsurance_index","body":{"query":{"bool":{"must":[{"term":{"account_id":3}},{"exists":{"field":"total"}}]}},"_source":["*"],"aggs":{"value":{"sum":{"field":"total"}}},"explain":true},"_source":["*"],"from":0,"size":0}';
            $output = '{"took":7,"timed_out":false,"_shards":{"total":1,"successful":1,"skipped":0,"failed":0},"hits":{"total":{"value":2,"relation":"eq"},"max_score":null,"hits":[]},"aggregations":{"value":{"value":1887.5600280761719}}}';
            $this->setMockData($input, $output);
        }
        AuthContext::put(AuthConstants::ACCOUNT_ID, 3);
        $parameters = ['operation'=>'sum','field'=>'total','round'=>'2'];
        $results = $ae->runQuery('diveinsurance', null, $parameters);
        $results = $results['data'];
        $this->assertEquals($results, 1887.56);
    }

    public function tearDown()
    {
        parent::tearDown();
        if (enableElastic!=0) {
            $indexer=  $this->getApplicationServiceLocator()->get(Indexer::class);
            $return1=$indexer->delete('11_test_index', 'all');
            $return2=$indexer->delete('12_test_index', 'all');
            $return3=$indexer->delete('sampleapp_index', 'all');
        }
        //Mockery::close();
        $this->elasticService->setElasticClient($this->client);
    }
}
