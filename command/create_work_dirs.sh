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
#  file: command/create_work_dirs.sh
#  version: 1.3
#  coder: janui
#  date: December 2020
#
set -x # echo all commands to cli
set +e # continue on errors
cd /home
#
# the work directories are created on each start up, most are in the tmpfs memory file system, see /etc/fstab
# backup could be created their, but is as default created in /home
#
# functions
function enable_overlay_art_cache {
# parameter 1 = art directory
# this routine will create a partition at the end of the micro sd card disk, mount it and then
#   mount an overly file system which is used as a persistent cache the contents of the art directory
# when the partition is already there the routine will mount it and mount the overlay file system
# we are very careful creating and using these partitions, if anything is not as expected this routine will do nothing
#   it is set up is such a way that when something goes wrong it will continue to work without the persistent cache
    artDir="$1"
    partitions=$( fdisk /dev/mmcblk0 -l | grep -ic mmcblk0p )
    if [ "$partitions" == "2" ]; then
        # standard number of partitions are there, try to create the cache partition
        # first do a dry run to create a third partition contiguously after partition 2 and collect some data
        lines=$( echo -e 'n\np\n3\n\n+1G\ny\nt\n3\n83\np\n\n\nq' | fdisk /dev/mmcblk0 | grep -iE 'mmcblk0p3|disk ' | xargs )
        if [[ "$lines" == *" 1G 83 Linux"* ]]; then
            # echo $lines
            # echo "OK"
            # the dry run could create a partition correctly
            # using the information calculate the values to create a partition at the end of the disk
            tot_sectors=$( sed 's/^.*.bytes, //' <<< "$lines" )
            tot_sectors=$( sed 's/ sectors.*//' <<< "$tot_sectors" )
            size_sectors=$( sed 's/^.*.mmcblk0p3 //' <<< "$lines" )
            size_sectors=$( echo $size_sectors | cut -d ' ' -f 3 )
            # leave 34 free sectors at the end of the disk
            begin_sector=$(( $tot_sectors-$size_sectors-34 ))
            end_sector=$(( $tot_sectors-35 ))
            new_size_sectors=$(($end_sector-$begin_sector+1))
            # now dry run the creation of the partition at the end of the disk
            lines=$( echo -e "n\np\n3\n$begin_sector\n$end_sector\ny\nt\n3\n83\np\n\n\nq" | fdisk /dev/mmcblk0 | grep -iE 'mmcblk0p3| disk ' | xargs )
            # the contents of test_text should be contained in the result
            testtext="$begin_sector $end_sector $new_size_sectors 1G 83 Linux"
            # echo $lines
            # echo $test
            if [[ "$lines" == *"$test_text"* ]]; then
                # the partition could be correctly created at the end of the disk
                # now actually create it
                lines=$( echo -e "n\np\n3\n$begin_sector\n$end_sector\ny\nt\n3\n83\np\n\n\nw" | fdisk /dev/mmcblk0 | grep -iE 'mmcblk0p3| disk ' | xargs )
                # check it again
                if [[ "$lines" == *"$test_text"* ]]; then
                    # format the partition
                    partprobe /dev/mmcblk0
                    echo y | mkfs -t ext4 /dev/mmcblk0p3
                    partprobe /dev/mmcblk0
                    # mount the partition
                    rm -r /home/cache
                    mkdir /home/cache
                    mount -o noatime,noexec /dev/mmcblk0p3 /home/cache
                    # make sure the file system is correct
                    partprobe /dev/mmcblk0
                    resize2fs /dev/mmcblk0p3
                    # create a work directory on the new mount
                    mkdir /home/cache/art
                    # mount it as the lower part of an overlay file system pointing to 'artDir'
                    artDirRoot=$( sed 's|\/[^\/]*$||g' <<< "$artDir" )
                    artDirWork="$artDirRoot/work"
                    artDirUpper="$artDirRoot/upper"
                    mkdir "$artDirWork"
                    mkdir "$artDirUpper"
                    mount -t overlay overlay_art_cache -o noatime,noexec,lowerdir=/home/cache/art,upperdir="$artDirUpper",workdir="$artDirWork" "$artDir"
                    redis-cli set overlay_art_cache 1
                fi
            fi
        fi
    else
        # looks like the cache partition has previously been created, first check it
        # we are interested in the last partition on the disk
        lines=$( fdisk /dev/mmcblk0 -l | grep -iE "mmcblk0p$partitions|disk " | xargs )
        if [[ "$lines" == *" 1G 83 Linux"* ]]; then
            # mmcblk0p? has the correct size and type
            tot_sectors=$( sed 's/^.*.bytes, //' <<< "$lines" )
            tot_sectors=$( sed 's/ sectors.*//' <<< "$tot_sectors" )
            end_sector=$( sed "s/^.*.mmcblk0p$partitions //" <<< "$lines" )
            end_sector=$( echo $end_sector | cut -d ' ' -f 2 )
            reserved_sectors=$(( $tot_sectors-$end_sector ))
            if [ "$reserved_sectors" == "35" ]; then
                # on creation we reserved 34 free sectors at the end of the disk, so this is the cache partition
                # mmcblk0p? should not be mounted and the mount point /home/cache should not be in use
                test_count1=$( grep -ic "mmcblk0p$partitions" '/proc/mounts' )
                test_count2=$( grep -ic '/home/cache' '/proc/mounts' )
                if [ "$test_count1" == "0" ] && [ "$test_count1" == "0" ]; then
                    # the persistent cache partition mmcblk0p? is not be mounted and the mount point /home/cache should is not in use
                    # mount mmcblk0p3
                    rm -r /home/cache
                    mkdir -p /home/cache
                    mount -o noatime,noexec /dev/mmcblk0p$partitions /home/cache
                    # make sure the file system is correct
                    partprobe /dev/mmcblk0
                    resize2fs /dev/mmcblk0p$partitions
                    test_count1=$( grep -ic 'overlay_art_cache' '/proc/mounts' )
                    if [ "$test_count1" == "0" ]; then
                        # the overlay art cache is not mounted
                        mkdir -p /home/cache/art
                        # mount it as the lower part of an overlay file system pointing to 'artDir'
                        artDirRoot=$( sed 's|\/[^\/]*$||g' <<< "$artDir" )
                        artDirWork="$artDirRoot/work"
                        artDirUpper="$artDirRoot/upper"
                        mkdir -p "$artDirWork"
                        mkdir -p "$artDirUpper"
                        mount -t overlay overlay_art_cache -o noatime,noexec,lowerdir=/home/cache/art,upperdir="$artDirUpper",workdir="$artDirWork" "$artDir"
                        redis-cli set overlay_art_cache 1
                    fi
                else
                    # the persistent cache partition mmcblk0p? is mountend
                    test_count1=$( grep -ic 'overlay_art_cache' '/proc/mounts' )
                    if [ "$test_count1" == "0" ]; then
                        # the overlay art cache is not mounted
                        mkdir -p /home/cache/art
                        # mount it as the lower part of an overlay file system pointing to 'artDir'
                        artDirRoot=$( sed 's|\/[^\/]*$||g' <<< "$artDir" )
                        artDirWork="$artDirRoot/work"
                        artDirUpper="$artDirRoot/upper"
                        mkdir -p "$artDirWork"
                        mkdir -p "$artDirUpper"
                        mount -t overlay overlay_art_cache -o noatime,noexec,lowerdir=/home/cache/art,upperdir="$artDirUpper",workdir="$artDirWork" "$artDir"
                        redis-cli set overlay_art_cache 1
                    else
                        # the overlay art cache is already mounted
                        redis-cli set overlay_art_cache 1
                    fi
                fi
            fi
        fi
    fi
}
#
# first create and initialise the backup directory specified by redis
#
# get the redis variable and make any duplicate trailing / into a single /
backupDir=$( redis-cli get backup_dir | tr -s / | xargs )
# remove a trailing / if it exists
backupDir="${backupDir%/}"
if [[ "$backupDir" == "" ]]; then
    # backup_dir has no value, maybe the redis database is empty or redis is not running
    # set it to default
    backupDir="/home/backup"
