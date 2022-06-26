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
#  file: command/internet_available_async.sh
#  version: 1.3
#  coder: janui
#  date: September 2020
#
# Test to determine if an internet connection is available and check all the other services.
# This will allow graceful disabling of Rune service functionality in the UI.
#
# setup
set +e # continue on errors
# set -x # echo all commands to cli
#
# just in case something has gone wrong with the local router link, try to reconnect
# if a Wi-Fi nic is down, use IP to take the nic down manually and bring it up, connman will then reconnect automatically
#
# if connman has lost a Wi-Fi connection it should reconnect automatically, but the current version does not do it
# running 'iwctl station <nic> scan' for the Wi-Fi nics should initiate a reconnect, no real issue in running this every time this routine runs
# get a list of the nics which are down
DOWN=$(ip -o -br  address | grep -i 'down' | cut -d ' ' -f1 | xargs)
# get a list of all Wi-Fi nics
NICS=$(iw dev | grep -i interface | cut -d ' ' -f2 | xargs)
for NIC in $NICS
do
    # only for Wi-Fi nics
    if [[ "$DOWN" =~ "$NIC" ]]; then
        # only for nics which are down
        if [ -f "/tmp/$NIC.up" ]; then
            # only for nics which were previously up
            ip link set dev $NIC down
            ip link set dev $NIC up
        fi
    else
        # nic is up
        # create a file '/tmp/<nic name>.up' for each Wi-Fi interface which is up
        # the /tmp directory is a TMPFS file-system which will be recreated on reboot
        touch /tmp/$NIC.up
    fi
    # scan for wireless networks per wireless nic
    iwctl station $NIC scan
done
#
# internet
# determine if we can see google.com, this command will give up after +/-20 seconds (= timeout x retries)
wget --force-html --spider --connect-timeout=1 --timeout=10 --tries=2 https://www.google.com/ > /dev/null 2>&1
if [ $? -eq 0 ]; then
    # internet connection is available
    redis-cli hset service internet 1
    redis-cli hset service webradio 1
else
    # internet connection not available
    redis-cli hset service internet 0
    redis-cli hset service webradio 0
    redis-cli hset service dirble 0
    redis-cli hset service lastfm 0
    redis-cli hset service makeitpersonal 0
    redis-cli hset service musicbrainz 0
    redis-cli hset service coverartarchiveorg 0
    redis-cli hset service wikipedia 0
    redis-cli hset service azlyrics 0
    redis-cli hset service discogs 0
    redis-cli hset service fanarttv 0
    redis-cli hset service jamendo 0
    exit
fi
# dirble
# determine if we can see dirble.com, this command will give up after +/-20 seconds (= timeout x retries)
wget --force-html --spider --connect-timeout=1 --timeout=10 --tries=2 --max-redirect=0 https://www.dirble.com/ > /dev/null 2>&1
if [ $? -eq 0 ]; then
    # dirble is available
    redis-cli hset service dirble 1
else
#   dirble is not available
    redis-cli hset service dirble 0
fi
# last.fm
# determine if we can see ws.audioscrobbler.com, this command will give up after +/-20 seconds (= timeout x retries)
wget --force-html --spider --connect-timeout=1 --timeout=10 --tries=2 https://ws.audioscrobbler.com/ > /dev/null 2>&1
if [ $? -eq 0 ]; then
    # last.fm is available
    redis-cli hset service lastfm 1
else
    # last.fm is not available
    redis-cli hset service lastfm 0
fi
# makeitpersonal lyrics
# determine if we can see makeitpersonal.co/, this command will give up after +/-20 seconds (= timeout x retries)
wget --force-html --spider --connect-timeout=1 --timeout=10 --tries=2 https://makeitpersonal.co/ > /dev/null 2>&1
if [ $? -eq 0 ]; then
    # lyrics is available
    redis-cli hset service makeitpersonal 1
else
    # lyrics is not available
    redis-cli hset service makeitpersonal 0
fi
# musicbrainz
# determine if we can see musicbrainz.org/ws/2/, this command will give up after +/-20 seconds (= timeout x retries)
wget --force-html --spider --connect-timeout=1 --timeout=10 --tries=2 https://musicbrainz.org/ws/2/ > /dev/null 2>&1
if [ $? -eq 0 ]; then
    # musicbrainz is available
    redis-cli hset service musicbrainz 1
else
    # musicbrainz is not available
    redis-cli hset service musicbrainz 0
fi
# coverartarchive.org
# determine if we can see coverartarchive.org, this command will give up after +/-20 seconds (= timeout x retries)
wget --force-html --spider --connect-timeout=1 --timeout=10 --tries=2 https://coverartarchive.org > /dev/null 2>&1
if [ $? -eq 0 ]; then
    # coverartarchive.org is available
    redis-cli hset service coverartarchiveorg 1
else
    # coverartarchive.org is not available
    redis-cli hset service coverartarchiveorg 0
fi
# # wikipedia
# # determine if we can see upload.wikimedia.org, this command will give up after +/-20 seconds (= timeout x tries)
# wget --force-html --spider --connect-timeout=1 --timeout=10 --tries=2 https://upload.wikimedia.org > /dev/null 2>&1
# if [ $? -eq 0 ]; then
    # # wikipedia is available
    # redis-cli hset service wikipedia 1
# else
    # # wikipedia is not available
    # redis-cli hset service wikipedia 0
# fi
# # azlyrics
# # determine if we can see search.azlyrics.com/search.php, this command will give up after +/-20 seconds (= timeout x tries)
# wget --force-html --spider --connect-timeout=1 --timeout=10 --tries=2 --max-redirect=0 https://search.azlyrics.com/search.php > /dev/null 2>&1
# if [ $? -eq 0 ]; then
    # # azlyrics is available
    # redis-cli hset service azlyrics 1
# else
    # # azlyrics is not available
    # redis-cli hset service azlyrics 0
# fi
# discogs
# determine if we can see www.discogs.com/search, this command will give up after +/-20 seconds (= timeout x retries)
wget --force-html --spider --connect-timeout=1 --timeout=10 --tries=2 https://www.discogs.com/search > /dev/null 2>&1
if [ $? -eq 0 ]; then
    # discogs is available
    redis-cli hset service discogs 1
else
    # discogs is not available
    redis-cli hset service discogs 0
fi
# fanart.tv
# determine if we can see webservice.fanart.tv/v3/audio, this command will give up after +/-20 seconds (= timeout x retries)
wget --force-html --spider --connect-timeout=1 --timeout=10 --tries=2 https://webservice.fanart.tv > /dev/null 2>&1
if [ $? -eq 0 ]; then
    # fanart.tv is available
    redis-cli hset service fanarttv 1
else
    # fanart.tv is not available
    redis-cli hset service fanarttv 0
fi
# jamendo
# determine if the warning message on the jamendo website has been removed, currently the stream links provided do not work
count=$( curl -s -f --connect-timeout 1 -m 10 --retry 2 "https://developer.jamendo.com/v3.0/radios/stream" | grep -c "WARNING: The stream link returned is not more working, and it could be never fixed" )
if [ $count -eq 0 ]; then
    # the warning has gone so assume that jamendo is available
    redis-cli hset service jamendo 1
else
    # the warning is still there, jamendo is not available
    redis-cli hset service jamendo 0
fi
#---
#End script
