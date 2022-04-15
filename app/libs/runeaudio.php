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
// MPD: "OK MPD x.xx.xx\n" (14 to 15 bytes, as of version 0.22.0)
// SPOP: "spop x.x.x\n" (11 bytes, as of version 0.0.1)
// Where x is a numeric vanue (version number)

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
        runelog('[open]['.$sock['description'].']\t>>>>>> OPEN MPD SOCKET - **BURST MODE 2 (non blocking)** <<<<<<','');
    } else if ($type === 1) {
        socket_set_block($$sockVarName);
        runelog('[open]['.$sock['description'].']\t>>>>>> OPEN MPD SOCKET - **BURST MODE 2 (blocking)** <<<<<<','');
    } else {
        socket_set_block($$sockVarName);
        runelog('[open]['.$sock['description'].']\t>>>>>> OPEN MPD SOCKET - **NORMAL MODE (blocking)** <<<<<<','');
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
            runelog('[open]['.$sock['description'].']\t>>>>>> MPD OPEN SOCKET ERROR REPORTED - Greeting response: ', $header);
            // ui_notifyError('MPD open error: '.$sock['description'],'Greeting response = '.$header);
            closeMpdSocket($sock);
            return false;
        }
        runelog('[open]['.$sock['description'].']\t>>>>>> OPEN MPD SOCKET - Greeting response: '.$header.'<<<<<<','');
        return $sock;
    } else {
        runelog('[open]['.$sock['description'].']\t>>>>>> MPD SOCKET ERROR: '.socket_last_error($$sockVarName).' <<<<<<','');
        // ui_notifyError('MPD sock: '.$sock['description'],'socket error = '.socket_last_error($$sockVarName));
        closeMpdSocket($sock);
        return false;
    }
}

function closeMpdSocket($sock, $retainSockVarName = false)
// when $retainSockVarName is set to true the socket variable name will not be unset
//  this is used when reopening a timed out socket with the same name
{
    if (!is_array($sock) || !isset($sock['sockVarName'])) {
        if (!isset($sock['description'])) {
            $sock['description'] = 'UNSET SOCKET';
        }
        runelog('[close]['.$sock['description'].'\t<<<<<< MPD SOCKET ERROR: Invalid parameters - Continuing >>>>>>','');
    }
    // define the socket variable name as global
    $sockVarName = $sock['sockVarName'];
    global $$sockVarName;
    if (!isset($$sockVarName)) {
        if (!isset($sock['description'])) {
            $sock['description'] = 'UNSET SOCKET';
        }
        runelog('[close]['.$sock['description'].'\t<<<<<< MPD SOCKET ERROR: Invalid socket variable name - Continuing >>>>>>','');
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
        runelog('[close]['.$sock['description'].']\t<<<<<< MPD SOCKET ERROR: Looks like the socket is already closed - Continuing >>>>>>', '');
    }
    catch (Exception $e) {
        // Executed only in PHP 5 and lower, will not be reached in PHP 7 and higher
        runelog('[close]['.$sock['description'].']\t<<<<<< MPD SOCKET ERROR: Looks like the socket is already closed - Continuing >>>>>>', '');
    }
    runelog('[close]['.$sock['description'].']\t<<<<<< MPD SOCKET CLOSE >>>>>>', '');
    if ($retainSockVarName) {
        // remove the global variable containing the socket resource or object
        unset($$sockVarName);
        unset($GLOBALS[$sockVarName]);
    } else {
        runelog('[close]['.$sock['description'].']\t<<<<<< MPD SOCKET NAME RETAINED ON CLOSE >>>>>>', '');
    }
}

