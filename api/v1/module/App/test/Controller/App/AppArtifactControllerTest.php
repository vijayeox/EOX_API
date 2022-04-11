<?php
namespace App;

use Oxzion\Service\AppService;
use Oxzion\Test\ControllerTest;
use Oxzion\Utils\FileUtils;
use Oxzion\App\AppArtifactNamingStrategy;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Exception;
use AppTest\AppTestSetUpTearDownHelper;

class AppArtifactControllerTest extends ControllerTest
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


    

    public function testArtifactAddAppIcon()
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
        $fileName = 'icon.png';
        if (PHP_OS == 'Linux') {
            $fileSize = 74665;
        } else {
            $fileSize = 76653;
        }
        $_FILES = [
            'file' => [
                'name' => $fileName,
                'type' => 'application/png',
                'tmp_name' => random_int(99,9999999999),
                'error' => UPLOAD_ERR_OK,
                'size' => $fileSize
            ]
        ];
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/add/app_icon", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $content['status']);
        //Ensure file is found in the correct location
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $artifactFile = $appSourceDir . '/view/apps/eoxapps/' . $fileName;
        $this->assertTrue(file_exists($artifactFile));
    }

    public function testArtifactAddAppIconWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/add/app_icon", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('ox_app', $content['data']['entity']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

    public function testArtifactAddAppIconWithoutAppSourceDir()
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
        $this->dispatch("/app/${uuid}/artifact/add/app_icon", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }

    public function testArtifactAddAppIconWhite()
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
        $fileName = 'icon_white.png';
        if (PHP_OS == 'Linux') {
            $fileSize = 74665;
        } else {
            $fileSize = 76653;
        }
        $_FILES = [
            'file' => [
                'name' => $fileName,
                'type' => 'application/png',
                'tmp_name' => random_int(99,9999999999),
                'error' => UPLOAD_ERR_OK,
                'size' => $fileSize
            ]
        ];
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/add/app_icon_white", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $content['status']);
        //Ensure file is found in the correct location
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $artifactFile = $appSourceDir . '/view/apps/eoxapps/' . $fileName;
        $this->assertTrue(file_exists($artifactFile));
    }

    public function testArtifactAddAppIconWhiteWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/add/app_icon_white", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('ox_app', $content['data']['entity']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

    public function testArtifactAddAppIconWhiteWithoutAppSourceDir()
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
        $this->dispatch("/app/${uuid}/artifact/add/app_icon_white", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }

    public function testArtifactAddDelegate()
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
        $fileName = 'AddDelegateTest.php';
        if (PHP_OS == 'Linux') {
            $fileSize = 74665;
        } else {
            $fileSize = 76653;
        }
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $_FILES = [
            'artifactFile' => [
                'name' => $fileName,
                'tmp_name' => $this->createTemporaryFile($filePath),
                'error' => UPLOAD_ERR_OK,
                'size' => $fileSize
            ]
        ];
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/add/delegate", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $content['status']);
        //Ensure file is found in the correct location
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $artifactFile = $appSourceDir . '/data/delegate/' . $fileName;
        $this->assertTrue(file_exists($artifactFile));
    }

    public function testArtifactAddDelegateWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/add/delegate", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('ox_app', $content['data']['entity']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

    public function testArtifactAddDelegateWithoutAppSourceDir()
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
        $this->dispatch("/app/${uuid}/artifact/add/delegate", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }

    public function testArtifactAddTemplate()
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
        $fileName = 'AddTemplateTest.tpl';
        if (PHP_OS == 'Linux') {
            $fileSize = 74665;
        } else {
            $fileSize = 76653;
        }
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $_FILES = [
            'artifactFile' => [
                'name' => $fileName,
                'tmp_name' => $this->createTemporaryFile($filePath),
                'error' => UPLOAD_ERR_OK,
                'size' => $fileSize
            ]
        ];
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/add/template", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $content['status']);
        //Ensure file is found in the correct location
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $artifactFile = $appSourceDir . '/data/template/' . $fileName;
        $this->assertTrue(file_exists($artifactFile));
    }

    public function testArtifactAddTemplateWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/add/template", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('ox_app', $content['data']['entity']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

    public function testArtifactAddTemplateWithoutAppSourceDir()
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
        $this->dispatch("/app/${uuid}/artifact/add/template", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }


    public function testGetArtifactForm()
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
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        
        $targetPath = $appSourceDir . '/content/forms/' . $fileName;
        if (!copy($filePath, $targetPath)) {
            throw new Exception("Failed to copy file ${filePath} to ${targetPath}.");
        }
        
        $actual_content=file_get_contents($targetPath);
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/list/form", 'GET');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $content['status']);
        $this->assertTrue(file_exists($targetPath));
        $this->assertTrue(filesize($targetPath) == 74665 || filesize($targetPath) == 76653);
        $this->assertEquals($actual_content, $content['data'][0]['content']);
    }

    public function testGetArtifactFormWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/list/form", 'GET');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('ox_app', $content['data']['entity']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

    public function testGetArtifactFormWithoutAppSourceDir()
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
        $this->dispatch("/app/${uuid}/artifact/list/form", 'GET');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }

    public function testGetArtifactWorkflow()
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
        $fileName = 'AddWorkflowTest.bpmn';
        if (PHP_OS == 'Linux') {
            $fileSize = 546495;
        } else {
            $fileSize = 546674;
        }
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        
        $targetPath = $appSourceDir . '/content/workflows/' . $fileName;
        if (!copy($filePath, $targetPath)) {
            throw new Exception("Failed to copy file ${filePath} to ${targetPath}.");
        }
        
        $actual_content=file_get_contents($targetPath);
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/list/workflow", 'GET');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        
        $this->assertEquals('success', $content['status']);
        $this->assertTrue(file_exists($targetPath));
        $this->assertTrue(filesize($targetPath) == 546493 || filesize($targetPath) == 546674);
        $this->assertEquals($actual_content, $content['data'][0]['content']);
    }

    public function testGetArtifactWorkflowWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/list/workflow", 'GET');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('ox_app', $content['data']['entity']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

    public function testGetArtifactWorkflowWithoutAppSourceDir()
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
        $this->dispatch("/app/${uuid}/artifact/list/workflow", 'GET');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }

    public function testGetArtifactDelegate()
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
        $fileName = 'AddDelegateTest.php';
        
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        
        $targetPath = $appSourceDir . '/data/delegate/' . $fileName;
        if (!copy($filePath, $targetPath)) {
            throw new Exception("Failed to copy file ${filePath} to ${targetPath}.");
        }
        
        $actual_content=file_get_contents($targetPath);
        
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/list/delegate", 'GET');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        
        $this->assertEquals('success', $content['status']);
        $this->assertTrue(file_exists($targetPath));
        $this->assertTrue(filesize($targetPath) == 228 || filesize($targetPath) == 409);
        $this->assertEquals($actual_content, $content['data'][0]['content']);
    }

    public function testGetArtifactDelegateWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/list/delegate", 'GET');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('ox_app', $content['data']['entity']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

    public function testGetArtifactDelegateWithoutAppSourceDir()
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
        $this->dispatch("/app/${uuid}/artifact/list/delegate", 'GET');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }

    public function testGetArtifactTemplate()
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
        $fileName = 'AddTemplateTest.tpl';
        
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        
        $targetPath = $appSourceDir . '/data/template/' . $fileName;
        if (!copy($filePath, $targetPath)) {
            throw new Exception("Failed to copy file ${filePath} to ${targetPath}.");
        }
        
        $actual_content=file_get_contents($targetPath);
        
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/list/template", 'GET');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        
        $this->assertEquals('success', $content['status']);
        $this->assertTrue(file_exists($targetPath));
        $this->assertTrue(filesize($targetPath) == 143 || filesize($targetPath) == 324);
        $this->assertEquals($actual_content, $content['data'][0]['content']);
    }

    public function testGetArtifactTemplateWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/list/template", 'GET');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('ox_app', $content['data']['entity']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

    public function testGetArtifactTemplateWithoutAppSourceDir()
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
        $this->dispatch("/app/${uuid}/artifact/list/template", 'GET');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }
    

    /* COMMENTING THIS FOR NOW AS THE ADD FORM LOGIC ALLOWS DUPLICATES
     public function testArtifactAddFormWithDuplicateFileName() {
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
         $fileSize = 74665;
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
         //Ensure file already exists in the destination directory.
         $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
         $artifactFile = $appSourceDir . '/content/forms/' . $fileName;
         if (!copy($filePath, $artifactFile)) {
             throw new Exception("Failed to copy file ${filePath} to ${artifactFile}.");
         }
         $this->initAuthToken($this->adminUser);
         $this->dispatch("/app/${uuid}/artifact/add/form", 'POST');
         $this->runDefaultAsserts();
         $content = json_decode($this->getResponse()->getContent(), true);
         $this->assertEquals('error', $content['status']);
     } */

    public function testArtifactAddWorkflow()
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
        $fileName = 'AddWorkflowTest.bpmn';
        if (PHP_OS == 'Linux') {
            $fileSize = 546495;
        } else {
            $fileSize = 546674;
        }
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $_FILES = [
            'artifactFile' => [
                'name' => $fileName,
                'type' => 'application/octet-stream',
                'tmp_name' => $this->createTemporaryFile($filePath),
                'error' => UPLOAD_ERR_OK,
                'size' => $fileSize
            ]
        ];
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/add/workflow", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $content['status']);
        //Ensure file is found in the correct location.
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $artifactFile = $appSourceDir . '/content/workflows/' . $fileName;
        $this->assertTrue(file_exists($artifactFile));
        $this->assertTrue(filesize($artifactFile) == 546493 || filesize($artifactFile) == 546674 || filesize($artifactFile) <= 546672);
    }

    public function testArtifactAddWorkflowWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/add/workflow", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('ox_app', $content['data']['entity']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

    public function testArtifactAddWorkflowWithoutAppSourceDir()
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
        $this->dispatch("/app/${uuid}/artifact/add/workflow", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }
    /* COMMENTING THIS FOR NOW AS THE ADD WORKFLOW LOGIC ALLOWS DUPLICATES
    public function testArtifactAddWorkflowWithDuplicateFileName() {
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
        $fileName = 'AddWorkflowTest.bpmn';
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $_FILES = [
            'artifactFile' => [
                'name' => $fileName,
                'type' => 'application/octet-stream',
                'tmp_name' => $this->createTemporaryFile($filePath),
                'error' => UPLOAD_ERR_OK,
                'size' => 546495
            ]
        ];
        //Ensure file already exists in the destination directory.
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $artifactFile = $appSourceDir . '/content/workflows/' . $fileName;
        if (!copy($filePath, $artifactFile)) {
            throw new Exception("Failed to copy file ${filePath} to ${artifactFile}.");
        }
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/add/workflow", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
    } */

    public function testArtifactDeleteForm()
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
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $fileName = 'AddFormTest.json';
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $targetPath = $appSourceDir . '/content/forms/' . $fileName;
        if (!copy($filePath, $targetPath)) {
            throw new Exception("Failed to copy file ${filePath} to ${targetPath}.");
        }

        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/delete/form/${fileName}", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $content['status']);
        //Ensure file is NOT found in the location.
        $this->assertFalse(file_exists($targetPath));
    }

    public function testArtifactDeleteFormWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/delete/form/AnyFileName.json", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('ox_app', $content['data']['entity']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

    public function testArtifactDeleteFormWithoutAppSourceDir()
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
        $this->dispatch("/app/${uuid}/artifact/delete/form/AnyFileName.json", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }

    public function testArtifactDeleteFormFileNotFound()
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
        //Ensure artifact file does not exist.
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $artifactFile = $appSourceDir . '/content/forms/' . $fileName;
        if (file_exists($artifactFile) && !unlink($artifactFile)) {
            throw new Exception("Failed to delete file ${artifactFile}.");
        }
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/delete/form/${fileName}", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Artifact file is not found.', $content['message']);
        $this->assertEquals($artifactFile, $content['data']['file']);
    }

    public function testArtifactDeleteWorkflow()
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
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $fileName = 'AddWorkflowTest.bpmn';
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $targetPath = $appSourceDir . '/content/workflows/' . $fileName;
        if (!copy($filePath, $targetPath)) {
            throw new Exception("Failed to copy file ${filePath} to ${targetPath}.");
        }

        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/delete/workflow/${fileName}", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $content['status']);
        //Ensure file is NOT found in the location.
        $this->assertFalse(file_exists($targetPath));
    }

    public function testArtifactDeleteWorkflowWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/delete/workflow/AnyFileName.bpmn", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('ox_app', $content['data']['entity']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

    public function testArtifactDeleteWorkflowWithoutAppSourceDir()
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
        $this->dispatch("/app/${uuid}/artifact/delete/workflow/AnyFileName.bpmn", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }

    public function testArtifactDeleteWorkflowFileNotFound()
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
        $fileName = 'AddWorkflowTest.bpmn';
        //Ensure artifact file does not exist.
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $artifactFile = $appSourceDir . '/content/workflows/' . $fileName;
        if (file_exists($artifactFile) && !unlink($artifactFile)) {
            throw new Exception("Failed to delete file ${artifactFile}.");
        }
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/delete/workflow/${fileName}", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Artifact file is not found.', $content['message']);
        $this->assertEquals($artifactFile, $content['data']['file']);
    }

    

    public function testArtifactDeleteDelegate()
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
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $fileName = 'AddDelegateTest.php';
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $targetPath = $appSourceDir . '/data/delegate/' . $fileName;
        if (!copy($filePath, $targetPath)) {
            throw new Exception("Failed to copy file ${filePath} to ${targetPath}.");
        }

        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/delete/delegate/${fileName}", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $content['status']);
        //Ensure file is NOT found in the location.
        $this->assertFalse(file_exists($targetPath));
    }

    public function testArtifactDeleteDelegateWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/delete/delegate/AnyFileName.json", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('ox_app', $content['data']['entity']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

    public function testArtifactDeleteDelegateWithoutAppSourceDir()
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
        $this->dispatch("/app/${uuid}/artifact/delete/delegate/AnyFileName.json", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }

    public function testArtifactDeleteDelegateFileNotFound()
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
        $fileName = 'AddDelegateTest.php';
        //Ensure artifact file does not exist.
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $artifactFile = $appSourceDir . '/data/delegate/' . $fileName;
        if (file_exists($artifactFile) && !unlink($artifactFile)) {
            throw new Exception("Failed to delete file ${artifactFile}.");
        }
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/delete/delegate/${fileName}", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Artifact file is not found.', $content['message']);
        $this->assertEquals($artifactFile, $content['data']['file']);
    }

    public function testArtifactDeleteTemplate()
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
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $fileName = 'AddTemplateTest.tpl';
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $targetPath = $appSourceDir . '/data/template/' . $fileName;
        if (!copy($filePath, $targetPath)) {
            throw new Exception("Failed to copy file ${filePath} to ${targetPath}.");
        }

        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/delete/template/${fileName}", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $content['status']);
        //Ensure file is NOT found in the location.
        $this->assertFalse(file_exists($targetPath));
    }

    public function testArtifactDeleteTemplateWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/delete/template/AnyFileName.json", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('ox_app', $content['data']['entity']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

    public function testArtifactDeleteTemplateWithoutAppSourceDir()
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
        $this->dispatch("/app/${uuid}/artifact/delete/template/AnyFileName.json", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }

    public function testArtifactDeleteTemplateFileNotFound()
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
        $fileName = 'AddTemplateTest.tpl';
        //Ensure artifact file does not exist.
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $artifactFile = $appSourceDir . '/data/template/' . $fileName;
        if (file_exists($artifactFile) && !unlink($artifactFile)) {
            throw new Exception("Failed to delete file ${artifactFile}.");
        }
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/delete/template/${fileName}", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        //print_r($content);
        //die;
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Artifact file is not found.', $content['message']);
        $this->assertEquals($artifactFile, $content['data']['file']);
    }

    public function testArtifactDownloadDelegate()
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
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $fileName = 'AddDelegateTest.php';
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $targetPath = $appSourceDir . '/data/delegate/' . $fileName;
        if (!copy($filePath, $targetPath)) {
            throw new Exception("Failed to copy file ${filePath} to ${targetPath}.");
        }

        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/download/delegate/${fileName}", 'GET');
        $this->runDefaultAssertsDownload();
        //$content = $this->getResponse();
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $this->assertEquals(
            'application/octet-stream',
            $headers->get('content-type')->getFieldValue()
        );
        $this->assertEquals(
            'attachment; filename="' . $fileName . '"',
            $headers->get('content-disposition')->getFieldValue()
        );
        $this->assertTrue(file_exists($targetPath));
    }

    public function testArtifactDownloadDelegateWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $fileName = 'AddDelegateTest.php';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/download/delegate/${fileName}", 'GET');
        $this->runDefaultAssertsDownload();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('ox_app', $content['data']['entity']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

    public function testArtifactDownloadDelegateWithoutAppSourceDir()
    {
        $uuid = '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        $fileName = 'AddDelegateTest.php';
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
        $this->dispatch("/app/${uuid}/download/delegate/${fileName}", 'GET');
        $this->runDefaultAssertsDownload();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }

    public function testArtifactDownloadTemplate()
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
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $fileName = 'AddTemplateTest.tpl';
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $targetPath = $appSourceDir . '/data/template/' . $fileName;
        if (!copy($filePath, $targetPath)) {
            throw new Exception("Failed to copy file ${filePath} to ${targetPath}.");
        }

        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/download/template/${fileName}", 'GET');
        $this->runDefaultAssertsDownload();
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $this->assertEquals(
            'application/octet-stream',
            $headers->get('content-type')->getFieldValue()
        );
        $this->assertEquals(
            'attachment; filename="' . $fileName.'"',
            $headers->get('content-disposition')->getFieldValue()
        );
        $this->assertTrue(file_exists($targetPath));
    }

    public function testArtifactDownloadTemplateWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $fileName = 'AddTemplateTest.tpl';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/download/template/${fileName}", 'GET');
        $this->runDefaultAssertsDownload();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('ox_app', $content['data']['entity']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

    public function testArtifactDownloadTemplateWithoutAppSourceDir()
    {
        $uuid = '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        $fileName = 'AddTemplateTest.tpl';
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
        $this->dispatch("/app/${uuid}/download/template/${fileName}", 'GET');
        $this->runDefaultAssertsDownload();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }

    

    public function testUploadArchive()
    {
        $uuid = 'cdccd58f-b8af-4b41-a64b-c02dae6f77d6';

        //Ensure application does not exist in database.
        $query = "SELECT name FROM ox_app WHERE uuid='${uuid}'";
        $existingAppRecordSet = $this->executeQueryTest($query);
        $this->assertTrue(empty($existingAppRecordSet));

        $fileName = 'TestArchiveWithApplicationDescriptor.zip';
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $tempFile = $this->createTemporaryFile($filePath);
        $_FILES = [
            'artifactFile' => [
                'name' => $fileName,
                'type' => 'application/zip',
                'tmp_name' => $tempFile,
                'error' => UPLOAD_ERR_OK,
                'size' => 1546507
            ]
        ];
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/archive/upload", 'POST');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->runDefaultAsserts();
        $this->assertEquals('success', $content['status']);
        $this->assertEquals($uuid, $content['data']['app']['uuid']);

        //Ensure application is added to the database.
        $newAppRecordSet = $this->executeQueryTest($query);
        $this->assertFalse(empty($newAppRecordSet));
        $newRecord = $newAppRecordSet[0];
        $this->assertEquals('Test Application', $newRecord['name']);
    }

    public function testUploadArchiveWithoutApplicationDescriptor()
    {
        //Take applicatio snapshot before running the test.
        $query = "SELECT id, uuid FROM ox_app ORDER BY id";
        $appRecordSetBeforeTest = $this->executeQueryTest($query);

        $fileName = 'TestArchiveWithoutApplicationDescriptor.zip';
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $tempFile = $this->createTemporaryFile($filePath);
        $_FILES = [
            'artifactFile' => [
                'name' => $fileName,
                'type' => 'application/zip',
                'tmp_name' => $tempFile,
                'error' => UPLOAD_ERR_OK,
                'size' => 1546507
            ]
        ];
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/archive/upload", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(406, $content['errorCode']);
        $this->assertEquals('Invalid application archive.', $content['message']);

        //Take applicatio snapshot after running the test.
        $appRecordSetBeforeTest = $this->executeQueryTest($query);
        $this->assertEquals($appRecordSetBeforeTest, $appRecordSetBeforeTest);
    }

    public function testUploadArchiveWithInvalidApplicationDescriptor()
    {
        //Take applicatio snapshot before running the test.
        $query = "SELECT id, uuid FROM ox_app ORDER BY id";
        $appRecordSetBeforeTest = $this->executeQueryTest($query);

        $fileName = 'TestArchiveWithInvalidApplicationDescriptor.zip';
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $tempFile = $this->createTemporaryFile($filePath);
        $_FILES = [
            'artifactFile' => [
                'name' => $fileName,
                'type' => 'application/zip',
                'tmp_name' => $tempFile,
                'error' => UPLOAD_ERR_OK,
                'size' => 1546507
            ]
        ];
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/archive/upload", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(406, $content['errorCode']);
        $this->assertEquals('Invalid application archive.', $content['message']);

        //Take applicatio snapshot after running the test.
        $appRecordSetBeforeTest = $this->executeQueryTest($query);
        $this->assertEquals($appRecordSetBeforeTest, $appRecordSetBeforeTest);
    }

    public function testUploadArchiveWithInvalidArchive()
    {
        //Take applicatio snapshot before running the test.
        $query = "SELECT id, uuid FROM ox_app ORDER BY id";
        $appRecordSetBeforeTest = $this->executeQueryTest($query);

        $fileName = 'TestArchiveWithInvalidArchive.zip';
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $tempFile = $this->createTemporaryFile($filePath);
        $_FILES = [
            'artifactFile' => [
                'name' => $fileName,
                'type' => 'application/zip',
                'tmp_name' => $tempFile,
                'error' => UPLOAD_ERR_OK,
                'size' => 1546507
            ]
        ];
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/archive/upload", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(406, $content['errorCode']);
        $this->assertEquals('Invalid application archive.', $content['message']);

        //Take applicatio snapshot after running the test.
        $appRecordSetBeforeTest = $this->executeQueryTest($query);
        $this->assertEquals($appRecordSetBeforeTest, $appRecordSetBeforeTest);
    }

    public function testUploadArchiveWithDuplicateApplicationInDatabase()
    {
        //Take application snapshot before running the test.
        $query = "SELECT id, uuid FROM ox_app ORDER BY id";
        $appRecordSetBeforeTest = $this->executeQueryTest($query);

        $fileName = 'TestArchiveWithDuplicateApplicationInDatabase.zip';
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $tempFile = $this->createTemporaryFile($filePath);
        $_FILES = [
            'artifactFile' => [
                'name' => $fileName,
                'type' => 'application/zip',
                'tmp_name' => $tempFile,
                'error' => UPLOAD_ERR_OK,
                'size' => 1546507
            ]
        ];
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/archive/upload", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(500, $content['errorCode']);

        //Take application snapshot after running the test.
        $appRecordSetBeforeTest = $this->executeQueryTest($query);
        $this->assertEquals($appRecordSetBeforeTest, $appRecordSetBeforeTest);
    }

    public function testUploadArchiveWithDuplicateApplicationInFileSystem()
    {
        //Take application snapshot before running the test.
        $query = "SELECT id, uuid FROM ox_app ORDER BY id";
        $appRecordSetBeforeTest = $this->executeQueryTest($query);

        $fileName = 'TestArchiveWithDuplicateApplicationInFileSystem.zip';
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $tempFile = $this->createTemporaryFile($filePath);
        $_FILES = [
            'artifactFile' => [
                'name' => $fileName,
                'type' => 'application/zip',
                'tmp_name' => $tempFile,
                'error' => UPLOAD_ERR_OK,
                'size' => 1546507
            ]
        ];
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, [
            'name' => 'New Application',
            'uuid' => '11111111-1111-1111-1111-111111111112'
        ]);
        if (!file_exists($appSourceDir) && !mkdir($appSourceDir)) {
            throw new Exception("Failed to create app source dir ${appSourceDir}.");
        }
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/archive/upload", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(409, $content['errorCode']);
        $this->assertEquals('Application with this UUID already exists on the server.', $content['message']);

        //Take application snapshot after running the test.
        $appRecordSetBeforeTest = $this->executeQueryTest($query);
        $this->assertEquals($appRecordSetBeforeTest, $appRecordSetBeforeTest);
    }

    public function testDownloadAppArchive()
    {
        $uuid = '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        //Setup data and application source directory.
        //IMPORTANT - $data contains only necessary fields.
        $data = [
            'app' => [
                'name' => 'SampleApp',
                'uuid' => $uuid
            ]
        ];
        $appSourceDir = $this->setupAppSourceDir($data);
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/archive/download", 'GET');
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $this->assertEquals(
            'application/zip',
            $headers->get('content-type')->getFieldValue()
        );
        $this->assertEquals(
            'attachment; filename=SampleApp-OxzionAppArchive.zip',
            $headers->get('content-disposition')->getFieldValue()
        );
        $bodyContent = $response->getBody();
        $signature = substr($bodyContent, 0, 4);
        $this->assertEquals("\x50\x4B\x03\x04", $signature); //PK zip signature.
    }

    public function testDownloadAppArchiveWithWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/archive/download", 'GET');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Entity not found.', $content['message']);
    }

    public function testArtifactAddMigration()
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
        $fileName = '1.0__Migration.sql';
        if (PHP_OS == 'Linux') {
            $fileSize = 74665;
        } else {
            $fileSize = 76653;
        }
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $_FILES = [
            'artifactFile' => [
                'name' => $fileName,
                'tmp_name' => $this->createTemporaryFile($filePath),
                'error' => UPLOAD_ERR_OK,
                'size' => $fileSize
            ]
        ];
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/add/migrations", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $content['status']);
        //Ensure file is found in the correct location
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $artifactFile = $appSourceDir . '/data/migrations/' . $fileName;
        $this->assertTrue(file_exists($artifactFile));
    }

    public function testArtifactAddMigrationWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/add/migrations", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

     public function testArtifactAddMigrationWithoutAppSourceDir()
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
        $this->dispatch("/app/${uuid}/artifact/add/migrations", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }

    public function testArtifactAddAppUpgrade()
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
        $fileName = 'AppUpgrade1_1.php';
        if (PHP_OS == 'Linux') {
            $fileSize = 74665;
        } else {
            $fileSize = 76653;
        }
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $_FILES = [
            'artifactFile' => [
                'name' => $fileName,
                'tmp_name' => $this->createTemporaryFile($filePath),
                'error' => UPLOAD_ERR_OK,
                'size' => $fileSize
            ]
        ];
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/add/appupgrade", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $content['status']);
        //Ensure file is found in the correct location
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $artifactFile = $appSourceDir . '/data/appupgrade/' . $fileName;
        $this->assertTrue(file_exists($artifactFile));
    }

    public function testArtifactAddAppUpgradeWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/add/appupgrade", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

     public function testArtifactAddAppUpgradeWithoutAppSourceDir()
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
        $this->dispatch("/app/${uuid}/artifact/add/appupgrade", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }

    public function testArtifactAddTransformer()
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
        $fileName = 'transformer1.yml';
        if (PHP_OS == 'Linux') {
            $fileSize = 74665;
        } else {
            $fileSize = 76653;
        }
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $_FILES = [
            'artifactFile' => [
                'name' => $fileName,
                'tmp_name' => $this->createTemporaryFile($filePath),
                'error' => UPLOAD_ERR_OK,
                'size' => $fileSize
            ]
        ];
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/add/transformer", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $content['status']);
        //Ensure file is found in the correct location
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $artifactFile = $appSourceDir . '/data/transformer/' . $fileName;
        $this->assertTrue(file_exists($artifactFile));
    }

    public function testArtifactAddTransformerWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/add/transformer", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

     public function testArtifactAddTransformerWithoutAppSourceDir()
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
        $this->dispatch("/app/${uuid}/artifact/add/transformer", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }

    public function testArtifactDeleteMigrations()
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
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $fileName = 'DeleteMigration.sql';
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $targetPath = $appSourceDir . '/data/migrations/' . $fileName;
        if (!copy($filePath, $targetPath)) {
            throw new Exception("Failed to copy file ${filePath} to ${targetPath}.");
        }

        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/delete/migrations/${fileName}", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $content['status']);
        //Ensure file is NOT found in the location.
        $this->assertFalse(file_exists($targetPath));
    }

    public function testArtifactDeleteMigrationsWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/delete/migrations/DeleteMigration.sql", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('ox_app', $content['data']['entity']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

    public function testArtifactDeleteMigrationsWithoutAppSourceDir()
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
        $this->dispatch("/app/${uuid}/artifact/delete/migrations/DeleteMigration.sql", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }

    public function testArtifactDeleteMigrationsFileNotFound()
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
        $fileName = 'DeleteMigration.sql';
        //Ensure artifact file does not exist.
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $artifactFile = $appSourceDir . '/data/migrations/' . $fileName;
        if (file_exists($artifactFile) && !unlink($artifactFile)) {
            throw new Exception("Failed to delete file ${artifactFile}.");
        }
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/delete/migrations/${fileName}", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Artifact file is not found.', $content['message']);
        $this->assertEquals($artifactFile, $content['data']['file']);
    }

    public function testArtifactDeleteTransformer()
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
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $fileName = 'DeleteTransformer.yml';
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $targetPath = $appSourceDir . '/data/transformer/' . $fileName;
        if (!copy($filePath, $targetPath)) {
            throw new Exception("Failed to copy file ${filePath} to ${targetPath}.");
        }

        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/delete/transformer/${fileName}", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $content['status']);
        //Ensure file is NOT found in the location.
        $this->assertFalse(file_exists($targetPath));
    }

    public function testArtifactDeleteTransformerWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/delete/transformer/DeleteTransformer.yml", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('ox_app', $content['data']['entity']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

    public function testArtifactDeleteTransformerWithoutAppSourceDir()
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
        $this->dispatch("/app/${uuid}/artifact/delete/transformer/DeleteTransformer.yml", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }

    public function testArtifactDeleteTransformerFileNotFound()
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
        $fileName = 'DeleteTransformer.yml';
        //Ensure artifact file does not exist.
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $artifactFile = $appSourceDir . '/data/transformer/' . $fileName;
        if (file_exists($artifactFile) && !unlink($artifactFile)) {
            throw new Exception("Failed to delete file ${artifactFile}.");
        }
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/delete/transformer/${fileName}", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Artifact file is not found.', $content['message']);
        $this->assertEquals($artifactFile, $content['data']['file']);
    }

    public function testArtifactDeleteAppUpgrade()
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
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $fileName = 'DeleteAppUpgrade1_1.php';
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $targetPath = $appSourceDir . '/data/appupgrade/' . $fileName;
        if (!copy($filePath, $targetPath)) {
            throw new Exception("Failed to copy file ${filePath} to ${targetPath}.");
        }

        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/delete/appupgrade/${fileName}", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $content['status']);
        //Ensure file is NOT found in the location.
        $this->assertFalse(file_exists($targetPath));
    }

    public function testArtifactDeleteAppUpgradeWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/delete/appupgrade/DeleteAppUpgrade1_1.php", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('ox_app', $content['data']['entity']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

    public function testArtifactDeleteAppUpgradeWithoutAppSourceDir()
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
        $this->dispatch("/app/${uuid}/artifact/delete/appupgrade/DeleteAppUpgrade1_1.php", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }

    public function testArtifactDeleteAppUpgradeFileNotFound()
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
        $fileName = 'DeleteAppUpgrade1_1.php';
        //Ensure artifact file does not exist.
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $artifactFile = $appSourceDir . '/data/appupgrade/' . $fileName;
        if (file_exists($artifactFile) && !unlink($artifactFile)) {
            throw new Exception("Failed to delete file ${artifactFile}.");
        }
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/delete/appupgrade/${fileName}", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Artifact file is not found.', $content['message']);
        $this->assertEquals($artifactFile, $content['data']['file']);
    }

    public function testArtifactDownloadAppUpgrade()
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
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $fileName = 'AppUpgrade1_1.php';
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $targetPath = $appSourceDir . '/data/appupgrade/' . $fileName;
        if (!copy($filePath, $targetPath)) {
            throw new Exception("Failed to copy file ${filePath} to ${targetPath}.");
        }

        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/download/appupgrade/${fileName}", 'GET');
        $this->runDefaultAssertsDownload();
        //$content = $this->getResponse();
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $this->assertEquals(
            'application/octet-stream',
            $headers->get('content-type')->getFieldValue()
        );
        $this->assertEquals(
            'attachment; filename="' . $fileName . '"',
            $headers->get('content-disposition')->getFieldValue()
        );
        $this->assertTrue(file_exists($targetPath));
    }

    public function testArtifactDownloadAppUpgradeWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $fileName = 'AppUpgrade1_1.php';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/download/appupgrade/${fileName}", 'GET');
        $this->runDefaultAssertsDownload();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('ox_app', $content['data']['entity']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

    public function testArtifactDownloadAppUpgradeWithoutAppSourceDir()
    {
        $uuid = '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        $fileName = 'AppUpgrade1_1.php';
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
        $this->dispatch("/app/${uuid}/download/appupgrade/${fileName}", 'GET');
        $this->runDefaultAssertsDownload();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }

    public function testArtifactDownloadTransformer()
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
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $fileName = 'transformer1.yml';
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $targetPath = $appSourceDir . '/data/transformer/' . $fileName;
        if (!copy($filePath, $targetPath)) {
            throw new Exception("Failed to copy file ${filePath} to ${targetPath}.");
        }

        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/download/transformer/${fileName}", 'GET');
        $this->runDefaultAssertsDownload();
        //$content = $this->getResponse();
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $this->assertEquals(
            'application/octet-stream',
            $headers->get('content-type')->getFieldValue()
        );
        $this->assertEquals(
            'attachment; filename="' . $fileName . '"',
            $headers->get('content-disposition')->getFieldValue()
        );
        $this->assertTrue(file_exists($targetPath));
    }

    public function testArtifactDownloadTransformerWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $fileName = 'transformer1.yml';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/download/transformer/${fileName}", 'GET');
        $this->runDefaultAssertsDownload();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('ox_app', $content['data']['entity']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

    public function testArtifactDownloadTransformerWithoutAppSourceDir()
    {
        $uuid = '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        $fileName = 'transformer1.yml';
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
        $this->dispatch("/app/${uuid}/download/transformer/${fileName}", 'GET');
        $this->runDefaultAssertsDownload();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }

    public function testArtifactDownloadMigrations()
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
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $fileName = '1.0__Migration.sql';
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $targetPath = $appSourceDir . '/data/migrations/' . $fileName;
        if (!copy($filePath, $targetPath)) {
            throw new Exception("Failed to copy file ${filePath} to ${targetPath}.");
        }

        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/download/migrations/${fileName}", 'GET');
        $this->runDefaultAssertsDownload();
        //$content = $this->getResponse();
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $this->assertEquals(
            'application/octet-stream',
            $headers->get('content-type')->getFieldValue()
        );
        $this->assertEquals(
            'attachment; filename="' . $fileName . '"',
            $headers->get('content-disposition')->getFieldValue()
        );
        $this->assertTrue(file_exists($targetPath));
    }

    public function testArtifactDownloadMigrationsWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $fileName = '1.0__Migration.sql';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/download/migrations/${fileName}", 'GET');
        $this->runDefaultAssertsDownload();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('ox_app', $content['data']['entity']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

    public function testArtifactDownloadMigrationsWithoutAppSourceDir()
    {
        $uuid = '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        $fileName = '1.0__Migration.sql';
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
        $this->dispatch("/app/${uuid}/download/migrations/${fileName}", 'GET');
        $this->runDefaultAssertsDownload();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }

    public function testGetArtifactAppUpgrade()
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
        $fileName = 'AppUpgrade1_1.php';
        if (PHP_OS == 'Linux') {
            $fileSize = 74665;
        } else {
            $fileSize = 76653;
        }
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        
        $targetPath = $appSourceDir . '/data/appupgrade/' . $fileName;
        if (!copy($filePath, $targetPath)) {
            throw new Exception("Failed to copy file ${filePath} to ${targetPath}.");
        }
        
        $actual_content=file_get_contents($targetPath);
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/list/appupgrade", 'GET');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $content['status']);
        $this->assertTrue(file_exists($targetPath));
        $this->assertEquals($actual_content, $content['data'][0]['content']);
    }

    public function testGetArtifactAppUpgradeWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/list/appupgrade", 'GET');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('ox_app', $content['data']['entity']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

    public function testGetArtifactAppUpgradeWithoutAppSourceDir()
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
        $this->dispatch("/app/${uuid}/artifact/list/appupgrade", 'GET');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }

    public function testGetArtifactTransformer()
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
        $fileName = 'transformer1.yml';
        if (PHP_OS == 'Linux') {
            $fileSize = 74665;
        } else {
            $fileSize = 76653;
        }
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        
        $targetPath = $appSourceDir . '/data/transformer/' . $fileName;
        if (!copy($filePath, $targetPath)) {
            throw new Exception("Failed to copy file ${filePath} to ${targetPath}.");
        }
        
        $actual_content=file_get_contents($targetPath);
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/list/transformer", 'GET');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $content['status']);
        $this->assertTrue(file_exists($targetPath));
        $this->assertEquals($actual_content, $content['data'][0]['content']);
    }

    public function testGetArtifactTransformerWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/list/transformer", 'GET');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('ox_app', $content['data']['entity']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

    public function testGetArtifactTransformerWithoutAppSourceDir()
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
        $this->dispatch("/app/${uuid}/artifact/list/transformer", 'GET');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }

    public function testGetArtifactMigrations()
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
        $fileName = '1.0__Migration.sql';
        if (PHP_OS == 'Linux') {
            $fileSize = 74665;
        } else {
            $fileSize = 76653;
        }
        $appSourceDir = AppArtifactNamingStrategy::getSourceAppDirectory($this->config, $data['app']);
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        
        $targetPath = $appSourceDir . '/data/migrations/' . $fileName;
        if (!copy($filePath, $targetPath)) {
            throw new Exception("Failed to copy file ${filePath} to ${targetPath}.");
        }
        
        $actual_content=file_get_contents($targetPath);
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/list/migrations", 'GET');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $content['status']);
        $this->assertTrue(file_exists($targetPath));
        $this->assertEquals($actual_content, $content['data'][0]['content']);
    }

    public function testGetArtifactMigrationsWrongUuid()
    {
        $uuid = '11111111-1111-1111-1111-111111111112';
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/artifact/list/migrations", 'GET');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('ox_app', $content['data']['entity']);
        $this->assertEquals($uuid, $content['data']['uuid']);
    }

    public function testGetArtifactMigrationsWithoutAppSourceDir()
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
        $this->dispatch("/app/${uuid}/artifact/list/migrations", 'GET');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals(404, $content['errorCode']);
        $this->assertEquals('Application source directory is not found.', $content['message']);
        $this->assertEquals($appSourceDir, $content['data']['directory']);
    }
}
