
<?php

use Oxzion\Db\Persistence\Persistence;
use Oxzion\AppDelegate\AbstractAppDelegate;
use Oxzion\AppDelegate\AppTrait;

class LoadAppData extends AbstractAppDelegate
{
    use AppTrait;

    public function __construct()
    {
        parent::__construct();
    }

    public function execute(array $data, Persistence $persistenceService)
    {
        $this->logger->info("Data in the delegate LoadAppData----".print_r($data,true));
        if (isset($data['app']['uuid'])) {
            $data['listOFForms']  =  $this->getArtifacts($data['app']['uuid'], 'form');  
            $data['listOFWorkflows']  =  $this->getArtifacts($data['app']['uuid'], 'workflow');
            if(!empty($data['pages'])) { 
                foreach ($data['pages'] as $key => &$value) {
                    $this->logger->info("Dapagesssss---".print_r($value,true));
                    $value['pageContent']['data']['content'] = $value['content'];
                    if (!empty($value['content'][0])) {
                        if ($value['content'][0]['type'] == 'List') {
                            $value['content'][0]['gridContent'] =  
                            $value['pageContent']['data']['content'][0]['gridContent'] =
                            $value['pageContent']['data']['content']['gridContent'] = $value['content'][0]['content'];
                            if (isset($value['pageContent']['data']['content'][0]['gridContent']['actions'])) {
                                foreach ($value['pageContent']['data']['content'][0]['gridContent']['actions'] as $keyPdupl => &$valuePDupl) {
                                    $valuePDupl['contentDuplicate']['data']['content'] = $valuePDupl['details'];
                                }
                            }
                            if (isset($value['pageContent']['data']['content'][0]['gridContent']['operations']['actions'])) {
                                foreach ($value['pageContent']['data']['content'][0]['gridContent']['operations']['actions'] as $keyGridDupl => &$valueGridDupl) {
                                    $valueGridDupl['detailsDuplicate']['data']['content'] = $valueGridDupl['details'];
                                } 
                            }   
                        }
                        if ($value['content'][0]['type'] == 'KanbanViewer') {
                            $value['content'][0]['kanbanContent'] = $value['content'][0]['content'];
                        }
                    }                   
                }
            }
    
            $copyDP = [];
            $copyP = [];
            if (!empty($data['privilege'])) {
                foreach($data['privilege'] as &$key) {
                    $this->logger->info("DATA PRIVILEGE KEY INPUT".print_r($key,true));
                    
                    $copyP['name'] = $key['name'];
                    if (!empty($key["permission"]['15']) && $key['permission']['15'] == true) {
                        $copyP['permission'] = array("15"=>true, "7"=>true, "3"=>true, "1"=>true);
                    }
                    elseif (!empty($key["permission"]['7']) && $key['permission']['7'] == true) {
                        $copyP['permission'] = array("15"=>false, "7"=>true, "3"=>true, "1"=>true);
                    }
                    elseif (!empty($key["permission"]['3']) && $key['permission']['3'] == true) {
                        $copyP['permission'] = array("15"=>false, "7"=>false, "3"=>true, "1"=>true);
                    }
                    elseif (!empty($key["permission"]['1']) && $key['permission']['1'] == true) {
                        $copyP['permission'] = array("15"=>false, "7"=>false, "3"=>false, "1"=>true);
                    }
                    array_push($copyDP, $copyP);
                    $this->logger->info("DATA PRIVILEGE KEY OUTPUT".print_r($copyP,true));
                    $copyP = [];
                }
                $data['privilege'] = $copyDP;
                $this->logger->info("DATA PRIVILEGE FINAL OUTPUT".print_r($data['privilege'],true));
            }
            
            if (!empty($data['role'])) {
                for ($x = 0; $x <= count($data['role']); $x++) {
                    if (!empty($data['role'][$x]['privileges'])) {
                        $this->logger->info("DATA ROLE ELEMENT IN".print_r($data['role'][$x],true));
                        for ($y = 0; $y <= count($data['role'][$x]['privileges']); $y++) {
                            if (!empty($data['role'][$x]['privileges'][$y]['permission'])) {
                                $this->logger->info("DATA ROLE PRIVILEGE ELEMENT IN".print_r($data['role'][$x]['privileges'][$y],true));
                                if (!empty($data['role'][$x]['privileges'][$y]['permission']['15']) && $data['role'][$x]['privileges'][$y]['permission']['15'] == true) {
                                    $data['role'][$x]['privileges'][$y]['permission'] = array("15"=>true, "7"=>true, "3"=>true, "1"=>true);
                                }
                                elseif (!empty($data['role'][$x]['privileges'][$y]['permission']['7']) && $data['role'][$x]['privileges'][$y]['permission']['7'] == true) {
                                    $data['role'][$x]['privileges'][$y]['permission'] = array("15"=>false, "7"=>true, "3"=>true, "1"=>true);
                                }
                                elseif (!empty($data['role'][$x]['privileges'][$y]['permission']['3']) && $data['role'][$x]['privileges'][$y]['permission']['3'] == true) {
                                    
                                    $data['role'][$x]['privileges'][$y]['permission'] = array("15"=>false, "7"=>false, "3"=>true, "1"=>true);
                                }
                                elseif (!empty($data['role'][$x]['privileges'][$y]['permission']['1']) && $data['role'][$x]['privileges'][$y]['permission']['1'] == true) {
                                    $data['role'][$x]['privileges'][$y]['permission'] = array("15"=>false, "7"=>false, "3"=>false, "1"=>true);
                                }
                            if (!empty($data['role'][$x]['privileges'][$y])) {
                                $this->logger->info("DATA ROLE PRIVILEGE ELEMENT OUT".print_r($data['role'][$x]['privileges'][$y],true));
                            }
                        }
                        $this->logger->info("DATA ROLE ELEMENT OUT".print_r($data['role'][$x],true));
                    }
                }
            }
                $this->logger->info("DATA ROLE FINAL OUTPUT".print_r($data['role'],true));
            }

            $valuePartiRole = [];
            if (!empty($data['entity'])) {
                foreach ($data['entity'] as $key => &$value) {
                    if (!empty($value['participantRole'])) {
                        foreach ($value['participantRole'] as $keyParticipantRole => $valueParticipantRole) {
                            if (!empty($valueParticipantRole['businessRole'])) {
                                $valuePartiRole['businessRole']['name'] = $valueParticipantRole['businessRole'];
                                $value['participantRoleDuplicate'][] = $valuePartiRole;
                            }
                        }
                    }
                }
                array_shift($value['participantRoleDuplicate']);
            }
        }
         $this->logger->info("Data out of delegate LOADAPPDATA----".print_r($data,true));
        return $data;
    }
}