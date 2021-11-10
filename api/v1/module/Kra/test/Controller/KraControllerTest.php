<?php
namespace Kra;

use Kra\Controller\KraController;
use Mockery;
use Oxzion\Service\KraService;
use Oxzion\Test\ControllerTest;
use PHPUnit\DbUnit\DataSet\YamlDataSet;

class KraControllerTest extends ControllerTest
{
    public function setUp(): void
    {
        $this->loadConfig();
        parent::setUp();
    }

    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__) . "/../Dataset/Kra.yml");
        return $dataset;
    }

    protected function setDefaultAsserts()
    {
        $this->assertModuleName('Kra');
        $this->assertControllerName(KraController::class); // as specified in router's controller name alias
        $this->assertControllerClass('KraController');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
    }

    public function testGetKras()
    {
        $this->initAuthToken($this->adminUser);
        $kraId = '153f3e9e-eb07-4ca4-be78-34f715bd50db';
        $this->dispatch("/kra/$kraId", 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('kras');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($kraId, $content['data']['uuid']);
        $this->assertEquals('Test Kra Once Again', $content['data']['name']);
        $userId = '4fd99e8e-758f-11e9-b2d5-68ecc57cde45';
        $accountId = '53012471-2863-4949-afb1-e69b0891c98a';
        $this->assertEquals($userId, $content['data']['userId']);
        $this->assertEquals($accountId, $content['data']['accountId']);
    }

    public function testGetKrasWithAccountId()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/kra/153f3e9e-eb07-4ca4-be78-34f715bd50db', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('kras');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(5, $this->getConnection()->getRowCount('ox_kra'));
    }

    public function testGetKrasWithInValidAccountId()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/kra/153f3e9e-eb07-4ca4-be78-34f715bd50db', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('kras');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(5, $this->getConnection()->getRowCount('ox_kra'));
    }

    public function testGetKrasNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/kra/10000', 'GET');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('kras');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'], array());
    }

    public function testGetKrasListWithAccountID()
    {
        $this->initAuthToken($this->managerUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/kra', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('kras');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(3, count($content['data']));
        $kras = ['Test Kra 4', 'Test Kra 5', 'Test Kra Once Again'];
        foreach ($kras as $key => $value) {
            $this->assertEquals($value, $content['data'][$key]['name']);
        }
    }

    public function testGetKrasforByUser()
    {
        $this->initAuthToken($this->managerUser);
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/kra', 'GET');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(401);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('kras');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You do not have permissions to get the kras list');
    }

    public function testGetKrasForUser()
    {
        $this->initAuthToken($this->managerUser);
        $this->dispatch('/kra', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Kra');
        $this->assertControllerName(KraController::class); // as specified in router's controller name alias
        $this->assertControllerClass('KraController');
        $this->assertMatchedRouteName('kras');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(5, $this->getConnection()->getRowCount('ox_kra'));
    }

    public function testGetKrasForEmployee()
    {
        $this->initAuthToken($this->employeeUser);
        $this->dispatch('/kra', 'GET');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('Kra');
        $this->assertControllerName(KraController::class); // as specified in router's controller name alias
        $this->assertControllerClass('KraController');
        $this->assertMatchedRouteName('kras');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(5, $this->getConnection()->getRowCount('ox_kra'));
    }

    // Testing to see if the Create Kra function is working as intended if all the value passed are correct.
    public function testCreate()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Kras 22', 'accountId' => '53012471-2863-4949-afb1-e69b0891c98a', 'userId' => "4fd99e8e-758f-11e9-b2d5-68ecc57cde45", 'status' => 'Active','queryId'=> '8f1d2819-c5ff-4426-bc40-f7a20704a738'];
        $this->assertEquals(5, $this->getConnection()->getRowCount('ox_kra'));
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/kra', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('kras');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals(6, $this->getConnection()->getRowCount('ox_kra'));
    }

    public function testCreateWithExistingKra()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Test Kra Once Again', 'account_id' => 1, 'userId' => "4fd99e8e-758f-11e9-b2d5-68ecc57cde45",'query_id'=>11];
        $this->assertEquals(5, $this->getConnection()->getRowCount('ox_kra'));
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/kra', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(406);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('kras');
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Kra already exists');
    }

    public function testCreateWithExistingKraInactive()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Test Kra', 'account_id' => 1, 'userId' => "4fd99e8e-758f-11e9-b2d5-68ecc57cde45",'query_id'=>11];
        $this->assertEquals(5, $this->getConnection()->getRowCount('ox_kra'));
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/kra', 'POST', $data);
        $this->assertResponseStatusCode(406);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('kras');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Kra already exists would you like to reactivate?');
    }

    public function testCreateWithExistingKraInactiveWithReactivateFlag()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Test Kra', 'account_id' => 1, 'userId' => "4fd99e8e-758f-11e9-b2d5-68ecc57cde45", 'reactivate' => 1,'query_id'=>11];
        $this->assertEquals(5, $this->getConnection()->getRowCount('ox_kra'));
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/kra', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('kras');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['status'], 'Active');
    }

    public function testCreateByAdminWithDifferentAccountId()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Kras 22', 'userId' => "4fd99e8e-758f-11e9-b2d5-68ecc57cde45", 'status' => 'Active','queryId'=> '8f1d2819-c5ff-4426-bc40-f7a20704a738'];
        $this->assertEquals(5, $this->getConnection()->getRowCount('ox_kra'));
        $this->setJsonContent(json_encode($data));
        $accountId = 'b0971de7-0387-48ea-8f29-5d3704d96a46';
        $this->dispatch("/account/$accountId/kra", 'POST', $data);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('kras');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals(6, $this->getConnection()->getRowCount('ox_kra'));
    }

    public function testCreateNewKra()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Kras 22', 'userId' => "4fd99e8e-758f-11e9-b2d5-68ecc57cde45",'queryId'=> '8f1d2819-c5ff-4426-bc40-f7a20704a738'];
        $this->assertEquals(5, $this->getConnection()->getRowCount('ox_kra'));
        $this->setJsonContent(json_encode($data));
        $accountId = '53012471-2863-4949-afb1-e69b0891c98a';
        $this->dispatch("/account/$accountId/kra", 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('kras');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['name'], $data['name']);
        $this->assertEquals(6, $this->getConnection()->getRowCount('ox_kra'));
    }

    public function testCreateWithoutRequiredField()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Kras 22', 'status' => 'Active'];
        $this->assertEquals(5, $this->getConnection()->getRowCount('ox_kra'));
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/kra', 'POST', $data);
        $this->assertResponseStatusCode(406);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('kras');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Validation error(s).');
    }

    public function testCreateByEmployee()
    {
        $this->initAuthToken($this->employeeUser);
        $data = ['name' => 'Kras 22', 'account_id' => 'b0971de7-0387-48ea-8f29-5d3704d96a46', 'userId' => "4fd99e8e-758f-11e9-b2d5-68ecc57cde45"];
        $this->assertEquals(5, $this->getConnection()->getRowCount('ox_kra'));
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/kra', 'POST', $data);
        $this->assertResponseStatusCode(401);
        $this->assertModuleName('Kra');
        $this->assertControllerName(KraController::class); // as specified in router's controller name alias
        $this->assertControllerClass('KraController');
        $this->assertMatchedRouteName('kras');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You have no Access to this API');
    }

    public function testCreateByInvalidTeam()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['name' => 'Kras 22', 'account_id' => 'b0971de7-0387-48ea-8f29-5d3704d96a46', 'userId' => "4fd99e8e-758f-11e9-b2d5-68ecc57cde45", 'teamId' => '10766504-bf40-4824-a16a-fbc7df45b944'];
        $this->assertEquals(5, $this->getConnection()->getRowCount('ox_kra'));
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/kra', 'POST', $data);
        $this->assertResponseStatusCode(406);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('kras');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Validation error(s).');
    }

    public function testUpdate()
    {
        $data = ['name' => 'Test Create Kra', 'userId' => "4fd9ce37-758f-11e9-b2d5-68ecc57cde45",'query_id'=>11];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $kraId = '2db1c5a3-8a82-4d5b-b60a-c648cf1e27de';
        $this->dispatch("/kra/$kraId", 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('kras');
        $this->assertEquals($content['status'], 'success');
    }

    public function testUpdateWithAccountID()
    {
        $data = ['name' => 'Test Create Kra', 'userId' => "4fd9ce37-758f-11e9-b2d5-68ecc57cde45"];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $kraId = '2db1c5a3-8a82-4d5b-b60a-c648cf1e27de';
        $accountId = '53012471-2863-4949-afb1-e69b0891c98a';
        $this->dispatch("/account/$accountId/kra/$kraId", 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(201);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('kras');
        $this->assertEquals($content['status'], 'success');
    }

    public function testUpdateWithInvalidAccountAccountID()
    {
        $data = ['name' => 'Test Create Kra', 'userId' => "4fd9ce37-758f-11e9-b2d5-68ecc57cde45"];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/kra/2db1c5a3-8a82-4d5b-b60a-c648cf1e27de', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('kras');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Kra does not belong to the account');
    }

    public function testUpdateWithInvalidKraID()
    {
        $data = ['name' => 'Test Create Kra', 'userId' => "4fd9ce37-758f-11e9-b2d5-68ecc57cde45"];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/kra/2db82-4d5b-b60a-c648cf1e27de', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('kras');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Updating non existent Kra');
    }

    public function testUpdateNotFound()
    {
        $data = ['name' => 'Test', 'userId' => "4fd99e8e-758f-11e9-b2d5-68ecc57cde45"];
        $this->initAuthToken($this->adminUser);
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/kra/10000', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('kras');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testDelete()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/kra/153f3e9e-eb07-4ca4-be78-34f715bd50db', 'DELETE');
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('kras');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
    }

    public function testDeleteWithInvalidAccount()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/kra/153f3e9e-eb07-4ca4-be78-34f715bd50sd', 'DELETE');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('kras');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Kra does not belong to the account');
    }

    public function testDeleteByEmployee()
    {
        $this->initAuthToken($this->employeeUser);
        $this->dispatch('/account/b0971de7-0387-48ea-8f29-5d3704d96a46/kra/2db1c5a3-8a82-4d5b-b60a-c648cf1e27de', 'DELETE');
        $this->assertResponseStatusCode(401);
        $this->assertModuleName('Kra');
        $this->assertControllerName(KraController::class); // as specified in router's controller name alias
        $this->assertControllerClass('KraController');
        $this->assertMatchedRouteName('kras');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'You have no Access to this API');
    }

    public function testDeleteNotFound()
    {
        $this->initAuthToken($this->adminUser);
        $this->dispatch('/kra/10000', 'DELETE');
        $this->assertResponseStatusCode(404);
        $this->setDefaultAsserts();
        $this->assertMatchedRouteName('kras');
        $content = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testGetExcludedKrasList()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['exclude' => array('2db1c5a3-8a82-4d5b-b60a-c648cf1e27de', '153f3e9e-eb07-4ca4-be78-34f715bd50db'), 'filter' => json_encode(array('0' => array('filter' => array('logic' => 'and', 'filters' => array(['field' => 'name', 'operator' => 'startswith', 'value' => 'Test'])), 'sort' => array(['field' => 'name', 'dir' => 'asc']), 'skip' => 0, 'take' => 2)))];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/kras/list', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('krasList');
        $this->assertEquals($content['status'], 'success');
        // print_r($content);exit;
        $this->assertEquals(2, count($content['data']));
        $this->assertEquals($content['data'][0]['name'], 'Test Kra 4');
    }

    public function testGetExcludedKrasListWithExcludedKraFilter()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['exclude' => array('4fd9f04d-758f-11e9-b2d5-68ecc57cde45', '768d1fb9-de9c-46c3-8d5c-23e0e484ce2e'), 'filter' => json_encode(array('0' => array('sort' => array(['field' => 'name', 'dir' => 'dsc']), 'skip' => 0, 'take' => 2)))];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/kras/list', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('krasList');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['total'], 3);
    }

    public function testGetExcludedKrasListWithAccountId()
    {
        $this->initAuthToken($this->adminUser);
        $data = ['exclude' => array('2db1c5a3-8a82-4d5b-b60a-c648cf1e27de'), 'filter' => json_encode(array('0' => array('sort' => array(['field' => 'name', 'dir' => 'dsc']), 'skip' => 0, 'take' => 20)))];
        $this->setJsonContent(json_encode($data));
        $this->dispatch('/account/53012471-2863-4949-afb1-e69b0891c98a/kras/list', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->setDefaultAsserts('krasList');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data'][0]['name'], 'Test Kra Once Again');
    }
}
