
### Custom Query

Kütüphanenin desteği olmadığı özel istekleri bu methodla yapabilirsiniz.

```sh
$ cp ./vendor/mews/pos/config/pos_test.php ./pos_test_ayarlar.php
```

**config.php (Ayar dosyası)**
```php
<?php
require './vendor/autoload.php';

// API kullanıcı bilgileri
// AccountFactory'de kullanılacak method Gateway'e göre değişir!!!
// /examples altındaki _config.php dosyalara bakınız
// (örn: /examples/tosla/regular/_config.php)
$account = \Mews\Pos\Factory\AccountFactory::createToslaPosAccount(
    'tosla',
    '424342224432',
    'POS_rwrwwrwr',
    'POS_4343223',
);
$eventDispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();

try {
    $config = require __DIR__.'/pos_test_ayarlar.php';

    $pos = \Mews\Pos\Factory\PosFactory::createPosGateway($account, $config, $eventDispatcher);

    // GarantiPos'u test ortamda test edebilmek için zorunlu.
    $pos->setTestMode(true);
} catch (\Mews\Pos\Exceptions\BankNotFoundException | \Mews\Pos\Exceptions\BankClassNullException $e) {
    var_dump($e));
    exit;
}
```

**custom_query.php**
```php
<?php

require 'config.php';

/**
 * requestData içinde API hesap bilgileri, hash verisi ve bazi sabit değerler
 * eğer zaten bulunmuyorsa kütüphane otomatik ekler.
 */
$requestData = [
    'bin' => 415956,
];

/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */
$eventDispatcher->addListener(\Mews\Pos\Event\RequestDataPreparedEvent::class, function (\Mews\Pos\Event\RequestDataPreparedEvent $event) {
//    dump($event->getRequestData()); //bankaya gonderilecek veri:
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
    $pos->customQuery(
        $requestData,

        // URL optional, bazı gateway'lerde zorunlu.
        // Default olarak configdeki query_api ya da payment_api kullanılır.
        'https://prepentegrasyon.tosla.com/api/Payment/GetCommissionAndInstallmentInfo'
    );
} catch (Exception $e) {
    dd($e);
}

/**
 * Bankadan dönen cevap array'e dönüştürülür,
 * ancak diğer transaction'larda olduğu gibi mapping/normalization yapılmaz.
 */
$response = $pos->getResponse();
var_dump($response);
```
