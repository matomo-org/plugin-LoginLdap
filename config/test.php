<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

return [
    // PHP 8.4 raised bcrypt's default cost from 10 to 12, which made every hash in these tests
    // about 4x slower; minimal-cost argon2id keeps the many users the tests create cheap.
    \Piwik\Config::class => \Piwik\DI::decorate(function ($previous) {
        if (!defined('PASSWORD_ARGON2ID')) {
            return $previous;
        }

        $general = $previous->General;
        $general['password_hash_algorithm'] = 'argon2id';
        $general['password_hash_argon2_threads'] = 1;
        $general['password_hash_argon2_memory_cost'] = 8;
        $general['password_hash_argon2_time_cost'] = 1;
        $previous->General = $general;

        return $previous;
    }),
];
