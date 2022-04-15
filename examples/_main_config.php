<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$root = realpath(__DIR__);
require_once "$root/../vendor/autoload.php";

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$ip = $request->getClientIp();

$sessionHandler = new \Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage([
    'cookie_samesite' => 'None',
]);
$session        = new \Symfony\Component\HttpFoundation\Session\Session($sessionHandler);
//banktan donuste eski session'a devam edemiyor, yeni session olusturuluyor
//eski session'deki order bilgiler kayboluyor.
//session id vererek, yeni session olusmasini engelliyoruz
$session->setId('mbu0tkd5vkbkksrkk824f1ib4a');


$hostUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')."://$_SERVER[HTTP_HOST]";
$subMenu = [];

function getGateway(\Mews\Pos\Entity\Account\AbstractPosAccount $account): ?\Mews\Pos\PosInterface
{
    try {
        $pos = \Mews\Pos\Factory\PosFactory::createPosGateway($account);
        $pos->setTestMode(true);

        return $pos;
    } catch (Exception $e) {
        dd($e);
    }
}
