#!/usr/bin/php
<?php
@ob_end_clean(); if (ini_get('output_buffering')) ob_start();
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
 *  file: /command/restore.php
 *  version: 1.3
 *  coder: janui
 *  date: October 2020
 *
 */
// common include
// RuneAudio Library include
require_once('/srv/http/app/libs/runeaudio.php');
// Connect to Redis backend include
require_once('/srv/http/app/libs/openredis.php');
// ini_set('display_errors',1);
// error_reporting(E_ALL);
$errorMessages = array(
    0 => 'There is no error, the file uploaded with success.',
    1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
    2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
    3 => 'The uploaded file was only partially uploaded.',
    4 => 'No file was uploaded.',
    6 => 'Missing a temporary folder.',
    7 => 'Failed to write file to disk.',
    8 => 'A PHP extension stopped the file upload.',
);
// get the file target directory and clean it up
if (!$redis->exists('backup_dir')) {
    sysCmd('srv/http/db/redis_datastore_setup check');
}
// get the file upload information
$file = $_FILES['filebackup'];
$fileName = trim($file['name']);
$fileTmp = $file['tmp_name'];
$fileSize = $file['size'];
$fileError = $file['error'];
$fileType = ' '.strtolower($file['type']);
// set up the destination file name and delete it if it exists
$fileDestDir = '/'.trim($redis->get('backup_dir'), "/ \t\n\r\0\x0B").'/';
$fileDest = $fileDestDir.$fileName;
unlink($fileDest);
// remove any other backup files in the destination directory
sysCmd('rm -f '.$fileDestDir.'backup*');
// force the UI notifications/messages to be queued
$redis->set('waitSyWrk', '1');
// check for errors and security issues
$isError = false;
if ($fileError) {
    if (isset($errorMessages[$fileError])) {
        ui_notifyError($redis, 'Error', $errorMessages[$fileError]);
    } else {
        ui_notifyError($redis, 'Error', 'Unknown error, error number: '.$fileError);
    }
    $isError = true;
} else if ($fileSize === 0) {
    ui_notifyError($redis, 'Error', 'Empty file.');
    $isError = true;
} else if (strpos($fileType, 'image')) {
    ui_notifyError($redis, 'Error', 'Invalid file contents.');
    $isError = true;
} else if (preg_match('/[^a-zA-Z0-9\-_ .]/', $fileName)) {
    ui_notifyError($redis, 'Error', 'Invalid characters in the file name: '.$fileName);
    ui_notifyError($redis, 'Error', 'Invalid characters : '.preg_replace('/[a-zA-Z0-9\-_ .]/', '' ,$fileName));
    $isError = true;
} else if (mb_strlen($fileName,"UTF-8") > 225) {
    ui_notifyError($redis, 'Error', 'File name too long.');
    $isError = true;
} else if (substr($fileName, -7) != '.tar.gz') {
    ui_notifyError($redis, 'Error', 'File extension incorrect, expected: .tar.gz');
    $isError = true;
} else if (!move_uploaded_file($fileTmp, $fileDest)) {
    ui_notifyError($redis, 'Error', 'Invalid upload, cannot process file.');
    $isError = true;
} else if (strpos(' '.sysCmd('bsdtar -tf')[0], 'bsdtar: Error')) {
    ui_notifyError($redis, 'Error', 'File content is not a valid archive format.');
    $isError = true;
} else {
    ui_notify($redis, 'Success', 'File is valid and was successfully uploaded, restore and restart will follow...');
    // start a job in the back-end (as root) to process the backup file
    $jobID[] = wrk_control($redis, 'newjob', $data = array('wrkcmd' => 'restore', 'args' => $fileDest));
}
// clean up if there was an error
if ($isError) {
    unlink($fileDest);
    sleep(5);
} else {
    if (isset($jobID)) {
        waitSyWrk($redis, $jobID);
    }
}
sleep(1);
// release queued UI notifications/messages
$redis->set('waitSyWrk', '0');
