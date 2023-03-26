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
if [ "$1" == "full" ] ; then
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
declare -a disable_arr=(ashuffle mpd haveged mpdscribble nmb smb smbd nmbd winbindd winbind udevil upmpdcli hostapd shairport-sync\
    local-browser rune_SSM_wrk rune_PL_wrk cmd_async_queue dhcpcd php-fpm ntpd bt_mon_switch bt_scan_output bluealsa-aplay bluealsa-monitor\
    bluealsa bluetooth-agent bluetoothctl_scan bluetooth chronyd cronie plymouth-lite-halt plymouth-lite-reboot plymouth-lite-poweroff\
    plymouth-lite-start bootsplash systemd-resolved systemd-homed local-browser-w llmnrd upower systemd-networkd rune_shutdown)
declare -a enable_arr=(avahi-daemon nginx redis rune_SY_wrk sshd systemd-journald systemd-timesyncd dbus iwd connman amixer-webui udevil llmnrd)
declare -a stop_arr=(ashuffle mpd spopd nmbd nmb smbd smb winbind winbindd shairport-sync local-browser rune_SSM_wrk rune_PL_wrk rune_SY_wrk\
    cmd_async_queue upmpdcli chronyd systemd-timesyncd systemd-resolved systemd-homed cronie udevil bt_mon_switch bt_scan_output bluealsa-aplay\
    bluealsa-monitor bluealsa bluetooth-agent bluetoothctl_scan bluetooth amixer-webui local-browser-w llmnrd haveged upower systemd-networkd)
declare -a mask_arr=(connman-vpn dbus-org.freedesktop.resolve1 systemd-logind systemd-resolved systemd-homed getty@tty1 haveged upower bluealsa-monitor)
# declare -a mask_arr=(connman-vpn dbus-org.freedesktop.resolve1 systemd-resolved systemd-homed haveged upower) # this one will enable console login
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
# for a distribution image disable systemd audit to reduce log files. Switch it on for a development image
if [ "$1" == "full" ] ; then
    systemctl mask systemd-journald-audit.socket
else
    systemctl unmask systemd-journald-audit.socket
