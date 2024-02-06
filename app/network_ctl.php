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
 *  file: app/network_ctl.php
 *  version: 1.3
 *  coder: Simone De Gregori
 *
 */

// inspect POST
if (isset($_POST)) {
    // valid netcfg action values:
    //    refresh, refreshAsync, saveWifi, saveEthernet, reconnect, connect,
    //    autoconnect-on, autoconnect-off, disconnect, disconnect-delete, delete & reset
    if (isset($_POST['refresh'])) {
        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'netcfg', 'action' => 'refresh'));
    }
    if (isset($_POST['profile']['action'])) {
        // debug
        // $redis->set($_POST['profile']['action'], json_encode($_POST['profile']));
        $jobID[] = wrk_control($redis, 'newjob', $data = array( 'wrkcmd' => 'netcfg', 'action' => $_POST['profile']['action'], 'args' => $_POST['profile']));
    }
    if (isset($_POST['apenable'])) {
        if ($_POST['apenable'] && !$redis->hGet('AccessPoint', 'enable')) {
            $redis->hSet('AccessPoint', 'enable', 1);
        } else if (!$_POST['apenable'] && $redis->hGet('AccessPoint', 'enable')) {
            $redis->hSet('AccessPoint', 'enable', 0);
        }
    }
    if (isset($_POST['btenable'])) {
        if ($_POST['btenable'] && !$redis->get('bluetooth_on')) {
            $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'btcfg', 'action' => 'enable'));
        } else if (!$_POST['btenable'] && $redis->get('bluetooth_on')) {
            $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'btcfg', 'action' => 'disable'));
        }
    }
    if (isset($_POST['wifienable'])) {
        if ($_POST['wifienable'] && !$redis->get('wifi_on')) {
            $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'netcfg', 'action' => 'enableWifi'));
        } else if (!$_POST['wifienable'] && $redis->get('wifi_on')) {
            $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'netcfg', 'action' => 'disableWifi'));
        }
    }
}
if (isset($jobID)) {
    waitSyWrk($redis, $jobID);
}

$template->hostname = $redis->get('hostname');
$template->network_autoOptimiseWifi = $redis->get('network_autoOptimiseWifi');

