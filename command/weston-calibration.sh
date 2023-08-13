#!/bin/bash
# This code modified by gearhead to work with the RPiOS Rune installation
# from: 
# https://coral.googlesource.com/weston-imx/+/e0921a74330a8c3d7dce245c70cd21f471cdde76/protocol/weston-touch-calibration.xml
# Copyright 2018 Collabora, Ltd.
# Copyright 2018 General Electric Company
#
# Permission is hereby granted, free of charge, to any person obtaining
# a copy of this software and associated documentation files (the
# "Software"), to deal in the Software without restriction, including
# without limitation the rights to use, copy, modify, merge, publish,
# distribute, sublicense, and/or sell copies of the Software, and to
# permit persons to whom the Software is furnished to do so, subject to
# the following conditions:
#
# The above copyright notice and this permission notice (including the
# next paragraph) shall be included in all copies or substantial
# portions of the Software.
#
# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
# EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
# MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
# NONINFRINGEMENT.  IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS
# BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN
# ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
# CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
# SOFTWARE.

# This is an example script working as Weston's calibration helper.
# Its purpose is to permanently store the calibration matrix for the given
# touchscreen input device into a udev property. Since this script naturally
# runs as the user that runs Weston, it presumably cannot write directly into
# /etc. It is left for the administrator to set up appropriate files and
# permissions.


#RUNE RPiOS:
# To use this script, one needs to edit weston.ini, in section [libinput], 
# un-comment these lines:
# touchscreen_calibrator=true
# calibration_helper=/usr/bin/echo
# Then start weston.service *and* then from an SSH session,
# systemctl start weston
# then, run this script ./weston-calibrate.sh
# once calibrated, you can comment back the lines in the weston.ini file.


# exit immediately if any command fails
set -e
# This sets the display and run dir
export WAYLAND_DISPLAY=wayland-0
export XDG_RUNTIME_DIR=/run/weston
# This gets the device we are calibrating:
SYSPATH=$(/usr/bin/weston-touch-calibrator | sed 's/[^"]*"\([^"]*\)".*/\1/')
# This is where the rule gets written
RULE="/etc/udev/rules.d/90-touchscreen.rules"

# this sets the targets in motion
MATRIX=$(/usr/bin/weston-touch-calibrator -v "$SYSPATH" | cut -b 21-)
#echo "$MATRIX"
# Pick something to recognize the right touch device with.
# Usually one would use something like a serial.
#SERIAL=$(udevadm info "$SYSPATH" --query=property | \
#	awk -- 'BEGIN { FS="=" } { if ($1 == "ID_INPUT") { print $2; exit } }')
DEVICE=$(udevadm info --attribute-walk "$SYSPATH" | grep 'name' | tr -d '[:space:]')
IS_INPUT=$(udevadm info --attribute-walk "$SYSPATH" | grep 'input')
# If cannot find an input device, tell the server to not use the new calibration.
[ -z "$IS_INPUT" ] && exit 1

# You'd have this write a file instead.
echo "ACTION!=\"remove\",KERNEL==\"event[0-9]*\",SUBSYSTEM==\"input\",$DEVICE,ENV{LIBINPUT_CALIBRATION_MATRIX}=\"$MATRIX\"" > "$RULE"
# RUNE
# comment the calibration lines in /srv/http/.config/weston.ini
#touchscreen_calibrator=true
#calibration_helper=/bin/echo
# then stop and start weston
