<?php

use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\PosInterface;

// ilgili bankanin _config.php dosyasi load ediyoruz.
// ornegin /examples/finansbank-payfor/3d-host/_config.php
require '_config.php';

require '../../_templates/_header.php';

$order = getNewOrder(
    $baseUrl,
    $ip,
    $request->get('currency', PosInterface::CURRENCY_TRY),
    $request->get('installment'),
    false,
    $request->get('lang', PosInterface::LANG_TR)
);
$session->set('order', $order);

/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */
$eventDispatcher->addListener(RequestDataPreparedEvent::class, function (RequestDataPreparedEvent $event) {
    /**
     * Burda istek banka API'na gonderilmeden once gonderilecek veriyi degistirebilirsiniz.
     * Ornek:
     * if ($event->getTxType() === PosInterface::TX_TYPE_PAY_AUTH) {
     *     $data = $event->getRequestData();
     *     $data['abcd'] = '1234';
     *     $event->setRequestData($data);
     * }
     *
     * Bu asamada bu Event sadece PosNet, PayFlexCPV4Pos, PayFlexV4Pos, KuveytPos gatewayler'de trigger edilir.
     */
});

/**
 * Bu Event'i dinleyerek 3D formun hash verisi hesaplanmadan önce formun input array içireğini güncelleyebilirsiniz.
 */
$eventDispatcher->addListener(Before3DFormHashCalculatedEvent::class, function (Before3DFormHashCalculatedEvent $event) {
    /**
     * Örneğin İşbank İmece Kart ile ödeme yaparken aşağıdaki verilerin eklenmesi gerekiyor:
     * $supportedPaymentModels = [
     * \Mews\Pos\Gateways\PosInterface::MODEL_3D_PAY,
     * \Mews\Pos\Gateways\PosInterface::MODEL_3D_PAY_HOSTING,
     * \Mews\Pos\Gateways\PosInterface::MODEL_3D_HOST,
     * ];
     * if ($event->getTxType() === PosInterface::TX_TYPE_PAY_AUTH && in_array($event->getPaymentModel(), $supportedPaymentModels, true)) {
     * $formInputs           = $event->getRequestData();
     * $formInputs['IMCKOD'] = '9999'; // IMCKOD bilgisi bankadan alınmaktadır.
     * $formInputs['FDONEM'] = '5'; // Ödemenin faizsiz ertelenmesini istediğiniz dönem sayısı.
     * $event->setRequestData($formInputs);
     * }*/
});

try {
    $formData = $pos->get3DFormData($order, PosInterface::MODEL_3D_HOST, $transaction);
} catch (\Exception $e) {
    dd($e);
}

require '../../_templates/_redirect_form.php';
require '../../_templates/_footer.php';
