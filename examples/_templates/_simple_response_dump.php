<?php if (\Mews\Pos\Gateways\AbstractGateway::TX_CANCEL === $transaction): ?>
    <h5 class="text-center">NOT: Iptal işlemi gün sonu <b>kapanmadan</b> önce gerçekleştirilebilir.</h5>
<?php endif; ?>
<?php if (\Mews\Pos\Gateways\AbstractGateway::TX_REFUND === $transaction): ?>
    <h5 class="text-center">NOT: İade işlemi gün sonu <b>kapandıktan</b> sonra gerçekleştirilebilir.</h5>
<?php endif; ?>
<div class="result">
    <h3 class="text-center text-<?= $pos->isSuccess() ? 'success' : 'danger'; ?>">
        <?= $transaction ?> <?= $pos->isSuccess() ? 'order is successful!' : 'order is not successful!'; ?>
    </h3>
    <dl class="row">
        <dt class="col-sm-12">All Data Dump:</dt>
        <dd class="col-sm-12">
            <pre><?php dump($response); ?></pre>
        </dd>
    </dl>
    <hr>
    <div class="text-right">
        <?php if ($pos->isSuccess()) : ?>
            <?php if (\Mews\Pos\Gateways\AbstractGateway::TX_STATUS === $transaction) : ?>
                <a href="<?= $bankTestsUrl ?>/regular/cancel.php" class="btn btn-lg btn-danger">Cancel payment</a>
                <a href="<?= $bankTestsUrl ?>/regular/refund.php" class="btn btn-lg btn-danger">Refund payment</a>
            <?php endif; ?>
            <?php if (\Mews\Pos\Gateways\AbstractGateway::TX_CANCEL === $transaction) : ?>
                <a href="<?= $bankTestsUrl ?>/regular/status.php" class="btn btn-lg btn-danger">Payment Status</a>
            <?php endif; ?>
        <?php endif; ?>
        <a href="index.php" class="btn btn-lg btn-info">&lt; Click to payment form</a>
    </div>
</div>
