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
#  file: command/waitnic.sh
#  version: 1.3
#  coder: janui
#  date: January 2021
#
# Purpose:
# A routine which will loop for a maximum of 5 seconds waiting for a nic to go into the UP
#   state, used by the connman systemd unit (etc/systemd/system/connman.service) to delay
#   starting connman until at least one nic has initialised
#
val="0"
cnt=20
while [ "$val" == "0"  ] && [ $cnt -gt 0 ]; do
    val=$(ip -o link show | grep -i 'state up' | grep -icv loopback)
    if [ "$val" == "0" ]; then
        sleep 0.25
    fi
    cnt=$[$cnt-1]
done
#---
#End script
