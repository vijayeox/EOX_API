<?php

namespace Oxzion\Utils;
use Rogervila\ArrayDiffMultidimensional;

class ArrayUtils
{

    /**
     * Get the difference between two array of one-dimensional matrix. It checks the size of the array
     * and does the difference based on the bigger array
     * @Utils
     * @Ref: UserService/getAppsWithoutAccessForUser
     * @method checkDiffMultiArray
     * @param $array1 and $array2
     * @return Array with the unique values from bigger array
     */
    public static function checkDiffMultiArray($array1, $array2)
    {
        $len1 = sizeof($array1);
        $len2 = sizeof($array2);
        if ($len1 < $len2) {
            $result = array_diff($array2, $array1); //If the length of second array is bigger than the first
        } else {
            $result = array_diff($array1, $array2); //If the length of first array is bigger than the second
        }
        return $result;
    }

    public static function multiDimensionalSearch($array, $field, $value)
    {
        foreach ($array as $key => $item) {
            if ($item[$field] === $value) {
                return $item;
            }
        }
        return false;
    }

    public static function multiFieldSearch($array, array $fieldValues)
    {
        foreach ($array as $key => $item) {
            $found = 0;
            foreach ($fieldValues as $field => $value) {
                if ($item[$field] === $value) {
                    $found++;
                }
            }
            if ($found == count($fieldValues)) {
                return $item;
            }
        }
        return false;
    }

    public static function isJson($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    public static function isKeyDefined($arr = array(), ...$params)
    {
        foreach ($params as $key => $value) {
            if (!isset($arr[$value]) || empty($arr[$value])) {
                return false;
            }
        }
        return true;
    }

    public static function convertListToMap($list, $key, $value)
    {
        $map =array();
        foreach ($list as $index => $item) {
            $map[$item[$key]]=  $item[$value];
        }
        return $map;
    }

    public static function isList($arr)
    {
        if (array() === $arr) {
            return false;
        }
        return array_keys($arr) === range(0, count($arr) - 1);
    }

    //Merges values of second array into first one - WITHOUT CREATING A NEW ARRAY.
    //It is very  useful when first array is large array - because it avoids creating
    //a copy of large array and then merging.
    //Note - both parameters are pass by reference to avoid creating a copy of the
    //arrays when they are passed by value.
    public static function merge(&$first, &$second)
    {
        foreach ($second as $key => $value) {
            $first[$key] = $value;
        }
    }
    public static function in_array_r($needle, $haystack, $strict = false)
    {
        if (is_array($haystack)) {
            foreach ($haystack as $item) {
                if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && self::in_array_r($needle, $item, $strict))) {
                    return true;
                }
            }
        }
        return false;
    }
    public static function moveKeyBefore($arr, $find, $move)
    {
        if (!isset($arr[$find], $arr[$move])) {
            return $arr;
        }
        $elem = [$move=>$arr[$move]];  // cache the element to be moved
        $start = array_splice($arr, 0, array_search($find, array_keys($arr)));
        unset($start[$move]);  // only important if $move is in $start
        return $start + $elem + $arr;
    }

    /**
     * Returns an array with the differences between $array1 and $array2
     * $strict variable defines if comparison must be strict or not
     *
     * @param array $array1
     * @param array $array2
     * @param bool $strict
     *
     * @return array
     */
    public static function compareMultiDimensionalArrays($arr1,$arr2) {
        return ArrayDiffMultidimensional::compare($arr1, $arr2);
    }
}
