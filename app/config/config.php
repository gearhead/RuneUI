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
 *  file: app/config/config.php
 *  version: 1.3
 *  coder: Simone De Gregori
 *
 */
// Environment vars
if ((isset($_SERVER['HOME'])) && ($_SERVER['HOME']) && ($_SERVER['HOME'] != '/root')) {
    define('APP',$_SERVER['HOME'].'/app/');
} else {
    define('APP','/var/www/app/');
}
// extend include path for Vendor Libs
$libs = APP.'libs/vendor';
set_include_path(get_include_path() . PATH_SEPARATOR . $libs);
// RuneAudio Library include
require_once(APP.'libs/runeaudio.php');
// Connect to Redis backend include
require_once(APP.'libs/openredis.php');
// LogSettings
if ($redis->get('debug')) {
    $activeLog = '1';
} else {
    $activeLog = '0';
}
ini_set('log_errors', $activeLog);
ini_set('error_log', '/var/log/runeaudio/runeui.log');
ini_set('display_errors', $activeLog);
$socketError = true;
$socketErrorCount = 10;
// connect to MPD daemon
if ((isset($_SERVER["SCRIPT_FILENAME"])) && ($activePlayer === 'MPD') && (($_SERVER["SCRIPT_FILENAME"] === '/var/www/command/index.php') || ($_SERVER["SCRIPT_FILENAME"] === '/srv/http/command/index.php'))) {
    // debug
    runelog('[config.php] >>> OPEN MPD SOCKET [NORMAL MODE [0] (blocking)] <<<','');
    while ($socketError) {
        $mpd = openMpdSocket('/run/mpd/socket', 0);
        if (isset($mpd) && $mpd) {
            $socketError = false;
        } else {
            $socketError = true;
            sleep(2);
            $socketErrorCount--;
        }
        if ($socketErrorCount <= 0) {
            // exit the loop
            $socketError = false;
        }
    }
} else if ($activePlayer === 'MPD') {
    // debug
    runelog('[config.php] >>> OPEN MPD SOCKET [BURST MODE [1] (blocking)] <<<','');
    while ($socketError) {
        $mpd = openMpdSocket('/run/mpd/socket', 1);
        if (isset($mpd) && $mpd) {
            $socketError = false;
        } else {
            $socketError = true;
            sleep(2);
            $socketErrorCount--;
        }
        if ($socketErrorCount <= 0) {
            // exit the loop
            $socketError = false;
        }
    }
} else if (($redis->hGet('spotify', 'enable')) && ($activePlayer === 'Spotify')) {
    runelog('[config.php] >>> OPEN SPOTIFY SOCKET [BURST MODE [1] (blocking)] <<<','');
    $spop = openSpopSocket('localhost', 6602, 1);
}