fi
if [[ "$backupDir" != *"tmp"* ]] && [[ "$backupDir" != *"backup"* ]]; then
    # backupDir must contain 'tmp' or 'backup', it should then never interfere with the Linux or RuneAudio
    # otherwise set it to default
    backupDir="/home/backup"
fi
# save the backupDir name in redis
redis-cli set backup_dir "$backupDir"
# create the directory , change the owner and privileges and delete its contents(if any)
mkdir -p "$backupDir"
chown -R http:http "$backupDir"
chmod -R 755 "$backupDir"
rm -fR $backupDir/*
#
# Create the directory '/run/bluealsa-monitor/asoundrc', required by the 'bluealsa-monitor' package
#
mkdir -p /run/bluealsa-monitor/asoundrc
#
# depending on the total memory and the PI model expand the tmpfs size file system used for albumart, used by MPD, Airplay & Spotify Connect
#
# get the total memory
memory=$( grep -i MemTotal /proc/meminfo | xargs  | cut -d ' ' -f 2 )
# the size of the http-tmp is based on using luakit as local browser
if [ "$memory" != "" ] && [[ "$memory" =~ ^-?[0-9]+$ ]]; then
    # memory has a value and its numeric
    if [ "$memory" -gt "1200000" ]; then
        # more than 1GB, so it is 2, 4 or 8GB, increase the size to 100MB (up to 200MB will probably be OK)
        mount -o remount,size=100M http-tmp
    elif [ "$memory" -gt "600000" ]; then
        # more than 512MB, so it is 1GB, increase the size to 50MB (up to 100Mb will probably be OK)
        mount -o remount,size=50M http-tmp
    elif [ "$memory" -gt "300000" ]; then
        # more than 256MB, so it is 512GB
        # get the model type
        model=$( redis-cli get pi_model )
        if [ "$model" == "0d" ] || [ "$model" == "12" ] || [ "$model" == "" ]; then
            # its a Pi 3 A+Pi, a Zero 2W or unknown with 512MB, multiprocessor & local browser support
            # increase the size to 20MB (up to 40Mb will probably be OK)
            mount -o remount,size=20M http-tmp
        else
            # it's probably a Pi Zero, Zero W or Pi B+ with 512MB, single processor, no local browser
            # increase the size to 30MB (up to 40Mb will probably be OK)
            mount -o remount,size=30M http-tmp
        fi
    fi
    # for 256MB or less leave the default active
fi
#
# create and initialise the albumart directory, used by MPD, Airplay & Spotify Connect
#
# get the directory name from redis
artDir=$( redis-cli get albumart_image_dir | tr -s / | xargs )
# remove a trailing / if it exists
artDir="${artDir%/}"
if [[ "$artDir" == "" ]]; then
    artDir="/srv/http/tmp/art"
fi
if [[ ! -d "$artDir" ]]; then
    mkdir -p "$artDir"
    chown -R http:http "$artDir"
    chmod 755 "$artDir"
    chmod -R 644 $artDir/*
fi
# set indicator for no overlay cache, it will be set to 1 (true) if the cache is initialised successfully
redis-cli set overlay_art_cache 0
# try initialising the overlay cache
enable_overlay_art_cache "$artDir"
#
# copy the default art to the art directory, note that all are copied as .png files even though they may be .jpg's
cp "/srv/http/assets/img/cover-default-runeaudio.png" "$artDir/none.png"
cp "/srv/http/assets/img/black.png" "$artDir/black.png"
cp "/srv/http/assets/img/airplay-default.png" "$artDir/airplay.png"
cp "/srv/http/assets/img/spotify-connect-default.png" "$artDir/spotify-connect.png"
cp "/srv/http/assets/img/cover-radio.jpg" "$artDir/radio.png"
cp "/srv/http/assets/img/Bluetooth_300x300.jpg" "$artDir/bluetooth.png"
#
# if the Spotify Connect cache is defined create the directory, normally not set, it uses large amounts of space
#   when set and instructed to use it, it contains the Spotify played music files. Note: it does not clean itself up
#
spotifyConnectCache=$( redis-cli hget spotifyconnect cache_path | tr -s / | xargs )
if [ "$spotifyConnectCache" != "" ]; then
    # remove a trailing / if it exists
    spotifyConnectCache="${spotifyConnectCache%/}"
    mkdir -p "$spotifyConnectCache"
    chown -R spotifyd.spotifyd "$spotifyConnectCache"
    chmod 755 "$spotifyConnectCache"
    chmod -R 644 $spotifyConnectCache/*
fi
#
# determine whether album art directory is a tmpfs file system
#
# convert any symlinks to the actual path
artDir=$( readlink -f "$artDir" )
# get all the tmpfs mount points
tmpfsAll=$( df -t tmpfs --output=target | grep '^/' | xargs )
# set the tmpfs switch to false
redis-cli set albumart_image_tmpfs 0
for tmpfs in $tmpfsAll ; do
    # convert any symlinks to the actual path
    tmpfs=$( readlink -f "$tmpfs" )
    if [[ "$artDir" == "$tmpfs"* ]] ; then
        # a tmpfs file path is the first part of the art directory, set the switch to true
        redis-cli set albumart_image_tmpfs 1
    fi
done
exit
#
# The code below is included for documentation purposes it is used in the image reset script
#   the code safely removes the cache partition created above
#   good to kept it here so that changes to the code above can be reviewed
#
partitions=$( fdisk /dev/mmcblk0 -l | grep -ic mmcblk0p )
if [ "$partitions" == "3" ]; then
    # looks like the cache partition has previously been created, first check it
    lines=$( fdisk /dev/mmcblk0 -l | grep -iE 'mmcblk0p3|disk ' | xargs )
    if [[ "$lines" == *" 1G 83 Linux"* ]]; then
        # the partition size is correct
        tot_sectors=$( sed 's/^.*.bytes, //' <<< "$lines" )
        tot_sectors=$( sed 's/ sectors.*//' <<< "$tot_sectors" )
        end_sector=$( sed 's/^.*.mmcblk0p3 //' <<< "$lines" )
        end_sector=$( echo $end_sector | cut -d ' ' -f 2 )
        reserved_sectors=$(( $tot_sectors-$end_sector ))
        if [ "$reserved_sectors" == "35" ]; then
            # on creation we reserved 34 free sectors at the end of the disk, so this is the cache partition
            # unmount the overlay
            umount overlay_art_cache
            # unmount the partition
            umount /dev/mmcblk0p3
            # remove the mount point
            rmdir /home/cache
            # first change the partition type to 0 (zero = undefined/empty)
            partprobe /dev/mmcblk0
            lines=$( echo -e "t\n3\n0\np\nw\n" | fdisk /dev/mmcblk0 | grep -iE 'mmcblk0p3| disk ' | xargs )
            # now remove the partition, this sometimes gives errors, but works as required
            partprobe /dev/mmcblk0
            lines=$( echo -e "d\n3\np\nw\n" | fdisk /dev/mmcblk0 | grep -iE 'mmcblk0p3| disk ' | xargs )
            partprobe /dev/mmcblk0
        fi
    fi
 fi
#
#---
#End script
