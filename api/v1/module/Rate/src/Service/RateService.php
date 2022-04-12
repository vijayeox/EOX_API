<?php
namespace Rate\Service;

use Oxzion\Service\AbstractService;
use Rate\Model\RateTable;
use Rate\Model\Rate;
use Exception;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Oxzion\Utils\UuidUtil;
use Oxzion\EntityNotFoundException;
use Oxzion\OxServiceException;
use Oxzion\ServiceException;
use Oxzion\Utils\ArrayUtils;
use Oxzion\Utils\FilterUtils;

class RateService extends AbstractService
{
    private $table;
    private $uuidToTableList = array('entity_id' => 'ox_app_entity','account_id' => 'ox_account','app_id' => 'ox_app','condition_1' => 'ox_rate_condition','condition_2' => 'ox_rate_condition','condition_3' => 'ox_rate_condition','condition_4' => 'ox_rate_condition','condition_5' => 'ox_rate_condition','condition_6' => 'ox_rate_condition');

    private $maxConditon = 6;

    public static $rateFields = array('id' => 'ora.id', 'uuid' => 'ora.uuid', 'condition_1' => 'orc.value', 'condition_2' => 'orc2.value', 'condition_3' => 'orc3.value','condition_4' => 'orc4.value','condition_5' => 'orc5.value','condition_6' => 'orc6.value', 'conditional_expression' => 'ora.conditional_expression', 'rate' => 'ora.rate', 'app_id' => 'ora.app_id', 'account_id' => 'ora.account_id', 'entity_id' => 'ora.entity_id','date_created' => 'ora.date_created');

