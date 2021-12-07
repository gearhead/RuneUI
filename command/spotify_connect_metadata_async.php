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
// common include
ini_set('display_errors', '1');
ini_set('error_reporting', -1);
ini_set('error_log', '/var/log/runeaudio/spotify_connect_metadata_async.log');
require_once('/var/www/app/libs/runeaudio.php');
// Connect to Redis backend include
require_once('/var/www/app/libs/openredis.php');
error_reporting(E_ALL & ~E_NOTICE);

// reset logfile
sysCmd('echo "--------------- start: spotify_connect_metadata_async.php ---------------" > /var/log/runeaudio/spotify_connect_metadata_async.log');
runelog('spotify_connect_metadata_async START');

// get the album art directory and url dir
$artDir = rtrim(trim($redis->get('albumart_image_dir')), '/');
$artUrl = trim($redis->get('albumart_image_url_dir'), " \n\r\t\v\0/");
if ($redis->get('remoteSSbigart') === 'album') {
    $bigartIsAlbum = true;
} else {
    $bigartIsAlbum = false;
}

// read the parameters - this is the PLAYER_EVENT and TRACK_ID
// $event = trim($argv[1]); // PLAYER_EVENT: stop, start or change (connect= stop)
// $track_id = trim($argv[2]); // TRACK_ID
// don't use the parameters, this job runs with a delay, there may have been more events since this job was initiated
// use the latest information from redis, later jobs could run but may have nothing to do
$event = $redis->hGet('spotifyconnect', 'event');
$track_id = $redis->hGet('spotifyconnect', 'track_id');
$event_time_stamp = $redis->hGet('spotifyconnect', 'event_time_stamp');
runelog('spotify_connect_metadata_async PLAYER_EVENT    :', $event);
runelog('spotify_connect_metadata_async TRACK_ID        :', $track_id);
runelog('spotify_connect_metadata_async EVENT TIME STAMP:', $event_time_stamp);
if ($event == '') {
    runelog('spotify_connect_metadata_async PLAYER_EVENT:', 'Empty - Terminating');
    return 0;
}
if ($track_id == '') {
    runelog('spotify_connect_metadata_async TRACK_ID:', 'Empty - Terminating');
    return 0;
}
$active_player = $redis->get('activePlayer');
if ($active_player != 'SpotifyConnect') {
    runelog('spotify_connect_metadata_async active player changed:', $active_player);
    return 0;
}

$last_track_id = $redis->hGet('spotifyconnect', 'last_track_id');
if ($event!= 'stop') {
    // set this early so that any later jobs will do nothing when events are skipped
    $redis->hSet('spotifyconnect', 'last_track_id', $track_id);
}

