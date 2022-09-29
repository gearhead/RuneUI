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

// set initial delay count to 5: the failed trusted output auto-connect routing runs with an initial delay of 15 seconds (5x3 = 15 seconds)
//  the main loop cycles every 3 seconds
$delayCnt = 5;

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
        // when the active player is MPD and it is not playing, but output is playing then it is Bluetooth
        $mpdPlaying = SysCmd("mpc status | grep -ic '\[playing\]'")[0];
        if (!$mpdPlaying) {
            // mpd not playing
            $musicPlaying = trim(sysCmd('grep -vihs closed /proc/asound/card?/pcm?p/sub?/hw_params | xargs')[0]);
            if ($musicPlaying) {
                // but music is playing, therefore its Bluetooth, switch the player to Bluetooth
                wrk_startPlayer($redis, "Bluetooth");
                sleep(5);
                continue;
            }
        }
    }
    if ($redis->get('activePlayer') != 'Bluetooth') {
        // the 'W: Missing RTP packet' message is produced repeatedly when the alsa output is in use (locked) by another player
        // if the last message is 'W: Missing RTP packet', pause, and check again for a different time-stamp
        $lastJournalMessage = sysCmd("journalctl -u bluealsa | tail -n 1")[0];
        if (strpos(' '.$lastJournalMessage, 'W: Missing RTP packet')) {
            // last message contains the warning
            // 2 second sleep
            sleep(2);
            $newLastJournalMessage = sysCmd("journalctl -u bluealsa | tail -n 1")[0];
            if (strpos(' '.$newLastJournalMessage, 'W: Missing RTP packet')) {
                // last message again contains the warning
                if ($lastJournalMessage != $newLastJournalMessage) {
                    // both messages contain the warning and have different time stamps
                    //  the alsa output is in use (locked) by another player, switch the player to Bluetooth
                    wrk_startPlayer($redis, "Bluetooth");
                    sleep(5);
                    continue;
                }
            }
        }
    }
    if (($redis->get('activePlayer') != 'Bluetooth') && ($delayCnt-- <= 0)) {
        // the player is not Bluetooth, run through the Bluetooth outputs
        // try connecting any Bluetooth outputs which are trusted, not blocked and not connected
        $devices = wrk_btcfg($redis, 'status');
        foreach ($devices as $device) {
            // sometime trusted auto-connect wont work, do it manually here
            if ($device['sink'] && !$device['source'] && !$device['connected'] && $device['trusted'] && !$device['blocked']) {
                // attempt to connect
                wrk_btcfg($redis, 'connect', $device['device']);
            }
        }
        // set delay count to 20: this routing runs every 1 minute (20x3 = 60 seconds)
        //  the main loop cycles every 3 seconds
        $delayCnt = 20;
    }
}
//
runelog('WORKER bt_mon_switch.php END...');
