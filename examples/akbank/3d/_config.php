<?php

require '../../_main_config.php';

$path = '/akbank/3d/';
$baseUrl = $hostUrl . $path;

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$ip = $request->getClientIp();

$account = [
    'bank'          => 'akbank',
    'model'         => '3d',
    'client_id'     => 'XXXXXXX',
    'username'      => 'XXXXXXX',
    'password'      => 'XXXXXXX',
    'store_key'     => 'XXXXXXX',
    'env'           => 'test',
];

try {
    $pos = new \Mews\Pos\Pos($account);
} catch (\Mews\Pos\Exceptions\BankNotFoundException $e) {
    dump($e->getCode(), $e->getMessage());
} catch (\Mews\Pos\Exceptions\BankClassNullException $e) {
    dump($e->getCode(), $e->getMessage());
}

$templateTitle = '3D Model Payment';
