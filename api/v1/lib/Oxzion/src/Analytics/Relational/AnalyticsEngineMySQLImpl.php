<?php

namespace Oxzion\Analytics\Relational;

use Oxzion\Analytics\Relational\AnalyticsEngineRelational;
use Zend\Db\Adapter\Adapter;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;

class AnalyticsEngineMySQLImpl extends AnalyticsEngineRelational
{
    public function __construct($appDBAdapter, $appConfig)
    {
        parent::__construct($appDBAdapter, $appConfig);
    }

    public function setConfig($config)
    {
        $dbConfig['driver'] = 'Pdo';
        $dbConfig['database'] = $config['database'];
        $dbConfig['host'] = $config['host'];
        $dbConfig['username'] = $config['username'];
        $dbConfig['password'] = $config['password'];
        $dbConfig['dsn'] = 'mysql:dbname=' . $config['database'] . ';host=' . $config['host'] . ';charset=utf8;username=' . $config["username"] . ';password=' . $config["password"] . '';
        $this->dbConfig = $dbConfig;
        $this->dbAdapter = new Adapter($dbConfig);
    }

    public function getData($app_name, $entity_name, $parameters)
    {
        $result = parent::getData($app_name, $entity_name, $parameters);
        if (isset($parameters['view'])) {
            $entity_name = $parameters['view'];
        }
        $result['data'] = $this->changeDataType($result['data'], $entity_name, $parameters);
        return $result;
    }

    public function changeDataType($result, $entity_name, $parameters)
    {
        $metadata = $this->getFields($entity_name);
        if (!empty($result) && is_array($result)) {
            foreach ($result as $rowkey => $row) {
                if (is_array($row)) {
                    foreach ($row as $colkey => $column) {
                        if ($colkey=='count') {
                            $result[$rowkey][$colkey] = (int) $column;
                        }
                        elseif (isset($metadata[$colkey])) {
                            $type = $metadata[$colkey]['type'];
                            switch ($type) {
                                case 'int':
                                    $result[$rowkey][$colkey] = (int) $column;
                                    break;
                                case 'double':
                                case 'float':
                                case 'decimal':
                                    $result[$rowkey][$colkey] = (float) $column;
                                    break;
                            }
                        }
                    }
                }
            }
        }
        return $result;
    }
}
