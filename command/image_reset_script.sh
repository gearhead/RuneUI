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
#  file: command/image_reset_script.sh
#  version: 0.5
#  coder: janui
#  date: October 2020
#
set -x # echo all commands to cli
set +e # continue on errors
cd /home
#
# determine the OS
pacman_cnt=$( find / -maxdepth 3 -name pacman | wc -l | xargs )
apt_cnt=$( find / -maxdepth 3 -name apt | wc -l | xargs )
if [ "$pacman_cnt" == "0" ] && [ "$apt_cnt" != "0" ] ; then
    os="RPiOS"
elif [ "$pacman_cnt" != "0" ] && [ "$apt_cnt" == "0" ] ; then
    os="ARCH"
fi
redis-cli set os $os
#
# determine the codename
if [ "$os" == 'ARCH' ] ; then
    codename=""
elif [ "$os" == 'RPiOS' ] ; then
    codename=$( grep -i VERSION_CODENAME /etc/os-release | cut -d "=" -f 2 | xargs )
fi
redis-cli set codename "$codename"
#
# Image reset script
if [ "$1" == "full" ] || [ "$2" == "full" ] ; then
    echo "Running full cleanup and image initialisation for a distribution image"
else
    echo "Running quick image initialisation"
fi
#---
# Before running the script...
# Connect via Wired ethernet, remove all WiFi profiles
# Dismount all NAS and USB sources, clear all NAS information. Unplug all USB decvices
# Reset the image using the following commands, some commands may fail (e.g. local-browser not installed), no problem
#
#
# clean up any no longer valid mounts
udevil clean
#
# clear Bluetooth cache
# the bluetooth service needs to running in order to use the bluetoothctl command
systemctl start bluetooth
# loop until bluetoothctl gives a non-error response (give up after 20 seconds)
count=3
timeout 5 bluetoothctl devices
until [ $? -eq 0 ] || (( count-- <= 0 )) ; do
    # loop for 3 times to allow the bluetooth service to start
    # echo $count
    # echo $?
    sleep 2
    timeout 5 bluetoothctl devices
done
# now get a list of bluetooth devices
btdevices=$( timeout 5 bluetoothctl devices )
# for each device disconnect and remove (now max 25 seconds since starting the bluetooth sevice)
for i in "$btdevices" ; do
    # echo $i
    btmac=$( echo $i | cut -d ' ' -f 2 )
    # echo $btmac
    if [ "$btmac" != "" ]; then
        bluetoothctl disconnect $btmac
        bluetoothctl remove $btmac
    fi
