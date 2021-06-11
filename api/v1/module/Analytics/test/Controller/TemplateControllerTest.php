<?php
namespace Analytics;

use Analytics\Controller\TemplateController;
use Oxzion\Test\ControllerTest;
use Oxzion\Utils\FileUtils;
use PHPUnit\DbUnit\DataSet\DefaultDataSet;

class TemplateControllerTest extends ControllerTest
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

    protected function setDefaultAsserts()
    {
        $this->assertModuleName('Analytics');
        $this->assertControllerName(TemplateController::class); // as specified in router's controller name alias
        $this->assertControllerClass('TemplateController');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    }

    public function testTemplateCreate()
    {
        $this->initAuthToken($this->adminUser);
        $config = $this->getApplicationConfig();
        $tempFolder = $config['TEMPLATE_FOLDER'] . "/OITemplate/test/";
        FileUtils::createDirectory($tempFolder);
        $data = array('name' => 'testTemplate', 'content' => "Test template content");
        $this->dispatch('/analytics/template', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        // print_r($content);exit;
        $this->assertResponseStatusCode(201);
        $this->assertModuleName('Analytics');
        $this->assertControllerName(TemplateController::class); // as specified in router's controller name alias
        $this->assertControllerClass('TemplateController');
        $this->assertMatchedRouteName('template');
        $this->assertEquals($content['status'], 'success');
    }

    public function testTemplateCreateWithoutFileName()
    {
        $this->initAuthToken($this->adminUser);
        $config = $this->getApplicationConfig();
        $tempFolder = $config['TEMPLATE_FOLDER'] . "/OITemplate/test/";
        FileUtils::createDirectory($tempFolder);
        $data = array('content' => "Test template content");
        $this->dispatch('/analytics/template', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('Analytics');
        $this->assertControllerName(TemplateController::class); // as specified in router's controller name alias
        $this->assertControllerClass('TemplateController');
        $this->assertMatchedRouteName('template');
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Template name does not exist');
    }

    public function testTemplateCreateEmptyFileName()
    {
        $this->initAuthToken($this->adminUser);
        $config = $this->getApplicationConfig();
        $tempFolder = $config['TEMPLATE_FOLDER'] . "/OITemplate/test/";
        FileUtils::createDirectory($tempFolder);
        $data = array('name' => '', 'content' => "Test template content");
        $this->dispatch('/analytics/template', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(500);
        $this->assertModuleName('Analytics');
        $this->assertControllerName(TemplateController::class); // as specified in router's controller name alias
        $this->assertControllerClass('TemplateController');
        $this->assertMatchedRouteName('template');
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'File Name is empty');
    }
}
