#!/bin/bash

# install LDAP
echo "Installing LDAP..."
sudo apt-get update > /dev/null
if ! sudo apt-get install slapd ldap-utils -y -qq > /dev/null; then
    echo "Failed to install OpenLDAP!"
fi

# configure LDAP
echo ""
echo "Configuring LDAP..."

mkdir -p /tmp/ldap
sudo chmod -R 777 /tmp/ldap

ADMIN_USER=fury
ADMIN_PASS=secrets
ADMIN_PASS_HASH=`slappasswd -h {md5} -s $ADMIN_PASS`
BASE_DN="dc=avengers,dc=shield,dc=org"

STR_OID="1.3.6.1.4.1.1466.115.121.1.15"
VIEW_OID="2.16.840.1.113730.3.1.1.1"
ADMIN_OID="2.16.840.1.113730.3.1.1.2"
SUPERUSER_OID="2.16.840.1.113730.3.1.1.3"

sudo ldapmodify -Y EXTERNAL -H ldapi:/// <<EOF

dn: cn=config
changetype: modify
replace: olcLogLevel
olcLogLevel: -1
-
add: olcDisallows
olcDisallows: bind_anon

EOF

if [ "$?" -ne "0" ]; then
    echo "Failed to change config olcLogLevel or olcDisallows!"
    echo ""
    echo "slapd log:"
    sudo grep slapd /var/log/syslog

    exit 1
fi

sudo ldapadd -Y EXTERNAL -H ldapi:/// <<EOF

# database
dn: olcDatabase={2}hdb,cn=config
objectClass: olcDatabaseConfig
objectClass: olcHdbConfig
olcDatabase: {2}hdb
olcRootDN: cn=$ADMIN_USER,$BASE_DN
olcRootPW: $ADMIN_PASS_HASH
olcDbDirectory: /tmp/ldap
olcSuffix: $BASE_DN
olcAccess: {0}to attrs=userPassword,shadowLastChange by self write by dn="cn=$ADMIN_USER,$BASE_DN" write by * auth
olcAccess: {1}to dn.base="" by dn="cn=$ADMIN_USER,$BASE_DN" write by * read
olcAccess: {2}to * by self write by dn="cn=$ADMIN_USER,$BASE_DN" write by * read
olcRequires: authc
olcLastMod: TRUE
olcDbCheckpoint: 512 30
olcDbConfig: {0}set_cachesize 0 2097152 0
olcDbConfig: {1}set_lk_max_objects 1500
olcDbConfig: {2}set_lk_max_locks 1500
olcDbConfig: {3}set_lk_max_lockers 1500
olcDbIndex: objectClass eq

# modules
dn: cn=module,cn=config
cn: module
objectClass: olcModuleList
objectClass: top
olcModulePath: /usr/lib/ldap
olcModuleLoad: memberof.la

dn: olcOverlay={0}memberof,olcDatabase={2}hdb,cn=config
objectClass: olcConfig
objectClass: olcMemberOf
objectClass: olcOverlayConfig
objectClass: top
olcOverlay: memberof

dn: cn=module,cn=config
cn: module
objectclass: olcModuleList
objectClass: top
olcModuleLoad: refint.la
olcModulePath: /usr/lib/ldap

dn: olcOverlay={1}refint,olcDatabase={2}hdb,cn=config
objectClass: olcConfig
objectClass: olcOverlayConfig
objectClass: olcRefintConfig
objectClass: top
olcOverlay: {1}refint
olcRefintAttribute: memberof member manager owner

EOF

if [ "$?" -ne "0" ]; then
    echo "Failed to change config database or modules!"
    echo ""
    echo "slapd log:"
    sudo grep slapd /var/log/syslog

    exit 1
fi

sudo ldapmodify -Y EXTERNAL -H ldapi:/// <<EOF

# first define custom LDAP attributes for Piwik access
dn: cn=schema,cn=config
changetype: modify
add: olcAttributeTypes
olcAttributeTypes: ( $VIEW_OID
  NAME 'view'
  DESC 'Describes site IDs user has view access to.'
  EQUALITY caseIgnoreMatch
  ORDERING caseIgnoreOrderingMatch
  SYNTAX $STR_OID )
-
add: olcAttributeTypes
olcAttributeTypes: ( $ADMIN_OID
  NAME 'admin'
  DESC 'Describes site IDs user has admin access to.'
  EQUALITY caseIgnoreMatch
  ORDERING caseIgnoreOrderingMatch
  SYNTAX $STR_OID )
-
add: olcAttributeTypes
olcAttributeTypes: ( $SUPERUSER_OID
  NAME 'superuser'
  DESC 'Marks user as superuser if present.'
  EQUALITY caseIgnoreMatch
  ORDERING caseIgnoreOrderingMatch
  SYNTAX $STR_OID )

EOF

if [ "$?" -ne "0" ]; then
    echo "Failed to add custom attributes!"
    echo ""
    echo "slapd log:"
    sudo grep slapd /var/log/syslog

    exit 1
