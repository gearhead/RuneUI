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
    echo '{"error":0,"message":"The artist-information server is not available","links":[]}'
    exit
fi

artist_name="$1"
if [ -z "$artist_name" ]; then
    artist_name=$( mpc current | cut -s -d "-" -f 1 )
    artist_name=$( echo $artist_name | tr -s " " )
fi
if [ -z "$artist_name" ]; then
    echo '{"error":0,"message":"Invalid artist name","links":[]}'
    exit
fi

artist=`perl -MURI::Escape -e 'print uri_escape($ARGV[0]);' "$artist_name"`

echo $artist

artistinfo=$( curl -s -f --connect-timeout 1 -m 10 --retry 2 "http://ws.audioscrobbler.com/2.0/?method=artist.getinfo&autocorrect=1&artist=$artist&api_key=ba8ad00468a50732a3860832eaed0882&format=json" | sed ':a;N;$!ba;s/\n/<br>/g' | xargs -0 )

noContent=$( echo $artistinfo | grep -c '"content":""' )
bio=$( echo $artistinfo | grep -c '"bio":{' )
noSummary=$( echo $artistinfo | grep -c '"summary":""' )
if [ $bio -eq 1 ] && [ $noContent -eq 0 ]; then
    # biography information is available and biography content has a value
    echo $artistinfo
    exit
fi

# the artist has not been found, maybe 2 artist names with some sort of connecting character or string
artist=${artist_name//\\/}
colon=":"
substituteArray=("&" ";" "-" "(" "[" "{" "<" "/" " Feat. " " feat. " " Feat " " feat " " ft " " ft. " " Ft " " Ft. " " featuring " " Featuring " " vs. " " vs " " Vs. " " Vs ")
for i in "${substituteArray[@]}"; do
    artist=${artist//$i/$colon}
done
artist=$( echo $artist | cut -d ':' -f 1 )
artist=$( echo $artist | tr -s " " )

if [ "$artist" == "$artist_name" ]; then
    substituteArray=("\?" "#" "@" "!" "$" "\*" "+" "," "=")
    space=" "
    for i in "${substituteArray[@]}"; do
        artist=${artist//$i/$space}
    done
    artist=$( echo $artist | tr -s " " )
fi

if [ "$artist" == "$artist_name" ]; then
    if [ $bio -eq 1 ] && [ $noSummary -eq 0 ]; then
        echo $artistinfo
    else
        echo '{"error":0,"message":"Unknown error","links":[]}'
    fi
    exit
fi

artist=`perl -MURI::Escape -e 'print uri_escape($ARGV[0]);' "$artist"`
artistinfo=$( curl -s -f --connect-timeout 1 -m 10 --retry 2 "http://ws.audioscrobbler.com/2.0/?method=artist.getinfo&autocorrect=1&artist=$artist&api_key=ba8ad00468a50732a3860832eaed0882&format=json" | sed ':a;N;$!ba;s/\n/<br>/g' | xargs -0 )
bio=$( echo $artistinfo | grep -c '"bio":{' )
if [ $bio -eq 1 ]; then
    echo $artistinfo
else
    echo '{"error":0,"message":"Unknown error","links":[]}'
fi
