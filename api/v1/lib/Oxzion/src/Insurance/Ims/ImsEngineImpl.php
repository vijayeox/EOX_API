<?php
namespace Oxzion\Insurance\Ims;

use Oxzion\ServiceException;
use Oxzion\OxServiceException;
use Oxzion\Utils\SOAPUtils;
use Oxzion\Utils\ValidationUtils;
use Oxzion\Insurance\InsuranceEngine;

class ImsEngineImpl implements InsuranceEngine
{
    private $soapClient;
    private $config;
    private $token;
    private $handle;
    private $initialHandle;

    /**
     * @ignore __construct
     */
    public function __construct($config)
    {
        $this->config = $config;
    }
    private function getConfig()
    {
        return $this->config['ims'];
    }
    public function setConfig($data)
    {
        $this->setSoapClient($data['handle']);
    }
    private function setSoapClient($handle)
    {
        $this->handle = $handle;
        $this->soapClient = new SOAPUtils($this->getConfig()['wsdlUrl'] . $this->handle . ".asmx?wsdl");
        $this->soapClient->setHeader('http://tempuri.org/IMSWebServices/' . $this->handle, 'TokenHeader', ['Token' => $this->getToken()]);
    }
    private function getToken()
    {
        if ($this->token) {
            return $this->token;
        }
        $config = $this->getConfig();
        $soapClient = new SOAPUtils($config['wsdlUrl']."logon.asmx?wsdl");
        $LoginIMSUser = $soapClient->makeCall('LoginIMSUser', $config);
        $this->token = current($LoginIMSUser)['Token'];
        return $this->token;
    }
    private function makeCall(string $method, array $data, bool $suppressError = false)
    {
        $this->checkHandle($method);
        $xmlToArray = isset($data['xmlToArray']) ? $data['xmlToArray'] : null;
        if ($suppressError) {
            try {
                $response = $this->soapClient->makeCall($method, $data);
            } catch (\Exception $e) {}
        } else {
            $response = $this->soapClient->makeCall($method, $data);
        }
        if (!isset($response) || !$response) {
            $response = [];
        }

        if ($xmlToArray) {
            $tmpResponse = $response;
            foreach (explode(',', $xmlToArray) as $value) {
                if (isset($tmpResponse[$value]))
                    $tmpResponse = &$tmpResponse[$value];
                else
                    break;
            }
            if (is_string($tmpResponse) && ValidationUtils::isValid('xml', $tmpResponse)) {
                $response = \Oxzion\Utils\XMLUtils::parseString($tmpResponse, true);
            }
        }

        return $response;
    }

    public function getFunctionStructure($method)
    {
        $this->checkHandle($method);
        return $this->soapClient->getFunctionStruct($method);
    }

