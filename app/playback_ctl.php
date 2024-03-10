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
 *  file: app/playback_ctl.php
 *  version: 1.3
 *  coder: Simone De Gregori
 *
 */
 //
 // run ui_render in the background after the absolute time in seconds has past
$nowSeconds = microtime(true);
// delay 2 second, so add 2 to the value
$startAfterSeconds = $nowSeconds + 2;
wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'ui_render', 'action' => 'seconds', 'args' => $startAfterSeconds));
$template->activePlayer = $redis->get('activePlayer');
//
// setup the display variables
if ($redis->get('coverart') == 1) {
    if ($redis->hGet('mpdconf', 'mixer_type') != 'hide') {
        $template->coverart = 1;
        $template->colspan = 4;
        $template->volume['hide'] = 0;
    } else {
        $template->coverart = 1;
        $template->colspan = 6;
        $template->volume['hide'] = 1;
    }
} else {
    if ($redis->hGet('mpdconf', 'mixer_type') != 'hide') {
        $template->coverart = 0;
        $template->colspan = 6;
        $template->volume['hide'] = 0;
    } else {
        $template->coverart = 0;
        $template->colspan = 12;
        $template->volume['hide'] = 1;
    }
}
if (($redis->get('volume') == 1) && ($template->activePlayer == 'MPD')) {
    $template->volume['color'] = '#0095D8';
    $template->volume['readonly'] = 'false';
    $template->volume['disabled'] = 0;
    $template->volume['divclass'] = '';
} else {
    //$_volumeColor = '#002c40';
    $template->volume['color'] = '#1A242F';
    $template->volume['readonly'] = 'true';
    $template->volume['disabled'] = 1;
    $template->volume['divclass'] = 'nomixer';
}
if (count(json_decode($redis->hGet('CD', 'status'), true)) > 0) {
    $template->ejectOff = 0;
} else {
    $template->ejectOff = 1;
}
$template->UIorder = str_split($redis->get('UIorder'));
$template->volume['dynamic'] = $redis->get('dynVolumeKnob');
$template->dev = $redis->get('dev');
$template->spotifyconnect = $redis->hGet('spotifyconnect', 'enable');
$template->airplay = $redis->hGet('airplay', 'enable');
$template->dlna = $redis->hGet('dlna', 'enable');
$template->bluetooth = $redis->get('bluetooth_on');
$template->localSStime = $redis->hGet('local_browser', 'localSStime');
$template->remoteSStime = $redis->get('remoteSStime');
$template->hostname = $redis->get('hostname');
$template->pwd_protection = $redis->get('pwd_protection');
$template->smallScreenSaver = $redis->hGet('local_browser', 'smallScreenSaver');
