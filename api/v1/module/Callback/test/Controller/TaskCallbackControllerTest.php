<?php
namespace Callback;

use Callback\Controller\TaskCallbackController;
use Oxzion\Test\ControllerTest;
use Oxzion\Db\ModelTable;
use PHPUnit\DbUnit\TestCaseTrait;
use PHPUnit\DbUnit\DataSet\DefaultDataSet;
use PHPUnit\Framework\TestResult;
use Zend\Db\Sql\Sql;
use Zend\Db\Adapter\Adapter;
use Oxzion\Utils\RestClient;
use Callback\Service\TaskService;
use Mockery;
    


class TaskCallbackControllerTest extends ControllerTest
{

    public function setUp() : void
    {
        $this->loadConfig();
        parent::setUp();
    }

    public function getDataSet()
    {
        return new DefaultDataSet();
    }

    private function getMockRestClientForTaskService(){
        $taskService = $this->getApplicationServiceLocator()->get(Service\TaskService::class);
        $mockRestClient = Mockery::mock('Oxzion\Utils\RestClient');
        $taskService->setRestClient($mockRestClient);
        return $mockRestClient;
    }

    public function testCreate()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'New Project 1','description' => 'Open project applications','uuid'=>'faaf6453-d5a8-4061-9ac7-a83b8eefe20e'];
        if(enableCamel==0){ 
                     $mockRestClient = $this->getMockRestClientForTaskService();
                     $mockRestClient->expects('postWithHeader')->with("projects",array("name" => "New Project 1","description" => "Open project applications","uuid" => "faaf6453-d5a8-4061-9ac7-a83b8eefe20e"))->once()->andReturn(array("body" => json_encode(array("status" => "success","data" => array("name" => "New Project 1","description" => "Open project applications","uuid" => "faaf6453-d5a8-4061-9ac7-a83b8eefe20e"),"message" => "Project Added Successfully"))));  
                    }
        $this->dispatch('/callback/task/addproject', 'POST',array(json_encode($data)=>''));
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('addprojectfromcallback');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals($content['data']['description'], $data['description']);
    }

    public function testCreateProjectUuidAlreadyExists()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'New Project 1','description' => 'Open project applications','uuid'=>'faaf6453-d5a8-4061-9ac7-a83b8eefe20e'];
        if(enableCamel==0){ 
                     $mockRestClient = $this->getMockRestClientForTaskService();
                     $exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
                     $mockRestClient->expects('postWithHeader')->with("projects",array("name" => "New Project 1","description" => "Open project applications","uuid" => "faaf6453-d5a8-4061-9ac7-a83b8eefe20e"))->once()->andThrow($exception);
                    }
        $this->dispatch('/callback/task/addproject', 'POST',array(json_encode($data)=>''));
        $this->assertResponseStatusCode(400);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('addprojectfromcallback');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }


    public function testCreateProjectInvalidParameters()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['description' => 'Open project applications','uuid'=>'faaf6453-d5a8-4061-9ac7-a83b8eefe20e'];
        if(enableCamel==0){ 
                     $mockRestClient = $this->getMockRestClientForTaskService();
                     $exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
                     $mockRestClient->expects('postWithHeader')->with("projects",array("name" => NULL,"description" => "Open project applications","uuid" => "faaf6453-d5a8-4061-9ac7-a83b8eefe20e"))->once()->andThrow($exception);
                    }
        $this->dispatch('/callback/task/addproject', 'POST',array(json_encode($data)=>''));
        $this->assertResponseStatusCode(400);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('addprojectfromcallback');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }


    public function testUpdate()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['uuid'=>'faaf6453-d5a8-4061-9ac7-a83b8eefe20e','name' => 'Project Data','description' => 'New Demo Project'];
        if(enableCamel==0){ 
                     $mockRestClient = $this->getMockRestClientForTaskService();
                     $mockRestClient->expects('updateWithHeader')->with("projects/".$data['uuid'],array("name" => "Project Data","description" => "New Demo Project"))->once()->andReturn(array("body" => json_encode(array("status" => "success","data" => array("name" => "Project Data","description" => "New Demo Project"),"message" => "Project Updated Successfully"))));  
                    }
        $this->dispatch('/callback/task/updateproject', 'POST',array(json_encode($data)=>''));
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('updateprojectfromcallback');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
    }


    public function testUpdateWithInavlidID()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['uuid'=>'faaf6453-d5a8-406','name' => 'Project Data','description' => 'New Demo Project'];
        if(enableCamel==0){ 
                     $mockRestClient = $this->getMockRestClientForTaskService();
                     $exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
                     $mockRestClient->expects('updateWithHeader')->with("projects/".$data['uuid'],array("name" => "Project Data","description" => "New Demo Project"))->once()->andThrow($exception);
                    }
        $this->dispatch('/callback/task/updateproject', 'POST',array(json_encode($data)=>''));
        $this->assertResponseStatusCode(400);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('updateprojectfromcallback');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }


    public function testDelete()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['uuid'=>'faaf6453-d5a8-4061-9ac7-a83b8eefe20e'];
        if(enableCamel==0){ 
                     $mockRestClient = $this->getMockRestClientForTaskService();
                     $mockRestClient->expects('deleteWithHeader')->with("projects/".$data['uuid'])->once()->andReturn(array("body" => json_encode(array("status" => "success","data" => array("name" => "Project Data","description" => "New Demo Project","uuid" => "faaf6453-d5a8-4061-9ac7-a83b8eefe20e"),"message" => "Project Deleted Successfully"))));  
                    }
        $this->dispatch('/callback/task/deleteproject', 'POST',array(json_encode($data)=>''));
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('deleteprojectfromcallback');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], 'Project Data');
        $this->assertEquals($content['data']['description'], 'New Demo Project');
    }

    public function testDeleteWithInavlidID()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['uuid'=>'faaf6453-d5a8-406'];
        if(enableCamel==0){ 
                     $mockRestClient = $this->getMockRestClientForTaskService();
                     $exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
                     $mockRestClient->expects('deleteWithHeader')->with("projects/".$data['uuid'])->once()->andThrow($exception);
                    }
        $this->dispatch('/callback/task/deleteproject', 'POST',array(json_encode($data)=>''));
        $this->assertResponseStatusCode(400);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('deleteprojectfromcallback');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
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