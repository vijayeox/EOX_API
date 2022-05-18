<?php

namespace Oxzion\Model;

use Countable;
use Exception;
use Oxzion\DataCorruptedException;
use Oxzion\EntityNotFoundException;
use Oxzion\InvalidInputException;
use Oxzion\InvalidPropertyValueException;
use Oxzion\ParameterRequiredException;
use Oxzion\Type;
use Oxzion\ValidationException;
use ParseError;

abstract class Entity implements Countable
{
    protected $data = null;
    protected $table;

    const INCLUDE_ID = 1;
    const INCLUDE_CREATED_BY_AND_DATE = 2;
    const INCLUDE_MODIFIED_BY_AND_DATE = 4;

    const COLUMN_ID = 'id';
    const COLUMN_UUID = 'uuid';
    const COLUMN_VERSION = 'version';
    const COLUMN_CREATED_DATE = 'date_created';
    const COLUMN_CREATED_BY = 'created_by';
    const COLUMN_MODIFIED_DATE = 'date_modified';
    const COLUMN_MODIFIED_BY = 'modified_by';

    public function __construct($table = null)
    {
        $this->table = $table;
        if (!isset($this->data)) {
            $this->data = [];
            $model = &$this->getModel();
            if (!isset($model)) {
                return;
            }
            foreach ($model as $propName => $propDef) {
                $this->data[$propName] = array_key_exists('value', $propDef) ? $propDef['value'] : null;
            }
        }
    }

    public function count()
    {
        return 1;
    }

    public function __set($key, $val)
    {
        if (array_key_exists($key, $this->data)) {
            $this->data[$key] = ($val === '') ? null : $val;
        }
    }

    public function setParseData($val)
    {
        $this->parsedata = $val;
    }

    public function __get($key)
    {
        if (array_key_exists($key, $this->data)) {
            if ($this->parsedata) {
                //return VA_Service_Utils::parseInstanceExpression ($this->data[$key]);
            } else {
                return $this->data[$key];
            }
        }
    }

    public function __isset($key)
    {
        return (array_key_exists($key, $this->data)) ? isset($this->data[$key]) : false;
    }

    public function __unset($key)
    {
        if (array_key_exists($key, $this->data)) {
            unset($this->data[$key]);
        } else {
            return null;
        }
    }

    public function getKeyArray()
    {
        $data = array();
        foreach ($this->data as $key => $val) {
            $data[] = $key;
        }
        return $data;
    }

    public function import($data)
    {
        foreach ($data as $key => $val) {
            $this->__set($key, $val);
        }
        return $this;
    }

    /*
     * This method is used by the framework. Don't remove.
     */
    public function toArray()
    {
        return $this->data;
    }

    /*
     * This method is used by Zend framework to set values into the model.
     * See $resultSetPrototype->setArrayObjectPrototype in Module.php.
     */
    public function exchangeArray($data)
    {
        foreach ($data as $key => $value) {
            if (!array_key_exists($key, $this->data)) {
                continue;
            }
            $this->data[$key] = $value;
        }
    }

    //This function is to check if the data passed through the post command has NULL, "" and empty Value. If it has any of these then an error message is shown
    public function validateWithParams($dataArray)
    {
        $errors = array();
        foreach ($dataArray as $data) {
            if ($this->data[$data] === null || $this->data[$data] === "" || empty($this->data[$data])) {
                $errors[$data] = 'required';
            }
        }
        if (count($errors) > 0) {
            $validationException = new ValidationException();
            $validationException->setErrors($errors);
            throw $validationException;
        } else {
            return;
        }
    }

    /*
     * Child classes must override this method. $MODEL instance should be
     * returned by reference.
     *
     * TODO:Convert this method to abstract method when all the child classes have been migrated.
     */
    protected function &getModel()
    {
        return null;
    }

