<?php

use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\PosInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Bu kod MODEL_3D_SECURE, MODEL_3D_PAY, MODEL_3D_HOST odemeler icin gereken HTML form verisini olusturur.
 * Odeme olmayan (iade, iptal, durum) veya MODEL_NON_SECURE islemlerde kullanilmaz.
 */

// ilgili bankanin _config.php dosyasi load ediyoruz.
// ornegin /examples/finansbank-payfor/3d/_config.php
require '_config.php';

if ($request->getMethod() !== 'POST') {
    echo new RedirectResponse($baseUrl.'index.php');
    exit();
}
$transaction = $request->get('tx', PosInterface::TX_TYPE_PAY_AUTH);
$order       = createPaymentOrder(
    $pos,
    $paymentModel,
    $baseUrl,
    $ip,
    $request->get('currency', PosInterface::CURRENCY_TRY),
    $request->get('installment'),
    $request->get('is_recurring', 0) == 1,
    $request->get('lang', PosInterface::LANG_TR)
);
$session->set('order', $order);
$session->set('tx', $transaction);

$card = createCard($pos, $request->request->all());

if (get_class($pos) === \Mews\Pos\Gateways\PayFlexV4Pos::class) {
    // bu gateway için ödemeyi tamamlarken tekrar kart bilgisi lazım olacak.
    $session->set('card', $request->request->all());
}

// ============================================================================================
// OZEL DURUMLAR ICIN KODLAR START
// ============================================================================================

$formVerisiniOlusturmakIcinApiIstegiGonderenGatewayler = [
    \Mews\Pos\Gateways\PosNet::class,
    \Mews\Pos\Gateways\KuveytPos::class,
    \Mews\Pos\Gateways\ToslaPos::class,
    \Mews\Pos\Gateways\VakifKatilimPos::class,
    \Mews\Pos\Gateways\PayFlexV4Pos::class,
    \Mews\Pos\Gateways\PayFlexCPV4Pos::class,
];
if (in_array(get_class($pos), $formVerisiniOlusturmakIcinApiIstegiGonderenGatewayler, true)) {
    /** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */
    $eventDispatcher->addListener(RequestDataPreparedEvent::class, function (RequestDataPreparedEvent $event) {
        //Burda istek banka API'na gonderilmeden once gonderilecek veriyi degistirebilirsiniz.
        // Ornek:
//            if ($event->getTxType() === PosInterface::TX_TYPE_PAY_AUTH) {
//                $data         = $event->getRequestData();
//                $data['abcd'] = '1234';
//                $event->setRequestData($data);
//            }
    });
}

// KuveytVos TDV2.0.0 icin ozel biri durum
$eventDispatcher->addListener(
    RequestDataPreparedEvent::class,
    function (RequestDataPreparedEvent $requestDataPreparedEvent): void {
        if ($requestDataPreparedEvent->getGatewayClass() !== \Mews\Pos\Gateways\KuveytPos::class) {
            return;
        }
        // KuveytPos TDV2.0.0 icin zorunlu eklenmesi gereken ekstra alanlar:
        $additionalRequestDataForKuveyt = [
            'DeviceData'     => [
                /**
                 * DeviceChannel : DeviceData alanı içerisinde gönderilmesi beklenen işlemin yapıldığı cihaz bilgisi.
                 * 2 karakter olmalıdır. 01-Mobil, 02-Web Browser için kullanılmalıdır.
                 */
                'DeviceChannel' => '02',
            ],
            'CardHolderData' => [
                /**
                 * BillAddrCity: Kullanılan kart ile ilişkili kart hamilinin fatura adres şehri.
                 * Maksimum 50 karakter uzunluğunda olmalıdır.
                 */
                'BillAddrCity'     => 'İstanbul',
                /**
                 * BillAddrCountry Kullanılan kart ile ilişkili kart hamilinin fatura adresindeki ülke kodu.
                 * Maksimum 3 karakter uzunluğunda olmalıdır.
                 * ISO 3166-1 sayısal üç haneli ülke kodu standardı kullanılmalıdır.
                 */
                'BillAddrCountry'  => '792',
                /**
                 * BillAddrLine1: Kullanılan kart ile ilişkili kart hamilinin teslimat adresinde yer alan sokak vb. bilgileri içeren açık adresi.
                 * Maksimum 150 karakter uzunluğunda olmalıdır.
                 */
                'BillAddrLine1'    => 'XXX Mahallesi XXX Caddesi No 55 Daire 1',
                /**
                 * BillAddrPostCode: Kullanılan kart ile ilişkili kart hamilinin fatura adresindeki posta kodu.
                 */
                'BillAddrPostCode' => '34000',
                /**
                 * BillAddrState: CardHolderData alanı içerisinde gönderilmesi beklenen ödemede kullanılan kart ile ilişkili kart hamilinin fatura adresindeki il veya eyalet bilgisi kodu.
                 * ISO 3166-2'de tanımlı olan il/eyalet kodu olmalıdır.
                 */
                'BillAddrState'    => '40',
                /**
                 * Email: Kullanılan kart ile ilişkili kart hamilinin iş yerinde oluşturduğu hesapta kullandığı email adresi.
                 * Maksimum 254 karakter uzunluğunda olmalıdır.
                 */
                'Email'            => 'xxxxx@gmail.com',
                'MobilePhone'      => [
                    /**
                     * Cc: Kullanılan kart ile ilişkili kart hamilinin cep telefonuna ait ülke kodu. 1-3 karakter uzunluğunda olmalıdır.
                     */
                    'Cc'         => '90',
                    /**
                     * Subscriber: Kullanılan kart ile ilişkili kart hamilinin cep telefonuna ait abone numarası.
                     * Maksimum 15 karakter uzunluğunda olmalıdır.
                     */
                    'Subscriber' => '1234567899',
                ],
            ],
        ];

        $requestData = $requestDataPreparedEvent->getRequestData();
        $requestData = array_merge_recursive($requestData, $additionalRequestDataForKuveyt);
        $requestDataPreparedEvent->setRequestData($requestData);
    });

