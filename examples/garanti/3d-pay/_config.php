<?php

require '../../_main_config.php';

$path = '/pos/examples/garanti/3d-pay/';
$baseUrl = $hostUrl . $path;

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$ip = $request->getClientIp();

$account = [
    'bank'              => 'garanti',
    'model'             => '3d_pay',
    'client_id'         => '7000679',
    'terminal_id'       => '30691298',
    'username'          => 'PROVAUT',
    'password'          => '123qweASD/',
    'store_key'         => '12345678',
    'env'               => 'test',
];

try {
    $pos = new \Mews\Pos\Pos($account);
} catch (\Mews\Pos\Exceptions\BankNotFoundException $e) {
    dump($e->getCode(), $e->getMessage());
} catch (\Mews\Pos\Exceptions\BankClassNullException $e) {
    dump($e->getCode(), $e->getMessage());
}

$templateTitle = '3D Pay Model Payment';
