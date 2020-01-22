<?php
namespace Analytics;

use Analytics\Controller\QueryController;
use Oxzion\Test\ControllerTest;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use PHPUnit\DbUnit\DataSet\SymfonyYamlParser;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Oxzion\Search\Indexer;
use Mockery;


class QueryControllerTest extends ControllerTest
{

    public function setUp() : void
    {
        $this->loadConfig();
        parent::setUp();
    }

    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__) . "/../Dataset/DataSource.yml");
        $dataset->addYamlFile(dirname(__FILE__) . "/../Dataset/Query.yml");
        return $dataset;
    }

    public function tearDown()  : void {
        parent::tearDown();
        Mockery::close();
    }

    public function createIndex($indexer, $body)
    {
        $entity_name = 'test';
        $app_name = $body['app_name'];
        $id = $body['id'];
        AuthContext::put(AuthConstants::ORG_ID, $body['org_id']);
        $return=$indexer->index($app_name, $id, $entity_name, $body);
    }


    private function setMockData($input,$output)
    {
            $mock =  Mockery::mock('overload:Elasticsearch\ClientBuilder');
            $mock->shouldReceive('create')
            ->once()
            ->andReturn(0);
            $mock->shouldReceive('search')
            ->once()
   //         ->with($input)
            ->andReturn($output);
    }

    public function setElasticData()
    {
        $parser = new SymfonyYamlParser();
        $eDataset = $parser->parseYaml(dirname(__FILE__)."/../Dataset/Elastic.yml");
        $indexer=  $this->getApplicationServiceLocator()->get(Indexer::class);
        $dataset = $eDataset['ox_elastic'];
        foreach ($dataset as $body) {
            $this->createIndex($indexer, $body);
        }
        sleep(2);
    }

    protected function setDefaultAsserts()
    {
        $this->assertModuleName('Analytics');
        $this->assertControllerName(QueryController::class); // as specified in router's controller name alias
        $this->assertControllerClass('QueryController');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    }

    public function testCreate()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => "query5", 'datasource_id' => 'd08d06ce-0cae-47e7-9c4f-a6716128a303', 'configuration' => '{"date_type":"date_created","date-period":"2018-01-01/now","operation":"sum","group":"created_by","field":"amount"}', 'ispublic' => 1];
        $this->assertEquals(15, $this->getConnection()->getRowCount('ox_query'));
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/analytics/query', 'POST', $data);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('query');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals($content['data']['datasource_id'], $data['datasource_id']);
        $this->assertEquals($content['data']['configuration'], $data['configuration']);
        $this->assertEquals(16, $this->getConnection()->getRowCount('ox_query'));
    }

    public function testCreateWithoutRequiredField()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => "query5", 'configuration' => '{"date_type":"date_created","date-period":"2018-01-01/now","operation":"sum","group":"created_by","field":"amount"}'];
        $this->assertEquals(15, $this->getConnection()->getRowCount('ox_query'));
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/analytics/query', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('query');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Validation Errors');
        $this->assertEquals($content['data']['errors']['datasource_id'], 'required');
    }

    public function testUpdate()
    {
        $data = ['name' => "querytest", 'datasource_id' => '7700c623-1361-4c85-8203-e255ac995c4a', 'version' => 1];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/analytics/query/8f1d2819-c5ff-4426-bc40-f7a20704a738', 'PUT', null);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('query');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals($content['data']['datasource_id'], $data['datasource_id']);
    }

    public function testUpdateWithWrongVersion()
    {
        $data = ['name' => "Analytics", 'type' => 'Elastic' , 'version' => 3];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/analytics/query/8f1d2819-c5ff-4426-bc40-f7a20704a738', 'PUT', null);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('query');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Version changed');
    }

    public function testUpdateNotFound()
    {
        $data = ['name' => "querytest", 'datasource_id' => 2, 'version' => 1];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/analytics/query/1000', 'PUT', null);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('query');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testDelete()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/analytics/query/8f1d2819-c5ff-4426-bc40-f7a20704a738?version=1', 'DELETE');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('query');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testDeleteWithWrongVersion()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/analytics/query/8f1d2819-c5ff-4426-bc40-f7a20704a738?version=3', 'DELETE');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('query');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Version changed');
    }

    public function testDeleteNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/analytics/query/10000', 'DELETE');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('query');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testGet() {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/analytics/query/8f1d2819-c5ff-4426-bc40-f7a20704a738', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['query']['uuid'], '8f1d2819-c5ff-4426-bc40-f7a20704a738');
        $this->assertEquals($content['data']['query']['name'], 'query1');
    }

    // public function testGetHub() {
    //     $this->initAuthToken($this->adminUser);
    //     $this->dispatch('/analytics/query/e6de79cb-3148-11ea-98ba-283a4d5d1bdb?data=true', 'GET');
    //     $this->assertResponseStatusCode(200);
    //     $this->setDefaultAsserts();
    //     $content = json_decode($this->getResponse()->getContent(), true);
    //     $this->assertEquals($content['status'], 'success');
    //     $this->assertEquals($content['data']['query']['uuid'], '8f1d2819-c5ff-4426-bc40-f7a20704a738');
    //     $this->assertEquals($content['data']['query']['name'], 'query1');
    // }

    public function testGetWithResults() {
        if (enableElastic!=0) {
            $this->setElasticData();
        } else {
            $input = json_decode('{"index":"diveinsurance_index","body":{"query":{"bool":{"must":[{"term":{"org_id":1}},{"exists":{"field":"total"}},{"range":{"start_date":{"gte":"2018-01-01","lte":"2019-12-27","format":"yyyy-MM-dd"}}}]}},"_source":["*"],"aggs":{"groupdata":{"date_histogram":{"field":"start_date","interval":"month","format":"MMM-yyyy"},"aggs":{"value":{"sum":{"field":"total"}}}}},"explain":true},"_source":["*"],"from":0,"size":0}',true);
            $output = json_decode('{"took":2,"timed_out":false,"_shards":{"total":1,"successful":1,"skipped":0,"failed":0},"hits":{"total":{"value":4,"relation":"eq"},"max_score":null,"hits":[]},"aggregations":{"groupdata":{"buckets":[{"key_as_string":"Apr-2019","key":1554076800000,"doc_count":1,"value":{"value":890}},{"key_as_string":"May-2019","key":1556668800000,"doc_count":1,"value":{"value":400.7799987792969}},{"key_as_string":"Jun-2019","key":1559347200000,"doc_count":0,"value":{"value":0}},{"key_as_string":"Jul-2019","key":1561939200000,"doc_count":0,"value":{"value":0}},{"key_as_string":"Aug-2019","key":1564617600000,"doc_count":1,"value":{"value":1486.780029296875}},{"key_as_string":"Sep-2019","key":1567296000000,"doc_count":1,"value":{"value":600.780029296875}}]}}}',true);
            $this->setMockData($input,$output);
        }
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/analytics/query/6f1d2819-c5ff-2326-bc40-f7a20704a748?data=true', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['query']['data'][0]['period-month'], 'Apr-2019');
        $this->assertEquals($content['data']['query']['data'][0]['total'], 890);
    }


    public function testGetNotFound() {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/analytics/query/100', 'GET');
        $this->assertResponseStatusCode(404);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testGetList()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/analytics/query', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']['data']), 15);
        $this->assertEquals($content['data']['data'][6]['uuid'], '8f1d2819-c5ff-4426-bc40-f7a20704a738');
        $this->assertEquals($content['data']['data'][6]['name'], 'query1');
        $this->assertEquals($content['data']['data'][7]['datasource_id'], 3);
        $this->assertEquals($content['data']['data'][7]['name'], 'query2');
        $this->assertEquals($content['data']['total'],15);
    }

    public function testGetListWithDeleted()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/analytics/query?show_deleted=true', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']['data']), 15);
        $this->assertEquals($content['data']['data'][6]['uuid'], '8f1d2819-c5ff-4426-bc40-f7a20704a738');
        $this->assertEquals($content['data']['data'][6]['name'], 'query1');
        $this->assertEquals($content['data']['data'][6]['isdeleted'], 0);
        $this->assertEquals($content['data']['data'][7]['datasource_id'], 3);
        $this->assertEquals($content['data']['data'][7]['name'], 'query2');
        $this->assertEquals($content['data']['total'],15);
    }

    public function testGetListWithSort()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/analytics/query?sort=[{"field":"name","dir":"desc"}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']['data']), 15);
        $this->assertEquals($content['data']['data'][5]['uuid'], '1a7d9e0d-f6cd-40e2-9154-87de247b9ce1');
        $this->assertEquals($content['data']['data'][5]['name'], 'query3');
        $this->assertEquals($content['data']['data'][6]['ispublic'], 1);
        $this->assertEquals($content['data']['data'][6]['name'], 'query2');
        $this->assertEquals($content['data']['total'],15);
    }

     public function testGetListSortWithPageSize()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/analytics/query?skip=1&limit=10&sort=[{"field":"name","dir":"asc"}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']['data']), 10);
        $this->assertEquals($content['data']['data'][2]['uuid'], '6f1d2819-c5ff-2326-bc40-f7a20704a748');
        $this->assertEquals($content['data']['data'][2]['name'], 'hub 3');
        $this->assertEquals($content['data']['data'][2]['is_owner'], 'true');
        $this->assertEquals($content['data']['total'],15);
    }

    public function testGetListwithQueryParameters()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/analytics/query?limit=10&sort=[{"field":"name","dir":"desc"}]&filter=[{"logic":"and"},{"filters":[{"field":"name","operator":"endswith","value":"3"},{"field":"name","operator":"startswith","value":"q"}]}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']['data']), 1);
        $this->assertEquals($content['data']['data'][0]['uuid'], '1a7d9e0d-f6cd-40e2-9154-87de247b9ce1');
        $this->assertEquals($content['data']['data'][0]['name'], 'query3');
        $this->assertEquals($content['data']['total'],1);
    }

}