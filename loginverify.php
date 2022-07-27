<?php
// Connect to Redis backend include
require_once('/srv/http/app/libs/openredis.php');
$hash = $redis->get('password');

if (!password_verify($_POST['pwd'], $hash)) die();

echo 1;

session_start();
$_SESSION['login'] = 1;
