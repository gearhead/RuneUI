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
 *  file: command/check_connman_async.php
 *  version: 0.6
 *  coder: janui
 *  date: December 2023
 */
 // initialisation
// report errors: set display_errors to true (=1)
ini_set('display_errors', '1');
// report all PHP errors: set error_reporting to -1
ini_set('error_reporting', -1);
// set the name of the error log file
ini_set('error_log', '/var/log/runeaudio/check_connman_async.log');
// common include
require_once('/srv/http/app/libs/runeaudio.php');
// Connect to Redis backend
require_once('/srv/http/app/libs/openredis.php');
//
define('APP', '/srv/http/app/');
// reset logfile
sysCmd('echo "--------------- start: check_connman_async.php ---------------" > /var/log/runeaudio/check_connman_async.log');
runelog('WORKER check_connman_async.php STARTING...');
if (isset($argv[1]) && $argv[1]) {
    $arg = strtolower($argv[1]);
} else {
    $arg = '';
}
wrk_netconfig($redis, 'check_connman', $arg);

runelog('WORKER check_connman_async.php END...');
