
### Örnek 3D Secure ve 3D Pay ödemenin Modal Box'ta iframe kullanarak örneği

3D Secure ve 3D Pay ödemede kullanmanız gereken kodlar arasında tek fark `$paymentModel` değeridir.
```php
$paymentModel = \Mews\Pos\PosInterface::MODEL_3D_SECURE;
// veya
$paymentModel = \Mews\Pos\PosInterface::MODEL_3D_PAY;
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
    'cookie_secure'   => true,
    'cookie_httponly' => true, // Javascriptin session'a erişimini engelliyoruz.
]);
$session        = new \Symfony\Component\HttpFoundation\Session\Session($sessionHandler);
$session->start();

$paymentModel = \Mews\Pos\PosInterface::MODEL_3D_SECURE;
$transactionType = \Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH;

// AccountFactory'de kullanılacak method Gateway'e göre değişir!!!
// /examples altındaki _config.php dosyalara bakınız
// (örn: /examples/akbankpos/3d/_config.php)
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

    // GarantiPos'u test ortamda test edebilmek için zorunlu.
    $pos->setTestMode(true);
} catch (\Mews\Pos\Exceptions\BankNotFoundException | \Mews\Pos\Exceptions\BankClassNullException $e) {
    var_dump($e));
    exit;
}
```

**_iframe_form.php** (form.php icinde kullanilacak)
```php
<!DOCTYPE HTML>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title></title>
</head>
<body>
<form method="<?= $formData['method']; ?>" action="<?= $formData['gateway']; ?>" name="redirect-form"
      class="redirect-form" role="form">
    <?php foreach ($formData['inputs'] as $key => $value) : ?>
        <input type="hidden" name="<?= $key; ?>" value="<?= $value; ?>">
    <?php endforeach; ?>
    <div class="text-center">Redirecting...</div>
</form>
<script>
    setTimeout(function () {
        document.forms['redirect-form'].submit();
    }, 1000);
<\/script>
</body>
</html>
```

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

    // Success ve Fail URL'ler farklı olabilir ama kütüphane success ve fail için aynı kod çalıştırır.
    // success_url ve fail_url'lerin aynı olmasın fayda var çünkü bazı gateyway'ler tek bir URL kabul eder.
    'success_url' => 'https://example.com/response.php',
    'fail_url'    => 'https://example.com/response.php',

    //lang degeri verilmezse account (EstPosAccount) dili kullanılacak
    'lang' => \Mews\Pos\Gateways\PosInterface::LANG_TR, // Kullanıcının yönlendirileceği banka gateway sayfasının ve gateway'den dönen mesajların dili.
];

/**
 * NOT! kod örneği basit tutma amaçlı order'i (ve diğer verileri) session'a kaydediyoruz.
 * Siz veri tabanı ya da farklı bir storage mediumda kullanabilirsiniz.
 */
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
        /**
         * MODEL_3D_SECURE veya MODEL_3D_PAY ödemelerde kredi kart verileri olmadan
         * form verisini oluşturmak için true yapabilirsiniz.
         * Yine de bazı gatewaylerde kartsız form verisi oluşturulamıyor.
         */
        false
    );
} catch (\InvalidArgumentException $e) {
    // örneğin kart bilgisi sağlanmadığında bu exception'i alırsınız.
    var_dump($e);
} catch (\LogicException $e) {
    // ödeme modeli veya işlem tipi desteklenmiyorsa bu exception'i alırsınız.
    var_dump($e);
} catch (\Throwable $e) {
    var_dump($e);
    exit;
}

if (is_string($formData)) {
    $renderedForm = $formData;
} else {
    ob_start();
    include('_iframe_form.php');
    $renderedForm = ob_get_clean();
}
?>


<!--
    $renderedForm içinde 3D formun verileriyle oluşturulan HTML form bulunur.
    alttaki kodlar ise bu $renderedForm verisini seçilen $flowType'a göre
    iframe modal box içine veya pop up window içine basar.
    NOT: ornek JS kodlar Boostrap ve jQuery kullanarak yapilmistir.
