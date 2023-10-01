<?php

use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\PosInterface;

require '_config.php';
require '../../_templates/_header.php';

$order = getNewOrder(
    $baseUrl,
    $ip,
    $request->get('currency', PosInterface::CURRENCY_TRY),
    $session,
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
         * if ($event->getTxType() === PosInterface::TX_PAY) {
         *     $data = $event->getRequestData();
         *     $data['abcd'] = '1234';
         *     $event->setRequestData($data);
         * }
         *
         * Bu asamada bu Event sadece PosNet, PayFlexCPV4Pos, PayFlexV4Pos, KuveytPos gatewayler'de trigger edilir.
         */
    });

$formData = $pos->get3DFormData($order, PosInterface::MODEL_3D_HOST, $transaction);

require '../../_templates/_redirect_form.php';
require '../../_templates/_footer.php';
