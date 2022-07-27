<?php
// Connect to Redis backend include
require_once('/srv/http/app/libs/openredis.php');

$option = array('cost' => 12);
$hash = password_hash('rune', PASSWORD_BCRYPT, $option);

$redis->set('password', $hash);
