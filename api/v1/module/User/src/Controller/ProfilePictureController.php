<?php
namespace User\Controller;

use Oxzion\Controller\AbstractApiController;
use Oxzion\Service\ProfilePictureService;
use Zend\Db\Adapter\AdapterInterface;
use Exception;
use Zend\InputFilter\Input;
use Oxzion\Utils\FileUtils;

class ProfilePictureController extends AbstractApiController
{
    /**
    * @var ProfilepictureService Instance of Projectpicture Service
    */
    private $profilepictureService;
    /**
    * @ignore __construct
    */
    public function __construct(ProfilePictureService $profilepictureService, AdapterInterface $dbAdapter)
    {
        parent::__construct(null, null);
        $this->setIdentifierName('profileId');
        $this->profilepictureService = $profilepictureService;
    }

    /**
    * Update Profilepicture API
    * @api
    * @link /profilepicture[/:profileId]
    * @method POST
    * @param $id ID of Profilepicture to update
    * @param $data
    * @return array Returns a JSON Response with Status Code and Created Profilepicture.
    */
    public function updateProfileAction()
    {
        $this->log->info("Profile update controller");
        $params=$this->extractPostData();
        try {
            $this->profilepictureService->uploadProfilepicture($params);
        } catch (Exception $e) {
            $this->log->error("Failed to upload profile picture", $e);
            return $this->exceptionToResponse($e);
        }
        return $this->getSuccessResponse("Upload successfull", 200);
        $this->log->info("Profile update controller end");
    }
}