    public function search($data)
    {
        $response = array();
        switch ($this->handle) {
            case 'InsuredFunctions':
                $response = $this->searchInsured($data);
                break;
            case 'ProducerFunctions':
                $response = $this->searchProducer($data);
                break;
            case 'QuoteFunctions':
                $response = $this->searchQuote($data);
                break;
            case 'DocumentFunctions':
                $response = $this->searchDocument($data);
                break;
            default:
                throw new ServiceException("Search not avaliable for " . $this->handle, 'search.not.found', OxServiceException::ERR_CODE_NOT_FOUND);
                break;
        }
        return $response;
    }
    public function searchInsured($data)
    {
        $searchMethod = 'ClearInsuredAsXml';
        $searchMethods = array(
            'insuredGuid' => 'InsuredGuid',
            'insuredContactGuid' => 'GetInsuredGuidFromContactGuid',
            'SSN' => 'FindInsuredBySSN'
        );
        foreach ($searchMethods as $key => $method) {
            if (isset($data[$key])) {
                $searchMethod = $method;
                break;
            }
        }
        switch ($searchMethod) {
            case 'InsuredGuid':
                $insureds = array(['InsuredGuid' => $data['insuredGuid']]);
                break;
            case 'GetInsuredGuidFromContactGuid':
                $insureds = array(['InsuredGuid' => current($this->makeCall($searchMethod, $data))]);
                break;
            case 'FindInsuredBySSN':
                $insureds = array(['InsuredGuid' => current($this->makeCall($searchMethod, $data))]);
                break;
            case 'ClearInsuredAsXml':
                $InsuredList = $this->makeCall($searchMethod, $data + ['xmlToArray'=>'ClearInsuredAsXmlResult']);
                $insureds = array_map(function($insured){
                    return ['InsuredGuid' => $insured['InsuredGuid'], 'Clearance' => $insured];
                }, (isset($InsuredList['Clearance']['Insured']) ? $InsuredList['Clearance']['Insured'] : []));
                unset($InsuredList);
                break;
        }
        $responseArray = [];
        foreach ($insureds as $key => $insured) {
            $GetInsured = $this->makeCall('GetInsured', array('insuredGuid' => $insured['InsuredGuid']));
            $GetInsuredPolicyInfo = $this->makeCall('GetInsuredPolicyInfo', array('insuredGuid' => $insured['InsuredGuid']));
            $GetInsuredPrimaryLocation = $this->makeCall('GetInsuredPrimaryLocation', array('insuredGuid' => $insured['InsuredGuid']));
            $HasSubmissions = $this->makeCall('HasSubmissions', array('insuredguid' => $insured['InsuredGuid']));

            $responseArray[] = array_merge($insured, $GetInsured, $GetInsuredPrimaryLocation, $HasSubmissions, $GetInsuredPolicyInfo);
        }
        return $responseArray;
    }
    public function searchProducer($data)
    {
        $searchMethod = 'ProducerClearance';
        $searchMethods = array(
            'producerLocationGuid' => 'ProducerLocationGuid',
            'producerContactGuid' => 'GetProducerInfoByContact',
            'searchString' => 'ProducerSearch'
        );
        foreach ($searchMethods as $key => $method) {
            if (isset($data[$key])) {
                $searchMethod = $method;
                break;
            }
        }
        switch ($searchMethod) {
            case 'ProducerLocationGuid':
                $producers = array(['ProducerLocationGuid' => $data['producerLocationGuid']]);
                break;
            case 'GetProducerInfoByContact':
                $GetProducerInfoByContactResult = current($this->makeCall($searchMethod, $data));
                $producers = array(['ProducerLocationGuid' => $GetProducerInfoByContactResult['ProducerLocationGuid']]);
                break;
            case 'ProducerSearch':
                $ProducerSearchResult = current($this->makeCall($searchMethod, $data));
                $producers = array_map(function($ProducerLocation){
                    return ['ProducerLocationGuid' => $ProducerLocation['ProducerLocationGuid'], 'GetProducerInfoResult' => $ProducerLocation];
                }, (isset($ProducerSearchResult['ProducerLocation']) ? $ProducerSearchResult['ProducerLocation'] : []));
                unset($ProducerSearchResult);
                break;
            case 'ProducerClearance':
                $ProducerClearanceResult = $this->makeCall($searchMethod, $data);
                if (isset(current($ProducerClearanceResult)['guid'])) {
                    foreach (current($ProducerClearanceResult)['guid'] as $guid) {
                        $GetProducerResult[$guid] = ['ProducerGuid' => $guid];
                        $GetProducerResult[$guid] += $this->makeCall('GetProducerUnderwriter', ['ProducerEntity' => $guid, 'LineGuid' => '00000000-0000-0000-0000-000000000000']);
                    }
                }
                return isset($GetProducerResult) ? array_values($GetProducerResult) : [];
                break;
        }
        if (!$producers) {
            throw new ServiceException("Producer not found", 'producer.not.found', OxServiceException::ERR_CODE_NOT_FOUND);
        }
        foreach ($producers as &$producer) {
            if (ValidationUtils::isValid('uuidStrict', $producer['ProducerLocationGuid'])) {
                if (!isset($producer['GetProducerInfoResult'])) {
                    $producer += $this->makeCall('GetProducerInfo', array('producerLocationGuid' => $producer['ProducerLocationGuid']));
                }
                if (isset(($producer['GetProducerInfoResult']['LocationCode'])) && $producer['GetProducerInfoResult']['LocationCode']) {
                    $GetProducerContactByLocationCodeResult = $this->makeCall('GetProducerContactByLocationCode', array('locationCode' => $producer['GetProducerInfoResult']['LocationCode']));
                    $producer += $GetProducerContactByLocationCodeResult;
                    $producer += $this->makeCall('GetProducerContactInfo', array('producerContactGuid' => current($GetProducerContactByLocationCodeResult)));
                }
            }
            unset($producer['ProducerLocationGuid']);
        }
        return array_filter($producers);
    }
    public function searchQuote($data)
    {
        $quote = [];
        if (isset($data['quoteGuid']) && ValidationUtils::isValid('uuidStrict', $data['quoteGuid'])) {
            $quote += ['QuoteGuid' => $data['quoteGuid']];
            $quote += $this->makeCall('AutoAddQuoteOptions', array('quoteGuid' => $quote['QuoteGuid']));
            $quote += $this->makeCall('GetPolicyInformation', array('quoteGuid' => $quote['QuoteGuid'], 'xmlToArray' => 'GetPolicyInformationResult'));
            $quote += $this->makeCall('GetControlNumber', array('quoteGuid' => $quote['QuoteGuid']));
            // if (isset($quote['GetControlNumberResult']) && ValidationUtils::isValid('int', $quote['GetControlNumberResult'])) {
            //     $quote += $this->makeCall('GetControlInformation', array('controls' => (String) $quote['GetControlNumberResult']));
            // }
            $quote += $this->makeCall('GetSubmissionGroupGuidFromQuoteGuid', array('quoteGuid' => $quote['QuoteGuid']));
            $quote += $this->makeCall('GetAvailableInstallmentOptions', array('quoteGuid' => $quote['QuoteGuid']));
        } else {
            throw new ServiceException("Invalid Quote uuid", 'quote.not.found', OxServiceException::ERR_CODE_NOT_FOUND);
        }
        return $quote;
    }
    public function searchDocument($data)
    {
        $searchMethod = '';
        $searchMethods = array(
            'docGuid' => 'GetDocumentFromStore',
            'quoteGuid' => 'QuoteGuid'
        );
        foreach ($searchMethods as $key => $method) {
            if (isset($data[$key])) {
                $searchMethod = $method;
                break;
            }
        }
        switch ($searchMethod) {
            case 'GetDocumentFromStore':
                return $this->makeCall($searchMethod, $data);
                break;
            case 'QuoteGuid':
                $document = ['QuoteGuid' => $data['quoteGuid']];
                break;
            default;
                throw new ServiceException("Invalid search request", 'document.not.found', OxServiceException::ERR_CODE_NOT_FOUND);
                break;
        }
        if (isset($document['QuoteGuid']) && ValidationUtils::isValid('uuidStrict', $document['QuoteGuid'])) {
            if (isset($data['folderID'])) {
                $document += $this->makeCall('GetDocumentFromFolder', array('quoteGuid' => $document['QuoteGuid'], 'folderID' => $data['folderID']));
            } else {
                $document += $this->makeCall('GetPolicyDocumentsList', array('QuoteGuid' => $document['QuoteGuid'], 'xmlToArray' => 'GetPolicyDocumentsListResult'));
            }
            if (isset($data['RaterID'])) {
                $document += $this->makeCall('GetPolicyRatingSheetByRater', array('QuoteGuid' => $document['QuoteGuid'], 'RaterID' => $data['RaterID']));
            } else {
                $document += $this->makeCall('GetPolicyRatingSheet', array('QuoteGuid' => $document['QuoteGuid']), true);
            }
            unset($document['QuoteGuid']);
        } else {
            throw new ServiceException("Invalid Quote uuid", 'document.not.found', OxServiceException::ERR_CODE_NOT_FOUND);
        }
        return $document;
    }