    public function __construct($config, $dbAdapter, RateTable $table, RateConditionService $rateConditionService)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
        $this->rateConditionSerive = $rateConditionService;
    }

    private function convertEssentialUuidsToIds(&$data) {
        foreach($this->uuidToTableList as $key => $value) {
            if(isset($data[$key])) {
                if(UuidUtil::isValidUuid($data[$key])) {
                    $data[$key] = $this->getIdFromUuid($value,$data[$key]);
                } else {
                    throw new ServiceException("Value specified for $key is not a valid uuid","incorrect.uuid.specified");
                }
            }
        }
    }

    private function checkConditionToSequenceMatch($data) {
        foreach($data as $key => $value) {
            if(str_contains($key,'condition_')) {
                $sequence_id = $this->rateConditionSerive->getSequenceId($value);
                $condition_id = substr($key,-1);
                if($sequence_id == $condition_id) {
                    continue;
                } else {
                    throw new ServiceException("Condition and Sequence do not match","condition.sequence.mismatch");
                }
            }
        }
    }

    public function createRate($data)
    {
        if(!isset($data['account_id'])) {
            $data['account_id'] = AuthContext::get(AuthConstants::ACCOUNT_UUID);
        }

        $this->convertEssentialUuidsToIds($data);
        $this->checkConditionToSequenceMatch($data);

        if(isset($data['conditional_expression'])) {
            if(!ArrayUtils::isJson($data['conditional_expression'])){
                throw new ServiceException("Conditional Expression must be a json string","conditional.expression.incorrect");
            }
        }
        $rate = new Rate($this->table);
        $rate->assign($data);
        try {
            $this->beginTransaction();
            $rate->save();
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        return $rate->getGenerated();
    }

    public function updateRate($uuid, $data)
    {
        $this->convertEssentialUuidsToIds($data);
        $this->checkConditionToSequenceMatch($data);
        $rate = new Rate($this->table);
        $rate->loadByUuid($uuid);
        $rate->assign($data);
        try {
            $this->beginTransaction();
            $rate->save();
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        return $rate->getGenerated();
    }

    public function deleteRate($uuid, $version)
    {
        $rate = new Rate($this->table);
        $rate->loadByUuid($uuid);
        $rate->assign([
            'version' => $version,
            'isdeleted' => 1
        ]);
        try {
            $this->beginTransaction();
            $rate->save();
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function getRate($uuid)
    {
        $queryString = 'SELECT ora.uuid, orc.uuid as condition_1, ora.condition_2, ora.condition_3,ora.condition_4,ora.condition_5,ora.condition_6, ora.conditional_expression, ora.rate, oa.uuid as account_id, ora.`version`, oap.uuid as app_id, oae.uuid as entity_id, ora.date_created, ora.date_modified,ora.modified_by, ora.created_by from ox_rate ora
        left join ox_account oa on ora.account_id = oa.id
        left join ox_app_entity oae on ora.entity_id = oae.id
        left join ox_rate_condition orc on ora.condition_1 = orc.id
        left join ox_rate_condition orc2 on ora.condition_2 = orc2.id
        left join ox_rate_condition orc3 on ora.condition_3 = orc3.id
        left join ox_rate_condition orc4 on ora.condition_4 = orc4.id
        left join ox_rate_condition orc5 on ora.condition_5 = orc5.id
        left join ox_rate_condition orc6 on ora.condition_6 = orc6.id
        inner join ox_app oap on ora.app_id = oap.id
        where ora.uuid =:uuid and ora.isdeleted = 0';
        $queryParams = ['uuid' => $uuid];
        $resultSet = $this->executeQueryWithBindParameters($queryString, $queryParams)->toArray();
        if (empty($resultSet)) {
            throw new EntityNotFoundException('Rate specified is not present or has been deleted', OxServiceException::ERR_CODE_NOT_FOUND);
        }
        return $resultSet;
    }

    public function getRateList($params = null)
    {
        $this->convertEssentialUuidsToIds($params);
        if(!isset($params['account_id'])) {
            $params['account_id'] = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }

        $paginateOptions = FilterUtils::paginateLikeKendo($params,self::$rateFields);
        $where = $paginateOptions['where'];
        $sort = $paginateOptions['sort'] ? " ORDER BY ".$paginateOptions['sort'] : '';
        $limit = " LIMIT ".$paginateOptions['pageSize']." offset ".$paginateOptions['offset'];

        if(!empty($where)) {
            $where .= " AND orc.app_id = '".$params['app_id']."' AND orc.entity_id = '".$params['entity_id']."' AND orc.account_id = ".$params['account_id']." AND orc.isdeleted <>1";
        } else {
            $where .= "WHERE orc.app_id = '".$params['app_id']."' AND orc.entity_id = '".$params['entity_id']."' AND orc.account_id = ".$params['account_id']." AND orc.isdeleted <>1";
        }

        $selectQuery = "SELECT ora.uuid, orc.value as condition_1, orc2.value as condition_2, orc3.value as condition_3, orc4.value as condition_4, orc5.value as condition_5, orc6.value as condition_6, ora.conditional_expression, ora.rate, oa.uuid as account_id, ora.`version`, oap.uuid as app_id, oae.uuid as entity_id, ora.date_created, ora.date_modified,ora.modified_by, ora.created_by";
        $fromQuery = "from ox_rate ora
        left join ox_account oa on ora.account_id = oa.id
        left join ox_app_entity oae on ora.entity_id = oae.id
        left join ox_rate_condition orc on ora.condition_1 = orc.id
        left join ox_rate_condition orc2 on ora.condition_2 = orc2.id
        left join ox_rate_condition orc3 on ora.condition_3 = orc3.id
        left join ox_rate_condition orc4 on ora.condition_4 = orc4.id
        left join ox_rate_condition orc5 on ora.condition_5 = orc5.id
        left join ox_rate_condition orc6 on ora.condition_6 = orc6.id
        inner join ox_app oap on ora.app_id = oap.id";

        if (!isset($params['show_deleted']) || $params['show_deleted']==false) {
            $where .= empty($where) ? "WHERE ora.isdeleted <>1 " : " AND ora.isdeleted <>1 ";
        }

        $query = $selectQuery." ".$fromQuery." ".$where." ".$sort." ".$limit;
        $resultSet = $this->executeQuerywithParams($query);
        $result = $resultSet->toArray();
        $cntQuery ="SELECT count(orc.id) as 'count'";
        $resultSet = $this->executeQuerywithParams($cntQuery." ".$fromQuery." ".$where);
        $count=$resultSet->toArray()[0]['count'];

        return array('data' => $result, 'total' => $count);
    }

}
