<?php
namespace App;

use Oxzion\Service\AppService;
use Oxzion\Test\ControllerTest;
use Oxzion\Utils\FileUtils;
use Oxzion\App\AppArtifactNamingStrategy;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Exception;
use AppTest\AppTestSetUpTearDownHelper;

class FileAttachmentControllerTest extends ControllerTest
{
    private $setUpTearDownHelper = null;
    private $config = null;

    public function __construct()
    {
        parent::__construct();
        $this->loadConfig();
        $this->config = $this->getApplicationConfig();
        $this->setUpTearDownHelper = new AppTestSetUpTearDownHelper($this->config);
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTearDownHelper->cleanAll();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->setUpTearDownHelper->cleanAll();
    }

    public function getDataSet()
    {
        return new YamlDataSet(dirname(__FILE__) . "/../../Dataset/Workflow.yml");
    }

    protected function runDefaultAsserts()
    {
        $this->assertModuleName('App');
        $this->assertControllerClass('AppArtifactController');
        $this->assertControllerName('App\Controller\AppArtifactController');
        $contentTypeHeader = $this->getResponseHeader('content-type')->toString();
        $contentTypeRegex = '/application\/json(;? *?charset=utf-8)?/i';
        $this->assertTrue(preg_match($contentTypeRegex, $contentTypeHeader) ? true : false);
    }

    protected function runDefaultAssertsDownload()
    {
        $this->assertModuleName('App');
        $this->assertControllerClass('AppArtifactController');
        $this->assertControllerName('App\Controller\AppArtifactController');
        $contentTypeHeader = $this->getResponseHeader('content-type')->toString();
        $contentTypeRegex = '/application\/octet-stream(;? *?charset=utf-8)?/i';
        //$this->assertTrue(preg_match($contentTypeRegex, $contentTypeHeader) ? true : false);
    }

    private function setupAppSourceDir($ymlData)
    {
        $appService = $this->getApplicationServiceLocator()->get(AppService::class);
        return $appService->setupOrUpdateApplicationDirectoryStructure($ymlData);
    }

    private function createTemporaryFile($sourceFilePath)
    {
        $tempDir = sys_get_temp_dir();
        $tempFilePath = tempnam($tempDir, '');
        if (!copy($sourceFilePath, $tempFilePath)) {
            throw new Exception("Failed to copy ${sourceFilePath} to ${tempFilePath}");
        }
        return $tempFilePath;
    }

    public function testArtifactAddForm()
    {
        $uuid = '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        //Setup data and application source directory.
        $data = [
            'app' => [
                'name' => 'Test Application',
                'uuid' => $uuid,
                'type' => 2,
                'category' => 'TestCategory',
                'logo' => 'app.png'
            ]
        ];
        $this->setupAppSourceDir($data);
        $fileName = 'AddFormTest.json';
        if (PHP_OS == 'Linux') {
            $fileSize = 74665;
        } else {
            $fileSize = 76653;
        }
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $_FILES = [
            'artifactFile' => [
                'name' => $fileName,
                'type' => 'application/json',
                'tmp_name' => $this->createTemporaryFile($filePath),
                'error' => UPLOAD_ERR_OK,
                'size' => $fileSize
            ]
        ];
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/add/form", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $content['status']);
        //Ensure file is found in the correct location
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $artifactFile = $appSourceDir . '/content/forms/' . $fileName;
        $this->assertTrue(file_exists($artifactFile));
        $this->assertTrue(filesize($artifactFile) == 74665 || filesize($artifactFile) == 76653);
    }

    public function testArtifactAddFormWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/add/form", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('ox_app', $content['data']['entity']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

    public function testArtifactAddFormWithoutAppSourceDir()
    {
        $uuid = '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        //Setup data.
        $data = [
            'app' => [
                'name' => 'Test Application',
                'uuid' => $uuid,
                'type' => 2,
                'category' => 'TestCategory',
                'logo' => 'app.png'
            ]
        ];
        //Ensure app source dir does not exist.
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        try {
            FileUtils::rmDir($appSourceDir);
        } catch (Exception $ignored) {
        }
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/add/form", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }


    

    
}
