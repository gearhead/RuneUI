#!/usr/bin/php
<?php
/*
 * Copyright (C) 2013-2015 RuneAudio Team
 * http://www.runeaudio.com
 *
 * RuneUI
 * copyright (C) 2013-2015 - Andrea Coiutti (aka ACX) & Simone De Gregori (aka Orion)
 *
 * RuneOS
 * copyright (C) 2013-2015 - Simone De Gregori (aka Orion) & Carmelo San Giovanni (aka Um3ggh1U)
 *
 * RuneAudio website and logo
 * copyright (C) 2013-2015 - ACX webdesign (Andrea Coiutti)
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
 *  file: command/restore_wifi_profiles.php
 *  version: 0.6
 *  coder: janui
 *  date: May 2023
 */
//
// this script is part of the backup restore routines
// looks at the redis Wi-Fi stored profiles and creates profile files in /boot/wifi
// usage:
//  1. run this program to create profile files in /boot/wifi
//      existing files will not be replaced
//  2. restore the redis backup file
//      replaces all the redis variables
//  3. run this script to create profile files in /boot/wifi
//      existing files will not be replaced
//  4. reboot
//      on start-up the profile files in /boot/wifi will be activated and the profile files deleted
//
// initialisation
// report errors: set display_errors to true (=1)
ini_set('display_errors', '1');
// report all PHP errors: set error_reporting to -1
ini_set('error_reporting', -1);
// set the name of the error log file
ini_set('error_log', '/var/log/runeaudio/restore_wifi_profiles.log');
// common include
require_once('/srv/http/app/libs/runeaudio.php');
// Connect to Redis backend
require_once('/srv/http/app/libs/openredis.php');
// reset logfile
sysCmd('echo "--------------- start: restore_wifi_profiles.php ---------------" > /var/log/runeaudio/restore_wifi_profiles.log');
// logging starting message
runelog('WORKER restore_wifi_profiles.php STARTING...');
// define APP global
define('APP', '/srv/http/app/');
//
$storedProfiles = json_decode($redis->get('network_storedProfiles'), true);
for each ($storedProfiles as $storedProfile) {
    if (!isset($storedProfile['technology']) || ($storedProfile['technology'] != 'wifi')) {
        continue;
    }
    if (!isset($storedProfile['ssid']) || !isset($storedProfile['passphrase'])) {
        continue;
    }
    $profileFileName = '/boot/wifi/'.$storedProfile['ssid'].'profile';
    clearstatcache(true, $profileFileName);
    if (file_exists($profileFileName) {
        continue;
    }
    $profielFileContent = "Name=$storedProfile['ssid']\nPassphrase=$storedProfile['passphrase']\n";
    if (isset($storedProfile['hidden']) && $storedProfile['hidden']) {
        $profielFileContent .= "Hidden=true\n";
    } else {
        $profielFileContent .= "Hidden=false\n";
    }
    file_put_contents($profileFileName, $profielFileContent);
    unset($profileFileName, $profielFileContent);
}
unset($storedProfiles, $storedProfile);
//
// close Redis connection
$redis->close();
echo "Completed, $cnt redis keys registered in the reference list\n";
runelog('WORKER restore_wifi_profiles.php END...');
#---
#End script
