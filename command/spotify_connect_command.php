#!/usr/bin/php
<?php
/*
 * Copyright (C) 2013-2014 RuneAudio Team
 * http://www.runeaudio.com
 *
 * RuneUI
 * copyright (C) 2013-2014 - Andrea Coiutti (aka ACX) & Simone De Gregori (aka Orion)
 *
 * RuneOS
 * copyright (C) 2013-2014 - Simone De Gregori (aka Orion) & Carmelo San Giovanni (aka Um3ggh1U)
 *
 * RuneAudio website and logo
 * copyright (C) 2013-2014 - ACX webdesign (Andrea Coiutti)
 *
 * This Program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3, or (at your option)
 * any later version.
 *
 * This Program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with RuneAudio; see the file COPYING. If not, see
 * <http://www.gnu.org/licenses/gpl-3.0.txt>.
 *
 *  file: command/spotify_connect_command.php
 *  version: 0.5b
 *  coder: janui
 *  date: 20-02-2019
 *
 */
// common include
ini_set('display_errors', '1');
ini_set('error_reporting', -1);
ini_set('error_log', '/var/log/runeaudio/spotify_connect_command.log');
require_once('/var/www/app/libs/runeaudio.php');
error_reporting(E_ALL & ~E_NOTICE);

// reset logfile
sysCmd('echo "--------------- start: spotify_connect_command.php ---------------" > /var/log/runeaudio/spotify_connect_command.log');
runelog('spotify_connect_command START');

// Connect to Redis backend
$redis = new Redis();
$redis->connect('/run/redis/socket');

// get the environment variables
$track_id = trim(getenv('TRACK_ID', true));
$old_track_id = trim(getenv('OLD_TRACK_ID', true));
// track id's are unique Spotify ID's
$player_event = trim(getenv('PLAYER_EVENT', true));
// player event is 'start', 'change', 'stop'
// at connect time a stop is issued, the other events work as you would expect
$event_time_stamp = time();
runelog('spotify_connect_command TRACK_ID        :'.$track_id);
runelog('spotify_connect_command OLD_TRACK_ID    :'.$old_track_id);
runelog('spotify_connect_command PLAYER_EVENT    :'.$player_event);
runelog('spotify_connect_command EVENT_TIME_STAMP:'.$event_time_stamp);
if (($track_id == '') || ($player_event == '')) {
    // a track ID and an event are essential
    runelog('spotify_connect_command - ERROR - no parameters');
    // return false
    return 0;
}

// set the event, track and time stamp in redis variables
$redis->hSet('spotifyconnect', 'event', $player_event);
$redis->hSet('spotifyconnect', 'track_id', $track_id);
$redis->hSet('spotifyconnect', 'event_time_stamp', $event_time_stamp);

// pass the command to the back-end to process the information, it needs to run as root to start and stop systemd services
$jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'spotifyconnectmsg', 'action' => $player_event, 'args' => $track_id));

// return true
return 1;
