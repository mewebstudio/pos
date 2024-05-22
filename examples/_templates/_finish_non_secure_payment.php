<?php

use Mews\Pos\Event\RequestDataPreparedEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;

// dinamik olarak ilgili bunkanin regular klasor altindaki _config.php yuklenir
// ornegin: payten/regular/_config.php
require_once '_config.php';
require '../../_templates/_header.php';

/**
 * alttaki script
 * MODEL_NON_SECURE ve TX_TYPE_PAY_AUTH, TX_TYPE_PAY_PRE_AUTH odemede kullanicidan
 * kredi karti alindiktan sonra odemeyi tamamlar.
 */
// non secure odemede POST ile kredi kart bilgileri gelmesi bekleniyor.
if (($request->getMethod() !== 'POST')) {
    echo new RedirectResponse($baseUrl);
    exit();
}
// ============================================================================================
// OZEL DURUMLAR ICIN KODLAR START
// ============================================================================================
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */
$eventDispatcher->addListener(RequestDataPreparedEvent::class, function (RequestDataPreparedEvent $event) {
//         Burda istek banka API'na gonderilmeden once gonderilecek veriyi degistirebilirsiniz.
//         Ornek:
//         $data = $event->getRequestData();
//         $data['abcd'] = '1234';
//         $event->setRequestData($data);

    /**
     * KOICodes:
     * 1:Ek Taksit
     * 2: Taksit Atlatma
     * 3: Ekstra Puan
     * 4: Kontur Kazanım
     * 5: Ekstre Erteleme
     * 6: Özel Vade Farkı
     */
    if ($event->getGatewayClass() instanceof \Mews\Pos\Gateways\PosNetV1Pos) {
        // Albaraka PosNet KOICode ekleme
        // $data            = $event->getRequestData();
        // $data['KOICode'] = '1';
        // $event->setRequestData($data);
    }
    if ($event->getGatewayClass() instanceof \Mews\Pos\Gateways\PosNet) {
        // Yapikredi PosNet KOICode ekleme
        // $data            = $event->getRequestData();
        // $data['sale']['koiCode'] = '1';
        // $event->setRequestData($data);
    }
});
// ============================================================================================
// OZEL DURUMLAR ICIN KODLAR END
// ============================================================================================
try {
    doPayment($pos, $paymentModel, $transaction, $order, $card);
} catch (Exception $e) {
    dd($e);
}
$response = $pos->getResponse();

if ($pos->isSuccess()) {
    $session->set('last_response', $response);
}

require __DIR__.'/_render_payment_response.php';
require __DIR__.'/_footer.php';
