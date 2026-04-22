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
#  date: April 2025
#
# create the owntone music library directory(s), fifo pipes and devices then start owntone
#
{
set -x # echo no commands to cli
set +e # continue on errors
cd /home
# only create on multiprocessor models and when multi-room is enabled
cores=$( redis-cli get cores )
if [[ $cores > 1 ]] ; then
    # remove any old format alsa config files, this test can be removed in a future version
    if [ -f "/etc/alsa/conf.d/99_runeaudio_owntone.conf" ] ; then
        rm "/etc/alsa/conf.d/99_runeaudio_owntone.conf"
    fi
    # refresh the config file if it does not exist
    if [ ! -f "/etc/owntone.conf" ] ; then
        cp "/srv/http/app/confif/defaults/etc/owntone.conf" "/etc/owntone.conf"
    fi
    # always refresh the owntone tmpfiles config file, it gets modified below
    cp "/srv/http/app/confif/defaults/etc/tmpfiles.d/owntone.conf" "/etc/tmpfiles.d/owntone.conf"
    # examine the owntone config file to determine the directories and user
    owntone_dirs=$( grep -i '\s*directories\s*=\s*{\s*\"' "/etc/owntone.conf" | cut -d '{' -f 2 | cut -d '}' -f 1 | xargs | sed 's/\r$//' )
    owntone_user=$( grep -i '\s*uid\s*=' "/etc/owntone.conf" | cut -d '=' -f 2 | xargs | sed 's/\r$//' )
    owntone_logfile=$( grep -i '\s*logfile\s*=' "/etc/owntone.conf" | cut -d '=' -f 2 | xargs | sed 's/\r$//' )
    # fix ownership and privilages for the owntone log file
    if [ -f "$owntone_logfile" ] ; then
        chmod 666 "$owntone_logfile"
        chown $owntone_user:audio "$owntone_logfile"
        chmod 666 "$owntone_logfile"
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
                # empty or create the fifo files
                # first the fifo pipe
                if [ -p "$x/pipe_$pipe.fifo" ] ; then
                    # pipe exists empty it
                    dd if="$x/pipe_$pipe.fifo" iflag=nonblock of=/dev/null
                else
                    # delete the file and recreate the pipe and set its privileges
                    rm -f "$x/pipe_$pipe.fifo"
                    mkfifo -m 666 "$x/pipe_$pipe.fifo"
                    chown $owntone_user:audio "$x/pipe_$pipe.fifo"
                fi
                # then the metadata pipe
                if [ -p "$x/pipe_$pipe.fifo.metadata" ] ; then
                    # pipe exists empty it
                    dd if="$x/pipe_$pipe.fifo.metadata" iflag=nonblock of=/dev/null
                else
                    # delete the file and recreate the pipe and set its privileges
                    rm -f "$x/pipe_$pipe.fifo.metadata"
                    mkfifo -m 666 "$x/pipe_$pipe.fifo.metadata"
                    chown $owntone_user:audio "$x/pipe_$pipe.fifo.metadata"
                fi
                # the creation of the fifo data file above is too late for some systemd units, set up tmpfiles.d/owntone.conf to
                #   create the files at startup
                #   the lines of code above are still necessary, they will recreate an empty fifo files when owntone restarts
                sed -i "/pipe_$pipe.fifo/d" "/etc/tmpfiles.d/owntone.conf"
                echo "p $x/pipe_$pipe.fifo 666 $owntone_user audio -" >> "/etc/tmpfiles.d/owntone.conf"
                echo "p $x/pipe_$pipe.fifo.metadata 666 $owntone_user audio -" >> "/etc/tmpfiles.d/owntone.conf"
                if [ -f "/etc/alsa/conf.d/99-runeaudio-owntone.conf" ] ; then
                    alsa_dev=$( grep -ic "pcm.owntone$pipe""fifo" "/etc/alsa/conf.d/99-runeaudio-owntone.conf" )
                else
                    alsa_dev="0"
                fi
                # set up an alsa output for $pipe which writes to the $pipe pipe
                if [ "$alsa_dev" == "0" ] ; then
                    cat "/srv/http/.config/owntone$pipe""fifo.alsa" >> "/etc/alsa/conf.d/99-runeaudio-owntone.conf"
                fi
                pipe_name=$( grep -ic "file.*$x/pipe_$pipe\.fifo.*\#.*$pipe.*owntone.*fifo" "/etc/alsa/conf.d/99-runeaudio-owntone.conf" )
                if [ "$pipe_name" == "0" ] ; then
                    sed  -i "/file.*#.*$pipe.*owntone.*fifo/ c\    file \"$x/pipe_$pipe\.fifo\" \# the $pipe owntone fifo file name" "/etc/alsa/conf.d/99-runeaudio-owntone.conf"
                fi
            done
        fi
        # change the ownership of the directory and its contents
        # chown -R $owntone_user:audio $x
        # change the privilages to r/w for owner and group, nothing for public
        # chmod -R 660 $x
    done
    rate=$( redis-cli hget owntone rate )
    rate_cnt=$( grep -i 'rate.*# owntone rate' "/etc/alsa/conf.d/99-runeaudio-owntone.conf" | grep -ic $rate )
    if [ "$rate_cnt" != "4" ] ; then
        sed -i "/rate.*# owntone rate/ s/rate.*# owntone rate.*/rate $rate # owntone rate/" "/etc/alsa/conf.d/99-runeaudio-owntone.conf"
    fi
    format=$( redis-cli hget owntone format )
    format_cnt=$( grep -i 'format.*# owntone format' "/etc/alsa/conf.d/99-runeaudio-owntone.conf" | grep -ic $format )
    if [ "$format_cnt" != "4" ] ; then
        sed -i "/format.*# owntone format/ s/format.*# owntone format.*/format $format # owntone format/" "/etc/alsa/conf.d/99-runeaudio-owntone.conf"
    fi
    # tweak the output for mpd, this changes the use of the fifo pipe with rate conversion (S16_LE, 44.1kHz) to the fifo pipe without rate conversion
    #   the pipe without rate conversion has no plugins
    device_mpd=$( redis-cli hget owntone device_mpd )
    device_mpd="${device_mpd/fifo/FIFO}"
    redis-cli hset owntone device_mpd $device_mpd
    # start owntone
    # when the shairport-stnc config type is 'dual' and the switching override is not set stop shairport-sync and nqptp, then
    #   switch to ap1 and restart shairport-sync
    ss_conf=$( redis-cli hget airplay ss_conf )
    ot_ss_switch_override=$( redis-cli hget owntone override_airplay_switching )
    if [ "$ss_conf" == "dual" ] && [ "$ot_ss_switch_override" != "1" ]; then
        # config type is 'dual' and override is not set, switch the shairport-sync service
        active_player=$( redis-cli get activePlayer )
        if [ "$active_player" == "Airplay" ] ; then
            # Airplay is active, stop the stream and switch the playback engine to MPD
            /srv/http/command/airplay_toggle off
        fi
        systemctl stop shairport-sync
        systemctl stop nqptp
        if [ -f "/etc/systemd/system/shairport-sync.service" ] ; then
            rm /etc/systemd/system/shairport-sync.service
        fi
        ln -s /etc/systemd/system/shairport-sync-ap1.service /etc/systemd/system/shairport-sync.service
        systemctl daemon-reload
        airplay_enable=$( redis-cli hget airplay enable )
        if [ "$airplay_enable" == "1" ] ; then
            systemctl start shairport-sync
        fi
    fi
    # remove the old log, then reset the failed state, then start it
    rm -f /var/log/runeaudio/owntone.log
    systemctl -q is-failed owntone && systemctl reset-failed owntone
    systemctl start owntone
fi
} > /var/log/runeaudio/owntone_init.log 2>&1
#---
#End script

