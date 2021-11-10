<?php

use Oxzion\AppDelegate\AbstractDocumentAppDelegate;
use Oxzion\Db\Persistence\Persistence;
use Oxzion\Utils\ArtifactUtils;
use Oxzion\AppDelegate\FileTrait;
use Oxzion\AppDelegate\CommentTrait;
use Oxzion\AppDelegate\EsignTrait;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Oxzion\Model\Field;

class CCPDFGeneration extends AbstractDocumentAppDelegate
{
    use FileTrait;
    use CommentTrait;
    use EsignTrait;

    public function __construct()
    {
        parent::__construct();
    }
    
    public function execute(array $data, Persistence $persistenceService)
    {
        $fieldTypeMappingPDF = include(__DIR__ . "/FieldMappingCCPDF.php");
        $this->logger->info("PDF MAPPING DATA : ". print_r($data, true));
        $PDFTemplateList = array("CC");
        $fileUUID = isset($data['uuid']) ? $data['uuid'] : $data['fileId'];
        $orgUuid = isset($data['orgId']) ? $data['orgId'] : AuthContext::get(AuthConstants::ORG_UUID);
        $fileDestination =  ArtifactUtils::getDocumentFilePath($this->destination, $fileUUID, array('orgUuid' => $orgUuid));
        $this->logger->info("GenerateFilledPDF Dest" . json_encode($fileDestination, JSON_UNESCAPED_SLASHES));
        $generatedPDFList = array();
        foreach ($PDFTemplateList as $selectedTemplate) {
            $this->logger->info("selected template",$selectedTemplate);
            $pdfData = array();
            $documentDestination = $fileDestination['absolutePath'].$selectedTemplate .".pdf";
                foreach ($fieldTypeMappingPDF[$selectedTemplate]["text"] as  $formField => $pdfField) {
                isset($data[$formField]) ? $pdfData[$pdfField] = $data[$formField] : null;
                }
                if(isset($data['freightBrokerDetailsDate'])){
                $data['pdfDateCC'] = explode('T',$data['freightBrokerDetailsDate'])[0];
                $pdfData['Date'] = date("m/d/Y", strtotime($data['pdfDateCC']));
                }
                $pdfData = array_filter($pdfData);
                $pdfData['appId'] = $data['appId'];
                $this->logger->info("PDF Filling Data \n" . json_encode($pdfData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->documentBuilder->fillPDFForm(
                $selectedTemplate.".pdf",
                $pdfData,
                $documentDestination
            );
            $documentpdf = $fileDestination['relativePath'] . $selectedTemplate.".pdf";
            switch ($selectedTemplate) {
                case "CC":  
                    $field = array(
                        array(
                        "name"=>"esignCCForm",
                        "height"=>80,
                        "width"=>20,
                        "x"=>62,
                        "y"=>48,
                        "pageNumber"=>8,
                     )
                        );
                    break;
                default:
                  echo "Invalid document name!";
              }
        $signers = array(
                "name"=>$selectedTemplate,
                "message"=>"Please sign",
                "signers"=>[['participant' => ["email"=>$data['email'], 'name' => $data['firstname']],
                            "fields"=> $field]]);
        $docId = $this->setupDocument($fileUUID."_".$selectedTemplate,$documentDestination,$signers);
        $signingLink = $this->getDocumentSigningLink($docId);
            array_push(
                $generatedPDFList,
                array(
                    "fullPath" => $documentDestination,
                    "file" => $documentpdf,
                    "originalName" => $selectedTemplate.".pdf",
                    "type" => "file/pdf",
                    "docId"=>$docId,
                    "signingLink"=>$signingLink,
                    "status"=>"UNSIGNED"
                )
            );  
    }
    $data['attachments'] = $generatedPDFList;
    $this->logger->info("PDF MAPPING : ". print_r($data['attachments'], true));
    $this->logger->info("Completed signature document with data- " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $this->saveFile($data, $fileUUID);
    return $data;
}
}