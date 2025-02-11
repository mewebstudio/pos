<div class="result">
    <h3 class="text-center text-<?= $pos->isSuccess() ? 'success' : 'danger'; ?>">
        <?php if (\Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH === $transaction || \Mews\Pos\PosInterface::TX_TYPE_PAY_POST_AUTH === $transaction) : ?>
            <?= $pos->isSuccess() ? 'Payment is successful!' : 'Payment is not successful!'; ?>
        <?php elseif (\Mews\Pos\PosInterface::TX_TYPE_PAY_PRE_AUTH === $transaction) : ?>
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
        <dt class="col-sm-3">RetRefNum <small>(iade, iptal, durum sorgulama icin kullanilacak numara)</small>:</dt>
        <dd class="col-sm-9"><?= $response['ref_ret_num'] ?: '-'; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">Transaction ID <small>(iade, iptal, durum sorgulama icin kullanilacak numara)</small>:</dt>
        <dd class="col-sm-9"><?= $response['transaction_id'] ?: '-'; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">ProcReturnCode:</dt>
        <dd class="col-sm-9"><?= $response['proc_return_code'] ?: '-'; ?></dd>
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
    <?php if (\Mews\Pos\PosInterface::MODEL_NON_SECURE !== $paymentModel): ?>
    <!--bu alanlar non secure odemede yer almaz.-->
    <dl class="row">
        <dt class="col-sm-3">MD Status <small>(3D Secure doğrulama başarılı oldugu durumda degeri (genelde) 1
                oluyor)</small>:
        </dt>
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
    <?php endif; ?>
    <hr>
    <div class="text-right">
        <?php if ($pos->isSuccess()) : ?>
            <!--yapılan ödeme tipine göre bir sonraki yapılabilecek işlemlerin butonlarını gösteriyoruz.-->
            <?php if (\Mews\Pos\PosInterface::TX_TYPE_PAY_PRE_AUTH === $transaction) : ?>
                <a href="<?= $bankTestsUrl ?>/regular/post-auth.php" class="btn btn-lg btn-primary">Finish provisioning
                    ></a>
            <?php endif; ?>
            <?php if (\Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH === $transaction) : ?>
                <a href="<?= $bankTestsUrl ?>/regular/cancel.php" class="btn btn-lg btn-danger">Cancel payment</a>
            <?php endif; ?>
            <a href="<?= $bankTestsUrl ?>/regular/status.php" class="btn btn-lg btn-light">Payment Status</a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-lg btn-info">&lt; Click to payment form</a>
    </div>
    <hr>
    <dl class="row">
        <dt class="col-sm-12">All Data Dump:</dt>
        <dd class="col-sm-12">
            <pre><?php dump($response); ?></pre>
        </dd>
    </dl>
</div>
