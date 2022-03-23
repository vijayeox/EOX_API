<?php
namespace Project;

use Mockery;
use Oxzion\Service\ProjectService;
use Oxzion\Test\ControllerTest;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Project\Controller\ProjectController;

class ProjectControllerTest extends ControllerTest
{
    public function setUp(): void
    {
        $this->loadConfig();
        parent::setUp();
    }

    public function getMockMessageProducer()
    {
        $projectService = $this->getApplicationServiceLocator()->get(Service\ProjectService::class);
        $mockMessageProducer = Mockery::mock('Oxzion\Messaging\MessageProducer');
        $projectService->setMessageProducer($mockMessageProducer);
        return $mockMessageProducer;
    }

    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__) . "/../Dataset/Project.yml");
        return $dataset;
    }

    protected function setDefaultAsserts()
    {
        $this->assertModuleName('Project');
        $this->assertControllerName(ProjectController::class); // as specified in router's controller name alias
        $this->assertControllerClass('ProjectController');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    }

    public function testGetList()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['data' => array([
            "uuid" => "886d7eff-6bae-4892-baf8-6fefc56cbf0b",
            "name" => "Test Project 1",
            "manager_id" => "4fd99e8e-758f-11e9-b2d5-68ecc57cde45",
            "description" => "Description Test Data",
        ], [
            "uuid" => "ced672bb-fe33-4f0a-b153-f1d182a02603",
            "name" => "Test Project 2",
            "manager_id" => "4fd99e8e-758f-11e9-b2d5-68ecc57cde45",
            "description" => "Description Test Data",
        ], [
            "name" => "New Project",
            "manager_id" => "1",
            "description" => "Description Test Data",
        ],
        )];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/project', 'GET');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $diff = array();
        $this->assertEquals(count($content['data']), 3);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['total'], 3);
    }

    public function testGetListByManagerWithDifferentAccount()
    {
        $this->initAuthToken($this->managerUser);
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/project?filter=[{"skip":0,"take":1}]', 'GET');
        $this->assertResponseStatusCode(401);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You do not have permissions to get the project list');
    }

    public function testGetListWithAccountId()
    {
        $this->initAuthToken($this->managerUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/project?filter=[{"skip":0,"take":1}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['name'], 'New Project');
    }

    public function testGetListByManager()
    {
        $this->initAuthToken($this->managerUser);
        $data = ['data' => array([
            "id" => "1",
            "uuid" => "886d7eff-6bae-4892-baf8-6fefc56cbf0b",
            "name" => "Test Project 1",
            "account_id" => "1",
            "manager_id" => "4fd99e8e-758f-11e9-b2d5-68ecc57cde45",
            "description" => "Description Test Data",
            "created_by" => "1",
            "modified_by" => "1",
            "date_created" => "2018-11-11 07:25:06",
            "date_modified" => "2018-12-11 07:25:06",
            "isdeleted" => "0",
            "user_id" => "1",
            "project_id" => "1",
        ],
        )];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/project?filter=[{"skip":0,"take":1}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 1);
        $this->assertEquals($content['total'], 3);
    }

    public function testGetListByEmployee()
    {
        $this->initAuthToken($this->employeeUser);
        $this->dispatch('/project?filter=[{"skip":0,"take":1}]', 'GET');
        $this->assertResponseStatusCode(401);
        $this->assertModuleName('Project');
        $this->assertControllerName(ProjectController::class); // as specified in router's controller name alias
        $this->assertControllerClass('ProjectController');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You have no Access to this API');
    }
    public function testGetListWithQuery()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['data' => array([
            "id" => "3",
            "name" => "Test Project 2",
            "account_id" => "1",
            "description" => "Description Test Data",
            "created_by" => "1",
            "modified_by" => "1",
            "date_created" => "2018-11-11 07:25:06",
            "date_modified" => "2018-12-11 07:25:06",
            "isdeleted" => "0",
            "user_id" => "1",
            "project_id" => "2",
        ])];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/project?filter=[{"filter":{"filters":[{"field":"name","operator":"endswith","value":"2"}]},"sort":[{"field":"name","dir":"asc"}],"skip":0,"take":1}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 1);
        $this->assertEquals($content['total'], 1);
    }

    public function testGetListWithQuerywithPageNo()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['data' => array([
            "id" => "1",
            "name" => "Test Project 1",
            "account_id" => "1",
            "description" => "Description Test Data",
            "created_by" => "1",
            "modified_by" => "1",
            "date_created" => "2018-11-11 07:25:06",
            "date_modified" => "2018-12-11 07:25:06",
            "isdeleted" => "0",
            "user_id" => "1",
            "project_id" => "2",
        ])];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/project?filter=[{"skip":0,"take":1}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 1);
        $this->assertEquals($content['total'], 3);
    }

    public function testGetListWithQuerywithSort()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/project?filter=[{"sort":[{"field":"name","dir":"asc"}],"skip":0,"take":3}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['uuid'], '3dbacd80-ff27-4169-a683-4a45d2a8fb8f');
        $this->assertEquals($content['data'][0]['name'], 'New Project');
        $this->assertEquals($content['data'][1]['uuid'], '886d7eff-6bae-4892-baf8-6fefc56cbf0b');
        $this->assertEquals($content['data'][1]['name'], 'Test Project 1');
        $this->assertEquals($content['data'][2]['uuid'], 'ced672bb-fe33-4f0a-b153-f1d182a02603');
        $this->assertEquals($content['data'][2]['name'], 'Test Project 2');
        $this->assertEquals($content['total'], 3);
    }

    public function testGet()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/project/886d7eff-6bae-4892-baf8-6fefc56cbf0b', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['id'], 1);
        $this->assertEquals($content['data']['name'], 'Test Project 1');
    }

    public function testGetWithAccountId()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/project/886d7eff-6bae-4892-baf8-6fefc56cbf0b', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['id'], 1);
        $this->assertEquals($content['data']['name'], 'Test Project 1');
    }

    public function testGetWithInvalidAccountId()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/project/886d7eff-6bae-4892-baf8-6fefc56cbf0b', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'], array());
    }

    public function testGetWithInvalidProjectId()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/project/886d7eff-6baf8-6fefc56cbf0b', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'], array());
    }

    public function testGetNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/project/64', 'GET');
        $this->assertResponseStatusCode(200);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'], array());
    }

    public function testCreate()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Test Project 3', 'description' => 'Project Description', 'managerId' => '4fd99e8e-758f-11e9-b2d5-68ecc57cde45'];
        $this->assertEquals(4, $this->getConnection()->getRowCount('ox_project'));
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            //Message to be sent to Mockery => json_encode(array('accountName'=> 'Cleveland Black','projectname' => 'Test Project 3','description' => 'Project Description','uuid' => '')
            // Since value of uuid changes during each project creation Mockery Message is set to Mockery::any()
            $mockMessageProducer->expects('sendTopic')->with(Mockery::any(), 'PROJECT_ADDED')->once()->andReturn();
        }
        $this->dispatch('/project', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        $projectId = $content['data']['uuid'];
        $select = "SELECT p.*, man.uuid as managerId, a.uuid as accountId
                    from ox_project p 
                    inner join ox_user man on man.id = p.manager_id
                    inner join ox_account a on a.id = p.account_id
                    where p.uuid = '".$projectId."'";
        $project = $this->executeQueryTest($select);
        $select = "SELECT * from ox_user_project where user_id =" . $project[0]['manager_id'] . " and project_id =" . $project[0]['id'];
        $oxproject = $this->executeQueryTest($select);
        $this->assertEquals($project[0]['name'], $data['name']);
        $this->assertEquals($project[0]['description'], $data['description']);
        $this->assertEquals($project[0]['managerId'], $data['managerId']);
        $this->assertEquals($oxproject[0]['user_id'], 1);
        $this->assertEquals($project[0]['manager_id'], 1);
        $this->assertEquals($project[0]['account_id'], 1);
        $this->assertEquals($project[0]['isdeleted'], 0);
        $this->assertEquals($project[0]['parent_id'], null);
        $this->assertEquals(5, $this->getConnection()->getRowCount('ox_project'));
    }

    public function testCreateWithAccountID()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Test Project 3', 'description' => 'Project Description', 'managerId' => '4fd99e8e-758f-11e9-b2d5-68ecc57cde45'];
        $this->assertEquals(4, $this->getConnection()->getRowCount('ox_project'));
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            //Message to be sent to Mockery => json_encode(array('accountName'=> 'Cleveland Black','projectname' => 'Test Project 3','description' => 'Project Description','uuid' => '')
            // Since value of uuid changes during each project creation Mockery Message is set to Mockery::any()
            $mockMessageProducer->expects('sendTopic')->with(Mockery::any(), 'PROJECT_ADDED')->once()->andReturn();
        }
        $accountId = '53012471-2863-4949-afb1-e69b0891c98a';
        $this->dispatch("/account/$accountId/project", 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        $projectId = $content['data']['uuid'];
        $select = "SELECT p.*, man.uuid as managerId, a.uuid as accountId
                    from ox_project p 
                    inner join ox_user man on man.id = p.manager_id
                    inner join ox_account a on a.id = p.account_id
                    where p.uuid = '".$projectId."'";
        $project = $this->executeQueryTest($select);
        $select = "SELECT * from ox_user_project where user_id =" . $project[0]['manager_id'] . " and project_id =" . $project[0]['id'];
        $oxproject = $this->executeQueryTest($select);
        $this->assertEquals($project[0]['name'], $data['name']);
        $this->assertEquals($project[0]['description'], $data['description']);
        $this->assertEquals($project[0]['managerId'], $data['managerId']);
        $this->assertEquals($oxproject[0]['user_id'], 1);
        $this->assertEquals($project[0]['manager_id'], 1);
        $this->assertEquals($project[0]['account_id'], 1);
        $this->assertEquals($project[0]['accountId'], $accountId);
        $this->assertEquals($project[0]['isdeleted'], 0);
        $this->assertEquals($project[0]['parent_id'], null);
        $this->assertEquals(5, $this->getConnection()->getRowCount('ox_project'));
    }

    public function testCreateWithExistingProject()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Test Project 1', 'description' => 'Project Description', 'managerId' => '4fd99e8e-758f-11e9-b2d5-68ecc57cde45'];
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/project', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(412);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Project already exists');
    }

    public function testCreateDeletedProject()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'New Project 1', 'description' => 'Project Description', 'managerId' => '4fd99e8e-758f-11e9-b2d5-68ecc57cde45'];
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/project', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(406);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Project already exists would you like to reactivate?');
    }

    public function testCreateDeletedProjectWithReactivateFlag()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'New Project 1', 'description' => 'Project Description', 'managerId' => '4fd99e8e-758f-11e9-b2d5-68ecc57cde45', 'reactivate' => 1];
        $accountId = '53012471-2863-4949-afb1-e69b0891c98a';
        $this->dispatch("/account/$accountId/project", 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        $projectId = $content['data']['uuid'];
        $select = "SELECT p.*, man.uuid as managerId, a.uuid as accountId
                    from ox_project p 
                    inner join ox_user man on man.id = p.manager_id
                    inner join ox_account a on a.id = p.account_id
                    where p.uuid = '$projectId'";
        $project = $this->executeQueryTest($select);
        $select = "SELECT * from ox_user_project where user_id =" . $project[0]['manager_id'] . " and project_id =" . $project[0]['id'];
        $oxproject = $this->executeQueryTest($select);
        $this->assertEquals($project[0]['name'], $data['name']);
        $this->assertEquals($project[0]['description'], $data['description']);
        $this->assertEquals($project[0]['managerId'], $data['managerId']);
        $this->assertEquals($oxproject[0]['user_id'], 1);
        $this->assertEquals($project[0]['manager_id'], 1);
        $this->assertEquals($project[0]['account_id'], 1);
        $this->assertEquals($project[0]['accountId'], $accountId);
        $this->assertEquals($project[0]['isdeleted'], 0);
        $this->assertEquals($project[0]['parent_id'], null);
    }

    public function testCreateWithDifferentAccountID()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Test Project 3', 'description' => 'Project Description', 'managerId' => '4fd99e8e-758f-11e9-b2d5-68ecc57cde45'];
        $accountId = 'b0971de7-0387-48ea-8f29-5d3704d96a46';
        $this->dispatch("/account/$accountId/project", 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $projectId = $content['data']['uuid'];
        $select = "SELECT p.*, man.uuid as managerId, a.uuid as accountId
                    from ox_project p 
                    inner join ox_user man on man.id = p.manager_id
                    inner join ox_account a on a.id = p.account_id
                    where p.uuid = '".$projectId."'";
        $project = $this->executeQueryTest($select);
        $select = "SELECT * from ox_user_project where user_id =" . $project[0]['manager_id'] . " and project_id =" . $project[0]['id'];
        $oxproject = $this->executeQueryTest($select);
        $this->assertEquals($project[0]['name'], $data['name']);
        $this->assertEquals($project[0]['description'], $data['description']);
        $this->assertEquals($project[0]['managerId'], $data['managerId']);
        $this->assertEquals($oxproject[0]['user_id'], 1);
        $this->assertEquals($project[0]['manager_id'], 1);
        $this->assertEquals($project[0]['account_id'], 2);
        $this->assertEquals($project[0]['accountId'], $accountId);
        $this->assertEquals($project[0]['isdeleted'], 0);
        $this->assertEquals($project[0]['parent_id'], null);
    }

    public function testCreateWithOutNameFailure()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['description' => 'Project Description', 'managerId' => '4fd99e8e-758f-11e9-b2d5-68ecc57cde45'];
        $this->setJsonContent(json_encode($data));
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
        }
        $this->dispatch('/project', 'POST', null);
        $this->assertResponseStatusCode(406);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Validation error(s).');
        $this->assertEquals($content['data']['errors']['name'], 'required');
    }

    public function testCreateAccess()
    {
        $this->initAuthToken($this->employeeUser);
        $data = ['name' => 'Test Project 1', 'description' => 'Project Description'];
        $this->setJsonContent(json_encode($data));
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
        }
        $this->dispatch('/project', 'POST', null);
        $this->assertResponseStatusCode(401);
        $this->assertModuleName('Project');
        $this->assertControllerName(ProjectController::class); // as specified in router's controller name alias
        $this->assertControllerClass('ProjectController');
        $this->assertMatchedRouteName('project');
        $this->assertResponseHeaderContains('content-type', 'application/json');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You have no Access to this API');
    }

    public function testCreateWithParentId()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Test Project 3', 'description' => 'Project Description', 'managerId' => '4fd99e8e-758f-11e9-b2d5-68ecc57cde45', 'parent_id' => '2'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/project', 'POST', null);
        $this->assertResponseStatusCode(201);
        $this->assertModuleName('Project');
        $this->assertControllerName(ProjectController::class); // as specified in router's controller name alias
        $this->assertControllerClass('ProjectController');
        $this->assertMatchedRouteName('project');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $projectId = $content['data']['uuid'];
        $select = "SELECT p.*, man.uuid as managerId, a.uuid as accountId, parent.uuid as parentId
                    from ox_project p 
                    inner join ox_project parent on parent.id = p.parent_id
                    inner join ox_user man on man.id = p.manager_id
                    inner join ox_account a on a.id = p.account_id
                    where p.uuid = '".$projectId."'";
        $project = $this->executeQueryTest($select);
        $select = "SELECT * from ox_user_project where user_id =" . $project[0]['manager_id'] . " and project_id =" . $project[0]['id'];
        $oxproject = $this->executeQueryTest($select);
        $this->assertEquals($project[0]['name'], $data['name']);
        $this->assertEquals($project[0]['description'], $data['description']);
        $this->assertEquals($project[0]['managerId'], $data['managerId']);
        $this->assertEquals($oxproject[0]['user_id'], 1);
        $this->assertEquals($project[0]['manager_id'], 1);
        $this->assertEquals($project[0]['account_id'], 1);
        $this->assertEquals($project[0]['isdeleted'], 0);
        $this->assertEquals($project[0]['parent_id'], 2);
        $this->assertEquals($content['data']['parent_id'], $project[0]['parent_id']);
    }

    public function testUpdate()
    {
        $data = ['name' => 'Test Project', 'description' => 'Project Description'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('accountName' => 'Cleveland Black', 'old_projectname' => 'Test Project 1', 'new_projectname' => 'Test Project', 'description' => 'Project Description', 'uuid' => '886d7eff-6bae-4892-baf8-6fefc56cbf0b',"parent_identifier" => null)), 'PROJECT_UPDATED')->once()->andReturn();
        }
        $projectId = '886d7eff-6bae-4892-baf8-6fefc56cbf0b';
        $this->dispatch("/project/$projectId", 'PUT', null);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $select = "SELECT p.*, man.uuid as managerId, a.uuid as accountId
                    from ox_project p 
                    inner join ox_user man on man.id = p.manager_id
                    inner join ox_account a on a.id = p.account_id
                    where p.uuid = '$projectId'";
        $project = $this->executeQueryTest($select);
        $select = "SELECT * from ox_user_project where user_id =" . $project[0]['manager_id'] . " and project_id =" . $project[0]['id'];
        $oxproject = $this->executeQueryTest($select);
        $this->assertEquals($project[0]['name'], $data['name']);
        $this->assertEquals($project[0]['description'], $data['description']);
        $this->assertEquals($oxproject[0]['user_id'], 1);
        $this->assertEquals($project[0]['manager_id'], 1);
        $this->assertEquals($project[0]['account_id'], 1);
        $this->assertEquals($project[0]['isdeleted'], 0);
        $this->assertEquals($project[0]['parent_id'], null);
    }

    public function testUpdateWithManagerID()
    {
        $data = ['name' => 'Test Project', 'description' => 'Project Description', 'managerId' => '4fd9f04d-758f-11e9-b2d5-68ecc57cde45'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('accountName' => 'Cleveland Black', 'old_projectname' => 'Test Project 1', 'new_projectname' => 'Test Project', 'description' => 'Project Description', 'uuid' => '886d7eff-6bae-4892-baf8-6fefc56cbf0b', "parent_identifier" => null, 'manager_login' => 'employeetest')), 'PROJECT_UPDATED')->once()->andReturn();
        }
        $projectId = '886d7eff-6bae-4892-baf8-6fefc56cbf0b';
        $this->dispatch("/project/$projectId", 'PUT', null);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        $select = "SELECT p.*, man.uuid as managerId, a.uuid as accountId
                    from ox_project p 
                    inner join ox_user man on man.id = p.manager_id
                    inner join ox_account a on a.id = p.account_id
                    where p.uuid = '$projectId'";
        $project = $this->executeQueryTest($select);
        $select = "SELECT * from ox_user_project where user_id =" . $project[0]['manager_id'] . " and project_id =" . $project[0]['id'];
        $oxproject = $this->executeQueryTest($select);
        $this->assertEquals($project[0]['name'], $data['name']);
        $this->assertEquals($project[0]['description'], $data['description']);
        $this->assertEquals($oxproject[0]['user_id'], 3);
        $this->assertEquals($project[0]['managerId'], $data['managerId']);
        $this->assertEquals($project[0]['manager_id'], 3);
        $this->assertEquals($project[0]['account_id'], 1);
        $this->assertEquals($project[0]['isdeleted'], 0);
        $this->assertEquals($project[0]['parent_id'], null);
    }

    public function testUpdateByManager()
    {
        $data = ['name' => 'Test Project', 'description' => 'Project Description'];
        $this->initAuthToken($this->managerUser);
        $this->setJsonContent(json_encode($data));
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('accountName' => 'Cleveland Black', 'old_projectname' => 'Test Project 1', 'new_projectname' => 'Test Project', 'description' => 'Project Description', 'uuid' => '886d7eff-6bae-4892-baf8-6fefc56cbf0b', "parent_identifier" => null)), 'PROJECT_UPDATED')->once()->andReturn();
        }
        $projectId = '886d7eff-6bae-4892-baf8-6fefc56cbf0b';
        $this->dispatch("/project/$projectId", 'PUT', null);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $select = "SELECT p.*, man.uuid as managerId, a.uuid as accountId
                    from ox_project p 
                    inner join ox_user man on man.id = p.manager_id
                    inner join ox_account a on a.id = p.account_id
                    where p.uuid = '$projectId'";
        $project = $this->executeQueryTest($select);
        $select = "SELECT * from ox_user_project where user_id =" . $project[0]['manager_id'] . " and project_id =" . $project[0]['id'];
        $oxproject = $this->executeQueryTest($select);
        $this->assertEquals($project[0]['name'], $data['name']);
        $this->assertEquals($project[0]['description'], $data['description']);
        $this->assertEquals($oxproject[0]['user_id'], 1);
        $this->assertEquals($project[0]['manager_id'], 1);
        $this->assertEquals($project[0]['account_id'], 1);
        $this->assertEquals($project[0]['isdeleted'], 0);
        $this->assertEquals($project[0]['parent_id'], null);
    }

    public function testUpdateByManagerofDifferentAccount()
    {
        $data = ['name' => 'Test Project', 'description' => 'Project Description'];
        $this->initAuthToken($this->managerUser);
        $this->setJsonContent(json_encode($data));
        $accountId = 'b0971de7-0387-48ea-8f29-5d3704d96a46';
        $projectId = '886d7eff-6bae-4892-baf8-6fefc56cbf0b';
        $this->dispatch("/account/$accountId/project/$projectId", 'PUT', null);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(401);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You do not have permissions to edit the project');
    }

    public function testUpdateInvalidAccount()
    {
        $data = ['name' => 'Test Project', 'description' => 'Project Description'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/project/886d7eff-6bae-4892-baf8-6fefc56cbf0b', 'PUT', null);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Project does not belong to the account');
    }

    public function testUpdateInvalidProjectId()
    {
        $data = ['name' => 'Test Project', 'description' => 'Project Description'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/project/886d7eff-6bae-4892-bc56cbf0b', 'PUT', null);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Updating non-existent Project');
    }

    public function testUpdateRestricted()
    {
        $data = ['name' => 'Test Project 1', 'description' => 'Project Description'];
        $this->initAuthToken($this->employeeUser);
        $this->setJsonContent(json_encode($data));
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
        }
        $this->dispatch('/project/886d7eff-6bae-4892-baf8-6fefc56cbf0b', 'PUT', null);
        $this->assertResponseStatusCode(401);
        $this->assertModuleName('Project');
        $this->assertControllerName(ProjectController::class); // as specified in router's controller name alias
        $this->assertControllerClass('ProjectController');
        $this->assertMatchedRouteName('project');
        $this->assertResponseHeaderContains('content-type', 'application/json');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You have no Access to this API');
    }

    public function testUpdateNotFound()
    {
        $data = ['name' => 'Test Project 1', 'description' => 'Project Description'];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
        }
        $this->dispatch('/project/886d7eff-6bae', 'PUT', null);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testDelete()
    {
        $this->initAuthToken($this->adminUser);
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('accountName' => 'Cleveland Black', 'projectname' => 'Test Project 2', 'uuid' => 'ced672bb-fe33-4f0a-b153-f1d182a02603')), 'PROJECT_DELETED')->once()->andReturn();
        }
        $projectId = 'ced672bb-fe33-4f0a-b153-f1d182a02603';
        $this->dispatch("/project/$projectId", 'DELETE');
        
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        $select = "SELECT p.*
                    from ox_project p 
                    where p.uuid = '$projectId'";
        $project = $this->executeQueryTest($select);
        $this->assertEquals($project[0]['isdeleted'], 1);
    }
    //TODO write tests for sub project scenarios - create, update, delete and get APIS

    public function testDeleteWithAccountId()
    {
        $this->initAuthToken($this->adminUser);
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('accountName' => 'Cleveland Black', 'projectname' => 'Test Project 2', 'uuid' => 'ced672bb-fe33-4f0a-b153-f1d182a02603')), 'PROJECT_DELETED')->once()->andReturn();
        }
        $accountId = '53012471-2863-4949-afb1-e69b0891c98a';
        $projectId = 'ced672bb-fe33-4f0a-b153-f1d182a02603';
        $this->dispatch("/account/$accountId/project/$projectId", 'DELETE');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $select = "SELECT p.*
                    from ox_project p 
                    where p.uuid = '$projectId'";
        $project = $this->executeQueryTest($select);
        $this->assertEquals($project[0]['isdeleted'], 1);
    }

    public function testDeleteWithAccountIdInvalidAccountId()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/project/ced672bb-fe33-4f0a-b153-f1d182a02603', 'DELETE');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Project does not belong to the account');
    }

    public function testDeleteByManagerOfDifferentAccount()
    {
        $this->initAuthToken($this->managerUser);
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/project/ced672bb-fe33-4f0a-b153-f1d182a02603', 'DELETE');
        $this->assertResponseStatusCode(401);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You do not have permissions to delete the project');
    }

    public function testDeleteByManager()
    {
        $this->initAuthToken($this->managerUser);
        $projectId = 'ced672bb-fe33-4f0a-b153-f1d182a02603';
        $this->dispatch("/project/$projectId", 'DELETE');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        $select = "SELECT p.*
                    from ox_project p 
                    where p.uuid = '$projectId'";
        $project = $this->executeQueryTest($select);
        $this->assertEquals($project[0]['isdeleted'], 1);
    }

    public function testDeleteNotFound()
    {
        $this->initAuthToken($this->adminUser);
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
        }
        $this->dispatch('/project/1222', 'DELETE');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Project not found');
    }

    public function testSaveUser()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['userIdList' => array(['uuid' => '4fd9ce37-758f-11e9-b2d5-68ecc57cde45'], ['uuid' => '4fd9f04d-758f-11e9-b2d5-68ecc57cde45'])];
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('accountName' => 'Cleveland Black', 'projectname' => 'Test Project 1', 'username' => $this->adminUser)), 'USERTOPROJECT_DELETED')->once()->andReturn();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('accountName' => 'Cleveland Black', 'projectname' => 'Test Project 1', 'username' => $this->employeeUser)), 'USERTOPROJECT_ADDED')->once()->andReturn();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('username' => $this->adminUser, 'projectUuid' => '886d7eff-6bae-4892-baf8-6fefc56cbf0b')), 'DELETION_USERFROMPROJECT')->once()->andReturn();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('username' => $this->employeeUser, 'firstname' => 'Employee', 'lastname' => 'Test', 'email' => 'admin3@eoxvantage.in', 'timezone' => 'United States/New York', 'projectUuid' => '886d7eff-6bae-4892-baf8-6fefc56cbf0b')), 'ADDITION_USERTOPROJECT')->once()->andReturn();
        }
        $projectId = '886d7eff-6bae-4892-baf8-6fefc56cbf0b';
        $this->dispatch("/project/$projectId/save", 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');
        $select = "SELECT u.uuid 
                    from ox_project p 
                    inner join ox_user_project up on up.project_id = p.id
                    inner join ox_user u on u.id = up.user_id
                    where p.uuid = '$projectId'";
        $project = $this->executeQueryTest($select);
        $this->assertEquals($data['userIdList'], $project);
    }

    public function testSaveUserWithAccountId()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['userIdList' => array(['uuid' => '4fd9ce37-758f-11e9-b2d5-68ecc57cde45'], ['uuid' => '4fd9f04d-758f-11e9-b2d5-68ecc57cde45'])];
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('accountName' => 'Cleveland Black', 'projectname' => 'Test Project 1', 'username' => $this->adminUser)), 'USERTOPROJECT_DELETED')->once()->andReturn();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('accountName' => 'Cleveland Black', 'projectname' => 'Test Project 1', 'username' => $this->employeeUser)), 'USERTOPROJECT_ADDED')->once()->andReturn();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('username' => $this->adminUser, 'projectUuid' => '886d7eff-6bae-4892-baf8-6fefc56cbf0b')), 'DELETION_USERFROMPROJECT')->once()->andReturn();
            $mockMessageProducer->expects('sendTopic')->with(json_encode(array('username' => $this->employeeUser, 'firstname' => 'Employee', 'lastname' => 'Test', 'email' => 'admin3@eoxvantage.in', 'timezone' => 'United States/New York', 'projectUuid' => '886d7eff-6bae-4892-baf8-6fefc56cbf0b')), 'ADDITION_USERTOPROJECT')->once()->andReturn();
        }
        $projectId = '886d7eff-6bae-4892-baf8-6fefc56cbf0b';
        $this->dispatch("/account/53012471-2863-4949-afb1-e69b0891c98a/project/$projectId/save", 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $select = "SELECT u.uuid 
                    from ox_project p 
                    inner join ox_user_project up on up.project_id = p.id
                    inner join ox_user u on u.id = up.user_id
                    where p.uuid = '$projectId'";
        $project = $this->executeQueryTest($select);
        $this->assertEquals($data['userIdList'], $project);
    }

    public function testSaveUserByManagerOfDifferentAccount()
    {
        $this->initAuthToken($this->managerUser);
        $data = ['userIdList' => array(['uuid' => '4fd9ce37-758f-11e9-b2d5-68ecc57cde45'], ['uuid' => '4fd9f04d-758f-11e9-b2d5-68ecc57cde45'])];
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/project/886d7eff-6bae-4892-baf8-6fefc56cbf0b/save', 'POST', $data);
        $this->assertResponseStatusCode(401);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You do not have permissions to add users to project');
    }

    public function testSaveUserInvalidAccount()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['userIdList' => array(['uuid' => '4fd9ce37-758f-11e9-b2d5-68ecc57cde45'], ['uuid' => '4fd9f04d-758f-11e9-b2d5-68ecc57cde45'])];
        $this->dispatch('/account/b0971de7-0387-48e5d3704d96a46/project/886d7eff-6bae-4892-baf8-6fefc56cbf0b/save', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Project does not belong to the account');
    }

    public function testSaveUserInvalidProject()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['userIdList' => array(['uuid' => '4fd9ce37-758f-11e9-b2d5-68ecc57cde45'], ['uuid' => '4fd9f04d-758f-11e9-b2d5-68ecc57cde45'])];
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/project/8-6bae-4892-baf8-6fefc56cbf0b/save', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Project not found');
    }

    public function testSaveUserWithoutUser()
    {
        $this->initAuthToken($this->adminUser);
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
        }
        $this->dispatch('/project/886d7eff-6bae-4892-baf8-6fefc56cbf0b/save', 'POST');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Users not selected');
    }

    public function testSaveUserNotFound()
    {
        $this->initAuthToken($this->adminUser);

        $data = ['userIdList' => array(['uuid' => '4fd99e8e-758f-11ikwsd-dbnb'], ['uuid' => '4fd99e8e-758f-11e9'])];

        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
        }
        $this->dispatch('/project/886d7eff-6bae-4892-baf8-6fefc56cbf0b/save', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Users not found');
    }

    public function testGetListOfUsers()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/project/886d7eff-6bae-4892-baf8-6fefc56cbf0b/users', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 2);
        $this->assertEquals($content['data'][0]['uuid'], '4fd99e8e-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][0]['name'], 'Admin Test');
        $this->assertEquals($content['data'][1]['uuid'], '4fd9ce37-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][1]['name'], 'Manager Test');
        $this->assertEquals($content['total'], 2);
    }

    public function testGetListOfUsersWithAccountID()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/project/886d7eff-6bae-4892-baf8-6fefc56cbf0b/users', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 2);
        $this->assertEquals($content['data'][0]['uuid'], '4fd99e8e-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][0]['name'], 'Admin Test');
        $this->assertEquals($content['data'][1]['uuid'], '4fd9ce37-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][1]['name'], 'Manager Test');
        $this->assertEquals($content['total'], 2);
    }

    public function testGetListOfUsersWithInvalidAccountID()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/project/886d7eff-6bae-4892-baf8-6fefc56cbf0b/users', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'], array());
    }

    public function testGetListOfUsersWithInvalidProjectId()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/project/886d7eff-6bae-4892-baf8-6f/users', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'], array());
    }

    public function testGetListOfUsersByManagerofDifferentAccount()
    {
        $this->initAuthToken($this->managerUser);
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/project/886d7eff-6bae-4892-baf8-6fefc56cbf0b/users?filter=[{"take":20,"skip":0}]', 'GET');
        $this->assertResponseStatusCode(401);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You do not have permissions to get the user list of project');
    }

    public function testGetListOfUsersWithQuery()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/project/886d7eff-6bae-4892-baf8-6fefc56cbf0b/users?filter=[{"filter":{"filters":[{"field":"name","operator":"endswith","value":"r Test"}]},"sort":[{"field":"id","dir":"desc"}],"skip":0,"take":1}]
    ', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 1);
        $this->assertEquals($content['data'][0]['uuid'], '4fd9ce37-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][0]['name'], 'Manager Test');
        $this->assertEquals($content['total'], 1);
    }

    public function testGetListOfUsersWithPageNoQuery()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/project/886d7eff-6bae-4892-baf8-6fefc56cbf0b/users?filter=[{"skip":1,"take":1}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 1);
        $this->assertEquals($content['data'][0]['uuid'], '4fd9ce37-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][0]['name'], 'Manager Test');
        $this->assertEquals($content['total'], 2);
    }

    public function testGetListOfUsersWithQueryParameter()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/project/886d7eff-6bae-4892-baf8-6fefc56cbf0b/users?filter=[{"filter":{"filters":[{"field":"name","operator":"startswith","value":"Manager"}]},"sort":[{"field":"id","dir":"asc"}],"skip":0,"take":1}]', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 1);
        $this->assertEquals($content['data'][0]['uuid'], '4fd9ce37-758f-11e9-b2d5-68ecc57cde45');
        $this->assertEquals($content['data'][0]['name'], 'Manager Test');
        $this->assertEquals($content['total'], 1);
    }

    public function testGetListOfUsersNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/project/64/users', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'], array());
        $this->assertEquals($content['total'], 0);
    }

    public function testGetMyProjectList()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['data' => array([
            "id" => "1",
            "name" => "Test Project 1",
            "account_id" => "1",
            "description" => "Description Test Data",
            "created_by" => "1",
            "modified_by" => "1",
            "date_created" => "2018-11-11 07:25:06",
            "date_modified" => "2018-12-11 07:25:06",
            "isdeleted" => "0",
            "user_id" => "1",
            "project_id" => "1",
        ], [
            "id" => "3",
            "name" => "Test Project 2",
            "account_id" => "1",
            "description" => "Description Test Data",
            "created_by" => "1",
            "modified_by" => "1",
            "date_created" => "2018-11-11 07:25:06",
            "date_modified" => "2018-12-11 07:25:06",
            "isdeleted" => "0",
            "user_id" => "1",
            "project_id" => "2",
        ])];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/project/myproject', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('myproject');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 1);
    }

    public function testGetMyProjectListWithAccountId()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['data' => array([
            "id" => "1",
            "name" => "Test Project 1",
            "account_id" => "1",
            "description" => "Description Test Data",
            "created_by" => "1",
            "modified_by" => "1",
            "date_created" => "2018-11-11 07:25:06",
            "date_modified" => "2018-12-11 07:25:06",
            "isdeleted" => "0",
            "user_id" => "1",
            "project_id" => "1",
        ], [
            "id" => "3",
            "name" => "Test Project 2",
            "account_id" => "1",
            "description" => "Description Test Data",
            "created_by" => "1",
            "modified_by" => "1",
            "date_created" => "2018-11-11 07:25:06",
            "date_modified" => "2018-12-11 07:25:06",
            "isdeleted" => "0",
            "user_id" => "1",
            "project_id" => "2",
        ])];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/project/myproject', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('myproject');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(count($content['data']), 1);
    }

    public function testGetMyProjectListByManager()
    {
        $this->initAuthToken($this->managerUser);
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/project/myproject', 'GET');
        $this->assertResponseStatusCode(401);
        $this->setDefaultAsserts('myproject');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You do not have permissions to get the users of project');
    }

    public function testGetMyProjectListDifferentAccount()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/project/myproject', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('myproject');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'], array());
    }

    public function testGetMyProjectListWithoutdata()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/project/myproject', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('myproject');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    //TODO Subproject Tests
    public function testCreateSubprojectWithNewManager()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Test Project 4', 'description' => 'Child of Test Project 1', 'managerId' => '4fd9ce37-758f-11e9-b2d5-68ecc57cde45','parentId'=>'886d7eff-6bae-4892-baf8-6fefc56cbf0b'];
        
        //Check if the projects defined in Dataset\\Project.yml have been inserted correctly
        $this->assertEquals(4, $this->getConnection()->getRowCount('ox_project'));

        //Create Subproject
        $this->dispatch('/project', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $this->assertEquals($content['status'], 'success');

        $subprojectUuid= $content['data']['uuid'];

        //Get the newly created row
        $select = "SELECT op.*,ou.uuid as manager_uuid  FROM ox_project as op INNER JOIN ox_user as ou on op.manager_id=ou.id WHERE op.uuid = '".$subprojectUuid."'";
        $subprojectData = $this->executeQueryTest($select);

        //Get the data from the row
        $subprojectId = $subprojectData[0]['id'];
        $subprojectParentId = $subprojectData[0]['parent_id'];
        $subprojectManagerUuid = $subprojectData[0]['manager_uuid'];
        $subprojectManagerId = $subprojectData[0]['manager_id'];
        $subprojectName = $subprojectData[0]['name'];
        $subprojectDescription = $subprojectData[0]['description'];

        //Get the row corresponding to the parent project
        $select = "SELECT op.*, ou.uuid as parent_manager_uuid FROM ox_project as op INNER JOIN ox_user as ou on op.manager_id=ou.id WHERE op.id = '".$subprojectParentId."'";
        $parentProjectData = $this->executeQueryTest($select);

        $subprojectParentManagerUuId = $parentProjectData[0]['parent_manager_uuid'];
        $subprojectParentManagerId = $parentProjectData[0]['manager_id'];
        $subprojectParentUuid = $parentProjectData[0]['uuid'];

        $this->assertEquals($subprojectManagerUuid, $data['managerId']);
        $this->assertEquals($subprojectName, $data['name']);
        $this->assertEquals($subprojectDescription, $data['description']);
        $this->assertEquals($subprojectParentUuid, $data['parentId']);
    
        //Get the users corresponding to the project

        $select = "SELECT user_id FROM ox_user_project WHERE project_id = '".$subprojectId."' AND user_id = '".$subprojectManagerId."'";
        $subprojectManagerData = $this->executeQueryTest($select);

        $select = "SELECT user_id FROM ox_user_project WHERE project_id = '".$subprojectId."' AND user_id = '".$subprojectParentManagerId."'";
        $subprojectParentManagerData = $this->executeQueryTest($select);

        $this->assertEquals($subprojectManagerData[0]['user_id'], $subprojectManagerId);
        $this->assertEquals($subprojectParentManagerData[0]['user_id'], $subprojectParentManagerId);
    }
}
