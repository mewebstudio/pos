<?php

require '../../_main_config.php';

$path = '/finansbank-payfor/3d/';
$baseUrl = $hostUrl.$path;

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$ip = $request->getClientIp();

$account = [
    'bank'          => 'qnbfinansbank-payfor',
    'model'         => '3d',
    'client_id'     => '085300000009704',
    'username'      => 'QNB_API_KULLANICI_3DPAY',
    'password'      => 'UcBN0',
    'store_key'     => '12345678', //MerchantPass only needed for 3D payment
    'env'           => 'test',
    'lang'          => \Mews\Pos\PayForPos::LANG_EN,
];

try {
    $pos = new \Mews\Pos\Pos($account);
} catch (\Mews\Pos\Exceptions\BankNotFoundException $e) {
    dump($e->getCode(), $e->getMessage());
} catch (\Mews\Pos\Exceptions\BankClassNullException $e) {
    dump($e->getCode(), $e->getMessage());
}

$templateTitle = '3D Model Payment';
