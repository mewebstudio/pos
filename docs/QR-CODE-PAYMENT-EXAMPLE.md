
### QR Code ile Odeme Ornegi

Kutüphanenin henüz QR ödeme desteği **yoktur**, ancak kodlarınızda azıcık değişiklik
yaparak QR kod ile ödeme yapabilirsiniz.

Bu örnek QNB Finansbank için yapılmıştır.
QR kode ödemeler temel olarak 3D Host ödeme gibi çalışır.
Ancak **SecureType** değeri **NonSecure** olması gerekiyor.
Bu yüzden aşağıdaki örnekte ödeme model duruma göre
**MODEL_3D_HOST** veya **MODEL_NON_SECURE** olarak kullanımıştır.

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

$paymentModel = \Mews\Pos\PosInterface::MODEL_3D_HOST;
$transactionType = \Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH;

// API kullanıcı bilgileri
// AccountFactory'de kullanılacak method Gateway'e göre değişir!!!
// /examples altındaki _config.php dosyalara bakınız
// (örn: /examples/akbankpos/3d/_config.php)
$account = \Mews\Pos\Factory\AccountFactory::createPayForAccount(
    'qnbfinansbank-payfor', //pos config'deki ayarın index name'i
    'merchantId',
    'userCode',
    'userPassword',
    $paymentModel,
    'merchantPass',
    \Mews\Pos\PosInterface::LANG_TR
);

$eventDispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();

try {
    $config = require __DIR__.'/pos_test_ayarlar.php';

    $pos = \Mews\Pos\Factory\PosFactory::createPosGateway($account, $config, $eventDispatcher);
} catch (\Mews\Pos\Exceptions\BankNotFoundException | \Mews\Pos\Exceptions\BankClassNullException $e) {
    var_dump($e);
    exit;
}
```

**index.php**

```php
<?php

require 'config.php';

// Sipariş bilgileri
$order = [
    'id'          => 'BENZERSIZ-SIPARIS-ID',
    'amount'      => 1.01,
    'currency'    => \Mews\Pos\PosInterface::CURRENCY_TRY, //optional. default: TRY
    'installment' => 0, //0 ya da 1'den büyük değer, optional. default: 0

    // Success ve Fail URL'ler farklı olabilir ama kütüphane success ve fail için aynı kod çalıştırır.
    // success_url ve fail_url'lerin aynı olmasın fayda var çünkü bazı gateyway'ler tek bir URL kabul eder.
    'success_url' => 'https://example.com/response.php',
    'fail_url'    => 'https://example.com/response.php',

    //lang degeri verilmezse account (EstPosAccount) dili kullanılacak
    'lang' => \Mews\Pos\Gateways\PosInterface::LANG_TR, // Kullanıcının yönlendirileceği banka gateway sayfasının ve gateway'den dönen mesajların dili.
];

$session->set('order', $order);
$card = null;

$formData = $pos->get3DFormData(
    $order,
    \Mews\Pos\PosInterface::MODEL_NON_SECURE, // QR code odeme icin MODEL_NON_SECURE kullanilir
    $transactionType,
    $card,
    /**
     * MODEL_3D_SECURE veya MODEL_3D_PAY ödemelerde kredi kart verileri olmadan
     * form verisini oluşturmak için true yapabilirsiniz.
     * Yine de bazı gatewaylerde kartsız form verisi oluşturulamıyor.
     */
    true
);

unset($formData['inputs']['Rnd']);
unset($formData['inputs']['Hash']);
$formData['inputs']['UserPass'] = $pos->getAccount()->getPassword();

// Canli ortam icin dogru URLi kullanin
$formData['gateway'] = 'https://vpostest.qnb.com.tr/Gateway/QR/QRHost.aspx';

} catch (\InvalidArgumentException $e) {
    // örneğin kart bilgisi sağlanmadığında bu exception'i alırsınız.
    var_dump($e);
} catch (\LogicException $e) {
    // ödeme modeli veya işlem tipi desteklenmiyorsa bu exception'i alırsınız.
    var_dump($e);
} catch (\Exception|\Error $e) {
    var_dump($e);
    exit;
}
```
```php
// $formData içeriği HTML forma render ediyoruz ve kullanıcıyı banka gateway'ine yönlendiriyoruz.
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
<script>
    // Formu JS ile otomatik submit ederek kullaniciyi banka gatewayine yonlendiriyoruz.
    let redirectForm = document.querySelector('form.redirect-form');
    if (redirectForm) {
        redirectForm.submit();
    }
</script>
```

**response.php (gateway'den döndükten sonra çalışacak kod)**

```php
<?php

require 'config.php';

$order = $session->get('order');
$card  = null;

// Sonuç işleniyor
try  {
    $pos->payment(
        $paymentModel,
        $order,
        $transactionType,
        $card
    );

    // Sonuç çıktısı
    $response = $pos->getResponse();
    var_dump($response);
    // response içeriği için /examples/template/_payment_response.php dosyaya bakınız.

    // Ödeme başarılı mı?
    if ($pos->isSuccess()) {
        // NOT: Ödeme durum sorgulama, iptal ve iade işlemleri yapacaksanız $response değerini saklayınız.
    }
} catch (\Mews\Pos\Exceptions\HashMismatchException $e) {
    /**
     * Bankadan gelen verilerin bankaya ait olmadığında bu exception oluşur.
     * Veya Banka API bilgileriniz hatalı ise de oluşur.
     * Eğer kütühaneden dolayı hash doğrulama hatası alıyorsanız, issue oluşturunuz.
     * Issue çözülene kadar geçici olarak disable_3d_hash_check: true ayarla hash doğrulamasını devre dışı bırakabilirsiniz.
     * Güvenlik açısından disable_3d_hash_check: false olarak kullanılması tavsiye edilmez.
     */
} catch (\Error $e) {
    var_dump($e);
    exit;
}
```
