<?php
use Oxzion\Test\DelegateTest;
use Oxzion\AppDelegate\AppDelegateService;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use PHPUnit\DbUnit\DataSet\YamlDataSet;

class PadiVerificationTest extends DelegateTest
{
    
    public function setUp() : void
    {
        $this->loadConfig();
        $this->data = array(
            "appName" => 'ox_client_app',
            'UUID' => 8765765,
            'description' => 'FirstAppOfTheClient',
        );
        $migrationFolder = __DIR__  . "/../data/migrations/";
        $this->doMigration($this->data,$migrationFolder);
        $path = __DIR__.'/../../../api/v1/data/delegate/'.$this->data['UUID'];
        if (!is_link($path)) {
            symlink(__DIR__.'/../data/delegate/',$path);
        }
        parent::setUp();               
    }

    public function getDataSet()
    {
        $dataset = new YamlDataSet(__DIR__."/Dataset/PadiData.yml");
        return $dataset;
    }

    public function tearDown() : void
    {
        parent::tearDown();
        $path = __DIR__.'/../../../api/v1/data/delegate/'.$this->data['UUID'];
        if (is_link($path)) {
            unlink($path);
        }
        $query = "DROP DATABASE " . $this->database;
        $statement = $this->getDbAdapter()->query($query);
        $result = $statement->execute();
        
    }

    public function testPadiVerification()
    {
        $orgId = AuthContext::put(AuthConstants::ORG_ID, 3);
        $data =['member_number' => '2141'];
        $appId = $this->data['UUID'];
        $appName = $this->data['appName'];
        $config = $this->getApplicationConfig();
        $delegateService = new AppDelegateService($this->getApplicationConfig(),$this->getDbAdapter());
        $delegateService->setPersistence($appId, $this->persistence);
        $content = $delegateService->execute($appId, 'PadiVerification', $data);
        $this->assertEquals($content[0]['member_number'], $data['member_number']);
    }
}