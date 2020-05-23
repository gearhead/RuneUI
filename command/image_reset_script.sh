#!/bin/bash
set -x # echo all commands to cli
set +e # continue on errors
#
# Image reset script
if [ "$1" == "full" ];
then
    echo "Running full cleanup and image initialisation for a distribution image"
else
    echo "Running quick image initialisation"
fi
#---
#Before running the script...
#Connect via Wired ethernet, remove all WiFi profiles
#Dismount all NAS and USB sources, clear all NAS information. Unplug USB sources
#Reset the image using the following commands, some commands may fail (e.g. local-browser not installed), no problem
#
# clean up any no longer valid mounts
udevil clean
#
# set up services and stop them
systemctl unmask systemd-journald
# for a distribution image disable systemd audit to reduce log files. Switch it on for a development image
if [ "$1" == "full" ];
then
    systemctl mask systemd-journald-audit.socket
else
    systemctl unmask systemd-journald-audit.socket
fi
# systemctl stops after an erroneous entry, use an array to run through all entries
declare -a disable_arr=(ashuffle mpd mpdscribble nmb smb smbd nmbd winbindd winbind udevil upmpdcli hostapd shairport-sync local-browser rune_SSM_wrk rune_PL_wrk dhcpcd php-fpm ntpd bluetooth chronyd cronie plymouth-lite-halt plymouth-lite-reboot plymouth-lite-poweroff plymouth-lite-start)
declare -a enable_arr=(avahi-daemon haveged nginx redis rune_SY_wrk sshd systemd-resolved systemd-journald systemd-timesyncd bootsplash dbus)
declare -a stop_arr=(ashuffle mpd spopd nmbd nmb smbd smb winbind winbindd shairport-sync local-browser rune_SSM_wrk rune_PL_wrk rune_SY_wrk upmpdcli bluetooth chronyd systemd-timesyncd cronie udevil)
#
for i in "${disable_arr[@]}"
do
   systemctl disable "$i"
done
#
for i in "${enable_arr[@]}"
do
   systemctl enable "$i"
done
#
for i in "${stop_arr[@]}"
do
   systemctl stop "$i"
done
# stop twice, some services try to restart themselves (e.g. ashuffle)
for i in "${stop_arr[@]}"
do
   systemctl stop "$i"
done

# install raspi-rotate
/var/www/command/raspi-rotate-install.sh
#
# install spotifyd
/var/www/command/spotifyd-install.sh
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
# remove user files and logs
rm -rf /var/log/runeaudio/*
umount /srv/http/tmp
umount /var/log/runeaudio
umount /var/log
rm -f /var/lib/mpd/mpd.db
rm -f /var/lib/mpd/mpdstate
rm -rf /var/log/*
rm -rf /root/.*
rm -rf /var/www/test
rm -rf /mnt/MPD/LocalStorage/*
rm -rf /mnt/MPD/Webradio/*
rm -rf /var/lib/mpd/playlists/*
rm -rf /var/lib/mpd/playlists/RandomPlayPlaylist.m3u
rm -rf /srv/http/tmp
rm -f /etc/sudoers.d/*
rm -rf /home/*
rm -rf /var/lib/bluetooth/*
#
# redis reset
redis-cli del AccessPoint
redis-cli del airplay
redis-cli del dirble
redis-cli del dlna
redis-cli del jamendo
redis-cli del lyrics
redis-cli del mpdconf
redis-cli del nics
redis-cli del samba
redis-cli del spotify
redis-cli del spotifyconnect
redis-cli del usbmounts
redis-cli del debugdata
redis-cli del local_browser
redis-cli del snapserver
php -f /srv/http/db/redis_datastore_setup reset
redis-cli set playerid ""
redis-cli set hwplatformid ""
#
# update local git
rm -f /var/www/command/mpd-watchdog
cd /srv/http/
git config --global core.editor "nano"
git config user.email "any@body.com"
git config user.name "anybody"
git stash
git add .
git stash
git pull --no-edit
git stash
cd /home
#
# remove any git user-names & email
cd /srv/http/
git config user.name ""
git config user.email ""
cd /home
#
# remove any samba passwords
pdbedit -L | grep -o ^[^:]* | smbpasswd -x
#
# reset root password
echo -e "rune\nrune" | passwd root
#
# reset the service and configuration files to the distribution standard
# the following commands should also be run after a system update or any package updates
rm -f /etc/nginx/nginx.conf
rm -f /etc/samba/*.conf
rm -f /etc/netctl/*
# copy default settings and services
cp -Rv /srv/http/app/config/defaults/etc/* /etc
cp -Rv /srv/http/app/config/defaults/usr/* /usr
# copy a standard config.txt
cp -Rv /srv/http/app/config/defaults/boot/* /boot
# make appropriate links
ln -s /etc/nginx/nginx-prod.conf /etc/nginx/nginx.conf
ln -s /etc/samba/smb-prod.conf /etc/samba/smb.conf

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
# some fixes for the ply-image binary location - currently required for 0.5b
if [ -e /usr/bin/ply-image ];
then
    rm /usr/local/bin/ply-image
else
    cp /usr/local/bin/ply-image /usr/bin/ply-image
    rm /usr/local/bin/ply-image
    chmod 755 /usr/bin/ply-image
fi
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
if [ "$1" == "full" ];
then
    pacman -Sc --noconfirm
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
# set timezone to -11 hours before GMT - any user adjustment will always go forward
timedatectl set-timezone Pacific/Pago_Pago
redis-cli set timezone "Pacific/Pago_Pago"
#
# shutdown redis and force a write all in-memory keys to disk (purges any cached values)
redis-cli save
# run the shutdown poweroff script (this will also remove network mounts)
/var/www/command/rune_shutdown poweroff

#
# zero fill the file system if parameter 'full' is selected
# this takes ages to run, but the zipped distribution image will then be very small
if [ "$1" == "full" ];
then
    redis-cli save
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
