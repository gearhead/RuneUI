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
 *  file: app/libs/runeaudio.php
 *  version: 1.3
 *  coder: Simone De Gregori
 *
 */

// Expected MPD & SPOP Open Response messages
// MPD: " OK MPD x.xx.xx\n" (16 bytes, as of version 0.21.11)
// SPOP: "spop x.x.x\n" (11 bytes, as of version 0.0.1)
// Where x is a numeric vanue (version number)

function is_localhost() {
    $whitelist = array( '127.0.0.1', '::1' );
    if( in_array( $_SERVER['REMOTE_ADDR'], $whitelist) )
        return true;
}

function openMpdSocket($path, $type = null)
// connection types: 0 = normal (blocking), 1 = burst mode (blocking), 2 = burst mode 2 (non blocking)
{
    $sock = socket_create(AF_UNIX, SOCK_STREAM, 0);
    // create non blocking socket connection
    if ($type === 1 OR $type === 2) {
        if ($type === 2) {
            socket_set_nonblock($sock);
            runelog('opened **BURST MODE 2 (non blocking)** socket resource: ',$sock);
        } else {
            runelog('opened **BURST MODE (blocking)** socket resource: ',$sock);
        }
        $sock = array('resource' => $sock, 'type' => $type);
        $connection = socket_connect($sock['resource'], $path);
        if ($connection) {
            // skip MPD greeting response (first 20 bytes or until the first \n, \r or \0) - trim the trailing \n, \r or \0, but not spaces/tabs for reporting
            $header = rtrim(socket_read($sock['resource'], 20, PHP_NORMAL_READ), "\n\r\0");
            // the header should contain a 'OK', if not something went wrong
            if (!strpos(' '.$header, 'OK')) {
                runelog("[error][".$sock['resource']."]\t>>>>>> MPD OPEN SOCKET ERROR REPORTED - Greeting response: ".$header."<<<<<<",'');
                // ui_notifyError('MPD open error: '.$sock.'','Greeting response = '.$header);
                closeMpdSocket($sock);
                return false;
            }
            runelog("[open][".$sock['resource']."]\t>>>>>> OPEN MPD SOCKET - Greeting response: ".$header."<<<<<<",'');
            return $sock;
        } else {
            runelog("[error][".$sock['resource']."]\t>>>>>> MPD SOCKET ERROR: ".socket_last_error($sock['resource'])." <<<<<<",'');
            // ui_notifyError('MPD sock: '.$sock.'','socket error = '.socket_last_error($sock));
            return false;
        }
    // create blocking socket connection
    } else {
        runelog('opened **NORMAL MODE (blocking)** socket resource: ',$sock);
        $connection = socket_connect($sock, $path);
        if ($connection) {
            // skip MPD greeting response (first 20 bytes or until the first \n, \r or \0) - trim the trailing \n, \r or \0, but not spaces/tabs for reporting
            $header = socket_read($sock, 20, PHP_NORMAL_READ);
            // the header should contain a 'OK', if not something went wrong
            if (!strpos(' '.$header, 'OK')) {
                runelog("[error][".$sock['resource']."]\t>>>>>> MPD OPEN SOCKET ERROR REPORTED - Greeting response: ".$header."<<<<<<",'');
                // ui_notifyError('MPD open error: '.$sock.'','Greeting response = '.$header);
                closeMpdSocket($sock);
                return false;
            }
            runelog("[open][".$sock."]\t<<<<<<<<<<<< OPEN MPD SOCKET ---- MPD greeting response: ".$header.">>>>>>>>>>>>",'');
            return $sock;
        } else {
            runelog("[error][".$sock."]\t<<<<<<<<<<<< MPD SOCKET ERROR: ".socket_strerror(socket_last_error($sock))." >>>>>>>>>>>>",'');
            // ui_notifyError('MPD sock: '.$sock.'','socket error = '.socket_last_error($sock));
            return false;
        }
    }
}

function closeMpdSocket($sock)
{
    if ((is_array($sock)) && (isset($sock['resource']))) {
        $sockResource = $sock['resource'];
    } else {
        $sockResource = $sock;
    }
    sendMpdCommand($sock, 'close');
    // socket_shutdown($sock, 2);
    // debug
    runelog("[close][".$sockResource."]\t<<<<<< CLOSE MPD SOCKET (".socket_strerror(socket_last_error($sockResource)).") >>>>>>",'');
    socket_close($sockResource);
}

function sendMpdCommand($sock, $cmd)
{
    if ((is_array($sock)) && (isset($sock['resource']))) {
        $sockResource = $sock['resource'];
    } else {
        $sockResource = $sock;
    }
    $cmd = $cmd."\n";
    socket_write($sockResource, $cmd, strlen($cmd));
    runelog("MPD COMMAND: (socket=".$sockResource.")", $cmd);
}

// detect end of MPD response
function checkEOR($chunk)
{
    if (strpos(" ".$chunk, "OK\n")) {
        return true;
    } elseif (strpos(" ".$chunk, "ACK [")) {
        if (preg_match("/(\[[0-9]@[0-9]\])/", $chunk) === 1) {
            return true;
        }
    } else {
        return false;
    }
}

function readMpdResponse($sock)
{
     if ((is_array($sock)) && (isset($sock['resource']))) {
        $sockResource = $sock['resource'];
        $sockType = $sock['type'];
    } else {
        $sockResource = $sock;
        $sockType = 0;
    }
    // initialize vars
    $output = '';
    $read = '';
    $read_monitor = array();
    $write_monitor  = NULL;
    $except_monitor = NULL;
    // debug
    // socket monitoring
    // iteration counter
    // $i = 0;
    // timestamp
    // $starttime = microtime(true);
    // runelog('START timestamp:', $starttime);
    if ($sockType === 2) {
        // handle burst mode 2 (nonblocking) socket session
        $read_monitor = array($sockResource);
        $buff = 1024;
        // debug
        // $socket_activity = socket_select($read_monitor, $write_monitor, $except_monitor, NULL);
        // runelog('socket_activity (pre-loop):', $socket_activity);
        $end = 0;
        while($end === 0) {
            if (is_resource($sockResource)) {
                $read = socket_read($sockResource, $buff);
            } else {
                break;
            }
            if (checkEOR($read)) {
                ob_start();
                echo $read;
                // flush();
                ob_flush();
                ob_end_clean();
                $end = 1;
                break;
            }
            if (strpos($read, "\n")) {
                ob_start();
                echo $read;
                // flush();
                ob_flush();
                ob_end_clean();
            } else {
                continue;
            }
            usleep(200);
        }
    } elseif ($sockType === 1) {
    // handle burst mode 1 (blocking) socket session
        $read_monitor = array($sockResource);
        $buff = 1310720;
        // debug
        // $socket_activity = socket_select($read_monitor, $write_monitor, $except_monitor, NULL);
        // runelog('socket_activity (pre-loop):', $socket_activity);
        do {
            // debug
            // $i++;
            // $elapsed = microtime(true);
            // read data from socket
            if (is_resource($sockResource) === true) {
                $read = socket_read($sockResource, $buff);
            } else {
                break;
            }
            // debug
            // runelog('socket_read status', $read);
            if ($read === '' OR $read === false) {
                $output = socket_strerror(socket_last_error($sockResource));
                // debug
                runelog('socket disconnected!!!', $output);
                break;
            }
            $output .= $read;
            // usleep(200);
            // debug
            // runelog('_1_socket_activity (in-loop): iteration='.$i.' ', $socket_activity);
            // runelog('_1_buffer length:', strlen($output));
            // runelog('_1_iteration:', $i);
            // runelog('_1_timestamp:', $elapsed);
        } while (!checkEOR($read));
        // debug
        // runelog('END timestamp:', $elapsed);
        // runelog('RESPONSE length:', strlen($output));
        // runelog('EXEC TIME:', $elapsed - $starttime);
        return $output;
    } else {
        // handle normal mode (blocking) socket session
        $read_monitor = array($sockResource);
        $buff = 4096;
        // debug
        // $socket_activity = socket_select($read_monitor, $write_monitor, $except_monitor, NULL);
        // runelog('socket_activity (pre-loop):', $socket_activity);
        do {
            // debug
            // $i++;
            // $elapsed = microtime(true);
            if (is_resource($sockResource) === true) {
                $read = socket_read($sockResource, $buff, PHP_NORMAL_READ);
            } else {
                break;
            }
            // debug
            // runelog('socket_read status', $read);
            if ($read === '' OR $read === false) {
                $output = socket_strerror(socket_last_error($sockResource));
                // debug
                runelog('socket disconnected!!!', $output);
                break;
            }
            $output .= $read;
            // usleep(200);
            // debug
            // runelog('read buffer content (0 mode)', $read);
            // runelog('_0_buffer length:', strlen($output));
            // runelog('_0_iteration:', $i);
            // runelog('_0_timestamp:', $elapsed);
        } while (!checkEOR($read));
        // debug
        // runelog('END timestamp:', $elapsed);
        // runelog('RESPONSE length:', strlen($output));
        // runelog('EXEC TIME:', $elapsed - $starttime);
        return $output;
    }
}

function sendMpdIdle($sock)
{
    //sendMpdCommand($sock,"idle player,playlist");
    sendMpdCommand($sock,'idle');
    $response = readMpdResponse($sock);
    $response = array_map('trim', explode(":", $response));
    return $response;
}

function monitorMpdState($redis, $sock)
{
    if ($change = sendMpdIdle($sock)) {
        $status = _parseStatusResponse($redis, MpdStatus($sock));
        $status['changed'] = substr($change[1], 0, -3);
        // runelog('monitorMpdState()', $status);
        return $status;
    } else {
        $status = false;
    }
}

function getTrackInfo($sock, $songID)
{
    // set currentsong, currentartis, currentalbum
    sendMpdCommand($sock, 'playlistinfo '.$songID);
    $track = readMpdResponse($sock);
    // runelog('+++++++++++++ getTrackInfo data +++++++++++++++', $track);
    return _parseFileListResponse($track);
}

function getPlayQueue($sock)
{
    sendMpdCommand($sock, 'playlistinfo');
    $playqueue = readMpdResponse($sock);
    //return _parseFileListResponse($playqueue);
    return $playqueue;
}

// Spotify support
function openSpopSocket($host, $port, $type = null)
// connection types: 0 = normal (blocking), 1 = burst mode (blocking), 2 = burst mode 2 (non blocking)
{
    $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    // create non blocking socket connection
    if ($type === 1 OR $type === 2) {
        if ($type === 2) {
            socket_set_nonblock($sock);
            runelog('opened **BURST MODE 2 (non blocking)** socket resource: ',$sock);
        } else {
            runelog('opened **BURST MODE (blocking)** socket resource: ',$sock);
        }
        $sock = array('resource' => $sock, 'type' => $type);
        $connection = socket_connect($sock['resource'], $host, $port);
        if ($connection) {
            // skip SPOP greeting response (first 20 bytes or until the first \n, \r or \0) - trim the trailing \n, \r or \0, but not spaces/tabs, for reporting
            $header = rtrim(socket_read($sock['resource'], 20, PHP_NORMAL_READ), "\n\r\0");
            runelog("[open][".$sock['resource']."]\t>>>>>> OPEN SPOP SOCKET greeting response: ".$header."<<<<<<",'');
            return $sock;
        } else {
            runelog("[error][".$sock['resource']."]\t>>>>>> SPOP SOCKET ERROR: ".socket_last_error($sock['resource'])." <<<<<<",'');
            // ui_notifyError('SPOP sock: '.$sock.'','socket error = '.socket_last_error($sock));
            return false;
        }
    // create blocking socket connection
    } else {
        runelog('opened **NORMAL MODE (blocking)** socket resource: ',$sock);
        $connection = socket_connect($sock, $host, $port);
        if ($connection) {
            // skip SPOP greeting response (first 20 bytes or until the first \n, \r or \0) - trim the trailing \n, \r or \0, but not spaces/tabs, for reporting
            $header = rtrim(socket_read($sock, 11, PHP_NORMAL_READ), "\n\r\0");
            runelog("[open][".$sock['resource']."]\t>>>>>> OPEN SPOP SOCKET greeting response: ".$header."<<<<<<",'');
            return $sock;
        } else {
            runelog("[error][".$sock."]\t<<<<<<<<<<<< SPOP SOCKET ERROR: ".socket_strerror(socket_last_error($sock))." >>>>>>>>>>>>",'');
            // ui_notifyError('SPOP sock: '.$sock.'','socket error = '.socket_last_error($sock));
            return false;
        }
    }
}

function closeSpopSocket($sock)
{
     if ((is_array($sock)) && (isset($sock['resource']))) {
        $sockResource = $sock['resource'];
    } else {
        $sockResource = $sock;
    }
    sendSpopCommand($sock, 'bye');
    // socket_shutdown($sock, 2);
    // debug
    runelog("[close][".$sock."]\t<<<<<< CLOSE SPOP SOCKET (".socket_strerror(socket_last_error($sock)).") >>>>>>",'');
    socket_close($sockResource);
}


function sendSpopCommand($sock, $cmd)
{
     if ((is_array($sock)) && (isset($sock['resource']))) {
        $sockResource = $sock['resource'];
    } else {
        $sockResource = $sock;
    }
    $cmd = $cmd."\n";
    socket_write($sockResource, $cmd, strlen($cmd));
    runelog("SPOP COMMAND: (socket=".$sockResource.")", $cmd);
    //ui_notify('COMMAND GIVEN','CMD = '.$cmd,'','.9');
}

// detect end of SPOP response
function checkSpopEOR($chunk)
{
    if (strpos($chunk, "\n") !== false) {
        return true;
    } else {
        return false;
    }
}

function readSpopResponse($sock)
{
    if ((is_array($sock)) && (isset($sock['resource']))) {
        $sockResource = $sock['resource'];
        $sockType = $sock['type'];
    } else {
        $sockResource = $sock;
        $sockType = 0;
    }
    // initialize vars
    $output = '';
    $read = '';
    $read_monitor = array();
    $write_monitor  = NULL;
    $except_monitor = NULL;
    // debug
    // socket monitoring
    // iteration counter
    // $i = 0;
    // timestamp
    // $starttime = microtime(true);
    // runelog('START timestamp:', $starttime);
    if ($sockType === 2) {
        // handle burst mode 2 (nonblocking) socket session
        $read_monitor = array($sockResource);
        $buff = 1024;
        $end = 0;
        // debug
        // $socket_activity = socket_select($read_monitor, $write_monitor, $except_monitor, NULL);
        // runelog('socket_activity (pre-loop):', $socket_activity);
        while($end === 0) {
            if (is_resource($sockResource) === true) {
                $read = socket_read($sockResource, $buff);
            } else {
                break;
            }
            if (checkSpopEOR($read) === true) {
                ob_start();
                echo $read;
                // flush();
                ob_flush();
                ob_end_clean();
                $end = 1;
                break;
            }
            if (strpos($read, "\n")) {
                ob_start();
                echo $read;
                // flush();
                ob_flush();
                ob_end_clean();
            } else {
                continue;
            }
            usleep(200);
        }
    } elseif ($sockType === 1) {
        // handle burst mode 1 (blocking) socket session
        $read_monitor = array($sockResource);
        $buff = 1310720;
        // debug
        // $socket_activity = socket_select($read_monitor, $write_monitor, $except_monitor, NULL);
        // runelog('socket_activity (pre-loop):', $socket_activity);
        do {
            // debug
            // $i++;
            // $elapsed = microtime(true);
            // read data from socket
            if (is_resource($sockResource) === true) {
                $read = socket_read($sockResource, $buff);
            } else {
                break;
            }
            // debug
            // runelog('socket_read status', $read);
            if ($read === '' OR $read === false) {
                $output = socket_strerror(socket_last_error($sockResource));
                // debug
                runelog('socket disconnected!!!', $output);
                break;
            }
            $output .= $read;
            // usleep(200);
            // debug
            // runelog('_1_socket_activity (in-loop): iteration='.$i.' ', $socket_activity);
            // runelog('_1_buffer length:', strlen($output));
            // runelog('_1_iteration:', $i);
            // runelog('_1_timestamp:', $elapsed);
        } while (checkSpopEOR($read) === false);
        // debug
        // runelog('END timestamp:', $elapsed);
        // runelog('RESPONSE length:', strlen($output));
        // runelog('EXEC TIME:', $elapsed - $starttime);
        return $output;
    } else {
        // handle normal mode (blocking) socket session
        $read_monitor = array($sockResource);
        $buff = 4096;
        // debug
        // $socket_activity = socket_select($read_monitor, $write_monitor, $except_monitor, NULL);
        // runelog('socket_activity (pre-loop):', $socket_activity);
        do {
            // debug
            // $i++;
            // $elapsed = microtime(true);
            if (is_resource($sockResource) === true) {
                $read = socket_read($sockResource, $buff, PHP_NORMAL_READ);
            } else {
                break;
            }
            // debug
            // runelog('socket_read status', $read);
            if ($read === '' OR $read === false) {
                $output = socket_strerror(socket_last_error($sockResource));
                // debug
                runelog('socket disconnected!!!', $output);
                break;
            }
            $output .= $read;
            // usleep(200);
            // debug
            // runelog('read buffer content (0 mode)', $read);
            // runelog('_0_buffer length:', strlen($output));
            // runelog('_0_iteration:', $i);
            // runelog('_0_timestamp:', $elapsed);
        } while (checkSpopEOR($read) === false);
        // debug
        // runelog('END timestamp:', $elapsed);
        // runelog('RESPONSE length:', strlen($output));
        // runelog('EXEC TIME:', $elapsed - $starttime);
        return $output;
    }
}

function sendSpopIdle($sock)
{
    sendSpopCommand($sock,'idle');
    $response = readSpopResponse($sock);
    return $response;
}

function monitorSpopState($sock)
{
    if ($change = sendSpopIdle($sock)) {
        $status = _parseSpopStatusResponse(SpopStatus($sock));
        runelog('monitorSpopState()', $status);
        return $status;
    }
}

function SpopStatus($sock)
{
    sendSpopCommand($sock, "status");
    $status = readSpopResponse($sock);
    return $status;
}

function getSpopPlayQueue($sock)
{
    sendSpopCommand($sock, 'qpls');
    $playqueue = readSpopResponse($sock);
    //return _parseFileListResponse($playqueue);
    return $playqueue;
}

function getSpopQueue($sock)
{
    $queue = '';
    sendSpopCommand($sock, 'qls');
    $playqueue = readSpopResponse($sock);
    //return _parseFileListResponse($playqueue);
    $pl = json_decode($playqueue);
    foreach ($pl->tracks as $track) {
        $queue .= "file: ".$track->uri."\n";
        $queue .= "Time: ".($track->duration / 1000)."\n";
        $queue .= "Track: ".$track->index."\n";
        $queue .= "Title: ".$track->title."\n";
        $queue .= "Artist: ".$track->artist."\n";
        $queue .= "AlbumArtist: ".$track->artist."\n";
        $queue .= "Album: ".$track->album."\n";
        $queue .= "Date:\n";
        $queue .= "Genre:\n";
        $queue .= "Pos: ".$track->index."\n";
        $queue .= "Id: ".$track->index."\n";
    }
    return $queue;
}

function spopDB($sock, $plid = null)
{
    if (isset($plid)) {
        sendSpopCommand($sock,"ls ".$plid);
    } else {
        sendSpopCommand($sock, 'ls');
    }
    $response = readSpopResponse($sock);
    return $response;
}

function getMpdOutputs($mpd)
{
    sendMpdCommand($mpd, 'outputs');
    $outputs= readMpdResponse($mpd);
    return $outputs;
}

function getMpdCurrentsongInfo($mpd, $raw=false)
// returns the current song information unaltered (as returned by MPD) or as an array of information elements
// by default an array is returned, by specifying a non-false value for $raw the the data from MPD is returned unaltered
{
    sendMpdCommand($mpd, 'currentsong');
    $songinfo= readMpdResponse($mpd);
    if ($raw) {
        return $songinfo;
    } else {
        return _parseMpdresponse($songinfo);
    }
}

function getLastFMauth($redis)
{
    $lastfmauth = $redis->hGetAll('lastfm');
    return $lastfmauth;
}

function setLastFMauth($redis, $lastfm)
{
    $redis->hSet('lastfm', 'user', $lastfm->user);
    $redis->hSet('lastfm', 'pass', $lastfm->pass);
}

function saveBookmark($redis, $path)
{
    $idx = $redis->incr('bookmarksidx');
    $name = parseFileStr($path,'/');
    $return = $redis->hSet('bookmarks', $idx, json_encode(array('name' => $name, 'path' => $path)));
    return $return;
}

function deleteBookmark($redis, $id)
{
    $return = $redis->hDel('bookmarks', $id);
    return $return;
}

function browseDB($sock,$browsemode,$query='') {
    switch ($browsemode) {
        case 'file':
            if (isset($query) && !empty($query)){
                sendMpdCommand($sock,'lsinfo "'.html_entity_decode($query).'"');
            } else {
                sendMpdCommand($sock,'lsinfo');
            }
            break;
        case 'album':
            if (isset($query) && !empty($query)){
                sendMpdCommand($sock,'find "album" "'.html_entity_decode($query).'"');
            } else {
                sendMpdCommand($sock,'list "album"');
            }
            break;
        case 'artist':
            if (isset($query) && !empty($query)){
                if ($query === 'Various Artists') {
                    sendMpdCommand($sock,'list artist albumartist "Various Artists"');
                } else {
                    sendMpdCommand($sock,'list "album" "'.html_entity_decode($query).'"');
                }
            } else {
                sendMpdCommand($sock,'list "albumartist"');
            }
            break;
        case 'composer':
            if (isset($query) && !empty($query)){
                sendMpdCommand($sock,'find "composer" "'.html_entity_decode($query).'"');
            } else {
                sendMpdCommand($sock,'list "composer"');
            }
            break;
        case 'genre':
            if (isset($query) && !empty($query)){
                sendMpdCommand($sock,'list "albumartist" "genre" "'.html_entity_decode($query).'"');
            } else {
                sendMpdCommand($sock,'list "genre"');
            }
            break;
        case 'albumfilter':
            if (isset($query) && !empty($query)){
                sendMpdCommand($sock,'find "albumartist" "'.html_entity_decode($query).'" "album" ""');
            }
            break;
        case 'globalrandom':
            sendMpdCommand($sock,'listall');
            break;
    }
    $response = readMpdResponse($sock);
    return _parseFileListResponse($response);
}

function searchDB($sock,$querytype,$query) {
    sendMpdCommand($sock,"search ".$querytype." \"".html_entity_decode($query)."\"");
    $response = readMpdResponse($sock);
    return _parseFileListResponse($response);
}

function remTrackQueue($sock, $songpos)
{
    $datapath = findPLposPath($songpos, $sock);
    sendMpdCommand($sock, 'delete '.$songpos);
    $response = readMpdResponse($sock);
    return $datapath;
}

function addToQueue($sock, $path, $addplay = null, $pos = null, $clear = null)
{
    $fileext = parseFileStr($path,'.');
    $cmd = ($fileext == 'm3u' OR $fileext == 'pls' OR $fileext == 'cue') ? "load" : "add";
    if (isset($addplay) || isset($clear)) {
        $cmdlist = "command_list_begin\n";
        $cmdlist .= (isset($clear)) ? "clear\n" : "";               // add clear call if needed
        $cmdlist .= $cmd." \"".html_entity_decode($path)."\"\n";
        $cmdlist .= (isset($addplay)) ? "play ".$pos."\n" : "";     // add play call if needed
        $cmdlist .= "command_list_end";
        sendMpdCommand($sock, $cmdlist);
    } else {
        sendMpdCommand($sock, $cmd." \"".html_entity_decode($path)."\"");
    }
}

function addAlbumToQueue($sock, $path, $addplay = null, $pos = null, $clear = null)
{
    if (isset($addplay)) {
        $cmdlist = "command_list_begin\n";
        $cmdlist .= (isset($clear)) ? "clear\n" : "";               // add clear call if needed
        $cmdlist .= "findadd \"album\" \"".html_entity_decode($path)."\"\n";
        $cmdlist .= (isset($addplay)) ? "play ".$pos."\n" : "";     // add play call if needed
        $cmdlist .= "command_list_end";
        sendMpdCommand($sock, $cmdlist);
    } else {
        sendMpdCommand($sock, "findadd \"album\" \"".html_entity_decode($path)."\"");
    }
}

function addArtistToQueue($sock, $path, $addplay = null, $pos = null, $clear = null)
{
    if (isset($addplay)) {
        $cmdlist = "command_list_begin\n";
        $cmdlist .= (isset($clear)) ? "clear\n" : "";               // add clear call if needed
        $cmdlist .= "findadd \"artist\" \"".html_entity_decode($path)."\"\n";
        $cmdlist .= (isset($addplay)) ? "play ".$pos."\n" : "";     // add play call if needed
        $cmdlist .= "command_list_end";
        sendMpdCommand($sock, $cmdlist);
    } else {
        sendMpdCommand($sock, "findadd \"artist\" \"".html_entity_decode($path)."\"");
    }
}

function addGenreToQueue($sock, $path, $addplay = null, $pos = null, $clear = null)
{
    if (isset($addplay)) {
        $cmdlist = "command_list_begin\n";
        $cmdlist .= (isset($clear)) ? "clear\n" : "";               // add clear call if needed
        $cmdlist .= "findadd \"genre\" \"".html_entity_decode($path)."\"\n";
        $cmdlist .= (isset($addplay)) ? "play ".$pos."\n" : "";     // add play call if needed
        $cmdlist .= "command_list_end";
        sendMpdCommand($sock, $cmdlist);
    } else {
        sendMpdCommand($sock, "findadd \"genre\" \"".html_entity_decode($path)."\"");
    }
}

function addComposerToQueue($sock, $path, $addplay = null, $pos = null, $clear = null)
{
    if (isset($addplay)) {
        $cmdlist = "command_list_begin\n";
        $cmdlist .= (isset($clear)) ? "clear\n" : "";               // add clear call if needed
        $cmdlist .= "findadd \"composer\" \"".html_entity_decode($path)."\"\n";
        $cmdlist .= (isset($addplay)) ? "play ".$pos."\n" : "";     // add play call if needed
        $cmdlist .= "command_list_end";
        sendMpdCommand($sock, $cmdlist);
    } else {
        sendMpdCommand($sock, "findadd \"composer\" \"".html_entity_decode($path)."\"");
    }
}

function MpdStatus($sock)
{
    sendMpdCommand($sock, "status");
    $status = readMpdResponse($sock);
    return $status;
}

function songTime($sec)
{
    $minutes = sprintf('%02d', floor($sec / 60));
    $seconds = sprintf(':%02d', (int) $sec % 60);
    return $minutes.$seconds;
}

function sysCmd($syscmd)
{
    exec($syscmd." 2>&1", $output);
    runelog('sysCmd($str)', $syscmd);
    runelog('sysCmd() output:', $output);
    return $output;
}

function sysCmdAsync($syscmd, $waitsec = null) {
    if (isset($waitsec)) {
        $cmdstr = "/var/www/command/cmd_async ".base64_encode($syscmd);
    } else {
        $cmdstr = "/var/www/command/cmd_async ".base64_encode($syscmd);
    }
    exec($cmdstr." > /dev/null 2>&1 &", $output);
    runelog('sysCmdAsync($cmdstr) decoded', $syscmd, __FUNCTION__);
    runelog('sysCmdAsync($cmdstr) encoded', $cmdstr, __FUNCTION__);
    runelog('sysCmdAsync() output:', $output, __FUNCTION__);
    return $output;
}

function getMpdDaemonDetalis()
{
    $cmd = sysCmd('id -u mpd');
    $details['uid'] = $cmd[0];
    $cmd = sysCmd('id -g mpd');
    $details['gid'] = $cmd[0];
    $cmd = sysCmd('pgrep -u mpd');
    $details['pid'] = $cmd[0];
    return $details;
}

// using an array as needles in strpos
function strposa($haystack, $needle, $offset=0)
{
    if (!is_array($needle)) $needle = array($needle);
    foreach ($needle as $query) {
        if (strpos($haystack, $query, $offset) !== false) return true; // stop on first true result
    }
    return false;
}

// format Output for "playlist"
function _parseFileListResponse($resp)
{
    if (is_null($resp)) {
        return null;
    } else {
        // $start_time = microtime(TRUE);
        $plistArray = array();
        $plistLine = strtok($resp, "\n");
        // $plistFile = "";
        $plCounter = -1;
        $element = '';
        $value = '';
        $browseMode = TRUE;
        while ($plistLine) {
            // runelog('_parseFileListResponse plistLine', $plistLine);
            if (!strpos(' '.$plistLine,'@eaDir') && !strpos(' '.$plistLine,'.Trash')) {
                if (strpos(' '.$plistLine, ': ')) {
                    list ($element, $value) = explode(': ', $plistLine, 2);
                } else {
                    $element = '';
                    $value = '';
                }
            } else {
                $element = '';
                $value = '';
            }
            // $blacklist = ['@eaDir', '.Trash'];
            // if (!strposa($plistLine, $blacklist)) list ($element, $value) = explode(': ', $plistLine, 2);
            if ($element === 'file' OR $element === 'playlist') {
                $plCounter++;
                $browseMode = FALSE;
                // $plistFile = $value;
                $plistArray[$plCounter][$element] = $value;
                $plistArray[$plCounter]['fileext'] = parseFileStr($value, '.');
            } elseif ($element === 'directory') {
                $plCounter++;
                // record directory index for further processing
                $dirCounter++;
                // $plistFile = $value;
                $plistArray[$plCounter]['directory'] = $value;
            } elseif ($browseMode) {
                if ( $element === 'Album' ) {
                    $plCounter++;
                    $plistArray[$plCounter]['album'] = $value;
                } elseif ( $element === 'Artist' ) {
                    $plCounter++;
                    $plistArray[$plCounter]['artist'] = $value;
                } elseif ( $element === 'AlbumArtist' ) {
                    $plCounter++;
                    $plistArray[$plCounter]['artist'] = $value;
                } elseif ( $element === 'Composer' ) {
                    $plCounter++;
                    $plistArray[$plCounter]['composer'] = $value;
                } elseif ( $element === 'Genre' ) {
                    $plCounter++;
                    $plistArray[$plCounter]['genre'] = $value;
                }
            } else {
                // runelog('_parseFileListResponse Element', $element);
                // runelog('_parseFileListResponse Value', $value);
                // runelog('_parseFileListResponse plistArray [ plCounter ]', $plistArray[$plCounter]);
                if ( $plCounter > -1 ) {
                    if (($element != '')) {
                        $plistArray[$plCounter][$element] = $value;
                    }
                    if ( $element === 'Time' ) {
                        $plistArray[$plCounter]['Time2'] = songTime($plistArray[$plCounter]['Time']);
                    }
                }
            }
            $plistLine = strtok("\n");
        }
        // $end_time = microtime(TRUE);
        // if (($end_time - $start_time) > 0.1) {
            // ui_notify_async('ELAPSED', $end_time - $start_time);
        // }
    }
    return $plistArray;
}

// format Output for "status"
function _parseStatusResponse($redis, $resp)
{
    if (isset($resp)) {
        $resp = trim($resp);
    } else {
        return null;
    }
    if (is_null($resp)) {
        return null;
    } else if (empty($resp)) {
        return null;
    } else {
        $plistArray = array();
        $plistLine = strtok($resp, "\n");
        $plistFile = "";
        $plCounter = -1;
        while ($plistLine) {
            // runelog('_parseStatusResponse plistLine', $plistLine);
            if (strpos(' '.$plistLine,': ')) {
                list ($element, $value) = explode(': ', $plistLine, 2);
                $plistArray[$element] = $value;
            }
            $plistLine = strtok("\n");
        }
        // "elapsed time song_percent" added to output array
        if (isset($plistArray['time'])) {
            $time = explode(":", $plistArray['time']);
            if ($time[0] != 0 && $time[1] != 0) {
                $percent = round(($time[0]*100)/$time[1]);
            } else {
                $percent = 0;
            }
            $plistArray["song_percent"] = $percent;
            $plistArray["elapsed"] = $time[0];
            $plistArray["time"] = $time[1];
        } else {
            $plistArray["song_percent"] = 0;
            $plistArray["elapsed"] = 0;
            $plistArray["time"] = 0;
        }

         // "audio format" output
         if (isset($plistArray['audio'])) {
            $audio_format = explode(":", $plistArray['audio']);
            $retval = sysCmd('grep -hi "rate: " /proc/asound/card?/pcm?p/sub?/hw_params');
            switch (strtoupper($audio_format[0])) {
                case 'DSD64':
                case 'DSD128':
                case 'DSD256':
                case 'DSD512':
                case 'DSD1024':
                    if (trim($retval[0]) != '') {
                        $audio_format[2] = $audio_format[1];
                        $audio_format[1] = 'dsd';
                        $dsdRate = preg_replace('/[^0-9]/', '', $audio_format[0]);
                        $audio_format[0] = intval(explode(' ', $retval[0])[1]);
                        $plistArray['bitrate'] = intval(44100 * $dsdRate / 1000);
                        $plistArray['audio_sample_rate'] = rtrim(number_format($audio_format[0], 0, ',', '.'),0);
                        $plistArray['audio'] = $audio_format[0].':'.$audio_format[1].':'.$audio_format[2];
                    }
                    break;
                case '48000':
                    // no break
                case '96000':
                    // no break
                case '192000':
                    // no break
                case '384000':
                    $plistArray['audio_sample_rate'] = rtrim(rtrim(number_format($audio_format[0]), 0), ',');
                    break;
                case '44100':
                    // no break
                case '88200':
                    // no break
                case '176400':
                    // no break
                case '352800':
                    // no break
                default:
                    $plistArray['audio_sample_rate'] = rtrim(number_format($audio_format[0], 0, ',', '.'),0);
                    break;
            }
        } else {
            $audio_format[2] = 0;
            $audio_format[1] = '';
            $dsdRate = 0;
            $audio_format[0] = 0;
            $plistArray['bitrate'] = 0;
            $plistArray['audio_sample_rate'] = 0;
            $plistArray['audio'] = $audio_format[0].':'.$audio_format[1].':'.$audio_format[2];
         }
        unset($retval);
        // format "audio_sample_depth" string
        $plistArray['audio_sample_depth'] = $audio_format[1];
        // format "audio_channels" string
        if (is_numeric($audio_format[2])) {
            if ($audio_format[2] === "2") {
                $plistArray['audio_channels'] = "Stereo";
            } else if ($audio_format[2] === "1") {
                $plistArray['audio_channels'] = "Mono";
            } else if ($audio_format[2] > "0") {
                $plistArray['audio_channels'] = "Multichannel";
            }
        } else if ($plistArray['audio_channels'] != '') {
            // do nothing
        } else {
            $plistArray['audio_channels'] = "Stereo";
        }
        //
        // when bitrate still is empty use mediainfo to examine the file which is playing
        // but only when the file-name is available and mediainfo is installed
        // ignore any line returned by mpd status containing 'updating'
        $status = sysCmd('mpc status | grep -vi updating');
        // bit rate
        // clear the cache otherwise file_exists() returns incorrect values
        clearstatcache(true, '/usr/bin/mediainfo');
        if ((($plistArray['bitrate'] == '0') || ($plistArray['bitrate'] == '')) && (count($status) == 3) && (file_exists('/usr/bin/mediainfo'))) {
            $retval = sysCmd('mpc -f "[%file%]"');
            $retval = sysCmd('mediainfo "'.trim($redis->hGet('mpdconf', 'music_directory')).'/'.trim($retval[0]).'" | grep "Overall bit rate  "');
            $bitrate = trim(preg_replace('/[^0-9]/', '', $retval[0]));
            If (!empty($bitrate)) {
                $plistArray['bitrate'] = intval($bitrate);
            }
            unset($retval);
        }
        // sample rate
        if ((($plistArray['audio_sample_rate'] == '0') || ($plistArray['audio_sample_rate'] == '')) && (count($status) == 3)) {
            $retval = sysCmd('mpc -f "[%file%]"');
            $retval = sysCmd('ffprobe -v error -show_entries stream=sample_rate -of default=noprint_wrappers=1 "'.trim($redis->hGet('mpdconf', 'music_directory')).'/'.trim($retval[0]).'"');
            $samplerate = trim(preg_replace('/[^0-9]/', '', $retval[0]));
            If (!empty($samplerate)) {
                $plistArray['audio_sample_rate'] = rtrim(number_format($samplerate, 0, ',', '.'),0);
            }
            unset($retval);
        }
        // sample format
        // if ((($plistArray['??'] == '0') || ($plistArray['??'] == '')) && (count($status) === 3)) {
            // $retval = sysCmd('mpc -f "[%file%]"');
            // $retval = sysCmd('ffprobe -v error -show_entries stream=sample_fmt -of default=noprint_wrappers=1 "'.trim($redis->hGet('mpdconf', 'music_directory')).'/'.trim($retval[0]).'"');
            // $sampleformat = trim(preg_replace('/[^0-9]/', '', $retval[0]));
            // If (!empty($sampleformat)) {
                // $plistArray['??'] = $sampleformat;
            // }
            // unset($retval);
        // }
        unset($status);
    }
    return $plistArray;
}

