# Piwik LoginLdap Plugin

## Description

**Piwik authentication module that allows users in LDAP to log in to Piwik. Supports web server authentication (eg, for Kerberos SSO).**

LoginLdap authenticates with an LDAP server and uses LDAP information to personalize Piwik.

When using LoginLdap, non-LDAP users stored in Piwik's DB will not be able to login, unless they are superusers.

## Installation

To start using LoginLdap, follow these steps:

1. Login as a superuser
2. On the _Manager > Plugins_ admin page, enable the LoginLdap plugin
3. Navigate to the _Manage > LDAP Users_ page
4. Enter and save settings for your LDAP servers

   _Note: You can test your servers by entering something into the 'Required User Group' and clicking the test link that appears.
   An error message will display if LoginLdap cannot connect to the LDAP server._
5. You can now login with LDAP cedentials.

_**Note:** LDAP users are not synchronized with Piwik until they are first logged in. This means you cannot access a token auth for an LDAP user until the user is synchronized.
To synchronize all of your LDAP users at once, use the `./console loginldap:synchronize-users` command._

## Upgrading from 2.2.7

TODO

## Features

### Authenticating with Kerberos

If you want to use Kerberos, check the **Use Web Server Auth (e.g. Kerberos SSO)** checkbox in the LDAP settings admin page.

Then, make sure your web server performs the necessary authentication and sets the `$_SERVER['REMOTE_USER']` server variable when a user is authenticated.

When the `$_SERVER['REMOTE_USER']` variable is set, LoginLdap will assume the user has already been authenticated.

_Note: The plugin will still communicate with the LDAP server in order to synchronize user details, so if LDAP settings are incorrect, authentication will fail._

### Specifying Fallback Servers

LoginLdap v3.0.0 and greater supports specifying multiple LDAP servers to use. If connecting to a server fails, the other servers are used as fallbacks.

You can enter fallback servers by adding new servers at the bottom of the _Manage > LDAP Users_ page.

### Filtering Users in LDAP

You can use the **Required User Group** and **LDAP Search Filter** settings to filter LDAP entries. Users whose entries do not match these filters
will not be allowed to authenticate.

Set **Required User Group** to the full DN of a group the user should be a member of. _Note: Internally, LoginLdap will issue a query using `(memberof=?)`
to find users of a certain group._

Set **LDAP Search Filter** to an LDAP filter string to use, for example: `(objectClass=person)` or
`(&(resMemberOf=cn=mygroup,ou=commonOU,dc=www,dc=example,dc=org)(objectclass=person))`.

You can test both of these settings from within the LDAP settings page.

### LDAP User Synchronization

LoginLdap will use information in LDAP to determine a user's alias and email address. On the _Manage > LDAP Users_ page, you can specify which LDAP attributes should be
use to determine these fields.

_Note: If the LDAP attribute for a user's alias is not found, the user's alias is defaulted to the first and last names of the user. On the settings page you can
specify which LDAP attributes are used to determine a user's first & last name._

**E-mail addresses**

E-mail addresses are required for Piwik users. If your users in LDAP do not have e-mail addresses, you can set the **E-mail Address Suffix** setting to an e-mail
address suffix, for example:

`@myorganization.com`

The suffix will be added to usernames to generate an e-mail address for your users.

### Piwik Access Synchronization

LoginLdap also supports synchronizing access levels using attributes found in LDAP. To use this feature, first, you will need to modify your LDAP server's
schema and add three special attributes to user entries:

- an attribute to specify the sites a user has view access to
- an attribute to specify the sites a user has admin access to
- and an attribute used to specify if a user is a superuser or not

_Note: You can choose whatever names you want for these attributes. You will be able to tell LoginLdap about these names in the LDAP settings page._

Then you must set these attributes correctly within LDAP, for example:

- **view: all**
- **admin: 1,2,3**
- **superuser: 1**

Finally, in the LDAP settings page, check the **Enable User Access Synchronization from LDAP** checkbox and fill out the settings that appear below it.

#### Managing Access for Multiple Piwik Instances

LoginLdap supports using a single LDAP server to manage access for multiple Piwik instances. If you'd like to use this feature, you must specify special values
for LDAP access attributes. For example:

- **view: mypiwikserver.whatever.com:1,2,3;myotherserver.com:all**
- **admin: mypiwikserver.whatever.com:all;mythirdserver.com:3,4**
- **superuser: myotherserver.com;myotherserver.com/otherpiwik**

If you don't want to use URLs in your access attributes, you can use the **Special Name For This Piwik Instance** setting to specify a special name
for each of your Piwiks. For example, if you set it to 'piwikServerA' in one Piwik and 'piwikServerB' in another, your LDAP attributes might look
like:

- **view: piwikServerA:1,2,3;piwikServerB:all**
- **admin: piwikServerA:4,5,6**
- **superuser: piwikServerC**

**Using a custom access attribute format**

You can customize the separators used in access attributes by setting the **User Access Attribute Server Specification Delimiter** and
**User Access Attribute Server & Site List Separator** settings.

If you set the **User Access Attribute Server Specification Delimiter** option to `'#'`, access attributes can be specified as:

**view: piwikServerA:1,2,3#piwikServerB:all**

If you set the **User Access Attribute Server & Site List Separator** option to `'#'`, access attributes can be specified as:

**view: piwikServerA#1,2,3;piwikServerB#all**

## Security Considerations

**User passwords**

For added security, LoginLdap will not store user passwords or a hash of a user password within Piwik's DB. So if the Piwik DB is compromised
for whatever reason, user passwords will not be compromised.

**Token Auths**

LDAP has no concept of authentication tokens, so user token_auths are stored exclusively in Piwik's MySQL DB. If a token auth is compromised,
you can have Piwik generate a new one by changing a user's password in LDAP.

**Logging**

LoginLdap uses debug logging extensively so problems can be diagnosed quickly. The logs should not contain sensitive information, but **you
should still disable DEBUG logging in production**.

If you need to debug a problem, enable it temporarily by changing the `[log] log_level` and `[log] log_writers` core INI config options.
If you use file logs, make sure to delete the logs after you are finished debugging.

## Commands

TODO

## FAQ

* [https://github.com/piwik/plugin-LoginLdap/wiki](https://github.com/piwik/plugin-LoginLdap/wiki)

## Changelog

See [https://github.com/piwik/plugin-LoginLdap/blob/master/CHANGELOG.md](https://github.com/piwik/plugin-LoginLdap/blob/master/CHANGELOG.md).

## Support

**Please direct any feedback to [https://github.com/piwik/plugin-LoginLdap](https://github.com/piwik/plugin-LoginLdap).**