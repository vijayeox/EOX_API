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
use Oxzion\Service\GroupService;
use Mockery;
use Oxzion\Messaging\MessageProducer;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\ResultSet\ResultSet;



class GroupControllerTest extends ControllerTest {

    public function setUp() : void {
        $this->loadConfig();
        parent::setUp();
    }

    public function getMockMessageProducer(){
        $organizationService = $this->getApplicationServiceLocator()->get(Service\GroupService::class);
        $mockMessageProducer = Mockery::mock('Oxzion\Messaging\MessageProducer');
        $organizationService->setMessageProducer($mockMessageProducer);
        return $mockMessageProducer;
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

    private function executeQueryTest($query){
        $dbAdapter = $this->getApplicationServiceLocator()->get(AdapterInterface::class);
        $statement = $dbAdapter->query($query);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $resultSet->initialize($result);
        return $resultSet->toArray();
    }

    public function testgetGroupsforUser() {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/group/2db1c5a3-8a82-4d5b-b60a-c648cf1e27de', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('groups');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(4, $this->getConnection()->getRowCount('ox_group'));
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

    public function testgetGroupsforUserByManager() {
        $this->initAuthToken($this->managerUser);
        $this->dispatch('/group?org_id=b0971de7-0387-48ea-8f29-5d3704d96a46', 'GET');
        $this->assertResponseStatusCode(403);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('groups');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'],'You do not have permissions to get the group list');
    }

    public function testgetGroupsforUserForManager() {
        $this->initAuthToken($this->managerUser);
        $this->dispatch('/group', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Group');
        $this->assertControllerName(GroupController::class); // as specified in router's controller name alias
        $this->assertControllerClass('GroupController');
        $this->assertMatchedRouteName('groups');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(4, $this->getConnection()->getRowCount('ox_group'));  
    }

  
    public function testgetGroupsforUserForEmployee() {
        $this->initAuthToken($this->employeeUser);
        $this->dispatch('/group', 'GET');
        $this->assertResponseStatusCode(401);
        $this->assertModuleName('Group');
        $this->assertControllerName(GroupController::class); // as specified in router's controller name alias
        $this->assertControllerClass('GroupController');
        $this->assertMatchedRouteName('groups');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You have no Access to this API');
    }

// Testing to see if the Create Group function is working as intended if all the value passed are correct.
    public function testCreate() {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Groups 22', 'parent_id' => "2db1c5a3-8a82-4d5b-b60a-c648cf1e27de", 'org_id'=> 1, 'manager_id' => "4fd99e8e-758f-11e9-b2d5-68ecc57cde45", 'description'=>'Description Test Data', 'logo' => 'grp1.png','status' => 'Active'];
        $this->assertEquals(4, $this->getConnection()->getRowCount('ox_group'));
        $this->setJsonContent(json_encode($data));
        if(enableActiveMQ == 0){
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('groupname' => 'Groups 22', 'orgname'=>'Cleveland Black')),'GROUP_ADDED')->once()->andReturn();
        }
        $this->dispatch('/group', 'POST', $data);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('groups');
        $select = "SELECT id,manager_id from ox_group where name = 'Groups 22'";
        $group = $this->executeQueryTest($select);
        $select = "SELECT * from ox_user_group where avatar_id =".$group[0]['manager_id']." and group_id =".$group[0]['id'];
        $oxgroup = $this->executeQueryTest($select);
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals($content['data']['parent_id'], 1);
        $this->assertEquals($content['data']['org_id'], 1);
        $this->assertEquals($content['data']['manager_id'], 1);
        $this->assertEquals($content['data']['description'], $data['description']);
        $this->assertEquals($content['data']['logo'], "grp1.png");
        $this->assertEquals($content['data']['status'], "Active");
        $this->assertEquals($group[0]['manager_id'], 1);
        $this->assertEquals($oxgroup[0]['avatar_id'], 1);
        $this->assertEquals(5, $this->getConnection()->getRowCount('ox_group'));
    }

    public function testCreateByAdminWithDifferentOrgID() {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Groups 22', 'parent_id' => '2db1c5a3-8a82-4d5b-b60a-c648cf1e27de', 'org_id'=> 'b0971de7-0387-48ea-8f29-5d3704d96a46', 'manager_id' => "4fd99e8e-758f-11e9-b2d5-68ecc57cde45", 'description'=>'Description Test Data', 'logo' => 'grp1.png','status' => 'Active'];
        $this->assertEquals(4, $this->getConnection()->getRowCount('ox_group'));
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/group', 'POST', $data);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('groups');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals($content['data']['parent_id'], 1);
        $this->assertEquals($content['data']['org_id'], 2);
        $this->assertEquals($content['data']['manager_id'], 1);
        $this->assertEquals($content['data']['description'], $data['description']);
        $this->assertEquals($content['data']['logo'], "grp1.png");
        $this->assertEquals($content['data']['status'], "Active");
        $this->assertEquals(5, $this->getConnection()->getRowCount('ox_group'));
    }

//Test Case to check the errors when the required field is not selected. Here I removed the parent_id field from the list.
    public function testCreateWithoutRequiredField() {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Groups 22', 'description'=>'Description Test Data', 'status' => 'Active'];
        $this->assertEquals(4, $this->getConnection()->getRowCount('ox_group'));
        $this->setJsonContent(json_encode($data));
        if(enableActiveMQ == 0){
            $mockMessageProducer = $this->getMockMessageProducer();
        }
        $this->dispatch('/group', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('groups');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        //  print_r($content);exit;
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Validation Errors');
        $this->assertEquals($content['data']['errors']['manager_id'], 'required');
    }

    public function testCreateByEmployee() {
        $this->initAuthToken($this->employeeUser);
        $data = ['name' => 'Groups 22', 'parent_id' => 1, 'org_id'=> 'b0971de7-0387-48ea-8f29-5d3704d96a46', 'manager_id' => "4fd99e8e-758f-11e9-b2d5-68ecc57cde45", 'description
        '=>'Description Test Data', 'logo' => 'grp1.png','status' => 'Active'];
        $this->assertEquals(4, $this->getConnection()->getRowCount('ox_group'));
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/group', 'POST', $data);
        $this->assertResponseStatusCode(401);
        $this->assertModuleName('Group');
        $this->assertControllerName(GroupController::class); // as specified in router's controller name alias
        $this->assertControllerClass('GroupController');
        $this->assertMatchedRouteName('groups');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You have no Access to this API');
    }

    public function testUpdate() {
        $data = ['name' => 'Test Create Group','manager_id' => "4fd9ce37-758f-11e9-b2d5-68ecc57cde45", 'description'=>'Description Test Data'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        if(enableActiveMQ == 0){
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('old_groupname' => 'Test Group', 'orgname'=> 'Cleveland Black' , 'new_groupname'=> 'Test Create Group')),'GROUP_UPDATED')->once()->andReturn();
        }
        $this->dispatch('/group/2db1c5a3-8a82-4d5b-b60a-c648cf1e27de', 'POST', $data);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('groups');
        $select = "SELECT id,manager_id from ox_group where name = 'Test Create Group'";
        $group = $this->executeQueryTest($select);
        $select = "SELECT * from ox_user_group where avatar_id =".$group[0]['manager_id']." and group_id =".$group[0]['id'];
        $oxgroup = $this->executeQueryTest($select);
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], 'Test Create Group');
        $this->assertEquals($content['data']['org_id'], 1);
        $this->assertEquals($content['data']['manager_id'], 2);
        $this->assertEquals($content['data']['description'], "Description Test Data");
        $this->assertEquals($content['data']['status'], "Active");
        $this->assertEquals($group[0]['manager_id'], 2);
        $this->assertEquals($oxgroup[0]['avatar_id'], 2);
    }


