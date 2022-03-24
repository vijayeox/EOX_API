<?php
namespace Oxzion\Transformer;

use Exception;
use Logger;
use Oxzion\Service\AbstractService;
use Symfony\Component\Yaml\Yaml;
use Oxzion\EntityNotFoundException;

class JsonTransformerService extends AbstractService
{
    private $fileExt = ".yml";
    protected $logger;
    private $attributeTransformer;
    public function __construct($config, AttributeTransformer $attributeTransformer)
    {
        $this->config = $config;
        $this->logger = Logger::getLogger(__CLASS__);
        $this->attributeTransformer = $attributeTransformer;
        $this->transformerDir = $this->config['TRANSFORMER_FOLDER'];
        if (!is_dir($this->transformerDir)) {
            mkdir($this->transformerDir, 0777, true);
        }
    }

    public function transform($appId, $directive, &$dataArray = array(), $returnNewArray = false)
    {
        $this->logger->info("TRANSFORM ---".print_r($dataArray,true));          
        try {
            $returnArray = $returnNewArray ? array() : $dataArray;
            $result = $this->directiveFile($appId, $directive);
            $newArray['appId'] = $appId;  
            $this->logger->info("TRANSFORM RESULT---".print_r($result,true));          
            if ($result) {
                // Process the data
                foreach ($result['transform'] as $key => $value) {
                    foreach ($value['src'] as $key => $srcValue) {
                        if (!empty($returnArray)) {
                            $this->transformData($appId, $srcValue, $value, $returnArray);
                        }else{
                            if (isset($srcValue['path'])) {
                                $newArray[$srcValue['path']] = $dataArray[$srcValue['path']];
                            }
                            $this->transformData($appId, $srcValue, $value, $newArray);
                        }
                    }
                }
                $returnArray = empty($returnArray) ? $newArray :  $returnArray;
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e);
            throw $e;
        }
        return $returnArray;
    }

    private function directiveFile($appId, $className)
    {
        $file = $className . $this->fileExt;
        $path = $this->transformerDir . $appId . "/" . $file;
        if ((file_exists($path))) {
            return Yaml::parse(file_get_contents($path));
        } else {
            throw new EntityNotFoundException("Transformer not found");
        }
    }

    private function transformData($appId, $srcValue, $value, &$returnArray)
    {
        $field = (isset($srcValue['path']) && isset($returnArray[$srcValue['path']])) ? $returnArray[$srcValue['path']] : '';
        if(isset($srcValue['emptyOnNull']) && $srcValue['emptyOnNull'] == true && strtoupper($field) == 'NULL'){
            $returnArray[$value['target']] = ''; 
        }elseif((isset($srcValue['excludeOnNull']) && $srcValue['excludeOnNull'] == true && strtoupper($field) == 'NULL') || (isset($srcValue['remove']) && $srcValue['remove'] == true)){
            unset($returnArray[$value['target']]);
        }elseif(isset($srcValue['dataType']) && strtolower($srcValue['dataType']) == 'date'){
                $returnArray[$value['target']] = date($srcValue['format'], strtotime($field));
        }elseif(isset($srcValue['script'])){
            $returnArray[$value['target']] = $this->attributeTransformer->processScript($srcValue['script'], $field);
        }elseif(isset($srcValue['method'])){
            $this->attributeTransformer->processMethod($appId,$srcValue['method'], $field, $returnArray);
            $returnArray[$value['target']] = $field;  
        }else{
            $returnArray[$value['target']] = $field;
        }
    }
}
