<?php
namespace UserAuditLog\Model;

use Oxzion\Model\Entity;

class UserAuditLog extends Entity
{
    protected $data = array(
        'id' => null,
        'user_id' => null,
        'account_id' => null,
        'activity_time' => null,
        'activity' => null,
        'jwtToken' => null,
    );

    public function validate()
    {
        $dataArray = array("user_id", "account_id", "activity_time", "activity", "jwtToken");
        $this->validateWithParams($dataArray);
    }
}
