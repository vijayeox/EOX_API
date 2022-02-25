<?php
namespace File;

use File\Controller\CommentController;
use Oxzion\Test\ControllerTest;
use Oxzion\Db\ModelTable;
use PHPUnit\DbUnit\TestCaseTrait;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Zend\Db\Sql\Sql;
use Zend\Db\Adapter\Adapter;
use Oxzion\Utils\FileUtils;

class CommentControllerTest extends ControllerTest
{
    public function setUp() : void
    {
        $this->loadConfig();
        parent::setUp();
    }

    public function tearDown() : void
    {
        parent::tearDown();
    }
    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__)."/../Dataset/Comment.yml");
        return $dataset;
    }
    protected function setDefaultAsserts()
    {
        $this->assertModuleName('File');
        $this->assertControllerName(CommentController::class); // as specified in router's controller name alias
        $this->assertControllerClass('CommentController');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    }
    public function testGetList()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/file/d13d0c68-98c9-11e9-adc5-308d99c9145b/comment', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $content['status']);
        $this->assertEquals(3, count($content['data']));
        $this->assertEquals("3ff78f56-5748-406b-9ce9-426242c5afc5", $content['data'][0]['commentId']);
        $this->assertEquals('Comment 1', $content['data'][0]['text']);
        $this->assertEquals("b223d10a-32f3-438e-94e8-6ac345f612aa", $content['data'][1]['commentId']);
        $this->assertEquals('Comment 2', $content['data'][1]['text']);
        $this->assertEquals("fcfb78b3-3389-4876-bfd9-1ffabdf50681", $content['data'][2]['commentId']);
        $this->assertEquals('Comment 3', $content['data'][2]['text']);
    }
    public function testGet()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/file/d13d0c68-98c9-11e9-adc5-308d99c9145b/comment/3ff78f56-5748-406b-9ce9-426242c5afc5', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals("3ff78f56-5748-406b-9ce9-426242c5afc5", $content['data']['commentId']);
        $this->assertEquals('Comment 1', $content['data']['text']);
        $this->assertNull($content['data']['attachments']);
    }
    public function testGetNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/file/d13d0c68-98c9-11e9-adc5-308d99c9145b/comment/d13d0c68-98c9-11e9-adc5-308d99c91466', 'GET');
        $this->assertResponseStatusCode(404);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }
    public function testCreate()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['text' => 'Comment 5','parent' => "c1c5828f-2424-4e80-a09b-d752d004a6c8"];
        $this->assertEquals(5, $this->getConnection()->getRowCount('ox_comment'));
        $this->dispatch('/file/e23d0c68-98c9-11e9-adc5-308d99c9146c/comment', 'POST', $data);
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['text'], $data['text']);
        $this->assertEquals(6, $this->getConnection()->getRowCount('ox_comment'));
    }
    public function testCreateWithOutTextFailure()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['parent' => "c1c5828f-2424-4e80-a09b-d752d004a6c8"];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/file/e23d0c68-98c9-11e9-adc5-308d99c9146c/comment', 'POST', null);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Validation Errors');
        $this->assertEquals($content['data']['errors']['text'], 'required');
    }

    // public function testCreateAccess()
    // {
    //     $this->initAuthToken($this->employeeUser);
    //     $data = ['text' => 'Comment 1','parent' => "c1c5828f-2424-4e80-a09b-d752d004a6c8"];
    //     $this->setJsonContent(json_encode($data));
    //     $this->dispatch('/file/d13d0c68-98c9-11e9-adc5-308d99c9145b/comment', 'POST', null);
    //     $content = (array)json_decode($this->getResponse()->getContent(), true);
    //     $this->assertResponseStatusCode(401);
    //     $this->assertModuleName('File');
    //     $this->assertControllerName(CommentController::class); // as specified in router's controller name alias
    //     $this->assertControllerClass('CommentController');
    //     $this->assertMatchedRouteName('Comment');
    //     $this->assertResponseHeaderContains('content-type', 'application/json');
    //     $this->assertEquals($content['status'], 'error');
    //     $this->assertEquals($content['message'], 'You have no Access to this API');
    // }
        
    public function testUpdate()
    {
        $data = ['text' => 'Updated Comment'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/file/d13d0c68-98c9-11e9-adc5-308d99c9145b/comment/3ff78f56-5748-406b-9ce9-426242c5afc5', 'PUT', null);
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['text'], $data['text']);
    }
    //TODO rules for preventing updates not implemented
    // public function testUpdateRestricted()
    // {
    //     $data = ['text' => 'Updated Comment'];
    //     $this->initAuthToken($this->employeeUser);
    //     $this->setJsonContent(json_encode($data));
    //     $this->dispatch('/file/d13d0c68-98c9-11e9-adc5-308d99c9145b/comment/3ff78f56-5748-406b-9ce9-426242c5afc5', 'PUT', null);
    //     $content = (array)json_decode($this->getResponse()->getContent(), true);
    //     print_r($content);exit;
    //     $this->assertResponseStatusCode(401);
    //     $this->assertModuleName('File');
    //     $this->assertControllerName(CommentController::class); // as specified in router's controller name alias
    //     $this->assertControllerClass('CommentController');
    //     $this->assertMatchedRouteName('Comment');
    //     $this->assertResponseHeaderContains('content-type', 'application/json');
    //     $this->assertEquals($content['status'], 'error');
    //     $this->assertEquals($content['message'], 'You have no Access to this API');
    // }
    
    public function testUpdateNotFound()
    {
        $data = ['text' => 'Updated Comment','parent' => "c1c5828f-2424-4e80-a09b-d752d004a6c8"];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/file/d13d0c68-98c9-11e9-adc5-308d99c9145b/comment/122', 'PUT', null);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testDelete()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/file/e23d0c68-98c9-11e9-adc5-308d99c9146c/comment/c1c5828f-2424-4e80-a09b-d752d004a6c8', 'DELETE');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testDeleteNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/file/e23d0c68-98c9-11e9-adc5-308d99c9146c/comment/1222', 'DELETE');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testGetChildList()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/file/d13d0c68-98c9-11e9-adc5-308d99c9145b/comment/3ff78f56-5748-406b-9ce9-426242c5afc5/getchildlist', 'POST');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

     public function testUpdateWithAccountIdInData()
    {
        $data = ['text' => 'Updated Comment', 'accountId' =>'53012471-2863-4949-afb1-e69b0891c98a'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/file/d13d0c68-98c9-11e9-adc5-308d99c9145b/comment/3ff78f56-5748-406b-9ce9-426242c5afc5', 'PUT', null);
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['text'], $data['text']);
    }

     public function testGetCommentAttachmentDetails()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/file/e23d0c68-98c9-11e9-adc5-308d99c9146c/comment/c1c5828f-2424-4e80-a09b-d752d004a6c8', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals("c1c5828f-2424-4e80-a09b-d752d004a6c8", $content['data']['commentId']);
        $this->assertEquals('Comment New', $content['data']['text']);
        $this->assertNotNull($content['data']['attachments']);
    }

    public function testGetParentCommentsList()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/file/d13d0c68-98c9-11e9-adc5-308d99c9145b/getParentCommentslist', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals("3ff78f56-5748-406b-9ce9-426242c5afc5", $content['data'][0]['commentId']);
        $this->assertEquals('Comment 1', $content['data'][0]['text']);
    }
}
