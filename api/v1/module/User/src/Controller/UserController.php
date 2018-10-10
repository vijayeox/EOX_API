<?php
namespace User\Controller;

use Zend\Log\Logger;
use User\Model\User;
use User\Model\UserTable;
use User\Service\UserService;
use Oxzion\Controller\AbstractApiController;
use Oxzion\ValidationResult;
use Oxzion\ValidationException;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Sql;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Adapter\AdapterInterface;



class UserController extends AbstractApiController {

	private $dbAdapter;
	public function __construct(UserTable $table, Logger $log, UserService $userService){
		parent::__construct($table, $log, __CLASS__, User::class);
		$this->setIdentifierName('userId');
		$this->userService = $userService;
	}

	    /**
    *   $data should be in the following JSON format
    *   {
    *       'id' : integer,
    *       'name' : string,
    *       'org_id' : integer,
    *       'status' : string,
    *       'description' : string,
    *       'start_date' : dateTime (ISO8601 format yyyy-mm-ddThh:mm:ss),
    *       'end_date' : dateTime (ISO8601 format yyyy-mm-ddThh:mm:ss)
    *       'media_type' : string,
    *       'media_location' : string,
    *       'groups' : [
    *                       {'id' : integer}.
    *                       ....multiple 
    *                  ],
    *   }
    *
    *
    */

	public function create($data){
        try{
            $count = $this->userService->createUser($data);
        }catch(ValidationException $e){
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            /*
        	PLease see the html error codes. https://www.restapitutorial.com/httpstatuscodes.html
        	Not found = 406
        	While this is not exactly not found we don't have a better HTML error code for create.
       		*/
            return $this->getErrorResponse("Validation Errors",406, $response);
        }

        if($count == 0){
            return $this->getFailureResponse("Failed to create a new user", $data);
        }
        /*
        PLease see the html error codes. https://www.restapitutorial.com/httpstatuscodes.html
        Successful create = 201
        */
        return $this->getSuccessResponseWithData($data,201);
    }


/*
We need to be passing org_id or group_id or both to this. Otherwise it wouldn't make sense anywhere. Right? I am going to pass both of them and if the id is there we will query by that id.
*/

    public function getList() {
        $result = $this->userService->getUsers();
        return $this->getSuccessResponseWithData($result);
    }

    public function update($id, $data){
        try{
            $count = $this->userService->updateUser($id,$data);
        }catch(ValidationException $e){
            $response = ['data' => $data, 'errors' => $e->getErrors()];
            return $this->getErrorResponse("Validation Errors",404, $response);
        }
        if($count == 0){
            return $this->getErrorResponse("Entity not found for id - $id", 404);
        }
        return $this->getSuccessResponseWithData($data,200);
    }
    public function delete($id){
        $response = $this->userService->deleteUser($id);
        if($response == 0){
            return $this->getErrorResponse("User not found", 404, ['id' => $id]);
        }
        return $this->getSuccessResponse();
    }




}