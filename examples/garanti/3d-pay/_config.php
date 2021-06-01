<?php

use Mews\Pos\Exceptions\BankClassNullException;
use Mews\Pos\Exceptions\BankNotFoundException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\PosFactory;
use Symfony\Component\HttpFoundation\Request;

require '../../_main_config.php';

$path = '/garanti/3d-pay/';
$baseUrl = $hostUrl.$path;

$request = Request::createFromGlobals();
$ip = $request->getClientIp();
$account = AccountFactory::createGarantiPosAccount('garanti', '7000679', 'PROVAUT', '123qweASD/', '30691298', '3d_pay', '12345678');

try {
    $pos = PosFactory::createPosGateway($account);
    $pos->setTestMode(true);
} catch (BankNotFoundException $e) {
    dump($e->getCode(), $e->getMessage());
} catch (BankClassNullException $e) {
    dump($e->getCode(), $e->getMessage());
}

$templateTitle = '3D Pay Model Payment';
