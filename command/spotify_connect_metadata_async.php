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
 * along with RuneAudio; see the file COPYING. If not, see
 * <http://www.gnu.org/licenses/gpl-3.0.txt>.
 *
 *  file: command/spotify_connect_metadata_async.php
 *  version: 0.5b
 *  coder: janui
 *  date: 20-02-2019
 *
 */
//
// this routing should read the play event information from spotifyd, but it is wrong and unusable
//  the player-events names are correctly posted, but only small parts of the other data is correct
//  luckily spotifyd also posts textual information to its log which is correct, from this we can
//  derive the spotify track-id, the track-duration and the track-name
//  with this information the Spotify metadata can be retrieved and posted in a reasonable way, however
//  the code in this module is a mess
//
// initialisation
// report errors: set display_errors to true (=1)
ini_set('display_errors', '1');
// report all PHP errors: set error_reporting to -1
ini_set('error_reporting', -1);
// set the name of the error log file
ini_set('error_log', '/var/log/runeaudio/spotify_connect_metadata_async.log');
// common include
require_once('/srv/http/app/libs/runeaudio.php');
// Connect to Redis backend
require_once('/srv/http/app/libs/openredis.php');
// reset logfile
sysCmd('echo "--------------- start: spotify_connect_metadata_async.php ---------------" > /var/log/runeaudio/spotify_connect_metadata_async.log');
// logging starting message
runelog('WORKER spotify_connect_metadata_async.php STARTING...');
// define APP global
define('APP', '/srv/http/app/');

