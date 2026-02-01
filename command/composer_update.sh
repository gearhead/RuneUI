#!/bin/bash
#
#  Copyright (C) 2013-2014 RuneAudio Team
#  http://www.runeaudio.com
#
#  RuneUI
#  copyright (C) 2013-2014 – Andrea Coiutti (aka ACX) & Simone De Gregori (aka Orion)
#
#  RuneOS
#  copyright (C) 2013-2014 – Simone De Gregori (aka Orion) & Carmelo San Giovanni (aka Um3ggh1U)
#
#  RuneAudio website and logo
#  copyright (C) 2013-2014 – ACX webdesign (Andrea Coiutti)
#
#  This Program is free software; you can redistribute it and/or modify
#  it under the terms of the GNU General Public License as published by
#  the Free Software Foundation; either version 3, or (at your option)
#  any later version.
#
#  This Program is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
#  GNU General Public License for more details.
#
#  You should have received a copy of the GNU General Public License
#  along with RuneAudio; see the file COPYING. If not, see
#  <http://www.gnu.org/licenses/gpl-3.0.txt>.
#
#  file: command/composer_update.sh
#  version: 1.3
#  coder: janui
#  date: September 2020
#
# This file will upgrade composer modules used in RuneAudio
# The target versions of the composer components can be edited below
#
# setup
set +e # continue on errors
# We do all of this in app/libs/
cd /srv/http/app/libs
# Note the getid3 composer files are cloned directly from git hub
# make a copy of the file audioinfo.class.php, this is very important and it is not supplied in the getid3 composer component!
#   there is a reserve copy in /srv/http/.config
if [ -f "/srv/http/app/libs/vendor/getid3/audioinfo.class.php" ] ; then
    cp /srv/http/app/libs/vendor/getid3/audioinfo.class.php /home/audioinfo.class.php
else
    cp /srv/http/.config/audioinfo.class.php /home/audioinfo.class.php
fi
# download and install composer.phar in the directory /srv/http/app/libs/
# these instructions are from here: https://getcomposer.org/download/
if [ -f "composer-setup.php" ] ; then
    rm composer-setup.php
fi
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
# the sha384 checksum of the composer installer is stored here: https://composer.github.io/installer.sig
php -r "if (hash_file('sha384', 'composer-setup.php') === file_get_contents('https://composer.github.io/installer.sig')) { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
php composer-setup.php --install-dir=/srv/http/app/libs
php -r "unlink('composer-setup.php');"
# make copies of the current composer files and delete the originals
cd /srv/http/app/libs
mv /srv/http/app/libs/composer.json /srv/http/app/libs/composer.json.save
mv /srv/http/app/libs/composer.lock /srv/http/app/libs/composer.lock.save
# create the composer file which holds the target versions
#   allow all the packages to upgrade within the major version
#
cat <<EOF >/srv/http/app/libs/composer.json
{
    "require": {
        "league/plates": "3.*.*",
        "evenement/evenement": "3.*.*",
        "guzzlehttp/guzzle": "7.*.*"
    }
}

EOF
# remove the historical composer files if they exist
if [ -f "/srv/http/app/libs/vendor/Zend" ] ; then
    rm -r /srv/http/app/libs/vendor/Zend
fi
if [ -f "/srv/http/app/libs/vendor/ziegler" ] ; then
    rm -r /srv/http/app/libs/vendor/ziegler
fi
# make sure all the files are owned by www-data
chown -R www-data:www-data /srv/http/app/libs/
# install/upgrade the composer components
sudo -u www-data ./composer.phar --with-all-dependencies update
# remove the composer setup script
if [ -f "/home/composer-setup.php" ] ; then
    rm /home/composer-setup.php
fi
# and remove the composer.phar file
if [ -f "/srv/http/app/libs/composer.phar" ] ; then
    rm /srv/http/app/libs/composer.phar
fi
# now download the latest version of getID3 from github, the git hub master version has the latest bug fixes
if [ -d "/home/getID3" ] ; then
    rm -r /home/getID3
fi
mkdir -p /home/getID3
git clone -b master https://github.com/JamesHeinrich/getID3.git/ /home/getID3
# move the getID3 php files to the correct directories
if [ -d "/srv/http/app/libs/vendor/getid3" ] ; then
    rm -r /srv/http/app/libs/vendor/getid3
fi
if [ -e "/srv/http/app/libs/vendor/getid3" ] ; then
    rm -r /srv/http/app/libs/vendor/getid3
fi
mkdir /srv/http/app/libs/vendor/getid3
mv /home/getID3/* /srv/http/app/libs/vendor/getid3
mv /srv/http/app/libs/vendor/getid3/getid3/* /srv/http/app/libs/vendor/getid3
rmdir /srv/http/app/libs/vendor/getid3/getid3
# move the saved version of audioinfo.class.php from /home to the getid3 files directory
mv /home/audioinfo.class.php /srv/http/app/libs/vendor/getid3/audioinfo.class.php
# correct the ownership of the audioinfo.class.php file
chown www-data:www-data /srv/http/app/libs/vendor/getid3/audioinfo.class.php
# remove the downloaded getID3 files
rm -r /home/getID3
# make sure that all files are unix format and have the correct ownerships and protections
/srv/http/command/convert_dos_files_to_unix_script.sh > /dev/null 2>&1
#---
#End script
