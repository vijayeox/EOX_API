<?php

namespace Oxzion\Model;

use Oxzion\Model\Entity;

class Comment extends Entity
{
    protected $data = array(
        'id'=>null ,
        'account_id' => null,
        'parent' => null,
        'uuid' => null,
        'text' => null,
        'file_id' => null,
        'created_by' => null,
        'modified_by' => null,
        'date_created' => null,
        'date_modified' => null,
        'isdeleted' => 0,
        'attachments' => NULL,
    );
    protected $attributes = array();

    public function validate()
    {
        $required = array('text', 'uuid');
        $this->validateWithParams($required);
    }
}
