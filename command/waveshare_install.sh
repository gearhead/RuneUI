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
#  file: command/waveshare_install.sh
#  version: 1.3
#  coder: janui
#  date: April 2021
#
# run this file when you want to change the default calibration start settings
#   by default it is set up for a 3.5inch screen
#   all of the waveshare overlays are pre-installed
#
#
set -x # echo all commands to cli
set +e # continue on errors
#
# do nothing if the waveshare overlays are present
wsoc=$( find /boot -name waveshare*.dtbo | wc -l | xargs )
if [ $wsoc -ge 5 ]; then
    echo 'Waveshare overlays preinstalled'
    exit
fi
#
# get the latest waveshare software
cd /home
rm -r /home/LCD-show
git clone https://github.com/waveshare/LCD-show.git
cd /home/LCD-show/
#
# the waveshare installation scripts attempt to build the software, this is not required
#   the build scripts are set up for rasbian and will not work on RuneAudio
#
# the next section strips the build parts of the scripts out while leaving the required copy commands
set +x # No echo of commands to cli
FILES="/home/LCD-show/LCD*show*"
shopt -s nullglob
# replace ##sudo (###sudo or ####sudo) with #sudo
for f in $FILES
do
    sed -i "/sudo/s/#\+.sudo/#sudo/g" "$f"
done
# replace #sudo with ##sudo
for f in $FILES
do
    sed -i "/sudo/s/#sudo/##sudo/g" "$f"
done
# replace sudo with #!sudo
for f in $FILES
do
    sed -i "/sudo/s/sudo/#\!sudo/g" "$f"
done
# replace sudo  cp (sudo with any number of spaces followed by cp) with sudo cp
for f in $FILES
do
    sed -i "/sudo/s/sudo. \+.cp/sudo cp/g" "$f"
done
# replace #!sudo cp with sudo cp
for f in $FILES
do
    sed -i "/sudo/s/#\!sudo cp/sudo cp/g" "$f"
done
# replace in lines containing 'config.txt' the text 'sudo cp' with 'echo ;#!sudo cp'
for f in $FILES
do
    sed -i "/config.txt/s/sudo cp/echo ;#\!sudo cp/g" "$f"
done
# replace in lines containing 'cmdline.txt' the text 'sudo cp' with 'echo ;#!sudo cp'
for f in $FILES
do
    sed -i "/cmdline.txt/s/sudo cp/echo ;#\!sudo cp/g" "$f"
done
# replace in lines containing 'inittab' the text 'sudo cp' with 'echo ;#!sudo cp'
for f in $FILES
do
    sed -i "/inittab/s/sudo cp/echo ;#\!sudo cp/g" "$f"
done
# replace in lines containing 'rc.local' the text 'sudo cp' with 'echo ;#!sudo cp'
for f in $FILES
do
    sed -i "/rc.local/s/sudo cp/echo ;#\!sudo cp/g" "$f"
done
# replace in lines containing 'RetroPie' the text 'sudo cp' with 'echo ;#!sudo cp'
for f in $FILES
do
    sed -i "/RetroPie/s/sudo cp/echo ;#\!sudo cp/g" "$f"
done
# replace ##sudo with #sudo
for f in $FILES
do
    sed -i "/sudo/s/#\+sudo/#sudo/g" "$f"
done
# so now all sudo commands except the relevant copy commands have been commented out
# any commands replacing config.txt, cmdline.txt and inittab are also commented out
#
# now run the install scripts
set -x # echo all commands to cli
cd /home/LCD-show
sudo ./LCD28-show
sudo ./LCD32-show
sudo ./LCD32C-show
sudo ./LCD35-show
sudo ./LCD35B-show
sudo ./LCD35B-show-V2
sudo ./LCD35C-show
sudo ./LCD4-show
sudo ./LCD4C-show
if [ -f "./$1" ]; then
    echo "Setting up calibration for: $1 $2 $3"
    sudo ./$1
else
    echo "Setting up default calibration for: LCD35-show"
    sudo ./LCD35-show
fi
#
# remove some of the bigger installation files
cd /home
rm -r /home/LCD-show/nes
rm -r /home/LCD-show/mk_arcade_joystick_rpi-master
rm -r /home/LCD-show/xinput-calibrator*
#---
#End script
