<?php
namespace Analytics;

use Analytics\Controller\VisualizationController;
use Analytics\Model;
use Oxzion\Test\ControllerTest;
use Oxzion\Db\ModelTable;
use PHPUnit\DbUnit\TestCaseTrait;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use PHPUnit\Framework\TestResult;
use Zend\Db\Sql\Sql;
use Zend\Db\Adapter\Adapter;

class VisualizationControllerTest extends ControllerTest
{
    public function setUp() : void
    {
        $this->loadConfig();
        parent::setUp();
    }

    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__) . "/../Dataset/Visualization.yml");
        return $dataset;
    }

    protected function setDefaultAsserts()
    {
        $this->assertModuleName('Analytics');
        $this->assertControllerName(VisualizationController::class); // as specified in router's controller name alias
        $this->assertControllerClass('VisualizationController');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    }

    public function testCreate()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Line','configuration' => '{"data":"config"}','renderer' => 'chart','type' => 'chart'];
        $this->assertEquals(6, $this->getConnection()->getRowCount('ox_visualization'));
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/analytics/visualization', 'POST', $data);
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('visualization');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals(7, $this->getConnection()->getRowCount('ox_visualization'));
    }

    public function testCreateWithoutRequiredField()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => ''];
        $this->assertEquals(6, $this->getConnection()->getRowCount('ox_visualization'));
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/analytics/visualization', 'POST', $data);
        $this->assertResponseStatusCode(406);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('visualization');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Validation error(s).');
        $this->assertEquals($content['data']['errors']['name']['error'], 'required');
    }

    public function testUpdate()
    {
        $data = ['name' => "Pie", 'version' => 1];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/analytics/visualization/44f22a46-26d2-48df-96b9-c58520005817', 'PUT', null);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('visualization');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
    }

    public function testUpdateWithWrongVersion()
    {
        $data = ['name' => "Aggregate", 'version' => 3];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/analytics/visualization/44f22a46-26d2-48df-96b9-c58520005817', 'PUT', null);
        $this->assertResponseStatusCode(412);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('visualization');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Entity version sent by client does not match the version on server.');
    }

    public function testUpdateNotFound()
    {
        $data = ['name' => "test"];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/analytics/visualization/1000', 'PUT', null);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('visualization');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testGet()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/analytics/visualization/44f22a46-26d2-48df-96b9-c58520005817', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['uuid'], '44f22a46-26d2-48df-96b9-c58520005817');
        $this->assertEquals($content['data']['name'], 'Bar');
    }

    public function testGetNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/analytics/visualization/100', 'GET');
        $this->assertResponseStatusCode(404);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testGetList()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/analytics/visualization', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']['data']), 6);
        $this->assertEquals($content['data']['data'][3]['uuid'], '44f22a46-26d2-48df-96b9-c58520005817');
        $this->assertEquals($content['data']['data'][3]['name'], 'Bar');
        $this->assertEquals($content['data']['data'][4]['name'], 'Aggregate');
        $this->assertEquals($content['data']['data'][4]['uuid'], '101b3d1e-175b-43d8-ac38-485e80e6b2f3');
        $this->assertEquals($content['data']['total'], 6);
    }

    public function testGetListWithDeleted()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/analytics/visualization?show_deleted=true', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']['data']), 6);
        $this->assertEquals($content['data']['data'][3]['uuid'], '44f22a46-26d2-48df-96b9-c58520005817');
        $this->assertEquals($content['data']['data'][3]['name'], 'Bar');
        $this->assertEquals($content['data']['data'][3]['isdeleted'], 0);
        $this->assertEquals($content['data']['data'][4]['name'], 'Aggregate');
        $this->assertEquals($content['data']['data'][4]['uuid'], '101b3d1e-175b-43d8-ac38-485e80e6b2f3');
        $this->assertEquals($content['data']['total'], 6);
    }

    public function testGetListWithSort()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/analytics/visualization?filter=[{"sort":[{"field":"name","dir":"asc"}]}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']['data']), 6);
        $this->assertEquals($content['data']['data'][0]['uuid'], '101b3d1e-175b-43d8-ac38-485e80e6b2f3');
        $this->assertEquals($content['data']['data'][0]['name'], 'Aggregate');
        $this->assertEquals($content['data']['data'][1]['name'], 'Aggregate value');
        $this->assertEquals($content['data']['data'][1]['uuid'], '153f4f96-9b6c-47db-95b2-104af23e7522');
        $this->assertEquals($content['data']['total'], 6);
    }

    public function testGetListSortWithPageSize()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/analytics/visualization?filter=[{"sort":[{"field":"name","dir":"asc"}],"skip":1,"take":10}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']['data']), 5);
        $this->assertEquals($content['data']['data'][1]['uuid'], '44f22a46-26d2-48df-96b9-c58520005817');
        $this->assertEquals($content['data']['data'][1]['name'], 'Bar');
        $this->assertEquals($content['data']['data'][1]['is_owner'], 'true');
        $this->assertEquals($content['data']['total'], 6);
    }

    public function testGetListwithQueryParameters()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/analytics/visualization?filter=[{"filter":{"logic":"and","filters":[{"field":"name","operator":"endswith","value":"r"},{"field":"name","operator":"startswith","value":"b"}]},"sort":[{"field":"id","dir":"desc"}],"skip":0,"take":10}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']['data']), 1);
        $this->assertEquals($content['data']['data'][0]['uuid'], '44f22a46-26d2-48df-96b9-c58520005817');
        $this->assertEquals($content['data']['data'][0]['name'], 'Bar');
        $this->assertEquals($content['data']['total'], 1);
    }

    public function testDelete()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/analytics/visualization/44f22a46-26d2-48df-96b9-c58520005817?version=1', 'DELETE');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('visualization');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testDeleteNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/analytics/visualization/11111111-1111-1111-1111-111111111111?version=1', 'DELETE');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('visualization');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testDeleteWithWrongVersion()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/analytics/visualization/44f22a46-26d2-48df-96b9-c58520005817?version=3', 'DELETE');
        $this->assertResponseStatusCode(412);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('visualization');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Entity version sent by client does not match the version on server.');
    }
}
