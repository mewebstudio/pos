<?php

use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Gateways\AbstractGateway;

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/kuveytpos';

$subMenu = [
    AbstractGateway::MODEL_3D_SECURE => [
        'path' => '/3d/index.php',
        'label' => '3D Ödeme',
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
    ?int $installment = 0
): array {
    $successUrl = $baseUrl.'response.php';
    $failUrl = $baseUrl.'response.php';

    $orderId = date('Ymd').strtoupper(substr(uniqid(sha1(time())), 0, 4));

    $amount = 10.01;

    $order = [
        'id'          => $orderId,
        'amount'      => $amount,
        'installment' => $installment,
        'currency'    => 'TRY',
        'success_url' => $successUrl,
        'fail_url'    => $failUrl,
        'ip'          => $ip,
    ];

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
        'number' => '4155650100416111',
        'year' => '25',
        'month' => '1',
        'cvv' => '123',
        'name' => 'John Doe',
        'type' => AbstractCreditCard::CARD_TYPE_VISA,
    ],
];
