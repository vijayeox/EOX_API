<?php
namespace App;

use Mockery;
use Oxzion\Test\ControllerTest;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Oxzion\Service\CommandService;
use Oxzion\Utils\FileUtils;

class PipelineControllerTest extends ControllerTest
{
    public function setUp(): void
    {
        $this->loadConfig();
        parent::setUp();
    }

    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__) . "/../Dataset/Workflow.yml");
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
        $taskService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\CommandService::class);
        $mockRestClient = Mockery::mock('Oxzion\Utils\RestClient');
        $taskService->setRestClient($mockRestClient);
        return $mockRestClient;
    }

    public function testPipelineMailExecution()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['command' => 'mail', 'to' => 'bharatgtest@myvamla.com', 'body' => 'create a new body', 'subject' => 'NewSubject'];
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
        $data = ['command' => 'mail', 'to' => 'bharatgtest@myvamla.com', 'body' => 'create a new body'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(406);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Validation Errors');
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
        $this->assertEquals($content['message'], 'Validation Errors');
        $this->assertEquals($content['data']['errors']['to'], 'required');
    }

    public function testPipelineSchedule()
    {
        $this->initAuthToken($this->adminUser);
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "processInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd","command" => "schedule","firstname" => "Neha", "policy_period" => "1year", "card_expiry_date" => "10/24", "city" => "Bangalore", "orgUuid" => "53012471-2863-4949-afb1-e69b0891c98a", "isequipmentliability" => "1", "card_no" => "1234", "jobUrl" => "/app/ec8942b7-aa93-4bc6-9e8c-e1371988a5d4/delegate/DispatchAutoRenewalNotification", "state" => "karnataka",  "cron" => "0 0/1 * * * ? *", "zip" => "560030", "coverage" => "100000", "product" => "Individual Professional Liability", "address2" => "dhgdhdh", "address1" => "hjfjhfjfjfhfg", "expiry_date" => "2020-06-30", "form_id" => "0", "entity_id" => "1", "created_by" => "1", "url" => "setupjob",  "jobName" => "autoRenewalJob", "expiry_year" => "2019", "orgid" => "53012471-2863-4949-afb1-e69b0891c98a", "lastname" => "Rai", "isexcessliability" => "1", "workflow_instance_id" => "142", "credit_card_type" => "credit", "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925", "fileId" => "134", "email" => 'bharat@gmail.com', "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];

        $this->setJsonContent(json_encode($data));
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $mockRestClient->expects('postWithHeader')->with("setupjob", Mockery::any())->once()->andReturn();
        }
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
    }

    public function testPipelineScheduleWithoutRequiredFields()
    {
        $this->initAuthToken($this->adminUser);
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "processInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd","firstname" => "Neha", "policy_period" => "1year", "card_expiry_date" => "10/24", "city" => "Bangalore", "orgUuid" => "53012471-2863-4949-afb1-e69b0891c98a", "isequipmentliability" => "1", "card_no" => "1234", "jobUrl" => "/app/ec8942b7-aa93-4bc6-9e8c-e1371988a5d4/delegate/DispatchAutoRenewalNotification", "state" => "karnataka", "cron" => "0 0/1 * * * ? *", "zip" => "560030", "coverage" => "100000", "product" => "Individual Professional Liability", "address2" => "dhgdhdh", "address1" => "hjfjhfjfjfhfg", "expiry_date" => "2020-06-30", "form_id" => "0", "entity_id" => "1", "created_by" => "1", "url" => "setupjob", "command" => "schedule", "expiry_year" => "2019", "orgid" => "53012471-2863-4949-afb1-e69b0891c98a", "lastname" => "Rai", "isexcessliability" => "1", "workflow_instance_id" => "142", "credit_card_type" => "credit", "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925", "fileId" => "134", "email" => 'bharat@gmail.com', "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];

        $this->setJsonContent(json_encode($data));
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $mockRestClient->expects('postWithHeader')->with("setupjob", Mockery::any())->once()->andReturn();
        }
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'JobUrl or Cron Expression or URL or JobName Not Specified');
    }

    public function testPipelineCancelJob()
    {
        $this->initAuthToken($this->adminUser);
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "processInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd","firstname" => "Neha", "policy_period" => "1year", "card_expiry_date" => "10/24", "city" => "Bangalore", "orgUuid" => "53012471-2863-4949-afb1-e69b0891c98a", "isequipmentliability" => "1", "card_no" => "1234", "state" => "karnataka", "zip" => "560030", "coverage" => "100000", "product" => "Individual Professional Liability", "address2" => "dhgdhdh", "address1" => "hjfjhfjfjfhfg", "expiry_date" => "2020-06-30", "form_id" => "0", "entity_id" => "1", "created_by" => "1", "url" => "canceljob", "command" => "cancelJob", "jobName" => "autoRenewalJob", "autoRenewalJob" => '{"jobId":"14b5370e-a580-4b80-a17a-a13be8b47ee0","jobGroup":"Job"}', "expiry_year" => "2019", "orgid" => "53012471-2863-4949-afb1-e69b0891c98a", "lastname" => "Rai", "isexcessliability" => "1", "workflow_instance_id" => "142", "credit_card_type" => "credit", "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925", "fileId" => "134", "email" => 'bharat@gmail.com', "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];

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
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "processInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd","firstname" => "Neha", "policy_period" => "1year", "card_expiry_date" => "10/24", "city" => "Bangalore", "orgUuid" => "53012471-2863-4949-afb1-e69b0891c98a", "isequipmentliability" => "1", "card_no" => "1234", "state" => "karnataka", "zip" => "560030", "coverage" => "100000", "product" => "Individual Professional Liability", "address2" => "dhgdhdh", "address1" => "hjfjhfjfjfhfg", "expiry_date" => "2020-06-30", "form_id" => "0", "entity_id" => "1", "created_by" => "1", "url" => "canceljob", "command" => "cancelJob", "autoRenewalJob" => '{"jobId":"14b5370e-a580-4b80-a17a-a13be8b47ee0","jobGroup":"Job"}', "expiry_year" => "2019", "orgid" => "53012471-2863-4949-afb1-e69b0891c98a", "lastname" => "Rai", "isexcessliability" => "1", "workflow_instance_id" => "142", "credit_card_type" => "credit", "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925", "fileId" => "134", "email" => 'bharat@gmail.com', "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];

        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Job Name Not Specified');
    }

    public function testPipelineCancelJobWithoutJobID()
    {
        $this->initAuthToken($this->adminUser);
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "processInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd","firstname" => "Neha", "policy_period" => "1year", "card_expiry_date" => "10/24", "city" => "Bangalore", "orgUuid" => "53012471-2863-4949-afb1-e69b0891c98a", "isequipmentliability" => "1", "card_no" => "1234", "state" => "karnataka", "zip" => "560030", "coverage" => "100000", "product" => "Individual Professional Liability", "address2" => "dhgdhdh", "address1" => "hjfjhfjfjfhfg", "expiry_date" => "2020-06-30", "form_id" => "0", "entity_id" => "1", "created_by" => "1", "url" => "canceljob", "command" => "cancelJob", "jobName" => "autoRenewalJob", "autoRenewalJob" => '{"jobID":"14b5370e-a580-4b80-a17a-a13be8b47ee0","jobgroup":"Job"}', "expiry_year" => "2019", "orgid" => "53012471-2863-4949-afb1-e69b0891c98a", "lastname" => "Rai", "isexcessliability" => "1", "workflow_instance_id" => "142", "credit_card_type" => "credit", "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925", "fileId" => "134", "email" => 'bharat@gmail.com', "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];

        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Job Id or Job Group Not Specified');
    }

    public function testPipelineCancelJobEmpty()
    {
        $this->initAuthToken($this->adminUser);
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "processInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd","firstname" => "Neha", "policy_period" => "1year", "card_expiry_date" => "10/24", "city" => "Bangalore", "orgUuid" => "53012471-2863-4949-afb1-e69b0891c98a", "isequipmentliability" => "1", "card_no" => "1234", "state" => "karnataka", "zip" => "560030", "coverage" => "100000", "product" => "Individual Professional Liability", "address2" => "dhgdhdh", "address1" => "hjfjhfjfjfhfg", "expiry_date" => "2020-06-30", "form_id" => "0", "entity_id" => "1", "created_by" => "1", "url" => "canceljob", "command" => "cancelJob", "jobName" => "autoRenewalJob", "expiry_year" => "2019", "orgid" => "53012471-2863-4949-afb1-e69b0891c98a", "lastname" => "Rai", "isexcessliability" => "1", "workflow_instance_id" => "142", "credit_card_type" => "credit", "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925", "fileId" => "134", "email" => 'bharat@gmail.com', "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];

        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Job autoRenewalJob Not Specified');
    }

    public function testPipelineCancelInvalidJob()
    {
        $this->initAuthToken($this->adminUser);
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "processInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd","firstname" => "Neha", "policy_period" => "1year", "card_expiry_date" => "10/24", "city" => "Bangalore", "orgUuid" => "53012471-2863-4949-afb1-e69b0891c98a", "isequipmentliability" => "1", "card_no" => "1234", "state" => "karnataka", "zip" => "560030", "coverage" => "100000", "product" => "Individual Professional Liability", "address2" => "dhgdhdh", "address1" => "hjfjhfjfjfhfg", "expiry_date" => "2020-06-30", "form_id" => "0", "entity_id" => "1", "created_by" => "1", "url" => "canceljob", "command" => "cancelJob", "jobName" => "autoRenewalJob", "autoRenewalJob" => '{"jobId":"f4f7833e-7e34-4b00-bcab-ef6048e7fbcb","jobGroup":"Job"}', "expiry_year" => "2019", "orgid" => "53012471-2863-4949-afb1-e69b0891c98a", "lastname" => "Rai", "isexcessliability" => "1", "workflow_instance_id" => "142", "credit_card_type" => "credit", "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925", "fileId" => "134", "email" => 'bharat@gmail.com', "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];

        $this->setJsonContent(json_encode($data));
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $exception = Mockery::Mock('\GuzzleHttp\Exception\ClientException');
            $request = Mockery::Mock('\Psr\Http\Message\RequestInterface');
            $exception = new \GuzzleHttp\Exception\ClientException('Client error: `POST http://172.16.1.95:8085/canceljob` resulted in a `404 Not Found` response:{"timestamp":"2019-11-05T13:06:56.308+0000","status":404,"error":"Not Found","message":"Job Does not Exists","path":"/ca (truncated...)', $request);
            $mockRestClient->expects('postWithHeader')->with("canceljob", array('jobid' => 'f4f7833e-7e34-4b00-bcab-ef6048e7fbcb', 'jobgroup' => 'Job'))->once()->andThrow($exception);
        }
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
    }

    public function testFileSave()
    {
        $this->initAuthToken($this->adminUser);
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "processInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd","firstname" => "Neha", "policy_period" => "1year", "card_expiry_date" => "10/24", "city" => "Bangalore", "orgUuid" => "53012471-2863-4949-afb1-e69b0891c98a", "isequipmentliability" => "1", "card_no" => "1234", "state" => "karnataka", "zip" => "560030", "coverage" => "100000", "product" => "Individual Professional Liability", "address2" => "dhgdhdh", "address1" => "hjfjhfjfjfhfg", "expiry_date" => "2020-06-30", "form_id" => "0", "entity_id" => "1", "created_by" => "1", "command" => "fileSave", "expiry_year" => "2019", "orgid" => "53012471-2863-4949-afb1-e69b0891c98a", "lastname" => "Rai", "isexcessliability" => "1", "workflow_instance_id" => "1", "credit_card_type" => "credit", "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925", "fileId" => "1", "email" => 'bharat@gmail.com', "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];
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
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "processInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd","firstname" => "Neha", "policy_period" => "1year", "card_expiry_date" => "10/24", "city" => "Bangalore", "orgUuid" => "53012471-2863-4949-afb1-e69b0891c98a", "isequipmentliability" => "1", "card_no" => "1234", "state" => "karnataka", "zip" => "560030", "coverage" => "100000", "product" => "Individual Professional Liability", "address2" => "dhgdhdh", "address1" => "hjfjhfjfjfhfg", "expiry_date" => "2020-06-30", "form_id" => "0", "entity_id" => "1", "created_by" => "1", "command" => "fileSave", "expiry_year" => "2019", "orgid" => "53012471-2863-4949-afb1-e69b0891c98a", "lastname" => "Rai", "isexcessliability" => "1", "workflow_instance_id" => "5", "credit_card_type" => "credit", "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925", "fileId" => "1", "email" => 'bharat@gmail.com', "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];
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
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "processInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd","command" => "file", "orgId" => "53012471-2863-4949-afb1-e69b0891c98a", "fileId" => "d13d0c68-98c9-11e9-adc5-308d99c9145b", "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];
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
        $params = ["commands" => array('{"command":"filelist", "filter" : "'.'[{\"filter\":{\"filters\":[{\"field\":\"expiry_date\",\"operator\":\"lt\",\"value\":\"' . $currentDate . '\"}]},\"sort\":[{\"field\":\"expiry_date\",\"dir\":\"asc\"}],\"skip\":0,\"take\":1}]'.'"'.'}', '{"command":"delegate", "delegate": "followup"}'), 'orgId' => "53012471-2863-4949-afb1-e69b0891c98a", "app_id" => "1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4", "workFlowId" => "1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4", "userId" => null, ];
        $this->setJsonContent(json_encode($params));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $params);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['appId'], "1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4");
    }
    public function testGetStartFormCommands()
    {
        $this->initAuthToken($this->adminUser);
        $date = date('Y-m-d');
        $currentDate = date('Y-m-d', strtotime($date . ' + 1 days'));
        $params = json_decode('{"commands": [{"command":"startform","workflow_id":"1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4"}]}',true);
        $this->setJsonContent(json_encode($params));
        $this->dispatch('/app/1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/pipeline', 'POST', $params);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['appId'], "1c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4");
    }
}
