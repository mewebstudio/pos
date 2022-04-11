<?php

use Mews\Pos\Entity\Card\CreditCardVakifBank;
use Symfony\Component\HttpFoundation\RedirectResponse;

require '_config.php';
require '../../template/_header.php';

if ($request->getMethod() !== 'POST') {
    echo new RedirectResponse($baseUrl.'index.php');
    exit();
}

$order = getNewOrder($baseUrl, $ip, $session, $request->get('installment'));
$session->set('order', $order);

$card = new CreditCardVakifBank(
    $request->get('number'),
    $request->get('year'),
    $request->get('month'),
    $request->get('cvv'),
    $request->get('name'),
    $request->get('type')
);

/**
 * provizyonu (odemeyi) tamamlamak icin tekrar kredi kart bilgileri isteniyor, bu yuzden kaydediyoruz
 */
$session->set('card', [
    'number' => $request->get('number'),
    'year'   => $request->get('year'),
    'month'  => $request->get('month'),
    'cvv'    => $request->get('cvv'),
    'name'   => $request->get('name'),
    'type'   => $request->get('type'),
]);

$pos->prepare($order, $transaction, $card);
try {
    $formData = $pos->get3DFormData();
} catch (\Exception $e) {
    dd($e);
}

require '../../template/_redirect_form.php';
require '../../template/_footer.php';
