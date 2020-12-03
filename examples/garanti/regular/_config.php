<?php

require '../../_main_config.php';

$path = '/garanti/regular/';
$baseUrl = $hostUrl.$path;

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$ip = $request->getClientIp();
$account = \Mews\Pos\Factory\AccountFactory::createGarantiPosAccount('garanti', '7000679', 'PROVAUT', '123qweASD/', '30691298', 'regular', '', 'PROVRFN', '123qweASD/');

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
