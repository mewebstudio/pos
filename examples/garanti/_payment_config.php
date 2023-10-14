<?php

use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\PosInterface;

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/garanti';
$posClass = \Mews\Pos\Gateways\GarantiPos::class;

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
        $order['recurringFrequencyType'] = 'MONTH';
        $order['recurringFrequency'] = 2;
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
/*    'visa1' => [
        'number' => '4282209004348015',
        'year' => '30',
        'month' => '08',
        'cvv' => '123',
        'name' => 'John Doe',
        'type' => AbstractCreditCard::CARD_TYPE_VISA,
    ],*/
    'visa1' => [
        // pin 147852
        'number' => '5549604173790011',
        'year' => '24',
        'month' => '02',
        'cvv' => '423',
        'name' => 'John Doe',
        'type' => AbstractCreditCard::CARD_TYPE_MASTERCARD,
    ],
    // test kartlar https://dev.garantibbva.com.tr/test-kartlari
    'visa2' => [
        // pin 147852
        'number' => '5406697543211173',
        'year' => '27',
        'month' => '04',
        'cvv' => '423',
        'name' => 'John Doe',
        'type' => AbstractCreditCard::CARD_TYPE_MASTERCARD,
    ],
];
