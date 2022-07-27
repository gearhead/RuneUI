#!/bin/bash
# script to finish off installing spotifyd
# the spotifyd package must be installed first
# there is no standard arch arm pacman version available
# a custom spotifyd-rune package is available for armv6 (Pi1) and armv7 (pi2) on github
# see here for the packages:
# see here for documentation: https://github.com/Spotifyd/spotifyd
#
set -x # echo all commands to cli
set +e # continue on errors
#
# is spotifyd installed?
if pacman -Q spotifyd ;
then
    echo "spotifyd installed"
else
    echo "spotifyd not installed - terminating"
    exit 0
fi
#
# make sure that the spotifyd redis varaiables are initialised
/srv/http/db/redis_datastore_setup check
#
# create a user for starting spotifyd as a systemd service
userdel spotifyd
groupdel spotifyd
useradd -U -G audio -c 'Spotifyd systemd user' -d /dev/null -s /sbin/nologin spotifyd
id spotifyd
grep -i spotifyd /etc/passwd
#
# copy the default spotifyd.conf (this will be automatically be replaced by RuneAudio)
cp /var/www/app/config/defaults/etc/spotifyd.conf /etc/spotifyd.conf
chmod 644 /etc/spotifyd.conf
#
# copy the systemd service file
cp /var/www/app/config/defaults/usr/lib/systemd/system/spotifyd.service /usr/lib/systemd/system/spotifyd.service
chmod 644 /usr/lib/systemd/system/spotifyd.service
#
# initiate systemd
systemctl daemon-reload
systemctl disable spotifyd
#
# end
exit 1
