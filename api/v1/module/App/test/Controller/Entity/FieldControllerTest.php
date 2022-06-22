<?php
namespace App;

use App\Controller\FieldController;
use Oxzion\Test\ControllerTest;
use PHPUnit\DbUnit\DataSet\YamlDataSet;

class FieldControllerTest extends ControllerTest
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

    private function getFieldUuid()
    {
        $selctQuery = "SELECT * from ox_field where id=1";
        $selectResult = $this->executeQueryTest($selctQuery);
        return $selectResult;
    }

    protected function setDefaultAsserts()
    {
        $this->assertModuleName('App');
        $this->assertControllerName(FieldController::class); // as specified in router's controller name alias
        $this->assertControllerClass('FieldController');
        $this->assertMatchedRouteName('appfield');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    }

    public function testGetList()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/field', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 10);
        $this->assertEquals($content['data'][0]['id'] > 0, true);
        $this->assertEquals($content['data'][0]['name'], 'field1');
        $this->assertEquals($content['data'][1]['id'] > 1, true);
        $this->assertEquals($content['data'][1]['name'], 'field2');
        $this->assertEquals($content['data'][2]['id'] > 2, true);
        $this->assertEquals($content['data'][2]['name'], 'expiry_date');
    }

    public function testGet()
    {
        $this->initAuthToken($this->adminUser);
        $selectResult = $this->getFieldUuid();
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/field/' . $selectResult[0]['uuid'], 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['id'] > 0, true);
        $this->assertEquals($content['data']['name'], 'field1');
    }

    public function testGetNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/field/465c8710-df82-11e9-8a34-2a2ae2dbcce0', 'GET');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testCreate()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'field3', 'entity_id' => 1, 'app_id' => 1, 'required' => 1, 'data_type' => 'text'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/field', 'POST', null);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['id'] > 2, true);
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals($content['data']['required'], $data['required']);
        $this->assertEquals($content['data']['data_type'], $data['data_type']);
    }

    public function testCreateFailure()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['required' => 1, 'sequence' => 1, 'data_type' => 'text'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/field', 'POST', null);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Validation Errors');
        $this->assertEquals($content['data']['errors']['name'], 'required');
    }

    public function testUpdate()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Sample2', 'app_id' => 1, 'required' => 0, 'sequence' => 1];
        $this->setJsonContent(json_encode($data));
        $selectResult = $this->getFieldUuid();
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/field/' . $selectResult[0]['uuid'], 'PUT', null);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['id'], 1);
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals($content['data']['sequence'], $data['sequence']);
    }

    public function testUpdateNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Sample2', 'text' => 'Sample 2 Description'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/field/465c8710-df82-11e9-8a34-2a2ae2dbccb6', 'PUT', null);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testDelete()
    {
        $this->initAuthToken($this->adminUser);
        $selectResult = $this->getFieldUuid();
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/field/' . $selectResult[0]['uuid'], 'DELETE');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testDeleteNotPossible()
    {
        $this->initAuthToken($this->adminUser);
        $selectResult = $this->getFieldUuid();
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/field/' . $selectResult[0]['uuid'], 'DELETE');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testDeleteNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/field/465c8710-df82-11e9-8a34-2a2ae2dbccb8', 'DELETE');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testSearchIndex()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/searchIndex', 'POST');
        $this->assertResponseStatusCode(200);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 5);
        $this->assertEquals($content['data'][0]['name'], 'field1');
        $this->assertEquals($content['data'][1]['name'], 'padi_number');
        $this->assertEquals($content['data'][2]['name'], 'policy_document');
    }

    public function testSearchIndexWithEntity()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/searchIndex/entity1', 'POST');
        $this->assertResponseStatusCode(200);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 4);
        $this->assertEquals($content['data'][0]['name'], 'field1');
        $this->assertEquals($content['data'][1]['name'], 'padi_number');
        $this->assertEquals($content['data'][2]['name'], 'policy_document');
    }

    public function testSearchIndexWithOtherEntity()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/searchIndex/entity2', 'POST');
        $this->assertResponseStatusCode(200);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 1);
        $this->assertEquals($content['data'][0]['name'], 'policy_period');
    }
}
