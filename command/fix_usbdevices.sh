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
#  file: command/fix_usbdevices.sh
#  version: 0.7
#  coder: janui
#  date: October 2024
#
# on the Pi5 USB sound cards sometimes fail to activate, it is possibly caused by a power supply issue
# this routine detects this condition and attempts to correct it
# this routine should only be run once
#
# setup
set +e # continue on errors
# set -x # echo all commands to cli
#
# find any failed USB devices
devices=$( ( journalctl | grep "can't set config #1, error -71" | grep usb ; journalctl | grep -iE 'failed' | grep -i usb | grep kernel ) | sed 's/^.*usb //;s/: .*$//' | sort -u | xargs )
# echo $devices
for i in $devices ; do
    # echo $i
    echo 0 > /sys/bus/usb/devices/$i/authorized
    sleep 2
    echo 1 > /sys/bus/usb/devices/$i/authorized
done
#---
#End script
