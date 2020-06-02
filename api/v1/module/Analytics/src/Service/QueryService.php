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
use Ramsey\Uuid\Uuid;
use Zend\Db\Exception\ExceptionInterface as ZendDbException;

class QueryService extends AbstractService
{

    private $table;
    private $datasourceService;
    static $queryFields = array('uuid' => 'q.uuid', 'name' => 'q.name', 'datasource_uuid' => 'd.uuid', 'configuration' => 'q.configuration', 'ispublic' => 'q.ispublic', 'created_by' => 'q.created_by', 'version' => 'q.version', 'org_id' => 'q.org_id');

    public function __construct($config, $dbAdapter, QueryTable $table, $datasourceService)
    {
        parent::__construct($config, $dbAdapter);
        $this->table             = $table;
        $this->datasourceService = $datasourceService;
    }

    public function createQuery($data)
    {
        $form                 = new Query();
        $data['uuid']         = Uuid::uuid4()->toString();
        $data['created_by']   = AuthContext::get(AuthConstants::USER_ID);
        $data['date_created'] = date('Y-m-d H:i:s');
        $data['org_id']       = AuthContext::get(AuthConstants::ORG_ID);
        if (isset($data['datasource_id'])) {
            $data['datasource_id'] = $this->getIdFromUuid('ox_datasource', $data['datasource_id']);
        }

        $form->exchangeWithSpecificKey($data, 'value');
        $form->validate();
        $this->beginTransaction();
        $count = 0;
        try {
            $count = $this->table->save2($form);
            if ($count == 0) {
                $this->rollback();
                return 0;
            }
            $id         = $this->table->getLastInsertValue();
            $data['id'] = $id;
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        return $count;
    }

    public function updateQuery($uuid, $data)
    {
        $obj = $this->table->getByUuid($uuid, array());
        if (is_null($obj)) {
            return 0;
        }
        if (!isset($data['version'])) {
            throw new Exception("Version is not specified, please specify the version");
        }
        $form = new Query();
        if (isset($data['datasource_id'])) {
            $data['datasource_id'] = $this->getIdFromUuid('ox_datasource', $data['datasource_id']);
        }

        $form->exchangeWithSpecificKey($obj->toArray(), 'value');
        $form->exchangeWithSpecificKey($data, 'value', true);
        $form->updateValidate();
        $count = 0;
        try {
            $count = $this->table->save2($form);
            if ($count == 0) {
                $this->rollback();
                return 0;
            }
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        return $count;
    }

    public function deleteQuery($uuid, $version)
    {
        $obj = $this->table->getByUuid($uuid, array());
        if (is_null($obj)) {
            return 0;
        }
        if (!isset($version)) {
            throw new Exception("Version is not specified, please specify the version");
        }
        $data = array('version' => $version, 'isdeleted' => 1);
        $form = new Query();
        $form->exchangeWithSpecificKey($obj->toArray(), 'value');
        $form->exchangeWithSpecificKey($data, 'value', true);
        $form->updateValidate();
        $count = 0;
        try {
            $count = $this->table->save2($form);
            if ($count == 0) {
                $this->rollback();
                return 0;
            }
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        return $count;
    }

    public function getQuery($uuid, $params)
    {
        $query       = 'select q.uuid, q.name, q.configuration, q.ispublic, if(q.created_by=:created_by, true, false) as is_owner, q.isdeleted, q.version, d.uuid as datasource_uuid, d.name as datasource_name from ox_query q join ox_datasource d on d.id=q.datasource_id where q.isdeleted=false and q.org_id=:org_id and q.uuid=:uuid and (q.ispublic=true or q.created_by=:created_by)';
        $queryParams = [
            'created_by' => AuthContext::get(AuthConstants::USER_ID),
            'org_id'     => AuthContext::get(AuthConstants::ORG_ID),
            'uuid'       => $uuid,
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
            $queryResult               = $this->runQuery($resultSet[0]['configuration'], $resultSet[0]['datasource_uuid']);
            $response['query']['data'] = $queryResult['data'];
        }
        return $response;
    }

    public function getQueryList($params = null)
    {
        $paginateOptions = FilterUtils::paginateLikeKendo($params, self::$queryFields);
        $where           = $paginateOptions['where'];
        if (isset($params['show_deleted']) && $params['show_deleted'] == true) {
            $where .= empty($where) ? "WHERE (q.org_id =" . AuthContext::get(AuthConstants::ORG_ID) . ") and (q.created_by = " . AuthContext::get(AuthConstants::USER_ID) . " OR q.ispublic = 1)" : " AND(q.org_id =" . AuthContext::get(AuthConstants::ORG_ID) . ") and (q.created_by = " . AuthContext::get(AuthConstants::USER_ID) . " OR q.ispublic = 1)";
        } else {
            $where .= empty($where) ? "WHERE q.isdeleted <> 1 AND (q.org_id =" . AuthContext::get(AuthConstants::ORG_ID) . ") and (q.created_by = " . AuthContext::get(AuthConstants::USER_ID) . " OR q.ispublic = 1)" : " AND q.isdeleted <> 1 AND(q.org_id =" . AuthContext::get(AuthConstants::ORG_ID) . ") and (q.created_by = " . AuthContext::get(AuthConstants::USER_ID) . " OR q.ispublic = 1)";
        }
        $sort  = $paginateOptions['sort'] ? " ORDER BY " . $paginateOptions['sort'] : '';
        $limit = " LIMIT " . $paginateOptions['pageSize'] . " offset " . $paginateOptions['offset'];

        $cntQuery  = "SELECT count(id) as 'count' FROM `ox_query` as q ";
        $resultSet = $this->executeQuerywithParams($cntQuery . $where);
        $count     = $resultSet->toArray()[0]['count'];

        if (isset($params['show_deleted']) && $params['show_deleted'] == true) {
            $query = "SELECT q.uuid,q.name,d.uuid as datasource_uuid,q.configuration,q.ispublic,IF(q.created_by = " . AuthContext::get(AuthConstants::USER_ID) . ", 'true', 'false') as is_owner,q.version,q.org_id,q.isdeleted FROM `ox_query` as q inner join ox_datasource as d on q.datasource_id = d.id " . $where . " " . $sort . " " . $limit;
        } else {
            $query = "SELECT q.uuid,q.name,datasource_id,q.configuration,q.ispublic,IF(q.created_by = " . AuthContext::get(AuthConstants::USER_ID) . ", 'true', 'false') as is_owner,q.version,q.org_id FROM `ox_query` as q inner join ox_datasource as d on q.datasource_id = d.id " . $where . " " . $sort . " " . $limit;
        }
        $resultSet = $this->executeQuerywithParams($query);
        $result    = $resultSet->toArray();
        foreach ($result as $key => $value) {
            $result[$key]['configuration'] = json_decode($result[$key]['configuration']);
            unset($result[$key]['id']);
        }
        return array('data' => $result,
            'total'             => $count);
    }

    public function getQueryJson($uuid)
    {
        $statement = "Select configuration as query from ox_query where isdeleted <> 1 AND uuid = '" . $uuid . "'";
        $resultSet = $this->executeQuerywithParams($statement);
        $result    = $resultSet->toArray();
        if ($result) {
            return $result[0];
        } else {
            return 0;
        }

    }

    public function executeAnalyticsQuery($uuid, $overRides = null)
    {
        $query       = 'select q.uuid, q.name, q.configuration, q.ispublic, q.isdeleted, d.uuid as datasource_uuid from ox_query q join ox_datasource d on d.id=q.datasource_id where q.isdeleted=false and q.org_id=:org_id and q.uuid=:uuid';
        $queryParams = [
            'org_id' => AuthContext::get(AuthConstants::ORG_ID),
            'uuid'   => $uuid,
        ];
        //      try {
        $resultSet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
        if (count($resultSet) == 0) {
            return 0;
        }
        $configuration = $resultSet[0]['configuration'];
        $configArray   = json_decode($configuration, 1);
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
        $result        = $this->runQuery($configuration, $resultSet[0]['datasource_uuid'], $overRides);

        //      } catch(Exception $e) {
        //         return 0;
        //     }

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
        try {
            $result = $this->runQuery($configuration, $datasource_id);
        } catch (Exception $e) {
            $this->logger->error('Error in running the query');
            $this->logger->error($e);
            throw $e;
        }
        return $result['data'];
    }

    private function runQuery($configuration, $datasource_uuid, $overRides = null)
    {
        $analyticsEngine = $this->datasourceService->getAnalyticsEngine($datasource_uuid);
        $parameters      = json_decode($configuration, 1);
        if (!isset($parameters['inline_filter'])) {
            $parameters['inline_filter'] = [];
        }
        if (!empty($overRides)) {
            if (array_key_exists('filter', $overRides)) {
                if (!empty($overRides['filter'])) {
                    $filter                        = '{"filter":' . $overRides['filter'] . '}';
                    $filter                        = json_decode($filter, 1);
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
        $app_name = $parameters['app_name'];
        if (isset($parameters['entity_name'])) {
            $entity_name = $parameters['entity_name'];
        } else {
            $entity_name = null;
        }
        $result = $analyticsEngine->runQuery($app_name, $entity_name, $parameters);
        return $result;
    }

    public function queryData($rows)
    {
        if (array_key_exists('uuids', $rows)) {
            $data = $this->runMultipleQueries($rows['uuids']);
        } else {
            $errors              = array('message' => 'uuids is required');
            $validationException = new ValidationException();
            $validationException->setErrors($errors);
            throw $validationException;
        }
        return $data;
    }

    public function runMultipleQueries($uuidList, $overRides = null)
    {
        $aggCheck    = 0;
        $data        = array();
        $resultCount = count($uuidList);
        foreach ($uuidList as $key => $value) {
            $this->logger->info("Executing AnalyticsQuery with input -" . $value);
            $queryData = $this->executeAnalyticsQuery($value, $overRides);
            $this->logger->info("Executing AnalyticsQuery returned -" . print_r($queryData, true));
            if ($queryData == null || $queryData == 0) {
                throw new InvalidInputException("uuid entered is incorrect - $value", 1);
            }

            if ($key == 0) {
                if (!empty($queryData['meta']['aggregates'])) {
                    $aggCheck = 1;
                }
            }
            if (!empty($data) && isset($queryData['data']) && is_array($queryData['data'])) {
                if ($aggCheck == 1) {
                    if (!empty($queryData['meta']['aggregates'])) {
                        $data = array_replace_recursive($data, $queryData['data']);
                    } else {
                        throw new InvalidInputException("Aggregate query type cannot be followed by a non-aggregate query type", 1);
                    }

                } else {
                    if (!empty($queryData['meta']['aggregates'])) {
                        throw new InvalidInputException("Non-aggregate query type cannot be followed by a aggregate query type", 1);
                    } else {
                        $data = array_replace_recursive($data, $queryData['data']);
                    }

                }
            } else {
                if (isset($queryData['data'])) {
                    if (!is_array($queryData['data']) && $resultCount > 1) {
                        $data[0]['q' . strval($key + 1)] = $queryData['data'];
                    } else {
                        $data = $queryData['data'];
                    }
                }
            }
        }
        return $data;
    }
}
