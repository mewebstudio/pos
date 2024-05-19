<?php

use Mews\Pos\PosInterface;

// ilgili bankanin _config.php dosyasi load ediyoruz.
// ornegin /examples/finansbank-payfor/regular/_config.php
require_once '_config.php';

$transaction = $request->get('tx', PosInterface::TX_TYPE_PAY_AUTH);

$order = createPaymentOrder(
    $pos,
    $paymentModel,
    $baseUrl,
    $ip,
    $request->get('currency', PosInterface::CURRENCY_TRY),
    $request->get('installment'),
    $request->get('is_recurring', 0) == 1,
    $request->get('lang', PosInterface::LANG_TR)
);

$card = createCard($pos, $request->request->all());

require '../../_templates/_finish_non_secure_payment.php';
