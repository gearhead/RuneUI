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
#  file: command/bt_off.sh
#  version: 1.3
#  coder: janui
#  date: January 2021
#

set +e # continue on errors
p1mountpoint=$( redis-cli get p1mountpoint )
sed -i '/dtoverlay=disable-bt/c\dtoverlay=disable-bt' "$p1mountpoint/config.txt"
sed -i '/dtparam=krnbt=/c\dtparam=krnbt=off' "$p1mountpoint/config.txt"
timeout 5 bluetoothctl pairable off
timeout 5 bluetoothctl discoverable off
timeout 5 bluetoothctl power off
declare -a bluetooth_arr=(bt_mon_switch bt_scan_output bluealsa-aplay bluealsa-monitor bluealsa bluetooth-agent bluetoothctl_scan bluetooth)
# stop and disable Bluetooth services
for i in "${bluetooth_arr[@]}"
do
   systemctl stop "$i"
   systemctl disable "$i"
done
redis-cli set bluetooth_on 0
#---
#End script
