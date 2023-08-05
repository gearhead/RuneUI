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
 *  file: command/sethost_async.php
 *  version: 0.6
 *  coder: janui
 *  date: March 2023
 */
// common include
ini_set('error_log', '/var/log/runeaudio/sethost_async.log');
define('APP', '/srv/http/app/');
require_once('/srv/http/app/libs/runeaudio.php');
// Connect to Redis backend include
require_once('/srv/http/app/libs/openredis.php');

// reset logfile
sysCmd('echo "--------------- start: sethost_async.php ---------------" > /var/log/runeaudio/sethost_async.log');
runelog('WORKER sethost_async.php STARTING...');

// check ans set HOSTNAME
$retval = sysCmd('hostname');
$hn = trim($retval[0]);
if (strtolower($hn) != strtolower(trim($redis->get('hostname')))) {
    wrk_changeHostname($redis, $redis->get('hostname'));
}
unset($retval);

runelog('WORKER sethost_async.php END...');
#---
#End script
