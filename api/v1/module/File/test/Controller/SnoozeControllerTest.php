<?php
namespace File;

use File\Controller\SnoozeController;
use Oxzion\Test\ControllerTest;
use Oxzion\Db\ModelTable;
use PHPUnit\DbUnit\TestCaseTrait;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Zend\Db\Sql\Sql;
use Zend\Db\Adapter\Adapter;
use Oxzion\Utils\FileUtils;

class SnoozeControllerTest extends ControllerTest
{
    public function setUp() : void
    {
        $this->loadConfig();
        parent::setUp();
    }
    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__)."/../Dataset/Snooze.yml");
        return $dataset;
    }
    protected function setDefaultAsserts()
    {
        $this->assertModuleName('File');
        $this->assertControllerName(SnoozeController::class); // as specified in router's controller name alias
        $this->assertControllerClass('SnoozeController');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    }
    public function testSnoozeFile()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['snooze' => '1'];
        $this->dispatch('/file/870caa5b-695a-4ee6-bdd6-9564f1d7e366/snooze', 'POST',$data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['message'], 'File has been snoozed');

        $query = "Select * from ox_file where uuid = '870caa5b-695a-4ee6-bdd6-9564f1d7e366'";
        $result = $this->executeQueryTest($query);
        $this->assertEquals($result[0]["is_snoozed"],1);
    }

    public function testUnsnoozeFile()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['snooze' => '0'];
        $this->dispatch('/file/6a5521c3-dcf3-4854-b2d0-dc430eb48a75/snooze', 'POST',$data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['message'], 'File is alive');

        $query = "Select * from ox_file where uuid = '6a5521c3-dcf3-4854-b2d0-dc430eb48a75'";
        $result = $this->executeQueryTest($query);
        $this->assertEquals($result[0]["is_snoozed"],0);
  
    }

}
