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
 *  file: app/dev_ctl.php
 *  version: 1.3
 *  coder: Simone De Gregori
 *
 */
 // flag to determine mpd update
$mpdChange = false;
// inspect POST
if (isset($_POST)) {
    // ----- DEV MODE -----
    if (isset($_POST['mode'])) {
        if ((isset($_POST['mode']['dev']['enable'])) && ($_POST['mode']['dev']['enable'])) {
            // create worker job (start udevil)
            if ($redis->get('dev') != 1) {
                $redis->set('dev', 1);
                $redis->get('debug') == 1 || $redis->set('debug', 1);
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'sambarestart'));
            }
        } else {
            // create worker job (stop udevil)
            if ($redis->get('dev') != 0) {
                $redis->set('dev', 0);
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'sambarestart'));
            }
        }
        // ----- DEBUG -----
        if ((isset($_POST['mode']['debug']['enable'])) && ($_POST['mode']['debug']['enable'])) {
            // set debug on
            $redis->get('debug') == 1 || $redis->set('debug', 1);
        } else {
            // set debug off
            $redis->get('debug') == 0 || $redis->set('debug', 0);
        }
        // ----- SoXr MPD -----
        if ((isset($_POST['mode']['soxrmpdonoff']['enable'])) && ($_POST['mode']['soxrmpdonoff']['enable'])) {
            // create worker job (set on and reset/restart MPD/Airplay)
            $redis->get('soxrmpdonoff') == 1 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'soxrmpd', 'action' => 1));
        } else {
            // create worker job (set off and reset/restart MPD/Airplay)
            $redis->get('soxrmpdonoff') == 0 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'soxrmpd', 'action' => 0));
        }
        // ----- SoXr Airplay -----
        if ((isset($_POST['mode']['soxrairplayonoff']['enable'])) && ($_POST['mode']['soxrairplayonoff']['enable'])) {
            // create worker job (set on and reset/restart MPD/Airplay)
            $redis->hget('airplay', 'soxronoff') == 1 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'soxrairplay', 'action' => 1));
        } else {
            // create worker job (set off and reset/restart MPD/Airplay)
            $redis->hget('airplay', 'soxronoff') == 0 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'soxrairplay', 'action' => 0));
        }
        // ----- Airplay Metadata -----
        if ((isset($_POST['mode']['metadataairplayonoff']['enable'])) && ($_POST['mode']['metadataairplayonoff']['enable'])) {
            // create worker job (set on and reset/restart MPD/Airplay)
            $redis->hget('airplay', 'metadataonoff') == 1 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'metadataairplay', 'action' => 1));
        } else {
            // create worker job (set off and reset/restart MPD/Airplay)
            $redis->hget('airplay', 'metadataonoff') == 0 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'metadataairplay', 'action' => 0));
        }
        // ----- Airplay Artwork -----
        if ((isset($_POST['mode']['artworkairplayonoff']['enable'])) && ($_POST['mode']['artworkairplayonoff']['enable'])) {
            // create worker job (set on and reset/restart MPD/Airplay)
            $redis->hget('airplay', 'artworkonoff') == 1 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'artworkairplay', 'action' => 1));
        } else {
            // create worker job (set off and reset/restart MPD/Airplay)
            $redis->hget('airplay', 'artworkonoff') == 0 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'artworkairplay', 'action' => 0));
        }
        // ----- Airplay output format -----
        if ((isset($_POST['mode']['airplayof'])) && ($_POST['mode']['airplayof'] != $redis->hget('airplay', 'alsa_output_format'))) {
            // create worker job (set value and reset/restart MPD/Airplay)
            $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'airplayoutputformat', 'args' => $_POST['mode']['airplayof']));
        }
        // ----- Airplay output rate -----
        if ((isset($_POST['mode']['airplayor'])) && ($_POST['mode']['airplayor'] != $redis->hget('airplay', 'alsa_output_rate'))) {
            // create worker job (set on and reset/restart MPD/Airplay)
            $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'airplayoutputrate', 'args' => $_POST['mode']['airplayor']));
        }
        // ----- UI Player name Menu -----
        if ((isset($_POST['mode']['playernamemenu']['enable'])) && ($_POST['mode']['playernamemenu']['enable'])) {
            // create worker job (set on)
            $redis->get('playernamemenu') == 1 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'playernamemenu', 'action' => 1));
        } else {
            // create worker job (set off)
            $redis->get('playernamemenu') == 0 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'playernamemenu', 'action' => 0));
        }
        // ----- UI Object Order -----
        if ((isset($_POST['mode']['UIorder'])) && ($_POST['mode']['UIorder'])) {
            // value is set
            if ($redis->get('UIorder') != $_POST['mode']['UIorder']) {
                // value has changed, save it
                $redis->set('UIorder', $_POST['mode']['UIorder']);
                if ($redis->hGet('local_browser', 'enable')) {
                    // local browser is enabled, restart it
                    $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'xorgserver', 'action' => 'restart'));
                }
            }
        }
        // ----- Webstreaming encoder and bitrate -----
        if ((isset($_POST['mode']['WSencoder'])) && ($_POST['mode']['WSencoder'])) {
            // value is set
            if ($redis->get('WSencoder') != $_POST['mode']['WSencoder']) {
                // value has changed, save it
                $redis->hSet('websreaming', 'encoder', $_POST['mode']['WSencoder']);
                if ($_POST['mode']['WSencoder'] === 'lame') {
                    $redis->hSet('websreaming', 'samplerate', '44100');
                }
                if ($redis->hGet('mpdconf', 'webstreaming')) {
                    // this redis variable ('mpdconf', 'webstreaming') is a switch, 0 = off, any other value is on
                    // it has a value so replace it
                    $redis->hSet('mpdconf', 'webstreaming', $redis->hGet('websreaming', 'samplerate'));
                }
                // always save the encoder
                $redis->hSet('mpdconf', 'webstreaming_encoder', $redis->hGet('websreaming', 'encoder'));
            }
        }
        if ((isset($_POST['mode']['WSsamplerate'])) && ($_POST['mode']['WSsamplerate'])) {
            // value is set
            if ($redis->get('WSsamplerate') != $_POST['mode']['WSsamplerate']) {
                // value has changed, save it
                if ($redis->hGet('websreaming', 'encoder') === 'lame') {
                    $redis->hSet('websreaming', 'samplerate', '44100');
                } else {
                    $redis->hSet('websreaming', 'samplerate', $_POST['mode']['WSsamplerate']);
                }
                if ($redis->hGet('mpdconf', 'webstreaming')) {
                    // this redis variable ('mpdconf', 'webstreaming') is a switch, 0 = off, any other value is on
                    // it has a value so replace it and the encoder
                    $redis->hSet('mpdconf', 'webstreaming', $redis->hGet('websreaming', 'samplerate'));
                }
                // always save the encoder
                $redis->hSet('mpdconf', 'webstreaming_encoder', $redis->hGet('websreaming', 'encoder'));
            }
        }
        // ----- Automatic Wi-Fi Optimisation-----
        if ((isset($_POST['mode']['optwifionof']['enable'])) && ($_POST['mode']['optwifionof']['enable'])) {
            $redis->get('network_autoOptimiseWifi') || $redis->set('network_autoOptimiseWifi', 1);
        } else {
            !$redis->get('network_autoOptimiseWifi') || $redis->set('network_autoOptimiseWifi', 0);
        }
        // ----- Underclocking -----
        if ((isset($_POST['mode']['underclocking']['enable'])) && ($_POST['mode']['underclocking']['enable'])) {
            // create worker job (set on)
            $redis->get('underclocking') == 1 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'underclocking', 'action' => 1));
        } else {
            // create worker job (set off)
            $redis->get('underclocking') == 0 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'underclocking', 'action' => 0));
        }
        // ----- MPD configuration -----
        if (isset($_POST['mode']['conf'])) {
            if (
            (isset($_POST['mode']['conf']['proxy_node']) && ($_POST['mode']['conf']['proxy_node'] != $redis->hGet('mpdconf', 'proxy_node'))) ||
            (isset($_POST['mode']['conf']['proxy_port']) && ($_POST['mode']['conf']['proxy_port'] != $redis->hGet('mpdconf', 'proxy_port')))
            ) {
                $mpdChange = true;
            }
        }
    }
    // update the MPD configuration if required
    if ($mpdChange) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'mpdcfg', 'action' => 'update', 'args' => $_POST['mode']['conf']));
        $mpdChange = false;
    }
    
    // ----- OPCACHE -----
    if (isset($_POST['opcache'])) {
        if ($_POST['opcache']['enable']) {
            // create worker job (enable php opcache)
            $redis->get('opcache') == 1 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'opcache', 'action' => 'enable'));
        } else {
            // create worker job (disable php opcache)
            $redis->get('opcache') == 0 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'opcache', 'action' => 'disable'));
        }
    }
    if (isset($_POST['syscmd'])) {
        // ----- BLANK PLAYERID -----
        if ($_POST['syscmd'] === 'blankplayerid') {
            $redis->set('playerid','');
            $redis->set('hwplatformid','');
        }
        // ----- CLEARIMG -----
        if ($_POST['syscmd'] === 'clearimg') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'clearimg'));
        // ----- CHECK FS PERMISSIONS -----
        if ($_POST['syscmd'] === 'syschmod') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'sysAcl'));
        // ----- RESTART MPD -----
        if ($_POST['syscmd'] === 'mpdrestart') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'forcempdrestart'));
        // ----- RESET NET CONFIG -----
        if ($_POST['syscmd'] === 'netconfreset') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'netcfg', 'action' => 'reset'));
        // ----- RESET MPD CONFIG -----
        if ($_POST['syscmd'] === 'mpdconfreset') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'mpdcfg', 'action' => 'reset'));
        // ----- RESTART PHP-FPM -----
        if ($_POST['syscmd'] === 'phprestart') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'phprestart'));
        // ----- GIT PULL -----
        if ($_POST['syscmd'] === 'gitpull') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'gitpull'));
        // ----- RESTART WORKERS -----
        if (isset($_POST['syscmd']['wrkrestart'])) $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'wrkrestart', 'args' => $_POST['syscmd']['wrkrestart']));
        // ----- RESTART SAMBA -----
        if ($_POST['syscmd'] === 'sambarestart') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'sambarestart'));
        // ----- INSTALL RERNS ADD-ON MENU -----
        if ($_POST['syscmd'] === 'rerninstall') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'rerninstall'));
        // ----- REMOVE RERNS ADD-ON MENU -----
        if ($_POST['syscmd'] === 'rernremove') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'rernremove'));
        // ----- EXTEND THE LINUX PARTITION -----
        if ($_POST['syscmd'] === 'extendpartition') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'extendpartition'));
        // ----- RESET AIRPLAY CONFIG -----
        if ($_POST['syscmd'] === 'airplayconfreset') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'airplayconfreset'));
        // ----- RESET SAMBA CONFIG -----
        if ($_POST['syscmd'] === 'sambaconfreset') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'sambaconfreset'));
        // ----- REBUILD WEBRADIO DATABASE -----
        if ($_POST['syscmd'] === 'webradiorebuild') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'webradio', 'action' => 'rebuild'));
        // ----- ADD REPLAYGAIN TAGS TO ALL FLAC FILES -----
        if ($_POST['syscmd'] === 'addRGtagsFlac') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'replaygain', 'action' => 'addRGtagsFlac'));
        // ----- UPDATE REPLAYGAIN TAGS TO ALL FLAC FILES -----
        if ($_POST['syscmd'] === 'updateRGtagsFlac') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'replaygain', 'action' => 'updateRGtagsFlac'));
        // ----- REMOVE REPLAYGAIN TAGS TO ALL FLAC FILES -----
        if ($_POST['syscmd'] === 'delRGtagsFlac') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'replaygain', 'action' => 'delRGtagsFlac'));
        // ----- ADD REPLAYGAIN TAGS TO ALL MUSIC FILES -----
        if ($_POST['syscmd'] === 'addRGtagsAll') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'replaygain', 'action' => 'addRGtagsAll'));
        // ----- UPDATE REPLAYGAIN TAGS TO ALL MUSIC FILES -----
        if ($_POST['syscmd'] === 'updateRGtagsAll') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'replaygain', 'action' => 'updateRGtagsAll'));
        // ----- REMOVE REPLAYGAIN TAGS TO ALL MUSIC FILES -----
        if ($_POST['syscmd'] === 'delRGtagsAll') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'replaygain', 'action' => 'delRGtagsAll'));
    }
}
if (isset($jobID)) {
    waitSyWrk($redis, $jobID);
}
$template->dev = $redis->get('dev');
$template->debug = $redis->get('debug');
$template->playerid = $redis->get('playerid');
$template->hwplatformid = $redis->get('hwplatformid');
$template->opcache = $redis->get('opcache');
$template->gitbranch = $redis->hGet('git', 'branch');
$template->sambadevonoff = $redis->hGet('samba', 'devonoff');
$template->sambaprodonoff = $redis->hGet('samba', 'prodonoff');
$template->soxrmpdonoff = $redis->get('soxrmpdonoff');
$template->playernamemenu = $redis->get('playernamemenu');
$template->UIorder = $redis->get('UIorder');
$template->soxrairplayonoff = $redis->hGet('airplay', 'soxronoff');
$template->metadataairplayonoff = $redis->hGet('airplay', 'metadataonoff');
$template->artworkairplayonoff = $redis->hGet('airplay', 'artworkonoff');
$template->hostname = $redis->get('hostname');
$template->airplayof = $redis->hGet('airplay', 'alsa_output_format');
$template->airplayor = $redis->hGet('airplay', 'alsa_output_rate');
$template->optwifionof = $redis->get('network_autoOptimiseWifi');
$template->underclocking = $redis->get('underclocking');
$template->WSencoder = $redis->hGet('websreaming', 'encoder');
$template->WSsamplerate = $redis->hGet('websreaming', 'samplerate');
$template->conf = $redis->hGetAll('mpdconf');

// debug
// var_dump($template->dev);
// var_dump($template->debug);
// var_dump($template->opcache);
