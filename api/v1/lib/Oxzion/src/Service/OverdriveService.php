<?php

namespace Oxzion\Service;

use Oxzion\Auth\AuthConstants;
use Oxzion\Auth\AuthContext;
use Oxzion\Service\AbstractService;
use Oxzion\AppDelegate\HTTPMethod;
use Oxzion\AppDelegate\HttpClientTrait;
use Exception;


class OverdriveService extends AbstractService
{
    use HttpClientTrait;
    protected $TruechoiceApiUrl;
    protected $truechoice_auth_token;
    protected $httpmethod;
    protected $endpoint;
    
    /**
     * @ignore __construct
     */
    public function __construct($config, $dbAdapter)
    {
        parent::__construct($config, $dbAdapter);
        $this->TruechoiceApiUrl=$config['TRUECHOICE']['TRUECHOICE_API_URL'];
        $this->truechoice_auth_token=$config['TRUECHOICE']['TRUECHOICE_AUTH_TOKEN'];
    }

    /* Get Contractor Details from Truechoice */
    public function getContractor($data)
    {
        try {
        $this->httpmethod = HTTPMethod::POSTWITHHEADERS;
        $this->endpoint = 'api/contractor/search';
        $json_request = '{
            "FirstName": "' . $data['firstname'] . '",
            "LastName": "' . $data['lastname'] . '",
            "DateOfBirth": "' . $data['dateOfBirth'] . '",
            "Email": "' . $data['email'] . '",
            "MotorCarrierName": "' . $data['motorCarrier1'] . '"
            }';
        $json_request_array = json_decode($json_request);
        $response = $this->apiCall($json_request_array);
        return $response;
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            throw $e;
        }
    }

    /* Get Active Coverages of driver from Truechoice */
    public function getActiveCoverages($data)
    {
        try {
        $this->httpmethod = HTTPMethod::GET;
        $this->endpoint = 'api/coverages/'.$data["contractor_entryid"].'/driver/'.$data['driver_entryid'].'/truechoices';
        $response = $this->apiCall(array());
        return $response;
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            throw $e;
        }
    }

    /* Add Contractor to Truechoice */
    public function addContractor($data)
    {
        try {
        $json_request = '{
            "EntryId": 0,
            "MotorCarrierName": "' . $data['motorCarrier1'] . '",
            "FirstName": "' . $data['firstname'] . '",
            "MiddleInitial": "sample string 4",
            "LastName": "' . $data['lastname'] . '",
            "BirthDate": "' . $data['dateOfBirth'] . '",
            "Company": "sample string 6",
            "ContractorNumber": "sample string 7",
            "Ssn": "sample string 8",
            "Fein": "sample string 9",
            "Email": "' . $data['email'] . '",
            "Terminal": "sample string 11",
            "TruckNumber": "sample string 12",
            "CdlState": "sample string 13",
            "CdlNumber": "sample string 14",
            "Address1": "' . $data['address1'] . '",
            "City": "' . $data['city'] . '",
            "State": "' . $data['state'] . '",
            "Zip": "' . $data['zip'] . '",
            "HomePhone": "' . $data['phoneNumber'] . '",
            "CellPhone": "sample string 20",
            "OtherPhone": "sample string 21",
            "BillingMethod": "sample string 24",
            "RoutingNum": "sample string 25",
            "AccountNumber": "sample string 26",
            "CCInfo": "sample string 27",
            "CreditCardYYYY": "sample string 28",
            "CreditCardMM": "sample string 29",
            "Producer": "sample string 30",
            "Radius": "sample string 31",
            "CargoDes": "sample string 32",
            "BillingFirstName": "sample string 33",
            "BillingLastName": "sample string 34",
            "BillingAddress": "sample string 35",
            "BillingCity": "sample string 36",
            "BillingState": "sample string 37",
            "BillingZip": "sample string 38",
            "ContractDate": "2022-03-04T01:35:16.4218209-06:00",
            "BillBatch": "sample string 39",
            "MarkDes": "sample string 40",
            "MarketingPlanInfo": "sample string 41",
            "YearsOfExp": 42,
            "YearsInBusiness": 43,
            "MemberClassification": "sample string 44",
            "DrivesForMC": "sample string 45"
          }';

        $json_request_array = json_decode($json_request);

        $this->httpmethod = HTTPMethod::PUT;
        $this->endpoint = 'api/contractor';
        $response = $this->apiCall($json_request_array);
        return $response;
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            throw $e;
        }
    }

    /* Add Driver under Contractor to Truechoice */
    public function addDriver($data)
    {
        try {
        $contractor_entryid=$data['contractor_entryid'];
        $json_request = '{
                "EntryId": 0,
                "ParentId": ' . $contractor_entryid . ',
                "FirstName": "' . $data['firstname'] . '",
                "LastName": "' . $data['lastname'] . '",
                "BirthDate": "' . $data['dateOfBirth'] . '",
                "DriverNum": "sample string 5",
                "Ssn": "sample string 6",
                "Fein": "sample string 7",
                "Email": "' . $data['email'] . '",
                "HomePhone": "' . $data['phoneNumber'] . '",
                "CellPhone": "sample string 10",
                "OtherPhone": "sample string 11",
                "Address": "' . $data['address1'] . '",
                "City": "' . $data['city'] . '",
                "State": "' . $data['state'] . '",
                "Zip": "' . $data['zip'] . '",
                "MotorCarrierName": "' . $data['motorCarrier1'] . '",
                "ContractorCompanyName": "sample string 17",
                "TruckNumber": "sample string 18",
                "CDLExpire": "sample string 19",
                "CDLNumber": "sample string 20",
                "CDLState": "sample string 21",
                "MVRPoints": "sample string 22",
                "MVRPointDate": "sample string 23",
                "ContractDate": "2022-03-04T02:09:41.3024863-06:00",
                "YearsOfExp": 24,
                "Paid1099": true,
                "MaritalStatus": "sample string 26",
                "Gender": "sample string 27",
                "AnnualIncome": "sample string 28"
                }';
        $this->httpmethod = HTTPMethod::PUT;
        $this->endpoint = "api/contractor/$contractor_entryid/driver";
        $json_request_array = json_decode($json_request);
        $response_api = $this->apiCall($json_request_array);
        return $response_api;
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            throw $e;
        }
    }
    
    public function apiCall($json_request_array)
    {
        $response = $this->makeRequest(
            $this->httpmethod,
            $this->TruechoiceApiUrl . $this->endpoint,
            $json_request_array,
            [
                'AuthorizationToken' => 'Bearer ' . $this->truechoice_auth_token,
                'content-type' => 'application/json'
            ],
            ''
        );
        
        return $response;
    }
}
