#!/bin/bash
set -x # echo all commands to cli
set +e # continue on errors
#
# change from connman/iwd to NetworkManager/wpa_supplicant
# ----------
apt install network-manager -y
systemctl stop connman ; systemctl stop iwd ; systemctl restart NetworkManager
apt purge connman-git iwd -y
apt autoremove -y
mv /srv/http/app/libs/runeaudio.php /srv/http/app/libs/runeaudio_orig.php
ln -s /srv/http/app/libs/runeaudio_nm.php /srv/http/app/libs/runeaudio.php

mv /srv/http/command/debug_collector /srv/http/command/debug_collector_orig
ln -s /srv/http/command/debug_collector_nm /srv/http/command/debug_collector

mv /srv/http/command/internet_available_async.sh /srv/http/command/internet_available_async_orig.sh
ln -s /srv/http/command/internet_available_async_nm.sh /srv/http/command/internet_available_async.sh

mv /srv/http/command/refresh_nics /srv/http/command/refresh_nics_orig
ln -s /srv/http/command/refresh_nics_nm /srv/http/command/refresh_nics

systemctl restart rune_SY_wrk
reboot
#
#---
#End script
