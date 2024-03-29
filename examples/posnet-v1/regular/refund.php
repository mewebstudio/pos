<?php

use Mews\Pos\Gateways\AbstractGateway;

require '_config.php';
$templateTitle = 'Refund Order';
require '../../_templates/_header.php';

$ord = $session->get('order') ?: getNewOrder($baseUrl, $ip, $request->get('currency', 'TRY'), $session);

$transaction = AbstractGateway::TX_REFUND;
$pos->prepare([
    // order id veya ref_ret_num (ReferenceCode) saglanmasi gerekiyor. Ikisinden biri zorunlu.
    // daha iyi performance icin ref_ret_num tercih edilmelidir.
    'id'          => $ord['id'],
    'ref_ret_num' => $session->get('ref_ret_num'),
    'amount'      => $ord['amount'],
    'currency'    => $ord['currency'],
], $transaction);

// Refund Order
$pos->refund();

$response = $pos->getResponse();
require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
