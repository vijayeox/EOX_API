<?php

namespace Oxzion\Utils;

use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Logger;

class ArtifactUtils
{
    public static $logger;
    public static function getTemplatePath($config, $template, $params = array())
    {
        $templateDir = $config['TEMPLATE_FOLDER'];
        $accountId = isset($params['accountId']) ? $params['accountId'] : (isset($params['accountId']) ? $params['accountId'] : AuthContext::get(AuthConstants::ACCOUNT_UUID));
        self::$logger->info("accountId - $accountId");
        if (isset($accountId)) {
            $path = $accountId."/".$template;
            if (isset($params['appId'])) {
                $path = $accountId."/".$params['appId']."/".$template;
            }
        } else {
            $path = $template;
        }
        self::$logger->info("Path - $path, template directory - $templateDir,template - $template");
        if (is_file($templateDir.$path)) {
            if (isset($params['appId']) && isset($accountId)) {
                $path = $templateDir.$accountId."/".$params['appId'];
                return $path;
            }
            elseif(isset($params['appId']) && !isset($accountId)){
                $path = $templateDir."/".$params['appId'];
                return $path;
            }
            else
            {
                return $templateDir.$accountId;
            }
        } elseif (is_file($templateDir.$template)) {
            return $templateDir;
        }
        return false;
    }

    public static function getDocumentFilePath($templateDir, $fileUuid, $params = array())
    {
        $accountId = isset($params['accountId']) ? $params['accountId'] : AuthContext::get(AuthConstants::ACCOUNT_UUID);
        if (isset($accountId)) {
            $path = $accountId."/".$fileUuid."/";
        } else {
            $path = $fileUuid."/";
        }
        if (!is_file($templateDir.$path)) {
            FileUtils::createDirectory($templateDir.$path);
        }
        return array('absolutePath' => $templateDir.$path, 'relativePath' => $path);
    }

    public static function getMimeType($fileName)
    {
        $pathInfo = pathinfo($fileName);
        $fileExtension = $pathInfo['extension'];
        switch ($fileExtension) {
            case 'png':
                $mimeType = 'image/png';
                break;
            case 'pdf':
                $mimeType = 'application/pdf';
                break;
            case 'jpeg':
            case 'jpg':
                $mimeType = 'image/jpeg';
                break;
            case 'mp4':
                $mimeType = 'video/mp4';
                break;
            case 'gif':
                $mimeType = 'image/gif';
                break;
            case 'xls':
                $mimeType = 'application/vnd.ms-excel';
                break;
            case 'xlsx':
                $mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                break;
            case 'docx':
                $mimeType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                break;
            case 'doc':
                $mimeType = 'application/msword';
                break;
            case 'odt':
                $mimeType = 'application/vnd.oasis.opendocument.text';
                break;
            case 'zip':
                $mimeType = 'application/zip';
                break;
            default:
                $mimeType = 'application/octet-stream';
                break;
        }
        return $mimeType;
    }
}

ArtifactUtils::$logger  = Logger::getLogger(__CLASS__);
