<?php

namespace Analytics\Model;

use Oxzion\Model\Entity;
use Oxzion\Type;

class Widget extends Entity
{
    protected static $MODEL = [
        'id' =>                 ['type' => Type::INTEGER,   'readonly' => TRUE ,    'required' => FALSE],
        'uuid' =>               ['type' => Type::UUID,      'readonly' => TRUE ,    'required' => FALSE],
        'visualization_id' =>   ['type' => Type::INTEGER,   'readonly' => FALSE ,   'required' => TRUE],
        'ispublic' =>           ['type' => Type::BOOLEAN,   'readonly' => FALSE ,   'required' => FALSE, 'value' => FALSE],
        'created_by' =>         ['type' => Type::INTEGER,   'readonly' => TRUE ,    'required' => FALSE],
        'date_created' =>       ['type' => Type::TIMESTAMP, 'readonly' => TRUE ,    'required' => FALSE],
        'account_id' =>             ['type' => Type::INTEGER,   'readonly' => TRUE ,    'required' => TRUE],
        'isdeleted' =>          ['type' => Type::BOOLEAN,   'readonly' => FALSE ,   'required' => FALSE, 'value' => FALSE],
        'name' =>               ['type' => Type::STRING,    'readonly' => FALSE ,   'required' => TRUE],
        'configuration' =>      ['type' => Type::STRING,    'readonly' => FALSE ,   'required' => TRUE],
        'expression' =>         ['type' => Type::STRING,    'readonly' => FALSE ,   'required' => FALSE],
        'version' =>            ['type' => Type::INTEGER,   'readonly' => FALSE,    'required' => FALSE],
        'exclude_overrides' => ['type' => Type::STRING,   'readonly' => FALSE ,   'required' => FALSE]
    ];

    public function &getModel()
    {
        return self::$MODEL;
    }
}
