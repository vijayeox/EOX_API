<?php
/**
* File Api
*/
namespace Oxzion\Service;

use Oxzion\Service\AbstractService;
use Oxzion\Model\CommentTable;
use Oxzion\Model\Comment;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Oxzion\ValidationException;
use Oxzion\Utils\UuidUtil;
use Zend\Db\Sql\Expression;
use Exception;
use Oxzion\Messaging\MessageProducer;

/**
 * Comment Controller
 */
class CommentService extends AbstractService
{
    /**
    * @var CommentService Instance of Comment Service
    */
    private $commentService;
    private $messageProducer;
    /**
    * @ignore __construct
    */

    public function setMessageProducer($messageProducer)
    {
        $this->messageProducer = $messageProducer;
    }

    public function __construct($config, $dbAdapter, CommentTable $table, MessageProducer $messageProducer)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
        $this->messageProducer = $messageProducer;
    }

    public function createComment($data, $fileId)
    {
        $form = new Comment();
        //Additional fields that are needed for the create
        $data['text'] = isset($data['text']) ? $data['text'] : null;
        $data['file_id'] = $this->getIdFromUuid('ox_file', $fileId);
        $data['account_id'] = AuthContext::get(AuthConstants::ACCOUNT_ID);
        $data['uuid'] = UuidUtil::uuid();
        $data['created_by'] = isset($data['created_by']) ? $data['created_by'] : AuthContext::get(AuthConstants::USER_ID);
        $data['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_created'] = isset($data['date_created']) ? $data['date_created'] : date('Y-m-d H:i:s');
        $data['date_modified'] = date('Y-m-d H:i:s');
        if (isset($data['attachments'])) {            
            $data['attachments'] = json_encode($data['attachments']);
        }
        if(isset($data['parent'])){
            if(!is_numeric($data['parent'])) {
                $data['parent']= $this->getIdFromUuid('ox_comment', $data['parent']);
            }
        }
        $data['isdeleted'] = false;
        $form->exchangeArray($data);
        $form->validate();
        $this->beginTransaction();
        $count = 0;
        try {
            $count = $this->table->save($form);
            if ($count == 0) {
                $this->rollback();
                return 0;
            }
            $id = $this->table->getLastInsertValue();
            $data['id'] = $id;
            $postId = isset($data['postId']) ? $data['postId'] : '';
            $this->logger->info("Comments Data from CS---".print_r(array('message' => $data['text'], 'fileId' => $fileId, 'commentId' =>$data['uuid'], 'fileIds' => $postId ,'from' => AuthContext::get(AuthConstants::USERNAME)),true));
            $this->messageProducer->sendTopic(json_encode(array('message' => $data['text'], 'fileId' => $fileId, 'commentId' =>$data['uuid'], 'fileIds' => $postId ,'from' => AuthContext::get(AuthConstants::USERNAME))), 'CHAT_APPBOT_NOTIFICATION');
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        return $data;
    }

    private function getParentId(&$data, $fileId)
    {
        $fId = $this->getIdFromUuid("ox_file", $fileId);
        $obj = $this->getIdFromUuid('ox_comment', $data['parent'], array("file_id" => $fId, "account_id" => AuthContext::get(AuthConstants::ACCOUNT_ID)));
        if (!$obj) {
            return 0;
        }
        $data['parent'] = $obj;
        return 1;
    }
    public function updateComment($id, $fileId, $data)
    {
        $fId = $this->getIdFromUuid("ox_file", $fileId);
        $accountId = isset($data['accountId']) && !is_numeric($data['accountId']) ? $this->getIdFromUuid("ox_account", $data['accountId']) :AuthContext::get(AuthConstants::ACCOUNT_ID);
        $obj = $this->table->getByUuid($id, array("file_id" => $fId, "account_id" => $accountId));
        if (!$obj) {
            return 0;
        }
        if(isset($data['parent'])){
            if(!is_numeric($data['parent'])) {
                $data['parent']= $this->getIdFromUuid('ox_comment', $data['parent']);
            }
        }
        $obj = $obj->toArray();
        if (isset($obj['attachments'])) {
            $objAttach = is_string($obj['attachments']) ? json_decode($obj['attachments'],true): $obj['attachments'];
        }
        $form = new Comment();
        $data = array_merge($obj, $data); //Merging the data from the db for the ID
        $data['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_modified'] = date('Y-m-d H:i:s');
        if (isset($data['attachments'])) {            
            $attach = is_string($data['attachments']) ? json_decode($data['attachments'],true) : $data['attachments'];
            $attachmentArray = isset($attach['attachments'][0]) ? $attach['attachments'][0] : (isset($attach['attachments']) ? $attach['attachments'] : (isset($attach) ? $attach : null));
            if(!in_array($attachmentArray,$objAttach)){
                $objAttach[] = $attachmentArray;
            }
            $data['attachments'] = json_encode($objAttach);
        }
        $form->exchangeArray($data);
        $form->validate();
        $this->beginTransaction();
        $count = 0;
        try {
            $count = $this->table->save($form);
            if ($count == 0) {
                $this->rollback();
                return 0;
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        return $data;
    }

    public function deleteComment($id, $fileId)
    {
        $fId = $this->getIdFromUuid("ox_file", $fileId);
        $obj = $this->table->getByUuid($id, array("file_id" => $fId, "account_id" => AuthContext::get(AuthConstants::ACCOUNT_ID)));
        if (is_null($obj)) {
            return 0;
        }
        $form = new Comment();
        $data = $obj->toArray();
        $data['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_modified'] = date('Y-m-d H:i:s');
        $data['isdeleted'] = 1;
        $form->exchangeArray($data);
        $form->validate();
        $count = 0;
        try {
            $this->beginTransaction();
            $count = $this->table->save($form);
            if ($count == 0) {
                $this->rollback();
                return 0;
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        return $count;
    }

    public function getComment($id, $fileId)
    {
        $result = $this->getCommentsInternal($fileId, $id);
        if (count($result) > 0) {
            return $result[0];
        }

        return 0;
    }
    public function getComments($fileId, $parentComment = false)
    {
        return $this->getCommentsInternal($fileId, null, $parentComment);
    }

    private function getCommentsInternal($fileId, $id = null,$parentComment = false)
    {
        $fileClause = $parentClause = "";
        $queryParams = array("accountId"=>AuthContext::get(AuthConstants::ACCOUNT_ID),"fileId"=>$fileId);
        if ($id) {
            $fileClause = "AND ox_comment.uuid = :commentId";
            $queryParams['commentId'] = $id;
        }
        if ($parentComment) {
            $parentClause = "AND ox_comment.parent IS NULL";
        }
        $query = "select text,ou.name as name,ou.icon as icon,ou.uuid as userId,ox_comment.date_created as time, ox_comment.uuid as commentId, ox_comment.attachments 
                    from ox_comment 
                    inner join ox_user ou on ou.id = ox_comment.created_by 
                    inner join ox_file of on of.id = ox_comment.file_id 
                    where ox_comment.account_id = :accountId AND of.uuid = :fileId $fileClause $parentClause order by ox_comment.date_created asc";
        $this->logger->info("Executing Query $query with params - ".print_r($queryParams, true));
        $resultSet = $this->executeQueryWithBindParameters($query, $queryParams)->toArray();
        if (count($resultSet) >0) {
            for ($i=0; $i < count($resultSet); $i++) {                 
                $attachment = json_decode($resultSet[$i]['attachments'],true);
                $resultSet[$i]['attachments'] = isset($attachment['attachments']) ? $attachment['attachments'] : null;
            }
        }
        return $resultSet;
    }

    public function getchildren($id, $fileId)
    {
        $queryString = "select ox_comment.text, ou.name, ou.icon, ou.uuid as userId, ox_comment.date_created as time, ox_comment.attachments,
                        ox_comment.uuid as commentId from ox_comment 
                        inner join ox_comment as parent on parent.id = ox_comment.parent
                        inner join ox_user ou on ou.id = ox_comment.created_by 
                        inner join ox_file of on of.id = ox_comment.file_id
                        where parent.uuid = :commentId AND ox_comment.account_id=".AuthContext::get(AuthConstants::ACCOUNT_ID)." AND ox_comment.isdeleted!=1 AND of.uuid = :fileId order by ox_comment.id";
        $queryParams = ["commentId" => $id, "fileId" => $fileId];
        $result = $this->executeQueryWithBindParameters($queryString, $queryParams)->toArray();
        if (count($result) >0) {
            for ($i=0; $i < count($result); $i++) {               
                $attachment = is_string($result[$i]['attachments']) ? json_decode($result[$i]['attachments'],true) : $result[$i]['attachments'];
                $result[$i]['attachments'] =  isset($attachment) ? $attachment : (isset($attachment['attachments']) ? $attachment['attachments'] : null);
            }
        }
        return $result;
    }
}
