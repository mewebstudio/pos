<?php

use Mews\Pos\Exceptions\BankClassNullException;
use Mews\Pos\Exceptions\BankNotFoundException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\VakifBankPos;
use Symfony\Component\HttpFoundation\Request;

require '../../_main_config.php';

$path = '/vakifbank/3d/';
$baseUrl = $hostUrl.$path;

$successUrl = $failUrl = $baseUrl.'response.php';

$account = AccountFactory::createVakifBankAccount('vakifbank', '000000000111111', '3XTgER89as', 'VP999999', '3d');

$request = Request::createFromGlobals();
$ip = $request->getClientIp();

try {
    /**
     * @var VakifBankPos $pos
     */
    $pos = PosFactory::createPosGateway($account);
    $pos->setTestMode(true);
} catch (BankNotFoundException $e) {
    dump($e->getCode(), $e->getMessage());
} catch (BankClassNullException $e) {
    dump($e->getCode(), $e->getMessage());
}

$templateTitle = '3D Model Payment';