function sendMpdCommand(&$sock, $cmd)
// note that &$sock is passed by reference, it can be reset to new values
{
    if (!is_array($sock) || !isset($sock['sockVarName'])) {
        if (!isset($sock['description'])) {
            $sock['description'] = 'UNSET SOCKET';
        }
        runelog('[send]['.$sock['description'].'\t<<<<<< MPD SOCKET ERROR: Invalid parameters >>>>>>','');
        return false;
    }
    // define the socket variable name as global
    $sockVarName = $sock['sockVarName'];
    global $$sockVarName;
    if (!isset($$sockVarName)) {
        if (!isset($sock['description'])) {
            $sock['description'] = 'UNSET SOCKET';
        }
        runelog('[send]['.$sock['description'].'\t<<<<<< MPD SOCKET ERROR: Invalid socket variable name >>>>>>','');
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
                    runelog('[send]['.$sock['description'].']\t<<<<<< MPD SOCKET SEND ERROR (2nd TRY) ('.socket_strerror(socket_last_error($$sockVarName)).') >>>>>>','');
                    return false;
                }
            } else {
                runelog('[send]['.$sock['description'].']\t<<<<<< MPD SOCKET SEND ERROR (REOPEN) ('.socket_strerror(socket_last_error($$sockVarName)).') >>>>>>','');
                return false;
            }
        } else {
            runelog('[send]['.$sock['description'].']\t<<<<<< MPD SOCKET SEND ERROR ('.socket_strerror(socket_last_error($$sockVarName)).') >>>>>>','');
            return false;
        }
    }
    runelog('[send]['.$sock['description'].']\t<<<<<< MPD SOCKET SEND : ', $cmd);
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
        runelog('[read]['.$sock['description'].']\t<<<<<< MPD SOCKET ERROR: Invalid parameters >>>>>>','');
        return false;
    }
    // define the socket variable name as global
    $sockVarName = $sock['sockVarName'];
    global $$sockVarName;
    if (!isset($$sockVarName)) {
        if (!isset($sock['description'])) {
            $sock['description'] = 'UNSET SOCKET';
        }
        runelog('[read]['.$sock['description'].'\t<<<<<< MPD SOCKET ERROR: Invalid socket variable name >>>>>>','');
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
        // runelog('[read]['.$sock['description'].'][pre-loop]['.$sock['type'].']\t<<<<<< MPD READ: ',$socket_activity);
        $end = 0;
        while($end === 0) {
            // the next line is php 7 and php 8 compatible TO-DO at some time in the future the php 7 part can be removed
            if ((($phpVersion < 8) && is_resource($$sockVarName)) || (($phpVersion > 7) && is_object($$sockVarName))) {
                $read = socket_read($$sockVarName, $buff);
                if (!isset($read) || $read === false) {
                    $output = socket_strerror(socket_last_error($$sockVarName));
                    runelog('[read]['.$sock['description'].'][read-loop]['.$sock['type'].']\t<<<<<< MPD READ SOCKET DISCONNECTED: ',$output);
                    break;
                }
            } else {
                runelog('[read]['.$sock['description'].'][read-loop]['.$sock['type'].']\t<<<<<< MPD READ INVALID SOCKET: ',$sock['description']);
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
        // runelog('[read]['.$sock['description'].'][pre-loop]['.$sock['type'].']\t<<<<<< MPD READ: ',$socket_activity);
        do {
            // debug
            // $i++;
            // $elapsed = microtime(true);
            // read data from socket
            // the next line is php 7 and php 8 compatible TO-DO at some time in the future the php 7 part can be removed
            if ((($phpVersion < 8) && is_resource($$sockVarName)) || (($phpVersion > 7) && is_object($$sockVarName))) {
                $read = socket_read($$sockVarName, $buff);
            } else {
                runelog('[read]['.$sock['description'].'][read-loop]['.$sock['type'].']\t<<<<<< MPD READ INVALID SOCKET: ',$sock['description']);
                break;
            }
            // debug
            // runelog('socket_read status', $read);
            if (!isset($read) || $read === '' || $read === false) {
                $output = socket_strerror(socket_last_error($$sockVarName));
                // debug
                runelog('[read]['.$sock['description'].'][read-loop]['.$sock['type'].']\t<<<<<< MPD READ SOCKET DISCONNECTED: ',$output);
                break;
            } else {
                runelog('[read]['.$sock['description'].'][read-loop]['.$sock['type'].']\t<<<<<< MPD READ SUCCESS : ','');
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
        // runelog('[read]['.$sock['description'].'][pre-loop]['.$sock['type'].']\t<<<<<< MPD READ: ',$socket_activity);
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
                runelog('[read]['.$sock['description'].'][read-loop]['.$sock['type'].']\t<<<<<< MPD READ SOCKET DISCONNECTED : ',$output);
                break;
            } else {
                runelog('[read]['.$sock['description'].'][read-loop]['.$sock['type'].']\t<<<<<< MPD READ SUCCESS : ','');
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
        // convert it to a string delimited by colons, no duplicates, e.g. 'player:playlist:mixer:OK'
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
            // ui_notifyError('SPOP sock: '.$sock['resource'],'socket error = '.socket_last_error($sock));
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
            // ui_notifyError('SPOP sock: '.$sock['resource'],'socket error = '.socket_last_error($sock));
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
    //ui_notify('COMMAND GIVEN','CMD = '.$cmd,'.9');
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
    if ($change == sendSpopIdle($sock)) {
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
    switch ($browsemode) {
        case 'file':
            if (isset($query) && !empty($query)){
                sendMpdCommand($sock, 'lsinfo "'.html_entity_decode($query).'"');
            } else {
                sendMpdCommand($sock, 'lsinfo');
            }
            break;
        case 'album':
            if (isset($query) && !empty($query)){
                sendMpdCommand($sock, 'find "album" "'.html_entity_decode($query).'"');
            } else {
                sendMpdCommand($sock, 'list "album"');
            }
            break;
        case 'artist':
            if (isset($query) && !empty($query)){
                if ($query === 'Various Artists') {
                    sendMpdCommand($sock, 'list artist albumartist "Various Artists"');
                } else {
                    sendMpdCommand($sock, 'list "album" "'.html_entity_decode($query).'"');
                }
            } else {
                sendMpdCommand($sock, 'list "albumartist"');
            }
            break;
        case 'composer':
            if (isset($query) && !empty($query)){
                sendMpdCommand($sock, 'find "composer" "'.html_entity_decode($query).'"');
            } else {
                sendMpdCommand($sock, 'list "composer"');
            }
            break;
        case 'genre':
            if (isset($query) && !empty($query)){
                sendMpdCommand($sock, 'list "albumartist" "genre" "'.html_entity_decode($query).'"');
            } else {
                sendMpdCommand($sock, 'list "genre"');
            }
            break;
        case 'albumfilter':
            if (isset($query) && !empty($query)){
                sendMpdCommand($sock, 'find "albumartist" "'.html_entity_decode($query).'" "album" ""');
            }
            break;
        case 'globalrandom':
            sendMpdCommand($sock, 'listall');
            break;
    }
    $response = readMpdResponse($sock);
    return _parseFileListResponse($response);
}

function searchDB($sock, $querytype, $query) {
    sendMpdCommand($sock, "search ".$querytype." \"".html_entity_decode($query)."\"");
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

function addNextToQueue($sock, $path)
{
    $fileext = parseFileStr($path,'.');
    $status = _parseStatusResponse($redis, MpdStatus($sock));
    if (!isset($status['playlistlength']) || !isset($status['nextsong'])) {
        // failed to get a valid status so return with an error
        return false;
    }
    if (($fileext == 'm3u') || ($fileext == 'pls') || ($fileext == 'cue')) {
        // its a playlist or webradio
        $cmdlist = "command_list_begin\n";
        // load the first song in the playlist, it gets added to the end of the queue
        $cmdlist .= "load \"".html_entity_decode($path)."\" 0:1\n";
        // move the last entry in the queue to the next play position
        $cmdlist .= "move ".$status['playlistlength']." ".$status['nextsong']."\n";
        $cmdlist .= "command_list_end";
    } else {
        // its a song file
        $cmdlist = "command_list_begin\n";
        // add the song in the playlist, it gets added to the end of the queue
        $cmdlist .= "add \"".html_entity_decode($path)."\"\n";
        // move the last entry in the queue to the next play position
        $cmdlist .= "move ".$status['playlistlength']." ".$status['nextsong']."\n";
        $cmdlist .= "command_list_end";
    }
    sendMpdCommand($sock, $cmdlist);
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

function sysCmdAsync($syscmd, $waitsec = null)
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
        // no $waitsec value is present, so queue the command in fifo 'cmd_queue'
        global $redis;
        //
        // maybe a little paranoid, but to prevent anyone just dropping commands into the cmd_queue
        //  the commands are deflated, encrypted and then base64_encoded
        //  the reverse is used to decode commands
        //  its not really secure but should be enough to deter the casual burglar
        // variables
        // use the first cypher in the list of possibles
        $cipher = openssl_get_cipher_methods()[0];
        // the passphrase is a hash of the playerid
        $passphrase = $redis->get('playerid');
        // the initialization vector is calculated in the standard way, once per boot
        if (is_firstTime($redis, 'cipher_iv')) {
            // this is the standard way
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
            $redis->set('cipher_iv', $iv);
        } else {
            // it must be the same for encoding and decoding, save it
            //  this means that encoding changes after each reboot
            $iv = $redis->get('cipher_iv');
        }
        // encode
        // $encoded = base64_encode(openssl_encrypt(gzdeflate($command, 9), $cipher, $passphrase, 0, $iv));
        // decode
        // $command = trim(gzinflate(openssl_decrypt(base64_decode($encoded), $cipher, $passphrase, 0, $iv)));
        //
        // deflate, encrypt en base64 encode then put the command into the queue
        $encoded = base64_encode(openssl_encrypt(gzdeflate($syscmd, 9), $cipher, $passphrase, 0, $iv));
        $redis->lPush('cmd_queue', $encoded);
        // get the lock value
        $lock = $redis->get('lock_cmd_queue');
        // debug
        runelog('sysCmdAsync(lock status) ', $lock);
        runelog('sysCmdAsync($syscmd) decoded', $syscmd);
        runelog('sysCmdAsync($syscmd) encoded', $encoded);
        if (($lock === '0') || ($lock === '9')  || ($lock >= 9)) {
            // the job is not running
            // set the lock, it will be unlocked when the command queue job completes
            $redis->set('lock_cmd_queue', '1');
            // start the command queue job by posting it with an explicit wait of 0 seconds
            sysCmdAsync('nice --adjustment=1 /srv/http/command/cmd_async_queue', 0);
        } else {
            // locked
            // just in case something goes wrong increment the lock value by 1
            // when it reaches 9 (this should never happen) it will be processed as if there is no lock
            $lock += 1;
            $redis->set('lock_cmd_queue', $lock);
        }
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
            $element = '';
            $value = '';
            // $blacklist = ['@eaDir', '.Trash'];
            if (!strpos(' '.$plistLine,'@eaDir') && !strpos(' '.$plistLine,'.Trash')) {
                if (strpos(' '.$plistLine, ': ')) {
                    list ($element, $value) = explode(': ', $plistLine, 2);
                    $element = trim($element);
                    if (isset($value)) {
                        $value = trim($value);
                    } else {
                        $value = '';
                    }
                }
            }
            if (!$element) {
                // do nothing
            } elseif ($element === 'file' OR $element === 'playlist') {
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
                if ( $plCounter > -1 ) {
                    $plistArray[$plCounter][$element] = $value;
                    if ( $element === 'Time' ) {
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
        //
        // mpd returns the actual bitrate of the music file at that moment, when a variable bit rate file (e.g. FLAC) is being played
        //  the value is generally incorrect, replace it when possible with the average bitrate
        //  we need the current music file name in order to retrieve an average bitrate value
        //  when required also update sample rate and sample depth
        // ignore any line returned by mpd status containing 'updating', 3 lines mean a file is playing
        $status = sysCmd('mpc status | grep -vi updating');
        if ((count($status) == 3)) {
            // we can determine the file name
            $fileName = rtrim($redis->hGet('mpdconf', 'music_directory'), '/').'/'.trim(sysCmd('mpc -f "[%file%]"')[0]);
            $metadata = getMusicFileMatadata($redis, $fileName);
            // bit rate, always update it when a value is available
            if ($metadata && isset($metadata['avg_bit_rate']) && $metadata['avg_bit_rate']) {
                $plistArray['bitrate'] = intval($metadata['avg_bit_rate']/1000);
            }
            // sample rate, fix it if missing
            if (!isset($plistArray['audio_sample_rate']) || !$plistArray['audio_sample_rate']) {
                if ($metadata && isset($metadata['sample_rate']) && $metadata['sample_rate']) {
                    $plistArray['audio_sample_rate'] = round($metadata['sample_rate']/1000, 1);
                }
            }
            // sample depth, fix it if missing
            if (!isset($plistArray['audio_sample_depth']) || !$plistArray['audio_sample_depth']) {
                if ($metadata && isset($metadata['bits_per_sample']) && $metadata['bits_per_sample']) {
                    $plistArray['audio_sample_depth'] = $metadata['bits_per_sample'];
                }
            }
            unset($fileName, $metadata);
        }
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
        $resp = json_decode($resp, true);
        if ($resp['status'] === 'playing') {
            $status['state'] = 'play';
        } else if ($resp['status'] === 'stopped') {
            $status['state'] = 'stop';
        } else if ($resp['status'] === 'paused') {
            $status['state'] = 'pause';
        }
        if ($resp['repeat'] === false) {
            $status['repeat'] = '0';
        } else {
            $status['repeat'] = '1';
        }
        if ($resp['shuffle'] === false) {
            $status['random'] = '0';
        } else {
            $status['random'] = '1';
        }
        $status['playlistlength'] = $resp['total_tracks'];
        $status['currentartist'] = $resp['artist'];
        $status['currentalbum'] = $resp['album'];
        $status['currentsong'] = $resp['title'];
        $status['song'] = $resp['current_track'] -1;
        if (isset($res['position'])) {
            $status['elapsed'] = $resp['position'];
        } else {
            $status['elapsed'] = 0;
        }
        $status['time'] = $resp['duration'] / 1000;
        $status['volume'] = 100;
        if ($resp['status'] === 'stopped') {
            $status['song_percent'] = 0;
        } else {
            $status['song_percent'] = round(100 - (($status['time'] - $status['elapsed']) * 100 / $status['time']));
        }
        $status['uri'] = $resp['uri'];
        $status['popularity'] = $resp['popularity'];
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
            // start the local browser
            $redis->hSet('local_browser', 'enable', $args);
            // modify the files in /usr/share/X11/xorg.conf.d to contain valid rotate and frame buffer options
            sysCmd('/srv/http/command/add-screen-rotate.sh');
            sysCmd('systemctl start local-browser');
            wrk_xorgconfig($redis, 'enable-splash', 1);
            if (sysCmd("grep -ic '#disable_overscan=1' '/boot/config.txt'")[0]) {
                wrk_xorgconfig($redis, 'overscan', 1);
            } else {
                wrk_xorgconfig($redis, 'overscan', 0);
            }
            sysCmdAsync('nice --adjustment=4 /srv/http/command/rune_prio nice');
            break;
        case 'stop':
            // stop the local browser
            $redis->hSet('local_browser', 'enable', $args);
            // for attached lcd tft screens 'xset dpms force off' is requird to clear the screen
            sysCmd('export DISPLAY=:0; xset dpms force off; systemctl stop local-browser');
            wrk_xorgconfig($redis, 'enable-splash', 0);
            break;
        case 'restart':
            if ($redis->hGet('local_browser', 'enable')) {
                sysCmd('systemctl stop local-browser');
                sysCmd('systemctl daemon-reload');
                sysCmd('systemctl start local-browser');
                sysCmdAsync('nice --adjustment=4 /srv/http/command/rune_prio nice');
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
            // remove the next lines after the next image is produced
            // modify the zoom factor in /etc/X11/xinit/xinitrc
            $filePathName = '/etc/X11/xinit/xinitrc';
            // replace the line with '/usr/bin/chromium' without a zoom factor parameter
            sysCmd('sed -i "\|/usr/bin/chromium|c\sudo -u http /usr/bin/chromium http://localhost/" "'.$filePathName.'"');
            // remove up to here
            // modify the zoom factor for the chromium browser in /srv/http/.config/chromium-flags.conf
            // chromium scale factor is a decimal 1 = 100% ( we store it as a decimal)
            $filePathName = '/srv/http/.config/chromium-flags.conf';
            clearstatcache(true, $filePathName);
            if (!file_exists($filePathName)) {
                $filePath = pathinfo($filePathName)['dirname'];
                sysCmd('mkdir "'.$filePath.'"; chown http.http "'.$filePath.'"');
                sysCmd('cp "/srv/http/app/config/defaults'.$filePathName.'" "'.$filePathName.'"; chown http.http "'.$filePathName.'"; chmod 644 "'.$filePathName.'"');
            }
            if (sysCmd('grep -ic force-device-scale-factor "'.$filePathName.'"')[0]) {
                // scale factor line exists, modify it
                sysCmd('sed -i "/force-device-scale-factor/c\--force-device-scale-factor='.$args.'" "'.$filePathName.'"');
            } else {
                // scale factor line is missing, add it
                sysCmd('echo "--force-device-scale-factor='.$args.'" >> "'.$filePathName.'"');
            }
            // modify the zoom factor for the luakit browser in /srv/http/.config/luakit/userconf.lua
            // luakit scale factor is a percentage  ( we store it as a decimal)
            $filePathName = '/srv/http/.config/luakit/userconf.lua';
            $filePath = pathinfo($filePathName)['dirname'];
            clearstatcache(true, $filePathName);
            if (!file_exists($filePathName)) {
                $filePath = pathinfo($filePathName)['dirname'];
                sysCmd('mkdir "'.$filePath.'"; chown http.http "'.$filePath.'"');
                sysCmd('cp "/srv/http/app/config/defaults'.$filePathName.'" "'.$filePathName.'"; chown http.http "'.$filePathName.'"; chmod 644 "'.$filePathName.'"');
            }
            if (sysCmd('grep -ic settings.webview.zoom_level "'.$filePathName.'"')[0]) {
                // scale factor line exists, modify it
                sysCmd('sed -i "/^settings.webview.zoom_level/c\settings.webview.zoom_level = '.round($args*100).'" "'.$filePathName.'"');
            } else {
                // scale factor line is missing, add it
                sysCmd('echo "settings.webview.zoom_level = '.round($args*100).'" >> "'.$filePathName.'"');
            }
            wrk_xorgconfig($redis, 'restart', 1);
            break;
        case 'rotate':
            $redis->hSet('local_browser', $action, $args);
            sysCmd('/srv/http/command/raspi-rotate-screen.sh '.$args);
            wrk_xorgconfig($redis, 'restart', 1);
            break;
        case 'overscan':
            $redis->hSet('local_browser', $action, $args);
            if ($args){
                // switch overscan on
                sysCmd("sed -i '/disable_overscan/c\#disable_overscan=1' '/boot/config.txt'");
                $redis->hSet('local_browser', 'overscan', 1);
            } else {
                // switch overscan off
                // modify /boot/config.txt
                sysCmd("sed -i '/disable_overscan/c\disable_overscan=1' '/boot/config.txt'");
                $redis->hSet('local_browser', 'overscan', 0);
            }
            break;
        case 'mouse_cursor':
            $redis->hSet('local_browser', $action, $args);
            if ($args){
                // switch mouse cursor on
                $usecursorno = '';
                // modify the udev rules for vc4 screens
                sysCmd("sed -i '/LIBINPUT_IGNORE_DEVICE/s/\=\"0\"/\=\"1\"/g' '/etc/udev/rules.d/99-runeaudio.rules'");
            } else {
                // switch mouse cursor off
                $usecursorno = '-use_cursor no ';
                // modify the udev rules for vc4 screens
                sysCmd("sed -i '/LIBINPUT_IGNORE_DEVICE/s/\=\"1\"/\=\"0\"/g' '/etc/udev/rules.d/99-runeaudio.rules'");
            }
            // modify the mouse on/off setting in /etc/X11/xinit/xinitrc
            $filePathName = '/etc/X11/xinit/xinitrc';
            // replace the line with 'matchbox-window-manager' adding or removing the '-use cursor no' clause
            sysCmd('sed -i "\|matchbox-window-manager|c\matchbox-window-manager -use_titlebar no '.$usecursorno.'&" "'.$filePathName.'"');
            wrk_xorgconfig($redis, 'restart', 1);
            break;
    }
}

function wrk_avahiconfig($redis, $hostname)
{
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
            // add the jobID to the fifo queue
            $redis->lPush('w_queue_fifo', $jobID);
            runelog('wrk_control data:', $redis->hGet('w_queue', $jobID));
            break;
    }
    // debug
    runelog('[wrk] wrk_control($redis,'.$action.',$data) jobID='.$jobID, $data, 'wrk_control');
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
    // create a diff file /home/config.txt.diff of /srv/http/app/config/defaults/boot/config.txt vs. /boot/config.txt
    sysCmd('diff -Nau /srv/http/app/config/defaults/boot/config.txt /boot/config.txt >/home/config.txt.diff');
    // build up the backup command string
    if ($bktype === 'dev') {
        // $filepath = $fileDestDir.'backup-total-'.date("Y-m-d").'.tar.gz';
        // $cmdstring = "rm -f '".$fileDestDir."backup-total-*' &> /dev/null;".
            // " bsdtar -czpf '".$filepath."'".
            // " /mnt/MPD/Webradio".
            // " /var/lib/redis/rune.rdb".
            // " '".$redis->hGet('mpdconf', 'db_file')."'".
            // " '".$redis->hGet('mpdconf', 'sticker_file')."'".
            // " '".$redis->hGet('mpdconf', 'playlist_directory')."'".
            // " '".$redis->hGet('mpdconf', 'state_file')."'".
            // " /var/lib/connman".
            // " /var/www".
            // " /etc".
            // " /home/config.txt.diff".
            // "";
    } else {
        $filepath = $fileDestDir.'backup-'.date("Y-m-d").'.tar.gz';
        // To-Do: make the backup and restore string (see also restore.sh) match and be more explicit
        $cmdstring = "rm -f '".$fileDestDir."backup-*' &> /dev/null;".
            " bsdtar -czpf '".$filepath."'".
            " /mnt/MPD/Webradio".
            " /var/lib/redis/rune.rdb".
            " '".$redis->hGet('mpdconf', 'db_file')."'".
            " '".$redis->hGet('mpdconf', 'sticker_file')."'".
            " '".$redis->hGet('mpdconf', 'playlist_directory')."'".
            " '".$redis->hGet('mpdconf', 'state_file')."'".
            " /var/lib/connman/wifi_*.config".
            " /var/lib/connman/ethernet_*.config".
            " /etc/mpd.conf".
            " /etc/samba".
            " /home/config.txt.diff".
            "";
    }
    // // add the names of the distribution files
    // $extraFiles = sysCmd('find /srv/http/app/config/defaults/ -type f');
    // foreach ($extraFiles as $extraFile) {
        // // convert the names of the distribution files to the location of production version (the one being used)
        // $fileName = str_replace('/srv/http/app/config/defaults', '', $extraFile);
        // if (($bktype === 'dev') && ((substr($fileName, 0, 9) === '/srv/http/') || (substr($fileName, 0, 5) === '/etc/'))) {
            // // skip any files in /srv/http/ and /etc/ for a dev backup, they are already included
            // continue;
        // }
        // // clear the cache otherwise file_exists() returns incorrect values
        // clearstatcache(true, $fileName);
        // if (file_exists($fileName)) {
            // // add the files to the backup command if they exist
            // $cmdstring .= " '".$fileName."'";
        // }
    // }
    ui_notify('Backup', $cmdstring);
    // save the redis database
    $redis->save();
    // run the backup
    sysCmd($cmdstring);
    // delete the diff file for /boot/config.txt
    unlink('/home/config.txt.diff');
    // change the file privileges
    sysCmd('chown http.http '."'".$filepath."'".' ; chmod 644 '."'".$filepath."'");
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
            $fileName = '/etc/php/conf.d/opcache.ini';
            // clear the file cache otherwise file_exists() returns incorrect values
            clearstatcache(true, $fileName);
            if (!file_exists($fileName)) {
                sysCmd('echo -en "opcache.enable=1\npcache.enable_cli=1\n" > "'.$fileName.'"');
            } else {
                sysCmd("sed -i '/^pcache.enable=/c\pcache.enable=1' '".$fileName."'");
            }
            $redis->set('opcache', 1);
            break;
        case 'disable':
            $fileName = '/etc/php/conf.d/opcache.ini';
            // clear the file cache otherwise file_exists() returns incorrect values
            clearstatcache(true, $fileName);
            if (!file_exists($fileName)) {
                sysCmd('echo -en "opcache.enable=1\npcache.enable_cli=0\n" > "'.$fileName.'"');
            } else {
                sysCmd("sed -i '/^pcache.enable=/c\pcache.enable=0' '".$fileName."'");
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
            //
            $fileName = '/etc/php/conf.d/opcache.ini';
            // clear the file cache otherwise file_exists() returns incorrect values
            clearstatcache(true, $fileName);
            if (!file_exists($fileName)) {
                sysCmd('echo -en "opcache.enable=1\npcache.enable_cli=1\nopcache.memory_consumption='.$memory.'\n" > "'.$fileName.'"');
            } else {
                if (sysCmd('grep -ic "opcache.memory_consumption=" "'.$fileName.'"')[0]) {
                    sysCmd("sed -i '/^opcache.memory_consumption=/c\opcache.memory_consumption=".$memory."' '".$fileName."'");
                } else {
                    sysCmd('echo -en "opcache.memory_consumption='.$memory.'\n" >> "'.$fileName.'"');
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

function wrk_apconfig($redis, $action, $args = null)
{
    runelog('wrk_apconfig args = ', $args);
    $return = '';
    switch ($action) {
        case 'writecfg':
            if (isset($args['enable'])) {
                $redis->hSet('AccessPoint', 'enable', $args['enable']);
            } else {
                $redis->hSet('AccessPoint', 'enable', 0);
            }
            $redis->hSet('AccessPoint', 'ssid', $args['ssid']);
            $redis->hSet('AccessPoint', 'passphrase', $args['passphrase']);
            $redis->hSet('AccessPoint', 'ip-address', $args['ip-address']);
            $redis->hSet('AccessPoint', 'broadcast', $args['broadcast']);
            $redis->hSet('AccessPoint', 'dhcp-range', $args['dhcp-range']);
            $redis->hSet('AccessPoint', 'dhcp-option-dns', $args['dhcp-option-dns']);
            $redis->hSet('AccessPoint', 'dhcp-option-router', $args['dhcp-option-router']);
            if (isset($args['enable-NAT'])) {
                $redis->hSet('AccessPoint', 'enable-NAT', $args['enable-NAT']);
            } else {
                $redis->hSet('AccessPoint', 'enable-NAT', 0);
            }
            $args = array_merge($args, $redis->hgetall('AccessPoint'));
            break;
        case 'reset':
            $redis->Del('AccessPoint');
            sysCmd('/srv/http/db/redis_datastore_setup check');
            $args = array_merge($args, $redis->hgetall('AccessPoint'));
            break;
    }
    if ($args['enable']) {
        if ($args['reboot']) {
            runelog('**** AP reboot requested ****', $args);
            $return = 'reboot';
        } elseif ($args['restart']) {
            $procCount = sysCmd('pgrep -x "hostapd|dnsmasq" | wc -l')[0];
            // $procCount has value 2 when both hostapd and dnsmasq are running, cannot restart if these are not running
            if ($procCount === 2) {
                runelog('**** AP restart requested ****', $args);
                $file = '/etc/hostapd/hostapd.conf';
                // change AP name
                $newArray = wrk_replaceTextLine($file, '', 'ssid=', 'ssid='.$args['ssid']);
                // change passphrase
                $newArray = wrk_replaceTextLine('' , $newArray, 'wpa_passphrase=', 'wpa_passphrase='.$args['passphrase']);
                $fp = fopen($file, 'w');
                $return = fwrite($fp, implode('', $newArray));
                fclose($fp);
                $file = '/etc/dnsmasq.conf';
                // change dhcp-range
                $newArray = wrk_replaceTextLine($file, '', 'dhcp-range=', 'dhcp-range='.$args['dhcp-range']);
                // change dhcp-option dns-server
                $newArray = wrk_replaceTextLine('' , $newArray, 'dhcp-option-force=option:dns-server,', 'dhcp-option-force=option:dns-server,'.$args['dhcp-option-dns']);
                // change dhcp-option router
                $newArray = wrk_replaceTextLine('' , $newArray, 'dhcp-option-force=option:router,', 'dhcp-option-force=option:router,'.$args['dhcp-option-router']);
                $fp = fopen($file, 'w');
                $return = fwrite($fp, implode('', $newArray));
                fclose($fp);
                $dnsmasqLines = sysCmd('grep -i interface /etc/dnsmasq.conf');
                foreach ($dnsmasqLines as $dnsmasqLine) {
                    list($id, $value) = explode('=', $dnsmasqLine, 2);
                    if ($id === 'interface') {
                        $wlanNic = $value;
                    }
                    if ($id === 'no-dhcp-interface') {
                        $ethNic = $value;
                    }
                }
                if (isset($wlanNic)) {
                    // flush the wlan nic then take it down and bring it up, this will turn the AP off
                    sysCmd('ip addr flush '.$wlanNic.';ip link set dev '.$wlanNic.' down;ip link set dev '.$wlanNic.' up');
                    sysCmd('systemctl restart hostapd');
                    sysCmd('systemctl restart dnsmasq');
                    // enable the AP by switching braodcast on
                    sysCmd('ip addr add '.$args['ip-address'].'/24 broadcast '.$args['broadcast'].' dev '.$wlanNic);
                    if (isset($ethNic)) {
                        $ethNicConnected = sysCmd('ip address | grep -ic '.$ethNic)[0];
                    } else {
                        $ethNicConnected = false;
                    }
                    if (($args['enable-NAT'] === '1') && $ethNicConnected) {
                        // enable NAT if enabled and there is a wired nic available
                        sysCmd('iptables -t nat -A POSTROUTING -o '.$ethNic.' -j MASQUERADE');
                        sysCmd('iptables -A FORWARD -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT');
                        sysCmd('iptables -A FORWARD -i '.$wlanNic.' -o '.$ethNic.' -j ACCEPT');
                        sysCmd('sysctl net.ipv4.ip_forward=1');
                    } else {
                        sysCmd('sysctl net.ipv4.ip_forward=0');
                    }
                }
            }
        }
        // the following lines use qrencode to generate a QR-code for the AP connect and browser URL (ip address)
        //  it looks neat, but is pretty useless because you need to connect to be able to see the codes!
        //  currently disabled, the UI will only display QR-codes for the default settings
        // sysCmd('qrencode -l H -t PNG -o /srv/http/assets/img/RuneAudioAP.png "WIFI:S:'.$args['ssid'].';T:WPA2;P:'.$args['passphrase'].';;"');
        // sysCmd('qrencode -l H -t PNG -o /srv/http/assets/img/RuneAudioURL.png http://'.$args['ip-address']);
    } else {
        // now disabled
        $procCount = sysCmd('pgrep -x "hostapd|dnsmasq" | wc -l')[0];
        // $procCount has value 2 when both hostapd and dnsmasq are running and 1 when one is running
        if ($procCount) {
            // one of the processes is running, so turn it off
            sysCmd('pgrep -x hostapd && systemctl stop hostapd');
            sysCmd('pgrep -x dnsmasq && systemctl stop dnsmasq');
            // flush the wlan nic then take it down and bring it up, this will turn the AP off
            sysCmd('ip addr flush '.$wlanNic.';ip link set dev '.$wlanNic.' down;ip link set dev '.$wlanNic.' up');
        }
    }
    return $return;
}
// function wrk_apconfig($redis, $action, $args = null)
// {
    // $return = array();
    // runelog('wrk_apconfig args = ', $args);
    // switch ($action) {
        // case 'writecfg':
            // if (isset($args->{'enable'})) {
                // $redis->hSet('AccessPoint', 'enable', $args->{'enable'});
            // } else {
                // $redis->hSet('AccessPoint', 'enable', 0);
            // }
            // $redis->hSet('AccessPoint', 'ssid', $args->{'ssid'});
            // $redis->hSet('AccessPoint', 'passphrase', $args->{'passphrase'});
            // $redis->hSet('AccessPoint', 'ip-address', $args->{'ip-address'});
            // $redis->hSet('AccessPoint', 'broadcast', $args->{'broadcast'});
            // $redis->hSet('AccessPoint', 'dhcp-range', $args->{'dhcp-range'});
            // $redis->hSet('AccessPoint', 'dhcp-option-dns', $args->{'dhcp-option-dns'});
            // $redis->hSet('AccessPoint', 'dhcp-option-router', $args->{'dhcp-option-router'});
            // if ($args->{'enable-NAT'} === '1') {
                // $redis->hSet('AccessPoint', 'enable-NAT', $args->{'enable-NAT'});
            // } else {
                // $redis->hSet('AccessPoint', 'enable-NAT', 0);
            // }
            // if ($args->{'reboot'} === '1') {
                // runelog('**** AP reboot requested ****', $args);
                // $return = 'reboot';
            // } elseif ($args->{'restart'} === '1') {
                // runelog('**** AP restart requested ****', $args);
                // // change AP name
                // $file = '/etc/hostapd/hostapd.conf';
                // $newArray = wrk_replaceTextLine($file, '', 'ssid=', 'ssid='.$args->{'ssid'});
                // $fp = fopen($file, 'w');
                // $return = fwrite($fp, implode("", $newArray));
                // fclose($fp);
                // // change passphrase
                // $file = '/etc/hostapd/hostapd.conf';
                // $newArray = wrk_replaceTextLine($file, '', 'wpa_passphrase=', 'wpa_passphrase='.$args->{'passphrase'});
                // $fp = fopen($file, 'w');
                // $return = fwrite($fp, implode("", $newArray));
                // fclose($fp);
                // sysCmd('systemctl start hostapd');
                // // change dhcp-range
                // $file = '/etc/dnsmasq.conf';
                // $newArray = wrk_replaceTextLine($file, '', 'dhcp-range=', 'dhcp-range='.$args->{'dhcp-range'});
                // $fp = fopen($file, 'w');
                // $return = fwrite($fp, implode("", $newArray));
                // fclose($fp);
                // // change dhcp-option
                // $file = '/etc/dnsmasq.conf';
                // $newArray = wrk_replaceTextLine($file, '', 'dhcp-option-force=option:dns-server,', 'dhcp-option-force=option:dns-server,'.$args->{'dhcp-option-dns'});
                // $fp = fopen($file, 'w');
                // $return = fwrite($fp, implode("", $newArray));
                // fclose($fp);
                // $file = '/etc/dnsmasq.conf';
                // $newArray = wrk_replaceTextLine($file, '', 'dhcp-option-force=option:router,', 'dhcp-option-force=option:router,'.$args->{'dhcp-option-router'});
                // $fp = fopen($file, 'w');
                // $return = fwrite($fp, implode("", $newArray));
                // fclose($fp);
                // sysCmd('ip addr flush dev wlan0');
                // sysCmd('ip addr add '.$args->{'ip-address'}.'/24 broadcast '.$args->{'broadcast'}.' dev wlan0');
                // sysCmd('systemctl reload-or-restart hostapd');
                // sysCmd('systemctl reload-or-restart dnsmasq');
                // if ($args->{'enable-NAT'} === '1') {
                    // sysCmd('iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE');
                    // sysCmd('iptables -A FORWARD -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT');
                    // sysCmd('iptables -A FORWARD -i wlan0 -o eth0 -j ACCEPT');
                    // sysCmd('sysctl net.ipv4.ip_forward=1');
                // } else {
                    // sysCmd('sysctl net.ipv4.ip_forward=0');
                // }
                // $return = '';
            // }
            // sysCmd('qrencode -l H -t PNG -o /srv/http/assets/img/RuneAudioAP.png "WIFI:S:'.$args->ssid.';T:WPA2;P:'.$args->passphrase.';;"');
            // sysCmd('qrencode -l H -t PNG -o /srv/http/assets/img/RuneAudioURL.png http://'.$args->{'ip-address'});
            // break;
        // case 'reset':
            // break;
    // }
    // return $return;
// }

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
            sysCmdAsync('nice --adjustment=4 /srv/http/command/refresh_nics');
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
                sysCmd('rm -f \'/var/lib/iwd/'.$args['ssid'].'.*\'');
            }
            // ethernet
            if (isset($args['macAddress']) && isset($storedProfiles[$macAddressKey])) {
                unset($storedProfiles[$macAddressKey]);
                unlink('/var/lib/connman/ethernet_'.$args['macAddress'].'.config');
                sysCmd('rmdir --ignore-fail-on-non-empty \'/var/lib/connman/ethernet_'.$args['macAddress'].'*\'');
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
    sysCmd('chown http.http '.$file);
    sysCmd('chmod 644 '.$file);
}

function wrk_audioOutput($redis, $action, $args = null)
{
    switch ($action) {
        case 'refresh':
            // get a list of the hardware audio cards
            $cardlist = array();
            $cardlist = sysCmd('aplay -l -v | grep card');
            // determine if the number of cards has changed
            if ($redis->hlen('acards') != count($cardlist)) {
                $cardChange = 1;
            } else {
                $cardChange = 0;
            }
            $acards = array();
            // reformat the output of the card list
            foreach ($cardlist as $card) {
                $cardNr=get_between_data($card, 'card', ':');
                $acards[$cardNr]['number'] = $cardNr;
                $acards[$cardNr]['device'] = get_between_data($card, ', device', ':');
                $acards[$cardNr]['name'] = get_between_data($card, '[', ']');
                $acards[$cardNr]['sysdesc'] = get_between_data($card, '[', ']', 2);
                // check to see if the individual cards have changed
                if (!$cardChange) {
                    if (!$redis->hexists('acards', $acards[$cardNr]['name'])) {
                        $cardChange = 1;
                    } else {
                        $cardDet = array();
                        $cardDet = json_decode($redis->hget('acards', $acards[$cardNr]['name']), true);
                        if (get_between_data($cardDet['device'], ':', ',') != $cardNr) {
                            $cardChange = 1;
                        }
                    }
                }
            }
            if ($cardChange) {
                $redis->Del('acards');
            } else {
                // the cards are unchanged, just return
                return 'unchanged';
            }
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
                runelog('>>--------------------------- card number '.$card['number'].' name: '.$card['name'].' (start) --------------------------->>');
                //$card_index = explode(' : ', $card, 2);
                //$card_index = trim($card_index[0]);
                //$card_index = $card['number'];
                //$card = explode(' - ', $card, 2);
                //$card = trim($card[1]);
                // $description = sysCmd("grep -h ':' /proc/asound/cards | cut -d ':' -f 2 | cut -d ' ' -f 4-20");
                // debug
                //$card = $card['name'];
                runelog('wrk_audioOutput card string: ', $card['name']);
                //$description = sysCmd("aplay -l -v | grep \"\[".$card."\]\"");
                //$subdeviceid = explode(':', $description[0]);
                //$subdeviceid = explode(',', trim($subdeviceid[1]));
                //$subdeviceid = explode(' ', trim($subdeviceid[1]));
                //$data['device'] = 'hw:'.$card_index.','.$subdeviceid[1];
                $data['device'] = 'hw:'.$card['number'].','.$card['device'];
                //if ($i2smodule !== 'none' && isset($i2smodule_details->sysname) && $i2smodule_details->sysname === $card) {
                    //$acards_details = $i2smodule_details;
                //} else {
                    //$acards_details = $redis->hGet('acards_details', $card);
                //}
                // read the matching predefined configuration for this audio card
                $acards_details = $redis->hGet('acards_details', $card['name']);
                unset($details);
                $details = array();
                // use the predefined configuration for this card of generate one from the system information
                if ($acards_details == '') {
                    // no predefined configuration for this card use the available information
                    $details['sysname'] = $card['name'];
                    $details['extlabel'] = $card['sysdesc'];
                    $details['hwplatformid'] = '08';
                    if (substr($card['name'], 0, 8) == 'bcm2835 ') {
                        // these are the on-board standard audio outputs
                        $details['description'] = 'Raspberry Pi: '.trim(substr($card['name'], 8));
                        $details['type'] = 'integrated';
                    } else {
                        $details['description'] = $card['name'];
                        $details['type'] = 'unknown';
                    }
                } else {
                    // using the predefined configuration
                    $details = json_decode($acards_details, true);
                    // determine the description
                    if (isset($details['type'])) {
                        if ($details['type'] == 'i2s') {
                            // save the name as defined in the UI when selecting this card
                            $details['description'] = trim(explode('|', $redis->Get('i2smodule_select'), 2)[1]);
                            if (($details['description'] === '') || ($redis->Get('i2smodule') === 'none')) {
                                // otherwise call set the description to default, could happen when manually configured
                                $details['description'] = 'Soundcard: '.$card['sysdesc'];
                            }
                        } else if ($details['type'] == 'usb') {
                            // its a USB DAC
                            $details['description'] = 'USB: '.$card['sysdesc'];
                        } else {
                            // no idea what this card is, use its system description
                            $details['description'] = $card['sysdesc'];
                        }
                    } else {
                        // type is not set, use its system description
                        $details['description'] = $card['sysdesc'];
                    }
                }
                // when the mixer number ID or the mixer control name are not defined, sometimes these can be determined
                if (!isset($details['mixer_numid']) || !$details['mixer_numid']) {
                    // mixer number ID is missing
                    $retval = sysCmd('amixer controls -c '.$card['number'].' | grep -i "playback volume"');
                    if (isset($retval) && is_array($retval) && count($retval) == 1) {
                        // one value returned, so use it
                        $details['mixer_numid'] = get_between_data($retval[0], 'numid=', ',');
                    }
                    unset ($retval);
                } else {
                    // mixer number ID is specified, check that it is a valid number
                    $retval = sysCmd('amixer controls -c '.$card['number'].' | grep -ic "numid='.$details['mixer_numid'].'"');
                    if(isset($retval) && is_array($retval) && $retval[0]) {
                        // it is valid, do nothing
                    } else {
                        // not found, unset the value
                        unset($details['mixer_numid']);
                    }
                }
                if (!isset($details['mixer_control']) || !$details['mixer_control']) {
                    // mixer control is missing
                    $retval = sysCmd('amixer scontents -c '.$card['number'].' | grep -iE "simple|pvolume"');
                    $pvolumeCnt = 0;
                    foreach ($retval as $retline) {
                        if (substr(strtolower($retline), 0, 6) === 'simple' ) {
                            $mixerControl = get_between_data($retline, "'", "'");
                        }
                        if (strpos($retline, 'pvolume')) {
                            $validMixerControl = $mixerControl;
                            $pvolumeCnt++;
                        }
                    }
                    if ($pvolumeCnt == 1) {
                        // one value returned, so use it
                        $details['mixer_control'] = $validMixerControl;
                    }
                    unset ($retval, $retline, $mixerControl, $validMixerControl, $pvolumeCnt);
                } else {
                    // mixer control is specified, check that it is valid
                    $retval = sysCmd('amixer scontrols -c '.$card['number'].' | grep -ic "'.$details['mixer_control'].'"');
                    if(isset($retval) && is_array($retval) && $retval[0]) {
                        // it is valid, do nothing
                    } else {
                        // not found, unset the value
                        unset($details['mixer_control']);
                    }
                }
                if (isset($details['sysname']) && $details['sysname']) {
                    // a card has been determined, process it
                    // debug
                    runelog('wrk_audioOutput: in loop: acards_details for: '.$card['name'], json_encode($details));
                    //$details = new stdClass();
                    // debug
                    runelog('wrk_audioOutput: in loop: (decoded) acards_details for: '.$card['name'], $details['extlabel']);
                    if (isset($details['mixer_control']) && $details['mixer_control']) {
                        //$volsteps = sysCmd("amixer -c ".$card_index." get \"".$details['mixer_control']."\" | grep Limits | cut -d ':' -f 2 | cut -d ' ' -f 4,6");
                        //$volsteps = sysCmd("amixer -c ".$card_index." get \"".$details['mixer_control']."\" | grep Limits | cut -d ':' -f 2 | cut -d ' ' -f 3,5");
                        //$volsteps = explode(' ', $volsteps[0]);
                        $volsteps = sysCmd("amixer -c ".$card['number']." get \"".$details['mixer_control']."\" | grep -i limits:");
                        $volsteps = explode(' - ',preg_replace('/[^0-9- ]/', '', trim($volsteps[0])));
                        if (isset($volsteps[0])) $data['volmin'] = trim($volsteps[0]);
                        if (isset($volsteps[1])) $data['volmax'] = trim($volsteps[1]);
                        // $data['mixer_device'] = "hw:".$details['mixer_numid'];
                        $data['mixer_device'] = "hw:".$card['number'];
                        $data['mixer_control'] = $details['mixer_control'];
                    }
                    if (isset($details['sysname']) && ($details['sysname'] === $card['name'])) {
                        if ($details['type'] === 'integrated_sub') {
                            $sub_interfaces = $redis->sMembers($card['name']);
                            // debug
                            runelog('line 2444: (sub_interfaces loop) card: '.$card['name'], $sub_interfaces);
                            foreach ($sub_interfaces as $sub_interface) {
                                runelog('line 2446: (sub_interfaces foreach) card: '.$card['name'], $sub_interface);
                                //$sub_int_details = new stdClass();
                                $sub_int_details = array();
                                $sub_int_details = json_decode($sub_interface, true);
                                runelog('line 2449: (sub_interfaces foreach json_decode) card: '.$card['name'], $sub_int_details);
                                $sub_int_details['device'] = $data['device'];
                                $sub_int_details['name'] = $card['name'].'_'.$sub_int_details['id'];
                                $sub_int_details['description'] = $sub_int_details['extlabel'];
                                $sub_int_details['type'] = 'alsa';
                                $sub_int_details['integrated_sub'] = 1;
                                // prepare data for real_interface record
                                $data['name'] = $card['name'];
                                $data['type'] = 'alsa';
                                //$data['system'] = trim($card['sysdesc']);
                                // write real_interface json (use this to create the real MPD output)
                                $sub_int_details['real_interface'] = json_encode($data);
                                // replace index string in route command
                                if (isset($sub_int_details['route_cmd'])) $sub_int_details['route_cmd'] = str_replace("*CARDID*", $card['number'], $sub_int_details['route_cmd']);
                                // debug
                                runelog('::::::sub interface record array:::::: ',json_encode($sub_int_details));
                                $redis->hSet('acards', $card['name'].'_'.$sub_int_details['id'], json_encode($sub_int_details));
                            }
                        }
                        // if ($details['extlabel'] !== 'none') $data['extlabel'] = $details['extlabel'];
                        if (isset($details['extlabel']) && $details['extlabel'] !== 'none') {
                            runelog('::::::acard extlabel:::::: ', $details['extlabel']);
                            $data['extlabel'] = $details['extlabel'];
                        }
                    }
                    // debug
                    if (isset($data['extlabel'])) runelog('wrk_audioOutput: in loop: extlabel for: '.$card['name'], $data['extlabel']);
                    // test if there is an option for mpd.conf set
                    // for example ODROID C1 needs "card_option":"buffer_time\t\"0\""
                    if (isset($details['card_option']) && $details['card_option']) {
                        $data['card_option'] = $details['card_option'];
                    }
                    // test if there is a set of allowed formats for this card
                    // for example the ES9023 audio card expects 24 bit input
                    if (isset($allowedFormats[$activeOverlayAndName.$card['name']]) && $allowedFormats[$activeOverlayAndName.$card['name']]) {
                        $data['allowed_formats'] = $allowedFormats[$activeOverlayAndName.$card['name']];
                    }
                }
                if (!isset($sub_interfaces[0]) || (!$sub_interfaces[0])) {
                    $data['name'] = $card['name'];
                    $data['type'] = 'alsa';
                    $data['description'] = $details['description'];
                    //$data['system'] = trim($card['sysdesc']);
                    // debug
                    // runelog('::::::acard record array::::::', $data);
                    $redis->hSet('acards', $card['name'], json_encode($data));
                }
                // acards loop
                runelog('<<--------------------------- card: '.$card['name'].' index: '.$card['number'].' (finish) ---------------------------<<');
            }
            // $redis->save();
            $redis->bgSave();
            break;
        case 'setdetails':
            $redis->hSet('acards_details', $args['card'], json_encode($args['details']));
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
            // dtoverlay=none disables all following dtoverlay commands in config.txt, so comment out the line
            $newLine = '#dtoverlay='.$args;
        } else {
            $newLine = 'dtoverlay='.$args;
        }
        $file = '/boot/config.txt';
        $newArray = wrk_replaceTextLine($file, '', 'dtoverlay=', $newLine, 'RuneAudio I2S-Settings', 1);
        // Commit changes to /boot/config.txt
        $fp = fopen($file, 'w');
        $return = fwrite($fp, implode("", $newArray));
        fclose($fp);
    } else {
        if (wrk_mpdPlaybackStatus($redis) === 'playing') {
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
    if($redis->get('hwplatformid') === '08') {
        if ($args == 1) {
            sysCmd("sed -i '/dtparam=audio=/c\dtparam=audio=on' /boot/config.txt");
        } else {
            sysCmd("sed -i '/dtparam=audio=/c\dtparam=audio=off' /boot/config.txt");
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
            // ui_notify('MPD', 'config file part one finished');
            $acards = $redis->hGetAll('acards');
            // debug
            // --- audio output ---
            $lastAo = $redis->Get('ao');
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
                // set this card to the default alsa card
                set_alsa_default_card($ao);
            }
            // debug
            runelog('detected ACARDS ', count($acards), __FUNCTION__);
            $sub_count = 0;
            // sort the cards so that when acards has a different sequence but the same contents
            //  the MPD config file will not be replaced and MPD not restarted
            ksort($acards);
            foreach ($acards as $main_acard_name => $main_acard_details) {
                // $card_decoded = new stdClass();
                unset($card_decoded);
                $card_decoded = array();
                $card_decoded = json_decode($main_acard_details, true);
                // debug
                runelog('decoded ACARD '.$card_decoded['name'], $main_acard_details, __FUNCTION__);
                // handle sub-interfaces
                if (isset($card_decoded['integrated_sub']) && ($card_decoded['integrated_sub'] === 1)) {
                    // record UI audio output name
                    $current_card = $card_decoded['name'];
                    // if ($sub_count >= 1) continue;
                    // $card_decoded = json_decode($card_decoded->real_interface);
                    runelog('current AO ---->  ', $ao, __FUNCTION__);
                    // var_dump($ao);
                    runelog('current card_name ---->  ', $card_decoded['name'], __FUNCTION__);
                    // var_dump($card_decoded->name);
                    // var_dump(strpos($ao, $card_decoded->name));
                    if (strpos($ao, $card_decoded['name']) === true OR strpos($ao, $card_decoded['name']) === 0) $sub_interface_selected = 1;
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
                    $output .="\tname \t\t\"".$card_decoded['name']."\"\n";
                } else {
                    $output .="\tname \t\t\"".$main_acard_name."\"\n";
                }
                $output .="\ttype \t\t\"".$card_decoded['type']."\"\n";
                $output .="\tdevice \t\t\"".$card_decoded['device']."\"\n";
                if ($hwmixer) {
                     if (isset($card_decoded['mixer_control'])) {
                        $output .="\tmixer_control \t\"".$card_decoded['mixer_control']."\"\n";
                        $output .="\tmixer_type \t\"hardware\"\n";
                        $output .="\tmixer_device \t\"".substr($card_decoded['device'], 0, 4)."\"\n";
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
                $output .="\tauto_resample \t\"no\"\n";
                $output .="\tauto_format \t\"no\"\n";
                if ($ao === $main_acard_name) {
                    $output .="\tenabled \t\"yes\"\n";
                }
                $output .="}\n";
                unset($sub_interface);
                // debug
                // runelog('conf output (in loop)', $output, __FUNCTION__);
            }
            // ui_notify('MPD', 'config file part two finished');
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
                    $output .="\tencoder \t\t\"lame\"\n";
                    $output .="\tquality \t\t\"0\"\n";
                }
                $output .="\talways_on \t\t\"yes\"\n";
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
            // ui_notify('MPD', 'config file part three finished');
            // write mpd.conf file to /tmp location
            $fh = fopen('/tmp/mpd.conf', 'w');
            fwrite($fh, $output);
            fclose($fh);
            // check whether the /tmp/mpd.conf is not the same as /etc/mpd.conf and has not the same md5 as stored
            if (($redis->get('mpdconfhash') !== md5_file('/tmp/mpd.conf')) || ($redis->get('mpdconfhash') !== md5_file('/etc/mpd.conf'))) {
                // mpd configuration has changed, set mpdconfchange on, to indicate that MPD needs to be restarted and shairport conf needs updating
                $redis->set('mpdconfchange', 1);
                syscmd('cp /tmp/mpd.conf /etc/mpd.conf');
                syscmd('rm -f /tmp/mpd.conf');
                // update hash
                $redis->set('mpdconfhash', md5_file('/etc/mpd.conf'));
            } else {
                // nothing has changed, but don't unset mpdconfchange, a reboot may be needed for other reasons
                syscmd('rm -f /tmp/mpd.conf');
            }
            break;
        case 'update':
            foreach ($args as $param => $value) {
                $redis->hSet('mpdconf', $param, $value);
            }
            // ui_notify('MPD', 'redis database updated');
            wrk_mpdconf($redis, 'writecfg');
            break;
        case 'switchao':
            // record current interface selection
            $oldMpdout = $redis->get('ao');
            if ($oldMpdout != $args) {
                $redis->set('ao', $args);
                // set this card to the default alsa card
                set_alsa_default_card($args);
            }
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
                syscmd('mpc pause');
                $recover_state = 1;
                // debug
                runelog('switchao (set recover state):', $recover_state);
            }
            // switch interface
            // debug
            runelog('switchao (switch AO):', $mpdout);
            syscmd('mpc enable "'.$mpdout.'"');
            syscmd('mpc disable "'.$oldMpdout.'"');
            // restore playback state
            if (isset($recover_state)) {
                // debug
                runelog('switchao (RECOVER STATE!)');
                syscmd('mpc play');
            }
            // check that MPD only has one output enabled and if not correct it
            sysCmdAsync('nice --adjustment=4 /srv/http/command/check_MPD_outputs_async.php');
            // set notify label
            wrk_shairport($redis, $args);
            wrk_spotifyd($redis, $args);
            if (isset($interface_details->description)) {
                $interface_label = $interface_details->description;
            } else if (isset($interface_details->extlabel)) {
                $interface_label = $interface_details->extlabel;
            } else {
                $interface_label = $args;
            }
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
                    ui_notify('MPD', 'restarting MPD, it takes a while');
                    // reload systemd daemon to activate any changed unit files
                    sysCmd('systemctl daemon-reload');
                    // start mpd
                    sysCmd('systemctl start mpd.socket');
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
            sysCmdAsync('nice --adjustment=4 /srv/http/command/rune_prio nice');
            unset($activePlayer, $retval);
            break;
        case 'forcestop':
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
                    // ui_notify('MPD', 'restarting MPD, it takes a while');
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
            // sysCmdAsync('nice --adjustment=4 /srv/http/command/rune_prio nice');
            // unset($activePlayer, $retval);
            break;
        case 'forcerestart':
            wrk_mpdconf($redis, 'forcestop');
            wrk_mpdconf($redis, 'start');
            // code below is experimental
            // $activePlayer = $redis->get('activePlayer');
            // if ($activePlayer === 'MPD') {
                // $retval = sysCmd('systemctl is-active mpd');
                // ui_notify('MPD', 'restarting MPD, it takes a while');
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
            // sysCmdAsync('nice --adjustment=4 /srv/http/command/rune_prio nice');
            // unset($activePlayer, $retval);
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
        $loops = 24;
        $sleepSeconds = 2;
        for ($mpd_play_count = 0; $mpd_play_count < $loops; $mpd_play_count++) {
            // wait before looping
            sleep($sleepSeconds);
            switch (wrk_mpdPlaybackStatus($redis)) {
                case 'paused':
                    // it was playing, now paused, so set to play
                    sysCmd('mpc play || mpc play');
                    break;
                case 'playing':
                    // it was playing, now playing, so do nothing and exit the loop
                    $mpd_play_count = $loops;
                    break;
                default:
                    // it was playing, now not paused or playing, so start the track which was last playing
                    sysCmd('mpc play '.$mpd_playback_lastnumber.' || mpc play '.$mpd_playback_lastnumber);
                    if ($mpd_play_count == ($loops - 2)) {
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
                set_alsa_default_card($ao);
            }
        }
    } else {
        $ao = trim($ao);
    }
    $redis->hSet('spotifyconnect', 'ao', $ao);
    //
    $acard = json_decode($redis->hGet('acards', $ao), true);
    runelog('wrk_spotifyd acard name         : ', $acard['name']);
    runelog('wrk_spotifyd acard type         : ', $acard['type']);
    runelog('wrk_spotifyd acard device       : ', $acard['device']);
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
    $spotifyd_conf .= "# Auto generated spotifyd.conf file\n";
    $spotifyd_conf .= "# Configuration File for Spotifyd\n";
    $spotifyd_conf .= "# A spotify playing daemon - Spotify Connect Receiver\n";
    $spotifyd_conf .= '# See: https://github.com/Spotifyd/spotifyd#configuration'."\n";
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
            if ($value) {
                $spotifyd_conf .= $param." = ".'"'.$value.'"'."\n";
            }
            break;
        case "use_mpris":
            // no break;
        case "bitrate":
            if ($value) {
                $spotifyd_conf .= $param." = ".$value."\n";
            }
            break;
        case "volume_control":
            $spotifyd_conf .= "volume-control = ".'"'.$value.'"'."\n";
            break;
        case "volume_normalisation":
            if ($value == 'true') {
                $spotifyd_conf .= "volume-normalisation = ".$value."\n";
            }
            break;
        case "normalisation_pregain":
            if ($sccfg['volume_normalisation'] == 'true') {
                $spotifyd_conf .= "normalisation-pregain = ".$value."\n";
            } else {
                $spotifyd_conf .= "normalisation-pregain = 0\n";
            }
            break;
        case "cache_path":
            if ($value != '') {
                $spotifyd_conf .= "# Disable the cache, it uses too much memory\n";
                $spotifyd_conf .= "# ".$param." = ".'"'.$value.'"'."\n";
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
        // stop spotifyd & rune_SDM_wrk
        sysCmd('pgrep -x spotifyd && systemctl stop spotifyd');
        sysCmd('pgrep -x rune_SDM_wrk && systemctl stop rune_SDM_wrk');
        $redis->hSet('spotifyconnect', 'last_track_id', '');
        // update systemd
        sysCmd('systemctl daemon-reload');
        if ($redis->hGet('spotifyconnect', 'enable')) {
            runelog('restart spotifyd');
            sysCmd('systemctl reload-or-restart spotifyd || systemctl start spotifyd');
            $redis->hSet('spotifyconnect', 'last_track_id', '');
            sysCmd('mpc volume '.$redis->get('lastmpdvolume'));
        }
    }
}

function wrk_shairport($redis, $ao, $name = null)
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
        $ao = trim($redis->get('ao'));
        if ($ao == '') {
            $ao = end($redis->hKeys('acards'));
            if ($ao != '') {
                $redis->set('ao', $ao);
                // set this card to the default alsa card
                set_alsa_default_card($ao);
            }
        }
    } else {
        $ao = trim($ao);
    }
    $redis->hSet('airplay', 'ao', $ao);
    //
    $acard = $redis->hGet('acards', $ao);
    $acard = json_decode($acard, true);
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
        syscmd('rm -f '.$newfile);
    } else {
        // mpd configuration has changed, set sssconfchange on
        $redis->set('sssconfchange', 1);
        syscmd('cp '.$newfile.' '.$file);
        syscmd('rm -f '.$newfile);
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
        if ($airplay['enable']) {
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
            sysCmdAsync('nice --adjustment=4 /srv/http/command/rune_prio nice');
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
            sysCmdAsync('nice --adjustment=4 /srv/http/command/rune_prio nice');
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
                sysCmd('udevil clean');
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
    if ($redis->exists('pi_model')) {
        $previousModel = $redis->get('pi_model');
    } else {
        $previousModel = '';
    }
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
        case 'BCM2711':
        case 'BCM2835':
        case 'BCM2836':
        case 'BCM2837':
            if (intval("0x".$revision, 16) < 16) {
                // RaspberryPi1
                $arch = '08';
                $model = "00";
                // old single processor models no on-board Wi-Fi or Bluetooth
                $redis->exists('soxrmpdonoff') || $redis->set('soxrmpdonoff', 0);
                $redis->exists('bluetooth_on') || $redis->set('bluetooth_on', 0);
                $redis->hExists('airplay', 'soxronoff') || $redis->hSet('airplay', 'soxronoff', 0);
                $redis->hExists('airplay', 'metadataonoff') || $redis->hSet('airplay', 'metadataonoff', 0);
                $redis->hExists('airplay', 'artworkonoff') || $redis->hSet('airplay', 'artworkonoff', 0);
                $redis->hExists('airplay', 'enable') || $redis->hSet('airplay', 'enable', 0);
                $redis->hExists('airplay', 'metadata_enabled') || $redis->hSet('airplay', 'metadata_enabled', 'no');
                $redis->hExists('spotifyconnect', 'metadata_enabled') || $redis->hSet('spotifyconnect', 'metadata_enabled', 0);
                $redis->hExists('AccessPoint', 'enable') || $redis->hSet('AccessPoint', 'enable', 1);
                // // Temporary fix for 'dtparam=audio=on' failures
                // if ($previousModel != $model) {
                    // $redis->set('audio_on_off', -1);
                // }
                // // End temporary fix
            }
            else {
                $model = strtolower(trim(substr($revision, -3, 2)));
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
                        $redis->hExists('AccessPoint', 'enable') || $redis->hSet('AccessPoint', 'enable', 1);
                        // // Temporary fix for 'dtparam=audio=on' failures
                        // if ($previousModel != $model) {
                            // $redis->set('audio_on_off', -1);
                        // }
                        // // End temporary fix
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
                    case "06":
                        // 06 = PiCompute Module 1
                        // no break;
                    case "09":
                        // 09 = PiZero,
                        // single processor (armv6) models no on-board Wi-Fi or Bluetooth
                        // // Temporary fix for 'dtparam=audio=on' failures
                        // if ($previousModel != $model) {
                            // $redis->set('audio_on_off', -1);
                        // }
                        // // End temporary fix
                        $arch = '08';
                        $redis->exists('soxrmpdonoff') || $redis->set('soxrmpdonoff', 1);
                        $redis->exists('bluetooth_on') || $redis->set('bluetooth_on', 0);
                        $redis->hExists('airplay', 'soxronoff') || $redis->hSet('airplay', 'soxronoff', 1);
                        $redis->hExists('airplay', 'metadataonoff') || $redis->hSet('airplay', 'metadataonoff', 1);
                        $redis->hExists('airplay', 'artworkonoff') || $redis->hSet('airplay', 'artworkonoff', 1);
                        $redis->hExists('airplay', 'enable') || $redis->hSet('airplay', 'enable', 1);
                        $redis->hExists('airplay', 'metadata_enabled') || $redis->hSet('airplay', 'metadata_enabled', 'yes');
                        $redis->hExists('spotifyconnect', 'metadata_enabled') || $redis->hSet('spotifyconnect', 'metadata_enabled', 1);
                        $redis->hExists('AccessPoint', 'enable') || $redis->hSet('AccessPoint', 'enable', 1);
                        break;
                    case "04":
                        // 04 = Pi2B,
                        // no break;
                    case "06":
                        // 06 = PiCompute Module
                        // no break;
                    case "0a":
                        // 0a = PiCompute Module 3
                        // no break;
                    case "0A":
                        // 0A = PiCompute Module 3
                        // no break;
                    case "10":
                        // 10 = PiCompute Module 3+
                        // no break;
                    case "14":
                        // 14 = PiCompute Module 4
                        // multi processor (armv7 or 64bit) models no on-board Wi-Fi or Bluetooth
                        $arch = '08';
                        $redis->exists('soxrmpdonoff') || $redis->set('soxrmpdonoff', 1);
                        $redis->exists('bluetooth_on') || $redis->set('bluetooth_on', 0);
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
                        $arch = '08';
                        // single processor (armv6) models with on-board Wi-Fi and/or Bluetooth
                        // // Temporary fix for 'dtparam=audio=on' failures
                        // if ($previousModel != $model) {
                            // $redis->set('audio_on_off', -1);
                        // }
                        // // End temporary fix
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
                    case "08":
                        // 08 = Pi3B,
                        // no break;
                    case "0d":
                        // 0d = Pi3B+
                        // no break;
                    case "0e":
                        // 0d = Pi3A+
                        // no break;
                    case "11":
                        // 0d = Pi4B+
                        // no break;
                    case "12":
                        // 12 = PiZero 2 W
                        // no break;
                    case "13":
                        // 13 = Pi400
                        // no break;
                        $arch = '08';
                        // multi processor (atrmv7 or 64bit) models with on-board Wi-Fi and/or Bluetooth
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
                    case "0f":
                        // 0f = internal use only,
                        // no break;
                    default:
                        $arch = '08';
                        $redis->exists('soxrmpdonoff') || $redis->set('soxrmpdonoff', 0);
                        $redis->exists('bluetooth_on') || $redis->set('bluetooth_on', 0);
                        $redis->hExists('airplay', 'soxronoff') || $redis->hSet('airplay', 'soxronoff', 0);
                        $redis->hExists('airplay', 'metadataonoff') || $redis->hSet('airplay', 'metadataonoff', 0);
                        $redis->hExists('airplay', 'artworkonoff') || $redis->hSet('airplay', 'artworkonoff', 0);
                        $redis->hExists('airplay', 'enable') || $redis->hSet('airplay', 'enable', 0);
                        $redis->hExists('airplay', 'metadata_enabled') || $redis->hSet('airplay', 'metadata_enabled', 'no');
                        $redis->hExists('spotifyconnect', 'metadata_enabled') || $redis->hSet('spotifyconnect', 'metadata_enabled', 0);
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
    $redis->set('pi_model', $model);
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

function wrk_startPlayer($redis, $newplayer)
{
    $activePlayer = $redis->get('activePlayer');
    if ($activePlayer === '') {
        // it should always be set, but default to MPD when nothing specified
        $activePlayer = 'MPD';
    }
    if ($activePlayer === 'MPD') {
        $redis->set('stoppedPlayer', $activePlayer);
        // record  the mpd status
        wrk_mpdPlaybackStatus($redis);
        // connect to MPD daemon
        // $sock = openMpdSocket('/run/mpd.sock', 0);
        $sock = openMpdSocket($redis->hGet('mpdconf', 'bind_to_address'), 0);
        if ($sock) {
            $status = _parseStatusResponse($redis, MpdStatus($sock));
            runelog('MPD status', $status);
            if ($status['state'] === 'play') {
                // pause playback
                if (sendMpdCommand($sock, 'pause')) {
                    readMpdResponse($sock);
                }
                // debug
                runelog('sendMpdCommand', 'pause');
            }
            // set the new player
            $redis->set('activePlayer', $newplayer);
            // to get MPD out of its idle-loop we discribe to a channel
            sendMpdCommand($sock, 'subscribe '.$newplayer);
            sendMpdCommand($sock, 'unsubscribe '.$newplayer);
            closeMpdSocket($sock);
        }
        if ($newplayer == 'Spotify') {
            $retval = sysCmd('systemctl is-active spopd');
            if ($retval[0] === 'active') {
                // do nothing
            } else {
                sysCmd('systemctl start spopd');
                usleep(500000);
            }
            wrk_mpdconf($redis, 'forcestop');
            $redis->set('mpd_playback_status', 'stop');
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
            wrk_mpdconf($redis, 'start');
            // ashuffle gets started automatically
            // stop spotify
            sysCmd('systemctl stop spopd');
            // set process priority
            sysCmdAsync('rune_prio nice');
        }
    } elseif ($activePlayer === 'Airplay') {
        // cant switch back to Airplay so don't set stoppedPlayer
        // stop the Airplay metadata worker
        wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'airplaymetadata', 'action' => 'stop'));
        // set the new player
        $redis->set('activePlayer', $newplayer);
        // if ($newplayer === 'SpotifyConnect') {
            // this will disconnect an exiting Airplay stream
            // do it only when connecting to another stream
            sysCmd('systemctl restart shairport-sync');
        // }
    } elseif ($activePlayer === 'SpotifyConnect') {
        // cant switch back to SpotifyConnect so don't set stoppedPlayer
        // stop SpotifyConnect worker for SpotifyConnect
        wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'spotifyconnectmetadata', 'action' => 'stop'));
        // set the new player
        $redis->set('activePlayer', $newplayer);
        // if ($newplayer === 'Airplay') {
            // this will disconnect an exiting SpotifyConnect stream
            // do it only when connecting to another stream
            sysCmd('systemctl restart spotifyd');
        // }
        $redis->hSet('spotifyconnect', 'last_track_id', '');
        sysCmd('mpc volume '.$redis->get('lastmpdvolume'));
        if (($newplayer === 'MPD') && ($redis->get('mpd_playback_laststate') == 'paused')) {
            // to-do: work out a better way to do this
            // we need to pause MPD very early to allow spotify connect to start correctly
            //  this means that we need to assume that if the stopped player is MDP and it's saved
            //  state is paused then its real previous state was playing
            $redis->set('mpd_playback_laststate', 'playing');
        }
        // sysCmd('rm /srv/http/tmp/spotify-connect/spotify-connect-cover.*');
        ui_render('playback', "{\"currentartist\":\"Spotify Connect\",\"currentsong\":\"Switching\",\"currentalbum\":\"-----\",\"artwork\":\"\",\"genre\":\"\",\"comment\":\"\"}");
        sysCmd('curl -s -X GET http://localhost/command/?cmd=renderui');
    }
    if ($newplayer == 'MPD') {
        wrk_mpdRestorePlayerStatus($redis);
    } elseif ($newplayer == 'Airplay') {
        wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'airplaymetadata', 'action' => 'start'));
    } elseif ($newplayer == 'SpotifyConnect') {
        wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'spotifyconnectmetadata', 'action' => 'start'));
    }
    sysCmd('curl -s -X GET http://localhost/command/?cmd=renderui');
    // set process priority
    sysCmdAsync('nice --adjustment=4 /srv/http/command/rune_prio nice');
}

function wrk_stopPlayer($redis, $activePlayer=null)
{
    runelog('wrk_stopPlayer active player', $activePlayer);
    if (is_null($activePlayer)) {
        $activePlayer = $redis->get('activePlayer');
    }
    runelog('wrk_stopPlayer active player', $activePlayer);
    if (($activePlayer == 'Airplay') || ($activePlayer == 'SpotifyConnect')) {
        // we previously stopped playback of one player to use the Stream
        $stoppedPlayer = $redis->get('stoppedPlayer');
        runelog('wrk_stopPlayer stoppedPlayer = ', $stoppedPlayer);
        // if ($activePlayer == 'Airplay') {
            // sysCmd('systemctl restart shairport-sync');
        // }
        if ($stoppedPlayer === '') {
            // if no stopped player is specified use MPD as default
            $stoppedPlayer = 'MPD';
        }
        if ($activePlayer == 'SpotifyConnect') {
            runelog('wrk_stopPlayer restart spotifyd');
            sysCmd('systemctl restart spotifyd');
            $redis->hSet('spotifyconnect', 'last_track_id', '');
            sysCmd('mpc volume '.$redis->get('lastmpdvolume'));
            if (($stoppedPlayer === 'MPD') && ($redis->get('mpd_playback_laststate') == 'paused')) {
                // to-do: work out a better way to do this
                // we need to pause MPD very early to allow spotify connect to start correctly
                //  this means that we need to assume that if the stopped player is MDP and it's saved
                //  state is paused then its real previous state was playing
                $redis->set('mpd_playback_laststate', 'playing');
            }
        }
        runelog('wrk_stopPlayer stoppedPlayer = ', $stoppedPlayer);
        if ($stoppedPlayer === 'MPD') {
            wrk_mpdconf($redis, 'start');
            // ashuffle gets started automatically
            // stop spotify
            sysCmd('systemctl stop spopd');
            // set process priority
            sysCmdAsync('rune_prio nice');
            // set the active player back to the one we stopped
            $redis->set('activePlayer', $stoppedPlayer);
            // connect to MPD daemon
            //$sock = openMpdSocket('/run/mpd.sock', 0);
            $sock = openMpdSocket($redis->hGet('mpdconf', 'bind_to_address'), 0);
            if ($sock) {
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
            }
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
            wrk_mpdconf($redis, 'forcestop');
            $redis->set('mpd_playback_status', 'stop');
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
        }
    }
    runelog('endFunction!!!', $stoppedPlayer);
    sysCmd('curl -s -X GET http://localhost/command/?cmd=renderui');
    // set process priority
    sysCmdAsync('nice --adjustment=4 /srv/http/command/rune_prio nice');
}

// function wrk_SpotifyConnectMetadata($redis, $event, $track_id)
// {
    // runelog('wrk_SpotifyConnectMetadata event   :', $event);
    // runelog('wrk_SpotifyConnectMetadata track ID:', $track_id);
    // switch($event) {
        // case 'start':
            // // no break;
        // case 'change':
            // // no break;
        // case 'stop':
            // // run asynchronous metadata script
            // sysCmdAsync('nice --adjustment=3 /srv/http/command/spotify_connect_metadata_async.php '.$event.' '.$track_id);
            // break;
        // default:
            // runelog('wrk_SpotifyConnectMetadata error:', 'Unknown event');
            // break;
    // }
// }

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
    wrk_avahiconfig($redis, strtolower($newhostname));
    // activate when a change has been made
    if ($redis->get('avahiconfchange')) {
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
    $redis->set('avahiconfchange', 0);
    // set process priority
    sysCmdAsync('nice --adjustment=4 /srv/http/command/rune_prio nice');
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
    $logFile = $redis->hGet('dlna', 'logfile');
    $logLevel = $redis->hGet('dlna', 'loglevel');
    $file = '/etc/systemd/system/upmpdcli.service';
    sysCmd('sed -i '."'".'/^ExecStart/ s|.*|ExecStart=/usr/bin/upmpdcli -c /etc/upmpdcli.conf -q '.$queueowner.' -d "'.$logFile.'" -l '.$logLevel.' -f "'.$name.'"|'."'".' /etc/systemd/system/upmpdcli.service');
    // the modifications above should work, but the parameter file seems to override the parameters on the ExecStart unit file line line
    // modify them all
    sysCmd('sed -i '."'".'/^friendlyname/ s|.*|friendlyname = '.$name.'|'."'".' /etc/upmpdcli.conf');
    sysCmd('sed -i '."'".'/^ohproductroom/ s|.*|ohproductroom = '.$name.'|'."'".' /etc/upmpdcli.conf');
    sysCmd('sed -i '."'".'/^ownqueue/ s|.*|ownqueue = '.$queueowner.'|'."'".' /etc/upmpdcli.conf');
    sysCmd('sed -i '."'".'/^logfilename/ s|.*|logfilename = '.$logFile.'|'."'".' /etc/upmpdcli.conf');
    sysCmd('sed -i '."'".'/^loglevel/ s|.*/|loglevel = '.$logLevel.'|'."'".' /etc/upmpdcli.conf');
    if ($redis->hGet('dlna','enable') === '1') {
        // update systemd
        sysCmd('systemctl daemon-reload');
        runelog('restart upmpdcli');
        sysCmd('systemctl reload-or-restart upmpdcli');
    }
    // set process priority
    sysCmdAsync('nice --adjustment=4 /srv/http/command/rune_prio nice');
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
        $newpls .= "Title1=".$data->label."\n";
        // Commit changes to .pls file
        $fp = fopen($file, 'w');
        $return = fwrite($fp, $newpls);
        fclose($fp);
        if ($return) {
            if (sendMpdCommand($mpd, 'update Webradio')) {
                readMpdResponse($mpd);
            }
        }
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
        if ($return) {
            if (sendMpdCommand($mpd, 'update Webradio')) {
                readMpdResponse($mpd);
            }
        }
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
            if (sendMpdCommand($mpd, 'update Webradio')) {
                readMpdResponse($mpd);
            }
        }
    } else {
        $return = false;
    }
    return $return;
}

function ui_notify($title, $text, $type = null, $permanotice = null)
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

function ui_notifyError($title, $text, $type = null, $permanotice = null)
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

function ui_notify_async($title, $text, $type = null, $permanotice = null)
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
        sysCmdAsync('/srv/http/command/ui_notify.php \''.$output.'\'');
    } else {
        sysCmdAsync('/srv/http/command/ui_notify.php "'.$output.'"');
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
    if (isset($status['song'])) {
        $curTrack = getTrackInfo($mpd, $status['song']);
    }
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
    //  distribution default timezone (Pacific/Pago_Pago) and the Wi-Fi regulatory domain = 00
    //  Pago Pago is GMT -11 hours, so any time adjustment can only go forward, it's Wi-Fi regulatory domain is AS
    // experimented with https://ipsidekick.com/ and https://timezoneapi.io, currently using https://ipapi.co/
    //
    $wifiRegDom00 = sysCmd('iw reg get | grep -ic "country 00:"')[0];
    if ($redis->hget('service', 'internet') && ($redis->get('timezone') === 'Pacific/Pago_Pago') && $wifiRegDom00) {
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
                    sysCmd("timedatectl set-timezone 'Pacific/Pago_Pago'");
                } else {
                    $redis->set('timezone', $timeZone);
                    $redis->set('regdom', $countryCode);
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
            // it's not been set automatically, so set it to '00'
            $redis->set('regdom', '00');
        }
        // set the Wi-Fi regulatory domain based on the current time zone
        wrk_setRegDom($redis);
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
    if (!$redis->exists('regdom')) {
        // it's not been set automatically or manually yet, don't reset it
        return false;
    }
    $timeZone = $redis->get('timezone');
    // determine the country code from the timezone
    $tz = new DateTimeZone($timeZone);
    $countryCode = timezone_location_get($tz)['country_code'];
    // set the Wi-Fi regulatory domain, the standard is 00 and is compatible with most countries
    // setting it will could allow more Wi-Fi power to be used (never less) and sometimes improve the usable frequency ranges
    // not all country codes have a specificity specified regulatory domain profile, so if it fails, set to the default (00)
    sysCmd('iw reg set '.$countryCode.' || iw reg set 00');
    $newCountryCode = trim(sysCmd('iw reg get')[0]);
    $redis->set('regdom', $newCountryCode);
}

function ui_update($redis, $sock=null, $clientUUID=null)
{
    $activePlayerInfo = json_decode($redis->get('act_player_info'), true);
    if (isset($activePlayerInfo['actPlayer']) && $activePlayerInfo['actPlayer']) {
        // clear some act_player_info fields
        unset ($activePlayerInfo['elapsed']);
        ui_render('playback', json_encode($activePlayerInfo));
    }
    ui_libraryHome($redis, $clientUUID);
    switch ($redis->get('activePlayer')) {
        case 'MPD':
            return sysCmd('mpc status && mpc move 1 1 || mpc clear');
            break;
            // if ($redis->get('pl_length') !== '0') {
                // sendMpdCommand($sock, 'swap 0 0');
            // } else {
                // sendMpdCommand($sock, 'clear');
            // }
            // // return MPD response
            // return readMpdResponse($sock);
            // break;
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
        ui_notify($notify['title'].'', $notify['text']);
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
function osort(&$array, $key, $descending=false)
// $array is passed by reference, nothing needs to be returned
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
            return $a[$key] <=> $b[$key];
        });
    } else {
        // ascending sort
        usort($array, function($a, $b) use ($key) {
            return $a[$key] <=> $b[$key];
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
            // truncate the string up to a space colon, space semicolon, space slash or the
            //  text strings ' Feat. ', ' feat. ', ' Ft. ' or ' ft. ' (=featuring)
            //  this should remove a second artist or a featured artist from the string
            $string = preg_split('!( \:)|( \;)|( \/)|( Feat. )|( feat. )|( Ft. )|( ft. )!', $string)[0];
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
        // set Wi-Fi nic power management off and save its state
        $retval = sysCmd('iw dev '.$nic.' get power_save | cut -d ":" -f 2 | xargs');
        if (isset($retval[0])) {
            $networkInterfaces[$nic]['power_management'] = $retval[0];
        }
        if (!isset($retval[0]) || ($retval[0] != 'off')) {
            sysCmd('iw dev '.$nic.' set power_save off');
            $retval = sysCmd('iw dev '.$nic.' get power_save | cut -d ":" -f 2 | xargs');
            if (isset($retval[0])) {
                $networkInterfaces[$nic]['power_management'] = $retval[0];
            }
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
    if ($redis->get('avahi_nic') != $avahiNic) {
        // the nic assigned to avahi need to be changed
        if ($avahiNic === '') {
            $avahiLine = '#allow-interfaces=eth0';
        } else {
            $avahiLine = 'allow-interfaces='.$avahiNic;
        }
        sysCmd("sed -i '/allow-interfaces=/c\\".$avahiLine."' /etc/avahi/avahi-daemon.conf");
        // avahi needs to be restarted to activate the new entry in the config file
        sysCmd('systemctl daemon-reload; systemctl restart avahi-daemon');
        $redis->set('avahi_nic', $avahiNic);
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
        $queuedSongs = '';
    }
    unset($retval);
    // set up ashuffle tweaks to randomise the ashuffle window size (default = 7) to enhance the randomness and set
    //  the suspend timeout to its redis value (nominally 20ms) to prevent crashes after clearing the queue
    //$randomWindow = random_int(7, 20);
    $randomWindow = rand(7, 20);
    $tweaks = ' -t window-size='.$randomWindow.' -t suspend-timeout='.$redis->hGet('globalrandom', 'suspend_timeout');
    $hostAndPort = ' --host '."'".$redis->hGet('mpdconf', 'bind_to_address')."'".' --port '."'".$redis->hGet('mpdconf', 'port')."'";
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
                        sysCmd("sed -i '/^ExecStart/s/-q 1/-q 0/' '".$ashuffleUnitFilename."'");
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
                        sysCmd("sed -i '/^ExecStart/s/-q 0/-q 1/' '".$ashuffleUnitFilename."'");
                        // reload the service file
                        sysCmd('systemctl daemon-reload');
                        // stop ashuffle if it is running
                        sysCmd('pgrep -x ashuffle && systemctl stop ashuffle');
                    }
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
            $newArray = wrk_replaceTextLine($ashuffleUnitFilename, '', 'ExecStart=', 'ExecStart=/usr/bin/ashuffle -q '.$queuedSongs.' -f '."'".$playlistFilename."'".$hostAndPort.$tweaks);
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
            $newArray = wrk_replaceTextLine($ashuffleUnitFilename, '', 'ExecStart=', 'ExecStart=/usr/bin/ashuffle -q '.$queuedSongs.$hostAndPort.$tweaks.$ashuffleAlbum.$randomExclude);
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
                        $retval = sysCmd('ps -C mpd -o etimes=');
                        if (!isset($retval) || !is_array($retval) || !$retval) {
                            // no value, MPD is probably not running
                            $mpd_uptime = 0;
                        } else {
                            // a value has been returned
                            $mpd_uptime = intval(trim($retval[0]));
                        }
                        if ($mpd_uptime > intval($redis->hGet('globalrandom', 'start_delay'))) {
                            // remove any invalid symlinks in the playlist directory
                            sysCmd('find '."'".$playlistDirectory."'".' -xtype l -delete');
                            // check that the queued songs based on crossfade is set correctly
                            wrk_ashuffle($redis, 'checkcrossfade');
                            sysCmd('pgrep -x ashuffle || systemctl start ashuffle');
                            sysCmdAsync('nice --adjustment=4 /srv/http/command/rune_prio nice');
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
                        // set this card to the default alsa card
                        set_alsa_default_card($enabled);
                    }
                }
            }
        }
    }
}

