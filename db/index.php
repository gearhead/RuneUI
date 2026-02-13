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
 *  file: db/index.php
 *  version: 1.3
 *  coder: Simone De Gregori
 *
 */
// Environment vars
// common include
if ((isset($_SERVER['HOME'])) && ($_SERVER['HOME']) && ($_SERVER['HOME'] != '/root')) {
    require_once($_SERVER['HOME'].'/app/config/config.php');
} else {
    require_once('/var/www/app/config/config.php');
}
ini_set('display_errors', -1);
error_reporting(E_ALL);
// check current player backend
$activePlayer = $redis->get('activePlayer');
if (isset($_GET['cmd']) && !empty($_GET['cmd'])) {
    switch ($_GET['cmd']) {
        case 'browse':
            if (isset($_POST['path']) && $_POST['path'] !== '') {
                if ($_POST['path'] === 'Albums' OR $_POST['path'] === 'Artists' OR $_POST['path'] === 'Genres' OR $_POST['path'] === 'Composer') {
                    echo json_encode(browseDB($mpd, $_POST['browsemode']));
                } else {
                    echo json_encode(browseDB($mpd, $_POST['browsemode'], $_POST['path']));
                }
            } else {
                if ($activePlayer === 'MPD') {
                    // MPD
                    echo json_encode(browseDB($mpd, $_POST['browsemode']));
                }
            }
            break;
        case 'playlist':
            // open non blocking socket with mpd daemon
            // $mpd2 = openMpdSocket('/run/mpd/socket', 2);
            // $mpd2 = openMpdSocket($redis->hGet('mpdconf', 'bind_to_address'), 2);
            // getPlayQueue($mpd2);
            // closeMpdSocket($mpd2);
            if ($activePlayer === 'MPD') {
                // $resp = trim(getPlayQueue($mpd), "\x7f..\xff\x0..\x1f");
                // if (substr($resp, 0, 2) == '\n') {
                    // $resp = substr($resp, 2);
                // }
                // echo $resp;
                echo getPlayQueue($mpd);
                // echo trim(getPlayQueue($mpd), "\x7f..\xff\x0..\x1f");
            }
            break;
        case 'add':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['path'])) {
                    if ($_POST['path'] == 'cdda://') {
                        wrk_CD($redis, 'playCD', 'Add');
                        ui_notify($redis, 'CD added to queue', $_POST['path']);
                    } else {
                        addToQueue($mpd, $_POST['path']);
                        // send MPD response to UI
                        ui_notify($redis, 'Added to queue', $_POST['path']);
                    }
                }
            }
            break;
        case 'addplay':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['path'])) {
                    if ($_POST['path'] == 'cdda://') {
                        wrk_CD($redis, 'playCD', 'AddPlay');
                        ui_notify($redis, 'CD added to queue', $_POST['path']);
                    } else {
                        $status = _parseStatusResponse($redis, MpdStatus($mpd));
                        $pos = $status['playlistlength'];
                        addToQueue($mpd, $_POST['path'], 1, $pos);
                        // send MPD response to UI
                        ui_notify($redis, 'Added to queue', $_POST['path']);
                    }
                }
            }
            break;
        case 'addnext':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['path'])) {
                    if ($_POST['path'] == 'cdda://') {
                        wrk_CD($redis, 'playCD', 'AddNext');
                        ui_notify($redis, 'CD inserted next in queue', $_POST['path']);
                    } else {
                        if (addNextToQueue($redis, $mpd, $_POST['path'])) {
                            ui_notify($redis, 'Inserted next in queue', $_POST['path']);
                            //ui_mpd_response($mpd, array('title' => 'Inserted next in queue', 'text' => $_POST['path']));
                        } else {
                            ui_notifyError($redis, 'Failed to insert next in queue', $_POST['path']);
                        }
                    }
                } else {
                    ui_notifyError($redis, 'Failed to insert next in queue, no path set');
                }
            }
            break;
        case 'addnextplay':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['path'])) {
                    if ($_POST['path'] == 'cdda://') {
                        wrk_CD($redis, 'playCD', 'AddNextPlay');
                        ui_notify($redis, 'CD inserted next in queue', $_POST['path']);
                    } else {
                        if (addNextToQueueAndPlay($redis, $mpd, $_POST['path'])) {
                            ui_notify($redis, 'Inserted next in queue', $_POST['path']);
                            //ui_mpd_response($mpd, array('title' => 'Inserted next in queue', 'text' => $_POST['path']));
                        } else {
                            ui_notifyError($redis, 'Failed to insert next in queue', $_POST['path']);
                        }
                    }
                } else {
                    ui_notifyError($redis, 'Failed to insert next in queue, no path set');
                }
            }
            break;
        case 'addreplaceplay':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['path'])) {
                    if ($_POST['path'] == 'cdda://') {
                        wrk_CD($redis, 'playCD', 'ClearAddPlay');
                        ui_notify($redis, 'Queue cleared<br> CD added to queue', $_POST['path']);
                    } else {
                        addToQueue($mpd, $_POST['path'], 1, 0, 1); // last argument is for the "clear" command
                        // send MPD response to UI
                        ui_notify($redis, 'Queue cleared<br> Added to queue', $_POST['path']);
                    }
                }
            }
            break;
        case 'lastfmadd':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['path'])) {
                    addToQueue($mpd, $_POST['path']);
                    // send MPD response to UI
                    ui_notify($redis, 'Added to queue', $_POST['path']);
                    //ui_mpd_response($mpd, array('title' => 'Added to queue', 'text' => $_POST['path']));
                    // Get the last track and try to use LastFM to populate a similar playlist
                    list($artist, $title) = explode(' - ', sysCmd('/bin/mpc playlist | tail -1')[0], 2);
                    $proxy = $redis->hGetall('proxy');
                    $lastfm_apikey = $redis->get('lastfm_apikey');
                    if (ui_lastFM_similar($redis, trim($artist), trim($title), $lastfm_apikey, $proxy)) {
                        ui_notify($redis, 'Added similar tracks', 'As listed by last.fm');
                    } else {
                        ui_notifyError($redis, 'Error', 'No similar tracks, or last.fm not available to provide similar tracks information');
                    }
                }
                unset($artist, $title, $proxy, $lastfm_apikey);
            }
            break;
        case 'lastfmaddreplaceplay':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['path'])) {
                    sendMpdCommand($mpd, 'clear');
                    addToQueue($mpd, $_POST['path']);
                    sendMpdCommand($mpd, 'play');
                    // send MPD response to UI
                    ui_notify($redis, 'Queue cleared<br> Added to queue', 'As listed by last.fm');
                    //ui_mpd_response($mpd, array('title' => 'Queue cleared<br> Added to queue', 'text' => $_POST['path']));
                    // Get the current track and try to use LastFM to populate a similar playlist
                    $curTrack = getTrackInfo($mpd, $status['song']);
                    if (isset($curTrack[0]['Title'])) {
                        $status['currentartist'] = $curTrack[0]['Artist'];
                        $status['currentsong'] = $curTrack[0]['Title'];
                        $status['currentalbum'] = $curTrack[0]['Album'];
                        $status['fileext'] = parseFileStr($curTrack[0]['file'], '.');
                        $proxy = $redis->hGetall('proxy');
                        $lastfm_apikey = $redis->get('lastfm_apikey');
                        if (ui_lastFM_similar($redis, $status['currentartist'], $status['currentsong'], $lastfm_apikey, $proxy)) {
                            ui_notify($redis, 'Added similar tracks', 'As listed by last.fm');
                        } else {
                            ui_notifyError($redis, 'Error', 'No similar tracks, or last.fm not available to provide similar tracks information');
                        }
                    }
                }
                unset($curTrack, $status, $proxy, $lastfm_apikey);
            }
            break;
        case 'update':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['path'])) {
                    sendMpdCommand($mpd, "update \"".html_entity_decode($_POST['path'])."\"");
                    // send MPD response to UI
                    ui_notify($redis, 'MPD update DB path', $_POST['path']);
                    //ui_mpd_response($mpd, array('title' => 'MPD update DB path:', 'text' => $_POST['path']));
                }
            }
            break;
        case 'rescan':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['path'])) {
                    sendMpdCommand($mpd, "rescan \"".html_entity_decode($_POST['path'])."\"");
                    // send MPD response to UI
                    ui_notify($redis, 'MPD update DB path', $_POST['path']);
                    //ui_mpd_response($mpd, array('title' => 'MPD rescan DB path:', 'text' => $_POST['path']));
                }
            }
            break;
        case 'search':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['query']) && isset($_GET['querytype'])) {
                    echo json_encode(searchDB($mpd, $_GET['querytype'], $_POST['query']));
                }
            }
            break;
        case 'bookmark':
            if (isset($_POST['path'])) {
                if (saveBookmark($redis, $_POST['path'])) {
                    ui_notify($redis, 'Bookmark saved', $_POST['path'].' added to bookmarks');
                    // ui_notify($redis, 'Bookmark saved', $_POST['path'].' added to bookmarks');
                    ui_libraryHome($redis);
                } else {
                    ui_notifyError($redis, 'Error saving bookmark', 'please try again later');
                }
            }
            if (isset($_POST['id'])) {
                if (deleteBookmark($redis,$_POST['id'])) {
                    ui_notify($redis, 'Bookmark deleted', '"' . $_POST['name'] . '" successfully removed');
                    ui_libraryHome($redis);
                } else {
                    ui_notifyError($redis, 'Error deleting bookmark', 'Please try again later');
                }
            }
            break;
        case 'dirble':
            if ($activePlayer === 'MPD') {
                $proxy = $redis->hGetall('proxy');
                $dirblecfg = $redis->hGetAll('dirble');
                $token = '?all=1&token='.$dirblecfg['apikey'];
                $dirblecfg['baseurl'] = 'http://api.dirble.com/v2';
                if (isset($_POST['querytype'])) {
                    // if ($_POST['querytype'] === 'amountStation') {
                    if ($_POST['querytype'] === 'amountStation') {
                        //$dirble = json_decode(curlGet($dirblecfg['baseurl'].'amountStation/apikey/'.$dirblecfg['apikey'], $proxy));
                        //echo $dirble->amount;
                        echo '4048'; // Just a fake value, we need a new implementation of this call in v2 api.
                    }
                    // Get primaryCategories
                    if ($_POST['querytype'] === 'categories' OR $_POST['querytype'] === 'primaryCategories' ) {
                        echo curlGet($dirblecfg['baseurl'].'/categories/primary'.$token, $proxy);
                    }
                    // Get childCategories by primaryid
                    if ($_POST['querytype'] === 'childs' && isset($_POST['args'])) {
                        echo curlGet($dirblecfg['baseurl'].'/category/'.$_POST['args'].'/childs'.$token, $proxy);
                    }
                    // Get childStations by primaryid
                    if ($_POST['querytype'] === 'childs-stations' && isset($_POST['args'])) {
                        echo curlGet($dirblecfg['baseurl'].'/category/'.$_POST['args'].'/stations'.$token, $proxy);
                    }
                    // Get stations by primaryid
                    if ($_POST['querytype'] === 'stations' && isset($_POST['args'])) {
                        echo curlGet($dirblecfg['baseurl'].'/category/'.$_POST['args'].'/stations'.$token, $proxy);
                    }
                    // Get station by ID
                    if ($_POST['querytype'] === 'station' && isset($_POST['args'])) {
                        echo curlGet($dirblecfg['baseurl'].'/station/'.$_POST['args'].$token, $proxy);
                    }
                    // Search radio station
                    if ($_POST['querytype'] === 'search' && isset($_POST['args'])) {
                        echo curlGet($dirblecfg['baseurl'].'/search/'.urlencode($_POST['args']).$token, $proxy);
                    }
                    // Get stations by continent
                    //if ($_POST['querytype'] === 'continent' && isset($_POST['args'])) {
                    //    echo curlGet($dirblecfg['baseurl'].'continent/apikey'.$dirblecfg['apikey'].'/continent/'.$_POST['args'], $proxy);
                    //}
                    // Get stations by country
                    //if ($_POST['querytype'] === 'country' && isset($_POST['args'])) {
                    //    echo curlGet($dirblecfg['baseurl'].'country/apikey'.$dirblecfg['apikey'].'/country/'.$_POST['args'], $proxy);
                    //}
                    // Add station
                    //if ($_POST['querytype'] === 'addstation' && isset($_POST['args'])) {
                        // input array $_POST['args'] = array('name' => 'value', 'streamurl' => 'value', 'website' => 'value', 'country' => 'value', 'directory' => 'value')
                    //    echo curlPost($dirblecfg['baseurl'].'station/apikey/'.$dirblecfg['apikey'], $_POST['args'], $proxy);
                    //}
                }
                unset($proxy, $dirblecfg, $token);
            }
            break;
        case 'jamendo':
            if ($activePlayer === 'MPD') {
                $apikey = $redis->hGet('jamendo', 'clientid');
                $proxy = $redis->hGetall('proxy');
                if ($_POST['querytype'] === 'radio') {
                    $jam_channels = json_decode(curlGet('http://api.jamendo.com/v3.0/radios/?client_id='.$apikey.'&format=json&limit=200', $proxy));
                        foreach ($jam_channels->results as $station) {
                            $channel = json_decode(curlGet('http://api.jamendo.com/v3.0/radios/stream?client_id='.$apikey.'&format=json&name='.$station->name, $proxy));
                            $station->stream = $channel->results[0]->stream;
                        }
                    // TODO: add cache jamendo channels on Redis
                    // $redis->hSet('jamendo', 'ch_cache', json_encode($jam_channels));
                    // echo $redis->hGet('jamendo', 'ch_cache');
                    echo json_encode($jam_channels);
                }
                if ($_POST['querytype'] === 'radio' && !empty($_POST['args'])) {
                    echo curlGet('http://api.jamendo.com/v3.0/radios/stream?client_id='.$apikey.'&format=json&name='.$_POST['args'], $proxy);
                }
                unset($apikey, $proxy, $jam_channels, $channel, $station);
            }
            break;
        case 'addradio':
            if ($activePlayer === 'MPD') {
            // input array= $_POST['radio']['label'] $_POST['radio']['url']
                wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'webradio', 'action' => 'add', 'args' => $_POST['radio']));
            }
            break;
        case 'editradio':
            if ($activePlayer === 'MPD') {
                // input array= $_POST['radio']['label'] $_POST['radio']['newlabel'] $_POST['radio']['url']
                wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'webradio', 'action' => 'edit', 'args' => $_POST['radio']));
            }
            break;
        case 'readradio':
            if ($activePlayer === 'MPD') {
                $name = parseFileStr(parseFileStr($_POST['filename'], '.', 1), '/');
                echo json_encode(array('name' => $name, 'url' => $redis->hGet('webradios', $name)));
            }
            break;
        case 'deleteradio':
            if ($activePlayer === 'MPD') {
                // input array= $_POST['radio']['label']
                wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'webradio', 'action' => 'delete', 'args' => $_POST['radio']));
            }
            break;
        case 'test':
            $proxy = $redis->hGetall('proxy');
            print_r($proxy);
            break;
        case 'albumadd':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['path'])) {
                    addAlbumToQueue($mpd, $_POST['path']);
                    // send MPD response to UI
                    ui_notify($redis, 'Added to queue', $_POST['path']);
                    //ui_mpd_response($mpd, array('title' => 'Added to queue', 'text' => $_POST['path']));
                }
            }
            break;
        case 'albumaddplay':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['path'])) {
                    $status = _parseStatusResponse($redis, MpdStatus($mpd));
                    $pos = $status['playlistlength'] ;
                    addAlbumToQueue($mpd, $_POST['path'], 1, $pos);
                    // send MPD response to UI
                    ui_notify($redis, 'Added to queue', $_POST['path']);
                    //ui_mpd_response($mpd, array('title' => 'Added to queue', 'text' => $_POST['path']));
                }
                unset($status, $pos);
            }
            break;
        case 'albumaddreplaceplay':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['path'])) {
                    addAlbumToQueue($mpd, $_POST['path'], 1, 0, 1); // last argument is for the "clear" command
                    // send MPD response to UI
                    ui_notify($redis, 'Queue cleared<br> Added to queue', $_POST['path']);
                    //ui_mpd_response($mpd, array('title' => 'Queue cleared<br> Added to queue', 'text' => $_POST['path']));
                }
            }
            break;
        case 'artistadd':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['path'])) {
                    addArtistToQueue($mpd, $_POST['path']);
                    // send MPD response to UI
                    ui_notify($redis, 'Added to queue', $_POST['path']);
                    //ui_mpd_response($mpd, array('title' => 'Added to queue', 'text' => $_POST['path']));
                }
            }
            break;
        case 'artistaddplay':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['path'])) {
                    $status = _parseStatusResponse($redis, MpdStatus($mpd));
                    $pos = $status['playlistlength'] ;
                    addArtistToQueue($mpd, $_POST['path'], 1, $pos);
                    // send MPD response to UI
                    ui_notify($redis, 'Added to queue', $_POST['path']);
                    //ui_mpd_response($mpd, array('title' => 'Added to queue', 'text' => $_POST['path']));
                }
                unset($status, $pos);
            }
            break;
        case 'artistaddreplaceplay':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['path'])) {
                    addArtistToQueue($mpd, $_POST['path'], 1, 0, 1); // last argument is for the "clear" command
                    // send MPD response to UI
                    ui_notify($redis, 'Queue cleared<br> Added to queue', $_POST['path']);
                    //ui_mpd_response($mpd, array('title' => 'Queue cleared<br> Added to queue', 'text' => $_POST['path']));
                }
            }
            break;
        case 'genreadd':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['path'])) {
                    addGenreToQueue($mpd, $_POST['path']);
                    // send MPD response to UI
                    ui_notify($redis, 'Added to queue', $_POST['path']);
                    //ui_mpd_response($mpd, array('title' => 'Added to queue', 'text' => $_POST['path']));
                }
            }
            break;
        case 'genreaddplay':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['path'])) {
                    $status = _parseStatusResponse($redis, MpdStatus($mpd));
                    $pos = $status['playlistlength'] ;
                    addGenreToQueue($mpd, $_POST['path'], 1, $pos);
                    // send MPD response to UI
                    ui_notify($redis, 'Added to queue', $_POST['path']);
                    //ui_mpd_response($mpd, array('title' => 'Added to queue', 'text' => $_POST['path']));
                }
                unset($status, $pos);
            }
            break;
        case 'genreaddreplaceplay':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['path'])) {
                    addGenreToQueue($mpd, $_POST['path'], 1, 0, 1); // last argument is for the "clear" command
                    // send MPD response to UI
                    ui_notify($redis, 'Queue cleared<br> Added to queue', $_POST['path']);
                    //ui_mpd_response($mpd, array('title' => 'Queue cleared<br> Added to queue', 'text' => $_POST['path']));
                }
            }
            break;
        case 'composeradd':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['path'])) {
                    addComposerToQueue($mpd, $_POST['path']);
                    // send MPD response to UI
                    ui_notify($redis, 'Added to queue', $_POST['path']);
                    //ui_mpd_response($mpd, array('title' => 'Added to queue', 'text' => $_POST['path']));
                }
            }
            break;
        case 'composeraddplay':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['path'])) {
                    $status = _parseStatusResponse($redis, MpdStatus($mpd));
                    $pos = $status['playlistlength'] ;
                    addComposerToQueue($mpd, $_POST['path'], 1, $pos);
                    // send MPD response to UI
                    ui_notify($redis, 'Added to queue', $_POST['path']);
                    //ui_mpd_response($mpd, array('title' => 'Added to queue', 'text' => $_POST['path']));
                }
                unset($status, $pos);
            }
            break;
        case 'composeraddreplaceplay':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['path'])) {
                    addComposerToQueue($mpd, $_POST['path'], 1, 0, 1); // last argument is for the "clear" command
                    // send MPD response to UI
                    ui_notify($redis, 'Queue cleared<br> Added to queue', $_POST['path']);
                    //ui_mpd_response($mpd, array('title' => 'Queue cleared<br> Added to queue', 'text' => $_POST['path']));
                }
            }
            break;
        case 'pl-crop':
            if ($activePlayer === 'MPD') {
                sysCmd('mpc crop');
            }
            break;
        case 'pl-clear-played':
            if ($activePlayer === 'MPD') {
                $currSongInfo = getMpdCurrentsongInfo($mpd);
                if ($currSongInfo && isset($currSongInfo['Pos'])) {
                    // $currSongInfo[Pos] contains currently playing song position in the queue, 0 is the first position
                    // MPD delete format is: delete <from position>:<number to delete>
                    // 'delete 0:0' deletes nothing
                    // 'delete 0:1' deletes the first entry in the queue
                    // 'delete 0:5' deletes the first 5 entries in the queue
                    // 'delete 2:5' deletes the third to 8th entries in the queue
                    sendMpdCommand($mpd, 'delete 0:'.$currSongInfo['Pos']);
                    readMpdResponse($mpd);
                }
                unset($currSongInfo);
            }
            break;
        case 'pl-save':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['playlist'])) {
                    $playlist = trim($_POST['playlist']);
                    if (strlen($playlist)) {
                        // check existence of playlist with the given name
                        // get the playlist directory
                        $playlistDirectory = rtrim(trim($redis->hget('mpdconf', 'playlist_directory')),'/');
                        // delete all broken symbolic links in the playlist directory
                        sysCmd('find '."'".$playlistDirectory."'".' -xtype l -delete');
                        $playlistFileName = $playlistDirectory.'/'.$playlist.'.m3u';
                        clearstatcache(true, $playlistFileName);
                        if (file_exists($playlistFileName)) {
                            // Note: file_exists() will not detect a broken symlink
                            // file exists
                            ui_notifyError($redis, 'Error', 'Playlist name already in use: '.$playlist);
                        } else {
                            sendMpdCommand($mpd, 'save "'.$playlist.'"');
                            $response = readMpdResponse($mpd);
                            if (strpos(' '.$response, 'OK')) {
                                ui_notify($redis, 'Saved', $playlist);
                            } else {
                                ui_notifyError($redis, 'Error', $response);
                            }
                        }
                    } else {
                        ui_notifyError($redis, 'Error', 'No playlist name given');
                    }
                } else {
                    ui_notifyError($redis, 'Error', 'No playlist name given');
                }
            }
            break;
        case 'pl-rename':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['oldname']) && isset($_POST['newname'])) {
                    $oldname = trim($_POST['oldname']);
                    $newname = trim($_POST['newname']);
                    if (strlen($oldname) && strlen($newname)) {
                        // check existence of new playlist with the given name
                        // get the playlist directory
                        $playlistDirectory = rtrim(trim($redis->hget('mpdconf', 'playlist_directory')),'/');
                        // delete all broken symbolic links in the playlist directory
                        sysCmd('find '."'".$playlistDirectory."'".' -xtype l -delete');
                        $newPlaylistFileName = $playlistDirectory.'/'.$playlist.'.m3u';
                        clearstatcache(true, $newPlaylistFileName);
                        if ($oldname === $redis->hGet('globalrandom', 'playlist')) {
                            ui_notifyError($redis, 'Error', 'This playlist is currently used for Random Play and cannot be renamed: '.$oldname);
                        } else if (file_exists($newPlaylistFileName)) {
                            // Note: file_exists() will not detect a broken symlink
                            // file exists
                            ui_notifyError($redis, 'Error', 'New playlist name already in use: '.$newname);
                        } else {
                            sendMpdCommand($mpd, 'rename "'.$oldname.'" "'.$newname.'"');
                            $response = readMpdResponse($mpd);
                            if (strpos(' '.$response, 'OK')) {
                                ui_notify($redis, 'Renamed', 'From: '.$oldname.', to: '.$newname);
                            } else {
                                ui_notifyError($redis, 'Error', $response);
                            }
                        }
                    } else {
                        ui_notifyError($redis, 'Error', 'No new playlist name given');
                    }
                } else {
                    ui_notifyError($redis, 'Error', 'No new playlist name given');
                }
            }
            break;
        case 'pl-rem-dup':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['playlist'])) {
                    wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'pl_rem_dup', 'args' => $_POST['playlist']));
                }
            }
            break;
        case 'pl-rem-invalid':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['playlist'])) {
                    wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'pl_rem_invalid', 'args' => $_POST['playlist']));
                }
            }
            break;
        case 'pl-ashuffle-start':
            if ($activePlayer === 'MPD') {
                $redis->hSet('globalrandom', 'enable', 1);
                ui_notify($redis, 'Global Random', 'Started');
            }
            break;
        case 'pl-ashuffle-stop':
            if ($activePlayer === 'MPD') {
                $redis->hSet('globalrandom', 'enable', 0);
                ui_notify($redis, 'Global Random', 'Stopped');
            }
            break;
        case 'pl-ashuffle-reset':
            if ($activePlayer === 'MPD') {
                $jobID = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'ashufflereset', 'args' => $playlist));
                ui_notify($redis, 'Global Random', 'Adding songs from your full collection');
                $redis->hSet('globalrandom', 'enable', 1);
                waitSyWrk($redis, $jobID);
                unset($jobID);
            }
            break;
        case 'pl-ashuffle':
            if ($activePlayer === 'MPD') {
                if (isset($_POST['playlist'])) {
                    $playlist = trim($_POST['playlist']);
                    $jobID = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'pl_ashuffle', 'args' => $playlist));
                    ui_notify($redis, 'Global Random', 'Adding songs from playlist: '.$playlist);
                    waitSyWrk($redis, $jobID);
                    ui_notify($redis, 'Global Random', 'To add songs from your full collection, reset Random Play in the MPD menu or in the playlist UI');
                }
                unset($jobID);
            }
            break;
        case 'eject':
            ui_notify($redis, 'CD Input', 'Eject requested');
            wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'cdinput', 'action' => 'eject'));
            break;
        case 'sambadetails':
            // // ui_notify($redis, 'SAMBA', 'Details requested');
            // $args = array();
            // if (isset($_POST['clientUUID']) && $_POST['clientUUID']) {
                // $args['clientUUID'] = $_POST['clientUUID'];
            // }
            // wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'sambadetails', 'args' => $args));
            // unset($args)
            $data = array();
            $data = getHtmlSambaInfo($redis);
            $data['id'] = 'modal-display-text';
            if (isset($_POST['clientUUID']) && $_POST['clientUUID']) {
                $data['clientUUID'] = $_POST['clientUUID'];
            }
            ui_render($redis, 'modal', json_encode($data));
            unset($data);
            break;
        case 'MRconnect':
            // Multi-room connect change
            // params: command, id, name, selected, pin
            // command = 'Connect'
            // returns: id, selected, volume, mute
            // no break;
        case 'MRvolume':
            // Multi-room volume change
            // params: command, id, name, volume, pin
            // command = 'Volume'
            // returns: id, selected, volume, mute
            // no break;
        case 'MRmute':
            // Multi-room mute change
            // params: command, id, name, mute, volume, pin
            // command = 'Mute' or 'Unmute'
            // returns: id, selected, volume, mute
            $params = json_decode($_GET['params'], true);
            if (isset($params['selected'])) {
                // in php we use true and false as boolians, make sure that the variable type for $params['selected'] is a boolean
                if ($params['selected']) {
                    $params['selected'] = true;
                } else {
                    $params['selected'] = false;
                }
            }
            $defaultVolume = $redis->hGet('owntone', 'default_volume');
            $server = $redis->hGet('owntone', 'server');
            if (!$redis->hExists('owntone_presets', $params['name'])) {
                // the presets entry is missing, create it
                $preset = array();
                $preset['mute'] = 0;
                $preset['autoconnect'] = false;
                $preset['volume_preset'] = $defaultVolume;
                $preset['offset_ms'] = 0;
                $preset['pin_connect'] = false;
                $redis->hSet('owntone_presets', $params['name'], json_encode($preset));
            } else {
                $preset = json_decode($redis->hGet('owntone_presets', $params['name']), true);
                // the next lines can be removed after the next release
                if (!isset($preset['offset_ms'])) {
                    // offset is not set, add a null offset value
                    $preset['offset_ms'] = 0;
                    $redis->hSet('owntone_presets', $params['name'], json_encode($preset));
                }
                // the next lines can be removed after the next release
                if (!isset($preset['pin_connect'])) {
                    // pin_connect is not set, add a false pin_connect value
                    $preset['pin_connect'] = false;
                    $redis->hSet('owntone_presets', $params['name'], json_encode($preset));
                }
            }
            // first set the redis outputs and presets to the expected new values and save them
            if (isset($params['offset_ms']) && is_numeric($params['offset_ms'])) {
                // round the offset to the nearest 50, max 2000, min -2000
                $params['offset_ms'] = min(max(50 * round($params['offset_ms'] / 50), -2000), 2000);
                if ($preset['offset_ms'] != $params['offset_ms']) {
                    // new offset differs from last saved version, save it
                    $preset['offset_ms'] = $params['offset_ms'];
                    $redis->hSet('owntone_presets', $params['name'], json_encode($preset));
                }
            } else if ($preset['offset_ms']) {
                $params['offset_ms'] = $preset['offset_ms'];
            }
            if (isset($params['pin']) && $params['pin']) {
                // pin is set, so this output needs a pin to connect, set pin_connect to true
                $preset['pin_connect'] = true;
                $redis->hSet('owntone_presets', $params['name'], json_encode($preset));
            }
            if ((isset($params['command']) && ($params['command'] == 'Mute') && $preset['mute']) ||
                   (isset($params['command']) && ($params['command'] == 'Unmute') && !$preset['mute'])) {
                // mute requested, already muted or unmute requested, already unmuted
                //  dont need to do anything, just return the correct values
                $params['mute'] = $preset['mute'];
                // get the current values for the volume and selected for the return values
                $output = json_decode($redis->hGet('owntone_outputs', $params['name']), true);
                $params['volume'] = $output['volume'];
                $params['selected'] = $output['selected'];
            } else {
                // redis outputs and/or presets need to be changed
                if (isset($params['mute'])) {
                    if (!$params['mute']) {
                        // unmute requested, was muted, use the saved mute value as the new volume
                        $params['volume'] = $preset['mute'];
                        if ($preset['mute'] != $params['mute']) {
                            $preset['mute'] = $params['mute'];
                            $redis->hSet('owntone_presets', $params['name'], json_encode($preset));
                        }
                    } else {
                        // mute requested, was not muted, set the volume to zero and use the current volume as muted value
                        $params['volume'] = 0;
                        $output = json_decode($redis->hGet('owntone_outputs', $params['name']), true);
                        $params['mute'] = $output['volume'];
                        if ($preset['mute'] != $output['volume']) {
                            $preset['mute'] = $output['volume'];
                            $redis->hSet('owntone_presets', $params['name'], json_encode($preset));
                        }
                    }
                } else {
                    // parameter mute is not set so its not a mute action
                    // its a volume change or connect/disconnect action
                    //  in all cases set mute to 0
                    $params['mute'] = 0;
                    if ($preset['mute'] != 0) {
                        $preset['mute'] = 0;
                        $redis->hSet('owntone_presets', $params['name'], json_encode($preset));
                    }
                }
                $output = json_decode($redis->hGet('owntone_outputs', $params['name']), true);
                $offsetRestart = false;
                if (isset($params['offset_ms']) && is_numeric($params['offset_ms'])) {
                    if ($output['offset_ms'] != $params['offset_ms']) {
                        // offset change
                        if ($output['selected']) {
                            // a restart is required to activate the offset 
                            if ($preset['pin_connect']) {
                                // don’t try to automatically restart when a pin code was used for the connect
                                ui_notify($redis, 'Multi-room', 'This output required a pin code to connect. Disconnect and re-connect manually to activate the offset');
                            } else if (!isset($params['pin']) || !$params['pin']) {
                                // it's not a transaction containing a pin-code, it can be restarted automatically
                                $offsetRestart = true;
                            }
                        }
                        $output['offset_ms'] = $params['offset_ms'];
                        $redis->hSet('owntone_outputs', $params['name'], json_encode($output));
                    }
                }
                if (isset($params['volume'])) {
                    if ($output['volume'] != $params['volume']) {
                        // volume change
                        $output['volume'] = $params['volume'];
                    }
                    $localOutputName = $redis->hGet('owntone', 'local_output_name');
                    if ($localOutputName && ($localOutputName == $output['name'])) {
                        // this is the local output, mpd volume needs correcting
                        $automuteTimeEnd = floatval($redis->hGet('owntone', 'automute')) + intval($redis->hGet('owntone', 'unmute_delay'));
                        $now = microtime(true);
                        if ($automuteTimeEnd && ($automuteTimeEnd > $now)) {
                            // automute is active, change the mpd volume synchronously with a delay until after the automute expires,
                            //  no need to check its current mpd volume level
                            // first change the mute volume level, this will be ignored if it has already been applied
                            $preset = json_decode($redis->hGet('owntone_presets', $output['name']), true);
                            $preset['mute'] = $params['volume'];
                            $redis->hSet('owntone_presets', $output['name'], json_encode($preset));
                            // now change the mpd volume
                            sysCmdAsync($redis, 'mpc volume '.$params['volume'], ceil($now - $automuteTimeEnd + 1));
                        } else {
                            // change the mpd volume, no need to check its current  mpd volume level
                            sysCmd('mpc volume '.$params['volume']);
                        }
                    }
                    $redis->hSet('owntone_outputs', $params['name'], json_encode($output));
                } else if (isset($params['selected']) && $output['selected'] != $params['selected']) {
                    // connect/disconnect
                    $output['selected'] = $params['selected'];
                    if ($params['selected']) {
                        // connect, set the connect volume
                        $output['volume'] = $preset['volume_preset'];
                    }
                    $redis->hSet('owntone_outputs', $params['name'], json_encode($output));
                }
                // now update the output volume and/or selected
                // there can only be one connected bluetooth output, when multidevice is set it is possible to attempt to
                //  connect two Bluetooth devices from the UI
                //  to prevent problems, all other connected bluetooth devices are disconnected when a multidevice bluetooth
                //      connect is requested, there will be a maximum of one device disconnected, mostly none
                if (isset($params['selected']) && $params['selected'] && $redis->hGet('owntone', 'multidevice') && (substr($params['name'], 0, 11) == 'Bluetooth: ')) {
                    // disconnect all other bluetooth outputs
                    // get the redis outputs again
                    $outputNames = $redis->hKeys('owntone_outputs');
                    foreach ($outputNames as $outputName) {
                        if (substr($outputName, 0, 11) != 'Bluetooth: ') {
                            // not bluetooth
                            continue;
                        }
                        // bluetooth, get the details
                        $output = json_decode($redis->hGet('owntone_outputs', $outputName), true);
                        if (!$output['selected']) {
                            // not connected
                            continue;
                        }
                        if ($output['name'] == $params['name']) {
                            // this is the current device, it is processed below
                            continue;
                        }
                        // this one needs to be disconnected
                        // unmute in the presets if required
                        $preset = json_decode($redis->hGet('owntone_presets', $outputName), true);
                        if (isset($preset['mute']) && ($preset['mute'] != 0)) {
                            $preset['mute'] = 0;
                            $redis->hSet('owntone_presets', $outputName, json_encode($preset));
                        }
                        // determine the volume
                        $volume = $defaultVolume;
                        if (isset($preset['autoconnect']) && isset($preset['volume_preset'])) {
                            if (!$preset['autoconnect'] && ($preset['volume_preset'] != $defaultVolume)) {
                                $preset['volume_preset'] = $defaultVolume;
                                $redis->hSet('owntone_presets', $outputName, json_encode($preset));
                            }
                            $volume = $preset['volume_preset'];
                        }
                        // set up the disconnect command, run it and get the modified data
                        $commandPut =
                            'curl -X PUT -s --connect-timeout 2 -m 5 --retry 2 "http://'.$server.':3689/api/outputs/'.$output['id'].'" --data "{\"selected\": false, \"volume\": '.$volume.'}"';
                        sysCmd($commandPut);
                        // get the changed values
                        $commandGet = 'curl -X GET -s --connect-timeout 2 -m 5 --retry 2 "http://'.$server.':3689/api/outputs/'.$output['id'].'"';
                        $retval = sysCmd($commandGet);
                        if (isset($retval[0])) {
                            // an array returned
                            $retval = json_decode($retval[0], true);
                            if (!isset($retval['id']) || ($output['id'] != $retval['id'])) {
                                // an invalid result has been returned, try again
                                $retval = sysCmd($commandGet);
                                if (isset($retval[0])) {
                                    // an array returned
                                    $retval = json_decode($retval[0], true);
                                } else {
                                    // no array returned
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
                                // no array returned
                                $retval = array();
                            }
                        }
                        // check that the command has returned valid data
                        if (isset($retval['id']) && ($output['id'] == $retval['id'])) {
                            if (isset($retval['volume']) && is_numeric($retval['volume']) && ($outputs[$output['name']] != $retval)) {
                                $redis->hSet('owntone_outputs', $outputName, json_encode($retval));
                            }
                        }
                    }
                }
                // normal processing to connect/disconnect or change the volume
                $commandPut = '';
                // when the offset is set use it in the command
                if (isset($params['offset_ms']) && is_numeric($params['offset_ms'])) {
                    // its a offset transaction
                    $offsetCommandPart = ', \"offset_ms\": '.$params['offset_ms'];
                    if ($offsetRestart && !isset($params['selected'])) {
                        // set selected to true to force a full connect string with a selected clause
                        $params['selected'] = true;
                    }
                } else {
                    $offsetCommandPart = '';
                }
                if (isset($params['pin']) && $params['pin']) {
                    // its a pin transaction, no other parameters are valid
                    $commandPut =
                        'curl -X PUT -s --connect-timeout 2 -m 5 --retry 2 "http://'.$server.':3689/api/outputs/'.$params['id'].'" --data "{';
                    $commandPut .= ' \"pin\": \"'.$params['pin'].'\"}"';
                } else if (isset($params['selected']) || isset($params['volume'])) {
                    // set up the command
                    $commandPut =
                        'curl -X PUT -s --connect-timeout 2 -m 5 --retry 2 "http://'.$server.':3689/api/outputs/'.$params['id'].'" --data "{';
                    if (isset($params['selected']) && isset($params['volume'])) {
                        if ($params['selected']) {
                            $action = 'true';
                        } else {
                            $action = 'false';
                        }
                        $commandPut .= '\"selected\": '.$action.$offsetCommandPart.', \"volume\": '.$params['volume'].'}"';
                    } else if (isset($params['volume'])) {
                        // only the volume is changed, no select clause
                        $commandPut .= '\"volume\": '.$params['volume'].$offsetCommandPart.'}"';
                    } else if (isset($params['selected'])) {
                        if ($params['selected']) {
                            $action = 'true';
                        } else {
                            $action = 'false';
                        }
                        // get the preset or default volume
                        $params['volume'] = $defaultVolume;
                        if (isset($preset['autoconnect']) && isset($preset['volume_preset'])) {
                            if (!$preset['autoconnect'] && ($preset['volume_preset'] != $defaultVolume)) {
                                $preset['volume_preset'] = $defaultVolume;
                                $redis->hSet('owntone_presets', $outputName, json_encode($preset));
                            }
                            $params['volume'] = $preset['volume_preset'];
                        }
                        // connect/disconnect will always set the volume
                        $commandPut .= '\"selected\": '.$action.$offsetCommandPart.', \"volume\": '.$params['volume'].'}"';
                    }
                    // debug
                    ui_notify($redis, 'Debug', $commandPut);
                    // when an offset ms change is requested and the output is active first run the command with 'selected false' to switch the output off
                    //  when the real command is run the offset ms will be activated when the output is activated
                    if ($commandPut) {
                        if ($offsetRestart) {
                            // runeaudio airplay outputs are disconnect and reconnected after a delay of 10 seconds when their version is less than 0.7
                            // all other outputs are disconnected and reconnected without a delay
                            // disconnect the output with volume and offset
                            sysCmd(str_replace('\"selected\": true', '\"selected\": false', $commandPut));
                            if (strpos(' '.strtolower($output['type']), 'airplay') ) {
                                // airplay
                                // determine if it will be reconnected after a delay
                                $airplayNode = $redis->hGet('owntone_nodes', $output['name']);
                                if (!isset($airplayNode['runeaudio'])) {
                                    // unknown if it is a runeaudio node, delay
                                    sleep(10);
                                } else if ($airplayNode['runeaudio']) {
                                    // it is a runeaudio node
                                    if (!isset($airplayNode['runeaudio_version']) || !$airplayNode['runeaudio_version']) {
                                        // runeaudio version unknown, sleep
                                        sleep(10);
                                    } else if ($airplayNode['runeaudio_version'] < '0.7') {
                                        // runeaudio version known and less than 0.7
                                        sleep(10);
                                    }
                                }
                            }
                        }
                        // run the unmodified command
                        sysCmd($commandPut);
                    }
                }
                // get the current settings of the output, update the redis outputs and set the return values
                $commandGet = 'curl -X GET -s --connect-timeout 2 -m 5 --retry 2 "http://'.$server.':3689/api/outputs/'.$params['id'].'"';
                $retval = sysCmd($commandGet);
                if (isset($retval[0])) {
                    // an array returned
                    $output = json_decode($retval[0], true);
                    if (!isset($output['id']) || ($output['id'] != $params['id'])) {
                        // an invalid result has been returned, try again
                        $retval = sysCmd($commandGet);
                        if (isset($retval[0])) {
                            // an array returned
                            $output = json_decode($retval[0], true);
                        } else {
                            // no array returned
                            $output = array();
                        }
                    }
                } else {
                    // no array returned
                    $retval = sysCmd($commandGet);
                    if (isset($retval[0])) {
                        // an array returned
                        $output = json_decode($retval[0], true);
                    } else {
                        // no array returned
                        $output = array();
                    }
                }
                if (isset($output['id']) && ($output['id'] == $params['id']) && !$output['requires_auth']) {
                    // a valid result has been returned and it is not a request for a pin-code
                    if (isset($output['volume']) && $commandPut && ($output['volume'] != $params['volume'])) {
                        // volume is incorrectly set, run the PUT command again and attempt to retrieve the results
                        sysCmd($commandPut);
                        $retval = sysCmd($commandGet);
                        if (isset($retval[0])) {
                            // an array returned
                            $output = json_decode($retval[0], true);
                            if (!isset($output['id']) && ($output['id'] != $params['id'])) {
                                // an invalid result has been returned, try again
                                $retval = sysCmd($commandGet);
                                if (isset($retval[0])) {
                                    // an array returned
                                    $output = json_decode($retval[0], true);
                                } else {
                                    // no array returned
                                    $output = array();
                                }
                            }
                        } else {
                            // no array returned, try again
                            $retval = sysCmd($commandGet);
                            if (isset($retval[0])) {
                                // an array returned
                                $output = json_decode($retval[0], true);
                            } else {
                                // no array returned
                                $output = array();
                            }
                        }
                    }
                }
                if (isset($output['id']) && ($output['id'] == $params['id'])) {
                    // a valid result has been returned
                    $redis->hSet('owntone_outputs', $params['name'], json_encode($output));
                } else {
                    // invalid information returned, delete the output
                    $redis->hDel('owntone_outputs', $params['name']);
                }
                if (isset($output['id'])) {
                    // valid output
                    // if it was a successful disconnect set pin connect to false
                    if (isset($params['selected']) && !$params['selected'] && !$output['selected']) {
                        $preset = json_decode($redis->hGet('owntone_presets', $params['name']), true);
                        if (!isset($preset['pin_connect']) || $preset['pin_connect']) {
                            // pin_connect is not set or is true, set to false
                            $preset['pin_connect'] = false;
                            $redis->hSet('owntone_presets', $params['name'], json_encode($preset));
                        }
                    }
                    // correct the return values if required
                    if (!isset($params['selected']) || ($output['selected'] != $params['selected'])) {
                        $params['selected'] = $output['selected'];
                    }
                    if (!isset($params['volume']) || ($output['volume'] != $params['volume'])) {
                        $params['volume'] = $output['volume'];
                    }
                    $params['requires_auth'] = $output['requires_auth'];
                } else {
                    // output has been deleted
                    $params['selected'] = false;
                    $params['volume'] = 0;
                    $params['mute'] = 0;
                    $params['requires_auth'] = 0;
                    if (isset($preset['mute']) && $preset['mute']) {
                        // correct the muted info in redis presets
                        $preset['mute'] = 0;
                        $redis->hSet('owntone', 'output_presets', $params['name'], json_encode($preset));
                    }
                }
            }
            // in javascript we use the 0 and 1 integers as boolians, so make sure $params['selected'] is correctly set
            if ($params['selected']) {
                $params['selected'] = 1;
            } else {
                $params['selected'] = 0;
            }
            if ($preset['pin_connect']) {
                $pinConnect = 1;
            } else {
                $pinConnect = 0;
            }
            echo json_encode(array(
                'id' => $params['id'],
                'selected' => $params['selected'],
                'volume' => $params['volume'],
                'mute' => $params['mute'],
                'requires_auth' => $params['requires_auth'],
                'pin_connect' => $pinConnect,
                'offset_ms' => $params['offset_ms']));
            unset($params, $defaultVolume, $preset, $output, $action, $volume, $localOutputName, $pinConnect);
            break;
        case 'MRpreset':
            // Multi-room preset change
            // params: id, name, autoconnect, volume_preset
            // returns: id, autoconnect, volume_preset, selected, volume, mute
            $params = json_decode($_GET['params'], true);
            // get the default volume
            $defaultVolume = $redis->hGet('owntone', 'default_volume');
            // Bluetooth can only have one autoconnect device
            if ($params['autoconnect'] && (substr($params['name'], 0, 11) == 'Bluetooth: ')) {
                // switching autoconnect on for a bluetooth output device
                //  remove autoconnect for all other bluetooth output devices
                $presetNames = $redis->hGet('owntone_presets');
                foreach ($presetNames as $presetName) {
                    if ($presetName == $params['name']) {
                        // this one is processed below
                        continue;
                    }
                    if (substr($presetName, 0, 11) == 'Bluetooth: ') {
                        // check the autoconnect status
                        $preset = json_decode($redis->hGet('owntone_presets', $presetName), true);
                        if ($preset['autoconnect']) {
                            // autoconnect is on, turn it off and set the preset volume to default
                            $preset['autoconnect'] = false;
                            $preset['volume_preset'] = $defaultVolume;
                            // save the preset
                            $redis->hSet('owntone_presets', $presetName, json_encode($preset));
                        }
                    }
                }
            }
            $preset = json_decode($redis->hGet('owntone_presets', $params['name']), true);
            $preset['autoconnect'] = $params['autoconnect'];
            if (!$params['autoconnect']) {
                // autoconnect switched off, set the preset volume to the default volume
                $params['volume_preset'] = $defaultVolume;
            }
            $preset['volume_preset'] = $params['volume_preset'];
            if (!isset($preset['mute'])) {
                $preset['mute'] = 0;
            }
            // get and return redis current selected and volume values for the return values
            $output = json_decode($redis->hGet('owntone_outputs', $params['name']), true);
            if (isset($output['selected'])) {
                $selected = $output['selected'];
            } else {
                $selected = 0;
            }
            if (isset($output['volume'])) {
                $volume = $output['volume'];
                if ($output['volume']) {
                    $preset['mute'] = 0;
                }
            } else {
                $volume = 0;
            }
            $redis->hSet('owntone_presets', $params['name'], json_encode($preset));
            echo json_encode(array(
                'id' => $params['id'],
                'autoconnect' => $params['autoconnect'],
                'volume_preset' => $params['volume_preset'],
                'selected' => $selected,
                'volume' => $volume,
                'mute' => $preset['mute']));
            unset($params, $preset, $output, $selected, $volume);
            break;
        case 'MRowntoneactive':
            // Multi-room activate/deactivate
            // params: 1 (activate) or 0, null (deactivate)
            // returns (echo to the UI): 1 (activated) or 0 (deactivated)
            $params = $_GET['params'];
            if (isset($params) && $params) {
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'owntoneactive', 'args' => array('active' => true)));
                // wrk_owntone($redis, 'activate');
            } else {
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'owntoneactive', 'args' => array('active' => false)));
                // wrk_owntone($redis, 'deactivate');
            }
            if (isset($jobID)) {
                waitSyWrk($redis, $jobID);
            }
            sleep(1);
            echo $redis->hGet('owntone', 'active');
            unset($params, $jobID);
            break;
    }
} else {
  echo 'MPD DB INTERFACE<br>';
  echo 'INTERNAL USE ONLY<br>';
  echo 'hosted on runeaudio.local:81';
}
// close player backend connection
if ($activePlayer === 'MPD') {
    // close MPD connection
    closeMpdSocket($mpd);
}
// close Redis connection
$redis->close();
