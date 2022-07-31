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
#  file: command/wifi_off.sh
#  version: 1.3
#  coder: janui
#  date: July 2022
#

sed -i '/dtoverlay=disable-wifi/c\dtoverlay=disable-wifi' /boot/config.txt
# turn off the Wi-Fi nics, it could lock out the UI if a mistake were made
#   currently not using this, a reboot is required to actually switch everything off
# for dev in /sys/class/net/*; do
    # [ -e "$dev"/wireless ] && ip link set dev ${dev##*/} down && echo ${dev##*/}
# done
redis-cli set wifi_on 0
#---
#End script
