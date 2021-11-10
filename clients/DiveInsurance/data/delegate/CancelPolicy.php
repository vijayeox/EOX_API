<?php

use Oxzion\Db\Persistence\Persistence;
use Oxzion\Utils\ArtifactUtils;
use Oxzion\Utils\FileUtils;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Oxzion\AppDelegate\FileTrait;

require_once __DIR__ . "/PolicyDocument.php";


class CancelPolicy extends PolicyDocument
{
    use FileTrait;
    protected $documentBuilder;
    protected $type;
    protected $template;

    public function __construct()
    {
        parent::__construct();
        $this->type = 'cancel';
        $this->template = array(
            'Individual Professional Liability'
            => array(
                'template' => 'Cancellation_Approval',
                'header' => 'Cancellation_header.html',
                'footer' => 'Cancellation_footer.html'
            ),
            'Emergency First Response'
            => array(
                'template' => 'Cancellation_Approval',
                'header' => 'Cancellation_header.html',
                'footer' => 'Cancellation_footer.html'
            ),
            'Dive Boat'
            => array(
                'template' => 'Cancellation_Approval',
                'header' => 'Cancellation_header.html',
                'footer' => 'Cancellation_footer.html'
            ),
            'Dive Store'
            => array(
                'template' => 'Cancellation_Approval',
                'header' => 'Cancellation_header.html',
                'footer' => 'Cancellation_footer.html'
            ),
            'Group Professional Liability'
            => array(
                'template' => 'Cancellation_Approval',
                'header' => 'Cancellation_header.html',
                'footer' => 'Cancellation_footer.html'
            )
        );
    }

