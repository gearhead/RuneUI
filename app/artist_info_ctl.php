<?php

// direct output bypass template system
$tplfile = 0;
runelog("\n--------------------- artist info (start) ---------------------");
// turn off output buffering
ob_implicit_flush(0);

ob_clean();
flush();

// --------------------- All Players ---------------------
if (!$redis->Exists('act_player_info')) {
    echo '';
} else {
    $info = json_decode($redis->Get('act_player_info'), true);
    if (isset($info['currentartist']) && $info['currentartist']) {
        if (strpos(' '.$info['currentartist'],"'") != 0 ) {
            $currentartist = '"'.trim(preg_replace('![\s]+!', ' ',$info['currentartist'])).'"';
        } else {
            $currentartist = "'".trim(preg_replace('![\s]+!', ' ',$info['currentartist']))."'";;
        }
    }
    if (isset($info['currentalbumartist']) && $info['currentalbumartist']) {
        if (strpos(' '.$info['currentalbumartist'],"'") != 0 ) {
            $currentalbumartist = '"'.trim(preg_replace('![\s]+!', ' ',$info['currentalbumartist'])).'"';
        } else {
            $currentalbumartist = "'".trim(preg_replace('![\s]+!', ' ',$info['currentalbumartist']))."'";;
        }
    }
    if (isset($info['currentartist']) && $info['currentartist']) {
        if ((strtolower(trim($info['currentartist'])) == 'various artists') && isset($info['currentalbumartist']) && $info['currentalbumartist']) {
            echo str_replace('">Read more on Last.fm', '/+wiki\" target=\"_blank\" rel=\"nofollow\">Read more on Last.fm', preg_replace('/[\t\n\r\s]+/',' ',sysCmd("sh /var/www/command/artist_info.sh ".$currentalbumartist)[1]));
        } else {
            echo str_replace('">Read more on Last.fm', '/+wiki\" target=\"_blank\" rel=\"nofollow\">Read more on Last.fm', preg_replace('/[\t\n\r\s]+/',' ',sysCmd("sh /var/www/command/artist_info.sh ".$currentartist)[1]));
        }
    } else if (isset($info['currentalbumartist']) && $info['currentalbumartist']) {
        echo str_replace('">Read more on Last.fm', '/+wiki\" target=\"_blank\" rel=\"nofollow\">Read more on Last.fm', preg_replace('/[\t\n\r\s]+/',' ',sysCmd("sh /var/www/command/artist_info.sh ".$currentalbumartist)[1]));
    } else {
        $fail = array();
        $fail['error'] = "0";
        $fail['message'] = "Empty artist name";
        $fail['links']= array();
        echo json_encode($fail);
    }
}
unset($info, $fail);
runelog("\n--------------------- artist info (end) ---------------------");
