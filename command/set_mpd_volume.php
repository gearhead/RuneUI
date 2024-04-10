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
 * along with RuneAudio; see the file COPYING.  If not, see
 * <http://www.gnu.org/licenses/gpl-3.0.txt>.
 *
 *  file: command/set_mpd_volume.php
 *  version: 1.5
 *  date: 27 July 2018
 *  coder: janui
 *
 */
// common include
ini_set('error_log', '/var/log/runeaudio/set_mpd_volume.log');
define('APP', '/srv/http/app/');
require_once('/srv/http/app/libs/runeaudio.php');
// Connect to Redis backend include
require_once('/srv/http/app/libs/openredis.php');

// reset logfile
sysCmd('echo "--------------- start: set_mpd_volume.php ---------------" > /var/log/runeaudio/set_mpd_volume.log');
runelog('WORKER set_mpd_volume.php STARTING...');

// what we are trying to do is set the mpd volume to preset start volume when this option is active
// and set the mpd volume to its last set value when preset start volume is inactive
if (($redis->get('activePlayer') == 'MPD') && ($redis->hGet('spotifyconnect', 'track_id') == '')) {
    // Start MPD (if  not started) in order to set the mpd volume
    wrk_mpdconf($redis, 'start');
    set_last_mpd_volume($redis);
}
