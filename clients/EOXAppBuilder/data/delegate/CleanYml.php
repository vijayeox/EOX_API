
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

    private function cleanTabSegment(&$tabs){
        for($i = 0; $i < count($tabs); $i++){
            if(!isset($tabs[$i]['detailsDuplicate']['data']['content'])) continue;
            $tabs[$i]['content'] = $tabs[$i]['detailsDuplicate']['data']['content'];
            unset($tabs[$i]['detailsDuplicate']);
            if(isset($tabs[$i]['name1'])){
                $tabs[$i]['name'] = $tabs[$i]['name1'];
                unset($tabs[$i]['name1']);
            }
            for($j = 0;$j < count($tabs[$i]['content']);$j++){
                if(isset($tabs[$i]['content'][$j]['tabs'])){
                    $tabs[$i]['content'][$j]['content'] = array('tabs' => $tabs[$i]['content'][$j]['tabs']);
                    unset($tabs[$i]['content'][$j]['tabs']);
                    $this->cleanTabSegment($tabs[$i]['content'][$j]['content']['tabs']);
                }else{
                    $this->haveOnlyRequiredInfo($tabs[$i]['content'][$j]);
                }
            }
        }
    }

    private function haveOnlyRequiredInfo(&$pageData){
        if ($pageData['type'] == 'HTMLViewer') {
            $pageData['content'] = isset($pageData['htmlContent']) ? $pageData['htmlContent'] : null;
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
            $whitelist = ['type' ,'template_file','form_id', 'formSource','form_name','parentFileId', 'fileId', 'readOnly'];
            $pageData = array_intersect_key( $pageData, array_flip( $whitelist ) );
        }

        if ($pageData['type'] == 'List') {
            $pageData['content'] = isset($pageData['gridContent']) ? $pageData['gridContent'] : null;
            $this->logger->info("CONTENT----".print_r($pageData,true));
            $whitelist = ['type' ,'route','content','gridContent','disableAppId','defaultFilters','pageable','autoRefreshInterval','exportToPDF'];
            $pageData = array_intersect_key( $pageData, array_flip( $whitelist ) );
        }
        if ($pageData['type'] == 'KanbanViewer') {
            $pageData['content'] = isset($pageData['kanbanContent']) ? $pageData['kanbanContent'] : null;
            $whitelist = ['type' ,'content'];
            $pageData = array_intersect_key( $pageData, array_flip( $whitelist ) );
        }
        if ($pageData['type'] == 'GoogleMapViewer') {
            $whitelist = ['type'];
            $pageData = array_intersect_key( $pageData, array_flip( $whitelist ) );
        }
        if ($pageData['type'] == 'ReactComponent' && isset($pageData['content'])) {
            $pageData['content'] = array('reactId' => isset($pageData['reactId']) ? $pageData['reactId'] : null);
        }
        if ($pageData['type'] == 'TabSegment' && isset($pageData['tabs'])) {
            $pageData['content'] = array('tabs' => $pageData['tabs']);
            $this->cleanTabSegment($pageData['content']['tabs']);
        }
        if ($pageData['type'] == 'Comment'){
            $pageData['content'] = isset($pageData['fileId'])? $pageData['fileId'] : null;
            $pageData = array_intersect_key( $pageData, array_flip( ['type' ,'content'] ) );
        }
        if ($pageData['type'] == 'ActivityLog'){
            $pageData['content'] = '{{uuid}}';
            $pageData['type'] = 'History';
            $pageData = array_intersect_key( $pageData, array_flip( ['type' ,'content'] ) );
        }
        if ($pageData['type'] == 'RenderButtons'){
            if(isset($pageData['renderContent'])){
                $pageData['content'] = $pageData['renderContent'];
            }
            $pageData = array_intersect_key( $pageData, array_flip( ['renderContent', 'content','type'] ) );
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
        $this->logger->info("DETAILS-----".print_r($newPageData,true));
        if (isset($newPageData['details']) && !empty($newPageData['details'])) {
            if ($newPageData['details'][0]['type'] == 'Comment') {
                $whitelist = ['type' ,'url'];
                $newPageData['details'][0] = array_intersect_key( $newPageData['details'][0], array_flip( $whitelist ) );
            }
            if ($newPageData['details'][0]['type'] == 'EntityViewer') {
                $whitelist = ['type' ,'page_id'];
                $newPageData['details'][0] = array_intersect_key( $newPageData['details'][0], array_flip( $whitelist ) );
            }
            if ($newPageData['details'][0]['type'] == 'Form') {
                $whitelist = ['type' ,'template_file','form_id', 'formSource','form_name','parentFileId', 'fileId', 'readOnly'];
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
            if ($newPageData['details'][0]['type'] == 'HTMLViewer') {
                $newPageData['details'][0]['content'] = $newPageData['details'][0]['htmlContent'];
                $whitelist = ['type' ,'htmlContent','content'];
                $newPageData['details'][0] = array_intersect_key( $newPageData['details'][0], array_flip( $whitelist ) );
            }
        }        
    }

    private function cleanUpList(&$pagesContent){
        $pagesContent = array_map(function ($newPages){
            $this->haveOnlyRequiredInfo($newPages);
            if($newPages['type'] == 'List'){
                unset($newPages["gridContent"]);
                if (isset($newPages['route']) && empty($newPages['route'])) {
                    unset($newPages['route']);
                }
                if (isset($newPages['content']['actions'])) {                    
                    $newPages['content']['actions'] = array_map(function ($newPageData){
                        $this->cleanUpyml($newPageData);
                        return $newPageData;
                    }, $newPages['content']['actions']);
                }
                if (isset($newPages['content']['defaultFilters']) && empty($newPages['content']['defaultFilters'])) {
                    unset($newPages['content']['defaultFilters']);
                }
            $this->logger->info("OPERATIONS----".print_r($newPages,true));
                if (isset($newPages['content']['operations']) && empty($newPages['content']['operations']['title'])) {
                    unset($newPages['content']['operations']);
                }

                if (isset($newPages['content']['operations']['title'])) {
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
        //cleaning permission data inside privilge here
        if (isset($descriptorData["privilege"])) {
            foreach ($descriptorData["privilege"] as &$i) {
                $this->logger->info("ENTERING LOOP check fr i val | inside the priv".print_r($i,true));

                if (isset($i["permission"]["15"]) && $i["permission"]["15"] == true) { $i["permission"] = "15"; }
                elseif (isset($i["permission"]["7"]) && $i["permission"]["7"] == true) { $i["permission"] = "7"; }
                elseif (isset($i["permission"]["3"]) && $i["permission"]["3"] == true) { $i["permission"] = "3"; }
                elseif (isset($i["permission"]["1"]) && $i["permission"]["1"] == true) { $i["permission"] = "1"; }
                
            }
        }
        //cleaning up the permision data inside roles here
        if (isset($descriptorData["role"])) {
        $this->logger->info("ENTERING LOOP | role");
        foreach ($descriptorData["role"] as &$i) {
            if (isset($i["privileges"])) {
                foreach ($i["privileges"] as &$j) {
                    $this->logger->info("JJJ | role".print_r($j,true));
                    if (isset($j['privilege_name']) && is_array($j['privilege_name'])) {
                        $privilegeArray = $j['privilege_name'];
                        unset($j['privilege_name']);
                        $j['privilege_name'] = $privilegeArray['name'];
                    }
                    if (isset($j["permission"]["15"]) && $j["permission"]["15"] == true) { $j["permission"] = "15"; }
                    elseif (isset($j["permission"]["7"]) && $j["permission"]["7"] == true) { $j["permission"] = "7"; }
                    elseif (isset($j["permission"]["3"]) && $j["permission"]["3"] == true) { $j["permission"] = "3"; }
                    elseif (isset($j["permission"]["1"]) && $j["permission"]["1"] == true) { $j["permission"] = "1"; }
                    $this->logger->info("ENTERING LOOP | role > privileges");
                }
            }
            
        }
        }

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
        if (isset($descriptorData["businessRole"]) && empty($descriptorData['businessRole'][0]['name'])) {
            unset($descriptorData["businessRole"]);
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