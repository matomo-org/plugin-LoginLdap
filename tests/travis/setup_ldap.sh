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
ADMIN_PASS_HASH=`slappasswd -s $ADMIN_PASS`
BASE_DN="dc=avengers,dc=shield,dc=org"

sudo ldapadd -Y EXTERNAL -H ldapi:/// <<EOF

# database
dn: olcDatabase={3}hdb,cn=config
objectClass: olcDatabaseConfig
objectClass: olcHdbConfig
olcDatabase: {3}hdb
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

if [ "$?" -eq "0" ]; then
    echo "Configured."
else
    echo "Failed to configure ldap."
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

# group entry
dn: cn=avengers,$BASE_DN
gidNumber: 500
cn: avengers
objectClass: posixGroup
objectClass: top

# USER ENTRY (pwd: piedpiper)
dn: cn=Tony Stark,$BASE_DN
cn: Tony Stark
sn: Stark
objectClass: inetOrgPerson
objectClass: posixAccount
objectClass: top
uid: ironman
userPassword: `slappasswd -s piedpiper`
gidNumber: 500
uidNumber: 1000
homeDirectory: /home/ironman/

# USER ENTRY (pwd: redledger)
dn: cn=Natalia Romanova,$BASE_DN
cn: Natalia Romanova
objectClass: top
objectClass: inetOrgPerson
objectClass: posixAccount
sn: Romanova
uid: blackwidow
userPassword: `slappasswd -s redledger`
gidNumber: 500
uidNumber: 1001
homeDirectory: /home/blackwidow/

# USER ENTRY (pwd: thaifood)
dn: cn=Steve Rodgers,$BASE_DN
cn: Steve Rodgers
objectClass: top
objectClass: inetOrgPerson
objectClass: posixAccount
sn: Rodgers
uid: captainamerica
userPassword: `slappasswd -s thaifood`
gidNumber: 500
uidNumber: 1002
homeDirectory: /home/captainamerica/

EOF

if [ "$?" -eq "0" ]; then
    echo "Added entries."
else
    echo "Failed to add entries."
    exit 1
fi

echo ldapsearch -x -D "cn=Tony Stark,$BASE_DN" -w "piedpiper" -b "$BASE_DN"
ldapsearch -x -D "cn=Tony Stark,$BASE_DN" -w "piedpiper" -b "$BASE_DN"