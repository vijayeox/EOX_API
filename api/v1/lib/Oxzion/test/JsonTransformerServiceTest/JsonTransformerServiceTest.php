<?php
namespace Oxzion\TRansformer;

use Oxzion\Test\ServiceTest;
use Oxzion\Transformer\JsonTransformerService;
use Exception;
use PHPUnit\DbUnit\DataSet\YamlDataSet;

class JsonTransformerServiceTest extends ServiceTest
{
    public function setUp() : void
    {
        $this->loadConfig();
        $this->data = array(
            "appName" => 'ox_client_app',
            'UUID' => 8765765,
            'description' => 'FirstAppOfTheClient',
        );

        $path = __DIR__.'/../../../../data/transformer/'.$this->data['UUID'];
        if (!is_link($path)) {
            symlink(__DIR__.'/../JsonTransformerServiceTest/transformer/', $path);
        }

        parent::setUp();
    }

    public function tearDown() : void
    {
        parent::tearDown();
        $path = __DIR__.'/../../../../data/transformer/'.$this->data['UUID'];
        if (is_link($path)) {
            unlink($path);
        }
    }
    
    public function testJsonTransform()
    {
        $dataSet = ['firstname' => 'Tommy', 'initial' => 'S', 'lastname' => 'Thomsan','address1' => '','City' => '', 'Dateofbirth' => '2022-11-11','home_country_code' => '65','maritalStatus' => 'single'];
        $appId = $this->data['UUID'];
        $jsonTransformerService = $this->getApplicationServiceLocator()->get(JsonTransformerService::class);
        $content = $jsonTransformerService->transform($appId, 'transformer1', $dataSet);
        $this->assertEquals($dataSet['firstname'], $content['FirstName']);
        $this->assertEquals($dataSet['initial'], $content['MiddleName']);
        $this->assertEquals($dataSet['lastname'], $content['LastName']);
        $this->assertEmpty($content['Address']);
        $this->assertNotNull($content['Birth Date']);
        $this->assertNotEquals($dataSet['home_country_code'], $content['homeCountrycode']);
        $this->assertEquals(77, $content['homeCountrycode']);
        $this->assertTrue($content['mariatal']);
        $this->assertNotNull($content['Todaysdate']);
    }
}
