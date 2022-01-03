<?php
namespace Callback;

use Callback\Controller\TaskCallbackController;
// use Callback\Service\TaskService;
use Mockery;
use Oxzion\Test\ControllerTest;
use PHPUnit\DbUnit\DataSet\DefaultDataSet;

class TaskCallbackControllerTest extends ControllerTest
{
    public function setUp(): void
    {
        $this->loadConfig();
        parent::setUp();
    }

    public function getDataSet()
    {
        return new DefaultDataSet();
    }

    private function getMockRestClientForTaskService()
    {
        $taskService = $this->getApplicationServiceLocator()->get(Service\TaskService::class);
        $mockRestClient = Mockery::mock('Oxzion\Utils\RestClient');
        $taskService->setRestClient($mockRestClient);
        return $mockRestClient;
    }

    public function testCreate()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['projectname' => 'New Project 1', 'description' => 'Open project applications', 'uuid' => 'faaf6453-d5a8-4061-9ac7-a83b8eefe20e', 'parent_identifier'];
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForTaskService();
            $mockRestClient->expects('postWithHeader')->with("projects", array("name" => "New Project 1", "description" => "Open project applications", "uuid" => "pfaaf6453-d5a8-4061-9ac7-a83b8eefe20e", 'manager_login' => null, 'parent_identifier' => null))->once()->andReturn(array("body" => json_encode(array("status" => "success", "data" => array("name" => "New Project 1", "description" => "Open project applications", "uuid" => "pfaaf6453-d5a8-4061-9ac7-a83b8eefe20e"), "message" => "Project Added Successfully"))));
        }
        $this->dispatch('/callback/task/addproject', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('addprojectfromcallback');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['projectname']);
        $this->assertEquals($content['data']['description'], $data['description']);
    }

    public function testCreateProjectUuidAlreadyExists()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['projectname' => 'New Project 1', 'description' => 'Open project applications', 'uuid' => 'faaf6453-d5a8-4061-9ac7-a83b8eefe20e'];
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForTaskService();
            $exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
            $mockRestClient->expects('postWithHeader')->with("projects", array("name" => "New Project 1", "description" => "Open project applications", "uuid" => "pfaaf6453-d5a8-4061-9ac7-a83b8eefe20e"))->once()->andThrow($exception);
        }
        $this->dispatch('/callback/task/addproject', 'POST', $data);
        $this->assertResponseStatusCode(500);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('addprojectfromcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testCreateProjectInvalidParameters()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['description' => 'Open project applications', 'uuid' => 'faaf6453-d5a8-4061-9ac7-a83b8eefe20e'];
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForTaskService();
            $exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
            $mockRestClient->expects('postWithHeader')->with("projects", array("name" => null, "description" => "Open project applications", "uuid" => "pfaaf6453-d5a8-4061-9ac7-a83b8eefe20e"))->once()->andThrow($exception);
        }
        $this->dispatch('/callback/task/addproject', 'POST', $data);
        $this->assertResponseStatusCode(500);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('addprojectfromcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testUpdate()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['uuid' => 'faaf6453-d5a8-4061-9ac7-a83b8eefe20e', 'new_projectname' => 'Project Data', 'description' => 'New Demo Project'];
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForTaskService();
            $mockRestClient->expects('updateWithHeader')->with("projects/" . "p".$data['uuid'], array("name" => "Project Data", "description" => "New Demo Project", 'parent_identifier' => null))->once()->andReturn(array("body" => json_encode(array("status" => "success", "data" => array("name" => "Project Data", "description" => "New Demo Project", 'manager_login' => null), "message" => "Project Updated Successfully"))));
        }
        $this->dispatch('/callback/task/updateproject', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('updateprojectfromcallback');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['new_projectname']);
    }

    public function testUpdateWithInavlidID()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['uuid' => 'faaf6453-d5a8-406', 'new_projectname' => 'Project Data', 'description' => 'New Demo Project'];
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForTaskService();
            $exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
            $mockRestClient->expects('updateWithHeader')->with("projects/" . "p".$data['uuid'], array("name" => "Project Data", "description" => "New Demo Project", 'parent_identifier' => null))->once()->andThrow($exception);
        }
        $this->dispatch('/callback/task/updateproject', 'POST', $data);
        $this->assertResponseStatusCode(400);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('updateprojectfromcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testDelete()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['uuid' => 'faaf6453-d5a8-4061-9ac7-a83b8eefe20e'];
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForTaskService();
            $mockRestClient->expects('deleteWithHeader')->with("projects/" . "p".$data['uuid'])->once()->andReturn(array("body" => json_encode(array("status" => "success", "data" => array("name" => "Project Data", "description" => "New Demo Project", "uuid" => "pfaaf6453-d5a8-4061-9ac7-a83b8eefe20e"), "message" => "Project Deleted Successfully"))));
        }
        $this->dispatch('/callback/task/deleteproject', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('deleteprojectfromcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], 'Project Data');
        $this->assertEquals($content['data']['description'], 'New Demo Project');
    }

    public function testDeleteWithInavlidID()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['uuid' => 'faaf6453-d5a8-406'];
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForTaskService();
            $exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
            $mockRestClient->expects('deleteWithHeader')->with("projects/" . "p".$data['uuid'])->once()->andThrow($exception);
        }
        $this->dispatch('/callback/task/deleteproject', 'POST', $data);
        $this->assertResponseStatusCode(400);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('deleteprojectfromcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testAddUserToProject()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => 'employeetest', 'firstname' => 'rakshith', 'lastname' => 'amin', 'email' => 'test@va.com', 'timezone' => 'United States/New York', 'projectUuid' => 'demo-project'];
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForTaskService();
            $mockRestClient->expects('postWithHeader')->with("oxusers", array('username' => 'employeetest', 'firstname' => 'rakshith', 'lastname' => 'amin', 'email' => 'test@va.com', 'timezone' => 'United States/New York', 'projectUuid' => 'pdemo-project'))->once()->andReturn(array("body" => json_encode(array("status" => "success", "data" => array('username' => 'employeetest', 'firstname' => 'rakshith', 'lastname' => 'amin', 'email' => 'test@va.com', 'timezone' => 'United States/New York', 'projectUuid' => 'pdemo-project')))));
        }
        $this->dispatch('/callback/task/addusertotasktracker', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('ttadduserfromcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['username'], $data['username']);
    }

    public function testAddUserToProjectWithMissingData()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => 'employeetest', 'firstname' => 'rakshith', 'lastname' => 'amin', 'email' => 'test@va.com', 'timezone' => 'United States/New York'];
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForTaskService();
            $mockRestClient->expects('postWithHeader')->with("oxusers", array('username' => 'employeetest', 'firstname' => 'rakshith', 'lastname' => 'amin', 'email' => 'test@va.com', 'timezone' => 'United States/New York'))->once()->andReturn(array("body" => json_encode(array("status" => "error", "data" => array('username' => 'employeetest', 'firstname' => 'rakshith', 'lastname' => 'amin', 'email' => 'test@va.com', 'timezone' => 'United States/New York')))));
        }
        $this->dispatch('/callback/task/addusertotasktracker', 'POST', $data);
        $this->assertResponseStatusCode(400);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('ttadduserfromcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testRemoveUserFromProject()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => 'employeetest', 'projectUuid' => 'demo-project'];
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForTaskService();
            $mockRestClient->expects('deleteWithHeader')->with("oxusers", array('username' => 'employeetest', 'projectUuid' => 'pdemo-project'))->once()->andReturn(array("body" => json_encode(array("status" => "success", "data" => array('username' => 'employeetest', 'projectUuid' => 'pdemo-project')))));
        }
        $this->dispatch('/callback/task/deleteuserfromtasktracker', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('ttdeleteuserfromcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['username'], $data['username']);
    }

    public function testRemoveUserFromProjectWithMissingData()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => 'employeetest'];
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForTaskService();
            $mockRestClient->expects('deleteWithHeader')->with("oxusers", array('username' => 'employeetest', 'projectUuid' => null))->once()->andReturn(array("body" => json_encode(array("status" => "error", "data" => array('username' => 'employeetest')))));
        }
        $this->dispatch('/callback/task/deleteuserfromtasktracker', 'POST', $data);
        $this->assertResponseStatusCode(400);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('ttdeleteuserfromcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    protected function setDefaultAsserts()
    {
        $this->assertModuleName('Callback');
        $this->assertControllerName(TaskCallbackController::class); // as specified in router's controller name alias
        $this->assertControllerClass('TaskCallbackController');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    }
}
