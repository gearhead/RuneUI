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
#  file: command/convert_dos_files_to_unix_script.sh
#  version: 1.3
#  coder: janui
#  date: October 2020
#
set -x # echo all commands to cli
set +e # continue on errors
cd /home
#
# parameters:
#   fast > a quick version which just sets the privileges
#   cleanfiles > a full version will also clear trailing whitespace from source files and uglify the javascript files
#
# Convert important files from dos format to unix format script
# Don't run if fast parameter is selected
if [ "$1" != "fast" ] && [ "$2" != "fast" ] && [ "$3" != "fast" ]; then
    #
    # Install dos2unix if required
    pacman -Q dos2unix || pacman -Sy dos2unix --noconfirm
    #
    # Dos2Unix conversion
    # exclude binary files, keep the date, keep the old file name
    #
    # all files in the directory /srv/http/app/config/defaults/ inclusive subdirectories
    # exceptions are /boot/config.txt and /boot/wifi/* these stay in ms-dos format
    cd /srv/http/app/config/defaults
    find /srv/http/app/config/defaults/ -type f -exec dos2unix -k -s -o {} \;
    # all files in /srv/http/assets/js
    cd /srv/http/assets/js
    dos2unix -k -s -o *
    # all files in /srv/http/db
    cd /srv/http/db
    dos2unix -k -s -o *
    # all files in /srv/http/app
    cd /srv/http/app
    dos2unix -k -s -o *
    # all files in /srv/http/app/templates
    cd /srv/http/app/templates
    dos2unix -k -s -o *
    # all files in /srv/http/app/libs except the composer files
    mkdir /tmp/composer
    cp /srv/http/app/libs/composer.* /tmp/composer
    cd /srv/http/app/libs
    dos2unix -k -s -o *
    cp /tmp/composer/* /srv/http/app/libs
    # all files in /srv/http/command
    cd /srv/http/command
    dos2unix -k -s -o *
    # all files in /srv/http
    cd /srv/http
    dos2unix -k -s -o *
    # all files named *.conf in /etc and subdirectories
    cd /home
    find /etc -type f -name *.conf -exec dos2unix -k -s -o {} \;
    # the file /srv/http/assets/css/runeui.css
    dos2unix -k -s -o /srv/http/assets/css/runeui.css
    #
    # Convert leading tabs to 4 spaces in the files
    #
    set +x # echo no commands to cli
    echo "Convert leading tabs to 4 spaces in the files"
    FILES="/srv/http/assets/js/*
    /srv/http/db/*
    /srv/http/app/*
    /srv/http/app/templates/*
    /srv/http/app/libs/*
    /srv/http/command/*
    /srv/http/*
    /srv/http/assets/css/runeui.css"
    shopt -s nullglob
    for f in $FILES
    do
        if [ -d "$f" ] ; then
            continue # its a directory not a file
        fi
        if [[ $string == *"composer"* ]] ; then
            continue #skip composer files
        fi
        numltabs=$(grep -Pc "^\t" "$f")
        numlspacetabs=$(grep -Pc "^ *.\t" "$f")
        if [ "$numltabs" == "0" ] && [ "$numlspacetabs" == "0" ] ; then
            continue # no leading tab(s) or space(s) then tab(s) in the file
        fi
        expand -i -t4 "$f" > /home/file.temp
        cp /home/file.temp "$f"
        rm /home/file.temp
        echo "Tabs to spaces: $f"
    done
fi
#
# When requested, remove trailing whitespace in lines from bin/bash files, but exclude vendor files
#
if [ "$1" == "cleanfiles" ] || [ "$2" == "cleanfiles" ] || [ "$3" == "cleanfiles" ]; then
    echo "Removing trailing whitespace from bin/bash files"
    FILES=$(grep -lr '^#!/bin/bash' /srv/http | grep -v '/vendor/')
    for f in $FILES
    do
        if [ -d "$f" ] ; then
            continue # its a directory not a file
        fi
        numstrpace=$(grep -c '[[:blank:]]$' "$f")
        if [ "$numstrpace" == "0" ] ; then
            continue # no trailing whitespace in the file
        fi
        echo "Trailing whitespace bin/bash: $f"
        sed -i 's/[ \t]*$//' "$f"
    done
fi
#
# When requested, remove trailing whitespace from php files, but exclude vendor files
#
if [ "$1" == "cleanfiles" ] || [ "$2" == "cleanfiles" ] || [ "$3" == "cleanfiles" ]; then
    echo "Removing trailing whitespace from php files"
    FILES=$(grep -lr '^<?php' /srv/http | grep -v '/vendor/')
    for f in $FILES
    do
        if [ -d "$f" ] ; then
            continue # its a directory not a file
        fi
        numstrpace=$(grep -c '[[:blank:]]$' "$f")
        if [ "$numstrpace" == "0" ] ; then
            continue # no trailing whitespace in the file
        fi
        echo "Trailing whitespace php: $f"
        sed -i 's/[ \t]*$//' "$f"
    done
fi
#
# When requested, remove trailing whitespace from specific directory's and files
#
if [ "$1" == "cleanfiles" ] || [ "$2" == "cleanfiles" ] || [ "$3" == "cleanfiles" ]; then
    echo "Removing trailing whitespace from /srv/http/app/templates/* files"
    FILES="/srv/http/assets/css/* /srv/http/assets/js/* /srv/http/app/templates/*"
    for f in $FILES
    do
        if [ -d "$f" ] ; then
            continue # its a directory not a file
        fi
        numstrpace=$(grep -c '[[:blank:]]$' "$f")
        if [ "$numstrpace" == "0" ] ; then
            continue # no trailing whitespace in the file
        fi
        echo "Trailing whitespace rest: $f"
        sed -i 's/[ \t]*$//' "$f"
    done
fi
set -x # echo all commands to cli
#
# When requested, use uglifyjs to compress and mangle runeui.js
#
if [ "$1" == "cleanfiles" ] || [ "$2" == "cleanfiles" ] || [ "$3" == "cleanfiles" ]; then
    # Install uglify-js if required
    pacman -Q uglify-js || pacman -Sy uglify-js --noconfirm
    cd /srv/http/
    uglifyjs --verbose --mangle --warn --validate --webkit --ie8 assets/js/runeui.js --output assets/js/runeui.min.js
    cd /home
fi
#
# Check file protections and ownership
#
# art cache needs to be cleaned and dismounted first
/srv/http/command/clean_music_metadata_async.php
sync
umount overlay_art_cache
sync
# now change the permissions of the UI files
chown -R http:http /srv/http/
find /srv/http/ -type f -exec chmod 644 {} \;
find /srv/http/ -type d -exec chmod 755 {} \;
find /etc -type f -name *.conf -exec chmod 644 {} \;
find /etc/systemd/system -type f -name *.service -exec chmod 644 {} \;
chmod 644 /etc/nginx/html/50x.html
chmod 777 /run
chmod 755 /srv/http/command/*
chmod 755 /srv/http/db/*
# remount art cache
set +x # echo no commands to cli
sync
/srv/http/command/create_work_dirs.sh
set -x # echo all commands to cli
# chmod 755 /srv/http/db/redis_datastore_setup
# chmod 755 /srv/http/db/redis_acards_details
chmod 755 /srv/http/app/config/config.php
chmod -R 755 /etc/X11/xinit/
chown mpd:audio /mnt/MPD/*
chown mpd:audio /mnt/MPD/USB/*
chmod 777 /mnt/MPD/USB
chmod 777 /mnt/MPD/USB/*
chown -R mpd:audio /var/lib/mpd
chmod 440 /etc/sudoers
chmod -R 440 /etc/sudoers.d
# corrections for previously erroneously setting /usr to 755
chmod -R -x /usr/lib/systemd/system/*
chmod -R -x /usr/lib/systemd/network/*
chmod -R -x /usr/lib/udev/rules.d/*
# udevil will fail when it is not explicitly given system privileges
chmod +s /usr/bin/udevil
# luakit will fail to start when it cant read its recovery session (luakit runs as http)
chmod 666 /etc/xdg/luakit/rc.lua
chown http:http /etc/xdg/luakit/rc.lua
#---
#End script
