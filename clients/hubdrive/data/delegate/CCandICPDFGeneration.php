<?php

use Oxzion\AppDelegate\AbstractDocumentAppDelegate;
use Oxzion\AppDelegate\AccountTrait;
use Oxzion\Db\Persistence\Persistence;
use Oxzion\Utils\ArtifactUtils;
use Oxzion\AppDelegate\FileTrait;
use Oxzion\AppDelegate\CommentTrait;
use Oxzion\AppDelegate\EsignTrait;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Oxzion\Model\Field;

class CCandICPDFGeneration extends AbstractDocumentAppDelegate
{
    use FileTrait;
    use CommentTrait;
    use EsignTrait;
    use AccountTrait;

    public function __construct()
    {
        parent::__construct();
    }
    
    public function execute(array $data, Persistence $persistenceService)
    {
        $fieldTypeMappingPDF = include(__DIR__ . "/FieldMappingCCPDF.php");
        $this->logger->info("PDF MAPPING DATA : ". print_r($data, true));
        $PDFTemplateList = array("IC");
        $fileUUID = isset($data['uuid']) ? $data['uuid'] : $data['fileId'];
        $accountId = $this->getAccountByName($data['accountName']) ? $this->getAccountByName($data['accountName']) : AuthContext::get(AuthConstants::ACCOUNT_UUID);
        $this->logger->info("ACCOUT IS ____" .$accountId);
        $fileDestination =  ArtifactUtils::getDocumentFilePath($this->destination, $fileUUID, array('accountId' => $accountId));
        $this->logger->info("GenerateFilledPDF Dest" . json_encode($fileDestination, JSON_UNESCAPED_SLASHES));
        $generatedPDFList = array();
        foreach ($PDFTemplateList as $selectedTemplate) {
            $this->logger->info("selected template",$selectedTemplate);
            $pdfData = array();
            $documentDestination = $fileDestination['absolutePath'].$selectedTemplate .".pdf";
                foreach ($fieldTypeMappingPDF[$selectedTemplate]["text"] as  $formField => $pdfField) {
                isset($data[$formField]) ? $pdfData[$pdfField] = $data[$formField] : null;
                }
                if(isset($data['motorCarrierDetailsDate'])){
                $data['pdfDateIC'] = explode('T',$data['motorCarrierDetailsDate'])[0];
                $pdfData['contractDate'] = date("m/d/Y", strtotime($data['pdfDateIC']));
                $pdfData['dayMonth'] = date("m/d", strtotime($data['pdfDateIC']));
                $pdfData['year'] = date("y", strtotime($data['pdfDateIC']));
                }
                $pdfData['accountId'] = $accountId;
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
                case "IC":  
                    $field = array(
                        array(
                        "name"=>"esignICForm",
                        "height"=>80,
                        "width"=>20,
                        "x"=>60,
                        "y"=>24,
                        "pageNumber"=>13,
                        ),
                        array(
                            "name"=>"esignICForm",
                            "height"=>80,
                            "width"=>15,
                            "x"=>77,
                            "y"=>31,
                            "pageNumber"=>6,
                        ),
                        array(
                            "name"=>"esignICForm",
                            "height"=>80,
                            "width"=>15,
                            "x"=>77,
                            "y"=>38,
                            "pageNumber"=>6,
                        ),
                         array(
                            "name"=>"esignICForm",
                            "height"=>80,
                            "width"=>15,
                            "x"=>77,
                            "y"=>45,
                            "pageNumber"=>6,
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