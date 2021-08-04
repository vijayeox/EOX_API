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
        try {
            $response = $this->soapClient->makeCall($method, $data);
        } catch (\Exception $e) {
            if (!$suppressError) throw $e;
            $response = [];
        }
        if (isset($data['xmlToArray'])) {
            $tmpResponse = $response;
            foreach (explode(',', $data['xmlToArray']) as $indexName) {
                if (!isset($tmpResponse[$indexName])) break;
                $tmpResponse = &$tmpResponse[$indexName];
            }
            if (is_string($tmpResponse) && ValidationUtils::isValid('xml', $tmpResponse)) {
                $response = \Oxzion\Utils\XMLUtils::parseString($tmpResponse, true);
            }
            unset($data['xmlToArray']);
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
            'SSN' => 'FindInsuredBySSN',
            'FEIN' => 'FindInsuredBySSN'
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
            case 'FindInsuredBySSN':
                $data['SSN'] = (empty($data['SSN'])) ? $data['FEIN'] : $data['SSN'];
            case 'GetInsuredGuidFromContactGuid':
                $insureds = array(['InsuredGuid' => current($this->makeCall($searchMethod, $data))]);
                break;
            case 'ClearInsuredAsXml':
                $InsuredList = $this->makeCall($searchMethod, $data + ['xmlToArray' => 'ClearInsuredAsXmlResult']);
                $insureds = array_map(function($insured){
                    return ['InsuredGuid' => $insured['InsuredGuid'], 'Clearance' => $insured];
                }, (isset($InsuredList['Clearance']['Insured']) ? $InsuredList['Clearance']['Insured'] : []));
                unset($InsuredList);
                break;
        }
        if (empty($insureds)) {
            throw new ServiceException("Insured not found", 'insured.not.found', OxServiceException::ERR_CODE_NOT_FOUND);
        }
        foreach ($insureds as &$insured) {
            if (!ValidationUtils::isValid('uuidStrict', $insured['InsuredGuid'])) continue;
            $insuredGuid = array('insuredGuid' => $insured['InsuredGuid']);
            $insured += $this->makeCall('GetInsured', $insuredGuid);
            $insured += $this->makeCall('GetInsuredPrimaryLocation', $insuredGuid);
            $insured += $this->makeCall('HasSubmissions', array('insuredguid' => $insured['InsuredGuid']));
            $insured += $this->makeCall('GetInsuredPolicyInfo', $insuredGuid);
            unset($insuredGuid);
        }
        return $insureds;
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
                $producers = array(['producerLocationGuid' => $data['producerLocationGuid']]);
                break;
            case 'GetProducerInfoByContact':
                $GetProducerInfoByContactResult = current($this->makeCall($searchMethod, $data));
                if (!empty($GetProducerInfoByContactResult['ProducerLocationGuid'])) {
                    $producers = array([
                        'producerLocationGuid' => $GetProducerInfoByContactResult['ProducerLocationGuid'],
                        'GetProducerInfoResult' => $GetProducerInfoByContactResult,
                        'ProducerContactGuid' => $data['producerContactGuid']
                    ]);
                }
                unset($GetProducerInfoByContactResult);
                break;
            case 'ProducerSearch':
                $ProducerSearchResult = current($this->makeCall($searchMethod, $data));
                if (!empty($ProducerSearchResult['ProducerLocation']) && isset($ProducerSearchResult['ProducerLocation']['ProducerLocationGuid'])) {
                    $ProducerSearchResult['ProducerLocation'] = [$ProducerSearchResult['ProducerLocation']];
                }
                $producers = array_map(function($ProducerLocation) {
                    return [
                        'producerLocationGuid' => $ProducerLocation['ProducerLocationGuid'],
                        'GetProducerInfoResult' => $ProducerLocation
                    ];
                }, (!empty($ProducerSearchResult['ProducerLocation']) ? $ProducerSearchResult['ProducerLocation'] : []));
                unset($ProducerSearchResult);
                break;
            case 'ProducerClearance':
                $ProducerClearanceResult = current($this->makeCall($searchMethod, $data));
                $producers = [];
                if ($ProducerClearanceResult) {
                    $ProducerClearanceResult = is_array($ProducerClearanceResult['guid']) ? $ProducerClearanceResult['guid'] : [$ProducerClearanceResult['guid']];
                    foreach ($ProducerClearanceResult as $producerContactGuid) {
                        $GetProducerInfoByContactResult = current($this->makeCall('GetProducerInfoByContact', ['producerContactGuid' => $producerContactGuid]));
                        $producers[] = array(
                            'producerLocationGuid' => $GetProducerInfoByContactResult['ProducerLocationGuid'],
                            'GetProducerInfoResult' => $GetProducerInfoByContactResult,
                            'ProducerContactGuid' => $producerContactGuid
                        );
                        unset($GetProducerInfoByContactResult);
                    }
                }
                unset($ProducerClearanceResult);
                break;
        }
        if (empty($producers)) {
            throw new ServiceException("Producer not found", 'producer.not.found', OxServiceException::ERR_CODE_NOT_FOUND);
        }
        foreach ($producers as &$producer) {
            if (ValidationUtils::isValid('uuidStrict', $producer['producerLocationGuid'])) {
                if (empty($producer['GetProducerInfoResult'])) {
                    $producer += $this->makeCall('GetProducerInfo', array('producerLocationGuid' => $producer['producerLocationGuid']));
                }
                unset($producer['producerLocationGuid']);
                if (empty($producer['ProducerContactGuid']) && !empty($producer['GetProducerInfoResult']['LocationCode'])) {
                    $producer += ['ProducerContactGuid' => current($this->makeCall('GetProducerContactByLocationCode', array('locationCode' => $producer['GetProducerInfoResult']['LocationCode'])))];
                }
                if (!empty($producer['ProducerContactGuid'])) {
                    $producer += $this->makeCall('GetProducerContactInfo', array('producerContactGuid' => $producer['ProducerContactGuid']));
                    // $producer += $this->makeCall('GetProducerUnderwriter', ['ProducerEntity' => $producer['ProducerContactGuid'], 'LineGuid' => '00000000-0000-0000-0000-000000000000']);
                }
            }
        }
        return array_filter($producers);
    }
    public function searchQuote($data)
    {
        $quote = [];
        if (!empty($data['quoteGuid']) && ValidationUtils::isValid('uuidStrict', $data['quoteGuid'])) {
            $quote += ['QuoteGuid' => $data['quoteGuid']];
            $quoteGuid = array('quoteGuid' => $quote['QuoteGuid']);
            $quote += $this->makeCall('AutoAddQuoteOptions', $quoteGuid);
            $quote += $this->makeCall('GetPolicyInformation', $quoteGuid + array('xmlToArray' => 'GetPolicyInformationResult'));
            $quote += $this->makeCall('GetControlNumber', $quoteGuid);
            // if (isset($quote['GetControlNumberResult']) && ValidationUtils::isValid('int', $quote['GetControlNumberResult'])) {
            //     $quote += $this->makeCall('GetControlInformation', array('controls' => (String) $quote['GetControlNumberResult']));
            // }
            $quote += $this->makeCall('GetSubmissionGroupGuidFromQuoteGuid', $quoteGuid);
            $quote += $this->makeCall('GetAvailableInstallmentOptions', $quoteGuid);
            unset($quoteGuid);
        } else {
            throw new ServiceException("Invalid quoteGuid - " . $data['quoteGuid'], 'quote.not.found', OxServiceException::ERR_CODE_NOT_FOUND);
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
        if (!empty($document['QuoteGuid']) && ValidationUtils::isValid('uuidStrict', $document['QuoteGuid'])) {
            $QuoteGuid = ['QuoteGuid' => $document['QuoteGuid']];
            if (isset($data['folderID'])) {
                $document += $this->makeCall('GetDocumentFromFolder', array('quoteGuid' => $document['QuoteGuid'], 'folderID' => $data['folderID']));
            } else {
                $document += $this->makeCall('GetPolicyDocumentsList', $QuoteGuid + array('xmlToArray' => 'GetPolicyDocumentsListResult'));
            }
            if (isset($data['RaterID'])) {
                $document += $this->makeCall('GetPolicyRatingSheetByRater', $QuoteGuid + array('RaterID' => $data['RaterID']));
            } else {
                $document += $this->makeCall('GetPolicyRatingSheet', $QuoteGuid, true);
            }
            unset($QuoteGuid);
        } else {
            throw new ServiceException("Invalid quoteGuid - " . $document['QuoteGuid'], 'document.not.found', OxServiceException::ERR_CODE_NOT_FOUND);
        }
        return $document;
    }

    public function create($data)
    {
        $response = [];
        switch ($this->handle) {
            case 'InsuredFunctions':
                $response = $this->makeCall('AddInsuredWithLocation', $data);
                break;
            case 'ProducerFunctions':
                $response = $this->makeCall('AddProducerWithLocation', $data);
                break;
            case 'QuoteFunctions':
                if (empty($data['quote']['Submission'])) {
                    $response = $this->makeCall('AddSubmission', $data['submission']);
                    $data['quote']['Submission'] = current($response);
                }
                $response += $this->makeCall('AddQuoteWithAutocalculateDetailsQuote', $data['quote']);
                break;
            default:
                throw new ServiceException("Create not avaliable for " . $this->handle, 'create.not.found', OxServiceException::ERR_CODE_NOT_FOUND);
                break;
        }
        return ['data' => $data, 'response' => $response];
    }

    public function perform(String $method, array $data)
    {
        return $this->makeCall($method, $data);
    }

    public function getQuotingParams(Array $data = [])
    {
        // return $this->getQuotingParams(['programCode' => 'APPL8']);
        // return $this->getQuotingParams(['programCode' => 'AXON1']);
        $response = $this->makeCall('ExecuteCommand', [
            "procedureName" => "ValidCompanyLinesXml",
            "parameters" => "<string>@programCode</string><string>".$data['programCode']."</string>"
        ]);
        if (is_string($response['ExecuteCommandResult']) && ValidationUtils::isValid('xml', $response['ExecuteCommandResult'])) {
            $response = \Oxzion\Utils\XMLUtils::xmlToArray($response['ExecuteCommandResult']);
            if (isset($response['CompanyLine'])) {
                $response = $this->processCompanyLines($response['CompanyLine']);
            }
        }
        $response += ["SupplementaryDataResult" => $this->makeCall('ExecuteDataSet', [
            "procedureName" => "SupplementaryData",
            "parameters" => ' ',
            "xmlToArray" => "ExecuteDataSetResult"
        ])];
        return $response;
    }

    private function processCompanyLines(Array $CompanyLines)
    {
        $result = [];
        foreach ($CompanyLines as $CompanyLine) {
            if (isset($CompanyLine['Offices']['Office']['@attributes'])) {
                $CompanyLine['Offices']['Office'] = [$CompanyLine['Offices']['Office']];
            }
            foreach ($CompanyLine['Offices']['Office'] as $Office) {
                $result['QuotingAndIssuingOffices'][$Office['@attributes']['OfficeGuid']] = $Office['@attributes']['QuotingAndIssuingOffice'];
                $result['Lines'][$Office['@attributes']['OfficeGuid']][$CompanyLine['LineGUID']] = $CompanyLine['LineName'];
                if (isset($CompanyLine['Users']['User']['@attributes'])) {
                    $CompanyLine['Users']['User'] = [$CompanyLine['Users']['User']];
                }
                foreach ($CompanyLine['Users']['User'] as $User) {
                    $result['Underwriters'][$Office['@attributes']['OfficeGuid']][$CompanyLine['LineGUID']][$User['@attributes']['UserGUID']] = $User['@attributes']['UserName'];
                }
                $result['States'][$Office['@attributes']['OfficeGuid']][$CompanyLine['LineGUID']][$CompanyLine['StateID']] = $CompanyLine['StateID'];
                $result['Company'][$Office['@attributes']['OfficeGuid']][$CompanyLine['LineGUID']][$CompanyLine['StateID']][$CompanyLine['CompanyLocationGUID']] = $CompanyLine['LocationName'];

                if (isset($CompanyLine['BillTypes']['BillType']['@attributes'])) {
                    $CompanyLine['BillTypes']['BillType'] = [$CompanyLine['BillTypes']['BillType']];
                }
                foreach ($CompanyLine['BillTypes']['BillType'] as $BillType) {
                    $result['BillTypes'][$Office['@attributes']['OfficeGuid']][$CompanyLine['LineGUID']][$CompanyLine['StateID']][$CompanyLine['CompanyLocationGUID']][$BillType['@attributes']['BillingTypeID']] = $BillType['@attributes']['BillingType'];
                }
                if (isset($Office['CostCenters']['CostCenter']['@attributes'])) {
                    $Office['CostCenters']['CostCenter'] = [$Office['CostCenters']['CostCenter']];
                }
                foreach ($Office['CostCenters']['CostCenter'] as $CostCenter) {
                    $result['CostCenters'][$Office['@attributes']['OfficeGuid']][$CompanyLine['LineGUID']][$CompanyLine['StateID']][$CompanyLine['CompanyLocationGUID']][$CostCenter['@attributes']['CostCenterID']] = $CostCenter['@attributes']['Name'];
                }
            }
        }
        return ['CompanyLines' => $result];
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