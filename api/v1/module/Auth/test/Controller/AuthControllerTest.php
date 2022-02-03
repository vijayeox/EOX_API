<?php

namespace Auth;

use Auth\Controller\AuthController;
use Oxzion\Test\ControllerTest;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\ResultSet\ResultSet;

class AuthControllerTest extends ControllerTest
{
    private $dbAdapter;

    public function setUp(): void
    {
        $this->loadConfig();
        $conn=$this->getConnection();
        $conn->getConnection()->query("SET FOREIGN_KEY_CHECKS=0");
        parent::setUp();
        $conn->getConnection()->query("SET FOREIGN_KEY_CHECKS=1");
    }

    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__) . "/../Dataset/token.yml");
        return $dataset;
    }

    protected function getDbAdapter()
    {
        if (!$this->dbAdapter) {
            $this->dbAdapter = $this->getApplicationServiceLocator()->get(AdapterInterface::class);
        }
        
        return $this->dbAdapter;
    }

    private function runQuery($query)
    {
        $adapter = $this->getDbAdapter();
        $statement = $adapter->query($query);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        return $result;
    }

    public function testAuthentication()
    {
        $data = ['username' => $this->adminUser, 'password' => 'Welcome2eox!'];
        $this->dispatch('/auth', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('auth');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(is_null($content['data']['jwt']), false);
        $this->assertEquals(is_null($content['data']['refresh_token']), false);
    }

    public function testAuthenticationWithSpaceAtEnd()
    {
        $data = ['username' => $this->adminUser . '   ', 'password' => 'Welcome2eox!'];
        $this->dispatch('/auth', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('auth');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(is_null($content['data']['jwt']), false);
        $this->assertEquals(is_null($content['data']['refresh_token']), false);
    }

    public function testAuthenticationWithSpaceAtBeginning()
    {
        $data = ['username' => '   ' . $this->adminUser, 'password' => 'Welcome2eox!'];
        $this->dispatch('/auth', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('auth');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(is_null($content['data']['jwt']), false);
        $this->assertEquals(is_null($content['data']['refresh_token']), false);
    }

    public function testAuthenticationInActiveUser()
    {
        $update = "UPDATE ox_user SET status = 'Inactive' where username = '" . $this->adminUser . "'";
        $result = $this->executeUpdate($update);
        $data = ['username' => $this->adminUser, 'password' => 'Welcome2eox!'];
        $this->dispatch('/auth', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('auth');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Authentication Failure - Incorrect data specified');
    }

    public function testAuthenticationInActiveAccount()
    {
        $update = "UPDATE ox_account SET status = 'Inactive' where id = 1";
        $result = $this->executeUpdate($update);
        $data = ['username' => $this->adminUser, 'password' => 'Welcome2eox!'];
        $this->dispatch('/auth', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('auth');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Authentication Failure - Incorrect data specified');
    }

    public function testAuthenticationFail()
    {
        $data = ['username' => 'mehul', 'password' => 'Welcome2eox!'];
        $this->dispatch('/auth', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('auth');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Authentication Failure - Incorrect data specified');
    }

    public function testAuthenticationRefreshTokenExpired()
    {
        $data = ['username' => $this->managerUser, 'password' => 'Welcome2eox!'];
        $this->dispatch('/auth', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('auth');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(is_null($content['data']['jwt']), false);
        $this->assertNotEquals($content['data']['refresh_token'], '6456365665c809d01693770.52543401');
    }

    public function testAuthenticationByApiKey()
    {
        $data = ['apikey' => '0cb6fd4c-40a5-11e9-a30d-1c1b0d785c98', 'accountid' => '1'];
        $this->dispatch('/auth', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('auth');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(is_null($content['data']['jwt']), false);
    }

    public function testAuthenticationFailByApiKey()
    {
        $data = ['apikey' => '0cb6fd4c-40a5-11e9-a30d-1c1b0d785x36', 'accounrtid' => '1'];
        $this->dispatch('/auth', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('auth');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
    }

    public function testRefreshValidUser()
    {
        $dbAdapter = $this->getApplicationServiceLocator()->get(AdapterInterface::class);
        $query = "update ox_user_refresh_token set expiry_date = '" . date('Y-m-d H:i:s', strtotime('+1 day', time())) . "'";
        $statement = $dbAdapter->query($query);
        $result = $statement->execute();
        $data = ['username' => $this->adminUser, 'password' => 'Welcome2eox!'];
        $this->dispatch('/auth', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $rToken = $content['data']['refresh_token'];
        $jToken = $content['data']['jwt'];
        $this->reset();
        $data = ['jwt' => $jToken, 'refresh_token' => $rToken];
        $this->dispatch('/refreshtoken', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('refreshtoken');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals(is_null($content['data']['jwt']), false);
        $this->assertNotEquals($content['data']['jwt'], $jToken);
        $this->assertEquals($content['data']['refresh_token'], $rToken);
    }

    public function testRefreshJwtTokenExpired()
    {
        $data = ['username' => $this->adminUser, 'password' => 'Welcome2eox!'];
        $this->dispatch('/auth', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $rToken = $content['data']['refresh_token'];
        $jToken = $content['data']['jwt'];
        $this->reset();
        $data = ['jwt' => $jToken, 'refresh_token' => $rToken];
        $this->dispatch('/refreshtoken', 'POST', $data);
        $content = $this->getResponse()->getContent();
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('refreshtoken');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $responseContent = (array) json_decode($content, true);
        $this->assertEquals($responseContent['status'], 'success');
        $this->assertNotEquals($responseContent['data']['jwt'], $jToken);
        $this->assertEquals($responseContent['data']['refresh_token'], $rToken);
    }

    public function testRefreshFailRefreshTokenExpired()
    {
        $jwtToken = $this->getJwtToken($this->adminUser);
        $data = ['jwt' => $jwtToken, 'refresh_token' => '13273925815c7e2c7930c794.82022621'];
        $this->dispatch('/refreshtoken', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('refreshtoken');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Refresh Token Expired');
    }

    public function testRefreshFailInvalidToken()
    {
        $data = ['jwt' => 'eyiJKV1QUzUxMiJ9.eyJpYXQiOjE1NTE3NzI3OTMsImp0aSI6IjRKZjlqT0dkNjVkNlhvQjBBRUhzb0VWd0VBOEZibkFGbjkyT3kzV09sXC9ZPSIsIm5iZiI6MTU1MTc3Mjc5MywiZXhwIjoxNTUxNzcyODIzLCJkYXRhIjp7InVzZXJuYW1lIjoicmFrc2hpdGgiLCJvcmdJZCI6IjEifX0.rYJg5Jyq2_KdXJZjuc4moYY3Zfr1NWXrWN4cemTwNqv-t0NfXIRw7OShsCQRYbCiC6K_5lSeCwDSxdyZFVSLAA', 'refresh_token' => '13273925815c7e2c7930c794.82022621'];
        $this->dispatch('/refreshtoken', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('refreshtoken');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Invalid JWT Token');
    }

    public function testRefreshNoJwtToken()
    {
        $data = ['refresh_token' => '13273925815c7e2c7930c794.82022621'];
        $this->dispatch('/refreshtoken', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('refreshtoken');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'JWT Token Not Found');
    }

    public function testValidateToken()
    {
        $data = ['username' => $this->adminUser, 'password' => 'Welcome2eox!'];
        $this->dispatch('/auth', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $data = ['jwt' => $content['data']['jwt']];
        $this->reset();
        $this->dispatch('/validatetoken', 'POST', $data);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('validatetoken');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['message'], 'Token Valid');
    }

    public function testValidateTokenFail()
    {
        $data = ['jwt' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzUxMiJ9.eyJpYXQiOjE2MDc0OTMwNDEsImp0aSI6IkxsYUg0SDc0bjRRTzROUkhXbTl0aEl1dzd3Q01LaG0wRkdHdjZoc0JaUmM9IiwibmJmIjoxNjA3NDkzMDQxLCJleHAiOjE2MDc1NjUwNDEsImRhdGEiOnsidXNlcm5hbWUiOiJhZG1pbnRlc3QiLCJhY2NvdW50SWQiOiIxIn19.Ib-Nde1nvthuUeEIWsMlw0ZJHc6vgeWFYWwmnyausxqqoNLja7GRZjYxMac5ox29ktn7FYCS8QEOM3t9nT0TKQ'];
        $this->dispatch('/validatetoken', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('validatetoken');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Token Expired');
    }

    public function testValidateTokenInvalid()
    {
        $data = ['jwt' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzUxMiJ9.eyJpYXQiOjE1NTE4NjUwNjksImp0aSI6IlB4ZGthaUc1eE1qYUE2MW1lSkFOZGpSWWZpVVFrdVRoN1hIemRqUWs4VkE9IiwibmJmIjoxNTUxODY1MDY5LCJleHAiOjE1NTE5MzcwNjksImRhdGEiOnsidXNlcm5hbWUiOiJiaGFyYXRnIiwiSWQiOiIxIn19.TaAN3LvL2hbMfpPgQVm4fhhBCsT_sEJ5_jyp3id0qvI6i_Pmra4dL-aYgsoDw-9bqdDr2WUfh-Xu9vFnfV-eoA'];
        $this->dispatch('/validatetoken', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('validatetoken');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Invalid JWT Token');
    }

    public function testValidateNoToken()
    {
        $data = [];
        $this->dispatch('/validatetoken', 'POST', $data);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('validatetoken');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'JWT Token Not Found');
    }

    public function testValidProfileUsername()
    {
        $data = ['username' => $this->adminUser];
        $this->dispatch('/userprof', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('userprof');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['username'], 'Admin Test');
        $this->assertContains('user/profile/', $content['data']['profileUrl']);
    }

    public function testValidProfileEmail()
    {
        $data = ['username' => 'admin1@eoxvantage.in'];
        $this->dispatch('/userprof', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('userprof');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertEquals($content['data']['username'], 'Admin Test');
        $this->assertContains('user/profile/', $content['data']['profileUrl']);
    }

    public function testInvalidProfileEmail()
    {
        $data = ['username' => 'bharat@vma.com'];
        $this->dispatch('/userprof', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('userprof');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Invalid User');
    }

    public function testInvalidProfileUsername()
    {
        $data = ['username' => 'bhara'];
        $this->dispatch('/userprof', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('userprof');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Invalid User');
    }

    public function testRegisterBusinessAccount()
    {
        $data = '{"data":{"appId":"debf3d35-a0ee-49d3-a8ac-8e480be9dac7","firstname":"Bharat","lastname":"Gogineni","address1":"66,1st cross,2nd main,H.A.L 3r","address2":"PES University Campus,","type":"BUSINESS","business_role":"Policy Holder","name" : "Big Org", "city":"Bangalore","zip":"560075","state":"AR","country":"India","sameasmailingaddress":false,"address3":"Bangalore","address4":"PES University Campus,","phonenumber":"(973) 959-1462","commands" : "[\"register_account\",\"store_cache_data\",\"sign_in\"]","mobilephone":"(973) 959-1462","fax":"","email":"support@eoxvantage.com","submit":true},"metadata":{"timezone":"Asia/Calcutta","offset":330,"referrer":"","browserName":"Netscape","userAgent":"Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.142 Safari/537.36","pathName":"/static/1/","onLine":true},"state":"submitted","saved":false}';
        $data = json_decode($data, true);
        $this->dispatch('/register', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('register');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertArrayHasKey('jwt', $content['data']);
        $this->assertArrayHasKey('refresh_token', $content['data']);
        $this->assertArrayHasKey('username', $content['data']);
        $this->performAssertions($data);
    }

    public function testRegisterIndividualAccount()
    {
        $data = '{"data":{"appId":"debf3d35-a0ee-49d3-a8ac-8e480be9dac7","firstname":"Bharat","lastname":"Gogineni","address1":"66,1st cross,2nd main,H.A.L 3r","address2":"PES University Campus,","type":"INDIVIDUAL","business_role":"Policy Holder","identifier_field":"padi","padi":"12345", "city":"Bangalore","zip":"560075","state":"AR","country":"India","sameasmailingaddress":false,"address3":"Bangalore","address4":"PES University Campus,","phonenumber":"(973) 959-1462","commands" : "[\"register_account\",\"store_cache_data\",\"sign_in\"]","mobilephone":"(973) 959-1462","fax":"","email":"support@eoxvantage.com","submit":true},"metadata":{"timezone":"Asia/Calcutta","offset":330,"referrer":"","browserName":"Netscape","userAgent":"Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.142 Safari/537.36","pathName":"/static/1/","onLine":true},"state":"submitted","saved":false}';
        $data = json_decode($data, true);
        $this->dispatch('/register', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('register');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertArrayHasKey('jwt', $content['data']);
        $this->assertArrayHasKey('refresh_token', $content['data']);
        $this->assertArrayHasKey('username', $content['data']);
        $this->performAssertions($data);
    }
    private function performAssertions($data)
    {
        $sqlQuery = 'SELECT u.id, up.firstname, up.lastname, up.email, u.account_id FROM ox_user u inner join ox_person up on up.id = u.person_id order by u.id DESC LIMIT 1';
        $newQueryResult = $this->runQuery($sqlQuery);
        $accountId = $newQueryResult[0]['account_id'];
        $sqlQuery = 'SELECT * FROM ox_account where id = '.$accountId;
        $acctResult = $this->runQuery($sqlQuery);
        $sqlQuery = 'SELECT br.* FROM ox_account_business_role obr inner join ox_business_role br on obr.business_role_id = br.id where obr.account_id = '.$accountId;
        $bussRoleResult = $this->runQuery($sqlQuery);
        $sqlQuery = 'SELECT * FROM ox_role where account_id = '.$accountId;
        $roleResult = $this->runQuery($sqlQuery);
        $sqlQuery = "SELECT ur.* FROM ox_user_role ur 
                        INNER JOIN ox_account_user au on au.id = ur.account_user_id
                        INNER JOIN ox_user u on u.id = au.user_id
                    where u.id = ".$newQueryResult[0]['id']." AND role_id = ".$roleResult[0]['id'];
        $urResult = $this->runQuery($sqlQuery);

        $this->assertEquals($data['data']['firstname'], $newQueryResult[0]['firstname']);
        $this->assertEquals($data['data']['lastname'], $newQueryResult[0]['lastname']);
        $this->assertEquals($data['data']['email'], $newQueryResult[0]['email']);
        if ($data['data']['type'] == 'INDIVIDUAL') {
            $this->assertEquals($data['data']['firstname']." ".$data['data']['lastname'], $acctResult[0]['name']);
        } else {
            $this->assertEquals($data['data']['name'], $acctResult[0]['name']);
        }
        $this->assertEquals($data['data']['type'], $acctResult[0]['type']);
        $this->assertEquals($newQueryResult[0]['id'], $acctResult[0]['contactid']);
        if (isset($data['data']['identifier_field'])) {
            $sqlQuery = "SELECT * FROM ox_wf_user_identifier where identifier_name = '".$data['data']['identifier_field']."' AND identifier = '".$data['data'][$data['data']['identifier_field']]."'";
            $identifierResult = $this->runQuery($sqlQuery);
            $this->assertEquals(1, count($identifierResult));
            $this->assertEquals(100, $identifierResult[0]['app_id']);
            $this->assertEquals($acctResult[0]['id'], $identifierResult[0]['account_id']);
            $this->assertEquals($newQueryResult[0]['id'], $identifierResult[0]['user_id']);
        }
        if (isset($data['data']['businessRole'])) {
            $this->assertEquals($data['data']['businessRole'], $bussRoleResult[0]['name']);
            $this->assertEquals("Admin", $roleResult[0]['name']);
            $this->assertEquals(1, count($urResult));
        } else {
            $this->assertEquals(3, count($roleResult));
            $this->assertEquals(1, count($urResult));
        }
        $sqlQuery = "SELECT ar.* from ox_app_registry ar inner join ox_app a on a.id = ar.app_id 
                        where a.uuid = '".$data['data']['appId']."' AND account_id = $accountId";

        $result = $this->runQuery($sqlQuery);
        $this->assertEquals(1, count($result));
        $this->assertEquals(date('Y-m-d'), date_create($result[0]['date_created'])->format('Y-m-d'));
    }
    public function testRegisterWithoutType()
    {
        $data = '{"data":{"app_id":"debf3d35-a0ee-49d3-a8ac-8e480be9dac7","firstname":"Bharat","lastname":"Gogineni","address1":"66,1st cross,2nd main,H.A.L 3r","address2":"PES University Campus,","businessRole":"Policy Holder", "city":"Bangalore","zip":"560075","state":"AR","country":"India","sameasmailingaddress":false,"address3":"Bangalore","address4":"PES University Campus,","phonenumber":"(973) 959-1462","commands" : "[\"register_account\",\"store_cache_data\",\"sign_in\"]","mobilephone":"(973) 959-1462","fax":"","email":"support@eoxvantage.com","submit":true},"metadata":{"timezone":"Asia/Calcutta","offset":330,"referrer":"","browserName":"Netscape","userAgent":"Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.142 Safari/537.36","pathName":"/static/1/","onLine":true},"state":"submitted","saved":false}';
        $this->dispatch('/register', 'POST', json_decode($data, true));
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('register');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals('Business Type not specified', $content['message']);
    }

    public function testRegisterWithoutBusinessRole()
    {
        $data = '{"data":{"appId":"debf3d35-a0ee-49d3-a8ac-8e480be9dac7","firstname":"Bharat","lastname":"Gogineni","address1":"66,1st cross,2nd main,H.A.L 3r","address2":"PES University Campus,","type":"INDIVIDUAL","city":"Bangalore","zip":"560075","state":"AR","country":"India","sameasmailingaddress":false,"address3":"Bangalore","address4":"PES University Campus,","phonenumber":"(973) 959-1462","commands" : "[\"register_account\",\"store_cache_data\",\"sign_in\"]","mobilephone":"(973) 959-1462","fax":"","email":"support@eoxvantage.com","submit":true},"metadata":{"timezone":"Asia/Calcutta","offset":330,"referrer":"","browserName":"Netscape","userAgent":"Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.142 Safari/537.36","pathName":"/static/1/","onLine":true},"state":"submitted","saved":false}';
        $data = json_decode($data, true);
        $this->dispatch('/register', 'POST', $data);
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('register');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertArrayHasKey('jwt', $content['data']);
        $this->assertArrayHasKey('refresh_token', $content['data']);
        $this->assertArrayHasKey('username', $content['data']);
        $this->performAssertions($data);
    }

    public function testRegisterWithoutCredentialsCommand()
    {
        $data = '{"data":{"appId":"debf3d35-a0ee-49d3-a8ac-8e480be9dac7","firstname":"Bharat","lastname":"Gogineni","address1":"66,1st cross,2nd main,H.A.L 3r","address2":"PES University Campus,","type":"INDIVIDUAL","business_role":"Policy Holder","city":"Bangalore","zip":"560075","commands":"[\"register_account\",\"store_cache_data\"]","state":"AR","country":"India","sameasmailingaddress":false,"address3":"Bangalore","address4":"PES University Campus,","phonenumber":"(973) 959-1462","mobilephone":"(973) 959-1462","fax":"","email":"support@eoxvantage.com","submit":true},"metadata":{"timezone":"Asia/Calcutta","offset":330,"referrer":"","browserName":"Netscape","userAgent":"Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.142 Safari/537.36","pathName":"/static/1/","onLine":true},"state":"submitted","saved":false}';
        $this->dispatch('/register', 'POST', json_decode($data, true));
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('register');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertArrayHasKey('cache_data', $content['data']);
    }
    public function testRegisterWithoutCacheCommand()
    {
        $data = '{"data":{"appId":"debf3d35-a0ee-49d3-a8ac-8e480be9dac7", "identifier_field": "padi", "padi": "12345", "firstname":"Bharat","lastname":"Gogineni","address1":"66,1st cross,2nd main,H.A.L 3r","address2":"PES University Campus,","city":"Bangalore","zip":"560075","type":"INDIVIDUAL","business_role":"Policy Holder","commands":"[\"register_account\"]","state":"AR","country":"India","sameasmailingaddress":false,"address3":"Bangalore","address4":"PES University Campus,","phonenumber":"(973) 959-1462","mobilephone":"(973) 959-1462","fax":"","email":"support@eoxvantage.com","submit":true},"metadata":{"timezone":"Asia/Calcutta","offset":330,"referrer":"","browserName":"Netscape","userAgent":"Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.142 Safari/537.36","pathName":"/static/1/","onLine":true},"state":"submitted","saved":false}';
        $this->dispatch('/register', 'POST', json_decode($data, true));
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('register');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertArrayNotHasKey('cache_data', $content['data']);
    }
    public function testRegisterWithOnlyCacheCommand()
    {
        $data = '{"data":{"username":"admintest","commands":"[\"store_cache_data\"]"},"username":"admintest","dummdata":"dummy"}';
        $this->dispatch('/register', 'POST', json_decode($data, true));
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('register');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'success');
        $this->assertArrayHasKey('cache_data', $content['data']);
    }
    public function testRegisterWithOnlyCacheCommandNoUser()
    {
        $data = '{"data":{"username":"bhatgtest","commands":"[\"store_cache_data\"]"},"username":"goku","dummdata":"dummy"}';
        $this->dispatch('/register', 'POST', json_decode($data, true));
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // as specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('register');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'error');
        $this->assertArrayNotHasKey('data', $content);
    }
    
    public function testRegisterAccountUserExists()
    {
        $data = '{"data":{"app_id":"debf3d35-a0ee-49d3-a8ac-8e480be9dac7", "identifier_field": "padi", "padi": "123456", "firstname":"Bharat","lastname":"Gogineni","address1":"66,1st cross,2nd main,H.A.L 3r","address2":"PES University Campus,","city":"Bangalore","zip":"560075","type":"INDIVIDUAL","business_role":"Policy Holder","commands":"[\"register_account\",\"store_cache_data\",\"sign_in\"]","state":"AR","country":"India","sameasmailingaddress":false,"address3":"Bangalore","address4":"PES University Campus,","phonenumber":"(973) 959-1462","mobilephone":"(973) 959-1462","fax":"","email":"bharatg@myvamla.com","submit":true},"metadata":{"timezone":"Asia/Calcutta","offset":330,"referrer":"","browserName":"Netscape","userAgent":"Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.142 Safari/537.36","pathName":"/static/1/","onLine":true},"state":"submitted","saved":false}';
        $this->dispatch('/register', 'POST', json_decode($data, true));
        $content = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertResponseStatusCode(404);
        $this->assertModuleName('auth');
        $this->assertControllerName(AuthController::class); // As specified in router's controller name alias
        $this->assertControllerClass('AuthController');
        $this->assertMatchedRouteName('register');
        $this->assertResponseHeaderContains('content-type', 'application/json; charset=utf-8');
        $this->assertEquals($content['status'], 'error');
        $this->assertEquals($content['message'], 'Username/Email Used');
    }
}
