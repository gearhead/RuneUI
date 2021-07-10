#!/bin/sh


CHROMIUM_TEMP=/tmp/chromium
sudo http mkdir -p $CHROMIUM_TEMP
export HOME=$CHROMIUM_TEMP

chromium --app=http://localhost --start-fullscreen --force-device-scale-factor=1.8 --kiosk --disable-pinch --disable-translate --disable-crash-reporter --disable-infobars --check-for-update-interval=8640000
