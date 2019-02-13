<?php
namespace Oxzion\Model;

use Bos\Model\Entity;

class User extends Entity{

    protected $data = array(
        'id' => NULL,
        'gamelevel' => NULL,
        'username' => NULL,
        'password' => NULL,
        'firstname' => NULL,
        'lastname' => NULL,
        'name' => NULL,
        'role' => '',
        'last_login' => NULL,
        'orgid' => NULL,
        'email' => NULL,
        'emailnotify' => 'Active',
        'sentinel' => 'On',
        'icon' => NULL,
        'gamemodeIcon' => NULL,
        'status' => 'Active',
        'ipaddress' => NULL,
        'country' => NULL,
        'dob' => NULL,
        'designation' => NULL,
        'phone' => NULL,
        'address' => NULL,
        'sex' => NULL,
        'website' => NULL,
        'about' => NULL,
        'interest' => NULL,
        'hobbies' => NULL,
        'managerid' => NULL,
        'alertsacknowledged' => '1',
        'pollsacknowledged' => '1',
        'selfcontribute' => NULL,
        'contribute_percent' => NULL,
        'statusbox' => 'Matrix|Leaderboard|Alerts',
        'eid' => NULL,
        'defaultgroupid' => NULL,
        'cluster' => '0',
        'level' => NULL,
        'open_new_tab' => '0',
        'listtoggle' => NULL,
        'defaultmatrixid' => '0',
        'lastactivity' => '0',
        'locked' => '0',
        'signature' => NULL,
        'location' => NULL,
        'org_role_id' => '1',
        'in_game' => '0',
        'mission_link' => NULL,
        'instanceform_link' => NULL,
        'timezone' => 'Asia/Kolkata',
        'inmail_label' => '2=>Comment|3=>Observer|4=>Personal',
        'avatar_date_created' => NULL,
        'doj' => NULL,
        'password_reset_date' => NULL,
        'otp' => NULL,
        'preferences' => NULL,
    );

    public function validate(){
        $required = array('gamelevel','username','password','firstname','lastname','name','role','email','status','dob','designation','sex','managerid','level','org_role_id','doj');
        $this->validateWithParams($required);
    }
}
