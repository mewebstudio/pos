<?php

require '../../_main_config.php';

$path = '/akbank/3d-host/';
$baseUrl = $hostUrl.$path;

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$ip = $request->getClientIp();

$account = \Mews\Pos\Factory\AccountFactory::createEstPosAccount('akbank', 'XXXXXXX', 'XXXXXXX', '', '3d_host', 'XXXXXXX');

try {
    $pos = \Mews\Pos\Factory\PosFactory::createPosGateway($account);
    $pos->setTestMode(true);
} catch (\Mews\Pos\Exceptions\BankNotFoundException $e) {
    dump($e->getCode(), $e->getMessage());
} catch (\Mews\Pos\Exceptions\BankClassNullException $e) {
    dump($e->getCode(), $e->getMessage());
}

$templateTitle = '3D Host Model Payment';