-->
<div class="alert alert-dismissible" role="alert" id="result-alert">
    <!-- buraya odeme basarili olup olmadini alttaki JS kodlariyla basiyoruz. -->
</div>
<pre id="result-response">
    <!-- buraya odeme sonuc verilerinin alttaki JS kodlariyla basiyoruz-->
</pre>

<script>
    document.getElementById('result-alert').style.display = 'none';
    let messageReceived = false;

    /**
     * Bankadan geri websitenize yönlendirme yapıldıktan sonra alınan sonuca göre başarılı/başarısız alert box'u gösterir.
     */
    let displayResponse = function (event) {
        let alertBox = document.getElementById('result-alert');
        let data = JSON.parse(atob(event.data));

        let resultResponse = document.getElementById('result-response');
        resultResponse.appendChild(document.createTextNode(JSON.stringify(data, null, '\t')));

        if (data.status === 'approved') {
            alertBox.appendChild(document.createTextNode('payment successful'));
            alertBox.classList.add('alert-info');
        } else {
            alertBox.classList.add('alert-danger');
            alertBox.appendChild(document.createTextNode('payment failed: ' + (data.error_message ?? data.md_error_message)));
        }

        alertBox.style.display = 'block';
    }
</script>

    <div class="modal fade" tabindex="-1" role="dialog" id="iframe-modal" data-keyboard="false" data-backdrop="static">
        <div class="modal-dialog" role="document" id="iframe-modal-dialog" style="width: 426px;">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"
                    style="color: white; opacity: 1;"><span aria-hidden="true">&times;</span></button>
        </div>
    </div>
    <script>
        /**
         * Bankadan geri websitenize yönlendirme yapıldıktan sonra ödeme sonuç verisi iframe/popup içinde olur.
         * Modal box'ta açılan iframe'den ana pencereye JS'in windowlar arası Message API'ile ödeme sonucunu ana window'a gönderiyoruz.
         * Alttaki kod ise bu message API event'ni dinler,
         * message (yani bankadan dönen ödeme sonucu) aldığında sonucu kullanıcıya ana window'da gösterir
         */
        window.addEventListener('message', function (event) {
            messageReceived = true;
            displayResponse(event);
            let myModal = bootstrap.Modal.getInstance(document.getElementById('iframe-modal'));
            myModal.hide();
        });

        /**
         * modal box'ta iframe ile ödeme yöntemi seçilmiş.
         * modal box içinde yeni iframe oluşturuyoruz ve iframe içine $renderedForm verisini basıyoruz.
         */
        let iframe = document.createElement('iframe');
        document.getElementById("iframe-modal-body").appendChild(iframe);
        iframe.style.height = '500px';
        iframe.style.width = '410px';
        iframe.contentWindow.document.open();
        iframe.contentWindow.document.write(`<?= $renderedForm; ?>`);
        iframe.contentWindow.document.close();
        let modalElement = document.getElementById('iframe-modal');
        let myModal = new bootstrap.Modal(modalElement, {
            keyboard: false
        })
        myModal.show();

        modalElement.addEventListener('hidden.bs.modal', function () {
            if (!messageReceived) {
                let alertBox = document.getElementById('result-alert');
                alertBox.classList.add('alert-danger');
                alertBox.appendChild(document.createTextNode('modal box kapatildi'));
                alertBox.style.display = 'block';
            }
        });
    </script>
```

**response.php (gateway'den döndükten sonra çalışacak kod)**

```php
<?php

require 'config.php';

$order = $session->get('order');
$card  = null;
if (get_class($pos) === \Mews\Pos\Gateways\PayFlexV4Pos::class) {
    // bu gateway için ödemeyi tamamlarken tekrar kart bilgisi lazım.
    $cardData = $session->get('card')
    $session->remove('card');
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
} catch (Mews\Pos\Exceptions\HashMismatchException $e) {
}
?>


<script>
    if (window.parent) {
        // response.php iframe'de calisti
        // odeme sonucunu ana window'a yani form.php'e gonderiyoruz.
        window.parent.postMessage(`<?= base64_encode(json_encode($response)); ?>`);
    }
</script>
```
