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
    if (isset($_POST['settings'])) {
        //ui_notify_async("'wrkcmd' => 'apcfg', 'action' => 'config'", $_POST['settings']);
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'apcfg', 'action' => 'config', 'args' => $_POST['settings']));        
    }
}

waitSyWrk($redis,$jobID);

$template->enabled = $redis->hGet('AccessPoint', 'enabled');
$template->accesspoint = $redis->hGetAll('AccessPoint');
$template->hostname = $redis->get('hostname');
exec('ifconfig -a', $phyinfo);
$template->wifiavailable = (preg_match_all("/^.*\wlan0:/m", implode("\n", $phyinfo))) ? 1 : 0;
exec('iw phy phy0 info', $phyinfo);
$template->wififullfunction = (preg_match_all("/^.*\interface combinations are not supported/m", implode("\n", $phyinfo))) ? 0 : 1;
exec('iw phy phy0 info | sed -n "/Supported interface modes:/,/:/p"', $phyinfo);
$template->wififeatureAP = (preg_match_all("/^.*\* AP$/m", implode("\n", $phyinfo))) ? 1 : 0;
