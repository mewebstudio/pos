<?php

require '_config.php';
$templateTitle = 'Refund Order';
require '../../_templates/_header.php';

use Mews\Pos\PosInterface;

$ord = $session->get('order') ?: getNewOrder($baseUrl, $ip, $request->get('currency', PosInterface::CURRENCY_TRY));

$order = [
    'id'          => $ord['id'],
    'ip'          => $ord['ip'],
    'email'       => $ord['email'],
    'amount'      => $ord['amount'],
    'currency'    => $ord['currency'],
    'ref_ret_num' => '831803586333',
];
$transaction = PosInterface::TX_REFUND;

$pos->refund($order);

$response = $pos->getResponse();
require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
