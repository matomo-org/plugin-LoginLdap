<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

return [
    'observers.global' => \Piwik\DI::add([
        ['Login.userRequiresPasswordConfirmation', \Piwik\DI::value(function (&$requiresPasswordConfirmation, $login) {
            if (\Piwik\Plugins\LoginLdap\Auth\WebServerAuth::isCurrentRequestWebServerAuthenticated()) {
                $requiresPasswordConfirmation = false;
                return;
            }
            $userMapper = new \Piwik\Plugins\LoginLdap\LdapInterop\UserMapper();
            if ($userMapper->isUserLdapUser($login)) {
                $enablePasswordConfirmation = \Piwik\Plugins\LoginLdap\Config::getConfigOption('enable_password_confirmation');
                if (!$enablePasswordConfirmation) {
                    $requiresPasswordConfirmation = false;
                }
            }
        })],
    ]),
];
