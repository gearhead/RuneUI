<?php

// direct output bypass template system
$tplfile = 0;
runelog("\n--------------------- lyric (start) ---------------------");
// turn off output buffering
ob_implicit_flush(0);

ob_clean();
flush();

// --------------------- All players ---------------------
if (!$redis->Exists('act_player_info')) {
    echo '';
} else {
    $info = json_decode($redis->Get('act_player_info'), true);
    if (isset($info['currentalbumartist']) && $info['currentalbumartist'] && isset($info['currentsong']) && $info['currentsong']) {
        if ((strtolower($info['currentalbumartist']) == 'various artists') && isset($info['currentartist']) && $info['currentartist']) {
            echo sysCmd("sh /var/www/command/lyric.sh '".trim(preg_replace('![\s]+!', ' ',$info['currentartist']))."' '".trim(preg_replace('![\s]+!', ' ',$info['currentsong']))."'")[2];
        } else {
            echo sysCmd("sh /var/www/command/lyric.sh '".trim(preg_replace('![\s]+!', ' ',$info['currentalbumartist']))."' '".trim(preg_replace('![\s]+!', ' ',$info['currentsong']))."'")[2];
        }
    } else {
        echo '';
    }
}
unset($info);
runelog("\n--------------------- lyric (end) ---------------------");
