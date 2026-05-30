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
// pull from redis first and refresh in the background kg    
//        $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'netcfg', 'action' => 'refresh'));
        wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'netcfg', 'action' => 'refreshAsync'));
        $redis->setex('network_refreshing', 60, 1);
        header('Location: /network');
        exit;
    }
    if (isset($_POST['profile']['action'])) {
        // debug
        // $redis->set($_POST['profile']['action'], json_encode($_POST['profile']));
        $jobID[] = wrk_control($redis, 'newjob', $data = array( 'wrkcmd' => 'netcfg', 'action' => $_POST['profile']['action'], 'args' => $_POST['profile']));
    }
    if (isset($_POST['apenable'])) {
        if ($_POST['apenable'] && !$redis->hGet('AccessPoint', 'enable')) {
            $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'apcfg', 'action' => 'enable'));
        } else if (!$_POST['apenable'] && $redis->hGet('AccessPoint', 'enable')) {
            $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'apcfg', 'action' => 'disable'));
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
    if (isset($_POST['allwifienable'])) {
        if ($_POST['allwifienable'] && !$redis->get('allwifi_on')) {
            $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'netcfg', 'action' => 'enableAllWifi'));
        } else if (!$_POST['allwifienable'] && $redis->get('allwifi_on')) {
            $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'netcfg', 'action' => 'disableAllWifi'));
        }
    }
}
if (isset($jobID)) {
    waitSyWrk($redis, $jobID);
}

$templateData['hostname'] = $redis->get('hostname');
$templateData['network_autoOptimiseWifi'] = $redis->get('network_autoOptimiseWifi');

