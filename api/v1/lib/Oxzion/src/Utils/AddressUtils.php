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
            'key' => 'AIzaSyDKTTBIKbunORXBEY-ThE5iynoUvjU3-Cc'
        ];
        $client = new RestClient($apiUrl);
        $response = $client->get($apiUrl.http_build_query($params));
        $response = (!ValidationUtils::isValid('json', $response)) ? [] : json_decode($response, true);
        $returnArray = ['address' => trim($address)];
        if (!empty($response) && $response['status'] == 'OK') {
            $response = current($response['results']);
            // echo "<pre>";print_r($response);exit;
            $returnArray['formatted_address'] = $response['formatted_address'];
            foreach ($response['address_components'] as $address_component) {
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
            $returnArray['location'] = $response['geometry']['location'];
            if (!empty($response['types'])) {
                $returnArray['location_type'] = $response['types'];
            }
        }
        if (!empty($list) && in_array('all', $list) !== false) {
            $list = array_keys($returnArray);
        }
        $returnArray = array_intersect_key($returnArray, array_combine($list, $list));
		self::$logger->info(get_class()." input data - " . print_r($address, true));
		self::$logger->info(get_class()." output data - " . print_r($returnArray, true));
        return $returnArray;
    }

    public static function getCountryDetails(string $country)
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


    public static function codeToCountryName($code)
    {
        $code = strtoupper($code);
        $countryList = array('AF' => 'Afghanistan','AX' => 'Aland Islands','AL' => 'Albania','DZ' => 'Algeria','AS' => 'American Samoa','AD' => 'Andorra','AO' => 'Angola','AI' => 'Anguilla','AQ' => 'Antarctica','AG' => 'Antigua and Barbuda','AR' => 'Argentina','AM' => 'Armenia','AW' => 'Aruba','AU' => 'Australia','AT' => 'Austria','AZ' => 'Azerbaijan','BS' => 'Bahamas the','BH' => 'Bahrain','BD' => 'Bangladesh','BB' => 'Barbados','BY' => 'Belarus','BE' => 'Belgium','BZ' => 'Belize','BJ' => 'Benin','BM' => 'Bermuda','BT' => 'Bhutan','BO' => 'Bolivia','BA' => 'Bosnia and Herzegovina','BW' => 'Botswana','BV' => 'Bouvet Island (Bouvetoya)','BR' => 'Brazil','IO' => 'British Indian Ocean Territory (Chagos Archipelago)','VG' => 'British Virgin Islands','BN' => 'Brunei Darussalam','BG' => 'Bulgaria','BF' => 'Burkina Faso','BI' => 'Burundi','KH' => 'Cambodia','CM' => 'Cameroon','CA' => 'Canada','CV' => 'Cape Verde','KY' => 'Cayman Islands','CF' => 'Central African Republic','TD' => 'Chad','CL' => 'Chile','CN' => 'China','CX' => 'Christmas Island','CC' => 'Cocos (Keeling) Islands','CO' => 'Colombia','KM' => 'Comoros the','CD' => 'Congo','CG' => 'Congo the','CK' => 'Cook Islands','CR' => 'Costa Rica','CI' => 'Cote d\'Ivoire','HR' => 'Croatia','CU' => 'Cuba','CY' => 'Cyprus','CZ' => 'Czech Republic','DK' => 'Denmark','DJ' => 'Djibouti','DM' => 'Dominica','DO' => 'Dominican Republic','EC' => 'Ecuador','EG' => 'Egypt','SV' => 'El Salvador','GQ' => 'Equatorial Guinea','ER' => 'Eritrea','EE' => 'Estonia','ET' => 'Ethiopia','FO' => 'Faroe Islands','FK' => 'Falkland Islands (Malvinas)','FJ' => 'Fiji the Fiji Islands','FI' => 'Finland','FR' => 'France, French Republic','GF' => 'French Guiana','PF' => 'French Polynesia','TF' => 'French Southern Territories','GA' => 'Gabon','GM' => 'Gambia the','GE' => 'Georgia','DE' => 'Germany','GH' => 'Ghana','GI' => 'Gibraltar','GR' => 'Greece','GL' => 'Greenland','GD' => 'Grenada','GP' => 'Guadeloupe','GU' => 'Guam','GT' => 'Guatemala','GG' => 'Guernsey','GN' => 'Guinea','GW' => 'Guinea-Bissau','GY' => 'Guyana','HT' => 'Haiti','HM' => 'Heard Island and McDonald Islands','VA' => 'Holy See (Vatican City State)','HN' => 'Honduras','HK' => 'Hong Kong','HU' => 'Hungary','IS' => 'Iceland','IN' => 'India','ID' => 'Indonesia','IR' => 'Iran','IQ' => 'Iraq','IE' => 'Ireland','IM' => 'Isle of Man','IL' => 'Israel','IT' => 'Italy','JM' => 'Jamaica','JP' => 'Japan','JE' => 'Jersey','JO' => 'Jordan','KZ' => 'Kazakhstan','KE' => 'Kenya','KI' => 'Kiribati','KP' => 'Korea','KR' => 'Korea','KW' => 'Kuwait','KG' => 'Kyrgyz Republic','LA' => 'Lao','LV' => 'Latvia','LB' => 'Lebanon','LS' => 'Lesotho','LR' => 'Liberia','LY' => 'Libyan Arab Jamahiriya','LI' => 'Liechtenstein','LT' => 'Lithuania','LU' => 'Luxembourg','MO' => 'Macao','MK' => 'Macedonia','MG' => 'Madagascar','MW' => 'Malawi','MY' => 'Malaysia','MV' => 'Maldives','ML' => 'Mali','MT' => 'Malta','MH' => 'Marshall Islands','MQ' => 'Martinique','MR' => 'Mauritania','MU' => 'Mauritius','YT' => 'Mayotte','MX' => 'Mexico','FM' => 'Micronesia','MD' => 'Moldova','MC' => 'Monaco','MN' => 'Mongolia','ME' => 'Montenegro','MS' => 'Montserrat','MA' => 'Morocco','MZ' => 'Mozambique','MM' => 'Myanmar','NA' => 'Namibia','NR' => 'Nauru','NP' => 'Nepal','AN' => 'Netherlands Antilles','NL' => 'Netherlands the','NC' => 'New Caledonia','NZ' => 'New Zealand','NI' => 'Nicaragua','NE' => 'Niger','NG' => 'Nigeria','NU' => 'Niue','NF' => 'Norfolk Island','MP' => 'Northern Mariana Islands','NO' => 'Norway','OM' => 'Oman','PK' => 'Pakistan','PW' => 'Palau','PS' => 'Palestinian Territory','PA' => 'Panama','PG' => 'Papua New Guinea','PY' => 'Paraguay','PE' => 'Peru','PH' => 'Philippines','PN' => 'Pitcairn Islands','PL' => 'Poland','PT' => 'Portugal, Portuguese Republic','PR' => 'Puerto Rico','QA' => 'Qatar','RE' => 'Reunion','RO' => 'Romania','RU' => 'Russian Federation','RW' => 'Rwanda','BL' => 'Saint Barthelemy','SH' => 'Saint Helena','KN' => 'Saint Kitts and Nevis','LC' => 'Saint Lucia','MF' => 'Saint Martin','PM' => 'Saint Pierre and Miquelon','VC' => 'Saint Vincent and the Grenadines','WS' => 'Samoa','SM' => 'San Marino','ST' => 'Sao Tome and Principe','SA' => 'Saudi Arabia','SN' => 'Senegal','RS' => 'Serbia','SC' => 'Seychelles','SL' => 'Sierra Leone','SG' => 'Singapore','SK' => 'Slovakia (Slovak Republic)','SI' => 'Slovenia','SB' => 'Solomon Islands','SO' => 'Somalia, Somali Republic','ZA' => 'South Africa','GS' => 'South Georgia and the South Sandwich Islands','ES' => 'Spain','LK' => 'Sri Lanka','SD' => 'Sudan','SR' => 'Suriname','SJ' => 'Svalbard & Jan Mayen Islands','SZ' => 'Swaziland','SE' => 'Sweden','CH' => 'Switzerland, Swiss Confederation','SY' => 'Syrian Arab Republic','TW' => 'Taiwan','TJ' => 'Tajikistan','TZ' => 'Tanzania','TH' => 'Thailand','TL' => 'Timor-Leste','TG' => 'Togo','TK' => 'Tokelau','TO' => 'Tonga','TT' => 'Trinidad and Tobago','TN' => 'Tunisia','TR' => 'Turkey','TM' => 'Turkmenistan','TC' => 'Turks and Caicos Islands','TV' => 'Tuvalu','UG' => 'Uganda','UA' => 'Ukraine','AE' => 'United Arab Emirates','GB' => 'United Kingdom','US' => 'United States of America','UM' => 'United States Minor Outlying Islands','VI' => 'United States Virgin Islands','UY' => 'Uruguay, Eastern Republic of','UZ' => 'Uzbekistan','VU' => 'Vanuatu','VE' => 'Venezuela','VN' => 'Vietnam','WF' => 'Wallis and Futuna','EH' => 'Western Sahara','YE' => 'Yemen','ZM' => 'Zambia','ZW' => 'Zimbabwe', 'Oth' => 'Others');
        return isset($countryList[$code])?$countryList[$code]:false;
    }
}
