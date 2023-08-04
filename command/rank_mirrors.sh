#!/bin/bash
#
#  Adapted form Rern's rankmirrors, see: https://github.com/rern/rAudio-addons/tree/main/rankmirrors
#  Copyright (C) 2023 Rern
#  Licence GNU General Public License v3.0
#
#  The following copyright notices statements are valid in addition to original copyright and licence
#  conditions providing they are not in conflict to the original.
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
#  file: command/rank_mirrors.sh
#  version: 0.6
#  coder: janui
#  date: July 2023
#
# rank_mirrors.sh
# mitigate download errors by enable(uncomment) and
# rank servers in /etc/pacman.d/mirrorlist by download speed

sec=${args[0]}
[[ ! $sec ]] && sec=3

echo "
$bar Get latest mirrorlist of package servers ...
"
curl -sLo /tmp/mirrorlist https://github.com/archlinuxarm/PKGBUILDs/raw/master/core/pacman-mirrorlist/mirrorlist
tmplist=/tmp/mirrorlist
echo $( grep 'Generated' $tmplist | cut -d' ' -f2- )

# convert mirrorlist to url list
if grep -qs '# Server = ' $tmplist; then
    sed -i '/^\s*$/d
        /^# Server = /!d
        s/^# Server = //g
        s|/$arch/$repo||g' $tmplist
        # delete blank lines and lines not start with '# Server = ', remove '# Server = '
else
    sed -i 's/^Server = //g
        s|/$arch/$repo||g' $tmplist # already uncomment
fi

readarray servers < "$tmplist"

tmpdir=/tmp/rankmirrors
rm -rf $tmpdir && mkdir $tmpdir

echo "
$bar Get file list for download test ..."
readarray -t srcfiles <<< $( curl -sL http://mirror.archlinuxarm.org/os/ | sed -E -n '/>Arch.*gz</ {s/.*>(Arch.*gz).*/\1/; p}' )
[[ ! $srcfiles ]] && echo "$warn Download file list failed." && exit

srcL=${#srcfiles[@]}
echo "
$bar Test ${#servers[@]} servers @ $sec seconds random download + 3 pings:"
i=0
for server in ${servers[@]}; do # download from each mirror
    (( i++ ))
    speed=1
    y=5
    while [ $speed -lt 2 ] && [ $y -gt 0 ]; do
        (( y-- ))
        srcfile=${srcfiles[$(( $RANDOM % $srcL ))]}
        # echo "<a class='cgr'>Download: $srcfile</a>"
        timeout $sec curl --max-time $sec -sLo $tmpdir/srcfile $server/os/$srcfile?$( date +%s ) &
        wait
        dl=$( du -c $tmpdir | grep total | awk '{print $1}' ) # get downloaded amount
        rm -f $tmpdir/srcfile
        speed=$(( dl / sec ))
    done
    ping=$( ping -4 -c 3 -w 3 ${server/http*\:\/\/} | tail -1 | cut -d'/' -f5 )
    if [[ $ping ]]; then
        latency=$( printf %.0f $ping )
    else
        latency=999
    fi
    server0='Server = '$server'/$arch/$repo'
    dl_server+="$server0 $speed $latency\n"
    printf "%3s %-37s %11s %7s\n" $i. $server "$speed kB/s" "$latency ms"
done

rank=$( echo -e "$dl_server" | awk NF | sort -g -k4,4nr -k5n )
rankfile=$( cut -d' ' -f1-3 <<< $rank )

echo "
$info Top 3 servers:"

lines=$( head -3 <<< "$rank" | sed 's/Server = \|\/\$arch.*repo//g' )
for i in 1 2 3; do
    fields=( $( sed -n "$i p" <<< $lines ) )
    printf "%3s %-37s %11s %7s\n" $i. ${fields[0]} "${fields[1]} kB/s" "${fields[2]} ms"
done

list=/etc/pacman.d/mirrorlist
[[ ! -e $list.backup ]] && cp $list $list.backup
echo "$rankfile" > $list
rm -rf $tmpdir

echo "
$bar Update package database ..."
rm -f /var/lib/pacman/db.lck
pacman -Sy
