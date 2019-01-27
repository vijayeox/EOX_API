<?php

namespace Oxzion\Db\Persistence;

use Bos\Service\AbstractService;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use Zend\Db\Table;
use Oxzion\Utils\FileUtils;
use PHPSQLParser\PHPSQLParser;
use PHPSQLParser\PHPSQLCreator;


class Persistence extends AbstractService {

    /**
     * Persistence constructor.
     * @param $config
     * @param $database
     */
    public function __construct($config, $database) {
        $this->database = $database;
        $this->config = $config;
        $config = $config['db'];
        $config['dsn'] = 'mysql:dbname=' . $this->database . ';host=' . $config['host'] . ';charset=utf8;username=' . $config["username"] . ';password=' . $config["password"] . '';
        $this->adapter = new Adapter($config);
        parent::__construct($config, $this->adapter);
    }

    /**
     * @param $sqlQuery
     * @return \Zend\Db\Adapter\Driver\ResultInterface
     */
    public function insertQuery($sqlQuery) {
        $parsedData = new PHPSQLParser($sqlQuery['query']);
        $parsedArray = $parsedData->parsed;
        try {
            if(!empty($parsedArray['INSERT'])) {
                foreach ($parsedArray['INSERT'] as $key => $insertArray) {
                    if($insertArray['expr_type'] === 'table') {
                        if ($insertArray['alias']) {
                            $tableName = $insertArray['alias']['name'];
                        } else {
                            $tableName = $insertArray['table'];
                        }
                        $columnResult = $this->adapter->query("SELECT TABLE_NAME, GROUP_CONCAT(COLUMN_NAME) as column_list FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name LIKE '$tableName'");
                        $resultSet1 = $columnResult->execute();
                        while ($resultSet1->next()) {
                            $resultTableName = $resultSet1->current();
                            $columnList = explode(",", $resultTableName['column_list']);
                            if (!in_array('ox_app_org_id', $columnList)) {
                                $tableResult = $this->adapter->query("ALTER TABLE " . $tableName . " ADD `ox_app_org_id` INT(11) NOT NULL");
                                $tableResult->execute();
                            }
                        }
                    }
                    if($insertArray['expr_type'] === 'column-list') {
                        if (strpos($insertArray['base_expr'], 'ox_app_org_id') !== true) {
                            $fieldsStringAfterTrim = substr(substr($insertArray['base_expr'], 0, -1), 1);
                            $fieldValue = explode(",", $fieldsStringAfterTrim);
                            array_push($fieldValue, "`ox_app_org_id`");
                            $fieldValueWithOrg = implode(",", $fieldValue);
                            array_push($parsedArray['INSERT'][$key]['sub_tree'],
                                Array (
                                    "expr_type" => "colref",
                                    "base_expr" => "`ox_app_org_id`",
                                    "no_quotes" => Array
                                    (
                                        "delim" => "",
                                        "parts" => Array
                                        (
                                            "0" => "ox_app_org_id"
                                        )
                                    )
                                )
                            );
                            $parsedArray['INSERT'][$key]['base_expr'] = "(" . $fieldValueWithOrg . ")";
                        }
                    }
                }
            }
            if(!empty($parsedArray['SELECT'])) {
                $SelectArrayKeys = array_keys($parsedArray['SELECT']);
                $lastElementInSelectList = end($SelectArrayKeys);
                $parsedArray['SELECT'][$lastElementInSelectList]['delim'] = ",";
                $selectExpressionOperator = Array (
                    "expr_type" => "const",
                    "base_expr" => '1',
                    "sub_tree" => "",
                    "delim" => "");
                array_push($parsedArray['SELECT'], $selectExpressionOperator);
                if(!empty($parsedArray['FROM'])) {
                    foreach ($parsedArray['FROM'] as $fromkey => $queryFrom) {
                        //Code to add the orgid column to the new JOIN table if it is not there.
                        if ($queryFrom['alias']) {
                            $tableName = $queryFrom['alias']['name'];
                        } else {
                            $tableName = $queryFrom['table'];
                        }
                        $tableArrayList[] = $tableName;
                        $columnResult = $this->adapter->query("SELECT TABLE_NAME, GROUP_CONCAT(COLUMN_NAME) as column_list FROM 
INFORMATION_SCHEMA.COLUMNS WHERE table_name LIKE '$tableName'");
                        $resultSet1 = $columnResult->execute();
                        while ($resultSet1->next()) {
                            $resultTableName = $resultSet1->current();
                            $columnList = explode(",", $resultTableName['column_list']);
                            if (!in_array('ox_app_org_id', $columnList)) {
                                $tableResult = $this->adapter->query("ALTER TABLE " . $tableName . " ADD `ox_app_org_id` INT(11) NOT NULL");
                                $tableResult->execute();
                            }
                        }
                        $parsedArray = $this->getReferenceClause($parsedArray, $fromkey, $queryFrom, $tableArrayList, "FROM");
                    }
                }
            }
            if (!empty($parsedArray['WHERE'])) {
                $expAndOperator = Array ("expr_type" => "operator", "base_expr" => "and", "sub_tree" => "");
                $exp_const = Array ("expr_type" => "const", "base_expr" => "1", "sub_tree" => "");
                $exp_operator = Array ("expr_type" => "operator", "base_expr" => "=", "sub_tree" => "");
                $exp_colref = Array ("expr_type" => "colref", "base_expr" => $tableArrayList[0] . ".ox_app_org_id", "sub_tree" => "", "no_quotes" =>
                    Array( "delim" => "", "parts" => Array ("0" => $tableArrayList[0], "1" => "ox_app_org_id") )
                );
                array_push($parsedArray['WHERE'], $expAndOperator, $exp_colref, $exp_operator, $exp_const);
            }
            if (!empty($parsedArray['VALUES'])) {
                foreach ($parsedArray['VALUES'] as $key => $insertArray) {
                    $fieldsStringAfterTrim = substr(substr($insertArray['base_expr'], 0, -1), 1);
                    $fieldValue = explode(",", $fieldsStringAfterTrim);
                    array_push($fieldValue, 1);
                    $fieldValueWithOrg = implode(",", $fieldValue);
                    array_push($parsedArray['VALUES'][$key]['data'],
                        Array (
                            "expr_type" => "const",
                            "base_expr" => 1,
                            "sub_tree" => ""
                        )
                    );
                    $parsedArray['VALUES'][$key]['base_expr'] = "(" . $fieldValueWithOrg . ")";
                }
            }
        } catch (Exception $e) {
            return 0;
        }
        return $queryExecute = $this->generateSQLFromArray($parsedArray);
    }

    /**
     * @param $sqlQuery
     * @return \Zend\Db\Adapter\Driver\ResultInterface
     */
    public function updateQuery($sqlQuery) {
        $parsedData = new PHPSQLParser($sqlQuery['query']);
        $parsedArray = $parsedData->parsed;
        try {
            if (!empty($parsedArray['UPDATE'])) {
                foreach ($parsedArray['UPDATE'] as $key => $updateArray) {
                    if ($updateArray['expr_type'] === 'table') {
                        if ($updateArray['alias']) {
                            $tableName = $updateArray['alias']['name'];
                        } else {
                            $tableName = $updateArray['table'];
                        }
                        $tableArrayList[] = $tableName;
                        $columnResult = $this->adapter->query("SELECT TABLE_NAME, GROUP_CONCAT(COLUMN_NAME) as column_list FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name LIKE '$tableName'");
                        $resultSet1 = $columnResult->execute();
                        while ($resultSet1->next()) {
                            $resultTableName = $resultSet1->current();
                            $columnList = explode(",", $resultTableName['column_list']);
                            if (in_array('ox_app_org_id', $columnList)) {
                                $expAndOperator = Array("expr_type" => "operator", "base_expr" => "and", "sub_tree" => "");
                                $exp_const = Array("expr_type" => "const", "base_expr" => "1", "sub_tree" => "");
                                $exp_operator = Array("expr_type" => "operator", "base_expr" => "=", "sub_tree" => "");
                                $exp_colref = Array("expr_type" => "colref", "base_expr" => $tableName . " . ox_app_org_id", "sub_tree" => "", "no_quotes" =>
                                    Array("delim" => "", "parts" => Array("0" => "ox_app_org_id"))
                                );
                                array_push($parsedArray['WHERE'], $expAndOperator, $exp_colref, $exp_operator, $exp_const);
                            }
                        }
                    }
                    $parsedArray = $this->getReferenceClause($parsedArray, $key, $updateArray, $tableArrayList, "UPDATE");
                }
            }
        } catch (Exception $e) {
            return 0;
        }
        return $queryExecute = $this->generateSQLFromArray($parsedArray);
    }

    /**
     * @param $sqlQuery
     * @return array
     */
    public function selectQuery($sqlQuery) {
        $parsedData = new PHPSQLParser($sqlQuery['query']);
        $parsedArray = $parsedData->parsed;
        try {
            if(!empty($parsedArray['FROM'])) {
                foreach ($parsedArray['FROM'] as $key => $updateArray) {
                    if ($updateArray['expr_type'] === 'table') {
                        if ($updateArray['alias']) {
                            $tableName = $updateArray['alias']['name'];
                        } else {
                            $tableName = $updateArray['table'];
                        }
                        $tableArrayList[] = $tableName;
                        $columnResult = $this->adapter->query("SELECT TABLE_NAME, GROUP_CONCAT(COLUMN_NAME) as column_list FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name LIKE '$tableName'");
                        $resultSet1 = $columnResult->execute();
                        while($resultSet1->next()) {
                            $resultTableName = $resultSet1->current();
                            $columnList = explode(",", $resultTableName['column_list']);
                            if(in_array('ox_app_org_id', $columnList)) {
                                $expAndOperator = Array ("expr_type" => "operator", "base_expr" => "and", "sub_tree" => "");
                                $exp_const = Array ("expr_type" => "const", "base_expr" => "1", "sub_tree" => "");
                                $exp_operator = Array ("expr_type" => "operator", "base_expr" => "=", "sub_tree" => "");
                                $exp_colref = Array ("expr_type" => "colref", "base_expr" => $tableName . " . ox_app_org_id", "sub_tree" => "", "no_quotes" =>
                                    Array( "delim" => "", "parts" => Array ("0" => "ox_app_org_id") )
                                );
                                array_push($parsedArray['WHERE'], $expAndOperator, $exp_colref, $exp_operator, $exp_const);
                            }
                        }
                    }
                    $parsedArray = $this->getReferenceClause($parsedArray, $key, $updateArray, $tableArrayList, "FROM");
                }
            }
        } catch (Exception $e) {
            return 0;
        }
        return $queryExecute = $this->generateSQLFromArray($parsedArray);
    }

    /**
     * @param $sqlQuery
     * @return \Zend\Db\Adapter\Driver\ResultInterface
     */
    public function deleteQuery($sqlQuery) {
        $parsedData = new PHPSQLParser($sqlQuery['query']);
        $parsedArray = $parsedData->parsed;
        try {
            if(!empty($parsedArray['FROM'])) {
                foreach ($parsedArray['FROM'] as $key => $updateArray) {
                    if ($updateArray['expr_type'] === 'table') {
                        $tableName = $updateArray['table'];
                        $columnResult = $this->adapter->query("SELECT TABLE_NAME, GROUP_CONCAT(COLUMN_NAME) as column_list FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name LIKE '$tableName'");
                        $resultSet1 = $columnResult->execute();
                        while($resultSet1->next()) {
                            $resultTableName = $resultSet1->current();
                            $columnList = explode(",", $resultTableName['column_list']);
                            if(in_array('ox_app_org_id', $columnList)) {
                                $expAndOperator = Array ("expr_type" => "operator", "base_expr" => "and", "sub_tree" => "");
                                $exp_const = Array ("expr_type" => "const", "base_expr" => "1", "sub_tree" => "");
                                $exp_operator = Array ("expr_type" => "operator", "base_expr" => "=", "sub_tree" => "");
                                $exp_colref = Array ("expr_type" => "colref", "base_expr" => "ox_app_org_id", "sub_tree" => "", "no_quotes" =>
                                    Array( "delim" => "", "parts" => Array ("0" => "ox_app_org_id") )
                                );
                                array_push($parsedArray['WHERE'], $expAndOperator, $exp_colref, $exp_operator, $exp_const);
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            return 0;
        }
        return $queryExecute = $this->generateSQLFromArray($parsedArray);
    }

    private function getReferenceClause($parsedArray, $key, $data, $tableArrayList, $queryStatement) {
        if(!empty($data['ref_clause'])) {
            $expAndOperator = Array ("expr_type" => "operator", "base_expr" => "and", "sub_tree" => "");
            array_push($parsedArray[$queryStatement][$key]['ref_clause'], $expAndOperator);
            $arrayKeys = array_keys($tableArrayList);
            $lastElementInTableList = end($arrayKeys);
            foreach($tableArrayList as $fromkey => $tableList) {
                $exp_colref = Array (
                    "expr_type" => "colref",
                    "base_expr" => $tableList . ".ox_app_org_id",
                    "no_quotes" => Array (
                        "delim" => ".",
                        "parts" => Array("0" => $tableList, "1" => "ox_app_org_id")
                    ),
                    "sub_tree" => "",
                );
                if($lastElementInTableList == $fromkey) {
                    array_push($parsedArray[$queryStatement][$key]['ref_clause'], $exp_colref);
                } else {
                    $exp_operator = Array ("expr_type" => "operator", "base_expr" => "=", "sub_tree" => "");
                    array_push($parsedArray[$queryStatement][$key]['ref_clause'], $exp_colref, $exp_operator);
                }
            }
        }
        return $parsedArray;
    }

    private function generateSQLFromArray($parsedArray) {
        $statement = new PHPSQLCreator($parsedArray);
        $statement3 = $this->adapter->query($statement->created);
        return $statement3->execute();
    }

}