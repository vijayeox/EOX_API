<?php
namespace Announcement\Service;

use Announcement\Model\Announcement;
use Announcement\Model\AnnouncementTable;
use Exception;
use Oxzion\AccessDeniedException;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\Security\SecurityManager;
use Oxzion\ServiceException;
use Oxzion\OxServiceException;
use Oxzion\EntityNotFoundException;
use Oxzion\Service\AbstractService;
use Oxzion\Service\AccountService;
use Oxzion\Utils\FilterUtils;
use Oxzion\Utils\UuidUtil;

/**
 * Announcement Service
 */
class AnnouncementService extends AbstractService
{
    /**
     * @ignore ANNOUNCEMENT_FOLDER
     */
    const ANNOUNCEMENT_FOLDER = "/announcements/";

    /**
     * @ignore table
     */
    private $table;

    /**
     * @ignore __construct
     */
    public function __construct($config, $dbAdapter, AnnouncementTable $table, AccountService $accountService)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
        $this->accountService = $accountService;
    }

    /**
     * Create Announcement Service
     * @param array $data Array of elements as shown</br>
     * <code> name : string,
     *        status : string,
     *        description : string,
     *        link : string,
     *        start_date : dateTime (ISO8601 format yyyy-mm-ddThh:mm:ss),
     *        end_date : dateTime (ISO8601 format yyyy-mm-ddThh:mm:ss)
     *        media_type : string,
     *        media_location : string,
     *        teams : [{'id' : integer}.....multiple*],
     *        type: enum('HOMESCREEN','ANNOUNCEMENT')
     * </code>
     * @return integer 0|$id of Announcement Created
     */
    public function createAnnouncement(&$inputData, $params = null)
    {
        $data = $inputData;
        if (isset($params['accountId'])) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') &&
                ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You do not have permissions create announcement");
            } else {
                $data['account_id'] = $this->getIdFromUuid('ox_account', $params['accountId']);
            }
        } else {
            $data['account_id'] = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }

        if (isset($data['type'])) {
            if (!($data['type'] == 'ANNOUNCEMENT' || $data['type'] == 'HOMESCREEN')) {
                throw new ServiceException("Announcement Type must be ANNOUNCEMENT or HOMESCREEN", 'invalid.type.given', OxServiceException::ERR_CODE_PRECONDITION_FAILED);
            }
        }

        try {
            $data['name'] = isset($data['name']) ? $data['name'] : null;
            if (isset($data['account_id'])) {
                $select = "SELECT uuid,name,status,end_date from ox_announcement where name = '" . $data['name'] . "' and account_id = " . $data['account_id'] . " and end_date >= curdate()";
            } else {
                $select = "SELECT uuid,name,status,end_date from ox_announcement where name = '" . $data['name'] . "' and end_date >= curdate()";
            }
            $result = $this->executeQuerywithParams($select)->toArray();
            if (count($result) > 0) {
                throw new ServiceException("Announcement already exists", "announcement.exists", OxServiceException::ERR_CODE_PRECONDITION_FAILED);
            }
            $form = new Announcement();
            $inputData['uuid'] = $data['uuid'] = UuidUtil::uuid();
            $data['created_id'] = AuthContext::get(AuthConstants::USER_ID);
            $data['start_date'] = isset($data['start_date']) ? $data['start_date'] : date('Y-m-d');
            $data['status'] = isset($data['status']) ? $data['status'] : 1;
            $data['end_date'] = isset($data['end_date']) ? $data['end_date'] : date('Y-m-d', strtotime("+7 day"));
            $data['created_date'] = date('Y-m-d');
            $this->logger->info('Modified announcement data before insert- ' . print_r($data, true));
            $form->exchangeArray($data);
            $form->validate();
            $this->beginTransaction();
            $count = 0;
            $count = $this->table->save($form);
            if ($count == 0) {
                throw new ServiceException("Failed to create", "failed.announcement.create");
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Update Announcement
     * @method PUT
     * @param integer $id ID of Announcement to update
     * @param array $data Data Array as Follows:
     * @throws  Exception
     * <code>
     * {
     *  integer id,
     *  string name,
     *  string status,
     *  string description,
     *  string link,
     *  dateTime start_date (ISO8601 format yyyy-mm-ddThh:mm:ss),
     *  dateTime end_date (ISO8601 format yyyy-mm-ddThh:mm:ss)
     *  string media_type,
     *  string media_location,
     *  teams : [{'id' : integer}.....multiple]
     *  type: enum('HOMESCREEN','ANNOUNCEMENT')
     * }
     * </code>
     * @return array Returns the Created Announcement.
     */
    public function updateAnnouncement($uuid, &$inputData, $accountId = null)
    {
        $data = $inputData;
        if (isset($accountId)) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') &&
                ($accountId != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You do not have permissions to update announcement");
            } else {
                $accountId = $this->getIdFromUuid('ox_account', $accountId);
            }
        } else {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE')) {
                $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
            }
        }
        $obj = $this->table->getByUuid($uuid, array());
        if (is_null($obj)) {
            throw new ServiceException("Announcement not found", "announcement.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
        }
        $originalArray = $obj->toArray();
        if (isset($accountId)) {
            if ($accountId != $originalArray['account_id']) {
                throw new ServiceException("Announcement does not belong to the account", "announcement.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
            }
        }
        if (isset($data['type'])) {
            if (!($data['type'] == 'ANNOUNCEMENT' || $data['type'] == 'HOMESCREEN')) {
                throw new ServiceException("Announcement Type must be ANNOUNCEMENT or HOMESCREEN", 'invalid.type.given', OxServiceException::ERR_CODE_NOT_FOUND);
            }
        }
        $form = new Announcement();
        $data = array_merge($originalArray, $data);
        $data['id'] = $originalArray['id'];
        $this->logger->info('Modified announcement data before update- ' . print_r($data, true));
        $form->exchangeArray($data);
        $form->validate();
        try {
            $this->beginTransaction();
            $this->table->save($form);

            $sql = $this->getSqlObject();
            $update = $sql->update('ox_user_announcement_mapper');
            $update->set(['view' => 0]);
            $update->where(['announcement_id' => $data['id']]);
            $this->executeUpdate($update);

            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * @ignore updateTeams
     */
    protected function updateTeams($announcementId, $teams)
    {
        $oldTeams = array_column($this->getTeamsByAnnouncement($announcementId), 'team_id');
        $newTeams = array_column($teams, 'id');
        $teamsRemoved = array_diff($oldTeams, $newTeams);
        if (count($teamsRemoved) > 0) {
            $result['delete'] = $this->deleteTeamsByAnnouncement($announcementId, $teamsRemoved);
            if ($result['delete'] != count($teamsRemoved) || count($teamsRemoved) == 0) {
                return 0;
            }
        }
        $result['insert'] = $this->insertAnnouncementForTeam($announcementId, $teams);
    }

    public function markAsRead($announcementId)
    {
        $sql = $this->getSqlObject();
        $update = $sql->update('ox_user_announcement_mapper');
        $update->set(['view' => 1]);
        $update->where(['announcement_id' => $this->getIdFromUuid('ox_announcement', $announcementId), 'user_id' => AuthContext::get(AuthConstants::USER_ID)]);
        $this->executeUpdate($update);
    }

    /**
     * @ignore deleteTeamsByAnnouncement
     */
    protected function deleteTeamsByAnnouncement($announcementId, $teamIdList)
    {
        $rowsAffected = 0;
        foreach ($teamIdList as $key => $teamId) {
            $sql = $this->getSqlObject();
            $delete = $sql->delete('ox_announcement_team_mapper');
            $delete->where(['announcement_id' => $announcementId, 'team_id' => $teamId]);
            $result = $this->executeUpdate($delete);
            if ($result->getAffectedRows() == 0) {
                break;
            }
            $rowsAffected++;
        }
        return $rowsAffected;
    }

    /**
     * @ignore getTeamsByAnnouncement
     */
    protected function getTeamsByAnnouncement($announcementId)
    {
        $sql = $this->getSqlObject();
        $select = $sql->select();
        $select->from('ox_announcement_team_mapper')
            ->columns(array("team_id", "announcement_id"))
            ->where(array('ox_announcement_team_mapper.announcement_id' => $announcementId));
        return $this->executeQuery($select)->toArray();
    }

    /**
     * @ignore insertAnnouncementForTeam
     */
    public function insertAnnouncementForTeam($announcementId, $teams)
    {
        if (!$teams || empty($teams)) {
            return;
        }
        try {
            $this->beginTransaction();
            $teamSingleArray = array_unique(array_map('current', $teams));
            $delete = $this->getSqlObject()
                ->delete('ox_announcement_team_mapper')
                ->where(['announcement_id' => $announcementId]);
            $result = $this->executeQueryString($delete);
            $query = "INSERT into ox_announcement_team_mapper(announcement_id,team_id)
                      SELECT $announcementId, id
                        from ox_team
                        where ox_team.uuid in (" . implode(',', $teamSingleArray) . ")";
            $resultInsert = $this->runGenericQuery($query);
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    protected function getAnnouncementIdBYUuid($uuid)
    {
        $select = "SELECT id from `ox_announcement` where uuid = '" . $uuid . "'";
        $id = $this->executeQuerywithParams($select)->toArray();
        if (count($id) > 0) {
            return $id[0]['id'];
        }
        throw new EntityNotFoundException("Announcement not found");
    }

    /**
     * Delete Announcement
     * @param integer $id ID of Announcement to Delete
     * @return int 0=>Failure | $id;
     */
    public function deleteAnnouncement($uuid, $params)
    {
        if (isset($params['accountId'])) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') &&
                ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You do not have permissions delete announcement");
            } else {
                $params['accountId'] = $this->getIdFromUuid('ox_account', $params['accountId']);
            }
        } else {
            $params['accountId'] = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }
        $obj = $this->table->getByUuid($uuid, array());
        try {
            $this->beginTransaction();
            $sql = $this->getSqlObject();
            $delete = $sql->delete('ox_announcement');
            $delete->where(['uuid' => $uuid, 'account_id' => $params['accountId']]);
            $result = $this->executeUpdate($delete);
            if ($result->getAffectedRows() == 0) {
                throw new ServiceException("Announcement not found", "announcement.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
            }
            $delete = "DELETE FROM ox_attachment where uuid = '" . $obj->media . "'";
            $this->executeQuerywithParams($delete);
            $select = "SELECT count(announcement_id) from `ox_announcement_team_mapper` where announcement_id = " . $obj->id;
            $count = $this->executeQuerywithParams($select)->toArray();
            if ($count[0]['count(announcement_id)'] > 0) {
                $sql = $this->getSqlObject();
                $delete = $sql->delete('ox_announcement_team_mapper');
                $delete->where(['announcement_id' => $obj->id]);
                $result = $this->executeUpdate($delete);
                if ($result->getAffectedRows() == 0 && $obj->type == 'ANNOUNCEMENT') {
                    throw new ServiceException("Failed to delete", "failed.announcement.delete");
                }
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * GET List Announcement
     * @method GET
     * @return array $dataget list of Announcements by User
     * <code></br>
     * {
     *  string name,
     *  string status,
     *  string link,
     *  string description,
     *  dateTime start_date (ISO8601 format yyyy-mm-ddThh:mm:ss),
     *  dateTime end_date (ISO8601 format yyyy-mm-ddThh:mm:ss)
     *  string media_type,
     *  string media_location,
     *  teams : [{'id' : integer}.....multiple]
     * }
     * </code>
     */
    public function getAnnouncements($params)
    {
        if (isset($params['accountId'])) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') &&
                ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You do not have permissions to get the announcement list");
            } else {
                $accountId = $this->getIdFromUuid('ox_account', $params['accountId']);
            }
        } else {
            $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }
        $select = "SELECT
            *
        FROM
        (
            SELECT
                a.id,a.uuid,a.name,a.account_id,a.status,a.description,a.link,a.start_date,a.end_date,a.media_type,a.media
            FROM
                ox_announcement as a
            LEFT JOIN ox_announcement_team_mapper as ogm
            ON a.id = ogm.announcement_id
            LEFT JOIN ox_user_team as oug
            ON ogm.team_id = oug.team_id
            WHERE oug.avatar_id = " . AuthContext::get(AuthConstants::USER_ID) . "
            AND a.account_id = ".$accountId."
            AND a.end_date >= curdate()
                UNION
            SELECT a.id,a.uuid,a.name,a.account_id,a.status,a.description,a.link,a.start_date,a.end_date,a.media_type,a.media
            FROM
                ox_announcement as a
            LEFT JOIN ox_announcement_team_mapper as ogm
            ON a.id = ogm.announcement_id
            WHERE ogm.team_id is NULL
            AND a.account_id = " . $accountId . "
            AND a.end_date >= curdate( )
        ) as a
        ORDER BY a.id DESC";
        return $this->executeQuerywithParams($select)->toArray();
    }

    /**
     * GET Announcement
     * @param integer $id ID of the Announcement
     * @return array $dataget list of Announcements by User
     * <code></br>
     * {
     *  string name,
     *  string status,
     *  string description,
     *  string link,
     *  dateTime start_date (ISO8601 format yyyy-mm-ddThh:mm:ss),
     *  dateTime end_date (ISO8601 format yyyy-mm-ddThh:mm:ss)
     *  string media_type,
     *  string media_location,
     *  teams : [{'id' : integer}.....multiple]
     * }
     * </code>
     */
    public function getAnnouncement($id, $params)
    {
        if (isset($params['accountId'])) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') &&
                ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You do not have permissions to get the announcement list");
            } else {
                $accountId = $this->getIdFromUuid('ox_account', $params['accountId']);
            }
        } else {
            $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }
        $select = "SELECT DISTINCT a.uuid,a.name,acct.uuid as accountId,a.status,a.description,a.link,
                        a.start_date,a.end_date,a.media_type,a.media
                    from ox_announcement as a
                    left join ox_account acct on acct.id = a.account_id
                    left join ox_announcement_team_mapper as ogm on a.id = ogm.announcement_id
                    left join ox_user_team as oug on ogm.team_id=oug.team_id
                    where a.account_id = " . $accountId . " AND a.uuid = '" . $id . "' AND a.end_date >= curdate()";
        $response = $this->executeQuerywithParams($select)->toArray();
        if (empty($response)) {
            return array();
        }
        return $response[0];
    }

    public function getAnnouncementsList($filterParams, $params)
    {
        if (isset($params['accountId'])) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') &&
                ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You do not have permissions to get the announcement list");
            } else {
                $accountId = $this->getIdFromUuid('ox_account', $params['accountId']);
            }
        } else {
            $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }
        if (isset($params['type'])) {
            if (!($params['type'] == 'ANNOUNCEMENT' || $params['type'] == 'HOMESCREEN')) {
                throw new ServiceException("Announcement Type must be ANNOUNCEMENT or HOMESCREEN", 'invalid.type.given', OxServiceException::ERR_CODE_PRECONDITION_FAILED);
            }
        } else {
            throw new ServiceException("Announcement Type must be specified", 'type.is.required', OxServiceException::ERR_CODE_PRECONDITION_FAILED);
        }
        $where = "";
        $pageSize = 20;
        $offset = 0;
        $sort = "start_date DESC,created_date DESC";
        $cntQuery = "SELECT count(id) FROM `ox_announcement` ann";
        if (count($filterParams) > 0 || sizeof($filterParams) > 0) {
            $filterArray = json_decode($filterParams['filter'], true);
            if (isset($filterArray[0]['filter'])) {
                $filterlogic = isset($filterArray[0]['filter']['logic']) ? $filterArray[0]['filter']['logic'] : "AND";
                $filterList = $filterArray[0]['filter']['filters'];
                $where = " WHERE " . FilterUtils::filterArray($filterList, $filterlogic);
            }
            if (isset($filterArray[0]['sort']) && count($filterArray[0]['sort']) > 0) {
                $sort = $filterArray[0]['sort'];
                $sort = FilterUtils::sortArray($sort);
            }
            $pageSize = $filterArray[0]['take'];
            $offset = $filterArray[0]['skip'];
        }
        if ($params['type'] == 'ANNOUNCEMENT') {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE')) {
                $where .= strlen($where) > 0 ? " AND " : "WHERE ";
                $where .= "account_id =" . $accountId . " AND start_date <= curdate() AND
                            end_date >= curdate() AND ann.type ='ANNOUNCEMENT'";
            } else {
                $where .= strlen($where) > 0 ? " AND " : "WHERE ";
                $where .= "start_date <= curdate() AND end_date >= curdate() AND
                            ann.type ='ANNOUNCEMENT' AND account_id IN (".$accountId.",null)";
            }
        } else {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE')) {
                $where .= strlen($where) > 0 ? " AND " : "WHERE ";
                $where .= "account_id =" . $accountId . " AND start_date <= curdate() AND end_date >= curdate() AND ann.type ='HOMESCREEN'";
            } else {
                $where .= strlen($where) > 0 ? " AND " : "WHERE ";
                $where .= "start_date <= curdate() AND end_date >= curdate() AND ann.type ='HOMESCREEN' AND account_id IN (".$accountId.",null)" ;
            }
        }
        $sort = " ORDER BY " . $sort;
        $limit = " LIMIT " . $pageSize . " offset " . $offset;
        $this->logger->info("Executing query - $cntQuery$where");
        $resultSet = $this->executeQuerywithParams($cntQuery ." ".$where);
        $count = $resultSet->toArray()[0]['count(id)'];
        $query = "SELECT ann.uuid, ann.name, a.uuid as accountId, ann.status, ann.description,
                        ann.link, ann.start_date, ann.end_date, ann.media_type, ann.media, ann.type , uam.view
                    FROM `ox_announcement` ann
                    LEFT OUTER JOIN ox_user_announcement_mapper uam on uam.announcement_id = ann.id AND uam.user_id = ".AuthContext::get(AuthConstants::USER_ID)."
                    LEFT OUTER JOIN ox_account a on a.id = ann.account_id " . $where . " " . $sort . " " . $limit;
        $this->logger->info("Executing query - $query");
        $resultSet = $this->executeQuerywithParams($query)->toArray();
        return array('data' => $resultSet, 'total' => $count);
    }

    public function getAnnouncementTeamList($params, $filterParams = null)
    {
        if (isset($params['accountId'])) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') &&
                ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You do not have permissions to get the team list of announcement");
            } else {
                $accountId = $this->getIdFromUuid('ox_account', $params['accountId']);
            }
        } else {
            $accountId = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }
        $pageSize = 20;
        $offset = 0;
        $where = "";
        $sort = "ox_team.name";
        $query = "SELECT ox_team.uuid,ox_team.name";
        $from = " FROM ox_team left  join ox_announcement_team_mapper on ox_team.id = ox_announcement_team_mapper.team_id left  join ox_announcement on ox_announcement.id = ox_announcement_team_mapper.announcement_id";
        $cntQuery = "SELECT count(ox_team.id)" . $from;
        if (count($filterParams) > 0 || sizeof($filterParams) > 0) {
            $filterArray = json_decode($filterParams['filter'], true);
            if (isset($filterArray[0]['filter'])) {
                $filterlogic = $filterArray[0]['filter']['logic'];
                $filterList = $filterArray[0]['filter']['filters'];
                $where = " WHERE " . FilterUtils::filterArray($filterList, $filterlogic);
            }
            if (isset($filterArray[0]['sort']) && count($filterArray[0]['sort']) > 0) {
                $sort = $filterArray[0]['sort'];
                $sort = FilterUtils::sortArray($sort);
            }
            $pageSize = $filterArray[0]['take'];
            $offset = $filterArray[0]['skip'];
        }
        $where .= strlen($where) > 0 ? " AND ox_announcement.uuid = '" . $params['announcementId'] . "' AND ox_announcement.end_date >= now() AND ox_team.status = 1 AND ox_announcement.account_id = " . $accountId : " WHERE ox_announcement.uuid = '" . $params['announcementId'] . "' AND  ox_team.status = 1 AND ox_announcement.account_id = " . $accountId;
        $sort = " ORDER BY " . $sort;
        $limit = " LIMIT " . $pageSize . " offset " . $offset;
        $resultSet = $this->executeQuerywithParams($cntQuery . $where);
        $count = $resultSet->toArray()[0]['count(ox_team.id)'];
        $query = $query . " " . $from . " " . $where . " " . $sort . " " . $limit;
        $resultSet = $this->executeQuerywithParams($query);
        return array('data' => $resultSet->toArray(),
            'total' => $count);
    }

    public function saveTeam($params, $data)
    {
        if (isset($params['accountId'])) {
            if (!SecurityManager::isGranted('MANAGE_ACCOUNT_WRITE') &&
                ($params['accountId'] != AuthContext::get(AuthConstants::ACCOUNT_UUID))) {
                throw new AccessDeniedException("You do not have permissions to add teams to announcement");
            } else {
                $params['accountId'] = $this->getIdFromUuid('ox_account', $params['accountId']);
            }
        } else {
            $params['accountId'] = AuthContext::get(AuthConstants::ACCOUNT_ID);
        }
        $obj = $this->table->getByUuid($params['announcementId'], array());
        if (is_null($obj)) {
            throw new ServiceException("Announcement not found", "announcement.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
        }
        if (isset($params['accountId'])) {
            print("Accounts - " .$params['accountId'] != $obj->account_id."\n");
            if ($params['accountId'] != $obj->account_id) {
                throw new ServiceException("Announcement does not belong to the account", "announcement.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
            }
        } else {
            throw new ServiceException("Account does not exist", "account.not.found", OxServiceException::ERR_CODE_NOT_FOUND);
        }
        $announcementId = $obj->id;
        $accountId = $params['accountId'];
        $teamSingleArray = array_column($data['teams'], 'uuid');
        $delete = "DELETE oag FROM ox_announcement_team_mapper as oag
            inner join ox_team as og on oag.team_id = og.id
            where og.uuid not in ('" . implode("','", $teamSingleArray) . "') and oag.announcement_id = " . $announcementId . " and og.account_id =" . $accountId;
        $this->executeQuerywithParams($delete);

        $query = "INSERT into ox_announcement_team_mapper(announcement_id, team_id)
            SELECT " . $announcementId . ",og.id from ox_team as og
            LEFT OUTER JOIN ox_announcement_team_mapper as oag on og.id = oag.team_id and oag.announcement_id = " . $announcementId . "
            where og.uuid in ('" . implode("','", $teamSingleArray) . "') and og.account_id = " . $accountId . " and og.status = 'Active' and oag.announcement_id is null";
        $this->runGenericQuery($query);

        $delete = "DELETE oua FROM ox_user_announcement_mapper as oua
            left outer join ox_announcement_team_mapper as oat on oat.announcement_id = oua.announcement_id
            left outer join ox_user_team as ot on oat.team_id= ot.team_id and ot.avatar_id=oua.user_id
            where ot.avatar_id is null and oua.announcement_id = " . $announcementId;
        $this->executeQuerywithParams($delete);

        $query = "INSERT into ox_user_announcement_mapper(announcement_id, user_id)
            SELECT " . $announcementId . ", ou.id from ox_user as ou
            LEFT JOIN ox_user_team as ut on ut.avatar_id = ou.id
            LEFT JOIN ox_team as ot on ot.id = ut.team_id
            LEFT JOIN ox_announcement_team_mapper as oat on oat.team_id = ot.id
            LEFT JOIN ox_announcement as oa on oa.id = oat.announcement_id
            LEFT OUTER JOIN ox_user_announcement_mapper as oua on oua.announcement_id = oat.announcement_id and oua.user_id = ou.id
            where oua.user_id is null and oa.id = " . $announcementId;
        $this->runGenericQuery($query);
    }

    public function getHomescreenAnnouncementList($filterParams, $params)
    {
        $accountId = null;
        if (isset($params['subdomain'])) {
            $query = "SELECT id from ox_account where subdomain ='".$params['subdomain']."'";
            $resultSet = $this->executeQuerywithParams($query)->toArray();
            if (empty($resultSet)) {
                $accountId = null;
            } else {
                $accountId = $resultSet[0]['id'];
            }
        }
        $where = "";
        $pageSize = 20;
        $offset = 0;
        $sort = "name";
        $cntQuery = "SELECT count(id) FROM `ox_announcement` ann";
        if (count($filterParams) > 0 || sizeof($filterParams) > 0) {
            $filterArray = json_decode($filterParams['filter'], true);
            if (isset($filterArray[0]['filter'])) {
                $filterlogic = isset($filterArray[0]['filter']['logic']) ? $filterArray[0]['filter']['logic'] : "AND";
                $filterList = $filterArray[0]['filter']['filters'];
                $where = " WHERE " . FilterUtils::filterArray($filterList, $filterlogic);
            }
            if (isset($filterArray[0]['sort']) && count($filterArray[0]['sort']) > 0) {
                $sort = $filterArray[0]['sort'];
                $sort = FilterUtils::sortArray($sort);
            }
            $pageSize = $filterArray[0]['take'];
            $offset = $filterArray[0]['skip'];
        }
        $account = isset($accountId)?",".$accountId:"";
        $where .= strlen($where) > 0 ? " AND " : " WHERE ";
        $where .= "account_id in (null".$account.") AND end_date >= curdate() AND ann.type ='HOMESCREEN'";

        $sort = " ORDER BY " . $sort;
        $limit = " LIMIT " . $pageSize . " offset " . $offset;
        $resultSet = $this->executeQuerywithParams($cntQuery . $where);
        $count = $resultSet->toArray()[0]['count(id)'];
        $query = "SELECT ann.uuid, ann.name, a.uuid as accountId, ann.status, ann.description,
                        ann.link, ann.start_date, ann.end_date, ann.media_type, ann.media
                    FROM `ox_announcement` ann
                    LEFT JOIN ox_account a on a.id = ann.account_id " . $where . " " . $sort . " " . $limit;
        $resultSet = $this->executeQuerywithParams($query)->toArray();
        return array('data' => $resultSet, 'total' => $count);
    }

}