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
#  file: command/bt_on.sh
#  version: 1.3
#  coder: janui
#  date: January 2021
#

set +e # continue on errors
p1mountpoint=$( redis-cli get p1mountpoint )
sed -i '/dtoverlay=disable-bt/c\#dtoverlay=disable-bt' "$p1mountpoint/config.txt"
sed -i '/dtparam=krnbt=/c\dtparam=krnbt=on' "$p1mountpoint/config.txt"
systemctl start hciuart
systemctl start bluetooth
count=3
timeout 5 bluetoothctl power on
until [ $? -eq 0 ] || (( count-- <= 0 )); do
    # loop for 3 times to allow the bluetooth service to start and initialise
    # echo $count
    # echo $?
    sleep 2
    timeout 5 bluetoothctl power on
done
# if there is a choice of controllers use the one which is not used by Wi-Fi
#   get the wifi mac-address
wifimac=$( ip -br link | grep wlan0 | xargs | cut -d ' ' -f 3 )
#   truncate the last 3 characters
wifimac_short=${wifimac:0:-3}
#   lower case
wifimac_short=${wifimac_short,,}
#   get the controlers
controllers=$( timeout 5 bluetoothctl list | xargs | cut -d ' ' -f2 )
#   walk through the controllers
for controller in $controllers ; do
    # this routine selects the first controller when no others are available and then
    # attempts not to choose a controller with a similar mac address as the Wi-Fi radio
    # so a Bluetooth dongle will have priority over an on board Bluetooth radio when Wi-Fi is enabled
    # otherwise the last listed controller will be used
    #   lower case
    controller=${controller,,}
    if [ "$controler" == "" ] ; then
        # controller not set, skip it
        continue
    fi
    if [ "$sel_controler" == "" ] ; then
        # not set use the first controller
        sel_controler="$controller"
        # echo "$controller"
    fi
    if [[ "$controller" != *"$wifimac_short"* ]] ; then
        # not the same as the Wi-Fi radio
        sel_controler="$controller"
        # echo "$controller $wifimac_short"
    fi
done
if [ "$sel_controler" != "" ] ; then
    # set the default controller when set
    timeout 5 bluetoothctl select $sel_controler
fi
systemctl start bluealsa
count=3
timeout 5 bluealsa-cli list-services
until [ $? -eq 0 ] || (( count-- <= 0 )); do
    # loop for 3 times to allow the bluealsa service to start and initialise
    # echo $count
    # echo $?
    sleep 2
    timeout 5 bluealsa-cli list-services
done
systemctl start bluealsa-aplay
systemctl start bt_mon_switch
timeout 5 bluetoothctl discoverable off
timeout 5 bluetoothctl pairable off
if [ $? -eq 0 ]; then
    # every thing is up, so change the redis indicator
    redis-cli set bluetooth_on 1
    # enable all the bluetooth service to start on boot
    systemctl enable hciuart
    systemctl enable bluetooth
    systemctl enable bluealsa
    systemctl enable bluealsa-aplay
    systemctl enable bt_mon_switch
    #
    # set up the redis variable containing the Bluetooth Quality options
    id=$( cat /proc/sys/kernel/random/uuid | md5sum | cut -d ' ' -f 1 )
    # start a system worker background job (rune_SY_wrk > btcfg > action:quality_options)
    #   by writing the command to the worker redis hash and fifo queue
    redis-cli hset w_queue "$id" '{"wrkcmd":"btcfg","action":"quality_options","args":null}'
    redis-cli lpush w_queue_fifo "$id"
fi

#---
#End script
