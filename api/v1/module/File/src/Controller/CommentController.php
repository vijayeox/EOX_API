<?php

namespace File\Controller;

use Oxzion\Model\CommentTable;
use Oxzion\Model\Comment;
use Oxzion\Service\CommentService;
use Zend\Db\Adapter\AdapterInterface;
use Oxzion\Controller\AbstractApiController;
use Oxzion\ValidationException;
use Zend\InputFilter\Input;

class CommentController extends AbstractApiController
{
    /**
    * @var CommentService Instance of Comment Service
    */
    private $commentService;
    /**
    * @ignore __construct
    */
    public function __construct(CommentTable $table, CommentService $commentService, AdapterInterface $dbAdapter)
    {
        parent::__construct($table, Comment::class);
        $this->setIdentifierName('id');
        $this->commentService = $commentService;
    }

    /**
    * Create Comment API
    * @api
    * @link /Comment
    * @method POST
    * @param array $data Array of elements as shown
    * <code> {
    *               id : integer,
    *               account_id : integer,
                    file_id: integer,
    *               text : string,
    *               parent : integer,
    *} </code>
    * @return array Returns a JSON Response with Status Code and Created Comment.
    */
    public function create($data)
    {
        $params = $this->params()->fromRoute();
        try {
            $count = $this->commentService->createComment($data, $params['fileId']);
        } catch (ValidationException $e) {
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors", 404, $response);
        }
        if ($count == 0) {
            return $this->getFailureResponse("Failed to create a new comment", $data);
        }
        return $this->getSuccessResponseWithData($data, 201);
    }
    /**
    * Update Comment API
    * @api
    * @link /file/:fileId/comment[/:id]
    * @method PUT
    * @param array $id ID of Comment to update
    * @param array $data
    * <code> status : "success|error",
    *        data :
                    {
                    integer id,
                    integer file_id,
                    string text,
                    integer parent,
                    integer orgid,
                    integer created_by,
                    integer modified_by,
                    dateTime date_created (ISO8601 format yyyy-mm-ddThh:mm:ss),
                    dateTime date_modified (ISO8601 format yyyy-mm-ddThh:mm:ss),
                    boolean isdeleted,
                    }
    * </code>
    * @return array Returns a JSON Response with Status Code and Created Comment.
    */
    public function update($id, $data)
    {
        $params = $this->params()->fromRoute();
        try {
            $count = $this->commentService->updateComment($id, $params['fileId'], $data);
        } catch (ValidationException $e) {
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors", 404, $response);
        }
        if ($count == 0) {
            return $this->getErrorResponse("Entity not found for id - $id", 404);
        }
        return $this->getSuccessResponseWithData($data, 200);
    }

    /**
    * Delete Comment API
    * @api
    * @link file/:fileId/comment[/:id]
    * @method DELETE
    * @param $id ID of Comment to Delete
    * @return array success|failure response
    */
    public function delete($id)
    {
        $params = $this->params()->fromRoute();
        $response = $this->commentService->deleteComment($id, $params['fileId']);
        if ($response == 0) {
            return $this->getErrorResponse("Comment not found", 404, ['id' => $id]);
        }
        return $this->getSuccessResponse();
    }

    /**
    * Get Comment API
    * @api
    * @link file/:fileId/comment[/:id]
    * @method GET
    * @param $id ID of Comment to Get
    * @return array success|failure response
    */
    public function get($id)
    {
        $params = $this->params()->fromRoute();
        $response = $this->commentService->getComment($id, $params['fileId']);
        if ($response == 0) {
            return $this->getErrorResponse("Comment not found", 404, ['id' => $id]);
        }
        return $this->getSuccessResponseWithData($response);
    }

    /**
    * GET List Comment API
    * @api
    * @link /file/:fileid/comment
    * @method GET
    * @return array $dataget list of Comments by User
    * <code>status : "success|error",
    *       data :  {
                    integer id,
                    integer file_id,
                    string text,
                    integer parent,
                    integer orgid,
                    integer created_by,
                    integer modified_by,
                    dateTime date_created (ISO8601 format yyyy-mm-ddThh:mm:ss),
                    dateTime date_modified (ISO8601 format yyyy-mm-ddThh:mm:ss),
                    boolean isdeleted,
                    }
    * </code>
    */
    public function getList()
    {
        $filterParams = $this->params()->fromRoute();
        $result = $this->commentService->getComments($filterParams['fileId']);
        return $this->getSuccessResponseWithData($result);
    }

    public function getChildListAction()
    {
        $params = $this->params()->fromRoute();
        $id = $params['id'];
        try {
            $response = $this->commentService->getchildren($id, $params['fileId']);
        } catch (ValidationException $e) {
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors", 404, $response);
        }
        return $this->getSuccessResponseWithData($response, 200);
    }

    public function getParentCommentslistAction()
    {
        $params = $this->params()->fromRoute();
        try {
            $response = $this->commentService->getComments($params['fileId'], true);
        } catch (ValidationException $e) {
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors", 404, $response);
        }
        return $this->getSuccessResponseWithData($response, 200);
    }
}
