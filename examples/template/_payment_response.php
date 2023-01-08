<?php

use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Gateways\AbstractGateway;
use Symfony\Component\HttpFoundation\RedirectResponse;

require_once '_config.php';
require '../../template/_header.php';

if ($request->getMethod() !== 'POST' && AbstractGateway::TX_POST_PAY !== $transaction) {
    echo new RedirectResponse($baseUrl);
    exit();
}

if (AbstractGateway::TX_POST_PAY === $transaction) {
    $order = $session->get('post_order');
} else {
    $order = $session->get('order');
}
if (!$order) {
    throw new Exception('Sipariş bulunamadı, session sıfırlanmış olabilir.');
}

$pos->prepare($order, $transaction);

try {
    doPayment($pos, $transaction, $card);
} catch (HashMismatchException $e) {
    dd($e);
}
$response = $pos->getResponse();

if ($pos->isSuccess()) {
    // siparis iptal ve iade islemlerde kullanilir
    $session->set('ref_ret_num', $response['ref_ret_num']);
}
$session->set('last_response', $response);
?>

    <div class="result">
        <h3 class="text-center text-<?= $pos->isSuccess() ? 'success' : 'danger'; ?>">
            <?php if (AbstractGateway::TX_PAY === $transaction || AbstractGateway::TX_POST_PAY === $transaction) : ?>
                <?= $pos->isSuccess() ? 'Payment is successful!' : 'Payment is not successful!'; ?>
            <?php elseif (AbstractGateway::TX_PRE_PAY === $transaction) : ?>
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
        <?php if (AbstractGateway::MODEL_NON_SECURE !== $pos->getAccount()->getModel()): ?>
        <dl class="row">
            <dt class="col-sm-3">mdStatus:</dt>
            <dd class="col-sm-9"><?= $response['md_status'] ?: '-'; ?></dd>
        </dl>
        <hr>
        <hr>
        <dl class="row">
            <dt class="col-sm-3">Md Error Message:</dt>
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
                <?php if (AbstractGateway::TX_PRE_PAY === $transaction) : ?>
                    <a href="<?= $bankTestsUrl ?>/regular/post-auth.php" class="btn btn-lg btn-primary">Finish provisioning
                        ></a>
                <?php endif; ?>
                <?php if (AbstractGateway::TX_PAY === $transaction) : ?>
                    <a href="<?= $bankTestsUrl ?>/regular/cancel.php" class="btn btn-lg btn-danger">Cancel payment</a>
                <?php endif; ?>
                <a href="<?= $bankTestsUrl ?>/regular/status.php" class="btn btn-lg btn-default">Payment Status</a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-lg btn-info">&lt; Click to payment form</a>
        </div>
    </div>

<?php require __DIR__.'/_footer.php';
