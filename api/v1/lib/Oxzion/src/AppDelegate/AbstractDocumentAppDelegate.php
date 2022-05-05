<?php

namespace Oxzion\AppDelegate;

use Logger;
use Oxzion\Document\DocumentBuilder;

abstract class AbstractDocumentAppDelegate implements DocumentAppDelegate
{
    use UserContextTrait;
    protected $logger;
    protected $documentBuilder;
    protected $baseUrl;
    protected $destination;

    public function __construct()
    {
        $this->logger = Logger::getLogger(__CLASS__);
    }

    public function setDocumentBuilder(DocumentBuilder $documentBuilder)
    {
        $this->documentBuilder = $documentBuilder;
    }
    
    public function setDocumentPath($destination)
    {
        $this->destination = $destination;
    }
    
    public function setBaseUrl($url)
    {
        $this->baseUrl = $url;
    }
}
