
### Örnek 3D Secure ödeme kodu
**config.php (Ayar dosyası)**
```php
<?php
require './vendor/autoload.php';

$sessionHandler = new \Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage([
    'cookie_samesite' => 'None',
    'cookie_secure' => true,
]);
$session        = new Session($sessionHandler);
$session->start();


// API kullanıcı bilgileri
// AccountFactory'de kullanılacak method Gateway'e göre değişir. Örnek kodlara bakınız.
$account = \Mews\Pos\Factory\AccountFactory::createEstPosAccount(
    'akbank', //pos config'deki ayarın index name'i
    'yourClientID',
    'yourKullaniciAdi',
    'yourSifre',
    PosInterface::MODEL_3D_SECURE, // storetype
    'yourStoreKey',
    PosInterface::LANG_TR
);

$eventDispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();

try {
    $config = require __DIR__.'/../config/pos_test.php';

    $pos = \Mews\Pos\Factory\PosFactory::createPosGateway($account, $config, $eventDispatcher);

    // GarantiPos ve KuveytPos'u test ortamda test edebilmek için zorunlu.
    $pos->setTestMode(true);
} catch (\Mews\Pos\Exceptions\BankNotFoundException | \Mews\Pos\Exceptions\BankClassNullException $e) {
    dd($e));
}
```

**form.php (kullanıcıdan kredi kart bilgileri alındıktan sonra çalışacak kod)**
```php
<?php

require 'config.php';

// Sipariş bilgileri
$order = [
    'id'          => 'BENZERSIZ-SIPERIS-ID',
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

// Kredi kartı bilgileri
try {
$card = \Mews\Pos\Factory\CreditCardFactory::create(
        $pos,
        $_REQUEST['number'],
        $_REQUEST['year'],
        $_REQUEST['month'],
        $_REQUEST['cvv'],
        $_REQUEST['name'],

        // kart tipi Gateway'e göre zorunlu, alabileceği örnek değer: "visa"
        // alabileceği alternatif değerler için \Mews\Pos\Entity\Card\CreditCardInterface'a bakınız.
        $_REQUEST['type'] ?? null
  );
} catch (CardTypeRequiredException $e) {
    // bu gateway için kart tipi zorunlu
} catch (CardTypeNotSupportedException $e) {
    // sağlanan kart tipi bu gateway tarafından desteklenmiyor
}

if (get_class($pos) === \Mews\Pos\Gateways\PayFlexV4Pos::class) {
    // bu gateway için ödemeyi tamamlarken tekrar kart bilgisi lazım olacak.
    $session->set('card', $_REQUEST);
}

try {
    /** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */
    $eventDispatcher->addListener(RequestDataPreparedEvent::class, function (RequestDataPreparedEvent $event) {
        /**
         * Bazı Gatewayler 3D Form verisini oluşturabilmek için bankaya API istek gönderir.
         * 3D form verisini oluşturmak için API isteği Gönderen Gateway'ler: AkOde, PosNet, PayFlexCPV4Pos, PayFlexV4Pos, KuveytPos
         * Burda istek banka API'na gonderilmeden once gonderilecek veriyi degistirebilirsiniz.
         * Ornek:
         * if ($event->getTxType() === PosInterface::TX_TYPE_PAY) {
         *     $data = $event->getRequestData();
         *     $data['abcd'] = '1234';
         *     $event->setRequestData($data);
         * }
         */
    });

    /**
     * Bu Event'i dinleyerek 3D formun hash verisi hesaplanmadan önce formun input array içireğini güncelleyebilirsiniz.
     * Eğer ekleyeceğiniz veri hash hesaplamada kullanılmıyorsa Form verisi oluştuktan sonra da güncelleyebilirsiniz.
     */
    $eventDispatcher->addListener(Before3DFormHashCalculatedEvent::class, function (Before3DFormHashCalculatedEvent $event) use ($pos): void {
        if (get_class($pos) === \Mews\Pos\Gateways\EstPos::class || get_class($pos) === \Mews\Pos\Gateways\EstV3Pos::class) {
            /**
             * Örnek 1: İşbank İmece Kart ile ödeme yaparken aşağıdaki verilerin eklenmesi gerekiyor:
                $supportedPaymentModels = [
                \Mews\Pos\Gateways\PosInterface::MODEL_3D_PAY,
                \Mews\Pos\Gateways\PosInterface::MODEL_3D_PAY_HOSTING,
                \Mews\Pos\Gateways\PosInterface::MODEL_3D_HOST,
                ];
                if ($event->getTxType() === PosInterface::TX_TYPE_PAY && in_array($event->getPaymentModel(), $supportedPaymentModels, true)) {
                $formInputs           = $event->getRequestData();
                $formInputs['IMCKOD'] = '9999'; // IMCKOD bilgisi bankadan alınmaktadır.
                $formInputs['FDONEM'] = '5'; // Ödemenin faizsiz ertelenmesini istediğiniz dönem sayısı.
                $event->setRequestData($formInputs);
            }*/
        }
    });

    $formData = $pos->get3DFormData(
        $order,
        \Mews\Pos\PosInterface::MODEL_3D_SECURE,
        \Mews\Pos\PosInterface::TX_TYPE_PAY,
        $card
    );
} catch (\Throwable $e) {
    dd($e);
}
```
```html
<!-- $formData içeriği HTML forma render ediyoruz ve kullanıcıyı banka gateway'ine yönlendiriyoruy. -->
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
    $card = \Mews\Pos\Factory\CreditCardFactory::create(
        $pos,
        $cardData['number'],
        $cardData['year'],
        $cardData['month'],
        $cardData['cvv'],
        $cardData['name'],
        $cardData['type']
  );
}
// Ödeme tamamlanıyor,
try  {
    $pos->payment(
        \Mews\Pos\PosInterface::MODEL_3D_SECURE,
        $order,
        \Mews\Pos\PosInterface::TX_TYPE_PAY,
        $card
    );

    // Ödeme başarılı mı?
    $pos->isSuccess();

    // Sonuç çıktısı
    dump($pos->getResponse());
    // response içeriği için /examples/template/_payment_response.php dosyaya bakınız.
} catch (Mews\Pos\Exceptions\HashMismatchException $e) {
   // Bankadan gelen verilerin bankaya ait olmadığında bu exception oluşur.
   // Banka API bilgileriniz hatalı ise de oluşur.
}
```
