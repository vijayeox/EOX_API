<?php

use Oxzion\AppDelegate\AbstractDocumentAppDelegate;
use Oxzion\Db\Persistence\Persistence;
use Oxzion\Utils\UuidUtil;
use Oxzion\Utils\ArtifactUtils;
use Oxzion\PDF\PDF_Watermarker;

class PolicyDocument extends AbstractDocumentAppDelegate
{
    protected $type;
    protected $template;
    
    public function __construct(){
        parent::__construct();
        $this->type = 'policy';
        $this->template = array(
            'Individual Professional Liability' 
                => array('template' => 'ProfessionalLiabilityCOI',
                'header' => 'COIheader.html',
                'footer' => 'COIfooter.html',
                'slWording' => 'SL_Wording.pdf',
                'policy' => 'Individual_Professional_Liability_Policy.pdf',
                'aiTemplate' => 'Individual_PL_AI',
                'blanketForm' => 'Individual_AI_Blanket_Endorsement.pdf',
                'aiheader' => 'IPL_AI_header.html',
                'aifooter' => null,
                'iplScuba' => 'PL_Scuba_Fit_Endorsement.pdf',
                'iplCylinder' => 'PL_Cylinder_Endorsement.pdf',
                'iplEquipment' => 'PL_Equipment_Liability_Endorsement.pdf'),
            'Dive Boat' 
                => array('template' => 'DiveBoatCOI',
                'header' => 'DiveBoatHeader.html',
                'footer' => 'DiveBoatFooter.html',
                'slWording' => 'SL_Wording.pdf',
                'policy' => 'Dive_Boat_Policy.pdf',
                'cover_letter' => 'Dive_Boat_Cover_Letter',
                'lheader' => 'letter_header.html',
                'lfooter' => 'letter_footer.html',
                'instruct' => 'Instructions_To_Insured.pdf',
                'aiTemplate' => 'DiveBoat_AI',
                'aiheader' => 'DiveBoat_AI_header.html',
                'aifooter' => 'DiveBoat_AI_footer.html',
                'eTemplate' => 'DiveBoat_Endorsement.tpl',
                'eheader' => 'DB_Endorsement_header.html',
                'efooter' => 'DB_Endorsement_footer.html',
                'lpTemplate' => 'DiveBoat_LP',
                'lpheader' => 'DiveBoat_LP_header.html',
                'lpfooter' => 'DiveBoat_LP_footer.html',
                'gtemplate' => 'Group_PL_COI',
                'gheader' => 'Group_header.html',
                'gfooter' => 'Group_footer.html',
                'nTemplate' => 'Group_PL_NI',
                'nheader' => 'Group_NI_header.html',
                'nfooter' => 'Group_NI_footer.html',
                'aniTemplate' => 'DiveBoat_ANI',
                'aniheader' => 'DB_Quote_ANI_header.html',
                'anifooter' => null,
                'waterEndorsement' => 'DB_In_Water_Crew_Endorsement.pdf',
                'blanketForm' => 'DB_AI_Blanket_Endorsement.pdf',
                'groupExclusions' => 'Group_Exclusions.pdf'),
            'Dive Store'
                => array('template' => array('liability' => 'DiveStore_Liability_COI','property' => 'DiveStore_Property_COI'),
                        'header' => 'DiveStoreHeader.html',
                        'footer' => 'DiveStoreFooter.html',
                        'slWording' => 'SL_Wording.pdf',
                        'policy' => array('liability' => 'Dive_Store_Liability_Policy.pdf','property' => 'Dive_Store_Property_Policy.pdf'),
                        'cover_letter' => 'Dive_Store_Cover_Letter',
                        'lheader' => 'letter_header.html',
                        'lfooter' => 'letter_footer.html',
                        'aiTemplate' => 'DiveStore_AI',
                        'aiheader' => 'DiveStore_AI_header.html',
                        'aifooter' => 'DiveStore_AI_footer.html',
                        'lpTemplate' => 'DiveStore_LP',
                        'lpheader' => 'DiveStore_LP_header.html',
                        'lpfooter' => 'DiveStore_LP_footer.html',
                        'nTemplate' => 'Group_PL_NI',
                        'nheader' => 'Group_NI_header.html',
                        'nfooter' => 'Group_NI_footer.html',
                        'aniTemplate' => 'DiveStore_ANI',
                        'aniheader' => 'DS_Quote_ANI_header.html',
                        'anifooter' => null,
                        'gtemplate' => 'Group_PL_COI',
                        'gheader' => 'Group_header.html',
                        'gfooter' => 'Group_footer.html',
                        'alheader' => 'DiveStore_AL_header.html',
                        'alfooter' => 'DiveStore_AL_footer.html',
                        'alTemplate' => 'DiveStore_AdditionalLocations',
                        'travelAgentEO' => 'Travel_Agents_PL_Endorsement.pdf',
                        'groupExclusions' => 'Group_Exclusions.pdf'),
            'Emergency First Response'
                => array('template' => 'Emergency_First_Response_COI',
                'header' => 'EFR_header.html',
                'footer' => 'EFR_footer.html',
                'slWording' => 'SL_Wording.pdf',
                'policy' => 'Policy.pdf',
                'aiTemplate' => 'EFR_AI',
                'aiheader' => 'EFR_AI_header.html',
                'aifooter' => 'EFR_AI_footer.html')
        );

        $this->endorsementOptions = array('modify_personalInformation','modify_coverage','modify_additionalInsured','modify_businessAndPolicyInformation','modify_boatUsageCaptainCrewSchedule','modify_boatDeatails','modify_additionalInsured','modify_lossPayees','modify_groupProfessionalLiability');
    }
        
