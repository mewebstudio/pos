<?php

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/parampos';
$posClass = \Mews\Pos\Gateways\ParamPos::class;

$testCards = [
    'visa1' => [
        'number' => '4446763125813623',
        'year' => '26',
        'month' => '12',
        'cvv' => '000',
        'name' => 'John Doe',
    ],
];
