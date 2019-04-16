<?php
namespace Project;

use Project\Controller\ProjectController;
use Project\Model;
use Oxzion\Test\ControllerTest;
use PHPUnit\DbUnit\TestCaseTrait;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Zend\Db\Sql\Sql;
use Zend\Db\Adapter\Adapter;
use Oxzion\Service\ProjectService;
use Mockery;
use Oxzion\Messaging\MessageProducer;

class ProjectControllerTest extends ControllerTest {
    public function setUp() : void{
        $this->loadConfig();
        parent::setUp();
    }   

    public function getMockMessageProducer(){
        $organizationService = $this->getApplicationServiceLocator()->get(Service\ProjectService::class);
        $mockMessageProducer = Mockery::mock('Oxzion\Messaging\MessageProducer');
        $organizationService->setMessageProducer($mockMessageProducer);
        return $mockMessageProducer;
    }

    public function getDataSet() {
        $dataset = new YamlDataSet(dirname(__FILE__)."/../Dataset/Project.yml");
        return $dataset;
    }

    protected function setDefaultAsserts() {
        $this->assertModuleName('Project');
        $this->assertControllerName(ProjectController::class); // as specified in router's controller name alias
        $this->assertControllerClass('ProjectController');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    }
    public function testGetList() {
        $this->initAuthToken($this->adminUser);
        $data = ['data' => array([
            "id" => "1",
            "name"=> "Test Project 1",
            "org_id"=>"1",
            "description"=> "Description Test Data",
            "created_by"=> "1",
            "modified_by"=> "1",
            "date_created"=> "2018-11-11 07:25:06",
            "date_modified"=> "2018-12-11 07:25:06",
            "isdeleted"=> "0",
            "user_id"=> "1",
            "project_id"=>"1"
        ],[
            "id"=> "3",
            "name"=> "Test Project 2",
            "org_id"=>"1",
            "description"=> "Description Test Data",
            "created_by"=> "1",
            "modified_by"=> "1",
            "date_created"=> "2018-11-11 07:25:06",
            "date_modified"=> "2018-12-11 07:25:06",
            "isdeleted"=> "0",
            "user_id"=> "1",
            "project_id"=> "2"
        ])];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/project', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $diff=array_diff($data, $content);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($diff, array());
    }
    public function testGet() {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/project/1', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['id'], 1);
        $this->assertEquals($content['data']['name'], 'Test Project 1');
    }
    public function testGetNotFound() {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/project/64', 'GET');
        $this->assertResponseStatusCode(404);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }
    public function testCreate() {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Test Project 3','description'=>'Project Description'];
        $this->assertEquals(2, $this->getConnection()->getRowCount('ox_project'));
        if(enableActiveMQ == 0){
             $mockMessageProducer = $this->getMockMessageProducer();
             $mockMessageProducer->expects('sendTopic')->with(json_encode(array('orgname'=> 'Cleveland Black','projectname' => 'Test Project 3')),'PROJECT_ADDED')->once()->andReturn();
        }
        $this->dispatch('/project', 'POST', $data);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals(3, $this->getConnection()->getRowCount('ox_project'));
    }
    public function testCreateWithOutNameFailure() {    
        $this->initAuthToken($this->adminUser);
        $data = ['description'=>'Project Description'];
        $this->setJsonContent(json_encode($data));
        if(enableActiveMQ == 0){
            $mockMessageProducer = $this->getMockMessageProducer();
        }
        $this->dispatch('/project', 'POST', null);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Validation Errors');
        $this->assertEquals($content['data']['errors']['name'], 'required');
    }

