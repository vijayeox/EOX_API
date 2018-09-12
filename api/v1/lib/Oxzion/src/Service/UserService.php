<?php
namespace Oxzion\Service;
use Zend\Db\Sql\Sql;
use Zend\Db\Adapter\Adapter;
use Oxzion\Service\CacheService;

class UserService {
	private $userName;
	private $cacheStorage;
	protected $userInfo = array();
	protected $groupsArray = array();
	private $id;
	private $orgId;
	protected $config;

	public function __construct($config){
		$this->config = $config;
		$this->cacheStorage = new CacheService();
	}
	public function setUserName($username){
		$this->userName = $username;
		if($cacheData = $this->cacheStorage->get($this->userName)){
			$data = $cacheData;
		} else {
			$data = $this->retrieveUserInfoFromDb($this->userName);
		}
		$this->setUserInfo($data);
	}
	protected function retrieveUserInfoFromDb($userName){
		$dbAdapter = new Adapter($this->config['db']);
		$sql = new Sql($dbAdapter);
		$select = $sql->select()
		->from('avatars')
        ->columns(array('id','name','orgid'))
		->where(array('username = "'.(string)$userName.'"'))->limit(1);
		$results = $dbAdapter->query($sql->getSqlStringForSqlObject($select), \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE)->toArray()[0];
		$this->cacheStorage->set($userName,$results);
		$this->id = $results['id'];
		$this->orgId = $results['orgid'];
		return $results;
	}
	private function setUserInfo($data){
		$this->userInfo = $data;
	}
	private function setGroups(){
		$this->cacheStorage->set($this->userName."_groups",$this->getGroupsDB());
		return $this->groupsArray;
	}
	private function getGroupsDB(){
		$dbAdapter = new Adapter($this->config['db']);
		$sql = new Sql($dbAdapter);
		$select = $sql->select()
		->from('groups_avatars')
        ->columns(array())
        ->join('groups', 'groups.id = groups_avatars.groupid')
        ->where(array('groups_avatars.avatarid' => $this->id));
		$selectString = $sql->getSqlStringForSqlObject($select);
		return $this->groupsArray = $dbAdapter->query($selectString, \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE)->toArray();
	}
	public function getGroups(){
		if($groupData = $this->cacheStorage->get($this->userName."_groups")){
			$data = $groupData;
		} else {
			$data = $this->setGroups();
		}
		return $data;
	}
	public function getUserInfo(){
		return $this->userInfo;
	}
	public function getId(){
		return $this->id;
	}
	public function getOrgId(){
		$data = $this->userInfo;
		return $data['orgid'];
	}
}
?>