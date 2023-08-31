<?php

use Mews\Pos\PosInterface;

require_once '_config.php';

$order = getNewOrder(
    $baseUrl,
    $ip,
    $request->get('currency', 'TRY'),
    $session,
    $request->get('installment'),
    $request->get('is_recurring', 0) == 1,
    $request->get('lang', PosInterface::LANG_TR)
);
$session->set('order', $order);
$transaction = $request->get('tx', PosInterface::TX_PAY);

$card = createCard($pos, $request->request->all());

require '../../_templates/_payment_response.php';
