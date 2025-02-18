<?php

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/akbankpos';
$posClass = \Mews\Pos\Gateways\AkbankPos::class;

$testCards = [
    'visa1' => [
        // OTP 123456
        'number' => '4355093000315232',
        'year' => '40',
        'month' => '11',
        'cvv' => '471',
        'name' => 'John Doe',
    ],
];
