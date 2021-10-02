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
    if (isset($info['currentalbumartist']) && $info['currentalbumartist']) {
        if ((strtolower($info['currentalbumartist']) == 'various artists') && isset($info['currentartist']) && $info['currentartist']) {
            echo str_replace('">Read more on Last.fm', '/+wiki\" target=\"_blank\" rel=\"nofollow\">Read more on Last.fm', preg_replace('/[\t\n\r\s]+/',' ',sysCmd("sh /var/www/command/artist_info.sh '".trim(preg_replace('![\s]+!', ' ',$info['currentartist']))."'")[1]));
        } else {
            echo str_replace('">Read more on Last.fm', '/+wiki\" target=\"_blank\" rel=\"nofollow\">Read more on Last.fm', preg_replace('/[\t\n\r\s]+/',' ',sysCmd("sh /var/www/command/artist_info.sh '".trim(preg_replace('![\s]+!', ' ',$info['currentalbumartist']))."'")[1]));
        }
    } else {
        echo '';
    }
}
unset($info);
runelog("\n--------------------- artist info (end) ---------------------");
