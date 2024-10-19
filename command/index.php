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
$response = 'OK';
// check current player backend
$activePlayer = $redis->get('activePlayer');
if (isset($_GET['switchplayer']) && $_GET['switchplayer'] !== '') {
    if ($_GET['switchplayer'] === 'MPD') {
        // switch player engine
        wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'switchplayer', 'args' => $_GET['switchplayer']));
        ui_notify($redis, 'Switch Player', 'Switch player backend started...');
    }
} else if (isset($_GET['cmd']) && $_GET['cmd']) {
    // debug
    // runelog('MPD command: ',$_GET['cmd']);
    if ($_GET['cmd'] === 'renderui') {
        if ($activePlayer === 'MPD') {
            $socket = $mpd;
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
            // ui_notify($redis, 'MPD command', $_GET['cmd']);
            if (isset($mpdSendResponse) && $mpdSendResponse) $response = readMpdResponse($mpd);
            // debug
            // ui_notify($redis, 'MPD response', $response);
            // catch any volume change set in the UI, and save its value
            //  the volume can also be set by streaming services this code is not used in those cases
            if (strpos(' '.$_GET['cmd'], 'setvol') && $mpdSendResponse && strpos(' '.$response, 'OK')) {
                // 'setvol <999>' is the command, the command was successfully sent and the response was OK, save the value
                // remove all non-numeric values from the command
                $volume = trim(preg_replace('/[^0-9]/', '', $_GET['cmd']));
                if (strlen($volume) && ($volume >= 0) && ($volume <= 100)) {
                    // $volume is set and it has a value between 0 and 100 inclusive, thus valid
                    $redis->set('lastmpdvolume', $volume);
                }
                unset($mpdSendResponse, $volume, $sign, $lastvolume);
            }
        } else if ($activePlayer === 'Bluetooth') {
            list($command, $value) = explode(' ', trim(preg_replace('/\s+/', ' ', $_GET['cmd']), 2));
            if (isset($command)) {
                $command = trim($command);
            }
            if (isset($value)) {
                $value = trim($value);
            }
            switch ($command) {
                case 'setvol':
                    $localVolumeControl = $redis->hGet('bluetooth', 'local_volume_control');
                    if (isset($value) && ($value >= 0) && ($value <= 100)  && $localVolumeControl) {
                        if ($localVolumeControl != 'd') {
                            $pcms = wrk_btcfg($redis, 'auto_volume');
                            if (isset($pcms['input']['pcm']) && $pcms['input']['pcm']) {
                                $volume = round(($value * 127) / 100);
                                $x = sysCmd('bluealsactl volume '.$pcms['input']['pcm'].' '.$volume);
                                $response = implode('\n', $x);
                            }
                        } else {
                            $acard = json_decode($redis->hGet('acards', $redis->get('ao')), true);
                            if (isset($acard['mixer_control']) && $acard['mixer_control']) {
                                $card = get_between_data($acard['device'], ':', ',');
                                $mixerControl = $acard['mixer_control'];
                                $x = sysCmd('amixer -c'.$card.' sset '.$mixerControl.' '.$value.'%');
                                $response = implode('\n', $x);
                            } else {
                                $pcms = wrk_btcfg($redis, 'auto_volume');
                                if (isset($pcms['input']['pcm']) && $pcms['input']['pcm']) {
                                    $volume = round(($value * 127) / 100);
                                    $x = sysCmd('bluealsactl volume '.$pcms['input']['pcm'].' '.$volume);
                                    $response = implode('\n', $x);
                                }
                            }
                        }
                    }
                    break;
                default:
                    break;
            }
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
}
// close Redis connection
$redis->close();
