<?php

use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\PosInterface;

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/interpos';
$posClass = \Mews\Pos\Gateways\InterPos::class;

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
    string $lang = PosInterface::LANG_TR
): array {
    // todo tekrarlanan odemeler icin daha fazla bilgi lazim, Deniz bank dokumantasyonunda hic bir aciklama yok
    //  ornek kodlarda ise sadece bu alttaki 2 veriyi gondermis.
    //'MaturityPeriod' => 1,
    //'PaymentFrequency' => 2,

    return createNewPaymentOrderCommon($baseUrl, $ip, $currency, $installment, $lang);
}

function doPayment(PosInterface $pos, string $paymentModel, string $transaction, array $order, ?\Mews\Pos\Entity\Card\AbstractCreditCard $card)
{
    if ($paymentModel === PosInterface::MODEL_NON_SECURE
        && PosInterface::TX_POST_PAY !== $transaction
    ) {
        //bu asamada $card regular/non secure odemede lazim.
        $pos->payment($paymentModel, $order, $transaction, $card);
    } else {
        $pos->payment($paymentModel, $order, $transaction);
    }
}

$testCards = [
    'visa1' => [
        'number' => '4090700090840057',
        'year' => '22',
        'month' => '1',
        'cvv' => '592',
        'name' => 'John Doe',
        'type' => AbstractCreditCard::CARD_TYPE_VISA,
    ],
    'visa2' => [
        'number' => '4090700101174272',
        'year' => '22',
        'month' => '12',
        'cvv' => '104',
        'name' => 'John Doe',
        'type' => AbstractCreditCard::CARD_TYPE_VISA,
    ],
];
