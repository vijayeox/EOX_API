<?php
namespace FileIndexer\Controller;

    use Zend\Log\Logger;
    use Oxzion\Controller\AbstractApiControllerHelper;
    use Oxzion\ServiceException;
    use FileIndexer\Service\FileIndexerService;

    class FileIndexerController extends AbstractApiControllerHelper
    {
        private $fileIndexerService;
        protected $log;

        /**
        * @ignore __construct
        */
        public function __construct(FileIndexerService $fileIndexerService)
        {
            $this->fileIndexerService = $fileIndexerService;
            $this->log = $this->getLogger();
        }

        public function IndexAction()
        {
            $params = $this->extractPostData();
            $params['id']  = isset($params['id']) ? $params['id'] : null;
            $params['filedata'] = ($params['id']) ? ($params['id']) : "No File to index";
            $this->log->info("Params- ".json_encode($params));
            $response = $this->fileIndexerService->getRelevantDetails($params['id']);
            if ($response) {
                $this->log->info(FileIndexerController::class.":File has been Indexed");
                return $this->getSuccessResponseWithData($response);
            }
            return $this->getErrorResponse("Failure to Index File ", 400);
        }

        /**
        * Index File API
        * @api
        * @link /fileindexer/file
        * @method POST
        * @param file uuid</br>
        * <code>
        *  uuid : string
        * </code>
        * @return array Returns a JSON Response with Status Code and File Data sent to the queue for indexing.</br>
        * <code> status : "success|error",
        *        data : "JSON STRING CONTAINING FILE DATA"
        * </code>
        */
        public function indexFileAction()
        {
            // Get the uuid entered
            $params = $this->extractPostData();
            $uuid  = isset($params['uuid']) ? $params['uuid'] : null;
            if ($uuid == null) {
                //Handle no uuid being present
                return $this->getErrorResponse("uuid must be provided", 404);
            }
            try {
                $response = $this->fileIndexerService->indexFile($uuid);
                $this->log->info(FileIndexerController::class.":File has been Indexed");
                return $this->getSuccessResponseWithData($response);
            }
            catch (ServiceException $e) {
                return $this->getErrorResponse("Failure to Index File as incorrect uuid is specified", 404);
            } 
            catch (Exception $e) {
                return $this->getErrorResponse("Failure to Index File ", 400);
            }
        }

        /**
        * Process Batch Index API
        * @api
        * Meant for Asynchronous batch indexing for large number of records
        * @link /fileindexer/batch/process
        * @method POST
        * @param array $data Array of elements as shown</br>
        * <code>
        *  app_id : string
        *  start_date : string
        *  end_date : string
        * </code>
        * @return array Returns a JSON Response with Status Code.</br>
        * <code> status : "success|error",
        * </code>
        */
        public function processBatchIndexAction()
        {
            $params = $this->extractPostData();
            try {
                $response = $this->fileIndexerService->processBatchIndex($params);
                $this->log->info(FileIndexerController::class.":Batch indexing process has been initiated");
                return $this->getSuccessResponse();
            } catch (Exception $e) {
                return $this->getErrorResponse("Failure to Index File ", 400);
            }
        }

        public function batchIndexAction()
        {
            $params = $this->extractPostData();
            $startdate = isset($params['start_date']) ? $params['start_date'] : null;
            $enddate = isset($params['end_date']) ? $params['end_date'] : null;
            $appUuid = isset($params['app_id']) ? $params['app_id'] : null;
            $this->log->info(FileIndexerController::class.":Batch indexing ");
            $this->log->info("Params- ".json_encode($params));
            try {
                $response = $this->fileIndexerService->batchIndexer($appUuid, $startdate, $enddate);
                if ($response) {
                    $this->log->info(FileIndexerController::class.":Files have been Indexed");
                    return $this->getSuccessResponseWithData($response);
                }
            } catch (Exception $e) {
                return $this->getErrorResponse($e->getMessage(), 400);
            }
            $this->log->info(FileIndexerController::class.":Files have failed indexing");
            return $this->getErrorResponse("Failure to Index File ", 400);
        }

        public function deleteIndexAction()
        {
            $params = $this->extractPostData();
            $params['id']  = isset($params['id']) ? $params['id'] : null;
            $params['filedata'] = ($params['id']) ? ($params['id']) : "No File to Delete";
            $this->log->info(FileIndexerController::class.":File Id- ".$params['filedata']);
            $response = $this->fileIndexerService->deleteDocument($params['id']);
            if ($response) {
                $this->log->info(FileIndexerController::class.":File has been Deleted");
                return $this->getSuccessResponseWithData($response);
            }
            return $this->getErrorResponse("Failure to Delete File ", 400);
        }
    }