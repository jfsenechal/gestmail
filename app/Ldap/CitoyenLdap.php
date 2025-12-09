<?php

namespace App\Ldap;

use LdapRecord\Models\Model;

class CitoyenLdap extends Model
{
    /**
     * The object classes of the LDAP model.
     */
    public static array $objectClasses = [
        'gosaAccount',
        'gosaMailAccount',
        'top',
        'person',
        'organizationalPerson',
        'inetOrgPerson',
        'posixAccount',
    ];

    public static function cryptPassword(string $password): string
    {
        $salt = substr(sha1(uniqid(random_int(0, mt_getrandmax()), true), true), 0, 4);
        $rawHash = sha1($password.$salt, true).$salt;
        $method = '{SSHA}';

        return $method.base64_encode($rawHash);
    }
}
