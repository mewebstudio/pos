<?php

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/akbank';

$installments = [
    0  => 'Peşin',
    2  => '2 Taksit',
    6  => '6 Taksit',
    12 => '12 Taksit',
];

function getNewOrder(
    string $baseUrl,
    string $ip,
    ?int $installment = 0,
    bool $tekrarlanan = false
): array {
    $successUrl = $baseUrl.'response.php';
    $failUrl = $baseUrl.'response.php';

    $orderId = date('Ymd').strtoupper(substr(uniqid(sha1(time())), 0, 4));
    $amount = 1.01;

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
        'lang'        => \Mews\Pos\Gateways\EstPos::LANG_TR,
        'rand'        => $rand,
    ];

    if ($tekrarlanan) {
        //tekrarlanan odemeler icin (optional):
        $order['recurringFrequency'] = 3;
        $order['recurringFrequencyType'] = 'MONTH'; //DAY|WEEK|MONTH|YEAR
        //recurring işlemin toplamda kaç kere tekrar edeceği bilgisini içerir
        $order['recurringInstallmentCount'] = 4;
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
    'visa1' => new \Mews\Pos\Entity\Card\CreditCardEstPos(
        '4355084355084358',
        30,
        12,
        '000',
        'John Doe',
        'visa'
    ),
];
