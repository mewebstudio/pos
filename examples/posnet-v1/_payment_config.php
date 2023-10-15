<?php

use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\PosInterface;

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/posnet-v1';
$posClass = \Mews\Pos\Gateways\PosNetV1Pos::class;

function getNewOrder(
    string $baseUrl,
    string $ip,
    string $currency,
    \Symfony\Component\HttpFoundation\Session\Session $session,
    ?int $installment = 0,
    bool $tekrarlanan = false,
    string $lang = PosInterface::LANG_TR
): array {
    return createNewPaymentOrderCommon($baseUrl, $ip, $currency, $installment, $lang);
}

$testCards = [
    // 3d onay kodu 34020
    'visa1' => [
        'number' => '4506347010299085',
        'year' => '26',
        'month' => '09',
        'cvv' => '000',
        'name' => 'John Doe',
        'type' => AbstractCreditCard::CARD_TYPE_VISA,
    ],
];
