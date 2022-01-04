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


    /* HARI */

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
        //$filePath = __DIR__ . '/../../Dataset/' . $fileName;
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
        //$this->assertTrue(filesize($artifactFile) == 74665 || filesize($artifactFile) == 76653);
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
        //$filePath = __DIR__ . '/../../Dataset/' . $fileName;
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
        //$this->assertTrue(filesize($artifactFile) == 74665 || filesize($artifactFile) == 76653);
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
                //'type' => 'application/json',
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
        //$this->assertTrue(filesize($artifactFile) == 74665 || filesize($artifactFile) == 76653);
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
                //'type' => 'application/json',
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
        //$this->assertTrue(filesize($artifactFile) == 74665 || filesize($artifactFile) == 76653);
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

    /* HARI */

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
}
