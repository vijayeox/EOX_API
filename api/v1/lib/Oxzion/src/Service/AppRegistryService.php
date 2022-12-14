<?php
namespace Oxzion\Service;

use Exception;
use Oxzion\Service\AbstractService;
use Oxzion\Service\BusinessParticipantService;

class AppRegistryService extends AbstractService
{
    protected $table;
    protected $modelClass;
    protected $businessParticipantService;
    /**
     * @ignore __construct
     */
    public function __construct($config, $dbAdapter, BusinessParticipantService $businessParticipantService)
    {
        parent::__construct($config, $dbAdapter);
        $this->businessParticipantService = $businessParticipantService;
    }

    public function createAppRegistry($appId, $accountId, $startOptions = [])
    {
        $sql = $this->getSqlObject();
        //Code to check if the app is already registered for the account
        $queryString = "select count(ar.id) as count
        from ox_app_registry as ar
        inner join ox_app ap on ap.id = ar.app_id
        inner join ox_account acct on acct.id = ar.account_id
        where ap.uuid = :appId and acct.uuid = :accountId";
        $params = array("appId" => is_array($appId) ? $appId['value'] : $appId, "accountId" => $accountId);
        $resultSet = $this->executeQueryWithBindParameters($queryString, $params)->toArray();
        if ($resultSet[0]['count'] == 0) {
            try {
                $this->beginTransaction();
                $insert = "INSERT into ox_app_registry (app_id, account_id, start_options)
                select ap.id, acct.id, ap.start_options from ox_app as ap, ox_account as acct where ap.uuid = :appId and acct.uuid = :accountId";
                $params = array("appId" => !is_numeric($appId) ? $appId : $this->getUuidFromId('ox_app',$appId), "accountId" => $accountId);
                $this->logger->info("REGIsTRY Insert--- $insert with params---".print_r($params,true));
                $result = $this->executeUpdateWithBindParameters($insert, $params);
                $this->commit();
                return $result->getAffectedRows();
            } catch (Exception $e) {
                $this->rollback();
                throw $e;
            }
        }
        $updateQuery = "UPDATE ox_app_registry SET start_options = :startOptions where app_id = :appId and account_id = :accountId";
        $params = array("appId" => is_numeric($appId) ? $appId : $this->getIdFromUuid('ox_app', $appId), "accountId" => is_numeric($accountId) ? $accountId : $this->getIdFromUuid('ox_account', $accountId), "startOptions" => !empty($startOptions) ? json_encode($startOptions) : null);
        $this->logger->info("UPDATEREGISTRY STARTOPTIONS $$updateQuery with params---" . print_r($params, true));
        $this->executeUpdateWithBindParameters($updateQuery, $params);
        return 0;
    }

    public function getAppProperties($data){
        $queryString = "SELECT oar.start_options
                    FROM ox_app_registry oar
                    INNER JOIN ox_app oa ON oa.id = oar.app_id
                    INNER JOIN ox_account oxa ON oxa.id = oar.account_id
                    WHERE oa.uuid =:appId AND oxa.uuid =:accountId";
        $params = ['appId' => $data['appId'],'accountId' => $data['accountId']];
        $resultSet = $this->executeQueryWithBindParameters($queryString, $params)->toArray();
        if (count($resultSet) == 0) {
            $queryString = "SELECT oa.start_options
                            FROM ox_app oa
                            WHERE oa.uuid =:appId";
            $params = ['appId' => $data['appId']];
            $resultSet = $this->executeQueryWithBindParameters($queryString, $params)->toArray();
        }
        $accountOfferings = $this->businessParticipantService->checkIfAccountOfferingExists($data['appId']);
        $result =['accountOffering' => $accountOfferings , 'start_options' => $resultSet[0]['start_options']];   
        return $result;
    }
}
