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
    // ----- KERNEL -----
    if (isset($_POST['kernel'])) {        
        // submit worker job
        if ($redis->get('kernel') !== $_POST['kernel']) {
            $job = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'kernelswitch', 'args' => $_POST['kernel']));
            $notification = new stdClass();
            $notification->title = 'Kernel switch';
            $notification->text = 'Kernel switch started...';
            wrk_notify($redis, 'startjob', $notification, $job);
            $jobID[] = $job;
        }
    }
    if (isset($_POST['orionprofile'])) {        
        // submit worker job
        $redis->get('orionprofile') == $_POST['orionprofile'] || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'orionprofile', 'args' => $_POST['orionprofile']));
    }
    if (isset($_POST['i2smodule_select'])) {
        // submit worker job
        if ($redis->get('i2smodule_select') !== $_POST['i2smodule_select']) {
			$redis->set('i2smodule_select', $_POST['i2smodule_select']);
            $notification = new stdClass();
			list($i2smodule, $i2sselectedname) = explode('|', $_POST['i2smodule_select'], 2);
            if ($i2smodule !== 'none') {
                $notification->title = 'Loading I&#178;S kernel module';
            } else {
                $notification->title = 'Unloading I&#178;S kernel module';
            }
            $job = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'i2smodule', 'args' => $i2smodule));
            $notification->text = 'Please wait';
            wrk_notify($redis, 'startjob', $notification, $job);
            $jobID[] = $job;
        }
        
        // autoswitch optimized kernel profile for BerryNOS mini DAC
        if ($_POST['i2smodule'] === 'berrynosmini') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'orionprofile', 'args' => 'OrionV3_berrynosmini'));
        // autoswitch optimized kernel profile for IQaudIO Pi-DAC
        if ($_POST['i2smodule'] === 'iqaudiopidac') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'orionprofile', 'args' => 'OrionV3_iqaudio'));

    // audio-on-off
        if ($redis->get('audio_on_off') !== $_POST['audio_on_off']) {
            // submit worker job
            $notification = new stdClass();
            $job = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'audio_on_off', 'args' => $_POST['audio_on_off']));
            $notification->text = 'Please wait';
            wrk_notify($redis, 'startjob', $notification, $job);
            $jobID[] = $job;
            if ($_POST['audio_on_off'] == 1) {
                $redis->get('audio_on_off') == 1 || $redis->set('audio_on_off', 1);
            } else {
                $redis->get('audio_on_off') == 0 || $redis->set('audio_on_off', 0);
            }
        }
    }

    // ----- FEATURES -----
    if (isset($_POST['features'])) {
        if ($_POST['features']['airplay']['enable'] == 1) {
            if ($redis->hGet('airplay','enable') !== $_POST['features']['airplay']['enable'] OR $redis->hGet('airplay','name') !== $_POST['features']['airplay']['name']) {
                // create worker job (start shairport-sync)
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'airplay', 'action' => 'start', 'args' => $_POST['features']['airplay']['name']));
            }
        } else {
            // create worker job (stop shairport-sync)
            $redis->hGet('airplay','enable') === '0' || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'airplay', 'action' => 'stop', 'args' => $_POST['features']['airplay']['name']));
        }
        if ($_POST['features']['dlna']['enable'] == 1) {
            if ($redis->hGet('dlna','enable') !== $_POST['features']['dlna']['enable'] OR $redis->hGet('dlna','name') !== $_POST['features']['dlna']['name'] OR $redis->hGet('dlna','queueowner') !== $_POST['features']['dlna']['queueowner']) {
                // create worker job (start upmpdcli)
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'dlna', 'action' => 'start', 'args' => $_POST['features']['dlna']));
            }
        } else {
            // create worker job (stop upmpdcli)
            $redis->hGet('dlna','enable') === '0' || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'dlna', 'action' => 'stop', 'args' => $_POST['features']['dlna']));
        }
        if ($_POST['features']['snapserver']['enable'] == 1) {
            $redis->hGet('snapserver', 'enable') == 1 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'snapserver', 'action' => 'start', 'args' => 1));
        } else {
            $redis->hGet('snapserver', 'enable') == 0 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'snapserver', 'action' => 'stop', 'args' => 0));
        }
        if ($_POST['features']['local_browser']['enable'] == 1) {
            $redis->hGet('local_browser', 'enable') == 1 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'xorgserver', 'action' => 'start', 'args' => 1));
			if ($_POST['features']['local_browser']['zoomfactor'] != $redis->hGet('local_browser', 'zoomfactor')) {
				$jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'xorgserver', 'action' => 'zoomfactor', 'args' => $_POST['features']['local_browser']['zoomfactor']));
			}
			if ($_POST['features']['local_browser']['rotate'] != $redis->hGet('local_browser', 'rotate')) {
				$jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'xorgserver', 'action' => 'rotate', 'args' => $_POST['features']['local_browser']['rotate']));
			}
			if ($_POST['features']['local_browser']['mouse_cursor'] == 1) {
				$redis->hGet('local_browser', 'mouse_cursor') == 1 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'xorgserver', 'action' => 'mouse_cursor', 'args' => 1));
			} else {
				$redis->hGet('local_browser', 'mouse_cursor') == 0 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'xorgserver', 'action' => 'mouse_cursor', 'args' => 0));
			}
			if ($_POST['features']['localSStime'] != $redis->get('localSStime')) {
				$jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'xorgserver', 'action' => 'localSStime', 'args' => $_POST['features']['localSStime']));
			}
        } else {
            $redis->hGet('local_browser', 'enable') == 0 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'xorgserver', 'action' => 'stop', 'args' => 0));
        }
        if ($_POST['features']['pwd_protection'] == 1) {
            $redis->get('pwd_protection') == 1 || $redis->set('pwd_protection', 1);
        } else {
            $redis->get('pwd_protection') == 0 || $redis->set('pwd_protection', 0);
        }
        if (isset($_POST['features']['remoteSStime'])) {
            $redis->set('remoteSStime', $_POST['features']['remoteSStime']);
        }
        if ($_POST['features']['udevil'] == 1) {
            // create worker job (start udevil)
            $redis->get('udevil') == 1 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'udevil', 'action' => 'start'));
        } else {
            // create worker job (stop udevil)
            $redis->get('udevil') == 0 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'udevil', 'action' => 'stop'));
        }
        if ($_POST['features']['coverart'] == 1) {
            $redis->get('coverart') == 1 || $redis->set('coverart', 1);
        } else {
            $redis->get('coverart') == 0 || $redis->set('coverart', 0);
        }
        if ($_POST['features']['lastfm']['enable'] == 1) {
            // create worker job (start lastfm)
            if (($_POST['features']['lastfm']['user'] != $redis->hGet('lastfm', 'user') OR $_POST['features']['lastfm']['pass'] != $redis->hGet('lastfm', 'pass')) OR $redis->hGet('lastfm', 'enable') != $_POST['features']['lastfm']['enable']) {
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'lastfm', 'action' => 'start', 'args' => $_POST['features']['lastfm']));
            }
        } else {
            // create worker job (stop lastfm)
            $redis->hGet('lastfm','enable') == 0 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'lastfm', 'action' => 'stop'));
		}
        if ($_POST['features']['samba']['enable'] == 1) {
            // create worker job (start samba)
            if (($_POST['features']['samba']['readwrite'] != $redis->hGet('samba', 'readwrite')) OR ($redis->hGet('samba', 'enable') != $_POST['features']['samba']['enable'])) {
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'sambaonoff', 'action' => $_POST['features']['samba']['enable'], 'args' => $_POST['features']['samba']['readwrite']));
            }
        } else {
            // create worker job (stop samba)
            $redis->hGet('samba','enable') == 0 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'sambaonoff', 'action' => $_POST['features']['samba']['enable'], 'args' => $_POST['features']['samba']['readwrite']));
        }
		if ($_POST['features']['spotify']['enable'] == 1) {
            // create worker job (start spotify)
            if (($_POST['features']['spotify']['user'] != $redis->hGet('spotify', 'user') OR $_POST['features']['spotify']['pass'] != $redis->hGet('spotify', 'pass')) OR $redis->hGet('spotify', 'enable') != $_POST['features']['spotify']['enable']) {
                $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'spotify', 'action' => 'start', 'args' => $_POST['features']['spotify']));
            }
        } else {
            // create worker job (stop spotify)
            $redis->hGet('spotify','enable') == 0 || $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'spotify', 'action' => 'stop'));
        }
    }
    // ----- SYSTEM COMMANDS -----
    if (isset($_POST['syscmd'])){
        if ($_POST['syscmd'] === 'reboot') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'reboot'));
        if ($_POST['syscmd'] === 'poweroff') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'poweroff'));
        if ($_POST['syscmd'] === 'display_off') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'display_off'));
        if ($_POST['syscmd'] === 'mpdrestart') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'mpdrestart'));
        if ($_POST['syscmd'] === 'backup') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'backup'));
        if ($_POST['syscmd'] === 'activate') $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'restoreact'));
    }
}
waitSyWrk($redis,$jobID);
// push backup file
if ($_POST['syscmd'] === 'backup') {
    pushFile($redis->hGet('w_msg', $jobID[0]));
    $redis->hDel('w_msg', $jobID[0]);
}
// collect system status
$template->sysstate['kernel'] = file_get_contents('/proc/version');
$template->sysstate['time'] = implode('\n', sysCmd('date'));
$template->sysstate['uptime'] = date('d:H:i:s', strtok(file_get_contents('/proc/uptime'), ' ' ));
$template->sysstate['HWplatform'] = $redis->get('hwplatform')." (".$redis->get('hwplatformid').")";
$template->sysstate['HWmodel'] = implode('\n', sysCmd('cat /proc/device-tree/model'));
$template->sysstate['playerID'] = $redis->get('playerid');
$template->sysstate['buildversion'] = $redis->get('buildversion')."-".$redis->get('patchlevel');
$template->sysstate['release'] = $redis->get('release');
$template->hostname = $redis->get('hostname');
$template->ntpserver = $redis->get('ntpserver');
$template->timezone = $redis->get('timezone');
$template->orionprofile = $redis->get('orionprofile');
$template->airplay = $redis->hGetAll('airplay');
$template->dlna = $redis->hGetAll('dlna');
$template->local_browser = $redis->hGetAll('local_browser');
$template->snapserver = $redis->hGetAll('snapserver');
$template->localSStime = $redis->get('localSStime');
$template->remoteSStime = $redis->get('remoteSStime');
$template->udevil = $redis->get('udevil');
$template->coverart = $redis->get('coverart');
$template->lastfm = $redis->hGetAll('lastfm');
$template->proxy = $redis->hGetAll('proxy');
$template->spotify = $redis->hGetAll('spotify');
$template->samba = $redis->hGetAll('samba');
$template->hwplatformid = $redis->get('hwplatformid');
$template->i2smodule = $redis->get('i2smodule');
$template->i2smodule_select = $redis->get('i2smodule_select');
// if ($template->i2smodule == 'none') {
	// $retval = sysCmd("grep -v '#.*=' /boot/config.txt | sed -n '/## RuneAudio I2S-Settings/,/#/p' | grep dtoverlay | cut -d '=' -f2");
	// if (isset($retval[0])) {
		// $retval[0] = trim($retval[0]);
		// if (($retval[0] != 'none') && (!empty(retval[0]))) {
			// $redis->set('i2smodule', $retval[0]);
			// $template->i2smodule = $retval[0];
		// }
	// }
	// unset($retval);
// }
$template->audio_on_off = $redis->get('audio_on_off');
$template->kernel = $redis->get('kernel');
$template->pwd_protection = $redis->get('pwd_protection');
$template->local_browseronoff = file_exists('/usr/bin/xinit');
$template->snapserveronoff = file_exists('/usr/bin/snapserver');
$template->restoreact = $redis->get('restoreact');
