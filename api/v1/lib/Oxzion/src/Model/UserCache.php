<?php

namespace Oxzion\Model;

use Oxzion\Model\Entity;

class UserCache extends Entity
{
    protected $data = array(
        'id' => 0,
        'app_id' => 0,
        'content' => null,
        'user_id' => 0,
        'workflow_id' => null,
        'workflow_instance_id' => null,
        'activity_instance_id' => null,
        'form_id' => null,
        'date_created' => 0,
        'deleted' => 0,
    );
    protected $attributes = array();

    public function validate()
    {
    }
}