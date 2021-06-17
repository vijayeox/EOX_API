<?php
namespace Oxzion\Utils;

use SoapClient;
use SoapHeader;
use Webmozart\Assert\Assert;
use Oxzion\ServiceException;
use Oxzion\OxServiceException;

class SOAPUtils
{
    private $xml;
    private $client;
    private $options;

    public function __construct($wsdl, array $options = array())
    {
        $this->setWsdl($wsdl);

        $defaultOptions = array(
            'defaultNamespace' => 'http://www.w3.org/2001/XMLSchema'
        );
        $this->options = array_merge($defaultOptions, array_intersect_key($options, $defaultOptions));
        $this->options['prefix'] = array_flip($this->xml->getDocNamespaces())[$this->options['defaultNamespace']];
    }

    private function setWsdl($wsdl)
    {
        if (substr($wsdl, 0, 4) === 'http') {
            $this->client = new SoapClient($wsdl);
        }
        if (substr($wsdl, 0, 4) === 'http' || is_file($wsdl)) {
            try {
                $temp_wsdl = $wsdl;
                ob_start();
                $wsdl = file_get_contents($wsdl);
            } catch (\Exception $e) {}
            if (!$wsdl) {
                ob_clean();
                throw new ServiceException('Cannot fetch the service from '.$temp_wsdl, 'soap.call.errors', OxServiceException::ERR_CODE_INTERNAL_SERVER_ERROR);
            }
        }
        $this->xml = simplexml_load_string($wsdl);
    }

    public function setHeader(string $namespace, string $name, array $data)
    {
        $header = new SoapHeader($namespace, $name, $data);
        $this->client->__setSoapHeaders($header);
    }

    public function makeCall(string $function, array &$data = [], bool $clean = true)
    {
        if ($errors = $this->getValidData($function, $data)) {
            throw new ServiceException(json_encode($errors), 'validation.errors', OxServiceException::ERR_CODE_NOT_ACCEPTABLE);
        }
        try {
            $response = $this->client->{$function}($data);
        } catch (\Exception $e) {
            throw new ServiceException($e->getMessage(), 'soap.call.errors', OxServiceException::ERR_CODE_INTERNAL_SERVER_ERROR);
        }
        if ($clean) {
            return $this->cleanResponse($response);
        }
        return $response;
    }

    private function cleanResponse($response)
    {
        if (is_object($response)) {
            return json_decode(json_encode($response), true);
        }
        return $response;
    }

    public function isValidFunction(string $function)
    {
        return in_array($function, $this->getFunctions());
    }

    public function getFunctions()
    {
        $functions = array();
        $functions = $this->client->__getFunctions();
        foreach ($functions as $function) {
            preg_match_all('/^(\w+) (\w+)\((.*)\)$/m', $function, $parts);
            $functions[$parts[2][0]] = $parts[3][0];
        }
        return array_keys($functions);
    }

    public function getValidData($functionStruct, array &$data, array $errors = [])
    {
        if (is_string($functionStruct)) {
            $functionStruct = $this->getFunctionStruct($functionStruct);
        }
        $data = array_intersect_key($data, $functionStruct);
        foreach ($functionStruct as $key => $value) {
            if ($value['required'] && !isset($data[$key])) {
                $errors[$key] = 'Required Field';
            } elseif (isset($data[$key]) && !$value['nillable'] && !$data[$key]) {
                $errors[$key] = 'Value cannot be Nill';
            }
            if (isset($data[$key]) && isset($value['type'])) {
                switch ($value['type']) {
                    case 'string':
                        break;
                    case 'int':
                        if (!is_int($data[$key])) {
                            if (preg_match('/^[-]?[\d]*$/m', $data[$key])) {
                                $data[$key] = (int) $data[$key];
                            } else {
                                $errors[$key] = 'Integer value Expected';
                            }
                        }
                        break;
                    case 'boolean':
                        if (!is_bool($data[$key])) {
                            if (preg_match('/^(true|false)$/m', strtolower($data[$key]))) {
                                $data[$key] = (strtolower($data[$key]) == 'true') ? true : false;
                            } else {
                                $errors[$key] = 'Boolean value Expected';
                            }
                        }
                        break;
                    case 'enumeration':
                        if (!in_array($data[$key], $value['enumeration'])) {
                            $errors[$key] = 'Invalid value selected';
                        }
                        break;
                    case 'date':
                        if ($data[$key]) {
                            if ((strlen($data[$key]) != 10) || checkdate(date('m', strtotime($data[$key])), date('d', strtotime($data[$key])), date('Y', strtotime($data[$key]))))
                                $errors[$key] = 'Invalid Date';
                        }
                        break;
                    case 'pattern':
                        if (!preg_match('/'.$value['pattern'].'/m', $data[$key])) {
                            $errors[$key] = 'Incorrect format';
                        }
                        break;
                    default:
                        throw new ServiceException("Unable to validate ".json_encode($value)." = ".$data[$key], 'validation.errors', OxServiceException::ERR_CODE_NOT_FOUND);
                        break;
                }
            }
            if (isset($functionStruct[$key]['children']) && $data[$key]) {
                $errors = array_merge($errors, $this->getValidData($functionStruct[$key]['children'], $data[$key], $errors));
            }
        }
        return $errors;
    }

