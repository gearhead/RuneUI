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
        if ($redis->get('patchlevel') == 0) {
            // 1st update
            $redis->set('patchlevel', 1);
            ui_notify($redis, 'Post update processing', 'Patchlevel 1');
        }
        if ($redis->get('patchlevel') == 1) {
            // 2nd update
            $redis->set('patchlevel', 2);
            ui_notify($redis, 'Post update processing', 'Patchlevel 2');
            sysCmd("sed -i '/^success_rootexec/s/.*/success_rootexec = \/var\/www\/command\/usbmount asyncnow/' '/etc/udevil/udevil.conf'");
        }
        if ($redis->get('patchlevel') == 2) {
            // 3rd update
            $redis->set('patchlevel', 3);
            ui_notify($redis, 'Post update processing', 'Patchlevel 3');
            sysCmd("sed -i '/^success_rootexec/s/.*/success_rootexec = \/var\/www\/command\/usbmount/' '/etc/udevil/udevil.conf'");
        }
        if ($redis->get('patchlevel') == 3) {
            // 4th update
            $redis->set('patchlevel', 4);
            ui_notify($redis, 'Post update processing', 'Patchlevel 4');
            sysCmd('cp /srv/http/app/config/defaults/etc/tmpfiles.d/smb.conf /etc/tmpfiles.d/smb.conf');
            sysCmd('cp /srv/http/app/config/defaults/etc/samba/smb-dev.conf /etc/samba/smb-dev.conf');
            sysCmd('cp /srv/http/app/config/defaults/etc/samba/smb-prod.conf /etc/samba/smb-prod.conf');
            if ($redis->get('os') == 'RPiOS') {
                sysCmd('apt install -y wsdd');
            } else if ($redis->get('os') == 'ARCH') {
                sysCmd('pacman -Sy wsdd --noconfirm');
            }
            sysCmd('systemctl daemon-reload ; systemctl disable wsdd ; systemctl stop wsdd');
            wrk_restartSamba($redis);
        }
        if ($redis->get('patchlevel') == 4) {
            // 5th update
            $redis->set('patchlevel', 5);
            ui_notify($redis, 'Post update processing', 'Patchlevel 5');
        }
        if ($redis->get('patchlevel') == 5) {
            // 6th update
            ui_notify($redis, 'Post update processing', 'Patchlevel 6');
            // delete the Spotify Connect username and password
            $spotifyConnect = $redis->hSet('spotifyconnect', 'username', '');
            $spotifyConnect = $redis->hSet('spotifyconnect', 'password', '');
            // process the changes
            wrk_spotifyd($redis);
            $redis->set('patchlevel', 6);
            // if Spotify Connect is enabled, reboot
            if ($redis->hGet('spotifyconnect', 'enable')) {
                ui_notify($redis, 'Post update processing', 'Reboot required, rebooting');
                sysCmd('/srv/http/command/rune_shutdown reboot ; redis-cli shutdown save ; systemctl stop redis ; shutdown now --reboot --no-wall');
            }
        }
        // if ($redis->get('patchlevel') == 6) {
            // // 7th update
            // $redis->set('patchlevel', 7);
            // ui_notify($redis, 'Post update processing', 'Patchlevel 7');
        // }
    }
}
