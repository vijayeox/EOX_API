<?php
namespace Oxzion\Utils;

use Oxzion\ServiceException;
use Oxzion\OxServiceException;

class SOAPUtils extends \SoapClient
{
    private $xml;
    private $options;

    public function __construct(String $wsdl, Array $options = [])
    {
        parent::__construct($wsdl, $options);
        $this->processXml($wsdl, $options);
    }

    public function setHeader(string $namespace, string $name, $data, bool $mustUnderstand = false)
    {
        $header = new \SoapHeader($namespace, $name, $data, $mustUnderstand);
        $this->__setSoapHeaders($header);
    }
    public function setWsseHeader($username, $password) {
        $domRequest = new \DOMDocument();
        $security = $domRequest->createElementNS('http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd', 'wsse:Security');
        $usernameToken = $domRequest->createElement('wsse:UsernameToken');
        $username = $domRequest->createElement('wsse:Username', $username);
        $password = $domRequest->createElement('wsse:Password', $password);
        $usernameToken->appendChild($username);
        $usernameToken->appendChild($password);
        $security->appendChild($usernameToken);
        $domRequest->appendChild($security);

        $soapVar = new \SoapVar($domRequest->saveHTML(), XSD_ANYXML);
        $this->setHeader('http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd', 'Security', $soapVar, true);
    }

    public function makeCall(string $function, array $data = [], bool $clean = true)
    {
        if ($errors = $this->getValidData($function, $data)) {
            throw new ServiceException(json_encode($errors), 'validation.errors', OxServiceException::ERR_CODE_NOT_ACCEPTABLE);
        }
        try {
            $response = $this->{$function}($data);
        } catch (\Exception $e) {
            throw new ServiceException($e->getMessage(), 'soap.call.errors', OxServiceException::ERR_CODE_INTERNAL_SERVER_ERROR);
        }
        if ($clean) {
            return $this->cleanResponse($response, $function);
        }
        return $response;
    }

    public function getFunctions()
    {
        $functions = [];
        foreach ($this->__getFunctions() as $function) {
            preg_match_all('/^(\w+) (\w+)\((\w+) (\$\w+)\)$/m', $function, $parts);
            $functions[] = $parts[2][0];
            $functions[] = $parts[3][0];
        }
        return array_unique($functions);
    }

    public function getFunctionStruct(String $function)
    {
        if (!$this->isValidFunction($function)) {
            throw new ServiceException("Requested function not found", 'function.not.found', OxServiceException::ERR_CODE_NOT_FOUND);
        }

        $parameters = array();
        $schema = $this->xml->children($this->options['wsdlNamespace'])->children($this->options['xmlNamespace'])->schema;
        foreach ($schema->element as $methodNode) {
        if ($methodNode->attributes()['name'] == $function) {
                foreach ($methodNode->children(current($methodNode->getNamespaces())) as $type) {
                    $parameters = array_merge($parameters, $this->processElements($type));
                }
                break;
            }
        }
        return $parameters;
    }

    public function getValidData($functionStruct, array &$data, array $errors = [])
    {
        if (is_string($functionStruct)) {
            $functionStruct = $this->getFunctionStruct($functionStruct);
        }
        $data = array_intersect_key($data, $functionStruct);
        foreach ($functionStruct as $key => $value) {
            if (!isset($data[$key])) {
                if (!$value['required']) continue;
                $errors[$key] = 'Required Field';
            } elseif (isset($value['nillable']) && !$value['nillable'] && !$data[$key]) {
                $errors[$key] = 'Value cannot be Nill';
            } else {
                if (!empty($value['type'])) {
                    switch ($value['type']) {
                        case 'enumeration':
                            $tempData = ['data' => $data[$key], 'options' => $value['enumeration']];
                            $valid = ValidationUtils::isValid('inArray', $tempData, true);
                            break;
                        case 'pattern':
                            $tempData = ['data' => $data[$key], 'regex' => '/'.$value['pattern'].'/m'];
                            $valid = ValidationUtils::isValid('regex', $tempData, true);
                            break;
                        default:
                            $valid = ValidationUtils::isValid($value['type'], $data[$key], true);
                            break;
                    }
                    if (isset($tempData)) unset($tempData);
                    if ($valid !== true) $errors[$key] = $valid;
                } elseif (isset($value['children']) && $data[$key]) {
                    if (!is_array($data[$key]) && ValidationUtils::isValid('json', $data[$key])) {
                        $data[$key] = json_decode($data[$key], true);
                    }
                    if (is_array($data[$key])) {
                        if (is_int(key($data[$key]))) {
                            foreach ($data[$key] as $ckey => &$cvalue) {
                                $error = $this->getValidData($value['children'], $cvalue);
                                if ($error) {
                                    $errors[$key][$ckey] = $error;
                                }
                            }
                        } else {
                            $error = $this->getValidData($value['children'], $data[$key]);
                            if ($error) {
                                $errors[$key] = $error;
                            }
                        }
                    }
                } else {
                    $errors[$key] = 'Could not process '.$key;
                }
            }
        }
        return $errors;
    }

