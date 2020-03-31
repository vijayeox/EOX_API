<?php
use Oxzion\AppDelegate\MailDelegate;
use Oxzion\Db\Persistence\Persistence;
use Oxzion\Messaging\MessageProducer;
use Oxzion\DelegateException;

require_once __DIR__."/DispatchDocument.php";


class DispatchProposalDocument extends DispatchDocument {

    public function __construct(){
        $this->template = array(
            'Dive Boat' => 'diveBoatProposalMailTemplate',
            'Dive Store' => 'diveStoreProposalMailTemplate');
        parent::__construct();
    }

    
    public function execute(array $data,Persistence $persistenceService)
    {
        $this->logger->info("Proposal DOCUMENT --- ".json_encode($data));
        $data['template'] = $this->template[$data['product']];
        if(isset($data['documents']) && is_string($data['documents'])){
            $data['documents'] = json_decode($data['documents'],true);
        }

        $fileData =array();
        $errorFile = array();
        foreach($data['documents'] as $doc){
            $file = $this->destination.$doc;
            if(file_exists($file)){
                array_push($fileData, $file);         
            } else {
                $this->logger->error("File Not Found".$file);
                array_push($errorFile,$file);
            }
        }
        if(count($errorFile) > 0){
            $error = json_encode($errorFile);
            $this->logger->error("Documents Not Found".$error);
            throw new DelegateException('Documents Not Found','file.not.found',0,$errorFile);
        }
        $data['document'] =$fileData;
        $data['subject'] = 'Proposal Document';
        $data['url'] = $this->baseUrl. '?app=DiveInsurance&params={"type":"Form","activityInstanceId":"'.$data['activityInstanceId'].'","workflowInstanceId":"'.$data['workflowInstanceId'].'"}';
        $response = $this->dispatch($data);
        return $response;
    }
}
?>