function _parseSpopStatusResponse($resp)
{
if (is_null($resp)) {
        return null;
    } else {
        $status = array();
        $resp = json_decode($resp);
        if ($resp->status === "playing") $status['state'] = "play";
        if ($resp->status === "stopped") $status['state'] = "stop";
        if ($resp->status === "paused") $status['state'] = "pause";
        if ($resp->repeat === false) {
            $status['repeat'] = '0';
        } else {
            $status['repeat'] = '1';
        }
        if ($resp->shuffle === false) {
            $status['random'] = '0';
        } else {
            $status['random'] = '1';
        }
        $status['playlistlength'] = $resp->total_tracks;
        $status['currentartist'] = $resp->artist;
        $status['currentalbum'] = $resp->album;
        $status['currentsong'] = $resp->title;
        $status['song'] = $resp->current_track -1;
        if (isset($resp->position)) {
            $status['elapsed'] = $resp->position;
        } else {
            $status['elapsed'] = 0;
        }
        $status['time'] = $resp->duration / 1000;
        $status['volume'] = 100;
        if ($resp->status === "stopped") {
            $status['song_percent'] = 0;
        } else {
            $status['song_percent'] = round(100 - (($status['time'] - $status['elapsed']) * 100 / $status['time']));
        }
        $status['uri'] = $resp->uri;
        $status['popularity'] = $resp->popularity;
        return $status;
    }
}
// function to structure an MPD response into an array indexed by the information elements
function _parseMpdresponse($input)
// $input is the output from MPD
// expected input format is "element1: value1<new_line>element2: value2
// returns false if no 'OK' detected in the input string from MPD
{
    if (isset($input)) {
        $resp = trim($input);
    } else {
        return null;
    }
    if (is_null($input)) {
        return null;
    } else if (empty($input)) {
        return null;
    } else {
        $plistArray = array();
        $isOk = false;
        $plistLine = strtok($input, "\n\t\r\0");
        while ($plistLine) {
            // runelog('_parseMpdResponse plistLine', $plistLine);
            if (strpos(' '.$plistLine,'OK')) {
                $isOk = true;
            }
            if (strpos(' '.$plistLine,': ')) {
                list ($element, $value) = explode(': ', $plistLine, 2);
                $plistArray[trim($element)] = trim($value);
            }
            $plistLine = strtok("\n\t\r");
        }
    }
    if ($isOk) {
        return $plistArray;
    } else {
        return false;
    }
}
//
// no longer used
// function _parseOutputsResponse($input, $active)
// {
    // if (is_null($input)) {
        // return null;
    // } else {
        // $response = preg_split("/\r?\n/", $input);
        // $outputs = array();
        // $linenum = 0;
        // $i = -1;
        // foreach($response as $line) {
            // if ($linenum % 3 == 0) {
                // $i++;
            // }
            // if (!empty($line)) {
            // $value = explode(':', $line);
            // $outputs[$i][$value[0]] = trim($value[1]);
                // if (isset($active)) {
                    // if ($value[0] === 'outputenabled' && $outputs[$i][$value[0]]) {
                        // $active = $i;
                    // }
                // }
            // } else {
                // unset($outputs[$i]);
            // }
            // $linenum++;
        // }
    // }
    // if (isset($active)) {
        // return $active;
    // } else {
        // return $outputs;
    // }
// }

// get file extension
function parseFileStr($strFile, $delimiter, $negative = null)
{
    // runelog("parseFileStr($strFile,$delimiter)");
    $pos = strrpos($strFile, $delimiter);
    // runelog('parseFileStr (position of delimiter)',$pos);
    if (isset($negative)) {
        $str = substr($strFile, 0, -4);
    } else {
        $str = substr($strFile, $pos+1);
    }
    // runelog('parseFileStr (string)',$str);
    return $str;
}

function OpCacheCtl($action, $basepath, $redis = null)
{
    if ($action === 'prime' OR $action === 'primeall') $cmd = 'opcache_compile_file';
    if ($action === 'reset') $cmd = 'opcache_invalidate';
    if ($action === 'prime') {
        $files = $redis->sMembers('php_opcache_prime');
        foreach ($files as $file) {
            opcache_compile_file($file);
        }
    }
    if ($action === 'primeall' OR $action === 'reset') {
        // clear the cache otherwise is_file() and is_dir() return incorrect values
        clearstatcache(true, $basepath);
        if (is_file($basepath)) {
            if (parseFileStr($basepath,'.') === 'php' && $basepath !== '/srv/http/command/cachectl.php' ) $cmd ($basepath);
        }
        elseif(is_dir($basepath)) {
            $scan = glob(rtrim($basepath,'/').'/*');
            foreach($scan as $index=>$path) {
                OpCacheCtl($path,$action);
            }
        }
    }
}

function netMounts($redis, $action, $data = null)
{
    // mountpoint input format
    // $data = array( 'name' => '', 'type' => '', 'address' => '', 'remotedir' => '', 'username' => '', 'password' => '', 'charset' => '', 'rsize' => '', 'wsize' => '', 'options' => '', 'error' => '' );
    switch ($action) {
        case 'list':
            $mp = $redis->Keys('mount_*');
            runelog('keys list: ', $mp);
            break;
        case 'read':
            if (isset($data)) {
                $mp = $redis->hGetAll($data);
            } else {
                $mp = array();
                $mounts = netMounts($redis, 'list');
                foreach ($mounts as $mount) {
                    $mp[] = netMounts($redis, 'read', $mount);
                }
            }
            break;
        case 'write':
            $redis->hSet('mount_'.$data['name'], 'name', $data['name']) || $mp = 0;
            $redis->hSet('mount_'.$data['name'], 'type', $data['type']) || $mp = 0;
            $redis->hSet('mount_'.$data['name'], 'address', $data['address']) || $mp = 0;
            $redis->hSet('mount_'.$data['name'], 'remotedir', $data['remotedir']) || $mp = 0;
            $redis->hSet('mount_'.$data['name'], 'username', $data['username']) || $mp = 0;
            $redis->hSet('mount_'.$data['name'], 'password', $data['password']) || $mp = 0;
            $redis->hSet('mount_'.$data['name'], 'charset', $data['charset']) || $mp = 0;
            $redis->hSet('mount_'.$data['name'], 'rsize', $data['rsize']) || $mp = 0;
            $redis->hSet('mount_'.$data['name'], 'wsize', $data['wsize']) || $mp = 0;
            $redis->hSet('mount_'.$data['name'], 'options', $data['options']) || $mp = 0;
            $redis->hSet('mount_'.$data['name'], 'error', $data['error']) || $mp = 0;
            if (!isset($mp)) {
                $mp = 1;
            } else {
                $redis->Del('mount_'.$data['name']);
            }
            break;
        case 'delete':
            if (isset($data)) {
                $mp = $redis->Del('mount_'.$data['name']);
            } else {
                $mp = sysCmd('redis-cli KEYS "mount_*" | xargs redis-cli DEL');
            }
            break;
    }
    return $mp;
}

// Ramplay functions
function rp_checkPLid($id, $mpd)
{
    $_SESSION['DEBUG'] .= "rp_checkPLid:$id |";
    sendMpdCommand($mpd,'playlistid '.$id);
    $response = readMpdResponse($mpd);
    echo "<br>debug__".$response;
    echo "<br>debug__".stripos($response,'MPD error');
    if (stripos($response,'OK')) {
        return true;
    } else {
        return false;
    }
}

//<< TODO: join with findPLposPath
function rp_findPath($id, $mpd)
{
    sendMpdCommand($mpd, 'playlistid '.$id);
    $idinfo = _parseFileListResponse(readMpdResponse($mpd));
    $path = $idinfo[0]['file'];
    return $path;
}

//<< TODO: join with rp_findPath()
function findPLposPath($songpos, $mpd)
{
    sendMpdCommand($mpd, 'playlistinfo '.$songpos);
    $idinfo = _parseFileListResponse(readMpdResponse($mpd));
    $path = $idinfo[0]['file'];
    return $path;
}

function rp_deleteFile($id, $mpd)
{
    $_SESSION['DEBUG'] .= "rp_deleteFile:$id |";
    if (unlink(rp_findPath($id, $mpd))) {
        return true;
    } else {
        return false;
    }
}

function rp_copyFile($id, $mpd)
{
    $_SESSION['DEBUG'] .= "rp_copyFile: $id|";
    $path = rp_findPath($id, $mpd);
    $song = parseFileStr($path, "/");
    $realpath = "/mnt/".$path;
    $ramplaypath = "/dev/shm/".$song;
    $_SESSION['DEBUG'] .= "rp_copyFilePATH: $path $ramplaypath|";
    if (copy($realpath, $ramplaypath)) {
        $_SESSION['DEBUG'] .= "rp_addPlay:$id $song $path $pos|";
        return $path;
    } else {
        return false;
    }
}

function rp_updateFolder($mpd)
{
    $_SESSION['DEBUG'] .= "rp_updateFolder: |";
    sendMpdCommand($mpd, "update ramplay");
}

function rp_addPlay($path, $mpd, $pos)
{
    $song = parseFileStr($path,"/");
    $ramplaypath = "ramplay/".$song;
    $_SESSION['DEBUG'] .= "rp_addPlay:$id $song $path $pos|";
    addToQueue($mpd, $ramplaypath);
    sendMpdCommand($mpd, 'play '.$pos);
}

function rp_clean()
{
    $_SESSION['DEBUG'] .= "rp_clean: |";
    recursiveDelete('/dev/shm/');
}

function recursiveDelete($str)
{
    // clear the cache otherwise is_file() and is_dir() return incorrect values
    clearstatcache($str);
    if(is_file($str)) {
        return @unlink($str);
        // TODO: add search path in playlist and remove from playlist
    }
    elseif(is_dir($str)) {
        $scan = glob(rtrim($str, '/').'/*');
        foreach($scan as $index=>$path) {
            recursiveDelete($path);
        }
    }
}

function pushFile($filepath)
{
    // debug
    runelog('pushFile(): filepath', $filepath);
    // clear the cache otherwise file_exists() returns incorrect values
    clearstatcache(true, $filepath);
    if (file_exists($filepath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename='.basename($filepath));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: '.filesize($filepath));
        ob_clean();
        flush();
        readfile($filepath);
        return true;
    } else {
        return false;
    }
}

// check if mpd.conf or interfaces was modified outside
function hashCFG($action, $redis)
{
    switch ($action) {
        case 'check_net':
            // --- CODE REWORK NEEDED ---
            //$hash = md5_file('/etc/netctl/eth0');
            // have to find the settings file by MAC address in connman
            $eth0MAC = sysCmd("ip link show dev eth0 | grep 'link/ether' | sed 's/^[ \t]*//' |cut -d ' ' -f 2 | tr -d ':'");
            $hash = md5_file('/var/lib/connman/ethernet_'.$eth0MAC[0].'_cable/settings');
            if ($redis->get('netconfhash') !== $hash) {
                $redis->set('netconf_advanced', 1);
                return false;
            } else {
                $redis->set('netconf_advanced', 0);
            }
            break;
        case 'check_mpd':
            $hash = md5_file('/etc/mpd.conf');
            if ($redis->get('mpdconfhash') !== $hash) {
                $redis->set('mpdconf_advanced', 1);
                return false;
            } else {
                $redis->set('mpdconf_advanced', 0);
            }
            break;
    }
    return true;
}


function runelog($title, $data = null, $function_name = null)
{
// Connect to Redis backend
    $store = new Redis();
//    $store->connect('127.0.0.1', 6379);
    $store->pconnect('/run/redis/socket');
    $debug_level = $store->get('debug');
    if (isset($function_name)) {
        $function_name = '['.$function_name.'] ';
    } else {
        $function_name = '';
    }
    if ($debug_level !== '0') {
        if(is_array($data) OR is_object($data)) {
            if (is_array($data)) error_log($function_name.'### '.$title.' ### $data type = array',0);
            if (is_object($data)) error_log($function_name.'### '.$title.' ### $data type = object',0);
            foreach($data as $key => $value) {
                error_log($function_name.'### '.$title.' ###  [\''.$key.'\'] => '.$value,0);
            }
        } else {
            error_log($function_name.'### '.$title.' ###  '.$data,0);
        }
    }
    $store->close();
}

function waitSyWrk($redis, $jobID)
{
    if (is_array($jobID)) {
        foreach ($jobID as $job) {
            do {
                usleep(650000);
            } while ($redis->sIsMember('w_lock', $job));
        }
    } elseif (!empty($jobID)) {
        do {
            usleep(650000);
        } while ($redis->sIsMember('w_lock', $jobID));
    }
}

function getmac($nicname)
{
    // clear the cache otherwise file_exists() returns incorrect values
    clearstatcache(true, '/sys/class/net/'.$nicname.'/address');
    if (file_exists('/sys/class/net/'.$nicname.'/address')) {
        // get the nic address if it exists
        $mac = file_get_contents('/sys/class/net/'.$nicname.'/address');
    } else {
        // if not, get the first valid nic address (a Zero has no internal eth0 network adaptor)
        $retval = sysCmd('cat /sys/class/net/*/address | grep -v 00:00:00:00');
        $mac = trim($retval[0]);
        unset($retval);
    }
    $mac = strtolower($mac);
    runelog('getmac('.$nicname.'): ', $mac);
    return trim($mac);
}

function wrk_xorgconfig($redis, $action, $args)
{
    switch ($action) {
        case 'start':
            // no break
        case 'stop':
            // no break
        case 'enable-splash':
            if ($args) {
                // spash on
                // enable the systemd boot splash unit
                sysCmd('systemctl enable bootsplash');
                // set the redis variable enable-splash to true
                $redis->hSet('local_browser', 'enable-splash', 1);
            } else {
                // spash off
                // enable the systemd boot splash unit
                sysCmd('systemctl disable bootsplash');
                // set the redis variable enable-splash to false
                $redis->hSet('local_browser', 'enable-splash', 0);
            }
            break;
        case 'zoomfactor':
            // modify the zoom factor in /etc/X11/xinit/start_chromium.sh
            $file = '/etc/X11/xinit/xinitrc';
            // replace the line with 'force-device-scale-factor='
            $newArray = wrk_replaceTextLine($file, '', 'force-device-scale-factor', 'sudo -u http /usr/bin/chromium --kiosk --incognito -e http://localhost/ --force-device-scale-factor='.$args);
            // Commit changes to /etc/X11/xinit/start_chromium.sh
            $fp = fopen($file, 'w');
            $return = fwrite($fp, implode("", $newArray));
            fclose($fp);
            break;
        case 'rotate':
            sysCmd('/srv/http/command/raspi-rotate-screen.sh '.$args);
            break;
        case 'mouse_cursor':
            if ($args){
                $usecursorno = '';
            } else {
                $usecursorno = '-use_cursor no ';
            }
            // modify the mouse on/off setting in /etc/X11/xinit/xinitrc
            $file = '/etc/X11/xinit/xinitrc';
            // replace the line with 'matchbox-window-manager'
            $newArray = wrk_replaceTextLine($file, '', 'matchbox-window-manager', 'matchbox-window-manager -use_titlebar no '.$usecursorno.'&');
            // Commit changes to /etc/X11/xinit/xinitrc
            $fp = fopen($file, 'w');
            $return = fwrite($fp, implode("", $newArray));
            fclose($fp);
            break;
    }
}

function wrk_avahiconfig($redis, $hostname)
{
    // clear the cache otherwise file_exists() returns incorrect values
    clearstatcache(true, '/etc/avahi/services/runeaudio.service');
    if (!file_exists('/etc/avahi/services/runeaudio.service')) {
        runelog('avahi service descriptor not present, initializing...');
        sysCmd('/usr/bin/cp /var/www/app/config/defaults/avahi_runeaudio.service /etc/avahi/services/runeaudio.service');
    }
    $file = '/etc/avahi/services/runeaudio.service';
    $newArray = wrk_replaceTextLine($file, '','replace-wildcards', '<name replace-wildcards="yes">RuneAudio ['.$hostname.'] ['.getmac('eth0').']</name>');
    // Commit changes to /tmp/runeaudio.service
    $newfile = '/tmp/runeaudio.service';
    $fp = fopen($newfile, 'w');
    fwrite($fp, implode("", $newArray));
    fclose($fp);
    // check that the conf file has changed
    if (md5_file($file) === md5_file($newfile)) {
        // nothing has changed, set avahiconfchange off
        $redis->set('avahiconfchange', 0);
        syscmd('rm -f '.$newfile);
    } else {
        // avahi configuration has changed, set avahiconfchange on
        $redis->set('avahiconfchange', 1);
        syscmd('cp '.$newfile.' '.$file);
        syscmd('rm -f '.$newfile);
        // also modify /etc/hosts replace line beginning with 127.0.0.1 (PIv4)
        syscmd('sed -i "/^127.0.0.1/c\127.0.0.1       localhost localhost.localdomain '.$hostname.'.local '.$hostname.'" /etc/hosts');
        // and line beginning with ::1 (IPv6)
        syscmd('sed -i "/^::1/c\::1       localhost localhost.localdomain '.$hostname.'.local '.$hostname.'" /etc/hosts');
    }
}

function wrk_control($redis, $action, $data)
{
    $jobID = "";
    // accept $data['action'] $data['args'] from controller
    switch ($action) {
        case 'newjob':
            // generate random jobid
            $jobID = wrk_jobID();
            $wjob = array(
                'wrkcmd' => (isset($data['wrkcmd'])? $data['wrkcmd'] : NULL),
                'action' => (isset($data['action'])? $data['action'] : NULL),
                'args' => (isset($data['args'])? $data['args'] : NULL)
            );
            $redis->hSet('w_queue', $jobID, json_encode($wjob));
            runelog('wrk_control data:', $redis->hGet('w_queue', $jobID));
            break;
    }
    // debug
    runelog('[wrk] wrk_control($redis,'.$action.',$data) jobID='.$jobID, $data, 'wrk_control');
    return $jobID;
}

// search a string in a file and replace with another string the whole line.
function wrk_replaceTextLine($file, $inputArray, $strfind, $strrepl, $linelabel = null, $lineoffset = null)
{
    runelog('wrk_replaceTextLine($file, $inputArray, $strfind, $strrepl, $linelabel, $lineoffset)','');
    runelog('wrk_replaceTextLine $file', $file);
    runelog('wrk_replaceTextLine $strfind', $strfind);
    runelog('wrk_replaceTextLine $strrepl', $strrepl);
    runelog('wrk_replaceTextLine $linelabel', $linelabel);
    runelog('wrk_replaceTextLine $lineoffset', $lineoffset);
    if (!empty($file)) {
        $fileData = file($file);
    } else {
        $fileData = $inputArray;
    }
    $newArray = array();
    if (isset($linelabel) && isset($lineoffset)) {
        $linenum = 0;
    }
    foreach($fileData as $line) {
        if (isset($linelabel) && isset($lineoffset)) {
            $linenum++;
            if (preg_match('/'.$linelabel.'/', $line)) {
                $lineindex = $linenum;
                runelog('line index match! $line', $lineindex);
            }
            if ((($lineindex+$lineoffset)-$linenum)==0) {
                if (preg_match('/'.$strfind.'/', $line)) {
                    $line = $strrepl."\n";
                    runelog('internal loop $line', $line);
                }
            }
        } else {
          if (preg_match('/'.$strfind.'/', $line)) {
            $line = $strrepl."\n";
            runelog('replaceall $line', $line);
          }
        }
      $newArray[] = $line;
    }
    return $newArray;
}

function wrk_backup($redis, $bktype = null)
{
    // get the directory which is used for the backup
    $fileDestDir = '/'.trim($redis->get('backup_dir'), "/ \t\n\r\0\x0B").'/';
    // build up the backup command string
    if ($bktype === 'dev') {
        $filepath = $fileDestDir.'backup-total-'.date("Y-m-d").'.tar.gz';
        $cmdstring = "rm -f '".$fileDestDir."backup-total-*' &> /dev/null;".
            " bsdtar -czpf '".$filepath."'".
            " /mnt/MPD/Webradio".
            " /var/lib/redis/rune.rdb".
            " '".$redis->hGet('mpdconf', 'db_file')."'".
            " '".$redis->hGet('mpdconf', 'sticker_file')."'".
            " '".$redis->hGet('mpdconf', 'playlist_directory')."'".
            " '".$redis->hGet('mpdconf', 'state_file')."'".
            " /var/lib/connman".
            " /var/www".
            " /etc".
            "";
    } else {
        $filepath = $fileDestDir.'backup-'.date("Y-m-d").'.tar.gz';
        $cmdstring = "rm -f '".$fileDestDir."backup-*' &> /dev/null;".
            " bsdtar -czpf '".$filepath."'".
            " /mnt/MPD/Webradio".
            " /var/lib/redis/rune.rdb".
            " '".$redis->hGet('mpdconf', 'db_file')."'".
            " '".$redis->hGet('mpdconf', 'sticker_file')."'".
            " '".$redis->hGet('mpdconf', 'playlist_directory')."'".
            " '".$redis->hGet('mpdconf', 'state_file')."'".
            " /var/lib/connman".
            " /etc/mpd.conf".
            "";
    }
    // add the names of the distribution files
    $extraFiles = sysCmd('find /var/www/app/config/defaults/ -type f');
    foreach ($extraFiles as $extraFile) {
        // convert the names of the distribution files to the location of production version (the one being used)
        $fileName = str_replace('/var/www/app/config/defaults', '', $extraFile);
        if (($bktype === 'dev') && ((substr($fileName, 0, 9) === '/var/www/') || (substr($fileName, 0, 5) === '/etc/'))) {
            // skip any files in /var/www/ and /etc/ for a dev backup, they are already included
            continue;
        }
        // clear the cache otherwise file_exists() returns incorrect values
        clearstatcache(true, $fileName);
        if (file_exists($fileName)) {
            // add the files to the backup command if they exist
            $cmdstring .= " '".$fileName."'";
        }
    }
    ui_notify('Diagnosics', $cmdstring);
    // remove debug data from redis
    $redis->set('debugdata', '');
    // save the redis database
    $redis->save();
    // run the backup
    sysCmd($cmdstring);
    // change the file privileges
    sysCmd('chown http.http '."'".$filepath."'".' ; chmod 644 '."'".$filepath."'");
    // regenerate the debug data for redis
    sysCmdAsync('nice --adjustment=2 /srv/http/command/debug_collector');
    return $filepath;
}

function wrk_restore($redis, $backupfile)
{
    $fileDestDir = '/'.trim($redis->get('backup_dir'), "/ \t\n\r\0\x0B").'/';
    $lenDestDir = strlen($fileDestDir);
    if (substr($backupfile, 0, $lenDestDir) === $fileDestDir) {
        // only allow a restore from the backup directory
        ui_notify('Restore backup starting', 'please wait for a restart...');
        sysCmd('/srv/http/command/restore.sh '.$backupfile);
        // a reboot will be initiated in restore.sh, it will never come back here
    } else {
        ui_notifyError('Error', 'Attempted to restore from the incorrect directory: '.$backupfile);
        // delete the backup file, OK if this fails
        unlink($backupfile);
    }
    return;
}

function wrk_opcache($action, $redis)
{
    // debug
    runelog('wrk_opcache ', $action);
    switch ($action) {
        case 'prime':
            opcache_reset();
            if ($redis->get('opcache')) sysCmd('curl http://127.0.0.1/command/cachectl.php?action=prime');
            break;
        case 'forceprime':
            opcache_reset();
            sysCmd('curl http://127.0.0.1/command/cachectl.php?action=prime');
            break;
        case 'reset':
            // sysCmd('curl http://127.0.0.1/clear');
            // reset cache
            OpCacheCtl('reset', '/srv/http/');
            opcache_reset();
            break;
        case 'enable':
            // opcache.ini
            $file = '/etc/php/conf.d/opcache.ini';
            $newArray = wrk_replaceTextLine($file, '', 'opcache.enable', 'opcache.enable=1', 'zend_extension', 1);
            // Commit changes to /etc/php/conf.d/opcache.ini
            $fp = fopen($file, 'w');
            fwrite($fp, implode("", $newArray));
            fclose($fp);
            $redis->set('opcache', 1);
            break;
        case 'disable':
            // opcache.ini
            // -- REWORK NEEDED --
            $file = '/etc/php/conf.d/opcache.ini';
            $newArray = wrk_replaceTextLine($file, '', 'opcache.enable', 'opcache.enable=0', 'zend_extension', 1);
            // Commit changes to /etc/php/conf.d/opcache.ini
            $fp = fopen($file, 'w');
            fwrite($fp, implode("", $newArray));
            fclose($fp);
            $redis->set('opcache', 0);
            break;
    }
}

// KEW
// takes a netmask and returns the CIDR notation
// in: net_NetmaskToCidr("255.255.255.0");
// out: 24
function net_NetmaskToCidr($netmask) {
    $bits = 0;
    $chunks = explode(".", $netmask);
    foreach($chunks as $octect) {
        $bits += strlen(str_replace("0", "", decbin($octect)));
    }
    return $bits;
}

// KEW
// takes CIDR notation and returns the netmask string
// in: net_CidrToNetmask(24);
// out: "255.255.255.0"
function net_CidrToNetmask($cidr) {
    $netmask = str_split(str_pad(str_pad('', $cidr, '1'), 32, '0'), 8);
    foreach ($netmask as &$element) {
        $element = bindec($element);
    }
    return join('.', $netmask);
}

function wrk_apconfig($redis, $action, $args = null)
{
    $return = array();
    runelog('wrk_apconfig args = ', $args);
    switch ($action) {
        case 'writecfg':
            if (isset($args->{'enable'})) {
                $redis->hSet('AccessPoint', 'enable', $args->{'enable'});
            } else {
                $redis->hSet('AccessPoint', 'enable', 0);
            }
            $redis->hSet('AccessPoint', 'ssid', $args->{'ssid'});
            $redis->hSet('AccessPoint', 'passphrase', $args->{'passphrase'});
            $redis->hSet('AccessPoint', 'ip-address', $args->{'ip-address'});
            $redis->hSet('AccessPoint', 'broadcast', $args->{'broadcast'});
            $redis->hSet('AccessPoint', 'dhcp-range', $args->{'dhcp-range'});
            $redis->hSet('AccessPoint', 'dhcp-option-dns', $args->{'dhcp-option-dns'});
            $redis->hSet('AccessPoint', 'dhcp-option-router', $args->{'dhcp-option-router'});
            if ($args->{'enable-NAT'} === '1') {
                $redis->hSet('AccessPoint', 'enable-NAT', $args->{'enable-NAT'});
            } else {
                $redis->hSet('AccessPoint', 'enable-NAT', 0);
            }
            if ($args->{'reboot'} === '1') {
                runelog('**** AP reboot requested ****', $args);
                $return = 'reboot';
            } elseif ($args->{'restart'} === '1') {
                runelog('**** AP restart requested ****', $args);
                // change AP name
                $file = '/etc/hostapd/hostapd.conf';
                $newArray = wrk_replaceTextLine($file, '', 'ssid=', 'ssid='.$args->{'ssid'});
                $fp = fopen($file, 'w');
                $return = fwrite($fp, implode("", $newArray));
                fclose($fp);
                // change passphrase
                $file = '/etc/hostapd/hostapd.conf';
                $newArray = wrk_replaceTextLine($file, '', 'wpa_passphrase=', 'wpa_passphrase='.$args->{'passphrase'});
                $fp = fopen($file, 'w');
                $return = fwrite($fp, implode("", $newArray));
                fclose($fp);
                sysCmd('systemctl start hostapd');
                // change dhcp-range
                $file = '/etc/dnsmasq.conf';
                $newArray = wrk_replaceTextLine($file, '', 'dhcp-range=', 'dhcp-range='.$args->{'dhcp-range'});
                $fp = fopen($file, 'w');
                $return = fwrite($fp, implode("", $newArray));
                fclose($fp);
                // change dhcp-option
                $file = '/etc/dnsmasq.conf';
                $newArray = wrk_replaceTextLine($file, '', 'dhcp-option-force=option:dns-server,', 'dhcp-option-force=option:dns-server,'.$args->{'dhcp-option-dns'});
                $fp = fopen($file, 'w');
                $return = fwrite($fp, implode("", $newArray));
                fclose($fp);
                $file = '/etc/dnsmasq.conf';
                $newArray = wrk_replaceTextLine($file, '', 'dhcp-option-force=option:router,', 'dhcp-option-force=option:router,'.$args->{'dhcp-option-router'});
                $fp = fopen($file, 'w');
                $return = fwrite($fp, implode("", $newArray));
                fclose($fp);
                sysCmd('ip addr flush dev wlan0');
                sysCmd('ip addr add '.$args->{'ip-address'}.'/24 broadcast '.$args->{'broadcast'}.' dev wlan0');
                sysCmd('systemctl reload-or-restart hostapd');
                sysCmd('systemctl reload-or-restart dnsmasq');
                if ($args->{'enable-NAT'} === '1') {
                    sysCmd('iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE');
                    sysCmd('iptables -A FORWARD -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT');
                    sysCmd('iptables -A FORWARD -i wlan0 -o eth0 -j ACCEPT');
                    sysCmd('sysctl net.ipv4.ip_forward=1');
                } else {
                    sysCmd('sysctl net.ipv4.ip_forward=0');
                }
                $return = '';
            }
            sysCmd('qrencode -l H -t PNG -o /var/www/assets/img/RuneAudioAP.png "WIFI:S:'.$args->ssid.';T:WPA2;P:'.$args->passphrase.';;"');
            sysCmd('qrencode -l H -t PNG -o /var/www/assets/img/RuneAudioURL.png http://'.$args->{'ip-address'});
            break;
        case 'reset':
            break;
    }
    return $return;
}

