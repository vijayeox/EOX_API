<?php
namespace Oxzion\Service;

use Oxzion\Test\AbstractServiceTest;
use Oxzion\Service\AccountService;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Zend\Db\ResultSet\ResultSet;

class AccountServiceTest extends AbstractServiceTest
{
    public $dataset = null;

    public $adapter = null;

    protected function setUp(): void
    {
        $this->loadConfig();
        parent::setUp();
        $this->adapter = $this->getDbAdapter();
        $this->adapter->getDriver()->getConnection()->setResource(static::$pdo);
    }

    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__)."/Dataset/AccountUser.yml");
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

    

    public function testRegisterAccount(){
        $accountService = $this->getApplicationServiceLocator()->get(AccountService::class);
        $data = array(
            "address1" => "Address",
            "appId" => "a77ea120-b028-479b-8c6e-60476b6a4459",
            "city"=> "City",
            "contact"=> array(
                "username"=> "abc@gmail.com", 
                "firstname"=> "ABC",
                "lastname"=> "Comapny",
                "email" => ""
            ),
            "country"=> "United States of America",
            "email"=> "abc@gmail.com",
            "firstname"=> "ABC",
            "lastname"=> "Company",
            "middlename"=> "",
            "name"=> "ABC Company",
            "preferences"=> "{}",
            "state"=> "Arkansas",
            "type"=> "BUSINESS",
            "zip"=> "00000"
        );
        $accountService->registerAccount($data);
        $select = "SELECT * from ox_account where name = 'ABC Company'";
        $result = $this->runQuery($select);
        $this->assertEquals('ABC Company', $result[0]['name']);
    }

    public function testRegisterAccountWithExistingUser(){
        $accountService = $this->getApplicationServiceLocator()->get(AccountService::class);
        $data = array(
            "address1" => "Address",
            "appId" => "a77ea120-b028-479b-8c6e-60476b6a4459",
            "city"=> "City",
            "contact"=> array(
                "username"=> "abc123", 
                "firstname"=> "Deepa",
                "lastname"=> "Shree",
                "email" => ""
            ),
            "country"=> "United States of America",
            "email"=> "abc@gmail.com",
            "firstname"=> "ABC",
            "lastname"=> "Company",
            "middlename"=> "",
            "name"=> "ABC Company",
            "preferences"=> "{}",
            "state"=> "Arkansas",
            "type"=> "BUSINESS",
            "zip"=> "00000",
            "userUuid" => "2db1c5a3-8a82-4d5b-b60a-c648cf1e27de",
            "addExistingUserToNewAccount" => true
        );
        $accountService->registerAccount($data);
        $select = "SELECT * from ox_account where name = 'ABC Company'";
        $result = $this->runQuery($select);
        $this->assertEquals('ABC Company', $result[0]['name']);
        $select2 = "SELECT * from ox_user where name = 'Deepa Shree'";
        $result2 = $this->runQuery($select2);
        $this->assertEquals('Deepa Shree', $result2[0]['name']);
        $this->assertEquals($result[0]['id'], $result2[0]['account_id']);
    }

    public function testRegisterAccountWithBusinessRole(){
        $accountService = $this->getApplicationServiceLocator()->get(AccountService::class);
        $data = array(
            "address1" => "Address",
            "appId" => "a77ea120-b028-479b-8c6e-60476b6a4459",
            "businessRole"=> "Business Role 1", 
            "city"=> "City",
            "contact"=> array(
                "username"=> "abc@gmail.com", 
                "firstname"=> "ABC",
                "lastname"=> "Comapny",
                "email" => ""
            ),
            "country"=> "United States of America",
            "email"=> "abc@gmail.com",
            "firstname"=> "ABC",
            "lastname"=> "Company",
            "middlename"=> "",
            "name"=> "ABC Company",
            "preferences"=> "{}",
            "state"=> "Arkansas",
            "type"=> "BUSINESS",
            "zip"=> "00000"
        );
        $accountService->registerAccount($data);
        $select = "SELECT * from ox_account where name = 'ABC Company'";
        $result = $this->runQuery($select);
        $this->assertEquals('ABC Company', $result[0]['name']);
        $select = "SELECT * from ox_account_business_role oabr join ox_business_role obr on oabr.business_role_id = obr.id where account_id = ".$result[0]['id'];
        $result2 = $this->runQuery($select);
        $this->assertEquals($data['businessRole'], $result2[0]['name']);
    }


    public function testRegisterAccountVerifyRole(){
        $accountService = $this->getApplicationServiceLocator()->get(AccountService::class);
        $data = array(
            "address1" => "Address",
            "appId" => "a77ea120-b028-479b-8c6e-60476b6a4459",
            "city"=> "City",
            "contact"=> array(
                "username"=> "abc@gmail.com", 
                "firstname"=> "ABC",
                "lastname"=> "Comapny",
                "email" => ""
            ),
            "country"=> "United States of America",
            "email"=> "abc@gmail.com",
            "firstname"=> "ABC",
            "lastname"=> "Company",
            "middlename"=> "",
            "name"=> "ABC Company",
            "preferences"=> "{}",
            "state"=> "Arkansas",
            "type"=> "BUSINESS",
            "zip"=> "00000"
        );
        $accountService->registerAccount($data);
        $select = "SELECT * from ox_account where name = 'ABC Company'";
        $result = $this->runQuery($select);
        $this->assertEquals('ABC Company', $result[0]['name']);
        $select = "SELECT * from ox_role where account_id = ".$result[0]['id'];
        $result2 = $this->runQuery($select);
        $this->assertEquals('ADMIN', $result2[0]['name']);
        $this->assertEquals('MANAGER', $result2[1]['name']);
        $this->assertEquals('EMPLOYEE', $result2[2]['name']);
    }
}
