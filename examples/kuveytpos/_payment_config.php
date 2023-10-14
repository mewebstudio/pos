<?php

use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\PosInterface;

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/kuveytpos';
$posClass = \Mews\Pos\Gateways\KuveytPos::class;

function getNewOrder(
    string $baseUrl,
    string $ip,
    string $currency,
    \Symfony\Component\HttpFoundation\Session\Session $session,
    ?int $installment = 0,
    bool $tekrarlanan = false,
    string $lang = PosInterface::LANG_TR
): array {
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
        'number' => '4155650100416111',
        'year' => '25',
        'month' => '1',
        'cvv' => '123',
        'name' => 'John Doe',
        'type' => AbstractCreditCard::CARD_TYPE_VISA,
    ],
];
