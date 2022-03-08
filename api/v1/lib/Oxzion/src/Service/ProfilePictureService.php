<?php
namespace Oxzion\Service;

use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Oxzion\Service\AbstractService;
use Oxzion\Model\User;
use Oxzion\ValidationException;
use Zend\Db\Sql\Expression;
use Oxzion\Messaging\MessageProducer;
use Exception;
// use v1\module\Attachment\Service\AttachmentService;
use Oxzion\Utils\FileUtils;

class ProfilePictureService extends AbstractService
{
    private $profilePic = "profile.png";
    private $messageProducer;

    public function setMessageProducer($messageProducer)
    {
        $this->messageProducer = $messageProducer;
    }
    
    /**
    * @ignore __construct
    */
    public function __construct($config, $dbAdapter, MessageProducer $messageProducer)
    {
        parent::__construct($config, $dbAdapter);
        $this->messageProducer = $messageProducer;
    }

    public function getProfilePicturePath($id, $ensureDir=false)
    {
        $baseFolder = $this->config['UPLOAD_FOLDER'];
        //TODO : Replace the User_ID with USER uuid
        $folder = $baseFolder."user/";
        if (isset($id)) {
            $folder = $folder.$id."/";
        }

        if ($ensureDir && !file_exists($folder)) {
            FileUtils::createDirectory($folder);
        }

        return $folder.$this->profilePic;
    }


    

    /**
     * createUpload
     *
     * Upload files from Front End and store it in temp Folder
     *
     *  @param files Array of files to upload
     *  @return JSON array of filenames
    */
    public function uploadProfilepicture($params)
    {
        $files=substr($params['file'], strpos($params['file'], ",")+1);
        $file=base64_decode($files);
        $id = AuthContext::get(AuthConstants::USER_UUID);

        if (isset($file)) {
            $destFile = $this->getProfilePicturePath($id, true);

            // move_uploaded_file($file, $destFile);
        $message = array('userName' => AuthContext::get(AuthConstants::USERNAME), 'destFile' => $destFile/*'file' => $files*/);
            $this->logger->info("DATA TO file----".print_r($message,true));
            $this->messageProducer->sendTopic(json_encode($message), 'UPDATE_CHAT_PROFILE_PICTURE');
            file_put_contents($destFile, $file);
        }
    }
}
