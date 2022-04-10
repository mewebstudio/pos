<?php

require __DIR__.'/../_main_config.php';

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$ip = $request->getClientIp();

$installments = [
    0  => 'Peşin',
    2  => '2 Taksit',
    6  => '6 Taksit',
    12 => '12 Taksit',
];

function getGateway(\Mews\Pos\Entity\Account\AbstractPosAccount $account): ?\Mews\Pos\PosInterface
{
    try {
        $pos = \Mews\Pos\Factory\PosFactory::createPosGateway($account);
        $pos->setTestMode(true);

        return $pos;
    } catch (\Mews\Pos\Exceptions\BankNotFoundException $e) {
        dump($e->getCode(), $e->getMessage());
    } catch (\Mews\Pos\Exceptions\BankClassNullException $e) {
        dump($e->getCode(), $e->getMessage());
    }

    return null;
}

function getNewOrder(
    string $baseUrl,
    ?int $installment = 0
): array {
    $successUrl = $baseUrl.'response.php';
    $failUrl = $baseUrl.'response.php';

    $orderId = date('Ymd').strtoupper(substr(uniqid(sha1(time())), 0, 4));
    $amount = 1.0;

    $order = [
        'id'          => $orderId,
        'amount'      => $amount,
        'installment' => $installment,
        'currency'    => 'TRY',
        'success_url' => $successUrl,
        'fail_url'    => $failUrl,
        'lang'        => \Mews\Pos\Gateways\PosNet::LANG_TR,
    ];

    return $order;
}

$testCards = [
    'visa1' => new \Mews\Pos\Entity\Card\CreditCardPosNet(
        '4543600299100712',
        23,
        11,
        454,
        'John Doe',
        'visa'
    ),
];
