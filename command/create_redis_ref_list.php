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
 *  file: command/create_redis_ref_list.php
 *  version: 0.6
 *  coder: janui
 *  date: March 2023
 */
//
// this script is part of the backup restore routines
// it works together with the script command/work_redis_ref_list.php
//  create_redis_ref_list.php creates a reference list of redis variables
//  work_redis_ref_list.php removes any redis variables not in the reference list
//  the reference list contains variable name and type
// usage:
//  1. run create_redis_ref_list.php on the current redis variable set
//      creates a list of the current variables
//  2. restore the redis backup file
//      replaces all the redis variables
//  3. run work_redis_ref_list.php on the restored redis variable set
//      removes any redis variables which are not included in the reference list or when their type has changed
//  4. run redis_datastore_setup with the check parameter
//      any redis variables which are required but not set will be created with default values
//
// initialisation
// report errors: set display_errors to true (=1)
ini_set('display_errors', '1');
// report all PHP errors: set error_reporting to -1
ini_set('error_reporting', -1);
// set the name of the error log file
ini_set('error_log', '/var/log/runeaudio/create_redis_ref_list.log');
// common include
require_once('/srv/http/app/libs/runeaudio.php');
// Connect to Redis backend
require_once('/srv/http/app/libs/openredis.php');
// reset logfile
sysCmd('echo "--------------- start: create_redis_ref_list.php ---------------" > /var/log/runeaudio/create_redis_ref_list.log');
// logging starting message
runelog('WORKER create_redis_ref_list.php STARTING...');
// define APP global
define('APP', '/srv/http/app/');
//
$cnt = 0;
$output = '';
$rediskeys = sysCmd('redis-cli --scan');
foreach ($rediskeys as $rediskey) {
    $output .= $rediskey.' '.$redis->type($rediskey)."\n";
    $cnt++;
}
//
file_put_contents('/tmp/redis_ref_list', $output);
//
// close Redis connection
$redis->close();
echo "Completed, $cnt redis keys registered in the reference list\n";
runelog('WORKER create_redis_ref_list.php END...');
#---
#End script
