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
// delay1 = 20 : runs every 60 to 66 seconds
$delay1 = 20;
// initial wait = 60 seconds
$cnt1 = $delay1;
// delay2 = 7 : runs every 21 to 23 seconds
$delay2 = 7;
// initial wait = 9 seconds
$cnt2 = 3;
// delay3 = 20 : runs every 60 to 69 seconds
$delay3 = 20;
// initial wait = 9 seconds
$cnt3  = 3;
// loop forever
while (true) {
    sleep(3);
    if ($redis->hGet('owntone', 'enable') && $redis->hGet('owntone', 'active')) {
        if ($cnt1-- <= 0) {
            // this restarts mpd and owntone when owntone discovers new local output devices
            //  it should not happen very often as historically discovered local devices are cached and included
            $retval = wrk_owntone($redis, 'conf_add_alsa_cards');
            if ($retval == 'changed') {
                wrk_owntone($redis, 'restart');
            }
            $cnt1 = $delay1 + rand(0, 2);
        } else if ($cnt2-- <= 0) {
            // this resolves the problem when mpd starts playing to a owntone fifo file before owntone has fully initialised
            //  this should never happen, wrk_owntone($redis, 'status') is the normal processing
            $mpdError = sysCmd('mpc status 2>&1 | grep -ic error | xargs')[0];
            $owntoneRunning = wrk_systemd_unit($redis, 'is-active', 'owntone');
            $mpdRunning = wrk_systemd_unit($redis, 'is-active', 'mpd');
            if ($mpdError && $owntoneRunning && $mpdRunning) {
                wrk_owntone($redis, 'reset');
            } else {
                wrk_owntone($redis, 'status');
            }
            $cnt2 = $delay2 + rand(0, 2);
        // } else if ($cnt3-- <= 0) {
            // journalctl -u owntone | tail -n 10 | grep -ic 'raop: TEARDOWN request failed in session shutdown'
            // // this resolves the problem when owntone is stopped and restarted too quickly
            // //  this should never happen
            // $error1 = sysCmd("journalctl -u owntone | tail -n 10 | grep -ic 'raop: TEARDOWN request failed in session shutdown' | xargs")[0];
            // // $error2 = sysCmd("journalctl -u owntone | tail -n 10 | grep -ic 'player: Source is not providing sufficient data' | xargs")[0];
            // $owntoneServerPlayer = json_decode($redis->hGet('owntone', 'server_player'), true);
            // if ($error1) {
                // wrk_owntone($redis, 'restart');
            // }
            // $cnt3 = $delay3 + rand(0, 2);
        }
        //
        // this section modifies the owntone volume level of the local device when modified via the UI via MPD, Spotify, Airplay or Bluetooth
        //  too keep it responsive, it runs every 3 seconds or so
        $localOutputName = $redis->hGet('owntone', 'local_output_name');
        if ($localOutputName) {
            $localOutput = $redis->hGet('owntone_outputs', $localOutputName);
            if ($localOutput) {
                $localOutput = json_decode($localOutput, true);
                if (isset($localOutput['selected']) && $localOutput['selected']) {
                    $activePlayer = $redis->get('activePlayer');
                    if ($activePlayer == 'MPD') {
                        $localVolume = $redis->get('lastmpdvolume');
                    }
                    // for all other active players mpd knows the current volume, but lastmpdvolume is not set to that value
                    if (!isset($localVolume) || !is_numeric($localVolume)) {
                        $localVolume = preg_replace('/[^0-9]/', '', sysCmd('mpc volume | xargs')[0]);
                        if (!is_numeric($localVolume)) {
                            $retval = json_decode($redis->get('act_player_info'), true);
                            if (isset($retval['volume']) && is_numeric($retval['volume'])) {
                                $localVolume = $retval['volume'];
                            }
                            unset($retval);
                        }
                    }
                    if (is_numeric($localVolume) && isset($localOutput['volume']) && ($localVolume != $localOutput['volume'])) {
                        // local output volume has been changed via the UI and the output is active in owntone
                        // get the server
                        $server = $redis->hGet('owntone', 'server');
                        if ($server) {
                            // set up the command
                            $commandPut =
                                'curl -X PUT -s --connect-timeout 2 -m 5 --retry 2 "http://'.$server.':3689/api/outputs/'.$localOutput['id'].'"'.
                                ' --data '.
                                '"{\"volume\": '.$localVolume.
                                '}"';
                            // run the command
                            sysCmd($commandPut);
                            // get the current output data
                            // set up the command
                            $commandGet =
                                'curl -X GET -s --connect-timeout 2 -m 5 --retry 2 "http://'.$server.':3689/api/outputs/'.$localOutput['id'].'"';
                            // run the command
                            $retval = sysCmd($commandGet);
                            if (isset($retval[0])) {
                                // an array returned
                                $retval = json_decode($retval[0], true);
                                if (!isset($retval['id']) || ($localOutput['id'] != $retval['id'])) {
                                    // invalid data returned, try again
                                    $retval = sysCmd($commandGet);
                                    if (isset($retval[0])) {
                                        // an array returned
                                        $retval = json_decode($retval[0], true);
                                    } else {
                                        // no array returned, clear the values
                                        $retval = array();
                                    }
                                }
                            } else {
                                // no array returned, try again
                                $retval = sysCmd($commandGet);
                                if (isset($retval[0])) {
                                    // an array returned
                                    $retval = json_decode($retval[0], true);
                                } else {
                                    // no array returned, clear the values
                                    $retval = array();
                                }
                            }
                            if (isset($retval['id']) && ($localOutput['id'] == $retval['id'])) {
                                $localOutput = $retval;
                                // save the output when required
                                $redis->hSet('owntone_outputs', $localOutput['name'], json_encode($localOutput));
                            }
                            // check for mute
                            $localOutputPreset = $redis->hGet('owntone_presets', $localOutputName);
                            if ($localOutputPreset) {
                                $localOutputPreset = json_decode($localOutputPreset, true);
                                if (isset($localOutputPreset['mute'])) {
                                    $writePreset = false;
                                    if ($localVolume && ($localOutputPreset['mute'] != 0)) {
                                        $localOutputPreset['mute'] = 0;
                                        $writePreset = true;
                                    } else if (!$localVolume && ($localOutputPreset['mute'] != $localVolume)){
                                        $localOutputPreset['mute'] = $localVolume;
                                        $writePreset = true;
                                    }
                                    if ($writePreset) {
                                        $redis->hSet('owntone_presets', $localOutputName, json_encode($localOutputPreset));
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        // this section posts the current song metadata to the owntone metadata fifo
        //  ...
    }
}
//
runelog('WORKER owntone_monitor.php END...');
