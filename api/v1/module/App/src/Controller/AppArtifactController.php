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
            $response = new \Zend\Http\Response\Stream();
            $headers = new \Zend\Http\Headers();
            $headers->addHeaderLine('Content-Type', 'application/octet-stream')
                    ->addHeaderLine("Content-disposition: attachment; filename=\"".$artifactName."\"")
                    ->addHeaderLine('Access-Control-Expose-Headers: Content-Disposition')
                    ->addHeaderLine('Content-Length', filesize($fileData));
            $response->setHeaders($headers);
            return $response;
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->exceptionToResponse($e);
        }
    }
}
