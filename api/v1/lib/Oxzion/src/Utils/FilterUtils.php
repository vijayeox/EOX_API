<?php

namespace Oxzion\Utils;

class FilterUtils
{
    public static function paginate($params)
    {
        $pageSize = 20;
        $offset = 0;
        $sort = null;
        $where = "";

        if (!empty($params)) {
            if (!empty($params['limit'])) {
                $pageSize = $params['limit'];
            }

            if (!empty($params['skip'])) {
                $offset = $params['skip'];
            }

            if (isset($params['sort'])) {
                $sortArray = json_decode($params['sort'], true);
                $sort = FilterUtils::sortArray($sortArray);
            }
            if (isset($params['filter'])) {
                $filterArray = call_user_func_array('array_merge', json_decode($params['filter'], true));
                $filterlogic = isset($filterArray['logic']) ? $filterArray['logic'] : "AND";
                $filterList = $filterArray['filters'];
                $where = " WHERE " . FilterUtils::filterArray($filterList, $filterlogic);
            }
        }
        return $paginate = array('pageSize' => $pageSize, 'offset' => $offset, 'sort' => $sort, 'where' => $where);
    }

    public static function paginateLikeKendo($params, $fieldMap = array())
    {
        $pageSize = 20;
        $offset = 0;
        $sort = null;
        $where = "";
        if (!empty($params)) {
            if (!empty($params['filter'])) {
                $filterArray = json_decode($params['filter'], true);
                if (count($filterArray) > 0 || sizeof($filterArray) > 0) {
                    if (isset($filterArray[0]['take'])) {
                        $pageSize = ($filterArray[0]['take'] == 0) ? null : $filterArray[0]['take'];
                    }
                    if (isset($filterArray[0]['skip'])) {
                        $offset = $filterArray[0]['skip'];
                    }
                    if (isset($filterArray[0]['sort']) && count($filterArray[0]['sort']) > 0) {
                        $sort = FilterUtils::sortArray($filterArray[0]['sort'], $fieldMap);
                    }
                    if (isset($filterArray[0]['filter'])) {
                        $filterlogic = isset($filterArray[0]['filter']['logic']) ? $filterArray[0]['filter']['logic'] : "AND";
                        $filterList = $filterArray[0]['filter']['filters'];
                        $where = " WHERE " . FilterUtils::filterArray($filterList, $filterlogic, $fieldMap);
                    }
                }
            }
        }
        return $paginate = array('pageSize' => $pageSize, 'offset' => $offset, 'sort' => $sort, 'where' => $where);
    }

    public static function filterArray($filterList, $filterlogic, $fieldMap = array())
    {
        $where = "";
        for ($x = 0; $x < sizeof($filterList); $x++) {
            if (!isset($filterList[$x]['operator'])) {
                continue;
            }
            $operator = $filterList[$x]['operator'];
            $field = $filterList[$x]['field'];
            if(empty($field)){
                continue;
            }
            $field = isset($fieldMap[$field]) ? $fieldMap[$field] : $field;
            $value = $filterList[$x]['value'];
            $operatorp1 = '';
            $operatorp2 = '';
            if ($operator == 'startswith') {
                $operatorp2 = '%';
                $operation = ' like ';
            } elseif ($operator == 'endswith') {
                $operatorp1 = '%';
                $operation = ' like ';
            } elseif ($operator == 'eq') {
                $operation = ' = ';
            } elseif ($operator == 'neq') {
                $operation = ' <> ';
            } elseif ($operator == 'contains') {
                $operatorp1 = '%';
                $operatorp2 = '%';
                $operation = ' like ';
            } elseif ($operator == 'doesnotcontain') {
                $operatorp1 = '%';
                $operatorp2 = '%';
                $operation = ' NOT LIKE ';
            } elseif ($operator == 'isnull' || $operator == 'isempty') {
                $value = '';
                $operation = ' = ';
            } elseif ($operator == 'isnotnull' || $operator == 'isnotempty') {
                $value = '';
                $operation = ' <> ';
            } elseif ($operator == 'lte') {
                $operation = ' <= ';
            } elseif ($operator == 'lt') {
                $operation = ' < ';
            } elseif ($operator == 'gt') {
                $operation = ' > ';
            } elseif ($operator == 'gte') {
                $operation = ' >= ';
            } else {
                $operatorp1 = '%';
                $operatorp2 = '%';
                $operation = ' like ';
            }
            print_r($value);
            if ($value == 'null' || $value == null || $value == '' && $operator) {
                if ($operator == 'isnotnull' || $operator == 'isnotempty') {
                    $where .= strlen($where) == 0 ? $field . " is NOT NULL" : " " . $filterlogic . " " . $field . " is NOT NULL";
                } else {
                    $where .= strlen($where) == 0 ? $field . " is NULL" : " " . $filterlogic . " " . $field . " is NULL";
                }
            } else {
                $where .= strlen($where) == 0 ? $field . " " . $operation . "'" . $operatorp1 . $value . $operatorp2 . "'" : " " . $filterlogic . " " . $field . " " . $operation . "'" . $operatorp1 . $value . $operatorp2 . "'";
            }
            if (substr($where, 0, strlen(" " . $filterlogic)) == " " . $filterlogic) {
                $where = substr_replace($where, '', 1, strlen(" " . $filterlogic));
            }
        }
        return $where;
    }