    public function create($data)
    {
        $response = array();
        switch ($this->handle) {
            case 'InsuredFunctions':
                $response = $this->makeCall('AddInsuredWithContact', $data);
                break;
            case 'ProducerFunctions':
                $response = $this->makeCall('AddProducerWithLocation', $data);
                break;
            case 'QuoteFunctions':
                $response = $this->makeCall('AddQuoteWithAutocalculateDetails', $data);
                break;
            default:
                throw new ServiceException("Create not avaliable for " . $this->handle, 'create.not.found', OxServiceException::ERR_CODE_NOT_FOUND);
                break;
        }
        return $response;
    }

    public function perform(String $method, array $data)
    {
        return $this->makeCall($method, $data);
    }

    private function checkHandle(String $method) {
        if (!$this->initialHandle) {
            $this->initialHandle = $this->handle;
        }
        switch ($method) {
            case 'ClearActiveInsured':
            case 'ClearActiveInsuredAsXml':
            case 'ClearInsured':
            case 'ClearInsuredAsXml':
            case 'ClearLocation':
            case 'ClearLocationAsXml':
            case 'ClearActiveLocationAsXml':
                $handle = 'Clearance';
                break;
            case 'ExecuteCommand':
            case 'ExecuteDataSet':
                $handle = 'DataAccess';
                break;
            default:
                $handle = $this->initialHandle;
                $this->initialHandle = null;
                break;
        }
        if ($this->handle != $handle) {
            $this->setConfig(['handle' => $handle]);
        }
    }

}