done
# finally delete the Bluetooth cache
rm -rf /var/lib/bluetooth/*
#
# set up services and stop them
# systemctl sometimes stops after an erroneous entry, use arrays to run through all entries individually
declare -a disable_arr=(ashuffle bluealsa bluealsa-aplay bluealsa-monitor bluetooth bluetooth-agent bluetoothctl_scan\
    bootsplash bt_mon_switch bt_scan_output chronyd cmd_async_queue connman-wait-online cron cronie dhcpcd dphys-swapfile haveged hciuart hostapd llmnrd\
    local-browser local-browser-w mpd mpdscribble nmb nmbd ntpd pcscd php7.4-fpm php8.2-fpm php-fpm plymouth-lite-halt plymouth-lite-poweroff\
    plymouth-lite-reboot plymouth-lite-start redis-server rsyslog rune_PL_wrk rune_shutdown rune_SSM_wrk shairport-sync smartmontools smb smbd\
    systemd-homed systemd-networkd udevil udisks2 upmpdcli upower winbind winbindd)
declare -a enable_arr=(amixer-webui avahi-daemon connman dbus iwd mosquitto mpdversion nginx redis rune_SY_wrk sshd systemd-journald\
    systemd-resolved systemd-timesyncd udevil)
declare -a stop_arr=(amixer-webui ashuffle bluealsa bluealsa-aplay bluealsa-monitor bluetooth bluetooth-agent\
    bluetoothctl_scan bootsplash bt_mon_switch bt_scan_output chronyd cmd_async_queue connman-wait-online cron cronie dhcpcd dphys-swapfile\
    haveged hciuart llmnrd local-browser local-browser-w mosquitto mpd mpdversion nmb nmbd pcscd php7.4-fpm php8.2-fpm php-fpm\
    plymouth-lite-halt plymouth-lite-poweroff plymouth-lite-reboot plymouth-lite-start redis-server rsyslog rune_PL_wrk\
    rune_shutdown rune_SSM_wrk rune_SY_wrk shairport-sync smartmontools smb smbd systemd-homed systemd-networkd systemd-timesyncd udevil udisks2\
    upmpdcli upower winbind winbindd)
if [ "$1" == "consolelogin" ] || [ "$2" == "consolelogin" ] ; then
    declare -a mask_arr=(bluealsa-monitor connman-vpn dhcpcd dphys-swapfile haveged llmnrd php7.4-fpm php8.2-fpm redis-server rsyncd\
        rsyncd@ rsyslog systemd-homed udisks2 upower)
else
    declare -a mask_arr=(bluealsa-monitor connman-vpn dhcpcd dphys-swapfile getty@tty1 haveged llmnrd php7.4-fpm php8.2-fpm redis-server rsyncd\
        rsyncd@ rsyslog systemd-homed udisks2 upower)
fi
declare -a unmask_arr=(systemd-journald)
#
# stop specified services
for i in "${stop_arr[@]}" ; do
   systemctl stop "$i"
done
#
# unmask masked services, do this first otherwise other settings are ignored for masked services
alreadymasked=$( systemctl list-unit-files --state=masked | grep -i service | cut -f 1 -d " " )
for i in $alreadymasked ; do
   systemctl unmask "$i"
done
#
# disable specified services
for i in "${disable_arr[@]}" ; do
   systemctl disable "$i"
done
#
# enable specified services
for i in "${enable_arr[@]}" ; do
   systemctl enable "$i"
done
#
# mask specified services
for i in "${mask_arr[@]}" ; do
   systemctl mask "$i"
done
#
# unmask specified services
for i in "${unmask_arr[@]}" ; do
   systemctl unmask "$i"
done
#
# stop specified services
for i in "${stop_arr[@]}" ; do
   systemctl stop "$i"
done
#
# stop twice, rune_SY_wrk will try to restart some services (e.g. ashuffle)
for i in "${stop_arr[@]}" ; do
   systemctl stop "$i"
done

# make sure xwindows has stopped
export DISPLAY=:0
xset dpms force off
#
# unmount the local an network devices
umount -Rf /mnt/MPD/NAS/*
umount -Rf /mnt/MPD/USB/*
rmdir /mnt/MPD/NAS/*
rmdir /mnt/MPD/USB/*
# clean up any no longer valid mounts
udevil clean
#
# set up connman
# delete the file/link at /etc/resolv.conf
#   Note this section can be removed in the next release
# rm -f /etc/resolv.conf
# symlink it to connman's dynamically created resolv.conf
# ln -sfT /run/connman/resolv.conf /etc/resolv.conf
#
# # fix for removing openresolv
# #   Note this section can be removed in the next version
# if [ "$os" == "RPiOS" ] ; then
    # a=$( apt -qq list openresolv 2> /dev/null | grep -ci installed )
    # if [ "$a" == "0" ] ; then
        # bash -c "apt install -y openresolv >/dev/null 2>&1"
    # fi
    # rm -f /etc/resolv.conf
    # resolvconf -u
    # # bash -c "apt purge -y openresolv >/dev/null 2>&1"
# elif [ "$os" == "ARCH" ] ; then
    # # install openresolv if missing, dont run resolvconf -u
    # pacman -Q openresolv || pacman -Sy openresolv --noconfirm
# fi
#
# remove rerns addons menu (if installed)
systemctl stop addons
systemctl disable addons
rm -f /etc/systemd/system/addons.service
rm -f /etc/sudoers.d/http
rm -f /etc/sudoers.d/http-backup
rm -fr /home/rern
/usr/local/bin/uninstall_addo.sh
rm -f ./install.sh
rm -f /usr/local/bin/uninstall_addo.sh
rm -f /usr/local/bin/uninstall_enha.sh
redis-cli del addons
redis-cli del addo
#
# Make sure cron/cronie is installed for logrotate (not activated)
#   This should be removed in a future release
if [ "$os" == "RPiOS" ] ; then
    a=$( apt -qq list cron 2> /dev/null | grep -ci installed )
    if [ "$a" == "0" ] ; then
        bash -c "apt install -y cron >/dev/null 2>&1"
        systemctl disable cron
        systemctl stop cron
    fi
elif [ "$os" == "ARCH" ] ; then
    # on ARCH cronie provides cron
    pacman -Q cronie || pacman -Sy cronie --noconfirm
    systemctl disable cronie
    systemctl stop cronie
    cp /srv/http/app/config/defaults/logrotate/etc/cron.daily/logrotate /etc/cron.daily/logrotate
fi
#
# remove user files
rm -rf /root/*
rm -f /srv/http/.config/debug.*
rm -f /srv/http/.config/noreboot
rm -f /var/lib/mpd/mpd.db
rm -f /var/lib/mpd/mpdstate
rm -rf /root/.*
rm -rf /srv/http/test
rm -rf /mnt/MPD/LocalStorage/*
rm -rf /mnt/MPD/Webradio/*
rm -rf /mnt/MPD/Webradio/.pls
rm -rf /var/lib/mpd/playlists/*
rm -f /etc/sudoers.d/*
rm -rf /home/*
rm -rf /var/lib/bluetooth/*
#
# remove core dumps
rm /var/lib/systemd/coredump/*.zst
#
# reset the mpd database and state file
systemctl start mpd
mpc stop
mpc clear
mpc rescan
updating=$( mpc | grep -ic 'Updating' )
cnt=7
while [ "$updating" != "0" ] && [ $(( cnt-- )) -gt 0 ] ; do
    sleep 2
    updating=$( mpc | grep -ic 'Updating' )
    (( cnt-- ))
    if [ $cnt -le 0 ] ; then
        break
    fi
done
systemctl stop mpd
#
# keep the old nic name format (e.g. eth0, eth1, wlan0, wlan1, etc.)
# remove this symlink to enable the new 'predictable' format
ln -sfT /dev/null /etc/udev/rules.d/80-net-setup-link.rules
#
# the standard location of /etc/X11/xorg.conf.d has moved to /usr/share/X11/xorg.conf.d
# copy any existing files to the new location and delete the old location
if [ -d "/etc/X11/xorg.conf.d" ] ; then
    if [ ! -d "/usr/share/X11/xorg.conf.d" ] ; then
        mkdir -p /usr/share/X11/xorg.conf.d
    fi
    cp -n /etc/X11/xorg.conf.d/* /usr/share/X11/xorg.conf.d/
    rm -rf /etc/X11/xorg.conf.d
fi
#
# the standard location of /etc/php/fpm.d has moved to /etc/php/php-fpm.d
# copy any existing files to the new location and delete the old location
if [ -d "/etc/php/fpm.d" ] ; then
    if [ ! -d "/etc/php/php-fpm.d" ] ; then
        mkdir -p /etc/php/php-fpm.d
    fi
    cp -n /etc/php/fpm.d/* /etc/php/php-fpm.d/
    rm -rf /etc/php/fpm.d
fi
#
# remove backup work directory and any contents
dirName=$( redis-cli get backup_dir | tr -s / | xargs )
# remove a trailing / if it exists
dirName="${dirName%/}"
rm -rf "$dirName"
#
# remove mac spoofing scripts
rm -f /etc/systemd/system/macfix_*.service
rm -f /etc/systemd/system/multi-user.target.wants/macfix_*.service
#
# remove a problem looping symlink in the vendor files if it exists (created erroneously in a previous version)
rm -f /srv/http/app/libs/vendor/james-heinrich/getid3/getid3/getid3
#
# update local git and clean up any stashes
md5beforeThis=$( md5sum $0 | xargs | cut -f 1 -d " " )
md5beforeRotate=$( md5sum /srv/http/command/raspi-rotate-install.sh | xargs | cut -f 1 -d " " )
# md5beforeSpotifyd=$( md5sum /srv/http/command/spotifyd-install.sh | xargs | cut -f 1 -d " " )
md5beforeGitignore=$( md5sum /srv/http/.gitignore | xargs | cut -f 1 -d " " )
rm -f /srv/http/command/mpd-watchdog
cd /srv/http/
git config --global core.editor "nano"
git config --global pull.rebase false
git config --global --add safe.directory /srv/http
git config --global user.email any@body.com
git config --global user.name "any body"
git config --global pull.rebase false
git config core.editor "nano"
git config pull.rebase false
git config --add safe.directory /srv/http
git config user.email any@body.com
git config user.name "any body"
git config pull.rebase false
git stash
git stash
git add .
git stash
git stash
git pull --no-edit
# the following three lines should not be required
git stash
git stash
git pull --no-edit
if [ "$1" == "full" ] || [ "$2" == "full" ] ; then
    # clear the stash stack
    git stash clear
    git reset HEAD -- .
    git clean -f
fi
git config --global core.editor "nano"
git config --global pull.rebase false
git config --global --add safe.directory /srv/http
git config --global user.email any@body.com
git config --global user.name "any body"
git config --global pull.rebase false
git config core.editor "nano"
git config pull.rebase false
git config --add safe.directory /srv/http
git config user.email any@body.com
git config user.name "any body"
git config pull.rebase false
cd /home
md5afterThis=$( md5sum $0 | xargs | cut -f 1 -d " " )
md5afterRotate=$( md5sum /srv/http/command/raspi-rotate-install.sh | xargs | cut -f 1 -d " " )
# md5afterSpotifyd=$( md5sum /srv/http/command/spotifyd-install.sh | xargs | cut -f 1 -d " " )
md5afterGitignore=$( md5sum /srv/http/.gitignore | xargs | cut -f 1 -d " " )
if [ "$md5beforeThis" != "$md5afterThis" ] || [ "$md5beforeRotate" != "$md5afterRotate" ] || [ "$md5beforeSpotifyd" != "$md5afterSpotifyd" ] || [ "$md5beforeGitignore" != "$md5afterGitignore" ] ; then
    set +x
    echo "#######################################################################################"
    echo "## This script or another essential file has been changed during the git pull update ##"
    echo "##                   Exiting! - You need to run this script again                    ##"
    echo "##                              ---------------------------------                    ##"
    echo "#######################################################################################"
    exit
fi
#
# set up raspberrypi-firmware so that it works from systemd
#   systemd will only look in the $PATH=/usr/local/sbin:/usr/local/bin:/usr/bin when run as root
#   the raspberrypi-firmware utilities are installed in /opt/... directory tree
#   this routine will set up sysmlinks to the routines in /usr/local/bin
#   it looks like a systemd bug, maybe this will not be needed in the future
vcgencmdPath=$( find /opt -name vcgencmd )
if [ "$vcgencmdPath" == "" ] ; then
    if [ ! -f /usr/bin/vcgencmd ] ; then
        set +x
        echo "########################################################################"
        echo "##                 Error: raspberrypi-firmware missing                ##"
        echo "## Exiting! - Install raspberrypi-firmware then run this script again ##"
        echo "##            ----------------------------      --------------------- ##"
        echo "########################################################################"
        exit
    fi
else
    firmwareDir=${vcgencmdPath%/*}
    # echo $firmwareDir
    for fullFilename in $firmwareDir/* ; do
        firmwareName=$(basename ${fullFilename})
        # echo "ln -s $fullFilename /usr/bin/$firmwareName"
        ln -sf "$fullFilename" "/usr/bin/$firmwareName"
    done
fi
#
# redis reset
# remove the redis variables used for:
#   Access Point info (accesspoint), audio cards & hdmi acards (acard), access point (access), airplay (airplay), audio output (ao),
#       global random (ashuffle & random), bluetooth (bluetooth), music metadata caching (cleancache), debug data - historical (debugdata),
#       dirble (dirble), dlna (dlna), first time boot (first), HDMI info (hdmi), jamendo (jamendo), local browser (local),
#       lock indicators (lock_), lyrics (lyric), MAC address (mac), ImageMagick (magick), mounted volume information (mou), MPD, (mpd),
#       network information (net), Network interface card information (nic), batch processing queues (queue),
#       DNS resolve information - historical (resolv), samba server (samba), OS update file md5 stamp (update),
#       USB mounts and status (usb), web streaming (web), debug variables (wrk)
#   at some time in the future we should delete the whole redis database here
#   run in two steps, part 1
redisvars=$( redis-cli --scan | grep -iE 'accesspoint|acard|access|airplay|ao|ashuffle|bluetooth|cleancache|debugdata|dirble|dlna|first|hdmi' | xargs )
for redisvar in $redisvars ; do
    redis-cli del $redisvar
done
#   run in two steps, part 2
redisvars=$( redis-cli --scan | grep -iE 'jamendo|local|lock_|lyric|mac|magick|mou|mpd|net|nic|queue|random|resolv|samba|spotify|update|usb|web|wrk' | xargs )
for redisvar in $redisvars ; do
    redis-cli del $redisvar
done
# run the setup script with parameter reset
php -f /srv/http/db/redis_datastore_setup reset
# refresh the audio card database
php -f /srv/http/db/redis_acards_details
# always clear player ID and hardware platform ID
redis-cli set playerid ""
redis-cli set hwplatformid ""
#
# install raspi-rotate, only when xwindows is installed
if [ -f "/bin/xinit" ] ; then
    /srv/http/command/raspi-rotate-install.sh
fi
# #
# # install spotifyd
# /srv/http/command/spotifyd-install.sh
#
# remove any samba passwords
pdbedit -L | grep -o ^[^:]* | smbpasswd -x
#
# reset root password and save the date set
echo -e "rune\nrune" | passwd root
passworddate=$( chage -l root | grep -i 'Last password change' | cut -d ':' -f 2 | xargs )
redis-cli set passworddate "$passworddate"
#
# make sure that Rune-specific users are created
#   first the user http, this has a specific default account
usercnt=$( grep -c "http:" "/etc/passwd" )
if [ "$usercnt" == "0" ] ; then
    # create the accounts with no password, locked and pointing to the shell /usr/bin/nologin
    useradd -U -c "http webserver user" -d /srv/http -s /usr/bin/nologin "http"
fi
#   now the rest of the users, these are used by systemd
#   note: remove user llmnrd from the list on the next release
declare -a createusers=(mpd spotifyd snapserver snapclient shairport-sync upmpdcli bluealsa mpdscribble lirc llmnrd udevil redis)
for i in "${createusers[@]}" ; do
    usercnt=$( grep -c "^_$i:" "/etc/passwd" )
    if [ "$usercnt" == "1" ] ; then
        userdel -r "_$i"
    fi
    usercnt=$( grep -c "^$i:" "/etc/passwd" )
    if [ "$usercnt" == "0" ] ; then
        # create the accounts with no password, locked and pointing to the shell /usr/bin/nologin
        useradd -U -c "$i systemd user" -d /dev/null -s /usr/bin/nologin "$i"
    else
        usermod -L -s /usr/bin/bash "$i"
    fi
done
#
# make sure that Audio-specific users are member of the audio group
declare -a audiousers=(http mpd spotifyd snapserver snapclient shairport-sync upmpdcli bluealsa mpdscribble)
for i in "${audiousers[@]}" ; do
    audiocnt=$( groups $i | grep -c audio )
    if [ "$audiocnt" == "0" ] ; then
        usermod -a -G audio $i
    fi
done
#
# make sure that Device-specific users are member of the audio, disk, floppy, optical and storage groups
declare -a devusers=(udevil)
declare -a devgroups=(audio disk floppy optical storage)
for i in "${devusers[@]}" ; do
    for j in "${devgroups[@]}" ; do
        devusercnt=$( groups $i | grep -c $j )
        if [ "$devusercnt" == "0" ] ; then
            usermod -a -G $j $i
        fi
    done
done
# #
# # make sure that Video-specific users are member of the video group
# declare -a videousers=(http)
# for i in "${videousers[@]}" ; do
    # videocnt=$( groups $i | grep -c video )
    # if [ "$videocnt" == "0" ] ; then
        # usermod -a -G video $i
    # fi
# done
# #
# # some users need root privileges, add the root group
# declare -a rootusers=(rune_worker)
# for i in "${rootusers[@]}" ; do
    # rootcnt=$( groups $i | grep -c root )
    # if [ "$rootcnt" == "0" ] ; then
        # usermod -a -G root $i
    # fi
# done
# #
# # the spotifyd account needs to have its shell pointing to /usr/bin/bash to be able to run scripts
# # also disable logins by locking the account NOTE: now done above, this can be removed
# usermod -L -s /usr/bin/bash spotifyd
#
# seems that there is a known bug in avahi-daemon where it expects group netdev to exist
#   the netdev group is normally created by dhcdbd, which we don't use
#   at some stage in the future the next line can be removed when it is fixed
# add system group netdev if it is not defined
grep -i netdev /etc/group || groupadd --system netdev
#
# add waveshare LDC touchscreen overlays, only when xwindows is installed
if [ -f "/bin/xinit" ] ; then
    /srv/http/command/waveshare_install.sh
fi
#
# remove the network configuration files, these could contain Wi-Fi passwords
#   for the connman and iwd user files, connman needs to be stopped
systemctl stop connman
# get the networks known to iwd
networks=$( iwctl known-networks list | tail -n +5 | cut -b 6-40 | sed 's/^[[:space:]]*//;s/[[:space:]]*$//' )
# forget each network known to iwd
for i in "$networks" ; do
    # echo "'$i'"
    wctl known-networks "$i" forget
