<?php
namespace Domain;

use Email\Controller\DomainController;
use Oxzion\Test\ControllerTest;
use Email\Model;
use PHPUnit\DbUnit\TestCaseTrait;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Zend\Db\Sql\Sql;
use Zend\Db\Adapter\Adapter;
use Oxzion\Utils\FileUtils;


class DomainControllerTest extends ControllerTest
{
    public function setUp() : void
    {
        $this->loadConfig();
        parent::setUp();
    }

    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__) . "/../Dataset/Domain.yml");
        return $dataset;
    }

    protected function setDefaultAsserts()
    {
        $this->assertModuleName('Email');
        $this->assertControllerName(DomainController::class); // as specified in router's controller name alias
        $this->assertControllerClass('DomainController');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    }

    public function testGetList()
    {
        $data = ['id' => 1,
            'name' => 'Test Server',
            'imap_server' => 'Test Server',
            'imap_port' => '90',
            'imap_secure' => '90',
            'imap_short_login' => '2',
            'smtp_server' => 'testing',
            'smtp_port' => '99',
            'smtp_secure' => 'securing1',
            'smtp_short_login' => 'short_name',
            'smtp_auth' => 'auth',
            'smtp_use_php_mail' => 'No',
        ];
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/domain', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 2);
        foreach ($data as $key => $val) {
            $this->assertEquals($content['data'][0][$key], $val);
        }
    }

    public function testGet()
    {
        $data = ['id' => 1,
            'name' => 'Test Server',
            'imap_server' => 'Test Server',
            'imap_port' => '90',
            'imap_secure' => '90',
            'imap_short_login' => '2',
            'smtp_server' => 'testing',
            'smtp_port' => '99',
            'smtp_secure' => 'securing1',
            'smtp_short_login' => 'short_name',
            'smtp_auth' => 'auth',
            'smtp_use_php_mail' => 'No',
        ];
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/domain/1', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        foreach ($data as $key => $val) {
            $this->assertEquals($content['data'][$key], $val);
        }
    }

    public function testGetNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/domain/64', 'GET');
        $this->assertResponseStatusCode(404);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testCreate()
    {
        $this->initAuthToken($this->adminUser);
        $data = [
            'name' => 'Test Server 3',
            'imap_server' => 'Test Server 3',
            'imap_port' => '90',
            'imap_secure' => '90',
            'imap_short_login' => '2',
            'smtp_server' => 'testing',
            'smtp_port' => '99',
            'smtp_secure' => 'securing3',
            'smtp_short_login' => 'short_name',
            'smtp_auth' => 'auth',
            'smtp_use_php_mail' => 'No',
        ];
        $this->assertEquals(2, $this->getConnection()->getRowCount('ox_email_domain'));
        $this->dispatch('/domain', 'POST', $data);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        foreach ($data as $key => $val) {
            $this->assertEquals($content['data'][$key], $val);
        }
        $this->assertEquals(3, $this->getConnection()->getRowCount('ox_email_domain'));
    }

    public function testCreateWithOutDataFailure()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Wrong Server'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/domain', 'POST', null);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Validation Errors');
        $this->assertEquals($content['data']['errors']['imap_server'], 'required');
    }

    public function testUpdate()
    {
        $data = [
            'id' => 1,
            'name' => 'Test Server Changed Name',
        ];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/domain/1', 'PUT', null);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('domain');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
    }

    public function testUpdateNotFound()
    {
        $data = [
            'id' => 99,
            'name' => 'Test Server Changed Name',
        ];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/domain/99', 'PUT', null);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('domain');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testDelete()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/domain/1', 'DELETE');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('domain');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testDeleteNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/domain/9999', 'DELETE');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('domain');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }


}