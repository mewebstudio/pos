<?php

require '../../_main_config.php';

$path = '/finansbank-payfor/regular/';
$baseUrl = $hostUrl.$path;

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$ip = $request->getClientIp();

$account = [
    'bank'          => 'qnbfinansbank-payfor',
    'model'         => 'regular',
    'client_id'     => '085300000009704',
    'username'      => 'QNB_API_KULLANICI_3DPAY',
    'password'      => 'UcBN0',
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

$gateway = $baseUrl.'response.php';

$templateTitle = 'Regular Payment';
