<?php

use Mews\Pos\PosInterface;

// ilgili bankanin _config.php dosyasi load ediyoruz.
// ornegin /examples/finansbank-payfor/regular/_config.php
require_once '_config.php';

$order = getNewOrder(
    $baseUrl,
    $ip,
    $request->get('currency', PosInterface::CURRENCY_TRY),
    $request->get('installment'),
    $request->get('is_recurring', 0) == 1,
    $request->get('lang', PosInterface::LANG_TR)
);
$session->set('order', $order);
$transaction = $request->get('tx', PosInterface::TX_TYPE_PAY);

// examples'da post odeme butonu gostermek icin degeri kullanilir.
$session->set('tx', $transaction);

$card = createCard($pos, $request->request->all());

require '../../_templates/_payment_response.php';
