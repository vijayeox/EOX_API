<?php

use Oxzion\AppDelegate\AbstractDocumentAppDelegate;

class IndividualLiabilityImpl extends AbstractDocumentAppDelegate {
    private $builder;
    public function __construct(){
        parent::__construct();
    }

    public function setDocumentBuilder($builder){
        $this->builder = $builder;
    }
    public function setTemplatePath($destination)
    {
        $this->destination = $destination;
    }
    public function execute(array $data, $persistenceService){ 
        $this->logger->info("executing IndividualLiability");
        if(!$this->builder){
            return array("Document Builder Not set");
        }
        if (in_array("Checking App Delegate", $data))
        {
           return array("Checking App Delegate");
        }

    }
}
?>