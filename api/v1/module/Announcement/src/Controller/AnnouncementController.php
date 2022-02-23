<?php
/**
 * Announcement Api
 */

namespace Announcement\Controller;

use Announcement\Model\Announcement;
use Announcement\Model\AnnouncementTable;
use Announcement\Service\AnnouncementService;
use Attachment\Service\AttachmentService;
use Oxzion\Controller\AbstractApiController;
use Zend\Db\Adapter\AdapterInterface;
use Exception;

/**
 * Announcement Controller
 */
class AnnouncementController extends AbstractApiController
{
    /**
     * @var AnnouncementService Instance of Announcement Service
     */
    private $announcementService;
    /**
     * @ignore __construct
     */
    public function __construct(AnnouncementTable $table, AnnouncementService $announcementService,AdapterInterface $dbAdapter,AttachmentService $attachmentService)
    {
        parent::__construct($table, Announcement::class);
        $this->setIdentifierName('announcementId');
        $this->announcementService = $announcementService;
        $this->attachmentService = $attachmentService;
        $this->log = $this->getLogger();
    }
    /**
     * Create Announcement API
     * @api
     * @link /announcement
     * @method POST
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
     * </code>
     * @return array Returns a JSON Response with Status Code and Created Announcement.</br>
     * <code> status : "success|error",
     *        data : array Created Announcement Object
     * </code>
     */
    public function create($data)
    {
        $params = $this->params()->fromRoute();
        $this->log->info("-> \nCreate announcement - " . print_r($data, true) . "Parameters - " . print_r($params, true));
        try {
            $this->announcementService->createAnnouncement($data, $params);
            return $this->getSuccessResponseWithData($data, 201);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }
    /**
     * GET List Announcement API
     * @api
     * @link /announcement
     * @method GET
     * @return array $dataget list of Announcements by User
     * <code>
     * {
     *  string name,
     *  string status,
     *  string description,
     *  link : string,
     *  dateTime start_date (ISO8601 format yyyy-mm-ddThh:mm:ss),
     *  dateTime end_date (ISO8601 format yyyy-mm-ddThh:mm:ss)
     *  string media_type,
     *  string media_location,
     *  teams : [{'id' : integer}.....multiple]
     * }
     * </code>
     */
    public function getList()
    {
        $params = $this->params()->fromRoute();
        print_r($params);
        $this->log->info(__CLASS__ . "-> Get announcement list- " . print_r($params, true));
        try {
            $result = $this->announcementService->getAnnouncements($params);
            return $this->getSuccessResponseWithData($result);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Update Announcement API
     * @api
     * @link /announcement[/:announcementId]
     * @method PUT
     * @param array $id ID of Announcement to update
     * @param array $data
     * <code>
     * {
     *  integer id,
     *  string name,
     *  string status,
     *  string description,
     *  string link
     *  dateTime start_date (ISO8601 format yyyy-mm-ddThh:mm:ss),
     *  dateTime end_date (ISO8601 format yyyy-mm-ddThh:mm:ss)
     *  string media_type,
     *  string media_location,
     *  teams : [{'id' : integer}.....multiple]
     * }
     * </code>
     * @return array Returns a JSON Response with Status Code and Created Announcement.
     */
    public function update($id, $data)
    {
        $params = $this->params()->fromRoute();
        $this->log->info(__CLASS__ . "-> \nUpdate announcement - " . print_r($data, true) . "Parameters - " . print_r($params, true));
        try {
            $accountId = isset($params['accountId']) ? $params['accountId'] : null;
            $this->announcementService->updateAnnouncement($id, $data, $accountId);
            return $this->getSuccessResponseWithData($data, 200);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }
    /**
     * Delete Announcement API
     * @api
     * @link /announcement[/:announcementId]
     * @method DELETE
     * @param $id ID of Announcement to Delete
     * @return array success|failure response
     */
    public function delete($id)
    {
        $params = $this->params()->fromRoute();
        $this->log->info(__CLASS__ . "-> \nDelete announcement - " . print_r($id, true) . "Parameters - " . print_r($params, true));
        try {
            $this->announcementService->deleteAnnouncement($id, $params);
            return $this->getSuccessResponse();
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }
    /**
     * GET Announcement API
     * @api
     * @link /announcement[/:announcementId]
     * @method GET
     * @param $id ID of Announcement to Delete
     * @return array $data
     * <code>
     * {
     *  integer id,
     *  string name,
     *  integer account_id,
     *  string status,
     *  string description,
     *  string link,
     *  dateTime start_date (ISO8601 format yyyy-mm-ddThh:mm:ss),
     *  dateTime end_date (ISO8601 format yyyy-mm-ddThh:mm:ss)
     *  string media_type,
     *  string media_location
     * }
     * </code>
     * @return array Returns a JSON Response with Status Code and Created Announcement.
     */
    public function get($id)
    {
        $params = $this->params()->fromRoute();
        $this->log->info(__CLASS__ . "-> \nGet announcement - " . print_r($id, true) . "Parameters - " . print_r($params, true));
        try {
            $result = $this->announcementService->getAnnouncement($id, $params);
            return $this->getSuccessResponseWithData($result);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }
    /**
     * GET List of All Announcement API
     * @api
     * @link /announcement
     * @method GET
     * @return array $dataget list of Announcements
     */
    public function announcementListAction()
    {
        $filterParams = $this->params()->fromQuery();
        $params = $this->params()->fromRoute();
        
        try {
            $result = $this->announcementService->getAnnouncementsList($filterParams, $params);
            
            $i=0;
            foreach($result['data'] as $key=>$value)
            {
                $media_uuid=$value['media'];
                $result_attachment = $this->attachmentService->getAttachmentDetails($media_uuid);
                $result['data'][$i]['upload']=$result_attachment;
                $i++;
            }
            
            return $this->getSuccessResponseDataWithPagination($result['data'], $result['total']);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    public function announcementToTeamAction()
    {
        $params = $this->params()->fromRoute();
        $data = $this->extractPostData();
        try {
           // echo "< pre >  "; print_r([$data, $params]); exit();
            $count = $this->announcementService->saveTeam($params, $data);
            return $this->getSuccessResponseWithData($data, 200);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    public function markAsReadAction()
    {
        $data = $this->extractPostData();
        try {
            $this->announcementService->markAsRead($data['announcementId']);
            return $this->getSuccessResponseWithData($data, 200);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    public function announcementTeamsAction()
    {
        $params = $this->params()->fromRoute();
        $filterParams = $this->params()->fromQuery(); // empty method call
        try {
            $count = $this->announcementService->getAnnouncementTeamList($params, $filterParams);
            return $this->getSuccessResponseDataWithPagination($count['data'], $count['total']);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }
}
