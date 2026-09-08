<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

return [
    // keeps a Matomo session from outliving the web server identity it was created for
    \Piwik\Session\SessionAuth::class => \Piwik\DI::decorate(function (\Piwik\Session\SessionAuth $previous) {
        return new \Piwik\Plugins\LoginLdap\Auth\WebServerSessionAuth($previous);
    }),

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
