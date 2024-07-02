
### Tarihçe Sorgulama

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
// (örn: /examples/akbankpos/3d/_config.php)
$account = \Mews\Pos\Factory\AccountFactory::createEstPosAccount(
    'akbank', //pos config'deki ayarın index name'i
    'yourClientID',
    'yourKullaniciAdi',
    'yourSifre',
    \Mews\Pos\PosInterface::MODEL_NON_SECURE,
    '', // bankaya göre zorunlu
    \Mews\Pos\PosInterface::LANG_TR
);

$eventDispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();

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

**history.php**
```php
<?php

require 'config.php';

function createHistoryOrder(string $gatewayClass, array $extraData, string $ip): array
{
    $order  = [];
    $txTime = new \DateTimeImmutable();
    if (\Mews\Pos\Gateways\PayForPos::class === $gatewayClass) {
        $order = [
            // odeme tarihi
            'transaction_date'  => $extraData['transaction_date'] ?? $txTime,
        ];
    } elseif (\Mews\Pos\Gateways\VakifKatilimPos::class === $gatewayClass) {
        $order  = [
            'page'       => 1,
            'page_size'  => 20,
            'start_date' => $txTime->modify('-1 day'),
            'end_date'   => $txTime->modify('+1 day'),
        ];
    } elseif (\Mews\Pos\Gateways\GarantiPos::class === $gatewayClass) {
        $order = [
            'ip'         => $ip,
            'page'       => 1, //optional
            // Başlangıç ve bitiş tarihleri arasında en fazla 30 gün olabilir
            'start_date' => $txTime,
            'end_date'   => $txTime->modify('+1 day'),
        ];
    } elseif (\Mews\Pos\Gateways\AkbankPos::class === $gatewayClass) {
        $order  = [
            // Gün aralığı 1 günden fazla girilemez
            'start_date' => $txTime->modify('-23 hour'),
            'end_date'   => $txTime,
        ];
//        ya da batch number ile (batch number odeme isleminden alinan response'da bulunur):
//        $order  = [
//            'batch_num' => 24,
//        ];
    }

    return $order;
}

$order = createHistoryOrder(get_class($pos), [], '127.0.0.1');

try {
    $pos->history($order);
} catch (\Error $e) {
    var_dump($e);
    exit;
}
$response = $pos->getResponse();
var_dump($response);
```
