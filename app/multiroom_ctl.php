<?php
/*
 * Copyright (C) 2013 RuneAudio Team
 * http://www.runeaudio.com
 *
 * RuneUI
 * copyright (C) 2013 - Andrea Coiutti (aka ACX) & Simone De Gregori (aka Orion)
 *
 * RuneOS
 * copyright (C) 2013 - Carmelo San Giovanni (aka Um3ggh1U) & Simone De Gregori (aka Orion)
 *
 * RuneAudio website and logo
 * copyright (C) 2013 - ACX webdesign (Andrea Coiutti)
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
 *  file: app/debug_ctl.php
 *  version: 1.3
 *
 */
// inspect POST
if (isset($_POST)) {
    // if (isset($_POST['refresh'])) {
        // $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'debug-refresh'));
    // } else {
        // $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'debug'));
    // }
// } else {
    // $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'debug'));
}
if (isset($jobID)) {
    waitSyWrk($redis, $jobID);
}
$availableOutputs = array();
$template->multidevice = $redis->hGet('owntone', 'multidevice');
if ($template->multidevice) {
    $acards = $redis->hgetall('acards');
    foreach ($acards as $acard) {
        $availableOutputs[] = json_decode($acard, true)['description'];
    }
    unset($acards, $acard);
}
$defaultVolume = $redis->hGet('owntone', 'default_volume');
$template->controls = array();
$controlOrder = $redis->hGet('owntone', 'MRorder');
if ($controlOrder) {
    $template->contolOrder = array();
    foreach (str_split($controlOrder) as $char) {
        if ($char == 'M') {
            $template->contolOrder[] = 'master';
        } else if ($char == 'L') {
            $template->contolOrder[] = 'local';
        } else if ($char == 'C') {
            $template->contolOrder[] = 'client';
        }
    }
} else {
    $template->contolOrder = array('master', 'local', 'client');
}
$outputNames = $redis->hKeys('owntone_outputs');
if (isset($outputNames) && $outputNames ) {
    if (is_array($outputNames) && count($outputNames)) {
        asort($outputNames);
        foreach ($outputNames as $outputName) {
            if (!$redis->hExists('owntone_presets', $outputName)) {
                $preset = array();
                $preset['autoconnect'] = 0;
                $preset['mute'] = 0;
                $preset['volume_preset'] = $defaultVolume;
            } else {
                $preset = json_decode($redis->hGet('owntone_presets', $outputName), true);
            }
            $output = json_decode($redis->hGet('owntone_outputs', $outputName), true);
            // truncate the output type up to the first space - 'AirPlay 1' becomes 'AirPlay'
            $output['type'] = explode(' ', $output['type'])[0];
            if ($output['type'] == 'ALSA') {
                $classification = 'local';
                if ($template->multidevice) {
                    if ($output['selected']) {
                        $template->controls[$classification][$outputName] = array_merge($preset, $output);
                    } else if (in_array($output['name'], $availableOutputs)) {
                        $template->controls[$classification][$outputName] = array_merge($preset, $output);
                    } else {
                        continue;
                    }
                } else {
                    if ($output['selected']) {
                        $template->controls[$classification][$outputName] = array_merge($preset, $output);
                    } else {
                        continue;
                    }
                }
            } else {
                $classification = 'client';
                $template->controls[$classification][$outputName] = array_merge($preset, $output);
            }
        }
    }
}
$template->master = json_decode($redis->hGet('owntone', 'master'), true);
$template->server = $redis->hGet('owntone', 'server');
$template->status = '';
if ($redis->hGet('owntone', 'enable')) {
    $template->status .= 'Enabled';
} else {
    $template->status .= 'Disabled';
}
if ($redis->hGet('owntone', 'active')) {
    $template->status .= ', Active';
} else {
    $template->status .= ', Inactive';
}
$role = $redis->hGet('owntone', 'role');
if ($role) {
    $template->status .= ', Role: '.ucfirst($role);
}
if (isset($outputs['master']['state']) && $outputs['master']['state']) {
    $template->status .= ', State: '.ucfirst($outputs['master']['state']);
}
$serverHostname = $redis->hGet('owntone', 'server_hostname');
if ($serverHostname) {
    $template->status .= ', Server Hostname: '.$serverHostname;
}
//
$template->hostname = $redis->get('hostname');
