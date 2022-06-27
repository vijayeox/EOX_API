<?php
/**
 * Code to get the Username and the List of all the apps that are not in the Userlist
 * @api
 * @link /user/:userId/usertoken
 * @method userLoginToken
 * @param $id ID of User
 * @return Json Array of Username and List of Apps
 */
namespace Oxzion\Utils;

use Ramsey\Uuid\Uuid;

class UuidUtil
{
    const UUID_PATTERN = '[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}';
    public static function uuid()
    {
        return Uuid::uuid4()->toString();
    }

    public static function isValidUuid($uuid)
    {
        return ValidationUtils::isValid('uuid', $uuid);
    }
}
