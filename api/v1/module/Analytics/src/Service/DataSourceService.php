<?php
namespace Analytics\Service;

use Oxzion\Service\AbstractService;
use Analytics\Model\DataSourceTable;
use Analytics\Model\DataSource;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Oxzion\ValidationException;
use Zend\Db\Sql\Expression;
use Oxzion\Utils\FilterUtils;
use Ramsey\Uuid\Uuid;
use Exception;

use function GuzzleHttp\json_decode;

class DataSourceService extends AbstractService
{

    private $table;

    public function __construct($config, $dbAdapter, DataSourceTable $table)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
    }

    public function createDataSource($data)
    {
        $form = new DataSource();
        $data['created_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_created'] = date('Y-m-d H:i:s');
        $data['org_id'] = AuthContext::get(AuthConstants::ORG_ID);
        $data['uuid'] = Uuid::uuid4()->toString();
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
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        return $count;
    }

    public function updateDataSource($uuid, $data)
    {
        $obj = $this->table->getByUuid($uuid, array());
        if (is_null($obj)) {
            return 0;
        }
        if(!isset($data['version']))
        {
            throw new Exception("Version is not specified, please specify the version");
        }
        $form = new DataSource();
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

    public function deleteDataSource($uuid,$version)
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
        $form = new DataSource();
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

    public function getDataSource($uuid)
    {
        $sql = $this->getSqlObject();
        $select = $sql->select();
        $select->from('ox_datasource')
            ->columns(array('name','type','configuration', 'is_owner' => (new Expression('IF(created_by = '.AuthContext::get(AuthConstants::USER_ID).', "true", "false")')),'org_id','isdeleted','uuid'))
            ->where(array('ox_datasource.uuid' => $uuid,'org_id' => AuthContext::get(AuthConstants::ORG_ID),'isdeleted' => 0));
        $response = $this->executeQuery($select)->toArray();
        if (count($response) == 0) {
            return 0;
        }
        return $response[0];
    }

    public function getDataSourceList($params = null)
    {
        $paginateOptions = FilterUtils::paginate($params);
        $where = $paginateOptions['where'];
        $where .= empty($where) ? "WHERE isdeleted <> 1 AND org_id =".AuthContext::get(AuthConstants::ORG_ID) : " AND isdeleted <> 1 AND org_id =".AuthContext::get(AuthConstants::ORG_ID);
        $sort = $paginateOptions['sort'] ? " ORDER BY ".$paginateOptions['sort'] : '';
        $limit = " LIMIT ".$paginateOptions['pageSize']." offset ".$paginateOptions['offset'];

        $cntQuery ="SELECT count(id) as 'count' FROM `ox_datasource` ";
        $resultSet = $this->executeQuerywithParams($cntQuery.$where);
        $count=$resultSet->toArray()[0]['count'];

        $query ="SELECT name,type,configuration,IF(created_by = ".AuthContext::get(AuthConstants::USER_ID).", 'true', 'false') as is_owner,org_id,isdeleted,uuid FROM `ox_datasource`".$where." ".$sort." ".$limit;
        $resultSet = $this->executeQuerywithParams($query);
        $result = $resultSet->toArray();
        foreach ($result as $key => $value) {
            if(isset($result[$key]['configuration']) && (!empty($result[$key]['configuration']))){
                $result[$key]['configuration'] = json_decode($result[$key]['configuration']);
                unset($result[$key]['id']);
            }
        }
        return array('data' => $result,
                 'total' => $count);
    }

    public function getAnalyticsEngine($uuid) {
        $sql = $this->getSqlObject();
        $select = $sql->select();
        $select->from('ox_datasource')
            ->columns(array('id','name','type','configuration','org_id','isdeleted','uuid'))
            ->where(array('uuid' => $uuid,'org_id' => AuthContext::get(AuthConstants::ORG_ID),'isdeleted' => 0));
        $response = $this->executeQuery($select)->toArray();
        if (count($response) == 0) {
            throw new Exception("Error Processing Request", 1);
        }
        $type = $response[0]['type'];

        $config = json_decode($response[0]['configuration'],1);

        try{
            switch($type) {
                case 'Elastic':
                case 'ElasticSearch':
                    $elasticConfig['elasticsearch'] = $config['data'];
                    $analyticsObject = new  \Oxzion\Analytics\Elastic\AnalyticsEngineImpl($elasticConfig);
                    return $analyticsObject;
                break;
            }
        }
        catch(Exception $e){
            throw $e;
        }
    }
}
