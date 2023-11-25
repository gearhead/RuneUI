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
 *  file: command/bt_mon_switch.php
 *  version: 0.6
 *  coder: janui
 *  date: August 2022
 */
 // initialisation
// report errors: set display_errors to true (=1)
ini_set('display_errors', '1');
// report all PHP errors: set error_reporting to -1
ini_set('error_reporting', -1);
// set the name of the error log file
ini_set('error_log', '/var/log/runeaudio/bt_mon_switch.log');
// common include
require_once('/srv/http/app/libs/runeaudio.php');
// Connect to Redis backend
require_once('/srv/http/app/libs/openredis.php');
// common include
define('APP', '/srv/http/app/');
// reset logfile
sysCmd('echo "--------------- start: bt_mon_switch.php ---------------" > /var/log/runeaudio/bt_mon_switch.log');
runelog('WORKER bt_mon_switch.php STARTING...');

// set initial delay count to 3: the failed trusted output auto-connect routine runs with an initial delay of 9 seconds (3x3 = 15 seconds)
//  the main loop cycles every 3 seconds
$delayCnt = 3;

while (true) {
    sleep(3);
    if (!$redis->get('bluetooth_on')) {
        // don't do anything unless bluetooth is on
        sleep(10);
        continue;
    }
    if ($redis->get('activePlayer') === 'Bluetooth') {
        // when the active player is Bluetooth there is no need to do anything
        sleep(10);
        continue;
    }
    if (sysCmd('systemctl is-active bluetooth')[0] != 'active') {
        // can't do anything if the bluetooth service is not active
        sleep(10);
        continue;
    }
    if (sysCmd('systemctl is-active bluealsa')[0] != 'active') {
        // can't do anything if bluealsa service is not active
        sleep(10);
        continue;
    }
    if ($redis->get('activePlayer') === 'MPD') {
        // when the active player is MPD and it is not playing, but output is playing something then it is Bluetooth
        $mpdStatus = strtolower(SysCmd('mpc status 2>&1 | xargs')[0]);
        if (strpos(' '.$mpdStatus, 'mpd error')) {
            // mpd is not yet running, cant do anything yet
            sleep(10);
            continue;
        }
        if (!strpos(' '.$mpdStatus, '[playing]')) {
            // mpd not playing
            if (is_playing($redis)) {
                // but something is playing, therefore its almost certainly Bluetooth
                // examine the bluetooth connection status to determine if a Bluetooth source or sink is connected
                if (!isset($devices)) {
                    // this routine is expensive to run, so only run it when required
                    $devices = wrk_btcfg($redis, 'status');
                }
                $source_connected = false;
                $sink_connected = false;
                foreach ($devices as $device) {
                    if ($device['connected'] && $device['source']) {
                        $source_connected = true;
                    }
                    if ($device['connected'] && $device['sink']) {
                        $sink_connected = true;
                    }
                }
                if ($source_connected) {
                    // Bluetooth source is connected, switch the player to Bluetooth
                    wrk_startPlayer($redis, "Bluetooth");
                    sleep(5);
                    continue;
                }
            }
        }
    }
    if ($redis->get('activePlayer') != 'Bluetooth') {
        // get the current pcms
        $pcms = wrk_btcfg($redis, 'auto_volume');
        if (isset($pcms['input']['running']) && $pcms['input']['running']) {
            // an input pcm is running, set the volume and switch the player
            $defVolume = $redis->hGet('bluetooth', 'def_volume_in');
            if ($defVolume != -1) {
                $defVolume = round(($defVolume * 127) / 100);
                sysCmd('bluealsa-cli volume '.$pcms['input']['pcm'].' '.$defVolume);
            }
            wrk_startPlayer($redis, "Bluetooth");
            sleep(5);
            continue;
        }
        // the 'W: Missing RTP packet' message is produced repeatedly when the alsa output is in use (locked) by another player
        // if the last message is 'W: Missing RTP packet', pause, and check again for a different time-stamp
        $lastJournalMessage = sysCmd('journalctl -u bluealsa | tail -n 1')[0];
        if (strpos(' '.$lastJournalMessage, 'Missing RTP packet')) {
            // last message contains the warning
            // 2 second sleep
            sleep(2);
            $newLastJournalMessage = sysCmd("journalctl -u bluealsa | tail -n 1")[0];
            if (strpos(' '.$newLastJournalMessage, 'Missing RTP packet')) {
                // last message again contains the warning
                if ($lastJournalMessage != $newLastJournalMessage) {
                    // both messages contain the warning and have different time stamps
                    //  the alsa output is in use (locked) by another player, looks like a Bluetooth input
                    // examine the bluetooth connection status to determine if a Bluetooth source or sink is connected
                    if (!isset($devices)) {
                        // this routine is expensive to run, so only run it when required
                        $devices = wrk_btcfg($redis, 'status');
                    }
                    $source_connected = false;
                    $sink_connected = false;
                    foreach ($devices as $device) {
                        if ($device['connected'] && $device['source']) {
                            $source_connected = true;
                        }
                        if ($device['connected'] && $device['sink']) {
                            $sink_connected = true;
                        }
                    }
                    $defVolume = $redis->hGet('bluetooth', 'def_volume_in');
                    if ($source_connected && ($defVolume != -1)) {
                        // Bluetooth source is connected, and the default volume is set
                        //  set the volume and switch the player to Bluetooth
                        if ($redis->hGet('bluetooth', 'local_volume_control') != 'd') {
                            if (isset($pcms['input']['pcm']) && $pcms['input']['pcm']) {
                                $defVolume = round(($defVolume * 127) / 100);
                                sysCmd('bluealsa-cli volume '.$pcms['input']['pcm'].' '.$defVolume);
                            }
                        } else {
                            $acard = json_decode($redis->hGet('acards', $redis->get('ao')), true);
                            if (isset($acard['mixer_control']) && $acard['mixer_control']) {
                                $card = get_between_data($acard['device'], ':', ',');
                                $mixerControl = $acard['mixer_control'];
                                sysCmd('amixer -c'.$card.' sset '.$mixerControl.' '.$defVolume.'%');
                            } else {
                                if (isset($pcms['input']['pcm']) && $pcms['input']['pcm']) {
                                    $defVolume = round(($defVolume * 127) / 100);
                                    sysCmd('bluealsa-cli volume '.$pcms['input']['pcm'].' '.$defVolume);
                                }
                            }
                        }
                        wrk_startPlayer($redis, "Bluetooth");
                        sleep(5);
                        continue;
                    }
                }
            }
        }
        // if (isset($pcms['output']['running']) && !$pcms['output']['running']) {
            // // an output pcm is available but nor running, set the default volume
            // $defVolume = $redis->hGet('bluetooth', 'def_volume_out');
            // if ($defVolume != -1) {
                // $defVolume = round(($defVolume * 127) / 100);
                // sysCmd('bluealsa-cli volume '.$pcms['output']['pcm'].' '.$defVolume);
            // }
            // continue;
        // }
    }
    $acards = $redis->hgetall('acards');
    $noOutput = '1';
    foreach ($acards as $acard) {
        $card = json_decode($acard, true);
        if (strpos(' '.strtolower($card['device']), 'hw')) {
            $noOutput = '0';
            break;
        }
    }
    $bluealsaActive = sysCmd('systemctl is-active bluealsa | grep -ic active | xargs')[0];
    $bluealsaAplayActive = sysCmd('systemctl is-active bluealsa-aplay | grep -ic active | xargs')[0];
    if ($noOutput) {
        // there are no output cards, stop bluealsa and bluealsa-aplay
        sysCmd('sysetmctl stop bluealsa ; sysetmctl stop bluealsa-aplay');
    } else if (!$bluealsaActive && !$bluealsaAplayActive) {
        // there are output cards start bluealsa if required
        sysCmd('sysetmctl start bluealsa');
        sleep(4);
        sysCmd('sysetmctl start bluealsa-aplay');
    } else if ($bluealsaActive && !$bluealsaAplayActive) {
        // this will correct the situation when blualsa-aplay refuses to start with an active and running pcm
        //  the action will drop connected inputs
        set_alsa_default_card($redis);
        sysCmd('sysetmctl stop bluealsa');
        sysCmd('sysetmctl start bluealsa');
        sleep(4);
        sysCmd('sysetmctl start bluealsa-aplay');
    }
    if ($delayCnt-- <= 0) {
        // try connecting any Bluetooth outputs which are trusted, not blocked and not connected
        // examine the bluetooth connection status to determine if a Bluetooth source or sink is connected
        if (!isset($devices)) {
            // this routine is expensive to run, so only run it when required
            $devices = wrk_btcfg($redis, 'status');
        }
        if ($redis->get('activePlayer') != 'Bluetooth') {
            foreach ($devices as $device) {
                // sometime trusted auto-connect wont work, do it manually here
                if ($device['sink'] && !$device['connected'] && $device['trusted'] && !$device['blocked']) {
                    // attempt to connect
                    wrk_btcfg($redis, 'connect', $device['device']);
                }
            }
        }
        // set delay count to 3: this routing runs every 9 seconds (3x3 = 9 seconds)
        //  the main loop cycles every 3 seconds
        $delayCnt = 3;
    }
    unset($devices, $source_connected, $sink_connected, $acards, $acard, $card, $noOutput, $bluealsaActive, $bluealsaAplayActive, $defVolume);
}
//
runelog('WORKER bt_mon_switch.php END...');
