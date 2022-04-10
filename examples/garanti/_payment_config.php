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
    string $ip,
    ?int $installment = 0
): array {
    $successUrl = $baseUrl.'response.php';
    $failUrl = $baseUrl.'response.php';

    $orderId = date('Ymd').strtoupper(substr(uniqid(sha1(time())), 0, 4));

    $amount = 1.0;

    $rand = microtime();

    $order = [
        'id'          => $orderId,
        'email'       => 'mail@customer.com', // optional
        'name'        => 'John Doe', // optional
        'amount'      => $amount,
        'installment' => $installment,
        'currency'    => 'TRY',
        'ip'          => $ip,
        'success_url' => $successUrl,
        'fail_url'    => $failUrl,
        'lang'        => \Mews\Pos\Gateways\GarantiPos::LANG_TR,
        'rand'        => $rand,
    ];

    return $order;
}

$testCards = [
    'visa1' => new \Mews\Pos\Entity\Card\CreditCardGarantiPos(
        '4090700101174272',
        30,
        12,
        '000',
        'John Doe',
        'visa'
    ),
];
