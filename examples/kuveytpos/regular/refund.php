<?php

use Mews\Pos\Gateways\AbstractGateway;

require '_config.php';
$templateTitle = 'Refund Order';
require '../../_templates/_header.php';

$lastResponse = $session->get('last_response');

// Refund Order
$order       = [
    'id'              => $lastResponse['order_id'], // MerchantOrderId
    'remote_order_id' => $lastResponse['remote_order_id'], // OrderId
    'ref_ret_num'     => $lastResponse['ref_ret_num'],
    'auth_code'       => $lastResponse['auth_code'],
    'trans_id'        => $lastResponse['trans_id'],
    'amount'          => $lastResponse['amount'],
    'currency'        => $lastResponse['currency'],
];

/*$order = [
    "id"              => "202307093C2D2",
    "remote_order_id" => "1142936252",
    "ref_ret_num"     => "319013298458",
    "auth_code"       => "241855",
    "trans_id"        => "298458",
    "amount"          => 1.01,
    "currency"        => "TRY",
];*/

$transaction = AbstractGateway::TX_REFUND;
$pos->prepare($order, $transaction);

$pos->refund();

$response = $pos->getResponse();
require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
