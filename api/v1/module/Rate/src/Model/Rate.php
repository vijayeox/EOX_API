<?php

namespace Rate\Model;

use Oxzion\Model\Entity;
use Oxzion\Type;

class Rate extends Entity
{
    protected static $MODEL = [
        'id' =>                     ['type' => Type::INTEGER,   'readonly' => true ,    'required' => false],

        'uuid' =>                   ['type' => Type::UUID,      'readonly' => true,     'required' => false],


        'condition_1' =>            ['type' => Type::INTEGER,   'readonly' => false,    'required' => true],

        'condition_2' =>            ['type' => Type::INTEGER,   'readonly' => false,    'required' => false],

        'condition_3' =>            ['type' => Type::INTEGER,   'readonly' => false,    'required' => false],

        'condition_4' =>            ['type' => Type::INTEGER,   'readonly' => false,    'required' => false],

        'condition_5' =>            ['type' => Type::INTEGER,   'readonly' => false,    'required' => false],

        'condition_6' =>            ['type' => Type::INTEGER,   'readonly' => false,    'required' => false],

        'conditional_expression' => ['type' => Type::STRING,   'readonly' => false,    'required' => false],

        'rate' =>                   ['type' => Type::STRING,   'readonly' => false,    'required' => false],

        'isdeleted' =>              ['type' => Type::INTEGER,   'readonly' => false,    'required' => false, 'value' => 0],

        'version' =>                ['type' => Type::INTEGER,   'readonly' => false,    'required' => false, 'value' => 0],

        'account_id' =>             ['type' => Type::INTEGER,   'readonly' => false,    'required' => false],

        'app_id' =>                 ['type' => Type::INTEGER,   'readonly' => false,    'required' => true],

        'entity_id' =>              ['type' => Type::INTEGER,   'readonly' => false,    'required' => false],

        'date_created' =>           ['type' => Type::TIMESTAMP, 'readonly' => true,     'required' => false],

        'date_modified' =>          ['type' => Type::TIMESTAMP, 'readonly' => true,     'required' => false],

        'created_by' =>             ['type' => Type::INTEGER,   'readonly' => true,     'required' => false],

        'modified_by' =>            ['type' => Type::INTEGER,   'readonly' => true,     'required' => false],
    ];

    public function &getModel()
    {
        return self::$MODEL;
    }
}
