<?php
namespace App;

use Mockery;
use Oxzion\Service\CommandService;
use Oxzion\Service\BusinessParticipantService;
use Oxzion\Test\ControllerTest;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Oxzion\Utils\FileUtils;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;

class PipelineControllerTest extends ControllerTest
{
    public function setUp(): void
    {
        $this->loadConfig();
        parent::setUp();
    }

    public function getDataSet()
    {
        switch ($this->getName()) {
            case 'testsetupBusinessRelationshipWithSellerAccountName':
            case 'testsetupBusinessRelationshipWithSellerAccountId':
            case 'testGetEntitySellerAccountWhenBuyerIsLoggedIn':
            case 'testsetupBusinessRelationshipWithSellerLoggedIn':
            case 'testCheckIfBusinessRelationshipExists':
                return new YamlDataSet(dirname(__FILE__) . "/../../Dataset/BusinessRelationship.yml");;
            break;
        }
        $dataset = new YamlDataSet(dirname(__FILE__) . "/../../Dataset/Workflow.yml");
        return $dataset;
    }

    public function getMockMessageProducer()
    {
        $serviceTaskService = $this->getApplicationServiceLocator()->get(CommandService::class);
        $mockMessageProducer = Mockery::mock('Oxzion\Messaging\MessageProducer');
        $serviceTaskService->setMessageProducer($mockMessageProducer);
        return $mockMessageProducer;
    }

