<?php

namespace App\Service;

use App\Model\PageTable;
use App\Model\Page;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Oxzion\Service\AbstractService;
use Oxzion\ValidationException;
use Zend\Db\Sql\Expression;
use Oxzion\Utils\UuidUtil;
use App\Service\PageContentService;
use Oxzion\ServiceException;
use Exception;

class PageService extends AbstractService
{
    public function __construct($config, PageContentService $pageContentService, $dbAdapter, PageTable $table)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
        $this->pageContentService = $pageContentService;
    }
    public function savePage($routeData, &$data, $id = null)
    {
        $this->logger->info("save page - params - " . json_encode($routeData) . ", " . json_encode($data) . ", $id");
        $count = 0;
        $data['app_id'] = $this->getIdFromUuid('ox_app', $routeData['appId']);
        $content = false;
        if (isset($data['app_id'])) {
            $page = null;
            $content = isset($data['content']) ? $data['content'] : false;
            $this->beginTransaction();
            if (isset($id) && !empty($id)) {
                $page = $this->table->getByUuid($id);
                $data['uuid'] = $id;
                if ($page) {
                    $data['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
                    $data['date_modified'] = date('Y-m-d H:i:s');
                    $existingPage = $page->toArray();
                    $deleteQuery = "DELETE from ox_page_content where page_id = ?";
                    $whereParams = array($existingPage['id']);
                    $deleteResult = $this->executeUpdatewithBindParameters($deleteQuery, $whereParams);
                    $deleteRecord = $this->table->delete($existingPage['id'], ['app_id' => $data['app_id']]);
                    unset($data['id']);
                    unset($page->id);
                }
            }

            if (!$page) {
                $page = new Page();
                $data['uuid'] = (isset($id) && !empty($id)) ? $id : UuidUtil::uuid();
                $data['created_by'] = AuthContext::get(AuthConstants::USER_ID);
                $data['date_created'] = date('Y-m-d H:i:s');
            }
            $page->exchangeArray($data);
            $page->validate();
            try {
                if (isset($data['content'])) {
                    unset($data['content']);
                }
                $count = $this->table->save($page);
                if ($count == 0) {
                    throw new ServiceException("Page save failed", "page.save.failed");
                }
                if (!isset($data['id'])) {
                    $id = $this->table->getLastInsertValue();
                    $data['id'] = $id;
                }
                if (isset($content) && $content) {
                    $pageContent = $this->pageContentService->savePageContent($data['id'], $content);
                }
                $this->commit();
            } catch (Exception $e) {
                $this->rollback();
                $this->logger->error($e->getMessage(), $e);
                throw $e;
            }
        } else {
            throw new ServiceException("App Does not Exist", "app.not.found");
        }
        return $count;
    }

    public function deletePage($appUuid, $pageUuid)
    {
        $select = "SELECT ox_app_page.* from ox_app_page left join ox_app on ox_app.id = ox_app_page.app_id where ox_app.uuid =? AND ox_app_page.uuid =?";
        $whereQuery = array($appUuid, $pageUuid);
        $result = $this->executeQueryWithBindParameters($select, $whereQuery)->toArray();
        $count = 0;
        if (count($result) > 0) {
            $this->beginTransaction();
            try {
                $selectQuery = "DELETE from ox_page_content where page_id = ?";
                $selectParams = array($result[0]['id']);
                $resultSet = $this->executeQueryWithBindParameters($selectQuery, $selectParams);
                $count = $this->table->delete($this->getIdFromUuid('ox_app_page', $pageUuid), ['app_id' => $this->getIdFromUuid('ox_app', $appUuid)]);
                if ($count == 0) {
                    $this->rollback();
                    return 0;
                }
                $this->commit();
            } catch (Exception $e) {
                $this->rollback();
                $this->logger->error($e->getMessage(), $e);
                throw $e;
            }
        } else {
            throw new ServiceException("Page Not Found", "page.not.found");
        }
        return $count;
    }

    public function getPages($appUuid = null, $filterArray = array())
    {
        if (isset($appUuid)) {
            $filterArray['app_id'] = $this->getIdFromUuid('ox_app', $appUuid);
        }
        $resultSet = $this->getDataByParams('ox_app_page', array("*"), $filterArray, null);
        return $resultSet->toArray();
    }
    public function getPage($appUuid, $pageUuid)
    {
        try {
            $select = "SELECT ox_app_page.* FROM ox_app_page left join ox_app on ox_app.id = ox_app_page.app_id where ox_app_page.uuid=? and ox_app.uuid=?";
            $whereQuery = array($pageUuid, $appUuid);
            $response = $this->executeQueryWithBindParameters($select, $whereQuery)->toArray();
            if (count($response) == 0) {
                return 0;
            }
            return $response[0];
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }
    public function getPageByName($appId, $pageName)
    {
        try {
            $pageName = is_array($pageName) ? $pageName['name'] : $pageName;
            $select = "SELECT ox_app_page.* FROM ox_app_page left join ox_app on ox_app.id = ox_app_page.app_id where ox_app_page.name=? and ox_app.uuid=?";
            $whereQuery = array($pageName, $appId);
            $response = $this->executeQueryWithBindParameters($select, $whereQuery)->toArray();
            if (count($response) == 0) {
                return 0;
            }
            return $response[0];
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }
}
