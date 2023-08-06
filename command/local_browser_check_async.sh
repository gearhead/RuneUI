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
#  file: command/local_browser_check_async.sh
#  version: 0.6
#  coder: janui
#  date: August 2023
#
{
# continue on errors
set +x # dont echo commands
set +e # continue on errors
echo "local_browser_check_async.sh started"

windows=$( redis-cli hget local_browser windows )
if [ "$windows" == "xorg" ] ; then
    # first-time start of the chormium browser under xorg (sometimes) fails
    #  when restarted it is fine
    echo "windows: xorg"
    sleep 7
    smashing=$( systemctl status local-browser | grep -ic '*** stack smashing detected ***' )
    if [ "$smashing" != "0" ] ; then
        echo "stack smashing detected, restarting local-browser"
        systemctl stop local-browser
        systemctl start local-browser
    fi
elif [ "$windows" == "weston" ] ; then
    # any restart actions for weston can be coded here
    echo "windows: weston"
fi
echo "local_browser_check_async.sh finished"
} > /var/log/runeaudio/local_browser_check_async.log 2>&1

#---
#End script
