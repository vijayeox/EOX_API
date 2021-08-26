<?php
namespace Analytics\Service;

use Analytics\Model\Dashboard;
use Analytics\Model\DashboardTable;
use Exception;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\Service\AbstractService;
use Oxzion\Utils\FilterUtils;
use Zend\Db\Exception\ExceptionInterface as ZendDbException;

class DashboardService extends AbstractService
{
    private $table;

    public function __construct($config, $dbAdapter, DashboardTable $table)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
    }

    public function createDashboard($data)
    {
        $dashboard = new Dashboard($this->table);
        $dashboard->assign(['account_id' => AuthContext::get(AuthConstants::ACCOUNT_ID)]);
        $dashboard->assign($data);

        $foundDefaultDashboard = false;
        try {
            //Check whether there is a default dashboard. If not, make this dashboard default one.
            $query = 'select id from ox_dashboard where isdefault = 1 and account_id=:account_id';
            $queryParams = [
                'account_id' => AuthContext::get(AuthConstants::ACCOUNT_ID),
            ];
            $check = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
            if (0 == count($check)) {
                $foundDefaultDashboard = false;
                $dashboard->assign(['isdefault' => 1]);
            } else {
                $foundDefaultDashboard = true;
            }
        } catch (Exception $e) {
            throw $e;
        }
        try {
            $this->beginTransaction();
            //If default dashboard already exists and if this dashboard has default set to TRUE, clear default setting of existing default dashboard.
            if ($foundDefaultDashboard && isset($data['isdefault']) && $data['isdefault']) {
                $query = 'update ox_dashboard set isdefault = 0 where isdefault = 1 and account_id=:account_id';
                $queryParams = [
                    'account_id' => AuthContext::get(AuthConstants::ACCOUNT_ID),
                ];
                $this->executeUpdateWithBindParameters($query, $queryParams);
            }
            $dashboard->save();
            $this->commit();
            return $dashboard->getGenerated();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function updateDashboard($uuid, $data)
    {
        $dashboard = new Dashboard($this->table);
        $dashboard->loadByUuid($uuid);
        $dashboard->assign($data);

        try {
            $this->beginTransaction();
            if (isset($data['isdefault']) && ($data['isdefault'] == 1)) {
                $query = 'Update ox_dashboard SET isdefault = 0 where isdefault = 1 and account_id=:account_id';
                $queryParams = [
                    'account_id' => AuthContext::get(AuthConstants::ACCOUNT_ID),
                ];
                $this->executeUpdateWithBindParameters($query, $queryParams);
            }
            $dashboard->save();
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        return [
            'dashboard' => [
                'version' => $dashboard->getProperty('version'),
                'data' => $data,
            ],
        ];
    }

    public function deleteDashboard($uuid, $version)
    {
        $dashboard = new Dashboard($this->table);
        $dashboard->loadByUuid($uuid);
        $dashboard->assign([
            'version' => $version,
            'isdeleted' => 1,
        ]);

        try {
            $this->beginTransaction();
            $dashboard->save();
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function getDashboard($uuid)
    {
        $query = 'select uuid, name, ispublic, description, dashboard_type, date_created, content, version, if(created_by=:created_by, true, false) as is_owner, isdeleted, filter_configuration, export_configuration from ox_dashboard where uuid=:uuid and (ispublic=true or created_by=:created_by) and isdeleted=false';
        $queryParams = [
            'created_by' => AuthContext::get(AuthConstants::USER_ID),
        //    'account_id' => AuthContext::get(AuthConstants::ACCOUNT_ID),  // Removing the dashboard filtering to allow different accounts
            'uuid' => $uuid,
        ];
        try {
            $resultSet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
            if (0 == count($resultSet)) {
                return 0;
            }
            return [
                'dashboard' => $resultSet[0],
            ];
        } catch (ZendDbException $e) {
            $this->logger->error('Database exception occurred.');
            $this->logger->error($e);
            return 0;
        }
    }

    public function getDashboardList($params = null)
    {
        $paginateOptions = FilterUtils::paginateLikeKendo($params);
        // print_r($params);exit;
        $where = $paginateOptions['where'];
        if (isset($params['show_deleted']) && $params['show_deleted'] == true) {
            $dashboardConditions = '(d.account_id = ' . AuthContext::get(AuthConstants::ACCOUNT_ID) . ') AND ((d.created_by =  ' . AuthContext::get(AuthConstants::USER_ID) . ') OR (d.ispublic = 1))';
        } else {
            $dashboardConditions = 'd.isdeleted <> 1 AND (d.account_id = ' . AuthContext::get(AuthConstants::ACCOUNT_ID) . ') AND ((d.created_by =  ' . AuthContext::get(AuthConstants::USER_ID) . ') OR (d.ispublic = 1))';
        }
        $where .= empty($where) ? "WHERE ${dashboardConditions}" : " AND ${dashboardConditions}";
        $sort = $paginateOptions['sort'] ? (' ORDER BY d.' . $paginateOptions['sort']) : '';
        if ($paginateOptions['pageSize'] != 0) {
            $limit = " LIMIT " . $paginateOptions['pageSize'] . " offset " . $paginateOptions['offset'];
        } else {
            $limit = " ";
        }

        $countQuery = "SELECT COUNT(id) as 'count' FROM ox_dashboard d ${where}";
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
            $query = 'SELECT d.uuid, d.name,d.version, d.ispublic, d.description, d.dashboard_type, IF(d.created_by = ' . AuthContext::get(AuthConstants::USER_ID) . ', true, false) AS is_owner, d.account_id, d.isdeleted, d.isdefault, d.filter_configuration, export_configuration from ox_dashboard d ' . $where . ' ' . $sort . ' ' . $limit;
        } else {
            $query = 'SELECT d.uuid, d.name,d.version, d.ispublic, d.description, d.dashboard_type, IF(d.created_by = ' . AuthContext::get(AuthConstants::USER_ID) . ', true, false) AS is_owner, d.account_id, d.isdefault, d.filter_configuration, export_configuration from ox_dashboard d ' . $where . ' ' . $sort . ' ' . $limit;
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
}
