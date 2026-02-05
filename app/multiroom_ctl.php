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
 *  file: app/multiroom_ctl.php
 *  version: 1.3
 *  coder: janui
 *  date: April 2025
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
$templateData['multidevice'] = $redis->hGet('owntone', 'multidevice');
if ($templateData['multidevice']) {
    $acards = $redis->hgetall('acards');
    foreach ($acards as $acard) {
        $availableOutputs[] = json_decode($acard, true)['description'];
    }
    unset($acards, $acard);
}
$defaultVolume = $redis->hGet('owntone', 'default_volume');
$templateData['controls'] = array();
$controlOrder = $redis->hGet('owntone', 'MRorder');
if ($controlOrder) {
    $templateData['contolOrder'] = array();
    foreach (str_split($controlOrder) as $char) {
        if ($char == 'M') {
            $templateData['contolOrder'][] = 'master';
        } else if ($char == 'L') {
            $templateData['contolOrder'][] = 'local';
        } else if ($char == 'C') {
            $templateData['contolOrder'][] = 'client';
        }
    }
} else {
    $templateData['contolOrder'] = array('master', 'local', 'client');
}
$outputNames = $redis->hKeys('owntone_outputs');
if (isset($outputNames) && $outputNames ) {
    if (is_array($outputNames) && count($outputNames)) {
        asort($outputNames);
        foreach ($outputNames as $outputName) {
            if (!$redis->hExists('owntone_presets', $outputName)) {
                $preset = array();
                $preset['autoconnect'] = false;
                $preset['mute'] = 0;
                $preset['volume_preset'] = $defaultVolume;
                $preset['pin'] = '';
            } else {
                $preset = json_decode($redis->hGet('owntone_presets', $outputName), true);
                // the next lines can be removed after the next release
                if (!isset($preset['pin'])) {
                    $preset['pin'] = '';
                    $redis->hSet('owntone_presets', $params['name'], json_encode($preset));
                }
                // when the pin has a value, set it to a dummy value for the UI
                if ($preset['pin']) {
                    $preset['pin'] = '******';
                }
            }
            $output = json_decode($redis->hGet('owntone_outputs', $outputName), true);
            // debug
            // if (strtolower($outputName) == 'pi4') {
                // $output['requires_auth'] = 1;
            // }
            // truncate the output type up to the first space - 'AirPlay 1' becomes 'AirPlay'
            $output['type'] = explode(' ', $output['type'])[0];
            if ($output['type'] == 'ALSA') {
                $classification = 'local';
                if ($templateData['multidevice']) {
                    if ($output['selected']) {
                        $templateData['controls'][$classification][$outputName] = array_merge($preset, $output);
                    } else if (in_array($output['name'], $availableOutputs)) {
                        $templateData['controls'][$classification][$outputName] = array_merge($preset, $output);
                    } else {
                        continue;
                    }
                } else {
                    if ($output['selected']) {
                        $templateData['controls'][$classification][$outputName] = array_merge($preset, $output);
                    } else {
                        continue;
                    }
                }
            } else {
                $classification = 'client';
                $templateData['controls'][$classification][$outputName] = array_merge($preset, $output);
            }
        }
    }
}
$templateData['master'] = json_decode($redis->hGet('owntone', 'master'), true);
$templateData['server'] = $redis->hGet('owntone', 'server');
$templateData['owntoneStreaming'] = $redis->hGet('owntone', 'streaming');
$templateData['status'] = '';
$serverHostname = $redis->hGet('owntone', 'server_hostname');
if ($serverHostname) {
    $templateData['status'] .= 'Server Hostname: '.$serverHostname;
}
if ($redis->hGet('owntone', 'enable')) {
    $templateData['status'] .= '; Server: Enabled';
} else {
    $templateData['status'] .= '; Server: Disabled';
}
if ($redis->hGet('owntone', 'active')) {
    $templateData['status'] .= ', Active';
} else {
    $templateData['status'] .= ', Inactive';
}
$role = $redis->hGet('owntone', 'role');
if ($role) {
    $templateData['status'] .= '; Role: '.ucfirst($role);
}
$server_player = json_decode($redis->hGet('owntone', 'server_player'), true);
if (isset($server_player['state']) && $server_player['state']) {
    $templateData['status'] .= '; State: '.ucfirst($server_player['state']);
}
//
$templateData['hostname'] = $redis->get('hostname');
