<?php
namespace Oxzion\Document;

use Oxzion\Service\TemplateService;
use Oxzion\Utils\ArtifactUtils;
use Oxzion\Document\DocumentGenerator;
use Oxzion\Utils\FileUtils;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Logger;
class DocumentBuilder {
    private $templateService;
    private $documentGenerator;
    private $config;
    private $logger;
    public function __construct($config, TemplateService $templateService, DocumentGenerator $documentGenerator){
        $this->config = $config;
        $this->templateService = $templateService;
        $this->documentGenerator = $documentGenerator;
        $this->logger = Logger::getLogger(__CLASS__);
    }

    /**
    *  $template - string - template name
    *  $data - array - the context data to process the template
    *               orgUuid - if present will be used else current logged in org uuid 
    *                         will be used 
    *  $destination - string - the file path of the destination file
    *  $options - array 
    *               header - name of the header file (path will be discovered using conventions)
    *               footer - name of the footer file (path will be discovered using conventions)
    */
    public function generateDocument($template, $data, $destination, $options = null){
        $this->logger->info("Template - $template");
        $content = $this->templateService->getContent($template, $data);
        $this->logger->info("Content  - $content");
        $append = array();
        $prepend = array();
        $header = null;
        $footer = null;
        $generateOptions = array();
        if($options && isset($options['header'])){
            $header = $options['header'];
            $header = ArtifactUtils::getTemplatePath($this->config, $header, $data)."/".$header;
        }
        if($options && isset($options['footer'])){
            $footer = $options['footer'] ;
            $footer = ArtifactUtils::getTemplatePath($this->config, $footer, $data)."/".$footer;
        }

        if($options && isset($options['append'])){
            $append = $options['append'];
        }

        if($options && isset($options['prepend'])){
            $prepend = $options['prepend'];
        }

        if($options && isset($options['generateOptions'])){
            $generateOptions = $options['generateOptions'];
        }
       return $this->documentGenerator->generatePdfDocumentFromHtml($content, $destination, $header, $footer,$data,$append,$prepend,$generateOptions);
    }

    public function copyTemplateToDestination($template,$destination){
        $sourcePath = $this->config['TEMPLATE_FOLDER'].AuthContext::get(AuthConstants::ORG_UUID).'/'.$template;
        $this->logger->info("copyTemplateToDestination".$sourcePath);
        $destinationPath = $this->config['APP_DOCUMENT_FOLDER'].$destination;
        FileUtils::copy($sourcePath,$template,$destinationPath);
        return;
    }
}