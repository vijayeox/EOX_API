<?php
namespace FileIndexer;

use FileIndexer\Controller\FileIndexerController;
use Oxzion\Test\ControllerTest;
use Oxzion\Db\ModelTable;
use PHPUnit\DbUnit\TestCaseTrait;
use PHPUnit\DbUnit\DataSet\DefaultDataSet;
use PHPUnit\Framework\TestResult;
use Zend\Db\Sql\Sql;
use Zend\Db\Adapter\Adapter;
use Oxzion\Utils\RestClient;
use FileIndexer\Service\FileIndexerService;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Mockery;

class FileIndexerControllerTest extends ControllerTest
{
    private $config;

    public function setUp() : void
    {
        $this->loadConfig();
        $this->config = $this->getApplicationConfig();
        parent::setUp();
    }

    public function getDataSet()
    {
        $dataset = new YamlDataSet(realpath(dirname(__FILE__)."/../Dataset/WorkflowTest.yml"));
        $dataset->addYamlFile(dirname(__FILE__) . "/../../../User/test/Dataset/User.yml");
        return $dataset;
    }

    private function getMockRestClientForFileIndexerService()
    {
        $fileIndexerService = $this->getApplicationServiceLocator()->get(Service\FileIndexerService::class);
        $mockRestClient = Mockery::mock('Oxzion\Utils\RestClient');
        $fileIndexerService->setRestClient($mockRestClient);
        return $mockRestClient;
    }

    public function getMockMessageProducer()
    {
        $fileIndexerService = $this->getApplicationServiceLocator()->get(Service\FileIndexerService::class);
        $mockMessageProducer = Mockery::mock('Oxzion\Messaging\MessageProducer');
        $fileIndexerService->setMessageProducer($mockMessageProducer);
        return $mockMessageProducer;
    }

    protected function setDefaultAsserts()
    {
        $this->assertModuleName('FileIndexer');
        $this->assertControllerName(FileIndexerController::class); // as specified in router's controller name alias
        $this->assertControllerClass('FileIndexerController');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    }

    public function testCreate()
    {
        //Scenario where both workflow_instance_id and activity_instance_id exists
        $this->initAuthToken($this->adminUser);
        $data = ['id' => 102];
        $this->dispatch('/fileindexer', 'POST', $data);
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(Mockery::any(), 'elastic')->once()->andReturn();
        }
        if (enableElastic==0) {
            $mockRestClient = $this->getMockRestClientForFileIndexerService();
            $mockRestClient->expects('get')->with("localhost:".$this->config['elasticsearch']['port']."/sampleapp_index/_doc/102")->once()->andReturn(array("body" => json_encode(array(
              '_index' => 'sampleapp_index',
              '_type' => '_doc',
              '_id' => '102',
              '_version' => 1,
              '_seq_no' => 0,
              '_primary_term' => 1,
              'found' => true,
              '_source' =>
              array(
                'id' => '102',
                'app_name' => 'SampleApp',
                'entity_id' => '1',
                'entity_name' => 'sampleEntity1',
                'file_uuid' => 'd13d0c68-98c9-11e9-adc5-308d99c9145c',
                'is_active' => '1',
                'account_id' => '1',
                'fields' => '{"field1" : "field1text","field2" : "field2text","field3" : "field3text","field4" : "field4text"}',
                'field3' => 3,
                'field4' => 4,
            ),
          ))));
        }
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('index');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['id'], $data['id']);
        //$this->assertEquals($content['data']['entity_name'], 'sampleEntity1');
        $this->assertEquals($content['data']['fields'], '{"field1" : "field1text","field2" : "field2text","field3" : "field3text","field4" : "field4text"}');
    }

    public function testCreateScenario2()
    {
        //Scenario where workflow_instance_id exists and activity_instance_id does not
        $this->initAuthToken($this->adminUser);
        $data = ['id' => 101];
        $this->dispatch('/fileindexer', 'POST', $data);
        if (enableElastic==0) {
            $mockRestClient = $this->getMockRestClientForFileIndexerService();
            $mockRestClient->expects('get')->with("localhost:".$this->config['elasticsearch']['port']."/sampleapp_index/_doc/101")->once()->andReturn(array("body" => json_encode(array(
              '_index' => 'sampleapp_index',
              '_type' => '_doc',
              '_id' => '101',
              '_version' => 5,
              '_seq_no' => 14,
              '_primary_term' => 1,
              'found' => true,
              '_source' =>
              array(
                'id' => '101',
                'app_name' => 'SampleApp',
                'entity_id' => '1',
                'entity_name' => 'sampleEntity1',
                'file_uuid' => 'd13d0c68-98c9-11e9-adc5-308d99c9145b',
                'is_active' => '1',
                'parent_id' => null,
                'account_id' => '1',
                'fields' => '{"field1" : "field1text","field2" : "field2text","field3" : "field3text","field4" : "field4text"}',
                'field1' => 3,
                'field2' => 4,
            ),
          ))));
        }
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(Mockery::any(), '/topic/elastic')->once()->andReturn();
        }
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('index');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['id'], $data['id']);
        $this->arrayHasKey($content['data']['account_id'], 1);
