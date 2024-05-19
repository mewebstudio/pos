
### Örnek 3DSecure, 3DPay, 3DHost ödeme kodu

3DSecure, 3DPay, 3DHost ödemeniz gereken kodlar arasında tek fark `$paymentModel` değeridir.
```php
$paymentModel = \Mews\Pos\PosInterface::MODEL_3D_SECURE;
// veya
// $paymentModel = \Mews\Pos\PosInterface::MODEL_3D_PAY;
// $paymentModel = \Mews\Pos\PosInterface::MODEL_3D_HOST;
```
Kütüphane içersinde ödeme modele göre farklı kodlar çalışacak.

```sh
$ cp ./vendor/mews/pos/config/pos_test.php ./pos_test_ayarlar.php
```

**config.php (Ayar dosyası)**
```php
<?php
require './vendor/autoload.php';

$sessionHandler = new \Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage([
    'cookie_samesite' => 'None',
    'cookie_secure' => true,
]);
$session        = new \Symfony\Component\HttpFoundation\Session\Session($sessionHandler);
$session->start();

$paymentModel = \Mews\Pos\PosInterface::MODEL_3D_SECURE;
$transactionType = \Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH;

// API kullanıcı bilgileri
// AccountFactory'de kullanılacak method Gateway'e göre değişir. Örnek kodlara bakınız.
$account = \Mews\Pos\Factory\AccountFactory::createEstPosAccount(
    'akbank', //pos config'deki ayarın index name'i
    'yourClientID',
    'yourKullaniciAdi',
    'yourSifre',
    $paymentModel,
    'yourStoreKey',
    \Mews\Pos\PosInterface::LANG_TR
);

$eventDispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();

try {
    $config = require __DIR__.'/pos_test_ayarlar.php';

    $pos = \Mews\Pos\Factory\PosFactory::createPosGateway($account, $config, $eventDispatcher);

    // GarantiPos ve KuveytPos'u test ortamda test edebilmek için zorunlu.
    $pos->setTestMode(true);
} catch (\Mews\Pos\Exceptions\BankNotFoundException | \Mews\Pos\Exceptions\BankClassNullException $e) {
    var_dump($e);
    exit;
}
```

**form.php (3DSecure ve 3DPay odemede kullanıcıdan kredi kart bilgileri alındıktan sonra çalışacak kod)**

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

if ($tekrarlanan = false) { // recurring payments
    // Desteleyen Gatewayler: GarantiPos, EstPos, EstV3Pos, PayFlexV4, AkbankPos
    $order['installment'] = 0; // Tekrarlayan ödemeler taksitli olamaz.

    $recurringFrequency     = 3;
    $recurringFrequencyType = 'MONTH'; // DAY|WEEK|MONTH|YEAR
    $endPeriod              = $installment * $recurringFrequency;

    $order['recurring'] = [
        'frequency'     => $recurringFrequency,
        'frequencyType' => $recurringFrequencyType,
        'installment'   => $installment,
        'startDate'     => new \DateTimeImmutable(), // GarantiPos optional
        'endDate'       => (new \DateTime())->modify(\sprintf('+%d %s', $endPeriod, $recurringFrequencyType)), // Sadece PayFlexV4'te zorunlu
    ];
}

$session->set('order', $order);

