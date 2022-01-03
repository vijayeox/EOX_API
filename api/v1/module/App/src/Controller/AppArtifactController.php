<?php

namespace App\Controller;

use App\Service\AppArtifactService;
use Exception;
use Oxzion\Controller\AbstractApiController;
use Oxzion\Utils\ZipException;
use Oxzion\App\AppArtifactNamingStrategy;

/*
 * Supports the following:
 *
 * Upload form definition file (form.json).
 * Delete form definition file.
 * Upload workflow definition file (workflow.bpmn).
 * Delete workflow definition file.
 * Upload application archive (application.zip).
 * Download application archive.
 *
 */
class AppArtifactController extends AbstractApiController
{
    private $appArtifactService = null;

    public function __construct(AppArtifactService $appArtifactService)
    {
        $this->appArtifactService = $appArtifactService;
        $this->log = $this->getLogger();
    }

    public function addArtifactAction()
    {
        $routeParams = $this->params()->fromRoute();
        $appUuid = $routeParams['appUuid'];
        $artifactType = $routeParams['artifactType'];
        try {
            return $this->getSuccessResponseWithData($this->appArtifactService->saveArtifact($appUuid, $artifactType));
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    public function deleteArtifactAction()
    {
        $routeParams = $this->params()->fromRoute();
        $appUuid = $routeParams['appUuid'];
        $artifactType = $routeParams['artifactType'];
        $artifactName = $routeParams['artifactName'];
        try {
            $this->appArtifactService->deleteArtifact($appUuid, $artifactType, $artifactName);
            return $this->getSuccessResponse();
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    public function downloadAppArchiveAction()
    {
        $routeParams = $this->params()->fromRoute();
        $appUuid = $routeParams['appUuid'];
        try {
            $archiveData = $this->appArtifactService->createAppArchive($appUuid);
            $response = new \Zend\Http\Response\Stream();
            $zipFilePath = $archiveData['zipFile'];
            $response->setStream(fopen($zipFilePath, 'r'));
            $response->setStatusCode(200);
            $normalizedAppName = AppArtifactNamingStrategy::normalizeAppName($archiveData['name']);
            $downloadFileName = $normalizedAppName . '-OxzionAppArchive.zip';
            $headers = new \Zend\Http\Headers();
            $headers->addHeaderLine('Content-Type', 'application/zip')
                    ->addHeaderLine('Content-Disposition', 'attachment; filename=' . $downloadFileName)
                    ->addHeaderLine('Access-Control-Expose-Headers: Content-Disposition')
                    ->addHeaderLine('Content-Length', filesize($zipFilePath));
            $response->setHeaders($headers);
            return $response;
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }

    public function uploadAppArchiveAction()
    {
        try {
            $returnData = $this->appArtifactService->uploadAppArchive();
            return $this->getSuccessResponseWithData($returnData, 200);
        } catch (ZipException $e) {
            return $this->getErrorResponse('Invalid application archive.');
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }
    public function getArtifactsAction()
    {
        $routeParams = $this->params()->fromRoute();
        $appUuid = $routeParams['appUuid'];
        $artifactType = $routeParams['artifactType'];
        try {
            return $this->getSuccessResponseWithData($this->appArtifactService->getArtifacts($appUuid, $artifactType));
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }
    public function downloadAppFileAction()
    {
        $routeParams = $this->params()->fromRoute();
        $appUuid = $routeParams['appUuid'];
        $artifactName = $routeParams['artifactName'];
        $artifactType = $routeParams['artifactType'];
        try {
            $fileData = $this->appArtifactService->createDownload($appUuid, $artifactType, $artifactName);
        //    print_r($fileData);exit;
            $response = new \Zend\Http\Response\Stream();
//             // $zipFilePath = $archiveData['zipFile'];
//             $f = fopen($fileData, 'r');
//             $response->setStream($f);
//             $response->setStatusCode(200);
//             // $normalizedAppName = AppArtifactNamingStrategy::normalizeAppName($archiveData['name']);
//             // $downloadFileName = $normalizedAppName . '-OxzionAppArchive.zip';
            $headers = new \Zend\Http\Headers();
            // $headers->addHeaderLine("Pragma: public", true)
// ->addHeaderLine("Expires: 0")// set expiration time
// ->addHeaderLine("Cache-Control: must-revalidate, post-check=0, pre-check=0")
// ->addHeaderLine("Content-Type: application/force-download")
// ->addHeaderLine("Content-Type: application/octet-stream")
// ->addHeaderLine("Content-Type: application/download")
// ->addHeaderLine("Content-Disposition: attachment; filename=".basename($fileData))
// ->addHeaderLine("Content-Transfer-Encoding: binary")
// ->addHeaderLine("Content-Length: ".filesize($fileData));
            $headers->addHeaderLine('Content-Type', 'application/octet-stream')
                    // ->addHeaderLine('Content-Description: File Transfer')
                    // ->addHeaderLine("Cache-Control: no-cache, must-revalidate")
                    // ->addHeaderLine("Expires: 0")
                    // ->addHeaderLine('Pragma: public')
                    // ->addHeaderLine("Content-Transfer-Encoding: Binary")
                    ->addHeaderLine("Content-disposition: attachment; filename=\"".$artifactName."\"")
                    // ->addHeaderLine("Content-disposition: attachment; filename=\"" . basename($fileData) . "\"")
                    // ->addHeaderLine('Content-Disposition', 'attachment; filename=' . basename($fileData))
                    ->addHeaderLine('Access-Control-Expose-Headers: Content-Disposition')
                    ->addHeaderLine('Content-Length', filesize($fileData));
            $response->setHeaders($headers);
//             // while (!feof($f)) {
//             //     // send the file part to the web browser
//             //     print fread($f, round(200 * 1024));
        
//             //     // flush the content to the web browser
//             //     flush();
        
//             //     // sleep one second
//             //     sleep(1);
//             // }

//             //Clear system output buffer
//             // flush();

//             //Read the size of the file
//             // readfile($fileData);

            // print_r($fileData);exit;
            return $response;
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }
}
