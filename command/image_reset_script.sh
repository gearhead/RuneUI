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
#  version: 1.3
#  coder: janui
#  date: October 2020
#
set -x # echo all commands to cli
set +e # continue on errors
cd /home
#
# Image reset script
if [ "$1" == "full" ];
then
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
# set up services and stop them
# systemctl stops after an erroneous entry, use arrays to run through all entries individually
declare -a disable_arr=(ashuffle mpd mpdscribble nmb smb smbd nmbd winbindd winbind udevil upmpdcli hostapd shairport-sync local-browser rune_SSM_wrk rune_PL_wrk dhcpcd php-fpm ntpd bluetooth chronyd cronie plymouth-lite-halt plymouth-lite-reboot plymouth-lite-poweroff plymouth-lite-start bootsplash systemd-resolved local-browser-w rune_shutdown llmnrd)
declare -a enable_arr=(avahi-daemon haveged nginx redis rune_SY_wrk sshd systemd-journald systemd-timesyncd dbus iwd connman bluetooth bluealsa bluealsa-aplay amixer-webui udevil llmnrd)
declare -a stop_arr=(ashuffle mpd spopd nmbd nmb smbd smb winbind winbindd shairport-sync local-browser rune_SSM_wrk rune_PL_wrk rune_SY_wrk upmpdcli bluetooth chronyd systemd-timesyncd cronie udevil bluetooth bluealsa bluealsa-aplay amixer-webui local-browser-w llmnrd)
declare -a mask_arr=(connman-vpn dbus-org.freedesktop.resolve1 systemd-logind systemd-resolved getty@tty1)
declare -a unmask_arr=(systemd-journald)
#
# disable specified services
for i in "${disable_arr[@]}"
do
   systemctl disable "$i"
done
#
# enable specified services
for i in "${enable_arr[@]}"
do
   systemctl enable "$i"
done
# unmask masked services
alreadymasked=$( systemctl list-unit-files --state=masked | grep -i service | cut -f 1 -d " " )
for i in $alreadymasked
do
   systemctl unmask "$i"
done
# mask specified services
for i in "${mask_arr[@]}"
do
   systemctl mask "$i"
done
# unmask specified services
for i in "${unmask_arr[@]}"
do
   systemctl unmask "$i"
done
# for a distribution image disable systemd audit to reduce log files. Switch it on for a development image
if [ "$1" == "full" ];
then
    systemctl mask systemd-journald-audit.socket
else
    systemctl unmask systemd-journald-audit.socket
fi
#
# stop specified services
for i in "${stop_arr[@]}"
do
   systemctl stop "$i"
done
# stop twice, rune_SY_wrk will try to restart some services (e.g. ashuffle)
for i in "${stop_arr[@]}"
do
   systemctl stop "$i"
