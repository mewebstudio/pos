
### Tarihçe Sorgulama

```sh
$ cp ./vendor/mews/pos/config/pos_test.php ./pos_test_ayarlar.php
```

**config.php (Ayar dosyası)**
```php
<?php
require './vendor/autoload.php';

$paymentModel = \Mews\Pos\PosInterface::MODEL_NON_SECURE;

// API kullanıcı bilgileri
// AccountFactory'de kullanılacak method Gateway'e göre değişir. Örnek kodlara bakınız.
$account = \Mews\Pos\Factory\AccountFactory::createEstPosAccount(
    'akbank', //pos config'deki ayarın index name'i
    'yourClientID',
    'yourKullaniciAdi',
    'yourSifre',
    $paymentModel
    '', // bankaya göre zorunlu
    PosInterface::LANG_TR
);

$eventDispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();

try {
    $config = require __DIR__.'/pos_test_ayarlar.php';

    $pos = \Mews\Pos\Factory\PosFactory::createPosGateway($account, $config, $eventDispatcher);

    // GarantiPos ve KuveytPos'u test ortamda test edebilmek için zorunlu.
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

function createHistoryOrder(string $gatewayClass, array $extraData): array
{
    $order = [];

    if (PayForPos::class === $gatewayClass) {
        $order = [
            // odeme tarihi
            'transaction_date'  => $extraData['transaction_date'] ?? new \DateTimeImmutable(),
        ];
    }

    return $order;
}

$order = createHistoryOrder(get_class($pos), []);

$pos->history($order);
$response = $pos->getResponse();
var_dump($response);
```
