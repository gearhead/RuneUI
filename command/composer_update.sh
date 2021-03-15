#!/bin/bash
#
# composer_update.sh
# ------------------------
# This file will upgrade composer modules used in runeaudio
# The target versions of the composer components can be edited below
#
# setup
set +e # continue on errors
#
cd /home
# make a copy of the file /home/audioinfo.class.php
cp /srv/http/app/libs/vendor/james-heinrich/getid3/getid3/audioinfo.class.php /home/audioinfo.class.php
# download and install composer.phar in the directory /srv/http/app/libs/
php -r "unlink('composer-setup.php');"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('sha384', 'composer-setup.php') === '756890a4488ce9024fc62c56153228907f1545c228516cbf63f885e036d37e9a59d27d63f46af1d4d07ee0f76181c7d3') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
# php composer-setup.php
rm /srv/http/app/libs/composer.phar
php composer-setup.php --install-dir=/srv/http/app/libs
# php -r "unlink('composer-setup.php');"
# make copies of the current composer files and delete them
cd /srv/http/app/libs
cp /srv/http/app/libs/composer.json /srv/http/app/libs/composer.json.save
cp /srv/http/app/libs/composer.lock /srv/http/app/libs/composer.lock.save
rm /srv/http/app/libs/composer.json /srv/http/app/libs/composer.lock
# create the composer file which holds the target versions
# notes for 'league of plates':
# the latest version of league of plates (3.4.0) breaks the UI
# the highest version which does not break the UI is 1.2.1.
# at 2.0, some of the UI works but Now playing and queue fail
# at 3.0 all I get is a white page.

cat <<EOF >composer.json
{
    "require": {
        "league/plates": "1.2.*",
        "evenement/evenement": "3.0.*",
        "guzzlehttp/guzzle": "7.2.*",
        "james-heinrich/getid3": "*.*.*"
    }
}

EOF
# remove the getid3 symlink (in old Rune versions of the actual files are here)
rm -r /srv/http/app/libs/vendor/getid3
# remove the historical composer files
rm -r /srv/http/app/libs/vendor/Zend
rm -r /srv/http/app/libs/vendor/ziegler
# install/upgrade the composer components
sudo -u http ./composer.phar --with-all-dependencies update
# create a symlink in the vendor directory pointing to the getid3 files
ln -s /srv/http/app/libs/vendor/james-heinrich/getid3/getid3 /srv/http/app/libs/vendor/getid3
# copy the saved version of audioinfo.class.php from /home to the getid3 files directory
cp /home/audioinfo.class.php /srv/http/app/libs/vendor/james-heinrich/getid3/getid3/audioinfo.class.php
# correct the ownership of the audioinfo.class.php file
chown http.http /srv/http/app/libs/vendor/james-heinrich/getid3/getid3/audioinfo.class.php
# remove the saved version of the audioinfo.class.php file
rm /home/audioinfo.class.php
#---
#End script