done
#
# make sure xwindows has stopped
export DISPLAY=:0
xset dpms force off
#
# unmount the local an network devices
umount -Rf /mnt/MPD/NAS/*
umount -Rf /mnt/MPD/USB/*
rmdir /mnt/MPD/NAS/*
rmdir /mnt/MPD/USB/*
#
# set up connman
# delete the file/link at /etc/resolv.conf
rm -f /etc/resolv.conf
# link it to connman's dynamically created resolv.conf
ln -sfT /run/connman/resolv.conf /etc/resolv.conf
#
# remove rerns addons menu (if installed)
systemctl stop addons cronie
systemctl disable addons cronie
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
# remove user files
rm -f /srv/http/.config/debug.*
rm -f /var/lib/mpd/mpd.db
rm -f /var/lib/mpd/mpdstate
rm -rf /root/.*
rm -rf /srv/http/test
rm -rf /mnt/MPD/LocalStorage/*
rm -rf /mnt/MPD/Webradio/*
rm -rf /var/lib/mpd/playlists/*
rm -f /etc/sudoers.d/*
rm -rf /home/*
rm -rf /var/lib/bluetooth/*
rm -f /var/lib/connman/*.service
rm -rf /var/lib/connman/ethernet_*
rm -rf /var/lib/connman/wifi_*
rm -rf /var/lib/connman/bluetooth_*
find /var/lib/iwd/ -type f -exec rm -f {} \;
#
# remove current and historical RuneAudio specific udev rules
# remove the lines for 99-vc4_input.rules and 70-usb-audio.rules after the a couple of image build's
rm -f /etc/udev/rules.d/99-vc4_input.rules
rm -f /etc/udev/rules.d/70-usb-audio.rules
rm -f /etc/udev/rules.d/99-runeaudio.rules
#
# keep the old nic name format (e.g. eth0, eth1, wlan0, wlan1, etc.)
# remove this symlink to enable the new 'predictable' format
ln -sfT /dev/null /etc/udev/rules.d/80-net-setup-link.rules
#
# the standard location of /etc/X11/xorg.conf.d has moved to /usr/share/X11/xorg.conf.d
# copy any existing files to the new location and delete the old location
mkdir -p /usr/share/X11/xorg.conf.d
cp /etc/X11/xorg.conf.d/* /usr/share/X11/xorg.conf.d/
rm -rf /etc/X11/xorg.conf.d
#
# remove the art directory
dirName=$( redis-cli get albumart_image_dir | tr -s / | xargs )
# remove a trailing / if it exists
dirName="${dirName%/}"
rm -rf "$dirName"
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
md5beforeSpotifyd=$( md5sum /srv/http/command/spotifyd-install.sh | xargs | cut -f 1 -d " " )
md5beforeGitignore=$( md5sum /sev/http/.gitignore | xargs | cut -f 1 -d " " )
rm -f /srv/http/command/mpd-watchdog
cd /srv/http/
git config --global core.editor "nano"
git config user.email "any@body.com"
git config user.name "anybody"
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
if [ "$1" == "full" ]; then
    # clear the stash stack
    git stash clear
    git reset HEAD -- .
    git clean -f
fi
cd /home
md5afterThis=$( md5sum $0 | xargs | cut -f 1 -d " " )
md5afterRotate=$( md5sum /srv/http/command/raspi-rotate-install.sh | xargs | cut -f 1 -d " " )
md5afterSpotifyd=$( md5sum /srv/http/command/spotifyd-install.sh | xargs | cut -f 1 -d " " )
md5afterGitignore=$( md5sum /sev/http/.gitignore | xargs | cut -f 1 -d " " )
if [ "$md5beforeThis" != "$md5afterThis" ] || [ "$md5beforeRotate" != "$md5afterRotate" ] || [ "$md5beforeSpotifyd" != "$md5afterSpotifyd" ] || [ "$md5beforeGitignore" != "$md5afterGitignore" ]; then
    set +x
    echo "#######################################################################################"
    echo "## This script or another essential file has been changed during the git pull update ##"
    echo "##                  Exiting! - You need to run this script again!!                   ##"
    echo "##                             -----------------------------------                   ##"
    echo "#######################################################################################"
    exit
fi
#
# remove any git user-names & email
cd /srv/http/
git config user.name ""
git config user.email ""
cd /home
#
# redis reset
redis-cli del AccessPoint
redis-cli del airplay
redis-cli del debugdata
redis-cli del dirble
redis-cli del dlna
redis-cli del first_time
redis-cli del fix_mac
redis-cli del jamendo
redis-cli del local_browser
redis-cli del lyrics
redis-cli del mpdconf
redis-cli del samba
redis-cli del spotify
redis-cli del spotifyconnect
redis-cli del webradios
# remove the redis variables used for:
#   debug (wrk), network configuration (net, mac & nic), usb mounts (usb), disk mounts (mou), random play (random|ashuffle), lyrics (unused variables)
redisvars=$( redis-cli --scan | grep -iE 'wrk|net|mac|nic|usb|mou|random|ashuffle|lyrics' | xargs )
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
# install raspi-rotate, only when chromium is installed
if [ -f "/bin/chromium" ]; then
    /srv/http/command/raspi-rotate-install.sh
fi
#
# install spotifyd
/srv/http/command/spotifyd-install.sh
#
# remove any samba passwords
pdbedit -L | grep -o ^[^:]* | smbpasswd -x
#
# reset root password
echo -e "rune\nrune" | passwd root
#
# make sure that specific users are created
declare -a createusers=(http mpd spotifyd snapserver snapclient shairport-sync upmpdcli bluealsa mpdscribble)
for i in "${createusers[@]}" ; do
    usercnt=$( grep -c "$i" "/etc/passwd" )
    if [ "$usercnt" == "0" ] ; then
        useradd -U -c "$i systemd user" -d /dev/null -s /sbin/nologin "$i"
    fi
done
#
# make sure that specific users are member of the audio group
declare -a audiousers=(http mpd spotifyd snapserver snapclient shairport-sync upmpdcli bluealsa mpdscribble)
for i in "${audiousers[@]}" ; do
    audiocnt=$( groups $i | grep -c audio )
    if [ "$audiocnt" == "0" ] ; then
        usermod -a -G audio $i
    fi
done

#
# reset the service and configuration files to the distribution standard
# the following commands should also be run after a system update or any package updates
rm -f /etc/nginx/nginx.conf
rm -f /etc/samba/*.conf
#rm -f /etc/netctl/*
# copy default settings and services
cp -RTv /srv/http/app/config/defaults/etc/. /etc
cp -RTv /srv/http/app/config/defaults/usr/. /usr
cp -RTv /srv/http/app/config/defaults/var/. /var
# copy config files for xbindkeys & chromium
cp -RTv /srv/http/app/config/defaults/srv/. /srv
# copy a standard config.txt & cmdline.txt
cp -RTv /srv/http/app/config/defaults/boot/. /boot
# make appropriate links
ln -sfT /etc/nginx/nginx-prod.conf /etc/nginx/nginx.conf
ln -sfT /etc/samba/smb-prod.conf /etc/samba/smb.conf
ln -sfT /srv/http/app/libs/vendor/james-heinrich/getid3/getid3 /srv/http/app/libs/vendor/getid3
#
# modify the systemd journal configuration file to use volatile memory (Storage=volatile)
volitileFound=$(grep -c "^\s*Storage=volatile" "/etc/systemd/journald.conf")
if [ "$volitileFound" == "0" ]; then
    # no uncommented line containing 'Storage=volatile' found
    sed -i 's/\[Journal\]/\[Journal\]\nStorage=volatile/' "/etc/systemd/journald.conf"
fi
#
# modify the /etc/php/php.ini file to set opcache.memory_consumption to 32MB (default is 192MB)
if [ -f "/etc/php/php.ini" ]; then
    sed -i '/^opcache.memory_consumption=/c\opcache.memory_consumption=32' /etc/php/php.ini
fi
#
# add waveshare LDC touchscreen overlays, when chromium is installed
if [ -f "/bin/chromium" ]; then
    /srv/http/command/waveshare_install.sh
fi
#
# copy a logo for display in BubbleUpnp via upmpdcli
cp /srv/http/assets/img/favicon-64x64.png /usr/share/upmpdcli/runeaudio.png
chgmod 644 /usr/share/upmpdcli/runeaudio.png
#
# modify all standard .service files which specify the wrong PIDFile location
sed -i 's|.*PIDFile=/var/run.*/|PIDFile=/run/|g' /usr/lib/systemd/system/*.service
# sed -i 's|.*PIDFile=/var/run.*/|PIDFile=/run/|g' /usr/lib/systemd/system/nmb.service
# sed -i 's|.*PIDFile=/var/run.*/|PIDFile=/run/|g' /usr/lib/systemd/system/winbind.service
# sed -i 's|.*User=mpd.*|#User=mpd|g' /usr/lib/systemd/system/mpd.service
#
# some fixes for the ply-image binary location (required for 0.5b)
if [ -e /usr/bin/ply-image ]; then
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
# the 'final' option also removes the dos2unix package
if [ "$1" == "full" ]; then
    /srv/http/command/convert_dos_files_to_unix_script.sh final
