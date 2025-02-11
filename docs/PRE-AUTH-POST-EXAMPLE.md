
## 3D Secure ile Ön otorizasyon ve Ön Otorizasyon kapama örneği

```sh
$ cp ./vendor/mews/pos/config/pos_test.php ./pos_test_ayarlar.php
```

**config.php (Ayar dosyası)**
```php
<?php
require './vendor/autoload.php';

$sessionHandler = new \Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage([
    'cookie_samesite' => 'None',
    'cookie_secure'   => true,
    'cookie_httponly' => true, // Javascriptin session'a erişimini engelliyoruz.
]);
$session        = new \Symfony\Component\HttpFoundation\Session\Session($sessionHandler);
$session->start();

// Ön otorizasyon için kullanılması gereken ödeme modeli değişir.
$paymentModel = \Mews\Pos\PosInterface::MODEL_3D_SECURE;
$transactionType = \Mews\Pos\PosInterface::TX_TYPE_PAY_PRE_AUTH;

// API kullanıcı bilgileri
// AccountFactory'de kullanılacak method Gateway'e göre değişir!!!
// /examples altındaki _config.php dosyalara bakınız
// (örn: /examples/akbankpos/3d/_config.php)
$account = \Mews\Pos\Factory\AccountFactory::createEstPosAccount(
    'akbank', //pos config'deki ayarın index name'i
    'yourClientID',
    'yourKullaniciAdi',
    'yourSifre',
    $paymentModel,
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

### Pre Auth (Ön otorizasyon)


**form.php (kullanıcıdan kredi kart bilgileri alındıktan sonra çalışacak kod)**

```php
<?php

require 'config.php';

// Sipariş bilgileri
$order = [
    'id'          => 'BENZERSIZ-SIPARIS-ID',
    'amount'      => 1.01,
    'currency'    => \Mews\Pos\PosInterface::CURRENCY_TRY, //optional. default: TRY
    'installment' => 0, //0 ya da 1'den büyük değer, optional. default: 0
    // lang degeri verilmezse account (EstPosAccount) dili kullanılacak
    'lang' => \Mews\Pos\Gateways\PosInterface::LANG_TR, // Kullanıcının yönlendirileceği banka gateway sayfasının ve gateway'den dönen mesajların dili.
];
    if ($pos instanceof \Mews\Pos\Gateways\ParamPos
        || in_array($paymentModel, [
        PosInterface::MODEL_3D_SECURE,
        PosInterface::MODEL_3D_PAY,
        PosInterface::MODEL_3D_HOST,
        PosInterface::MODEL_3D_PAY_HOSTING,
    ], true)) {
        // Success ve Fail URL'ler farklı olabilir ama kütüphane success ve fail için aynı kod çalıştırır.
        // success_url ve fail_url'lerin aynı olmasın fayda var çünkü bazı gateyway'ler tek bir URL kabul eder.
        $order['success_url'] = 'https://example.com/response.php';
        $order['fail_url']    = 'https://example.com/response.php';
    }


$session->set('order', $order);

// Kredi kartı bilgileri
try {
$card = \Mews\Pos\Factory\CreditCardFactory::createForGateway(
        $pos,
        $_POST['card_number'],
        $_POST['card_year'],
        $_POST['card_month'],
        $_POST['card_cvv'],
        $_POST['card_name'],

        // kart tipi Gateway'e göre zorunlu, alabileceği örnek değer: "visa"
        // alabileceği alternatif değerler için \Mews\Pos\Entity\Card\CreditCardInterface'a bakınız.
        $_POST['card_type'] ?? null
  );
} catch (\Mews\Pos\Exceptions\CardTypeRequiredException $e) {
    // bu gateway için kart tipi zorunlu
} catch (\Mews\Pos\Exceptions\CardTypeNotSupportedException $e) {
    // sağlanan kart tipi bu gateway tarafından desteklenmiyor
}

if (get_class($pos) === \Mews\Pos\Gateways\PayFlexV4Pos::class) {
    // bu gateway için ödemeyi tamamlarken tekrar kart bilgisi lazım olacak.
    $session->set('card', $_POST);
}

try {
    $formData = $pos->get3DFormData(
        $order,
        $paymentModel,
        $transactionType,
        $card,
        false
    );
} catch (\Exception|\Error $e) {
    var_dump($e);
    exit;
}
```
```php
<?php if (is_string($formData)): ?>
    <?= $formData ?>
