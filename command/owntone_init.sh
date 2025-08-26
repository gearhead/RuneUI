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
#  file: command/owntone_init.sh
#  version: 1.3
#  coder: janui
#  date: April 2024
#
# when owntone is enabled, create the owntone music library directory(s), fifo pipes and devices then start owntone
#
{
set -x # echo no commands to cli
set +e # continue on errors
cd /home
# only create on multiprocessor models and when multi-room is enabled
cores=$( redis-cli get cores )
owntone_enabled=$( redis-cli hget owntone enable )
if [ "$cores" != "1" ] && [ "$owntone_enabled" == "1" ] ; then
    # examine the owntone config file to determine the directories and user
    owntone_dirs=$( grep -i '\s*directories\s*=\s*{\s*\"' /etc/owntone.conf | cut -d '{' -f 2 | cut -d '}' -f 1 | xargs | sed 's/\r$//' )
    owntone_user=$( grep -i '\s*uid\s*=' /etc/owntone.conf | cut -d '=' -f 2 | xargs | sed 's/\r$//' )
    owntone_logfile=$( grep -i '\s*logfile\s*=' /etc/owntone.conf | cut -d '=' -f 2 | xargs | sed 's/\r$//' )
    # fix ownership and privilages for the ownttone log file
    if [ -f "$owntone_logfile" ] ; then
        chmod 666 "$owntone_logfile"
        chown $owntone_user:audio "$owntone_logfile"
    fi
    # there can be multiple directories specified, create each one
    for x in $owntone_dirs ; do
        # echo $x
        # remove a trailing / if it exists
        x="${x%/}"
        # create the directory, including path, if required
        if [ ! -d "$x" ]; then
            mkdir -p $x
            # change the privileges of the directory and its contents
            chmod 777 $x
            chown $owntone_user:audio $x
        fi
        if [ "$owntone_dir" == "" ] ; then
            # save the first directory, we will use this one for fifo files
            owntone_dir="$x"
            redis-cli hset owntone library_dir "$x"
            # different users need to write to this directory, change the privileges
            # chmod 777 $x
            # create the fifo pipes for mpd, spotify connect, airplay and bluetooth
            pipes='mpd sc ap bt'
            for pipe in $pipes ; do
                # echo $pipe
                redis-cli hset owntone pipe_$pipe "$x/pipe_$pipe.fifo"
                redis-cli hset owntone device_$pipe "owntone$pipe""fifo"
                if [ ! -p $x/pipe_$pipe.fifo ]; then
                    if [ -f $x/pipe_$pipe.fifo ]; then
                        rm $x/pipe_$pipe.fifo
                    fi
                    # create the fifo data and metadata files with r/w for owner, group and public
                    mkfifo -m 666 $x/pipe_$pipe.fifo
                    mkfifo -m 666 $x/pipe_$pipe.fifo.metadata
                    chown $owntone_user:audio $x/pipe_$pipe.fifo
                    chown $owntone_user:audio $x/pipe_$pipe.fifo.metadata
                    if [ -f /etc/alsa/conf.d/99_runeaudio_owntone.conf ] ; then
                        alsa_dev=$( grep -ic "pcm.owntone$pipe""fifo" /etc/alsa/conf.d/99_runeaudio_owntone.conf )
                    else
                        alsa_dev="0"
                    fi
                    # set up an alsa output for $pipe which writes to the $pipe pipe
                    if [ "$alsa_dev" == "0" ] ; then
                        cat "/srv/http/.config/owntone$pipe""fifo.alsa" >> /etc/alsa/conf.d/99_runeaudio_owntone.conf
                    fi
                    pipe_name=$( grep -ic "file.*$x/pipe_$pipe\.fifo.*\#.*$pipe.*owntone.*fifo" /etc/alsa/conf.d/99_runeaudio_owntone.conf )
                    if [ "$pipe_name" == "0" ] ; then
                        sed  -i "/file.*#.*$pipe.*owntone.*fifo/ c\    file \"$x/pipe_$pipe\.fifo\" \# the $pipe owntone fifo file name" /etc/alsa/conf.d/99_runeaudio_owntone.conf
                    fi
                fi
                rate=$( redis-cli hget owntone rate )
                rate_cnt=$( grep -i 'rate.*# owntone rate' /etc/alsa/conf.d/99_runeaudio_owntone.conf | grep -ic $rate )
                if [ "$rate_cnt" == "0" ] ; then
                    sed "/rate.*# owntone rate/ s/rate.*# owntone rate,*/rate $rate # owntone rate/" /etc/alsa/conf.d/99_runeaudio_owntone.conf
                fi
            done
        fi
        # change the ownership of the directory and its contents
        # chown -R $owntone_user:audio $x
        # change the privilages to r/w for owner and group, nothing for public
        # chmod -R 660 $x
    done
    # start owntone
    systemctl start owntone
fi
} > /var/log/runeaudio/owntone_init.log 2>&1
#---
#End script

