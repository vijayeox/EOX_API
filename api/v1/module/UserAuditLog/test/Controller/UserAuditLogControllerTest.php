<?php

namespace UserAuditLog;

use Oxzion\Test\ControllerTest;
use UserAuditLog\Controller\UserAuditLogController;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Zend\Db\Sql\Sql;

class UserAuditLogControllerTest extends ControllerTest
{
    public function setUp() : void
    {
        $this->loadConfig();
        parent::setUp();
    }
    protected function setDefaultAsserts()
    {
        $this->assertModuleName('userauditlog');
        $this->assertControllerName(UserAuditLogController::class); // as specified in router's controller name alias
        $this->assertControllerClass('UserAuditLogController');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    }

    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__) . "/../Dataset/UserAuditLog.yml");
        return $dataset;
    }

    public function testInsertLoginTime(){
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/auditlog/activity/login', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('userauditlog');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $query = "SELECT user_id, activity_time, activity from ox_user_audit_log where user_id = '".$this->adminUserId."' and account_id = '".$this->testAccountId."' AND activity ='login' ORDER BY activity_time DESC limit 1";
        $res = $this->executeQueryTest($query);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($res[0]['activity_time'], date('Y-m-d h:i:s'));
        $this->assertEquals($res[0]['user_id'], $this->adminUserId);
        $this->assertEquals($res[0]['activity'], "login");
    }

    public function testInsertLogoutTime(){
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/auditlog/activity/logout', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('userauditlog');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $query = "SELECT user_id, activity_time, activity from ox_user_audit_log where user_id = '".$this->adminUserId."' and account_id = '".$this->testAccountId."' AND activity ='logout' ORDER BY activity_time DESC limit 1";
        $res = $this->executeQueryTest($query);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($res[0]['activity_time'], date('Y-m-d h:i:s'));
        $this->assertEquals($res[0]['user_id'], $this->adminUserId);
        $this->assertEquals($res[0]['activity'], "logout");
    }

    public function testInsertOpenpcTime(){
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/auditlog/activity/open Policy Central', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('userauditlog');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $query = "SELECT user_id, activity_time, activity from ox_user_audit_log where user_id = '".$this->adminUserId."' and account_id = '".$this->testAccountId."' AND activity ='open Policy Central' ORDER BY activity_time DESC limit 1";
        $res = $this->executeQueryTest($query);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($res[0]['activity_time'], date('Y-m-d h:i:s'));
        $this->assertEquals($res[0]['user_id'], $this->adminUserId);
        $this->assertEquals($res[0]['activity'], "open Policy Central");
    }

    public function testInsertClosepcTime(){
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/auditlog/activity/close Policy Central', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('userauditlog');
        $content = (array)json_decode($this->getResponse()->getContent(), true);
        $query = "SELECT user_id, activity_time, activity from ox_user_audit_log where user_id = '".$this->adminUserId."' and account_id = '".$this->testAccountId."' AND activity ='close Policy Central' ORDER BY activity_time DESC limit 1";
        $res = $this->executeQueryTest($query);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($res[0]['activity_time'], date('Y-m-d h:i:s'));
        $this->assertEquals($res[0]['user_id'], $this->adminUserId);
        $this->assertEquals($res[0]['activity'], "close Policy Central");
    }


   
}
