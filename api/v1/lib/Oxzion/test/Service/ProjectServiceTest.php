<?php
namespace Oxzion\Service;

use Oxzion\Test\AbstractServiceTest;
use Project\Service\ProjectService;
use Zend\Db\Adapter\Adapter;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use Zend\Db\ResultSet\ResultSet;

class ProjectServiceTest extends AbstractServiceTest
{
    private $adapter = NULL;

    /**
     * Method executes before every test, Initialise variables and open file connection
     */
    protected function setUp(): void
    {
        $this->loadConfig();
        parent::setUp();
        $this->projectService = $this->getApplicationServiceLocator()->get(\Project\Service\ProjectService::class);
        $this->adapter = $this->getDbAdapter();
        $this->adapter->getDriver()->getConnection()->setResource(static::$pdo);
    }

    /**
     *  Prepare dataset for project to compare the actual contents of a database against the expected contents.
     */
    public function getDataSet()
    {
        $dataset = new YamlDataSet(dirname(__FILE__)."/Dataset/UpdateProject.yml");
        return $dataset;
    }

    /**
     * Initailize and Set the data source for the result set
     */
    private function runQuery($query)
    {
        $statement = $this->adapter->query($query);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $result = $resultSet->initialize($result)->toArray();
        return $result;
    }

    /**
     * Test updating of project for empty Parent Project
     */
    public function testUpdateProjectForEmptyParent()
    {
        $id = '0bd9d096-b8a9-11eb-8529-0242ac130003';
        $data = [
            'name' => 'Project 2', 
            'description' => 'test project 2', 
            'managerId' => '4fd9ce37-758f-11e9-b2d5-68ecc57cde45',
            'parentId' => NULL
        ];
        $this->projectService->updateProject($id, $data);
        $sqlQuery = "SELECT * FROM ox_project WHERE uuid = '".$id."'";
        $result = $this->runQuery($sqlQuery);
        $this->assertEquals(1, count($result));
        $this->assertEquals(2, $result[0]['manager_id']);
        $this->assertEquals(NULL, $result[0]['parent_id']);
    }

    /**
     * Test updating of project for Parent Project
     */
    public function testUpdateProjectForParentProject()
    {
        $id = '0bd9d096-b8a9-11eb-8529-0242ac130003';
        $data = [
            'name' => 'Project 2', 
            'description' => 'test project 2', 
            'managerId' => '4fd9ce37-758f-11e9-b2d5-68ecc57cde45',
            'parentId' => '3e93ab92-b8a9-11eb-8529-0242ac130003'
        ];
        $this->projectService->updateProject($id, $data);
        $sqlQuery = "SELECT * FROM ox_project WHERE uuid = '".$id."'";
        $result = $this->runQuery($sqlQuery);
        $this->assertEquals(1, count($result));
        $this->assertEquals(2, $result[0]['manager_id']);
        $this->assertEquals(5, $result[0]['parent_id']);
    }
}