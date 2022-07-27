<?php
// Connect to Redis backend include
require_once('/srv/http/app/libs/openredis.php');
$hash = $redis->get('password');

if (!password_verify($_POST['pwd'], $hash)) die();

$option = array('cost' => 12);
$hash = password_hash($_POST['pwdnew'], PASSWORD_BCRYPT, $option);

$set = $redis->set('password', $hash);

echo $set;