/**
 * Bu Event'i dinleyerek 3D formun hash verisi hesaplanmadan önce formun input array içireğini güncelleyebilirsiniz.
 * Eger ekleyeceginiz veri hash hesaplamada kullanilmiyorsa form verisi olusturduktan sonra da ekleyebilirsiniz.
 */
$eventDispatcher->addListener(Before3DFormHashCalculatedEvent::class, function (Before3DFormHashCalculatedEvent $event): void {
    if ($event->getGatewayClass() === \Mews\Pos\Gateways\EstPos::class || $event->getGatewayClass() === \Mews\Pos\Gateways\EstV3Pos::class) {
        //Örnek 1: İşbank İmece Kart ile ödeme yaparken aşağıdaki verilerin eklenmesi gerekiyor:
//                $supportedPaymentModels = [
//                    \Mews\Pos\PosInterface::MODEL_3D_PAY,
//                    \Mews\Pos\PosInterface::MODEL_3D_PAY_HOSTING,
//                    \Mews\Pos\PosInterface::MODEL_3D_HOST,
//                ];
//                if ($event->getTxType() === PosInterface::TX_TYPE_PAY_AUTH && in_array($event->getPaymentModel(), $supportedPaymentModels, true)) {
//                    $formInputs           = $event->getFormInputs();
//                    $formInputs['IMCKOD'] = '9999'; // IMCKOD bilgisi bankadan alınmaktadır.
//                    $formInputs['FDONEM'] = '5'; // Ödemenin faizsiz ertelenmesini istediğiniz dönem sayısı.
//                    $event->setFormInputs($formInputs);
//                }
    }
    if ($event->getGatewayClass() === \Mews\Pos\Gateways\EstV3Pos::class) {
//                // Örnek 2: callbackUrl eklenmesi
//                $formInputs                = $event->getFormInputs();
//                $formInputs['callbackUrl'] = $formInputs['failUrl'];
//                $formInputs['refreshTime'] = '10'; // birim: saniye; callbackUrl sisteminin doğru çalışması için eklenmesi gereken parametre
//                $event->setFormInputs($formInputs);
    }
});
// ============================================================================================
// OZEL DURUMLAR ICIN KODLAR END
// ============================================================================================

