# Matomo LoginLdap Plugin

[![Build Status](https://travis-ci.org/matomo-org/plugin-LoginLdap.svg?branch=master)](https://travis-ci.org/matomo-org/plugin-LoginLdap)

## Description

Allows users in LDAP to log in to MAtomo Analytics. Supports web server authentication (eg, for Kerberos SSO).

LoginLdap authenticates with an LDAP server and uses LDAP information to personalize Matomo.

### Installation

To start using LoginLdap, follow these steps:

1. Login as a superuser
2. On the _Manage > Marketplace_ admin page, install the LoginLdap plugin
3. On the _Manage > Plugins_ admin page, enable the LoginLdap plugin
4. Navigate to the _Settings > LDAP_ page
5. Enter and save settings for your LDAP servers

   _Note: You can test your servers by entering something into the 'Required User Group' and clicking the test link that appears.
   An error message will display if LoginLdap cannot connect to the LDAP server._

6. You can now login with LDAP cedentials.

_**Note:** LDAP users are not synchronized with Matomo until they are first logged in. This means you cannot access a token auth for an LDAP user until the user is synchronized.
If you use the default LoginLdap configuration, you can synchronize all of your LDAP users at once using the `./console loginldap:synchronize-users` command._

### Troubleshooting

To troubleshoot any connectivity issues, read our [troubleshooting guide](https://github.com/matomo-org/plugin-LoginLdap/wiki/Troubleshooting).

### Upgrading from 2.2.7

Version 3.0.0 is a major rewrite of the plugin, so if you are upgrading for 2.2.7 you will have to do some extra work when upgrading:

- Navigate tothe _Settings > LDAP_ admin page. If the configuration options look broken, make sure to reload your browser cache. You can do this by reloading the page, or through your browser's settings.

- The admin user for servers must now be a full DN. In the LDAP settings page, change the admin name to be the full DN (ie, cn=...,dc=...).

- Uncheck the `Use LDAP for authentication` checkbox

  Version 2.2.7 and below used an authentication strategy where user passwords were stored both in Matomo and in LDAP. In order to keep your current
  users' token auths from changing, that same strategy has to be used.

### Configurations

LoginLdap supports three different LDAP authentication strategies:

- using LDAP for authentication only
- using LDAP for synchronization only
- logging in with Kerberos SSO (or something similar)

Each strategy has advantages and disadvantages. What you should use depends on your needs.

### Using LDAP for authentication only

This strategy is more secure than the one below, but it requires connecting to the LDAP server on each login attempt.

With this strategy, every time a user logs in, LoginLdap will connect to LDAP to authenticate. On successful login, the user can
be synchronised, but the user's password is never stored in Matomo's DB, just in the LDAP server. Additionally, the token auth is generated using
a hash of a hash of the password, or is generated randomly.

This means that if the Matomo DB is ever compromised, your LDAP users' passwords will still be safe.

_Note: With this auth strategy, non-LDAP users are still allowed to login to Matomo. These users must be created through Matomo, not in LDAP._

**Steps to enable**

_Note: this is the default configuration._

1. Check the `Use LDAP for authentication` option and uncheck the `Use Web Server Auth (e.g. Kerberos SSO)` option.

### Using LDAP for synchronization only

This strategy involves storing the user's passwords in the Matomo DB using Matomo's hashing. As a result, it is not as secure as the above
method. If your Matomo DB is compromised, your LDAP users' passwords will be in greater danger of being cracked.

But, this strategy opens up the possibility of not communicating with LDAP servers at all during authentication, which may provide a better user experience.

_Note: With this auth strategy, non-LDAP users can login to Matomo._

**Steps to enable**

1. Uncheck the `Use LDAP for authentication` option and uncheck the `Use Web Server Auth (e.g. Kerberos SSO)` option.
2. If you don't want to connect to LDAP while logging in, uncheck the `Synchronize Users After Successful Login` option.
   
   a. If you uncheck this option, make sure your users are synchronized in some other way (eg, by using the `loginldap:synchronize-users` command).
      Matomo still needs information about your LDAP users in order to let them authenticate.

### Logging in with Kerberos SSO (or something similar)

This strategy delegates authentication to the webserver. You setup a system where the webserver authenticates the user and
sets the `$_SERVER['REMOTE_USER']` server variable, and LoginLdap will assume the user is already authenticated.

This strategy will still connect to an LDAP server in order to synchronize user information, unless configured not to.

_Note: With this auth strategy, any user that appears as a REMOTE_USER can login, even if they are not in LDAP._

**Steps to enable**

1. Check the `Use Web Server Auth (e.g. Kerberos SSO)` option.
2. If you don't want to connect to LDAP while logging in, uncheck the `Synchronize Users After Successful Login` option.
   
   a. If you uncheck this option, make sure your users are synchronized in some other way (eg, by using the `loginldap:synchronize-users` command).
      Matomo still needs information about your LDAP users in order to let them authenticate.

### Features

### Authenticating with Kerberos

If you want to use Kerberos, check the **Use Web Server Auth (e.g. Kerberos SSO)** checkbox in the LDAP settings admin page.

Then, make sure your web server performs the necessary authentication and sets the `$_SERVER['REMOTE_USER']` server variable when a user is authenticated.

When the `$_SERVER['REMOTE_USER']` variable is set, LoginLdap will assume the user has already been authenticated. When `$_SERVER['REMOTE_USER']` variable
is not set and "Always Use LDAP for Authentication" option is checked, LDAP authentication is performed. When "Always Use LDAP for Authentication" is unchecked,
normal authentication will take place.

_Note: The plugin will still communicate with the LDAP server in order to synchronize user details, so if LDAP settings are incorrect, authentication will fail._

### Specifying Fallback Servers

LoginLdap v3.0.0 and greater supports specifying multiple LDAP servers to use. If connecting to one server fails, the other servers are used as fallbacks.

You can enter fallback servers by adding new servers at the bottom of the _Settings > LDAP_ page.

### Filtering Users in LDAP

You can use the **Required User Group** and **LDAP Search Filter** settings to filter LDAP entries. Users whose entries do not match these filters
will not be allowed to authenticate.

Set **Required User Group** to the full DN of a group the user should be a member of. _Note: Internally, LoginLdap will issue a query using `(memberof=?)`
to find users of a certain group. Your server may require additional configuration to support `memberof`._

Set **LDAP Search Filter** to an LDAP filter string to use, for example: `(objectClass=person)` or
`(&(resMemberOf=cn=mygroup,ou=commonOU,dc=www,dc=example,dc=org)(objectclass=person))`.

You can test both of these settings from within the LDAP settings page.

### LDAP User Synchronization

LoginLdap will use information in LDAP to determine a user's alias and email address. On the _Settings > LDAP_ page, you can specify which LDAP attributes should be
use to determine these fields.

_Note: If the LDAP attribute for a user's alias is not found, the user's alias is defaulted to the first and last names of the user. On the settings page you can
specify which LDAP attributes are used to determine a user's first & last name._

**E-mail addresses**

E-mail addresses are required for Matomo users. If your users in LDAP do not have e-mail addresses, you can set the **E-mail Address Suffix** setting to an e-mail
address suffix, for example:

`@myorganization.com`

The suffix will be added to usernames to generate an e-mail address for your users.

Users are synchronized every time they log in. You can use the `loginldap:synchronize-users` command to synchronize users manually.

### Matomo Access Synchronization

LoginLdap also supports synchronizing access levels using attributes found in LDAP. To use this feature, first, you will need to modify your LDAP server's
schema and add three special attributes to user entries:

- an attribute to specify the sites a user has view access to
- an attribute to specify the sites a user has admin access to
- and an attribute used to specify if a user is a superuser or not

_Note: You can choose whatever names you want for these attributes. You will be able to tell LoginLdap about these names in the LDAP settings page._

Then you must set these attributes correctly within LDAP, for example:

- `view: all`
- `admin: 1,2,3`
- `superuser: 1`

Finally, in the LDAP settings page, check the **Enable User Access Synchronization from LDAP** checkbox and fill out the settings that appear below it.

User access synchronization occurs at the same time as normal user synchronization. So the `loginldap:synchronize-users` command will synchronize access levels too.

#### Managing Access for Multiple Matomo Instances

LoginLdap supports using a single LDAP server to manage access for multiple Matomo instances. If you'd like to use this feature, you must specify special values
for LDAP access attributes. For example:

- `view: mymatomoserver.whatever.com:1,2,3;myotherserver.com:all`
- `admin: mymatomoserver.whatever.com:all;mythirdserver.com:3,4`
- `superuser: myotherserver.com;myotherserver.com/othermatomo`

If you don't want to use URLs in your access attributes, you can use the **Special Name For This Matomo Instance** setting to specify a special name
for each of your Matomos. For example, if you set it to 'matomoServerA' in one Matomo and 'matomoServerB' in another, your LDAP attributes might look
like:

- `view: matomoServerA:1,2,3;matomoServerB:all`
- `admin: matomoServerA:4,5,6`
- `superuser: matomoServerC`

**Using a custom access attribute format**

You can customize the separators used in access attributes by setting the **User Access Attribute Server Specification Delimiter** and
**User Access Attribute Server & Site List Separator** settings.

If you set the **User Access Attribute Server Specification Delimiter** option to `'#'`, access attributes can be specified as:

`view: matomoServerA:1,2,3#matomoServerB:all`

If you set the **User Access Attribute Server & Site List Separator** option to `'#'`, access attributes can be specified as:

`view: matomoServerA#1,2,3;matomoServerB#all`

### Security Considerations

**User passwords**

For added security, LoginLdap's default configuration will not store user passwords or a hash of a user password within Matomo's DB. So if the Matomo DB is compromised
for whatever reason, user passwords will not be compromised.

**Token Auths**

LDAP has no concept of authentication tokens, so user token_auths are stored exclusively in Matomo's MySQL DB. If a token auth is compromised,
you can have Matomo generate a new.

**Logging**

LoginLdap uses debug logging extensively so problems can be diagnosed quickly. The logs should not contain sensitive information, but _you
should still disable DEBUG logging in production_.

If you need to debug a problem, enable it temporarily by changing the `[log] log_level` and `[log] log_writers` core INI config options.
If you use file logs, make sure to delete the logs after you are finished debugging.

### Commands

LoginLdap comes with the following console commands:

* `loginldap:synchronize-users`: Can be used to synchronize one, multiple, or all users in LDAP at once. If you'd like to setup user access
  within Matomo before a user logs in, this command should be used.

### Changelog

See [https://github.com/matomo-org/plugin-LoginLdap/blob/master/CHANGELOG.md](https://github.com/matomo-org/plugin-LoginLdap/blob/master/CHANGELOG.md).

### Support

**Please direct any feedback to [https://github.com/matomo-org/plugin-LoginLdap](https://github.com/matomo-org/plugin-LoginLdap).**
