<?php

use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\PosInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

require_once '_config.php';
require '../../_templates/_header.php';

if (($request->getMethod() !== 'POST' && PosInterface::TX_POST_PAY !== $transaction)
    // PayFlex-CP GET request ile cevapliyor
    && ($request->getMethod() === 'GET' && [] === $request->query->all())
) {
    echo new RedirectResponse($baseUrl);
    exit();
}

if (PosInterface::TX_POST_PAY === $transaction) {
    $order = $session->get('post_order');
} else {
    $order = $session->get('order');
}
if (!$order) {
    throw new Exception('Sipariş bulunamadı, session sıfırlanmış olabilir.');
}

try {
    /** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */
    $eventDispatcher->addListener(RequestDataPreparedEvent::class, function (RequestDataPreparedEvent $event) use ($pos, $paymentModel) {
        /**
         * Burda istek banka API'na gonderilmeden once gonderilecek veriyi degistirebilirsiniz.
         * Ornek:
         * if ($event->getTxType() === PosInterface::TX_PAY) {
         *     $data = $event->getRequestData();
         *     $data['abcd'] = '1234';
         *     $event->setRequestData($data);
         * }
         *
         * Bu asamada bu Event genellikle 1 kere trigger edilir.
         * Bir tek PosNet MODEL_3D_SECURE odemede 2 kere API call'i yapildigi icin bu event 2 kere trigger edilir.
         */

        /**
         * KOICode - 1: Ek Taksit 2: Taksit Atlatma 3: Ekstra Puan 4: Kontur Kazanım 5: Ekstre Erteleme 6: Özel Vade Farkı
         */
        if ($pos instanceof \Mews\Pos\Gateways\PosNetV1Pos && $event->getTxType() === PosInterface::TX_PAY) {
            // Albaraka PosNet KOICode ekleme
            // $data            = $event->getRequestData();
            // $data['KOICode'] = '1';
            // $event->setRequestData($data);
        }
        if ($pos instanceof \Mews\Pos\Gateways\PosNet
            && $event->getTxType() === PosInterface::TX_PAY
            && PosInterface::MODEL_NON_SECURE === $paymentModel) {
            // Yapikredi PosNet KOICode ekleme
            // $data            = $event->getRequestData();
            // $data['sale']['koiCode'] = '1';
            // $event->setRequestData($data);
        }
    });

    /**
     * Isbank İMECE kart ile MODEL_3D_SECURE yöntemiyle ödeme için ekstra alanların eklenme örneği
     *
     * $eventDispatcher->addListener(RequestDataPreparedEvent::class, function (RequestDataPreparedEvent $event) {
     * if ($event->getTxType() === PosInterface::TX_PAY) {
     *     $data         = $event->getRequestData();
     *     $data['Extra']['IMCKOD'] = '9999'; // IMCKOD bilgisi bankadan alınmaktadır.
     *     $data['Extra']['FDONEM'] = '5'; // Ödemenin faizsiz ertelenmesini istediğiniz dönem sayısı
     *     $event->setRequestData($data);
     * }
     * });*/

    doPayment($pos, $paymentModel, $transaction, $order, $card);
} catch (HashMismatchException $e) {
    dd($e);
}
$response = $pos->getResponse();

if ($pos->isSuccess()) {
    // siparis iptal ve iade islemlerde kullanilir
    $session->set('ref_ret_num', $response['ref_ret_num']);
}

// aşağıdaki veriler sipariş durum sorgulama isteğinde kullanılır.
$response['order_id']      = $response['order_id'] ?? $order['id'];
$response['currency']      = $response['currency'] ?? $order['currency'];
$response['payment_model'] = $paymentModel;

