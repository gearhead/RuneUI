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
#  file: command/artist_info.sh
#  version: 1.3
#  coder: janui
#  date: October 2020
#

available=$( redis-cli hget service lastfm )
if [ "$available" != 1 ] ; then
    echo "{"error":0,"message":"The artist-information server is not available","links":[]}"
    exit
fi

artist_name=$( redis-cli hget lyrics currentartist )
if [ -z "$artist_name" ]; then
    artist_name=`mpc -f %name% | head -n 1`
fi

artist=`perl -MURI::Escape -e 'print uri_escape($ARGV[0]);' "$artist_name"`

echo $artist

artistinfo=$( curl -s -f --connect-timeout 1 -m 10 --retry 2 "http://ws.audioscrobbler.com/2.0/?method=artist.getinfo&autocorrect=1&artist=$artist&api_key=ba8ad00468a50732a3860832eaed0882&format=json" | sed ':a;N;$!ba;s/\n/<\/br>/g' | xargs -0 )

if [[ $artistinfo == *"png"* ]]; then
    echo $artistinfo
else
    colon=":"
    spacecolon=" :"
    artist_name=${artist_name//$spacecolon/$colon}
    spaceampersand=" &"
    artist_name=${artist_name//$spaceampersand/$colon}
    ampersand="&"
    artist_name=${artist_name//$ampersand/$colon}
    spacedash=" -"
    artist_name=${artist_name//$spacedash/$colon}
    dash="-"
    artist_name=${artist_name//$dash/$colon}
    case $artist_name in
        *:*)
            artist_name=$( echo "$artist_name" | cut -d ":" -f 1 )
            artist=`perl -MURI::Escape -e 'print uri_escape($ARGV[0]);' "$artist_name"`
            curl -s -f --connect-timeout 1 -m 10 --retry 2 "http://ws.audioscrobbler.com/2.0/?method=artist.getinfo&autocorrect=1&artist=$artist&api_key=ba8ad00468a50732a3860832eaed0882&format=json" | sed ':a;N;$!ba;s/\n/<\/br>/g' | xargs -0
            ;;
        *)
            echo $artistinfo
            ;;
    esac
fi
