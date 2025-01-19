<?php

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/parampos';
$posClass = \Mews\Pos\Gateways\ParamPos::class;

$testCards = [
    'visa1' => [
        'number' => '5456165456165454',
        'year' => '26',
        'month' => '12',
        'cvv' => '000',
        'name' => 'John Doe',
    ],
    //    'visa1' => [ // non secure USD/doviz odeme karti
//        'number' => '4546711234567894',
//        'year' => '26',
//        'month' => '12',
//        'cvv' => '000',
//        'name' => 'John Doe',
//    ],
];
