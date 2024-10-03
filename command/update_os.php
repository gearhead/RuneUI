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
    if ($redis->get('buildversion') === 'janui-20231210') {
        // this is 0.6b, only applicable for a specific build
        ui_notify($redis, 'Post update processing', 'Startup may take longer than  mormal!');
        // if ($redis->get('patchlevel') == 0) {
            // // 1st update
            // $redis->set('patchlevel', 1);
            // ui_notify($redis, 'Post update processing', 'Patchlevel 1');
        // }
    } else if (($redis->get('buildversion') === 'janui-20230805') || ($redis->get('buildversion') === 'janui-20230823')) {
        // this is 0.6a, only applicable for a specific build
        ui_notify($redis, 'Post update processing', 'Startup may take longer than  mormal!');
        if ($redis->get('patchlevel') == 0) {
            // 1st update
            //  clean up erroneous lyrics
            sysCmd('rm /srv/http/tmp/art/*.mpd ; rm /srv/http/tmp/art/*.song');
            sysCmd('rm /srv/http/tmp/upper/*.mpd ; rm /srv/http/tmp/upper/*.song');
            sysCmd('rm /home/cache/art/*.mpd ; rm /home/cache/art/*.song');
            sysCmd('echo 3 > /proc/sys/vm/drop_caches ; mount -o remount overlay_art_cache');
            sleep(2);
            sysCmd('echo 3 > /proc/sys/vm/drop_caches ; mount -o remount overlay_art_cache');
            //  missing packages for RPiOS
            if ($redis->get('os') == 'RPiOS') {
                sysCmd('apt install -y firmware-linux-free');
                sysCmd('apt install -y firmware-linux-nonfree');
                sysCmd('apt -y autoremove ; apt -y autoclean');
            }
            //  delete acards to force its regeneration
            $redis->del('acards');
            //  set the patch level
            $redis->set('patchlevel', 1);
            ui_notify($redis, 'Post update processing', 'Patchlevel 1');
        }
        if ($redis->get('patchlevel') == 1) {
            // 2nd update
            sysCmd('cp /srv/http/app/config/defaults/etc/systemd/system/amixer-webui.service /etc/systemd/system/amixer-webui.service');
            sysCmd('cp /srv/http/app/config/defaults/srv/http/.config/i2s_table.txt /srv/http/.config/i2s_table.txt');
            sysCmd('/srv/http/command/convert_dos_files_to_unix_script.sh');
            wrk_systemd_unit($redis, 'daemon-reload');
            $pythonPlugin = sysCmd('find /usr/lib -name python_plugin.so | wc -w | xargs')[0];
            $python3Plugin = sysCmd('find /usr/lib -name python3_plugin.so | wc -w | xargs')[0];
            if (($pythonPlugin == 1) && ($python3Plugin != 1)) {
                sysCmd("sed -i '/^plugins = python/c\plugins = python' /srv/http/amixer/amixer-webui.ini");
            } else if (($pythonPlugin != 1) && ($python3Plugin == 1)) {
                sysCmd("sed -i '/^plugins = python/c\plugins = python3' /srv/http/amixer/amixer-webui.ini");
            }
            wrk_systemd_unit($redis, 'reload-or-restart', 'amixer-webui');
            $redis->set('patchlevel', 2);
            ui_notify($redis, 'Post update processing', 'Patchlevel 2');
        }
        if ($redis->get('patchlevel') == 2) {
            // 3rd update
            sysCmd('cp /srv/http/app/config/defaults/etc/systemd/system/amixer-webui.service /etc/systemd/system/amixer-webui.service');
            sysCmd('cp /srv/http/app/config/defaults/srv/http/.config/i2s_table.txt /srv/http/.config/i2s_table.txt');
            sysCmd('/srv/http/command/convert_dos_files_to_unix_script.sh');
            wrk_systemd_unit($redis, 'daemon-reload');
            $pythonPlugin = sysCmd('find /usr/lib -name python_plugin.so | wc -w | xargs')[0];
            $python3Plugin = sysCmd('find /usr/lib -name python3_plugin.so | wc -w | xargs')[0];
            if (($pythonPlugin == 1) && ($python3Plugin != 1)) {
                sysCmd("sed -i '/^plugins = python/c\plugins = python' /srv/http/amixer/amixer-webui.ini");
            } else if (($pythonPlugin != 1) && ($python3Plugin == 1)) {
                sysCmd("sed -i '/^plugins = python/c\plugins = python3' /srv/http/amixer/amixer-webui.ini");
            }
            wrk_systemd_unit($redis, 'reload-or-restart', 'amixer-webui');
            $redis->hset('mpdconf', 'max_output_buffer_size', 32768);
            wrk_mpdconf($redis, 'refresh');
            $redis->set('patchlevel', 3);
            ui_notify($redis, 'Post update processing', 'Patchlevel 3');
        }
        if ($redis->get('patchlevel') == 3) {
            // 4th update
            sysCmd('cp /srv/http/app/config/defaults/srv/http/amixer/amixer-webui.ini /srv/http/amixer/amixer-webui.ini');
            $pythonPlugin = sysCmd('find /usr/lib -name python_plugin.so | wc -w | xargs')[0];
            $python3Plugin = sysCmd('find /usr/lib -name python3_plugin.so | wc -w | xargs')[0];
            if (($pythonPlugin == 1) && ($python3Plugin != 1)) {
                sysCmd("sed -i '/^plugins = python/c\plugins = python' /srv/http/amixer/amixer-webui.ini");
            } else if (($pythonPlugin != 1) && ($python3Plugin == 1)) {
                sysCmd("sed -i '/^plugins = python/c\plugins = python3' /srv/http/amixer/amixer-webui.ini");
            }
            $redis->set('patchlevel', 4);
            ui_notify($redis, 'Post update processing', 'Patchlevel 4');
        }
        if ($redis->get('patchlevel') == 4) {
            // 5th update
            // add --disable-pinch to chromium-flags
            if (!sysCmd('grep -ic -- "--disable-pinch" /srv/http/.config/chromium-flags.conf')[0]) {
                // --disable-pinch is not present
                $retval = sysCmd("sed -i 's/^#--process-per-tab/--disable-pinch\\n#--process-per-tab/' /srv/http/.config/chromium-flags.conf");
            }
            // make chromium the default browser and restart the browser if required
            $redis->hSet('local_browser', 'browser', 'chromium');
            wrk_localBrowser($redis, 'restart');
            // // fix for removing openresolv
            // $os = $redis->get('os');
            // if ($os == 'RPiOS') {
                // if (!sysCmd('apt -qq list openresolv 2> /dev/null | grep -ci installed')[0]) {
                    // sysCmd('apt install -y openresolv >/dev/null 2>&1');
                // }
                // sysCmd('rm -f /etc/resolv.conf ; resolvconf -u');
                // // sysCmd('apt purge -y openresolv >/dev/null 2>&1');
            // } else if ($os == 'ARCH') {
                // // removing openresolv and running resolvconf -u seem to do strange things on ARCH, don't do anything
                // sysCmd('pacman -Q openresolv || pacman -Sy openresolv --noconfirm');
                // sysCmd('rm -f /etc/resolv.conf ; pacman -Q openresolv && resolvconf -u');
                // sysCmd('pacman -Rsn openresolv --noconfirm');
            // }
            //
            $redis->set('patchlevel', 5);
            ui_notify($redis, 'Post update processing', 'Patchlevel 5');
        }
        if ($redis->get('patchlevel') == 5) {
            // 6th update
            // fix for installing openresolv, cron/cronie
            // fix for excessive logging, disable rsyslog
            $os = $redis->get('os');
            if ($os == 'RPiOS') {
                // // install openresolv if missing
                // if (!sysCmd('apt -qq list openresolv 2> /dev/null | grep -ci installed')[0]) {
                // // install openresolv if missing
                    // sysCmd('apt install -y openresolv >/dev/null 2>&1');
                // }
                // install cron if missing
                if (!sysCmd('apt -qq list cron 2> /dev/null | grep -ci installed')[0]) {
                // install openresolv if missing
                    sysCmd('apt install -y cron >/dev/null 2>&1');
                }
                // stop and disable cron
                wrk_systemd_unit($redis, 'disable_and_stop', 'cron');
                // stop and disable rsyslog
                wrk_systemd_unit($redis, 'disable_and_stop', 'rsyslog');
                // start and enable mosquitto
                wrk_systemd_unit($redis, 'enable_and_start', 'mosquitto');
            } else if ($os == 'ARCH') {
                // // install openresolv if missing
                // sysCmd('pacman -Q openresolv || pacman -Sy openresolv --noconfirm');
                // install cronie if missing
                sysCmd('pacman -Q cronie || pacman -Sy cronie --noconfirm');
                // stop and disable cronie
                wrk_systemd_unit($redis, 'disable_and_stop', 'cronie');
                // copy cronie daily file for logrotate
                sysCmd('cp /srv/http/app/config/defaults/logrotate/etc/cron.daily/logrotate /etc/cron.daily/logrotate');
            }
            $redis->set('patchlevel', 6);
            ui_notify($redis, 'Post update processing', 'Patchlevel 6');
        }
        if ($redis->get('patchlevel') == 6) {
            // 7th update
            // update redis datastore cipher exclude
            $redis->del('cipher_exclude_list');
            $redis->hDel('bluetooth', 'def_volume');
            $redis->hDel('bluetooth', 'IO_toggle');
            $redis->hDel('bluetooth', 'local_volume_control');
            $redis->hDel('bluetooth', 'native_volume_control');
            $redis->hDel('bluetooth', 'remember_last_volume');
            sysCmd('/srv/http/db/redis_datastore_setup check');
            // refresh the redis acards details
            sysCmd('/srv/http/db/redis_acards_details');
            // install the flac package for RPiOS if required
            if ($os == 'RPiOS') {
                if (!sysCmd('apt -qq list flac 2> /dev/null | grep -ci installed')[0]) {
                    // install flac if missing
                    sysCmd('apt install -y flac >/dev/null 2>&1');
                    sysCmd('apt --fix-broken -y install');
                }
                if (!sysCmd('apt -qq list flac 2> /dev/null | grep -ci installed')[0]) {
                    //  standard flac install failed, clean up and try using a different method
                    sysCmd('mkdir /home/flac ; rm /home/flac/*');
                    sysCmd('cd /home/flac ; wget https://archive.raspbian.org/raspbian/pool/main/libo/libogg/libogg0_1.3.2-1+b2_armhf.deb');
                    sysCmd('cd /home/flac ; wget https://archive.raspbian.org/raspbian/pool/main/f/flac/flac_1.3.3-2+deb11u2_armhf.deb');
                    sysCmd('cd /home/flac ; dpkg -i libogg0_1.3.2-1+b2_armhf.deb');
                    sysCmd('cd /home/flac ; dpkg -i flac_1.3.3-2+deb11u2_armhf.deb');
                    sysCmd('cd ~ ; rm -r /home/flac');
                }
                sysCmd('apt --fix-broken -y install');
                sysCmd('apt autoremove ; apt autoclean');
                // add the RPiOS codename to motd
                $codename = '-'.trim(sysCmd('grep -i VERSION_CODENAME /etc/os-release | cut -d "=" -f 2 | xargs')[0]);
                sysCmd("sed -i '/^Hw-env:/s/RPiOS)/RPiOS-".$codename.")/' /etc/motd");
            }
            // enable mpd to start automatically on boot
            wrk_systemd_unit($redis, 'enable', 'mpd.service');
            // disable the connman-wait-online and pcscd services to speed up startup
            wrk_systemd_unit($redis, 'disable', 'connman-wait-online.service');
            wrk_systemd_unit($redis, 'disable', 'pcscd.service');
            // disable and stop smartmontools and udisks2 services, these are not required
            wrk_systemd_unit($redis, 'disable_and_stop', 'smartmontools.service');
            wrk_systemd_unit($redis, 'disable_and_stop', 'udisks2.service');
            wrk_systemd_unit($redis, 'mask', 'udisks2.service');
            // when bluetooth is off disable and stop the hciuart service
            if (!$redis->get('bluetooth_on')) {
                wrk_systemd_unit($redis, 'disable_and_stop', 'hciuart');
            }
            wrk_systemd_unit($redis, 'daemon-reload');
            $redis->set('patchlevel', 7);
            ui_notify($redis, 'Post update processing', 'Patchlevel 7');
        }
        // if ($redis->get('patchlevel') == 7) {
            // // 8th update
            // $redis->set('patchlevel', 8);
            // ui_notify($redis, 'Post update processing', 'Patchlevel 8');
        // }
    }
}
