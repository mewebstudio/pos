<?php

use Mews\Pos\Entity\Card\CreditCardInterface;

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/kuveytpos';
$posClass = \Mews\Pos\Gateways\KuveytPos::class;

$testCards = [
    'visa1' => [
        //Kart Doğrulama Şifresi: 123456
        'number' => '5188961939192544',
        'year' => '25',
        'month' => '06',
        'cvv' => '929',
        'name' => 'John Doe',
        'type' => CreditCardInterface::CARD_TYPE_MASTERCARD,
    ],
];
