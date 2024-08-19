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
 *  file: command/clean_music_metadata_async.php
 *  version: 0.5
 *  coder: janui
 *  date: January 2022
 */
// initialisation
// report errors: set display_errors to true (=1)
ini_set('display_errors', '1');
// report all PHP errors: set error_reporting to -1
ini_set('error_reporting', -1);
// set the name of the error log file
ini_set('error_log', '/var/log/runeaudio/clean_music_metadata_async.log');
// common include
require_once('/srv/http/app/libs/runeaudio.php');
// Connect to Redis backend
require_once('/srv/http/app/libs/openredis.php');
// reset logfile
$logfile = '/var/log/runeaudio/clean_music_metadata_async.log';
clearstatcache(true, $logfile);
if (!file_exists('/var/log/runeaudio/clean_music_metadata_async.log')) {
    sysCmd('echo "--------------- start: clean_music_metadata_async.php ---------------" > '.$logfile);
}
// logging starting message
runelog('WORKER clean_music_metadata_async.php STARTING...');
// define APP global
define('APP', '/srv/http/app/');
// get the lock status
$lock = $redis->get('lock_clean_music_metadata');
runelog('lock status ', $lock);
if (($lock === '0') || ($lock === '9')  || ($lock >= 9)) {
    // set the lock
    $redis->set('lock_clean_music_metadata', '1');
    // clean the music metadata
    wrk_clean_music_metadata($redis, $logfile);
    // unlock
    $redis->set('lock_clean_music_metadata', '0');
} else {
    runelog("LOCKED!", '');
    echo date(DATE_RFC2822)." LOCKED!";
    // just in case something goes wrong increment the lock value by 1
    // when it reaches 9 (this should never happen) it will be processed as if there is no lock
    $lock += 1;
    $redis->set('lock_clean_music_metadata', $lock);
}
runelog('lock status ', $redis->get('lock_clean_music_metadata'));
// close Redis connection
$redis->close();

runelog('WORKER clean_music_metadata_async.php END...');
