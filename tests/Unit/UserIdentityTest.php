<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\LoginLdap\tests\Unit;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\LoginLdap\UserIdentity;

/**
 * @group LoginLdap
 * @group LoginLdap_Unit
 * @group LoginLdap_UserIdentityTest
 */
class UserIdentityTest extends TestCase
{
    /**
     * @dataProvider getLoginsToCompare
     */
    public function test_isSameLogin_ComparesLoginsWithoutUnicodeFolding($expected, $assertedLogin, $storedLogin)
    {
        $this->assertSame($expected, UserIdentity::isSameLogin($assertedLogin, $storedLogin));
    }

    public function getLoginsToCompare()
    {
        return array(
            'identical' => array(true, 'karen', 'karen'),
            'ascii case difference' => array(true, 'Karen', 'karen'),
            'ascii case difference, other way round' => array(true, 'karen', 'KAREN'),
            'different logins' => array(false, 'karen', 'bob'),

            // the collation of the login column equates these pairs, as does mb_strtolower()
            'kelvin sign' => array(false, "\xe2\x84\xaaaren", 'Karen'),
            'angstrom sign' => array(false, "\xe2\x84\xabngus", 'Ångus'),
            'accent' => array(false, 'josé', 'jose'),
            'sharp s' => array(false, 'straße', 'strasse'),
            'trailing space, PAD SPACE collations' => array(false, 'karen ', 'karen'),
            'full width letter' => array(false, "\xef\xbd\x8baren", 'karen'),
        );
    }

    public function test_isSameLogin_DoesNotFoldTheSameWayMbStrtolowerDoes()
    {
        // guards against a regression back to mb_strtolower(), which reports these two logins as equal
        $kelvinKaren = "\xe2\x84\xaaaren";

        $this->assertSame(mb_strtolower($kelvinKaren, 'UTF-8'), mb_strtolower('Karen', 'UTF-8'));
        $this->assertFalse(UserIdentity::isSameLogin($kelvinKaren, 'Karen'));
    }
}
