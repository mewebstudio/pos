<?php

use Mews\Pos\PosInterface;

$templateTitle = 'Custom Query';

require '_config.php';
$transaction = PosInterface::TX_TYPE_CUSTOM_QUERY;

require '../../_templates/_header.php';

[$requestData, $apiUrl] = getCustomRequestData();

dump($requestData, $apiUrl);

/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */
$eventDispatcher->addListener(\Mews\Pos\Event\RequestDataPreparedEvent::class, function (\Mews\Pos\Event\RequestDataPreparedEvent $event) {
    dump($event->getRequestData()); //bankaya gonderilecek veri:
//
//    // Burda istek banka API'na gonderilmeden once gonderilecek veriyi degistirebilirsiniz.
//    // Ornek:
//    if ($event->getTxType() === PosInterface::TX_TYPE_CUSTOM_QUERY) {
//        $data         = $event->getRequestData();
//        $data['abcd'] = '1234';
//        $event->setRequestData($data);
//    }
});


try {
    /**
     * requestData içinde API hesap bilgileri, hash verisi ve bazi sabit değerler
     * eğer zaten bulunmuyorsa kütüphane otomatik ekler.
     */
    $pos->customQuery(
        $requestData,

        // URL optional, bazı gateway'lerde zorunlu.
        // Default olarak configdeki query_api ya da payment_api kullanılır.
        $apiUrl
    );
} catch (Exception $e) {
    dd($e);
}

/**
 * Bankadan dönen cevap array'e dönüştürülür,
 * ancak diğer transaction'larda olduğu gibi mapping/normalization yapılmaz.
 */
$response = $pos->getResponse();

require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
