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
 *  file: app/settings_ctl.php
 *  version: 1.3
 *  coder: Simone De Gregori
 *
 */
// check the user agent for Firefox browser, then attempt to set IPv6 off
if (isset($_SERVER['HTTP_USER_AGENT'])) {
    if (strpos($_SERVER['HTTP_USER_AGENT'], 'Opera') || strpos($user_agent, 'OPR/')) $browser = 'Opera';
    else if (strpos($_SERVER['HTTP_USER_AGENT'], 'Edge')) $browser = 'Edge';
    else if (strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome')) $browser = 'Chrome';
    else if (strpos($_SERVER['HTTP_USER_AGENT'], 'Safari')) $browser = 'Safari';
    else if (strpos($_SERVER['HTTP_USER_AGENT'], 'Firefox')) $browser = 'Firefox';
    else if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') || strpos($user_agent, 'Trident/7')) $browser = 'Internet Explorer';
    else $browser = 'Other';
    //
    $redis->hSet('browser', $browser, 1);
    if ($browser == 'Firefox') {
        wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'firefox', 'action' => 'IPv6Off'));
    }
}
//
// inspect POST
if (isset($_POST)) {
    // ----- TIME SETTINGS -----
    if (isset($_POST['ntpserver'])) {
        if (empty($_POST['ntpserver'])) {
            $args = 'pool.ntp.org';
        } else {
            $args = $_POST['ntpserver'];
        }
        $redis->get('ntpserver') == $args || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'ntpserver', 'args' => $args));
    }
    if (isset($_POST['timezone'])) {
        $args = $_POST['timezone'];
        $redis->get('timezone') == $args || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'timezone', 'args' => $args));
    }
    // ----- HOSTNAME -----
    if (isset($_POST['hostname'])) {
        if (empty($_POST['hostname'])) {
            $args = 'RuneAudio';
        } else {
            $args = $_POST['hostname'];
        }
        $redis->get('hostname') == $_POST['hostname'] || $jobID[] = wrk_control($redis, 'newjob', $data = array( 'wrkcmd' => 'hostname', 'args' => $args ));
    }
    if (isset($_POST['orionprofile'])) {
        // submit worker job
        $redis->get('orionprofile') == $_POST['orionprofile'] || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'orionprofile', 'args' => $_POST['orionprofile']));
    }
    if (isset($_POST['i2smodule_select'])) {
        // submit worker job
        if ($redis->get('i2smodule_select') !== $_POST['i2smodule_select']) {
            $redis->set('i2smodule_select', $_POST['i2smodule_select']);
            list($i2smodule, $i2sselectedname) = explode('|', $_POST['i2smodule_select'], 2);
            $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'i2smodule', 'args' => $i2smodule));
        }

        // autoswitch optimized kernel profile for BerryNOS mini DAC
        if (isset($i2smodule)) {
            if ($i2smodule === 'berrynosmini') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'orionprofile', 'args' => 'OrionV3_berrynosmini'));
            // autoswitch optimized kernel profile for IQaudIO Pi-DAC
            if ($i2smodule === 'iqaudiopidac') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'orionprofile', 'args' => 'OrionV3_iqaudio'));
        }

    // audio-on-off
        if (isset($_POST['audio_on_off']) && $_POST['audio_on_off']) {
            if (!$redis->get('audio_on_off')) {
                $redis->set('audio_on_off', 1);
                // submit worker job
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'audio_on_off', 'args' => $_POST['audio_on_off']));
            }
        } else {
            if ($redis->get('audio_on_off')) {
                $redis->set('audio_on_off', 0);
                // submit worker job
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'audio_on_off', 'args' => $_POST['audio_on_off']));
            }
        }
    }
    // ----- KERNEL -----
    if (isset($_POST['kernel'])) {
        // submit worker job
        if ($redis->get('kernel') !== $_POST['kernel']) {
            $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'kernelswitch', 'args' => $_POST['kernel']));
        }
    }
    // ----- FEATURES -----
    if (isset($_POST['features'])) {
        if (isset($_POST['features']['airplay']['enable']) && $_POST['features']['airplay']['enable']) {
            if (isset($_POST['features']['airplay']['name']) && (($redis->hGet('airplay','enable') !== $_POST['features']['airplay']['enable']) || ($redis->hGet('airplay','name') !== $_POST['features']['airplay']['name']))) {
                if (trim($_POST['features']['airplay']['name']) == "") $_POST['features']['airplay']['enable'] = "RuneAudio";
                // create worker job (start shairport-sync)
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'airplay', 'action' => 'start', 'args' => $_POST['features']['airplay']['name']));
            }
        } else {
            // create worker job (stop shairport-sync)
            $redis->hGet('airplay','enable') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'airplay', 'action' => 'stop', 'args' => $_POST['features']['airplay']['name']));
        }
        if (isset($_POST['features']['dlna'])) {
            if ((!isset($_POST['features']['dlna']['enable']) || !$_POST['features']['dlna']['enable'])) $_POST['features']['dlna']['enable'] = '0';
            if ((!isset($_POST['features']['dlna']['queueowner']) || !$_POST['features']['dlna']['queueowner'])) $_POST['features']['dlna']['queueowner'] = '0';
            if (isset($_POST['features']['dlna']['enable']) && $_POST['features']['dlna']['enable'] && !$redis->hGet('dlna','enable')) {
                // create worker job (start upmpdcli)
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'dlna', 'action' => 'start', 'args' => $_POST['features']['dlna']));
            } else if (isset($_POST['features']['dlna']['enable']) && !$_POST['features']['dlna']['enable'] && $redis->hGet('dlna','enable')) {
                // create worker job (stop upmpdcli)
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'dlna', 'action' => 'stop', 'args' => $_POST['features']['dlna']));
            } else if (isset($_POST['features']['dlna']['name']) || isset($_POST['features']['dlna']['queueowner']) || isset($_POST['features']['dlna']['services'])) {
                // create worker job (update upmpdcli)
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'dlna', 'args' => $_POST['features']['dlna']));
            }
        }
        if (isset($_POST['features']['local_browser']['enable']) && $_POST['features']['local_browser']['enable']) {
            $redis->hGet('local_browser', 'enable') || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'localbrowser', 'action' => 'start', 'args' => 1));
            if (isset($_POST['features']['local_browser']['zoomfactor']) && ($_POST['features']['local_browser']['zoomfactor'] != $redis->hGet('local_browser', 'zoomfactor'))) {
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'localbrowser', 'action' => 'zoomfactor', 'args' => $_POST['features']['local_browser']['zoomfactor']));
            }
            if (isset($_POST['features']['local_browser']['rotate']) && ($_POST['features']['local_browser']['rotate'] != $redis->hGet('local_browser', 'rotate'))) {
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'localbrowser', 'action' => 'rotate', 'args' => $_POST['features']['local_browser']['rotate']));
            }
            if (isset($_POST['features']['local_browser']['overscan']) && $_POST['features']['local_browser']['overscan']) {
                $redis->hGet('local_browser', 'overscan') || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'localbrowser', 'action' => 'overscan', 'args' => 1));
            } else {
                $redis->hGet('local_browser', 'overscan') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'localbrowser', 'action' => 'overscan', 'args' => 0));
            }
            if (isset($_POST['features']['local_browser']['mouse_cursor']) && $_POST['features']['local_browser']['mouse_cursor']) {
                $redis->hGet('local_browser', 'mouse_cursor') || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'localbrowser', 'action' => 'mouse_cursor', 'args' => 1));
            } else {
                $redis->hGet('local_browser', 'mouse_cursor') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'localbrowser', 'action' => 'mouse_cursor', 'args' => 0));
            }
            if (isset($_POST['features']['local_browser']['localSStime']) && ($_POST['features']['local_browser']['localSStime'] != $redis->hGet('local_browser', 'localSStime'))) {
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'localbrowser', 'action' => 'localSStime', 'args' => $_POST['features']['local_browser']['localSStime']));
            }
            if (isset($_POST['features']['local_browser']['smallScreenSaver']) && $_POST['features']['local_browser']['smallScreenSaver']) {
                $redis->hGet('local_browser', 'smallScreenSaver') || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'localbrowser', 'action' => 'smallScreenSaver', 'args' => 1));
            } else {
                $redis->hGet('local_browser', 'smallScreenSaver') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'localbrowser', 'action' => 'smallScreenSaver', 'args' => 0));
            }
        } else {
            $redis->hGet('local_browser', 'enable') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'localbrowser', 'action' => 'stop', 'args' => 0));
        }
        if (isset($_POST['features']['local_browser']['enable-splash']) && $_POST['features']['local_browser']['enable-splash']) {
            $redis->hGet('local_browser', 'enable-splash') || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'localbrowser', 'action' => 'enable-splash', 'args' => 1));
        } else {
            $redis->hGet('local_browser', 'enable-splash') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'localbrowser', 'action' => 'enable-splash', 'args' => 0));
        }
        if (isset($_POST['features']['pwd_protection']) && $_POST['features']['pwd_protection']) {
            $redis->get('pwd_protection') || $redis->set('pwd_protection', 1);
        } else {
            $redis->get('pwd_protection') && $redis->set('pwd_protection', 0);
        }
        if (isset($_POST['features']['remoteSStime'])) {
            $redis->set('remoteSStime', $_POST['features']['remoteSStime']);
        }
        if (isset($_POST['features']['udevil']) && $_POST['features']['udevil']) {
            // create worker job (start udevil)
            $redis->get('udevil') || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'udevil', 'action' => 'start'));
        } else {
            // create worker job (stop udevil)
            $redis->get('udevil') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'udevil', 'action' => 'stop'));
        }
        if (isset($_POST['features']['hwinput']) && $_POST['features']['hwinput']) {
            // create worker job (start hwinput)
            $redis->hGet('hw_input', 'enable') || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'hwinput', 'action' => 'start'));
        } else {
            // create worker job (stop hwinput)
            $redis->hGet('hw_input', 'enable') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'hwinput', 'action' => 'stop'));
        }
        // CD input
        if (isset($_POST['features']['cdautoplay']) && $_POST['features']['cdautoplay']) {
            $cdautoplay = $_POST['features']['cdautoplay'];
        } else {
            $cdautoplay = 'None';
        }
        if ($cdautoplay != $redis->hGet('CD', 'autoplay')) {
            if (isset($_POST['features']['cdinput']) && $_POST['features']['cdinput']) {
                // create worker job (start cdinput) with arguments containing UI values
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'cdinput', 'action' => 'start', 'args' => array('autoplay' => $cdautoplay)));
            } else {
                // create worker job (stop cdinput) with arguments containing UI values
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'cdinput', 'action' => 'stop', 'args' => array('autoplay' => $cdautoplay)));
            }
        } else if (isset($_POST['features']['cdinput']) && $_POST['features']['cdinput']) {
            // create worker job (start cdinput) when stopped
            $redis->hGet('CD', 'enable') || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'cdinput', 'action' => 'start'));
        } else {
            // create worker job (stop cdinput) when started
            $redis->hGet('CD', 'enable') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'cdinput', 'action' => 'stop'));
        }
        // CD ripper
        if (isset($_POST['features']['cdstoragedevice']) && $_POST['features']['cdstoragedevice']) {
            $cdstoragedevice = $_POST['features']['cdstoragedevice'];
        } else {
            $cdstoragedevice = 'None';
        }
        if ($cdstoragedevice != $redis->hGet('CDripper', 'cdstoragedevice')) {
            if (isset($_POST['features']['cdripper']) && $_POST['features']['cdripper']) {
                // create worker job (start cdripper) with arguments containing UI values
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'cdripper', 'action' => 'start', 'args' => array('cdstoragedevice' => $cdstoragedevice)));
            } else {
                // create worker job (stop cdripper) with arguments containing UI values
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'cdripper', 'action' => 'stop', 'args' => array('cdstoragedevice' => $cdstoragedevice)));
            }
        } else if (isset($_POST['features']['cdripper']) && $_POST['features']['cdripper']) {
            // create worker job (start cdripper) when stopped
            $redis->hGet('CDripper', 'enable') || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'cdripper', 'action' => 'start'));
        } else {
            // create worker job (stop cdripper) when started
            $redis->hGet('CDripper', 'enable') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'cdripper', 'action' => 'stop'));
        }
        if (isset($_POST['features']['coverart']) && $_POST['features']['coverart']) {
            if ($redis->get('coverart') != 1) {
                $redis->set('coverart', 1);
                $redis->hSet('spotifyconnect', 'metadata_enabled', 1);
                $redis->hSet('airplay', 'metadataonoff', 1);
            }
        } else {
            if ($redis->get('coverart') != 0) {
                $redis->set('coverart', 0);
                $redis->hSet('spotifyconnect', 'metadata_enabled', 0);
                $redis->hSet('airplay', 'metadataonoff', 0);
            }
        }
        if (isset($_POST['features']['lastfm']['enable']) && $_POST['features']['lastfm']['enable']) {
            // create worker job (start lastfm)
            if (!isset($_POST['features']['lastfm']['user']) || (trim($_POST['features']['lastfm']['user']) == "")) $_POST['features']['lastfm']['user'] = "user";
            if (!isset($_POST['features']['lastfm']['pass']) || (trim($_POST['features']['lastfm']['pass']) == "")) $_POST['features']['lastfm']['pass'] = "pass";
            if (($_POST['features']['lastfm']['user'] != $redis->hGet('lastfm', 'user')) || ($_POST['features']['lastfm']['pass'] != $redis->hGet('lastfm', 'pass')) || ($redis->hGet('lastfm', 'enable') != $_POST['features']['lastfm']['enable'])) {
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'lastfm', 'action' => 'start', 'args' => $_POST['features']['lastfm']));
            }
        } else {
            // create worker job (stop lastfm)
            $redis->hGet('lastfm','enable') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'lastfm', 'action' => 'stop'));
        }
        if (isset($_POST['features']['samba']['enable']) && $_POST['features']['samba']['enable']) {
            // create worker job (start samba)
            if ((!isset($_POST['features']['samba']['readwrite'])) || (empty($_POST['features']['samba']['readwrite']))) $_POST['features']['samba']['readwrite'] = '0';
            if (($_POST['features']['samba']['readwrite'] != $redis->hGet('samba', 'readwrite')) || ($redis->hGet('samba', 'enable') != $_POST['features']['samba']['enable'])) {
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'sambaonoff', 'action' => $_POST['features']['samba']['enable'], 'args' => $_POST['features']['samba']['readwrite']));
            }
        } else {
            // create worker job (stop samba)
            $_POST['features']['samba']['enable'] = '0';
            $_POST['features']['samba']['readwrite'] = '0';
            if (($_POST['features']['samba']['readwrite'] != $redis->hGet('samba', 'readwrite')) || ($redis->hGet('samba', 'enable') != $_POST['features']['samba']['enable'])) {
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'sambaonoff', 'action' => $_POST['features']['samba']['enable'], 'args' => $_POST['features']['samba']['readwrite']));
            }
        }
        if (isset($_POST['features']['spotifyconnect']['enable']) && $_POST['features']['spotifyconnect']['enable']) {
            // create worker job (start Spotify Connect)
            // if (!isset($_POST['features']['spotifyconnect']['username']) || (trim($_POST['features']['spotifyconnect']['username']) == "")) $_POST['features']['spotifyconnect']['username'] = "user";
            // if (!isset($_POST['features']['spotifyconnect']['password']) || (trim($_POST['features']['spotifyconnect']['password']) == "")) $_POST['features']['spotifyconnect']['password'] = "pass";
            // if (!isset($_POST['features']['spotifyconnect']['username'])) $_POST['features']['spotifyconnect']['username'] = "";
            // if (!isset($_POST['features']['spotifyconnect']['password'])) $_POST['features']['spotifyconnect']['password'] = "";
            if (!isset($_POST['features']['spotifyconnect']['autoplay']) || (trim($_POST['features']['spotifyconnect']['autoplay']) == "")) $_POST['features']['spotifyconnect']['autoplay'] = "false";
            if (!isset($_POST['features']['spotifyconnect']['device_name']) || (trim($_POST['features']['spotifyconnect']['device_name']) == "")) $_POST['features']['spotifyconnect']['device_name'] = "RuneAudio";
            if (!isset($_POST['features']['spotifyconnect']['save_last_volume']) || (trim($_POST['features']['spotifyconnect']['save_last_volume']) == "")) $_POST['features']['spotifyconnect']['save_last_volume'] = "0";
            if (!isset($_POST['features']['spotifyconnect']['volume_normalisation']) || (trim($_POST['features']['spotifyconnect']['volume_normalisation']) == "")) $_POST['features']['spotifyconnect']['volume_normalisation'] = "false";
            if ($_POST['features']['spotifyconnect']['autoplay'] != $redis->hGet('spotifyconnect', 'autoplay')
                    // || $_POST['features']['spotifyconnect']['password'] != $redis->hGet('spotifyconnect', 'password')
                    // || $_POST['features']['spotifyconnect']['username'] != $redis->hGet('spotifyconnect', 'username')
                    || $_POST['features']['spotifyconnect']['bitrate'] != $redis->hGet('spotifyconnect', 'bitrate')
                    || $_POST['features']['spotifyconnect']['device_name'] != $redis->hGet('spotifyconnect', 'device_name')
                    || $_POST['features']['spotifyconnect']['enable'] != $redis->hGet('spotifyconnect', 'enable')
                    || $_POST['features']['spotifyconnect']['normalisation_pregain'] != $redis->hGet('spotifyconnect', 'normalisation_pregain')
                    || $_POST['features']['spotifyconnect']['save_last_volume'] != $redis->hGet('spotifyconnect', 'save_last_volume')
                    || $_POST['features']['spotifyconnect']['timeout'] != $redis->hGet('spotifyconnect', 'timeout')
                    || $_POST['features']['spotifyconnect']['volume_normalisation'] != $redis->hGet('spotifyconnect', 'volume_normalisation')) {
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'spotifyconnect', 'action' => 'start', 'args' => $_POST['features']['spotifyconnect']));
            }
        } else {
            // create worker job (stop Spotify Connect)
            $redis->hGet('spotifyconnect','enable') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'spotifyconnect', 'action' => 'stop'));
        }
        if (isset($_POST['features']['owntone']) && is_array($_POST['features']['owntone'])) {
            // create worker job (stop/start/modify owntone)
            $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'owntoneenable', 'args' => $_POST['features']['owntone']));
        }
    }
    // ----- SYSTEM COMMANDS -----
    if (isset($_POST['syscmd'])){
        if ($_POST['syscmd'] === 'reboot') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'reboot'));
        if ($_POST['syscmd'] === 'poweroff') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'poweroff'));
        if ($_POST['syscmd'] === 'display_off') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'display_off'));
        if ($_POST['syscmd'] === 'mpdrestart') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'mpdrestart'));
        if ($_POST['syscmd'] === 'backup') {
            $backupJobID = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'backup'));
            $jobID[] = $backupJobID;
        }
        if ($_POST['syscmd'] === 'restore') {
            sysCmd('/srv/http/command/restore.php');
        }
        if ($_POST['syscmd'] === 'security') {
            if (isset($_POST['action']) && strpos('|linux_password_save|ap_password_save|', $_POST['action']) && isset($_POST['args'])) {
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => $_POST['syscmd'], 'action' => $_POST['action'], 'args' => $_POST['args']));
            } else if (isset($_POST['action']) && strpos('|linux_password_randomise|', $_POST['action'])) {
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => $_POST['syscmd'], 'action' => $_POST['action']));
            }
        }
    }
}
if (isset($jobID)) {
    waitSyWrk($redis, $jobID);
}
// push backup file
if (isset($_POST['syscmd']) && ($_POST['syscmd'] === 'backup')) {
    $fileName = $redis->hGet('w_msg', $backupJobID);
    // push the file via the browser
    pushFile($redis, $fileName);
    // queue a job to delete the file, don't wait for completion
    wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'delbackup', 'args' => $fileName));
    $redis->hDel('w_msg', $backupJobID);
}
// collect system status
$bit = '('.trim(sysCmd('getconf LONG_BIT')[0]).'bit)';
$release = trim(sysCmd('uname -sr')[0]);
$machine = trim(sysCmd('uname -m')[0]);
$os = $redis->get('os');
if ($os == 'RPiOS') {
    $codename = '-'.$redis->get('codename');
} else {
    $codename = '';
}
$templateData['sysstate']['kernel'] = $release.' '.$os.$codename.' '.$machine.' '.$bit;
$templateData['sysstate']['time'] = implode('\n', sysCmd('date'));
$templateData['sysstate']['uptime'] = date('d:H:i:s', strtok(file_get_contents('/proc/uptime'), ' ' ));
$templateData['sysstate']['HWplatform'] = $redis->get('hwplatform')." (".$redis->get('hwplatformid').")";
$templateData['sysstate']['HWmodel'] = implode('\n', sysCmd('cat /proc/device-tree/model'));
$templateData['sysstate']['playerID'] = $redis->get('playerid');
$templateData['sysstate']['runeOS'] = trim(sysCmd("cat /etc/motd | grep -i 'RuneOS:' | cut -d ':' -f 2")[0]);
$templateData['sysstate']['buildversion'] = $redis->get('buildversion')."-".$redis->get('patchlevel');
$templateData['sysstate']['release'] = $redis->get('release');
// the next line won't work, file protection issue with /opt/vc/bin/vcgencmd
$templateData['sysstate']['cpuTemp'] = trim(preg_replace('/[^0-9.]/', '', sysCmd('vcgencmd measure_temp | grep temp')[0]));
// collect the rest of the UI variables
$templateData['hostname'] = $redis->get('hostname');
$templateData['ntpserver'] = $redis->get('ntpserver');
$templateData['timezone'] = $redis->get('timezone');
$templateData['orionprofile'] = $redis->get('orionprofile');
$templateData['airplay'] = $redis->hGetAll('airplay');
$templateData['dlna'] = $redis->hGetAll('dlna');
$templateData['local_browser'] = $redis->hGetAll('local_browser');
$templateData['remoteSStime'] = $redis->get('remoteSStime');
$templateData['udevil'] = $redis->get('udevil');
$templateData['coverart'] = $redis->get('coverart');
$templateData['lastfm'] = $redis->hGetAll('lastfm');
$templateData['proxy'] = $redis->hGetAll('proxy');
$templateData['spotifyconnect'] = $redis->hGetAll('spotifyconnect');
$templateData['owntone']['enable'] = $redis->hGet('owntone', 'enable');
$templateData['owntone']['default_volume'] = $redis->hGet('owntone', 'default_volume');
$templateData['owntone']['use_current_volume'] = $redis->hGet('owntone', 'use_current_volume');
$templateData['owntone']['multidevice'] = $redis->hGet('owntone', 'multidevice');
$templateData['owntone']['streaming'] = $redis->hGet('owntone', 'streaming');
$templateData['samba'] = $redis->hGetAll('samba');
$templateData['hwplatformid'] = $redis->get('hwplatformid');
$templateData['i2smodule'] = $redis->get('i2smodule');
$templateData['i2smodule_select'] = $redis->get('i2smodule_select');
if ($redis->get('ao')) {
    $templateData['ao'] = 1;
} else {
    $templateData['ao'] = 0;
}
$templateData['hwinput'] = $redis->hGet('hw_input', 'enable');
$templateData['cdinput'] = $redis->hGet('CD', 'enable');
$templateData['cdautoplay'] = $redis->hGet('CD', 'autoplay');
// the following code is for a manually edited <p1mountpoint>/config.txt containing a I2S-Settings dtoverlay value
if ($templateData['i2smodule'] == 'none') {
    $retval = sysCmd("grep -v '#.*=' '".$redis->get('p1mountpoint')."/config.txt' | sed -n '/^#.[ ]*.RuneAudio I2S-Settings/,/^#/p' | grep '^dtoverlay' | cut -d '=' -f2")[0];
    if (isset($retval)) {
        $retval = trim($retval);
        if (($retval != 'none') && $retval) {
            $redis->set('i2smodule', $retval);
            $templateData['i2smodule'] = $retval;
            // also determine a valid value of $redis->get('i2smodule_select')
            // first try to match a generic sound-card
            $retval1 = sysCmd("grep -i '".$retval."|Generic' '/srv/http/.config/i2s_table.txt' | head -n 1")[0];
            if (isset($retval1)) {
                $retval1 = trim($retval1);
                if ($retval1) {
                    $redis->set('i2smodule_select', $retval1);
                    $templateData['i2smodule_select'] = $retval1;
                } else {
                    // when no generic sound card matches, just get the first
                    $retval1 = sysCmd("grep -i '".$retval."|' '/srv/http/.config/i2s_table.txt' | head -n 1")[0];
                    if (isset($retval1)) {
                        $retval1 = trim($retval1);
                        if ($retval1) {
                            $redis->set('i2smodule_select', $retval1);
                            $templateData['i2smodule_select'] = $retval1;
                        } else {
                            // this should never happen
                            $retval1 = $retval.'|Unknown';
                            $redis->set('i2smodule_select', $retval1);
                            $templateData['i2smodule_select'] = $retval1;
                        }
                    }
                }
            }
        }
    }
    unset($retval, $retval1);
}
$templateData['audio_on_off'] = $redis->get('audio_on_off');
// $templateData['kernel'] = $redis->get('kernel');
$templateData['kernel'] = trim(sysCmd('uname -sr')[0]).$bit;
// the next line prevents the kernel change routine from running
$redis->set('kernel', $templateData['kernel']);
unset($bit);
$templateData['pwd_protection'] = $redis->get('pwd_protection');
$templateData['memory'] = $redis->get('memoryKb');
$cores = $redis->get('cores');
$templateData['cores'] = $cores;
// check if a local browser and owntone are supported, hardware must be multiprocessor
if ($redis->get('cores') > 1) {
    $templateData['local_browseronoff'] = true;
    $templateData['local_owntoneonoff'] = true;
    $templateData['local_cdripperonoff'] = true;
} else {
    $templateData['local_browseronoff'] = false;
    $templateData['local_owntoneonoff'] = false;
    $templateData['local_cdripperonoff'] = false;
}
// proces CD ripper information
$stillMounted = false;
if ($templateData['local_cdripperonoff']) {
    // check that a cd drive is present
    $templateData['cdstoragedevices'] = array();
    $templateData['cdstoragedevice'] = $redis->hGet('CDripper', 'cdstoragedevice');
    $templateData['storagedevices'] = '<strong>Available devices and relevance</strong><br>';
    $templateData['ripdirectory'] = '/'.trim($redis->hGet('CDripper', 'ripdir'), " \n\r\t\v\x00/");
    $cdPresent = sysCmd("ls -al /dev/cdrom 2>/dev/null | grep -ic '/dev/cdrom\s*->' | xargs")[0];
    if ($cdPresent) {
        // only process when a CD drive is present
        // get a list of devices, only network devices and disks are selected
        $devices = sysCmd("df --output -B M | grep -iE '^//|^/dev/sd'");
        if (count($devices)) {
            foreach ($devices as $device) {
                $deviceDetails = explode(' ', trim(preg_replace('!\s+!', ' ', $device)));
                // relevant details
                //  0 : Filesystem
                //  2 : Label
                //  6 : Device capacity Mb (with trailing M)
                //  8 : Free space b (with trailing M)
                // 11 : Mount
                $freeSpace = preg_replace('/[^0-9\s]/', '', $deviceDetails[8]);
                if (($freeSpace < 600) && ($deviceDetails[11].'/rips' == $templateData['cdstoragedevice'])) {
                    $redis->hSet('CDripper', 'cdstoragedevice', 'None');
                    $templateData['cdstoragedevice'] = 'None';
                }
                $readOnly = 0;
                $noWrite = 0;
                if (substr($deviceDetails[0], 0, 2) == '//') {
                    // its a network mount
                    $readOnly = sysCmd("mount -l | grep -i '".$deviceDetails[0]."' | grep -ic '(ro,' | xargs")[0];
                    if (!$readOnly) {
                        // its mounted r/w
                        // check that the share is r/w
                        $noWrite = sysCmd("touch /mnt/MPD/USB/sda1-usb-USB_SanDisk_3.2G/xxx1234567890xxx >/dev/null ; echo $? | xargs")[0];
                        if (!$noWrite) {
                            // successful write, file share is r/w, remove the file created in the touch
                            sysCmd("rm /mnt/MPD/USB/sda1-usb-USB_SanDisk_3.2G/xxx1234567890xxx");
                        }
                    }
                    $templateData['storagedevices'] .= $deviceDetails[0].' on '.$deviceDetails[11].', capacity:'.$deviceDetails[6].'b, free:'.$deviceDetails[8].'b - ';
                    if ($freeSpace < 600) {
                        $templateData['storagedevices'] .= '<strong>Insufficient free space</strong><br>';
                    } else if ($readOnly) {
                        $templateData['storagedevices'] .= '<strong>Read only mount</strong><br>';
                    } else if ($noWrite) {
                        $templateData['storagedevices'] .= '<strong>Read only network share</strong><br>';
                    } else if ($deviceDetails[11] == $templateData['cdstoragedevice']) {
                        $templateData['storagedevices'] .= '<strong>Selected</strong><br>';
                        $templateData['cdstoragedevices'][] = $deviceDetails[11];
                        $stillMounted = true;
                    } else {
                        $templateData['storagedevices'] .= '<strong>Device usable</strong><br>';
                        $templateData['cdstoragedevices'][] = $deviceDetails[11];
                    }
                } else {
                    // its a sd?? filesystem mount
                    $templateData['storagedevices'] .= $deviceDetails[0].' on '.$deviceDetails[11].', capacity:'.$deviceDetails[6].'b, free:'.$deviceDetails[8].'b - ';
                    if ($freeSpace < 600) {
                        $templateData['storagedevices'] .= '<strong>Insufficient free space</strong><br>';
                    } else if ($deviceDetails[11] == $templateData['cdstoragedevice']) {
                        $templateData['storagedevices'] .= '<strong>Selected</strong><br>';
                        $templateData['cdstoragedevices'][] = $deviceDetails[11];
                        $stillMounted = true;
                    } else {
                        $templateData['storagedevices'] .= '<strong>Device usable</strong><br>';
                        $templateData['cdstoragedevices'][] = $deviceDetails[11];
                    }
                }
            }
        } else {
            $templateData['storagedevices'] = '<strong>No devices detected</strong><br>';
        }
    } else {
        $templateData['storagedevices'] = '<strong>No CD-ROM Connected</strong><br>';
    }
} else {
    $redis->hSet('CDripper', 'enable', 0);
    $redis->hSet('CDripper', 'cdstoragedevice', 'None');
}
if (!isset($templateData['cdstoragedevices']) || !count($templateData['cdstoragedevices']) || !$stillMounted) {
    $redis->hSet('CDripper', 'cdstoragedevice', 'None');
}
$templateData['cdripper'] = $redis->hGet('CDripper', 'enable');
$templateData['cdstoragedevice'] = $redis->hGet('CDripper', 'cdstoragedevice');
