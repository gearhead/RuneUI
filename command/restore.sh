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
set -x # echo commands
set +e # continue on errors

/srv/http/command/ui_notify.php 'Working' 'It takes a while, please wait, restart will follow...' 'simplemessage'
/srv/http/command/webradiodb.sh
redis-cli shutdown save
systemctl stop redis udevil ashuffle upmpdcli mpdscribble mpd spotifyd shairport-sync spopd smbd smb nmbd nmb rune_PL_wrk rune_SSM_wrk
## To-Do: make the backup and restore lines match, use some kind of dynamic list, not hard coded
bsdtar -xpf $1 -C / --include var/lib/redis/rune.rdb etc/samba/*.conf etc/mpd.conf mnt/MPD/Webradio/* var/lib/connman/*.config var/lib/mpd/* home/config.txt.diff
systemctl daemon-reload
systemctl start redis
## try to recover changes in the /boot/config.txt
patch -lN /boot/config.txt /home/config.txt.diff
rm -f /home/config.txt.diff
## /srv/http/command/convert_dos_files_to_unix_script.sh fast
hostnm=$( redis-cli get hostname )
hostnm=${hostnm,,}
ohostnm=$( hostnamectl hostname )
if [ "$hostnm" != "$ohostnm" ]; then
    /srv/http/command/ui_notify.php 'Working' "Setting new hostname ($hostnm), please wait for restart and connect with the new hostname, working..." 'simplemessage'
fi
sed -i "s/opcache.enable=./opcache.enable=$( redis-cli get opcache )/" /etc/php/conf.d/opcache.ini
rm -f $1
// delete the lastmpdvolume variable, it will be set back to its default of 40%, save your ears and speakers
redis-cli del lastmpdvolume
/srv/http/db/redis_datastore_setup check
/srv/http/db/redis_acards_details
/srv/http/command/ui_notify.php 'Working' 'Please wait...' 'simplemessage'
/srv/http/command/refresh_ao
/srv/http/command/ui_notify.php 'Working' 'Please wait...' 'simplemessage'
/srv/http/command/post_restore_actions.php
/srv/http/command/ui_notify.php 'Working' 'Almost done...' 'simplemessage'
set +e
count=$( cat /srv/http/app/templates/header.php | grep -c '$this->hostname' )
if [ $count -gt 2 ]
then
    redis-cli set playernamemenu '1'
else
    redis-cli set playernamemenu '0'
fi
redis-cli set dev '0'
redis-cli set debug '0'
/srv/http/command/webradiodb.sh
systemctl stop connman
# clean up the connman cache files, these will be recreated with restart
find /var/lib/connman/* -type d -exec rm -R '{}' \;
rm -r /var/lib/iwd/*
systemctl start connman
/srv/http/command/refresh_nics
/srv/http/command/ui_notify.php 'Restarting now' 'Please wait...' 'simplemessage'
/srv/http/command/rune_shutdown
reboot
#---
#End script
