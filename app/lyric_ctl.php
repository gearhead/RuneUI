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
    if (isset($info['currentsong']) && $info['currentsong']) {
        if (strpos(' '.$info['currentsong'],"'") != 0 ) {
            $currentsong = '"'.trim(preg_replace('![\s]+!', ' ',$info['currentsong'])).'"';
        } else {
            $currentsong = "'".trim(preg_replace('![\s]+!', ' ',$info['currentsong']))."'";;
        }
    }
    if (isset($info['currentartist']) && $info['currentartist'] && isset($info['currentsong']) && $info['currentsong']) {
        if ((strtolower(trim($info['currentartist'])) == 'various artists') && isset($info['currentalbumartist']) && $info['currentalbumartist']) {
            echo sysCmd("sh /var/www/command/lyric.sh ".$currentalbumartist." ".$currentsong)[2];
        } else {
            echo sysCmd("sh /var/www/command/lyric.sh ".$currentartist." ".$currentsong)[2];
        }
    } else if (isset($info['currentalbumartist']) && $info['currentalbumartist'] && isset($info['currentsong']) && $info['currentsong']) {
        echo sysCmd("sh /var/www/command/lyric.sh ".$currentalbumartist." ".$currentsong)[2];
    } else {
        echo 'Invalid Artist and/or Song - No lyrics available';
    }
}
unset($info);
runelog("\n--------------------- lyric (end) ---------------------");
