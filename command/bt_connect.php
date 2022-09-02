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
 *  file: command/bt_connect.php
 *  version: 0.6
 *  coder: janui
 *  date: August 2022
 */
 // initialisation
// report errors: set display_errors to true (=1)
ini_set('display_errors', '1');
// report all PHP errors: set error_reporting to -1
ini_set('error_reporting', -1);
// set the name of the error log file
ini_set('error_log', '/var/log/runeaudio/bt_connect.log');
// common include
require_once('/srv/http/app/libs/runeaudio.php');
// Connect to Redis backend
require_once('/srv/http/app/libs/openredis.php');
// common include
define('APP', '/srv/http/app/');
// reset logfile
sysCmd('echo "--------------- start: bt_connect.php ---------------" > /var/log/runeaudio/bt_connect.log');
runelog('WORKER bt_connect.php STARTING...');
//
// get arguments
//  parameter has values 'add' or 'remove', and defines the action
$param = trim($argv[1]);
if (isset($argv[1]))  {
    $param = trim($argv[1]);
} else {
    echo 'Error, no parameter supplied: .../bt_connect.php add|remove';
    // exit with 22 (EINVAL): 'Invalid argument'
    exit (22);
}
if (!$param || !strpos('|add|remove|', $param)) {
    echo 'Error, invalid parameter: .../bt_connect.php add|remove';
    // exit with 22 (EINVAL): 'Invalid argument'
    exit (22);
}
// count the number of sources and sinks
$sources = sysCmd('bluealsa-cli -q list-pcms | grep a2dp | grep -i "source"');
$sourceCnt = count($sources);
// $sinks = sysCmd('bluealsa-cli -q list-pcms | grep a2dp | grep -i "sink"');
// $sinkCnt = count($sinks);
switch($param) {
    case 'add';
        // $response = sysCmd('timeout 5 stdbuf -oL bluealsa-cli -q monitor | grep -q -m 1 "^PCMAdded" || echo "0"')[0];
        // if (!$response) {
            // echo 'Error, timeout waiting for Bluetooth device to become available';
            // // exit with 62 (ETIME): 'Timer expired'
            // exit (62);
        // }
        if ($sourceCnt) {
            // start the Bluetooth player
            wrk_startPlayer($redis, 'Bluetooth');
        }
        break;
    case 'remove';
        if ($redis->get('activePlayer') == 'Bluetooth') {
            // active player is Bluetooth
            if (!$sourceCnt) {
                // no connected Bluetooth source device, stop current player 
                wrk_stopPlayer($redis);
            }
        }
        break;
}
// running wrk_btcfg status asynchronously will allow this job to terminate quickly
//  it will correct all source and sink configurations
wrk_btcfg($redis, 'status_async');
//
runelog('WORKER bt_connect.php END...');
