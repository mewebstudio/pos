<?php

use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\PosInterface;

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/garanti';
$posClass = \Mews\Pos\Gateways\GarantiPos::class;

$testCards = [
/*    'visa1' => [
        'number' => '4282209004348015',
        'year' => '30',
        'month' => '08',
        'cvv' => '123',
        'name' => 'John Doe',
        'type' => AbstractCreditCard::CARD_TYPE_VISA,
    ],*/
    'visa1' => [
        // pin 147852
        'number' => '5549604173790011',
        'year' => '24',
        'month' => '02',
        'cvv' => '423',
        'name' => 'John Doe',
        'type' => AbstractCreditCard::CARD_TYPE_MASTERCARD,
    ],
    // test kartlar https://dev.garantibbva.com.tr/test-kartlari
    'visa2' => [
        // pin 147852
        'number' => '5406697543211173',
        'year' => '27',
        'month' => '04',
        'cvv' => '423',
        'name' => 'John Doe',
        'type' => AbstractCreditCard::CARD_TYPE_MASTERCARD,
    ],
];