function wrk_netconfig($redis, $action, $arg = '', $args = array())
{
    // valid netcfg $action values:
    //    boot-initialise, refresh, refreshAsync, saveWifi, saveEthernet, reconnect, connect,
    //    autoconnect-on, autoconnect-off, disconnect, disconnect-delete, delete & reset
    // $arg and $args are optional, $arg contains the connman string, $args contains an array to modify a profile
    // debug
    // $redis->set('wrk_netconfig_'.$action, json_encode($args));
    $args['action'] = $action;
    if (isset($arg)) {
        $argN = trim($arg);
        if ($argN) {
            // $args has a value so use it in the array
            $args['connmanString'] = $argN;
        }
    }
    // some values are sometimes not set for Wi-Fi
    if (isset($args['ssidHex'])) {
        $args['ssidHex'] = trim($args['ssidHex']);
    } else {
        $args['ssidHex'] = '';
    }
    if (isset($args['security'])) {
        $args['security'] = trim($args['security']);
    } else {
        $args['security'] = '';
    }
    if (isset($args['ssid'])) {
        $args['ssid'] = trim($args['ssid']);
    } else {
        $args['ssid'] = '';
    }
    if (strlen($args['ssid'])) {
        // there is a ssid, so wifi
        if (!$args['ssidHex']) {
            // empty string, so calculate
            $args['ssidHex'] = trim(implode(unpack("H*", $args['ssid'])));
        }
        if (!$args['security']) {
            // empty string
            $args['security'] = 'PSK';
        }
    }
    if (isset($args['macAddress'])) {
        $args['macAddress'] = trim($args['macAddress']);
    } else {
        $args['macAddress'] = '';
    }
    // the keys in the stored profile array must contain a letter, so add an indicator
    $ssidHexKey = 'ssidHex:'.$args['ssidHex'];
    $macAddressKey = 'macAddress:'.$args['macAddress'];
    // debug
    // $redis->set('wrk_netconfig_'.$action.'_1', json_encode($args));
    // get the stored profiles
    if ($redis->exists('network_storedProfiles')) {
        $storedProfiles = json_decode($redis->get('network_storedProfiles'), true);
    } else {
        // create an empty array when the redis variable is not set
        $storedProfiles = array();
    }
    switch ($action) {
        case 'boot-initialise':
            // this is a routine which helps when setting up Wi-Fi on RuneAudio for the first time
            // the routine looks in the directory /boot/wifi for any files, all files will be processed, except:
            //      a file called readme and the directory /boot/wifi/examples and its contents
            // it steps through the files and or directories and deletes them after processing (regardless of success)
            // any file with lines containing 'Name=<value>' and 'Passphrase=<value>' will be used to set up a Wi-Fi profile
            // the optional value 'Hidden=[true]|[false]' will also be processed if present
            // multiple entries in the same file will be processed, a 'Name=<value>' starts the new network
            // the files can be added with a text editor when the Micro-SD card is plugged into a computer
            // get a list of files, ignoring the 'readme', 'examples', '.' and '..' file entries
            $profilearray = array();
            $counter = -1;
            $directory = '/boot/wifi';
            $fileNames = array_diff(scandir($directory), array('..', '.', 'readme', 'examples'));
            foreach ($fileNames as $fileName) {
                // clear the cache otherwise is_dir() returns incorrect values
                clearstatcache(true, $directory.DIRECTORY_SEPARATOR.$fileName);
                if (is_dir($directory.DIRECTORY_SEPARATOR.$fileName)) {
                    // remove unknown directories
                    sysCmd('rmdir --ignore-fail-on-non-empty \''.$directory.DIRECTORY_SEPARATOR.$fileName.'\'');
                    continue;
                }
                // load the file data into an array, ignoring empty lines and removing any <cr> or <lf>
                // $filerecords = file($directory.DIRECTORY_SEPARATOR.$fileName, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $filerecords = file($directory.DIRECTORY_SEPARATOR.$fileName);
                foreach ($filerecords as $filerecord) {
                    $recordcontent = explode('=', $filerecord, 2);
                    if (count($recordcontent) != 2) {
                        continue;
                    } else {
                        $parameter = strtolower(trim($recordcontent[0]));
                        $value = trim($recordcontent[1]);
                        if ($parameter === 'name' && $value) {
                            // a name has been found and it has a value
                            // on a new name increment the counter
                            $profilearray[++$counter]['name'] = $value;
                        } else if ($parameter === 'passphrase' && $value) {
                            // a passphrase has been found and it has a value
                            $profilearray[$counter]['passphrase'] = $value;
                        } else if ($parameter === 'hidden') {
                            // a hidden indicator has been found
                            // 1, "1", "true", "on" and "yes" are true, anything else is false
                            $profilearray[$counter]['hidden'] = filter_var(strtolower($value), FILTER_VALIDATE_BOOLEAN);
                        }
                    }
                }
                // delete the file
                sysCmd('rm \''.$directory.DIRECTORY_SEPARATOR.$fileName.'\'');
            }
            // debug
            // $redis->set('wrk_boot_wifi_filenames', json_encode($fileNames));
            // $redis->set('wrk_boot_wifi_filerecords', json_encode($filerecords));
            // $redis->set('wrk_boot_wifi_profilearray', json_encode($profilearray));
            // create the profiles
            foreach ($profilearray as $profile) {
                if (!isset($profile['name']) || !isset($profile['passphrase'])) {
                    // name and passphrase must be set
                    // invalid file content continue with the next one
                    continue;
                }
                // a valid Wi-Fi specification available
                // calculate the ssidhex value
                $ssidHex = implode(unpack("H*", trim($profile['name'])));
                $ssidHexKey = 'ssidHex:'.$ssidHex;
                if (isset($storedProfiles[$ssidHexKey])) {
                    // remove existing profile for this network
                    unset($storedProfiles[$ssidHexKey]);
                }
                // add the new values to the stored profile array
                $storedProfiles[$ssidHexKey]['technology'] = 'wifi';
                $storedProfiles[$ssidHexKey]['ssidHex'] = $ssidHex;
                $storedProfiles[$ssidHexKey]['ssid'] = $profile['name'];
                $storedProfiles[$ssidHexKey]['passphrase'] = $profile['passphrase'];
                $storedProfiles[$ssidHexKey]['ipAssignment'] = 'DHCP';
                if (isset($profile['hidden'])) {
                    if ($profile['hidden']) {
                        $storedProfiles[$ssidHexKey]['hidden'] = true;
                    } else {
                        $storedProfiles[$ssidHexKey]['hidden'] = false;
                    }
                }
                // create the config file in '/var/lib/connman/', the name is 'wifi_<ssidHex>.config'
                $profileFileName = '/var/lib/connman/wifi_'.$ssidHex.'.config';
                $profileFileContent =
                    '[global]'."\n".
                    'Description=Boot generated DHCP Wi-Fi network configuration for network (SSID) "'.$profile['name'].'", with SSID hex value "'.$ssidHex."\"\n".
                    '[service_'.$ssidHex.']'."\n".
                    'Type=wifi'."\n".
                    'SSID='.$ssidHex."\n".
                    'Passphrase='.$profile['passphrase']."\n";
                if (isset($profile['hidden'])) {
                    if ($profile['hidden']) {
                        $profileFileContent .= 'Hidden=true'."\n";
                    } else {
                        $profileFileContent .= 'Hidden=false'."\n";
                    }
                }
                // sort the profile array on ssid (case insensitive)
                $ssidCol = array_column($storedProfiles, 'ssid');
                $ssidCol = array_map('strtolower', $ssidCol);
                array_multisort($ssidCol, SORT_ASC, $storedProfiles);
                // save the profile array
                $redis->set('network_storedProfiles', json_encode($storedProfiles));
                // commit the config file, creating a new file triggers connman to use it
                $fp = fopen($profileFileName, 'w');
                fwrite($fp, $profileFileContent);
                fclose($fp);
            }
            // restore the default boot-initialise Wi-Fi files
            sysCmd('mkdir -p /boot/wifi/examples');
            sysCmd('cp /srv/http/app/config/defaults/boot/wifi/readme /boot/wifi/readme');
            sysCmd('cp /srv/http/app/config/defaults/boot/wifi/examples/* /boot/wifi/examples');
            // run refresh_nics to finish off
            wrk_netconfig($redis, 'refreshAsync');
            break;
        case 'refresh':
            // check the lock status
            $lockWifiscan = $redis->Get('lock_wifiscan');
            if ($lockWifiscan) {
                if ($lockWifiscan >= 7) {
                    // its not really a great problem if this routine runs twice at the same time
                    // but spread the attempts, so let it run on the 7th attempt
                    $redis->Set('lock_wifiscan', ++$lockWifiscan);
                } else {
                    $redis->Set('lock_wifiscan', ++$lockWifiscan);
                    break;
                }
            }
            // run the refresh nics routine and wait until it finishes
            refresh_nics($redis);
            // sysCmd('/srv/http/command/refresh_nics');
            break;
        case 'refreshAsync':
            // check the lock status
            $lockWifiscan = $redis->Get('lock_wifiscan');
            if ($lockWifiscan) {
                if ($lockWifiscan >= 7) {
                    // its not really a great problem if this routine runs twice at the same time
                    // but spread the attempts, so let it run on the 7th attempt
                    $redis->Set('lock_wifiscan', ++$lockWifiscan);
                } else {
                    $redis->Set('lock_wifiscan', ++$lockWifiscan);
                    break;
                }
            }
            // run the refresh nics routine async don't wait until it finishes
            sysCmdAsync('nice --adjustment=2 /srv/http/command/refresh_nics');
            break;
        case 'saveWifi':
            // is used to create/modify a wifi config file and stored profile
            // add a config file and stored profile
            // if the passphrase is not set, try to retrieve the passphrase from the profile
            if (isset($args['passphrase'])) {
                $args['passphrase'] = trim($args['passphrase']);
            } else {
                $args['passphrase'] = '';
            }
            if (!strlen($args['passphrase'])) {
                // passphase not set in the UI
                if (isset($storedProfiles[$ssidHexKey]['passphrase'])) {
                    // there is a passphrase in the stored profile, save it
                    $args['passphrase'] = trim($storedProfiles[$ssidHexKey]['passphrase']);
                }
            }
            // delete the current profile
            if (isset($storedProfiles[$ssidHexKey])) {
                unset($storedProfiles[$ssidHexKey]);
            }
            // set up the net profile array
            foreach ($args as $key => $value) {
                $val = trim($value);
                if (strpos('|manual|connmanString|action|reboot|', $key)) {
                    // omit some of the values
                    continue;
                }
                if (($args['ipAssignment'] === 'DHCP') && strpos('|ipv4Address|ipv4Mask|defaultGateway|primaryDns|secondaryDns|', $key)) {
                    // omit extra values if IP Assignment is DHCP
                    continue;
                }
                if (!$val) {
                    // there is no value
                    continue;
                }
                // otherwise save the UI values
                $storedProfiles[$ssidHexKey][$key] = $val;
            }
            $storedProfiles[$ssidHexKey]['technology'] = 'wifi';
            // create the config file in '/var/lib/connman/', the name is 'wifi_<ssidHex>.config'
            $profileFileName = '/var/lib/connman/wifi_'.$args['ssidHex'].'.config';
            $tmpFileName = '/tmp/wifi_'.$args['ssidHex'].'.config';
            $profileFileContent =
                '[global]'."\n".
                'Description=';
            if ($args['ipAssignment'] === 'DHCP') {
                $profileFileContent .= 'DHCP ';
            } else {
                $profileFileContent .= 'Static ';
            }
            $profileFileContent .= $args['security'].' Wi-Fi network configuration for network (SSID) "'.$args['ssid'].'", with SSID hex value "'.$args['ssidHex']."\"\n".
                '[service_'.$args['ssidHex'].']'."\n".
                'Type=wifi'."\n".
                'SSID='.$args['ssidHex']."\n";
            if (isset($args['autoconnect'])) {
                $profileFileContent .= 'Security=open'."\n";
                if ($args['autoconnect']) {
                    $profileFileContent .= 'AutoConnect=true'."\n";
                } else {
                    $profileFileContent .= 'AutoConnect=false'."\n";
                }
            } else {
                $profileFileContent .= 'Security='.strtolower($args['security'])."\n".
                    'Passphrase='.$args['passphrase']."\n";
            }
            if (isset($args['hidden']) && $args['hidden']) {
                $profileFileContent .= 'Hidden=true'."\n";
            } else {
                $profileFileContent .= 'Hidden=false'."\n";
            }
            if ($args['ipAssignment'] === 'DHCP') {
                if (isset($args['connmanString'])) {
                    $args['connmanString'] = trim($args['connmanString']);
                    if ($args['connmanString']) {
                        // make sure that connman has the correct values
                        sysCmd('connmanctl config '.$args['connmanString'].' --ipv6 auto');
                        sysCmd('connmanctl config '.$args['connmanString'].' --ipv4 dhcp');
                    }
                }
            } else {
                $profileFileContent .= 'IPv4='.$args['ipv4Address'].'/'.$args['ipv4Mask'].'/'.$args['defaultGateway']."\n".
                    'IPv6=off'."\n";
                if ($args['primaryDns'] && !$args['secondaryDns']) {
                    $profileFileContent .= 'Nameservers='.$args['primaryDns']."\n";
                } else if (!$args['primaryDns'] && $args['secondaryDns']) {
                    $profileFileContent .= 'Nameservers='.$args['secondaryDns']."\n";
                } else if ($args['primaryDns'] && $args['secondaryDns']) {
                    $profileFileContent .= 'Nameservers='.$args['secondaryDns'].','.$args['secondaryDns']."\n";
                }
            }
            // sort the profile array on ssid (case insensitive)
            $ssidCol = array_column($storedProfiles, 'ssid');
            $ssidCol = array_map('strtolower', $ssidCol);
            array_multisort($ssidCol, SORT_ASC, $storedProfiles);
            // save the profile array
            $redis->set('network_storedProfiles', json_encode($storedProfiles));
            // commit the config file, creating a new file triggers connman to use it
            $fp = fopen($tmpFileName, 'w');
            fwrite($fp, $profileFileContent);
            fclose($fp);
            // don't replace the existing connman configuration file if the new file is identical
            if (md5_file($profileFileName) != md5_file($tmpFileName)) {
                rename($tmpFileName, $profileFileName);
            } else {
                unlink($tmpFileName);
            }
            break;
        case 'saveEthernet':
            // is only used to set/remove a static IP-address
            if ($args['ipAssignment'] === 'DHCP') {
                // just delete the config file and remove the stored profile
                wrk_netconfig($redis, 'delete', '', $args);
                // make sure that connman has the correct values
                sysCmd('connmanctl config '.$args['connmanString'].' --ipv6 auto');
                sysCmd('connmanctl config '.$args['connmanString'].' --ipv4 dhcp');
            } else {
                // add a config file and stored profile
                // set up the profile array
                foreach ($args as $key => $value) {
                    if (strpos('|connmanString|', $key)) {
                        // omit some of the values
                        continue;
                    }
                    $storedProfiles[macAddressKey][$key] = $value;
                }
                $storedProfiles[macAddressKey]['technology'] = 'ethernet';
                // create the config file in '/var/lib/connman/', the name is 'ethernet_<macAddress>.config'
                $profileFileName = '/var/lib/connman/ethernet_'.$args['macAddress'].'.config';
                $tmpFileName = '/tmp/ethernet_'.$args['macAddress'].'.config';
                $macAddress = join(":", str_split($args['macAddress'], 2));
                $profileFileContent =
                    '[global]'."\n".
                    'Description=Static IP configuration for nic "'.$args['nic'].'", with MAC address "'.$macAddress."\"\n".
                    '[service_'.$args['macAddress'].']'."\n".
                    // add colons to the MAC address
                    'MAC='.$macAddress."\n".
                    'Type=ethernet'."\n".
                    'IPv4='.$args['ipv4Address'].'/'.$args['ipv4Mask'].'/'.$args['defaultGateway']."\n".
                    'IPv6=off'."\n";
                if ($args['primaryDns'] && !$args['secondaryDns']) {
                    $profileFileContent .= 'Nameservers='.$args['primaryDns']."\n";
                } else if (!$args['primaryDns'] && $args['secondaryDns']) {
                    $profileFileContent .= 'Nameservers='.$args['secondaryDns']."\n";
                } else if ($args['primaryDns'] && $args['secondaryDns']) {
                    $profileFileContent .= 'Nameservers='.$args['secondaryDns'].','.$args['secondaryDns']."\n";
                }
                // save the profile array
                $redis->set('network_storedProfiles', json_encode($storedProfiles));
                // commit the config file, creating a new file triggers connman to use it
                $fp = fopen($tmpFileName, 'w');
                fwrite($fp, $profileFileContent);
                fclose($fp);
                // don't replace the existing connman configuration file if the new file is identical
                if (md5_file($profileFileName) != md5_file($tmpFileName)) {
                    rename($tmpFileName, $profileFileName);
                } else {
                    unlink($tmpFileName);
                }
            }
            break;
        case 'reconnect':
            // no break;
        case 'connect':
            // manual connect
            sysCmd('connmanctl connect '.$args['connmanString']);
            break;
        case 'autoconnect-on':
            // manually set autoconnet on
            sysCmd('connmanctl config '.$args['connmanString'].' --autoconnect on');
            break;
        case 'autoconnect-off':
            // manually set autoconnet off
            sysCmd('connmanctl config '.$args['connmanString'].' --autoconnect off');
            break;
        case 'disconnect':
            // manual disconnect, to avoid automatic reconnection autoconnect is set off
            sysCmd('connmanctl config '.$args['connmanString'].' --autoconnect off');
            sysCmd('connmanctl disconnect '.$args['connmanString']);
            break;
        case 'disconnect-delete':
            // manual disconnect, to avoid automatic reconnection autoconnect is set off, then continues to delete
            sysCmd('connmanctl config '.$args['connmanString'].' --autoconnect off');
            sysCmd('connmanctl disconnect '.$args['connmanString']);
            // no break;
        case 'delete':
            // delete a connection, also removes the stored profile and configuration files
            // wifi
            if (isset($args['ssidHex']) && isset($storedProfiles[$ssidHexKey])) {
                unset($storedProfiles[$ssidHexKey]);
                unlink('/var/lib/connman/wifi_'.$args['ssidHex'].'.config');
                sysCmd('rmdir --ignore-fail-on-non-empty \'/var/lib/connman/wifi_*'.$args['ssidHex'].'*\'');
            }
            // ethernet
            if (isset($args['macAddress']) && isset($storedProfiles[macAddressKey])) {
                unset($storedProfiles[macAddressKey]);
                unlink('/var/lib/connman/ethernet_'.$args['macAddress'].'.config');
                sysCmd('rmdir --ignore-fail-on-non-empty \'/var/lib/connman/ethernet_'.$args['macAddress'].'*\'');
            }
            $redis->set('network_storedProfiles', json_encode($storedProfiles));
            break;
        case 'reset':
            // delete all stored profiles and configuration files and restore the  system defaults
            // automatic reboot follows
            // first disconnect all the networks
            if ($redis->exists('network_info')) {
                $networks = json_decode($redis->get('network_info'), true);
                foreach ($networks as $network) {
                    wrk_netconfig($redis, 'disconnect', $network['connmanString']);
                }
            }
            // stop connman, otherwise it may recreate the configuration files after deletion
            sysCmd('systemctl stop connman');
            // clear the network array
            $redis->set('network_info', json_encode(array()));
            // clear the stored profiles
            $redis->set('network_storedProfiles', json_encode(array()));
            // delete all connman config files
            sysCmd('rm -r /var/lib/connman/*');
            // restore the default connman configuration file
            sysCmd('mkdir -p /var/lib/connman');
            sysCmd('cp /srv/http/app/config/defaults/var/lib/connman/settings /var/lib/connman/settings');
            sysCmd('chmod 600 /var/lib/connman/settings');
            // restore the default boot-initialise Wi-Fi files
            sysCmd('mkdir -p /boot/wifi/examples');
            sysCmd('cp /srv/http/app/config/defaults/boot/wifi/readme/* /boot/wifi/readme');
            sysCmd('cp /srv/http/app/config/defaults/boot/wifi/examples /boot/wifi/examples');
            // restore the standard service and config files
            sysCmd('mkdir /etc/systemd/system/');
            sysCmd('cp /srv/http/app/config/defaults/etc/systemd/system/connman.service /etc/systemd/system/connman.service');
            sysCmd('mkdir /etc/connman/');
            sysCmd('cp /srv/http/app/config/defaults/etc/connman/* /etc/connman/');
            // start connman
            sysCmd('systemctl daemon-reload');
            sysCmd('systemctl start connman');
            // set automatic Wi-Fi optimisation
            $redis->set('network_autoOptimiseWifi', 1);
            // run refresh_nics
            wrk_netconfig($redis, 'refresh');
            // set poweroff to true
            $args['poweroff'] = true;
            // set dev mode off (setting it on is required to reset the networt configuration)
            $redis->set('dev', 0);
            break;
    }
    if (isset($args['poweroff']) && $args['poweroff']) {
        // poweroff requested
        wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'poweroff'));
    } else if (isset($args['reboot']) && $args['reboot']) {
        // reboot requested
        wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'reboot'));
    }
}

function wrk_jobID()
{
    $jobID = md5(uniqid(rand(), true));
    return $jobID;
}

function wrk_checkStrSysfile($sysfile, $searchstr)
{
    $file = stripcslashes(file_get_contents($sysfile));
    // debug
    runelog('wrk_checkStrSysfile('.$sysfile.','.$searchstr.')', $searchstr);
    if (strpos($file, $searchstr)) {
        return true;
    } else {
        return false;
    }
}

function wrk_checkMount($mpname)
{
    $check_mp = sysCmd('grep -hc "/mnt/MPD/NAS/'.$mpname.'" /proc/mounts')[0];
    if ($check_mp)) {
        return true;
    } else {
        return false;
    }
}

function wrk_cleanDistro()
{
    runelog('function CLEAN DISTRO invoked!!!','');
    sysCmd('/srv/http/command/image_reset_script.sh');
}

function wrk_playernamemenu($action)
{
    if ($action) {
        // on - player name and "Menu"
        $newline = '        <a id="menu-settings" class="dropdown-toggle" role="button" data-toggle="dropdown" data-target="#" href="#"><?=$this->hostname ?> MENU <i class="fa fa-bars dx"></i></a> <!--- playernamemenu -->';
    } else {
        // off - "Menu" (default)
        $newline = '        <a id="menu-settings" class="dropdown-toggle" role="button" data-toggle="dropdown" data-target="#" href="#">MENU <i class="fa fa-bars dx"></i></a> <!--- playernamemenu -->';
    }
    $file = '/srv/http/app/templates/header.php';
    $newArray = wrk_replaceTextLine($file, '', '<!--- playernamemenu -->', $newline);
    // Commit changes to /srv/http/app/templates/header.php
    $fp = fopen($file, 'w');
    fwrite($fp, implode("", $newArray));
    fclose($fp);
    unset($newArray);
    sysCmd('chown http.http '.$file);
    sysCmd('chmod 644 '.$file);
}

function wrk_audioOutput($redis, $action, $args = null)
{
    switch ($action) {
        case 'refresh':
            $redis->Del('acards');
            // $redis->save();
            // $acards = sysCmd("cat /proc/asound/cards | grep : | cut -d '[' -f 2 | cut -d ']' -f 1");
            // $acards = sysCmd("cat /proc/asound/cards | grep : | cut -d '[' -f 2 | cut -d ':' -f 2");
            // $acards = sysCmd("cat /proc/asound/cards | grep : | cut -b 1-3,21-");
            $acards = sysCmd('grep -h ":" /proc/asound/cards | cut -b 1-3,21-');
            $i2smodule = $redis->Get('i2smodule');
            // check if i2smodule is enabled and read card details
            if ($i2smodule !== 'none') {
                $i2smodule_details = $redis->hGet('acards_details', $i2smodule);
            }
            runelog('/proc/asound/cards', $acards);
            foreach ($acards as $card) {
                unset($sub_interfaces);
                unset($data);
                $card_index = explode(' : ', $card, 2);
                $card_index = trim($card_index[0]);
                // acards loop
                runelog('>>--------------------------- card: '.$card.' index: '.$card_index.' (start) --------------------------->>');
                $card = explode(' - ', $card, 2);
                $card = trim($card[1]);
                // $description = sysCmd("grep -h ':' /proc/asound/cards | cut -d ':' -f 2 | cut -d ' ' -f 4-20");
                // debug
                runelog('wrk_audioOutput card string: ', $card);
                $description = sysCmd("aplay -l -v | grep \"\[".$card."\]\"");
                $subdeviceid = explode(':', $description[0]);
                $subdeviceid = explode(',', trim($subdeviceid[1]));
                $subdeviceid = explode(' ', trim($subdeviceid[1]));
                $data['device'] = 'hw:'.$card_index.','.$subdeviceid[1];
                if ($i2smodule !== 'none' && isset($i2smodule_details->sysname) && $i2smodule_details->sysname === $card) {
                    $acards_details = $i2smodule_details;
                } else {
                    $acards_details = $redis->hGet('acards_details', $card);
                }
                if ($acards_details !== '') {
                    // debug
                    runelog('wrk_audioOutput: in loop: acards_details for: '.$card, $acards_details);
                    $details = new stdClass();
                    $details = json_decode($acards_details);
                    // debug
                    runelog('wrk_audioOutput: in loop: (decoded) acards_details for: '.$card, $details);
                    if (isset($details->mixer_control)) {
                        //$volsteps = sysCmd("amixer -c ".$card_index." get \"".$details->mixer_control."\" | grep Limits | cut -d ':' -f 2 | cut -d ' ' -f 4,6");
                        //$volsteps = sysCmd("amixer -c ".$card_index." get \"".$details->mixer_control."\" | grep Limits | cut -d ':' -f 2 | cut -d ' ' -f 3,5");
                        //$volsteps = explode(' ', $volsteps[0]);
                        $volsteps = sysCmd("amixer -c ".$card_index." get \"".$details->mixer_control."\" | grep -i limits");
                        $volsteps = explode('-',preg_replace('/[^0-9-]/', '', $volsteps[0]));
                        if (isset($volsteps[0])) $data['volmin'] = $volsteps[0];
                        if (isset($volsteps[1])) $data['volmax'] = $volsteps[1];
                        // $data['mixer_device'] = "hw:".$details->mixer_numid;
                        $data['mixer_device'] = "hw:".$card_index;
                        $data['mixer_control'] = $details->mixer_control;
                    }
                    if (isset($details->sysname) && $details->sysname === $card) {
                        if ($details->type === 'integrated_sub') {
                            $sub_interfaces = $redis->sMembers($card);
                            // debug
                            runelog('line 2444: (sub_interfaces loop) card: '.$card, $sub_interfaces);
                            foreach ($sub_interfaces as $sub_interface) {
                                runelog('line 2446: (sub_interfaces foreach) card: '.$card, $sub_interface);
                                $sub_int_details = new stdClass();
                                $sub_int_details = json_decode($sub_interface);
                                runelog('line 2449: (sub_interfaces foreach json_decode) card: '.$card, $sub_int_details);
                                $sub_int_details->device = $data['device'];
                                $sub_int_details->name = $card.'_'.$sub_int_details->id;
                                $sub_int_details->type = 'alsa';
                                $sub_int_details->integrated_sub = 1;
                                // prepare data for real_interface record
                                $data['name'] = $card;
                                $data['type'] = 'alsa';
                                $data['system'] = trim($description[0]);
                                // write real_interface json (use this to create the real MPD output)
                                $sub_int_details->real_interface = json_encode($data);
                                // replace index string in route command
                                if (isset($sub_int_details->route_cmd)) $sub_int_details->route_cmd = str_replace("*CARDID*", $card_index, $sub_int_details->route_cmd);
                                // debug
                                runelog('::::::sub interface record array:::::: ',$sub_int_details);
                                $redis->hSet('acards', $card.'_'.$sub_int_details->id, json_encode($sub_int_details));
                            }
                        }
                        // if ($details->extlabel !== 'none') $data['extlabel'] = $details->extlabel;
                        if (isset($details->extlabel) && $details->extlabel !== 'none') {
                            runelog('::::::acard extlabel:::::: ', $details->extlabel);
                            $data['extlabel'] = $details->extlabel;
                        }
                    }
                    // debug
                    if (isset($data['extlabel'])) runelog('wrk_audioOutput: in loop: extlabel for: '.$card, $data['extlabel']);
                    // test if there is an option for mpd.conf set
                    // for example ODROID C1 needs "card_option":"buffer_time\t\"0\""
                    if (isset($details->card_option)) {
                        $data['card_option'] = $details->card_option;
                    }
                }
                if (!isset($sub_interfaces)) {
                    $data['name'] = $card;
                    $data['type'] = 'alsa';
                    $data['system'] = trim($description[0]);
                    // debug
                    runelog('::::::acard record array::::::', $data);
                    $redis->hSet('acards', $card, json_encode($data));
                }
                // acards loop
                runelog('<<--------------------------- card: '.$card.' index: '.$card_index.' (finish) ---------------------------<<');
            }
            // $redis->save();
            break;
        case 'setdetails':
            $redis->hSet('acards_details', $args['card'], json_encode($args['details']));
            break;
    }
}

function wrk_i2smodule($redis, $args)
{
    $redis->set('i2smodule', $args);

    if($redis->get('hwplatformid') === '01' || $redis->get('hwplatformid') === '08') {
        ## RuneAudio enable HDMI & analog output
        $file = '/boot/config.txt';
        $newArray = wrk_replaceTextLine($file, '', 'dtoverlay=', 'dtoverlay='.$args, '## RuneAudio I2S-Settings', 1);
        // Commit changes to /boot/config.txt
        $fp = fopen($file, 'w');
        $return = fwrite($fp, implode("", $newArray));
        fclose($fp);
    } else {
        if (wrk_mpdPlaybackStatus($redis) === 'playing') {
            $mpd = openMpdSocket('/run/mpd/socket', 0);
            sendMpdCommand($mpd, 'kill');
            closeMpdSocket($mpd);
        }
        switch ($args) {
            case 'none':
                sysCmd('rmmod snd_soc_iqaudio_dac').usleep(300000);
                sysCmd('rmmod snd_soc_hifiberry_digi').usleep(300000);
                sysCmd('rmmod snd_soc_hifiberry_dac').usleep(300000);
                sysCmd('rmmod snd_soc_hifiberry_dacplus').usleep(300000);
                sysCmd('rmmod snd_soc_wm8804').usleep(300000);
                sysCmd('rmmod snd_soc_odroid_dac').usleep(300000);
                sysCmd('rmmod snd_soc_pcm512x').usleep(300000);
                sysCmd('rmmod snd_soc_pcm5102').usleep(300000);
                sysCmd('rmmod snd_soc_pcm5102a');
                break;
            case 'berrynos':
                sysCmd('modprobe bcm2708_dmaengine').usleep(300000);
                sysCmd('modprobe snd_soc_wm8804').usleep(300000);
                sysCmd('modprobe snd_soc_bcm2708_i2s').usleep(300000);
                sysCmd('modprobe snd_soc_pcm5102a').usleep(300000);
                sysCmd('modprobe snd_soc_hifiberry_dac');
                break;
            case 'berrynosmini':
                sysCmd('modprobe bcm2708_dmaengine').usleep(300000);
                sysCmd('modprobe snd_soc_wm8804').usleep(300000);
                sysCmd('modprobe snd_soc_bcm2708_i2s').usleep(300000);
                sysCmd('modprobe snd_soc_pcm5102a').usleep(300000);
                sysCmd('modprobe snd_soc_hifiberry_dac');
                break;
            case 'hifiberrydac':
                sysCmd('modprobe bcm2708_dmaengine').usleep(300000);
                sysCmd('modprobe snd_soc_wm8804').usleep(300000);
                sysCmd('modprobe snd_soc_bcm2708_i2s').usleep(300000);
                sysCmd('modprobe snd_soc_pcm5102a').usleep(300000);
                sysCmd('modprobe snd_soc_hifiberry_dac');
                break;
            case 'hifiberrydacplus':
                sysCmd('modprobe bcm2708_dmaengine').usleep(300000);
                sysCmd('modprobe snd_soc_wm8804').usleep(300000);
                sysCmd('modprobe snd_soc_bcm2708_i2s').usleep(300000);
                sysCmd('modprobe snd_soc_pcm512x').usleep(300000);
                sysCmd('modprobe snd_soc_hifiberry_dacplus');
                break;
            case 'hifiberrydigi':
                sysCmd('modprobe bcm2708_dmaengine').usleep(300000);
                sysCmd('modprobe snd_soc_wm8804').usleep(300000);
                sysCmd('modprobe snd_soc_bcm2708_i2s').usleep(300000);
                sysCmd('modprobe snd_soc_pcm5102a').usleep(300000);
                sysCmd('modprobe snd_soc_hifiberry_digi');
                break;
            case 'iqaudiopidac':
                sysCmd('modprobe bcm2708_dmaengine').usleep(300000);
                sysCmd('modprobe snd_soc_wm8804').usleep(300000);
                sysCmd('modprobe snd_soc_bcm2708_i2s').usleep(300000);
                sysCmd('modprobe snd_soc_pcm512x').usleep(300000);
                sysCmd('modprobe snd_soc_iqaudio_dac');
                break;
            case 'raspyplay3':
                sysCmd('modprobe bcm2708_dmaengine').usleep(300000);
                sysCmd('modprobe snd_soc_wm8804').usleep(300000);
                sysCmd('modprobe snd_soc_bcm2708_i2s').usleep(300000);
                sysCmd('modprobe snd_soc_pcm5102a').usleep(300000);
                sysCmd('modprobe snd_soc_hifiberry_dac');
                break;
            case 'raspyplay4':
                sysCmd('modprobe bcm2708_dmaengine').usleep(300000);
                sysCmd('modprobe snd_soc_wm8804').usleep(300000);
                sysCmd('modprobe snd_soc_bcm2708_i2s').usleep(300000);
                sysCmd('modprobe snd_soc_pcm512x').usleep(300000);
                sysCmd('modprobe snd_soc_iqaudio_dac');
                break;
            case 'odroidhifishield':
                sysCmd('modprobe snd_soc_odroid_dac').usleep(300000);
                sysCmd('modprobe snd_soc_pcm5102').usleep(300000);
                break;
        }
        wrk_mpdconf($redis, 'refresh');
    }
}

function wrk_audio_on_off($redis, $args)
{
    if($redis->get('hwplatformid') === '08') {
        if ($args == 1) {
            sysCmd('sed -i '."'".'s/dtparam=audio=.*/dtparam=audio=on/'."'".' /boot/config.txt');
        } else {
            sysCmd('sed -i '."'".'s/dtparam=audio=.*/dtparam=audio=off/'."'".' /boot/config.txt');
        }
        // ## RuneAudio enable HDMI & analog output
        // $file = '/boot/config.txt';
        // $newArray = wrk_replaceTextLine($file, '', 'dtparam=audio=', 'dtparam=audio='.($args == 1 ? 'on' : 'off'), '## RuneAudio HDMI & 3,5mm jack', 1);
        // // Commit changes to /boot/config.txt
        // $fp = fopen($file, 'w');
        // $return = fwrite($fp, implode("", $newArray));
        // fclose($fp);
    }
}

function wrk_kernelswitch($redis, $args)
{
    $file = '/boot/config.txt';
    $newArray = wrk_replaceTextLine($file, '', 'kernel=', 'kernel='.$args.'.img');
    // Commit changes to /boot/config.txt
    $fp = fopen($file, 'w');
    $return = fwrite($fp, implode("", $newArray));
    fclose($fp);
    $file = '/boot/config.txt';
    $newArray = wrk_replaceTextLine($file, '', 'cmdline=', 'cmdline=cmdline_'.$args.'.txt');
    // Commit changes to /boot/config.txt
    $fp = fopen($file, 'w');
    $return = fwrite($fp, implode("", $newArray));
    fclose($fp);

    if ($return) {
        $redis->set('kernel', $args);
        $redis->save();
    }
    return $return;
}

