<?php
namespace Oxzion\Service;

use Oxzion\Test\AbstractServiceTest;
use Zend\Db\Adapter\Adapter;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Symfony\Component\Yaml\Yaml;
use Oxzion\ServiceException;
use Oxzion\ValidationException;
use Oxzion\EntityNotFoundException;
use Zend\Db\Adapter\Exception\InvalidQueryException;
use \Exception;
use Zend\Db\ResultSet\ResultSet;
use Oxzion\Utils\FileUtils;

class EmployeeServiceTest extends AbstractServiceTest
{
    public $dataset = null;

    public $adapter = null;

    protected function setUp(): void
    {
        $this->loadConfig();
        parent::setUp();
        $this->EmplyService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\EmployeeService::class);
        $this->adapter = $this->getDbAdapter();
        $this->adapter->getDriver()->getConnection()->setResource(static::$pdo);
    }

    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__)."/Dataset/File.yml");
        return $dataset;
    }

    private function runQuery($query)
    {
        $statement = $this->adapter->query($query);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        return $result;
    }

    public function testaddEmployeeRecord()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        AuthContext::put(AuthConstants::ACCOUNT_ID, '1');
        $data = ['firstname' => 'Individual',
        'lastname' => 'Admin',
        'username' => 'testuser',
        'email' => 'admintestindividual@eoxvantage.in',
        'date_of_birth' => '2020-11-02',
        'gender' => 'Male',
        'managerId' => '4fd99e8e-758f-11e9-b2d5-68ecc57cde45',
        'role' => array(['0' => array(['uuid' => 'f5359981-fca4-11ea-8921-485f997ffb6f'])]),
        'address1' => '23811 Chagrin Blvd, Ste 244',
        'city' => 'Beachwood',
        'state' => 'OH',
        'zip' => '44122',
        'country' => 'US',
        'account_id' => '1',
        'address2' => '',
        'person_id' => '152',
        'name' => 'test test',
        'date_created' => '2020-11-05 10:07:39',
        'password_reset_code' => 'c347a277-e724-42ea-9b2a-c80dc62f7d7e',
        'created_by' => ''
    ];
        $this->EmplyService->addEmployeeRecord($data);
        $person_id = $data['person_id'];
        $rows = $this->executeQueryTest("SELECT * FROM ox_employee WHERE person_id='${person_id}'");
        $this->assertEquals($data['manager_id'], $rows[0]['manager_id']);
        $this->assertEquals($data['person_id'], $rows[0]['person_id']);
        $this->assertEquals($rows[0]['designation'], "Staff");
        $this->assertEquals($rows[0]['date_of_join'], date('Y-m-d'));
    }

    public function testaddEmployeeWithoutDataRecord()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        AuthContext::put(AuthConstants::ACCOUNT_ID, '1');
        $data = ['firstname' => 'Admintest',
        'lastname' => 'test',
        'username' => 'testuser',
        'email' => 'admintestindividual@eoxvantage.in',
        'date_of_birth' => '2020-11-02',
        'gender' => 'Male',
        'managerId' => '4fd99e8e-758f-11e9-b2d5-68ecc57cde45',
        'role' => array(['0' => array(['uuid' => 'f5359981-fca4-11ea-8921-485f997ffb6f'])]),
        'address1' => '23811 Chagrin Blvd, Ste 244',
        'city' => 'Beachwood',
        'state' => 'OH',
        'zip' => '44122',
        'country' => 'US',
        'account_id' => '1',
        'address2' => '',
        'designation' => 'Dev',
        'person_id' => '152',
        'name' => 'test test',
        'date_of_join' => '2020-11-23',
        'date_created' => '2020-11-05 10:07:39',
        'password_reset_code' => 'c347a277-e724-42ea-9b2a-c80dc62f7d7e',
        'created_by' => ''];
        $this->EmplyService->addEmployeeRecord($data);
        $person_id = $data['person_id'];
        $rows = $this->executeQueryTest("SELECT * FROM ox_employee WHERE person_id='${person_id}'");
        $this->assertEquals($data['manager_id'], $rows[0]['manager_id']);
        $this->assertEquals($data['person_id'], $rows[0]['person_id']);
        $this->assertEquals($data['designation'], $rows[0]['designation']);
        $this->assertEquals($data['date_of_join'], $rows[0]['date_of_join']);
    }

    public function testupdateEmployeeWithRecord()
    {
        AuthContext::put(AuthConstants::USER_ID, '1');
        AuthContext::put(AuthConstants::ACCOUNT_ID, '1');
        $data = [
            'uuid' => '0bd8934b-2a80-11eb-af3f-283a4d5cd637',
            'username' => 'testuser',
            'password' => '68de082ad0afbcdb3cdec0427e38dd3f',
            'name' => 'test test',
            'account_id' => '1',
            'icon' => '',
            'status' => 'Active',
            'in_game' => '',
            'timezone' => 'Asia/Kolkata',
            'preferences' => '',
            'password_reset_code' => 'a3fb75c1-dc0f-47d5-8950-ace6f1ac373f',
            'password_reset_expiry_date' => '',
            'person_id' => '1',
            'firstname' => 'test',
            'lastname' => 'test',
            'email' => 'admintestindividual@eoxvantage.in',
            'date_of_birth' => '2020-11-16',
            'designation' => 'Dev',
            'gender' => 'Male',
            'managerid' => '4fd99e8e-758f-11e9-b2d5-68ecc57cde45',
            'role' => array(['0' => array(['uuid' => 'f5359981-fca4-11ea-8921-485f997ffb6f'])]),
            'project' => array(
            ),

            'date_of_join' => '2020-11-23',
            'address1' => '23811 Chagrin Blvd, Ste 244',
            'city' => 'Beachwood',
            'state' => 'OH',
            'zip' => '44122',
            'country' => 'US',
            'address2' => '',
            'modified_id' => '1',
            'date_modified' => '2020-11-06 11:30:42'
        ];
        $this->EmplyService->updateEmployeeDetails($data);
        $personId = $data['person_id'];
        $rows = $this->executeQueryTest("SELECT * FROM ox_employee WHERE id='${personId}'");
        $this->assertEquals($data['person_id'], $rows[0]['person_id']);
        $this->assertEquals($data['designation'], $rows[0]['designation']);
        $this->assertEquals($data['date_of_join'], $rows[0]['date_of_join']);
    }
}