        public function execute(array $data,Persistence $persistenceService) 
        {     
            $documents = array();
            $options = array();
            $this->logger->info("Template Data Source - ".print_r($data, true));
            $date = ''; 
            $this->logger->info("Executing Policy Document");
            $length = 0;
            $startDate = $data['start_date'];
            $endDate = $data['end_date'];

            if(isset($data['update_date'])){
                $updateDate = $data['update_date'];
            }
            
            if(isset($data['previous_policy_data'])){
                $previous_data = array();
                $previous_data = json_decode($data['previous_policy_data'],true);
                $length = sizeof($previous_data);
            }
            $this->setPolicyInfo($data,$persistenceService,$length);
            
            $dest = $data['dest'];
            if($this->type == 'quote' || $this->type == 'endorsementQuote'){
                $dest['relativePath'] = $dest['relativePath'].'Quote/';
                $dest['absolutePath'] = $dest['absolutePath'].'Quote/';
            }


            if(isset($data['state'])){
                $data['state_in_short'] = $this->getStateInShort($data['state'],$persistenceService);
            }

            if(isset($data['business_state'])){
                $data['state_in_short'] = $this->getStateInShort($data['business_state'],$persistenceService);
            }
            $this->logger->info("Data------------------ ".print_r($data,true));
            unset($data['dest']);

            $temp = $data;
            foreach ($temp as $key => $value) {
                if(is_array($temp[$key])){
                    $temp[$key] = json_encode($value);
                }
            }

            if($data['product'] == "Individual Professional Liability" || $data['product'] == "Emergency First Response"){
                if(isset($data['careerCoverage']) || isset($data['scubaFit']) || isset($data['cylinder']) || isset($data['equipment'])){
                    $this->logger->info("DOCUMENT careerCoverage || scubaFit || cylinder || equipment");
                    $coverageList = array();
                    array_push($coverageList,$data['careerCoverage']);
                  if($data['product'] == "Individual Professional Liability"){   
                        if(isset($data['scubaFit']) && $data['scubaFit'] == "scubaFitInstructor"){
                            $documents['scuba_fit_document'] = $this->copyDocuments($data,$dest['relativePath'],'iplScuba');
                            array_push($coverageList,$data['scubaFit']);
                        }
                        if(isset($data['cylinder']) && ($data['cylinder'] == "cylinderInspector" || $data['cylinder'] == "cylinderInstructor" || $data['cylinder'] == "cylinderInspectorAndInstructor")){
                            $documents['cylinder_document'] = $this->copyDocuments($data,$dest['relativePath'],'iplCylinder');
                            array_push($coverageList,$data['cylinder']);
                        }
                        
                        if(isset($data['equipment']) && $data['equipment'] == "equipmentLiabilityCoverage"){
                            $documents['equipment_liability_document'] = $this->copyDocuments($data,$dest['relativePath'],'iplEquipment');
                        }

                        if(isset($data['upgradeCareerCoverage'])){
                            if(!is_array($data['upgradeCareerCoverage'])){  
                                $coverageOnCsrReview = json_decode($data['upgradeCareerCoverage'],true);    
                                $data['upgradeCareerCoverage'] = $coverageOnCsrReview;  
                            }
                            $temp['upgradeCareerCoverageVal'] = $data['upgradeCareerCoverage']['label'];    
                            array_push($coverageList,$temp['upgradeCareerCoverageVal']);
                        }
                        if(isset($data['upgradecylinder']) && !is_array($data['upgradecylinder'])){    
                            $cylinderOnCsrReview = json_decode($data['upgradecylinder'],true);  
                            $data['upgradecylinder'] = $cylinderOnCsrReview;
                            $data['cylinder'] = $data['upgradecylinder']['value'];
                            $temp['cylinderPriceVal'] = $data['upgradecylinder']['label'];
                        }
                        if(isset($data['upgradeExcessLiability']) && !is_array($data['upgradeExcessLiability'])){ 
                            $excessLiabilityOnCsrReview = json_decode($data['upgradeExcessLiability'],true);    
                            $data['upgradeExcessLiability'] = $excessLiabilityOnCsrReview;
                            $data['excessLiability'] = $data['upgradeExcessLiability']['value'];
                        }
                    }
                    $result = $this->getCoverageName($coverageList,$data['product'],$persistenceService);
                    $result = json_decode($result,true);
                    if($data['product'] == "Individual Professional Liability"){
                        if(isset($result[$data['scubaFit']])){
                            $temp['scubaFitVal'] = $result[$data['scubaFit']];
                        }
                        if(isset($result[$data['cylinder']]) && !isset($temp['cylinderPriceVal'])){
                            $temp['cylinderPriceVal'] = $result[$data['cylinder']];
                        }
                    }
                    
                    if( isset($temp['upgradeCareerCoverageVal']) && isset($result[$temp['upgradeCareerCoverageVal']])){
                        $data['upgradeCareerCoverageVal'] = $result[$temp['upgradeCareerCoverageVal']];
                    }
                    $temp['careerCoverageVal'] = $result[$data['careerCoverage']];
                }

                if(isset($temp['AdditionalInsuredOption']) && $temp['AdditionalInsuredOption'] == 'newListOfAdditionalInsureds'){
                    $this->logger->info("DOCUMENT AdditionalInsuredOption");
                    $documents['additionalInsured_document'] = array($this->generateDocuments($temp,$dest,$options,'aiTemplate','aiheader','aifooter'));
                }

                if(isset($this->template[$temp['product']]['blanketForm'])){
                    $this->logger->info("DOCUMENT blanketForm");
                    $documents['blanket_document'] = $this->copyDocuments($temp,$dest['relativePath'],'blanketForm');
                }
            }
            else if($data['product'] == "Dive Boat"){
                if(isset($this->template[$data['product']]['instruct'])){
                    $this->logger->info("DOCUMENT instruct");
                    $documents['instruct'] = $this->copyDocuments($data,$dest['relativePath'],'instruct');
                }

                if($this->type != 'endorsementQuote' && $this->type != 'endorsement'){
                    if(isset($temp['additionalInsured']) && $temp['additional_insured_select'] == 'newListOfAdditionalInsureds'){
                    $this->logger->info("DOCUMENT additionalInsured");
                    $temp['additionalInsured'] = json_decode($temp['additionalInsured'],true);
                    for($i = 0;$i< sizeof($temp['additionalInsured']);$i++){
                        $temp['additionalInsured'][$i]['state_in_short'] = $this->getStateInShort($temp['additionalInsured'][$i]['state'],$persistenceService);
                    }
                    $temp['additionalInsured'] = json_encode($temp['additionalInsured']);
                    $documents['additionalInsured_document'] = $this->generateDocuments($temp,$dest,$options,'aiTemplate','aiheader','aifooter');
                    }    
                }
                

                if(isset($temp['groupPL']) && $temp['groupProfessionalLiability'] == 'yes'){
                    if($this->type == 'quote' || $this->type == 'endorsementQuote'){
                         $documents['roster_certificate'] = $this->generateDocuments($temp,$dest,$options,'roster','rosterHeader','rosterFooter',null,$length);
                         $documents['roster_pdf'] = $this->copyDocuments($temp,$dest['relativePath'],'rosterPdf');                         
                    }
                    else{
                        $this->logger->info("DOCUMENT groupPL");
                        $documents['group_coi_document'] = $this->generateDocuments($temp,$dest,$options,'gtemplate','gheader','gfooter');


                        if(isset($temp['additionalNamedInsured']) && $temp['additional_named_insureds_option'] == 'yes'){
                        $this->logger->info("DOCUMENT additionalNamedInsured");
                        $documents['additionalNamedInsured_document'] = $this->generateDocuments($temp,$dest,$options,'aniTemplate','aniheader','anifooter');
                        }

                        if(isset($temp['namedInsureds']) && $temp['named_insureds'] == 'yes'){
                        $this->logger->info("DOCUMENT namedInsured");
                        $documents['named_insured_document'] = $this->generateDocuments($temp,$dest,$options,'nTemplate','nheader','nfooter');
                        }
                    }

                    $documents['group_exclusions'] = $this->copyDocuments($temp,$dest['relativePath'],'groupExclusions');
                }

                if(isset($temp['loss_payees']) && $temp['loss_payees'] == 'yes'){
                    $documents['loss_payee_document'] = $this->generateDocuments($temp,$dest,$options,'lpTemplate','lpheader','lpfooter');
                }

                if(isset($this->template[$temp['product']]['cover_letter'])){
                    $this->logger->info("DOCUMENT cover_letter");
                    $documents['cover_letter'] = $this->generateDocuments($temp,$dest,$options,'cover_letter','lheader','lfooter');
                }

                if($this->type == 'quote' || $this->type == 'endorsementQuote'){
                    if(!isset($temp['CrewInBoatCount']) || $temp['CrewInBoatCount'] == ''){
                        $documents['boat_acknowledgement'] = $this->copyDocuments($temp,$dest['relativePath'],'boatAcknowledgement');
                    }
                    if(!isset($temp['CrewInWaterCount']) || $temp['CrewInWaterCount'] == ''){
                         $documents['water_acknowledgement'] = $this->copyDocuments($temp,$dest['relativePath'],'waterAcknowledgement');
                    }

                    if(isset($data['quoteRequirement'])){
                        if(is_string($data['quoteRequirement'])){
                            $data['quoteRequirement'] = json_decode($data['quoteRequirement'],true);
                        } 
                        for($i = 0;$i < sizeof($data['quoteRequirement']);$i++){
                            if($data['quoteRequirement'][$i]['quoteInfo'] == 'Hurricane Questionnaire.'){
                                $documents['hurricane_questionnaire'] = $this->copyDocuments($temp,$dest['relativePath'],'hurricaneQuestionnaire');
                            }
                        }
                        $data['quoteRequirement'] = json_encode($data['quoteRequirement']);
                    }
                }


                if(isset($temp['CrewInWaterCount']) && $temp['CrewInWaterCount'] != ''){
                    $documents['water_endorsement_certificate'] = $this->copyDocuments($temp,$dest['relativePath'],'waterEndorsement');
                }
                   

                 if(isset($this->template[$temp['product']]['blanketForm'])){
                    $this->logger->info("DOCUMENT blanketForm");
                    $documents['blanket_document'] = $this->copyDocuments($temp,$dest['relativePath'],'blanketForm');
                }
            }
            else if($data['product'] == "Dive Store"){
                if(isset($temp['additionalInsured']) && (isset($temp['additionalInsuredSelect']) && $temp['additionalInsuredSelect']=="yes")){
                    $this->logger->info("DOCUMENT additionalInsured");
                    $documents['additionalInsured_document'] = $this->generateDocuments($temp,$dest,$options,'aiTemplate','aiheader','aifooter');
                }

                if(isset($temp['lossPayees']) && $temp['lossPayeesSelect']=="yes"){
                    $this->logger->info("DOCUMENT lossPayees");
                    $documents['loss_payee_document'] = $this->generateDocuments($temp,$dest,$options,'lpTemplate','lpheader','lpfooter');
                }

                if(isset($temp['additionalLocations']) && is_array($temp['additionalLocations']) && $temp['additionalLocationsSelect']=="yes"){
                    for($i=0; $i<sizeof($temp['additionalLocations']);$i++){
                        $this->logger->info("DOCUMENT additionalLocations (additional named insuredes");
                        $temp["additionalLocationData"] = $temp['additionalLocations'][$i];
                        $documents['additionalLocations_document_'.$i] = $this->generateDocuments($temp,$dest,$options,'alTemplate','alheader','alfooter');
                        unset($temp["additionalLocationData"]);
                    }
                }

                if(isset($temp['groupPL']) && $temp['groupProfessionalLiability'] == 'yes'){
                    if($this->type == 'quote' || $this->type == 'endorsementQuote'){
                         $documents['roster_certificate'] = $this->generateDocuments($temp,$dest,$options,'roster','rosterHeader','rosterFooter',null,$length);
                         $documents['roster_pdf'] = $this->copyDocuments($temp,$dest['relativePath'],'rosterPdf');                         
                    }
                    else{
                        $this->logger->info("DOCUMENT groupPL");
                        $documents['group_coi_document'] = $this->generateDocuments($temp,$dest,$options,'gtemplate','gheader','gfooter');


                        if(isset($temp['additionalNamedInsured']) && $temp['additional_named_insureds_option'] == 'yes'){
                        $this->logger->info("DOCUMENT additionalNamedInsured");
                        $documents['additionalNamedInsured_document'] = $this->generateDocuments($temp,$dest,$options,'aniTemplate','aniheader','anifooter');
                        }

                        if(isset($temp['namedInsureds']) && $temp['named_insureds'] == 'yes'){
                        $this->logger->info("DOCUMENT namedInsured");
                        $documents['named_insured_document'] = $this->generateDocuments($temp,$dest,$options,'nTemplate','nheader','nfooter');
                        }
                    }

                    $documents['group_exclusions'] = $this->copyDocuments($temp,$dest['relativePath'],'groupExclusions');
                }

                if(isset($this->template[$temp['product']]['cover_letter'])){
                    $this->logger->info("DOCUMENT cover_letter");
                    $documents['cover_letter'] = $this->generateDocuments($temp,$dest,$options,'cover_letter','lheader','lfooter');
                }

                if(isset($this->template[$temp['product']]['travelAgentEO']))   {
                    if(isset($temp['TravelAgentEOFP']) && $temp['TravelAgentEOFP']){
                        $this->logger->info("DOCUMENT TravelAgentEOFP");
                        $documents['Travel_Agents_PL_Endorsement'] = $this->copyDocuments($temp,$dest['relativePath'],'travelAgentEO');
                    }
                }

            }

            if($this->type != 'quote'){
                if(isset($temp['liability'])){
                    $this->logger->info("DOCUMENT liability_coi_document");
                    $documents['liability_coi_document'] = $this->generateDocuments($temp,$dest,$options,'template','header','footer','liability');
                    $this->logger->info("DOCUMENT liability_policy_document");
                    $documents['liability_policy_document'] = $this->copyDocuments($temp,$dest['relativePath'],'policy','liability');
                }
                
                if(isset($temp['property'])){
                    $this->logger->info("DOCUMENT property_coi_document");
                    $documents['property_coi_document']  = $this->generateDocuments($temp,$dest,$options,'template','header','footer','property');
                    $this->logger->info("DOCUMENT property_policy_document");
                    $documents['property_policy_document'] = $this->copyDocuments($temp,$dest['relativePath'],'policy','property');
                }
            }

            if(!isset($documents['property_coi_document']) && !isset($documents['liability_coi_document'])){
                $this->logger->info("DOCUMENT coi_document");
                $this->logger->info("Policy Documnet Generation");
                if($this->type == 'endorsement' || $this->type == 'endorsementQuote'){
                    $endorsementFileName = 'Endorsement - '.$length;
                    $documents[$endorsementFileName] = $this->generateDocuments($temp,$dest,$options,'template','header','footer',null,$length);
                }else{
                    $policyDocuments = $this->generateDocuments($temp,$dest,$options,'template','header','footer');
                    if(is_array($policyDocuments)){
                        foreach ($policyDocuments as $key => $value) {
                            $documents[$key] = $value;
                        }
                    }else if($temp['product'] == 'Individual Professional Liability'){
                        $documents['coi_document']  = array($policyDocuments);
                    }else{
                        $documents['coi_document']  = $policyDocuments;
                    }
                }
                if($this->type != 'quote' && $this->type != 'endorsementQuote')
                {
                    $policyDocuments = $this->copyDocuments($temp,$dest['relativePath'],'policy');
                    if(is_array($policyDocuments)){
                        foreach ($policyDocuments as $key => $value) {
                            $documents[$key] = $value;
                        } 
                    } else {
                        $documents['policy_document'] = $policyDocuments;
                    }
                }
            }
            
            if($this->type == 'lapse'){
                $this->logger->info("DOCUMENT lapse");
                return $this->generateDocuments($data,$dest,$options,'ltemplate','lheader','lfooter');
            }
            
            if(isset($this->template[$temp['product']]['slWording'])){        
                if($temp['product'] == 'Dive Store'){
                    if($temp['business_state'] == 'California'){
                            $documents['slWording'] = $this->copyDocuments($temp,$dest['relativePath'],'slWording');
                        }
                    }
                else{
                    if($temp['state'] == 'California'){
                            $documents['slWording'] = $this->copyDocuments($temp,$dest['relativePath'],'slWording');
                    }
                }
            }




            $this->logger->info("temp".print_r($data,true));
            $this->logger->info("Documents :".print_r($documents,true));
            if($temp['product'] == 'Individual Professional Liability'){
                if(isset($data['documents']['coi_document'][0]) && isset($documents['coi_document'][0])){
                    $destinationForWatermark = $dest['absolutePath'].'../../'.$data['documents']['coi_document'][0];
                    $this->addWaterMark($destinationForWatermark,"INVALID");
                    array_push($documents['coi_document'],$data['documents']['coi_document'][0]);
                }
                if(isset($data['documents']['additionalInsured_document'][0]) && isset($documents['additionalInsured_document'][0])){
                    $destinationForWatermark = $dest['absolutePath'].'../../'.$data['documents']['additionalInsured_document'][0];
                    $this->addWaterMark($destinationForWatermark,"INVALID");
                    array_push($documents['additionalInsured_document'],$data['documents']['additionalInsured_document'][0]);
                }
                $data['documents'] = $documents;
            }else if($this->type == 'endorsement' || $this->type == 'endorsementQuote'){
                $data['documents'] = json_decode($data['documents'],true);
                if($this->type == 'endorsement'){
                    if(isset($data['documents']['roster_certificate'])){
                        unset($data['documents']['roster_certificate']);
                    }
                    if(isset($data['documents']['roster_pdf'])){
                        unset($data['documents']['roster_pdf']);
                    }
                }
                $data['documents'] = array_merge($data['documents'],$documents);
            }else{
                $data['documents'] = $documents;
            }
             
            if(isset($data['endorsement_options'])){
                if(isset($data['endorsementCoverage'])){
                    $data['endorsementCoverage'] = array();
                }
                if(isset($data['endorsementCylinder'])){
                    $data['endorsementCylinder'] = array();
                }
                if(isset($data['endorsementExcessLiability'])){
                    $data['endorsementExcessLiability'] = array();
                }
                if(is_string($data['endorsement_options'])){
                    $data['endorsement_options'] = json_decode($data['endorsement_options'],true);
                }
                if(is_array($data['endorsement_options'])){
                    foreach ($data['endorsement_options'] as $key => $val){
                        $data['endorsement_options'][$key] = false;
                    }
                }
            }
            $this->processAttachments($data);
            $data['CSRReviewRequired'] = "";
            $data['rejectionReason'] = "";
            if($this->type == 'quote' || $this->type == 'endorsementQuote'){
                $data['policyStatus'] = "Quote Approval Pending";
            } else if($this->type == 'lapse'){
                $data['policyStatus'] = "Lapsed";
            } else {
                $data['policyStatus'] = "In Force";
                if(isset($data['endorsement_options'])){
                    unset($data['endorsement_options']);
                }
            }
            $data['start_date'] = $startDate;
            $data['end_date'] = $endDate;
            if(isset($data['update_date'])){
                $data['update_date'] = $updateDate;
            }
            
            $this->logger->info("Policy Document Generation",print_r($data,true));
            return $data;
        }
                 
