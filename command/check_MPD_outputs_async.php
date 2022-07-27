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
 * along with RuneAudio; see the file COPYING.  If not, see
 * <http://www.gnu.org/licenses/gpl-3.0.txt>.
 *
 *  file: command/check_MPD_outputs_async.php
 *  version: 0.5
 *  coder: janui
 *  date: January 2021
 */
// common include
ini_set('error_log', '/var/log/runeaudio/check_MPD_outputs_async.log');
define('APP', '/srv/http/app/');
require_once('/srv/http/app/libs/runeaudio.php');
// Connect to Redis backend
$redis = new Redis();
$redis->connect('/run/redis/socket');

// reset logfile
sysCmd('echo "--------------- start: check_MPD_outputs_async.php ---------------" > /var/log/runeaudio/check_MPD_outputs_async.log');
runelog('WORKER check_MPD_outputs_async.php STARTING...');

wrk_check_MPD_outputs($redis);

runelog('WORKER check_MPD_outputs_async.php END...');
