#!/bin/bash
set -x # echo all commands to cli
set +e # continue on errors
#
# update MPD
# ----------
#
# make work directories
mkdir -p /home/mpdupdate
mkdir -p /home/mpdupdate/icu
cd /home/mpdupdate
#
# make copy the current icu 61 libraries
cp /usr/lib/libicudata.so.61.1 /home/mpdupdate/icu/libicudata.so.61.1
cp /usr/lib/libicui18n.so.61.1 /home/mpdupdate/icu/libicui18n.so.61.1
cp /usr/lib/libicuio.so.61.1 /home/mpdupdate/icu/libicuio.so.61.1
cp /usr/lib/libicutest.so.61.1 /home/mpdupdate/icu/libicutest.so.61.1
cp /usr/lib/libicutu.so.61.1 /home/mpdupdate/icu/libicutu.so.61.1
cp /usr/lib/libicuuc.so.61.1 /home/mpdupdate/icu/libicuuc.so.61.1
#
# stop mpd and ashuffle
mpc stop
systemctl stop mpd ashuffle mpdscribble upmpdcli
pacman -Syy
#
# update prerequisites (2.52 MiB & 0.01 MiB & 0.55 MiB)
pacman -S glibc libmpdclient icu --noconfirm
#
# copy the icu 61 libraries back and add symlinks
cp /home/mpdupdate/icu/libicudata.so.61.1 /usr/lib/libicudata.so.61.1
cp /home/mpdupdate/icu/libicui18n.so.61.1 /usr/lib/libicui18n.so.61.1
cp /home/mpdupdate/icu/libicuio.so.61.1 /usr/lib/libicuio.so.61.1
cp /home/mpdupdate/icu/libicutest.so.61.1 /usr/lib/libicutest.so.61.1
cp /home/mpdupdate/icu/libicutu.so.61.1 /usr/lib/libicutu.so.61.1
cp /home/mpdupdate/icu/libicuuc.so.61.1 /usr/lib/libicuuc.so.61.1
ln -sf /usr/lib/libicudata.so.61.1 /usr/lib/libicudata.so.61
ln -sf /usr/lib/libicui18n.so.61.1 /usr/lib/libicui18n.so.61
ln -sf /usr/lib/libicuio.so.61.1 /usr/lib/libicuio.so.61
ln -sf /usr/lib/libicutest.so.61.1 /usr/lib/libicutest.so.61
ln -sf /usr/lib/libicutu.so.61.1 /usr/lib/libicutu.so.61
ln -sf /usr/lib/libicuuc.so.61.1 /usr/lib/libicuuc.so.61
#
# upgrade to the latest version of ashuffle (0.00 MiB)
wget https://github.com/gearhead/RuneOS/raw/master/packages/ashuffle-rune/ashuffle-rune-0.22-6-armv6h.pkg.tar.xz
pacman -U ashuffle-rune-0.22-6-armv6h.pkg.tar.xz --noconfirm
#
# remove mpd-rune and ffmpeg-rune (-16.94 MiB)
pacman -Rs mpd-rune ffmpeg-rune --noconfirm
#
# install standard mpd and ffmpeg (125.13 MiB)
pacman -S mpd ffmpeg mpc --noconfirm
#
# remove work directory
rm -r /home/mpdupdate
#
# check file protection and ownership
chown -R http:http /srv/http/
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
chown mpd:audio /mnt/MPD/*
chown mpd:audio /mnt/MPD/USB/*
chmod 777 /mnt/MPD/USB
chmod 777 /mnt/MPD/USB/*
chown -R mpd:audio /var/lib/mpd
#---
#End script
