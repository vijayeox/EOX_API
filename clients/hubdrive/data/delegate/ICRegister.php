<?php

use Oxzion\AppDelegate\AbstractAppDelegate;
use Oxzion\Db\Persistence\Persistence;
use Oxzion\AppDelegate\AccountTrait;
use Oxzion\AppDelegate\UserContextTrait;
use Oxzion\Utils\UuidUtil;
use Oxzion\DelegateException;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;

class ICRegister extends AbstractAppDelegate
{
    use AccountTrait;
    use UserContextTrait;
    const APPID = 'a4b1f073-fc20-477f-a804-1aa206938c42';

    public function __construct()
    {
        parent::__construct();
    }

    public function execute(array $data, Persistence $persistenceService)
    {
        $this->logger->info("Executing IC Registration with data- " . json_encode($data, JSON_UNESCAPED_SLASHES));
        $data['businessRole'] = 'Independent Contractor';
        $data['sellerBusinessRole'] = 'Contract Carrier';
        $data['sellerAccountName'] = "IGGI";
        $data['appId'] = self::APPID; 

        // Add logs for created by id and producer name who triggered submission
        $this->logger->info("Check---- " . print_r(!isset($data['user_exists']) || ($data['user_exists'] === false || $data['user_exists'] === 0|| $data['user_exists'] === 'false' || $data['user_exists'] === "0"), true));

        if(!isset($data['user_exists']) || $data['user_exists'] === "0"|| ($data['user_exists'] === false || $data['user_exists'] === 0|| $data['user_exists'] === 'false')) {
            $dataForIC = array();
            $dataForIC['businessRole'] = $data['businessRole'];
            $dataForIC['sellerBusinessRole'] = $data['sellerBusinessRole'];
            $dataForIC['appId'] = $data['appId'];
            $dataForIC['name'] = $data['name'];
            $dataForIC['email'] = $data['email'];
            $dataForIC['firstname'] = $data['firstname'];
            $dataForIC['lastname'] = $data['lastname'];
            $dataForIC['address1'] = $data['address1'];
            $dataForIC['city'] = $data['city'];
            if (!is_array($data['stateObj'])) {
                $stateDecoded = json_decode($data['stateObj'],true);
                $this->logger->info("stateDecoded- " . print_r($stateDecoded, true));
            }
            $dataForIC['state'] = isset($stateDecoded['abbreviation']) ? $stateDecoded['abbreviation'] : $data['stateObj']['abbreviation'];
            $dataForIC['zip'] = $data['zip'];
            $dataForIC['country'] = 'United States of America';
            if (!isset($dataForIC['contact'])) {
                $dataForIC['contact'] = array();
                $dataForIC['contact']['username'] = str_replace('@', '.', $data['email']);
                $dataForIC['contact']['firstname'] = $data['firstname'];
                $dataForIC['contact']['lastname'] = $data['lastname'];
                $dataForIC['contact']['email'] = $data['email'];
            }
            if (!isset($dataForIC['preferences'])) {
                $dataForIC['preferences'] = '{}';
            }
            $dataForIC['type'] = 'BUSINESS';
            $dataForIC['identifier_field'] = $data['identifier_field'];
            $this->logger->info("Before RegisterAcount---".print_r($dataForIC,true));
            $this->registerAccount($dataForIC);
            $this->logger->info("After RegisterAcount---".print_r($dataForIC,true));
            $data['buyerAccountId'] = $dataForIC['accountId'];
        } else{
           $response =  $this->getUserDataByIdentifier($data['appId'], $data[$data['identifier_field']], $data['identifier_field']);
           if (count($response) == 0) {
                throw new DelegateException("No user record found","no.user.record.exists");
           }
           $data['buyerAccountId'] = $response[0]['accountId'];
           $sellerAccountId = AuthContext::get(AuthConstants::ACCOUNT_UUID);
           $response = $this->checkIfBusinessRelationshipExists($response[0]['entityId'],$response[0]['account_id'],$sellerAccountId);
           if ($response) {
               throw new DelegateException("Business Relationship already exists","businessrelationship.exists");
           }
        }
        return $data;
    }
}
