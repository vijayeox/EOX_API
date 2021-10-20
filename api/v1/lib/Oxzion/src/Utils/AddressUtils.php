<?php
namespace Oxzion\Utils;

use Logger;
AddressUtils::$logger  = Logger::getLogger(__CLASS__);

class AddressUtils
{
    public static $logger;

    public static function search(string $address, array $list = [])
    {
        $list = !empty($list) ? $list : [
            'address', 'formatted_address', 'street_number', 'street', 'city', 'state', 'zip'
        ];
        $apiUrl = 'https://maps.googleapis.com/maps/api/geocode/json?';
        $params = [
            'address' => $address,
            // 'key' => 'AIzaSyDKTTBIKbunORXBEY-ThE5iynoUvjU3-Cc'
            // 'key' => 'AIzaSyC1hAY30XQ1QGD6kRH-Q-BuGnabRRENctc'
            // 'key' => 'AIzaSyC1JjP9YuxKYRNwxPC279AMw3oNb0nk8ro'
            'key' => 'AIzaSyCY6BtcSLKz3Dd__qXbb-qqbT2BuUBV5y4'
        ];
        $client = new RestClient($apiUrl);
        $googleAddresses = $client->get($apiUrl.http_build_query($params));
        $googleAddresses = (!ValidationUtils::isValid('json', $googleAddresses)) ? [] : json_decode($googleAddresses, true);
        $returnArray = [];
        if (!empty($googleAddresses) && $googleAddresses['status'] == 'OK') {
            foreach (array_reverse($googleAddresses['results']) as $googleAddress) {
                $returnArray = [
                    'address' => trim($address),
                    'formatted_address' => $googleAddress['formatted_address']
                ];
                foreach ($googleAddress['address_components'] as $address_component) {
                    foreach ($address_component['types'] as $address_component_type) {
                        switch ($address_component_type) {
                            case 'street_number':
                                $returnArray['street_number'] = $address_component['long_name'];
                                break;
                            case 'route':
                                $returnArray['street'] = $address_component['short_name'];
                                $returnArray['street_full'] = $address_component['long_name'];
                                break;
                            case 'locality':
                                $returnArray['city'] = $address_component['long_name'];
                                break;
                            case 'administrative_area_level_2':
                                $returnArray['county'] = explode(' ', $address_component['long_name']);
                                if (strtolower(end($returnArray['county'])) == 'county') {
                                    array_pop($returnArray['county']);
                                }
                                $returnArray['county'] = implode(' ', $returnArray['county']);
                                break;
                            case 'administrative_area_level_1':
                                $returnArray['state'] = $address_component['short_name'];
                                $returnArray['state_name'] = $address_component['long_name'];
                                break;
                            case 'country':
                                $returnArray['country'] = $address_component['short_name'];
                                $returnArray['country_name'] = $address_component['long_name'];
                                break;
                            case 'postal_code':
                                $returnArray['zip'] = $address_component['long_name'];
                                break;
                        }
                    }
                }
                $returnArray['location'] = $googleAddress['geometry']['location'];
                if (!empty($googleAddress['types'])) {
                    $returnArray['location_type'] = $googleAddress['types'];
                }
                if (self::validateGoogleAddress($returnArray['address'], $returnArray)) {
                    break;
                } else {
                    $returnArray = [];
                }
            }
        } else {
            throw new \Exception($googleAddresses['status'], 500);
        }
        if (!empty($list) && in_array('all', $list) !== false) {
            $list = array_keys($returnArray);
        }
        $returnArray = array_intersect_key($returnArray, array_combine($list, $list));
		self::$logger->info(get_class()." input data - " . print_r($address, true));
		self::$logger->info(get_class()." output data - " . print_r($returnArray, true));
        return (count($returnArray) === 1 && isset($returnArray['address'])) ? [] : $returnArray;
    }

    private static function validateGoogleAddress(String $address, array $googleAddress)
    {
        if (!is_array($address)) {
            $addressArray = self::parseAddressString($address);
        }
        if (!empty($addressArray['zip']) && strpos($addressArray['zip'], $googleAddress['zip']) !== false) {
            $valid['zip'] = true;
        }
        if (!empty($addressArray['state']) && $googleAddress['state'] == $addressArray['state']) {
            $valid['state'] = true;
        }
        if (!empty($valid['state']) || !empty($valid['zip'])) {
            $googleStreet = !empty($googleAddress['street_number']) ? $googleAddress['street_number'] : (!empty($googleAddress['street']) ? current(explode(' ', trim($googleAddress['street']))) : '');
            if (!empty($googleStreet) && strpos(strtolower(current(explode(',', $addressArray['address']))), strtolower($googleStreet)) !== false) {
                return true;
            }
        }
        return false;
    }

