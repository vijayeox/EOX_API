<?php
namespace App;

use App\Controller\EntityController;
use Mockery;
use Oxzion\Test\ControllerTest;
use Oxzion\Workflow\WorkflowFactory;
use PHPUnit\DbUnit\DataSet\YamlDataSet;

class EntityControllerTest extends ControllerTest
{
    public function setUp(): void
    {
        $this->loadConfig();
        parent::setUp();
    }
    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__) . "/../../Dataset/Workflow.yml");
        return $dataset;
    }

    protected function setDefaultAsserts()
    {
        $this->assertModuleName('App');
        $this->assertControllerName(EntityController::class); // as specified in router's controller name alias
        $this->assertControllerClass('EntityController');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    }

    public function testGetList()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/entity', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(EntityController::class); // as specified in router's controller name alias
        $this->assertControllerClass('EntityController');
        $this->assertMatchedRouteName('appentity');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 1);
        $this->assertEquals($content['data'][0]['id'] > 0, true);
        $this->assertEquals($content['data'][0]['name'], 'entity1');
    }

    public function testGet()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/entity/1', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(EntityController::class); // as specified in router's controller name alias
        $this->assertControllerClass('EntityController');
        $this->assertMatchedRouteName('appentity');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], 'entity1');
        $this->assertEquals($content['data']['app_id'], 199);
    }

    public function testGetNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/entity/122', 'GET');
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('App');
        $this->assertControllerName(EntityController::class); // as specified in router's controller name alias
        $this->assertControllerClass('EntityController');
        $this->assertMatchedRouteName('appentity');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testCreate()
    {
        $this->initAuthToken($this->adminUser);
        $data = json_decode('{"name":"Entity Test1","description":"Entity Description"}', true);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/entity', 'POST', null);
        $this->assertResponseStatusCode(201);
        $this->assertModuleName('App');
        $this->assertControllerName(EntityController::class); // as specified in router's controller name alias
        $this->assertControllerClass('EntityController');
        $this->assertMatchedRouteName('appentity');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(isset($content['data']['id']), false);
        $this->assertEquals(isset($content['data']['uuid']), true);
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals($content['data']['description'], $data['description']);
        $query = "SELECT * from ox_app_entity where uuid = '".$content['data']['uuid']."'";
        $result = $this->executeQueryTest($query);
        $this->assertEquals(count($result), 1);
        $this->assertEquals($result[0]['name'], $data['name']);
        $this->assertEquals($result[0]['description'], $data['description']);
        $this->assertEquals($result[0]['app_id'], 199);
    }

    public function testCreateFailure()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['app_id' => 199];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/entity', 'POST', null);
        $this->assertResponseStatusCode(406);
        $this->assertModuleName('App');
        $this->assertControllerName(EntityController::class); // as specified in router's controller name alias
        $this->assertControllerClass('EntityController');
        $this->assertMatchedRouteName('appentity');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Validation error(s).');
        $this->assertEquals($content['data']['errors']['name']['error'], 'required');
    }

    public function testUpdate()
    {
        $this->initAuthToken($this->adminUser);
        $data = json_decode('{"name":"Entity23","description":"Entity Description","content":[{"content":"<div>Entity Content goes here!!!....</div>","type": "Document"},{"form_id":1,"type": "Form"}]}');
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/8ab30b2d-d1da-427a-8e40-bc954b2b0f87/entity/e13d0c68-98c9-11e9-adc5-308d99c9145b', 'PUT', null);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(EntityController::class); // as specified in router's controller name alias
        $this->assertControllerClass('EntityController');
        $this->assertMatchedRouteName('appentity');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data->name);
    }

    public function testUpdateNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Sample2', 'text' => 'Sample 2 Description'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/entity/122', 'PUT', null);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('App');
        $this->assertControllerName(EntityController::class); // as specified in router's controller name alias
        $this->assertControllerClass('EntityController');
        $this->assertMatchedRouteName('appentity');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Entity not found.');
    }

    public function testDelete()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/8ab30b2d-d1da-427a-8e40-bc954b2b0f87/entity/e13d0c68-98c9-11e9-adc5-308d99c9145b', 'DELETE');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(EntityController::class); // as specified in router's controller name alias
        $this->assertControllerClass('EntityController');
        $this->assertMatchedRouteName('appentity');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
    }

    public function testDeleteNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/entity/122', 'DELETE');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('App');
        $this->assertControllerName(EntityController::class); // as specified in router's controller name alias
        $this->assertControllerClass('EntityController');
        $this->assertMatchedRouteName('appentity');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Entity not found for the App');
    }
}
