# LoginLdap Changelog

#### LoginLdap 2.2.7:
* Auto create users from LDAP #23

#### LoginLdap 2.2.6:
* Fixes empty characters

#### LoginLdap 2.2.5:
* Fixes issue #22 'unable to login'

#### LoginLdap 2.2.4:
* Added debug mode and more detail logging

#### LoginLdap 2.2.3:
* Fixes #21 Ensure all variables are correctly set
* Storing log file in tmp/logs/ and fix PHP log read warning

#### LoginLdap 2.2.2:
* Adding missing namespace

#### LoginLdap 2.2.1:
* Controller now extends Login controller. Reusing assets and templates.

#### LoginLdap 2.1.0:
* Code updated to support Piwik 2.1 and newer

#### LoginLdap 2.0.9:
* Fixes Piwik #4001 Deprecate force_ssl_login setting

#### LoginLdap 2.0.8:
* Fixed issue #7 'Deinstallation not possible'

#### LoginLdap 2.0.7:
* Fixed issue #4 'useKerberos config problem'

#### LoginLdap 2.0.6:
* Tmuellerleile fixed default controller action

#### LoginLdap 2.0.5:
* Fixed issue with log file creation and reading

#### LoginLdap 2.0.4:
* Added 'View LDAP log from web as admin'
* Added better error detection and check if LDAP is enabled in PHP

#### LoginLdap 2.0.3:
* Issue #26 Fixed 'malformed UTF8 in de.json'
* Issue #28 Fixed 'plugin install should add parameters to config.ini.php'

#### LoginLdap 2.0.2:
* Added 'de' and 'et' translations
* Minor code enhancements

#### LoginLdap 2.0.1:
* First public release in Piwik Marketplace

#### LoginLdap 2.0.0:
* First release for Piwik 2.0, may contain bugs!
* Added LDAP server port configuration option

#### LoginLdap 1.3.5:
* Issue #20 Fixed 'kerberos is not working'
* Issue #19 Fixed 'wrong version info'

#### LoginLdap 1.3.4:
* Issue #18 Fixed 'iconv() expects parameter 3 to be string array given'

#### LoginLdap 1.3.3:
* Issue #17 Fixed 'Undefined index: phpVersion'

#### LoginLdap 1.3.2:
* Issue #15 Fixed 'Setting a custom mail field has no effect'
* Issue #16 Fixed 'Login fails because of non-UTF8 values passed to json_encode()'

#### LoginLdap 1.3.1:
* Issue #7 Added check on the activate handler to ensure the php-ldap extension is installed.
* Issue #8 Only superuser can view (and modify) LDAP configuration
* Issue #9 Fixed 'Undefined index: activeDirectory'
* Issue #11 E-Mail Address Being Required
* Issue #12 Fixed 'Undefined index: topMenu'
* Issue #13 LDAP Users were not able login using the mobile app and using API in general as their credentials were not stored in the database.
* Applied fix for Piwik Dev Zone Ticket #734: 'Correction added so Page Overlay feature works'.
* Added functionality to ensure that the Login and LoginLDAP plugins are never enabled simultaneously.
* Removed support for IE6.
* Changed log file location so to be include into the plugin directory and more easy to find.

#### LoginLdap 1.3.0:
* Issue #1 Only superuser can modify LDAP configuration
* Issue #2 LDAP search filters
* Issue #3 Enable Kerberos login for piwik
* Issue #4 You cannot login as superuser if LDAP connection fails
* Issue #5 Add more LDAP logging options
* Issue #6 Error while trying to read a specific config file entry 'LoginLdap'

#### LoginLdap 1.2.0:
* ActiveDirectory Support
* Piwik >= 1.6 Install Bug Fix

#### LoginLdap 1.0.0:
* Initial Version just for plain anonymous Ldap
