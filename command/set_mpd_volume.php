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
 *  file: command/set_mpd_volume.php
 *  version: 1.5
 *  date: 27 July 2018
 *  coder: janui
 *
 */
// common include
ini_set('error_log', '/var/log/runeaudio/set_mpd_volume.log');
define('APP', '/srv/http/app/');
require_once('/srv/http/app/libs/runeaudio.php');
// Connect to Redis backend include
require_once('/srv/http/app/libs/openredis.php');

// reset logfile
sysCmd('echo "--------------- start: set_mpd_volume.php ---------------" > /var/log/runeaudio/set_mpd_volume.log');
runelog('WORKER set_mpd_volume.php STARTING...');

// what we are trying to do is set the mpd volume to preset start volume
// and store a value of the last known mpd volume to be used when switching players
if (($redis->get('activePlayer') == 'MPD') && ($redis->hGet('spotifyconnect', 'track_id') == '')) {
    // Start MPD (if  not started) in order to set the startup volume (if needed and if set) then kill MPD (if required)
    wrk_mpdconf($redis, 'start');
    $mpdstartvolume = $redis->get('mpd_start_volume');
    // sometimes mpd fails to start correctly (e.g. when the incorrect audio card overlay is specified)
    // then it will never return a volume value so limit the number of retries with a counter
    if ($redis->hGet('mpdconf', 'mixer_type') != 'disabled') {
        if ($mpdstartvolume != -1 && is_numeric($mpdstartvolume) && ($mpdstartvolume >= 0) && ($mpdstartvolume <= 100)) {
            // if valid set the redis variable 'lastmpdvolume' to the pre-set volume value
            // it will get modified below to the actual set value, see note about volume steps below
            // setting the volume control based on 'lastmpdvolume' is done elsewhere
            // first save the current value
            $saveLastmpdvolume = $redis->get('lastmpdvolume');
            // set the new value
            $redis->set('lastmpdvolume', $mpdstartvolume);
            $retries_volume = 40;
            do {
                // retry getting the volume until MPD is up and returns a valid entry
                $retval = sysCmd('mpc volume | grep "volume:" | xargs');
                if (!isset($retval[0]) || !$retval[0]) {
                    // no response
                    sleep(2);
                    continue;
                }
                $retval = explode(':',trim(preg_replace('!\s+!', ' ', $retval[0])));
                if (!isset($retval[1])) {
                    // invalid response
                    sleep(2);
                    continue;
                }
                if ($retval[1] === 'n/a') {
                    // something wrong, mismatch between redis and mpd volume 'disabled' values, give up
                    // set the redis variable 'lastmpdvolume' back to its initial value
                    $redis->set('lastmpdvolume', $saveLastmpdvolume);
                    $retries_volume = 0;
                    continue;
                }
                // strip any non-numeric values from the string
                $mpdvolume = trim(preg_replace('/[^0-9]/', '', $retval[1]));
                // careful: the volume control works in steps so the return value after stetting it may not be exactly the
                //  same as the requested value
                // use a soft increase/decrease when the difference is more than 4%, otherwise directly set the pre-set value
                if ($mpdvolume && is_numeric($mpdvolume) && ($mpdvolume >= 0) && ($mpdvolume <= 100)) {
                    // a valid current volume has been returned
                    if (abs($mpdvolume - $mpdstartvolume) > 4) {
                        // set the mpd volume, do a soft increase/decrease
                        $setvolume = $mpdvolume - round((($mpdvolume-$mpdstartvolume)/2), 0, PHP_ROUND_HALF_UP);
                        $retval = sysCmd('mpc volume '.$setvolume.' | grep "volume:" | xargs');
                        $retval = explode(':',trim(preg_replace('!\s+!', ' ', $retval[0])));
                        $mpdvolume = trim(preg_replace('/[^0-9]/', '', $retval[1]));
                        // sleep 1 second before looping
                        sleep(1);
                    } else {
                        // set the mpd volume directly
                        $retval = sysCmd('mpc volume '.$mpdstartvolume.' | grep "volume:" | xargs');
                        $retval = explode(':',trim(preg_replace('!\s+!', ' ', $retval[0])));
                        $mpdvolume = trim(preg_replace('/[^0-9]/', '', $retval[1]));
                        if ($mpdvolume && is_numeric($mpdvolume) && ($mpdvolume >= 0) && ($mpdvolume <= 100)) {
                            // when $mpdvolume has a valid value we are finished
                            $retries_volume = 0;
                        } else {
                            // sleep 1 second before looping
                            sleep(1);
                        }
                    }
                } else {
                    // no valid current volume returned
                    sleep(2);
                }
            } while (--$retries_volume > 0);
            // startup volume has been set, $mpdvolume contains the current volume
            // if valid set the redis variable 'lastmpdvolume' to the current volume value
            // setting the volume control based on 'lastmpdvolume' is done elsewhere
            if (isset($mpdvolume) && $mpdvolume && is_numeric($mpdvolume) && ($mpdvolume >= 0) && ($mpdvolume <= 100)) {
                $redis->set('lastmpdvolume', $mpdvolume);
            }
        } else {
            // pre-set volume level is not set, do nothing
        }
    } else {
        // volume control is off, no nothing
    }
}
