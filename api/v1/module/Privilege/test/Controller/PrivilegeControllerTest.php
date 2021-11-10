<?php
namespace Privilege;

use Oxzion\Test\MainControllerTest;
use Privilege\Controller\PrivilegeController;

class PrivilegeControllerTest extends MainControllerTest
{
    public function setUp(): void
    {
        $this->loadConfig();
        parent::setUp();
    }

    protected function setDefaultAsserts()
    {
        $this->assertModuleName('Privilege');
        $this->assertControllerName(PrivilegeController::class); // as specified in router's controller name alias
        $this->assertControllerClass('PrivilegeController');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    }

    public function testGetUserPrivilegesWithWrongApps()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/privilege/app/23635WR36APPS', 'GET');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('userprivileges');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testGetMasterPrivilegeList()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/masterprivilege', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('getMasterPrivilege');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']['masterPrivilege']), 42);
        $this->assertEquals($content['data']['masterPrivilege'][0]['privilege_name'], 'MANAGE_ACCOUNT');
        $this->assertEquals($content['data']['masterPrivilege'][1]['privilege_name'], 'MANAGE_ALERT');
        $this->assertEquals($content['data']['masterPrivilege'][2]['privilege_name'], 'MANAGE_ANALYTICS_WIDGET');
    }

    public function testGetMasterPrivilegeListWithRoleId()
    {
        $update = "UPDATE ox_role SET uuid = 'c04edd51-af8a-11e9-91bf-68ecc57cde45' where name ='MANAGER' and account_id = 1";
        $result = $this->executeUpdate($update);

        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/masterprivilege/c04edd51-af8a-11e9-91bf-68ecc57cde45', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('getMasterPrivilege');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']['masterPrivilege']), 42);
        $this->assertEquals($content['data']['masterPrivilege'][0]['privilege_name'], 'MANAGE_ACCOUNT');
        $this->assertEquals($content['data']['masterPrivilege'][1]['privilege_name'], 'MANAGE_ALERT');
        $this->assertEquals(count($content['data']['rolePrivilege']), 4);
        $this->assertEquals($content['data']['rolePrivilege'][0]['privilege_name'], 'MANAGE_CRM');
        $this->assertEquals($content['data']['rolePrivilege'][1]['privilege_name'], 'MANAGE_DATASOURCE');
    }

    public function testGetMasterPrivilegeListWithInValidRoleId()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/masterprivilege/58428', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('getMasterPrivilege');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']['masterPrivilege']), 42);
        $this->assertEquals($content['data']['masterPrivilege'][0]['privilege_name'], 'MANAGE_ACCOUNT');
        $this->assertEquals($content['data']['masterPrivilege'][1]['privilege_name'], 'MANAGE_ALERT');
        $this->assertEquals(empty($content['data']['rolePrivilege']), true);
    }

    public function testGetMasterPrivilegeOtherOrg()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/masterprivilege', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('getMasterPrivilege');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']['masterPrivilege']), 39);
        $this->assertEquals($content['data']['masterPrivilege'][0]['privilege_name'], 'MANAGE_ALERT');
        $this->assertEquals($content['data']['masterPrivilege'][1]['privilege_name'], 'MANAGE_ANALYTICS_WIDGET');
    }
}
