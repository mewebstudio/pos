<?php

use Mews\Pos\Entity\Card\CreditCardInterface;

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/ykb';
$posClass = \Mews\Pos\Gateways\PosNet::class;

$testCards = [
    'visa1' => [
        'number' => '4543600299100712',
        'year' => '23',
        'month' => '11',
        'cvv' => '454',
        'name' => 'John Doe',
        'type' => CreditCardInterface::CARD_TYPE_VISA,
    ],
];
