<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$root = realpath(__DIR__);
require_once "$root/../vendor/autoload.php";

/*$redis = new Redis();
$redis->connect('pos_redis', 6379);
$sessionHandler = new \Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler($redis);
$sessionHandler = new \Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage([
    'cookie_samesite' => 'None'
], $sessionHandler);
*/

$sessionHandler = new \Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage([
    'cookie_samesite' => 'None'
]);
$session        = new \Symfony\Component\HttpFoundation\Session\Session($sessionHandler);
//banktan donuste eski session'a devam edemiyor, yeni session olusturuluyor
//eski session'deki order bilgiler kayboluyor.
//session id vererek, yeni session olusmasini engelliyoruz
$session->setId('mbu0tkd5vkbkksrkk824f1ib4a');


$hostUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')."://$_SERVER[HTTP_HOST]";
