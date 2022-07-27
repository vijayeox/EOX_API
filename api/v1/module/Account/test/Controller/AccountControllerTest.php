<?php
namespace Account;

use Mockery;
use Account\Controller\AccountController;
use Oxzion\Service\AccountService;
use Oxzion\Test\ControllerTest;
use Oxzion\Utils\FileUtils;
use PHPUnit\DbUnit\DataSet\YamlDataSet;

class AccountControllerTest extends ControllerTest
{
    protected $topic;

    public function setUp(): void
    {
        $this->loadConfig();
        parent::setUp();
    }

    public function getMockMessageProducer()
    {
        $accountService = $this->getApplicationServiceLocator()->get(AccountService::class);
        $mockMessageProducer = Mockery::mock('Oxzion\Messaging\MessageProducer');
        $accountService->setMessageProducer($mockMessageProducer);
        return $mockMessageProducer;
    }

    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__) . "/../Dataset/Account.yml");
        $dataset->addYamlFile(dirname(__FILE__) . "/../../../Team/test/Dataset/Team.yml");
        $dataset->addYamlFile(dirname(__FILE__) . "/../../../Project/test/Dataset/Project.yml");
        $dataset->addYamlFile(dirname(__FILE__) . "/../../../Announcement/test/Dataset/Announcement.yml");
        return $dataset;
    }
    protected function setDefaultAsserts()
    {
        $this->assertModuleName('Account');
        $this->assertControllerName(AccountController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AccountController');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    }
    // Testing to see if the Create Contact function is working as intended if all the value passed are correct.
    
    public function testGetListWithQuery()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account?filter=[{"filter":{"logic":"and","filters":[{"field":"name","operator":"endswith","value":"rs"},{"field":"state","operator":"contains","value":"oh"}]},"sort":[{"field":"id","dir":"asc"},{"field":"uuid","dir":"dsc"}],"skip":0,"take":1}]
', 'GET');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(1, count($content['data']));
        $this->assertEquals($content['data'][0]['uuid'], 'b0971de7-0387-48ea-8f29-5d3704d96a46');
        $this->assertEquals($content['data'][0]['name'], 'Golden State Warriors');
        $this->assertEquals($content['total'], 1);
    }

    public function testGetList()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account', 'GET');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(4, count($content['data']));
        $this->assertEquals($content['data'][0]['uuid'], '53012471-2863-4949-afb1-e69b0891c98a');
        $this->assertEquals($content['data'][0]['name'], 'Cleveland Black');
        $this->assertEquals($content['data'][1]['uuid'], 'b0971de7-0387-48ea-8f29-5d3704d96a46');
        $this->assertEquals($content['data'][1]['name'], 'Golden State Warriors');
        $this->assertEquals($content['data'][2]['uuid'], 'c7499a34-c100-4e41-bece-5822adca3223');
        $this->assertEquals($content['data'][2]['name'], 'Sample Child Organization');
        $this->assertEquals($content['data'][3]['uuid'], 'b6499a34-c100-4e41-bece-5822adca3844');
        $this->assertEquals($content['data'][3]['name'], 'Sample Organization');
        $this->assertEquals($content['data'][2]['parentId'], '915d207e-ac75-11ea-bb37-0242ac130002');
        $this->assertEquals($content['data'][2]['parentName'], $content['data'][3]['name']);
        $this->assertEquals($content['total'], 4);
    }

    public function testGetUserOrgList()
    {
        $this->initAuthToken($this->employeeUser);
        $this->dispatch('/account', 'GET');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(1, count($content['data']));
        $this->assertEquals($content['data'][0]['uuid'], '53012471-2863-4949-afb1-e69b0891c98a');
        $this->assertEquals($content['data'][0]['name'], 'Cleveland Black');
        $this->assertEquals($content['total'], 1);
    }

    
    public function testGetListWithQueryField()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account?filter=[{"filter":{"logic":"and","filters":[{"field":"state","operator":"contains","value":"oh"}]},"skip":0,"take":1}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(1, count($content['data']));
        $this->assertEquals($content['data'][0]['uuid'], '53012471-2863-4949-afb1-e69b0891c98a');
        $this->assertEquals($content['data'][0]['name'], 'Cleveland Black');
        $this->assertEquals($content['total'], 2);
    }

    public function testGetListWithQueryPageSize()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account?filter=[{"skip":0,"take":1}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(1, count($content['data']));
        $this->assertEquals($content['data'][0]['uuid'], '53012471-2863-4949-afb1-e69b0891c98a');
        $this->assertEquals($content['data'][0]['name'], 'Cleveland Black');
        $this->assertEquals($content['total'], 4);
    }

    public function testGet()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['uuid'], '53012471-2863-4949-afb1-e69b0891c98a');
        $this->assertEquals($content['data']['name'], 'Cleveland Black');
    }

    public function testGetNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-494', 'GET');
        $this->assertResponseStatusCode(404);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    private function performBaseCreateTest($data, $mainOrgId = null)
    {
        $this->setJsonContent(json_encode($data));
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('accountName' => $data['name'], 'status' => isset($data['status']) ? $data['status'] : 'Active')), 'ACCOUNT_ADDED')->once()->andReturn();
        }
        $this->dispatch('/account', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('account');
        $contact = json_decode($data['contact'], true);
        $query = "SELECT * from ox_role where account_id = (SELECT id from ox_account where uuid = '" . $content['data']['uuid'] . "')";
        $role = $this->executeQueryTest($query);
        for ($x = 0; $x < sizeof($role); $x++) {
            $query = "SELECT count(id) from ox_role_privilege where account_id = (SELECT id from ox_account where role_id =" . $role[$x]['id'] . "
                AND uuid = '" . $content['data']['uuid'] . "')";
            $rolePrivilegeResult[] = $this->executeQueryTest($query);
        }
        $select = "SELECT * FROM ox_user_role where role_id =" . $role[0]['id'];
        $roleResult = $this->executeQueryTest($select);
        $select = "SELECT * FROM ox_account_user where account_id = (SELECT id from ox_account where uuid ='" . $content['data']['uuid'] . "')";
        $accountResult = $this->executeQueryTest($select);
        $select = "SELECT ox_user.*,ox_person.firstname,ox_person.lastname,ox_person.address_id,ox_employee.designation FROM ox_user inner join ox_person on ox_person.id = ox_user.person_id inner join ox_employee on ox_employee.person_id = ox_person.id where ox_user.username ='" . $contact['username'] . "'";
        $usrResult = $this->executeQueryTest($select);
        $select = "SELECT ox_address.address1,ox_organization.uuid,ox_account.uuid,ox_account.name, ox_account.type, ox_organization.parent_id, ox_account.organization_id
            from ox_address 
            join ox_organization on ox_address.id = ox_organization.address_id 
            join ox_account on ox_account.organization_id=ox_organization.id 
            where name = '".$data['name']."'";
        $account = $this->executeQueryTest($select);
        $query = "SELECT * from ox_app_registry where account_id = (SELECT id from ox_account where uuid = '" . $content['data']['uuid'] . "')";
        $appResult = $this->executeQueryTest($query);
        $this->assertEquals(count($role), 3);
        $this->assertEquals(count($roleResult), 1);
        $this->assertEquals(count($accountResult), 1);
        $this->assertEquals($usrResult[0]['firstname'], $contact['firstname']);
        $this->assertEquals($usrResult[0]['lastname'], $contact['lastname']);
        $this->assertEquals($usrResult[0]['designation'], 'Admin');
        $this->assertEquals($rolePrivilegeResult[0][0]['count(id)'], 36);
        $this->assertEquals($rolePrivilegeResult[1][0]['count(id)'], 10);
        $this->assertEquals($rolePrivilegeResult[2][0]['count(id)'], 8);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals(isset($usrResult[0]['address_id']), true);
        $this->assertEquals($account[0]['address1'], $data['address1']);
        $this->assertEquals($account[0]['type'], 'BUSINESS');
        $this->assertEquals($appResult[0]['app_id'], 1);
        if (isset($data['parentId'])) {
            $this->assertEquals(!empty($account[0]['parent_id']), true);
            $query = "SELECT oh.* from ox_org_heirarchy oh
                    inner join ox_organization org on org.id = oh.main_org_id
                    where oh.child_id = ".$account[0]['organization_id'];
            $orgHeirarchy = $this->executeQueryTest($query);
            $this->assertEquals(1, count($orgHeirarchy));
            $this->assertEquals($orgHeirarchy[0]['main_org_id'], $mainOrgId);
            $this->assertEquals($account[0]['parent_id'], $orgHeirarchy[0]['parent_id']);
        } else {
            $this->assertEquals($account[0]['parent_id'], null);
        }
    }

    public function testCreate()
    {
        $this->initAuthToken($this->adminUser);
        $config = $this->getApplicationConfig();
        $tempFolder = $config['UPLOAD_FOLDER'] . "account/" . $this->testAccountId . "/";
        FileUtils::createDirectory($tempFolder);
        copy(__DIR__ . "/../files/logo.png", $tempFolder . "logo.png");
        $contact = array('username' => 'goku', 'firstname' => 'Bharat', 'lastname' => 'Gogineni', 'email' => 'barat@myvamla.com', 'phone' => '1234567890');
        $preferences = array('currency' => 'INR', 'timezone' => 'Asia/Calcutta', 'dateformat' => 'dd/mm/yyy');
        $data = array('name' => 'ORGANIZATION', 'address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456', 'contact' => json_encode($contact), 'preferences' => json_encode($preferences));
        $this->performBaseCreateTest($data);
    }

    public function testCreateChildOrganization()
    {
        $this->initAuthToken($this->adminUser);
        $config = $this->getApplicationConfig();
        $tempFolder = $config['UPLOAD_FOLDER'] . "account/" . $this->testAccountId . "/";
        FileUtils::createDirectory($tempFolder);
        copy(__DIR__ . "/../files/logo.png", $tempFolder . "logo.png");
        $contact = array('username' => 'goku', 'firstname' => 'Bharat', 'lastname' => 'Gogineni', 'email' => 'barat@myvamla.com', 'phone' => '1234567890');
        $preferences = array('currency' => 'INR', 'timezone' => 'Asia/Calcutta', 'dateformat' => 'dd/mm/yyy');
        $data = array('name' => 'CHILD ORGANIZATION', 'address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456', 'contact' => json_encode($contact), 'preferences' => json_encode($preferences), 'parentId' => '915d207e-ac75-11ea-bb37-0242ac130002');
        $this->performBaseCreateTest($data, 101);
    }

    public function testCreateGrandChildOrganization()
    {
        $this->initAuthToken($this->adminUser);
        $config = $this->getApplicationConfig();
        $tempFolder = $config['UPLOAD_FOLDER'] . "account/" . $this->testAccountId . "/";
        FileUtils::createDirectory($tempFolder);
        copy(__DIR__ . "/../files/logo.png", $tempFolder . "logo.png");
        $contact = array('username' => 'goku', 'firstname' => 'Bharat', 'lastname' => 'Gogineni', 'email' => 'barat@myvamla.com', 'phone' => '1234567890');
        $preferences = array('currency' => 'INR', 'timezone' => 'Asia/Calcutta', 'dateformat' => 'dd/mm/yyy');
        $data = array('name' => 'CHILD ORGANIZATION', 'address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456', 'contact' => json_encode($contact), 'preferences' => json_encode($preferences), 'parentId' => 'a25d22cc-ac75-11ea-bb37-0242ac130013');
        $this->performBaseCreateTest($data, 101);
    }

    public function testCreateIndiividualAccount()
    {
        $this->initAuthToken($this->adminUser);
        $config = $this->getApplicationConfig();
        $contact = array('username' => 'goku', 'firstname' => 'Bharat', 'lastname' => 'Gogineni', 'email' => 'barat@myvamla.com', 'phone' => '1234567890');
        $preferences = array('currency' => 'INR', 'timezone' => 'Asia/Calcutta', 'dateformat' => 'dd/mm/yyy');
        $data = array('type' => 'INDIVIDUAL', 'address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456', 'contact' => json_encode($contact), 'preferences' => json_encode($preferences));
        $this->setJsonContent(json_encode($data));
        $accountName = $contact['firstname']." ".$contact['lastname'];
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('accountName' => $accountName, 'status' => 'Active')), 'ACCOUNT_ADDED')->once()->andReturn();
        }
        $this->dispatch('/account', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('account');
        $query = "SELECT * from ox_role where account_id = (SELECT id from ox_account where uuid = '" . $content['data']['uuid'] . "')";
        $role = $this->executeQueryTest($query);
        for ($x = 0; $x < sizeof($role); $x++) {
            $query = "SELECT count(id) from ox_role_privilege where account_id = (SELECT id from ox_account where role_id =" . $role[$x]['id'] . "
                AND uuid = '" . $content['data']['uuid'] . "')";
            $rolePrivilegeResult[] = $this->executeQueryTest($query);
        }
        $select = "SELECT * FROM ox_user_role where role_id =" . $role[0]['id'];
        $roleResult = $this->executeQueryTest($select);
        $select = "SELECT * FROM ox_account_user where account_id = (SELECT id from ox_account where uuid ='" . $content['data']['uuid'] . "')";
        $accountResult = $this->executeQueryTest($select);
        $select = "SELECT ox_user.*,ox_person.firstname,ox_person.lastname,ox_person.address_id,ox_employee.id as employeeId FROM ox_user inner join ox_person on ox_person.id = ox_user.person_id left outer join ox_employee on ox_employee.person_id = ox_person.id where ox_user.username ='" . $contact['username'] . "'";
        $usrResult = $this->executeQueryTest($select);
        $select = "SELECT ox_account.organization_id,ox_account.uuid,ox_account.name from ox_account where name = '$accountName'";
        $account = $this->executeQueryTest($select);
        $query = "SELECT * from ox_app_registry where account_id = (SELECT id from ox_account where uuid = '" . $content['data']['uuid'] . "')";
        $appResult = $this->executeQueryTest($query);
        $this->assertEquals(count($role), 3);
        $this->assertEquals(count($roleResult), 1);
        $this->assertEquals(count($accountResult), 1);
        $this->assertEquals($usrResult[0]['firstname'], $contact['firstname']);
        $this->assertEquals($usrResult[0]['lastname'], $contact['lastname']);
        $this->assertEquals($usrResult[0]['employeeId'], null);
        $this->assertEquals($rolePrivilegeResult[0][0]['count(id)'], 36);
        $this->assertEquals($rolePrivilegeResult[1][0]['count(id)'], 10);
        $this->assertEquals($rolePrivilegeResult[2][0]['count(id)'], 8);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $accountName);
        $this->assertEquals(isset($usrResult[0]['address_id']), true);
        $this->assertEquals($account[0]['organization_id'], null);
        $this->assertEquals($account[0]['name'], $accountName);
        $this->assertEquals($appResult[0]['app_id'], 1);
    }

    public function testCreateWithoutAccountaddress()
    {
        $this->initAuthToken($this->adminUser);
        $config = $this->getApplicationConfig();
        $tempFolder = $config['UPLOAD_FOLDER'] . "account/" . $this->testAccountId . "/";
        FileUtils::createDirectory($tempFolder);
        copy(__DIR__ . "/../files/logo.png", $tempFolder . "logo.png");
        $contact = array('username' => 'goku', 'firstname' => 'Bharat', 'lastname' => 'Gogineni', 'email' => 'bharatg@myvamla.com', 'phone' => '1234567890');
        $preferences = array('currency' => 'INR', 'timezone' => 'Asia/Calcutta', 'dateformat' => 'dd/mm/yyy');
        $data = array('name' => 'ORGANIZATION', 'contact' => json_encode($contact), 'preferences' => json_encode($preferences));
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(406);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('account');
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Validation error(s).');
        $errors = ['address1' => 'required',
                    'city' => 'required',
                    'state' => 'required',
                    'zip' => 'required'];
        $this->assertEquals($errors, $content['data']['errors']);
    }

    public function testCreateWithExistingContactpersonEmail()
    {
        $this->initAuthToken($this->adminUser);
        $config = $this->getApplicationConfig();
        $tempFolder = $config['UPLOAD_FOLDER'] . "account/" . $this->testAccountId . "/";
        FileUtils::createDirectory($tempFolder);
        copy(__DIR__ . "/../files/logo.png", $tempFolder . "logo.png");
        $contact = array('username' => 'goku', 'firstname' => 'Admin', 'lastname' => 'Test', 'email' => 'admin1@eoxvantage.in', 'phone' => '1234567890');
        $preferences = array('currency' => 'INR', 'timezone' => 'Asia/Calcutta', 'dateformat' => 'dd/mm/yyy');
        $data = array('name' => 'ORGANIZATION', 'address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456', 'contact' => json_encode($contact), 'preferences' => json_encode($preferences));
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(500);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('account');
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Username or Email Exists in other Account');
    }

    public function testCreateWithExistingContactpersonUsername()
    {
        $this->initAuthToken($this->adminUser);
        $config = $this->getApplicationConfig();
        $tempFolder = $config['UPLOAD_FOLDER'] . "account/" . $this->testAccountId . "/";
        FileUtils::createDirectory($tempFolder);
        copy(__DIR__ . "/../files/logo.png", $tempFolder . "logo.png");
        $contact = array('username' => 'admintest', 'firstname' => 'Bharat', 'lastname' => 'Gogineni', 'email' => 'goku@myvamla.com', 'phone' => '1234567890');
        $preferences = array('currency' => 'INR', 'timezone' => 'Asia/Calcutta', 'dateformat' => 'dd/mm/yyy');
        $data = array('name' => 'ORGANIZATION', 'address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456', 'contact' => json_encode($contact), 'preferences' => json_encode($preferences));
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(500);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('account');
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Username or Email Exists in other Account');
    }

    public function testCreateWithExistingActiveAccount()
    {
        $this->initAuthToken($this->adminUser);
        $config = $this->getApplicationConfig();
        $tempFolder = $config['UPLOAD_FOLDER'] . "account/" . $this->testAccountId . "/";
        FileUtils::createDirectory($tempFolder);
        copy(__DIR__ . "/../files/logo.png", $tempFolder . "logo.png");
        $contact = array('username' => 'goku', 'firstname' => 'Bharat', 'lastname' => 'Gogineni', 'email' => 'bharatg@myvamla.com', 'phone' => '1234567890');
        $preferences = array('currency' => 'INR', 'timezone' => 'Asia/Calcutta', 'dateformat' => 'dd/mm/yyy');
        $data = array('name' => 'Sample Organization', 'address1' => 'HSR','city' => 'Bangalore','state' => 'KARNATAKA','zip' => '560080','contact' => json_encode($contact), 'preferences' => json_encode($preferences));
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(412);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('account');
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Account already exists');
    }

    public function testCreateWithExistingInactiveAccountWithoutReactivateFlag()
    {
        $this->initAuthToken($this->adminUser);
        $config = $this->getApplicationConfig();
        $tempFolder = $config['UPLOAD_FOLDER'] . "account/" . $this->testAccountId . "/";
        FileUtils::createDirectory($tempFolder);
        copy(__DIR__ . "/../files/logo.png", $tempFolder . "logo.png");
        $contact = array('username' => 'goku', 'firstname' => 'Bharat', 'lastname' => 'Gogineni', 'email' => 'bharatg@myvamla.com', 'phone' => '1234567890');
        $preferences = array('currency' => 'INR', 'timezone' => 'Asia/Calcutta', 'dateformat' => 'dd/mm/yyy');
        $data = array('name' => 'Test Organization', 'address1' => 'Ariyalur', 'city' => 'Chennai','state' => 'Tamil Nadu', 'zip' =>'560079','contact' => json_encode($contact), 'preferences' => json_encode($preferences));
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(412);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('account');
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Account already exists would you like to reactivate?');
    }

    public function testCreateWithExistingInactiveAccountWithReactivateFlag()
    {
        $this->initAuthToken($this->adminUser);
        $config = $this->getApplicationConfig();
        $tempFolder = $config['UPLOAD_FOLDER'] . "account/" . $this->testAccountId . "/";
        FileUtils::createDirectory($tempFolder);
        copy(__DIR__ . "/../files/logo.png", $tempFolder . "logo.png");
        $contact = array('username' => 'goku', 'firstname' => 'Bharat', 'lastname' => 'Gogineni', 'email' => 'bharatg@myvamla.com', 'phone' => '1234567890');
        $preferences = array('currency' => 'INR', 'timezone' => 'Asia/Calcutta', 'dateformat' => 'dd/mm/yyy');
        $data = array('name' => 'Test Organization', 'address1' => 'Ariyalur', 'city' => 'Chennai','state' => 'Tamil Nadu', 'zip' =>'560079', 'contact' => json_encode($contact), 'preferences' => json_encode($preferences), 'reactivate' => 1);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('account');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['status'], 'Active');
    }

    public function testCreateWithoutContactPerson()
    {
        $this->initAuthToken($this->adminUser);
        $config = $this->getApplicationConfig();
        $tempFolder = $config['UPLOAD_FOLDER'] . "account/" . $this->testAccountId . "/";
        FileUtils::createDirectory($tempFolder);
        copy(__DIR__ . "/../files/logo.png", $tempFolder . "logo.png");
        $preferences = array('currency' => 'INR', 'timezone' => 'Asia/Calcutta', 'dateformat' => 'dd/mm/yyy');
        $data = array('name' => 'Test Organization', 'address1' => 'Ariyalur', 'city' => 'Chennai','state' => 'Tamil Nadu', 'zip' =>'560079', 'preferences' => json_encode($preferences), 'reactivate' => 1);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account', 'POST', $data);
        $this->assertResponseStatusCode(412);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('account');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Contact Person details are required');
    }

    public function testCreateWithOutNameFailure()
    {
        $this->initAuthToken($this->adminUser);
        $contact = array('username' => 'goku', 'firstname' => 'Bharat', 'lastname' => 'Gogineni', 'email' => 'bharat@myvamla.com', 'phone' => '1234567890');
        $preferences = array('currency' => 'INR', 'timezone' => 'Asia/Calcutta', 'dateformat' => 'dd/mm/yyy');
        $data = ['address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456', 'logo' => 'logo.png', 'status' => 'Active', 'contact' => json_encode($contact), 'preferences' => json_encode($preferences)];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account', 'POST', null);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(406);
        $this->setDefaultAsserts();
        $this->assertEquals('error', $content['status']);
        $this->assertEquals('Validation error(s).', $content['message']);
        $this->assertEquals('Account name required', $content['data']['errors']);
    }

    public function testCreateAccess()
    {
        $this->initAuthToken($this->employeeUser);
        $data = ['name' => 'Cleveland Cavaliers', 'logo' => 'logo.png', 'status' => 'Active'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account', 'POST', null);
        $this->assertResponseStatusCode(401);
        $this->assertModuleName('Account');
        $this->assertControllerName(AccountController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AccountController');
        $this->assertMatchedRouteName('account');
        $this->assertResponseHeaderContains('content-type', 'application/json');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You have no Access to this API');
    }

    public function testUpdate()
    {
        $data = ['name' => 'Cleveland Cavaliers', 'address1' => '23811 Chagrin Blvd, Ste 244', 'city' => 'Beachwood', 'state' => 'OH', 'country' => 'US', 'zip' => '44122', 'logo' => 'logo.png', 'status' => 'InActive'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('new_accountName' => 'Cleveland Cavaliers', 'old_accountName' => 'Cleveland Black', 'status' => 'InActive')), 'ACCOUNT_UPDATED')->once()->andReturn();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('accountName' => 'Cleveland Black', 'status' => 'InActive')), 'ACCOUNT_DELETED')->once()->andReturn();
        }
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a', 'POST', null);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $select = "SELECT ox_address.*
                    from ox_address join ox_organization on ox_address.id = ox_organization.address_id 
                    inner join ox_account o on o.organization_id = ox_organization.id where o.name = 'Cleveland Cavaliers'";
        $account = $this->executeQueryTest($select);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals($content['data']['status'], $data['status']);
        $this->assertEquals($account[0]['address1'], '23811 Chagrin Blvd, Ste 244');
    }

    public function testUpdateWithParent()
    {
        $data = ['name' => 'Cleveland Cavaliers', 'address1' => '23811 Chagrin Blvd, Ste 244', 'city' => 'Beachwood', 'state' => 'OH', 'country' => 'US', 'zip' => '44122', 'logo' => 'logo.png', 'status' => 'Active', 'parentId' => '915d207e-ac75-11ea-bb37-0242ac130002'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('new_accountName' => 'Cleveland Cavaliers', 'old_accountName' => 'Cleveland Black', 'status' => 'Active')), 'ACCOUNT_UPDATED')->once()->andReturn();
        }
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a', 'POST', null);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $select = "SELECT ox_address.*, parent.uuid as parentId, o.organization_id
                    from ox_address 
                    join ox_organization org on ox_address.id = org.address_id 
                    inner join ox_account o on o.organization_id = org.id 
                    inner join ox_organization parent on org.parent_id = parent.id
                    where o.name = 'Cleveland Cavaliers'";
        $account = $this->executeQueryTest($select);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals($content['data']['status'], $data['status']);
        $this->assertEquals($account[0]['address1'], $data['address1']);
        $this->assertEquals($account[0]['parentId'], $data['parentId']);
        $query = "SELECT oh.* from ox_org_heirarchy oh
                    inner join ox_organization org on org.id = oh.main_org_id
                    where oh.child_id = ".$account[0]['organization_id']." AND org.uuid = '".$data['parentId']."'";
        $orgHeirarchy = $this->executeQueryTest($query);
        $this->assertEquals(1, count($orgHeirarchy));
    }

    public function testUpdateWithAddress()
    {
        $data = ['name' => 'Cleveland Cavaliers', 'logo' => 'logo.png', 'status' => 'InActive', 'address1' => 'Banshankari', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '23456'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('new_accountName' => 'Cleveland Cavaliers', 'old_accountName' => 'Cleveland Black', 'status' => 'InActive')), 'ACCOUNT_UPDATED')->once()->andReturn();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('accountName' => 'Cleveland Black', 'status' => 'InActive')), 'ACCOUNT_DELETED')->once()->andReturn();
        }
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a', 'POST', null);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $select = "SELECT ox_address.*
                    from ox_address join ox_organization on ox_address.id = ox_organization.address_id 
                    inner join ox_account o on o.organization_id = ox_organization.id where o.name = 'Cleveland Cavaliers'";
        $account = $this->executeQueryTest($select);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals($content['data']['status'], $data['status']);
        $this->assertEquals($account[0]['address1'], $data['address1']);
    }

    public function testUpdateRestricted()
    {
        $data = ['name' => 'Cleveland Cavaliers', 'logo' => 'logo.png', 'status' => 'Active'];
        $this->initAuthToken($this->employeeUser);
        $this->setJsonContent(json_encode($data));
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
        }
        $this->dispatch('/account/1', 'PUT', null);
        $this->assertResponseStatusCode(401);
        $this->assertModuleName('Account');
        $this->assertControllerName(AccountController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AccountController');
        $this->assertMatchedRouteName('account');
        $this->assertResponseHeaderContains('content-type', 'application/json');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You have no Access to this API');
    }

    public function testDelete()
    {
        $this->initAuthToken($this->adminUser);
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('accountName' => 'Cleveland Black', 'status' => 'Inactive')), 'ACCOUNT_DELETED')->once()->andReturn();
        }
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a', 'DELETE');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
    }

    public function testDeleteNotFound()
    {
        $this->initAuthToken($this->adminUser);
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
        }
        $this->dispatch('/account/53012471-2863-4', 'DELETE');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testSaveUser()
    {
        $this->initAuthToken($this->adminUser);
        $uuid = "53012471-2863-4949-afb1-e69b0891c98a";
        $data = ['userIdList' => array(['uuid' => '4fd9f04d-758f-11e9-b2d5-68ecc57cde45'])];
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('accountName' => 'Cleveland Black', 'username' => 'managertest')), 'USERTOACCOUNT_DELETED')->once()->andReturn();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('accountName' => 'Cleveland Black', 'username' => 'orgadmin')), 'USERTOACCOUNT_DELETED')->once()->andReturn();
        }
        $this->dispatch('/account/' . $uuid . '/save', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('addUsersToAccount');
        $select = "SELECT au.* from ox_account_user au 
                    inner join ox_account a on a.id = au.account_id 
                    where a.uuid = '$uuid'";
        $accountResult = $this->executeQueryTest($select);
        $select = "SELECT * from ox_user where account_id is NULL";
        $userData = $this->executeQueryTest($select);
        $select = "SELECT ur.* from ox_user_role ur
                    inner join ox_account_user au on au.id = ur.account_user_id
                    inner join ox_account a on a.id = au.account_id 
                    where a.uuid = '$uuid'";
        $userRoleData = $this->executeQueryTest($select);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($accountResult), 2);
        $this->assertEquals($accountResult[0]['user_id'], 1);
        $this->assertEquals($accountResult[0]['account_id'], 1);
        $this->assertEquals($accountResult[0]['default'], 1);
        $this->assertEquals($accountResult[1]['user_id'], 3);
        $this->assertEquals($accountResult[1]['account_id'], 1);
        $this->assertEquals($accountResult[1]['default'], 1);
        $this->assertEquals(3, count($userRoleData));
        $this->assertEquals($userRoleData[0]['role_id'], 4);
        $this->assertEquals($userRoleData[0]['account_user_id'], 1);
        $this->assertEquals($userRoleData[1]['role_id'], 5);
        $this->assertEquals($userRoleData[1]['account_user_id'], 1);
        $this->assertEquals($userRoleData[2]['role_id'], 6);
        $this->assertEquals($userRoleData[2]['account_user_id'], 3);
        $this->assertEquals(2, count($userData));
        $this->assertEquals($userData[0]['id'], 2);
        $this->assertEquals($userData[1]['id'], 4);
    }

    public function testSaveUserWithUserAlreadyExistsInOtherAccount()
    {
        $this->initAuthToken($this->adminUser);
        $uuid = "53012471-2863-4949-afb1-e69b0891c98a";
        $data = ['userIdList' => array(['uuid' => '4fd9f04d-758f-11e9-b2d5-68ecc57cde45'], ['uuid' => 'fbde2453-17eb-4d7f-909a-0fccc6d53e7a'])];
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('accountName' => 'Cleveland Black', 'username' => 'managertest')), 'USERTOACCOUNT_DELETED')->once()->andReturn();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('accountName' => 'Cleveland Black', 'username' => 'orgadmin')), 'USERTOACCOUNT_DELETED')->once()->andReturn();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('accountName' => 'Cleveland Black', 'status' => 'Active', 'username' => 'org2admin')), 'USERTOACCOUNT_ADDED')->once()->andReturn();
        }
        $this->dispatch('/account/' . $uuid . '/save', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('addUsersToAccount');
        $select = "SELECT * FROM ox_account_user where account_id = (SELECT id from ox_account where uuid ='" . $uuid . "')";
        $accountResult = $this->executeQueryTest($select);
        $select = "SELECT count(id) from ox_user where account_id is NULL";
        $accountCount = $this->executeQueryTest($select);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($accountResult), 3);
        $this->assertEquals($accountResult[0]['user_id'], 1);
        $this->assertEquals($accountResult[0]['account_id'], 1);
        $this->assertEquals($accountResult[0]['default'], 1);
        $this->assertEquals($accountResult[1]['user_id'], 3);
        $this->assertEquals($accountResult[1]['account_id'], 1);
        $this->assertEquals($accountResult[1]['default'], 1);
        $this->assertEquals($accountResult[2]['user_id'], 5);
        $this->assertEquals($accountResult[2]['account_id'], 1);
        $this->assertEquals($accountResult[2]['default'], null);
        $this->assertEquals($accountCount[0]['count(id)'], 2);
    }

    public function testSaveUserWithUserToOtherAccount()
    {
        $this->initAuthToken($this->adminUser);
        $uuid = "53012471-2863-4949-afb1-e69b0891c98a";
        $data = ['userIdList' => array(['uuid' => '4fd99e8e-758f-11e9-b2d5-68ecc57cde45'], ['uuid' => '4fd9ce37-758f-11e9-b2d5-68ecc57cde45'], ['uuid' => '768d1fb9-de9c-46c3-8d5c-23e0e484ce2e'], ['uuid' => 'fbde2453-17eb-4d7f-909a-0fccc6d53e7a'])];
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('accountName' => 'Cleveland Black', 'username' => 'employeetest')), 'USERTOACCOUNT_DELETED')->once()->andReturn();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('accountName' => 'Cleveland Black', 'status' => 'Active', 'username' => 'org2admin')), 'USERTOACCOUNT_ADDED')->once()->andReturn();
        }
        $this->dispatch('/account/' . $uuid . '/save', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('addUserToAccount');
        $select = "SELECT * FROM ox_account_user where account_id = (SELECT id from ox_account where uuid ='" . $uuid . "')";
        $accountResult = $this->executeQueryTest($select);
        $select = "SELECT count(id) from ox_user where account_id is NULL";
        $accountCount = $this->executeQueryTest($select);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($accountResult), 4);
        $this->assertEquals($accountResult[0]['user_id'], 1);
        $this->assertEquals($accountResult[0]['account_id'], 1);
        $this->assertEquals($accountResult[0]['default'], 1);
        $this->assertEquals($accountResult[1]['user_id'], 2);
        $this->assertEquals($accountResult[1]['account_id'], 1);
        $this->assertEquals($accountResult[1]['default'], 1);
        $this->assertEquals($accountResult[2]['user_id'], 4);
        $this->assertEquals($accountResult[2]['account_id'], 1);
        $this->assertEquals($accountResult[2]['default'], 1);
        $this->assertEquals($accountResult[3]['user_id'], 5);
        $this->assertEquals($accountResult[3]['account_id'], 1);
        $this->assertEquals($accountResult[3]['default'], null);
        $this->assertEquals($accountCount[0]['count(id)'], 1);
    }

    public function testToDeleteContactUserFromAccount()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['userIdList' => array(['uuid' => '4fd99e8e-758f-11e9-b2d5-68ecc57cde45'])];
        $uuid = "b6499a34-c100-4e41-bece-5822adca3844";
        $update = "update ox_account set contactid = 6 where id = 3";
        $accountResult = $this->executeUpdate($update);
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('accountName' => 'Sample Organization', 'username' => 'abc134')), 'USERTOACCOUNT_DELETED')->once()->andReturn();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('accountName' => 'Sample Organization', 'status' => 'Active', 'username' => 'admintest')), 'USERTOACCOUNT_ADDED')->once()->andReturn();
        }
        $this->dispatch('/account/' . $uuid . '/save', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('addUserToAccount');
        $select = "SELECT * FROM ox_account_user where account_id = (SELECT id from ox_account where uuid ='" . $uuid . "')";
        $accountResult = $this->executeQueryTest($select);
        $select = "SELECT count(id) from ox_user where account_id is NULL";
        $accountCount = $this->executeQueryTest($select);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($accountResult), 2);
        $this->assertEquals($accountResult[0]['user_id'], 6);
        $this->assertEquals($accountResult[0]['account_id'], 3);
        $this->assertEquals($accountResult[0]['default'], 1);
        $this->assertEquals($accountResult[1]['user_id'], 1);
        $this->assertEquals($accountResult[1]['account_id'], 3);
        $this->assertEquals($accountResult[1]['default'], null);
        $this->assertEquals($accountCount[0]['count(id)'], 1);
    }

    public function testAddUserToAccountWithDifferentUser()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['userIdList' => array(['uuid' => '4fd99e8e-758f-11e9-b2d5'], ['uuid' => '4fd99e8e68ecc57cde4'])];
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/save', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts('addUserToAccount');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testGetUsersOfAccount()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/users', 'GET');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(4, count($content['data']));
        $this->assertEquals($content['data'][0]['uuid'], "768d1fb9-de9c-46c3-8d5c-23e0e484ce2e");
        $this->assertEquals($content['data'][0]['name'], 'Cleveland Test');
        $this->assertEquals($content['data'][1]['uuid'], "4fd9f04d-758f-11e9-b2d5-68ecc57cde45");
        $this->assertEquals($content['data'][1]['name'], 'Employee Test');
        $this->assertEquals($content['data'][2]['uuid'], "4fd9ce37-758f-11e9-b2d5-68ecc57cde45");
        $this->assertEquals($content['data'][2]['name'], 'Manager Test');
        $this->assertEquals($content['data'][3]['uuid'], "4fd99e8e-758f-11e9-b2d5-68ecc57cde45");
        $this->assertEquals($content['data'][3]['name'], 'Admin Test');
        $this->assertEquals($content['total'], 4);
    }

    public function testGetUsersOfAccountWithFilter()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/users?filter=[{"skip":1,"take":2}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(2, count($content['data']));
        $this->assertEquals($content['data'][0]['uuid'], "4fd9f04d-758f-11e9-b2d5-68ecc57cde45");
        $this->assertEquals($content['data'][0]['name'], 'Employee Test');
        $this->assertEquals($content['data'][1]['uuid'], "4fd9ce37-758f-11e9-b2d5-68ecc57cde45");
        $this->assertEquals($content['data'][1]['name'], 'Manager Test');
        $this->assertEquals($content['total'], 4);
    }

    public function testGetUsersOfAccountWithSortFilter()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/users?filter=[{"sort":[{"field":"name","dir":"asc"}],"skip":2,"take":2}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(2, count($content['data']));
        $this->assertEquals($content['data'][0]['uuid'], "4fd9f04d-758f-11e9-b2d5-68ecc57cde45");
        $this->assertEquals($content['data'][0]['name'], 'Employee Test');
        $this->assertEquals($content['data'][1]['uuid'], "4fd9ce37-758f-11e9-b2d5-68ecc57cde45");
        $this->assertEquals($content['data'][1]['name'], 'Manager Test');
        $this->assertEquals($content['total'], 4);
    }

    public function testGetUsersOfAccountWithFieldFilter()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/users?filter=[{"filter":{"filters":[{"field":"name","operator":"endswith","value":"n Test"}]},"sort":[{"field":"id","dir":"asc"}],"skip":0,"take":2}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(1, count($content['data']));
        $this->assertEquals($content['data'][0]['uuid'], "4fd99e8e-758f-11e9-b2d5-68ecc57cde45");
        $this->assertEquals($content['data'][0]['name'], 'Admin Test');
        $this->assertEquals($content['total'], 1);
    }

    public function testGetUsersOfAccountWithFieldCountryFilter()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/users?filter=[{"filter":{"filters":[{"field":"country","operator":"endswith","value":"ana"}]},"sort":[{"field":"id","dir":"asc"}],"skip":0,"take":1}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(1, count($content['data']));
        $this->assertEquals($content['data'][0]['uuid'], "4fd9ce37-758f-11e9-b2d5-68ecc57cde45");
        $this->assertEquals($content['data'][0]['name'], 'Manager Test');
        $this->assertEquals($content['total'], 2);
    }

    public function testGetAdminUsersAccountWithFilter()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/adminusers?filter=[{"filter":{"filters":[{"field":"name","operator":"endswith","value":"n Test"}]},"sort":[{"field":"name","dir":"asc"}],"skip":0,"take":2}]', 'GET');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(1, count($content['data']));
        $this->assertEquals($content['data'][0]['uuid'], '4fd99e8e-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][0]['name'], 'Admin Test');
        $this->assertEquals($content['total'], 1);
    }

    public function testGetAdminUsersAccount()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/adminusers?filter=[{"sort":[{"field":"name","dir":"asc"}],"skip":0,"take":20}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(1, count($content['data']));
        $this->assertEquals($content['data'][0]['uuid'], 'fbde2453-17eb-4d7f-909a-0fccc6d53e7a');
        $this->assertEquals($content['data'][0]['name'], 'Golden Test');
        $this->assertEquals($content['total'], 1);
    }

    public function testGetAdminUsersAccountByManager()
    {
        $this->initAuthToken($this->managerUser);
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/adminusers?filter=[{"sort":[{"field":"id","dir":"asc"}],"skip":0,"take":20}]', 'GET');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(401);
        $this->assertModuleName('Account');
        $this->assertControllerName(AccountController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AccountController');
        $this->assertMatchedRouteName('getListofAdminUsers');
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals("You do not have permissions", $content['message']);
    }

    public function testGetAdminUsersAccountByEmployee()
    {
        $this->initAuthToken($this->employeeUser);
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/adminusers?filter=[{"sort":[{"field":"id","dir":"asc"}],"skip":0,"take":20}]', 'GET');
        $this->assertResponseStatusCode(401);
        $this->assertModuleName('Account');
        $this->assertControllerName(AccountController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AccountController');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You have no Access to this API');
    }

    public function testGetAccountTeams()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/teams', 'GET');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['uuid'], '153f3e9e-eb07-4ca4-be78-34f715bd124');
        $this->assertEquals($content['data'][0]['name'], 'Test Team 5');
        $this->assertEquals($content['data'][0]['description'], 'Team Description');
        $this->assertEquals($content['data'][0]['managerId'], '4fd99e8e-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][1]['uuid'], '2db1c5a3-8a82-4d5b-b60a-c648cf1e27de');
        $this->assertEquals($content['data'][1]['name'], 'Test Team');
        $this->assertEquals($content['data'][1]['description'], 'Description Test Data');
        $this->assertEquals($content['data'][1]['managerId'], '4fd99e8e-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][1]['parent_id'], null);
        $this->assertEquals($content['data'][1]['accountId'], '53012471-2863-4949-afb1-e69b0891c98a');
        $this->assertEquals($content['total'], 2);
    }

    public function testGetAccountTeamsWithFilter()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/teams?filter=[{"filter":{"filters":[{"field":"name","operator":"endswith","value":"eam"},{"field":"description","operator":"startswith","value":"dEs"}]},"skip":0,"take":2}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['uuid'], '2db1c5a3-8a82-4d5b-b60a-c648cf1e27de');
        $this->assertEquals($content['data'][0]['name'], 'Test Team');
        $this->assertEquals($content['data'][0]['description'], 'Description Test Data');
        $this->assertEquals($content['data'][0]['managerId'], '4fd99e8e-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][0]['parent_id'], null);
        $this->assertEquals($content['data'][0]['accountId'], '53012471-2863-4949-afb1-e69b0891c98a');
        $this->assertEquals($content['total'], 1);
    }

    public function testGetAccountTeamsWithSortFilter()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/teams?filter=[{"sort":[{"field":"uuid","dir":"asc"}],"skip":0,"take":2}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['uuid'], '153f3e9e-eb07-4ca4-be78-34f715bd124');
        $this->assertEquals($content['data'][0]['name'], 'Test Team 5');
        $this->assertEquals($content['data'][1]['uuid'], '2db1c5a3-8a82-4d5b-b60a-c648cf1e27de');
        $this->assertEquals($content['data'][1]['name'], 'Test Team');
        $this->assertEquals($content['total'], 2);
    }

    public function testGetAccountTeamsWithPagsize()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/teams?filter=[{"skip":0,"take":1}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['uuid'], '153f3e9e-eb07-4ca4-be78-34f715bd124');
        $this->assertEquals($content['data'][0]['name'], 'Test Team 5');
        $this->assertEquals($content['total'], 2);
    }

    public function testGetAccountTeamsWithPagination()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/teams?filter=[{"skip":1,"take":1}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['uuid'], '2db1c5a3-8a82-4d5b-b60a-c648cf1e27de');
        $this->assertEquals($content['data'][0]['name'], 'Test Team');
        $this->assertEquals($content['total'], 2);
    }

    public function testGetAccountTeamsWithInvalidAccountId()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-/teams?filter=[{"skip":1,"take":1}]', 'GET');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Invalid Account');
    }

    public function testGetAccountTeamsWithInvalidFilter()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/teams?filter=[{"filter":{"filters":[{"field":"name","operator":"endswith","value":"nbn"},{"field":"description","operator":"startswith","value":"ngjdg"}]},"skip":0,"take":2}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'], array());
        $this->assertEquals($content['total'], 0);
    }
    //Project

    public function testGetAccountProjects()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/projects', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['uuid'], '3dbacd80-ff27-4169-a683-4a45d2a8fb8f');
        $this->assertEquals($content['data'][0]['name'], 'New Project');
        $this->assertEquals($content['data'][0]['managerId'], '4fd9ce37-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][0]['accountId'], '53012471-2863-4949-afb1-e69b0891c98a');
        $this->assertEquals($content['data'][1]['uuid'], 'ced672bb-fe33-4f0a-b153-f1d182a02603');
        $this->assertEquals($content['data'][1]['name'], 'Test Project 2');
        $this->assertEquals($content['data'][2]['uuid'], '886d7eff-6bae-4892-baf8-6fefc56cbf0b');
        $this->assertEquals($content['data'][2]['name'], 'Test Project 1');
        $this->assertEquals($content['data'][2]['managerId'], '4fd99e8e-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['total'], 3);
    }

    public function testGetAccountProjectWithFilter()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/projects?filter=[{"filter":{"filters":[{"field":"name","operator":"endswith","value":"ct"},{"field":"description","operator":"startswith","value":"dEs"}]},"skip":0,"take":2}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['uuid'], '3dbacd80-ff27-4169-a683-4a45d2a8fb8f');
        $this->assertEquals($content['data'][0]['name'], 'New Project');
        $this->assertEquals($content['data'][0]['description'], 'Description Test Data');
        $this->assertEquals($content['data'][0]['managerId'], '4fd9ce37-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][0]['accountId'], '53012471-2863-4949-afb1-e69b0891c98a');
        $this->assertEquals($content['total'], 1);
    }

    public function testGetAccountProjectsWithSortFilter()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/projects?filter=[{"sort":[{"field":"uuid","dir":"asc"}],"skip":0,"take":2}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['uuid'], '3dbacd80-ff27-4169-a683-4a45d2a8fb8f');
        $this->assertEquals($content['data'][0]['name'], 'New Project');
        $this->assertEquals($content['data'][1]['uuid'], '886d7eff-6bae-4892-baf8-6fefc56cbf0b');
        $this->assertEquals($content['data'][1]['name'], 'Test Project 1');
        $this->assertEquals($content['total'], 3);
    }

    public function testGetAccountProjectsWithPagsize()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/projects?filter=[{"skip":0,"take":1}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['uuid'], '3dbacd80-ff27-4169-a683-4a45d2a8fb8f');
        $this->assertEquals($content['data'][0]['name'], 'New Project');
        $this->assertEquals($content['total'], 3);
    }

    public function testGetAccountProjectsWithPagination()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/projects?filter=[{"skip":1,"take":1}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['uuid'], 'ced672bb-fe33-4f0a-b153-f1d182a02603');
        $this->assertEquals($content['data'][0]['name'], 'Test Project 2');
        $this->assertEquals($content['total'], 3);
    }

    public function testGetAccountProjectsWithInvalidAccountId()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-/projects?filter=[{"skip":1,"take":1}]', 'GET');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Invalid Account');
    }

    public function testGetAccountProjectsWithInvalidFilter()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/projects?filter=[{"filter":{"filters":[{"field":"name","operator":"endswith","value":"nbn"},{"field":"description","operator":"startswith","value":"ngjdg"}]},"skip":0,"take":2}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'], array());
        $this->assertEquals($content['total'], 0);
    }
    // Announcements

    public function testGetAccountAnnouncements()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/announcements', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['uuid'], '7ba66332-5f48-4c8a-b0ee-6ebfffda06d7');
        $this->assertEquals($content['data'][0]['name'], 'Announcement 9');
        $this->assertEquals($content['data'][0]['accountId'], '53012471-2863-4949-afb1-e69b0891c98a');
        $this->assertEquals($content['data'][1]['uuid'], 'f825f46c-ab93-433d-84f8-cfbc118c38a1');
        $this->assertEquals($content['data'][1]['name'], 'Announcement 8');
        $this->assertEquals(7, $content['total']);
    }

    public function testGetAccountAnnouncementsWithFilter()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/announcements?filter=[{"filter":{"filters":[{"field":"name","operator":"endswith","value":"2"},{"field":"description","operator":"startswith","value":"announ"}]},"skip":0,"take":2}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['uuid'], 'e66157ee-47de-4ed5-a78e-8a9195033f7a');
        $this->assertEquals($content['data'][0]['name'], 'Announcement 2');
        $this->assertEquals($content['data'][0]['description'], 'Announcemnt Test');
        $this->assertEquals($content['data'][0]['accountId'], '53012471-2863-4949-afb1-e69b0891c98a');
        $this->assertEquals($content['total'], 1);
    }

    public function testGetAccountAnnouncementsWithSortFilter()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/announcements?filter=[{"sort":[{"field":"uuid","dir":"asc"}],"skip":0,"take":2}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['uuid'], '36c8980d-48c2-45fc-b5bc-4407c80f6d71');
        $this->assertEquals($content['data'][0]['name'], 'Announcement 7');
        $this->assertEquals($content['data'][1]['uuid'], '70d53bec-6e2d-4128-933b-96caa3d88e2a');
        $this->assertEquals($content['data'][1]['name'], 'Announcement 6');
        $this->assertEquals($content['total'], 7);
    }

    public function testGetAccountAnnouncementsWithPagsize()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/announcements?filter=[{"skip":0,"take":1}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['uuid'], '7ba66332-5f48-4c8a-b0ee-6ebfffda06d7');
        $this->assertEquals($content['data'][0]['name'], 'Announcement 9');
        $this->assertEquals($content['total'], 7);
    }

    public function testGetAccountAnnouncementsWithPagination()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/announcements?filter=[{"skip":1,"take":1}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['uuid'], 'f825f46c-ab93-433d-84f8-cfbc118c38a1');
        $this->assertEquals($content['data'][0]['name'], 'Announcement 8');
        $this->assertEquals($content['total'], 7);
    }

    public function testGetAccountAnnouncementsWithInvalidAccountId()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-/announcements?filter=[{"skip":1,"take":1}]', 'GET');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Invalid Account');
    }

    public function testGetAccountAnnouncementsWithInvalidFilter()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/announcements?filter=[{"filter":{"filters":[{"field":"name","operator":"endswith","value":"nbn"},{"field":"description","operator":"startswith","value":"ngjdg"}]},"skip":0,"take":2}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'], array());
        $this->assertEquals($content['total'], 0);
    }
    // Roles

    public function testGetAccountRoles()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/roles', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['name'], 'EMPLOYEE');
        $this->assertEquals($content['data'][0]['accountId'], '53012471-2863-4949-afb1-e69b0891c98a');
        $this->assertEquals($content['data'][1]['name'], 'MANAGER');
        $this->assertEquals($content['data'][2]['name'], 'ADMIN');
        $this->assertEquals($content['total'], 3);
    }

    public function testGetAccountRolesWithFilter()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/roles?filter=[{"filter":{"filters":[{"field":"name","operator":"endswith","value":"ger"},{"field":"description","operator":"startswith","value":"Must"}]},"skip":0,"take":2}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['name'], 'MANAGER');
        $this->assertEquals($content['total'], 1);
    }

    public function testGetAccountRolesWithSortFilter()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/roles?filter=[{"sort":[{"field":"uuid","dir":"asc"}],"skip":0,"take":2}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['name'], 'ADMIN');
        $this->assertEquals($content['data'][1]['name'], 'MANAGER');
        $this->assertEquals($content['total'], 3);
    }

    public function testGetAccountRolesWithPagsize()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/roles?filter=[{"skip":0,"take":3}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['name'], 'EMPLOYEE');
        $this->assertEquals($content['total'], 3);
    }

    public function testGetAccountRolesWithPagination()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/roles?filter=[{"skip":1,"take":1}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['name'], 'MANAGER');
        $this->assertEquals($content['total'], 3);
    }

    public function testGetAccountRolesWithInvalidAccountId()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-/roles?filter=[{"skip":1,"take":1}]', 'GET');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Invalid Account');
    }

    public function testGetAccountRolesWithInvalidFilter()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/roles?filter=[{"filter":{"filters":[{"field":"name","operator":"endswith","value":"nbn"},{"field":"description","operator":"startswith","value":"ngjdg"}]},"skip":0,"take":2}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'], array());
        $this->assertEquals($content['total'], 0);
    }

    public function testcheckAccountUserWithCorrectEmail() {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/usercheck/admin1@eoxvantage.in', 'GET');
        $this->assertResponseStatusCode(412);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'User/Email already exists');
    }

    public function testcheckAccountUserWithCorrectUsername() {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/usercheck/admintest', 'GET');
        $this->assertResponseStatusCode(412);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'User/Email already exists');
    }

    public function testcheckAccountUserWithIncorrectEmail() {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/usercheck/lmn', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['message'], 'No user found');
    }

    public function testcheckAccountUserWithIncorrectAccount() {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98b/usercheck/admintest', 'GET');
        $this->assertResponseStatusCode(412);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Incorrect Account specified');
    }
}