    public function execute(array $data, Persistence $persistenceService)
    {
        $this->logger->info("Executing Cancel Policy with data- " . json_encode($data));
        if (isset($data['data'])) {
            $fileData = json_decode($data['data'], true);
            unset($data['data']);
            $data = array_merge($fileData, $data);
        }
        if (isset($data['cancellationDate'])) {
            $data['cancellationDate'] = "";
        }
        $options = array();
        foreach ($data as $key => $row) {
            if (is_array($row)) {
                $data[$key] = json_encode($row);
            }
        }
        $value = json_decode($data['reasonforCsrCancellation'], true);
        $data['reasonforCsrCancellation'] = $value['value'];
        // $data['reinstateDocuments'] = $data['documents'];
        $data['reasonforRejection'] = isset($data['reasonforRejection']) ? $data['reasonforRejection'] : "Not Specified";
        $data['policyEndDate'] = date_format(date_create($data['end_date']),'Y-m-d');
        $data['cancelDate'] = isset($data['cancelDate']) ? date_format(date_create($data['cancelDate']),'Y-m-d') : date_create()->format("Y-m-d");
        $data['end_date'] = $data['cancelDate'];
        $data['policyStatus'] = "Cancelled";
        $data['carrierName'] = "";
        $data['policyId'] = "";
        $data['propPolicyId'] = "";
        $data['groupPolicyId'] = "";
        $data['propCarrierName'] = "";
        $data['groupCarrierName'] = "";
        $data['coverageTitle'] = "";
        $data['multiplePolicy'] = "no";
        $data['dbaName'] = isset($data['dba']) ? (($data['dba'] != "") ? "DBA: ".$data['dba'] : "") : "";
        if ($data['product'] == "Dive Store") {
            $data['cancellationName'] = $data['business_name'];
            $data['carrierName'] = "Liability Policy issued by " . $data['liability_carrier'];
            $data['policyId'] = "Liability Policy #:" . $data['liability_policy_id'];
            $data['coverageTitle'] = "GENERAL LIABILITY";
            if ($data['propertyCoverageSelect'] == "yes") {
                $data['multiplePolicy'] = "yes";
                $data['propCarrierName'] = "Property Policy issued by " . $data['property_carrier'];
                $data['propPolicyId'] = "Property Policy #:" . $data['property_policy_id'];
                $data['coverageTitle'] = $data['coverageTitle'].",PROPERTY";
            }
            if ($data['groupProfessionalLiabilitySelect'] == "yes") {
                $data['multiplePolicy'] = "yes";
                $data['groupCarrierName'] = "Group Policy issued by " . $data['group_carrier'];
                $data['groupPolicyId'] = "Group Policy #:" . $data['group_policy_id'];
                $data['coverageTitle'] = $data['coverageTitle'].",GROUP PROFESSIONAL LIABILITY";
            }
        } else if ($data['product'] == "Group Professional Liability") {
            $data['cancellationName'] = $data['business_name'];
            $data['carrierName'] = "Policy issued by " . $data['group_carrier'];
            $data['policyId'] = "Policy #:" . $data['group_policy_id'];
            $data['coverageTitle'] = "GROUP PROFESSIONAL LIABILITY";
        } else if($data['product'] == "Individual Professional Liability"){
            $data['cancellationName'] = $data['firstname']."  ".$data['lastname'];
            $data['carrierName'] = "Policy issued by " . $data['carrier'];
            $data['policyId'] = "Policy #:" . $data['policy_id'];
            $data['coverageTitle'] = "INDIVIDUAL PROFESSIONAL LIABILITY";
        }else if($data['product'] == "Emergency First Response"){
            $data['cancellationName'] = $data['firstname']."  ".$data['lastname'];
            $data['carrierName'] = "Policy issued by " . $data['carrier'];
            $data['policyId'] = "Policy #:" . $data['policy_id'];
            $data['coverageTitle'] = "EMERGENCY FIRST RESPONSE";
        }
        $data['confirmReinstatePolicy'] = '';
        if ($data['reasonforCsrCancellation'] == 'nonPaymentOfPremium') {
            $this->logger->info("Processing nonPaymentOfPremium");
            $temp = date_create();
            $data['ReinstatePolicyPeriod'] = $temp->add(new DateInterval("P10D"))->format("Y-m-d");
        } else if ($data['reasonforCsrCancellation'] == 'padiMembershipNotCurrent') {
            $this->logger->info("Processing padiMembershipNotCurrent");
            $temp = date_create();
            $data['ReinstatePolicyPeriod'] = $temp->add(new DateInterval("P45D"))->format("Y-m-d");
        }
        if (isset($data['state'])) {
            $data['state_in_short'] = $this->getStateInShort($data['state'], $persistenceService);
        }
        $orgUuid = isset($data['orgUuid']) ? $data['orgUuid'] : (isset($data['orgId']) ? $data['orgId'] : AuthContext::get(AuthConstants::ORG_UUID));
        $dest = ArtifactUtils::getDocumentFilePath($this->destination, $data['fileId'], array('orgUuid' => $orgUuid));
        $this->logger->info('the  destination consists of : ' . print_r($dest, true));
        
        if(isset($data['previous_policy_data'])){
            $previous_policy = is_string($data['previous_policy_data']) ? json_decode($data['previous_policy_data'],true) : $data['previous_policy_data'];
            if(sizeof($previous_policy) > 0){
                $workflowInstUuid = $this->getWorkflowInstanceByFileId($data['fileId'], 'In Progress');
                if (count($workflowInstUuid) > 0 && (isset($workflowInstUuid[0]['process_instance_id']))) {
                    $dest['absolutePath'] .= $workflowInstUuid[0]['process_instance_id'] . "/";
                    $dest['relativePath'] .= $workflowInstUuid[0]['process_instance_id'] . "/";
                    FileUtils::createDirectory($dest['absolutePath']);
                }
            }
        }
        $this->logger->info("execute generate documents");
        $cancelDoc = $this->generateDocuments($data, $dest, $options, 'template', 'header', 'footer');
        $data['documents'] = isset($data['documents']) ? (is_string($data['documents']) ?  json_decode($data['documents'],true) : $data['documents']) : array();
        if (isset($data['documents']['cancel_doc'])) {
            $cancelDocList = is_string($data['documents']['cancel_doc']) ? json_decode($data['documents']['cancel_doc'], true) : $data['documents']['cancel_doc'];
            if(empty($cancelDocList)){
                $data['documents']['cancel_doc'] = array(0 => $data['documents']['cancel_doc']);
            }
        }else{
            $data['documents']['cancel_doc'] = array();
        }
        array_push($data['documents']['cancel_doc'], $cancelDoc);
        unset($data['carrierName']);
        unset($data['policyId']);
        unset($data['propPolicyId']);
        unset($data['groupPolicyId']);
        unset($data['multiplePolicy']);
        unset($data['coverageTitle']);
        $this->saveFile($data, $data['fileId']);
        return $data;
    }
}