<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\LoginLdap;

/**
 * Compares an asserted login with the login of the Matomo user it resolved to.
 *
 * Users are looked up through the collation of the `login` column, so a lookup can return a row whose login
 * only collates the same as the asserted one. These comparisons decide whether such a row may be used.
 */
class UserIdentity
{
    /**
     * Returns true if the asserted login and the stored login identify the same Matomo user.
     *
     * Only ASCII case differences are tolerated, since LDAP directories match user ids case insensitively.
     * Unicode aware folding must not be used here, as it treats as equal the same values the collation does.
     *
     * @param string $assertedLogin The login the authentication attempt was made with.
     * @param string $storedLogin The login of the user row the lookup returned.
     * @return bool
     */
    public static function isSameLogin(string $assertedLogin, string $storedLogin): bool
    {
        return self::asciiLower($assertedLogin) === self::asciiLower($storedLogin);
    }

    /**
     * Lowercases the ASCII letters in $value and leaves every other byte untouched.
     *
     * strtolower() is not used since it is locale aware on PHP versions before 8.2 and can fold bytes outside
     * of ASCII.
     *
     * @param string $value
     * @return string
     */
    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