function wrk_mpdconf($redis, $action, $args = null, $jobID = null)
{
    // check if we are in "advanced mode" (manual edit mode)
    if ($action === 'reset') {
        $redis->set('mpdconf_advanced', 0);
        $mpdconf_advanced = 0;
    } else {
        $mpdconf_advanced = $redis->get('mpdconf_advanced');
    }
    // set mpd.conf file header
    $header =  "###################################\n";
    $header .= "#  Auto generated mpd.conf file   #\n";
    $header .= "# please DO NOT edit it manually! #\n";
    $header .= "#  Use RuneUI MPD config section  #\n";
    $header .= "###################################\n";
    $header .= "#\n";
    switch ($action) {
        case 'reset':
            // default MPD config
            sysCmd('/srv/http/db/redis_datastore_setup mpdreset');
            sysCmd('/srv/http/db/redis_acards_details');
            wrk_audioOutput($redis, 'refresh');
            unset($retval);
            $retval = sysCmd("mpd --version | grep -o 'Music Player Daemon.*' | cut -f4 -d' '");
            $redis->hSet('mpdconf', 'version', trim(reset($retval)));
            unset($retval);
            // if MPD has been built with SoXr support use it
            // it was introduced in v0.19 but is difficult to detect, search for soxr in the binary
            // for v0.20 and higher SoXr is reported in the --version list if it was included in the build
            if ($redis->hGet('mpdconf', 'version') >= '0.20.00') {
                // MPD version is higher than 0.20
                $count = sysCmd('mpd --version | grep -c "soxr"');
            } elseif ($redis->hGet('mpdconf', 'version') >= '0.19.00') {
                // MPD version is higher than 0.19 but lower than 0.20
                $count = sysCmd('grep -hc "soxr" /usr/bin/mpd');
            } else {
                // MPD version is lower than 0.19
                $count[0] = 0;
            }
            if ($count[0] > 0) {
                // SoXr has been built with MPD, so use it
                $redis->hSet('mpdconf', 'soxr', 'very high');
            } else {
                $redis->hDel('mpdconf', 'soxr');
            }
            unset($count);
            // set mpd zeroconfig name to hostname
            $redis->hSet('mpdconf', 'zeroconf_name', $redis->get('hostname'));
            wrk_mpdconf($redis, 'writecfg');
            break;
        case 'writecfg':
            // some MPD options are no longer valid for version 0.21.00 and later
            if ($redis->hGet('mpdconf', 'version') >= '0.21.00') {
                $redis->hExists('mpdconf', 'id3v1_encoding') && $redis->hDel('mpdconf', 'id3v1_encoding');
                $redis->hExists('mpdconf', 'buffer_before_play') && $redis->hDel('mpdconf', 'buffer_before_play');
                $redis->hExists('mpdconf', 'gapless_mp3_playback') && $redis->hDel('mpdconf', 'gapless_mp3_playback');
            }
            $mpdcfg = $redis->hGetAll('mpdconf');
            $current_out = $redis->Get('ao');
            // if (!$redis->hExists('acards', $current_out)) {
            if (($redis->hLen('acards') === 1) OR ((!$redis->hExists('acards', $current_out)) && ($redis->Get('i2smodule') === 'none'))){
                $stored_acards = $redis->hKeys('acards');
                // debug
                runelog('force audio output', $stored_acards[0]);
                // force first output available if the current interface does not exists or there is only one card available
                $redis->Set('ao', $stored_acards[0]);
                $redis->save();
            }
            // if there are no cards defined in acards, enable the built in bcm2835 cards for the following boot
            // TO DO this code needs to be moved to a place where redis acards is actually set
            if ((!$redis->exists('acards')) OR ($redis->hLen('acards') === 0)) {
                $redis->set('audio_on_off', 1);
                wrk_audio_on_off($redis, 1);
            }
            $output = null;
            // --- log settings ---
            if ($mpdcfg['log_level'] === 'none') {
                $redis->hDel('mpdconf', 'log_file');
            } else {
                $output .= "log_level\t\"".$mpdcfg['log_level']."\"\n";
                $output .= "log_file\t\"/var/log/runeaudio/mpd.log\"\n";
                $redis->hSet('mpdconf', 'log_file', '/var/log/runeaudio/mpd.log');
            }
            unset($mpdcfg['log_level']);
            unset($mpdcfg['log_file']);
            // --- state file ---
            if (!isset($mpdcfg['state_file']) || $mpdcfg['state_file'] === 'no') {
                $redis->hDel('mpdconf', 'state_file');
            } else {
                $output .= "state_file\t\"/var/lib/mpd/mpdstate\"\n";
                $redis->hSet('mpdconf', 'state_file', '/var/lib/mpd/mpdstate');
            }
            unset($mpdcfg['state_file']);
            // --- general settings ---
            foreach ($mpdcfg as $param => $value) {
                if ($param === 'version') {
                    // --- MPD version number ---
                    $output .="# MPD version number: ".$value."\n";
                    continue;
                }
                if ($param === 'audio_output_interface' OR $param === 'dsd_usb') {
                    continue;
                }
                if ($param === 'mixer_type') {
                    if ($value === 'software' OR $value === 'hardware') {
                        $redis->set('volume', 1);
                        if ($value === 'hardware') {
                            $hwmixer = 1;
                            continue;
                        }
                    } else {
                        $redis->set('volume', 0);
                    }
                }
                if ($param === 'user' && $value === 'mpd') {
                    $output .= $param." \t\"".$value."\"\n";
                    // group is not valid in MPD v0.21.00 or higher
                    if ($redis->hGet('mpdconf', 'version') < '0.21.00') {
                        $output .= "group \t\"audio\"\n";
                    }
                    continue;
                }
                if ($param === 'user' && $value === 'root') {
                    $output .= $param." \t\"".$value."\"\n";
                    // group is not valid in MPD v0.21.00 or higher
                    if ($redis->hGet('mpdconf', 'version') < '0.21.00') {
                        $output .= "group \t\"root\"\n";
                    }
                    continue;
                }
                if ($param === 'bind_to_address') {
                    $output .= "bind_to_address \"/run/mpd/socket\"\n";
                }
                if ($param === 'ffmpeg') {
                    // --- decoder plugin ---
                    $output .="decoder {\n";
                    $output .="\tplugin \t\"ffmpeg\"\n";
                    $output .="\tenabled \"".$value."\"\n";
                    $output .="}\n";
                    continue;
                }
                if ($param === 'soxr') {
                    if ($redis->get('soxrmpdonoff')) {
                        // --- soxr samplerate converter - resampler ---
                        if ($redis->hGet('mpdconf', 'version') >= '0.20.00') {
                            // MPD version is higher than 0.20
                            $output .="resampler {\n";
                            $output .="\tplugin \t\"".$param."\"\n";
                            $output .="\tquality \"".$value."\"\n";
                            $output .="}\n";
                            continue;
                        } elseif ($redis->hGet('mpdconf', 'version') >= '0.19.00') {
                            // MPD version is higher than 0.19 but lower than 0.20
                            $output .="samplerate_converter \"".$param." ".$value."\"\n";
                            continue;
                        } else {
                            // MPD version is lower than 0.19 - do nothing
                            continue;
                        }
                    }
                    continue;
                }
                if ($param === 'curl') {
                    // --- input plugin ---
                    $output .="input {\n";
                    $output .="\tplugin \t\"curl\"\n";
                        if ($redis->hget('proxy','enable') === '1') {
                            $output .="\tproxy \t\"".($redis->hget('proxy', 'host'))."\"\n";
                            if ($redis->hget('proxy','user') !== '') {
                                $output .="\tproxy_user \t\"".($redis->hget('proxy', 'user'))."\"\n";
                                $output .="\tproxy_password \t\"".($redis->hget('proxy', 'pass'))."\"\n";
                            }
                        }
                    $output .="}\n";
                    continue;
                }
                if ($param === 'webstreaming') {
                    // --- websteaming output ---
                    if ($value) {
                        // save the indicator, add the output after the normal output interfaces
                        $websteaming = $value;
                    }
                    continue;
                }
                if ($param === 'brutefir') {
                    // --- brutefir pipe output ---
                    if ($value) {
                        // save the indicator, add the output after the normal output interfaces
                        $brutefirCommand = $value;
                    }
                    continue;
                }
                if ($param === 'snapcast') {
                    // --- snapcast fifo output ---
                    if ($value) {
                        // save the indicator, add the output after the normal output interfaces
                        $snapcastPath = $value;
                    }
                    continue;
                }
                $output .= $param." \t\"".$value."\"\n";
            }
            $output = $header.$output;
            // --- audio output ---
            $acards = $redis->hGetAll('acards');
            // debug
            runelog('detected ACARDS ', $acards, __FUNCTION__);
            $ao = $redis->Get('ao');
            $sub_count = 0;
            foreach ($acards as $main_acard_name => $main_acard_details) {
                $card_decoded = new stdClass();
                $card_decoded = json_decode($main_acard_details);
                // debug
                runelog('decoded ACARD '.$card_decoded->name, $card_decoded, __FUNCTION__);
                // handle sub-interfaces
                if (isset($card_decoded->integrated_sub) && $card_decoded->integrated_sub === 1) {
                    // record UI audio output name
                    $current_card = $card_decoded->name;
                    // if ($sub_count >= 1) continue;
                    // $card_decoded = json_decode($card_decoded->real_interface);
                    runelog('current AO ---->  ', $ao, __FUNCTION__);
                    // var_dump($ao);
                    runelog('current card_name ---->  ', $card_decoded->name, __FUNCTION__);
                    // var_dump($card_decoded->name);
                    // var_dump(strpos($ao, $card_decoded->name));
                    if (strpos($ao,$card_decoded->name) === true OR strpos($ao, $card_decoded->name) === 0) $sub_interface_selected = 1;
                    // debug
                    if (isset($sub_interface_selected)) runelog('sub_card_selected ? >>>> '.$sub_interface_selected);
                    // debug
                    runelog('this is a sub_interface', __FUNCTION__);
                    $sub_interface = 1;
                    // debug
                    $sub_count++;
                    runelog('sub_count', $sub_count, __FUNCTION__);
                }
                $output .="audio_output {\n";
                // $output .="name \t\t\"".$card_decoded->name."\"\n";
                if (isset($sub_interface)) {
                    $output .="\tname \t\t\"".$card_decoded->name."\"\n";
                } else {
                    $output .="\tname \t\t\"".$main_acard_name."\"\n";
                }
                $output .="\ttype \t\t\"".$card_decoded->type."\"\n";
                $output .="\tdevice \t\t\"".$card_decoded->device."\"\n";
                if (isset($hwmixer)) {
                     if (isset($card_decoded->mixer_control)) {
                        $output .="\tmixer_control \t\"".$card_decoded->mixer_control."\"\n";
                        $output .="\tmixer_type \t\"hardware\"\n";
                        $output .="\tmixer_device \t\"".substr($card_decoded->device, 0, 4)."\"\n";
                    } else {
                        if (!isset($sub_interface)) {
                            $output .="\tmixer_control \t\"".alsa_findHwMixerControl(substr($card_decoded->device, 5, 1))."\"\n";
                        }
                    }
                    // $output .="\tmixer_index \t\"0\"\n";"\t\t  \t\"0\"\n";
                }
                // test if there is an option for mpd.conf set
                // for example ODROID C1 needs "card_option":"buffer_time\t\"0\""
                if (isset($card_decoded->card_option)) {
                    $output .= "\t".$card_decoded->card_option."\n";
                }
                if ($mpdcfg['dsd_usb'] === 'yes') $output .="\tdsd_usb \t\"yes\"\n";
                if ($mpdcfg['dsd_usb'] === 'DSDNATIVE') $output .="\tdsd_native \t\"yes\"\n\tdsd_native_type \t\"2\"\n";
                if ($mpdcfg['dsd_usb'] === 'DSDDOP') $output .="\tdsd_usb \t\"yes\"\n";
                $output .="\tauto_resample \t\"no\"\n";
                $output .="\tauto_format \t\"no\"\n";
                if ($ao === $main_acard_name) $output .="\tenabled \t\"yes\"\n";
                $output .="}\n";
                unset($sub_interface);
            // debug
            // runelog('conf output (in loop)', $output, __FUNCTION__);
            }
            // add the snapcast fifo output if requested
            if (isset($snapcastPath) && $snapcastPath) {
                $output .="audio_output {\n";
                $output .="\tname \t\t\"snapcast_fifo\"\n";
                $output .="\ttype \t\t\"fifo\"\n";
                $output .="\tpath \t\t\"".$snapcastPath."\"\n";
                $output .="\tformat \t\t\"48000:16:2\"\n";
                $output .="\tmixer_type \t\t\"software\"\n";
                $output .="\tenabled \t\t\"no\"\n";
                $output .="}\n";
            }
            // add the brutefir pipe output if requested
            if (isset($brutefirCommand) && $brutefirCommand) {
                $output .="audio_output {\n";
                $output .="\tname \t\t\"".$redis->get('hostname')."_pipe\"\n";
                $output .="\ttype \t\t\"pipe\"\n";
                // Command format examples:
                //   command     "aplay -f cd 2>/dev/null"
                // Or if you want to use AudioCompress
                //  command     "AudioCompress -m | aplay -f cd 2>/dev/null"
                // Or to send raw PCM stream through PCM:
                //  command     "nc example.org 8765"
                // Or if you want to use brutefir:
                //  command     "/usr/local/bin/brutefir -nodefault /home/brutefir/.brutefir_config"
                $output .="\tcommand \t\t\"".$brutefirCommand."\"\n";
                $output .="\tformat \t\t\"96000:24:2\"\n";
                $output .="\tenabled \t\t\"no\"\n";
                $output .="}\n";
            }
            // add the webstreaming output if requested
            if (isset($websteaming) && $websteaming) {
                $output .="audio_output {\n";
                $output .="\tname \t\t\"".$redis->get('hostname')."_stream\"\n";
                $output .="\ttype \t\t\"httpd\"\n";
                $output .="\tencoder \t\t\"flac\"\n";
                $output .="\tport \t\t\"8000\"\n";
                $output .="\tquality \t\t\"6\"\n";
                $output .="\tformat \t\t\"44100:16:2\"\n";
                $output .="\talways_on \t\t\"yes\"\n";
                $output .="\ttags \t\t\"yes\"\n";
                $output .="}\n";
            }
            // debug
            // runelog('raw mpd.conf', $output, __FUNCTION__);
            // check if mpd.conf was modified outside RuneUI (advanced mode)
            runelog('mpd.conf advanced state', $mpdconf_advanced);
            // many users need to add an extra parameters to the MPD configuration file
            // this can be specified in the file /home/your-extra-mpd.conf
            // see the example file: /var/www/app/config/defaults/your-extra-mpd.conf
            // clear the cache otherwise file_exists() returns incorrect values
            clearstatcache(true, '/home/your-extra-mpd.conf');
            if (file_exists('/home/your-extra-mpd.conf')) {
                $output .= "\n";
                $output .= "###############################################\n";
                $output .= "# Contents of /home/your-extra-mpd.conf added #\n";
                $output .= "###############################################\n";
                $output .= "\n";
                $output .= file_get_contents('/home/your-extra-mpd.conf');
            }
            // write mpd.conf file to /tmp location
            $fh = fopen('/tmp/mpd.conf', 'w');
            fwrite($fh, $output);
            fclose($fh);
            // check whether the mpd.conf file has changed
            if ($redis->get('mpdconfhash') === md5_file('/tmp/mpd.conf')) {
                // nothing has changed, set mpdconfchange off
                $redis->set('mpdconfchange', 0);
                syscmd('rm -f /tmp/mpd.conf');
            } else {
                // mpd configuration has changed, set mpdconfchange on, to indicate that MPD needs to be restarted and shairport conf needs updating
                $redis->set('mpdconfchange', 1);
                syscmd('cp /tmp/mpd.conf /etc/mpd.conf');
                syscmd('rm -f /tmp/mpd.conf');
                // update hash
                $redis->set('mpdconfhash', md5_file('/etc/mpd.conf'));
            }
            // write the changes to the Airplay (shairport-sync) configuration file
            wrk_shairport($redis, $ao);
            wrk_spotifyd($redis, $ao);
            break;
        case 'update':
            foreach ($args as $param => $value) {
                $redis->hSet('mpdconf', $param, $value);
            }
            wrk_mpdconf($redis, 'writecfg');
            break;
        case 'switchao':
            // record current interface selection
            $redis->set('ao', $args);
            $mpdout = $args;
            // get interface details
            $interface_details = $redis->hGet('acards', $args);
            $interface_details = json_decode($interface_details);
            // check for "special" sub_interfaces
            if (isset($interface_details->integrated_sub)) {
                // execute special internal route command
                sysCmd($interface_details->route_cmd);
                // TODO: improve this function
                sysCmd('amixer -c 0 set PCM unmute');
                // $mpdout = $interface_details->sysname;
            }
            wrk_mpdconf($redis, 'writecfg');
            // toggle playback state
            if (wrk_mpdPlaybackStatus($redis) === 'playing') {
                syscmd('mpc toggle');
                $recover_state = 1;
                // debug
                runelog('switchao (set recover state):', $recover_state);
            }
            // switch interface
            // debug
            runelog('switchao (switch AO):', $mpdout);
            syscmd('mpc enable only "'.$mpdout.'"');
            // restore playback state
            if (isset($recover_state)) {
                // debug
                runelog('switchao (RECOVER STATE!)');
                syscmd('mpc toggle');
            }
            // set notify label
            if (isset($interface_details->extlabel)) { $interface_label = $interface_details->extlabel; } else { $interface_label = $args; }
            // notify UI
            ui_notify_async('Audio output switched', "Current active output:\n".$interface_label, $jobID);
            break;
        case 'refresh':
            wrk_audioOutput($redis, 'refresh');
            wrk_mpdconf($redis, 'writecfg');
            if ($redis->get('mpdconfchange')) {
                // mpd.conf has changed so stop the mpd jobs
                wrk_mpdconf($redis, 'stop');
            }
            // always run start to make sure the mpd jobs are running
            // mpd will not be restarted if it was not stopped
            wrk_mpdconf($redis, 'start');
            break;
        case 'start':
            $activePlayer = $redis->get('activePlayer');
            if ($activePlayer === 'MPD') {
                // reload systemd daemon to activate any changed configuration files
                sysCmd('systemctl daemon-reload');
                $retval = sysCmd('systemctl is-active mpd');
                if ($retval[0] === 'active') {
                    // do nothing
                } else {
                    sysCmd('systemctl start mpd');
                }
                unset($retval);
                sleep(2);
                // ashuffle gets started automatically
                // restore the player status
                sysCmd('mpc volume '.$redis->get('lastmpdvolume'));
                wrk_mpdRestorePlayerStatus($redis);
                // restart mpdscribble
                if ($redis->hGet('lastfm', 'enable') === '1') {
                    sysCmd('systemctl reload-or-restart mpdscribble || systemctl start mpdscribble');
                }
                // restart upmpdcli
                if ($redis->hGet('dlna', 'enable') === '1') {
                    sysCmd('systemctl reload-or-restart upmpdcli || systemctl start upmpdcli');
                }
            }
            // set mpdconfchange off
            $redis->set('mpdconfchange', 0);
            // set process priority
            sysCmdAsync('nice --adjustment=2 /var/www/command/rune_prio nice');
            break;
        case 'stop':
            $redis->set('mpd_playback_status', wrk_mpdPlaybackStatus($redis));
            //$mpd = openMpdSocket('/run/mpd/socket', 0);
            //sendMpdCommand($mpd, 'kill');
            //closeMpdSocket($mpd);
            $retval  = sysCmd('mpc volume');
            $redis->set('lastmpdvolume', preg_replace('/[^0-9]/', '',$retval[0]));
            unset($retval);
            sysCmd('mpc stop');
            sysCmd('mpc volume 100');
            sysCmd('mpc volume');
            sysCmd('mpd --kill');
            sleep(1);
            sysCmd('systemctl stop mpd ashuffle mpdscribble upmpdcli');
            break;
        case 'restart':
            wrk_mpdconf($redis, 'stop');
            wrk_mpdconf($redis, 'start');
            break;
    }
}

function wrk_mpdPlaybackStatus($redis = null, $action = null)
{
    // sometimes MPD is still starting up
    // loop 5 times or until mpc returns a value
    $cnt = 5;
    do {
        $retval = sysCmd("mpc status | grep '^\[' | cut -d '[' -f 2 | cut -d ']' -f 1");
        if (isset($retval[0])) {
            $status = trim($retval[0]);
            unset($retval);
            $retval = sysCmd("mpc status | grep '^\[' | cut -d '#' -f 2 | cut -d '/' -f 1");
            $number = trim($retval[0]);
            unset($retval);
        } else {
            $status = '';
            $number = '';
            sleep(1);
            $cnt--;
        }
    } while (!$status && ($cnt >= 0));
    unset($retval);
    if (isset($action)) {
        switch ($action) {
            case 'record':
                return $redis->set('mpd_playback_laststate', wrk_mpdPlaybackStatus($redis));
                break;
            case 'laststate':
                $mpdlaststate = $redis->get('mpd_playback_laststate');
                if (!empty($status)) {
                    $redis->set('mpd_playback_laststate', $status);
                    $redis->set('mpd_playback_lastnumber', $number);
                } else {
                    $redis->set('mpd_playback_laststate', 'stopped');
                    $redis->set('mpd_playback_lastnumber', '');
                }
                return $mpdlaststate;
                break;
        }
    } else {
        if (!empty($status)) {
            // do nothing
        } else {
            $status = 'stopped';
            $number = '';
        }
        runelog('wrk_mpdPlaybackStatus (current state):', $status);
        runelog('wrk_mpdPlaybackStatus (current number):', $number);
        $redis->set('mpd_playback_laststate', $status);
        $redis->set('mpd_playback_lastnumber', $number);
    }
    return $status;
}

function wrk_mpdRestorePlayerStatus($redis)
{
    // disable start global random
    $redis->hSet('globalrandom', 'wait_for_play', 1);
    $mpd_playback_lastnumber = $redis->get('mpd_playback_lastnumber');
    if (wrk_mpdPlaybackStatus($redis, 'laststate') === 'playing') {
        // seems to be a bug somewhere in MPD
        // if play is requested too quickly after start it goes into pause or does nothing
        // solve by repeat play commands (no effect if already playing)
        for ($mpd_play_count = 0; $mpd_play_count < 12; $mpd_play_count++) {
            // wait before looping
            sleep(4);
            switch (wrk_mpdPlaybackStatus($redis)) {
                case 'paused':
                    // it was playing, now paused, so set to play
                    sysCmd('mpc play || mpc play');
                    break;
                case 'playing':
                    // it was playing, now playing, so do nothing and exit the loop
                    $mpd_play_count = 12;
                    break;
                default:
                    // it was playing, now not paused or playing, so start the track which was last playing
                    sysCmd('mpc play '.$mpd_playback_lastnumber.' || mpc play '.$mpd_playback_lastnumber);
                    if ($mpd_play_count == 11) {
                        // one more loop to go, so next time play the first track in the playlist, no effect if the playlist is empty
                        $mpd_playback_lastnumber = '1';
                    }
                    break;
            }
        }
    }
    // allow global random to start
    $redis->hSet('globalrandom', 'wait_for_play', 0);
}

function wrk_spotifyd($redis, $ao = null, $name = null)
{
    if (empty($name)) {
        $name = $redis->hGet('spotifyconnect', 'device_name');
    }
    $name = trim($name);
    if ($name == '') {
        $name = 'RuneAudio';
    }
    $redis->hSet('spotifyconnect', 'device_name', $name);
    if (empty($ao)) {
        $ao = $redis->get('ao');
    }
    $redis->hSet('spotifyconnect', 'ao', $ao);
    //
    $acard = json_decode($redis->hGet('acards', $ao));
    //$acard = json_decode($acard);
    runelog('wrk_spotifyd acard details      : ', $acard);
    runelog('wrk_spotifyd acard name         : ', $acard->name);
    runelog('wrk_spotifyd acard type         : ', $acard->type);
    runelog('wrk_spotifyd acard device       : ', $acard->device);
    //
    !empty($acard->device) && $redis->hSet('spotifyconnect', 'device', preg_split('/[\s,]+/', $acard->device)[0]);
    // !empty($acard->device) && $redis->hSet('spotifyconnect', 'device', 'plug'.preg_split('/[\s,]+/', $acard->device)[0]);
    // !empty($acard->device) && $redis->hSet('spotifyconnect', 'device', $acard->device);
    //!empty($acard->device) && $redis->hSet('spotifyconnect', 'device', 'plug'.$acard->device);
    //
    if (!empty($acard->mixer_control)) {
        $mixer = trim($acard->mixer_control);
        $volume_control = 'alsa';
    } else {
        $mixer = 'PCM';
        $volume_control = 'softvol';
    }
    if ($mixer === '') {
        $mixer = 'PCM';
        $volume_control = 'softvol';
    }
    if ($redis->hGet('mpdconf', 'mixer_type') != 'hardware') {
        $mixer = 'PCM';
        $volume_control = 'softvol';
    }
    runelog('wrk_spotifyd mixer: ', $mixer);
    $redis->hSet('spotifyconnect', 'mixer', $mixer);
    runelog('wrk_spotifyd volume control: ', $volume_control);
    $redis->hSet('spotifyconnect', 'volume_control', $volume_control);
    //
    $spotifyd_conf  = "############################################################\n";
    $spotifyd_conf .= "# Auto generated spotifyd.conf file\n";
    $spotifyd_conf .= "# Configuration File for Spotifyd\n";
    $spotifyd_conf .= "# A spotify playing daemon - Spotify Connect Receiver\n";
    $spotifyd_conf .= '# See: https://github.com/Spotifyd/spotifyd#configuration'."\n";
    $spotifyd_conf .= '# Also see: /var/www/app/config/defaults/spotifyd.conf'."\n";
    $spotifyd_conf .= "############################################################\n";
    $spotifyd_conf .= "#\n";
    $spotifyd_conf .= "[global]\n";
    $spotifyd_conf .= "#\n";
    $sccfg = $redis->hGetAll('spotifyconnect');
    foreach ($sccfg as $param => $value) {
        switch ($param) {
        case "username":
            // no break;
        case "password":
            // no break;
        case "backend":
            // no break;
        case "device":
            // no break;
        case "mixer":
            // no break;
        case "onevent":
            // no break;
        case "device_name":
            // no break;
        case "bitrate":
            if ($value != '') {
                $spotifyd_conf .= $param." = ".$value."\n";
            }
            break;
        case "volume_control":
            $spotifyd_conf .= "volume-control = ".$value."\n";
            break;
        case "volume_normalisation":
            if ($value == 'true') {
                $spotifyd_conf .= "volume-normalisation = ".$value."\n";
            }
            break;
        case "normalisation_pregain":
            if ($sccfg['volume_normalisation'] == 'true') {
                $spotifyd_conf .= "normalisation-pregain = ".$value."\n";
            }
            break;
        case "cache_path":
            if ($value != '') {
                $spotifyd_conf .= "# Disable the cache, it uses too much memory\n";
                $spotifyd_conf .= "# ".$param." = ".$value."\n";
            }
            break;
        default:
            break;
        }
    }
    // write spotifyd.conf file to /tmp location
    $fh = fopen('/tmp/spotifyd.conf', 'w');
    fwrite($fh, $spotifyd_conf);
    fclose($fh);
    // check whether the spotifyd.conf file has changed
    if (md5_file('/etc/spotifyd.conf') == md5_file('/tmp/spotifyd.conf')) {
        // nothing has changed
        syscmd('rm -f /tmp/spotifyd.conf');
    } else {
        // spotifyd configuration has changed
        if ($redis->get('activePlayer') === 'SpotifyConnect') {
            runelog('Stop SpotifyConnect player');
            wrk_stopPlayer($redis, 'SpotifyConnect');
        }
        syscmd('cp /tmp/spotifyd.conf /etc/spotifyd.conf');
        syscmd('rm -f /tmp/spotifyd.conf');
        // stop spotifyd
        sysCmd('pgrep -x spotifyd && systemctl stop spotifyd');
        $redis->hSet('spotifyconnect', 'track_id', '');
        $redis->hSet('spotifyconnect', 'last_track_id', '');
        $redis->hSet('spotifyconnect', 'event_time_stamp', 0);
        $redis->hSet('spotifyconnect', 'last_time_stamp', 0);
        // update systemd
        sysCmd('systemctl daemon-reload');
        if ($redis->hGet('spotifyconnect', 'enable')) {
            runelog('restart spotifyd');
            sysCmd('systemctl reload-or-restart spotifyd || systemctl start spotifyd');
            $redis->hSet('spotifyconnect', 'track_id', '');
            $redis->hSet('spotifyconnect', 'last_track_id', '');
            $redis->hSet('spotifyconnect', 'event_time_stamp', 0);
            $redis->hSet('spotifyconnect', 'last_time_stamp', 0);
        }
    }
}

function wrk_shairport($redis, $ao, $name = null)
{
    if (!isset($name)) {
        $name = trim($redis->hGet('airplay', 'name'));
    } else {
        $name = trim($name);
        if (!strlen($name)) {
            $name = trim($redis->hGet('airplay', 'name'));
        }
    }
    $redis->hSet('airplay', 'ao', $ao);
    $acard = $redis->hGet('acards', $ao);
    $acard = json_decode($acard);
    //$acard = json_decode($redis->hGet('acards', $ao), true);
    //$redis->hSet('airplay', 'acard', $acard);
    runelog('wrk_shairport acard details      : ', $acard);
    runelog('wrk_shairport acard name         : ', $acard->name);
    runelog('wrk_shairport acard type         : ', $acard->type);
    runelog('wrk_shairport acard device       : ', $acard->device);
    // shairport-sync output device is specified without a subdevice if only one subdevice exists
    // determining the number of sub devices is done by counting the number of alsa info file for the device
    // if (count(sysCmd('dir -l /proc/asound/card'.preg_split('/[\s,:]+/', $acard->device)[1].'/pcm?p/sub?/info')) > 1) {
    //if (count(sysCmd('dir -l /proc/asound/card'.preg_split('/[\s,:]+/', $acard->device)[1].'/pcm?p/sub0/info')) > 1) {
    //  $redis->hSet('airplay', 'alsa_output_device', $acard->device);
    //} else {
    //  $redis->hSet('airplay', 'alsa_output_device', preg_split('/[\s,]+/', $acard->device)[0]);
    //}
    //
    // shairport-sync output device is always specified without a subdevice! Possible that this will need extra work for USB DAC's
    $redis->hSet('airplay', 'alsa_output_device', preg_split('/[\s,]+/', $acard->device)[0]);
    //
    if (!empty($acard->mixer_device)) {
        $mixer_device = trim($acard->mixer_device);
    } else {
        $mixer_device = '';
    }
    if ($redis->hGet('mpdconf', 'mixer_type') != 'hardware') {
        $mixer_device = '';
    }
    runelog('wrk_shairport acard mixer_device : ', $mixer_device);
    $redis->hSet('airplay', 'alsa_mixer_device', $mixer_device);
    //
    if (!empty($acard->mixer_control)) {
        $mixer_control = trim($acard->mixer_control);
    } else {
        $mixer_control = 'PCM';
    }
    if ($mixer_control === '') {
        $mixer_control = 'PCM';
    }
    if ($redis->hGet('mpdconf', 'mixer_type') != 'hardware') {
        $mixer_control = 'PCM';
    }
    runelog('wrk_shairport acard mixer_control: ', $mixer_control);
    $redis->hSet('airplay', 'alsa_mixer_control', $mixer_control);
    //
    if (!empty($acard->extlabel)) {
        $extlabel = trim($acard->extlabel);
    } else {
        $extlabel = '';
    }
    runelog('wrk_shairport acard extlabel     : ', $extlabel);
    $redis->hSet('airplay', 'extlabel', $extlabel);
    //
    if ($redis->hGet('airplay', 'soxronoff')) {
        if ($redis->hGet('airplay', 'interpolation') != '') {
            $interpolation = $redis->hGet('airplay', 'interpolation');
        } else {
            $interpolation = 'soxr';
        }
    } else {
        $interpolation = '';
    }
    $redis->hSet('airplay', 'interpolation', $interpolation);
    //
    if ($redis->hGet('airplay', 'metadataonoff')) {
        if ($redis->hGet('airplay', 'metadata_enabled') != '') {
            $metadata_enabled = $redis->hGet('airplay', 'metadata_enabled');
        } else {
            $metadata_enabled = 'yes';
        }
    } else {
        $metadata_enabled = '';
    }
    $redis->hSet('airplay', 'metadata_enabled', $metadata_enabled);
    //
    if ($redis->hGet('airplay', 'artworkonoff')) {
        if ($redis->hGet('airplay', 'metadata_include_cover_art') != '') {
            $metadata_include_cover_art = $redis->hGet('airplay', 'metadata_include_cover_art');
        } else {
            $metadata_include_cover_art = 'yes';
        }
    } else {
        $metadata_include_cover_art = '';
    }
    $redis->hSet('airplay', 'metadata_include_cover_art', $metadata_include_cover_art);
    //
    // update shairport-sync.conf
    $file = '/etc/shairport-sync.conf';
    $newArray = wrk_replaceTextLine($file, '', ' general_name', 'name="'.$redis->hGet('airplay', 'name').'"; // general_name');
    $newArray = wrk_replaceTextLine('', $newArray, ' general_output_backend', 'output_backend="'.$redis->hGet('airplay', 'output_backend').'"; // general_output_backend');
    if ($interpolation === '') {
        $newArray = wrk_replaceTextLine('', $newArray, ' general_interpolation', '// interpolation="'.$interpolation.'"; // general_interpolation');
    } else {
        $newArray = wrk_replaceTextLine('', $newArray, ' general_interpolation', 'interpolation="'.$interpolation.'"; // general_interpolation');
    }
    $newArray = wrk_replaceTextLine('', $newArray, ' general_alac_decoder', 'alac_decoder="'.$redis->hGet('airplay', 'alac_decoder').'"; // general_alac_decoder');
    $newArray = wrk_replaceTextLine('', $newArray, ' run_this_before_play_begins', 'run_this_before_play_begins="'.$redis->hGet('airplay', 'run_this_before_play_begins').'"; // run_this_before_play_begins');
    $newArray = wrk_replaceTextLine('', $newArray, ' run_this_after_play_ends', 'run_this_after_play_ends="'.$redis->hGet('airplay', 'run_this_after_play_ends').'"; // run_this_after_play_ends');
    $newArray = wrk_replaceTextLine('', $newArray, ' run_this_wait_for_completion', 'wait_for_completion="'.$redis->hGet('airplay', 'run_this_wait_for_completion').'"; // run_this_wait_for_completion');
    $newArray = wrk_replaceTextLine('', $newArray, ' alsa_output_device', 'output_device="'.$redis->hGet('airplay', 'alsa_output_device').'"; // alsa_output_device');
    if ($mixer_control === 'PCM') {
        $newArray = wrk_replaceTextLine('', $newArray, ' alsa_mixer_control_name', '// mixer_control_name="'.$mixer_control.'"; // alsa_mixer_control_name');
    } else {
        $newArray = wrk_replaceTextLine('', $newArray, ' alsa_mixer_control_name', 'mixer_control_name="'.$mixer_control.'"; // alsa_mixer_control_name');
    }
    if ($mixer_device === '') {
        $newArray = wrk_replaceTextLine('', $newArray, ' alsa_mixer_device', '// mixer_device="'.$mixer_device.'"; // alsa_mixer_device');
    } else {
        $newArray = wrk_replaceTextLine('', $newArray, ' alsa_mixer_device', 'mixer_device="'.$mixer_device.'"; // alsa_mixer_device');
    }
    $newArray = wrk_replaceTextLine('', $newArray, ' alsa_output_format', 'output_format="'.$redis->hGet('airplay', 'alsa_output_format').'"; // alsa_output_format');
    $newArray = wrk_replaceTextLine('', $newArray, ' alsa_output_rate', 'output_rate='.$redis->hGet('airplay', 'alsa_output_rate').'; // alsa_output_rate');
    $newArray = wrk_replaceTextLine('', $newArray, ' pipe_pipe_name', 'name="'.$redis->hGet('airplay', 'pipe_pipe_name').'"; // pipe_pipe_name');
    if ($metadata_enabled === '') {
        $newArray = wrk_replaceTextLine('', $newArray, ' metadata_enabled', '// enabled="'.$metadata_enabled.'"; // metadata_enabled');
    } else {
        $newArray = wrk_replaceTextLine('', $newArray, ' metadata_enabled', 'enabled="'.$metadata_enabled.'"; // metadata_enabled');
    }
    if (($metadata_include_cover_art === '') OR ($metadata_enabled === '')) {
        $newArray = wrk_replaceTextLine('', $newArray, ' metadata_include_cover_art', '// include_cover_art="'.$metadata_include_cover_art.'"; // metadata_include_cover_art');
    } else {
        $newArray = wrk_replaceTextLine('', $newArray, ' metadata_include_cover_art', 'include_cover_art="'.$metadata_include_cover_art.'"; // metadata_include_cover_art');
    }
    $newArray = wrk_replaceTextLine('', $newArray, ' metadata_pipe_name', 'pipe_name="'.$redis->hGet('airplay', 'metadata_pipe_name').'"; // metadata_pipe_name');
    // Commit changes to /tmp/shairport-sync.conf
    $newfile = '/tmp/shairport-sync.conf';
    $fp = fopen($newfile, 'w');
    fwrite($fp, implode("", $newArray));
    fclose($fp);
    // check that the conf file has changed
    if (md5_file($file) === md5_file($newfile)) {
        // nothing has changed, set sssconfchange off
        $redis->set('sssconfchange', 0);
        syscmd('rm -f '.$newfile);
    } else {
        // mpd configuration has changed, set sssconfchange on
        $redis->set('sssconfchange', 1);
        syscmd('cp '.$newfile.' '.$file);
        syscmd('rm -f '.$newfile);
    }
    // libio
    $file = '/etc/libao.conf';
    $newArray = wrk_replaceTextLine($file, '', 'dev=', 'dev='.$acard->device);
    // Commit changes to /tmp/libao.conf
    $newfile = '/tmp/libao.conf';
    $fp = fopen($newfile, 'w');
    fwrite($fp, implode("", $newArray));
    fclose($fp);
    // check that the conf file has changed
    if (md5_file($file) === md5_file($newfile)) {
        // nothing has changed, set libaoconfchange off
        $redis->set('libaoconfchange', 0);
        syscmd('rm -f '.$newfile);
    } else {
        // mpd configuration has changed, set libaoconfchange on
        $redis->set('libaoconfchange', 1);
        syscmd('cp '.$newfile.' '.$file);
        syscmd('rm -f '.$newfile);
    }
    // restart only if the conf files have changed
    if (($redis->get('sssconfchange')) OR ($redis->get('libaoconfchange'))) {
        // stop rune_SSM_wrk
        if ($redis->get('activePlayer') === 'Airplay') {
            runelog('Stop Airplay player');
            wrk_stopPlayer($redis, 'Airplay');
        }
        sysCmd('systemctl stop rune_SSM_wrk shairport-sync');
        // update systemd
        sysCmd('systemctl daemon-reload');
        if ($redis->hGet('airplay', 'enable')) {
            runelog('restart shairport-sync');
            sysCmd('systemctl reload-or-restart shairport-sync || systemctl start shairport-sync');
        }
    }
    $redis->set('sssconfchange', 0);
    $redis->set('libaoconfchange', 0);
}

