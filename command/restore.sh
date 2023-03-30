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
#  file: command/restore.sh
#  version: 1.3
#  coder: janui
#  date: October 2020
#
{
# continue on errors
set -x # echo commands
set +e # continue on errors
echo "restore started"

/srv/http/command/ui_notify.php 'Working' 'It takes a while, please wait, restart will follow...' 'simplemessage'
# regenerate webradios
/srv/http/command/webradiodb.sh
# save the passworddate
passworddate=$( redis-cli get passworddate )
# create a reference list of valid redis variables
/srv/http/command/create_redis_ref_list.php
# shutdown redis
redis-cli shutdown save
# stop most rune systemd units
systemctl stop redis udevil ashuffle upmpdcli mpdscribble mpd spotifyd shairport-sync spopd smbd smb nmbd nmb rune_PL_wrk rune_SSM_wrk
# restore the backup
bsdtar -xpf $1 -C / --include var/lib/redis/rune.rdb etc/samba/*.conf etc/mpd.conf mnt/MPD/Webradio/* var/lib/connman/*.config var/lib/mpd/* home/config.txt.diff
# refresh systemd and restart redis
systemctl daemon-reload
systemctl start redis
# try to recover changes in the /boot/config.txt
patch -lN /boot/config.txt /home/config.txt.diff
rm -f /home/config.txt.diff
# delete any redis variables not included in the reference list
#   variable name and type must be valid, otherwise delete
/srv/http/command/work_redis_ref_list.php
## /srv/http/command/convert_dos_files_to_unix_script.sh fast
hostnm=$( redis-cli get hostname )
hostnm=${hostnm,,}
ohostnm=$( hostnamectl hostname )
if [ "$hostnm" != "$ohostnm" ] ; then
    /srv/http/command/ui_notify.php 'Working' "Setting new hostname ($hostnm), please wait for restart and connect with the new hostname, working..." 'permanotice'
fi
sed -i "s/opcache.enable=./opcache.enable=$( redis-cli get opcache )/" /etc/php/conf.d/opcache.ini
rm -f $1
# delete the lastmpdvolume variable, it will be set back to its default of 40%, save your ears and speakers
redis-cli del lastmpdvolume
# generate default values for missing redis variables
/srv/http/db/redis_datastore_setup check
# reset the passworddate
redis-cli set passworddate $passworddate
# regenerate audio card details
/srv/http/db/redis_acards_details
/srv/http/command/ui_notify.php 'Working' 'Please wait...' 'simplemessage'
# refresh audio aoutputs
/srv/http/command/refresh_ao
/srv/http/command/ui_notify.php 'Working' 'Please wait...' 'simplemessage'
# run some php based pot restore actions
/srv/http/command/post_restore_actions.php
/srv/http/command/ui_notify.php 'Working' 'Almost done...' 'simplemessage'
# set various options off, setting them on will validate the new hardware environment, no data will be lost
redis-cli hset spotifyconnect enable '0'
redis-cli hset airplay enable '0'
redis-cli hset dlna enable '0'
# set up the player name in the UI
set +e
count=$( cat /srv/http/app/templates/header.php | grep -c '$this->hostname' )
if [ $count -gt 2 ] ; then
    redis-cli set playernamemenu '1'
else
    redis-cli set playernamemenu '0'
fi
# sev dev mode off
redis-cli set dev '0'
# set debug off
redis-cli set debug '0'
# regenerate webradios
/srv/http/command/webradiodb.sh
# clean up the connman cache files, these will be recreated with restart
#  stopping connman will also terminate network connections
systemctl stop connman
find /var/lib/connman/* -type d -exec rm -R '{}' \;
rm -r /var/lib/iwd/*
# start connman and refresh the network information
systemctl start connman
/srv/http/command/refresh_nics
/srv/http/command/ui_notify.php 'Restarting now' 'Please wait...' 'simplemessage'
# run the shutdown script and reboot
/srv/http/command/rune_shutdown reboot
echo "restore finished"
} > /var/log/runeaudio/restore.log 2>&1
# debug
#} > /home/restore.log 2>&1
reboot
#---
#End script
