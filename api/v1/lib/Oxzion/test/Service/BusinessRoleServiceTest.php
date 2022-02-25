<?php
namespace Oxzion\Service;

use Oxzion\Test\AbstractServiceTest;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Symfony\Component\Yaml\Yaml;
use Oxzion\ValidationException;

class BusinessRoleServiceTest extends AbstractServiceTest
{
    public function setUp() : void
    {
        $this->loadConfig();
        parent::setUp();
        $this->businessRoleService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\BusinessRoleService::class);
        AuthContext::put(AuthConstants::ACCOUNT_ID, 1);
        AuthContext::put(AuthConstants::USER_ID, 1);
    }

    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__)."/Dataset/Form.yml");
        return $dataset;
    }

    private function parseYaml()
    {
        $dataset = Yaml::parseFile(dirname(__FILE__)."/Dataset/Form.yml");
        return $dataset;
        
    }

    public function testCreateBusinessRole()
    {
        $dataset = $this->parseYaml();
        $data['name'] = 'Agency';
        $appId = $dataset['ox_app'][0]['uuid'];
        $this->businessRoleService->saveBusinessRole($appId, $data);
        $this->assertEquals(true, isset($data['uuid']));
        $query = "select * from ox_business_role where uuid = '".$data['uuid']."'";
        $queryResult = $this->executeQueryTest($query);
        $this->assertEquals(1, count($queryResult));
        $this->assertEquals(1, $queryResult[0]['created_by']);
        $this->assertEquals(null, $queryResult[0]['modified_by']);
        $this->assertEquals(null, $queryResult[0]['date_modified']);
        $this->assertEquals(date('Y-m-d'), date_create($queryResult[0]['date_created'])->format('Y-m-d'));
        $this->assertEquals($data['name'], $queryResult[0]['name']);
        $this->assertEquals($dataset['ox_app'][0]['id'], $queryResult[0]['app_id']);
        $this->assertEquals($data['version'], $queryResult[0]['version']);
    }

    public function testCreateBusinessRoleWithUuid()
    {
        $dataset = $this->parseYaml();
        $data['name'] = 'Agency';
        $data['uuid'] = $roleId = "f12c8367-fffe-4d9b-8914-14a80a478bda";
        $appId = $dataset['ox_app'][0]['uuid'];
        $this->businessRoleService->saveBusinessRole($appId, $data);
        $this->assertEquals(true, isset($data['uuid']));
        $query = "select * from ox_business_role where uuid = '".$data['uuid']."'";
        $queryResult = $this->executeQueryTest($query);
        $this->assertEquals(1, count($queryResult));
        $this->assertEquals(1, $queryResult[0]['created_by']);
        $this->assertEquals($roleId, $queryResult[0]['uuid']);
        $this->assertEquals(null, $queryResult[0]['modified_by']);
        $this->assertEquals(null, $queryResult[0]['date_modified']);
        $this->assertEquals(date('Y-m-d'), date_create($queryResult[0]['date_created'])->format('Y-m-d'));
        $this->assertEquals($data['name'], $queryResult[0]['name']);
        $this->assertEquals($dataset['ox_app'][0]['id'], $queryResult[0]['app_id']);
        $this->assertEquals($data['version'], $queryResult[0]['version']);
    }

    public function testCreateBusinessRoleWithNoName()
    {
        $dataset = $this->parseYaml();
        $appId = $dataset['ox_app'][1]['uuid'];
        $data=array();
        $this->expectException(ValidationException::class);
        $this->businessRoleService->saveBusinessRole($appId, $data);
    }

    public function testUpdateBusinessRole()
    {
        $dataset = $this->parseYaml();
        $data['name'] = 'Agency';
        $roleId = $data['uuid'] = $dataset['ox_business_role'][0]['uuid'];
        $appId = $dataset['ox_app'][0]['uuid'];
        $this->businessRoleService->saveBusinessRole($appId, $data);
        $this->assertEquals(true, isset($data['uuid']));
        $query = "select * from ox_business_role where uuid = '".$data['uuid']."'";
        $queryResult = $this->executeQueryTest($query);
        $this->assertEquals(1, count($queryResult));
        $this->assertEquals($dataset['ox_business_role'][0]['id'], $queryResult[0]['id']);
        $this->assertEquals($roleId, $queryResult[0]['uuid']);
        $this->assertEquals($dataset['ox_business_role'][0]['created_by'], $queryResult[0]['created_by']);
        $this->assertEquals(1, $queryResult[0]['modified_by']);
        $this->assertEquals($dataset['ox_business_role'][0]['date_created'], $queryResult[0]['date_created']);
        $this->assertEquals(date('Y-m-d'), date_create($queryResult[0]['date_modified'])->format('Y-m-d'));
        $this->assertEquals($data['name'], $queryResult[0]['name']);
        $this->assertEquals($dataset['ox_business_role'][0]['version'] + 1, $queryResult[0]['version']);
    }

    public function testGetBusinessRole()
    {
        $dataset = $this->parseYaml();
        $roleId = $dataset['ox_business_role'][0]['uuid'];
        $data = $this->businessRoleService->getBusinessRole($roleId);
        $this->assertEquals($dataset['ox_business_role'][0]['id'], $data['id']);
        $this->assertEquals($roleId, $data['uuid']);
        $this->assertEquals($dataset['ox_business_role'][0]['created_by'], $data['created_by']);
        $this->assertEquals(null, $data['modified_by']);
        $this->assertEquals($dataset['ox_business_role'][0]['date_created'], $data['date_created']);
        $this->assertEquals(null, $data['date_modified']);
        $this->assertEquals($dataset['ox_business_role'][0]['name'], $data['name']);
        $this->assertEquals($dataset['ox_business_role'][0]['version'], $data['version']);
    }

    public function testGetBusinessRoleByName()
    {
        $dataset = $this->parseYaml();
        $roleName = $dataset['ox_business_role'][0]['name'];
        $appId = $dataset['ox_app'][0]['uuid'];
        $data = $this->businessRoleService->getBusinessRoleByName($appId, $roleName);
        $this->assertEquals(1, count($data));
        $data = $data[0];
        $this->assertEquals($dataset['ox_business_role'][0]['id'], $data['id']);
        $this->assertEquals($dataset['ox_business_role'][0]['uuid'], $data['uuid']);
        $this->assertEquals($dataset['ox_business_role'][0]['created_by'], $data['created_by']);
        $this->assertEquals(null, $data['modified_by']);
        $this->assertEquals($dataset['ox_business_role'][0]['date_created'], $data['date_created']);
        $this->assertEquals(null, $data['date_modified']);
        $this->assertEquals($dataset['ox_business_role'][0]['name'], $data['name']);
        $this->assertEquals($dataset['ox_business_role'][0]['version'], $data['version']);
    }

    public function testPreventionOfDuplicateBusinessRole() {
        $dataset = $this->parseYaml();
        $data['name'] = $dataset['ox_business_role'][0]['name'];
        $appId = $dataset['ox_app'][0]['uuid'];
        $resultData = $this->businessRoleService->saveBusinessRole($appId, $data);
        $this->assertEquals(true, isset($data['uuid']));
        $query = "select * from ox_business_role where uuid = '".$data['uuid']."'";
        $queryResult = $this->executeQueryTest($query);
        $this->assertEquals($dataset['ox_business_role'][0]['id'], $queryResult[0]['id']);
        $this->assertEquals($dataset['ox_business_role'][0]['uuid'], $queryResult[0]['uuid']);
        $this->assertEquals($dataset['ox_business_role'][0]['created_by'], $queryResult[0]['created_by']);
        $this->assertEquals(null, $queryResult[0]['modified_by']);
        $this->assertEquals($dataset['ox_business_role'][0]['date_created'], $queryResult[0]['date_created']);
        $this->assertEquals(null, $queryResult[0]['date_modified']);
        $this->assertEquals($dataset['ox_business_role'][0]['name'], $queryResult[0]['name']);
    }
}
