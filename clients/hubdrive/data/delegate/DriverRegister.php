<?php

use Oxzion\AppDelegate\AbstractAppDelegate;
use Oxzion\Db\Persistence\Persistence;
use Oxzion\AppDelegate\AccountTrait;
use Oxzion\DelegateException;
use Oxzion\Utils\UuidUtil;

class DriverRegister extends AbstractAppDelegate
{
    use AccountTrait;
    const APPID = 'a4b1f073-fc20-477f-a804-1aa206938c42';

    public function __construct()
    {
        parent::__construct();
    }

    public function execute(array $data, Persistence $persistenceService)
    {
        $this->logger->info("Executing Driver Registration with data- " . print_r($data, true));
        // Add logs for created by id and producer name who triggered submission
        if(!isset($data['isDriverRegisterationOver']) || ($data['isDriverRegisterationOver'] === false || $data['isDriverRegisterationOver'] === 'false')) {
            if(isset($data['dataGrid']) && !is_array($data['dataGrid'])){
                $data['dataGrid'] = json_decode($data['dataGrid'],true);
            }
            foreach ($data['dataGrid'] as $driver) {
                $dataForDriver = array();
                if (!isset($dataForDriver['uuid'])) {
                    $dataForDriver['uuid'] = UuidUtil::uuid();
                }
                $dataForDriver['name'] = $driver['nameDriverUnit']." ".$driver['driverLastName'];
                $dataForDriver['email'] = $driver['driverEmail'];
                $dataForDriver['firstname'] = $driver['nameDriverUnit'];
                $dataForDriver['lastname'] = $driver['driverLastName'];
                $dataForDriver['address1'] = $driver['street1DriverUnitInfo'];
                $dataForDriver['city'] = $driver['city1DriverUnitInfo'];
                $dataForDriver['state'] = $driver['stateDriverUnitInfo']['abbreviation'];
                $dataForDriver['zip'] = $driver['zipCode1DriverUnitInfo'];
                $dataForDriver['country'] = 'United States of America';
                if (!isset($dataForDriver['contact'])) {
                    $dataForDriver['contact'] = array();
                    $dataForDriver['contact']['username'] = str_replace('@', '.', $driver['driverEmail']);
                    $dataForDriver['contact']['firstname'] = $driver['nameDriverUnit'];
                    $dataForDriver['contact']['lastname'] = $driver['driverLastName'];
                    $dataForDriver['contact']['email'] = $driver['driverEmail'];
                }
                if (!isset($dataForDriver['preferences'])) {
                    $dataForDriver['preferences'] = '{}';
                }
                $dataForDriver['app_id'] = self::APPID;
                $dataForDriver['type'] = 'INDIVIDUAL';
                $exceptionOnFailure = $this->registerAccount($dataForDriver);
                // if ($exceptionOnFailure == 1) {
                //     throw new DelegateException("Username/Email Used","record.exists");
                // }
            }
        }
        $data['isDriverRegisterationOver'] = true;
        return $data;
    }
}
