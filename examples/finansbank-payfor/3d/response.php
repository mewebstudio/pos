<?php

require '_config.php';

require '../../template/_header.php';

if ($request->getMethod() !== 'POST') {
    echo new \Symfony\Component\HttpFoundation\RedirectResponse($baseUrl);
    exit();
}
$order = (array) json_decode($redis->lPop('order'));
dump($account);
dump($order);

$pos->prepare($order);
$payment = $pos->payment();
$response = $payment->getResponse();

if ($pos->isSuccess()) {
    $redis->lPush('order', json_encode($order));
}
?>

<div class="result">
    <h3 class="text-center text-<?php echo $pos->isSuccess() ? 'success' : 'danger'; ?>">
        <?php echo $pos->isSuccess() ? 'Payment is successful!' : 'Payment is not successful'; ?>
    </h3>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">Status:</dt>
        <dd class="col-sm-9"><?php echo $response->status; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">Transaction:</dt>
        <dd class="col-sm-9"><?php echo $response->transaction; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">Transaction Type:</dt>
        <dd class="col-sm-9"><?php echo $response->transaction_type; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">Hash:</dt>
        <dd class="col-sm-9"><?php echo $response->hash; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">Order ID:</dt>
        <dd class="col-sm-9"><?php echo $response->order_id ? $response->order_id : '-'; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">AuthCode:</dt>
        <dd class="col-sm-9"><?php echo $response->auth_code ? $response->auth_code : '-'; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">HostRefNum:</dt>
        <dd class="col-sm-9"><?php echo $response->host_ref_num ? $response->host_ref_num : '-'; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">ProcReturnCode:</dt>
        <dd class="col-sm-9"><?php echo $response->code ? $response->code : '-'; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">mdStatus:</dt>
        <dd class="col-sm-9"><?php echo $response->md_status ? $response->md_status : '-'; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">Error Code:</dt>
        <dd class="col-sm-9"><?php echo $response->error_code ? $response->error_code : '-'; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">Error Message:</dt>
        <dd class="col-sm-9"><?php echo $response->error_message ? $response->error_message : '-'; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">Md Error Message:</dt>
        <dd class="col-sm-9"><?php echo $response->md_error_message ? $response->md_error_message : '-'; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">Md Error Code:</dt>
        <dd class="col-sm-9"><?php echo $response->md_error_code ? $response->md_error_code : '-'; ?></dd>
    </dl>
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
            <?php if ('pay' === $order['transaction']) : ?>
                <a href="../regular/cancel.php" class="btn btn-lg btn-danger">Cancel payment</a>
            <?php endif; ?>
            <a href="../regular/status.php" class="btn btn-lg btn-default">Order Status</a>
        <?php endif; ?>
        <a href="payment-form.php" class="btn btn-lg btn-info">&lt; Click to payment form</a>
    </div>
</div>

<?php require '../../template/_footer.php'; ?>
