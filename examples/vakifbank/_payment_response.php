<?php

use Mews\Pos\Gateways\AbstractGateway;
use Symfony\Component\HttpFoundation\RedirectResponse;

require_once '_config.php';
require '../../template/_header.php';
require '../_header.php';

if ($request->getMethod() !== 'POST' && AbstractGateway::TX_POST_PAY !== $transaction) {
    echo new RedirectResponse($baseUrl);
    exit();
}

$order = $session->get('order');

$pos->prepare($order, $transaction);

if (AbstractGateway::TX_POST_PAY !== $transaction) {
    /**
     * diger banklaradan farkli olarak 3d islemler icin de Vakifbank bu asamada kredi kart bilgileri istiyor
     */
    $payment = $pos->payment($card);
} else {
    $payment = $pos->payment();
}

$response = $payment->getResponse();
?>

    <div class="result">
        <h3 class="text-center text-<?= $pos->isSuccess() ? 'success' : 'danger'; ?>">
            <?php if (AbstractGateway::TX_PAY === $transaction) : ?>
                <?= $pos->isSuccess() ? 'Payment is successful!' : 'Payment is not successful!'; ?>
            <?php elseif (AbstractGateway::TX_PRE_PAY === $transaction) : ?>
                <?= $pos->isSuccess() ? 'Pre Authorization is successful!' : 'Pre Authorization is not successful!'; ?>
            <?php endif; ?>
        </h3>

        <hr>
        <dl class="row">
            <dt class="col-sm-3">Response:</dt>
            <dd class="col-sm-9"><?= $response->response; ?></dd>
        </dl>
        <hr>
        <dl class="row">
            <dt class="col-sm-3">Status:</dt>
            <dd class="col-sm-9"><?= $response->status; ?></dd>
        </dl>
        <hr>
        <dl class="row">
            <dt class="col-sm-3">Transaction:</dt>
            <dd class="col-sm-9"><?= $response->transaction; ?></dd>
        </dl>
        <hr>
        <dl class="row">
            <dt class="col-sm-3">Transaction Type:</dt>
            <dd class="col-sm-9"><?= $response->transaction_type; ?></dd>
        </dl>
        <hr>
        <dl class="row">
            <dt class="col-sm-3">Order ID:</dt>
            <dd class="col-sm-9"><?= $response->order_id ?: '-'; ?></dd>
        </dl>
        <hr>
        <dl class="row">
            <dt class="col-sm-3">AuthCode:</dt>
            <dd class="col-sm-9"><?= $response->auth_code ?: '-'; ?></dd>
        </dl>
        <hr>
        <dl class="row">
            <dt class="col-sm-3">HostRefNum:</dt>
            <dd class="col-sm-9"><?= $response->host_ref_num ?: '-'; ?></dd>
        </dl>
        <hr>
        <dl class="row">
            <dt class="col-sm-3">ProcReturnCode:</dt>
            <dd class="col-sm-9"><?= $response->code ?: '-'; ?></dd>
        </dl>
        <hr>
        <dl class="row">
            <dt class="col-sm-3">Error Code:</dt>
            <dd class="col-sm-9"><?= $response->error_code ?: '-'; ?></dd>
        </dl>
        <dl class="row">
            <dt class="col-sm-3">Status Detail:</dt>
            <dd class="col-sm-9"><?= $response->status_detail ?: '-'; ?></dd>
        </dl>
        <?php if ('regular' !== $pos->getAccount()->getModel()): ?>
            <hr>
            <dl class="row">
                <dt class="col-sm-3">Error Message:</dt>
                <dd class="col-sm-9"><?= $response->error_message ?: '-'; ?></dd>
            </dl>
            <dl class="row">
                <dt class="col-sm-3">mdStatus:</dt>
                <dd class="col-sm-9"><?= $response->md_status ?: '-'; ?></dd>
            </dl>
            <hr>
            <hr>
            <dl class="row">
                <dt class="col-sm-3">Md Error Message:</dt>
                <dd class="col-sm-9"><?= $response->md_error_message ?: '-'; ?></dd>
            </dl>
            <hr>
            <dl class="row">
                <dt class="col-sm-3">Transaction Security:</dt>
                <dd class="col-sm-9"><?= $response->transaction_security; ?></dd>
            </dl>
            <hr>
            <dl class="row">
                <dt class="col-sm-3">Hash:</dt>
                <dd class="col-sm-9"><?= $response->hash; ?></dd>
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
                    <a href="post-auth.php" class="btn btn-lg btn-primary">Finish provisioning
                        ></a>
                <?php endif; ?>
                <?php if (AbstractGateway::TX_PAY === $transaction) : ?>
                    <a href="cancel.php" class="btn btn-lg btn-danger">Cancel payment</a>
                <?php endif; ?>
                <a href="status.php" class="btn btn-lg btn-default">Order Status</a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-lg btn-info">&lt; Click to payment form</a>
        </div>
    </div>

<?php require '../../template/_footer.php';
