<?php

use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\PosInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Bu kod MODEL_3D_SECURE, MODEL_3D_PAY, MODEL_3D_HOST odemeler icin gereken HTML form verisini olusturur.
 * Odeme olmayan (iade, iptal, durum) veya MODEL_NON_SECURE islemlerde kullanilmaz.
 */

require '_config.php';
require '../../_templates/_header.php';

if ($request->getMethod() !== 'POST') {
    echo new RedirectResponse($baseUrl.'index.php');
    exit();
}
$transaction = $request->get('tx', PosInterface::TX_PAY);
$order       = getNewOrder(
    $baseUrl,
    $ip,
    $request->get('currency', PosInterface::CURRENCY_TRY),
    $session,
    $request->get('installment'),
    $request->get('is_recurring', 0) == 1,
    $request->get('lang', PosInterface::LANG_TR)
);
$session->set('order', $order);

$card = createCard($pos, $request->request->all());

/**
 * PayFlex'te provizyonu (odemeyi) tamamlamak icin tekrar kredi kart bilgileri isteniyor,
 * bu yuzden kart bilgileri kaydediyoruz
 */
$session->set('card', $request->request->all());
$session->set('tx', $transaction);

try {

    /** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */
    $eventDispatcher->addListener(RequestDataPreparedEvent::class, function (RequestDataPreparedEvent $event) {
            /**
             * Burda istek banka API'na gonderilmeden once gonderilecek veriyi degistirebilirsiniz.
             * Ornek:
             * if ($event->getTxType() === PosInterface::TX_PAY) {
             *     $data = $event->getRequestData();
             *     $data['abcd'] = '1234';
             *     $event->setRequestData($data);
             * }
             *
             * Bu asamada bu Event sadece PosNet, PayFlexCPV4Pos, PayFlexV4Pos, KuveytPos gatewayler'de trigger edilir.
             */
        });

        /**
         * Bu Event'i dinleyerek 3D formun hash verisi hesaplanmadan önce formun input array içireğini güncelleyebilirsiniz.
         */
        $eventDispatcher->addListener(Before3DFormHashCalculatedEvent::class, function (Before3DFormHashCalculatedEvent $event) {
            /**
             * Örneğin İşbank İmece Kart ile ödeme yaparken aşağıdaki verilerin eklenmesi gerekiyor:
            $supportedPaymentModels = [
                AbstractGateway::MODEL_3D_PAY,
                AbstractGateway::MODEL_3D_PAY_HOSTING,
                AbstractGateway::MODEL_3D_HOST,
            ];
            if ($event->getTxType() === PosInterface::TX_PAY && in_array($event->getPaymentModel(), $supportedPaymentModels, true)) {
                $formInputs           = $event->getRequestData();
                $formInputs['IMCKOD'] = '9999'; // IMCKOD bilgisi bankadan alınmaktadır.
                $formInputs['FDONEM'] = '5'; // Ödemenin faizsiz ertelenmesini istediğiniz dönem sayısı.
                $event->setRequestData($formInputs);
            }*/
        });



    $formData = $pos->get3DFormData($order, PosInterface::MODEL_3D_SECURE, $transaction, $card);


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

    //dd($formData);
} catch (\Throwable $e) {
    dd($e);
}
$flowType = $request->get('payment_flow_type');
?>

<?php if ($flowType === 'by_redirection') { ?>
    <?php require '../../_templates/_redirect_form.php'; ?>
    <script>
        $(function () {
            var redirectForm = $('form.redirect-form')
            if (redirectForm.length) {
                redirectForm.submit()
            }
        })
    </script>
<?php } elseif ($flowType === 'by_iframe' || $flowType === 'by_popup_window') {

    ob_start();
    include('../../_templates/_redirect_iframe_or_popup_window_form.php');
    $renderedForm = ob_get_contents();
    ob_end_clean();
    ?>
    <div class="alert alert-dismissible" role="alert" id="result-alert">
    </div>
    <pre id="result-response">
</pre>

    <script>
        $('#result-alert').hide();
        let messageReceived = false;

        let displayResponse = function (event) {
            let alertBox = $('#result-alert');
            let data = JSON.parse(event.data);
            $('#result-response').append(JSON.stringify(data, null, '\t'));
            if (data.status === 'approved') {
                alertBox.append('payment successful');
                alertBox.addClass('alert-info');
            } else {
                alertBox.addClass('alert-danger');
                alertBox.append('payment failed: ' + data.error_message ?? data.md_error_message);
            }
            alertBox.show();
        }
    </script>
<?php } ?>
<?php if ($flowType === 'by_iframe') { ?>
    <div class="modal fade" tabindex="-1" role="dialog" id="iframe-modal" data-keyboard="false" data-backdrop="static">
        <div class="modal-dialog" role="document" id="iframe-modal-dialog" style="width: 426px;">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"
                    style="color: white; opacity: 1;"><span aria-hidden="true">&times;</span></button>
        </div>
    </div>
    <script>
        window.addEventListener('message', function (event) {
            messageReceived = true;
            displayResponse(event);
            $('#iframe-modal').modal('hide');
        });

        let iframe = document.createElement('iframe');
        document.getElementById("iframe-modal-dialog").appendChild(iframe);
        $(iframe).height('500px');
        $(iframe).width('410px');
        iframe.contentWindow.document.open();
        iframe.contentWindow.document.write(`<?= $renderedForm; ?>`);
        iframe.contentWindow.document.close();
        $('#iframe-modal').modal('show');

        $('#iframe-modal').on('hidden.bs.modal', function () {
            if (!messageReceived) {
                let alertBox = $('#result-alert');
                alertBox.addClass('alert-danger');
                alertBox.append('modal box kapatildi');
                alertBox.show();
            }
        });
    </script>
<?php } elseif ($flowType === 'by_popup_window') { ?>
    <script>
        windowWidth = 400;
        let leftPosition = (screen.width / 2) - (windowWidth / 2);
        let popupWindow = window.open('about:blank', 'popup_window', 'toolbar=no,scrollbars=no,location=no,statusbar=no,menubar=no,resizable=no,width=' + windowWidth + ',height=500,left=' + leftPosition + ',top=234');
        let closeInterval = setInterval(function () {
            if (popupWindow.closed && !messageReceived) {
                // windows is closed without completing payment
                clearInterval(closeInterval);
                let alertBox = $('#result-alert');
                alertBox.addClass('alert-danger');
                alertBox.append('popup kapatildi');
                alertBox.show();
            }
        }, 1000);
        if (null === popupWindow) {
            // pop up bloke edilmis.
            alert("pop window'a izin veriniz.");
        } else {
            popupWindow.document.write(`<?= $renderedForm; ?>`);
            window.target = 'popup_window';
            window.addEventListener('message', function (event) {
                messageReceived = true;
                displayResponse(event);
                popupWindow.close();
            });
        }
    </script>
<?php } ?>
<?php
require '../../_templates/_footer.php';