// retrieve the nics
$template->nics = json_decode($redis->get('network_interfaces'), true);
// retrieve the networks
$networks = json_decode($redis->get('network_info'), true);
// start an asynchronous job to refresh the network & nic info, don't wait wait for completion
wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'netcfg', 'action' => 'refreshAsync'));
//
if ($template->action === 'wifi_scan') {
    //
    // call from network.php > target template = network_wifi_scan.php
    // $template->arg contains the wifi nic
    //
    $template->networks = array();
    $template->networksFound = false;
    //
    foreach ($networks as $key => $network) {
        if ($network['nic'] != $template->arg) {
            continue;
        }
        if ($network['technology'] != 'wifi') {
            continue;
        }
        $template->networksFound = true;
        $template->macAddress = $network['macAddress'];
        foreach ($network as $entry => $value) {
            if (strpos(' |technology|nic|macAddress|ssidHex|connected|configured|security|ssid|strength|strengthStars|ready|online|', '|'.$entry.'|')) {
                $template->networks[$key][$entry] = $value;
            }
        }
    }
    // debug
    // $redis->set('wifi_scan', json_encode($template->networks));
    //
    // get the stored profiles if they exists
    $template->storedProfiles = array();
    $template->storedProfilesFound = false;
    if ($redis->exists('network_storedProfiles')) {
        $template->storedProfiles = json_decode($redis->get('network_storedProfiles'), true);
        foreach ($template->storedProfiles as $key => $profile) {
            $template->storedProfiles[$key]['online'] = false;
            $template->storedProfiles[$key]['ready'] = false;
            $template->storedProfilesFound = true;
        }
        foreach ($networks as $network) {
            if ($network['technology'] != 'wifi') {
                continue;
            }
            if (isset($network['ssid'])) {
                $ssidHexKey = 'ssidHex:'.trim(implode(unpack("H*", $network['ssid'])));
            } else {
                continue;
            }
            if (isset($template->storedProfiles[$ssidHexKey]['ssid'])) {
                $template->storedProfiles[$ssidHexKey]['security'] = strtoupper($network['security']);
                if ($network['online']) {
                    $template->storedProfiles[$ssidHexKey]['online'] = true;
                }
                if ($network['ready']) {
                    $template->storedProfiles[$ssidHexKey]['ready'] = true;
                }
            }
        }
    }
    // sort the network array into strength descending order
    osort($template->networks, 'strength', true);
    // clean up
    $template->profile = array();
    unset($networks, $storedProfiles);
    //
} else if ($template->action === 'wifi_edit') {
    //
    // call from network_wifi_scan.php > target template = network_wifi_edit.php
    // $template->arg contains the wifi mac address plus ssid-hex ('mac_ssid')
    //
    // build up the profile use the nic information then the network information and then the stored profile
    // set up some defaults
    $template->profile = array();
    $template->profile['connected'] = false;
    $template->profile['ipAssignment'] = 'DHCP';
    // get the nic information and add it to the profile
    // this supplies the ip information, masks, default gateway, dns
    list($macAddress, $ssidHex) = explode('_',$template->arg,2);
    $macAddress = (string) trim($macAddress);
    $macAddressKey = 'macAddress:'.$macAddress;
    $ssidHex = (string) trim($ssidHex);
    $ssidHexKey = 'ssidHex:'.$ssidHex;
    $first = true;
    foreach ($template->nics as $nic) {
        if (($nic['technology'] === 'wifi') && $first) {
            // use the first wifi profile as a default
            $template->profile = array_merge($template->profile, $nic);
            $first = false;
        }
        if ($nic['macAddress'] === $macAddress) {
            // if a match is found use it end exit the loop
            $template->profile = array_merge($template->profile, $nic);
            break;
        }
    }
    // add the network to the profile
    if (isset($networks[$template->arg])) {
        $template->profile = array_merge($template->profile, $networks[$template->arg]);
        $template->profile['manual'] = false;
    } else {
        // this an add without selecting a network (possibly a hidden ssid)
        // clear the information which should be provided by the network info
        $template->profile['manual'] = true;
        $template->profile['ssid'] = '';
        $template->profile['passphrase'] = '';
        $template->profile['ssidHex'] = '';
        $template->profile['autoconnect'] = false;
        $template->profile['ready'] = false;
        $template->profile['online'] = false;
        $template->profile['configured'] = false;
        $template->profile['security'] = 'PSK';
        $template->profile['connmanString'] = '';
        $template->profile['hidden'] = false;
    }
    // determine if this network is connected on another nic and store the nic
    $template->profile['cNic'] = '';
    if (($template->profile['configured']) && isset($ssidHex) && $ssidHex) {
        foreach ($networks as $network) {
            if (($network['ssidHex'] === $ssidHex) && ($network['ready'] || $network['online'])) {
                $template->profile['cNic'] = $network['nic'];
                break;
            }
        }
    }
    // get the stored profile if it exists ans add it to the profile
    if ($redis->exists('network_storedProfiles')) {
        $storedProfiles = json_decode($redis->get('network_storedProfiles'), true);
        if (isset($storedProfiles[$ssidHexKey])) {
            $template->profile = array_merge($template->profile, $storedProfiles[$ssidHexKey]);
            $template->profile['manual'] = false;
            $template->profile['configured'] = true;
        } else if (!$template->profile['configured']) {
            $template->profile['manual'] = true;
        }
    } else if (!$template->profile['configured']) {
        $template->profile['manual'] = true;
    }
    if ($template->profile['manual']) {
        // set the ipv4 address to a default based on the Default Gateway, replacing the last segment with 200
        $ipv4Address = explode('.', $template->profile['defaultGateway']);
        if (count($ipv4Address) === 4) {
            $ipv4Address[3] = '200';
            $template->profile['ipv4Address'] = join('.', $ipv4Address);
        } else {
            $template->profile['ipv4Address'] = '192.168.1.200';
        }
    }
    // never pass the passphrase the the UI
    $template->profile['passphrase'] = '';
    // clean up
    $template->networks = array();
    $template->storedProfiles = array();
    unset($first, $networks, $network, $storedProfiles, $macAddress, $ssidHex, $ssidHexKey, $ipv4Address);
    //
} else if ($template->action === 'ethernet_edit') {
    //
    // call from network_wifi_scan.php > target template = network_wifi_edit.php
    // $template->arg contains the ethernet nic
    //
    // build up the profile use the nic information and then the stored profile
    // set up some defaults
    $template->profile = array();
    $template->profile['ipAssignment'] = 'DHCP';
    // get the nic information and add it to the profile
    foreach ($networks as $network) {
        if ($network['nic'] === $template->arg) {
            $template->profile = array_merge($template->profile, $network);
            break;
        }
    }
    if (isset($template->nics[$template->arg])) {
        $template->profile = array_merge($template->profile, $template->nics[$template->arg]);
    }
    // get the stored profile if it exists and add it to the profile
    if ($redis->exists('network_storedProfiles')) {
        $storedProfiles = json_decode($redis->Get('network_storedProfiles'), true);
        if ((isset($template->profile['macAddress'])) && (isset($storedProfiles[$template->profile['macAddress']]))) {
            $macAddressKey = 'macAddress:'.$template->profile['macAddress'];
            $template->profile = array_merge($template->profile, $storedProfiles[$macAddressKey]);
        }
    }
    // clean up
    $template->networks = array();
    $template->storedProfiles = array();
    unset($networks, $storedProfiles);
    //
} else {
    //
    // call from menu > target template = network.php
    // no parameters
    //
    // reset the template parameters
    $template->action = '';
    $template->arg = '';
    $template->content = 'network';
    $template->networks = array();
    $template->storedProfiles = array();
    $template->profile = array();
    $interface = $redis->hGet('AccessPoint', 'interface');
    $wlanNic = $redis->hGet('AccessPoint', 'wlanNic');
    if (isset($interface) && isset($wlanNic) && ($interface != $wlanNic)) {
        $template->virtNic = $interface;
    } else {
        $template->virtNic = '';
    }
    $template->nat = $redis->hGet('AccessPoint', 'enable-NAT');
    $template->apenable = $redis->hGet('AccessPoint', 'enable');
    $template->wifienable = $redis->get('wifi_on');
    $template->btenable = $redis->get('bluetooth_on');
    $template->btstring = '';
    $btDevices = wrk_btcfg($redis, 'status');
    foreach ($btDevices as $btDevice) {
        if ($btDevice['source'] && $btDevice['connected']) {
            $template->btstring = '[Input: '.$btDevice['name'].'] ['.ucwords($btDevice['icon']).': '.$btDevice['device'].']';
            break;
        }
        if ($btDevice['sink'] && $btDevice['connected']) {
            $template->btstring = '[Output: '.$btDevice['name'].'] ['.ucwords($btDevice['icon']).': '.$btDevice['device'].']';
            break;
        }
    }
    $wired = false;
    $wifi = false;
    foreach ($template->nics as $nic) {
        if (($nic['technology'] === 'ethernet') && $nic['connected']) {
            // a connected wired connection is available
            $wired = true;
        }
        if (($nic['technology'] === 'wifi') && ($nic['type'] === 'managed') && $nic['connected']) {
            // a connected wifi connection is available
            $wifi = true;
        }
    }
    if ($wired && !$wifi) {
        // a wired connection is available and no Wi-Fi connection is available (maybe it is configured as an AP), so enable switching Wi-Fi on/off
        $template->wifiswitch = 1;
    } else{
        // disable switching Wi-Fi on/off
        $template->wifiswitch = 0;
    }
    unset($networks, $storedProfiles, $btDevices, $wired, $wifi, $interface, $wlanNic);
    // only the contents of $template->nics is used
}
