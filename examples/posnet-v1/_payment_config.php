<?php

use Mews\Pos\Entity\Card\CreditCardInterface;

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/posnet-v1';
$posClass = \Mews\Pos\Gateways\PosNetV1Pos::class;

$testCards = [
    // 3d onay kodu 34020
    'visa1' => [
        'number' => '4506347010299085',
        'year' => '26',
        'month' => '09',
        'cvv' => '000',
        'name' => 'John Doe',
        'type' => CreditCardInterface::CARD_TYPE_VISA,
    ],
];