<?php else: ?>
    <!-- $formData içeriği HTML forma render ediyoruz ve kullanıcıyı banka gateway'ine yönlendiriyoruz. -->
    <form method="<?= $formData['method']; ?>" action="<?= $formData['gateway']; ?>"  class="redirect-form" role="form">
        <?php foreach ($formData['inputs'] as $key => $value) : ?>
            <input type="hidden" name="<?= $key; ?>" value="<?= $value; ?>">
        <?php endforeach; ?>
        <div class="text-center">Redirecting...</div>
        <hr>
        <div class="form-group text-center">
            <button type="submit" class="btn btn-lg btn-block btn-success">Submit</button>
        </div>
    </form>
<?php endif; ?>
```
**response.php (gateway'den döndükten sonra çalışacak kod)**

```php
<?php

require 'config.php';

$order = $session->get('order');
$card  = null;
if (get_class($pos) === \Mews\Pos\Gateways\PayFlexV4Pos::class) {
    // bu gateway için ödemeyi tamamlarken tekrar kart bilgisi lazım.
    $cardData = $session->get('card');
    $card = \Mews\Pos\Factory\CreditCardFactory::createForGateway(
        $pos,
        $cardData['card_number'],
        $cardData['card_year'],
        $cardData['card_month'],
        $cardData['card_cvv'],
        $cardData['card_name'],
        $cardData['card_type']
  );
}

// Pre Auth Ödeme tamamlanıyor,
try  {
    $pos->payment(
        $paymentModel,
        $order,
        $transactionType,
        $card
    );

    // Ödeme başarılı mı?
    $pos->isSuccess();
    // Sonuç çıktısı
    $response = $pos->getResponse();
    var_dump($response);
    // response içeriği için /examples/template/_payment_response.php dosyaya bakınız.

    if ($pos->isSuccess()) {
        $session->set('last_response', $response);
    }
} catch (\Mews\Pos\Exceptions\HashMismatchException $e) {
   // Bankadan gelen verilerin bankaya ait olmadığında bu exception oluşur.
   // Banka API bilgileriniz hatalı ise de oluşur.
} catch (\Exception|\Error $e) {
    var_dump($e);
    exit;
}
```


### Post Auth (Ön otorizasyon kapama)

**post-auth.php**
```php
require 'config.php';

// Ön otorizasyon kapama işlemi MODEL_NON_SECURE ile gerçekleşir.
$paymentModel = \Mews\Pos\PosInterface::MODEL_NON_SECURE;
$transactionType = \Mews\Pos\PosInterface::TX_TYPE_PAY_POST_AUTH;
$lastResponse = $session->get('last_response');

function createPostPayOrder(string $gatewayClass, array $lastResponse, string $ip, ?float $postAuthAmount = null): array
{
    $postAuth = [
        'id'              => $lastResponse['order_id'],
        'amount'          => $postAuthAmount ?? $lastResponse['amount'],
        'pre_auth_amount' => $lastResponse['amount'], // amount > pre_auth_amount durumlar icin kullanilir
        'currency'        => $lastResponse['currency'],
        'ip'              => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : '127.0.0.1',
    ];

    if (\Mews\Pos\Gateways\GarantiPos::class === $gatewayClass) {
        $postAuth['ref_ret_num'] = $lastResponse['ref_ret_num'];
    }
    if (\Mews\Pos\Gateways\PosNetV1Pos::class === $gatewayClass || \Mews\Pos\Gateways\PosNet::class === $gatewayClass) {
        $postAuth['installment'] = $lastResponse['installment_count'];
        $postAuth['ref_ret_num'] = $lastResponse['ref_ret_num'];
    }

    return $postAuth;
}

$lastResponse = $session->get('last_response');

$preAuthAmount = $lastResponse['amount'];
// Bazi gatewaylerde otorizasyon kapama amount'u ön otorizasyon amount'tan daha fazla olabilir.
$postAuthAmount = $lastResponse['amount'] + 0.02;
$gatewayClass = get_class($pos);

$order = createPostPayOrder(
    $gatewayClass,
    $lastResponse,
    '127.0.0.1',
    $postAuthAmount
);

try {
    $pos->payment($paymentModel, $order, $transaction);
    var_dump($response);
} catch (\Exception|\Error $e) {
    var_dump($e);
    exit;
}
```
