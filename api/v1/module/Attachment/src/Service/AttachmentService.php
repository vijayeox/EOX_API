<?php

namespace Attachment\Service;

use Attachment\Model\Attachment;
use Attachment\Model\AttachmentTable;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\Service\AbstractService;
use Oxzion\Utils\FileUtils;

class AttachmentService extends AbstractService
{
    private $table;
    /**
     * @ignore __construct
     */
    public function __construct($config, $dbAdapter, AttachmentTable $table)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
    }

    /**
     * createUpload
     *
     * Upload files from Front End and store it in temp Folder
     *
     *  @param files Array of files to upload
     *  @return JSON array of filenames
     */
    public function upload(array $data, $files)
    {
        $fileArray = array();
        $data['account_id'] = AuthContext::get(AuthConstants::ACCOUNT_ID);
        $data['created_id'] = AuthContext::get(AuthConstants::USER_ID);
        if (isset($data['type'])) {
            $fileArray = array();
            if (isset($files)) {
                foreach ($files as $file) {
                    $fileArray[] = $this->constructAttachment($data, $file);
                }
            } else {
                if (isset($data['files'])) {
                    foreach ($data['files'] as $key => $value) {
                        $fileArray[] = $this->constructAttachment($data, $value);
                    }
                }
            }
        }
        return $fileArray;
    }

    /**
     * @ignore constructAttachment
     */
    protected function constructAttachment($data, $file)
    {
        if (isset($file['name'])) {
            $uniqueId = uniqid();
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $data['file_name'] = $file['name'];
            $data['extension'] = $ext;
            $file['name'] = $data['file_name'];
            $data['uuid'] = $uniqueId;
        } else {
            $data['uuid'] = $file['uuid'];
            $data['file_name'] = $file['file_name'];
            $data['extension'] = $file['extension'];
        }
        $folderPath = $this->constructPath($data['type']);
        $form = new Attachment();
        $data['created_date'] = isset($data['start_date']) ? $data['start_date'] : date('Y-m-d H:i:s');
        $path = realpath($folderPath . $data['file_name']) ? realpath($folderPath . $data['file_name']) : FileUtils::truepath($folderPath . $data['file_name']);
        $data['path'] = $path;
        $form->exchangeArray($data);
        $form->validate();
        $count = $this->table->save($form);
        $id = $this->table->getLastInsertValue();
        FileUtils::storeFile($file, $folderPath);
        return $data['uuid'];
    }

    /**
     * GET Attachment Service
     * @method GET
     * @param $id ID of Attachment to Delete
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
     * @return array Returns a JSON Response with Status Code and Created Attachment.
     */
    public function getAttachment($id)
    {
        $sql = $this->getSqlObject();
        $select = $sql->select();
        $select->from('ox_attachment')
            ->columns(array("path"))
            ->where(array('uuid' => $id));
        $result = $this->executeQuery($select)->toArray();
        return $result;
    }

    public function getAttachmentDetails($id)
    {
        $sql = $this->getSqlObject();
        $select = $sql->select();
        $select->from('ox_attachment')
            ->columns(array("file_name","extension","type","path"))
            ->where(array('uuid' => $id));
        $result = $this->executeQuery($select)->toArray();
        return $result;
    }

    /**
     * @ignore constructPath
     */
    private function constructPath($type)
    {
        $baseFolder = $this->config['UPLOAD_FOLDER'];
        switch ($type) {
            case 'ANNOUNCEMENT':
                return $baseFolder . "account/" . AuthContext::get(AuthConstants::ACCOUNT_ID) . "/announcements/";
            default:
                return $baseFolder;
        }
    }
}
