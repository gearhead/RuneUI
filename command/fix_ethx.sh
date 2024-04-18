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
#  file: command/fix_ethx.sh
#  version: 1.3
#  coder: janui
#  date: September 2020
#
# on the Pi5 the Ethernet connection (normally eth0) sometimes hangs and does not acquire an ip-address
# this routine detects this condition and corrects it
#
# setup
set +e # continue on errors
# set -x # echo all commands to cli
#
done="0"
# loop 20 times with a 5 second sleep, the job runs for a maximum of 100 seconds
for i in {0..20..1} ; do
    if [ "$done" == "1" ] ; then
        # finished
        break
    fi
    sleep 5
    pgrep iwd >/dev/null 2>&1
    if [ "$?" != "0" ] ; then
        # iwd is not running, loop
        continue
    fi
    pgrep connman >/dev/null 2>&1
    if [ "$?" != "0" ] ; then
        # connman is not running, loop
        continue
    fi
    # get a list of all nics
    nics=$( ip -o -br  address | cut -d ' ' -f1 | xargs )
    # get a list of all Wi-Fi nics
    wifinics=$( iw dev | grep -i interface | cut -d ' ' -f2 | xargs )
    # get a list of nics with an ip-address
    ipnics=$( ip -o -br  address | grep -i '/' | cut -d ' ' -f1 | xargs )
    done="1"
    for nic in $nics ; do
        if [[ "$ipnics" =~ "$nic" ]]; then
            # this nic has an ip-address, ignore it
            continue
        fi
        if [[ "$wifinics" =~ "$nic" ]]; then
            # this nic is a wifi nic, ignore it
            continue
        fi
        # this is an ethernet nic without an ip-address
        ip addr flush $nic
        ip link set dev $nic down
        ip link set dev $nic up
        done="0"
    done
done
#---
#End script
