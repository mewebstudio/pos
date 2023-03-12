<?php

require '_config.php';
$templateTitle = 'Refund Order';
require '../../_templates/_header.php';

use Mews\Pos\Gateways\AbstractGateway;

$ord = $session->get('order') ?: getNewOrder($baseUrl, $ip, $request->get('currency', 'TRY'), $session);

$order = [
    'id'          => $ord['id'],
    'ip'          => $ord['ip'],
    'email'       => $ord['email'],
    'amount'      => $ord['amount'],
    'currency'    => $ord['currency'],
    'ref_ret_num' => '831803586333',
];
$transaction = AbstractGateway::TX_REFUND;
$pos->prepare($order, $transaction);

$pos->refund();

$response = $pos->getResponse();
require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
