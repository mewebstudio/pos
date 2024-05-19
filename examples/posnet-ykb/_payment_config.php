<?php

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/posnet-ykb';
$posClass     = \Mews\Pos\Gateways\PosNet::class;

$testCards = [
    'visa1' => [
        'number' => '4048095010857528',
        'year'   => '28',
        'month'  => '05',
        'cvv'    => '454',
        'name'   => 'John Doe',
    ],
];
