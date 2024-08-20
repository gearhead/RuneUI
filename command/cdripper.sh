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
#  file: command/cdripper.sh
#  version: 0.6
#  coder: janui
#  date: August 2024
#
#   steps
#   1. connect a CD drive
#   2. load a music cd
#   3. create an empty directory for the ripped files
#   4. cd to the empty directory
#   5. run this script
#
#   results
#   - *.wav files are created in the empty directory
#   - a subdirectory /flac is created and filled with *.flac versions of the *.wav files
#
#   can also be used with a parameter which is the full path of the empty directory which will receive the *.wav files
#       in this case there is no need to create the directory or cd to the directory (step 3 and 4)
#
#   this script will not add metadata to the files
#
set +e # continue on errors
cdparanoia -A
if [ "$?" != "" ] ; then
    # cdparanoia failed, probably no CD drive attached of no CD in the CD drive, exit with message
    echo "Failed: Failed to detect a CD in the CD drive"
    exit 1
fi
if [ "$1" != "" ] ; then
    # there is a parameter
    if [[ $1 != /* ]] ; then
        # the parameter has no leading /, not a full path, exit with a message
        echo "Failed: '$1' is not a full path"
        exit 1
    fi
    if [ -e "$1" ] ; then
        # the parameter is an existing file or directory, exit with a message
        echo "Failed: A file or directory exists with the name '$1'"
        exit 1
    fi
    mkdir -p "$1"
    if [ ! -d "$1" ] ; then
        # the mkdir command failed to correctly create a directory, exit with a message
        echo "Failed: Failed to correctly create directory '$1'. You will need to clean up manually!"
        exit 1
    fi
    cd "$1"
fi
if [ ! -z "$( ls -A )" ]; then
    # the current directory is not empty, exit with a message
    echo "Failed: Directory '$PWD' is not empty"
    exit 1
fi
cdparanoia -XB
if [ "$?" != "" ] ; then
    # cdparanoia failed, could be any reason, exit with message
    echo "Failed: CD read failed"
    exit 1
fi
rmdir flac
mkdir flac
for filename in *.wav; do
    flac $filename -o flac/$filename.flac
done
#---
#End script
