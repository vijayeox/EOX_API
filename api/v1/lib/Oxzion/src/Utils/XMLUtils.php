<?php
namespace Oxzion\Utils;

// use Symfony\Component\Config\Util\XmlUtils as sXmlUtils;

class XMLUtils
{
    public static function xmlToArray($string)
    {
        return $xmlArray = json_decode(json_encode(simplexml_load_string($string)), true);
    }

    public static function domToArray($root)
    {
        $result = array();
        if ($root->hasAttributes()) {
            $attrs = $root->attributes;
            foreach ($attrs as $i => $attr) {
                $result[$attr->name] = $attr->value;
            }
        }
        $children = $root->childNodes;
        if ($children) {
            if ($children->length == 1) {
                $child = $children->item(0);
                if ($child->nodeType == XML_TEXT_NODE) {
                    $result['_value'] = $child->nodeValue;
                    if (count($result) == 1) {
                        return $result['_value'];
                    } else {
                        return $result;
                    }
                }
            }
        }
        $group = array();
        if (isset($children->length)) {
            for ($i = 0; $i < $children->length; $i++) {
                $child = $children->item($i);
                if (!isset($result[$child->nodeName])) {
                    $result[$child->nodeName] = self::domToArray($child);
                } else {
                    if (!isset($group[$child->nodeName])) {
                        $tmp = $result[$child->nodeName];
                        $result[$child->nodeName] = array($tmp);
                        $group[$child->nodeName] = 1;
                    }
                    $result[$child->nodeName][] = self::domToArray($child);
                }
            }
        }
        return $result;
    }

     public static function isValid( String $sXml, $getMessage = false, $version = '1.0', $encoding = 'utf-8' ) {
        try {
            libxml_use_internal_errors(true);

            // (new \DOMDocument($version, $encoding))->loadXML($sXml);
            simplexml_load_string($sXml);
            // sXmlUtils::parse($sXml);

            $errorMessage = implode(', ', array_map(function($error){
                return $error->message;
            }, libxml_get_errors()));
            libxml_clear_errors();

            if ($errorMessage) throw new \Exception($errorMessage, 1);

        } catch (\Exception $e) {
            return ($getMessage) ? $e->getMessage() : false;
        }
        return true;
    }

    /**
     * Method for loading XML Data from String
     *
     * @param string $sXml
     * @param bool $bOptimize
     */
    public static function parseString( String $sXml , $bOptimize = false) {
        $oXml = new \XMLReader();
        // try {
            // Set String Containing XML data
            $oXml->XML($sXml);

            // Parse Xml and return result
            return self::parseXml($oXml, $bOptimize);
        // } catch (\Exception $e) {
        //     echo $e->getMessage();
        // }
    }

    /**
     * Method for loading Xml Data from file
     *
     * @param string $sXmlFilePath
     * @param bool $bOptimize
     */
    public static function parseFile( String $sXmlFilePath , $bOptimize = false ) {
        $oXml = new \XMLReader();
        // try {
            // Open XML file
            $oXml->open($sXmlFilePath);

            // // Parse Xml and return result
            return self::parseXml($oXml, $bOptimize);
        // } catch (\Exception $e) {
        //     echo $e->getMessage(). ' | Try open file: '.$sXmlFilePath;
        // }
    }

    /**
     * XML Parser
     *
     * @param XMLReader $oXml
     * @return array
     */
    protected static function parseXml( \XMLReader $oXml , $bOptimize = false ) {

        $aAssocXML = null;
        $iDc = -1;

        while($oXml->read()){
            switch ($oXml->nodeType) {

                case \XMLReader::END_ELEMENT:

                    if ($bOptimize) {
                        self::optXml($aAssocXML);
                    }
                    return $aAssocXML;

                case \XMLReader::ELEMENT:

                    if(!isset($aAssocXML[$oXml->name])) {
                        if($oXml->hasAttributes) {
                            $aAssocXML[$oXml->name][] = $oXml->isEmptyElement ? '' : self::parseXML($oXml, $bOptimize);
                        } else {
                            if($oXml->isEmptyElement) {
                                $aAssocXML[$oXml->name] = '';
                            } else {
                                $aAssocXML[$oXml->name] = self::parseXML($oXml, $bOptimize);
                            }
                        }
                    } elseif (is_array($aAssocXML[$oXml->name])) {
                        if (!isset($aAssocXML[$oXml->name][0]))
                        {
                            $temp = $aAssocXML[$oXml->name];
                            foreach ($temp as $sKey=>$sValue)
                            unset($aAssocXML[$oXml->name][$sKey]);
                            $aAssocXML[$oXml->name][] = $temp;
                        }

                        if($oXml->hasAttributes) {
                            $aAssocXML[$oXml->name][] = $oXml->isEmptyElement ? '' : self::parseXML($oXml, $bOptimize);
                        } else {
                            if($oXml->isEmptyElement) {
                                $aAssocXML[$oXml->name][] = '';
                            } else {
                                $aAssocXML[$oXml->name][] = self::parseXML($oXml, $bOptimize);
                            }
                        }
                    } else {
                        $mOldVar = $aAssocXML[$oXml->name];
                        $aAssocXML[$oXml->name] = array($mOldVar);
                        if($oXml->hasAttributes) {
                            $aAssocXML[$oXml->name][] = $oXml->isEmptyElement ? '' : self::parseXML($oXml, $bOptimize);
                        } else {
                            if($oXml->isEmptyElement) {
                                $aAssocXML[$oXml->name][] = '';
                            } else {
                                $aAssocXML[$oXml->name][] = self::parseXML($oXml, $bOptimize);
                            }
                        }
                    }

                    if($oXml->hasAttributes) {
                        $mElement =& $aAssocXML[$oXml->name][count($aAssocXML[$oXml->name]) - 1];
                        while($oXml->moveToNextAttribute()) {
                            $mElement[$oXml->name] = $oXml->value;
                        }
                    }
                    break;
                case \XMLReader::TEXT:
                case \XMLReader::CDATA:

                    $aAssocXML[++$iDc] = $oXml->value;

            }
        }

        return $aAssocXML;
    }

    /**
     * Method to optimize assoc tree.
     * ( Deleting 0 index when element
     *  have one attribute / value )
     *
     * @param array $mData
     */
    public static function optXml(&$mData) {
        if (is_array($mData)) {
            if (isset($mData[0]) && count($mData) == 1 ) {
                $mData = $mData[0];
                if (is_array($mData)) {
                    foreach ($mData as &$aSub) {
                        self::optXml($aSub);
                    }
                }
            } else {
                foreach ($mData as &$aSub) {
                    self::optXml($aSub);
                }
            }
        }
    }

}