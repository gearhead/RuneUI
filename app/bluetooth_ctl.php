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
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'btcfg', 'action' => 'enable'));
    } else if (isset($_POST['reset'])) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'btcfg', 'action' => 'reset'));
    } else if (isset($_POST['clear'])) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'btcfg', 'action' => 'clear'));
    } else if (isset($_POST['input_connect'])) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'btcfg', 'action' => 'input_connect'));
    } else if (isset($_POST['output_list'])) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'btcfg', 'action' => 'output_list'));
    } else if (isset($_POST['connect'])) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'btcfg', 'action' => 'connect', 'args' => $_POST['connect']));
    } else if (isset($_POST['disconnect'])) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'btcfg', 'action' => 'disconnect', 'args' => $_POST['disconnect']));
    } else if (isset($_POST['trust'])) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'btcfg', 'action' => 'trust', 'args' => $_POST['trust']));
    } else if (isset($_POST['untrust'])) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'btcfg', 'action' => 'untrust', 'args' => $_POST['untrust']));
    } else if (isset($_POST['block'])) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'btcfg', 'action' => 'block', 'args' => $_POST['block']));
    } else if (isset($_POST['unblock'])) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'btcfg', 'action' => 'unblock', 'args' => $_POST['unblock']));
    } else if (isset($_POST['forget'])) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'btcfg', 'action' => 'forget', 'args' => $_POST['forget']));
    } else if (isset($_POST['save'])) {
        $bt_config = array();
        if (isset($_POST['bluetooth_quality']) && ($_POST['bluetooth_quality'] != $redis->hGet('bluetooth', 'quality'))) {
            $bt_config['quality'] = $_POST['bluetooth_quality'];
        }
        if (isset($_POST['bluetooth_samplerate']) && ($_POST['bluetooth_samplerate'] != $redis->hGet('bluetooth', 'samplerate'))) {
            $bt_config['samplerate'] = $_POST['bluetooth_samplerate'];
        }
        if (isset($_POST['bluetooth_def_volume']) && ($_POST['bluetooth_def_volume'] != $redis->hGet('bluetooth', 'def_volume'))) {
            $bt_config['def_volume'] = $_POST['bluetooth_def_volume'];
        }
        if (isset($_POST['bluetooth_timeout']) && ($_POST['bluetooth_timeout'] != $redis->hGet('bluetooth', 'timeout'))) {
            $bt_config['timeout'] = $_POST['bluetooth_timeout'];
        }
        $native_volume_control = $redis->hGet('bluetooth', 'native_volume_control');
        if (isset($_POST['bluetooth_native_volume_control'])) {
            if (($_POST['bluetooth_native_volume_control'] == '1') && !$native_volume_control) {
                $bt_config['native_volume_control'] = 1;
            } else if (($_POST['bluetooth_native_volume_control'] == '0') && $native_volume_control) {
                $bt_config['native_volume_control'] = 0;
            }
        } else if ($native_volume_control) {
            $bt_config['native_volume_control'] = 0;
        }
        $aptX_HD_codec = $redis->hGet('bluetooth', 'aptX_HD_codec');
        if (isset($_POST['bluetooth_aptX_HD_codec'])) {
            if (($_POST['bluetooth_aptX_HD_codec'] == '1') && !$aptX_HD_codec) {
                $bt_config['aptX_HD_codec'] = 1;
            } else if (($_POST['bluetooth_aptX_HD_codec'] == '0') && $aptX_HD_codec) {
                $bt_config['aptX_HD_codec'] = 0;
            }
        } else if ($aptX_HD_codec) {
            $bt_config['aptX_HD_codec'] = 0;
        }
        $FastStream_codec = $redis->hGet('bluetooth', 'FastStream_codec');
        if (isset($_POST['bluetooth_FastStream_codec'])) {
            if (($_POST['bluetooth_FastStream_codec'] == '1') && !$FastStream_codec) {
                $bt_config['FastStream_codec'] = 1;
            } else if (($_POST['bluetooth_FastStream_codec'] == '0') && $FastStream_codec) {
                $bt_config['FastStream_codec'] = 0;
            }
        } else if ($FastStream_codec) {
            $bt_config['FastStream_codec'] = 0;
        }
        $LDAC_codec = $redis->hGet('bluetooth', 'LDAC_codec');
        if (isset($_POST['bluetooth_LDAC_codec'])) {
            if (($_POST['bluetooth_LDAC_codec'] == '1') && !$LDAC_codec) {
                $bt_config['LDAC_codec'] = 1;
            } else if (($_POST['bluetooth_LDAC_codec'] == '0') && $LDAC_codec) {
                $bt_config['LDAC_codec'] = 0;
            }
        } else if ($LDAC_codec) {
            $bt_config['LDAC_codec'] = 0;
        }
        if (count($bt_config)) {
            $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'btcfg', 'action' => 'config', 'args' => json_encode($bt_config)));
        }
        unset($bt_config, $native_volume_control, $aptX_HD_codec, $FastStream_codec, $LDAC_codec);
    }
}

if (!$redis->get('bluetooth_on') || (sysCmd('systemctl is-active bluetooth')[0] == 'inactive')) {
    $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'btcfg', 'action' => 'enable'));
}

waitSyWrk($redis,$jobID);

$template->hostname = $redis->get('hostname');
$template->enable = $redis->get('bluetooth_on');
// testing for an active Blutooth controller seems to give a false negative on its first try, repeat it max 3 times with a half second delay
$template->bluetooth = false;
$cnt = 3;
while (!$template->bluetooth && ($cnt-- > 0)) {
    $template->bluetooth = sysCmd('timeout 5 bluetoothctl list | grep -ic "Controller " | xargs')[0];
    if (!$template->bluetooth) {
        // sleep half a second
        usleep(500000);
    }
}
$template->config = $redis->hgetall('bluetooth');
// remove the next lines after the next build
if (!isset($template->config['samplerate'])) {
    $redis->hSet('bluetooth', 'samplerate', '48000');
    $template->config['samplerate'] = '48000';
}
if (!isset($template->config['native_volume_control'])) {
    $redis->hSet('bluetooth', 'native_volume_control', 1);
    $template->config['native_volume_control'] = 1;
}
if (!isset($template->config['aptX_HD_codec'])) {
    $redis->hSet('bluetooth', 'aptX_HD_codec', 0);
    $template->config['aptX_HD_codec'] = 0;
}
if (!isset($template->config['FastStream_codec'])) {
    $redis->hSet('bluetooth', 'FastStream_codec', 0);
    $template->config['FastStream_codec'] = 0;
}
if (!isset($template->config['LDAC_codec'])) {
    $redis->hSet('bluetooth', 'LDAC_codec', 0);
    $template->config['LDAC_codec'] = 0;
}
// end remove
$template->devices = wrk_btcfg($redis, 'status');
$template->samplerate = wrk_btcfg($redis, 'status');
// get the quality options, these are stored in files '/etc/default/bluealsa.<name>'
//  name is not empty and is the name of the quality option
if (isset($template->config['quality_options'])) {
    $template->quality_options = json_decode($template->config['quality_options'], true);
} else {
    $template->quality_options = array();
}
