<?php

use Mews\Pos\Entity\Card\CreditCardInterface;

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/payflex-mpi-v4';
$posClass = \Mews\Pos\Gateways\PayFlexV4Pos::class;

$testCards = [
    'visa1' => [
        // NOTE: 3D Secure sifre 123456
        'number' => '4938460158754205',
        'year' => '24',
        'month' => '11',
        'cvv' => '715',
        'name' => 'John Doe',
        'type' => CreditCardInterface::CARD_TYPE_VISA,
    ],
];