// get the lock status
$lock = $redis->get('lock_spotify_connect_metadata');
runelog('lock status ', $lock);
if (($lock === '0') || ($lock === '60')  || ($lock >= 60)) {
    // set the lock
    $redis->set('lock_spotify_connect_metadata', '1');
    // process the spotify connect metadata
} else {
    runelog("LOCKED!", '');
    echo "LOCKED!";
    // just in case something goes wrong increment the lock value by 1
    // when it reaches 60 (= 30 seconds, this should never happen) it will be processed as if there is no lock
    $lock += 1;
    $redis->set('lock_spotify_connect_metadata', $lock);
    runelog('lock status ', $redis->get('lock_spotify_connect_metadata'));
    // close Redis connection
    $redis->close();
    runelog('WORKER spotify_connect_metadata_async.php END...');
    exit(1);
}
// get the album art directory and url dir
$artDir = rtrim(trim($redis->get('albumart_image_dir')), '/');
$artUrl = trim($redis->get('albumart_image_url_dir'), " \n\r\t\v\0/");
if ($redis->get('remoteSSbigart') === 'album') {
    $bigartIsAlbum = true;
} else {
    $bigartIsAlbum = false;
}
// get the last stored status
$status = json_decode($redis->get('act_player_info'), true);
$lastEvent = '';
do {
    // make sure we have the last track ID
    $last_track_id = $redis->hGet('spotifyconnect', 'last_track_id');
    // pop the jobID from the work fifo queue
    $jobID = $redis->rPop('s_queue_fifo');
    // get the active player for the continue statements, it is tested on end loop
    $active_player = $redis->get('activePlayer');
    if (!isset($jobID) || !$jobID) {
        // queue is empty
        // in some cases an entry is added to the s_queue without adding an entry to the fifo queue
        //  this should never happen, even so add an entry to the fifo queue for
        //  any entries in the work queue
        foreach ($redis->hGetAll('s_queue') as $jobID => $job) {
            if (isset($jobID) && $jobID) {
                $redis->lPush('s_queue_fifo', $jobID);
            }
        }
        // run through the loop one more time, this should set the playing indicator and volume level, maybe a change of track
        $job['event'] = 'endofqueue';
        $job['track_id'] = $last_track_id;
        $job['duration_ms'] = '0';
        $job['position_ms'] = '0';
    }
    // read the job information from the work queue
    if (isset($jobID) && $jobID) {
        // not end of queue
        if ($redis->hExists('s_queue', $jobID)) {
            $job = json_decode($redis->hGet('s_queue', $jobID), true);
            $redis->hDel('s_queue', $jobID);
        } else {
            // no work queue entry for the fifo queue entry, this should never happen!
            //  something went wrong, just skip and get the next one
            continue;
        }
    }
    // PLAYER_EVENT: <one of the values: start, stop, play, pause, change, volumeset, load, preload, endoftrack, unavailable, preloading>
    // ignore the following events
    $skipEvents = array('load', 'preload', 'unavailable', 'preloading');
    if (in_array($job['event'],$skipEvents)) {
        continue;
    }
    // event: stop, start, change, load, play, pause, preload, endoftrack, volumeset, unavailable, preloading
    // track_id: the current Spotify track ID
    // duration_ms: track time in milliseconds
    // position_ms: track current position in milliseconds
    runelog('spotify_connect_metadata_async job PLAYER_EVENT    :', $job['event']);
    runelog('spotify_connect_metadata_async job TRACK_ID        :', $job['track_id']);
    runelog('spotify_connect_metadata_async job TIME            :', $job['duration_ms']);
    runelog('spotify_connect_metadata_async job ELAPSED         :', $job['position_ms']);
    // echo $job['event']." ".$job['track_id']." ".$job['duration_ms']." ".$job['position_ms']." Read\n";
    $title = '';
    unset($status['elapsed'], $status['song_percent']);
    // sleep for a half of a second between processing each event, do it here to allow the journal information
    //  to be completed
    usleep(500000);
    $song_line=sysCmd("journalctl -u spotifyd | tail -n 60 | grep -i '<spotify:track:' | tail -n 1");
    if (isset($song_line[0])) {
        $job['track_id'] = get_between_data($song_line[0], '<spotify:track:', '>', 1);
        $title = get_between_data($song_line[0], '<', '>', 1);
        // escape the backslash and double quote, not a single quote
        $title = str_replace('\\', '\\\\', $title);
        $title = str_replace('"', '\\"', $title);
        $song_line=sysCmd('journalctl -u spotifyd | tail -n 60 | grep -i "'.$title.'> (" | tail -n 1');
        if (isset($song_line[0])) {
            $duration_ms = get_between_data($song_line[0], '(', ' ms)', 1);
        } else {
            continue;
        }
    } else {
        continue;
    }
    runelog('spotify_connect_metadata_async fixed TRACK_ID      :', $job['track_id']);
    runelog('spotify_connect_metadata_async fixed TIME          :', $duration_ms);
    runelog('spotify_connect_metadata_async fixed TITLE         :', $title);
    // echo $job['event']." ".$job['track_id']." ".$duration_ms." ".$title." Fixed\n";
    // check for first time
    if (($last_track_id == '') && ($job['event'] == 'start')) {
        // first time start
        // initialise the status array
        initialise_playback_array($redis, 'Spotify connect');
        $status['audio'] = "44100:16:2";
        $status['audio_sample_rate'] = "44.1";
        $status['audio_sample_depth'] = "16";
        $status['bitrate'] = $redis->hGet('spotifyconnect', 'bitrate');
        $status['audio_channels'] = "Stereo";
        $status['random'] = "0";
        $status['single'] = "0";
        $status['consume'] = "0";
        $status['playlist'] = "1";
        $status['playlistlength'] = "1";
        $status['state'] = "stop";
        $status['time'] = "0";
        $status['elapsed'] = "0";
        $status['song_percent'] = "100";
        $lastvolume = $redis->hGet('spotifyconnect', 'lastvolume');
        $status['volume'] = $lastvolume;
        sysCmd('mpc volume '.$lastvolume);
        $status['currentalbumartist'] = "SpotifyConnect";
        $status['currentartist'] = "SpotifyConnect";
        $status['currentalbum'] = "-----";
        $status['currentsong'] = "Switching";
        $status['actPlayer'] = "SpotifyConnect";
        $status['radioname'] = null;
        $status['OK'] = null;
        $status['mainArtURL'] = $artUrl.'/spotify-connect.png';
        $status['smallArtURL'] = $artUrl.'/black.png';
        $status['bigArtURL'] = $artUrl.'/black.png';
        // save JSON response for extensions
        $redis->set('act_player_info', json_encode($status));
        ui_render('playback', json_encode($status));
        // echo $job['event']." ".$job['track_id']." Init\n";
        sysCmd('curl -s -X GET http://localhost/command/?cmd=renderui');
        sysCmdAsync('/var/www/command/ui_update_async', 0);
        $status['song_percent'] = 0;
        $status['elapsed'] = 0;
    }
    if ((($job['event'] == 'pause') || ($job['event'] == 'play')) && ($job['event'] != $lastEvent)) {
        if (!isset($status['time']) || !$status['time']) {
            $status['time'] = round($job['duration_ms']/1000);
        }
        $status['elapsed'] = round($job['position_ms']/1000);
        // calculate the percentage played
        if ($status['time'] != 0) {
            $status['song_percent'] = round(($status['elapsed'] / $status['time']) * 100);
        } else {
            $status['song_percent'] = 0;
        }
    }
    $lastEvent = $job['event'];
    if (($job['event'] == 'endoftrack') || ($job['event'] == 'change')) {
        $status['song_percent'] = 0;
        $status['elapsed'] = 0;
    }
    // calculate the volume, get the actual volume from alsa
    $card = preg_replace('/[^0-9]/', '',$redis->hGet('spotifyconnect', 'device'));
    $mixer = $redis->hGet('spotifyconnect', 'mixer');
    $retval = sysCmd('amixer -c '.$card.' get '.$mixer.' | grep "%"');
    if (isset($retval[0]) && $retval[0]) {
        $lastvolume = get_between_data($retval[0], '[', '%');
        if (is_numeric($lastvolume) && ($lastvolume >= 0) && ($lastvolume <= 100)) {
            if ($status['volume'] != $lastvolume) {
                $status['volume'] = $lastvolume;
                // save the last set volume
                $redis->hSet('spotifyconnect', 'lastvolume', $status['volume']);
                $active_player = $redis->get('activePlayer');
            }
        }
    } else {
        $status['volume'] = $redis->hGet('spotifyconnect', 'lastvolume');
    }
    //
    // $playing = sysCmd('cat /proc/asound/card*/pcm*p/sub*/hw_params | grep -civ closed')[0];
    // // $playing contains 0 or false when nothing is playing, otherwise a positive number like 7 (=true)
    // if ($playing) {
        // // something is playing
        // // restarting a paused track
        // $status['state'] = "play";
    // } else {
        // // start > stop or change > stop
        // // assume pause, timeout counter starts when stop is set, actual stop occurs after timeout
        // $status['state'] = "pause";
    // }
    // set playing on certain events
    $playingEvents = array('start', 'endoftrack', 'change');
    if (in_array($job['event'],$playingEvents)) {
        $status['state'] = "play";
    }
    // get the metadata when the track changes
    $trackChangeEvents = array('start', 'endoftrack', 'change', 'play', 'endofqueue');
    if ((in_array($job['event'],$trackChangeEvents) || ($last_track_id == ''))
            && ($last_track_id != $job['track_id'])
            && $job['track_id']) {
        // save the last track ID
        $redis->hSet('spotifyconnect', 'last_track_id', $job['track_id']);
        // get the Spotify metadata based on the track ID
        $retval = wrk_getSpotifyMetadata($redis, $job['track_id']);
        // var_dump($retval);
        $status['state'] = "pause";
        if (isset($retval['duration_in_sec']) && $retval['duration_in_sec']) {
            $status['time'] = abs(round(floatval($retval['duration_in_sec'])));
        } else {
            $status['time'] = round($duration_ms/1000);
        }
        // calculate the percentage played
        if (isset($status['elapsed']) && $status['elapsed']) {
            if ($status['time'] != 0) {
                $status['song_percent'] = round(($status['elapsed'] / $status['time']) * 100);
            } else {
                $status['song_percent'] = 0;
            }
        }
        $status['currentartist'] = $retval['artist'];
        $status['currentalbumartist'] = $retval['artist'];
        $status['currentalbum'] = $retval['album'];
        $status['currentsong'] = $retval['title'];
        if (($status['currentsong'] == '-') && $title != '') {
            $status['currentsong'] = $title;
        }
        $status['mainArtURL'] = $retval['albumart_url'];
        if ($bigartIsAlbum) {
            $status['bigArtURL'] = $retval['albumart_url'];
        } else {
            $status['smallArtURL'] = $retval['albumart_url'];
        }
        // now get the artist art and song information
        $info = array();
        $info['artist'] = $status['currentartist'];
        $info['albumartist'] = $status['currentalbumartist'];
        $info['song'] = $status['currentsong'];
        $retval = get_artistInfo($redis, $info);
        if ($retval) {
            $info = array_merge($info, $retval);
        }
        $retval = get_songInfo($redis, $info);
        if ($retval) {
            $info = array_merge($status, $retval);
        }
        $status['song_lyrics'] = $info['song_lyrics'];
        $status['artist_bio_summary'] = $info['artist_bio_summary'];
        $status['artist_similar'] = $info['artist_similar'];
        if ($artUrl == substr($info['artist_arturl'], 0, strlen($artUrl))) {
            // the artist art has not been found, so use the album art
            $info['artist_arturl'] = $status['mainArtURL'];
        }
        if ($bigartIsAlbum) {
            $status['smallArtURL'] = $info['artist_arturl'];
        } else {
            $status['bigArtURL'] = $info['artist_arturl'];
        }
    }
    $active_player = $redis->get('activePlayer');
    if ($active_player == 'SpotifyConnect') {
        // save JSON response for extensions
        $redis->set('act_player_info', json_encode($status));
        ui_render('playback', json_encode($status));
        sysCmd('curl -s -X GET http://localhost/command/?cmd=renderui');
        sysCmdAsync('/var/www/command/ui_update_async', 0);
        // echo $job['event']." ".$job['track_id']." Main\n";
    }
} while (($active_player == 'SpotifyConnect') && isset($jobID) && $jobID);
// clean up the metadata, async and at low priority
sysCmdAsync('nice --adjustment=10 /srv/http/command/clean_music_metadata_async.php');
// unlock
$redis->set('lock_spotify_connect_metadata', '0');
runelog('lock status ', $redis->get('lock_spotify_connect_metadata'));
// close Redis connection
$redis->close();

runelog('WORKER spotify_connect_metadata_async.php END...');
#---
#End script

