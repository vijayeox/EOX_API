<?php
namespace Analytics\Service;

use Analytics\Model\Widget;
use Analytics\Model\WidgetTable;
use Analytics\Service\TemplateService as OITemplateService;
use Exception;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\Document\Template\Smarty\SmartyTemplateProcessorImpl;
use Oxzion\EntityNotFoundException;
use Oxzion\InsertFailedException;
use Oxzion\Service\AbstractService;
use Oxzion\Service\TemplateService;
use Oxzion\Utils\EvalMath;
use Oxzion\Utils\FilterUtils;
use Oxzion\ValidationException;
use Zend\Db\Exception\ExceptionInterface as ZendDbException;

class WidgetService extends AbstractService
{
    private $table;
    private $queryService;

    public function __construct($config, $dbAdapter, WidgetTable $table, $queryService)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
        $this->queryService = $queryService;
    }

    public function createWidget($data)
    {
        if (!isset($data['queries']) || empty($data['queries'])) {
            $ve = new ValidationException();
            $ve->setErrors(array('queries' => 'required'));
            throw $ve;
        }

        if (isset($data['configuration'])) {
            $data['configuration'] = json_encode($data['configuration']);
        }
        if (isset($data['expression'])) {
            $data['expression'] = json_encode($data['expression']);
        }

        $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        $widget = new Widget($this->table);
        $widget->assign($data);
        $widget->setForeignKey('account_id', $accountId);
        if (isset($data['visualization_uuid'])) {
            //TODO: Query visualization with account_id, ispublic and created_by filters to ensure current user has permission to read it.
            $visualizationId = $this->getIdFromUuid('ox_visualization', $data['visualization_uuid'], array('account_id' => $accountId));
            $widget->setForeignKey('visualization_id', $visualizationId);
        }

        try {
            $this->beginTransaction();
            $widget->save();
            $generated = $widget->getGenerated();

            //Insert the queries related to this widget.
            $widgetIdSelectionQuery = '(SELECT w.id FROM ox_widget w
                WHERE w.uuid=:widgetUuid and w.account_id=:account_id and (w.ispublic=true OR w.created_by=:created_by))';
            $queryIdSelectionQuery = '(SELECT q.id FROM ox_query q
                WHERE q.uuid=:queryUuid and q.account_id=:account_id and (q.ispublic=true OR q.created_by=:created_by))';
            $createdBy = AuthContext::get(AuthConstants::USER_ID);
            $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
            $sequence = 0;
            foreach ($data['queries'] as $query) {
                $queryUuid = $query['uuid'];
                if (isset($query['configuration'])) {
                    $queryConfiguration = json_encode($query['configuration']);
                } else {
                    $queryConfiguration = '';
                }
                $query = "INSERT INTO ox_widget_query (ox_widget_id, ox_query_id, sequence, configuration) VALUES
                            ($widgetIdSelectionQuery, $queryIdSelectionQuery, :sequence, :configuration)";
                $queryParams = [
                    'widgetUuid' => $generated['uuid'],
                    'queryUuid' => $queryUuid,
                    'sequence' => $sequence,
                    'configuration' => $queryConfiguration,
                    'created_by' => $createdBy,
                    'account_id' => $accountId,
                ];
                $result = $this->executeQueryWithBindParameters($query, $queryParams);
                if (1 != $result->count()) {
                    $this->logger->error('ox_widget_query insert failed.', $result);
                    $this->logger->error('Query and parameters are:');
                    $this->logger->error($query);
                    $this->logger->error($queryParams);
                    throw new InsertFailedException(
                        'Database insert failed.',
                        null,
                        InsertFailedException::ERR_CODE_INTERNAL_SERVER_ERROR,
                        InsertFailedException::ERR_TYPE_ERROR,
                        null
                    );
                }
                $sequence++;
            }
            $this->commit();
            return $generated;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function updateWidget($uuid, &$data)
    {
        if (!isset($data['queries']) || empty($data['queries'])) {
            $errors = new ValidationException();
            $errors->setErrors(array('queries' => 'required'));
            throw $errors;
        }

        if ($uuid != $data['uuid']) {
            throw new Exception("UUID mismatch in URL and the request parameter.");
        }

        $query = 'SELECT w.id from ox_widget as w where w.uuid=:uuid and w.account_id=:account_id and (w.ispublic=true OR w.created_by=:created_by)';
        $queryParams = [
            'created_by' => AuthContext::get(AuthConstants::USER_ID),
            'account_id' => AuthContext::get(AuthConstants::ACCOUNT_ID),
            'uuid' => $uuid,
        ];
        $resultSet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
        if (0 == count($resultSet)) {
            throw new Exception("Given wiget id ${uuid} either does not exist OR user has no permission to access the widget.");
        }

        $widget = new Widget($this->table);
        $widget->loadByUuid($uuid);

        if (isset($data['visualization_uuid'])) {
            //TODO: Query visualization with account_id, ispublic and created_by filters to ensure current user has permission to read it.
            $visualizationId = $this->getIdFromUuid('ox_visualization', $data['visualization_uuid'], array('account_id' => $data['account_id']));
            $widget->setForeignKey('visualization_id', $visualizationId);
        }
        if (is_array($data['configuration'])) {
            $data['configuration'] = json_encode($data['configuration']);
        }
        if (is_array($data['expression'])) {
            $data['expression'] = json_encode($data['expression']);
        }
        $widget->assign($data);

        try {
            $this->beginTransaction();
            $widget->save();

            $query = 'DELETE FROM ox_widget_query WHERE ox_widget_id = (SELECT id FROM ox_widget WHERE uuid = :uuid)';
            $queryParams = [
                'uuid' => $uuid,
            ];
            $result = $this->executeQueryWithBindParameters($query, $queryParams);

            $sequence = 0;
            foreach ($data['queries'] as $query) {
                $queryUuid = $query['uuid'];
                if (isset($query['configuration'])) {
                    $queryConfiguration = json_encode($query['configuration']);
                } else {
                    $queryConfiguration = '';
                }
                $query = 'INSERT INTO ox_widget_query (ox_widget_id, ox_query_id, sequence, configuration) VALUES ((SELECT w.id FROM ox_widget w WHERE uuid=:widgetUuid), (SELECT q.id FROM ox_query q WHERE q.uuid=:queryUuid and q.account_id=:account_id and (q.ispublic=true OR q.created_by=:created_by)), :sequence, :configuration)';
                $queryParams = [
                    'widgetUuid' => $data['uuid'],
                    'queryUuid' => $queryUuid,
                    'sequence' => $sequence,
                    'configuration' => $queryConfiguration,
                    'created_by' => AuthContext::get(AuthConstants::USER_ID),
                    'account_id' => AuthContext::get(AuthConstants::ACCOUNT_ID),
                ];
                $result = $this->executeQueryWithBindParameters($query, $queryParams);
                if (1 != $result->count()) {
                    $this->rollback();
                    throw new InsertFailedException(
                        'ox_widget_query insert failed.',
                        ['table' => 'ox_widget_query', 'query' => $query, 'queryParams' => $queryParams]
                    );
                }
                $sequence++;
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }

        return $widget->getGenerated();
    }

    public function deleteWidget($uuid, $version)
    {
        $widget = new Widget($this->table);
        $widget->loadByUuid($uuid);
        $widget->assign([
            'version' => $version,
            'isdeleted' => 1,
        ]);

        try {
            $this->beginTransaction();
            $widget->save();
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function getWidgetByName($name)
    {
        $query = 'SELECT w.uuid, w.ispublic, w.created_by, w.date_created, w.name, w.configuration, IF(w.created_by=:created_by, true, false) AS is_owner, w.version,v.renderer, v.type FROM ox_widget w JOIN ox_visualization v ON w.visualization_id=v.id WHERE w.isdeleted=false AND w.account_id=:account_id AND w.name=:name AND (w.ispublic=true OR w.created_by=:created_by)';
        $queryParams = [
            'created_by' => AuthContext::get(AuthConstants::USER_ID),
            'account_id' => AuthContext::get(AuthConstants::ACCOUNT_ID),
            'name' => $name,
        ];
        try {
            $resultSet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
            if (count($resultSet) == 0) {
                return 0;
            }
            $response = [
                'widget' => $resultSet[0],
            ];
            //Widget configuration value from database is a JSON string. Convert it to object and overwrite JSON string value.
            $response['widget']['configuration'] = json_decode($resultSet[0]["configuration"]);
        } catch (ZendDbException $e) {
            $this->logger->error('Database exception occurred.');
            $this->logger->error($e);
            $this->logger->error('Query and params:');
            $this->logger->error($query);
            $this->logger->error($queryParams);
            return 0;
        }
        return $response;
    }

    public function getWidget($uuid, $params)
    {
        $overRides = [];
        if (!isset($params['data'])) {
            $query = 'SELECT w.uuid, w.ispublic, w.date_created, w.name, w.configuration, w.expression, w.exclude_overrides, IF(w.created_by=:created_by, true, false) AS is_owner, w.version,v.renderer, v.type, q.uuid AS query_uuid, wq.sequence AS query_sequence, wq.configuration AS query_configuration FROM ox_widget w JOIN ox_visualization v on w.visualization_id=v.id JOIN ox_widget_query wq ON w.id=wq.ox_widget_id JOIN ox_query q ON wq.ox_query_id=q.id WHERE w.isdeleted=false and w.account_id=:account_id and w.uuid=:uuid AND (w.ispublic=true OR w.created_by=:created_by) ORDER BY wq.sequence ASC';
            $queryParams['account_id'] = AuthContext::get(AuthConstants::ACCOUNT_ID);
        } else {
            $query = 'SELECT w.uuid, w.ispublic, w.date_created, w.name, w.configuration, w.expression, w.exclude_overrides, IF(w.created_by=:created_by, true, false) AS is_owner, w.version,v.renderer, v.type, q.uuid AS query_uuid, wq.sequence AS query_sequence, wq.configuration AS query_configuration FROM ox_widget w JOIN ox_visualization v on w.visualization_id=v.id JOIN ox_widget_query wq ON w.id=wq.ox_widget_id JOIN ox_query q ON wq.ox_query_id=q.id WHERE w.isdeleted=false and w.uuid=:uuid AND (w.ispublic=true OR w.created_by=:created_by) ORDER BY wq.sequence ASC';
        }
        $queryParams['created_by'] = AuthContext::get(AuthConstants::USER_ID);
        $queryParams['uuid'] = $uuid;
        try {
            $resultSet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
            if (count($resultSet) == 0) {
                return 0;
            }
            $queries = [];
            foreach ($resultSet as $row) {
                $configuration = json_decode($row['query_configuration'], 1);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $configuration = null;
                }
                array_push($queries, [
                    'uuid' => $row['query_uuid'],
                    'sequence' => $row['query_sequence'],
                    'configuration' => $configuration,
                ]);
                if (!empty($configuration)) {
                    $overRides[$row['query_uuid']] = $configuration;
                }
            }
            $firstRow = $resultSet[0];
            $widget = [
                'uuid' => $firstRow['uuid'],
                'ispublic' => $firstRow['ispublic'],
                'date_created' => $firstRow['date_created'],
                'name' => $firstRow['name'],
                'configuration' => json_decode($firstRow['configuration']),
                'expression' => json_decode($firstRow['expression'], 1),
                'is_owner' => $firstRow['is_owner'],
                'renderer' => $firstRow['renderer'],
                'type' => $firstRow['type'],
                'version' => $firstRow['version'],
                'exclude_overrides' => $firstRow['exclude_overrides'],
                'queries' => $queries,
            ];
            $response = [
                'widget' => $widget,
            ];
            //Widget configuration value from database is a JSON string. Convert it to object and overwrite JSON string value.
            //$response['widget']['configuration'] = json_decode($resultSet[0]['configuration'],1);
        } catch (ZendDbException $e) {
            $this->logger->error('Database exception occurred.');
            $this->logger->error($e);
            $this->logger->error('Query and params:');
            $this->logger->error($query);
            $this->logger->error($queryParams);
            return 0;
        } catch (Exception $e) {
            throw $e;
        }
        $data = array();
        $uuidList = array_column($resultSet, 'query_uuid');
        $filter = null;
        $overRidesAllowed = ['group', 'sort', 'field', 'date-period', 'date-range', 'filter', 'expression', 'round', 'pivot', 'skip', 'top', 'filter_grid', 'orderby', 'use_participants'];
        if (!empty($firstRow['exclude_overrides'])) {
            if (strtolower($firstRow['exclude_overrides'] == 'all')) {
                unset($overRidesAllowed[array_search('filter', $overRidesAllowed)]);
            } else {
                $overRides['excludes'] = explode(',', $firstRow['exclude_overrides']);
            }
        }

        if (isset($params['data'])) {
            if ($firstRow['renderer'] == 'jsGrid') {
                $config = json_decode($firstRow['configuration'], 1);
                if (isset($config['pageSize'])) {
                    $overRides['pagesize'] = $config['pageSize'];
                }
                if (isset($config['column'])) {
                    $columns = array();
                    foreach ($config['column'] as $column) {
                        if (isset($column['type'])) {
                            $columns[$column['field']] = $column['type'];
                        } else {
                            $columns[$column['field']] = 'string';
                        }

                    }
                    $overRides['columns'] = $columns;
                }
                if (isset($config['sort'])) {
                    $overRides['orderby'] = $config['sort'][0]['field'] . ' ' . $config['sort'][0]['dir'];
                }
            }
            foreach ($overRidesAllowed as $overRidesKey) {
                if (isset($params[$overRidesKey])) {
                    $overRides[$overRidesKey] = $params[$overRidesKey];
                }
            }
            $donotcombine = 0;
            $template = null;
            if (strtoupper(($firstRow['type'])) == 'HTML') {
                $config = json_decode($firstRow['configuration'], 1);
                if (isset($config['template'])) {
                    if (!empty($config['donotcombine'])) {
                        $donotcombine = 1;
                        $data = $this->queryService->runMultipleQueriesWithoutCombine($uuidList, $overRides);
                    }
                    $template = $config['template'];
                }
            }
            if (!$donotcombine) {
                $data = $this->queryService->runMultipleQueries($uuidList, $overRides);
            }
            if ($this->queryService->getTotalCount()) {
                $response['widget']['total_count'] = $this->queryService->getTotalCount();
            }
            if (isset($response['widget']['expression']['expression'])) {
                $expressions = $response['widget']['expression']['expression'];
                if (!is_array($expressions)) {
                    $expressions = array($expressions);
                }
                foreach ($expressions as $expression) {
                    $data = $this->evaluteExpression($data, $expression);
                }
            }

            if (isset($data[0]['calculated']) && count($data) == 1) {
                //Send only the calculated value if left oprand not specified and aggregate values
                $data = $data[0]['calculated'];
            }
            if (is_array($data)) {
                $data = $this->getTargets($uuid, $data, 1);
            } else {
                $targets = $this->getTargets($uuid, $data, 0);
                if ($targets) {
                    $response['widget']['targets'] = $targets;
                }
            }
            $response['widget']['data'] = $data;
            if ($template) {
                $response['widget']['data'] = $this->applyOITemplate($response['widget'], $template);
            }
        }
        return $response;
    }

    public function applyTemplate($resultData, $templateName)
    {
        $templateEngine = new TemplateService($this->config, $this->dbAdapter);
        $templateEngine->init();
        $result = $templateEngine->getContent($templateName, $resultData);
        $result = str_replace(array("\r\n", "\r", "\n", "\t"), '', $result);
        return $result;
    }

    public function getWidgetTableData($uuid)
    {
        $sql = $this->getSqlObject();
        $select = $sql->select();
        $fieldList = array("id", "uuid", "visualization_id", "ispublic", "created_by", "date_created", "account_id", "isdeleted", "name", "configuration", "version", "expression", "exclude_overrides");
        $select->from('ox_widget')
            ->columns($fieldList)
            ->where(array('ox_widget.uuid' => $uuid, 'account_id' => AuthContext::get(AuthConstants::ACCOUNT_ID), 'isdeleted' => 0));
        $response = $this->executeQuery($select)->toArray();
        if (count($response) == 0) {
            return 0;
        }
        return $response[0];
    }

    public function getTargets($uuid, $data, $isaggregate)
    {
        $query = 'SELECT wt.group_key, wt.group_value, t.type, t.red_limit, t.yellow_limit, t.green_limit
            FROM ox_widget w
            JOIN ox_widget_target wt on w.id=wt.widget_id
            JOIN ox_target t ON t.id=wt.target_id
            WHERE w.uuid=:uuid';
        $queryParams = [
            'uuid' => $uuid,
        ];
        try {
            $resultSet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
            if (count($resultSet) == 0) {
                if ($isaggregate) {
                    return $data;
                } else {
                    return null;
                }
            }
            if ($isaggregate) {
                if (count($resultSet) > 1) {
                    $targets = [];
                    foreach ($resultSet as $row) {
                        $targets[$row['group_value']]['red_limit'] = $row['red_limit'];
                        $targets[$row['group_value']]['yellow_limit'] = $row['yellow_limit'];
                        $targets[$row['group_value']]['green_limit'] = $row['green_limit'];
                    }
                }
                if ((count($data) > 0)) {
                    $cols = array_keys($data[0]);
                    $group_key = $cols[0];
                    $i = 0;
                    foreach ($data as $key1 => $dataset) {
                        if (count($resultSet) > 1) {
                            $keyvalue = $group_key . "_" . $i;
                            if (isset($targets[$keyvalue])) {
                                $data[$key1]['red_limit'] = $targets[$keyvalue]['red_limit'];
                                $data[$key1]['yellow_limit'] = $targets[$keyvalue]['yellow_limit'];
                                $data[$key1]['green_limit'] = $targets[$keyvalue]['green_limit'];
                            }
                        } else {
                            $data[$key1]['red_limit'] = $resultSet[0]['red_limit'];
                            $data[$key1]['yellow_limit'] = $resultSet[0]['yellow_limit'];
                            $data[$key1]['green_limit'] = $resultSet[0]['green_limit'];
                        }
                        $i++;
                    }
                }
                return $data;
            } else {
                $color = TargetService::checkRYG($data, $resultSet[0]['type'], $resultSet[0]['red_limit'], $resultSet[0]['yellow_limit'], $resultSet[0]['green_limit']);
                return ['red_limit' => $resultSet[0]['red_limit'], 'yellow_limit' => $resultSet[0]['yellow_limit'], 'green_limit' => $resultSet[0]['green_limit'], 'color' => $color];
            }
        } catch (Exception $e) {
            if ($isaggregate) {
                return $data;
            } else {
                return null;
            }
        }
    }

    public function evaluteExpression($data, $expression)
    {
        $expArray = explode("=", $expression, 2);
        if (count($expArray) == 2) {
            $colName = $expArray[0];
            $expression = $expArray[1];
        } else {
            $colName = 'calculated';
        }
        $expression = strtolower($expression);
        foreach ($data as $key1 => $dataset) {
            $m = new EvalMath;
            $m->suppress_errors = true;
            $m->evaluate('round(x,y) = (((x*(10^y))+0.5*(abs(x)/(x+0^abs(x))))%(10^10))/(10^y)');
            foreach ($dataset as $key2 => $value) {
                if (is_numeric($value)) {
                    $key2 = strtolower($key2);
                    $m->evaluate("$key2 = $value");
                } else {
                    $m->evaluate("$key2 = 0");
                }
            }
            $calculated = $m->evaluate($expression);
            if ($calculated == 'false' || $calculated == '') {
                $calculated = 0;
            }
            $data[$key1][$colName] = $calculated;
        }
        return $data;
    }

    public function getWidgetList($params = null)
    {
        $paginateOptions = FilterUtils::paginateLikeKendo($params);
        $where = $paginateOptions['where'];
        if (isset($params['show_deleted']) && $params['show_deleted'] == true) {
            $widgetConditions = '(w.account_id = ' . AuthContext::get(AuthConstants::ACCOUNT_ID) . ') AND ((w.created_by =  ' . AuthContext::get(AuthConstants::USER_ID) . ') OR (w.ispublic = 1))';
        } else {
            $widgetConditions = '(w.isdeleted <> 1) AND (w.account_id = ' . AuthContext::get(AuthConstants::ACCOUNT_ID) . ') AND ((w.created_by =  ' . AuthContext::get(AuthConstants::USER_ID) . ') OR (w.ispublic = 1))';
        }
        $where .= empty($where) ? "WHERE ${widgetConditions}" : " AND ${widgetConditions}";
        $sort = $paginateOptions['sort'] ? (' ORDER BY w.' . $paginateOptions['sort']) : '';
        $limit = ' LIMIT ' . $paginateOptions['pageSize'] . ' OFFSET ' . $paginateOptions['offset'];
        $countQuery = "SELECT COUNT(id) as 'count' FROM ox_widget w ${where}";
        try {
            $resultSet = $this->executeQuerywithParams($countQuery);
        } catch (ZendDbException $e) {
            $this->logger->error('Database exception occurred. Query:');
            $this->logger->error($countQuery);
            $this->logger->error($e);
            return 0;
        }
        $count = $resultSet->toArray()[0]['count'];
        if (isset($params['show_deleted']) && $params['show_deleted'] == true) {
            $query = 'SELECT w.name, w.uuid, w.version,IF(w.created_by = ' . AuthContext::get(AuthConstants::USER_ID) . ', true, false) AS is_owner, w.ispublic, w.isdeleted, v.type, v.renderer FROM ox_widget w JOIN ox_visualization v ON w.visualization_id = v.id ' . $where . ' ' . $sort . ' ' . $limit;
        } else {
            $query = 'SELECT w.name, w.uuid, w.version,IF(w.created_by = ' . AuthContext::get(AuthConstants::USER_ID) . ', true, false) AS is_owner, w.ispublic, v.type, v.renderer FROM ox_widget w JOIN ox_visualization v ON w.visualization_id = v.id ' . $where . ' ' . $sort . ' ' . $limit;
        }
        try {
            $resultSet = $this->executeQuerywithParams($query);
        } catch (ZendDbException $e) {
            $this->logger->error('Database exception occurred. Query:');
            $this->logger->error($query);
            $this->logger->error($e);
            return 0;
        }
        $result = $resultSet->toArray();
        return array('data' => $result,
            'total' => $count);
    }

    public function copyWidget($params)
    {
        if (!isset($params['queries']) || empty($params['queries'])) {
            throw new Exception('Widget must have at least one query.');
        }

        $widgetUuid = $params['widgetUuid'];
        $query = 'SELECT w.id, w.name, w.visualization_id, w.ispublic, w.configuration, w.expression from ox_widget as w where w.uuid=:uuid and w.account_id=:account_id and (w.ispublic=true OR w.created_by=:created_by)';
        $queryParams = [
            'created_by' => AuthContext::get(AuthConstants::USER_ID),
            'account_id' => AuthContext::get(AuthConstants::ACCOUNT_ID),
            'uuid' => $widgetUuid,
        ];
        try {
            $this->logger->info("Executing query - $query with params - " . json_encode($queryParams));
            $resultGet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
            if (count($resultGet) == 0) {
                throw new EntityNotFoundException(
                    "Wiget id ${widgetUuid} either does not exist OR user has no read permission to the entity.",
                    ['entity' => 'ox_widget', 'uuid' => $widgetUuid]
                );
            }
            $firstRow = $resultGet[0];
        } catch (ZendDbException $e) {
            $this->logger->error('Database exception occurred.');
            $this->logger->error('Query and params:');
            $this->logger->error($query);
            $this->logger->error($queryParams);
            throw $e;
        }

        $widget = [
            'ispublic' => $firstRow['ispublic'],
            'visualization_id' => $firstRow['visualization_id'],
            'name' => isset($params['name']) ? $params['name'] : $firstRow['name'] . '_copy_' . date('Y-m-d H:i:s'),
            'configuration' => isset($params['configuration']) ? $params['configuration'] : $firstRow['configuration'],
            'expression' => isset($params['expression']) ? $params['expression'] : $firstRow['expression'],
            'queries' => $params['queries'],
        ];

        try {
            $resultCreate = $this->createWidget($widget);
            return $resultCreate;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function previewWidget($params)
    {
        $uuidList = array();
        if (isset($params['queries'])) {
            $queries = json_decode($params['queries'], 1);
            if (!is_array($queries['queries'])) {
                $uuidList = array($queries['queries']);
            } else {
                $uuidList = $queries['queries'];
            }
        }
        $donotcombine = 0;
        $template = null;
        if (isset($params['configuration'])) {
            $config = json_decode($params['configuration'], 1);
        }
        if (isset($config['template'])) {
            if (!empty($config['donotcombine'])) {
                $donotcombine = 1;
                $data = $this->queryService->runMultipleQueriesWithoutCombine($uuidList, null);
            }
            $template = $config['template'];
        }
        if (!$donotcombine) {
            $data = $this->queryService->runMultipleQueries($uuidList, null);
        }
        if ($this->queryService->getTotalCount()) {
            $response['widget']['total_count'] = $this->queryService->getTotalCount();
        }
        if (isset($params['expression'])) {
            $expressionTmp = json_decode($params['expression'], 1);
            $expression = $expressionTmp['expression'];
            if (!is_array($expression)) {
                $expressions = array($expression);
            }
            foreach ($expressions as $expression) {
                $data = $this->evaluteExpression($data, $expression);
            }
        }

        if (isset($data[0]['calculated']) && count($data) == 1) {
            //Send only the calculated value if left oprand not specified and aggregate values
            $data = $data[0]['calculated'];
        }
        $response['widget']['data'] = $data;
        if ($template) {
            $response['widget']['data'] = $this->applyOITemplate($response['widget'], $template);
        }
        return $response;
    }

    public function applyOITemplate($resultData, $templateName)
    {
        $client = new SmartyTemplateProcessorImpl();
        $OITemplateService = new OITemplateService($this->config, $this->dbAdapter); //OI template class declaration
        $TemplateService = new TemplateService($this->config, $this->dbAdapter); //OXZion relate template class declaration to get the list of all the template related folder
        $templateDir = $this->config['TEMPLATE_FOLDER']; // Setting up the default template folder
        $template = $OITemplateService->getTemplatePath($templateName . ".tpl"); // function call to get the OITemplate directory and the template name
        $templateParams = $TemplateService->getTemplateFolderList($templateDir); // Get the list of all the template folders and initialize them
        //TODO We need to refactor this, we dont have to initialize all the folders at once to initialize a single type of template
        $client->init($templateParams);
        $content = $client->getContent($template, $resultData, null);
        $result = str_replace(array("\r\n", "\r", "\n", "\t"), '', $content);
        return $result;
    }
}