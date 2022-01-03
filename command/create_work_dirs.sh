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
#  date: December 2020
#
set -x # echo all commands to cli
set +e # continue on errors
#
# the work directories are created on each start up, most are in the tmpfs memory file system, see /etc/fstab
# backup could be created their, but is as default created in /home
#
# first create and initialise the backup directory specified by redis
#
# get the redis variable and make any duplicate trailing / into a single /
backupDir=$( redis-cli get backup_dir | tr -s / | xargs )
# remove a trailing / if it exists
backupDir="${backupDir%/}"
if [[ "$backupDir" != *"tmp"* ]] && [[ "$backupDir" != *"backup"* ]]; then
    # backupDir must contain 'tmp' or 'backup', it should then never interfere with the Linux or RuneAudio
    # otherwise ste it to default
    backupDir="/home/backup"
fi
# save the backupDir name in redis
redis-cli set backup_dir "$backupDir"
# create the directory , change the owner and privileges and delete its contents(if any)
mkdir -p "$backupDir"
chown -R http.http "$backupDir"
chmod -R 755 "$backupDir"
rm -fR "$backupDir/*"
#
# create and initialise the albumart directory
#
# get the directory name from redis
artDir=$( redis-cli get albumart_image_dir | tr -s / | xargs )
# remove a trailing / if it exists
artDir="${artDir%/}"
mkdir -p "$artDir"
cp "/srv/http/assets/img/cover-default-runeaudio.png" "$artDir/none.png"
cp "/srv/http/assets/img/black.png" "$artDir/black.png"
cp "/srv/http/assets/img/airplay-default.png" "$artDir/airplay.png"
cp "/srv/http/assets/img/spotify-connect-default.png" "$artDir/spotify-connect.png"
cp "/srv/http/assets/img/cover-radio.jpg" "$artDir/radio.png"
chown -R http.http "$artDir"
chmod 755 "$artDir"
chmod -R 644 "$artDir/*"
#
# if the Spotify Connect cache is defined create the directory, normally not set, it uses large amounts of space
#   when set and instructed to use it, it contains the Spotify played music files. Note: it does not clean itself up
#
spotifyConnectCache=$( redis-cli hget spotifyconnect cache_path | tr -s / | xargs )
if [ "$spotifyConnectCache" != "" ]; then
    # remove a trailing / if it exists
    spotifyConnectCache="${spotifyConnectCache%/}"
    mkdir -p "$spotifyConnectCache"
    chown -R spotifyd.spotifyd "$spotifyConnectCache"
    chmod 755 "$spotifyConnectCache"
    chmod -R 644 "$spotifyConnectCache/*"
fi
#
# determine whether album art directory is a tmpfs file system
#
# convert any symlinks to the actual path
artDir=$( readlink -f "$artDir" )
# get all the tmpfs mount points
tmpfsAll=$( df -t tmpfs --output=target | grep '^/' | xargs )
# set the tmpfs switch to false
redis-cli set albumart_image_tmpfs 0
for tmpfs in $tmpfsAll ; do
    # convert any symlinks to the actual path
    tmpfs=$( readlink -f "$tmpfs" )
    if [[ "$artDir" == "$tmpfs"* ]] ; then
        # a tmpfs file path is the first part of the art directory, set the switch to true
        redis-cli set albumart_image_tmpfs 1
    fi
done
#
#---
#End script