try {
    $formData = $pos->get3DFormData(
        $order,
        $paymentModel,
        $transaction,
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
    dd($e);
} catch (\LogicException $e) {
    // ödeme modeli veya işlem tipi desteklenmiyorsa bu exception'i alırsınız.
    dd($e);
} catch (\Throwable $e) {
    dd($e);
}


// ============================================================================================
// OZEL DURUMLAR ICIN KODLAR START
// ============================================================================================

/**
 * PosNet vftCode - VFT Kampanya kodunu. Vade Farklı işlemler için kullanılacak olan kampanya kodunu belirler.
 * Üye İşyeri için tanımlı olan kampanya kodu, İşyeri Yönetici Ekranlarına giriş
 * yapıldıktan sonra, Üye İşyeri bilgileri sayfasından öğrenilebilinir.
 */
if ($pos instanceof \Mews\Pos\Gateways\PosNet) {
    // YapiKredi
    // $formData['inputs']['vftCode'] = 'xxx';
}
if ($pos instanceof \Mews\Pos\Gateways\PosNetV1Pos) {
    // Albaraka
    // $formData['inputs']['VftCode'] = 'xxx';
}

/**
 * KOICode - Joker Vadaa Kampanya Kodu.
 * Degerler - 1: Ek Taksit 2: Taksit Atlatma 3: Ekstra Puan 4: Kontur Kazanım 5: Ekstre Erteleme 6: Özel Vade Farkı
 * İşyeri, UseJokerVadaa alanını 1 yaparak bankanın joker vadaa sorgu ve müşteri joker vadaa
 * kampanya seçim ekranının açılmasını ve Joker Vadaa kampanya seçiminin müşteriye bırakılmasını
 * sağlayabilir. İşyeri, müşterilere ortak ödeme sayfasında kampanya sunulmasını istemiyorsa
 * UseJokerVadaa alanını 0 set etmesi gerekir.
 */
if ($pos instanceof \Mews\Pos\Gateways\PosNetV1Pos) {
    // Albaraka
    // $formData['inputs']['UseJokerVadaa'] = '1';
    // $formData['inputs']['KOICode']       = 'xxx';
}
if ($pos instanceof \Mews\Pos\Gateways\PosNet) {
    // YapiKredi
    // $formData['inputs']['useJokerVadaa'] = '1';
}
// ============================================================================================
// OZEL DURUMLAR ICIN KODLAR END
// ============================================================================================

$flowType = $request->get('payment_flow_type');
?>


    <!------------------------------------------------------------------------------------------------------------->
    <!--
        Alttaki kodlarda secilen islem akisina gore
            - redirect ile odeme
            - modal box'ta odeme
            - pop up window'da odeme
        gereken kodlari calistiryoruz.
        Size gereken odeme akis yontemine gore alttaki kodlari kullaniniz.
    -->
    <!------------------------------------------------------------------------------------------------------------->

<?php if ('by_redirection' === $flowType) : ?>
    <?php if (is_string($formData)) : ?>
        <?= $formData; ?>
    <?php else: ?>
        <!--
        Sık kullanılan yöntem, 3D form verisini bir HTML form içine basıp JS ile otomatik submit ediyoruz.
        Submit sonucu kullanıcı banka sayfasıne yönlendirilir, işlem sonucundan ise duruma göre websitinizin
        success veya fail URL'na geri yönlendilir.
    -->
        <?php require '../../_templates/_redirect_form.php'; ?>
        <script>
            // Formu JS ile otomatik submit ederek kullaniciyi banka gatewayine yonlendiriyoruz.
            let redirectForm = document.querySelector('form.redirect-form');
            if (redirectForm) {
                redirectForm.submit();
            }
        </script>
    <?php endif; ?>
<?php elseif ('by_iframe' === $flowType || 'by_popup_window' === $flowType):
    if (is_string($formData)) {
        $renderedForm = $formData;
    } else {
        ob_start();
        include('../../_templates/_redirect_iframe_or_popup_window_form.php');
        $renderedForm = ob_get_clean();
    }
    ?>
    <!--
        $renderedForm içinde 3D formun verileriyle oluşturulan HTML form bulunur.
        alttaki kodlar ise bu $renderedForm verisini seçilen $flowType'a göre iframe modal box içine veya pop up window içine basar.
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
<?php endif; ?>


<?php if ('by_iframe' === $flowType) : ?>
    <div class="modal fade" tabindex="-1" role="dialog" id="iframe-modal" data-keyboard="false" data-backdrop="static">
        <div class="modal-dialog" role="document" style="width: 426px;">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="iframe-modal-body">
                </div>
            </div>
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


<?php elseif ('by_popup_window' === $flowType) : ?>
    <script>

        windowWidth = 400;
        let leftPosition = (screen.width / 2) - (windowWidth / 2);
        let popupWindow = window.open('about:blank', 'popup_window', 'toolbar=no,scrollbars=no,location=no,statusbar=no,menubar=no,resizable=no,width=' + windowWidth + ',height=500,left=' + leftPosition + ',top=234');
        if (null === popupWindow) {
            // pop up bloke edilmis.
            alert("pop window'a izin veriniz.");
        } else {
            /**
             * Popup ile ödeme yöntemi seçilmiş.
             * Popup window içine $renderedForm verisini basıyoruz.
             */
            popupWindow.document.write(`<?= $renderedForm; ?>`);

            // fokusu popup windowa odakla
            window.target = 'popup_window';

            /**
             * Bankadan geri websitenize yönlendirme yapıldıktan sonra ödeme sonuç verisi iframe/popup içinde olur.
             * Popup'tan ana pencereye JS'in windowlar arası Message API'ile ödeme sonucunu ana window'a gönderiyoruz.
             * Alttaki kod ise bu message API event'ni dinler,
             * message (yani bankadan dönen ödeme sonucu) aldığında sonucu kullanıcıya ana window'da gösterir
             */
            window.addEventListener('message', function (event) {
                messageReceived = true;
                displayResponse(event);
                popupWindow.close();
            });
        }
        /**
         * kullanıcı ödeme işlemine devam etmeden popup window'u kapatabilir.
         * Burda o durumu kontrol ediyoruz.
         */
        let closeInterval = setInterval(function () {
            if (popupWindow.closed && !messageReceived) {
                // window is closed without completing payment
                clearInterval(closeInterval);
                let alertBox = document.getElementById('result-alert');
                alertBox.classList.add('alert-danger');
                alertBox.appendChild(document.createTextNode('popup kapatildi'));
                alertBox.style.display = 'block';
            }
        }, 1000);
    </script>
<?php endif; ?>
<?php
require '../../_templates/_footer.php';
