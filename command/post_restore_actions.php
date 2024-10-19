#!/usr/bin/php
<?php
@ob_end_clean(); if (ini_get('output_buffering')) ob_start();
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
 * along with RuneAudio; see the file COPYING. If not, see
 * <http://www.gnu.org/licenses/gpl-3.0.txt>.
 *
 *  file: /command/post_restore_actions.php
 *  version: 1.3
 *  coder: janui
 *  date: October 2020
 *
 */
// initialisation
// report errors: set display_errors to true (=1)
ini_set('display_errors', '1');
// report all PHP errors: set error_reporting to -1
ini_set('error_reporting', -1);
// common include
require_once('/srv/http/app/libs/runeaudio.php');
// Connect to Redis backend
require_once('/srv/http/app/libs/openredis.php');
// define APP global
define('APP', '/srv/http/app/');

// set the variables which are machine dependant, the backup could have been made on a different model
wrk_setHwPlatform($redis, true);
ui_notify($redis, 'Restore', 'Working, please wait...');
// run routines which set up <p1mountpoint>/config.txt, the local browser setup, other files are fixed on startup
wrk_audio_on_off($redis, $redis->get('audio_on_off'));
wrk_i2smodule($redis, $redis->get('i2smodule'));
$xorgEnable = $redis->hGet('local_browser', 'enable');
$redis->hSet('local_browser', 'enable', 0);
wrk_localBrowser($redis, 'enable-splash', $redis->hGet('local_browser', 'enable-splash'));
wrk_localBrowser($redis, 'zoomfactor', $redis->hGet('local_browser', 'zoomfactor'));
wrk_localBrowser($redis, 'rotate', $redis->hGet('local_browser', 'rotate'));
wrk_localBrowser($redis, 'overscan', $redis->hGet('local_browser', 'overscan'));
wrk_localBrowser($redis, 'mouse_cursor', $redis->hGet('local_browser', 'mouse_cursor'));
$redis->hSet('local_browser', 'enable', $xorgEnable);
ui_notify($redis, 'Restore', 'Working, please wait...');
$actualHostname = strtolower(trim(sysCmd('hostname | xargs')[0]));
$redisHostname = strtolower(trim($redis->get('hostname')));
if (($actualHostname != $redisHostname) && $redisHostname) {
    wrk_changeHostname($redis, $redis->get('hostname'));
}
wrk_NTPsync($redis->get('ntpserver'));
wrk_setTimezone($redis, $redis->get('timezone'));
wrk_llmnrd($redis);
ui_notify($redis, 'Restore', 'Working, please wait...');
// set up ashuffle configuration, it wont be started
$playlistName = $redis->hGet('globalrandom', 'playlist');
if (isset($playlistName) && $playlistName) {
    wrk_ashuffle($redis, 'set', $playlistName);
} else {
    wrk_ashuffle($redis, 'reset');
}
// set up Bluetooth
if ($redis->get('bluetooth_on')) {
    wrk_btcfg($redis, 'enable');
} else {
    wrk_btcfg($redis, 'disable');
}
$bluetoothCodecs = strtolower(trim(explode(':', sysCmd('bluealsad --help | grep -i a2dp-source:')[0], 2)[1]));
if (!strpos(' '.$bluetoothCodecs, 'aptx-hd')) {
    $redis-hSet('bluetooth', 'aptX_HD_codec', 0);
}
if (!strpos(' '.$bluetoothCodecs, 'faststream')) {
    $redis-hSet('bluetooth', 'FastStream_codec', 0);
}
if (!strpos(' '.$bluetoothCodecs, 'ldac')) {
    $redis-hSet('bluetooth', 'LDAC_codec', 0);
}
wrk_btcfg($redis, 'config', json_encode($redis->hgetall('bluetooth')));
wrk_btcfg($redis, 'quality_options');
wrk_btcfg($redis, 'status');
// set up Wi-Fi
if ($redis->get('wifi_on')) {
    wrk_netconfig($redis, 'enableWiFi');
} else {
    wrk_netconfig($redis, 'disableWiFi');
}
ui_notify($redis, 'Restore', 'Working, please wait...');
// refresh the nic's data store
refresh_nics($redis);
