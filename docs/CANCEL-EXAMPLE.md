
### Ödeme İptali

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

**cancel.php**
```php
<?php

require 'config.php';

function createCancelOrder(string $gatewayClass, array $lastResponse, string $ip): array
{
    $cancelOrder = [
        'id'          => $lastResponse['order_id'], // MerchantOrderId
        'currency'    => $lastResponse['currency'],
        'ref_ret_num' => $lastResponse['ref_ret_num'],
        'ip'          => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : '127.0.0.1',
    ];

    if (\Mews\Pos\Gateways\GarantiPos::class === $gatewayClass) {
        $cancelOrder['amount'] = $lastResponse['amount'];
    } elseif (\Mews\Pos\Gateways\KuveytPos::class === $gatewayClass) {
        $cancelOrder['remote_order_id'] = $lastResponse['remote_order_id']; // banka tarafındaki order id
        $cancelOrder['auth_code']       = $lastResponse['auth_code'];
        $cancelOrder['transaction_id']  = $lastResponse['transaction_id'];
        $cancelOrder['amount']          = $lastResponse['amount'];
    } elseif (\Mews\Pos\Gateways\VakifKatilimPos::class === $gatewayClass) {
        $cancelOrder['remote_order_id']  = $lastResponse['remote_order_id']; // banka tarafındaki order id
        $cancelOrder['amount']           = $lastResponse['amount'];
        // on otorizasyon islemin iptali icin PosInterface::TX_TYPE_PAY_PRE_AUTH saglanmasi gerekiyor
        $cancelOrder['transaction_type'] = $lastResponse['transaction_type'] ?? PosInterface::TX_TYPE_PAY_AUTH;
    } elseif (\Mews\Pos\Gateways\PayFlexV4Pos::class === $gatewayClass || \Mews\Pos\Gateways\PayFlexCPV4Pos::class === $gatewayClass) {
        // çalışmazsa $lastResponse['all']['ReferenceTransactionId']; ile denenmesi gerekiyor.
        $cancelOrder['transaction_id'] = $lastResponse['transaction_id'];
    } elseif (\Mews\Pos\Gateways\PosNetV1Pos::class === $gatewayClass || \Mews\Pos\Gateways\PosNet::class === $gatewayClass) {
        /**
         * payment_model: siparis olusturulurken kullanilan odeme modeli.
         * orderId'yi dogru şekilde formatlamak icin zorunlu.
         */
        $cancelOrder['payment_model'] = $lastResponse['payment_model'];
        // satis islem disinda baska bir islemi (Ön Provizyon İptali, Provizyon Kapama İptali, vs...) iptal edildiginde saglanmasi gerekiyor
        // 'transaction_type' => $lastResponse['transaction_type'],
    }


    if (isset($lastResponse['recurring_id'])) {
        // tekrarlanan odemeyi iptal etmek icin:
        if (\Mews\Pos\Gateways\EstPos::class === $gatewayClass || \Mews\Pos\Gateways\EstV3Pos::class === $gatewayClass) {
            $cancelOrder += [
                'recurringOrderInstallmentNumber' => 1, // hangi taksidi iptal etmek istiyoruz?
            ];
        } elseif (\Mews\Pos\Gateways\AkbankPos::class === $gatewayClass) {
            // odemesi gerceklesmis recurring taksidin iptali:
//            $cancelOrder += [
//                'recurring_id'                    => $lastResponse['recurring_id'],
//                'recurringOrderInstallmentNumber' => 1,
//            ];

            // odemesi henuz gerceklesmemis recurring taksidin iptali:
            $cancelOrder += [
                'recurring_id'                    => $lastResponse['recurring_id'],
                'recurringOrderInstallmentNumber' => 2,
                'recurring_payment_is_pending'    => true,
            ];

            // odemesi henuz gerceklesmemis recurring işlem talimatlarının tamamı iptal edilmek isteniyorsa
//            $cancelOrder += [
//                'recurring_id'                    => $lastResponse['recurring_id'],
//                'recurringOrderInstallmentNumber' => null,
//                'recurring_payment_is_pending'    => true,
//            ];
        }
    }

    return $cancelOrder;
}

// odemeden aldiginiz cevap: $pos->getResponse();
$lastResponse = $session->get('last_response');
$ip = '127.0.0.1';
$order = createCancelOrder(get_class($pos), $lastResponse, $ip);

try {
    $pos->cancel($order);
} catch (\Error $e) {
    var_dump($e);
    exit;
}
$response = $pos->getResponse();
var_dump($response);
```
