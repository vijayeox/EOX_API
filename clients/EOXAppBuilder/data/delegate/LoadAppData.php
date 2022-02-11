
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
        $this->logger->info("Data in the delegate----".print_r($data,true));
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

                            foreach ($value['pageContent']['data']['content'][0]['gridContent']['actions'] as $keyPdupl => &$valuePDupl) {
                                $valuePDupl['contentDuplicate']['data']['content'] = $valuePDupl['details'];
                            }
                            if (isset($value['pageContent']['data']['content'][0]['gridContent']['operations']['actions'])) {
                                foreach ($value['pageContent']['data']['content'][0]['gridContent']['operations']['actions'] as $keyGridDupl => &$valueGridDupl) {
                                    $valueGridDupl['detailsDuplicate']['data']['content'] = $valueGridDupl['details'];
                                }  
                            }  
                        }
                    }                   
                }
            }
             $valuepri = [];
            if(!empty($data['role'])) {
                foreach ($data['role'] as $key => &$value) {
                    foreach ($value['privileges'] as $keyPrivileges => $valuePrivileges) {
                        if (!empty($valuePrivileges['privilege_name'])) {
                            $valuepri['privilege_name']['name'] = $valuePrivileges['privilege_name'];
                            $valuepri['permission'] = $valuepri['privilege_name']['permission']= $valuePrivileges['permission'];
                            $value['privilegesDuplicate'][] = $valuepri;
                        }
                    }
                    array_shift($value['privilegesDuplicate']);
                }
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
         $this->logger->info("Data out of delegate----".print_r($data,true));
        return $data;
    }
}
