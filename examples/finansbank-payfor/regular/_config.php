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
    'customData'    => (object) [
        /**
         * 0 : İşlemin E-commerce olduğunu ifade eder.
         * 1 : İşlemin MO TO olduğunu ifade ede
         */
        'moto' => '0',
        'mbrId' => 5, //Kurum Kodu
    ]
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
