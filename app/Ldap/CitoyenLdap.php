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
}
