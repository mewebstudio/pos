<?php

require __DIR__.'/../_main_config.php';

$installments = [
    0  => 'Peşin',
    2  => '2 Taksit',
    6  => '6 Taksit',
    12 => '12 Taksit',
];

function getNewOrder(string $baseUrl, ?int $installment = 0)
{
    $amount = 320.00;

    $successUrl = $baseUrl.'response.php';
    $failUrl = $baseUrl.'response.php';

    $rand = microtime();
    $orderId = date('Ymd').strtoupper(substr(uniqid(sha1(time())), 0, 4));

    $order = [
        'id'          => $orderId,
        'amount'      => $amount,
        'installment' => $installment,
        'currency'    => 'TRY',
        'success_url' => $successUrl,
        'fail_url'    => $failUrl,
        'lang'        => \Mews\Pos\Gateways\InterPos::LANG_TR,
        'rand'        => $rand,
        // todo tekrarlanan odemeler icin daha fazla bilgi lazim, Deniz bank dokumantasyonunda hic bir aciklama yok
        //  ornek kodlarda ise sadece bu alttaki 2 veriyi gondermis.
        //'MaturityPeriod' => 1,
        //'PaymentFrequency' => 2,
    ];

    return $order;
}

$testCards = [
    'visa1' => new \Mews\Pos\Entity\Card\CreditCardInterPos(
        '4090700090840057',
        22,
        11,
        592,
        'John Doe',
        'visa'
    ),
    'visa2' => new \Mews\Pos\Entity\Card\CreditCardInterPos(
        '4090700101174272',
        22,
        12,
        104,
        'John Doe',
        'visa'
    ),
];
