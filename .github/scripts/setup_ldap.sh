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
MODULE_PATH="/usr/lib/ldap"
if [ -d "/usr/lib/ldap/modules" ]; then
    MODULE_PATH="/usr/lib/ldap/modules"
fi

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

# modules
dn: cn=module,cn=config
changetype: modify
replace: olcModulePath
olcModulePath: $MODULE_PATH
-
replace: olcModuleLoad
olcModuleLoad: memberof.so
olcModuleLoad: refint.so

EOF

if [ "$?" -ne "0" ]; then
    # 2.6.x may not have cn=module,cn=config; detect or create it
    MODULE_DN=$(sudo ldapsearch -Y EXTERNAL -H ldapi:/// -b cn=config '(objectClass=olcModuleList)' dn | awk '/^dn: /{print $2}' | head -n 1)
    if [ -z "$MODULE_DN" ]; then
        MODULE_DN="cn=module,cn=config"
        sudo ldapadd -Y EXTERNAL -H ldapi:/// <<EOF
dn: $MODULE_DN
objectClass: olcModuleList
cn: module
olcModulePath: $MODULE_PATH
olcModuleLoad: memberof.so
olcModuleLoad: refint.so
EOF
    else
        sudo ldapmodify -Y EXTERNAL -H ldapi:/// <<EOF
dn: $MODULE_DN
changetype: modify
replace: olcModulePath
olcModulePath: $MODULE_PATH
-
replace: olcModuleLoad
olcModuleLoad: memberof.so
olcModuleLoad: refint.so
EOF
    fi

    if [ "$?" -ne "0" ]; then
        echo "Failed to change config modules!"
        echo ""
        echo "slapd log:"
        sudo grep slapd /var/log/syslog

        exit 1
    fi
fi

DB_DN=$(sudo ldapsearch -Y EXTERNAL -H ldapi:/// -b cn=config '(olcDatabase=*)' dn olcDatabase | awk '/^dn: /{dn=$2} /^olcDatabase: .*mdb/{print dn}' | head -n 1)
if [ -z "$DB_DN" ]; then
    DB_DN="olcDatabase={1}mdb,cn=config"
fi

sudo ldapmodify -Y EXTERNAL -H ldapi:/// <<EOF

# database
dn: $DB_DN
changetype: modify
replace: olcRootDN
olcRootDN: cn=$ADMIN_USER,$BASE_DN
-
replace: olcRootPW
olcRootPW: $ADMIN_PASS_HASH
-
replace: olcDbDirectory
olcDbDirectory: /var/lib/ldap
-
replace: olcSuffix
olcSuffix: $BASE_DN
-
replace: olcAccess
olcAccess: {0}to attrs=userPassword,shadowLastChange by self write by dn="cn=$ADMIN_USER,$BASE_DN" write by * auth
olcAccess: {1}to dn.base="" by dn="cn=$ADMIN_USER,$BASE_DN" write by * read
olcAccess: {2}to * by self write by dn="cn=$ADMIN_USER,$BASE_DN" write by * read
-
replace: olcRequires
olcRequires: authc
-
replace: olcLastMod
olcLastMod: TRUE
-
replace: olcDbCheckpoint
olcDbCheckpoint: 512 30
-
replace: olcDbIndex
olcDbIndex: objectClass eq

EOF

if [ "$?" -ne "0" ]; then
    echo "Failed to change config database!"
    echo ""
    echo "slapd log:"
    sudo grep slapd /var/log/syslog

    exit 1
fi

MEMBEROF_DN="olcOverlay={0}memberof,$DB_DN"
if ! sudo ldapsearch -Y EXTERNAL -H ldapi:/// -b "$MEMBEROF_DN" -s base dn > /dev/null 2>&1; then
    sudo ldapadd -Y EXTERNAL -H ldapi:/// <<EOF
dn: $MEMBEROF_DN
objectClass: olcConfig
objectClass: olcMemberOf
objectClass: olcOverlayConfig
objectClass: top
olcOverlay: memberof
EOF
fi

REFINT_DN="olcOverlay={1}refint,$DB_DN"
if ! sudo ldapsearch -Y EXTERNAL -H ldapi:/// -b "$REFINT_DN" -s base dn > /dev/null 2>&1; then
    sudo ldapadd -Y EXTERNAL -H ldapi:/// <<EOF
dn: $REFINT_DN
objectClass: olcConfig
objectClass: olcOverlayConfig
objectClass: olcRefintConfig
objectClass: top
olcOverlay: {1}refint
olcRefintAttribute: memberof member manager owner
EOF
fi

sudo ldapmodify -Y EXTERNAL -H ldapi:/// <<EOF

# first define custom LDAP attributes for Matomo access
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

# USER ENTRY (pwd: piedpiper)
dn: cn=Tony Stark1,$BASE_DN
cn: Tony Stark1
sn: Stark1
givenName: Tony1
objectClass: piwikPerson
objectClass: top
uid: ironman2
userPassword: `slappasswd -h {md5} -s piedpiper`
mobile: 555-555-5556
mail: billionairephilanthropistplayboy2@starkindustries.com
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

# USER ENTRY (pwd: cherry)
dn: cn=Rogue,$BASE_DN
objectClass: top
objectClass: piwikPerson
cn: Rogue
uid: rogue@xmansion.org
userPassword: `slappasswd -h {md5} -s cherry`
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
