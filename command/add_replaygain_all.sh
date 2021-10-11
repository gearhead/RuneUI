#!/bin/bash
#
# Reworking of tag-flac-with-rg.sh and tfwrg.sh
# Originally created by Bobulous, October 2008
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
#  file: command/add_replaygain_all.sh
#  version: 1.3
#  coder: janui
#  date: June 2021
#
# Purpose:
# This script will add song and album ReplayGain tags to all all valid music files in the specified directory
#   (including subdirectories when 'scan' parameter is specified.
#
# Usage <path_to_script>replaygain_all.sh <directory> <mode> <mode> <mode>
# Where:
# <directory> must be a valid directory, this is the directory to be processed (use quotes it there are spaces in the file name)
# <mode> can optionally be defined as 'scan' to process specified directory and its subdirectories
# <mode> can optionally be defined as 'skip', which causes the script to skip the files which already have ReplayGain set
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
    mountpoint -q "$1"
    if [ $? -eq 0 ]; then
        echo "Argument 1 $1 is a mount point, continuing..."
    else
        echo "Argument 1 $1 is NOT a directory!"
        exit $ARGUMENT_NOT_DIRECTORY
    fi
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
        echo "Argument 2 $2 invalid!"
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
            echo "Argument 3 $3 invalid!"
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
            echo "Argument 4 $4 invalid!"
            exit $INVALID_ARGUMENT
        fi
    fi
fi
#
if [ $skip -eq 0 ]; then
    param="skip"
else
    param=""
fi
if [ $silent -eq 0 ]; then
    param="$param silent"
fi
#
if [ $scan -eq 0 ]; then
    dir=$(echo "$1" | tr -s /)
    dir=${dir%/}
    if [ $silent -eq 1 ]; then
        echo "********************************************************"
        echo "Using root directory : $1"
    fi
    find "$dir" -type d -exec /srv/http/command/add_replaygain_all.sh '{}' $param \;
else
    # count the number of files per file type in this directory.
    cd "$1"
    unset filetypes
    declare -a filetypes=(flac ogg oga spx opus mp2 mp3 mp4 m4a asf wma wv ape wav aiff aif)
    unset filenum
    declare -A filenum
    dirfiletypes=$( ls -1 . | grep '\.' | sed 's/^.*\.//' )
    for filetype in $dirfiletypes
    do
        # echo $filetype
        ((filenum[$filetype]++))
    done
    for filetype in "${filetypes[@]}"
    do
        if [ "" != "${filenum[$filetype]}" ]; then
            filenum[totalfiles]=$(( ${filenum[totalfiles]} +  ${filenum[$filetype]} ))
        fi
        filetype=${filetype^^}
        if [ "" != "${filenum[$filetype]}" ]; then
            filenum[totalfiles]=$(( ${filenum[totalfiles]} +  ${filenum[$filetype]} ))
        fi
    done
    #   when no music files are found in this directory, then exit without error.
    if [ "" == "${filenum[totalfiles]}" ]; then
        if [ $silent -eq 1 ]; then
            echo "$1 (No music files)"
        fi
        exit 0
    fi
    unset filetypes
    #
    unset musictypes
    declare -A musictypes
    musictypes[flac-type]="flac"
    musictypes[ogg-type]="ogg oga spx opus"
    musictypes[mp2-type]="mp2"
    musictypes[mp3-type]="mp3"
    musictypes[mp4-type]="mp4 m4a"
    musictypes[asf-type]="asf wma"
    musictypes[wavpac-type]="wv"
    musictypes[ape-type]="ape"
    musictypes[wav-type]="wav"
    musictypes[aiff-type]="aiff aif"
    unset loudgainfiles
    declare -A loudgainfiles
    for key in "${!musictypes[@]}"
    do
        declare -a filetypes=("${musictypes[$key]}")
        for filetype in $filetypes
        do
            if [ "" != "${filenum[$filetype]}" ]; then
                loudgainfiles[$filetypes]=" *.$filetype"
                filecount=$(( ${filenum[$filetype]} ))
            else
                filecount=0
            fi
            filetype=${filetype^^}
            if [ "" != "${filenum[$filetype]}" ]; then
                loudgainfiles[$filetypes]="${loudgainfiles[$filetypes]} *.$filetype"
                filecount=$(( filecount + ${filenum[$filetype]} ))
            fi
        done
        if [ "" != "${loudgainfiles[$filetypes]}" ]; then
            if [ $silent -eq 1 ]; then
                echo "$1 ($filecount $key music files:${loudgainfiles[$filetypes]})"
            fi
            if [ $skip -eq 0 ]; then
                if [ "$key" == "flac-type" ]; then
                    tagcnt=$( mediainfo ${loudgainfiles[$filetypes]} | grep -ic 'replay gain' )
                    # there should be 4 reported lines per file for fully tagged music files (this only works for flac-type)
                    tagcntl=$(( $filecount * 4 ))
                else
                    tagcnt=$( mediainfo ${loudgainfiles[$filetypes]} | grep -icE 'replaygain_album_gain|replaygain_track_gain' )
                    # there should be 2 reported lines per file for fully tagged music files (this works for mp3-type & mp4-type, others unknown)
                    tagcntl=$(( $filecount * 2 ))
                fi
                if [ $tagcnt -eq $tagcntl ]; then
                    # all files alredy have tags
                    if [ $silent -eq 1 ]; then
                        echo "$1 (All files already have ReplayGain:${loudgainfiles[$filetypes]})"
                    fi
                    # clear the variable so they will not be processed
                    unset loudgainfiles[$filetypes]
                fi
            fi
        fi
    done
    #
    if [ $silent -eq 1 ]; then
        echo "Calculating ReplayGain values for the music files..."
    fi
    #
    for loudgainfile in "${loudgainfiles[@]}"
    do
        if [ $silent -eq 1 ]; then
            loudgain -a -s e  $loudgainfile
        else
            loudgain -a -q -s e $loudgainfile
        fi
        #   error condition handling process the files one by one
        if [ "$?" != "0" ]; then
            # loudgain command failed for some reason
            # process the files individually one-by-one
            if [ $silent -eq 1 ]; then
                echo "Error, album ReplayGain failed, running on individual files"
            fi
            for file in $loudgainfile
            do
                # for each file
                if [ -d "$file" ] ; then
                    continue # its a directory not a file
                fi
                if [ $silent -eq 1 ]; then
                    loudgain -a -s e "$file"
                else
                    loudgain -a -q -s e "$file"
                fi
                # on errors just continue with the next one
                unset ?
            done
        fi
    done
fi
#
cd /home
#
exit 0
#---
#End script
