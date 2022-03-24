<?php
namespace Workflow;

use Mockery;
use Oxzion\Test\ControllerTest;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Oxzion\Service\CommandService;
use Oxzion\Utils\FileUtils;
use Oxzion\Utils\StringUtils;

class ServiceTaskControllerTest extends ControllerTest
{
    public function setUp(): void
    {
        $this->loadConfig();
        parent::setUp();
    }

    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__) . "/../Dataset/ActivityInstance.yml");
        $dataset->addYamlFile(dirname(__FILE__) . "/../Dataset/Workflow.yml");
        switch ($this->getName()) {
            case 'testExtractFileWithPreDefinedFields':
            case 'testServiceTaskScheduleWithPreDefinedFields':
                $dataset->addYamlFile(dirname(__FILE__) . "/../Dataset/Activity.yml");
        }
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

    public function testServiceTaskMailExecution()
    {
        $this->initAuthToken($this->adminUser);
        $data['variables'] = ['command' => 'mail', 'to' => 'admintest@myvamla.com', 'body' => 'create a new body', 'subject' => 'NewSubject'];
        $this->setJsonContent(json_encode($data));
        if (enableActiveMQ == 0) {
            $mockMessageProducer = $this->getMockMessageProducer();
            $payload = json_encode(array('to' => $data['variables']['to'], 'subject' => $data['variables']['subject'], 'body' => $data['variables']['body'], 'attachments' => null));
            $mockMessageProducer->expects('sendQueue')->with($payload, 'mail')->once()->andReturn(123);
        }
        $this->dispatch('/callback/workflow/servicetask', 'POST', $data);
        $this->assertResponseStatusCode(200);
    }

    public function testServiceTaskWithoutSubjectExecution()
    {
        $this->initAuthToken($this->adminUser);
        $data['variables'] = ['command' => 'mail', 'to' => 'admintest@myvamla.com', 'body' => 'create a new body'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/workflow/servicetask', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(406);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Validation error(s).');
        $this->assertEquals($content['data']['errors']['subject'], 'required');
    }

    public function testServiceTaskWithoutRecepientMailExecution()
    {
        $this->initAuthToken($this->adminUser);
        $data['variables'] = ['command' => 'mail', 'body' => 'create a new body', 'subject' => 'NewSubject'];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/workflow/servicetask', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(406);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Validation error(s).');
        $this->assertEquals($content['data']['errors']['to'], 'required');
    }

    public function testServiceTaskPDFExecution()
    {
        $data = ['uuid' => '53012471-2863-4949-afb1-e69b0891c98a'];
        $config = $this->getApplicationConfig();
        $tempFolder = $config['TEMPLATE_FOLDER'] . "53012471-2863-4949-afb1-e69b0891c98a/";
        FileUtils::createDirectory($tempFolder);
        $tempFile = $config['TEMPLATE_FOLDER'] . "/";
        FileUtils::createDirectory($tempFile);
        copy(__DIR__ . "/../Dataset/GenericTemplate.tpl", $tempFile . "GenericTemplate.tpl");
        $params['variables'] = ['command' => 'pdf', 'template' => 'GenericTemplate', 'accountId' => $data['uuid'], 'options' => array('initial_title' => 'Vantage agora Pdf Template', 'second_title' => 'Title 2', 'pdf_header_logo' => '/logo_example.jpg', 'pdf_header_logo_width' => 20, 'header_text_color' => array(139, 58, 58), 'header_line_color' => array(255, 48, 48), 'footer_text_color' => array(123, 121, 34), 'footer_line_color' => array(56, 142, 142)), 'destination' => $config['TEMPLATE_FOLDER'] . "GenericTemplate.pdf"];
        $this->setJsonContent(json_encode($params));
        $this->dispatch('/callback/workflow/servicetask', 'POST', $params);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $templateName = "GenericTemplate.tpl";
        FileUtils::deleteFile($templateName, $tempFile);
        if (file_exists($config['TEMPLATE_FOLDER'].'GenericTemplate.pdf')) {
            FileUtils::deleteFile("GenericTemplate.pdf", $config['TEMPLATE_FOLDER']);
        }
    }

    public function testServiceTaskSchedule()
    {
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd",
                 "activityName" => "Setup Autorenewal",
                 "processInstanceId" => "3f20b5c5-0124-11ea-a8a0-22e8105c0778",
                 "variables" => array("firstname" => "Neha",
                                      "policy_period" => "1year",
                                      "card_expiry_date" => "10/24",
                                      "city" => "Bangalore",
                                      "isequipmentliability" => "1",
                                      "card_no" => "1234",
                                      "jobUrl" => "/app/9fc99df0-d91b-11e9-8a34-2a2ae2dbcce4/delegate/DispatchAutoRenewalNotification",
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
                                      "jobName" => "autoRenewalJob",
                                      "expiry_year" => "2019",
                                      "lastname" => "Rai",
                                      "isexcessliability" => "1",
                                      "accountId" => "53012471-2863-4949-afb1-e69b0891c98a",
                                      "credit_card_type" => "credit",
                                      "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925",
                                      "appId" => "9fc99df0-d91b-11e9-8a34-2a2ae2dbcce4",
                                      "fileId" => "d13d0c68-98c9-11e9-adc5-308d99c9145b",
                                      "email" => 'bharat@gmail.com'),
                                      "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd",
                                      "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];

        $this->setJsonContent(json_encode($data));
        $jobId = '12343';
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $response = ['body' => '{"Success" : true, "JobId" : "'.$jobId.'"}'];
            $mockRestClient->expects('postWithHeader')->with("setupjob", Mockery::any())->once()->andReturn($response);
        }
        $this->dispatch('/callback/workflow/servicetask', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertEquals("success", $content['status']);
        $this->assertEquals(count($data['variables'])-3, count($content['data']));
        $this->assertEquals(true, isset($content['data']['autoRenewalJob']));
        $job = json_decode($content['data']['autoRenewalJob'], true);
        $this->assertEquals($job['jobId'], $jobId);
        $this->assertEquals($job['jobGroup'], $data['variables']['jobName']);
        $query = "SELECT * from ox_job where job_id = '$jobId'";
        $result = $this->executeQueryTest($query);
        $this->assertEquals(1, count($result));
        $this->assertEquals(99, $result[0]['app_id']);
        $this->assertEquals(1, $result[0]['account_id']);
        $this->assertEquals($job['jobGroup'], $result[0]['group_name']);
        $config = json_decode($result[0]['config'], true);
        $this->assertEquals(true, StringUtils::endsWith($config['job']['url'], $data['variables']['jobUrl']));
    }

    public function testServiceTaskScheduleWithoutRequiredFields()
    {
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd",
                 "processInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd",
                 "variables" => array("firstname" => "Neha",
                                      "policy_period" => "1year",
                                      "card_expiry_date" => "10/24",
                                      "city" => "Bangalore",
                                      "accountId" => "53012471-2863-4949-afb1-e69b0891c98a",
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
                                      "lastname" => "Rai",
                                      "isexcessliability" => "1",
                                      "workflow_instance_id" => "142",
                                      "credit_card_type" => "credit",
                                      "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925",
                                      "fileId" => "134",
                                      "email" => 'bharat@gmail.com',
                                      "appId" => "9fc99df0-d91b-11e9-8a34-2a2ae2dbcce4"),
                        "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];

        $this->setJsonContent(json_encode($data));
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $mockRestClient->expects('postWithHeader')->with("setupjob", Mockery::any())->once()->andReturn();
        }
        $this->dispatch('/callback/workflow/servicetask', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals("JobUrl or Cron Expression or JobName Not Specified", $content['message']);
    }

    public function testServiceTaskCancelJob()
    {
        $jobId = '14b5370e-a580-4b80-a17a-a13be8b47ee0';
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd",
                  "activityName" => "Cancel Job",
                  "processInstanceId" => "3f20b5c5-0124-11ea-a8a0-22e8105c0778",
                  "variables" => array("firstname" => "Neha",
                                        "policy_period" => "1year",
                                        "card_expiry_date" => "10/24",
                                        "city" => "Bangalore",
                                        "accountId" => "53012471-2863-4949-afb1-e69b0891c98a",
                                        "isequipmentliability" => "1",
                                        "card_no" => "1234",
                                        "state" => "karnataka",
                                        "zip" => "560030",
                                        "coverage" => "100000",
                                        "product" => "Individual Professional Liability",
                                        "address2" => "dhgdhdh",
                                        "address1" => "hjfjhfjfjfhfg",
                                        "expiry_date" => "2020-06-30",
                                        "form_id" => "0",
                                        "entity_id" => "1",
                                        "created_by" => "1",
                                        "url" => "canceljob",
                                        "command" => "cancelJob",
                                        "jobName" => "autoRenewalJob",
                                        "autoRenewalJob" => '{"jobId":"'.$jobId.'","jobGroup":"Job"}',
                                        "expiry_year" => "2019",
                                        "accountId" => "53012471-2863-4949-afb1-e69b0891c98a",
                                        "lastname" => "Rai",
                                        "isexcessliability" => "1",
                                        "workflow_instance_id" => "142",
                                        "credit_card_type" => "credit",
                                        "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925",
                                        "fileId" => "134",
                                        "email" => 'bharat@gmail.com',
                                        "appId" => "9fc99df0-d91b-11e9-8a34-2a2ae2dbcce4"),
                  "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd",
                  "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];

        $this->setJsonContent(json_encode($data));
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $response = ['body' => '{"Success" : true}'];
            $mockRestClient->expects('postWithHeader')->with("canceljob", Mockery::any())->once()->andReturn($response);
        }
        $query = "SELECT * from ox_job where job_id = '$jobId'";
        $result = $this->executeQueryTest($query);
        $this->assertEquals(1, count($result));
        $this->dispatch('/callback/workflow/servicetask', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $result = $this->executeQueryTest($query);
        $this->assertEquals(0, count($result));
    }

    public function testServiceTaskCancelJobWithoutJobName()
    {
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "activityName" => "Cancel Job", "processInstanceId" => "3f20b5c5-0124-11ea-a8a0-22e8105c0778", "variables" => array("firstname" => "Neha", "policy_period" => "1year", "card_expiry_date" => "10/24", "city" => "Bangalore", "accountId" => "53012471-2863-4949-afb1-e69b0891c98a", "isequipmentliability" => "1", "card_no" => "1234", "state" => "karnataka", "zip" => "560030", "coverage" => "100000", "product" => "Individual Professional Liability", "address2" => "dhgdhdh", "address1" => "hjfjhfjfjfhfg", "expiry_date" => "2020-06-30", "form_id" => "0", "entity_id" => "1", "created_by" => "1", "url" => "canceljob", "command" => "cancelJob", "autoRenewalJob" => '{"jobId":"14b5370e-a580-4b80-a17a-a13be8b47ee0","jobTeam":"Job"}', "expiry_year" => "2019", "lastname" => "Rai", "isexcessliability" => "1", "workflow_instance_id" => "142", "credit_card_type" => "credit", "appId" => "9fc99df0-d91b-11e9-8a34-2a2ae2dbcce4", "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925", "fileId" => "134", "email" => 'bharat@gmail.com'), "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];

        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/workflow/servicetask', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertEquals($content['status'], 'success');
    }

    public function testServiceTaskCancelJobWithoutJobID()
    {
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "activityName" => "Setup Autorenewal Job", "processInstanceId" => "3f20b5c5-0124-11ea-a8a0-22e8105c0778", "variables" => array("firstname" => "Neha", "policy_period" => "1year", "card_expiry_date" => "10/24", "city" => "Bangalore", "accountId" => "53012471-2863-4949-afb1-e69b0891c98a", "isequipmentliability" => "1", "card_no" => "1234", "state" => "karnataka", "zip" => "560030", "coverage" => "100000", "product" => "Individual Professional Liability", "address2" => "dhgdhdh", "address1" => "hjfjhfjfjfhfg", "expiry_date" => "2020-06-30", "form_id" => "0", "entity_id" => "1", "created_by" => "1", "url" => "canceljob", "command" => "cancelJob", "jobName" => "autoRenewalJob", "autoRenewalJob" => '{"jobID":"14b5370e-a580-4b80-a17a-a13be8b47ee0","jobteam":"Job"}', "expiry_year" => "2019",  "lastname" => "Rai", "isexcessliability" => "1", "appId" => "9fc99df0-d91b-11e9-8a34-2a2ae2dbcce4", "workflow_instance_id" => "142", "credit_card_type" => "credit", "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925", "fileId" => "134", "email" => 'bharat@gmail.com'), "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];

        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/workflow/servicetask', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testServiceTaskCancelJobEmpty()
    {
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "activityName" => "Cancel Job", "processInstanceId" => "3f20b5c5-0124-11ea-a8a0-22e8105c0778", "variables" => array("firstname" => "Neha", "policy_period" => "1year", "card_expiry_date" => "10/24", "city" => "Bangalore", "accountId" => "53012471-2863-4949-afb1-e69b0891c98a", "isequipmentliability" => "1", "card_no" => "1234", "state" => "karnataka", "zip" => "560030", "coverage" => "100000", "product" => "Individual Professional Liability", "address2" => "dhgdhdh", "address1" => "hjfjhfjfjfhfg", "expiry_date" => "2020-06-30", "form_id" => "0", "entity_id" => "1", "created_by" => "1", "url" => "canceljob", "command" => "cancelJob", "jobName" => "autoRenewalJob", "expiry_year" => "2019", "lastname" => "Rai", "isexcessliability" => "1", "workflow_instance_id" => "142", "appId" => "9fc99df0-d91b-11e9-8a34-2a2ae2dbcce4", "credit_card_type" => "credit", "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925", "fileId" => "134", "email" => 'bharat@gmail.com'), "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];

        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/workflow/servicetask', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testServiceTaskCancelInvalidJob()
    {
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "activityName" => "Cancel Job", "processInstanceId" => "3f20b5c5-0124-11ea-a8a0-22e8105c0778", "variables" => array("firstname" => "Neha", "policy_period" => "1year", "card_expiry_date" => "10/24", "city" => "Bangalore", "accountId" => "53012471-2863-4949-afb1-e69b0891c98a", "isequipmentliability" => "1", "card_no" => "1234", "state" => "karnataka", "zip" => "560030", "coverage" => "100000", "product" => "Individual Professional Liability", "address2" => "dhgdhdh", "address1" => "hjfjhfjfjfhfg", "expiry_date" => "2020-06-30", "form_id" => "0", "entity_id" => "1", "created_by" => "1", "url" => "canceljob", "command" => "cancelJob", "jobName" => "autoRenewalJob", "autoRenewalJob" => '{"jobId":"f4f7833e-7e34-4b00-bcab-ef6048e7fbcb","jobTeam":"Job"}', "expiry_year" => "2019", "lastname" => "Rai", "isexcessliability" => "1", "appId" => "9fc99df0-d91b-11e9-8a34-2a2ae2dbcce4", "workflow_instance_id" => "142", "credit_card_type" => "credit", "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925", "fileId" => "134", "email" => 'bharat@gmail.com'), "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];

        $this->setJsonContent(json_encode($data));
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $exception = Mockery::Mock('\GuzzleHttp\Exception\ClientException');
            $request = Mockery::Mock('\Psr\Http\Message\RequestInterface');
            $exception = new \GuzzleHttp\Exception\ClientException('Client error: `POST http://172.16.1.95:8085/canceljob` resulted in a `404 Not Found` response:{"timestamp":"2019-11-05T13:06:56.308+0000","status":404,"error":"Not Found","message":"Job Does not Exists","path":"/ca (truncated...)', $request);
            $mockRestClient->expects('postWithHeader')->with("canceljob", array('jobid' => 'f4f7833e-7e34-4b00-bcab-ef6048e7fbcb', 'jobteam' => 'Job'))->once()->andThrow($exception);
        }
        $this->dispatch('/callback/workflow/servicetask', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
    }

    public function testFileSaveWithFileId()
    {
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "activityName" => "File save", "processInstanceId" => "3f20b5c5-0124-11ea-a8a0-22e8105c0778", "variables" => array("firstname" => "Neha", "policy_period" => "1year", "card_expiry_date" => "10/24", "city" => "Bangalore", "accountId" => "53012471-2863-4949-afb1-e69b0891c98a", "isequipmentliability" => "1", "card_no" => "1234", "state" => "karnataka", "zip" => "560030", "coverage" => "100000", "product" => "Individual Professional Liability", "address2" => "dhgdhdh", "address1" => "hjfjhfjfjfhfg", "expiry_date" => "2020-06-30 00:00:00", "form_id" => "0", "entity_id" => "1", "created_by" => "1", "command" => "fileSave", "expiry_year" => "2019", "lastname" => "Rai", "isexcessliability" => "1", "workflow_instance_id" => "1", "credit_card_type" => "credit", "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925", "fileId" => "d13d0c68-98c9-11e9-adc5-308d99c9145b", "email" => 'bharat@gmail.com', "start_date" => date_format(date_create(null), 'Y-m-d H:i:s'), "end_date" => date_format(date_create(null), 'Y-m-d H:i:s'), "status" => null), "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/workflow/servicetask', 'POST', $data);
        $query = "Select data from ox_file where uuid = 'd13d0c68-98c9-11e9-adc5-308d99c9145b'";
        $result = $this->executeQueryTest($query);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertEquals($content['status'], 'success');
        unset($data['variables']['command'], $data['variables']['fileId'], $data['variables']['appId'], $data['variables']['form_id'], $data['variables']['created_by'], $data['variables']['workflow_instance_id'], $data['variables']['workflowId'], $data['variables']['accountId'], $data['variables']['entity_id']);
        $this->assertEquals($data['variables'], json_decode($result[0]['data'], true));
    }

    public function testFileSaveWithoutFileId()
    {
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "activityName" => "File save", "processInstanceId" => "3f20b5c5-0124-11ea-a8a0-22e8105c0778", "variables" => array("firstname" => "Neha", "policy_period" => "1year", "card_expiry_date" => "10/24", "city" => "Bangalore", "accountId" => "53012471-2863-4949-afb1-e69b0891c98a", "isequipmentliability" => "1", "card_no" => "1234", "state" => "karnataka", "zip" => "560030", "coverage" => "100000", "product" => "Individual Professional Liability", "address2" => "dhgdhdh", "address1" => "hjfjhfjfjfhfg", "expiry_date" => "2020-06-30 00:00:00", "form_id" => "0", "entity_id" => "1", "created_by" => "1", "command" => "fileSave", "expiry_year" => "2019", "lastname" => "Rai", "isexcessliability" => "1", "workflow_instance_id" => "1", "credit_card_type" => "credit", "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925", "email" => 'bharat@gmail.com', "start_date" => date_format(date_create(null), 'Y-m-d H:i:s'), "end_date" => date_format(date_create(null), 'Y-m-d H:i:s'), "status" => null), "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/workflow/servicetask', 'POST', $data);
        $query = "Select data from ox_file where uuid = 'd13d0c68-98c9-11e9-adc5-308d99c9145b'";
        $result = $this->executeQueryTest($query);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertEquals($content['status'], 'success');
        unset($data['variables']['command'], $data['variables']['fileId'], $data['variables']['appId'], $data['variables']['form_id'], $data['variables']['created_by'], $data['variables']['workflow_instance_id'], $data['variables']['workflowId'], $data['variables']['accountId'], $data['variables']['entity_id']);
        $this->assertEquals($data['variables'], json_decode($result[0]['data'], true));
    }

    public function testFileSaveInvalidWokflowInstanceID()
    {
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "processInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "variables" => array("firstname" => "Neha", "policy_period" => "1year", "card_expiry_date" => "10/24", "city" => "Bangalore", "accountId" => "53012471-2863-4949-afb1-e69b0891c98a", "isequipmentliability" => "1", "card_no" => "1234", "state" => "karnataka", "zip" => "560030", "coverage" => "100000", "product" => "Individual Professional Liability", "address2" => "dhgdhdh", "address1" => "hjfjhfjfjfhfg", "expiry_date" => "2020-06-30", "form_id" => "0", "entity_id" => "1", "created_by" => "1", "command" => "fileSave", "expiry_year" => "2019", "lastname" => "Rai", "isexcessliability" => "1", "workflow_instance_id" => "5", "credit_card_type" => "credit", "workflowId" => "a01a6776-431a-401e-9288-6acf3b2f3925", "email" => 'bharat@gmail.com'), "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/workflow/servicetask', 'POST', $data);
        $query = "Select data from ox_file where uuid = 'd13d0c68-98c9-11e9-adc5-308d99c9145b'";
        $result = $this->executeQueryTest($query);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Workflow Instance Id Not Found');
    }

    public function testExtractFile()
    {
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "activityName" => "Get File", "processInstanceId" => "3f20b5c5-0124-11ea-a8a0-22e8105c0778", "variables" => array("command" => "file", "accountId" => "53012471-2863-4949-afb1-e69b0891c98a", "fileId" => "d13d0c68-98c9-11e9-adc5-308d99c9145b"), "parentInstanceId" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd", "parentActivity" => "651eebfb-ef09-11e9-a364-62be4f9e1bfd"];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/workflow/servicetask', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(is_array($content['data']), true);
        $this->assertEquals(24, count($content['data']));
    }
        
    public function testExtractFileWithPreDefinedFields()
    {
        $data = ["activityInstanceId" => "Task_1bw1uyk:651f1320-ef09-11e9-a364-62be4f9e1bfd", "activityName" => "Get File", "processInstanceId" => "de20b5c5-0124-11ea-a8a0-22e8105c07fe", "variables" => array("command" => "file", "accountId" => "53012471-2863-4949-afb1-e69b0891c98a", "fileId" => "ee3d0c68-98c9-11e9-adc5-308d99c914ca")];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/callback/workflow/servicetask', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(is_array($content['data']), true);
        $this->assertEquals(4, count($content['data']));
    }

    public function testServiceTaskCommands()
    {
        $data = ['uuid' => '53012471-2863-4949-afb1-e69b0891c98a'];
        $config = $this->getApplicationConfig();
        $tempFolder = $config['TEMPLATE_FOLDER'] . "53012471-2863-4949-afb1-e69b0891c98a/";
        FileUtils::createDirectory($tempFolder);
        $tempFile = $config['TEMPLATE_FOLDER'] . "/";
        FileUtils::createDirectory($tempFile);
        copy(__DIR__ . "/../Dataset/GenericTemplate.tpl", $tempFile . "GenericTemplate.tpl");
        $params['variables'] = ["app_id" => "9fc99df0-d91b-11e9-8a34-2a2ae2dbcce4",'commands' => array('{"command":"pdf", "template":"GenericTemplate"}', '{"command":"file"}'), 'accountId' => $data['uuid'], 'options' => array('initial_title' => 'Vantage agora Pdf Template', 'second_title' => 'Title 2', 'pdf_header_logo' => '/logo_example.jpg', 'pdf_header_logo_width' => 20, 'header_text_color' => array(139, 58, 58), 'header_line_color' => array(255, 48, 48), 'footer_text_color' => array(123, 121, 34), 'footer_line_color' => array(56, 142, 142)), 'destination' => $config['TEMPLATE_FOLDER'] . "GenericTemplate.pdf", "accountId" => "53012471-2863-4949-afb1-e69b0891c98a", "fileId" => "d13d0c68-98c9-11e9-adc5-308d99c9145b","workFlowId" => "a01a6776-431a-401e-9288-6acf3b2f3925"];
        $this->setJsonContent(json_encode($params));
        $this->dispatch('/callback/workflow/servicetask', 'POST', $params);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $templateName = "GenericTemplate.tpl";
        FileUtils::deleteFile($templateName, $tempFile);
        if (file_exists($config['TEMPLATE_FOLDER'].'GenericTemplate.pdf')) {
            FileUtils::deleteFile("GenericTemplate.pdf", $config['TEMPLATE_FOLDER']);
        }
    }

    public function testGenerateMultipleCommands()
    {
        $date = date('Y-m-d');
        $currentDate = date('Y-m-d', strtotime($date . ' + 1 days'));
        $params['variables'] = ["commands" => array('{"command":"filelist", "filter" : "'.'[{\"filter\":{\"filters\":[{\"field\":\"expiry_date\",\"operator\":\"lt\",\"value\":\"' . $currentDate . '\"}]},\"sort\":[{\"field\":\"expiry_date\",\"dir\":\"asc\"}],\"skip\":0,\"take\":1}]'.'"'.'}', '{"command":"sign_in"}'), 'accountId' => "53012471-2863-4949-afb1-e69b0891c98a", "appId" => "9fc99df0-d91b-11e9-8a34-2a2ae2dbcce4", "workFlowId" => "1141cd2e-cb14-11e9-a32f-2a2ae2dbcce4", "userId" => null];
        $params['processInstanceId'] =
        $this->setJsonContent(json_encode($params));
        $this->dispatch('/callback/workflow/servicetask', 'POST', $params);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['appId'], "9fc99df0-d91b-11e9-8a34-2a2ae2dbcce4");
        $this->assertEquals($content['data']['auto_login'], 1);
    }
}
