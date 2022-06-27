<?php

namespace Oxzion\Db\Migration;

use Oxzion\Service\AbstractService;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use Zend\Db\Table;
use Oxzion\Utils\FileUtils;
use Oxzion\App\AppArtifactNamingStrategy;
use Exception;

class Migration extends AbstractService
{
    private $database;
    private $appName;
    private $appId;
    private $mysqlAdapter;
    private $description;
    /**
     * Migration constructor.
     * @param $config
     * @param $database
     */
    public function __construct($config, $appName, $appId, $description)
    {
        if (empty($appName) || empty($appId)) {
            throw new Exception("appName and appId cannot be empty!");
        }

        $this->description = $description;
        $this->appName = $appName;
        $this->appId = $appId;

        $dbConfig = array_merge(array(), $config['db']);
        $dbConfig['dsn'] = 'mysql:dbname=mysql;host=' . $dbConfig['host'] . ';charset=utf8;username=' . $dbConfig["username"] . ';password=' . $dbConfig["password"] . '';
        $dbConfig['database'] = 'mysql';
        $this->mysqlAdapter = new Adapter($dbConfig);

        $this->database = AppArtifactNamingStrategy::getDatabaseName(['name' => $appName, 'uuid' => $appId]);
        $adapter = self::createAdapter($config, $this->database);
        parent::__construct($config, $adapter);
        $this->initDB();
    }

    public static function createAdapter($config, $database)
    {
        $dbConfig = $config['db'];
        $dbConfig['dsn'] = 'mysql:dbname=' . $database . ';host=' . $dbConfig['host'] . ';charset=utf8;username=' . $dbConfig["username"] . ';password=' . $dbConfig["password"] . '';
        $dbConfig['database'] = $database;
        $adapter = new Adapter($dbConfig);
        return $adapter;
    }

    //this method is used only for phpunit tests. Not required to be called otherwise
    public function getAdapter()
    {
        return $this->dbAdapter;
    }

    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @param $data
     * @return int
     */
    private function initDB()
    {
        $adapter = $this->mysqlAdapter;
        try {
            $adapter->getDriver()->getConnection()->beginTransaction();
            $checkVersion = $this->checkDB(); //Code to check if the App Version is already installed.
            if (($checkVersion == 0)) {
                $sqlQuery = 'CREATE DATABASE IF NOT EXISTS ' . $this->database;
                $statement = $this->mysqlAdapter->query($sqlQuery);
                $result = $statement->execute();
                if ($result) {
                    $appVersion = $this->insertAppVersion();
                } else {
                    //this method is not there!!!!!
                    //$this->updateMigration();
                }
                $adapter->getDriver()->getConnection()->commit();
            } else {
                $adapter->getDriver()->getConnection()->rollback();
                ;
            }
        } catch (Exception $e) {
            $adapter->getDriver()->getConnection()->rollback();
            throw $e;
        }
    }

    /**
     * @return mixed
     */
    private function checkDB()
    {
        $adapter = $this->mysqlAdapter;
        $sqlQuery = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '".$this->database."'" ;
        $statement = $adapter->query($sqlQuery);
        $result = $statement->execute();
        return $result->count();
    }

    /**
     * @return \Zend\Db\Adapter\Driver\ResultInterface
     */
    private function insertAppVersion()
    {
        //The code to add the app version information to the table, after it is been installed into the OXZion system.
        $adapter = $this->dbAdapter;

        try {
            //Code to create the migration table once the app is installed to the system.
            $this->beginTransaction();
            $createQuery = "CREATE TABLE IF NOT EXISTS `ox_app_migration_version` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `version_number` varchar(1000) NOT NULL,
                  `date_created` datetime NOT NULL,
                  `date_modified` datetime NULL,
                  `description` varchar(10000),
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=latin1;";
            $statement1 = $adapter->query($createQuery);
            $result = $statement1->execute();
            $this->commit();
        } catch (Exception $e) {
            // print_r($e->getMessage());exit;
            $this->rollback();
            throw $e;
        }
        return $result;
    }

    /**
     * @param $fileList
     * @param $migrationFolder
     * @param $data
     * @return int
     */
    public function migrate($migrationFolder)
    {
        //The code to add the app version information to the table, after it is been installed into the OXZion system.
        try {
            $checkDb = $this->checkDB();
            if ($checkDb == 1) {
                $this->beginTransaction();
                $adapter = $this->dbAdapter;
                $fileList = array_diff(scandir($migrationFolder), array(".", ".."));
                sort($fileList);
                foreach ($fileList as $files) {
                    $versionExp = explode("__", $files);
                    $statement1 = $adapter->query("Select id from ox_app_migration_version where version_number = '$versionExp[0]'");
                    $result1 = $statement1->execute();
                    if ($result1->getAffectedRows() === 0) {
                        $versionArray[] = $versionExp[0];
                        $fileContent = file_get_contents($migrationFolder."/".$files);
                        $statement = $adapter->query($fileContent);
                        $statement->execute();
                        $statement->getResource()->closeCursor();
                    }
                }

                //Code to add the new column account_id to the table that is created
                $columnResult = $this->mysqlAdapter->query("SELECT TABLE_NAME, GROUP_CONCAT(COLUMN_NAME) as column_list FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . $this->database . "' and table_name NOT LIKE 'ox_app_migration_version' GROUP BY TABLE_NAME");
                $resultSet1 = $columnResult->execute();
                while ($resultSet1->next()) {
                    $resultTableName = $resultSet1->current();
                    $columnList = explode(",", $resultTableName['column_list']);
                    if (!in_array('ox_app_account_id', $columnList)) {
                        $tableResult = $adapter->query("ALTER TABLE " . $resultTableName['TABLE_NAME'] . " ADD `ox_app_account_id` INT(32) NOT NULL DEFAULT '0'");
                        $tableResult->execute();
                    }
                }

                if (!empty($versionArray)) {
                    //Insert the latest version to the migration table if it is not already added.
                    foreach ($versionArray as $ver) {
                        $statement1 = $adapter->query("Select id from ox_app_migration_version where version_number = '$ver'");
                        $result1 = $statement1->execute();
                        if ($result1->getAffectedRows() === 0) {
                            $sqlQuery = "INSERT into ox_app_migration_version (version_number, date_created, description) VALUES('" . $ver . "', '" . Date('Y-m-d H:i:s') . "', '" . $this->description . "')";
                            $statement3 = $adapter->query($sqlQuery);
                            $statement3->execute();
                        }
                    }
                }
                $this->commit();
            } else {
                $this->rollback();
                throw new Exception("Database not found");
            }
            return 1;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
}