fi
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
# clean up any no longer valid mounts
udevil clean
#
# set up connman
# delete the file/link at /etc/resolv.conf
rm -f /etc/resolv.conf
# symlink it to connman's dynamically created resolv.conf
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
rm -f /var/lib/connman/*.service
rm -rf /var/lib/connman/ethernet_*
rm -rf /var/lib/connman/wifi_*
rm -rf /var/lib/connman/bluetooth_*
find /var/lib/iwd/ -type f -exec rm -f {} \;
#
# remove core dumps
rm /var/lib/systemd/coredump/*.zst
#
# remove current and historical RuneAudio specific udev rules
# remove the lines for 99-vc4_input.rules and 70-usb-audio.rules after the a couple of image build's
rm -f /etc/udev/rules.d/99-vc4_input.rules
rm -f /etc/udev/rules.d/70-usb-audio.rules
rm -f /etc/udev/rules.d/99-a2dp.rules
rm -f /etc/udev/rules.d/99-runeaudio.rules
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
if [ "$1" == "full" ]; then
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
md5afterSpotifyd=$( md5sum /srv/http/command/spotifyd-install.sh | xargs | cut -f 1 -d " " )
md5afterGitignore=$( md5sum /srv/http/.gitignore | xargs | cut -f 1 -d " " )
if [ "$md5beforeThis" != "$md5afterThis" ] || [ "$md5beforeRotate" != "$md5afterRotate" ] || [ "$md5beforeSpotifyd" != "$md5afterSpotifyd" ] || [ "$md5beforeGitignore" != "$md5afterGitignore" ] ; then
    set +x
    echo "#######################################################################################"
    echo "## This script or another essential file has been changed during the git pull update ##"
    echo "##                  Exiting! - You need to run this script again!!                   ##"
    echo "##                             -----------------------------------                   ##"
    echo "#######################################################################################"
    exit
fi
#
# redis reset
# remove the redis variables used for:
#   debug (wrk), network configuration (net, mac & nic), usb mounts (usb), disk mounts (mou), random play (random|ashuffle),
#       lyrics (unused variables), resolv* (unused variables), webradios & webstreaming (web), spotify* (spotify), *mpd* (mpd)
#       airplay (airplay), samba (samba), debugdata (debugdata), locks (lock), first* (first), local* (local), access* (access)
#       dirble* (dirble), dlna* (dlna), jamendo* (jamendo), *queue (queue), cleancache, bluetooth
#   at some time in the future we should delete the whole redis database here
redisvars=$( redis-cli --scan | grep -iE 'wrk|net|mac|nic|usb|mou|random|ashuffle|lyrics|resolv|web|spotify|mpd|airplay|samba|debugdata|lock|first|local|access|dirble|dlna|jamendo|queue|cleancache|bluetooth' | xargs )
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
#
# install spotifyd
/srv/http/command/spotifyd-install.sh
#
# remove any samba passwords
pdbedit -L | grep -o ^[^:]* | smbpasswd -x
#
# reset root password and save the date set
echo -e "rune\nrune" | passwd root
passworddate=$( passwd -S root | cut -d ' ' -f 3 | xargs )
redis-cli set passworddate $passworddate
#
# make sure that Rune-specific users are created
declare -a createusers=(http mpd spotifyd snapserver snapclient shairport-sync upmpdcli bluealsa bluealsa-aplay mpdscribble lirc llmnrd udevil)
for i in "${createusers[@]}" ; do
    usercnt=$( grep -c "$i:" "/etc/passwd" )
    if [ "$usercnt" == "0" ] ; then
        # create the accounts with no password, locked and pointing to the shell /usr/bin/nologin
        useradd -U -c "$i systemd user" -d /dev/null -s /usr/bin/nologin "$i"
    fi
done
#
# make sure that Audio-specific users are member of the audio group
declare -a audiousers=(http mpd spotifyd snapserver snapclient shairport-sync upmpdcli bluealsa bluealsa-aplay mpdscribble)
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
# # some users need root privileges, add the root group
# declare -a rootusers=(rune_worker)
# for i in "${rootusers[@]}" ; do
    # rootcnt=$( groups $i | grep -c root )
    # if [ "$rootcnt" == "0" ] ; then
        # usermod -a -G root $i
    # fi
# done
#
# the spotifyd account needs to have its shell pointing to /usr/bin/bash to be able to run scripts
# also disable logins by locking the account
usermod -L -s /usr/bin/bash spotifyd
#
# seems that there is a known bug in avahi-daemon where it expects group netdev to exist
#   the netdev group is normally created by dhcdbd, which we don't use
#   at some stage in the future the next line can be removed when it is fixed
# add system group netdev if it is not defined
grep -i netdev /etc/group || groupadd --system netdev
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
# copy config files for xbindkeys, luakit, chromium, etc.
cp -RTv /srv/http/app/config/defaults/srv/. /srv
# copy a standard config.txt & cmdline.txt
cp -RTv /srv/http/app/config/defaults/boot/. /boot
# first-time boot version of cmdline.txt is different
cp -f /boot/cmdline.txt.firstboot /boot/cmdline.txt
# modify /boot/config.txt if this is a 'all platform version'
if [ -f "/boot/initramfs-v7-linux.img" ] ; then
    # remove lines containing [pi02] before a line starting with initramfs
    sed -i '/^\[pi02\]/{N;s/\[pi02\]\ninitramfs/initramfs/}' /boot/config.txt
    # remove lines containing [pi0] before a line starting with initramfs
    sed -i '/^\[pi0\]/{N;s/\[pi0\]\ninitramfs/initramfs/}' /boot/config.txt
    # remove lines containing [pi1] before a line starting with initramfs
    sed -i '/^\[pi1\]/{N;s/\[pi1\]\ninitramfs/initramfs/}' /boot/config.txt
    # remove the first line beginning with initramfs and all following lines to end of file
    sed -i '/^initramfs/,$d' /boot/config.txt
    # append the special all platform initramfs lines
    sed -i '$ a [pi02]\ninitramfs initramfs-v7-linux.img followkernel\n[pi0]\ninitramfs initramfs-linux.img followkernel\n[pi1]\ninitramfs initramfs-linux.img followkernel\n[all]\ninitramfs initramfs-v7-linux.img followkernel' /boot/config.txt
fi
# create required directories
mkdir /root/.ssh
mkdir -p /run/bluealsa-monitor
touch /run/bluealsa-monitor/asoundrc
# remove specific files
rm /etc/udev/rules.d/99-runeaudio.rules
rm /etc/udev/rules.d/70-bluealsa.rules
rm /etc/udev/rules.d/90-touchscreen.rules
# make appropriate links
ln -sfT /etc/nginx/nginx-prod.conf /etc/nginx/nginx.conf
ln -sfT /etc/samba/smb-prod.conf /etc/samba/smb.conf
ln -sfT /srv/http/app/libs/vendor/james-heinrich/getid3/getid3 /srv/http/app/libs/vendor/getid3
ln -sfT /etc/default/bluealsa.default /etc/default/bluealsa
#
# modify the systemd journal configuration file to use volatile memory (Storage=volatile)
volitileFound=$(grep -c "^\s*Storage=volatile" "/etc/systemd/journald.conf")
if [ "$volitileFound" == "0" ] ; then
    # no uncommented line containing 'Storage=volatile' found
    sed -i 's/\[Journal\]/\[Journal\]\nStorage=volatile/' "/etc/systemd/journald.conf"
fi
#
# modify the /etc/php/php.ini file to set opcache.memory_consumption to 32MB (default is 192MB)
if [ -f "/etc/php/php.ini" ] ; then
    sed -i '/^opcache.memory_consumption=/c\opcache.memory_consumption=32' /etc/php/php.ini
fi
#
# add waveshare LDC touchscreen overlays, only when xwindows is installed
if [ -f "/bin/xinit" ] ; then
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
# generate locales for a full image reset
if [ "$1" == "full" ] ; then
    locale-gen
fi
#
# for a distribution image remove the pacman history. It makes a lot of space free, but that history is useful when developing
if [ "$1" == "full" ] ; then
    # remove uglify-js if required
    # pacman -Q uglify-js && pacman -Rsn uglify-js --noconfirm
    # removing dos2unix if required
    # pacman -Q dos2unix && pacman -Rsn dos2unix --noconfirm
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
while [ $i -lt 5 ] ; do
    linuxbuilddate=${linuxbuilddate#*[[:space:]]*}
    osdate=$( date -d "$linuxbuilddate" +%Y%m%d )
    if [ $? -eq 0 ] ; then
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
gitbranch=$( git --git-dir=/srv/http/.git branch --show-current | xargs )
if [ "$gitbranch" == "$release" ] ; then
    if [ "${gitbranch:3:1}" == "a" ] ; then
        experimental="Alpha"
    else
        experimental="Beta"
    fi
else
    if [ "${gitbranch:3:1}" == "a" ]; then
        experimental="Experimental Alpha"
    else
        experimental="Experimental Beta"
    fi
fi
if [ "$experimental" == "Beta" ] && [ "${gitbranch:3:1}" == "a" ]; then
    experimental="Alpha"
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
if [ "$1" == "full" ] ; then
    echo "Zero filling the file system"
    # zero fill the file system
    cd /boot
    sync
    cat /dev/zero > zero.file
    rm zero.file
    sync
    cd /
    cat /dev/zero > zero.file
    rm zero.file
    sync
    cd /home
fi
#
# shutdown & poweroff
shutdown -P now
#---
#End script
