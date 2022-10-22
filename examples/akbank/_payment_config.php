<?php

use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Gateways\AbstractGateway;

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/akbank';
$subMenu = [
    AbstractGateway::MODEL_3D_SECURE => [
        'path' => '/3d/index.php',
        'label' => '3D Ödeme',
    ],
    AbstractGateway::MODEL_3D_PAY => [
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
    AbstractGateway::TX_STATUS => [
        'path' => '/regular/status.php',
        'label' => 'Ödeme Durumu',
    ],
    AbstractGateway::TX_CANCEL => [
        'path' => '/regular/cancel.php',
        'label' => 'İptal',
    ],
    AbstractGateway::TX_REFUND => [
        'path' => '/regular/refund.php',
        'label' => 'İade',
    ],
    AbstractGateway::TX_HISTORY => [
        'path' => '/regular/history.php',
        'label' => 'History',
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

    if ($tekrarlanan) {
        $order['installment'] = 0; //Tekrarlayan ödemeler taksitli olamaz.
        //tekrarlanan odemeler icin (optional):
        $order['recurringFrequency'] = 3;
        $order['recurringFrequencyType'] = 'MONTH'; //DAY|WEEK|MONTH|YEAR
        //recurring işlemin toplamda kaç kere tekrar edeceği bilgisini içerir
        $order['recurringInstallmentCount'] = $installment;
    }

    return $order;
}

function doPayment(\Mews\Pos\PosInterface $pos, string $transaction, ?\Mews\Pos\Entity\Card\AbstractCreditCard $card)
{
    if ($pos->getAccount()->getModel() === AbstractGateway::MODEL_NON_SECURE
        && AbstractGateway::TX_POST_PAY !== $transaction
    ) {
        //bu asamada $card regular/non secure odemede lazim.
        $pos->payment($card);
    } else {
        $pos->payment();
    }
}

$testCards = [
    'visa2' => [
        'number' => '4355084355084358',
        'year' => '30',
        'month' => '12',
        'cvv' => '000',
        'name' => 'John Doe',
        'type' => AbstractCreditCard::CARD_TYPE_VISA,
    ],
    'visaZiraat' => [
        'number' => '4546711234567894',
        'year' => '26',
        'month' => '12',
        'cvv' => '000',
        'name' => 'John Doe',
        'type' => AbstractCreditCard::CARD_TYPE_VISA,
    ],
    'masterZiraat' => [
        'number' => '5401341234567891',
        'year' => '26',
        'month' => '12',
        'cvv' => '000',
        'name' => 'John Doe',
        'type' => AbstractCreditCard::CARD_TYPE_MASTERCARD,
    ],
    'visa1' => [
        'number' => '4546711234567894',
        'year' => '26',
        'month' => '12',
        'cvv' => '000',
        'name' => 'John Doe',
        'type' => AbstractCreditCard::CARD_TYPE_VISA,
    ],
];
