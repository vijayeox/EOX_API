<?php

namespace Oxzion\Utils;

class StringUtils
{
    public static function startsWith($string, $startString, $caseSensitive = false)
    {
        $len = strlen($startString);
        if (!$caseSensitive) {
            $string = strtoupper($string);
            $startString = strtoupper($startString);
        }
        return (substr($string, 0, $len) === $startString);
    }

    public static function endsWith($string, $endString, $caseSensitive = false)
    {
        $len = strlen($endString);
        if (!$caseSensitive) {
            $string = strtoupper($string);
            $endString = strtoupper($endString);
        }
        if ($len == 0) {
            return true;
        }
        return (substr($string, -$len) === $endString);
    }

    public static function randomString($stringLength)
    {
        $sourceStr = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        return substr(str_shuffle($sourceStr), 0, $stringLength);
    }

    public static function formatString($type, $value)
    {
        switch ($type) {
            case 'USD':
                $value = (empty($value)) ? 0 : $value;
                $value = numfmt_format_currency(numfmt_create('en_US', \NumberFormatter::CURRENCY), $value, 'USD');
                break;
            case 'ExcelDateToTimestamp':
                $unixDate = ($value - 25569) * 86400;
                $value = gmdate("Y-m-d H:i:s", $unixDate);
                $value = (($value == '1970-01-01 00:00:00') ? null : $value);
                break;
        }
        return $value;
    }

}