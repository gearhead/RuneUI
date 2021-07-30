#!/bin/bash
#
# Reworking of raspi-rotate-screen.sh
# Originally created by colinleroy, December 2018.
# See https://github.com/colinleroy/raspi-rotate
#
#  If and where applicable, not infringing on any original copyright:
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
#  file: command/raspi-rotate-screen.sh-screen.sh
#  version: 1.3
#  coder: janui
#  date: April 2021
#
#
#

# Variables
CONF_DIR="/srv/http/command"
HOOKS_DIR="$CONF_DIR"
HOOKS_SPLASH_FILE="$HOOKS_DIR/01-bootsplash.sh"
XORG_TEMPLATE="/srv/http/app/config/defaults/99-raspi-rotate.conf.tmpl"
XORG_CONF_DIR="/usr/share/X11/xorg.conf.d"
XORG_CONF_FILE="$XORG_CONF_DIR/99-raspi-rotate.conf"

function usage
{
    echo "usage: $0 [rotation]"
    echo "where rotation is one of:"
    echo "    normal:                 no rotation"
    echo "    cw, clockwise:          rotate 90° clockwise"
    echo "    ccw, counter-clockwise: rotate 90° counter-clockwise"
    echo "    ud:                     rotate 180°"
    exit
}

# Parse argument and set variables
if [ "$#" -gt 0 ]; then
    case "$1" in
        normal|NORMAL)
            ROTATE="NORMAL"
            MATRIX="1 0 0 0 1 0 0 0 1"
        ;;
        cw|clockwise|CW|CLOCKWISE)
            ROTATE="CW"
            MATRIX="0 1 0 -1 0 1 0 0 1"
        ;;
        ccw|counter-clockwise|CCW|COUNTER-CLOCKWISE)
            ROTATE="CCW"
            MATRIX="0 -1 1 1 0 0 0 0 1"
        ;;
        ud|upside-down|UD|UPSIDE-DOWN)
            ROTATE="UD"
            MATRIX="-1 0 1 0 -1 1 0 0 1"
        ;;
        *)
            usage
        ;;
    esac
else
    usage
fi

# Build the config file
TMP_FILE=$(mktemp /tmp/rotate.XXXXXX)

if [ -f "$XORG_TEMPLATE" ]; then
    sed "s/ROTATION_SETTING/$ROTATE/" "$XORG_TEMPLATE" > "$TMP_FILE"
    sed -i "s/MATRIX_SETTING/$MATRIX/" "$TMP_FILE"

    # Install the config file
    mkdir -p "$XORG_CONF_DIR"
    chmod 644 "$TMP_FILE"
    mv "$TMP_FILE" "$XORG_CONF_FILE"
fi

# Apply the rotate to all files containing the rotate option
FILES=$( grep -Ril "Option.*rotate" "$XORG_CONF_DIR" )
for FILE in $FILES ; do
    sed -i '/Option.*rotate/s/^.*Option.*\"rotate\"/       Option \"rotate\" \"$ROTATE\"/' "$FILE"
    # There is no valid value for the rotate option when 0' rotate is specified
    if [ "$ROTATE" = "NORMAL" ]; then
        sed -i '/Option.*rotate/s/^ .*Option.*\"rotate\"/#       Option \"rotate\"/' "$FILE"
    fi
done

# Run hooks
# if [ -d "$HOOKS_DIR" ] ; then
    # for f in $HOOKS_DIR/?*.sh ; do
        # [ -x "$f" ] && . "$f"
    # done
    # unset f
# fi
eval $HOOKS_SPLASH_FILE $1

echo "Rotation set to $ROTATE"
#---
#End script
