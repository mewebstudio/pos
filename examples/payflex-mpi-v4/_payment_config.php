<?php

use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\PosInterface;

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/payflex-mpi-v4';
$posClass = \Mews\Pos\Gateways\PayFlexV4Pos::class;

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

        $recurringFrequency = 3;
        $recurringFrequencyType = 'MONTH'; //DAY|MONTH|YEAR
        $endPeriod = $installment * $recurringFrequency;
        $order = array_merge($order, [
            //tekrarlanan odemeler icin (optional):
            'recurringFrequency'        => $recurringFrequency,
            'recurringFrequencyType'    => $recurringFrequencyType,
            //recurring işlemin toplamda kaç kere tekrar edeceği bilgisini içerir
            'recurringInstallmentCount' => $installment,
            'recurringEndDate'          => (new DateTime())->modify("+$endPeriod $recurringFrequencyType"),
            // yukardaki belirtilen ayarin anlami 3 ayda bir kesintip yap ve bunu toplam $installment kadar kere tekrarla.
        ]);
    }

    return $order;
}

function doPayment(PosInterface $pos, string $paymentModel, string $transaction, array $order, ?\Mews\Pos\Entity\Card\AbstractCreditCard $card)
{
    if (PosInterface::TX_POST_PAY !== $transaction) {
        /**
         * diger banklaradan farkli olarak 3d islemler icin de PayFlex MPI bu asamada kredi kart bilgileri istiyor
         */
        $pos->payment($paymentModel, $order, $transaction, $card);
    } else {
        $pos->payment($paymentModel, $order, $transaction);
    }
}

$testCards = [
    'visa1' => [
        // NOTE: 3D Secure sifre 123456
        'number' => '4938460158754205',
        'year' => '24',
        'month' => '11',
        'cvv' => '715',
        'name' => 'John Doe',
        'type' => AbstractCreditCard::CARD_TYPE_VISA,
    ],
];
