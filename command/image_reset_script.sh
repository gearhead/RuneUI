#!/bin/bash
set -x # echo all commands to cli
set +e # continue on errors
#Image reset script

#Use the next line only for a distribution build, do not use on development versions!!! It clears the pacman history, makes a lot of space free, but that history is useful.
if [ "$1" == "full" ]; then
	echo "Running full cleanup and image initialisation"
	echo "Removing pacman cache"
	pacman -Sc --noconfirm
else
	echo "Running quick image initialisation"
fi
#---
#Before running the script...
#Connect via Wired ethernet, remove all WiFi profiles.
#Dismount all NAS and USB sources, clear all NAS information. Unplug USB sources.
#Reset the image using the following commands, some commands may fail (e.g. local-browser not installed), no problem.
#
# clean up any no longer valid mounts
udevil clean
#
# set up services and stop them
systemctl unmask systemd-journald
systemctl disable ashuffle mpd mpdscribble nmbd smbd udevil upmpdcli hostapd shairport-sync local-browser rune_SSM_wrk rune_PL_wrk dhcpcd systemd-timesyncd php-fpm ntpd bluetooth chronyd bootsplash cronie
systemctl enable avahi-daemon haveged nginx redis rune_SY_wrk sshd systemd-resolved systemd-journald systemd-timesyncd
systemctl stop ashuffle mpd spopd smbd nmbd shairport-sync local-browser rune_SSM_wrk rune_PL_wrk rune_SY_wrk upmpdcli bluetooth chronyd systemd-timesyncd cronie
#
# install raspi-rotate
/var/www/command/raspi-rotate-install.sh
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
git stash
git add .
git pull
git config user.email "any@body.com"
git config user.name "anybody"
git stash
git add .
git pull
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
# The following commands should also be run after a system update or any package updates
# Reset the service and configuration files to the distribution standard
cp /var/www/app/config/defaults/avahi-daemon.conf /etc/avahi/avahi-daemon.conf
cp /var/www/app/config/defaults/chrony.conf /etc/chrony.conf
cp /var/www/app/config/defaults/hostapd.conf /etc/hostapd/hostapd.conf
cp /var/www/app/config/defaults/journald.conf /etc/systemd/journald.conf
cp /var/www/app/config/defaults/lircd.conf /etc/conf.d/lircd.conf
cp /var/www/app/config/defaults/lirc_options.conf /etc/lirc/lirc_options.conf
cp /var/www/app/config/defaults/mpdscribble.conf /etc/mpdscribble.conf
rm -f /etc/nginx/nginx.conf
cp /var/www/app/config/defaults/nginx-prod.conf /etc/nginx/nginx-prod.conf
ln -s /etc/nginx/nginx-prod.conf /etc/nginx/nginx.conf
cp /var/www/app/config/defaults/50x.html /etc/nginx/html/50x.html
cp /var/www/app/config/defaults/nsswitch.conf /etc/nsswitch.conf
cp /var/www/app/config/defaults/php-fpm.conf /etc/php/php-fpm.conf
cp /var/www/app/config/defaults/redis.conf /etc/redis.conf
cp /var/www/app/config/defaults/shairport-sync.conf /etc/shairport-sync.conf
rm -f /etc/samba/*.conf
cp /var/www/app/config/defaults/smb-dev.conf /etc/samba/smb-dev.conf
cp /var/www/app/config/defaults/smb-prod.conf /etc/samba/smb-prod.conf
ln -s /etc/samba/smb-prod.conf /etc/samba/smb.conf
cp /var/www/app/config/defaults/spopd.conf /etc/spop/spopd.conf
cp /var/www/app/config/defaults/timesyncd.conf /etc/systemd/timesyncd.conf
cp /var/www/app/config/defaults/upmpdcli.conf /etc/upmpdcli.conf
cp /var/www/app/config/defaults/wpa_supplicant.conf /etc/wpa_supplicant/wpa_supplicant.conf
cp /var/www/app/config/defaults/fstab /etc/fstab
cp /var/www/app/config/defaults/hosts /etc/hosts
cp /var/www/app/config/defaults/irexec.service /usr/lib/systemd/system/irexec.service
cp /var/www/app/config/defaults/start_chromium.sh /etc/X11/xinit/start_chromium.sh
cp /var/www/app/config/defaults/ashuffle.service /usr/lib/systemd/system/ashuffle.service
cp /var/www/app/config/defaults/avahi_runeaudio.service /etc/avahi/services/runeaudio.service
cp /var/www/app/config/defaults/local-browser.service /usr/lib/systemd/system/local-browser.service
cp /var/www/app/config/defaults/php-fpm.service /usr/lib/systemd/system/php-fpm.service
cp /var/www/app/config/defaults/redis.service /usr/lib/systemd/system/redis.service
cp /var/www/app/config/defaults/rune_PL_wrk.service /usr/lib/systemd/system/rune_PL_wrk.service
cp /var/www/app/config/defaults/rune_SSM_wrk.service /usr/lib/systemd/system/rune_SSM_wrk.service
cp /var/www/app/config/defaults/rune_SY_wrk.service /usr/lib/systemd/system/rune_SY_wrk.service
cp /var/www/app/config/defaults/shairport-sync.service /usr/lib/systemd/system/shairport-sync.service
cp /var/www/app/config/defaults/spopd.service /usr/lib/systemd/system/spopd.service
cp /var/www/app/config/defaults/udevil.service /usr/lib/systemd/system/udevil.service
cp /var/www/app/config/defaults/upmpdcli.service /usr/lib/systemd/system/upmpdcli.service
#
# network
rm -f /etc/netctl/*
cp /var/www/app/config/defaults/eth0 /etc/netctl/eth0
cp /var/www/app/config/defaults/test /etc/netctl/test
#
# copy a standard config.txt
cp /var/www/app/config/defaults/config.txt /boot/config.txt
#
# remove logs, run poweroff script and remove mounts
rm -rf /var/log/runeaudio/*
/var/www/command/rune_shutdown poweroff
rm -rf /mnt/MPD/NAS/*
#
# file protection and ownership
# (the find commands are slow but work with spaces in file-names, required for chrome/xorg)
chown -R http.http /srv/http/
find /srv/http/ -type f -exec chmod 644 {} \;
find /srv/http/ -type d -exec chmod 755 {} \;
find /etc -name *.conf -exec chmod 644 {} \;
find /usr/lib/systemd/system -name *.service -exec chmod 644 {} \;
chmod 644 /etc/nginx/html/50x.html
chmod 777 /run
chmod 755 /srv/http/command/*
chmod 755 /srv/http/db/redis_datastore_setup
chmod 755 /srv/http/db/redis_acards_details
chmod 755 /etc/X11/xinit/start_chromium.sh
chown mpd.audio /mnt/MPD/*
chown mpd.audio /mnt/MPD/USB/*
find /mnt/MPD/USB -type d -exec chmod 777 {} \;
find /mnt/MPD/USB -type f -exec chmod 644 {} \;
chown -R mpd.audio /var/lib/mpd
#
# set lyrics and artistinfo for no internet connection
chown root.root /srv/http/command/lyric.sh
chown root.root /srv/http/command/artist_info.sh
chmod 600 /srv/http/command/lyric.sh
chmod 600 /srv/http/command/artist_info.sh

#
# reset services so that any cached files are replaced by the latest ones (in case you don't want to reboot)
systemctl daemon-reload
#
# zero fill the file system if parameter full is selected
if [ "$1" == "full" ]; then
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
# reset root password
echo -e "rune\nrune" | passwd root
#
# reset host information (icon-name, chassis and hostname)
hostnamectl --static --transient --pretty set-icon-name multimedia-player
hostnamectl --static --transient --pretty set-chassis embedded
hostnamectl --static --transient --pretty set-hostname runeaudio
#
# set timezone to -11 hours of GMT - any user adjustment will always go forward
timedatectl set-timezone Pacific/Pago_Pago
redis-cli set timezone "Pacific/Pago_Pago"
#
# shutdown redis and force a write all in-memory keys to disk (purges any cached values)
redis-cli save
redis-cli shutdown save
#
# shutdown & poweroff
shutdown -P now
#---
#End script
