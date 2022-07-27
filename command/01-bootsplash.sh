#!/bin/bash

if [ "$1" != "" ]; then
    if [ -d "/usr/share/bootsplash-$1" ]; then
        echo "Linking /usr/share/bootsplash-$1 to /usr/share/bootsplash"
        rm -f /usr/share/bootsplash
        ln -s "/usr/share/bootsplash-$1" /usr/share/bootsplash
    fi
fi
#---
#End script
