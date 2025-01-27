<?php

use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\PosInterface;

// ilgili bankanin _config.php dosyasi load ediyoruz.
// ornegin /examples/finansbank-payfor/3d-host/_config.php
require '_config.php';

require '../../_templates/_header.php';

$order = createPaymentOrder(
    $pos,
    $paymentModel,
    $baseUrl,
    $ip,
    $request->get('currency', PosInterface::CURRENCY_TRY),
    $request->get('installment'),
    false,
    $request->get('lang', PosInterface::LANG_TR)
);
$session->set('order', $order);

$formVerisiniOlusturmakIcinApiIstegiGonderenGatewayler = [
    \Mews\Pos\Gateways\PosNet::class,
    \Mews\Pos\Gateways\KuveytPos::class,
    \Mews\Pos\Gateways\ToslaPos::class,
    \Mews\Pos\Gateways\VakifKatilimPos::class,
    \Mews\Pos\Gateways\PayFlexV4Pos::class,
    \Mews\Pos\Gateways\PayFlexCPV4Pos::class,
];
if (in_array(get_class($pos), $formVerisiniOlusturmakIcinApiIstegiGonderenGatewayler, true)) {
    /** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */
    $eventDispatcher->addListener(RequestDataPreparedEvent::class, function (RequestDataPreparedEvent $event) {
//        // Burda istek banka API'na gonderilmeden once gonderilecek veriyi degistirebilirsiniz.
//        // Ornek:
//        if ($event->getTxType() === PosInterface::TX_TYPE_PAY_AUTH) {
//            $data         = $event->getRequestData();
//            $data['abcd'] = '1234';
//            $event->setRequestData($data);
//        }
    });
}


/**
 * Bu Event'i dinleyerek 3D formun hash verisi hesaplanmadan önce formun input array içireğini güncelleyebilirsiniz.
 */
$eventDispatcher->addListener(Before3DFormHashCalculatedEvent::class, function (Before3DFormHashCalculatedEvent $event) {
//    if ($event->getGatewayClass() !== \Mews\Pos\Gateways\EstV3Pos::class || $event->getGatewayClass() !== \Mews\Pos\Gateways\EstPos::class) {
//        return;
//    }
//    // Örneğin İşbank İmece Kart ile ödeme yaparken aşağıdaki verilerin eklenmesi gerekiyor:
//    $supportedPaymentModels = [
//        \Mews\Pos\PosInterface::MODEL_3D_PAY,
//        \Mews\Pos\PosInterface::MODEL_3D_PAY_HOSTING,
//        \Mews\Pos\PosInterface::MODEL_3D_HOST,
//    ];
//    if ($event->getTxType() === PosInterface::TX_TYPE_PAY_AUTH && in_array($event->getPaymentModel(), $supportedPaymentModels, true)) {
//        $formInputs           = $event->getFormInputs();
//        $formInputs['IMCKOD'] = '9999'; // IMCKOD bilgisi bankadan alınmaktadır.
//        $formInputs['FDONEM'] = '5'; // Ödemenin faizsiz ertelenmesini istediğiniz dönem sayısı.
//        $event->setFormInputs($formInputs);
//    }
});

try {
    $formData = $pos->get3DFormData(
        $order,
        PosInterface::MODEL_3D_HOST,
        $transaction
    );
} catch (\LogicException $e) {
    // ödeme modeli veya işlem tipi desteklenmiyorsa bu exception'i alırsınız.
    dd($e);
} catch (\Exception $e) {
    dd($e);
}

require '../../_templates/_redirect_form.php';
require '../../_templates/_footer.php';
