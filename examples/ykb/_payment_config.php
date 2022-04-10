<?php

require __DIR__.'/../_main_config.php';

$installments = [
    0  => 'Peşin',
    2  => '2 Taksit',
    6  => '6 Taksit',
    12 => '12 Taksit',
];

function getNewOrder(
    string $baseUrl,
    ?int $installment = 0
): array {
    $successUrl = $baseUrl.'response.php';
    $failUrl = $baseUrl.'response.php';

    $orderId = date('Ymd').strtoupper(substr(uniqid(sha1(time())), 0, 4));
    $amount = 1.0;

    $order = [
        'id'          => $orderId,
        'amount'      => $amount,
        'installment' => $installment,
        'currency'    => 'TRY',
        'success_url' => $successUrl,
        'fail_url'    => $failUrl,
        'lang'        => \Mews\Pos\Gateways\PosNet::LANG_TR,
    ];

    return $order;
}

$testCards = [
    'visa1' => new \Mews\Pos\Entity\Card\CreditCardPosNet(
        '4543600299100712',
        23,
        11,
        454,
        'John Doe',
        'visa'
    ),
];
