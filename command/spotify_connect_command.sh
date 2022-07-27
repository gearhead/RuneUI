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
#  file: command/spotify_connect_command.sh
#  version: 1.3
#  coder: janui
#  date: February 2022
#
set -x # echo of commands to cli
set -e # fail on errors
# parameters and possible values (most are undocumented)
# PLAYER_EVENT: <one of the values: start, stop, play, pause, change, volumeset, load, preload, endoftrack, unavailable, preloading>
# TRACK_ID: <the spotify track ID, used in the start, stop, play, pause & change events>
# OLD_TRACK_ID: <the previous track ID, used in the change event>
# PLAY_REQUEST_ID: <unknown>
# DURATION_MS: <the duration of the track in milliseconds, used in the start, stop, play & pause events>
# POSITION_MS: <the current track play position in milliseconds, used in the start, stop, play & pause events>
# VOLUME: <the volume level, the scale in unknown , used in the in the volumeset event>
# all player events are processed
# get an ID for the queues
id=$( uuidgen | md5sum | cut -d ' ' -f 1 )
# get the event value
# start event
if [ "$PLAYER_EVENT" == "start" ]; then
    # pause MPD with mpc pause, this should not be necessary
    #   it is also done in the background worker job below, but it is too slow to avoid spotifyd
    #   crashing as the output device is in use, the background worker job will change the MPD
    #   player state to paused, but only when it is currently playing
    mpc pause
    # switch player by starting a system worker background job (rune_SY_wrk > switchplayer)
    #   by writing the command to the worker redis hash and fifo queue
    redis-cli hset w_queue "$id" '{"wrkcmd":"switchplayer","action":null,"args":"SpotifyConnect"}'
    redis-cli lpush w_queue_fifo "$id"
fi
#
# we only use the metadata PLAYER_EVENT, TRACK_ID, DURATION_MS, and POSITION_MS
# write the values to the spotify connect redis hash and fifo queue
redis-cli hset s_queue "$PLAYER_EVENT$id" "{\"event\":\"$PLAYER_EVENT\",\"track_id\":\"$TRACK_ID\",\"duration_ms\":\"$DURATION_MS\",\"position_ms\":\"$POSITION_MS\"}"
redis-cli lpush s_queue_fifo "$PLAYER_EVENT$id"
#---
#End script
