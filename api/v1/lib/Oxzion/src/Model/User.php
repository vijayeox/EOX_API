<?php
namespace Oxzion\Model;

use Oxzion\Type;
use Oxzion\Model\Entity;

class User extends Entity
{
    protected static $MODEL = array(
        'id' =>                 ['type' => Type::INTEGER,   'readonly' => true , 'required' => false],
        'uuid' =>               ['type' => Type::UUID,      'readonly' => false, 'required' => false],
        'username' =>           ['type' => Type::STRING,    'readonly' => false, 'required' => true],
        'password' =>           ['type' => Type::STRING,    'readonly' => false, 'required' => true],
        'name' =>               ['type' => Type::STRING,    'readonly' => false, 'required' => false],
        'account_id' =>         ['type' => Type::INTEGER,   'readonly' => false, 'required' => true],
        'icon' =>               ['type' => Type::STRING,    'readonly' => false, 'required' => false],
        'status' =>             ['type' => Type::STRING,    'readonly' => false, 'required' => true, 'value' => 'Active'],
        'in_game' =>            ['type' => Type::BOOLEAN,    'readonly' => false, 'required' => false, 'value' => false],
        'timezone' =>           ['type' => Type::STRING,    'readonly' => false, 'required' => false, 'value' => 'Asia/Kolkata'],
        'date_created' =>       ['type' => Type::TIMESTAMP, 'readonly' => true,  'required' => false],
        'date_modified' =>      ['type' => Type::TIMESTAMP, 'readonly' => true,  'required' => false],
        'created_by' =>         ['type' => Type::INTEGER,   'readonly' => false, 'required' => false],
        'modified_by' =>        ['type' => Type::INTEGER,   'readonly' => true,  'required' => false],
        'preferences' =>        ['type' => Type::STRING,    'readonly' => false, 'required' => false],
        'password_reset_code' => ['type' => Type::STRING,    'readonly' => false, 'required' => false],
        'password_reset_expiry_date' => ['type' => Type::TIMESTAMP,    'readonly' => false, 'required' => false],
        'person_id' =>          ['type' => Type::INTEGER,    'readonly' => false, 'required' => true],
        'cleared_browser_cache' =>          ['type' => Type::INTEGER,    'readonly' => false, 'required' => false, 'value' => 0],
    );

    public function &getModel()
    {
        return self::$MODEL;
    }
}
