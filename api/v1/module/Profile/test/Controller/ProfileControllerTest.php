<?php
namespace Profile;

use Profile\Controller\ProfileController;
use Mockery;
use Oxzion\Service\ProfileService;
use Oxzion\Test\ControllerTest;
use PHPUnit\DbUnit\DataSet\YamlDataSet;

class ProfileControllerTest extends ControllerTest
{
    public function setUp(): void
    {
        $this->loadConfig();
        parent::setUp();
    }

    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__) . "/../Dataset/Profile.yml");
        return $dataset;
    }

    protected function setDefaultAsserts()
    {
        $this->assertModuleName('Profile');
        $this->assertControllerName(ProfileController::class); // as specified in router's controller name alias
        $this->assertControllerClass('ProfileController');
    //    $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    }

    public function testGetProfiles()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch("/profile", 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('profile');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 3);
        $this->assertEquals($content['data'][0]['name'], 'Default profile');
        $this->assertEquals($content['data'][1]['name'], 'Manager profile');
        $this->assertEquals($content['data'][2]['name'], 'Employee profile');
    }


    public function testGetProfilesNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/profile/10000', 'GET');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('profile');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }


    public function testGetProfilesforNonAccessUser()
    {
        $this->initAuthToken($this->managerUser);
        $this->dispatch('/profile/user', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(401);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('profileuser');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You have no Access to this API');
    }

    public function testGetProfilesForUser()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/profile/user', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('profileuser');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['data']['dashboard_uuid'], 'abcbefce-1111-4266-bbd6-d8da5571b10a');
        $this->assertEquals($content['data']['type'], 'dashboard');
    }


    // Testing to see if the Create Profile function is working as intended if all the value passed are correct.
    public function testCreate()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'New Profile', 'dashboard_uuid' => '53012471-2863-4949-afb1-e69b0891c98a', 'role_id' => 6, 'type' => 'dashboard','precedence'=> '1'];
        $this->assertEquals(3, $this->getConnection()->getRowCount('ox_profile'));
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/profile', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('profile');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals(4, $this->getConnection()->getRowCount('ox_profile'));
    }



    public function testCreateWithoutRequiredField()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['role_id' => 7];
        $this->assertEquals(3, $this->getConnection()->getRowCount('ox_profile'));
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/profile', 'POST', $data);
        $this->assertResponseStatusCode(500);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('profile');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Statement could not be executed (23000 - 1048 - Column \'name\' cannot be null)');
    }


    public function testUpdate()
    {
        $data = ['name' => 'Updated Name','dashboard_uuid'=>'1234623-1312-4c85-8203-e255ac995c4a','role_id' => 3];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch("/profile/1123c623-1123-4c45-8203-e255ac995c4a", 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('profile');
        $this->assertEquals($content['status'], 'success');
    }


    public function testUpdateNotFound()
    {
        $data = ['name' => 'Test'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/profile/10000', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('profile');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testDelete()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/profile/1123c623-1123-4c45-8203-e255ac995c4a', 'DELETE');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('profile');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testDeleteNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/profile/10000', 'DELETE');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('profile');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Profile not found');
    }

}
