<?php
namespace Callback;

use Callback\Controller\ChatCallbackController;
use Callback\Service\ChatService;
use Mockery;
use Oxzion\Test\ControllerTest;
use PHPUnit\DbUnit\DataSet\DefaultDataSet;
use PHPUnit\DbUnit\DataSet\YamlDataSet;

class ChatCallbackControllerTest extends ControllerTest
{
    public function setUp(): void
    {
        $this->loadConfig();
        parent::setUp();
    }
    public function getDataSet()
    {
        // return new DefaultDataSet();
        
        $dataset = new YamlDataSet(dirname(__FILE__) . "/../Dataset/User.yml");
        return $dataset;
    }

    private function getMockRestClientForChatService()
    {
        $chatService = $this->getApplicationServiceLocator()->get(Service\ChatService::class);
        $mockRestClient = Mockery::mock('Oxzion\Utils\RestClient');
        $chatService->setRestClient($mockRestClient);
        return $mockRestClient;
    }

    protected function setDefaultAsserts()
    {
        $this->assertModuleName('Callback');
        $this->assertControllerName(ChatcallbackController::class); // as specified in router's controller name alias
        $this->assertControllerClass('ChatcallbackController');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    }

    public function testAddAccount()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['accountName' => 'Teams-1', 'status' => 'Active'];
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $mockRestClient->expects('postWithHeader')->with("api/v4/teams", array("name" => "teams1", "display_name" => "teams1", "type" => "O"), Mockery::any())->once()->andReturn(array("body" => json_encode(array("name" => "teams-1", "display_name" => "teams-1"))));
        }
        $this->dispatch('/callback/chat/addaccount', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Callback');
        $this->assertControllerName(ChatcallbackController::class); // as specified in router's controller name alias
        $this->assertControllerClass('ChatcallbackController');
        $this->assertMatchedRouteName('addcallback');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testAddAccountAlreadyExists()
    {
        $data = ['accountName' => 'Teams-1', 'status' => 'Active'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
            $mockRestClient->expects('postWithHeader')->with("api/v4/teams", array("name" => 'teams1', "display_name" => 'teams1', "type" => 'O'), Mockery::any())->once()->andThrow($exception);
        }
        $this->dispatch('/callback/chat/addaccount', 'POST', $data);
        $this->assertResponseStatusCode(400);
        $this->assertMatchedRouteName('addcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testAddAccountNotFound()
    {
        $data = ['status' => 'Active'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
            $mockRestClient->expects('postWithHeader')->with("api/v4/teams", array("status" => "Active"), Mockery::any())->once()->andThrow($exception);
        }
        $this->dispatch('/callback/chat/addaccount', 'POST', $data);
        $this->assertResponseStatusCode(400);
        $this->assertMatchedRouteName('addcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testUpdateAccount()
    {
        $data = ['new_accountName' => 'new-oxzion1', 'old_accountName' => 'teams-1', 'status' => 'Active'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $mockRestClient->expects('get')->with("api/v4/teams/name/teams1", array(), Mockery::any())->once()->andReturn(json_encode(array("name" => "teams1", "display_name" => 'teams1', "id" => 121)));
            $mockRestClient->expects('put')->with("api/v4/teams/121", array("name" => 'newoxzion1', "display_name" => 'newoxzion1', "id" => 121), Mockery::any())->once()->andReturn(json_encode(array('name' => "newoxzion1", "display_name" => 'newoxzion1')));
        }

        $this->dispatch('/callback/chat/updateaccount', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertMatchedRouteName('updatecallback');
        $this->assertEquals($content['status'], 'success');
    }

    public function accountNameNotExists()
    {
        $data = ['accountName' => 'Team Vantage123', 'status' => 'Active'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
            $mockRestClient->expects('get')->with("api/v4/teams/name/teams1", array(), Mockery::any())->once()->andReturn(json_encode(array("name" => "teams1", "display_name" => 'teams1', "id" => 121)));
            $mockRestClient->expects('put')->with("api/v4/teams/121", array("id" => 121), Mockery::any())->once()->andThrow($exception);
        }
        $this->dispatch('/callback/chat/updateaccount', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->assertMatchedRouteName('updatecallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }
    // // No mock test
    public function testUpdateAccountOldNameNotExists()
    {
        $data = ['accountName' => 'Vantage Vantage', 'status' => 'Active'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/chat/updateaccount', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->assertMatchedRouteName('updatecallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testAddUserToAccountBothExists()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => $this->managerUser, 'accountName' => 'teams-1', 'status' => 'Active'];
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $mockRestClient->expects('get')->with("api/v4/teams/name/teams1", array(), Mockery::any())->once()->andReturn(json_encode(array('name' => "teamoxzion", "display_name" => 'teamoxzion', "id" => 121)));
            $mockRestClient->expects('get')->with("api/v4/users/username/" . $this->managerUser, array(), Mockery::any())->once()->andReturn(json_encode(array('name' => $this->managerUser, "id" => 1)));
            $mockRestClient->expects('postWithHeader')->with("api/v4/teams/121/members", array('team_id' => 121, 'user_id' => 1), Mockery::any())->once()->andReturn(array("body" => json_encode(array("team_id" => 121, "user_id" => 1, "roles" => "team_user"))));
        }

        $this->dispatch('/callback/chat/adduser', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('addusercallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testAddUserToAccountForNewUser()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => 'deepak', 'accountName' => 'Teams 1', 'status' => 'Active'];
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $request = Mockery::Mock('Psr\Http\Message\RequestInterface');
            $response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
            $response->expects('getStatusCode')->andReturn(404);
            $mockRestClient->expects('get')->with("api/v4/teams/name/teams1", array(), Mockery::any())->once()->andReturn(json_encode(array('name' => "teamoxzion", "display_name" => 'teamoxzion', "id" => 121)));
            $mockRestClient->expects('get')->with("api/v4/users/username/deepak", array(), Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('"id" : "store.sql_user.get_by_username.app_error"', $request, $response));
            $mockRestClient->expects('postWithHeader')->with("api/v4/users", array("email" => "deepak@gmail.com", "username" => "deepak", "first_name" => "deepak", "password" => md5('deepak')), Mockery::any())->once()->andReturn(array("body" => json_encode(array("id" => 2, "username" => "deepak", "email" => "deepak@gmail.com", "first_name" => "deepak"))));
            $mockRestClient->expects('put')->with('api/v4/users/404', array("first_name" => 'Deepak','last_name' => 'S','email' => 'deepak@eox.com'), Mockery::any())->once()->andReturn(array("body" => json_encode(array("id" => 2, "first_name" => 'Deepak','last_name' => 'S','email' => 'deepak@eox.com'))));
            $mockRestClient->expects('postWithHeader')->with("api/v4/teams/121/members", array('team_id' => 121, 'user_id' => 2), Mockery::any())->once()->andReturn(array("body" => json_encode(array("team_id" => 121, "user_id" => 2, "roles" => "team_user"))));
        }
        $this->dispatch('/callback/chat/adduser', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('addusercallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testAddUserToAccountNetworkIssue()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => 'ramya', 'accountName' => 'Teams 1', 'status' => 'Active'];
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $request = Mockery::Mock('Psr\Http\Message\RequestInterface');
            $response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
            $response->expects('getStatusCode')->andReturn(500);
            $mockRestClient->expects('get')->with("api/v4/teams/name/teams1", array(), Mockery::any())->once()->andReturn(json_encode(array('name' => "teamoxzion", "display_name" => 'teamoxzion', "id" => 121)));
            $mockRestClient->expects('get')->with("api/v4/users/username/ramya", array(), Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('', $request, $response));
        }
        if (enableMattermost == 1) {
            $this->assertTrue(true);
        } else {
            $this->dispatch('/callback/chat/adduser', 'POST', $data);
            $this->assertResponseStatusCode(500);
            $this->setDefaultAsserts();
            $this->assertMatchedRouteName('addusercallback');
            $content = (array) json_decode($this->getResponse()->getContent(), true);
            $this->assertEquals($content['status'], 'error');
        }
    }

    public function testAddUserToAccountForNewAccount()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => $this->managerUser, 'accountName' => 'Teams new1', 'status' => 'Active'];
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $request = Mockery::Mock('Psr\Http\Message\RequestInterface');
            $response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
            $response->expects('getStatusCode')->andReturn(404);
            $mockRestClient->expects('get')->with("api/v4/teams/name/teamsnew1", array(), Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('"id" : "store.sql_team.get_by_name.app_error"', $request, $response));
            $mockRestClient->expects('postWithHeader')->with("api/v4/teams", array('name' => "teamsnew1", 'display_name' => "teamsnew1", 'type' => 'O'), Mockery::any())->once()->andReturn(array("body" => json_encode(array("id" => 125, "name" => "teamsnew1", "display_name" => "teamsnew1"))));
            $mockRestClient->expects('get')->with("api/v4/users/username/" . $this->managerUser, array(), Mockery::any())->once()->andReturn(json_encode(array('name' => $this->managerUser, "id" => 1)));
            $mockRestClient->expects('postWithHeader')->with("api/v4/teams/125/members", array('team_id' => 125, 'user_id' => 1), Mockery::any())->once()->andReturn(array("body" => json_encode(array("team_id" => 125, "user_id" => 1, "roles" => "team_user"))));
        }
        $this->dispatch('/callback/chat/adduser', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('addusercallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testAddUserToAccountThatDoesNotExist()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => $this->managerUser, 'accountName' => 'Orgo organization', 'status' => 'Active'];
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $request = Mockery::Mock('Psr\Http\Message\RequestInterface');
            $response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
            $response->expects('getStatusCode')->andReturn(500);
            $mockRestClient->expects('get')->with("api/v4/teams/name/orgoorganization", array(), Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('', $request, $response));
        }
        if (enableMattermost == 1) {
            $this->assertTrue(true);
        } else {
            $this->dispatch('/callback/chat/adduser', 'POST', $data);
            $this->assertResponseStatusCode(500);
            $this->setDefaultAsserts();
            $this->assertMatchedRouteName('addusercallback');
            $content = (array) json_decode($this->getResponse()->getContent(), true);
            $this->assertEquals($content['status'], 'error');
        }
    }

    public function testAddUserToAccountUserAndAccountNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['status' => 'Active'];
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
            $mockRestClient->expects('postWithHeader')->with("api/v4/teams", array("status" => "Active"), Mockery::any())->once()->andThrow($exception);
        }
        $this->dispatch('/callback/chat/adduser', 'POST', $data);
        $this->assertResponseStatusCode(400);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('addusercallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testCreateChannel()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['accountName' => 'teams-1', 'teamname' => 'Channel Private-1', 'status' => 'Active'];
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $mockRestClient->expects('get')->with("api/v4/teams/name/teams1", array(), Mockery::any())->once()->andReturn(json_encode(array('name' => "teams1", "display_name" => 'teams1', "id" => 121)));
            $mockRestClient->expects('postWithHeader')->with("api/v4/channels", array('team_id' => 121, 'name' => "channelprivate1", 'display_name' => "channelprivate1", 'type' => 'P'), Mockery::any())->once()->andReturn(array("body" => json_encode(array("id" => 234, "name" => "channelprivate1", "display_name" => "channelprivate1", "team_id" => 121))));
        }

        $this->dispatch('/callback/chat/createchannel', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('createchannelcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testCreateChannelForNewAccount()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['accountName' => 'New Org-1', 'teamname' => 'New Channel-Test', 'status' => 'Active'];
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $request = Mockery::Mock('Psr\Http\Message\RequestInterface');
            $response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
            $response->expects('getStatusCode')->andReturn(404);
            $mockRestClient->expects('get')->with("api/v4/teams/name/neworg1", array(), Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('"id" : "store.sql_team.get_by_name.app_error"', $request, $response));
            $mockRestClient->expects('postWithHeader')->with("api/v4/teams", array('name' => "neworg1", 'display_name' => "neworg1", 'type' => 'O'), Mockery::any())->once()->andReturn(array("body" => json_encode(array("id" => 130, "name" => "neworg1", "display_name" => "neworg1"))));
            $mockRestClient->expects('postWithHeader')->with("api/v4/channels", array('team_id' => 130, 'name' => "newchanneltest", 'display_name' => "newchanneltest", 'type' => 'P'), Mockery::any())->once()->andReturn(array("body" => json_encode(array("id" => 250, "name" => "newchanneltest", "display_name" => "newchanneltest", "team_id" => 130))));
        }
        $this->dispatch('/callback/chat/createchannel', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('createchannelcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testCreateChannelNetworkIssue()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['accountName' => 'Orgo organization', 'teamname' => 'New Channel-Test', 'status' => 'Active'];
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $request = Mockery::Mock('Psr\Http\Message\RequestInterface');
            $response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
            $response->expects('getStatusCode')->andReturn(500);
            $mockRestClient->expects('get')->with("api/v4/teams/name/orgoorganization", array(), Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('', $request, $response));
        }
        if (enableMattermost == 1) {
            $this->assertTrue(true);
        } else {
            $this->dispatch('/callback/chat/createchannel', 'POST', $data);
            $this->assertResponseStatusCode(500);
            $this->setDefaultAsserts();
            $this->assertMatchedRouteName('createchannelcallback');
            $content = (array) json_decode($this->getResponse()->getContent(), true);
            $this->assertEquals($content['status'], 'error');
        }
    }

    public function testCreateChannelNameNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['accountName' => 'Teams 1', 'status' => 'Active'];
        $this->setJsonContent(json_encode($data));
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
            $mockRestClient->expects('get')->with("api/v4/teams/name/teams1", array(), Mockery::any())->once()->andReturn(json_encode(array('name' => "teams1", "display_name" => 'teams1', "id" => 121)));
            $mockRestClient->expects('postWithHeader')->with("api/v4/channels", array('team_id' => 121, 'type' => 'P'), Mockery::any())->once()->andThrows($exception);
        }
        $this->dispatch('/callback/chat/createchannel', 'POST', $data);
        $this->assertResponseStatusCode(400);
        $this->assertMatchedRouteName('createchannelcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }
    // No mock test
    public function testCreateChannelTeamNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['teamname' => 'Private Channel 1', 'status' => 'Active'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/chat/createchannel', 'POST', $data);
        $this->assertResponseStatusCode(400);
        $this->assertMatchedRouteName('createchannelcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testUpdateChannel()
    {
        $data = ['new_teamname' => 'New Channel Private 1', 'old_teamname' => 'Channel private-1', 'accountName' => 'teams 1', 'status' => 'Active'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $mockRestClient->expects('get')->with("api/v4/teams/name/teams1", array(), Mockery::any())->once()->andReturn(json_encode(array('name' => "teams1", "display_name" => 'teams1', "id" => 121)));
            $mockRestClient->expects('get')->with("api/v4/teams/121/channels/name/channelprivate1", array(), Mockery::any())->once()->andReturn(json_encode(array("id" => 234, "name" => "channelprivate1", "display_name" => "channelprivate1", "team_id" => 121)));
            $mockRestClient->expects('put')->with("api/v4/channels/234", array('id' => 234, 'name' => "newchannelprivate1", 'display_name' => "newchannelprivate1"), Mockery::any())->once()->andReturn(json_encode(array("id" => 234, "name" => "newchannelprivate1", "display_name" => "newchannelprivate1", "team_id" => 121)));
        }
        $this->dispatch('/callback/chat/updatechannel', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->assertMatchedRouteName('updatechannelcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testUpdateChannelNewNameNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['old_teamname' => 'Channel Private 1', 'accountName' => 'Teams 1', 'status' => 'Active'];
        $this->setJsonContent(json_encode($data));
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
            $mockRestClient->expects('get')->with("api/v4/teams/name/teams1", array(), Mockery::any())->once()->andReturn(json_encode(array('name' => "teams1", "display_name" => 'teams1', "id" => 121)));
            $mockRestClient->expects('get')->with("api/v4/teams/121/channels/name/channelprivate1", array(), Mockery::any())->once()->andReturn(json_encode(array("id" => 234, "name" => "channelprivate1", "display_name" => "channelprivate1", "team_id" => 121)));
            $mockRestClient->expects('put')->with("api/v4/channels/234", array('id' => 234), Mockery::any())->once()->andThrow($exception);
        }
        $this->dispatch('/callback/chat/updatechannel', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->assertMatchedRouteName('updatechannelcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }
    // No mock test
    public function testUpdateChannelNameNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['new_teamname' => 'Oh myh god', 'accountName' => 'Testing Team', 'status' => 'Active'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/chat/updatechannel', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->assertMatchedRouteName('updatechannelcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testAddUserToChannel()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => $this->managerUser, 'accountName' => 'Teams 1', 'teamname' => 'New Channel Private 1', 'status' => 'Active'];
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $mockRestClient->expects('get')->with("api/v4/teams/name/teams1", array(), Mockery::any())->once()->andReturn(json_encode(array('name' => "teams1", "display_name" => 'teams1', "id" => 121)));
            $mockRestClient->expects('get')->with("api/v4/teams/121/channels/name/newchannelprivate1", array(), Mockery::any())->once()->andReturn(json_encode(array("id" => 234, "name" => "newchannelprivate1", "display_name" => "newchannelprivate1", "team_id" => 121)));
            $mockRestClient->expects('get')->with("api/v4/users/username/" . $this->managerUser, array(), Mockery::any())->once()->andReturn(json_encode(array("id" => 1, "name" => $this->managerUser)));
            $mockRestClient->expects('get')->with("api/v4/teams/121/members/1", array(), Mockery::any())->once()->andReturn(json_encode(array("team_id" => 121, "user_id" => 1)));
            $mockRestClient->expects('postWithHeader')->with("api/v4/channels/234/members", array('user_id' => 1), Mockery::any())->once()->andReturn(array("body" => json_encode(array('channel_id' => 234, "user_id" => 1))));
        }

        $this->dispatch('/callback/chat/addusertochannel', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('addusertochannelcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testAddUserToChannelCreateChannel()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => $this->managerUser, 'accountName' => 'Teams 1', 'teamname' => 'Private1 Private', 'status' => 'Active'];
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $request = Mockery::Mock('Psr\Http\Message\RequestInterface');
            $response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
            $response->expects('getStatusCode')->andReturn(404);
            $mockRestClient->expects('get')->with("api/v4/teams/name/teams1", array(), Mockery::any())->once()->andReturn(json_encode(array('name' => "teams1", "display_name" => 'teams1', "id" => 121)));
            $mockRestClient->expects('get')->with("api/v4/teams/121/channels/name/private1private", array(), Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('"id" : "store.sql_channel.get_by_name.missing.app_error"', $request, $response));
            $mockRestClient->expects('postWithHeader')->with("api/v4/channels", array('team_id' => 121, 'name' => "private1private", 'display_name' => "private1private", 'type' => 'P'), Mockery::any())->once()->andReturn(array("body" => json_encode(array('id' => 260, "team_id" => 121))));
            $mockRestClient->expects('get')->with("api/v4/users/username/" . $this->managerUser, Mockery::any(), Mockery::any())->once()->andReturn(json_encode(array("id" => 1, "name" => $this->managerUser)));
            $mockRestClient->expects('get')->with("api/v4/teams/121/members/1", array(), Mockery::any())->once()->andReturn(json_encode(array("team_id" => 121, "user_id" => 1)));
            $mockRestClient->expects('postWithHeader')->with("api/v4/channels/260/members", array('user_id' => 1), Mockery::any())->once()->andReturn(array("body" => json_encode(array('channel_id' => 260, "user_id" => 1))));
        }
        $this->dispatch('/callback/chat/addusertochannel', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('addusertochannelcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testAddUserToChannelNetworkIssue()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => $this->managerUser, 'accountName' => 'Teams 1', 'teamname' => 'Channel Chan', 'status' => 'Active'];
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $request = Mockery::Mock('Psr\Http\Message\RequestInterface');
            $response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
            $response->expects('getStatusCode')->andReturn(500);
            $mockRestClient->expects('get')->with("api/v4/teams/name/teams1", array(), Mockery::any())->once()->andReturn(json_encode(array('name' => "teams1", "display_name" => 'teams1', "id" => 121)));
            $mockRestClient->expects('get')->with("api/v4/teams/121/channels/name/channelchan", array(), Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('', $request, $response));
        }
        if (enableMattermost == 1) {
            $this->assertTrue(true);
        } else {
            $this->dispatch('/callback/chat/addusertochannel', 'POST', $data);
            $this->assertResponseStatusCode(500);
            $this->setDefaultAsserts();
            $this->assertMatchedRouteName('addusertochannelcallback');
            $content = (array) json_decode($this->getResponse()->getContent(), true);
            $this->assertEquals($content['status'], 'error');
        }
    }

    public function testAddUserToChannelCreateTeam()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => $this->managerUser, 'accountName' => 'Boscos Team', 'teamname' => 'Private1 Private', 'status' => 'Active'];
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $request = Mockery::Mock('Psr\Http\Message\RequestInterface');
            $response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
            $response->expects('getStatusCode')->andReturn(404);
            $mockRestClient->expects('get')->with("api/v4/teams/name/boscosteam", array(), Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('"id" : "store.sql_team.get_by_name.app_error"', $request, $response));
            $mockRestClient->expects('postWithHeader')->with("api/v4/teams", array('name' => "boscosteam", 'display_name' => "boscosteam", 'type' => 'O'), Mockery::any())->andReturn(array("body" => json_encode(array('id' => 170, "name" => "boscosteam", "display_name" => "boscosteam"))));
            $mockRestClient->expects('get')->with("api/v4/teams/170/channels/name/private1private", array(), Mockery::any())->once()->andReturn(json_encode(array("id" => 270, "name" => "private1private", "display_name" => "private1private", "team_id" => 170)));
            $mockRestClient->expects('postWithHeader')->with("api/v4/channels", array('team_id' => 121, 'name' => "private1private", 'display_name' => "private1private", 'type' => 'P'), Mockery::any())->once()->andReturn(array("body" => json_encode(array('id' => 270, "team_id" => 170))));
            $mockRestClient->expects('get')->with("api/v4/users/username/" . $this->managerUser, array(), Mockery::any())->once()->andReturn(json_encode(array("id" => 1, "name" => $this->managerUser)));
            $mockRestClient->expects('get')->with("api/v4/teams/170/members/1", array(), Mockery::any())->once()->andReturn(json_encode(array("team_id" => 170, "user_id" => 1)));
            $mockRestClient->expects('postWithHeader')->with("api/v4/channels/270/members", array('user_id' => 1), Mockery::any())->once()->andReturn(array("body" => json_encode(array('channel_id' => 270, "user_id" => 1))));
        }
        $this->dispatch('/callback/chat/addusertochannel', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('addusertochannelcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testAddUserToChannelTeamNotFoundBeczNetworkIssue()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => $this->managerUser, 'accountName' => 'PES Team', 'teamname' => 'Private1 Private', 'status' => 'Active'];
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $request = Mockery::Mock('Psr\Http\Message\RequestInterface');
            $response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
            $response->expects('getStatusCode')->andReturn(500);
            $mockRestClient->expects('get')->with("api/v4/teams/name/pesteam", array(), Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('', $request, $response));
        }
        if (enableMattermost == 1) {
            $this->assertTrue(true);
        } else {
            $this->dispatch('/callback/chat/addusertochannel', 'POST', $data);
            $this->assertResponseStatusCode(500);
            $this->setDefaultAsserts();
            $this->assertMatchedRouteName('addusertochannelcallback');
            $content = (array) json_decode($this->getResponse()->getContent(), true);
            $this->assertEquals($content['status'], 'error');
        }
    }

    public function testAddUserToChannelCreateUser()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => 'Girly', 'accountName' => 'Teams 1', 'teamname' => 'Private1 Private', 'status' => 'Active'];
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $request = Mockery::Mock('Psr\Http\Message\RequestInterface');
            $response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
            $response->expects('getStatusCode')->andReturn(404);
            $mockRestClient->expects('get')->with("api/v4/teams/name/teams1", array(), Mockery::any())->once()->andReturn(json_encode(array('name' => "teams1", "display_name" => 'teams1', "id" => 121)));
            $mockRestClient->expects('get')->with("api/v4/teams/121/channels/name/private1private", array(), Mockery::any())->once()->andReturn(json_encode(array("id" => 260, "name" => "private1private", "display_name" => "private1private", "team_id" => 121)));
            $mockRestClient->expects('get')->with("api/v4/users/username/girly", array(), Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('"id" : "store.sql_user.get_by_username.app_error"', $request, $response));
            $mockRestClient->expects('postWithHeader')->with("api/v4/users", array('email' => "girly@gmail.com", 'username' => "girly", 'first_name' => "girly", 'password' => md5('girly')), Mockery::any())->once()->andReturn(array("body" => json_encode(array("id" => 3, 'email' => "girly@gmail.com", 'username' => "girly", 'first_name' => "girly"))));
            $mockRestClient->expects('put')->with('api/v4/users/404', array("first_name" => 'Girly Raj','last_name' => 'K','email' => 'girly@yhoo.com'), Mockery::any())->once()->andReturn(array("body" => json_encode(array("id" => 3, "first_name" => 'Girly Raj','last_name' => 'K','email' => 'girly@yhoo.com'))));
            $mockRestClient->expects('get')->with("api/v4/teams/121/members/3", array(), Mockery::any())->once()->andReturn(json_encode(array("team_id" => 121, "user_id" => 3)));
            $mockRestClient->expects('postWithHeader')->with("api/v4/channels/260/members", array('user_id' => 3), Mockery::any())->once()->andReturn(array("body" => json_encode(array('channel_id' => 260, "user_id" => 3))));
        }
        $this->dispatch('/callback/chat/addusertochannel', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('addusertochannelcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testAddUserToChannelUserNotCreatedBeczNetworkIssue()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => 'Boyish', 'accountName' => 'Teams 1', 'teamname' => 'Private1 Private', 'status' => 'Active'];
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $request = Mockery::Mock('Psr\Http\Message\RequestInterface');
            $response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
            $response->expects('getStatusCode')->andReturn(500);
            $mockRestClient->expects('get')->with("api/v4/teams/name/teams1", array(), Mockery::any())->once()->andReturn(json_encode(array('name' => "teams1", "display_name" => 'teams1', "id" => 121)));
            $mockRestClient->expects('get')->with("api/v4/teams/121/channels/name/private1private", array(), Mockery::any())->once()->andReturn(json_encode(array("id" => 260, "name" => "private1private", "display_name" => "private1private", "team_id" => 121)));
            $mockRestClient->expects('get')->with("api/v4/users/username/boyish", array(), Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('', $request, $response));
        }
        if (enableMattermost == 1) {
            $this->assertTrue(true);
        } else {
            $this->dispatch('/callback/chat/addusertochannel', 'POST', $data);
            $this->assertResponseStatusCode(500);
            $this->setDefaultAsserts();
            $this->assertMatchedRouteName('addusertochannelcallback');
            $content = (array) json_decode($this->getResponse()->getContent(), true);
            $this->assertEquals($content['status'], 'error');
        }
    }

    // No mock test
    public function testAddUserToChannelDataNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['accountName' => 'Raks Team', 'teamname' => 'Channel Crrate Private', 'status' => 'Active'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/chat/addusertochannel', 'POST', $data);
        $this->assertResponseStatusCode(400);
        $this->assertMatchedRouteName('addusertochannelcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testRemoveUserFromChannel()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => $this->managerUser, 'accountName' => 'Teams 1', 'teamname' => 'New Channel Private 1', 'status' => 'Active'];
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $mockRestClient->expects('get')->with("api/v4/teams/name/teams1", array(), Mockery::any())->once()->andReturn(json_encode(array('name' => "teams1", "display_name" => 'teams1', "id" => 121)));
            $mockRestClient->expects('get')->with("api/v4/teams/121/channels/name/newchannelprivate1", array(), Mockery::any())->once()->andReturn(json_encode(array("id" => 234, "name" => "newchannelprivate1", "display_name" => "newchannelprivate1", "team_id" => 121)));
            $mockRestClient->expects('get')->with("api/v4/users/username/" . $this->managerUser, array(), Mockery::any())->once()->andReturn(json_encode(array("id" => 1, "name" => $this->managerUser)));
            $mockRestClient->expects('get')->with("api/v4/teams/121/members/1", array(), Mockery::any())->once()->andReturn(json_encode(array("team_id" => 121, "user_id" => 1)));
            $mockRestClient->expects('get')->with("api/v4/channels/234/members/1", array(), Mockery::any())->once()->andReturn(json_encode(array("channel_id" => 234, "user_id" => 1)));
            $mockRestClient->expects('delete')->with("api/v4/channels/234/members/1", array(), Mockery::any())->once()->andReturn(json_encode(array("status" => "OK")));
        }
        $this->dispatch('/callback/chat/removeuserfromchannel', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('removeuserfromchannelcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }
    // No mock test
    public function testRemoveUserFromChannelUserNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['accountName' => 'Raks Team', 'teamname' => 'Channel Crrate Private', 'status' => 'Active'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/chat/removeuserfromchannel', 'POST', $data);
        $this->assertResponseStatusCode(400);
        $this->assertMatchedRouteName('removeuserfromchannelcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testRemoveUserFromChannelUserNotInChannel()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => $this->managerUser, 'accountName' => 'Teams 1', 'teamname' => 'Private1 Private', 'status' => 'Active'];
        $this->setJsonContent(json_encode($data));
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
            $mockRestClient->expects('get')->with("api/v4/teams/name/teams1", array(), Mockery::any())->once()->andReturn(json_encode(array('name' => "teams1", "display_name" => 'teams1', "id" => 121)));
            $mockRestClient->expects('get')->with("api/v4/teams/121/channels/name/private1private", array(), Mockery::any())->once()->andReturn(json_encode(array("id" => 260, "name" => "private1private", "display_name" => "private1private", "team_id" => 121)));
            $mockRestClient->expects('get')->with("api/v4/users/username/" . $this->managerUser, array(), Mockery::any())->once()->andReturn(json_encode(array("id" => 1, "name" => $this->managerUser)));
            $mockRestClient->expects('get')->with("api/v4/teams/121/members/1", array(), Mockery::any())->once()->andReturn(json_encode(array("team_id" => 121, "user_id" => 1)));
            $mockRestClient->expects('get')->with("api/v4/channels/260/members/1", array(), Mockery::any())->once()->andThrow($exception);
        }
        $this->dispatch('/callback/chat/removeuserfromchannel', 'POST', $data);
        $this->assertResponseStatusCode(400);
        $this->assertMatchedRouteName('removeuserfromchannelcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testRemoveUserFromChannelNotCreatedIssue()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => $this->managerUser, 'accountName' => 'Teams 1', 'teamname' => 'Payyannur', 'status' => 'Active'];
        $this->setJsonContent(json_encode($data));
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $request = Mockery::Mock('Psr\Http\Message\RequestInterface');
            $response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
            $response->expects('getStatusCode')->andReturn(500);
            $mockRestClient->expects('get')->with("api/v4/teams/name/teams1", array(), Mockery::any())->once()->andReturn(json_encode(array('name' => "teams1", "display_name" => 'teams1', "id" => 121)));
            $mockRestClient->expects('get')->with("api/v4/teams/121/channels/name/payyannur", array(), Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('', $request, $response));
        }
        if (enableMattermost == 1) {
            $this->assertTrue(true);
        } else {
            $this->dispatch('/callback/chat/removeuserfromchannel', 'POST', $data);
            $this->assertResponseStatusCode(500);
            $this->assertMatchedRouteName('removeuserfromchannelcallback');
            $content = (array) json_decode($this->getResponse()->getContent(), true);
            $this->assertEquals($content['status'], 'error');
        }
    }

    public function testDeleteChannel()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['accountName' => 'teams-1', 'teamname' => 'New Channel Private 1', 'status' => 'Active'];
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $mockRestClient->expects('get')->with("api/v4/teams/name/teams1", array(), Mockery::any())->once()->andReturn(json_encode(array('name' => "teams1", "display_name" => 'teams1', "id" => 121)));
            $mockRestClient->expects('get')->with("api/v4/teams/121/channels/name/newchannelprivate1", array(), Mockery::any())->once()->andReturn(json_encode(array("id" => 234, "name" => "newchannelprivate1", "display_name" => 'newchannelprivate1', "team_id" => 121)));
            $mockRestClient->expects('delete')->with("api/v4/channels/234", array(), Mockery::any())->once()->andReturn(json_encode(array("status" => "OK")));
        }

        $this->dispatch('/callback/chat/deletechannel', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('deletechannelcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }
    // no mock test
    public function testDeleteChannelNameNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['accountName' => 'Raks Team', 'status' => 'Active'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/chat/deletechannel', 'POST', $data);
        $this->assertResponseStatusCode(400);
        $this->assertMatchedRouteName('deletechannelcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }
    // no mock test
    public function testDeleteChannelTeamNameNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['teamname' => 'off-topic', 'status' => 'Active'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/chat/deletechannel', 'POST', $data);
        $this->assertResponseStatusCode(400);
        $this->assertMatchedRouteName('deletechannelcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testDeleteChannelNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['accountName' => 'Teams 1', 'teamname' => 'Privatecs 3', 'status' => 'Active'];
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
            $request = Mockery::Mock('Psr\Http\Message\RequestInterface');
            $response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
            $response->expects('getStatusCode')->andReturn(500);
            $mockRestClient->expects('get')->with("api/v4/teams/name/teams1", array(), Mockery::any())->once()->andReturn(json_encode(array('name' => "teams1", "display_name" => 'teams1', "id" => 121)));
            $mockRestClient->expects('get')->with("api/v4/teams/121/channels/name/privatecs3", array(), Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('', $request, $response));
        }
        if (enableMattermost == 1) {
            $this->assertTrue(true);
        } else {
            $this->dispatch('/callback/chat/deletechannel', 'POST', $data);
            $this->assertResponseStatusCode(500);
            $this->setDefaultAsserts();
            $this->assertMatchedRouteName('deletechannelcallback');
            $content = (array) json_decode($this->getResponse()->getContent(), true);
            $this->assertEquals($content['status'], 'error');
        }
    }

    public function testRemoveUserFromAccount()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => $this->managerUser, 'accountName' => 'teams-1', 'status' => 'Active'];
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $mockRestClient->expects('get')->with("api/v4/users/username/" . $this->managerUser, array(), Mockery::any())->once()->andReturn(json_encode(array('name' => $this->managerUser, "id" => 1)));
            $mockRestClient->expects('get')->with("api/v4/teams/name/teams1", array(), Mockery::any())->once()->andReturn(json_encode(array('name' => "teams1", "display_name" => 'teams1', "id" => 121)));
            $mockRestClient->expects('delete')->with("api/v4/teams/121/members/1", array(), Mockery::any())->once()->andReturn(json_encode(array("status" => "OK")));
        }

        $this->dispatch('/callback/chat/removeuser', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('removeusercallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testRemoveUserFromAccountUserNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => 'laxmi', 'accountName' => 'teams-1', 'status' => 'Active'];
        $this->setJsonContent(json_encode($data));
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $request = Mockery::Mock('Psr\Http\Message\RequestInterface');
            $response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
            $response->expects('getStatusCode')->andReturn(500);
            $mockRestClient->expects('get')->with("api/v4/users/username/laxmi", array(), Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('', $request, $response));
        }
        if (enableMattermost == 1) {
            $this->assertTrue(true);
        } else {
            $this->dispatch('/callback/chat/removeuser', 'POST', $data);
            $this->assertResponseStatusCode(500);
            $this->assertMatchedRouteName('removeusercallback');
            $content = (array) json_decode($this->getResponse()->getContent(), true);
            $this->assertEquals($content['status'], 'error');
        }
    }

    // No mock test
    public function testRemoveUserFromAccountDataNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['accountName' => 'Raks Team', 'status' => 'Active'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/chat/removeuser', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->assertMatchedRouteName('removeusercallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }
    // No mock test
    public function testRemoveUserFromAccountNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => $this->adminUser, 'status' => 'Active'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/chat/removeuser', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->assertMatchedRouteName('removeusercallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testRemoveUserFromAccountUserNotInTeam()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['username' => $this->managerUser, 'accountName' => 'Raks Team', 'status' => 'Active'];
        $this->setJsonContent(json_encode($data));
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
            $mockRestClient->expects('get')->with("api/v4/users/username/" . $this->managerUser, array(), Mockery::any())->once()->andReturn(json_encode(array('name' => $this->managerUser, "id" => 1)));
            $mockRestClient->expects('get')->with("api/v4/teams/name/raksteam", array(), Mockery::any())->once()->andReturn(json_encode(array('name' => "raksteam", "display_name" => 'raksteam', "id" => 175)));
            $mockRestClient->expects('delete')->with("api/v4/teams/175/members/1", array(), Mockery::any())->once()->andThrow($exception);
        }
        $this->dispatch('/callback/chat/removeuser', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->assertMatchedRouteName('removeusercallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testDeleteAccount()
    {
        $data = ['accountName' => 'teams-1', 'status' => 'Active'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $mockRestClient->expects('postWithHeader')->with("api/v4/teams/search", array('term' => "teams1"), Mockery::any())->once()->andReturn(array("body" => json_encode(array(array("name" => "teams1", "display_name" => "teams1", "id" => 121)))));
            $mockRestClient->expects('delete')->with("api/v4/teams/121", array('permanent' => 'false'), Mockery::any())->once()->andReturn(json_encode(array("status" => "OK")));
        }
        $this->dispatch('/callback/chat/deleteaccount', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->assertMatchedRouteName('deletecallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testDeleteAccountNotFound()
    {
        $data = ['accountName' => 'hmm ok jog', 'status' => 'Active'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
            $mockRestClient->expects('postWithHeader')->with("api/v4/teams/search", array('term' => "hmmokjog"), Mockery::any())->once()->andThrow($exception);
        }
        $this->dispatch('/callback/chat/deleteaccount', 'POST', $data);
        $this->assertResponseStatusCode(400);
        $this->assertMatchedRouteName('deletecallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testSaveBotWithoutName()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['appName' => ''];
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
            $mockRestClient->expects('postWithHeader')->with("api/v4/bots", array("appName" => ""), Mockery::any())->once()->andThrow($exception);
        }
        $this->dispatch('/callback/chat/savebot', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(400);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('savebotcallback');
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Bot Name is missing');
    }

    public function testSaveBotCreation()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['appName' => 'testbotuser'];
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
            $mockRestClient->expects('get')->with("api/v4/users/username/testbotuser", array(), Mockery::any())->once()->andReturn($exception);
            $mockRestClient->expects('postWithHeader')->with("api/v4/bots", array("username" => "testbotuser", "display_name" => "testbotuser", "description" => "BOT for testbotuser"), Mockery::any())->once()->andReturn(array("body" => json_encode(array("username" => "testbotuser", "display_name" => "testbotuser"))));
        }
        $this->dispatch('/callback/chat/savebot', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('savebotcallback');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['username'], $data['appName']);
        $this->assertEquals($content['data']['display_name'], $data['appName']);
    }

    public function testCreateBotNotFound()
    {
        $data = ['description' => 'A BOT for testing'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
            $mockRestClient->expects('postWithHeader')->with("api/v4/bots", array("description" => "A BOT for testing"), Mockery::any())->once()->andThrow($exception);
        }
        $this->dispatch('/callback/chat/savebot', 'POST', $data);
        $this->assertResponseStatusCode(400);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('savebotcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Bot Name is missing');
    }

    public function testSaveBotUpdation()
    {
        $data = ['appName' => 'testbotuser', 'displayName' => 'Test BOT User' , 'delete_at' => 0];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $mockRestClient->expects('get')->with("api/v4/users/username/testbotuser", array(), Mockery::any())->once()->andReturn(json_encode(array('name' => 'testbotuser', "id" => 111, "delete_at" => 0)));
            $mockRestClient->expects('put')->with("api/v4/bots/111", array("display_name" => 'Test BOT User'), Mockery::any())->once()->andReturn(json_encode(array('display_name' => "Test BOT User")));
        }
        $this->dispatch('/callback/chat/savebot', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('savebotcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['display_name'], $data['displayName']);
    }

    public function testSaveBotEnable()
    {
        $data = ['appName' => 'testbotuser', 'displayName' => 'Test BOT User' , 'delete_at' => 191819180];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $mockRestClient->expects('get')->with("api/v4/users/username/testbotuser", array(), Mockery::any())->once()->andReturn(json_encode(array('name' => 'testbotuser', "id" => 111, "delete_at" => 1911910)));
            $mockRestClient->expects('postWithHeader')->with("api/v4/bots/111/enable", array(), Mockery::any())->once()->andReturn(json_encode(array('display_name' => "Test BOT User")));
        }
        $this->dispatch('/callback/chat/savebot', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('savebotcallback');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['display_name'], $data['displayName']);
    }

    public function testSaveBotUpdateWithProfileImage()
    {
        $data = ['appName' => 'testbotuser','delete_at' => 0, 'displayName' => 'Test BOT User', 'profileImage' => __DIR__."/../files/icon.png"];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $mockRestClient->expects('get')->with("api/v4/users/username/testbotuser", array(), Mockery::any())->once()->andReturn(json_encode(array('name' => 'testbotuser', "id" => 111, 'delete_at' => 0)));
            $mockRestClient->expects('put')->with("api/v4/bots/111", array("display_name" => 'Test BOT User'), Mockery::any())->once()->andReturn(json_encode(array('display_name' => "Test BOT User")));
            $mockRestClient->expects('postMultiPart')->with("api/v4/users/111/image", array(), array("image" => __DIR__."/../files/icon.png"), Mockery::any())->once()->andReturn(json_encode(array("status" => "OK")));
        }
        $this->dispatch('/callback/chat/savebot', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('savebotcallback');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['display_name'], $data['displayName']);
    }

    public function testDisableBot()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['appName' => 'testbotuser'];
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
            $mockRestClient->expects('get')->with("api/v4/users/username/testbotuser", array(), Mockery::any())->once()->andReturn(json_encode(array('name' => 'testbotuser', "id" => 111)));
            $mockRestClient->expects('postWithHeader')->with("api/v4/bots/111/disable", array(), Mockery::any())->once()->andReturn(array("body" => json_encode(array("username" => "testbotuser", "display_name" => "testbotuser" , "delete_at" => 2121212121))));
        }
        $this->dispatch('/callback/chat/disablebot', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('disablebotcallback');
        $this->assertEquals($content['status'], 'success');
        $this->assertNotEquals($content['data']['delete_at'], 0);
    }

    public function testDisableBotNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['appName' => 'testbotusernew'];
        if (enableMattermost == 0) {
            $mockRestClient = $this->getMockRestClientForChatService();
            $exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
            $mockRestClient->expects('get')->with("api/v4/users/username/testbotusernew", array(), Mockery::any())->once()->andReturn($exception);
        }
        $this->dispatch('/callback/chat/disablebot', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(400);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('disablebotcallback');
        $this->assertEquals($content['status'], 'error');
    }

    //APP BOT NOTIFICATION TESTS
}