done
# delete the connman configuration files
rm -rf /var/lib/connman/*
# delete the iwd configuration files (there should be none after forgetting the known networks)
rm -rf /var/lib/iwd/*
# copy the default connman config file
cp /srv/http/app/config/defaults/var/lib/connman/* /var/lib/connman/
# start connman which will refresh the network information
systemctl start connman
#
# reset the service and configuration files to the distribution standard
# the following commands should also be run after a system update or any package updates
rm -f /etc/samba/*.conf
#rm -f /etc/netctl/*
# copy default settings and services
cp -RTv /srv/http/app/config/defaults/etc/. /etc
cp -RTv /srv/http/app/config/defaults/usr/. /usr
cp -RTv /srv/http/app/config/defaults/var/. /var
# copy config files for xbindkeys, luakit, chromium, etc.
cp -RTv /srv/http/app/config/defaults/srv/. /srv
# copy a standard config.txt & cmdline.txt
#   note: for RPiOS Bookworm these files have a different location and these will be replaced by symlinks, see below
cp -RTv /srv/http/app/config/defaults/boot/. /boot
# first-time boot version of cmdline.txt is different
cp -f /boot/cmdline.txt.firstboot /boot/cmdline.txt
# generate a default mpd --version file
mpd --version | grep -v '^$' > /srv/http/.config/mpdversion.txt
# create required directories
mkdir /root/.ssh
mkdir -p /run/bluealsa-monitor
touch /run/bluealsa-monitor/asoundrc
# remove specific files
rm /etc/udev/rules.d/99-runeaudio.rules
rm /etc/udev/rules.d/70-bluealsa.rules
rm /etc/udev/rules.d/90-touchscreen.rules
# if certain udev rules files are not present use the defaults, otherwise remove the defaults (these files could be created in the image)
for f in /etc/udev/rules.d/*.default ;  do
    f1=${f::-8}
    if [ -f "$f1" ] ; then
        # the file is present delete the default
        rm "$f"
    else
        # the file is missing rename the default
        mv "$f" "$f1"
    fi
done
# make appropriate links
ln -sfT /etc/samba/smb-prod.conf /etc/samba/smb.conf
ln -sfT /srv/http/app/libs/vendor/james-heinrich/getid3/getid3 /srv/http/app/libs/vendor/getid3
ln -sfT /etc/default/bluealsa.default /etc/default/bluealsa
#
# set op logrotate
if [ "$os" == "ARCH" ] ; then
    cp /srv/http/app/config/defaults/logrotate/etc/cron.daily/logrotate /etc/cron.daily/logrotate
    // more files are requied here
fi
#
# set specific files for ARCH and RPiOS version compatibility
#  python plugin for amixer-webui
pythonPlugin=$( find /usr/lib -name python_plugin.so | wc -w | xargs )
python3Plugin=$( find /usr/lib -name python3_plugin.so | wc -w | xargs )
if [ $pythonPlugin -eq 1 ] && [ $python3Plugin -ne 1 ] ; then
    # echo 'python'
    sed -i '/^plugins = python/c\plugins = python' /srv/http/amixer/amixer-webui.ini
elif [ $pythonPlugin -ne 1 ] && [ $python3Plugin -eq 1 ] ; then
    # echo 'python3'
    sed -i '/^plugins = python/c\plugins = python3' /srv/http/amixer/amixer-webui.ini
fi
#   PHP configuration files differ, all files are distributed, make sure only the required files are in the production directories
#   NOTE: when the PHP version on RPiOS changes this code needs to be changed!!
if [ "$os" == "RPiOS" ] ; then
    php_path=$( find /usr/*bin -name php-fpm* )
    php_exe=$(basename -- "$php_path")
    php_ver=${php_exe:7:10}
    if [ "$php_ver" != "7.4" ] && [ "$php_ver" != "8.2" ] ; then
        set +x
        echo "########################################################################"
        echo "##              Error: PHP version has changed for RPiOS              ##"
        echo "## Exiting! - Supported versions 7.4, 8.2, new version is $php_ver         ##"
        echo "##      This script (image_reset_script.sh) needs to be modified      ##"
        echo "##                   ---------------------              --------      ##"
        echo "########################################################################"
        exit
        # when this error occurs look at all occurrences of the old version number in this script, they will need to be changed
        # also the contents of /srv/http/app/config/defaults/etc/php will need to change
        # and the files /srv/http/app/config/defaults//etc/systemd/system/php-fpm.service* will need to be modified
    fi
fi
for f in /etc/php/*.* ;  do
    if [ "$os" == "ARCH" ] && [ "$f" == "/etc/php/7.4"* ] ; then
        # echo $f
        rm -r "$f"
    elif [ "$os" == "ARCH" ] && [ "$f" == "/etc/php/8.2"* ] ; then
        # echo $f
        rm -r "$f"
    elif [ "$os" == "RPiOS" ] &&  [ "$php_ver" == "7.4" ] && [ ! "$f" == "/etc/php/7.4"* ] ; then
        # echo $f
        rm -r "$f"
    elif [ "$os" == "RPiOS" ] &&  [ "$php_ver" == "8.2" ] && [ ! "$f" == "/etc/php/8.2"* ] ; then
        # echo $f
        rm -r "$f"
    fi
done
#   we also need to delete /etc/php/x.x/fpm/pool.d/www.conf for RPiOS PHP 8.2
#       to-do: make this consistent for all RPiOS PHP versions
if [ "$os" == "RPiOS" ] && [ "$php_ver" == "8.2" ] ; then
    rm /etc/php/8.2/fpm/pool.d/www.conf
fi
#   php-fpm.service
#   NOTE: when the PHP version on RPiOS changes this code needs to be changed and a new version of
#       /srv/http/app/config/defaults/etc/systemd/system/php-fpm.service.RPiOS<v.v> should be created
if [ "$os" == "ARCH" ] ; then
    cp /etc/systemd/system/php-fpm.service.ARCH /etc/systemd/system/php-fpm.service
elif [ "$os" == "RPiOS" ] && [ "$php_ver" == "8.2" ] ; then
    cp /etc/systemd/system/php-fpm.service.RPiOS8.2 /etc/systemd/system/php-fpm.service
elif [ "$os" == "RPiOS" ] && [ "$php_ver" == "7.4" ] ; then
    cp /etc/systemd/system/php-fpm.service.RPiOS7.4 /etc/systemd/system/php-fpm.service
fi
rm /etc/systemd/system/php-fpm.service.ARCH
rm /etc/systemd/system/php-fpm.service.RPiOS7.4
rm /etc/systemd/system/php-fpm.service.RPiOS8.2
# for RPiOS Bookworm the first partition is mounted as /boot/firmware in all other cases it is /boot
if [ "$codename" == "bookworm" ] ; then
    # set up a redis variable to point to the mount point of mmcblk0p1
    redis-cli set p1mountpoint '/boot/firmware'
    # check fstab
    fstab_boot=$( grep -ic '/boot ' /etc/fstab )
    fstab_boot_firmware=$( grep -ic '/boot/firmware ' /etc/fstab )
    if [ "$fstab_boot" == "1" ] && [ "$fstab_boot_firmware" == "0" ] ; then
        # change fstab and remount /dev/mmcblk0p1 at mount point /boot/firmware
        sed -i '/mmcblk0p1/s/\/boot         /\/boot\/firmware/' /etc/fstab
        umount /dev/mmcblk0p1
        mkdir -p /boot/firmware
        mount -t vfat /dev/mmcblk0p1 /boot/firmware
    fi
    cp -RTv /srv/http/app/config/defaults/boot/. /boot/firmware
    cp -f /boot/firmware/cmdline.txt.firstboot /boot/firmware/cmdline.txt
    rm -r /boot/firmware/firmware
    FILES=$( find /srv/http/app/config/defaults/boot/ -maxdepth 1 -type d )
    for f in $FILES ; do
        # create symlinks to directories in /boot to point to /boot/firmware distribution files
        f=${f:35}
        if [ "$f" != "" ] ; then
            ln -sf /boot/firmware/$f /boot/$f
        fi
    done
    FILES=$( find /srv/http/app/config/defaults/boot/ -maxdepth 1 -type f -name *.* )
    for f in $FILES ; do
        # create sysmlinks in /boot to point to /boot/firmware distribution files
        f=${f:35}
        if [ "$f" != "" ] ; then
            ln -sf /boot/firmware/$f /boot/$f
        fi
    done
else
    # set up a redis variable to point to the mount point of mmcblk0p1
    redis-cli set p1mountpoint '/boot'
    # check fstab
    fstab_boot=$( grep -ic '/boot ' /etc/fstab )
    fstab_boot_firmware=$( grep -ic '/boot/firmware ' /etc/fstab )
    if [ "$fstab_boot" == "0" ] && [ "$fstab_boot_firmware" == "1" ] ; then
        # change fstab and remount /dev/mmcblk0p1 at mount point /boot
        sed -i '/mmcblk0p1/s/\/boot\/firmware/\/boot         /' /etc/fstab
        umount /dev/mmcblk0p1
        rm -r /boot/*
        mount -t vfat /dev/mmcblk0p1 /boot
        cp -RTv /srv/http/app/config/defaults/boot/. /boot
        cp -f /boot/cmdline.txt.firstboot /boot/cmdline.txt
    fi
    rm -r /boot/firmware
    rm -f /usr/local/sbin/apt
fi
#
# modify /etc/ssh/sshd_config
#   some distributions support an 'Include' statement some don't,
#       when valid make sure that 'Include /etc/ssh/sshd_config.d/*.conf' is the first line of the file
#   when distributions don't support 'Include' modify the 'sshd_config' directly, but first copy it to 'sshd_config.orig'
#   determine the file name(s)
files=$( find /etc -type f -name sshd_config 2>/dev/null )
#   then for each of the files
for f in $files ; do
    # make a copy of the file if that does not exist
    if [ ! -f "$f.orig" ] ; then
        cp $f $f.orig
    fi
    # first delete any lines containing an 'Include'
    sed -i '/^\s*[;|#]*\s*[I|i]nclude\s*\//d' $f
    if [ "$os" == "" ] ; then
        # Note: this currently does not work for any of the current RuneAudio OS-types
        # use the include in 'sshd_config'
        # add the correct Include line to the file
        sed -i '/Port\s*22/i Include /etc/ssh/sshd_config.d/*.conf\n' $f
    else
        # Note: this is currently used for all of the current RuneAudio OS-types
        # modify 'sshd_config' directly directly
        # in each file comment out all occurrences of 'PermitRootLogin'
        sed  -i '/^\s*PermitRootLogin.*/ s/./# &/' "$f"
        # in each file replace the line with the first occurrence of commented out 'PermitRootLogin' with 'PermitRootLogin yes'
        sed -i '0,/^\s*#*\s*PermitRootLogin/s/\s*#*\s*PermitRootLogin.*/PermitRootLogin yes/' "$f"
        # in each file comment out all occurrences of 'Subsystem      sftp'
        sed  -i '/^\s*Subsystem\s*sftp.*/ s/./# &/' "$f"
        # in each file replace the line with the first occurrence of commented out 'Subsystem      sftp' with 'Subsystem      sftp    internal-sftp'
        sed -i '0,/^\s*#*\s*Subsystem\s*sftp/s/\s*#*\s*Subsystem\s*sftp.*/Subsystem      sftp    internal-sftp/' "$f"
    fi
    # tidy up the file replacing multiple space line by one
    sed -i '/^$/N;/^\n$/D' $f