        protected function setPolicyInfo(&$data,$persistenceService,$length)
        {
                if($this->type != "quote" || $this->type != "lapse" || $this->type != 'endorsementQuote'){
                    $coi_number = $this->generateCOINumber($data,$persistenceService);
                    if($this->type == 'endorsement'){
                        $data['certificate_no'] = $data['certificate_no'].' - '.$length;
                    }else{
                        $data['certificate_no'] = $coi_number;
                        if(isset($data['groupPL']) && $data['groupProfessionalLiability'] == 'yes'){
                            $data['group_certificate_no'] = 'S'.$coi_number;
                        }
                    }
                    
                }
                
                $date=date_create($data['start_date']);
                $data['start_date'] = date_format($date,"m/d/Y");
                $date=date_create($data['end_date']);
                $data['end_date'] = date_format($date,"m/d/Y");
                if(isset($data['update_date'])){
                    $date=date_create($data['update_date']);
                    $data['update_date'] = date_format($date,"m/d/Y");
                }
                if(isset($data['fileId'])){
                    $data['uuid'] = $data['fileId'];
                }
                if(!isset($data['uuid'])){
                    $data['uuid'] = UuidUtil::uuid();
                }
                
                $orgUuid = isset($data['orgUuid']) ? $data['orgUuid'] : ( isset($data['orgId']) ? $data['orgId'] :AuthContext::get(AuthConstants::ORG_UUID));        
                $data['orgUuid'] = $orgUuid;
                
                if($this->type != "lapse"){
                    $license_number = $this->getLicenseNumber($data,$persistenceService);
                    $policyDetails = $this->getPolicyDetails($data,$persistenceService);
                    $data['license_number'] = $license_number;
                    
                    if($policyDetails){
                        $data['policy_id'] = $policyDetails['policy_number'];
                        $data['carrier'] = $policyDetails['carrier'];
                    } 
                }

                if(isset($data['groupPL']) && $data['groupProfessionalLiability'] == 'yes'){
                    $product = 'Group Professional Liability';
                    $policyDetails = $this->getPolicyDetails($data,$persistenceService,$product);
                    if($policyDetails){
                        $data['group_policy_id'] = $policyDetails['policy_number'];
                        $data['group_carrier'] = $policyDetails['carrier'];
                    }    
                }
                
                $data['dest'] = ArtifactUtils::getDocumentFilePath($this->destination,$data['fileId'],array('orgUuid' => $orgUuid));
                
                return $data;
        }
            