// sort out the metadata
$status = array();
if ($last_track_id == '') {
    // first time start
    // remove any cover art
    sysCmd('rm -f /srv/http/tmp/spotify-connect/spotify-connect-cover.*');
    // initialise the status array
    $status['audio'] = "44100:16:2";
    $status['audio_sample_rate'] = "44.1";
    $status['audio_sample_depth'] = "16";
    $status['bitrate'] = "1411";
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
    if ($event == 'stop') {
        // save JSON response for extensions
        $redis->set('act_player_info', json_encode($status));
        ui_render('playback', json_encode($status));
        sysCmd('curl -s -X GET http://localhost/command/?cmd=renderui');
        sysCmdAsync('/var/www/command/ui_update_async');
    }
} else {
    // not the frst time, we have already processed some data
    // get the last stored status
    $status = json_decode($redis->get('act_player_info'), true);
}
if ($track_id == $last_track_id) {
    // same song as last time
    // stop > start, start > stop or change > stop
    if ($event == 'stop') {
        // start > stop or change > stop
        // assume pause, timeout counter starts when stop is set, actual stop occurs after timeout
        $status['state'] = "pause";
        // calculate elapsed time and song percentage at stop time, it will be used on a restart
        // compensate because this routine runs async and is therefore late, event_time_stamp contains the real stop event time
        $last_time_stamp = $redis->hGet('spotifyconnect', 'last_time_stamp');
        $status['elapsed'] += intval($event_time_stamp - $last_time_stamp);
        $redis->hSet('spotifyconnect', 'last_time_stamp', $event_time_stamp);
        if ($status['time'] != 0) {
            $status['song_percent'] = $status['elapsed'] / $status['time'] * 100;
        } else {
            $status['song_percent'] = 0;
        }
    } else {
        // stop > start
        // restarting a paused track
        $status['state'] = "play";
    }
} else if ($event != 'stop') {
    // null > start, stop > change or start > change
    // new song, assume started from the beginning of the track
    $status['state'] = "play";
    $status['time'] = "0";
    $status['elapsed'] = "0";
    $status['song_percent'] = "0";
    // delete any existing cover art
    // curl -s https://open.spotify.com/track/<TRACK_ID> | sed 's/<meta/\n<meta/g' | grep -i -E 'og:title|og:image|music:duration|music:album|music:musician'
    $command = 'curl -s -f --connect-timeout 5 -m 10 --retry 2 https://open.spotify.com/track/'.$track_id.' | sed '."'".'s/<meta/\n<meta/g'."'".' | grep -i -E '."'".'og:title|og:image|music:duration|music:album|music:musician'."'";
    runelog('spotify_connect_metadata_async track command:', $command);
    $retval = sysCmd($command);
    foreach ($retval as &$line) {
        // replace all combinations of single or multiple tab, space, <cr> or <lf> with a single space
        $line = preg_replace('/[\t\n\r\s]+/', ' ', $line);
        // then strip the html out of the response
        $line = str_replace('<meta property="', '', $line);
        $line = str_replace('" content', '', $line);
        $line = str_replace('">', '', $line);
        $line = str_replace('"', '', $line);
        $line = trim($line);
        // result is <identifier>=<value>
        $lineparts = explode('=', $line);
        if ($lineparts[0] === 'og:title') {
            $title = trim($lineparts[1]);
            runelog('spotify_connect_metadata_async track title:', $title);
        } elseif ($lineparts[0] === 'og:image') {
            $albumart_url = trim($lineparts[1]);
            runelog('spotify_connect_metadata_async track albumart_url:', $albumart_url);
        } elseif ($lineparts[0] === 'music:duration') {
            $duration_in_sec = trim($lineparts[1]);
            runelog('spotify_connect_metadata_async track duration_in_sec:', $duration_in_sec);
        } elseif ($lineparts[0] === 'music:album') {
            $album_url = trim($lineparts[1]);
            runelog('spotify_connect_metadata_async track album_url:', $album_url);
        } elseif ($lineparts[0] === 'music:musician') {
            $artist_url = trim($lineparts[1]);
            runelog('spotify_connect_metadata_async track artist_url:', $artist_url);
        }
        unset($lineparts);
    }
    unset($retval, $line);
    // get the album name
    if ($album_url == '') {
        runelog('spotify_connect_metadata_async ALBUM_URL:', 'Empty');
    } else {
        runelog('spotify_connect_metadata_async ALBUM_URL:', $album_url);
        // curl -s <ALBUM_URL> | sed 's/<meta/\n<meta/g' | grep -i 'og:title'
        $command = 'curl -s -f --connect-timeout 5 -m 10 --retry 2 '.$album_url.' | sed '."'".'s/<meta/\n<meta/g'."'".' | grep -i '."'".'og:title'."'";
        runelog('spotify_connect_metadata_async album command:', $command);
        $retval = sysCmd($command);
        foreach ($retval as &$line) {
            // replace all combinations of single or multiple tab, space, <cr> or <lf> with a single space
            $line = preg_replace('/[\t\n\r\s]+/', ' ', $line);
            // then strip the html out of the response
            $line = str_replace('<meta property="', '', $line);
            $line = str_replace('" content', '', $line);
            $line = str_replace('">', '', $line);
            $line = str_replace('"', '', $line);
            $line = trim($line);
            // result is <identifier>=<value>
            $lineparts = explode('=', $line);
            if ($lineparts[0] === 'og:title') {
                $album = trim($lineparts[1]);
                runelog('spotify_connect_metadata_async album title:', $album);
            }
            unset($lineparts);
        }
        unset($retval, $line);
    }
    // get the artist name
    runelog('spotify_connect_metadata_async ARTIST_URL:', $artist_url);
    if ($artist_url == '') {
        runelog('spotify_connect_metadata_async ARTIST_URL:', 'Empty');
    } else {
        // curl -s <ARTIST_URL> | sed 's/<meta/\n<meta/g' | grep -i 'og:title'
        $command = 'curl -s -f --connect-timeout 5 -m 10 --retry 2 '.$artist_url.' | sed '."'".'s/<meta/\n<meta/g'."'".' | grep -i '."'".'og:title'."'";
        runelog('spotify_connect_metadata_async artist command:', $command);
        $retval = sysCmd($command);
        foreach ($retval as &$line) {
            // replace all combinations of single or multiple tab, space, <cr> or <lf> with a single space
            $line = preg_replace('/[\t\n\r\s]+/', ' ', $line);
            // then strip the html out of the response
            $line = str_replace('<meta property="', '', $line);
            $line = str_replace('" content', '', $line);
            $line = str_replace('">', '', $line);
            $line = str_replace('"', '', $line);
            $line = trim($line);
            // result is <identifier>=<value>
            $lineparts = explode('=', $line);
            if ($lineparts[0] === 'og:title') {
                $artist = trim($lineparts[1]);
                runelog('spotify_connect_metadata_async artist title:', $artist);
            }
            unset($lineparts);
        }
        unset($retval, $line);
    }
    // get the album art file
    runelog('spotify_connect_metadata_async ALBUMART_URL:', $albumart_url);
    if ($albumart_url == '') {
        runelog('spotify_connect_metadata_async ALBUMART_URL:', 'Empty - Terminating');
    } else {
        $spotifyFileName = 'spotify-connect-cover';
        $fileName = $artDir.'/'.$spotifyFileName;
        // wget -nv -F -T 10 -t 2 -O /srv/http/tmp/spotify-connect/spotify-connect-cover https://i.scdn.co/image/<ALBUMART_URL>
        $command = 'wget -nv -F -T 10 -t 2 -O "'.$fileName.'" '.$albumart_url;
        runelog('spotify_connect_metadata_async album art:', $command);
        $retval = sysCmd($command);
        // clear the cache otherwise filesize() returns incorrect values
        clearstatcache(true, $fileName);
        if (filesize($fileName) <= 100) {
            runelog('spotify_connect_metadata_async ALBUMART FILE:', 'Empty');
        } else {
            // extract the first 32 characters from the album art file
            $fp = fopen($fileName, 'r');
            $data_32 = fread($fp, 32);
            fclose($fp);
            // determine the album art file type
            if (strpos(' '.$data_32, 'PNG')) {
                $imgtype = '.png';
            } else if (strpos(' '.$data_32, 'JFIF')) {
                $imgtype = '.jpg';
            } else {
                $imgtype = '.jpg';
            }
            rename($fileName, $fileName.$imgtype);
            runelog('spotify_connect_metadata_async image filename:', $fileName.$imgtype);
        }
        unset($retval);
    }
    $status['time'] = abs(round(floatval($duration_in_sec)));
    $status['currentartist'] = $artist;
    $status['currentalbumartist'] = $artist;
    $status['currentalbum'] = $album;
    $status['currentsong'] = $title;
    $status['mainArtURL'] = $artDir.'/'.$spotifyFileName.$imgtype;
    if ($albumBig) {
        $status['bigArtURL'] = $artDir.'/'.$spotifyFileName.$imgtype;
    } else {
        $status['smallArtURL'] = $artDir.'/'.$spotifyFileName.$imgtype;
    }
    // calculate elapsed time and song percentage
    // compensate because this routine runs async and is therefore late, event_time_stamp contains the real start time
    $time_stamp = time();
    $redis->hSet('spotifyconnect', 'last_time_stamp', $time_stamp);
    $status['elapsed'] = intval($time_stamp - $event_time_stamp);
    if ($status['time'] != 0) {
        $status['song_percent'] = $status['elapsed'] / $status['time'] * 100;
    } else {
        $status['song_percent'] = 0;
    }
}
// save JSON response for extensions
$redis->set('act_player_info', json_encode($status));
ui_render('playback', json_encode($status));
sysCmd('curl -s -X GET http://localhost/command/?cmd=renderui');
sysCmdAsync('/var/www/command/ui_update_async');
return 1;
