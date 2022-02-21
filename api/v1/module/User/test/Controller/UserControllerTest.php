<?php
namespace User;

use Mockery;
use Oxzion\Service\UserService;
use Oxzion\Test\ControllerTest;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use User\Controller\UserController;

class UserControllerTest extends ControllerTest
{
    public function setUp(): void
    {
        $this->loadConfig();
        parent::setUp();
    }

    public function getMockMessageProducer()
    {
        $userService = $this->getApplicationServiceLocator()->get(UserService::class);
        $mockMessageProducer = Mockery::mock('Oxzion\Messaging\MessageProducer');
        $userService->setMessageProducer($mockMessageProducer);
        return $mockMessageProducer;
    }

    public function getDataSet()
    {
        /*switch ($this->getName()) {
            case 'testGetListWithProjectAndRoles':
                return new YamlDataSet(dirname(__FILE__) . "/../../Dataset/UserProjectsRoles.yml");;
            break;
        }*/
        $dataset = new YamlDataSet(dirname(__FILE__) . "/../Dataset/User.yml");
        $dataset->addYamlFile(dirname(__FILE__) . "/../../../Project/test/Dataset/Project.yml");
        $dataset->addYamlFile(dirname(__FILE__) . "/../../../Team/test/Dataset/Team.yml");
        $dataset->addYamlFile(dirname(__FILE__) . "/../../../Role/test/Dataset/Role.yml");
        return $dataset;
    }

    protected function setDefaultAsserts($router = "user")
    {
        $this->assertModuleName('User');
        $this->assertControllerName(UserController::class); // as specified in router's controller name alias
        $this->assertControllerClass('UserController');
        $this->assertMatchedRouteName($router);
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    }

