<?php

namespace App\Model;

use Oxzion\Model\Entity;

class Payment extends Entity
{
    protected $data = array(
        'id' => 0,
        'app_id' => '',
        'payment_client' => '',
        'api_url' => '',
        'server_instance_name' => null,
        'payment_config' => null,
    );

    public function validate()
    {
        $dataArray = array("app_id", "payment_client", "api_url");
        $this->validateWithParams($dataArray);
    }
}
