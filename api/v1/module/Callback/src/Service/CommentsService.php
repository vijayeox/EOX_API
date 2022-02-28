<?php
namespace Callback\Service;

use Exception;
use Oxzion\Service\AbstractService;
use Oxzion\Utils\RestClient;
use Oxzion\Service\FileService;
use Oxzion\Service\SubscriberService;
use Oxzion\Service\CommentService;
use Oxzion\Service\UserService;
use Callback\Service\ChatService;
use Oxzion\Utils\ArtifactUtils;
use Oxzion\Utils\FileUtils;

class CommentsService extends AbstractService
{
    private $restClient;
    protected $dbAdapter;

    public function setRestClient($restClient)
    {
        $this->restClient = $restClient;
    }

    public function __construct($config, $dbAdapter, FileService $fileService, SubscriberService $subscriberService, CommentService $commentService, UserService $userService, ChatService $chatService)
    {
        parent::__construct($config, $dbAdapter);
        $this->restClient = new RestClient($this->config['chat']['chatServerUrl']);
        $this->authToken = $this->config['chat']['authToken']; //PAT
        $this->appBotUrl = $this->config['chat']['appBotUrl'];
        $this->fileService = $fileService;
        $this->subscriberService = $subscriberService;
        $this->commentService = $commentService;
        $this->userService = $userService;
        $this->chatService = $chatService;
        $this->dbAdapter = $dbAdapter;
        $this->config = $config;
    }

    public function postFileComment($data){
        try{
            $this->logger->info("postFileComment---".print_r($data,true));
            $userInfo = $this->chatService->getUser($data['senderId']);
            $this->logger->info("Userinfo---".print_r($userInfo,true));
            $userDetails = $this->userService->getUserContextDetails($userInfo['username']);
            $this->logger->info("userDetails---".print_r($userDetails,true));
            $subscribers =  $this->subscriberService->getUserSubscriber($data['FileId'],null,$userDetails['id']);
            $this->logger->info("subscribers---".print_r($subscribers,true));
            // TODO CHECK IF COMMENT SENDER HAVING ACCESS TO THE FILE - Subscriber or one of the partcipants
            $context = ['accountId' => isset($subscribers[0]['account_id']) ? $subscribers[0]['account_id']: $userDetails['accountId'], 'userId' => $userDetails['userId']];
            $this->logger->info("Contexttt---".print_r($context,true));
            $this->updateAccountContext($context);
            $this->logger->info("DAATAA-----".print_r($data,true));
            if (!isset($data['CommentId'])) {                
            return   $this->commentService->createComment($data,$data['FileId']);
            }else{
             return $this->commentService->updateComment($data['CommentId'],$data['FileId'],$data);
            }
        }catch(Exception $e){
            throw $e;
        }
    }

    public function addAttachment($params,$file){
        try {
            $file['type'] = ArtifactUtils::getMimeType($file['name']);

            $select = "SELECT oxf.uuid as fileId, oxa.uuid as accountId,oxae.uuid as entityId, oxae.app_id as appId, oxc.created_by as createdBy, oxc.attachments as commentAttachment
                       FROM ox_comment oxc
                       inner join ox_file oxf on oxf.id = oxc.file_id
                       inner join ox_account oxa on oxa.id = oxf.account_id
                       inner join ox_app_entity oxae on oxae.id = oxf.entity_id
                       where oxc.uuid =:commentId";
            $params = ['commentId' => $params['commentId']];
            $res = $this->executeQueryWithBindParameters($select,$params)->toArray();

            if (count($res) > 0 && isset($params['commentId'])) {                
                $dest = ArtifactUtils::getDocumentFilePath($this->config['APP_DOCUMENT_FOLDER'], $res[0]['fileId'], array('accountId' => $res[0]['accountId']));
                $dest['absolutePath'] .= $params['commentId'] . "/";
                $dest['relativePath'] .= $params['commentId'] . "/";
                FileUtils::createDirectory($dest['absolutePath']);
                FileUtils::storeFile($file, $dest['absolutePath']);
            }
            if (isset($res[0]['commentAttachment'])) {
                $newAttach['attachments'] = [];
                $attach = is_string($res[0]['commentAttachment']) ? json_decode($res[0]['commentAttachment'],true) : $res[0]['commentAttachment'];
                $newAttach['attachments'][] = ['name' =>$file['name'], 'path' => $dest['relativePath'].str_replace(' ', '_', $file['name'])];
                $data['attachments'] = json_encode($newAttach); 
            }else{
                $data['attachments'] = json_encode(array("attachments" => array(array("name" => $file['name'], "path" => $dest['relativePath'].str_replace(' ', '_', $file['name'])))));
            } 
            $data['accountId'] = $res[0]['accountId'];
            $this->commentService->updateComment($params['commentId'],$res[0]['fileId'],$data);
            
            $select = "SELECT generic_attachment_config from ox_app_entity where uuid=:entityId";
            $paramsData['entityId'] = $res[0]['entityId'];
            $result = $this->executeQueryWithBindParameters($select,$paramsData)->toArray();
            if (count($result) > 0) {
                $fieldLabel = json_decode($result[0]['generic_attachment_config'],true);
                $attachData['fieldLabel'] = preg_replace('/[^A-Za-z0-9\-]/', '', $fieldLabel['attachmentField']);
                $attachData['fileId'] = $res['0']['fileId'];
                $attachData['appId'] = $this->getUuidFromId('ox_app' ,$res[0]['appId']);
                $context = ['accountId' => $res[0]['accountId'], 'userId' => $this->getUuidFromId('ox_user',$res[0]['createdBy'])];
            	$this->updateAccountContext($context);
                return $this->fileService->addAttachment($attachData, $file,$params['commentId']);
            }
            
        } catch(Exception $e){
            throw $e;
        }
    }

    public function getCommentsAttachmentPath($params)
    {
        $select = "SELECT oxf.uuid as fileId, oxa.uuid as accountId,oxae.uuid as entityId, oxae.app_id as appId, oxc.created_by as createdBy,oxc.attachments
                       FROM ox_comment oxc
                       inner join ox_file oxf on oxf.id = oxc.file_id
                       inner join ox_account oxa on oxa.id = oxf.account_id
                       inner join ox_app_entity oxae on oxae.id = oxf.entity_id
                       where oxc.uuid =:commentId";
            $data = ['commentId' => $params['commentId']];
            $res = $this->executeQueryWithBindParameters($select,$data)->toArray();
        if(isset($params['docPath'])){
            return  $this->config['APP_DOCUMENT_FOLDER'] . $params['docPath'];
        }else{            
            $attach = json_decode($res[0]['attachments'],true);
            $attach = is_string($attach) ? json_decode($attach,true) : $attach;
            $path = pathinfo($attach[0]['path']);
            // print_r($this->config['APP_DOCUMENT_FOLDER'] . $path['dirname']."/". $params['fileName']);exit;
            return $this->config['APP_DOCUMENT_FOLDER'] . $path['dirname']."/". $params['fileName'];
        }
    }

}
