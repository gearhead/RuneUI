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
 *  file: /srv/http/command/update_os
 *  version: 1.5
 *  date: August 2018
 *  coder: janui
 *
 */

function updateOS($redis) {
	// first check that the patch variable exists
	if (!$redis->exists('patchlevel')) {
		$redis->set('patchlevel', 0);
	}
    // standard format for the update part - patch levels start at 0 (= no patch)
	// even if an image is reset all patches will be applied sequentially
	// patches should always be repeatable without causing problems
	// when a new image is created the patch level will always be set to zero, the following code should also be reviewed
	if ($redis->get('buildversion') === 'janui-20180805') {
		// only applicable for a specific build
		// final update for this build - move to a new buildversion
		$count = sysCmd("cat /srv/http/db/redis_datastore_setup | grep -c -i 'janui-20180805'");
		if ($count[0] == 0) {
			// the new version of /srv/http/db/redis_datastore_setup has been delivered via a git pull so use it
			// carry out/repeat all previous 'janui-20180805' updates first
			sysCmd('cp /var/www/app/config/defaults/nginx-prod.conf /etc/nginx/nginx-prod.conf');
			sysCmd('rm -f /etc/nginx/nginx.conf');
			sysCmd('ln -s /etc/nginx/nginx-prod.conf /etc/nginx/nginx.conf');
			sysCmd('cp /var/www/app/config/defaults/start_chromium.sh /etc/X11/xinit/start_chromium.sh');
			sysCmd('cp /var/www/app/config/defaults/udevil.service /usr/lib/systemd/system/udevil.service');
			sysCmd('cp /var/www/app/config/defaults/50x.html /etc/nginx/html/50x.html');
			$redis->del('acards');
			sysCmd('php -f /srv/http/db/redis_acards_details');
			// check the variables in new version of redis_datastore_setup
			sysCmd('php -f /srv/http/db/redis_datastore_setup check');
			sysCmd('systemctl disable php-fpm');
			// set file protections and ownership
			wrk_sysAcl();
			// set the patch level to 0 and set the next valid build version
			$redis->set('patchlevel', 0);
			$redis->set('buildversion', 'janui-20180903');
		}
		unset($count);
	}
	if ($redis->get('buildversion') === 'janui-20180903') {
		// only applicable for a specific build
		if ($redis->get('patchlevel') == 0) {
			// 1st update - modify /etc/chrony.conf, a new version of chrony.conf is delivered via git pull, don't wait for it, just do the update
			// disable initstepslew, this is already done due to the iburst parameter on the servers and pool in combination with makestep
			sysCmd("sed -i 's/^initstepslew.*/! initstepslew 30 0.pool.ntp.org 1.pool.ntp.org 2.pool.ntp.org/' /etc/chrony.conf");
			// enable logging of time changes of over 20 seconds
			sysCmd("sed -i 's/^! logchange.*/logchange 20/' /etc/chrony.conf");
			// set the patch level
			$redis->set('patchlevel', 1);
		}
		if ($redis->get('patchlevel') == 1) {
			// 2nd update - settings for the justboom dac - run the new version of /srv/http/db/redis_acards_details after it has been delivered by git pull
			// count then number of lines with the active justboom dac string in /srv/http/db/redis_acards_details
			$count = sysCmd("grep 'snd_rpi_justboom_dac' /srv/http/db/redis_acards_details | grep -c '^\$redis->hSet('");
			if ($count[0] == 1) {
				// the just boom dac entry is active in the file, so run the script
				sysCmd("/srv/http/db/redis_acards_details");
				// set the patch level
				$redis->set('patchlevel', 2);
			}
			unset($count);
		}
		if ($redis->get('patchlevel') == 2) {
			// 3rd update - copy a new version of avahi-daemon.conf to /etc/avahi/ after it is delivered by git pull
			// check that the file exists in /var/www/app/config/defaults/
			if (file_exists('/var/www/app/config/defaults/avahi-daemon.conf')) {
				// the file is there, copy it and set the correct protection
				sysCmd("cp /var/www/app/config/defaults/avahi-daemon.conf /etc/avahi/avahi-daemon.conf");
				sysCmd("chmod 644 /etc/avahi/avahi-daemon.conf");
				// restart avahi
				sysCmd("systemctl daemon-reload");
				sysCmd("systemctl restart avahi-daemon");
				// clean up the pacnew version of the config file is it exists
				if (file_exists('/etc/avahi/avahi-daemon.conf.pacnew')) {
					sysCmd("rm -f /etc/avahi/avahi-daemon.conf.pacnew");
				}
				// set the patch level
				$redis->set('patchlevel', 3);
			}
		}
		if ($redis->get('patchlevel') == 3) {
			// 4th update - various updates
			// clean up incorrectly set redis variable
			$redis->exists('ahuffle_start_delay') && $redis->del('ahuffle_start_delay');
			// switch to systemd_time - new default
			sysCmd("/srv/http/command/switch_systemd_time_on.sh");
			// set the patch level
			$redis->set('patchlevel', 4);
		}
		if ($redis->get('patchlevel') == 4) {
			// 5th update - install lirc for the Pi 1 image
			$retval = sysCmd("uname -m");
			if ($retval[0] == 'armv6l') {
				sysCmd("pacman -Syy lirc --noconfirm");
			}
			unset($retval);
			sysCmd("cp /var/www/app/config/defaults/irexec.service /usr/lib/systemd/system/irexec.service");
			sysCmd("cp /var/www/app/config/defaults/lirc_options.conf /etc/lirc/lirc_options.conf");
			sysCmd("cp /var/www/app/config/defaults/lircd.conf /etc/conf.d/lircd.conf");
			wrk_sysAcl();
			// set the patch level
			$redis->set('patchlevel', 5);
		}
		if ($redis->get('patchlevel') == 5) {
			// 6th update - reassign local browser redis variables & initialise a few new redis variables
			// activate fix for Allo Piano 2.0 & activate some shairport-sync options
			// install raspi-rotate
			$retval = sysCmd("grep -c 'Allo Piano 2.0' /srv/http/app/templates/settings.php");
			if ($retval[0] != 0) {
				$local_browser = $redis->get('local_browser');
				$redis->del('local_browser');
				$redis->hSet('local_browser', 'enable', $local_browser);
				if (file_exists('/usr/bin/xinit')) {
					$zoomfactor = sysCmd("grep -i 'force-device-scale-factor=' /etc/X11/xinit/start_chromium.sh | cut -d'=' -f3");
					$redis->hSet('local_browser', 'zoomfactor', $zoomfactor[0]);
					unset($zoomfactor);
				}
				sysCmd("/srv/http/db/redis_datastore_setup check");
				sysCmd("/srv/http/db/redis_acards_details");
				sysCmd("cp /var/www/app/config/defaults/shairport-sync.conf /etc/shairport-sync.conf");
				sysCmd("chmod 644 /etc/shairport-sync.conf");
				sysCmd("/srv/http/command/raspi-rotate-install.sh");
				wrk_sysAcl();
				// set the patch level
				$redis->set('patchlevel', 6);
			}
			unset($retval);
		}
		if ($redis->get('patchlevel') == 6) {
			// 7th update - set a value to the redis variable i2smodule_select
			if (file_exists('/var/www/app/config/defaults/i2s_table.txt')) {
				$retval = $redis->get('i2smodule');
				$retval = sysCmd("grep -i '".$retval."' /var/www/app/config/defaults/i2s_table.txt");
				$redis->set('i2smodule_select', $retval[0]);
				unset($retval);
				// set the patch level
				$redis->set('patchlevel', 7);
			}
		}
		if ($redis->get('patchlevel') == 7) {
			// 8th update - create a xbindkeys configuration file called /root/.xbindkeysrc
			// if it already exists don't create it
			// on the Pi1 image it will never be used, no problem if it fails
			sysCmd('xbindkeys || xbindkeys --defaults > /root/.xbindkeysrc');
			// set the patch level
			$redis->set('patchlevel', 8);
		}
		if ($redis->get('patchlevel') == 8) {
			// 9th update - lots of small changes since the last patch level increment
			// just increment the patch level, no other actions, do it only when /srv/http/db/redis_datastore_setup has been updated
			$retval = sysCmd("grep -i 'AccessPoint' /srv/http/db/redis_datastore_setup | grep -i 'enabled' | grep -i 'redis' | grep -c -i 'hDel'");
			if ($retval[0] == 1) {
				// set the patch level
				$redis->set('patchlevel', 9);
			}
			unset($retval);
		}
		if ($redis->get('patchlevel') == 9) {
			// 10th update - change the MPD Log level to 'default' and replace the exitsing /usr/lib/systemd/system/rune_SY_wrk.service with the latest version
			// change the redis value when /srv/http/db/redis_datastore_setup has been updated
			$retval = sysCmd("grep -i 'mpdconf' /srv/http/db/redis_datastore_setup | grep -i 'log_level' | grep -i 'redis' | grep -c -i 'default'");
			if ($retval[0] == 2) {
				// set the actual redis variable mpdconf log_level to 'default'
				$redis->hSet('mpdconf', 'log_level', 'default');
				// copy the latest version of /usr/lib/systemd/system/rune_SY_wrk.service and set the correct file owner and protection
				sysCmd('cp /var/www/app/config/defaults/rune_SY_wrk.service /usr/lib/systemd/system/rune_SY_wrk.service');
				sysCmd('chown root.root /usr/lib/systemd/system/rune_SY_wrk.service');
				sysCmd('chmod 644 /usr/lib/systemd/system/rune_SY_wrk.service');
				// clear the refresh ao lock
				$redis->set('lock_refresh_ao', 0);
				// delete incorrect redis variable
				$redis->del('globalrandom_lock');
				// set the patch level
				$redis->set('patchlevel', 10);
			}
			unset($retval);
		}
		//
		// if ($redis->get('patchlevel') == x) {
			// // xth update - install runeaudio.cron in /etc/cron.d/ after it is delivered by git pull
			// // check that the file exists in /var/www/app/config/defaults/
			// if (file_exists('/var/www/app/config/defaults/runeaudio.cron')) {
				// // the file is there, copy it and set the correct protection
				// sysCmd("cp /var/www/app/config/defaults/runeaudio.cron /etc/cron.d/runeaudio.cron");
				// sysCmd("chown root.root /etc/avahi/runeaudio.cron");
				// sysCmd("chmod 644 /etc/avahi/runeaudio.cron");
				// // clean up any cron avahi jobs owned by root
				// if (file_exists('/var/spool/cron/crontabs/root')) {
					// sysCmd("sed -i '/systemctl restart avahi-daemon/d' /var/spool/cron/crontabs/root");
				// }
				// // set the patch level
				// $redis->set('patchlevel', x);
			// }
		//
		// template for the update part replace x with the number
		//if ($redis->get('patchlevel') < x) {
			// xth update
			//
			// set the patch level
			//$redis->set('patchlevel', x);
		//}
	}
}