//        $this->assertEquals($content['data']['entity_name'], 'sampleEntity1');
    }

    public function testCreateScenario3()
    {
        //scenario where both workflow_instance_id and activity_instance_id does not
        $this->initAuthToken($this->adminUser);
        $data = ['id' => 103];
        $this->dispatch('/fileindexer', 'POST', $data);
        if (enableElastic==0) {
            $mockRestClient = $this->getMockRestClientForFileIndexerService();
            $mockRestClient->expects('get')->with("localhost:".$this->config['elasticsearch']['port']."/sampleapp_index/_doc/103")->once()->andReturn(array("body" => json_encode(array(
              '_index' => 'sampleapp_index',
              '_type' => '_doc',
              '_id' => '103',
              '_version' => 5,
              '_seq_no' => 43,
              '_primary_term' => 1,
              'found' => true,
              '_source' =>
              array(
                'id' => '103',
                'app_name' => 'SampleApp',
                'entity_id' => '1',
                'entity_name' => 'sampleEntity1',
                'file_uuid' => 'd13d0c68-98c9-11e9-adc5-308d99c9145d',
                'is_active' => '1',
                'account_id' => '1',
                'fields' => '{"field1" : "field1text","field2" : "field2text","field3" : "field3text","field4" : "field4text"}',
                'field4' => 4,
            ),
          ))));
        }
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(Mockery::any(), '/topic/elastic')->once()->andReturn();
        }
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('index');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['id'], $data['id']);
        //$this->assertEquals($content['data']['entity_name'], 'sampleEntity1');
        $this->assertEquals($content['data']['field4'], 4);
    }

    public function testCreateScenario4()
    {
        //Scenario where file id does not exist
        $this->initAuthToken($this->adminUser);
        $data = ['id' => 35];
        $this->dispatch('/fileindexer', 'POST', $data);
        if (enableElastic==0) {
            $mockRestClient = $this->getMockRestClientForFileIndexerService();
            $mockRestClient->expects('get')->with("localhost:".$this->config['elasticsearch']['port']."/sampleapp_index/_doc/35")->once()->andReturn(array("body" => json_encode(array(
              '_index' => 'sampleapp_index',
              '_type' => '_doc',
              '_id' => '35',
              'found' => false
          ))));
        }
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(Mockery::any(), '/topic/elastic')->once()->andReturn();
        }
        $this->assertResponseStatusCode(400);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('index');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Failure to Index File ');
    }

    public function testDelete()
    {
        //Scenario where file id does not exist
        $this->initAuthToken($this->adminUser);
        $data = ['id' => 101];
        $this->dispatch('/fileindexer/remove', 'POST', $data);
        if (enableElastic==0) {
            $mockRestClient = $this->getMockRestClientForFileIndexerService();
            $mockRestClient->expects('get')->with("localhost:".$this->config['elasticsearch']['port']."/sampleapp_index/_doc/1")->once()->andReturn(array("body" => json_encode(array(
              '_index' => 'sampleapp_index',
              '_type' => '_doc',
              '_id' => '101',
              'found' => false,
              ))));
        } 
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(Mockery::any(), '/topic/elastic')->once()->andReturn();
        }
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('deleteIndex');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['fileId'], 101);
    }

    public function testDeleteWithWrongId()
    {
        //Scenario where file id does not exist
        $this->initAuthToken($this->adminUser);
        $data = ['id' => 10];
        $this->dispatch('/fileindexer/remove', 'POST', $data);
        if (enableElastic==0) {
            $mockRestClient = $this->getMockRestClientForFileIndexerService();
            $mockRestClient->expects('get')->with("localhost:".$this->config['elasticsearch']['port']."/sampleapp_index/_doc/10")->once()->andReturn(array("body" => json_encode(array(
              '_index' => 'sampleapp_index',
              '_type' => '_doc',
              '_id' => '1',
              'found' => false,
              ))));
        }
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(Mockery::any(), '/topic/elastic')->once()->andReturn();
        }
        $this->assertResponseStatusCode(400);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('deleteIndex');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Failure to Delete File ');
    }

    public function testBatchIndex()
    {
        //Only start date is provided
        $this->initAuthToken($this->adminUser);
        $data = ["app_id" => "5965c47d-7bc8-4ae6-ab6c-916c8d78e10f","start_date" => "2019-12-19 11:03:08",];
        $this->dispatch('/fileindexer/batch', 'POST', $data);
        if (enableElastic==0) {
            $mockRestClient = $this->getMockRestClientForFileIndexerService();
            $mockRestClient->expects('get')->with("localhost:".$this->config['elasticsearch']['port']."/sampleapp_index/_doc/102")->once()->andReturn(array("body" => json_encode(array(
              '_index' => 'sampleapp_index',
              '_type' => '_doc',
              '_id' => '102',
              '_version' => 1,
              '_seq_no' => 0,
              '_primary_term' => 1,
              'found' => true,
              '_source' =>
              array(
                'id' => '102',
                'app_name' => 'SampleApp',
                'entity_id' => '1',
                'entity_name' => 'sampleEntity1',
                'file_uuid' => 'd13d0c68-98c9-11e9-adc5-308d99c9145c',
                'is_active' => '1',
                'account_id' => '1',
                'fields' => '{"field1" : "field1text","field2" : "field2text","field3" : "field3text","field4" : "field4text"}',
                'user_id' => null,
                'workflow_instance_id' => '1',
                'status' => 'In Progress',
                'activity_instance_id' => '[activityInstanceId]',
                'workflow_name' => 'Test Workflow 1',
                'activities' => '{"Task" : "In Progress","Test Form 2" : "In Progress"}',
                'field3' => 3,
                'field4' => 4,
            ),
            ))));
            $mockRestClient->expects('get')->with("localhost:".$this->config['elasticsearch']['port']."/sampleapp_index/_doc/103")->once()->andReturn(array("body" => json_encode(array(
              '_index' => 'sampleapp_index',
              '_type' => '_doc',
              '_id' => '103',
              '_version' => 5,
              '_seq_no' => 43,
              '_primary_term' => 1,
              'found' => true,
              '_source' =>
              array(
                'id' => '103',
                'app_name' => 'SampleApp',
                'entity_id' => '1',
                'name' => 'sampleEntity1',
                'file_uuid' => 'd13d0c68-98c9-11e9-adc5-308d99c9145d',
                'is_active' => '1',
                'account_id' => '1',
                'fields' => '{"field1" : "field1text","field2" : "field2text","field3" : "field3text","field4" : "field4text"}',
                'user_id' => null,
                'workflow_instance_id' => null,
                'status' => null,
                'activity_instance_id' => null,
                'workflow_name' => null,
                'activities' => null,
                'field4' => 4,
            ),
            ))));
        }
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(Mockery::any(), 'elastic')->once()->andReturn();
        }
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('batchindex');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['id'], 102);
        $this->assertEquals($content['data'][1]['id'], 103);
        $this->assertEquals($content['data'][0]['field3'], 3);
        $this->assertEquals($content['data'][1]['field4'], 4);
    }

    public function testBatchIndexScenario2()
    {
        //Only end_date is provided
        $this->initAuthToken($this->adminUser);
        $data = ["app_id" => "5965c47d-7bc8-4ae6-ab6c-916c8d78e10f","end_date" => "2020-02-19 11:03:08"];
        $this->dispatch('/fileindexer/batch', 'POST', $data);
        if (enableElastic==0) {
            $mockRestClient = $this->getMockRestClientForFileIndexerService();
            $mockRestClient->expects('get')->with("localhost:".$this->config['elasticsearch']['port']."/sampleapp_index/_doc/101")->once()->andReturn(array("body" => json_encode(array(
              '_index' => 'sampleapp_index',
              '_type' => '_doc',
              '_id' => '101',
              '_version' => 5,
              '_seq_no' => 14,
              '_primary_term' => 1,
              'found' => true,
              '_source' =>
              array(
                'id' => '101',
                'app_name' => 'SampleApp',
                'entity_id' => '1',
                'entity_name' => 'sampleEntity1',
                'file_uuid' => 'd13d0c68-98c9-11e9-adc5-308d99c9145b',
                'is_active' => '1',
                'account_id' => '1',
                'fields' => '{"field1" : "field1text","field2" : "field2text","field3" : "field3text","field4" : "field4text"}',
                'user_id' => null,
                'workflow_instance_id' => '1',
                'status' => 'In Progress',
                'activity_instance_id' => '[activityInstanceId]',
                'workflow_name' => 'Test Workflow 1',
                'activities' => '{"Task" : "In Progress","Test Form 2" : "In Progress"}',
                'field1' => 3,
                'field2' => 4,
            ),
            ))));
            $mockRestClient->expects('get')->with("localhost:".$this->config['elasticsearch']['port']."/sampleapp_index/_doc/102")->once()->andReturn(array("body" => json_encode(array(
              '_index' => 'sampleapp_index',
              '_type' => '_doc',
              '_id' => '102',
              '_version' => 1,
              '_seq_no' => 0,
              '_primary_term' => 1,
              'found' => true,
              '_source' =>
              array(
                'id' => '102',
                'app_name' => 'SampleApp',
                'entity_id' => '1',
                'entity_name' => 'sampleEntity1',
                'file_uuid' => 'd13d0c68-98c9-11e9-adc5-308d99c9145c',
                'is_active' => '1',
                'account_id' => '1',
                'fields' => '{"field1" : "field1text","field2" : "field2text","field3" : "field3text","field4" : "field4text"}',
                'user_id' => null,
                'workflow_instance_id' => '1',
                'status' => 'In Progress',
                'activity_instance_id' => '[activityInstanceId]',
                'workflow_name' => 'Test Workflow 1',
                'activities' => '{"Task" : "In Progress","Test Form 2" : "In Progress"}',
                'field3' => 3,
                'field4' => 4,
            ),
            ))));
        }
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(Mockery::any(), 'elastic')->once()->andReturn();
        }
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('batchindex');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['id'], 101);
        $this->assertEquals($content['data'][1]['id'], 102);
        $this->assertEquals($content['data'][1]['field3'], 3);
        $this->assertEquals($content['data'][0]['field1'], 3);
    }

    public function testBatchIndexScenario3()
    {
        //Both start_date and end_date is provided
        $this->initAuthToken($this->adminUser);
        $data = ["app_id" => "5965c47d-7bc8-4ae6-ab6c-916c8d78e10f","start_date" => "2019-12-19 11:03:08","end_date" => "2020-02-19 11:03:08"];
        $this->dispatch('/fileindexer/batch', 'POST', $data);
        if (enableElastic==0) {
            $mockRestClient = $this->getMockRestClientForFileIndexerService();
            $mockRestClient->expects('get')->with("localhost:".$this->config['elasticsearch']['port']."/sampleapp_index/_doc/102")->once()->andReturn(array("body" => json_encode(array(
              '_index' => 'sampleapp_index',
              '_type' => '_doc',
              '_id' => '102',
              '_version' => 1,
              '_seq_no' => 0,
              '_primary_term' => 1,
              'found' => true,
              '_source' =>
              array(
                'id' => '102',
                'app_name' => 'SampleApp',
                'entity_id' => '1',
                'entity_name' => 'sampleEntity1',
                'file_uuid' => 'd13d0c68-98c9-11e9-adc5-308d99c9145c',
                'is_active' => '1',
                'account_id' => '1',
                'fields' => '{"field1" : "field1text","field2" : "field2text","field3" : "field3text","field4" : "field4text"}',
                'user_id' => null,
                'workflow_instance_id' => '1',
                'status' => 'In Progress',
                'activity_instance_id' => '[activityInstanceId]',
                'workflow_name' => 'Test Workflow 1',
                'activities' => '{"Task" : "In Progress","Test Form 2" : "In Progress"}',
                'field3' => 3,
                'field4' => 4,
            ),
            ))));
        }
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(Mockery::any(), 'elastic')->once()->andReturn();
        }
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('batchindex');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['id'], 102);
        $this->assertEquals($content['data'][0]['field3'], 3);
        $this->assertEquals($content['data'][0]['entity_name'], 'sampleEntity1');
        $this->assertEquals($content['data'][0]['fields'], '{"field1" : "field1text","field2" : "field2text","field3" : "field3text","field4" : "field4text"}');
    }

    public function testBatchIndexScenario4()
    {
        //where file.latest = 0
        $this->initAuthToken($this->adminUser);
        $data = ["app_id" => "5965c47d-7bc8-4ae6-ab6c-916c8d78e10f","start_date" => "2019-12-19 11:03:08","end_date" => "2020-03-19 11:03:08"];
        $this->dispatch('/fileindexer/batch', 'POST', $data);
        if (enableElastic==0) {
            $mockRestClient = $this->getMockRestClientForFileIndexerService();
            $mockRestClient->expects('get')->with("localhost:".$this->config['elasticsearch']['port']."/sampleapp_index/_doc/102")->once()->andReturn(array("body" => json_encode(array(
              '_index' => 'sampleapp_index',
              '_type' => '_doc',
              '_id' => '102',
              '_version' => 1,
              '_seq_no' => 0,
              '_primary_term' => 1,
              'found' => true,
              '_source' =>
              array(
                'id' => '102',
                'app_name' => 'SampleApp',
                'entity_id' => '1',
                'entity_name' => 'sampleEntity1',
                'file_uuid' => 'd13d0c68-98c9-11e9-adc5-308d99c9145c',
                'is_active' => '1',
                'account_id' => '1',
                'fields' => '{"field1" : "field1text","field2" : "field2text","field3" : "field3text","field4" : "field4text"}',
                'user_id' => null,
                'workflow_instance_id' => '1',
                'status' => 'In Progress',
                'activity_instance_id' => '[activityInstanceId]',
                'workflow_name' => 'Test Workflow 1',
                'activities' => '{"Task" : "In Progress","Test Form 2" : "In Progress"}',
                'field3' => 3,
                'field4' => 4,
            ),
            ))));
            $mockRestClient->expects('get')->with("localhost:".$this->config['elasticsearch']['port']."/sampleapp_index/_doc/103")->once()->andReturn(array("body" => json_encode(array(
              '_index' => 'sampleapp_index',
              '_type' => '_doc',
              '_id' => '103',
              '_version' => 5,
              '_seq_no' => 43,
              '_primary_term' => 1,
              'found' => true,
              '_source' =>
              array(
                'id' => '103',
                'app_name' => 'SampleApp',
                'entity_id' => '1',
                'name' => 'sampleEntity1',
                'file_uuid' => 'd13d0c68-98c9-11e9-adc5-308d99c9145d',
                'is_active' => '1',
                'account_id' => '1',
                'fields' => '{"field1" : "field1text","field2" : "field2text","field3" : "field3text","field4" : "field4text"}',
                'user_id' => null,
                'workflow_instance_id' => null,
                'status' => null,
                'activity_instance_id' => null,
                'workflow_name' => null,
                'activities' => null,
                'field4' => 4,
            ),
            ))));
        }
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(Mockery::any(), 'elastic')->once()->andReturn();
        }
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('batchindex');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['id'], 102);
        $this->assertEquals($content['data'][1]['id'], 103);
        $this->assertEquals($content['data'][0]['field3'], 3);
        $this->assertEquals($content['data'][1]['field4'], 4);
    }

    public function testBatchIndexNotFound()
    {
        //Both start_date and end_date is provided
        $this->initAuthToken($this->adminUser);
        $data = ["app_id" => "ba33c8bb-29cc-4448-a5dc-7e6112225b01","start_date" => "2020-02-19 11:03:08"];
        $this->dispatch('/fileindexer/batch', 'POST', $data);
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(Mockery::any(), 'elastic')->once()->andReturn();
        }
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(400);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('batchindex');
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Failure to Index File ');
    }
}