        private function generateCOINumber($data,$persistenceService)
        {  
                $sequence = 0;
                $year = date('Y', strtotime($data['end_date']));
                $persistenceService->beginTransaction();
                try{ 
                    $select1 = "Select * FROM certificate_of_insurance_number WHERE product ='".$data['product']."' AND year = $year FOR UPDATE";
                    $this->logger->info("QUERY POLICY - ".print_r($select1,true));
                    $result1 = $persistenceService->selectQuery($select1); 
                    while ($result1->next()) {
                        $details[] = $result1->current();
                    }
                    if($result1->count() == 0){
                        $sequence ++;
                        $select2 = "INSERT INTO certificate_of_insurance_number (`product`,`year`,`sequence`) VALUES ('".$data['product']."', $year, $sequence)";
                        $result2 = $persistenceService->insertQuery($select2);
                    }else{ 
                        $sequence = $details[0]['sequence'];
                        $sequence ++;
                        $select3 = "UPDATE `certificate_of_insurance_number` SET `sequence` = $sequence WHERE product ='".$data['product']."' AND year = $year";
                        $result3 = $persistenceService->updateQuery($select3);
                    }
                    $persistenceService->commit();
                }catch(Exception $e){
                    print_r($e->getMessage());
                    $persistenceService->rollback();
                    throw $e;
                }
                $coi_number = $year. str_pad($sequence,5,'0',STR_PAD_LEFT);
                return $coi_number;
        }
            
