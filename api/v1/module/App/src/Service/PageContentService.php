<?php
namespace App\Service;

use App\Model\PageContentTable;
use App\Model\PageContent;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Oxzion\Service\AbstractService;
use Oxzion\ValidationException;
use Zend\Db\Sql\Expression;
use Zend\Db\ResultSet\ResultSet;
use Exception;

class PageContentService extends AbstractService
{
    private $fileExt = ".json";
    public function __construct($config, $dbAdapter, PageContentTable $table)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
        $this->formsFolder = $this->config['FORM_FOLDER'];
    }

    public function getPageContent($appUuid, $pageUuid)
    { 
        $select = "SELECT * FROM ox_app_page 
        left join ox_app on ox_app_page.app_id = ox_app.id
         where ox_app_page.uuid =? and ox_app.uuid =?";
        $selectQuery = array($pageUuid,$appUuid);
        $selectResult = $this->executeQueryWithBindParameters($select,$selectQuery)->toArray();
        if(count($selectResult)>0){
            $queryString = " SELECT ox_app_page.name, ox_page_content.type,ox_form.uuid as form_id, ox_form.name as formName, ox_page_content.content
            FROM ox_page_content 
            LEFT JOIN ox_app_page on ox_app_page.id = ox_page_content.page_id
            LEFT OUTER JOIN ox_form on ox_page_content.form_id = ox_form.id
             WHERE ox_app_page.uuid =? ORDER BY ox_page_content.sequence ";
            $queryStringParams = array($pageUuid);
            $selectResult = $this->executeQueryWithBindParameters($queryString,$queryStringParams)->toArray();
            if (count($selectResult)==0) {
                return 0;
            }
        }else{
            return 0;
        }

        $result = array();       
        foreach($selectResult as $resultArray){
            if(isset($resultArray['formName'])){
                $filePath = $this->formsFolder.$appUuid."/".$resultArray['formName'].$this->fileExt;
                if(file_exists($filePath)){
                    $resultArray['content'] = file_get_contents($filePath);
                }
            }
            if($resultArray['type'] == 'List' || $resultArray['type'] == 'Form' || $resultArray['type'] == 'DocumentViewer'){ 
                $resultArray['content'] = json_decode($resultArray['content']);
            }else{
                $resultArray['content'] = $resultArray['content'];
            }  
            $result[] = $resultArray;
        }
        $content = array('content' => $result); 
        return array_merge($selectResult[0],$content);
    }

    public function savePageContent($pageId, &$data)
    { 
        $this->beginTransaction();
        $counter=0;
        try{
            $select = "DELETE from ox_page_content where page_id =?";
            $deleteQuery = array($pageId);
            $result = $this->executeQuerywithBindParameters($select,$deleteQuery);
            foreach($data as $key => $value){
                if($value['type'] == 'List' || $value['type'] == 'Search'){
                    $value['content'] = json_encode($value['content']);
                }
                if($value['type'] == 'Form' && isset($value['formUuid'])){
                    $value['form_id'] = $this->getIdFromUuid('ox_form', $value['formUuid']);
                }
                unset($value['id']);
                if (!isset($value['id'])) {
                    $value['created_by'] = AuthContext::get(AuthConstants::USER_ID);
                    $value['date_created'] = date('Y-m-d H:i:s');
                }
                $value['page_id'] = $pageId;
                $value['sequence'] = $key+1;
                $counter+=$this->savePageContentInternal($value);
            }
            $this->commit(); 
        }
        catch(Exception $e){
            $this->rollback();
            $this->logger->error($e->getMessage(),$e);
            throw $e;
        }
        return $counter;
    }

    public function createPageContent(&$data)
    {
        $page = new PageContent();
        if (!isset($data['id'])) {
            $data['created_by'] = AuthContext::get(AuthConstants::USER_ID);
            $data['date_created'] = date('Y-m-d H:i:s');
        }
        $data['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_modified'] = date('Y-m-d H:i:s');
        $page->exchangeArray($data);
        $page->validate();
        $this->beginTransaction();
        $count = 0;
        try {
            $count = $this->table->save($page);
            if ($count == 0) {
                $this->rollback();
                return 0;
            }
            if (!isset($data['id'])) {
                $id = $this->table->getLastInsertValue();
                $data['id'] = $id;
            }
            $this->commit();
        } catch (Exception $e) {
                $this->rollback();
                $this->logger->error($e->getMessage(), $e);
                throw $e;
            }
        return $count;
    }

    public function updatePageContent($id, &$data)
    {
        $obj = $this->table->get($id, array());
        if (is_null($obj)) {
            return 0;
        }
        $data['id'] = $id;
        $data['modified_by'] = AuthContext::get(AuthConstants::USER_ID);
        $data['date_modified'] = date('Y-m-d H:i:s');
        $file = $obj->toArray();
        $changedArray = array_merge($obj->toArray(), $data);
        $PageContent = new PageContent();
        $PageContent->exchangeArray($changedArray);
        $PageContent->validate();
        $this->beginTransaction();
        $count = 0;
        try {
            $count = $this->table->save($PageContent);
            if ($count == 0) {
                $this->rollback();
                return 0;
            }
            $this->commit();
        } catch (Exception $e) {
            print_r($e->getMessage());exit;
            $this->rollback();
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
        return $count;
    }

    public function deletePageContent($id)
    {
        $this->beginTransaction();
        $count = 0;
        try {
            $count = $this->table->delete($id);
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
        return $count;
    }

    public function getPageContents($appId=null, $filterArray = array())
    {
        $resultSet = $this->getDataByParams('ox_page_content', array("*"), $filterArray, null);
        return $resultSet->toArray();
    }
    public function getContent($id)
    {
        try{
            $queryString = "SELECT * FROM ox_page_content WHERE id =?";
            $selectQuery = array($id);
            $resultSet= $this->executeQuerywithBindParameters($queryString,$selectQuery)->toArray();
            // $resultSet = new ResultSet();
            // $resultSet->initialize($result);
            // $resultSet = $resultSet->toArray();
            if (isset($resultSet[0])) {
                return $resultSet[0];
            } else {
                return array();
            }
        }catch(Exception $e){
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }

    private function savePageContentInternal($data)
    {   
        try{
            if(isset($data['content']) && !is_string($data['content'])){
                $data['content'] = json_encode($data['content']);
            }
            $page = new PageContent();
            $page->exchangeArray($data);
            $page->validate();
            $count = 0;

            $count = $this->table->save($page);
            if ($count == 0) {
                return 0;
            }
            if (!isset($data['id'])) {
                $id = $this->table->getLastInsertValue();
                $data['id'] = $id;
            }
            return $count;
        }catch(Exception $e){
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
    }
}
