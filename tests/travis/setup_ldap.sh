#!/bin/bash

# install LDAP
echo "Installing LDAP..."
if ! sudo apt-get install slapd ldap-utils -y -qq > /dev/null; then
    echo "Failed to install OpenLDAP!"
fi

# configure LDAP
echo ""
echo "Configuring LDAP..."

mkdir -p $TRAVIS_BUILD_DIR/ldap
sudo chmod -R 777 $TRAVIS_BUILD_DIR/ldap

ADMIN_USER=fury
ADMIN_PASS=secrets
ADMIN_PASS_HASH=`slappasswd -h {md5} -s $ADMIN_PASS`
BASE_DN="dc=avengers,dc=shield,dc=org"

sudo ldapmodify -Y EXTERNAL -H ldapi:/// <<EOF

dn: cn=config
replace: olcLogLevel
olcLogLevel: -1

EOF

sudo ldapadd -Y EXTERNAL -H ldapi:/// <<EOF

# database
dn: olcDatabase={2}hdb,cn=config
objectClass: olcDatabaseConfig
objectClass: olcHdbConfig
olcDatabase: {2}hdb
olcRootDN: cn=$ADMIN_USER,$BASE_DN
olcRootPW: $ADMIN_PASS_HASH
olcDbDirectory: $TRAVIS_BUILD_DIR/ldap
olcSuffix: $BASE_DN
olcAccess: {0}to attrs=userPassword,shadowLastChange by self write by anonymous auth by dn="cn=$ADMIN_USER,$BASE_DN" write by * none
olcAccess: {1}to dn.base="" by * read
olcAccess: {2}to * by self write by dn="cn=$ADMIN_USER,$BASE_DN" write by * read
olcLastMod: TRUE
olcDbCheckpoint: 512 30
olcDbConfig: {0}set_cachesize 0 2097152 0
olcDbConfig: {1}set_lk_max_objects 1500
olcDbConfig: {2}set_lk_max_locks 1500
olcDbConfig: {3}set_lk_max_lockers 1500
olcDbIndex: objectClass eq

EOF

sudo ldapadd -Y EXTERNAL -H ldapi:/// <<EOF

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

if [ "$?" -eq "0" ]; then
    echo "Configured."
else
    echo "Failed to configure ldap."
    echo ""
    echo "slapd log:"
    sudo grep slapd /var/log/syslog

    exit 1
fi

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
objectClass: inetOrgPerson
objectClass: top
uid: ironman
userPassword: `slappasswd -h {md5} -s piedpiper`
mobile: 555-555-5555
mail: billionairephilanthropistplayboy@starkindustries.com

# USER ENTRY (pwd: redledger)
dn: cn=Natalia Romanova,$BASE_DN
cn: Natalia Romanova
objectClass: top
objectClass: inetOrgPerson
sn: Romanova
uid: blackwidow
userPassword: `slappasswd -h {md5} -s redledger`
mobile: none

# USER ENTRY (pwd: thaifood)
dn: cn=Steve Rodgers,$BASE_DN
cn: Steve Rodgers
objectClass: top
objectClass: inetOrgPerson
sn: Rodgers
uid: captainamerica
userPassword: `slappasswd -h {md5} -s thaifood`
mobile: 123-456-7890
mail: srodgers@aol.com

# group entry
dn: cn=avengers,$BASE_DN
cn: avengers
objectClass: groupOfNames
objectClass: top
member: cn=Tony Stark,$BASE_DN
member: cn=Natalia Romanova,$BASE_DN
member: cn=Steve Rodgers,$BASE_DN

# another group entry
dn: cn=S.H.I.E.L.D.,$BASE_DN
cn: S.H.I.E.L.D.
objectClass: groupOfNames
objectClass: top
member: cn=Natalia Romanova,$BASE_DN

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