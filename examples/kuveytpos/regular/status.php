<?php

use Mews\Pos\Gateways\AbstractGateway;

require '_config.php';
$templateTitle = 'Order Status';
require '../../_templates/_header.php';

$ord = $session->get('order');
$lastResponse = $session->get('last_response');

if ($lastResponse) {
    $order = [
        'id'              => $lastResponse['order_id'], // MerchantOrderId
        'remote_order_id' => $lastResponse['remote_order_id'], // OrderId
        'currency'        => $lastResponse['currency'],
    ];
} else {
    $order = [
        'id' => $ord ? $ord['id'] : '2023070849CD', //MerchantOrderId

        // varsa remote_order_id (bankadan donen OrderId) de saglanmasi gerekiyor
        //'remote_order_id' => $ord ? $ord['remote_order_id'] : '114293600', // OrderId

        'currency' => $ord ? $ord['currency'] : 'TRY',
    ];
}

$transaction = AbstractGateway::TX_STATUS;
$pos->prepare($order, $transaction);

$pos->status();

$response = $pos->getResponse();
require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
