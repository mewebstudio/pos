<?php

use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Gateways\AbstractGateway;

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/garanti';
$subMenu = [
    AbstractGateway::MODEL_3D_SECURE => [
        'path' => '/3d/index.php',
        'label' => '3D Ödeme',
    ],
    AbstractGateway::MODEL_3D_PAY => [
        'path' => '/3d-pay/index.php',
        'label' => '3D Pay Ödeme',
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
        $order['recurringFrequencyType'] = 'MONTH';
        $order['recurringFrequency'] = 2;
        $order['recurringInstallmentCount'] = $installment;
    }

    return $order;
}

function doPayment(\Mews\Pos\PosInterface $pos, string $transaction, ?\Mews\Pos\Entity\Card\AbstractCreditCard $card)
{
    if ($pos->getAccount()->getModel() === \Mews\Pos\Gateways\AbstractGateway::MODEL_NON_SECURE
        && \Mews\Pos\Gateways\AbstractGateway::TX_POST_PAY !== $transaction
    ) {
        //bu asamada $card regular/non secure odemede lazim.
        $pos->payment($card);
    } else {
        $pos->payment();
    }
}

$testCards = [
    'visa1' => [
        'number' => '4282209004348015',
        'year' => '22',
        'month' => '08',
        'cvv' => '123',
        'name' => 'John Doe',
        'type' => AbstractCreditCard::CARD_TYPE_VISA,
    ],
];
