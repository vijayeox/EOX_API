<?php
namespace Announcement;

use Announcement\Controller\AnnouncementController;
use Oxzion\Test\ControllerTest;
use Oxzion\Utils\FileUtils;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Zend\Db\Adapter\AdapterInterface;

class AnnouncementControllerTest extends ControllerTest
{
    public function setUp(): void
    {
        $this->loadConfig();
        parent::setUp();
    }
    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__) . "/../../../Team/test/Dataset/Team.yml");
        $dataset->addYamlFile(dirname(__FILE__) . "/../Dataset/Announcement.yml");
        return $dataset;
    }

    protected function createDummyFile()
    {
        $config = $this->getApplicationConfig();
        $tempFolder = $config['UPLOAD_FOLDER'] . "account/" . $this->testAccountId . "/announcements/temp/";
        FileUtils::createDirectory($tempFolder);
        copy(dirname(__FILE__) . "/../files/test-oxzionlogo.png", $tempFolder . "test-oxzionlogo.png");
    }

    protected function setDefaultAsserts()
    {
        $this->assertModuleName('Announcement');
        $this->assertControllerName(AnnouncementController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AnnouncementController');
        $this->assertMatchedRouteName('announcement');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    }

    public function testGetList()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/announcement', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 5);
        $this->assertEquals($content['data'][4]['uuid'], '9068b460-2943-4508-bd4c-2b29238700f3');
        $this->assertEquals($content['data'][4]['name'], 'Announcement 1');
        $this->assertEquals($content['data'][3]['uuid'], 'e66157ee-47de-4ed5-a78e-8a9195033f7a');
        $this->assertEquals($content['data'][3]['name'], 'Announcement 2');
    }

    public function testGetListWithType()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/announcement/a/ANNOUNCEMENT', 'GET');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 3);
        $this->assertEquals($content['data'][2]['uuid'], 'e66157ee-47de-4ed5-a78e-8a9195033abc');
        $this->assertEquals($content['data'][2]['name'], 'Announcement 3');
        $this->assertEquals($content['data'][1]['uuid'], 'e66157ee-47de-4ed5-a78e-8a9195033f7a');
        $this->assertEquals($content['data'][1]['name'], 'Announcement 2');
        $this->assertEquals($content['data'][0]['name'], 'Announcement 1');
    }

    public function testGetListWithIncorrectType()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/announcement/a/something', 'GET');
        $this->assertResponseStatusCode(412);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Announcement Type must be ANNOUNCEMENT or HOMESCREEN');
    }

    public function testGetListWithDifferentType()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/announcement/a/HOMESCREEN', 'GET');
        $this->assertResponseStatusCode(200);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 2);
        $this->assertEquals($content['data'][0]['uuid'], '70d53bec-6e2d-4128-933b-96caa3d88e2a');
        $this->assertEquals($content['data'][0]['name'], 'Announcement 6');
        $this->assertEquals($content['data'][1]['uuid'], '36c8980d-48c2-45fc-b5bc-4407c80f6d71');
        $this->assertEquals($content['data'][1]['name'], 'Announcement 7');
    }

    public function testGetListWithAccountID()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/announcement', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 5);
        $this->assertEquals($content['data'][3]['uuid'], 'e66157ee-47de-4ed5-a78e-8a9195033f7a');
        $this->assertEquals($content['data'][3]['name'], 'Announcement 2');
        $this->assertEquals($content['data'][4]['uuid'], '9068b460-2943-4508-bd4c-2b29238700f3');
        $this->assertEquals($content['data'][4]['name'], 'Announcement 1');
    }

    public function testGetListWithExpirationStartDate()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/announcement/a/HOMESCREEN', 'GET');
        $this->assertResponseStatusCode(200);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertNotContains('Announcement 8', array_column($content['data'], 'name'));
        $this->assertEquals($content['status'], 'success');
    }

    public function testGetListWithExpirationEndDate()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/announcement/a/HOMESCREEN', 'GET');
        $this->assertResponseStatusCode(200);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertNotContains('Announcement 9', array_column($content['data'], 'name'));
        $this->assertEquals($content['status'], 'success');
    }

    public function testGetListofAll()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/announcement/a/ANNOUNCEMENT', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Announcement');
        $this->assertControllerName(AnnouncementController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AnnouncementController');
        $this->assertMatchedRouteName('announcementList');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['uuid'], '9068b460-2943-4508-bd4c-2b29238700f3');
        $this->assertEquals($content['data'][0]['name'], 'Announcement 1');
        $this->assertEquals($content['data'][1]['uuid'], 'e66157ee-47de-4ed5-a78e-8a9195033f7a');
        $this->assertEquals($content['data'][1]['name'], 'Announcement 2');
    }

    public function testGetListofAllWithAccountId()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/announcement/a/ANNOUNCEMENT', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Announcement');
        $this->assertControllerName(AnnouncementController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AnnouncementController');
        $this->assertMatchedRouteName('announcementList');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['uuid'], '9068b460-2943-4508-bd4c-2b29238700f3');
        $this->assertEquals($content['data'][0]['name'], 'Announcement 1');
        $this->assertEquals($content['data'][1]['uuid'], 'e66157ee-47de-4ed5-a78e-8a9195033f7a');
        $this->assertEquals($content['data'][1]['name'], 'Announcement 2');
    }

    public function testGetListofAllByManagerInvalidAccount()
    {
        $this->initAuthToken($this->managerUser);
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/announcement/a/ANNOUNCEMENT', 'GET');
        $this->assertResponseStatusCode(401);
        $this->assertModuleName('Announcement');
        $this->assertControllerName(AnnouncementController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AnnouncementController');
        $this->assertMatchedRouteName('announcementList');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You do not have permissions to get the announcement list');
    }

    public function testGetListofAllByManagerValidAccount()
    {
        $this->initAuthToken($this->managerUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/announcement/a/ANNOUNCEMENT', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Announcement');
        $this->assertControllerName(AnnouncementController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AnnouncementController');
        $this->assertMatchedRouteName('announcementList');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['uuid'], '9068b460-2943-4508-bd4c-2b29238700f3');
        $this->assertEquals($content['data'][0]['name'], 'Announcement 1');
        $this->assertEquals($content['data'][1]['uuid'], 'e66157ee-47de-4ed5-a78e-8a9195033f7a');
        $this->assertEquals($content['data'][1]['name'], 'Announcement 2');
    }

    public function testGet()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/announcement/9068b460-2943-4508-bd4c-2b29238700f3', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['uuid'], '9068b460-2943-4508-bd4c-2b29238700f3');
        $this->assertEquals($content['data']['name'], 'Announcement 1');
    }

    public function testGetListAccountID()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/announcement/9068b460-2943-4508-bd4c-2b29238700f3', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['uuid'], '9068b460-2943-4508-bd4c-2b29238700f3');
        $this->assertEquals($content['data']['name'], 'Announcement 1');
    }

    public function testGetListWithInvalidAccountID()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/announcement/9068b460-2943-4508-bd4c-2b29238700f3', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'], array());
    }

    public function testGetNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/announcement/9068b460-2943-4508-bd4c', 'GET');
        $this->assertResponseStatusCode(200);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['data'], array());
    }

    public function testCreate()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Test Announcement', 'status' => 1, 'start_date' => date('Y-m-d H:i:s'), 'end_date' => date('Y-m-d H:i:s', strtotime("+7 day")), 'media' => 'test-oxzionlogo.png', 'accountId' => '53012471-2863-4949-afb1-e69b0891c98a', 'type' => 'ANNOUNCEMENT'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/announcement', 'POST', null);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals($content['data']['status'], $data['status']);
        $this->assertEquals($content['data']['start_date'], $data['start_date']);
        $this->assertEquals($content['data']['end_date'], $data['end_date']);
    }

    public function testCreateWithoutAccountAsEmployeeUser()
    {
        $this->initAuthToken($this->employeeUser);
        $data = ['name' => 'Test Announcement', 'status' => 1, 'start_date' => date('Y-m-d H:i:s'), 'end_date' => date('Y-m-d H:i:s', strtotime("+7 day")), 'media' => 'test-oxzionlogo.png', 'type' => 'ANNOUNCEMENT'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/announcement', 'POST', null);
        $this->assertResponseStatusCode(401);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You have no Access to this API');
    }

    public function testCreateWithoutAccountAsSuperUser()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Test Announcement', 'status' => 1, 'start_date' => date('Y-m-d H:i:s'), 'end_date' => date('Y-m-d H:i:s', strtotime("+7 day")), 'media' => 'test-oxzionlogo.png', 'type' => 'ANNOUNCEMENT'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/announcement', 'POST', null);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals($content['data']['status'], $data['status']);
        $this->assertEquals($content['data']['start_date'], $data['start_date']);
        $this->assertEquals($content['data']['end_date'], $data['end_date']);
    }

    public function testCreateWithAccount()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Test Announcement', 'status' => 1, 'start_date' => date('Y-m-d H:i:s'), 'end_date' => date('Y-m-d H:i:s', strtotime("+7 day")), 'media' => 'test-oxzionlogo.png', 'type' => 'ANNOUNCEMENT'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/announcement', 'POST', null);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);

        $select = "SELECT * from ox_announcement where name = 'Test Announcement'";
        $result = $this->executeQueryTest($select);

        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals($content['data']['status'], $data['status']);
        $this->assertEquals($content['data']['start_date'], $data['start_date']);
        $this->assertEquals($content['data']['end_date'], $data['end_date']);
        $this->assertEquals($result[0]['name'], 'Test Announcement');
        $this->assertEquals($result[0]['account_id'], 1);
    }

    public function testCreateOtherAccount()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Test Announcement', 'status' => 1, 'start_date' => date('Y-m-d H:i:s'), 'end_date' => date('Y-m-d H:i:s', strtotime("+7 day")), 'media' => 'test-oxzionlogo.png', 'type' => 'ANNOUNCEMENT'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/announcement', 'POST', null);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $select = "SELECT * from ox_announcement where name = 'Test Announcement'";
        $result = $this->executeQueryTest($select);

        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals($content['data']['status'], $data['status']);
        $this->assertEquals($content['data']['start_date'], $data['start_date']);
        $this->assertEquals($content['data']['end_date'], $data['end_date']);
        $this->assertEquals($result[0]['name'], 'Test Announcement');
        $this->assertEquals($result[0]['account_id'], 2);
    }

    public function testCreateWithOutNameFailure()
    {
        $this->initAuthToken($this->adminUser);
        $this->createDummyFile();
        $data = ['teams' => '[{"id":1},{"id":2}]', 'status' => 1, 'start_date' => date('Y-m-d H:i:s'), 'end_date' => date('Y-m-d H:i:s', strtotime("+7 day"))];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/announcement', 'POST', null);
        $this->assertResponseStatusCode(406);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Validation error(s).');
        $this->assertEquals($content['data']['errors']['name'], 'required');
    }

    public function testCreateExistingAnnouncement()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Announcement 1', 'status' => 1, 'start_date' => date('Y-m-d H:i:s'), 'end_date' => date('Y-m-d H:i:s', strtotime("+7 day")), 'media' => 'test-oxzionlogo.png'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/announcement', 'POST', null);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(412);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Announcement already exists');
    }

    public function testCreateExistingAnnouncementWithoutAccount()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Announcement 1', 'status' => 1, 'start_date' => date('Y-m-d H:i:s'), 'end_date' => date('Y-m-d H:i:s', strtotime("+7 day")), 'media' => 'test-oxzionlogo.png'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/announcement', 'POST', null);
        $this->assertResponseStatusCode(412);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Announcement already exists');
    }

    public function testCreateExpiredAnnouncement()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Announcement 5', 'status' => 1, 'start_date' => date('Y-m-d H:i:s'), 'end_date' => date('Y-m-d H:i:s', strtotime("+7 day")), 'media' => 'test-oxzionlogo.png', 'type' => 'ANNOUNCEMENT'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/announcement', 'POST', null);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $select = "SELECT * from ox_announcement where name = 'Announcement 5'";
        $result = $this->executeQueryTest($select);

        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals($content['data']['status'], $data['status']);
        $this->assertEquals($content['data']['start_date'], $data['start_date']);
        $this->assertEquals($content['data']['end_date'], $data['end_date']);
        $this->assertEquals($result[0]['name'], 'Announcement 5');
        $this->assertEquals($result[0]['account_id'], 1);
    }

    public function testCreateWithOtherAccount()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Announcement 5', 'status' => 1, 'start_date' => date('Y-m-d H:i:s'), 'end_date' => date('Y-m-d H:i:s', strtotime("+7 day")), 'media' => 'test-oxzionlogo.png', 'type' => 'ANNOUNCEMENT'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/announcement', 'POST', null);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);

        $select = "SELECT * from ox_announcement where name = 'Announcement 5'";
        $result = $this->executeQueryTest($select);

        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals($content['data']['status'], $data['status']);
        $this->assertEquals($content['data']['start_date'], $data['start_date']);
        $this->assertEquals($content['data']['end_date'], $data['end_date']);
        $this->assertEquals($result[0]['name'], 'Announcement 5');
        $this->assertEquals($result[0]['account_id'], 1);
    }

    public function testCreateAccess()
    {
        $this->initAuthToken($this->employeeUser);
        $data = ['name' => 'Test Announcement', 'teams' => '[{"id":1},{"id":2}]', 'status' => 1, 'start_date' => date('Y-m-d H:i:s'), 'end_date' => date('Y-m-d H:i:s', strtotime("+7 day"))];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/announcement', 'POST', null);
        $this->assertResponseStatusCode(401);
        $this->assertModuleName('Announcement');
        $this->assertControllerName(AnnouncementController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AnnouncementController');
        $this->assertMatchedRouteName('announcement');
        $this->assertResponseHeaderContains('content-type', 'application/json');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You have no Access to this API');
    }

    public function testUpdate()
    {
        $data = ['name' => 'Test Announcement', 'status' => 1, 'start_date' => date('Y-m-d H:i:s'), 'end_date' => date('Y-m-d H:i:s', strtotime("+7 day")), 'media' => 'test-oxzionlogo.png'];
        // $this->createDummyFile();
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/announcement/9068b460-2943-4508-bd4c-2b29238700f3', 'PUT', null);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
    }

    public function testUpdateWithAccountId()
    {
        $data = ['name' => 'Test Announcement', 'status' => 1, 'start_date' => date('Y-m-d H:i:s'), 'end_date' => date('Y-m-d H:i:s', strtotime("+7 day")), 'media' => 'test-oxzionlogo.png'];
        // $this->createDummyFile();
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/announcement/9068b460-2943-4508-bd4c-2b29238700f3', 'PUT', null);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
    }

    public function testUpdateWithDifferentAccount()
    {
        $data = ['name' => 'Test Announcement', 'status' => 1, 'start_date' => date('Y-m-d H:i:s'), 'end_date' => date('Y-m-d H:i:s', strtotime("+7 day")), 'media' => 'test-oxzionlogo.png'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/announcement/9068b460-2943-4508-bd4c-2b29238700f3', 'PUT', null);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Announcement does not belong to the account');
    }

    public function testUpdateWithInvalidAnnouncementId()
    {
        $data = ['name' => 'Test Announcement', 'status' => 1, 'start_date' => date('Y-m-d H:i:s'), 'end_date' => date('Y-m-d H:i:s', strtotime("+7 day")), 'media' => 'test-oxzionlogo.png'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/announcement/9068b460-2943-4508-b', 'PUT', null);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Announcement not found');
    }

    public function testUpdateRestricted()
    {
        $data = ['name' => 'Test Announcement', 'teams' => '[{"id":1}]', 'status' => 1, 'start_date' => date('Y-m-d H:i:s'), 'end_date' => date('Y-m-d H:i:s', strtotime("+7 day")), 'media' => 'test-oxzionlogo.png'];
        // $this->createDummyFile();
        $this->initAuthToken($this->employeeUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/announcement/9068b460-2943-4508-bd4c-2b29238700f3', 'PUT', null);
        $this->assertResponseStatusCode(401);
        $this->assertModuleName('Announcement');
        $this->assertControllerName(AnnouncementController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AnnouncementController');
        $this->assertMatchedRouteName('announcement');
        $this->assertResponseHeaderContains('content-type', 'application/json');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You have no Access to this API');
    }

    public function testUpdateNotFound()
    {
        $data = ['name' => 'Test Announcement', 'teams' => '[{"id":1},{"id":2}]', 'status' => 1, 'start_date' => date('Y-m-d H:i:s'), 'end_date' => date('Y-m-d H:i:s', strtotime("+7 day"))];
        // $this->createDummyFile();
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/announcement/9068b460-2943-4508-b', 'PUT', null);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testDelete()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/announcement/e66157ee-47de-4ed5-a78e-8a9195033f7a', 'DELETE');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testDeleteWithAccount()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/announcement/e66157ee-47de-4ed5-a78e-8a9195033f7a', 'DELETE');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();

        $select = "SELECT * from ox_attachment where uuid = 'test'";
        $result = $this->executeQueryTest($select);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($result, array());
    }

    public function testDeleteWithDifferentAccount()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/announcement/e66157ee-47de-4ed5-a78e-8a9195033f7a', 'DELETE');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Announcement not found');
    }

    public function testDeleteWithInvalidAnnouncement()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/announcement/e66157ee-47de-4ed5-a78e7a', 'DELETE');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Announcement not found');
    }

    public function testDeleteNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/announcement/e66157ee-47de-4ed5-a', 'DELETE');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Announcement not found');
    }

    public function testGetListOfAnnouncementTeams()
    {
        $this->initAuthToken($this->adminUser);
        $dbAdapter = $this->getApplicationServiceLocator()->get(AdapterInterface::class);
        $query = "UPDATE `ox_announcement` SET `start_date` = now() ,`end_date` = '" . date('Y-m-d', strtotime("+0 day")) . "' where uuid ='9068b460-2943-4508-bd4c-2b29238700f3'";
        $statement = $dbAdapter->query($query);
        $result = $statement->execute();

        $this->dispatch('/announcement/9068b460-2943-4508-bd4c-2b29238700f3/teams', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Announcement');
        $this->assertControllerName(AnnouncementController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AnnouncementController');
        $this->assertMatchedRouteName('announcementteams');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 1);
        $this->assertEquals($content['data'][0]['uuid'], '2db1c5a3-8a82-4d5b-b60a-c648cf1e27de');
        $this->assertEquals($content['data'][0]['name'], 'Test Team');
        $this->assertEquals($content['total'], 1);
    }

    public function testGetListOfAnnouncementTeamsWithAccountId()
    {
        $this->initAuthToken($this->adminUser);
        $dbAdapter = $this->getApplicationServiceLocator()->get(AdapterInterface::class);
        $query = "UPDATE `ox_announcement` SET `start_date` = now() ,`end_date` = '" . date('Y-m-d', strtotime("+0 day")) . "' where uuid ='9068b460-2943-4508-bd4c-2b29238700f3'";
        $statement = $dbAdapter->query($query);
        $result = $statement->execute();

        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/announcement/9068b460-2943-4508-bd4c-2b29238700f3/teams', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Announcement');
        $this->assertControllerName(AnnouncementController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AnnouncementController');
        $this->assertMatchedRouteName('announcementteams');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 1);
        $this->assertEquals($content['data'][0]['uuid'], '2db1c5a3-8a82-4d5b-b60a-c648cf1e27de');
        $this->assertEquals($content['data'][0]['name'], 'Test Team');
        $this->assertEquals($content['total'], 1);
    }

    public function testGetListOfAnnouncementTeamsWithInvalidAnnouncementID()
    {
        $this->initAuthToken($this->adminUser);
        $dbAdapter = $this->getApplicationServiceLocator()->get(AdapterInterface::class);
        $query = "UPDATE `ox_announcement` SET `start_date` = now() ,`end_date` = '" . date('Y-m-d', strtotime("+0 day")) . "' where uuid ='9068b460-2943-4508-bd4c-2b29238700f3'";
        $statement = $dbAdapter->query($query);
        $result = $statement->execute();

        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/announcement/9068b460-29d4c-2b29238700f3/teams', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Announcement');
        $this->assertControllerName(AnnouncementController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AnnouncementController');
        $this->assertMatchedRouteName('announcementteams');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'], array());
    }

    public function testGetListOfAnnouncementTeamsWithInvalidAccountId()
    {
        $this->initAuthToken($this->adminUser);
        $dbAdapter = $this->getApplicationServiceLocator()->get(AdapterInterface::class);
        $query = "UPDATE `ox_announcement` SET `start_date` = now() ,`end_date` = '" . date('Y-m-d', strtotime("+0 day")) . "' where uuid ='9068b460-2943-4508-bd4c-2b29238700f3'";
        $statement = $dbAdapter->query($query);
        $result = $statement->execute();

        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/announcement/9068b460-2943-4508-bd4c-2b29238700f3/teams', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Announcement');
        $this->assertControllerName(AnnouncementController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AnnouncementController');
        $this->assertMatchedRouteName('announcementteams');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'], array());
    }

    public function testsaveTeam()
    {
        $data = ['teams' => array(['uuid' => '2db1c5a3-8a82-4d5b-b60a-c648cf1e27de'], ['uuid' => '153f3e9e-eb07-4ca4-be78-34f715bd50db'])];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/announcement/9068b460-2943-4508-bd4c-2b29238700f3/save', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Announcement');
        $this->assertControllerName(AnnouncementController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AnnouncementController');
        $this->assertMatchedRouteName('announcementToTeam');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');

        $content = (array) json_decode($this->getResponse()->getContent(), true);

        $select = "SELECT id FROM ox_announcement where uuid = '9068b460-2943-4508-bd4c-2b29238700f3'";
        $result = $this->executeQueryTest($select);

        $select = "SELECT * from ox_announcement_team_mapper where announcement_id = " . $result[0]['id'];
        $announcementTeam = $this->executeQueryTest($select);

        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']['teams']), 2);
        $this->assertEquals($announcementTeam[0]['announcement_id'], 1);
        $this->assertEquals($announcementTeam[0]['team_id'], 1);
        $this->assertEquals($announcementTeam[1]['announcement_id'], 1);
        $this->assertEquals($announcementTeam[1]['team_id'], 2);
    }

    public function testsaveTeamWithAccount()
    {
        $data = ['teams' => array(['uuid' => '2db1c5a3-8a82-4d5b-b60a-c648cf1e27de'], ['uuid' => '153f3e9e-eb07-4ca4-be78-34f715bd50db'])];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/announcement/9068b460-2943-4508-bd4c-2b29238700f3/save', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Announcement');
        $this->assertControllerName(AnnouncementController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AnnouncementController');
        $this->assertMatchedRouteName('announcementToTeam');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');

        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $select = "SELECT id FROM ox_announcement where uuid = '9068b460-2943-4508-bd4c-2b29238700f3'";
        $result = $this->executeQueryTest($select);

        $select = "SELECT * from ox_announcement_team_mapper where announcement_id = " . $result[0]['id'];
        $announcementTeam = $this->executeQueryTest($select);

        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']['teams']), 2);
        $this->assertEquals($announcementTeam[0]['announcement_id'], 1);
        $this->assertEquals($announcementTeam[0]['team_id'], 1);
        $this->assertEquals($announcementTeam[1]['announcement_id'], 1);
        $this->assertEquals($announcementTeam[1]['team_id'], 2);
    }

    public function testsaveTeamWithInvalidAccount()
    {
        $data = ['teams' => array(['uuid' => '2db1c5a3-8a82-4d5b-b60a-c648cf1e27de'], ['uuid' => '153f3e9e-eb07-4ca4-be78-34f715bd50db'])];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/5301263-4949-afb1-e69b0891c98a/announcement/9068b460-2943-4508-bd4c-2b29238700f3/save', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('Announcement');
        $this->assertControllerName(AnnouncementController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AnnouncementController');
        $this->assertMatchedRouteName('announcementToTeam');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');

        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Announcement does not belong to the account');
    }

    public function testsaveTeamWithInvalidAnnouncementId()
    {
        $data = ['teams' => array(['uuid' => '2db1c5a3-8a82-4d5b-b60a-c648cf1e27de'], ['uuid' => '153f3e9e-eb07-4ca4-be78-34f715bd50db'])];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/5301263-4949-afb1-e69b0891c98a/announcement/90943-4508-bd4c-2b29238700f3/save', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('Announcement');
        $this->assertControllerName(AnnouncementController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AnnouncementController');
        $this->assertMatchedRouteName('announcementToTeam');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');

        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Announcement not found');
    }

    public function testsaveTeamOfDifferentAccount()
    {
        $data = ['teams' => array(['uuid' => '153f3e9e-eb07-4ca4-be78-34f715bd50sd'])];
        // $this->createDummyFile();
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/announcement/9068b460-2943-4508-bd4c-2b29238700f3/save', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Announcement');
        $this->assertControllerName(AnnouncementController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AnnouncementController');
        $this->assertMatchedRouteName('announcementToTeam');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');

        $content = (array) json_decode($this->getResponse()->getContent(), true);

        $select = "SELECT id FROM ox_announcement where uuid = '9068b460-2943-4508-bd4c-2b29238700f3'";
        $result = $this->executeQueryTest($select);

        $select = "SELECT * from ox_announcement_team_mapper where announcement_id = " . $result[0]['id'];
        $announcementTeam = $this->executeQueryTest($select);

        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']['teams']), 1);
        $this->assertEquals(count($announcementTeam), 0);
    }
}