else
    /srv/http/command/convert_dos_files_to_unix_script.sh
fi
#
# for a distribution image remove the pacman history. It makes a lot of space free, but that history is useful when developing
if [ "$1" == "full" ]; then
    # remove uglify-js if required
    pacman -Q uglify-js && pacman -Rcsn uglify-js --noconfirm
    # removing dos2unix if required
    pacman -Q dos2unix && pacman -Rcsn dos2unix --noconfirm
    # remove pacman history and no longer installed packages from the package database
    pacman -Sc --noconfirm
    # remove ALL files from the package cache
    # pacman -Scc --noconfirm
    # rank mirrors and refresh repo's
    /srv/http/command/rank_mirrors.sh
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
while [ $i -lt 5 ]; do
    linuxbuilddate=${linuxbuilddate#*[[:space:]]*}
    osdate=$( date -d "$linuxbuilddate" +%Y%m%d )
    if [ $? -eq 0 ]; then
        i="5"
    else
        i=$[$i+1]
    fi
done
osver=$( uname -r | xargs )
buildversion=$( redis-cli get buildversion | xargs )
patchlevel=$( redis-cli get patchlevel | xargs )
release=$( redis-cli get release | xargs )
archarmver=$( uname -msr | xargs )
cd /srv/http/
gitbranch=$( git branch | xargs )
gitbranch=${gitbranch#*[[:space:]]*}
cd /home
if [ $gitbranch = $release ]; then
    experimental="Beta"
else
    experimental="Experimental Beta"
fi
line1="RuneOs: $experimental V$release-gearhead-$osdate"
line2="RuneUI: $gitbranch V$release-$buildversion-$patchlevel"
line3="Hw-env: Raspberry Pi ($archarmver)"
sed -i "s|^RuneOs:.*|$line1|g" /etc/motd
sed -i "s|^RuneUI:.*|$line2|g" /etc/motd
sed -i "s|^Hw-env:.*|$line3|g" /etc/motd
cat /etc/motd
#
# set timezone to -11 hours before GMT - any user adjustment will always go forward
timedatectl set-timezone Pacific/Pago_Pago
redis-cli set timezone 'Pacific/Pago_Pago'
#
# set the Wi-Fi regulatory domain to 00
iw reg set 00
#
# shutdown redis and force a write all in-memory keys to disk (purges any cached values)
sync
redis-cli save
redis-cli shutdown save
sync
#
# unmount rune tmpfs filesystems, empty their mount points and remount (to avoid errors in the startup sequence)
# http-tmp > /srv/http/tmp
rm -r /srv/http/tmp/*
umount http-tmp
rm -r /srv/http/tmp
mkdir /srv/http/tmp
chown http.http /srv/http/tmp
chmod 777 /srv/http/tmp
mount http-tmp
# many of the remaining lines in this section fail! this is not a problem
# rune-logs > /var/log/runeaudio (after shutting down redis! without remount)
rm -r /var/log/runeaudio/*
umount rune-logs
rm -r /var/log/runeaudio
mkdir /var/log/runeaudio
chown root.root /var/log/runeaudio
chmod 777 /var/log/runeaudio
# logs > /var/log
rm -r /var/log/*
umount logs
rm -r /var/log
mkdir /var/log
chown root.root /var/log
chmod 777 /var/log
mount logs
# rune-logs > /var/log/runeaudio (again after logs, with remount)
rm -r /var/log/runeaudio/*
umount rune-logs
rm -r /var/log/runeaudio
mkdir /var/log/runeaudio
chown root.root /var/log/runeaudio
chmod 777 /var/log/runeaudio
mount rune-logs
#
# zero fill the file system if parameter 'full' is selected
# this takes ages to run, but the zipped distribution image will then be very small
if [ "$1" == "full" ]; then
    echo "Zero filling the file system"
    # zero fill the file system
    cd /boot
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
# shutdown & poweroff
shutdown -P now
#---
#End script
