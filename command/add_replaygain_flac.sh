#!/bin/bash
#
# Reworking of tag-flac-with-rg.sh and tfwrg.sh
# Originally created by Bobulous, October 2008.
# See www.bobulous.org.uk/misc/Replay-Gain-in-Linux.html
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
#  file: command/add_replaygain_flac.sh
#  version: 1.3
#  coder: janui
#  date: June 2021
#
# Purpose:
# This script will add song and album replaygain tags to all flac files in the specified directory
#   (including subdirectories when 'scan' parameter is specified.
#
# Usage <path_to_script>replaygain_flac.sh <directory> <mode> <mode> <mode>
# Where:
# <directory> must be a valid directory, this is the directory to be processed (use quotes it there are spaces in the file name)
# <mode> can optionally be defined as 'scan' to process specified directory and subdirectories
# <mode> can optionally be defined as 'skip', which causes the script to skip the files which already have replay gain set
# <mode> can optionally be defined as 'silent', which causes the script to run silently unless an error condition arises
# the <mode> parameters can be given in any order after the directory, they must be in lower case
#
#
# Error codes
FILE_NOT_FOUND=2 # 2 ENOENT No such file or directory
ERROR_WRITE_PROTECT=13 # 13 EACCES Permission denied (is Windows code 19)
ARGUMENT_NOT_DIRECTORY=20 # 20 ENOTDIR Not a directory
INVALID_ARGUMENT=22 # 22 EINVAL Invalid argument
#
if [ ! -d "$1" ]; then
    echo "Argument $1 is NOT a directory!"
    exit $ARGUMENT_NOT_DIRECTORY
fi
#
if [ ! -w "$1" ]; then
    echo "Directory $1 is is not writeable!"
    exit $ERROR_WRITE_PROTECT
fi
#
if [ -z "$2" ]; then
    silent=1
    scan=1
    skip=1
else
    if [ "silent" = "$2" ]; then
        silent=0
        scan=1
        skip=1
    elif [ "scan" = "$2" ]; then
        silent=1
        scan=0
        skip=1
    elif [ "skip" = "$2" ]; then
        silent=1
        scan=1
        skip=0
    else
        echo "Argument $2 invalid!"
        exit $INVALID_ARGUMENT
    fi
    if [ ! -z "$3" ]; then
        if [ "silent" = "$3" ]; then
            silent=0
        elif [ "scan" = "$3" ]; then
            scan=0
        elif [ "skip" = "$3" ]; then
            skip=0
        else
            echo "Argument $3 invalid!"
            exit $INVALID_ARGUMENT
        fi
    fi
    if [ ! -z "$4" ]; then
        if [ "silent" = "$4" ]; then
            silent=0
        elif [ "scan" = "$4" ]; then
            scan=0
        elif [ "skip" = "$4" ]; then
            skip=0
        else
            echo "Argument $4 invalid!"
            exit $INVALID_ARGUMENT
        fi
    fi
fi
#
if [ $scan -eq 0 ]; then
    dir=$(echo "$1" | tr -s /)
    dir=${dir%/}
    if [ $silent -eq 0 ]; then
        find "$dir" -type d -exec /srv/http/command/add_replaygain_flac.sh '{}' silent \;
    else
        echo "********************************************************"
        echo "Using root directory : $1"
        find "$dir" -type d -exec /srv/http/command/add_replaygain_flac.sh '{}' \;
    fi
else
#   count the number of FLAC and flac files in this directory.
    flacnuml=$(ls "$1" | grep -c \\.flac)
    flacnumu=$(ls "$1" | grep -c \\.FLAC)
    flacnum=$(( $flacnuml + $flacnumu ))
#   when no FLAC files are found in this directory, then exit without error.
    if [ $flacnum -lt 1 ]; then
        if [ $silent -eq 1 ]; then
            echo "$1 (No FLAC files)"
        fi
        exit 0
    else
        if [ $silent -eq 1 ]; then
            echo "$1 ($flacnum FLAC files)"
        fi
    fi
#
    cd "$1"
#
    if [ $skip -eq 1 ]; then
        skipSw=1
        if [ $flacnuml -gt 0 ]; then
            FILES="*.flac"
            for file in $FILES
            do
                if [ -d "$file" ] ; then
                    continue # its a directory not a file
                fi
                cnt=$( metaflac --show-tag=REPLAYGAIN_TRACK_GAIN --show-tag=REPLAYGAIN_ALBUM_GAIN "$file" | wc -l)
                if [ $cnt = 0 ]; then
                    skipSw=0
                fi
            done
        elif [ $flacnumu -gt 0 ]; then
            FILES="*.FLAC"
            for file in $FILES
            do
                if [ -d "$file" ] ; then
                    continue # its a directory not a file
                fi
                cnt=$( metaflac --show-tag=REPLAYGAIN_TRACK_GAIN --show-tag=REPLAYGAIN_ALBUM_GAIN "$file" | wc -l)
                if [ $cnt = 0 ]; then
                    skipSw=0
                fi
            done
        fi
        if [ $skipSw = 1 ]; then
            if [ $silent -eq 1 ]; then
                echo "$1 (All files already have Replay Gain)"
            fi
            exit 0
        fi
    fi
#
    echo "Calculating Replay Gain values for FLAC files..."
#
    if [ $flacnuml -gt 0 ]; then
        if [ $flacnumu -gt 0 ]; then
            FILES="*.flac
            *.FLAC"
            metaflac --add-replay-gain *.flac *.FLAC
        else
            FILES="*.flac"
            metaflac --add-replay-gain *.flac
        fi
    else
        if [ $flacnumu -gt 0 ]; then
            FILES="*.FLAC"
            metaflac --add-replay-gain *.FLAC
        fi
    fi

#   error condition handling process the files one by one
    if [ "$?" != "0" ]; then
#       metaflac command failed for some reason
#       e.g. the flac files in a directory have different bit-rates
#       or one of the files is not a flac file, etc.
        if [ $silent -eq 1 ]; then
            echo "Error, album gain replay failed, running on individual files"
        fi
        for file in $FILES
        do
#           for each file with an upper of lower case flac file extension
            if [ -d "$file" ] ; then
                continue # its a directory not a file
            fi
            metaflac --add-replay-gain "$file"
#           on errors just continue with the next one
        done
    fi
#
    if [ $silent -eq 1 ]; then
#       output the newly-created Replay Gain values for the FLAC
#       files in this directory.
        echo "Newly-calculated Replay Gain values:"
        for file in $FILES
        do
            if [ -d "$file" ] ; then
                continue # its a directory not a file
            fi
            if [ ! -e "$file" ]; then
                # This should not happen.
                echo "Error: file $file not found."
                exit $FILE_NOT_FOUND
            fi
            echo "File : "$file
            metaflac --show-tag=REPLAYGAIN_TRACK_GAIN --show-tag=REPLAYGAIN_ALBUM_GAIN "$file"
        done
    fi
fi

cd /home

exit 0
#---
#End script
