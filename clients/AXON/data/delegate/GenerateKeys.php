<?php

use Oxzion\AppDelegate\AbstractDocumentAppDelegate;
use Oxzion\Db\Persistence\Persistence;

class GenerateKeys extends AbstractDocumentAppDelegate
{
    public function __construct(){
        parent::__construct();
    }
    public function execute(array $data, Persistence $persistenceService)
    {
        if (isset($data)) {
            $mapKeyArray = [
                'FName' => 'firstname',
                'LName' => 'lastname',
                'Email' => 'email',
                'producerLocationRequiredInfo' => [
                    'City' => 'city',
                    'ZipCode' => 'zip',
                    'Address1' => 'address1'
                ]
            ];
            foreach ($data as $key => $value) {
                foreach ($mapKeyArray as $modifiedKey => $replace) {
                    if (array_key_exists($modifiedKey, $data)) {
                        if ($modifiedKey == 'producerLocationRequiredInfo') {
                            foreach ($mapKeyArray['producerLocationRequiredInfo'] as $info => $infoValue) {
                                if (isset($data['producerLocationRequiredInfo']['State'])) {
                                    $data['state'] = $data['producerLocationRequiredInfo']['State']['name'];
                                }
                                $data[$infoValue] = $data['producerLocationRequiredInfo'][$info];
                            }
                        } else {
                            $data[$replace] = $data[$modifiedKey];
                        }
                    }
                }
            }
        }
        $this->logger->info("Data after filtering API keys----".print_r($data, true));
        return $data;
    }
}
