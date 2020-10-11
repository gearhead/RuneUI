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
 *  file: mpd_ctl.php
 *  version: 1.3
 *  coder: Simone De Gregori
 *
 */
 if (isset($_POST)) {
    // switch audio output
    if (isset($_POST['ao'])) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'mpdcfg', 'action' => 'switchao', 'args' => $_POST['ao']));
    }
    // reset MPD configuration
    if (isset($_POST['reset'])) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'mpdcfg', 'action' => 'reset'));
    }
    // update MPD configuration
    if (isset($_POST['conf'])) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'mpdcfg', 'action' => 'update', 'args' => $_POST['conf']));
    }
    // manual MPD configuration
    if (isset($_POST['mpdconf'])) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'mpdcfgman', 'args' => $_POST['mpdconf']));
    }
    // ----- FEATURES -----
    if (isset($_POST['mpdvol'])) {
        if ($_POST['mpdvol']['realtime_volume'] == "yes") {
            $redis->get('dynVolumeKnob') == 1 || $redis->set('dynVolumeKnob', 1);
        } else {
            $redis->get('dynVolumeKnob') == 0 || $redis->set('dynVolumeKnob', 0);
        }
        if (isset($_POST['mpdvol']['start_volume'])) {
            $redis->get('mpd_start_volume') == $_POST['mpdvol']['start_volume'] || $redis->set('mpd_start_volume', $_POST['mpdvol']['start_volume']);
        }
    }
    if (isset($_POST['mpd'])) {
        if (isset($_POST['mpd']['crossfade'])) {
            sysCmd('mpc crossfade '.$_POST['mpd']['crossfade']);
        }
        if ((isset($_POST['mpd']['globalrandom'])) && ($_POST['mpd']['globalrandom'])) {
            if ($redis->get('globalrandom') != 1) {
                $redis->set('globalrandom', 1);
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'ashufflereset'));
            } else {
                // check that crossfade is set up correctly
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'ashufflecheckCF'));
            }
        } else {
            if ($redis->get('globalrandom') != 0) {
                $redis->set('globalrandom', 0);
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'ashufflereset'));
            } else {
                // check that crossfade is set up correctly
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'ashufflecheckCF'));
            }
        }
        if ((isset($_POST['mpd']['random_album'])) && ($_POST['mpd']['random_album'])) {
            if ($redis->get('random_album') != 1) {
                $redis->set('random_album', 1);
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'ashufflereset'));
            } else {
                // check that crossfade is set up correctly
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'ashufflecheckCF'));
            }
        } else {
            if ($redis->get('random_album') != 0) {
                $redis->set('random_album', 0);
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'ashufflereset'));
            } else {
                // check that crossfade is set up correctly
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'ashufflecheckCF'));
            }
        }
        if ((isset($_POST['mpd']['addrandom'])) && (is_numeric($_POST['mpd']['addrandom']))) {
            $redis->get('addrandom') == $_POST['mpd']['addrandom'] || $redis->set('addrandom', $_POST['mpd']['addrandom']);
        }
        if ((isset($_POST['mpd']['mpd_autoplay'])) && ($_POST['mpd']['mpd_autoplay'])) {
            $redis->get('mpd_autoplay') == 1 || $redis->set('mpd_autoplay', 1);
        } else {
            $redis->get('mpd_autoplay') == 0 || $redis->set('mpd_autoplay', 0);
        }
    }
    // ----- RESET GLOBAL RANDOM -----
    if ((isset($_POST['resetrp'])) && ($_POST['resetrp'])) $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'ashufflereset'));
 }
if (isset($jobID)) {
    waitSyWrk($redis, $jobID);
}
// collect system status
$template->hwplatformid = $redis->get('hwplatformid');
$template->realtime_volume = $redis->get('dynVolumeKnob');
$template->mpd['start_volume'] = $redis->get('mpd_start_volume');
$template->mpd['mpd_autoplay'] = $redis->get('mpd_autoplay');
$template->mpd['globalrandom'] = $redis->get('globalrandom');
$template->mpd['random_album'] = $redis->get('random_album');
$template->mpd['addrandom'] = $redis->get('addrandom');
$template->hostname = $redis->get('hostname');
$crossfade = explode(": ", sysCmd('mpc crossfade')[0]);
$template->mpd['crossfade'] = $crossfade[1];
// clear the cache otherwise file_exists() returns incorrect values
clearstatcache();
if (file_exists($redis->hget('mpdconf', 'playlist_directory').'/RandomPlayPlaylist.m3u')) {
    // random source is a playlist
    $playlist = readlink($redis->hget('mpdconf', 'playlist_directory').'/RandomPlayPlaylist.m3u');
    $first_pl = strripos($playlist, '/') + 1;
    $length_pl = stripos($playlist, '.m3u') - $first_pl;
    $playlist = trim(substr($playlist, $first_pl, $length_pl));
    if ($playlist != '') {
        $template->ramdomsource = "Playlist '".$playlist."' is selected as random source";
    } else {
        $template->ramdomsource = 'Full MPD library is selected as random source';
    }
} else {
    $template->ramdomsource = 'Full MPD library is selected as random source';
}
if ($redis->hGet('mpdconf', 'version') >= '0.21.00') $template->mpdv21 = true;
// check integrity of /etc/network/interfaces
if(!hashCFG('check_mpd', $redis)) {
    $template->mpdconf = file_get_contents('/etc/mpd.conf');
    // set manual config template
    $template->content = "mpd_manual";
} else {
    $template->conf = $redis->hGetAll('mpdconf');
    $i2smodule = $redis->get('i2smodule');
    // debug
    // echo $i2smodule."\n";
    $acards = $redis->hGetAll('acards');
    // debug
    // print_r($acards);
    foreach ($acards as $card => $data) {
        $acard_data = json_decode($data);
        // debug
        // echo $card."\n";
        // print_r($acard_data);
        if ($i2smodule !== 'none') {
            $acards_details = $redis->hGet('acards_details', $i2smodule);
        } else {
            $acards_details = $redis->hGet('acards_details', $card);
        }
        if (!empty($acards_details)) {
            $details = json_decode($acards_details);
            // debug
            // echo "acards_details\n";
            // print_r($details);
            if ($details->sysname === $card) {
                if ($details->type === 'integrated_sub') {
                    $sub_interfaces = $redis->sMembers($card);
                    foreach ($sub_interfaces as $int) {
                        $sub_int_details = json_decode($int);
                        // TODO !!! check
                        $audio_cards[] = $sub_int_details;
                    }
                }
                if ($details->extlabel !== 'none') $acard_data->extlabel = $details->extlabel;
            }
        }
        $audio_cards[] = $acard_data;
    }
    osort($audio_cards, 'extlabel');
    // debug
    // print_r($audio_cards);
    $template->acards = $audio_cards;
    $template->ao = $redis->get('ao');
}
