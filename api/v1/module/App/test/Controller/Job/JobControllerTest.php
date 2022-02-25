<?php
namespace Job;

use Mockery;
use Oxzion\Service\JobService;
use Oxzion\Test\ControllerTest;
use PHPUnit\DbUnit\DataSet\YamlDataSet;

class JobControllerTest extends ControllerTest
{
    public function setUp(): void
    {
        $this->loadConfig();
        parent::setUp();
    }

    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__) . "/../../Dataset/Job.yml");
        if ($this->getName() == 'testGetJobsList' || $this->getName() == 'testGetJob' || $this->getName() == 'testGetJobDetails' || $this->getName() == 'testJobServiceScheduleWhichExists' || $this->getName() == 'testJobServiceCancelJob' || $this->getName() == 'testJobServiceCancelJobId' || $this->getName() == 'testJobServiceCancelJobWithoutJobID' || $this->getName() == 'testJobServiceCancelJobThatDoesNotExist') {
            $dataset->addYamlFile(dirname(__FILE__) . "/../../Dataset/Job2.yml");
        }
        return $dataset;
    }

    public function getMockMessageProducer()
    {
        $serviceTaskService = $this->getApplicationServiceLocator()->get(JobService::class);
        $mockMessageProducer = Mockery::mock('Oxzion\Messaging\MessageProducer');
        $serviceTaskService->setMessageProducer($mockMessageProducer);
        return $mockMessageProducer;
    }

    private function getMockRestClientForScheduleService()
    {
        $taskService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\JobService::class);
        $mockRestClient = Mockery::mock('Oxzion\Utils\RestClient');
        $taskService->setRestClient($mockRestClient);
        return $mockRestClient;
    }

    public function testJobServiceSchedule()
    {
        $this->initAuthToken($this->adminUser);
        $data['jobName'] = '53012471-2863-4949-afb1-e69b0891c98b';
        $data['jobTeam'] = 'autoRenewalJob';
        $data['cron'] = '0 0/1 * * * ? *';
        $data['orgId'] = '1';
        $data['jobPayload'] = array("job" => array("url" => 'http://localhost:8080/workflow/91cb9e10-5845-4379-97c9-f9486b702bd6', "data" => $data), "schedule" => array("cron" => $data['cron']));
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $mockRestClient->expects('postWithHeader')->with("setupjob", Mockery::any())->once()->andReturn(array('body' => '{"Success":true,"Message":"Job Scheduled Successfully!","JobId":"3a289705-763d-489a-b501-0755b9d4b64b","JobTeam":"autoRenewalJob"}'));
        }
        $this->dispatch('/app/2c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/scheduleJob', 'POST', $data);
        $content = json_decode($this->getResponse()->getContent(), true);
        if (isset($content['data'])) {
            $select = "Select * from ox_job where job_id = '" . $content['data']['job_id'] . "'";
            $job = $this->executeQueryTest($select);
            $this->assertEquals(1, count($job));
        }
        $this->assertResponseStatusCode(200);
    }

    public function testGetJobDetails()
    {
        $this->initAuthToken($this->adminUser);
        $data['jobId'] = '168247b6-66f9-43e4-b66e-5413e642b7cb';
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/2c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/getJob/168247b6-66f9-43e4-b66e-5413e642b7cb', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['job_id'], $data['jobId']);
    }

    public function testGetJobDetailsNotInDb()
    {
        $this->initAuthToken($this->adminUser);
        $data['jobId'] = '968247b6-66f9-43e4-b66e-5413e642b7ce';
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/app/2c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/getJob/968247b6-66f9-43e4-b66e-5413e642b7ce', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'No records found');
    }

    public function testGetJobsList()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/2c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/getJobsList', 'GET');
        $query = "Select count(*) from ox_job";
        $result = $this->executeQueryTest($query);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($result[0]['count(*)'], 1);
    }

    public function testGetJobsListFromEmptyDb()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/app/2c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/getJobsList', 'GET');
        $query = "Select count(id) from ox_job";
        $result = $this->executeQueryTest($query);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $result = $this->executeQueryTest("Select * from ox_job");
        $this->assertEquals(0, count($result));
    }

    public function testJobServiceCancelJob()
    {
        $service = $this->getApplicationServiceLocator()->get(JobService::class);
        $this->initAuthToken($this->adminUser);
        $data['jobName'] = '8cfa0709-dbb8-41f1-b7a4-a87b5dab670f';
        $data['jobTeam'] = 'autoRenewalJob';
        $data['cron'] = '0 0/1 * * * ? *';
        $data['accountId'] = '53012471-2863-4949-afb1-e69b0891c98a';
        $data['appId'] = '99';
        $data['jobPayload'] = array("job" => array("url" => 'http://localhost:8080/workflow/91cb9e10-5845-4379-97c9-f9486b702bd6', "data" => $data), "schedule" => array("cron" => $data['cron']));
        $select = "Select * from ox_job";
        $job = $this->executeQueryTest($select);
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $mockRestClient->expects('postWithHeader')->with("canceljob", Mockery::any())->once()->andReturn(array('body' => '{"Success":true,"Message":"Job Cancelled Successfully!","JobId":"168247b6-66f9-43e4-b66e-5413e642b7cb","JobTeam":"autoRenewalJob"}'));
            $response = array('body' => '{"Success":true,"Message":"Job Scheduled Successfully!","JobId":"168247b6-66f9-43e4-b66e-5413e642b7cb","JobTeam":"autoRenewalJob"}');
        } else {
            $response = $service->scheduleNewJob($data['jobName'], $data['jobTeam'], $data['jobPayload'], $data['cron'], $data['appId'], $data['orgId']);
            $response = json_decode($response['body']);
        }
        $newData['jobName'] = '8cfa0709-dbb8-41f1-b7a4-a87b5dab670f';
        $newData['jobTeam'] = 'autoRenewalJob';
        $newData['accountId'] = $data['accountId'] ;
        $this->dispatch('/app/2c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/cancelJob', 'POST', $newData);
        $content = json_decode($this->getResponse()->getContent(), true);
        $select = "Select * from ox_job where job_id is not null";
        $job = $this->executeQueryTest($select);
        $this->assertEmpty($job);
        $this->assertResponseStatusCode(200);
    }

    public function testJobServiceCancelJobThatDoesNotExist()
    {
        $service = $this->getApplicationServiceLocator()->get(JobService::class);
        $this->initAuthToken($this->adminUser);
        $data['jobName'] = '8cfa0709-dbb8-41f1-b7a4-a87b5dab670f';
        $data['jobTeam'] = 'autoRenewalJob';
        $data['cron'] = '0 0/1 * * * ? *';
        $data['orgId'] = '1835';
        $data['appId'] = '99';
        $data['jobPayload'] = array("job" => array("url" => 'http://localhost:8080/workflow/91cb9e10-5845-4379-97c9-f9486b702bd6', "data" => $data), "schedule" => array("cron" => $data['cron']));
        $select = "Select * from ox_job";
        $job = $this->executeQueryTest($select);
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $mockRestClient->expects('postWithHeader')->with("canceljob", Mockery::any())->once()->andReturn(array('body' => '{"Success":true,"Message":"Job Cancelled Successfully!","JobId":"168247b6-66f9-43e4-b66e-5413e642b7cb","JobTeam":"autoRenewalJob"}'));
            $response = array('body' => '{"Success":true,"Message":"Job Scheduled Successfully!","JobId":"168247b6-66f9-43e4-b66e-5413e642b7cb","JobTeam":"autoRenewalJob"}');
        } else {
            $response = $service->scheduleNewJob($data['jobName'], $data['jobTeam'], $data['jobPayload'], $data['cron'], $data['appId'], $data['orgId']);
            $response = json_decode($response['body']);
        }
        $newData['jobName'] = '3cfa0709-dbb8-41f1-b7a4-a87b5dab670f';
        $newData['jobTeam'] = 'autoRenewalJob';
        $this->dispatch('/app/2c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/cancelJob', 'POST', $newData);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->assertEquals($content['message'], 'No record found');
    }

    public function testJobServiceCancelJobId()
    {
        $service = $this->getApplicationServiceLocator()->get(JobService::class);
        $this->initAuthToken($this->adminUser);
        $data['jobName'] = '53012471-2863-4949-afb1-e69b0891c98b';
        $data['jobTeam'] = 'autoRenewalJob';
        $data['cron'] = '0 0/1 * * * ? *';
        $data['orgId'] = '1835';
        $data['appId'] = '99';
        $data['jobPayload'] = array("job" => array("url" => 'http://localhost:8080/workflow/91cb9e10-5845-4379-97c9-f9486b702bd6', "data" => $data), "schedule" => array("cron" => $data['cron']));
        $select = "Select * from ox_job";
        $job = $this->executeQueryTest($select);
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $mockRestClient->expects('postWithHeader')->with("canceljob", Mockery::any())->once()->andReturn(array('body' => '{"Success":true,"Message":"Job Cancelled Successfully!","JobId":"168247b6-66f9-43e4-b66e-5413e642b7cb","JobTeam":"autoRenewalJob"}'));
            $response = array('body' => '{"Success":true,"Message":"Job Scheduled Successfully!","JobId":"168247b6-66f9-43e4-b66e-5413e642b7cb","JobTeam":"autoRenewalJob"}');
        } else {
            $response = $service->scheduleNewJob($data['jobName'], $data['jobTeam'], $data['jobPayload'], $data['cron'], $data['appId'], $data['orgId']);
            $response = json_decode($response['body']);
        }
        $response = $response['body'];
        $response = json_decode($response);
        $newData['jobId'] = $response->JobId;
        $newData['jobTeam'] = $response->JobTeam;
        $this->dispatch('/app/2c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/cancelJobId', 'POST', $newData);
        $content = json_decode($this->getResponse()->getContent(), true);
        $select = "Select * from ox_job where job_id is not null";
        $job = $this->executeQueryTest($select);
        $this->assertEmpty($job);
        $this->assertResponseStatusCode(200);
    }

    public function testJobServiceCancelJobWithoutJobID()
    {
        $service = $this->getApplicationServiceLocator()->get(JobService::class);
        $this->initAuthToken($this->adminUser);
        $data['jobName'] = '53012471-2863-4949-afb1-e69b0891c98b';
        $data['jobTeam'] = 'autoRenewalJob';
        $data['cron'] = '0 0/1 * * * ? *';
        $data['orgId'] = '1835';
        $data['appId'] = '99';
        $data['jobPayload'] = array("job" => array("url" => 'http://localhost:8080/workflow/91cb9e10-5845-4379-97c9-f9486b702bd6', "data" => $data), "schedule" => array("cron" => $data['cron']));
        $select = "Select * from ox_job";
        $job = $this->executeQueryTest($select);
        if (enableCamel == 0) {
            $mockRestClient = $this->getMockRestClientForScheduleService();
            $mockRestClient->expects('postWithHeader')->with("canceljob", Mockery::any())->once()->andReturn(array('body' => '{"Success":true,"Message":"Job Cancelled Successfully!","JobId":"168247b6-66f9-43e4-b66e-5413e642b7cb","JobTeam":"autoRenewalJob"}'));
            $response = array('body' => '{"Success":true,"Message":"Job Cancelled Successfully!","JobId":"368247b6-66f9-43e4-b66e-5413e642b7cb","JobTeam":"autoRenewalJob"}');
        } else {
            $response = $service->scheduleNewJob($data['jobName'], $data['jobTeam'], $data['jobPayload'], $data['cron'], $data['appId'], $data['orgId']);
            $response = json_decode($response['body']);
        }
        $response = $response['body'];
        $response = json_decode($response);
        $newData['jobId'] = $response->JobId;
        $newData['jobTeam'] = $response->JobTeam;
        $this->dispatch('/app/2c0f0bc6-df6a-11e9-8a34-2a2ae2dbcce4/cancelJobId', 'POST', $newData);
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertNotEmpty($job);
        $this->assertResponseStatusCode(200);
    }
}
