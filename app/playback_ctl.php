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
$templateData['activePlayer'] = $redis->get('activePlayer');
// when this runs rune has just started or it is returning from a settings change, trigger redis to carry out an asynchronous database save
//  this will ensure that settings changes will not be lost if rune is unplugged without shutting down
$redis->bgSave();
//
// setup the display variables
if ($redis->get('coverart') == 1) {
    if ($redis->hGet('mpdconf', 'mixer_type') != 'hide') {
        $templateData['coverart'] = 1;
        $templateData['colspan'] = 4;
        $templateData['volume']['hide'] = 0;
    } else {
        $templateData['coverart'] = 1;
        $templateData['colspan'] = 6;
        $templateData['volume']['hide'] = 1;
    }
} else {
    if ($redis->hGet('mpdconf', 'mixer_type') != 'hide') {
        $templateData['coverart'] = 0;
        $templateData['colspan'] = 6;
        $templateData['volume']['hide'] = 0;
    } else {
        $templateData['coverart'] = 0;
        $templateData['colspan'] = 12;
        $templateData['volume']['hide'] = 1;
    }
}
if (
        ($redis->get('volume') && ($templateData['activePlayer'] == 'MPD')) ||
        ($redis->hGet('bluetooth', 'local_volume_control') && ($templateData['activePlayer'] == 'Bluetooth'))
        ) {
    $templateData['volume']['color'] = '#0095D8';
    $templateData['volume']['readonly'] = 'false';
    $templateData['volume']['disabled'] = 0;
    $templateData['volume']['divclass'] = '';
} else {
    //$_volumeColor = '#002c40';
    $templateData['volume']['color'] = '#1A242F';
    $templateData['volume']['readonly'] = 'true';
    $templateData['volume']['disabled'] = 1;
    $templateData['volume']['divclass'] = 'nomixer';
}
if (count(json_decode($redis->hGet('CD', 'status'), true)) > 0) {
    $templateData['ejectOff'] = 0;
} else {
    $templateData['ejectOff'] = 1;
}
$templateData['UIorder'] = str_split($redis->get('UIorder'));
$templateData['volume']['dynamic'] = $redis->get('dynVolumeKnob');
$templateData['dev'] = $redis->get('dev');
$templateData['spotifyconnect'] = $redis->hGet('spotifyconnect', 'enable');
$templateData['airplay'] = $redis->hGet('airplay', 'enable');
$templateData['dlna'] = $redis->hGet('dlna', 'enable');
$templateData['bluetooth'] = $redis->get('bluetooth_on');
$templateData['localSStime'] = $redis->hGet('local_browser', 'localSStime');
$templateData['remoteSStime'] = $redis->get('remoteSStime');
$templateData['hostname'] = $redis->get('hostname');
$templateData['pwd_protection'] = $redis->get('pwd_protection');
$templateData['smallScreenSaver'] = $redis->hGet('local_browser', 'smallScreenSaver');
