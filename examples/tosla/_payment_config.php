<?php

use Mews\Pos\Entity\Card\CreditCardInterface;

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/tosla';
$posClass     = \Mews\Pos\Gateways\ToslaPos::class;

$testCards = [
    'visa1'  => [
        'number' => '4159560047417732',
        'year'   => '24',
        'month'  => '08',
        'cvv'    => '123',
        'name'   => 'John Doe',
        'type'   => CreditCardInterface::CARD_TYPE_VISA,
    ],
    'master' => [
        'number' => '5571135571135575',
        'year'   => '24',
        'month'  => '12',
        'cvv'    => '000',
        'name'   => 'John Doe',
        'type'   => CreditCardInterface::CARD_TYPE_VISA,
    ],
];
