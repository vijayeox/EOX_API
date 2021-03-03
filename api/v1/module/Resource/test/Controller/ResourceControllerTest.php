<?php
namespace Resource;

use Oxzion\Test\ControllerTest;
use Oxzion\Utils\FileUtils;
use PHPUnit\DbUnit\DataSet\ArrayDataSet;
use Resource\Controller\ResourceController;

class ResourceControllerTest extends ControllerTest
{
    
    protected $table;
    public function setUp(): void
    {
        $this->loadConfig();
        parent::setUp();
    }

    public function getDataSet()
    {
        $config = $this->getApplicationConfig();
        $dataset = new ArrayDataSet(array('ox_attachment'=>array(array(
            'id' => 1,
            'uuid' => 'test',
            'path' => $config['UPLOAD_FOLDER'] .'account/1/announcements/oxzionlogo.png',
            'extension' => 'png',
            'file_name' => 'oxzionlogo.png',
            'type' => 'ANNOUNCEMENT',
            'created_id' => 1,
            'account_id' => 1
        ))));
        return $dataset;
    }

    public function testResourceGet()
    {
        $this->initAuthToken($this->adminUser);
        $config = $this->getApplicationConfig();
        $tempFolder = $config['UPLOAD_FOLDER'] . "account/" . $this->testAccountId . "/announcements/";
        FileUtils::createDirectory($tempFolder);
        copy(__DIR__ . "/../files/oxzionlogo.png", $tempFolder . "oxzionlogo.png");
        $this->dispatch('/resource/test', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Resource');
        $this->assertControllerName(ResourceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('ResourceController');
        $this->assertMatchedRouteName('resource');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $response = $this->getResponse();
        $this->assertNotEquals(strlen($response), 0);
    }

    public function testResourceNotFoundGet()
    {
        $this->initAuthToken($this->adminUser);
        $config = $this->getApplicationConfig();
        $tempFolder = $config['UPLOAD_FOLDER'] . "account/" . $this->testAccountId . "/announcements/";
        FileUtils::createDirectory($tempFolder);
        copy(__DIR__ . "/../files/oxzionlogo.png", $tempFolder . "oxzionlogo.png");
        $this->dispatch('/resource/notfound', 'GET');
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('Resource');
        $this->assertControllerName(ResourceController::class); // as specified in router's controller name alias
        $this->assertControllerClass('ResourceController');
        $this->assertMatchedRouteName('resource');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }
}