    public static function processFilters($filterList, $filterlogic, $fieldMap = array(), &$params)
    {
        $where = "";
        for ($x = 0; $x < sizeof($filterList); $x++) {
            $operator = $filterList[$x]['operator'];
            $field = $filterList[$x]['field'];
            $field = isset($fieldMap[$field]) ? $fieldMap[$field] : $field;
            $value = $filterList[$x]['value'];
            $operatorp1 = '';
            $operatorp2 = '';
            if ($operator == 'startswith') {
                $operatorp2 = 'like';
                $operation = ' % ';
            } elseif ($operator == 'endswith') {
                $operatorp1 = '%';
                $operation = ' like ';
            } elseif ($operator == 'eq') {
                $operation = ' = ';
            } elseif ($operator == 'neq') {
                $operation = ' <> ';
            } elseif ($operator == 'contains') {
                $operatorp1 = '%';
                $operatorp2 = '%';
                $operation = ' like ';
            } elseif ($operator == 'doesnotcontain') {
                $operatorp1 = '%';
                $operatorp2 = '%';
                $operation = ' NOT LIKE ';
            } elseif ($operator == 'isnull' || $operator == 'isempty') {
                $value = '';
                $operation = ' = ';
            } elseif ($operator == 'isnotnull' || $operator == 'isnotempty') {
                $value = '';
                $operation = ' <> ';
            } elseif ($operator == 'lte') {
                $operation = ' <= ';
            } elseif ($operator == '==') {
                $operation = ' = ';
            } elseif ($operator == 'lt') {
                $operation = ' < ';
            } elseif ($operator == 'gt') {
                $operation = ' > ';
            } elseif ($operator == 'gte') {
                $operation = ' >= ';
            } else {
                $operatorp1 = '%';
                $operatorp2 = '%';
                $operation = ' like ';
            }
            if ($value == 'null' || $value == null || $value == '' && $operator) {
                if ($operator == 'isnotnull' || $operator == 'isnotempty') {
                    $where .= strlen($where) == 0 ? $field . " is NOT NULL" : " " . $filterlogic . " " . $field . " is NOT NULL";
                } else {
                    $where .= strlen($where) == 0 ? $field . " is NULL" : " " . $filterlogic . " " . $field . " is NULL";
                }
            } else {
                $where .= strlen($where) == 0 ? $field . " " . $operation . ":filter" . $x : " " . $filterlogic . " " . $field . " " . $operation . ":filter" . $x;
                $params['filter' . $x] = $operatorp1 . $value . $operatorp2;
            }
            if (substr($where, 0, strlen(" " . $filterlogic)) == " " . $filterlogic) {
                $where = substr_replace($where, '', 1, strlen(" " . $filterlogic));
            }
        }
        return $where;
    }

    public static function sortArray($sort, $fieldMap = array())
    {
        $sSort = "";
        foreach ($sort as $key => $value) {
            if ($value['dir'] == 'dsc') {
                $value['dir'] = 'desc';
            }
            if(empty($value['field'])){
                continue;
            }
            $value['field'] = isset($fieldMap[$value['field']]) ? $fieldMap[$value['field']] : $value['field'];
            $sSort .= strlen($sSort) == 0 ? $value['field'] . " " . $value['dir'] : " ," . $value['field'] . " " . $value['dir'];
        }
        return $sSort;
    }
}