        private function getCoverageName($data,$product,$persistenceService){
            $selectQuery = "SELECT group_concat(distinct concat('\"',`key`,'\":\"',coverage,'\"')) as name FROM premium_rate_card WHERE `key` in ('".implode("','", $data) . "')  AND product = '".$product."'";
            $resultQuery = $persistenceService->selectQuery($selectQuery);
            while ($resultQuery->next()) {
                $coverageName[] = $resultQuery->current();
            }
            if($resultQuery->count()!=0){
                return '{'.$coverageName[0]['name'].'}';
            }
            
        }
        private function getLicenseNumber($data,$persistenceService)
        {
            $selectQuery = "Select * FROM state_license WHERE state = '".$data['state']."'";
            $resultQuery = $persistenceService->selectQuery($selectQuery);
            while ($resultQuery->next()) {
                $stateLicenseDetails[] = $resultQuery->current();
            }
            if($resultQuery->count()!=0){
                return $stateLicenseDetails[0]['license_number'];
            }else{
                $selectQuery = "Select * FROM state_license WHERE state = 'California'";
                $resultQuery = $persistenceService->selectQuery($selectQuery);
                while ($resultQuery->next()) {
                    $stateLicenseDetails[] = $resultQuery->current();
                }
                return $stateLicenseDetails[0]['license_number'];
            }
        }
        
