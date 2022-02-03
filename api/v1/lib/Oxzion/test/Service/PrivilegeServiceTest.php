<?php
namespace Oxzion\Service;

use Oxzion\Test\AbstractServiceTest;
use Oxzion\Transaction\TransactionManager;
use Zend\Db\Adapter\Adapter;
use Oxzion\Service\PrivilegeService;
use PHPUnit\DbUnit\DataSet\SymfonyYamlParser;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Adapter\Driver\Pdo\Pdo;

class PrivilegeServiceTest extends AbstractServiceTest
{
    public function setUp() : void
    {
        $this->loadConfig();
        $conn=$this->getConnection();
        $conn->getConnection()->query("SET FOREIGN_KEY_CHECKS=0");
        parent::setUp();
        $this->privilegeService = $this->getApplicationServiceLocator()->get(\Oxzion\Service\PrivilegeService::class);
        $conn->getConnection()->query("SET FOREIGN_KEY_CHECKS=1");
        
    }

    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__)."/Dataset/Privilege.yml");
        return $dataset;
    }

    public function testSaveAppPrivilegesAddExtraPrivilegesInDatabaseFromYml()
    {
        $data = array(['name' => 'MANAGE_POLICY_APPROVAL', 'permission' => 3 ], ['name' => 'MANAGE_MY_POLICY', 'permission' => 3 ], ['name' => 'MANAGE_P', 'permission' => 3 ]);
        $config = $this->getApplicationConfig();
        $conn=$this->getConnection();
        $conn->getConnection()->query("SET FOREIGN_KEY_CHECKS=0");
        $content = $this->privilegeService->saveAppPrivileges(240, $data);
        $conn->getConnection()->query("SET FOREIGN_KEY_CHECKS=1");
        $sqlQuery = "SELECT count(name) as count FROM ox_privilege WHERE app_id = 240";
        $adapter = $this->getDbAdapter();
        $adapter->getDriver()->getConnection()->setResource(static::$pdo);
        $statement = $adapter->query($sqlQuery);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        $this->assertEquals($result[0]['count'], 3);
    }

    public function testSaveAppPrivilegesDeleteExtraPrivilegesInDatabaseNotInYml()
    {
        $sqlQuery = "SELECT count(name) as count FROM ox_privilege WHERE app_id = 240";
        $adapter = $this->getDbAdapter();
        $adapter->getDriver()->getConnection()->setResource(static::$pdo);
        $statement = $adapter->query($sqlQuery);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        $this->assertEquals($result[0]['count'], 4);
        $data = array(['name' => 'MANAGE_POLICY_APPROVAL', 'permission' => 3 ], ['name' => 'MANAGE_MY_POLICY', 'permission' => 3 ], ['name' => 'MANAGE_P', 'permission' => 3 ]);
        $config = $this->getApplicationConfig();
        $conn=$this->getConnection();
        $conn->getConnection()->query("SET FOREIGN_KEY_CHECKS=0");
        $content = $this->privilegeService->saveAppPrivileges(240, $data);
        $conn->getConnection()->query("SET FOREIGN_KEY_CHECKS=1");
        $sqlQuery = "SELECT count(name) as count FROM ox_privilege WHERE app_id = 240";
        $adapter = $this->getDbAdapter();
        $adapter->getDriver()->getConnection()->setResource(static::$pdo);
        $statement = $adapter->query($sqlQuery);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result1 = $resultSet->initialize($result)->toArray();
        $this->assertEquals($result1[0]['count'], 3);
    }
}
