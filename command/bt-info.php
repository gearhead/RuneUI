#!/usr/bin/php
<?php
// script which runs when a BT device is 'connected' to the systemctl
// bluetoothctl connect MAC will cause this to run as it is triggerd by
// udev rule 60-bluetooth.rules
// Keith Grider 12/2020

// common include
ini_set('display_errors', '1');
// ini_set('error_reporting', -1);
ini_set('error_log', '/var/log/runeaudio/refresh_bt.log');
// Connect to Redis backend
$redis = new Redis();
$redis->connect('/run/redis/socket');
require_once('/srv/http/app/libs/runeaudio.php');

// interesting BT profie UUIDs:
// bluetoothctl info
//        UUID: Serial Port               (00001101-0000-1000-8000-00805f9b34fb)
//        UUID: Headset                   (00001108-0000-1000-8000-00805f9b34fb)
//        UUID: Audio Source              (0000110a-0000-1000-8000-00805f9b34fb)
//        UUID: Audio Sink                (0000110b-0000-1000-8000-00805f9b34fb)
//        UUID: A/V Remote Control Target (0000110c-0000-1000-8000-00805f9b34fb)
//        UUID: Advanced Audio Distribu.. (0000110d-0000-1000-8000-00805f9b34fb)
//        UUID: A/V Remote Control        (0000110e-0000-1000-8000-00805f9b34fb)
//        UUID: Headset AG                (00001112-0000-1000-8000-00805f9b34fb)
//        UUID: Handsfree Audio Gateway   (0000111f-0000-1000-8000-00805f9b34fb)
// we get to this script when  bt device connects/disconnects. We need to see what is connected
// bluetoothctl is useless for this as it only shows some devices.
// need to use btmgt and bluetoothctl to actually query the bt 'bus' to see what
// is connected and we need to keep track to see if it is 'new'
sleep (5);
// collect the devices into an array by grabbing the MACs from the command:
// 'btmgmt con' - one device per line first 17 characters is the MAC
// # btmgmt con | cut -d " " -f 1
// F8:1F:32:39:FE:31
// 08:B7:38:11:A6:C2

// then parse each device to determine its capabilities by using
// 'bluetoothctl info MAC' then parse the mac to determine it if is a source or a sink
// as we only care about audio devices...
// bluetoothctl info F8:1F:32:39:FE:31 | grep "0000110a"
//        UUID: Audio Source              (0000110a-0000-1000-8000-00805f9b34fb)

// need to keep track with a redis array of device macs, check when one is added
// only deal with audio devices.

// log file
$myfile = fopen("/srv/http/command/bt-diag.txt", "w") or die("Unable to open file!");

// these are devices we know that have been connected
$knownBT = array_keys($redis->Hgetall("bt-known"));
print_r("Known BT\n");

fwrite($myfile, "Known BTs\n");
    foreach ($knownBT as &$value){
    fwrite($myfile, $value."\n");
    print_r($value."\n");
}
// this generates an array of currently attached BT devices
$attachedBT = sysCmd("btmgmt con | cut -d ' ' -f 1");
print_r("Attached BT\n");
fwrite($myfile, "Attached BTs\n");
    foreach ($attachedBT as &$value){
    fwrite($myfile, $value."\n");
    print_r($value."\n");
}
fwrite($myfile, $argv[1]."\n");
print_r($argv[1]."\n");
switch ($argv[1]) {// passed from udev rule as parameter
case "start":
//sleep(10);
// connect
//print_r("Checking newly connected BT\n");
foreach($attachedBT as &$value){
      //print_r($value."\n");
      //is it already connected?
      if (!preg_grep("/$value/",$knownBT)) {
        fwrite($myfile, "\n found new BT MAC \n");
            $found_source = 0;
            $bt_capability=sysCmd('bluetoothctl info '.$value);
            //print_r($bt_capability);
            // test to see if it is an audio source or a sink or not of interest
            // we look for a source first and assume that the device is a phone
            // being used as a source to Rune secondarily we look for a sink
            if (preg_grep("/0000110a/s",$bt_capability)) {
            // we have an Audio Source
            fwrite($myfile," New Audio Source\n");
            sysCmd ('mpc stop');
            // since we know the MAC, we can put this into the /etc/default/bluealsa
            // and restart, but it is 00:00:00:00:00:00 so it will play
            // everthing from anything
            sysCmd ('systemctl start bluealsa-aplay');
            // add to the list of attached BTs
            // $redis-> add to list...
            $redis->hset("bt-known", $value, 1);
            $found_source = 1;
             }
            if (preg_grep("/0000110b/s",$bt_capability) && !$found_source) {
            // only set up a sink if we did not find a source. Maybe not the best way
            // but since bluealsa cannot do both simultaneously...
            fwrite($myfile," New Audio Sink\n");
            // set up as an alsa device for MPD
            //Need to send this line by line to mpd.conf
            //"audio_output {"
            //    "type            alsa"
            //    "name           ".$name
            //    "device          bluealsa:DEV=".$value.",PROFILE=a2dp"
            //    "auto_resample    no"
            //    "auto_format      no"
            //    "enabled          yes"
            //"}"
            // need to set shairport-sync and spotify to use this 'device' as well
            $redis->hset("bt-known", $value, 0);
            }
            elseif (!$found_source){
            // nothing
            //print_r("Not Audio\n");
            fwrite($myfile, " Keyboard\n");
            $redis->hset("bt-known", $value, 2);
            }
        }
        else {
        print_r(" Already Known\n");
        }
    }
    fclose($myfile);
break;;
case "stop":
// disconnect
fwrite($myfile, " Checking newly removed BT MAC\n");
print_r(" Checking newly removed BT MAC\n");
foreach($knownBT as &$value){
      //print_r($value);
      //print_r("\n");
      //is it just removed?
      if (!preg_grep("/$value/",$attachedBT)) {
          // this is the removed device
          $type = $redis->Hmget("bt-known", [$value]);
          switch ($type[$value]) {
          case 0:
            // sink
            print_r(" sink removed\n");
            fwrite($myfile, " sink removed\n");
            // remove the lines from mpd.conf
            // remove from mpd.conf and change AO to something else
          break;;
          case 1:
            // source
            print_r(" source removed\n");
            fwrite($myfile, " source removed\n");
            sysCmd ('systemctl stop bluealsa-aplay');
          break;;
          case 2:
             print_r(" not Audio\n");
             fwrite($myfile, " other removed\n");
            // not audio
          break;;
          }
        $redis->Hdel("bt-known", $value);
        }
    }
    fclose($myfile);
break;;
}
?>
