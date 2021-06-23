<?php
namespace Oxzion\Utils;

use Oxzion\ServiceException;
use Oxzion\OxServiceException;
use Webmozart\Assert\Assert as assert;
use Respect\Validation\Validator as validate;

class ValidationUtils
{
    const BOOL = false;
    const MESSAGE = true;
    const EXCEPTION = 'EXCEPTION';

    public static function isValid(String $type, $value, $getMessage = self::BOOL, $customMessage = null)
    {
        try {
            switch (strtolower($type)) {
                case 'string':
                    assert::string($value);
                    break;
                case 'int':
                    assert::integerish($value);
                    break;
                case 'float':
                case 'decimal':
                    assert::float($value);
                    break;
                case 'boolean':
                    if (!validate::BoolVal()->validate($value)) {
                        throw new \Exception("Expected a boolean", 1);
                    }
                    break;
                case 'email':
                    assert::email($value);
                    break;
                case 'date':
                    if ($value) {
                        if ((strlen($value) != 10) || !checkdate(date('m', strtotime($value)), date('d', strtotime($value)), date('Y', strtotime($value))))
                            throw new \Exception('Invalid Date', 1);
                    }
                case 'datetime':
                    if (!validate::dateTime()->validate($value)) {
                        throw new \Exception("Invalid DateTime", 1);
                    }
                    break;
                case 'uuid':
                    assert::uuid($value);
                    break;
                case 'uuidstrict':
                    if (!validate::uuid()->validate($value)) {
                        throw new \Exception("Invalid uuid", 1);
                    }
                    break;
                case 'json':
                    if (!validate::json()->validate($value)) {
                        throw new \Exception("Invalid Json", 1);
                    }
                    break;
                case 'xml':
                    $xmlErr = XMLUtils::isValid($value, ($getMessage == self::MESSAGE));
                    if ($xmlErr !== true) {
                        throw new \Exception($xmlErr, 1);
                    }
                    break;
                case 'base64':
                case 'base64binary':
                    if (!validate::base64()->validate($value)) {
                        throw new \Exception("Invalid Base64String", 1);
                    }
                    break;
                case 'inarray':
                    $options = $value['options'];
                    $value = $value['data'];
                    assert::inArray($value, $options);
                    break;
                case 'regex':
                case 'pattern':
                    $regex = $value['regex'];
                    $value = $value['data'];
                    assert::regex($value, $regex);
                    break;
                default:
                    throw new ServiceException("Unable to validate ".$type." = ".self::valueToString($value), 'validation.errors', OxServiceException::ERR_CODE_NOT_FOUND);
                    break;
            }
        } catch (\Exception $e) {
            $message = ($customMessage) ? sprintf($customMessage, self::valueToString($value)) : $e->getMessage();
            switch ($getMessage) {
                case SELF::BOOL:
                    return false;
                    break;
                case SELF::MESSAGE:
                    return $message;
                    break;
                case SELF::EXCEPTION:
                default:
                    throw new \Exception($message, 1);
                    break;
            }
        }
        return true;
    }

    private static function valueToString($value)
    {
        if (null === $value) {
            return 'null';
        }
        if (true === $value) {
            return 'true';
        }
        if (false === $value) {
            return 'false';
        }
        if (is_array($value)) {
            return 'array';
        }
        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return get_class($value).': '.self::valueToString($value->__toString());
            }

            if ($value instanceof \DateTime || $value instanceof \DateTimeImmutable) {
                return get_class($value).': '.self::valueToString($value->format('c'));
            }

            return get_class($value);
        }
        if (is_resource($value)) {
            return 'resource';
        }
        if (is_string($value)) {
            return '"'.$value.'"';
        }
        return (string) $value;
    }

}