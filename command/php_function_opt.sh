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
#  file: command/php_function_opt.sh
#  version: 0.7
#  coder: janui
#  date: July 2024
#
set -x # echo all commands to cli
set +e # continue on errors
cd /home
rm -r /home/function_analysis
mkdir /home/function_analysis
mkdir /home/function_analysis/functions
functionlist=$( grep '^function' /srv/http/app/libs/runeaudio.php | cut -d ' ' -f 2 | cut -d '(' -f 1 )
lastfunction=''
for i in $functionlist ; do
    if [ "$lastfunction"  != "" ] ; then
        # echo $lastfunction $i
        sed -n -e "/^function $lastfunction/,/^function $i/{  /^function $i/d; p; }" /srv/http/app/libs/runeaudio.php > "/home/function_analysis/functions/$lastfunction"
        sed -i '/^\/\/ function .*(\$.*)/q' "/home/function_analysis/functions/$lastfunction"
        sed -i '/^\/\/ function .*()/q' "/home/function_analysis/functions/$lastfunction"
        sed -i '/^\/\/ function .*(\$.*)/d' "/home/function_analysis/functions/$lastfunction"
        sed -i '/^\/\/ function .*()/d' "/home/function_analysis/functions/$lastfunction"
    fi
    lastfunction=$i
done
# echo $lastfunction
sed -n -e "/^function $lastfunction/,\$p" /srv/http/app/libs/runeaudio.php > /home/function_analysis/functions/$lastfunction
sed -i '/^\/\/ function .*(\$.*)/q' "/home/function_analysis/functions/$lastfunction"
sed -i '/^\/\/ function .*()/q' "/home/function_analysis/functions/$lastfunction"
sed -i '/^\/\/ function .*(\$.*)/d' "/home/function_analysis/functions/$lastfunction"
sed -i '/^\/\/ function .*()/d' "/home/function_analysis/functions/$lastfunction"
for i in $functionlist ; do
    echo $i  >> /home/function_analysis/functionlist.txt
    phpfiles=$( (grep -IR "$i(" /srv ; grep -Ri "$i(" /home/function_analysis/functions ) |  grep -v ':function' | grep -vE ':\s+\/\/' | grep -ve ':\s+function' | cut -d ':' -f 1 | sort -u )
    if [ "$phpfiles" == "" ] ; then
        echo $i  >> /home/function_analysis/functionlist_not_called.txt
        continue
    fi
    if [ "$phpfiles" == "/home/function_analysis/functions/$i" ] ; then
        echo $i  >> /home/function_analysis/functionlist_not_called.txt
        echo $i  >> /home/function_analysis/functionlist_called_by_itsself.txt
        continue
    fi
    for j in $phpfiles ; do
        if [ "$j" == "/srv/http/app/libs/runeaudio.php" ] ; then
            continue
        fi
        calledby=${j#/home/function_analysis/functions/}
        if [ "$calledby" == "$i" ] ; then
            echo $i  >> /home/function_analysis/functionlist_called_by_itsself.txt
            continue
        fi
        if [[ $calledby == /* ]]; then
            echo $calledby $i >> /home/function_analysis/root_x_calls_y.txt
        else
            echo $calledby $i >> /home/function_analysis/x_calls_y.txt
        fi
    done
done
sort /home/function_analysis/functionlist.txt -u -o /home/function_analysis/functionlist.txt
sort /home/function_analysis/functionlist_not_called.txt -u -o /home/function_analysis/functionlist_not_called.txt
sort /home/function_analysis/functionlist_called_by_itsself.txt -u -o /home/function_analysis/functionlist_called_by_itsself.txt
sort /home/function_analysis/x_calls_y.txt -u -o /home/function_analysis/x_calls_y.txt
sort /home/function_analysis/root_x_calls_y.txt -u -o /home/function_analysis/root_x_calls_y.txt