function wrk_sourcemount($redis, $action, $id = null, $quiet = false, $quick = false)
{
    switch ($action) {
        case 'mount':
            $mp = $redis->hGetAll('mount_'.$id);
            if ($mp['type'] === 'cifs' OR $mp['type'] === 'osx') {
                $type = 'cifs';
            } else if ($mp['type'] === 'nfs') {
                $type = 'nfs';
                // some possible UI values are not valid for nfs, so empty them
                $mp['username'] = '';
                $mp['password'] = '';
                $mp['charset'] = '';
            }
            // check that it is not already mounted
            $retval = sysCmd('grep -h "'.$mp['address'].'" /proc/mounts | grep "'.$mp['remotedir'].'" | grep "'.$type.'" | grep -c "/mnt/MPD/NAS/'.$mp['name'].'"');
            if ($retval[0]) {
                // already mounted, do nothing and return
                return 1;
            }
            unset($retval);
            // validate the mount name
            $mp['name'] = trim($mp['name']);
            if ($mp['name'] != preg_replace('/[^A-Za-z0-9-._ ]/', '', $mp['name'])) {
                // no special characters allowed in the mount name
                $mp['error'] = '"'.$mp['name'].'" Invalid Mount Name - no special characters allowed';
                if (!$quiet) {
                    ui_notifyError($type.' mount', $mp['error']);
                    sleep(3);
                }
                $redis->hMSet('mount_'.$id, $mp);
                return 0;
            }
            // clean up the address and remotedir variables: make backslashes slashes and remove leading and trailing slashes
            $mp['address'] = trim(str_replace(chr(92) , '/', $mp['address']));
            $mp['address'] = trim($mp['address'], '/');
            $mp['remotedir'] = trim(str_replace(chr(92), '/', $mp['remotedir']));
            $mp['remotedir'] = trim($mp['remotedir'], '/');
            if ($mp['address'] != preg_replace('/[^A-Za-z0-9-.]/', '', $mp['address'])) {
                // spaces or special characters are not normally valid in an IP Address
                $mp['error'] = 'Warning "'.$mp['address'].'" IP Address seems incorrect - contains space(s) and/or special character(s) - continuing';
                if (!$quiet) {
                    ui_notifyError($type.' mount', $mp['error']);
                    sleep(3);
                }
            }
            if ($mp['remotedir'] != preg_replace('|[^A-Za-z0-9-._/ ]|', '', $mp['remotedir'])) {
                // special characters are not normally valid as a remote directory name
                $mp['error'] = 'Warning "'.$mp['remotedir'].'" Remote Directory seems incorrect - contains special character(s) - continuing';
                if (!$quiet) {
                    ui_notifyError($type.' mount', $mp['error']);
                    sleep(3);
                }
            }
            if (!strlen($mp['remotedir'])) {
                // normally valid as a remote directory name should be specified
                $mp['error'] = 'Warning "'.$mp['remotedir'].'" Remote Directory seems incorrect - empty - continuing';
                if (!$quiet) {
                    ui_notifyError($type.' mount', $mp['error']);
                    sleep(3);
                }
            }
            // strip special characters, spaces, tabs, etc. (hex 00 to 20 and 7F), from the options string
            $mp['options'] = preg_replace("|[\\x00-\\x20\\x7F]|", "", $mp['options']);
            // bug fix: remove the following lines in the next version
            if (!strpos(' '.$mp['options'], ',')) {
                $mp['options'] = '';
            }
            // end bug fix
            // trim leasing and trailing whitespace from username and password
            $mp['username'] = trim($mp['username']);
            $mp['password'] = trim($mp['password']);
            // strip non numeric characters from rsize and wsize
            $mp['rsize'] = preg_replace('|[^0-9]|', '', $mp['rsize']);
            $mp['wsize'] = preg_replace('|[^0-9]|', '', $mp['wsize']);
            if ($type === 'nfs') {
                // nfs mount
                if ($mp['options'] == '') {
                    // no mount options set by the user or from previous auto mount, so set it to a value
                    $options2 = 'ro,nocto,noexec';
                } else {
                    // mount options provided so use them
                    if (!$quiet) ui_notify($type.' mount', 'Attempting to use saved/predefined mount options');
                    $options2 = $mp['options'];
                }
                // janui nfs mount string modified, old invalid options removed, no longer use nfsvers='xx' - let it auto-negotiate
                $mountstr = "mount -t nfs -o soft,retry=0,retrans=2,timeo=50,noatime,rsize=".$mp['rsize'].",wsize=".$mp['wsize'].",".$options2." \"".$mp['address'].":/".$mp['remotedir']."\" \"/mnt/MPD/NAS/".$mp['name']."\"";
                // $mountstr = "mount -t nfs -o soft,retry=0,actimeo=1,retrans=2,timeo=50,nofsc,noatime,rsize=".$mp['rsize'].",wsize=".$mp['wsize'].",".$mp['options']." \"".$mp['address'].":/".$mp['remotedir']."\" \"/mnt/MPD/NAS/".$mp['name']."\"";
                // $mountstr = "mount -t nfs -o soft,retry=1,noatime,rsize=".$mp['rsize'].",wsize=".$mp['wsize'].",".$mp['options']." \"".$mp['address'].":/".$mp['remotedir']."\" \"/mnt/MPD/NAS/".$mp['name']."\"";
            }
            if ($type === 'cifs') {
                // smb/cifs mount
                // get the MPD uid and gid
                $mpdproc = getMpdDaemonDetalis();
                if (!empty($mp['username'])) {
                    $auth = 'username='.$mp['username'].',password='.$mp['password'].',';
                } else {
                    $auth = 'guest,';
                }
                if ($mp['options'] == '') {
                    // no mount options set by the user or from previous auto mount, so set it to a value
                    $options2 = 'cache=loose,noserverino,ro,sec=ntlmssp,noexec';
                } else {
                    // mount options provided so use them
                    if (!$quiet) ui_notify($type.' mount', 'Attempting to use saved/predefined mount options');
                    $options2 = $mp['options'];
                    // clean up the mount options
                    // remove leading and trailing white-space and commas
                    $options2 = trim($options2, ", \t\n\r\0\x0B");
                    // remove all spaces before or after any comma or equals sign
                    $options2 = str_replace(', ',',',$options2);
                    $options2 = str_replace(' ,',',',$options2);
                    $options2 = str_replace('= ','=',$options2);
                    $options2 = str_replace(' =','=',$options2);
                    // if no other cache option is specified and the mount is read-only then use loose caching
                    // user defined 'cache=strict' or 'cache=none' will always retained
                    // when loose caching is specified remove it for for read/write mounts
                    if (strpos(' '.$options2, 'cache')) {
                        // cache is defined, remove loose cache if not read only
                        if ((!strpos(' ,'.$options2.',', ',ro,')) && (!strpos(' ,'.$options2.',', ',read-only,'))) {
                            // is read/write, remove the loose cache (default caching is 'cache=strict')
                            $options2 = str_replace(',cache=loose','',$options2);
                            $options2 = str_replace('cache=loose,','',$options2);
                        }
                    } else if ((strpos(' ,'.$options2.',', ',ro,')) || (strpos(' ,'.$options2.',', ',read-only,'))) {
                        // read only is defined and no cache option is specified, add loose cache
                        $options2 = 'cache=loose,'.$options2;
                    }
                }
                $mountstr = "mount -t cifs -o ".$auth.",soft,uid=".$mpdproc['uid'].",gid=".$mpdproc['gid'].",rsize=".$mp['rsize'].",wsize=".$mp['wsize'].",iocharset=".$mp['charset'].",".$options2." \"//".$mp['address']."/".$mp['remotedir']."\" \"/mnt/MPD/NAS/".$mp['name']."\"";
            }
            // create the mount point
            sysCmd("mkdir -p '/mnt/MPD/NAS/".$mp['name']."'");
            // debug
            runelog('mount string', $mountstr);
            $count = 10;
            $busy = 1;
            $unresolved = 0;
            $noaddress = 0;
            while ($busy && !$unresolved && !$noaddress && $count--) {
                usleep(100000);
                $busy = 0;
                unset($retval);
                // attempt to mount it
                $retval = sysCmd($mountstr);
                $mp['error'] = implode("\n", $retval);
                foreach ($retval as $line) {
                    $busy += substr_count($line, 'resource busy');
                    $unresolved += substr_count($line, 'could not resolve address');
                    $noaddress += substr_count($line, 'Unable to find suitable address');
                }
            }
            runelog('system response: ', implode("\n", $retval));
            if (empty($retval)) {
                // mounted OK
                $mp['error'] = '';
                // only save mount options when mounted OK
                $mp['options'] = $options2;
                $mp['type'] = $type;
                // save the mount information
                $redis->hMSet('mount_'.$id, $mp);
                if (!$quiet) {
                    ui_notify($type.' mount', '//'.$mp['address'].'/'.$mp['remotedir'].' Mounted');
                    sleep(3);
                }
                return 1;
            } else {
                unset($retval);
                $retval = sysCmd('grep -h "'.$mp['address'].'" /proc/mounts | grep "'.$mp['remotedir'].'" | grep "'.$type.'" | grep -c "/mnt/MPD/NAS/'.$mp['name'].'"');
                if ($retval[0]) {
                    // mounted OK
                    $mp['error'] = '';
                    // only save mount options when mounted OK
                    $mp['options'] = $options2;
                    $mp['type'] = $type;
                    // save the mount information
                    $redis->hMSet('mount_'.$id, $mp);
                    if (!$quiet) {
                        ui_notify($type.' mount', '//'.$mp['address'].'/'.$mp['remotedir'].' Mounted');
                        sleep(3);
                    }
                    return 1;
                }
                // mount failed
                $redis->hMSet('mount_'.$id, $mp);
            }
            unset($retval);
            if ($unresolved OR $noaddress OR $quick) {
                if (!$quiet) {
                    ui_notifyError($type.' mount', $mp['error']);
                    sleep(3);
                    ui_notifyError($type.' mount', '//'.$mp['address'].'/'.$mp['remotedir'].' Failed');
                    sleep(3);
                }
                if(!empty($mp['name'])) sysCmd("rmdir '/mnt/MPD/NAS/".$mp['name']."'");
                return 0;
            }
            if ($type === 'cifs') {
                for ($i = 1; $i <= 8; $i++) {
                    // try all valid cifs versions
                    // vers=1.0, vers=2.0, vers=2.1, vers=3.0, vers=3.02, vers=3.1.1
                    //
                    switch ($i) {
                        case 1:
                            if (!$quiet) ui_notify($type.' mount', 'Attempting automatic negotiation');
                            $options1 = 'cache=loose,noserverino,ro,noexec';
                            break;
                        case 2:
                            if (!$quiet) ui_notify($type.' mount', 'Attempting vers=3.1.1');
                            $options1 = 'cache=loose,noserverino,ro,vers=3.1.1,noexec';
                            break;
                        case 3:
                            if (!$quiet) ui_notify($type.' mount', 'Attempting vers=3.02');
                            $options1 = 'cache=loose,noserverino,ro,vers=3.02,noexec';
                            break;
                        case 4:
                            if (!$quiet) ui_notify($type.' mount', 'Attempting vers=3.0');
                            $options1 = 'cache=loose,noserverino,ro,vers=3.0,noexec';
                            break;
                        case 5:
                            if (!$quiet) ui_notify($type.' mount', 'Attempting vers=2.1');
                            $options1 = 'cache=loose,noserverino,ro,vers=2.1,noexec';
                            break;
                        case 6:
                            if (!$quiet) ui_notify($type.' mount', 'Attempting vers=2.0');
                            $options1 = 'cache=loose,noserverino,ro,vers=2.0,noexec';
                            break;
                        case 7:
                            if (!$quiet) ui_notify($type.' mount', 'Attempting vers=1.0');
                            $options1 = 'cache=loose,noserverino,ro,vers=1.0,noexec';
                            break;
                        default:
                            $i = 10;
                            break;
                    }
                    for ($j = 1; $j <= 7; $j++) {
                        switch ($j) {
                            case 1:
                                $options2 = $options1.',sec=ntlm';
                                break;
                            case 2:
                                $options2 = $options1.',sec=ntlmssp';
                                break;
                            case 3:
                                $options2 = $options1.',sec=ntlm,nounix';
                                break;
                            case 4:
                                $options2 = $options1.',sec=ntlmssp,nounix';
                                break;
                            case 5:
                                $options2 = $options1;
                                break;
                            case 6:
                                $options2 = $options1.',nounix';
                                break;
                            case 7:
                                if ($auth == 'guest,') {
                                    $auth = '';
                                }
                                $options2 = $options1.',sec=none';
                                break;
                            default:
                                $j = 10;
                                break;
                        }
                        $mountstr = "mount -t cifs -o ".$auth.",soft,uid=".$mpdproc['uid'].",gid=".$mpdproc['gid'].",rsize=".$mp['rsize'].",wsize=".$mp['wsize'].",iocharset=".$mp['charset'].",".$options2." \"//".$mp['address']."/".$mp['remotedir']."\" \"/mnt/MPD/NAS/".$mp['name']."\"";
                        // debug
                        runelog('mount string', $mountstr);
                        $count = 10;
                        $busy = 1;
                        while ($busy && $count--) {
                            usleep(100000);
                            $busy = 0;
                            unset($retval);
                            // attempt to mount it
                            $retval = sysCmd($mountstr);
                            $mp['error'] = implode("\n", $retval);
                            foreach ($retval as $line) {
                                $busy += substr_count($line, 'resource busy');
                            }
                        }
                        runelog('system response: ', implode("\n", $retval));
                        if (empty($retval)) {
                            // mounted OK
                            $mp['error'] = '';
                            // only save mount options when mounted OK
                            $mp['options'] = $options2;
                            $mp['type'] = $type;
                            // save the mount information
                            $redis->hMSet('mount_'.$id, $mp);
                            if (!$quiet) {
                                ui_notify($type.' mount', '//'.$mp['address'].'/'.$mp['remotedir'].' Mounted');
                                sleep(3);
                            }
                            return 1;
                        } else {
                            unset($retval);
                            $retval = sysCmd('grep -h "'.$mp['address'].'" /proc/self/mountinfo | grep "'.$mp['remotedir'].'" | grep "'.$type.'" | grep -c "/mnt/MPD/NAS/'.$mp['name'].'"');
                            if ($retval[0]) {
                                // mounted OK
                                $mp['error'] = '';
                                // only save mount options when mounted OK
                                $mp['options'] = $options2;
                                $mp['type'] = $type;
                                // save the mount information
                                $redis->hMSet('mount_'.$id, $mp);
                                if (!$quiet) {
                                    ui_notify($type.' mount', '//'.$mp['address'].'/'.$mp['remotedir'].' Mounted');
                                    sleep(3);
                                }
                                return 1;
                            }
                            // mount failed
                            $redis->hMSet('mount_'.$id, $mp);
                            unset($retval);
                        }
                    }
                }
            }
            // mount failed
            if(!empty($mp['name'])) sysCmd("rmdir '/mnt/MPD/NAS/".$mp['name']."'");
            if (!$quiet) {
                ui_notifyError($type.' mount', $mp['error']);
                sleep(3);
                ui_notifyError($type.' mount', '//'.$mp['address'].'/'.$mp['remotedir'].' Failed');
                sleep(3);
            }
            return 0;
            break;
        case 'mountall':
            $test = 1;
            $mounts = $redis->keys('mount_*');
            if (!empty($mounts)) {
                // $mounts is set and has values
                foreach ($mounts as $key) {
                    if ($key != '') {
                        $mp = $redis->hGetAll($key);
                        if (!wrk_checkMount($mp['name'])) {
                            // parameters: wrk_sourcemount($redis, $action, $id = null, $quiet = false, $quick = false)
                            if (wrk_sourcemount($redis, 'mount', $mp['id'], $quiet, $quick) === 0) {
                                $test = 0;
                            }
                        }
                    }
                }
            }
            $return = $test;
            break;
    }
    return $return;
}

function wrk_sourcecfg($redis, $action, $args=null)
{
    runelog('function wrk_sourcecfg('.$action.')', $args);
    switch ($action) {
        case 'add':
            // unset($args->id);
            $args->id = $redis->incr('mountidx');
            $args = (array) $args;
            $redis->hMset('mount_'.$args['id'], $args);
            $return = wrk_sourcemount($redis, 'mount', $args['id']);
            break;
        case 'edit':
            $mp = $redis->hGetAll('mount_'.$args->id);
            $args = (array) $args;
            // check if the mount type has changed, saved options need to be cleared, assume that they won't be valid
            if ($mp['type'] != $args['type']) {
                $args['options'] = '';
            }
            $redis->hMset('mount_'.$args['id'], $args);
            sysCmd('mpc stop');
            usleep(500000);
            sysCmd("umount -f '/mnt/MPD/NAS/".$mp['name']."'");
            if ($mp['name'] != $args['name']) {
                sysCmd("rmdir '/mnt/MPD/NAS/".$mp['name']."'");
                sysCmd("mkdir '/mnt/MPD/NAS/".$args['name']."'");
            }
            $return = wrk_sourcemount($redis, 'mount', $args['id']);
            runelog('wrk_sourcecfg(edit) exit status', $return);
            break;
        case 'delete':
            $mp = $redis->hGetAll('mount_'.$args->id);
            sysCmd('mpc stop');
            usleep(500000);
            sysCmd("umount -f '/mnt/MPD/NAS/".$mp['name']."'");
            sleep(3);
            if (!empty($mp['name'])) sysCmd("rmdir '/mnt/MPD/NAS/".$mp['name']."'");
            $return = $redis->del('mount_'.$args->id);
            break;
        case 'reset':
            sysCmd('mpc stop');
            sysCmd('systemctl stop mpd ashuffle');
            usleep(500000);
            $source = $redis->keys('mount_*');
            foreach ($source as $key) {
                $mp = $redis->hGetAll($key);
                runelog('wrk_sourcecfg() umount loop $mp[name]',$mp['name']);
                sysCmd("umount -f '/mnt/MPD/NAS/".$mp['name']."'");
                sysCmd("rmdir '/mnt/MPD/NAS/".$mp['name']."'");
                $return = $redis->del($key);
            }
            // reset mount index
            if ($return) $redis->del('mountidx');
            sysCmd('systemctl start mpd');
            // ashuffle gets started automatically
            // set process priority
            sysCmdAsync('nice --adjustment=2 /var/www/command/rune_prio nice');
            break;
        case 'umountall':
            sysCmd('mpc stop');
            sysCmd('systemctl stop mpd ashuffle');
            usleep(500000);
            $source = $redis->keys('mount_*');
            foreach ($source as $key) {
                $mp = $redis->hGetAll($key);
                runelog('wrk_sourcecfg() umount loop $mp[name]',$mp['name']);
                sysCmd("umount -f '/mnt/MPD/NAS/".$mp['name']."'");
                sysCmd("rmdir '/mnt/MPD/NAS/".$mp['name']."'");
            }
            sysCmd('systemctl start mpd');
            // ashuffle gets started automatically
            // set process priority
            sysCmdAsync('nice --adjustment=2 /var/www/command/rune_prio nice');
            break;
        case 'mountall':
            // Note: wrk_sourcemount() will not do anything for existing mounts
            // parameters: wrk_sourcemount($redis, $action, $id = null, $quiet = false, $quick = false)
            $return = wrk_sourcemount($redis, 'mountall');
            break;
        case 'remountall':
            // remove all mounts first
            wrk_sourcecfg($redis, 'umountall');
            // then mount them all again
            // parameters: wrk_sourcemount($redis, $action, $id = null, $quiet = false, $quick = false)
            wrk_sourcecfg($redis, 'mountall');
            break;
        case 'umountusb':
            $return = sysCmd('udevil umount '.$args);
            // for some unknown reason usb devices sometimes get mounted twice check that is is dismounted, if not run again
            if (wrk_checkMount($args)) {
                sysCmd('udevil umount '.$args);
            }
            // clean up any invalid mount points
            sysCmd('udevil clean');
            break;
    }
    return $return;
}

function wrk_getHwPlatform($redis)
{
    $file = '/proc/cpuinfo';
    $fileData = file($file);
    foreach($fileData as $line) {
        if (substr($line, 0, 8) == 'Revision') {
            $revision = trim(substr($line, 11, 50));
            // debug
            runelog('wrk_getHwPlatform() /proc/cpuinfo revision', $revision);
        }

        if (substr($line, 0, 8) == 'Hardware') {
            $hardware = trim(substr($line, 11, 50));
            // debug
            runelog('wrk_getHwPlatform() /proc/cpuinfo hardware', $hardware);
        }
    }

    switch($hardware) {
        // RaspberryPi
        case 'BCM2708':
        case 'BCM2709':
        case 'BCM2835':
        case 'BCM2836':
        case 'BCM2837':
            if (intval("0x".$revision, 16) < 16) {
                // RaspberryPi1
                $arch = '08';
                // old single processor models no on-board Wi-Fi or Bluetooth
                $redis->exists('soxrmpdonoff') || $redis->set('soxrmpdonoff', 0);
                $redis->exists('bluetooth_on') || $redis->set('bluetooth_on', 0);
                $redis->hExists('airplay', 'soxronoff') || $redis->hSet('airplay', 'soxronoff', 0);
                $redis->hExists('airplay', 'metadataonoff') || $redis->hSet('airplay', 'metadataonoff', 0);
                $redis->hExists('airplay', 'artworkonoff') || $redis->hSet('airplay', 'artworkonoff', 0);
                $redis->hExists('airplay', 'enable') || $redis->hSet('airplay', 'enable', 0);
                $redis->hExists('airplay', 'metadata_enabled') || $redis->hSet('airplay', 'metadata_enabled', 'no');
                $redis->hExists('spotifyconnect', 'metadata_enabled') || $redis->hSet('spotifyconnect', 'metadata_enabled', 0);
                $redis->hExists('AccessPoint', 'enable') || $redis->hSet('AccessPoint', 'enable', 0);
            }
            else {
                $model = trim(substr($revision, -3, 2));
                switch($model) {
                    case "00":
                        // 00 = PiA or PiB
                        $arch = '08';
                        // single processor models no on-board Wi-Fi or Bluetooth
                        $redis->exists('soxrmpdonoff') || $redis->set('soxrmpdonoff', 0);
                        $redis->exists('bluetooth_on') || $redis->set('bluetooth_on', 0);
                        $redis->hExists('airplay', 'soxronoff') || $redis->hSet('airplay', 'soxronoff', 0);
                        $redis->hExists('airplay', 'metadataonoff') || $redis->hSet('airplay', 'metadataonoff', 0);
                        $redis->hExists('airplay', 'artworkonoff') || $redis->hSet('airplay', 'artworkonoff', 0);
                        $redis->hExists('airplay', 'enable') || $redis->hSet('airplay', 'enable', 0);
                        $redis->hExists('airplay', 'metadata_enabled') || $redis->hSet('airplay', 'metadata_enabled', 'no');
                        $redis->hExists('spotifyconnect', 'metadata_enabled') || $redis->hSet('spotifyconnect', 'metadata_enabled', 0);
                        $redis->hExists('AccessPoint', 'enable') || $redis->hSet('AccessPoint', 'enable', 0);
                        break;
                    case "01":
                        // 01 = PiB+, PiA+ or PiCompute module 1
                        // no break;
                    case "02":
                        // 02 = PiA+,
                        // no break;
                    case "03":
                        // 03 = PiB+,
                        // no break;
                    case "04":
                        // 04 = Pi2B,
                        // no break;
                    case "06":
                        // 06 = PiCompute Module
                        // no break;
                    case "09":
                        // 09 = PiZero,
                        // no break;
                    case "0a":
                        // 0a = PiCompute Module 3
                        // no break;
                    case "0A":
                        // 0A = PiCompute Module 3
                        // no break;
                    case "10":
                        // 10 = PiCompute Module 3+
                        $arch = '08';
                        // single and multi processor models no on-board Wi-Fi or Bluetooth
                        $redis->exists('soxrmpdonoff') || $redis->set('soxrmpdonoff', 1);
                        $redis->exists('bluetooth_on') || $redis->set('bluetooth_on', 0);
                        $redis->hExists('airplay', 'soxronoff') || $redis->hSet('airplay', 'soxronoff', 1);
                        $redis->hExists('airplay', 'metadataonoff') || $redis->hSet('airplay', 'metadataonoff', 1);
                        $redis->hExists('airplay', 'artworkonoff') || $redis->hSet('airplay', 'artworkonoff', 1);
                        $redis->hExists('airplay', 'enable') || $redis->hSet('airplay', 'enable', 1);
                        $redis->hExists('airplay', 'metadata_enabled') || $redis->hSet('airplay', 'metadata_enabled', 'yes');
                        $redis->hExists('spotifyconnect', 'metadata_enabled') || $redis->hSet('spotifyconnect', 'metadata_enabled', 1);
                        $redis->hExists('AccessPoint', 'enable') || $redis->hSet('AccessPoint', 'enable', 0);
                        break;
                    case "08":
                        // 08 = Pi3B,
                        // no break;
                    case "0c":
                        // 0c = PiZero W
                        // no break;
                    case "0C":
                        // 0C = PiZero W
                        // no break;
                    case "0d":
                        // 0d = Pi3B+
                        // no break;
                    case "0D":
                        // 0D = Pi3B+
                        // no break;
                    case "0e":
                        // 0d = Pi3A+
                        // no break;
                    case "0E":
                        // 0D = Pi3A+
                        // no break;
                    case "11":
                        // 0d = Pi4B+
                        // no break;
                        $arch = '08';
                        // single and multi processor models with on-board Wi-Fi or Bluetooth
                        $redis->exists('soxrmpdonoff') || $redis->set('soxrmpdonoff', 1);
                        $redis->exists('bluetooth_on') || $redis->set('bluetooth_on', 1);
                        $redis->hExists('airplay', 'soxronoff') || $redis->hSet('airplay', 'soxronoff', 1);
                        $redis->hExists('airplay', 'metadataonoff') || $redis->hSet('airplay', 'metadataonoff', 1);
                        $redis->hExists('airplay', 'artworkonoff') || $redis->hSet('airplay', 'artworkonoff', 1);
                        $redis->hExists('airplay', 'enable') || $redis->hSet('airplay', 'enable', 1);
                        $redis->hExists('airplay', 'metadata_enabled') || $redis->hSet('airplay', 'metadata_enabled', 'yes');
                        $redis->hExists('spotifyconnect', 'metadata_enabled') || $redis->hSet('spotifyconnect', 'metadata_enabled', 1);
                        $redis->hExists('AccessPoint', 'enable') || $redis->hSet('AccessPoint', 'enable', 1);
                        break;
                    case "05":
                        // 05 = PiAlpha prototype,
                        // no break;
                    case "07":
                        // 07 = unknown,
                        // no break;
                    case "0b":
                        // 0b = unknown,
                        // no break;
                    case "0B":
                        // 0B = unknown,
                        // no break;
                    case "0f":
                        // 0f = internal use only,
                        // no break;
                    case "0F":
                        // 0F = internal use only,
                        // no break;
                    default:
                        $arch = '--';
                        $redis->exists('soxrmpdonoff') || $redis->set('soxrmpdonoff', 0);
                        $redis->exists('bluetooth_on') || $redis->set('bluetooth_on', 0);
                        $redis->hExists('airplay', 'soxronoff') || $redis->hSet('airplay', 'soxronoff', 0);
                        $redis->hExists('airplay', 'metadataonoff') || $redis->hSet('airplay', 'metadataonoff', 0);
                        $redis->hExists('airplay', 'artworkonoff') || $redis->hSet('airplay', 'artworkonoff', 0);
                        $redis->hExists('airplay', 'enable') || $redis->hSet('airplay', 'enable', 0);
                        $redis->hExists('airplay', 'metadata_enabled') || $redis->hSet('airplay', 'metadata_enabled', 'no');
                        $redis->hExists('spotifyconnect', 'metadata_enabled') || $redis->hSet('spotifyconnect', 'metadata_enabled', 0);
                        $redis->hExists('AccessPoint', 'enable') || $redis->hSet('AccessPoint', 'enable', 0);
                        break;
                }
            }
            break;

        // UDOO
        case 'SECO i.Mx6 UDOO Board':
            $arch = '02';
            break;

        // CuBox
        case 'Marvell Dove (Flattened Device Tree)':
        case 'SolidRun CuBox':
            $arch = '03';
            break;

        // BeagleBone Black
        case 'Generic AM33XX (Flattened Device Tree)':
            $arch = '04';
            break;

        // Utilite Standard
        case 'Compulab CM-FX6':
            $arch = '05';
            break;

        // Cubietruck
        case 'sun7i':
            $arch = '06';
            break;

        // Cubox-i
        case 'Freescale i.MX6 Quad/DualLite (Device Tree)':
            $arch = '07';
            break;

        // ODROID C1
        case 'ODROIDC':
            $arch = '09';
            break;

        // ODROID C2
        case 'ODROID-C2':
            $arch = '10';
            break;

        default:
            $arch = '--';
            break;
    }

    $arch = '08';
    if (!isset($arch)) {
        $arch = '--';
    }

    if (!isset($arch)) {
        $arch = '--';
    }
    return $arch;
}