    public function testCreateByAdmin()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => 'John Holt', 'status' => 'Active', 'date_of_birth' => date('Y-m-d', strtotime("-50 year")), 'date_of_join' => date('Y-m-d'), 'icon' => 'test-oxzionlogo.png', 'managerid' => '471', 'firstname' => 'John', 'lastname' => 'Holt', 'designation' => 'CEO', 'location' => 'USA', 'email' => 'harshva.com', 'gender' => 'Male', 'address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456', 'role' => array(['id' => '89a01b30-9cc9-416e-8027-1fd2083786c7'], ['id' => '5ecccd2d-4dc7-4e19-ae5f-adb3c8f48073'])];
        $this->setJsonContent(json_encode($data));
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(Mockery::any(), 'USER_ADDED')->once()->andReturn();
            $mockMessageProducer->expects('sendTopic')->with(Mockery::any(), 'USERTOACCOUNT_ADDED')->once()->andReturn();
        }
        $this->dispatch('/user', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);

        $query = "SELECT ox_user.id,ox_user.uuid,usrp.address_id from ox_user join ox_person usrp on usrp.id=ox_user.person_id where username = '" . $data['username'] . "'";
        $userId = $this->executeQueryTest($query);

        $query = "SELECT * from ox_address where id = " . $userId[0]['address_id'];
        $address = $this->executeQueryTest($query);

        $query = "SELECT * from ox_account_user where user_id = " . $userId[0]['id'];
        $userOrg = $this->executeQueryTest($query);

        $query = "SELECT * from ox_user_role where account_user_id = " . $userOrg[0]['id'] . " order by role_id";
        $userRole = $this->executeQueryTest($query);

        $query = "SELECT designation,date_of_join from ox_employee inner join ox_user on ox_user.person_id = ox_employee.person_id  where username = '" . $data['username'] . "'";
        $empDetails = $this->executeQueryTest($query);

        $this->assertEquals($content['status'], 'success');
        $this->assertNotEmpty($content['data']['uuid']);
        $this->assertEquals($content['data']['username'], $data['username']);
        $this->assertEquals($content['data']['status'], $data['status']);
        $this->assertEquals($content['data']['uuid'], $userId[0]['uuid']);
        $this->assertEquals($userOrg[0]['user_id'], $userId[0]['id']);
        $this->assertEquals($userOrg[0]['account_id'], 1);
        $this->assertEquals($userRole[0]['role_id'], 16);
        $this->assertEquals($userRole[1]['role_id'], 17);
        $this->assertEquals($address[0]['address1'], $data['address1']);
        $this->assertEquals($address[0]['city'], $data['city']);
        $this->assertEquals($address[0]['state'], $data['state']);
        $this->assertEquals($address[0]['country'], $data['country']);
        $this->assertEquals($empDetails[0]['designation'], $data['designation']);
        $this->assertEquals($empDetails[0]['date_of_join'], $data['date_of_join']);
    }

    public function testCreateWithRoleOfOtherAccount()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => 'John Holt', 'status' => 'Active', 'date_of_birth' => date('Y-m-d', strtotime("-50 year")), 'date_of_join' => date('Y-m-d'), 'icon' => 'test-oxzionlogo.png', 'address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456', 'managerid' => '471', 'firstname' => 'John', 'lastname' => 'Holt', 'designation' => 'CEO', 'location' => 'USA', 'email' => 'harshva.com', 'gender' => 'Male', 'role' => array(['id' => '508572ae-a6c2-11e9-b648-68ecc57cde45'], ['id' => '50873a47-a6c2-11e9-b648-68ecc57cde45'])];
        $this->setJsonContent(json_encode($data));
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(Mockery::any(), 'USER_ADDED')->once()->andReturn();
            $mockMessageProducer->expects('sendTopic')->with(Mockery::any(), 'USERTOACCOUNT_ADDED')->once()->andReturn();
        }
        $this->dispatch('/user', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);

        $query = "SELECT id, uuid from ox_user where username = '" . $data['username'] . "'";
        $userId = $this->executeQueryTest($query);

        $query = "SELECT * from ox_account_user where user_id = " . $userId[0]['id'];
        $userOrg = $this->executeQueryTest($query);

        $query = "SELECT * from ox_user_role where account_user_id = " . $userOrg[0]['id'];
        $userRole = $this->executeQueryTest($query);

        $query = "SELECT designation,date_of_join from ox_employee
                    inner join ox_user on ox_user.person_id = ox_employee.person_id
                    where username = '" . $data['username'] . "'";
        $empDetails = $this->executeQueryTest($query);

        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertNotEmpty($content['data']['uuid']);
        $this->assertEquals($content['data']['username'], $data['username']);
        $this->assertEquals($content['data']['status'], $data['status']);
        $this->assertEquals($userId[0]['uuid'], $content['data']['uuid']);
        $this->assertEquals($userOrg[0]['account_id'], 1);
        $this->assertEquals($empDetails[0]['designation'], $data['designation']);
        $this->assertEquals($empDetails[0]['date_of_join'], $data['date_of_join']);
        $this->assertEquals(count($userRole), 0);
    }

    public function testCreateByEmployee()
    {
        $this->initAuthToken($this->employeeUser);
        $data = ['username' => 'John Holt', 'status' => 'Active', 'date_of_birth' => date('Y-m-d', strtotime("-50 year")), 'date_of_join' => date('Y-m-d'), 'icon' => 'test-oxzionlogo.png', 'managerid' => '471', 'firstname' => 'John', 'lastname' => 'Holt', 'designation' => 'CEO', 'location' => 'USA', 'email' => 'harshva.com', 'gender' => 'Male', 'address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456', 'role' => array(['id' => '50873baa-a6c2-11e9-b648-68ecc57cde45'], ['id' => '50873bf0-a6c2-11e9-b648-68ecc57cde45'])];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/user', 'POST', $data);
        $this->assertResponseStatusCode(401);
        $this->assertModuleName('User');
        $this->assertControllerName(UserController::class); // as specified in router's controller name alias
        $this->assertControllerClass('UserController');
        $this->assertMatchedRouteName('user');
        $this->assertResponseHeaderContains('content-type', 'application/json');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You have no Access to this API');
    }

    public function testCreateforExistingInactiveUserWithoutReactivateFlag()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => 'rajesh', 'date_of_birth' => date('Y-m-d', strtotime("-50 year")), 'date_of_join' => date('Y-m-d'), 'icon' => 'test-oxzionlogo.png', 'managerid' => '471', 'firstname' => 'John', 'lastname' => 'Holt', 'designation' => 'CEO', 'location' => 'USA', 'email' => 'john@gmail.com', 'gender' => 'Male', 'address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456', 'role' => array(['id' => '89a01b30-9cc9-416e-8027-1fd2083786c7'], ['id' => '5ecccd2d-4dc7-4e19-ae5f-adb3c8f48073'])];
        $this->setJsonContent(json_encode($data));

        $this->dispatch('/user', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(412);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);

        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'user already exists and is inactive. Please contact the admin to activate');
    }

    public function testCreateforExistingInactiveUserWithReactivateFlag()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => 'rajesh', 'date_of_birth' => date('Y-m-d', strtotime("-50 year")), 'date_of_join' => date('Y-m-d'), 'icon' => 'test-oxzionlogo.png', 'managerId' => '53a68c7b-f991-11ea-b145-5435302dc17d', 'firstname' => 'John', 'lastname' => 'Holt', 'designation' => 'CEO', 'location' => 'USA', 'email' => 'john@gmail.com', 'gender' => 'Male', 'address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456', 'role' => array(['id' => '89a01b30-9cc9-416e-8027-1fd2083786c7'], ['id' => '5ecccd2d-4dc7-4e19-ae5f-adb3c8f48073']), 'reactivate' => 1];
        $this->setJsonContent(json_encode($data));

        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(Mockery::any(), 'USER_ADDED')->once()->andReturn();
        }

        $this->dispatch('/user', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);

        $query = "SELECT status from ox_user where username = '" . $data['username'] . "'";
        $userStatus = $this->executeQueryTest($query);

        $query = "SELECT id from ox_user where username = '" . $data['username'] . "'";
        $userId = $this->executeQueryTest($query);

        $query = "SELECT * from ox_account_user where user_id = " . $userId[0]['id'];
        $userOrg = $this->executeQueryTest($query);

        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['username'], $data['username']);
        $this->assertEquals($userStatus[0]['status'], 'Active');
        $this->assertEquals($userOrg[0]['account_id'], 2);
        $this->assertEquals($userOrg[1]['account_id'], 1);
    }

    public function testCreateforExistingUser()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => 'rajesh', 'date_of_birth' => date('Y-m-d', strtotime("-50 year")), 'date_of_join' => date('Y-m-d'), 'icon' => 'test-oxzionlogo.png', 'managerid' => '471', 'firstname' => 'John', 'lastname' => 'Holt', 'designation' => 'CEO', 'location' => 'USA', 'email' => 'john@gmail.com', 'gender' => 'Male', 'address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456', 'role' => array(['id' => '89a01b30-9cc9-416e-8027-1fd2083786c7'], ['id' => '5ecccd2d-4dc7-4e19-ae5f-adb3c8f48073'])];
        $this->setJsonContent(json_encode($data));

        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(Mockery::any(), 'USER_ADDED')->once()->andReturn();
        }

        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/user', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(412);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'user already exists and is inactive. Please contact the admin to activate');
    }

    public function testCreateforOtherAccount()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => 'rajesha', 'date_of_birth' => date('Y-m-d', strtotime("-50 year")), 'date_of_join' => date('Y-m-d'), 'icon' => 'test-oxzionlogo.png', 'managerid' => '471', 'firstname' => 'John', 'lastname' => 'Holt', 'designation' => 'CEO', 'location' => 'USA', 'email' => 'john@gmail.com', 'gender' => 'Male', 'address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456', 'role' => array(['id' => '53012471-2863'])];
        $this->setJsonContent(json_encode($data));

        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(Mockery::any(), 'USER_ADDED')->once()->andReturn();
            $mockMessageProducer->expects('sendTopic')->with(Mockery::any(), 'USERTOACCOUNT_ADDED')->once()->andReturn();
        }

        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/user', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);

        $query = "SELECT id from ox_user where username = '" . $data['username'] . "'";
        $userId = $this->executeQueryTest($query);

        $query = "SELECT * from ox_account_user where user_id = " . $userId[0]['id'];
        $userOrg = $this->executeQueryTest($query);

        $query = "SELECT * from ox_user_role where account_user_id = " . $userOrg[0]['id'];
        $userRole = $this->executeQueryTest($query);

        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['username'], $data['username']);
        $this->assertEquals($userOrg[0]['account_id'], 2);
        $this->assertEquals($userRole[0]['role_id'], 15);
    }

    public function testCreateExistingUsernameInDifferentOrg()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => 'deepak', 'date_of_birth' => date('Y-m-d', strtotime("-50 year")), 'date_of_join' => date('Y-m-d'), 'icon' => 'test-oxzionlogo.png', 'managerid' => '471', 'firstname' => 'John', 'lastname' => 'Holt', 'designation' => 'CEO', 'location' => 'USA', 'email' => 'john@gmail.com', 'gender' => 'Male', 'address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456', 'role' => array(['id' => '53012471-2863'])];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/user', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(412);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Username/Email Exists');
    }

    public function testCreateExistingUsername()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => 'deepak', 'date_of_birth' => date('Y-m-d', strtotime("-50 year")), 'date_of_join' => date('Y-m-d'), 'icon' => 'test-oxzionlogo.png', 'managerid' => '471', 'firstname' => 'John', 'lastname' => 'Holt', 'designation' => 'CEO', 'location' => 'USA', 'email' => 'john@gmail.com', 'gender' => 'Male', 'address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456', 'role' => array(['id' => '53012471-2863'])];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/user', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(412);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Username/Email Exists');
    }

    public function testCreateExistingEmailId()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => 'raju', 'date_of_birth' => date('Y-m-d', strtotime("-50 year")), 'date_of_join' => date('Y-m-d'), 'icon' => 'test-oxzionlogo.png', 'managerid' => '471', 'firstname' => 'John', 'lastname' => 'Holt', 'designation' => 'CEO', 'location' => 'USA', 'email' => 'deepak@gmail.com', 'gender' => 'Male', 'address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456', 'role' => array(['id' => '53012471-2863'])];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/user', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);

        $this->assertResponseStatusCode(412);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Email Exists');
    }

    public function testCreateExistingEmailIdInDifferAccount()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => 'raju', 'date_of_birth' => date('Y-m-d', strtotime("-50 year")), 'date_of_join' => date('Y-m-d'), 'icon' => 'test-oxzionlogo.png', 'managerid' => '471', 'firstname' => 'John', 'lastname' => 'Holt', 'designation' => 'CEO', 'location' => 'USA', 'email' => 'deepak@gmail.com', 'gender' => 'Male', 'address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456', 'role' => array(['id' => '53012471-2863'])];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/user', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(412);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Email Exists');
    }

    public function testCreateExistingEmailIdInactiveUser()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => 'raju', 'date_of_birth' => date('Y-m-d', strtotime("-50 year")), 'date_of_join' => date('Y-m-d'), 'icon' => 'test-oxzionlogo.png', 'managerid' => '471', 'firstname' => 'John', 'lastname' => 'Holt', 'designation' => 'CEO', 'location' => 'USA', 'email' => 'prajwal@gmail.com', 'gender' => 'Male', 'address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456', 'role' => array(['id' => '89a01b30-9cc9-416e-8027-1fd2083786c7'])];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/user', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);

        $this->assertResponseStatusCode(412);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'user already exists and is inactive. Please contact the admin to activate');
    }

    public function testCreateExistingUsernameInactiveUser()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => 'prajwal', 'date_of_birth' => date('Y-m-d', strtotime("-50 year")), 'date_of_join' => date('Y-m-d'), 'icon' => 'test-oxzionlogo.png', 'managerid' => '471', 'firstname' => 'John', 'lastname' => 'Holt', 'designation' => 'CEO', 'location' => 'USA', 'email' => 'klmn@gmail.com', 'gender' => 'Male', 'address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456', 'role' => array(['id' => '89a01b30-9cc9-416e-8027-1fd2083786c7'])];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/user', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);

        $this->assertResponseStatusCode(412);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);

        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'user already exists and is inactive. Please contact the admin to activate');
    }

    public function testCreateExistingUsernameInactiveUserWithReactivateFlag()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => 'prajwal', 'date_of_birth' => date('Y-m-d', strtotime("-50 year")), 'date_of_join' => date('Y-m-d'), 'icon' => 'test-oxzionlogo.png', 'managerid' => '471', 'firstname' => 'John', 'lastname' => 'Holt', 'designation' => 'CEO', 'location' => 'USA', 'email' => 'klmn@gmail.com', 'gender' => 'Male', 'address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456', 'role' => array(['id' => '89a01b30-9cc9-416e-8027-1fd2083786c7']), 'reactivate' => 1];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/user', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);

        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);

        $query = "SELECT id from ox_user where username = '" . $data['username'] . "'";
        $userId = $this->executeQueryTest($query);

        $query = "SELECT * from ox_account_user where user_id = " . $userId[0]['id'];
        $userOrg = $this->executeQueryTest($query);

        $query = "SELECT * from ox_user_role where account_user_id = " . $userOrg[0]['id'];
        $userRole = $this->executeQueryTest($query);

        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['username'], $data['username']);
        $this->assertEquals($userOrg[0]['account_id'], 1);
        $this->assertEquals($userRole[0]['role_id'], 16);
    }

    public function testCreateExistingUsernameAndEmailIdInactiveUser()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => 'prajwal', 'date_of_birth' => date('Y-m-d', strtotime("-50 year")), 'date_of_join' => date('Y-m-d'), 'icon' => 'test-oxzionlogo.png', 'managerid' => '471', 'firstname' => 'John', 'lastname' => 'Holt', 'designation' => 'CEO', 'location' => 'USA', 'email' => 'prajwal@gmail.com', 'gender' => 'Male', 'address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456', 'role' => array(['id' => '89a01b30-9cc9-416e-8027-1fd2083786c7'])];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/user', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);

        $this->assertResponseStatusCode(412);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);

        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'user already exists and is inactive. Please contact the admin to activate');
    }

    public function testCreateExistingUsernameAndEmailIdInactiveUserWithReactivateFlag()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => 'prajwal', 'date_of_birth' => date('Y-m-d', strtotime("-50 year")), 'date_of_join' => date('Y-m-d'), 'icon' => 'test-oxzionlogo.png', 'managerid' => '471', 'firstname' => 'John', 'lastname' => 'Holt', 'designation' => 'CEO', 'location' => 'USA', 'email' => 'prajwal@gmail.com', 'gender' => 'Male', 'address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456', 'role' => array(['id' => '89a01b30-9cc9-416e-8027-1fd2083786c7']), 'reactivate' => 1];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/user', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);

        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);

        $query = "SELECT id from ox_user where username = '" . $data['username'] . "'";
        $userId = $this->executeQueryTest($query);

        $query = "SELECT * from ox_account_user where user_id = " . $userId[0]['id'];
        $userOrg = $this->executeQueryTest($query);

        $query = "SELECT * from ox_user_role where account_user_id = " . $userOrg[0]['id'];
        $userRole = $this->executeQueryTest($query);

        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['username'], $data['username']);
        $this->assertEquals($userOrg[0]['account_id'], 1);
        $this->assertEquals($userRole[0]['role_id'], 16);
    }

    public function testCreateUsernameAndEmailIdExist()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => 'prajwal', 'date_of_birth' => date('Y-m-d', strtotime("-50 year")), 'date_of_join' => date('Y-m-d'), 'icon' => 'test-oxzionlogo.png', 'managerid' => '471', 'firstname' => 'John', 'lastname' => 'Holt', 'designation' => 'CEO', 'location' => 'USA', 'email' => 'deepak@gmail.com', 'gender' => 'Male', 'address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456', 'role' => array(['id' => '89a01b30-9cc9-416e-8027-1fd2083786c7'])];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/user', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);

        $this->assertResponseStatusCode(412);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Username or Email Exists in other Account');
    }

    //GET
    public function testGetList()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['uuid'], '4fd99e8e-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][0]['name'], 'Admin Test');
        $this->assertEquals($content['data'][1]['uuid'], '768d1fb9-de9c-46c3-8d5c-23e0e484ce2e');
        $this->assertEquals($content['data'][1]['name'], 'Cleveland Test');
        $this->assertEquals($content['data'][2]['uuid'], 'd9890624-8f42-4201-bbf9-675ec5dc8400');
        $this->assertEquals($content['data'][2]['name'], 'Deepak S');
        $this->assertEquals($content['data'][3]['uuid'], '4fd9f04d-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][3]['name'], 'Employee Test');
        $this->assertEquals($content['data'][4]['uuid'], '4fd9ce37-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][4]['name'], 'Manager Test');
        $this->assertEquals(5, $content['total']);
    }

    public function testGetListWithProjectAndRoles()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['uuid'], '4fd99e8e-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][0]['name'], 'Admin Test');
        $this->assertEquals($content['data'][1]['uuid'], '768d1fb9-de9c-46c3-8d5c-23e0e484ce2e');
        $this->assertEquals($content['data'][1]['name'], 'Cleveland Test');
        $this->assertEquals($content['data'][2]['uuid'], 'd9890624-8f42-4201-bbf9-675ec5dc8400');
        $this->assertEquals($content['data'][2]['name'], 'Deepak S');
        $this->assertEquals($content['data'][3]['uuid'], '4fd9f04d-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][3]['name'], 'Employee Test');
        $this->assertEquals($content['data'][4]['uuid'], '4fd9ce37-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][4]['name'], 'Manager Test');
        $this->assertIsArray($content['data'][0]['project'],"assert variable Project is not an array");
        $this->assertIsArray($content['data'][1]['project'],"assert variable Project is not an array");
        $this->assertIsArray($content['data'][2]['project'],"assert variable Project is not an array");
        $this->assertIsArray($content['data'][3]['project'],"assert variable Project is not an array");
        $this->assertIsArray($content['data'][4]['project'],"assert variable Project is not an array");

        $this->assertIsArray($content['data'][0]['role'],"assert variable Role is not an array");
        $this->assertIsArray($content['data'][1]['role'],"assert variable Role is not an array");
        $this->assertIsArray($content['data'][2]['role'],"assert variable Role is not an array");
        $this->assertIsArray($content['data'][3]['role'],"assert variable Role is not an array");
        $this->assertIsArray($content['data'][4]['role'],"assert variable Role is not an array");
        $this->assertEquals(5, $content['total']);
    }

    public function testGetListWithSort()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user?filter=[{"sort":[{"field":"name","dir":"desc"}],"skip":0,"take":2}]
', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 2);
        $this->assertEquals($content['data'][0]['uuid'], '4fd9ce37-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][0]['name'], 'Manager Test');
        $this->assertEquals($content['data'][1]['uuid'], '4fd9f04d-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][1]['name'], 'Employee Test');
        $this->assertEquals(5, $content['total']);
    }

    public function testGetListWithSortForCountry()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user?filter=[{"sort":[{"field":"country","dir":"dsc"}],"skip":0,"take":2}]
', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 2);
        $this->assertEquals($content['data'][0]['uuid'], '768d1fb9-de9c-46c3-8d5c-23e0e484ce2e');
        $this->assertEquals($content['data'][0]['name'], 'Cleveland Test');
        $this->assertEquals($content['data'][1]['uuid'], 'd9890624-8f42-4201-bbf9-675ec5dc8400');
        $this->assertEquals($content['data'][1]['name'], 'Deepak S');
    }

    public function testGetListWithFilterForCountry()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user?filter=[{"filter":{"logic":"and","filters":[{"field":"country","operator":"startswith","value":"Ghana"}]},"sort":[{"field":"id","dir":"asc"},{"field":"uuid","dir":"dsc"}],"skip":0,"take":2}]
', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 2);
        $this->assertEquals($content['data'][0]['uuid'], '4fd9ce37-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][0]['name'], 'Manager Test');
        $this->assertEquals($content['data'][1]['uuid'], '4fd9f04d-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][1]['name'], 'Employee Test');
    }

    public function testGetListWithMultipleFilter()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user?filter=[{"filter":{"logic":"and","filters":[{"field":"country","operator":"startswith","value":"in"},{"field":"address1","operator":"endswith","value":"r"},{"field":"address2","operator":"contains","value":"mba"},{"field":"state","operator":"startswith","value":"Tamil"}]},"sort":[{"field":"id","dir":"asc"},{"field":"uuid","dir":"dsc"}],"skip":0,"take":2}]
', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 1);
        $this->assertEquals($content['data'][0]['uuid'], 'd9890624-8f42-4201-bbf9-675ec5dc8400');
        $this->assertEquals($content['data'][0]['name'], 'Deepak S');
    }
    public function testGetListSortWithPageSize()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user?filter=[{"sort":[{"field":"name","dir":"asc"}],"skip":2,"take":2}]
', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 2);
        $this->assertEquals($content['data'][0]['uuid'], '4fd99e8e-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][0]['name'], 'Admin Test');
        $this->assertEquals($content['data'][1]['uuid'], '768d1fb9-de9c-46c3-8d5c-23e0e484ce2e');
        $this->assertEquals($content['data'][1]['name'], 'Cleveland Test');
        $this->assertEquals(5, $content['total']);
    }

    public function testGetListwithQueryParameters()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user?filter=[{"filter":{"logic":"and","filters":[{"field":"name","operator":"endswith","value":"r Test"},{"field":"designation","operator":"startswith","value":"it"}]},"sort":[{"field":"id","dir":"asc"},{"field":"uuid","dir":"dsc"}],"skip":0,"take":2}]
', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 1);
        $this->assertEquals($content['data'][0]['uuid'], '4fd9ce37-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][0]['name'], 'Manager Test');
        $this->assertEquals($content['total'], 1);
    }

    public function testGetListwithQueryWithPageSize()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user?filter=[{"filter":{"logic":"and","filters":[{"field":"name","operator":"endswith","value":"n Test"},{"field":"designation","operator":"startswith","value":"it"}]},"sort":[{"field":"id","dir":"asc"},{"field":"uuid","dir":"dsc"}],"skip":0,"take":1}]
', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 1);
        $this->assertEquals($content['data'][0]['uuid'], '4fd99e8e-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][0]['name'], 'Admin Test');
        $this->assertEquals($content['total'], 1);
    }

    public function testGetListwithQueryPage()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user?filter=[{"skip":0,"take":1}]
', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 1);
        $this->assertEquals($content['data'][0]['uuid'], '4fd99e8e-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][0]['name'], 'Admin Test');
    }

    public function testGetListwithQueryPageNo()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user?filter=[{"skip":1,"take":2}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['uuid'], '4fd99e8e-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][0]['name'], 'Admin Test');
        $this->assertEquals($content['data'][1]['uuid'], '768d1fb9-de9c-46c3-8d5c-23e0e484ce2e');
        $this->assertEquals($content['data'][1]['name'], 'Cleveland Test');
    }

    public function testGet()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user/4fd99e8e-758f-11e9-b2d5-68ecc57cde45', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['username'], $this->adminUser);
        $this->assertEquals($content['data']['name'], 'Admin Test');
        $this->assertEquals($content['data']['country'], 'Germany');
        $this->assertEquals($content['data']['accountId'], '53012471-2863-4949-afb1-e69b0891c98a');
        $query = "SELECT u.uuid from ox_user u
                    inner join ox_employee e on e.person_id = u.person_id
                    inner join ox_employee m on m.id = e.manager_id";
        $result = $this->executeQueryTest($query);
        $this->assertEquals($result[0]['uuid'], $content['data']['managerId']);
    }

    public function testGetNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user/4fd99e8e-758f-11e9-b2d5-68ecc57c3456', 'GET');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testUpdate()
    {
        $data = ['firstname' => 'John', 'lastname' => 'Holt'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $userId = '4fd99e8e-758f-11e9-b2d5-68ecc57cde45';
        $this->dispatch("/user/$userId", 'PUT', null);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['firstname'], $data['firstname']);
        $this->assertEquals($content['data']['lastname'], $data['lastname']);
        $query = "SELECT * from ox_user where uuid = '$userId'";
        $userData = $this->executeQueryTest($query);
        $query = "SELECT * from ox_person where id = " . $userData[0]['person_id'];
        $personData = $this->executeQueryTest($query);
        $this->assertEquals($data['firstname'] . " " . $data['lastname'], $userData[0]['name']);
        $this->assertEquals($data['firstname'], $personData[0]['firstname']);
        $this->assertEquals($data['lastname'], $personData[0]['lastname']);
    }

    public function testUpdateWithAddress()
    {
        $data = ['firstname' => 'John', 'lastname' => 'Holt', 'address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $userId = '4fd99e8e-758f-11e9-b2d5-68ecc57cde45';
        $this->dispatch("/user/$userId", 'PUT', null);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['firstname'], $data['firstname']);
        $this->assertEquals($content['data']['lastname'], $data['lastname']);

        $query = "SELECT a.*, u.name, p.firstname, p.lastname from ox_address a
                    inner join ox_person p on p.address_id = a.id
                    inner join ox_user u on u.person_id = p.id
                    where u.uuid = '$userId'";
        $userData = $this->executeQueryTest($query);
        $this->assertEquals($data['firstname'] . " " . $data['lastname'], $userData[0]['name']);
        $this->assertEquals($data['firstname'], $userData[0]['firstname']);
        $this->assertEquals($data['lastname'], $userData[0]['lastname']);

        $this->assertEquals($data['address1'], $userData[0]['address1']);
        $this->assertEquals($data['city'], $userData[0]['city']);
        $this->assertEquals($data['state'], $userData[0]['state']);
        $this->assertEquals($data['country'], $userData[0]['country']);
        $this->assertEquals($data['zip'], $userData[0]['zip']);
    }

    public function testUpdateWithAccountId()
    {
        $data = ['firstname' => 'John', 'lastname' => 'Holt'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $userId = '4fd99e8e-758f-11e9-b2d5-68ecc57cde45';
        $this->dispatch("/account/53012471-2863-4949-afb1-e69b0891c98a/user/$userId", 'PUT', null);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['firstname'], $data['firstname']);
        $this->assertEquals($content['data']['lastname'], $data['lastname']);
        $query = "SELECT u.name, p.firstname, p.lastname from ox_person p
                    inner join ox_user u on u.person_id = p.id
                    where u.uuid = '$userId'";
        $userData = $this->executeQueryTest($query);
        $this->assertEquals($data['firstname'] . " " . $data['lastname'], $userData[0]['name']);
        $this->assertEquals($data['firstname'], $userData[0]['firstname']);
        $this->assertEquals($data['lastname'], $userData[0]['lastname']);
    }

    public function testUpdateWithInvalidAccountId()
    {
        $data = ['firstname' => 'John', 'lastname' => 'Holt'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/user/4fd99e8e-758f-11e9-b2d5-68ecc57cde45', 'PUT', null);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(412);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'User does not belong to the Account');
    }

    public function testUpdateWithInvalidUserid()
    {
        $data = ['firstname' => 'John', 'lastname' => 'Holt'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/user/4fd99e8e-e9-b2d5-68ecc57cde45', 'PUT', null);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Entity not found.');
    }

    public function testUpdateNotFound()
    {
        $data = ['firstname' => 'Test', 'lastname' => 'User'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/user/4fd99e8e-758f-11e9-b2d5-68ecc57c6543', 'PUT', null);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testDelete()
    {
        $this->initAuthToken($this->adminUser);
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $payload = json_encode(array('username' => $this->employeeUser, 'accountName' => 'Cleveland Black'));
            $mockMessageProducer->expects('sendTopic')->with($payload, 'USER_DELETED')->once()->andReturn();
        }
        $userId = '4fd9f04d-758f-11e9-b2d5-68ecc57cde45';
        $this->dispatch("/user/$userId", 'DELETE');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        $query = "SELECT * from ox_user where uuid = '$userId'";
        $userData = $this->executeQueryTest($query);
        $this->assertEquals('Inactive', $userData[0]['status']);
    }

    public function testDeleteProjectManager()
    {
        $this->initAuthToken($this->adminUser);
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('username' => $this->employeeUser, 'accountName' => 'Cleveland Black')), 'USER_DELETED')->once()->andReturn();
        }
        $this->dispatch('/user/768d1fb9-de9c-46c3-8d5c-23e0e484ce2e', 'DELETE');
        $this->assertResponseStatusCode(403);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Not allowed to delete the project manager');
    }

    public function testDeleteTeamManager()
    {
        $this->initAuthToken($this->adminUser);
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('username' => $this->employeeUser, 'accountName' => 'Cleveland Black')), 'USER_DELETED')->once()->andReturn();
        }
        $this->dispatch('/user/4fd9ce37-758f-11e9-b2d5-68ecc57cde45', 'DELETE');
        $this->assertResponseStatusCode(403);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Not allowed to delete the team manager');
    }

    public function testDeleteWithAccountId()
    {
        $this->initAuthToken($this->adminUser);
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('username' => $this->employeeUser, 'accountName' => 'Cleveland Black')), 'USER_DELETED')->once()->andReturn();
        }
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/user/4fd9f04d-758f-11e9-b2d5-68ecc57cde45', 'DELETE');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testDeleteInvalidAccountId()
    {
        $this->initAuthToken($this->adminUser);
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('username' => $this->employeeUser, 'accountName' => 'Cleveland Black')), 'USER_DELETED')->once()->andReturn();
        }
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/user/4fd9f04d-758f-11e9-b2d5-68ecc57cde45', 'DELETE');
        $this->assertResponseStatusCode(412);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'User does not belong to the account');
    }

    public function testDeleteInvalidUserID()
    {
        $this->initAuthToken($this->adminUser);
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('username' => $this->employeeUser, 'accountName' => 'Cleveland Black')), 'USER_DELETED')->once()->andReturn();
        }
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/user/4fd9f04d-7e9-b2d5-68ecc57cde45', 'DELETE');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Entity not found.');
    }

    public function testDeleteAdminUser()
    {
        $this->initAuthToken($this->adminUser);
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('username' => $this->employeeUser, 'accountName' => 'Cleveland Black')), 'USER_DELETED')->once()->andReturn();
        }
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/user/4fd99e8e-758f-11e9-b2d5-68ecc57cde45', 'DELETE');
        $this->assertResponseStatusCode(403);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Not allowed to delete Admin user');
    }

    public function testDeleteNotFound()
    {
        $this->initAuthToken($this->adminUser);
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
        }
        $this->dispatch('/user/122', 'DELETE');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testAssignManagerToUser()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user/4fd99e8e-758f-11e9-b2d5-68ecc57cde45/assign/4fd9ce37-758f-11e9-b2d5-68ecc57cde45', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('assignUserManager');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(2, $this->getConnection()->getRowCount('ox_employee_manager'));
    }

    public function testAssignUserExists()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user/4fd99e8e-758f-11e9-b2d5-68ecc57cde45/assign/4fd9f04d-758f-11e9-b2d5-68ecc57cde45', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(406);
        $this->setDefaultAsserts('assignUserManager');
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Employee already assigned to the manager');
        $this->assertEquals(1, $this->getConnection()->getRowCount('ox_employee_manager'));
    }

    public function testRemoveManagerToUser()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user/4fd99e8e-758f-11e9-b2d5-68ecc57cde45/remove/4fd9f04d-758f-11e9-b2d5-68ecc57cde45', 'delete', null);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('removeUserManager');
        $this->assertEquals($content['status'], 'success');
    }

    public function testUserLoginToken()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user/usertoken', 'get');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('userToken');
        $this->assertEquals($content['status'], 'success');
    }

    public function testUserSearch()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['searchVal' => 'Manager', 'firstname' => 'Manager', 'lastname' => 'Test'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/user/usersearch', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('userSearch');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['firstname'], $data['firstname']);
        $this->assertEquals($content['data'][0]['lastname'], $data['lastname']);
    }

    public function testUserSearchNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['searchVal' => 'errorName', 'firstname' => 'Karan', 'lastname' => 'Agarwal'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/user/usersearch', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('userSearch');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], "No results found for " . $data['searchVal']);
    }

    public function testChangePassword()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['old_password' => 'Welcome2eox!', 'new_password' => 'welcome', 'confirm_password' => 'welcome'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/user/me/changepassword', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('changepassword');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['message'], 'Password changed successfully!');
    }

    public function testChangePasswordWithWrongOldPassword()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['old_password' => 'wrongPassword', 'new_password' => 'welcome', 'confirm_password' => 'welcome'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/user/me/changepassword', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts('changepassword');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testChangePasswordWithDifferentPassword()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['old_password' => 'Welcome2eox!', 'new_password' => 'welcome', 'confirm_password' => 'wrongConfirmPassword'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/user/me/changepassword', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts('changepassword');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testChangePasswordWithAllWrongData()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['old_password' => 'wrongPassword', 'new_password' => 'wrongNewPassword', 'confirm_password' => 'wrongConfirmPassword'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/user/me/changepassword', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts('changepassword');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testLoggedInUser()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user/me/m', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('loggedInUser');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['data']['username'], $this->adminUser);
        $this->assertEquals($content['data']['name'], 'Admin Test');
    }

    public function testLoggedInUserCompleteDetails()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user/me/a', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('loggedInUser');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['username'], $this->adminUser);
    }

    public function testLoggedInUserComboDetails()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user/me/a+ap', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('loggedInUser');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['active_account']['accountId'], '53012471-2863-4949-afb1-e69b0891c98a');
        $this->assertEquals($content['data']['active_account']['name'], 'Cleveland Black');
        $this->assertEquals(count($content['data']['apps']), 10);
        $apps = ['Admin', 'Analytics', 'AppBuilder', 'CRM', 'Documents', 'MailAdmin', 'OrgChart', 'Task', 'TaskAdmin'];
        foreach ($apps as $key => $value) {
            $this->assertEquals($content['data']['apps'][$key]['name'], $value);
        }
    }

    public function testLoggedInUserComboWithProjects()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user/me/a+pr', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('loggedInUser');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['projects'][0]['name'], 'Test Project 1');
        $this->assertEquals($content['data']['projects'][0]['uuid'], '886d7eff-6bae-4892-baf8-6fefc56cbf0b');
    }

    public function testUserAccess()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user/me/access', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('getUserAppsAndPrivileges');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertNotEmpty($content['data']['privilege']);
        $this->assertNotEmpty($content['data']['whiteListedApps']);
        $this->assertEquals(true, count($content['data']['whiteListedApps']) > 0);
    }

    public function testGetUserProjectWithdata()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['data' => array([
            "id" => "1",
            "name" => "Test Project 1",
            "account_id" => "1",
            "description" => "Description Test Data",
            "created_by" => "1",
            "modified_by" => "1",
            "date_created" => "2018-11-11 07:25:06",
            "date_modified" => "2018-12-11 07:25:06",
            "isdeleted" => "0",
            "user_id" => "1",
            "project_id" => "1",
        ], [
            "id" => "3",
            "name" => "Test Project 2",
            "account_id" => "1",
            "description" => "Description Test Data",
            "created_by" => "1",
            "modified_by" => "1",
            "date_created" => "2018-11-11 07:25:06",
            "date_modified" => "2018-12-11 07:25:06",
            "isdeleted" => "0",
            "user_id" => "1",
            "project_id" => "2",
        ])];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/user/1/project', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('getuserproject');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 1);
    }

    public function testGetUserProjectWithoutdata()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['data' => array([])];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/user/5/project', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('getuserproject');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 0);
    }

    public function testSaveMe()
    {
        $data = ['name' => 'John Holt', 'firstname' => 'John', 'lastname' => 'Holt'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/user/me/save', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('saveMe');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals($content['data']['firstname'], $data['firstname']);
        $this->assertEquals($content['data']['lastname'], $data['lastname']);
        $query = "SELECT * from ox_user where username = '" . $this->adminUser . "'";
        $userData = $this->executeQueryTest($query);
        $query = "SELECT * from ox_person where id = " . $userData[0]['person_id'];
        $personData = $this->executeQueryTest($query);
        $this->assertEquals($data['firstname'] . " " . $data['lastname'], $userData[0]['name']);
        $this->assertEquals($data['firstname'], $personData[0]['firstname']);
        $this->assertEquals($data['lastname'], $personData[0]['lastname']);
    }

    public function testSaveMeWithPreferenceData()
    {
        $preferences = array('currency' => 'INR', 'timezone' => 'Asia/Calcutta', 'dateformat' => 'dd/mm/yyy');
        $data = ['preferences' => json_encode($preferences)];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/user/me/save', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('saveMe');
        $this->assertEquals($content['status'], 'success');
        $query = "SELECT * from ox_user where username = '" . $this->adminUser . "'";
        $userData = $this->executeQueryTest($query);
        $query = "SELECT * from ox_person where id = " . $userData[0]['person_id'];
        $queryData = $this->executeQueryTest($query);
        $this->assertEquals($queryData[0]['firstname'] . " " . $queryData[0]['lastname'], $userData[0]['name']);
    }

    public function testBlackListApps()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user/me/bapp', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('loggedInUser');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']['blackListedApps']), 0);
    }

    public function testBlackListAppsForEmployee()
    {
        $this->initAuthToken($this->employeeUser);
        $this->dispatch('/user/me/bapp', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('loggedInUser');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['blackListedApps']['Admin'], 'f297dd6a-3eb4-4e06-83ad-fb289e5c0535');
    }

    public function testBlackListAppsForManager()
    {
        $this->initAuthToken($this->managerUser);
        $this->dispatch('/user/me/bapp', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('loggedInUser');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testGetExcludedUserList()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['exclude' => array('4fd9f04d-758f-11e9-b2d5-68ecc57cde45', '768d1fb9-de9c-46c3-8d5c-23e0e484ce2e'), 'filter' => json_encode(array('0' => array('filter' => array('logic' => 'and', 'filters' => array(['field' => 'name', 'operator' => 'endswith', 'value' => 'r Test'], ['field' => 'designation', 'operator' => 'startswith', 'value' => 'it'])), 'sort' => array(['field' => 'id', 'dir' => 'asc'], ['field' => 'uuid', 'dir' => 'dsc']), 'skip' => 0, 'take' => 2)))];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/users/list', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('usersList');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['name'], 'Manager Test');
    }

    public function testGetExcludedUserListWithExcludedUserFilter()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['exclude' => array('4fd9f04d-758f-11e9-b2d5-68ecc57cde45', '768d1fb9-de9c-46c3-8d5c-23e0e484ce2e', '4fd9ce37-758f-11e9-b2d5-68ecc57cde45'), 'filter' => json_encode(array('0' => array('filter' => array('logic' => 'and', 'filters' => array(['field' => 'name', 'operator' => 'endswith', 'value' => 'al'], ['field' => 'designation', 'operator' => 'startswith', 'value' => 'it'])), 'sort' => array(['field' => 'id', 'dir' => 'asc'], ['field' => 'uuid', 'dir' => 'dsc']), 'skip' => 0, 'take' => 2)))];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/users/list', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('usersList');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['total'], 0);
    }

    public function testGetExcludedUserListWithAccountId()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['exclude' => array('4fd9f04d-758f-11e9-b2d5-68ecc57cde45', '768d1fb9-de9c-46c3-8d5c-23e0e484ce2e'), 'filter' => json_encode(array('0' => array('sort' => array(['field' => 'id', 'dir' => 'asc'], ['field' => 'uuid', 'dir' => 'dsc']), 'skip' => 0, 'take' => 20)))];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/users/list', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('usersList');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['name'], 'Admin Test');
        $this->assertEquals($content['data'][1]['name'], 'Manager Test');
        $this->assertEquals($content['data'][2]['name'], 'Deepak S');
    }

    public function testgetUserProfileDetail()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/user/4fd99e8e-758f-11e9-b2d5-68ecc57cde45/profile', 'GET');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('getuserdetaillist');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], 'Admin Test');
        $this->assertEquals($content['data']['role'][0]['name'], 'ADMIN');
        $this->assertEquals($content['data']['role'][1]['name'], 'MANAGER');
    }

    public function testgetUserProfileDetailInvalidOrAccount()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/5301247-4949-afb1-e69b0891c98a/user/4fd99e8e-758f-11e9-b2d5-68ecc57cde45/profile', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('getuserdetaillist');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['data'], array());
        $this->assertEquals($content['data']['role'], array());
    }

    public function testgetUserProfileDetailInvalidUserId()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/user/4fd99e8e-758f-11e9-bcc57cde45/profile', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('getuserdetaillist');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['data'], array());
        $this->assertEquals($content['data']['role'], array());
    }

    public function testgetUserProfileDetailDifferentAccount()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/user/4fd99e8e-758f-11e9-b2d5-68ecc57cde45/profile', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('getuserdetaillist');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['data'], array());
        $this->assertEquals($content['data']['role'], array());
    }

    public function testGetPrivacyPolicyWithData()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user/me/hasLoggedIn', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('User');
        $this->assertControllerName(UserController::class);
        $this->assertControllerClass('UserController');
        $this->assertMatchedRouteName('get_loggedIn');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }
    public function testGetPrivacyPolicyWithoutdata()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user/me/hasLoggedIn', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('User');
        $this->assertControllerName(UserController::class);
        $this->assertControllerClass('UserController');
        $this->assertMatchedRouteName('get_loggedIn');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 2);
    }
    public function testUpdatePrivacyPolicyData()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/user/me/updateLoggedIn', 'POST');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('User');
        $this->assertControllerName(UserController::class);
        $this->assertControllerClass('UserController');
        $this->assertMatchedRouteName('update_loggedIn');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }
    public function testUserAccountList()
    {
        $this->initAuthToken($this->employeeUser);
        $this->dispatch('/user/me/acc', 'GET');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('loggedInUser');
        $this->assertEquals($content['status'], 'success');
        $this->assertGreaterThanOrEqual(1, count($content['data']['accounts']));
        $this->assertEquals($content['data']['accounts'][0]['accountId'], '53012471-2863-4949-afb1-e69b0891c98a');
        $this->assertEquals($content['data']['accounts'][0]['name'], 'Cleveland Black');
    }

    public function testSwitchAccount()
    {
        $data = ['accountId' => 'b0971de7-0387-48ea-8f29-5d3704d96a46'];
        $this->initAuthToken('deepak');
        $this->dispatch('/user/switchaccount', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('switchAccount');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(is_null($content['data']['jwt']), false);

        $this->reset();

        $this->getRequest()->getHeaders()->addHeaderLine('Authorization', 'Bearer ' . $content['data']['jwt']);
        $this->dispatch('/user/me/a', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('loggedInUser');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['active_account']['accountId'], 'b0971de7-0387-48ea-8f29-5d3704d96a46');
    }
}
