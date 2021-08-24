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
// in the backup and other functions redis is stopped and restarted, it is possible that
// redis is still starting when this code executes, so trap any errors
$redisError = true;
$redisErrorCount = 10;
$redisSleepTime = 2;
while ($redisError) {
    try
    {
        // Code that may throw an Exception or Error.
        $redis = new Redis();
        if ($redis->pconnect('/run/redis/socket')) {
            $redisError = false;
        } else {
            $redisError = true;
            sleep($redisSleepTime);
            $redisErrorCount--;
        }
    }
    catch (Throwable $t)
    {
        // Executed only in PHP 7 and higher, will not match in PHP 5
        $redisError = true;
        sleep($redisSleepTime);
        $redisErrorCount--;
    }
    catch (Exception $e)
    {
        // Executed only in PHP 5, will not be reached in PHP 7 and higher
        $redisError = true;
        sleep($redisSleepTime);
        $redisErrorCount--;
    }
    if ($redisErrorCount <= 0) {
        // exit the loop
        $redisError = false;
        // try again but don't trap the error
        $redis = new Redis();
        $redis->pconnect('/run/redis/socket');
    }
}
unset($redisError, $redisErrorCount, $redisSleepTime);
// end open redis
//