function wrk_setHwPlatform($redis)
{
    $arch = wrk_getHwPlatform($redis);
    runelog('arch= ', $arch);
    $playerid = wrk_playerID($arch);
    $redis->set('playerid', $playerid);
    runelog('playerid= ', $playerid);
    // register platform into database
    switch($arch) {
        case '01':
            $redis->set('hwplatform', 'RaspberryPi');
            $redis->set('hwplatformid', $arch);
            break;
        case '02':
            $redis->set('hwplatform', 'UDOO');
            $redis->set('hwplatformid',$arch);
            break;
        case '03':
            $redis->set('hwplatform', 'CuBox');
            $redis->set('hwplatformid',$arch);
            break;
        case '04':
            $redis->set('hwplatform', 'BeagleBone Black');
            $redis->set('hwplatformid', $arch);
            break;
        case '05':
            $redis->set('hwplatform', 'Utilite Standard');
            $redis->set('hwplatformid', $arch);
            break;
        case '06':
            $redis->set('hwplatform', 'Cubietruck');
            $redis->set('hwplatformid', $arch);
            break;
        case '08':
            $redis->set('hwplatform', 'RaspberryPi');
            $redis->set('hwplatformid', $arch);
            break;
        case '09':
            $redis->set('hwplatform', 'ODROID-C1');
            $redis->set('hwplatformid', $arch);
            break;
        case '10':
            $redis->set('hwplatform', 'ODROID-C2');
            $redis->set('hwplatformid', $arch);
            break;
        default:
            $redis->set('hwplatform', 'unknown');
            $redis->set('hwplatformid', $arch);
    }
}

// this can be removed in next version, because it's replaced by wrk_startPlayback($redis, $newplayer) and wrk_stopPlayback($redis, $oldplayer)
// function wrk_togglePlayback($redis, $activePlayer)
// {
// $stoppedPlayer = $redis->get('stoppedPlayer');
// // debug
// runelog('stoppedPlayer = ', $stoppedPlayer);
// runelog('activePlayer = ', $activePlayer);
    // if ($stoppedPlayer !== '') {
        // if ($stoppedPlayer === 'MPD') {
            // // connect to MPD daemon
            // $sock = openMpdSocket('/run/mpd.sock', 0);
            // $status = _parseStatusResponse($redis, MpdStatus($sock));
            // runelog('MPD status', $status);
            // if ($status['state'] === 'pause') {
                // $redis->set('stoppedPlayer', '');
            // }
            // sendMpdCommand($sock, 'pause');
            // closeMpdSocket($sock);
            // // debug
            // runelog('sendMpdCommand', 'pause');
        // } elseif ($stoppedPlayer === 'Spotify') {
            // // connect to SPOPD daemon
            // $sock = openSpopSocket('localhost', 6602, 1);
            // $status = _parseSpopStatusResponse(SpopStatus($sock));
            // runelog('SPOP status', $status);
            // if ($status['state'] === 'pause') {
                // $redis->set('stoppedPlayer', '');
            // }
            // sendSpopCommand($sock, 'toggle');
            // closeSpopSocket($sock);
            // // debug
            // runelog('sendSpopCommand', 'toggle');
        // }
        // $redis->set('activePlayer', $stoppedPlayer);
    // } else {
        // $redis->set('stoppedPlayer', $activePlayer);
        // wrk_togglePlayback($redis, $activePlayer);
    // }
// runelog('endFunction!!!', $stoppedPlayer);
// }

function wrk_startPlayer($redis, $newplayer)
{
    $activePlayer = $redis->get('activePlayer');
    if ($activePlayer === '') {
        // it should always be set, but default to MPD when nothing specified
        $activePlayer = 'MPD';
    }
    if ($activePlayer != $newplayer) {
        if ($activePlayer === 'MPD') {
            $redis->set('stoppedPlayer', $activePlayer);
            // record  the mpd status
            wrk_mpdPlaybackStatus($redis);
            // connect to MPD daemon
            $sock = openMpdSocket('/run/mpd.sock', 0);
            $status = _parseStatusResponse($redis, MpdStatus($sock));
            runelog('MPD status', $status);
            if ($status['state'] === 'play') {
                // pause playback
                sendMpdCommand($sock, 'pause');
                // debug
                runelog('sendMpdCommand', 'pause');
            }
            // set the new player
            $redis->set('activePlayer', $newplayer);
            // to get MPD out of its idle-loop we discribe to a channel
            sendMpdCommand($sock, 'subscribe '.$newplayer);
            sendMpdCommand($sock, 'unsubscribe '.$newplayer);
            closeMpdSocket($sock);
            if ($newplayer == 'Spotify') {
                $retval = sysCmd('systemctl is-active spopd');
                if ($retval[0] === 'active') {
                    // do nothing
                } else {
                    sysCmd('systemctl start spopd');
                    usleep(500000);
                }
                if ($redis->hGet('lastfm','enable')) sysCmd('systemctl stop mpdscribble');
                if ($redis->hGet('dlna','enable')) sysCmd('systemctl stop upmpdcli');
                sysCmd('systemctl stop mpd');
                sysCmd('systemctl stop ashuffle');
                $redis->set('mpd_playback_status', 'stop');
                // set process priority
                sysCmdAsync('rune_prio nice');
            }
        } elseif ($activePlayer === 'Spotify') {
            $redis->set('stoppedPlayer', $activePlayer);
            // connect to SPOPD daemon
            $sock = openSpopSocket('localhost', 6602, 1);
            $status = _parseSpopStatusResponse(SpopStatus($sock));
            runelog('SPOP status', $status);
            if ($status['state'] === 'play') {
                sendSpopCommand($sock, 'toggle');
                // debug
                runelog('sendSpopCommand', 'toggle');
            }
            // set the new player
            $redis->set('activePlayer', $newplayer);
            // to get SPOP out of its idle-loop
            sendSpopCommand($sock, 'notify');
            closeSpopSocket($sock);
            if ($newplayer == 'MPD') {
                $retval = sysCmd('systemctl is-active mpd');
                if ($retval[0] === 'active') {
                    // do nothing
                } else {
                    sysCmd('systemctl start mpd');
                    usleep(500000);
                }
                unset($retval);
                // ashuffle gets started automatically
                if ($redis->hGet('lastfm','enable')) sysCmd('pgrep -x mpdscribble || systemctl start mpdscribble');
                if ($redis->hGet('dlna','enable')) sysCmd('pgrep -x upmpdcli || systemctl start upmpdcli');
                sysCmd('systemctl stop spopd');
                // set process priority
                sysCmdAsync('rune_prio nice');
            }
        } elseif ($activePlayer === 'Airplay') {
            // cant switch back to Airplay so don't set stoppedPlayer
            // stop the Airplay metadata worker
            $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'airplaymetadata', 'action' => 'stop'));
            waitSyWrk($redis, $jobID);
            // set the new player
            $redis->set('activePlayer', $newplayer);
            if ($newplayer === 'SpotifyConnect') {
                // this will disconnect an exiting Airplay stream
                // do it only when connecting to another stream
                sysCmd('systemctl restart shairport-sync');
            }
        } elseif ($activePlayer === 'SpotifyConnect') {
            // cant switch back to SpotifyConnect so don't set stoppedPlayer
            // no metadata worker for SpotifyConnect
            // set the new player
            $redis->set('activePlayer', $newplayer);
            // if ($newplayer === 'Airplay') {
                // this will disconnect an exiting SpotifyConnect stream
                // do it only when connecting to another stream
                sysCmd('systemctl restart spotifyd');
                $redis->hSet('spotifyconnect', 'track_id', '');
                $redis->hSet('spotifyconnect', 'last_track_id', '');
                $redis->hSet('spotifyconnect', 'event_time_stamp', 0);
                $redis->hSet('spotifyconnect', 'last_time_stamp', 0);
            // }
            sysCmd('rm /srv/http/tmp/spotify-connect/spotify-connect-cover.*');
            ui_render('playback', "{\"currentartist\":\"Spotify Connect\",\"currentsong\":\"Switching\",\"currentalbum\":\"-----\",\"artwork\":\"\",\"genre\":\"\",\"comment\":\"\"}");
            sysCmd('curl -s -X GET http://localhost/command/?cmd=renderui');
        }
    }
    if ($newplayer == 'MPD') {
        wrk_mpdRestorePlayerStatus($redis);
    }
    sysCmd('curl -s -X GET http://localhost/command/?cmd=renderui');
}

function wrk_stopPlayer($redis, $activePlayer=null)
{
    runelog('wrk_stopPlayer active player', $activePlayer);
    if (is_null($activePlayer)) {
        $activePlayer = $redis->get('activePlayer');
    }
    if ($redis->get('activePlayer') != $activePlayer) {
        runelog('wrk_stopPlayer player already stopped');
    } else {
        runelog('wrk_stopPlayer active player', $activePlayer);
        if (($activePlayer == 'Airplay') || ($activePlayer == 'SpotifyConnect')) {
            // we previously stopped playback of one player to use the Stream
            $stoppedPlayer = $redis->get('stoppedPlayer');
            runelog('wrk_stopPlayer stoppedPlayer = ', $stoppedPlayer);
            // if ($activePlayer == 'Airplay') {
                // sysCmd('systemctl restart shairport-sync');
            // }
            if ($activePlayer == 'SpotifyConnect') {
                runelog('wrk_stopPlayer restart spotifyd');
                sysCmd('systemctl restart spotifyd');
                $redis->hSet('spotifyconnect', 'track_id', '');
                $redis->hSet('spotifyconnect', 'last_track_id', '');
                $redis->hSet('spotifyconnect', 'event_time_stamp', 0);
                $redis->hSet('spotifyconnect', 'last_time_stamp', 0);
            }
            if ($stoppedPlayer === '') {
                // if no stopped player is specified use MPD as default
                $stoppedPlayer = 'MPD';
            }
            runelog('wrk_stopPlayer stoppedPlayer = ', $stoppedPlayer);
            if ($stoppedPlayer === 'MPD') {
                $retval = sysCmd('systemctl is-active mpd');
                if ($retval[0] === 'active') {
                    // do nothing
                } else {
                    sysCmd('systemctl start mpd');
                    usleep(500000);
                }
                unset($retval);
                // ashuffle gets started automatically
                if ($redis->hGet('lastfm','enable')) sysCmd('pgrep -x mpdscribble || systemctl start mpdscribble');
                if ($redis->hGet('dlna','enable')) sysCmd('pgrep -x upmpdcli || systemctl start upmpdcli');
                sysCmd('systemctl stop spopd');
                // set process priority
                sysCmdAsync('rune_prio nice');
                // set the active player back to the one we stopped
                $redis->set('activePlayer', $stoppedPlayer);
                // connect to MPD daemon
                $sock = openMpdSocket('/run/mpd.sock', 0);
                $status = _parseStatusResponse($redis, MpdStatus($sock));
                runelog('MPD status', $status);
                if ($status['state'] === 'pause') {
                    // clear the stopped player if we left MPD paused
                    $redis->set('stoppedPlayer', '');
                }
                // to get MPD out of its idle-loop we discribe to a channel
                sendMpdCommand($sock, 'subscribe '.$activePlayer);
                sendMpdCommand($sock, 'unsubscribe '.$activePlayer);
                closeMpdSocket($sock);
                // continue playing mpd where it stopped when the stream started
                wrk_mpdRestorePlayerStatus($redis);
            } elseif ($stoppedPlayer === 'Spotify') {
                $retval = sysCmd('systemctl is-active spopd');
                if ($retval[0] === 'active') {
                    // do nothing
                } else {
                    sysCmd('systemctl start spopd');
                    usleep(500000);
                }
                unset($retval);
                if ($redis->hGet('lastfm','enable')) sysCmd('systemctl stop mpdscribble');
                if ($redis->hGet('dlna','enable')) sysCmd('systemctl stop upmpdcli');
                sysCmd('systemctl stop mpd');
                sysCmd('systemctl stop ashuffle');
                $redis->set('mpd_playback_status', 'stop');
                // set process priority
                sysCmdAsync('rune_prio nice');
                // connect to SPOPD daemon
                $sock = openSpopSocket('localhost', 6602, 1);
                $status = _parseSpopStatusResponse(SpopStatus($sock));
                runelog('SPOP status', $status);
                if ($status['state'] === 'pause') {
                    // clear the stopped player if we left SPOP paused
                    $redis->set('stoppedPlayer', '');
                }
                // to get SPOP out of its idle-loop
                sendSpopCommand($sock, 'notify');
                //sendSpopCommand($sock, 'toggle');
                closeSpopSocket($sock);
                // set the active player back to the one we stopped
                $redis->set('activePlayer', $stoppedPlayer);
                //delete all files in shairport folder except "now_playing"
                $dir = '/var/run/shairport/';
                $leave_files = array('now_playing');
                foreach( glob("$dir/*") as $file ) {
                    if( !in_array(basename($file), $leave_files) ) {
                        unlink($file);
                    }
                }
            }
            runelog('endFunction!!!', $stoppedPlayer);
            sysCmd('curl -s -X GET http://localhost/command/?cmd=renderui');
        }
    }
}

function wrk_SpotifyConnectMetadata($redis, $event, $track_id)
{
    runelog('wrk_SpotifyConnectMetadata event   :', $event);
    runelog('wrk_SpotifyConnectMetadata track ID:', $track_id);
    switch($event) {
        case 'start':
            // no break;
        case 'change':
            // no break;
        case 'stop':
            // run asynchronous metadata script
            sysCmdAsync('nice --adjustment=2 /var/www/command/spotify_connect_metadata_async.php '.$event.' '.$track_id);
            break;
        default:
            runelog('wrk_SpotifyConnectMetadata error:', 'Unknown event');
            break;
    }
}

function wrk_startUpmpdcli($redis)
{
    // TO-DO
    // If active player is Airplay toggle to MPD
    // If active player is Spotify switch to MPD
    // pause current track
    // save current track in playlist
    // save MPD state
    // save current playlist in memory
    // Set redis Upmpdcli running (this stops ashuffle from restarting)
    // Stop ashuffle
}

function wrk_stopUpmpdcli($redis)
{
    // TO-DO
    // if the saved playlist is still available
    // delete current MPD playlist
    // restore MPD playlist
    // restore MPD state
    // play saved playlist track
    // endif
    // unset redis Upmpdcli running (this allows ashuffle to restart)
}

function wrk_pausedUpmpdcli($redis)
{
    // TO-DO
    // start the <Upmpdcli timer> for <Upmpdcli timeout time> minutes
    // if the <Upmpdcli timer> times out
    // restart Upmpdcli.service
    // call wrk_stopUpmpdcli($redis)
    // endif
}

function wrk_playUpmpdcli($redis)
{
    // TO-DO
    // clear the <Upmpdcli timer>
}

function wrk_playerID($arch)
{
    // $playerid = $arch.md5(uniqid(rand(), true)).md5(uniqid(rand(), true));
    $playerid = $arch.md5_file('/sys/class/net/eth0/address');
    // janui modification for a Pi Zero W connected without wired Ethernet (e.g. AP mode) there is no eth0 address
    // if not filled then use the wlan0 information
    if (trim($playerid) === $arch) {
        $playerid = $arch.md5_file('/sys/class/net/wlan0/address');
    }
    // And just in case a normal Pi Zero boots the first time without any network interface use the CPU serial number
    if (trim($playerid) === $arch) {
        $retval = sysCmd('grep -hPo "^Serial\s*:\s*\K[[:xdigit:]]{16}" /proc/cpuinfo');
        $playerid = $arch.'CPU'.$retval[0];
        unset($retval);
    }
    // And just in case...
    if (trim($playerid) === $arch) {
        $playerid = $arch.'-00000-UNKNOWN-00000-';
    }
    // end janui modification
    return $playerid;
}

// function wrk_switchplayer($redis, $playerengine)
// {
    // switch ($playerengine) {
        // case 'MPD':
            // $retval = sysCmd('systemctl is-active mpd');
            // if ($retval[0] === 'active') {
                // // do nothing
            // } else {
                // $return = sysCmd('systemctl start mpd');
            // }
            // unset($retval);
            // // ashuffle gets started automatically
            // usleep(500000);
            // if ($redis->hGet('lastfm','enable') === '1') sysCmd('systemctl start mpdscribble');
            // if ($redis->hGet('dlna','enable') === '1') sysCmd('systemctl start upmpdcli');
            // $redis->set('activePlayer', 'MPD');
            // wrk_mpdRestorePlayerStatus($redis);
            // $return = sysCmd('systemctl stop spopd');
            // $return = sysCmd('curl -s -X GET http://localhost/command/?cmd=renderui');
            // // set process priority
            // sysCmdAsync('rune_prio nice');
            // break;

        // case 'Spotify':
            // $return = sysCmd('systemctl start spopd');
            // usleep(500000);
            // if ($redis->hGet('lastfm','enable') === '1') sysCmd('systemctl stop mpdscribble');
            // if ($redis->hGet('dlna','enable') === '1') sysCmd('systemctl stop upmpdcli');
            // sysCmd('systemctl stop ashuffle');
            // wrk_mpdPlaybackStatus($redis);
            // $redis->set('activePlayer', 'Spotify');
            // $return = sysCmd('systemctl stop mpd');
            // $redis->set('mpd_playback_status', 'stop');
            // $return = sysCmd('curl -s -X GET http://localhost/command/?cmd=renderui');
            // // set process priority
            // sysCmdAsync('rune_prio nice');
            // break;
    // }
    // return $return;
// }

function wrk_sysAcl()
{
    sysCmd('/srv/http/command/convert_dos_files_to_unix_script.sh fast');
}

function wrk_NTPsync($ntpserver)
{
    //debug
    runelog('NTP SERVER', $ntpserver);
    $retval = sysCmd('systemctl is-active systemd-timesyncd');
    $return = $retval[0];
    unset($retval);
    if ($return === 'active') {
        // systemd-timesyncd is running
        // with systemd-timesyncd the new ntp server can not be validated
        // add the server name to timesyncd.conf
        $file = '/etc/systemd/timesyncd.conf';
        // replace the line with 'NTP=' in the line 1 line after a line containing '# Next line is set in RuneAudio Settings'
        // but add the valid pool.ntp.org ntp server to the line in case the new server is invalid
        $newArray = wrk_replaceTextLine($file, '', 'NTP=', 'NTP='.$ntpserver.' pool.ntp.org', '# Next line is set in RuneAudio Settings', 1);
        // Commit changes to /etc/systemd/timesyncd.conf
        $fp = fopen($file, 'w');
        $return = fwrite($fp, implode("", $newArray));
        fclose($fp);
        // restart systemd-timesyncd
        sysCmd('systemctl daemon-reload');
        sysCmd('systemctl restart systemd-timesyncd');
        sysCmd('timedatectl set-ntp true');
        // return the valid ntp server name
        return $ntpserver;
    } else {
        return false;
    }
}

function wrk_restartSamba($redis)
{
    // restart Samba
    // first stop Samba ?
    runelog('Samba Stopping...', '');
    sysCmd('systemctl stop smbd smb nmbd nmb');
    runelog('Samba Dev Mode   :', $redis->get('dev'));
    runelog('Samba Enable     :', $redis->hGet('samba', 'enable'));
    runelog('Samba Read/Write :', $redis->hGet('samba', 'readwrite'));
    // clear the php cache
    clearstatcache(true, '/etc/samba/smb.conf');
    if ($redis->get('dev')) {
        // dev mode on
        // switch smb.conf (development = read/write)
        if (readlink('/etc/samba/smb.conf') == '/etc/samba/smb-dev.conf') {
            // already set do nothing
        } else {
            unlink('/etc/samba/smb.conf');
            symlink('/etc/samba/smb-dev.conf', '/etc/samba/smb.conf');
        }
    } else if ($redis->hGet('samba', 'enable')) {
        // Prod mode and Samba switched on
        if ($redis->hGet('samba', 'readwrite')) {
            // read/write switched on
            if (readlink('/etc/samba/smb.conf') == '/etc/samba/smb-dev.conf') {
                // already set do nothing
            } else {
                unlink('/etc/samba/smb.conf');
                symlink('/etc/samba/smb-dev.conf', '/etc/samba/smb.conf');
            }
        } else {
            // read/write switched off, so read-only switched on
            if (readlink('/etc/samba/smb.conf') == '/etc/samba/smb-prod.conf') {
                // already set do nothing
            } else {
                unlink('/etc/samba/smb.conf');
                symlink('/etc/samba/smb-prod.conf', '/etc/samba/smb.conf');
            }
        }
    }
    if (($redis->get('dev')) OR ($redis->hGet('samba', 'enable'))) {
        runelog('Samba Restarting...', '');
        sysCmd('systemctl daemon-reload');
        sysCmd('systemctl start nmbd nmb smbd smb');
        sysCmd('pgrep -x nmbd || systemctl reload-or-restart nmbd');
        sysCmd('pgrep -x smbd || systemctl reload-or-restart smbd');
        sysCmd('pgrep -x nmb || systemctl reload-or-restart nmb');
        sysCmd('pgrep -x smb || systemctl reload-or-restart smb');
    }
}

function wrk_changeHostname($redis, $newhostname)
{
    // new hostname can not have spaces or special characters
    $newhostname = trim($newhostname);
    If ($newhostname != preg_replace('/[^A-Za-z0-9-]/', '', $newhostname)) {
        // do not do anything
        runelog('new hostname invalid', $newhostname);
        return;
    }
    $retval = sysCmd('hostname');
    $shn = trim($retval[0]);
    unset($retval);
    $rhn = trim($redis->get('hostname'));
    runelog('current system hostname:', $shn);
    runelog('current redis hostname :', $rhn);
    runelog('new hostname           :', $newhostname);
    // update airplay name
    if ((trim($redis->hGet('airplay', 'name')) === $rhn) && ($newhostname != $rhn)) {
        $redis->hSet('airplay', 'name', $newhostname);
        wrk_shairport($redis, $redis->get('ao'), $newhostname);
        if ($redis->hGet('airplay','enable') === '1') {
            runelog("service: airplay restart",'');
            sysCmd('systemctl reload-or-restart shairport-sync || systemctl start shairport-sync');
        }
    }
    // update spotifyconnect name
    if ((trim($redis->hGet('spotifyconnect', 'device_name')) === $rhn) && ($newhostname != $rhn)) {
        $redis->hSet('spotifyconnect', 'device_name', $newhostname);
        wrk_spotifyd($redis, $redis->get('ao'), $newhostname);
        if ($redis->hGet('spotifyconnect','enable') === '1') {
            runelog("service: spotifyconnect restart",'');
            sysCmd('systemctl reload-or-restart spotifyd || systemctl start spotifyd');
            $redis->hSet('spotifyconnect', 'track_id', '');
            $redis->hSet('spotifyconnect', 'last_track_id', '');
            $redis->hSet('spotifyconnect', 'event_time_stamp', 0);
            $redis->hSet('spotifyconnect', 'last_time_stamp', 0);
        }
    }
    // update dlna name
    if ((trim($redis->hGet('dlna', 'name')) === $rhn) && ($newhostname != $rhn)) {
        $redis->hSet('dlna','name', $newhostname);
        wrk_upmpdcli($redis, $newhostname);
        if ($redis->hGet('dlna', 'enable') === '1') {
            runelog("service: UPMPDCLI restart");
            sysCmd('systemctl reload-or-restart upmpdcli || systemctl start upmpdcli');
        }
    }
    // update mpd if required
    If ($redis->hGet('mpdconf', 'zeroconf_name') != $newhostname) {
        // update zeroconfname in MPD configuration
        $redis->hSet('mpdconf', 'zeroconf_name', $newhostname);
        // rewrite mpd.conf file
        wrk_mpdconf($redis, 'refresh');
    }
    // change system hostname
    $redis->set('hostname', $newhostname);
    sysCmd('hostnamectl  --static --transient --pretty set-hostname '.strtolower($newhostname));
    // 'host-name' is optionally set in /etc/avahi/avahi-daemon.conf
    // change any line beginning with 'host-name' to 'host-name=<new_host_name>'
    // if 'host-name' is commented out, no problem, nothing will change
    sysCmd('sed -i '."'".'s|^[[:space:]]*host-name.*|host-name='.strtolower($newhostname).'|g'."'".' /etc/avahi/avahi-daemon.conf');
    // update AVAHI service data
    wrk_avahiconfig($redis, strtolower($newhostname));
    // activate when a change has been made
    if ($redis->get('avahiconfchange')) {
        // restart avahi-daemon if it is running (active), some users switch it off
        // it is also started automatically when shairport-sync starts
        $retval = sysCmd('systemctl is-active avahi-daemon');
        if ($retval[0] === 'active') {
            sysCmd('systemctl stop avahi-daemon');
            sysCmd('systemctl daemon-reload');
            sysCmd('systemctl start avahi-daemon || systemctl reload-or-restart avahi-daemon');
        }
        unset($retval);
        // reconfigure MPD
        //wrk_mpdPlaybackStatus($redis);
        wrk_mpdRestorePlayerStatus($redis);
        // restart SAMBA
        wrk_restartSamba($redis);
    }
    $redis->set('avahiconfchange', 0);
    // set process priority
    sysCmdAsync('nice --adjustment=2 /var/www/command/rune_prio nice');
}

function wrk_upmpdcli($redis, $name = null, $queueowner = null)
{
    if (!isset($name)) {
        $name = $redis->hGet('dlna', 'name');
    }
    if (!isset($queueowner)) {
        $queueowner = $redis->hGet('dlna', 'queueowner');
    }
    if ($queueowner != 1) {
        $queueowner = '0';
    }
    $file = '/usr/lib/systemd/system/upmpdcli.service';
    $newArray = wrk_replaceTextLine($file, '', 'ExecStart=', 'ExecStart=/usr/bin/upmpdcli -c /etc/upmpdcli.conf -q '.$queueowner.' -d '.$redis->hGet('dlna', 'logfile').' -l '.$redis->hGet('dlna', 'loglevel').' -f "'.$name.'"');
    runelog('upmpdcli.service :', $newArray);
    // Commit changes to /usr/lib/systemd/system/upmpdcli.service
    $fp = fopen($file, 'w');
    fwrite($fp, implode("", $newArray));
    fclose($fp);
    if ($redis->hGet('dlna','enable') === '1') {
        // update systemd
        sysCmd('systemctl daemon-reload');
        runelog('restart upmpdcli');
        sysCmd('systemctl reload-or-restart upmpdcli');
    }
    // set process priority
    sysCmdAsync('nice --adjustment=2 /var/www/command/rune_prio nice');
}

function alsa_findHwMixerControl($cardID)
{
    $cmd = "amixer -c ".$cardID." |grep \"mixer control\"";
    $str = sysCmd($cmd);
    $hwmixerdev = substr(substr($str[0], 0, -(strlen($str[0]) - strrpos($str[0], "'"))), strpos($str[0], "'")+1);
    runelog('Try to find HwMixer control (str): ', $str);
    runelog('Try to find HwMixer control: (output)', $hwmixerdev);
    return $hwmixerdev;
}

// webradio management (via .pls files)
function addRadio($mpd, $redis, $data)
{
    if ($data->label !== '' && $data->url !== '') {
        //debug
        runelog('addRadio (data)', $data);
        // store webradio record in redis
        $redis->hSet('webradios', $data->label, $data->url);
        // create new file
        // $file = '/mnt/MPD/Webradio/'.$data['label'].'.pls';
        $file = '/mnt/MPD/Webradio/'.$data->label.'.pls';
        $newpls = "[playlist]\n";
        $newpls .= "NumberOfEntries=1\n";
        $newpls .= "File1=".$data->url."\n";
        $newpls .= "Title1=".$data->label;
        // Commit changes to .pls file
        $fp = fopen($file, 'w');
        $return = fwrite($fp, $newpls);
        fclose($fp);
        if ($return) sendMpdCommand($mpd, 'update Webradio');
    } else {
        $return = false;
    }
    return $return;
}

function editRadio($mpd,$redis,$data)
{
    if ($data->label !== '' && $data->url !== '') {
        //debug
        runelog('editRadio (data)', $data);
        // edit webradio URL in .pls file
        $file = '/mnt/MPD/Webradio/'.$data->label.'.pls';
        if ($data->label !== $data->newlabel) {
            unlink($file);
            // delete old webradio record in redis
            $redis->hDel('webradios', $data->label);
            // store new webradio record in redis
            $data->label = $data->newlabel;
            $data->newlabel = null;
            $return = addRadio($mpd, $redis, $data);
        } else {
            $redis->hSet('webradios',$data->label,$data->url);
            $newArray = wrk_replaceTextLine($file, '', 'File1=', 'File1='.$data->url, 'NumberOfEntries=1',1);
            // Commit changes to .pls file
            $fp = fopen($file, 'w');
            $return = fwrite($fp, implode("", $newArray));
            fclose($fp);
        }
        if ($return) sendMpdCommand($mpd, 'update Webradio');
    } else {
        $return = false;
    }
    return $return;
}

function deleteRadio($mpd,$redis,$data)
{
    if ($data->label !== '') {
        //debug
        runelog('deleteRadio (data)', $data);
        // delete .pls file
        $file = '/mnt/MPD/Webradio/'.$data->label;
        $label = parseFileStr($data->label, '.', 1);
        runelog('deleteRadio (label)', $label);
        $return = unlink($file);
        if ($return) {
            // delete webradio record in redis
            $redis->hDel('webradios', $label);
            sendMpdCommand($mpd, 'update Webradio');
        }
    } else {
        $return = false;
    }
    return $return;
}

function ui_notify($title = null, $text, $type = null, $permanotice = null)
{
    if (is_object($permanotice)) {
        $output = array('title' => $title, 'permanotice' => '', 'permaremove' => '');
    } else {
        if ($permanotice === 1) {
            $output = array('title' => $title, 'text' => $text, 'permanotice' => '');
        } else {
            $output = array('title' => $title, 'text' => $text);
        }
    }
    ui_render('notify', json_encode($output));
}

function ui_notifyError($title = null, $text, $type = null, $permanotice = null)
{
    if (is_object($permanotice)) {
        $output = array('title' => $title, 'permanotice' => '', 'permaremove' => '', 'icon' => 'fa fa-exclamation');
    } else {
        if ($permanotice === 1) {
            $output = array('title' => $title, 'text' => $text, 'permanotice' => '', 'icon' => 'fa fa-exclamation');
        } else {
            $output = array('title' => $title, 'text' => $text, 'icon' => 'fa fa-exclamation');
        }
    }
    ui_render('notify', json_encode($output));
}

function ui_notify_async($title = null, $text, $type = null, $permanotice = null)
{
    if (is_object($permanotice)) {
        $output = array('title' => $title, 'permanotice' => '', 'permaremove' => '');
    } else {
        if ($permanotice === 1) {
            $output = array('title' => $title, 'text' => $text, 'permanotice' => '');
        } else {
            $output = array('title' => $title, 'text' => $text);
        }
    }
    $output = json_encode($output);
    runelog('notify (async) JSON string: ', $output);
    if (!strpos(' '.$output,"'")) {
        sysCmdAsync('/var/www/command/ui_notify.php \''.$output.'\'');
    } else {
        sysCmdAsync('/var/www/command/ui_notify.php "'.$output.'"');
    }
}

function wrk_notify($redis, $action, $notification, $jobID = null)
{
    switch ($action) {
        case 'raw':
            // debug
            runelog('wrk_notify (raw)', $notification);
            break;
        case 'startjob':
            if (!empty($notification)) {
                if (is_object($notification)) {
                    $notification = json_encode(array('title' => $notification->title, 'text' => $notification->text, 'icon' => 'fa fa-cog fa-spin', 'permanotice' => $jobID));
                    // debug
                    runelog('wrk_notify (startjob) jobID='.$jobID, $notification);
                }
                if (wrk_notify_check($notification)) {
                    if (empty($redis->hGet('notifications', $jobID)) && empty($redis->hGet('notifications', 'permanotice_'.$jobID))) {
                        $redis->hSet('notifications', $jobID, $notification);
                    }
                }
            }
            break;
        case 'endjob':
            $notification = $redis->hGet('notifications', $jobID);
            if (!empty($notification)) {
                $notification = json_decode($notification);
                $notification = json_encode(array('title' => $notification->title, 'text' => '', 'permanotice' => $jobID, 'permaremove' => $jobID));
                // debug
                runelog('wrk_notify (endjob) jobID='.$jobID, $notification);
                $redis->hDel('notifications', $jobID);
            }
            break;
        case 'kernelswitch':
            // debug
            runelog('wrk_notify (kernelswitch) jobID='.$jobID, $notification);
            if (!empty($notification)) {
                $notification = json_encode(array('title' => $notification->title, 'text' => $notification->text, 'custom' => 'kernelswitch'));
                if (wrk_notify_check($notification)) {
                    // if (empty($redis->hGet('notifications', $jobID)) && empty($redis->hGet('notifications', 'permanotice_'.$jobID))) {
                        $redis->hSet('notifications', 'permanotice_kernelswitch', $notification);
                    // }
                }
            }
            break;
    }
    if (wrk_notify_check($notification)) ui_render('notify', $notification);
}

