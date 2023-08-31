<?php

use Mews\Pos\PosInterface;

require '_config.php';
$templateTitle = 'Cancel Order';
require '../../_templates/_header.php';

$lastResponse = $session->get('last_response');

$order        = [
    'id'              => $lastResponse['order_id'], // MerchantOrderId
    'remote_order_id' => $lastResponse['remote_order_id'], // OrderId
    'ref_ret_num'     => $lastResponse['ref_ret_num'],
    'auth_code'       => $lastResponse['auth_code'],
    'trans_id'        => $lastResponse['trans_id'],
    'amount'          => $lastResponse['amount'],
    'currency'        => $lastResponse['currency'],
];

/*$order        = [
    'id'          => '202307093C2D',
    'remote_order_id'          => '114293625',
    'ref_ret_num' => '319013298458',
    'auth_code'   => '241855',
    'trans_id'    => '298458',
    'amount'      => 1.01,
    'currency'    => PosInterface::CURRENCY_TRY,
];*/

$transaction = PosInterface::TX_CANCEL;

// Cancel Order
$pos->cancel($order);

$response = $pos->getResponse();
require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
