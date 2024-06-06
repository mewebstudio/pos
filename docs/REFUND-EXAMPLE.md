
### Ödeme İadesi

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

**refund.php**
```php
<?php

require 'config.php';

function createRefundOrder(string $gatewayClass, array $lastResponse, string $ip, ?float $refundAmount = null): array
{
    $refundOrder = [
        'id'           => $lastResponse['order_id'], // MerchantOrderId
        'amount'       => $refundAmount ?? $lastResponse['amount'],

        // toplam siparis tutari, kismi iade mi ya da tam iade mi oldugunu anlamak icin kullanilir.
        'order_amount' => $lastResponse['amount'],

        'currency'     => $lastResponse['currency'],
        'ref_ret_num'  => $lastResponse['ref_ret_num'],
        'ip'           => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : '127.0.0.1',
    ];

    if (\Mews\Pos\Gateways\KuveytPos::class === $gatewayClass) {
        $refundOrder['remote_order_id'] = $lastResponse['remote_order_id']; // banka tarafındaki order id
        $refundOrder['auth_code']       = $lastResponse['auth_code'];
        $refundOrder['transaction_id']  = $lastResponse['transaction_id'];
    } elseif (\Mews\Pos\Gateways\VakifKatilimPos::class === $gatewayClass) {
        $refundOrder['remote_order_id']  = $lastResponse['remote_order_id']; // banka tarafındaki order id
        // on otorizasyon islemin iadesi icin PosInterface::TX_TYPE_PAY_PRE_AUTH saglanmasi gerekiyor
        $refundOrder['transaction_type'] = $lastResponse['transaction_type'] ?? PosInterface::TX_TYPE_PAY_AUTH;
    } elseif (\Mews\Pos\Gateways\PayFlexV4Pos::class === $gatewayClass || \Mews\Pos\Gateways\PayFlexCPV4Pos::class === $gatewayClass) {
        // çalışmazsa $lastResponse['all']['ReferenceTransactionId']; ile denenmesi gerekiyor.
        $refundOrder['transaction_id'] = $lastResponse['transaction_id'];
    } elseif (\Mews\Pos\Gateways\PosNetV1Pos::class === $gatewayClass || \Mews\Pos\Gateways\PosNet::class === $gatewayClass) {
        /**
         * payment_model: siparis olusturulurken kullanilan odeme modeli.
         * orderId'yi dogru şekilde formatlamak icin zorunlu.
         */
        $refundOrder['payment_model'] = $lastResponse['payment_model'];
    }

    if (isset($lastResponse['recurring_id'])) {
        // tekrarlanan odemeyi iade etmek icin:
        if (\Mews\Pos\Gateways\AkbankPos::class === $gatewayClass) {
            // odemesi gerceklesmis recurring taksidinin iadesi:
            $refundOrder += [
                'recurring_id'                    => $lastResponse['recurring_id'],
                'recurringOrderInstallmentNumber' => 1,
            ];
        }
    }

    return $refundOrder;
}

// odemeden aldiginiz cevap: $pos->getResponse();
$lastResponse = $session->get('last_response');

// tam iade:
$refundAmount = $lastResponse['amount'];
// kismi iade:
$refundAmount = $lastResponse['amount'] - 2;

$ip = '127.0.0.1';
$order = createRefundOrder(get_class($pos), $lastResponse, $ip, $refundAmount);

try {
    $pos->refund($order);
} catch (\Error $e) {
    var_dump($e);
    exit;
}

$response = $pos->getResponse();
var_dump($response);
```
