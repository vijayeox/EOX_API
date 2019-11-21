<?php
namespace Analytics;

use Oxzion\Test\ControllerTest;
use Oxzion\Db\ModelTable;
use PHPUnit\DbUnit\TestCaseTrait;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Zend\Db\Sql\Sql;
use Zend\Db\Adapter\Adapter;
use Oxzion\Analytics\AnalyticsEngine;
use Oxzion\Search;
use Oxzion\Search\Indexer;
use Oxzion\Analytics;
use PHPUnit\DbUnit\DataSet\SymfonyYamlParser;
use Oxzion\Test\MainControllerTest;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;

use function GuzzleHttp\json_encode;

class AnalyticsTest extends MainControllerTest
{
    private $dataset;
    private $searchFactory;
    private $analyticsFactory;

    public function setUp() : void
    {
        $this->loadConfig();
        parent::setUp();
        if(enableElastic!=0){
            $this->setSearchData();
            $config = $this->getApplicationConfig();
            $this->setupData();
            sleep (2) ;
        }
    }

    public function setSearchData()
    {
        $parser = new SymfonyYamlParser();
        $this->dataset = $parser->parseYaml(dirname(__FILE__)."/Dataset/Analytics.yml");
    }

    public function createIndex($indexer,$body) {
        $app_name = $body['app_name'];
        $id = $body['id'];
        AuthContext::put(AuthConstants::ORG_ID, $body['org_id']);
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

    public function testGrouping() {
        if(enableElastic==0){
            $this->markTestSkipped('Only Integration Test');
        }
        AuthContext::put(AuthConstants::ORG_ID, 1);
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngine::class);
        $parameters = ['group'=>'created_by','field'=>'amount','operation'=>'sum','date-period'=>'2018-01-01/2018-12-12','date_type'=>'date_created'];
        $results = $ae->runQuery('11_test', null, $parameters);
        $results = $results['data'];
        $this->assertEquals($results[0]['name'], "John Doe");
        $this->assertEquals($results[0]['value'], "800");
        $this->assertEquals($results[1]['name'], "Mike Price");
        $this->assertEquals($results[1]['value'], "50.5");
    }

