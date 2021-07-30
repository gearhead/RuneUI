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
#
# Variables
XORG_CONF_DIR="/usr/share/X11/xorg.conf.d"
#
# first correct any invalid frame buffer 1 specifications
#   these are only valid when the file /dev/fb1 exists
#
# get a list of files with a specified frame buffer 1
FB_FILES=$( grep -Ril "Option.*fbdev.*/dev/fb1" "$XORG_CONF_DIR" )
if [ -f "/dev/fb1" ]; then
    # frame buffer 1 exists
    for FILE in $FB_FILES ; do
        # remove comment character (leading #)
        sed -i '/Option.*fbdev.*\/dev\/fb1/s/^[#]*.[ ]*/ /' "$FILE"
    done
else
    # no frame buffer 1
    for FILE in $FB_FILES ; do
        # add comment character (leading #)
        sed -i '/Option.*fbdev.*\/dev\/fb1/s/^/# /' "$FILE"
    done
fi
unset FB_FILES
#
# now add the rotate option to any file containing the text 'Section "Device"' and has no rotate option
DV_FILES=$( grep -Ril "Section.*Device" "$XORG_CONF_DIR" )
for FILE in $DV_FILES ; do
    # count the number of rotate options
    RT_COUNT=$( grep -ic "Option.*rotate" "$FILE" )
    if [ "$RT_COUNT" == "0" ]; then
        # rotate option not found, add it to the correct position
        sed -i '/^Section "Device"/,/^EndSection/s/EndSection/# Option "rotate" "NORMAL"\nEndSection/' "$FILE"
    fi
done
#---
#End script
