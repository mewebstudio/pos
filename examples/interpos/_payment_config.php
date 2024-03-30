<?php

use Mews\Pos\Entity\Card\CreditCardInterface;

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/interpos';
$posClass = \Mews\Pos\Gateways\InterPos::class;

$testCards = [
    'visa1' => [
        'number' => '4090700090840057',
        'year' => '22',
        'month' => '1',
        'cvv' => '592',
        'name' => 'John Doe',
        'type' => CreditCardInterface::CARD_TYPE_VISA,
    ],
    'visa2' => [
        'number' => '4090700101174272',
        'year' => '22',
        'month' => '12',
        'cvv' => '104',
        'name' => 'John Doe',
        'type' => CreditCardInterface::CARD_TYPE_VISA,
    ],
];
