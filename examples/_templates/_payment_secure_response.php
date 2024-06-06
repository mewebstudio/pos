<?php

use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\PosInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

// ilgili gatewayin payment modele gore configini load ediyoruz
// ornegin: payten/3d/_config.php ya da payten/3d-host/_config.php
require_once '_config.php';
require '../../_templates/_header.php';

/**
 * alttaki script
 * MODEL_3D_SECURE, MODEL_3D_PAY, MODEL_3D_HOST odeme modellerde ve TX_TYPE_PAY_AUTH, TX_TYPE_PAY_PRE_AUTH islem
 * tiplerinde gatewayden geri websitenize yonlendirildiginde calisir.
 *
 * Bu script redirectli, iframe'de ve popup'da odemeler icin kullanilabilinir.
 */
// 3D odemelerde gatewayden genelde POST istek bekleniyor.
if (($request->getMethod() !== 'POST')
    // PayFlex-CP GET request ile cevapliyor
    && ($request->getMethod() === 'GET'
        && (get_class($pos) !== \Mews\Pos\Gateways\PayFlexCPV4Pos::class || [] === $request->query->all()))
) {
    echo new RedirectResponse($baseUrl);
    exit();
}

$order = $session->get('order');
if (!$order) {
    throw new Exception('Sipariş bulunamadı, session sıfırlanmış olabilir.');
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
     * Bu asamada bu Event genellikle 1 kere trigger edilir.
     * Bir tek PosNet MODEL_3D_SECURE odemede 2 kere API call'i yapildigi icin bu event 2 kere trigger edilir.
     */

    /**
     * KOICodes
     * 1: Ek Taksit
     * 2: Taksit Atlatma
     * 3: Ekstra Puan
     * 4: Kontur Kazanım
     * 5: Ekstre Erteleme
     * 6: Özel Vade Farkı
     */
    if ($event->getGatewayClass() instanceof \Mews\Pos\Gateways\PosNetV1Pos && $event->getTxType() === PosInterface::TX_TYPE_PAY_AUTH) {
        // Albaraka PosNet KOICode ekleme
        // $data            = $event->getRequestData();
        // $data['KOICode'] = '1';
        // $event->setRequestData($data);
    }
});


//  // Isbank İMECE için ekstra alanların eklenme örneği
    $eventDispatcher->addListener(RequestDataPreparedEvent::class, function (RequestDataPreparedEvent $event) {
//        if ($event->getPaymentModel() === PosInterface::MODEL_3D_SECURE && $event->getTxType() === PosInterface::TX_TYPE_PAY_AUTH) {
//            $data                    = $event->getRequestData();
//            $data['Extra']['IMCKOD'] = '9999'; // IMCKOD bilgisi bankadan alınmaktadır.
//            $data['Extra']['FDONEM'] = '5'; // Ödemenin faizsiz ertelenmesini istediğiniz dönem sayısı
//            $event->setRequestData($data);
//        }
    });

$card = null;
if (get_class($pos) === \Mews\Pos\Gateways\PayFlexV4Pos::class) {
    // bu gateway için ödemeyi tamamlarken tekrar kart bilgisi lazım.
    $savedCard = $session->get('card');
    $session->remove('card');
    $card      = createCard($pos, $savedCard);
}
// ============================================================================================
// OZEL DURUMLAR ICIN KODLAR END
// ============================================================================================

try {
    doPayment($pos, $paymentModel, $transaction, $order, $card);
} catch (HashMismatchException $e) {
    dd($e);
} catch (\Exception|\Error $e) {
    dd($e);
}
$response = $pos->getResponse();

if ($pos->isSuccess()) {
    $session->set('last_response', $response);
}

require __DIR__.'/_render_payment_response.php';
?>

<script>
    if (window.opener && window.opener !== window) {
        // you are in a popup
        // send result data to parent window
        window.opener.parent.postMessage(`<?= base64_encode(json_encode($response)); ?>`);
    } else if (window.parent) {
        // you are in iframe
        // send result data to parent window
        window.parent.postMessage(`<?= base64_encode(json_encode($response)); ?>`);
    }
</script>
<?php require __DIR__.'/_footer.php'; ?>
