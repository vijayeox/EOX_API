<?php
namespace Announcement;

use Announcement\Controller\AnnouncementController;
use Announcement\Model;
use Oxzion\Test\ControllerTest;
use Oxzion\Db\ModelTable;
use PHPUnit\DbUnit\TestCaseTrait;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Zend\Db\Sql\Sql;
use Zend\Db\Adapter\Adapter;
use Oxzion\Service\FileService;

class AnnouncementControllerTest extends ControllerTest{
    
    public function setUp() : void{
        $this->loadConfig();
        parent::setUp();
    }   
    public function getDataSet() {
        $dataset = new YamlDataSet(dirname(__FILE__)."/../Dataset/Announcement.yml");
        return $dataset;
    }

    protected function tearDown() : void {
        $config = $this->getApplicationConfig();
        $announcementsFolder = $config['DATA_FOLDER']."organization/1/announcements";
        FileService::deleteDirectoryContents($announcementsFolder);
    }
    protected function createDummyFile(){
        $config = $this->getApplicationConfig();
        $tempFolder = $config['DATA_FOLDER']."temp/";
        copy(dirname(__FILE__)."/../files/test-oxzionlogo.png", $tempFolder."test-oxzionlogo.png");
    }
    protected function setDefaultAsserts(){
        $this->assertModuleName('Announcement');
        $this->assertControllerName(AnnouncementController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AnnouncementController');
        $this->assertMatchedRouteName('announcement');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    }
    public function testGetList(){
        $this->initAuthToken('bharatg');
        $this->dispatch('/announcement', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 2);
        $this->assertEquals($content['data'][0]['id'], 1);
        $this->assertEquals($content['data'][0]['name'], 'Announcement 1');
        $this->assertEquals($content['data'][1]['id'], 2);
        $this->assertEquals($content['data'][1]['name'], 'Announcement 2');
    }
    public function testGet(){
        $this->initAuthToken('bharatg');
        $this->dispatch('/announcement/1', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['id'], 1);
        $this->assertEquals($content['data']['name'], 'Announcement 1');
    }
    public function testGetNotFound(){
        $this->initAuthToken('bharatg');
        $this->dispatch('/announcement/64', 'GET');
        $this->assertResponseStatusCode(404);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }
    public function testCreate(){
        $this->initAuthToken('bharatg');
        $this->createDummyFile();
        $data = ['name' => 'Test Announcement','groups'=>'[{"id":1},{"id":2}]','status'=>1,'start_date'=>date('Y-m-d H:i:s'),'end_date'=>date('Y-m-d H:i:s',strtotime("+7 day")),'file'=>'test-oxzionlogo.png'];
        $this->assertEquals(2, $this->getConnection()->getRowCount('ox_announcement'));
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/announcement', 'POST', null);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals($content['data']['status'], $data['status']);
        $this->assertEquals($content['data']['startdate'], $data['startdate']);
        $this->assertEquals($content['data']['enddate'], $data['enddate']);
        $this->assertEquals(3, $this->getConnection()->getRowCount('ox_announcement'));
    }
    public function testFileValidation(){
        $data = ['name' => 'Test Announcement','groups'=>'[{"id":1}]','status'=>1,'start_date'=>date('Y-m-d H:i:s'),'end_date'=>date('Y-m-d H:i:s',strtotime("+7 day"))];
        $this->assertEquals(2, $this->getConnection()->getRowCount('ox_announcement'));
        $this->initAuthToken('bharatg');
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/announcement', 'POST', null);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Validation Errors');
        $this->assertEquals($content['data']['errors']['media_location'], 'required');
    }
    public function testCreateWithOutNameFailure(){
        $this->initAuthToken('bharatg');
        $this->createDummyFile();
        $data = ['groups'=>'[{"id":1},{"id":2}]','status'=>1,'start_date'=>date('Y-m-d H:i:s'),'end_date'=>date('Y-m-d H:i:s',strtotime("+7 day"))];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/announcement', 'POST', null);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Validation Errors');
        $this->assertEquals($content['data']['errors']['name'], 'required');
    }
    public function testUpdate(){
        $data = ['name' => 'Test Announcement','groups'=>'[{"id":1}]','status'=>1,'start_date'=>date('Y-m-d H:i:s'),'end_date'=>date('Y-m-d H:i:s',strtotime("+7 day")),'file'=>'test-oxzionlogo.png'];
        $this->createDummyFile();
        $this->initAuthToken('bharatg');
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/announcement/1', 'PUT', null);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['id'], 1);
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals($content['data']['description'], $data['description']);
    }

    public function testUpdateNotFound(){
        $data = ['name' => 'Test Announcement','groups'=>'[{"id":1},{"id":2}]','status'=>1,'start_date'=>date('Y-m-d H:i:s'),'end_date'=>date('Y-m-d H:i:s',strtotime("+7 day"))];
        $this->createDummyFile();
        $this->initAuthToken('bharatg');
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/announcement/122', 'PUT', null);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }
    public function testAddGroupUpdate(){
        $data = ['name' => 'Test Announcement','groups'=>'[{"id":1},{"id":2}]','status'=>1,'start_date'=>date('Y-m-d H:i:s'),'end_date'=>date('Y-m-d H:i:s',strtotime("+7 day")),'file'=>'test-oxzionlogo.png'];
        $this->initAuthToken('bharatg');
        $this->createDummyFile();
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/announcement/1', 'PUT', null);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['id'], 1);
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals($content['data']['description'], $data['description']);
    }
    public function testRemoveGroupUpdate(){
        $data = ['name' => 'Test Announcement','groups'=>'[{"id":2}]','status'=>1,'start_date'=>date('Y-m-d H:i:s'),'end_date'=>date('Y-m-d H:i:s',strtotime("+7 day")),'file'=>'test-oxzionlogo.png'];
        $this->createDummyFile();
        $this->initAuthToken('bharatg');
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/announcement/1', 'PUT', null);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['id'], 1);
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals($content['data']['description'], $data['description']);
    }

    public function testDelete(){
        $this->initAuthToken('bharatg');
        $this->dispatch('/announcement/2', 'DELETE');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testDeleteNotFound(){
        $this->initAuthToken('bharatg');
        $this->dispatch('/announcement/122', 'DELETE');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');        
    }
}