    public function testDoubleGrouping() {
        if(enableElastic==0){
            $this->markTestSkipped('Only Integration Test');
        }
        AuthContext::put(AuthConstants::ORG_ID, 1);
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngine::class);
        $parameters = ['group'=>'created_by,category','field'=>'amount','operation'=>'sum','date-period'=>'2018-01-01/2018-12-12','date_type'=>'date_created'];
        $results = $ae->runQuery('11_test', null, $parameters);
        $results = $results['data'];
        $this->assertEquals($results[0]['name'], "John Doe - A");
        $this->assertEquals($results[0]['value'], "200");
        $this->assertEquals($results[2]['name'], "Mike Price - A");
        $this->assertEquals($results[2]['value'], "50.5");
    }

    public function testDoubleGroupingCount() {
        if(enableElastic==0){
            $this->markTestSkipped('Only Integration Test');
        }
        AuthContext::put(AuthConstants::ORG_ID, 1);
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngine::class);
        $parameters = ['group'=>'created_by,category','field'=>'amount','operation'=>'count','date-period'=>'2018-01-01/2018-12-12','date_type'=>'date_created'];
        $results = $ae->runQuery('11_test', null, $parameters);
        $results = $results['data'];
        $this->assertEquals($results[0]['name'], "John Doe - A");
        $this->assertEquals($results[0]['value'], "1");
        $this->assertEquals($results[2]['name'], "Mike Price - A");
        $this->assertEquals($results[2]['value'], "1");
    }

    public function testLists() {
        if(enableElastic==0){
            $this->markTestSkipped('Only Integration Test');
        }
        AuthContext::put(AuthConstants::ORG_ID, 1);
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngine::class);
        $parameters = ['field'=>'amount','date-period'=>'2018-01-01/2019-12-12','date_type'=>'date_created','list'=>'name,created_by,category'];
        $results = $ae->runQuery('11_test', null, $parameters);
        $results = $results['data'];
        $this->assertEquals($results[0]['name'], "test document");
        $this->assertEquals($results[0]['category'], "A");
        $this->assertEquals($results[0]['created_by'], "John Doe");
    }

    public function testAggregatesNoGroups() {
        if(enableElastic==0){
            $this->markTestSkipped('Only Integration Test');
        }
        AuthContext::put(AuthConstants::ORG_ID, 1);
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngine::class);
        $parameters = ['operation'=>'sum','field'=>'amount','date-period'=>'2018-01-01/2019-12-12','date_type'=>'date_created'];
        $results = $ae->runQuery('11_test', null, $parameters);
        $results = $results['data'];

        $this->assertEquals($results,950.5);
    }

    public function testOnlyFilters() {
        if(enableElastic==0){
            $this->markTestSkipped('Only Integration Test');
        }
        AuthContext::put(AuthConstants::ORG_ID, 1);
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngine::class);
        $parameters = ['date-period'=>'2018-01-01/2019-12-12','date_type'=>'date_created'];
        $results = $ae->runQuery('11_test', null, $parameters);
        $results = $results['data'];
        $this->assertEquals($results, 4);
    }

    public function testDefaultField() {
        if(enableElastic==0){
            $this->markTestSkipped('Only Integration Test');
        }
        AuthContext::put(AuthConstants::ORG_ID, 1);
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngine::class);
        $parameters = ['group'=>'created_by','operation'=>'count','date-period'=>'2018-01-01/2019-12-12','date_type'=>'date_created'];
        $results = $ae->runQuery('11_test', null, $parameters);
        $results = $results['data'];
        $this->assertEquals($results[0]['name'], "John Doe");
        $this->assertEquals($results[0]['value'], "3");
        $this->assertEquals($results[1]['name'], "Mike Price");
        $this->assertEquals($results[1]['value'], "1");
    }

    public function testWorkflowData() {
        if(enableElastic==0){
            $this->markTestSkipped('Only Integration Test');
        }
        AuthContext::put(AuthConstants::ORG_ID, 1);
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngine::class);
        $parameters = ['group'=>'field3','field'=>'field5','operation'=>'avg'];
        $results = $ae->runQuery('sampleapp', 'TaskSystem', $parameters);
        $results = $results['data'];
        $this->assertEquals($results[0]['name'], "field3text");
        $this->assertEquals($results[0]['value'], "15");
        $this->assertEquals($results[1]['name'], "cfield3text");
        $this->assertEquals($results[1]['value'], "30");
    }

    public function testCrmDataWithFilter() {
        if(enableElastic==0){
            $this->markTestSkipped('Only Integration Test');
        }
        AuthContext::put(AuthConstants::ORG_ID, 1);
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngine::class);
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


    public function testCrmComplexFilterNot() {
        if(enableElastic==0){
            $this->markTestSkipped('Only Integration Test');
        }
        AuthContext::put(AuthConstants::ORG_ID, 1);
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngine::class);
        $parameters = ['filter'=>[
                     ['numberOfEmployees','<',5]
                ],'operation'=>'count'];
        $results = $ae->runQuery('crm', 'Lead', $parameters);
        $results = $results['data'];
        $this->assertEquals($results, 1);
    }

    public function testCrmComplexFilterSymbols() {
        if(enableElastic==0){
            $this->markTestSkipped('Only Integration Test');
        }
        AuthContext::put(AuthConstants::ORG_ID, 1);
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngine::class);
        $parameters = ['filter'=>[
                 
                     ['numberOfEmployees','>',4],'AND',
                     ['numberOfEmployees','<',10]
                ],'operation'=>'sum','field'=>'numberOfEmployees'];
        $results = $ae->runQuery('crm', 'Lead', $parameters);
        $results = $results['data'];
        $this->assertEquals($results, 13.0);
    }

    public function testCrmComplexFilterNotNoArray() {
        if(enableElastic==0){
            $this->markTestSkipped('Only Integration Test');
        }
        AuthContext::put(AuthConstants::ORG_ID, 1);
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngine::class);
        $parameters = ['filter'=>[
                     'owner_username','<>','bharatg'
                ],'operation'=>'count'];
        $results = $ae->runQuery('crm', 'Lead', $parameters);
        $results = $results['data'];
        $this->assertEquals($results, 1);
    }

    public function tearDown()
    {
         parent::tearDown();
         if(enableElastic!=0){
            $indexer=  $this->getApplicationServiceLocator()->get(Indexer::class);
            $return1=$indexer->delete('11_test_index','all');
            $return2=$indexer->delete('12_test_index','all');
            $return3=$indexer->delete('sampleapp_index','all');
        }
    }

    public function testExpressionWithGrouping() {
        if(enableElastic==0){
            $this->markTestSkipped('Only Integration Test');
        }
        AuthContext::put(AuthConstants::ORG_ID, 1);
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngine::class);
        $parameters = ['group'=>'created_by','field'=>'amount','operation'=>'sum','date-period'=>'2018-01-01/2018-12-12','date_type'=>'date_created','expression'=>'/10'];
        $results = $ae->runQuery('11_test', null, $parameters);
        $results = $results['data'];
        $this->assertEquals($results[0]['name'], "John Doe");
        $this->assertEquals($results[0]['value'], "80");
        $this->assertEquals($results[1]['name'], "Mike Price");
        $this->assertEquals($results[1]['value'], "5.05");
    }

    public function testRoundingWithGrouping() {
        if(enableElastic==0){
            $this->markTestSkipped('Only Integration Test');
        }
        AuthContext::put(AuthConstants::ORG_ID, 1);
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngine::class);
        $parameters = ['group'=>'created_by','field'=>'amount','operation'=>'sum','date-period'=>'2018-01-01/2018-12-12','date_type'=>'date_created','expression'=>'*23/53','round'=>'2'];
        $results = $ae->runQuery('11_test', null, $parameters);
        $results = $results['data'];
        $this->assertEquals($results[0]['name'], "John Doe");
        $this->assertEquals($results[0]['value'], "347.17");
        $this->assertEquals($results[1]['name'], "Mike Price");
        $this->assertEquals($results[1]['value'], "21.92");
    }

    public function testExpressionsNoGroups() {
        if(enableElastic==0){
            $this->markTestSkipped('Only Integration Test');
        }
        AuthContext::put(AuthConstants::ORG_ID, 1);
        $ae = $this->getApplicationServiceLocator()->get(AnalyticsEngine::class);
        $parameters = ['operation'=>'sum','field'=>'amount','date-period'=>'2018-01-01/2019-12-12','date_type'=>'date_created','expression'=>'*10'];
        $results = $ae->runQuery('11_test', null, $parameters);
        $results = $results['data'];
        $this->assertEquals($results,9505);
    }

}