$session->set('last_response', $response);
?>

    <div class="result">
        <h3 class="text-center text-<?= $pos->isSuccess() ? 'success' : 'danger'; ?>">
            <?php if (PosInterface::TX_PAY === $transaction || PosInterface::TX_POST_PAY === $transaction) : ?>
                <?= $pos->isSuccess() ? 'Payment is successful!' : 'Payment is not successful!'; ?>
            <?php elseif (PosInterface::TX_PRE_PAY === $transaction) : ?>
                <?= $pos->isSuccess() ? 'Pre Authorization is successful!' : 'Pre Authorization is not successful!'; ?>
            <?php endif; ?>
        </h3>
        <hr>
        <dl class="row">
            <dt class="col-sm-3">Status:</dt>
            <dd class="col-sm-9"><?= $response['status']; ?></dd>
        </dl>
        <hr>
        <dl class="row">
            <dt class="col-sm-3">Order ID:</dt>
            <dd class="col-sm-9"><?= $response['order_id'] ?: '-'; ?></dd>
        </dl>
        <hr>
        <dl class="row">
            <dt class="col-sm-3">AuthCode:</dt>
            <dd class="col-sm-9"><?= $response['auth_code'] ?: '-'; ?></dd>
        </dl>
        <hr>
        <dl class="row">
            <dt class="col-sm-3">RetRefNum (iade, iptal, durum soruglama icin kullnilacak numara):</dt>
            <dd class="col-sm-9"><?= $response['ref_ret_num'] ?: '-'; ?></dd>
        </dl>
        <hr>
        <dl class="row">
            <dt class="col-sm-3">ProcReturnCode:</dt>
            <dd class="col-sm-9"><?= $response['proc_return_code'] ?: '-'; ?></dd>
        </dl>
        <hr>
        <dl class="row">
            <dt class="col-sm-3">Transaction ID:</dt>
            <dd class="col-sm-9"><?= $response['trans_id'] ?: '-'; ?></dd>
        </dl>
        <hr>
        <dl class="row">
            <dt class="col-sm-3">Error Code:</dt>
            <dd class="col-sm-9"><?= $response['error_code'] ?: '-'; ?></dd>
        </dl>
        <dl class="row">
            <dt class="col-sm-3">Status Detail:</dt>
            <dd class="col-sm-9"><?= $response['status_detail'] ?: '-'; ?></dd>
        </dl>
        <hr>
        <dl class="row">
            <dt class="col-sm-3">Error Message:</dt>
            <dd class="col-sm-9"><?= $response['error_message'] ?: '-'; ?></dd>
        </dl>
        <?php if (PosInterface::MODEL_NON_SECURE !== $paymentModel): ?>
            <!--bu alanlar non secure odemede yer almaz.-->
            <dl class="row">
                <dt class="col-sm-3">MD Status <small>(3D Secure doğrulama başarılı oldugu durumda degeri (genelde) 1 oluyor)</small>:</dt>
                <dd class="col-sm-9"><?= $response['md_status'] ?: '-'; ?></dd>
            </dl>
            <hr>
            <hr>
            <dl class="row">
                <dt class="col-sm-3">MD Error Message:</dt>
                <dd class="col-sm-9"><?= $response['md_error_message'] ?: '-'; ?></dd>
            </dl>
            <hr>
            <dl class="row">
                <dt class="col-sm-3">Transaction Security:</dt>
                <dd class="col-sm-9"><?= $response['transaction_security']; ?></dd>
            </dl>
        <?php endif ?>
        <hr>
        <dl class="row">
            <dt class="col-sm-12">All Data Dump:</dt>
            <dd class="col-sm-12">
                <pre><?php dump($response); ?></pre>
            </dd>
        </dl>
        <hr>
        <div class="text-right">
            <?php if ($pos->isSuccess()) : ?>
                <!--yapılan ödeme tipine göre bir sonraki yapılabilecek işlemlerin butonlarını gösteriyoruz.-->
                <?php if (PosInterface::TX_PRE_PAY === $transaction) : ?>
                    <a href="<?= $bankTestsUrl ?>/regular/post-auth.php" class="btn btn-lg btn-primary">Finish provisioning
                        ></a>
                <?php endif; ?>
                <?php if (PosInterface::TX_PAY === $transaction) : ?>
                    <a href="<?= $bankTestsUrl ?>/regular/cancel.php" class="btn btn-lg btn-danger">Cancel payment</a>
                <?php endif; ?>
                <a href="<?= $bankTestsUrl ?>/regular/status.php" class="btn btn-lg btn-default">Payment Status</a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-lg btn-info">&lt; Click to payment form</a>
        </div>
    </div>

<script>
    if (window.opener && window.opener !== window) {
        // you are in a popup
        // send result data to parent window
        window.opener.parent.postMessage(`<?= json_encode($response); ?>`);
    } else if (window.parent) {
        // you are in iframe
        // send result data to parent window
        window.parent.postMessage(`<?= json_encode($response); ?>`);
    }
</script>
<?php require __DIR__.'/_footer.php'; ?>
