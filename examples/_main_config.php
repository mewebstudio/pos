<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$root = realpath($_SERVER["DOCUMENT_ROOT"]);
require_once "$root/../vendor/autoload.php";

$redis = new Redis();
$redis->connect('pos_redis', 6379);

$hostUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')."://$_SERVER[HTTP_HOST]";
