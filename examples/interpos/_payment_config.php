<?php

use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Gateways\AbstractGateway;

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/interpos';

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
];


$installments = [
    0  => 'Peşin',
    2  => '2 Taksit',
    6  => '6 Taksit',
    12 => '12 Taksit',
];

function getNewOrder(string $baseUrl, ?int $installment = 0)
{
    $amount = 30.0;

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
    'visa1' => new \Mews\Pos\Entity\Card\CreditCardInterPos(
        '4090700090840057',
        22,
        11,
        592,
        'John Doe',
        AbstractCreditCard::CARD_TYPE_VISA
    ),
    'visa2' => new \Mews\Pos\Entity\Card\CreditCardInterPos(
        '4090700101174272',
        22,
        12,
        104,
        'John Doe',
        AbstractCreditCard::CARD_TYPE_VISA
    ),
];