done
#
# for RPiOS we need to make sure the swapfile is switched off and uninstalled
if [ "$os" == "RPiOS" ] ; then
    dphys-swapfile swapoff
    dphys-swapfile uninstall
fi
#
# set up transparent cursor for Weston / Wayland / luakit
#   get the size of the active cursor pointer file
if [ -f /usr/share/icons/Adwaita/cursors/left_ptr ] ; then
    # get the size of the file
    declare -i left_ptr_size=$( ls -l /usr/share/icons/Adwaita/cursors/left_ptr | xargs | cut -d ' ' -f 5 )
else
    left_ptr_size=0
fi
#   get the size of the saved cursor pointer file
if [ -f /usr/share/icons/Adwaita/cursors/left_ptr.original ] ; then
    # get the size of the file
    declare -i left_ptr_size_orig=$( ls -l /usr/share/icons/Adwaita/cursors/left_ptr.original | xargs | cut -d ' ' -f 5 )
else
    left_ptr_size_orig=0
fi
#   check that we have a correctly saved original left_ptr file
if [ $left_ptr_size -lt 60000 ] && [ $left_ptr_size_orig -lt 60000 ] ; then
    # we don't have an original left_ptr file, use the backup one (there could be a newer on available!!)
    cp /usr/share/icons/Adwaita/cursors/left_ptr.backup /usr/share/icons/Adwaita/cursors/left_ptr
    cp /usr/share/icons/Adwaita/cursors/left_ptr.backup /usr/share/icons/Adwaita/cursors/left_ptr.orig
