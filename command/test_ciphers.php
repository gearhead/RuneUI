#!/usr/bin/php
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
 *  file: command/test_ciphers.php
 *  version: 1.3
 *  coder: janui
 *  date: November 2021
 *
 */
$cnt = 10;
while ((!isset($cipher_array) || (isset($cipher_array) && !is_array($cipher_array))) && ($cnt-- > 0)) {
    $cipher_array = openssl_get_cipher_methods();
    if (!isset($cipher_array) || (isset($cipher_array) && !is_array($cipher_array))) {
        sleep(1);
        unset($cipher_array);
    }
}
echo "Array length full ".count($cipher_array)."\n";
// remove weak ciphers from the array (ecb, des, rc2, rc4, md5)
// remove AEAD ciphers which require an authentication tag (gcm, ccm, ocb, xts, wrap)
$cipher_exclude_list = 'ecb des rc2 rc4 md5 gcm ccm ocb xts wrap';
$cipher_exclude_array = explode(' ', $cipher_exclude_list);
foreach ($cipher_exclude_array as $cipher_exclude) {
    $cipher_array = array_filter( $cipher_array, function($c) { global $cipher_exclude; return stripos($c, $cipher_exclude)===FALSE; } );
    echo "Array length -".$cipher_exclude." ".count($cipher_array)."\n";
}
// ensure the cipher array indexes are sequential
$cipher_array = array_values($cipher_array);
// determine the highest cipher array index
$cipher_array_max_index = count($cipher_array) - 1;
// randomly choose a cipher from the cipher array
$cipher = $cipher_array[rand(0, $cipher_array_max_index)];

foreach ($cipher_array as $cipher) {
    // determine the initialization vector
    //  when it fails it raises an exception, what a pain
    //  retry 10 times, wait 1 second in the loop
    $cnt = 10;
    $ivError = true;
    while ($ivError) {
        try {
            // Code that may throw an Exception or Error.
            // Connect to redis
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
            $ivError = false;
        }
        catch (Throwable $t) {
            // Executed only in PHP 7 and higher, will not match in PHP 5 and lower
            $ivError = true;
            $cnt--;
        }
        catch (Exception $e) {
            // Executed only in PHP 5 and lower, will not be reached in PHP 7 and higher
            $ivError = true;
            $cnt--;
        }
        if ($cnt <= 0) {
            // since it wont work just exit
            // Use '126	ENOKEY	Required key not available' as exit code
            // exit(126) will be interpreted as a failure (error) completion in bash
            echo "Error: [app/libs/runeaudio.php][reset_cmd_queue_encoding] Failed to determine initialization vector, aborting";
            exit(126);
        } else if ($ivError) {
            // loop again, so sleep first
            sleep(1);
        }
    }
    // the passphrase is the playerid
    $passphrase = '016c4adb0f4af9d346c380a103b2e1dde5';
    unset($encoded);
    $encoded = base64_encode(openssl_encrypt(gzdeflate('test a string', 9), $cipher, $passphrase, 0, $iv));
    if (isset($encoded) && $encoded) {
        echo "'".$cipher."' OK\n";
    } else {
        echo "'".$cipher."' Error\n";
        if (!in_array($cipher, $cipher_exclude_array)) {
            $cipher_exclude_array[] = $cipher;
        }
    }
}
$cipher_exclude_list = implode(' ', $cipher_exclude_array);
echo "New cipher_exclude_list '".$cipher_exclude_list."'\n";
