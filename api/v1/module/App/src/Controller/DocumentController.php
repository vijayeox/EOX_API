<?php
/**
 * Document Api
 * TODO We need to create the test case for this, we have the appId but its not being used anywhere in the API, we need to revist this or remove this if we are using the Attachment API instead of this one
 */
namespace App\Controller;

use Oxzion\Controller\AbstractApiControllerHelper;
use Oxzion\Utils\ArtifactUtils;

/**
 * Document Controller
 */
class DocumentController extends AbstractApiControllerHelper
{
    /**
     * @ignore __construct
     */
    private $config;
    protected $log;
    public function __construct($config)
    {
        $this->setIdentifierName('document');
        $this->config = $config;
        $this->log = $this->getLogger();
    }

    public function head($id = null)
    {
        return true;
    }

    /**
     * Create Document API
     * @api
     * @link /Document
     * @method POST
     */
    public function create($data)
    {
        return $this->getInvalidMethod();
    }

    /**
     * GET Document API
     * @api
     * @link /Document
     * @method GET
     * @param $id ID of Document to Delete
     * @return array $data
     * <code>
     * {
     *  integer id,
     *  string file_name,
     *  integer extension,
     *  string uuid,
     *  string type,
     *  dateTime path Full Path of File,
     * }
     * </code>
     * @return array Returns a JSON Response with Status Code and Created Document.
     */
    public function get($id)
    {
        $params = array_merge($this->extractPostData(), $this->params()->fromRoute());
        $params = array_merge($params, $this->params()->fromQuery());

        if (isset($params['docPath'])) {
            if(strpos($params['docPath'], $this->config['APP_DOCUMENT_FOLDER']) !== false){
                $attachment_location = $params['docPath'];
            } else {
                $attachment_location = $this->config['APP_DOCUMENT_FOLDER'] . $params['docPath'];
            }
        } else {
            $attachment_location = $this->config['APP_DOCUMENT_FOLDER'] . $params['accountId'] . "/" . $params['fileId'] . "/" . $params['document'];

            if (isset($params['folder1'])) {
                $attachment_location = $this->config['APP_DOCUMENT_FOLDER'] . $params['accountId'] . "/" . $params['fileId'] . "/" . $params['folder1'] . "/" . $params['document'];
            }
        }

        $ext = pathinfo($attachment_location, PATHINFO_EXTENSION);
        $dispositionType = isset($ext) && (strtolower($ext) =="pdf")  ? "inline" : "attachment";
        
        if (file_exists($attachment_location)) {
            if (!headers_sent()) {
                header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
                header("Cache-Control: public"); // needed for internet explorer
                $mimeType = ArtifactUtils::getMimeType($params['document']);
                header("Content-Type:".$mimeType);
                header("Content-Transfer-Encoding: Binary");
                header("Content-Length:" . filesize($attachment_location));
                header("Access-Control-Expose-Headers:Content-Disposition");
                header("Content-Disposition: ". $dispositionType ."; filename=" . $params['document']);
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

    /**
     * GET List Document API
     * @api
     * @link /Document
     * @method GET
     * @return Error Response Array
     */
    public function getList()
    {
        return $this->getInvalidMethod();
    }


    public function getTempDocumentAction()
    {
        $params = array_merge($this->extractPostData(), $this->params()->fromRoute());
        $attachment_location = $this->config['APP_DOCUMENT_FOLDER'] . $params['accountId'] . "/temp/" . $params['tempId'] . "/". $params['documentName'];

        $ext = pathinfo($attachment_location, PATHINFO_EXTENSION);
        $dispositionType = isset($ext) && $ext=="pdf"  ? "inline" : "attachment";
        if (file_exists($attachment_location)) {
            if (!headers_sent()) {
                header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
                header("Cache-Control: public"); // needed for internet explorer
                $mimeType = ArtifactUtils::getMimeType($params['documentName']);
                header("Content-Type:".$mimeType);
                header("Content-Transfer-Encoding: Binary");
                header("Content-Length:" . filesize($attachment_location));
                header("Content-Disposition: ". $dispositionType ."; filename=" . $params['documentName']);
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
