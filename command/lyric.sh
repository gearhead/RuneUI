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
#  file: command/lyric.sh
#  version: 1.3
#  coder: janui
#  date: October 2020
#

available=$( redis-cli hget service makeitpersonal )
if [ "$available" != 1 ] ; then
    echo "No lyrics server available"
    exit
fi
artist_name="$1"
title_name="$2"
artist=`perl -MURI::Escape -e 'print uri_escape($ARGV[0]);' "$artist_name"`
title=`perl -MURI::Escape -e 'print uri_escape($ARGV[0]);' "$title_name"`

echo $artist
echo $title

lyric=$( curl -s -f --connect-timeout 1 -m 10 --retry 2 "https://makeitpersonal.co/lyrics?artist=$artist&title=$title" | sed ':a;N;$!ba;s/\n/<\/br>/g' | xargs -0 )

if [[ $lyric == *"something went wrong"* ]]; then
    echo "No lyrics server available"
    exit
fi

if [ "$lyric" != "" ] && [[ $lyric != *"don't have lyrics"* ]]; then
    echo $lyric
    exit
fi

colon=":"
substituteArray=("&" ";" "-" "(" "[" "{" "<" "/" "Feat." "feat." "Feat" "feat" " :" " :")
for i in "${substituteArray[@]}"
do
    artist_name=${artist_name//$i/$colon}
    title_name=${title_name//$i/$colon}
done
artist=$(echo $artist_name  | cut -d ':' -f 1 )
title=$(echo $title_name  | cut -d ':' -f 1 )
if [ "$artist" == "$artist_name" ] && [ "$title" == "$title_name" ]; then
    if [ "$lyric" != "" ]; then
        echo $lyric
    else
        echo "No lyrics available"
    fi
else
    artist=`perl -MURI::Escape -e 'print uri_escape($ARGV[0]);' "$artist"`
    title=`perl -MURI::Escape -e 'print uri_escape($ARGV[0]);' "$title"`
    lyric=$( curl -s -f --connect-timeout 1 -m 10 --retry 2 "https://makeitpersonal.co/lyrics?artist=$artist&title=$title" | sed ':a;N;$!ba;s/\n/<\/br>/g' | xargs -0 )
    if [ "$lyric" != "" ]; then
        echo $lyric
    else
        echo "No lyrics available"
    fi
fi
