<?php
namespace Group;

use Group\Controller\GroupController;
use Group\Model;
use Oxzion\Test\ControllerTest;
use Oxzion\Db\ModelTable;
use PHPUnit\DbUnit\TestCaseTrait;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Zend\Db\Sql\Sql;
use Zend\Db\Adapter\Adapter;


class GroupControllerTest extends ControllerTest {

    public function setUp() : void {
        $this->loadConfig();
        parent::setUp();
    }

    public function getDataSet() {
        $dataset = new YamlDataSet(dirname(__FILE__)."/../Dataset/Group.yml");
        return $dataset;
    }

    protected function setDefaultAsserts() {
        $this->assertModuleName('Group');
        $this->assertControllerName(GroupController::class); // as specified in router's controller name alias
        $this->assertControllerClass('GroupController');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    }

    public function testgetGroupsforUser() {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/group/1', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('groups');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(2, $this->getConnection()->getRowCount('ox_group'));
    }

    public function testgetGroupsforUserNotFound() {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/group/10000', 'GET');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('groups');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

//Testing to see if the Create Group function is working as intended if all the value passed are correct.
    public function testCreate() {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Groups 22', 'parent_id'=> 9, 'org_id'=>1, 'manager_id' => 436, 'description
        '=>'Description Test Data', 'logo' => 'grp1.png', 'cover_photo'=>'grp1.png', 'type' => 1, 'status' => 'Active'];
        $this->assertEquals(2, $this->getConnection()->getRowCount('ox_group'));
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/group', 'POST', $data);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('groups');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals($content['data']['parent_id'], 9);
        $this->assertEquals($content['data']['org_id'], 1);
        $this->assertEquals($content['data']['manager_id'], 436);
        $this->assertEquals($content['data']['description'], $data['description']);
        $this->assertEquals($content['data']['logo'], "grp1.png");
        $this->assertEquals($content['data']['cover_photo'], "grp1.png");
        $this->assertEquals($content['data']['type'], 1);
        $this->assertEquals($content['data']['status'], "Active");
        $this->assertEquals(3, $this->getConnection()->getRowCount('ox_group'));
    }

//Test Case to check the errors when the required field is not selected. Here I removed the parent_id field from the list.
    public function testCreateWithoutRequiredField() {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Groups 22', 'manager_id' => 436, 'description
        '=>'Description Test Data', 'logo' => 'grp1.png', 'cover_photo'=>'grp1.png', 'type' => 1, 'status' => 'Active'];
        $this->assertEquals(2, $this->getConnection()->getRowCount('ox_group'));
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/group', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('groups');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        // print_r($content);exit;
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Validation Errors');
        $this->assertEquals($content['data']['errors']['parent_id'], 'required');
    }

    public function testUpdate() {
        $data = ['name' => 'Test Create Group', 'parent_id'=> 9, 'org_id'=>1, 'manager_id' => 436, 'description
        '=>'Description Test Data', 'logo' => 'grp1.png', 'cover_photo'=>'grp1.png', 'type' => 1, 'status' => 'Active'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/group/1', 'PUT', null);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('groups');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], 'Test Create Group');
        $this->assertEquals($content['data']['parent_id'], 9);
        $this->assertEquals($content['data']['org_id'], 1);
        $this->assertEquals($content['data']['manager_id'], 436);
        $this->assertEquals($content['data']['description'], "Description Test Data");
        $this->assertEquals($content['data']['logo'], "grp1.png");
        $this->assertEquals($content['data']['cover_photo'], "grp1.png");
        $this->assertEquals($content['data']['type'], 1);
        $this->assertEquals($content['data']['status'], "Active");
    }

    public function testUpdateNotFound() {
        $data = ['name' => 'Test Create Group', 'org_id'=>1, 'manager_id' => 436, 'description
        '=>'Description Test Data', 'logo' => 'grp1.png', 'cover_photo'=>'grp1.png', 'type' => 1, 'status' => 'Active'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/group/10000', 'PUT', null);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('groups');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testUpdateWithoutRequiredField() {
        $data = ['name' => 'Test Create Group', 'org_id'=>1, 'manager_id' => 436, 'description
        '=>'Description Test Data', 'logo' => 'grp1.png', 'cover_photo'=>'grp1.png', 'type' => 1, 'status' => 'Active'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/group/1', 'PUT', null);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('groups');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testDelete() {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/group/1', 'DELETE');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('groups');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testDeleteNotFound() {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/group/10000', 'DELETE');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('groups');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

}