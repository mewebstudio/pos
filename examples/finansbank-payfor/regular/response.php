<?php

use Mews\Pos\Entity\Card\CreditCardPayFor;
use Mews\Pos\Gateways\AbstractGateway;
use Symfony\Component\HttpFoundation\RedirectResponse;

require '_config.php';

require '../../template/_header.php';

if ($request->getMethod() !== 'POST') {
    echo new RedirectResponse($baseUrl);
    exit();
}

$orderId = date('Ymd').strtoupper(substr(uniqid(sha1(time())), 0, 4));
$amount = (float) 100.12;

$order = [
    'id'          => $orderId,
    'name'        => 'John Doe', // optional
    'email'       => 'mail@customer.com', // optional
    'user_id'     => '12', // optional
    'amount'      => $amount,
    'installment' => '4',
    'currency'    => 'TRY',
    'ip'          => $ip,
    //'lang'          => \Mews\Pos\PayForPos::LANG_TR
];

$transaction = AbstractGateway::TX_PRE_PAY;
$pos->prepare($order, $transaction);

$card = new CreditCardPayFor(
    $request->get('number'),
    $request->get('year'),
    $request->get('month'),
    $request->get('cvv'),
    $request->get('name')
);

$pos->payment($card);

$response = $pos->getResponse();

if ($pos->isSuccess()) {
    $redis->lPush('order', json_encode($order));
}

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
            <dd class="col-sm-9"><?= $response->order_id ? $response->order_id : '-'; ?></dd>
        </dl>
        <hr>
        <dl class="row">
            <dt class="col-sm-3">AuthCode:</dt>
            <dd class="col-sm-9"><?= $response->auth_code ? $response->auth_code : '-'; ?></dd>
        </dl>
        <hr>
        <dl class="row">
            <dt class="col-sm-3">HostRefNum:</dt>
            <dd class="col-sm-9"><?= $response->host_ref_num ? $response->host_ref_num : '-'; ?></dd>
        </dl>
        <hr>
        <dl class="row">
            <dt class="col-sm-3">TransactionId:</dt>
            <dd class="col-sm-9"><?= $response->trans_id ? $response->trans_id : '-'; ?></dd>
        </dl>
        <hr>
        <dl class="row">
            <dt class="col-sm-3">ProcReturnCode:</dt>
            <dd class="col-sm-9"><?= $response->code; ?></dd>
        </dl>
        <hr>
        <dl class="row">
            <dt class="col-sm-3">Error Code:</dt>
            <dd class="col-sm-9"><?= $response->error_code ? $response->error_code : '-'; ?></dd>
        </dl>
        <hr>
        <dl class="row">
            <dt class="col-sm-3">Error Message:</dt>
            <dd class="col-sm-9"><?= $response->error_message ? $response->error_message : '-'; ?></dd>
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
                <?php if (AbstractGateway::TX_PRE_PAY === $transaction) : ?>
                    <a href="post-auth.php" class="btn btn-lg btn-primary">Finish provisioning
                        ></a>
                <?php endif; ?>
                <?php if (AbstractGateway::TX_PAY === $transaction) : ?>
                    <a href="cancel.php" class="btn btn-lg btn-danger">Cancel payment</a>
                <?php endif; ?>
                <a href="status.php" class="btn btn-lg btn-default">Order Status</a>
            <?php endif; ?>
            <a href="credit-card-form.php" class="btn btn-lg btn-info">&lt; Click to payment form</a>
        </div>

    </div>

<?php require '../../template/_footer.php';

