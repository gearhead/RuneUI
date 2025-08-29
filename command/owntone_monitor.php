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
 *  file: command/owntone_monitor.php
 *  version: 0.6
 *  coder: janui
 *  date: May 2025
 */
//
// routine to detect and process changes to the local
//
// initialisation
// report errors: set display_errors to true (=1)
ini_set('display_errors', '1');
// report all PHP errors: set error_reporting to -1
ini_set('error_reporting', -1);
// set the name of the error log file
ini_set('error_log', '/var/log/runeaudio/owntone_monitor.log');
// common include
require_once('/srv/http/app/libs/runeaudio.php');
// Connect to Redis backend
require_once('/srv/http/app/libs/openredis.php');
//
define('APP', '/srv/http/app/');
// reset logfile
sysCmd('echo "--------------- start: owntone_monitor.php ---------------" > /var/log/runeaudio/owntone_monitor.log');
runelog('WORKER owntone_monitor.php STARTING...');
//
// delay1 = 12 : runs every 60 seconds
$delay1 = 12;
$cnt1 = $delay1;
// delay2 = 6 : runs every 30 seconds
$delay2 = 6;
$cnt2 = $delay2;
while (true) {
    sleep(5);
    if ($redis->hGet('owntone', 'enable') && ($redis->hGet('owntone', 'role') == 'server') && ($cnt1-- <= 0)) {
        $retval = wrk_owntone($redis, 'conf_add_alsa_cards');
        if ($retval == 'changed') {
            wrk_owntone($redis, 'reset');
        }
        $cnt1 = $delay1;
    }
    if ($redis->hGet('owntone', 'enable') && $redis->hGet('owntone', 'active') && ($cnt2-- <= 0)) {
        $mpdError = sysCmd('mpc status 2>&1 | grep -ic error | xargs')[0];
        $owntoneRunning = wrk_systemd_unit($redis, 'is-active', 'owntone');
        $mpdRunning = wrk_systemd_unit($redis, 'is-active', 'mpd');
        if ($mpdError && $owntoneRunning && $mpdRunning) {
            wrk_owntone($redis, 'reset');
        } else {
            wrk_owntone($redis, 'status');
        }
        $cnt2 = $delay2;
    }
}
//
runelog('WORKER owntone_monitor.php END...');
