<?php
namespace Privilege;

use Privilege\Controller\PrivilegeController;
use Oxzion\Test\MainControllerTest;
use Privilege\Model;
use PHPUnit\DbUnit\TestCaseTrait;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Zend\Db\Sql\Sql;
use Zend\Db\Adapter\Adapter;
use Oxzion\Utils\FileUtils;
use Oxzion\Service\PrivilegeService;


class PrivilegeControllerTest extends MainControllerTest
{
    public function setUp() : void
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

    public function testGetUserPrivileges()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/privilege/getappid', 'GET');
        $this->assertResponseStatusCode(200);
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $appId = $content['data'][0];
        $this->reset();
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/privilege/app/'.$appId, 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('userprivileges');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['name'], 'MANAGE_ANNOUNCEMENT');
        $this->assertEquals($content['data'][0]['permission_allowed'], 3);
    }

    public function testGetUserPrivilegesWithWrongApps()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/privilege/app/23435WR34APPS', 'GET');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('userprivileges');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testGetMasterPrivilegeList()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/masterprivilege', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('getMasterPrivilege');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']['masterPrivilege']),24);
        $this->assertEquals($content['data']['masterPrivilege'][0]['id'],1);
        $this->assertEquals($content['data']['masterPrivilege'][0]['privilege_name'],'MANAGE_ANNOUNCEMENT');
        $this->assertEquals($content['data']['masterPrivilege'][1]['id'],16);
        $this->assertEquals($content['data']['masterPrivilege'][1]['privilege_name'],'MANAGE_GROUP');
        $this->assertEquals($content['data']['masterPrivilege'][2]['id'],17);
        $this->assertEquals($content['data']['masterPrivilege'][2]['privilege_name'],'MANAGE_ORGANIZATION');
        $this->assertEquals($content['data']['masterPrivilege'][3]['id'],18);
        $this->assertEquals($content['data']['masterPrivilege'][3]['privilege_name'],'MANAGE_USER');
    }

    public function testGetMasterPrivilegeListWithRolePrivilege()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/masterprivilege/5', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('getMasterPrivilege');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']['masterPrivilege']),24);
        $this->assertEquals($content['data']['masterPrivilege'][0]['id'],1);
        $this->assertEquals($content['data']['masterPrivilege'][0]['privilege_name'],'MANAGE_ANNOUNCEMENT');
        $this->assertEquals($content['data']['masterPrivilege'][1]['id'],16);
        $this->assertEquals($content['data']['masterPrivilege'][1]['privilege_name'],'MANAGE_GROUP');
        $this->assertEquals(count($content['data']['rolePrivilege']),6);
        $this->assertEquals($content['data']['rolePrivilege'][0]['id'],39);
        $this->assertEquals($content['data']['rolePrivilege'][0]['privilege_name'],'MANAGE_MLET');
        $this->assertEquals($content['data']['rolePrivilege'][1]['id'],42);
        $this->assertEquals($content['data']['rolePrivilege'][1]['privilege_name'],'MANAGE_CRM');
    }

    public function testGetMasterPrivilegeListWithInValidRolePrivilege()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/masterprivilege/58428', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('getMasterPrivilege');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']['masterPrivilege']),24);
        $this->assertEquals($content['data']['masterPrivilege'][0]['id'],1);
        $this->assertEquals($content['data']['masterPrivilege'][0]['privilege_name'],'MANAGE_ANNOUNCEMENT');
        $this->assertEquals($content['data']['masterPrivilege'][1]['id'],16);
        $this->assertEquals($content['data']['masterPrivilege'][1]['privilege_name'],'MANAGE_GROUP');
        $this->assertEquals($content['data']['rolePrivilege'],array());
    }
}