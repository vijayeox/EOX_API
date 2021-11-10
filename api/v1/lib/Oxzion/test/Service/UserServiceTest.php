<?php
namespace Oxzion\Service;

use Oxzion\Test\AbstractServiceTest;
use Oxzion\Service\EmailService;
use Oxzion\Service\AddressService;
use Zend\Db\Adapter\AdapterInterface;
use Oxzion\Service\TemplateService;
use Oxzion\Transaction\TransactionManager;
use Zend\Db\Adapter\Adapter;
use Oxzion\Messaging\MessageProducer;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Zend\Db\ResultSet\ResultSet;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Symfony\Component\Yaml\Yaml;

class UserServiceTest extends AbstractServiceTest
{
    public $dataset = null;

    public function setUp() : void
    {
        AuthContext::put(AuthConstants::ACCOUNT_ID, 1);
        AuthContext::put(AuthConstants::ACCOUNT_UUID, '53012471-2863-4949-afb1-e69b0891c98a');
        $this->loadConfig();
        parent::setUp();
        $this->adapter = $this->getDbAdapter();
        $this->adapter->getDriver()->getConnection()->setResource(static::$pdo);
    }
    
    /**
     *  Prepare dataset for user service
     */
    public function parseYaml()
    {
        $dataset = Yaml::parseFile(dirname(__FILE__)."/Dataset/UserService.yml");
        return $dataset;
    }

    /**
     * Initailize and Set the data source for the result set
     */
    private function runQuery($query)
    {
        $statement = $this->adapter->query($query);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        return $result;
    }

    /**
     *  Prepare dataset for user service to compare the actual contents of a database against the expected contents
     */
    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__)."/Dataset/UserService.yml");
        return $dataset;
    }

    private function getUserService()
    {
        return $this->getApplicationServiceLocator()->get(\Oxzion\Service\UserService::class);
    }

    public function testGetPrivileges()
    {
        $data = $this->getUserService()->getPrivileges(1, 1);
        $this->assertEquals(isset($data), true);
        $this->assertEquals(count($data) > 0, true);
    }

    /**
     *  Test Delete User if manager is assigned
     */
    public function testDeleteUser()
    {
        AuthContext::put(AuthConstants::ACCOUNT_UUID, '53012471-2863-4949-afb1-e69b0891c98a');
        $dataset = ["accountId" => '53012471-2863-4949-afb1-e69b0891c98a'];
        $createData = ['username' => 'Anna Lee', 'status' => 'Active', 'date_of_birth' => date('Y-m-d', strtotime("-50 year")), 'date_of_join' => date('Y-m-d'), 'icon' => 'test-oxzionlogo.png', 'managerid' => '471', 'firstname' => 'Anna', 'lastname' => 'Lee','designation' => 'Principal Consultant', 'location' => 'USA', 'email' => 'annalee@gmail.com', 'gender' => 'Female', 'address1' => 'HSR', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '560068', 'role' => array(['id' => '89a01b30-9cc9-416e-8027-1fd2083786c7'], ['id' => '5ecccd2d-4dc7-4e19-ae5f-adb3c8f48073']), 'managerId' => 'd9890624-8f42-4201-bbf9-675ec5dc8933'];
        $this->getUserService()->createUser($dataset, $createData);
        
        $sqlQuery = "SELECT ox_user.id, ox_user.uuid, ox_user.username, ox_user.person_id FROM ox_user JOIN ox_person usrp ON usrp.id = ox_user.person_id WHERE username = '" .$createData['username']."'";
        $result = $this->runQuery($sqlQuery);
        $this->assertEquals($createData['username'], $result[0]['username']);

        $empquery = "SELECT * from ox_employee
        inner join ox_user on ox_user.person_id = ox_employee.person_id
        where username = '" .$createData['username']."'";
        $empDetails = $this->runQuery($empquery);        
        $this->assertEquals(50, $empDetails[0]['manager_id']);

        $deleteData = ['accountId' => '53012471-2863-4949-afb1-e69b0891c98a', 'userId' => 'd9890624-8f42-4201-bbf9-675ec5dc8933'];
        $this->getUserService()->deleteUser($deleteData);
        $getUuid = "SELECT * FROM ox_user WHERE uuid = '" .$deleteData['userId']."'";
        $getUuidResult = $this->runQuery($getUuid);
        $this->assertEquals(1, count($getUuidResult));
        $this->assertEquals('Inactive', $getUuidResult[0]['status']);

        $empDeletequery = "SELECT * from ox_employee
        inner join ox_user on ox_user.person_id = ox_employee.person_id
        where username = '" .$createData['username']."'";
        $empDeleteDetails = $this->runQuery($empDeletequery);
        $this->assertEquals(NULL, $empDeleteDetails[0]['manager_id']);
    }

    /**
     *  Test Delete User if manager is not assigned
     */
    public function testDeleteUserWithoutManagerId()
    {
        AuthContext::put(AuthConstants::ACCOUNT_UUID, '53012471-2863-4949-afb1-e69b0891c98a');
        $dataset = ["accountId" => '53012471-2863-4949-afb1-e69b0891c98a'];
        $createData = ['username' => 'Anna Lee', 'status' => 'Active', 'date_of_birth' => date('Y-m-d', strtotime("-50 year")), 'date_of_join' => date('Y-m-d'), 'icon' => 'test-oxzionlogo.png', 'managerid' => '471', 'firstname' => 'Anna', 'lastname' => 'Lee','designation' => 'Principal Consultant', 'location' => 'USA', 'email' => 'annalee@gmail.com', 'gender' => 'Female', 'address1' => 'HSR', 'city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India', 'zip' => '560068', 'role' => array(['id' => '89a01b30-9cc9-416e-8027-1fd2083786c7'], ['id' => '5ecccd2d-4dc7-4e19-ae5f-adb3c8f48073'])];

        $this->getUserService()->createUser($dataset, $createData);
        $sqlQuery = "SELECT ox_user.id, ox_user.uuid, ox_user.username,ox_user.person_id FROM ox_user JOIN ox_person usrp ON usrp.id = ox_user.person_id WHERE username = '" .$createData['username']."'";
        $result = $this->runQuery($sqlQuery);
        $this->assertEquals($createData['username'], $result[0]['username']);

        $empquery = "SELECT * from ox_employee
        inner join ox_user on ox_user.person_id = ox_employee.person_id
        where username = '" .$createData['username']."'";
        $empDetails = $this->runQuery($empquery);
        $this->assertEquals(NULL, $empDetails[0]['manager_id']);

        $deleteData = ['accountId' => '53012471-2863-4949-afb1-e69b0891c98a', 'userId' => $result[0]['uuid']];
        $this->getUserService()->deleteUser($deleteData);
        $getUuid = "SELECT * FROM ox_user WHERE uuid = '" .$result[0]['uuid']."'";
        $getUuidResult = $this->runQuery($getUuid);
        $this->assertEquals(1, count($getUuidResult));
        $this->assertEquals('Inactive', $getUuidResult[0]['status']);
    }
}
