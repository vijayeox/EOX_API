<?php
namespace Oxzion\Transformer;

use Logger;
use Exception;

class AttributeTransformer
{
    private $logger;
    private $fileExt = ".php";

    public function __construct($config)
    {
        $this->config = $config;
        $this->logger = Logger::getLogger(__CLASS__);
        $this->transformerDir = $this->config['TRANSFORMER_FOLDER'];
        if (!is_dir($this->transformerDir)) {
            mkdir($this->transformerDir, 0777, true);
        }
    }

    public function processMethod($appId, $scriptFile, &$field, &$data){
        $this->loadScriptFile($appId, $scriptFile, $field, $data);  
    }

    public function processScript($script, $field){
        // evaluate the script
        $script = str_replace('$field', $field, $script);
        return eval("return" .$script. ";");

    }
    
    private function loadScriptFile($appId, $scriptFile, &$field, &$data)
    {
        $file = $scriptFile . $this->fileExt;
        $path = $this->transformerDir . $appId . "/" . $file;
        $this->logger->info("PATH------>".print_r($path,true));   
        if ((file_exists($path))) {
            // include $path;
            $this->logger->info("Loading Script File");
            require $path;
            $this->logger->info("DATA at processed script file------>".print_r($data,true));  
        } else {
            throw new EntityNotFoundException("Script File not found");
        }
    }

}