function wrk_notify_check($notification)
{
    if (json_decode($notification) !== null) {
        $notification = json_decode($notification);
        if (isset($notification->title) && isset($notification->text)) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

class ui_renderQueue
{
    public function __construct($socket)
    {
        $this->socket = $socket;
    }
    public function output()
    {
        $queue = getPlayQueue($this->socket);
        ui_render('queue', json_encode($queue));
    }
}

function ui_status($mpd, $status)
{
    $curTrack = getTrackInfo($mpd, $status['song']);
    if (isset($curTrack[0]['Title'])) {
        $status['currentartist'] = $curTrack[0]['Artist'];
        $status['currentsong'] = htmlentities($curTrack[0]['Title'], ENT_XML1, 'UTF-8');
        $status['currentalbum'] = $curTrack[0]['Album'];
        $status['currentcomposer'] = $curTrack[0]['Composer'];
        $status['fileext'] = parseFileStr($curTrack[0]['file'], '.');
    } else {
        $path = parseFileStr($curTrack[0]['file'], '/');
        $status['fileext'] = parseFileStr($curTrack[0]['file'], '.');
        $status['currentartist'] = "";
        // $status['currentsong'] = $song;
        if (!empty($path)) {
            $status['currentalbum'] = $path;
        } else {
            $status['currentalbum'] = '';
        }
    }
    $status['file'] = $curTrack[0]['file'];
    $status['radioname'] = $curTrack[0]['Name'];
    return $status;
}

function ui_libraryHome($redis, $clientUUID=null)
{
    // Internet available
    $internetAvailable = $redis->hGet('service', 'internet');
    // LocalStorage
    $localStorages = countDirs('/mnt/MPD/LocalStorage');
    // runelog('ui_libraryHome - networkmounts: ',$networkmounts);
    // Network mounts
    $networkMounts = countDirs('/mnt/MPD/NAS');
    // runelog('ui_libraryHome - networkmounts: ',$networkmounts);
    // USB mounts
    $usbMounts = countDirs('/mnt/MPD/USB');
    // runelog('ui_libraryHome - usbmounts: ',$usbmounts);
    // Webradios
    if ($redis->hGet('service', 'webradio')) {
        $webradios = count($redis->hKeys('webradios'));
        // runelog('ui_libraryHome - webradios: ',$webradios);
    } else {
        $webradios = '';
    }
    // Jamendo
    if ($redis->hGet('service', 'jamendo')) {
        $jamendo = 1;
        // runelog('ui_libraryHome - jamendo: ',$jamendo);
    } else {
        $jamendo = '';
    }
    // Dirble
    if ($redis->hGet('service', 'dirble')) {
        // dirble is available
        $proxy = $redis->hGetall('proxy');
        $dirblecfg = $redis->hGetAll('dirble');
        $dirble = json_decode(curlGet($dirblecfg['baseurl'].'amountStation/apikey/'.$dirblecfg['apikey'], $proxy));
        // runelog('ui_libraryHome - dirble: ',$dirble);
        $dirbleAmount = $dirble->amount;
    } else {
        $dirbleAmount = '';
    }
    // Spotify
    if ($redis->hGet('spotify', 'enable')) {
        $spotify = 1;
        // runelog('ui_libraryHome - spotify: ',$spotify);
    } else {
        $spotify = '';
    }
    // Check current player backend
    $activePlayer = $redis->get('activePlayer');
    // Bookmarks
    $bookmarks = array();
    if ($redis->Exists('bookmarks')) {
        $redis_bookmarks = $redis->hGetAll('bookmarks');
        $bookmarks = array();
        foreach ($redis_bookmarks as $key => $data) {
            $bookmark = json_decode($data);
            runelog('ui_libraryHome - bookmark details', $data);
            // $bookmarks[] = array('bookmark' => $key, 'name' => $bookmark->name, 'path' => $bookmark->path);
            $bookmarks[] = array('id' => $key, 'name' => $bookmark->name, 'path' => $bookmark->path);
        }
    } else {
        // $bookmarks[0] = '';
    }
    // runelog('ui_libraryHome - bookmarks: ',$bookmarks);
    // $jsonHome = json_encode(array_merge($bookmarks, array(0 => array('networkMounts' => $networkmounts)), array(0 => array('USBMounts' => $usbmounts)), array(0 => array('webradio' => $webradios)), array(0 => array('Dirble' => $dirble->amount)), array(0 => array('ActivePlayer' => $activePlayer))));
    // $jsonHome = json_encode(array_merge($bookmarks, array(0 => array('networkMounts' => $networkmounts)), array(0 => array('USBMounts' => $usbmounts)), array(0 => array('webradio' => $webradios)), array(0 => array('Spotify' => $spotify)), array(0 => array('Dirble' => $dirble->amount)), array(0 => array('ActivePlayer' => $activePlayer))));
    $jsonHome = json_encode(array('internetAvailable' => $internetAvailable, 'bookmarks' => $bookmarks, 'localStorages' => $localStorages, 'networkMounts' => $networkMounts, 'USBMounts' => $usbMounts, 'webradio' => $webradios, 'Spotify' => $spotify, 'Dirble' => $dirbleAmount, 'Jamendo' => $jamendo, 'ActivePlayer' => $activePlayer, 'clientUUID' => $clientUUID));
    // Encode UI response
    runelog('ui_libraryHome - JSON: ', $jsonHome);
    ui_render('library', $jsonHome);
}

function ui_lastFM_coverart($redis, $artist, $album, $lastfm_apikey, $proxy)
{
    if (!$redis->hGet('service', 'lastfm')) {
        return false;
    }
    if (!empty($album)) {
        $url = "https://ws.audioscrobbler.com/2.0/?method=album.getinfo&api_key=".$lastfm_apikey."&artist=".urlencode($artist)."&album=".urlencode($album)."&format=json";
        unset($artist);
    } else {
        $url = "https://ws.audioscrobbler.com/2.0/?method=artist.getinfo&api_key=".$lastfm_apikey."&artist=".urlencode($artist)."&format=json";
        $artist = 1;
    }
    // debug
    //echo $url;
    $output = json_decode(curlGet($url, $proxy), true);
    // debug
    runelog('coverart lastfm query URL', $url);
    // debug++
    // echo "<pre>";
    // print_r($output);
    // echo "</pre>";

    // key [3] == extralarge last.fm image
    // key [4] == mega last.fm image
    if(isset($artist)) {
        runelog('coverart lastfm query URL', $output['artist']['image'][3]['#text']);
        return $output['artist']['image'][3]['#text'];
    } else {
        runelog('coverart lastfm query URL', $output['album']['image'][3]['#text']);
        return $output['album']['image'][3]['#text'];
    }
}

// populate queue with similiar tracks suggested by Last.fm
function ui_lastFM_similar($redis, $artist, $track, $lastfmApikey, $proxy)
{
    if (!$redis->hGet('service', 'lastfm')) {
        return false;
    }
    runelog('similar lastfm artist', $artist);
    runelog('similar lastfm track', $track);
    runelog('similar lastfm name', $proxy);
    runelog('similar lastfm lastfm_api', $lastfm_api);
    // This makes the call to Last.fm. The limit parameter can be adjusted to the number of tracks you want returned.
    // [TODO] adjustable amount of tracks in settings screen
    $url = "https://ws.audioscrobbler.com/2.0/?method=track.getsimilar&limit=1000&api_key=".$lastfm_apikey."&artist=".urlencode($artist)."&track=".urlencode($track)."&format=json";
    runelog('similar lastfm query URL', $url);
    // debug
    //echo $url;
    // This call does not work
    //$output = json_decode(curlGet($url, $proxy), true);
    // But these 2 lines do
    $content = file_get_contents($url);
    $output = json_decode($content, true);
    // debug
    // debug++
    // echo "<pre>";
    // print_r($output);
    // echo "</pre>";
    $retval = false;
    foreach($output['similartracks']['track'] as $similar) {
        $simtrack = $similar['name'];
        $simartist = $similar['artist']['name'];
        if ($simtrack && $simartist) {
            // If we have a track and an artist then make a call to mpd to add it. If it doesn't exist then it doesn't
            // matter
            $status = sysCmd("mpc search artist '".$simartist."' title '".$simtrack. "' | head -n1 | mpc add");
            $retval = true;
        }
    }
    return $retval;
}

// push UI update to NGiNX channel
function ui_render($channel, $data)
{
    curlPost('http://127.0.0.1/pub?id='.$channel, $data);
    runelog('ui_render channel=', $channel);
}

function ui_timezone() {
    // used to provide a list of valid timezones for the UI
    $zones_array = array();
    $timestamp = time();
    foreach(timezone_identifiers_list() as $key => $zone) {
        date_default_timezone_set($zone);
        $zones_array[$key]['zone'] = $zone;
        $zones_array[$key]['diff_from_GMT'] = 'GMT ' . date('P', $timestamp);
    }
    return $zones_array;
}

function autoset_timezone($redis) {
    // this function uses a one of the many internet services which return the timezone and the country code
    // it uses the external IP-address of the connected network to determine the location
    // is used to automatically set the timezone and the Wi-Fi regulatory domain
    // the timezone will only be changed when the current timezone is set to the
    //      distribution default timezone (Pacific/Pago_Pago) the Wi-Fi regulatory domain = 00 and internet is available
    // https://ipsidekick.com/ and https://ipapi.co/ were found to be unreliable, currently using https://timezoneapi.io
    //
    $wifiRegDom00 = sysCmd('iw reg get | grep -ic 00')[0];
    if ($redis->hget('service', 'internet') && ($redis->get('timezone') === 'Pacific/Pago_Pago') && $wifiRegDom00) {
        // make sure that file_get_contents() times out when nothing is returned
        $opts = array('http' =>
            array(
                // timeout in seconds
                // 5 seconds is a little on the high side, 2 or 3 is probably better.
                // its not really problem because this will only be run once per installation!
                'timeout' => 5
            )
        );
        $context  = stream_context_create($opts);
        // https://ipsidekick.com/
        // $result = file_get_contents('https://ipsidekick.com/json', false, $context);
        // https://ipapi.co/
        // $result = file_get_contents('https://ipapi.co/json', false, $context);
        // https://timezoneapi.io
        $timezoneapiToken = $redis->hGet('TimezoneAPI', 'apikey');
        $result = file_get_contents('https://timezoneapi.io/api/ip/?token='.$timezoneapiToken, false, $context);
        // debug
        $redis->set('wrk_autoset_timezone', $result);
        if ($result) {
            $result = json_decode($result, true);
            // https://ipsidekick.com/
            // if (isset($result['timeZone']['name']) && strlen($result['timeZone']['name'])) {
                // runelog('autoset_timezone :', $result['timeZone']['name']);
                // $timeZone = $result['timeZone']['name'];
                // $countryCode = $result['country']['code'];
            // https://ipapi.co/
            // if (isset($result['timezone']) && strlen($result['timezone'])) {
                // runelog('autoset_timezone :', $result['timezone']);
                // $timeZone = $result['timezone'];
                // $countryCode = $result['country_code'];
            // https://timezoneapi.io
            if (isset($result['data']['timezone']['id']) && strlen($result['data']['timezone']['id'])) {
                runelog('autoset_timezone :', $result['data']['timezone']['id']);
                $timeZone = $result['data']['timezone']['id'];
                $countryCode = $result['data']['country_code'];
                $result = sysCmd('timedatectl set-timezone '."'".$timeZone."'")[0];
                $result = ' '.strtolower($restult);
                if (strpos($result, 'failed') || strpos($result, 'invalid')) {
                    sysCmd("timedatectl set-timezone 'Pacific/Pago_Pago'");
                } else {
                    $redis->set('timezone', $timeZone);
                    // set the Wi-Fi regulatory domain, the standard is 00 and is compatible with most countries
                    // setting it will could allow more Wi-Fi power to be used (never less) and sometimes improve the usable frequency ranges
                    // not all country codes have a specificity specified regulatory domain profile, so if it fails, set to the default (00)
                    sysCmd('iw reg set '.$countryCode.' || iw reg set 00');
                    ui_notify('Timezone', 'Timezone automatically updated.<br>Current timezone: '.$timeZone);
                }
            }
        }
    }
}

function wrk_setTimezone($redis, $timeZone) {
    // the timezone and the Wi-Fi regulatory domain from the UI
    // return true when successful, false on error
    $result = sysCmd('timedatectl set-timezone '."'".$timeZone."'")[0];
    $result = ' '.strtolower($restult);
    if (strpos($result, 'failed') || strpos($result, 'invalid')) {
        $retval = false;
    } else {
        $redis->set('timezone', $timeZone);
        // determine the country code from the timezone
        $tz = new DateTimeZone($timeZone);
        $countryCode = timezone_location_get($tz)['country_code'];
        // set the Wi-Fi regulatory domain, the standard is 00 and is compatible with most countries
        // setting it will could allow more Wi-Fi power to be used (never less) and sometimes improve the usable frequency ranges
        // not all country codes have a specificity specified regulatory domain profile, so if it fails, set to the default (00)
        sysCmd('iw reg set '.$countryCode.' || iw reg set 00');
        $retval = true;
    }
    return $retval;
}

function ui_update($redis, $sock, $clientUUID=null)
{
    ui_libraryHome($redis, $clientUUID);
    switch ($redis->get('activePlayer')) {
        case 'MPD':
            if ($redis->get('pl_length') !== '0') {
                sendMpdCommand($sock, 'swap 0 0');
            } else {
                sendMpdCommand($sock, 'clear');
            }
            // return MPD response
            return readMpdResponse($sock);
            break;
        case 'Spotify':
            sendSpopCommand($sock, 'repeat');
            sendSpopCommand($sock, 'repeat');
             // return SPOP response
            return readSpopResponse($sock);
            break;
    }
}

function ui_mpd_response($mpd, $notify = null)
{
    runelog('ui_mpd_response invoked');
    $response = json_encode(readMpdResponse($mpd));
    // --- TODO: check this condition
    if (strpos($response, "OK") && isset($notify)) {
        runelog('send UI notify: ', $notify);
        ui_notify($notify['title'], $notify['text']);
    }
    echo $response;
}

function curlPost($url, $data, $proxy = null)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Connection: close"));
    curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    if (isset($proxy)) {
        if ($proxy['enable'] === '1') {
            $proxy['user'] === '' || curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy['user'].':'.$proxy['pass']);
            curl_setopt($ch, CURLOPT_PROXY, $proxy['host']);
            //runelog('cURL proxy HOST: ',$proxy['host']);
            //runelog('cURL proxy USER: ',$proxy['user']);
            //runelog('cURL proxy PASS: ',$proxy['pass']);
        }
    }
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 400);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);  // DO NOT RETURN HTTP HEADERS
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);  // RETURN THE CONTENTS OF THE CALL
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function curlGet($url, $proxy = null)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Connection: close"));
    curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    if (isset($proxy)) {
        if (isset($proxy['enable']) && $proxy['enable'] === '1' && isset($proxy['host']) && $proxy['host']) {
            if (isset($proxy['user']) && $proxy['user'] && isset($proxy['pass'])) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy['user'].':'.$proxy['pass']);
            }
            curl_setopt($ch, CURLOPT_PROXY, $proxy['host']);
            // runelog('cURL proxy HOST: ',$proxy['host']);
            // runelog('cURL proxy USER: ',$proxy['user']);
            // runelog('cURL proxy PASS: ',$proxy['pass']);
        }
    }
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 400);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function countDirs($basepath)
{
    $scandir = scandir($basepath."/", SCANDIR_SORT_NONE);
    $count = count(array_diff($scandir, array('..', '.')));
    return $count;
}

function netmask($bitcount)
{
    $netmask = str_split(str_pad(str_pad('', $bitcount, '1'), 32, '0'), 8);
    foreach ($netmask as &$element) $element = bindec($element);
    return join('.', $netmask);
}

// sort multi-dimensional array by key
function osort(&$array, $key)
{
    usort($array, function($a, $b) use ($key) {
        return $a->$key > $b->$key ? 1 : -1;
    });
}

// clean up strings for lyrics and artistinfo
function lyricsStringClean($string, $type=null)
{
    // replace all combinations of single or multiple tab, space, <cr> or <lf> with a single space
    $string = preg_replace('/[\t\n\r\s]+/', ' ', $string);
    // standard trim of whitespace
    $string = trim($string);
    // trim open or closed angle, square, round or squiggly brackets in first and last positions
    $string = trim($string, '<[({})}>');
    // truncate the string up to a open or closed angle, square, round or squiggly bracket
    $string = explode('[', $string);
    $string = explode('(', $string[0]);
    $string = explode('{', $string[0]);
    $string = explode('<', $string[0]);
    $string = explode(')', $string[0]);
    $string = explode('}', $string[0]);
    $string = explode(')', $string[0]);
    $string = explode('>', $string[0]);
    // for artist truncate the string to the first semicolon, slash or comma
    if ($type == 'artist') {
        $string = explode(';', $string[0]);
        $string = explode('/', $string[0]);
        $string = explode(',', $string[0]);
    }
    // remove leading and trailing ASCII hex characters 0 to 2F and 3A to 40 and 5B to 60 and 7B to 7F
    $string = trim($string[0], "\x0..\x2F\x3A..\x40\x5B..\x60\x7B..\x7F");
    return $string;
}

// function to refresh the nics and network database arrays
function refresh_nics($redis)
// This function returns an array of nics (and false on error)
// three arrays are saved in redis:
//   'network_interfaces' containing the nics (always saved)
//   'translate_mac_nic' containing a translation table mac-address to nic-name (always saved)
//   'network_info' containing the network information (not saved when $process and $return = 'nics')
{
    // startup - lock the scan system
    runelog('--------------------------- lock the scan system ---------------------------');
    $lockWifiscan = $redis->Get('lock_wifiscan');
    if ($lockWifiscan) {
        if ($lockWifiscan >= 7) {
            // its not really a great problem if this routine runs twice at the same time
            // but spread the attempts, so let it run on the 7th attempt
        } else {
            $redis->Set('lock_wifiscan', ++$lockWifiscan);
            return false;
        }
    }
    // lock it
    $redis->Set('lock_wifiscan', 1);
    //
    // startup - collect system data
    runelog('--------------------------- set up variables and initialise ---------------------------');
    // nics to exclude from processing
    $excluded_nics = array('ifb0', 'ifb1', 'p2p0', 'bridge', 'lo');
    // this routine will switch on the following technologies (use lower case)
    $enabled_technology = array('wifi', 'ethernet');
    // this routine will only process the following technologies (use lower case)
    $process_technology = array('wifi', 'ethernet');
    // switch the technology on
    foreach ($enabled_technology as $technology) {
        sysCmd('connmanctl enable '.$technology);
    }
    // get the default gateway
    $defaultGateway = sysCmd("ip route | grep -i 'default via'");
    if (isset($defaultGateway[0])) {
        $defaultGateway = trim(str_replace(' via', 'via', preg_replace('!\s+!', ' ', $defaultGateway[0])));
        $defaultGateway = explode(' ', $defaultGateway);
        if (isset($defaultGateway[1])) {
            $defaultGateway = $defaultGateway[1];
        } else {
            $defaultGateway = '';
        }
    } else {
        $defaultGateway = '';
    }
    // get the default dns nameservers, use the same value as the default gateway
    $primaryDns = $defaultGateway;
    $secondaryDns = '';
    //
    // add MAC addresses to array $networkInterfaces with ip link
    // also add the nic's per MAC address to the array $translateMacNic
    $networkInterfaces = array();
    $translateMacNic = array();
    // get the array containing any mac addresses which need to be spoofed
    if ($redis->Exists('network_mac_spoof')) {
        $networkSpoofArray = json_decode($redis->Get('network_mac_spoof'), true);
    } else {
        $networkSpoofArray = array();
    }
    // get the nics
    $links = sysCmd("ip -o -br link | sed 's,[ ]\+, ,g'");
    foreach ($links as $link) {
        $linkArray = explode(' ', $link);
        $nic = trim($linkArray[0]);
        if (in_array($nic, $excluded_nics)) {
            // skip nics in the excluded list
            continue;
        }
        $macAddress = $linkArray[2];
        if (in_array($macAddress , $networkSpoofArray)) {
            // cheap network card, they all have the same MAC address (e.g. '00:e0:4c:53:44:58'), make it unique by spoofing
            $macAddress = fix_mac($redis, $nic);
        }
        $macAddress = str_replace(':', '', $macAddress);
        $translateMacNic[$macAddress.'_'] = $nic;
        $networkInterfaces[$nic]['macAddress'] = $macAddress;
        $networkInterfaces[$nic]['nic'] = $nic;
        $networkInterfaces[$nic]['ipStatus'] = $linkArray[1];
        $networkInterfaces[$nic]['ipInfo'] = $linkArray[3];
        $networkInterfaces[$nic]['nic'] = $nic;
        if ($nic === 'lo' ) {
            // set technology to loopback
            $networkInterfaces[$nic]['technology'] = 'loopback';
        } else {
            // set default technology to ethernet, wifi will be determined below
            $networkInterfaces[$nic]['technology'] = 'ethernet';
        }
        // set the connected state to false, the connected ones will be determined below
        $networkInterfaces[$nic]['connected'] = false;
        $networkInterfaces[$nic]['ipv4Address'] = '';
        $networkInterfaces[$nic]['ipv4Mask'] = '';
        $networkInterfaces[$nic]['ipv4Broadcast'] = '';
        $networkInterfaces[$nic]['ipv4Rest'] = '';
        $networkInterfaces[$nic]['ipv6Address'] = '';
        $networkInterfaces[$nic]['ipv6Rest'] = '';
        // set the default gateway and DSN Name servers
        $networkInterfaces[$nic]['defaultGateway'] = '';
        $networkInterfaces[$nic]['primaryDns'] = '';
        $networkInterfaces[$nic]['secondaryDns'] = '';
        // set the speed to speed to unknown, the speed of the connected ones will be determined below
        $networkInterfaces[$nic]['speed'] ='Unknown';
        // save the default ssid and type, wifi ssis will be set up below
        $networkInterfaces[$nic]['ssid'] = 'Wired';
        $networkInterfaces[$nic]['type'] = '';
        // enable the nic
        sysCmd('ip link set '.$nic.' up');
    }
    // add ip addresses to array $networkInterfaces with ip address
    $addrs = sysCmd("ip -o  address | sed 's,[ ]\+, ,g'");
    foreach ($addrs as $addr) {
        $addrArray = explode(' ', $addr, 5);
        $nic = $addrArray[1];
        if (in_array($nic, $excluded_nics)) {
            // skip nics in the excluded list
            continue;
        }
        $networkInterfaces[$nic]['nic'] = $nic;
        if (isset($addrArray[2])) {
            $networkInterfaces[$nic]['connected'] = true;
            if ($addrArray[2] === 'inet') {
                $networkInterfaces[$nic]['ipv4Address'] = substr($addrArray[3],0,strpos($addrArray[3],'/'));
                $networkInterfaces[$nic]['ipv4Mask'] = net_CidrToNetmask(substr($addrArray[3],strpos($addrArray[3],'/')+1));
                $ipv4Rest = explode(' ', str_replace('  ', ' ', str_replace("\\", '', $addrArray[4])), 3);
                if ((isset($ipv4Rest[2])) && $ipv4Rest[0] === 'brd') {
                    $networkInterfaces[$nic]['ipv4Broadcast'] = $ipv4Rest[1];
                    $networkInterfaces[$nic]['ipv4Rest'] = $ipv4Rest[2];
                } else {
                    $networkInterfaces[$nic]['ipv4Broadcast'] = '';
                    $networkInterfaces[$nic]['ipv4Rest'] = str_replace('  ', ' ', str_replace("\\", '', $addrArray[4]));
                }
            } else if ($addrArray[2] === 'inet6') {
                $networkInterfaces[$nic]['ipv6Address'] = $addrArray[3];
                $networkInterfaces[$nic]['ipv6Rest'] = str_replace('  ', ' ', str_replace("\\", '', $addrArray[4]));
            }
            // set the default gateway and DSN name servers
            $networkInterfaces[$nic]['defaultGateway'] = $defaultGateway;
            $networkInterfaces[$nic]['primaryDns'] = $primaryDns;
            $networkInterfaces[$nic]['secondaryDns'] = $secondaryDns;
        }
        if ($networkInterfaces[$nic]['speed'] === 'Unknown') {
            $speed = sysCmd('ethtool '.$nic." | grep -i speed | sed 's,[ ]\+, ,g'");
            if ((isset($speed[0])) && (strpos(' '.$speed[0], ':'))) {
                $speed = trim(explode(':', preg_replace('!\s+!', ' ', $speed[0]),2)[1]);
                if ($speed) {
                    $networkInterfaces[$nic]['speed'] = str_replace('0Mb', '0 Mb', $speed);
                }
            }
        }
        // wired nics without an IP address will not be added
    }
    // determine the wireless nics with iw
    // add the wifi technology to array $networkInterfaces with iw
    // also add the nic's per physical id to array $wirelessNic
    $wirelessNic = array();
    $deviceInfoList = sysCmd("iw dev | sed 's,[ ]\+, ,g' | grep -iE 'phy|interface|ssid|type'");
    foreach ($deviceInfoList as $deviceInfoLine) {
        $deviceInfoLine = ' '.trim(preg_replace('!\s+!', ' ', $deviceInfoLine));
        if (strpos($deviceInfoLine, 'phy')) {
            $phyDev = trim(str_replace('#', '', $deviceInfoLine));
        } else if (strpos($deviceInfoLine, 'Interface')) {
            $nic = trim(explode(' ', trim($deviceInfoLine))[1]);
            if (in_array($nic, $excluded_nics)) {
                // skip nics in the excluded list
                continue;
            }
            // array for pysical device id to nic name translation
            $wirelessNic[$phyDev] = $nic;
            // register the technology as wifi
            $networkInterfaces[$nic]['technology'] = 'wifi';
            // save the physical device
            $networkInterfaces[$nic]['physical'] = $phyDev;
            // save the default ssid
            $networkInterfaces[$nic]['ssid'] = '';
            // save the default type
            $networkInterfaces[$nic]['type'] = '';
            // refresh network list for wifi
            sysCmd('iwctl station '.$nic.' scan');
            // sleep (1);
        } else if (strpos($deviceInfoLine, 'ssid ')) {
            $networkInterfaces[$nic]['ssid'] = trim(explode(' ', trim($deviceInfoLine))[1]);
        } else if (strpos($deviceInfoLine, 'type ')) {
            $networkInterfaces[$nic]['type'] = trim(explode(' ', trim($deviceInfoLine))[1]);
        }
        if ($networkInterfaces[$nic]['speed'] === 'Unknown') {
            $speed = sysCmd('iw dev '.$nic." station dump | grep -i 'rx bitrate' | sed 's,[ ]\+, ,g'");
            if ((isset($speed[0])) && (strpos(' '.$speed[0], ':'))) {
                $speed = trim(explode(':', preg_replace('!\s+!', ' ', $speed[0]),2)[1]);
                if ($speed) {
                    $networkInterfaces[$nic]['speed'] = $speed;
                }
            }
        }
    }
    // determine AP capability with iw
    // add the wifi technology to array $networkInterfaces with iw
    // uses the array $wirelessNic for device id to nic name translation
    $deviceInfoList = sysCmd("iw list | sed 's,[ ]\+, ,g' | grep -iE '^Wiphy|Supported interface modes:|* AP$|:$'");
    // the nic names are not listed, only the physical device id's
    $phyDev = '';
    $intMode = false;
    $nic = '';
    foreach ($deviceInfoList as $deviceInfoLine) {
        $deviceInfoLine = ' '.trim($deviceInfoLine);
        if (strpos($deviceInfoLine, 'Wiphy')) {
            $phyDev = trim(explode(' ', trim($deviceInfoLine))[1]);
            if (isset($wirelessNic[$phyDev])) {
                $nic = $wirelessNic[$phyDev];
            } else {
                $nic = '';
            }
        } else if (strpos($deviceInfoLine, 'Supported interface modes:')) {
            // the 'Supported interface modes:' section of the file is terminated with a line containing a colon (:)
            $intMode = true;
        } else if (strpos($deviceInfoLine, '* AP')) {
            if (($nic != '') && ($intMode)) {
                // access point (AP) is listed as a 'Supported interface mode'
                $networkInterfaces[$nic]['apSupported'] = true;
                $phyDev = '';
                $intMode = false;
                $nic = '';
            }
        } else if (strpos($deviceInfoLine, ':')) {
            if (($nic != '') && ($intMode)) {
                // reached the end of the 'Supported interface modes:' section and no access point (AP) listed
                $networkInterfaces[$nic]['apSupported'] = false;
            }
            $intMode = false;
        }
    }
    // determine AP full function is supported
    foreach ($networkInterfaces as $key => $nic) {
        if ($nic['technology'] === 'wifi') {
            $retval = sysCmd("iw phy ".$nic['physical']." info | grep -ci 'interface combinations are not supported'")[0];
            if (!$retval && $nic['apSupported']) {
                $networkInterfaces[$key]['apFull'] = true;
            } else {
                $networkInterfaces[$key]['apFull'] = false;
            }
            unset($retval);
        }
    }
    $redis->set('network_interfaces', json_encode($networkInterfaces));
    $redis->set('translate_mac_nic', json_encode($translateMacNic));
    //
    //
    // add the available networks to array $networkInfo with connman
    // uses the array $translateMacNic for mac to nic name translation
    // uses the array $networkInterfaces for ip address information (and possibly modifies this array)
    //
    // add to the existing array if the time since the last run is less than 6 hours
    // otherwise start with an empty array
    list($nowMicroseconds, $nowSeconds) = explode(" ", microtime());
    $nowSeconds = floatval($nowSeconds);
    if (!$redis->Exists('network_info_time')) {
        $redis->Set('network_info_time', $nowSeconds);
    }
    $previousSeconds = floatval($redis->Get('network_info_time'));
    $hoursSince = floor(($nowSeconds - $previousSeconds)/60/60);
    if ($hoursSince >= 6) {
        // clear the array and save the time
        $networkInfo = array();
        $redis->Set('network_info_time', $nowSeconds);
    } else {
        // use the last array if it exists
        if ($redis->exists('network_info')) {
            $networkInfo = json_decode($redis->Get('network_info'), true);
        } else {
            $networkInfo = array();
            $redis->Set('network_info_time', $nowSeconds);
        }
    }
    // subtract 3 from all network strength values and remove values which go negative
    // all networks which are (re)detected will reset their strength to the actual value
    // the networks which are successively not detected will be shown as weak and eventually be deleted
    foreach ($networkInfo as $key => $network) {
        if (isset($networkInfo[$key]['strength'])) {
            $networkInfo[$key]['strength'] = $networkInfo[$key]['strength'] - 3;
            if ($network['strength'] <= 0) {
                unset($networkInfo[$key]);
            } else {
                $networkInfo[$key]['strengthStars'] = str_repeat(' &#9733', max(1, round($networkInfo[$key]['strength']/10)));
            }
        }
    }
    // always clear the optimise wifi array
    $optimiseWifi = array();
    $accessPoint = $redis->hGet('AccessPoint', 'ssid');
    $accessPointEnabled = $redis->hGet('AccessPoint', 'enable');
    $hiddenCount = 0;
    $networkInterfacesModified = false;
    // get the services
    $services = sysCmd('connmanctl services');
    foreach ($services as $service) {
        unset($security, $strength, $strengthStars);
        $status = strtoupper(trim(substr($service, 0, 4)));
        // in theory ssid should be max 21 characters long, but there are longer ones!
        if (strpos($service, ' ethernet_')) {
            $pos = 25;
        } else if (strpos($service, ' wifi_')) {
            $pos = strpos($service, ' wifi_', 24);
        } else {
            $pos = 25;
        }
        $ssid = trim(substr($service, 4, $pos - 4));
        $connmanString = trim(substr($service, $pos));
        $connmanStringParts = explode('_', $connmanString);
        $technology = $connmanStringParts[0];
        if (!in_array($technology, $process_technology)) {
            // skip technologies not listed
            continue;
        }
        $macAddress = trim($connmanStringParts[1]);
        if (isset($translateMacNic[$macAddress.'_'])) {
            $nic = $translateMacNic[$macAddress.'_'];
            if (($accessPointEnabled) && ($accessPoint === $ssid)) {
                // ssid configured as an AccessPoint, so skip
                continue;
            }
        } else {
            $nic = '000000';
        }
        if ($technology === 'ethernet') {
            // connect wired interface
            if ($networkInterfaces[$nic]['connected']) {
                // do nothing
            } else {
                wrk_netconfig($redis, 'autoconnect-on', $connmanString);
            }
        } else if ($technology === 'wifi') {
            // this is for WiFi
            if ($ssid === '') {
                // when the ssid is empty it is a hidden ssid, so make it unique, there may be more than one
                $ssid = '<Hidden'.++$hiddenCount.'>';
            }
        } else {
            // not Wi-Fi or Wired Ethernet, so skip it (could be Bluetooth, etc.)
            continue;
        }
        $ssidHex = implode(unpack("H*", trim($ssid)));
        // set the deault values for DNS name servers and gateway from
        $networkInfo[$macAddress.'_'.$ssidHex]['primaryDns'] = $networkInterfaces[$nic]['primaryDns'];
        $networkInfo[$macAddress.'_'.$ssidHex]['secondaryDns'] = $networkInterfaces[$nic]['secondaryDns'];
        $networkInterfaces[$nic]['defaultGateway'] = $networkInterfaces[$nic]['defaultGateway'];
        // get the signal strength, security, DNS name servers and gateway from connman
        $connmanLines = sysCmd('connmanctl services '.$connmanString);
        foreach ($connmanLines as $connmanLine) {
            if (strpos(' '.$connmanLine, '.Configuration')) {
                // don't use the configuration lines
                continue;
            }
            $connmanLineParts = explode('=', $connmanLine, 2);
            if (count($connmanLineParts) !=2) {
                // skip the line if it has no value (or '=' charecter)
                continue;
            }
            $entry = ' '.strtolower(trim($connmanLineParts[0]));
            $value = strtolower(trim($connmanLineParts[1], " \t\n\r\0\x0B]["));
            if (strpos($entry, 'security')) {
                $networkInfo[$macAddress.'_'.$ssidHex]['security'] = strtoupper($value);
            } else if (strpos($entry, 'strength')) {
                if ($value) {
                    $strength = $value;
                    $networkInfo[$macAddress.'_'.$ssidHex]['strength'] = $strength;
                    // strength is a value from 1 to 100, genereate 1 to 10 stars
                    $networkInfo[$macAddress.'_'.$ssidHex]['strengthStars'] = str_repeat(' &#9733', max(1, round($strength/10)));
                }
            } else if (strpos($entry, 'nameservers')) {
                if ($value) {
                    $nameservers = explode(',', $value);
                    if (isset($nameservers[0])) {
                        $nameservers[0] = trim($nameservers[0]);
                        if ($nameservers[0]) {
                            if ($networkInfo[$macAddress.'_'.$ssidHex]['primaryDns'] != $nameservers[0]) {
                                $networkInfo[$macAddress.'_'.$ssidHex]['primaryDns'] = $nameservers[0];
                                $networkInfo[$macAddress.'_'.$ssidHex]['secondaryDns'] = '';
                                $networkInterfaces[$nic]['primaryDns'] = $nameservers[0];
                                $networkInterfaces[$nic]['secondaryDns'] = '';
                                $networkInterfacesModified = true;
                            }
                        }
                    }
                    if (isset($nameservers[1])) {
                        $nameservers[1] = trim($nameservers[1]);
                        if ($nameservers[1]) {
                            if ($networkInfo[$macAddress.'_'.$ssidHex]['secondaryDns'] != $nameservers[1]) {
                                $networkInfo[$macAddress.'_'.$ssidHex]['secondaryDns'] = $nameservers[1];
                                $networkInterfaces[$nic]['secondaryDns'] = $nameservers[1];
                                $networkInterfacesModified = true;
                            }
                        }
                    }
                }
            } else if (strpos($entry, 'ipv4')) {
                // pick up the device gateway
                if ($value && strpos(' '.$value, 'gateway')) {
                    $gateway = substr($value, strpos($value, 'gateway'));
                    $gateway = explode('=', $gateway, 2);
                    if (isset($gateway[1])) {
                        $gateway[1] = trim($gateway[1]);
                        if ($gateway[1]) {
                            $gateway = $gateway[1];
                            if (strpos(' '.$gateway, ',')) {
                                $gateway = substr($gateway, 0, strpos($gateway, ','));
                            }
                            if (strpos(' '.$gateway, '=')) {
                                $gateway = substr($gateway, 0, strpos($gateway, '='));
                            }
                            if ($gateway && ($gateway != $networkInterfaces[$nic]['defaultGateway'])) {
                                $networkInfo[$macAddress.'_'.$ssidHex]['defaultGateway'] = $gateway;
                                $networkInterfaces[$nic]['defaultGateway'] = $gateway;
                                $networkInterfacesModified = true;
                            }
                        }
                    }
                } else {
                    $networkInfo[$macAddress.'_'.$ssidHex]['defaultGateway'] = $networkInterfaces[$nic]['defaultGateway'];
                }
                // pick up the device network mask
                if ($value && strpos(' '.$value, 'netmask')) {
                    $netmask = substr($value, strpos($value, 'netmask'));
                    $netmask = explode('=', $netmask, 2);
                    if (isset($netmask[1])) {
                        $netmask[1] = trim($netmask[1]);
                        if ($netmask[1]) {
                            $netmask = $netmask[1];
                            if (strpos(' '.$netmask, ',')) {
                                $netmask = substr($netmask, 0, strpos($netmask, ','));
                            }
                            if (strpos(' '.$netmask, '=')) {
                                $netmask = substr($netmask, 0, strpos($netmask, '='));
                            }
                            if ($netmask && ($netmask != $networkInterfaces[$nic]['ipv4Mask'])) {
                                $networkInfo[$macAddress.'_'.$ssidHex]['ipv4Mask'] = $netmask;
                                $networkInterfaces[$nic]['ipv4Mask'] = $netmask;
                                $networkInterfacesModified = true;
                            }
                        }
                    }
                } else {
                    $networkInfo[$macAddress.'_'.$ssidHex]['ipv4Mask'] = $networkInterfaces[$nic]['ipv4Mask'];
                }
            }
        }
        $networkInfo[$macAddress.'_'.$ssidHex]['ssid'] = $ssid;
        $networkInfo[$macAddress.'_'.$ssidHex]['ssidHex'] = $ssidHex;
        $networkInfo[$macAddress.'_'.$ssidHex]['status'] = $status;
        $networkInfo[$macAddress.'_'.$ssidHex]['connmanString'] = $connmanString;
        $networkInfo[$macAddress.'_'.$ssidHex]['macAddress'] = $macAddress;
        $networkInfo[$macAddress.'_'.$ssidHex]['technology'] = $technology;
        if (isset($security)) {

        }
        if ($nic != '000000') {
            $networkInfo[$macAddress.'_'.$ssidHex]['nic'] = $nic;
        }
        //
        if ($status) {
            $networkInfo[$macAddress.'_'.$ssidHex]['configured'] = true;
            if (strpos(' '.$status, 'A')) {
                $networkInfo[$macAddress.'_'.$ssidHex]['autoconnect'] = true;
            } else {
                $networkInfo[$macAddress.'_'.$ssidHex]['autoconnect'] = false;
            }
            if (strpos(' '.$status, 'O')) {
                $networkInfo[$macAddress.'_'.$ssidHex]['online'] = true;
            } else {
                $networkInfo[$macAddress.'_'.$ssidHex]['online'] = false;
            }
            if (strpos(' '.$status, 'R')) {
                $networkInfo[$macAddress.'_'.$ssidHex]['ready'] = true;
            } else {
                $networkInfo[$macAddress.'_'.$ssidHex]['ready'] = false;
            }
            if (isset($networkInterfaces[$nic]['ipStatus'])) {
                $networkInfo[$macAddress.'_'.$ssidHex]['ipStatus'] = $networkInterfaces[$nic]['ipStatus'];
            }
            if (isset($networkInterfaces[$nic]['ipInfo'])) {
                $networkInfo[$macAddress.'_'.$ssidHex]['ipInfo'] = $networkInterfaces[$nic]['ipInfo'];
            }
            if (isset($networkInterfaces[$nic]['ipv4Address'])) {
                $networkInfo[$macAddress.'_'.$ssidHex]['ipv4Address'] = $networkInterfaces[$nic]['ipv4Address'];
            }
            if (isset($networkInterfaces[$nic]['ipv6Address'])) {
                $networkInfo[$macAddress.'_'.$ssidHex]['ipv6Address'] = $networkInterfaces[$nic]['ipv6Address'];
            }
            if (isset($networkInterfaces[$nic]['ipv4Rest'])) {
                $networkInfo[$macAddress.'_'.$ssidHex]['ipv4Rest'] = $networkInterfaces[$nic]['ipv4Rest'];
            }
            if (isset($networkInterfaces[$nic]['ipv6Rest'])) {
                $networkInfo[$macAddress.'_'.$ssidHex]['ipv6Rest'] = $networkInterfaces[$nic]['ipv6Rest'];
            }
        } else {
            $networkInfo[$macAddress.'_'.$ssidHex]['configured'] = false;
            $networkInfo[$macAddress.'_'.$ssidHex]['autoconnect'] = false;
            $networkInfo[$macAddress.'_'.$ssidHex]['online'] = false;
            $networkInfo[$macAddress.'_'.$ssidHex]['ready'] = false;
        }
        if (($networkInfo[$macAddress.'_'.$ssidHex]['technology'] === 'wifi') &&
                ($networkInfo[$macAddress.'_'.$ssidHex]['configured']) &&
                ($networkInfo[$macAddress.'_'.$ssidHex]['security'] != 'OPEN')) {
            // write the configured wifi networks to an array for autoconnect optimisation
            // autoconnect is never automatically set for OPEN security
            $optimiseWifi[] = array('connmanString' => $connmanString
                , 'strength' => $strength
                , 'macAddress' => $macAddress
                , 'ssidHex' => $ssidHex
                );
        }
    }
    //
    // optimise wifi for the next reboot and the first time after setting up a Wi-Fi network
    // this is done by setting autoconnect on for the best network reception (per nic & ssid combination) and off for the rest
    // most of the time there will only be one network and one wifi nic, so the routine won't do very much other than switch on autoconnect
    // autoconnect is never automatically set for OPEN security
    //
    if ($redis->get('network_autoOptimiseWifi')) {
        $strengthCol  = array_column($optimiseWifi, 'strength');
        $ssidHexCol = array_column($optimiseWifi, 'ssidHex');
        $macAddressCol = array_column($optimiseWifi, 'macAddress');
        array_multisort($strengthCol, SORT_DESC, $ssidHexCol, SORT_ASC, $macAddressCol, SORT_ASC, $optimiseWifi);
        while ($optimiseWifi) {
            // the array has some values, an empty array returns false and ends the loop
            $first = true;
            foreach ($optimiseWifi as $key => $network) {
                if ($first) {
                    // the first one has the strongest signal so enable autoconnect
                    // if this nic and ssid are not currently connected connman will connect it immediately after enabling autoconnect
                    wrk_netconfig($redis, 'autoconnect-on', $network['connmanString']);
                    $macAddress = $network['macAddress'];
                    $ssidHex = $network['ssidHex'];
                    $connmanString = $network['connmanString'];
                    // delete this line from the array
                    unset($optimiseWifi[$key]);
                    $first = false;
                } else {
                    if (($macAddress === $network['macAddress']) || ($ssidHex === $network['ssidHex'])) {
                        // then disable autoconnect on other networks using the same mac address or ssid
                        // most of the time connman retains the existing connections, but in some circumstances
                        // (when 2 Wi-Fi nics are present) it will disconnect and reconnect on-the-fly
                        wrk_netconfig($redis, 'autoconnect-off', $network['connmanString']);
                        // order the networks in the connman list, there are circumstances (when 2 Wi-Fi nics are present)
                        // where connman will act on this on-the-fly optimisation, however the information is lost on reboot
                        sysCmd('connmanctl move-after '.$network['connmanString'].' '. $connmanString);
                        $connmanString = $network['connmanString'];
                        // delete this element from the array
                        unset($optimiseWifi[$key]);
                    }
                }
            }
        }
    }
    //
    $redis->set('network_info', json_encode($networkInfo));
    if ($networkInterfacesModified) {
        $redis->set('network_interfaces', json_encode($networkInterfaces));
    }
    //
    // unlock the scan system
    $redis->Set('lock_wifiscan', 0);
    runelog('--------------------------- returning network interface array ---------------------------');
    return $networkInterfaces;
}

