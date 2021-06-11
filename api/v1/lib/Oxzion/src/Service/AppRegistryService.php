<?php
namespace Oxzion\Service;

use Exception;
use Oxzion\Service\AbstractService;

class AppRegistryService extends AbstractService
{
    protected $table;
    protected $modelClass;
    /**
     * @ignore __construct
     */
    public function __construct($config, $dbAdapter)
    {
        parent::__construct($config, $dbAdapter);
    }

    public function createAppRegistry($appId, $accountId)
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
        return 0;
    }
}
