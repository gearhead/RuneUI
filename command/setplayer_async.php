#!/usr/bin/php
<?php
/*
 * Copyright (C) 2013-2015 RuneAudio Team
 * http://www.runeaudio.com
 *
 * RuneUI
 * copyright (C) 2013-2015 - Andrea Coiutti (aka ACX) & Simone De Gregori (aka Orion)
 *
 * RuneOS
 * copyright (C) 2013-2015 - Simone De Gregori (aka Orion) & Carmelo San Giovanni (aka Um3ggh1U)
 *
 * RuneAudio website and logo
 * copyright (C) 2013-2015 - ACX webdesign (Andrea Coiutti)
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
 *  file: command/setplayer_async.php
 *  version: 0.6
 *  coder: janui
 *  date: March 2023
 */
// common include
ini_set('error_log', '/var/log/runeaudio/setplayer_async.log');
define('APP', '/srv/http/app/');
require_once('/srv/http/app/libs/runeaudio.php');
// Connect to Redis backend include
require_once('/srv/http/app/libs/openredis.php');

// reset logfile
sysCmd('echo "--------------- start: setplayer_async.php ---------------" > /var/log/runeaudio/setplayer_async.log');
runelog('WORKER setplayer_async.php STARTING...');

// wake up any hdmi interfaces
sysCmd('export DISPLAY=:0 ; xrandr');
// force alsa to reload all card profiles (should not be required, but some audio devices seem to need it)
sysCmd('alsactl kill rescan');

// reset activePlayer state to MPD (default)
$redis->set('activePlayer', 'MPD');
// Refresh the MPD config file and start/restart MPD if required
wrk_mpdconf($redis, 'refresh');
// ashuffle gets started automatically

runelog('WORKER setplayer_async.php END...');
#---
#End script
