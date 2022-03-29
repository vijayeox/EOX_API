<?php

namespace Rate\Model;

use Oxzion\Model\Entity;
use Oxzion\Type;

class RateCondition extends Entity
{
    protected static $MODEL = [
        'id' =>                     ['type' => Type::INTEGER,   'readonly' => true ,    'required' => false],

        'uuid' =>                   ['type' => Type::UUID,      'readonly' => true,     'required' => false],

        'name' =>                   ['type' => Type::STRING,    'readonly' => false,    'required' => true],

        'value' =>                  ['type' => Type::STRING,    'readonly' => false,    'required' => true],

        'isdeleted' =>              ['type' => Type::INTEGER,   'readonly' => false,    'required' => false, 'value' => 0],

        'version' =>                ['type' => Type::INTEGER,   'readonly' => false,    'required' => false, 'value' => 0],

        'account_id' =>             ['type' => Type::INTEGER,   'readonly' => false,    'required' => false],

        'app_id' =>                 ['type' => Type::INTEGER,   'readonly' => false,    'required' => true],

        'entity_id' =>              ['type' => Type::INTEGER,   'readonly' => false,    'required' => false],

        'date_created' =>           ['type' => Type::TIMESTAMP, 'readonly' => true,     'required' => false],

        'created_by' =>             ['type' => Type::INTEGER,   'readonly' => true,     'required' => false],

    ];

    public function &getModel()
    {
        return self::$MODEL;
    }
}
