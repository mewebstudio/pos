<?php

require '../../_main_config.php';

$path = '/akbank/regular/';
$baseUrl = $hostUrl.$path;

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$ip = $request->getClientIp();
$account = \Mews\Pos\Factory\AccountFactory::createEstPosAccount('akbank', 'XXXXXXX', 'XXXXXXX', '', 'regular');

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
