# LoginLdap Changelog

#### LoginLdap 4.0.7

* Updated translations and Readme (no code change)

#### LoginLdap 4.0.6

* Make plugin compatible with latest Matomo version

#### LoginLdap 4.0.4

* Fixing bug that made it impossible to set append_user_email_suffix_to_username to 0 for appending username suffix to username for email and not during auth.

#### LoginLdap 4.0.0

* Compatibility with Piwik 3
* Configuration value 'enable_random_token_auth_generation' has been removed as its obsolete with Piwik 3 having random auth tokens.
* Command `loginldap:generate-token-auth` has been removed as auth tokens are independent from password now and new auth token can now be generated directly in user admin
* Updated UI: Now completely works using AngularJS and material design

#### LoginLdap 3.3.1

* Plugin settings: clarify an inline help for `Use Web Server Auth (e.g. Kerberos SSO)`
 
#### LoginLdap 3.3.0

* Compatibility with Piwik 2.16.0

#### LoginLdap 3.2.2

* LDAP user can't change their passwords in Piwik's UI (passwords should be managed directly on LDAP host)

#### LoginLdap 3.2.1

* Configureed LDAP passwords are no longer stored in the HTML in the LDAP settings page. This is a minor security update.

#### LoginLdap 3.2.0

* Compatibility w/ Piwik 2.15.0

#### LoginLdap 3.1.5

* Fixing regression caused by Piwik 2.14 change: authenticating in tracker w/ token_auth no longer worked if LoginLdap was used.
* Workaround issue where 'LDAP Functions are Missing' notification was never removed from the screen by making it transient & closeable.

#### LoginLdap 3.1.2

* Change placeholder value of server hostname config option and add a note so users can avoid the problem where ports are ignored when ldap:// URLs are used in the hostname option.
* Make sure users upgrading from pre-3.0 versions set the correct LDAP settings.
* Add documentation regarding using LoginLdap with Piwik's official mobile app.

#### LoginLdap 3.1.1

* Make plugin compatible with latest Piwik version.

#### LoginLdap 3.1.0

* add --skip-existing option to loginldap:synchronize-users command
* warning displayed if Login + LoginLdap plugins are enabled at the same time
* re-added the load ldap user form in the settings page
* normal users can be managed when LdapAuth implementation is used (when Always Use LDAP for Authentication is checked)
* fixed bug in web server auth strategy where LDAP auth was not used if REMOTE_USER var not found. made connecting via mobile app impossible.
* fix bug in synchronizing users w/ user_email_suffix configured (first login worked, subsequent logins failed since username used in UserSynchronizer was incorrect)

#### LoginLdap 3.0.0:

* Automatic creation of Piwik users using LDAP (old 'auto create users' feature) is now standard.
* Default access permissions can be specified for newly synchronized users.
* Only super users are allowed to login w/o authenticating to LDAP now. Normal users stored in Piwik will not be allowed to authenticate if using LoginLdap.
* It is possible now to test memberOf and filter settings from within the LDAP settings page.
* Piwik access permissions can be specified from within LDAP using custom attributes.
* It is allowed to specify multiple LDAP fallback servers. If one fails, the others are used.
* Tests that make sure the PHP LDAP extension exists were fixed and also implemented in loginpage.
* Special LDAP log was removed. Logging is done through Piwik\\Log now.
* New setting for LDAP network timeout.
* Menu entry is LDAP > Settings now instead of Manage > LDAP Users.
* The synchronize single user feature in the settings page was removed.
* Supports three types of authentication strategies.
* Only compatible with Piwik 2.8 and above.

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
