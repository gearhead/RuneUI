#!/bin/bash
#
# Reworking of webradio
# Originally created by Rern, December 2020
# See https://github.com/rern/rAudio-addons/tree/main/webradio
#
#  If and where applicable, not infringing on any original copyright:
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
#  file: command/webradiodb.sh
#  version: 1.3
#  coder: janui
#  date: June 2021
#
# Purpose:
# Imports webradios from <p1mountpoint>/webradios (see: <p1mountpoint>/webradios/readme) and performs a two way synchronisation
#   of the redis webradio database and files in the MPD webradio directory
#
#set -x # echo all commands to cli
set +x # don't echo commands to cli
set +e # continue on errors
#
webradiodir="/mnt/MPD/Webradio"
p1mountpoint=$( redis-cli get p1mountpoint )
#
# if the directory <p1mountpoint>/webradios contains *.pls files move them to the webradio directory and delete them
find "$p1mountpoint/webradios" -type f -name '*.pls' -exec mv -fn -- '{}' "$webradiodir/" \;
# when the files already exist in $webradiodir the file will not be moved, the next line deletes what is left
find "$p1mountpoint/webradios" -type f -name '*.pls' -exec rm -- '{}' \;
# remove any empty directories from <p1mountpoint>/webradios
#   nested directories could need several passes, this routine is run on each boot
find "$p1mountpoint/webradios/" -type d -exec rmdir '{}' &> /dev/null \;
# recreate the structure and instructions
if [ ! -d "$p1mountpoint/webradios" ]; then
    mkdir -p $p1mountpoint/webradios
fi
if [ ! -f "$p1mountpoint/webradios/readme" ]; then
    cp /srv/http/app/config/defaults/boot/webradios/readme $p1mountpoint/webradios/readme
fi
#
# create webradio files when they are defined in redis but the file does not exist
redis-cli hkeys webradios > /tmp/radios
while IFS= read -r webradio; do
    if [ -z "${webradio// }" ]; then
        # empty, skip
        continue
    fi
    if [ ! -f "$webradiodir/$webradio.pls" ]; then
        # file is not there create it
        url=$( redis-cli hget webradios "$webradio" )
        if [ -z "${url// }" ]; then
            # empty, skip
            continue
        fi
        echo -e "[playlist]\nNumberOfEntries=1\nFile1=$url\nTitle1=$webradio\n" > "$webradiodir/$webradio.pls"
    fi
done < "/tmp/radios"
rm /tmp/radios
#
# if sub directories
# -mindepth 2 = in sub directories && -type f = file
find "$webradiodir" -mindepth 2 -type f -name '*.pls' -exec mv -f -- '{}' "$webradiodir/" \;
# remove empty subdirectories
find "$webradiodir/*" -type d -exec rmdir {} &> /dev/null \;

# clear the redis database
redis-cli del webradios > /dev/null

# add data from files to redis database
for file in $webradiodir/*.pls; do
    if [ "$file" != "$webradiodir/*.pls" ] ; then
        name=$( basename "$file" )
        name=${name%.*}
        url=$( grep -h 'File1' "$file" | cut -d '=' -f2 )
        if [ "$name" != "" ] && [ "$url" != "" ]; then
            redis-cli hset webradios "$name" "$url" > /dev/null
            echo "Added - Name: $name, URL: $url"
        else
            echo "Invalid content in file: $webradiodir/$name.pls"
        fi
    else
        echo "No webradios found"
        break
    fi
done

# refresh list
mpc update Webradio > /dev/null
#---
#End script