elif [ $left_ptr_size_orig -lt $left_ptr_size ] ; then
    # the saved original file is smaller than the active file, copy active to saved original
    cp cp /usr/share/icons/Adwaita/cursors/left_ptr /usr/share/icons/Adwaita/cursors/left_ptr.orig
elif [ $left_ptr_size -lt $left_ptr_size_org ] ; then
    cp cp /usr/share/icons/Adwaita/cursors/left_ptr.orig /usr/share/icons/Adwaita/cursors/left_ptr
    # the active file is smaller than the saved original file, copy saved original to active
fi
#   now set the file up as a hidden cursor (default)
cp /usr/share/icons/Adwaita/cursors/left_ptr.transparent /usr/share/icons/Adwaita/cursors/left_ptr
#
# modify the systemd journal configuration file to use volatile memory (Storage=volatile)
volitileFound=$(grep -c "^\s*Storage=volatile" "/etc/systemd/journald.conf")
if [ "$volitileFound" == "0" ] ; then
    # no uncommented line containing 'Storage=volatile' found
    sed -i 's/\[Journal\]/\[Journal\]\nStorage=volatile/' "/etc/systemd/journald.conf"
fi
#
# modify the php.ini and other /etc/php/.../*.ini
#   set 'opcache.memory_consumption=32', the file can be in various places, there could be duplicates0
php_ini_files=$( grep -Ril '^[\s]*opcache.memory_consumption=' /etc/php | grep -i '.ini$' )
for f in $php_ini_files ; do
    # in each file comment out all occurrences of 'opcache.memory_consumption='
    sed  -i '/^[\s]*opcache.memory_consumption=.*/ s/./; &/' "$f"
