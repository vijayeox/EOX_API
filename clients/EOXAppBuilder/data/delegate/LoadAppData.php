
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
            $res = $this->getApp($data['app']['uuid']);
            if(!empty($data['form'])) {
                foreach ($data['form'] as $key => &$values) {
                    $values['template'][0]['originalName'] = $values['template_file'];
                //    $this->logger->info("Data in the filesize----".print_r(filesize($values['template_file']),true));
                //    $values['template'][0]['size'] = filesize($values['template_file']);
                }
            }
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

                            foreach ($value['pageContent']['data']['content'][0]['gridContent']['operations']['actions'] as $keyGridDupl => &$valueGridDupl) {
                                $valueGridDupl['detailsDuplicate']['data']['content'] = $valueGridDupl['details'];
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
        }
         $this->logger->info("Data out of delegate----".print_r($data,true));
        return $data;
    }
}
