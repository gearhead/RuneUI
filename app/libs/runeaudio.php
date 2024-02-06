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

function is_localhost()
{
    $whitelist = array( '127.0.0.1', '::1' );
    if( in_array( $_SERVER['REMOTE_ADDR'], $whitelist) )
        return true;
}

function openMpdSocketRepeat($path, $type = 0, $retries = 1, $wait = 2)
// this calls function openMpdSocket $retries times, pausing $wait seconds on each error iteration
{
    $socketError = true;
    $socketErrorCount = $retries;
    $waitSec = intval($wait);
    while ($socketError) {
        $sock = openMpdSocket($path, $type);
        if (isset($sock) && is_array($sock) && isset($sock['description'])) {
            $socketError = false;
        } else {
            $sock = false;
            $socketError = true;
            sleep($waitSec);
            $socketErrorCount--;
        }
        if ($socketErrorCount <= 0) {
            $sock = false;
            // exit the loop
            $socketError = false;
        }
    }
    return $sock;
}

function openMpdSocket($path, $type = 0, $sockVarName = null)
// connection types: 0 = normal (blocking), 1 = burst mode (blocking), 2 = burst mode 2 (non blocking)
// normal (blocking) is default when type is not specified
// when the value of $sockVarName is specified it indicates that it will be reused, instead of searching for a free socket
//  this is used for reopening a timed out socket with the same socket name
// the success return value is an array containing 'resource' = the socket resource or object (previously a resource,
//  from php v8 socket is an object), 'sockVarName' = the socket variable name, 'type' = connection type and
//  'description' = a description of the socket object derives van a var_dump of the resource
// the variable with the name contained in the variable sockVarName is a global, accessible outside this function
//  within the current php job, it is referenced to by using the $$ variable variable construction
//  so $sock['sockVarName'] contains the name of the global variable containing the globally defined resource or object
//  the format of the globally defined resource or object variable is 'mpdsock_A_B', where A is the socket type
//  and B is a numeric value between 0 and 9, examples of globally defined resource or object variables
//  are: $mpdsock_0_0, $mpdsock_2_9
// the globally defined resource or object variables are removed when the socket is closed
{
    if (!isset($sockVarName) && !$sockVarName) {
        // find a free socket variable name
        for ($i = 0; $i <= 10; $i++) {
            if ($i === 10) {
                runelog("[open]\t>>>>>> OPEN MPD SOCKET ERROR - **All sockets used for type = ".$type."** <<<<<<",'');
                return false;
            }
            $sockVarName = 'mpdsock_'.$type.'_'.$i;
            if (!isset($GLOBALS[$sockVarName])) {
                break;
            }
        }
    }
    // define the socket variable name as global
    global $$sockVarName;
    // create the socket
    $$sockVarName = socket_create(AF_UNIX, SOCK_STREAM, 0);
    // create a description of the socket
    ob_start(null, 0, PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_REMOVABLE);
    var_dump($$sockVarName);
    $sockDesc = trim(preg_replace('/[\t\n\r\s]+/',' ', ob_get_clean()));
    // create socket connection array
    $sock = array('sockVarName' => $sockVarName, 'type' => $type, 'description' => $sockDesc);
    if ($type === 2) {
        socket_set_nonblock($$sockVarName);
        runelog('[open]['.$sock['description']."]\t>>>>>> OPEN MPD SOCKET - **BURST MODE 2 (non blocking)** <<<<<<",'');
    } else if ($type === 1) {
        socket_set_block($$sockVarName);
        runelog('[open]['.$sock['description']."]\t>>>>>> OPEN MPD SOCKET - **BURST MODE 2 (blocking)** <<<<<<",'');
    } else {
        socket_set_block($$sockVarName);
        runelog('[open]['.$sock['description']."]\t>>>>>> OPEN MPD SOCKET - **NORMAL MODE (blocking)** <<<<<<",'');
    }
    // socket_connect(<socket>, <host>, <port>);
    // <port> is not used for AF_UNIX sockets, see the socket_create() usage above
    $connection = socket_connect($$sockVarName, $path);
    if ($connection) {
        // Expected MPD Open Response messages
        // MPD: 'OK MPD x.xx.xx\n' (14 to 15 bytes, as of version 0.22.0)
        // Where x is a numeric value (version number)
        // read the MPD greeting response, use the first 20 bytes - trim the leading and trailing whitespace
        $header = trim(socket_read($$sockVarName, 20, PHP_NORMAL_READ));
        // the header should contain an 'OK', if not something went wrong
        if (!strpos(' '.$header, 'OK')) {
            runelog('[open]['.$sock['description']."]\t>>>>>> MPD OPEN SOCKET ERROR REPORTED - Greeting response: ", $header);
            // ui_notifyError($redis, 'MPD open error: '.$sock['description'],'Greeting response = '.$header);
            closeMpdSocket($sock);
            return false;
        }
        runelog('[open]['.$sock['description']."]\t>>>>>> OPEN MPD SOCKET - Greeting response: ".$header.'<<<<<<','');
        return $sock;
    } else {
        runelog('[open]['.$sock['description']."]\t>>>>>> MPD SOCKET ERROR: ".socket_last_error($$sockVarName).' <<<<<<','');
        // ui_notifyError($redis, 'MPD sock: '.$sock['description'],'socket error = '.socket_last_error($$sockVarName));
        closeMpdSocket($sock);
        return false;
    }
}

function closeMpdSocket($sock, $retainSockVarName = false)
// when $retainSockVarName is set to true the socket variable name will not be unset
//  this is used when reopening a timed out socket with the same name
{
    if (!is_array($sock) || !isset($sock['sockVarName'])) {
        // debug
        // echo "[close][INVALID SOCKET]\t<<<<<< MPD SOCKET ERROR: Invalid parameters - Terminating >>>>>>";
        runelog("[close][INVALID SOCKET]\t<<<<<< MPD SOCKET ERROR: Invalid parameters - Terminating >>>>>>",'');
        return false;
    }
    // define the socket variable name as global
    $sockVarName = $sock['sockVarName'];
    global $$sockVarName;
    if (!isset($$sockVarName)) {
        if (!isset($sock['description'])) {
            $sock['description'] = 'UNSET SOCKET';
        }
        runelog('[close]['.$sock['description']."\t<<<<<< MPD SOCKET ERROR: Invalid socket variable name - Continuing >>>>>>",'');
    }
    //
    // code to force the socket to close and close it
    //  the status of non blocking sockets cannot be examined with stream_get_meta_data()
    //  this means that its status cannot always be determined in advance and if the socket
    //  is already closed for some reason (eg a timeout) the following lines will fail
    //  the solution is to trap any errors and continue
    try {
        // Code that may throw an Exception or Error.
        // set values in the socket to force immediate closure
        $linger = array ('l_linger' => 0, 'l_onoff' => 1);
        socket_set_block($$sockVarName);
        socket_set_option($$sockVarName, SOL_SOCKET, SO_LINGER, $linger);
    }
    catch (Throwable $t) {
        // Executed only in PHP 7 and higher, will not match in PHP 5 and lower
        runelog('[close]['.$sock['description'].']\t<<<<<< MPD SOCKET ERROR: Failed to set $linger - Continuing >>>>>>', '');
    }
    catch (Exception $e) {
        // Executed only in PHP 5 and lower, will not be reached in PHP 7 and higher
        runelog('[close]['.$sock['description'].']\t<<<<<< MPD SOCKET ERROR: Failed to set $linger - Continuing >>>>>>', '');
    }
    try {
        // Code that may throw an Exception or Error.
        // close the socket
        socket_close($$sockVarName);
    }
    catch (Throwable $t) {
        // Executed only in PHP 7 and higher, will not match in PHP 5 and lower
        runelog('[close]['.$sock['description']."]\t<<<<<< MPD SOCKET ERROR: Looks like the socket is already closed - Continuing >>>>>>", '');
    }
    catch (Exception $e) {
        // Executed only in PHP 5 and lower, will not be reached in PHP 7 and higher
        runelog('[close]['.$sock['description']."]\t<<<<<< MPD SOCKET ERROR: Looks like the socket is already closed - Continuing >>>>>>", '');
    }
    runelog('[close]['.$sock['description']."]\t<<<<<< MPD SOCKET CLOSE >>>>>>", '');
    if (!$retainSockVarName) {
        // remove the global variable containing the socket resource or object
        unset($$sockVarName);
        unset($GLOBALS[$sockVarName]);
    } else {
        runelog('[close]['.$sock['description']."]\t<<<<<< MPD SOCKET NAME RETAINED ON CLOSE >>>>>>", '');
    }
}

function sendMpdCommand(&$sock, $cmd)
// note that &$sock is passed by reference, it can be reset to new values
{
    if (!is_array($sock) || !isset($sock['sockVarName'])) {
        if (!isset($sock['description'])) {
            $sock['description'] = 'UNSET SOCKET';
        }
        runelog('[send]['.$sock['description']."\t<<<<<< MPD SOCKET ERROR: Invalid parameters >>>>>>",'');
        return false;
    }
    // define the socket variable name as global
    $sockVarName = $sock['sockVarName'];
    global $$sockVarName;
    if (!isset($$sockVarName)) {
        if (!isset($sock['description'])) {
            $sock['description'] = 'UNSET SOCKET';
        }
        runelog('[send]['.$sock['description']."\t<<<<<< MPD SOCKET ERROR: Invalid socket variable name >>>>>>",'');
        return false;
    }
    $cmd = trim($cmd)."\n";
    if (socket_write($$sockVarName, $cmd, strlen($cmd)) === false) {
        // try reopening the socket with the same socket name if it has timed out (error code = 104)
        if (socket_last_error($$sockVarName) == 104) {
            closeMpdSocket($sock, true);
            $sock = openMpdSocket($sock['path'], $sock['type'], $sockVarName);
            if ($sock) {
                if (socket_write($$sockVarName, $cmd, strlen($cmd)) === false) {
                    runelog('[send]['.$sock['description']."]\t<<<<<< MPD SOCKET SEND ERROR (2nd TRY) (".socket_strerror(socket_last_error($$sockVarName)).') >>>>>>','');
                    return false;
                }
            } else {
                runelog('[send]['.$sock['description']."]\t<<<<<< MPD SOCKET SEND ERROR (REOPEN) (".socket_strerror(socket_last_error($$sockVarName)).') >>>>>>','');
                return false;
            }
        } else {
            runelog('[send]['.$sock['description']."]\t<<<<<< MPD SOCKET SEND ERROR (".socket_strerror(socket_last_error($$sockVarName)).') >>>>>>','');
            return false;
        }
    }
    runelog('[send]['.$sock['description']."]\t<<<<<< MPD SOCKET SEND : ", $cmd);
    return true;
}

// detect end of MPD response
function checkEOR($chunk)
{
    if (strpos(' '.$chunk, "OK\n")) {
        return true;
    } else if (strpos(' '.$chunk, 'ACK [')) {
        // the format is 'ACK [99@9] ...', see: https://www.musicpd.org/doc/html/protocol.html
        if (preg_match("/(\[[0-9]+@[0-9]+\])/", $chunk)) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function readMpdResponse($sock)
{
    if (!is_array($sock) || !isset($sock['sockVarName'])) {
        if (!isset($sock['description'])) {
            $sock['description'] = 'UNSET SOCKET';
        }
        runelog('[read]['.$sock['description']."]\t<<<<<< MPD SOCKET ERROR: Invalid parameters >>>>>>",'');
        return false;
    }
    // define the socket variable name as global
    $sockVarName = $sock['sockVarName'];
    global $$sockVarName;
    if (!isset($$sockVarName)) {
        if (!isset($sock['description'])) {
            $sock['description'] = 'UNSET SOCKET';
        }
        runelog('[read]['.$sock['description']."\t<<<<<< MPD SOCKET ERROR: Invalid socket variable name >>>>>>",'');
        return false;
    }
    // initialize vars
    $output = '';
    $read = '';
    $read_monitor = array();
    $write_monitor  = NULL;
    $except_monitor = NULL;
    // the variable phpVersion contains the major release, for example 5, 7, 8 , 9, etc.
    $phpVersion = intval(phpversion());
    // debug
    // socket monitoring
    // iteration counter
    // $i = 0;
    // timestamp
    // $starttime = microtime(true);
    // runelog('START timestamp:', $starttime);
    if ($sock['type'] === 2) {
        // handle burst mode 2 (nonblocking) socket session
        $read_monitor = array($$sockVarName);
        $buff = 1024;
        // debug
        // $socket_activity = socket_select($read_monitor, $write_monitor, $except_monitor, NULL);
        // runelog('[read]['.$sock['description'].'][pre-loop]['.$sock['type']."]\t<<<<<< MPD READ: ",$socket_activity);
        $end = 0;
        while($end === 0) {
            // the next line is php 7 and php 8 compatible TO-DO at some time in the future the php 7 part can be removed
            if ((($phpVersion < 8) && is_resource($$sockVarName)) || (($phpVersion > 7) && is_object($$sockVarName))) {
                $read = socket_read($$sockVarName, $buff);
                if (!isset($read) || $read === false) {
                    $output = socket_strerror(socket_last_error($$sockVarName));
                    runelog('[read]['.$sock['description'].'][read-loop]['.$sock['type']."]\t<<<<<< MPD READ SOCKET DISCONNECTED: ",$output);
                    break;
                }
            } else {
                runelog('[read]['.$sock['description'].'][read-loop]['.$sock['type']."]\t<<<<<< MPD READ INVALID SOCKET: ",$sock['description']);
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
            if (strpos(' '.$read, "\n")) {
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
    } else if ($sock['type'] === 1) {
    // handle burst mode 1 (blocking) socket session
        $read_monitor = array($$sockVarName);
        $buff = 1310720;
        // debug
        // $socket_activity = socket_select($read_monitor, $write_monitor, $except_monitor, NULL);
        // runelog('[read]['.$sock['description'].'][pre-loop]['.$sock['type']."]\t<<<<<< MPD READ: ",$socket_activity);
        do {
            // debug
            // $i++;
            // $elapsed = microtime(true);
            // read data from socket
            // the next line is php 7 and php 8 compatible TO-DO at some time in the future the php 7 part can be removed
            if ((($phpVersion < 8) && is_resource($$sockVarName)) || (($phpVersion > 7) && is_object($$sockVarName))) {
                $read = socket_read($$sockVarName, $buff);
            } else {
                runelog('[read]['.$sock['description'].'][read-loop]['.$sock['type']."]\t<<<<<< MPD READ INVALID SOCKET: ",$sock['description']);
                break;
            }
            // debug
            // runelog('socket_read status', $read);
            if (!isset($read) || $read === '' || $read === false) {
                $output = socket_strerror(socket_last_error($$sockVarName));
                // debug
                runelog('[read]['.$sock['description'].'][read-loop]['.$sock['type']."]\t<<<<<< MPD READ SOCKET DISCONNECTED: ",$output);
                break;
            } else {
                runelog('[read]['.$sock['description'].'][read-loop]['.$sock['type']."]\t<<<<<< MPD READ SUCCESS : ",'');
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
        return preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $output);
    } else {
        // handle normal mode (blocking) socket session
        $read_monitor = array($$sockVarName);
        $buff = 4096;
        // debug
        // $socket_activity = socket_select($read_monitor, $write_monitor, $except_monitor, NULL);
        // runelog('[read]['.$sock['description'].'][pre-loop]['.$sock['type']."]\t<<<<<< MPD READ: ",$socket_activity);
        do {
            // debug
            // $i++;
            // $elapsed = microtime(true);
            // the next line is php 7 and php 8 compatible TO-DO at some time in the future the php 7 part can be removed
            if ((($phpVersion < 8) && is_resource($$sockVarName)) || (($phpVersion > 7) && is_object($$sockVarName))) {
                $read = socket_read($$sockVarName, $buff, PHP_NORMAL_READ);
            } else {
                break;
            }
            // debug
            // runelog('socket_read status', $read);
            if (!isset($read) || $read === '' || $read === false) {
                $output = socket_strerror(socket_last_error($$sockVarName));
                // debug
                runelog('[read]['.$sock['description'].'][read-loop]['.$sock['type']."]\t<<<<<< MPD READ SOCKET DISCONNECTED : ",$output);
                break;
            } else {
                runelog('[read]['.$sock['description'].'][read-loop]['.$sock['type']."]\t<<<<<< MPD READ SUCCESS : ",'');
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
        return preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $output);
    }
}

function sendMpdIdle($sock)
{
    sendMpdCommand($sock, 'idle');
    $resp = readMpdResponse($sock);
    if (strpos(' '.$resp, 'OK')) {
        // the response is 'changed:<value>', multiple lines can be returned, followed by 'OK'
        // convert it to a string delimited by colons, no duplicates, e.g. 'player:playlist:mixer:OK' and not 'player:playlist:player:mixer:OK'
        $plistArray = array();
        $plistLine = strtok($resp, "\n\r");
        while ($plistLine) {
            $element = '';
            $value = '';
            if (strpos(' '.$plistLine, ':')) {
                list ($element, $value) = explode(':', $plistLine, 2);
                $element = trim($element);
                if (isset($value)) {
                    $value = trim($value);
                } else {
                    $value = '';
                }
            }
            if ($element) {
                $plistArray[] = $value;
            }
            $plistLine = strtok("\n\r");
        }
        $plistArray = array_unique($plistArray);
        $resp = implode(':',$plistArray).':OK';
        return $resp;
    } else {
        return false;
    }
}

function monitorMpdState($redis, $sock)
{
    $change = sendMpdIdle($sock);
    if (isset($change) && $change) {
        $status = _parseStatusResponse($redis, MpdStatus($sock));
        if (isset($status) && is_array($status)) {
            // $change is a string delimited by colons, e.g. 'player:playlist:mixer:OK'
            $status['changed'] = $change;
            // runelog('monitorMpdState()', $status);
            return $status;
        } else {
            return false;
        }
    } else {
        return false;
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
function getMpdOutputs($mpd)
{
    if (sendMpdCommand($mpd, 'outputs')) {
        $outputs= readMpdResponse($mpd);
        if ($outputs) {
            return $outputs;
        } else {
            runelog('[getMpdOutputs] READ RESPONSE ERROR', '');
            return false;
        }
    } else {
        runelog('[getMpdOutputs] SEND COMMAND ERROR', '');
        return false;
    }
}

function getMpdCurrentsongInfo($mpd, $raw=false)
// returns the current song information unaltered (as returned by MPD) or as an array of information elements
// by default an array is returned, by specifying a non-false value for $raw the the data from MPD is returned unaltered
{
    if (sendMpdCommand($mpd, 'currentsong')) {
        $songinfo= readMpdResponse($mpd);
        if (!$songinfo) {
            runelog('[getMpdCurrentsongInfo] READ RESPONSE ERROR', '');
            return false;
        } else if ($raw) {
            return $songinfo;
        } else {
            return _parseMpdresponse($songinfo);
        }
    } else {
        runelog('[getMpdCurrentsongInfo] SEND COMMAND ERROR', '');
        return false;
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

function browseDB($sock, $browsemode, $query='')
{
    // debug for quotes in library items
    // file_put_contents('/srv//http/tmp/browseDB.txt', 'Before:'.$browsemode.':'.$query."\n", FILE_APPEND | LOCK_EX);
    $query = addslashes(html_entity_decode($query));
    switch ($browsemode) {
        case 'file':
            if (isset($query) && !empty($query)){
                sendMpdCommand($sock, 'lsinfo "'.$query.'"');
            } else {
                sendMpdCommand($sock, 'lsinfo');
            }
            break;
        case 'album':
            if (isset($query) && !empty($query)){
                sendMpdCommand($sock, 'find "album" "'.$query.'"');
            } else {
                sendMpdCommand($sock, 'list "album"');
            }
            break;
        case 'artist':
            if (isset($query) && !empty($query)){
                if ($query === 'Various Artists') {
                    sendMpdCommand($sock, 'list artist albumartist "Various Artists"');
                } else {
                    sendMpdCommand($sock, 'list "album" "'.$query.'"');
                }
            } else {
                sendMpdCommand($sock, 'list "albumartist"');
            }
            break;
        case 'composer':
            if (isset($query) && !empty($query)){
                sendMpdCommand($sock, 'find "composer" "'.$query.'"');
            } else {
                sendMpdCommand($sock, 'list "composer"');
            }
            break;
        case 'genre':
            if (isset($query) && !empty($query)){
                sendMpdCommand($sock, 'list "albumartist" "genre" "'.$query.'"');
            } else {
                sendMpdCommand($sock, 'list "genre"');
            }
            break;
        case 'albumfilter':
            if (isset($query) && !empty($query)){
                sendMpdCommand($sock, 'find "albumartist" "'.$query.'" "album" ""');
            }
            break;
        case 'globalrandom':
            sendMpdCommand($sock, 'listall');
            break;
    }
    $response = readMpdResponse($sock);
    return _parseFileListResponse($response, 'htmlspecialchars');
}

function searchDB($sock, $querytype, $query) {
    // debug for quotes in library items
    // file_put_contents('/srv/http/tmp/searchDB.txt', $querytype.':'.$query."\n", FILE_APPEND | LOCK_EX);
    $query = addslashes(html_entity_decode($query));
    sendMpdCommand($sock, "search ".$querytype." \"".$query."\"");
    $response = readMpdResponse($sock);
    return _parseFileListResponse($response, 'htmlspecialchars');
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
    $cmd = (($fileext == 'm3u') || ($fileext == 'pls') || ($fileext == 'cue')) ? "load" : "add";
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

function addNextToQueue($redis, $sock, $path)
{
    $status = _parseStatusResponse($redis, MpdStatus($sock));
    if (!isset($status['playlistlength']) || !is_numeric($status['playlistlength'])) {
        // failed to get a valid status so return with an error
        return false;
    }
    if (!isset($status['nextsong']) || !$status['nextsong']) {
        // currently playing the last song in the queue
        $status['nextsong'] = $status['playlistlength'];
    }
    $musicDir = rtrim($redis->hGet('mpdconf', 'music_directory'), '/');
    $filename = $musicDir.'/'.$path;
    clearstatcache(true, $filename);
    if (is_dir($filename)) {
        // its a directory, its a bit more complicated
        //  assume that no songs are added or deleted during the operation
        //  do it in 2 steps:
        //      add the songs from the directory to the end of the queue and wait until completed
        //      then move the added songs to the next song position
        // remember the position at which the songs will be added
        $moveStart = $status['playlistlength'];
        // set up the command
        $cmdlist = "command_list_begin\n";
        // add the songs to the playlist, they get added to the end of the queue
        $cmdlist .= "add \"".html_entity_decode($path)."\"\n";
        // request wait until playlist event
        $cmdlist .= "idle playlist\n";
        // terminate the command
        $cmdlist .= "command_list_end";
        sendMpdCommand($sock, $cmdlist);
        // the read will wait for playlist event
        readMpdResponse($sock);
        // get the status again to in order to determine the number of songs added
        $status = _parseStatusResponse($redis, MpdStatus($sock));
        // move the previous last entry up to the current last entry in the queue to the next play position
        if (isset($status['nextsong']) && $status['nextsong'] && ($moveStart != $status['nextsong'])) {
            // we were not playing the last song in the queue when songs were added, they need to be moved
            $cmdlist = "move ".$moveStart.":".$status['playlistlength']." ".$status['nextsong'];
            sendMpdCommand($sock, $cmdlist);
        }
    } else {
        $fileext = parseFileStr($path,'.');
        if (($fileext == 'm3u') || ($fileext == 'pls') || ($fileext == 'cue')) {
            // its a playlist or webradio
            $cmdlist = "command_list_begin\n";
            // load the first song in the playlist, it gets added to the end of the queue
            $cmdlist .= "load \"".html_entity_decode($path)."\" 0:1\n";
            // move the last entry in the queue to the next play position
            if (isset($status['nextsong']) && $status['nextsong'] && ($status['playlistlength'] != $status['nextsong'])) {
                // we were not playing the last song in the queue when songs were added, they need to be moved
                $cmdlist .= "move ".$status['playlistlength']." ".$status['nextsong']."\n";
            }
            $cmdlist .= "command_list_end";
            sendMpdCommand($sock, $cmdlist);
        } else {
            // its a song file
            $cmdlist = "command_list_begin\n";
            // add the song in the playlist, it gets added to the end of the queue
            $cmdlist .= "add \"".html_entity_decode($path)."\"\n";
            // move the last entry in the queue to the next play position
            if (isset($status['nextsong']) && $status['nextsong'] && ($status['playlistlength'] != $status['nextsong'])) {
                // we were not playing the last song in the queue when songs were added, they need to be moved
                $cmdlist .= "move ".$status['playlistlength']." ".$status['nextsong']."\n";
            }
            $cmdlist .= "command_list_end";
            sendMpdCommand($sock, $cmdlist);
        }
    }
    return true;
}

function addNextToQueueAndPlay($redis, $sock, $path)
{
    $status = _parseStatusResponse($redis, MpdStatus($sock));
    if (!isset($status['playlistlength']) || !is_numeric($status['playlistlength'])) {
        // failed to get a valid status so return with an error
        return false;
    }
    if (!isset($status['nextsong']) || !$status['nextsong']) {
        // currently playing the last song in the queue
        $status['nextsong'] = $status['playlistlength'];
    }
    $musicDir = rtrim($redis->hGet('mpdconf', 'music_directory'), '/');
    $filename = $musicDir.'/'.$path;
    clearstatcache(true, $filename);
    if (is_dir($filename)) {
        // its a directory, its a bit more complicated
        //  assume that no songs are added or deleted during the operation
        //  do it in 2 steps:
        //      add the songs from the directory to the end of the queue and wait until completed
        //      then move the added songs to the next song position
        // remember the position at which the songs will be added
        $moveStart = $status['playlistlength'];
        // set up the command
        $cmdlist = "command_list_begin\n";
        // add the songs to the playlist, they get added to the end of the queue
        $cmdlist .= "add \"".html_entity_decode($path)."\"\n";
        // request wait until playlist event
        $cmdlist .= "idle playlist\n";
        // terminate the command
        $cmdlist .= "command_list_end";
        sendMpdCommand($sock, $cmdlist);
        // the read will wait for playlist event
        readMpdResponse($sock);
        // get the status again to in order to determine the number of songs added
        $status = _parseStatusResponse($redis, MpdStatus($sock));
        $cmdlist = "command_list_begin\n";
        // move the previous last entry up to the current last entry in the queue to the next play position
        if (isset($status['nextsong']) && $status['nextsong'] && ($moveStart != $status['nextsong'])) {
            // we were not playing the last song in the queue when songs were added, they need to be moved
            $cmdlist .= "move ".$moveStart.":".$status['playlistlength']." ".$status['nextsong']."\n";
            $cmdlist .= "play ".$status['nextsong']."\n";
        } else {
            $cmdlist .= "play ".$moveStart."\n";
        }
        // terminate the command
        $cmdlist .= "command_list_end";
        // run the command
        sendMpdCommand($sock, $cmdlist);
    } else {
        $fileext = parseFileStr($path,'.');
        if (($fileext == 'm3u') || ($fileext == 'pls') || ($fileext == 'cue')) {
            // its a playlist or webradio
            $cmdlist = "command_list_begin\n";
            // load the first song in the playlist, it gets added to the end of the queue
            $cmdlist .= "load \"".html_entity_decode($path)."\" 0:1\n";
            // move the last entry in the queue to the next play position
            if (isset($status['nextsong']) && $status['nextsong'] && ($status['playlistlength'] != $status['nextsong'])) {
                // we were not playing the last song in the queue when songs were added, they need to be moved
                $cmdlist .= "move ".$status['playlistlength']." ".$status['nextsong']."\n";
                $cmdlist .= "play ".$status['nextsong']."\n";
            } else {
                $cmdlist .= "play ".$status['playlistlength']."\n";
            }
            // terminate the command
            $cmdlist .= "command_list_end";
            // run the command
            sendMpdCommand($sock, $cmdlist);
        } else {
            // its a song file
            $cmdlist = "command_list_begin\n";
            // add the song in the playlist, it gets added to the end of the queue
            $cmdlist .= "add \"".html_entity_decode($path)."\"\n";
            // move the last entry in the queue to the next play position
            if (isset($status['nextsong']) && $status['nextsong'] && ($status['playlistlength'] != $status['nextsong'])) {
                // we were not playing the last song in the queue when songs were added, they need to be moved
                $cmdlist .= "move ".$status['playlistlength']." ".$status['nextsong']."\n";
                $cmdlist .= "play ".$status['nextsong']."\n";
            } else {
                $cmdlist .= "play ".$status['playlistlength']."\n";
            }
            // terminate the command
            $cmdlist .= "command_list_end";
            // run the command
            sendMpdCommand($sock, $cmdlist);
        }
    }
    return true;
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

function sysCmdAsync($redis, $syscmd, $waitsec = null)
// when called with the parameter $waitsec (a zero value is valid) the
//  function will execute the command directly as a new job
//  otherwise the command will be queued to be executed sequentially by a single job
//  the job to process the sequential queue will be started if it is not running
{
    if (isset($waitsec) && is_numeric($waitsec)) {
        // a $waitsec value is present and valid, so start a new job
        $cmdstr = "/srv/http/command/cmd_async ".base64_encode($syscmd)." ".intval($waitsec);
        exec($cmdstr." > /dev/null 2>&1 &", $output);
        // debug
        runelog('sysCmdAsync($waitsec) ', $waitsec, __FUNCTION__);
        runelog('sysCmdAsync($cmdstr) decoded', $syscmd, __FUNCTION__);
        runelog('sysCmdAsync($cmdstr) encoded', $cmdstr, __FUNCTION__);
        runelog('sysCmdAsync() output:', $output, __FUNCTION__);
        return $output;
    } else {
        // no valid $waitsec value is present, so queue the command in fifo 'cmd_queue'
        //
        // maybe a little paranoid, but to prevent anyone just dropping commands into the cmd_queue
        //  the commands are deflated, encrypted and then base64_encoded
        //  the reverse is used to decode commands
        //  its not really secure but should be enough to deter the casual burglar
        // variables, initially set at boot time and after emptying the queue
        //  the cipher is the first one returned by openssl_get_cipher_methods()
        //  the initialization vector is calculated in the standard way
        //  the passphrase is the Rune playerID
        if (is_firstTime($redis, 'cmd_queue_encoding')) {
            // delete any existing command queue entries
            $redis->del('cmd_queue');
            // generate the encryption data
            reset_cmd_queue_encoding($redis);
        }
        do {
            // this loop checks that the encryption data is the same before and after writing to the queue
            //  if the encryption data has changed the previously written command will not be decodable
            //  so just write it again with the latest encryption data
            //  it should not happen very often
            // get the encryption data
            $iv = $redis->hGet('cmd_queue_encoding', 'cipher_iv');
            $cipher = $redis->hGet('cmd_queue_encoding', 'cipher');
            $passphrase = $redis->hGet('cmd_queue_encoding', 'passphrase');
            // encode
            //  $encoded = base64_encode(openssl_encrypt(gzdeflate($command, 9), $cipher, $passphrase, 0, $iv));
            // decode
            //  $command = trim(gzinflate(openssl_decrypt(base64_decode($encoded), $cipher, $passphrase, 0, $iv)));
            //
            // deflate, encrypt en base64 encode then put the command into the queue
            //  this takes time, the encryption data could change while encoding and writing to the queue
            $encoded = base64_encode(openssl_encrypt(gzdeflate($syscmd, 9), $cipher, $passphrase, 0, $iv));
            $redis->lPush('cmd_queue', $encoded);
        } while ($iv != $redis->hGet('cmd_queue_encoding', 'cipher_iv'));
        // start the Asynchronous FIFO command queue service to process the data
        //  it loops forever, starting it while it is still running is not a problem
        sysCmd('systemctl start cmd_async_queue');
    }
}

// function to set up the asynchronous FIFO command queue encryption variables
function reset_cmd_queue_encoding($redis)
// maybe a little paranoid, but to prevent anyone just dropping commands into the cmd_queue
//  the commands are deflated, encrypted and then base64_encoded
//  the reverse is used to decode commands
//  its not really secure but should be enough to deter the casual burglar
// variables, initially at boot time and after emptying the queue (see below)
//  the cipher is the first one returned by openssl_get_cipher_methods()
//  the initialization vector is calculated in the standard way
//  the passphrase is the Rune playerID
{
    // the passphrase is the md5 of player id plus the current time
    $passphrase = md5($redis->get('playerid').microtime(true));
    // get the ciphers which we do not use
    $cipher_exclude_list = 'ecb des rc2 rc4 md5 gcm ccm ocb xts wrap';
    if ($redis->exists('cipher_exclude_list')) {
        $cipher_exclude_list = $redis->get('cipher_exclude_list');
        $cipher_exclude_list = trim(preg_replace('/\s\s+/', ' ', $cipher_exclude_list));
    }
    $cipher_exclude_array = explode(' ', $cipher_exclude_list);
    //
    $first_time = is_firstTime($redis, 'cmd_queue_cipher_array');
    if ($first_time) {
        // create the cipher array, run only once after a boot
        //  retry 20 times, wait 1 second in the loop
        $cnt = 20;
        while ((!isset($cipher_array) || (isset($cipher_array) && !is_array($cipher_array))) && ($cnt-- > 0)) {
            $cipher_array = openssl_get_cipher_methods();
            if (!isset($cipher_array) || (isset($cipher_array) && !is_array($cipher_array))) {
                sleep(1);
                unset($cipher_array);
            }
        }
    }
    if (!isset($cipher_array) || (isset($cipher_array) && !is_array($cipher_array))) {
        // still not set
        // unset the is_first_time state of 'cmd_queue_cipher_array'
        unset_is_firstTime($redis, 'cmd_queue_cipher_array');
        // try to use the last stored array or cipher
        $cipher_array = array();
        if ($redis->hExists('cmd_queue_encoding', 'cipher_array')) {
            $cipher_array = json_decode($redis->hGet('cmd_queue_encoding', 'cipher_array'), true);
        } else if ($redis->hExists('cmd_queue_encoding', 'cipher')) {
            $cipher_array[] = $redis->hGet('cmd_queue_encoding', 'cipher');
        } else {
            // cant do anything, abort with an error
            // Use '126	ENOKEY	Required key not available' as exit code
            // exit(126) will be interpreted as a failure (error) completion in bash
            echo "Error: [app/libs/runeaudio.php][reset_cmd_queue_encoding] Failed to determine cipher array, aborting\n";
            exit(126);
        }
    }
    if ($first_time) {
        // this is only run once after a boot
        // remove weak ciphers (e.g. ecb, des, rc2, rc4, md5) and ciphers which have failed from the array
        //  the failed ciphers are those which require a 'tag' (e.g. gcm, ccm, ocb, xts, wrap)
        // need to do some tricks to globalise the variable $cipher_exclude to to make this work
        //  it is removed as a global after use
        global $cipher_exclude;
        foreach ($cipher_exclude_array as $cipher_exclude) {
            $cipher_array = array_filter( $cipher_array, function($c) { global $cipher_exclude; return stripos($c, $cipher_exclude)===FALSE; } );
            // debug
            // echo "Array length -".$cipher_exclude." ".count($cipher_array)."\n";
            runelog('[reset_cmd_queue_encoding] Array length -'.$cipher_exclude.' ', count($cipher_array));
        }
        // remove the golbaised $cipher_exclude
        unset($GLOBALS['cipher_exclude']);
        // ensure the cipher array indexes are sequential
        $cipher_array = array_values($cipher_array);
        $redis->hSet('cmd_queue_encoding', 'cipher_array', json_encode($cipher_array));
    }
    // determine the initialization vector
    //  when it fails it raises an exception, what a pain
    //  retry 10 times, wait 1 second in the loop
    $cnt = 10;
    $ivError = true;
    while ($ivError) {
        try {
            // Code that may throw an Exception or Error
            //
            // determine the highest cipher array index
            $cipher_array_max_index = count($cipher_array) - 1;
            // randomly choose a cipher from the cipher array
            $cipherIndex = rand(0, $cipher_array_max_index);
            $cipher = $cipher_array[$cipherIndex];
            // the next line can throw an exception or error
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
            // try using the cipher to encode and decode the string 'Test'
            $test = base64_encode(openssl_encrypt(gzdeflate('Test', 9), $cipher, $passphrase, 0, $iv));
            $test = trim(gzinflate(openssl_decrypt(base64_decode($test), $cipher, $passphrase, 0, $iv)));
            if ($test == 'Test') {
                // encode and decode successful
                $ivError = false;
                // break the loop
                break;
            } else {
                // encode and decode failed
                echo "Warning: [app/libs/runeaudio.php][reset_cmd_queue_encoding] Encode/decode invalid cipher: '$cipher'\n";
                // remove the invalid entry from the array and save it
                unset($cipher_array[$cipherIndex]);
                $cipher_array = array_values($cipher_array);
                $redis->hSet('cmd_queue_encoding', 'cipher_array', json_encode($cipher_array));
                // save the invalid entry in the cipher exclude list
                $cipher_exclude_list .= ' '.$cipher;
                $redis->set('cipher_exclude_list', $cipher_exclude_list);
                $ivError = true;
                $cnt--;
            }
        }
        catch (Throwable $t) {
            // Executed only in PHP 7 and higher, will not match in PHP 5 and lower
            echo "Warning: [app/libs/runeaudio.php][reset_cmd_queue_encoding] Thrown invalid cipher: '$cipher'\n";
            // remove the invalid entry form the array and save it
            unset($cipher_array[$cipherIndex]);
            $cipher_array = array_values($cipher_array);
            $redis->hSet('cmd_queue_encoding', 'cipher_array', json_encode($cipher_array));
            // save the invalid entry in the cipher exclude list
            $cipher_exclude_list .= ' '.$cipher;
            $redis->set('cipher_exclude_list', $cipher_exclude_list);
            $ivError = true;
            $cnt--;
        }
        catch (Exception $e) {
            // Executed only in PHP 5 and lower, will not be reached in PHP 7 and higher
            echo "Warning: [app/libs/runeaudio.php][reset_cmd_queue_encoding] Exception invalid cipher: '$cipher'\n";
            // remove the invalid entry form the array and save it
            unset($cipher_array[$cipherIndex]);
            $cipher_array = array_values($cipher_array);
            $redis->hSet('cmd_queue_encoding', 'cipher_array', json_encode($cipher_array));
            // save the invalid entry in the cipher exclude list
            $cipher_exclude_list .= ' '.$cipher;
            $redis->set('cipher_exclude_list', $cipher_exclude_list);
            $ivError = true;
            $cnt--;
        }
        if ($cnt <= 0) {
            // since it wont work just exit
            // Use '126	ENOKEY	Required key not available' as exit code
            // exit(126) will be interpreted as a failure (error) completion in bash
            echo "Error: [app/libs/runeaudio.php][reset_cmd_queue_encoding] Failed to determine initialization vector, aborting\n";
            exit(126);
        } else if ($ivError) {
            // loop again, so sleep first
            sleep(1);
        }
    }
    // save the values if the queue is (still) empty
    if (!$redis->lLen('cmd_queue')) {
        $redis->hSet('cmd_queue_encoding', 'cipher', $cipher);
        $redis->hSet('cmd_queue_encoding', 'cipher_iv', $iv);
        $redis->hSet('cmd_queue_encoding', 'passphrase', $passphrase);
    }
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

// format Output for the "library", no longer used for the "playlist"
function _parseFileListResponse($resp, $extraAction=null)
// Extra action can be optionally specified, values:
//  'htmlspecialchars': values of ampersand, double quote, single quote, less then and more then are encoded as &amp;, &quot, &#039, &lt; and &gt;
//  'escaped': values of double quote, single quote, backslash and null are escaped by inserting a backslash before the value
{
    if (is_null($resp)) {
        return null;
    } else {
        // $start_time = microtime(TRUE);
        if ($extraAction == 'htmlspecialchars') {
            $extraAction = 1;
        } else if ($extraAction == 'escaped') {
            $extraAction = 2;
        } else {
            $extraAction = 0;
        }
        $plistArray = array();
        $plistLine = strtok($resp, "\n");
        // $plistFile = "";
        $plCounter = -1;
        $element = '';
        $value = '';
        $browseMode = TRUE;
        while ($plistLine) {
            // runelog('_parseFileListResponse plistLine', $plistLine);
            $element = '';
            $value = '';
            // $blacklist = ['@eaDir', '.Trash'];
            if (!strpos(' '.$plistLine,'@eaDir') && !strpos(' '.$plistLine,'.Trash')) {
                if (strpos(' '.$plistLine, ': ')) {
                    list ($element, $value) = explode(': ', $plistLine, 2);
                    $element = trim($element);
                    if (isset($value)) {
                        switch ($extraAction) {
                            case 0:
                                // no extra action
                                $value = trim($value);
                                break;
                            case 1:
                                // extra action htmlspecialchars
                                $value = htmlspecialchars(trim($value), ENT_QUOTES);
                                break;
                            case 2:
                                // extra action escape
                                $value = addslashes(trim($value));
                                break;
                        }
                    } else {
                        $value = '';
                    }
                }
            }
            if (!$element) {
                // do nothing
            } else if (($element === 'file') || ($element === 'playlist')) {
                $plCounter++;
                $browseMode = FALSE;
                // $plistFile = $value;
                $plistArray[$plCounter][$element] = $value;
                $plistArray[$plCounter]['fileext'] = parseFileStr($value, '.');
            } elseif ($element === 'directory') {
                $plCounter++;
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
                if ($plCounter > -1) {
                    $plistArray[$plCounter][$element] = $value;
                    if ($element === 'Time') {
                        $plistArray[$plCounter]['Time2'] = songTime($plistArray[$plCounter]['Time']);
                    }
                    if (isset($plistArray[$plCounter]['Title']) && isset($plistArray[$plCounter]['file']) && (strtolower(substr($plistArray[$plCounter]['file'], 0, 4)) === 'http') && !isset($plistArray[$plCounter]['Name'])) {
                        // could be a webradio and the Name is not set
                        $filename = '/mnt/MPD/Webradio/'.$plistArray[$plCounter]['Title'].'.pls';
                        clearstatcache(true, $filename);
                        if (file_exists($filename)) {
                            // it is a webradio
                            $plistArray[$plCounter]['Name'] = $plistArray[$plCounter]['Title'];
                        }
                    }
                }
            }
            $plistLine = strtok("\n");
        }
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
        while ($plistLine) {
            $element = '';
            $value = '';
            if (strpos(' '.$plistLine, ': ')) {
                list ($element, $value) = explode(': ', $plistLine, 2);
                $element = trim($element);
                if (isset($value)) {
                    $value = trim($value);
                } else {
                    $value = '';
                }
            }
            if ($element) {
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
            $plistArray['song_percent'] = $percent;
            $plistArray['elapsed'] = $time[0];
            $plistArray['time'] = $time[1];
        } else {
            $plistArray['song_percent'] = 0;
            // $plistArray['elapsed'] = 0;
            $plistArray['time'] = 0;
        }

         // "audio format" output
         if (isset($plistArray['audio'])) {
            $audio_format = explode(":", $plistArray['audio']);
            $retval = sysCmd('grep -his "rate: " /proc/asound/card?/pcm?p/sub?/hw_params');
            switch (strtoupper($audio_format[0])) {
                case 'DSD64':
                case 'DSD128':
                case 'DSD256':
                case 'DSD512':
                case 'DSD1024':
                    if (trim($retval[0]) != '') {
                        $audio_format[2] = $audio_format[1];
                        $audio_format[1] = strtoupper($audio_format[0]).' 1';
                        $dsdRate = preg_replace('/[^0-9]/', '', $audio_format[0]);
                        $audio_format[0] = intval(explode(' ', $retval[0])[1]);
                        $plistArray['bitrate'] = intval(44100 * $dsdRate / 1000);
                        $plistArray['audio_sample_rate'] = round($audio_format[0]/1000, 1);
                        $plistArray['audio'] = $audio_format[0].':'.$audio_format[1].':'.$audio_format[2];
                    }
                    break;
                // case '48000':
                    // no break
                // case '96000':
                    // no break
                // case '192000':
                    // no break
                // case '384000':
                    // $plistArray['audio_sample_rate'] = rtrim(rtrim(number_format($audio_format[0]), 0), ',');
                    // break;
                // case '44100':
                    // // no break
                // case '88200':
                    // // no break
                // case '176400':
                    // // no break
                // case '352800':
                    // $plistArray['audio_sample_rate'] = rtrim(number_format($audio_format[0], 0, ',', '.'),0);
                    // break;
                default:
                    $plistArray['audio_sample_rate'] = round($audio_format[0]/1000, 1);
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
        // elapsed is not set when stop is returned, set it to 0
        if (!isset($plistArray['state']) || ($plistArray['state'] == 'stop')) {
            $plistArray['elapsed'] = 0;
            $plistArray['song_percent'] = 0;
        }
    }
    return $plistArray;
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
            $element = '';
            $value = '';
            if (strpos(' '.$plistLine, ': ')) {
                list ($element, $value) = explode(': ', $plistLine, 2);
                $element = trim($element);
                if (isset($value)) {
                    $value = trim($value);
                } else {
                    $value = '';
                }
            }
            if ($element) {
                $plistArray[$element] = $value;
                $isOk = true;
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
    $blacklist = array('/srv/http/app/libs/runeaudio.php');
    if ($action === 'prime') {
        $files = $redis->sMembers('php_opcache_prime');
        foreach ($files as $file) {
            if (in_array($file, $blacklist)) {
                continue;
            }
            try {
                // Code that may throw an Exception or Error
                opcache_compile_file($file);
            }
            catch (Throwable $t) {
                // Executed only in PHP 7 and higher, will not match in PHP 5 and lower
                continue;
            }
            catch (Exception $e) {
                // Executed only in PHP 5 and lower, will not be reached in PHP 7 and higher
                continue;
            }
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

function pushFile($redis, $filepath)
{
    // debug
    runelog('[pushFile] filepath', $filepath);
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
        ui_notify($redis, 'Backup', 'Downloaded: '.basename($filepath));
        return true;
    } else {
        ui_notifyError($redis, 'Backup', 'Download failed');
        return false;
    }
}

// check if mpd.conf or interfaces was modified outside
function hashCFG($redis, $action='check_mpd')
{
    switch ($action) {
        // case 'check_net':
            // // --- CODE REWORK NEEDED ---
            // //$hash = md5_file('/etc/netctl/eth0');
            // // have to find the settings file by MAC address in connman
            // $eth0MAC = sysCmd("ip link show dev eth0 | grep 'link/ether' | sed 's/^[ \t]*//' |cut -d ' ' -f 2 | tr -d ':'");
            // $hash = md5_file('/var/lib/connman/ethernet_'.$eth0MAC[0].'_cable/settings');
            // if ($redis->get('netconfhash') !== $hash) {
                // return false;
            // }
            // break;
        case 'check_mpd':
            $hash = md5_file('/etc/mpd.conf');
            if ($redis->get('mpdconfhash') !== $hash) {
                return false;
            }
            break;
    }
    return true;
}


function runelog($title, $data = null, $function_name = null)
{
// Connect to Redis backend
    $store = new Redis();
    $store->pconnect('/run/redis/socket');
    $debug_level = $store->get('debug');
    if (isset($function_name)) {
        $function_name = '['.$function_name.'] ';
    } else {
        $function_name = '';
    }
    if ($debug_level !== '0') {
        if (is_array($data) || is_object($data)) {
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
    $redis->set('waitSyWrk', '1');
    if (is_array($jobID)) {
        $cntQueued = 2;
        while (($cntQueued > 1)) {
            $cntQueued = 0;
            foreach ($jobID as $job) {
                if ($redis->hExists('w_queue', $job)) {
                    $cntQueued++;
                    $saveJobID = $job;
                    $inQueue = true;
                }
            }
            if ($cntQueued == 1) {
                do {
                    usleep(650000);
                } while ($redis->sIsMember('w_lock', $saveJobID));
                break;
            } else {
                usleep(650000);
            }
        }
    } elseif (!empty($jobID)) {
        do {
            usleep(650000);
        } while ($redis->sIsMember('w_lock', $jobID));
    }
    // delay setting waitSyWrk off by 1 second to allow the UI to activate
    sysCmdAsync($redis, 'redis-cli set waitSyWrk 0', 1);
    // $redis->set('waitSyWrk', '0');
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

function wrk_localBrowser($redis, $action, $args=null, $jobID=null)
{
    switch ($action) {
        case 'start':
            // start the local browser
            if (sysCmd("grep -ic '^[s\]*#[\s]*disable_overscan=1' '".$redis->get('p1mountpoint')."/config.txt'")[0]) {
                wrk_localBrowser($redis, 'overscan', 1);
            } else {
                wrk_localBrowser($redis, 'overscan', 0);
            }
            if (isset($args) && is_numeric($args)) {
                $redis->hSet('local_browser', 'enable', $args);
                wrk_localBrowser($redis, 'enable-splash', $args);
            }
            if (isset($jobID) && $jobID) {
                $redis->sRem('w_lock', $jobID);
            }
            // modify the files in /usr/share/X11/xorg.conf.d to contain valid rotate and frame buffer options
            sysCmd('/srv/http/command/add-screen-rotate.sh');
            $windows = $redis->hGet('local_browser', 'windows');
            if ($windows == 'xorg') {
                sysCmd('pgrep -x xinit || systemctl start local-browser ; /srv/http/command/ui_update_async 5000000');
            } else if ($windows == 'weston') {
                if (is_firstTime($redis, 'weston_start')) {
                    wrk_localBrowser($redis, 'configure_weston_ini');
                }
                sysCmd('pgrep -x weston || systemctl start local-browser-w ; /srv/http/command/ui_update_async 5000000');
            } else {
                ui_notifyError($redis, 'Local Browser', 'Start-up failed, incorrectly configured');
                break;
            }
            // sometimes x11/chomium fails to start, check it, it starts ok on the second attempt
            sysCmdAsync($redis, '/srv/http/command/local_browser_check_async.sh');
            sysCmdAsync($redis, 'nice --adjustment=10 /srv/http/command/rune_prio nice');
            break;
        case 'stop':
            // stop the local browser
            if (isset($args) && is_numeric($args)) {
                $redis->hSet('local_browser', 'enable', $args);
                wrk_localBrowser($redis, 'enable-splash', $args);
            }
            if (isset($jobID) && $jobID) {
                $redis->sRem('w_lock', $jobID);
            }
            // for attached lcd tft screens 'xset dpms force off' is requird to clear the screen
            sysCmd('pgrep -x xinit && systemctl stop local-browser ; export DISPLAY=:0 ; xset dpms force off');
            sysCmd('pgrep -x weston && systemctl stop local-browser-w');
            break;
        case 'restart':
            wrk_localBrowser($redis, 'stop');
            if ($redis->hGet('local_browser', 'enable')) {
                // wrk_localBrowser($redis, 'start');
                sleep(2);
                wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'localbrowser', 'action' => 'start', 'args' => 1));
            }
            break;
        case 'enable-splash':
            $redis->hSet('local_browser', $action, $args);
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
            $redis->hSet('local_browser', $action, $args);
            if (isset($jobID) && $jobID) {
                $redis->sRem('w_lock', $jobID);
            }
            // modify the zoom factor for the chromium browser in /srv/http/.config/chromium-flags.conf
            // chromium scale factor is a decimal 1 = 100% ( we store it as a decimal)
            $filePathName = '/srv/http/.config/chromium-flags.conf';
            clearstatcache(true, $filePathName);
            if (!file_exists($filePathName)) {
                $filePath = pathinfo($filePathName)['dirname'];
                sysCmd('mkdir "'.$filePath.'"; chown http:http "'.$filePath.'"');
                sysCmd('cp "/srv/http/app/config/defaults'.$filePathName.'" "'.$filePathName.'"; chown http:http "'.$filePathName.'"; chmod 644 "'.$filePathName.'"');
            }
            if (sysCmd('grep -ic "^[\s]*--force-device-scale-factor[\s]*=" "'.$filePathName.'"')[0]) {
                // scale factor line exists, modify it
                sysCmd('sed -i "/^[\s]*--force-device-scale-factor[\s]*=/c\--force-device-scale-factor='.$args.'" "'.$filePathName.'"');
            } else {
                // scale factor line is missing, add it
                sysCmd('echo "--force-device-scale-factor='.$args.'\n" >> "'.$filePathName.'"');
            }
            // modify the zoom factor for the luakit browser in /srv/http/.config/luakit/userconf.lua
            // luakit scale factor is a percentage  ( we store it as a decimal)
            $filePathName = '/srv/http/.config/luakit/userconf.lua';
            $filePath = pathinfo($filePathName)['dirname'];
            clearstatcache(true, $filePathName);
            if (!file_exists($filePathName)) {
                $filePath = pathinfo($filePathName)['dirname'];
                sysCmd('mkdir "'.$filePath.'"; chown http:http "'.$filePath.'"');
                sysCmd('cp "/srv/http/app/config/defaults'.$filePathName.'" "'.$filePathName.'"; chown http:http "'.$filePathName.'"; chmod 644 "'.$filePathName.'"');
            }
            if (sysCmd('grep -ic settings.webview.zoom_level "'.$filePathName.'"')[0]) {
                // scale factor line exists, modify it
                sysCmd('sed -i "/^settings.webview.zoom_level/c\settings.webview.zoom_level = '.round($args*100).'" "'.$filePathName.'"');
            } else {
                // scale factor line is missing, add it
                sysCmd('echo "settings.webview.zoom_level = '.round($args*100).'" >> "'.$filePathName.'"');
            }
            wrk_localBrowser($redis, 'restart');
            break;
        case 'rotate':
            $redis->hSet('local_browser', $action, $args);
            if (isset($jobID) && $jobID) {
                $redis->sRem('w_lock', $jobID);
            }
            sysCmd('/srv/http/command/raspi-rotate-screen.sh '.$args);
            wrk_localBrowser($redis, 'configure_weston_ini');
            wrk_localBrowser($redis, 'restart');
            break;
        case 'overscan':
            $redis->hSet('local_browser', $action, $args);
            if (isset($jobID) && $jobID) {
                $redis->sRem('w_lock', $jobID);
            }
            if ($args){
                // switch overscan on
                // modify <p1mountpoint>/config.txt
                sysCmd("sed -i '/disable_overscan/c\#disable_overscan=1' '".$redis->get('p1mountpoint')."/config.txt'");
                $redis->hSet('local_browser', 'overscan', 1);
            } else {
                // switch overscan off
                // modify <p1mountpoint>/config.txt
                sysCmd("sed -i '/disable_overscan/c\disable_overscan=1' '".$redis->get('p1mountpoint')."/config.txt'");
                $redis->hSet('local_browser', 'overscan', 0);
            }
            break;
        case 'mouse_cursor':
            // configure the mouse cursor for the various windows/browsers environments
            $redis->hSet('local_browser', $action, $args);
            if (isset($jobID) && $jobID) {
                $redis->sRem('w_lock', $jobID);
            }
            if ($args){
                // switch mouse cursor on for X11
                $usecursorno = '';
                // modify the udev rules for vc4 screens
                sysCmd("sed -i '/LIBINPUT_IGNORE_DEVICE/s/\=\"0\"/\=\"1\"/g' '/etc/udev/rules.d/99-runeaudio_local_browser.rules'");
                // and for weston/luakit
                sysCmd('cp /usr/share/icons/Adwaita/cursors/left_ptr.orig /usr/share/icons/Adwaita/cursors/left_ptr');
            } else {
                // switch mouse cursor off for X11
                $usecursorno = '-use_cursor no ';
                // modify the udev rules for vc4 screens
                sysCmd("sed -i '/LIBINPUT_IGNORE_DEVICE/s/\=\"1\"/\=\"0\"/g' '/etc/udev/rules.d/99-runeaudio_local_browser.rules'");
                // and for weston/luakit
                sysCmd('cp /usr/share/icons/Adwaita/cursors/left_ptr.transparent /usr/share/icons/Adwaita/cursors/left_ptr');
            }
            // modify the mouse on/off setting in /etc/X11/xinit/xinitrc
            $filePathName = '/etc/X11/xinit/xinitrc';
            // replace the line with 'matchbox-window-manager' adding or removing the '-use cursor no' clause
            sysCmd('sed -i "\|matchbox-window-manager|c\matchbox-window-manager -use_titlebar no '.$usecursorno.'&" "'.$filePathName.'"');
            wrk_localBrowser($redis, 'restart');
            break;
        case 'configure_weston_ini':
            // configure the weston configuration file /srv/http/.config/weston.ini
            $fileName = '/srv/http/.config/weston.ini';
            $fileContents = file_get_contents($fileName);
            // get the output section
            $outputSection = get_between_data($fileContents, "\n[output]\n", "\n[output]\n");
            if (!$outputSection) {
                // output section is empty, possible that there is no second output section
                $outputSection = get_between_data($fileContents, "\n[output]\n");
            }
            if (!$outputSection) {
                // output section is still empty, generate a default
                $outputSection="name=HDMI-A-2\nmode=current\ntransform=rotate-0\nscale=1\n#app-ids=1\n";
            }
            $outputSection = "[output]\n".$outputSection;
            // strip the output sections from the file contents, these are the last sections of the file
            $fileContents = get_between_data($fileContents, '', "\n[output]\n")."\n";
            // get and set the rotation value in the output specification
            $rotate = $redis->hGet('local_browser', 'rotate');
            if ($rotate == 'NORMAL') {
                $rotate = '0';
            } else if ($rotate == 'CCW') {
                $rotate = '90';
            } else if ($rotate == 'UD') {
                $rotate = '180';
            } else if ($rotate == 'CW') {
                $rotate = '270';
            }
            // change the rotate value in the output section
            if ($rotate == '0') {
                $outputSection = preg_replace("/\n\s*#*\s*transform=rotate.*\n/","\n# transform=rotate-0\n", $outputSection);
            } else {
                $outputSection = preg_replace("/\n\s*#*\s*transform=rotate.*\n/","\ntransform=rotate-".$rotate."\n", $outputSection);
            }
            // get the valid hdmi vc4 device names, the command 'ls /sys/class/drm' returns the information
            //  any of the space delimited values containing 'HDMI', omitting the string before 'HDMI' is the device name
            //  e.g. output: 'card0  card0-HDMI-A-1  card0-HDMI-A-2  renderD128  version', the valid output names are: 'HDMI-A-1' and 'HDMI-A-2'
            $devices = sysCmd('ls /sys/class/drm | xargs')[0];
            $devices = explode(' ', $devices);
            $deviceFound = false;
            foreach ($devices as $device) {
                if (strpos(' '.$device, 'HDMI')) {
                    // found a HDMI output, use it
                    //  TFT & LCD devices should also be found in a similar way
                    // strip the characters befor HDMI
                    $device = 'HDMI'.get_between_data($device, 'HDMI');
                    // change the name in the output section
                    $outputSection = preg_replace("/\n\s*name=.*\n/","\nname=".$device."\n", $outputSection);
                    // append the output section to the file contents
                    $fileContents = $fileContents.$outputSection;
                    $deviceFound = true;
                }
            }
            if (!$deviceFound) {
                // no output devices found, write the original output section
                $fileContents = $fileContents.$outputSection;
            }
            // write the file contents
            file_put_contents($fileName, $fileContents);
            break;
    }
}

function wrk_avahiconfig($redis, $action, $args=null, $jobID=null)
// actions:
//  set_ip: set the way this function works
//      valid args values: 'a' (automatic), '4' (IPv4 only), '6' (IPv6 only), b (both IPv4 and IPv6)
//  check_ip: check and sets the avahi configuration, avahi will be restarted if required
//  hostname: sets the hostname
//      valid args values: a hostname
// the redis hash variable avahi is used
{
    switch ($action) {
        case 'set_ip':
            // validate the value of $args
            if (!isset($args) || !$args || !strpos(' a46b', $args)) {
                ui_notifyError($redis, 'Avahi config', 'Invalid choice: '.$args);
                break;
            }
            // set the redis variable
            $redis->hSet('avahi', 'ip_setup', $args);
            if (isset($jobID) && $jobID) {
                $redis->sRem('w_lock', $jobID);
            }
            // no break;
        case 'check_ip':
            // avahi config file
            $file = '/etc/avahi/avahi-daemon.conf';
            // get the current avahi ip setup
            $curIpv4 = sysCmd('grep -ic "\s*use-ipv4\s*=\s*yes" '.$file)[0];
            $curIpv6 = sysCmd('grep -ic "\s*use-ipv6\s*=\s*yes" '.$file)[0];
            if ($curIpv4 && $curIpv6) {
                $currentSetup = 'b';
            } else if ($curIpv4 && !$curIpv6) {
                $currentSetup = '4';
            } else if (!$curIpv4 && $curIpv6) {
                $currentSetup = '6';
            } else {
                $currentSetup = 'n';
            }
            // get the redis value for $args
            $args = $redis->hGet('avahi', 'ip_setup');
            // determine the automatic configuration
            if ($args = 'a') {
                // determined by the scope of the ip addresses, 'global' (or 'site', ipv6 only) are routable
                $ipv4Routable = sysCmd("ip add | grep -i 'inet\s*' | grep -iEc 'scope\s*global|scope\s*site'")[0];
                $ipv6Routable = sysCmd("ip add | grep -i 'inet6\s*' | grep -iEc 'scope\s*global|scope\s*site'")[0];
                if ($ipv4Routable && $ipv6Routable) {
                    $args = 'b';
                } else if ($ipv4Routable && !$ipv6Routable) {
                    $args = '4';
                } else if (!$ipv4Routable && $ipv6Routable) {
                    $args = '6';
                } else {
                    // should never happen!
                    $args = 'b';
                }
            }
            // if it is already set correctly just return
            if ($currentSetup == $args) {
                break;
            }
            // from this point we know that we will change the configuration
            // check that lines containing use-ipv4 and use-ipv6 exist and add then when that is not the case
            //  they are added in the [server] section
            $ipv4 = sysCmd('grep -ic "use-ipv4" '.$file)[0];
            $ipv6 = sysCmd('grep -ic "use-ipv6" '.$file)[0];
            if (!$ipv4) {
                sysCmd("sed -i '/\[server\]/a # use-ipv4=yes' ".$file);
            }
            if (!$ipv6) {
                sysCmd("sed -i '/\[server\]/a # use-ipv6=yes' ".$file);
            }
            // set up the configuration
            //  first comment all the use-ipv* lines out, then uncomment/change the relevant lines
            sysCmd("sed -i '/^\s*use-ipv.\s*=.*/ s/./# &/' ".$file);
            if ($args == '4') {
                sysCmd("sed -i '/^\s*#\s*use-ipv4\s*=.*/c \use-ipv4=yes' ".$file);
                sysCmd("sed -i '/^\s*#\s*use-ipv6\s*=.*/c \use-ipv6=no' ".$file);
            } else  if ($args == '6') {
                sysCmd("sed -i '/^\s*#\s*use-ipv4\s*=.*/c \use-ipv4=no' ".$file);
                sysCmd("sed -i '/^\s*#\s*use-ipv6\s*=.*/c \use-ipv6=yes' ".$file);
            } else  if ($args == 'b') {
                sysCmd("sed -i '/^\s*#\s*use-ipv4\s*=.*/c \use-ipv4=yes' ".$file);
                sysCmd("sed -i '/^\s*#\s*use-ipv6\s*=.*/c \use-ipv6=yes' ".$file);
            }
            // reload avahi
            sysCmd('systemctl reload avahi-daemon');
            break;
        case 'hostname':
            $hostname = $args;
            // clear the cache otherwise file_exists() returns incorrect values
            clearstatcache(true, '/etc/avahi/services/runeaudio.service');
            if (!file_exists('/etc/avahi/services/runeaudio.service')) {
                runelog('avahi service descriptor not present, initializing...');
                sysCmd('/usr/bin/cp /srv/http/app/config/defaults/avahi_runeaudio.service /etc/avahi/services/runeaudio.service');
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
                // nothing has changed, set avahi confchange off
                $redis->hSet('avahi', 'confchange', 0);
                sysCmd('rm -f '.$newfile);
            } else {
                // avahi configuration has changed, set avahi confchange on
                $redis->hSet('avahi', 'confchange', 1);
                sysCmd('cp '.$newfile.' '.$file);
                sysCmd('rm -f '.$newfile);
                // also modify /etc/hosts replace line beginning with 127.0.0.1 (PIv4)
                sysCmd('sed -i "/^127.0.0.1/c\127.0.0.1       localhost localhost.localdomain '.$hostname.'.local '.$hostname.'" /etc/hosts');
                // and line beginning with ::1 (IPv6)
                sysCmd('sed -i "/^::1/c\::1       localhost localhost.localdomain '.$hostname.'.local '.$hostname.'" /etc/hosts');
            }
            break;
    }
}

function wrk_control($redis, $action, $data)
{
    $jobID = "";
    // accept $data['action'] $data['args'] from controller
    switch ($action) {
        case 'noduplicates':
            // when noduplicates is specified a job is added only when no other $data['wrkcmd'] job exists
            foreach ($redis->hGetAll('w_queue') as $key => $wjob) {
                $wjob = json_decode($wjob, true);
                if ($wjob['wrkcmd'] == $data['wrkcmd']) {
                    $jobID = $key;
                    // break while and switch
                    break 2;
                }
            }
            // no break, treat as newjob
        case 'newjob':
            // generate random jobid
            $jobID = wrk_jobID();
            $wjob = array(
                'wrkcmd' => (isset($data['wrkcmd'])? $data['wrkcmd'] : NULL),
                'action' => (isset($data['action'])? $data['action'] : NULL),
                'args' => (isset($data['args'])? $data['args'] : NULL)
            );
            $redis->hSet('w_queue', $jobID, json_encode($wjob));
            // add the jobID to the fifo queue
            $redis->lPush('w_queue_fifo', $jobID);
            runelog('wrk_control data:', $redis->hGet('w_queue', $jobID));
            break;
    }
    // debug
    runelog('[wrk] wrk_control($redis,'.$action.',$data) jobID='.$jobID, json_encode($data), 'wrk_control');
    return $jobID;
}

// search a string in a file and for each occurrence replace the whole line with another string
// when $linelabel and $lineoffset are present, the replacement will only take place on the $lineoffset'th line following a line containing $linelabel
//  example: wrk_replaceTextLine($file, '', 'findstring', 'replaceline', 'findlabel', 2)
//  >> in file $file replace line containing 'findstring' with 'replaceline' on the 2nd line after each line containing 'findlabel'
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
    $linenum = 0;
    foreach($fileData as $line) {
        if (isset($linelabel) && isset($lineoffset)) {
            $linenum++;
            if (preg_match('/'.$linelabel.'/', $line)) {
                $lineindex = $linenum;
                runelog('line index match! $line', $lineindex);
            }
            if (isset($lineindex) && (($lineindex+$lineoffset)-$linenum)==0) {
                if (preg_match('/'.$strfind.'/', $line)) {
                    $line = $strrepl."\n";
                    runelog('internal loop $line', $line);
                    unset($lineindex);
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
    // create a diff file /home/config.txt.diff of /srv/http/app/config/defaults/boot/config.txt vs. <p1mountpoint>/config.txt
    sysCmd("diff -Nau '/srv/http/app/config/defaults/boot/config.txt' '".$redis->get('p1mountpoint')."/config.txt' >/home/config.txt.diff");
    // build up the backup command string
    if ($bktype === 'dev') {
        $filepath = $fileDestDir.'dev-backup-total-'.date("Y-m-d").'.tar.gz';
        $cmdstring = "rm -f '".$fileDestDir."backup-*' &> /dev/null ; redis-cli save ; \\\n".
            " bsdtar -c -z -p -f '".$filepath."' \\\n".
            " /mnt/MPD/Webradio \\\n".
            " /var/lib/redis/rune.rdb \\\n".
            " '".$redis->hGet('mpdconf', 'db_file')."' \\\n".
            " '".$redis->hGet('mpdconf', 'sticker_file')."' \\\n".
            " '".$redis->hGet('mpdconf', 'playlist_directory')."' \\\n".
            " '".$redis->hGet('mpdconf', 'state_file')."' \\\n".
            " /var/lib/connman";
        if (glob('/home/your-extra-mpd.conf')) {
            $cmdstring .= " /home/your-extra-mpd.conf \\\n";
        }
        $cmdstring .= " /srv/http".
            " /home/config.txt.diff".
            "";
        // for each distributed default file take a copy of the production version
        foreach ( sysCmd('find /srv/http/app/config/defaults/ -type f') as $bFile) {
            $bfile = str_replace('/srv/http/app/config/defaults', '', $bFile);
            clearstatcache(true, $bFile);
            if (is_file($bFile)) {
                $cmdstring .= " \\\n '".$bFile."'";
            }
        }
    } else {
        $filepath = $fileDestDir.'backup-'.date("Y-m-d").'.tar.gz';
        $cmdstring = "rm -f '".$fileDestDir."backup-*' &> /dev/null ; redis-cli save ; \\\n".
            " bsdtar -c -z -p -f '".$filepath."' \\\n".
            " /mnt/MPD/Webradio \\\n".
            " /var/lib/redis/rune.rdb \\\n".
            " '".$redis->hGet('mpdconf', 'db_file')."' \\\n".
            " '".$redis->hGet('mpdconf', 'sticker_file')."' \\\n".
            " '".$redis->hGet('mpdconf', 'playlist_directory')."' \\\n".
            " '".$redis->hGet('mpdconf', 'state_file')."' \\\n";
        if (glob('/var/lib/connman/wifi_*.config')) {
            $cmdstring .= " /var/lib/connman/wifi_*.config \\\n";
        }
        if (glob('/var/lib/connman/ethernet_*.config')) {
            $cmdstring .= " /var/lib/connman/ethernet_*.config \\\n";
        }
        if (glob('/home/your-extra-mpd.conf')) {
            $cmdstring .= " /home/your-extra-mpd.conf \\\n";
        }
        $cmdstring .= " /etc/mpd.conf".
            " /etc/samba \\\n".
            " /home/config.txt.diff".
            "";
    }
    ui_notify($redis, 'Backup', $cmdstring);
    // save the redis database
    $redis->save();
    // run the backup
    sysCmd($cmdstring);
    // delete the diff file for config.txt
    unlink('/home/config.txt.diff');
    // change the file privileges
    sysCmd('chown http:http '."'".$filepath."'".' ; chmod 644 '."'".$filepath."'");
    return $filepath;
}

function wrk_restore($redis, $backupfile)
{
    $fileDestDir = '/'.trim($redis->get('backup_dir'), "/ \t\n\r\0\x0B").'/';
    $lenDestDir = strlen($fileDestDir);
    if (substr($backupfile, 0, $lenDestDir) === $fileDestDir) {
        // only allow a restore from the backup directory
        ui_notify($redis, 'Restore backup starting', 'please wait for a restart...');
        sysCmd('/srv/http/command/restore.sh '.$backupfile);
        // a reboot will be initiated in restore.sh, it will never come back here
    } else {
        ui_notifyError($redis, 'Error', 'Attempted to restore from the incorrect directory: '.$backupfile);
        // delete the backup file, OK if this fails
        unlink($backupfile);
    }
    return;
}

function wrk_opcache($redis, $action)
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
        case 'reload':
            sysCmd('systemctl reload php-fpm');
            break;
        case 'enable':
            $fileNames = sysCmd('find /etc/php -name opcache.ini 2>/dev/null ; grep -Ril --binary-files=without-match "^opcache.enable=" /etc/php 2>/dev/null');
            foreach ($fileNames as $fileName) {
                // clear the file cache otherwise file_exists() returns incorrect values
                clearstatcache(true, $fileName);
                if (file_exists($fileName)) {
                    sysCmd("sed -i '/^opcache.enable=/c\opcache.enable=1' '".$fileName."'");
                }
            }
            $redis->set('opcache', 1);
            break;
        case 'disable':
            $fileNames = sysCmd('find /etc -name opcache.ini');
            foreach ($fileNames as $fileName) {
                // clear the file cache otherwise file_exists() returns incorrect values
                clearstatcache(true, $fileName);
                if (file_exists($fileName)) {
                    sysCmd("sed -i '/^opcache.enable=/c\opcache.enable=0' '".$fileName."'");
                }
            }
            $redis->set('opcache', 0);
            break;
        case 'isfull':
            $opCacheStatus = opcache_get_status();
            if (isset($opCacheStatus) && is_array($opCacheStatus)) {
                if (isset($opCacheStatus['opcache_enabled']) && $opCacheStatus['opcache_enabled']) {
                    if (isset($opCacheStatus['cache_full']) && $opCacheStatus['cache_full']) {
                        return true;
                    } else {
                        if (isset($opCacheStatus['memory_usage']['used_memory'])) {
                            // do nothing
                        } else {
                            return false;
                        }
                        if (isset($opCacheStatus['memory_usage']['free_memory'])) {
                            // do nothing
                        } else {
                            return false;
                        }
                        $memoryUsage = $opCacheStatus['memory_usage']['used_memory'] / ($opCacheStatus['memory_usage']['used_memory'] + $opCacheStatus['memory_usage']['free_memory']);
                        if ($memoryUsage >= 0.8 ) {
                            // treat 80% full as full
                            return true;
                        } else {
                            return false;
                        }
                    }
                } else {
                    return false;
                }
            } else {
                return false;
            }
            break;
        case 'add8mb':
            $opCacheConfiguration = opcache_get_configuration();
            if (isset($opCacheConfiguration) && is_array($opCacheConfiguration)) {
                if (isset($opCacheConfiguration['directives']['opcache.enable']) && $opCacheConfiguration['directives']['opcache.enable']) {
                    if (isset($opCacheConfiguration['directives']['opcache.memory_consumption'])) {
                        // do nothing
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
            } else {
                return false;
            }
            // convert total memory from B to MB
            $memory = $opCacheConfiguration['directives']['opcache.memory_consumption'] * (9.5367431640625*10**-7);
            // total memory rounded to units of 8MB, plus 8MB
            $memory = (round($memory/8)*8) + 8;
            // the opcache config file can be in different places depending on ARCH or RPiOS and the PHP version
            //  modify any files found
            $fileNames = sysCmd('find /etc -name opcache.ini');
            foreach ($fileNames as $fileName) {
                // clear the file cache otherwise file_exists() returns incorrect values
                clearstatcache(true, $fileName);
                if (file_exists($fileName)) {
                    if (sysCmd('grep -ic "opcache.memory_consumption=" "'.$fileName.'"')[0]) {
                        sysCmd("sed -i '/^opcache.memory_consumption=/c\opcache.memory_consumption=".$memory."' '".$fileName."'");
                    } else {
                        sysCmd('echo -en "opcache.memory_consumption='.$memory.'\n" >> "'.$fileName.'"');
                    }
                }
            }
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

function wrk_apconfig($redis, $action, $args = null, $jobID = null)
{
    runelog('wrk_apconfig args = ', $args);
    $return = '';
    switch ($action) {
        case 'writecfg':
            if (isset($args['ssid']) && $args['ssid'] && ($args['ssid'] != $redis->hGet('AccessPoint', 'ssid'))) {
                $redis->hSet('AccessPoint', 'ssid', $args['ssid']);
                $args['restart'] = 1;
            }
            if (isset($args['passphrase']) && $args['passphrase'] && ($args['passphrase'] != $redis->hGet('AccessPoint', 'passphrase'))) {
                $redis->hSet('AccessPoint', 'passphrase', $args['passphrase']);
                $args['restart'] = 1;
            }
            $ipAddressOld = $redis->hGet('AccessPoint', 'ip-address');
            if (isset($args['ip-address']) && $args['ip-address'] && ($args['ip-address'] != $redis->hGet('AccessPoint', 'ip-address'))) {
                $redis->hSet('AccessPoint', 'ip-address', $args['ip-address']);
                $args['restart'] = 1;
            }
            if (isset($args['broadcast']) && $args['broadcast'] && ($args['broadcast'] != $redis->hGet('AccessPoint', 'broadcast'))) {
                $redis->hSet('AccessPoint', 'broadcast', $args['broadcast']);
                $args['restart'] = 1;
            }
            if (isset($args['dhcp-range']) && $args['dhcp-range'] && ($args['dhcp-range'] != $redis->hGet('AccessPoint', 'dhcp-range'))) {
                $redis->hSet('AccessPoint', 'dhcp-range', $args['dhcp-range']);
                $args['restart'] = 1;
            }
            if (isset($args['dhcp-option-dns']) && $args['dhcp-option-dns'] && ($args['dhcp-option-dns'] != $redis->hGet('AccessPoint', 'dhcp-option-dns'))) {
                $redis->hSet('AccessPoint', 'dhcp-option-dns', $args['dhcp-option-dns']);
                $args['restart'] = 1;
            }
            if (isset($args['dhcp-option-router']) && $args['dhcp-option-router'] && ($args['dhcp-option-router'] != $redis->hGet('AccessPoint', 'dhcp-option-router'))) {
                $redis->hSet('AccessPoint', 'dhcp-option-router', $args['dhcp-option-router']);
                $args['restart'] = 1;
            }
            if ($args['restart'] == 1) {
                $message = "Configuration changed";
            }
            if (isset($args['enable-NAT']) && $args['enable-NAT'] && !$redis->hGet('AccessPoint', 'enable-NAT')) {
                // there is a value passed with $args and it is true and current state is false
                $redis->hSet('AccessPoint', 'enable-NAT', 1);
                $args['rescan'] = 1;
            } else if ((!isset($args['enable-NAT']) || !$args['enable-NAT']) && $redis->hGet('AccessPoint', 'enable-NAT')) {
                // there is a value passed with $args and it is false and current state is true
                $redis->hSet('AccessPoint', 'enable-NAT', 0);
                $args['rescan'] = 1;
            }
            if ($args['rescan'] == 1) {
                $message = "Configuration changed";
            }
            var_dump($args);
            if (isset($args['enable']) && $args['enable'] && !$redis->hGet('AccessPoint', 'enable')) {
                // enable requested, was disabled
                $redis->hSet('AccessPoint', 'enable', $args['enable']);
                unset_is_firstTime($redis, 'AP-start');
                $args['restart'] = 0;
                $args['rescan'] = 1;
                if (isset($message)) {
                    $message .= ' and enabled';
                } else {
                    $message = 'Enabled';
                }
            } else if ((!isset($args['enable']) || !$args['enable']) && $redis->hGet('AccessPoint', 'enable')) {
                // disable requested, was enabled
                $redis->hSet('AccessPoint', 'enable', 0);
                if ($redis->hGet('AccessPoint', 'NAT-configured')) {
                    // NAT is configured, remove the configuration
                    sysCmd('iptables -F');
                    sysCmd('iptables -t nat -F');
                    sysCmd('sysctl net.ipv4.ip_forward=0');
                    $redis->hSet('AccessPoint', 'NAT-configured', 0);
                }
                // stop the hostapd AP jobs if they are running
                sysCmd('pgrep hostapd && systemctl stop hostapd ; pgrep dnsmasq && systemctl stop dnsmasq');
                // stop the iwd access point
                sysCmd('iwctl ap '.$interface.' stop');
                // get the wlan nic used for accesspoint
                $wlanNic = $redis->hGet('AccessPoint', 'wlanNic');
                //
                // determine the AP wlan nic(s) by searching the ip addresses for the current and previous AP ip-address (these may have the same value)
                $wlanNics = explode(' ', sysCmd("ip -o add | grep -iE '".$ipAddressOld."|".$redis->hGet('AccessPoint', 'ip-address')."' | xargs | cut -d ' ' -f 2 | xargs")[0]);
                // check that the stored wlan nic is included in the wlan nics array
                $interface = $redis->hGet('AccessPoint', 'interface');
                if (isset($interface) && $interface && !in_array($interface, $wlanNics)) {
                    // not found in array, add it
                    $wlanNics[] = $interface;
                }
                // get the names of the physical nics
                $physNics = sysCmd('dir -1 /sys/class/net/');
                // process all the relevant wlan nics (normally one or none)
                foreach ($wlanNics as $wlanNicInterface) {
                    // just in case the use of virtual nics may have been switched, we don't use the redis hash 'AccessPoint'  'virtual_ap_dev'
                    //  to determine a physical or virtual nic
                    if (in_array($wlanNicInterface, $physNics)  && ($wlanNic == $wlanNicInterface)) {
                        // the nic is in the list of physical nics and its name is the same as the wlan nic
                        // flush the nic then take the Wi-Fi nic down and up, this will clear the AP from the nic
                        sysCmd('ip addr flush '.$wlanNicInterface.' ; ip link set dev '.$wlanNicInterface.' down ; ip link set dev '.$wlanNicInterface.' up');
                    } else {
                        // the nic is not in the list of physical nics or its name is different to the wlan nic
                        // delete the virtual nic
                        sysCmd('iw dev '.$wlanNicInterface.' del');
                    }
                }
                // comment out any lines in the iwd configuration file /etc/iwd/main.conf containing 'APRanges='
                sysCmd("sed -i /APRanges=/s/^/# / '/etc/iwd/main.conf'");
                // remove any iwd AP definition files
                sysCmd('mkdir -p /var/lib/iwd/ap/');
                sysCmd('rm /var/lib/iwd/ap/*.ap');
                // do we need to restart iwd? I don't think it is necessary - need to test
                // sysCmd('systemctl restart iwd');
                // unset the AP nic names
                $redis->hSet('AccessPoint', 'ethNic', '');
                $redis->hSet('AccessPoint', 'wlanNic', '');
                $redis->hSet('AccessPoint', 'interface', '');
                $args['restart'] = 0;
                if (isset($args['norescan']) && $args['norescan']) {
                    $args['rescan'] = 0;
                } else {
                    $args['rescan'] = 1;
                }
                if (isset($message)) {
                    $message .= ', and disabled';
                } else {
                    $message = 'Disabled';
                }
            }
            break;
        case 'reset':
            sysCmd('/srv/http/db/redis_datastore_setup apreset');
            wrk_getHwPlatform($redis);
            $args['restart'] = 1;
            $message = "Resetting Access Point to default values";
            break;
    }
    if (isset($jobID) && $jobID) {
        $redis->sRem('w_lock', $jobID);
    }
    if ((!isset($args['silent']) || !$args['silent']) && isset($message) && $message) {
        ui_notify($redis, 'AccessPoint', $message);
    }
    // reboot and restart can be selected in the UI
    // restart will be automatically deselected when not required
    // restart and rescan are automatically selected in this function when required
    if (isset($args['reboot']) && $args['reboot']) {
        // reboot requested from the UI
        runelog('**** AP reboot requested ****', $args);
        ui_notify($redis, 'AccessPoint', 'Reboot requested');
        $return = 'reboot';
    } else if (isset($args['restart']) && $args['restart']) {
        // a restart has been requested from the UI or automatically determined
        runelog('**** AP restart requested ****', $args);
        ui_notify($redis, 'AccessPoint', 'restarting the Access Point');
        ui_notify($redis, 'AccessPoint', 'the changed configuration will be activated, you may need to reconnect', '', 1);
        // stop the access point, by disabling it
        $restart_args = array();
        $restart_args['enable'] = 0;
        $restart_args['norescan'] = 1;
        $restart_args['silent'] = 1;
        // nat will automatically be disabled when the AP is stopped, save its current value
        $natSave = $redis->hGet('AccessPoint', 'enable-NAT');
        wrk_apconfig($redis, 'writecfg', $restart_args);
        // start the access point by enabling it
        $restart_args = array();
        $restart_args['enable'] = 1;
        $restart_args['silent'] = 1;
        // restore nat to its previous value
        $restart_args['enable-NAT'] = $natSave;
        wrk_apconfig($redis, 'writecfg', $restart_args);
    } else if (isset($args['rescan']) && $args['rescan']) {
        ui_notify($redis, 'AccessPoint', 'Applying changes');
        sysCmdAsync($redis, 'nice --adjustment=10 /srv/http/command/refresh_nics');
    }
    // the following lines use qrencode to generate a QR-code for the AP connect and browser URL (ip address)
    //  it looks neat, but is pretty useless because you need to connect to be able to see the codes!
    //  currently disabled, the UI will only display QR-codes for the default settings
    // sysCmd('qrencode -l H -t PNG -o /srv/http/assets/img/RuneAudioAP.png "WIFI:S:'.$args['ssid'].';T:WPA2;P:'.$args['passphrase'].';;"');
    // sysCmd('qrencode -l H -t PNG -o /srv/http/assets/img/RuneAudioURL.png http://'.$args['ip-address']);
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
            // the routine looks in the directory <p1mountpoint>/wifi for any files, all files will be processed, except:
            //  a file called readme and the directory <p1mountpoint>/wifi/examples and its contents
            // it steps through the files and or directories and deletes them after processing (regardless of success)
            // any file with lines containing 'Name=<value>' and 'Passphrase=<value>' will be used to set up a Wi-Fi profile
            // the optional value 'Hidden=[true]|[false]' will also be processed if present
            // multiple entries in the same file will be processed, a 'Name=<value>' starts the new network
            // the files can be added with a text editor when the Micro-SD card is plugged into a computer
            // get a list of files, ignoring the 'readme', 'examples', '.' and '..' file entries
            $profilearray = array();
            $counter = -1;
            $directory = $redis->get('p1mountpoint').'/wifi';
            $fileFound = false;
            $fileNames = array_diff(scandir($directory), array('..', '.', 'readme', 'examples'));
            if (count($fileNames) == 0) {
                // no files found, exit the switch case
                break;
            }
            foreach ($fileNames as $fileName) {
                // clear the cache otherwise is_dir() returns incorrect values
                clearstatcache(true, $directory.DIRECTORY_SEPARATOR.$fileName);
                if (is_dir($directory.DIRECTORY_SEPARATOR.$fileName)) {
                    // remove unknown directories
                    sysCmd('rmdir --ignore-fail-on-non-empty \''.$directory.DIRECTORY_SEPARATOR.$fileName.'\'');
                    continue;
                }
                $fileFound = true;
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
                if ($redis->get('network_ipv6')) {
                    $profileFileContent .= 'IPv6=auto'."\n";
                } else {
                    $profileFileContent .= 'IPv6=off'."\n";
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
            sysCmd('mkdir -p '.$directory.'/examples');
            sysCmd('cp /srv/http/app/config/defaults/boot/wifi/readme '.$directory.'/readme');
            sysCmd('cp /srv/http/app/config/defaults/boot/wifi/examples/* '.$directory.'/examples');
            if ($fileFound) {
                // set access point to default values
                wrk_apconfig($redis, 'reset');
                // set wifi on and reboot it required
                if (!$redis->get(wifi_on)) {
                    wrk_netconfig($redis, 'enableWifi');
                    ui_notify($redis, 'Wi-Fi reset', 'Restarting to enable Wi-Fi');
                    wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'reboot'));
                }
            }
            // restart connman to pick up the new config files
            sysCmd('systemctl restart connman');
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
            sysCmdAsync($redis, 'nice --adjustment=10 /srv/http/command/refresh_nics');
            break;
        case 'enableWifi':
            // run the command file to disable Wi-Fi, a reboot is required
            sysCmd('/srv/http/command/wifi_on.sh');
            break;
        case 'disableWifi':
            // run the command file to disable Wi-Fi, a reboot is required
            sysCmd('/srv/http/command/wifi_off.sh');
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
            if ($redis->get('network_ipv6')) {
                $profileFileContent .= 'IPv6=auto'."\n";
            } else {
                $profileFileContent .= 'IPv6=off'."\n";
            }
            if ($args['ipAssignment'] === 'DHCP') {
                if (isset($args['connmanString'])) {
                    $args['connmanString'] = trim($args['connmanString']);
                    if ($args['connmanString']) {
                        // make sure that connman has the correct values
                        if ($redis->get('network_ipv6')) {
                            // ipv6 is enabled
                            sysCmd('connmanctl config '.$args['connmanString'].' --ipv6 auto');
                        } else {
                            // ipv6 is disabled
                            sysCmd('connmanctl config '.$args['connmanString'].' --ipv6 off');
                        }
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
                if ($redis->get('network_ipv6')) {
                    // ipv6 is enabled
                    sysCmd('connmanctl config '.$args['connmanString'].' --ipv6 auto');
                } else {
                    // ipv6 is disabled
                    sysCmd('connmanctl config '.$args['connmanString'].' --ipv6 off');
                }
                sysCmd('connmanctl config '.$args['connmanString'].' --ipv4 dhcp');
                // take the nic down and bring it up to reset its ip-address
                sysCmd('ip link set dev '.$args['nic'].' down; ip link set dev '.$args['nic'].' up');
                wrk_netconfig($redis, 'refreshAsync');
            } else {
                // add a config file and stored profile
                // set up the profile array
                foreach ($args as $key => $value) {
                    if (strpos('|connmanString|', $key)) {
                        // omit some of the values
                        continue;
                    }
                    $storedProfiles[$macAddressKey][$key] = $value;
                }
                $storedProfiles[$macAddressKey]['technology'] = 'ethernet';
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
                clearstatcache(true, $profileFileName);
                if (!file_exists($profileFileName) || (md5_file($profileFileName) != md5_file($tmpFileName))) {
                    rename($tmpFileName, $profileFileName);
                    // take the nic down and bring it up to reset its ip-address
                    sysCmd('ip link set dev '.$args['nic'].' down; ip link set dev '.$args['nic'].' up');
                    wrk_netconfig($redis, 'refreshAsync');
                } else {
                    unlink($tmpFileName);
                }
            }
            break;
        case 'check_connman':
            // enables and disables ipv6 and corrects the all configuration files
            //  IPv6.privacy=disabled to IPv6.privacy=preferred
            // get the ipv6 setting
            $network_ipv6 = $redis->get('network_ipv6');
            // check the llmnrd ipv6 setting
            if ($redis->get('llmnrdipv6') != $network_ipv6) {
                // correct the llmnrd ipv6 setting
                $redis->set('llmnrdipv6', $network_ipv6);
            }
            // refresh the nics to refresh the 'network_info'
            refresh_nics($redis);
            // get the networks
            $network_info = json_decode($redis->get('network_info'), true);
            // set connman restart switch to false
            $restartConnman = 0;
            // walk through the networks
            foreach ($network_info as $network) {
                if (isset($network['configured']) && $network['configured']) {
                    // this is a configured network, check its ipv6 status
                    if (isset($network['ipv6.method']) && ($network['ipv6.method'] == 'auto') && !$network_ipv6) {
                        // network is configured for ipv6 and the setting is disabled, disable ipv6
                        if (isset($network['connmanString']) && isset($network['technology']) && ($network['technology'] == 'ethernet')) {
                            // the connman string is set and it is an ethernet connection
                            // change the configuration with connmanctl
                            sysCmd('connmanctl config '.$network['connmanString'].' --ipv6 off');
                        } else if (isset($network['ssidHex']) && isset($network['technology']) && ($network['technology'] == 'wifi')) {
                            // ssidHex is set and it is a wifi connection
                            // for wifi the config file needs to be changed
                            $configFile = '/var/lib/connman/wifi_'.$network['ssidHex'].'.config';
                            // check that the config file exists
                            clearstatcache(true, $configFile);
                            if (file_exists($configFile)) {
                                sysCmd("sed -i '/IPv6\s*\=\s*auto/s/.*/IPv6\=off/' '".$configFile."'");
                            }
                        }
                    } else if (isset($network['ipv6.method']) && ($network['ipv6.method'] == 'off') && $network_ipv6) {
                        // network is not configured for ipv6 and the setting is enabled, enable ipv6
                        if (isset($network['connmanString']) && isset($network['technology']) && ($network['technology'] == 'ethernet')) {
                            // the connman string is set and it is an ethernet connection
                            // change the configuration with connmanctl, but only for dhcp assigned networks (ipv6 is always disabled for static networks)
                            if (isset($network['ipAssignment']) && ($network['ipAssignment'] == 'DHCP')) {
                                sysCmd('connmanctl config '.$network['connmanString'].' --ipv6 auto');
                                // when ipv6 is switched off the IPv6.privacy=preferred is automatically set to IPv6.privacy=disabled
                                // test here if it needs to be set to IPv6.privacy=preferred
                                if (isset($network['macAddress'])) {
                                    // the mac address is set, its required for the config file name
                                    $configFile = '/var/lib/connman/ethernet_'.$network['macAddress'].'_cable/settings';
                                    // check that the config file exists
                                    clearstatcache(true, $configFile);
                                    if (file_exists($configFile)) {
                                        // determine whether the config file needs changing and if connman needs to be restarted
                                        $changeFile = sysCmd("grep -ic 'IPv6\.privacy\s*\=\s*disabled' '".$configFile."' | xargs")[0];
                                        if (!$restartConnman) {
                                            $restartConnman = $changeFile;
                                        }
                                        if ($changeFile) {
                                            sysCmd("sed -i '/IPv6\.privacy\s*\=\s*disabled/s/.*/IPv6\.privacy\=preferred/' '".$configFile."'");
                                        }
                                    }
                                }
                            }
                        } else if (isset($network['ssidHex']) && isset($network['technology']) && ($network['technology'] == 'wifi')) {
                            // ssidHex is set and it is a wifi connection
                            // for wifi the config file needs to be changed
                            $configFile = '/var/lib/connman/wifi_'.$network['ssidHex'].'.config';
                            // check that the config file exists
                            clearstatcache(true, $configFile);
                            if (file_exists($configFile)) {
                                sysCmd("sed -i '/IPv6\s*\=\s*off/s/.*/IPv6\=auto/' '".$configFile."'");
                            }
                        }
                    }
                    if (isset($network['technology']) && isset($network['ipv6.privacy']) && ($network['technology'] == 'ethernet') && ($network['ipv6.privacy'] == 'disabled')) {
                        // its an ethernet nic and ipv6 privacy is disabled, set IPv6.privacy=preferred
                        if (isset($network['macAddress'])) {
                            // the mac address is set, its required for the config file name
                            $configFile = '/var/lib/connman/ethernet_'.$network['macAddress'].'_cable/settings';
                            // check that the config file exists
                            clearstatcache(true, $configFile);
                            if (file_exists($configFile)) {
                                // determine whether the config file needs changing and if connman needs to be restarted
                                $changeFile = sysCmd("grep -ic 'IPv6\.privacy\s*\=\s*disabled' '".$configFile."' | xargs")[0];
                                if (!$restartConnman) {
                                    $restartConnman = $changeFile;
                                }
                                if ($changeFile) {
                                    sysCmd("sed -i '/IPv6\.privacy\s*\=\s*disabled/s/.*/IPv6\.privacy\=preferred/' '".$configFile."'");
                                }
                            }
                        }
                    }
                }
            }
            if ($restartConnman && ($arg != 'norestart')) {
                sysCmd('systemctl restart connman');
            }
            unset($network_ipv6, $network_info, $network, $configFile, $changeFile, $restartConnman);
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
                sysCmd('systemctl stop connman');
                unset($storedProfiles[$ssidHexKey]);
                unlink('/var/lib/connman/wifi_'.$args['ssidHex'].'.config');
                sysCmd('rm -rf \'/var/lib/connman/wifi_*'.$args['ssidHex'].'\'');
                sysCmd("iwctl known-networks '".$args['ssid']."' forget");
                sysCmd('rm -f \'/var/lib/iwd/'.$args['ssid'].'.*\'');
                sysCmd('systemctl start connman');
            }
            // ethernet
            if (isset($args['macAddress']) && isset($storedProfiles[$macAddressKey])) {
                sysCmd('systemctl stop connman');
                unset($storedProfiles[$macAddressKey]);
                unlink('/var/lib/connman/ethernet_'.$args['macAddress'].'.config');
                sysCmd('rm -rf \'/var/lib/connman/ethernet_'.$args['macAddress'].'\'');
                sysCmd('systemctl start connman');
            }
            $redis->set('network_storedProfiles', json_encode($storedProfiles));
            break;
        case 'reset':
            // delete all stored profiles and configuration files and restore the system defaults
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
            // instruct iwd to forget all its known networks
            $iwdNetworks = sysCmd("iwctl known-networks list | tail -n +5 | cut -b 6-40 | sed 's/^[[:space:]]*//;s/[[:space:]]*$//'");
            foreach ($iwdNetworks as $iwdNetwork) {
                sysCmd("iwctl known-networks '".$iwdNetwork."' forget");
            }
            // delete all connman & iwd config files
            sysCmd('rm -rf /var/lib/iwd/*');
            sysCmd('rm -rf /var/lib/connman/*');
            // restore the default connman configuration file
            sysCmd('mkdir -p /var/lib/connman');
            sysCmd('mkdir -p /var/lib/iwd');
            sysCmd('cp /srv/http/app/config/defaults/var/lib/connman/settings /var/lib/connman/settings');
            sysCmd('chmod 600 /var/lib/connman/settings');
            // restore the default boot-initialise Wi-Fi files
            $directory = $redis->get('p1mountpoint').'/wifi';
            sysCmd('mkdir -p '.$directory.'/examples');
            sysCmd('cp /srv/http/app/config/defaults/boot/wifi/readme '.$directory.'/readme');
            sysCmd('cp /srv/http/app/config/defaults/boot/wifi/examples/* '.$directory.'/examples');
            // restore the standard service and config files
            sysCmd('cp /srv/http/app/config/defaults/etc/systemd/system/connman.service /etc/systemd/system/connman.service');
            sysCmd('mkdir /etc/connman/');
            sysCmd('cp /srv/http/app/config/defaults/etc/connman/* /etc/connman/');
            // start connman
            sysCmd('systemctl daemon-reload ; systemctl start connman');
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
    if ($check_mp) {
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
    sysCmd('chown http:http '.$file);
    sysCmd('chmod 644 '.$file);
}

function wrk_audioOutput($redis, $action)
{
    switch ($action) {
        case 'refresh':
            // get a list of the hardware audio cards
            $cardlist = array();
            $cardlistHDMIvc4 = array();
            // while alsa is starting aplay -l may return errors, the following routine loops until the correct information is returned
            //  loop for maximum 30 cycles with a sleep of 2 seconds (= 1 minute)
            $countErr = 1;
            $cnt = 30;
            while ($countErr && ($cnt-- >= 0)) {
                $countErr = sysCmd('aplay -l -v 2>&1 | grep -ic "Cannot access file" | xargs')[0];
                if ($countErr) {
                    sleep(2);
                }
            }
            unset($countErr, $cnt);
            // note: eliminate HW HDMI vc4 cards, they wont work correctly, software vc4 cards are added at the end of the function
            //  also eliminate loopback card definitions, these are used for internal routing of the sound path
            //  vc4 HDMI cards appear regardless of attached device or setting on-board cards off, ignore these when on board cards is set off
            if ($redis->exists('hdmivc4hw') && (($redis->get('hdmivc4hw') == 1) || ($redis->get('hdmivc4hw') == 3) || ($redis->get('hdmivc4hw') == 4))) {
                // handle vc4 as hardware cards
                if ($redis->get('audio_on_off')) {
                    // all cards when on-board audio is enabled
                    $cardlist = sysCmd('aplay -l -v | grep -i "^card " | grep -vi "loopback"');
                } else {
                    // eliminate hdmi cards when on-board audio is disabled
                    $cardlist = sysCmd('aplay -l -v | grep -i "^card " | grep -vi "loopback" | grep -vi "hdmi"');
                }
            } else {
                // eliminate vc4 from the hardware cards
                $cardlist = sysCmd('aplay -l -v | grep -i "^card " | grep -vi "vc4" | grep -vi "loopback"');
                // handle vc4 as software cards, but only when on-board audio is enabled
                if ($redis->get('audio_on_off')) {
                    // get a separate list of SW HDMI vc4 cards
                    if (!$redis->exists('hdmivc4hw') || $redis->get('hdmivc4hw') == 2) {
                        // search for 'default'
                        $cardlistHDMIvc4 = sysCmd('aplay -L -v | grep -i "^default" | grep -i "vc4" | grep -i hdmi');
                    }
                    foreach ($cardlistHDMIvc4 as $key => $cardHDMIvc4) {
                        if (!sysCmd("amixer -D ".$cardHDMIvc4." scontrols | grep -ic pcm | xargs")[0]) {
                            unset($cardlistHDMIvc4[$key]);
                        }
                    }
                }
            }
            $acards = array();
            // reformat the output of the card list
            $cardChange = false;
            if (is_array($cardlist)) {
                foreach ($cardlist as $card) {
                    $cardNr=get_between_data($card, 'card', ':');
                    // some cards have multiple devices, use the first one
                    if (!isset($acards[$cardNr]['number'])) {
                        // first time for the card number, use this one
                        $acards[$cardNr]['number'] = $cardNr;
                        $acards[$cardNr]['device'] = get_between_data($card, ', device', ':');
                        $acards[$cardNr]['sysname'] = get_between_data($card, '[', ']');
                        $acards[$cardNr]['sysdesc'] = get_between_data($card, '[', ']', 2);
                        // check to see if the individual cards have changed
                        if (!$cardChange) {
                            if (!$redis->hexists('acards', $acards[$cardNr]['sysname'])) {
                                $cardChange = true;
                            } else {
                                $cardDet = array();
                                $cardDet = json_decode($redis->hget('acards', $acards[$cardNr]['sysname']), true);
                                if (get_between_data($cardDet['device'], ':', ',') != $cardNr) {
                                    $cardChange = true;
                                }
                            }
                        }
                    }
                }
            }
            unset($card, $cardDet);
            //
            if (!$cardChange  && is_array($cardlistHDMIvc4)) {
                foreach ($cardlistHDMIvc4 as $card) {
                    $cardname = get_between_data($card, '=');
                    if (!$redis->hExists('acards', $cardname)) {
                        $cardChange = true;
                        break;
                    }
                }
            }
            unset($card, $cardname);
            //
            if (!$cardChange) {
                $acardsOldKeys = $redis->hKeys('acards');
                $cardlistJson = json_encode($cardlist);
                $cardlistHDMIvc4Json = json_encode($cardlistHDMIvc4);
                if (is_array($acardsOldKeys)) {
                    foreach ($acardsOldKeys as $acardsOldKey) {
                        if (!strpos($cardlistJson, $acardsOldKey) && !strpos($cardlistHDMIvc4Json, $acardsOldKey)) {
                            $cardChange = true;
                            break;
                        }
                    }
                } else if (count($cardlist) || count($cardlistHDMIvc4)) {
                    $cardChange = true;
                }
            }
            unset($acardsOldKeys, $acardsOldKey, $cardlistJson, $cardlistHDMIvc4Json);
            //
            if (!$cardChange) {
                return 'unchanged';
            }
            //
            $redis->del('acards');
            //
            // load the table of allowed formats which are valid for specific combinations of overlays
            $fileName = '/srv/http/db/audio_allowed_formats_table.txt';
            $allowedFormats = array();
            if (file_exists($fileName)) {
                $fileContent = file_get_contents($fileName);
                $fileContentLines = explode("\n", $fileContent);
                foreach ($fileContentLines as $fileContentLine) {
                    if ($fileContentLine) {
                        list($overlay, $name, $sysname, $content) = explode('|', $fileContentLine, 4);
                        if (isset($content) && $content) {
                            $allowedFormats[$overlay.'|'.$name.'|'.$sysname] = $content;
                        }
                    }
                    unset($overlay, $name, $sysname, $content);
                }
                unset($fileContent, $fileContentLines, $fileContentLine);
            }
            unset($filename);
            // this is used as the first part of the index of $allowedFormats, the sysname needs to be added
            $activeOverlayAndName = $redis->get('i2smodule_select').'|';
            //
            foreach ($acards as $card) {
                unset($sub_interfaces);
                $sub_interfaces = array();
                unset($data);
                $data = array();
                // acards loop
                runelog('>>--------------------------- card number '.$card['number'].' name: '.$card['sysname'].' (start) --------------------------->>');
                //$card_index = explode(' : ', $card, 2);
                //$card_index = trim($card_index[0]);
                //$card_index = $card['number'];
                //$card = explode(' - ', $card, 2);
                //$card = trim($card[1]);
                // $description = sysCmd("grep -his ':' /proc/asound/cards | cut -d ':' -f 2 | cut -d ' ' -f 4-20");
                // debug
                //$card = $card['sysname'];
                runelog('wrk_audioOutput card string: ', $card['sysname']);
                //$description = sysCmd("aplay -l -v | grep \"\[".$card."\]\"");
                //$subdeviceid = explode(':', $description[0]);
                //$subdeviceid = explode(',', trim($subdeviceid[1]));
                //$subdeviceid = explode(' ', trim($subdeviceid[1]));
                //$data['device'] = 'hw:'.$card_index.','.$subdeviceid[1];
                $data['device'] = 'hw:'.$card['number'].','.$card['device'];
                // get the hardware platform descriptor,format is two numeric characters, eg 01, 02, 03, etc
                $hwplatformid = $redis->get('hwplatformid');
                // read the matching predefined configuration for this audio card
                $acards_details = $redis->hGet('acards_details', $card['sysname']);
                // check that the card details are valid
                if (isset($acards_details) && $acards_details) {
                    $details = json_decode($acards_details, true);
                    // check that both sysname and hwplatformid are set
                    if (!isset($details['sysname'])) {
                        // not set, reset the details, cant use this one
                        $details['sysname'] = '';
                        $acards_details = '';
                    }
                    if (!isset($details['hwplatformid'])) {
                        // not set, card is valid for all platforms, set it to the valid value
                        $details['hwplatformid'] = $hwplatformid;
                    }
                    // check that the sysname is the one we want and that the hardware platform matches
                    //  $details['hwplatformid'] can contain multiple hardware platform descriptors, vertical line delimited, eg '01|08'
                    //  hardware platform descriptors ($hwplatformid) is a two character numeric string, eg 01, 02, 03, etc
                    if (($details['sysname'] != $card['sysname']) || !strpos('|'.$details['hwplatformid'], $hwplatformid)) {
                        // not found, reset the details
                        $acards_details = '';
                    }
                } else {
                    $acards_details = '';
                }
                // when no card is found try to determine a card-name in the table with a postfix
                //  when the same card is defined for more hardware types with differing properties, they have a postfix in the key
                //  name, which makes them unique
                //  the sysname must be the name of the card we are looking for and the hwplatformid must match the current hardware
                // this is the only place where hwplatformid is used, normally a match on the array key is enough regardless of the
                //  hwplatformid value
                if (!$acards_details) {
                    // card not found, collect the acard table keys
                    $acards_keys = $redis->hKeys('acards_details');
                    foreach ($acards_keys as $acards_key) {
                        // try to find a matching key
                        if (strpos(' '.$acards_key, $card['sysname']) == 1) {
                            // the key matches, possibly with some sort of postfix, get the details
                            $acards_details = $redis->hGet('acards_details', $acards_key);
                            $details = json_decode($acards_details, true);
                            // check that both sysname and hwplatformid are set
                            if (!isset($details['sysname'])) {
                                // not set, reset the details, cant use this one
                                $acards_details = '';
                                continue;
                            } else if (!isset($details['hwplatformid'])) {
                                // not set, card is valid for all platforms, set it to the valid value
                                $details['hwplatformid'] = $hwplatformid;
                            }
                            // check that the sysname is the one we want and that the hardware platform matches
                            //  $details['hwplatformid'] can contain multiple hardware platform descriptors, vertical line delimited, eg '01|08'
                            //  hardware platform descriptors ($hwplatformid) is a two character numeric string, eg 01, 02, 03, etc
                            if (($details['sysname'] != $card['sysname']) && strpos('|'.$details['hwplatformid'], $hwplatformid)) {
                                // found, break the loop
                                break;
                            } else {
                                // not found, reset the details
                                $acards_details = '';
                            }
                        }
                    }
                }
                // use the predefined configuration for this card or generate one from the system information
                unset($details);
                $details = array();
                if (!$acards_details) {
                    // no predefined configuration for this card use the available information
                    $details['sysname'] = $card['sysname'];
                    $details['extlabel'] = $card['sysdesc'];
                    $details['hwplatformid'] = $redis->get('hwplatformid');
                    // debug
                    // echo "details['sysname']: '".$details['sysname']."'\n";
                    // echo "details['extlabel']: '".$details['extlabel']."'\n";
                    // echo "details['hwplatformid']: '".$details['hwplatformid']."'\n";
                    if (substr($card['sysname'], 0, 8) == 'bcm2835 ') {
                        // these are the on-board standard audio outputs
                        $details['type'] = 'integrated';
                    } else if (substr($card['sysname'], 0, 4) == 'vc4-') {
                        // these are the on-board standard audio outputs
                        $details['type'] = 'integrated';
                    } else if (substr($card['sysname'], 0, 3) == 'vc4') {
                        // these are the on-board standard audio outputs
                        $details['type'] = 'integrated';
                    } else {
                        // try to identify USB devices
                        $usbDevicesNames = preg_replace('/\s*iProduct\s*.\s*/', '|', implode(' ', sysCmd('lsusb -v 2>/dev/null | grep -i "iProduct"'))).'|';
                        if (strpos($usbDevicesNames, $card['sysname'])) {
                            // its an usb device
                            $details['type'] = 'usb';
                        } else if ($redis->get('i2smodule') != 'none') {
                            // UI assigned soundcard, assume this is the i2s device, there is only one present
                            $details['type'] = 'i2s';
                        } else if (!sysCmd('grep -ic "^#dtoverlay=none" \''.$redis->get('p1mountpoint').'/config.txt\'')[0]) {
                            // manually assigned soundcard, assume this is the i2s device, there is only one present
                            $details['type'] = 'i2s';
                        } else {
                            $details['type'] = 'unknown';
                        }
                    }
                } else {
                    // using the predefined configuration
                    // echo "acard_details:\n$acards_details\n";
                    $details = json_decode($acards_details, true);
                }
                // determine the description
                if ((!isset($details['description']) || !$details['description']) && isset($details['type']) && $details['type']) {
                    if ($details['type'] == 'integrated') {
                        if (isset($details['sysname']) && $details['sysname']) {
                            if (strpos(' '.$details['sysname'], 'vc4') == 1) {
                                $details['description'] = 'Raspberry Pi: '.decode_vc4_hdmi_desc($details['sysname']);
                            } else if (strpos(' '.$details['sysname'], 'bcm2835 ') == 1) {
                                $details['description'] = 'Raspberry Pi: '.trim(substr($details['sysname'], 8));
                            } else {
                                $details['description'] = 'Raspberry Pi: '.trim($details['sysname']);
                            }
                        } else if (isset($details['extlabel']) && $details['extlabel']) {
                            if (strpos(' '.$details['extlabel'], 'vc4') == 1) {
                                $details['description'] = 'Raspberry Pi: '.decode_vc4_hdmi_desc($details['extlabel']);
                            } else if (strpos(' '.$details['extlabel'], 'bcm2835 ') == 1) {
                                $details['description'] = 'Raspberry Pi: '.trim(substr($details['extlabel'], 8));
                            } else {
                                $details['description'] = 'Raspberry Pi: '.trim($details['extlabel']);
                            }
                        }
                    } else if ($details['type'] == 'i2s') {
                        // save the name as defined in the UI when selecting this card
                        $i2sModule = $redis->get('i2smodule');
                        if ($i2sModule && ($i2sModule != 'none')) {
                            $details['description'] = 'Soundcard: '.trim(explode('|', $redis->get('i2smodule_select'), 2)[1]);
                        } else {
                            // otherwise set the description to default, could happen when manually configured
                            if (isset($details['extlabel']) && $details['extlabel']) {
                                $details['description'] = 'Soundcard: '.$details['extlabel'];
                            } else if (isset($details['sysname']) && $details['sysname']) {
                                $details['description'] = 'Soundcard: '.$details['sysname'];
                            }
                        }
                    } else if (($details['type'] == 'usb')) {
                        // its a USB DAC
                        if (isset($details['sysname']) && $details['sysname']) {
                            $details['description'] = 'USB: '.$details['sysname'];
                        } else if (isset($details['extlabel']) && $details['extlabel']) {
                            $details['description'] = 'USB: '.$details['extlabel'];
                        }
                    }
                }
                if (!isset($details['description']) || !$details['description']) {
                    if (isset($details['extlabel']) && $details['extlabel']) {
                        $details['description'] = 'X: '.$details['extlabel'];
                    } else if (isset($details['sysname']) && $details['sysname']){
                        // no idea what this card is, use its system description
                        $details['description'] = 'X: '.$card['sysname'];
                    } else {
                        $details['description'] = 'X: Unknown sound device';
                    }
                }
                // when a mixer number ID is specified check its validity
                if (isset($details['mixer_numid']) && is_numeric($details['mixer_numid']) && strlen($details['mixer_numid'])) {
                    // mixer number ID is specified, check that it is a valid number
                    $retval = sysCmd('amixer controls -c '.$card['number'].' | grep -ic "numid='.$details['mixer_numid'].'"');
                    if(!isset($retval) || !is_array($retval) || !$retval[0]) {
                        // not found, unset the value
                        unset($details['mixer_numid']);
                    }
                    unset($retval);
                }
                // when the mixer number ID is not defined, sometimes it can be derived (generally this value is not used)
                if (!isset($details['mixer_numid']) || !is_numeric($details['mixer_numid']) || !strlen($details['mixer_numid'])) {
                    // mixer number ID is missing
                    $retval = sysCmd('amixer controls -c '.$card['number'].' | grep -i "playback volume"');
                    if (isset($retval) && is_array($retval) && count($retval) == 1) {
                        // one value returned, so use it
                        $details['mixer_numid'] = get_between_data($retval[0], 'numid=', ',');
                    } else {
                        // sometimes there is a analogue and digital volume control, we may be able to identify the digital one
                        $retval = sysCmd('amixer controls -c '.$card['number'].' | grep -i "playback volume" | grep -i "digital"');
                        if (isset($retval) && is_array($retval) && count($retval) == 1) {
                            // one value returned, so use it
                            $details['mixer_numid'] = get_between_data($retval[0], 'numid=', ',');
                        }
                    }
                    if (isset($details['mixer_numid']) && (!is_numeric($details['mixer_numid']) || !strlen($details['mixer_numid']))) {
                        // mixer id is set but not to a valid value, unset it
                        unset($details['mixer_numid']);
                    }
                    unset($retval);
                }
                // when a mixer control is specified check its validity
                if (isset($details['mixer_control']) && $details['mixer_control']) {
                    // mixer control is specified, check that it is valid
                    $retval = sysCmd('amixer scontrols -c '.$card['number'].' | grep -ic "'.$details['mixer_control'].'"');
                    if(isset($retval) && is_array($retval) && $retval[0]) {
                        // it is valid, do nothing
                    } else {
                        // not found, unset the value
                        unset($details['mixer_control']);
                    }
                    unset($retval);
                }
                // when the mixer control name is not defined, sometimes it can be derived (this value is always used when available)
                if (!isset($details['mixer_control']) || !$details['mixer_control']) {
                    // mixer control is missing try to derive it
                    $retval = sysCmd('amixer scontents -c '.$card['number'].' | grep -iE "simple|pvolume|limits"');
                    $pvolumeFound = false;
                    $limitsFound = false;
                    $singleLimitsFound = false;
                    $validMixerControls = array();
                    foreach ($retval as $retline) {
                        // clean up a version of the return line for testing, single space begin and end, replace whitespace to single space, lower case
                        $retlineTest = ' '.strtolower(trim(preg_replace('/[\s]+/', ' ', $retline))).' ';
                        if (substr($retlineTest, 1, 6) === 'simple') {
                            $mixerControl = get_between_data($retline, "'", "'");
                            $pvolumeFound = false;
                            $limitsFound = false;
                            $singleLimitsFound = false;
                        }
                        if (strpos($retlineTest, 'pvolume ')) {
                            $pvolumeFound = true;
                        }
                        if (strpos($retlineTest, 'limits') && !strpos($retlineTest, ': 0 - 1 ')) {
                            // limits need to be non '0 - 1'
                            $limitsFound = true;
                        }
                        if (strpos($retlineTest, 'limits') && (substr_count($retlineTest, ' - ') == 1)) {
                            // only  valid when there is one set of limits
                            $singleLimitsFound = true;
                        }
                        if (isset($mixerControl) && $mixerControl && $pvolumeFound && $limitsFound && $singleLimitsFound) {
                            $validMixerControls[] = $mixerControl;
                        }
                    }
                    if (count($validMixerControls) == 1) {
                        // one valid value found, so use it
                        $details['mixer_control'] = reset($validMixerControls);
                    } else if (count($validMixerControls)) {
                        foreach ($validMixerControls as $key => $validMixerControl) {
                            $validMixerControl = strtolower($validMixerControl);
                            // eliminate 'mic' (microphone) volume controls
                            if (strpos(' '.$validMixerControl, 'mic')) {
                                unset($validMixerControls[$key]);
                            }
                            // eliminate 'input' volume controls
                            if (strpos(' '.$validMixerControl, 'input')) {
                                unset($validMixerControls[$key]);
                            }
                        }
                        if (count($validMixerControls) == 1) {
                            $details['mixer_control'] = reset($validMixerControls);
                        } else {
                            ui_notify($redis, 'Automatic audio-card detection', 'Failed for \''.$details['sysname'].'\'. Please report this on the forum', '', 1);
                        }
                    }
                    unset ($retval, $retline, $mixerControl, $pvolumeFound, $limitsFound, $validMixerControl, $validMixerControls, $cardCnt);
                }
                // add allowed formats for hdmi cards, when no other card options are specified
                //  allowed formats (example syntax: allowed_formats = "96000:16:* 192000:24:* dsd64:=dop *:dsd:")
                //      HDMI, valid audio format: sample rates 32 kHz, 44.1 kHz, 48 kHz, 88.2 kHz, 96 kHz, 176.4 kHz, or 192 at 16 bits,
                //          20 bits, or 24 bits at up to 8 channels
                //      we choose to run at 16 or 24 bit, with any number channels for the specified sample rates
                if ((!isset($details['card_option']) || !$details['card_option']) && strpos(' '.strtolower($details['sysname']), 'hdmi')) {
                    // add allowed formats to the card options
                    $hwplatformid = $redis->get('hwplatformid');
                    if ($hwplatformid == '08') {
                        $details['card_option'] = "allowed_formats \"192000:24:* 44100:24:* 48000:24:* 32000:24:* 88200:24:* 176400:24:* 96000:24:* 96000:16:* 44100:16:* 48000:16:* 32000:16:* 88200:16:* 176400:16:* 192000:16:*\"";
                    } else {
                        $details['card_option'] = "allowed_formats \"96000:24:* 44100:24:* 48000:24:* 32000:24:* 88200:24:* 176400:24:* 192000:24:* 96000:16:* 44100:16:* 48000:16:* 32000:16:* 88200:16:* 176400:16:* 192000:16:*\"";
                    }
                }
                if (isset($details['sysname']) && $details['sysname']) {
                    // a card has been determined, process it
                    // debug
                    runelog('wrk_audioOutput: in loop: acards_details for: '.$card['sysname'], json_encode($details));
                    //$details = new stdClass();
                    // debug
                    runelog('wrk_audioOutput: in loop: (decoded) acards_details for: '.$card['sysname'], $details['extlabel']);
                    if (isset($details['mixer_control']) && $details['mixer_control']) {
                        //$volsteps = sysCmd("amixer -c ".$card_index." get \"".$details['mixer_control']."\" | grep Limits | cut -d ':' -f 2 | cut -d ' ' -f 4,6");
                        //$volsteps = sysCmd("amixer -c ".$card_index." get \"".$details['mixer_control']."\" | grep Limits | cut -d ':' -f 2 | cut -d ' ' -f 3,5");
                        //$volsteps = explode(' ', $volsteps[0]);
                        $volsteps = sysCmd("amixer -c ".$card['number']." get \"".$details['mixer_control']."\" | grep -i limits:");
                        if (isset($volsteps[0]) && $volsteps[0]) {
                            $volsteps = explode(' - ',preg_replace('/[^0-9- ]/', '', trim($volsteps[0])));
                            if (isset($volsteps[0])) $data['volmin'] = trim($volsteps[0]);
                            if (isset($volsteps[1])) $data['volmax'] = trim($volsteps[1]);
                        }
                        // $data['mixer_device'] = "hw:".$details['mixer_numid'];
                        $data['mixer_device'] = "hw:".$card['number'];
                        $data['mixer_control'] = $details['mixer_control'];
                    }
                    // if its a vc4 hdmi card and hdmivc4hw is set to 3 (process as hardware & set mixer_control to PCM) add PCM as mixer_control
                    if (strpos(' '.strtolower($details['sysname']), 'hdmi') && strpos(' '.strtolower($details['sysname']), 'vc4') && $redis->exists('hdmivc4hw') && ($redis->get('hdmivc4hw') == 3)) {
                        $data['mixer_control'] = 'PCM';
                        unset($data['mixer_device']);
                    }
                    if (isset($details['sysname']) && ($details['sysname'] === $card['sysname'])) {
                        if ($details['type'] === 'integrated_sub') {
                            $sub_interfaces = $redis->sMembers($card['sysname']);
                            // debug
                            runelog('line 3440: (sub_interfaces loop) card: '.$card['sysname'], $sub_interfaces);
                            foreach ($sub_interfaces as $sub_interface) {
                                runelog('line 3442: (sub_interfaces foreach) card: '.$card['sysname'], $sub_interface);
                                //$sub_int_details = new stdClass();
                                $sub_int_details = array();
                                $sub_int_details = json_decode($sub_interface, true);
                                runelog('line 2449: (sub_interfaces foreach json_decode) card: '.$card['sysname'], $sub_int_details);
                                $sub_int_details['device'] = $data['device'];
                                $sub_int_details['name'] = $card['sysname'].'_'.$sub_int_details['id'];
                                $sub_int_details['description'] = $sub_int_details['extlabel'];
                                $sub_int_details['type'] = 'alsa';
                                $sub_int_details['integrated_sub'] = 1;
                                // prepare data for real_interface record
                                $data['sysname'] = $card['sysname'];
                                $data['type'] = 'alsa';
                                //$data['system'] = trim($card['sysdesc']);
                                // write real_interface json (use this to create the real MPD output)
                                $sub_int_details['real_interface'] = json_encode($data);
                                // replace index string in route command
                                if (isset($sub_int_details['route_cmd'])) $sub_int_details['route_cmd'] = str_replace("*CARDID*", $card['number'], $sub_int_details['route_cmd']);
                                // debug
                                runelog('::::::sub interface record array:::::: ',json_encode($sub_int_details));
                                $redis->hSet('acards', $card['sysname'].'_'.$sub_int_details['id'], json_encode($sub_int_details));
                            }
                        }
                        // if ($details['extlabel'] !== 'none') $data['extlabel'] = $details['extlabel'];
                        if (isset($details['extlabel']) && $details['extlabel'] !== 'none') {
                            runelog('::::::acard extlabel:::::: ', $details['extlabel']);
                            $data['extlabel'] = $details['extlabel'];
                        }
                    }
                    // debug
                    if (isset($data['extlabel'])) runelog('wrk_audioOutput: in loop: extlabel for: '.$card['sysname'], $data['extlabel']);
                    // test if there is an option for mpd.conf set
                    // for example ODROID C1 needs "card_option":"buffer_time\t\"0\""
                    if (isset($details['card_option']) && $details['card_option']) {
                        $data['card_option'] = $details['card_option'];
                    }
                    // test if there is a set of allowed formats for this card
                    // for example the ES9023 audio card expects 24 bit input
                    if (isset($allowedFormats[$activeOverlayAndName.$card['sysname']]) && $allowedFormats[$activeOverlayAndName.$card['sysname']]) {
                        $data['allowed_formats'] = $allowedFormats[$activeOverlayAndName.$card['sysname']];
                    }
                }
                // when hdmivc4hw = 4 (process as hardware but use the software device name)
                //  remove the vc4 hdmi card when no mixer control has been found, this means that it is not connected
                //  change the device name to the software device
                if (($redis->get('hdmivc4hw') == 4) && strpos(' '.strtolower($details['sysname']), 'hdmi') && strpos(' '.strtolower($details['sysname']), 'vc4')) {
                    if (!isset($details['mixer_control']) || !$details['mixer_control']) {
                        // this vc4 hdmi card is not connected, skip it
                        unset($details, $data, $card);
                        continue;
                    }
                    // format of the software device is 'sysdefault:CARD=' followed by the sysname with all hyphens, underscores and whitespace removed
                    $swDevice = 'sysdefault:CARD='.preg_replace('/[\s_-]+/' ,'' , $details['sysname']);
                    // check that the software device is defined
                    if (sysCmd("aplay -L | grep -c '^".$swDevice."' | xargs")[0]) {
                        // its there, so use it
                        $data['device'] = $swDevice;
                    } else {
                        // just in case it goes wrong
                        ui_notify($redis, 'vc4 hdmi audio', 'Failed to determine the software device name: "'.$details['sysname'].'", "'.$swDevice.'". Please report this on the forum', '', 1);
                    }
                }
                if (!isset($sub_interfaces[0]) || (!$sub_interfaces[0])) {
                    $data['sysname'] = $card['sysname'];
                    $data['type'] = 'alsa';
                    $data['description'] = $details['description'];
                    //$data['system'] = trim($card['sysdesc']);
                    // debug
                    // runelog('::::::acard record array::::::', $data);
                    $redis->hSet('acards', $card['sysname'], json_encode($data));
                }
                // acards loop
                runelog('<<--------------------------- card: '.$card['sysname'].' index: '.$card['number'].' (finish) ---------------------------<<');
            }
            // extra processing for HDMI cards
            //  allowed formats (example syntax: allowed_formats = "96000:16:* 192000:24:* dsd64:=dop *:dsd:")
            //      HDMI, valid audio format: sample rates 32 kHz, 44.1 kHz, 48 kHz, 88.2 kHz, 96 kHz, 176.4 kHz, or 192 at 16 bits,
            //          20 bits, or 24 bits at up to 8 channels
            //      we choose to run at 16 or 24 bit, with any number channels for the specified sample rates
            // first delete any existing HDMI vc4 cards, when hdmivc4hw is unset or = 2
            //  unset = use 'default' software definition without PCM, 2 = use 'default' software definition with PCM
            if (!$redis->exists('hdmivc4hw') || ($redis->get('hdmivc4hw') == 2)) {
                $acardKeys = $redis->hKeys('acards');
                foreach ($acardKeys as $acardKey) {
                    $acardKeyLower = ' '.strtolower($acardKey);
                    if (strpos($acardKeyLower, 'hdmi')) {
                        if (strpos($acardKeyLower, 'vc4')) {
                            // delete any hardware HDMI vc4 cards, delete this one
                            $redis->hDel('acards', $acardKey);
                        }
                    }
                }
            }
            // now add the HDMI vc4 cards
            if (is_array($cardlistHDMIvc4)) {
                foreach ($cardlistHDMIvc4 as $card) {
                    $cardname = get_between_data($card, '=');
                    if ($cardname) {
                        $acardHDMIvc4 = array();
                        $acardHDMIvc4['device'] = $card;
                        $acardHDMIvc4['extlabel'] = $cardname;
                        $acardHDMIvc4['sysname'] = $cardname;
                        $acardHDMIvc4['type'] = 'alsa';
                        if ($redis->exists('hdmivc4hw') && ($redis->get('hdmivc4hw') == 2)) {
                            // hdmivc4hw = 2: use software card definition and set mixer_control to PCM
                            $acardHDMIvc4['mixer_control'] = 'PCM';
                            // set the mixer device to null
                            $acardHDMIvc4['mixer_device'] = '';
                        }
                        $acardHDMIvc4['description'] = 'Raspberry Pi: '.decode_vc4_hdmi_desc($cardname);
                        // add allowed formats to the card options
                        $hwplatformid = $redis->get('hwplatformid');
                        if ($hwplatformid == '08') {
                            $acardHDMIvc4['card_option'] = "allowed_formats \"192000:24:* 44100:24:* 48000:24:* 32000:24:* 88200:24:* 176400:24:* 96000:24:* 96000:16:* 44100:16:* 48000:16:* 32000:16:* 88200:16:* 176400:16:* 192000:16:*\"";
                        } else {
                            $acardHDMIvc4['card_option'] = "allowed_formats \"96000:24:* 44100:24:* 48000:24:* 32000:24:* 88200:24:* 176400:24:* 192000:24:* 96000:16:* 44100:16:* 48000:16:* 32000:16:* 88200:16:* 176400:16:* 192000:16:*\"";
                        }
                        $redis->hSet('acards', $acardHDMIvc4['sysname'], json_encode($acardHDMIvc4));
                    }
                }
            }
            //
            // $redis->save();
            $redis->bgSave();
            break;
    }
    return 'changed';
}

function wrk_i2smodule($redis, $args)
{
    $redis->set('i2smodule', $args);
    if($redis->get('hwplatformid') === '01' || $redis->get('hwplatformid') === '08') {
        // RuneAudio enable/disable/change i2s audio output overlays
        if ($args == 'none') {
            // dtoverlay=none disables all following dtoverlay commands in <p1mountpoint>/config.txt, so comment out the line
            $newLine = '#dtoverlay='.$args;
        } else {
            $newLine = 'dtoverlay='.$args;
        }
        $file = $redis->get('p1mountpoint').'/config.txt';
        $newArray = wrk_replaceTextLine($file, '', 'dtoverlay=', $newLine, 'RuneAudio I2S-Settings', 1);
        // Commit changes to config.txt
        $fp = fopen($file, 'w');
        $return = fwrite($fp, implode("", $newArray));
        fclose($fp);
    } else {
        if (wrk_mpdPlaybackStatus($redis) === 'play') {
            //$mpd = openMpdSocket('/run/mpd/socket', 0);
            $mpd = openMpdSocket($redis->hGet('mpdconf', 'bind_to_address'), 0);
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
    $hwplatformid = $redis->get('hwplatformid');
    if (($hwplatformid === '01') || ($hwplatformid === '08')) {
        if ($args == 1) {
            sysCmd("sed -i '/dtparam=audio=/c\dtparam=audio=on' '".$redis->get('p1mountpoint')."/config.txt'");
        } else {
            sysCmd("sed -i '/dtparam=audio=/c\dtparam=audio=off' '".$redis->get('p1mountpoint')."/config.txt'");
        }
        // delete the hdmi card cache as these will always be included in mpd.conf as this is an overhead,
        //  or there is a chance that these will have become unreliable
        $redis->del('hdmiacards');
        // refresh the audio cards
        sysCmdAsync($redis, 'nice --adjustment=10 /srv/http/command/refresh_ao');
    }
}

function wrk_kernelswitch($redis, $args)
{
    // $file = $redis->get('p1mountpoint').'/config.txt';
    // $newArray = wrk_replaceTextLine($file, '', 'kernel=', 'kernel='.$args.'.img');
    // // Commit changes to config.txt
    // $fp = fopen($file, 'w');
    // $return = fwrite($fp, implode("", $newArray));
    // fclose($fp);
    // $newArray = wrk_replaceTextLine($file, '', 'cmdline=', 'cmdline=cmdline_'.$args.'.txt');
    // // Commit changes to config.txt
    // $fp = fopen($file, 'w');
    // $return = fwrite($fp, implode("", $newArray));
    // fclose($fp);

    // if ($return) {
        // $redis->set('kernel', $args);
        // $redis->save();
    // }
    // return $return;
}

function wrk_mpdconf($redis, $action, $args = null, $jobID = null)
{
    switch ($action) {
        case 'reset':
            // default MPD config
            sysCmd('/srv/http/db/redis_datastore_setup mpdreset');
            if (isset($jobID) && $jobID) {
                $redis->sRem('w_lock', $jobID);
            }
            sysCmd('/srv/http/db/redis_acards_details');
            $redis->del('acards');
            wrk_audioOutput($redis, 'refresh');
            $mpdversion = sysCmd("grep -i 'Music Player Daemon' /srv/http/.config/mpdversion.txt | cut -f4 -d' ' | xargs")[0];
            $redis->hSet('mpdconf', 'version', $mpdversion);
            // if MPD has been built with SoXr support use it
            // it was introduced in v0.19 but is difficult to detect, search for soxr in the binary
            // for v0.20 and higher SoXr is reported in the --version list if it was included in the build
            if ($mpdversion >= '0.20.00') {
                // MPD version is higher than 0.20
                $count = sysCmd('grep -ic "soxr" /srv/http/.config/mpdversion.txt');
            } elseif ($mpdversion >= '0.19.00') {
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
            unset($count, $mpdversion);
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
            // get the mpd configuration data
            $mpdcfg = $redis->hGetAll('mpdconf');
            $output = null;
            // set mpd.conf file header
            $output =  "###################################\n";
            $output .= "#  Auto generated mpd.conf file   #\n";
            $output .= "# please DO NOT edit it manually! #\n";
            $output .= "#  Use RuneUI MPD config section  #\n";
            $output .= "###################################\n";
            $output .= "#\n";
            // --- log settings ---
            if ($mpdcfg['log_level'] === 'none') {
                $redis->hDel('mpdconf', 'log_file');
            } else {
                $output .= "log_level\t\"".$mpdcfg['log_level']."\"\n";
                $output .= "log_file\t\"".$mpdcfg['log_file']."\"\n";
            }
            unset($mpdcfg['log_level'], $mpdcfg['log_file']);
            // --- state file ---
            if (!isset($mpdcfg['state_file_enable']) || $mpdcfg['state_file_enable'] === 'no') {
                // do nothing
            } else {
                $output .= "state_file\t\"".$mpdcfg['state_file']."\"\n";
            }
            unset($mpdcfg['state_file'], $mpdcfg['state_file_enable']);
            // --- proxy_node & proxy_port ---
            if (isset($mpdcfg['proxy_node']) && $mpdcfg['proxy_node'] && isset($mpdcfg['proxy_port']) && $mpdcfg['proxy_port']) {
                // set up a database proxy
                $output .= "database {\n";
                $output .= "\tplugin\t\t\"proxy\"\n";
                $output .= "\thost\t\t\"".$mpdcfg['proxy_node']."\"\n";
                $output .= "\tport\t\t\"".$mpdcfg['proxy_port']."\"\n";
                $output .= "\tkeepalive\t\t\"yes\"\n";
                $output .= "}\n";
                // unset the database file name entry, this is invalid when the database proxy is used
                unset($mpdcfg['db_file']);
            }
            unset($mpdcfg['proxy_node'], $mpdcfg['proxy_port']);
            // --- bind_to_address & port ---
            if (!isset($mpdcfg['bind_to_address']) || $mpdcfg['bind_to_address'] === '') {
                // not set, add localhost
                $output .= "bind_to_address\t\"localhost\"\n";
            } else {
                $output .= "bind_to_address\t\"".$mpdcfg['bind_to_address']."\"\n";
                if (strpos(' '.$mpdcfg['bind_to_address'], '/')) {
                    // its a file name also add a bind_to_address line for localhost
                    $output .= "bind_to_address\t\"localhost\"\n";
                }
            }
            if (!isset($mpdcfg['port']) || ($mpdcfg['port'] == '')) {
                // TCP port number is not set, use the default
                $mpdcfg['port'] = '6600';
            }
            $output .= "port\t\t\"".$mpdcfg['port']."\"\n";
            unset($mpdcfg['bind_to_address'], $mpdcfg['port']);
            // sort the mpd configuration data into index key order
            ksort($mpdcfg);
            // --- general settings ---
            foreach ($mpdcfg as $param => $value) {
                switch ($param) {
                    case 'version':
                        // --- MPD version number ---
                        $output .="# MPD version number: ".$value."\n";
                        break;
                    case 'audio_output_interface':
                        // --- audio output interface ---
                        // do nothing
                        // no break
                    case 'dsd_usb':
                        // --- DSD USB ---
                        // do nothing
                        break;
                    case 'crossfade':
                        // --- Crossfade ---
                        // do nothing
                        break;
                    case 'consume':
                        // --- Consume ---
                        // do nothing
                        break;
                    case 'replaygainhandler':
                        // --- Replay Gain Handler ---
                        // do nothing
                        break;
                    case 'mixer_type':
                        // --- Mixer type ---
                        $hwmixer = 0;
                        if ($value === 'software' OR $value === 'hardware') {
                            $redis->set('volume', 1);
                            $volumeControl = 1;
                            if ($value === 'hardware') {
                                $hwmixer = 1;
                            }
                        } else {
                            $redis->set('volume', 0);
                            $volumeControl = 0;
                        }
                        break;
                    case 'user':
                        // --- user under which MPD must run ---
                        $output .= $param." \t\"".$value."\"\n";
                        // the MPD unit service file must also specify the same user
                        // check to see if a change is required
                        $retval = sysCmd('grep -c "^User='.$value.'" /etc/systemd/system/mpd.service');
                        if (!$retval[0]) {
                            // the user has changed
                            sysCmd("sed -i '/^User/s/^User.*/User=".$value."/' /etc/systemd/system/mpd.service");
                            // remove the MPD log file, this will have an incorrect file owner, otherwise MPD will fail to start
                            sysCmd("rm '".$redis->hGet('mpdconf', 'log_file')."'");
                            // change the privilages for /run/mpd so that the new user can create a socket & pid file
                            sysCmd("chmod 777 /run/mpd");
                            // a restart is required so set the mpdconfchange redis variable
                            $redis->set('mpdconfchange', 1);
                        }
                        unset($retval);
                        break;
                    case 'ffmpeg':
                        // --- ffmpeg decoder plugin ---
                        $output .="decoder {\n";
                        $output .="\tplugin \t\"ffmpeg\"\n";
                        $output .="\tenabled \"".$value."\"\n";
                        $output .="}\n";
                        break;
                    case 'soxr':
                        // --- soxr samplerate converter - resampler ---
                        if ($redis->get('soxrmpdonoff')) {
                            // soxr has not been switched off
                            $output .="resampler {\n";
                            $output .="\tplugin \t\"".$param."\"\n";
                            $output .="\tquality \"".$value."\"\n";
                            $output .="}\n";
                        }
                        break;
                    case 'curl':
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
                        break;
                    case 'webstreaming':
                        // --- websteaming output ---
                        if ($value) {
                            // save the indicator, add the output after the normal output interfaces
                            // a non zero value is the output samplerate
                            $websteaming = $value;
                            if (!isset($websteaming_encoder)) {
                                $websteaming_encoder = 'lame';
                            }
                        }
                        break;
                    case 'webstreaming_encoder':
                        // --- websteaming output encoder---
                        if ($value) {
                            // save the indicator, add the output after the normal output interfaces
                            // a non zero value is the output samplerate
                            $websteaming_encoder = $value;
                            if (!isset($websteaming)) {
                                $websteaming_encoder = '44100';
                            }
                        }
                        break;
                    case 'brutefir':
                        // --- brutefir pipe output ---
                        if ($value) {
                            // save the indicator, add the output after the normal output interfaces
                            $brutefirCommand = $value;
                        }
                        break;
                    case 'snapcast':
                        // --- snapcast fifo output ---
                        if ($value) {
                            // save the indicator, add the output after the normal output interfaces
                            $snapcastPath = $value;
                        }
                        break;
                    default:
                        $output .= $param." \t\"".$value."\"\n";
                        break;
                }
            }
            // ui_notify($redis, 'MPD', 'config file part one finished');
            // remove Bluetooth cards from acards
            wrk_btcfg($redis, 'remove_bt_acards');
            // get acards
            $acards = $redis->hGetAll('acards');
            //
            // --- audio output ---
            $lastAo = $redis->get('ao_default');
            // ao_default contains the last chosen hardware card (device=hw:...)
            //  ao contains the last chosen card, could be a software card like Bluetooth
            //  we try to minimise changes to mpd.conf, using ao_default helps
            // make sure we have at least one valid output
            if (!isset($acards) || !is_array($acards) || !reset($acards)) {
                // no audio cards, enable the http output (bitrate = 44100), so that mpd has one valid output
                if (!isset($websteaming)) {
                    // use these defaults when not set 44.1K, MP3 - this should always work
                    $websteaming = '44100';
                    $websteaming_encoder = 'lame';
                }
                $ao = '';
                runelog('detected ACARDS ', 'No audio cards', __FUNCTION__);
            } else if (count($acards) == 1) {
                // there is only one output card, use it
                $ao = array_key_first($acards);
            } else if (!isset($lastAo) || !$lastAo) {
                // no active audio card specified, use the first in the array
                $ao = array_key_first($acards);
            } else if (!isset($acards[$lastAo])) {
                // the specified audio output is no longer valid, use the first in the array
                $ao = array_key_first($acards);
            } else {
                $ao = $lastAo;
            }
            if ($lastAo != $ao) {
                // save the audio output
                $redis->Set('ao', $ao);
                $redis->Set('ao_default', $ao);
                // set this card to the default alsa card
                set_alsa_default_card($redis, $ao);
                wrk_hwinput($redis, 'refresh');
            }
            // debug
            runelog('detected ACARDS ', count($acards), __FUNCTION__);
            // get hdmi acards and merge with acards
            //  these are hdmi outputs which have been used in the past on this machine
            //  by always including them in mpd.conf it will prevent restarting mpd when hdmi output is is active/inactive on subsequent start-ups
            if ($redis->exists('hdmiacards')) {
                $acards = array_merge($redis->hgetall('hdmiacards'), $acards);
            }
            // save hdmi acards
            //  first delete the current hdmi acards
            $redis->del('hdmiacards');
            foreach ($acards as $key => $acard) {
                if (strpos(' '.strtolower($key), 'hdmi')) {
                    // the card name contains hdmi, save it
                    $redis->hSet('hdmiacards', $key, $acard);
                }
            }
            $sub_count = 0;
            // sort the cards so that when acards has a different sequence but the same contents
            //  the MPD config file will not be replaced and MPD not restarted
            //  sort order is case insensitive
            ksort($acards, SORT_NATURAL|SORT_FLAG_CASE);
            foreach ($acards as $main_acard_name => $main_acard_details) {
                // $card_decoded = new stdClass();
                unset($card_decoded);
                $card_decoded = array();
                $card_decoded = json_decode($main_acard_details, true);
                // debug
                runelog('decoded ACARD '.$card_decoded['sysname'], $main_acard_details, __FUNCTION__);
                // handle sub-interfaces
                if (isset($card_decoded['integrated_sub']) && ($card_decoded['integrated_sub'] === 1)) {
                    // record UI audio output name
                    $current_card = $card_decoded['sysname'];
                    // if ($sub_count >= 1) continue;
                    // $card_decoded = json_decode($card_decoded->real_interface);
                    runelog('current AO ---->  ', $ao, __FUNCTION__);
                    // var_dump($ao);
                    runelog('current card_name ---->  ', $card_decoded['sysname'], __FUNCTION__);
                    // var_dump($card_decoded->name);
                    // var_dump(strpos($ao, $card_decoded->name));
                    if (strpos($ao, $card_decoded['sysname']) === true OR strpos($ao, $card_decoded['sysname']) === 0) $sub_interface_selected = 1;
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
                    $output .="\tname \t\t\"".$card_decoded['sysname']."\"\n";
                } else {
                    $output .="\tname \t\t\"".$main_acard_name."\"\n";
                }
                $output .="\ttype \t\t\"".$card_decoded['type']."\"\n";
                $output .="\tdevice \t\t\"".$card_decoded['device']."\"\n";
                if ($hwmixer) {
                    if (isset($card_decoded['mixer_control'])) {
                        // mixer control is set
                        if ($card_decoded['mixer_control']) {
                            // mixer control has a value
                            $output .="\tmixer_control \t\"".$card_decoded['mixer_control']."\"\n";
                        }
                        // hardware mixer type is set when mixer control is set, even if mixer control has no value
                        $output .="\tmixer_type \t\"hardware\"\n";
                        if (isset($card_decoded['mixer_device']) && $card_decoded['mixer_device']) {
                            // mixer device is set and has a value
                            $output .="\tmixer_device \t\"".$card_decoded['mixer_device']."\"\n";
                        }
                        if (isset($mpdcfg['replaygain']) && ($mpdcfg['replaygain'] != 'off') && isset($mpdcfg['replaygainhandler'])) {
                            // when replay gain is enabled and there is a hardware mixer, then use the mixer as reply gain handler
                            $output .="\treplay_gain_handler \"".$mpdcfg['replaygainhandler']."\"\n";
                        }
                    } else {
                        if (!isset($sub_interface) && isset($card_decoded['mixer_control'])) {
                            $output .="\tmixer_control \t\"".$card_decoded['mixer_control']."\"\n";
                        } else {
                            $output .="\tmixer_type \t\"software\"\n";
                        }
                    }
                    // $output .="\tmixer_index \t\"0\"\n";"\t\t  \t\"0\"\n";
                } else if ($volumeControl) {
                    $output .="\tmixer_type \t\"software\"\n";
                } else {
                    $output .="\tmixer_type \t\"none\"\n";
                }
                // test if there is an option for mpd.conf is set
                // for example ODROID C1 needs "card_option":"buffer_time\t\"0\""
                if (isset($card_decoded['card_option'])) {
                    $output .= "\t".$card_decoded['card_option']."\n";
                }
                // test if there is an allowed_formats for mpd.conf is set
                // for example the ES9023 audio card expects 24 bit input
                if (isset($card_decoded['allowed_formats'])) {
                    $output .= "\tallowed_formats\t\"".$card_decoded['allowed_formats']."\"\n";
                }
                if ($mpdcfg['dsd_usb'] != 'no') {
                    if ($mpdcfg['dsd_usb'] === 'DSDDOP') {
                        $output .="\tdsd_usb \t\"yes\"\n";
                    } else if ($mpdcfg['dsd_usb'] === 'DSDNATIVE') {
                        $output .="\tdsd_native \t\"yes\"\n\tdsd_native_type \t\"2\"\n";
                    } else if ($mpdcfg['dsd_usb'] === 'yes') {
                        $output .="\tdsd_usb \t\"yes\"\n";
                    }
                }
                if ($card_decoded['type'] == 'alsa') {
                    $output .="\tbuffer_time \t\"200000\"\n";
                    $output .="\tperiod_time \t\"5084\"\n";
                }
                $output .="\tauto_resample \t\"no\"\n";
                $output .="\tauto_format \t\"no\"\n";
                if ($ao === $main_acard_name) {
                    $output .="\tenabled \t\"yes\"\n";
                } else {
                    $output .="\tenabled \t\"no\"\n";
                }
                $output .="}\n";
                unset($sub_interface);
                // debug
                // runelog('conf output (in loop)', $output, __FUNCTION__);
            }
            // add Bluetooth output devices for all known connections
            $btDevices = wrk_btcfg($redis, 'status');
            $btconfig = $redis->hgetall('bluetooth');
            ksort($btconfig, SORT_NATURAL|SORT_FLAG_CASE);
            foreach ($btDevices as $btDevice) {
                if ($btDevice['sink'] && $btDevice['device']) {
                    $output .= "audio_output {\n";
                    $output .= "\tname \t\t\"".$btDevice['name']."\"\n";
                    $output .= "\ttype \t\t\"alsa\"\n";
                    $output .= "\tdevice \t\t\"bluealsa:DEV=".$btDevice['device'].",PROFILE=a2dp\"\n";
                    // hardware mixer, wont work
                    // $output .= "\tmixer_control \t\"".$btDevice['name']." - A2DP \"\n";
                    // $output .= "\tmixer_type \t\"hardware\"\n";
                    // $output .= "\tmixer_device \t\"bluealsa:".$btDevice['device']."\"\n";
                    //
                    // software mixer
                    $output .= "\tmixer_type \t\"software\"\n";
                    //
                    if (isset($btconfig['samplerate'])) {
                        $output .= "\tallowed_formats \"".$btconfig['samplerate'].":16:2\"\n";
                    } else {
                        $output .= "\tallowed_formats\t\"".$redis->hGet('bluetooth', 'samplerate').":16:2\"";
                    }
                    $output .= "\tauto_resample \t\"no\"\n";
                    $output .= "\tauto_format \t\"no\"\n";
                    // never set Bluetooth to enabled
                    $output .= "\tenabled \t\"no\"\n";
                    $output .= "}\n";
                }
            }
            // add Bluetooth output devices to acards
            wrk_btcfg($redis, 'status');
            // add a null output device
            $output .="audio_output {\n";
            $output .="\tname \t\t\"null\"\n";
            $output .="\ttype \t\t\"null\"\n";
            $output .="\tenabled \t\"yes\"\n";
            $output .="}\n";
            // ui_notify($redis, 'MPD', 'config file part two finished');
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
                $output .="\tport \t\t\"8000\"\n";
                $output .="\tformat \t\t\"".$websteaming.":16:2\"\n";
                if ($websteaming_encoder === 'flac') {
                    $output .="\tencoder \t\t\"flac\"\n";
                    $output .="\tcompression \t\t\"0\"\n";
                } else {
                    $output .="\tencoder \t\"lame\"\n";
                    $output .="\tquality \t\"0\"\n";
                }
                $output .="\talways_on \t\"yes\"\n";
                $output .="\ttags \t\t\"yes\"\n";
                $output .="}\n";
            }
            // some users need to add an extra parameters to the MPD configuration file
            // this can be specified in the file /home/your-extra-mpd.conf
            // see the example file: /srv/http/app/config/defaults/your-extra-mpd.conf
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
            // ui_notify($redis, 'MPD', 'config file part three finished');
            // write mpd.conf file to /tmp location
            $fh = fopen('/tmp/mpd.conf', 'w');
            fwrite($fh, $output);
            fclose($fh);
            // check whether the /tmp/mpd.conf is not the same as /etc/mpd.conf and has not the same md5 as stored
            if (($redis->get('mpdconfhash') !== md5_file('/tmp/mpd.conf')) || ($redis->get('mpdconfhash') !== md5_file('/etc/mpd.conf'))) {
                // mpd configuration has changed, set mpdconfchange on, to indicate that MPD needs to be restarted and shairport conf needs updating
                $redis->set('mpdconfchange', 1);
                sysCmd('cp /tmp/mpd.conf /etc/mpd.conf');
                sysCmd('rm -f /tmp/mpd.conf');
                // update hash
                $redis->set('mpdconfhash', md5_file('/etc/mpd.conf'));
            } else {
                // nothing has changed, but don't unset mpdconfchange, a reboot may be needed for other reasons
                sysCmd('rm -f /tmp/mpd.conf');
            }
            break;
        case 'update':
            foreach ($args as $param => $value) {
                $redis->hSet('mpdconf', $param, $value);
            }
            if (isset($jobID) && $jobID) {
                $redis->sRem('w_lock', $jobID);
            }
            // ui_notify($redis, 'MPD', 'redis database updated');
            wrk_mpdconf($redis, 'writecfg');
            break;
        case 'switchao':
            // switch audio output to $args
            $args = trim($args);
            // record current interface selection, note: $args can be an empty string!
            $oldMpdout = $redis->get('ao');
            if (!isset($args) || !$args) {
                $ao_default = trim($redis->get('ao_default'));
                if ($ao_default) {
                    $args = $ao_default;
                }
            }
            // debug
            runelog('switchao (switch AO) from:', $oldMpdout);
            runelog('switchao (switch AO) to  :', $args);
            $startBluetooth = false;
            $disableNull = false;
            if ($args && ($oldMpdout != $args) && $redis->hExists('acards', $args)) {
                $redis->set('ao', $args);
                if (isset($jobID) && $jobID) {
                    $redis->sRem('w_lock', $jobID);
                }
                // set this card to the default alsa card
                set_alsa_default_card($redis, $args);
                wrk_hwinput($redis, 'refresh');
                // get interface details
                $acard = json_decode($redis->hGet('acards', $args), true);
                // save the card if it is a 'hw:' type
                if (isset($acard['device']) && (substr($acard['device'], 0, 3) == 'hw:')) {
                    $redis->set('ao_default', $args);
                }
                if (isset($acard['device']) && (substr($acard['device'], 0, 9) == 'bluealsa:')) {
                    // enable the null output, its needed when a bluetooth connection is lost
                    sysCmd('mpc enable null');
                    $startBluetooth = true;
                } else if (isset($acard['device']) && strpos(' '.$acard['device'], 'vc4') && strpos(' '.$acard['device'], 'hdmi')) {
                    // enable the null output, its needed for vc4 hdmi output, which always appears as a valid output even when it is not connected
                    sysCmd('mpc enable null');
                } else {
                    $disableNull = true;
                }
                // check for "special" sub_interfaces
                if (isset($acard['integrated_sub']) && $acard['integrated_sub']) {
                    // execute special internal route command
                    sysCmd($acard['route_cmd']);
                    // TODO: improve this function
                    sysCmd('amixer -c 0 set PCM unmute');
                    // $args = $acard->sysname;
                }
                // switch interface
                sysCmd('mpc enable "'.$args.'"');
                sysCmd('mpc disable "'.$oldMpdout.'"');
                if ($disableNull) {
                    sysCmd('mpc disable null');
                }
                wrk_shairport($redis, $args);
                wrk_spotifyd($redis, $args);
            } else if ($oldMpdout && $redis->hExists('acards', $oldMpdout)) {
                // save the previous card if it is a 'hw:' type
                $acard = json_decode($redis->hGet('acards', $oldMpdout), true);
                if (isset($acard['device']) && (substr($acard['device'], 0, 3) == 'hw:')) {
                    $redis->set('ao_default', $oldMpdout);
                }
                sysCmd('mpc enable "'.$oldMpdout.'"');
                if (isset($acard['device']) && (substr($acard['device'], 0, 9) == 'bluealsa:')) {
                    sysCmd('mpc enable null');
                } else if (isset($acard['device']) && strpos(' '.$acard['device'], 'vc4') && strpos(' '.$acard['device'], 'hdmi')) {
                    // enable the null output, its needed for vc4 hdmi output, which always appears as a valid output even when it is not connected
                    sysCmd('mpc enable null');
                } else {
                    sysCmd('mpc disable null');
                }
                // wrk_shairport($redis, $oldMpdout);
                // wrk_spotifyd($redis, $oldMpdout);
            } else {
                sysCmd('mpc enable null');
                // check that MPD only has one output enabled and if not correct it
                sysCmdAsync($redis, 'nice --adjustment=10 /srv/http/command/check_MPD_outputs_async.php');
            }
            if ($startBluetooth && sysCmd('mpc status | grep -ic "[playing]"')[0]) {
                // // set the initial volume for the bluetooth device
                // $btVolume = $redis->hGet('bluetooth', 'def_volume_out');
                // if ($btVolume != -1) {
                    // sysCmd('mpc volume '.$btVolume);
                // }
                // bluealsa needs a pause and play to successfully switch when already playing
                sysCmd('mpc pause');
                sysCmd('mpc play');
            }
            if ($redis->get('activePlayer') === 'Bluetooth') {
                wrk_btcfg($redis, 'auto_volume');
            }
            // set notify label
            if (isset($acard['description'])) {
                $interface_label = $acard['description'];
            } else if (isset($acard['extlabel'])) {
                $interface_label = $acard['extlabel'];
            } else {
                $interface_label = $args;
            }
            // notify UI
            if ($interface_label) {
                ui_notify($redis, 'Audio output switched', "Current active output:\n".$interface_label);
            }
            if ($redis->get('activePlayer') != 'MPD') {
                ui_notify($redis, 'Playback source switched to:', 'MPD');
                wrk_stopPlayer($redis);
            }
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
        case 'refreshsoundcards':
            $retval = wrk_audioOutput($redis, 'refresh');
            if ($retval === 'changed') {
                wrk_mpdconf($redis, 'writecfg');
                if ($redis->get('mpdconfchange')) {
                    // mpd.conf has changed so stop the mpd jobs
                    wrk_mpdconf($redis, 'stop');
                }
            }
            // always run start to make sure the mpd jobs are running
            // mpd will not be restarted if it was not stopped
            wrk_mpdconf($redis, 'start');
            break;
        case 'start':
            $activePlayer = $redis->get('activePlayer');
            if ($activePlayer === 'MPD') {
                $retval = sysCmd('systemctl is-active mpd');
                if ($retval[0] === 'active') {
                    // do nothing
                } else {
                    ui_notify($redis, 'MPD', 'starting MPD');
                    // reload systemd daemon to activate any changed unit files
                    sysCmd('systemctl daemon-reload');
                    // start mpd
                    start_mpd($redis);
                    // set mpdconfchange off
                    $redis->set('mpdconfchange', 0);
                }
                sleep(1);
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
            // set process priority
            sysCmdAsync($redis, 'nice --adjustment=10 /srv/http/command/rune_prio nice');
            unset($activePlayer, $retval);
            break;
        case 'forcestop':
            ui_notify($redis, 'MPD', 'stopping MPD');
            $redis->set('mpd_playback_status', wrk_mpdPlaybackStatus($redis));
            sysCmd('mpc stop');
            sysCmd('systemctl stop mpd');
            sleep(1);
            sysCmd('systemctl stop mpd ashuffle mpdscribble upmpdcli');
            break;
        case 'stop':
            // don't stop mpd if it is not running
            $retval = sysCmd('systemctl is-active mpd');
            if ($retval[0] === 'active') {
                // mpd is running
                // don't stop mpd if its configuration or unit file has not been changed
                if ($redis->get('mpdconfchange')) {
                    // the configuration file has changed
                    ui_notify($redis, 'MPD', 'stopping MPD');
                    $redis->set('mpd_playback_status', wrk_mpdPlaybackStatus($redis));
                    sysCmd('mpc stop');
                    sysCmd('systemctl stop mpd');
                    sleep(1);
                    sysCmd('systemctl stop mpd ashuffle mpdscribble upmpdcli');
                    // set mpdconfchange off
                    $redis->set('mpdconfchange', 0);
                }
            }
            break;
        case 'restart':
            wrk_mpdconf($redis, 'stop');
            wrk_mpdconf($redis, 'start');
            // code below is experimental
            // $activePlayer = $redis->get('activePlayer');
            // if ($activePlayer === 'MPD') {
                // $retval = sysCmd('systemctl is-active mpd');
                // if ($redis->get('mpdconfchange')) {
                    // ui_notify($redis, 'MPD', 'restarting MPD, it takes a while');
                    // // reload systemd daemon to activate any changed unit files
                    // sysCmd('systemctl daemon-reload');
                    // // restart mpd
                    // sysCmd('systemctl restart mpd');
                    // // set mpdconfchange off
                    // $redis->set('mpdconfchange', 0);
                // }
                // sleep(1);
                // // ashuffle gets started automatically
                // // restore the player status
                // sysCmd('mpc volume '.$redis->get('lastmpdvolume'));
                // wrk_mpdRestorePlayerStatus($redis);
                // // restart mpdscribble
                // if ($redis->hGet('lastfm', 'enable') === '1') {
                    // sysCmd('systemctl reload-or-restart mpdscribble || systemctl start mpdscribble');
                // }
                // // restart upmpdcli
                // if ($redis->hGet('dlna', 'enable') === '1') {
                    // sysCmd('systemctl reload-or-restart upmpdcli || systemctl start upmpdcli');
                // }
            // }
            // // set process priority
            // sysCmdAsync($redis, 'nice --adjustment=10 /srv/http/command/rune_prio nice');
            // unset($activePlayer, $retval);
            break;
        case 'forcerestart':
            wrk_mpdconf($redis, 'forcestop');
            wrk_mpdconf($redis, 'start');
            // code below is experimental
            // $activePlayer = $redis->get('activePlayer');
            // if ($activePlayer === 'MPD') {
                // $retval = sysCmd('systemctl is-active mpd');
                // ui_notify($redis, 'MPD', 'restarting MPD, it takes a while');
                // // reload systemd daemon to activate any changed unit files
                // sysCmd('systemctl daemon-reload');
                // // restart mpd
                // sysCmd('systemctl restart mpd');
                // // set mpdconfchange off
                // $redis->set('mpdconfchange', 0);
                // sleep(1);
                // // ashuffle gets started automatically
                // // restore the player status
                // sysCmd('mpc volume '.$redis->get('lastmpdvolume'));
                // wrk_mpdRestorePlayerStatus($redis);
                // // restart mpdscribble
                // if ($redis->hGet('lastfm', 'enable') === '1') {
                    // sysCmd('systemctl reload-or-restart mpdscribble || systemctl start mpdscribble');
                // }
                // // restart upmpdcli
                // if ($redis->hGet('dlna', 'enable') === '1') {
                    // sysCmd('systemctl reload-or-restart upmpdcli || systemctl start upmpdcli');
                // }
            // }
            // // set process priority
            // sysCmdAsync($redis, 'nice --adjustment=10 /srv/http/command/rune_prio nice');
            // unset($activePlayer, $retval);
            break;
    }
}

function wrk_mpdPlaybackStatus($redis, $action = null)
// returns the current MPD status, or when $action = laststate the previously recorded MPD state
//  MPD state values are 'stop', 'play', 'pause'
//  the last state and last playing song number (songid) are updated if there are valid values
{
    // sometimes MPD is still starting up
    // loop until the MPD socket can be opened, max 5 times with a 2 second sleep
    $cnt = 5;
    $status = '';
    $number = '';
    $sock = false;
    $bindToAddress = $redis->hGet('mpdconf', 'bind_to_address');
    while (!$sock && ($cnt-- >= 0)) {
        $sock = openMpdSocket($bindToAddress, 0);
        if (!$sock) {
            sleep(2);
        }
    }
    if ($sock) {
        // there is a valid MPD socket, get the MPD status
        $mpdstatus = _parseStatusResponse($redis, MpdStatus($sock));
        if (isset($mpdstatus['state']) && $mpdstatus['state']) {
            $status = $mpdstatus['state'];
            if (isset($mpdstatus['songid']) && $mpdstatus['songid']) {
                $number = $mpdstatus['songid'];
            }
        }
        closeMpdSocket($sock);
    }
    unset($sock, $cnt, $mpdstatus);
    //
    if (isset($action) && ($action == 'laststate')) {
        // return the last state
        $state = $redis->get('mpd_playback_laststate');
    } else {
        // return the current state (it may have a false value)
        $state = $status;
    }
    if ($status) {
        // when we have a valid current state save it
        $redis->set('mpd_playback_laststate', $status);
        if ($number) {
            // when we have a valid current number save it
            $redis->set('mpd_playback_lastnumber', $number);
        }
    }
    runelog('wrk_mpdPlaybackStatus (current state):', $status);
    runelog('wrk_mpdPlaybackStatus (current number):', $number);
    runelog('wrk_mpdPlaybackStatus (returned state):', $state);
    return $state;
}

function wrk_mpdRestorePlayerStatus($redis)
{
    // disable start global random
    $redis->hSet('globalrandom', 'wait_for_play', 1);
    $mpd_playback_lastnumber = $redis->get('mpd_playback_lastnumber');
    if (wrk_mpdPlaybackStatus($redis, 'laststate') === 'play') {
        // MPD can take a while to start up
        // attached USB storage devices could still be off-line, they will come on-line eventually
        //  if play is requested too quickly after startup it may not work
        //  solve by repeat play commands (no effect if already playing)
        //  this may seem inefficient and a problem for performance, but nothing will work correctly until MPD
        //      is up and running and USB storage is on-line
        $loops = 24;
        $sleepSeconds = 2;
        $bindToAddress = $redis->hGet('mpdconf', 'bind_to_address');
        while ($loops-- > 0) {
            $status = wrk_mpdPlaybackStatus($redis);
            // wrk_mpdPlaybackStatus() will set laststate to the actual current state, when we are in this loop set the laststate to play
            //  another job may call this routine while this loop is still running and then laststate could then be set to stop which could
            //      override the expected results
            $redis->set('mpd_playback_laststate', 'play');
            switch ($status) {
                // note: running wrk_mpdPlaybackStatus updates the last song number
                case 'pause':
                    // it was playing, now paused, start the current song
                    $sock = openMpdSocket($bindToAddress, 0);
                    if ($sock) {
                        if ($mpd_playback_lastnumber == $redis->get('mpd_playback_lastnumber')) {
                            // the paused song is the same as the last song, play from the paused playtime
                            sendMpdCommand($sock, 'play');
                        } else {
                            // the current paused song number is not the same as the last song number, play the last song from the beginning
                            sendMpdCommand($sock, 'playid '.$mpd_playback_lastnumber);
                        }
                        closeMpdSocket($sock);
                    }
                    break;
                case 'play':
                    // it was playing, now playing, so do nothing and exit the loop
                    $loops = 0;
                    break;
                case 'stop':
                    // it was playing, now stopped, so start the track which was last playing
                    $sock = openMpdSocket($bindToAddress, 0);
                    if ($sock) {
                        // could add code here to determine if the music file is accessible
                        sendMpdCommand($sock, 'playid '.$mpd_playback_lastnumber);
                        closeMpdSocket($sock);
                    }
                    break;
                default:
                    // MPD is not (yet) running, extra sleep
                    sleep($sleepSeconds);
                    break;
            }
            // sleep between loops
            sleep($sleepSeconds);
        }
        if (($loops <= 0) && (wrk_mpdPlaybackStatus($redis) != 'play')) {
            // gone through all the loops and it is still not playing, play the first entry in the queue
            //  if the queue is empty it has no effect
            //  if the USB storage deveic for track 1 is still off-line, MPD will sequentially try all the subsequent tracks in the queue until one plays
            // again laststate needs to be set to play, see above
            $redis->set('mpd_playback_laststate', 'play');
            $sock = openMpdSocket($bindToAddress, 0);
            if ($sock) {
                sendMpdCommand($sock, 'play 0');
                closeMpdSocket($sock);
            }
        }
    }
    // make sure the audio output is set to the selected card
    // get the selected audio output
    $audioOutput = $redis->get('ao');
    // enable the selected audio output (then selected and possibly null activated)
    if (isset($audioOutput) && $audioOutput) {
        // check that the card is still valid
        if (sysCmd("grep -ic '".$audioOutput."' /etc/mpd.conf | xargs")[0]) {
            // the card is defined in mpd.conf, enable it
            //
            // socket version is complex, the number of the audio output must be determined, mpc does it easily
            // $sock = openMpdSocket($bindToAddress, 0);
            // if ($sock) {
                // sendMpdCommand($sock, 'enableoutput '.$audioOutputNumber);
                // closeMpdSocket($sock);
            // }
            sysCmd('mpc output enable "'.$audioOutput.'"');
        } else {
            // the card is invalid, its not defined in mpd.conf
            // try the default card name, this should be a hardware card and almost always valid
            $audioOutput = $redis->get('ao_default');
            if (isset($audioOutput) && $audioOutput) {
                // check that the card is still valid
                if (sysCmd("grep -ic '".$audioOutput."' /etc/mpd.conf | xargs")[0]) {
                    // the card is defined in mpd.conf, enable it
                    //
                    // socket version is complex, the number of the audio output must be determined, mpc does it easily
                    // $sock = openMpdSocket($bindToAddress, 0);
                    // if ($sock) {
                        // sendMpdCommand($sock, 'enableoutput '.$audioOutputNumber);
                        // closeMpdSocket($sock);
                    // }
                    sysCmd('mpc output enable "'.$audioOutput.'"');
                } else {
                    // the default card is also invalid
                    $audioOutput = '';
                }
            }
        }
    }
    // disable the null audio output when the audio card is a valid hardware card, otherwise enable it
    if (isset($audioOutput) && $audioOutput) {
        // the audio output is valid
        if (sysCmd("aplay -l | grep -ic '".$audioOutput."' | xargs")[0]) {
            // its a hardware card, disable null
            //
            // socket version is complex, the number of the audio output must be determined, mpc does it easily
            // $sock = openMpdSocket($bindToAddress, 0);
            // if ($sock) {
                // sendMpdCommand($sock, 'disableoutput '.$audioOutputNumber);
                // closeMpdSocket($sock);
            // }
            sysCmd('mpc output disable "null"');
            // set the redis variables ao and ao_default to this value
            $redis->set('ao_default', $audioOutput);
            $redis->set('ao', $audioOutput);
        } else {
            // its not a hardware card, enable null
            //
            // socket version is complex, the number of the audio output must be determined, mpc does it easily
            // $sock = openMpdSocket($bindToAddress, 0);
            // if ($sock) {
                // sendMpdCommand($sock, 'enableoutput '.$audioOutputNumber);
                // closeMpdSocket($sock);
            // }
            sysCmd('mpc output enable "null"');
            // set the redis variable ao to this value
            $redis->set('ao', $audioOutput);
        }
    } else {
        // the audio output is invalid, enable null
        //
        // socket version is complex, the number of the audio output must be determined, mpc does it easily
        // $sock = openMpdSocket($bindToAddress, 0);
        // if ($sock) {
            // sendMpdCommand($sock, 'enableoutput '.$audioOutputNumber);
            // closeMpdSocket($sock);
        // }
        sysCmd('mpc output enable "null"');
    }
    // set this card to the default alsa card
    set_alsa_default_card($redis);
    // allow global random to start
    $redis->hSet('globalrandom', 'wait_for_play', 0);
}

function wrk_spotifyd($redis, $ao = null, $name = null)
{
    if (!isset($name) || empty($name) || !$name) {
        $name = trim($redis->hGet('spotifyconnect', 'device_name'));
        if ($name == '') {
            $name = trim($redis->get('hostname'));
            if ($name == '') {
                $name = 'RuneAudio';
            }
        }
    } else {
        $name = trim($name);
    }
    $redis->hSet('spotifyconnect', 'device_name', $name);
    //
    if (!isset($ao) || empty($ao) || !$ao) {
        $ao = trim($redis->get('ao'));
        if ($ao == '') {
            $ao = end($redis->hKeys('acards'));
            if ($ao != '') {
                $redis->set('ao', $ao);
                // set this card to the default alsa card
                set_alsa_default_card($redis, $ao);
                wrk_hwinput($redis, 'refresh');
            }
        }
    } else {
        $ao = trim($ao);
    }
    $redis->hSet('spotifyconnect', 'ao', $ao);
    //
    $acard = json_decode($redis->hGet('acards', $ao), true);
    runelog('[wrk_spotifyd] acard name         : ', $acard['name']);
    runelog('[wrk_spotifyd] acard type         : ', $acard['type']);
    runelog('[wrk_spotifyd] acard device       : ', $acard['device']);
    //
    !empty($acard['device']) && $redis->hSet('spotifyconnect', 'device', preg_split('/[\s,]+/', $acard['device'])[0]);
    //
    if (!empty($acard['mixer_control'])) {
        $mixer = trim($acard['mixer_control']);
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
    $spotifyd_conf .= "# RuneAudio auto generated spotifyd.conf file, do not edit\n";
    $spotifyd_conf .= "# Configuration File for Spotifyd\n";
    $spotifyd_conf .= "# A spotify playing daemon - Spotify Connect Receiver\n";
    $spotifyd_conf .= '# See: https://spotifyd.github.io/spotifyd/config/File.html'."\n";
    $spotifyd_conf .= '# Also see: /srv/http/app/config/defaults/spotifyd.conf'."\n";
    $spotifyd_conf .= "############################################################\n";
    $spotifyd_conf .= "#\n";
    $spotifyd_conf .= "[global]\n";
    $spotifyd_conf .= "#\n";
    $sccfg = $redis->hGetAll('spotifyconnect');
    // redis randomises the order of elements in a hash, sort to improve give a consistent order in the conf file
    ksort($sccfg);
    foreach ($sccfg as $param => $value) {
        $value = trim($value);
        switch ($param) {
        case "backend":
        case "device":
        case "device_name":
        case "device_type":
        case "mixer":
        case "onevent":
        case "password":
        case "username":
            if ($value) {
                $spotifyd_conf .= $param." = ".'"'.$value.'"'."\n";
            }
            break;
        case "autoplay":
        case "bitrate":
        case "use_mpris":
            if ($value) {
                $spotifyd_conf .= $param." = ".$value."\n";
            }
            break;
        case "initial_volume":
            // if (isset($sccfg['save_last_volume']) && $sccfg['save_last_volume']) {
                // $spotifyd_conf .= "initial_volume = ".'"'.$sccfg['lastvolume'].'"'."\n";
            // } else {
                // $spotifyd_conf .= "initial_volume = ".'"'.$value.'"'."\n";
            // }
            // use the last MPD volume, it gets set when spotifyd starts, not when the stream starts
            $spotifyd_conf .= "# initial_volume gets used when spotifyd starts, not when the stream starts\n";
            $spotifyd_conf .= "initial_volume = ".'"'.$redis->get('lastmpdvolume').'"'."\n";
            break;
        case "volume_control":
            $spotifyd_conf .= "volume-control = ".'"'.$value.'"'."\n";
            break;
        case "volume_normalisation":
            if ($value == 'true') {
                $spotifyd_conf .= "volume-normalisation = ".$value."\n";
            } else {
                $spotifyd_conf .= "volume-normalisation = false\n";
            }
            break;
        case "normalisation_pregain":
            if (isset($sccfg['volume_normalisation']) && $sccfg['volume_normalisation'] == 'true') {
                $spotifyd_conf .= "normalisation-pregain = ".$value."\n";
            } else {
                $spotifyd_conf .= "normalisation-pregain = 0\n";
            }
            break;
        case "cache_path":
            if ($value != '') {
                $spotifyd_conf .= "# Disable the cache, it uses too much memory\n";
                $spotifyd_conf .= "# ".$param." = ".'"'.$value.'"'."\n";
                if (isset($sccfg['max_cache_size']) && $sccfg['max_cache_size']) {
                    $spotifyd_conf .= "# Maximum cache size, defined in bytes\n";
                    $spotifyd_conf .= "# max_cache_size = ".'"'.$sccfg['max_cache_size'].'"'."\n";
                } else {
                    $spotifyd_conf .= "# Maximum cache size 50Kb, defined in bytes\n";
                    $spotifyd_conf .= "# max_cache_size = 50000\n";
                }
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
        sysCmd('rm -f /tmp/spotifyd.conf');
        if ($redis->hGet('spotifyconnect', 'enable')) {
            // when nothing has changed check that spotifyd is running
            runelog('start spotifyd');
            sysCmd('pgrep -x spotifyd || systemctl start spotifyd');
        } else {
            // stop spotifyd & rune_SDM_wrk, they should already have stopped
            runelog('stop spotifyd');
            sysCmd('pgrep -x spotifyd && systemctl stop spotifyd');
            sysCmd('pgrep -x rune_SDM_wrk && systemctl stop rune_SDM_wrk');
            $redis->hSet('spotifyconnect', 'last_track_id', '');
        }
    } else {
        // spotifyd configuration has changed
        if ($redis->get('activePlayer') === 'SpotifyConnect') {
            runelog('Stop SpotifyConnect player');
            wrk_stopPlayer($redis);
        }
        sysCmd('cp /tmp/spotifyd.conf /etc/spotifyd.conf');
        sysCmd('rm -f /tmp/spotifyd.conf');
        // stop spotifyd & rune_SDM_wrk, they should already have stopped using the function wrk_stopPlayer()
        sysCmd('pgrep -x spotifyd && systemctl stop spotifyd');
        sysCmd('pgrep -x rune_SDM_wrk && systemctl stop rune_SDM_wrk');
        $redis->hSet('spotifyconnect', 'last_track_id', '');
        // update systemd
        sysCmd('systemctl daemon-reload');
        if ($redis->hGet('spotifyconnect', 'enable')) {
            runelog('restart spotifyd');
            wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'spotifyconnect', 'action' => 'start'));
            $redis->hSet('spotifyconnect', 'last_track_id', '');
            sysCmd('mpc volume '.$redis->get('lastmpdvolume'));
            // no need to start this, spotifyconnect is disconnected //wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'spotifyconnectmetadata', 'action' => 'start'));
        }
    }
}

function wrk_shairport($redis, $ao = null, $name = null)
{
    if (!isset($name) || empty($name) || !$name) {
        $name = trim($redis->hGet('airplay', 'device_name'));
        if ($name == '') {
            $name = trim($redis->get('hostname'));
            if ($name == '') {
                $name = 'RuneAudio';
            }
        }
    } else {
        $name = trim($name);
    }
    $redis->hSet('airplay', 'device_name', $name);
    //
    if (!isset($ao) || empty($ao) || !$ao) {
        $ao = trim($redis->hGet('airplay', 'ao'));
        if ($ao == '') {
            $ao = trim($redis->get('ao'));
            if ($ao == '') {
                $ao = end($redis->hKeys('acards'));
                if ($ao != '') {
                    $redis->set('ao', $ao);
                    // set this card to the default alsa card
                    set_alsa_default_card($redis, $ao);
                    wrk_hwinput($redis, 'refresh');
                }
            }
        }
    } else {
        $ao = trim($ao);
    }
    $redis->hSet('airplay', 'ao', $ao);
    //
    $acard = json_decode($redis->hGet('acards', $ao), true);
    if (!isset($acard) || !is_array($acard) || !isset($acard['name'])) {
        // no output devices
        $redis->hSet('airplay', 'ao', '');
        $redis->hSet('airplay', 'alsa_mixer_control', '');
        $redis->hSet('airplay', 'alsa_mixer_device', '');
        $redis->hSet('airplay', 'alsa_output_device', '');
        // stop shairport-sync
        sysCmd('pgrep shairport-sync && systemctl stop shairport-sync');
        return 0;
    }
    runelog('wrk_shairport acard name         : ', $acard['name']);
    runelog('wrk_shairport acard type         : ', $acard['type']);
    runelog('wrk_shairport acard device       : ', $acard['device']);
    // shairport-sync output device is specified without a subdevice if only one subdevice exists
    // determining the number of sub devices is done by counting the number of alsa info file for the device
    // shairport-sync output device is always specified without a subdevice! Possible that this will need extra work for USB DAC's
    $redis->hSet('airplay', 'alsa_output_device', preg_split('/[\s,]+/', $acard['device'])[0]);
    //
    if (!empty($acard['mixer_device'])) {
        $alsa_mixer_device = trim($acard['mixer_device']);
    } else {
        $alsa_mixer_device = '';
    }
    if ($redis->hGet('mpdconf', 'mixer_type') != 'hardware') {
        $alsa_mixer_device = '';
    }
    runelog('wrk_shairport alsa_mixer_device : ', $alsa_mixer_device);
    $redis->hSet('airplay', 'alsa_mixer_device', $alsa_mixer_device);
    unset($alsa_mixer_device);
    //
    if (!empty($acard['mixer_control'])) {
        $alsa_mixer_control = trim($acard['mixer_control']);
    } else {
        $alsa_mixer_control = 'PCM';
    }
    if ($alsa_mixer_control === '') {
        $alsa_mixer_control = 'PCM';
    }
    if ($redis->hGet('mpdconf', 'mixer_type') != 'hardware') {
        $alsa_mixer_control = 'PCM';
    }
    runelog('wrk_shairport acard alsa_mixer_control: ', $alsa_mixer_control);
    $redis->hSet('airplay', 'alsa_mixer_control', $alsa_mixer_control);
    unset($alsa_mixer_control);
    //
    if (!empty($acard['description'])) {
        $description = trim($acard['description']);
    } else {
        $description = '';
    }
    runelog('wrk_shairport acard description     : ', $description);
    $redis->hSet('airplay', 'description', $description);
    unset($description);
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
    unset($interpolation);
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
    unset($metadata_enabled);
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
    unset($metadata_include_cover_art);
    //get all the airplay parameters
    $airplay = $redis->hGetall('airplay');
    // update shairport-sync.conf
    $file = '/etc/shairport-sync.conf';
    $newArray = wrk_replaceTextLine($file, '', ' general_name', 'name="'.$airplay['name'].'"; // general_name');
    $newArray = wrk_replaceTextLine('', $newArray, ' general_output_backend', 'output_backend="'.$airplay['output_backend'].'"; // general_output_backend');
    if ($airplay['interpolation'] === '') {
        $newArray = wrk_replaceTextLine('', $newArray, ' general_interpolation', '// interpolation="'.$airplay['interpolation'].'"; // general_interpolation');
    } else {
        $newArray = wrk_replaceTextLine('', $newArray, ' general_interpolation', 'interpolation="'.$airplay['interpolation'].'"; // general_interpolation');
    }
    $newArray = wrk_replaceTextLine('', $newArray, ' general_alac_decoder', 'alac_decoder="'.$airplay['alac_decoder'].'"; // general_alac_decoder');
    $newArray = wrk_replaceTextLine('', $newArray, ' run_this_before_play_begins', 'run_this_before_play_begins="'.$airplay['run_this_before_play_begins'].'"; // run_this_before_play_begins');
    $newArray = wrk_replaceTextLine('', $newArray, ' run_this_after_play_ends', 'run_this_after_play_ends="'.$airplay['run_this_after_play_ends'].'"; // run_this_after_play_ends');
    $newArray = wrk_replaceTextLine('', $newArray, ' run_this_wait_for_completion', 'wait_for_completion="'.$airplay['run_this_wait_for_completion'].'"; // run_this_wait_for_completion');
    $newArray = wrk_replaceTextLine('', $newArray, ' alsa_output_device', 'output_device="'.$airplay['alsa_output_device'].'"; // alsa_output_device');
    if ($airplay['alsa_mixer_control'] === 'PCM') {
        $newArray = wrk_replaceTextLine('', $newArray, ' alsa_mixer_control_name', '// mixer_control_name="'.$airplay['alsa_mixer_control'].'"; // alsa_mixer_control_name');
    } else {
        $newArray = wrk_replaceTextLine('', $newArray, ' alsa_mixer_control_name', 'mixer_control_name="'.$airplay['alsa_mixer_control'].'"; // alsa_mixer_control_name');
    }
    if ($airplay['alsa_mixer_device'] === '') {
        $newArray = wrk_replaceTextLine('', $newArray, ' alsa_mixer_device', '// mixer_device="'.$airplay['alsa_mixer_device'].'"; // alsa_mixer_device');
    } else {
        $newArray = wrk_replaceTextLine('', $newArray, ' alsa_mixer_device', 'mixer_device="'.$airplay['alsa_mixer_device'].'"; // alsa_mixer_device');
    }
    $newArray = wrk_replaceTextLine('', $newArray, ' alsa_output_format', 'output_format="'.$airplay['alsa_output_format'].'"; // alsa_output_format');
    $newArray = wrk_replaceTextLine('', $newArray, ' alsa_output_rate', 'output_rate='.$airplay['alsa_output_rate'].'; // alsa_output_rate');
    $newArray = wrk_replaceTextLine('', $newArray, ' pipe_pipe_name', 'name="'.$airplay['pipe_pipe_name'].'"; // pipe_pipe_name');
    if ($airplay['metadata_enabled'] === '') {
        $newArray = wrk_replaceTextLine('', $newArray, ' metadata_enabled', '// enabled="'.$airplay['metadata_enabled'].'"; // metadata_enabled');
    } else {
        $newArray = wrk_replaceTextLine('', $newArray, ' metadata_enabled', 'enabled="'.$airplay['metadata_enabled'].'"; // metadata_enabled');
    }
    if (($airplay['metadata_include_cover_art'] === '') || ($airplay['metadata_enabled'] === '')) {
        $newArray = wrk_replaceTextLine('', $newArray, ' metadata_include_cover_art', '// include_cover_art="'.$airplay['metadata_include_cover_art'].'"; // metadata_include_cover_art');
    } else {
        $newArray = wrk_replaceTextLine('', $newArray, ' metadata_include_cover_art', 'include_cover_art="'.$airplay['metadata_include_cover_art'].'"; // metadata_include_cover_art');
    }
    $newArray = wrk_replaceTextLine('', $newArray, ' metadata_pipe_name', 'pipe_name="'.$airplay['metadata_pipe_name'].'"; // metadata_pipe_name');
    // Commit changes to /tmp/shairport-sync.conf
    $newfile = '/tmp/shairport-sync.conf';
    $fp = fopen($newfile, 'w');
    fwrite($fp, implode("", $newArray));
    fclose($fp);
    // check that the conf file has changed
    if (md5_file($file) === md5_file($newfile)) {
        // nothing has changed, set sssconfchange off
        $redis->set('sssconfchange', 0);
        sysCmd('rm -f '.$newfile);
    } else {
        // mpd configuration has changed, set sssconfchange on
        $redis->set('sssconfchange', 1);
        sysCmd('cp '.$newfile.' '.$file);
        sysCmd('rm -f '.$newfile);
    }
    // libio
    $file = '/etc/libao.conf';
    if ($airplay['output_backend'] === 'alsa') {
        $newArray = wrk_replaceTextLine($file, '', 'dev=', 'dev='.$airplay['alsa_output_device']);
    } else {
        $newArray = wrk_replaceTextLine($file, '', '#dev=', 'dev='.$airplay['alsa_output_device']);
    }
    // Commit changes to /tmp/libao.conf
    $newfile = '/tmp/libao.conf';
    $fp = fopen($newfile, 'w');
    fwrite($fp, implode("", $newArray));
    fclose($fp);
    // check that the conf file has changed
    if (md5_file($file) === md5_file($newfile)) {
        // nothing has changed, set libaoconfchange off
        $redis->set('libaoconfchange', 0);
        sysCmd('rm -f '.$newfile);
    } else {
        // mpd configuration has changed, set libaoconfchange on
        $redis->set('libaoconfchange', 1);
        sysCmd('cp '.$newfile.' '.$file);
        sysCmd('rm -f '.$newfile);
    }
    // restart only if the conf files have changed
    if (($redis->get('sssconfchange')) OR ($redis->get('libaoconfchange'))) {
        // stop rune_SSM_wrk
        if ($redis->get('activePlayer') === 'Airplay') {
            runelog('Stop Airplay player');
            wrk_stopPlayer($redis);
        }
        sysCmd('pgrep -x shairport-sync && systemctl stop shairport-sync');
        sysCmd('pgrep -x rune_SSM_wrk && systemctl stop rune_SSM_wrk');
        // update systemd
        sysCmd('systemctl daemon-reload');
        if ($airplay['enable']) {
            runelog('restart shairport-sync');
            sysCmd('systemctl reload-or-restart shairport-sync || systemctl start shairport-sync');
        }
    } else {
        // nothing has changed, check that shairport-sync is running or stopped as required
        if ($airplay['enable']) {
            runelog('start shairport-sync');
            sysCmd('pgrep -x shairport-sync || systemctl start shairport-sync');
        } else {
            runelog('stop shairport-sync');
            sysCmd('pgrep -x shairport-sync && systemctl stop shairport-sync');
            sysCmd('pgrep -x rune_SSM_wrk && systemctl stop rune_SSM_wrk');
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
                    ui_notifyError($redis, $type.' mount', $mp['error']);
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
                    ui_notifyError($redis, $type.' mount', $mp['error']);
                    sleep(3);
                }
            }
            if ($mp['remotedir'] != preg_replace('|[^A-Za-z0-9-._/ ]|', '', $mp['remotedir'])) {
                // special characters are not normally valid as a remote directory name
                $mp['error'] = 'Warning "'.$mp['remotedir'].'" Remote Directory seems incorrect - contains special character(s) - continuing';
                if (!$quiet) {
                    ui_notifyError($redis, $type.' mount', $mp['error']);
                    sleep(3);
                }
            }
            if (!strlen($mp['remotedir'])) {
                // normally valid as a remote directory name should be specified
                $mp['error'] = 'Warning "'.$mp['remotedir'].'" Remote Directory seems incorrect - empty - continuing';
                if (!$quiet) {
                    ui_notifyError($redis, $type.' mount', $mp['error']);
                    sleep(3);
                }
            }
            // strip special characters, spaces, tabs, etc. (hex 00 to 20 and 7F), from the options string
            $mp['options'] = preg_replace("|[\\x00-\\x20\\x7F]|", "", $mp['options']);
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
                    if (!$quiet) ui_notify($redis, $type.' mount', 'Attempting to use saved/predefined mount options');
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
                    if (!$quiet) ui_notify($redis, $type.' mount', 'Attempting to use saved/predefined mount options');
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
                    ui_notify($redis, $type.' mount', '//'.$mp['address'].'/'.$mp['remotedir'].' Mounted');
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
                        ui_notify($redis, $type.' mount', '//'.$mp['address'].'/'.$mp['remotedir'].' Mounted');
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
                    ui_notifyError($redis, $type.' mount', $mp['error']);
                    sleep(3);
                    ui_notifyError($redis, $type.' mount', '//'.$mp['address'].'/'.$mp['remotedir'].' Failed');
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
                            if (!$quiet) ui_notify($redis, $type.' mount', 'Attempting automatic negotiation');
                            $options1 = 'cache=loose,noserverino,ro,noexec';
                            break;
                        case 2:
                            if (!$quiet) ui_notify($redis, $type.' mount', 'Attempting vers=3.1.1');
                            $options1 = 'cache=loose,noserverino,ro,vers=3.1.1,noexec';
                            break;
                        case 3:
                            if (!$quiet) ui_notify($redis, $type.' mount', 'Attempting vers=3.02');
                            $options1 = 'cache=loose,noserverino,ro,vers=3.02,noexec';
                            break;
                        case 4:
                            if (!$quiet) ui_notify($redis, $type.' mount', 'Attempting vers=3.0');
                            $options1 = 'cache=loose,noserverino,ro,vers=3.0,noexec';
                            break;
                        case 5:
                            if (!$quiet) ui_notify($redis, $type.' mount', 'Attempting vers=2.1');
                            $options1 = 'cache=loose,noserverino,ro,vers=2.1,noexec';
                            break;
                        case 6:
                            if (!$quiet) ui_notify($redis, $type.' mount', 'Attempting vers=2.0');
                            $options1 = 'cache=loose,noserverino,ro,vers=2.0,noexec';
                            break;
                        case 7:
                            if (!$quiet) ui_notify($redis, $type.' mount', 'Attempting vers=1.0');
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
                                ui_notify($redis, $type.' mount', '//'.$mp['address'].'/'.$mp['remotedir'].' Mounted');
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
                                    ui_notify($redis, $type.' mount', '//'.$mp['address'].'/'.$mp['remotedir'].' Mounted');
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
                ui_notifyError($redis, $type.' mount', $mp['error']);
                sleep(3);
                ui_notifyError($redis, $type.' mount', '//'.$mp['address'].'/'.$mp['remotedir'].' Failed');
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
    $return = true;
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
            wrk_mpdconf($redis,'forcestop');
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
            wrk_mpdconf($redis, 'start');
            // ashuffle gets started automatically
            // set process priority
            sysCmdAsync($redis, 'nice --adjustment=10 /srv/http/command/rune_prio nice');
            break;
        case 'umountall':
            wrk_mpdconf($redis,'forcestop');
            usleep(500000);
            $source = $redis->keys('mount_*');
            foreach ($source as $key) {
                $mp = $redis->hGetAll($key);
                runelog('wrk_sourcecfg() umount loop $mp[name]',$mp['name']);
                sysCmd("umount -f '/mnt/MPD/NAS/".$mp['name']."'");
                sysCmd("rmdir '/mnt/MPD/NAS/".$mp['name']."'");
            }
            wrk_mpdconf($redis,'start');
            // ashuffle gets started automatically
            // set process priority
            sysCmdAsync($redis, 'nice --adjustment=10 /srv/http/command/rune_prio nice');
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
            // for some unknown reason usb devices sometimes get mounted twice check that it is dismounted, if not run again
            // clean up any invalid mount points
            sysCmd('udevil clean');
            if (wrk_checkMount($args)) {
                sysCmd('udevil umount '.$args);
                // clean up any invalid mount points
                sysCmd('udevil clean');
                // udevil is becoming less reliable, if the disk is still mounted dismount with umount
                //  the code below needs to run as root, umount has the +s flag
                if (wrk_checkMount($args)) {
                    sysCmd('umount '.$args);
                    // clean up any invalid mount points
                    sysCmd('udevil clean');
                }
            }
            break;
    }
    return $return;
}

function wrk_getHwPlatform($redis, $reset=false)
{
    if ($reset) {
        // remove the redis variables set in this routine
        $redis->del('soxrmpdonoff');
        $redis->del('bluetooth_on');
        $redis->del('wifi_on');
        $redis->hDel('airplay', 'soxronoff');
        $redis->hDel('airplay', 'metadataonoff');
        $redis->hDel('airplay', 'artworkonoff');
        $redis->hDel('airplay', 'enable');
        $redis->hDel('airplay', 'metadata_enabled');
        $redis->hDel('spotifyconnect', 'metadata_enabled');
        $redis->hDel('AccessPoint', 'enable');
        // set the default local browser windows and browser type
        $redis->hSet('local_browser', 'windows', 'xorg');
        $redis->hSet('local_browser', 'browser', 'chromium');
        $redis->del('acards');
        $redis->del('hdmiacards');
    }
    $file = '/proc/cpuinfo';
    $fileData = file($file);
    foreach($fileData as $line) {
        if ((substr(strtolower($line), 0, 8) == 'revision') && strpos(' '.$line, ':')) {
            $revision = trim(explode(':', $line, 2)[1]);
            // debug
            runelog('[wrk_getHwPlatform] /proc/cpuinfo revision', $revision);
        }
        if ((substr(strtolower($line), 0, 8) == 'hardware') && strpos(' '.$line, ':')) {
            $hardware = trim(explode(':', $line, 2)[1]);
            // debug
            runelog('[wrk_getHwPlatform] /proc/cpuinfo hardware', $hardware);
        }
        if (substr(strtolower($line), 0, 5) == 'model') {
            if (strpos(' '.strtolower($line), 'raspberry pi')) {
                $model = 'raspberry pi';
            } else {
                $model = trim($line);
            }
            // debug
            runelog('[wrk_getHwPlatform] /proc/cpuinfo model', $model);
        }
    }
    if (!isset($hardware) || !$hardware) {
        $hardware = $model;
    }
    unset($model);

    switch($hardware) {
        // RaspberryPi
        case 'BCM2708':
        case 'BCM2709':
        case 'BCM2710':
        case 'BCM2711':
        case 'BCM2835':
        case 'BCM2836':
        case 'BCM2837':
        case 'raspberry pi':
            if (intval("0x".$revision, 16) < 16) {
                // RaspberryPi1
                $arch = '01';
                $model = "00";
                // old single processor models no on-board Wi-Fi or Bluetooth
                $redis->exists('soxrmpdonoff') || $redis->set('soxrmpdonoff', 0);
                $redis->exists('bluetooth_on') || $redis->set('bluetooth_on', 0);
                $redis->exists('wifi_on') || $redis->set('wifi_on', 0);
                $redis->hExists('airplay', 'soxronoff') || $redis->hSet('airplay', 'soxronoff', 0);
                $redis->hExists('airplay', 'metadataonoff') || $redis->hSet('airplay', 'metadataonoff', 0);
                $redis->hExists('airplay', 'artworkonoff') || $redis->hSet('airplay', 'artworkonoff', 0);
                $redis->hExists('airplay', 'enable') || $redis->hSet('airplay', 'enable', 0);
                $redis->hExists('airplay', 'metadata_enabled') || $redis->hSet('airplay', 'metadata_enabled', 'no');
                $redis->hExists('spotifyconnect', 'metadata_enabled') || $redis->hSet('spotifyconnect', 'metadata_enabled', 0);
                $redis->hExists('AccessPoint', 'enable') || $redis->hSet('AccessPoint', 'enable', 1);
            }
            else {
                $model = strtolower(trim(substr($revision, -3, 2)));
                switch($model) {
                    case "00":
                        // 00 = PiA or PiB
                        $arch = '01';
                        // single processor models no on-board Wi-Fi or Bluetooth
                        $redis->exists('soxrmpdonoff') || $redis->set('soxrmpdonoff', 0);
                        $redis->exists('bluetooth_on') || $redis->set('bluetooth_on', 0);
                        $redis->exists('wifi_on') || $redis->set('wifi_on', 0);
                        $redis->hExists('airplay', 'soxronoff') || $redis->hSet('airplay', 'soxronoff', 0);
                        $redis->hExists('airplay', 'metadataonoff') || $redis->hSet('airplay', 'metadataonoff', 0);
                        $redis->hExists('airplay', 'artworkonoff') || $redis->hSet('airplay', 'artworkonoff', 0);
                        $redis->hExists('airplay', 'enable') || $redis->hSet('airplay', 'enable', 0);
                        $redis->hExists('airplay', 'metadata_enabled') || $redis->hSet('airplay', 'metadata_enabled', 'no');
                        $redis->hExists('spotifyconnect', 'metadata_enabled') || $redis->hSet('spotifyconnect', 'metadata_enabled', 0);
                        $redis->hExists('AccessPoint', 'enable') || $redis->hSet('AccessPoint', 'enable', 1);
                        break;
                    case "01":
                        // 01 = PiB+, PiA+ or PiCompute module 1
                    case "02":
                        // 02 = PiA+,
                    case "03":
                        // 03 = PiB+,
                    case "05":
                        // 05 = PiAlpha prototype, treat as a PiB+
                    case "06":
                        // 06 = PiCompute Module 1
                    case "07":
                        // 07 = unknown, treat as a PiB+
                    case "09":
                        // 09 = PiZero
                    case "0b":
                        // 0b = unknown, treat as a PiB+
                    case "0f":
                        // 0f = internal use only, treat as a PiB+
                        // single processor models no on-board Wi-Fi or Bluetooth
                        $arch = '01';
                        $redis->exists('soxrmpdonoff') || $redis->set('soxrmpdonoff', 1);
                        $redis->exists('bluetooth_on') || $redis->set('bluetooth_on', 0);
                        $redis->exists('wifi_on') || $redis->set('wifi_on', 0);
                        $redis->hExists('airplay', 'soxronoff') || $redis->hSet('airplay', 'soxronoff', 1);
                        $redis->hExists('airplay', 'metadataonoff') || $redis->hSet('airplay', 'metadataonoff', 1);
                        $redis->hExists('airplay', 'artworkonoff') || $redis->hSet('airplay', 'artworkonoff', 1);
                        $redis->hExists('airplay', 'enable') || $redis->hSet('airplay', 'enable', 0);
                        $redis->hExists('airplay', 'metadata_enabled') || $redis->hSet('airplay', 'metadata_enabled', 'yes');
                        $redis->hExists('spotifyconnect', 'metadata_enabled') || $redis->hSet('spotifyconnect', 'metadata_enabled', 1);
                        $redis->hExists('AccessPoint', 'enable') || $redis->hSet('AccessPoint', 'enable', 1);
                        break;
                    case "04":
                        // 04 = Pi2B,
                    case "06":
                        // 06 = PiCompute Module
                    case "0a":
                        // 0a = PiCompute Module 3
                    case "10":
                        // 10 = PiCompute Module 3+
                    case "14":
                        // 14 = PiCompute Module 4
                        // multi processor models no on-board Wi-Fi or Bluetooth
                        $arch = '08';
                        $redis->exists('soxrmpdonoff') || $redis->set('soxrmpdonoff', 1);
                        $redis->exists('bluetooth_on') || $redis->set('bluetooth_on', 0);
                        $redis->exists('wifi_on') || $redis->set('wifi_on', 0);
                        $redis->hExists('airplay', 'soxronoff') || $redis->hSet('airplay', 'soxronoff', 1);
                        $redis->hExists('airplay', 'metadataonoff') || $redis->hSet('airplay', 'metadataonoff', 1);
                        $redis->hExists('airplay', 'artworkonoff') || $redis->hSet('airplay', 'artworkonoff', 1);
                        $redis->hExists('airplay', 'enable') || $redis->hSet('airplay', 'enable', 1);
                        $redis->hExists('airplay', 'metadata_enabled') || $redis->hSet('airplay', 'metadata_enabled', 'yes');
                        $redis->hExists('spotifyconnect', 'metadata_enabled') || $redis->hSet('spotifyconnect', 'metadata_enabled', 1);
                        $redis->hExists('AccessPoint', 'enable') || $redis->hSet('AccessPoint', 'enable', 1);
                        break;
                    case "0c":
                        // 0c = PiZero W
                        $arch = '01';
                        // single processor models with on-board Wi-Fi and/or Bluetooth
                        $redis->exists('soxrmpdonoff') || $redis->set('soxrmpdonoff', 1);
                        $redis->exists('bluetooth_on') || $redis->set('bluetooth_on', 1);
                        $redis->exists('wifi_on') || $redis->set('wifi_on', 1);
                        $redis->hExists('airplay', 'soxronoff') || $redis->hSet('airplay', 'soxronoff', 1);
                        $redis->hExists('airplay', 'metadataonoff') || $redis->hSet('airplay', 'metadataonoff', 1);
                        $redis->hExists('airplay', 'artworkonoff') || $redis->hSet('airplay', 'artworkonoff', 1);
                        $redis->hExists('airplay', 'enable') || $redis->hSet('airplay', 'enable', 1);
                        $redis->hExists('airplay', 'metadata_enabled') || $redis->hSet('airplay', 'metadata_enabled', 'yes');
                        $redis->hExists('spotifyconnect', 'metadata_enabled') || $redis->hSet('spotifyconnect', 'metadata_enabled', 1);
                        $redis->hExists('AccessPoint', 'enable') || $redis->hSet('AccessPoint', 'enable', 1);
                        break;
                    case "08":
                        // 08 = Pi3B,
                    case "0d":
                        // 0d = Pi3B+
                    case "0e":
                        // 0e = Pi3A+
                    case "11":
                        // 11 = Pi4B+
                    case "12":
                        // 12 = PiZero 2 W
                    case "13":
                        // 13 = Pi400
                        $arch = '08';
                    case "17":
                        // 17 = Pi5B
                        $arch = '08';
                        // multi processor models with on-board Wi-Fi and/or Bluetooth
                        $redis->exists('soxrmpdonoff') || $redis->set('soxrmpdonoff', 1);
                        $redis->exists('bluetooth_on') || $redis->set('bluetooth_on', 1);
                        $redis->exists('wifi_on') || $redis->set('wifi_on', 1);
                        $redis->hExists('airplay', 'soxronoff') || $redis->hSet('airplay', 'soxronoff', 1);
                        $redis->hExists('airplay', 'metadataonoff') || $redis->hSet('airplay', 'metadataonoff', 1);
                        $redis->hExists('airplay', 'artworkonoff') || $redis->hSet('airplay', 'artworkonoff', 1);
                        $redis->hExists('airplay', 'enable') || $redis->hSet('airplay', 'enable', 1);
                        $redis->hExists('airplay', 'metadata_enabled') || $redis->hSet('airplay', 'metadata_enabled', 'yes');
                        $redis->hExists('spotifyconnect', 'metadata_enabled') || $redis->hSet('spotifyconnect', 'metadata_enabled', 1);
                        $redis->hExists('AccessPoint', 'enable') || $redis->hSet('AccessPoint', 'enable', 1);
                        break;
                    default:
                        $arch = '08';
                        // unknown models assume multi processor models with on-board Wi-Fi and/or Bluetooth
                        $redis->exists('soxrmpdonoff') || $redis->set('soxrmpdonoff', 1);
                        $redis->exists('bluetooth_on') || $redis->set('bluetooth_on', 1);
                        $redis->exists('wifi_on') || $redis->set('wifi_on', 1);
                        $redis->hExists('airplay', 'soxronoff') || $redis->hSet('airplay', 'soxronoff', 1);
                        $redis->hExists('airplay', 'metadataonoff') || $redis->hSet('airplay', 'metadataonoff', 1);
                        $redis->hExists('airplay', 'artworkonoff') || $redis->hSet('airplay', 'artworkonoff', 1);
                        $redis->hExists('airplay', 'enable') || $redis->hSet('airplay', 'enable', 1);
                        $redis->hExists('airplay', 'metadata_enabled') || $redis->hSet('airplay', 'metadata_enabled', 'yes');
                        $redis->hExists('spotifyconnect', 'metadata_enabled') || $redis->hSet('spotifyconnect', 'metadata_enabled', 1);
                        $redis->hExists('AccessPoint', 'enable') || $redis->hSet('AccessPoint', 'enable', 1);
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
    if (!isset($model)) {
        $model = '';
    }
    //
    return array($arch, $model);
}

function wrk_setHwPlatform($redis, $reset=false)
{
    list($arch, $model) = wrk_getHwPlatform($redis, $reset);
    runelog('arch= ', $arch);
    // register the player ID
    $playerid = wrk_playerID($arch);
    $redis->set('playerid', $playerid);
    runelog('playerid= ', $playerid);
    // register the hardware platform into database
    $redis->set('hwplatformid', $arch);
    // register the model into database
    $redis->set('hwmodel', $model);
    //
    // fix for broken luakit on the Pi4
    if ($model == '11') {
        // its a Pi4
        $filename = '/usr/bin/chromium';
        clearstatcache(true, $filename);
        if (file_exists($filename)) {
            // chromium is installed, use it
            $redis->hSet('local_browser', 'browser', 'chromium');
        }
    } else {
        // fix for poor performance of luakit on all platforms, use chromium
        if (file_exists($filename)) {
            // chromium is installed, use it
            $redis->hSet('local_browser', 'browser', 'chromium');
        }
    }
    //
    switch($arch) {
        case '01':
            $redis->set('hwplatform', 'RaspberryPi1');
            break;
        case '02':
            $redis->set('hwplatform', 'UDOO');
            break;
        case '03':
            $redis->set('hwplatform', 'CuBox');
            break;
        case '04':
            $redis->set('hwplatform', 'BeagleBone Black');
            break;
        case '05':
            $redis->set('hwplatform', 'Utilite Standard');
            break;
        case '06':
            $redis->set('hwplatform', 'Cubietruck');
            break;
        case '08':
            $redis->set('hwplatform', 'RaspberryPi2');
            break;
        case '09':
            $redis->set('hwplatform', 'ODROID-C1');
            break;
        case '10':
            $redis->set('hwplatform', 'ODROID-C2');
            break;
        default:
            $redis->set('hwplatform', 'unknown');
    }
    return array($arch, $model);
}

function wrk_startPlayer($redis, $newPlayer)
{
    $activePlayer = trim($redis->get('activePlayer'));
    if (!isset($activePlayer) || ($activePlayer == '')) {
        $activePlayer = 'MPD';
    }
    // stopped player is the player which will resume when the current stops
    //  only MPD is capable of being started by then back-end
    $redis->set('stoppedPlayer', 'MPD');
    // set the new player
    $redis->set('activePlayer', $newPlayer);
    // connect to MPD daemon
    $sock = openMpdSocket($redis->hGet('mpdconf', 'bind_to_address'), 0);
    if (($activePlayer === 'MPD') && ($newPlayer != 'MPD')) {
        // record  the mpd status
        wrk_mpdPlaybackStatus($redis);
        if ($sock) {
            $status = _parseStatusResponse($redis, MpdStatus($sock));
            // runelog('MPD status', $status);
            // change the MPD output device to null and pause the player (if it is playing)
            // get the current audio output
            $audioOutput = $redis->get('ao');
            // enable the null audio output (then selected and null audio output activated)
            sendMpdCommand($sock, 'outputset null 1');
            // disable the selected audio output (then only the null audio output activated)
            if (isset($audioOutput) && $audioOutput) {
                sendMpdCommand($sock, 'outputset '.$audioOutput.' 0');
            }
            if ($status['state'] === 'play') {
                // it's playing, so pause playback
                if (sendMpdCommand($sock, 'pause')) {
                    // readMpdResponse($sock);
                }
                // debug
                runelog('sendMpdCommand', 'pause');
            }
        }
    } elseif (($activePlayer === 'Airplay') && ($newPlayer != 'Airplay')) {
        // stop the Airplay metadata worker
        wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'airplaymetadata', 'action' => 'stop'));
        // if ($newPlayer === 'SpotifyConnect') {
            // this will disconnect an exiting Airplay stream
            // do it only when connecting to another stream
            sysCmd('systemctl restart shairport-sync');
        // }
    } elseif (($activePlayer === 'SpotifyConnect') && ($newPlayer != 'SpotifyConnect')) {
        // stop SpotifyConnect worker for SpotifyConnect
        wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'spotifyconnectmetadata', 'action' => 'stop'));
        // set the new player
        $redis->set('activePlayer', $newPlayer);
        // if ($newPlayer === 'Airplay') {
            // this will disconnect an exiting SpotifyConnect stream
            // do it only when connecting to another stream
            sysCmd('systemctl restart spotifyd');
        // }
        $redis->hSet('spotifyconnect', 'last_track_id', '');
        sysCmd('mpc volume '.$redis->get('lastmpdvolume'));
        if (($newPlayer === 'MPD') && ($redis->get('mpd_playback_laststate') == 'pause')) {
            // to-do: work out a better way to do this
            // we need to pause MPD very early to allow spotify connect to start correctly
            //  this means that we need to assume that if the stopped player is MDP and it's saved
            //  state is paused then its real previous state was playing
            $redis->set('mpd_playback_laststate', 'play');
        }
        ui_render('playback', "{\"currentartist\":\"Spotify Connect\",\"currentsong\":\"Switching\",\"currentalbum\":\"-----\",\"artwork\":\"\",\"genre\":\"\",\"comment\":\"\",\"volume\":\"0\",\"state\":\"stop\"}");
        sysCmd('curl -s -X GET http://localhost/command/?cmd=renderui');
    } elseif (($activePlayer === 'Bluetooth') && ($newPlayer != 'Bluetooth')) {
        wrk_btcfg($redis, 'disconnect_sources');
        sleep(2);
    }
    if ($sock) {
        // to get MPD out of its idle-loop we discribe to a channel
        sendMpdCommand($sock, 'subscribe '.$newPlayer);
        sendMpdCommand($sock, 'unsubscribe '.$newPlayer);
        closeMpdSocket($sock);
    }
    if ($newPlayer == 'MPD') {
        if ($activePlayer != 'MPD') {
            wrk_mpdRestorePlayerStatus($redis);
        }
        wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'airplaymetadata', 'action' => 'stop'));
        wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'spotifyconnectmetadata', 'action' => 'stop'));
    } elseif ($newPlayer == 'Airplay') {
        wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'airplaymetadata', 'action' => 'start'));
        wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'spotifyconnectmetadata', 'action' => 'stop'));
    } elseif ($newPlayer == 'SpotifyConnect') {
        wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'airplaymetadata', 'action' => 'stop'));
        wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'spotifyconnectmetadata', 'action' => 'start'));
    } elseif ($newPlayer == 'Bluetooth') {
        wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'airplaymetadata', 'action' => 'stop'));
        wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'spotifyconnectmetadata', 'action' => 'stop'));
    }
    usleep(500000);
    sysCmd('curl -s -X GET http://localhost/command/?cmd=renderui');
    // set process priority
    sysCmdAsync($redis, 'nice --adjustment=10 /srv/http/command/rune_prio nice');
}

function wrk_stopPlayer($redis)
{
    // we previously stopped playback of one player to use the Stream
    $stoppedPlayer = $redis->get('stoppedPlayer');
    if ($stoppedPlayer === '') {
        // if no stopped player is specified use MPD as default
        $stoppedPlayer = 'MPD';
        $redis->set('stoppedPlayer', $stoppedPlayer);
    }
    runelog('wrk_stopPlayer stoppedPlayer = ', $stoppedPlayer);
    // start the stopped player
    wrk_startPlayer($redis, $stoppedPlayer);
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
    if (file_exists('/sys/class/net/eth0/address')) {
        // there is a wired Ethernet adaptor (most hw devices)
        $playerid = $arch.md5_file('/sys/class/net/eth0/address');
    } else if (file_exists('/sys/class/net/wlan0/address')) {
        // there is a wireless Ethernet adaptor (zero W & moderner model A)
        $playerid = $arch.md5_file('/sys/class/net/wlan0/address');
    } else {
        // other hw without network adaptor
        $retval = sysCmd('grep -hPoi "^Serial\s*:\s*\K[[:xdigit:]]{16}" /proc/cpuinfo');
        $playerid = $arch.'CPU'.$retval[0];
        unset($retval);
    }
    // and just in case...
    if (!isset($playerid) || !$playerid){
        $playerid = $arch.'-00000-UNKNOWN-00000-';
    }
    return $playerid;
}

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
        // add the server name to /etc/systemd/timesyncd.conf.d/runeaudio.conf
        // this file also contains a series of failover ntp servers
        $file = '/etc/systemd/timesyncd.conf.d/runeaudio.conf';
        // replace the line with 'NTP=' in the line 1 line after a line containing '# Next line is set in RuneAudio Settings'
        // but add the valid pool.ntp.org ntp server to the line in case the new server is invalid
        if ($ntpserver === 'pool.ntp.org') {
            $newArray = wrk_replaceTextLine($file, '', 'NTP=', 'NTP=pool.ntp.org', '# Next line is set in RuneAudio Settings', 1);
        } else {
            $newArray = wrk_replaceTextLine($file, '', 'NTP=', 'NTP='.$ntpserver.' pool.ntp.org', '# Next line is set in RuneAudio Settings', 1);
        }
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
// restart Samba
{
    // on RPiOS smbd, nmbd and winbindd services are used, while on ARCH they are smb, nmb and winbind
    //  the latest standard uses smb, nmb and winbind, Debian (and RIPiOS) will support both in the future
    //  depending on the available files set up the stop, start, enable and disable systemctl command strings
    // no need for clearstatcache() the service files are static
    if (file_exists('/usr/lib/systemd/system/smb.service')) {
        $serviceNames = array("smb", "nmb", "winbind");
    } else if (file_exists('/usr/lib/systemd/system/smbd.service')) {
        $serviceNames = array("smbd", "nmbd", "winbindd");
    } else if (file_exists('/etc/systemd/system/smb.service')) {
        $serviceNames = array("smb", "nmb", "winbind");
    } else if (file_exists('/etc/systemd/system/smbd.service')) {
        $serviceNames = array("smbd", "nmbd", "winbindd");
    } else if (file_exists('/lib/systemd/system/smb.service')) {
        $serviceNames = array("smb", "nmb", "winbind");
    } else if (file_exists('/lib/systemd/system/smbd.service')) {
        $serviceNames = array("smbd", "nmbd", "winbindd");
    } else {
        $serviceNames = array("smb", "nmb", "winbind");
    }
    $sambaStopCommand = 'systemctl stop '.$serviceNames[0].' ; systemctl stop '.$serviceNames[1].' ; systemctl stop '.$serviceNames[2];
    $sambaDisableCommand = 'systemctl disable '.$serviceNames[0].' ; systemctl disable '.$serviceNames[1].' ; systemctl disable '.$serviceNames[2];
    $sambaStartCommand = 'systemctl start '.$serviceNames[0].' ; systemctl start '.$serviceNames[1].' ; systemctl start '.$serviceNames[2];
    $sambaEnableCommand = 'systemctl enable '.$serviceNames[0].' ; systemctl enable '.$serviceNames[1].' ; systemctl enable '.$serviceNames[2];
    //
    runelog('Samba Stopping...', '');
    sysCmd($sambaStopCommand);
    sysCmd($sambaDisableCommand);
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
    if ($redis->get('dev') || $redis->hGet('samba', 'enable')) {
        runelog('Samba Restarting...', '');
        sysCmd('systemctl daemon-reload');
        sysCmd($sambaStartCommand);
        sysCmd($sambaEnableCommand);
    }
}

function wrk_changeHostname($redis, $newhostname)
{
    // new hostname can not have spaces or special characters
    $newhostname = trim($newhostname);
    If ($newhostname != preg_replace('/[^A-Za-z0-9-]/', '', $newhostname)) {
        // do not do anything
        ui_notifyError($redis, 'Hostname', 'New hostname invalid, hostname change failed');
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
            $redis->hSet('spotifyconnect', 'last_track_id', '');
            sysCmd('mpc volume '.$redis->get('lastmpdvolume'));
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
    wrk_avahiconfig($redis, 'hostname', strtolower($newhostname));
    // activate when a change has been made
    if ($redis->hGet('avahi', 'confchange')) {
        // reload or restart avahi-daemon if it is running (active), some users switch it off
        // it is also started automatically when shairport-sync starts
        sysCmd('pgrep avahi-daemon && systemctl stop avahi-daemon');
        sysCmd('systemctl daemon-reload');
        sysCmd('pgrep avahi-daemon || systemctl start avahi-daemon');
        // connman also needs to be restarted, otherwise connected wifi will fail
        sysCmd('systemctl restart connman');
        // reconfigure MPD
        //wrk_mpdPlaybackStatus($redis);
        wrk_mpdRestorePlayerStatus($redis);
        // restart SAMBA
        wrk_restartSamba($redis);
    }
    $redis->hSet('avahi', 'confchange', 0);
    // set process priority
    sysCmdAsync($redis, 'nice --adjustment=10 /srv/http/command/rune_prio nice');
}

function wrk_upmpdcli($redis, $name = null, $queueowner = null, $services = null, $enable = null, $jobID = null)
{
    $nameOld = $redis->hGet('dlna', 'name');
    if (isset($name) && $name) {
        $redis->hSet('dlna', 'name', $name);
    } else {
        $name = $nameOld;
    }
    $queueownerOld = $redis->hGet('dlna', 'queueowner');
    if (isset($queueowner) && strlen($queueowner)) {
        $redis->hSet('dlna', 'queueowner', $queueowner);
    } else {
        $queueowner = $queueownerOld;
    }
    $servicesOld = $redis->hGet('dlna', 'services');
    if (isset($services) && $services) {
        $redis->hSet('dlna', 'services', $services);
    } else {
        $services = $servicesOld;
    }
    $enableOld = $redis->hGet('dlna', 'enable');
    if (isset($enable) && strlen($enable)) {
        $redis->hSet('dlna', 'enable', $enable);
    } else {
        $enable = $enableOld;
    }
    if (isset($jobID) && $jobID) {
        $redis->sRem('w_lock', $jobID);
    }
    $serviceFile = '/etc/systemd/system/upmpdcli.service';
    $configFile = '/etc/upmpdcli.conf';
    // set the name and log level
    sysCmd('sed -i '."'".'/^friendlyname/ s|.*|friendlyname = '.$name.'|'."' '".$configFile."'");
    sysCmd('sed -i '."'".'/^ohproductroom/ s|.*|ohproductroom = '.$name.'|'."' '".$configFile."'");
    sysCmd('sed -i '."'".'/^loglevel/ s|.*/|loglevel = '.$logLevel.'|'."' '".$configFile."'");
    $logFile = $redis->hGet('dlna', 'logfile');
    $logLevel = $redis->hGet('dlna', 'loglevel');
    if (($name != $nameOld) || ($queueowner != $queueownerOld)) {
        $action = 'Updated';
        sysCmd('sed -i '."'".'/^ExecStart/ s|.*|ExecStart=/usr/bin/upmpdcli -m 1 -c /etc/upmpdcli.conf -q '.$queueowner.' -d "'.$logFile.'" -l '.$logLevel.' -f "'.$name.'"|'."' '".$serviceFile."'");
        // update systemd
        sysCmd('systemctl daemon-reload');
        // the modifications above should work, but the parameter file seems to override the parameters on the ExecStart unit file line line
        // modify them all
        sysCmd('sed -i '."'".'/^ownqueue/ s|.*|ownqueue = '.$queueowner.'|'."' '".$configFile."'");
        sysCmd('sed -i '."'".'/^logfilename/ s|.*|logfilename = '.$logFile.'|'."' '".$configFile."'");
        // update systemd
        sysCmd('systemctl daemon-reload');
        if ($enable) {
            runelog('restart upmpdcli');
            sysCmd('pgrep upmpdcli && systemctl stop upmpdcli');
        }
    }
    if (($services != $servicesOld)) {
        $action = 'Updated';
        sysCmd('sed -i '."'".'/^ExecStart/ s|.*|ExecStart=/usr/bin/upmpdcli -m 1 -c /etc/upmpdcli.conf -q '.$queueowner.' -d "'.$logFile.'" -l '.$logLevel.' -f "'.$name.'"|'."' '".$serviceFile."'");
        // update systemd
        sysCmd('systemctl daemon-reload');
        if ($services == 'UPnP AV') {
            sysCmd('sed -i '."'".'/^upnpav/ s|.*|upnpav = 1|'."' '".$configFile."'");
            sysCmd('sed -i '."'".'/^openhome/ s|.*|openhome = 0|'."' '".$configFile."'");
        } else if ($services == 'OpenHome') {
            sysCmd('sed -i '."'".'/^upnpav/ s|.*|upnpav = 0|'."' '".$configFile."'");
            sysCmd('sed -i '."'".'/^openhome/ s|.*|openhome = 1|'."' '".$configFile."'");
        } else if ($services == 'Both') {
            sysCmd('sed -i '."'".'/^upnpav/ s|.*|upnpav = 1|'."' '".$configFile."'");
            sysCmd('sed -i '."'".'/^openhome/ s|.*|openhome = 1|'."' '".$configFile."'");
        }
        if ($enable) {
            runelog('restart upmpdcli');
            sysCmd('pgrep upmpdcli && systemctl stop upmpdcli');
        }
    }
    if ($enable) {
        runelog('start upmpdcli');
        if ($enable != $enableOld) {
            if (isset($action)) {
                $action .= ' and enabled';
            } else {
                $action = 'Enabled';
            }
        }
        sysCmd('pgrep upmpdcli || systemctl start upmpdcli');
        // set process priority
        sysCmdAsync($redis, 'nice --adjustment=10 /srv/http/command/rune_prio nice');
    } else {
        runelog('stop upmpdcli');
        if ($enable != $enableOld) {
            if (isset($action)) {
                $action .= ' and disabled';
            } else {
                $action = 'Disabled';
            }
        }
        sysCmd('pgrep upmpdcli && systemctl stop upmpdcli');
    }
    if (isset($action)) {
        ui_notify($redis, 'UPnP/DLNA', 'UPnP/DLNA '.$action);
    }
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
function addRadio($redis, $mpd, $data)
// data->label contains the file name without the extension ('.pls')
{
    if ($data->label !== '' && $data->url !== '') {
        //debug
        runelog('addRadio (data)', $data);
        // store webradio record in redis
        $redis->hSet('webradios', $data->label, $data->url);
        // create new file
        $file = '/mnt/MPD/Webradio/'.$data->label.'.pls';
        $newpls = "[playlist]\n";
        $newpls .= "NumberOfEntries=1\n";
        $newpls .= "File1=".$data->url."\n";
        $newpls .= "Title1=".$data->label."\n";
        // Commit changes to .pls file
        $fp = fopen($file, 'w');
        $return = fwrite($fp, $newpls);
        fclose($fp);
        sendMpdCommand($mpd, 'update Webradio');
    } else {
        $return = false;
    }
    return $return;
}

function editRadio($redis, $mpd, $data)
// data->label and $data->newlabel contain the file name without the extension ('.pls')
{
    if ($data->label !== '' && $data->url !== '') {
        //debug
        runelog('editRadio (data)', $data);
        // delete and add the radio
        $data->label = $data->label.'.pls';
        deleteRadio($redis, $mpd, $data);
        if ($data->newlabel !== '') {
            $data->label = $data->newlabel;
        }
        unset($data->newlabel);
        addRadio($redis, $mpd, $data);
        sendMpdCommand($mpd, 'update Webradio');
        $return = true;
    } else {
        $return = false;
    }
    return $return;
}

function deleteRadio($redis, $mpd, $data)
// data->label contains the full file name (including the '.pls')
{
    if ($data->label !== '') {
        //debug
        runelog('deleteRadio (data)', $data);
        // delete .pls file
        $file = '/mnt/MPD/Webradio/'.$data->label;
        runelog('deleteRadio (label)', $data->label);
        $return = unlink($file);
        if ($return) {
            // delete webradio record in redis
            $label = str_replace('.pls', '', $data->label);
            $redis->hDel('webradios', $label);
            sendMpdCommand($mpd, 'update Webradio');
        }
    } else {
        $return = false;
    }
    return $return;
}

function ui_notify($redis, $title, $text, $type = null, $permanotice = null)
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
    $cnt = 3;
    do {
        // loop, sleeping for half a second until the UI is active
        //  timeout/continue after 1,5 seconds ($cnt = 3 x 0,5)
        usleep(500000);
        // sleep(1);
    } while ($redis->get('waitSyWrk') && --$cnt >= 0);
    if ($redis->get('waitSyWrk')) {
        // still no UI, queue the message and start a job to process it
        $redis->lPush('w_message', base64_encode(json_encode($output)));
        // using nodupliates ensures that only one job is queued to process the queued messages
        wrk_control($redis, 'noduplicates', $data = array('wrkcmd' => 'notify_async'));
    } else {
        // UI is available, display any queued messages, the messages are then sent in fifo ourder
        do {
            $message = $redis->rPop('w_message');
            // debug
            // echo "Message : $message\n";
            runelog('Message : '.$message);
            if (isset($message) && $message) {
                ui_render('notify', base64_decode($message));
                // sleep for 0,5 second between sending messages
                usleep(500000);
                // sleep(1);
            }
        } while (isset($message) && $message);
        // display the current message
        ui_render('notify', json_encode($output));
    }
}

function ui_notifyError($redis, $title, $text, $type = null, $permanotice = null)
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
    $cnt = 3;
    do {
        // loop, sleeping for half a second until the UI is active
        //  timeout/continue after 1,5 seconds ($cnt = 3 x 0,5)
        usleep(500000);
        // sleep(1);
    } while ($redis->get('waitSyWrk') && --$cnt >= 0);
    if ($redis->get('waitSyWrk')) {
        // still no UI, queue the message and start a job to process it
        $redis->lPush('w_message', base64_encode(json_encode($output)));
        // using nodupliates ensures that only one job is queued to process the queued messages
        wrk_control($redis, 'noduplicates', $data = array('wrkcmd' => 'notify_async'));
    } else {
        // UI is available, display any queued messages, the messages are then sent in fifo ourder
        do {
            $message = $redis->rPop('w_message');
            // debug
            // echo "Message : $message\n";
            runelog('Message : '.$message);
            if (isset($message) && $message) {
                ui_render('notify', base64_decode($message));
                // sleep for 0,5 second between sending messages
                usleep(500000);
                // sleep(1);
            }
        } while (isset($message) && $message);
        // display the current message
        ui_render('notify', json_encode($output));
    }
}

// function ui_notify_async($redis, $title, $text, $type = null, $permanotice = null)
// {
    // if (is_object($permanotice)) {
        // $output = array('title' => $title, 'permanotice' => '', 'permaremove' => '');
    // } else {
        // if ($permanotice === 1) {
            // $output = array('title' => $title, 'text' => $text, 'permanotice' => '');
        // } else {
            // $output = array('title' => $title, 'text' => $text);
        // }
    // }
    // $output = json_encode($output);
    // runelog('notify (async) JSON string: ', $output);
    // if (!strpos(' '.$output,"'")) {
        // sysCmdAsync($redis, '/srv/http/command/ui_notify.php \''.$output.'\'');
    // } else {
        // sysCmdAsync($redis, '/srv/http/command/ui_notify.php "'.$output.'"');
    // }
// }

// function wrk_notify($redis, $action, $notification, $jobID = null)
// {
    // switch ($action) {
        // case 'raw':
            // // debug
            // runelog('wrk_notify (raw)', $notification);
            // break;
        // case 'startjob':
            // if (!empty($notification)) {
                // if (is_object($notification)) {
                    // $notification = json_encode(array('title' => $notification->title, 'text' => $notification->text, 'icon' => 'fa fa-cog fa-spin', 'permanotice' => $jobID));
                    // // debug
                    // runelog('wrk_notify (startjob) jobID='.$jobID, $notification);
                // }
                // if (wrk_notify_check($notification)) {
                    // if (empty($redis->hGet('notifications', $jobID)) && empty($redis->hGet('notifications', 'permanotice_'.$jobID))) {
                        // $redis->hSet('notifications', $jobID, $notification);
                    // }
                // }
            // }
            // break;
        // case 'endjob':
            // $notification = $redis->hGet('notifications', $jobID);
            // if (!empty($notification)) {
                // $notification = json_decode($notification);
                // $notification = json_encode(array('title' => $notification->title, 'text' => '', 'permanotice' => $jobID, 'permaremove' => $jobID));
                // // debug
                // runelog('wrk_notify (endjob) jobID='.$jobID, $notification);
                // $redis->hDel('notifications', $jobID);
            // }
            // break;
        // case 'kernelswitch':
            // // debug
            // runelog('wrk_notify (kernelswitch) jobID='.$jobID, $notification);
            // if (!empty($notification)) {
                // $notification = json_encode(array('title' => $notification->title, 'text' => $notification->text, 'custom' => 'kernelswitch'));
                // if (wrk_notify_check($notification)) {
                    // // if (empty($redis->hGet('notifications', $jobID)) && empty($redis->hGet('notifications', 'permanotice_'.$jobID))) {
                        // $redis->hSet('notifications', 'permanotice_kernelswitch', $notification);
                    // // }
                // }
            // }
            // break;
    // }
    // if (wrk_notify_check($notification)) ui_render('notify', $notification);
// }

// function wrk_notify_check($notification)
// {
    // if (json_decode($notification) !== null) {
        // $notification = json_decode($notification);
        // if (isset($notification->title) && isset($notification->text)) {
            // return true;
        // } else {
            // return false;
        // }
    // } else {
        // return false;
    // }
// }

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

function ui_status($redis, $mpd, $status)
{
    if (isset($status['song'])) {
        $curTrack = getTrackInfo($mpd, $status['song']);
    } else {
        // MPD is stopped after completing playing the queue
        //  get info about the first track in  the queue
        $curTrack = getTrackInfo($mpd, 0);
        //  randomise the playlist variable to a negative value to force a queue update, normally MPD moves this number sequentially forward
        //      when it detects a queue change, but end-of-queue is not reported (it not foolproof, duplicate number could be returned)
        $status['playlist'] = rand(-100, -1);
        //  elapsed and song_pecent are zero
        $status['elapsed'] = 0;
        $status['song_percent'] = 0;
        //  set up the song, nextsong, songid and nextsongid, however nextsong and nextsongid could be incorrect but this will be
        //      corrected when something is played
        if (!isset($curTrack[0]['Pos']) || is_null($curTrack[0]['Pos'])) {
            $status['song'] = 0;
            $status['nextsong'] = 0;
        } else {
            $status['song'] = $curTrack[0]['Pos'];
            $status['nextsong'] = $status['song'] + 1;
        }
        if (!isset($curTrack[0]['Id']) || is_null($curTrack[0]['Id'])) {
            $status['songid'] = 0;
            $status['nextsongid'] = 0;
        } else {
            $status['songid'] = $curTrack[0]['Id'];
            $status['nextsongid'] = $status['songid'] + 1;
        }
        //  set up the track time
        if (!isset($curTrack[0]['Time']) || is_null($curTrack[0]['Time'])) {
            $status['time'] = 0;
        } else {
            $status['time'] = $curTrack[0]['Time'];
        }
    }
    // debug
    // echo "Function: ui_status\n";
    // var_dump($curTrack);
    if (isset($curTrack[0]['Title'])) {
        // $status['currentalbumartist'] = htmlentities($curTrack[0]['AlbumArtist'], ENT_XML1, 'UTF-8');
        // $status['currentartist'] = htmlentities($curTrack[0]['Artist'], ENT_XML1, 'UTF-8');
        // $status['currentsong'] = htmlentities($curTrack[0]['Title'], ENT_XML1, 'UTF-8');
        // $status['currentalbum'] = htmlentities($curTrack[0]['Album'], ENT_XML1, 'UTF-8');
        // $status['currentcomposer'] = htmlentities($curTrack[0]['Composer'], ENT_XML1, 'UTF-8');
        if (isset($curTrack[0]['AlbumArtist']) && $curTrack[0]['AlbumArtist']) {
            $status['currentalbumartist'] = $curTrack[0]['AlbumArtist'];
        }
        if (isset($curTrack[0]['Artist']) && $curTrack[0]['Artist']) {
            $status['currentartist'] = $curTrack[0]['Artist'];
        }
        if (isset($curTrack[0]['Title']) && $curTrack[0]['Title']) {
            $status['currentsong'] = $curTrack[0]['Title'];
        }
        if (isset($curTrack[0]['Album']) && $curTrack[0]['Album']) {
            $status['currentalbum'] = $curTrack[0]['Album'];
        }
        if (isset($curTrack[0]['Composer']) && $curTrack[0]['Composer']) {
            $status['currentcomposer'] = $curTrack[0]['Composer'];
        }
        if (isset($curTrack[0]['Date']) && $curTrack[0]['Date']) {
            $status['date'] = $curTrack[0]['Date'];
        }
    } else if (isset($curTrack) && is_array($curTrack)) {
        $status['currentartist'] = '';
        if (isset($curTrack[0]['file']) && $curTrack[0]['file']) {
            $status['currentalbum'] = parseFileStr($curTrack[0]['file'], '/');
        } else {
            $status['currentalbum'] = '';
        }
    }
    if (isset($curTrack[0]['file']) && $curTrack[0]['file']) {
        $status['file'] = $curTrack[0]['file'];
        $status['fileext'] = parseFileStr($curTrack[0]['file'], '.');
    }
    if (isset($curTrack[0]['Name']) && $curTrack[0]['Name']) {
        $status['radioname'] = htmlentities($curTrack[0]['Name'], ENT_XML1, 'UTF-8');
    }
    if (!isset($status['currentalbumartist'])) {
        $status['currentalbumartist'] = '';
    }
    if (!isset($status['currentartist'])) {
        $status['currentartist'] = '';
    }
    if (!isset($status['currentsong'])) {
        $status['currentsong'] = '';
    }
    if (!isset($status['currentalbum'])) {
        $status['currentalbum'] = '';
    }
    if (!isset($status['currentcomposer'])) {
        $status['currentcomposer'] = '';
    }
    if (!isset($status['file'])) {
        $status['file'] = '';
    }
    if (!isset($status['fileext'])) {
        $status['fileext'] = '';
    }
    if (!isset($status['radioname'])) {
        $status['radioname'] = '';
    }
    if (!isset($status['date'])) {
        $status['date'] = '';
    }
    //
    // special radio processing
    // delete the previous radio details if it is the first time since reboot
    if (is_firstTime($redis, 'last_radio_details')) {
        $redis->del('last_radio_details');
    }
    // get stored previous radio details (it could be empty!)
    $lastRadioDetails = json_decode($redis->get('last_radio_details'), true);
    // radio's are not always detected correctly, correct them and determine the radio name
    if (!$status['radioname'] && isset($status['file']) && (strtolower(substr($status['file'], 0, 4)) == 'http')) {
        // radio name not detected by MPD but the file is has a http prefix, it could still be a radio
        if (isset($lastRadioDetails['file']) && ($lastRadioDetails['file'] == $status['file'])) {
            // its the same radio as the previous one
            $status['radioname'] = $lastRadioDetails['radioname'];
            // current album is the radio name
            $status['currentalbum'] = $lastRadioDetails['radioname'];
        } else {
            // search the stored radio stations for the url (returns the radio station name or false)
            $radioName = is_radioUrl($redis, $status['file']);
            if ($radioName) {
                // its a radio url
                $status['radioname'] = $radioName;
                // current album is also the radio name
                $status['currentalbum'] = $radioName;
            }
        }
    }
    // track time is not returned from MPD for radio's set it to a default of 100 (seconds)
    if ($status['radioname']) {
        $status['time'] = 100;
    } else {
        // its not a radio, remove stored radio information
        if ($redis->exists('last_radio_details')) {
            $redis->del('last_radio_details');
        }
    }
    // elapsed time is not returned for some streaming sources like radio (sometimes), calculate it (and the song percent)
    if ($status['radioname'] && isset($status['file']) && isset($status['state'])) {
        // its a radio and we have enough information to generate the elapsed time
        $now = microtime(true);
        if (!isset($lastRadioDetails['file'])) {
            // no last radio information, its a new radio
            $status['elapsed'] = 0;
            $status['song_percent'] = 0;
            $pauseTimeStamp = 0;
            $elapsedTimeStamp = 0;
        } else if ($status['file'] != $lastRadioDetails['file']) {
            // its a different radio station
            $status['elapsed'] = 0;
            $status['song_percent'] = 0;
            $pauseTimeStamp = 0;
            $elapsedTimeStamp = 0;
        } else if ($status['state'] == 'stop') {
            // its stopped
            $status['elapsed'] = 0;
            $status['song_percent'] = 0;
            $pauseTimeStamp = 0;
            $elapsedTimeStamp = 0;
        } else if ($status['state'] == 'play') {
            if (!isset($lastRadioDetails['elapsed_time']) || !$lastRadioDetails['elapsed_time']) {
                // its just started
                $status['elapsed'] = 0;
                $status['song_percent'] = 0;
            } else {
                // it had already started playing, now playing, could have paused, update the elapsed time
                $status['elapsed'] = round($lastRadioDetails['elapsed'] + ($now - $lastRadioDetails['elapsed_time']));
                if (isset($lastRadioDetails['paused_time']) && $lastRadioDetails['paused_time']) {
                    // it had paused, update the elapsed time
                    $status['elapsed'] = round($status['elapsed'] - ($now - $lastRadioDetails['paused_time']));
                }
                $status['song_percent'] = min(100, round(100*$status['elapsed']/$status['time']));
            }
            $pauseTimeStamp = 0;
            $elapsedTimeStamp = $now;
        } else if ($status['state'] == 'pause') {
            if (!isset($lastRadioDetails['elapsed_time']) || !$lastRadioDetails['elapsed_time']) {
                // its just started
                $status['elapsed'] = 0;
                $status['song_percent'] = 0;
            } else {
                $status['elapsed'] = round($lastRadioDetails['elapsed'] + ($now - $lastRadioDetails['elapsed_time']));
                if (isset($lastRadioDetails['paused_time']) && $lastRadioDetails['paused_time']) {
                    // it was previously paused
                    $status['elapsed'] = round($status['elapsed'] - ($now - $lastRadioDetails['paused_time']));
                }
                $status['song_percent'] = min(100, round(100*$status['elapsed']/$status['time']));;
            }
            $pauseTimeStamp = $now;
            $elapsedTimeStamp = $now;
        }
        // save the last radio information
        $lastRadioDetails['file'] = $status['file'];
        $lastRadioDetails['radioname'] = $status['radioname'];
        $lastRadioDetails['elapsed'] = $status['elapsed'];
        $lastRadioDetails['elapsed_time'] = $elapsedTimeStamp;
        $lastRadioDetails['paused_time'] = $pauseTimeStamp;
        $redis->set('last_radio_details', json_encode($lastRadioDetails));
    }
    //
    // special hardware input processing
    if (isset($status['file']) && ((strtolower(substr($status['file'], 0, 5)) == 'alsa:') || (strtolower(substr($status['file'], 0, 5)) == 'cdda:'))) {
        // its a hardware source
        if (!isset($status['time']) || !$status['time']) {
            // when the song duration is not set, set it to 100 seconds
            $status['time'] = 100;
        }
        // delete the previous alsa details if it is the first time since reboot
        if (is_firstTime($redis, 'last_alsa_details')) {
            $redis->del('last_alsa_details');
        }
        // get stored previous alsa details (it could be empty!)
        $lastAlsaDetails = json_decode($redis->get('last_alsa_details'), true);
        $now = microtime(true);
        if (!isset($lastAlsaDetails['file'])) {
            // no last radio information, its a new radio
            $status['elapsed'] = 0;
            $status['song_percent'] = 0;
            $pauseTimeStamp = 0;
            $elapsedTimeStamp = 0;
        } else if ($status['file'] != $lastAlsaDetails['file']) {
            // its a different radio station
            $status['elapsed'] = 0;
            $status['song_percent'] = 0;
            $pauseTimeStamp = 0;
            $elapsedTimeStamp = 0;
        } else if ($status['state'] == 'stop') {
            // its stopped
            $status['elapsed'] = 0;
            $status['song_percent'] = 0;
            $pauseTimeStamp = 0;
            $elapsedTimeStamp = 0;
        } else if ($status['state'] == 'play') {
            if (!isset($lastAlsaDetails['elapsed_time']) || !$lastAlsaDetails['elapsed_time']) {
                // its just started
                $status['elapsed'] = 0;
                $status['song_percent'] = 0;
            } else {
                // it had already started playing, now playing, could have paused, update the elapsed time
                $status['elapsed'] = round($lastAlsaDetails['elapsed'] + ($now - $lastAlsaDetails['elapsed_time']));
                if (isset($lastAlsaDetails['paused_time']) && $lastAlsaDetails['paused_time']) {
                    // it had paused, update the elapsed time
                    $status['elapsed'] = round($status['elapsed'] - ($now - $lastAlsaDetails['paused_time']));
                }
                $status['song_percent'] = min(100, round(100*$status['elapsed']/$status['time']));
            }
            $pauseTimeStamp = 0;
            $elapsedTimeStamp = $now;
        } else if ($status['state'] == 'pause') {
            if (!isset($lastAlsaDetails['elapsed_time']) || !$lastAlsaDetails['elapsed_time']) {
                // its just started
                $status['elapsed'] = 0;
                $status['song_percent'] = 0;
            } else {
                $status['elapsed'] = round($lastAlsaDetails['elapsed'] + ($now - $lastAlsaDetails['elapsed_time']));
                if (isset($lastAlsaDetails['paused_time']) && $lastAlsaDetails['paused_time']) {
                    // it was previously paused
                    $status['elapsed'] = round($status['elapsed'] - ($now - $lastAlsaDetails['paused_time']));
                }
                $status['song_percent'] = min(100, round(100*$status['elapsed']/$status['time']));;
            }
            $pauseTimeStamp = $now;
            $elapsedTimeStamp = $now;
        }
        // save the last radio information
        $lastAlsaDetails['file'] = $status['file'];
        $lastAlsaDetails['radioname'] = $status['radioname'];
        $lastAlsaDetails['elapsed'] = $status['elapsed'];
        $lastAlsaDetails['elapsed_time'] = $elapsedTimeStamp;
        $lastAlsaDetails['paused_time'] = $pauseTimeStamp;
        $redis->set('last_alsa_details', json_encode($lastAlsaDetails));
    } else {
        // its not a hardware source
        if ($redis->exists('last_alsa_details')) {
            $redis->del('last_alsa_details');
        }
    }
    return $status;
}

function ui_mpd_fix($redis, $status)
//
// get the extra information about the song from the pre-cached information file
//
{
    if ((!isset($status['radio']) || !$status['radio']) && isset($status['file']) && $status['file']) {
        // not for radio and only when file has a value
        unset($datafile);
        $musicDir = rtrim($redis->hGet('mpdconf', 'music_directory'), '/');
        $artDir = rtrim(trim($redis->get('albumart_image_dir')), '/');
        $artUrl = trim($redis->get('albumart_image_url_dir'), " \n\r\t\v\0/");
        // the name of the file name is always the hash of its path
        $datafile = md5($musicDir.'/'.$status['file']);
        $fileName = $artDir.'/'.$datafile.'.mpd';
        // when $datafile is set we can determine a file name
        // ui_notify($redis, 'Test file name ', $fileName);
        $metadata = getMusicFileMatadata($redis, $fileName);
        if ($metadata) {
            // ui_notify($redis, 'Test metadata found ', $fileName);
            // avarage bit rate, always update it when a value is available, MPD returns the actual bit rate at that moment
            if (isset($metadata['avg_bit_rate']) && $metadata['avg_bit_rate']) {
                // ui_notify($redis, 'Test bitrate ', $metadata['avg_bit_rate']);
                $status['bitrate'] = intval($metadata['avg_bit_rate']/1000);
            }
            // sample rate, fix it if missing
            if (!isset($status['audio_sample_rate']) || !$status['audio_sample_rate']) {
                if (isset($metadata['sample_rate']) && $metadata['sample_rate']) {
                    $status['audio_sample_rate'] = round($metadata['sample_rate']/1000, 1);
                }
            }
            // sample depth, fix it if missing
            if (!isset($status['audio_sample_depth']) || !$status['audio_sample_depth']) {
                if (isset($metadata['bits_per_sample']) && $metadata['bits_per_sample']) {
                    $status['audio_sample_depth'] = $metadata['bits_per_sample'];
                }
            }
            // album art, add it if available
            if (isset($metadata['albumarturl']) && $metadata['albumarturl']) {
                // ui_notify($redis, 'Test album url ', $metadata['albumarturl']);
                // available
                $status['mainArtURL'] = $metadata['albumarturl'];
                if ($redis->get('remoteSSbigart') === 'album') {
                    $status['bigArtURL'] = $metadata['albumarturl'];
                } else {
                    $status['smallArtURL'] = $metadata['albumarturl'];
                }
            } else {
                // not available set it to the black image
                $status['mainArtURL'] = $artUrl.'/black.png';
            }
        } else {
            // no metadata file, so set the main art to the black image
            $status['mainArtURL'] = $artUrl.'/black.png';
        }
    }
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
    //  first clean udev drives
    sysCmd('udevil clean');
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
    // Hardware input devices
    if ($redis->hGet('hw_input', 'enable')) {
        $hwInput = $redis->hGetall('hw_input');
        $hwInput['status'] = json_decode($hwInput['status'], true);
        if (!count($hwInput['status'])) {
            unset($hwInput['status']);
        }
    } else {
        $hwInput = '';
    }
    // CD drive input
    if ($redis->hGet('CD', 'enable')) {
        $cdInput = $redis->hGetall('CD');
        $cdInput['status'] = json_decode($cdInput['status'], true);
        if (!count($cdInput['status'])) {
            unset($cdInput['status']);
        }
    } else {
        $cdInput = '';
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
    // $jsonHome = json_encode(array_merge($bookmarks, array(0 => array('networkMounts' => $networkmounts)), array(0 => array('USBMounts' => $usbmounts)), array(0 => array('webradio' => $webradios)), array(0 => array('Dirble' => $dirble->amount)), array(0 => array('ActivePlayer' => $activePlayer))));
    $jsonHome = json_encode(array('internetAvailable' => $internetAvailable, 'bookmarks' => $bookmarks,
                'localStorages' => $localStorages, 'networkMounts' => $networkMounts, 'USBMounts' => $usbMounts,
                'webradio' => $webradios, 'Dirble' => $dirbleAmount, 'Jamendo' => $jamendo,
                'ActivePlayer' => $activePlayer, 'clientUUID' => $clientUUID, 'HWinput' => $hwInput, 'CDinput' => $cdInput));
    // Encode UI response
    runelog('ui_libraryHome - JSON: ', $jsonHome);
    ui_render('library', $jsonHome);
}

function ui_lastFM_coverart($redis, $artist, $album, $lastfmApikey, $proxy)
{
    if (!$redis->hGet('service', 'lastfm')) {
        return false;
    }
    if (!empty($album)) {
        $url = "https://ws.audioscrobbler.com/2.0/?method=album.getinfo&api_key=".$lastfmApikey."&artist=".urlClean($artist)."&album=".urlClean($album)."&format=json";
        unset($artist);
    } else {
        $url = "https://ws.audioscrobbler.com/2.0/?method=artist.getinfo&api_key=".$lastfmApikey."&artist=".urlClean($artist)."&format=json";
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
    if (!empty($album)) {
        if (isset($output['album']['image'][3]['#text'])) {
            runelog('coverart lastfm query album URL:', $output['album']['image'][3]['#text']);
            return $output['album']['image'][3]['#text'];
        } else {
            runelog('coverart lastfm query album URL:', '<no-output>');
        }
    } else {
        if (isset($output['artist']['image'][3]['#text'])) {
            runelog('coverart lastfm query artist URL:', $output['artist']['image'][3]['#text']);
            return $output['artist']['image'][3]['#text'];
        } else {
            runelog('coverart lastfm query artist URL:', '<no-output>');
        }
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
    $url = "https://ws.audioscrobbler.com/2.0/?method=track.getsimilar&limit=1000&api_key=".$lastfmApikey."&artist=".urlClean($artist)."&track=".urlClean($track)."&format=json";
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
    //  and this is used to automatically set the timezone and the Wi-Fi regulatory domain
    // the timezone will only be changed when internet is available, the current timezone is set to the
    //  distribution default timezone (Pacific/Pago_Pago) and the Wi-Fi regulatory domain is set to 00
    //  Pago Pago is GMT -11 hours, so any time adjustment can only go forward, it's Wi-Fi regulatory domain is AS
    // experimented with https://ipsidekick.com/ and https://timezoneapi.io, currently using https://ipapi.co/
    // there seems to be a new standard for regulatory domain which is not exclusively based on letter codes
    //  numeric codes like 'country 98: DFS-FCC' which appears to be a group code for 'FCC' regulated domains
    //
    $retval = strtolower(trim(sysCmd('iw reg get | grep -i country')[0]));
    if (strpos(' '.$retval, 'unset') || is_numeric(get_between_data($retval, 'country ', ':')) || !$redis->exists('regdom')) {
        // the regulatory domain is unset, numeric or unsaved
        $wifiRegDomUnset = true;
    } else if ($redis->exists('regdom')) {
        $regdom = $redis->get('regdom');
        if ($regdom && !is_numeric($regdom)) {
            // the regulatory domain has been saved, but is invalid, treat as unset
            $wifiRegDomUnset = true;
        }
    } else {
        // the regulatory domain has been set
        $wifiRegDomUnset = false;
    }
    $sucess = false;
    if ($redis->hget('service', 'internet') && ($redis->get('timezone') === 'Pacific/Pago_Pago') && $wifiRegDomUnset) {
        // we should automatically change the timezone
        // // make sure that file_get_contents() times out when nothing is returned
        // // used for https://ipsidekick.com/ and https://timezoneapi.io
        // $opts = array('http' =>
            // array(
                // // timeout in seconds
                // // 5 seconds is a little on the high side, 2 or 3 is probably better.
                // // its not really problem because this will only be run once per installation!
                // 'timeout' => 5
            // )
        // );
        // $context  = stream_context_create($opts);
        //
        // https://ipsidekick.com/
        // $result = file_get_contents('https://ipsidekick.com/json', false, $context);
        //
        // https://timezoneapi.io
        // $timezoneapiToken = $redis->hGet('TimezoneAPI', 'apikey');
        // $result = file_get_contents('https://timezoneapi.io/api/ip/?token='.$timezoneapiToken, false, $context);
        //
        // https://ipapi.co/
        $result = implode("\n", sysCmd('curl -s "https://ipapi.co/json/"'));
        // debug
        // $redis->set('wrk_autoset_timezone', $result);
        if ($result) {
            $result = json_decode($result, true);
            // https://ipsidekick.com/
            // if (isset($result['timeZone']['name']) && strlen($result['timeZone']['name'])) {
                // runelog('autoset_timezone :', $result['timeZone']['name']);
                // $timeZone = $result['timeZone']['name'];
                // $countryCode = $result['country']['code'];
                //
            // https://timezoneapi.io
            // if (isset($result['data']['timezone']['id']) && strlen($result['data']['timezone']['id'])) {
                // runelog('autoset_timezone :', $result['data']['timezone']['id']);
                // $timeZone = $result['data']['timezone']['id'];
                // $countryCode = $result['data']['country_code'];
                //
            // https://ipapi.co/
            if (isset($result['timezone']) && strlen($result['timezone'])) {
                runelog('autoset_timezone :', $result['timezone']);
                $timeZone = $result['timezone'];
                $countryCode = $result['country_code'];
                //
                $result = sysCmd('timedatectl set-timezone '."'".$timeZone."' | xargs")[0];
                $result = ' '.strtolower($result);
                if (strpos($result, 'failed') || strpos($result, 'invalid')) {
                    sysCmd("timedatectl set-timezone 'Pacific/Pago_Pago' ; iw reg set 00");
                } else {
                    $redis->set('timezone', $timeZone);
                    $redis->set('regdom', $countryCode);
                    // set the Wi-Fi regulatory domain, the standard is 00 and is compatible with most countries
                    // setting it will could allow more Wi-Fi power to be used (never less) and sometimes improve the usable frequency ranges
                    // not all country codes have a specificity specified regulatory domain profile, so if it fails, set to the default (00)
                    sysCmd('iw reg set '.$countryCode.' || iw reg set 00');
                    ui_notify($redis, 'Timezone', 'Timezone automatically updated.<br>Current timezone: '.$timeZone);
                    $sucess = true;
                }
            }
        }
    }
    return $sucess;
}

function wrk_setTimezone($redis, $timeZone)
// set the timezone and the Wi-Fi regulatory domain from the UI
// return true when successful, false on error
{
    $retval = sysCmd('timedatectl set-timezone '."'".$timeZone."'".' ; echo $? | xargs')[0];
    if (isset($retval) && is_numeric($retval) && ($retval == 0)) {
        // returned 0 value is success
        // save the time zone in redis
        $redis->set('timezone', $timeZone);
        if (!$redis->exists('regdom')) {
            // give the saved regulatory domain a value (00 = unset)
            $redis->set('regdom', '00');
        }
        // set the Wi-Fi regulatory domain based on the current time zone
        $return = wrk_setRegDom($redis);
        if ($return) {
            // returned value is true, its a valid country code, save it
            $redis->set('regdom', $return);
        }
        $retval = true;
    } else {
        $retval = false;
    }
    return $retval;
}


function wrk_setRegDom($redis)
// set the Wi-Fi regulatory domain based on the current time zone
// no parameters
{
    // when regdom exists an automatic regulatory domain country code change has taken place
    if ($redis->exists('regdom')) {
        // automatic or manual regulatory domain country code change has taken place
        $timeZone = $redis->get('timezone');
        // determine the country code from the timezone
        $tz = new DateTimeZone($timeZone);
        $countryCode = timezone_location_get($tz)['country_code'];
        // set the Wi-Fi regulatory domain, the standard is 00 and is compatible with most countries
        // setting it will could allow more Wi-Fi power to be used (never less) and sometimes improve the usable frequency ranges
        // not all country codes have a specificity specified regulatory domain profile, so if it fails, set to the default (00)
        sysCmd('iw reg set '.$countryCode.' || iw reg set 00');
        $retval = trim(sysCmd('iw reg get | grep -i country')[0]);
        $newCountryCode = get_between_data($retval, 'country ', ':');
        if (!is_numeric($newCountryCode)) {
            // regulatory domain country code change has taken place with a two letter country code, save it
            $redis->set('regdom', $newCountryCode);
        }
    } else {
        sysCmd('iw reg set 00');
        $newCountryCode = '00';
    }
    if ($newCountryCode == '00') {
        return false;
    }
    return $newCountryCode;
}

function ui_update($redis, $sock=null, $clientUUID=null)
{
    ui_libraryHome($redis, $clientUUID);
    switch ($redis->get('activePlayer')) {
        case 'MPD':
            if ($sock) {
                // to get MPD out of its idle-loop we discribe to a channel
                sendMpdCommand($sock, 'subscribe renderui');
                sendMpdCommand($sock, 'unsubscribe renderui');
            }
            return sysCmd('mpc status && mpc move 1 1 || mpc clear')[0];
            break;
            // if ($redis->get('pl_length') !== '0') {
                // sendMpdCommand($sock, 'swap 0 0');
            // } else {
                // sendMpdCommand($sock, 'clear');
            // }
            // // return MPD response
            // return readMpdResponse($sock);
            // break;
        default:
            // for streaming - airplay, spotify connect & bluetooth
            $status = json_decode($redis->get('act_player_info'), true);
            if (($status['time'] != 0) && isset($status['time_last_elapsed']) && $status['time_last_elapsed'] && ($status['state'] == 'play')) {
                if (isset($status['elapsed'])) {
                    $status['elapsed'] = round($status['elapsed'] + microtime(true) - $status['time_last_elapsed']);
                } else {
                    $status['elapsed'] = round(microtime(true) - $status['time_last_elapsed']);
                }
                $status['song_percent'] = min(100, round(100*$status['elapsed']/$status['time']));
            } else {
                unset($status['song_percent'], $status['elapsed']);
            }
            ui_render('playback', json_encode($status));
            break;
    }
}

// function ui_mpd_response($mpd, $notify = null)
// {
    // runelog('ui_mpd_response invoked');
    // $response = json_encode(readMpdResponse($mpd));
    // // --- TODO: check this condition
    // if (strpos($response, "OK") && isset($notify)) {
        // runelog('send UI notify: ', $notify);
        // ui_notify($redis, $notify['title'].'', $notify['text']);
    // }
    // echo $response;
// }

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
    if (is_array($scandir)) {
        $count = count(array_diff($scandir, array('..', '.')));
    } else {
        $count = 0;
    }
    return $count;
}

function netmask($bitcount)
{
    $netmask = str_split(str_pad(str_pad('', $bitcount, '1'), 32, '0'), 8);
    foreach ($netmask as &$element) $element = bindec($element);
    return join('.', $netmask);
}

// sort multi-dimensional array by key
function osort(&$array, $key, $descending=false, $ignoreCase=false)
// $array is passed by reference, nothing needs to be returned
// two parameters:
//  $descending: default false, when true sorts descending
//  $ignoreCase: default false, when true sorts ignoring case
{
    if (!is_array($array)) {
        // not an array
        runelog('osort error:', 'no array passed');
        return false;
    }
    if (!isset($key)) {
        // no key specified
        runelog('osort error:', 'no key specified');
        return false;
    }

    if ($descending) {
        // descending  sort
        usort($array, function($b, $a) use ($key) {
            if ($ignoreCase) {
                return strtolower($a[$key]) <=> strtolower($b[$key]);
            } else {
                return $a[$key] <=> $b[$key];
            }
        });
    } else {
        // ascending sort
        usort($array, function($a, $b) use ($key) {
            if ($ignoreCase) {
                return strtolower($a[$key]) <=> strtolower($b[$key]);
            } else {
                return $a[$key] <=> $b[$key];
            }
        });
    }
    return true;
}

/**
 * Normalize non-ASCII characters to ASCII counterparts where possible.
 *
 * @param string $str
 * @return string
 *
 * Based on: https://gist.github.com/pjdietz/7342772
 * Credit: PJ Dietz https://gist.github.com/pjdietz/
 */
function squashCharacters($str)
{
    static $normalizeChars = null;
    if ($normalizeChars === null) {
        $normalizeChars = array(
            'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A',
            'Æ'=>'Ae',
            'Þ'=>'B',
            'Ç'=>'C',
            'Ð'=>'Dj',
            'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E',
            'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I',
            'Ñ'=>'N',
            'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O',
            'Œ'=>'Oe',
            'Š'=>'S',
            'ß'=>'Ss',
            'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U',
            'Ý'=>'Y', 'Ÿ'=>'Y',
            'Ž'=>'Z',
            'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a',
            'æ'=>'ae',
            'þ'=>'b',
            'ç'=>'c',
            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e',
            'ƒ'=>'f',
            'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i',
            'ñ'=>'n',
            'ð'=>'o', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o',
            'œ'=>'oe',
            'š'=>'s', 'ś' => 's',
            'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'µ'=>'u',
            'ý'=>'y', 'ÿ'=>'y',
            'ž'=>'z',
        );
        $normalizeChars[chr(96)]  = "'"; // Grave accent >> single quote
        $normalizeChars[chr(130)] = "'"; // Single low-9 quotation mark >> single quote
        $normalizeChars[chr(132)] = '"'; // Double low-9 quotation mark >> double quote
        $normalizeChars[chr(145)] = "'"; // Left single quotation mark >> single quote
        $normalizeChars[chr(146)] = "'"; // Right single quotation mark >> single quote
        $normalizeChars[chr(147)] = '"'; // Left double quotation mark >> double quote
        $normalizeChars[chr(148)] = '"'; // Right double quotation mark >> double quote
        $normalizeChars[chr(152)] = '~'; // Small tilde >> Normal tilde
        $normalizeChars[chr(160)] = ' '; // Non-breaking space >> space
        $normalizeChars[chr(161)] = '';  // Inverted exclamation mark >> nothing
        $normalizeChars[chr(173)] = '';  // Soft hyphen >> nothing
        $normalizeChars[chr(180)] = "'"; // Acute accent - spacing acute >> single quote
        $normalizeChars[chr(183)] = ','; // Middle dot - Georgian comma >> comma
        $normalizeChars[chr(184)] = ','; // Spacing cedilla >> comma
        $normalizeChars[chr(191)] = '';  // Inverted question mark >> nothing
    }
    // remove any control characters (hex 00 to 1F inclusive), delete character (hex 7F) and 'not assigned' characters (hex 81, 8D, 8F, 90 and 9D)
    $str = preg_replace("/[\x{00}-\x{1F}\x{7F}\x{81}\x{8D}\x{8F}\x{90}\x{9D}]+/", '', $str);
    // translate the characters based on the array
    return strtr($str, $normalizeChars);
}

// clean up url strings
function urlClean($string)
//
{
    $string = squashCharacters($string);
    $string = urlencode($string);
    return $string;
}

// clean up webradio strings
function webradioStringClean($string)
//
{
    // do some basic cleanup
    $string = urldecode($string);
    $string = html_entity_decode($string);
    $string = htmlentities($string, ENT_XML1, 'UTF-8');
    // trim after replacing all combinations of single or multiple tab, space, <cr> and <lf> and slash with a single space
    $string = trim(preg_replace('/[\t\n\r\s]+/', ' ', $string));
    // convert escaped characters to their actual value
    $string = stripcslashes($string);
    // replace characters with accents, etc. with normal characters
    $string = squashCharacters($string);
    // replace characters outside of the hex range 00 to FF with a space
    $string = preg_replace("/[^\x{00}-\x{FF}]+/", ' ', $string);
    // remove leading and trailing spaces, tabs, linefeeds, etc. after reducing all whitespace to a single space
    $string = trim(preg_replace('!\s+!', ' ', $string));
    return $string;
}

// remove consistent prefixes in the radio string
function webradioStringRemovePrefix($redis, $string)
// it works by stripping a prefix on and after the third time it is identical
// when a different prefix is encountered it is reset
{
    $radionamePrefix = $redis->get('radioname_prefix');
    $lastRadioString = $redis->get('last_radio_string');
    if ($lastRadioString == $string) {
        // identical radio strings are returned unchanged
        $retval = $string;
        $redis->set('radioname_prefix', '');
    } else if ($radionamePrefix) {
        // a prefix was determined
        $radionamePrefixLen = strlen($radionamePrefix);
        if ($radionamePrefix == substr($string, 0, $radionamePrefixLen)) {
            // the stored prefix is still valid
            $retval = substr($string, $radionamePrefixLen);
        } else {
            // the stored prefix is no longer valid
            $redis->set('radioname_prefix', '');
            $retval = $string;
        }
    } else {
        // no prefix has been determined
        for ($i = 1; $i <= max(strlen($string),strlen($lastRadioString)); $i++) {
            if (substr($lastRadioString, 0, $i) != substr($string, 0, $i)) {
                break;
            }
        }
        if ($i == 1) {
            $newPrefix = '';
        } else {
            $i--;
            $newPrefix = substr($lastRadioString, 0, $i);
        }
        if ($radionamePrefix != $newPrefix) {
            $redis->set('radioname_prefix', $newPrefix);
        }
        $retval = $string;
    }
    $redis->set('last_radio_string', $string);
    return $retval;
}

// clean up strings for lyrics and artistinfo
function metadataStringClean($string, $type='')
{
    // convert escaped characters (backslash followed by something) to their actual value
    $string = stripcslashes($string);
    // trim after replacing all combinations of single or multiple tab, space, <cr> and <lf> and slash with a single space
    $string = trim(preg_replace('/[\t\n\r\s]+/', ' ', $string));
    if ($type != 'radiostring') {
        // trim space, open or closed angle, square, round, squiggly brackets, colon, semicolon, comma, backslash, slash,
        //  single and double quotes (in any combinations) in first and last positions
        $string = trim($string, ' <[({})]>:;,\\\/"\'');
        // truncate the string up to a space followed by an open angle, square, round or squiggly bracket
        //  or a closed angle, square, round or squiggly bracket
        // this should remove things like '[live]', '(disk 1)', etc. from the string
        $string = preg_split('!( \()|( \{)|( \<)|( \[)|\]|\>|\}|\)!', $string)[0];
        if ($type == 'artist') {
            // truncate the string up to a space colon, colon space, space semicolon, semicolon space, slash or the
            //  text strings ' Feat. ', ' feat. ', ' Ft. ' or ' ft. ' (=featuring)
            //  text strings ' Feat ', ' feat ', ' Ft ' or ' ft ' (=featuring)
            //  text strings ' Vs ', ' vs ', ' Vs. ', ' vs. ' (=versus)
            //  this should remove a second artist or a featured artist from the string
            $string = preg_split('!( \:)|(\: )|( \;)|(\; )|(\/)|( Feat. )|( feat. )|( Ft. )|( ft. )|( Feat )|( feat )|( Ft )|( ft )|( Vs. )|( vs. )|( Vs )|( vs )!', $string)[0];
        }
    }
    // replace characters with accents, etc. with normal characters
    $string = squashCharacters($string);
    // replace ASCII hex characters 0 to 2F (control characters plus various), 3A to 40 (various),
    //  5B to 60 (various) and 7B to FF (various) with a space
    //  this is a valid string for a file name and for html use
    $string = preg_replace("/[\x{00}-\x{2F}\x{3A}-\x{40}\x{5B}-\x{60}\x{7B}-\x{FF}]+/", ' ', $string);
    // replace any ASCII hex characters not in the range 0 to FF (so anything higher) with a space
    //  these are graphical characters, boxes, borders, arrows, etc.
    $string = preg_replace("/[^\x{00}-\x{FF}]+/", ' ', $string);
    // replace whitespace with a single space, trim leading and trailing spaces
    $string = trim(preg_replace('!\s+!', ' ', $string));
    // remove any remaining backslashes
    $string = stripslashes($string);
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
            // array for pysical device id to nic name translation, there can be more nics per physical device
            // $wirelessNic[$nic] = $phyDev;
            $wirelessNic[$phyDev]['nics'][] = $nic;
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
            if ($networkInterfaces[$nic]['speed'] === 'Unknown') {
                $speed = sysCmd('iw dev '.$nic." station dump | grep -i 'rx bitrate' | sed 's,[ ]\+, ,g'");
                if ((isset($speed[0])) && (strpos(' '.$speed[0], ':'))) {
                    $speed = trim(explode(':', preg_replace('!\s+!', ' ', $speed[0]),2)[1]);
                    if ($speed) {
                        $networkInterfaces[$nic]['speed'] = $speed;
                    }
                }
            }
            // set Wi-Fi nic power management off and save its state
            sysCmd('iw dev '.$nic.' set power_save off');
            $retval = sysCmd('iw dev '.$nic.' get power_save | cut -d ":" -f 2 | xargs');
            if (isset($retval[0])) {
                $networkInterfaces[$nic]['power_management'] = $retval[0];
            }
        } else if (strpos($deviceInfoLine, 'ssid ')) {
            $networkInterfaces[$nic]['ssid'] = trim(explode(' ', trim($deviceInfoLine))[1]);
        } else if (strpos($deviceInfoLine, 'type ')) {
            $networkInterfaces[$nic]['type'] = trim(explode(' ', trim($deviceInfoLine))[1]);
        }
        unset($retval);
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
        } else if (strpos($deviceInfoLine, 'Supported interface modes:')) {
            // the 'Supported interface modes:' section of the file is terminated with a line containing a colon (:)
            $intMode = true;
        } else if (strpos($deviceInfoLine, '* AP')) {
            if ($intMode) {
                // access point (AP) is listed as a 'Supported interface mode'
                if (isset($wirelessNic[$phyDev]['nics']) && is_array($wirelessNic[$phyDev]['nics'])) {
                    $wirelessNic[$phyDev]['apSupported'] = true;
                    foreach ($wirelessNic[$phyDev]['nics'] as $nic) {
                        $networkInterfaces[$nic]['apSupported'] = true;
                    }
                }
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
    // delete networks for invalid nics from the network info array
    // also subtract 3 from all network strength values and remove values which go negative
    // all networks which are (re)detected will reset their strength to the actual value
    // the networks which are successively not detected will be shown as weak and eventually be deleted
    foreach ($networkInfo as $key => $network) {
        if (!isset($networkInterfaces[$network['nic']])) {
            unset($networkInfo[$key]);
            continue;
        }
        if (isset($networkInfo[$key]['strength'])) {
            $networkInfo[$key]['strength'] = $networkInfo[$key]['strength'] - 3;
            if ($networkInfo[$key]['strength'] <= 0) {
                unset($networkInfo[$key]);
            } else {
                $networkInfo[$key]['strengthStars'] = str_repeat(' &#9733', max(1, round($networkInfo[$key]['strength']/10)));
            }
        }
    }
    //
    // always clear the optimise wifi array
    $optimiseWifi = array();
    $accessPoint = $redis->hGet('AccessPoint', 'ssid');
    $accessPointEnabled = $redis->hGet('AccessPoint', 'enable');
    $hiddenCount = 0;
    $networkInterfacesModified = false;
    $avahiNic = '';
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
        // clean up any invalid connman config files
        if (in_array(implode(':', str_split($macAddress, 2)), $networkSpoofArray)) {
            // remove nic with tho old MAC address from the connman cache and restart connman
            $connmanConfDir = '/var/lib/connman/*'.$macAddress.'*';
            sysCmd('rm -fr '.$connmanConfDir.' ; systemctl restart connman');
            continue;
        }
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
            $connmanLineParts = explode('=', $connmanLine, 2);
            if (count($connmanLineParts) !=2) {
                // skip the line if it has no value (or '=' character)
                continue;
            }
            $entry = ' '.strtolower(trim($connmanLineParts[0]));
            $value = strtolower(trim($connmanLineParts[1], " \t\n\r\0\x0B]["));
            if (strpos(' '.$entry, '.configuration') && !strpos(' '.$entry, 'ipv6.')) {
                // don't use the .configuration lines unless its ipv6.configuration
                continue;
            }
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
                // pick up the IP assignment (Static or DHCP)
                if ($value && strpos(' '.$value, 'method')) {
                    $method = substr($value, strpos($value, 'method'));
                    $method = explode('=', $method, 2);
                    if (isset($method[1])) {
                        $method[1] = trim($method[1]);
                        if ($method[1]) {
                            $method = $method[1];
                            if (strpos(' '.$method, ',')) {
                                $method = substr($method, 0, strpos($method, ','));
                            }
                            if (strpos(' '.$method, '=')) {
                                $method = substr($method, 0, strpos($method, '='));
                            }
                            if ($method && ($method == 'dhcp')) {
                                $networkInfo[$macAddress.'_'.$ssidHex]['ipAssignment'] = 'DHCP';
                                if (!isset($networkInterfaces[$nic]['ipAssignment']) || ($networkInterfaces[$nic]['ipAssignment'] != 'DHCP')) {
                                    $networkInterfaces[$nic]['ipAssignment'] = 'DHCP';
                                    $networkInterfacesModified = true;
                                }
                            } else if ($method && ($method == 'fixed')) {
                                $networkInfo[$macAddress.'_'.$ssidHex]['ipAssignment'] = 'Static';
                                if (!isset($networkInterfaces[$nic]['ipAssignment']) || ($networkInterfaces[$nic]['ipAssignment'] != 'Static')) {
                                    $networkInterfaces[$nic]['ipAssignment'] = 'Static';
                                    $networkInterfacesModified = true;
                                }
                            }
                        }
                    }
                }
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
                                if (!isset($networkInterfaces[$nic]['defaultGateway']) || ($networkInterfaces[$nic]['defaultGateway'] != $gateway)) {
                                    $networkInterfaces[$nic]['defaultGateway'] = $gateway;
                                    $networkInterfacesModified = true;
                                }
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
                                if (!isset($networkInterfaces[$nic]['ipv4Mask']) || ($networkInterfaces[$nic]['ipv4Mask'] != $netmask)) {
                                    $networkInterfaces[$nic]['ipv4Mask'] = $netmask;
                                    $networkInterfacesModified = true;
                                }
                            }
                        }
                    }
                } else {
                    $networkInfo[$macAddress.'_'.$ssidHex]['ipv4Mask'] = $networkInterfaces[$nic]['ipv4Mask'];
                }
            } else if (strpos($entry, 'ipv6.configuration')) {
                if ($value) {
                    If (strpos($value, ',')) {
                        $ipv6Configuration = explode(',', $value);
                    } else {
                        $ipv6Configuration[0] = $value;
                    }
                    foreach ($ipv6Configuration as $ipv6Part) {
                        $ipv6Part = trim($ipv6Part);
                        if (strpos(' '.$ipv6Part, '=')) {
                            list($ipv6Item, $ipv6Value) = explode('=', $ipv6Part, 2);
                            $networkInfo[$macAddress.'_'.$ssidHex]['ipv6.'.trim($ipv6Item)] = trim($ipv6Value);
                        }
                    }
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
            if (!isset($strength) || !$strength) {
                // strength is not set, probably a restore from backup, set strength to a default value of 3
                $strength = 3;
            }
            $optimiseWifi[] = array('connmanString' => $connmanString
                , 'strength' => $strength
                , 'macAddress' => $macAddress
                , 'ssidHex' => $ssidHex
                );
        }
        // select the nic to use for avahi
        if (($networkInfo[$macAddress.'_'.$ssidHex]['technology'] === 'ethernet') &&
                ($networkInfo[$macAddress.'_'.$ssidHex]['configured']) &&
                ($networkInfo[$macAddress.'_'.$ssidHex]['autoconnect']) &&
                ($networkInfo[$macAddress.'_'.$ssidHex]['online']) &&
                ($networkInfo[$macAddress.'_'.$ssidHex]['ipStatus'] === 'UP')) {
            // use this nic for avahi
            // $avahiNic will contain the last detected wired (ethernet) interface which is:
            //  configured, autoconnect (on), online and has an 'UP' ipStatus
            //  the value is left unset when only Wi-Fi networks are detected
            $avahiNic = $networkInfo[$macAddress.'_'.$ssidHex]['nic'];
        }
    }
    //
    // set the selected nic for avahi
    // allowing more than one interface to be active will cause problems with Microsoft Windows clients
    // the last detected wired (ethernet) interface will be used
    // when only Wi-Fi networks are detected no interfaces are specifically selected
    //
    if ($redis->hGet('avahi', 'nic') != $avahiNic) {
        // the nic assigned to avahi need to be changed
        if ($avahiNic === '') {
            $avahiLine = '#allow-interfaces=eth0';
        } else {
            $avahiLine = 'allow-interfaces='.$avahiNic;
        }
        sysCmd("sed -i '/allow-interfaces=/c\\".$avahiLine."' /etc/avahi/avahi-daemon.conf");
        // avahi needs to be reloaded to activate the new entry in the config file
        sysCmd('systemctl daemon-reload; systemctl reload avahi-daemon');
        $redis->hSet('avahi', 'nic', $avahiNic);
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
    // get the array containing any mac addresses which need to be spoofed
    if ($redis->Exists('network_mac_spoof')) {
        $networkSpoofArray = json_decode($redis->Get('network_mac_spoof'), true);
    } else {
        $networkSpoofArray = array();
    }
    if (!in_array($macCurrent , $networkSpoofArray)) {
        // MAC address does not need to be changed
        return $macCurrent;
    }
    // the MAC address needs to be changed
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
    // change the MAC address, the nic needs to be brought down and up to activate the change
    sysCmd('ip link set dev '.$nic.' down ; ip link set dev '.$nic.' address '.$macNew.' ; ip link set dev '.$nic.' up');
    // remove nic with tho old MAC address from the connman cache and restart connman
    $connmanConfDir = '/var/lib/connman/*'.str_replace(':','',$macCurrent).'*';
    sysCmd('rm -fr '.$connmanConfDir.' ; systemctl restart connman');
    // construct a systemd unit file to automatically change the MAC address on boot
    $file = '/etc/systemd/system/macfix_'.$nic.'.service';
    // clear the cache otherwise file_exists() returns incorrect values
    clearstatcache(true, $file);
    if ((!file_exists($file)) || (!sysCmd('grep -ihc '.$macNew.' '.$file)[0])) {
        // create the systemd unit file only when it needs to be created
        $fileContent = '# file '.$file."\n"
            .'# some cheap network cards have an identical MAC address for all cards (e.g. 00:e0:4c:53:44:58)'."\n"
            .'# change it to a fixed (previouly ranomised) address'."\n"
            .'# this service is for nic:'.$nic.', spoofing to the Mac Address:'.$macNew."\n"
            .'# once activated the spoofed address will remain for the nic, regardless of the need'."\n\n"
            .'[Unit]'."\n"
            .'Description=MAC Address Fix for '.$nic.' when it has the MAC address: '.$macCurrent."\n"
            // .'Wants=network-pre.target'."\n"
            // .'Before=network-pre.target'."\n"
            // .'BindsTo=sys-subsystem-net-devices-'.$nic.'.device'."\n"
            // .'After=sys-subsystem-net-devices-'.$nic.'.device'."\n\n"
            .'PartOf=sys-subsystem-net-devices-'.$nic.'.device'."\n\n"
            .'[Service]'."\n"
            // .'Type=oneshot'."\n"
            ."ExecStart=/bin/bash -c '/usr/bin/ip link set dev ".$nic.' address '.$macNew.' ; /usr/bin/ip link set dev '.$nic.' down ; /usr/bin/ip link set dev '.$nic.' up'."'\n"
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
// ExecStart=/usr/bin/ashuffle -q <queue_length> -f <playlist_filename> --host '<mpd socket>' --port '<MPD port>' -t window-size=<number> -t suspend-timeout=<timeout>
// or
// ExecStart=/usr/bin/ashuffle -q <queue_length>  -e <excluded_selection> --by-album --host '<mpd socket>' --port '<MPD port>' -t window-size=<number> -t suspend-timeout=<timeout>
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
        $queuedSongs = $redis->hGet('mpdconf', 'crossfade');
    }
    // the number of extra queued songs should be min 1, max 10, defined by $minquelen
    $minquelen = $redis->hGet('globalrandom', 'minquelen');
    // the biggest value of $minquelen and $queuedSongs (default 0 when both unset) is used as $quelen
    $quelen = max($minquelen, $queuedSongs, 0);
    if ($minquelen != $quelen) {
        // this happens when an attempt is made to set the queued songs to zero when crossfade is non-zero
        $redis->hSet('globalrandom', 'minquelen', $quelen);
    }
    unset($retval);
    // set up ashuffle tweaks to randomise the ashuffle window size (default = 7) to enhance the randomness
    //  its set to a value larger than the minimum queue length so that the ashuffle intermediate random list is never emptied
    //  also set the suspend timeout to its redis value (default 20ms) to prevent crashes after clearing the queue, a value
    //  of 1 second allows UPnP/DLNA work correctly in conjunction with ashuffle
    $randomWindow = rand(30+$quelen, 40+$quelen);
    $songs = sysCmd('mpc stats | grep -i songs | xargs | cut -d ":" -f 2 | xargs')[0];
    if (isset($songs) && is_numeric($songs) && ($songs < $randomWindow)) {
        // random window should not be bigger than the number of songs
        if ($songs > 8) {
            $randomWindow = $songs - 1;
        } else {
            $randomWindow = 7;
        }
    }
    $tweaks = ' -t window-size='.$randomWindow.' -t suspend-timeout='.$redis->hGet('globalrandom', 'suspend_timeout');
    $hostAndPort = ' --host '."'".$redis->hGet('mpdconf', 'bind_to_address')."'".' --port '."'".$redis->hGet('mpdconf', 'port')."'";
    switch ($action) {
        case 'checkcrossfade':
            // $action = 'checkcrossfade'
            //
            // don't do anything if $queuedSongs has no value, MPD is probably not running, wait until the next time
            if (strlen($queuedSongs)) {
                // when the required queue length is not found in the unit file correct it
                if (!sysCmd("grep -ihc -- '-q[ ]*".$quelen."' '".$ashuffleUnitFilename."'")[0]) {
                    // incorrect value in the ashuffle service file
                    // find the line beginning with 'ExecStart' and in that line replace '-q x' with -q y'
                    sysCmd("sed -i '/^ExecStart/s/-q[ ]*.[^ ]*/-q ".$quelen."/' '".$ashuffleUnitFilename."'");
                    // reload the service file
                    sysCmd('systemctl daemon-reload');
                    // stop ashuffle if it is running
                    sysCmd('pgrep -x ashuffle && systemctl stop ashuffle');
                }
            }
            break;
        case 'changewindow':
            // $action = changewindow
            sysCmd("sed -i '/^ExecStart/s/-t window-size=.*.-t suspend/-t window-size=".$randomWindow." -t suspend/' '".$ashuffleUnitFilename."'");
            // reload the service file
            sysCmd('systemctl daemon-reload');
            // stop ashuffle if it is running
            sysCmd('pgrep -x ashuffle && systemctl stop ashuffle');
            break;
        case 'set':
            // $action = 'set'
            //
            if (is_null($playlistName)) {
                // no playlist name has been supplied, just exit
                break;
            }
            // stop ashuffle and set redis globalrandom to false/off, otherwise it may be restarted automatically
            $redis->hSet('globalrandom', 'enable', '0');
            sysCmd('pgrep -x ashuffle && systemctl stop ashuffle');
            // delete all broken symbolic links in the playlist directory
            sysCmd('find '."'".$playlistDirectory."'".' -xtype l -delete');
            $playlistFilename = $playlistDirectory.'/'.$playlistName.'.m3u';
            // reset the random window
            $randomWindow = rand(30+$quelen, 40+$quelen);
            $songs = sysCmd('wc -l '.$playlistFilename.' | xargs | cut -d " " -f1 | xargs')[0];
            if (isset($songs) && is_numeric($songs) && ($songs < $randomWindow)) {
                // random window should not be bigger than the songs in the playlist
                if ($songs > 8) {
                    $randomWindow = $songs - 1;
                } else {
                    $randomWindow = 7;
                }
            }
            $tweaks = ' -t window-size='.$randomWindow.' -t suspend-timeout='.$redis->hGet('globalrandom', 'suspend_timeout');
            // save the playlist and playlist filename
            $redis->hSet('globalrandom', 'playlist', $playlistName);
            $redis->hSet('globalrandom', 'playlist_filename', $playlistFilename);
            // the ashuffle systemd service file needs to explicitly reference the playlist file
            $newArray = wrk_replaceTextLine($ashuffleUnitFilename, '', 'ExecStart=', 'ExecStart=/usr/bin/ashuffle -q '.$quelen.' -f '."'".$playlistFilename."'".$hostAndPort.$tweaks);
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
            $newArray = wrk_replaceTextLine($ashuffleUnitFilename, '', 'ExecStart=', 'ExecStart=/usr/bin/ashuffle -q '.$quelen.$hostAndPort.$tweaks.$ashuffleAlbum.$randomExclude);
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
            $moveNr = intval($queuedSongs) + 1;
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
                // get the active player and active player info
                $activePlayer = $redis->get('activePlayer');
                $actPlayerInfo = json_decode($redis->get('act_player_info'), true);
                // check if MPD is not playing, playing a single song, repeating a song or randomly playing the current playlist
                if ($activePlayer != 'MPD') {
                    // active player not MPD, ashuffle should not be running
                    $mpdSingleRepeatRandomStopped = true;
                } else if (isset($actPlayerInfo['file']) && (substr($actPlayerInfo['file'], 0, 7) == 'cdda://')) {
                    // a CD is playing, ashuffle should not not be running
                    $mpdSingleRepeatRandomStopped = true;
                } else if (isset($actPlayerInfo['file']) && (substr($actPlayerInfo['file'], 0, 7) == 'alsa://')) {
                    // a HW input is playing, ashuffle should not not be running
                    $mpdSingleRepeatRandomStopped = true;
                } else {
                    $mpcStatus = ' '.trim(preg_replace('!\s+!', ' ', strtolower(sysCmd('mpc status | xargs')[0])));
                    if (!strpos($mpcStatus, 'playing')) {
                        // not playing
                        $retval = sysCmd('mpc move '.$moveNr.' '.$moveNr.' || echo 1');
                        if (!isset($retval) || !is_array($retval) || !isset($retval[0]) || !$retval[0]) {
                            $queueEmpty = 0;
                        } else {
                            $queueEmpty = 1;
                        }
                        // note: 'mpc move 1 1 || echo 1' (or 'mpc move 2 2 || echo 1') will do nothing and will also return
                        // nothing when the first (or second) position in the queue contains a song, so:
                        //  returning nothing is false >> songs in the queue
                        //  otherwise true >> queue empty
                        //  note: we are not checking that the queue is empty, but that the last song has played
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
                    if ((($nasmounts == 0) && ($usbmounts == 0) && ($localstoragefiles == 0)) || ($activePlayer != 'MPD') || $mpdSingleRepeatRandomStopped) {
                        // nothing to play or active player is not MPD or MPS stopped, MPD single, repeat or random is set, so stop ashuffle
                        sysCmd('pgrep -x ashuffle && systemctl stop ashuffle');
                    }
                } else {
                    // ashuffle not started
                    if ((($nasmounts == 0) && ($usbmounts == 0) && ($localstoragefiles == 0)) || ($activePlayer != 'MPD') || $mpdSingleRepeatRandomStopped) {
                        // nothing to play or active player is not MPD or MPS stopped, MPD single, repeat or random is set, do nothing
                    } else if (!sysCmd('mpc outputs | grep -ic enabled')[0]) {
                        //  MPD is not running or has no valid output, do nothing
                    } else if (!sysCmd('mpc stats | grep -i songs | xargs | cut -d " " -f 2 | xargs')[0]) {
                        //  MPD is not running or there are no songs in its database, do nothing
                    } else {
                        // start ashuffle
                        // seems to be a bug somewhere in MPD/ashuffle
                        // if ashuffle is started too quickly it queues many, many (far TOO many!) songs in the queue before MPD gets round to start playing one
                        // wait until mpd has been running for a while before starting ashuffle
                        // get the elapsed time that MPD has been running in seconds
                        $mpd_uptime = sysCmd('ps -C mpd -o etimes= | xargs')[0];
                        if (!isset($mpd_uptime) || !$mpd_uptime) {
                            // no value, MPD is probably not running
                            $mpd_uptime = 0;
                        } else {
                            // a value has been returned
                            $mpd_uptime = intval($mpd_uptime);
                        }
                        if ($mpd_uptime > intval($redis->hGet('globalrandom', 'start_delay'))) {
                            // remove any invalid symlinks in the playlist directory
                            sysCmd('find '."'".$playlistDirectory."'".' -xtype l -delete');
                            // check that the queued songs based on crossfade is set correctly
                            wrk_ashuffle($redis, 'checkcrossfade');
                            sysCmd('systemctl daemon-reload');
                            sysCmd('pgrep -x ashuffle || systemctl start ashuffle');
                            sysCmdAsync($redis, 'nice --adjustment=10 /srv/http/command/rune_prio nice');
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

// function to unset the check of a subject which is the first one since reboot
function unset_is_firstTime($redis, $subject)
// returns true or false
// true on success
// false on failure
{
    // this function removes '/tmp/<subject>.firsttime'
    // the /tmp directory is a tmpfs memory disk which is recreated at each reboot/boot
    $fileName = '/tmp/'.trim($subject).'.firsttime';
    // clear the static cache otherwise the file_exists() returns an incorrect value
    clearstatcache(true, $fileName);
    if (file_exists($fileName)) {
        // the file exists so not the first time
        // remove the file
        unlink($fileName);
        // check that the unlink is successful
        // clear the static cache otherwise the file_exists() returns an incorrect value
        clearstatcache(true, $fileName);
        if (file_exists($fileName)) {
            // the file still exists, failure
            $returnVal = false;
        } else {
            // the file does not exist, success
            $returnVal = true;
        }
    } else {
        // the  file does not exist, do nothing, return true
        $returnVal = true;
    }
    return $returnVal;
}

// function to check and correct the number of active MPD outputs
function wrk_check_MPD_outputs($redis)
// check that MPD only has one output enabled
// it is possible that stream output has been defined which is always active, so be careful
// exclude the stream output when counting the enabled output's, there should then only be one enabled output
{
    // get the number of enabled outputs, exclude any with a name ending with '_stream' or the name 'null'
    $countMpdEnabled = sysCmd('mpc outputs | grep -vi "_stream)" | grep -vi "(null)" | grep -ci "enabled"')[0];
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
            foreach ($outputs as $output) {
                $outputParts = explode(' ', $output, 3);
                // $outputParts[0] = 'Output' (can be disregarded), $outputParts[1] = <the output number> & $outputParts[2] = <the rest of the information>
                $aoName = get_between_data($outputParts[2], '(', ')');
                $outputParts[2] = strtolower($outputParts[2]);
                if (strpos(' '.$outputParts[2], 'bcm2835') || strpos($outputParts[2], 'hdmi')) {
                    // its a 3,5mm jack or hdmi output, so disable it, don't count it
                    sysCmd('mpc disable '.$outputParts[1]);
                    // save the number of the last one
                    $lastOutput = $outputParts[1];
                } else if (strpos(' '.$outputParts[2], '_stream)')) {
                    // its a streamed output, so enable it, don't count it
                    sysCmd('mpc enable '.$outputParts[1]);
                } else if (strpos(' '.$outputParts[2], '(null)')) {
                    // its the null output, don't change it, don't count it
                } else if (!$redis->exists('acards', $aoName)) {
                    // its not listed in acards, so it is inactive, probably a bluetooth output
                    //  disable it, don't count it
                    sysCmd('mpc disable '.$outputParts[1]);
                } else {
                    // its an audio card, USB DAC, active Bluetooth connection, fifo or pipe output
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
            // the first audio card, USB DAC, active Bluetooth connection, fifo or pipe output should now have been enabled
            // if applicable the streaming output is also enabled
            // the rest are disabled
            if ($countMpdEnabled == 0) {
                // no output enabled, there are no outputs available, no audio cards, USB DACs, fifo or pipe output detected
                if (isset($lastOutput)) {
                    sysCmd('mpc enable '.$lastOutput);
                }
                $countMpdEnabled = sysCmd('mpc outputs | grep -vi "_stream)" | grep -vi "(null)" | grep -ci "enabled"')[0];
                if ($countMpdEnabled == 0) {
                    $countMpdStreamEnabled = sysCmd('mpc outputs | grep i "_stream)" | grep -ci "enabled"')[0];
                    if ($countMpdStreamEnabled == 0) {
                        sysCmd('mpc enable null');
                    }
                }
            }
            // get the name of the enabled audio output for the UI, also set the default audio output for the UI
            $retval = sysCmd('mpc outputs | grep -vi "_stream)" | grep -vi "(null)" | grep -i enabled');
            if (isset($retval[0]) && trim($retval[0])) {
                // a card is enabled
                $aoName = get_between_data($retval[0], '(', ')');
                if (isset($aoName) && $aoName) {
                    // the card has an audio output name
                    wrk_hwinput($redis, 'refresh');
                    if ($redis->hExists('acards', $aoName)) {
                        // the card is listed in acards, so set it as the active audio output
                        $redis->set('ao', $aoName);
                        // set the default audio output to the same value as the audio output when it is a hw type
                        $acard = json_decode($redis->hGet('acards', $aoName), true);
                        if (isset($acard['device']) && (substr($acard['device'], 0, 3) == 'hw:')) {
                            // its a hardware card, so set it to the audio output default
                            $redis->set('ao_default', $aoName);
                            sysCmd('mpc disable null');
                        } else {
                            $redis->set('ao_default', '');
                        }
                    } else {
                        $redis->set('ao', '');
                        $redis->set('ao_default', '');
                    }
                } else {
                    $redis->set('ao', '');
                    $redis->set('ao_default', '');
                }
            } else {
                $redis->set('ao', '');
                $redis->set('ao_default', '');
            }
        }
    }
    $ao = $redis->get('ao');
    $spotifyconnectAo = $redis->hGet('spotifyconnect', 'ao');
    $airplayAo = $redis->hGet('airplay', 'ao');
    if ($ao && ($ao != $spotifyconnectAo)) {
        wrk_spotifyd($redis, $ao);
    }
    if ($ao && ($ao != $airplayAo)) {
        wrk_shairport($redis, $ao);
    }
    // set this card to the default alsa card
    set_alsa_default_card($redis);
}

// function which caches and cleans up old cached radio metadata, artist_song metadata, artist_album metadata, artist metadata
//  and local cached album art
function wrk_clean_music_metadata($redis, $logfile=null, $clearAll=null)
// when $clearAll is set to a true value all cached information will be cleared
// it should be noted that the synchronisation of a upper directory with a lower directory within a overlay file system should
//  not work correctly or consistently
//  the overlay file system is not aware of the synchronisation action and it remembers what should be in the upper and lower
//      directories regardless of their actual content
//      there is a trick implemented to make it work, see the last lines in this function for details
//  the trick works here because the availability of a cached file is not critical, runeaudio may do some extra work, but it will
//      still work fine, the trick is not advisable for critical content
{
    // initialise variables
    $cleaned = false;
    $artDir = rtrim(trim($redis->get('albumart_image_dir')), '/');
    if (!is_dir($artDir)) {
        return;
    }
    $overlay_art_cache = $redis->get('overlay_art_cache');
    // if required sync the in-memory tmpfs to the overly cache
    if ($overlay_art_cache) {
        // overlay cache is enabled substr($url, 0, strrpos( $url, '/'));
        $cleanUpperDir = substr($artDir, 0, strrpos( $artDir, '/')).'/upper';
        if (!is_dir($cleanUpperDir)) {
            return;
        }
        $cleanLowerDir = '/home/cache/art';
        if (!is_dir($cleanLowerDir)) {
            return;
        }
        // sync the files part 1
        //  the file timestamp in the upper files directory is changed on file use, ignore this change
        //  changing the timestamp on use forces the file to be cached in memory in the tmpfs
        //  the lower directory is ordered on creation date, the upper directory on usage date
        //  the following files are created once and never change so --ignore-existing will sync them
        // rsync --recursive --ignore-existing --include="*.radio" --include="*.album" --include="*.artist" --include="*.song" --exclude="*" /srv/http/tmp/upper/ /home/cache/art/
        $rsyncCommand = 'rsync --recursive --ignore-existing '.
            '--include="*.radio" '.
            '--include="*.album" '.
            '--include="*.artist" '.
            '--include="*.song" '.
            '--include="*.mpd" '.
            '--include="*.jpg" '.
            '--exclude="*" '.
            $cleanUpperDir.'/ '.
            $cleanLowerDir.'/';
        sysCmd($rsyncCommand);
        // sync the files part 2
        //  the file timestamp in the upper files directory is changed on file use, ignore this change
        //  changing the timestamp on use forces the file to be cached in memory in the tmpfs
        //  the lower directory is ordered on creation date, the upper directory on usage date
        //  the following files will be modified occasionally so --size_only and --checksum will sync them
        // rsync --recursive --size-only --checksum --include="*.spotify" --exclude="*" /srv/http/tmp/upper/ /home/cache/art/
        $rsyncCommand = 'rsync --recursive --size-only --checksum '.
            '--include="*.spotify" '.
            '--exclude="*" '.
            $cleanUpperDir.'/ '.
            $cleanLowerDir.'/';
        sysCmd($rsyncCommand);
    } else {
        $cleanUpperDir = $artDir;
        unset($cleanLowerDir);
    }
    // process clear all
    if ($clearAll) {
        $fileToSave = 0;
        if (isset($cleanLowerDir)) {
            sysCmd('rm -r '.$cleanLowerDir.'/*');
            sysCmd('umount '.$artDir);
        }
        sysCmd('rm -r '.$cleanUpperDir.'/*');
        sysCmdAsync($redis, 'nice --adjustment=10 /srv/http/command/create_work_dirs.sh');
        return;
    } else {
        // we will always leave 10 files regardless of the memory which we want to recover unless $clearAll is set to true
        $fileToSave = 10;
    }
    // clean up the album art files in the upper directory
    //
    // files in the $requiredFiles array are never deleted
    $requiredFiles = array('none.png', 'black.png', 'airplay.png', 'spotify-connect.png', 'radio.png', 'bluetooth.png');
    // touch the required files this will ensure that they have a timestamp of now, these are then last in the directory listing for deletion
    foreach ($requiredFiles as $requiredFile) {
        touch($cleanUpperDir.'/'.$requiredFile);
    }
    // always remove files which over 3 months (90 days) old
    // do it once per day!
    $today = date("Y-m-d");
    if ($today != $redis->hGet('cleancache', '90upperdate')) {
        // the following command removes all files from the art directory which are older than 90 days
        sysCmd('find "'.$cleanUpperDir.'" -type f -mtime +90 -exec rm {} \;');
        $redis->hSet('cleancache', '90upperdate', $today);
        $cleaned = true;
    }
    unset($today);
    // check for large files
    // if they exist in the lower directory delete them in the upper directory
    // this should not be happening, but it is:
    //  the remounting of the overlay cache could be creating anomalies or the ImageMagick size reduction is sometimes failing
    if ($overlay_art_cache) {
        $maxsize = intval($redis->hGet('cleancache', 'max_lower_size'));
        if ($maxsize == 0) {
            // when $maxsize is unset, set it to 100 and save it
            $maxsize = 100;
            $redis->hSet('cleancache', 'max_lower_size', $maxsize);
        }
        // we are interested in *.jpg files created more than 15 minutes ago with a size greater than $maxsize
        $files = sysCmd("find '".$cleanUpperDir."' -maxdepth 1 -type f -mmin +15 -size +".$maxsize."k -name '*.jpg'");
        foreach ($files as $file) {
            $fileName = $cleanLowerDir.'/'.basename($file);
            clearstatcache(true, $fileName);
            if (file_exists($fileName)) {
                unlink($file);
                $cleaned = true;
            }
        }
        unset($files, $file, $fileName);
    }
    // initialise the amount of diskspace to recover (kB)
    $recoverKB = 0;
    // if the art is using tmpfs get the physical memory information
    // tmpfs uses the main memory, ensure that enough physical stays available for system buffers and caching
    //  generally we will run out of allocated filespace before needing to free memory
    if ($redis->get('albumart_image_tmpfs')) {
        $memInfoLines = sysCmd("grep -iE 'MemTotal|MemAvailable' /proc/meminfo");
        $mem = array();
        foreach ( $memInfoLines as $memInfo ) {
            $memInfo = trim(preg_replace('!\s+!', ' ', strtolower($memInfo)));
            list($title, $value, $rest) = explode(' ', $memInfo, 3);
            $title = substr($title, 3, -1);
            $mem[$title] = intval($value);
        }
        // if the MemAvalable is less the 20% of the MemTotal clean up the files
        $percFreeMem = ($mem['available'] / $mem['total']) * 100;
        // memory to recover in kB
        if ($percFreeMem < 20) {
        // less than 20% available, recover to make 35% available
            $recoverKB = intval(((35 - $percFreeMem) * $mem['total'])/100);
        }
    }
    // allow the file system to fill to 80% (20% free), it is probably a tmpfs, but could
    //  use a sd-card, usb-drive or network-drive
    // get the total and available memory in the file system
    $totalSpaceKB = disk_total_space($cleanUpperDir)/1024;
    $freeSpaceKB = disk_free_space($cleanUpperDir)/1024;
    $percFreeDisk = ($freeSpaceKB / $totalSpaceKB) * 100;
    // diskspace to recover in kB
    if ($percFreeDisk < 20) {
        // less than 20% available, recover to make 35% available
        $recoverKB = intval(max(((35 - $percFreeDisk) * $totalSpaceKB)/100, $recoverKB));
    }
    if ($recoverKB) {
        $cleaned = true;
        // logging
        if (isset($logfile) && $logfile) {
            $output = "\nRecover Upper ".$cleanUpperDir." ".date(DATE_RFC2822)."\n";
            $output .= implode("\n", sysCmd('df -h'))."\n";
            $output = "totalSpaceKB:     ".$totalSpaceKB."\n";
            $output = "freeSpaceKB:      ".$freeSpaceKB."\n";
            $output = "percFreeDisk:     ".$percFreeDisk."\n";
            $output = "recoverKB:        ".$recoverKB."\n";
            $output .= implode("\n", sysCmd('free'))."\n";
            $output = "mem['total']:     ".$mem['total']."\n";
            $output = "mem['available']: ".$mem['available']."\n";
            $output = "percFreeMem:      ".$percFreeMem."\n";
            $output = "recoverKB:        ".$recoverKB."\n";
            file_put_contents($logfile, $output, FILE_APPEND);
            unset($output);
        }
        // need to recover diskspace
        // first get the number of files in the directory
        $filesInArtDir = sysCmd('ls -q1 "'.$cleanUpperDir.'" | wc -l | xargs')[0];
        // loop for processing files in blocks of 100, this will reduce memory use
        while (($filesInArtDir > $fileToSave) && ($recoverKB > 0)) {
            // get the file names in date order, oldest first, the first line is the total allocated blocks
            //  get in blocks of the 100 oldest files
            $files = sysCmd('ls -w0 -t1 -sGghr --time-style=iso "'.$cleanUpperDir.'" | head -n 100');
            foreach ($files as $file) {
                $file = trim($file);
                if (strpos(' '.$file, 'total') === 1) {
                    // total line
                    $file = preg_replace('!\s+!', ' ', $file);
                    list($null, $totalAllocated) = explode(' ', $file, 2);
                } else {
                    // file lines
                    // the file string contains tabs and double spaces, these must be removed, but not in the file name
                    // the file name is the last part of the string after a timestamp containing a ':'
                    $fileSplit = explode(':', $file, 2);
                    if (!isset($fileSplit[1])) {
                        // this should never happen!
                        continue;
                    }
                    $file = preg_replace('!\s+!', ' ', $fileSplit[0]).':'.$fileSplit[1];
                    list($allocated, $perm, $links, $size, $date, $time, $fileName) = explode(' ', $file, 8);
                    $fileName = trim($fileName);
                    if (!$fileName) {
                        // empty file name, should never happen
                        continue;
                    } else if (in_array($fileName, $requiredFiles)) {
                        // this file must always be present, skip it (should never happen)
                        continue;
                    }
                    $allocatedKB = intval(convertToBytes($allocated)/1024);
                    $recoverKB -= $allocatedKB;
                    unlink($cleanUpperDir.'/'.$fileName);
                    echo 'Deleted : '.$fileName."\n";
                }
                if ((--$filesInArtDir <= $fileToSave) || ($recoverKB <= 0)) {
                    break;
                }
            }
        }
    }
    if (!$overlay_art_cache) {
        // lower directory not in use
        return;
    }
    // clean up the album art files in the lower directory
    //
    // files in the $requiredFiles array are never deleted
    $requiredFiles = array();
    $fileToSave = 0;
    //
    // always remove files which over 3 months (90 days) old
    //  and files without content after 1 month (30 days)
    //  and song files created when the lyrics service was down after 2 days
    //  do it once per day!
    //  spread the load by performing the actions on separate runs of this script
    $today = date("Y-m-d");
    if ($today != $redis->hGet('cleancache', '90lowerdate')) {
        // the following command removes all files from the lower directory which are older than 90 days
        // the strategy is that we have used them for 3 months, but their source information may now have changed
        sysCmd('find "'.$cleanLowerDir.'" -type f -mtime +90 -exec rm {} \;');
        $redis->hSet('cleancache', '90lowerdate', $today);
        $cleaned = true;
    } else if ($today != $redis->hGet('cleancache', '30lowerdate_jpg')) {
        // the following command removes all *.jpg files from the lower directory which are older than 30 days
        // the strategy is that we have used them for 1 month, but their source information may now have changed
        // these files are large
        sysCmd("find '".$cleanLowerDir."' -type f -name '*.jpg' -mtime +30 -exec rm {} \;");
        $redis->hSet('cleancache', '30lowerdate_jpg', $today);
        $cleaned = true;
    } else if ($today != $redis->hGet('cleancache', '30lowerdate_mpd')) {
        // the following command removes all *.jpg files from the lower directory which are older than 30 days
        // the strategy is that we have used them for 1 month, but their source information may now have changed
        // there are many of these files
        sysCmd("find '".$cleanLowerDir."' -type f -name '*.mpd' -mtime +30 -exec rm {} \;");
        $redis->hSet('cleancache', '30lowerdate_mpd', $today);
        $cleaned = true;
    } else if ($today != $redis->hGet('cleancache', '30lowerdate_artist')) {
        // artist files without any content (these can contain the text 'Sorry, no details available') are deleted after 30 days
        // the strategy is that new artists may get modified information within a couple of weeks, in this way they are refreshed quickly
        //  first create a file containing file-names to exclude from the delete action (modified during the last 30 days)
        sysCmd("find '".$cleanLowerDir."' -type f -mtime -30 -name '*.artist' > '/tmp/exclude.filelist'");
        //  then create a list of files to be deleted (this excludes the files modified during the last 30 days)
        $files = sysCmd("grep -il --exclude-from='/tmp/exclude.filelist' 'Sorry, no details available' ".$cleanLowerDir."/*.artist &> /dev/null");
        //  remove the exclude file
        unlink('/tmp/exclude.filelist');
        // delete the files
        foreach ($files as $file) {
            unlink($file);
        }
        $redis->hSet('cleancache', '30lowerdate_artist', $today);
        $cleaned = true;
    } else if ($today != $redis->hGet('cleancache', '30lowerdate_song')) {
        // song files without any content (these can contain the text 'No lyrics available') are deleted after 30 days
        // the strategy is that new songs may get modified information within a couple of weeks, in this way they are refreshed quickly
        //  first create a file containing file-names to exclude from the delete action (modified during the last 30 days)
        sysCmd("find '".$cleanLowerDir."' -type f -mtime -30 -name '*.song' > '/tmp/exclude.filelist'");
        //  then create a list of files to be deleted (this excludes the files modified during the last 30 days)
        $files = sysCmd("grep -il --exclude-from='/tmp/exclude.filelist' 'No lyrics available' ".$cleanLowerDir."/*.song &> /dev/null");
        //  remove the exclude file
        unlink('/tmp/exclude.filelist');
        // delete the files
        foreach ($files as $file) {
            unlink($file);
        }
        $redis->hSet('cleancache', '30lowerdate_song', $today);
        $cleaned = true;
    } else if ($today != $redis->hGet('cleancache', '1lowerdate_song')) {
        // song files without any content (these can contain the text 'Lyrics service unavailable') are deleted after 1 day
        // the strategy is that the lyrics service may take a few days to fix and there is no point retrying until then
        //  first create a file containing file-names to exclude from the delete action (modified during the last 1 day)
        sysCmd("find '".$cleanLowerDir."' -type f -mtime -1 -name '*.song' > '/tmp/exclude.filelist'");
        //  then create a list of files to be deleted (this excludes the files modified during the last 1 day)
        $files = sysCmd("grep -il --exclude-from='/tmp/exclude.filelist' 'Lyrics service unavailable' ".$cleanLowerDir."/*.song &> /dev/null");
        //  remove the exclude file
        unlink('/tmp/exclude.filelist');
        // delete the files
        foreach ($files as $file) {
            unlink($file);
        }
        $redis->hSet('cleancache', '1lowerdate_song', $today);
        $cleaned = true;
    } else if ($today != $redis->hGet('cleancache', '0lowerdate_large_jpg')) {
        // some album art files throw an error when being converted by ImageMagick, these are copied unaltered the cache
        // even though ImageMagick shows a error the files can be converted correctly, here we try to convert large the files again
        $maxsize = intval($redis->hGet('cleancache', 'max_lower_size'));
        if ($maxsize == 0) {
            // when $maxsize is unset, set it to 100 and save it
            $maxsize = 100;
            $redis->hSet('cleancache', 'max_lower_size', $maxsize);
        }
        // we search for *.jpg files with a file size greater than $maxsize
        $files = sysCmd("find '".$cleanLowerDir."' -maxdepth 1 -type f -size +".$maxsize."k -name '*.jpg'");
        // convert the files
        $magick_opts = trim($redis->get('magick_opts'));
        $magickSize = substr($magick_opts, 0, strpos($magick_opts, 'x'));
        $reduceMaxsize = true;
        foreach ($files as $file) {
            // use ImageMagick to resize the file
            sysCmd("convert -resize ".$magick_opts." '".$file."' '".$file."'");
            // check that it is a valid image file, get some information about the file
            clearstatcache(true, $file);
            // check that the file is an image
            list($width, $height, $type, $attr) = getimagesize($file);
            // width and height are in pixels (null when invalid), type is a non zero/null value when valid
            if (!isset($width) || !isset($height) || !isset($type) || !($width > 20) || !($height > 20) || !$type) {
                // it is not a valid image file (or at least it has a invalid header) or it is smaller than 20x20px
                // the image file has an invalid format or is very small, delete it
                unlink($file);
            } else {
                // it is a valid image file
                if (($width == $magickSize) || ($height == $magickSize)) {
                    // the size of the file is the same as requested for the ImageMagick reduction
                    // get the file size in kb
                    $fileSizeK = intval(intval(filesize($file)) / 1024);
                    if ($fileSizeK > $maxsize)  {
                        // the file size is greater than the defined $maxsize, increase $maxsize
                        $maxsize = $fileSizeK;
                        $redis->hSet('cleancache', 'max_lower_size', $maxsize);
                        $reduceMaxsize = false;
                    }
                }
            $cleaned = true;
            }
        }
        if ($reduceMaxsize) {
            // files have been processed and no file had a larger size than $maxsize, reduce $maxsize by 2% (but not lower than 55)
            $maxsize = max(intval(intval($maxsize) * 0.98), 55);
            $redis->hSet('cleancache', 'max_lower_size', $maxsize);
        }
        $redis->hSet('cleancache', '0lowerdate_large_jpg', $today);
    }
    unset($today, $files, $file);
    // initialise the amount of diskspace to recover (kB)
    $recoverKB = 0;
    // allow the file system to fill to 80% (20% free), it is a partition on the sd-card
    // get the total and available memory in the file system
    $totalSpaceKB = disk_total_space($cleanLowerDir)/1024;
    $freeSpaceKB = disk_free_space($cleanLowerDir)/1024;
    $percFreeDisk = ($freeSpaceKB / $totalSpaceKB) * 100;
    // diskspace to recover in kB
    if ($percFreeDisk < 20) {
        // less than 20% available, recover to make 35% available
        $recoverKB = intval(((35 - $percFreeDisk) * $totalSpaceKB)/100);
    }
    if ($recoverKB && !$cleaned) {
        // but dont run this until the other cleaning actions have completed
        // logging
        if (isset($logfile) && $logfile) {
            $output = "\nRecover Lower ".$cleanLowerDir." ".date(DATE_RFC2822)."\n";
            $output .= implode("\n", sysCmd('df -h'))."\n";
            $output = "totalSpaceKB:     ".$totalSpaceKB."\n";
            $output = "freeSpaceKB:      ".$freeSpaceKB."\n";
            $output = "percFreeDisk:     ".$percFreeDisk."\n";
            $output = "recoverKB:        ".$recoverKB."\n";
            file_put_contents($logfile, $output, FILE_APPEND);
            unset($output);
        }
        $cleaned = true;
        // need to recover diskspace
        // first get the number of files in the directory
        $filesInArtDir = sysCmd('ls -q1 "'.$cleanLowerDir.'" | wc -l | xargs')[0];
        // loop for processing files in blocks of 100, this will reduce memory use
        while (($filesInArtDir > $fileToSave) && ($recoverKB > 0)) {
            // get the file names in date order, oldest first, the first line is the total allocated blocks
            //  get in blocks of the 100 oldest files
            $files = sysCmd('ls -w0 -t1 -sGghr --time-style=iso "'.$cleanLowerDir.'" | head -n 100');
            foreach ($files as $file) {
                $file = trim($file);
                if (strpos(' '.$file, 'total') === 1) {
                    // total line
                    $file = preg_replace('!\s+!', ' ', $file);
                    list($null, $totalAllocated) = explode(' ', $file, 2);
                } else {
                    // file lines
                    // the file string contains tabs and double spaces, these must be removed, but not in the file name
                    // the file name is the last part of the string after a timestamp containing a ':'
                    $fileSplit = explode(':', $file, 2);
                    if (!isset($fileSplit[1])) {
                        // this should never happen!
                        continue;
                    }
                    $file = preg_replace('!\s+!', ' ', $fileSplit[0]).':'.$fileSplit[1];
                    list($allocated, $perm, $links, $size, $date, $time, $fileName) = explode(' ', $file, 8);
                    $fileName = trim($fileName);
                    if (!$fileName) {
                        // empty file name, should never happen
                        continue;
                    } else if (in_array($fileName, $requiredFiles)) {
                        // this file must always be present, skip it (should never happen)
                        continue;
                    }
                    $allocatedKB = intval(convertToBytes($allocated)/1024);
                    $recoverKB -= $allocatedKB;
                    unlink($cleanLowerDir.'/'.$fileName);
                    echo 'Deleted : '.$fileName."\n";
                }
                if ((--$filesInArtDir <= $fileToSave) || ($recoverKB <= 0)) {
                    break;
                }
            }
        }
    }
    //
    if ($cleaned || $redis->get('cleaned_last_time')) {
        // this runs when the upper file system has been changed within this function, when files are deleted
        //  from the lower file system within this funcuion and on the first following run when nothing has been changed
        //  there is no need to run this after synchronising the upper file system to the lower
        // this is the trick:
        //  command 1: forces the kernel to the free page cache and reclaimable slab objects (caches, dentries and i-node data)
        //      this causes the overlay file system to forget its previous contents, it will then
        //      rebuild its information based on what is actually there
        //          Note: since we don't use a pagefile its probably enough to use 'echo 2 > /proc/sys/vm/drop_caches'
        //  command 2: remounts the overlay file system
        //      this causes new content of the lower directory to be included in the overlay file system and deleted
        //      content of the lower directory to be omitted
        sysCmd('echo 3 > /proc/sys/vm/drop_caches ; mount -o remount overlay_art_cache');
    }
    if ($cleaned) {
        // do it again on the following run when nothing has been changed
        //  it seems to provide better results
        $redis->set('cleaned_last_time', 1);
    } else {
        $redis->set('cleaned_last_time', 0);
    }
    //
    // test commands
    // s -w0 -t1 -sGghr --time-style=iso /srv/http/tmp/art
    // du /srv/http/tmp/art | xargs
    // ls -q1 /srv/http/tmp/art | wc -l | xargs
}

// function to convert 'human readable' values (3K, 2kB, 20 B, 6.5T, 5 Gb, etc.) to bytes
function convertToBytes(string $from): ?int
{
    $units = ['B', 'K', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y'];
    $from = '0'.trim(preg_replace('!\s+!', '', strtoupper($from)));
    if (substr($from, -1) === 'B') {
        $from = substr($from, 0, -1);
    }
    if (is_numeric(substr($from, -1))) {
        $from = $from.'B';
    }
    $number = substr($from, 0, -1);
    if (!is_numeric($number)) {
        return null;
    }
    $suffix = substr($from, -1);
    if (!in_array($suffix, $units)) {
        return null;
    }
    $exponent = array_search($suffix, $units);
    return $number * (1024 ** $exponent);
}

// function to convert bytes to 'human readable' values (3 B, 2 kB, 20 gB, 6.5 tB, etc.)
function convertToHumanReadable($bytes)
{
    $symbols = array('B', 'kB', 'mB', 'gB', 'tB', 'pB', 'eB', 'zB', 'yB');
    $exp = $bytes ? intval(log($bytes) / log(1024)) : 0;
    return sprintf('%.1f '.$symbols[$exp], ($bytes/pow(1024, $exp)));
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
                if (!isset($retval[0]) || !$retval[0]) {
                    // no response
                    sleep(2);
                    continue;
                }
                if (!isset($retval[0])) {
                    // invalid response
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
                        $retval = sysCmd('mpc volume '.$setvolume.' | grep "volume:" | xargs');
                        if (isset($retval[0])) {
                            $retval = explode(':',trim(preg_replace('!\s+!', ' ', $retval[0])));
                        } else {
                            // invalid response
                            sleep(2);
                            continue;
                        }
                        if (isset($retval[1])) {
                            $mpdvolume = trim(preg_replace('/[^0-9]/', '', $retval[1]));
                        } else {
                            $mpdvolume = '';
                        }
                        // sleep 1 second before looping
                        sleep(1);
                    } else {
                        // set the mpd volume directly
                        $retval = sysCmd('mpc volume '.$lastmpdvolume.' | grep "volume:" | xargs');
                        if (isset($retval[0])) {
                            $retval = explode(':',trim(preg_replace('!\s+!', ' ', $retval[0])));
                        } else {
                            // invalid response
                            sleep(2);
                            continue;
                        }
                        if (isset($retval[1])) {
                            $mpdvolume = trim(preg_replace('/[^0-9]/', '', $retval[1]));
                        } else {
                            $mpdvolume = '';
                        }
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

// function to format radiostring file names
function format_radiostring_file_name($radiostring)
{
    $radiostring = metadataStringClean($radiostring, 'radiostring');
    $filename = substr(str_replace(' ', '', strtolower($radiostring)), 0, 100);
    return $filename;
}

// function to format artist album song file names
function format_artist_album_song_file_name($artist, $album, $song)
{
    $artist = metadataStringClean($artist, 'artist');
    $album = metadataStringClean($album, 'album');
    $song = metadataStringClean($song, 'song');
    $filename = substr(str_replace(' ', '', strtolower($artist.'_'.$album.'_'.$song)),0, 100);
    return $filename;
}

// function to format artist album file names
function format_artist_album_file_name($artist, $album)
{
    $artist = metadataStringClean($artist, 'artist');
    $album = metadataStringClean($album, 'album');
    $filename = substr(str_replace(' ', '', strtolower($artist.'_'.$album)),0, 100);
    return $filename;
}

// function to format artist song file names
function format_artist_song_file_name($artist, $song)
{
    $artist = metadataStringClean($artist, 'artist');
    $song = metadataStringClean($song, 'song');
    $filename = substr(str_replace(' ', '', strtolower($artist.'_'.$song)), 0, 100);
    return $filename;
}

// function to format artist file names
function format_artist_file_name($artist)
{
    $artist = metadataStringClean($artist, 'artist');
    $filename = substr(str_replace(' ', '', strtolower($artist)), 0, 100);
    return $filename;
}

// function which checks if an URL is defined as a webradio station
function is_radioUrl($redis, $url)
// the function returns the name of the webradio station (true) or an empty string (false)
{
    $radios = $redis->hGetall('webradios');
    foreach ($radios as $radioName => $radioUrl) {
        if ($url === $radioUrl) {
            return $radioName;
        }
        // this code and the function 'create_radioUrlRedirected' below are currently not required
        //  MPD does not translate a radio URL to its redirected form, it retains the original
        // if ($redis->exists('webradios_redirected', $radioName)) {
            // $radioUrlRedirected = $redis->hget('webradios_redirected', $radioName);
            // if ($url === $radioUrlRedirected) {
                // return $radioName;
            // }
        // }
    }
    return '';
}

// // function which creates an array of redirected radio URL's
// function create_radioUrlRedirected($redis)
// // the function creates a redis hash table of radio url's which differ from their initial value
// {
    // $redis->del('webradios_redirected');
    // $radios = $redis->hGetall('webradios');
    // foreach ($radios as $radioName => $radioUrl) {
        // $radioUrlRedirected = sysCmd('curl -L -s -I --connect-timeout 2 -m 5 --retry 2 -o /dev/null -w %{url_effective} '.$radioUrl.' 2> /dev/null || echo ""')[0];
        // if (isset($radioUrlRedirected) && $radioUrlRedirected && $radioUrlRedirected != $radioUrl) {
            // $redis->hSet('webradios_redirected', $radioName, $radioUrlRedirected);
        // }
    // }
// }

// function to get information from last.fm
function get_lastFm($redis, $url)
// returns false or the response from last.fm as an array
// the $url parameter must contain the authorisation token
{
    $lastfmUp = $redis->hGet('service', 'lastfm');
    $proxy = $redis->hGetall('proxy');
    $lastfmDownErrors = array(8, 11, 16, 29);
    // for error codes see: https://www.last.fm/api/errorcodes
    if (!$lastfmUp) {
        // last.fm is down
        return 0;
    }
//    $retval = json_decode(curlGet($url, $proxy), true);
    // $proxy = $redis->hGetall('proxy');
    // using a proxy is possible but not implemented
    $retval = sysCmd('curl -s -f --connect-timeout 3 -m 7 --retry 2 "'.$url.'"');
    if (isset($retval[0])) {
        $retval = json_decode($retval[0], true);
    } else {
        // no response
        $retval = '';
    }
    if (isset($retval['error'])) {
        if (in_array($retval['error'], $lastfmDownErrors)) {
            // last.fm is down, or has other problems, for error codes see: https://www.last.fm/api/errorcodes
            // disable last.fm
            $redis->hSet('service', 'lastfm', 0);
            // this will be reset each 15 minutes, if the last.fm site is up
        }
        // an error has been returned
        return 0;
    } else if (!is_array($retval)) {
        // response is not an array
        return 0;
    }
    return $retval;
}

// function to get information from musicbrainz
function get_musicBrainz($redis, $url)
// returns false or the response from musicbrainz as an array
// no authorisation token is required in the $url parameter
{
    $musicbrainzUp = $redis->hGet('service', 'musicbrainz');
    $MusicBrainzUserAgent = 'RuneAudio/'.$redis->hGet('git', 'branch').'.'.$redis->get('buildversion').' ( https://www.runeaudio.com/forum/member857.html )';
    // $proxy = $redis->hGetall('proxy');
    // proxy currently not implemented
    if (!$musicbrainzUp) {
        // musicbrainz is down
        return 0;
    }
    $opts = array('http' =>
        array(
            // timeout in seconds
            // 5 seconds is a little on the high side, 2 or 3 is probably better
            // setting it higher results in less failures, but can result in delays
            'timeout' => 5,
            // ignore any errors, we check the returned value for errors
            'ignore_errors' => '1',
            // set up the user agent ! this is very important !
            'user_agent' => $MusicBrainzUserAgent
        )
    );
    // proxy is something like this - untested
    // if (isset($proxy['enable']) && $proxy['enable']) {
        // if (isset($proxy['host']) && $proxy['host']) {
            // $opts['http']['proxy'] = $proxy['host'];
            // if (isset($proxy['user']) && $proxy['user'] && isset($proxy['pass'])) {
                // $opts['http']['header'] = array("Proxy-Authorization: Basic $proxy['user']:$proxy['pass']");
            // }
        // }
    // }
    $context  = stream_context_create($opts);
    $retval = json_decode(file_get_contents($url, false, $context), true);
    if (isset($retval['error'])) {
        // error response, some are ok, I cannot fine a full list, so it is trial and error
        if (strpos(strtolower(' '.$retval['error']),'do not match')) {
            // no match error, it can happen, return false, don't disable musicbrainz
            return 0;
        } else {
            // unknown error response, save the details and disable musicbrainz
            $redis->hSet('musicbrainz', 'url', $url);
            $redis->hSet('musicbrainz', 'error', $retval['error']);
            $redis->hSet('musicbrainz', 'retval', json_encode($retval));
            $redis->hSet('service', 'musicbrainz', 0);
            // this will be reset each 15 minutes, if the musicbrainz site is up
            return 0;
        }
    } else if (!is_array($retval)) {
        // response is not an array, probably timed out, this is OK most of the time (see note above about false matches), don't disable musicbrainz
        return 0;
    }
    return $retval;
}

// function to get information from fanart.tv
function get_fanartTv($redis, $url)
// returns false or the response from fanart.tv as an array
// the $url parameter must contain the authorisation token
{
    $fanarttvUp = $redis->hGet('service', 'fanarttv');
    // $proxy = $redis->hGetall('proxy');
    // using a proxy is possible but not implemented
    if (!$fanarttvUp) {
        // fanart.tv is down
        return 0;
    }
    $opts = array('http' =>
        array(
            // timeout in seconds
            // 5 seconds is a little on the high side, 2 or 3 is probably better.
            // but this part of the code is attempted only when fanart.tv is up, so it should not be a problem
            'timeout' => 5,
            // ignore any errors, we check the returned value for errors
            'ignore_errors' => '1'
        )
    );
    // proxy is something like this - untested
    // if (isset($proxy['enable']) && $proxy['enable']) {
        // if (isset($proxy['host']) && $proxy['host']) {
            // $opts['http']['proxy'] = $proxy['host'];
            // if (isset($proxy['user']) && $proxy['user'] && isset($proxy['pass'])) {
                // $opts['http']['header'] = array("Proxy-Authorization: Basic $proxy['user']:$proxy['pass']");
            // }
        // }
    // }
    $context  = stream_context_create($opts);
    $retval = json_decode(file_get_contents($url, false, $context), true);
    // json_decode returns null when it cannot decode the string
    if (isset($retval['status']) && $retval['status'] === 'error') {
        // an error has been returned, valid response but no results
        return 0;
    } else if (!is_array($retval)) {
        // unexpected response, disable fanarttv
        $redis->hSet('service', 'fanarttv', 0);
        // this will be reset each 15 minutes, if the fanarttv site is up
        return 0;
    }
    return $retval;
}

// function to get information from discogs
function get_discogs($redis, $url)
// returns false or the response from discogs as an array
// the $url parameter must contain the authorisation token
{
    $discogsUp = $redis->hGet('service', 'discogs');
    if (!$discogsUp) {
        // discogs is down
        return 0;
    }
    // $proxy = $redis->hGetall('proxy');
    // using a proxy is possible but not implemented
    $retval = json_decode(sysCmd('curl -s -f --connect-timeout 3 -m 7 --retry 2 "'.$url.'"')[0], true);
    if (!isset($retval['pagination']['items'])) {
        // unexpected response, disable discogs, items should always be set
        $redis->hSet('service', 'discogs', 0);
        // this will be reset each 15 minutes, if the discogs site is up
        return 0;
    } else if (!$retval['pagination']['items']) {
        // a zero number of items has been returned, valid response but no results
        return 0;
    } else if (!is_array($retval)) {
        // response is not an array
        return 0;
    }
    return $retval;
}

// function to get lyrics
function get_lyrics($redis, $searchArtist, $searchSong)
// returns an array containing the lyrics or a message and a success indicator
{
    // makeitpersonal
    $makeitpersonalUp = $redis->hGet('service', 'makeitpersonal');
    if (!$makeitpersonalUp) {
        // makeitpersonal is down
        $retval = 'Lyrics service unavailable<br>';
        $found = false;
    } else {
        // no authorisation token required
        // url format: https://makeitpersonal.co/lyrics?artist=annie+lennox&title=little+bird
        $url = 'https://makeitpersonal.co/lyrics?artist='.urlClean($searchArtist).'&title='.urlClean($searchSong);
        // $proxy = $redis->hGetall('proxy');
        // using a proxy is possible but not implemented
        $retval = sysCmd('curl -s --connect-timeout 3 -m 7 --retry 1 "'.$url.'"');
        $retval = trim(preg_replace('!\s+!', ' ', implode('<br>', $retval)));
        // remove any control characters (hex 00 to 1F inclusive), delete character (hex 7F) and 'not assigned' characters (hex 81, 8D, 8F, 90 and 9D)
        $retval = preg_replace("/[\x{00}-\x{1F}\x{7F}\x{81}\x{8D}\x{8F}\x{90}\x{9D}]+/", '', $retval);
        if (!$retval) {
            // retval is an empty string
            $retval = '';
            // makeitpersonal should always return a value, something wrong so disable it
            //  makeitpersonal will reset itsself after 15 minutes
            $redis->hSet('service', 'makeitpersonal', 0);
            $found = false;
        } else if (strpos(strtolower(' '.$retval), 'invalid params')) {
            // 'invalid params' returned, error condition, but not fatal
            // the artist and/or song parameters are probably too long
            $retval = '';
            $found = false;
        } else if (strpos(strtolower(' '.$retval), '>oh-noes<')) {
            // 'oh-noes' returned, error condition in a web page, but not fatal
            $retval = '';
            $found = false;
        } else if (strpos(strtolower(' '.$retval), '>oh noes!<')) {
            // 'oh noes' returned, error condition in a web page, but not fatal
            $retval = '';
            $found = false;
        } else if (strpos(strtolower(' '.$retval), 'internal server error')) {
            // 'Internal Server Error' returned, error condition server response, but not fatal
            // trim the error message from the return value, it ends in '/html><br>'
            $retval = trim(get_between_data($retval, '/html><br>'));
            $found = false;
        } else if (strpos(strtolower(' '.$retval), 'bots have beat this api for the time being')) {
            // 'Bots have beat this API for the time being, sorry!' returned, error condition in a web page
            $redis->hSet('service', 'makeitpersonal', 0);
            // this will be reset each 15 minutes, providing that the makeitpersonal site is up
            $retval = 'Lyrics service unavailable<br>';
            $found = false;
        } else if (strpos(strtolower(' '.$retval), 'something went wrong')) {
            // 'something went wrong' returned, error condition, disable makeitpersonal
            $redis->hSet('service', 'makeitpersonal', 0);
            // this will be reset each 15 minutes, providing that the makeitpersonal site is up
            $retval = '';
            $found = false;
        } else {
            while (substr($retval, 0, 4) == '<br>') {
                // remove leading empty lines
                $retval = trim(substr($retval, 4));
            }
            if (!$retval) {
                // nothing returned, it should always return something, disable makeitpersonal
                $redis->hSet('service', 'makeitpersonal', 0);
                // this will be reset each 15 minutes, providing that the makeitpersonal site is up
                $retval = '';
                $found = false;
            } else {
                $found = true;
            }
        }
    }
    if ($found) {
        $return = array();
        $return['song_lyrics'] = $retval;
        $return['success'] = $found;
        $return['service'] = 'makeitpersonal';
        return $return;
    }
    //
    // chartlyrics
    $match_percentage = $redis->hGet('lyrics', 'match_percentage');
    $chartlyricsUp = $redis->hGet('service', 'chartlyrics');
    if (!$chartlyricsUp) {
        // chartlyrics is down
        $retval = 'Lyrics service unavailable<br>';
        $found = false;
    } else {
        // no authorisation token required
        // url format: : http://api.chartlyrics.com/apiv1.asmx/SearchLyricDirect?artist=abba&song=Lay%20All%20Your%20Love%20on%20Me
        $url = 'http://api.chartlyrics.com/apiv1.asmx/SearchLyricDirect?artist='.urlClean($searchArtist).'&song='.urlClean($searchSong);
        // $proxy = $redis->hGetall('proxy');
        // using a proxy is possible but not implemented
        $retval = sysCmd('curl -s --connect-timeout 3 -m 7 --retry 1 "'.$url.'"');
        $retval = trim(preg_replace('!\s+!', ' ', implode('<br>', $retval)));
        // remove any control characters (hex 00 to 1F inclusive), delete character (hex 7F) and 'not assigned' characters (hex 81, 8D, 8F, 90 and 9D)
        $retval = preg_replace("/[\x{00}-\x{1F}\x{7F}\x{81}\x{8D}\x{8F}\x{90}\x{9D}]+/", '', $retval);
        $artist = get_between_data($retval, '<LyricArtist>', '</LyricArtist>');
        $song = get_between_data($retval, '<LyricSong>', '</LyricSong>');
        $rank = get_between_data($retval, '<LyricRank>', '</LyricRank>');
        $lyricCorrectUrl = get_between_data($retval, '<LyricCorrectUrl>', '</LyricCorrectUrl>');
        $covertArtUrl = get_between_data($retval, '<LyricCovertArtUrl>', '</LyricCovertArtUrl>');
        $retval = get_between_data($retval, '<Lyric>', '</Lyric>');
        if (!$retval) {
            // retval is an empty string
            $retval = '';
            $found = false;
        } else if (strpos(strtolower(' '.$retval), 'internal server error')) {
            // 'Internal Server Error' returned, error condition server response, but not fatal
            // trim the error message from the return value, it ends in '/html><br>'
            $retval = trim(get_between_data($retval, '/html><br>'));
            $found = false;
        } else if (strpos(strtolower(' '.$retval), 'something went wrong')) {
            // 'something went wrong' returned, error condition, disable chartlyrics
            $redis->hSet('service', 'chartlyrics', 0);
            // this will be reset each 15 minutes, providing that the chartlyrics site is up
            $retval = '';
            $found = false;
        } else {
            while (substr($retval, 0, 4) == '<br>') {
                // remove leading empty lines
                $retval = trim(substr($retval, 4));
            }
            $found = true;
            if (!$retval) {
                // nothing returned
                $retval = '';
                $found = false;
            } else {
                // we get a lot of false positive matches from chartlyrics
                // check that at least $match_percentage (default = 50%) of words in the search artist name occur in the returned
                //  artist name, and visa versa, and also in the search and returned song title and visa versa
                if (count_word_occurancies($searchArtist, $artist) < $match_percentage) {
                    $found = false;
                } else if (count_word_occurancies($artist, $searchArtist) < $match_percentage) {
                    $found = false;
                } else if (count_word_occurancies($searchSong, $song) < $match_percentage) {
                    $found = false;
                } else if (count_word_occurancies($song, $searchSong) < $match_percentage) {
                    $found = false;
                }
                if (!$found) {
                    $retval = '';
                }
            }
        }
    }
    //
    $return = array();
    if (isset($rank)) {
        $return['rank'] = $rank;
    }
    if (isset($covertArtUrl)) {
        $return['covertArtUrl'] = $covertArtUrl;
    }
    if (isset($retval)) {
        $return['song_lyrics'] = $retval;
    }
    $return['success'] = $found;
    $return['service'] = 'chartlyrics';
    return $return;
}

// function to get information from coverartarchive.org
function get_coverartarchiveorg($redis, $url)
// returns false or the response from coverartarchiveorg as an array
// the $url parameter must contain the authorisation token
{
    $coverartarchiveorgUp = $redis->hGet('service', 'coverartarchiveorg');
    $MusicBrainzUserAgent = 'RuneAudio/'.$redis->hGet('git', 'branch').'.'.$redis->get('buildversion').' ( https://www.runeaudio.com/forum/member857.html )';
    if (!$coverartarchiveorgUp) {
        // coverartarchiveorg is down
        return 0;
    }
    // $proxy = $redis->hGetall('proxy');
    // using a proxy is possible but not implemented
    $opts = array('http' =>
        array(
            // timeout in seconds
            // 5 seconds is a little on the high side, 2 or 3 is probably better.
            // but this part of the code is attempted only when musicbrainz is up, so it should not be a problem
            'timeout' => 5,
            // ignore any errors, we check the returned value for errors
            'ignore_errors' => '1',
            // set up the user agent ! this is important !
            'user_agent' => $MusicBrainzUserAgent
        )
    );
    // proxy is something like this - untested
    // if (isset($proxy['enable']) && $proxy['enable']) {
        // if (isset($proxy['host']) && $proxy['host']) {
            // $opts['http']['proxy'] = $proxy['host'];
            // if (isset($proxy['user']) && $proxy['user'] && isset($proxy['pass'])) {
                // $opts['http']['header'] = array("Proxy-Authorization: Basic $proxy['user']:$proxy['pass']");
            // }
        // }
    // }
    $context  = stream_context_create($opts);
    $retval = json_decode(file_get_contents($url, false, $context), true);
    // json_decode returns null when it cannot decode the string
    if (!$retval || !is_array($retval)) {
        // nothing has been returned
        return 0;
    }
    return $retval;
}

// function to initialise the array used for artist, album, metadata
function setup_metadata_array($metadataArray=array())
// returns the array with all the values defined
// if the optional input parameter is supplied and is not an array an error will be returned
// see the calling function for details of the content
{
    if (!is_array($metadataArray)) {
        // invalid parameter
        return 0;
    }
    // these are the elements in the array, check that they exist in $info
    $infoElements = array('webradiostring', 'webradiostring_filename', 'artist', 'albumartist', 'artist_mbid', 'artist_arturl',
         'artist_bio_summary', 'artist_bio_content', 'artist_similar', 'artist_filename', 'song', 'song_mbid',
         'song_lyrics', 'song_filename', 'album', 'album_mbid', 'album_arturl_large', 'album_arturl_medium', 'album_arturl_small',
         'album_filename');
    foreach ($infoElements as $infoElement) {
        if (!isset($metadataArray[$infoElement])) {
            $metadataArray[$infoElement] = '';
        }
    }
    // some basic validations and setup
    if ($metadataArray['artist'] && !$metadataArray['albumartist']) {
        $metadataArray['albumartist'] = $metadataArray['artist'];
    }
    if (!$metadataArray['artist'] && $metadataArray['albumartist']) {
        $metadataArray['artist'] = $metadataArray['albumartist'];
    }
    return $metadataArray;
}

// function to search internet sources for song information
function get_songInfo($redis, $info=array())
// $info is an array of already set information, this can include:
//  the string delevered from the webradio station (webradiostring)
//  artist name (artist and albumartist),
//  artist musicbrainz-id (artist_mbid),
//  the url of a image of the artist (artist_arturl),
//  the http-formatted artist summary and full biography (artist_bio_summary and artist_bio_content),
//  a http-formatted list similar artists (artist_similar),
//  song (song),
//  song musicbrainz-id (song_mbid),
//  the http-formatted song lyrics (song_lyrics),
//  album (album),
//  album musicbrainz-id (album_mbid),
//  the url's of large medium and small album cover art (album_arturl_large, album_arturl_medium, and album_arturl_small),
//  cache file names for song and album (artist_filename, song_filename and album_filename)
// this function specifically retrieves and sets:
//  the http-formatted song lyrics (song_lyrics),
//  the name of the file containing the cached song information (song_filename)
// the function sets any empty values for which information is not available
// a cache file of artist information is always created, and if this exists it will be used instead of retrieving the information from internet
// the function will return 'unknown' values when nothing van be found
// the function needs at least an artist name and song name to be passed to in order to determine a response values
{
    if (!is_array($info)) {
        // invalid parameter
        return 0;
    }
    $toSetInfoFields = array('song_lyrics', 'song_filename');
    $toCacheInfoFields = array_merge(array('artist', 'albumartist', 'artist_mbid', 'song', 'song_mbid'), $toSetInfoFields);
    // check all the required elements exist in $info
    $info = setup_metadata_array($info);
    // when all the information which needs to be set is already set just save the cache
    $allset = true;
    foreach ($toSetInfoFields as $toSetInfoField) {
        $info[$toSetInfoField] = trim($info[$toSetInfoField]);
        if (!isset($info[$toSetInfoField]) || !$info[$toSetInfoField]) {
            $allset = false;
            break;
        }
    }
    $artDir = rtrim(trim($redis->get('albumart_image_dir')), '/');
    $artUrl = trim($redis->get('albumart_image_url_dir'), " \n\r\t\v\0/");
    $infoCache = array();
    if ($allset) {
        // all the fields are already set
        foreach ($toCacheInfoFields as $toCacheInfoField) {
            $infoCache[$toCacheInfoField] = trim($info[$toCacheInfoField]);
        }
        $fileName = $artDir.'/'.$infoCache['song_filename'].'.song';
        // don't worry if the file exists, just overwrite it
        file_put_contents($fileName , json_encode($infoCache)."\n");
        return $info;
    }
    if (!isset($info['song_filename']) || !$info['song_filename']) {
        $info['song_filename'] = '';
        if (isset($info['artist']) && isset($info['song']) && $info['artist'] && $info['song']) {
            $info['song_filename'] = format_artist_song_file_name($info['artist'], $info['song']);
        }
    }
    if ($info['song_filename']){
        $fileName = $artDir.'/'.$info['song_filename'].'.song';
        clearstatcache(true, $fileName);
        if (file_exists($fileName)) {
            // found a cached file, update its timestamp, use it and return
            $infoCache = json_decode(trim(file_get_contents($fileName)), true);
            if ($fileName == $artDir.'/'.$infoCache['song_filename'].'.song') {
                touch($fileName);
                foreach ($infoCache as $key => $value) {
                    if (trim($value) != '') {
                        $info[$key] = trim($value);
                    }
                }
                return $info;
            }
        }
    }
    // check to see we can search for something
    if ((!$info['artist'] && !$info['albumartist']) || !$info['song']) {
        // no artist name is set or no song name set, just return the default values, cache cannot be set
        $info['song_lyrics'] = 'No lyrics available<br>';
        return $info;
    }
    // build up an array of artist and album search strings based on album artist, (song) artist and album name
    //  no duplicates in the array,
    //  each element is non-null, no non-space whitespace, single spaces, no leading or trailing spaces, lowercase, max. 100 chars
    $searchArtists = array();
    // when the album artist is contained in the (track) artist use the album artist
    //  otherwise use the (track) artist
    $useAlbumArtist = false;
    if (strpos(' '.strtolower($info['artist']), strtolower(trim($info['albumartist'])))) {
        $useAlbumArtist = true;
        $artist = substr(trim(preg_replace('!\s+!', ' ', strtolower($info['albumartist']))), 0, 100);
        if ($artist && !strpos(' '.$artist, 'various') && !in_array($artist, $searchArtists)) {
            $searchArtists[] = $artist;
        }
        $artist = substr(metadataStringClean(strtolower($info['albumartist']), 'artist'), 0, 100);
        if ($artist && !strpos(' '.$artist, 'various') && !in_array($artist, $searchArtists)) {
            $searchArtists[] = $artist;
        }
    }
    if (!$useAlbumArtist || !count($searchArtists)) {
        $artist = substr(trim(preg_replace('!\s+!', ' ', strtolower($info['artist']))), 0, 100);
        if ($artist && !strpos(' '.$artist, 'various') && !in_array($artist, $searchArtists)) {
            $searchArtists[] = $artist;
        }
        $artist = substr(metadataStringClean(strtolower($info['artist']), 'artist'), 0, 100);
        if ($artist && !strpos(' '.$artist, 'various') && !in_array($artist, $searchArtists)) {
            $searchArtists[] = $artist;
        }
    }
    $searchSongs = array();
    $song = substr(trim(preg_replace('!\s+!', ' ', strtolower($info['song']))),0, 100);
    if ($song && !in_array($song, $searchSongs)) {
        $searchSongs[] = $song;
    }
    $song = substr(metadataStringClean(strtolower($info['song']), 'song'), 0, 100);
    if ($song && !in_array($song, $searchSongs)) {
        $searchSongs[] = $song;
    }
    // search for an existing cached file
    foreach ($searchArtists as $searchArtist) {
        foreach ($searchSongs as $searchSong) {
            $songFilename = format_artist_song_file_name($searchArtist, $searchSong);
            $fileName = $artDir.'/'.$songFilename.'.song';
            clearstatcache(true, $fileName);
            if (file_exists($fileName)) {
                // found a cached file, update its timestamp, use it and return
                $infoCache = json_decode(trim(file_get_contents($fileName)), true);
                if ($songFilename === $infoCache['song_filename']) {
                    touch($fileName);
                    $info = array_merge($info, $infoCache);
                    return $info;
                }
            }
        }
    }
    //
    // lyrics are sourced from makeitpersonal or chartlyrics using artist name and song name as key
    if (!$info['song_lyrics']) {
        foreach ($searchArtists as $searchArtist) {
            foreach ($searchSongs as $searchSong) {
                $retval = get_lyrics($redis, $searchArtist, $searchSong);
                if (isset($retval['song_lyrics']) && $retval['song_lyrics']) {
                    // we have a value for song lyrics, use it
                    $info['song_lyrics'] = $retval['song_lyrics'];
                    // break both loops
                    break 2;
                }
                // sleep before trying again
                sleep(2);
            }
        }
    }
    if (!$info['song_lyrics']) {
        $info['song_lyrics'] = 'No lyrics available<br>';
        if (isset($retval['service']) && ($retval['service'] == 'chartlyrics')) {
            $info['song_lyrics'] .= 'Add lyrics for this song at <a href="http://chartlyrics.com" target="_blank" rel="nofollow">www.chartlyrics.com</a>';
        }
    }
    if ($useAlbumArtist && $info['albumartist'] && $info['song']) {
        $info['song_filename'] = format_artist_song_file_name($info['albumartist'], $info['song']);
    } else if ($info['artist'] && $info['song']) {
        $info['song_filename'] = format_artist_song_file_name($info['artist'], $info['song']);
    }
    if (isset($info['song_filename']) && $info['song_filename']) {
        $infoCache = array();
        foreach ($toCacheInfoFields as $toCacheInfoField) {
            $infoCache[$toCacheInfoField] = trim($info[$toCacheInfoField]);
        }
        if ($infoCache['song_filename']) {
            $fileName = $artDir.'/'.$infoCache['song_filename'].'.song';
            // don't worry if the file exists, just overwrite it
            file_put_contents($fileName , json_encode($infoCache)."\n");
        }
    }
    return $info;
}

// function to search internet sources for album information
function get_albumInfo($redis, $info=array())
// $info is an array of already set information, this can include:
//  the string delevered from the webradio station (webradiostring)
//  artist name (artist and albumartist),
//  artist musicbrainz-id (artist_mbid),
//  the url of a image of the artist (artist_arturl),
//  the http-formatted artist summary and full biography (artist_bio_summary and artist_bio_content),
//  a http-formatted list similar artists (artist_similar),
//  song (song),
//  song musicbrainz-id (song_mbid),
//  the http-formatted song lyrics (song_lyrics),
//  album (album),
//  album musicbrainz-id (album_mbid),
//  the url's of large medium and small album cover art (album_arturl_large, album_arturl_medium, and album_arturl_small),
//  cache file names for song and album (artist_filename, song_filename and album_filename)
// this function specifically retrieves and sets:
//  the url's of large medium and small album cover art (album_arturl_large, album_arturl_medium, and album_arturl_small),
//  the name of the file containing the cached album information (album_filename)
// the function sets any empty values for which information is not available
// a cache file of album information is always created, and if this exists it will be used instead of retrieving the information from internet
// the function will return 'unknown' values when nothing van be found
// the function needs at least an artist name plus a song name or album name to be passed to in order to determine a response values
{
    if (!is_array($info)) {
        // invalid parameter
        return 0;
    }
    $toSetInfoFields = array('album_arturl_large', 'album_arturl_medium', 'album_arturl_small', 'album_filename');
    $toCacheInfoFields = array_merge(array('artist', 'albumartist', 'artist_mbid', 'album', 'album_mbid'), $toSetInfoFields);
    // check all the required elements exist in $info
    $info = setup_metadata_array($info);
    // when all the information which needs to be set is already set just save the cache
    $allset = true;
    foreach ($toSetInfoFields as $toSetInfoField) {
        $info[$toSetInfoField] = trim($info[$toSetInfoField]);
        if (!isset($info[$toSetInfoField]) || !$info[$toSetInfoField]) {
            $allset = false;
            break;
        }
    }
    $discogsToken = $redis->hGet('discogs', 'token');
    $artDir = rtrim(trim($redis->get('albumart_image_dir')), '/');
    $artUrl = trim($redis->get('albumart_image_url_dir'), " \n\r\t\v\0/");
    $infoCache = array();
    if ($allset) {
        // all the fields are already set
        foreach ($toCacheInfoFields as $toCacheInfoField) {
            $infoCache[$toCacheInfoField] = trim($info[$toCacheInfoField]);
        }
        $fileName = $artDir.'/'.$infoCache['album_filename'].'.album';
        // don't worry if the file exists, just overwrite it
        file_put_contents($fileName , json_encode($infoCache)."\n");
        return $info;
    }
    if (!isset($info['album_filename']) || !$info['album_filename']) {
        $info['album_filename'] = '';
        if (isset($info['artist']) && isset($info['album']) && $info['artist'] && $info['album']) {
            $info['album_filename'] = format_artist_album_file_name($info['artist'], $info['album']);
        }
    }
    if ($info['album_filename']){
        $fileName = $artDir.'/'.$info['album_filename'].'.album';
        clearstatcache(true, $fileName);
        if (file_exists($fileName)) {
            // found a cached file, update its timestamp, use it and return
            $infoCache = json_decode(trim(file_get_contents($fileName)), true);
            if ($fileName == $artDir.'/'.$infoCache['album_filename'].'.album') {
                touch($fileName);
                foreach ($infoCache as $key => $value) {
                    if (trim($value) != '') {
                        $info[$key] = trim($value);
                    }
                }
                return $info;
            }
        }
    }
    // check to see we can search for something
    if ((!$info['artist'] && !$info['albumartist']) || !$info['album']) {
        // no artist name is set or no album name set, just return the default values, cache cannot be set
        if ($info['webradiostring']) {
            $info['album_arturl_large'] = $artUrl.'/radio.png';
            $info['album_arturl_medium'] = $artUrl.'/radio.png';
            $info['album_arturl_small'] = $artUrl.'/radio.png';
        } else {
            $info['album_arturl_large'] = $artUrl.'/none.png';
            $info['album_arturl_medium'] = $artUrl.'/none.png';
            $info['album_arturl_small'] = $artUrl.'/none.png';
        }
        return $info;
    }
    // build up an array of artist and album search strings based on album artist, (song) artist and album name
    //  no duplicates in the array,
    //  each element is non-null, no non-space whitespace, single spaces, no leading or trailing spaces, lowercase, max. 100 chars
    $searchArtists = array();
    // when the album artist is contained in the (track) artist use the album artist
    //  otherwise use the (track) artist
    if (strpos(' '.strtolower($info['artist']), strtolower(trim($info['albumartist'])))) {
        $useAlbumArtist = true;
        $artist = substr(trim(preg_replace('!\s+!', ' ', strtolower($info['albumartist']))), 0, 100);
        if ($artist && !strpos(' '.$artist, 'various') && !in_array($artist, $searchArtists)) {
            $searchArtists[] = $artist;
        }
        $artist = substr(metadataStringClean(strtolower($info['albumartist']), 'artist'), 0, 100);
        if ($artist && !strpos(' '.$artist, 'various') && !in_array($artist, $searchArtists)) {
            $searchArtists[] = $artist;
        }
    } else {
        $useAlbumArtist = false;
        $artist = substr(trim(preg_replace('!\s+!', ' ', strtolower($info['artist']))), 0, 100);
        if ($artist && !strpos(' '.$artist, 'various') && !in_array($artist, $searchArtists)) {
            $searchArtists[] = $artist;
        }
        $artist = substr(metadataStringClean(strtolower($info['artist']), 'artist'), 0, 100);
        if ($artist && !strpos(' '.$artist, 'various') && !in_array($artist, $searchArtists)) {
            $searchArtists[] = $artist;
        }
    }
    $searchAlbums = array();
    $album = substr(trim(preg_replace('!\s+!', ' ', strtolower($info['album']))),0, 100);
    if ($album && !in_array($album, $searchAlbums)) {
        $searchAlbums[] = $album;
    }
    $album = substr(metadataStringClean(strtolower($info['album']), 'album'), 0, 100);
    if ($album && !in_array($album, $searchAlbums)) {
        $searchAlbums[] = $album;
    }
    // search for an existing cached file
    foreach ($searchAlbums as $searchAlbum) {
        foreach ($searchArtists as $searchArtist) {
            $albumFilename = format_artist_album_file_name($searchArtist, $searchAlbum);
            $fileName = $artDir.'/'.$albumFilename.'.album';
            clearstatcache(true, $fileName);
            if (file_exists($fileName)) {
                // found a cached file, update its timestamp, use it and return
                $infoCache = json_decode(trim(file_get_contents($fileName)), true);
                if ($fileName == $artDir.'/'.$infoCache['album_filename'].'.album') {
                    touch($fileName);
                    foreach ($infoCache as $key => $value) {
                        if (trim($value) != '') {
                            $info[$key] = trim($value);
                        }
                    }
                    return $info;
                }
            }
        }
    }
    //
    // try once to retrieve the album art url from last.fm, it only occasionally returns a useful
    //  value, mostly it returns an image of a star, but when it returns something it is accurate
    $lastfmApikey = $redis->hGet('lastfm', 'apikey');
    $proxy = $redis->hGetall('proxy');
    if (!$info['album_arturl_large']) {
        $cover_url = ui_lastFM_coverart($redis, $searchArtists[0], $searchAlbums[0], $lastfmApikey, $proxy);
        if (isset($cover_url) && $cover_url && !strpos(' '.strtolower($cover_url), '2a96cbd8b46e442fc41c2b86b821562f')) {
            // not a star image so use it
            $info['album_arturl_small'] = $cover_url;
            $info['album_arturl_medium'] = $cover_url;
            $info['album_arturl_large'] = $cover_url;
        }
    }
    //
    // album art is normally sourced from coverartarchive.org using album_mbid as key
    if (!$info['album_arturl_large']) {
        // we need to determine at least one of the album art url's
        if (!$info['album_mbid']) {
            // no album mbid set, try to determine it via musicbrainz
            foreach ($searchAlbums as $searchAlbum) {
                foreach ($searchArtists as $searchArtist) {
                    // url format: https://musicbrainz.org/ws/2/release?query=release:diva+artist:annie+lennox&incl=artist-credit&limit=1&fmt=json
                    $url = 'https://musicbrainz.org/ws/2/release?query=release:'.urlClean($searchAlbum).'+artist:'.urlClean($searchArtist).'&incl=artist-credit&limit=1&fmt=json';
                    $retval = get_musicBrainz($redis, $url);
                    if ($retval) {
                        // found the release (album) on musicbrainz, use the data if it is set
                        if (isset($retval['releases'][0]['id']) && $retval['releases'][0]['id']) {
                            // album music brainz id is set
                            $info['album_mbid'] = $retval['releases'][0]['id'];
                            // also save the artist mbid if needed
                            if (!$info['artist_mbid'] && isset($retval['releases'][0]['artist-credit'][0]['artist']['id']) && $retval['releases'][0]['artist-credit'][0]['artist']['id']) {
                                $info['artist_mbid'] = $retval['releases'][0]['artist-credit'][0]['artist']['id'];
                            }
                            // break both loops
                            break 2;
                        }
                    }
                    // sleep before trying again
                    sleep(2);
                }
            }
        }
        if ($info['album_mbid']) {
            // album mbid set, get the album art url's from coverartarchive.org
            // url format: http://archive.org/download/mbid-96964bbe-81f0-3d3a-8ec9-9e10362e089a/index.json
            $url = 'http://archive.org/download/mbid-'.$info['album_mbid'].'/index.json';
            $retval = get_coverartarchiveorg($redis, $url);
            if ($retval) {
                // album art found
                if (isset($retval['images'][0]['thumbnails']['small']) && $retval['images'][0]['thumbnails']['small']) {
                    $info['album_arturl_small'] = $retval['images'][0]['thumbnails']['small'];
                    $info['album_arturl_medium'] = $retval['images'][0]['thumbnails']['small'];
                    $info['album_arturl_large'] = $retval['images'][0]['thumbnails']['small'];
                }
                if (isset($retval['images'][0]['thumbnails']['large']) && $retval['images'][0]['thumbnails']['large']) {
                    $info['album_arturl_medium'] = $retval['images'][0]['thumbnails']['large'];
                    $info['album_arturl_large'] = $retval['images'][0]['thumbnails']['large'];
                    if (!$info['album_arturl_small']) {
                        $info['album_arturl_small'] = $retval['images'][0]['thumbnails']['large'];
                    }
                }
                if (isset($retval['images'][0]['image']) && $retval['images'][0]['image']) {
                    $info['album_arturl_large'] = $retval['images'][0]['image'];
                    if (!$info['album_arturl_medium']) {
                        $info['album_arturl_medium'] = $retval['images'][0]['image'];
                    }
                    if (!$info['album_arturl_small']) {
                        $info['album_arturl_small'] = $retval['images'][0]['image'];
                    }
                }
            }
        }
        if (!$info['album_arturl_large'] && $info['album'] && $info['albumartist']) {
            // still nothing found try discogs
            // curl -s -f --connect-timeout 5 -m 10 --retry 2 "https://api.discogs.com/database/search?release_title=diva&artist=annie%20lennox&token=KFlNcwbmGJPjHGejEwSdjJjAcbDFFlycriUQSITI&per_page=1&page=1&type=single|album&format=CD
            $url = 'https://api.discogs.com/database/search?release_title'.urlClean($info['album']).'&artist='.urlClean($info['albumartist']).'&token='.$discogsToken.'&per_page=1&page=1&type=single|album&format=CD';
            $retval = get_discogs($redis, $url);
            if ($retval) {
                if (isset($retval['results'][0]['cover_image']) && $retval['results'][0]['cover_image']) {
                    // album art is filled, use it and save the details
                    $info['album_arturl_large'] = trim($retval['results'][0]['cover_image']);
                    $info['album_arturl_medium'] = trim($retval['results'][0]['cover_image']);
                    $info['album_arturl_small'] = trim($retval['results'][0]['cover_image']);
                }
                if (isset($retval['results'][0]['thumb']) && $retval['results'][0]['thumb']) {
                    // album art is filled, use it and save the details
                    $info['album_arturl_small'] = trim($retval['results'][0]['thumb']);
                    if (!$info['album_arturl_large']) {
                        $info['album_arturl_large'] = trim($retval['results'][0]['thumb']);
                        $info['album_arturl_medium'] = trim($retval['results'][0]['thumb']);
                    }
                }
            }
        }
    }
    if (!$info['album_arturl_large']) {
        // all art url's are set or unset
        if ($info['webradiostring']) {
            $info['album_arturl_large'] = $artUrl.'/radio.png';
            $info['album_arturl_medium'] = $artUrl.'/radio.png';
            $info['album_arturl_small'] = $artUrl.'/radio.png';
        } else {
            $info['album_arturl_large'] = $artUrl.'/none.png';
            $info['album_arturl_medium'] = $artUrl.'/none.png';
            $info['album_arturl_small'] = $artUrl.'/none.png';
        }
    }
    if ($useAlbumArtist && $info['albumartist'] && $info['album']) {
        $info['album_filename'] = format_artist_album_file_name($info['albumartist'], $info['album']);
    } else if ($info['artist'] && $info['album']) {
        $info['album_filename'] = format_artist_album_file_name($info['artist'], $info['album']);
    }
    if (isset($info['album_filename']) && $info['album_filename']) {
        $infoCache = array();
        foreach ($toCacheInfoFields as $toCacheInfoField) {
            $infoCache[$toCacheInfoField] = trim($info[$toCacheInfoField]);
        }
        if ($infoCache['album_filename']) {
            $fileName = $artDir.'/'.$infoCache['album_filename'].'.album';
            // don't worry if the file exists, just overwrite it
            file_put_contents($fileName , json_encode($infoCache)."\n");
        }
    }
    return $info;
}

// function to search internet sources for artist information
function get_artistInfo($redis, $info=array())
// $info is an array of already set information, this can include:
//  the string delevered from the webradio station (webradiostring)
//  artist name (artist and albumartist),
//  artist musicbrainz-id (artist_mbid),
//  the url of a image of the artist (artist_arturl),
//  the http-formatted artist summary and full biography (artist_bio_summary and artist_bio_content),
//  a http-formatted list similar artists (artist_similar),
//  song (song),
//  song musicbrainz-id (song_mbid),
//  the http-formatted song lyrics (song_lyrics),
//  album (album),
//  album musicbrainz-id (album_mbid),
//  the url's of large medium and small album cover art (album_arturl_large, album_arturl_medium, and album_arturl_small),
//  cache file names for song and album (artist_filename, song_filename and album_filename)
// this function specifically retrieves and sets:
//  the url of a image of the artist (artist_arturl),
//  the http-formatted artist summary and full biography (artist_bio_summary and artist_bio_content),
//  a http-formatted list similar artists (artist_similar),
//  the name of the file containing the cached artist information (artist_filename)
// the function sets any empty values for which information is not available
// a cache file of artist information is always created, and if this exists it will be used instead of retrieving the information from internet
// the function will return 'unknown' values when nothing van be found
// the function needs at least an artist name to be passed to in order to determine a response values
{
    if (!is_array($info)) {
        // invalid parameter
        return 0;
    }
    $toSetInfoFields = array('artist_arturl', 'artist_bio_summary', 'artist_bio_content', 'artist_similar', 'artist_filename');
    $toCacheInfoFields = array_merge(array('artist', 'albumartist', 'artist_mbid'), $toSetInfoFields);
    // check all the required elements exist in $info
    $info = setup_metadata_array($info);
    // when all the information which needs to be set is already set just save the cache
    $allset = true;
    foreach ($toSetInfoFields as $toSetInfoField) {
        $info[$toSetInfoField] = trim($info[$toSetInfoField]);
        if (!isset($info[$toSetInfoField]) || !$info[$toSetInfoField]) {
            $allset = false;
            break;
        }
    }
    $artDir = rtrim(trim($redis->get('albumart_image_dir')), '/');
    $artUrl = trim($redis->get('albumart_image_url_dir'), " \n\r\t\v\0/");
    $infoCache = array();
    if ($allset) {
        // all the fields are already set
        foreach ($toCacheInfoFields as $toCacheInfoField) {
            $infoCache[$toCacheInfoField] = trim($info[$toCacheInfoField]);
        }
        $fileName = $artDir.'/'.$infoCache['artist_filename'].'.artist';
        // don't worry if the file exists, just overwrite it
        file_put_contents($fileName , json_encode($infoCache)."\n");
        return $info;
    }
    if (!isset($info['artist_filename']) || !$info['artist_filename']) {
        $info['artist_filename'] = '';
        if (isset($info['artist']) && $info['artist']) {
            $info['artist_filename'] = format_artist_file_name($info['artist']);
        }
    }
    if ($info['artist_filename']){
        $fileName = $artDir.'/'.$info['artist_filename'].'.artist';
        clearstatcache(true, $fileName);
        if (file_exists($fileName)) {
            // found a cached file, update its timestamp, use it and return
            $infoCache = json_decode(trim(file_get_contents($fileName)), true);
            if ($fileName == $artDir.'/'.$infoCache['artist_filename'].'.artist') {
                touch($fileName);
                foreach ($infoCache as $key => $value) {
                    if (trim($value) != '') {
                        $info[$key] = trim($value);
                    }
                }
                return $info;
            }
        }
    }
    // check to see we can search for something
    if (!$info['artist'] && !$info['albumartist']) {
        // no artist name is set, just return the default values, cache cannot be set
        $info['artist_arturl'] = $artUrl.'/none.png';
        $info['artist_bio_summary'] = $info['artist'].' - Sorry, no details available.';
        $info['artist_bio_content'] = $info['artist'].' - Sorry, no details available.';
        $info['artist_similar'] = '<br>';
        return $info;
    } else if (!$info['artist']) {
        $info['artist'] = $info['albumartist'];
    } else if (!$info['albumartist']) {
        $info['albumartist'] = $info['artist'];
    }
    // build up an array of artist search strings based on album artist and (song) artist
    //  no duplicates in the array,
    //  each element is non-null, no non-space whitespace, single spaces, no leading or trailing spaces, lowercase, max. 100 chars
    $searchArtists = array();
    // when the album artist is contained in the (track) artist use the album artist
    //  otherwise use the (track) artist
    if (strpos(' '.strtolower($info['artist']), strtolower(trim($info['albumartist'])))) {
        $useAlbumArtist = true;
        $artist = substr(trim(preg_replace('!\s+!', ' ', strtolower($info['albumartist']))), 0, 100);
        if ($artist && !strpos(' '.$artist, 'various') && !in_array($artist, $searchArtists)) {
            $searchArtists[] = $artist;
        }
        $artist = substr(metadataStringClean(strtolower($info['albumartist']), 'artist'), 0, 100);
        if ($artist && !strpos(' '.$artist, 'various') && !in_array($artist, $searchArtists)) {
            $searchArtists[] = $artist;
        }
    } else {
        $useAlbumArtist = false;
        $artist = substr(trim(preg_replace('!\s+!', ' ', strtolower($info['artist']))), 0, 100);
        if ($artist && !strpos(' '.$artist, 'various') && !in_array($artist, $searchArtists)) {
            $searchArtists[] = $artist;
        }
        $artist = substr(metadataStringClean(strtolower($info['artist']), 'artist'), 0, 100);
        if ($artist && !strpos(' '.$artist, 'various') && !in_array($artist, $searchArtists)) {
            $searchArtists[] = $artist;
        }
    }
    // search for an existing cached file
    foreach ($searchArtists as $searchArtist) {
        $artistFilename = format_artist_file_name($searchArtist);
        $fileName = $artDir.'/'.$artistFilename.'.artist';
        clearstatcache(true, $fileName);
        if (file_exists($fileName)) {
            // found a cached file, update its timestamp, use it and return
            $infoCache = json_decode(trim(file_get_contents($fileName)), true);
            if ($fileName == $artDir.'/'.$infoCache['artist_filename'].'.artist') {
                touch($fileName);
                foreach ($infoCache as $key => $value) {
                    if (trim($value) != '') {
                        $info[$key] = trim($value);
                    }
                }
                return $info;
            }
        }
    }
    //
    $lastfmApikey = $redis->hGet('lastfm', 'apikey');
    $fanarttvToken = $redis->hGet('fanarttv', 'token');
    // use last.fm to retrieve the artist biography and similar artist list, this will also return the artist_mbid
    // when last.fm returns nothing use musicbrainz to determing the artist_mbid
    // use fanart.tv to determine the arist_arturl (indexed by the artist_mbid)
    if (!$info['artist_bio_summary'] || !$info['artist_bio_content'] || !$info['artist_similar']) {
        // one or more required data fields is empty
        if ($info['artist_mbid']) {
            // mbid is set so use it to retreve last.fm data
            // use the command: curl -s -f --connect-timeout 1 -m 10 --retry 2 "https://ws.audioscrobbler.com/2.0/?method=artist.getinfo&autocorrect=1&mbid=$mbid&api_key=ba8ad00468a50732a3860832eaed0882&format=json"
            // e.g.: curl -s -f --connect-timeout 1 -m 10 --retry 2 "https://ws.audioscrobbler.com/2.0/?method=artist.getinfo&autocorrect=1&mbid=3e30aebd-0557-4cfd-8fb9-3945afa5d72b&api_key=ba8ad00468a50732a3860832eaed0882&format=json"
            $url = 'https://ws.audioscrobbler.com/2.0/?method=artist.getinfo&autocorrect=1&mbid='.$info['artist_mbid'].'&api_key='.$lastfmApikey.'&format=json&limit=1';
            $retval = get_lastFm($redis, $url);
        } else {
            $retval = false;
        }
        if (!$retval) {
            // error returned, retrieve the info using artist name
            foreach ($searchArtists as $searchArtist) {
                // use the command: curl -s -f --connect-timeout 1 -m 10 --retry 2 "https://ws.audioscrobbler.com/2.0/?method=artist.getinfo&autocorrect=1&artist=$artist&api_key=ba8ad00468a50732a3860832eaed0882&format=json"
                // e.g.: curl -s -f --connect-timeout 1 -m 10 --retry 2 "https://ws.audioscrobbler.com/2.0/?method=artist.getinfo&autocorrect=1&artist=annie+lennox&api_key=ba8ad00468a50732a3860832eaed0882&format=json"
                $url = 'https://ws.audioscrobbler.com/2.0/?method=artist.getinfo&autocorrect=1&artist='.urlClean($searchArtist).'&api_key='.$lastfmApikey.'&format=json&limit=1';
                $retval = get_lastFm($redis, $url);
                if ($retval) {
                    // found the artist on last.fm, use the data
                    break;
                }
                // sleep before trying again
                sleep(2);
            }
        }
        if ($retval) {
            if (!$info['artist_mbid'] && isset($retval['artist']['mbid']) && trim($retval['artist']['mbid']) ) {
                $info['artist_mbid'] = trim($retval['artist']['mbid']);
            }
            if ($useAlbumArtist && $info['albumartist']) {
                $bioArtist = $info['albumartist'].' - ';
            } else if ($info['artist']) {
                $bioArtist = $info['artist'].' - ';
            } else {
                $bioArtist = '';
            }
            if (!$info['artist_bio_summary'] && isset($retval['artist']['bio']['summary']) && trim($retval['artist']['bio']['summary'])) {
                $info['artist_bio_summary'] = $bioArtist.trim(str_replace('">Read more on Last.fm', '/+wiki" target="_blank" rel="nofollow">Read more on Last.fm', preg_replace('/[\t\n\r\s]+/',' ',stripcslashes($retval['artist']['bio']['summary']))));
            }
            if (!$info['artist_bio_content'] && isset($retval['artist']['bio']['content']) && trim($retval['artist']['bio']['content'])) {
                $info['artist_bio_content'] = $bioArtist.trim(str_replace('">Read more on Last.fm', '/+wiki" target="_blank" rel="nofollow">Read more on Last.fm', preg_replace('/[\t\n\r\s]+/',' ',stripcslashes($retval['artist']['bio']['content']))));
            }
            if (!$info['artist_similar'] && isset($retval['artist']['similar']['artist'][0]['name'])) {
                // similar artist name summary is set
                $indx = 0;
                $info['artist_similar'] = '<strong>Similar artists</strong><br>'.$retval['artist']['similar']['artist'][$indx]['name'];
                while (++$indx <= 7) {
                    if (isset($retval['artist']['similar']['artist'][$indx]['name'])) {
                        $info['artist_similar'] .= '<br>'.$retval['artist']['similar']['artist'][$indx]['name'];
                    } else {
                        $indx = 7;
                    }
                }
                if (strpos($info['artist_similar'], 'Samsung Galaxy A80') || strpos($info['artist_similar'], 'PUBG Mobile')) {
                    $info['artist_similar'] = '';
                }
            }
        }
    }
    if (!$info['artist_arturl']) {
        // art url has no value
        if (!$info['artist_mbid']) {
            // try to get the musicbrainz id from musicbrainz
            foreach ($searchArtists as $searchArtist) {
                // use the command: curl -s -f --connect-timeout 1 -m 10 --retry 2 "https://musicbrainz.org/ws/2/artist/?query=annie%20lennox&limit=1&fmt=json"
                $url = 'https://musicbrainz.org/ws/2/artist/?query='.urlClean($searchArtist).'&limit=1&fmt=json';
                $retval = get_musicBrainz($redis, $url);
                if ($retval) {
                    // found the artist on musicbrainz, use the data
                    if (isset($retval['artists'][0]['id'])) {
                        // music brainz id is set
                        $info['artist_mbid'] = $retval['artists'][0]['id'];
                        break;
                    }
                }
                // sleep before trying again
                sleep(2);
            }
        }
        if ($info['artist_mbid']) {
            // mbid is set so we can try to get the art url from fanart.tv
            // call: curl -s -f --connect-timeout 1 -m 10 --retry 2 "http://webservice.fanart.tv/v3/music/3e30aebd-0557-4cfd-8fb9-3945afa5d72b?api_key=90fa4838789ea346c5e9cff6715f6e9b"
            // e.g.: http://webservice.fanart.tv/v3/music/<mbid>?api_key=<token>
            $url = 'http://webservice.fanart.tv/v3/music/'.$info['artist_mbid'].'?api_key='.$fanarttvToken;
            $retval = get_fanartTv($redis, $url);
            if ($retval && isset($retval['artistthumb'][0])) {
                // several image url's can be returned, randomly choose one
                $cnt = count($retval['artistthumb']);
                $indx = rand(0, $cnt-1);
                $info['artist_arturl'] = $retval['artistthumb'][$indx]['url'];
            }
        }
    }
    if (!$info['artist_bio_summary']) {
        $info['artist_bio_summary'] = $info['artist'].' - Sorry, no details available.';
    }
    if (!$info['artist_bio_content']) {
        $info['artist_bio_content'] = $info['artist'].' - Sorry, no details available.';
    }
    if (!$info['artist_similar']) {
        $info['artist_similar'] = '<br>';
    }
    if (!$info['artist_arturl']) {
        $info['artist_arturl'] = $artUrl.'/none.png';
    }
    if ($useAlbumArtist && $info['albumartist']) {
        $info['artist_filename'] = format_artist_file_name($info['albumartist']);
    } else if ($info['artist']) {
        $info['artist_filename'] = format_artist_file_name($info['artist']);
    }
    if (isset($info['artist_filename']) && $info['artist_filename']) {
        $infoCache = array();
        foreach ($toCacheInfoFields as $toCacheInfoField) {
            $infoCache[$toCacheInfoField] = trim($info[$toCacheInfoField]);
        }
        if ($infoCache['artist_filename']) {
            $fileName = $artDir.'/'.$infoCache['artist_filename'].'.artist';
            // don't worry if the file exists, just overwrite it
            file_put_contents($fileName , json_encode($infoCache)."\n");
        }
    }
    return $info;
}

// function which returns the artist image url artist information, the song lyrics and the album image URL as an array for a webradio string
function wrk_get_webradio_art($redis, $radiostring)
// this function also manages cache files containing all the retrieved information for a given webradio string
{
    $radiostring = webradioStringClean($radiostring);
    $radiostring = webradioStringRemovePrefix($redis, $radiostring);
    $radiostringClean = strtolower($radiostring);
    // $radiostringClean = metadataStringClean(strtolower($radiostring), 'radiostring');
    if (strlen($radiostringClean) <= 6) {
        // the assumption here is that a that a valid string has at least one letter for an
        //  artist, one letter for the song and a ' - ' (space-hyphen-space) separating them
        // so just return false
        return 0;
    }
    $internetUp = $redis->hGet('service', 'internet');
    if (!$internetUp) {
        // no internet connection, just return a false value
        return 0;
    }
    $info = array();
    $info['webradiostring'] = $radiostring;
    $info['webradiostring_filename'] = format_radiostring_file_name($radiostring);
    $artDir = rtrim(trim($redis->get('albumart_image_dir')), '/');
    $noRadioCache = true;
    if ($info['webradiostring_filename']) {
        $infoCache = array();
        $fileName = $artDir.'/'.$info['webradiostring_filename'].'.radio';
        clearstatcache(true, $fileName);
        if (file_exists($fileName)) {
            $infoCache = json_decode(trim(file_get_contents($fileName)), true);
            if ($fileName === $infoCache['webradiostring_filename']) {
                // update the file time stamp and use it
                touch($fileName);
                $info  = $infoCache;
                $noRadioCache = false;
            }
            unset($infoCache);
        }
    }
    $toSetInfoFields = array('webradiostring', 'webradiostring_filename', 'artist', 'artist_filename', 'album', 'album_filename', 'song', 'song_filename');
    $toCacheInfoFields = $toSetInfoFields;
    // check all the required elements exist in $info
    $info = setup_metadata_array($info);
    $lastfmApikey = $redis->hGet('lastfm', 'apikey');
    $discogsToken = $redis->hGet('discogs', 'token');
    if ($noRadioCache && (!$info['artist'] && !$info['albumartist']) || !$info['song']) {
        // this is the command to split the $radiostringClean into artist and song from last.fm
        // curl -s -f --connect-timeout 5 -m 10 --retry 2 https://ws.audioscrobbler.com/2.0/?method=track.search&track=annie%20lennox%20why&api_key=ba8ad00468a50732a3860832eaed0882&format=json&limit=1
        $url = 'https://ws.audioscrobbler.com/2.0/?method=track.search&track='.urlClean($radiostringClean).'&api_key='.$lastfmApikey.'&format=json&limit=1';
        $retval = get_lastFm($redis, $url);
        if ($retval) {
            if (isset($retval['results']['trackmatches']['track'][0]['name']) && $retval['results']['trackmatches']['track'][0]['name']) {
                $info['song'] = trim($retval['results']['trackmatches']['track'][0]['name']);
            }
            if (isset($retval['results']['trackmatches']['track'][0]['artist']) && $retval['results']['trackmatches']['track'][0]['artist']) {
                $info['artist'] = ucfirst($retval['results']['trackmatches']['track'][0]['artist']);
                $info['albumartist'] = ucfirst(metadataStringClean($info['artist'], 'artist'));
            }
            if (isset($retval['results']['trackmatches']['track'][0]['mbid']) && $retval['results']['trackmatches']['track'][0]['mbid']) {
                $info['song_mbid'] = trim($retval['results']['trackmatches']['track'][0]['mbid']);
            }
        }
    }
    if ($redis->get('activePlayer') != 'MPD') {
        // no longer MPD, just return with the current information
        return $info;
    }
    if ($noRadioCache && (!$info['artist'] && !$info['albumartist']) || !$info['song'] || $info['album']) {
        // try to pick the artist album and song up from discogs
        // the album art is will also be returned if there is a match
        // curl -s -f --connect-timeout 5 -m 10 --retry 2 "https://api.discogs.com/database/search?q=little%20bird%20annie%20lennox&token=KFlNcwbmGJPjHGejEwSdjJjAcbDFFlycriUQSITI&per_page=1&page=1&type=single|album&format=CD
        $url = 'https://api.discogs.com/database/search?q='.urlClean($radiostringClean).'&token='.$discogsToken.'&per_page=1&page=1&type=single|album&format=CD';
        $retval = get_discogs($redis, $url);
        if ($retval) {
            if (isset($retval['results'][0]['title']) && $retval['results'][0]['title']) {
                $title = explode(' - ', trim(preg_replace('![\s]+!', ' ', $retval['results'][0]['title'])), 2);
                if (isset($title[0]) && isset($title[1])) {
                    if (!$info['artist']) {
                        $info['artist'] = ucwords($title[0]);
                        $info['albumartist'] = ucwords(metadataStringClean($info['artist'], 'artist'));
                    }
                    if (!$info['album']) {
                        $info['album'] = ucwords($title[1]);
                    }
                    If (!$info['song']) {
                        $info['song'] = ucwords(trim(str_replace(trim(preg_replace('![\s\'"]+!', ' ', strtolower($title[0]))), '', $radiostringClean), ' -_'));
                    }
                }
            }
            if (isset($retval['results'][0]['cover_image']) && $retval['results'][0]['cover_image']) {
                // album art is filled, use it and save the details
                $info['album_arturl_large'] = trim($retval['results'][0]['cover_image']);
                $info['album_arturl_medium'] = trim($retval['results'][0]['cover_image']);
                $info['album_arturl_small'] = trim($retval['results'][0]['cover_image']);
            }
            if (isset($retval['results'][0]['thumb']) && $retval['results'][0]['thumb']) {
                // album art is filled, use it and save the details
                $info['album_arturl_small'] = trim($retval['results'][0]['thumb']);
                if (!$info['album_arturl_large']) {
                    $info['album_arturl_large'] = trim($retval['results'][0]['thumb']);
                    $info['album_arturl_medium'] = trim($retval['results'][0]['thumb']);
                }
            }
        }
    }
    if ($redis->get('activePlayer') != 'MPD') {
        // no longer MPD, just return with the current information
        return $info;
    }
    // use music brainz to determine the release (album) and album_mbid (it could also be a single)
    if ($noRadioCache && !$info['album']) {
        if ($info['song_mbid']) {
            // use musicbrainz to pick up the album ablum using
            // curl -s -f --connect-timeout 5 -m 10 --retry 2 https://musicbrainz.org/ws/2/recording/28734584-3a00-4072-8e09-dc5c40c0d50a?limit=1&inc=releases+artists+tags&media-format=CD&type=album|single&fmt=json
            $url = 'https://musicbrainz.org/ws/2/recording/'.$info['song_mbid'].'?limit=1&inc=releases+artists+tags&media-format=CD&type=album|single&fmt=json';
            $retval = get_musicBrainz($redis, $url);
            if ($retval) {
                if (isset($retval['releases'][0]['id']) && $retval['releases'][0]['id']) {
                    $info['album_mbid'] = $retval['releases'][0]['id'];
                    if (isset($retval['releases'][0]['title']) && $retval['releases'][0]['title']) {
                        $info['album'] = $retval['releases'][0]['title'];
                    }
                } else if (isset($retval['releases'][1]['id']) && $retval['releases'][1]['id']) {
                    $info['album_mbid'] = $retval['releases'][1]['id'];
                    if (isset($retval['releases'][1]['title']) && $retval['releases'][1]['title']) {
                        $info['album'] = $retval['releases'][1]['title'];
                    }
                } else if (isset($retval['releases'][2]['id']) && $retval['releases'][2]['id']) {
                    $info['album_mbid'] = $retval['releases'][2]['id'];
                    if (isset($retval['releases'][2]['title']) && $retval['releases'][2]['title']) {
                        $info['album'] = $retval['releases'][2]['title'];
                    }
                } else if (isset($retval['releases'][0]['title']) && $retval['releases'][0]['title']) {
                    $info['album'] = $retval['releases'][0]['title'];
                } else if (isset($retval['releases'][1]['title']) && $retval['releases'][1]['title']) {
                    $info['album'] = $retval['releases'][1]['title'];
                } else if (isset($retval['releases'][2]['title']) && $retval['releases'][2]['title']) {
                    $info['album'] = $retval['releases'][2]['title'];
                }
                if (isset($retval['artist-credit'][0]['artist']['id']) && $retval['artist-credit'][0]['artist']['id']) {
                    // music brainz artist ID is filled, save it
                    $info['artist_mbid'] = trim($retval['artist-credit'][0]['artist']['id']);
                    if (isset($retval['artist-credit'][0]['artist']['name']) && $retval['artist-credit'][0]['artist']['name']) {
                        $info['artist'] = ucfirst($retval['artist-credit'][0]['artist']['name']);
                        $info['albumartist'] = ucfirst(metadataStringClean($info['artist'], 'artist'));
                    }
                } else {
                    if (isset($retval['artist-credit'][0]['artist']['name']) && $retval['artist-credit'][0]['artist']['name']) {
                        $info['albumartist'] = ucfirst(metadataStringClean($retval['artist-credit'][0]['artist']['name'], 'artist'));
                    }
                }
            }
        } else {
            // use musicbrainz to pick up the album name and art using
            // curl -s -f --connect-timeout 5 -m 10 --retry 2 https://musicbrainz.org/ws/2/recording/?query=annie+lennox+-+why&limit=1&inc=releases+artists+tags&media-format=CD&fmt=json
            $url = 'https://musicbrainz.org/ws/2/recording/?query='.urlClean($radiostringClean).'&limit=1&inc=releases+artists+tags&media-format=CD&fmt=json';
            $retval = get_musicBrainz($redis, $url);
            if ($retval) {
                if (isset($retval['recordings'][0]['releases'][0]['id']) && $retval['recordings'][0]['releases'][0]['id']) {
                    $info['album_mbid'] = $retval['recordings'][0]['releases'][0]['id'];
                }
                if (isset($retval['recordings'][0]['releases'][0]['title']) && $retval['recordings'][0]['releases'][0]['title']) {
                    $info['album'] = $retval['recordings'][0]['releases'][0]['title'];
                }
                if (isset($retval['recordings'][0]['artist_credit'][0]['artist']['id']) && $retval['recordings'][0]['artist_credit'][0]['artist']['id']) {
                    // music brainz artist ID is filled, save it
                    $info['artist_mbid'] = $retval['recordings'][0]['artist_credit'][0]['artist']['id'];
                    if (isset($retval['recordings'][0]['artist_credit'][0]['artist']['name']) && $retval['recordings'][0]['artist_credit'][0]['artist']['name']) {
                        $info['artist'] = ucfirst($retval['recordings'][0]['artist_credit'][0]['artist']['name']);
                        $info['albumartist'] = ucfirst(metadataStringClean($info['artist'], 'artist'));
                    }
                } else if (isset($retval['recordings'][0]['artist_credit'][0]['artist']['name']) && $retval['recordings'][0]['artist_credit'][0]['artist']['name']) {
                    $info['albumartist'] = ucfirst(metadataStringClean($retval['recordings'][0]['artist_credit'][0]['artist']['name'], 'artist'));
                }
            }
        }
    }
    if ($redis->get('activePlayer') != 'MPD') {
        // no longer MPD, just return with the current information
        return $info;
    }

    $retval = get_artistInfo($redis, $info);
    if ($retval) {
        $info = array_merge($info, $retval);
    }
    if ($redis->get('activePlayer') != 'MPD') {
        // no longer MPD, just return with the current information
        return $info;
    }
    $retval = get_songInfo($redis, $info);
    if ($retval) {
        $info = array_merge($info, $retval);
    }
    if ($redis->get('activePlayer') != 'MPD') {
        // no longer MPD, just return with the current information
        return $info;
    }
    $retval = get_albumInfo($redis, $info);
    if ($retval) {
        $info = array_merge($info, $retval);
    }

    if ($info['webradiostring'] && $info['webradiostring_filename']) {
        $infoCache = array();
        foreach ($toCacheInfoFields as $toCacheInfoField) {
            $infoCache[$toCacheInfoField] = trim($info[$toCacheInfoField]);
        }
        if ($infoCache['webradiostring_filename']) {
            $fileName = $artDir.'/'.$infoCache['webradiostring_filename'].'.radio';
            file_put_contents($fileName, json_encode($infoCache)."\n");
        }
    }
    return $info;
}


// function which returns the artist image url artist information, the song lyrics and the album image URL as an array for a MPD song
function wrk_get_mpd_art($redis, $artist, $album, $song, $file)
// this retrieves the formation from cache files containing the previously retrieved information
{
    $info = array();
    $info['artist_album_song_filename'] = format_artist_album_song_file_name($artist, $album, $song, $file);
    $artDir = rtrim(trim($redis->get('albumart_image_dir')), '/');
    if ($info['webradiostring_filename']) {
        $infoCache = array();
        $fileName = $artDir.'/'.$info['artist_album_song_filename'].'.mpd';
        clearstatcache(true, $fileName);
        if (file_exists($fileName)) {
            $infoCache = json_decode(trim(file_get_contents($fileName)), true);
            if ($fileName === $infoCache['artist_album_song_filename']) {
                // update the file time stamp and use it
                touch($fileName);
                foreach ($infoCache as $key => $value) {
                    if (trim($value) != '') {
                        $info[$key] = trim($value);
                    }
                }
            } else {
                return 0;
            }
            unset($infoCache);
        } else {
            return 0;
        }
    } else {
        return 0;
    }
    if ($info['artist_filename']) {
        // an artist cache filename is set
        $retval = get_artistInfo($redis, $info);
        if ($retval) {
            $info = array_merge($info, $retval);
        }
    }
    if ($info['song_filename']) {
        // a  song cache filename is set
        $retval = get_songInfo($redis, $info);
        if ($retval) {
            $info = array_merge($info, $retval);
        }
    }
    if (!$info['album_arturl_local'] && $info['album_filename']) {
        // local sources albumart url not set and a album cache filename is set
        $retval = get_albumInfo($redis, $info);
        if ($retval) {
            $info = array_merge($info, $retval);
        }
    }

    return $info;
}

// function to return a string between two delimiters
function get_between_data($string, $start='', $end='', $occurrence=1)
// $start can be blank/null, then occurrence is ignored, selection is from the first position in $string
// $end can be blank/null, then selection to end of string
// when $start and $end are specified but no match if found a zero length string is returned
// when $start and $end are unspecified $string is returned
// when $occurrence is set (default = 1) it is used to match n'th occurrence of $start, only then is searched for an occurrence of $end
{
    if ($start == '') {
        $substr_data = $string;
    } else {
        if ( !is_numeric($occurrence) ) {
            $substr_data = "";
        } else {
            if ( $occurrence < 1 ) {
                $substr_data = "";
            } else {
                $substr_data = $string;
            }
        }
        for ($i = 1; $i <= $occurrence; $i++) {
            $pos_start = stripos($substr_data, $start);
            if ($pos_start === false) {
                $substr_data = "";
                break;
            }
            else {
                $substr_data = substr($substr_data, $pos_start+strlen($start));
            }
        }
    }
    if ($end != '') {
        $pos_end = stripos($substr_data, $end);
        if ($pos_end === false) {
            $substr_data = "";
        }
        else {
            $substr_data = trim(substr($substr_data, 0, $pos_end));
        }
    }
    return $substr_data;
}

// function to restart and resize opcache when full
function check_opcache($redis)
{
    if (wrk_opcache($redis, 'isfull')) {
        // opcache is full (or more the 90% full), try to add 8MB
        if (is_firstTime($redis, 'opcachefull')) {
            // this is the first time since reboot, add 8Mb to the opcache
            wrk_opcache($redis, 'add8mb');
        }
        // and do a reload, this will activate the extra memory (if applicable) and clear the cache
        wrk_opcache($redis, 'reload');
    }
}

// function to initialise the playback array
function initialise_playback_array($redis, $playerType = 'MPD')
// $playerType can have the following values: 'MPD' (default), 'Airplay', 'Spotify connect', 'Bluetooth'
{
    $artUrl = trim($redis->get('albumart_image_url_dir'), " \n\r\t\v\0/");
    $playerTypeLower = strtolower($playerType);
    $status = array();
    $status['OK'] = null;
    $status['actPlayer'] = $playerType;
    $status['audio'] = '44100:16:2';
    $status['audio_channels'] = 'Stereo';
    $status['audio_sample_depth'] = '16';
    $status['audio_sample_rate'] = '44.1';
    $status['bigArtURL'] = $artUrl.'/black.png';
    $status['bitrate'] = '320';
    $status['changed'] = '';
    $status['consume'] = '0';
    $status['currentalbum'] = '';
    $status['currentalbumartist'] = $playerType;
    $status['currentartist'] = $playerType;
    $status['currentcomposer'] = '';
    $status['currentsong'] = '';
    $status['duration'] = '0';
    $status['elapsed'] = '0';
    $status['file'] = '';
    $status['fileext'] = '';
    if ($playerTypeLower === 'mpd') {
        $status['mainArtURL'] = $artUrl.'/none.png';
    } else {
        $status['mainArtURL'] = $artUrl.'/'.str_replace(' ', '-', $playerTypeLower).'.png';
    }
    $status['mixrampdb'] = '0';
    $status['nextsong'] = '0';
    $status['nextsongid'] = '0';
    $status['partition'] = 'default';
    $status['playlist'] = '';
    $status['playlistlength'] = '';
    $status['radio'] = false;
    $status['radioname'] = '';
    $status['random'] = '0';
    $status['repeat'] = '0';
    $status['single'] = '0';
    $status['smallArtURL'] = $artUrl.'/black.png';
    $status['song'] = '0';
    $status['song_percent'] = '0';
    $status['songid'] = '0';
    $status['state'] = 'stop';
    $status['time'] = '0';
    $status['song_lyrics'] = ' ';
    $status['artist_bio_summary'] = ' ';
    $status['artist_similar'] = ' ';
    $volume = intval($redis->get('lastmpdvolume'));
    if ($redis->get('volume') && strlen($volume)) {
        $status['volume'] = $volume;
    } else {
        $status['volume'] = '0';
    }
    $xfade = intval($redis->hGet('mpdconf', 'crossfade'));
    if (strlen($xfade)) {
        $status['xfade'] = $xfade;
    } else {
        $status['xfade'] = '0';
    }
    // save JSON response for extensions
    $redis->set('act_player_info', json_encode($status));
    ui_render('playback', json_encode($status));
    sysCmd('curl -s -X GET http://localhost/command/?cmd=renderui');
    sysCmdAsync($redis, '/srv/http/command/ui_update_async', 0);
    return $status;
}

// function to add udev rules which which will become invalid on reboot
function add_udev_rules($rulesFileName)
// this function will activate a udev rules file
// after reboot the the udev rules file will become inactive
// Parameter 1 is the full file path of the udev rules file to be activated, this
//  file must not be located in /etc/udev/rules.d or /tmp
{
    clearstatcache(true, $rulesFileName);
    if (!file_exists($rulesFileName)) {
        // invalid parameter
        return false;
    }
    $udevRulesFileName = '/etc/udev/rules.d/'.basename($rulesFileName);
    clearstatcache(true, $udevRulesFileName);
    if (file_exists($udevRulesFileName) || is_link($udevRulesFileName)) {
        // delete the file of link
        unlink($udevRulesFileName);
    }
    $tmpRulesFileName = '/tmp/'.basename($rulesFileName);
    if (file_exists($tmpRulesFileName)) {
        // delete the file of link
        unlink($tmpRulesFileName);
    }
    copy($rulesFileName, $tmpRulesFileName);
    symlink($tmpRulesFileName, $udevRulesFileName);
    sysCmd('sync');
}

// function to retrieve Spotify metadata from the spotifyd journal based on a track ID
function wrk_getSpotifydJournalMetadata($redis, $track_id)
// track ID is returned by programs like spotifyd
// this routine examines the spotifyd journal file for a subset of required information
//  it us used as a sort of failover when the screen scraping function wrk_getSpotifyMetadata()
//  has timed out
// the results are returned in an array containing:
//  array['artist'] > artist name
//  array['albumartist'] > album name
//  array['album'] > album name
//  array['title'] > song title
//  array['albumart_url'] > URL pointing to the album art
//  array['duration_in_sec'] > the track duration in seconds
//  array['year'] > release year
//  array['date'] > today's date
// all are returned with a default value except:
//  array['title'] > song title
//  array['duration_in_sec'] > the track duration in seconds
//  array['date'] > today's date
{
    // get the album art directory and url dir
    $artDir = rtrim(trim($redis->get('albumart_image_dir')), '/');
    $artUrl = trim($redis->get('albumart_image_url_dir'), " \n\r\t\v\0/");
    // set the variables to default values
    $retval = array();
    $retval['artist'] = 'Spotify';
    $retval['albumartist'] = 'Spotify';
    $retval['album'] = 'Spotify Connect';
    $retval['title'] = '-';
    $retval['albumart_url'] = $artUrl.'/spotify-connect.png';
    $retval['duration_in_sec'] = '';
    $retval['year'] = '';
    $retval['date'] = date("Ymd");
    //
    $cnt = 5;
    while ($cnt-- >= 0) {
        // title
        // look for lines in the journal containing the $track_id and the text 'Loading <', if
        //  there are more than one, the first one is fine
        if ($retval['title'] == '-') {
            $spotifydJournalData = sysCmd("journalctl -u spotifyd | grep -i '".$track_id."' | grep -i 'loading <'")[0];
            if (isset($spotifydJournalData) && $spotifydJournalData) {
                // the title is between the strings 'ing <' and '> with'
                $title = get_between_data($spotifydJournalData, 'ing <', '> with');
                if (isset($title) && $title) {
                    // title found
                    $retval['title'] = $title;
                }
            }
        }
        // duration_in_seconds
        // look for lines in the journal containing the $retval['title'] and the text 'ms) loaded', if
        //  there are more than one, the first one is fine
        if ($retval['title'] != '-') {
            $spotifydJournalData = sysCmd("journalctl -u spotifyd | grep -i '<".$retval['title'].">' | grep -i 'ms) loaded'")[0];
            if (isset($spotifydJournalData) && $spotifydJournalData) {
                // the duration in ms is between the strings '(' and ' ms)'
                $duration_in_ms = trim(get_between_data($spotifydJournalData, '(', 'ms)'));
                if (isset($duration_in_ms)) {
                    // duration found
                    $retval['duration_in_sec'] = round(intval($duration_in_ms) / 1000);
                    // found all the available information, break the loop
                    break;
                }
            }
        }
        sleep(4);
    }
    return $retval;
}

// function to retrieve Spotify metadata based on a track ID
function wrk_getSpotifyMetadata($redis, $track_id)
// track ID is returned by programs like spotifyd
// this routine uses open Spotify URL's, and the returned web pages are screen-scraped
//  when too many calls are made to spotify it will block the calling URL, this is a sort of time-out
//  after a time-out failure we suspend calling spotify for an hour
// the results are returned in an array containing:
//  array['artist'] > artist name(s)
//  array['albumartist'] > artist name
//  array['album'] > album name
//  array['title'] > song title
//  array['albumart_url'] > URL pointing to the album art
//  array['duration_in_sec'] > the track duration in seconds
//  array['year'] > release year
// the results which can be returned using this method are limited, see here for a more advanced
//  method using the Spotify API: https://github.com/Spotifyd/spotifyd/wiki/User-supplied-scripts
// other things which are available and could be added are:
//  array['track.description'] > the track description (from track data)
//  array['album:track'] > the track number (from track data)
//  array['release_date'] > release date of the track (from track data)
//  array['album.description'] > the album description including single/album info (from album data)
//  array['artist.description'] > the number of monthly listeners (from artist data)
{
    // debug
    // echo "Track ID: '$track_id'\n";
    runelog('[wrk_getSpotifyMetadata] Track ID: '.$track_id);
    // get the album art directory and url dir
    $artDir = rtrim(trim($redis->get('albumart_image_dir')), '/');
    $artUrl = trim($redis->get('albumart_image_url_dir'), " \n\r\t\v\0/");
    // set the variables to default values
    $retval = array();
    $retval['artist'] = 'Spotify';
    $retval['albumartist'] = 'Spotify';
    $retval['album'] = 'Spotify Connect';
    $retval['title'] = '-';
    $retval['albumart_url'] = $artUrl.'/none.png';
    $retval['duration_in_sec'] = '';
    $retval['year'] = '';
    $retval['date'] = date("Ymd");
    // it there is a cache file, use it
    $cacheFile = $artDir.'/'.trim($track_id).'.spotify';
    clearstatcache(true, $cacheFile);
    if (file_exists($cacheFile)) {
        // the information for this track ID has been cached, use it
        $cache = json_decode(trim(file_get_contents($cacheFile)) , true);
        // update the file date stamp
        touch($cacheFile);
        if (!isset($cache['date']) || ($cache['date'] == date("Ymd"))) {
            // when 'date' is not set the information is complete, just return it
            // when date is set and it is from today, it may not be complete, but still just return it
            return $cache;
        } else {
            $retval = array_merge($retval, $cache);
        }
    }
    if ($redis->hGet('spotifyconnect', 'metadata_timeout_restart_time') >= microtime(true)) {
        $timeout = true;
    } else {
        $timeout = false;
    }
    // just return when metadata is disabled or the when screen scraper has timed out
    if (!$redis->hGet('spotifyconnect', 'metadata_enabled') || $timeout) {
        // don't save any information, just ruturn the defaults
        return $retval;
    }
    // when the API client-id and secret are set use the Spotify API method
    if (($redis->hExists('spotifyconnect', 'api_id') && $redis->hGet('spotifyconnect', 'api_id')
            && $redis->hExists('spotifyconnect', 'api_secret') && $redis->hGet('spotifyconnect', 'api_secret'))
            || ($redis->hExists('spotifyconnect', 'api_token') && $redis->hGet('spotifyconnect', 'api_token'))) {
        // API client-id and secret are set or the apt token is set
        $retval = wrk_getSpotifyMetadataAdvanced($redis, $track_id);
        if ($retval) {
            // on success return the value
            return $retval;
        }
    }
    // otherwise use screen scraping
    if ($retval['title'] == '-') {
        // still set to default, so try retreving information
        // curl -s 'https://open.spotify.com/track/<TRACK_ID>' | sed 's/<meta/\n<meta/g' | sed 's/></>\n</g' | grep -iE 'og:title|og:image|og:description|music:duration|music:album'
        $command = 'curl -s -f --connect-timeout 5 -m 10 --retry 2 '."'".'https://open.spotify.com/track/'.$track_id."'".' | sed '."'".'s/<meta/\n<meta/g'."'".' | sed '."'".'s/></>\n</g'."'".' | grep -iE '."'".'og:title|og:image|og:description|music:duration|music:album'."'";
        // debug line // $command = 'curl -s -f --connect-timeout 5 -m 10 --retry 2 '."'".'https://open.spotify.com/track/'.$track_id."'".' | sed '."'".'s/<meta/\n<meta/g'."'".' | sed '."'".'s/></>\n</g'."'".' | grep -iE '."'".'og:|music:'."'".' | grep -vi country | grep -vi canonical';
        //
        $command = 'curl -s '."'".'https://open.spotify.com/track/'.$track_id."'".' | sed '."'".'s/<meta/\n<meta/g'."'".' | sed '."'".'s/></>\n</g'."'".' | grep -iE '."'".'og:title|og:image|og:description|music:duration|music:album'."'";
        runelog('[wrk_getSpotifyMetadata] track command:', $command);
        $trackInfoLines = sysCmd($command);
        $timeout = true;
        foreach ($trackInfoLines as $workline) {
            // replace all combinations of single or multiple tab, space, <cr> or <lf> with a single space
            $line = preg_replace('/[\t\n\r\s]+/', ' ', $workline);
            // then strip the html out of the response
            $line = preg_replace('/\<[\s]*meta property[\s]*="/', '', $line);
            $line = preg_replace('/\<[\s]*meta name[\s]*="/', '', $line);
            $line = preg_replace('/"[\s]*content[\s]*=[\s]*/', '=', $line);
            $line = preg_replace('!"[\s]*/[\s]*\>!', '', $line);
            $line = preg_replace('/"[\s]*\>/', '', $line);
            $line = preg_replace('/[\s]*"[\s]*/', '', $line);
            $line = trim($line);
            if (!strpos(' '.$line, '=')) {
                continue;
            }
            // debug
            // echo "Line: ".$line."\n";
            runelog('[wrk_getSpotifyMetadata] Line: '.$line);
            // result is <identifier>=<value>
            $lineparts = explode('=', $line, 2);
            if ($lineparts[0] === 'og:title') {
                $retval['title'] = trim($lineparts[1]);
                runelog('[wrk_getSpotifyMetadata] track title:', $retval['title']);
                $timeout = false;
            } elseif ($lineparts[0] === 'og:image') {
                $retval['albumart_url'] = trim($lineparts[1]);
                runelog('[wrk_getSpotifyMetadata] track albumart_url:', $retval['albumart_url']);
            } elseif ($lineparts[0] === 'og:description') {
                $description = trim($lineparts[1]);
                $retval['artist'] = trim(get_between_data($description, '', ' · '));
                if (strpos(' '.$retval['artist'], '، ')) {
                    $retval['albumartist'] = trim(get_between_data($retval['artist'], '', '، '));
                    $retval['artist'] = str_replace('،', ',', $retval['artist']);
                } else if (strpos(' '.$retval['artist'], ', ')) {
                    $retval['albumartist'] = trim(get_between_data($retval['artist'], '', ', '));
                } else {
                    $retval['albumartist'] = $retval['artist'];
                }
                $retval['year'] = substr($description, -4);
                runelog('[wrk_getSpotifyMetadata] description:', $description);
                runelog('[wrk_getSpotifyMetadata] albumartist:', $retval['albumartist']);
                runelog('[wrk_getSpotifyMetadata] artist:', $retval['artist']);
                runelog('[wrk_getSpotifyMetadata] year:', $retval['year']);
            } elseif ($lineparts[0] === 'music:duration') {
                $retval['duration_in_sec'] = trim($lineparts[1]);
                runelog('[wrk_getSpotifyMetadata] track duration_in_sec:', $retval['duration_in_sec']);
            } elseif ($lineparts[0] === 'music:album') {
                $retval['album_url'] = trim($lineparts[1]);
                runelog('[wrk_getSpotifyMetadata] track album_url:', $retval['album_url']);
            }
            unset($lineparts);
        }
        unset($trackInfoLines, $workline, $line, $description);
        if ($timeout) {
            // timeout for an hour (= current timestamp + 60x60 seconds)
            $redis->hSet('spotifyconnect', 'metadata_timeout_restart_time', microtime(true) + (60*60));
        } else {
            // cache the track ID information for the next time
            file_put_contents($cacheFile, json_encode($retval)."\n");
        }
        return $retval;
    }
    //
    // get the album name
    if (!isset($retval['album_url']) || !$retval['album_url']) {
        runelog('[wrk_getSpotifyMetadata] ALBUM_URL:', 'Empty');
    } else if ($retval['date'] == date("Ymd")) {
        // do nothing
    } else {
        // album name is still the default
        runelog('[wrk_getSpotifyMetadata] ALBUM_URL:', $retval['album_url']);
        // curl -s '<ALBUM_URL>' | head -c 2000 | sed 's/<meta/\n<meta/g' | sed 's/></>\n</g' | grep -i 'og:title'
        $command = 'curl -s -f --connect-timeout 5 -m 10 --retry 2 '."'".$retval['album_url']."'".' | head -c 2000 | sed '."'".'s/<meta/\n<meta/g'."'".' | sed '."'".'s/></>\n</g'."'".' | grep -iE '."'".'og:title|og:description'."'";
        // debug line // $command = 'curl -s -f --connect-timeout 5 -m 10 --retry 2 '."'".$retval['album_url']."'".' | head -c 2000 | sed '."'".'s/<meta/\n<meta/g'."'".' | sed '."'".'s/></>\n</g'."'".' | grep -vi country | grep -vi canonical';
        // $command = 'curl -s '."'".$album_url."'".' | sed '."'".'s/<meta/\n<meta/g'."'".' | sed '."'".'s/></>\n</g'."'".' | grep -iE '."'".'og:title|og:description'."'";
        runelog('[wrk_getSpotifyMetadata] album command:', $command);
        $albumInfoLines = sysCmd($command);
        $timeout = true;
        foreach ($albumInfoLines as $workline) {
            // replace all combinations of single or multiple tab, space, <cr> or <lf> with a single space
            $line = preg_replace('/[\t\n\r\s]+/', ' ', $workline);
            // then strip the html out of the response
            $line = preg_replace('/\<[\s]*meta property[\s]*="/', '', $line);
            $line = preg_replace('/\<[\s]*meta name[\s]*="/', '', $line);
            $line = preg_replace('/"[\s]*content[\s]*=[\s]*/', '=', $line);
            $line = preg_replace('!"[\s]*/[\s]*\>!', '', $line);
            $line = preg_replace('/"[\s]*\>/', '', $line);
            $line = preg_replace('/[\s]*"[\s]*/', '', $line);
            $line = trim($line);
            if (!strpos(' '.$line, '=')) {
                continue;
            }
            // debug
            // echo "Album line: ".$line."\n";
            runelog('[wrk_getSpotifyMetadata] Album line: '.$line);
            // result is <identifier>=<value>
            $lineparts = explode('=', $line);
            if ($lineparts[0] === 'og:title') {
                $retval['album'] = trim($lineparts[1]);
                runelog('[wrk_getSpotifyMetadata] album title:', $retval['album']);
                $timeout = false;
                unset($retval['album_url'], $retval['date']);
            } elseif ($lineparts[0] === 'og:description') {
                $description = trim($lineparts[1]);
                $retval['albumartist'] = trim(get_between_data($description, '', ' · '));
                runelog('[wrk_getSpotifyMetadata] description:', $description);
                runelog('[wrk_getSpotifyMetadata] albumartist:', $retval['albumartist']);
            }
            unset($lineparts);
        }
        if (isset($description) && strpos(' '.$description, ' · Single · ')) {
            $retval['album'] = trim($retval['album'].' (Single)');
        }
        unset($albumInfoLines, $workline, $line, $description);
        if ($timeout) {
            // timeout for an hour (= current timestamp + 60x60 seconds)
            $redis->hSet('spotifyconnect', 'metadata_timeout_restart_time', microtime(true) + (60*60));
        } else {
            // cache the track ID information for the next time
            file_put_contents($cacheFile, json_encode($retval)."\n");
        }
        return $retval;
    }
    return $retval;
}

// function to retrieve Spotify metadata based on a track ID via the Spotify API
function wrk_getSpotifyMetadataAdvanced($redis, $track_id)
// track ID is returned by programs like spotifyd
// this routine uses the Spotify API, which requires a registered API user ID and Secret
// the results are returned in an array containing:
//  array['artist'] > artist name(s)
//  array['albumartist'] > artist name
//  array['album'] > album name
//  array['title'] > song title
//  array['albumart_url'] > URL pointing to the album art
//  array['duration_in_sec'] > the track duration in seconds
//  array[''] > release year
{
    // get the album art directory and url dir
    $artDir = rtrim(trim($redis->get('albumart_image_dir')), '/');
    $artUrl = trim($redis->get('albumart_image_url_dir'), " \n\r\t\v\0/");
    // it there is a cache file, use it
    $cacheFile = $artDir.'/'.trim($track_id).'.spotify';
    clearstatcache(true, $cacheFile);
    if (file_exists($cacheFile)) {
        // the information for this track ID has been cached, use it
        $retval = json_decode(trim(file_get_contents($cacheFile)) , true);
        // update the file date stamp
        touch($cacheFile);
        if (!isset($retval['date'])) {
            // value set in this routine, not by screen scraping, just return the value
            return $retval;
        }
    }
    // get the API user ID, secret and token
    $apiUserID = $redis->hGet('spotifyconnect', 'api_id');
    $apiSecret = $redis->hGet('spotifyconnect', 'api_secret');
    if (!$redis->hExists('spotifyconnect', 'api_token') || !$redis->hGet('spotifyconnect', 'api_token')) {
        // no API token, get one
        $retval = implode(' ', sysCmd("curl -s -X 'POST' -u ".$apiUserID.':'.$apiSecret.' -d grant_type=client_credentials https://accounts.spotify.com/api/token'));
        $retval = json_decode($retval, true);
        if (isset($retval['access_token']) && $retval['access_token']) {
            // got an API token, save it
            $apiToken = $retval['access_token'];
            $redis->hSet('spotifyconnect', 'api_token', $apiToken);
        } else {
            // cant get an API token, invalid user ID and/or secret
            $apiToken = '';
        }
    } else {
        // we have a saved API token, use it
        $apiToken = $redis->hGet('spotifyconnect', 'api_token');
    }
    if ($apiToken) {
        // there is a an API token
        $command = "curl -s -X 'GET' https://api.spotify.com/v1/tracks/".$track_id." -H 'Accept: application/json' -H 'Content-Type: application/json' -H 'Authorization:Bearer ".$apiToken."'";
        $retval = implode(' ', sysCmd($command));
        $metadata = json_decode($retval, true);
        if (isset($metadata['error']['message']) && strpos($metadata['error']['message'], 'expired') ) {
            // token has expired, get a new one
            // other failures are not handled
            $retval = implode(' ', sysCmd("curl -s -X 'POST' -u ".$apiUserID.':'.$apiSecret.' -d grant_type=client_credentials https://accounts.spotify.com/api/token'));
            $retval = json_decode($retval, true);
            if (isset($retval['access_token']) && $retval['access_token']) {
                // we have a new token, save it
                $apiToken = $retval['access_token'];
                $redis->hSet('spotifyconnect', 'api_token', $apiToken);
            } else {
                // cant get an API token, invalid user ID and/or secret
                $apiToken = '';
            }
        } else {
            // cant get an API token, some other unknown error
            $apiToken = '';
        }
        // try again
        if ($apiToken) {
        $command = "curl -s -X 'GET' https://api.spotify.com/v1/tracks/".$track_id." -H 'Accept: application/json' -H 'Content-Type: application/json' -H 'Authorization:Bearer ".$apiToken."'";
            $retval = implode(' ', sysCmd($command));
            $metadata = json_decode($retval, true);
        }
    }
    $retval = array();
    if (isset($metadata['name'])) {
        // track name is valid, assume the rest is OK and use the results
        // there can be more artist names, string the first 4 together
        $retval['artist'] = '';
        for ($i = 0; $i <= 4; $i++) {
            $metadata['artists'][$i]['name'] = trim($metadata['artists'][$i]['name']);
            if (isset($metadata['artists'][$i]['name']) && $metadata['artists'][$i]['name']) {
                $retval['artist'] .= $metadata['artists'][$i]['name'].', ';
            }
        }
        // remove trailing commas and spaces
        $retval['artist'] = trim($retval['artist'], " ,");
        $retval['albumartist'] = $metadata['artists'][0]['name']; // just use the first artist name for albumartist
        $retval['album'] = $metadata['album']['name'];
        $retval['title'] = $metadata['name'];
        $retval['albumart_url'] = $metadata['album']['images'][1]['url']; // [0] = 640x640px, [1] = 300x300px, [2] = 64x64px
        $retval['duration_in_sec'] = round($metadata['duration_ms']/1000);
        $retval['year'] = substr($metadata['album']['release_date'], 0, 4);
        // extra information is available, but not used
        // $retval['release_date'] = $metadata['album']['release_date'];
        // $retval['total_tracks'] = $metadata['album']['total_tracks']; // total number of tracks on the album
        // $retval['type'] = $metadata['album']['type']; // album or single
        // $retval['disc_number'] = $metadata['disc_number'];
        // $retval['popularity'] = $metadata['popularity']; // scale unknown
        // $retval['track_number'] = $metadata['track_number']; // this songs track number
        // cache the track ID information for the next time
        file_put_contents($cacheFile, json_encode($retval)."\n");
    } else {
        // no or invalid data returned, use default values
        $retval['artist'] = 'Spotify';
        $retval['album'] = 'Spotify Connect';
        $retval['title'] = '-';
        $retval['albumart_url'] = $artUrl.'/none.png';
        $retval['duration_in_sec'] = '';
        $retval['year'] = '';
    }
    return $retval;
}

// get the average bit rate for a music file
function getMusicFileMatadata($redis, $fileName)
// returns the cached metadata for the music file
//  all MPD music files which are currently playing (should) have a cached metadata file
//  contents include:
//      ["albumartfile"] The album art file name
//      ["albumarturl"] The album art URL
//      ["avg_bit_rate"] (example content = 320000 or 944292.45134554)
//      ["bitrate_mode"] (example content = "cbr", "vbr")
//      ["bits_per_sample"] (example content = 0)
//      ["channels"] (example content = 2)
//      ["date"] The publication year
//      ["encoder_options"] (example content = "CBR320")
//      ["encoder_version"] (example content = "LAME3.92")
//      ["format_name"] (example content = "MP3")
//      ["format_name"] Format of the music file (e.g. FLAC, MP3)
//      ["playing_time"] (example content = 380.47199999999998)
//      ["sample_rate"] (example content = 48000)
//
{
    clearstatcache(true, $fileName);
    if (!file_exists($fileName)) {
        return false;
    }
    $metadata = json_decode(file_get_contents($fileName), true);
    // remove the album art file name and URL when no longer valid
    if (isset($metadata['albumartfile']) && $metadata['albumartfile']) {
        if (strpos(' '.$metadata['albumartfile'],'http') != 1) {
            // its not a link to an external source
            clearstatcache(true, $metadata['albumartfile']);
            if (!file_exists($metadata['albumartfile'])) {
                unset($metadata['albumartfile'], $metadata['albumarturl']);
            }
        }
    } else {
        unset($metadata['albumartfile'], $metadata['albumarturl']);
    }
    return $metadata;
}

// get the value of the first matching key in a single or multidimensional array
function search_array_keys($myArray, $search, $caseInsensative=false, $skipEmpty=false, $checkValueArrayZero=false)
// returns the first non-null/non-false value of an array node when its key matching the search string
// it really only works well returning strings, null and boolean values give incorrect results
// no match returns false, a match with a null or boolean false value also return false
{
    if ($caseInsensative) {
        $search = strtolower($search);
    }
    foreach ($myArray as $key => $value) {
        if ($caseInsensative) {
            $key = strtolower($key);
        }
        if ($key == $search) {
            // match
            if ($checkValueArrayZero && is_array($value) && isset($value[0]) && !is_array($value[0])) {
                // check value[0], value is an array with a [0] element which is not an array, use the value of [0]
                $value = $value[0];
            }
            if ($skipEmpty && (is_array($value) || !strlen(trim($value)))) {
                // empty values are skipped and value is empty or an array, do nothing
            } else {
                if (!is_array($value)) {
                    // value is not an array, but may be empty, return it anyway
                    return $value;
                }
            }
        }
        if (is_array($value)) {
            $retval = search_array_keys($value, $search, $caseInsensative, $skipEmpty, $checkValueArrayZero);
            if ($retval) {
                return $retval;
            }
        }
    }
    return false;
}

// sets the default alsa card and the bluealsa ouput card, based on the card name
function set_alsa_default_card($redis, $cardName=null)
{
    $alsaFileName = '/etc/asound.conf';
    $bluealsaFileName = '/etc/default/bluealsa-aplay';
    $ao = $redis->get('ao');
    if (!isset($ao) || !$ao) {
        $ao = $redis->get('ao_default');
    }
    if (!isset($cardName) || !$cardName) {
        $cardName = $ao;
    }
    if (!isset($cardName) || !$cardName) {
        // no card defined
        return;
    }
    $acard = json_decode($redis->hGet('acards', $cardName), true);
    if (!isset($acard['device']) || !$acard['device']) {
        $acard = json_decode($redis->hGet('acards', $ao), true);
    }
    if (!isset($acard['device']) || !$acard['device']) {
        // invalid card
        echo "Invalid ao card: '$cardName', '$ao'\n";
        $aoTest = $redis->get('ao');
        $ao_default = $redis->get('ao_default');
        if (($aoTest == $cardName) || ($aoTest == $ao)) {
            $redis->set('ao', '');
        }
        if (($ao_default == $cardName) || ($ao_default == $ao)) {
            $redis->set('ao_default', '');
        }
        return;
    }
    //
    $device = $acard['device'];
    $cardNumber = get_between_data($device, ':', ',');
    if (isset($acard['mixer_device']) && isset($acard['mixer_control']) && $acard['mixer_device'] && $acard['mixer_control']) {
        $mixerInfo = ' --mixer-device='.$acard['mixer_device'].' --mixer-name='.$acard['mixer_control'];
    } else {
        $mixerInfo = '';
    }
    //
    if (!isset($cardNumber) || !is_numeric($cardNumber)) {
        // card number is not set, remove entries from /etc/asound.conf
        clearstatcache(true, $alsaFileName);
        if (file_exists($alsaFileName)) {
            // file exists
            sysCmd("sed -i '/defaults.pcm.card/d' '".$alsaFileName."'");
            sysCmd("sed -i '/defaults.ctl.card/d' '".$alsaFileName."'");
        } else {
            return;
        }
    } else {
        // card number is set modify/add entries to /etc/asound.conf
        clearstatcache(true, $alsaFileName);
        if (file_exists($alsaFileName)) {
            // file exists, remove then add the default lines
            sysCmd("sed -i '/defaults.pcm.card/d' '".$alsaFileName."'");
            sysCmd("sed -i '/defaults.ctl.card/d' '".$alsaFileName."'");
            sysCmd('echo defaults.pcm.card '.$cardNumber." >> '".$alsaFileName."'");
            sysCmd('echo defaults.ctl.card '.$cardNumber." >> '".$alsaFileName."'");
        } else {
            // no file, create it an then add the lines
            touch($alsaFileName);
            sysCmd('echo defaults.pcm.card '.$cardNumber." >> '".$alsaFileName."'");
            sysCmd('echo defaults.ctl.card '.$cardNumber." >> '".$alsaFileName."'");
        }
    }
    // also configure bluealsa to point at the default card
    sysCmd('echo "OPTIONS=\"--pcm='.$device.$mixerInfo.'\"" > "'.$bluealsaFileName.'"');
    // force alsa to reload all card profiles (should not be required, but some USB audio devices seem to need it)
    sysCmd('alsactl kill rescan');
    // restart bluealsa-aplay if it is running
    sysCmd('pgrep bluealsa-aplay && systemctl restart bluealsa-aplay');
}

// sets log file privileges to RW and deletes any log files with a zero size
function wrk_fixlogs()
{
    // change the privileges to RW for all users
    sysCmd('chmod 666 /var/log/runeaudio/*');
    // delete zero size files
    sysCmd('find /var/log/runeaudio/ -type f -size 0 -exec rm {} \;');
}

// configures, enables and disables llmnrd
function wrk_llmnrd($redis)
{
    // depending on llmnrdipv6 set up llmnrd
    $ipv6Status = trim(sysCmd('grep -ic "llmnrd -6" /etc/systemd/system/llmnrd.service')[0]);
    $ipv6Setting = $redis->get('llmnrdipv6');
    if ($ipv6Setting && !$ipv6Status) {
        // IPv6 is on and is not specified in the unit service file
        // stop llmnrd
        sysCmd('systemctl stop llmnrd');
        // change the unit service file
        sysCmd("sed -i '/^ExecStart=/c\ExecStart=\/usr\/bin\/llmnrd -6' /etc/systemd/system/llmnrd.service");
        // reload the systend daemon
        sysCmd('systemctl daemon-reload');
    } else if (!$ipv6Setting && $ipv6Status) {
        // IPv6 is off but is specified in the unit service file
        // stop llmnrd
        sysCmd('systemctl stop llmnrd');
        // change the unit service file
        sysCmd("sed -i '/^ExecStart=/c\ExecStart=\/usr\/bin\/llmnrd' /etc/systemd/system/llmnrd.service");
        // reload the systend daemon
        sysCmd('systemctl daemon-reload');
    }
    // depending on llmnrdonoff enable/disable start/stop llmnrd
    if ($redis->get('llmnrdonoff')) {
        // llmnrd is enabled
        // enable llmnrd to automatically start on boot and start it
        sysCmd('systemctl enable llmnrd; systemctl start llmnrd');
    } else {
        // llmnrd is disabled
        // disable llmnrd to prevent starting on boot and stop it
        sysCmd('systemctl disable llmnrd; systemctl stop llmnrd');
    }
}

// function to set up and configure Bluetooth input and output
function wrk_btcfg($redis, $action, $param = null, $jobID = null)
// $action has the values:
//  enable, disable, reset, restart_bluealsa_aplay, clear, input_connect, output_list, pair, unpair, connect,
//  disconnect, disconnect_sources, disconnect_sinks, trust, untrust, block, unblock, forget, remove_bt_acards,
//  correct_bt_ao, set_volume, status, status_async, status_async_now, check_bt_mpd_output, bt_scan_output, config,
//  quality_options, auto_volume
// the function returns true or false, except when $action = status, in which case an array containing all bluetooth device statuses is returned
// $param optionally contains a the MAC-address of the device, where:
//  $param is valid for: pair, cancel-pairing, connect, disconnect, output_connect, trust, untrust
//  $param is compulsory for: pair, connect
//  when $param is not supplied all devices will be processed
//  $jobID is always optional, is only relevant for some actions, when supplied the ID will be released quicker
{
    $retval = true;
    switch ($action) {
        case 'enable':
            // enable Bluetooth, a reboot is required if it was disabled
            if (isset($param) && ($param = 'async')) {
                sysCmdAsync($redis, 'nice --adjustment=10 /srv/http/command/bt_on.sh');
            } else {
                sysCmd('/srv/http/command/bt_on.sh');
            }
            break;
        case 'disable':
            // run the command file to disable Bluetooth, a reboot is required if it was enabled
            if (isset($param) && ($param = 'async')) {
                sysCmdAsync($redis, 'nice --adjustment=10 /srv/http/command/bt_off.sh');
            } else {
                sysCmd('/srv/http/command/bt_off.sh');
            }
            break;
        case 'reset':
            // disconnects all connected Bluetooth devices and restarts the Bluetooth services
            wrk_btcfg($redis, 'disconnect');
            wrk_btcfg($redis, 'disable');
            wrk_btcfg($redis, 'enable');
            break;
        case 'restart_bluealsa_aplay':
            // restarts bluealsa-aplay (when running) after changing parameters
            sysCmd('pgrep -x bluealsa-aplay && systemctl restart bluealsa-aplay');
            break;
        case 'clear':
            // remove all cached Bluetooth information
            wrk_btcfg($redis, 'remove_bt_acards');
            sysCmd('rm -r /var/lib/bluetooth/*');
            wrk_btcfg($redis, 'reset');
            break;
        case 'input_connect':
            // allows RuneAudio to be detectable as a Bluetooth device and allow a pair and connect from an input (source) device
            //  it is detectable, pair-able and connect-able  for 120 seconds
            sysCmd('systemctl stop bt_scan_output ; systemctl start bluetooth-agent');
            wrk_startPlayer($redis, "Bluetooth");
            break;
        case 'output_list':
            // scan for Bluetooth output devices
            sysCmd('systemctl stop bluetooth-agent ; systemctl start bt_scan_output');
            if ($redis->get('activePlayer') == 'Bluetooth') {
                wrk_stopPlayer($redis);
            }
            wrk_btcfg($redis, 'disconnect_sources');
            break;
        case 'pair':
            // pair a specified Bluetooth device
            if (!isset($param) || !$param) {
                $retval = false;
                break;
            }
            sysCmd('timeout 5 bluetoothctl pairable on');
            sysCmd('timeout 5 bluetoothctl pair '.$param);
            sysCmd('timeout 5 bluetoothctl pairable off');
            break;
        case 'unpair':
            // unpair (cancel pairing) of a specified Bluetooth device
            if (isset($param) && $param) {
                sysCmd('timeout 5 bluetoothctl cancel-pairing '.$param);
            } else {
                $bluetoothDevices = sysCmd('timeout 5 bluetoothctl devices');
                if (isset($bluetoothDevices[0]) && trim($bluetoothDevices[0])) {
                    foreach ($bluetoothDevices as $bluetoothDevice) {
                        $btDevice = preg_replace('/[\s\t]+/', ' ', trim($bluetoothDevice));
                        if (substr_count($btDevice, ' ') >= 2) {
                            list( , $bluetoothDeviceMac, ) = explode(' ', $btDevice, 3);
                            wrk_btcfg($redis, 'unpair', $bluetoothDeviceMac);
                        }
                    }
                }
            }
            break;
       case 'connect':
            // connect a specified Bluetooth device (pairing will also be done if required)
            if (!isset($param) || !$param) {
                $retval = false;
                break;
            }
            // wrk_btcfg($redis, 'trust', $param);
            wrk_btcfg($redis, 'pair', $param);
            sysCmd('timeout 5 bluetoothctl connect '.$param);
            wrk_btcfg($redis, 'trust', $param);
            break;
        case 'disconnect':
            // disconnect a specific or all Bluetooth devices
            $devices = json_decode($redis->get('bluetooth_status'), true);
            if (isset($param) && $param) {
                if (isset($devices[$param]['source']) && !$devices[$param]['source']) {
                    sysCmd('timeout 5 bluetoothctl untrust '.$param);
                }
                sysCmd('timeout 5 bluetoothctl disconnect '.$param);
            } else {
                $bluetoothDevices = sysCmd('timeout 5 bluetoothctl devices');
                if (isset($bluetoothDevices[0]) && trim($bluetoothDevices[0])) {
                    foreach ($bluetoothDevices as $bluetoothDevice) {
                        $btDevice = preg_replace('/[\s\t]+/', ' ', trim($bluetoothDevice));
                        if (substr_count($btDevice, ' ') >= 2) {
                            list( , $bluetoothDeviceMac, ) = explode(' ', $btDevice, 3);
                            wrk_btcfg($redis, 'disconnect', $bluetoothDeviceMac);
                        }
                    }
                }
            }
            break;
         case 'disconnect_sources':
            // disconnect all sources
            $devices = json_decode($redis->get('bluetooth_status'), true);
            foreach ($devices as $device) {
                if ($device['source']) {
                    if ($device['connected']) {
                        // disconnect if connected
                        wrk_btcfg($redis, 'disconnect', $device['device']);
                    }
                }
                if ($device['sink']) {
                    if ($device['blocked']) {
                        // disconnect if connected
                        wrk_btcfg($redis, 'unblock', $device['device']);
                    }
                }
            }
            break;
        case 'disconnect_sinks':
            // disconnect all sinks
            $devices = json_decode($redis->get('bluetooth_status'), true);
            foreach ($devices as $device) {
                if ($device['sink'] && !$device['source']) {
                    // some devices are source and sink's, these cannot be automatically blocked
                    if (!$device['blocked']) {
                        // the device must be blocked, otherwise it will just reconnect automatically
                        //  blocking is done instead of untrust so that the device retains its set trust status and is
                        //  blocked/unblocked automatically, the UI cannot block/unblock a output device
                        wrk_btcfg($redis, 'block', $device['device']);
                    }
                    if ($device['connected']) {
                        // disconnect if connected
                        wrk_btcfg($redis, 'disconnect', $device['device']);
                    }
                }
            }
            break;
        case 'trust':
            // trust a configured Bluetooth device
            if (isset($param) && $param) {
                sysCmd('timeout 5 bluetoothctl trust '.$param);
            } else {
                $bluetoothDevices = sysCmd('timeout 5 bluetoothctl devices');
                if (isset($bluetoothDevices[0]) && trim($bluetoothDevices[0])) {
                    foreach ($bluetoothDevices as $bluetoothDevice) {
                        $btDevice = preg_replace('/[\s\t]+/', ' ', trim($bluetoothDevice));
                        if (substr_count($btDevice, ' ') >= 2) {
                            list( , $bluetoothDeviceMac, ) = explode(' ', $btDevice, 3);
                            wrk_btcfg($redis, 'trust', $bluetoothDeviceMac);
                        }
                    }
                }
            }
            break;
        case 'untrust':
            // untrust a configured Bluetooth device
            if (isset($param) && $param) {
                sysCmd('timeout 5 bluetoothctl untrust '.$param);
            } else {
                $bluetoothDevices = sysCmd('timeout 5 bluetoothctl devices');
                if (isset($bluetoothDevices[0]) && trim($bluetoothDevices[0])) {
                    foreach ($bluetoothDevices as $bluetoothDevice) {
                        $btDevice = preg_replace('/[\s\t]+/', ' ', trim($bluetoothDevice));
                        if (substr_count($btDevice, ' ') >= 2) {
                            list( , $bluetoothDeviceMac, ) = explode(' ', $btDevice, 3);
                            wrk_btcfg($redis, 'untrust', $bluetoothDeviceMac);
                        }
                    }
                }
            }
            break;
        case 'block':
            // block a configured Bluetooth device
            if (isset($param) && $param) {
                sysCmd('timeout 5 bluetoothctl block '.$param);
            } else {
                $bluetoothDevices = sysCmd('timeout 5 bluetoothctl devices');
                if (isset($bluetoothDevices[0]) && trim($bluetoothDevices[0])) {
                    foreach ($bluetoothDevices as $bluetoothDevice) {
                        $btDevice = preg_replace('/[\s\t]+/', ' ', trim($bluetoothDevice));
                        if (substr_count($btDevice, ' ') >= 2) {
                            list( , $bluetoothDeviceMac, ) = explode(' ', $btDevice, 3);
                            wrk_btcfg($redis, 'block', $bluetoothDeviceMac);
                        }
                    }
                }
            }
            break;
        case 'unblock':
            // unblock a configured Bluetooth device
            if (isset($param) && $param) {
                sysCmd('timeout 5 bluetoothctl unblock '.$param);
            } else {
                $bluetoothDevices = sysCmd('timeout 5 bluetoothctl devices');
                if (isset($bluetoothDevices[0]) && trim($bluetoothDevices[0])) {
                    foreach ($bluetoothDevices as $bluetoothDevice) {
                        $btDevice = preg_replace('/[\s\t]+/', ' ', trim($bluetoothDevice));
                        if (substr_count($btDevice, ' ') >= 2) {
                            list( , $bluetoothDeviceMac, ) = explode(' ', $btDevice, 3);
                            wrk_btcfg($redis, 'unblock', $bluetoothDeviceMac);
                        }
                    }
                }
            }
            break;
        case 'forget':
            // removes the Bluetooth device(s)
            if (isset($param) && $param) {
                // if (isset($devices[$param]['paired']) && $devices[$param]['paired']) {
                    // wrk_btcfg($redis, 'unpair', $param);
                // }
                if (isset($devices[$param]['trused']) && $devices[$param]['trused']) {
                    wrk_btcfg($redis, 'untrust', $param);
                }
                if (isset($devices[$param]['connected']) && $devices[$param]['connected']) {
                    wrk_btcfg($redis, 'disconnect', $param);
                }
                sysCmd('timeout 5 bluetoothctl remove '.$param);
                sysCmd('find /var/lib/bluetooth/ -name *'.$param.'* -exec rm -rf {} \;');
            } else {
                $bluetoothDevices = sysCmd('timeout 5 bluetoothctl devices');
                if (isset($bluetoothDevices[0]) && trim($bluetoothDevices[0])) {
                    foreach ($bluetoothDevices as $bluetoothDevice) {
                        $btDevice = preg_replace('/[\s\t]+/', ' ', trim($bluetoothDevice));
                        if (substr_count($btDevice, ' ') >= 2) {
                            list( , $bluetoothDeviceMac, ) = explode(' ', $btDevice, 3);
                            wrk_btcfg($redis, 'forget', $bluetoothDeviceMac);
                        }
                    }
                }
            }
            break;
        case 'remove_bt_acards':
            // removes all Bluetooth output devices from the UI selectable output choices
            $acards = $redis->hgetall('acards');
            foreach ($acards as $key => $acard) {
                $cardDetails = json_decode($acard, true);
                if (strpos(' '.$cardDetails['device'], 'bluealsa')) {
                    // this is a bluealsa device, remove it
                    $redis->hdel('acards', $key);
                }
            }
            break;
        case 'correct_bt_ao':
            // if the current ao (audio output) points to a Bluetooth device check that it is valid and if not, correct it
            $ao = $redis->get('ao');
            $ao_default = $redis->get('ao_default');
            if (!$redis->hExists('acards', $ao)) {
                // the ao device no longer exists in acards, so it is invalid
                if ($ao_default) {
                    // a default value for ao exists, switch to this value
                    wrk_mpdconf($redis, 'switchao', $ao_default);
                } else {
                    // no default value for ao exists, switch to a null string
                    wrk_mpdconf($redis, 'switchao', '');
                }
            }
            break;
        case 'set_volume':
            // sets the volume of a Bluetooth source device
            //  there should only be one active source device when this is called, but we will apply the volume change to all active source pcms
            $pcmsSource = sysCmd('timeout 5 bluealsa-cli list-pcms | grep a2dp | grep -i "source"');
            $def_volume_in = $redis->hGet('bluetooth', 'def_volume_in');
            foreach ($pcmsSource as $pcmsPath) {
                sysCmd('timeout 5 bluealsa-cli volume '.$pcmsPath.' '.$def_volume_in);
            }
            break;
        case 'status':
            // returns an array containing the all Blutooth device/connection and their status
            // if an active output device/connection is detected it will be added to the acards data structure
            //  which allows it to be selected from the UI
            $deviceArray = array();
            $bluetoothOn = $redis->get('bluetooth_on');
            $bluetoothControlerOn = false;
            if ($bluetoothOn) {
                // testing for an active Bluetooth controller seems to give a false negative on its first try, repeat it max 3 times with a half second delay
                $cnt = 3;
                while (!$bluetoothControlerOn && ($cnt-- > 0)) {
                    $bluetoothControlerOn = sysCmd('timeout 5 bluetoothctl list | grep -ic "Controller " | xargs')[0];
                    if (!$bluetoothControlerOn) {
                        // sleep half a second
                        usleep(500000);
                    }
                }
            }
            if (!$bluetoothControlerOn || !$bluetoothOn) {
                // Bluetooth is off or there is no Bluetooth controller, just return an empty array
                $redis->set('bluetooth_status', json_encode($deviceArray));
                $retval = $deviceArray;
                $redis->set('bluetooth_status', json_encode($deviceArray));
                break;
            }
            $sourceConnected = 0;
            $devices = sysCmd('timeout 5 bluetoothctl devices');
            if (isset($devices[0]) && trim($devices[0])) {
                foreach ($devices as $device) {
                    $dev = preg_replace('/[\s\t]+/', ' ', trim($device));
                    if (substr_count($dev, ' ') >= 2) {
                        list( , $deviceMac, $deviceName) = explode(' ', trim($dev), 3);
                        $deviceArray[$deviceMac]['service'] = false;
                        $deviceArray[$deviceMac]['source'] = false;
                        $deviceArray[$deviceMac]['sink'] = false;
                        $deviceArray[$deviceMac]['device'] = $deviceMac;
                        $deviceInfo = sysCmd('timeout 5 bluetoothctl info '.$deviceMac);
                        if (isset($deviceInfo[0]) && trim($deviceInfo[0])) {
                            foreach ($deviceInfo as $info) {
                                $info = trim($info);
                                if (strpos(' '.$info, 'Device ')) {
                                    continue;
                                }
                                if (strpos(' '.$info, 'UUID: ')) {
                                    if (strpos(' '.strtolower($info), 'audio source')) {
                                        $deviceArray[$deviceMac]['source'] = true;
                                    } else if (strpos(' '.strtolower($info), 'audio sink')) {
                                        $deviceArray[$deviceMac]['sink'] = true;
                                    }
                                    continue;
                                }
                                if (strpos(' '.$info, ': ')) {
                                    list($key, $value) = explode(': ', $info, 2);
                                    $key = preg_replace('!\s+!', '_', strtolower(trim($key)));
                                    $value = trim($value);
                                    if (strtolower($value) == 'yes') {
                                        $value = true;
                                    } else if (strtolower($value) == 'no') {
                                        $value = false;
                                    }
                                    $deviceArray[$deviceMac][$key] = $value;
                                }
                            }
                        }
                    }
                    if (!isset($deviceArray[$deviceMac]['name']) || !$deviceArray[$deviceMac]['name']) {
                        unset($deviceArray[$deviceMac]);
                    } else {
                        if ($deviceArray[$deviceMac]['source'] && $deviceArray[$deviceMac]['connected']) {
                            $sourceConnected++;
                        }
                    }
                }
            }
            // check and fix any output Bluetooth devices (block/unblock/disconnect), depending on a connected source device
            //  and only allow one connected source
            $source_dev = $redis->hGet('bluetooth', 'source_dev');
            if (($sourceConnected > 1) && ($source_dev != '')) {
                wrk_btcfg($redis, 'disconnect', $source_dev);
                $deviceArray[$source_dev]['connected'] = false;
                $redis->hSet('bluetooth', 'source_dev', '');
                $sourceConnected = 1;
            }
            if (($sourceConnected == 0)) {
                $redis->hSet('bluetooth', 'source_dev', '');
            }
            foreach ($deviceArray as &$device) {
                // note $device is by reference and can be modified
                if (($sourceConnected == 1) && $device['source'] && $device['connected']) {
                    // if ($redis->hGet('bluetooth', 'source_dev') == '') {
                        // wrk_btcfg($redis, 'set_volume');
                    // }
                    $redis->hSet('bluetooth', 'source_dev', $device['device']);
                }
                if ($redis->get('activePlayer') == 'Bluetooth') {
                    if ($device['sink'] && !$device['source']) {
                        // source is playing, disconnect and block sink's
                        if (!$device['blocked']) {
                            wrk_btcfg($redis, 'block', $device['device']);
                            $device['blocked'] = true;
                        }
                        if ($device['connected']) {
                            wrk_btcfg($redis, 'disconnect', $device['device']);
                            $device['connected'] = false;
                        }
                    }
                } else {
                    // this is important, for reactivating output devices
                    if ($device['sink']) {
                        if ($device['blocked']) {
                            wrk_btcfg($redis, 'unblock', $device['device']);
                            $device['blocked'] = false;
                        }
                    }
                }
                if ($device['paired'] && !$device['source'] && !$device['sink']) {
                    // device is not valid for audio input or output, this cannot be seen in the UI
                    //  forget the device, its entry in the table will be deleted on the next cycle
                    wrk_btcfg($redis, 'forget', $device['device']);
                }
            }
            // control and fix Bluetooth output devices in acards
            // first delete all bluetooth entries in acards
            wrk_btcfg($redis, 'remove_bt_acards');
            $mpdConfigured = true;
            if (($redis->get('activePlayer') != 'Bluetooth') && count($deviceArray)) {
                // only activate when there is no connected connected source device and the device array has values
                $pcmsInfo = sysCmd('timeout 5 bluealsa-cli list-pcms');
                if (isset($pcmsInfo[0])) {
                    foreach ($pcmsInfo as $pcms) {
                        $pcmsData = get_between_data($pcms, 'dev_');
                        if ($pcmsData && (substr_count($pcmsData, '/') >= 2)) {
                            list($pcmsDevice, $pcmsService, $pcmsType) = explode('/', $pcmsData, 3);
                            if (isset($pcmsDevice) && $pcmsDevice) {
                                $pcmsDevice = str_replace('_', ':', $pcmsDevice);
                                if (isset($deviceArray[$pcmsDevice]['device'])) {
                                    if (isset($pcmsService) && $pcmsService) {
                                        $deviceArray[$pcmsDevice]['service'] = $pcmsService;
                                    }
                                    if (isset($pcmsType)) {
                                        if ($pcmsType == 'source') {
                                            $deviceArray[$pcmsDevice]['source'] = true;
                                        } else if ($pcmsType == 'sink') {
                                            $deviceArray[$pcmsDevice]['sink'] = true;
                                            $mpdConfigured = sysCmd('grep -ic "'.$deviceArray[$pcmsDevice]['device'].'" "/etc/mpd.conf"')[0];
                                            if ($mpdConfigured) {
                                                // only add entries to acards when they have been configured in the MPD configuration file
                                                if (strpos(' '.$pcmsService, 'a2dp')) {
                                                    // add this valid bluetooth card to the acards data structure
                                                    $btCard['device'] = 'bluealsa:DEV='.$deviceArray[$pcmsDevice]['device'].',PROFILE=a2dp';
                                                    // hardware mixer, wont work
                                                    // $btCard['volmin'] = '0'; // always 0 for bluealsa
                                                    // $btCard['volmax'] = '127'; // always 127 for bluealsa
                                                    // $btCard['mixer_device'] = 'bluealsa:'.$deviceArray[$pcmsDevice]['device'];
                                                    // $btCard['mixer_control'] = $deviceArray[$pcmsDevice]['name'].' - A2DP ';
                                                    //
                                                    $btCard['extlabel'] = $deviceArray[$pcmsDevice]['name'];
                                                    $btCard['sysname'] = $deviceArray[$pcmsDevice]['name'];
                                                    $btCard['type'] = 'alsa';
                                                    $btCard['description'] = 'Bluetooth: '.$deviceArray[$pcmsDevice]['name'].' ('.$deviceArray[$pcmsDevice]['icon'].')';
                                                    $redis->hSet('acards', $deviceArray[$pcmsDevice]['name'], json_encode($btCard));
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            if (!$mpdConfigured) {
                // the output device is not included in the MPD configuration file
                sysCmdAsync($redis, 'nice --adjustment=10 /srv/http/command/refresh_ao', 0);
            }
            wrk_btcfg($redis, 'correct_bt_ao');
            $redis->set('bluetooth_status', json_encode($deviceArray));
            $retval = $deviceArray;
            break;
        case 'status_async':
            // runs the status option asynchronously in the queue
            sysCmdAsync($redis, 'nice --adjustment=10 /srv/http/command/bt_status_async.php');
            break;
        case 'status_async_now':
            // runs the status option asynchronously now
            sysCmdAsync($redis, 'nice --adjustment=10 /srv/http/command/bt_status_async.php', 0);
            break;
        case 'check_bt_mpd_output':
            // check that mpd.conf contains an output for a specific or all output Bluetooth device(s)
            if (isset($param) && $param) {
                $mpdConfigured = sysCmd('grep -ic "'.$param.'" "/etc/mpd.conf"')[0];
                if ($mpdConfigured) {
                    $retval = true;
                } else {
                    $retval = false;
                }
            } else {
                $retval = true;
                $devices = wrk_btcfg($redis, 'status');
                foreach ($devices as $device) {
                    if ($device['name'] && $device['sink']) {
                        $mpdConfigured = sysCmd('grep -ic "'.$device['device'].'" "/etc/mpd.conf"')[0];
                        if (!$mpdConfigured) {
                            $retval = false;
                            break;
                        }
                    }
                }
            }
            break;
        case 'bt_scan_output':
            // scan for Bluetooth output devices
            //  it will run for $param seconds, default = 300 (5 minutes), max 600 (10 minutes)
            if (!isset($param) || !trim($param)) {
                // $param is not set
                $param = 300;
            } else {
                // $param has a value
                $param = intval(trim($param));
            }
            if ($param <= 0) {
                // $param negative or zero
                $param = 300;
            }
            // max 600
            $param = min($param, 600);
            // when we scan for sinks all sources must be disconnected
            wrk_btcfg($redis, 'disconnect_sources');
            // now retrieve the status of the devices, this will also fix audio output
            $devCnt = 0;
            $btDevCnt = 0;
            sysCmd('systemctl start bluetoothctl_scan');
            $end_timestamp = microtime(true)+$param;
            while ($end_timestamp >= microtime(true)) {
                // $btDevCntNew = count(sysCmd('timeout 5 bluetoothctl devices'));
                // if ($btDevCnt != $btDevCntNew) {
                    // $btDevCnt = $btDevCntNew;
                    // // number of devices has changed, scan all the devices details
                    // $devices = wrk_btcfg($redis, 'status');
                    // $devCntNew = count($devices);
                    // // not all new detected devices are relevant, some are ommited from $devices
                    // if ($devCnt != $devCntNew) {
                        // $devCnt = $devCntNew;
                        // sleep(3);
                        // foreach ($devices as $device) {
                            // if ($device['sink'] && !$device['source'] && !$device['connected'] && $device['trusted']) {
                                // // this is a new device or trusted auto-connect has failed to connect it
                                // wrk_btcfg($redis, 'connect', $device['device']);
                                // // force refreshing $devices on the next cycle
                                // $btDevCnt = 0;
                            // }
                        // }
                    // }
                // }
                sleep(3);
                $devices = wrk_btcfg($redis, 'status');
                if ($redis->get('activePlayer') != 'Bluetooth') {
                    foreach ($devices as $device) {
                        if ($device['sink'] && !$device['connected'] && $device['trusted'] && !$device['blocked']) {
                            // this is a new device or trusted auto-connect has failed to connect it
                            wrk_btcfg($redis, 'connect', $device['device']);
                        }
                    }
                }
            }
            sysCmd('systemctl stop bluetoothctl_scan ; pkill bluetoothctl');
            break;
        case 'config':
            // configuration details changed
            if (!isset($param) || !$param) {
                $retval = false;
                break;
            }
            $resetBluetooth = false;
            $restartBluealsaAplay = false;
            $configItems = array();
            $configItems = json_decode($param, true);
            foreach ($configItems as $configKey => $configValue) {
                if (!isset($configValue) || !strlen(trim($configValue))) {
                    // value is not set
                    // echo "error '".$configKey."' space/zero\n";
                    continue;
                }
                switch ($configKey) {
                    // setting default volume is done with bluealsa-cli, maybe the code below will be relevant in a future version
                    // case 'def_volume_in':
                        // $volume_in = $configValue;
                        // if (!isset($volume_out)) {
                            // $volume_out = $redis->hGet('bluetooth', 'def_volume_out');
                        // }
                    // case 'def_volume_out':
                        // $volume_out = $configValue;
                        // if (!isset($volume_in)) {
                            // $volume_in = $redis->hGet('bluetooth', 'def_volume_in');
                        // }
                        // if (($volume_in != $volume_out) || ($volume_in == -1)) {
                            // // the volume is only set when input and output volumes are identical and not -1
                            // // remove the switch in the files
                            // // get the files with the switch
                            // $bluealsaConfigFiles = sysCmd("grep -l -- ' --initial-volume=' /etc/default/bluealsa.*");
                            // foreach ($bluealsaConfigFiles as $bluealsaConfigFile) {
                                // // remove the switch per file, only in the line beginning with 'OPTIONS='
                                // sysCmd("sed -i '/^OPTIONS=/s/ --initial-volume=[^/s]*//' ".$bluealsaConfigFile);
                                // $resetBluetooth = true;
                            // }
                        // } else {
                            // // insert the switch in files
                            // // get the files without the switch with a correct value
                            // $bluealsaConfigFiles = sysCmd("grep -L -- ' --initial-volume=".$configValue."' /etc/default/bluealsa.*");
                            // // first remove the incorrect switches
                            // foreach ($bluealsaConfigFiles as $bluealsaConfigFile) {
                                // // remove the switch per file, only in the line beginning with 'OPTIONS='
                                // sysCmd("sed -i '/^OPTIONS=/s/ --initial-volume=[^/s]*//' ".$bluealsaConfigFile);
                                // $resetBluetooth = true;
                            // }
                            // // then add the switch with the correct value
                            // foreach ($bluealsaConfigFiles as $bluealsaConfigFile) {
                                // // add the switch per file, only in the line beginning with 'OPTIONS='
                                // sysCmd("sed -i '/^OPTIONS=/s/-p a2dp-source/-p a2dp-source --initial-volume=".$configValue."/' ".$bluealsaConfigFile);
                                // $resetBluetooth = true;
                            // }
                        // }
                        // unset($volume_in, $volume_out, $bluealsaConfigFiles);
                        // break;
                    case 'quality':
                        // change the symlink to point to the requested file version
                        //  there is a file for each quality setting
                        sysCmd('ln -sfT /etc/default/bluealsa.'.$configValue.' /etc/default/bluealsa');
                        if ($redis->hget('bluetooth', $configKey) != $configValue) {
                            $resetBluetooth = true;
                        }
                        break;
                    case 'samplerate':
                        // all of the files '/etc/default/bluealsa.*' need to have an extra switch removed or added
                        //  the switch --a2dp-force-audio-cd should be present for samplerate 44100 and omitted for 48000
                        if ($configValue == '44100') {
                            // get the files without the switch
                            $bluealsaConfigFiles = sysCmd("grep -L -- ' --a2dp-force-audio-cd' /etc/default/bluealsa.*");
                            foreach ($bluealsaConfigFiles as $bluealsaConfigFile) {
                                // add the switch per file, only in the line beginning with 'OPTIONS='
                                sysCmd("sed -i '/^OPTIONS=/s/-p a2dp-source/-p a2dp-source --a2dp-force-audio-cd/' ".$bluealsaConfigFile);
                                $resetBluetooth = true;
                            }
                        } else if ($configValue == '48000') {
                            // get the files with the switch
                            $bluealsaConfigFiles = sysCmd("grep -l -- ' --a2dp-force-audio-cd' /etc/default/bluealsa.*");
                            foreach ($bluealsaConfigFiles as $bluealsaConfigFile) {
                                // remove the switch per file, only in the line beginning with 'OPTIONS='
                                sysCmd("sed -i '/^OPTIONS=/s/ --a2dp-force-audio-cd//' ".$bluealsaConfigFile);
                                $resetBluetooth = true;
                            }
                        } else {
                            // invalid value
                            //echo "samplerate error invalid:'".$configValue."'\n";
                            break;
                        }
                        // MPD configuration can be modified on the fly, mpd.conf file will be modified on the next change or reboot
                        $btDevices = wrk_btcfg($redis, 'status');
                        foreach ($btDevices as $btDevice) {
                            if ($btDevice['sink'] && $btDevice['device'] && $btDevice['name']) {
                                // mpc outputset "<device>" allowed_formats=["48000:16:2"|"44100:16:2"]
                                sysCmd('mpc outputset "'.$btDevice['name'].'" allowed_formats="'.$configValue.':16:2"');
                            }
                        }
                        unset($bluealsaConfigFiles, $btDevices);
                        break;
                    case 'IO_toggle':
                        // all of the files '/etc/default/bluealsa.*' need to have the switch -p a2dp-sink and/or -p a2dp-source added or removed
                        //  the switch --a2dp-volume is invalid unless -p a2dp-sink is specified
                        if ($configValue == 'both') {
                            $addval = '-p a2dp-source -p a2dp-sink';
                        } else if ($configValue == 'input') {
                            $addval = '-p a2dp-sink';
                        } else if ($configValue == 'output') {
                            $addval = '-p a2dp-source';
                        }
                        if (($configValue != 'output') && $redis->hGet('bluetooth', 'native_volume_control')) {
                            $addval .= ' --a2dp-volume';
                        }
                        // get the files
                        $bluealsaConfigFiles = sysCmd("grep -l -- '^OPTIONS=\"' /etc/default/bluealsa.*");
                        // process the files
                        foreach ($bluealsaConfigFiles as $bluealsaConfigFile) {
                            // remove the switches per file, only in the line beginning with 'OPTIONS='
                            sysCmd("sed -i '/^OPTIONS=/s/\s*-p a2dp-sink// ; /^OPTIONS=/s/\s*-p a2dp-source// ; /^OPTIONS=/s/\s*--a2dp-volume//' '".$bluealsaConfigFile."'");
                            // add the new switches per file, only in the line beginning with 'OPTIONS='
                            $command = 'sed -i '."'".'/^OPTIONS=\"/s/OPTIONS=\"\s*/OPTIONS=\"'.$addval." /' '".$bluealsaConfigFile."'";
                            $check = implode("\n", sysCmd($command))."\n";
                            // debug
                            // echo "Command    :'".$command."'\n";
                            // echo "Retval sed :'".$check."'\n";
                        }
                        unset($check, $command, $addval, $bluealsaConfigFiles);
                        $resetBluetooth = true;
                        break;
                    case 'native_volume_control':
                        // all of the files '/etc/default/bluealsa.*' need to have the switch --a2dp-volume added or removed
                        if ($configValue) {
                            // get the files without the switch
                            $bluealsaConfigFiles = sysCmd("grep -L -- ' --a2dp-volume' /etc/default/bluealsa.*");
                            foreach ($bluealsaConfigFiles as $bluealsaConfigFile) {
                                // add the switch per file, only in the line beginning with 'OPTIONS='
                                sysCmd("sed -i '/^OPTIONS=/s/-p a2dp-sink/-p a2dp-sink --a2dp-volume/' ".$bluealsaConfigFile);
                                $resetBluetooth = true;
                            }
                        } else {
                            // get the files with the switch
                            $bluealsaConfigFiles = sysCmd("grep -l -- ' --a2dp-volume' /etc/default/bluealsa.*");
                            foreach ($bluealsaConfigFiles as $bluealsaConfigFile) {
                                // remove the switch per file, only in the line beginning with 'OPTIONS='
                                sysCmd("sed -i '/^OPTIONS=/s/\s*--a2dp-volume//' ".$bluealsaConfigFile);
                                $resetBluetooth = true;
                            }
                        }
                        unset($bluealsaConfigFiles);
                        break;
                    case 'aptX_HD_codec':
                        // all of the files '/etc/default/bluealsa.*' need to have the switch -c aptX-HD will be added or removed
                        if ($configValue) {
                            // get the files without the switch
                            $bluealsaConfigFiles = sysCmd("grep -L -- ' -c aptX-HD' /etc/default/bluealsa.*");
                            foreach ($bluealsaConfigFiles as $bluealsaConfigFile) {
                                // add the switch per file, only in the line beginning with 'OPTIONS='
                                sysCmd("sed -i '/^OPTIONS=/s/-c MP3/-c MP3 -c aptX-HD/' ".$bluealsaConfigFile);
                                $resetBluetooth = true;
                            }
                        } else {
                            // get the files with the switch
                            $bluealsaConfigFiles = sysCmd("grep -l -- ' -c aptX-HD' /etc/default/bluealsa.*");
                            foreach ($bluealsaConfigFiles as $bluealsaConfigFile) {
                                // remove the switch per file, only in the line beginning with 'OPTIONS='
                                sysCmd("sed -i '/^OPTIONS=/s/\s*-c aptX-HD//' ".$bluealsaConfigFile);
                                $resetBluetooth = true;
                            }
                        }
                        unset($bluealsaConfigFiles);
                        break;
                    case 'FastStream_codec':
                        // all of the files '/etc/default/bluealsa.*' need to have the switch '-c FastStream' added or removed
                        if ($configValue) {
                            // get the files without the switch
                            $bluealsaConfigFiles = sysCmd("grep -L -- ' -c FastStream' /etc/default/bluealsa.*");
                            foreach ($bluealsaConfigFiles as $bluealsaConfigFile) {
                                // add the switch per file, only in the line beginning with 'OPTIONS='
                                sysCmd("sed -i '/^OPTIONS=/s/-c MP3/-c MP3 -c FastStream/' ".$bluealsaConfigFile);
                                $resetBluetooth = true;
                            }
                        } else {
                            // get the files with the switch
                            $bluealsaConfigFiles = sysCmd("grep -l -- ' -c FastStream' /etc/default/bluealsa.*");
                            foreach ($bluealsaConfigFiles as $bluealsaConfigFile) {
                                // remove the switch per file, only in the line beginning with 'OPTIONS='
                                sysCmd("sed -i '/^OPTIONS=/s/\s*-c FastStream//' ".$bluealsaConfigFile);
                                $resetBluetooth = true;
                            }
                        }
                        unset($bluealsaConfigFiles);
                        break;
                    case 'LDAC_codec':
                        // all of the files '/etc/default/bluealsa.*' need to have the switches '-c LDAC --ldac-abr' added or removed
                        if ($configValue) {
                            // get the files without the switch
                            $bluealsaConfigFiles = sysCmd("grep -L -- ' -c LDAC --ldac-abr' /etc/default/bluealsa.*");
                            foreach ($bluealsaConfigFiles as $bluealsaConfigFile) {
                                // add the switch per file, only in the line beginning with 'OPTIONS='
                                sysCmd("sed -i '/^OPTIONS=/s/-c MP3/-c MP3 -c LDAC --ldac-abr/' ".$bluealsaConfigFile);
                                $resetBluetooth = true;
                            }
                        } else {
                            // get the files with the switch
                            $bluealsaConfigFiles = sysCmd("grep -l -- ' -c LDAC' /etc/default/bluealsa.*");
                            foreach ($bluealsaConfigFiles as $bluealsaConfigFile) {
                                // remove the switch per file, only in the line beginning with 'OPTIONS='
                                sysCmd("sed -i '/^OPTIONS=/s/\s*-c LDAC --ldac-abr//' ".$bluealsaConfigFile);
                                $resetBluetooth = true;
                            }
                        }
                        unset($bluealsaConfigFiles);
                        break;
                    case 'local_volume_control':
                        $restartBluealsaAplay = true;
                        break;
                    // case 'timeout':
                        // // don't need to do anything, the key value is updated below
                        // break;
                    // case 'remember_last_volume':
                        // // don't need to do anything, the key value is updated below
                        // break;
                    // default:
                        // // don't need to do anything, the key value is updated below
                        // break;
                }
                $redis->hSet('bluetooth', $configKey, $configValue);
            }
            if (isset($jobID) && $jobID) {
                $redis->sRem('w_lock', $jobID);
            }
            if ($resetBluetooth && $redis->get('bluetooth_on')) {
                // a reset is required to activate the saved values, its not always required
                wrk_btcfg($redis, 'reset');
            }
            if ($restartBluealsaAplay && $redis->get('bluetooth_on')) {
                // a reset is required to activate the saved values, its not always required
                wrk_btcfg($redis, 'restart_bluealsa_aplay');
            }
            break;
        case 'quality_options':
            // set up an array of the quality option files, these are stored in '/etc/default/bluealsa.<name>'
            //  name is not empty and is the name of the quality option
            $quality_options = array();
            $filesInBtconf = scandir('/etc/default');
            foreach ($filesInBtconf as $btConf) {
                if (substr_count($btConf, '.') != 1) {
                    continue;
                }
                $parts = explode('.', $btConf);
                if (isset($parts[0]) && ($parts[0] == 'bluealsa') && isset($parts[1]) && $parts[1]) {
                    $quality_options[] = $parts[1];
                }
            }
            rsort($quality_options);
            $redis->hset('bluetooth', 'quality_options', json_encode($quality_options));
            break;
        case 'auto_volume':
            // relevant for bluetooth input
            //  when bluetooth or bluetooth input is disabled the volume control settings in the bluetooth menu are removed
            // this function will determine the settings of the soft volume control depending on several variables
            //  when the player volume control is off, Bluetooth soft volume is set on/off depending on mixer control of the audio card
            //      and the volume control settings in the bluetooth menu are removed
            //  otherwise (the player volume control is on) and the native volume control is set to automatic or is off:
            //      if the current output card supports hardware volume, Bluetooth soft volume is set off (native volume control on)
            //      if the current output card does not support hardware volume, Bluetooth soft volume is set on (native volume control off)
            $retval = false;
            if (!$redis->get('bluetooth_on')) {
                // bluetooth is off, no nothing
                break;
            }
            if ($redis->hGet('bluetooth', 'IO_toggle') == 'output') {
                // only bluetooth output is on, no nothing
                break;
            }
            $bluealsaInfoLines = sysCmd('bluealsa-cli --verbose list-pcms');
            if (!isset($bluealsaInfoLines) || !count($bluealsaInfoLines)) {
                // bluealsa not active, do nothing
                break;
            }
            // we have information from bluealsa
            // make a list of the keys which have numeric information
            $numericKeys = array('sequence', 'channels', 'delay', 'volume');
            // decode the lines into an array
            foreach ($bluealsaInfoLines as $bluealsaInfoLine) {
                $bluealsaInfoLine = trim($bluealsaInfoLine);
                if (substr($bluealsaInfoLine, 0, 1) == '/') {
                    // theis is the pcm line
                    if (substr($bluealsaInfoLine, -6) == 'source') {
                        // its an input
                        $type = 'input';
                    } else if (substr($bluealsaInfoLine, -4) == 'sink') {
                        // its and output
                        $type = 'output';
                    } else {
                        // it should never happen
                        $trpe = 'unknown';
                    }
                    // save the pcm and type
                    $pcmInfo[$type]['pcm'] = $bluealsaInfoLine;
                    $pcmInfo[$type]['type'] = $type;
                    continue;
                }
                // extract the key and value from the line
                if (!strpos(' '.$bluealsaInfoLine, ': ')) {
                    // there needs to be a colon space in the line
                    continue;
                }
                // decode the line into a key and value
                list($key, $value) = explode(': ',$bluealsaInfoLine, 2);
                // make the key lower case and replace spaces with underscores
                $key = str_replace(' ', '_', strtolower(trim($key)));
                // trim the value
                $value = trim($value);
                if (strpos(' '.$value, 'L:') && strpos(' '.$value, 'R:')) {
                    // make values with right and left entries into a single entry
                    $value = trim(get_between_data($value, 'L:', 'R:'));
                }
                if ($value == 'false') {
                    // use zero for false
                    $value = 0;
                } else if ($value == 'true') {
                    // use 1 for true
                    $value = 1;
                } else {
                    if (in_array($key, $numericKeys)) {
                        // for numeric values remove all other characters except numbers and the decimal point
                        $value = preg_replace('/[^0-9.]/', '', $value);
                    }
                }
                // save the value in the array
                $pcmInfo[$type][$key] = $value;
            }
            $retval = $pcmInfo;
            if (!isset($pcmInfo['input']['pcm'])) {
                // there is no input pcm, do nothing
                break;
            }
            $currentAoInfo = json_decode($redis->hGet('acards', $redis->get('ao')), 'true');
            $player_volume_control = $redis->hGet('mpdconf', 'mixer_type');
            if (($player_volume_control != 'enabled') && ($player_volume_control != 'hardware') && ($player_volume_control != 'software')) {
                // player volume control disabled, switch local volume control off
                unset($configArray);
                if ($redis->hget('bluetooth', 'local_volume_control')) {
                    // set up the configuration array with the local volume control value (off)
                    $configArray['local_volume_control'] = 0;
                }
                if (isset($configArray)) {
                    // use the configuration, when set
                    wrk_btcfg($redis, 'config', $configArray);
                }
                // process soft volume as if it is set to automatic
                if (!isset($currentAoInfo['mixer_device']) && !$pcmInfo['input']['softvolume']) {
                    // no mixer device available for the sound card and softvolume is off, set soft volume control on
                    sysCmd('bluealsa-cli soft-volume '.$pcmInfo['input']['pcm'].' 1');
                } else if (isset($currentAoInfo['mixer_device']) && $pcmInfo['input']['softvolume']) {
                    // mixer device available for the sound card and softvolume is on, set soft volume control off
                    sysCmd('bluealsa-cli soft-volume '.$pcmInfo['input']['pcm'].' 0');
                }
                break;
            }
            $native_volume_control = $redis->hget('bluetooth', 'native_volume_control');
            if (($native_volume_control == 'a') || ($native_volume_control == '0')) {
                // native volume control is set to automatic or off
                if (!isset($currentAoInfo['mixer_device']) && !$pcmInfo['input']['softvolume']) {
                    // no mixer device available for the sound card and softvolume is off, set soft volume control on
                    sysCmd('bluealsa-cli soft-volume '.$pcmInfo['input']['pcm'].' 1');
                }
            } else if (($native_volume_control == 'a') || ($native_volume_control == '1')) {
                if (isset($currentAoInfo['mixer_device']) && $pcmInfo['input']['softvolume']) {
                    // mixer device available for the sound card and softvolume is on, set soft volume control off
                    sysCmd('bluealsa-cli soft-volume '.$pcmInfo['input']['pcm'].' 0');
                }
            }
            unset($bluealsaInfoLines,$bluealsaInfoLine, $numericKeys, $type, $pcmInfo, $key, $value, $currentAoInfo, $player_volume_control, $native_volume_control);
            break;
    }
    return $retval;
}

// function to test if something is playing
function is_playing($redis)
// returns true of false
{
    // alsa output device, when not playing the command returns an empty string, when playing returns something like:
    //  access: RW_INTERLEAVED format: S24_LE subformat: STD channels: 2 rate: 44100 (44100/1) period_size: 4410 buffer_size: 22050
    //  Note: when Bluetooth is input, output is generally an alsa hardware card
    if (trim(sysCmd('grep -vihs closed /proc/asound/card?/pcm?p/sub?/hw_params | xargs')[0])) return true;
    // other devices
    //  MPD as player
    //  generic via mpd, if MPD says its playing, then it is playing
    if (trim(sysCmd('mpc status | grep -ic "\[playing\]" | xargs')[0])) return true;
    //  now, since it is not playing, when MPD is the player it is not playing
    if ($redis->get('activePlayer') == 'MPD') return false;
    // other devices
    $ao = trim($redis->get('ao'));
    //  Bluetooth output
    if (isset($ao) && $ao) {
        $acard = json_decode($redis->hGet('acards', $redis->get('ao')), true);
        if (isset($acard['device']) && trim($acard['device'])) {
            $device = trim($acard['device']);
        }
    }
    if (isset($device) && $device && (strpos(' '.$device, 'bluealsa:') == 1)) {
        // output device is Bluetooth
        // use the function wrk_btcfg($redis, 'auto_volume') to get the pcm info
        //  when valid ['output'] is returned and [running] is true something is playing
        $pcm_info = wrk_btcfg($redis, 'auto_volume');
        if (isset($pcm_info) && is_array($pcm_info) && isset($pcm_info['output']['running']) && ($pcm_info['output']['running'])) {
            return true;
        }
    }
    //  Bluetooth input
    if ($redis->get('activePlayer') == 'Bluetooth') {
        // input device is Bluetooth
        // use the function wrk_btcfg($redis, 'auto_volume') to get the pcm info
        //  when valid ['input'] is returned and [running] is true something is playing
        $pcm_info = wrk_btcfg($redis, 'auto_volume');
        if (isset($pcm_info) && is_array($pcm_info) && isset($pcm_info['input']['running']) && ($pcm_info['input']['running'])) {
            return true;
        }
    }
    // vc4 hdmi output
    if (isset($device) && $device && strpos(' '.strtolower($device), 'vc4') && strpos(' '.strtolower($device), 'hdmi')) {
        // output device is software vc4 hdmi
        //  don't know how to do this one
    }

    return false;
}

function wrk_security($redis, $action, $args=null)
{
    $retval = true;
    switch ($action) {
        case 'check_linux_root_password':
            // Note: the format of the date in the password file can be different on various operating systems
            //          YYYY-MM-DD and DD/MM/YYYY have been seen there are possibly others
            //          this routine needs to have a consonant format of YYYY-MM-DD
            $today = date('Y-m-d');
            $passwordInfo = sysCmd("chage -l root | grep -i 'Last password change' | cut -d ':' -f 2 | xargs")[0];
            $passwordDate = date_to_Y_m_d($passwordInfo);
            if (!$passwordDate) {
                // date error report it
                echo '[wrk_security] passwordDate format error \''.$passwordInfo."'\n";
            }
            $passwordDateInitial = date_to_Y_m_d($redis->get('passworddate'));
            if (!$passwordDateInitial) {
                // date error report it
                echo '[wrk_security] passwordDateInitial format error \''.$redis->get('passworddate')."'\n";
            }
            if (($today != $passwordDate) && ($passwordDateInitial == $passwordDate)) {
                $retval = false;
            }
            // debug
            // echo "[wrk_security][check_linux_root_password] today:               '$today'\n";
            // echo "[wrk_security][check_linux_root_password] passwordDate:        '$passwordDate'\n";
            // echo "[wrk_security][check_linux_root_password] passwordDateInitial: '$passwordDateInitial'\n";
            // echo "[wrk_security][check_linux_root_password] retval:              '$retval'\n";
            break;
        case 'check_access_point_password':
            $networkInterfaces = json_decode($redis->get('network_interfaces'), true);
            $ap = false;
            foreach ($networkInterfaces as $networkInterface) {
                if (($networkInterface['type'] == 'AP') && ($networkInterface['technology'] == 'wifi')) {
                    $ap = true;
                    break;
                }
            }
            if ($ap && ($redis->hGet('AccessPoint', 'passphrase') == 'RuneAudio')) {
                $retval = false;
            }
            break;
        case 'reset_linux_root_password':
            // if a reset file has been created in <p1mountpoint>/password, set the redis variable 'passworddate'
            //  to the last changed password date for the root password
            $dirname = $redis->get('p1mountpoint').'/password';
            clearstatcache(true, $dirname);
            if (is_dir($dirname)) {
                $fileList = scandir($dirname);
                foreach ($fileList as $fileName) {
                    if ($fileName == '.') {
                        continue;
                    }
                    if ($fileName == '..') {
                        continue;
                    }
                    if ($fileName == 'readme') {
                        continue;
                    }
                    sysCmd('rm -r '.$dirname.'/*');
                    sysCmd('cp /srv/http/app/config/defaults/boot/password/readme '.$dirname.'/readme');
                    $passwordDate = sysCmd("chage -l root | grep -i 'Last password change' | cut -d ':' -f 2 | xargs")[0];
                    $redis->set('passworddate', $passwordDate);
                    break;
                }
            } else {
                sysCmd('mkdir -p \''.$dirname.'\'');
                sysCmd("cp '/srv/http/app/config/defaults/boot/password/readme' '".$dirname."/readme'");
            }
            break;
        case 'linux_password_save':
            // strip whitespace from the password
            $newpass = preg_replace('/\s/', '', $args);
            $passwordInfo = sysCmd('passwd -S root | xargs')[0];
            if (strpos(' '.$passwordInfo, $redis->get('passworddate')) && ($args == $newpass) && (strlen($args) >= 4)) {
                // the password has never been changed, it has no whitepace in it and it has a length equal to or greater than 4
                sysCmd('echo -e "'.$args.'\n'.$args.'" | passwd root');
                // send notfy to UI
                ui_notify($redis, 'Security', 'Linux root password changed');
            } else {
                // send notfy to UI
                ui_notifyError($redis, 'Security', 'Linux root password change failed, you will get a new reminder');
            }
            break;
        case 'linux_password_randomise':
            // generate a randomised password
            srand(preg_replace('/[^0-9]/', '', microtime(true)));
            $args = preg_replace('/\s/', '', md5(rand()));
            $passwordInfo = sysCmd('passwd -S root | xargs')[0];
            if (strpos(' '.$passwordInfo, $redis->get('passworddate')) && isset($args) && (strlen($args) >= 8) && (strlen($args) <= 255)) {
                // the password has never been changed, it has no whitepace in it and it has a length equal to or greater than 8 and less than or equal to 255
                sysCmd('echo -e "'.$args.'\n'.$args.'" | passwd root');
                // send notfy to UI
                ui_notify($redis, 'Security', 'Linux root password changed');
            } else {
                // send notfy to UI
                ui_notifyError($redis, 'Security', 'Linux root password change failed, invalid format. You will get a new reminder');
            }
            break;
        case 'ap_password_save':
            $newpass = preg_replace('/\s/', '', $args);
            $oldpass = $redis->hGet('AccessPoint', 'passphrase');
            if (isset($args) && ($args == $newpass) && (strlen($args) >= 8) && (strlen($args) <= 63) && ($args != $oldpass) && ($oldpass == 'RuneAudio')) {
                // the password has never been changed, it has no whitepace in it and it has a length equal to or greater than 8 and less than or equal to 255
                //  and it is different to the existing password
                $redis->hSet('AccessPoint', 'passphrase', $args);
                // send notfy to UI
                ui_notify($redis, 'Security', 'Access Point password changed, reboot to activate');
            } else {
                // send notfy to UI
                ui_notifyError($redis, 'Security', 'Access Point password change failed, invalid format. You will get a new reminder');
            }
            break;
    }
    unset($today, $newpass, $passwordInfo, $passwordDate, $networkInterfaces, $fileList);
    return $retval;
}

function wrk_CD($redis, $action='', $args=null, $track=null, $jobID=null)
// functions for attached CD drive
// returns true
{
    if ($redis->hget('CD', 'enable')) {
        $cdEnabled = true;
    } else {
        $cdEnabled = false;
    }
    if ($redis->get('activePlayer') == 'MPD') {
        $mpdPlayer = true;
    } else {
        $mpdPlayer = false;
    }
    $bindToAdderess = $redis->hGet('mpdconf', 'bind_to_address');
    switch ($action) {
        case 'start':
            // no break
            // break;
        case 'stop':
            if (isset($args) && (is_array($args) || is_object($args))) {
                foreach ($args as $key => $value) {
                    $redis->hSet('CD', $key, $value);
                }
            }
            if ($action == 'start') {
                $redis->hSet('CD', 'enable', 1);
                ui_notify($redis, "CD Input", 'Enabled');
                if (isset($jobID) && $jobID) {
                    $redis->sRem('w_lock', $jobID);
                }
            } else if ($action == 'stop') {
                $redis->hSet('CD', 'enable', 0);
                ui_notify($redis, "CD Input", 'Disabled');
                if (isset($jobID) && $jobID) {
                    $redis->sRem('w_lock', $jobID);
                }
                // get the tracks from the CD
                $cdTracks = json_decode($redis->hGet('CD', 'status'), true);
                if (isset($cdTracks) && is_array($cdTracks) && count($cdTracks)) {
                    // there is a CD inserted, eject it
                    wrk_CD($redis, 'eject');
                }
            }
            wrk_CD($redis, 'changed');
            unset($key, $value);
            break;
        case 'eject':
            $device = trim($redis->hGet('CD', 'device'));
            if (isset($device) && $device) {
                wrk_CD($redis, 'delete_from_queue');
                sysCmd('eject --cdrom');
                ui_notify($redis, "CD Input", 'CD Ejected');
                if (isset($jobID) && $jobID) {
                    $redis->sRem('w_lock', $jobID);
                }
                wrk_CD($redis, 'reset');
            }
            unset($device);
            break;
/*
         case 'playCDtrack':
            // play single CD track, $args contains the method to add to the queue, $track contains the track filename
            if ($mpdPlayer && $cdEnabled && isset($args) && $args && isset($track) && $track) {
                // CD is enabled and $args and $track have values
                if ($track == 'cdda://') {
                    // a request to play the full CD
                    wrk_CD($redis, 'playCD', $args);
                    break;
                }
                // open the MPD socket
                $socket = openMpdSocket($bindToAdderess, 0);
                // get the mpd status
                $status = _parseMpdresponse(MpdStatus($socket));
                // initiate a MPD command list
                sendMpdCommand($socket, 'command_list_begin');
                if (strpos(' '.$args, 'Clear')) {
                    // when the word Clear is included in $args, clear the queue
                    sendMpdCommand($socket, 'clear');
                    // modify some $status values
                    $status['playlistlength'] = 0;
                    $status['nextsong'] = 0;
                }
                if (strpos(' '.$args, 'Add')) {
                    // when the word Add is included in $args, add the CD-track to the queue
                    sendMpdCommand($socket, 'add '.$track);
                    // save some queue positions
                    $beginAdd = $status['playlistlength'];
                    $playFrom = $beginAdd;
                }
                if (strpos(' '.$args, 'Next') && $status['playlistlength']) {
                    // when the word Next is included in $args and the queue had some songs in it, move the added CD-track to the next queue position
                    sendMpdCommand($socket, 'move '.$beginAdd.' '.$status['nextsong']);
                    $playFrom = $status['nextsong'];
                }
                if (strpos(' '.$args, 'Play')) {
                    // when the word Play is included in $args, play the first song added
                    sendMpdCommand($socket, 'play '.$playFrom);
                }
                // terminate a MPD command list, this will execute all the commands
                sendMpdCommand($socket, 'command_list_end');
                // get the response, don't do anything with it
                readMpdResponse($socket);
                // close the socket
                closeMpdSocket($socket);
                unset($socket, $status, $track, $beginAdd, $playFrom);
            }
            break;
 */
        case 'playCD':
            // play whole CD, $args contains the method to add to the queue
            if ($mpdPlayer && $cdEnabled && isset($args) && $args) {
                // CD is enabled and $args has a value
                // open the MPD socket
                $socket = openMpdSocket($bindToAdderess, 0);
                // get the mpd status
                $status = _parseMpdresponse(MpdStatus($socket));
                // initiate a MPD command list
                sendMpdCommand($socket, 'command_list_begin');
                if (strpos(' '.$args, 'Clear')) {
                    // when the word Clear is included in $args, clear the queue
                    sendMpdCommand($socket, 'clear');
                    // modify some $status values
                    $status['playlistlength'] = 0;
                    $status['nextsong'] = 0;
                }
                if (strpos(' '.$args, 'Add')) {
                    // when the word Add is included in $args, add each CD-track to the queue
                    // get the tracks
                    $tracks = json_decode($redis->hGet('CD', 'status'), true);
                    // $tracks[0] is an entry for the full cd, remove it, we play all the tracks individually
                    unset($tracks[0]);
                    foreach ($tracks as $key => $track) {
                        // add the track to the queue
                        sendMpdCommand($socket, 'add '.$track['file']);
                    }
                    // save some queue positions
                    $beginAdd = $status['playlistlength'];
                    $endAdd = $beginAdd + count($tracks);
                    $playFrom = $beginAdd;
                }
                if (strpos(' '.$args, 'Next') && $status['playlistlength']) {
                    // when the word Next is included in $args and the queue had some songs in it, move the added CD-track to the next queue position
                    sendMpdCommand($socket, 'move '.$beginAdd.':'.$endAdd.' '.$status['nextsong']);
                    $playFrom = $status['nextsong'];
                }
                if (strpos(' '.$args, 'Play')) {
                    // when the word Play is included in $args, play the first song added
                    sendMpdCommand($socket, 'play '.$playFrom);
                }
                // terminate a MPD command list, this will execute all the commands
                sendMpdCommand($socket, 'command_list_end');
                // get the response, don't do anything with it
                readMpdResponse($socket);
                // close the socket
                closeMpdSocket($socket);
                unset($socket, $status, $tracks, $track, $beginAdd, $endAdd, $playFrom);
            }
            break;
        case 'changed':
            // the cd drive has powered up, been plugged in (USB), a CD inserted or a CD ejected
            // its not possible to determine the action in any more detail
            //
            // delete entries from a previous CD from the queue
            wrk_CD($redis, 'delete_from_queue');
            // refresh the CD database
            wrk_CD($redis, 'refresh');
            if ($mpdPlayer && $cdEnabled) {
                // when autoplay is set play the CD
                $autoplay = $redis->hGet('CD', 'autoplay');
                $cdTracks = json_decode($redis->hGet('CD', 'status'), true);
                if (isset($autoplay) && $autoplay && ($autoplay != 'None') && count($cdTracks)) {
                    // autoplay is set and there is something to play
                    wrk_CD($redis, 'playCD', $autoplay);
                }
            }
            break;
        case 'removed':
            // the CD drive has been powered down or has been unplugged (USB)
            //
            // delete entries from a previous CD from the queue
            wrk_CD($redis, 'delete_from_queue');
            // refresh the CD database
            wrk_CD($redis, 'refresh');
            break;
        case 'delete_from_queue':
            // remove all CD entries in the queue from the previous CD
            // get the tracks from the CD
            $cdTracks = json_decode($redis->hGet('CD', 'status'), true);
            if (isset($cdTracks) && is_array($cdTracks) && count($cdTracks)) {
                // there was a CD in the drive
                // clear the CD tracks from the queue, repeat max 10 times
                $cnt = 20;
                $found = true;
                // open the mpd socket
                $socket = openMpdSocket($bindToAdderess, 0);
                while (($cnt-- > 0) && $found) {
                    sleep(2);
                    if (!isset($socket) || !$socket) {
                        // open the mpd socket
                        $socket = openMpdSocket($bindToAdderess, 0);
                        continue;
                    }
                    // get the active player info including 'state' and 'file'
                    $actPlayerInfo = json_decode($redis->get('act_player_info'), true);
                    // when mpd is not stopped and the current song is a CD track stop mpd
                    if (isset($actPlayerInfo['state']) && isset($actPlayerInfo['file']) && ($actPlayerInfo['state'] != 'stop') && (substr($actPlayerInfo['file'], 0, 7) == 'cdda://')) {
                        // MPD is not stopped and it is a CD track
                        // everything will freeze if the CD is not reinserted
                        sysCmd('eject --trayclose');
                        $command = 'stop';
                        // send the command
                        sendMpdCommand($socket, $command);
                        // close and reopen the mpd socket
                        closeMpdSocket($socket);
                        $socket = openMpdSocket($bindToAdderess, 0);
                        // now re-eject the CD
                        sysCmd('eject --cdrom');
                        // save the MPD state
                        $previousState = trim($actPlayerInfo['state']);
                    }
                    $retval = _parseMpdresponse(MpdStatus($socket));
                    if (!isset($retval) || !$retval) {
                        closeMpdSocket($socket);
                        $socket = openMpdSocket($bindToAdderess, 0);
                        continue;
                    }
                    $found = false;
                    foreach ($cdTracks as $key => $cdInfo) {
                        // for each track build up a mpd protocol command to retrieve its position in the queue
                        $command = 'playlistsearch "(file == '."'".$cdInfo['file']."'".')"';
                        // send the command
                        sendMpdCommand($socket, $command);
                        // get the response
                        $retval = _parseMpdresponse(readMpdResponse($socket));
                        if ($retval) {
                            // the response is valid
                            $found = true;
                            // extract the queue position
                            $pos = $retval['Pos'];
                            if (isset($pos) && is_numeric($pos)) {
                                // the queue position is valid, create a command to delete it
                                if (!isset($savePos) || ($savePos > $pos)) {
                                    $savePos = $pos;
                                }
                                $command = 'delete '.$pos;
                                // send the command
                                sendMpdCommand($socket, $command);
                                // close and reopen the mpd socket
                                closeMpdSocket($socket);
                                $socket = openMpdSocket($bindToAdderess, 0);
                            }
                        }
                        unset($command, $retval, $pos);
                    }
                    sleep(2);
                }
                // check the previous state, take action when it was play and we have a play position
                if (isset($previousState) && ($previousState == 'play') && isset($savePos)) {
                    // set up the command to restore the previous state
                    $command = 'play '.$savePos;
                    // send the command
                    sendMpdCommand($socket, $command);
                    // get the mpd response
                    $retval = readMpdResponse($socket);
                    if (!strpos(' '.$retval, 'OK')) {
                        sysCmd('mpc play '.$savePos);
                    }
                }
                // close the socket
                closeMpdSocket($socket);
                unset($cdTracks, $socket, $key, $cdInfo, $found, $cnt, $previousState, $retval);
            }
            break;
        case 'refresh':
            // no break, collect the CD drive information (drive, model and error)
            // break;
        default:
            // clear the stored values
            $cdTracks = array();
            $redis->hSet('CD', 'status', json_encode($cdTracks));
            $redis->hDel('CD', 'device');
            $redis->hDel('CD', 'model');
            $redis->hSet('CD', 'error', 'Processing, please wait a couple of seconds and then refresh the UI');
            // determine the CD drive status and number of tracks on the cd
            $cnt = 5;
            while (!isset($retval) || !is_array($retval) || (count($retval) < 4)) {
                // get the CD information with cdparanoia
                //  we are looking for a minimum of 4 lines to be returned: device name, CD drive name, minimum one track and one total line
                //  when less than 4 lines are returned: error and optional: device, CD drive name
                //  loop maximum 5 times with a wait of 2 seconds - it may take a while for the CD drive initialise
                $retval = sysCmd("cdparanoia -vsQ 2>&1 | grep -iE 'device:|cdrom model|\[|^00|no cdrom drives'");
                if (!isset($retval) || !is_array($retval) || (count($retval) < 4)) {
                    sleep(2);
                }
                if ($cnt-- <= 0) {
                    break;
                }
            }
            $redis->hDel('CD', 'error');
            foreach ($retval as $cdInfo) {
                $cdInfo = trim($cdInfo);
                $cdInfoLower = ' '.strtolower($cdInfo);
                if (strpos($cdInfoLower,'device:')) {
                    // CD device name
                    $device = trim(explode(':', $cdInfo, 2)[1]);
                    $redis->hSet('CD', 'device', $device);
                    // make the CD device readable and read-only for MPD
                    sysCmd('chmod 644 '.$device);
                } else if (strpos($cdInfoLower, 'cdrom model') && strpos($cdInfoLower, ':')) {
                    // CD model name
                    $redis->hSet('CD', 'model', trim(explode(':', $cdInfo, 2)[1]));
                } else if ((strpos($cdInfoLower, '00') == 1) && strpos($cdInfoLower, ':')) {
                    // Error message
                    $redis->hSet('CD', 'error', trim(explode(':', $cdInfo, 2)[1], ". \n\r\t\v\x00"));
                } else if (strpos($cdInfoLower, 'no cdrom drives')) {
                    // Error message
                    $redis->hSet('CD', 'error', trim($cdInfo, ". \n\r\t\v\x00"));
                } else if (strpos($cdInfoLower, '[') && strpos($cdInfoLower, '.')) {
                    // CD track line or the total line
                    if (strpos($cdInfoLower, 'total')) {
                        // total line
                        $trackNumber = 0;
                        $cdTracks[$trackNumber]['name'] = 'Full CD';
                    } else {
                        // track line
                        $trackNumber = trim(get_between_data($cdInfo, '', '.'));
                        if (isset($trackNumber) && strlen($trackNumber)) {
                            $cdTracks[$trackNumber]['name'] = 'CD Track #'.$trackNumber;
                        }
                    }
                    // get the track duration, it has the format MM:SS.dd (MM = minutes, SS = seconds, dd = decimal seconds)
                    $trackDurationMMSSdd = trim(get_between_data($cdInfo, '[', ']'));
                    if (isset($trackDurationMMSSdd) && strlen($trackDurationMMSSdd)) {
                        list($trackMM, $trackSS) = explode(':', $trackDurationMMSSdd);
                        // set the duration, including decimals
                        $cdTracks[$trackNumber]['duration'] = ($trackMM * 60) + $trackSS;
                        // set the time rounded, no decimals
                        $cdTracks[$trackNumber]['time'] = round($cdTracks[$trackNumber]['duration']);
                    }
                }
            }
            foreach ($cdTracks as $key => $cdInfo) {
                // build up the track ID's which MPD understands
                if ($key == 0) {
                    // full CD entry
                    $cdTracks[$key]['file'] = 'cdda://';
                } else {
                    // CD track entry
                    $cdTracks[$key]['file'] = 'cdda:/'.$device.'/'.$key;
                }
            }
            $redis->hSet('CD', 'status', json_encode($cdTracks));
            ui_libraryHome($redis);
            break;
    }
}

function wrk_hwinput($redis, $action='', $args=null, $device=null, $jobID = null)
// functions for hardware input device
// returns true
{
    if ($redis->hget('hw_input', 'enable')) {
        $hwInputEnabled = true;
    } else {
        $hwInputEnabled = false;
    }
    if ($redis->get('activePlayer') == 'MPD') {
        $mpdPlayer = true;
    } else {
        $mpdPlayer = false;
    }
    $bindToAdderess = $redis->hGet('mpdconf', 'bind_to_address');
    switch ($action) {
        case 'start':
            // no break
            // break;
        case 'stop':
            if (isset($args) && (is_array($args) || is_object($args))) {
                foreach ($args as $key => $value) {
                    $redis->hSet('hw_input', $key, $value);
                }
            }
            if ($action == 'start') {
                $redis->hSet('hw_input', 'enable', 1);
                ui_notify($redis, "Hardware Input", 'Enabled');
                if (isset($jobID) && $jobID) {
                    $redis->sRem('w_lock', $jobID);
                }
            } else if ($action == 'stop') {
                $redis->hSet('hw_input', 'enable', 0);
                ui_notify($redis, "Hardware Input", 'Disabled');
                if (isset($jobID) && $jobID) {
                    $redis->sRem('w_lock', $jobID);
                }
            }
            wrk_hwinput($redis, 'refresh');
            unset($key, $value);
            break;
/*
         case 'playHWdevice':
            // play a hardware device, $args contains the method to add to the queue, $device contains the device filename
            if ($mpdPlayer && $hwInputEnabled && isset($args) && $args && isset($device) && $device) {
                // hardware input device is enabled and $args and $device have values
                // open the MPD socket
                $socket = openMpdSocket($bindToAdderess, 0);
                // get the mpd status
                $status = _parseMpdresponse(MpdStatus($socket));
                // initiate a MPD command list
                sendMpdCommand($socket, 'command_list_begin');
                if (strpos(' '.$args, 'Clear')) {
                    // when the word Clear is included in $args, clear the queue
                    sendMpdCommand($socket, 'clear');
                    // modify some $status values
                    $status['playlistlength'] = 0;
                    $status['nextsong'] = 0;
                }
                if (strpos(' '.$args, 'Add')) {
                    // when the word Add is included in $args, add the device to the queue
                    sendMpdCommand($socket, 'add '.$device);
                    // save some queue positions
                    $beginAdd = $status['playlistlength'];
                    $playFrom = $beginAdd;
                }
                if (strpos(' '.$args, 'Next') && $status['playlistlength']) {
                    // when the word Next is included in $args and the queue had some songs in it, move the added device to the next queue position
                    sendMpdCommand($socket, 'move '.$beginAdd.' '.$status['nextsong']);
                    $playFrom = $status['nextsong'];
                }
                if (strpos(' '.$args, 'Play')) {
                    // when the word Play is included in $args, play the first device added
                    sendMpdCommand($socket, 'play '.$playFrom);
                }
                // terminate a MPD command list, this will execute all the commands
                sendMpdCommand($socket, 'command_list_end');
                // get the response, don't do anything with it
                readMpdResponse($socket);
                // close the socket
                closeMpdSocket($socket);
                unset($socket, $status, $beginAdd, $playFrom);
            }
            break;
 */
        case 'remove_from_queue':
            // remove all occurences of a hw device from the queue
            // $device contains the 'file' name of the device
            if (isset($device) && $device) {
                // open the mpd socket
                $socket = openMpdSocket($bindToAdderess, 0);
                // repeat max 10 times
                $cnt = 10;
                $found = true;
                while (($cnt-- > 0) && $found) {
                    $found = false;
                    // for each track build up a mpd protocol command to retrieve its position in the queue
                    $command = 'playlistsearch "(file == '."'".$device."'".')"';
                    // send the command
                    sendMpdCommand($socket, $command);
                    // get the response
                    $retval = _parseMpdresponse(readMpdResponse($socket));
                    if ($retval) {
                        // the response is valid, extract the queue position
                        $pos = $retval['Pos'];
                        if (isset($pos) && is_numeric($pos)) {
                            // the queue position is valid and it has been played before the current song
                            $found = true;
                            // create a command to delete it
                            $command = 'delete '.$pos;
                            // send the command
                            sendMpdCommand($socket, $command);
                            // get the response, don't do anything with it
                            readMpdResponse($socket);
                        }
                    }
                    unset($command, $retval, $pos);
                }
                closeMpdSocket($socket);
                unset($socket, $cnt, $found);
            }
            break;
        case 'refresh':
            // no break, clean the queue & collect the hardware device information
            // break;
        case 'check_played':
            // when a Hardware device is no longer active (has been played) remove it from the queue
            // get the registered hardware devices
            $hwDevices = json_decode($redis->hGet('hw_input', 'status'), true);
            if (isset($hwDevices) && is_array($hwDevices) && count($hwDevices)) {
                // there is at least one hardware device
                // repeat max 10 times
                $cnt = 10;
                $found = true;
                while (($cnt-- > 0) && $found) {
                    $found = false;
                    foreach ($hwDevices as $hwDevice) {
                        // open the mpd socket
                        $socket = openMpdSocket($bindToAdderess, 0);
                        $status = _parseMpdresponse(MpdStatus($socket));
                        // for each track build up a mpd protocol command to retrieve its position in the queue
                        $command = 'playlistsearch "(file == '."'".$hwDevice['file']."'".')"';
                        // send the command
                        sendMpdCommand($socket, $command);
                        // get the response
                        $retval = _parseMpdresponse(readMpdResponse($socket));
                        if ($retval) {
                            // the response is valid, extract the queue position
                            $pos = $retval['Pos'];
                            if (isset($pos) && is_numeric($pos)) {
                                $found = true;
                                if (isset($status['song']) && ($pos < $status['song'])) {
                                    // the queue position is valid and it has been played before the current song
                                    // create a command to delete it
                                    $command = 'delete '.$pos;
                                    // send the command
                                    sendMpdCommand($socket, $command);
                                    // get the response, don't do anything with it
                                    readMpdResponse($socket);
                                }
                            }
                        }
                        closeMpdSocket($socket);
                        unset($command, $retval, $pos, $socket);
                    }
                }
                unset($hwDevices, $hwDevice, $cnt, $found);
            }
            // no break, collect the hardware device information
            // break;
        default:
            $hwInput = array();
            $ao = $redis->get('ao');
            if ($redis->hGet('hw_input', 'enable')) {
                $hwInputDevices = sysCmd('arecord -l | grep -i "^card"');
                if (is_array($hwInputDevices) && count($hwInputDevices)) {
                    foreach ($hwInputDevices as $hwInputDevice) {
                        $sysname = get_between_data($hwInputDevice, '[', ']');
                        $card = trim(get_between_data($hwInputDevice, 'card ', ': '));
                        $device = trim(get_between_data($hwInputDevice, '], device ', ': '));
                        $note = '';
                        // do not allow a device to be input and output
                        if ($sysname == $ao) {
                            // the hardware input is the same as the current hardware output device
                            // if the card and device are identical skip this card
                            $acard = json_decode($redis->hGet('acards', $ao), true);
                            $acardDevice = get_between_data($acard['device'], ':');
                            $hwDevice = $card.','.$device;
                            if ($acardDevice == $hwDevice) {
                                // skip this input device, it is being used as output
                                continue;
                            }
                        }
                        // warn the user if a card is input and output
                        $acard = json_decode($redis->hGet('acards', $sysname), true);
                        if (isset($acard) && is_array($acard) && isset($acard['device'])) {
                            $acardDevice = get_between_data($acard['device'], ':');
                            $hwDevice = $card.','.$device;
                            if ($acardDevice == $hwDevice) {
                                // skip this input device, it is being used as output
                                $note = ' - <strong>Warning: Use with caution, this device can be used for input and output</strong>';
                            }
                        }
                        if (!isset($device) || !strlen($device)) {
                            $device = '0';
                        }
                        $hwplug = 'hw:';
                        // get the hardware characteristics of the input audio card
                        $deviceHWparams = sysCmd('arecord --dump-hw-params -D hw:'.$card.','.$device.' 2>&1 | grep -iE "^[\s]*format: |^[\s]*channels: |^[\s]*rate: "');
                        foreach ($deviceHWparams as $deviceHWparam) {
                            // decode the hardware characteristics and choose for the best performance
                            $deviceHWparam = trim(strtolower($deviceHWparam));
                            list($param, $value) = explode(': ', $deviceHWparam, 2);
                            if (!isset($param) || !isset($value)) {
                                continue;
                            }
                            $param = trim($param);
                            $value = trim($value);
                            if (!$param || !$value) {
                                continue;
                            }
                            if ($param == 'format') {
                                // get the numeric values and spaces
                                $format = trim(preg_replace('/[^0-9\s]/', '', $value));
                                // all whitespace to a singe space
                                $format = preg_replace('!\s+!', ' ', $format);
                                // get the last format value
                                $parts = explode(' ', $format);
                                $format = end($parts);
                                // save the supported format
                                $formatS = $format;
                                if ($format < 16) {
                                    // less than 16 bit available, set it to 16 and use the plughw plugin
                                    $format = 16;
                                    $hwplug = 'plughw:';
                                }
                            }
                            if ($param == 'channels') {
                                // get the numeric values and spaces
                                $channels = preg_replace('/[^0-9\s]/', '', $value);
                                // all whitespace to a singe space
                                $channels = preg_replace('!\s+!', ' ', $channels);
                                $parts = explode(' ', $channels);
                                // save the supported channels
                                $channelsS = end($parts);
                                if (!strpos('# '.$channels.' ', ' 2 ')) {
                                    // 2 channels not available so we must use the plughw plugin
                                    $hwplug = 'plughw:';
                                }
                                // we always use 2 channels (stereo)
                                $channels = 2;
                            }
                            if ($param == 'rate') {
                                // get the numeric values and spaces
                                $rate = trim(preg_replace('/[^0-9\s]/', '', $value));
                                // all whitespace to a singe space
                                $rate = preg_replace('!\s+!', ' ', $rate);
                                // get the last format value
                                $parts = explode(' ', $rate);
                                $rate = end($parts);
                                // save the supported rate
                                $rateS = $rate;
                                if ($rate < 44100) {
                                    // rate is less than 44100, set it to 48000 (default) and use the plughw plugin
                                    $rate = 48000;
                                    $hwplug = 'plughw:';
                                }
                            }
                        }
                        $preferred = $rate.':'.$format.':'.$channels; // e.g. '48000:16:2'
                        $selected = $preferred;
                        // if the redis hash variable 'hw_input' 'format' is set use it to define the play format
                        if ($redis->hExists('hw_input', 'format')) {
                            // fixed input format defined, use it
                            // example format '44100:16:2', '48000:16:2'
                             $forced = trim($redis->hGet('hw_input', 'format'));
                             if ($forced != $preferred) {
                                 // its different from what we selected so use the plughw plugin
                                 $hwplug = 'plughw:';
                                 $selected = $forced;
                             }
                        }
                        $file = 'alsa://'.$hwplug.$card.','.$device.'?format='.$selected;
                        $hwInput[$file]['sysname'] = $sysname;
                        $hwInput[$file]['card'] = $card;
                        $hwInput[$file]['device'] = $device;
                        $hwInput[$file]['format'] = $format;
                        $hwInput[$file]['channels'] = $channels;
                        $hwInput[$file]['rate'] = $rate;
                        $hwInput[$file]['supported'] = $rateS.':'.$formatS.':'.$channelsS; // e.g. '48000:16:2'
                        if (isset($forced)) {
                            $hwInput[$file]['preferred'] = $preferred;
                            $hwInput[$file]['forced'] = $forced;
                        }
                        $hwInput[$file]['selected'] = $selected; // e.g. '48000:16:2'
                        $hwInput[$file]['hwplug'] = $hwplug;
                        $hwInput[$file]['name'] = get_between_data($hwInputDevice, ': ', ' [', 2);
                        $hwInput[$file]['description'] = get_between_data($hwInputDevice, '[', ']', 2);
                        $hwInput[$file]['file'] = $file;
                        $hwInput[$file]['note'] = $note;
                        unset($format, $channels, $rate, $preferred, $selected, $forced, $file);
                    }
                    unset($sysname, $card, $device, $hwplug, $note, $acard, $acardDevice, $hwDevice);
                }
            }
            $hwInputDevicesOld = json_decode($redis->hGet('hw_input', 'status'), true);
            foreach ($hwInputDevicesOld as $hwInputDeviceOld) {
                $found = false;
                foreach ($hwInput as $hwInputDevice) {
                    if ($hwInputDeviceOld['file'] == $hwInputDevice['file']) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    // the old hardware input is no longer valid, if it exists in the queue remove it
                    wrk_hwinput($redis, 'remove_from_queue', '', $hwInputDeviceOld['file']);
                }
            }
            if (!count($hwInput)) {
                $redis->hSet('hw_input', 'error', 'No devices found');
            } else {
                $redis->hDel('hw_input', 'error');
            }
            $redis->hSet('hw_input', 'status', json_encode($hwInput));
            ui_libraryHome($redis);
            unset($hwInput, $hwInputDevices, $hwInputDevice, $hwInputDevicesOld, $hwInputDeviceOld, $found, $ao);
            break;
    }
}

// function to start MPD, it checks that the connected USB storage devices are mounted before starting MPD
function start_mpd($redis)
// no parameters
{
    if (sysCmd('pgrep -x mpd 2>&1 || echo 0 | xargs')[0]) {
        // MPD is already running
        return;
    }
    // when udevil is not enabled the USB storage will never be mounted, don't wait
    if ($redis->get('udevil')) {
        // udevil is enabled, check that it is all mounted
        $loop=true;
        // set a limit of 30 cycles of the outside loop
        $limit=30;
        // within the loop sleep for 2 seconds before trying again
        $sleepSeconds=2;
        while ($loop && ($limit-- > 0)) {
            // get the connected USB devices
            $usbDevices = sysCmd('dir -1 /dev/sd* 2>&1');
            if (strpos(' '.strtolower($usbDevices[0]), 'no such file or directory')) {
                // no usb storage devices
                break;
            }
            // get the mounted USB devices
            $mountedUsbDevices = sysCmd('grep -i /dev/sd /proc/mounts | cut -d " " -f 1 | xargs')[0];
            // disable the outside loop
            $loop = false;
            foreach ($usbDevices as $usbDevice) {
                // loop through all the connected USB devices
                if (!strpos(' '.$mountedUsbDevices, $usbDevice)) {
                    // the USB device has not been mounted, enable the outside loop, sleep then break the inner loop
                    $loop = true;
                    sleep($sleepSeconds);
                    break;
                }
            }
        }
    }
    sysCmd('pgrep -x mpd || systemctl start mpd.socket');
}

// function to strip sysncronised encoding from lyrics
function strip_synchronised_lyrics($lyrics)
// the parameter lyrics is the full text of the lyrics including line ends
// the function returns the lyrics with HTML line breaks (<br>)
{
    // first convert line ends into standard Linux
    //  Windows to Linux
    $lyrics = str_replace("\r\n", "\n", $lyrics);
    //  Mac to Linux
    $lyrics = str_replace("\r", "\n", $lyrics);
    // replace whitespace with one space
    $lyrics = preg_replace("/[ \0\f\t\v]+/", " ", $lyrics);
    // remove leading and trailing spaces
    $lyrics = trim(preg_replace("/[ \0\f\t\v]*\n[ \0\f\t\v]*/", "\n", $lyrics));
    // remove a country code if present, always on the first line, format xxx||<lyric>
    if (strpos($lyrics, '||') == 3) {
        $lyrics = substr($lyrics, 5);
    }
    // add a leading and trailing new line
    $lyrics = "\n".$lyrics."\n";
    // remove timing information containing 'Walaoke extension: gender', format [mm:ss.xx]D: (the D can also be a F or a M)
    $lyrics = preg_replace("/\n[ \0\f\t\v]*\[..\:..\...\][FMDfmd]\:/", "\n", $lyrics);
    // remove standard timing information, format [mm:ss.xx]
    $lyrics = preg_replace("/\n[ \0\f\t\v]*\[..\:..\...\][ \0\f\t\v]*/", "\n", $lyrics);
    $lyrics = preg_replace("/\n[ \0\f\t\v]*\[..\:..\...\][ \0\f\t\v]*/", "\n", $lyrics);
    // remove timing information containing 'A2 extension: word time tags:', format <mm:ss.xx>
    $lyrics = preg_replace("/[ \0\f\t\v]*\<..\:..\...\>[ \0\f\t\v]*/", ' ', $lyrics);
    // convert it to an array to process metadata
    $lyrics = explode("\n", $lyrics);
    $artist = '';
    $title = '';
    $retval = '';
    foreach ($lyrics as $lyric) {
        // replace whitespace with one space & trim
        $lyric = trim(preg_replace("/[ \0\f\t\v]+/", ' ', $lyric));
        $lyric_test = ' '.strtolower($lyric);
        if (strpos($lyric_test, '[ar:') == 1) {
            $artist = trim(rtrim(substr($lyric, 4), ']'));
            continue;
        }
        if (strpos($lyric_test, '[ti:') == 1) {
            $title = trim(rtrim(substr($lyric, 4), ']'));
            continue;
        }
        if (preg_match("/\[..\:.*\]/", $lyric_test) == 1) {
            continue;
        }
        if (strpos($lyric_test, '[length:') == 1) {
            continue;
        }
        if (strpos($lyric_test, '[offset:') == 1) {
            continue;
        }
        $retval .= $lyric."\n";
    }
    $retval = trim($retval);
    if ($retval) {
        if ($artist && $title) {
            $retval = $artist."\n".$title."\n\n".$retval."\n";
        } else if ($title) {
            $retval = $title."\n\n".$retval."\n";
        } else {
            $retval = $retval."\n";
        }
        $retval = str_replace("\n", '<br>', $retval);
        // remove any control characters (hex 00 to 1F inclusive), delete character (hex 7F) and 'not assigned' characters (hex 81, 8D, 8F, 90 and 9D)
        $retval = preg_replace("/[\x{00}-\x{1F}\x{7F}\x{81}\x{8D}\x{8F}\x{90}\x{9D}]+/", '', $retval);
        // this could introduce double spaces, replace whitespace with one space
        $retval = preg_replace("/[\s]+/", " ", $retval);
    } else {
        $retval = '';
    }
    return $retval;
}

// calculate and return the percentage of words in string $search which occur in string $target
function count_word_occurancies($search, $target='')
// $search and $target are strings, return value is an integer (0 = no matches, 100 = all match)
// the string matching is case insensitive
// an empty $search string returns a 100% match
// an empty $target string returns a 0% match
{
    // convert parameters to lower case, replace whitespace with one space and trim
    $search = trim(preg_replace("/[\s]+/", ' ', strtolower($search)));
    $target = trim(preg_replace("/[\s]+/", ' ', strtolower($target)));
    // debug
    // echo "Search: '$search', Target: '$target'\n";
    runelog("[count_word_occurancies]", "Search: '$search', Target: '$target'");
    if (!strlen($search)) {
        return 100;
    }
    if (!strlen($target)) {
        return 0;
    }
    $searchParts = explode(' ', $search);
    $searchPartsCount = count($searchParts);
    $searchPartsFound = 0;
    foreach ($searchParts as $part) {
        // simple lower case word check
        if (stripos(' '.$target, $part)) {
            // match
            $searchPartsFound++;
        } else {
            // complex word check after converting special and complex characters to normal characters
            if (stripos(' '.squashCharacters($target), squashCharacters($part))) {
                // match
                $searchPartsFound++;
            }
        }
    }
    if ($searchPartsFound) {
        return round(min(($searchPartsFound * 100) / $searchPartsCount, 100),0);
    } else {
        return 0;
    }
}

// function to normalise a date format
function date_to_Y_m_d($date)
// the function accepts a 10 character date in YYYY-MM-DD or DD-MM-YYYY format with any separator (e.g. DD/MM/YYYY)
//  the function is inaccurate for formats YYYY-DD-MM and MM-DD-YYYY
// the function also accepts a longer date in the 'mmm DD YYYY' with its fields in any order and with any separator (e.g. format 'Dec 30, 2023')
// the function returns a date in YYYY-MM-DD format
{
    $date = trim(strtolower($date));
    //
    // assume the date is in the 10 character format YYYY-MM-DD or DD-MM-YYYY
    $date10character = true;
    // replace any non-numeric characters with a single hyphen and trim leading/trailing hyphens & spaces
    $date_wrk = trim(preg_replace('/[^0-9]+/', '-', $date), '- ');
    if (substr_count($date_wrk, '-') == 2) {
        // there are two hyphens
        if (strlen($date_wrk) == 10) {
            // the string length is 10
            if (strpos($date_wrk, '-') == 2) {
                // looks like DD-MM-YYYY
                list($d, $m, $y) = explode('-', $date_wrk);
            } else if (strpos($date_wrk, '-') == 4) {
                // looks like YYYY-MM-DD
                list($y, $m, $d) = explode('-', $date_wrk);
            }
        }
    }
    //
    if (!isset($y) || !isset($m) || !isset($d) || (strlen($y) != 4) || (strlen($m) != 2) || (strlen($d) != 2)) {
        // the date is not in the format YYYY-MM-DD or DD-MM-YYYY
        $date10character = false;
        // now assume the date is in the format 'mmm DD, YYYY'
        // replace any non-numeric or non-lower-case-alpha characters with a single hyphen and trim leading/trailing hyphens & spaces
        $date = trim(preg_replace('/[^0-9a-z]+/', '-', $date), '- ');
        if (substr_count($date, '-') != 2) {
            // invalid date format
            return false;
        }
        // there are two hyphens, split the sting on the hyphens
        $list = explode('-', $date);
        // walk through the list
        foreach ($list as $element) {
            if (strlen($element) == 1) {
                // could be a D, should not happen
                $d = '0'.$element;
            } else if (strlen($element) == 2) {
                // this is a DD
                $d = $element;
            } else if (strlen($element) == 3) {
                // this is a mmm
                $mmm = $element;
            } else if (strlen($element) == 4) {
                // this is a YYYY
                $y = $element;
            }
        }
        if (!isset($d) || !isset($mmm) || !isset($y)) {
            //invalid date format
            return false;
        }
        // convert the alpha month to numeric
        $months = array('', 'jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec');
        $m = array_search($mmm, $months);
        if (strlen($m) == 1) {
            // month contains one character, add a leading zero
            $m = '0'.$m;
        }
    }
    if ((strlen($y) != 4) || ($y < 1)) {
        // invalid date format
        return false;
    }
    if ((strlen($m) != 2) || ($m < 1)) {
        // invalid date format
        return false;
    }
    if ((strlen($d) != 2) || ($d < 1)) {
        // invalid date format
        return false;
    }
    if (($m > 12) && $date10character) {
        // day and month seem to be interchanged, this is very risky workaround, only do it for the 10 character date format
        $x = $m;
        $m = $d;
        $d = $x;
    }
    if ($m > 12 || ($d > 31)) {
        // invalid date format
        return false;
    }
    $date = $y.'-'.$m.'-'.$d;
    return $date;
}

// function which runs smart monitor tools
function wrk_smt($redis)
// S.M.A.R.T. monitoring tools
//  will test each attached hard disk and tape unit
//  test only when music is playing
//  first test after 480 cycles (= +/- 4 minutes) and then at a rate of once per +/-2400 cycles (= +/-20 minutes)
//  when a disk error is detected reduce the test every +/-180 cycles (= +/-1.5 minutes)
// Why test only when something is playing?
//  a) someone needs see the error messages and
//  b) most hard disks will be usb drives which go to sleep when not in use, let them sleep
{
    if (is_playing($redis)) {
        // something is playing
        if (sysCmd('command -v smartctl')[0]) {
            // monitoring tools software is installed
            // scan for hard disks
            $drive_list = sysCmd('smartctl --scan-open -- -H -i -s on | grep -v aborted');
            if (isset($drive_list) && !empty($drive_list)) {
                foreach($drive_list as $drive) {
                    // for each connected drive
                    $drive = trim($drive);
                    if ($drive != "") {
                        $command = "smartctl ".$drive." | grep -i -E 'Model:|Capacity:|-health self-|SMART support is'";
                        $self_check = sysCmd($command);
                        // the self_check variable now has 5 lines the 1st line must contain the word 'Available' - 'SMART support is: Available...'
                        If (!empty($self_check)) {
                            $smart_avalable = false;
                            $smart_enabled = false;
                            $smart_result = false;
                            $smart_good = false;
                            foreach ($self_check as $self_check_line) {
                                $self_check_line = trim($self_check_line);
                                if ($self_check_line != "") {
                                    // now looking for the lines
                                    // 'SMART support is: Available...' and
                                    // 'SMART support is: Enabled'
                                    if (strpos(' '.$self_check_line, 'SMART support is')) {
                                        if (strpos(' '.$self_check_line, 'Available')) {
                                            $smart_avalable = true;
                                        } else if (strpos(' '.$self_check_line, 'Enabled')) {
                                            $smart_enabled = true;
                                        }
                                    }
                                    // now looking for the line containing something like this:
                                    // 'SMART overall-health self-assessment test result: PASSED'
                                    // actually looking for '-health self-' together with 'OK' or 'PASSED'
                                    if (strpos(' '.$self_check_line, '-health self-')) {
                                        // there is a result
                                        $smart_result = true;
                                        if (strpos(' '.$self_check_line, 'OK')) {
                                            $smart_good = true;
                                        } else if (strpos(' '.$self_check_line, 'PASSED')) {
                                            $smart_good = true;
                                        }
                                    }
                                }
                            }
                            if ($smart_avalable && $smart_enabled && $smart_result && !$smart_good) {
                                // SMART is available, enabled, there is a result and it is not good
                                // display the information
                                ui_notifyError($redis, 'Disk errors - Action required', implode("\n", $self_check));
                                // set the disk_error flag to true
                                $redis->set('disk_error', 1);
                            }
                        }
                    }
                    unset($self_check);
                    unset($command);
                }
                unset($drive);
                unset($drive_list);
            }
        }
    }
    unset($retval);
    if ($redis->get('disk_error')) {
        // set the disk check frequency to +/-180 cycles (= +/-1.5 minutes)
        $redis->set('savecpuSmt', 170 + rand(0, 20));
    } else {
        // set the disk check frequency to +/-2400 cycles (= +/20 minutes)
        $redis->set('savecpuSmt', 2350 + rand(0, 100));
    }
}

// function to decode vc4 hdmi description
function decode_vc4_hdmi_desc($string)
//
{
    $stringL = strtolower(trim(preg_replace('/[\s-]*/', '', $string)));
    if (!strpos(' '.$stringL, 'vc4')) {
        return $string;
    }
    if (strpos(' '.$stringL, 'hdmi0')) {
        return 'HDMI 0';
    } else if (strpos(' '.$stringL, 'hdmi1')) {
        return 'HDMI 1';
    } else if (strpos(' '.$stringL, 'hdmi2')) {
        return 'HDMI 2';
    } else if (strpos(' '.$stringL, 'hdmi')) {
        return 'HDMI';
    } else {
        $retval = strtoupper(get_between_data(trim($stringL), vc4));
        if (!srtlen($retval)) {
            $retval = $string;
        }
        return $retval;
    }
}

// function to limit the sample rate for vc4 hdmi ouput
function set_vc4_hdmi_allowed_formats($redis)
// the function can only detect limitations when the vc4 hdmi output is actually being used
//  we only set the reduced allowed formats, we never reset it to its original value
//  when mpd restarts the allowed formats will revert to their original settings
//  its only relevant for MPD output, all other output streams are fixed to a sample rate of
//      44100hz or 48000hz, there are always supported
// by doing this we use soxr to do resampling, otherwise the awful dmix will be used
// hdmi output can normally manages 44100:24:*, 48000:24:*, 32000:24:*, 88200:24:*, 96000:24:*, 176400:24:* & 192000:24:*
//  the introduction of vc4 has reduces it to 44100:24:2 & 48000:24:2, hopefully this will change
{
    $ao =$redis->get('ao');
    if (!strpos(' '.strtolower($ao), 'vc4') || !strpos(' '.strtolower($ao), 'hdmi')) {
        // output is not vc4 hdmi, nothing to do
        return;
    }
    $currentSetting = sysCmd("mpc outputs | sed -n '/".$ao."/, /Output/{ /Output/!p }' | grep -i 'allowed_formats=' | xargs")[0];
    if (!isset($currentSetting) || !$currentSetting) {
        // it should have a value, if not, its incorrect
        return;
    }
    if (strtolower($currentSetting) != strtolower('allowed_formats=48000:24:* 44100:24:* 48000:16:* 44100:16:*')) {
        // the current setting is incorrect, it should be changed
        $playing = sysCmd('grep -vihs closed /proc/asound/card?/pcm?p/sub?/hw_params | xargs')[0];
        if (!isset($playing) || !$playing) {
            // nothing is playing, we cant do anything
            return;
        }
        if (strpos(' '.strtolower($playing), strtolower('format: IEC958_SUBFRAME_LE subformat: STD'))) {
            // the profile of the output matches the one which needs to be changed
            sysCmd('mpc outputset '.$ao.' allowed_formats="48000:24:* 44100:24:* 48000:16:* 44100:16:*"');
        }
    }
}

// function to control alsa equaliser
function wrk_alsa_equaliser($redis, $action, $args=null, $jobID=null)
//
{
    if ($redis->hget('alsa_equaliser', 'enable')) {
        $alsaEqualiserEnabled = true;
    } else {
        $alsaEqualiserEnabled = false;
    }
    if ($redis->get('activePlayer') == 'MPD') {
        $mpdPlayer = true;
    } else {
        $mpdPlayer = false;
    }
    switch ($action) {
        case 'start':
            // no break
            // break;
        case 'stop':
            // no break
            // break;
        case 'update':
            if (!$redis->hExists('alsa_equaliser', 'bands') || !$redis->hExists('alsa_equaliser', 'bands_number')) {
                wrk_alsa_equaliser($redis, 'initialise_bands');
            }
            if (isset($args) && (is_array($args) || is_object($args))) {
                foreach ($args as $key => $value) {
                    if (($key = 'bands_number') && ($value != $redis->hGet('alsa_equaliser', 'bands_number'))) {
                        if ($value = 20) {
                            wrk_alsa_equaliser($redis, 'bands_10_to_20');
                        }
                    }
                    $redis->hSet('alsa_equaliser', $key, $value);
                }
            }
            if ($action == 'start') {
                $redis->hSet('alsa_equaliser', 'enable', 1);
                ui_notify($redis, "Alsa Equaliser", 'Enabled');
                if (isset($jobID) && $jobID) {
                    $redis->sRem('w_lock', $jobID);
                }
                wrk_snd_aloop($redis, 'add', 'alsa_equaliser');
            } else if ($action == 'stop') {
                $redis->hSet('alsa_equaliser', 'enable', 0);
                ui_notify($redis, "Alsa Equaliser", 'Disabled');
                if (isset($jobID) && $jobID) {
                    $redis->sRem('w_lock', $jobID);
                }
                wrk_snd_aloop($redis, 'remove', 'alsa_equaliser');
            }
            unset($key, $value);
            break;
        case 'initialise_bands':
            $frequencies = array('31 Hz', '45 Hz', '63 Hz', '90 Hz', '125 Hz', '180 Hz', '250 Hz', '361 Hz', '500 Hz', '721 Hz', '1000 Hz', '1443 Hz', '2000 Hz', '2885 Hz', '4000 Hz', '5771 Hz', '8000 Hz', '11542 Hz','16000 Hz', '23083 Hz');
            $cnt = 0;
            if ($redis->hExinst('alsa_equaliser', 'init_value')) {
                $init_value = $redis->hGet('alsa_equaliser', 'init_value');
            } else {
                $init_value = 66;
            }
            foreach ($frequencies as $frequency) {
                if (strlen($cnt) == 1) {
                    $number = '0'.$cnt;
                } else {
                    $number = $cnt;
                }
                $bands[$cnt]['number'] = $number;
                $bands[$cnt]['frequency'] = $frequency;
                $bands[$cnt]['value'] = $init_value;
                $cnt++;
            }
            $redis->hSet('alsa_equaliser', 'bands', json_encode($bands));
            $redis->hSet('alsa_equaliser', 'bands_number', 10);
            unset($frequencies, $frequency, $cnt, $init_value, $number, $bands);
            break;
        case 'bands_10_to_20':
            $workList = array(1, 3, 5, 7, 9, 11, 13, 15, 17);
            $bands = json_decode($redis->hGet('alsa_equaliser', 'bands'), true);
            foreach ($workList as $element) {
                $bands[$element]['value'] = round(($bands[$element - 1]['value'] + $bands[$element + 1]['value'])/2);
            }
            $bands['19']['value'] = $bands['18']['value'];
            $redis->hSet('alsa_equaliser', 'bands', json_encode($bands));
            unset($workList, $element, $bands);
            break;
        case 'select':
            break;
        case 'deselect':
            break;
        case 'set_alsa':
            if ($alsaEqualiserEnabled) {
                if ($redis->hGet('alsa_equaliser', 'bands_number') == 10) {
                    $workList = array(0, 2, 4, 6, 8, 10, 12, 14, 16, 18);
                } else if ($redis->hGet('alsa_equaliser', 'bands_number') == 20) {
                    $workList = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19);
                } else {
                    ui_notifyError('Alsa Equaliser', 'Error: Not initialised');
                    break;
                }
                $bands = json_decode($redis->hGet('alsa_equaliser', 'bands'), true);
                foreach ($worklist as $element) {
                    // command format: amixer -D equal -q set '00. 31 Hz' 39
                    sysCmd("amixer -D equal -q set '".$bands[$element]['number'].". ".$bands[$element]['frequency']."' ".$bands[$element]['value']);
                }
            }
            break;
        case 'save_alsa':
            if ($alsaEqualiserEnabled) {
                if ($redis->hGet('alsa_equaliser', 'bands_number') == 10) {
                    $workList = array(0, 2, 4, 6, 8, 10, 12, 14, 16, 18);
                } else if ($redis->hGet('alsa_equaliser', 'bands_number') == 20) {
                    $workList = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19);
                } else {
                    ui_notifyError('Alsa Equaliser', 'Error: Not initialised');
                    break;
                }
                $bands = json_decode($redis->hGet('alsa_equaliser', 'bands'), true);
                foreach ($worklist as $element) {
                    // command format: amixer -D equal -q get '00. 31 Hz'
                    $bands[$element]['value'] = sysCmd("amixer -D equal -q get '".$bands[$element]['number'].". ".$bands[$element]['frequency']."'")[0];
                }
                $redis->hSet('alsa_equaliser', 'bands', json_encode($bands));
            }
            break;
        case 'set_output':
            if ($alsaEqualiserEnabled) {
                if (isset($args) && $args) {
                    $ao = $args;
                } else {
                    $ao = $redis->get('ao');
                }
                if (isset($ao) && $ao) {
                    $acard = json_decode($resid->hGet('acards', $ao), true);
                    if (isset($acard) && is_array($acard) && $acard['device']) {
                        // $search = '^pcm.plugequal \{\ntype equal;\nslave.pcm \"plughw:.*'; // for my editor a matching bracket }
                        // $replace = 'pcm.plugequal \{\ntype equal;\nslave.pcm \"'.str_replace('hw:', 'plughw:', $acard['device']).'\";'; // for my editor a matching bracket }
                    }
                }
            }
            break;
        default:
            ui_notifyError('Alsa Equaliser', 'Internal error: Invalid function call');
            break;
    }
}

// Function to control the loading of the ALSA loopback connector
function wrk_snd_aloop($redis, $action, $component=null)
// when the redis hash variable 'snd-aloop' has a value the loopback connector will loaded
//  when the redis hash variable 'snd-aloop' has no a value the loopback connector will unloaded on the next boot
// the loopback connector is used by several sound processing components, e.g. ALSA Equaliser, SnapCast, etc.
//  when any one of the sound processing components is enabled the loopback connector will be activated
//  when all sound processing components are disabled the loopback connector will be deactivated on the next boot
// parameters:  $redis - required
//              $action = add, remove, check - required
//              $component = the sound processing component, e.g. alsa_equaliser, snapcast_server, etc. - required for add and remove
{
    switch ($action) {
        case 'add':
            if (isset($component) && $component) {
                $redis->hSet('snd-aloop', $component, 1);
            }
            break;
        case 'remove':
            if (isset($component) && $component) {
                $redis->hDel('snd-aloop', $component);
            }
            break;
        case 'check';
            break;
        default:
            ui_notifyError('Alsa Loopback Adaptor', 'Internal error: Invalid function call');
            break;
    }
    if (sysCmd('grep -ic "snd-aloop" /etc/modules')[0]) {
        // loopback connecter loaded
        if (!$redis->exists('snd-aloop')) {
            // the loopback connector is disabled, remove the loopback connector on the next boot
            sysCmd("sed -i '/snd-aloop/d' /etc/modules");
        }
    } else {
        // loopback connecter not loaded
        if ($redis->exists('snd-aloop')) {
            // add the loopback connector on the next boot
            sysCmd('echo "snd-aloop" >> /etc/modules');
            // add the loopback connector now
            sysCmd('modprobe snd-aloop');
            // modify the mpd loopback configuration
            wrk_mpd_loopback($redis, 'restart_mpd');
        }
    }
}

// this function modifies the mpd sub-config file called /etc/mpd_loopback.conf, which configures outputs to the loopcack connector
//  the mpd sub-config file is included into the mpd config file
function wrk_mpd_loopback($redis, $action=null)
// the /etc/mpd_loopback.conf file is empty when the loopback connector is not activated
// when the loopback connector is activated it contains configuration files for the three sound processing components:
//  alsa_equaliser      uses loopback device 0 subdevice 0 input and device 1 subdevice 0 output
//  snapcast_client     uses loopback device 0 subdevice 1 input and device 1 subdevice 1 output
//  brutefir            uses loopback device 0 subdevice 2 input and device 1 subdevice 2 output
// if a hardware volume control is present it is retained pointing to the actual output card
//  on an output card change the mpd sub-config file /etc/mpd_loopback.conf is recreated in order to retain
//  the hardware volume controls and regenerate the alsa output configuration files of the sound processing components
// parameters:  $redis
//              $action     = 'restart_mpd' mpd will be restarted if required
// return value: 'unchanged' or 'changed'
{
    // file name
    $fileName = '/etc/mpd_loopback.conf';
    $fileNameTmp = '/tmp/mpd_loopback.conf';
    clearstatcache(true, $FileName);
    if (file_exists($fileName)) {
        $fileNameMD5 = md5_file($fileName);
    } else {
        $fileNameMD5 = '';
    }
    // set up the file header
    $output = '';
    $output .= "##########################################################\n";
    $output .= "#  This sub mpd configuration file is part of RuneAudio  #\n";
    $output .= "#  ----------------------------------------------------  #\n";
    $output .= "# Filename: /etc/mpd_loopback.conf                       #\n";
    $output .= "# This file is automatically generated and should not be #\n";
    $output .= "# manually edited. It contains the MPD output            #\n";
    $output .= "# definitions for the loopback connector devices which   #\n";
    $output .= "# are input devices for sound processing.                #\n";
    $output .= "# The file is automatically included into /etc/mpd.conf  #\n";
    $output .= "##########################################################\n";
    $output .= "#\n";
    // determine whether the loopback connector is enabled/disabled
    $loopbackEnabled = sysCmd("aplay -l | grep -i '^card' | grep -ic 'loopback'");
    if (!$loopbackEnabled) {
        // the loopback connector is disabled, create an empty mpd sub-config file called /etc/mpd_loopback.conf
        $output .= "# Loopback connector is disabled, this file has no active contents.\n";
        $output .= "#\n";
        file_put_contents($fileNameTmp, $output);
    } else {
        // the loopback connector is enabled, create the mpd outputs
        // get the current audio card
        $ao = $redis->get('ao');
        $acard = json_decode($redis->hGet('acards', $ao), true);
        // get the mpd configuration
        $mpdcfg = $redis->hgetall('mpdconf');
        //
        $output .= "# ALSA Equaliser output\n";
        $output .="audio_output {\n";
        // $output .="name \t\t\"".$card_decoded->name."\"\n";
        $output .="\tname \t\t\"ALSA_equaliser\"\n";
        $output .="\ttype \t\t\"alsa\"\n";
        $output .="\tdevice \t\t\"plughw:Loopback,0,0\"\n";
        if ($mpdcfg['mixer_type'] == 'hardware') {
             if (isset($card['mixer_control'])) {
                $output .="\tmixer_control \t\"".$acard['mixer_control']."\"\n";
                $output .="\tmixer_type \t\"hardware\"\n";
                $output .="\tmixer_device \t\"".substr($acard['device'], 0, 4)."\"\n";
                if (isset($mpdcfg['replaygain']) && ($mpdcfg['replaygain'] != 'off') && isset($mpdcfg['replaygainhandler'])) {
                    // when replay gain is enabled and there is a hardware mixer, then use the mixer as reply gain handler
                    $output .="\treplay_gain_handler \"".$mpdcfg['replaygainhandler']."\"\n";
                }
            } else {
                if (!isset($sub_interface) && isset($card_decoded['mixer_control'])) {
                    $output .="\tmixer_control \t\"".$card_decoded['mixer_control']."\"\n";
                } else {
                    $output .="\tmixer_type \t\"software\"\n";
                }
            }
            // $output .="\tmixer_index \t\"0\"\n";"\t\t  \t\"0\"\n";
        } else if ($volumeControl) {
            $output .="\tmixer_type \t\"software\"\n";
        } else {
            $output .="\tmixer_type \t\"none\"\n";
        }
        // test if there is an option to set in mpd.conf
        // for example ODROID C1 needs "card_option":"buffer_time\t\"0\""
        if (isset($card_decoded['card_option'])) {
            $output .= "\t".$card_decoded['card_option']."\n";
        }
        // test if there is an allowed_formats to set in mpd.conf
        // for example the ES9023 audio card expects 24 bit input
        if (isset($acard['allowed_formats'])) {
            $output .= "\tallowed_formats\t\"".$card_decoded['allowed_formats']."\"\n";
        }
        $output .="\tauto_resample \t\"no\"\n";
        $output .="\tauto_format \t\"no\"\n";
        $output .="\tenabled \t\"no\"\n";
        $output .="}\n";
        //
        $output .= "# Snapcast Server output\n";
        $output .="audio_output {\n";
        // $output .="name \t\t\"".$card_decoded->name."\"\n";
        $output .="\tname \t\t\"Snapcast_Server\"\n";
        $output .="\ttype \t\t\"alsa\"\n";
        $output .="\tdevice \t\t\"plughw:Loopback,0,1\"\n";
        if ($mpdcfg['mixer_type'] == 'hardware') {
             if (isset($card['mixer_control'])) {
                $output .="\tmixer_control \t\"".$acard['mixer_control']."\"\n";
                $output .="\tmixer_type \t\"hardware\"\n";
                $output .="\tmixer_device \t\"".substr($acard['device'], 0, 4)."\"\n";
                if (isset($mpdcfg['replaygain']) && ($mpdcfg['replaygain'] != 'off') && isset($mpdcfg['replaygainhandler'])) {
                    // when replay gain is enabled and there is a hardware mixer, then use the mixer as reply gain handler
                    $output .="\treplay_gain_handler \"".$mpdcfg['replaygainhandler']."\"\n";
                }
            } else {
                if (!isset($sub_interface) && isset($card_decoded['mixer_control'])) {
                    $output .="\tmixer_control \t\"".$card_decoded['mixer_control']."\"\n";
                } else {
                    $output .="\tmixer_type \t\"software\"\n";
                }
            }
            // $output .="\tmixer_index \t\"0\"\n";"\t\t  \t\"0\"\n";
        } else if ($volumeControl) {
            $output .="\tmixer_type \t\"software\"\n";
        } else {
            $output .="\tmixer_type \t\"none\"\n";
        }
        // test if there is an option for mpd.conf is set
        // for example ODROID C1 needs "card_option":"buffer_time\t\"0\""
        if (isset($card_decoded['card_option'])) {
            $output .= "\t".$card_decoded['card_option']."\n";
        }
        // test if there is an allowed_formats for mpd.conf is set
        // for example the ES9023 audio card expects 24 bit input
        if (isset($acard['allowed_formats'])) {
            // Snap Server has a fixed output sample rate of 48000Hz, 16bit, 2 channels (stereo)
            $output .= "\tallowed_formats\t\"48000:16:2\"\n";
        }
        $output .="\tauto_resample \t\"no\"\n";
        $output .="\tauto_format \t\"no\"\n";
        $output .="\tenabled \t\"no\"\n";
        $output .="}\n";
        //
        $output .= "# Brutefir output\n";
        $output .="audio_output {\n";
        // $output .="name \t\t\"".$card_decoded->name."\"\n";
        $output .="\tname \t\t\"Brutefir\"\n";
        $output .="\ttype \t\t\"alsa\"\n";
        $output .="\tdevice \t\t\"plughw:Loopback,0,2\"\n";
        if ($mpdcfg['mixer_type'] == 'hardware') {
             if (isset($card['mixer_control'])) {
                $output .="\tmixer_control \t\"".$acard['mixer_control']."\"\n";
                $output .="\tmixer_type \t\"hardware\"\n";
                $output .="\tmixer_device \t\"".substr($acard['device'], 0, 4)."\"\n";
                if (isset($mpdcfg['replaygain']) && ($mpdcfg['replaygain'] != 'off') && isset($mpdcfg['replaygainhandler'])) {
                    // when replay gain is enabled and there is a hardware mixer, then use the mixer as reply gain handler
                    $output .="\treplay_gain_handler \"".$mpdcfg['replaygainhandler']."\"\n";
                }
            } else {
                if (!isset($sub_interface) && isset($card_decoded['mixer_control'])) {
                    $output .="\tmixer_control \t\"".$card_decoded['mixer_control']."\"\n";
                } else {
                    $output .="\tmixer_type \t\"software\"\n";
                }
            }
            // $output .="\tmixer_index \t\"0\"\n";"\t\t  \t\"0\"\n";
        } else if ($volumeControl) {
            $output .="\tmixer_type \t\"software\"\n";
        } else {
            $output .="\tmixer_type \t\"none\"\n";
        }
        // test if there is an option for mpd.conf is set
        // for example ODROID C1 needs "card_option":"buffer_time\t\"0\""
        if (isset($card_decoded['card_option'])) {
            $output .= "\t".$card_decoded['card_option']."\n";
        }
        // test if there is an allowed_formats for mpd.conf is set
        // for example the ES9023 audio card expects 24 bit input
        if (isset($acard['allowed_formats'])) {
            // Snap Server has a fixed output sample rate of 96000Hz, 32bit, 2 channels (stereo)
            $output .= "\tallowed_formats\t\"96000:32:2\"\n";
        }
        $output .="\tauto_resample \t\"no\"\n";
        $output .="\tauto_format \t\"no\"\n";
        $output .="\tenabled \t\"no\"\n";
        $output .="}\n";
        //
    }
    if ($fileNameMD5 == md5_file($fileNameTmp)) {
        // old and new files are the same, remove the new file and return
        unlink($fileNameTmp);
        return 'unchanged';
    } else {
        // new  file has changed
        rename($fileNameTmp, $fileName);
        if ($action == 'restart_mpd') {
            wrk_mpdconf($redis, 'forcerestart');
        }
        return 'changed';
    }
}
