<?php

use Symfony\Component\HttpFoundation\RedirectResponse;

require '_config.php';
require '../../_templates/_header.php';

if ($request->getMethod() !== 'POST') {
    echo new RedirectResponse($baseUrl.'index.php');
    exit();
}
$transaction = $request->get('tx', \Mews\Pos\Gateways\AbstractGateway::TX_PAY);
$order = getNewOrder(
    $baseUrl,
    $ip,
    $request->get('currency', 'TRY'),
    $session,
    $request->get('installment'),
    $request->get('is_recurring', 0) == 1,
    $request->get('lang', \Mews\Pos\Gateways\AbstractGateway::LANG_TR)
);
$session->set('order', $order);

$card = createCard($pos, $request->request->all());

/**
 * Vakifbank'ta provizyonu (odemeyi) tamamlamak icin tekrar kredi kart bilgileri isteniyor, bu yuzden kart bilgileri kaydediyoruz
 */
$session->set('card', $request->request->all());

$pos->prepare($order, $transaction, $card);

try {
    $formData = $pos->get3DFormData();
    //dd($formData);
} catch (\Throwable $e) {
    dd($e);
}


require '../../_templates/_redirect_form.php';
require '../../_templates/_footer.php';
