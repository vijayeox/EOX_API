<?php

use Oxzion\Test\DelegateTest;
use Oxzion\AppDelegate\AppDelegateService;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Oxzion\Utils\FileUtils;
use Oxzion\Db\Persistence\Persistence;

class CleanYmlTest extends DelegateTest
{

    public function setUp(): void
    {
        $this->loadConfig();
        $config = $this->getApplicationConfig();
        $this->data = array(
            "appName" => 'Demo APP',
            'UUID' => '1276b333-e155-433e-a0c9-cb1fb92e99fa',
            'description' => 'Demo APP',
            'orgUuid' => '53012471-2863-4949-afb1-e69b0891c98a'
        );
        $path = __DIR__ . '/../../../api/v1/data/delegate/' . $this->data['UUID'];
        if (!is_link($path)) {
            symlink(__DIR__ . '/../data/delegate/', $path);
        }
        parent::setUp();
    }

    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__) . "/Dataset/AppData.yml");
        return $dataset;
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $path = __DIR__ . '/../../../api/v1/data/delegate/' . $this->data['UUID'];
        if (is_link($path)) {
            unlink($path);
        }
    }

    private function getSampleData(){
        $data = [
            "app" => array(
                "uuid" => "1276b333-e155-433e-a0c9-cb1fb92e99fa",
                "previousVersion" => "1.0",
                "oldAppName" => "",
                "title" => "Demo APP",
                "name" => "Demo APP",
                "description" => "",
                "category" => "business",
                "fontIcon"=> "fas fa-desktop-alt",
                "chat_notification"=> false,
                "type"=> 2,
                "isdefault"=> false,
                "logo"=> "default_app.png",
                "status"=> 2,
                "start_options"=> null,
                "app_icon"=> [],
                "app_icon_white"=> [],
                "autostart"=> false
            ),
            "appId"=> "41b77ef3-41db-4a52-8eb8-ba3ac9a9d771",
            "appVersion"=> "",
            "entity"=> array(array(
                "name"=> "Enty",
                "start_date_field"=> "start_date",
                "end_date_field"=> "end_date",
                "status_field"=> "status",
                "subscriber_field"=> "subscriber",
                "title"=> "",
                "enable_documents"=> false,
                "enable_comments"=> false,
                "enable_view"=> false,
                "enable_auditlog"=> false,
                "identifiers"=> array(array("identifier"=> "email")),
                "enable_print"=> false,
                "entity_uuid"=> "8890c67a-9e5a-4f75-ba68-f82239d5191c"
            )),
            "menu"=> array(array(
                "name"=> "Menut",
                "icon"=> "fa fa-home",
                "page"=> "Home",
                "uuid"=> "",
                "privilege"=> ""
            )),
            "pages"=> array(array(
                "name"=> "Home",
                "uuid"=> "b1b6e707-ad50-4a6c-86fb-4e607a605968",
                "description"=> "",
                "content"=> array(array(
                    "type"=> "HTMLViewer",
                    "htmlContent"=> "<p>&lt;p&gt;ABCD&lt;/p&gt;</p>\n",
                    "content"=> "<p>&lt;p&gt;ABCD&lt;/p&gt;</p>\n",
                    "useRowData"=> false,
                    "fileId"=> "{{uuid}}"
                )),
                "pageContent"=> array(
                    "data"=> array(
                        "pagesList"=> array(array(
                            "name"=> "Home",
                            "uuid"=> "b1b6e707-ad50-4a6c-86fb-4e607a605968"
                        )),
                        "content"=> array(array(
                            "type"=> "HTMLViewer",
                            "htmlContent"=> "<p>&lt;p&gt;ABCD&lt;/p&gt;</p>\n",
                            "content"=> "<p>&lt;p&gt;ABCD&lt;/p&gt;</p>\n",
                            "useRowData"=> false,
                            "fileId"=> "{{uuid}}"
                        )) 
                    )
                )
            )),
            "form" => "",
            "pagesList"=> array(array(
                "name"=> "Home",
                "uuid"=> "b1b6e707-ad50-4a6c-86fb-4e607a605968"
            )),
            "privilege"=> array(array(
                "name"=> "MANAGE_INS",
                "permission"=> array(
                    "1"=> true,
                    "3"=> true,
                    "7"=> true,
                    "15"=> true
                )
            )),
            "role"=> array([
                "name"=> "AVANT",
                "default"=> false,
                "privileges"=> array([
                    "permission"=> [
                        "1"=> true,
                        "3"=> false,
                        "7"=> false,
                        "15"=> false
                    ],
                    "privilege_name"=> "MANAGE_INS"
                ]),
            ]) 
        ];
        return $data;
    }

    public function testAddSearchIndexFieldsForEntityWithEmptyField()
    {
        $config = $this->getApplicationConfig();
        $this->initAuthContext('admintest');
        $appId = $this->data['UUID'];
        $data = $this->getSampleData();
        $config = $this->getApplicationConfig();
        $this->persistence = new Persistence($config, 'FirstAppOfTheClient', $appId);
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $path = $config['DATA_FOLDER'].'AppSource/'.$appId;
        if(!FileUtils::fileExists($path)){
            FileUtils::createDirectory($path);
            FileUtils::copy(__DIR__.'/Files/sample.yml','application.yml',$path);
        }
    
        $content = $delegateService->execute($appId, 'CleanYml', $data);
        FileUtils::rmDir($path);
        $this->assertNotEquals(sizeof($content),0);  
        $this->assertEquals(sizeof($content['entity'][0]['field']) >= 6,true);
    }


    public function testAddSearchIndexFieldsForEntityWithMissingField()
    {
        $config = $this->getApplicationConfig();
        $this->initAuthContext('admintest');
        $appId = $this->data['UUID'];
        $data = $this->getSampleData();
        $data['entity'][0]['field'] = [
            [
                "name" => "documents",
                "text" => "Documents",
                "data_type" => "list",
                "index" => true
            ]
        ];
        $config = $this->getApplicationConfig();
        $this->persistence = new Persistence($config, 'FirstAppOfTheClient', $appId);
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $path = $config['DATA_FOLDER'].'AppSource/'.$appId;
        if(!FileUtils::fileExists($path)){
            FileUtils::createDirectory($path);
            FileUtils::copy(__DIR__.'/Files/sample.yml','application.yml',$path);
        }
    
        $content = $delegateService->execute($appId, 'CleanYml', $data);
        FileUtils::rmDir($path);
        $this->assertNotEquals(sizeof($content),0);  
        $this->assertEquals(sizeof($content['entity'][0]['field']) >= 6,true);
    }

    public function testAddSearchIndexFieldsForFormWithoutField()
    {
        $config = $this->getApplicationConfig();
        $this->initAuthContext('admintest');
        $appId = $this->data['UUID'];
        $data = $this->getSampleData();
        $data['form'] = [
            [
                "entity"=> "Enty",
                "fields"=> [],
                "formAppId"=> "1420c15e-d762-4812-82b0-4e3142bd3b39",                    
                "name"=> "fieldSequence",
                "template_file"=> "fieldSequence.json",
                "uuid"=> "95824bf9-c411-4913-814e-29e9db5cf17e"
            ]
        ];
        $config = $this->getApplicationConfig();
        $this->persistence = new Persistence($config, 'FirstAppOfTheClient', $appId);
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $path = $config['DATA_FOLDER'].'AppSource/'.$appId;
        if(!FileUtils::fileExists($path)){
            FileUtils::createDirectory($path);
            FileUtils::copy(__DIR__.'/Files/sample.yml','application.yml',$path);
        }
    
        $content = $delegateService->execute($appId, 'CleanYml', $data);
        FileUtils::rmDir($path);
        $this->assertNotEquals(sizeof($content),0);  
        $this->assertEquals(sizeof($content['form'][0]['fields']) >= 6,true);
    }

    public function testAddSearchIndexFieldsForFormWithMissingField()
    {
        $config = $this->getApplicationConfig();
        $this->initAuthContext('admintest');
        $appId = $this->data['UUID'];
        $data = $this->getSampleData();
        $data['form'] = [
            [
                "entity"=> "Enty",
                "fields"=> [
                    "name" => "name",
                    "text" => "name",
                    "data_type" => "text",
                    "search_index" => true
                ],
                "formAppId"=> "1420c15e-d762-4812-82b0-4e3142bd3b39",                    
                "name"=> "fieldSequence",
                "template_file"=> "fieldSequence.json",
                "uuid"=> "95824bf9-c411-4913-814e-29e9db5cf17e"
            ]
        ];
        $config = $this->getApplicationConfig();
        $this->persistence = new Persistence($config, 'FirstAppOfTheClient', $appId);
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $path = $config['DATA_FOLDER'].'AppSource/'.$appId;
        if(!FileUtils::fileExists($path)){
            FileUtils::createDirectory($path);
            FileUtils::copy(__DIR__.'/Files/sample.yml','application.yml',$path);
        }
    
        $content = $delegateService->execute($appId, 'CleanYml', $data);
        FileUtils::rmDir($path);
        $this->assertNotEquals(sizeof($content),0);  
        $this->assertEquals(sizeof($content['form'][0]['fields']) >= 6,true);
    }
}
