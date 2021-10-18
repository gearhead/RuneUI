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
#  file: command/add_replaygain_all_to_local.sh
#  version: 1.3
#  coder: janui
#  date: August 2021
#
# Purpose:
# This script will search for local mounted drives. It will remount them in Write mode if required. Then call
#   command/add_replaygain_all.sh for each drive which will add ReplayGain tags to the all valid music files.
#
# Usage <path_to_script>replaygain_all_to_local.sh <mode> <mode>
# Where:
# <mode> can optionally be defined as 'skip', which causes the script to skip the files which already have ReplayGain set
# <mode> can optionally be defined as 'continue', which causes the script to continue on errors
# <mode> can optionally be defined as 'silent', which causes the script to run silently unless an error condition arises
# the <mode> parameters can be given in any order after the directory, they must be in lower case
#
#
# Error codes
INVALID_ARGUMENT=22 # 22 EINVAL Invalid argument
ERROR_WRITE_PROTECT=13 # 13 EACCES Permission denied (is Windows code 19)
#
# validate the parameters
silent=""
skip=""
continue=""
if [ ! -z "$1" ]; then
    if [ "silent" = "$1" ]; then
        silent="silent"
    elif [ "skip" = "$1" ]; then
        skip="skip"
    elif [ "continue" = "$1" ]; then
        continue="continue"
    else
        echo "Argument 1 $1 invalid!"
        exit $INVALID_ARGUMENT
    fi
    if [ ! -z "$2" ]; then
        if [ "silent" = "$2" ]; then
            silent="silent"
        elif [ "skip" = "$2" ]; then
            skip="skip"
        elif [ "continue" = "$2" ]; then
            continue="continue"
        else
            echo "Argument 2 $2 invalid!"
            exit $INVALID_ARGUMENT
        fi
    fi
    if [ ! -z "$3" ]; then
        if [ "silent" = "$3" ]; then
            silent="silent"
        elif [ "skip" = "$3" ]; then
            skip="skip"
        elif [ "continue" = "$3" ]; then
            continue="continue"
        else
            echo "Argument 3 $3 invalid!"
            exit $INVALID_ARGUMENT
        fi
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
            if [ "$continue" = "continue" }; then
                echo "Mount $FILE cannot be remounted for write access, skipping"
                continue
            else
                echo "Mount $FILE cannot be remounted for write access, terminating"
                exit $ERROR_WRITE_PROTECT
            fi
        fi
    fi
    /srv/http/command/add_replaygain_all.sh "$FILE" scan $skip $silent $continue
done
#
exit 0
#---
#End script
