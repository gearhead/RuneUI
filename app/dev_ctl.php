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
// inspect POST
if (isset($_POST)) {
    // ----- DEV MODE -----
    if (isset($_POST['mode'])) {
        if ($_POST['mode']['dev']['enable'] == 1) {
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
        if ($_POST['mode']['debug']['enable'] == 1) {
            // set debug on
            $redis->get('debug') == 1 || $redis->set('debug', 1);
        } else {
            // set debug off
            $redis->get('debug') == 0 || $redis->set('debug', 0);
        }
    // ----- SoXr MPD -----
        if ($_POST['mode']['soxrmpdonoff']['enable'] == 1) {
            // create worker job (set on and reset/restart MPD/Airplay)
            $redis->get('soxrmpdonoff') == 1 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'soxrmpd', 'action' => 1));
        } else {
            // create worker job (set off and reset/restart MPD/Airplay)
            $redis->get('soxrmpdonoff') == 0 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'soxrmpd', 'action' => 0));
        }
    // ----- SoXr Airplay -----
        if ($_POST['mode']['soxrairplayonoff']['enable'] == 1) {
            // create worker job (set on and reset/restart MPD/Airplay)
            $redis->hget('airplay', 'soxronoff') == 1 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'soxrairplay', 'action' => 1));
        } else {
            // create worker job (set off and reset/restart MPD/Airplay)
            $redis->hget('airplay', 'soxronoff') == 0 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'soxrairplay', 'action' => 0));
        }
    // ----- Airplay Metadata -----
        if ($_POST['mode']['metadataairplayonoff']['enable'] == 1) {
            // create worker job (set on and reset/restart MPD/Airplay)
            $redis->hget('airplay', 'metadataonoff') == 1 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'metadataairplay', 'action' => 1));
        } else {
            // create worker job (set off and reset/restart MPD/Airplay)
            $redis->hget('airplay', 'metadataonoff') == 0 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'metadataairplay', 'action' => 0));
        }
    // ----- Airplay Artwork -----
        if ($_POST['mode']['artworkairplayonoff']['enable'] == 1) {
            // create worker job (set on and reset/restart MPD/Airplay)
            $redis->hget('airplay', 'artworkonoff') == 1 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'artworkairplay', 'action' => 1));
        } else {
            // create worker job (set off and reset/restart MPD/Airplay)
            $redis->hget('airplay', 'artworkonoff') == 0 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'artworkairplay', 'action' => 0));
        }
    // ----- Airplay output format -----
        if ($_POST['mode']['airplayof'] != $redis->hget('airplay', 'alsa_output_format')) {
            // create worker job (set value and reset/restart MPD/Airplay)
            $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'airplayoutputformat', 'args' => $_POST['mode']['airplayof']));
        }
		// ----- Airplay output rate -----
        if ($_POST['mode']['airplayor'] != $redis->hget('airplay', 'alsa_output_rate')) {
            // create worker job (set on and reset/restart MPD/Airplay)
            $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'airplayoutputrate', 'args' => $_POST['mode']['airplayor']));
        }
    // ----- Player name Menu-----
        if ($_POST['mode']['playernamemenu']['enable'] == 1) {
            // create worker job (set on)
            $redis->get('playernamemenu') == 1 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'playernamemenu', 'action' => 1));
        } else {
            // create worker job (set off)
            $redis->get('playernamemenu') == 0 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'playernamemenu', 'action' => 0));
        }
    }
    // ----- OPCACHE -----
    if (isset($_POST['opcache'])) {
        if ($_POST['opcache']['enable'] == 1) {
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
        if ($_POST['syscmd'] === 'mpdrestart') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'mpdrestart'));
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
        // ----- RESET / SWITCH ON CHRONYD-TIME -----
        if ($_POST['syscmd'] === 'chronydon') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'NTPswitch', 'action' => 'chronyd'));
        // ----- RESET / SWITCH ON SYSTEMD-TIME -----
        if ($_POST['syscmd'] === 'systemdon') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'NTPswitch', 'action' => 'systemd'));
    }
}
waitSyWrk($redis, $jobID);
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
$template->soxrairplayonoff = $redis->hGet('airplay', 'soxronoff');
$template->metadataairplayonoff = $redis->hGet('airplay', 'metadataonoff');
$template->artworkairplayonoff = $redis->hGet('airplay', 'artworkonoff');
$template->hostname = $redis->get('hostname');
$template->chronydstatus = $redis->hGet('NTPtime', 'chronyd');
$template->systemdstatus = $redis->hGet('NTPtime', 'systemd');
$template->airplayof = $redis->hGet('airplay', 'alsa_output_format');
$template->airplayor = $redis->hGet('airplay', 'alsa_output_rate');
// debug
// var_dump($template->dev);
// var_dump($template->debug);
// var_dump($template->opcache);
