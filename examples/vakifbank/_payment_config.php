<?php

use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Gateways\AbstractGateway;

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/vakifbank';

$subMenu = [
    AbstractGateway::MODEL_3D_SECURE => [
        'path' => '/3d/index.php',
        'label' => '3D Ödeme',
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
    if ($tekrarlanan) {
        $order['installment'] = 0; //Tekrarlayan ödemeler taksitli olamaz.
        $order = array_merge($order, [
            //tekrarlanan odemeler icin (optional):
            'recurringFrequency'        => 3,
            'recurringFrequencyType'    => 'MONTH', //DAY|MONTH|YEAR
            //recurring işlemin toplamda kaç kere tekrar edeceği bilgisini içerir
            'recurringInstallmentCount' => $installment,
            'recurringEndDate'          => '202112', //optional
            // yukardaki belirtilen ayarin anlami 3 ayda bir kesintip yap ve bunu toplam 4 kere tekrarla.
        ]);
    }

    return $order;
}

function doPayment(\Mews\Pos\PosInterface $pos, string $transaction, ?\Mews\Pos\Entity\Card\AbstractCreditCard $card)
{
    if (\Mews\Pos\Gateways\AbstractGateway::TX_POST_PAY !== $transaction) {
        /**
         * diger banklaradan farkli olarak 3d islemler icin de Vakifbank bu asamada kredi kart bilgileri istiyor
         */
        $pos->payment($card);
    } else {
        $pos->payment();
    }
}

$testCards = [
    'visa1' => [
        'number' => '4543600299100712',
        'year' => '23',
        'month' => '11',
        'cvv' => '454',
        'name' => 'John Doe',
        'type' => AbstractCreditCard::CARD_TYPE_VISA,
    ],
];
