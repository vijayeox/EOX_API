<?php
namespace Oxzion\Service;

use Exception;
use Oxzion\ServiceException;
use Oxzion\OxServiceException;
use Oxzion\Service\AbstractService;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\Service\EntityService;

class BusinessParticipantService extends AbstractService
{
    protected $table;
    protected $modelClass;
    protected $entityService;
    /**
     * @ignore __construct
     */
    public function __construct($config, $dbAdapter, EntityService $entityService)
    {
        parent::__construct($config, $dbAdapter);
        $this->entityService = $entityService;
    }

    public function getEntitySellerAccount($entityId)
    {
        $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        $result = $this->getBusinessRelationship($entityId, $accountId);
        if (count($result) > 0) {
            return $result[0]['sellerAccountId'];
        }

        $select = "SELECT obr.account_id 
                   from ox_account_offering oof 
                   inner join ox_account_business_role obr on obr.id = oof.account_business_role_id
                   where oof.entity_id = :entityId and obr.account_id = :accountId";
        $params = ["entityId" => $entityId, "accountId" => $accountId];
        $this->logger->info("Query 1--- $select with Params--".print_r($params,true));
        $result = $this->executeQueryWithBindParameters($select, $params)->toArray();
        if (count($result) > 0) {
            return $result[0]['account_id'];
        }
        
         return $this->entityService->getEntityOfferingAccount($entityId);
    }

    private function getBusinessRelationship($entityId, $buyerAccountId, $sellerAccountId = null){
        $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        $select = "SELECT sbr.account_id as sellerAccountId, bbr.account_id as buyerAccountId
                   from ox_business_relationship obr 
                   inner join ox_account_business_role sbr on sbr.id = obr.seller_account_business_role_id
                   inner join ox_account_business_role bbr on bbr.id = obr.buyer_account_business_role_id
                   inner join ox_account_offering oof on sbr.id = oof.account_business_role_id
                   where oof.entity_id = :entityId and bbr.account_id = :accountId AND sbr.account_id <> $accountId";
        $params = ["entityId" => $entityId, "accountId" => $buyerAccountId];
        if(isset($sellerAccountId)){
            $select .= " AND sbr.account_id = :sellerAccountId";
            $params['sellerAccountId'] = $sellerAccountId;
        }
        $this->logger->info("Query 2--- $select with Params--".print_r($params,true));
        return $this->executeQueryWithBindParameters($select, $params)->toArray();
    }

    public function setupBusinessRelationship($buyerAccountId, $sellerAccountId, $buyerBusinessRole, $sellerBusinessRole, $appId){
        $buyerAccountBusinessRoleId = $this->getAccountBusinessRoleId($buyerBusinessRole,$appId,$buyerAccountId);
        $sellerAccountBusinessRoleId = $this->getAccountBusinessRoleId($sellerBusinessRole,$appId,$sellerAccountId);
        try {
            $this->beginTransaction();
            $insertQuery = "INSERT IGNORE INTO ox_business_relationship (seller_account_business_role_id, buyer_account_business_role_id) VALUES (:sellerAccountBusinessRoleId,:buyerAccountBusinessRoleId)";
            $queryParams = ['buyerAccountBusinessRoleId' => $buyerAccountBusinessRoleId , 'sellerAccountBusinessRoleId' => $sellerAccountBusinessRoleId];
            $this->executeUpdateWithBindParameters($insertQuery, $queryParams);
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;            
        }
    }
    private function getAccountBusinessRoleId($businessRole,$appId,$accountId){
        $select = "SELECT  abr.id
                        From ox_account_business_role abr 
                        INNER JOIN ox_business_role br ON br.id = abr.business_role_id
                        INNER JOIN ox_account oxa ON oxa.id = abr.account_id
                        INNER JOIN ox_app app ON app.id = br.app_id
                        WHERE br.name = :businessRoleName AND app.uuid= :appId AND oxa.uuid= :accountId";
        $params = ['businessRoleName' => $businessRole , 'appId' => $appId , 'accountId' =>$accountId];
        $this->logger->info("Query --- $select with Params--".print_r($params,true));
        $result = $this->executeQueryWithBindParameters($select, $params)->toArray();
        if (count($result) == 0) {
            throw new ServiceException("Account Business Role Not Found", "account.businessRole.notfound", OxServiceException::ERR_CODE_NOT_FOUND );
        }
        return $result[0]['id'];
    }

    public function checkIfBusinessRelationshipExists($entityId, $buyerAccountId, $sellerAccountId){
       $result = $this->getBusinessRelationship($entityId, $buyerAccountId, $sellerAccountId); 
       $this->logger->info("Result---".print_r(count($result),true));
       return count($result) > 0;
    }

    public function checkIfAccountOfferingExists($appId){
        $select = "SELECT oxabr.business_role_id as account_business_role_id , oxao.entity_id
                    FROM ox_account_offering oxao
                    INNER JOIN ox_app_entity oxae ON oxae.id = oxao.entity_id
                    INNER JOIN ox_account_business_role oxabr ON oxabr.id = oxao.account_business_role_id
                    INNER JOIN ox_business_role oxbr ON oxbr.id = oxabr.business_role_id
                    INNER JOIN ox_app oxap ON oxap.id = oxbr.app_id
                    WHERE oxap.uuid =:appId";
        $params = ['appId' => $appId] ;
        $this->logger->info("Query --- $select with Params--".print_r($params,true));
        return $this->executeQueryWithBindParameters($select, $params)->toArray();
                   
    }
}
