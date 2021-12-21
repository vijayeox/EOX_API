
<?php

use Oxzion\Db\Persistence\Persistence;
use Oxzion\AppDelegate\AbstractAppDelegate;
use Oxzion\AppDelegate\AppTrait;



class CleanYml extends AbstractAppDelegate
{
    use AppTrait;

    public function __construct()
    {
        parent::__construct();
    }

    public function execute(array $data, Persistence $persistenceService)
    {
        $this->logger->info("Data in the delegate----".print_r($data,true));
        $uuid = $data['app']['uuid'];
        $data = $this->cleanApplicationDescriptorData($data);
        $this->updateApp($uuid, $data);
        $this->logger->info("Data out of delegate----".print_r($data,true));
        return $data;
    }

    private function haveOnlyRequiredInfo(&$pageData){
        if ($pageData['type'] == 'HTMLViewer') {
            $whitelist = ['type' ,'htmlContent','content'];
            $pageData = array_intersect_key( $pageData, array_flip( $whitelist ) );
        }
        if ($pageData['type'] == 'Page') {
            $whitelist = ['type' ,'page_id'];
            $pageData = array_intersect_key( $pageData, array_flip( $whitelist ) );
        }

        if ($pageData['type'] == 'DashboardManager') {
            $whitelist = ['type' ,'dashboard_uuid','content'];
            $pageData = array_intersect_key( $pageData, array_flip( $whitelist ) );
        }

        if ($pageData['type'] == 'Form') {
            $whitelist = ['type' ,'template_file','form_id', 'formSource','form_name','parentFileId', 'fileId'];
            $pageData = array_intersect_key( $pageData, array_flip( $whitelist ) );
        }

        if ($pageData['type'] == 'List') {
            $whitelist = ['type' ,'route','content','gridContent','disableAppId','defaultFilters','pageable','autoRefreshInterval','exportToPDF'];
            $pageData = array_intersect_key( $pageData, array_flip( $whitelist ) );
        }
    }

    private function cleanUpyml(&$newPageData){ 
        if (isset($newPageData['paramsDuplicate'])) {           
            unset($newPageData['paramsDuplicate']);
        }
        if (isset($newPageData['contentDuplicate'])) {
            unset($newPageData['contentDuplicate']);
        }
        if (isset($newPageData['detailsDuplicate'])) {
            unset($newPageData['detailsDuplicate']);
        }
        if ($newPageData['details'][0]['type'] == 'Comment') {
            $whitelist = ['type' ,'url'];
            $newPageData['details'][0] = array_intersect_key( $newPageData['details'][0], array_flip( $whitelist ) );
        }
        if ($newPageData['details'][0]['type'] == 'EntityViewer') {
            $whitelist = ['type' ,'page_id'];
            $newPageData['details'][0] = array_intersect_key( $newPageData['details'][0], array_flip( $whitelist ) );
        }
        if ($newPageData['details'][0]['type'] == 'Form') {
            $whitelist = ['type' ,'template_file','form_id', 'formSource','form_name','parentFileId', 'fileId'];
            $newPageData['details'][0] = array_intersect_key( $newPageData['details'][0], array_flip( $whitelist ) );
        }
        if ($newPageData['details'][0]['type'] == 'API') {
            $whitelist = ['type' ,'route', 'typeOfRequest'];
            $newPageData['details'][0] = array_intersect_key( $newPageData['details'][0], array_flip( $whitelist ) );
        }
        if ($newPageData['details'][0]['type'] == 'Page') {
            $whitelist = ['type' ,'page_id'];
            $newPageData['details'][0] = array_intersect_key( $newPageData['details'][0], array_flip( $whitelist ) );
        }
        if ($newPageData['details'][0]['type'] == 'ButtonPopUp') {
            $whitelist = ['type' ,'params','fileId'];
            $newPageData['details'][0] = array_intersect_key( $newPageData['details'][0], array_flip( $whitelist ) );
        }
    }

    private function cleanUpList(&$pagesContent){
        $pagesContent = array_map(function ($newPages){
            $this->haveOnlyRequiredInfo($newPages);
            if($newPages['type'] == 'List'){
                unset($newPages["gridContent"]);
                $newPages['content']['actions'] = array_map(function ($newPageData){
                    $this->cleanUpyml($newPageData);
                    return $newPageData;
                }, $newPages['content']['actions']);

                if (empty($newPages['content']['operations'])) {
                    unset($newPages['content']['operations']);
                }

                if (isset($newPages['content']['operations'])) {
                    $newPages['content']['operations']['actions'] = array_map(function ($newPageOperation){
                        $this->cleanUpyml($newPageOperation);
                        return $newPageOperation;
                    }, $newPages['content']['operations']['actions']);
                }

            }  
            return $newPages;                      
        }, $pagesContent);
    }