// Kredi kartı bilgileri
try {
$card = null;
if (\Mews\Pos\PosInterface::MODEL_3D_HOST !== $paymentModel) {
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
}

try {
    /** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */
    $eventDispatcher->addListener(RequestDataPreparedEvent::class, function (RequestDataPreparedEvent $event) {
        /**
         * Bazı Gatewayler 3D Form verisini oluşturabilmek için bankaya API istek gönderir.
         * 3D form verisini oluşturmak için API isteği Gönderen Gateway'ler: ToslaPos, PosNet, PayFlexCPV4Pos, PayFlexV4Pos, KuveytPos
         * Burda istek banka API'na gonderilmeden once gonderilecek veriyi degistirebilirsiniz.
         * Ornek:
         * if ($event->getTxType() === PosInterface::TX_TYPE_PAY_AUTH) {
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
                if ($event->getTxType() === \Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH && in_array($event->getPaymentModel(), $supportedPaymentModels, true)) {
                $formInputs           = $event->getFormInputs();
                $formInputs['IMCKOD'] = '9999'; // IMCKOD bilgisi bankadan alınmaktadır.
                $formInputs['FDONEM'] = '5'; // Ödemenin faizsiz ertelenmesini istediğiniz dönem sayısı.
                $event->setFormInputs($formInputs);
            }*/

        }
        if (get_class($pos) === \Mews\Pos\Gateways\EstV3Pos::class) {
//           Örnek 2: callbackUrl eklenmesi
//           $formInputs                = $event->getFormInputs();
//           $formInputs['callbackUrl'] = $formInputs['failUrl'];
//           $formInputs['refreshTime'] = '10'; // birim: saniye; callbackUrl sisteminin doğru çalışması için eklenmesi gereken parametre
//           $event->setFormInputs($formInputs);
        }
    });

    // KuveytVos TDV2.0.0 icin ozel biri durum
    $eventDispatcher->addListener(
        RequestDataPreparedEvent::class,
        function (RequestDataPreparedEvent $requestDataPreparedEvent) use ($pos): void {
            if (get_class($pos) !== \Mews\Pos\Gateways\KuveytPos::class) {
                return;
            }
            // KuveytPos TDV2.0.0 icin zorunlu eklenmesi gereken ekstra alanlar:
            $additionalRequestDataForKuveyt = [
                'DeviceData' => [
                    //2 karakter olmalıdır. 01-Mobil, 02-Web Browser için kullanılmalıdır.
                    'DeviceChannel' => '02',
                ],
                'CardHolderData' => [
                    'BillAddrCity' => 'İstanbul',
                    // ISO 3166-1 sayısal üç haneli ülke kodu standardı kullanılmalıdır.
                    'BillAddrCountry' => '792',
                    'BillAddrLine1' => 'XXX Mahallesi XXX Caddesi No 55 Daire 1',
                    'BillAddrPostCode' => '34000',
                    // ISO 3166-2'de tanımlı olan il/eyalet kodu olmalıdır.
                    'BillAddrState' => '40',
                    'Email' => 'xxxxx@gmail.com',
                    'MobilePhone' => [
                        'Cc' => '90',
                        'Subscriber' => '1234567899',
                    ],
                ],
            ];
            $requestData = $requestDataPreparedEvent->getRequestData();
            $requestData = array_merge_recursive($requestData, $additionalRequestDataForKuveyt);
            $requestDataPreparedEvent->setRequestData($requestData);
        });

    $formData = $pos->get3DFormData(
        $order,
        $paymentModel,
        $transactionType,
        $card
    );
} catch (\Throwable $e) {
    var_dump($e);
    exit;
}
```
```html
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
<script>
    $(function () {
        // Formu JS ile otomatik submit ederek kullaniciyi banka gatewayine yonlendiriyoruz.
        let redirectForm = $('form.redirect-form')
        if (redirectForm.length) {
            redirectForm.submit()
        }
    })
</script>
```
**response.php (gateway'den döndükten sonra çalışacak kod)**

```php
<?php

require 'config.php';

$order = $session->get('order');
$card  = null;
if (\Mews\Pos\PosInterface::MODEL_3D_HOST !== $paymentModel) {
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
}

//    //Isbank İMECE kart ile MODEL_3D_SECURE yöntemiyle ödeme için ekstra alanların eklenme örneği
//    $eventDispatcher->addListener(RequestDataPreparedEvent::class, function (RequestDataPreparedEvent $event) use ($paymentModel) {
//        if ($event->getTxType() === \Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH && \Mews\Pos\PosInterface::MODEL_3D_SECURE === $paymentModel) {
//            $data                    = $event->getRequestData();
//            $data['Extra']['IMCKOD'] = '9999'; // IMCKOD bilgisi bankadan alınmaktadır.
//            $data['Extra']['FDONEM'] = '5'; // Ödemenin faizsiz ertelenmesini istediğiniz dönem sayısı
//            $event->setRequestData($data);
//        }
//    });

// Ödeme tamamlanıyor,
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
        // NOT: Ödeme durum sorgulama, iptal ve iade işlemleri yapacaksanız $response değerini saklayınız.
    }
} catch (\Mews\Pos\Exceptions\HashMismatchException $e) {
   // Bankadan gelen verilerin bankaya ait olmadığında bu exception oluşur.
   // Banka API bilgileriniz hatalı ise de oluşur.
}
```
