<?php

use Oxzion\AppDelegate\AbstractDocumentAppDelegate;
use Oxzion\Db\Persistence\Persistence;
use Oxzion\Utils\ArtifactUtils;
use Oxzion\AppDelegate\FileTrait;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Oxzion\Model\Field;

class OnTracPDFGeneration extends AbstractDocumentAppDelegate
{
    use FileTrait;
    
    public function __construct()
    {
        parent::__construct();
    }

    public function execute(array $data, Persistence $persistenceService)
    {
        $fieldMapping = include(__DIR__ . "/FieldMappingOnTarcPDF.php");
        $onTarcPDFTemplate = "OnTracRSPComplianceChecklistTemplate";
        $fileUUID = isset($data['uuid']) ? $data['uuid'] : $data['fileId'];
        $orgUuid = isset($data['orgId']) ? $data['orgId'] : AuthContext::get(AuthConstants::ORG_UUID);
        $fileDestination =  ArtifactUtils::getDocumentFilePath($this->destination, $fileUUID, array('orgUuid' => $orgUuid));
        $pdfData = array();
        $generatedONTarcPDF = array();
        $documentpdf = array();
        $documentDestination = $fileDestination['absolutePath'].$onTarcPDFTemplate .".pdf";
        $data['pdfDate'] = explode('T',$data['effectiveDate'])[0];
        $pdfData['Date'] = date("m/d/Y", strtotime($data['pdfDate']));
        $this->logger->info("field mapping data : ". print_r($data['autoLiability'], true));
        if(isset($fieldMapping["checkbox"])){
            foreach ($fieldMapping["checkbox"] as $formField => $fieldProps) {
                $fieldNamePDF = $fieldProps['fieldname'];
                $fieldOptions = $fieldProps["options"];
                $this->logger->info("filed mapping data : ". print_r($fieldNamePDF, true));
                $this->logger->info("filed mapping data : ". print_r($fieldOptions[$formField], true));
                if (isset($data[$formField]) && $data[$formField] == 'true') {
                    $pdfData[$fieldNamePDF] = $fieldOptions[$formField];
                    $this->logger->info("checkbox mapping data formfield : ". print_r($fieldOptions[$formField], true));
                    $this->logger->info("checkbox mapping data pdfdata : ". print_r($pdfData[$fieldNamePDF], true));
                }
            }
        }
        $pdfData = array_filter($pdfData);
        $pdfData['appId'] = $data['appId'];
        $this->documentBuilder->fillPDFForm(
            $onTarcPDFTemplate.".pdf",
            $pdfData,
            $documentDestination
        );
        $documentpdf = $fileDestination['relativePath'] . $onTarcPDFTemplate.".pdf";
        array_push(
            $generatedONTarcPDF,
            array(
                "name" => $onTarcPDFTemplate.".pdf",
                "fullPath" => $documentDestination,
                "file" => $documentpdf,
                "originalName" => $onTarcPDFTemplate.".pdf",
                "type" => "file/pdf",
            )
        );
        $data['attachments'] = $generatedONTarcPDF;
        $this->logger->info("PDF MAPPED DOCUMENT : ". print_r($data['attachments'], true));
        $this->saveFile($data, $fileUUID);
        return $data;
    }
}