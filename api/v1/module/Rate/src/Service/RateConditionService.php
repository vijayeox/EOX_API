<?php
namespace Rate\Service;

use Oxzion\Service\AbstractService;
use Rate\Model\RateConditionTable;
use Rate\Model\RateCondition;
use Exception;
use Oxzion\EntityNotFoundException;
use Oxzion\OxServiceException;
use Oxzion\Utils\FilterUtils;

class RateConditionService extends AbstractService
{
    private $table;

    public function __construct($config, $dbAdapter, RateConditionTable $table)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
    }

    private function convertEssentialUuidsToIds(&$data) {
        if(isset($data['entity_id'])){
            $data['entity_id'] = $this->getIdFromUuid('ox_app_entity',$data['entity_id']);
        }
        if(isset($data['account_id'])){
            $data['account_id'] = $this->getIdFromUuid('ox_account',$data['account_id']);
        }
        if(isset($data['app_id'])){
            $data['app_id'] = $this->getIdFromUuid('ox_app',$data['app_id']);
        }
    }

    public function createRateCondition($data)
    {
        $this->convertEssentialUuidsToIds($data);
        $rateCondition = new RateCondition($this->table);
        $rateCondition->assign($data);
        try {
            $this->beginTransaction();
            $rateCondition->save();
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        return $rateCondition->getGenerated();
    }

    public function updateRateCondition($uuid, $data)
    {
        $this->convertEssentialUuidsToIds($data);
        $rateCondition = new RateCondition($this->table);
        $rateCondition->loadByUuid($uuid);
        $rateCondition->assign($data);
        try {
            $this->beginTransaction();
            $rateCondition->save();
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        return $rateCondition->getGenerated();
    }

    public function deleteRateCondition($uuid, $version)
    {
        $rateCondition = new RateCondition($this->table);
        $rateCondition->loadByUuid($uuid);
        $rateCondition->assign([
            'version' => $version,
            'isdeleted' => 1
        ]);
        try {
            $this->beginTransaction();
            $rateCondition->save();
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function getRateCondition($uuid)
    {
        $queryString = 'SELECT orc.uuid, orc.`name`, oa.uuid as account_id, orc.`version`, oap.uuid as app_id, oae.uuid as entity_id, orc.date_created, orc.created_by from ox_rate orc
        left join ox_account oa on orc.account_id = oa.id
        left join ox_app_entity oae on orc.entity_id = oae.id
        inner join ox_app oap on orc.app_id = oap.id
        where orc.uuid =:uuid and orc.isdeleted = 0';
        $queryParams = ['uuid' => $uuid];
        $resultSet = $this->executeQueryWithBindParameters($queryString, $queryParams)->toArray();
        if (empty($resultSet)) {
            throw new EntityNotFoundException('Rate Condition specified is not present or has been deleted', OxServiceException::ERR_CODE_NOT_FOUND);
        }
        return $resultSet;
    }

    public function getRateList($params = null)
    {
        $paginateOptions = FilterUtils::paginateLikeKendo($params);
        $where = $paginateOptions['where'];
        $sort = $paginateOptions['sort'] ? " ORDER BY ".$paginateOptions['sort'] : '';
        $limit = " LIMIT ".$paginateOptions['pageSize']." offset ".$paginateOptions['offset'];

        $selectQuery = "SELECT orc.uuid, orc.`name`, oa.uuid as account_id, orc.`version`, oap.uuid as app_id, oae.uuid as entity_id, orc.date_created, orc.created_by";
        $fromQuery = "from ox_rate_condition orc
        left join ox_account oa on orc.account_id = oa.id
        left join ox_app_entity oae on orc.entity_id = oae.id
        inner join ox_app oap on orc.app_id = oap.id";

        if (!isset($params['show_deleted']) || $params['show_deleted']==false) {
            $where .= empty($where) ? "WHERE orc.isdeleted <>1 " : " AND orc.isdeleted <>1 ";
        }

        $query = $selectQuery." ".$fromQuery." ".$where." ".$sort." ".$limit;
        $resultSet = $this->executeQuerywithParams($query);
        $result = $resultSet->toArray();

        $cntQuery ="SELECT count(orc.id) as 'count'";
        $resultSet = $this->executeQuerywithParams($cntQuery." ".$fromQuery." ".$where);
        $count=$resultSet->toArray()[0]['count'];

        return array('data' => $result, 'total' => $count);
    }

    public function getRateConditionIdByName($name) {
        $queryString = 'SELECT orc.id from ox_rate_condition orc
        where orc.name =:name and orc.isdeleted = 0';
        $queryParams = ['name' => $name];
        $resultSet = $this->executeQueryWithBindParameters($queryString, $queryParams)->toArray();
        if (empty($resultSet)) {
            throw new EntityNotFoundException('Rate Condition specified is not present or has been deleted', OxServiceException::ERR_CODE_NOT_FOUND);
        }
        return $resultSet;
    }

}
