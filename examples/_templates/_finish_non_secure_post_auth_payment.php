<?php

use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\PosInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

// dinamik olarak ilgili bunkanin regular klasor altindaki _config.php yuklenir
// ornegin: payten/regular/_config.php
require_once '_config.php';
require '../../_templates/_header.php';

/**
 * alttaki script
 * MODEL_NON_SECURE ve TX_TYPE_PAY_POST_AUTH odemede kredi kart bilgileri olmadan Ön Otorizasyon İşlemi tamamlar.
 */
if (PosInterface::TX_TYPE_PAY_POST_AUTH !== $transaction) {
    echo new RedirectResponse($baseUrl);
    exit();
}

try {
    /** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */
    $eventDispatcher->addListener(RequestDataPreparedEvent::class, function (RequestDataPreparedEvent $event) {
//         Burda istek banka API'na gonderilmeden once gonderilecek veriyi degistirebilirsiniz.
//         Ornek:
//         $data = $event->getRequestData();
//         $data['abcd'] = '1234';
//         $event->setRequestData($data);
    });

    dump($order);
    doPayment($pos, $paymentModel, $transaction, $order, null);
} catch (Exception $e) {
    dd($e);
}
$response = $pos->getResponse();

if ($pos->isSuccess()) {
    $session->set('last_response', $response);
}

require __DIR__.'/_render_payment_response.php';
require __DIR__.'/_footer.php';
