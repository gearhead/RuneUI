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
#  file: command/raspi-rotate-install.sh
#  version: 1.3
#  coder: janui
#  date: April 2021
#
#
#
set -x # echo all commands to cli
set +e # continue on errors
#raspi-rotate install script
#
mkdir /home/raspi-rotate
cd /home/raspi-rotate
#
# save the current versions of the distribution files
cp /var/www/app/config/defaults/99-raspi-rotate.conf.* /home/raspi-rotate/
cp /var/www/app/config/defaults/runeaudio-0.5-bootsplashes.tar.gz /home/raspi-rotate/runeaudio-0.5-bootsplashes.tar.gz
cp /var/www/command/01-bootsplash.sh /home/raspi-rotate/01-bootsplash.sh
cp /var/www/command/raspi-rotate-screen.sh /home/raspi-rotate/raspi-rotate-screen.sh
#
# download the reference version
wget https://github.com/gearhead/RuneOS/raw/master/packages/raspi-rotate-rune/raspi-rotate-rune.tar
#
# restore the reference version, incl. documentation copyright, etc.
tar -xpf raspi-rotate-rune.tar -C /
#
# set the file attributes of the documentation files
chmod  755 /usr/share/doc/raspi-rotate
chmod  644 /usr/share/doc/raspi-rotate/*
#
# save a copy of the original bootsplash file set
mv -n /usr/share/bootsplash /usr/share/bootsplash-save
#
# copy the current versions of the distribution back to their normal places
cp /home/raspi-rotate/99-raspi-rotate.conf.* /var/www/app/config/defaults/
cp /home/raspi-rotate/runeaudio-0.5-bootsplashes.tar.gz /var/www/app/config/defaults/runeaudio-0.5-bootsplashes.tar.gz
cp /home/raspi-rotate/01-bootsplash.sh /var/www/command/01-bootsplash.sh
cp /home/raspi-rotate/raspi-rotate-screen.sh /var/www/command/raspi-rotate-screen.sh
#
# restore the bootsplashes for all screen orientations
tar -xpf /srv/http/app/config/defaults/runeaudio-0.5-bootsplashes.tar.gz -C /
#
# clean up the home directory
cd /home
rm -r /home/raspi-rotate
#
# remove redundant files in the bootsplashes and remove invalid symlinks
find /usr/share -name *audiophonics*.png -exec rm -rf {} \;
find /usr/share/*bootsplas* -xtype l -exec rm  -rf {} \;
#
# set the file attributes of the bootsplashes
chown -Rh root.root /usr/share/bootsplas*
chown -R root.root /usr/share/bootsplas*
find /usr/share/boot* -type d -exec chmod 755 {} \;
find /usr/share/boot*/*.png -type f -exec chmod 644 {} \;
#
# set the screen orientation
/srv/http/command/01-bootsplash.sh NORMAL
#
# copy the config file
cp /var/www/app/config/defaults/99-raspi-rotate.conf.tmpl /usr/share/X11/xorg.conf.d/99-raspi-rotate.conf
#---
#End script
