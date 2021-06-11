<?php
namespace Analytics\Service;

use Exception;
use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\Service\AbstractService;
use Oxzion\Utils\FileUtils;

class TemplateService extends AbstractService
{
    public function __construct($config, $dbAdapter)
    {
        parent::__construct($config, $dbAdapter);
    }

    public function createTemplate($data)
    {
        $data['account_id'] = AuthContext::get(AuthConstants::ACCOUNT_ID);
        $data['created_id'] = AuthContext::get(AuthConstants::USER_ID);
        try {
            $templateFolderPath = $this->config['TEMPLATE_FOLDER'];
            $UploadTemplatepath = FileUtils::truepath($templateFolderPath . "/OITemplate");
            FileUtils::createDirectory($UploadTemplatepath);
            if(!isset($data['name'])) {
                return 1;
            }
            if (strlen($data['name']) > 0 && strlen($data['content']) > 0) {
                $templateFile = fopen($UploadTemplatepath . "/" . $data['name'] . ".tpl", "w") or die("Unable to open file!");
                $templateContent = $data['content'];
                fwrite($templateFile, $templateContent);
            } else {
                return 0;
            }
        } catch (Exception $e) {
            throw $e;
        }
        return $data;
    }

    public function deleteTemplate($uuid, $version)
    {
        try {

        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function getTemplate($name)
    {
        try {
            $templateFolderPath = $this->config['TEMPLATE_FOLDER'];
            $UploadTemplatepath = realpath($templateFolderPath . "/OITemplate/");
            $fileContent = file_get_contents($UploadTemplatepath . "/" . $name);
        } catch (Exception $e) {
            throw $e;
        }
        return array('data' => $fileContent);
    }

    public function getTemplatePath($name)
    {
        $template = array();
        try {
            $templateFolderPath = $this->config['TEMPLATE_FOLDER'];
            $UploadTemplatepath = realpath($templateFolderPath . "/OITemplate/");
            $template['templatePath'] = $UploadTemplatepath;
            $template['templateNameWithExt'] = $name;
        } catch (Exception $e) {
            throw $e;
        }
        return $template;
    }

    public function getTemplateList($params = null)
    {
        try {
            $templateFolderPath = $this->config['TEMPLATE_FOLDER'];
            $uploadTemplatepath = realpath($templateFolderPath . "/OITemplate/");
            $fullFileList = scandir($uploadTemplatepath); //Get all the content from the file list in the ascending order including the . and ..
            $finalFileList = array_slice($fullFileList, 2); // get only the name of the files

        } catch (Exception $e) {
            throw $e;
        }
        return array('data' => $finalFileList, 'total' => count($finalFileList));
    }
}
