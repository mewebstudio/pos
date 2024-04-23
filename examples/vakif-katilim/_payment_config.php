<?php

use Mews\Pos\Entity\Card\CreditCardInterface;

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/vakif-katilim';
$posClass     = \Mews\Pos\Gateways\VakifKatilimPos::class;

$testCards = [
    'visa1' => [
        'number' => '4155650100416111',
        'year'   => '25',
        'month'  => '1',
        'cvv'    => '123',
        'name'   => 'John Doe',
        'type'   => CreditCardInterface::CARD_TYPE_VISA,
    ],
];
