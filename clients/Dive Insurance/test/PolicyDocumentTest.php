<?php
use Oxzion\Test\DelegateTest;
use Oxzion\AppDelegate\AppDelegateService;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Oxzion\Utils\FileUtils;
use PHPUnit\DbUnit\DataSet\DefaultDataSet;

class PolicyDocumentTest extends DelegateTest
{
    
    public function setUp() : void
    {
        $this->loadConfig();
        $config = $this->getApplicationConfig();
        $this->data = array(
            "appName" => 'ox_client_app',
            'UUID' => 8765765,
            'description' => 'FirstAppOfTheClient',
            'orgUuid' => '53012471-2863-4949-afb1-e69b0891c98a'
        );
        $migrationFolder = __DIR__  . "/../data/migrations/";
        $this->doMigration($this->data,$migrationFolder);
        $path = __DIR__.'/../../../api/v1/data/delegate/'.$this->data['UUID'];
        if (!is_link($path)) {
            symlink(__DIR__.'/../data/delegate/',$path);
        }
        $this->tempFile = $config['TEMPLATE_FOLDER'].$this->data['orgUuid'];
        $templateLocation = __DIR__."/../data/template";
        if(FileUtils::fileExists($this->tempFile)){
                FileUtils::rmDir($this->tempFile);
        }
        FileUtils::symlink($templateLocation, $this->tempFile);
        parent::setUp();               
    }

    public function getDataSet()
    {
        return new DefaultDataSet();
    }

    public function tearDown() : void
    {
        parent::tearDown();
        $path = __DIR__.'/../../../api/v1/data/delegate/'.$this->data['UUID'];
        if (is_link($path)) {
            unlink($path);
        }
        FileUtils::unlink($this->tempFile);
        $query = "DROP DATABASE " . $this->database;
        $statement = $this->getDbAdapter()->query($query);
        $result = $statement->execute();
        
    }

    public function testPolicyDocument()
    {
        $orgId = AuthContext::put(AuthConstants::ORG_ID, 1);
        AuthContext::put(AuthConstants::ORG_UUID, $this->data['orgUuid']);
        $appId = $this->data['UUID'];
        $data = ['initial_title' => 'PROFESSIONAL LIABILITY CERTIFICATE OF INSURANCE',
                'second_title' => 'CLAIMS MADE FORM',
                'state_id' => 'NY',
                'firstname' => 'Mohan',
                 'middlename' => 'Raj' ,
                 'lastname' => 'D',
                 'address1' => 'ABC 200',
                 'address2' => 'XYZ 300',
                 'city' => 'APO',
                 'state' => 'Armed Forces Europe',
                 'country' => 'US',
                 'zipcode' => '09522-9998',                
                 'member_no' => '34567',
                 'effective_date' => '06/30/2019',
                 'expiry_date' => '6/30/2020 12:01:00 AM',
                 'insured_status'=> 'Divester',
                 'physical_address' => 'APO,AE',
                 'single_limit' => '1,000,000',
                 'annual_aggregate' => '2,000,000',
                 'equipment_liability' => 'Not Included',
                 'cylinder_coverage' => 'Not Covered',
                 'update' => 1,
                 'update_date' => '08/06/2019',
                 'pageno' => 1,
                 'total_page' => 1,
                 'orgUuid' => $this->data['orgUuid'],
                 'product' => 'Individual Professional Liability'];
        $config = $this->getApplicationConfig();
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $content = $delegateService->execute($appId, 'PolicyDocument', $data);
        $this->assertEquals(isset($content['uuid']), true);
        $this->assertEquals(isset($content['policy_id']), true);
        $this->assertEquals(isset($content['carrier']), true);
        $this->assertEquals(isset($content['license_number']), true);
        $this->assertEquals(isset($content['certificate_no']), true);
        $doc = $this->tempFile."/".$content['uuid']."/";
        $this->assertTrue(is_file($doc."certificateOfInsurance.pdf"));
        $this->assertTrue(filesize($doc."certificateOfInsurance.pdf")>0);
        FileUtils::rmDir($doc);
        
    }
}