    private function runDynamicValidationIfExists($property, $value, $propDef)
    {
        if (!array_key_exists('dynamicValidation', $propDef)) {
            return;
        }
        $code = $propDef['dynamicValidation'];
        $data = $this->data;
        try {
            $result = eval("use \Oxzion\InvalidPropertyValueException;\r\n" . $code);
            if (isset($result)) {
                throw new InvalidPropertyValueException(
                    "Invalid value '${value}' for property '${property}'.",
                    ['property' => $property, 'value' => $value, 'error' => $result]
                );
            }
        } catch (ParseError $e) {
            throw new InvalidPropertyValueException(
                "Validator code parse error for property '${property}'.",
                ['property' => $property, 'error' => 'Validator code parse error:' . $e->getMessage()]
            );
        }
    }

    private function validateAndConvert($property, $value, $skipValidation = false)
    {
        $model = &$this->getModel();
        if (!isset($model)) {
            return $value; //Any value is valid when model is not set.
        }
        $propDef = $model[$property];
        if (!isset($propDef)) {
            return $value; //Any value is valid when the property definition is not set for a property.
        }
        if (!$skipValidation) {
            $this->runDynamicValidationIfExists($property, $value, $propDef);
        }
        try {
            $convertedValue = Type::convert($value, $propDef['type']);
        } catch (InvalidInputException $e) {
            throw new InvalidPropertyValueException(
                "Invalid value '${value}' for property '${property}'.",
                ['property' => $property, 'value' => $value, 'error' => 'type']
            );
        }
        if (!$skipValidation && $this->isRequired($property) && $this->isEmpty($convertedValue)) {
            throw new InvalidPropertyValueException(
                "Invalid value '${value}' for property '${property}'.",
                ['property' => $property, 'value' => $value, 'error' => 'required']
            );
        }
        return $convertedValue;
    }

