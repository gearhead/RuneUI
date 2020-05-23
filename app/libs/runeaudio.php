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
                // ui_notify('MPD open error: '.$sock.'','Greeting response = '.$header);
                closeMpdSocket($sock);
                return false;
            }
            runelog("[open][".$sock['resource']."]\t>>>>>> OPEN MPD SOCKET - Greeting response: ".$header."<<<<<<",'');
            return $sock;
        } else {
            runelog("[error][".$sock['resource']."]\t>>>>>> MPD SOCKET ERROR: ".socket_last_error($sock['resource'])." <<<<<<",'');
            // ui_notify('MPD sock: '.$sock.'','socket error = '.socket_last_error($sock));
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
                // ui_notify('MPD open error: '.$sock.'','Greeting response = '.$header);
                closeMpdSocket($sock);
                return false;
            }
            runelog("[open][".$sock."]\t<<<<<<<<<<<< OPEN MPD SOCKET ---- MPD greeting response: ".$header.">>>>>>>>>>>>",'');
            return $sock;
        } else {
            runelog("[error][".$sock."]\t<<<<<<<<<<<< MPD SOCKET ERROR: ".socket_strerror(socket_last_error($sock))." >>>>>>>>>>>>",'');
            // ui_notify('MPD sock: '.$sock.'','socket error = '.socket_last_error($sock));
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
            // ui_notify('SPOP sock: '.$sock.'','socket error = '.socket_last_error($sock));
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
            // ui_notify('SPOP sock: '.$sock.'','socket error = '.socket_last_error($sock));
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

/*
class globalRandom extends Thread
{
    // mpd status
    public $status;

    public function __construct($status)
    {
        $this->status = $status;
    }

    public function run()
    {
        $mpd = openMpdSocket('/run/mpd/socket', 0);
            // if ($this->status['consume'] == 0 OR $this->status['random'] == 0) {
            if ($this->status['random'] == 0) {
                // sendMpdCommand($mpd,'consume 1');
                sendMpdCommand($mpd,'random 1');
            }
            $path = randomSelect($mpd);
            if ($path) {
                addToQueue($mpd,$path);
                runelog("global random call",$path);
                ui_notify('Global Random Mode', htmlentities($path,ENT_XML1,'UTF-8').' added to current Queue');
            }
        closeMpdSocket($mpd);
    }
}
*/

function randomSelect($sock)
{
    $songs = browseDB($sock, 'globalrandom');
    srand((float) microtime() * 10000000);
    $randkey = array_rand($songs);
    return $songs[$randkey]['file'];
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
            $retval = sysCmd('cat /proc/asound/card?/pcm?p/sub?/hw_params | grep "rate: "');
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
        // but only when the filename is available and mediainfo is installed
        // ignore any line returned by mpd status containing 'updating'
        $status = sysCmd('mpc status | grep -vi updating');
        // bit rate
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
        // if ((($plistArray['??'] == '0') || ($plistArray['??'] == '')) && (count($status) == 3)) {
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

function _parseOutputsResponse($input, $active)
{
    if (is_null($input)) {
        return null;
    } else {
        $response = preg_split("/\r?\n/", $input);
        $outputs = array();
        $linenum = 0;
        $i = -1;
        foreach($response as $line) {
            if ($linenum % 3 == 0) {
                $i++;
            }
        if (!empty($line)) {
        $value = explode(':', $line);
        $outputs[$i][$value[0]] = trim($value[1]);
            if (isset($active)) {
                if ($value[0] == 'outputenabled' && $outputs[$i][$value[0]] == 1) {
                    $active = $i;
                }
            }
        } else {
            unset($outputs[$i]);
        }
            $linenum++;
        }
    }
    if (isset($active)) {
        return $active;
    } else {
        return $outputs;
    }
}

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
runelog('pushFile(): filepath', $filepath);
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
            $hash = md5_file('/etc/netctl/eth0');
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
            // modify bootsplash on/off setting in /boot/config.txt
            $file = '/boot/config.txt';
            // replace the line with 'disable_splash='
            $newArray = wrk_replaceTextLine($file, '', 'disable_splash=', 'disable_splash='.$args);
            // Commit changes to /boot/config.txt
            $fp = fopen($file, 'w');
            $return = fwrite($fp, implode("", $newArray));
            fclose($fp);
            break;
        case 'zoomfactor':
            // modify the zoom factor in /etc/X11/xinit/start_chromium.sh
            $file = '/etc/X11/xinit/start_chromium.sh';
            // replace the line with 'force-device-scale-factor='
            $newArray = wrk_replaceTextLine($file, '', 'force-device-scale-factor', 'chromium --app=http://localhost --start-fullscreen --force-device-scale-factor='.$args);
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
    if (md5_file($file) == md5_file($newfile)) {
        // nothing has changed, set avahiconfchange off
        $redis->set('avahiconfchange', 0);
        syscmd('rm -f '.$newfile);
    } else {
        // avahi configuration has changed, set avahiconfchange on
        $redis->set('avahiconfchange', 1);
        syscmd('cp '.$newfile.' '.$file);
        syscmd('rm -f '.$newfile);
        // also modify /etc/hosts replace line beginning with 127.0.0.1, sed is fastest
        syscmd('sed -i "/^127.0.0.1/c\127.0.0.1       localhost localhost.localdomain '.$hostname.'.local '.$hostname.'" /etc/hosts');
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
    if ($bktype === 'dev') {
        $filepath = "/srv/http/tmp/totalbackup_".date("Y-m-d").".tar.gz";
        $cmdstring = "rm -f /srv/http/tmp/totalbackup_* &> /dev/null;".
            " redis-cli save;".
            " bsdtar -czpf ".$filepath.
            " /etc".
            " /mnt/MPD/Webradio".
            " /var/lib/redis/rune.rdb".
            " /var/lib/mpd".
            " ".$redis->hGet('mpdconf', 'db_file').
            " ".$redis->hGet('mpdconf', 'sticker_file').
            " ".$redis->hGet('mpdconf', 'playlist_directory').
            " ".$redis->hGet('mpdconf', 'state_file').
            " /boot/config.txt".
            " /boot/cmdline.txt".
            " /var/www".
            "";
    } else {
        $filepath = "/srv/http/tmp/backup_".date("Y-m-d").".tar.gz";
        $cmdstring = "rm -f /srv/http/tmp/backup_* &> /dev/null;".
            " redis-cli save;".
            " bsdtar -czpf ".$filepath.
            " --exclude /etc/netctl/examples".
            " /etc/netctl".
            " /mnt/MPD/Webradio".
            " /var/lib/redis/rune.rdb".
            " ".$redis->hGet('mpdconf', 'db_file').
            " ".$redis->hGet('mpdconf', 'sticker_file').
            " ".$redis->hGet('mpdconf', 'playlist_directory').
            " ".$redis->hGet('mpdconf', 'state_file').
            " /etc/mpd.conf".
            " /etc/mpdscribble.conf".
            " /etc/spop".
            " /boot/config.txt".
            "";
        if (file_exists('/etc/shairport-sync.conf')) {
            $cmdstring .= " /etc/shairport-sync.conf";
        }
        if (file_exists('/etc/chrony.conf')) {
            $cmdstring .= " /etc/chrony.conf";
        }
        if (file_exists('/etc/nsswitch.conf')) {
            $cmdstring .= " /etc/nsswitch.conf";
        }
        if (file_exists('/etc/samba/smb-dev.conf')) {
            $cmdstring .= " /etc/samba/smb-dev.conf";
        }
        if (file_exists('/etc/samba/smb-prod.conf')) {
            $cmdstring .= " /etc/samba/smb-prod.conf";
        }
        if (file_exists('/etc/hostapd/hostapd.conf')) {
            $cmdstring .= " /etc/hostapd/hostapd.conf";
        }
        if (file_exists('/etc/upmpdcli.conf')) {
            $cmdstring .= " /etc/upmpdcli.conf";
        }
    }
    sysCmd($cmdstring);
    return $filepath;
}


function wrk_opcache($action, $redis)
{
// debug
runelog('wrk_opcache ', $action);
    switch ($action) {
        case 'prime':
            opcache_reset();
            if ($redis->get('opcache') == 1) sysCmd('curl http://127.0.0.1/command/cachectl.php?action=prime');
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
            if (isset($args->{'enabled'})) {
                $redis->hSet('AccessPoint', 'enabled', $args->{'enabled'});
            } else {
                $redis->hSet('AccessPoint', 'enabled', 0);
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

function wrk_netconfig($redis, $action, $args = null, $configonly = null)
{
    $return = array();
    // nics blacklist
    $excluded_nics = array('ifb0', 'ifb1', 'p2p0', 'bridge');
    $updateh = 0;
    switch ($action) {
        case 'setnics':
            // clear cache - redis, filesystem and php
            $redis->save();
            sysCmd("sync");
            clearstatcache();
            // flush nics Redis hash table
            $transaction = $redis->multi();
            $transaction->del('nics');
            $interfaces = sysCmd("ip addr |grep \"BROADCAST,\" |cut -d':' -f1-2 |cut -d' ' -f2");
            $interfaces = array_diff($interfaces, $excluded_nics);
            foreach ($interfaces as $interface) {
                $ip = sysCmd("ip addr list ".$interface." |grep \"inet \" |cut -d' ' -f6|cut -d/ -f1");
                // after setting a fixed IP address the existing address stays valid until it is no longer used and the DHCP lease expires
                // in this situation both IP addresses are valid for a given interface
                // when more than one IP address is valid the following code determines which one to use
                // the first IP address is the default, but if one matches a predefined fixed address then that one is used
                $ipidx = 0;
                $countip = count($ip);
                if ($countip > 1) {
                    // more than one valid IP address for this interface detected
                    // determine the fixed IP addresses (if any)
                    $command = 'cat /etc/netctl/* | grep -i "Address=(" | cut -d "'."'".'" -f 2 | cut -d "/" -f 1';
                    $fixedip = sysCmd($command);
                    // use another IP address if it matches one set up as a fixed IP address
                    for ($j = 0; $j < $countip; $j++) {
                        // report each valid IP address in the UI
                        ui_notify_async('More than one valid IP address for '.$interface, $ip[$j].'\n');
                        for ($i = 0, $countfixedip = count($fixedip); $i < $countfixedip; $i++) {
                            if ($ip[$j] == $fixedip[$i]) {
                                $ipidx = $j;
                            }
                        }
                    }
                }
                //***
                // KEW: Do we need to assume this may be CIDR & may be Netmask notatation?
                $netmask = sysCmd("ip addr list ".$interface." |grep \"inet \" |cut -d' ' -f6|cut -d/ -f2");
                if (isset($netmask[$ipidx])) {
                    //$netmask = netmask($netmask[$ipidx]);
                    $netmask = net_CidrToNetmask($netmask[$ipidx]);
                } else {
                    unset($netmask);
                }
                $gw = sysCmd("route -n |grep \"0.0.0.0\" |grep \"UG\" |cut -d' ' -f10");
                $dns = sysCmd("cat /etc/resolv.conf |grep \"nameserver\" |cut -d' ' -f2");
                $type = sysCmd("iwconfig ".$interface." 2>&1 | grep \"no wireless\"");
                runelog('interface type', (isset($type[0]) ? $type[0] : NULL));
                // if (empty(sysCmd("iwlist ".$interface." scan 2>&1 | grep \"Interface doesn't support scanning\""))) {
                if (empty($type[0])) {
                    $speed = sysCmd("iwconfig ".$interface." 2>&1 | grep 'Bit Rate' | cut -d '=' -f 2 | cut -d ' ' -f 1-2");
                    //$currentSSID = sysCmd("iwconfig ".$interface." | grep 'ESSID' | cut -d ':' -f 2 | cut -d '\"' -f 2");
                    $currentSSID = sysCmd("iwconfig ".$interface." | grep 'ESSID' | cut -d ':' -f 2 | cut -d '\"' -f 2");
                    $transaction->hSet('nics',
                        $interface,
                        json_encode(array(
                            'ip' => $ip[$ipidx],
                            'netmask' => $netmask,
                            'gw' => (isset($gw[0]) ? $gw[0] : null),
                            'dns1' => (isset($dns[0]) ? $dns[0] : null),
                            'dns2' => (isset($dns[1]) ? $dns[1] : null),
                            'speed' => (isset($speed[0]) ? $speed[0] : null),
                            'wireless' => 1,
                            'currentssid' => $currentSSID[0])));
                    //// $scanwifi = 1;
                } else {
                    $speed = sysCmd("ethtool ".$interface." 2>&1 | grep -i speed | cut -d':' -f2");
                    $transaction->hSet('nics',
                        $interface,
                        json_encode(array(
                            'ip' => $ip[$ipidx],
                            'netmask' => $netmask,
                            'gw' => (isset($gw[0]) ? $gw[0] : null),
                            'dns1' => (isset($dns[0]) ? $dns[0] : null),
                            'dns2' => (isset($dns[1]) ? $dns[1] : null),
                            'speed' => (isset($speed[0]) ? $speed[0] : null),
                            'wireless' => 0)));
                }
            }
            $transaction->exec();
            break;
        case 'getnics':
            $gw = array();
            $dns = array();
            $speed = array();
            // clear cache - filesystem and php
            sysCmd("sync");
            clearstatcache();
            $interfaces = sysCmd("ip addr |grep \"BROADCAST,\" |cut -d':' -f1-2 |cut -d' ' -f2");
            $interfaces = array_diff($interfaces, $excluded_nics);
            foreach ($interfaces as $interface) {
                $ip = sysCmd("ip addr list ".$interface." |grep \"inet \" |cut -d' ' -f6|cut -d/ -f1");
                // after setting a fixed IP address the existing address stays valid until it is no longer used and the DHCP lease expires
                // in this situation both IP addresses are valid for a given interface
                // when more than one IP address is valid the following code determines which one to use
                // the first IP address is the default, but if one matches a predefined fixed address then that one is used
                $ipidx = 0;
                $countip = count($ip);
                if ($countip > 1) {
                    // more than one valid IP address for this interface detected
                    // determine the fixed IP addresses (if any)
                    $command = 'cat /etc/netctl/* | grep -i "Address=(" | cut -d "'."'".'" -f 2 | cut -d "/" -f 1';
                    $fixedip = sysCmd($command);
                    // use another IP address if it matches one set up as a fixed IP address
                    for ($j = 0; $j < $countip; $j++) {
                        // report each valid IP address in the UI
                        ui_notify_async('More than one valid IP address for '.$interface, $ip[$j].'\n');
                        for ($i = 0, $countfixedip = count($fixedip); $i < $countfixedip; $i++) {
                            if ($ip[$j] == $fixedip[$i]) {
                                $ipidx = $j;
                            }
                        }
                    }
                }
                $netmask = sysCmd("ip addr list ".$interface." |grep \"inet \" |cut -d' ' -f6|cut -d/ -f2");
                if (isset($netmask[$ipidx]) && isset($ip[$ipidx])) {
                    //$netmask = "255.255.255.0";//netmask($netmask[$ipidx]);
                    //$netmask = netmask($netmask[$ipidx]);
                    $netmask = net_CidrToNetmask($netmask[$ipidx]);
                } else {
                    unset($netmask);
                }
                if (isset($netmask)) {
                    $gw = sysCmd("route -n |grep \"0.0.0.0\" |grep \"UG\" |cut -d' ' -f10");
                    $dns = sysCmd("cat /etc/resolv.conf |grep \"nameserver\" |cut -d' ' -f2");
                }
                $type = sysCmd("iwconfig ".$interface." 2>&1 | grep \"no wireless\"");
                if (empty($type[0])) {
                    $speed = sysCmd("iwconfig ".$interface." 2>&1 | grep 'Bit Rate' | cut -d ':' -f 2 | cut -d ' ' -f 1-2");
                    $currentSSID = sysCmd("iwconfig ".$interface." | grep 'ESSID' | cut -d ':' -f 2 | cut -d '\"' -f 2");
                    $actinterfaces[$interface] = (object) [
                        'ip' => (isset($ip[$ipidx]) ? $ip[$ipidx] : null),
                        'netmask' => (isset($netmask) ? $netmask : null),
                        'gw' => (isset($gw[0]) ? $gw[0] : null),
                        'dns1' => (isset($dns[0]) ? $dns[0] : null),
                        'dns2' => (isset($dns[1]) ? $dns[1] : null),
                        'speed' => (isset($speed[0]) ? $speed[0] : null),
                        'wireless' => 1,
                        'currentssid' => (isset($currentSSID[0]) ? $currentSSID[0] : null)
                        ];

                    $redis->hSet('nics', $interface,
                        json_encode(array(
                            'ip' => (isset($ip[$ipidx]) ? $ip[$ipidx] : null),
                            'netmask' => (isset($netmask) ? $netmask : null),
                            'gw' => (isset($gw[0]) ? $gw[0] : null),
                            'dns1' => (isset($dns[0]) ? $dns[0] : null),
                            'dns2' => (isset($dns[1]) ? $dns[1] : null),
                            'speed' => (isset($speed[0]) ? $speed[0] : null),
                            'wireless' => 1,
                            'currentssid' => (isset($currentSSID[0]) ? $currentSSID[0] : null)
                            )));
                } else {
                    $speed = sysCmd("ethtool ".$interface." 2>&1 | grep -i speed | cut -d':' -f2");
                    $actinterfaces[$interface] = (object) ['ip' => $ip[$ipidx], 'netmask' => $netmask, 'gw' => $gw[0], 'dns1' => $dns[0], 'dns2' => $dns[1], 'speed' => $speed[0], 'wireless' => 0];
                    $redis->hSet('nics', $interface , json_encode(array('ip' => $ip[$ipidx], 'netmask' => $netmask, 'gw' => $gw[0], 'dns1' => $dns[0], 'dns2' => $dns[1], 'speed' => $speed[0],'wireless' => 0)));
                }
            }
            return $actinterfaces;
            break;
        case 'getstoredwlans':
            $wlans = array();
            $connected = 0;
            sysCmd('/www/command/refresh_nics');
            $wlans_profiles = json_decode($redis->Get('stored_profiles'));
            foreach ($wlans_profiles as $profile) {
                runelog('  Get stored wlan profiles:'.$profile);

                 //*** Does this work? where do we get $nicdetail??   https://github.com/RuneAudio/RuneUI/blob/dev/app/libs/runeaudio.php#L1460
                 //if ($nicdetail->currentssid === $profile) {
                 //    $connected = 1;
                 //} else {
                 //    $connected = 0;
                 //}
                $wlans[] = json_encode(array('ssid' => $profile, 'encryption' => 'on', 'connected' => $connected, 'storedprofile' => 1));
            }
            return $wlans;
            //break;
        case 'writecfg':
            // ArchLinux netctl config for wired ethernet
                $nic = "Description='".$args->name." connection'\n";
                $nic .= "Interface=".$args->name."\n";
            if ($args->wireless === '1') {
                // Wireless configuration
                $nic .= "Connection=wireless\n";
                $nic .= "Security=wpa-configsection\n";
            } else {
                // Wired configuration
                $nic .= "ForceConnect=yes\n";
                $nic .= "SkipNoCarrier=yes\n";
                $nic .= "Connection=ethernet\n";
            }
            if ($args->dhcp === '1') {
                // DHCP configuration
                $nic .= "IP=dhcp\n";
                // Prepare data object for Redis record
                $dhcpargs = array( 'name' => $args->name, 'dhcp' => $args->dhcp );
                $dhcpargs = (object) $args;
            } else {
                // STATIC configuration
                $nic .= "AutoWired=yes\n";
                $nic .= "ExcludeAuto=no\n";
                $nic .= "IP=static\n";
                // KEW
                // Need address in CIDR notation 0.0.0.0/0
                $cidr = net_NetmaskToCidr($args->netmask);
                $nic .= "Address=('".$args->ip."/".$cidr."')\n";

                $nic .= "Gateway='".$args->gw."'\n";
                if (!empty($args->dns2)) {
                    $nic .= "DNS=('".$args->dns1."' '".$args->dns2."')\n";
                } else {
                    $nic .= "DNS=('".$args->dns1."')\n";
                }
            }
            if ($args->wireless === '1') {
                if ($args->hidden === '1') {
                    $nic .= "Hidden=yes\n";
                }
                $nic .= "WPAConfigSection=(\n";
                if ($args->newssid === "add") {
                    $nic .= "    'ssid=\"".$args->ssid."\"'\n";
                    $nic .= "    'scan_ssid=1'\n";
                } else {
                    $nic .= "    'ssid=\"".$args->newssid."\"'\n";
                }
                //$key = $args->key;
                switch ($args->encryption) {
                    case 'none':
                        $nic .= "    'key_mgmt=NONE'\n";
                        break;
                    case 'wep':
                        $nic .= "    'key_mgmt=NONE'\n";
                        $nic .= "    'wep_tx_keyidx=0'\n";
                        $wepkey = $args->key;
                        if (ctype_xdigit($wepkey) && (strlen($wepkey) == 10 OR strlen($wepkey) == 26 OR strlen($wepkey) == 32)) {
                            $nic .= "    'wep_key0=".$wepkey."'\n";
                        } elseif (strlen($wepkey) <= 16) {
                            $nic .= "    'wep_key0=\"".$wepkey."\"'\n";
                        } else {
                            $nic .= "    'wep_key0=\"* wrong wepkey *\"'\n";
                            ui_notify_async('WIFI-Config ERROR', "You entered a wrong key!\n");
                            return '';
                       }
                        //            auth_alg=SHARED
                        break;
                    case 'wpa':
                        $wpakey = $args->key;
                        if (ctype_xdigit($wpakey) && strlen($wpakey) == 64) {
                            $nic .= "    'psk=".$wpakey."'\n";
                        } elseif (strlen($wpakey) >= 8 && strlen($wpakey) <= 63) {
                            $nic .= "    'psk=\"".$wpakey."\"'\n";
                        } else {
                            $nic .= "    'psk=\"* wrong wepkey *\"'\n";
                            ui_notify_async('WIFI-Config ERROR', "You entered a wrong key!\n");
                            return '';
                        }
                        $nic .= "    'key_mgmt=WPA-PSK'\n";
                        if (strpos($args->ie, "WPA2") !== false) {
                            $nic .= "    'proto=RSN'\n";
                        } else {
                            $nic .= "    'proto=RSN WPA'\n"; // added RSN kg
                        }
                        if (strpos($args->GroupCipher, "CCMP") !== false) {
                            $nic .= "    'group=CCMP'\n";
                        } else {
                            $nic .= "    'group=CCMP TKIP'\n"; // added CCMP kg
                        }
                        if (strpos($args->PairwiseCiphers1, "CCMP") !== false OR strpos($args->PairwiseCiphers2, "CCMP") !== false) {
                            $nic .= "    'pairwise=CCMP'\n";
                        } else {
                            $nic .= "    'pairwise=CCMP TKIP'\n"; // added CCMP kg
                        }
                }
                $nic .= "    'priority=3'\n";
                $nic .= ")\n";
            }

            // set advanced DNS options
            $newArray = wrk_replaceTextLine('/etc/resolvconf.conf', '', 'resolv_conf_options=', "resolv_conf_options=('timeout:".$redis->hGet('resolvconf', 'timeout')." attempts:".$redis->hGet('resolvconf', 'attempts')."')", '#name_servers=127.0.0.1', 1);
            // Commit changes to /etc/resolvconf.conf
            $fp = fopen('/etc/resolvconf.conf', 'w');
            fwrite($fp, implode("", $newArray));
            fclose($fp);
            // tell the system to update /etc/resolv.conf
            sysCmd('resolvconf -u');

            // write current network config
            runelog("wireless = ".$args->wireless);
            if ($args->wireless === '1') {
                // wireless
                runelog("save as wireless");
                if ($args->newssid === "add") {
                    $redis->Set($args->ssid, json_encode($args));
                    $fp = fopen('/etc/netctl/'.$args->ssid, 'w');
                } else {
                    $redis->Set($args->newssid, json_encode($args));
                    $fp = fopen('/etc/netctl/'.$args->newssid, 'w');
                }
            } else {
                // wired
                runelog("save as wired");
                $redis->Set($args->name, json_encode($args));
                $fp = fopen('/etc/netctl/'.$args->name, 'w');
            }
            fwrite($fp, $nic);
            fclose($fp);
            if (!isset($configonly)) $updateh = 1;
            //$updateh = 1;
            break;
        case 'manual':
            $file = '/etc/netctl/'.$args['name'];
            $fp = fopen($file, 'w');
            fwrite($fp, $args['config']);
            fclose($fp);
            $updateh = 1;
            break;
        case 'reset':
            wrk_netconfig($redis, 'setnics');
            $args = new stdClass;
            $args->dhcp = '1';
            $args->name = 'eth0';
            wrk_netconfig($redis, 'writecfg', $args);
            $updateh = 1;
            break;
    }
    if ($updateh === 1) {
        // save playback status
        wrk_mpdPlaybackStatus($redis);
        // pause mpd
        sysCmd('mpc pause');
        //sysCmd('systemctl stop mpd');
        // activate configuration (RuneOS)
        runelog("wireless = ".$args->wireless);
        if ($args->wireless !== '1') {
            sysCmd('systemctl reenable netctl-ifplugd@'.$args->name);
            if ($args->reboot === '1') {
                runelog('**** reboot requested ****', $args->name);
                $return = 'reboot';
            } else {
                runelog('**** no reboot requested ****', $args->name);
                sysCmd('systemctl reload-or-restart netctl-ifplugd@'.$args->name);
                $return[] = '';
            }
        } else {
            sysCmd('systemctl reenable netctl-auto@'.$args->name);
            sysCmd('systemctl reload-or-restart netctl-auto@'.$args->name);
            sysCmd('netctl-auto enable '.$args->newssid);
            sysCmd('netctl-auto switch-to '.$args->newssid);
            runelog('**** wireless => do not reboot ****', $args->name);
            $return[] = '';
        }
    }
    // update hash if necessary
    $updateh === 0 || $redis->set($args->name.'_hash', md5_file('/etc/netctl/'.$args->name));
    // set mpd to play if it was playing before the pause
    wrk_mpdRestorePlayerStatus($redis);
    return $return;
}

function wrk_wifiprofile($redis, $action, $args)
{
    switch ($action) {
        case 'add':
            runelog('**** wrk_wifiprofile ADD ****', $args->ssid);
            wrk_wifiprofile($redis, 'connect', $args);
            break;
        case 'edit':
            runelog('**** wrk_wifiprofile EDIT ****', $args->ssid);
            wrk_wifiprofile($redis, 'connect', $args);
            break;
        case 'delete':
            runelog('**** wrk_wifiprofile DELETE ****', $args->ssid);
            wrk_wifiprofile($redis, 'disconnect', $args);
            $redis->Del($args->ssid);
            $redis->Del('stored_profiles');
            sysCmd("rm /etc/netctl/".escapeshellarg($args->ssid));
            sysCmdAsync("systemctl reload-or-restart netctl-auto@".$args->nic);
            $return = 1;
            break;
        case 'connect':
            runelog('**** wrk_wifiprofile CONNECT ****', $args->ssid);
            sysCmdAsync("netctl-auto switch-to ".escapeshellarg($args->ssid));
            $redis->Set('wlan_autoconnect', 1);
            $return = 1;
            break;
        case 'disconnect':
            runelog('**** wrk_wifiprofile DISCONNECT ****', $args->ssid);
            sysCmdAsync("netctl-auto disable ".escapeshellarg($args->ssid));
            $redis->Set('wlan_autoconnect', 0);
            $return = 1;
            break;
    }
    return $return;
}

function wrk_restore($redis, $backupfile)
{
    //$path = "/run/".$backupfile;
    //$cmdstring = "tar xzf ".$path." --overwrite --directory /";
    //if (sysCmd($cmdstring)) {
    //    recursiveDelete($path);
    //}
    return true;
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
    $check_mp = sysCmd('cat /proc/mounts | grep "/mnt/MPD/NAS/'.$mpname.'"');
    if (!empty($check_mp)) {
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
            $acards = sysCmd("cat /proc/asound/cards | grep : | cut -b 1-3,21-");
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
                // $description = sysCmd("cat /proc/asound/cards | grep : | cut -d ':' -f 2 | cut -d ' ' -f 4-20");
                // debug
                runelog('wrk_audioOutput card string: ', $card);
                $description = sysCmd("aplay -l -v | grep \"\[".$card."\]\"");
                $desc = array();
                $subdeviceid = explode(':', $description[0]);
                $subdeviceid = explode(',', trim($subdeviceid[1]));
                $subdeviceid = explode(' ', trim($subdeviceid[1]));
                $data['device'] = 'hw:'.$card_index.','.$subdeviceid[1];
                    if ($i2smodule !== 'none' && $i2smodule_details->sysname === $card) {
                        $acards_details = $i2smodule_details;
                    } else {
                        unset($acards_details);
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
                            runelog('line 1400: (sub_interfaces loop) card: '.$card, $sub_interfaces);
                            foreach ($sub_interfaces as $sub_interface) {
                                runelog('line 1402: (sub_interfaces foreach) card: '.$card, $sub_interface);
                                $sub_int_details = new stdClass();
                                $sub_int_details = json_decode($sub_interface);
                                runelog('line 1405: (sub_interfaces foreach json_decode) card: '.$card, $sub_int_details);
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
        ## RuneAudio enable HDMI & analog output
        $file = '/boot/config.txt';
        $newArray = wrk_replaceTextLine($file, '', 'dtparam=audio=', 'dtparam=audio='.($args == 1 ? 'on' : 'off'), '## RuneAudio HDMI & 3,5mm jack', 1);
        // Commit changes to /boot/config.txt
        $fp = fopen($file, 'w');
        $return = fwrite($fp, implode("", $newArray));
        fclose($fp);
    }
}

function wrk_kernelswitch($redis, $args)
{
    $redis->set('i2smodule', 'none');
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
    $header = "###################################\n";
    $header .= "# Auto generated mpd.conf file\n";
    $header .= "# please DO NOT edit it manually!\n";
    $header .= "# Use RuneUI MPD config section\n";
    $header .= "###################################\n";
    $header .= "\n";
    switch ($action) {
        case 'reset':
            // default MPD config
            sysCmd('/srv/http/db/redis_datastore_setup mpdreset');
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
                $count = sysCmd('grep -c "soxr" /usr/bin/mpd');
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
                    $output .="# MPD version number: ".$value."\n\n";
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
                    if ($redis->hGet('mpdconf', 'version') < '0.21.00') $output .= "group \t\"audio\"\n";
                    continue;
                }
                if ($param === 'user' && $value === 'root') {
                    $output .= $param." \t\"".$value."\"\n";
                    // group is not valid in MPD v0.21.00 or higher
                    if ($redis->hGet('mpdconf', 'version') < '0.21.00') $output .= "group \t\"root\"\n";
                    continue;
                }
                if ($param === 'bind_to_address') {
                    $output .= "bind_to_address \"/run/mpd/socket\"\n";
                }
                if ($param === 'ffmpeg') {
                    // --- decoder plugin ---
                    $output .="\n";
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
                            $output .="\n";
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
                    $output .="\n";
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
                $output .= $param." \t\"".$value."\"\n";
            }
            $output = $header.$output;
            // --- audio output ---
            $acards = $redis->hGetAll('acards');
            // debug
            runelog('detected ACARDS ', $acards, __FUNCTION__);
            $ao = $redis->Get('ao');
            $sub_count = 0;

            if ($redis->hGet("snapserver", "enable") === "0") {
                /* If we don't use snapcast, set mpd to output directly to the soundcard */
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
                    $output .="\n";
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
                $output .="\n";
            } else {
                /* setup mpd to output to snapcast fifo for synchronisation */
                $output .= "audio_output {\n\ttype\t\"fifo\"\n"
                                ."\tname\t\"Snapserver\"\n"
                                ."\tpath\t\"/tmp/snapfifo\"\n"
                                ."\tformat\t\"48000:16:2\"\n"
                                ."\tmixer_type\t\"software\"\n"
                                ."\talways_on\t\"yes\"\n"
                           ."}\n";
            }
            // debug
            // runelog('raw mpd.conf', $output, __FUNCTION__);
            // check if mpd.conf was modified outside RuneUI (advanced mode)
            runelog('mpd.conf advanced state', $mpdconf_advanced);
            // many users need to add an extra output device to MPD
            // this can be specified in the file /home/your-extra-mpd.conf
            // see the example file: /var/www/app/config/defaults/your-extra-mpd.conf
            if (file_exists('/home/your-extra-mpd.conf')) {
                $output .= file_get_contents('/home/your-extra-mpd.conf');
            }
            // write mpd.conf file to /tmp location
            $fh = fopen('/tmp/mpd.conf', 'w');
            fwrite($fh, $output);
            fclose($fh);
            // check whether the mpd.conf file has changed
            if ($redis->get('mpdconfhash') == md5_file('/tmp/mpd.conf')) {
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
            wrk_reconfigureSnapclient($redis);
            wrk_shairport($redis, $args);
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
            // set mpdconfchange off
            $redis->set('mpdconfchange', 0);
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
            sysCmdAsync('sleep 5 && rune_prio nice');
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
    $retval = sysCmd("mpc status | grep '^\[' | cut -d '[' -f 2 | cut -d ']' -f 1");
    $status = trim($retval[0]);
    unset($retval);
    $retval = sysCmd("mpc status | grep '^\[' | cut -d '#' -f 2 | cut -d '/' -f 1");
    $number = trim($retval[0]);
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
    $redis->set('ashuffle_wait_for_play', '1');
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
    $redis->set('ashuffle_wait_for_play', '0');
}

function wrk_shairport($redis, $ao, $name = null)
{
    if (!isset($name)) {
        $name = $redis->hGet('airplay', 'name');
//    } else {
//      $redis->hSet('airplay', 'name', $name);
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
    $newArray = wrk_replaceTextLine('', $newArray, ' alsa_output_rate', 'output_rate="'.$redis->hGet('airplay', 'alsa_output_rate').'"; // alsa_output_rate');
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
    if (md5_file($file) == md5_file($newfile)) {
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
    if (md5_file($file) == md5_file($newfile)) {
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
        sysCmd('systemctl stop rune_SSM_wrk');
        // update systemd
        sysCmd('systemctl daemon-reload');
        if ($redis->get('activePlayer') === 'Spotify') {
            runelog('restart spopd');
            sysCmd('systemctl reload-or-restart spopd || systemctl start spopd');
        }
        if ($redis->hGet('airplay','enable')) {
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
            $retval = sysCmd('grep "'.$mp['address'].'" /proc/mounts | grep "'.$mp['remotedir'].'" | grep "'.$type.'" | grep -c "/mnt/MPD/NAS/'.$mp['name'].'"');
            if ($retval[0]) {
                // already mounted, do nothing and return
                return 1;
            }
            unset($retval);
            // check that the mount server is on-line
            // $retval = sysCmd('avahi-browse -atrlkp | grep -Ei "smb|cifs|nfs" | grep -i -c "'.$mp['address'].'"');
            // if ($retval[0] == 0) {
                // // the mount server is not visible, so don't even try to mount it
                // $mp['error'] = 'Network Mount off-line';
                // return 0;
            // }
            // unset($retval);
            // validate the mount name
            $mp['name'] = trim($mp['name']);
            if ($mp['name'] != preg_replace('/[^A-Za-z0-9-._ ]/', '', $mp['name'])) {
                // no special characters allowed in the mount name
                $mp['error'] = '"'.$mp['name'].'" Invalid Mount Name - no special characters allowed';
                if (!$quiet) {
                    ui_notify($type.' mount', $mp['error']);
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
                    ui_notify($type.' mount', $mp['error']);
                    sleep(3);
                }
            }
            if ($mp['remotedir'] != preg_replace('|[^A-Za-z0-9-._/ ]|', '', $mp['remotedir'])) {
                // special characters are not normally valid as a remote directory name
                $mp['error'] = 'Warning "'.$mp['remotedir'].'" Remote Directory seems incorrect - contains special character(s) - continuing';
                if (!$quiet) {
                    ui_notify($type.' mount', $mp['error']);
                    sleep(3);
                }
            }
            if (strlen($mp['remotedir']) === 0) {
                // normally valid as a remote directory name should be specified
                $mp['error'] = 'Warning "'.$mp['remotedir'].'" Remote Directory seems incorrect - empty - continuing';
                if (!$quiet) {
                    ui_notify($type.' mount', $mp['error']);
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
                    $auth = "username=".$mp['username'].",password=".$mp['password'];
                } else {
                    $auth = 'guest';
                }
                if ($mp['options'] == '') {
                    // no mount options set by the user or from previous auto mount, so set it to a value
                    $options2 = 'cache=none,noserverino,ro,sec=ntlmssp,noexec';
                } else {
                    // mount options provided so use them
                    if (!$quiet) ui_notify($type.' mount', 'Attempting to use saved/predefined mount options');
                    $options2 = $mp['options'];
                }
                $mountstr = "mount -t cifs -o ".$auth.",soft,uid=".$mpdproc['uid'].",gid=".$mpdproc['gid'].",rsize=".$mp['rsize'].",wsize=".$mp['wsize'].",iocharset=".$mp['charset'].",".$options2." \"//".$mp['address']."/".$mp['remotedir']."\" \"/mnt/MPD/NAS/".$mp['name']."\"";
            }
            // create the mount point
            sysCmd("mkdir -p \"/mnt/MPD/NAS/".$mp['name']."\"");
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
            runelog('system response', var_dump($retval));
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
                $retval = sysCmd('grep "'.$mp['address'].'" /proc/mounts | grep "'.$mp['remotedir'].'" | grep "'.$type.'" | grep -c "/mnt/MPD/NAS/'.$mp['name'].'"');
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
                    ui_notify($type.' mount', $mp['error']);
                    sleep(3);
                    ui_notify($type.' mount', '//'.$mp['address'].'/'.$mp['remotedir'].' Failed');
                    sleep(3);
                }
                if(!empty($mp['name'])) sysCmd("rmdir \"/mnt/MPD/NAS/".$mp['name']."\"");
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
                            $options1 = 'cache=none,noserverino,ro,noexec';
                            break;
                        case 2:
                            if (!$quiet) ui_notify($type.' mount', 'Attempting vers=3.1.1');
                            $options1 = 'cache=none,noserverino,ro,vers=3.1.1,noexec';
                            break;
                        case 3:
                            if (!$quiet) ui_notify($type.' mount', 'Attempting vers=3.02');
                            $options1 = 'cache=none,noserverino,ro,vers=3.02,noexec';
                            break;
                        case 4:
                            if (!$quiet) ui_notify($type.' mount', 'Attempting vers=3.0');
                            $options1 = 'cache=none,noserverino,ro,vers=3.0,noexec';
                            break;
                        case 5:
                            if (!$quiet) ui_notify($type.' mount', 'Attempting vers=2.1');
                            $options1 = 'cache=none,noserverino,ro,vers=2.1,noexec';
                            break;
                        case 6:
                            if (!$quiet) ui_notify($type.' mount', 'Attempting vers=2.0');
                            $options1 = 'cache=none,noserverino,ro,vers=2.0,noexec';
                            break;
                        case 7:
                            if (!$quiet) ui_notify($type.' mount', 'Attempting vers=1.0');
                            $options1 = 'cache=none,noserverino,ro,vers=1.0,noexec';
                            break;
                        default:
                            $i = 10;
                            break;
                    }
                    for ($j = 1; $j <= 6; $j++) {
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
                        runelog('system response',var_dump($retval));
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
                            $retval = sysCmd('grep "'.$mp['address'].'" /proc/mounts | grep "'.$mp['remotedir'].'" | grep "'.$type.'" | grep -c "/mnt/MPD/NAS/'.$mp['name'].'"');
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
            if(!empty($mp['name'])) sysCmd("rmdir \"/mnt/MPD/NAS/".$mp['name']."\"");
            if (!$quiet) {
                ui_notify($type.' mount', $mp['error']);
                sleep(3);
                ui_notify($type.' mount', '//'.$mp['address'].'/'.$mp['remotedir'].' Failed');
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
            sysCmd("umount -f \"/mnt/MPD/NAS/".$mp['name']."\"");
            if ($mp['name'] != $args['name']) {
                sysCmd("rmdir \"/mnt/MPD/NAS/".$mp['name']."\"");
                sysCmd("mkdir \"/mnt/MPD/NAS/".$args['name']."\"");
            }
            $return = wrk_sourcemount($redis, 'mount', $args['id']);
            runelog('wrk_sourcecfg(edit) exit status', $return);
            break;
        case 'delete':
            $mp = $redis->hGetAll('mount_'.$args->id);
            sysCmd('mpc stop');
            usleep(500000);
            sysCmd("umount -f \"/mnt/MPD/NAS/".$mp['name']."\"");
            sleep(3);
            if (!empty($mp['name'])) sysCmd("rmdir \"/mnt/MPD/NAS/".$mp['name']."\"");
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
                sysCmd("umount -f \"/mnt/MPD/NAS/".$mp['name']."\"");
                sysCmd("rmdir \"/mnt/MPD/NAS/".$mp['name']."\"");
                $return = $redis->del($key);
            }
            // reset mount index
            if ($return) $redis->del('mountidx');
            wrk_start_mpd($redis);
            // ashuffle gets started automatically
            // set process priority
            sysCmdAsync('sleep 1 && rune_prio nice');
            break;
        case 'umountall':
            sysCmd('mpc stop');
            sysCmd('systemctl stop mpd ashuffle');
            usleep(500000);
            $source = $redis->keys('mount_*');
            foreach ($source as $key) {
                $mp = $redis->hGetAll($key);
                runelog('wrk_sourcecfg() umount loop $mp[name]',$mp['name']);
                sysCmd("umount -f \"/mnt/MPD/NAS/".$mp['name']."\"");
                sysCmd("rmdir \"/mnt/MPD/NAS/".$mp['name']."\"");
            }
            wrk_start_mpd($redis);
            // ashuffle gets started automatically
            // set process priority
            sysCmdAsync('sleep 1 && rune_prio nice');
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
                $redis->exists('soxrmpdonoff') || $redis->set('soxrmpdonoff', 0);
                $redis->hExists('airplay', 'soxronoff') || $redis->hSet('airplay', 'soxronoff', 0);
                $redis->hExists('airplay', 'metadataonoff') || $redis->hSet('airplay', 'metadataonoff', 0);
                $redis->hExists('airplay', 'artworkonoff') || $redis->hSet('airplay', 'artworkonoff', 0);
                $redis->hExists('airplay', 'enable') || $redis->hSet('airplay', 'enable', 0);
                $redis->hExists('AccessPoint', 'enabled') || $redis->hSet('AccessPoint', 'enabled', 0);
            }
            else {
                $model = trim(substr($revision, -3, 2));
                switch($model) {
                    case "00":
                        // 00 = PiA or PiB
                        $arch = '08';
                        $redis->exists('soxrmpdonoff') || $redis->set('soxrmpdonoff', 0);
                        $redis->hExists('airplay', 'soxronoff') || $redis->hSet('airplay', 'soxronoff', 0);
                        $redis->hExists('airplay', 'metadataonoff') || $redis->hSet('airplay', 'metadataonoff', 0);
                        $redis->hExists('airplay', 'artworkonoff') || $redis->hSet('airplay', 'artworkonoff', 0);
                        $redis->hExists('airplay', 'enable') || $redis->hSet('airplay', 'enable', 0);
                        $redis->hExists('AccessPoint', 'enabled') || $redis->hSet('AccessPoint', 'enabled', 0);
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
                        $redis->exists('soxrmpdonoff') || $redis->set('soxrmpdonoff', 1);
                        $redis->hExists('airplay', 'soxronoff') || $redis->hSet('airplay', 'soxronoff', 0);
                        $redis->hExists('airplay', 'metadataonoff') || $redis->hSet('airplay', 'metadataonoff', 1);
                        $redis->hExists('airplay', 'artworkonoff') || $redis->hSet('airplay', 'artworkonoff', 1);
                        $redis->hExists('airplay', 'enable') || $redis->hSet('airplay', 'enable', 1);
                        $redis->hExists('AccessPoint', 'enabled') || $redis->hSet('AccessPoint', 'enabled', 0);
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
                        $arch = '08';
                        $redis->exists('soxrmpdonoff') || $redis->set('soxrmpdonoff', 1);
                        $redis->hExists('airplay', 'soxronoff') || $redis->hSet('airplay', 'soxronoff', 0);
                        $redis->hExists('airplay', 'metadataonoff') || $redis->hSet('airplay', 'metadataonoff', 1);
                        $redis->hExists('airplay', 'artworkonoff') || $redis->hSet('airplay', 'artworkonoff', 1);
                        $redis->hExists('airplay', 'enable') || $redis->hSet('airplay', 'enable', 1);
                        $redis->hExists('AccessPoint', 'enabled') || $redis->hSet('AccessPoint', 'enabled', 1);
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
                        $redis->hExists('airplay', 'soxronoff') || $redis->hSet('airplay', 'soxronoff', 0);
                        $redis->hExists('airplay', 'metadataonoff') || $redis->hSet('airplay', 'metadataonoff', 0);
                        $redis->hExists('airplay', 'artworkonoff') || $redis->hSet('airplay', 'artworkonoff', 0);
                        $redis->hExists('airplay', 'enable') || $redis->hSet('airplay', 'enable', 0);
                        $redis->hExists('AccessPoint', 'enabled') || $redis->hSet('AccessPoint', 'enabled', 0);
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

// this can be removed in next version, because it's replaced by wrk_startAirplay($redis) and wrk_stopAirplay($redis)
function wrk_togglePlayback($redis, $activePlayer)
{
$stoppedPlayer = $redis->get('stoppedPlayer');
// debug
runelog('stoppedPlayer = ', $stoppedPlayer);
runelog('activePlayer = ', $activePlayer);
    if ($stoppedPlayer !== '') {
        if ($stoppedPlayer === 'MPD') {
            // connect to MPD daemon
            $sock = openMpdSocket('/run/mpd/socket', 0);
            $status = _parseStatusResponse($redis, MpdStatus($sock));
            runelog('MPD status', $status);
            if ($status['state'] === 'pause') {
                $redis->set('stoppedPlayer', '');
            }
            sendMpdCommand($sock, 'pause');
            closeMpdSocket($sock);
            // debug
            runelog('sendMpdCommand', 'pause');
        } elseif ($stoppedPlayer === 'Spotify') {
            // connect to SPOPD daemon
            $sock = openSpopSocket('localhost', 6602, 1);
            $status = _parseSpopStatusResponse(SpopStatus($sock));
            runelog('SPOP status', $status);
            if ($status['state'] === 'pause') {
                $redis->set('stoppedPlayer', '');
            }
            sendSpopCommand($sock, 'toggle');
            closeSpopSocket($sock);
            // debug
            runelog('sendSpopCommand', 'toggle');
        }
        $redis->set('activePlayer', $stoppedPlayer);
    } else {
        $redis->set('stoppedPlayer', $activePlayer);
        wrk_togglePlayback($redis, $activePlayer);
    }
runelog('endFunction!!!', $stoppedPlayer);
}

function wrk_startAirplay($redis)
{
    $activePlayer = $redis->get('activePlayer');
    if ($activePlayer != 'Airplay') {
        $redis->set('stoppedPlayer', $activePlayer);

        if ($activePlayer === 'MPD') {
            // record  the mpd status
            wrk_mpdPlaybackStatus($redis);
            // connect to MPD daemon
            $sock = openMpdSocket('/run/mpd/socket', 0);
            $status = _parseStatusResponse($redis, MpdStatus($sock));
            runelog('MPD status', $status);
            // to get MPD out of its idle-loop we discribe to a channel
            sendMpdCommand($sock, 'subscribe Airplay');
            sendMpdCommand($sock, 'unsubscribe Airplay');
            if ($status['state'] === 'play') {
                // pause playback
                sendMpdCommand($sock, 'pause');
                // debug
                runelog('sendMpdCommand', 'pause');
            }
            closeMpdSocket($sock);
        } elseif ($activePlayer === 'Spotify') {
            // connect to SPOPD daemon
            $sock = openSpopSocket('localhost', 6602, 1);
            // to get SPOP out of its idle-loop
            sendSpopCommand($sock, 'notify');
            sleep(1);
            $status = _parseSpopStatusResponse(SpopStatus($sock));
            runelog('SPOP status', $status);
            if ($status['state'] === 'play') {
                sendSpopCommand($sock, 'toggle');
                // debug
                runelog('sendSpopCommand', 'toggle');
            }
            closeSpopSocket($sock);
        }
        $redis->set('activePlayer', 'Airplay');
        //ui_render('playback', "{\"currentartist\":\"<unknown>\",\"currentsong\":\"Airplay\",\"currentalbum\":\"<unknown>\",\"artwork\":\"\",\"genre\":\"\",\"comment\":\"\"}");
        //sysCmd('curl -s -X GET http://localhost/command/?cmd=renderui');
    }
}

function wrk_stopAirplay($redis)
{
    $activePlayer = $redis->get('activePlayer');
    if ($activePlayer == 'Airplay') {

        $stoppedPlayer = $redis->get('stoppedPlayer');
        runelog('stoppedPlayer = ', $stoppedPlayer);

        if ($stoppedPlayer !== '') {
            // we previously stopped playback of one player to use the Airport Stream
            if ($stoppedPlayer === 'MPD') {
                // connect to MPD daemon
                $sock = openMpdSocket('/run/mpd/socket', 0);
                $status = _parseStatusResponse($redis, MpdStatus($sock));
                runelog('MPD status', $status);
                if ($status['state'] === 'pause') {
                    // clear the stopped player if we left MPD paused
                    $redis->set('stoppedPlayer', '');
                }
                //sendMpdCommand($sock, 'pause');
                // to get MPD out of its idle-loop we discribe to a channel
                sendMpdCommand($sock, 'subscribe Airplay');
                sendMpdCommand($sock, 'unsubscribe Airplay');
                closeMpdSocket($sock);
                // continue playing mpd where it stopped when airplay started
                wrk_mpdRestorePlayerStatus($redis);
                // debug
                //runelog('sendMpdCommand', 'pause');
            } elseif ($stoppedPlayer === 'Spotify') {
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
                // debug
                //runelog('sendSpopCommand', 'toggle');
            }
            // set the active player back to the one we stopped
            $redis->set('activePlayer', $stoppedPlayer);

            //delete all files in shairport folder except "now_playing"
            $dir = '/var/run/shairport/';
            $leave_files = array('now_playing');
            foreach( glob("$dir/*") as $file ) {
            if( !in_array(basename($file), $leave_files) )
                unlink($file);
            }
        }
        runelog('endFunction!!!', $stoppedPlayer);
        sysCmd('curl -s -X GET http://localhost/command/?cmd=renderui');
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
        $retval = sysCmd('grep -Po "^Serial\s*:\s*\K[[:xdigit:]]{16}" /proc/cpuinfo');
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

function wrk_reconfigureSnapclient($redis)
{
    sysCmd('systemctl stop snapclient');
    if ($redis->hGet("snapserver", "enable") === "1") {
        /* route sound via snapcast */
        $redis->set("snapcast_host", "127.0.0.1");
        wrk_configureSnapclient($redis);
        sysCmd('systemctl start snapclient');
        runelog("enable local snapclient");
    }
}

function wrk_start_mpd($redis)
{
    runelog("starting mpd");
    wrk_reconfigureSnapclient($redis);

    /* update mpd config */
    wrk_mpdconf($redis, 'writecfg');
    if ($redis->get('mpdconfchange')) {
        wrk_mpdconf($redis, 'refresh');
    }
	$retval = sysCmd('systemctl is-active mpd');
	if ($retval[0] === 'active') {
		/* nothing to do */
        runelog("mpd already running");
	} else {
        runelog("starting mpd daemon");
		$return = sysCmd('systemctl start mpd');
	}
    return $return;
}
function wrk_switchplayer($redis, $playerengine)
{
    switch ($playerengine) {
        case 'MPD':
            $return = wrk_start_mpd($redis);
            // ashuffle gets started automatically
            usleep(500000);
            if ($redis->hGet('lastfm','enable') === '1') sysCmd('systemctl start mpdscribble');
            if ($redis->hGet('dlna','enable') === '1') sysCmd('systemctl start upmpdcli');
            $redis->set('activePlayer', 'MPD');
            wrk_mpdRestorePlayerStatus($redis);
            $return = sysCmd('systemctl stop spopd');
            $return = sysCmd('curl -s -X GET http://localhost/command/?cmd=renderui');
            // set process priority
            sysCmdAsync('rune_prio nice');
            break;

        case 'Spotify':
            $return = sysCmd('systemctl start spopd');
            usleep(500000);
            if ($redis->hGet('lastfm','enable') === '1') sysCmd('systemctl stop mpdscribble');
            if ($redis->hGet('dlna','enable') === '1') sysCmd('systemctl stop upmpdcli');
            sysCmd('systemctl stop snapclient');
            sysCmd('systemctl stop ashuffle');
            wrk_mpdPlaybackStatus($redis);
            $redis->set('activePlayer', 'Spotify');
            $return = sysCmd('systemctl stop mpd');
            $redis->set('mpd_playback_status', 'stop');
            $return = sysCmd('curl -s -X GET http://localhost/command/?cmd=renderui');
            // set process priority
            sysCmdAsync('rune_prio nice');
            break;
	case 'Snapcast':
            sysCmd('systemctl stop snapclient');
            wrk_configureSnapclient($redis);
            sysCmd('systemctl start snapclient');
            wrk_mpdPlaybackStatus($redis);
            $redis->set('activePlayer', 'Snapcast');
            $return = sysCmd('systemctl stop mpd');
            $redis->set('mpd_playback_status', 'stop');
            $return = sysCmd('curl -s -X GET http://localhost/command/?cmd=renderui');
            // set process priority
            sysCmdAsync('rune_prio nice');
            break;

    }
    return $return;
}

function wrk_configureSnapclient($redis)
{
        $file = '/etc/default/snapclient';
        $ao = $redis->get("ao");
        $acard = json_decode($redis->hGet("acards", $ao));
        if (isset($acard->sysname)) {
            $card_param="-s '".$acard->sysname."'";
        } else if (isset($acard->system)) {
            $acard_system = $acard->system;
            runelog("acard ".$acard_system);
            runelog("acard_system $acard_system");
            $shortname = explode(':', $acard_system)[1];
            runelog("shortname $shortname");
            $shortname = trim(explode(' ', $shortname)[1]);
            runelog("shortname now $shortname");
            $card_param="-s '".$shortname."'";
        } else {
            $card_param="";
        }
        $newArray = wrk_replaceTextLine($file,
            '',
            'SNAPCLIENT_OPTS=',
            'SNAPCLIENT_OPTS="-h '.$redis->get("snapcast_host").' '.$card_param.'"');
        // Commit change
        $fp = fopen($file, 'w');
        fwrite($fp, implode("", $newArray));
        fclose($fp);
}
function wrk_sysAcl()
{
    sysCmd('chown -R http.http /srv/http/');
    sysCmd('find /srv/http/ -type f -exec chmod 644 {} \;');
    sysCmd('find /srv/http/ -type d -exec chmod 755 {} \;');
    sysCmd('find /etc -name *.conf -exec chmod 644 {} \;');
    sysCmd('find /usr/lib/systemd/system -name *.service -exec chmod 644 {} \;');
    sysCmd('chmod 644 /etc/nginx/html/50x.html');
    sysCmd('chmod 777 /run');
    sysCmd('chmod 755 /srv/http/command/*');
    sysCmd('chmod 755 /srv/http/db/redis_datastore_setup');
    sysCmd('chmod 755 /srv/http/db/redis_acards_details');
    sysCmd('chmod 755 /etc/X11/xinit/start_chromium.sh');
    sysCmd('chown mpd.audio /mnt/MPD/*');
    sysCmd('chown mpd.audio /mnt/MPD/USB/*');
    sysCmd('chmod 777 /mnt/MPD/USB');
    sysCmd('chmod 777 /mnt/MPD/USB/*');
    sysCmd('chown -R mpd.audio /var/lib/mpd');
}

function wrk_NTPsync($ntpserver)
{
    //debug
    runelog('NTP SERVER', $ntpserver);
    // support for chrony and systemd-time
    $retval = sysCmd('systemctl is-active chronyd');
    $return = $retval[0];
    unset($retval);
    if ($return === 'active') {
        // chrony is running
        // check that it is a valid ntp server
        $retval = sysCmd('chronyc add server '.$ntpserver.' | grep OK');
        $return = ' '.$retval[0];
        unset($retval);
        // servers seem to be valid as a chrony server or a chrony pool, so use the user defined server as a server and a pool in the config file
        // chrony will look for a series of valid servers before deciding which one to use
        // if the user defined server/pool is bad then the other predefined servers will be used
        if (strpos($return, 'OK')) {
            // add the server name to chrony.conf
            $file = '/etc/chrony.conf';
            // replace the line with 'server ' in the line 1 line after a line containing '# First ntp server is set in the RuneAudio Settings'
            $newArray = wrk_replaceTextLine($file, '', 'server ', 'server '.$ntpserver.' iburst prefer', '# First ntp server is set in the RuneAudio Settings', 1);
            // replace the line with 'pool ' in the line 1 line after a line containing '# First ntp pool is set in the RuneAudio Settings'
            $newArray = wrk_replaceTextLine('' , $newArray, 'pool ', 'pool '.$ntpserver.' iburst prefer', '# First ntp pool is set in the RuneAudio Settings', 1);
            // Commit changes to /etc/chrony.conf
            $fp = fopen($file, 'w');
            $return = fwrite($fp, implode("", $newArray));
            fclose($fp);
            // return the valid ntp server name
            return $ntpserver;
        } else {
            // invalid ftp server name, false returns a '' value
            return false;
       }
    } else {
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
    clearstatcache();
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
        sysCmd('pgrep nmbd || systemctl reload-or-restart nmbd');
        sysCmd('pgrep smbd || systemctl reload-or-restart smbd');
        sysCmd('pgrep nmb || systemctl reload-or-restart nmb');
        sysCmd('pgrep smb || systemctl reload-or-restart smb');
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
    // update airplayname
    if ((trim($redis->hGet('airplay', 'name')) === $rhn) && ($newhostname != $rhn)) {
        $redis->hSet('airplay', 'name', $newhostname);
        wrk_shairport($redis, $redis->get('ao'), $newhostname);
        if ($redis->hGet('airplay','enable') === '1') {
            runelog("service: airplay restart",'');
            sysCmd('systemctl reload-or-restart shairport-sync || systemctl start shairport-sync');
        }
    }
    // update dlnaname
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
    // update AVAHI serice data
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
    sysCmdAsync('sleep 1 && rune_prio nice');
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
    sysCmdAsync('sleep 1 && rune_prio nice');
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
    sysCmdAsync('/var/www/command/ui_notify.php \''.$output.'\'');
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
    //Snapcast
    if (file_exists('/usr/bin/snapclient')) {
        $snapcast = '1';
    } else {
        $snapcast = '';
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
    $jsonHome = json_encode(array('internetAvailable' => $internetAvailable,
                                  'bookmarks' => $bookmarks,
                                  'localStorages' => $localStorages,
                                  'networkMounts' => $networkMounts,
                                  'USBMounts' => $usbMounts,
                                  'webradio' => $webradios,
                                  'Spotify' => $spotify,
                                  'Snapcast' => $snapcast,
                                  'Dirble' => $dirbleAmount,
                                  'Jamendo' => $jamendo,
                                  'ActivePlayer' => $activePlayer,
                                  'clientUUID' => $clientUUID));
    // Encode UI response
    runelog('ui_libraryHome - JSON: ', $jsonHome);
    ui_render('library', $jsonHome);
}

function ui_lastFM_coverart($artist, $album, $lastfm_apikey, $proxy)
{
    if (!empty($album)) {
        $url = "http://ws.audioscrobbler.com/2.0/?method=album.getinfo&api_key=".$lastfm_apikey."&artist=".urlencode($artist)."&album=".urlencode($album)."&format=json";
        unset($artist);
    } else {
        $url = "http://ws.audioscrobbler.com/2.0/?method=artist.getinfo&api_key=".$lastfm_apikey."&artist=".urlencode($artist)."&format=json";
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
function ui_lastFM_similar($artist, $track, $lastfm_apikey, $proxy)
{
    runelog('similar lastfm artist', $artist);
    runelog('similar lastfm track', $track);
    runelog('similar lastfm name', $proxy);
    runelog('similar lastfm lastfm_api', $lastfm_api);
    // This makes the call to Last.fm. The limit parameter can be adjusted to the number of tracks you want returned.
    // [TODO] adjustable amount of tracks in settings screen
    $url = "http://ws.audioscrobbler.com/2.0/?method=track.getsimilar&limit=1000&api_key=".$lastfm_apikey."&artist=".urlencode($artist)."&track=".urlencode($track)."&format=json";
    runelog('similar lastfm query URL', $url);
    // debug
    //echo $url;
    // This call does not work
    //$output = json_decode(curlGet($url, $proxy), true);
    // But these 2 lines do
    $content = file_get_contents($url);
    $output = json_decode($content,true);
    // debug
    // debug++
    // echo "<pre>";
    // print_r($output);
    // echo "</pre>";
    foreach($output['similartracks']['track'] as $similar) {
        $simtrack = $similar['name'];
        $simartist = $similar['artist']['name'];
        if (strlen($simtrack)>0 and strlen($simartist)>0) {
            // If we have a track and an artist then make a call to mpd to add it. If it doesn't exist then it doesn't
            // matter
            $status = sysCmd("mpc search artist '".$simartist."' title '".$simtrack. "' | head -n1 | mpc add");
        }
    }
}

// push UI update to NGiNX channel
function ui_render($channel, $data)
{
    curlPost('http://127.0.0.1/pub?id='.$channel, $data);
    runelog('ui_render channel=', $channel);
}

function ui_timezone() {
  $zones_array = array();
  $timestamp = time();
  foreach(timezone_identifiers_list() as $key => $zone) {
    date_default_timezone_set($zone);
    $zones_array[$key]['zone'] = $zone;
    $zones_array[$key]['diff_from_GMT'] = 'GMT ' . date('P', $timestamp);
  }
  return $zones_array;
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
        if ($proxy['enable'] === '1') {
            $proxy['user'] === '' || curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy['user'].':'.$proxy['pass']);
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
