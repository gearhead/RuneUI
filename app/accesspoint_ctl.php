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
 *  file: app/accesspoint_ctl.php
 *  version: 1.3
 *  coder: Frank Friedmann (aka hondagx35)
 *
 */

// inspect POST
if (isset($_POST)) {
    if (isset($_POST['reset'])) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'apcfg', 'action' => 'reset', 'args' => $_POST['settings']));
    }
    if (isset($_POST['save'])) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'apcfg', 'action' => 'config', 'args' => $_POST['settings']));
    }
}

waitSyWrk($redis,$jobID);

$template->enable = $redis->hGet('AccessPoint', 'enable');
$template->accesspoint = $redis->hGetAll('AccessPoint');
$template->hostname = $redis->get('hostname');
$nics = json_decode($redis->Get('network_interfaces'), true);
$template->wifiavailable = 0;
$template->wififeatureAP = 0;
$template->wififullfunction = 0;
foreach ($nics as $nic) {
    if ($nic['technology'] == 'wifi') {
        $template->wifiavailable = 1;
        if ($nic['apSupported']) {
            $template->wififeatureAP = 1;
        }
        if ($nic['apFull']) {
            $template->wififullfunction = 1;
        }
    }
}
unset($nics, $nic);