    private function isValidFunction(string $function)
    {
        return in_array($function, $this->getFunctions());
    }

    private function processXml(String $wsdl, Array $options = [])
    {
            try {
                ob_start();
                if (is_file($wsdl) || ValidationUtils::isValid('url', $wsdl)) {
                    $this->xml = simplexml_load_file($wsdl, "SimpleXMLElement", LIBXML_PARSEHUGE);
                } elseif (ValidationUtils::isValid('xml', $wsdl)) {
                    $this->xml = simplexml_load_string($wsdl, "SimpleXMLElement", LIBXML_PARSEHUGE);
                }
            } catch (\Exception $e) {
                throw new ServiceException($e->getMessage(), 'soap.call.errors', OxServiceException::ERR_CODE_INTERNAL_SERVER_ERROR);
            } finally {
                ob_end_clean();
                if (!$this->xml instanceof \SimpleXMLElement) {
                    throw new ServiceException('Cannot fetch the service from '.$wsdl, 'soap.call.errors', OxServiceException::ERR_CODE_INTERNAL_SERVER_ERROR);
            }
        }

        $defaultOptions = array(
            'prefix' => '',
            'xmlNamespace' => 'http://www.w3.org/2001/XMLSchema',
            'wsdlNamespace' => 'http://schemas.xmlsoap.org/wsdl/'
        );
        $this->options = array_merge($defaultOptions, array_intersect_key($options, $defaultOptions));
        if (!$this->options['prefix']) {
            $this->options['prefix'] = array_search($this->options['xmlNamespace'], $this->xml->getDocNamespaces());
        }
    }

    private function processElements($type)
    {
        $parameters = array();
        foreach ($type->children(current($type->getNamespaces())) as $value) {
            foreach ($value->children(current($value->getNamespaces())) as $element) {
                $elementName = $element->attributes()['name']->__toString();
                $parameters[$elementName] = [
                    'name' => $elementName
                ];
                $parameter = &$parameters[$elementName];
                foreach ($element->attributes() as $key => $value) {
                    switch ($key) {
                        case 'type':
                            $parameter += is_array($type = $this->processElementType($element)) ? $type : ['type' => $element->attributes()['type']];
                            break;
                        case 'minOccurs':
                            $parameter['required'] = ((int) $element->attributes()['minOccurs']) ? true : false;
                            break;
                        case 'nillable':
                            $parameter['nillable'] = ((int) $element->attributes()['nillable']) ? true : false;
                            break;
                        default:
                            break;
                    }
                }
            }
        }
        return $parameters;
    }

    private function processElementType($element)
    {
        list($ns, $name) = explode(':', $element->attributes()['type']);
        if ($name && $ns === array_search($this->options['xmlNamespace'], $element->getDocNamespaces())) {
            return array('type' => $name);
        } elseif (!$name) {
            return array('type' => $ns);
        }

        // Need to write the below block better
        $elementTypes = array('simpleType', 'complexType');
        $wsdl = $this->xml->children($this->options['wsdlNamespace']);
        $targetPath = $this->options['prefix'].':schema/'.$this->options['prefix'].':';
        foreach ($elementTypes as $elementType) {
            $elementTypeDom = $wsdl->xpath($targetPath.$elementType.'[@name="'.$name.'"]');
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

    private function cleanResponse($response, String $function)
    {
        // $resultProperty = $function . 'Result';
        // if (property_exists($response, $resultProperty)) {
        //     $response = $response->$resultProperty;
        // }
        if (is_object($response)) {
            $response = json_decode(json_encode($response), true);
        }
        return $response;
    }

}