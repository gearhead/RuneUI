#!/bin/bash

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
    if [ "$ROTATE" = "NORMAL" ]; then
        sed -i '/Option.*rotate/s/^ .*Option \"rotate\"/#       Option \"rotate\"/' "$TMP_FILE"
    fi
    sed -i "s/MATRIX_SETTING/$MATRIX/" "$TMP_FILE"

    # Install the config file
    mkdir -p "$XORG_CONF_DIR"
    chmod 644 "$TMP_FILE"
    mv "$TMP_FILE" "$XORG_CONF_FILE"
fi

# Run hooks
# if [ -d "$HOOKS_DIR" ] ; then
    # for f in $HOOKS_DIR/?*.sh ; do
        # [ -x "$f" ] && . "$f"
    # done
    # unset f
# fi
eval $HOOKS_SPLASH_FILE $1

echo "Rotation set to $ROTATE"
