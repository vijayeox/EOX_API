<?php
use Oxzion\Test\DelegateTest;
use Oxzion\AppDelegate\AppDelegateService;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Oxzion\Utils\FileUtils;
use PHPUnit\DbUnit\DataSet\DefaultDataSet;
use Oxzion\Utils\ArtifactUtils;

class SaveDriverUnitTest extends DelegateTest
{
	public function setUp() : void
    {
        $this->loadConfig();
        $config = $this->getApplicationConfig();
        $this->data = array(
            "appName" => 'ox_client_app',
            'UUID' => '5060e4d5-006a-4054-85c0-bbf78579412d',
            'description' => 'Hubdrive Online Client Application',
            'accountUuid' => '53012471-2863-4949-afb1-e69b0891c98a',
            'accountId' =>'1'
        );
        $migrationFolder = __DIR__  . "/../data/migrations/";
        $this->doMigration($this->data,$migrationFolder);
        $path = __DIR__.'/../../../api/data/delegate/'.$this->data['UUID'];
        if (!is_link($path)) {
            symlink(__DIR__.'/../data/delegate/',$path);
        }



        parent::setUp();
    }

    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__) . "/Dataset/SaveDriverUnit.yml");
        return $dataset;
    }

    public function tearDown() : void
    {
        parent::tearDown();
        $path = __DIR__.'/../../../api/v1/data/delegate/'.$this->data['UUID'];
        if (is_link($path)) {
            unlink($path);
        }

        $query = "DROP DATABASE " . $this->database;//comment
        $statement = $this->getDbAdapter()->query($query);
        $result = $statement->execute();

    }

    public function testSaveDriverUnit()
    {
        $accountId=AuthContext::put(AuthConstants::ACCOUNT_ID, $this->data['accountId']);
        $accountUuid = AuthContext::put(AuthConstants::ACCOUNT_UUID,$this->data['accountUuid']);
        $data = array();
        $config = $this->getApplicationConfig();
        $appId = $this->data['UUID'];
        $data = Array
        (
            "driverDataGrid" => '[{"driverFirstName":"Jingle","driverId":1,"driverMiddleName":"J","driverLastName":"Jack","driverDateofBirth":"2021-05-31T12:00:00+05:30","driverSsn":"XXX8293042","driverLicense":"12355667","doesthedriverhave2YearsofcommercialdrivingexperienceinNorthAmerica":"yes","pleaseindicatetypeofdriver":"coDriver","pleaseselectthepaidbyoption":1099},{"driverFirstName":"John","driverId":2,"driverMiddleName":"D","driverLastName":"Unlup","driverDateofBirth":"2021-05-17T12:00:00+05:30","driverSsn":"XXX8584930","driverLicense":"1235589","doesthedriverhave2YearsofcommercialdrivingexperienceinNorthAmerica":"yes","pleaseindicatetypeofdriver":"coDriver","pleaseselectthepaidbyoption":"w2"}]',
            "unitDataGrid" => '[{"unitMake":"Chevrolet","unitModel":"Camaro","unitVin":"4Y1SL65848Z411439","unitGaragingCity":"Arisinakunte","addresswheretheunitisgaraged":"Kothnur Village Main Road, Elita Promenade","unitGaragingState":"Please Select","registeredownerfullname":"Ajit rao","isthisunitleasedorfinanced":"no","page7PanelWell3Doyouwanttoadddriverdetails":"","zipCode":"56007","doYouWantToAddAdditionalInsured":"","doesTheUnitHaveADriver":"yes","unitYear":1998,"driverSelect":[1]},{"unitMake":"Ford","unitModel":"Mustang","unitVin":"4Y1SL65848Z411438","unitGaragingCity":"City","addresswheretheunitisgaraged":"Addr1","unitGaragingState":"CA","zipCode":"90623","registeredownerfullname":"Admin User","isthisunitleasedorfinanced":"no","doYouWantToAddAdditionalInsured":"yes","doesTheUnitHaveADriver":"yes","unitYear":1997,"additionalInsuredDetailsDataGrid":[{"page7PanelAdditionalInsuredDetailsColumnsAdditionalinsuredname":"","page7PanelAdditionalInsuredDetailsColumnsAddress":"","page7PanelAdditionalInsuredDetailsColumns2City":"","page7PanelAdditionalInsuredDetailsColumns2State":"","additionalInsuredName":"Jimmy","additionalInsuredAddress":"#445","additionalInsuredCity":"Thing","additionalInsuredState":"","additionalInsuredZipCode":"78903"}],"driverSelect":[2],"leasedorFinancedDetailsDataGrid":[{"page7PanelLeasedorFinancedDetailsColumnsNameofthefinancialinstitution":"","page7PanelLeasedorFinancedDetailsColumnsAddress":"","page7PanelLeasedorFinancedDetailsColumns2City":"","page7PanelLeasedorFinancedDetailsColumns2State":"","financialInstitution":"","leasedorFinancedDetailsAddress":"","leasedorFinancedDetailsCity":"","leasedorFinancedDetailsState":"","leasedorFinancedDetailsZipcode":""}]}]'
        );


        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $content = $delegateService->execute($appId, 'SaveDriverUnit', $data);

        $select = "SELECT * FROM `driver` WHERE ox_app_account_id=:accountId AND ssn=:ssn ";
        $result = $this->persistence->selectQuery($select,[
            "accountId"=>$this->data['accountId'],
            "ssn" => "XXX8293042"
        ],true);
      
        $driverId = $result[0]['id'];
        $this->assertEquals($result[0]['first_name'],"Jingle");
        $this->assertEquals($result[0]['license_num'],"12355667");

        $select = "SELECT * FROM `unit` WHERE ox_app_account_id=:accountId AND vin =:vin ";
        $result = $this->persistence->selectQuery($select,[
            "accountId"=>$this->data['accountId'],
            "vin" => "4Y1SL65848Z411439"
        ],true);

        $this->assertEquals($result[0]['make'],"Chevrolet");
        $this->assertEquals($result[0]['model'],"Camaro");
        $unitId = $result[0]['id'];
        $select = "SELECT * FROM `driver_unit` WHERE ox_app_account_id=:accountId AND unit_id =:unitId";
        $result = $this->persistence->selectQuery($select,[
            "accountId"=>$this->data['accountId'],
            "unitId" => $unitId
        ],true);
        $this->assertEquals(count($result),1);
        $this->assertEquals($result[0]['driver_id'],$driverId);
    }

    public function testDriverUnitUpdatesOnMultipleSave()
    {
        $accountId=AuthContext::put(AuthConstants::ACCOUNT_ID, $this->data['accountId']);
        $accountUuid = AuthContext::put(AuthConstants::ACCOUNT_UUID,$this->data['accountUuid']);
        $data = array();
        $config = $this->getApplicationConfig();
        $appId = $this->data['UUID'];
        $data = Array
        (
            "driverDataGrid" => '[{"driverFirstName":"Jingle","driverId":1,"driverMiddleName":"J","driverLastName":"Jack","driverDateofBirth":"2021-05-31T12:00:00+05:30","driverSsn":"XXX8293042","driverLicense":"12355667","doesthedriverhave2YearsofcommercialdrivingexperienceinNorthAmerica":"yes","pleaseindicatetypeofdriver":"coDriver","pleaseselectthepaidbyoption":1099},{"driverFirstName":"John","driverId":2,"driverMiddleName":"D","driverLastName":"Unlup","driverDateofBirth":"2021-05-17T12:00:00+05:30","driverSsn":"XXX8584930","driverLicense":"1235589","doesthedriverhave2YearsofcommercialdrivingexperienceinNorthAmerica":"yes","pleaseindicatetypeofdriver":"coDriver","pleaseselectthepaidbyoption":"w2"}]',
            "unitDataGrid" => '[{"unitMake":"Chevrolet","unitModel":"Camaro","unitVin":"4Y1SL65848Z411439","unitGaragingCity":"Arisinakunte","addresswheretheunitisgaraged":"Kothnur Village Main Road, Elita Promenade","unitGaragingState":"Please Select","registeredownerfullname":"Ajit rao","isthisunitleasedorfinanced":"no","page7PanelWell3Doyouwanttoadddriverdetails":"","zipCode":"56007","doYouWantToAddAdditionalInsured":"","doesTheUnitHaveADriver":"yes","unitYear":1998,"driverSelect":[1]},{"unitMake":"Ford","unitModel":"Mustang","unitVin":"4Y1SL65848Z411438","unitGaragingCity":"City","addresswheretheunitisgaraged":"Addr1","unitGaragingState":"CA","zipCode":"90623","registeredownerfullname":"Admin User","isthisunitleasedorfinanced":"no","doYouWantToAddAdditionalInsured":"yes","doesTheUnitHaveADriver":"yes","unitYear":1997,"additionalInsuredDetailsDataGrid":[{"page7PanelAdditionalInsuredDetailsColumnsAdditionalinsuredname":"","page7PanelAdditionalInsuredDetailsColumnsAddress":"","page7PanelAdditionalInsuredDetailsColumns2City":"","page7PanelAdditionalInsuredDetailsColumns2State":"","additionalInsuredName":"Jimmy","additionalInsuredAddress":"#445","additionalInsuredCity":"Thing","additionalInsuredState":"","additionalInsuredZipCode":"78903"}],"driverSelect":[2],"leasedorFinancedDetailsDataGrid":[{"page7PanelLeasedorFinancedDetailsColumnsNameofthefinancialinstitution":"","page7PanelLeasedorFinancedDetailsColumnsAddress":"","page7PanelLeasedorFinancedDetailsColumns2City":"","page7PanelLeasedorFinancedDetailsColumns2State":"","financialInstitution":"","leasedorFinancedDetailsAddress":"","leasedorFinancedDetailsCity":"","leasedorFinancedDetailsState":"","leasedorFinancedDetailsZipcode":""}]}]'
        );


        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $content = $delegateService->execute($appId, 'SaveDriverUnit', $data);

        $data = Array
        (
            "driverDataGrid" => '[{"driverFirstName":"Johnny","driverId":1,"driverMiddleName":"J","driverLastName":"Jack","driverDateofBirth":"2021-05-31T12:00:00+05:30","driverSsn":"XXX8293042","driverLicense":"654321","doesthedriverhave2YearsofcommercialdrivingexperienceinNorthAmerica":"yes","pleaseindicatetypeofdriver":"coDriver","pleaseselectthepaidbyoption":1099},{"driverFirstName":"John","driverId":2,"driverMiddleName":"D","driverLastName":"Unlup","driverDateofBirth":"2021-05-17T12:00:00+05:30","driverSsn":"XXX8584930","driverLicense":"1235589","doesthedriverhave2YearsofcommercialdrivingexperienceinNorthAmerica":"yes","pleaseindicatetypeofdriver":"coDriver","pleaseselectthepaidbyoption":"w2"}]',
            "unitDataGrid" => '[{"unitMake":"Audi","unitModel":"Q4","unitVin":"4Y1SL65848Z411439","unitGaragingCity":"Arisinakunte","addresswheretheunitisgaraged":"Kothnur Village Main Road, Elita Promenade","unitGaragingState":"Please Select","registeredownerfullname":"Ajit rao","isthisunitleasedorfinanced":"no","page7PanelWell3Doyouwanttoadddriverdetails":"","zipCode":"56007","doYouWantToAddAdditionalInsured":"","doesTheUnitHaveADriver":"yes","unitYear":1998,"driverSelect":[1]},{"unitMake":"Ford","unitModel":"Mustang","unitVin":"4Y1SL65848Z411438","unitGaragingCity":"City","addresswheretheunitisgaraged":"Addr1","unitGaragingState":"CA","zipCode":"90623","registeredownerfullname":"Admin User","isthisunitleasedorfinanced":"no","doYouWantToAddAdditionalInsured":"yes","doesTheUnitHaveADriver":"yes","unitYear":1997,"additionalInsuredDetailsDataGrid":[{"page7PanelAdditionalInsuredDetailsColumnsAdditionalinsuredname":"","page7PanelAdditionalInsuredDetailsColumnsAddress":"","page7PanelAdditionalInsuredDetailsColumns2City":"","page7PanelAdditionalInsuredDetailsColumns2State":"","additionalInsuredName":"Jimmy","additionalInsuredAddress":"#445","additionalInsuredCity":"Thing","additionalInsuredState":"","additionalInsuredZipCode":"78903"}],"driverSelect":[2],"leasedorFinancedDetailsDataGrid":[{"page7PanelLeasedorFinancedDetailsColumnsNameofthefinancialinstitution":"","page7PanelLeasedorFinancedDetailsColumnsAddress":"","page7PanelLeasedorFinancedDetailsColumns2City":"","page7PanelLeasedorFinancedDetailsColumns2State":"","financialInstitution":"","leasedorFinancedDetailsAddress":"","leasedorFinancedDetailsCity":"","leasedorFinancedDetailsState":"","leasedorFinancedDetailsZipcode":""}]}]'
        );
        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $content = $delegateService->execute($appId, 'SaveDriverUnit', $data);

        $select = "SELECT * FROM `driver` WHERE ox_app_account_id=:accountId AND ssn=:ssn ";
        $result = $this->persistence->selectQuery($select,[
            "accountId"=>$this->data['accountId'],
            "ssn" => "XXX8293042"
        ],true);
      
        $driverId = $result[0]['id'];
        $this->assertEquals($result[0]['first_name'],"Johnny");
        $this->assertEquals($result[0]['license_num'],"654321");

        $select = "SELECT * FROM `unit` WHERE ox_app_account_id=:accountId AND vin =:vin ";
        $result = $this->persistence->selectQuery($select,[
            "accountId"=>$this->data['accountId'],
            "vin" => "4Y1SL65848Z411439"
        ],true);

        $this->assertEquals($result[0]['make'],"Audi");
        $this->assertEquals($result[0]['model'],"Q4");
        $unitId = $result[0]['id'];
        $select = "SELECT * FROM `driver_unit` WHERE ox_app_account_id=:accountId AND unit_id =:unitId";
        $result = $this->persistence->selectQuery($select,[
            "accountId"=>$this->data['accountId'],
            "unitId" => $unitId
        ],true);
        $this->assertEquals(count($result),1);
        $this->assertEquals($result[0]['driver_id'],$driverId);
    }

    public function testSaveOnUnitDriverMappingChange()
    {
        $accountId=AuthContext::put(AuthConstants::ACCOUNT_ID, $this->data['accountId']);
        $accountUuid = AuthContext::put(AuthConstants::ACCOUNT_UUID,$this->data['accountUuid']);
        $data = array();
        $config = $this->getApplicationConfig();
        $appId = $this->data['UUID'];
        $data = Array
        (
            "driverDataGrid" => '[{"driverFirstName":"Jingle","driverId":1,"driverMiddleName":"J","driverLastName":"Jack","driverDateofBirth":"2021-05-31T12:00:00+05:30","driverSsn":"XXX8293042","driverLicense":"12355667","doesthedriverhave2YearsofcommercialdrivingexperienceinNorthAmerica":"yes","pleaseindicatetypeofdriver":"coDriver","pleaseselectthepaidbyoption":1099},{"driverFirstName":"John","driverId":2,"driverMiddleName":"D","driverLastName":"Unlup","driverDateofBirth":"2021-05-17T12:00:00+05:30","driverSsn":"XXX8584930","driverLicense":"1235589","doesthedriverhave2YearsofcommercialdrivingexperienceinNorthAmerica":"yes","pleaseindicatetypeofdriver":"coDriver","pleaseselectthepaidbyoption":"w2"}]',
            "unitDataGrid" => '[{"unitMake":"Chevrolet","unitModel":"Camaro","unitVin":"4Y1SL65848Z411439","unitGaragingCity":"Arisinakunte","addresswheretheunitisgaraged":"Kothnur Village Main Road, Elita Promenade","unitGaragingState":"Please Select","registeredownerfullname":"Ajit rao","isthisunitleasedorfinanced":"no","page7PanelWell3Doyouwanttoadddriverdetails":"","zipCode":"56007","doYouWantToAddAdditionalInsured":"","doesTheUnitHaveADriver":"yes","unitYear":1998,"driverSelect":[1]},{"unitMake":"Ford","unitModel":"Mustang","unitVin":"4Y1SL65848Z411438","unitGaragingCity":"City","addresswheretheunitisgaraged":"Addr1","unitGaragingState":"CA","zipCode":"90623","registeredownerfullname":"Admin User","isthisunitleasedorfinanced":"no","doYouWantToAddAdditionalInsured":"yes","doesTheUnitHaveADriver":"yes","unitYear":1997,"additionalInsuredDetailsDataGrid":[{"page7PanelAdditionalInsuredDetailsColumnsAdditionalinsuredname":"","page7PanelAdditionalInsuredDetailsColumnsAddress":"","page7PanelAdditionalInsuredDetailsColumns2City":"","page7PanelAdditionalInsuredDetailsColumns2State":"","additionalInsuredName":"Jimmy","additionalInsuredAddress":"#445","additionalInsuredCity":"Thing","additionalInsuredState":"","additionalInsuredZipCode":"78903"}],"driverSelect":[2],"leasedorFinancedDetailsDataGrid":[{"page7PanelLeasedorFinancedDetailsColumnsNameofthefinancialinstitution":"","page7PanelLeasedorFinancedDetailsColumnsAddress":"","page7PanelLeasedorFinancedDetailsColumns2City":"","page7PanelLeasedorFinancedDetailsColumns2State":"","financialInstitution":"","leasedorFinancedDetailsAddress":"","leasedorFinancedDetailsCity":"","leasedorFinancedDetailsState":"","leasedorFinancedDetailsZipcode":""}]}]'
        );


        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $content = $delegateService->execute($appId, 'SaveDriverUnit', $data);

        $data = Array
        (
            "driverDataGrid" => '[{"driverFirstName":"Jingle","driverId":1,"driverMiddleName":"J","driverLastName":"Jack","driverDateofBirth":"2021-05-31T12:00:00+05:30","driverSsn":"XXX8293042","driverLicense":"12355667","doesthedriverhave2YearsofcommercialdrivingexperienceinNorthAmerica":"yes","pleaseindicatetypeofdriver":"coDriver","pleaseselectthepaidbyoption":1099},{"driverFirstName":"John","driverId":2,"driverMiddleName":"D","driverLastName":"Unlup","driverDateofBirth":"2021-05-17T12:00:00+05:30","driverSsn":"XXX8584930","driverLicense":"1235589","doesthedriverhave2YearsofcommercialdrivingexperienceinNorthAmerica":"yes","pleaseindicatetypeofdriver":"coDriver","pleaseselectthepaidbyoption":"w2"}]',
            "unitDataGrid" => '[{"unitMake":"Chevrolet","unitModel":"Camaro","unitVin":"4Y1SL65848Z411439","unitGaragingCity":"Arisinakunte","addresswheretheunitisgaraged":"Kothnur Village Main Road, Elita Promenade","unitGaragingState":"Please Select","registeredownerfullname":"Ajit rao","isthisunitleasedorfinanced":"no","page7PanelWell3Doyouwanttoadddriverdetails":"","zipCode":"56007","doYouWantToAddAdditionalInsured":"","doesTheUnitHaveADriver":"yes","unitYear":1998,"driverSelect":[1,2]}]'
        );

        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $content = $delegateService->execute($appId, 'SaveDriverUnit', $data);


        $select = "SELECT * FROM `unit` WHERE ox_app_account_id=:accountId AND vin =:vin ";
        $result = $this->persistence->selectQuery($select,[
            "accountId"=>$this->data['accountId'],
            "vin" => "4Y1SL65848Z411439"
        ],true);

        $this->assertEquals(count($result),1);
        $this->assertEquals($result[0]['make'],"Chevrolet");
        $unitId = $result[0]['id'];
        $select = "SELECT * FROM `driver_unit` as `du` INNER JOIN `driver` as `d` on `du`.driver_id = `d`.id  WHERE `du`.ox_app_account_id=:accountId AND `du`.unit_id =:unitId";
        $result = $this->persistence->selectQuery($select,[
            "accountId"=>$this->data['accountId'],
            "unitId" => $unitId
        ],true);
       $this->assertEquals(count($result),2);
       foreach($result as $index => $driverDetails)
       {
           if($driverDetails['ssn'] == "XXX8293042")
           {
               $this->assertEquals($result[$index]['first_name'],"Jingle");
           }
           else if($driverDetails['ssn'] == "XXX8584930")
           {
            $this->assertEquals($result[$index]['first_name'],"John");
           }
       }
    }

    public function testSaveOnUnitRemoved()
    {
        $accountId=AuthContext::put(AuthConstants::ACCOUNT_ID, $this->data['accountId']);
        $accountUuid = AuthContext::put(AuthConstants::ACCOUNT_UUID,$this->data['accountUuid']);
        $data = array();
        $config = $this->getApplicationConfig();
        $appId = $this->data['UUID'];
        $data = Array
        (
            "driverDataGrid" => '[{"driverFirstName":"Jingle","driverId":1,"driverMiddleName":"J","driverLastName":"Jack","driverDateofBirth":"2021-05-31T12:00:00+05:30","driverSsn":"XXX8293042","driverLicense":"12355667","doesthedriverhave2YearsofcommercialdrivingexperienceinNorthAmerica":"yes","pleaseindicatetypeofdriver":"coDriver","pleaseselectthepaidbyoption":1099},{"driverFirstName":"John","driverId":2,"driverMiddleName":"D","driverLastName":"Unlup","driverDateofBirth":"2021-05-17T12:00:00+05:30","driverSsn":"XXX8584930","driverLicense":"1235589","doesthedriverhave2YearsofcommercialdrivingexperienceinNorthAmerica":"yes","pleaseindicatetypeofdriver":"coDriver","pleaseselectthepaidbyoption":"w2"}]',
            "unitDataGrid" => '[{"unitMake":"Chevrolet","unitModel":"Camaro","unitVin":"4Y1SL65848Z411439","unitGaragingCity":"Arisinakunte","addresswheretheunitisgaraged":"Kothnur Village Main Road, Elita Promenade","unitGaragingState":"Please Select","registeredownerfullname":"Ajit rao","isthisunitleasedorfinanced":"no","page7PanelWell3Doyouwanttoadddriverdetails":"","zipCode":"56007","doYouWantToAddAdditionalInsured":"","doesTheUnitHaveADriver":"yes","unitYear":1998,"driverSelect":[1]},{"unitMake":"Ford","unitModel":"Mustang","unitVin":"4Y1SL65848Z411438","unitGaragingCity":"City","addresswheretheunitisgaraged":"Addr1","unitGaragingState":"CA","zipCode":"90623","registeredownerfullname":"Admin User","isthisunitleasedorfinanced":"no","doYouWantToAddAdditionalInsured":"yes","doesTheUnitHaveADriver":"yes","unitYear":1997,"additionalInsuredDetailsDataGrid":[{"page7PanelAdditionalInsuredDetailsColumnsAdditionalinsuredname":"","page7PanelAdditionalInsuredDetailsColumnsAddress":"","page7PanelAdditionalInsuredDetailsColumns2City":"","page7PanelAdditionalInsuredDetailsColumns2State":"","additionalInsuredName":"Jimmy","additionalInsuredAddress":"#445","additionalInsuredCity":"Thing","additionalInsuredState":"","additionalInsuredZipCode":"78903"}],"driverSelect":[2],"leasedorFinancedDetailsDataGrid":[{"page7PanelLeasedorFinancedDetailsColumnsNameofthefinancialinstitution":"","page7PanelLeasedorFinancedDetailsColumnsAddress":"","page7PanelLeasedorFinancedDetailsColumns2City":"","page7PanelLeasedorFinancedDetailsColumns2State":"","financialInstitution":"","leasedorFinancedDetailsAddress":"","leasedorFinancedDetailsCity":"","leasedorFinancedDetailsState":"","leasedorFinancedDetailsZipcode":""}]}]'
        );


        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $content = $delegateService->execute($appId, 'SaveDriverUnit', $data);

        $select = "SELECT * FROM `unit` WHERE ox_app_account_id=:accountId";
        $result = $this->persistence->selectQuery($select,[
            "accountId"=>$this->data['accountId']
        ],true);
        $this->assertEquals(count($result),2);

        $select = "SELECT * FROM `driver_unit` as `du` INNER JOIN `unit` as `u` on `du`.unit_id = `u`.id WHERE `u`.vin = :vin";

        $result = $this->persistence->selectQuery($select,[
            "vin"=>"4Y1SL65848Z411438"
        ],true);
        
        $this->assertEquals(count($result),1);


        $data = Array
        (
            "driverDataGrid" => '[{"driverFirstName":"Jingle","driverId":1,"driverMiddleName":"J","driverLastName":"Jack","driverDateofBirth":"2021-05-31T12:00:00+05:30","driverSsn":"XXX8293042","driverLicense":"12355667","doesthedriverhave2YearsofcommercialdrivingexperienceinNorthAmerica":"yes","pleaseindicatetypeofdriver":"coDriver","pleaseselectthepaidbyoption":1099},{"driverFirstName":"John","driverId":2,"driverMiddleName":"D","driverLastName":"Unlup","driverDateofBirth":"2021-05-17T12:00:00+05:30","driverSsn":"XXX8584930","driverLicense":"1235589","doesthedriverhave2YearsofcommercialdrivingexperienceinNorthAmerica":"yes","pleaseindicatetypeofdriver":"coDriver","pleaseselectthepaidbyoption":"w2"}]',
            "unitDataGrid" => '[{"unitMake":"Ford","unitModel":"Mustang","unitVin":"4Y1SL65848Z411438","unitGaragingCity":"City","addresswheretheunitisgaraged":"Addr1","unitGaragingState":"CA","zipCode":"90623","registeredownerfullname":"Admin User","isthisunitleasedorfinanced":"no","doYouWantToAddAdditionalInsured":"yes","doesTheUnitHaveADriver":"yes","unitYear":1997,"additionalInsuredDetailsDataGrid":[{"page7PanelAdditionalInsuredDetailsColumnsAdditionalinsuredname":"","page7PanelAdditionalInsuredDetailsColumnsAddress":"","page7PanelAdditionalInsuredDetailsColumns2City":"","page7PanelAdditionalInsuredDetailsColumns2State":"","additionalInsuredName":"Jimmy","additionalInsuredAddress":"#445","additionalInsuredCity":"Thing","additionalInsuredState":"","additionalInsuredZipCode":"78903"}],"driverSelect":[1,2],"leasedorFinancedDetailsDataGrid":[{"page7PanelLeasedorFinancedDetailsColumnsNameofthefinancialinstitution":"","page7PanelLeasedorFinancedDetailsColumnsAddress":"","page7PanelLeasedorFinancedDetailsColumns2City":"","page7PanelLeasedorFinancedDetailsColumns2State":"","financialInstitution":"","leasedorFinancedDetailsAddress":"","leasedorFinancedDetailsCity":"","leasedorFinancedDetailsState":"","leasedorFinancedDetailsZipcode":""}]}]'
        );

        $delegateService = $this->getApplicationServiceLocator()->get(AppDelegateService::class);
        $delegateService->setPersistence($appId, $this->persistence);
        $content = $delegateService->execute($appId, 'SaveDriverUnit', $data);

        $select = "SELECT * FROM `unit` WHERE ox_app_account_id=:accountId";
        $result = $this->persistence->selectQuery($select,[
            "accountId"=>$this->data['accountId'],
        ],true);
        $this->assertEquals(count($result),1);
        $this->assertEquals($result[0]['make'],"Ford");
        $this->assertEquals($result[0]['vin'],"4Y1SL65848Z411438");
        $unitId = $result[0]['id'];
        $select = "SELECT * FROM `driver_unit` WHERE ox_app_account_id=:accountId AND unit_id =:unitId";
        $result = $this->persistence->selectQuery($select,[
            "accountId"=>$this->data['accountId'],
            "unitId" => $unitId
        ],true);
        $this->assertEquals(count($result),2);
    }
}