<?php
namespace App;

use App\Controller\AppRegisterController;
use Mockery;
use Oxzion\Db\Migration\Migration;
use Oxzion\Test\ControllerTest;
use PHPUnit\DbUnit\DataSet\YamlDataSet;

class AppRegisterContollerTest extends ControllerTest
{
    public function setUp(): void
    {
        $this->loadConfig();
        $config = $this->getApplicationConfig();
        parent::setUp();
    }

    public function getDataSet()
    {
        switch ($this->getName()) {
            case 'testGetAccountOnForInstall':
            case 'testGetAccountOnInstalled':
            //case 'testCreateWithoutRequiredData':
                //Return empty data set to keep framework happy!
                echo "TESTING";
                return new YamlDataSet(dirname(__FILE__) . "/../../Dataset/fileattachment.yml");;
            break;
        }

        $dataset = new YamlDataSet(dirname(__FILE__) . "/../../Dataset/Workflow.yml");
        if ($this->getName() == 'testDeployAppWithWrongUuidInDatabase' || $this->getName() == 'testDeployAppWithWrongNameInDatabase' || $this->getName() == 'testDeployAppWithNameAndNoUuidInYMLButNameandUuidInDatabase' || $this->getName() == 'testDeployAppAddExtraPrivilegesInDatabaseFromYml' || $this->getName() == 'testDeployAppDeleteExtraPrivilegesInDatabaseNotInYml') {
            $dataset->addYamlFile(dirname(__FILE__) . "/../../Dataset/App2.yml");
        }
        return $dataset;
    }

    public function getMockProcessManager()
    {
        $mockProcessManager = Mockery::mock('\Oxzion\Workflow\Camunda\ProcessManagerImpl');
        $workflowService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\WorkflowService::class);
        $workflowService->setProcessManager($mockProcessManager);
        return $mockProcessManager;
    }

    protected function setDefaultAsserts()
    {
        $this->assertModuleName('App');
        $this->assertControllerName(AppRegisterController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AppRegisterContoller');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    }

    public function cleanDb($appName, $appId): void
    {
        $database = Migration::getDatabaseName($appName, $appId);
        $query = "DROP DATABASE IF EXISTS " . $database;
        $statement = Migration::createAdapter($this->getApplicationConfig(), $database)->query($query);
        $result = $statement->execute();
    }

    public function testAppRegister()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['applist' => json_encode([["name" => "CRM", "category" => "organization", "options" => ["autostart" => "false", "hidden" => "false"]], ["name" => "Calculator", "category" => "office", "options" => ["autostart" => "false", "hidden" => "false"]], ["name" => "Calendar", "category" => "collaboration", "options" => ["autostart" => "false", "hidden" => "false"]], ["name" => "Chat", "category" => "collaboration", "options" => ["autostart" => "true", "hidden" => "true"]], ["name" => "FileManager", "category" => "office", "options" => ["autostart" => "false", "hidden" => "false"]], ["name" => "Mail", "category" => "collaboration", "options" => ["autostart" => "true", "hidden" => "true"]], ["name" => "MailAdmin", "category" => "utilities", "options" => ["autostart" => "false", "hidden" => "false"]], ["name" => "MyTodo", "category" => "null", "options" => ["autostart" => "false", "hidden" => "true"]], ["name" => "Textpad", "category" => "office", "options" => ["autostart" => "false", "hidden" => "false"]]])];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/register', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('App');
        $this->assertControllerName(AppRegisterController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AppRegisterController');
        $this->assertMatchedRouteName('appregister');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testAppRegisterInvaliddata()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['applist' => json_encode([["name" => "", "category" => "organization", "options" => ["autostart" => "false", "hidden" => "false"]], ["name" => "Calculator", "category" => "office", "options" => ["autostart" => "false", "hidden" => "false"]], ["name" => "Calendar", "category" => "collaboration", "" => ["autostart" => "false", "hidden" => "false"]], ["name" => "Chat", "category" => "collaboration", "options" => ["autostart" => "true", "hidden" => "true"]], ["name" => "FileManager", "category" => "office", "options" => ["autostart" => "false", "hidden" => "false"]], ["name" => "Mail", "category" => "collaboration", "options" => ["autostart" => "true", "hidden" => "true"]], ["name" => "MailAdmin", "category" => "utilities", "options" => ["autostart" => "false", "hidden" => "false"]], ["name" => "MyTodo", "category" => "null", "options" => ["autostart" => "false", "hidden" => "true"]], ["name" => "Textpad", "category" => "office", "options" => ["autostart" => "false", "hidden" => "false"]]])];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/register', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('App');
        $this->assertControllerName(AppRegisterController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AppRegisterController');
        $this->assertMatchedRouteName('appregister');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

     
}
