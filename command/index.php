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
 *  file: command/index.php
 *  version: 1.3
 *  coder: Simone De Gregori
 *
 */
 // common include
if ((isset($_SERVER['HOME'])) && ($_SERVER['HOME']) && ($_SERVER['HOME'] != '/root')) {
    require_once($_SERVER['HOME'].'/app/config/config.php');
} else {
    require_once('/var/www/app/config/config.php');
}
// check current player backend
$activePlayer = $redis->get('activePlayer');
if (isset($_GET['switchplayer']) && $_GET['switchplayer'] !== '') {
    if ($_GET['switchplayer'] === 'Spotify') {
        if ($redis->hGet('spotify','enable') === '1') {
            $switchOK = 1;
        } else {
            $switchOK = 0;
        }
    }
    if ($switchOK === 1 || $_GET['switchplayer'] === 'MPD') {
        // switch player engine
        $jobID = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'switchplayer', 'args' => $_GET['switchplayer']));
        $notification = new stdClass();
        $notification->title = 'Switch Player';
        $notification->text = 'Switch player backend started...';
        wrk_notify($redis, 'startjob', $notification, $jobID);
    } else {
        ui_notify('Spotify not enabled', 'Enable and configure it under the Settings screen');
    }
} else if (isset($_GET['cmd']) && $_GET['cmd']) {
    // debug
    // runelog('MPD command: ',$_GET['cmd']);
    if ($_GET['cmd'] === 'renderui') {
        if ($activePlayer === 'MPD') {
            $socket = $mpd;
        } else if ($activePlayer === 'Spotify') {
            $socket = $spop;
        } else {
            $socket = null;
        }
        if (isset($_GET['clientUUID'])) {
            $response = ui_update($redis, $socket, $_GET['clientUUID']);
        } else {
            $response = ui_update($redis, $socket);
        }
    } else {
        if ($activePlayer === 'MPD') {
            $mpdSendResponse = sendMpdCommand($mpd, $_GET['cmd']);
            // debug
            // runelog('--- [command/index.php] --- CLOSE MPD SOCKET <<< (1) ---','');
            if (!isset($response) || !$response) $response = readMpdResponse($mpd);
            // catch any volume change set in the UI, and save its value
            //  the volume can also be set by streaming services this code is not used in those cases
            if (strpos(' '.$_GET['cmd'], 'setvol') && $mpdSendResponse && strpos(' '.$response, 'OK')) {
                // 'setvol [+|-]<999>' is the command, the command was successfully sent and the response was OK, save the value
                // remove all non-numeric values from the command
                $volume = trim(preg_replace('/[^0-9]/', '', $_GET['cmd']));
                // get the optional + or - sign
                $sign = substr(trim(preg_replace('/[^+-]/', '', $_GET['cmd'])), 0, 1);
                if ($volume && ($volume >= 0) && ($volume <= 100)) {
                    // $volume is set and it has a value between 0 and 100 inclusive, thus valid
                    if (!$sign) {
                        // no sign set, just save the set the volume
                        $redis->set('lastmpdvolume', $volume);
                    } else if ($sign === '+') {
                        // delta volume set, increase the saved volume with the value
                        // currently not used in Runeaudio
                        $lastvolume = $redis->get('lastmpdvolume');
                        $volume = $lastvolume + $volume;
                        // maximum value is 100
                        if ($volume > 100) $volume = 100;
                        $redis->set('lastmpdvolume', $volume);
                    } else if ($sign === '-') {
                        // delta volume set, decrease the saved volume with the value
                        // currently not used in Runeaudio
                        $lastvolume = $redis->get('lastmpdvolume');
                        $volume = $lastvolume - $volume;
                        // minimum value is 0
                        if ($volume < 0) $volume = 0;
                        $redis->set('lastmpdvolume', $volume);
                    }
                }
                unset($mpdSendResponse, $volume, $sign, $lastvolume);
            }
        } else if ($activePlayer === 'Spotify') {
            // MPD -> SPOP command conversion
            if ($_GET['cmd'] === 'pause') $_GET['cmd'] = 'toggle';
            if ($_GET['cmd'] === 'clear') {
                $_GET['cmd'] = 'qclear';
                $redis->hIncrBy('spotify', 'plversion', 1);
            }
            if (strpos(' '.$_GET['cmd'], 'repeat')) $_GET['cmd'] = 'repeat';
            if (strpos(' '. $_GET['cmd'], 'random')) $_GET['cmd'] = 'shuffle';
            if (strpos(' '.$_GET['cmd'], 'seek')) {
                $seek = explode(" ", $_GET['cmd']);
                $_GET['cmd'] = 'seek '.($seek[2] * 1000);
            }
            if (strpos(' '.$_GET['cmd'], 'play') && strpos($_GET['cmd'], ' ') === 4) {
                $play_track = explode(" ", $_GET['cmd']);
                $_GET['cmd'] = 'goto '.($play_track[1] + 1);
            }
            if (strpos(' '.$_GET['cmd'], 'deleteid')) {
                $remove_track = explode(" ", $_GET['cmd']);
                $_GET['cmd'] = 'qrm '.$remove_track[1];
                $redis->hIncrBy('spotify', 'plversion', 1);
            }
            sendSpopCommand($spop, $_GET['cmd']);
            $redis->hSet('spotify', 'lastcmd', $_GET['cmd']);
            if (!$response) $response = readSpopResponse($spop);
        }
    }
    echo $response;
// default response
} else {
    echo 'MPD COMMAND INTERFACE<br>';
    echo 'INTERNAL USE ONLY<br>';
    echo 'hosted on runeaudio.local:82';
}
// close palyer backend connection
if ($activePlayer === 'MPD') {
    // close MPD connection
    closeMpdSocket($mpd);
} elseif ($activePlayer === 'Spotify') {
    // close SPOP connection
    closeSpopSocket($spop);
}
// close Redis connection
$redis->close();
