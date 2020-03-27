<?php
namespace Analytics\Service;

use Oxzion\Service\AbstractService;
use Analytics\Model\WidgetTable;
use Analytics\Model\Widget;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Oxzion\ValidationException;
use Oxzion\Utils\FilterUtils;
use Oxzion\Analytics\AnalyticsEngine;
use Ramsey\Uuid\Uuid;
use Exception;
use Zend\Db\Exception\ExceptionInterface as ZendDbException;
use Zend\Mvc\Application;
use Webit\Util\EvalMath\EvalMath;

class WidgetService extends AbstractService
{
    private $table;
    private $queryService;

    public function __construct($config, $dbAdapter, WidgetTable $table, $queryService)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
        $this->queryService  = $queryService;
    }

    public function createWidget($data)
    {
        if (!isset($data['queries']) || empty($data['queries'])) {
            $errors = new ValidationException();
            $errors->setErrors(array('queries' => 'required'));
            throw $errors;
        }

        if (isset($data['uuid'])) {
            $uuid = $data['uuid'];
            $query = 'SELECT w.id from ox_widget as w where w.uuid=:uuid and w.org_id=:org_id and (w.ispublic=true OR w.created_by=:created_by)';
            $queryParams = [
                'created_by' => AuthContext::get(AuthConstants::USER_ID),
                'org_id' => AuthContext::get(AuthConstants::ORG_ID),
                'uuid' => $uuid
            ];
            $resultSet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
            if (0 == count($resultSet)) {
                throw new Exception("Given wiget id ${uuid} either does not exist OR user has no permission to read the widget.");
            }
        }

        $form = new Widget();
        $data['created_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_created'] = date('Y-m-d H:i:s');
        $data['org_id'] = AuthContext::get(AuthConstants::ORG_ID);
        $data['uuid'] = Uuid::uuid4()->toString();
        if(isset($data['visualization_uuid'])){
            //TODO: Query visualization with org_id, ispublic and created_by filters to ensure current user has permission to read it.
            $data['visualization_id'] = $this->getIdFromUuid('ox_visualization', $data['visualization_uuid'], array('org_id' => $data['org_id']));
            unset($data['visualization_uuid']);
        }
        if(isset($data['configuration'])) {
            $data['configuration'] = json_encode($data['configuration']);
        }
        if(isset($data['expression'])) {
            $data['expression'] = json_encode($data['expression']);
        }
        $form->exchangeWithSpecificKey($data,'value');
        $form->validate();
        $this->beginTransaction();
        $count = 0;
        try {
            $count = $this->table->save2($form);
            if ($count == 0) {
                $this->rollback();
                return 0;
            }
            $id = $this->table->getLastInsertValue();
            $data['id'] = $id;
            $this->commit();
        } 
        catch (Exception $e) {
            $this->rollback();
            throw $e;
        }

            try {
                $sequence = 0;
                foreach($data['queries'] as $query) {
                    $queryUuid = $query['uuid'];
                    if(isset($query['configuration'])) {
                        $queryConfiguration = json_encode($query['configuration']);
                    }
                    else {
                        $queryConfiguration = '';
                    }
                    $query = 'INSERT INTO ox_widget_query (ox_widget_id, ox_query_id, sequence, configuration) VALUES ((SELECT w.id FROM ox_widget w WHERE uuid=:widgetUuid and w.org_id=:org_id and (w.ispublic=true OR w.created_by=:created_by)), (SELECT q.id FROM ox_query q WHERE q.uuid=:queryUuid and q.org_id=:org_id and (q.ispublic=true OR q.created_by=:created_by)), :sequence, :configuration)';
                    $queryParams = [
                        'widgetUuid' => $data['uuid'],
                        'queryUuid' => $queryUuid,
                        'sequence' => $sequence,
                        'configuration' => $queryConfiguration,
                        'created_by' => AuthContext::get(AuthConstants::USER_ID),
                        'org_id' => AuthContext::get(AuthConstants::ORG_ID),
                    ];
                    $this->logger->info('Executing query:');
                    $this->logger->info($query);
                    $this->logger->info($queryParams);
                    $result = $this->executeQueryWithBindParameters($query, $queryParams);
                    if (1 != $result->count()) {
                        $this->logger->error('Unexpected result from ox_widget_query insert statement. Transaction rolled back.', $result);
                        $this->logger->error('Query and parameters are:');
                        $this->logger->error($query);
                        $this->logger->error($queryParams);
                        $this->rollback();
                        return 0;
                    }
                    $sequence++;
                }
                $this->commit();
                return $data['uuid'];
            }
            catch (ZendDbException $e) {
                $this->logger->error('Database exception occurred.');
                $this->logger->error($e);
                $this->logger->error("Query and params:");
                $this->logger->error($query);
                $this->logger->error($queryParams);
                try {
                    $this->rollback();
                }
                catch (ZendDbException $ee) {
                    $this->logger->error('Database exception occurred when rolling back transaction.');
                    $this->logger->error($ee);
                }
                return 0;
            }
    }

    //DO NOT ADD THIS AT IS NOT NEEDED. LEAVING THIS HERE IN CASE THE REQUIREMENT CHANGES
    //-----------------------------------------------------------------------------------
    // - BRIAN

    /*public function updateWidget($uuid, $data)
    {
        $obj = $this->table->getByUuid($uuid, array());
        if (is_null($obj)) {
            return 0;
        }
        if(!isset($data['version']))
        {
            throw new Exception("Version is not specified, please specify the version");
        }
        $form = new Widget();
        $form->exchangeWithSpecificKey($obj->toArray(), 'value');
        $form->exchangeWithSpecificKey($data,'value',true);
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
    }*/

    public function deleteWidget($uuid,$version)
    {
        $obj = $this->table->getByUuid($uuid, array());
        if (is_null($obj)) {
            return 0;
        }
        if(!isset($version))
        {
            throw new Exception("Version is not specified, please specify the version");
        }
        $data = array('version' => $version,'isdeleted' => 1);
        $form = new Widget();
        $form->exchangeWithSpecificKey($obj->toArray(), 'value');
        $form->exchangeWithSpecificKey($data,'value',true);
        $form->updateValidate($data);
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

    public function getWidgetByName($name) {
        $query = 'SELECT w.uuid, w.ispublic, w.created_by, w.date_created, w.name, w.configuration, IF(w.created_by=:created_by, true, false) AS is_owner, w.version,v.renderer, v.type FROM ox_widget w JOIN ox_visualization v ON w.visualization_id=v.id WHERE w.isdeleted=false AND w.org_id=:org_id AND w.name=:name AND (w.ispublic=true OR w.created_by=:created_by)';
        $queryParams = [
            'created_by' => AuthContext::get(AuthConstants::USER_ID),
            'org_id' => AuthContext::get(AuthConstants::ORG_ID),
            'name' => $name
        ];
        try {
            $resultSet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
            if (count($resultSet) == 0) {
                return 0;
            }
            $response = [
                'widget' => $resultSet[0]
            ];
            //Widget configuration value from database is a JSON string. Convert it to object and overwrite JSON string value.
            $response['widget']['configuration'] = json_decode($resultSet[0]["configuration"]);
        }
        catch (ZendDbException $e) {
            $this->logger->error('Database exception occurred.');
            $this->logger->error($e);
            $this->logger->error('Query and params:');
            $this->logger->error($query);
            $this->logger->error($queryParams);
            return 0;
        }
        return $response;
    }

    public function getWidget($uuid,$params)
    {
        $overRides = [];
        $query = 'SELECT w.uuid, w.ispublic, w.date_created, w.name, w.configuration, w.expression, IF(w.created_by=:created_by, true, false) AS is_owner, w.version,v.renderer, v.type, q.uuid AS query_uuid, wq.sequence AS query_sequence, wq.configuration AS query_configuration FROM ox_widget w JOIN ox_visualization v on w.visualization_id=v.id JOIN ox_widget_query wq ON w.id=wq.ox_widget_id JOIN ox_query q ON wq.ox_query_id=q.id WHERE w.isdeleted=false and w.org_id=:org_id and w.uuid=:uuid AND (w.ispublic=true OR w.created_by=:created_by) ORDER BY wq.sequence ASC';
        $queryParams = [
            'created_by' => AuthContext::get(AuthConstants::USER_ID),
            'org_id' => AuthContext::get(AuthConstants::ORG_ID),
            'uuid' => $uuid
        ];
        try {
            $resultSet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
            if (count($resultSet) == 0) {
                return 0;
            }
            $queries = [];
            foreach($resultSet as $row) {
                $configuration = json_decode($row['query_configuration'],1);
                array_push($queries, [
                    'uuid' => $row['query_uuid'],
                    'sequence' => $row['query_sequence'],
                    'configuration' => $configuration
                ]);
                if (!empty($configuration)) {
                    $overRides[$row['query_uuid']]=$configuration;
                }
            }
            $firstRow = $resultSet[0];
            $widget = [
                'uuid' => $firstRow['uuid'],
                'ispublic' => $firstRow['ispublic'],
                'date_created' => $firstRow['date_created'],
                'name' => $firstRow['name'],
                'configuration' => json_decode($firstRow['configuration']),
                'expression' => json_decode($firstRow['expression'],1),
                'is_owner' => $firstRow['is_owner'],
                'renderer' => $firstRow['renderer'],
                'type' => $firstRow['type'],
                'version' => $firstRow['version'],
                'queries' => $queries
            ];
            $response = [
                'widget' => $widget
            ];
            //Widget configuration value from database is a JSON string. Convert it to object and overwrite JSON string value.
            //$response['widget']['configuration'] = json_decode($resultSet[0]['configuration'],1);
        }
        catch (ZendDbException $e) {
            $this->logger->error('Database exception occurred.');
            $this->logger->error($e);
            $this->logger->error('Query and params:');
            $this->logger->error($query);
            $this->logger->error($queryParams);
            return 0;
        }
        $data = array();
        $uuidList = array_column($resultSet, 'query_uuid');
        $filter = null;
        $overRidesAllowed = ['group','sort','field','date-period','date-range','filter','expression','round'];
        
        if(isset($params['data'])) {
            foreach($overRidesAllowed as $overRidesKey) {
                if (isset($params[$overRidesKey])) {
                    $overRides[$overRidesKey] = $params[$overRidesKey];
                }
            }
            $data = $this->queryService->runMultipleQueries($uuidList,$overRides);

            if (isset($response['widget']['expression']['expression'])) {
                $expressions = $response['widget']['expression']['expression'];
                if (!is_array($expressions)) {
                    $expressions = array($expressions);
                }
                foreach($expressions as $expression) {
                    $data = $this->evaluteExpression($data,$expression);
                }
            }
            $response['widget']['data'] = $data;
        }
        return $response;
    }


    public function evaluteExpression($data,$expression) {
        $expArray = explode("=",$expression,2);
        if (count($expArray)==2) {
            $colName =  $expArray[0];
            $expression = $expArray[1];
        } else {
            $colName = 'calculated';
        }
        foreach($data as $key1=>$dataset) {
            $m = new EvalMath;
            $m->suppress_errors = true;
            $m->evaluate('round(x,y) = (((x*(10^y))+0.5*(abs(x)/(x+0^abs(x))))%(10^10))/(10^y)');
            foreach($dataset as $key2=>$value) {
                if (is_numeric($value)) {
                    $m->evaluate("$key2 = $value");
                }
            }
            $calculated = $m->evaluate($expression);
            $data[$key1][$colName] = $calculated;
        }
        return $data;
    }

    public function getWidgetList($params = null)
    {
        $paginateOptions = FilterUtils::paginateLikeKendo($params);
        $where = $paginateOptions['where'];
        if(isset($params['show_deleted']) && $params['show_deleted']==true) {
            $widgetConditions = '(w.org_id = ' . AuthContext::get(AuthConstants::ORG_ID) . ') AND ((w.created_by =  ' . AuthContext::get(AuthConstants::USER_ID) . ') OR (w.ispublic = 1))';
        }
        else{
            $widgetConditions = '(w.isdeleted <> 1) AND (w.org_id = ' . AuthContext::get(AuthConstants::ORG_ID) . ') AND ((w.created_by =  ' . AuthContext::get(AuthConstants::USER_ID) . ') OR (w.ispublic = 1))';
        }
        $where .= empty($where) ? "WHERE ${widgetConditions}" : " AND ${widgetConditions}";
        $sort = $paginateOptions['sort'] ? (' ORDER BY w.' . $paginateOptions['sort']) : '';
        $limit = ' LIMIT ' . $paginateOptions['pageSize'] . ' OFFSET ' . $paginateOptions['offset'];

        $countQuery = "SELECT COUNT(id) as 'count' FROM ox_widget w ${where}";
        try {
            $resultSet = $this->executeQuerywithParams($countQuery);
        }
        catch (ZendDbException $e) {
            $this->logger->error('Database exception occurred. Query:');
            $this->logger->error($countQuery);
            $this->logger->error($e);
            return 0;
        }
        $count = $resultSet->toArray()[0]['count'];

        if(isset($params['show_deleted']) && $params['show_deleted']==true){
            $query ='SELECT w.name, w.uuid, w.version,IF(w.created_by = ' . AuthContext::get(AuthConstants::USER_ID) . ', true, false) AS is_owner, w.ispublic, w.isdeleted, v.type, v.renderer FROM ox_widget w JOIN ox_visualization v ON w.visualization_id = v.id ' . $where. ' ' . $sort . ' ' . $limit;
        }
        else{
            $query ='SELECT w.name, w.uuid, w.version,IF(w.created_by = ' . AuthContext::get(AuthConstants::USER_ID) . ', true, false) AS is_owner, w.ispublic, v.type, v.renderer FROM ox_widget w JOIN ox_visualization v ON w.visualization_id = v.id ' . $where. ' ' . $sort . ' ' . $limit;
        }
        try {
            $resultSet = $this->executeQuerywithParams($query);
        }
        catch (ZendDbException $e) {
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
        $query = 'SELECT w.id, w.name, w.visualization_id, w.ispublic, w.configuration, w.expression from ox_widget as w where w.uuid=:uuid and w.org_id=:org_id and (w.ispublic=true OR w.created_by=:created_by)';
        $queryParams = [
            'created_by' => AuthContext::get(AuthConstants::USER_ID),
            'org_id' => AuthContext::get(AuthConstants::ORG_ID),
            'uuid' => $widgetUuid
        ];
        try {
            $resultGet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
            if (count($resultGet) == 0) {
                throw new Exception("Given wiget id ${widgetUuid} either does not exist OR user has no permission to read the widget.");
            }
            $firstRow = $resultGet[0];
        }
        catch (ZendDbException $e) {
            $this->logger->error('Database exception occurred.');
            $this->logger->error('Query and params:');
            $this->logger->error($query);
            $this->logger->error($queryParams);
            throw $e;
        }

        $widget = [
            'uuid'             => $widgetUuid,
            'ispublic'         => $firstRow['ispublic'],
            'visualization_id' => $firstRow['visualization_id'],
            'name'             => isset($params['name']) ? $params['name'] : $firstRow['name'] . '_copy_' . date('Y-m-d H:i:s'),
            'configuration'    => isset($params['configuration']) ? $params['configuration'] : $firstRow['configuration'],
            'expression'       => isset($params['expression']) ? $params['expression'] : $firstRow['expression'],
            'queries'          => $params['queries']
        ];

        try {
            $resultCreate = $this->createWidget($widget);
            return $resultCreate;
        }
        catch(Exception $e){
            throw $e;
        }
    }
}