    public function testUpdateByManagerWithDifferentOrgId() {
        $data = ['name' => 'Test Create Group','manager_id' => "4fd99e8e-758f-11e9-b2d5-68ecc57cde45", 'description'=>'Description Test Data', 'org_id'=> 'b0971de7-0387-48ea-8f29-5d3704d96a46'];
        $this->initAuthToken($this->managerUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/group/2db1c5a3-8a82-4d5b-b60a-c648cf1e27de', 'POST', null);
        $this->assertResponseStatusCode(403);
        $this->assertModuleName('Group');
        $this->assertControllerName(GroupController::class); // as specified in router's controller name alias
        $this->assertControllerClass('GroupController');
        $this->assertMatchedRouteName('groups');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You do not have permissions to edit the group');
    }

    public function testUpdateByManager() {
        $data = ['name' => 'Test Create Group','manager_id' => "4fd99e8e-758f-11e9-b2d5-68ecc57cde45", 'description'=>'Description Test Data'];
        $this->initAuthToken($this->managerUser);
        $this->setJsonContent(json_encode($data));
        if(enableActiveMQ == 0){
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('old_groupname' => 'Test Group', 'orgname'=> 'Cleveland Black' , 'new_groupname'=> 'Test Create Group')),'GROUP_UPDATED')->once()->andReturn();
        }
        $this->dispatch('/group/2db1c5a3-8a82-4d5b-b60a-c648cf1e27de', 'POST', $data);
        $this->assertResponseStatusCode(201);
        $this->assertModuleName('Group');
        $this->assertControllerName(GroupController::class); // as specified in router's controller name alias
        $this->assertControllerClass('GroupController');
        $this->assertMatchedRouteName('groups');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], 'Test Create Group');
        $this->assertEquals($content['data']['org_id'], 1);
        $this->assertEquals($content['data']['manager_id'], 1);
    }

    public function testUpdateNotFound() {
        $data = ['name' => 'Test','manager_id' => "4fd99e8e-758f-11e9-b2d5-68ecc57cde45", 'description'=>'Description Test Data'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        if(enableActiveMQ == 0){
           $mockMessageProducer = $this->getMockMessageProducer();
        }
        $this->dispatch('/group/10000', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('groups');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testDelete() {
        $this->initAuthToken($this->adminUser);
        if(enableActiveMQ == 0){
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('groupname' => 'Test Group', 'orgname'=>'Cleveland Black')),'GROUP_DELETED')->once()->andReturn();
        }
        $this->dispatch('/group/2db1c5a3-8a82-4d5b-b60a-c648cf1e27de', 'DELETE');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('groups');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }


    public function testDeleteByManager() {
        $this->initAuthToken($this->managerUser);
        $this->dispatch('/group/2db1c5a3-8a82-4d5b-b60a-c648cf1e27de?org_id=b0971de7-0387-48ea-8f29-5d3704d96a46', 'DELETE');
        $this->assertResponseStatusCode(403);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('groups');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You do not have permissions to delete the group');
    }

    public function testDeleteByManagerWithPresentOrg() {
        $this->initAuthToken($this->managerUser);
        $this->dispatch('/group/2db1c5a3-8a82-4d5b-b60a-c648cf1e27de', 'DELETE');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('groups');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testDeleteByEmployee() {
        $this->initAuthToken($this->employeeUser);
        $this->dispatch('/group/2db1c5a3-8a82-4d5b-b60a-c648cf1e27de?org_id=b0971de7-0387-48ea-8f29-5d3704d96a46', 'DELETE');
        $this->assertResponseStatusCode(401);
        $this->assertModuleName('Group');
        $this->assertControllerName(GroupController::class); // as specified in router's controller name alias
        $this->assertControllerClass('GroupController');
        $this->assertMatchedRouteName('groups');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You have no Access to this API');
    }

   
    public function testDeleteNotFound() {
        $this->initAuthToken($this->adminUser);
        if(enableActiveMQ == 0){
            $mockMessageProducer = $this->getMockMessageProducer();
        }
        $this->dispatch('/group/10000', 'DELETE');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('groups');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testsaveuserByAdmin() {
        $this->initAuthToken($this->adminUser);
        $data = ['userid' => array(['id' => '4fd9ce37-758f-11e9-b2d5-68ecc57cde45'],['id' => '4fd9f04d-758f-11e9-b2d5-68ecc57cde45'])];
        if(enableActiveMQ == 0){
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('groupname' => 'Test Group', 'orgname'=>'Cleveland Black','username' => $this->adminUser)),'USERTOGROUP_DELETED')->once()->andReturn();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('groupname' => 'Test Group', 'orgname'=>'Cleveland Black','username' => $this->employeeUser)),'USERTOGROUP_ADDED')->once()->andReturn();
        }
        $this->dispatch('/group/2db1c5a3-8a82-4d5b-b60a-c648cf1e27de/save','POST', $data); 
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success'); 
    }

    public function testsaveuserByManagerWithDifferentOrgId() {
        $this->initAuthToken($this->managerUser);
        $data = ['userid' => array(['id' => '4fd9ce37-758f-11e9-b2d5-68ecc57cde45'],['id' => '4fd9f04d-758f-11e9-b2d5-68ecc57cde45']),'org_id' => 'b0971de7-0387-48ea-8f29-5d3704d96a46'];
        $this->dispatch('/group/2db1c5a3-8a82-4d5b-b60a-c648cf1e27de/save','POST',$data); 
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('saveusers');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You do not have permissions to add users to group');
    }


    public function testsaveuserByManager() {
        $this->initAuthToken($this->managerUser);
        $data = ['userid' => array(['id' => '4fd9ce37-758f-11e9-b2d5-68ecc57cde45'],['id' => '4fd9f04d-758f-11e9-b2d5-68ecc57cde45'])]; 
        $this->dispatch('/group/2db1c5a3-8a82-4d5b-b60a-c648cf1e27de/save','POST',$data);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('saveusers');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }


    public function testsaveuserByEmployee() {
        $this->initAuthToken($this->employeeUser);
        $data = ['userid' => array(['id' => '4fd9ce37-758f-11e9-b2d5-68ecc57cde45'],['id' => '4fd9f04d-758f-11e9-b2d5-68ecc57cde45'])];
        $this->dispatch('/group/2db1c5a3-8a82-4d5b-b60a-c648cf1e27de/save','POST',$data); 
        $this->assertResponseStatusCode(401);
        $this->assertModuleName('Group');
        $this->assertControllerName(GroupController::class); // as specified in router's controller name alias
        $this->assertControllerClass('GroupController');
        $this->assertMatchedRouteName('saveusers');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You have no Access to this API');
    }

    public function testsaveuserwithoutuser() {
        $this->initAuthToken($this->adminUser);
        if(enableActiveMQ == 0){
            $mockMessageProducer = $this->getMockMessageProducer();
        }
        $this->dispatch('/group/2db1c5a3-8a82-4d5b-b60a-c648cf1e27de/save','POST'); 
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error'); 
    }

    public function testsaveusernotfound() {
        $this->initAuthToken($this->adminUser);
        if(enableActiveMQ == 0){
            $mockMessageProducer = $this->getMockMessageProducer();
        }
        $data = ['userid' => array(['id' => '4fd9ce37-758f-1c57cde45'],['id' => '4fd9f04d-758f-11e9-b'])];
        $this->dispatch('/group/1/save','POST',$data); 
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testgetuserlist() {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/group/2db1c5a3-8a82-4d5b-b60a-c648cf1e27de/users','GET'); 
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success'); 
        $this->assertEquals(count($content['data']), 2);
        $this->assertEquals($content['data'][0]['uuid'], '4fd99e8e-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][0]['name'], 'Bharat Gogineni');
        $this->assertEquals($content['data'][1]['uuid'], '4fd9ce37-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][1]['name'], 'Karan Agarwal');
        $this->assertEquals($content['total'],2);
    }

    public function testgetuserlistByManager() {
        $this->initAuthToken($this->managerUser);
        $this->dispatch('/group/2db1c5a3-8a82-4d5b-b60a-c648cf1e27de/users','GET'); 
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success'); 
        $this->assertEquals(count($content['data']), 2);
        $this->assertEquals($content['data'][0]['uuid'], '4fd99e8e-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][0]['name'], 'Bharat Gogineni');
        $this->assertEquals($content['data'][1]['uuid'], '4fd9ce37-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][1]['name'], 'Karan Agarwal');
        $this->assertEquals($content['total'],2);
    }

    public function testgetuserlistByManagerWithDifferentOrgId() {
        $this->initAuthToken($this->managerUser);
        $this->dispatch('/group/2db1c5a3-8a82-4d5b-b60a-c648cf1e27de/users?filter=[{"skip":1,"take":1}]&org_id=b0971de7-0387-48ea-8f29-5d3704d96a46','GET'); 
        $this->assertResponseStatusCode(403);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You do not have permissions to get the group users list');
    }
 
    public function testgetuserlistWithPagesize() {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/group/2db1c5a3-8a82-4d5b-b60a-c648cf1e27de/users?filter=[{"skip":1,"take":1}]
','GET'); 
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success'); 
        $this->assertEquals(count($content['data']), 1);
        $this->assertEquals($content['data'][0]['uuid'], '4fd9ce37-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][0]['name'], 'Karan Agarwal');
        $this->assertEquals($content['total'],2);
    }

    public function testgetuserlistWithPageNo() {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/group/2db1c5a3-8a82-4d5b-b60a-c648cf1e27de/users?filter=[{"filter":{"filters":[{"field":"name","operator":"contains","value":"go"}]},"sort":[{"field":"id","dir":"asc"}],"skip":0,"take":1}]
','GET'); 
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success'); 
        $this->assertEquals(count($content['data']), 1);
        $this->assertEquals($content['data'][0]['uuid'], '4fd99e8e-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][0]['name'], 'Bharat Gogineni');
        $this->assertEquals($content['total'], 1);
    }

    public function testgetuserlistWithQueryFieldParameter() {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/group/2db1c5a3-8a82-4d5b-b60a-c648cf1e27de/users?filter=[{"filter":{"filters":[{"field":"name","operator":"startswith","value":"ka"}]},"sort":[{"field":"id","dir":"asc"}],"skip":0,"take":1}]','GET'); 
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success'); 
        $this->assertEquals(count($content['data']), 1);
        $this->assertEquals($content['data'][0]['uuid'], '4fd9ce37-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][0]['name'], 'Karan Agarwal');
        $this->assertEquals($content['total'], 1);
    }

    public function testgetuserlistNotFound() {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/group/64/users','GET'); 
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success'); 
        $this->assertEquals($content['data'],array());
        $this->assertEquals($content['total'],0);
    }

}