    private function getMockRestClientForScheduleService()
    {
        $jobService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\JobService::class);
        $mockRestClient = Mockery::mock('Oxzion\Utils\RestClient');
        $jobService->setRestClient($mockRestClient);
        return $mockRestClient;
    }

    public function getBusinessParticipantService(){
        return $this->getApplicationServiceLocator()->get(BusinessParticipantService::class);
    }

    public function testPipelineMailExecution()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['command' => 'mail', 'to' => 'admintest@myvamla.com', 'body' => 'create a new body', 'subject' => 'NewSubject'];
        $this->setJsonContent(json_encode($data));
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $payload = json_encode(array('to' => $data['to'], 'subject' => $data['subject'], 'body' => $data['body'], 'attachments' => null));
            $mockMessageProducer->expects('sendQueue')->with($payload, 'mail')->once()->andReturn(123);
        }
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
    }

    public function testPipelineWithoutSubjectExecution()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['command' => 'mail', 'to' => 'admintest@myvamla.com', 'body' => 'create a new body'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(406);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Validation error(s).');
        $this->assertEquals($content['data']['errors']['subject'], 'required');
    }

    public function testPipelineWithoutRecepientMailExecution()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['command' => 'mail', 'body' => 'create a new body', 'subject' => 'NewSubject'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(406);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Validation error(s).');
        $this->assertEquals($content['data']['errors']['to'], 'required');
    }

    public function testPipelineSchedule()
    {
        $this->initAuthToken($this->adminUser);
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd",
                 "processInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd",
                 "command" => "schedule",
                 "firstname" => "Neha",
                 "policy_period" => "1year",
                 "card_expiry_date" => "10/24",
                 "city" => "Bangalore",
                 "orgUuid" => "53012471-2863-4949-afb1-e69b0891c98a",
                 "isequipmentliability" => "1",
                 "card_no" => "1234",
                 "jobUrl" => "/app/ec8942b7-aa93-4bc6-9e8c-e1371988a5d4/delegate/DispatchAutoRenewalNotification",
                 "state" => "karnataka",
                 "cron" => "0 0/1 * * * ? *",
                 "zip" => "560030",
                 "coverage" => "100000",
                 "product" => "Individual Professional Liability",
                 "address2" => "dhgdhdh",
                 "address1" => "hjfjhfjfjfhfg",
                 "expiry_date" => "2020-06-30",
                 "form_id" => "0",
                 "entity_id" => "1",
                 "created_by" => "1",
                 "url" => "setupjob",
                 "jobName" => "autoRenewalJob",
                 "expiry_year" => "2019",
                 "orgid" => "53012471-2863-4949-afb1-e69b0891c98a",
                 "lastname" => "Rai",
                 "isexcessliability" => "1",
                 "credit_card_type" => "credit",
                 "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925",
                 "fileId" => "d13d0c68-98c9-11e9-adc5-308d99c9145b",
                 "email" => 'bharat@gmail.com',
                 "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd",
                 "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];

        $this->setJsonContent(json_encode($data));
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $mockRestClient->expects('postWithHeader')->with("setupjob", Mockery::any())->once()->andReturn(["body" => '{"Success" : true, "JobId" : "123456", "Message" : "Job setup was successful"}']);
        }
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertEquals("success", $content['status']);
        $this->assertEquals(true, isset($content['data'][$data['jobName']]));
        $jobData = json_decode($content['data'][$data['jobName']], true);
        $this->assertEquals(123456, $jobData['jobId']);
        $this->assertEquals($data['jobName'], $jobData['jobGroup']);
    }

    public function testPipelineScheduleWithoutRequiredFields()
    {
        $this->initAuthToken($this->adminUser);
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd",
                 "processInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd",
                 "firstname" => "Neha",
                 "policy_period" => "1year",
                 "card_expiry_date" => "10/24",
                 "city" => "Bangalore",
                 "orgUuid" => "53012471-2863-4949-afb1-e69b0891c98a",
                 "isequipmentliability" => "1",
                 "card_no" => "1234",
                 "jobUrl" => "/app/ec8942b7-aa93-4bc6-9e8c-e1371988a5d4/delegate/DispatchAutoRenewalNotification",
                 "state" => "karnataka",
                 "cron" => "0 0/1 * * * ? *",
                 "zip" => "560030",
                 "coverage" => "100000",
                 "product" => "Individual Professional Liability",
                 "address2" => "dhgdhdh",
                 "address1" => "hjfjhfjfjfhfg",
                 "expiry_date" => "2020-06-30",
                 "form_id" => "0",
                 "entity_id" => "1",
                 "created_by" => "1",
                 "url" => "setupjob",
                 "command" => "schedule",
                 "expiry_year" => "2019",
                 "orgid" => "53012471-2863-4949-afb1-e69b0891c98a",
                 "lastname" => "Rai",
                 "isexcessliability" => "1",
                 "credit_card_type" => "credit",
                 "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925",
                 "fileId" => "d13d0c68-98c9-11e9-adc5-308d99c9145b",
                 "email" => 'bharat@gmail.com',
                 "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd",
                 "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];

        $this->setJsonContent(json_encode($data));
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $mockRestClient->expects('postWithHeader')->with("setupjob", Mockery::any())->once()->andReturn(["body" => '{"Success" : true, "JobId" : "123456", "Message" : "Job setup was successful"}']);
        }
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'JobUrl or Cron Expression or JobName Not Specified');
    }

    public function testPipelineCancelJob()
    {
        $this->initAuthToken($this->adminUser);
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "processInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "firstname" => "Neha", "policy_period" => "1year", "card_expiry_date" => "10/24", "city" => "Bangalore", "orgUuid" => "53012471-2863-4949-afb1-e69b0891c98a", "isequipmentliability" => "1", "card_no" => "1234", "state" => "karnataka", "zip" => "560030", "coverage" => "100000", "product" => "Individual Professional Liability", "address2" => "dhgdhdh", "address1" => "hjfjhfjfjfhfg", "expiry_date" => "2020-06-30", "form_id" => "0", "entity_id" => "1", "created_by" => "1", "url" => "canceljob", "command" => "cancelJob", "jobName" => "autoRenewalJob", "autoRenewalJob" => '{"jobId":"14b5370e-a580-4b80-a17a-a13be8b47ee0","jobTeam":"Job"}', "expiry_year" => "2019", "orgid" => "53012471-2863-4949-afb1-e69b0891c98a", "lastname" => "Rai", "isexcessliability" => "1", "workflow_instance_id" => "142", "credit_card_type" => "credit", "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925", "fileId" => "134", "email" => 'bharat@gmail.com', "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];

        $this->setJsonContent(json_encode($data));
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $mockRestClient->expects('postWithHeader')->with("canceljob", Mockery::any())->once()->andReturn();
        }
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $this->assertResponseStatusCode(200);
    }

    public function testPipelineCancelJobWithoutJobName()
    {
        $this->initAuthToken($this->adminUser);
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "processInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "firstname" => "Neha", "policy_period" => "1year", "card_expiry_date" => "10/24", "city" => "Bangalore", "orgUuid" => "53012471-2863-4949-afb1-e69b0891c98a", "isequipmentliability" => "1", "card_no" => "1234", "state" => "karnataka", "zip" => "560030", "coverage" => "100000", "product" => "Individual Professional Liability", "address2" => "dhgdhdh", "address1" => "hjfjhfjfjfhfg", "expiry_date" => "2020-06-30", "form_id" => "0", "entity_id" => "1", "created_by" => "1", "url" => "canceljob", "command" => "cancelJob", "autoRenewalJob" => '{"jobId":"14b5370e-a580-4b80-a17a-a13be8b47ee0","jobTeam":"Job"}', "expiry_year" => "2019", "orgid" => "53012471-2863-4949-afb1-e69b0891c98a", "lastname" => "Rai", "isexcessliability" => "1", "workflow_instance_id" => "142", "credit_card_type" => "credit", "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925", "fileId" => "134", "email" => 'bharat@gmail.com', "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];

        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertEquals($content['status'], 'success');
    }

    public function testPipelineCancelJobWithoutJobID()
    {
        $this->initAuthToken($this->adminUser);
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "processInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "firstname" => "Neha", "policy_period" => "1year", "card_expiry_date" => "10/24", "city" => "Bangalore", "orgUuid" => "53012471-2863-4949-afb1-e69b0891c98a", "isequipmentliability" => "1", "card_no" => "1234", "state" => "karnataka", "zip" => "560030", "coverage" => "100000", "product" => "Individual Professional Liability", "address2" => "dhgdhdh", "address1" => "hjfjhfjfjfhfg", "expiry_date" => "2020-06-30", "form_id" => "0", "entity_id" => "1", "created_by" => "1", "url" => "canceljob", "command" => "cancelJob", "jobName" => "autoRenewalJob", "autoRenewalJob" => '{"jobID":"14b5370e-a580-4b80-a17a-a13be8b47ee0","jobteam":"Job"}', "expiry_year" => "2019", "orgid" => "53012471-2863-4949-afb1-e69b0891c98a", "lastname" => "Rai", "isexcessliability" => "1", "workflow_instance_id" => "142", "credit_card_type" => "credit", "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925", "fileId" => "134", "email" => 'bharat@gmail.com', "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];

        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testPipelineCancelJobEmpty()
    {
        $this->initAuthToken($this->adminUser);
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "processInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "firstname" => "Neha", "policy_period" => "1year", "card_expiry_date" => "10/24", "city" => "Bangalore", "orgUuid" => "53012471-2863-4949-afb1-e69b0891c98a", "isequipmentliability" => "1", "card_no" => "1234", "state" => "karnataka", "zip" => "560030", "coverage" => "100000", "product" => "Individual Professional Liability", "address2" => "dhgdhdh", "address1" => "hjfjhfjfjfhfg", "expiry_date" => "2020-06-30", "form_id" => "0", "entity_id" => "1", "created_by" => "1", "url" => "canceljob", "command" => "cancelJob", "jobName" => "autoRenewalJob", "expiry_year" => "2019", "orgid" => "53012471-2863-4949-afb1-e69b0891c98a", "lastname" => "Rai", "isexcessliability" => "1", "workflow_instance_id" => "142", "credit_card_type" => "credit", "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925", "fileId" => "134", "email" => 'bharat@gmail.com', "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];

        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testPipelineCancelInvalidJob()
    {
        $this->initAuthToken($this->adminUser);
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "processInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "firstname" => "Neha", "policy_period" => "1year", "card_expiry_date" => "10/24", "city" => "Bangalore", "orgUuid" => "53012471-2863-4949-afb1-e69b0891c98a", "isequipmentliability" => "1", "card_no" => "1234", "state" => "karnataka", "zip" => "560030", "coverage" => "100000", "product" => "Individual Professional Liability", "address2" => "dhgdhdh", "address1" => "hjfjhfjfjfhfg", "expiry_date" => "2020-06-30", "form_id" => "0", "entity_id" => "1", "created_by" => "1", "url" => "canceljob", "command" => "cancelJob", "jobName" => "autoRenewalJob", "autoRenewalJob" => '{"jobId":"f4f7833e-7e34-4b00-bcab-ef6048e7fbcb","jobTeam":"Job"}', "expiry_year" => "2019", "orgid" => "53012471-2863-4949-afb1-e69b0891c98a", "lastname" => "Rai", "isexcessliability" => "1", "workflow_instance_id" => "142", "credit_card_type" => "credit", "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925", "fileId" => "134", "email" => 'bharat@gmail.com', "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];

        $this->setJsonContent(json_encode($data));
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $exception = Mockery::Mock('\GuzzleHttp\Exception\ClientException');
            $request = Mockery::Mock('\Psr\Http\Message\RequestInterface');
            $exception = new \GuzzleHttp\Exception\ClientException('Client error: `POST http://172.16.1.95:8085/canceljob` resulted in a `404 Not Found` response:{"timestamp":"2019-11-05T13:06:56.308+0000","status":404,"error":"Not Found","message":"Job Does not Exists","path":"/ca (truncated...)', $request);
            $mockRestClient->expects('postWithHeader')->with("canceljob", array('jobid' => 'f4f7833e-7e34-4b00-bcab-ef6048e7fbcb', 'jobteam' => 'Job'))->once()->andThrow($exception);
        }
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
    }

    public function testFileSave()
    {
        $this->initAuthToken($this->adminUser);
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "processInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "firstname" => "Neha", "policy_period" => "1year", "card_expiry_date" => "10/24", "city" => "Bangalore", "orgUuid" => "53012471-2863-4949-afb1-e69b0891c98a", "isequipmentliability" => "1", "card_no" => "1234", "state" => "karnataka", "zip" => "560030", "coverage" => "100000", "product" => "Individual Professional Liability", "address2" => "dhgdhdh", "address1" => "hjfjhfjfjfhfg", "expiry_date" => "2020-06-30", "form_id" => "0", "entity_id" => "1", "created_by" => "1", "command" => "fileSave", "expiry_year" => "2019", "orgid" => "53012471-2863-4949-afb1-e69b0891c98a", "lastname" => "Rai", "isexcessliability" => "1", "workflow_instance_id" => "1", "credit_card_type" => "credit", "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925", "fileId" => "d13d0c68-98c9-11e9-adc5-308d99c9145b", "email" => 'bharat@gmail.com', "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $query = "Select data from ox_file where uuid = 'd13d0c68-98c9-11e9-adc5-308d99c9145b'";
        $result = $this->executeQueryTest($query);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertEquals($content['status'], 'success');
        unset($data['command'], $data['orgid'], $data['fileId'], $data['appId'], $data['form_id'], $data['created_by'], $data['entity_id'], $data['workflow_instance_id'], $data['workflowId']);
    }

    public function testFileSaveInvalidWokflowInstanceID()
    {
        $this->initAuthToken($this->adminUser);
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "processInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "firstname" => "Neha", "policy_period" => "1year", "card_expiry_date" => "10/24", "city" => "Bangalore", "orgUuid" => "53012471-2863-4949-afb1-e69b0891c98a", "isequipmentliability" => "1", "card_no" => "1234", "state" => "karnataka", "zip" => "560030", "coverage" => "100000", "product" => "Individual Professional Liability", "address2" => "dhgdhdh", "address1" => "hjfjhfjfjfhfg", "expiry_date" => "2020-06-30", "form_id" => "0", "entity_id" => "1", "created_by" => "1", "command" => "fileSave", "expiry_year" => "2019", "orgid" => "53012471-2863-4949-afb1-e69b0891c98a", "lastname" => "Rai", "isexcessliability" => "1", "workflow_instance_id" => "225", "credit_card_type" => "credit", "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925", "email" => 'bharat@gmail.com', "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $query = "Select data from ox_file where uuid = 'd13d0c68-98c9-11e9-adc5-308d99c9145b'";
        $result = $this->executeQueryTest($query);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Workflow Instance Id Not Found');
    }

    public function testExtractFile()
    {
        $this->initAuthToken($this->adminUser);
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "processInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "command" => "file", "orgId" => "53012471-2863-4949-afb1-e69b0891c98a", "fileId" => "d13d0c68-98c9-11e9-adc5-308d99c9145b", "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(is_array($content['data']), true);
    }

    public function testGenerateMultipleCommands()
    {
        $this->initAuthToken($this->adminUser);
        $date = date('Y-m-d');
        $currentDate = date('Y-m-d', strtotime($date . ' + 1 days'));
        $params = ["commands" => array('{"command":"filelist", "filter" : "' . '[{\"filter\":{\"filters\":[{\"field\":\"expiry_date\",\"operator\":\"lt\",\"value\":\"' . $currentDate . '\"}]},\"sort\":[{\"field\":\"expiry_date\",\"dir\":\"asc\"}],\"skip\":0,\"take\":1}]' . '"' . '}', '{"command":"sign_in"}'), 'orgId' => "53012471-2863-4949-afb1-e69b0891c98a", "app_id" => "1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4", "workFlowId" => "1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4", "userId" => null];
        $this->setJsonContent(json_encode($params));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $params);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['appId'], "1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4");
        $this->assertEquals($content['data']['auto_login'], 1);
    }
    public function testGetStartFormCommands()
    {
        $this->initAuthToken($this->adminUser);
        $date = date('Y-m-d');
        $appId = "1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4";
        $config = $this->getApplicationConfig();
        $formFolder = $config['FORM_FOLDER'];
        $file = "/Test-Form-2.json";
        $linkFolder = $formFolder.$appId;
        FileUtils::createDirectory($linkFolder);
        $link = $linkFolder.$file;
        $target = __DIR__."/../../Dataset/Test-Form-2.json";
        FileUtils::symlink($target, $link);
        $currentDate = date('Y-m-d', strtotime($date . ' + 1 days'));
        $params = json_decode('{"commands": [{"command":"startform","workflow_id":"1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4"}]}', true);
        $this->setJsonContent(json_encode($params));
        $this->dispatch("/app/$appId/pipeline", 'POST', $params);
        FileUtils::rmDir($linkFolder);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['appId'], "1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4");
    }
    
    public function testPipelineSnooze()
    {
        $this->initAuthToken($this->adminUser);
        $data = ["command" => "snooze","snoozePipeline"=>"1", "fileId" => "d13d0c68-98c9-11e9-adc5-308d99c9145b"];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $query = "Select * from ox_file where uuid = 'd13d0c68-98c9-11e9-adc5-308d99c9145b'";
        $result = $this->executeQueryTest($query);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($result[0]["is_snoozed"],1);
        
    }

    public function testsetupBusinessRelationshipWithSellerAccountName(){
        $this->initAuthToken($this->adminUser);
        $query = "Select * from ox_business_relationship";
        $result = $this->executeQueryTest($query);
        $this->assertEquals(2, count($result));
        $data = ['command' => 'setupBusinessRelationship' , 'sellerAccountName' => 'Sample Organization' , 'buyerAccountId' => 'b6499a34-c100-4e41-bece-5822adca3abc' , 'businessRole' =>'Independent Contractor' ,'sellerBusinessRole' => 'Contractor carrier'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $query = "Select * from ox_business_relationship";
        $result = $this->executeQueryTest($query);
        $this->assertEquals(3, count($result));
    }

    public function testsetupBusinessRelationshipWithSellerAccountId(){
        $this->initAuthToken($this->adminUser);
        $query = "Select * from ox_business_relationship";
        $result = $this->executeQueryTest($query);
        $this->assertEquals(2, count($result));
        $data = ['command' => 'setupBusinessRelationship' , 'sellerAccountId' => 'b6499a34-c100-4e41-bece-5822adca3844' , 'buyerAccountId' => 'b6499a34-c100-4e41-bece-5822adca3abc' , 'businessRole' =>'Independent Contractor' ,'sellerBusinessRole' => 'Contractor carrier'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $query = "Select * from ox_business_relationship";
        $result = $this->executeQueryTest($query);
        $this->assertEquals(3, count($result));
    }

    public function testsetupBusinessRelationshipWithSellerLoggedIn(){
        $query = "Select * from ox_user";
        $result = $this->executeQueryTest($query);
        $this->noUser = $result[5]['username'];
        $this->noUserId = $result[5]['uuid'];
        $this->testAccountId = 3;
        $this->testAccountUuid = 'b6499a34-c100-4e41-bece-5822adca3844';
        AuthContext::put(AuthConstants::ACCOUNT_ID, $this->testAccountId);
        AuthContext::put(AuthConstants::ACCOUNT_UUID, $this->testAccountUuid);
        $this->initAuthToken($this->noUser);
        $query = "Select * from ox_business_relationship";
        $result = $this->executeQueryTest($query);
        $this->assertEquals(2, count($result));
        $data = ['command' => 'setupBusinessRelationship' , 'buyerAccountId' => 'b6499a34-c100-4e41-bece-5822adca3abc' , 'businessRole' =>'Independent Contractor' ,'sellerBusinessRole' => 'Contractor carrier'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $query = "Select * from ox_business_relationship";
        $result = $this->executeQueryTest($query);
        $this->assertEquals(3, count($result));
    }
    

    public function testGetEntitySellerAccountWhenBuyerIsLoggedIn(){
        $query = "Select * from ox_user";
        $result = $this->executeQueryTest($query);
        $this->noUser = $result[5]['username'];
        $this->noUserId = $result[5]['uuid'];
        $this->testAccountId = 3;
        $this->testAccountUuid = 'b6499a34-c100-4e41-bece-5822adca3844';
        AuthContext::put(AuthConstants::ACCOUNT_ID, $this->testAccountId);
        AuthContext::put(AuthConstants::ACCOUNT_UUID, $this->testAccountUuid);
        $this->initAuthToken($this->noUser);
        $query = "Select * from ox_business_relationship";
        $result = $this->executeQueryTest($query);
        $this->assertEquals(2, count($result));
        $data = ['entityId' => 1];
        $getBusinessParticipantService = $this->getBusinessParticipantService();
        $content = $getBusinessParticipantService->getEntitySellerAccount($data['entityId']);
        $query = "SELECT sbr.account_id as sellerAccountId, bbr.account_id as buyerAccountId
                   from ox_business_relationship obr 
                   inner join ox_account_business_role sbr on sbr.id = obr.seller_account_business_role_id
                   inner join ox_account_business_role bbr on bbr.id = obr.buyer_account_business_role_id
                   inner join ox_account_offering oof on sbr.id = oof.account_business_role_id
                   where oof.entity_id = 1 and bbr.account_id = 3 ";
        $result = $this->executeQueryTest($query);
        $this->assertEquals($result[0]['sellerAccountId'], $content);
    }

     public function testCheckIfBusinessRelationshipExists(){
        $query = "Select * from ox_account";
        $result = $this->executeQueryTest($query);
        $buyerAccountId = $result[2]['id'];
        $sellerAccountId = $result[4]['id'];
        AuthContext::put(AuthConstants::ACCOUNT_ID, $buyerAccountId);
        $this->initAuthToken($this->adminUser);
        $entityId = 1;
        $getBusinessParticipantService = $this->getBusinessParticipantService();
        $content = $getBusinessParticipantService->checkIfBusinessRelationshipExists($entityId,$buyerAccountId, $sellerAccountId);
        $this->assertTrue($content);
    }

    public function testGeneratePdfByHtmlTemplateWithNoBody(){
        $this->initAuthToken($this->adminUser);
        $data = ['command' => 'pdf',"fileId"=> "cdbbe380-77a7-4dfb-83ed-4e2ae058d387"];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals("success", $content['status']);
    }

    public function testGeneratePdfByHtmlTemplate(){
        $this->initAuthToken($this->adminUser);
        $data = ['command' => 'pdf',"firstname"=> "tahekuxem","fileId"=> "cdbbe380-77a7-4dfb-83ed-4e2ae058d387",'body' => 'HELLO ALLL!!!'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals("success", $content['status']);
        $this->assertEquals($data['firstname'], $content['data']['firstname']);
        $this->assertEquals($data['body'], $content['data']['body']);
        $this->assertNotNull($content['data']['document_path']);
        $this->assertNotNull($content['data']['attachments']);
    }

    public function testGeneratePdfByHtmlTemplateInSpecifiedPath(){
        $this->initAuthToken($this->adminUser);
        $data = ['command' => 'pdf',"firstname"=> "tahekuxem","fileId"=> "cdbbe380-77a7-4dfb-83ed-4e2ae058d387",'body' => 'HELLO ALLL!!!','pdfFileLocation' =>'attachments1'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals("success", $content['status']);
        $this->assertEquals($data['firstname'], $content['data']['firstname']);
        $this->assertEquals($data['body'], $content['data']['body']);
        $this->assertNotNull($content['data']['document_path']);
        $this->assertNotNull($content['data']['attachments1']);
    }
}

