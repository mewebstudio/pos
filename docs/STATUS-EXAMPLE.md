
### Ödeme Durum Sorgulama

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

**status.php**
```php
<?php

require 'config.php';

function createStatusOrder(string $gatewayClass, array $lastResponse, string $ip): array
{
    $statusOrder = [
        'id'       => $lastResponse['order_id'], // MerchantOrderId
        'currency' => $lastResponse['currency'],
        'ip'       => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : '127.0.0.1',
    ];
    if (\Mews\Pos\Gateways\KuveytPos::class === $gatewayClass) {
        $statusOrder['remote_order_id'] = $lastResponse['remote_order_id']; // OrderId
    }
    if (\Mews\Pos\Gateways\PosNetV1Pos::class === $gatewayClass || \Mews\Pos\Gateways\PosNet::class === $gatewayClass) {
        /**
         * payment_model: siparis olusturulurken kullanilan odeme modeli.
         * orderId'yi dogru sekilde formatlamak icin zorunlu.
         */
        $statusOrder['payment_model'] = $lastResponse['payment_model'];
    }
    if (isset($lastResponse['recurring_id'])
        && (\Mews\Pos\Gateways\EstPos::class === $gatewayClass || \Mews\Pos\Gateways\EstV3Pos::class === $gatewayClass)
    ) {
        // tekrarlanan odemenin durumunu sorgulamak icin:
        $statusOrder = [
            // tekrarlanan odeme sonucunda banktan donen deger: $response['Extra']['RECURRINGID']
            'recurringId' => $lastResponse['recurring_id'],
        ];
    }

    return $statusOrder;
}

// odemeden aldiginiz cevap: $pos->getResponse();
$lastResponse = $session->get('last_response');
$ip = '127.0.0.1';
$order = createStatusOrder(get_class($pos), $lastResponse, $ip);

try {
    $pos->status($order);
} catch (\Error $e) {
    var_dump($e);
    exit;
}
$response = $pos->getResponse();
var_dump($response);
```
