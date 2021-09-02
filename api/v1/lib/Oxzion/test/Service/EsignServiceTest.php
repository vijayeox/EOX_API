<?php
namespace Oxzion\Service;

use Oxzion\Test\AbstractServiceTest;
use Oxzion\Service\EsignService;
use Exception;
use Mockery;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Zend\Db\Adapter\Adapter;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Zend\Db\ResultSet\ResultSet;
use Oxzion\EntityNotFoundException;
use Oxzion\Model\Esign\EsignDocument;
use Oxzion\Model\Esign\EsignDocumentSigner;
use Oxzion\ValidationException;
use Oxzion\Utils\FileUtils;
use Oxzion\Utils\UuidUtil;
use Oxzion\ServiceException;

class EsignServiceTest extends AbstractServiceTest
{
    private $adapter = null;
    private $esignService;

    public function setUp(): void
    {
        $this->loadConfig();
        parent::setUp();
        $this->esignService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\EsignService::class);
        $this->adapter = $this->getDbAdapter();
        $this->adapter->getDriver()->getConnection()->setResource(static::$pdo);
        $this->config = $this->getApplicationConfig();
        $this->folderToClean = null;
        $this->authToken = "eyJraWQiOiJDQ08wNUYzQnp6NE03Mjh2eGxVYVVoWk9GdWsycFJ0dFVcL3Y0dVwvS29tdHc9IiwiYWxnIjoiUlMyNTYifQ.eyJzdWIiOiI2bDNscGdzYnJoYTEzdThyZGc5dDVocTNhdSIsInRva2VuX3VzZSI6ImFjY2VzcyIsInNjb3BlIjoiaHR0cHM6XC9cL2xhYi1hcGkuaW5zdXJlc2lnbi5pb1wvZG9jdW1lbnRzLnByZXBhcmUgaHR0cHM6XC9cL2xhYi1hcGkuaW5zdXJlc2lnbi5pb1wvZG9jdW1lbnRzLnNlbmQiLCJhdXRoX3RpbWUiOjE2MDkwNzE2MjQsImlzcyI6Imh0dHBzOlwvXC9jb2duaXRvLWlkcC51cy1lYXN0LTEuYW1hem9uYXdzLmNvbVwvdXMtZWFzdC0xX01ZRVJPSFRUQyIsImV4cCI6MTYwOTA3NTIyNCwiaWF0IjoxNjA5MDcxNjI0LCJ2ZXJzaW9uIjoyLCJqdGkiOiIyYmRjOWFjYi03MzY4LTQ0Y2UtYjE0ZS1iOWVhN2EyNmQ3YmMiLCJjbGllbnRfaWQiOiI2bDNscGdzYnJoYTEzdThyZGc5dDVocTNhdSJ9.IOj_01z-erKoOuyz_Iuj_9MwpgBOWN6nK9Gi2jUfBpO7j0nN8gfl0Pb_J2UI7Prx19DwyeABY-W2v8uwE5B6Lu5HKZh9lvHsoybOvHOn4ftUIHXpAPKtkGYXrUb4s0voTJfUjOI5GFHte8kS-bj6p118hT-bztbTE7hby_V_PLvjmFNT5I8J9f11QWebMbzqCVHCaaB6JHk90iCTlECT8150YkCymr4i8Dxnj8Qhzgxg1kd8c442ZrFatjHWwnHMceC5uSg1gPA70gVEYI2Env2K2mKjOObcVW_WxpiA4-bPQnbjNd601ns_B2_9aS68rO_HsMW9xNBzaF7ST53hoQ";
    }

    public function tearDown() : void
    {
        parent::tearDown();
        if ($this->folderToClean) {
            FileUtils::rmDir($this->folderToClean);
        }
    }

    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__)."/Dataset/Esign.yml");
        return $dataset;
    }

    public function getMockMessageProducer()
    {
        $mockMessageProducer = Mockery::mock('Oxzion\Messaging\MessageProducer');
        $this->esignService->setMessageProducer($mockMessageProducer);
        return $mockMessageProducer;
    }

    private function getMockRestClient()
    {
        $mockRestClient = Mockery::mock('Oxzion\Utils\RestClient');
        $this->esignService->setRestClient($mockRestClient);
        return $mockRestClient;
    }

    private function getTestFile()
    {
        $targetFolder = sys_get_temp_dir(). DIRECTORY_SEPARATOR. "EsignServiceTest";
        FileUtils::createDirectory($targetFolder);
        $file = $targetFolder.DIRECTORY_SEPARATOR."temp.txt";
        
        return $file;
    }
    private function saveToFile(string $data)
    {
        $testFile = $this->getTestFile();
        file_put_contents($testFile, $data);
    }

    private function readFromFile()
    {
        $testFile = $this->getTestFile();
        $data = file_get_contents($testFile);
        return $data;
    }
    private function mockAuthTokenCall()
    {
        $mockRestClient = $this->getMockRestClient();
        $this->mockAuthToken($mockRestClient);
        return $mockRestClient;
    }

    private function mockAuthToken($mockRestClient)
    {
        $postData = array(
            'grant_type' => 'client_credentials',
            'username' => $this->config['esign']['username'],
            'password' => $this->config['esign']['password'],
            'client_id' => $this->config['esign']['clientid'],
            'client_secret' => $this->config['esign']['clientsecret'],
            'redirect_uri' => $this->config['esign']['callbackUrl']
        );
        $postData = http_build_query($postData);
        $headers = array(
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Content-Length' => strlen($postData)
        );
        $mockRestClient->expects('postWithHeaderAsBody')->with($this->config['esign']['url'], $postData, $headers)
        ->once()->andReturn(array("body" => '{"access_token": "'.$this->authToken.'","expires_in":3600,"token_type":"Bearer"}'));
    }

    public function testgetAuthToken()
    {
        if (enableEsign == 0) {
            $mockRestClient = $this->mockAuthTokenCall();
        }
        
        $authToken = $this->esignService->getAuthToken();
        $this->assertEquals(isset($authToken), true);
    }

    public function testSetupDocumentWithDocument()
    {
        AuthContext::put(AuthConstants::USER_ID, 1);
        AuthContext::put(AuthConstants::ACCOUNT_ID, 1);
        $fileName = "mockpdf_with_field.pdf";
        $signers = array(
                "name"=>"Signature Document",
                "message"=>"Please sign",
                "signers"=>[['participant' => ["email"=>"eox_user_1@gmail.com", 'name' => 'eox_user_1'],
                            "fields"=> array(
                                array(
                                "name"=>"signature_field",
                                "height"=>2,
                                "width"=>50,
                                "x"=>10,
                                "y"=>84,
                                "pageNumber"=>0
                             )
                            ) ]]);
        $documentUrl = __DIR__."/Files/$fileName";
        if (enableEsign == 0) {
            $mockRestClient = $this->mockAuthTokenCall();
            $headers = ['Authorization'=> 'Bearer '. $this->authToken];
            $putheader = array( "Authorization"=>" Bearer ". $this->authToken,
            "content-type"=>" application/json");
            $putData = array(
                    "callbackUrl" => $this->config['esign']['callbackUrl']
                    );
            $mockRestClient->expects('put')->with($this->config['esign']['docurl']."integrations/".$this->config['esign']['integrator'], $putData, $putheader)->once()->andReturn();
            $returnSub = array('lastEvaluatedKey' => '',
                                'data' => array(
                                    array(
                                        'id'            => '940720aa-a691-4809-b9d3-0121b2f2b502',
                                        'eventType'     => 'SIGNED',
                                        'integrationId' => $this->config['esign']['integrator']
                                    )

                                )
                            );
            $this->mockAuthToken($mockRestClient);
            $mockRestClient->expects('get')->with($this->config['esign']['docurl']."integrations/".$this->config['esign']['integrator']."/subscriptions", array(), $headers)->once()->andReturn(json_encode($returnSub));
            $this->mockAuthToken($mockRestClient);
            $url = $this->config['esign']['docurl']."integrations/".$this->config['esign']['integrator']."/subscriptions/".$returnSub['data'][0]['id'];
            $mockRestClient->expects('delete')->with($url, array(), $headers)->once()->andReturn();
            $this->mockAuthToken($mockRestClient);
            $url = $this->config['esign']['docurl']."subscriptions";
            $postData = array( "eventType" => "FINALIZED" );
            $returnAddSub = array("id"=>"029b93e1-6b04-4a07-ab1f-2486467c2bee","integrationId"=>$this->config['esign']['integrator'],"eventType"=>"SIGNED");
            $mockRestClient->expects('postWithHeader')->with($url, $postData, $headers)->once()->andReturn(json_encode($returnAddSub));

            $data = [
                "name" => $signers['name'],
                "message" => $signers['message'],
                "action" => "send",
                'callback[url]' => $this->config['esign']['callbackUrl'],
                "participants" => [[
                    "name" => $signers['signers'][0]['participant']['name'],
                    "email" => $signers['signers'][0]['participant']['email'],
                    "phone" => '',
                    "sms" => false
                ]],
                "fields" => [[
                    "name" => $signers['signers'][0]['fields'][0]['name'],
                    "required" => true,
                    "pageNumber" => $signers['signers'][0]['fields'][0]['pageNumber'],
                    "x" => $signers['signers'][0]['fields'][0]['x'],
                    "y" => $signers['signers'][0]['fields'][0]['y'],
                    "height" => $signers['signers'][0]['fields'][0]['height'],
                    "width" => $signers['signers'][0]['fields'][0]['width'],
                    "type" => 'SIGNATURE',
                    "assignedTo" => json_encode([
                        "name" => $signers['signers'][0]['participant']['name'],
                        "email" => $signers['signers'][0]['participant']['email'],
                        "phone" => '',
                        "sms" => false
                        ])
                ]]
            ];
            $data['fields'] = json_encode(array_values($data['fields']));
            $data['participants'] = json_encode(array_values($data['participants']));
            $fields = $signers['signers'][0]['fields'];
            $fields['type'] = 'SIGNATURE';
            $fields['required'] = true;
            $fields['assignedTo'] = json_encode($signers['signers'][0]['participant']);
            $returnData = ["data" => [ "id" => UuidUtil::uuid(),
                                        "name" => $data['name'],
                                        "message" => $data['message'],
                                        "participants" => json_encode([$signers['signers'][0]['participant']]),
                                        "fields" => json_encode($fields),
                                        "action" => "send",
                                        "group" => UuidUtil::uuid(),
                                        "sender" => "0f3c3dd9-04fa-4e01-b6c2-e9c3340514c0",
                                        "created" => "2020-12-27T12:35:10.203Z"
                                    ]
                            ];
            $fileData = array(FileUtils::getFileName($documentUrl) => $documentUrl );
            $this->mockAuthToken($mockRestClient);
            $mockRestClient->expects('postMultiPart')->with($this->config['esign']['docurl']."documents", $data, $fileData, $headers)->once()->andReturn(json_encode($returnData));
        }
        
        $refId = '20';
        
        $docId = $this->esignService->setupDocument($refId, $documentUrl, $signers);
        $this->saveToFile($docId);
        if (enableEsign == 0) {
            $this->assertEquals($returnData['data']['id'], $docId);
        }
        $this->assertEquals(isset($docId), true);
        $query = "SELECT * from ox_esign_document where doc_id = '".$docId."'";
        $result = $this->executeQueryTest($query);
        $destination = $this->config['APP_ESIGN_FOLDER'];
        $destination .= "/".$result[0]['uuid'];
        $this->folderToClean = $destination;
        $this->assertEquals(1, count($result));
        $this->assertEquals($refId, $result[0]['ref_id']);
        $this->assertEquals(EsignDocument::IN_PROGRESS, $result[0]['status']);
        $this->assertEquals(1, $result[0]['created_by']);
        $this->assertEquals(true, FileUtils::fileExists($destination."/$fileName"));
        $query = "SELECT * from ox_esign_document_signer where esign_document_id = ".$result[0]['id'];
        $result = $this->executeQueryTest($query);
        $this->assertEquals(1, count($result));
        $this->assertEquals(EsignDocumentSigner::IN_PROGRESS, $result[0]['status']);
        $this->assertEquals($signers['signers'][0]['participant']['email'], $result[0]['email']);
        $this->assertEquals($signers['signers'][0], json_decode($result[0]['details'], true));
    }

    public function testSetupDocumentWithoutDocument()
    {
        AuthContext::put(AuthConstants::USER_ID, 1);
        AuthContext::put(AuthConstants::ACCOUNT_ID, 1);
        $signers = array(
                "name"=>"Signature Document",
                "message"=>"Please sign",
                "signers"=>[['participant' => ["email"=>"eox_user_1@gmail.com", 'name' => 'eox_user_1'],
                            "fields"=> array(
                                array(
                                "name"=>"signature_field",
                                "height"=>2,
                                "width"=>50,
                                "x"=>10,
                                "y"=>84,
                                "pageNumber"=>0
                            )
                            ) ]]);
        $documentUrl ="    ";
        $refId = '20';
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage("Document not found");
        $this->esignService->setupDocument($refId, $documentUrl, $signers);
    }

    public function testSetupDocumentWithoutSigners()
    {
        AuthContext::put(AuthConstants::USER_ID, 1);
        AuthContext::put(AuthConstants::ACCOUNT_ID, 1);
        $fileName = "mockpdf_with_field.pdf";
        $signers = array();
        $documentUrl = __DIR__."/Files/$fileName";
        $refId = '20';
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage("signers not provided");
        $this->esignService->setupDocument($refId, $documentUrl, $signers);
    }
     
    public function testGetStatus()
    {
        $docId = $this->readFromFile();
        if (enableEsign == 0) {
            $mockRestClient = $this->mockAuthTokenCall();
            $headers = ['Authorization'=> 'Bearer '. $this->authToken];
            $returnData = ['data' => ['status' => 'READY_FOR_SIGNATURE']];
            $mockRestClient->expects('get')->with($this->config['esign']['docurl']."documents/$docId", [], $headers)->once()->andReturn(json_encode($returnData));
        }
        $status = $this->esignService->getDocumentStatus($docId);
        $this->assertEquals(isset($status), true);
        $this->assertEquals('READY_FOR_SIGNATURE', $status);
    }

    public function testGetSigningLink()
    {
        $docId = $this->readFromFile();
        if (enableEsign == 0) {
            $mockRestClient = $this->mockAuthTokenCall();
            $headers = ['Authorization'=> 'Bearer '. $this->authToken];
            $returnData = ['signingLink' => " https://lab.insuresign.com?d=YjhiOTI4MDktNTQ5NC00MmExLWExNDktZDljNjg3ZDVkNjA5JmVveF91c2VyXzFAZ21haWwuY29t"];
            $mockRestClient->expects('get')->with($this->config['esign']['docurl']."documents/$docId/signinglink", [], $headers)->once()->andReturn(json_encode($returnData));
        }
        $data = $this->esignService->getDocumentSigningLink($docId);
        $this->assertEquals(isset($data), true);
        if (enableEsign == 0) {
            $this->assertEquals($returnData['signingLink'], $data);
        }
    }

    public function testSignEvent()
    {
        $docId = $this->readFromFile();
        $query = "UPDATE ox_esign_document SET doc_id ='$docId' WHERE id=1 ";
        $result = $this->executeUpdate($query);
        $query = "SELECT * from ox_esign_document where doc_id = '$docId'";
        $docTable = $this->executeQueryTest($query);
        $destination = $this->config['APP_ESIGN_FOLDER'];
        $destination .= "/".$docTable[0]['uuid'];
        $this->folderToClean = $destination;
        $destination .= '/signed/signed.pdf';
        if (enableEsign == 0) {
            $mockRestClient = $this->mockAuthTokenCall();
            $headers = ['Authorization'=> 'Bearer '. $this->authToken];
            $returnData = ["downloadUrl"=> __DIR__."/Files/mockpdf_with_field.pdf"];
            $mockRestClient->expects('get')->with($this->config['esign']['docurl'].'documents/'.$docId.'/pdf', [], $headers)->once()->andReturn(json_encode($returnData));
        }
        $mockMessageProducer = $this->getMockMessageProducer();
        $payload = json_encode(['file'   => $destination,
                                      'refId' => $docTable[0]['ref_id']]);
        $mockMessageProducer->expects('sendTopic')->with($payload, 'DOCUMENT_SIGNED')->once()->andReturn();
        $data = $this->esignService->signEvent($docId, 'FINALIZED');
        $docTable = $this->executeQueryTest($query);

        $query = "SELECT * from ox_esign_document_signer where esign_document_id =1 ";
        $signerTable = $this->executeQueryTest($query);
        $this->assertEquals($docTable[0]['status'], EsignDocument::COMPLETED);
        $this->assertEquals($signerTable[0]['status'], EsignDocument::COMPLETED);
        $this->assertEquals(true, FileUtils::fileExists($destination));
    }
}