    public static function parseAddressString(String $address)
    {
        $addressArray = ['address' => $address];
        $address = array_filter(array_map('trim', explode(',', $address)));
        switch (count($address)) {
            case 1:
                $addressArray += array_combine(['street'], $address);
                break;
            case 2:
                $addressArray += array_combine(['street', 'zipOrStateOrcity'], $address);
                break;
            case 3:
                $addressArray += array_combine(['street', 'cityOrState', 'zipOrState'], $address);
                break;
            case 4:
                $addressArray += array_combine(['street', 'city', 'state', 'zip'], $address);
                break;
            default:
                throw new \Exception("Invalid Address - ". $address, 500);
        }
        if (!empty($addressArray['zipOrStateOrcity'])) {
            $addressArray['zipOrStateOrcity'] = array_filter(array_map('trim', explode(' ', $addressArray['zipOrStateOrcity'])));
            if (($tempZip = self::validZipOrState('zip', end($addressArray['zipOrStateOrcity']))) !== false) {
                $addressArray['zip'] = $tempZip;
                array_pop($addressArray['zipOrStateOrcity']);
            }
            if (!empty($addressArray['zipOrStateOrcity'])) {
                $addressArray['zipOrStateOrcity'] = implode(' ', $addressArray['zipOrStateOrcity']);
                if (($tempState = self::validZipOrState('state', $addressArray['zipOrStateOrcity'])) !== false) {
                    $addressArray['state'] = (strlen($tempState) == 2) ? $tempState : $addressArray['zipOrStateOrcity'];
                } else {
                    $addressArray['city'] = $addressArray['zipOrStateOrcity'];
                }
            }
            unset($addressArray['zipOrStateOrcity']);
        }
        if (!empty($addressArray['zipOrState'])) {
            $addressArray['zipOrState'] = array_filter(array_map('trim', explode(' ', $addressArray['zipOrState'])));
            if (($tempZip = self::validZipOrState('zip', end($addressArray['zipOrState']))) !== false) {
                $addressArray['zip'] = $tempZip;
                array_pop($addressArray['zipOrState']);
            }
            if (!empty($addressArray['zipOrState'])) {
                $addressArray['zipOrState'] = implode(' ', $addressArray['zipOrState']);
                if (($tempState = self::validZipOrState('state', $addressArray['zipOrState'])) !== false) {
                    $addressArray['state'] = (strlen($tempState) == 2) ? $tempState : $addressArray['zipOrState'];
                }
            }
            unset($addressArray['zipOrState']);
        }
        if (!empty($addressArray['cityOrState'])) {
            $addressArray['cityOrState'] = implode(' ', array_filter(array_map('trim', explode(' ', $addressArray['cityOrState']))));
            if (!empty($addressArray['cityOrState'])) {
                if (($tempState = self::validZipOrState('state', $addressArray['cityOrState'])) !== false) {
                    $addressArray['state'] = (strlen($tempState) == 2) ? $tempState : $addressArray['cityOrState'];
                } else {
                    $addressArray['city'] = $addressArray['cityOrState'];
                }
            }
            unset($addressArray['cityOrState']);
        }
        if (!empty($addressArray['street'])) {
            $tempStreet = array_filter(array_map('trim', explode(' ', $addressArray['street'])));
            if (is_numeric(current($tempStreet))) {
                $addressArray['street_number'] = current($tempStreet);
                unset($tempStreet[0]);
                $addressArray['street'] = trim(implode(' ', $tempStreet));
            }
        }
        return $addressArray;
    }

    public static function validZipOrState(String $type, String $value)
    {
        $value = trim($value);
        if (empty($value)) return false;
        switch ($type) {
            case 'zip':
                if (self::getZipDetails($value)) {
                    return (strlen($value) == 4) ? (int) '0'.$value : $value;
                }
                break;
            case 'state':
                if (!empty($tempValue = self::getStateList($value)) || !empty($tempValue = self::getStateList($value, true))) {
                    return $tempValue;
                }
                break;
        }
        return false;
    }

    public static function searchCountryDetails(string $country)
    {
        $country = self::search('country ' . $country, ['country', 'country_name']);
        if (empty($country)) {
            throw new \Exception("INCORRECT_COUNTRY", 400);
        }
        $apiUrl = 'https://api.worldbank.org/v2/country/'.$country['country'].'?format=json';
        $client = new RestClient($apiUrl);
        $response = $client->get($apiUrl);
        $response = (!ValidationUtils::isValid('json', $response)) ? [] : json_decode($response, true);
        if (empty(current($response)['total']) || current($response)['total'] != 1) {
            throw new \Exception("COUNTRY_NOT_FOUND", 404);
        }
        return current(end($response));
    }

