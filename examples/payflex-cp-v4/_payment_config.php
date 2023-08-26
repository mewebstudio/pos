<?php

use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Gateways\AbstractGateway;

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/payflex-cp-v4';

$subMenu = [
    AbstractGateway::MODEL_3D_SECURE => [
        'path' => '/3d-pay/index.php',
        'label' => '3D Pay Ödeme',
    ],
    AbstractGateway::MODEL_3D_HOST => [
        'path' => '/3d-host/index.php',
        'label' => '3D Host Ödeme',
    ],
    AbstractGateway::MODEL_NON_SECURE => [
        'path' => '/regular/index.php',
        'label' => 'Non Secure Ödeme',
    ],
    AbstractGateway::TX_CANCEL => [
        'path' => '/regular/cancel.php',
        'label' => 'İptal',
    ],
    AbstractGateway::TX_REFUND => [
        'path' => '/regular/refund.php',
        'label' => 'İade',
    ],
];


$installments = [
    0  => 'Peşin',
    2  => '2 Taksit',
    6  => '6 Taksit',
    12 => '12 Taksit',
];

function getNewOrder(
    string $baseUrl,
    string $ip,
    string $currency,
    \Symfony\Component\HttpFoundation\Session\Session $session,
    ?int $installment = 0,
    bool $tekrarlanan = false,
    string $lang = AbstractGateway::LANG_TR
): array {
    $order = createNewPaymentOrderCommon($baseUrl, $ip, $currency, $installment, $lang);

    $order['extraData'] = $session->getId(); //optional, istekte SessionInfo degere atanir

    return $order;
}

function doPayment(\Mews\Pos\PosInterface $pos, string $paymentModel, string $transaction, ?\Mews\Pos\Entity\Card\AbstractCreditCard $card)
{
    if (\Mews\Pos\Gateways\AbstractGateway::TX_POST_PAY !== $transaction) {
        /**
         * diger banklaradan farkli olarak 3d islemler icin de PayFlex bu asamada kredi kart bilgileri istiyor
         */
        $pos->payment($paymentModel, $card);
    } else {
        $pos->payment($paymentModel);
    }
}

$testCards = [
    'visa1' => [
        // NOTE: 3D Secure sifre 123456
        'number' => '4938460158754205',
        'year' => '24',
        'month' => '11',
        'cvv' => '715',
        'name' => 'John Doe',
        'type' => AbstractCreditCard::CARD_TYPE_VISA,
    ],
];
