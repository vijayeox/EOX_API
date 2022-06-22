<?php
namespace UserAuditLog\Service;

use Exception;
use UserAuditLog\Model\UserAuditLog;
use UserAuditLog\Model\UserAuditLogTable;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\Service\AbstractService;


class UserAuditLogService extends AbstractService
{
    private $table;

    public function __construct($config, $dbAdapter, UserAuditLogTable $table)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
    }

    /**
     * Additional condition check:Adding logout to all those logins which are session out & hence logout isnt recorded
     **/
    public function insertActivityTime($activity, $jwtToken){
        $this->logger->info("User activity Insert >> $activity\n");
        $userId = AuthContext::get(AuthConstants::USER_ID);
        $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);

        try {
            if($activity == "login"){ //Additional condition
                $selectQuery = "SELECT jwtToken from ox_user_audit_log where user_id = :userId and account_id = :accountId and activity = 'logout' 
                and activity_time >= (SELECT activity_time from ox_user_audit_log where user_id = :userId and account_id = :accountId and activity = 'login' ORDER BY activity_time  DESC LIMIT 1) ORDER BY activity_time  DESC LIMIT 1";
                $selectParams = array("userId" => $userId, "accountId" => $accountId) ;
                $result = $this->executeQueryWithBindParameters($selectQuery, $selectParams)->toArray();

                if(empty($result)){
                    $this->logger->info("User log out not recorded-----\n");
                    $sql = $this->getSqlObject();
                    $lastloginquery = $sql->select();
                    $lastloginquery->from('ox_user_audit_log')
                        ->columns(array('jwtToken'))
                        ->where(array('user_id' => $userId, 'account_id'=> $accountId, 'activity'=> 'login'))
                        ->order('activity_time DESC')
                        ->limit(1);
                    $lastloginresult = $this->executeQuery($lastloginquery)->toArray();


                    if($lastloginresult && $jwtToken !== $lastloginresult[0]['jwtToken']){
                        $jwtKey = $this->config['jwtKey'];
                        $jwtAlgo = $this->config['jwtAlgo'];
                        $decodeToken = \Oxzion\Jwt\JwtHelper::decodeJwtToken($lastloginresult[0]['jwtToken'], $jwtKey, $jwtAlgo);
                        $jwtExpirytime = date('Y-m-d H:i:s', $decodeToken->exp);
                        if($decodeToken->exp >= strtotime("now"))
                            $jwtExpirytime = date('Y-m-d H:i:s', strtotime('-1 minute'));
                        $insertParams = array(
                            'userId' => $userId,
                            'accountId' => $accountId,
                            'activityTime' => $jwtExpirytime,
                            'activity' => 'logout',
                            'jwtToken' => $lastloginresult[0]['jwtToken']
                        );
                        $jwtExplode = explode('-', $jwtExpirytime);
                        if($jwtExplode[0] == date('Y')){//avoiding activty year as 1970
                            $insertQuery = "INSERT into ox_user_audit_log (`user_id`, `account_id`, `activity_time`, `activity`, `jwtToken`) 
                              VALUES (:userId, :accountId, :activityTime, :activity, :jwtToken)";
                            $queryResult = $this->executeUpdateWithBindParameters($insertQuery, $insertParams);
                        }
                    }

                }
            }
        
            $params = array(
                'userId' => $userId,
                'accountId' => $accountId,
                'activityTime' => date('Y-m-d H:i:s'),
                'activity' => $activity,
                'jwtToken' => $jwtToken
            );
            $query = "INSERT into ox_user_audit_log (`user_id`, `account_id`, `activity_time`, `activity`, `jwtToken`) 
                      VALUES (:userId, :accountId, :activityTime, :activity, :jwtToken)";
            $res = $this->executeUpdateWithBindParameters($query, $params);    
        } catch (Exception $e) {
            throw $e;
        } 
        
    }
    
}
