<?php
namespace Oxzion\Service;

use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Oxzion\ValidationException;
use Oxzion\Service\AbstractService;
use Oxzion\Model\Organization;
use Oxzion\Model\OrganizationTable;
use Oxzion\Messaging\MessageProducer;
use Oxzion\Utils\FileUtils;
use Oxzion\Utils\UuidUtil;
use Oxzion\Utils\FilterUtils;
use Oxzion\Security\SecurityManager;
use Oxzion\AccessDeniedException;
use Oxzion\ServiceException;
use Exception;

class OrganizationService extends AbstractService
{
    protected $table;
    private $userService;
    private $roleService;
    private $addressService;
    protected $modelClass;
    private $messageProducer;
    private $privilegeService;
    static $userField= array('name' => 'ox_user.name','id' => 'ox_user.id','city' => 'ox_address.city','country' => 'ox_address.country','address' => 'ox_address.address1','address2' => 'ox_address.address2','state' => 'ox_address.state');
    static $groupField = array('name' => 'oxg.name','description' => 'oxg.description');
    static $projectField = array('name' => 'oxp.name','description' => 'oxp.description');
    static $announcementField = array('name' => 'oxa.name','description' => 'oxa.description');
    static $roleField = array('name' => 'oxr.name','description' => 'oxr.description');
    static $orgField = array('id' => 'og.id','uuid' => 'og.uuid','name' => 'og.name','preferences' => 'og.preferences','address1' => 'oa.address1','address2' => 'oa.address2','city' => 'oa.city','state' => 'oa.state','country' => 'oa.country','zip' => 'oa.zip','logo' => 'og.logo');


    public function setMessageProducer($messageProducer)
    {
        $this->messageProducer = $messageProducer;
    }

