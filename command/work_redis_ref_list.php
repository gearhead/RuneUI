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
 *  file: command/work_redis_ref_list.php
 *  version: 0.6
 *  coder: janui
 *  date: March 2023
 */
//
// this script is part of the backup restore routines
// it works together with the script command/create_redis_ref_list.php
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
ini_set('error_log', '/var/log/runeaudio/work_redis_ref_list.log');
// common include
require_once('/srv/http/app/libs/runeaudio.php');
// Connect to Redis backend
require_once('/srv/http/app/libs/openredis.php');
// reset logfile
sysCmd('echo "--------------- start: work_redis_ref_list.php ---------------" > /var/log/runeaudio/work_redis_ref_list.log');
// logging starting message
runelog('WORKER work_redis_ref_list.php STARTING...');
// define APP global
define('APP', '/srv/http/app/');

clearstatcache(true, '/tmp/redis_ref_list');
if (!file_exists('/tmp/redis_ref_list')) {
    $redis->close();
    runelog('WORKER work_redis_ref_list.php Error END...');
    echo "Error: Redis ref list file not found (/tmp/redis_ref_list)\n";
    // exit with 'File not found' error code
    exit(2);
}
//
$refListArray = explode("\n", file_get_contents('/tmp/redis_ref_list'));
// remove any empty trailing array elements
while (end($refListArray) == '') {
    array_pop($refListArray);
}
$refListCnt = count($refListArray);
if (count($refListArray) < 75) {
    $redis->close();
    runelog('WORKER work_redis_ref_list.php Error END...');
    echo "Error: Invalid redis ref list file contents (/tmp/redis_ref_list)\n";
    // exit with 'No data available' exit code
    exit(61);
}
echo "$refListCnt redis variables in the reference list\n";
//
$rediskeys = sysCmd('redis-cli --scan');
$cntCheck = 0;
$cntDel = 0;
foreach ($rediskeys as $rediskey) {
    $cntCheck++;
    $keyAndType = $rediskey.' '.$redis->type($rediskey);
    if (!in_array($keyAndType, $refListArray)) {
        $redis->del($rediskey);
        // debug
        runelog('[work_redis_ref_list] Deleting invalid restored redis key: ', $rediskey);
        echo "Deleting invalid restored redis key: $rediskey\n";
        $cntDel++;
    }
}
// delete the redis ref file
unlink('/tmp/redis_ref_list');
//
// close Redis connection
$redis->close();
echo "Completed, $cntCheck restored redis keys checked and $cntDel deleted\n";
runelog('WORKER work_redis_ref_list.php END...');
#---
#End script
