<?php

use Mews\Pos\Entity\Card\CreditCardInterface;

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/finansbank-payfor';
$posClass = \Mews\Pos\Gateways\PayForPos::class;

$testCards = [
    'visa1' => [
        'number' => '4155650100416111',
        'year' => '25',
        'month' => '1',
        'cvv' => '123',
        'name' => 'John Doe',
        'type' => CreditCardInterface::CARD_TYPE_VISA,
    ],
];
