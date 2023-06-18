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
        if (isset($_POST['i2smodule'])) {
            if ($_POST['i2smodule'] === 'berrynosmini') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'orionprofile', 'args' => 'OrionV3_berrynosmini'));
            // autoswitch optimized kernel profile for IQaudIO Pi-DAC
            if ($_POST['i2smodule'] === 'iqaudiopidac') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'orionprofile', 'args' => 'OrionV3_iqaudio'));
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
        if (isset($_POST['features']['dlna']['enable']) && $_POST['features']['dlna']['enable']) {
            if (!isset($_POST['features']['dlna']['queueowner'])) $_POST['features']['dlna']['queueowner'] = 0;
            if (isset($_POST['features']['dlna']['name']) && (($redis->hGet('dlna','enable') !== $_POST['features']['dlna']['enable']) || ($redis->hGet('dlna','name') !== $_POST['features']['dlna']['name']) || ($redis->hGet('dlna','queueowner') !== $_POST['features']['dlna']['queueowner']))) {
                if (trim($_POST['features']['dlna']['name']) == "") $_POST['features']['dlna']['enable'] = "RuneAudio";
                // create worker job (start upmpdcli)
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'dlna', 'action' => 'start', 'args' => $_POST['features']['dlna']));
            }
        } else {
            // create worker job (stop upmpdcli)
            $redis->hGet('dlna','enable') === '0' || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'dlna', 'action' => 'stop', 'args' => $_POST['features']['dlna']));
        }
        if (isset($_POST['features']['local_browser']['enable']) && $_POST['features']['local_browser']['enable']) {
            $redis->hGet('local_browser', 'enable') || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'xorgserver', 'action' => 'start', 'args' => 1));
            if (isset($_POST['features']['local_browser']['zoomfactor']) && ($_POST['features']['local_browser']['zoomfactor'] != $redis->hGet('local_browser', 'zoomfactor'))) {
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'xorgserver', 'action' => 'zoomfactor', 'args' => $_POST['features']['local_browser']['zoomfactor']));
            }
            if (isset($_POST['features']['local_browser']['rotate']) && ($_POST['features']['local_browser']['rotate'] != $redis->hGet('local_browser', 'rotate'))) {
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'xorgserver', 'action' => 'rotate', 'args' => $_POST['features']['local_browser']['rotate']));
            }
            if (isset($_POST['features']['local_browser']['overscan']) && $_POST['features']['local_browser']['overscan']) {
                $redis->hGet('local_browser', 'overscan') || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'xorgserver', 'action' => 'overscan', 'args' => 1));
            } else {
                $redis->hGet('local_browser', 'overscan') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'xorgserver', 'action' => 'overscan', 'args' => 0));
            }
            if (isset($_POST['features']['local_browser']['mouse_cursor']) && $_POST['features']['local_browser']['mouse_cursor']) {
                $redis->hGet('local_browser', 'mouse_cursor') || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'xorgserver', 'action' => 'mouse_cursor', 'args' => 1));
            } else {
                $redis->hGet('local_browser', 'mouse_cursor') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'xorgserver', 'action' => 'mouse_cursor', 'args' => 0));
            }
            if (isset($_POST['features']['local_browser']['localSStime']) && ($_POST['features']['local_browser']['localSStime'] != $redis->hGet('local_browser', 'localSStime'))) {
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'xorgserver', 'action' => 'localSStime', 'args' => $_POST['features']['local_browser']['localSStime']));
            }
            if (isset($_POST['features']['local_browser']['smallScreenSaver']) && $_POST['features']['local_browser']['smallScreenSaver']) {
                $redis->hGet('local_browser', 'smallScreenSaver') || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'xorgserver', 'action' => 'smallScreenSaver', 'args' => 1));
            } else {
                $redis->hGet('local_browser', 'smallScreenSaver') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'xorgserver', 'action' => 'smallScreenSaver', 'args' => 0));
            }
        } else {
            $redis->hGet('local_browser', 'enable') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'xorgserver', 'action' => 'stop', 'args' => 0));
        }
        if (isset($_POST['features']['local_browser']['enable-splash']) && $_POST['features']['local_browser']['enable-splash']) {
            $redis->hGet('local_browser', 'enable-splash') || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'xorgserver', 'action' => 'enable-splash', 'args' => 1));
        } else {
            $redis->hGet('local_browser', 'enable-splash') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'xorgserver', 'action' => 'enable-splash', 'args' => 0));
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
            if (!isset($_POST['features']['spotifyconnect']['username']) || (trim($_POST['features']['spotifyconnect']['username']) == "")) $_POST['features']['spotifyconnect']['username'] = "user";
            if (!isset($_POST['features']['spotifyconnect']['password']) || (trim($_POST['features']['spotifyconnect']['password']) == "")) $_POST['features']['spotifyconnect']['password'] = "pass";
            if (!isset($_POST['features']['spotifyconnect']['device_name']) || (trim($_POST['features']['spotifyconnect']['device_name']) == "")) $_POST['features']['spotifyconnect']['device_name'] = "RuneAudio";
            if (!isset($_POST['features']['spotifyconnect']['volume_normalisation']) || (trim($_POST['features']['spotifyconnect']['volume_normalisation']) == "")) $_POST['features']['spotifyconnect']['volume_normalisation'] = "false";
            if (!isset($_POST['features']['spotifyconnect']['autoplay']) || (trim($_POST['features']['spotifyconnect']['autoplay']) == "")) $_POST['features']['spotifyconnect']['autoplay'] = "false";
            if (!isset($_POST['features']['spotifyconnect']['save_last_volume']) || (trim($_POST['features']['spotifyconnect']['save_last_volume']) == "")) $_POST['features']['spotifyconnect']['save_last_volume'] = "0";
            if ($_POST['features']['spotifyconnect']['autoplay'] != $redis->hGet('spotifyconnect', 'autoplay')
                    OR $_POST['features']['spotifyconnect']['bitrate'] != $redis->hGet('spotifyconnect', 'bitrate')
                    OR $_POST['features']['spotifyconnect']['device_name'] != $redis->hGet('spotifyconnect', 'device_name')
                    OR $_POST['features']['spotifyconnect']['enable'] != $redis->hGet('spotifyconnect', 'enable')
                    OR $_POST['features']['spotifyconnect']['normalisation_pregain'] != $redis->hGet('spotifyconnect', 'normalisation_pregain')
                    OR $_POST['features']['spotifyconnect']['password'] != $redis->hGet('spotifyconnect', 'password')
                    OR $_POST['features']['spotifyconnect']['save_last_volume'] != $redis->hGet('spotifyconnect', 'save_last_volume')
                    OR $_POST['features']['spotifyconnect']['timeout'] != $redis->hGet('spotifyconnect', 'timeout')
                    OR $_POST['features']['spotifyconnect']['username'] != $redis->hGet('spotifyconnect', 'username')
                    OR $_POST['features']['spotifyconnect']['volume_normalisation'] != $redis->hGet('spotifyconnect', 'volume_normalisation')) {
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'spotifyconnect', 'action' => 'start', 'args' => $_POST['features']['spotifyconnect']));
            }
        } else {
            // create worker job (stop Spotify Connect)
            $redis->hGet('spotifyconnect','enable') && $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'spotifyconnect', 'action' => 'stop'));
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
    // push the file to via the browser
    pushFile($fileName);
    // queue a job to delete the file, don't wait for completion
    wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'delbackup', 'args' => $fileName));
    $redis->hDel('w_msg', $backupJobID);
}
// collect system status
$bit = ' ('.sysCmd('getconf LONG_BIT')[0].'bit)';
$template->sysstate['kernel'] = trim(file_get_contents('/proc/version')).$bit;
$template->sysstate['time'] = implode('\n', sysCmd('date'));
$template->sysstate['uptime'] = date('d:H:i:s', strtok(file_get_contents('/proc/uptime'), ' ' ));
$template->sysstate['HWplatform'] = $redis->get('hwplatform')." (".$redis->get('hwplatformid').")";
$template->sysstate['HWmodel'] = implode('\n', sysCmd('cat /proc/device-tree/model'));
$template->sysstate['playerID'] = $redis->get('playerid');
$template->sysstate['runeOS'] = trim(sysCmd("cat /etc/motd | grep -i 'RuneOS:' | cut -d ':' -f 2")[0]);
$template->sysstate['buildversion'] = $redis->get('buildversion')."-".$redis->get('patchlevel');
$template->sysstate['release'] = $redis->get('release');
// the next line won't work, file protection issue with /opt/vc/bin/vcgencmd
$template->sysstate['cpuTemp'] = trim(preg_replace('/[^0-9.]/', '', sysCmd('vcgencmd measure_temp | grep temp')[0]));
// collect the rest of the UI variables
$template->hostname = $redis->get('hostname');
$template->ntpserver = $redis->get('ntpserver');
$template->timezone = $redis->get('timezone');
$template->orionprofile = $redis->get('orionprofile');
$template->airplay = $redis->hGetAll('airplay');
$template->dlna = $redis->hGetAll('dlna');
$template->local_browser = $redis->hGetAll('local_browser');
$template->remoteSStime = $redis->get('remoteSStime');
$template->udevil = $redis->get('udevil');
$template->coverart = $redis->get('coverart');
$template->lastfm = $redis->hGetAll('lastfm');
$template->proxy = $redis->hGetAll('proxy');
$template->spotifyconnect = $redis->hGetAll('spotifyconnect');
$template->samba = $redis->hGetAll('samba');
$template->hwplatformid = $redis->get('hwplatformid');
$template->i2smodule = $redis->get('i2smodule');
$template->i2smodule_select = $redis->get('i2smodule_select');
if ($redis->get('ao')) {
    $template->ao = true;
} else {
    $template->ao = false;
}
$template->hwinput = $redis->hGet('hw_input', 'enable');
$template->cdinput = $redis->hGet('CD', 'enable');
$template->cdautoplay = $redis->hGet('CD', 'autoplay');
// the following code is for a manually edited /boot/config.txt containing a I2S-Settings dtoverlay value
if ($template->i2smodule == 'none') {
    $retval = sysCmd("grep -v '#.*=' /boot/config.txt | sed -n '/^#.[ ]*.RuneAudio I2S-Settings/,/^#/p' | grep '^dtoverlay' | cut -d '=' -f2")[0];
    if (isset($retval)) {
        $retval = trim($retval);
        if (($retval != 'none') && $retval) {
            $redis->set('i2smodule', $retval);
            $template->i2smodule = $retval;
            // also determine a valid value of $redis->get('i2smodule_select')
            // first try to match a generic sound-card
            $retval1 = sysCmd("grep -i '".$retval."|Generic' '/srv/http/.config/i2s_table.txt' | head -n 1")[0];
            if (isset($retval1)) {
                $retval1 = trim($retval1);
                if ($retval1) {
                    $redis->set('i2smodule_select', $retval1);
                    $template->i2smodule_select = $retval1;
                } else {
                    // when no generic sound card matches, just get the first
                    $retval1 = sysCmd("grep -i '".$retval."|' '/srv/http/.config/i2s_table.txt' | head -n 1")[0];
                    if (isset($retval1)) {
                        $retval1 = trim($retval1);
                        if ($retval1) {
                            $redis->set('i2smodule_select', $retval1);
                            $template->i2smodule_select = $retval1;
                        } else {
                            // this should never happen
                            $retval1 = $retval.'|Unknown';
                            $redis->set('i2smodule_select', $retval1);
                            $template->i2smodule_select = $retval1;
                        }
                    }
                }
            }
        }
    }
    unset($retval, $retval1);
}
$template->audio_on_off = $redis->get('audio_on_off');
// $template->kernel = $redis->get('kernel');
$template->kernel = trim(sysCmd('uname -sr')[0]).$bit;
// the next line prevents the kernel change routine from running
$redis->set('kernel', $template->kernel);
unset($bit);
$template->pwd_protection = $redis->get('pwd_protection');
$memory = $redis->get('memoryKb');
$template->memory = $memory;
$cores = $redis->get('cores');
$template->cores = $cores;
// check if a local browser is supported, by checking that X11 is isntalled
clearstatcache(true, '/usr/bin/xinit');
if (file_exists('/usr/bin/xinit')) {
    $template->local_browseronoff = true;
    if ($memory < 400000) {
        // old PI models with less than 512MB cannot run the local browser even if X11 is installed
        $template->local_browseronoff = false;
    }
} else {
    $template->local_browseronoff = false;
}
