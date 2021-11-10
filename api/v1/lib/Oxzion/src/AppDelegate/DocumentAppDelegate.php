<?php
namespace Oxzion\AppDelegate;

use Oxzion\Document\DocumentBuilder;

interface DocumentAppDelegate extends AppDelegate
{
    public function setDocumentBuilder(DocumentBuilder $documentBuilder);
    public function setDocumentPath($destination);
    public function setBaseUrl($url);
}
