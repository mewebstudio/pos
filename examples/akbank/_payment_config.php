<?php

use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\PosInterface;

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/akbank';
$posClass = \Mews\Pos\Gateways\EstV3Pos::class;

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
    $order = createNewPaymentOrderCommon($baseUrl, $ip, $currency, $installment, $lang);

    if ($tekrarlanan) {
        $order['installment'] = 0; //Tekrarlayan ödemeler taksitli olamaz.
        //tekrarlanan odemeler icin (optional):
        $order['recurringFrequency'] = 3;
        $order['recurringFrequencyType'] = 'MONTH'; //DAY|WEEK|MONTH|YEAR
        //recurring işlemin toplamda kaç kere tekrar edeceği bilgisini içerir
        $order['recurringInstallmentCount'] = $installment;
    }

    return $order;
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
    'visa2' => [
        'number' => '4355084355084358',
        'year' => '30',
        'month' => '12',
        'cvv' => '000',
        'name' => 'John Doe',
        'type' => AbstractCreditCard::CARD_TYPE_VISA,
    ],
    'visaZiraat' => [
        'number' => '4546711234567894',
        'year' => '26',
        'month' => '12',
        'cvv' => '000',
        'name' => 'John Doe',
        'type' => AbstractCreditCard::CARD_TYPE_VISA,
    ],
    'masterZiraat' => [
        'number' => '5401341234567891',
        'year' => '26',
        'month' => '12',
        'cvv' => '000',
        'name' => 'John Doe',
        'type' => AbstractCreditCard::CARD_TYPE_MASTERCARD,
    ],
    'visa1' => [
        'number' => '4546711234567894',
        'year' => '26',
        'month' => '12',
        'cvv' => '000',
        'name' => 'John Doe',
        'type' => AbstractCreditCard::CARD_TYPE_VISA,
    ],
    'visa_isbank_imece' => [
        /**
         * IMECE kartlar isbankin tarima destek icin ozel kampanyalari olan kartlardir.
         * https://www.isbank.com.tr/is-ticari/imece-kart
         *
         * bu karti test edebilmek icin bu kartlarla odemeyi destekleyen Isbank Pos hesabi lazim.
         */
        'number' => '4242424242424242',
        'year'   => '2028',
        'month'  => '10',
        'cvv'    => '123',
        'name'   => 'John Doe',
        'type'   => AbstractCreditCard::CARD_TYPE_VISA,
    ],
];
