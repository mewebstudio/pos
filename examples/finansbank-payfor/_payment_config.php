<?php

use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\PosInterface;

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/finansbank-payfor';
$posClass = \Mews\Pos\Gateways\PayForPos::class;

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
    'visa1' => [
        'number' => '4155650100416111',
        'year' => '25',
        'month' => '1',
        'cvv' => '123',
        'name' => 'John Doe',
        'type' => AbstractCreditCard::CARD_TYPE_VISA,
    ],
];
