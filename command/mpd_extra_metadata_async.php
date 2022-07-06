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
 *  file: command/mpd_extra_metadata_async.php
 *  version: 0.5
 *  coder: janui
 *  date: March 2022
 *  description:
 *      this file replaces app/coverart_ctl.php, app/artist_info_ctl.php and app/lyric_ctl.php these
 *      files were previously called by the javascript UI engine (runeaudio.js) to retrieve album art
 *      artist information and lyrics at the moment it was required for display
 *      while operating the UI was blocked which caused poor UI response and the routines were also
 *      called by each active client causing stress on the external data-service providers
 *      in the new situation the information retrieved once and is pushed to the UI from the back-end
 *      some caching is applied to improve performance to reduce the load on the external data-service
 *      providers
 *      information for the next track (if known) is pre-retrieved while playing the previous track, this
 *      also helps the UI response
 */
// initialisation
// report errors: set display_errors to true (=1)
ini_set('display_errors', '1');
// report all PHP errors: set error_reporting to -1
ini_set('error_reporting', -1);
// set the name of the error log file
ini_set('error_log', '/var/log/runeaudio/mpd_extra_metadata_async.log');
// common include
require_once('/srv/http/app/libs/runeaudio.php');
// Connect to Redis backend
require_once('/srv/http/app/libs/openredis.php');
// include the music metadata decode module getid3
// getid3 needs to operate in directory /srv/http/app/libs/vendor
chdir('/srv/http/app/libs/vendor');
require_once('getid3/audioinfo.class.php');
// reset logfile
sysCmd('echo "--------------- start: mpd_extra_metadata_async.php ---------------" > /var/log/runeaudio/mpd_extra_metadata_async.log');
// logging starting message
runelog('WORKER mpd_extra_metadata_async.php STARTING...');
// define APP global
define('APP', '/srv/http/app/');
// get the album art directory and url dir
$artDir = rtrim(trim($redis->get('albumart_image_dir')), '/');
$artUrl = trim($redis->get('albumart_image_url_dir'), " \n\r\t\v\0/");
if ($redis->get('remoteSSbigart') === 'album') {
    $bigartIsAlbum = true;
} else {
    $bigartIsAlbum = false;
}
$mpdRoot = rtrim($redis->hGet('mpdconf', 'music_directory'), '/');
//
// get the lock status
$lock = $redis->get('lock_mpd_extra_metadata');
runelog('lock status ', $lock);
if (($lock === '0') || ($lock === '9')  || ($lock >= 9)) {
    // set the lock
    $redis->set('lock_mpd_extra_metadata', '1');
    // process the extra metadata
    $saveFile = '';
    $status = json_decode($redis->get('act_player_info'), true);
    while (($status['actPlayer'] == 'MPD') && !$status['radio'] && ($status['file'] != $saveFile)) {
        // loop while MPD, it is not a webradio and the song has changed
        // so exit if the song is the same at the start and end of the loop
        $saveFile = $status['file'];
        //
        // main processing
        //
        // open the MPD socket
        //
        $socket = openMpdSocket($redis->hGet('mpdconf', 'bind_to_address'), 0);
        $currsongid = '';
        $nextsongid = '';
        //
        // get the current status, this includes pointers to the current song and the next song
        //
        if ($socket && sendMpdCommand($socket, 'status')) {
            $retval = readMpdResponse($socket);
            if ($retval && strpos($retval, "OK\n")) {
                // response is valid
                $retarray = explode("\n", $retval);
                foreach ($retarray as $retline) {
                    if (strpos(' '.$retline, 'songid: ') == 1) {
                        // careful, 'songid: ' matches in the 'nextsongid: ' line
                        $currsongid = trim(explode(': ', $retline, 2)[1]);
                    } else if (strpos(' '.$retline, 'nextsongid: ') == 1) {
                        $nextsongid = trim(explode(': ', $retline, 2)[1]);
                    }
                }
            } else {
                // mpd response invalid, exit the outside loop
                break;
            }
        } else {
            // mpd socket invalid or send command failed, exit the outside loop
            break;
        }
        if (!$currsongid && !$nextsongid) {
            // nothing to do, exit the outside loop
            break;
        }
        $songinfo = array();
        //
        // get the current song information and create the 'currsong' cache table entry
        //
        if ($currsongid && $socket && sendMpdCommand($socket, 'playlistid '.$currsongid)) {
            $retval = readMpdResponse($socket);
            if ($retval && strpos($retval, "OK\n")) {
                // response is valid
                $retarray = explode("\n", $retval);
                $currsong = array();
                foreach ($retarray as $retline) {
                    if (strpos(' '.$retline, ': ')) {
                        $retlineparts = explode(': ', $retline, 2);
                        $retlineparts[0] = trim($retlineparts[0]);
                        $retlineparts[1] = trim($retlineparts[1]);
                        if ($retlineparts[0] && $retlineparts[1]) {
                            $songinfo['currsong'][strtolower($retlineparts[0])] = $retlineparts[1];
                        }
                    }
                }
            } else {
                // mpd response invalid, exit the outside loop
                break;
            }
        } else {
            // mpd socket invalid or send command failed, exit the outside loop
            break;
        }
        //
        // get the next song information and create the 'nextsong' cache table entry
        //
        if ($nextsongid && $socket && sendMpdCommand($socket, 'playlistid '.$nextsongid)) {
            $retval = readMpdResponse($socket);
            if ($retval && strpos($retval, "OK\n")) {
                // response is valid
                $retarray = explode("\n", $retval);
                foreach ($retarray as $retline) {
                    if (strpos(' '.$retline, ': ')) {
                        $retlineparts = explode(': ', $retline, 2);
                        $retlineparts[0] = trim($retlineparts[0]);
                        $retlineparts[1] = trim($retlineparts[1]);
                        if ($retlineparts[0] && $retlineparts[1]) {
                            $songinfo['nextsong'][strtolower($retlineparts[0])] = $retlineparts[1];
                        }
                    }
                }
            } else {
                // mpd response invalid, exit the outside loop
                break;
            }
        } else {
            // mpd socket invalid or send command failed, exit the outside loop
            break;
        }
        //
        // close the mpd socket and tidy up
        //
        closeMpdSocket($socket);
        unset($socket, $retval, $retarray, $retline, $retlineparts, $currsongid, $nextsongid);
        //
        // process the songinfo array, which has one or two entries, current song 'currsong' and in most cases also next song 'nextsong'
        //
        foreach ($songinfo as $songkey => &$song) {
            // note $song is by reference and can be modified
            //
            // retrieve and validate the cached information
            //
            $song['file'] = $mpdRoot.'/'.$song['file'];
            if (isset($song['album']) && isset($song['albumartist']) && isset($song['date']) && isset($song['title'])
                    && $song['album'] && $song['albumartist'] && $song['date'] && $song['title']) {
                $datafile = md5($song['album'].$song['albumartist'].$song['date'].$song['title']);
                $imagename = md5($song['album'].$song['albumartist'].$song['date']);
            } else if (isset($song['album']) && isset($song['artist']) && isset($song['title'])
                    && $song['album'] && $song['artist'] && $song['title']) {
                $datafile = md5($song['album'].$song['artist'].$song['title']);
                $imagename = md5($song['album'].$song['artist'].$song['date']);
            } else {
                $datafile = md5($song['file']);
                $imagename = $datafile;
            }
            $song['datafile'] = $artDir.'/'.$datafile.'.mpd';
            clearstatcache(true, $song['datafile']);
            if (file_exists($song['datafile'])) {
                touch($song['datafile']);
                $song = array_merge($song, json_decode(file_get_contents($song['datafile']), true));
            }
            if (isset($song['albumarturl'])) {
                // artfile entry in the array is set
                if (substr($song['albumarturl'], 0, 4) == 'http') {
                    // the name of the arturl is set and it is a web image, so still valid
                    // remove the album art file entry, it should not be there
                    unset($song['albumartfile']);
                } else if (isset($song['albumartfile']) && $song['albumartfile']) {
                    // album art file entry has a value
                    clearstatcache(true, $song['albumartfile']);
                    if (file_exists($song['albumartfile'])) {
                        // artfile exists, update its date stamp, the cache is still valid
                        touch($song['albumartfile']);
                    } else {
                        // cache is invalid clear the album art file and album art url entries
                        unset($song['albumartfile'], $song['albumarturl']);
                    }
                } else {
                    // cache is invalid clear the album art file and album art url entries
                    unset($song['albumartfile'], $song['albumarturl']);
                }
            } else {
                // cache is invalid clear the album art file and album art url entries
                unset($song['albumartfile'], $song['albumarturl']);
            }
            //
            // process album art
            //
            $artFound = false;
            if (isset($song['albumarturl'])) {
                // we already have a valid album art entry from the cache
                $artFound = true;
            }
            if (!$artFound) {
                // determine the album art
                // set up the file names, we assume jpg, but it could be something else, regardless
                //  of this, the browser seems to test the image file type and use it correctly
                $song['albumartfile'] = $artDir.'/'.$imagename.'.jpg';
                $song['albumarturl'] = $artUrl.'/'.$imagename.'.jpg';
                //
                // 1. try to extract embedded coverart
                // getid3 needs to operate in directory /srv/http/app/libs/vendor
                chdir('/srv/http/app/libs/vendor');
                // run getid3
                $au = new AudioInfo();
                $auinfo =  $au->Info($song['file']);
                if (isset($auinfo['comments']['picture'][0]['data']) && (strlen($auinfo['comments']['picture'][0]['data']) > 200)) {
                    // the music file has embedded metadata and it has a size of more than 200 bytes, save it
                    file_put_contents($song['albumartfile'], $auinfo['comments']['picture'][0]['data']);
                    // get some information about the file
                    list($width, $height, $type, $attr) = getimagesize($song['albumartfile']);
                    // width and height are in pixels (null when invalid), type is a non zero/null value when valid
                    if (($width > 20) && ($height > 20) && $type) {
                        // it is a valid image file (or at least it has a valid header) and it is at least 20x20px
                        $artFound = true;
                    } else {
                        // the image file has an invalid format or is very small, delete it
                        unlink($song['albumartfile']);
                    }
                }
                // save the other getid3 fields (e.g. average bitrate and sample rate)
                foreach ($auinfo as $valuekey => $value) {
                    if (!is_array($value)) {
                        // most of the useful information is stored at the first level of the array
                        $value = trim($value);
                        if ($value) {
                            $song[$valuekey] = $value;
                        }
                    } else {
                        // if there are music brainz id's in the metadata save them
                        $artist_mbid = trim(search_array_keys($value, 'artist_mbid'));
                        if ($artist_mbid) {
                            $song['artist_mbid'] = $artist_mbid;
                        }
                        $album_mbid = trim(search_array_keys($value, 'album_mbid'));
                        if ($album_mbid) {
                            $song['album_mbid'] = $album_mbid;
                        }
                        $song_mbid = trim(search_array_keys($value, 'song_mbid'));
                        if ($song_mbid) {
                            $song['song_mbid'] = $song_mbid;
                        }
                    }
                }
                unset($au, $auinfo, $width, $height, $type, $attr, $valuekey, $value, $artist_mbid, $album_mbid, $song_mbid);
            }
            if (!$artFound) {
                //
                // 2. try to find local coverart
                $coverArtFileNames = array('folder.jpg', 'cover.jpg', 'folder.png', 'cover.png');
                $coverArtDirectory = dirname($song['file']).'/';
                foreach ($coverArtFileNames as $coverArtFileName) {
                    clearstatcache(true, $coverArtDirectory.$coverArtFileName);
                    if (file_exists($coverArtDirectory.$coverArtFileName)) {
                        // there is a valid art file in the album directory, copy it to the art directory
                        copy($coverArtDirectory.$coverArtFileName, $song['albumartfile']);
                        $artFound = true;
                        // finish when one is found, exit the innermost loop
                        break;
                    }
                }
                unset($coverArtFileNames, $coverArtDirectory);
            }
            //
            // only when processing the current song and that albumart has been found update the UI
            if (($songkey == 'currsong') && $artFound) {
                // check the the current song is still valid
                $status = json_decode($redis->get('act_player_info'), true);
                if (($status['actPlayer'] == 'MPD') && !$status['radio'] && ($status['file'] == $saveFile)) {
                    // the current song is still valid
                    // write it when changed the main art url has changed
                    // if (!isset($status['mainArtURL']) || ($status['mainArtURL'] != $song['albumarturl'])) {
                        $status['mainArtURL'] = $song['albumarturl'];
                        if ($bigartIsAlbum) {
                            $status['bigArtURL'] = $song['albumarturl'];
                        } else {
                            $status['smallArtURL'] = $song['albumarturl'];
                        }
                        if (isset($song['avg_bit_rate']) && $song['avg_bit_rate']) {
                            $status['bitrate'] = intval($song['avg_bit_rate']/1000);
                        }
                        if (!isset($status['audio_sample_rate']) || !$status['audio_sample_rate']) {
                            if (isset($song['sample_rate']) && $song['sample_rate']) {
                                $status['audio_sample_rate'] = round($song['sample_rate']/1000, 1);
                            }
                        }
                        if (!isset($status['audio_sample_depth']) || !$status['audio_sample_depth']) {
                            if (isset($song['bits_per_sample']) && $song['bits_per_sample']) {
                                $status['audio_sample_depth'] = $song['bits_per_sample'];
                            }
                        }
                        unset($status['elapsed'], $status['song_percent']);
                        $redis->set('act_player_info', json_encode($status));
                        ui_render('playback', json_encode($status));
                        // unload CPU: 0.2 second sleep
                        // usleep(200000);
                    // }
                } else {
                    // the song has changed
                    // currently in a double loop, continue at the end of the outside loop
                    continue 2;
                }
            } else if (($songkey == 'nextsong') && $artFound) {
                // when processing the nextsong only update the cover art preload url
                // check the the current song is still valid
                $status = json_decode($redis->get('act_player_info'), true);
                if (($status['actPlayer'] == 'MPD') && !$status['radio'] && ($status['file'] == $saveFile)) {
                    // the current song is still valid
                    // set the cover art preload to the next song art url when changed
                    if (!isset($status['coverArtPreload']) || ($status['coverArtPreload'] != $song['albumarturl'])) {
                        $status['coverArtPreload'] = $song['albumarturl'];
                        unset($status['elapsed'], $status['song_percent']);
                        $redis->set('act_player_info', json_encode($status));
                        ui_render('playback', json_encode($status));
                        // unload CPU: 0.2 second sleep
                        // usleep(200000);
                    }
                } else {
                    // the song has changed
                    // currently in a double loop, continue at the end of the outside loop
                    continue 2;
                }
            }
            //
            // get the artistinfo & lyrics
            //
            $info = array();
            $info['artist'] = $song['artist'];
            $info['albumartist'] = $song['albumartist'];
            $info['song'] = $song['title'];
            $info['album'] = $song['album'];
            if (isset($song['artist_mbid'])) {
                $info['artist_mbid'] = $song['artist_mbid'];
            }
            if (isset($song['album_mbid'])) {
                $info['album_mbid'] = $song['album_mbid'];
            }
            if (isset($song['song_mbid'])) {
                $info['song_mbid'] = $song['song_mbid'];
            }
            $retval = get_artistInfo($redis, $info);
            if ($retval) {
                $info = array_merge($info, $retval);
            }
            if (strpos(' '.strtolower($song['genre']), 'classical')) {
                // genre is classical, skip the lyrics
                $info['song_lyrics'] = 'Lyrics retrieval omitted for the "classical" genre';
            } else {
                // when not classical get the lyrics
                $retval = get_songInfo($redis, $info);
                if ($retval) {
                    $info = array_merge($info, $retval);
                }
            }
            // it seems illogical for this to be here however it is more effective to search
            // for cover art on internet together with the artist and song information
            if (!$artFound) {
                // 3.0 try to find coverart on internet
                $retval = get_albumInfo($redis, $info);
                if ($retval) {
                    $info = array_merge($info, $retval);
                }
                // the routine always returns image names, including when 'not found'
                //  album_arturl_large, album_arturl_medium, and album_arturl_small
                // in this case there is no image file
                unset($song['albumartfile']);
                $song['albumarturl'] = $info['album_arturl_medium'];
                $artFound = true;
            }
            if ($artFound) {
                // save the songinfo data
                file_put_contents($song['datafile'], json_encode($song)."\n");
            }
            //
            if ($songkey == 'currsong') {
                // currnet song
                // check the the current song is still valid
                $status = json_decode($redis->get('act_player_info'), true);
                if (($status['actPlayer'] == 'MPD') && !$status['radio'] && ($status['file'] == $saveFile)) {
                    // the current song is still valid
                    $status['mainArtURL'] = $song['albumarturl'];
                    if ($bigartIsAlbum) {
                        $status['bigArtURL'] = $song['albumarturl'];
                    } else {
                        $status['smallArtURL'] = $song['albumarturl'];
                    }
                    if (isset($song['avg_bit_rate']) && $song['avg_bit_rate']) {
                        $status['bitrate'] = intval($song['avg_bit_rate']/1000);
                    }
                    if (!isset($status['audio_sample_rate']) || !$status['audio_sample_rate']) {
                        if (isset($song['sample_rate']) && $song['sample_rate']) {
                            $status['audio_sample_rate'] = round($song['sample_rate']/1000, 1);
                        }
                    }
                    if (!isset($status['audio_sample_depth']) || !$status['audio_sample_depth']) {
                        if (isset($song['bits_per_sample']) && $song['bits_per_sample']) {
                            $status['audio_sample_depth'] = $song['bits_per_sample'];
                        }
                    }
                    $status['song_lyrics'] = $info['song_lyrics'];
                    $status['artist_bio_summary'] = $info['artist_bio_summary'];
                    $status['artist_similar'] = $info['artist_similar'];
                    if ($artUrl == substr($info['artist_arturl'], 0, strlen($artUrl))) {
                        // the artist art has not been found, so use the album art
                        $info['artist_arturl'] = $song['albumarturl'];
                    }
                    if ($bigartIsAlbum) {
                        $status['smallArtURL'] = $info['artist_arturl'];
                    } else {
                        $status['bigArtURL'] = $info['artist_arturl'];
                    }
                    unset($status['elapsed'], $status['song_percent']);
                    $redis->set('act_player_info', json_encode($status));
                    ui_render('playback', json_encode($status));
                    // unload CPU: 0.2 second sleep
                    // usleep(200000);
                } else {
                    // the song has changed
                    // currently in a double loop, continue at the end of the outside loop
                    continue 2;
                }
            } else if (($songkey == 'nextsong') && $artFound) {
                // when processing the nextsong only update the cover art preload url
                // check the the current song is still valid
                $status = json_decode($redis->get('act_player_info'), true);
                if (($status['actPlayer'] == 'MPD') && !$status['radio'] && ($status['file'] == $saveFile)) {
                    // the current song is still valid
                    // set the cover art preload to the next song art url when changed
                    if (!isset($status['coverArtPreload']) || ($status['coverArtPreload'] != $song['albumarturl'])) {
                        $status['coverArtPreload'] = $song['albumarturl'];
                        unset($status['elapsed'], $status['song_percent']);
                        $redis->set('act_player_info', json_encode($status));
                        ui_render('playback', json_encode($status));
                        // unload CPU: 0.2 second sleep
                        // usleep(200000);
                    }
                } else {
                    // the song has changed
                    // currently in a double loop, continue at the end of the outside loop
                    continue 2;
                }
            }
            //
            unset($info);
        }
        $status = json_decode($redis->get('act_player_info'), true);
    }
    // close the socket if its not been done
    if (isset($socket) && $socket) {
        closeMpdSocket($socket);
    }
    // unlock
    $redis->set('lock_mpd_extra_metadata', '0');
} else {
    runelog("LOCKED!", '');
    echo "LOCKED!";
    // just in case something goes wrong increment the lock value by 1
    // when it reaches 9 (this should never happen) it will be processed as if there is no lock
    $lock += 1;
    $redis->set('lock_mpd_extra_metadata', $lock);
}
runelog('lock status ', $redis->get('lock_mpd_extra_metadata'));
// close Redis connection
$redis->close();

runelog('WORKER mpd_extra_metadata_async.php END...');
#---
#End script
