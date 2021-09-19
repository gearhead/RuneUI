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
#  date: January 2021
#
# Remove duplicate entries from a MPD playlist
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
# set up the filenames
filenameIn="$playlistDir/$playlist.m3u"
filenameSorted="$playlistDir/$playlist.sorted"
#
# check that the playlist file exists
if [ ! -f "$filenameIn" ] ; then
    echo "Invalid playlist, filename not found: $filenameIn"
    exit
fi
#
# sort the playlist
sort $filenameIn -o $filenameSorted
#
# read sequentially through the sorted playlist file
# detect the duplicate records
lastrec=""
while read rec; do
    if [ "$lastrec" == "$rec" ] ; then
        # duplicate found
        # assume a small number of duplicates, so use sed to delete the lines in the playlist
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
    lastrec=$rec
done < "$filenameSorted"
# delete the sorted playlist file
rm "$filenameSorted"
#---
#End script