    public function getFunctionStruct(string $function)
    {
        if (!$this->isValidFunction($function)) {
            throw new ServiceException("Requested function not found", 'function.not.found', OxServiceException::ERR_CODE_NOT_FOUND);
        }

        $targetPath = '/wsdl:definitions/wsdl:types/'.$this->options['prefix'].':schema';
        if ($this->xml->attributes()['targetNamespace']) {
            $targetPath .= '[@targetNamespace="'.$this->xml->attributes()['targetNamespace'].'"]';
        }
        $schemas = $this->xml->xpath($targetPath);

        if (count($schemas) != 1) {
            throw new ServiceException("Schema not found", 'schema.not.found', OxServiceException::ERR_CODE_NOT_FOUND);
        }

        $parameters = array();
        $schema = current($schemas);
        foreach ($schema->children(current($schema->getNamespaces())) as $methodNode) {
            if ($methodNode->attributes()['name'] == $function) {
                foreach ($methodNode->children(current($methodNode->getNamespaces())) as $type) {
                    $parameters = array_merge($parameters, $this->processElements($type));
                }
                break;
            }
        }
        return $parameters;
    }

    private function processElements($type)
    {
        $parameters = array();
        foreach ($type->children(current($type->getNamespaces())) as $value) {
            foreach ($value->children(current($value->getNamespaces())) as $element) {
                $parameters[$element->attributes()['name']->__toString()] = array(
                    'name' => $element->attributes()['name']->__toString(),
                    'required' => ((int) $element->attributes()['minOccurs']) ? true : false,
                    'nillable' => ((bool) $element->attributes()['nillable']) ? true : false
                ) + $this->processElementType($element);
            }
        }
        return $parameters;
    }

    private function processElementType($element)
    {
        $type = explode(':', $element->attributes()['type']);
        if ($type[1] && $type[0] === $this->options['prefix']) {
            return array('type' => $type[1]);
        } elseif (!$type[1]) {
            return array('type' => $type[0]);
        }

        $elementTypes = array('simpleType', 'complexType');
        $targetPath = '/wsdl:definitions/wsdl:types/'.$this->options['prefix'].':schema/'.$this->options['prefix'].':';
        foreach ($elementTypes as $elementType) {
            $elementTypeDom = $this->xml->xpath($targetPath.$elementType.'[@name="'.$type[1].'"]');
            if (count($elementTypeDom) > 1) {
                throw new ServiceException("More than one element found", 'schema.error', OxServiceException::ERR_CODE_CONFLICT);
            } elseif (count($elementTypeDom) == 0) {
                continue;
            }

            $elementTypeDom = current($elementTypeDom);
            switch ($elementType) {
                case 'complexType':
                    return array('children' => $this->processElements($elementTypeDom));
                    break;
                case 'simpleType':
                    return $this->processSimpleType($elementTypeDom);
                    break;
                default:
                    throw new ServiceException("Unknown element type", 'schema.error', OxServiceException::ERR_CODE_UNPROCESSABLE_ENTITY);
                    break;
            }
        }
    }

    private function processSimpleType($type)
    {
        $parameters = array();
        foreach ($type->children(current($type->getNamespaces())) as $value) {
            foreach ($value->children(current($value->getNamespaces())) as $element) {
                switch ($element->getName()) {
                    case 'enumeration':
                        $parameters[] = $element->attributes()['value']->__toString();
                        break;
                    case 'pattern':
                        $parameters = $element->attributes()['value']->__toString();
                        break;
                    default:
                        throw new ServiceException("Unknown simple element type", 'schema.error', OxServiceException::ERR_CODE_UNPROCESSABLE_ENTITY);
                        break;
                }
            }
        }
        return array('type' => $element->getName(), $element->getName() => $parameters);
    }
}
