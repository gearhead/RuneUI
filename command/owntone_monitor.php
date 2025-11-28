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
// initialise some things for the metadata routine
$actPlayerInfoSave = array();
// $playStarted = false;
$serverHostname = $redis->hGet('owntone', 'server_hostname');
// calculate an offset for timeRTP so that we use values which will not loop out of bounds
//  timeRTPOffset is added to timeRTP values to keep the initial value between (1000000000 and 2000000000)
$timeRTP = substr(round(microtime(true) * 44100), -9);
$timeRTP1stChar = substr($timeRTP, 0, 1);
if ($timeRTP1stChar == 0) {
    $timeRTPOffset = 1000000000;
} else if ($timeRTP1stChar > 1) {
    $timeRTPOffset = ($timeRTP1stChar - 1) * -1000000000;
} else {
    $timeRTPOffset = 0;
}
$renderedClients = array();
// cycle delay times
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
// sleep time initialisation
$sleepUntilTime = microtime(true) + 3;
// loop forever
while (true) {
    // 3 second sleep, always sleep for a minimum of 1 second
    //  some of the actions below could take much longer than 3 seconds
    $sleepTime = $sleepUntilTime - microtime(true);
    if ($sleepTime > 1) {
        $sleepTime = intval($sleepTime * 1000000);
        usleep($sleepTime);
    } else {
        sleep(1);
    }
    $sleepUntilTime = microtime(true) + 3;
    //
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
        //  when automute is active this routine is skipped, automute happens when pause or stop is clicked in the UI
        //      the idea of automute is to give a feeling of responsiveness when stopping play, in reality owntone will continue playing for a couple of seconds
        $localOutputName = $redis->hGet('owntone', 'local_output_name');
        $now = time();
        $automuteDelay = $redis->hGet('owntone', 'unmute_delay');
        $automuteTime = $redis->hGet('owntone', 'automute');
        if ($localOutputName && ($now > ($automuteTime + $automuteDelay))) {
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
        // this section posts the current song metadata to owntone via its api
        //  it seems not to work as expected, but it costs little to process
        $actPlayerInfo = json_decode($redis->get('act_player_info'), true);
        if ((!isset($actPlayerInfoSave['currentalbumartist']) || (isset($actPlayerInfo['currentalbumartist']) &&
                ($actPlayerInfoSave['currentalbumartist'] != $actPlayerInfo['currentalbumartist']))) ||
                (!isset($actPlayerInfoSave['currentartist']) || (isset($actPlayerInfo['currentartist']) &&
                ($actPlayerInfoSave['currentartist'] != $actPlayerInfo['currentartist']))) ||
                (!isset($actPlayerInfoSave['currentalbum']) || (isset($actPlayerInfo['currentalbum']) &&
                ($actPlayerInfoSave['currentalbum'] != $actPlayerInfo['currentalbum']))) ||
                (!isset($actPlayerInfoSave['currentsong']) || (isset($actPlayerInfo['currentsong']) &&
                ($actPlayerInfoSave['currentsong'] != $actPlayerInfo['currentsong']))) ||
                (!isset($actPlayerInfoSave['currentcomposer']) || (isset($actPlayerInfo['currentcomposer']) &&
                ($actPlayerInfoSave['currentcomposer'] != $actPlayerInfo['currentcomposer']))) ||
                (!isset($actPlayerInfoSave['genre']) || (isset($actPlayerInfo['genre']) &&
                ($actPlayerInfoSave['genre'] != $actPlayerInfo['genre']))) ||
                (!isset($actPlayerInfoSave['mainArtURL']) || (isset($actPlayerInfo['mainArtURL']) &&
                ($actPlayerInfoSave['mainArtURL'] != $actPlayerInfo['mainArtURL']))) ||
                (!isset($actPlayerInfoSave['file']) || (isset($actPlayerInfo['file']) &&
                ($actPlayerInfoSave['file'] != $actPlayerInfo['file'])))) {
            $server = $redis->hGet('owntone', 'server');
            $serverPlayer = $redis->hGet('owntone', 'server_player');
            if (isset($serverPlayer) && $serverPlayer) {
                $serverPlayer = json_decode($serverPlayer, true);
            } else {
                $serverPlayer = array();
            }
            if ($server && isset($serverPlayer['state']) && ($serverPlayer['state'] == 'play')) {
                $actPlayerInfoSave = $actPlayerInfo;
                $serverHostname = $redis->hGet('owntone', 'server_hostname');
                $serverIpAddress = $redis->hGet('owntone', 'server_ip_address');
                $commandPut = 'curl -X PUT -s --connect-timeout 2 -m 5 --retry 2 "http://'.$server.':3689/api/queue/items/now_playing?';
                if (isset($actPlayerInfo['currentsong']) && trim($actPlayerInfo['currentsong'])) {
                    $commandPut .= 'title='.urlencode($actPlayerInfo['currentsong']).'&';
                } else {
                    $commandPut .= 'title='.urlencode('Unknown Title').'&';
                }
                if (isset($actPlayerInfo['currentalbum']) && trim($actPlayerInfo['currentalbum'])) {
                    $commandPut .= 'album='.urlencode($actPlayerInfo['currentalbum']).'&';
                } else {
                    $commandPut .= 'album='.urlencode('Unknown Album').'&';
                }
                if (isset($actPlayerInfo['currentartist']) && trim($actPlayerInfo['currentartist'])) {
                    $commandPut .= 'artist='.urlencode($actPlayerInfo['currentartist']).'&';
                } else {
                    $commandPut .= 'artist='.urlencode('Unknown Artist').'&';
                }
                if (isset($actPlayerInfo['currentalbumartist']) && trim($actPlayerInfo['currentalbumartist'])) {
                    $commandPut .= 'album_artist='.urlencode($actPlayerInfo['currentalbumartist']).'&';
                } else {
                    $commandPut .= 'album_artist='.urlencode('Unknown Artist').'&';
                }
                if (isset($actPlayerInfo['currentcomposer']) && trim($actPlayerInfo['currentcomposer'])) {
                    $commandPut .= 'composer='.urlencode($actPlayerInfo['currentcomposer']).'&';
                } else {
                    $commandPut .= 'composer='.urlencode('Unknown Composer').'&';
                }
                if (isset($actPlayerInfo['genre']) && trim($actPlayerInfo['genre'])) {
                    $commandPut .= 'genre='.urlencode($actPlayerInfo['genre']).'&';
                } else {
                    $commandPut .= 'genre='.urlencode('Unknown Genre').'&';
                }
                if (isset($actPlayerInfo['mainArtURL']) && trim($actPlayerInfo['mainArtURL'])) {
                    if (strtolower(substr($actPlayerInfo['mainArtURL'], 0, 4) == 'http')) {
                        $commandPut .= 'artwork_url'.urlencode($actPlayerInfo['mainArtURL']).'&';
                    } else {
                        if ($serverIpAddress) {
                            $commandPut .= 'artwork_url='.urlencode('http://'.$serverIpAddress.'/'.$actPlayerInfo['mainArtURL']).'&';
                        } else if ($serverHostname) {
                            $commandPut .= 'artwork_url='.urlencode('http://'.$serverHostname.'.local/'.$actPlayerInfo['mainArtURL']).'&';
                        }
                    }
                } else {
                    if ($serverIpAddress) {
                        $commandPut .= 'artwork_url='.urlencode('http://'.$serverIpAddress.'/tmp/art/black.png').'&';
                    } else if ($serverHostname) {
                        $commandPut .= 'artwork_url='.urlencode('http://'.$serverHostname.'.local/tmp/art/black.png').'&';
                    }
                }
                // run the command
                $commandPut = rtrim($commandPut, '&').'"';
                sysCmd($commandPut);
            }
        }
        unset($actPlayerInfo, $server, $serverPlayer, $serverHostname, $serverIpAddress, $commandPut);
        //
        // this section post the current player information (redis cur_player_info) to each of the runeAudio owntone clients
        //  this is not really what we want to do as Airplay clients will receive no metadata
        // create a list of the runeaudio hostnames and ip addresses
        //
        // first create a list of owntone runeaudio clients
        $retval = sysCmd("avahi-browse -atrlkp 2>/dev/null | grep -i 'skin_name=RuneUI'");
        if (is_array($retval)) {
            $clientIp = array();
            foreach ($retval as $avahi_line) {
                // the avahi line contains a semicolon (;) delimited list
                $avahiElement = explode(';', strtolower($avahi_line));
                // the interesting elemens are:
                //  1 - the nic (e.g. eth0)
                //  2 - ip type (e.g. ipv4)
                //  6 - clientname (e.g. runeaudio.local)
                //  7 - IP address (e.g. 192.168.2.10)
                //
                // remove the '.local' from the client name
                $clientname = explode('.', $avahiElement[6], 2)[0];
                if (!isset($clientIp[$clientname])) {
                    $clientIp[$clientname] = json_encode(array('clientname' => $clientname, 'ip_address' => $avahiElement[7], 'ip_type' => $avahiElement[2], 'nic' => $avahiElement[1]));
                    continue;
                }
                // an eth? nic is preferable to a wlan? nic
                // an ipv4 connection is preferable to ipv6
                // retrieve the existing client IP details
                $storedclientIpDetails = json_decode($clientIp[$clientname], true);
                if ((substr($avahiElement[1], 0, 4) == 'eth') && (substr($storedclientIpDetails['nic'], 0, 3) != 'eth')) {
                    $clientIp[$clientname] = json_encode(array('clientname' => $clientname, 'ip_address' => $avahiElement[7], 'ip_type' => $avahiElement[2], 'nic' => $avahiElement[1]));
                    continue;
                }
                if ((substr($avahiElement[2], 0, 4) == 'ipv4') && (substr($storedclientIpDetails['ip_type'], 0, 4) != 'ipv4')) {
                    $clientIp[$clientname] = json_encode(array('clientname' => $clientname, 'ip_address' => $avahiElement[7], 'ip_type' => $avahiElement[2], 'nic' => $avahiElement[1]));
                    continue;
                }
            }
            // $clientIp now contains a list of runeaudio nodes on the network, excluding this node, it also contains the IP address of each node
            unset($retval, $avahi_line, $avahiElement, $clientname);
            if (count($clientIp)) {
                // there are other runeaudio nodes
                $outputs = $redis->hGetall('owntone_outputs');
                foreach ($outputs as $clientname => $output) {
                    // the array has a key in mixed case, convert it to lower case
                    $outputs[strtolower($clientname)] = $output;
                    unset($outputs[$clientname]);
                }
                foreach ($clientIp as $clientname => $value) {
                    // work through the runeaudio node list
                    if (!isset($outputs[$clientname])) {
                        // its not an owntone client, delete it from the list
                        unset($clientIp[$clientname]);
                        continue;
                    }
                    // its an owntone client, determine if it is connected
                    $outputDetail = json_decode($outputs[$clientname], true);
                    if (!$outputDetail['selected']) {
                        // the output is not connected, delete it from the list
                        unset($clientIp[$clientname]);
                    }
                }
                unset($outputs, $clientname, $output, $value, $outputDetail);
            }
        }
        while ($redis->lLen('owntone_render') > 5) {
            // more than 5 render events in the queue, remove the oldest ones
            $redis->rPop('owntone_render');
        }
        // $clientIp now contains a list of currently connected runeaudio owntone clients, it may also contain the IP address of each client
        $serverHostname = strtolower($redis->hGet('owntone', 'server_hostname'));
        $serverIpAddress = strtolower($redis->hGet('owntone', 'server_ip_address'));
        if ($redis->lLen('owntone_render') && ($serverHostname || $serverIpAddress) && count($clientIp)) {
            // something to process and the server ip address and/or hostmane has been determined and there is a client to service
            while ($redis->lLen('owntone_render')) {
                // read the fifo queue, the queue contains all of the records of act_player_info which have been sent to the UI
                $encoded = $redis->rPop('owntone_render');
                // check the length of the output (0 = end), this should never happen
                if (!strlen($encoded)) {
                    continue;
                }
                if (isset($lastOwntoneRender)) {
                    $decoded = array_merge(json_decode($lastOwntoneRender, true), json_decode($encoded, true));
                } else {
                    $decoded = json_decode($encoded, true);
                }
                // some modifications are required to the render array
                //  volume must be deleted, this is set by the client
                //  local_volume_control must be deleted, this is set by the client
                //  owntone state stop is always pause
                //  owntone actPlayer is always Airplay
                //  owntone always operates at 44100:16:2
                //  owntone bitrate is always 1,411
                //  owntone audio channels is always Stereo
                //  owntone consume is always 0
                //  owntone radio is always false
                //  the urls of images must be prefixed with the ip address
                //      bigArtURL
                //      coverArtPreload
                //      mainArtURL
                //      smallArtURL
                if (isset($decoded['volume'])) {
                    unset($decoded['volume']);
                }
                if (isset($decoded['local_volume_control'])) {
                    unset($decoded['local_volume_control']);
                }
                if (isset($decoded['state']) && ($decoded['state'] == 'stop')) {
                    $decoded['state'] = 'pause';
                }
                $decoded['actPlayer'] = 'Airplay';
                $decoded['audio'] = '44100:16:2';
                $decoded['audio_sample_depth'] = '24';
                $decoded['audio_sample_rate'] = 44.1;
                $decoded['bitrate'] = 1411;
                $decoded['audio_channels'] = "Stereo";
                $decoded['consume'] = '0';
                $decoded['radio'] = false;
                $imageUrls = array('bigArtURL', 'coverArtPreload', 'mainArtURL', 'smallArtURL');
                foreach ($imageUrls as $imageUrl) {
                    if (!isset($decoded[$imageUrl])) {
                        continue;
                    }
                    $decoded[$imageUrl] = trim(strtolower($decoded[$imageUrl]));
                    if ($decoded[$imageUrl]) {
                        if (substr($decoded[$imageUrl], 0, 4) != 'http') {
                            if ($serverIpAddress) {
                                $decoded[$imageUrl] = 'http://'.$serverIpAddress.'/'.$decoded[$imageUrl];
                            } else if ($serverIpAddress) {
                                $decoded[$imageUrl] = 'http://'.serverHostname.'.local/'.$decoded[$imageUrl];
                            } else {
                                unset($decoded[$imageUrl]);
                            }
                        }
                    }
                }
                $encoded = json_encode($decoded);
                echo 'Encoded: '.$encoded."\n";
                $lastOwntoneRender = $encoded;
                // now send the render information to each of the runeadio owntone clients
                foreach ($clientIp as $client) {
                    $client = json_decode($client, true);
                    if (isset($client['ip_address']) && $client['ip_address']) {
                        curlPost('http://'.$client['ip_address'].'/pub?id=playback', $encoded);
                    } else {
                        curlPost('http://'.$client['clientname'].'.local/pub?id=playback', $encoded);
                    }
                    // keep a list of clients which have had at least one render action
                    $renderedClients[$client['clientname']] = true;
                    // sleep for 0.05 seconds
                    usleep(50000);
                }
            }
            // we re-render the last information every 20 seconds, this means a reactivated inactive browser will be refreshed after 10 seconds on average
            $nextRenderTime = microtime(true) + 20;
            unset ($encoded, $decoded, $imageUrls, $serverIpAddress, $serverHostname);
        } else {
            // there is nothing to process, re-render after timeout or when a new client has attached
            if (isset($lastOwntoneRender) && count($clientIp)) {
                // we have rendered some information to a client and there are currently runeaudio owntone clients attached
                $newClient = false;
                foreach ($clientIp as $client => $value) {
                    if (!in_array($client, $renderedClients)) {
                        // new client has attached
                        $newClient = true;
                        break;
                    }
                }
                $now = microtime(true);
                if ($newClient || (isset($nextRenderTime) && ($nextRenderTime <= $now))) {
                    // there is a new client or re-render time has expired
                    foreach ($renderedClients as $renderedClientKey => $value) {
                        if (!isset($clientIp[$renderedClientKey])) {
                            // this client was connected, but not any more, remove it from the rendered client list
                            unset($renderedClients[$renderedClientKey]);
                        }
                    }
                    // prepare the render message, the elapsed values must be recalculated or the elapsed and song percentage must be cleared
                    $decoded = json_decode($lastOwntoneRender, true);
                    if (($decoded['state'] == 'play') && isset($decoded['last_elapsed']) && isset($decoded['time_last_elapsed']) && isset($decoded['time'])
                            && is_numeric($decoded['last_elapsed']) && is_numeric($decoded['time_last_elapsed']) && is_numeric($decoded['time'])
                            && $decoded['time_last_elapsed'] && $decoded['time']) {
                        // state is play and we have the information to recalculate the elapsed time
                        $decoded['elapsed'] = max(0, round($decoded['last_elapsed'] + ($now - $decoded['time_last_elapsed'])));
                        $decoded['song_percent'] = max(0, min(100, round(100 * $decoded['elapsed'] / $decoded['time'])));
                        $decoded['last_elapsed'] = $decoded['elapsed'];
                        $decoded['time_last_elapsed'] = $now;
                        $encoded = json_encode($decoded);
                        $lastOwntoneRender = $encoded;
                    } else {
                        // state is pause or we don’t have the details to calculate the elapsed time, ensure that the elapsed information is removed
                        unset($decoded['elapsed'], $decoded['song_percent']);
                        $encoded = json_encode($decoded);
                    }
                    foreach ($clientIp as $client) {
                        // render the last rendered information with updated elapsed information to each client
                        $client = json_decode($client, true);
                        if (isset($client['ip_address']) && $client['ip_address']) {
                            curlPost('http://'.$client['ip_address'].'/pub?id=playback', $encoded);
                        } else {
                            curlPost('http://'.$client['clientname'].'.local/pub?id=playback', $encoded);
                        }
                        // keep a list of clients which have had at least one render action
                        $renderedClients[$client['clientname']] = true;
                        // sleep for 0.1 seconds
                        usleep(100000);
                    }
                    // reset the re-render time
                    $nextRenderTime = microtime(true) + 20;
                }

            }
            unset($newClient, $now, $client, $value, $renderedClientKey, $decoded, $encoded);
        }
        unset($clientIp);
        //
        // // this section posts the current song metadata to the owntone metadata fifo
        // //   this should work but does not
        // //
        // $action = array();
        // $actPlayerInfo = json_decode($redis->get('act_player_info'), true);
        // if (isset($actPlayerInfo['state'])) {
            // // if ($redis->hGet('owntone', 'newconnection')) {
                    // // $action['start'] = true;
                    // // $action['change_song'] = true;
                    // // $action['change_picture'] = true;
                    // // $action['play'] = true;
                    // // $redis->hSet('owntone', 'newconnection', 0);
            // // } else
            // if (!isset($actPlayerInfoSave['currentalbumartist']) ||
                    // !isset($actPlayerInfoSave['currentartist']) ||
                    // !isset($actPlayerInfoSave['currentalbum']) ||
                    // !isset($actPlayerInfoSave['currentsong']) ||
                    // !isset($actPlayerInfoSave['currentcomposer']) ||
                    // !isset($actPlayerInfoSave['duration']) ||
                    // !isset($actPlayerInfoSave['genre']) ||
                    // !isset($actPlayerInfoSave['file']) ||
                    // !isset($actPlayerInfoSave['state']) ||
                    // !isset($actPlayerInfoSave['mainArtURL']) ||
                    // !isset($actPlayerInfoSave['time_last_elapsed'])) {
                // if ($actPlayerInfo['state'] == 'play') {
                    // $action['start'] = true;
                    // $action['change_song'] = true;
                    // $action['change_picture'] = true;
                    // $action['play'] = true;
                    // $playStarted = true;
                // }
            // } else if ($playStarted) {
                // if (isset($actPlayerInfoSave['mainArtURL']) && isset($actPlayerInfo['mainArtURL']) &&
                        // ($actPlayerInfoSave['mainArtURL'] != $actPlayerInfo['mainArtURL'])) {
                    // $action['change_picture'] = true;
                // }
                // if ((isset($actPlayerInfoSave['currentalbumartist']) && isset($actPlayerInfo['currentalbumartist']) &&
                        // ($actPlayerInfoSave['currentalbumartist'] != $actPlayerInfo['currentalbumartist'])) ||
                        // (isset($actPlayerInfoSave['currentartist']) && isset($actPlayerInfo['currentartist']) &&
                        // ($actPlayerInfoSave['currentartist'] != $actPlayerInfo['currentartist'])) ||
                        // (isset($actPlayerInfoSave['currentalbum']) && isset($actPlayerInfo['currentalbum']) &&
                        // ($actPlayerInfoSave['currentalbum'] != $actPlayerInfo['currentalbum'])) ||
                        // (isset($actPlayerInfoSave['currentsong']) && isset($actPlayerInfo['currentsong']) &&
                        // ($actPlayerInfoSave['currentsong'] != $actPlayerInfo['currentsong'])) ||
                        // (isset($actPlayerInfoSave['file']) && isset($actPlayerInfo['file']) &&
                        // ($actPlayerInfoSave['file'] != $actPlayerInfo['file']))) {
                    // $action['change_song'] = true;
                // }
                // if (isset($actPlayerInfoSave['state']) && isset($actPlayerInfo['state']) &&
                        // ($actPlayerInfoSave['state'] != $actPlayerInfo['state'])) {
                    // // state change
                    // if (($actPlayerInfoSave['state'] == 'play') && ($actPlayerInfo['state'] != 'play')) {
                        // $action['pause'] = true;
                    // } else if (($actPlayerInfoSave['state'] != 'play') && ($actPlayerInfo['state'] == 'play')) {
                        // $action['play'] = true;
                    // }
                // }
                // if ((isset($actPlayerInfoSave['duration']) && isset($actPlayerInfo['duration']) &&
                        // (actPlayerInfoSave['duration'] != $actPlayerInfo['duration'])) ||
                        // (isset($actPlayerInfoSave['last_elapsed']) && isset($actPlayerInfo['last_elapsed']) &&
                        // (actPlayerInfoSave['last_elapsed'] != $actPlayerInfo['last_elapsed'])) ||
                        // (isset($actPlayerInfoSave['time_last_elapsed']) && isset($actPlayerInfo['time_last_elapsed']) &&
                        // (actPlayerInfoSave['time_last_elapsed'] != $actPlayerInfo['time_last_elapsed']))) {
                    // // song duration change
                    // $action['play'] = true;
                // }
            // }
            // if ($playStarted) {
                // if (isset($action['start']) && $action['start']) {
                    // // start of stream event
                    // // no information required to process this event
                    // //
                    // // play stream begin(ssnc pbeg), no payload
                    // $args = array('type' => 'ssnc', 'code' => 'pbeg');
                    // wrk_airplay_metadata_encoder($redis, 'no_payload', $args);
                    // //
                    // // unknown purpose (ssnc pres), no payload
                    // $args = array('type' => 'ssnc', 'code' => 'pres');
                    // wrk_airplay_metadata_encoder($redis, 'no_payload', $args);
                    // //
                    // // unknown purpose (ssnc pffr), no payload
                    // $args = array('type' => 'ssnc', 'code' => 'pffr');
                    // wrk_airplay_metadata_encoder($redis, 'no_payload', $args);
                    // //
                    // // name of user sending device (ssnc snam)
                    // $args = array('type' => 'ssnc', 'code' => 'pffr', 'payload' => 'Owntone on '.$serverHostname);
                    // wrk_airplay_metadata_encoder($redis, 'payload', $args);
                    // //
                    // foreach ($actPlayerInfo as $key => $value) {
                        // $actPlayerInfoSave[$key] = '';
                    // }
                // }
                // if (isset($action['change_picture']) && $action['change_picture']) {
                    // // change picture event
                    // if (isset($actPlayerInfo['mainArtURL']) && $actPlayerInfo['mainArtURL']) {
                        // // the information is available to process this event
                        // //
                        // // start picture send (ssnc pcst), payload is current RTP timestamp
                        // $timeRTP = substr(round((microtime(true) * 44100) + $timeRTPOffset), -9);
                        // $args = array('type' => 'ssnc', 'code' => 'pcst', 'payload' => $timeRTP);
                        // wrk_airplay_metadata_encoder($redis, 'payload', $args);
                        // //
                        // // artwork send (ssnc PICT)
                        // $args = array('type' => 'ssnc', 'code' => 'PICT');
                        // if (strtolower(substr($actPlayerInfo['mainArtURL'], 0, 4)) == 'http') {
                            // $args['url'] = $actPlayerInfo['mainArtURL'];
                        // } else {
                            // $args['url'] = 'http://'.$serverHostname.'.local/'.$actPlayerInfo['mainArtURL'];
                            // $args['filname'] = '/srv/http/'.$actPlayerInfo['mainArtURL'];
                        // }
                        // wrk_airplay_metadata_encoder($redis, 'picture', $args);
                        // //
                        // // end picture send (ssnc pcen), it has the same RTP timestamp as the start picture send (ssnc pcst)
                        // $args = array('type' => 'ssnc', 'code' => 'pcen', 'payload' => $timeRTP);
                        // wrk_airplay_metadata_encoder($redis, 'payload', $args);
                        // //
                        // $actPlayerInfoSave['mainArtURL'] = $actPlayerInfo['mainArtURL'];
                    // }
                // }
                // if (isset($action['play']) && $action['play']) {
                    // // start of play of song event
                    // if (isset($actPlayerInfo['time_last_elapsed']) && trim($actPlayerInfo['time_last_elapsed']) &&
                            // isset($actPlayerInfo['last_elapsed']) && strlen($actPlayerInfo['last_elapsed']) &&
                            // isset($actPlayerInfo['duration']) && strlen($actPlayerInfo['duration'])) {
                        // // the information is available to process this event
                        // //
                        // // progress (ssnc prgr)
                        // // This is metadata from AirPlay consisting of RTP timestamps for the start of the current play sequence,
                        // // the current play point and the end of the play sequence.
                        // $now = microtime(true); // time now in seconds from epoch
                        // $timeLastElapsed = $actPlayerInfo['time_last_elapsed']; // the time last elapsed was set in seconds from epoch
                        // $lastElapsed = $actPlayerInfo['last_elapsed']; // last elapsed time in seconds
                        // $duration = $actPlayerInfo['duration']; // song duration in seconds
                        // // now calculate the start and end time in milliseconds when the current time is current play point
                        // $startTimeRTP = substr(round((($timeLastElapsed - $lastElapsed) * 44100) + $timeRTPOffset), -9);
                        // $endTimeRTP = substr(round((($duration + $timeLastElapsed - $lastElapsed) * 44100) + $timeRTPOffset), -9);
                        // $timeRTP = substr(round(($now * 44100) + $timeRTPOffset), -9);
                        // $args = array('type' => 'ssnc', 'code' => 'prgr', 'payload' => $startTimeRTP.'/'.$timeRTP.'/'.$endTimeRTP);
                        // wrk_airplay_metadata_encoder($redis, 'payload', $args);
                        // //
                        // // play stream resume (ssnc prsm)
                        // $args = array('type' => 'ssnc', 'code' => 'prsm');
                        // wrk_airplay_metadata_encoder($redis, 'no_payload', $args);
                        // //
                        // // StreamType (ssnc styp)
                        // $args = array('type' => 'ssnc', 'code' => 'styp', 'payload' => 'Buffered');
                        // wrk_airplay_metadata_encoder($redis, 'payload', $args);
                        // //
                        // $actPlayerInfoSave['time_last_elapsed'] = $actPlayerInfo['time_last_elapsed'];
                        // $actPlayerInfoSave['last_elapsed'] = $actPlayerInfo['last_elapsed'];
                        // $actPlayerInfoSave['duration'] = $actPlayerInfo['duration'];
                        // if (isset($actPlayerInfo['state']) && $actPlayerInfo['state']) {
                            // $actPlayerInfoSave['state'] = $actPlayerInfo['state'];
                        // } else {
                            // $actPlayerInfoSave['state'] = '';
                        // }
                    // }
                // }
                // if (isset($action['change_song']) && $action['change_song']) {
                    // // information required to process this event is determined per item
                    // //
                    // // metadata start (ssnc mdst), argument is the current RTP timestamp
                    // $timeRTP = substr(round((microtime(true) * 44100) + $timeRTPOffset), -9);
                    // $args = array('type' => 'ssnc', 'code' => 'mdst', 'payload' => $timeRTP);
                    // wrk_airplay_metadata_encoder($redis, 'payload', $args);
                    // //
                    // // persistent ID (core mper), argument value is unknown, length is 8 bytes
                    // //  we implement this by using the hash('crc32b', <string>) function, which returns an 8 byte string
                    // //  where the string is:
                    // //      file + currentalbumartist + currentartist + currentalbum + currentsong
                    // $string = '';
                    // if (isset($actPlayerInfo['file'])) {
                        // $string .= $actPlayerInfo['file'];
                    // }
                    // if (isset($actPlayerInfo['currentalbumartist'])) {
                        // $string .= $actPlayerInfo['currentalbumartist'];
                    // }
                    // if (isset($actPlayerInfo['currentartist'])) {
                        // $string .= $actPlayerInfo['currentartist'];
                    // }
                    // if (isset($actPlayerInfo['currentalbum'])) {
                        // $string .= $actPlayerInfo['currentalbum'];
                    // }
                    // if (isset($actPlayerInfo['currentsong'])) {
                        // $string .= $actPlayerInfo['currentsong'];
                    // }
                    // $args = array('type' => 'core', 'code' => 'mper', 'payload' => hash('crc32b', $string));
                    // wrk_airplay_metadata_encoder($redis, 'payload', $args);
                    // //
                    // // album (core asal)
                    // if (isset($actPlayerInfo['currentalbum']) && trim($actPlayerInfo['currentalbum'])) {
                        // $args = array('type' => 'core', 'code' => 'asal', 'payload' => $actPlayerInfo['currentalbum']);
                        // $actPlayerInfoSave['currentalbum'] = $actPlayerInfo['currentalbum'];
                    // } else {
                        // $args = array('type' => 'core', 'code' => 'asal', 'payload' => '');
                        // $actPlayerInfoSave['currentalbum'] = '';
                    // }
                    // wrk_airplay_metadata_encoder($redis, 'payload', $args);
                    // //
                    // // artist (core asar)
                    // if (isset($actPlayerInfo['currentartist']) && trim($actPlayerInfo['currentartist'])) {
                        // $args = array('type' => 'core', 'code' => 'asar', 'payload' => $actPlayerInfo['currentartist']);
                        // $actPlayerInfoSave['currentartist'] = $actPlayerInfo['currentartist'];
                    // } else {
                        // $args = array('type' => 'core', 'code' => 'asar', 'payload' => '');
                        // $actPlayerInfoSave['currentartist'] = '';
                    // }
                    // wrk_airplay_metadata_encoder($redis, 'payload', $args);
                    // //
                    // // composer (core ascp)
                    // if (isset($actPlayerInfo['currentcomposer']) && trim($actPlayerInfo['currentcomposer'])) {
                        // $args = array('type' => 'core', 'code' => 'ascp', 'payload' => $actPlayerInfo['currentcomposer']);
                        // $actPlayerInfoSave['currentcomposer'] = $actPlayerInfo['currentcomposer'];
                    // } else {
                        // $args = array('type' => 'core', 'code' => 'ascp', 'payload' => '');
                        // $actPlayerInfoSave['currentcomposer'] = '';
                    // }
                    // wrk_airplay_metadata_encoder($redis, 'payload', $args);
                    // //
                    // // genre (core asgn)
                    // if (isset($actPlayerInfo['genre']) && trim($actPlayerInfo['genre'])) {
                        // $args = array('type' => 'core', 'code' => 'asgn', 'payload' => $actPlayerInfo['genre']);
                        // $actPlayerInfoSave['genre'] = $actPlayerInfo['genre'];
                    // } else {
                        // $args = array('type' => 'core', 'code' => 'asgn', 'payload' => '');
                        // $actPlayerInfoSave['genre'] = '';
                    // }
                    // wrk_airplay_metadata_encoder($redis, 'payload', $args);
                    // //
                    // // song (core minm)
                    // if (isset($actPlayerInfo['currentsong']) && trim($actPlayerInfo['currentsong'])) {
                        // $args = array('type' => 'core', 'code' => 'asgn', 'payload' => $actPlayerInfo['currentsong']);
                        // $actPlayerInfoSave['currentsong'] = $actPlayerInfo['currentsong'];
                    // } else {
                        // $args = array('type' => 'core', 'code' => 'asgn', 'payload' => '');
                        // $actPlayerInfoSave['currentsong'] = '';
                    // }
                    // wrk_airplay_metadata_encoder($redis, 'payload', $args);
                    // //
                    // // song track number (core astn), 2 byte integer (unsigned short - always 16 bit, big endian byte order)
                    // //  we dont use this in runeaudio, just encode zero
                    // $args = array('type' => 'core', 'code' => 'astn', 'payload' => pack("n", 0));
                    // wrk_airplay_metadata_encoder($redis, 'payload', $args);
                    // //
                    // // song track count (core astc), 2 byte integer (unsigned short - always 16 bit, big endian byte order)
                    // //  we dont use this in runeaudio, just encode zero
                    // $args = array('type' => 'core', 'code' => 'astc', 'payload' => pack("n", 0));
                    // wrk_airplay_metadata_encoder($redis, 'payload', $args);
                    // //
                    // // song data kind (core asdk), single byte integer, unsigned
                    // // derived from the asdk metadata token, to the metadata bundle presented in the D-Bus interface. If 0 it seems to
                    // //  indicate an item of a specific duration such as an audio track; if 1 it seems to mean the stream is of unknown
                    // //  duration, for example an internet radio stream.
                    // // radio, alsa input and bluetooth fall into the unknown duration type (1), in all other cases the length of the song is known (0)
                    // if (isset($actPlayerInfo['actPlayer']) &&
                            // (((isset($actPlayerInfo['radio']) && $actPlayerInfo['actPlayer'] == 'MPD') && $actPlayerInfo['radio']) ||
                            // ((isset($actPlayerInfo['file']) && $actPlayerInfo['actPlayer'] == 'MPD') && (strtolower(substr($actPlayerInfo['file'], 0, 5)) == 'alsa:')) ||
                            // ($actPlayerInfo['actPlayer'] == 'Bluetooth'))) {
                        // $args = array('type' => 'core', 'code' => 'asdk', 'payload' => pack("C", 1));
                    // } else {
                        // $args = array('type' => 'core', 'code' => 'asdk', 'payload' => pack("C", 0));
                    // }
                    // wrk_airplay_metadata_encoder($redis, 'payload', $args);
                    // //
                    // // ?caps? (core caps), unknown purpose, singe byte integer (unsigned) - seems always to have a value of 1 or 2 (mostly 2)
                    // //  use a value of 2
                    // $args = array('type' => 'core', 'code' => 'caps', 'payload' => pack("C", 2));
                    // wrk_airplay_metadata_encoder($redis, 'payload', $args);
                    // //
                    // // song time in milliseconds (core astm), 4 byte integer (unsigned long (always 32 bit, big endian byte order))- also see 'ssnc prgr' progress
                    // if (isset($actPlayerInfo['duration'])) {
                        // $args = array('type' => 'core', 'code' => 'astm', 'payload' => pack("N", ($actPlayerInfo['duration'] * 1000)));
                        // $actPlayerInfoSave['duration'] = $actPlayerInfo['duration'];
                    // } else {
                        // $args = array('type' => 'core', 'code' => 'astm', 'payload' => pack("N", 0));
                        // $actPlayerInfoSave['duration'] = '';
                    // }
                    // wrk_airplay_metadata_encoder($redis, 'payload', $args);
                    // //
                    // // metadata end (ssnc mden), payload is same RTP timestamp as metadata start (ssnc mdst)
                    // $args = array('type' => 'ssnc', 'code' => 'mdst', 'payload' => $timeRTP);
                    // wrk_airplay_metadata_encoder($redis, 'payload', $args);
                    // //
                    // if (trim($actPlayerInfo['file'])) {
                        // $actPlayerInfoSave['file'] = $actPlayerInfo['file'];
                    // } else {
                        // $actPlayerInfoSave['file'] = '';
                    // }
                    // if (trim($actPlayerInfo['currentalbumartist'])) {
                        // $actPlayerInfoSave['currentalbumartist'] = $actPlayerInfo['currentalbumartist'];
                    // } else {
                        // $actPlayerInfoSave['currentalbumartist'] = '';
                    // }
                    // if (trim($actPlayerInfo['actPlayer'])) {
                        // $actPlayerInfoSave['actPlayer'] = $actPlayerInfo['actPlayer'];
                    // } else {
                        // $actPlayerInfoSave['actPlayer'] = '';
                    // }
                    // if (trim($actPlayerInfo['radio'])) {
                        // $actPlayerInfoSave['radio'] = $actPlayerInfo['radio'];
                    // } else {
                        // $actPlayerInfoSave['radio'] = '';
                    // }
                // }
                // if (isset($action['pause']) && $action['pause']) {
                    // // the pause action needs no further information
                    // //
                    // // flush stream request = pause (ssnc flsr), payload is current RTP timestamp
                    // $timeRTP = substr(round((microtime(true) * 44100) + $timeRTPOffset), -9);
                    // $args = array('type' => 'ssnc', 'code' => 'flsr', 'payload' => $timeRTP);
                    // wrk_airplay_metadata_encoder($redis, 'payload', $args);
                    // //
                    // // play stream flush (ssnc pfls), payload is current RTP timestamp
                    // $timeRTP = substr(round((microtime(true) * 44100) + $timeRTPOffset), -9);
                    // $args = array('type' => 'ssnc', 'code' => 'pfls', 'payload' => $timeRTP);
                    // wrk_airplay_metadata_encoder($redis, 'payload', $args);
                    // //
                    // // $actPlayerInfoSave = $actPlayerInfo;
                // }
            // }
        // }
    }
}
//
runelog('WORKER owntone_monitor.php END...');