    /*
     * This method only runs 'required' checks because data type is validated when
     * the values are assigned to $data array in this class.
     */
    public function validate()
    {
        $errors = array();
        foreach ($this->data as $property => $value) {
            if ($this->isRequired($property) && $this->isEmpty($value)) {
                $errors[$property] = ['error' => 'required', 'value' => $this->data[$property]];
            }
        }
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }
    }

    private function isEmpty($value)
    {
        if (!isset($value)) {
            return true;
        }
        switch (gettype($value)) {
            case 'string':
                return ((0 == strlen($value)) || (0 == strlen(trim($value, " \t\n\r\0\x0B")))) ? true : false;
                break;
            case 'boolean':
                return false;
                break;
            default:
                $strVal = print_r($value, true);
                return (0 == strlen($strVal)) ? true : false;
        }
    }

    private function isReadOnly($property)
    {
        $model = &$this->getModel();
        if (!isset($model)) {
            return false; //Everything is read-write when model is not set.
        }
        $propDef = $model[$property];
        if (!isset($propDef)) {
            return false; //Property is read-write when property definition is not set in the model.
        }
        return $propDef['readonly'] ? true : false;
    }

    private function isRequired($property)
    {
        $model = &$this->getModel();
        if (!isset($model)) {
            return false; //Everything is optional when model is not set.
        }
        $propDef = $model[$property];
        if (!isset($propDef)) {
            return false; //Property is optional when property definition is not set in the model.
        }
        return $propDef['required'] ? true : false;
    }

    /*
     * Assigns values from $input to model. Allows caller to control whether to honour 'readonly' flag.
     * Keys in $input that do not exist in the model are ignored.
     * By default readonly properties are not set. Caller can force setting readonly values
     * by seting $skipReadonly to FALSE.
     *
     * IMPORTANT: This method MUST BE PRIVATE to avoid problems with callers
     * setting properties without needed checks.
     */
    private function assignInternal($input, $skipReadOnly = true, $skipValidation = false)
    {
        $errors = array();
        foreach ($input as $property => $value) {
            //Ignore properties in input which don't exist in the model.
            if (!array_key_exists($property, $this->data)) {
                continue;
            }
            //Ignore read-only properties - if $skipReadOnly is true.
            if ($skipReadOnly && $this->isReadOnly($property)) {
                continue;
            }
            try {
                $this->data[$property] = $this->validateAndConvert($property, $value, $skipValidation);
            } catch (InvalidPropertyValueException $e) {
                $errors[$property] = ['value' => $value, 'error' => $e->getContextData()['error']];
            }
        }
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }
    }

    /*
     * Loads data from database using UUID.
     */
    public function loadByUuid($uuid)
    {
        $obj = $this->table->getByUuid($uuid);
        if (is_null($obj) || (0 == count($obj))) {
            throw new EntityNotFoundException(
                'Entity not found.',
                ['entity' => $this->table->getTableGateway()->getTable(), 'uuid' => $uuid]
            );
        }
        $this->assignInternal($obj->toArray(), false, true);
        return $this;
    }

    /*
     * Loads data from database using Id.
     */
    public function loadById($id)
    {
        $obj = $this->table->get($id);
        if (is_null($obj) || (0 == count($obj))) {
            throw new EntityNotFoundException('Entity not found.', ['entity' => $this->table->getTableGateway()->getTable(), 'id' => $id]);
        }
        $this->assignInternal($obj->toArray(), false, true);
        return $this;
    }

    /*
     * Assigns values from $input to model properties. Honours 'readonly' flag.
     * Keys in $input that do not exist in the model are ignored.
     * Throws ParameterRequiredException if 'version' is not set in $input.
     */
    public function assign($input)
    {
        //Make sure 'version' is set if this is update.
        $id = array_key_exists(self::COLUMN_ID, $this->data) ? $this->data[self::COLUMN_ID] : null;
        $uuid = array_key_exists(self::COLUMN_UUID, $this->data) ? $this->data[self::COLUMN_UUID] : null;
        if (empty($uuid) && array_key_exists(self::COLUMN_UUID, $input) && empty($input[self::COLUMN_UUID])) {
            unset($input[self::COLUMN_UUID]);
        }
        $isIdValid = (isset($id) && (0 != $id));
        $isUuidValid = isset($uuid);
        $isVersionInModel = array_key_exists(self::COLUMN_VERSION, $this->data);
        $isVersionSet = array_key_exists(self::COLUMN_VERSION, $input) && isset($input[self::COLUMN_VERSION]);
        //Existence of valid id or UUID means record is being updated. Therefore version is needed.
        if ($isVersionInModel && ($isIdValid || $isUuidValid) && !$isVersionSet) {
            throw new ParameterRequiredException('Version number is required.', [Entity::COLUMN_VERSION]);
        }
        //All ok. Go ahead and assign values.
        $this->assignInternal($input, true);
    }

    /*
     * Returns generated values 'uuid' and 'version' in an array.
     * 'id' value is also returned in the array if $includeId is TRUE.
     * 'id' value is NOT returned in the array if $includeId is not set or set to FALSE.
     */
    public function getGenerated($includeId = false)
    {
        $arr = array();
        if ($includeId) {
            $id = $this->data[self::COLUMN_ID];
            if (isset($id)) {
                $arr[self::COLUMN_ID] = $id;
            }
        }
        $uuid = isset($this->data[self::COLUMN_UUID]) ? $this->data[self::COLUMN_UUID] : null;
        if (isset($uuid)) {
            $arr[self::COLUMN_UUID] = $uuid;
        }
        if (array_key_exists(self::COLUMN_VERSION, $this->data)) {
            $version = $this->data[self::COLUMN_VERSION];
            if (isset($version)) {
                $arr[self::COLUMN_VERSION] = $version;
            }
        }
        return $arr;
    }

    public function getProperty($property)
    {
        if (!array_key_exists($property, $this->data)) {
            throw new Exception("Property '${property}' is not defined in the model.");
        }
        return $this->data[$property];
    }

    /*
     * Gets properties specified in $keyArray.
     *
     * Gets all properties except id, created_by, date_created, modified_by, date_modified
     * when $propArray is NULL or empty.
     *
     * Gets properties specified in $propArray except id, created_by, date_created, modified_by,
     * date_modified when $propArray is not NULL and not empty.
     *
     * By default id, created_by, date_created, modified_by, date_modified properties are not
     * returned. They can be fetched by setting $includes to INCLUDE_ID, INCLUDE_CREATED_BY_AND_DATE
     * and INCLUDE_MODIFIED_BY_AND_DATE or a bitwise combination of them.
     */
    public function getProperties($propArray = null, $includes = 0)
    {
        $returnArray = array();
        $includeId = $includes & self::INCLUDE_ID;
        $includeCreatedByAndDate = $includes & self::INCLUDE_CREATED_BY_AND_DATE;
        $includeModifiedByAndDate = $includes & self::INCLUDE_MODIFIED_BY_AND_DATE;
        if (!isset($propArray) || empty($propArray)) {
            $propArray = array();
            foreach ($this->data as $key => $value) {
                $propArray[] = $key;
            }
        }

        foreach ($propArray as $key) {
            if (!$includeId && (self::COLUMN_ID == $key)) {
                continue;
            }
            if (
                !$includeCreatedByAndDate &&
                ((self::COLUMN_CREATED_BY == $key) || (self::COLUMN_CREATED_DATE == $key))
            ) {
                continue;
            }
            if (
                !$includeModifiedByAndDate &&
                ((self::COLUMN_MODIFIED_BY == $key) || (self::COLUMN_MODIFIED_DATE == $key))
            ) {
                continue;
            }
            $returnArray[$key] = $this->data[$key];
        }
        return $returnArray;
    }

    public function save()
    {
        $this->validate();
        $this->table->internalSave2($this->data);
    }

    public function setForeignKey($key, $value, $force = false)
    {
        if (!array_key_exists($key, $this->data)) {
            throw new Exception("Property '${key}' is not defined in the model.");
        }
        $existingValue = $this->data[$key];
        $convertedValue = $this->validateAndConvert($key, $value);
        if (isset($existingValue)) {
            if (!$force && ($existingValue != $convertedValue)) {
                throw new DataCorruptedException(
                    'Data corrupted.',
                    [
                        'entity' => $this->table->getTableGateway()->getTable(), 'property' => $key,
                        'existingValue' => $existingValue, 'newValue' => $value
                    ]
                );
            }
        }
        $this->data[$key] = $convertedValue;
    }

    public function setModifiedBy($value, $property = self::COLUMN_MODIFIED_BY)
    {
        if (!array_key_exists($property, $this->data)) {
            throw new Exception("Property '${property}' is not defined in the model.");
        }
        $this->data[$property] = $this->validateAndConvert($property, $value);
    }

    public function setModifiedDate($value, $property = self::COLUMN_MODIFIED_DATE)
    {
        if (!array_key_exists($property, $this->data)) {
            throw new Exception("Property '${property}' is not defined in the model.");
        }
        $this->data[$property] = $this->validateAndConvert($property, $value);
    }

    public function setCreatedBy($value, $property = self::COLUMN_CREATED_BY)
    {
        if (!array_key_exists($property, $this->data)) {
            throw new Exception("Property '${property}' is not defined in the model.");
        }
        $this->data[$property] = $this->validateAndConvert($property, $value);
    }

    public function setCreatedDate($value, $property = self::COLUMN_CREATED_DATE)
    {
        if (!array_key_exists($property, $this->data)) {
            throw new Exception("Property '${property}' is not defined in the model.");
        }
        $this->data[$property] = $this->validateAndConvert($property, $value);
    }

    public function setUserGeneratedUuid($uuid)
    {
        $existingUuid = $this->data[Entity::COLUMN_UUID];
        if (isset($existingUuid)) {
            throw new Exception("UUID is already set. It cannot be modified.");
        }
        $this->data[Entity::COLUMN_UUID] = $this->validateAndConvert(Entity::COLUMN_UUID, $uuid);
    }
}
