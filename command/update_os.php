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
    if (($redis->get('buildversion') === 'janui-20230805') || ($redis->get('buildversion') === 'janui-20230823')) {
        // only applicable for a specific build
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
        }
        if ($redis->get('patchlevel') == 1) {
            // 2nd update
            sysCmd('cp /srv/http/app/config/defaults/etc/systemd/system/amixer-webui.service /etc/systemd/system/amixer-webui.service');
            sysCmd('cp /srv/http/app/config/defaults/srv/http/.config/i2s_table.txt /srv/http/.config/i2s_table.txt');
            sysCmd('/srv/http/command/convert_dos_files_to_unix_script.sh');
            sysCmd('systemctl daemon-reload');
            $pythonPlugin = sysCmd('find /usr/lib -name python_plugin.so | wc -w | xargs')[0];
            $python3Plugin = sysCmd('find /usr/lib -name python3_plugin.so | wc -w | xargs')[0];
            if (($pythonPlugin == 1) && ($python3Plugin != 1)) {
                sysCmd("sed -i '/^plugins = python/c\plugins = python' /srv/http/amixer/amixer-webui.ini");
            } else if (($pythonPlugin != 1) && ($python3Plugin == 1)) {
                sysCmd("sed -i '/^plugins = python/c\plugins = python3' /srv/http/amixer/amixer-webui.ini");
            }
            sysCmd('systemctl restart amixer-webui');
            $redis->set('patchlevel', 2);
        }
        if ($redis->get('patchlevel') == 2) {
            // 2nd update addendum, remove this when adding the 3rd update
            sysCmd('cp /srv/http/app/config/defaults/etc/systemd/system/amixer-webui.service /etc/systemd/system/amixer-webui.service');
            sysCmd('cp /srv/http/app/config/defaults/srv/http/.config/i2s_table.txt /srv/http/.config/i2s_table.txt');
            sysCmd('/srv/http/command/convert_dos_files_to_unix_script.sh');
            sysCmd('systemctl daemon-reload');
            $pythonPlugin = sysCmd('find /usr/lib -name python_plugin.so | wc -w | xargs')[0];
            $python3Plugin = sysCmd('find /usr/lib -name python3_plugin.so | wc -w | xargs')[0];
            if (($pythonPlugin == 1) && ($python3Plugin != 1)) {
                sysCmd("sed -i '/^plugins = python/c\plugins = python' /srv/http/amixer/amixer-webui.ini");
            } else if (($pythonPlugin != 1) && ($python3Plugin == 1)) {
                sysCmd("sed -i '/^plugins = python/c\plugins = python3' /srv/http/amixer/amixer-webui.ini");
            }
            sysCmd('systemctl restart amixer-webui');
            $redis->hset('mpdconf', 'max_output_buffer_size', 32768);
            wrk_mpdconf($redis, 'refresh');
            $redis->set('patchlevel', 3);
        }
        // if ($redis->get('patchlevel') == 2) {
            // // 3rd update
            // $redis->set('patchlevel', 2);
        // }
    }
}
