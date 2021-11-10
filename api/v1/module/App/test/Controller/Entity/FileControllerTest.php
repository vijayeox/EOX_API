<?php
namespace App;

use App\Controller\FileController;
use Oxzion\Encryption\Crypto;
use Oxzion\Test\ControllerTest;
use Oxzion\Utils\FileUtils;
use PHPUnit\DbUnit\DataSet\YamlDataSet;

class FileControllerTest extends ControllerTest
{
    public function setUp(): void
    {
        $this->loadConfig();
        parent::setUp();
        $this->config = $this->getApplicationConfig();
    }
    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__) . "/../../Dataset/Workflow.yml");
        return $dataset;
    }

    protected function setDefaultAsserts()
    {
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class); // as specified in router's controller name alias
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('appfile');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    }

    private function getFieldUuid()
    {
        $selctQuery = "SELECT * from ox_form where id=1";
        $selectResult = $this->executeQueryTest($selctQuery);
        return $selectResult;
    }

    public function testGetListOfFollowupsfiles()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/followups/createdBy/me', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('followups');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['total'], 3);
    }

    public function testGet()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/crud/d13d0c68-98c9-11e9-adc5-308d99c9145b', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['uuid'], 'd13d0c68-98c9-11e9-adc5-308d99c9145b');
    }
    public function testGetNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/crud/202d5c14-df9a-11e9-9d36-2a2ae2dbcce4', 'GET');
        $this->assertResponseStatusCode(404);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }
    public function testCreate()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['field1' => '1', 'field2' => '2', 'entity_id' => 1,'policyStatus' => 'On Going','expiry_date' => '2019-01-01'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/crud', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['field1'], $data['field1']);
        $fileId = $content['data']['uuid'];

        $selctQuery = "SELECT oxf.id, oxf.data,oxf.version as fileVersion from ox_file oxf where oxf.uuid='$fileId'";
        $selectResult = $this->executeQueryTest($selctQuery);
        unset($data['entity_id']);
        $this->assertEquals(count($selectResult), 1);
        $this->assertEquals($selectResult[0]['fileVersion'], 1);
        $this->assertEquals(json_decode($selectResult[0]['data'], true), $data);
    }

    public function testCreateAccess()
    {
        $this->initAuthToken($this->employeeUser);
        $data = ['field1' => '1', 'field2' => '2'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/crud', 'POST', null);
        $this->assertResponseStatusCode(401);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class); // as specified in router's controller name alias
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('appfile');
        $this->assertResponseHeaderContains('content-type', 'application/json');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You have no Access to this API');
    }
    public function testUpdate()
    {
        $data = ['field1' => '2', 'field2' => '3','version' => 1,'policyStatus' => 'On Hold','expiry_date' => '2019-01-01 00:00:00'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $selectResult = $this->getFieldUuid();
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/crud/d13d0c68-98c9-11e9-adc5-308d99c9145b', 'PUT', null);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['field1'], $data['field1']);
        // Performed ox_file_attribute table data verification
        $selctQuery = "SELECT * from ox_file oxf where oxf.uuid='d13d0c68-98c9-11e9-adc5-308d99c9145b'";
        $selectResult = $this->executeQueryTest($selctQuery);
        $this->assertEquals($content['data']['version'], $selectResult[0]['version']);
    }
    
    public function testUpdateRestricted()
    {
        $data = ['name' => 'Test File 1', 'app_id' => 1, 'version' => 1];
        $this->initAuthToken($this->employeeUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/crud', 'PUT', null);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(401);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class); // as specified in router's controller name alias
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('appfile');
        $this->assertResponseHeaderContains('content-type', 'application/json');
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You have no Access to this API');
    }

    public function testDelete()
    {
        $this->initAuthToken($this->adminUser);
        $selctQuery = "SELECT * from ox_file where uuid='d13d0c68-98c9-11e9-adc5-308d99c9145c'";
        $selectResult = $this->executeQueryTest($selctQuery);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/crud/d13d0c68-98c9-11e9-adc5-308d99c9145c?version=1', 'DELETE');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $selct = "SELECT oxf.is_active,oxf.version as fileVersion from ox_file oxf where oxf.uuid='d13d0c68-98c9-11e9-adc5-308d99c9145c'";
        $result = $this->executeQueryTest($selct);
        $this->assertEquals($result[0]['is_active'], 0);
        $this->assertEquals($result[0]['fileVersion'], $selectResult[0]['version']+1);
    }

    public function testDeleteWithWrongVersion()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/crud/d13d0c68-98c9-11e9-adc5-308d99c9145c?version=3', 'DELETE');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Version changed');
    }

    public function testDeleteNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/crud/465c8ff8-df82-11e9-8a34-2a2ae2dbbba3', 'DELETE');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testGetPdfFile()
    {
        $fileId = "d13d0c68-98c9-11e9-adc5-308d99c9145b";
        $this->initAuthToken($this->adminUser);
        $orgUuid = $this->testAccountUuid;

        $path1 = $this->config['TEMPLATE_FOLDER']. $orgUuid . "/";
        if (!is_dir($path1)) {
            mkdir($path1, 0777, true);
        }
        $path = $path1 . $fileId;
        if (!is_link($path)) {
            symlink(__DIR__.'/../../Files', $path);
        }
        $crypto = new Crypto();
        $documentName = $crypto->encryption($path . "/dummy.pdf");
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/' . $fileId . '/document/' . $documentName, 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('getdocument');
        $this->assertNotEquals(strlen($this->getResponse()), 0);
        if (is_link($path)) {
            unlink($path);
        }
        FileUtils::rmDir($path1);
    }
    public function testGetListOfFilesWithUserId()
    {
        $this->initAuthToken($this->adminUser);
        $date = date('Y-m-d');
        $currentDate = date('Y-m-d', strtotime($date . ' + 1 days'));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/workflow/1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4/4fd99e8e-758f-11e9-b2d5-68ecc57cde45/file?filter=[{"filter":{"filters":[{"field":"expiry_date","operator":"lt","value":"' . $currentDate . '"}]},"sort":[{"field":"expiry_date","dir":"asc"}],"skip":0,"take":1}]', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelisting');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['workflowStatus'], 'In Progress');
        $this->assertEquals($content['total'], 2);
    }
    public function testGetListOfFilesWithInvalidUserId()
    {
        $this->initAuthToken($this->adminUser);
        $date = date('Y-m-d');
        $currentDate = date('Y-m-d', strtotime($date . ' + 1 days'));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/workflow/1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4/3fd99e8e-758f-11e9-b2d5-68ecc57cde45/file?filter=[{"filter":{"filters":[{"field":"expiry_date","operator":"lt","value":"' . $currentDate . '"}]},"sort":[{"field":"expiry_date","dir":"asc"}],"skip":0,"take":1}]', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelisting');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }
    public function testGetListOfFilesWithInvalidWorkflow()
    {
        $this->initAuthToken($this->adminUser);
        $date = date('Y-m-d');
        $currentDate = date('Y-m-d', strtotime($date . ' + 1 days'));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/workflow/9941cd2e-cb14-11e9-a32f-2a2ae2dbcce4/4fd99e8e-758f-11e9-b2d5-68ecc57cde45/file?filter=[{"filter":{"filters":[{"field":"expiry_date","operator":"lt","value":"' . $currentDate . '"}]},"sort":[{"field":"expiry_date","dir":"asc"}],"skip":0,"take":1}]', 'GET');
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelisting');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testGetListOfFilesWithUserIdNoData()
    {
        $this->initAuthToken($this->adminUser);
        $date = date('Y-m-d');
        $currentDate = date('Y-m-d', strtotime($date . ' + 1 days'));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/workflow/1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4/4fd9ce37-758f-11e9-b2d5-68ecc57cde45/file?filter=[{"filter":{"filters":[{"field":"expiry_date","operator":"lt","value":"' . $currentDate . '"}]},"sort":[{"field":"expiry_date","dir":"asc"}],"skip":0,"take":1}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelisting');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 0);
        $this->assertEquals($content['total'], 0);
    }

    public function testGetListOfFilesWithQueryParameter()
    {
        $this->initAuthToken($this->adminUser);
        $date = date('Y-m-d');
        $currentDate = date('Y-m-d', strtotime($date . ' + 1 days'));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file?filter=[{"filter":{"filters":[{"field":"expiry_date","operator":"lt","value":"' . $currentDate . '"}]},"sort":[{"field":"expiry_date","dir":"asc"}],"skip":0,"take":1}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelisting');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['total'], 2);
    }

    public function testGetListOfFilesWithAppParameter()
    {
        $this->initAuthToken($this->adminUser);
        $date = date('Y-m-d');
        $currentDate = date('Y-m-d', strtotime($date . ' + 1 days'));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/workflow/1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4/file?filter=[{"filter":{"filters":[{"field":"expiry_date","operator":"lt","value":"' . $currentDate . '"}]},"sort":[{"field":"expiry_date","dir":"asc"}],"skip":0,"take":1}]', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelisting');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['workflowStatus'], 'In Progress');
        $this->assertEquals($content['total'], 2);
    }

    public function testGetListOfFilesWithInvalidAppParameter()
    {
        $this->initAuthToken($this->adminUser);
        $date = date('Y-m-d');
        $currentDate = date('Y-m-d', strtotime($date . ' + 1 days'));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/workflow/1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4/file?filter=[{"filter":{"filters":[{"field":"expiry_dates","operator":"lt","value":"' . $currentDate . '"}]},"sort":[{"field":"expiry_dates","dir":"asc"}],"skip":0,"take":1}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelisting');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testGetListOfFilesWithoutFilters()
    {
        $this->initAuthToken($this->adminUser);
        $date = date('Y-m-d');
        $currentDate = date('Y-m-d', strtotime($date . ' + 1 days'));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file?filter=[{"skip":0,"take":1}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelisting');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['total'], 6);
    }

    public function testGetListOfFilesWithUserIdUsingMultipleFilters()
    {
        $this->initAuthToken($this->adminUser);
        $date = date('Y-m-d');
        $currentDate = date('Y-m-d', strtotime($date . ' + 1 days'));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/workflow/1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4/4fd99e8e-758f-11e9-b2d5-68ecc57cde45/file?filter=[{"filter":{"filters":[{"field":"expiry_date","operator":"gt","value":"' . $currentDate . '"}, {"field":"field2","operator":"eq","value":8}]},"sort":[{"field":"expiry_date","dir":"asc"}],"skip":0,"take":1}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelisting');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['workflowStatus'], 'In Progress');
        $this->assertEquals($content['total'], 1);
    }
    public function testGetListOfFilesWithStatus()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/status/Completed', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelistingstatus');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['workflowStatus'], 'Completed');
        $this->assertEquals(3, $content['total']);
    }
    public function testGetListOfFilesWithStatus2()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/status/In Progress', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelistingstatus');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['workflowStatus'], 'In Progress');
        $this->assertEquals(3, $content['total']);
    }
    public function testGetListOfFilesWithUser()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/user/me', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelistinguser');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['workflowStatus'], 'In Progress');
        $this->assertEquals($content['data'][0]['uuid'], 'd13d0c68-98c9-11e9-adc5-308d99c9145b');
        $this->assertEquals($content['total'], 4);
    }
    public function testGetListOfFilesWithUser2()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/user/4fd9ce37-758f-11e9-b2d5-68ecc57cde45', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelistinguser');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['workflowStatus'], 'In Progress');
        $this->assertEquals($content['data'][0]['uuid'], 'f13d0c68-98c9-11e9-adc5-308d99c91478');
        $this->assertEquals($content['data'][1]['uuid'], 'd13d0c68-98c9-11e9-adc5-308d99c91478');
        $this->assertEquals($content['total'], 2);
    }
    public function testGetListOfFilesWithUserAndStatus()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/user/me/status/Completed', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelistinguser');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['workflowStatus'], 'Completed');
        $this->assertEquals($content['data'][0]['uuid'], 'd13d0c68-98c9-11e9-adc5-308d99c9145c');
        $this->assertEquals($content['total'], 1);
    }
    public function testGetListOfFilesWithUserAndStatus2()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/user/4fd9ce37-758f-11e9-b2d5-68ecc57cde45/status/Completed', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelistinguser');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(2, $content['total']);
        $this->assertEquals('In Progress', $content['data'][0]['workflowStatus']);
        $this->assertEquals('f13d0c68-98c9-11e9-adc5-308d99c91478', $content['data'][0]['uuid']);
        $this->assertEquals('Completed', $content['data'][1]['workflowStatus']);
        $this->assertEquals('d13d0c68-98c9-11e9-adc5-308d99c91478', $content['data'][1]['uuid']);
    }

    public function testGetListOfFilesWithStatusUsingMultipleFilters()
    {
        $this->initAuthToken($this->adminUser);
        $date = date('Y-m-d');
        $currentDate = date('Y-m-d', strtotime($date . ' + 1 days'));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/status/Completed?filter=[{"filter":{"filters":[{"field":"expiry_date","operator":"gt","value":"' . $currentDate . '"}, {"field":"field2","operator":"eq","value":8}]},"sort":[{"field":"expiry_date","dir":"asc"}],"skip":0,"take":1}]', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelistingstatus');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['workflowStatus'], 'Completed');
        $this->assertEquals($content['data'][0]['uuid'], 'd13d0c68-98c9-11e9-adc5-308d99c91478');
        $this->assertEquals($content['total'], 1);
    }

    public function testGetListOfFiles()
    {
        $this->initAuthToken($this->adminUser);
        $date = date('Y-m-d');
        $currentDate = date('Y-m-d', strtotime($date . ' + 1 days'));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelisting');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['total'], 6);
    }
    public function testGetListOfFilesWithoutWorkFlow()
    {
        $this->initAuthToken($this->adminUser);
        $date = date('Y-m-d');
        $currentDate = date('Y-m-d', strtotime($date . ' + 1 days'));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/search?filter=[{"filter":{"filters":[{"field":"expiry_date","operator":"lt","value":"' . $currentDate . '"}, {"field":"field2","operator":"eq","value":1}]},"sort":[{"field":"expiry_date","dir":"asc"}],"skip":0,"take":1}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelistfilter');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['total'], 1);
    }

    public function testgetFileDocumentList()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/d13d0c68-98c9-11e9-adc5-308d99c9145b/document', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class); // as specified in router's controller name alias
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filedocumentlisting');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']) > 0, true);
    }

    public function testgetFileDocumentListNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/d13d0c68-98c9-11e9-adc5-308d99c91422/document', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class); // as specified in router's controller name alias
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filedocumentlisting');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
    }
    public function testGetListOfFilesWithInvalid()
    {
        $this->initAuthToken($this->adminUser);
        $date = date('Y-m-d');
        $currentDate = date('Y-m-d', strtotime($date . ' + 1 days'));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce5/file/search?filter=[{"filter":{"filters":[{"field":"expiry_date","operator":"lt","value":"' . $currentDate . '"}, {"field":"field1","operator":"eq","value":1}, {"field":"user_id","operator":"eq","value":1}]},"sort":[{"field":"expiry_date","dir":"asc"}],"skip":0,"take":1}]', 'GET');
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelistfilter');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testGetListOfFilesCreatedByMe()
    {
        $this->initAuthToken($this->adminUser);
        $date = date('Y-m-d');
        $currentDate = date('Y-m-d', strtotime($date . ' + 1 days'));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/search/createdBy/me', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelistfilter');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['total'] > 0, true);
    }

    public function testGetListOfFilesWithNestedAndFilters()
    {
        $this->initAuthToken($this->adminUser);
        $date = date('Y-m-d');
        $currentDate = date('Y-m-d', strtotime($date . ' + 1 days'));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/status/Completed?filter=[{"filter":{"filters":[{"logic":"and","filters":[{"field":"field1","operator":"startswith","value":"32253"}]},{"logic":"and","filters":[{"field":"product","operator":"startswith","value":"Di"}]}],"logic":"and"},"skip":0}]', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelistingstatus');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['workflowStatus'], 'In Progress');
        $this->assertEquals($content['data'][0]['uuid'], 'f13d0c68-98c9-11e9-adc5-308d99c91478');
        $this->assertEquals($content['total'], 1);
    }


    public function testGetListOfFilesWithSameFieldAndFilters()
    {
        $this->initAuthToken($this->adminUser);
        $date = date('Y-m-d');
        $currentDate = date('Y-m-d', strtotime($date . ' + 1 days'));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/status/Completed?filter=[{"filter":{"filters":[{"logic":"and","filters":[{"field":"field1","operator":"startswith","value":"32"},{"field":"field1","operator":"endswith","value":"3"}]}],"logic":"and"},"skip":0}]', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelistingstatus');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['workflowStatus'], 'In Progress');
        $this->assertEquals($content['data'][0]['uuid'], 'f13d0c68-98c9-11e9-adc5-308d99c91478');
        $this->assertEquals($content['data'][1]['workflowStatus'], 'Completed');
        $this->assertEquals($content['data'][1]['uuid'], 'd13d0c68-98c9-11e9-adc5-308d99c91478');
        $this->assertEquals($content['total'], 2);
    }


    public function testGetListOfFilesWithSameFieldOrFilters()
    {
        $this->initAuthToken($this->adminUser);
        $date = date('Y-m-d');
        $currentDate = date('Y-m-d', strtotime($date . ' + 1 days'));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/status/Completed?filter: [{"filter":{"filters":[{"logic":"or","filters":[{"field":"field1","operator":"startswith","value":"1"},{"field":"field","operator":"startswith","value":"3"}]}],"logic":"and"},"skip":0}]', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelistingstatus');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['total'], 3);
    }


    public function testGetListOfFilesWithNestedOrFilters()
    {
        $this->initAuthToken($this->adminUser);
        $date = date('Y-m-d');
        $currentDate = date('Y-m-d', strtotime($date . ' + 1 days'));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/status/Completed?filter: [{"filter":{"filters":[{"logic":"or","filters":[{"field":"product","operator":"startswith","value":"Di"},{"field":"product","operator":"startswith","value":"Di"}]},{"logic":"or","filters":[{"field":"field1","operator":"startswith","value":"3"},{"field":"field1","operator":"endswith","value":"3"}]}],"logic":"and"},"skip":0}]', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelistingstatus');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['total'], 3);
    }
    public function testGetListOfFilesWithEntityFilter()
    {
        $this->initAuthToken($this->adminUser);
        $date = date('Y-m-d');
        $currentDate = date('Y-m-d', strtotime($date . ' + 1 days'));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file/status/Completed?filter:[{"filter":{"filters":[{"logic":"or","filters":[{"field":"entity_name","operator":"startswith","value":"1"}]}],"logic":"and"},"skip":0}]', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelistingstatus');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['total'], 3);
    }

    public function testGetSnoozedFiles()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('http://localhost:8080/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/file?filter=[{"snooze":"1","take":"50","sort":[{"field":"date_created","dir":"desc"}]}]', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(FileController::class);
        $this->assertControllerClass('FileController');
        $this->assertMatchedRouteName('filelisting');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['total'], 2);
    }

    // TODO WITH WORKFLOWINSTANCEID/ NO WORKFLOWINSTANCEID - CREATEFILE/UPDATEFILE
}
