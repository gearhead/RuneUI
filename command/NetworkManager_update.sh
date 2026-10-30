#!/bin/bash
set -x # echo all commands to cli
set +e # continue on errors

#
# change from connman/iwd to NetworkManager/wpa_supplicant
# --------------------------------------------------------
#
# run this script using: nohup /srv/http/command/NetworkManager_update.sh > /NetworkManager_update.log 2>&1 &
#

#clean up the package database
apt install --fix-broken -y
apt autoremove -y --purge
apt autoclean

# stop the major background processes
/srv/http/command/rune_shutdown
systemctl stop rune_SY_wrk rune_PL_wrk rune_MPDEM_wrk

# install network-manager
apt install network-manager -y
# disable and stop connman and iwd
systemctl disable connman
systemctl disable iwd
systemctl stop connman
systemctl stop iwd

# start and enable NetworkManager
systemctl restart NetworkManager
systemctl enable NetworkManager

# remove connman and iwd
apt remove connman-git -y --purge
apt remove iwd -y --purge

# also purge openresolv and resolvconf
apt purge openresolv resolvconf -y

#clean up the package database
apt install --fix-broken -y
apt autoremove -y --purge
apt autoclean

#stop disable and mask ModemManager (GSM support for NetworkManager)
systemctl stop ModemManager
systemctl disable ModemManager
systemctl mask ModemManager

# create symlink for resolv.conf
ln -sf /run/systemd/resolve/stub-resolv.conf /etc/resolv.conf

# create some files when they do not exist, these turn off the use of ifupdown by NetworkManager
if [ ! -f "/etc/tmpfiles.d/NetworkManager.conf" ] ; then
    cat << 'EOF' > /etc/tmpfiles.d/NetworkManager.conf
# This is part of RuneAudio
#
# This configuration file creates a directory and an empty file for NetworkManager which then suppresses
#   information and warning messages related to the optional package ifupdown
#   ifupdown manages the file /run/network/ifstate
#   ifupdown is not normally installed on ripios
d /run/network 0755 root root -
f /run/network/ifstate 0644 root root -
#
# end of file
EOF
fi
if [ ! -f "/etc/NetworkManager/conf.d/99-disable-ifupdown.conf" ] ; then
    cat << 'EOF' > /etc/NetworkManager/conf.d/99-disable-ifupdown.conf
# This is part of RuneAudio
#
# This configuration file suppresses the use of ifupdown
#   ifupdown is not normally installed on ripios
[main]
#plugins=ifupdown,keyfile
plugins=keyfile
#
# end of file
EOF
fi

# save current versions of runeauio.php, debug_colector internet_available.sh and refresh_nics
# then symlink the network-manager versions
mv /srv/http/app/libs/runeaudio.php /srv/http/app/libs/runeaudio_orig.php
ln -s /srv/http/app/libs/runeaudio_nm.php /srv/http/app/libs/runeaudio.php
#
mv /srv/http/command/debug_collector /srv/http/command/debug_collector_orig
ln -s /srv/http/command/debug_collector_nm /srv/http/command/debug_collector
#
mv /srv/http/command/internet_available_async.sh /srv/http/command/internet_available_async_orig.sh
ln -s /srv/http/command/internet_available_async_nm.sh /srv/http/command/internet_available_async.sh
#
mv /srv/http/command/refresh_nics /srv/http/command/refresh_nics_orig
ln -s /srv/http/command/refresh_nics_nm /srv/http/command/refresh_nics

# initiate a rune shutdown
/srv/http/command/rune_shutdown
# reboot
reboot now
#
#---
#End script