    public function testCreateAccess() {
        $this->initAuthToken($this->employeeUser);
        $data = ['name' => 'Test Project 1','description'=>'Project Description'];
        $this->setJsonContent(json_encode($data));
        if(enableActiveMQ == 0){
            $mockMessageProducer = $this->getMockMessageProducer();
        }
        $this->dispatch('/project', 'POST', null);
        $this->assertResponseStatusCode(401);
        $this->assertModuleName('Project');
        $this->assertControllerName(ProjectController::class); // as specified in router's controller name alias
        $this->assertControllerClass('ProjectController');
        $this->assertMatchedRouteName('project');
        $this->assertResponseHeaderContains('content-type', 'application/json');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You have no Access to this API');
    }
    public function testUpdate() {
        $data = ['name' => 'Test Project','description'=>'Project Description'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        if(enableActiveMQ == 0){
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('orgname' => 'Cleveland Black','old_projectname'=> 'Test Project 1','new_projectname' => 'Test Project')),'PROJECT_UPDATED')->once()->andReturn();
        }
        $this->dispatch('/project/1', 'PUT', null);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
    }
    public function testUpdateRestricted() {
        $data = ['name' => 'Test Project 1','description'=>'Project Description'];
        $this->initAuthToken($this->employeeUser);
        $this->setJsonContent(json_encode($data));
        if(enableActiveMQ == 0){
            $mockMessageProducer = $this->getMockMessageProducer();
        }
        $this->dispatch('/project/1', 'PUT', null);
        $this->assertResponseStatusCode(401);
        $this->assertModuleName('Project');
        $this->assertControllerName(ProjectController::class); // as specified in router's controller name alias
        $this->assertControllerClass('ProjectController');
        $this->assertMatchedRouteName('project');
        $this->assertResponseHeaderContains('content-type', 'application/json');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You have no Access to this API');
    }

    public function testUpdateNotFound() {
        $data = ['name' => 'Test Project 1','description'=>'Project Description'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        if(enableActiveMQ == 0){
            $mockMessageProducer = $this->getMockMessageProducer();
        }
        $this->dispatch('/project/122', 'PUT', null);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testDelete() {
        $this->initAuthToken($this->adminUser);
        if(enableActiveMQ == 0){
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('orgname' =>'Cleveland Black','projectname' => 'Test Project 2')),'PROJECT_DELETED')->once()->andReturn();
        }
        $this->dispatch('/project/2', 'DELETE');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testDeleteNotFound() {
        $this->initAuthToken($this->adminUser);
        if(enableActiveMQ == 0){
            $mockMessageProducer = $this->getMockMessageProducer();
        }
        $this->dispatch('/project/1222', 'DELETE');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');        
    }

    public function testSaveUser() {
        $this->initAuthToken($this->adminUser);
        if(enableActiveMQ == 0){
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('orgname' =>'Cleveland Black','projectname' => 'Test Project 1','username' => 'bharatg')),'USERTOPROJECT_DELETED')->once()->andReturn();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('orgname' =>'Cleveland Black','projectname' => 'Test Project 1','username' => 'rakshith')),'USERTOPROJECT_ADDED')->once()->andReturn();
        }
    	$this->dispatch('/project/1/save','POST',array('userid' => '[{"id":2},{"id":3}]')); 
    	$this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
    	$content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success'); 
    }

    public function testSaveUserWithoutUser() {
        $this->initAuthToken($this->adminUser);
        if(enableActiveMQ == 0){
            $mockMessageProducer = $this->getMockMessageProducer();
        }
    	$this->dispatch('/project/1/save','POST'); 
    	$this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
    	$content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error'); 
    }

    public function testSaveUserNotFound() {
        $this->initAuthToken($this->adminUser);
        if(enableActiveMQ == 0){
            $mockMessageProducer = $this->getMockMessageProducer();      
        }
    	$this->dispatch('/project/1/save','POST',array('userid' => '[{"id":1},{"id":23}]')); 
    	$this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
    	$content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testGetListOfUsers() {
    	$this->initAuthToken($this->adminUser);
    	$this->dispatch('/project/1/users','GET'); 
    	$this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
    	$content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success'); 
    }

    public function testGetListOfUsersNotFound() {
    	$this->initAuthToken($this->adminUser);
    	$this->dispatch('/project/64/users','GET'); 
    	$this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
    	$content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error'); 
    }

    public function testGetMyProjectList(){
        $this->initAuthToken($this->adminUser);
        $data = ['data' => array([
            "id" => "1",
            "name"=> "Test Project 1",
            "org_id"=>"1",
            "description"=> "Description Test Data",
            "created_by"=> "1",
            "modified_by"=> "1",
            "date_created"=> "2018-11-11 07:25:06",
            "date_modified"=> "2018-12-11 07:25:06",
            "isdeleted"=> "0",
            "user_id"=> "1",
            "project_id"=>"1"
        ],[
            "id"=> "3",
            "name"=> "Test Project 2",
            "org_id"=>"1",
            "description"=> "Description Test Data",
            "created_by"=> "1",
            "modified_by"=> "1",
            "date_created"=> "2018-11-11 07:25:06",
            "date_modified"=> "2018-12-11 07:25:06",
            "isdeleted"=> "0",
            "user_id"=> "1",
            "project_id"=> "2"
        ])];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/project/myproject', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('myproject');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $diff=array_diff($data, $content);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($diff, array());
    }
    public function testGetMyProjectListWithoutdata()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['data' => array([])];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/project/myproject', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('myproject');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $diff=array_diff($data, $content);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($diff, array());
    }
}
?>