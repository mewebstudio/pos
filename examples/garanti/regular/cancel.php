<?php

require '_config.php';
$templateTitle = 'Cancel Order';
require '../../_templates/_header.php';

use Mews\Pos\Gateways\AbstractGateway;

$ord = $session->get('order') ?: getNewOrder($baseUrl, $ip, $request->get('currency', 'TRY'), $session);

$order = [
    'id'          => $ord['id'],
    'ip'          => $ord['ip'],
    'email'       => $ord['email'],
    'amount'      => $ord['amount'],
    'currency'    => $ord['currency'],
    'ref_ret_num' => $session->get('ref_ret_num'),
];
$transaction = AbstractGateway::TX_CANCEL;
$pos->prepare($order, $transaction);
// Cancel Order
$pos->cancel();

$response = $pos->getResponse();
require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
