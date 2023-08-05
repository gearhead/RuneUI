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
#  file: command/emergency_reboot.sh
#  version: 1.3
#  coder: janui
#  date: December 2021
#
declare -a stop_arr=(amixer-webui ashuffle avahi-daemon bluealsa bluealsa-aplay bluetooth cmd_async_queue llmnrd mpd\
    mpdscribble nmb nmbd rune_MPDEM_wrk rune_PL_wrkrune_SDM_wrk rune_SSM_wrk samba shairport-sync smb smbd spotifyd\
    systemd-udevd udevil upmpdcli)
for i in "${stop_arr[@]}" ; do
   systemctl stop "$i"
done
redis-cli shutdown save
systemctl stop redis
sync
echo 1 > /proc/sys/kernel/sysrq
echo s > /proc/sysrq-trigger
echo 1 > /proc/sys/kernel/sysrq
echo b > /proc/sysrq-trigger
