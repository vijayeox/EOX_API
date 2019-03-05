<?php
namespace Callback;
	
	use Callback\Controller\ChatCallbackController;
	use Oxzion\Test\ControllerTest;
	use PHPUnit\DbUnit\TestCaseTrait;
	use PHPUnit\DbUnit\DataSet\YamlDataSet;
	use Zend\Db\Sql\Sql;
	use Zend\Db\Adapter\Adapter;
	use Oxzion\Utils\FileUtils;
	use PHPUnit\DbUnit\DataSet\DefaultDataSet;
	use Callback\Service\ChatService;
	use Oxzion\Utils\RestClient;
	use Mockery;
	
	class ChatCallbackControllerTest extends ControllerTest{
		
		public function setUp() : void{
			$this->loadConfig();
			parent::setUp();
		}   
		public function getDataSet() {
			return new DefaultDataSet();
		}
		
		private function getMockRestClientForChatService(){
			$chatService = $this->getApplicationServiceLocator()->get(Service\ChatService::class);
			$mockRestClient = Mockery::mock('Oxzion\Utils\RestClient');
			$chatService->setRestClient($mockRestClient);
			return $mockRestClient;
		}
		public function testAddOrg(){
			$this->initAuthToken($this->adminUser);
			$data = ['name' => 'Teams-1','status'=>'Active'];
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();
				$mockRestClient->expects('postWithHeader')->with("api/v4/teams",array("name" => "teams-1","display_name" => "teams-1","type" => "O"), Mockery::any())->once()->andReturn(array("body" => json_encode(array("name"=>"teams-1","display_name" => "teams-1"))));
			}
			$this->dispatch('/callback/chat/addorg', 'POST', $data);
			$this->assertResponseStatusCode(200);
			$this->assertModuleName('Callback');
			$this->assertControllerName(ChatcallbackController::class); // as specified in router's controller name alias
			$this->assertControllerClass('ChatcallbackController');
			$this->assertMatchedRouteName('addcallback');
			$this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'success');
		}
		
		public function testAddOrgAlreadyExists(){
			$data = ['name' => 'Teams 1','status'=>'Active'];
			$this->initAuthToken($this->adminUser);
			$this->setJsonContent(json_encode($data));
			 if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();
				$exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
				$mockRestClient->expects('postWithHeader')->with("api/v4/teams",array("name" => 'teams-1',"display_name" => 'teams-1',"type" => 'O'),Mockery::any())->once()->andThrow($exception);
			 }
			$this->dispatch('/callback/chat/addorg', 'POST', $data);
			$this->assertResponseStatusCode(400);
			$this->assertMatchedRouteName('addcallback');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'error');
		}
		
		public function testAddOrgNotFound(){
			$data = ['status'=>'Active'];
			$this->initAuthToken($this->adminUser);
			$this->setJsonContent(json_encode($data));
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();
				$exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
				$mockRestClient->expects('postWithHeader')->with("api/v4/teams",array("status"=>"Active"),Mockery::any())->once()->andThrow($exception);
			 }
			$this->dispatch('/callback/chat/addorg', 'POST', $data);
			$this->assertResponseStatusCode(400);
			$this->assertMatchedRouteName('addcallback');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'error');
		}
		
		public function testUpdateOrg(){
			$data = ['new_name' => 'new-oxzion1','old_name'=>'teams-1','status'=>'Active'];
			$this->initAuthToken($this->adminUser);
			$this->setJsonContent(json_encode($data));
		if(enableMattermost==0){
			$mockRestClient = $this->getMockRestClientForChatService();
			$mockRestClient->expects('get')->with("api/v4/teams/name/teams-1",array(),Mockery::any())->once()->andReturn(json_encode(array("name"=>"teams-1","display_name" => 'teams-1',"id" => 121)));
			$mockRestClient->expects('put')->with("api/v4/teams/121",array("name" => 'new-oxzion1',"display_name" => 'new-oxzion1',"id" => 121),Mockery::any())->once()->andReturn(json_encode(array('name'=>"new-oxzion1","display_name" => 'new-oxzion1')));
		}

			$this->dispatch('/callback/chat/updateorg', 'POST', $data);
			$this->assertResponseStatusCode(200);
			$this->assertMatchedRouteName('updatecallback');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'success');
		}
		
		public function testUpdateOrgNameNotExists(){
			$data = ['old_name' => 'Team Vantage123','status'=>'Active'];
			$this->initAuthToken($this->adminUser);
			$this->setJsonContent(json_encode($data));
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();
				$exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
				$mockRestClient->expects('get')->with("api/v4/teams/name/teams-1",array(),Mockery::any())->once()->andReturn(json_encode(array("name"=>"teams-1","display_name" => 'teams-1',"id" => 121)));
				$mockRestClient->expects('put')->with("api/v4/teams/121",array("id" => 121),Mockery::any())->once()->andThrow($exception);
			 }
			$this->dispatch('/callback/chat/updateorg', 'POST', $data);
			$this->assertResponseStatusCode(404);
			$this->assertMatchedRouteName('updatecallback');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'error');
		 }
		// // No mock test
		public function testUpdateOrgOldNameNotExists(){
			$data = ['new_name' => 'Vantage Vantage','status'=>'Active'];
			$this->initAuthToken($this->adminUser);
			$this->setJsonContent(json_encode($data));
			$this->dispatch('/callback/chat/updateorg', 'POST', $data);
			$this->assertResponseStatusCode(404);
			$this->assertMatchedRouteName('updatecallback');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'error');
		}
		
		public function testAddUserToOrgBothExists(){
			$this->initAuthToken($this->adminUser);
			$data = ['username' => 'rakshith','teamname' => 'teams-1','status'=>'Active'];
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();
				$mockRestClient->expects('get')->with("api/v4/teams/name/teams-1",array(),Mockery::any())->once()->andReturn(json_encode(array('name'=>"team-oxzion","display_name" => 'team-oxzion',"id" => 121)));
				$mockRestClient->expects('get')->with("api/v4/users/username/rakshith",array(),Mockery::any())->once()->andReturn(json_encode(array('name'=>"rakshith","id" => 1)));
				$mockRestClient->expects('postWithHeader')->with("api/v4/teams/121/members",array('team_id' => 121,'user_id' => 1),Mockery::any())->once()->andReturn(array("body" => json_encode(array("team_id"=>121,"user_id" => 1,"roles"=>"team_user"))));
			}

			$this->dispatch('/callback/chat/adduser', 'POST', $data);
			$this->assertResponseStatusCode(200);
			$this->assertModuleName('Callback');
			$this->assertControllerName(ChatcallbackController::class); // as specified in router's controller name alias
			$this->assertControllerClass('ChatcallbackController');
			$this->assertMatchedRouteName('addusercallback');
			$this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'success');
		}
		
		public function testAddUserToOrgForNewUser(){
			$this->initAuthToken($this->adminUser);
			$data = ['username' => 'shravani','teamname' => 'Teams 1','status'=>'Active'];
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();
				$request = Mockery::Mock('Psr\Http\Message\RequestInterface');
				$response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
				$response->expects('getStatusCode')->andReturn(404);
				$mockRestClient->expects('get')->with("api/v4/teams/name/teams-1",array(),Mockery::any())->once()->andReturn(json_encode(array('name'=>"team-oxzion","display_name" => 'team-oxzion',"id" => 121)));
				$mockRestClient->expects('get')->with("api/v4/users/username/shravani",array(),Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('"id" : "store.sql_user.get_by_username.app_error"', $request, $response));
				$mockRestClient->expects('postWithHeader')->with("api/v4/users",array("email" => "shravani@gmail.com","username" => "shravani","first_name" => "shravani","password" => md5('shravani')),Mockery::any())->once()->andReturn(array("body" => json_encode(array("id"=> 2,"username" => "shravani","email"=>"shravani@gmail.com","first_name"=>"shravani"))));
				$mockRestClient->expects('postWithHeader')->with("api/v4/teams/121/members",array('team_id' => 121,'user_id' => 2),Mockery::any())->once()->andReturn(array("body" => json_encode(array("team_id"=>121,"user_id" => 2,"roles"=>"team_user"))));
				
			}
			$this->dispatch('/callback/chat/adduser', 'POST', $data);
			$this->assertResponseStatusCode(200);
			$this->assertModuleName('Callback');
			$this->assertControllerName(ChatcallbackController::class); // as specified in router's controller name alias
			$this->assertControllerClass('ChatcallbackController');
			$this->assertMatchedRouteName('addusercallback');
			$this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'success');
		}

		public function testAddUserToOrgNetworkIssue(){
			$this->initAuthToken($this->adminUser);
			$data = ['username' => 'ramya','teamname' => 'Teams 1','status'=>'Active'];
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();
				$request = Mockery::Mock('Psr\Http\Message\RequestInterface');
				$response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
				$response->expects('getStatusCode')->andReturn(500);
				$mockRestClient->expects('get')->with("api/v4/teams/name/teams-1",array(),Mockery::any())->once()->andReturn(json_encode(array('name'=>"team-oxzion","display_name" => 'team-oxzion',"id" => 121)));
				$mockRestClient->expects('get')->with("api/v4/users/username/ramya",array(),Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('', $request, $response));				
			}
			if(enableMattermost==1){
				$this->assertTrue(true);
			}else{
				$this->dispatch('/callback/chat/adduser', 'POST', $data);
				$this->assertResponseStatusCode(500);
				$this->assertModuleName('Callback');
				$this->assertControllerName(ChatcallbackController::class); // as specified in router's controller name alias
				$this->assertControllerClass('ChatcallbackController');
				$this->assertMatchedRouteName('addusercallback');
				$this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
				$content = (array)json_decode($this->getResponse()->getContent(), true);
				$this->assertEquals($content['status'], 'error');
			}
		}
		
		public function testAddUserToOrgForNewOrg(){
			$this->initAuthToken($this->adminUser);
			$data = ['username' => 'rakshith','teamname' => 'Teams new1','status'=>'Active'];
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();
				$request = Mockery::Mock('Psr\Http\Message\RequestInterface');
				$response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
				$response->expects('getStatusCode')->andReturn(404);
				$mockRestClient->expects('get')->with("api/v4/teams/name/teams-new1",array(),Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('"id" : "store.sql_team.get_by_name.app_error"', $request, $response));
				$mockRestClient->expects('postWithHeader')->with("api/v4/teams",array('name' => "teams-new1",'display_name' => "teams-new1",'type' => 'O'),Mockery::any())->once()->andReturn(array("body" => json_encode(array("id"=>125,"name" => "teams-new1","display_name"=>"teams-new1"))));
				$mockRestClient->expects('get')->with("api/v4/users/username/rakshith",array(),Mockery::any())->once()->andReturn(json_encode(array('name'=>"rakshith","id" => 1)));
				$mockRestClient->expects('postWithHeader')->with("api/v4/teams/125/members",array('team_id' => 125,'user_id' => 1),Mockery::any())->once()->andReturn(array("body" => json_encode(array("team_id"=>125,"user_id" => 1,"roles"=>"team_user"))));
			}
			$this->dispatch('/callback/chat/adduser', 'POST', $data);
			$this->assertResponseStatusCode(200);
			$this->assertModuleName('Callback');
			$this->assertControllerName(ChatcallbackController::class); // as specified in router's controller name alias
			$this->assertControllerClass('ChatcallbackController');
			$this->assertMatchedRouteName('addusercallback');
			$this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'success');
		}

		public function testAddUserToOrgOrgNotFound(){
			$this->initAuthToken($this->adminUser);
			$data = ['username' => 'rakshith','teamname' => 'Orgo organization','status'=>'Active'];
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();
				$request = Mockery::Mock('Psr\Http\Message\RequestInterface');
				$response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
				$response->expects('getStatusCode')->andReturn(500);
				$mockRestClient->expects('get')->with("api/v4/teams/name/orgo-organization",array(),Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('', $request, $response));
			}
			if(enableMattermost==1){
				$this->assertTrue(true);
			}else{
				$this->dispatch('/callback/chat/adduser', 'POST', $data);
				$this->assertResponseStatusCode(500);
				$this->assertModuleName('Callback');
				$this->assertControllerName(ChatcallbackController::class); // as specified in router's controller name alias
				$this->assertControllerClass('ChatcallbackController');
				$this->assertMatchedRouteName('addusercallback');
				$this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
				$content = (array)json_decode($this->getResponse()->getContent(), true);
				$this->assertEquals($content['status'], 'error');
			}
		}
		
		public function testAddUserToOrgUserAndOrgNotFound(){
			$this->initAuthToken($this->adminUser);
			$data = ['status'=>'Active'];
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();
				$exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
				$mockRestClient->expects('postWithHeader')->with("api/v4/teams",array("status"=>"Active"),Mockery::any())->once()->andThrow($exception);
			 }
			$this->dispatch('/callback/chat/adduser', 'POST', $data);
			$this->assertResponseStatusCode(400);
			$this->assertModuleName('Callback');
			$this->assertControllerName(ChatcallbackController::class); // as specified in router's controller name alias
			$this->assertControllerClass('ChatcallbackController');
			$this->assertMatchedRouteName('addusercallback');
			$this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'error');
		}
		
		public function testCreateChannel(){
			$this->initAuthToken($this->adminUser);
			$data = ['teamname'=>'teams-1','channelname'=>'Channel Private-1','status'=>'Active'];
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();	
				$mockRestClient->expects('get')->with("api/v4/teams/name/teams-1",array(),Mockery::any())->once()->andReturn(json_encode(array('name'=>"teams-1","display_name" => 'teams-1',"id" => 121)));
				$mockRestClient->expects('postWithHeader')->with("api/v4/channels",array('team_id'=>121,'name'=>"channel-private-1",'display_name'=>"channel-private-1",'type'=>'P'),Mockery::any())->once()->andReturn(array("body" => json_encode(array("id"=> 234,"name"=>"channel-private-1","display_name" => "channel-private-1","team_id" => 121))));
			}

			$this->dispatch('/callback/chat/createchannel', 'POST', $data);
			$this->assertResponseStatusCode(200);
			$this->assertModuleName('Callback');
			$this->assertControllerName(ChatcallbackController::class); // as specified in router's controller name alias
			$this->assertControllerClass('ChatcallbackController');
			$this->assertMatchedRouteName('createchannelcallback');
			$this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'success'); 
		}
		
		public function testCreateChannelForNewOrg(){
			$this->initAuthToken($this->adminUser);
			$data = ['teamname'=>'New Org-1','channelname'=>'New Channel-Test','status'=>'Active'];
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();	
				$request = Mockery::Mock('Psr\Http\Message\RequestInterface');
				$response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
				$response->expects('getStatusCode')->andReturn(404);
				$mockRestClient->expects('get')->with("api/v4/teams/name/new-org-1",array(),Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('"id" : "store.sql_team.get_by_name.app_error"', $request, $response));
				$mockRestClient->expects('postWithHeader')->with("api/v4/teams",array('name' => "new-org-1",'display_name' => "new-org-1",'type' => 'O'),Mockery::any())->once()->andReturn(array("body" => json_encode(array("id"=> 130,"name"=>"new-org-1","display_name" => "new-org-1"))));
				$mockRestClient->expects('postWithHeader')->with("api/v4/channels",array('team_id'=>130,'name'=>"new-channel-test",'display_name'=>"new-channel-test",'type'=>'P'),Mockery::any())->once()->andReturn(array("body" => json_encode(array("id"=> 250,"name"=>"new-channel-test","display_name" => "new-channel-test","team_id" => 130))));
			}
			$this->dispatch('/callback/chat/createchannel', 'POST', $data);
			$this->assertResponseStatusCode(200);
			$this->assertModuleName('Callback');
			$this->assertControllerName(ChatcallbackController::class); // as specified in router's controller name alias
			$this->assertControllerClass('ChatcallbackController');
			$this->assertMatchedRouteName('createchannelcallback');
			$this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'success'); 
		}

		public function testCreateChannelNetworkIssue(){
			$this->initAuthToken($this->adminUser);
			$data = ['teamname'=>'Orgo organization','channelname'=>'New Channel-Test','status'=>'Active'];
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();	
				$request = Mockery::Mock('Psr\Http\Message\RequestInterface');
				$response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
				$response->expects('getStatusCode')->andReturn(500);
				$mockRestClient->expects('get')->with("api/v4/teams/name/orgo-organization",array(),Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('', $request, $response));
			}
			if(enableMattermost==1){
				$this->assertTrue(true);
			}else{
				$this->dispatch('/callback/chat/createchannel', 'POST', $data);
				$this->assertResponseStatusCode(500);
				$this->assertModuleName('Callback');
				$this->assertControllerName(ChatcallbackController::class); // as specified in router's controller name alias
				$this->assertControllerClass('ChatcallbackController');
				$this->assertMatchedRouteName('createchannelcallback');
				$this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
				$content = (array)json_decode($this->getResponse()->getContent(), true);
				$this->assertEquals($content['status'], 'error'); 
				}
		}
		
		public function testCreateChannelNameNotFound(){
			$this->initAuthToken($this->adminUser);
			$data = ['teamname'=>'Teams 1','status'=>'Active'];
			$this->setJsonContent(json_encode($data));
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();	
				$exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
				$mockRestClient->expects('get')->with("api/v4/teams/name/teams-1",array(),Mockery::any())->once()->andReturn(json_encode(array('name'=>"teams-1","display_name" => 'teams-1',"id" => 121)));
				$mockRestClient->expects('postWithHeader')->with("api/v4/channels",array('team_id'=>121,'type'=>'P'),Mockery::any())->once()->andThrows($exception);
			}
			$this->dispatch('/callback/chat/createchannel', 'POST', $data);
			$this->assertResponseStatusCode(400);
			$this->assertMatchedRouteName('createchannelcallback');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'error');
		}
		// // No mock test
		public function testCreateChannelTeamNotFound(){
			$this->initAuthToken($this->adminUser);
			$data = ['channelname'=>'Private Channel 1','status'=>'Active'];
			$this->setJsonContent(json_encode($data));
			$this->dispatch('/callback/chat/createchannel', 'POST', $data);
			$this->assertResponseStatusCode(400);
			$this->assertMatchedRouteName('createchannelcallback');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'error');
		}
		
		public function testUpdateChannel(){
			$data = ['new_channelname' => 'New Channel Private 1','old_channelname'=>'Channel private-1','team_name'=>'teams 1','status'=>'Active'];
			$this->initAuthToken($this->adminUser);
			$this->setJsonContent(json_encode($data));
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();	
				$mockRestClient->expects('get')->with("api/v4/teams/name/teams-1",array(),Mockery::any())->once()->andReturn(json_encode(array('name'=>"teams-1","display_name" => 'teams-1',"id" => 121)));
				$mockRestClient->expects('get')->with("api/v4/teams/121/channels/name/channel-private-1",array(),Mockery::any())->once()->andReturn(json_encode(array("id"=> 234,"name"=>"channel-private-1","display_name" => "channel-private-1","team_id" => 121)));
				$mockRestClient->expects('put')->with("api/v4/channels/234",array('id'=>234,'name'=>"new-channel-private-1",'display_name' =>"new-channel-private-1"),Mockery::any())->once()->andReturn(json_encode(array("id"=> 234,"name"=>"new-channel-private-1","display_name" => "new-channel-private-1","team_id" => 121)));
			}
			$this->dispatch('/callback/chat/updatechannel', 'POST', $data);
			$this->assertResponseStatusCode(200);
			$this->assertMatchedRouteName('updatechannelcallback');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'success');
		}
		
		public function testUpdateChannelNewNameNotFound(){
			$this->initAuthToken($this->adminUser);
			$data = ['old_channelname'=>'Channel Private 1','team_name'=>'Teams 1','status'=>'Active'];
			$this->setJsonContent(json_encode($data));
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();
				$exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
				$mockRestClient->expects('get')->with("api/v4/teams/name/teams-1",array(),Mockery::any())->once()->andReturn(json_encode(array('name'=>"teams-1","display_name" => 'teams-1',"id" => 121)));
				$mockRestClient->expects('get')->with("api/v4/teams/121/channels/name/channel-private-1",array(),Mockery::any())->once()->andReturn(json_encode(array("id"=> 234,"name"=>"channel-private-1","display_name" => "channel-private-1","team_id" => 121)));
				$mockRestClient->expects('put')->with("api/v4/channels/234",array('id'=>234),Mockery::any())->once()->andThrow($exception);
			 }
			$this->dispatch('/callback/chat/updatechannel', 'POST', $data);
			$this->assertResponseStatusCode(404);
			$this->assertMatchedRouteName('updatechannelcallback');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'error');
		}
		// No mock test
		public function testUpdateChannelNameNotFound(){
			$this->initAuthToken($this->adminUser);
			$data = ['new_channelname' => 'Oh myh god','team_name'=>'Testing Team','status'=>'Active'];
			$this->setJsonContent(json_encode($data));
			$this->dispatch('/callback/chat/updatechannel', 'POST', $data);
			$this->assertResponseStatusCode(404);
			$this->assertMatchedRouteName('updatechannelcallback');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'error');
		}
		
		public function testAddUserToChannel(){
			$this->initAuthToken($this->adminUser);
			$data = ['username' => 'Rakshith','teamname'=>'Teams 1','channelname'=>'New Channel Private 1','status'=>'Active'];
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();	
				$mockRestClient->expects('get')->with("api/v4/teams/name/teams-1",array(),Mockery::any())->once()->andReturn(json_encode(array('name'=>"teams-1","display_name" => 'teams-1',"id" => 121)));
				$mockRestClient->expects('get')->with("api/v4/teams/121/channels/name/new-channel-private-1",array(),Mockery::any())->once()->andReturn(json_encode(array("id"=>234,"name"=>"new-channel-private-1","display_name" => "new-channel-private-1","team_id" => 121)));
				$mockRestClient->expects('get')->with("api/v4/users/username/rakshith",array(),Mockery::any())->once()->andReturn(json_encode(array("id"=>1,"name"=>"rakshith")));
				$mockRestClient->expects('get')->with("api/v4/teams/121/members/1",array(),Mockery::any())->once()->andReturn(json_encode(array("team_id"=>121,"user_id"=>1)));
				$mockRestClient->expects('postWithHeader')->with("api/v4/channels/234/members",array('user_id' => 1),Mockery::any())->once()->andReturn(array("body" => json_encode(array('channel_id'=>234,"user_id" =>1))));
			}
	
			$this->dispatch('/callback/chat/addusertochannel', 'POST', $data);
			$this->assertResponseStatusCode(200);
			$this->assertModuleName('Callback');
			$this->assertControllerName(ChatcallbackController::class); // as specified in router's controller name alias
			$this->assertControllerClass('ChatcallbackController');
			$this->assertMatchedRouteName('addusertochannelcallback');
			$this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'success');
		}
		
		public function testAddUserToChannelCreateChannel(){
			$this->initAuthToken($this->adminUser);
			$data = ['username' => 'Rakshith','teamname'=>'Teams 1','channelname'=>'Private1 Private','status'=>'Active'];
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();	
				$request = Mockery::Mock('Psr\Http\Message\RequestInterface');
				$response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
				$response->expects('getStatusCode')->andReturn(404);
				$mockRestClient->expects('get')->with("api/v4/teams/name/teams-1",array(),Mockery::any())->once()->andReturn(json_encode(array('name'=>"teams-1","display_name" => 'teams-1',"id" => 121)));
				$mockRestClient->expects('get')->with("api/v4/teams/121/channels/name/private1-private",array(),Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('"id" : "store.sql_channel.get_by_name.missing.app_error"', $request, $response));
				$mockRestClient->expects('postWithHeader')->with("api/v4/channels",array('team_id'=>121,'name'=>"private1-private",'display_name'=>"private1-private",'type'=>'P'),Mockery::any())->once()->andReturn(array("body" => json_encode(array('id'=>260,"team_id" =>121))));
				$mockRestClient->expects('get')->with("api/v4/users/username/rakshith",Mockery::any(),Mockery::any())->once()->andReturn(json_encode(array("id"=>1,"name"=>"rakshith")));
				$mockRestClient->expects('get')->with("api/v4/teams/121/members/1",array(),Mockery::any())->once()->andReturn(json_encode(array("team_id"=>121,"user_id"=>1)));
				$mockRestClient->expects('postWithHeader')->with("api/v4/channels/260/members",array('user_id' => 1),Mockery::any())->once()->andReturn(array("body" => json_encode(array('channel_id'=>260,"user_id" =>1))));
			}
			$this->dispatch('/callback/chat/addusertochannel', 'POST', $data);
			$this->assertResponseStatusCode(200);
			$this->assertModuleName('Callback');
			$this->assertControllerName(ChatcallbackController::class); // as specified in router's controller name alias
			$this->assertControllerClass('ChatcallbackController');
			$this->assertMatchedRouteName('addusertochannelcallback');
			$this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'success');
		}

		public function testAddUserToChannelNetworkIssue(){
			$this->initAuthToken($this->adminUser);
			$data = ['username' => 'Rakshith','teamname'=>'Teams 1','channelname'=>'Channel Chan','status'=>'Active'];
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();	
				$request = Mockery::Mock('Psr\Http\Message\RequestInterface');
				$response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
				$response->expects('getStatusCode')->andReturn(500);
				$mockRestClient->expects('get')->with("api/v4/teams/name/teams-1",array(),Mockery::any())->once()->andReturn(json_encode(array('name'=>"teams-1","display_name" => 'teams-1',"id" => 121)));
				$mockRestClient->expects('get')->with("api/v4/teams/121/channels/name/channel-chan",array(),Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('', $request, $response));
			}
			if(enableMattermost==1){
				$this->assertTrue(true);
			}else{
				$this->dispatch('/callback/chat/addusertochannel', 'POST', $data);
				$this->assertResponseStatusCode(500);
				$this->assertModuleName('Callback');
				$this->assertControllerName(ChatcallbackController::class); // as specified in router's controller name alias
				$this->assertControllerClass('ChatcallbackController');
				$this->assertMatchedRouteName('addusertochannelcallback');
				$this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
				$content = (array)json_decode($this->getResponse()->getContent(), true);
				$this->assertEquals($content['status'], 'error');
			}
		}
		
		public function testAddUserToChannelCreateTeam(){
			$this->initAuthToken($this->adminUser);
			$data = ['username' => 'Rakshith','teamname'=>'Boscos Team','channelname'=>'Private1 Private','status'=>'Active'];
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();	
				$request = Mockery::Mock('Psr\Http\Message\RequestInterface');
				$response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
				$response->expects('getStatusCode')->andReturn(404);
				$mockRestClient->expects('get')->with("api/v4/teams/name/boscos-team",array(),Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('"id" : "store.sql_team.get_by_name.app_error"', $request, $response));
				$mockRestClient->expects('postWithHeader')->with("api/v4/teams",array('name' => "boscos-team",'display_name' => "boscos-team",'type' => 'O'),Mockery::any())->andReturn(array("body" => json_encode(array('id'=>170,"name" =>"boscos-team","display_name"=>"boscos-team"))));
				$mockRestClient->expects('get')->with("api/v4/teams/170/channels/name/private1-private",array(),Mockery::any())->once()->andReturn(json_encode(array("id"=>270,"name"=>"private1-private","display_name" => "private1-private","team_id" => 170)));
				$mockRestClient->expects('postWithHeader')->with("api/v4/channels",array('team_id'=>121,'name'=>"private1-private",'display_name'=>"private1-private",'type'=>'P'),Mockery::any())->once()->andReturn(array("body" => json_encode(array('id'=>270,"team_id" =>170))));
				$mockRestClient->expects('get')->with("api/v4/users/username/rakshith",array(),Mockery::any())->once()->andReturn(json_encode(array("id"=>1,"name"=>"rakshith")));
				$mockRestClient->expects('get')->with("api/v4/teams/170/members/1",array(),Mockery::any())->once()->andReturn(json_encode(array("team_id"=>170,"user_id"=>1)));
				$mockRestClient->expects('postWithHeader')->with("api/v4/channels/270/members",array('user_id' => 1),Mockery::any())->once()->andReturn(array("body" => json_encode(array('channel_id'=>270,"user_id" =>1))));
			}
			$this->dispatch('/callback/chat/addusertochannel', 'POST', $data);
			$this->assertResponseStatusCode(200);
			$this->assertModuleName('Callback');
			$this->assertControllerName(ChatcallbackController::class); // as specified in router's controller name alias
			$this->assertControllerClass('ChatcallbackController');
			$this->assertMatchedRouteName('addusertochannelcallback');
			$this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'success');
		}
		
		public function testAddUserToChannelTeamNotFoundBeczNetworkIssue(){
			$this->initAuthToken($this->adminUser);
			$data = ['username' => 'Rakshith','teamname'=>'PES Team','channelname'=>'Private1 Private','status'=>'Active'];
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();	
				$request = Mockery::Mock('Psr\Http\Message\RequestInterface');
				$response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
				$response->expects('getStatusCode')->andReturn(500);
				$mockRestClient->expects('get')->with("api/v4/teams/name/pes-team",array(),Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('', $request, $response));
			}
			if(enableMattermost==1){
				$this->assertTrue(true);
			}else{
				$this->dispatch('/callback/chat/addusertochannel', 'POST', $data);
				$this->assertResponseStatusCode(500);
				$this->assertModuleName('Callback');
				$this->assertControllerName(ChatcallbackController::class); // as specified in router's controller name alias
				$this->assertControllerClass('ChatcallbackController');
				$this->assertMatchedRouteName('addusertochannelcallback');
				$this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
				$content = (array)json_decode($this->getResponse()->getContent(), true);
				$this->assertEquals($content['status'], 'error');
			}
		}

		public function testAddUserToChannelCreateUser(){
			$this->initAuthToken($this->adminUser);
			$data = ['username' => 'Girly','teamname'=>'Teams 1','channelname'=>'Private1 Private','status'=>'Active'];
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();	
				$request = Mockery::Mock('Psr\Http\Message\RequestInterface');
				$response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
				$response->expects('getStatusCode')->andReturn(404);
				$mockRestClient->expects('get')->with("api/v4/teams/name/teams-1",array(),Mockery::any())->once()->andReturn(json_encode(array('name'=>"teams-1","display_name" => 'teams-1',"id" => 121)));
				$mockRestClient->expects('get')->with("api/v4/teams/121/channels/name/private1-private",array(),Mockery::any())->once()->andReturn(json_encode(array("id"=>260,"name"=>"private1-private","display_name" => "private1-private","team_id" => 121)));
				$mockRestClient->expects('get')->with("api/v4/users/username/girly",array(),Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('"id" : "store.sql_user.get_by_username.app_error"', $request, $response));
				$mockRestClient->expects('postWithHeader')->with("api/v4/users",array('email' => "girly@gmail.com",'username' => "girly",'first_name' => "girly",'password' => md5('girly')),Mockery::any())->once()->andReturn(array("body" => json_encode(array("id"=>3,'email' => "girly@gmail.com",'username' => "girly",'first_name' => "girly"))));
				$mockRestClient->expects('get')->with("api/v4/teams/121/members/3",array(),Mockery::any())->once()->andReturn(json_encode(array("team_id"=>121,"user_id"=>3)));
				$mockRestClient->expects('postWithHeader')->with("api/v4/channels/260/members",array('user_id' => 3),Mockery::any())->once()->andReturn(array("body" => json_encode(array('channel_id'=>260,"user_id" =>3))));
			}
			$this->dispatch('/callback/chat/addusertochannel', 'POST', $data);
			$this->assertResponseStatusCode(200);
			$this->assertModuleName('Callback');
			$this->assertControllerName(ChatcallbackController::class); // as specified in router's controller name alias
			$this->assertControllerClass('ChatcallbackController');
			$this->assertMatchedRouteName('addusertochannelcallback');
			$this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'success');
		}

		public function testAddUserToChannelUserNotCreatedBeczNetworkIssue(){
			$this->initAuthToken($this->adminUser);
			$data = ['username' => 'Boyish','teamname'=>'Teams 1','channelname'=>'Private1 Private','status'=>'Active'];
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();	
				$request = Mockery::Mock('Psr\Http\Message\RequestInterface');
				$response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
				$response->expects('getStatusCode')->andReturn(500);
				$mockRestClient->expects('get')->with("api/v4/teams/name/teams-1",array(),Mockery::any())->once()->andReturn(json_encode(array('name'=>"teams-1","display_name" => 'teams-1',"id" => 121)));
				$mockRestClient->expects('get')->with("api/v4/teams/121/channels/name/private1-private",array(),Mockery::any())->once()->andReturn(json_encode(array("id"=>260,"name"=>"private1-private","display_name" => "private1-private","team_id" => 121)));
				$mockRestClient->expects('get')->with("api/v4/users/username/boyish",array(),Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('', $request, $response));
			}
			if(enableMattermost==1){
				$this->assertTrue(true);
			}else{
				$this->dispatch('/callback/chat/addusertochannel', 'POST', $data);
				$this->assertResponseStatusCode(500);
				$this->assertModuleName('Callback');
				$this->assertControllerName(ChatcallbackController::class); // as specified in router's controller name alias
				$this->assertControllerClass('ChatcallbackController');
				$this->assertMatchedRouteName('addusertochannelcallback');
				$this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
				$content = (array)json_decode($this->getResponse()->getContent(), true);
				$this->assertEquals($content['status'], 'error');
			}
		}

		// No mock test
		public function testAddUserToChannelDataNotFound(){
			$this->initAuthToken($this->adminUser);
			$data = ['teamname'=>'Raks Team','channelname'=>'Channel Crrate Private','status'=>'Active'];
			$this->setJsonContent(json_encode($data));
			$this->dispatch('/callback/chat/addusertochannel', 'POST', $data);
			$this->assertResponseStatusCode(400);
			$this->assertMatchedRouteName('addusertochannelcallback');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'error');
		}
		
		public function testRemoveUserFromChannel(){
			$this->initAuthToken($this->adminUser);
			$data = ['username' => 'Rakshith','teamname'=>'Teams 1','channelname'=>'New Channel Private 1','status'=>'Active'];
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();	
				$mockRestClient->expects('get')->with("api/v4/teams/name/teams-1",array(),Mockery::any())->once()->andReturn(json_encode(array('name'=>"teams-1","display_name" => 'teams-1',"id" => 121)));
				$mockRestClient->expects('get')->with("api/v4/teams/121/channels/name/new-channel-private-1",array(),Mockery::any())->once()->andReturn(json_encode(array("id"=>234,"name"=>"new-channel-private-1","display_name" => "new-channel-private-1","team_id" => 121)));
				$mockRestClient->expects('get')->with("api/v4/users/username/rakshith",array(),Mockery::any())->once()->andReturn(json_encode(array("id"=>1,"name"=>"rakshith")));
				$mockRestClient->expects('get')->with("api/v4/teams/121/members/1",array(),Mockery::any())->once()->andReturn(json_encode(array("team_id"=>121,"user_id"=>1)));
				$mockRestClient->expects('get')->with("api/v4/channels/234/members/1",array(),Mockery::any())->once()->andReturn(json_encode(array("channel_id"=>234,"user_id"=>1)));
				$mockRestClient->expects('delete')->with("api/v4/channels/234/members/1",array(),Mockery::any())->once()->andReturn(json_encode(array("status"=>"OK")));
			}
			$this->dispatch('/callback/chat/removeuserfromchannel', 'POST', $data);
			$this->assertResponseStatusCode(200);
			$this->assertModuleName('Callback');
			$this->assertControllerName(ChatcallbackController::class); 
			$this->assertControllerClass('ChatcallbackController');
			$this->assertMatchedRouteName('removeuserfromchannelcallback');
			$this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'success');  
		}
		// No mock test
		public function testRemoveUserFromChannelUserNotFound(){
			$this->initAuthToken($this->adminUser);
			$data = ['teamname'=>'Raks Team','channelname'=>'Channel Crrate Private','status'=>'Active'];
			$this->setJsonContent(json_encode($data));
			$this->dispatch('/callback/chat/removeuserfromchannel', 'POST', $data);
			$this->assertResponseStatusCode(400);
			$this->assertMatchedRouteName('removeuserfromchannelcallback');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'error');
		}
		
		public function testRemoveUserFromChannelUserNotInChannel(){
			$this->initAuthToken($this->adminUser);
			$data = ['username' => 'Rakshith','teamname'=>'Teams 1','channelname'=>'Private1 Private','status'=>'Active'];
			$this->setJsonContent(json_encode($data));
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();	
				$exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
				$mockRestClient->expects('get')->with("api/v4/teams/name/teams-1",array(),Mockery::any())->once()->andReturn(json_encode(array('name'=>"teams-1","display_name" => 'teams-1',"id" => 121)));
				$mockRestClient->expects('get')->with("api/v4/teams/121/channels/name/private1-private",array(),Mockery::any())->once()->andReturn(json_encode(array("id"=>260,"name"=>"private1-private","display_name" => "private1-private","team_id" => 121)));
				$mockRestClient->expects('get')->with("api/v4/users/username/rakshith",array(),Mockery::any())->once()->andReturn(json_encode(array("id"=>1,"name"=>"rakshith")));
				$mockRestClient->expects('get')->with("api/v4/teams/121/members/1",array(),Mockery::any())->once()->andReturn(json_encode(array("team_id"=>121,"user_id"=>1)));
				$mockRestClient->expects('get')->with("api/v4/channels/260/members/1",array(),Mockery::any())->once()->andThrow($exception);
			}
			$this->dispatch('/callback/chat/removeuserfromchannel', 'POST', $data);
			$this->assertResponseStatusCode(400);
			$this->assertMatchedRouteName('removeuserfromchannelcallback');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'error');
		}
		
		
		public function testRemoveUserFromChannelNotCreatedIssue(){
			$this->initAuthToken($this->adminUser);
			$data = ['username' => 'Rakshith','teamname'=>'Teams 1','channelname'=>'Payyannur','status'=>'Active'];
			$this->setJsonContent(json_encode($data));
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();	
				$request = Mockery::Mock('Psr\Http\Message\RequestInterface');
				$response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
				$response->expects('getStatusCode')->andReturn(500);
				$mockRestClient->expects('get')->with("api/v4/teams/name/teams-1",array(),Mockery::any())->once()->andReturn(json_encode(array('name'=>"teams-1","display_name" => 'teams-1',"id" => 121)));
				$mockRestClient->expects('get')->with("api/v4/teams/121/channels/name/payyannur",array(),Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('', $request, $response));
			}
			if(enableMattermost==1){
				$this->assertTrue(true);
			}else{
				$this->dispatch('/callback/chat/removeuserfromchannel', 'POST', $data);
				$this->assertResponseStatusCode(500);
				$this->assertMatchedRouteName('removeuserfromchannelcallback');
				$content = (array)json_decode($this->getResponse()->getContent(), true);
				$this->assertEquals($content['status'], 'error');
			}
		}
		
		public function testDeleteChannel(){
			$this->initAuthToken($this->adminUser);
			$data = ['teamname' => 'teams-1','channelname'=>'New Channel Private 1','status'=>'Active'];
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();	
				$mockRestClient->expects('get')->with("api/v4/teams/name/teams-1",array(),Mockery::any())->once()->andReturn(json_encode(array('name'=>"teams-1","display_name" => 'teams-1',"id" => 121)));
				$mockRestClient->expects('get')->with("api/v4/teams/121/channels/name/new-channel-private-1",array(),Mockery::any())->once()->andReturn(json_encode(array("id"=>234,"name"=>"new-channel-private-1","display_name" => 'new-channel-private-1',"team_id" => 121)));
				$mockRestClient->expects('delete')->with("api/v4/channels/234",array(),Mockery::any())->once()->andReturn(json_encode(array("status"=>"OK")));
			}
	
			$this->dispatch('/callback/chat/deletechannel', 'POST', $data);
			$this->assertResponseStatusCode(200);
			$this->assertModuleName('Callback');
			$this->assertControllerName(ChatcallbackController::class); // as specified in router's controller name alias
			$this->assertControllerClass('ChatcallbackController');
			$this->assertMatchedRouteName('deletechannelcallback');
			$this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'success');
		}
		// no mock test
		public function testDeleteChannelNameNotFound(){
			$this->initAuthToken($this->adminUser);
			$data = ['teamname'=>'Raks Team','status'=>'Active'];
			$this->setJsonContent(json_encode($data));
			$this->dispatch('/callback/chat/deletechannel', 'POST', $data);
			$this->assertResponseStatusCode(400);
			$this->assertMatchedRouteName('deletechannelcallback');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'error');
		}
		// no mock test
		public function testDeleteChannelTeamNameNotFound(){
			$this->initAuthToken($this->adminUser);
			$data = ['channelname'=>'off-topic','status'=>'Active'];
			$this->setJsonContent(json_encode($data));
			$this->dispatch('/callback/chat/deletechannel', 'POST', $data);
			$this->assertResponseStatusCode(400);
			$this->assertMatchedRouteName('deletechannelcallback');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'error');
		}
		
		public function testDeleteChannelNotFound(){
			$this->initAuthToken($this->adminUser);
			$data = ['teamname' => 'Teams 1','channelname'=>'Privatecs 3','status'=>'Active'];
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();	
				$exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
				$request = Mockery::Mock('Psr\Http\Message\RequestInterface');
				$response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
				$response->expects('getStatusCode')->andReturn(500);
				$mockRestClient->expects('get')->with("api/v4/teams/name/teams-1",array(),Mockery::any())->once()->andReturn(json_encode(array('name'=>"teams-1","display_name" => 'teams-1',"id" => 121)));
				$mockRestClient->expects('get')->with("api/v4/teams/121/channels/name/privatecs-3",array(),Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('', $request, $response));
			}
			if(enableMattermost==1){
				$this->assertTrue(true);
			}else{
				$this->dispatch('/callback/chat/deletechannel', 'POST', $data);
				$this->assertResponseStatusCode(500);
				$this->assertModuleName('Callback');
				$this->assertControllerName(ChatcallbackController::class); // as specified in router's controller name alias
				$this->assertControllerClass('ChatcallbackController');
				$this->assertMatchedRouteName('deletechannelcallback');
				$this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
				$content = (array)json_decode($this->getResponse()->getContent(), true);
				$this->assertEquals($content['status'], 'error');
			}
		}
		
		public function testRemoveUserFromOrg(){
			$this->initAuthToken($this->adminUser);
			$data = ['username' => 'rakshith','teamname' => 'teams-1','status'=>'Active'];
		if(enableMattermost==0){
			$mockRestClient = $this->getMockRestClientForChatService();		
			$mockRestClient->expects('get')->with("api/v4/users/username/rakshith",array(),Mockery::any())->once()->andReturn(json_encode(array('name'=>"rakshith","id" => 1)));
			$mockRestClient->expects('get')->with("api/v4/teams/name/teams-1",array(),Mockery::any())->once()->andReturn(json_encode(array('name'=>"teams-1","display_name" => 'teams-1',"id" => 121)));
			$mockRestClient->expects('delete')->with("api/v4/teams/121/members/1",array(),Mockery::any())->once()->andReturn(json_encode(array("status"=>"OK")));
		}
		
			$this->dispatch('/callback/chat/removeuser', 'POST', $data);
			$this->assertResponseStatusCode(200);
			$this->assertModuleName('Callback');
			$this->assertControllerName(ChatcallbackController::class); // as specified in router's controller name alias
			$this->assertControllerClass('ChatcallbackController');
			$this->assertMatchedRouteName('removeusercallback');
			$this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'success');
		}

		public function testRemoveUserFromOrgUserNotFound(){
			$this->initAuthToken($this->adminUser);
			$data = ['username' => 'laxmi','teamname' => 'teams-1','status'=>'Active'];
			$this->setJsonContent(json_encode($data));
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();		
				$request = Mockery::Mock('Psr\Http\Message\RequestInterface');
				$response = Mockery::Mock('Psr\Http\Message\ResponseInterface');
				$response->expects('getStatusCode')->andReturn(500);
				$mockRestClient->expects('get')->with("api/v4/users/username/laxmi",array(),Mockery::any())->once()->andThrow(new \GuzzleHttp\Exception\ClientException('', $request, $response));
			}
			if(enableMattermost==1){
				$this->assertTrue(true);
			}else{
				$this->dispatch('/callback/chat/removeuser', 'POST', $data);
				$this->assertResponseStatusCode(500);
				$this->assertMatchedRouteName('removeusercallback');
				$content = (array)json_decode($this->getResponse()->getContent(), true);
				$this->assertEquals($content['status'], 'error');
			}
		} 

		// No mock test
		public function testRemoveUserFromOrgDataNotFound(){
			$this->initAuthToken($this->adminUser);
			$data = ['teamname' => 'Raks Team','status'=>'Active'];
			$this->setJsonContent(json_encode($data));
			$this->dispatch('/callback/chat/removeuser', 'POST', $data);
			$this->assertResponseStatusCode(404);
			$this->assertMatchedRouteName('removeusercallback');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'error');
		} 
		// No mock test
		public function testRemoveUserFromOrgNotFound(){
			$this->initAuthToken($this->adminUser);
			$data = ['username' => 'bharatg','status'=>'Active'];
			$this->setJsonContent(json_encode($data));
			$this->dispatch('/callback/chat/removeuser', 'POST', $data);
			$this->assertResponseStatusCode(404);
			$this->assertMatchedRouteName('removeusercallback');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'error');
		}
		
		public function testRemoveUserFromOrgUserNotInTeam(){
			$this->initAuthToken($this->adminUser);
			$data = ['username' => 'rakshith','teamname' => 'Raks Team','status'=>'Active'];
			$this->setJsonContent(json_encode($data));
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();		
				$exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
				$mockRestClient->expects('get')->with("api/v4/users/username/rakshith",array(),Mockery::any())->once()->andReturn(json_encode(array('name'=>"rakshith","id" => 1)));
				$mockRestClient->expects('get')->with("api/v4/teams/name/raks-team",array(),Mockery::any())->once()->andReturn(json_encode(array('name'=>"raks-team","display_name" => 'raks-team',"id" => 175)));
				$mockRestClient->expects('delete')->with("api/v4/teams/175/members/1",array(),Mockery::any())->once()->andThrow($exception);
			}
			$this->dispatch('/callback/chat/removeuser', 'POST', $data);
			$this->assertResponseStatusCode(404);
			$this->assertMatchedRouteName('removeusercallback');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'error');
		}
		
		public function testDeleteOrg(){
			$data = ['name' => 'teams-1','status'=>'Active'];
			$this->initAuthToken($this->adminUser);
			$this->setJsonContent(json_encode($data));
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();
				$mockRestClient->expects('postWithHeader')->with("api/v4/teams/search",array('term' => "teams-1"),Mockery::any())->once()->andReturn(array("body" => json_encode(array(array("name"=>"teams-1","display_name" => "teams-1","id"=>121)))));
				$mockRestClient->expects('delete')->with("api/v4/teams/121",array('permanent' => 'false'),Mockery::any())->once()->andReturn(json_encode(array("status"=>"OK")));
			}	
			$this->dispatch('/callback/chat/deleteorg', 'POST', $data);
			$this->assertResponseStatusCode(200);
			$this->assertMatchedRouteName('deletecallback');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'success');
		}
		
		public function testDeleteOrgNotFound(){
			$data = ['name'=>'hmm ok jog','status'=>'Active'];
			$this->initAuthToken($this->adminUser);
			$this->setJsonContent(json_encode($data));
			if(enableMattermost==0){
				$mockRestClient = $this->getMockRestClientForChatService();
				$exception = Mockery::Mock('GuzzleHttp\Exception\ClientException');
				$mockRestClient->expects('postWithHeader')->with("api/v4/teams/search",array('term' => "hmm-ok-jog"),Mockery::any())->once()->andThrow($exception);
			}
			$this->dispatch('/callback/chat/deleteorg', 'POST', $data);
			$this->assertResponseStatusCode(400);
			$this->assertMatchedRouteName('deletecallback');
			$content = (array)json_decode($this->getResponse()->getContent(), true);
			$this->assertEquals($content['status'], 'error');
		}
		
		
	}