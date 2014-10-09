# Piwik LoginLdap Plugin

## Description

**Piwik login that uses LDAP queries to authenticate users and offers Kerberos SSO support.**

#### HowTo Create a new User from LDAP in Piwik:
* Login to Piwik backend as superuser
* Navigate to Manage->LDAP Users
* If the LDAP Settings are correct you are now able to look up users from your directory

#### Note:
* Everytime a user is trying to login to your Piwik, the password is first of all checked
against your LDAP, if fails the plugin will check against the database.

#### Note2:
* Users may be modified and deleted via Manage->Users

#### Note3:
* If you change the username in LDAP/AD it will not be updated to Piwik!

#### Note4:
* When LDAP users are initially added on the 'Manage->LDAP Users' page their password is not entered and stored in the database.
However, when they first login via the web interface their credentials will be saved correctly in a hashed format.
From that point on they can login via mobile apps and API in general.

## FAQ

* [https://github.com/piwik/plugin-LoginLdap/wiki](https://github.com/piwik/plugin-LoginLdap/wiki)

## Changelog

See [https://github.com/piwik/plugin-LoginLdap/blob/master/CHANGELOG.md](https://github.com/piwik/plugin-LoginLdap/blob/master/CHANGELOG.md).

## Support

**Please direct any feedback to [https://github.com/tehnotronic/PiwikLdap](https://github.com/tehnotronic/PiwikLdap)**
