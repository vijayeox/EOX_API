<?php
namespace Oxzion\Analytics\Relational;

use Exception;
use Oxzion\Analytics\AnalyticsAbstract;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\Utils\AnalyticsUtils;
use Zend\DB;
use Zend\Db\Metadata\Source\Factory;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Where;

abstract class AnalyticsEngineRelational extends AnalyticsAbstract
{
    protected $dbAdapter;
    protected $dbConfig;
    private $filterTmpFields;
    private $filterFields;
    private $query;

    public function __construct($appDBAdapter, $appConfig)
    {
        parent::__construct($appDBAdapter, $appConfig);
    }

    public function setConfig($config)
    {
        parent::setConfig($config);
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function getData($app_name, $entity_name, $parameters)
    {
        try {
            $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
            if (isset($parameters['view'])) {
                //	$result = $this->getResultsFromView($orgId,$parameters['view']);
                $formatedPara = $this->formatQuery($parameters);
                //	print_r($formatedPara);exit;
                $result = $this->getResultsFromPara($accountId, $parameters['view'], $formatedPara);
                $finalResult['meta']['type'] = 'view';
                $finalResult['data'] = $result;
            } else {
                $formatedPara = $this->formatQuery($parameters);
                $result = $this->getResultsFromPara($accountId, $app_name, $entity_name, $formatedPara);
                $finalResult['data'] = $result;
            }
            return $finalResult;
        } catch (Exception $e) {
            throw new Exception("Error running MySQL Analytics", 0, $e);
        }
    }

    public function formatQuery($parameters)
    {
        $range=null;
        $field = null;
        $filter =array();
        $datetype = (!empty($parameters['date_type']))?$parameters['date_type']:null;
        if (!empty($parameters['date-period'])) {
            $dateperiod = $parameters['date-period'];
        }
        if (!empty($parameters['date_period'])) {
            $dateperiod =  $parameters['date_period'];
        }

        if (!empty($dateperiod)) {
            $period = explode('/', $dateperiod);
            $startdate = date('Y-m-d', strtotime($period[0]));
            $enddate =  date('Y-m-d', strtotime($period[1]));
        } else {
            $startdate = date('Y').'-01-01';
            $enddate = date('Y').'-12-31';
        }
        if (!empty($parameters['field'])) {
            if (substr(strtolower($parameters['field']), 0, 5) == 'date(') {
                $parameters['field'] = substr($parameters['field'], 5, -1);
            }
            $field = $parameters['field'];
        }

        if (!isset($parameters['operation'])) {
            $parameters['operation'] = 'count';
        }
        $operation = strtolower($parameters['operation']);
        $group = array();

        if (!empty($parameters['group'])) {
            $parameters['frequency'] = null;  //frequency 4 is to override time frequecy by group
            if (is_array($parameters['group'])) {
                $group = $parameters['group'];
            } else {
                $group = explode(',', $parameters['group']);
            }
        }
        $select = array();
        

        if (!empty($parameters['frequency'])) {
            $fieldname = "";
            switch ($parameters['frequency']) {
                case 1:
                    $group[] =  "DATE($datetype)";
                    $fieldname = "day";
                    break;
                case 2:
                    $group[] = "MONTHNAME($datetype)";
                    $fieldname = "month";
                    break;
                case 3:
                    $group[] = "QUARTER($datetype)";
                    $fieldname = "quarter";
                    break;
                case 4:
                    $group[] = "YEAR($datetype)";
                    $fieldname = "year";
                    break;
            }
        }
        if (!empty($group)) {
            if (!empty($parameters['frequency'])) {
                $select = [$fieldname=>new \Zend\Db\Sql\Expression("$group[0]")];
            } else {
                $select = $group;
            }
        }
        if ($field) {
            $select[$field] = new \Zend\Db\Sql\Expression("$operation($field)");
        } else {
            if (!empty($group)) {
                $select['count'] = new \Zend\Db\Sql\Expression("count(*)");
                ;
            } else {
                $select[]="*";
            }
        }
        if ($datetype) {
            $range = "$datetype between '$startdate' and '$enddate'";
        }
        if (isset($parameters['filter'])) {
            $filter[] = $parameters['filter'];
        }
        if (isset($parameters['inline_filter'])) {
            foreach ($parameters['inline_filter'] as $inlineArry) {
                array_unshift($filter, $inlineArry);
            }
        }
        $returnarray = array('group' => $group, 'range' => $range, 'select' => $select,'filter'=>$filter);
        if (isset($parameters['pagesize'])) {
            $returnarray['limit'] = $parameters['pagesize'];
        }
        if (isset($parameters['list'])) {
            $returnarray['list'] =$parameters['list'];
            $listConfig=explode(",", $parameters['list']);
            $returnarray['select'] = [];
            foreach ($listConfig as $k => $v) {
                if (strpos($v, "=")!==false) {
                    $listitem = explode("=", $v);
                    $returnarray['select'][$listitem[1]] = $listitem[0];
                    $returnarray['displaylist'][] = $listitem[1];
                } else {
                    $returnarray['select'][] = $v;
                    $returnarray['displaylist'][] = $v;
                }
            }
        }
        if (isset($parameters['sort'])) {
            $returnarray['sort'] = $parameters['sort'];
        }
        if (isset($parameters['frequency'])) {
            $returnarray['frequency'] = $parameters['frequency'];
        }

        return $returnarray;
    }

    public function getResultsFromView($accountId, $view)
    {
        $sql    = new Sql($this->dbAdapter);
        $select = $sql->select();
        $select->from($view);
        //	$select->where(['org_id' => $orgId]);   //commented it out for now since it is clearning out the order.
        //We will need this in the future so cross org by mistake is not possible
        $statement = $sql->prepareStatementForSqlObject($select);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        return $resultSet->initialize($result)->toArray();
    }

    public function getResultsFromPara($accountId, $entity_name, $para)
    {
        $sql    = new Sql($this->dbAdapter);
        $select = $sql->select();
        //		print_r($para['select']);exit;
        if (!empty($para['select'])) {
            $select->columns($para['select']);
        }
        $select->from($entity_name);

        
        if (!empty($para['filter'])) {
            $this->filterFields = array();
            foreach ($para['filter'] as $filter) {
                $this->filterTmpFields = array();
                $where = new Where();
                $this->createFilter($filter, $where);
                $select->where($where);
                $this->filterFields=array_merge($this->filterFields, $this->filterTmpFields);
            }
        }
        $select->where(['account_id' => $accountId]);

        if (!empty($para['range'])) {
            $select->where($para['range']);
        }

        if (!empty($para['group'])) {
            if (!empty($para['frequency'])) {
                $group = $para['group'][0];
                $select->group(new \Zend\Db\Sql\Expression("$group"));
            } else {
                $select->group($para['group']);
            }
        }
        if (!empty($para['limit'])) {
            $select->limit($para['limit']);
        }

        if (!empty($para['sort'])) {
            $select->order($para['sort']);
        }
        $statement = $sql->prepareStatementForSqlObject($select);
        $this->query = $sql->buildSqlString($select);
        //	print_r($sql->buildSqlString($select));
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $finalResult = $resultSet->initialize($result)->toArray();
        if (empty($para['group']) && empty($para['list'])) {
            $arryvalues = array_values($finalResult[0]);
            $finalResult = $arryvalues[0];
        }
        return $finalResult;
    }



    protected function createFilter($filter, $where)
    {
        $symMapping = ['>'=>'greaterThan','>='=>'greaterThanOrEqualTo','<'=>'lessThan',
        '<='=>'lessThanOrEqualTo','!='=>'notEqualTo','LIKE'=>'like','NOT LIKE'=>'notLike'];
        $boolMapping = ['OR'=>'or','AND'=>'and'];
        if (!isset($filter[1]) && is_array($filter)) {
            $filter = $filter[0];
        }
        $column = $filter[0];
        if (isset($filter[2])) {
            $value = $filter[2];
            $condition = $filter[1];
        } else {
            $condition = "==";
            $value = $filter[1];
        }
        if (strtoupper($condition)=='OR' or strtoupper($condition)=='AND') {
            $whereNest = $where->nest();
            $this->createFilter($column, $whereNest);
            if (strtoupper($condition)=='OR') {
                $whereNest->or;
            } else {
                $whereNest->and;
            }
            $this->createFilter($value, $whereNest);
            $whereNest->unnest();
        } else {
            if (!in_array($column, $this->filterFields)) {
                // echo $column.' '.$condition.' '.$value;exit;
                $value = AnalyticsUtils::checkSessionValue($value);
                if (strtolower(substr($value, 0, 5))=="date:") {
                    $value = date("Y-m-d", strtotime(substr($value, 5)));
                }
                if ($condition=="==") {
                    if (!is_array($value)) {
                        $where->equalTo($column, $value);
                    } else {
                        $where->in($column, $value);
                    }
                } else {
                    $functionName = $symMapping[$condition];
                    $where->$functionName($column, $value);
                }
                $this->filterTmpFields[] = $column;
            }
        }
    }
    
    public function getFields($table)
    {
        $metadata = \Zend\Db\Metadata\Source\Factory::createSourceFromAdapter($this->dbAdapter);
        $table = $metadata->getTable($table);
        $columns = $table->getColumns();
        $data=array();
        foreach ($columns as $column) {
            $data[$column->getName()]=["type"=>$column->getDataType(),"maxlength"=>$column->getCharacterMaximumLength()];
        }
        return $data;
    }

    public function getDataEntities()
    {
        $metadata = \Zend\Db\Metadata\Source\Factory::createSourceFromAdapter($this->dbAdapter);
        // get the table names
        $tableNames = $metadata->getViewNames();
        return $tableNames;
    }

    public function getValues($index, $field)
    {
        $sql    = new Sql($this->dbAdapter);
        $select = $sql->select();
        $select->quantifier('DISTINCT');
        $select->columns(array($field));
        $select->from($index);
        $statement = $sql->prepareStatementForSqlObject($select);
        $result = $statement->execute();
        $resultSet = new ResultSet();
        $data = $resultSet->initialize($result)->toArray();
        $values = array_column($data, $field);
        return $values;
    }
}