        private function getPolicyDetails($data,$persistenceService,$product = null)
        {  
            if(!isset($product)){
                $product = $data['product'];
            }
            $selectQuery = "Select carrier,policy_number FROM carrier_policy WHERE product ='".$product."' AND now() BETWEEN start_date AND end_date;";
            $resultQuery = $persistenceService->selectQuery($selectQuery); 
            while ($resultQuery->next()) {
                $policyDetails[] = $resultQuery->current();
            }
            if($resultQuery->count()!=0){
                return $policyDetails[0];
            }
            return NULL;
        }
        
        protected function generateDocuments(&$data,$dest,$options,$templateKey,$headerKey = null,$footerKey = null,$indexKey = null,$length = 0){
            $this->logger->info("Generate documents parameters templatekey is : ".print_r($templateKey, true));
            $this->logger->info("policy document destination is : ".print_r($dest, true));
            $this->logger->info("policy document options is : ".print_r($options, true));
            $this->logger->info("policy document data is : ".print_r($data, true));
            $this->logger->info("Product : ".print_r($this->template[$data['product']], true));
            $this->logger->info("TEMPLATE KEY ARRAY : ".print_r($this->template[$data['product']][$templateKey],true));
            $this->logger->info("index key : ".print_r($indexKey,true));
            
            if(isset($indexKey)){
                $this->logger->info("template with indexKey");
                $template =  $this->template[$data['product']][$templateKey][$indexKey];
            }else{
                $this->logger->info("template without indexKey");
                $template =  $this->template[$data['product']][$templateKey];
            }
            $this->logger->info("template slected: ".print_r($template, true));
            if(is_array($template)){
                $docDest = array();
                foreach ($template as $key => $value) {
                    $docDest[$value] = $dest['absolutePath'].$value.'.pdf';
                }
            } else {
                $docDest = $dest['absolutePath'].$template.'.pdf';
            }
            
            if($template == 'Group_PL_COI'){
                $options['generateOptions'] = array('disable_smart_shrinking' => 1);
            }
            
            if(isset($headerKey) && $headerKey !=null){ 
                $options['header'] =  $this->template[$data['product']][$headerKey];
            }
            if(isset($headerKey) && $footerKey !=null){ 
                $options['footer'] =  $this->template[$data['product']][$footerKey];
            }
            if(!is_array($docDest) && !file_exists($docDest)){
                $generatedDocument = $this->documentBuilder->generateDocument($template,$data,$docDest,$options);
            } else {
                if(is_array($docDest)){
                    $generatedDocuments = array();
                    foreach($docDest as $key => $doc){
                        $generatedDocuments[] = $this->documentBuilder->generateDocument($key,$data,$doc,$options);
                    }
                }
            }
            if($this->type == 'lapse'){
                $data['documents']['lapse_document'] = $dest['relativePath'].$template.'.pdf'; 
                return $data;
            }
            if($this->type == 'endorsement' || $this->type == 'endorsementQuote'){
                if(isset($indexKey) && $indexKey != null){
                    return $dest['relativePath'].$template.'-'.$length.'.pdf';
                }
                return $dest['relativePath'].$template.'.pdf';
            }else{
                if(is_array($docDest)){
                    $filesCreated = array();
                    foreach($docDest as $key => $doc){
                        $filesCreated[$key] = $dest['relativePath'].$key.'.pdf';
                    }
                    return $filesCreated;
                } else {
                    return $dest['relativePath'].$template.'.pdf';
                }
            }
            
        }
        