// function to correct a cheap network card which all seem to have the same MAC address (00:e0:4c:53:44:58)
function fix_mac($redis, $nic)
{
    // first check that the MAC address needs to be changed "ip -o -br link | sed 's,[ ]\+, ,g'"
    $response = sysCmd('ip -br link show '.$nic." | sed 's,[ ]\+, ,g'");
    $macCurrent = trim(explode(' ', $response[0])[2]);
    if ($macCurrent != '00:e0:4c:53:44:58') {
        // MAC address does not need to be changed
        return $macCurrent;
    }
    // the MAC address does needs to be changed
    // determine the new MAC address
    if ($redis->hExists('fix_mac', $nic)) {
        // the MAC address was changed in the past so use the same one
        $macNew = $redis->hGet('fix_mac', $nic);
    } else {
        // generate a new random MAC address
        $macNew = implode(':', str_split(substr(md5(mt_rand()), 0, 12), 2));
        // use the first 8 characters of the old MAC address to preserve the vendor code
        $macNew = substr($macCurrent, 0, 8).substr($macNew, 8, 9);
        // save the new MAC address so that the same one will be used in the future
        $redis->hSet('fix_mac', $nic, $macNew);
    }
    // change the MAC address
    sysCmd('ip link set dev '.$nic.' address '.$macNew);
    // construct a systemd unit file to automatically change the MAC address on boot
    $file = '/etc/systemd/system/macfix_'.$nic.'.service';
    // clear the cache otherwise file_exists() returns incorrect values
    clearstatcache(true, $file);
    if ((!file_exists($file)) || (!sysCmd('grep -ihc '.$macNew.' '.$file)[0])) {
        // create the systemd unit file only when it needs to be created
        $fileContent = '# file '.$file."\n"
            .'# some cheap network cards have an identical MAC address for all cards (00:e0:4c:53:44:58)'."\n"
            .'# change it to a fixed (previouly ranomised) address'."\n\n"
            .'[Unit]'."\n"
            .'Description=MAC Address Fix for '.$nic."\n"
            .'Wants=network-pre.target'."\n"
            .'Before=network-pre.target'."\n"
            .'BindsTo=sys-subsystem-net-devices-'.$nic.'.device'."\n"
            .'After=sys-subsystem-net-devices-'.$nic.'.device'."\n\n"
            .'[Service]'."\n"
            .'Type=oneshot'."\n"
            .'ExecStart=/usr/bin/ip link show '.$nic.' | /usr/bin/grep -ci 00:e0:4c:53:44:58 && /usr/bin/ip link set dev '.$nic.' address '.$macNew."\n"
            .'[Install]'."\n"
            .'WantedBy=multi-user.target'."\n";
        // write the file
        $fp = fopen($file, 'w');
        fwrite($fp, $fileContent);
        fclose($fp);
    }
    // enable the service
    sysCmd('systemctl enable macfix_'.$nic);
    return $macNew;
}
// work function to set, reset of check (including start and stop) ashuffle
function wrk_ashuffle($redis, $action = 'check', $playlistName = null)
// Parameter $redis is compulsory
// Parameter $action can have then the values: 'checkcrossfade', 'set', 'reset' or 'check' (default)
// Parameter $playlistName is only used when $action = set - it contains  the name of the playlist (not the filename)
// when $action = 'checkcrossfade' the number of songs to be mainland in the queue is checked and if required corrected
// when $action = 'set' the specified playlist is used as the source for ashuffle
// when $action = 'reset' the complete MPD library is used as the source for ashuffle
// when $action = 'check' conditions will be controlled and on the basis of these conditions ashuffle will be:
//   stopped, started, set or reset
//   this is the only place where ashuffle is started, it is stopped in many places within RuneAudio
//
// shuffle.service has the line:
// ExecStart=/usr/bin/ashuffle -q <queue_length> -f <playlist_filename>
// or
// ExecStart=/usr/bin/ashuffle -q <queue_length>  -e <excluded_selection> --by-album
//  -q is allways present
//  -f is present when randomly playing from a playlist
//  -e is optionally present when randomly playing from the full MPD library
//  --by-album is optionally present when randomly playing from the full MPD library, determined by the redis variable 'globalrandom' 'random_album'
//  <queue_length> is set to 0 or 1, determined by the value of MPD crossfade, when crossfade = 0 then queue_length = 0, otherwise queue_length = 1
//  <playlist_filename> is set to the value in the redis variable 'globalrandom' 'playlist_filename' and the file must exist
//  <excluded_selection> is determined by the redis variable 'globalrandom' 'exclude', if it contains a '-e' or '--exclude' it
//      is used unaltered, otherwise a string is built up with its contents excluding the genre for each space delimited substring
//      e.g. 'film vocal classical' would result in '-e genre film -e genre vocal -e genre classical'
//      which would result in excluding any song which had any one of the words 'film', 'vocal' or 'classical' in its genre metadata
{
    // get the playlist directory
    $playlistDirectory = rtrim(trim($redis->hget('mpdconf', 'playlist_directory')),'/');
    $ashuffleUnitFilename = '/etc/systemd/system/ashuffle.service';
    // to allow crossfade to work with ashuffle, when crossfade is set the queue needs to always have one extra song in the queue
    $retval = sysCmd('mpc crossfade');
    $retval = trim(preg_replace('/[^0-9]/', '', $retval[0]));
    if (strlen($retval)) {
        if ($retval == 0) {
            $queuedSongs = 0;
        } else {
            $queuedSongs = 1;
        }
    } else {
        $queuedSongs = '';
    }
    unset($retval);
    // set up ashuffle tweaks to randomise the ashuffle window size (default = 7) to enhance the randomness and set
    //  the suspend timeout to its redis value (nominally 20ms) to prevent crashes after clearing the queue
    $tweaks = ' -t window-size='.rand(7, 20).' -t suspend-timeout='.$redis->hGet('globalrandom', 'suspend_timeout');
    switch ($action) {
        case 'checkcrossfade':
            // $action = 'checkcrossfade'
            //
            // don't do anything if $queuedSongs has no value, MPD is probably not running, wait until the next time
            if (strlen($queuedSongs)) {
                if ($queuedSongs === 0) {
                    // crossfade = 0 so the number of extra queued songs should be 0
                    if (sysCmd('grep -ic -- '."'".'-q 1'."' '".$ashuffleUnitFilename."'")[0]) {
                        // incorrect value in the ashuffle service file
                        // find the line beginning with 'ExecStart' and in that line replace '-q 1'' with -q 0'
                        sysCmd("sed -i '/^ExecStart/s/-q 1/-q 0/' ".$ashuffleUnitFilename);
                        // reload the service file
                        sysCmd('systemctl daemon-reload');
                        // stop ashuffle if it is running
                        sysCmd('pgrep -x ashuffle && systemctl stop ashuffle');
                    }
                } else if ($queuedSongs > 0) {
                    // crossfade > 0 so the number of extra queued songs should be 1
                    if (sysCmd('grep -ihc -- '."'".'-q 0'."' '".$ashuffleUnitFilename."'")[0]) {
                        // incorrect value in the ashuffle service file
                        // find the line beginning with 'ExecStart' and in that line replace '-q 0'' with -q 1'
                        sysCmd("sed -i '/^ExecStart/s/-q 0/-q 1/' ".$ashuffleUnitFilename);
                        // reload the service file
                        sysCmd('systemctl daemon-reload');
                        // stop ashuffle if it is running
                        sysCmd('pgrep -x ashuffle && systemctl stop ashuffle');
                    }
                }
            }
            break;
        case 'set':
            // $action = 'set'
            //
            if (is_null($playlistName)) {
                // no playlist name has been supplied, just exit
                break;
            }
            if (!strlen($queuedSongs)) {
                $queuedSongs = 0;
            }
            // stop ashuffle and set redis globalrandom to false/off, otherwise it may be restarted automatically
            $redis->hSet('globalrandom', 'enable', '0');
            sysCmd('pgrep -x ashuffle && systemctl stop ashuffle');
            // delete all broken symbolic links in the playlist directory
            sysCmd('find '."'".$playlistDirectory."'".' -xtype l -delete');
            $playlistFilename = $playlistDirectory.'/'.$playlistName.'.m3u';
            // save the playlist and playlist filename
            $redis->hSet('globalrandom', 'playlist', $playlistName);
            $redis->hSet('globalrandom', 'playlist_filename', $playlistFilename);
            // the ashuffle systemd service file needs to explicitly reference the playlist file
            $newArray = wrk_replaceTextLine($ashuffleUnitFilename, '', 'ExecStart=', 'ExecStart=/usr/bin/ashuffle -q '.$queuedSongs.' -f '."'".$playlistFilename."'".$tweaks);
            $fp = fopen($ashuffleUnitFilename, 'w');
            $paramReturn = fwrite($fp, implode("", $newArray));
            fclose($fp);
            unset($newArray);
            // reload the service file
            sysCmd('systemctl daemon-reload');
            // set global random true/on
            $redis->hSet('globalrandom', 'enable', 1);
            // ashuffle gets started automatically when redis globalrandom is set to true/on
            break;
        case 'reset':
            // $action = 'reset'
            //
            if (!strlen($queuedSongs)) {
                $queuedSongs = 0;
            }
            // save current value of redis globalrandom and set it to false/off, otherwise it may be restarted automatically
            $saveGlobalrandom = $redis->hGet('globalrandom', 'enable');
            $redis->hSet('globalrandom', 'enable', '0');
            // Stop ashuffle
            sysCmd('pgrep -x ashuffle && systemctl stop ashuffle');
            // delete all broken symbolic links in the playlist directory
            sysCmd('find '."'".$playlistDirectory."'".' -xtype l -delete');
            // clear the playlist and playlist filename
            $redis->hSet('globalrandom', 'playlist', '');
            $redis->hSet('globalrandom', 'playlist_filename', '');
            // get the excluded songs (convert whitespace to single space and trim)
            $randomExclude = trim(preg_replace('/\s+/', ' ',$redis->hGet('globalrandom', 'exclude')));
            if ($randomExclude) {
                // something has been specified in the exclude string
                if (!strpos(' '.$randomExclude, '--exclude') && !strpos(' '.$randomExclude, '-e')) {
                    // not formatted as an exclude command, assume '-e genre <string>' for
                    //      each space delimited string in the exclude string
                    $randomExcludeArray = explode(' ', $randomExclude);
                    $randomExclude = ' -e genre '.implode( ' -e genre ' , $randomExcludeArray);
                } else {
                    // it is formatted as an exclude command, assume that it is correct
                    $randomExclude = ' '.$randomExclude;
                }
            }
            // get the variable defining random play by album
            if ($redis->hGet('globalrandom', 'random_album')) {
                $ashuffleAlbum = ' --by-album';
            } else {
                $ashuffleAlbum = '';
            }
            unset($retval);
            // the ashuffle systemd service file needs to explicitly exclude the reference the deleted playlist
            $newArray = wrk_replaceTextLine($ashuffleUnitFilename, '', 'ExecStart=', 'ExecStart=/usr/bin/ashuffle -q '.$queuedSongs.$tweaks.$ashuffleAlbum.$randomExclude);
            $fp = fopen($ashuffleUnitFilename, 'w');
            $paramReturn = fwrite($fp, implode("", $newArray));
            fclose($fp);
            unset($newArray);
            // reload the service file
            sysCmd('systemctl daemon-reload');
            // set redis globalrandom to the saved value
            $redis->hSet('globalrandom', 'enable', $saveGlobalrandom);
            // ashuffle gets started automatically when redis globalrandom is set to true/on
            break;
        default:
            // $action = 'check' (or any other value)
            //
            // first check that shuffle is running with/without a playlist
            // check that any randomplay playlist still exists
            $playlistFilename = $redis->hGet('globalrandom', 'playlist_filename');
            // clear the cache otherwise file_exists() returns an incorrect value
            clearstatcache(true, $playlistFilename);
            if (($playlistFilename != '') && !file_exists($playlistFilename)) {
                //  the playlist file no longer exits, reset ashuffle
                wrk_ashuffle($redis, 'reset');
                // it will reset the playlist filename
                $playlistFilename = $redis->hGet('globalrandom', 'playlist_filename');
            }
            // get the playlist name, it is not normally passed to the check function
            $playlistName = $redis->hGet('globalrandom', 'playlist');
            if ($playlistFilename === '') {
                // ashuffle should not have a playlist filename in its systemd unit file
                if (sysCmd('grep -ic '."'".' -f '."' '".$ashuffleUnitFilename."'")[0]) {
                    // play from file present, reset ashuffle
                    wrk_ashuffle($redis, 'reset');
                }
            } else {
                // ashuffle should play from the file in its systemd unit file
                if (!sysCmd('grep -ihc '."'".$playlistFilename."' '".$ashuffleUnitFilename."'")[0]) {
                    // play from the filename not present, set ashuffle to play from the filename
                    wrk_ashuffle($redis, 'set', $playlistName);
                }
            }
            if (!strlen($queuedSongs)) {
                $queuedSongs = 0;
            }
            $moveNr = $queuedSongs + 1;
            // start Global Random if enabled - check continually, ashuffle get stopped for lots of reasons
            // stop Global Random if disabled - there are also other conditions when ashuffle must be stopped
            // ashuffle also seems to be a little bit unstable, it occasionally unpredictably crashes
            // this is the only place where ashuffle it is started
            // first check that it is enabled, not waiting for auto play to initialise and there are some songs to play
            if (($redis->hGet('globalrandom', 'enable')) && (!$redis->hGet('globalrandom', 'wait_for_play'))) {
                // count the number of NAS Mounts
                $nasmounts = count(scandir("/mnt/MPD/NAS"))-2;
                // count the number of USB Mounts
                $usbmounts = count(scandir("/mnt/MPD/USB"))-2;
                // count the number of local storage files
                $localstoragefiles = count(scandir("/mnt/MPD/LocalStorage"))-2;
                // get the active player
                $activePlayer = $redis->get('activePlayer');
                // check if MPD is not playing, playing a single song, repeating a song or randomly playing the current playlist
                if ($activePlayer != 'MPD') {
                    // active player not MPD, ashuffle should not be running
                    $mpdSingleRepeatRandomStopped = false;
                } else {
                    $mpcStatus = ' '.trim(strtolower(preg_replace('!\s+!', ' ', sysCmd('mpc status | xargs')[0])));
                    if (!strpos($mpcStatus, 'playing')) {
                        // not playing
                        $queueEmpty = trim(sysCmd('mpc move '.$moveNr.' '.$moveNr.' || echo 1')[0]);
                        // note: 'mpc move 1 1 || echo 1' (or 'mpc move 2 2 || echo 1') will do nothing and will also return
                        // nothing when the first/second position in the queue contains a song, so:
                        //  returning nothing is false >> songs in the queue
                        //  otherwise true >> queue empty
                        if ($queueEmpty) {
                            // there is nothing in the queue, so ashuffle should be running to add the first songs
                            // sometimes ashuffle crashes after clearing the queue, this should restart it
                            $mpdSingleRepeatRandomStopped = false;
                        } else {
                            // there are songs in the queue, the the user has just pressed stop or pause, ashuffle should not be running
                            $mpdSingleRepeatRandomStopped = true;
                        }
                    } else if (strpos($mpcStatus, 'repeat: on')) {
                        // repeat on, ashuffle should not be running
                        $mpdSingleRepeatRandomStopped = true;
                    } else if (strpos($mpcStatus, 'random: on')) {
                        // random on, ashuffle should not be running
                        $mpdSingleRepeatRandomStopped = true;
                    } else if (strpos($mpcStatus, 'single: on')) {
                        // single on, ashuffle should not be running
                        $mpdSingleRepeatRandomStopped = true;
                    } else {
                        // ashuffle should be running
                        $mpdSingleRepeatRandomStopped = false;
                    }
                    unset($mpcStatus, $queueEmpty);
                }
                $retval = sysCmd('systemctl is-active ashuffle');
                if ($retval[0] == 'active') {
                    // ashuffle already started
                    if ((($nasmounts == 0) && ($usbmounts == 0) && ($localstoragefiles == 0)) || ($activePlayer != 'MPD') || ($mpdSingleRepeatRandomStopped)) {
                        // nothing to play or active player is not MPD or MPS stopped, MPD single, repeat or random is set, so stop ashuffle
                        sysCmd('pgrep -x ashuffle && systemctl stop ashuffle');
                    }
                } else {
                    // ashuffle not started
                    if ((($nasmounts == 0) && ($usbmounts == 0) && ($localstoragefiles == 0)) || ($activePlayer != 'MPD') || ($mpdSingleRepeatRandomStopped)) {
                        // nothing to play or active player is not MPD or MPS stopped, MPD single, repeat or random is set, do nothing
                    } else {
                        // start ashuffle
                        // seems to be a bug somewhere in MPD
                        // if ashuffle is started too quickly it queues many, many (far TOO many!) songs in the queue before MPD gets round to start playing one
                        // wait until mpd has been running for a while before starting ashuffle
                        // get the elapsed time that MPD has been running in seconds
                        $retval = trim(sysCmd('ps -C mpd -o etimes=')[0]);
                        if (isset($retval) && strlen($retval)) {
                            // a value has been returned
                            $mpd_uptime = intval($retval);
                        } else {
                            // no value, MPD is probably not running
                            $mpd_uptime = 0;
                        }
                        if ($mpd_uptime > intval($redis->hGet('globalrandom', 'start_delay'))) {
                            // remove any invalid symlinks in the playlist directory
                            sysCmd('find '."'".$playlistDirectory."'".' -xtype l -delete');
                            // check that the queued songs based on crossfade is set correctly
                            wrk_ashuffle($redis, 'checkcrossfade');
                            sysCmd('pgrep -x ashuffle || systemctl start ashuffle');
                            sysCmdAsync('nice --adjustment=2 /var/www/command/rune_prio nice');
                        }
                    }
                }
            } else {
                // random play is switched off or it is waiting to play, stop it if it is running
                sysCmd('pgrep -x ashuffle && systemctl stop ashuffle');
            }
    }
}

// work function to check the MPD logfile size
function wrk_mpdLog($redis, $logMax = null)
// get the location and name of the MPD logfile from redis
// check its size, when greater than $filesizeMax delete it and inform MPD to create a new one
{
    if (is_null($logMax)) {
        return;
    } else {
        $logSizeMax = floatval($logMax);
    }
    $logFile = $redis->hGet('mpdconf', 'log_file');
    // clear the static cache otherwise the file_exists() and filesize() return incorrect values
    clearstatcache(true, $logFile);
    // debug
    // $redis->hset('wrk_mpdLog', 'logSizeMax', $logSizeMax);
    // $redis->hset('wrk_mpdLog', 'logFile', $logFile);
    if (file_exists($logFile)) {
        // its there, get the size
        $logSize = floatval(filesize($logFile));
        // debug
        // $redis->hset('wrk_mpdLog', 'logSize', $logSize);
        if ($logSize >= $logSizeMax) {
            // delete the file
            sysCmd('rm '."'".$logFile."'");
            // commit and purge the buffers
            sysCmd('sync');
            // use systemctl/pkill to send the SIGHUP signal to tell MPD to recreate/reopen the log file
            // sysCmd('systemctl kill -s HUP mpd');
            sysCmd('pkill -HUP mpd');
        }
    } else {
        // file not found
        // if the file is not there MPD failed to restart writing to it the last time
        // commit and purge the buffers
        sysCmd('sync');
        // use systemctl or pkill to send the SIGHUP signal to tell MPD to recreate/reopen the log file
        sysCmd('systemctl kill -s HUP mpd');
        // sysCmd('pkill -HUP mpd');
    }
}
// function to check if a request about a subject is the first one since reboot
function is_firstTime($redis, $subject)
// returns true or false
// true when this is the first time this routing has been called with this subject since a reboot/boot
// false when this routine has previously been called with this subject since a reboot/boot
{
    // the first version of this function used a redis variable 'first_time' to store the
    // subject together with a boot timestamp based on 'uptime -s'
    // this will not work consistently because the boot timestamp gets moved after a nts timesync
    // the new method uses a file called '/tmp/<subject>.firsttime'
    // the /tmp directory is a tmpfs memory disk which is recreated at each reboot/boot
    // the existence of the file '/tmp/<subject>.firsttime' determines the result
    $fileName = '/tmp/'.trim($subject).'.firsttime';
    // clear the static cache otherwise the file_exists() returns an incorrect value
    clearstatcache(true, $fileName);
    if (file_exists($fileName)) {
        // the file exists so not the first time
        $returnVal = false;
    } else {
        // the file does not exist so always first time true
        $returnVal = true;
        // create the file
        touch($fileName);
    }
    return $returnVal;
}
// function to check and correct the number of active MPD outputs
function wrk_check_MPD_outputs($redis)
// check that MPD only has one output enabled
// it is possible that stream output has been defined which is always active, so be careful
// exclude the stream output when counting the enabled output's, there should then only be one enabled output
{
    // get the number of enabled outputs
    $retval = sysCmd('mpc outputs | grep -vi _stream | grep -ci enabled');
    $countMpdEnabled = $retval[0];
    if ($countMpdEnabled != 1) {
        // none or more than one outputs enabled
        $outputs = sysCmd('mpc outputs | grep -i output');
        $countMpdOutput = count($outputs);
        if ($countMpdOutput == 1) {
            // only one output device so enable it
            sysCmd("mpc enable only 1");
        } else {
            // more than one output device available
            // set the enabled counter to zero
            $countMpdEnabled = 0;
            // walk through the outputs
            foreach ( $outputs as $output) {
                $outputParts = explode(' ', $output, 3);
                // $outputParts[0] = 'Output' (can be disregarded), $outputParts[1] = <the output number> & $outputParts[2] = <the rest of the information>
                $outputParts[2] = strtolower($outputParts[2]);
                if (strpos($outputParts[2], 'bcm2835') || strpos($outputParts[2], 'hdmi')) {
                    // its a 3,5mm jack or hdmi output, so disable it, don't count it
                    sysCmd('mpc disable '.$outputParts[1]);
                    // save the number of the last one
                    $lastOutput = $outputParts[1];
                } else if (strpos($outputParts[2], 'stream')) {
                    // its a streamed output, so enable it, don't count it
                    sysCmd('mpc enable '.$outputParts[1]);
                } else {
                    // its an audio card, USB DAC, fifo or pipe output
                    if ($countMpdEnabled == 0) {
                        // its the first one, enable it and count it
                        sysCmd('mpc enable '.$outputParts[1]);
                        $countMpdEnabled++;
                    } else {
                        // its not the first one, disable it, don't count it
                        sysCmd('mpc disable '.$outputParts[1]);
                    }
                }
            }
            // the first audio card, USB DAC, fifo or pipe output should now have been enabled
            // if applicable the streaming output is also enabled
            // the rest are disabled
            if ($countMpdEnabled == 0) {
                // no output enabled, there is more than one (or no) outputs available, no audio cards, USB DACs, fifo or pipe output detected
                // so enable the 3,5mm output (this may not exist, that's OK)
                // old style name for older Linux versions
                sysCmd("mpc enable 'bcm2835 ALSA_1'");
                // new style name for current Linux versions
                sysCmd("mpc enable 'bcm2835 Headphones'");
                // check that we have one connected, if not, enable the saved disabled output if that exists
                // exclude any stream output when counting the enabled output's
                $retval = sysCmd('mpc outputs | grep -vi _stream | grep -ci enabled');
                $countMpdEnabled = $retval[0];
                if (($countMpdEnabled == 0) && isset($lastOutput)) {
                    sysCmd('mpc enable '.$lastOutput);
                }
            }
            // get the name of the enabled interface for the UI
            $retval = sysCmd('mpc outputs | grep -vi _stream | grep -i enabled');
            if (isset($retval[0])) {
                $retval = explode('(', $retval[0]);
                if (isset($retval[1])) {
                    $retval = explode(')', $retval[1]);
                    $enabled = trim($retval[0]);
                    if ($enabled) {
                        $redis->set('ao', $enabled);
                    }
                }
            }
        }
    }
}


// function to set the mpd volume to the last volume set via the UI
function set_last_mpd_volume($redis)
// set the mpd volume to the last value set via the UI, if a value is available and volume control is enabled
// the streaming services can change the alsa volume, we want to change it back to the last set value
{
    if (($redis->exists('lastmpdvolume')) && ($redis->hGet('mpdconf', 'mixer_type') != 'disabled')) {
        $lastmpdvolume = $redis->get('lastmpdvolume');
        if ($lastmpdvolume && is_numeric($lastmpdvolume) && ($lastmpdvolume >= 0) && ($lastmpdvolume <= 100)) {
            $retries_volume = 20;
            do {
                // retry getting the volume until MPD is up and returns a valid entry
                $retval = sysCmd('mpc volume | grep "volume:" | xargs');
                if (!$retval[0]) {
                    // no response
                    sleep(2);
                    continue;
                }
                $retval = explode(' ',trim(preg_replace('!\s+!', ' ', $retval[0])));
                if (!isset($retval[1])) {
                    // invalid response
                    sleep(2);
                    continue;
                }
                if ($retval[1] === 'n/a') {
                    // something wrong, mismatch between redis and mpd volume 'disabled' values, give up
                    $retries_volume = 0;
                    continue;
                }
                // strip any non-numeric values from the string
                $mpdvolume = trim(preg_replace('/[^0-9]/', '', $retval[1]));
                // careful: the volume control works in steps so the return value after stetting it may not be exactly the
                //  same as the requested value
                // use a soft increase/decrease when the difference is more than 4%, otherwise directly set the last saved value
                if ($mpdvolume && is_numeric($mpdvolume) && ($mpdvolume >= 0) && ($mpdvolume <= 100)) {
                    // a valid current volume has been returned
                    if (abs($mpdvolume - $lastmpdvolume) > 4) {
                        // set the mpd volume, do a soft increase/decrease
                        $setvolume = $mpdvolume - round((($mpdvolume-$lastmpdvolume)/2), 0, PHP_ROUND_HALF_UP);
                        $retval = sysCmd('mpc volume '.$setvolume);
                        // sleep 1 second before looping
                        sleep(1);
                    } else {
                        // set the mpd volume directly
                        $retval = sysCmd('mpc volume '.$lastmpdvolume.' | grep "volume:" | xargs');
                        $retval = explode(' ',trim(preg_replace('!\s+!', ' ', $retval[0])));
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
        }
    }
}
