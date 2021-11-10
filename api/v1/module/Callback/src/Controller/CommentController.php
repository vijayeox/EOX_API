<?php
namespace Callback\Controller;

use Callback\Service\CommentsService;
use Exception;
use Oxzion\Controller\AbstractApiControllerHelper;
use Oxzion\Utils\ArtifactUtils;

class CommentController extends AbstractApiControllerHelper
{
    private $commentsService;
    protected $log;
    /**
     * @ignore __construct
     */
    public function __construct(CommentsService $commentsService)
    {
        $this->commentsService = $commentsService;
        $this->log = $this->getLogger();
    }

    public function create($data)
    {
        $this->log->info(__CLASS__ . "-> Create App --- " . print_r($data, true));
        try{
            $res = $this->commentsService->postFileComment($data);
            return $this->getSuccessResponseWithData($res, 200);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    public function updateAction()
    {   
        $params = array_merge($this->extractPostData(),$this->params()->fromRoute());
        $this->log->info("postFileComment Params- " . json_encode($params));
        try{
           $res =  $this->commentsService->postFileComment($params);
            return $this->getSuccessResponseWithData($res,200);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    public function saveCommentAttachmentAction(){
        $params = array_merge($this->extractPostData(), $this->params()->fromRoute());
        try {
            $files = isset($_FILES['file']) ? $_FILES['file'] : $this->params()->fromFiles('files');
            
        $this->log->info("Save Comments Attchments data---".print_r($params,true));
        $this->log->info("Save Comments Attchments Params---".print_r($files,true));
            if (!$files) {
                return $this->getErrorResponse("No file Found", 404, $params);
            }
            $fileInfo = $this->commentsService->addAttachment($params,$files);
            return $this->getSuccessResponseWithData($fileInfo, 201);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->getErrorResponse("No attachment found for a file", 404);
        }
    }

     public function getCommentFileAction()
    {
        $params = array_merge($this->extractPostData(), $this->params()->fromRoute());
        $params = array_merge($params ,$this->params()->fromQuery());
        $attachment_location = $this->commentsService->getCommentsAttachmentPath($params);

        $ext = pathinfo($attachment_location, PATHINFO_EXTENSION);
        $dispositionType = isset($ext) && $ext=="pdf"  ? "inline" : "attachment";
        if (file_exists($attachment_location)) {
            if (!headers_sent()) {
                header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
                header("Cache-Control: public"); // needed for internet explorer            
                $mimeType = ArtifactUtils::getMimeType($params['fileName']);
                header("Content-Type:".$mimeType );  
                header("Content-Transfer-Encoding: Binary");
                header("Content-Length:" . filesize($attachment_location));
                header("Access-Control-Expose-Headers:Content-Disposition");
                header("Content-Disposition: ". $dispositionType ."; filename=" . $params['fileName']);
            }
            $fp = @fopen($attachment_location, 'rb');
            fpassthru($fp);
            fclose($fp);
            $this->response->setStatusCode(200);
            return $this->response;
        } else {
            $this->log->error("Error: File Not Found");
            return $this->getErrorResponse("File Not Found", 404);
        }
    }
}
