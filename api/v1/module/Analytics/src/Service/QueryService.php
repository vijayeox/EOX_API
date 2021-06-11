<?php
namespace Analytics\Service;

use Analytics\Model\Query;
use Analytics\Model\QueryTable;
use Exception;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\InvalidInputException;
use Oxzion\Service\AbstractService;
use Oxzion\Utils\FilterUtils;
use Oxzion\ValidationException;
use Zend\Db\Exception\ExceptionInterface as ZendDbException;

class QueryService extends AbstractService
{
    private $table;
    private $datasourceService;
    private $total_count;
    public static $queryFields = array('uuid' => 'q.uuid', 'name' => 'q.name', 'datasource_uuid' => 'd.uuid', 'configuration' => 'q.configuration', 'ispublic' => 'q.ispublic', 'created_by' => 'q.created_by', 'version' => 'q.version', 'account_id' => 'q.account_id');

    public function __construct($config, $dbAdapter, QueryTable $table, $datasourceService)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
        $this->datasourceService = $datasourceService;
    }

    public function getTotalCount()
    {
        return $this->total_count;
    }

    public function createQuery($data)
    {
        $dataSourceUuid = $data['datasource_id'];
        $dataSourceId = $this->getIdFromUuid('ox_datasource', $dataSourceUuid);
        $data['datasource_id'] = $dataSourceId;
        $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        $data['account_id'] = $accountId;
        $accountUuid = $this->getUuidFromId('ox_datasource', $accountId);

        $query = new Query($this->table);
        $query->assign($data);
        $query->setForeignKey('account_id', $accountId); //account_id is defined as readonly in the model.
        $query->setForeignKey('datasource_id', $dataSourceId); //datasource_id is defined as readonly in the model.
        try {
            $this->beginTransaction();
            $query->save();
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        //$query->assignBack($data);
        //$data['datasource_id'] = $dataSourceUuid;
        //$data['account_id'] = $accountUuid;
        return $query->getGenerated();
    }

    public function updateQuery($uuid, $data)
    {
        unset($data['datasource_id']);
        $query = new Query($this->table);
        $query->loadByUuid($uuid);
        $query->assign($data);
        try {
            $this->beginTransaction();
            $query->save();
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        return $query->getGenerated();
    }

    public function deleteQuery($uuid, $version)
    {
        $query = new Query($this->table);
        $query->loadByUuid($uuid);
        $query->assign([
            'version' => $version,
            'isdeleted' => 1,
        ]);
        try {
            $this->beginTransaction();
            $query->save();
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function getQuery($uuid, $params)
    {
        $query = 'select q.uuid, q.name, q.configuration, q.ispublic, if(q.created_by=:created_by, true, false) as is_owner, q.isdeleted, q.version, d.uuid as datasource_uuid, d.name as datasource_name from ox_query q join ox_datasource d on d.id=q.datasource_id where q.isdeleted=false and q.account_id=:account_id and q.uuid=:uuid and (q.ispublic=true or q.created_by=:created_by)';
        $queryParams = [
            'created_by' => AuthContext::get(AuthConstants::USER_ID),
            'account_id' => AuthContext::get(AuthConstants::ACCOUNT_ID),
            'uuid' => $uuid,
        ];
        try {
            $resultSet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
            if (count($resultSet) == 0) {
                return 0;
            }
            $response = [
                'query' => $resultSet[0],
            ];
            //Query configuration value from database is a JSON string. Convert it to object and overwrite JSON string value.
            if ($resultSet[0]["configuration"]) {
                $response['query']['configuration'] = json_decode($resultSet[0]["configuration"]);
            }
        } catch (ZendDbException $e) {
            $this->logger->error('Database exception occurred.');
            $this->logger->error($e);
            return 0;
        }

        if (isset($params['data'])) {
            $queryResult = $this->runQuery($resultSet[0]['configuration'], $resultSet[0]['datasource_uuid']);
            $response['query']['data'] = $queryResult['data'];
            if (isset($queryResult['targetquery'])) {
                $response['query']['targetquery']= $queryResult['targetquery'];
            }
        }
        return $response;
    }

    public function getQueryList($params = null)
    {
        $paginateOptions = FilterUtils::paginateLikeKendo($params, self::$queryFields);
        $where = $paginateOptions['where'];
        if (isset($params['show_deleted']) && $params['show_deleted'] == true) {
            $where .= empty($where) ? "WHERE (q.account_id =" . AuthContext::get(AuthConstants::ACCOUNT_ID) . ") and (q.created_by = " . AuthContext::get(AuthConstants::USER_ID) . " OR q.ispublic = 1)" : " AND(q.account_id =" . AuthContext::get(AuthConstants::ACCOUNT_ID) . ") and (q.created_by = " . AuthContext::get(AuthConstants::USER_ID) . " OR q.ispublic = 1)";
        } else {
            $where .= empty($where) ? "WHERE q.isdeleted <> 1 AND (q.account_id =" . AuthContext::get(AuthConstants::ACCOUNT_ID) . ") and (q.created_by = " . AuthContext::get(AuthConstants::USER_ID) . " OR q.ispublic = 1)" : " AND q.isdeleted <> 1 AND(q.account_id =" . AuthContext::get(AuthConstants::ACCOUNT_ID) . ") and (q.created_by = " . AuthContext::get(AuthConstants::USER_ID) . " OR q.ispublic = 1)";
        }
        $sort = $paginateOptions['sort'] ? " ORDER BY " . $paginateOptions['sort'] : '';
        $limit = " LIMIT " . $paginateOptions['pageSize'] . " offset " . $paginateOptions['offset'];

        $cntQuery = "SELECT count(id) as 'count' FROM `ox_query` as q ";
        $resultSet = $this->executeQuerywithParams($cntQuery . $where);
        $count = $resultSet->toArray()[0]['count'];

        if (isset($params['show_deleted']) && $params['show_deleted'] == true) {
            $query = "SELECT q.uuid,q.name,d.uuid as datasource_uuid,q.configuration,q.ispublic,IF(q.created_by = " . AuthContext::get(AuthConstants::USER_ID) . ", 'true', 'false') as is_owner,q.version,q.account_id,q.isdeleted FROM `ox_query` as q inner join ox_datasource as d on q.datasource_id = d.id " . $where . " " . $sort . " " . $limit;
        } else {
            $query = "SELECT q.uuid,q.name, d.uuid as datasource_uuid, datasource_id,q.configuration,q.ispublic,IF(q.created_by = " . AuthContext::get(AuthConstants::USER_ID) . ", 'true', 'false') as is_owner,q.version,q.account_id FROM `ox_query` as q inner join ox_datasource as d on q.datasource_id = d.id " . $where . " " . $sort . " " . $limit;
        }
        $resultSet = $this->executeQuerywithParams($query);
        $result = $resultSet->toArray();
        foreach ($result as $key => $value) {
            $result[$key]['configuration'] = json_decode($result[$key]['configuration']);
            unset($result[$key]['id']);
        }
        return array('data' => $result,
            'total' => $count);
    }

    public function getQueryJson($uuid)
    {
        $statement = "Select configuration as query from ox_query where isdeleted <> 1 AND uuid = '" . $uuid . "'";
        $resultSet = $this->executeQuerywithParams($statement);
        $result = $resultSet->toArray();
        if ($result) {
            return $result[0];
        } else {
            return 0;
        }
    }

    public function executeAnalyticsQuery($uuid, $overRides = null)
    {
        $query = 'select q.uuid, q.name, q.configuration, q.ispublic, q.isdeleted, d.uuid as datasource_uuid from ox_query q join ox_datasource d on d.id=q.datasource_id where q.isdeleted=false and q.account_id=:account_id and q.uuid=:uuid';
        $queryParams = [
            'account_id' => AuthContext::get(AuthConstants::ACCOUNT_ID),
            'uuid' => $uuid,
        ];
        $resultSet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
        if (count($resultSet) == 0) {
            return 0;
        }
        $configuration = $resultSet[0]['configuration'];
        $configArray = json_decode($configuration, 1);
        if (isset($overRides[$uuid])) {
            if (array_key_exists('filter', $overRides[$uuid])) {
                if (!empty($overRides[$uuid]['filter'])) {
                    $configArray['inline_filter'][] = $overRides[$uuid]['filter'];
                }
                unset($overRides[$uuid]['filter']);
            }
            if (!empty($overRides[$uuid])) {
                foreach ($overRides[$uuid] as $key => $config) {
                    if ($config !== null) {
                        $configArray[$key] = $config;
                    }
                }
            }
        }
        $configuration = json_encode($configArray);
        $result = $this->runQuery($configuration, $resultSet[0]['datasource_uuid'], $overRides);
        return $result;
    }

    public function previewQuery($params)
    {
        $errors = array();
        if (isset($params['datasource_id'])) {
            $datasource_id = $params['datasource_id'];
        } else {
            array_push($errors, array('message' => 'datasource_id is required'));
        }
        if (isset($params['configuration'])) {
            $configuration = $params['configuration'];
        } else {
            array_push($errors, array('message' => 'configuration is required'));
        }

        if (count($errors) > 0) {
            $validationException = new ValidationException();
            $validationException->setErrors($errors);
            throw $validationException;
        }
        if (isset($params['debug'])) {
            $configtemp = json_decode($configuration, 1);
            $configtemp['debug']=$params['debug'];
            $configuration = json_encode($configtemp);
        }
        try {
            $result = $this->runQuery($configuration, $datasource_id);
        } catch (Exception $e) {
            $this->logger->error('Error in running the query');
            $this->logger->error($e);
            throw $e;
        }
        return $result;
    }

    private function runQuery($configuration, $datasource_uuid, $overRides = null)
    {
        $analyticsEngine = $this->datasourceService->getAnalyticsEngine($datasource_uuid);
        $parameters = json_decode($configuration, 1);

        // if (isset($parameters['filter']) && is_string($parameters['filter'])) {
        //     $exp_config = json_decode($parameters['filter'], 1);
        //     $parameters['filter'] = $exp_config;
        // }

        // if (isset($parameters['sort']) && is_string($parameters['sort'])) {
        //     $exp_sort = json_decode($parameters['sort'], 1);
        //     $parameters['sort'] = $exp_sort;
        // }

        if (isset($parameters['filter']) && is_string($parameters['filter'])) {
            $parameters['filter'] = $this->stringDecode($parameters['filter']);
        }
        if (isset($parameters['sort']) && is_string($parameters['sort'])) {
            $parameters['sort'] = $this->stringDecode($parameters['sort']);
        }

        if (!isset($parameters['inline_filter'])) {
            $parameters['inline_filter'] = [];
        }
        if (!empty($overRides)) {
            if (array_key_exists('filter', $overRides)) {
                if (!empty($overRides['filter'])) {
                    $filter = '{"filter":' . $overRides['filter'] . '}';
                    $filter = json_decode($filter, 1);
                    $parameters['inline_filter'][] = $filter['filter']; //inline filter takes the highest precedence
                }
                unset($overRides['filter']);
            }
            foreach ($overRides as $key => $value) {
                if ($value !== null) {
                    $parameters[$key] = $value;
                }
            }
        }
        if (isset($parameters['app_name'])) {
            $app_name = $parameters['app_name'];
        } else {
            $app_name = '';
        }
        if (isset($parameters['entity_name'])) {
            $entity_name = $parameters['entity_name'];
        } else {
            $entity_name = null;
        }
        // echo "Parameters"; print_r($parameters);exit;
        $result = $analyticsEngine->runQuery($app_name, $entity_name, $parameters);
        return $result;
    }

    private function stringDecode($params)
    {
        if (isset($params) && is_string($params)) {
            $exp_sort = json_decode($params, 1);
            $params = $exp_sort;
        }
        return $params;
    }

    public function queryData($rows)
    {
        if (array_key_exists('uuids', $rows)) {
            $data = $this->runMultipleQueries($rows['uuids']);
        } else {
            $errors = array('message' => 'uuids is required');
            $validationException = new ValidationException();
            $validationException->setErrors($errors);
            throw $validationException;
        }
        return $data;
    }

    public function mergeArrays($a, $b, $oldkey, $newkey, $keys)
    {
        $c = array();
        foreach ($a as $row1) {
            $found = 0;
            $tmprow1 = array_intersect_key($row1, array_flip($keys));
            foreach ($b as $key => $row2) {
                $tmprow2 = array_intersect_key($row2, array_flip($keys));
                if ($tmprow1 == $tmprow2) {
                    $mergevalue = ($oldkey == $newkey) ? [$oldkey => $row2[$oldkey]] : [$newkey => $row2[$oldkey]];
                    $c[] = array_merge($row1, $mergevalue);
                    unset($b[$key]);
                    $found = 1;
                    break;
                }
            }
            if (!$found) {
                $c[] = $row1;
            }
        }
        foreach ($b as $row) {
            if ($oldkey != $newkey) {
                $row[$newkey] = $row[$oldkey];
                unset($row[$oldkey]);
            }
            $c[] = $row;
        }
        return ($c);
    }

    public function mergeData($data1, $data2, $index)
    {
        $arrykeys1 = array_keys($data1[0]);
        $arrykeys2 = array_keys($data2[0]);
        $oldkey = $arrykeys2[count($arrykeys2) - 1];
        array_pop($arrykeys2);
        if (in_array($oldkey, $arrykeys1)) {
            $newkey = $oldkey . $index;
        } else {
            $newkey = $oldkey;
        }
        $data = $this->mergeArrays($data1, $data2, $oldkey, $newkey, $arrykeys2);
        return $data;
    }

    public function runMultipleQueries($uuidList, $overRides = null)
    {
        $aggCheck = 0;
        $data = array();
        $resultCount = count($uuidList);
        $index = 1;
        foreach ($uuidList as $key => $value) {
            $this->logger->info("Executing AnalyticsQuery with input -" . $value);
            $queryData = $this->executeAnalyticsQuery($value, $overRides);
            if (isset($queryData['total_count'])) {
                $this->total_count = $queryData['total_count'];
            }
            $this->logger->info("Executing AnalyticsQuery returned -" . print_r($queryData, true));
            if ($queryData == null || $queryData == 0) {
                throw new InvalidInputException("uuid entered is incorrect - $value", 1);
            }

            if ($key == 0) {
                if (!empty($queryData['meta']['aggregates'])) {
                    $aggCheck = 1;
                }
            }
            if (!empty($data) && !empty($queryData['data']) && is_array($queryData['data'])) {
                if ($aggCheck == 1) {
                    if (!empty($queryData['meta']['aggregates'])) {
                        $data = $this->mergeData($data, $queryData['data'], $index);
                    } else {
                        throw new InvalidInputException("Aggregate query type cannot be followed by a non-aggregate query type", 1);
                    }
                } else {
                    if (!empty($queryData['meta']['aggregates'])) {
                        throw new InvalidInputException("Non-aggregate query type cannot be followed by a aggregate query type", 1);
                    } else {
                        $data = $this->mergeData($data, $queryData['data'], $index);
                    }
                }
            } else {
                if (!empty($queryData['data'])) {
                    if (!is_array($queryData['data']) && $resultCount > 1) {
                        $data[0]['q' . strval($key + 1)] = $queryData['data'];
                    } else {
                        $data = $queryData['data'];
                    }
                }
            }
            $index++;
        }
        return $data;
    }

    public function runMultipleQueriesWithoutCombine($uuidList, $overRides = null)
    {
        $aggCheck = 0;
        $data = array();
        $resultCount = count($uuidList);
        $index = 1;
        foreach ($uuidList as $key => $value) {
            $this->logger->info("Executing AnalyticsQuery with input -" . $value);
            $queryData = $this->executeAnalyticsQuery($value, $overRides);
            $this->logger->info("Executing AnalyticsQuery returned -" . print_r($queryData, true));
            $data[]=$queryData['data'];
        }
        return $data;
    }
}
