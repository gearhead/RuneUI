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
/srv/http/command/ui_notify.php 'Working' 'Please wait...' 'simplemessage'
# generate Wi-Fi profile files in <p1mountpoint>/wifi for the current Wi-Fi profiles in redis
/srv/http/command/restore_wifi_profiles.php
# save the passworddate
passworddate=$( redis-cli get passworddate )
# save the timezone
timezone=$( redis-cli get timezone )
# save the Wi-Fi regulatory domain
regdom=$( redis-cli get regdom )
# create a reference list of valid redis variables
/srv/http/command/create_redis_ref_list.php
/srv/http/command/ui_notify.php 'Working' 'Please wait...' 'simplemessage'
# shutdown redis
redis-cli shutdown save
systemctl stop redis
# save a copy of the redis database
cp /var/lib/redis/rune.rdb /var/lib/redis/rune.rdb.copy
# stop most rune systemd units, do it in two steps
# step 1 - the back-end jobs
declare -a stop_arr=(bluetoothctl_scan bt_mon_switch bt_scan_output cmd_async_queue nmb nmbd redis rune_MPDEM_wrk rune_PL_wrk rune_SDM_wrk rune_SSM_wrk rune_SY_wrk smb smbd udevil upmpdcli)
for i in "${stop_arr[@]}" ; do
   systemctl stop "$i"
done
# step 2 - the audio jobs
declare -a stop_arr=(ashuffle bluealsa bluealsa-aplay bluealsa-monitor mpd mpdscribble shairport-sync spotifyd)
for i in "${stop_arr[@]}" ; do
   systemctl stop "$i"
done
# restore the backup, do it file for file as some may not exist, non-existant files will cause bsdtar to exit on error with unpredictable results
bsdtar -x -p -f "$1" -C / --include var/lib/redis/rune.rdb
bsdtar -x -p -f "$1" -C / --include etc/samba/*.conf
bsdtar -x -p -f "$1" -C / --include etc/mpd.conf
bsdtar -x -p -f "$1" -C / --include mnt/MPD/Webradio/*
bsdtar -x -p -f "$1" -C / --include var/lib/connman/*.config
bsdtar -x -p -f "$1" -C / --include var/lib/mpd/*
bsdtar -x -p -f "$1" -C / --include home/config.txt.diff
bsdtar -x -p -f "$1" -C / --include home/your-extra-mpd.conf
# refresh systemd and restart redis
systemctl daemon-reload
systemctl start redis
# check redis is running
redis_pid=$( pgrep -x redis-server | xargs )
if [ "$redis_pid" == "" ] ; then
    # redis failed to start, something wrong with the backup
    # restore the redis database copy, clean up and reboot
    cp /var/lib/redis/rune.rdb.copy /var/lib/redis/rune.rdb
    systemctl start redis
    /srv/http/command/ui_notify.php 'Restore' 'Partialy failed, some files have been restored' 'simplemessage'
    # regenerate webradios
    /srv/http/command/webradiodb.sh
    rm -f /home/config.txt.diff
    /srv/http/command/ui_notify.php 'Restore' 'Restarting, please wait...' 'simplemessage'
    sleep 3
    /srv/http/command/rune_shutdown reboot
    reboot
else
    # redis has started, remove the redis database copy and continue
    rm -f /var/lib/redis/rune.rdb.copy
fi
/srv/http/command/ui_notify.php 'Working' 'Please wait...' 'simplemessage'
# try to recover changes in the <p1mountpoint>/config.txt
if [ -f "/home/config.txt.diff" ] ; then
    p1mountpoint=$( redis-cil get p1mountpoint )
    patch -lN $p1mountpoint/config.txt -o $p1mountpoint/config.txt -r /home/config.txt.rej /home/config.txt.diff
    rm -f /home/config.txt.diff
    rm -f /home/config.txt.rej
fi
/srv/http/command/ui_notify.php 'Working' 'Please wait...' 'simplemessage'
# delete any redis variables not included in the reference list
#   variable name and type must be valid, otherwise delete
/srv/http/command/work_redis_ref_list.php
## /srv/http/command/convert_dos_files_to_unix_script.sh fast
hostnm=$( redis-cli get hostname )
hostnm=${hostnm,,}
ohostnm=$( hostnamectl hostname )
if [ "$hostnm" != "$ohostnm" ] ; then
    # reset UI message queueing, this is normally done in rune_SY_wrk, but this job could crash because redis was stopped
    redis-cli set waitSyWrk 0
    /srv/http/command/ui_notify.php 'Working' "Setting new hostname ($hostnm), please wait for restart and connect with the new hostname, working..." 'permanotice'
fi
sed -i "s/opcache.enable=./opcache.enable=$( redis-cli get opcache )/" /etc/php/conf.d/opcache.ini
# delete the restore file
rm -f $1
# delete the lastmpdvolume variable, it will be set back to its default of 40%, save your ears and speakers
redis-cli del lastmpdvolume
# delete the kernel, os, codename and p1mountpoint variables, we may be restoring to a different machine type or a different OS
#   these will be reset to their correct values
redis-cli del kernel
redis-cli del os
redis-cli del codename
redis-cli del p1mountpoint
redis-cli del hdmivc4hw
# generate default values for missing redis variables
/srv/http/db/redis_datastore_setup check
# unset any locks
locks=$( redis-cli --scan --pattern lock_* | sort -u )
for lock in $locks ; do
    redis-cli set "$lock" 0
done
# possibly an old setup value for mpd mixer_type type has been restored, valid values are now 'hardware', 'disabled' and 'hide'
mpdMixer=$( redis-cli hget mpdconf mixer_type )
if [ "$mpdMixer" != "hardware" ] && [ "$mpdMixer" != "disabled" ] && [ "$mpdMixer" != "hide" ] ; then
    # set software to hardware
    redis-cli hset mpdconf mixer_type hardware
fi
# reset UI message queueing (again)
redis-cli set waitSyWrk 0
# reset the passworddate
redis-cli set passworddate "$passworddate"
# reset the timezone
redis-cli set timezone "$timezone"
# reset the Wi-Fi regulatory domain
redis-cli set regdom "$regdom"
# regenerate audio card details
/srv/http/db/redis_acards_details
/srv/http/command/ui_notify.php 'Working' 'Please wait...' 'simplemessage'
# refresh audio outputs
i2smodule=$( redis-cli get i2smodule | xargs )
if [ "$i2smodule" != "" ] ; then
    dtoverlay $i2smodule
fi
/srv/http/command/refresh_ao
/srv/http/command/ui_notify.php 'Working' 'Please wait...' 'simplemessage'
# run some php based post restore actions
/srv/http/command/post_restore_actions.php
/srv/http/command/ui_notify.php 'Working' 'Please wait...' 'simplemessage'
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
# clear the audio configuration to force rebuild on boot
redis-cli del acards
# regenerate webradios
/srv/http/command/webradiodb.sh
/srv/http/command/ui_notify.php 'Working' 'Almost done...' 'simplemessage'
# generate Wi-Fi profile files in <p1mountpoint>/wifi for the restored Wi-Fi profiles in redis
/srv/http/command/restore_wifi_profiles.php
# refresh the nic's
/srv/http/command/ui_notify.php 'Restarting now' 'Please wait...' 'simplemessage'
/srv/http/command/refresh_nics
# run the shutdown script and reboot
/srv/http/command/rune_shutdown reboot
echo "restore finished"
} > /var/log/runeaudio/restore.log 2>&1
# debug
#} > /home/restore.log 2>&1
reboot
#---
#End script