done
php_ini_files=$( grep -Ril '^\s*;*\s*opcache.memory_consumption=' /etc/php | grep -i '.ini$' )
for f in $php_ini_files ; do
    # in each file replace the line with the first occurrence of commented out 'opcache.memory_consumption=' with 'opcache.memory_consumption=32'
    sed -i '0,/^\s*;*\s*opcache.memory_consumption=/s/\s*;*\s*opcache.memory_consumption=.*/opcache.memory_consumption=32/' "$f"
done
#   set 'pcre.jit=0', the file can be in various places, there could be duplicates
php_ini_files=$( grep -Ril '^[\s]*pcre.jit=' /etc/php | grep -i '.ini$' )
for f in $php_ini_files ; do
    # in each file comment out all occurrences of 'pcre.jit='
    sed  -i '/^[\s]*pcre.jit=.*/ s/./; &/' "$f"
done
php_ini_files=$( grep -Ril '^\s*;*\s*opcache.memory_consumption=' /etc/php | grep -i '.ini$' )
for f in $php_ini_files ; do
    # in each file replace the line with the first occurrence of commented out 'pcre.jit=' with 'pcre.jit=0'
    sed -i '0,/^\s*;*\s*pcre.jit=/s/\s*;*\s*pcre.jit=.*/pcre.jit=0/' "$f"
