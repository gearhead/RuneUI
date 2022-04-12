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
 *  file: app/bluetooth_ctl.php
 *  version: 1.3
 *  coder: janui
 *  date: April 2022
 *
 */

// inspect POST
if (isset($_POST)) {
    if (isset($_POST['try'])) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'btcfg', 'action' => $_POST['try']));
    }
    if (isset($_POST['disconnect'])) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'btcfg', 'action' => $_POST['disconnect']));
    }
    if (isset($_POST['output_list'])) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'btcfg', 'action' => $_POST['output_list']));
    }
    if (isset($_POST['output_connect'])) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'btcfg', 'action' => 'output_connect', 'args' => $_POST['output_connect']));
    }
    if (isset($_POST['input_connect'])) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'btcfg', 'action' => $_POST['input_connect']));
    }
}

waitSyWrk($redis,$jobID);

$template->hostname = $redis->get('hostname');
$template->enable = $redis->get('bluetooth_on');
$template->bluetooth = sysCmd('bluetoothctl list | grep -ic controller');
$template->connected = sysCmd('bluetoothctl list | grep -ic paired-devices');