    private function cleanApplicationDescriptorData(&$descriptorData)
    {
        $this->logger->info("DECRPTOR DATA AT ENTRANCE-----".print_r($descriptorData,true));
        if (isset($descriptorData['listOFForms'])) {
           unset($descriptorData['listOFForms']);
        }
        if (isset($descriptorData['listOFWorkflows'])) {
            unset($descriptorData['listOFWorkflows']);
        }
        if (isset($descriptorData["entity"])) {
            $descEntity = [];
            foreach ($descriptorData["entity"] as &$value) {
                if (isset($value["formFieldsValidationExcel"]) && empty($value['formFieldsValidationExcel'])) {
                    unset($value["formFieldsValidationExcel"]);
                }
                if (isset($value['field']) && array_key_exists('name', $value['field'][0]) && empty($value['field'][0]['name'])) {
                    unset($value['field']);
                }
                if (isset($value['participantRoleDuplicate'])) {
                    unset($value['participantRoleDuplicate']);
                }
                if (isset($value['pageContent']['data']['pagesList'])) {
                   unset($value['pageContent']['data']['pagesList']);
                }
                if (isset($value['pageContent']['data']['formsList'])) {
                    unset($value['pageContent']['data']['formsList']);
                }
                if (isset($value['pageContent']['metadata'])) {
                   unset($value['pageContent']['metadata']);
                }
                if(empty($value['pageContent'])){
                    unset($value['pageContent']);
                }
                if(empty($value['ryg_rule'])){
                    unset($value['ryg_rule']);
                }
                if (isset($value['name']) && !empty($value['name'])) {
                    array_push($descEntity, $value);
                }
            }
            $descriptorData["entity"] = $descEntity;
        }
        if (isset($descriptorData["menu"])) {
            $descriptorData["menu"] = array_map(function ($menu) {
                if (isset($menu["privilege"]) && empty($menu['privilege'])) {
                    unset($menu["privilege"]);
                }
                if (isset($menu["parent"]) && empty($menu['parent'])) {
                    unset($menu["parent"]);
                }
                return $menu;
            }, $descriptorData["menu"]);
        }

        if (isset($descriptorData["form"]) && empty($descriptorData['form'][0]['name'])) {
            unset($descriptorData["form"]);
        }
        if (isset($descriptorData["job"]) && empty($descriptorData['job'][0]['name'])) {
            unset($descriptorData["job"]);
        }
        if (isset($descriptorData["org"])) {
            //Handle single and multiple org definitions
            if (array_key_exists(0, $descriptorData["org"])) {
                foreach ($descriptorData["org"] as $key => $value) {
                    if (empty($descriptorData["org"][$key]["name"])) {
                        unset($descriptorData["org"][$key]);
                    }
                }
            } else {
                if (empty($descriptorData["org"]["name"])) {
                    unset($descriptorData["org"]);
                }
            }
        }
        if (isset($descriptorData["workflow"]) && empty($descriptorData['workflow'][0]['name'])) {
            unset($descriptorData["workflow"]);
        }
        if (isset($descriptorData["role"])) {
            $descriptorData["role"] = array_map(function ($role) {
                if (isset($role["privilegesDuplicate"])) {
                    unset($role["privilegesDuplicate"]);
                }
                return $role;
            }, $descriptorData["role"]);
        }
        if (isset($descriptorData["pages"])) {
            $descriptorData["pages"] = array_map(function ($pages) {
                if (isset($pages["pageContent"])) {
                    unset($pages["pageContent"]);
                }
                if (isset($pages["content"])){
              $this->cleanUpList($pages["content"]);
                }
                return $pages;
            }, $descriptorData["pages"]);
        }
        foreach ($descriptorData as $arrayElementkey => &$arrayElementvalue) {   
            $this->logger->info("DESC-----".print_r($arrayElementkey,true));
            if ($arrayElementkey == 'textFieldValidate' || 
                $arrayElementkey == 'formsList' || 
                $arrayElementkey == 'workflowDuplicate' || 
                $arrayElementkey == 'pagesList' ||
                $arrayElementkey == 'workflowId' ||
                $arrayElementkey == 'formDuplicate' ||
                $arrayElementkey == 'formId' ||
                $arrayElementkey == 'appId' ||
                $arrayElementkey == 'app_id' ||
                $arrayElementkey == 'page' ||
                $arrayElementkey == 'entity_page') {
                unset($descriptorData[$arrayElementkey]);
            }
        }
        $this->logger->info("**DESCRIPTOR DATA--***".print_r($descriptorData,true));
        return $descriptorData;
    }
}
