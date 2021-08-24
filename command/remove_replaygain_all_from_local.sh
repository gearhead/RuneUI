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
#  file: command/remove_replaygain_all_from_local.sh
#  version: 1.3
#  coder: janui
#  date: August 2021
#
# Purpose:
# This script will search for local mounted drives. It will remount them in Write mode if required. Then call
#   command/remove_replaygain_all.sh for each drive which will remove ReplayGain tags from all music files files.
#
# Usage <path_to_script>replaygain_all_to_local.sh <mode>
# Where:
# <mode> can optionally be defined as 'silent', which causes the script to run silently unless an error condition arises
# the <mode> parameters must be in lower case
#
#
# Error codes
INVALID_ARGUMENT=22 # 22 EINVAL Invalid argument
#
# validate the parameters
if [ -z "$1" ]; then
    silent=""
else
    if [ "silent" = "$1" ]; then
        silent="silent"
    else
        echo "Argument 1 $1 invalid!"
        exit $INVALID_ARGUMENT
    fi
fi
#
# process each localy mounted drive
FILES="/mnt/MPD/USB/*"
for FILE in $FILES
do
    if [ -w "$FILE" ]; then
        echo "Mount $FILE is writable"
    else
        echo "Mount $FILE is read-only remounting"
        mount -o rw,remount "$FILE"
        if [ -w "$FILE" ]; then
            echo "Mount $FILE is writable"
        else
            echo "Mount $FILE cannot be remounted for write access, skipping"
            continue
        fi
    fi
    /srv/http/command/remove_replaygain_all.sh "$FILE" scan $silent
done
#
exit 0
#---
#End script