fi

sudo ldapmodify -Y EXTERNAL -H ldapi:/// <<EOF

dn: cn=schema,cn=config
changetype: modify
add: olcObjectClasses
olcObjectClasses: ( 2.16.840.1.113730.3.2.3
   NAME 'piwikPerson'
   DESC 'Piwik User'
   SUP inetOrgPerson
   STRUCTURAL
   MAY ( view $ admin $ superuser )
   )

EOF

if [ "$?" -ne "0" ]; then
    echo "Failed to add piwikPerson class!"
    echo ""
    echo "slapd log:"
    sudo grep slapd /var/log/syslog

    exit 1
fi

echo "Configured."

# add entries to LDAP
echo ""
echo "Adding entries to LDAP..."

sudo ldapadd -xv -w $ADMIN_PASS -D cn=$ADMIN_USER,$BASE_DN <<EOF

# base dn
dn: $BASE_DN
objectClass: domain
objectClass: top
dc: avengers

# ou entry
dn: ou=Groups,$BASE_DN
objectclass: organizationalunit
ou: Groups
description: all groups

# USER ENTRY (pwd: piedpiper)
dn: cn=Tony Stark,$BASE_DN
cn: Tony Stark
sn: Stark
givenName: Tony
objectClass: piwikPerson
objectClass: top
uid: ironman
userPassword: `slappasswd -h {md5} -s piedpiper`
mobile: 555-555-5555
mail: billionairephilanthropistplayboy@starkindustries.com
view: 1,2
view: 3
admin: 3

# USER ENTRY (pwd: redledger)
dn: cn=Natalia Romanova,$BASE_DN
cn: Natalia Romanova
objectClass: top
objectClass: piwikPerson
sn: Romanova
givenName: Natalia
uid: blackwidow
userPassword: `slappasswd -h {md5} -s redledger`
mobile: none
view: myPiwik:1,2;anotherPiwik:3,4
admin: myPiwik:3,4
admin: anotherPiwik:5,6

# USER ENTRY (pwd: thaifood)
dn: cn=Steve Rodgers,$BASE_DN
cn: Steve Rodgers
objectClass: top
objectClass: piwikPerson
sn: Rodgers
givenName: Steve
uid: captainamerica
userPassword: `slappasswd -h {md5} -s thaifood`
mobile: 123-456-7890
mail: srodgers@aol.com
superuser: 1
superuser: anotherPiwik

# USER ENTRY (pwd: bilgesnipe)
dn: cn=Thor,$BASE_DN
cn: Thor
objectClass: top
objectClass: piwikPerson
sn: Odinson
givenName: Thor
uid: thor
userPassword: `slappasswd -h {md5} -s bilgesnipe`
view: localhost:1,2;whatever.com:3,4
admin: whatever.com:1,2
admin: localhost:3,4
superuser: myPiwik:myOtherPiwik;localhost

# USER ENTRY (pwd: enrogue)
dn: cn=Ms Marvel,$BASE_DN
objectClass: top
objectClass: piwikPerson
cn: Ms Marvel
uid: msmarvel
userPassword: `slappasswd -h {md5} -s enrogue`
sn: Danvers

# group entry
dn: cn=avengers,$BASE_DN
cn: avengers
objectClass: groupOfNames
objectClass: top
member: cn=Tony Stark,$BASE_DN
member: cn=Natalia Romanova,$BASE_DN
member: cn=Steve Rodgers,$BASE_DN
member: cn=Thor,$BASE_DN

# another group entry
dn: cn=S.H.I.E.L.D.,$BASE_DN
cn: S.H.I.E.L.D.
objectClass: groupOfNames
objectClass: top
member: cn=Natalia Romanova,$BASE_DN

# USER ENTRY (pwd: cher)
dn: cn=Rogue,$BASE_DN
objectClass: top
objectClass: piwikPerson
cn: Rogue
uid: rogue@xmansion.org
userPassword: `slappasswd -h {md5} -s cher`
sn: Doesnthaveone

EOF

if [ "$?" -eq "0" ]; then
    echo "Added entries."
else
    echo "Failed to add entries."
    echo ""
    echo "slapd log:"
    sudo grep slapd /var/log/syslog

    exit 1
fi

echo ldapsearch -x -D "cn=Tony Stark,$BASE_DN" -w "piedpiper" -b "$BASE_DN" "(uid=ironman)" memberOf
ldapsearch -x -D "cn=Tony Stark,$BASE_DN" -w "piedpiper" -b "$BASE_DN" "(uid=ironman)" memberOf

echo ldapsearch -x -D "cn=$ADMIN_USER,$BASE_DN" -w "$ADMIN_PASS" -b "$BASE_DN" 
ldapsearch -x -D "cn=$ADMIN_USER,$BASE_DN" -w "$ADMIN_PASS" -b "$BASE_DN" 