done
#
# copy a logo for display in BubbleUpnp via upmpdcli
cp /srv/http/assets/img/favicon-64x64.png /usr/share/upmpdcli/runeaudio.png
chgmod 644 /usr/share/upmpdcli/runeaudio.png
#
# modify all standard .service files which specify the wrong PIDFile location
sed -i 's|.*PIDFile=/var/run.*/|PIDFile=/run/|g' /usr/lib/systemd/system/*.service
sed -i 's|.*PIDFile=/var/run.*/|PIDFile=/run/|g' /etc/systemd/system/*.service
#
# some fixes for the ply-image binary location (required for 0.5b)
if [ -e /usr/bin/ply-image ] ; then
    rm /usr/local/bin/ply-image
else
    cp /usr/local/bin/ply-image /usr/bin/ply-image
    rm /usr/local/bin/ply-image
    chmod 755 /usr/bin/ply-image
fi
#
# it is possible that the following line is required to correct a bug in the chromium singelton set up
# singleton processing is used to control multiple windows within a session, we always use a single window
# in full screen mode
# so singleton processing is irrelevant for us, just (un)comment the next line
rm /srv/http/.config/chromium/Singleton*
#
# make sure that all files are unix format and have the correct ownerships and protections
/srv/http/command/convert_dos_files_to_unix_script.sh
# generate locales and missing ssh keys for a full image reset
if [ "$1" == "full" ] || [ "$2" == "full" ] ; then
    locale-gen
    ssh-keygen -A
