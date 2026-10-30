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
#  file: command/remount_overlay_art_cache.sh
#  version: 1.3
#  coder: janui
#  date: October 2026
#
#  The overlay_art_cache is an overlay mount which uses upper and lower directories which both contain filesystem
#  When the upper and/or lower directories are manipulated, overlay_art_cache must be safely remounted.
#  This routine dismounts and mounts overlay_art_cache to refresh the whole structure. The current mount parameters
#  of overlay_art_cache are captured before unmounting and reused when mounting overlay_art_cache.
#
# Capture the raw kernel mount configuration line safely
mount_info=$(grep -w "^overlay_art_cache" /proc/mounts | head -n 1)
# Guardrail: Check if the overlay mount actually exists right now
if [ -z "$mount_info" ]; then
    # echo "ERROR: overlay_art_cache is not currently mounted."
    exit 1
fi
# Native Bash Word Splitting (Automatically strips all duplicate spaces)
set -- $mount_info
mount_name="$1"
mount_point="$2"
mount_type="$3"
mount_args="$4"
# Safely flush pending data and detach the filesystem structure
sync
# echo "Detaching $mount_point cleanly..."
umount -l "$mount_point"
# Retry loop logic (Try up to 3 times total)
max_attempts=3
attempt=1
sleep_time=1
while [ $attempt -le $max_attempts ]; do
    sleep $sleep_time
    # echo "Rebuilding overlay (Attempt $attempt of $max_attempts)..."
    # Try the mount and immediately verify it using mountpoint
    if mount -t "$mount_type" "$mount_name" -o "$mount_args" "$mount_point" && mountpoint -q "$mount_point"; then
        # echo "SUCCESS: $mount_point remounted ok!"
        # wait until the mount action is fully completed
        while ! grep -q "$mount_point" /proc/mounts; do
            sleep 0.1
        done
        # recover any files created in $mount_point while it was unmounted
        mkdir /tmp/ram_mirror
        mount --bind $mount_point /tmp/ram_mirror
        shopt -s nullglob dotglob
        mv /tmp/ram_mirror/art/* $mount_point 2>/dev/null
        shopt -u nullglob dotglob
        umount /tmp/ram_mirror
        rmdir /tmp/ram_mirror
        exit 0
    fi
    attempt=$((attempt + 1))
    sleep_time=$((sleep_time + 1))
done
# Failure fallback if all attempts are exhausted
# echo "FAILED: The mount could not be restored after $max_attempts attempts."
exit 1
