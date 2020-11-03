<?php

require '../../_main_config.php';

$path = '/akbank/regular/';
$baseUrl = $hostUrl . $path;

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$ip = $request->getClientIp();

$account = [
    'bank'          => 'akbank',
    'model'         => 'regular',
    'client_id'     => 'XXXXXXX',
    'username'      => 'XXXXXXX',
    'password'      => 'XXXXXXX',
    'env'           => 'test',
];

try {
    $pos = new \Mews\Pos\Pos($account);
} catch (\Mews\Pos\Exceptions\BankNotFoundException $e) {
    dump($e->getCode(), $e->getMessage());
} catch (\Mews\Pos\Exceptions\BankClassNullException $e) {
    dump($e->getCode(), $e->getMessage());
}

$gateway = $baseUrl . 'response.php';

$templateTitle = 'Regular Payment';