        private function copyDocuments(&$data,$dest,$fileKey,$indexKey =null){
            if(isset($indexKey)){
                $file =  $this->template[$data['product']][$fileKey][$indexKey];
            }else{
                $file =  $this->template[$data['product']][$fileKey];
            }
            if(!file_exists($dest)){
                if(is_array($file)){
                    $returnFiles = array();
                    foreach ($file as $k => $v) {
                        $this->documentBuilder->copyTemplateToDestination($v,$dest);
                        $returnFiles[$v] = $dest.$v;
                    }
                    return $returnFiles;
                } else {
                    $this->documentBuilder->copyTemplateToDestination($file,$dest);
                    return $dest.$file;
                }
            }
        }

        private function processAttachments(&$data){
            if(isset($data['csr_attachments']) && (!empty($data['csr_attachments']))){
                if(is_string($data['csr_attachments'])){
                    $data['csr_attachments'] = json_decode($data['csr_attachments'], true);
                }
                if(!isset($data['attachments'])){
                    $data['attachments'] = array();
                }else if(is_string($data['attachments'])){
                    $data['attachments'] = json_decode($data['attachments'], true);
                }
                foreach ($data['csr_attachments'] as $key => $value) {
                    $data['attachments'][] = $value;
                }
                $data['csr_attachments'] = "";
            }
        }

    private function addWaterMark($source,$text){
        $this->logger->info("Watermark source :",$source);
        $pdfwater = new PDF_Watermarker();
        $pdfwater->watermarkPDF($source,$text);
    }


    private function getStateInShort($state,$persistenceService){
        $selectQuery = "Select state_in_short FROM state_license WHERE state ='".$state."'";
        $resultSet = $persistenceService->selectQuery($selectQuery);
        if($resultSet->count() == 0){
            return $state;
        }else{
            while ($resultSet->next()) {
                $stateDetails[] = $resultSet->current();
            }       
            if(isset($stateDetails) && count($stateDetails)>0){                
                 $state = $stateDetails[0]['state_in_short'];                
            } 
        }
        return $state;
    }
}