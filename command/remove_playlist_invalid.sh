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
#  file: command/remove_playlist_invalid.sh
#  version: 1.3
#  coder: janui
#  date: September 2021
#
# Remove invalid entries from a MPD playlist
# Parameter contains the playlist name (not the filename)
#
# set -x # echo all commands to cli
set +e # continue on errors
#
# check the parameter
if [ "$1" == "" ] ; then
    echo "No playlist name supplied as parameter 1"
    exit
fi
playlist="$1"
#
# get the location of the playlist
playlistDir=$( redis-cli hget mpdconf playlist_directory | xargs )
lastchar="${playlistDir: -1}"
if [ "$lastchar" == "/" ] ; then
    playlistDir=${playlistDir::-1}
fi
#
# get the location of the music
musicDir=$( redis-cli hget mpdconf music_directory | xargs )
lastchar="${musicDir: -1}"
if [ "$lastchar" == "/" ] ; then
    musicDir=${musicDir::-1}
fi
#
# set up the filename
filenameIn="$playlistDir/$playlist.m3u"
#
# check that the playlist file exists
if [ ! -f "$filenameIn" ] ; then
    echo "Invalid playlist, filename not found: $filenameIn"
    exit
fi
#
# read sequentially through the playlist file
# detect the invalid records
while read rec; do
    file="$musicDir/$rec"
    if [ ! -f "$file"  ] ; then
        # invalid entry found
        echo "Removing: $rec"
        # assume a small number of invalid entries, so use sed to delete the lines in the playlist
        # remove only the first occurrence from the playlist file
        # test the sed delimiters before using them, try using a '=', '#', '^', '~' and '|' before giving up
        if [ "$rec" != *"="* ] ; then
            # no '=' character in the playlist record so use it as a delimiter for sed
            sed -i "0,\=$rec={\==d;}" "$filenameIn"
        else
            if [ "$rec" != *"#"* ] ; then
                # no '#' character in the playlist record so use it as a delimiter for sed
                sed -i "0,\#$rec#{\##d;}" "$filenameIn"
            else
                if [ "$rec" != *"^"* ] ; then
                    # no '^' character in the playlist record so use it as a delimiter for sed
                    sed -i "0,\^$rec^{\^^d;}" "$filenameIn"
                else
                    if [ "$rec" != *"~"* ] ; then
                        # no '~' character in the playlist record so use it as a delimiter for sed
                        sed -i "0,\~$rec~{\~~d;}" "$filenameIn"
                    else
                        if [ "$rec" != *"|"* ] ; then
                            # no '|' character in the playlist record so use it as a delimiter for sed
                            sed -i "0,\|$rec|{\||d;}" "$filenameIn"
                        fi
                    fi
                fi
            fi
        fi
    fi
done < "$filenameIn"
#---
#End script