// function which cleans up old cached radio metadata, artist_song metadata, artist_album metadata, artist metadata and local cached album art
function wrk_clean_music_metadata($redis, $clearAll=null)
// when $clearAll is set to a true value all cached information will be cleared
{
    // initialise variables
    $artDir = rtrim(trim($redis->get('albumart_image_dir')), '/');
    $overlay_art_cache = $redis->get('overlay_art_cache');
    // if required sync the in-memory tmpfs to the overly cache
    if ($overlay_art_cache) {
        // overlay cache is enabled
        $cleanUpperDir = dirname($artDir).'/upper';
        $cleanLowerDir = '/home/cache/art';
        // sync the files part 1
        //  image files are not synced
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
            '--exclude="*" '.
            $cleanUpperDir.'/ '.
            $cleanLowerDir.'/';
        sysCmd($rsyncCommand);
        // sync the files part 2
        //  image files are not synced
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
    // we will always leave 10 files regardless of the memory which we want to recover unless $clearAll is set to true
    if ($clearAll) {
        $fileToSave = 0;
    } else {
        $fileToSave = 10;
    }
    // clean up the album art files in the upper directory
    //
    // files in the $requiredFiles array are never deleted
    $requiredFiles = array('none.png', 'black.png', 'airplay.png', 'spotify-connect.png', 'radio.png');
    // touch the required files this will ensure that they have a timestamp of now, these are then last in the directory listing for deletion
    foreach ($requiredFiles as $requiredFile) {
        touch($cleanUpperDir.'/'.$requiredFile);
    }
    // always remove files which over 3 months (90 days) old
    // the following command removes all files from the art directory which are older than 90 days
    sysCmd('find "'.$cleanUpperDir.'" -type f -mtime +90 -exec rm {} \;');
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
    // the following command removes all files from the lower directory which are older than 90 days
    // the strategy is that we have used them for 3 months, but their source information may now have changed
    sysCmd('find "'.$cleanLowerDir.'" -type f -mtime +90 -exec rm {} \;');
    // artist files (these can contain the text 'Sorry, no details available') without any content are deleted after 30 days
    // the strategy is that new artists may get modified information within a couple of weeks, in this way they are refreshed
    $files = sysCmd("find '".$cleanLowerDir."' -type f -mtime +30 -exec grep -il 'Sorry, no details available' {} \;");
    foreach ($files as $file) {
        unlink($file);
    }
    unset($files, $file);
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
    if ($recoverKB) {
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
                        $retval = explode(':',trim(preg_replace('!\s+!', ' ', $retval[0])));
                        $mpdvolume = trim(preg_replace('/[^0-9]/', '', $retval[1]));
                        // sleep 1 second before looping
                        sleep(1);
                    } else {
                        // set the mpd volume directly
                        $retval = sysCmd('mpc volume '.$lastmpdvolume.' | grep "volume:" | xargs');
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
    }
    return '';
}

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
    $retval = json_decode(curlGet($url, $proxy), true);
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
            // 3 seconds is a little on the high side, 1 or 2 is probably better
            // setting it higher results in less failures, but causes musicbrainz to search for more obscure and more false matches
            // but this part of the code is attempted only when musicbrainz is up, so it should not be a problem
            'timeout' => 3,
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
            // 3 seconds is a little on the high side, 1 or 2 is probably better.
            // but this part of the code is attempted only when fanart.tv is up, so it should not be a problem
            'timeout' => 3,
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

// function to get information from makeitpersonal
function get_makeitpersonal($redis, $url)
// returns false or the response from makeitpersonal as an array
// no authorisation token required
{
    $makeitpersonalUp = $redis->hGet('service', 'makeitpersonal');
    if (!$makeitpersonalUp) {
        // makeitpersonal is down
        return 0;
    }
    // $proxy = $redis->hGetall('proxy');
    // using a proxy is possible but not implemented
    $retval = sysCmd('curl -s --connect-timeout 3 -m 7 --retry 1 "'.$url.'"');
    $retval = preg_replace('!\s+!', ' ', implode('<br>', $retval));
    if (!$retval) {
        // nothing returned, it should always return something, disable makeitpersonal
        $redis->hSet('service', 'makeitpersonal', 0);
        // this will be reset each 15 minutes, if the makeitpersonal site is up
        return 0;
    } else if (strpos(strtolower(' '.$retval), '>oh-noes<')) {
        // 'oh-noes' returned, error condition in a web page, but not fatal
        return 0;
    } else if (strpos(strtolower(' '.$retval), '>oh noes!<')) {
        // 'oh noes' returned, error condition in a web page, but not fatal
        return 0;
    } else if (strpos(strtolower(' '.$retval), 'something went wrong')) {
        // 'something went wrong' returned, error condition, disable makeitpersonal
        $redis->hSet('service', 'makeitpersonal', 0);
        // this will be reset each 15 minutes, if the makeitpersonal site is up
        return 0;
    }
    $return = array();
    $return['song_lyrics'] = $retval;
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
            // 3 seconds is a little on the high side, 1 or 2 is probably better.
            // but this part of the code is attempted only when musicbrainz is up, so it should not be a problem
            'timeout' => 3,
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
        if (!$info[$toSetInfoField]) {
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
    if ($info['song_filename']){
        $fileName = $artDir.'/'.$info['song_filename'].'.song';
        clearstatcache(true, $fileName);
        if (file_exists($fileName)) {
            // found a cached file, update its timestamp, use it and return
            $infoCache = json_decode(trim(file_get_contents($fileName)), true);
            if ($fileName == $infoCache['song_filename']) {
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
        $info['song_lyrics'] = '<br>No lyrics available<br>';
        return $info;
    }
    // build up an array of artist and album search strings based on album artist, (song) artist and album name
    //  no duplicates in the array,
    //  each element is non-null, no non-space whitespace, single spaces, no leading or trailing spaces, lowercase, max. 100 chars
    $searchArtists = array();
    $artist = substr(trim(preg_replace('!\s+!', ' ', strtolower($info['artist']))),0, 100);
    if ($artist && !strpos(' '.$artist, 'various') && !in_array($artist, $searchArtists)) {
        $searchArtists[] = $artist;
    }
    $artist = substr(trim(preg_replace('!\s+!', ' ', strtolower($info['albumartist']))),0, 100);
    if ($artist && !strpos(' '.$artist, 'various') && !in_array($artist, $searchArtists)) {
        $searchArtists[] = $artist;
    }
    $artist = substr(metadataStringClean(strtolower($info['artist']), 'artist'),0, 100);
    if ($artist && !strpos(' '.$artist, 'various') && !in_array($artist, $searchArtists)) {
        $searchArtists[] = $artist;
    }
    $artist = substr(metadataStringClean(strtolower($info['albumartist']), 'artist'), 0, 100);
    if ($artist && !strpos(' '.$artist, 'various') && !in_array($artist, $searchArtists)) {
        $searchArtists[] = $artist;
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
    // lyrics are sourced from makeitpersonal using artist name and song name as key
    if (!$info['song_lyrics']) {
        foreach ($searchArtists as $searchArtist) {
            foreach ($searchSongs as $searchSong) {
                // url format: https://makeitpersonal.co/lyrics?artist=annie+lennox&title=little+bird
                $url = 'https://makeitpersonal.co/lyrics?artist='.urlClean($searchArtist).'&title='.urlClean($searchSong);
                $retval = get_makeitpersonal($redis, $url);
                if ($retval) {
                    // found the release (song) on last.fm, use the data if it is set
                    if (isset($retval['song_lyrics']) && $retval['song_lyrics']) {
                        $info['song_lyrics'] = $retval['song_lyrics'];
                        // break both loops
                        break 2;
                    }
                }
            }
        }
    }
    if (!$info['song_lyrics']) {
        $info['song_lyrics'] = '<br>No lyrics available<br>';
    }
    if ($info['artist'] && $info['song']) {
        $info['song_filename'] = format_artist_song_file_name($info['artist'], $info['song']);
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
        if (!$info[$toSetInfoField]) {
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
    if ($info['album_filename']){
        $fileName = $artDir.'/'.$info['album_filename'].'.album';
        clearstatcache(true, $fileName);
        if (file_exists($fileName)) {
            // found a cached file, update its timestamp, use it and return
            $infoCache = json_decode(trim(file_get_contents($fileName)), true);
            if ($fileName == $infoCache['album_filename']) {
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
    $artist = substr(trim(preg_replace('!\s+!', ' ', strtolower($info['artist']))),0, 100);
    if ($artist && !in_array($artist, $searchArtists)) {
        $searchArtists[] = $artist;
    }
    $artist = substr(trim(preg_replace('!\s+!', ' ', strtolower($info['albumartist']))),0, 100);
    if ($artist && !in_array($artist, $searchArtists)) {
        $searchArtists[] = $artist;
    }
    $artist = substr(metadataStringClean(strtolower($info['artist']), 'artist'),0, 100);
    if ($artist && !in_array($artist, $searchArtists)) {
        $searchArtists[] = $artist;
    }
    $artist = substr(metadataStringClean(strtolower($info['albumartist']), 'artist'), 0, 100);
    if ($artist && !in_array($artist, $searchArtists)) {
        $searchArtists[] = $artist;
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
                if ($fileName == $infoCache['album_filename']) {
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
    if ($info['artist'] && $info['album']) {
        $info['album_filename'] = format_artist_album_file_name($info['artist'], $info['album']);
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
        if (!$info[$toSetInfoField]) {
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
    if ($info['artist_filename']){
        $fileName = $artDir.'/'.$info['artist_filename'].'.artist';
        clearstatcache(true, $fileName);
        if (file_exists($fileName)) {
            // found a cached file, update its timestamp, use it and return
            $infoCache = json_decode(trim(file_get_contents($fileName)), true);
            if ($fileName == $infoCache['artist_filename']) {
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
    }
    // build up an array of artist search strings based on album artist and (song) artist
    //  no duplicates in the array,
    //  each element is non-null, no non-space whitespace, single spaces, no leading or trailing spaces, lowercase, max. 100 chars
    $searchArtists = array();
    $artist = substr(trim(preg_replace('!\s+!', ' ', strtolower($info['artist']))),0, 100);
    if ($artist && !strpos(' '.$artist, 'various') && !in_array($artist, $searchArtists)) {
        $searchArtists[] = $artist;
    }
    $artist = substr(trim(preg_replace('!\s+!', ' ', strtolower($info['albumartist']))),0, 100);
    if ($artist && !strpos(' '.$artist, 'various') && !in_array($artist, $searchArtists)) {
        $searchArtists[] = $artist;
    }
    $artist = substr(metadataStringClean(strtolower($info['artist']), 'artist'),0, 100);
    if ($artist && !strpos(' '.$artist, 'various') && !in_array($artist, $searchArtists)) {
        $searchArtists[] = $artist;
    }
    $artist = substr(metadataStringClean(strtolower($info['albumartist']), 'artist'), 0, 100);
    if ($artist && !strpos(' '.$artist, 'various') && !in_array($artist, $searchArtists)) {
        $searchArtists[] = $artist;
    }
    // search for an existing cached file
    foreach ($searchArtists as $searchArtist) {
        $artistFilename = format_artist_file_name($searchArtist);
        $fileName = $artDir.'/'.$artistFilename.'.artist';
        clearstatcache(true, $fileName);
        if (file_exists($fileName)) {
            // found a cached file, update its timestamp, use it and return
            $infoCache = json_decode(trim(file_get_contents($fileName)), true);
            if ($fileName == $infoCache['artist_filename']) {
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
            }
        }
        if ($retval) {
            if (!$info['artist_mbid'] && isset($retval['artist']['mbid']) && trim($retval['artist']['mbid']) ) {
                $info['artist_mbid'] = trim($retval['artist']['mbid']);
            }
            if (!$info['artist_bio_summary'] && isset($retval['artist']['bio']['summary']) && trim($retval['artist']['bio']['summary'])) {
                $info['artist_bio_summary'] = trim(str_replace('">Read more on Last.fm', '/+wiki" target="_blank" rel="nofollow">Read more on Last.fm', preg_replace('/[\t\n\r\s]+/',' ',stripcslashes($retval['artist']['bio']['summary']))));
                if (substr($info['artist_bio_summary'], 0, 1) === '<') {
                    $info['artist_bio_summary'] = $info['artist'].' - '.$info['artist_bio_summary'];
                }
            }
            if (!$info['artist_bio_content'] && isset($retval['artist']['bio']['content']) && trim($retval['artist']['bio']['content'])) {
                $info['artist_bio_content'] = trim(str_replace('">Read more on Last.fm', '/+wiki" target="_blank" rel="nofollow">Read more on Last.fm', preg_replace('/[\t\n\r\s]+/',' ',stripcslashes($retval['artist']['bio']['content']))));
            }
                if (substr($info['artist_bio_content'], 0, 1) === '<') {
                    $info['artist_bio_content'] = $info['artist'].' - '.$info['artist_bio_content'];
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
    if ($info['artist']) {
        $info['artist_filename'] = format_artist_file_name($info['artist']);
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
function get_between_data($string, $start, $end, $occurence=1)
{
    if ( !is_numeric($occurence) ) {
        $substr_data = "";
    } else {
        if ( $occurence < 1 ) {
            $substr_data = "";
        } else {
            $substr_data = $string;
        }
    }
    for ($i = 1; $i <= $occurence; $i++) {
        $pos_start = stripos($substr_data, $start);
        if ($pos_start === false) {
            $substr_data = "";
            break;
        }
        else {
            $substr_data = substr($substr_data, $pos_start+strlen($start));
        }
    }
    $pos_end = stripos($substr_data, $end);
    if ($pos_end === false) {
        $substr_data = "";
    }
    else {
        $substr_data = trim(substr($substr_data, 0, $pos_end));
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
// $playerType can have the following values: 'MPD' (default), 'Spotify', 'Airplay', 'Spotify connect'
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
    $status['currentalbum'] = '-----';
    $status['currentalbumartist'] = $playerType;
    $status['currentartist'] = $playerType;
    $status['currentcomposer'] = '';
    $status['currentsong'] = '-----';
    $status['duration'] = '';
    $status['elapsed'] = '0';
    $status['file'] = '';
    $status['fileext'] = '';
    if ($playerTypeLower === 'mpd') {
        $status['mainArtURL'] = $artUrl.'/none.png';
    } else if ($playerTypeLower === 'spotify') {
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
    if ($redis->get('volume')) {
        if (!empty($redis->get('lastmpdvolume'))) {
            $status['volume'] = $redis->get('lastmpdvolume');
        } else {
            $status['volume'] = '0';
        }
    }
    if (!empty($redis->hGet('mpdconf', 'crossfade'))) {
        $status['xfade'] = $redis->hGet('mpdconf', 'crossfade');
    } else {
        $status['xfade'] = '0';
    }
    // save JSON response for extensions
    $redis->set('act_player_info', json_encode($status));
    ui_render('playback', json_encode($status));
    sysCmd('curl -s -X GET http://localhost/command/?cmd=renderui');
    sysCmdAsync('/srv/http/command/ui_update_async', 0);
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

// fucntion to retrieve Spotify metadata based on a track ID
function wrk_getSpotifyMetadata($redis, $track_id)
// track ID is returned by programs like spotifyd
// this routine uses open Spotify URL's, and the returned web pages are screen-scraped
// the results are returned in an array containing:
//  array['artist'] > artist name
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
    // get the album art directory and url dir
    $artDir = rtrim(trim($redis->get('albumart_image_dir')), '/');
    $artUrl = trim($redis->get('albumart_image_url_dir'), " \n\r\t\v\0/");
    // set the variables to default values
    $retval = array();
    $retval['artist'] = 'Spotify';
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
        // curl -s 'https://open.spotify.com/track/<TRACK_ID>' | sed 's/<meta/\n<meta/g' | grep -i -E 'og:title|og:image|og:description|music:duration|music:album|music:musician'
        $command = 'curl -s -f --connect-timeout 5 -m 10 --retry 2 '."'".'https://open.spotify.com/track/'.$track_id."'".' | sed '."'".'s/<meta/\n<meta/g'."'".' | grep -i -E '."'".'og:title|og:image|og:description|music:duration|music:album|music:musician'."'";
        //$command = 'curl -s '."'".'https://open.spotify.com/track/'.$track_id."'".' | sed '."'".'s/<meta/\n<meta/g'."'".' | grep -i -E '."'".'og:title|og:image|og:description|music:duration|music:album|music:musician'."'";
        runelog('wrk_getSpotifyMetadata track command:', $command);
        $trackInfoLines = sysCmd($command);
        $timeout = true;
        foreach ($trackInfoLines as $workline) {
            // replace all combinations of single or multiple tab, space, <cr> or <lf> with a single space
            $line = preg_replace('/[\t\n\r\s]+/', ' ', $workline);
            // then strip the html out of the response
            $line = preg_replace('/\<[\s]*meta property[\s]*="/', '', $line);
            $line = preg_replace('/"[\s]*content[\s]*=[\s]*/', '=', $line);
            $line = preg_replace('!"[\s]*/[\s]*\>!', '', $line);
            $line = preg_replace('/"[\s]*\>/', '', $line);
            $line = preg_replace('/[\s]*"[\s]*/', '', $line);
            $line = trim($line);
            // result is <identifier>=<value>
            $lineparts = explode('=', $line);
            if ($lineparts[0] === 'og:title') {
                $retval['title'] = trim($lineparts[1]);
                runelog('wrk_getSpotifyMetadata track title:', $retval['title']);
                $timeout = false;
            } elseif ($lineparts[0] === 'og:image') {
                $retval['albumart_url'] = trim($lineparts[1]);
                runelog('wrk_getSpotifyMetadata track albumart_url:', $retval['albumart_url']);
            } elseif ($lineparts[0] === 'og:description') {
                $description = trim($lineparts[1]);
                runelog('wrk_getSpotifyMetadata description:', $description);
                $retval['artist'] = get_between_data($description, '', ' · ');
                $retval['year'] = substr($description, -4);
                runelog('wrk_getSpotifyMetadata artist:', $retval['artist']);
                runelog('wrk_getSpotifyMetadata yeat:', $retval['year']);
            } elseif ($lineparts[0] === 'music:duration') {
                $retval['duration_in_sec'] = trim($lineparts[1]);
                runelog('wrk_getSpotifyMetadata track duration_in_sec:', $retval['duration_in_sec']);
            } elseif ($lineparts[0] === 'music:album') {
                $retval['album_url'] = trim($lineparts[1]);
                runelog('wrk_getSpotifyMetadata track album_url:', $retval['album_url']);
            } elseif ($lineparts[0] === 'music:musician') {
                $retval['artist_url'] = trim($lineparts[1]);
                runelog('wrk_getSpotifyMetadata track artist_url:', $retval['artist_url']);
            }
            unset($lineparts);
        }
        unset($trackInfoLines, $workline, $line);
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
        runelog('wrk_getSpotifyMetadata ALBUM_URL:', 'Empty');
    } else if ($retval['date'] == date("Ymd")) {
        // do nothing
    } else {
        // album name is still the default
        runelog('wrk_getSpotifyMetadata ALBUM_URL:', $retval['album_url']);
        // curl -s '<ALBUM_URL>' | head -c 2000 | sed 's/<meta/\n<meta/g' | grep -i 'og:title'
        $command = 'curl -s -f --connect-timeout 5 -m 10 --retry 2 '."'".$retval['album_url']."'".' | head -c 2000 | sed '."'".'s/<meta/\n<meta/g'."'".' | grep -i '."'".'og:title'."'";
        // $command = 'curl -s '."'".$album_url."'".' | sed '."'".'s/<meta/\n<meta/g'."'".' | grep -i '."'".'og:title'."'";
        runelog('wrk_getSpotifyMetadata album command:', $command);
        $albumInfoLines = sysCmd($command);
        $timeout = true;
        foreach ($albumInfoLines as $workline) {
            // replace all combinations of single or multiple tab, space, <cr> or <lf> with a single space
            $line = preg_replace('/[\t\n\r\s]+/', ' ', $workline);
            // then strip the html out of the response
            $line = preg_replace('/\<[\s]*meta property[\s]*="/', '', $line);
            $line = preg_replace('/"[\s]*content[\s]*=[\s]*/', '=', $line);
            $line = preg_replace('!"[\s]*/[\s]*\>!', '', $line);
            $line = preg_replace('/"[\s]*\>/', '', $line);
            $line = preg_replace('/[\s]*"[\s]*/', '', $line);
            $line = trim($line);
            // result is <identifier>=<value>
            $lineparts = explode('=', $line);
            if ($lineparts[0] === 'og:title') {
                $retval['date'] = date("Ymd");
                $retval['album'] = trim($lineparts[1]);
                runelog('wrk_getSpotifyMetadata album title:', $retval['album']);
                $timeout = false;
                unset($retval['album_url']);
            }
            unset($lineparts);
        }
        unset($albumInfoLines, $workline, $line);
        if ($timeout) {
            // timeout for an hour (= current timestamp + 60x60 seconds)
            $redis->hSet('spotifyconnect', 'metadata_timeout_restart_time', microtime(true) + (60*60));
        } else {
            // cache the track ID information for the next time
            file_put_contents($cacheFile, json_encode($retval)."\n");
        }
        return $retval;
    }

    // get the artist name
    if (!isset($retval['artist_url']) || !$retval['artist_url']) {
        runelog('wrk_getSpotifyMetadata ARTIST_URL:', 'Empty');
    } else if ($retval['date'] == date("Ymd")) {
        // do nothing
    } else {
        runelog('wrk_getSpotifyMetadata ARTIST_URL:', $retval['artist_url']);
        // curl -s '<ARTIST_URL>' | head -c 2000 | sed 's/<meta/\n<meta/g' | grep -i 'og:title'
        $command = 'curl -s -f --connect-timeout 5 -m 10 --retry 2 '."'".$retval['artist_url']."'".' | head -c 2000 | sed '."'".'s/<meta/\n<meta/g'."'".' | grep -i '."'".'og:title'."'";
        //$command = 'curl -s '."'".$artist_url."'".' | sed '."'".'s/<meta/\n<meta/g'."'".' | grep -i '."'".'og:title'."'";
        runelog('wrk_getSpotifyMetadata artist command:', $command);
        $artistInfoLines = sysCmd($command);
        $timeout = true;
        foreach ($artistInfoLines as $workline) {
            // replace all combinations of single or multiple tab, space, <cr> or <lf> with a single space
            $line = preg_replace('/[\t\n\r\s]+/', ' ', $workline);
            // then strip the html out of the response
            $line = preg_replace('/\<[\s]*meta property[\s]*="/', '', $line);
            $line = preg_replace('/"[\s]*content[\s]*=[\s]*/', '=', $line);
            $line = preg_replace('!"[\s]*/[\s]*\>!', '', $line);
            $line = preg_replace('/"[\s]*\>/', '', $line);
            $line = preg_replace('/[\s]*"[\s]*/', '', $line);
            $line = trim($line);
            // result is <identifier>=<value>
            $lineparts = explode('=', $line);
            if ($lineparts[0] === 'og:title') {
                $retval['artist'] = trim($lineparts[1]);
                runelog('wrk_getSpotifyMetadata artist title:', $retval['artist']);
                $timeout = false;
                unset($retval['artist_url'], $retval['date']);
            }
            unset($lineparts);
        }
        unset($artistInfoLines, $workline, $line);
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
//  array['artist'] > artist name
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
        $retval['artist'] = $metadata['artists'][0]['name']; // just use the first artist name, there are optionally more
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
function getMusicFileMatadata($redis, $filename)
// returns the cached metadata for the music file
//  all MPD music files which are currently playing (should) have a cached metadata file
//  contents include:
//      ["format_name"] (example content = "MP3")
//      ["encoder_version"] (example content = "LAME3.92")
//      ["encoder_options"] (example content = "CBR320")
//      ["bitrate_mode"] (example content = "cbr", "vbr")
//      ["channels"] (example content = 2)
//      ["sample_rate"] (example content = 48000)
//      ["bits_per_sample"] (example content = 0)
//      ["playing_time"] (example content = 380.47199999999998)
//      ["avg_bit_rate"] (example content = 320000)
{
    // get the album art directory and url dir
    $artDir = rtrim(trim($redis->get('albumart_image_dir')), '/');
    $metadataFileName = $artDir.'/'.md5($filename).'.mpd';
    clearstatcache(true, $metadataFileName);
    if (!file_exists($metadataFileName)) {
        return false;
    }
    $metadata = json_decode(file_get_contents($metadataFileName), true);
    return $metadata;
}

// get the value of the first matching key in a single or multidimensional array
function search_array_keys($myArray, $search)
// returns the first non-null/non-false value of an array node when its key matching the search string
// it really only works well returning strings, null and boolean values give incorrect results
// no match returns false, a match with a null or boolian false value returns false
{
    foreach ($myArray as $key => $value) {
        if (is_array($value)) {
            $retval = search_array_keys($value, $search);
            if ($retval) {
                return $retval;
            }
        } else {
            if ($key == $search) {
                return $value;
            }
        }
    }
    return false;
}

// sets the default alsa card, based on the card name
function set_alsa_default_card($cardName)
{
    $fileName = '/etc/asound.conf';
    if (isset($cardName) && $cardName) {
        $cardNummer = sysCmd("aplay -l | grep '".$cardName."'");
        if (isset($cardNummer[0]) && $cardNummer[0]) {
            $cardNummer = trim(get_between_data($cardNummer[0], 'card ', ': '));
        }
    } else {
        return;
    }
    if (!isset($cardNummer) || !is_numeric($cardNummer)) {
        // card number is not set, remove entries from /etc/asound.conf
        clearstatcache(true, $fileName);
        if (file_exists($fileName)) {
            // file exists
            sysCmd("sed -i '/defaults.pcm.card/d' '".$fileName."'");
            sysCmd("sed -i '/defaults.ctl.card/d' '".$fileName."'");
        } else {
            return;
        }
    } else {
        // card number is set modify/add entries to /etc/asound.conf
        clearstatcache(true, $fileName);
        if (file_exists($fileName)) {
            // file exists, remove then add the default lines
            sysCmd("sed -i '/defaults.pcm.card/d' '".$fileName."'");
            sysCmd("sed -i '/defaults.ctl.card/d' '".$fileName."'");
            sysCmd('echo defaults.pcm.card '.$cardNummer." >> '".$fileName."'");
            sysCmd('echo defaults.ctl.card '.$cardNummer." >> '".$fileName."'");
        } else {
            // no file, create it an then add the lines
            touch($fileName);
            sysCmd('echo defaults.pcm.card '.$cardNummer." >> '".$fileName."'");
            sysCmd('echo defaults.ctl.card '.$cardNummer." >> '".$fileName."'");
        }
    }
    // force alsa to reload all card profiles (should not be required, but some USB audio devices seem to need it)
    sysCmd('alsactl kill rescan');
}