    // might have blanks for specific zips in less popurlar countries. eg. 560061, IN
    public static function getZipDetails($zip, string $countryISO2 = 'US') {
        $response = [];
        if (!is_numeric($zip)) return $response;
        if (strtoupper($countryISO2) == 'US') {
            $client = new RestClient('https://ziptasticapi.com/');
            $response = json_decode($client->get((string) $zip), true);
        }
        if (empty($response) || isset($response['error'])) {
            $client = new RestClient("https://api.zippopotam.us/$countryISO2/");
            $response = json_decode($client->get($zip), true);
            if (!empty($response)) {
                $response = [
                    "country" => strtoupper($response['country abbreviation']),
                    "state" => strtoupper(current($response['places'])['state abbreviation']),
                    "city" => strtoupper(current($response['places'])['place name'])
                ];
            }
        }
        return $response;
    }

    // currently works only for us states
	public static function getStateList(string $code = null, bool $checkName = false)
	{
        $states = json_decode('{"AE":"Armed Forces Europe","AK":"Alaska","AL":"Alabama","AP":"Armed Forces Pacific","AR":"Arkansas","AZ":"Arizona","CA":"California","CO":"Colorado","CT":"Connecticut","DC":"District of Columbia","DE":"Delaware","FL":"Florida","FM":"Micronesia","GA":"Georgia","GU":"Guam","HI":"Hawaii","IA":"Iowa","ID":"Idaho","IL":"Illinois","IN":"Indiana","International":"International","KS":"Kansas","KY":"Kentucky","LA":"Louisiana","MA":"Massachusetts","MB":"Manitoba","MD":"Maryland","ME":"Maine","MH":"Marshall Islands","MI":"Michigan","MN":"Minnesota","MO":"Missouri","MP":"Northern Marianas","MS":"Mississippi","MT":"Montana","NC":"North Carolina","ND":"North Dakota","NE":"Nebraska","NH":"New Hampshire","NJ":"New Jersey","NM":"New Mexico","NV":"Nevada","NY":"New York","OH":"Ohio","OK":"Oklahoma","ON":"Ontario","OR":"Oregon","PA":"Pennsylvania","PR":"Puerto Rico","PW":"Palau","RI":"Rhode Island","SC":"South Carolina","SD":"South Dakota","TN":"Tennessee","TX":"Texas","UT":"Utah","VA":"Virginia","VI":"Virgin Islands","VT":"Vermont","WA":"Washington","WI":"Wisconsin","WV":"West Virginia","WY":"Wyoming"}', true);
        if ($checkName) {
            $states = array_flip(array_map('strtoupper', $states));
        }
        return empty($code) ? $states : (isset($states[strtoupper($code)]) ? $states[strtoupper($code)] : null);
	}

    public static function getCountryDetails(array $listConfig = [], string $search = null, string $checkValue = 'ISO2')
    {
        if (empty($listConfig)) $listConfig = ['key' => 'ISO2', 'value' => null];
        $detailList = [
            // 'ISO2',
            'ISO3' => json_decode(file_get_contents('http://country.io/iso3.json')),
            'Country' => json_decode(file_get_contents('http://country.io/names.json')),
            'Phone' => json_decode(file_get_contents('http://country.io/phone.json')),
            'Currency' => json_decode(file_get_contents('http://country.io/currency.json')),
            'Continent' => json_decode(file_get_contents('http://country.io/continent.json'))
        ];
        $details = [];
        foreach ($detailList as $index => $list) {
            foreach ($list as $code => $value) {
                if (empty($details[$code]['ISO2'])) $details[$code]['ISO2'] = $code;
                $details[$code][$index] = $value;
            }
        }
        // echo "<pre>";print_r(json_encode($details));exit;
        if (!empty($search)) {
            $search = strtoupper(trim($search));
            $detailsTemp = array_change_key_case(array_column($details, null, $checkValue), CASE_UPPER);
            switch ($checkValue) {
                case 'Country':
                    if (isset($detailsTemp[$search])) {
                        return $detailsTemp[$search];
                    }
                    foreach ($detailsTemp as $key => $detail) {
                        if (strpos($key, $search) !== false) {
                            return $detail;
                        }
                    }
                    return null;
                    break;
                default:
                    return isset($detailsTemp[$search]) ? $detailsTemp[$search] : null;
                    break;
            }
        }
        return array_column($details, $listConfig['value'], $listConfig['key']);
    }

}