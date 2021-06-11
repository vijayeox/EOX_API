<?php
use Oxzion\Test\DelegateTest;
use Oxzion\AppDelegate\AppDelegateService;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Zend\Db\Adapter\Adapter;
use PHPUnit\DbUnit\DataSet\DefaultDataSet;
use Oxzion\Service\TemplateService;
use Oxzion\Document\DocumentGeneratorImpl;
use Oxzion\Document\DocumentBuilder;
use Oxzion\Utils\FileUtils;
use Oxzion\Transaction\TransactionManager;

class OnTarcPDFGenerationTest extends DelegateTest
{

    public function setUp() : void
    {
        $this->loadConfig();
        $config = $this->getApplicationConfig();
        $this->adapter = new Adapter($config['db']);
        $this->data = array(
            "appName" => 'ox_client_app',
            'UUID' => '7a5ebf8a-2133-461a-acca-ab1d09df6224',
            'description' => 'FirstAppOfTheClient',
            'orgUuid' => '5060e4d5-006a-4054-85c0-bbf78579412d',
        );
        $path = __DIR__.'/../../../api/data/delegate/'.$this->data['UUID'];
        if (!is_link($path)) {
            symlink(__DIR__.'/../data/delegate/',$path);
        }
        parent::setUp();
    }

    public function getDataSet()
    {
        return new DefaultDataSet();
    }

    public function tearDown() : void
    {
        $tm = TransactionManager::getInstance($this->adapter);
        $tm->rollback();
        $_REQUEST = [];
    }

    public function testOnTarcPDFGeneration()
    {
        $data = ['Date' => '05/25/2021', 'autoLiability' => '1', 'cargoInsurance' => '2','orgUuid' => '5060e4d5-006a-4054-85c0-bbf78579412d','appId' => 'a4b1f073-fc20-477f-a804-1aa206938c42','accountId' => '5060e4d5-006a-4054-85c0-bbf78579412d'];
        $appId = $this->data['UUID'];
        AuthContext::put(AuthConstants::ORG_UUID, $data['orgUuid']);
        $config = $this->getApplicationConfig();
        $templateService = new TemplateService($config, $this->adapter);
        $documentGenerator = new DocumentGeneratorImpl();
        $documentBuilder = new DocumentBuilder($config, $templateService, $documentGenerator);
        $template = 'OnTracRSPComplianceChecklistTemplate';
        $tempFile = $config['CLIENT_FOLDER']."/"."hubdrive/data/template"."/".$template.".pdf";
        $templateLocation =  $config['CLIENT_FOLDER']."/"."hubdrive/data/template";
        if(FileUtils::fileExists($tempFile)){
            FileUtils::rmDir($tempFile);
        }
        FileUtils::symlink($templateLocation, $tempFile);
        $destination = $config['APP_DOCUMENT_FOLDER'].$data['orgUuid']."/OnTracRSPComplianceChecklistTemplate.pdf";
        $documentBuilder->fillPDFForm($template.".pdf",$data,$destination);
        $this->assertTrue(is_file($destination));
        $this->assertTrue(filesize($destination)>0);
        FileUtils::deleteFile("OnTracRSPComplianceChecklistTemplate.pdf", $config['APP_DOCUMENT_FOLDER'].$data['orgUuid']."/");
        FileUtils::unlink($tempFile);
    }
}