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
 *  file: command/ui_notify
 *  version: 1.3
 *  coder: Simone De Gregori
 *
 */
// common include
ini_set('display_errors', '1');
ini_set('error_reporting', -1);
ini_set('error_log','/var/log/runeaudio/ui_notify.log');
require_once('/srv/http/app/libs/runeaudio.php');
// Connect to Redis backend include
require_once('/srv/http/app/libs/openredis.php');
//
// if ((isset($argv[2])) && (!isset($argv[3]))) {
    // if (!($redis->sIsMember('w_lock', $argv[2]))) {
            // usleep(800000);
    // } else {
        // do {
            // usleep(500000);
        // } while ($redis->sIsMember('w_lock', $argv[2]));
    // }
    // $redis->close();
// } else {
    // usleep(500000);
// }
if (isset($argv[3]) && ($argv[3] === 'simplemessage')) {
    ui_notify($redis, $argv[1], $argv[2]);
} else if (isset($argv[3]) && ($argv[3] === 'permanotice')) {
    ui_notify($redis, $argv[1], $argv[2], '', 1);
} elseif (isset($argv[2]) && $argv[2]) {
    ui_notify($redis, $argv[1], $argv[2]);
} else {
    ui_notify($redis, $argv[1], '');
}