fi
#
# for a distribution image remove the pacman or apt history. It makes a lot of space free, but that history is useful when developing
if [ "$1" == "full" ] || [ "$2" == "full" ] ; then
    if [ "$os" == "ARCH" ] ; then
        # remove pacman history and no longer installed packages from the package database
        pacman -Sc --noconfirm
        # remove ALL files from the package cache
        # pacman -Scc --noconfirm
        # rank mirrors and refresh repo's
        /srv/http/command/rank_mirrors.sh
    elif [ "$os" == "RPiOS" ] ; then
        apt --fix-broken -y install
        apt autoclean
        apt autoremove
        apt clean
        apt-get clean
    fi
    # remove composer saved files and composer.phar
    rm /srv/http/app/libs/composer.phar
    rm /srv/http/app/libs/*.save
fi
#
# reset systemd services so that any cached files are replaced by the latest ones
systemctl daemon-reload
#
# reset host information (icon-name, chassis and hostname)
hostnamectl --static --transient --pretty set-icon-name multimedia-player
hostnamectl --static --transient --pretty set-chassis embedded
hostnamectl --static --transient --pretty set-hostname runeaudio
#
# clean up /etc/motd
linuxbuilddate=$( uname -v )
i="0"
while [ $i -lt 5 ] ; do
    linuxbuilddate=${linuxbuilddate#*[[:space:]]*}
    osdate=$( date -d "$linuxbuilddate" +%Y%m%d )
    if [ $? -eq 0 ] ; then
        i="5"
    else
        i=$[$i+1]
    fi
done
buildversion=$( redis-cli get buildversion | xargs )
patchlevel=$( redis-cli get patchlevel | xargs )
release=$( redis-cli get release | xargs )
linuxver=$( uname -sr | xargs )
gitbranch=$( git --git-dir=/srv/http/.git branch --show-current | xargs )
if [ "$gitbranch" == "$release" ] ; then
    if [ "${gitbranch:3:1}" == "a" ] ; then
        experimental="Alpha"
    else
        experimental="Beta"
    fi
else
    if [ "${gitbranch:3:1}" == "a" ] ; then
        experimental="Experimental Alpha"
    else
        experimental="Experimental Beta"
    fi
fi
if [ "$experimental" == "Beta" ] && [ "${gitbranch:3:1}" == "a" ]; then
    experimental="Alpha"
fi
if [ "$os" == "RPiOS" ] ; then
    cname="-$codename"
else
    cname = ""
fi
wordlength=$( getconf LONG_BIT | xargs )
wordlength="-$wordlength""bit"
line1="RuneOs: $experimental V$release-gearhead-$osdate"
line2="RuneUI: $gitbranch V$release-$buildversion-$patchlevel"
line3="Hw-env: Raspberry Pi ($linuxver $os$cname$wordlength)"
sed -i "s|^RuneOs:.*|$line1|g" /etc/motd
sed -i "s|^RuneUI:.*|$line2|g" /etc/motd
sed -i "s|^Hw-env:.*|$line3|g" /etc/motd
cat /etc/motd
#
# set timezone to -11 hours before GMT - any user adjustment will always go forward
timedatectl set-timezone Pacific/Pago_Pago
#
# set the Wi-Fi regulatory domain to 00
iw reg set 00
#
# shutdown redis and force a write all in-memory keys to disk (purges any cached values)
sync
redis-cli save
redis-cli shutdown save
systemctl stop redis
sync
#
# unmount the overlay cache filesystem and remove the cache disk partition
#   we need to be very careful with this action, it will only be removed when we are absolutely sure it the
#   partition which needs removing
partitions=$( fdisk /dev/mmcblk0 -l | grep -ic mmcblk0p )
if [ "$partitions" == "3" ] ; then
    # looks like the cache partition has previously been created, first check it
    lines=$( fdisk /dev/mmcblk0 -l | grep -iE 'mmcblk0p3|disk ' | xargs )
    if [[ "$lines" == *" 1G 83 Linux"* ]] ; then
        # the partition size is correct
        tot_sectors=$( sed 's/^.*.bytes, //' <<< "$lines" )
        tot_sectors=$( sed 's/ sectors.*//' <<< "$tot_sectors" )
        end_sector=$( sed 's/^.*.mmcblk0p3 //' <<< "$lines" )
        end_sector=$( echo $end_sector | cut -d ' ' -f 2 )
        reserved_sectors=$(( $tot_sectors-$end_sector ))
        if [ "$reserved_sectors" == "35" ] ; then
            # on creation we reserved 34 free sectors at the end of the disk, so this is the cache partition
            # unmount the overlay
            umount overlay_art_cache
            # unmount the partition
            umount /dev/mmcblk0p3
            # remove the mount point
            rmdir /home/cache
            # first change the partition type to 0 (zero = undefined/empty)
            partprobe /dev/mmcblk0
            lines=$( echo -e "t\n3\n0\np\nw\n" | fdisk /dev/mmcblk0 | grep -iE 'mmcblk0p3| disk ' | xargs )
            # now remove the partition, this sometimes gives errors, but works as required
            partprobe /dev/mmcblk0
            lines=$( echo -e "d\n3\np\nw\n" | fdisk /dev/mmcblk0 | grep -iE 'mmcblk0p3| disk ' | xargs )
            partprobe /dev/mmcblk0
        fi
    fi
 fi
#
# remove the art directory
dirName=$( redis-cli get albumart_image_dir | tr -s / | xargs )
# remove a trailing / if it exists
dirName="${dirName%/}"
rm -rf "$dirName"
#
# unmount rune tmpfs filesystems, empty their mount points and remount (to avoid errors in the startup sequence)
# http-tmp > /srv/http/tmp
rm -r /srv/http/tmp/*
umount http-tmp
rm -r /srv/http/tmp
mkdir /srv/http/tmp
chown http:http /srv/http/tmp
chmod 777 /srv/http/tmp
mount http-tmp
# many of the remaining lines in this section fail! this is not a problem
# rune-logs > /var/log/runeaudio (after shutting down redis! without remount)
rm -r /var/log/runeaudio/*
umount rune-logs
rm -r /var/log/runeaudio
mkdir /var/log/runeaudio
chown root:root /var/log/runeaudio
chmod 777 /var/log/runeaudio
# logs > /var/log
rm -r /var/log/*
umount logs
rm -r /var/log
mkdir /var/log
chown root:root /var/log
chmod 777 /var/log
mount logs
# rune-logs > /var/log/runeaudio (again after logs, with remount)
rm -r /var/log/runeaudio/*
umount rune-logs
rm -r /var/log/runeaudio
mkdir /var/log/runeaudio
chown root:root /var/log/runeaudio
chmod 777 /var/log/runeaudio
mount rune-logs
# http-tmp > /var/log/runeaudio (again after logs, with remount)
rm -r /srv/http/tmp/*
umount http-tmp
rm -r /srv/http/tmp
mkdir /srv/http/tmp
chown root:root /srv/http/tmp
chmod 777 /srv/http/tmp
mount http-tmp
#
# zero fill the file system if parameter 'full' is selected
# this takes ages to run, but the compressed distribution image will then be very small
if [ "$1" == "full" ] || [ "$2" == "full" ] ; then
    echo "Zero filling the file system"
    # zero fill the file system
    if [ "$codename" == "bookworm" ] ; then
        cd /boot/firmware
    else
        cd /boot
    fi
    sync
    cat /dev/zero > zero.file
    sync
    rm zero.file
    sync
    cd /
    cat /dev/zero > zero.file
    sync
    rm zero.file
    sync
    cd /home
fi
#
# remove connman wired network configuration files
#   dont stop connman
rm -rf /var/lib/connman/*
# copy the default connman config file
cp /srv/http/app/config/defaults/var/lib/connman/* /var/lib/connman/
#
# shutdown & poweroff
shutdown -P now
#---
#End script