    /**
     * @ignore __construct
     */
    public function __construct($config, $dbAdapter, OrganizationTable $table, UserService $userService, AddressService $addressService,RoleService $roleService, PrivilegeService $privilegeService,MessageProducer $messageProducer)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
        $this->userService = $userService;
        $this->addressService = $addressService;
        $this->roleService = $roleService;
        $this->modelClass = new Organization();
        $this->privilegeService = $privilegeService;
        $this->messageProducer = $messageProducer;
    }

    /**
     * Create Organization Service
     * @method createOrganization
     * @param array $data Array of elements as shown
     * <code> {
     *               id : integer,
     *               name : string,
     *               logo : string,
     *               status : String(Active|Inactive),
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created Organization.
     */
    public function createOrganization(&$data, $files)
    {
        $data['uuid'] = isset($data['uuid'])?$data['uuid']:UuidUtil::uuid();
        if(!isset($data['contact'])){
            throw new ServiceException("Contact Person details are required","org.contact.required");
        }
        if(is_string($data['contact'])){
            $data['contact'] = json_decode($data['contact'],true);
        }
        if(!is_string($data['preferences'])){
            $data['preferences'] = json_encode($data['preferences']);
        }
        $data['created_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_created'] = date('Y-m-d H:i:s');
        $data['date_modified'] = date('Y-m-d H:i:s');

        try {
            $data['name'] = isset($data['name']) ? $data['name'] : NULL;
            $select = "SELECT count(name),status,uuid from ox_organization where name = '".$data['name']."'";
            $result = $this->executeQuerywithParams($select)->toArray();
            if($result[0]['count(name)'] > 0){
                if($result[0]['status'] == 'Inactive'){
                    $data['reactivate'] = isset($data['reactivate']) ? $data['reactivate'] : 0;
                    if($data['reactivate'] == 1){
                        $data['status'] = 'Active';
                        $count = $this->updateOrganization($result[0]['uuid'],$data,$files);
                        $this->uploadOrgLogo($result[0]['uuid'],$files);
                        if($count == 1){
                            return 1;
                        }
                    }else{
                        throw new ServiceException("Organization already exists would you like to reactivate?","org.already.exists");
                    }
                }else{
                    throw new ServiceException("Organization already exists","org.exists");
                }
            }

            $addressid = $this->addressService->addAddress($data);
            $data['address_id'] = $addressid;
            $form = new Organization($data);
            $form->validate();
            $this->beginTransaction();
            $count = 0;
            $count = $this->table->save($form);
            if ($count == 0) {
                throw new ServiceException("Failed to create new entity","failed.create.org");
            }
            $form->id = $this->table->getLastInsertValue();
            $data['preferences'] = json_decode($data['preferences'], true);
            $data['id'] = $form->id;
            $userid['id'] = $this->setupBasicOrg($data, $data['contact'], $data['preferences']);

            if (isset($userid['id'])) {
                $update = "UPDATE `ox_organization` SET `contactid` = '".$userid['id']."' where uuid = '".$data['uuid']."'";
                $resultSet = $this->executeQueryWithParams($update);
            }
            else{
                throw new ServiceException("Failed to create new entity","failed.create.org");
            }
            $insert = "INSERT INTO ox_app_registry (`org_id`,`app_id`,`date_created`,`start_options`) SELECT ".$form->id.",id,CURRENT_TIMESTAMP(),start_options from ox_app where isdefault = 1";
            $resultSet = $this->executeQueryWithParams($insert);

            $this->uploadOrgLogo($data['uuid'], $files);
            $this->commit();
            $this->messageProducer->sendTopic(json_encode(array('orgname' => $form->name, 'status' => $form->status)), 'ORGANIZATION_ADDED');
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        return $count;
    }




    public function getOrgLogoPath($id, $ensureDir=false)
    {
        $baseFolder = $this->config['UPLOAD_FOLDER'];
        //TODO : Replace the User_ID with USER uuid
        $folder = $baseFolder."organization/";
        if (isset($id)) {
            $folder = $folder.$id."/";
        }

        if ($ensureDir && !file_exists($folder)) {
            FileUtils::createDirectory($folder);
        }

        return $folder;
    }




    /**
     * createUpload
     *
     * Upload files from Front End and store it in temp Folder
     *
     *  @param files Array of files to upload
     *  @return JSON array of filenames
    */
    public function uploadOrgLogo($id, $file)
    {
        if (isset($file)) {
            $destFile = $this->getOrgLogoPath($id, true);
            $image = FileUtils::convetImageTypetoPNG($file);
            if ($image) {
                if (FileUtils::fileExists($destFile)) {
                    imagepng($image, $destFile.'/logo.png');
                    $image = null;
                } else {
                    mkdir($destFile);
                    imagepng($image, $destFile.'/logo.png');
                    $image = null;
                }
            }
        }
    }



    private function setupBasicOrg($org, $contactPerson, $orgPreferences)
    {

         // adding basic roles
        $returnArray['roles'] = $this->roleService->createBasicRoles($org['id']);
         // adding a user
        $returnArray['user'] = $this->userService->createAdminForOrg($org,$contactPerson,$orgPreferences);
        return $returnArray['user'];
    }

    public function saveOrganization($orgData){
        $create = TRUE;
        $result;
        if(isset($orgData['uuid'])){
            try{
                $result = $this->updateOrganization($orgData['uuid'], $orgData);
                $create = FALSE;
            }catch(ServiceException $e){
                if($e->getMessageCode() != 'org.not.found'){
                    throw $e;
                }
            }
        }
        if($create){
            $result = $this->createOrganization($orgData, NULL);
        }

        return $result;
    }

    /**
     * Update Organization API
     * @method updateOrganization
     * @param array $id ID of Organization to update
     * @param array $data
     * @return array Returns a JSON Response with Status Code and Created Organization.
     */
    public function updateOrganization($id, &$data, $files = null)
    {
        $obj = $this->table->getByUuid($id, array());
        if (is_null($obj)) {
            throw new ServiceException("Entity not found for UUID","org.not.found");
        }
        if (isset($data['contactid'])) {
            $data['contactid'] = $this->userService->getUserByUuid($data['contactid']);
        }
        $org = $obj->toArray();
        $form = new Organization();
        $changedArray = array_merge($obj->toArray(), $data);

        if(isset($changedArray['address_id'])){
            $this->addressService->updateAddress($changedArray['address_id'],$data);
        }else{
            $addressid = $this->addressService->addAddress($data);
            $changedArray['address_id'] = $addressid;
        }
        $changedArray['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $changedArray['date_modified'] = date('Y-m-d H:i:s');
        $form->exchangeArray($changedArray);
        $form->validate();
        $this->beginTransaction();
        $count = 0;

        try {
            $count = $this->table->save($form);
            if(isset($files)){
                $this->uploadOrgLogo($id,$files);
            }
            $this->commit();
            if ($count == 0) {
                return 1;
            }
        } catch (Exception $e) {
            switch (get_class($e)) {
                case "Oxzion\ValidationException":
                $this->rollback();
                throw $e;
                break;
                default:
                $this->rollback();
                throw $e;
                break;
            }
        }
        if ($obj->name != $data['name']) {
            $this->messageProducer->sendTopic(json_encode(array('new_orgname' => $data['name'], 'old_orgname' => $obj->name,'status' => $form->status)), 'ORGANIZATION_UPDATED');
        }
        if ($form->status == 'InActive') {
            $this->messageProducer->sendTopic(json_encode(array('orgname' => $obj->name,'status' => $form->status)), 'ORGANIZATION_DELETED');
        }
        return $count;
    }

    /**
     * Delete Organization Service
     * @method deleteOrganization
     * @link /organization[/:orgId]
     * @param $id ID of Organization to Delete
     * @return array success|failure response
     */
    public function deleteOrganization($id)
    {
        $obj = $this->table->getByUuid($id, array());
        if (is_null($obj)) {
            return 0;
        }
        $originalArray = $obj->toArray();
        $form = new Organization();
        $originalArray['status'] = 'Inactive';
        $form->exchangeArray($originalArray);
        $result = $this->table->save($form);
        $this->messageProducer->sendTopic(json_encode(array('orgname' => $originalArray['name'],'status' => $originalArray['status'])), 'ORGANIZATION_DELETED');
        return $result;
    }

    /**
     * GET Organization Service
     * @method getOrganization
     * @param $id ID of Organization to GET
     * @return array $data
     * <code> {
     *               id : integer,
     *               name : string,
     *               logo : string,
     *               status : String(Active|Inactive),
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created Organization.
     */
    public function getOrganization($id)
    {
        $sql = $this->getSqlObject();
        $select = $sql->select();
        $select->from('ox_organization')
        ->columns(array("*"))
        ->where(array('ox_organization.id' => $id, 'status' => "Active"));
        $response = $this->executeQuery($select)->toArray();
        if (count($response) == 0) {
            return 0;
        }

        return $response[0];
    }

    public function getOrganizationIdByUuid($uuid)
    {
        $select ="SELECT id from ox_organization where uuid = '".$uuid."'";
        $result = $this->executeQueryWithParams($select)->toArray();
        if (isset($result[0])) {
            return $result[0]['id'];
        } else {
            return null;
        }
    }

    /**
     * GET Organization Service
     * @method getOrganization
     * @param $id ID of Organization to GET
     * @return array $data
     * <code> {
     *               id : integer,
     *               name : string,
     *               logo : string,
     *               status : String(Active|Inactive),
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created Organization.
     */
    public function getOrganizationByUuid($id)
    {
        $select = "SELECT og.uuid,og.name,oa.address1,oa.address2,oa.city,oa.state,oa.country,oa.zip,og.preferences,og.contactid from ox_organization as og join ox_address as oa on og.address_id = oa.id WHERE og.uuid = '".$id."' AND og.status = 'Active'";
        $response = $this->executeQuerywithParams($select)->toArray();
        if (count($response) == 0) {
            return 0;
        } else {
            $response[0]['contactid'] = $this->getOrgContactPersonDetails($id);
        }

        return $response[0];
    }

    private function getOrgContactPersonDetails($id)
    {
        $userData = array();
        $userSelect = "SELECT ou.uuid from `ox_user` as ou where ou.id = (SELECT og.contactid from `ox_organization` as og WHERE og.uuid = '".$id."')";
        $userData = $this->executeQueryWithParams($userSelect)->toArray();
        return $userData[0]['uuid'];
    }

    /**
     * GET Organization Service
     * @method getOrganizations
     * @return array $data
     * <code> {
     *               id : integer,
     *               name : string,
     *               logo : string,
     *               status : String(Active|Inactive),
     *   } </code>
     * @return array Returns a JSON Response with Status Code and Created Organization.
     */
    public function getOrganizations($filterParams = null)
    {
        $where = "";
        $pageSize = 20;
        $offset = 0;
        $sort = "name";

        $select = "SELECT og.uuid,og.name,oa.address1,oa.address2,oa.city,oa.state,oa.country,oa.zip,og.preferences,og.contactid";
        $from = " from ox_organization as og join ox_address as oa on og.address_id = oa.id";


        $cntQuery ="SELECT count(og.id) ".$from;

        if(count($filterParams) > 0 || sizeof($filterParams) > 0){
            $filterArray = json_decode($filterParams['filter'],true);
            $where = $this->createWhereClause($filterArray,self::$orgField);
            if(isset($filterArray[0]['sort']) && count($filterArray[0]['sort']) > 0){
                $sort = $this->createSortClause($filterArray[0]['sort'],self::$orgField);
            }
            $pageSize = $filterArray[0]['take'];
            $offset = $filterArray[0]['skip'];
        }

        $where .= strlen($where) > 0 ? " AND og.status = 'Active'" : " WHERE og.status = 'Active'";
        $sort = " ORDER BY ".$sort;
        $limit = " LIMIT ".$pageSize." offset ".$offset;
        $resultSet = $this->executeQuerywithParams($cntQuery.$where);
        $count=$resultSet->toArray();
        $query =$select." ".$from." ".$where." ".$sort." ".$limit;
        $resultSet = $this->executeQuerywithParams($query)->toArray();
        for($x=0;$x<sizeof($resultSet);$x++) {
            $resultSet[$x]['contactid'] = $this->getOrgContactPersonDetails($resultSet[$x]['uuid']);
        }
        return array('data' => $resultSet, 'total' => $count[0]['count(og.id)']);
    }


    public function getUserIdList($uuidList){
        $uuidList= array_unique(array_map('current', $uuidList));
        $query = "SELECT id from ox_user where uuid in ('".implode("','", $uuidList) . "')";
        $result = $this->executeQueryWithParams($query)->toArray();
        return $result;
    }


    public function saveUser($id, $data)
    {
        $obj = $this->table->getByUuid($id, array());
        if (is_null($obj)) {
            return 0;
        }
        if (!isset($data['userid']) || empty($data['userid'])) {
            return 2;
        }
        $orgId = $obj->id;
        $userArray = $this->getUserIdList($data['userid']);
        if ($userArray) {
            $userSingleArray= array_unique(array_map('current', $userArray));

            $querystring = "SELECT u.username FROM ox_user_org as ouo
            inner join ox_user as u on u.id = ouo.user_id
            inner join ox_organization as org on ouo.org_id = org.id and org.id =".$orgId."
            where ouo.org_id =".$orgId." and ouo.user_id not in (".implode(',', $userSingleArray).") and ouo.user_id != org.contactid";
            $deletedUser = $this->executeQuerywithParams($querystring)->toArray();


            $query = "SELECT ou.username from ox_user as ou LEFT OUTER JOIN ox_user_org as our on
            our.user_id = ou.id AND our.org_id = ou.orgid and our.org_id =".$orgId."
            WHERE ou.id in (".implode(',', $userSingleArray).") AND our.org_id is Null and ou.id not in (select user_id from  ox_user_org where user_id in (".implode(',', $userSingleArray).") and org_id =".$orgId.")";
            $insertedUser = $this->executeQuerywithParams($query)->toArray();


            $this->beginTransaction();
            try {
                $query = "UPDATE ox_user as ou
                inner join ox_organization as org on org.id = ou.orgid
                and ou.id != org.contactid
                SET ou.orgid = NULL WHERE ou.id not in (".implode(',', $userSingleArray).") AND ou.orgid = $orgId";
                $resultSet = $this->executeQuerywithParams($query);

                $select = "SELECT u.id FROM ox_user_org as ouo
                inner join ox_user as u on u.id = ouo.user_id
                inner join ox_organization as org on ouo.org_id = org.id and org.id =".$orgId."
                where ouo.org_id =".$orgId." and ouo.user_id not in (".implode(',', $userSingleArray).") and ouo.user_id != org.contactid";
                $userId = $this->executeQuerywithParams($select)->toArray();

                $query = "DELETE ouo FROM ox_user_org as ouo
                inner join ox_user as u on u.id = ouo.user_id
                inner join ox_organization as org on ouo.org_id = org.id and org.id =".$orgId."
                where ouo.org_id =".$orgId." and ouo.user_id not in (".implode(',', $userSingleArray).") and ouo.user_id != org.contactid";

                $resultSet = $this->executeQuerywithParams($query);
                $insert = "INSERT INTO ox_user_org (user_id,org_id,`default`)
                SELECT ou.id,".$orgId.",case when (ou.orgid is NULL)
                then 1
                end
                from ox_user as ou LEFT OUTER JOIN ox_user_org as our on our.user_id = ou.id AND our.org_id = ou.orgid and our.org_id =".$orgId."
                WHERE ou.id in (".implode(',', $userSingleArray).") AND our.org_id is Null AND ou.id not in (select user_id from  ox_user_org where user_id in (".implode(',', $userSingleArray).") and org_id =".$orgId.")";
                $resultSet = $this->executeQuerywithParams($insert);


                $update = "UPDATE ox_user SET orgid = $orgId WHERE id in (".implode(',', $userSingleArray).") AND orgid is NULL";
                $resultSet = $this->executeQuerywithParams($update);

                if (count($userId) > 0) {
                    $userIdArray= array_unique(array_map('current', $userId));
                    $update = "UPDATE ox_user SET orgid = NULL WHERE id in (".implode(',', $userIdArray).")";
                    $resultSet = $this->executeQuerywithParams($update);
                }

                $this->commit();
            } catch (Exception $e) {
                $this->rollback();
                throw $e;
            }

            foreach ($deletedUser as $key => $value) {
                $this->messageProducer->sendTopic(json_encode(array('orgname' => $obj->name , 'status' => 'Active', 'username'=>$value["username"])), 'USERTOORGANIZATION_DELETED');
            }
            foreach ($insertedUser as $key => $value) {
                $this->messageProducer->sendTopic(json_encode(array('orgname' => $obj->name , 'status' => 'Active', 'username'=>$value["username"])), 'USERTOORGANIZATION_ADDED');
            }

            return 1;
        }
        return 0;
    }

    public function getOrgUserList($id, $filterParams = null, $baseUrl = '')
    {
        if (!isset($id)) {
            return 0;
        }

        $pageSize = 20;
        $offset = 0;
        $where = "";
        $sort = "ox_user.name";


        $query = "SELECT ox_user.uuid,ox_user.name,ox_address.address1,ox_address.address2,ox_address.city,ox_address.state,ox_address.country,ox_address.zip,ox_user.designation,
        case when (ox_organization.contactid = ox_user.id)
        then 1
        end as is_admin";
        $from = " FROM ox_user inner join ox_user_org on ox_user.id = ox_user_org.user_id left join ox_organization on ox_organization.id = ox_user_org.org_id join ox_address on ox_user.address_id = ox_address.id";


        $cntQuery ="SELECT count(ox_user.id)".$from;

        if(count($filterParams) > 0 || sizeof($filterParams) > 0){
            $filterArray = json_decode($filterParams['filter'],true);
            $where = $this->createWhereClause($filterArray,self::$userField);
            if(isset($filterArray[0]['sort']) && count($filterArray[0]['sort']) > 0){
               $sort = $this->createSortClause($filterArray[0]['sort'],self::$userField);
           }
           $pageSize = $filterArray[0]['take'];
           $offset = $filterArray[0]['skip'];
       }

       $where .= strlen($where) > 0 ? " AND ox_organization.uuid = '".$id."' AND ox_user.status = 'Active'" : " WHERE ox_organization.uuid = '".$id."' AND ox_user.status = 'Active'";

       $sort = " ORDER BY ".$sort;
       $limit = " LIMIT ".$pageSize." offset ".$offset;
       $resultSet = $this->executeQuerywithParams($cntQuery.$where);
       $count=$resultSet->toArray();
       $query =$query." ".$from." ".$where." ".$sort." ".$limit;
       $resultSet = $this->executeQuerywithParams($query)->toArray();
       for($x=0;$x<sizeof($resultSet);$x++) {
        $resultSet[$x]['icon'] = $baseUrl . "/user/profile/" . $resultSet[$x]['uuid'];
    }
    return array('data' => $resultSet,
       'total' => $count[0]['count(ox_user.id)']);
}

public function getAdminUsers($filterParams, $orgId = null)
{
    if (!isset($orgId)) {
        $orgId = AuthContext::get(AuthConstants::ORG_UUID);
    }
    if (!SecurityManager::isGranted('MANAGE_ORGANIZATION_WRITE') &&
        SecurityManager::isGranted('MANAGE_MYORG_WRITE') &&
        $orgId != AuthContext::get(AuthConstants::ORG_UUID)) {
        throw new AccessDeniedException("You do not have permissions");
}

$pageSize = 20;
$offset = 0;
$where = "";
$sort = "name";


$select = "SELECT DISTINCT ox_user.uuid,ox_user.name ";
$from = " from ox_user inner join ox_user_role as our on ox_user.id = our.user_id inner join ox_role as oro on our.role_id = oro.id inner join ox_user_org as oug on oro.org_id = oug.org_id";

$cntQuery ="SELECT count(DISTINCT ox_user.uuid)".$from;

if(count($filterParams) > 0 || sizeof($filterParams) > 0){
    $filterArray = json_decode($filterParams['filter'],true);
    $where = $this->createWhereClause($filterArray,self::$userField);
    if(isset($filterArray[0]['sort']) && count($filterArray[0]['sort']) > 0){
       $sort = $this->createSortClause($filterArray[0]['sort'],self::$userField);
   }
   $pageSize = $filterArray[0]['take'];
   $offset = $filterArray[0]['skip'];
}

$orgId = $this->getOrganizationIdByUuid($orgId);
$where .= strlen($where) > 0 ? " AND oro.org_id =".$orgId." and oro.name = 'ADMIN'" : " WHERE oro.org_id =".$orgId." and oro.name = 'ADMIN'";

$sort = " ORDER BY ".$sort;
$limit = " LIMIT ".$pageSize." offset ".$offset;
$resultSet = $this->executeQuerywithParams($cntQuery.$where);
$count=$resultSet->toArray();
$query =$select." ".$from." ".$where." ".$sort." ".$limit;
$resultSet = $this->executeQuerywithParams($query)->toArray();
return array('data' => $resultSet,
   'total' => $count[0]['count(DISTINCT ox_user.uuid)']);
}

public function getOrgGroupsList($id, $filterParams = null)
{
    if (!isset($id)) {
        return 0;
    }

    $pageSize = 20;
    $offset = 0;
    $where = "";
    $sort = "oxg.name";


    $select = "SELECT oxg.uuid,oxg.name,oxg.description,oxu.uuid as manager_id, oxg1.uuid as parent_id, oxo.uuid as org_id";
    $from = "FROM `ox_group` as oxg
    LEFT JOIN ox_user as oxu on oxu.id = oxg.manager_id
    LEFT JOIN ox_group as oxg1 on oxg.parent_id = oxg1.id
    LEFT JOIN ox_organization as oxo on oxg.org_id = oxo.id";

    $cntQuery ="SELECT count(oxg.uuid) ".$from;


    if(count($filterParams) > 0 || sizeof($filterParams) > 0){
        $filterArray = json_decode($filterParams['filter'],true);
        $where = $this->createWhereClause($filterArray,self::$groupField);
        if(isset($filterArray[0]['sort']) && count($filterArray[0]['sort']) > 0){
           $sort = $this->createSortClause($filterArray[0]['sort'],self::$groupField);
       }
       $pageSize = $filterArray[0]['take'];
       $offset = $filterArray[0]['skip'];
   }
   $orgId = $this->getOrganizationIdByUuid($id);
   if(!$orgId){
    return 0;
}
$where .= strlen($where) > 0 ? " AND oxg.org_id =".$orgId." and oxg.status = 'Active'" : " WHERE oxg.org_id =".$orgId." and oxg.status = 'Active'";

$sort = " ORDER BY ".$sort;
$limit = " LIMIT ".$pageSize." offset ".$offset;


$resultSet = $this->executeQuerywithParams($cntQuery.$where);
$count=$resultSet->toArray();
$query =$select." ".$from." ".$where." ".$sort." ".$limit;
$resultSet = $this->executeQuerywithParams($query)->toArray();

return array('data' => $resultSet,
   'total' => $count[0]['count(oxg.uuid)']);

}

public function getOrgProjectsList($id,$filterParams = null){

    if(!isset($id)){
        return 0;
    }

    $pageSize = 20;
    $offset = 0;
    $where = "";
    $sort = "oxp.name";


    $select = "SELECT oxp.uuid,oxp.name,oxp.description,oxu.uuid as manager_id, oxo.uuid as org_id";
    $from = "FROM `ox_project` as oxp
    LEFT JOIN ox_user as oxu on oxu.id = oxp.manager_id
    LEFT JOIN ox_organization as oxo on oxp.org_id = oxo.id";

    $cntQuery ="SELECT count(oxp.uuid) ".$from;


    if(count($filterParams) > 0 || sizeof($filterParams) > 0){
        $filterArray = json_decode($filterParams['filter'],true);
        $where = $this->createWhereClause($filterArray,self::$projectField);
        if(isset($filterArray[0]['sort']) && count($filterArray[0]['sort']) > 0){
           $sort = $this->createSortClause($filterArray[0]['sort'],self::$projectField);
       }
       $pageSize = $filterArray[0]['take'];
       $offset = $filterArray[0]['skip'];
   }
   $orgId = $this->getOrganizationIdByUuid($id);
   if(!$orgId){
    return 0;
}
$where .= strlen($where) > 0 ? " AND oxp.org_id =".$orgId." and oxp.isdeleted != 1" : " WHERE oxp.org_id =".$orgId." and oxp.isdeleted != 1";

$sort = " ORDER BY ".$sort;
$limit = " LIMIT ".$pageSize." offset ".$offset;


$resultSet = $this->executeQuerywithParams($cntQuery.$where);
$count=$resultSet->toArray();
$query =$select." ".$from." ".$where." ".$sort." ".$limit;
$resultSet = $this->executeQuerywithParams($query)->toArray();

return array('data' => $resultSet,
   'total' => $count[0]['count(oxp.uuid)']);

}

public function getOrgAnnouncementsList($id,$filterParams = null){

    if(!isset($id)){
        return 0;
    }

    $pageSize = 20;
    $offset = 0;
    $where = "";
    $sort = "oxa.name";


    $select = "SELECT oxa.uuid,oxa.name,oxa.description,oxa.end_date,oxa.start_date,oxa.media_type,oxa.media,oxo.uuid as org_id";
    $from = "FROM `ox_announcement` as oxa
    LEFT JOIN ox_organization as oxo on oxa.org_id = oxo.id";

    $cntQuery ="SELECT count(oxa.uuid) ".$from;


    if(count($filterParams) > 0 || sizeof($filterParams) > 0){
        $filterArray = json_decode($filterParams['filter'],true);
        $where = $this->createWhereClause($filterArray,self::$announcementField);
        if(isset($filterArray[0]['sort']) && count($filterArray[0]['sort']) > 0){
           $sort = $this->createSortClause($filterArray[0]['sort'],self::$announcementField);
       }
       $pageSize = $filterArray[0]['take'];
       $offset = $filterArray[0]['skip'];
   }
   $orgId = $this->getOrganizationIdByUuid($id);
   if(!$orgId){
    return 0;
}

$where .= strlen($where) > 0 ? " AND oxa.org_id =".$orgId." and oxa.end_date >= curdate() and oxa.status = 1" : " WHERE oxa.org_id =".$orgId." and oxa.end_date >= curdate() and oxa.status = 1";

$sort = " ORDER BY ".$sort;
$limit = " LIMIT ".$pageSize." offset ".$offset;


$resultSet = $this->executeQuerywithParams($cntQuery.$where);
$count=$resultSet->toArray();
$query =$select." ".$from." ".$where." ".$sort." ".$limit;
$resultSet = $this->executeQuerywithParams($query)->toArray();

return array('data' => $resultSet,
   'total' => $count[0]['count(oxa.uuid)']);

}


public function getOrgRolesList($id, $filterParams = null)
{
    if (!isset($id)) {
        return 0;
    }

    $pageSize = 20;
    $offset = 0;
    $where = "";
    $sort = "oxr.name";


    $select = "SELECT oxr.uuid,oxr.name,oxr.description,oxr.is_system_role,oxo.uuid as org_id";
    $from = "FROM `ox_role` as oxr
    LEFT JOIN ox_organization as oxo on oxr.org_id = oxo.id";

    $cntQuery ="SELECT count(oxr.uuid) ".$from;


    if(count($filterParams) > 0 || sizeof($filterParams) > 0){
        $filterArray = json_decode($filterParams['filter'],true);
        $where = $this->createWhereClause($filterArray,self::$roleField);
        if(isset($filterArray[0]['sort']) && count($filterArray[0]['sort']) > 0){
           $sort = $this->createSortClause($filterArray[0]['sort'],self::$roleField);
       }
       $pageSize = $filterArray[0]['take'];
       $offset = $filterArray[0]['skip'];
   }

   $orgId = $this->getOrganizationIdByUuid($id);
   if(!$orgId){
    return 0;
}

$where .= strlen($where) > 0 ? " AND oxr.org_id =".$orgId : " WHERE oxr.org_id =".$orgId;

$sort = " ORDER BY ".$sort;

$limit = " LIMIT ".$pageSize." offset ".$offset;
$resultSet = $this->executeQuerywithParams($cntQuery.$where);

$count=$resultSet->toArray();
$query =$select." ".$from." ".$where." ".$sort." ".$limit;
$resultSet = $this->executeQuerywithParams($query)->toArray();

return array('data' => $resultSet,
   'total' => $count[0]['count(oxr.uuid)']);
}


private function createWhereClause($filterArray, $fieldName = null)
{
    if (isset($filterArray[0]['filter'])) {
        $filterlogic = isset($filterArray[0]['filter']['logic']) ? $filterArray[0]['filter']['logic'] : "AND" ;
        $filterList = $filterArray[0]['filter']['filters'];
        $where = " WHERE ".FilterUtils::filterArray($filterList, $filterlogic, $fieldName);
        return $where;
    } else {
        return "";
    }
}

private function createSortClause($sort, $fieldName = null)
{
    $sort = FilterUtils::sortArray($sort, $fieldName);
    return $sort;
}
}
