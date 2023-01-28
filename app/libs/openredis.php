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
 * along with RuneAudio; see the file COPYING.  If not, see
 * <http://www.gnu.org/licenses/gpl-3.0.txt>.
 *
 *  file: app/libs/openredis.php
 *  version: 1.3
 *  coder: janui
 *  date: August 2021
 *
 */
// Connect to Redis backend
// this file is included into all scripts which use redis
// in the backup and other functions redis is stopped and restarted, it is possible that
// redis is still starting when this code executes, so trap any errors
$redisError = true;
// a massive repeat value for looping, it should work on the first try
//  but, if no redis is connected is made the rest will fail, so keep trying
//  should only be a problem during start up
// this can be overridden by setting the variable $redisOpenOneTime to true in the parent
//  script, it will then run once and exit on failure
if (isset($redisOpenOneTime) && $redisOpenOneTime) {
    $redisErrorCount = 1;
} else {
    $redisErrorCount = 30;
}
$redisSleepTime = 2;

// initialise the redis object
$redis = new Redis();

while ($redisError) {
    try {
        // Code that may throw an Exception or Error.
        // Connect to redis
        if ($redis->pconnect('/run/redis/socket')) {
            $redisError = false;
        } else {
            $redisError = true;
            $redisErrorCount--;
        }
    }
    catch (Throwable $t) {
        // Executed only in PHP 7 and higher, will not match in PHP 5 and lower
        $redisError = true;
        $redisErrorCount--;
    }
    catch (Exception $e) {
        // Executed only in PHP 5 and lower, will not be reached in PHP 7 and higher
        $redisError = true;
        $redisErrorCount--;
    }
    if ($redisErrorCount <= 0) {
        if (isset($redisOpenOneTime) && $redisOpenOneTime) {
            // exit the script when $redisOpenOneTime is set and true
            // exit(0) will be interpreted as a successful (non-error) completion in bash
            exit(0);
        } else {
            // since nothing will work without redis just exit
            // Use '111 ECONNREFUSED Connection refused' as exit code
            // exit(111) will be interpreted as a failure (error) completion in bash
            echo "Error: [app/libs/openredis.php] Failed to connect to Redis aborting\n";
            exit(111);
        }
    } else if ($redisError) {
        // loop again, so sleep first
        sleep($redisSleepTime);
    }
}
unset($redisError, $redisErrorCount, $redisSleepTime, $redisOpenOneTime);
// end open redis
//
