<?php

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
    \Symfony\Component\HttpFoundation\Session\Session $session,
    ?int $installment = 0,
    bool $tekrarlanan = false
): array {
    $successUrl = $baseUrl.'response.php';
    $failUrl = $baseUrl.'response.php';

    $orderId = date('Ymd').strtoupper(substr(uniqid(sha1(time())), 0, 4));
    $amount = 1.01;

    $order = [
        'id'                        => $orderId,
        'amount'                    => $amount,
        'installment'               => $installment,
        'currency'                  => 'TRY',
        'success_url'               => $successUrl,
        'fail_url'                  => $failUrl,
        'rand'                      => time(),
        'ip'                        => $ip,
        'extraData'                 => $session->getId(), //optional, istekte SessionInfo degere atanir
    ];
    if ($tekrarlanan) {
        $order = array_merge($order, [
            //tekrarlanan odemeler icin (optional):
            'recurringFrequency'        => 3,
            'recurringFrequencyType'    => 'MONTH', //DAY|MONTH|YEAR
            //recurring işlemin toplamda kaç kere tekrar edeceği bilgisini içerir
            'recurringInstallmentCount' => 4,
            'recurringEndDate'          => '202112', //optional
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
    'visa1' => new \Mews\Pos\Entity\Card\CreditCardVakifBank(
        '4543600299100712',
        23,
        11,
        454,
        'John Doe',
        'visa'
    ),
];
