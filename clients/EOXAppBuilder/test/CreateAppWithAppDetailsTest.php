<?php

use Oxzion\Test\DelegateTest;
use Oxzion\AppDelegate\AppDelegateService;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Oxzion\Utils\FileUtils;
use PHPUnit\DbUnit\DataSet\DefaultDataSet;
use Oxzion\Utils\ArtifactUtils;
use Oxzion\Encryption\Crypto;
use Oxzion\DelegateException;

class CreateAppWithAppDetailsTest extends DelegateTest
{

    public function setUp(): void
    {
        $this->loadConfig();
        $config = $this->getApplicationConfig();
        $this->data = array(
            "appName" => 'ox_client_app',
            'UUID' => '41b77ef3-41db-4a52-8eb8-ba3ac9a9d771',
            'description' => 'FirstAppOfTheClient',
            'orgUuid' => '53012471-2863-4949-afb1-e69b0891c98a'
        );
        $migrationFolder = __DIR__  . "/../data/migrations/";
        $this->doMigration($this->data, $migrationFolder);
        $path = __DIR__ . '/../../../api/v1/data/delegate/' . $this->data['UUID'];
        if (!is_link($path)) {
            symlink(__DIR__ . '/../data/delegate/', $path);
        }

        $this->tempFile = $config['TEMPLATE_FOLDER'] . $this->data['orgUuid'];
        $templateLocation = __DIR__ . "/../data/template";

        if (FileUtils::fileExists($this->tempFile)) {
            FileUtils::rmDir($this->tempFile);
        }
        FileUtils::symlink($templateLocation, $this->tempFile);

        parent::setUp();
    }

    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__) . "/Dataset/applicationWIthUnwantedFields.yml");
        return $dataset;
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $path = __DIR__ . '/../../../api/v1/data/delegate/' . $this->data['UUID'];
        if (is_link($path)) {
            unlink($path);
        }
        FileUtils::unlink($this->tempFile);
        $query = "DROP DATABASE " . $this->database;//comment
        $statement = $this->getDbAdapter()->query($query);
        $result = $statement->execute();
    }

    public function testCreateAppWithAppDetails()
    {
        $config = $this->getApplicationConfig();
        $this->initAuthContext('admintest');
        $appId = $this->data['UUID'];
        $data = ['product' => 'Individual Professional Liability - New Policy','type' => 'PremiumRates'];
        $config = $this->getApplicationConfig();
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $content = $delegateService->execute($appId, 'CreateAppWithAppDetails', $data);
        print_r($content);exit;
        $this->assertNotEquals(sizeof($content),0);  
    }

    public function testGetYearListforSurplusLines()
    {
        $config = $this->getApplicationConfig();
        $this->initAuthContext('admintest');
        $appId = $this->data['UUID'];
        $data = ['product' => 'Individual Professional Liability','type' => 'SurplusLines'];
        $config = $this->getApplicationConfig();
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $content = $delegateService->execute($appId, 'YearList', $data);
        $this->assertNotEquals(sizeof($content),0);  
    }

    public function testGetYearListCarrierPolicy()
    {
        $config = $this->getApplicationConfig();
        $this->initAuthContext('admintest');
        $appId = $this->data['UUID'];
        $data = ['type' => 'CarrierPolicy','product' => 'Individual Professional Liability'];
        $config = $this->getApplicationConfig();
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $content = $delegateService->execute($appId, 'YearList', $data);
        $this->assertNotEquals(sizeof($content),0);  
    }

    public function testGetYearListStateTax()
    {
        $config = $this->getApplicationConfig();
        $this->initAuthContext('admintest');
        $appId = $this->data['UUID'];
        $data = ['coverage' => 'Liability','type' => 'StateTax'];
        $config = $this->getApplicationConfig();
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $content = $delegateService->execute($appId, 'YearList', $data);
        $this->assertNotEquals(sizeof($content),0);  
    }


    public function testGetPremiumRatesWithoutAdminPrivilege()
    {
        $config = $this->getApplicationConfig();
        $appId = $this->data['UUID'];
        $data = ['product' => 'Individual Professional Liability - New Policy','year' => 2019,'type' => 'coverage'];
        $config = $this->getApplicationConfig();
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $exception = $this->expectException(DelegateException::class);
        $this->expectExceptionMessage("You do not have access to this API");
        $content = $delegateService->execute($appId, 'GetPremiumRates', $data);
        $this->assertEquals($content, array());    
    }

    public function testGetPremiumRatesForCoverageNewPolicyIpl()
    {
        $config = $this->getApplicationConfig();
        $this->initAuthContext('admintest');
        $appId = $this->data['UUID'];
        $data = ['product' => 'Individual Professional Liability - New Policy','year' => 2020,'type' => 'coverage'];
        $config = $this->getApplicationConfig();
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $content = $delegateService->execute($appId, 'GetPremiumRates', $data);
        $this->assertEquals(sizeof($content),28);    
    }

    public function testGetPremiumRatesForSubCoverageNewPolicyIpl()
    {
        $config = $this->getApplicationConfig();
        $this->initAuthContext('admintest');
        $appId = $this->data['UUID'];
        $data = ['product' => 'Individual Professional Liability - New Policy','year' => 2020,'type' => 'subcoverage','coverage' => 'Excess Liability Coverage ($1,000,000)'];
        $config = $this->getApplicationConfig();
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $content = $delegateService->execute($appId, 'GetPremiumRates', $data);
        $this->assertEquals(sizeof($content),12);    
    }

    public function testGetPremiumRatesForCoverageEndorsementIpl()
    {
        $config = $this->getApplicationConfig();
        $this->initAuthContext('admintest');
        $appId = $this->data['UUID'];
        $data = ['product' => 'Individual Professional Liability - Endorsement','year' => 2020,'type' => 'coverage'];
        $config = $this->getApplicationConfig();
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $content = $delegateService->execute($appId, 'GetPremiumRates', $data);
        $this->assertEquals(sizeof($content),22);    
    }

    public function testGetPremiumRatesForSubCoverageEndorsementIpl()
    {
        $config = $this->getApplicationConfig();
        $this->initAuthContext('admintest');
        $appId = $this->data['UUID'];
        $data = ['product' => 'Individual Professional Liability - Endorsement','year' => 2020,'type' => 'subcoverage','coverage' => 'Assistant Instructor'];
        $config = $this->getApplicationConfig();
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $content = $delegateService->execute($appId, 'GetPremiumRates', $data);
        $this->assertEquals(sizeof($content),24);    
    }


    public function testGetPremiumRatesForCoverageNewPolicyEfr(){
        $config = $this->getApplicationConfig();
        $this->initAuthContext('admintest');
        $appId = $this->data['UUID'];
        $data = ['product' => 'Emergency First Response - New Policy','year' => 2020,'type' => 'coverage'];
        $config = $this->getApplicationConfig();
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $content = $delegateService->execute($appId, 'GetPremiumRates', $data);
        $this->assertEquals(sizeof($content) > 6,true);
    }

     public function testAddNewRecordStateTax(){
        $config = $this->getApplicationConfig();
        $this->initAuthContext('admintest');
        $appId = $this->data['UUID'];
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $data = ['coverage' => 'Liability','year' => 2020];
        $content = $delegateService->execute($appId, 'GetStateTaxRates', $data);
        $count = sizeof($content);
        $data1 = ['coverage' => 'Liability','year' => 2021];
        $content1 = $delegateService->execute($appId, 'GetStateTaxRates', $data1);
        $this->assertEquals($count,sizeof($content1));
    }


    public function testAddNewRecordCarrierPolicy(){
        $config = $this->getApplicationConfig();
        $this->initAuthContext('admintest');
        $appId = $this->data['UUID'];
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $data = ['year' => 2019];
        $content = $delegateService->execute($appId, 'GetCarrierAndPolicyNumber', $data);
        $count = sizeof($content);
        $data1 = ['year' => 2020];
        $content1 = $delegateService->execute($appId, 'GetCarrierAndPolicyNumber', $data1);
        $this->assertEquals($count,sizeof($content1));
    }

    public function testAddNewRecordSurplusLine(){
        $config = $this->getApplicationConfig();
        $this->initAuthContext('admintest');
        $appId = $this->data['UUID'];
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $data = ['year' => 2018,'product' => 'Individual Professional Liability'];
        $content = $delegateService->execute($appId, 'GetSurplusLines', $data);
        $this->assertEquals(sizeof($content),54);
        $temFolder = $config['TEMPLATE_FOLDER'].$this->data['orgUuid'].'/SurplusLines/IPL/2018';
        FileUtils::rmDir($temFolder);
    }

    public function testUpdatePremiumRates(){
        $config = $this->getApplicationConfig();
        $this->initAuthContext('admintest');
        $appId = $this->data['UUID'];
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $data = ['product' => 'Individual Professional Liability - New Policy','year' => 2020,'type' => 'subcoverage','coverage' => 'Instructor'];
        $content = $delegateService->execute($appId, 'GetPremiumRates', $data);
        $data1 = ['id' => $content[0]['id'],'premium' => 300,'tax' => 3];
        $delegateService->execute($appId, 'UpdatePolicyRates', $data1);
        $content1 = $delegateService->execute($appId, 'GetPremiumRates', $data);
        $this->assertEquals($content1[0]['id'],$data1['id']);
        $this->assertEquals($content1[0]['premium'],$data1['premium']);
        $this->assertEquals($content1[0]['tax'],$data1['tax']);
    }

    public function testUpdateSurplusLine(){
        $config = $this->getApplicationConfig();
        $this->initAuthContext('admintest');
        $appId = $this->data['UUID'];
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $data = ['year' => 2018,'product' => 'Individual Professional Liability'];
        $content1 = $delegateService->execute($appId, 'GetSurplusLines', $data);
        $data1 = ['state' => 'Alabama','surplusLine' => 'New Surplus','product' => 'Individual Professional Liability','year' => 2018];
        $content2 = $delegateService->execute($appId, 'UpdateSurplusLines', $data1);
        $this->assertEquals($content2,$data1);
        $content3 = $delegateService->execute($appId, 'GetSurplusLines', $data);
        foreach ($content3 as $key => $value) {
            if($value['state'] == 'Alabama'){
                $keyValue = $key;
                break;
            }
        }
        $this->assertEquals($content3[$keyValue]['surplusLine'],'New Surplus');
        $temFolder = $config['TEMPLATE_FOLDER'].$this->data['orgUuid'].'/SurplusLines/IPL/2018';
        FileUtils::rmDir($temFolder);
    }

    public function testUpdateCarrierPolicy(){
        $config = $this->getApplicationConfig();
        $this->initAuthContext('admintest');
        $appId = $this->data['UUID'];
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $data = ['year' => 2019];
        $content = $delegateService->execute($appId, 'GetCarrierAndPolicyNumber', $data);
        $data1 = ['id' => $content[0]['id'],'carrier' => 'New Carrier','policy_number' => 'ABDG345'];
        $delegateService->execute($appId, 'UpdateCarrierandPolicyNumber', $data1);
        $content1 = $delegateService->execute($appId, 'GetCarrierAndPolicyNumber', $data);
        $this->assertEquals($content1[0]['id'],$data1['id']);
        $this->assertEquals($content1[0]['carrier'],$data1['carrier']);
        $this->assertEquals($content1[0]['policy_number'],$data1['policy_number']);
    }

    public function testUpdateStateTax(){
        $config = $this->getApplicationConfig();
        $this->initAuthContext('admintest');
        $appId = $this->data['UUID'];
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $data = ['product' => 'Individual Professional Liability - New Policy','year' => 2019,'coverage' => 'Liability'];
        $content = $delegateService->execute($appId, 'GetStateTaxRates', $data);
        $data1 = ['id' => $content[0]['id'],'percentage' => 3];
        $delegateService->execute($appId, 'UpdateStateTaxRates', $data1);
        $content1 = $delegateService->execute($appId, 'GetStateTaxRates', $data);
        $this->assertEquals($content1[0]['id'],$data1['id']);
        $this->assertEquals($content1[0]['percentage'],$data1['percentage']);
    }


    public function testRemoveRecord(){
        $config = $this->getApplicationConfig();
        $this->initAuthContext('admintest');
        $appId = $this->data['UUID'];
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $data = ['product' => 'Individual Professional Liability - Endorsement','year' => 2020,'type' => 'subcoverage','coverage' => 'Instructor'];
        $content = $delegateService->execute($appId, 'GetPremiumRates', $data);
        $contentSize = sizeof($content) - 1;
        $data1 = ['id' => $content[0]['id'],'type' => 'remove'];
        $delegateService->execute($appId, 'AddOrRemovePolicyRates', $data1);
        $content1 = $delegateService->execute($appId, 'GetPremiumRates', $data);
        $contentSize1 = sizeof($content1);
        $this->assertEquals($contentSize,$contentSize1);
    }



    public function testAddNewEndorsementRateWithExistingRecord()
    {
        $config = $this->getApplicationConfig();
        $this->initAuthContext('admintest');
        $appId = $this->data['UUID'];
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $data = ['product' => 'Individual Professional Liability - Endorsement', 'year' => 2019, 'type' => 'add', 'month' => 'January', 'premium' => 100, 'tax' => 0, 'padi_fee' => 0, 'total' => 0, 'coverage' => 'Excess Liability Coverage ($1,000,000)', 'previous_coverage' => '0 Excess', 'coverage_category' => 'EXCESS_LIABILITY'];
        $exception = $this->expectException(DelegateException::class);
        $this->expectExceptionMessage("Record already exists");
        $content = $delegateService->execute($appId, 'AddOrRemovePolicyRates', $data);
    }



    public function testAddNewEndorsementRate()
    {
        $config = $this->getApplicationConfig();
        $this->initAuthContext('admintest');
        $appId = $this->data['UUID'];
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $data = ['product' => 'Individual Professional Liability - Endorsement','year' => 2020,'type' => 'subcoverage','coverage' => 'Assistant Instructor'];
        $content = $delegateService->execute($appId, 'GetPremiumRates', $data);
        $data1 = ['id' => $content[0]['id'],'type' => 'remove'];
        $delegateService->execute($appId, 'AddOrRemovePolicyRates', $data1);
        $content1 = $delegateService->execute($appId, 'GetPremiumRates', $data);
        $this->assertEquals(sizeof($content) - 1,sizeof($content1));
        $data2 = ['product' => 'Individual Professional Liability - Endorsement', 'year' => 2020, 'type' => 'add', 'month' => $content[0]['month'], 'premium' => 100, 'tax' => 0, 'padi_fee' => 0, 'total' => 100, 'coverage' => $content[0]['coverage'], 'previous_coverage' => $data['coverage'], 'coverage_category' => 'INSURED_STATUS'];
        $delegateService->execute($appId, 'AddOrRemovePolicyRates', $data2);
        $data3 = ['product' => 'Individual Professional Liability - Endorsement','year' => 2020,'type' => 'subcoverage','coverage' => 'Assistant Instructor'];
        $content3 = $delegateService->execute($appId, 'GetPremiumRates', $data3);
        $this->assertEquals(sizeof($content),sizeof($content3));
    }
}
