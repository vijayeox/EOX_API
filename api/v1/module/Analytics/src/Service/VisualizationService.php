<?php
namespace Analytics\Service;

use Oxzion\Service\AbstractService;
use Analytics\Model\VisualizationTable;
use Analytics\Model\Visualization;
use Oxzion\ValidationException;
use Zend\Db\Sql\Expression;
use Oxzion\Utils\FilterUtils;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Exception;

class VisualizationService extends AbstractService
{
    private $table;

    public function __construct($config, $dbAdapter, VisualizationTable $table)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
    }

    public function createVisualization($data)
    {
        $visualization = new Visualization($this->table);
        $visualization->assign($data);
        $visualization->setForeignKey('account_id', AuthContext::get(AuthConstants::ACCOUNT_ID)); //When account_id is defined as readonly in the model.
        try {
            $this->beginTransaction();
            $visualization->save();
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        return $visualization->getGenerated();
    }

    public function updateVisualization($uuid, $data)
    {
        $visualization = new Visualization($this->table);
        $visualization->loadByUuid($uuid);
        $visualization->assign($data);
        try {
            $this->beginTransaction();
            $visualization->save();
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        return $visualization->getGenerated();
    }

    public function deleteVisualization($uuid, $version)
    {
        $visualization = new Visualization($this->table);
        $visualization->loadByUuid($uuid);
        $visualization->assign([
            'version' => $version,
            'isdeleted' => 1
        ]);
        try {
            $this->beginTransaction();
            $visualization->save();
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function getVisualization($uuid)
    {
        $sql = $this->getSqlObject();
        $select = $sql->select();
        $select->from('ox_visualization')
            ->columns(array('uuid','name','is_owner' => (new Expression('IF(created_by = '.AuthContext::get(AuthConstants::USER_ID).', "true", "false")')),'account_id','version','isdeleted','renderer','type'))
            ->where(array('ox_visualization.uuid' => $uuid,'isdeleted' => 0));
        $response = $this->executeQuery($select)->toArray();
        if (count($response) == 0) {
            return 0;
        }
        return $response[0];
    }

    public function getVisualizationList($params = null)
    {
        $paginateOptions = FilterUtils::paginateLikeKendo($params);
        $where = $paginateOptions['where'];
        if (isset($params['show_deleted']) && $params['show_deleted']==true) {
            $where .= empty($where) ? "WHERE account_id =".AuthContext::get(AuthConstants::ACCOUNT_ID) : " AND account_id =".AuthContext::get(AuthConstants::ACCOUNT_ID);
        } else {
            $where .= empty($where) ? "WHERE isdeleted <>1 AND account_id =".AuthContext::get(AuthConstants::ACCOUNT_ID) : " AND isdeleted <>1 AND account_id =".AuthContext::get(AuthConstants::ACCOUNT_ID);
        }
        $sort = $paginateOptions['sort'] ? " ORDER BY ".$paginateOptions['sort'] : '';
        $limit = " LIMIT ".$paginateOptions['pageSize']." offset ".$paginateOptions['offset'];

        $cntQuery ="SELECT count(id) as 'count' FROM `ox_visualization` ";
        $resultSet = $this->executeQuerywithParams($cntQuery.$where);
        $count=$resultSet->toArray()[0]['count'];

        if (isset($params['show_deleted']) && $params['show_deleted']==true) {
            $query ="SELECT uuid,name,IF(created_by = ".AuthContext::get(AuthConstants::USER_ID).", 'true', 'false') as is_owner,version,account_id,isdeleted,renderer,type FROM `ox_visualization`".$where." ".$sort." ".$limit;
        } else {
            $query ="SELECT uuid,name,IF(created_by = ".AuthContext::get(AuthConstants::USER_ID).", 'true', 'false') as is_owner,version,account_id,renderer,type FROM `ox_visualization`".$where." ".$sort." ".$limit;
        }
        $resultSet = $this->executeQuerywithParams($query);
        $result = $resultSet->toArray();
        foreach ($result as $key => $value) {
            unset($result[$key]['id']);
        }
        return array('data' => $result,
                 'total' => $count);
    }
}
