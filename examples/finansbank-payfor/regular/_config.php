<?php

require '../../_main_config.php';

$path = '/finansbank-payfor/regular/';
$baseUrl = $hostUrl.$path;

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$ip = $request->getClientIp();

$account = \Mews\Pos\Factory\AccountFactory::createPayForAccount('qnbfinansbank-payfor', '085300000009704', 'QNB_API_KULLANICI_3DPAY', 'UcBN0');

try {
    $pos = \Mews\Pos\Factory\PosFactory::createPosGateway($account);
    $pos->setTestMode(true);
} catch (\Mews\Pos\Exceptions\BankNotFoundException $e) {
    dump($e->getCode(), $e->getMessage());
} catch (\Mews\Pos\Exceptions\BankClassNullException $e) {
    dump($e->getCode(), $e->getMessage());
}

$gateway = $baseUrl.'response.php';

$templateTitle = 'Regular Payment';