// retrieve the nics
$templateData['nics'] = json_decode($redis->get('network_interfaces'), true);
// retrieve the networks
$networks = json_decode($redis->get('network_info'), true);
// start an asynchronous job to refresh the network & nic info, don't wait wait for completion
// removed unconditional refresh wait kg
// wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'netcfg', 'action' => 'refreshAsync'));
//
if ($templateData['action'] === 'wifi_scan') {
    //
    // call from network.php > target template = network_wifi_scan.php
    // $templateData['arg'] contains the wifi nic
    //
    $templateData['networks'] = array();
    $templateData['networksFound'] = false;
    //
    foreach ($networks as $key => $network) {
        if ($network['nic'] != $templateData['arg']) {
            continue;
        }
        if ($network['technology'] != 'wifi') {
            continue;
        }
        $templateData['networksFound'] = true;
        $templateData['macAddress'] = $network['macAddress'];
        foreach ($network as $entry => $value) {
            if (strpos(' |technology|nic|macAddress|ssidHex|connected|configured|security|ssid|strength|strengthStars|ready|online|', '|'.$entry.'|')) {
                $templateData['networks'][$key][$entry] = $value;
            }
        }
    }
    // debug
    // $redis->set('wifi_scan', json_encode($templateData['networks']));
    //
    // get the stored profiles if they exists
    $templateData['storedProfiles'] = array();
    $templateData['storedProfilesFound'] = false;
    if ($redis->exists('network_storedProfiles')) {
        $templateData['storedProfiles'] = json_decode($redis->get('network_storedProfiles'), true);
        foreach ($templateData['storedProfiles'] as $key => $profile) {
            $templateData['storedProfiles'][$key]['online'] = false;
            $templateData['storedProfiles'][$key]['ready'] = false;
            $templateData['storedProfilesFound'] = true;
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
            if (isset($templateData['storedProfiles'][$ssidHexKey]['ssid'])) {
                $templateData['storedProfiles'][$ssidHexKey]['security'] = strtoupper($network['security']);
                if ($network['online']) {
                    $templateData['storedProfiles'][$ssidHexKey]['online'] = true;
                }
                if ($network['ready']) {
                    $templateData['storedProfiles'][$ssidHexKey]['ready'] = true;
                }
            }
        }
    }
    // sort the network array into strength descending order
    osort($templateData['networks'], 'strength', 1, 0);
    // clean up
    $templateData['profile'] = array();
    $templateData['wifi_on'] = $redis->get('wifi_on');
    $templateData['allwifi_on'] = $redis->get('allwifi_on');
    unset($networks, $storedProfiles);
    //
} else if ($templateData['action'] === 'wifi_edit') {
    //
    // call from network_wifi_scan.php > target template = network_wifi_edit.php
    // $templateData['arg'] contains the wifi mac address plus ssid-hex ('mac_ssid')
    //
    // build up the profile use the nic information then the network information and then the stored profile
    // set up some defaults
    $templateData['profile'] = array();
    $templateData['profile']['connected'] = false;
    $templateData['profile']['ipAssignment'] = 'DHCP';
    // get the nic information and add it to the profile
    // this supplies the ip information, masks, default gateway, dns
    list($macAddress, $ssidHex) = explode('_',$templateData['arg'],2);
    $macAddress = (string) trim($macAddress);
    $macAddressKey = 'macAddress:'.$macAddress;
    $ssidHex = (string) trim($ssidHex);
    $ssidHexKey = 'ssidHex:'.$ssidHex;
    $first = true;
    $templateData['connection'] = '';
    foreach ($templateData['nics'] as $nic) {
        if (($nic['technology'] === 'wifi') && $first) {
            // use the first wifi profile as a default
            $templateData['profile'] = array_merge($templateData['profile'], $nic);
            $first = false;
        }
        if ($nic['macAddress'] === $macAddress) {
            // if a match is found use it end exit the loop
            $templateData['profile'] = array_merge($templateData['profile'], $nic);
            // break;
        }
        if (($nic['technology'] == 'ethernet') && $nic['connected']) {
            $templateData['connection'] = 'ethernet';
        } else if (($nic['technology'] == 'wifi') && ($nic['type'] == 'managed') && $nic['connected'] && ($templateData['connection'] == '')) {
            $templateData['connection'] = 'wifi';
        } else if (($nic['technology'] == 'wifi') && ($nic['type'] == 'AP') && $nic['connected'] && ($templateData['connection'] != 'ethernet')) {
            $templateData['connection'] = 'AP';
        }
    }
    // add the network to the profile
    if (isset($networks[$templateData['arg']])) {
        $templateData['profile'] = array_merge($templateData['profile'], $networks[$templateData['arg']]);
        $templateData['profile']['manual'] = false;
    } else {
        // this an add without selecting a network (possibly a hidden ssid)
        // clear the information which should be provided by the network info
        $templateData['profile']['manual'] = true;
        $templateData['profile']['ssid'] = '';
        $templateData['profile']['passphrase'] = '';
        $templateData['profile']['ssidHex'] = '';
        $templateData['profile']['autoconnect'] = false;
        $templateData['profile']['ready'] = false;
        $templateData['profile']['online'] = false;
        $templateData['profile']['configured'] = false;
        $templateData['profile']['security'] = 'PSK';
        $templateData['profile']['connmanString'] = '';
        $templateData['profile']['hidden'] = false;
    }
    // determine if this network is connected on another nic and store the nic
    $templateData['profile']['cNic'] = '';
    if (($templateData['profile']['configured']) && isset($ssidHex) && $ssidHex) {
        foreach ($networks as $network) {
            if (($network['ssidHex'] === $ssidHex) && ($network['ready'] || $network['online'])) {
                $templateData['profile']['cNic'] = $network['nic'];
                break;
            }
        }
    }
    // get the stored profile if it exists ans add it to the profile
    if ($redis->exists('network_storedProfiles')) {
        $storedProfiles = json_decode($redis->get('network_storedProfiles'), true);
        if (isset($storedProfiles[$ssidHexKey])) {
            $templateData['profile'] = array_merge($templateData['profile'], $storedProfiles[$ssidHexKey]);
            $templateData['profile']['manual'] = false;
            $templateData['profile']['configured'] = true;
        } else if (!$templateData['profile']['configured']) {
            $templateData['profile']['manual'] = true;
        }
    } else if (!$templateData['profile']['configured']) {
        $templateData['profile']['manual'] = true;
    }
    if ($templateData['profile']['manual']) {
        // set the ipv4 address to a default based on the Default Gateway, replacing the last segment with 200
        $ipv4Address = explode('.', $templateData['profile']['defaultGateway']);
        if (count($ipv4Address) === 4) {
            $ipv4Address[3] = '200';
            $templateData['profile']['ipv4Address'] = join('.', $ipv4Address);
        } else {
            $templateData['profile']['ipv4Address'] = '192.168.1.200';
        }
    }
    // never pass the passphrase the the UI
    $templateData['profile']['passphrase'] = '';
    // clean up
    $templateData['networks'] = array();
    $templateData['storedProfiles'] = array();
    unset($first, $networks, $network, $storedProfiles, $macAddress, $ssidHex, $ssidHexKey, $ipv4Address);
    //
} else if ($templateData['action'] === 'ethernet_edit') {
    //
    // call from network_wifi_scan.php > target template = network_wifi_edit.php
    // $templateData['arg'] contains the ethernet nic
    //
    // build up the profile use the nic information and then the stored profile
    // set up some defaults
    $templateData['profile'] = array();
    $templateData['profile']['ipAssignment'] = 'DHCP';
    // get the nic information and add it to the profile
    foreach ($networks as $network) {
        if ($network['nic'] === $templateData['arg']) {
            $templateData['profile'] = array_merge($templateData['profile'], $network);
            break;
        }
    }
    if (isset($templateData['nics'][$templateData['arg']])) {
        $templateData['profile'] = array_merge($templateData['profile'], $templateData['nics'][$templateData['arg']]);
    }
    // get the stored profile if it exists and add it to the profile
    if ($redis->exists('network_storedProfiles')) {
        $storedProfiles = json_decode($redis->Get('network_storedProfiles'), true);
        if ((isset($templateData['profile']['macAddress'])) && (isset($storedProfiles[$templateData['profile']['macAddress']]))) {
            $macAddressKey = 'macAddress:'.$templateData['profile']['macAddress'];
            $templateData['profile'] = array_merge($templateData['profile'], $storedProfiles[$macAddressKey]);
        }
    }
    // clean up
    $templateData['networks'] = array();
    $templateData['storedProfiles'] = array();
    unset($networks, $storedProfiles);
    //
} else {
    //
    // call from menu > target template = network.php
    // no parameters
    //
    // reset the template parameters
    $templateData['action'] = '';
    $templateData['arg'] = '';
    $templateData['content'] = 'network';
    $templateData['networks'] = array();
    $templateData['storedProfiles'] = array();
    $templateData['profile'] = array();
    $interface = $redis->hGet('AccessPoint', 'interface');
    $wlanNic = $redis->hGet('AccessPoint', 'wlanNic');
    if (isset($interface) && isset($wlanNic) && ($interface != $wlanNic)) {
        $templateData['virtNic'] = $interface;
    } else {
        $templateData['virtNic'] = '';
    }
    $templateData['nat'] = $redis->hGet('AccessPoint', 'enable-NAT');
    $templateData['apenable'] = $redis->hGet('AccessPoint', 'enable');
    $templateData['wifienable'] = $redis->get('wifi_on');
    $templateData['allwifienable'] = $redis->get('allwifi_on');
    $templateData['btenable'] = $redis->get('bluetooth_on');
    $templateData['btstring'] = '';
    $btDevices = wrk_btcfg($redis, 'status');
    foreach ($btDevices as $btDevice) {
        if ($btDevice['source'] && $btDevice['connected']) {
            $templateData['btstring'] = '[Input: '.$btDevice['name'].'] ['.ucwords($btDevice['icon']).': '.$btDevice['device'].']';
            break;
        }
        if ($btDevice['sink'] && $btDevice['connected']) {
            $templateData['btstring'] = '[Output: '.$btDevice['name'].'] ['.ucwords($btDevice['icon']).': '.$btDevice['device'].']';
            break;
        }
    }
    $wired = 0;
    $wifi = 0;
    $wifiConnected = 0;
    $apSupp = 0;
    $apUp = 0;
    foreach ($templateData['nics'] as $nic) {
        if (($nic['technology'] === 'ethernet') && $nic['connected']) {
            // a wired nic is connected
            $wired++;
        }
        if (($nic['technology'] === 'wifi') && ($nic['type'] === 'managed')) {
            // a wifi nic is available
            $wifi++;
            if ($nic['connected']) {
                // a Wi-Fi nic is connected
                $wifiConnected++;
            }
        }
        if (($nic['technology'] === 'wifi') && ($nic['type'] === 'managed') && $nic['apSupported']) {
            // an access point supported Wi-Fi nic is available
            $apSupp++;
        }
        if (($nic['technology'] === 'wifi') && ($nic['type'] === 'AP')) {
            // an access point is up
            $apUp++;
        }
    }
    if (!$templateData['allwifienable'] || ($wired && sysCmd('lsusb -v | grep -i iProduct | grep -ic "802.11" | xargs')[0])) {
        // All Wi-Fi is switched off or a wifi dongle is connected and a wired connection is active
        $templateData['allwifiswitch'] = 1;
    } else {
        // disable switching Wi-Fi on/off
        $templateData['allwifiswitch'] = 0;
    }
    if ($templateData['allwifienable'] && (!$templateData['wifienable'] || ($wifiConnected > 1) || ($wired && $wifi))) {
        // All Wi-Fi is switched on and on board Wi-Fi is switched off or more than one Wi-Fi nics are connected or
        //  a wired nic is connected and a Wi-Fi nic is connected
        //  (it could be configured as an AP), enable switching Wi-Fi on/off
        $templateData['wifiswitch'] = 1;
    } else {
        // disable switching Wi-Fi on/off
        $templateData['wifiswitch'] = 0;
    }
    if ($templateData['allwifienable'] && (!$templateData['apenable'] || (($templateData['allwifienable'] || $templateData['wifienable']) && $apSupp && ($wifiConnected || $wired)))) {
        // All Wi-Fi is switched on and AP is switched off or Wi-Fi is switched on and an access point supported and a Wi-Fi nic or an Ethernet nic is connected,
        //  enable switching AP on/off
        $templateData['apswitch'] = 1;
    } else {
        // disable switching Wi-Fi on/off
        $templateData['apswitch'] = 0;
    }
    // is processing? this enables/disables the visibility of the nics in the UI
// revised to speed this up kg    
//    if ($apUp || $redis->hGet('AccessPoint', 'interface')) {
//        // access point is up so always show the nics in the UI
//        $templateData['processing'] = 0;
//    } else if (!$wired && !$wifiConnected) {
//        // nothing is connected, but this routine cannot run unless a nic is connected, so it is processing
//        $templateData['processing'] = 1;
//    } else {
//        // when the lock_wifiscan is set it is processing
//        $templateData['processing'] = $redis->Get('lock_wifiscan');
//    }
    if ($apUp || $redis->hGet('AccessPoint', 'interface')) {
        $templateData['processing'] = 0;
    } else if ($redis->exists('network_refreshing') && $redis->Get('lock_wifiscan')) {
        $templateData['processing'] = 1;
    } else if ($redis->exists('network_refreshing') && !$redis->Get('lock_wifiscan')) {
        $redis->del('network_refreshing');
        $templateData['processing'] = 0;
    } else if (!$wired && !$wifiConnected) {
        $templateData['processing'] = 1;
    } else {
        $templateData['processing'] = $redis->Get('lock_wifiscan');
    }
    $templateData['refreshing'] = (bool) $templateData['processing'];
    
    unset($networks, $storedProfiles, $btDevices, $wired, $wifi, $interface, $wlanNic);
    // only the contents of $templateData['nics'] is used
}
