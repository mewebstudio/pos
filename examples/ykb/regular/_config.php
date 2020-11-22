<?php

require '../../_main_config.php';

$path = '/ykb/regular/';
$baseUrl = $hostUrl.$path;

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$ip = $request->getClientIp();
$account = \Mews\Pos\Factory\AccountFactory::createPosNetAccount('yapikredi', '6706598320', 'XXXXXX', 'XXXXXX', '67322946', '27426', 'regular', '10,10,10,10,10,10,10,10');

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
