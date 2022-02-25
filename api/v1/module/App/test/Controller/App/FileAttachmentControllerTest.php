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
        switch ($this->getName()) {
            case 'testFileAttachmentAdd':
                //return new YamlDataSet(dirname(__FILE__) . "/../../Dataset/BusinessRelationship.yml");
                return new YamlDataSet(dirname(__FILE__) . "/../../Dataset/fileattachment.yaml");
                break;
            case 'testFileAttachmentRename':
            case 'testFileAttachmentDelete':
            case 'testFileAttachmentDeleteWrongAttachmentid':
            case 'testFileAttachmentDeleteWrongFileid':
            case 'testFileAttachmentRenameWrongAttachmentid':
            case 'testFileAttachmentRenameWrongFileid':
            //case 'testFileAttachmentDeleteNoFile':
                //Return empty data set to keep framework happy!
                return new YamlDataSet(dirname(__FILE__) . "/../../Dataset/addattachment.yaml");
            break;
        }

        //return new YamlDataSet(dirname(__FILE__) . "/../../Dataset/fileattachment.yaml");
    }

    protected function runDefaultAsserts()
    {
        $this->assertModuleName('App');
        $this->assertControllerClass('FileAttachmentController');
        $this->assertControllerName('App\Controller\FileAttachmentController');
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

    public function testFileAttachmentAdd()
    {
        $uuid = '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        //Setup data and application source directory.
        
        $fileName = 'AddFormTest.json';
        if (PHP_OS == 'Linux') {
            $fileSize = 74665;
        } else {
            $fileSize = 76653;
        }
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $_FILES = [
            'file' => [
                'name' => $fileName,
                'type' => 'application/json',
                'tmp_name' => $this->createTemporaryFile($filePath),
                'error' => UPLOAD_ERR_OK,
                'size' => $fileSize
            ]
        ];
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/file/attachment", 'POST');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $targetFile=$content['data']['path'];
        $this->assertEquals('success', $content['status']);
        $this->assertTrue(file_exists($targetFile));
        $this->assertTrue(filesize($targetFile) == 74665 || filesize($targetFile) == 76653);
        
    }

    public function testFileAttachmentDelete()
    {
        $account_uuid='53012471-2863-4949-afb1-e69b0891c98a';
        $uuid = '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        $fileid='e0517075-39f9-438e-861c-1a80ab05b8da';
        $attachmentid='e0517075-39f9-438e-861c-1a80ab05b8da';
        
        //Setup data and application source directory.
        
        $fileName = 'AddFormTest.json';
        if (PHP_OS == 'Linux') {
            $fileSize = 74665;
        } else {
            $fileSize = 76653;
        }
        $filePath = __DIR__ . '/../../Dataset/' . $fileName;
        $targetPath = $this->config['APP_DOCUMENT_FOLDER'].$account_uuid.'/temp/'.$fileid."/".$fileName;
        if (!copy($filePath, $targetPath)) {
            throw new Exception("Failed to copy file ${filePath} to ${targetPath}.");
        }
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/file/${fileid}/attachment/${attachmentid}/remove", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $content['status']);
        $this->assertFalse(file_exists($targetPath));
    }


    public function testFileAttachmentDeleteWrongAttachmentid()
    {
        $account_uuid='53012471-2863-4949-afb1-e69b0891c98a';
        $uuid = '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        $fileid='c6276b23-fa51-4363-adc7-753a9235a2d1';
        $attachmentid='1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/file/${fileid}/attachment/${attachmentid}/remove", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals('Incorrect attachment uuid specified', $content['message']);
    }


    public function testFileAttachmentDeleteWrongFileid()
    {
        $account_uuid='53012471-2863-4949-afb1-e69b0891c98a';
        $uuid = '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        $fileid='1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        $attachmentid='e0517075-39f9-438e-861c-1a80ab05b8da';
        
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/file/${fileid}/attachment/${attachmentid}/remove", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals('Incorrect file uuid specified', $content['message']);
    }

    public function testFileAttachmentRename()
    {
        $account_uuid='53012471-2863-4949-afb1-e69b0891c98a';
        $uuid = '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        $fileid='e0517075-39f9-438e-861c-1a80ab05b8da';
        $attachmentid='e0517075-39f9-438e-861c-1a80ab05b8da';
        
        //Setup data and application source directory.
        $fileName_orig='AddFormTest.json';
        $fileName = 'AddFormTest_New3.json';
        if (PHP_OS == 'Linux') {
            $fileSize = 74665;
        } else {
            $fileSize = 76653;
        }
        $filePath = __DIR__ . '/../../Dataset/' . $fileName_orig;
        $targetPath = $this->config['APP_DOCUMENT_FOLDER'].$account_uuid.'/temp/'.$fileid."/".$fileName;
        if (!copy($filePath, $targetPath)) {
            throw new Exception("Failed to copy file ${filePath} to ${targetPath}.");
        }
        $this->initAuthToken($this->adminUser);
        $data = ['name' => $fileName];
        $this->dispatch("/app/${uuid}/file/${fileid}/attachment/${attachmentid}", 'POST',$data);
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $content['status']);
        $this->assertTrue(file_exists($targetPath));
        $this->assertTrue(filesize($targetPath) == 74665 || filesize($targetPath) == 76653);
        
    }
    
    public function testFileAttachmentRenameWrongAttachmentid()
    {
        $account_uuid='53012471-2863-4949-afb1-e69b0891c98a';
        $uuid = '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        $fileid='c6276b23-fa51-4363-adc7-753a9235a2d1';
        $attachmentid='1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/file/${fileid}/attachment/${attachmentid}/remove", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals('Incorrect attachment uuid specified', $content['message']);
    }

    public function testFileAttachmentRenameWrongFileid()
    {
        $account_uuid='53012471-2863-4949-afb1-e69b0891c98a';
        $uuid = '1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        $fileid='1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4';
        $attachmentid='e0517075-39f9-438e-861c-1a80ab05b8da';
        
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/app/${uuid}/file/${fileid}/attachment/${attachmentid}/remove", 'DELETE');
        $this->runDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals('Incorrect file uuid specified', $content['message']);
    }

    
}
