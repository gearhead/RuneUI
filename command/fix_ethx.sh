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
# on the Pi5 the Ethernet connection (normally eth0) sometimes hangs and does not acquire a valid ip-address
# this routine detects this condition and corrects it
#
# setup
set +e # continue on errors
# set -x # echo all commands to cli
#
done="0"
# loop 20 times with a 5 second sleep, the job runs for a maximum of 100 seconds
for i in {0..20..1} ; do
    # echo $i
    pgrep iwd >/dev/null 2>&1
    if [ "$?" != "0" ] ; then
        # iwd is not running, loop
        sleep 5
        continue
    fi
    pgrep connman >/dev/null 2>&1
    if [ "$?" != "0" ] ; then
        # connman is not running, loop
        sleep 5
        continue
    fi
    pgrep redis >/dev/null 2>&1
    if [ "$?" != "0" ] ; then
        # redis is not running, loop
        sleep 5
        continue
    fi
    # check that this is a PI5, or unknown hardware type
    hwmodel=$( redis-cli get hwmodel | xargs )
    if [ "$hwmodel" != "" ] && [ "$hwmodel" != "17" ] ; then
        # hardware model is known ( != "") and not a Pi5 (!= "17")
        exit
    fi
    # get a list of all nics
    nics=$( ip -o -br  address | cut -d ' ' -f1 | xargs )
    # get a list of all Wi-Fi nics
    wifinics=$( iw dev | grep -i interface | cut -d ' ' -f2 | xargs )
    # get a list of nics with an ip-address
    ipnics=$( ip -o -br  address | grep -i '/' | cut -d ' ' -f1 | xargs )
    done="1"
    for nic in $nics ; do
        # echo $nic
        if [ "lo" == "$nic" ]; then
            # this nic is the loopback device, ignore it
            continue
        fi
        if [[ $nic == ap* ]]; then
            # this nic is a virtual access point device, ignore it
            continue
        fi
        if [[ "$wifinics" =~ "$nic" ]]; then
            # this nic is a wifi nic, ignore it
            continue
        fi
        # echo $nic
        if [[ "$ipnics" =~ "$nic" ]]; then
            # this nic has an ip-address
            invalid=$( ip -s -h addr show $nic | xargs | grep -c 'inet 169.254.' | xargs )
            # echo $nic
            if [ "$invalid" == "0" ]; then
                # the nic seems to have a valid ip address
                continue
            fi
        fi
        # this is an ethernet nic without an ip-address or an ethernet nic with an address which begins with 169.254
        # echo "Fixing $nic"
        ip addr flush $nic
        ip link set dev $nic down
        ip link set dev $nic up
        # the redis variable 'network_ipv6' has not yet been determined when this routine runs
        #   the lines below are included as documentation, the nic setting will be corrected later if required
        # ipv6_on=$( redis-cli get network_ipv6 )
        # if [ "$ipv6_on" == "0" ] ; then
            # # ipv6 is off, set the nic accordingly
            # sysctl -w net.ipv6.conf.$nic.disable_ipv6=1 > /dev/null
        # fi
        done="0"
    done
    if [ "$done" == "1" ] ; then
        # finished
        break
    fi
    # sleep 5 is fine for a fast Pi5, slower models need much longer to activate the ethernet nic (delay before and longer delay between tries)!
    sleep 5
done
#---
#End script
