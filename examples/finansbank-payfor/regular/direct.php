<?php

use Mews\Pos\Entity\Card\CreditCardPayFor;
use Mews\Pos\Gateways\AbstractGateway;

require '_config.php';

require '../../template/_header.php';


$orderId = date('Ymd').strtoupper(substr(uniqid(sha1(time())), 0, 4));
$amount = (float) 100.12;

$order = [
    'id'          => $orderId,
    'name'        => 'John Doe', // optional
    'email'       => 'mail@customer.com', // optional
    'user_id'     => '12', // optional
    'amount'      => $amount,
    'installment' => '0',
    'currency'    => 'TRY',
    'ip'          => $ip,
];

$pos->prepare($order, AbstractGateway::TX_PAY);

$card = new CreditCardPayFor('4155650100416111', '25', '01', '123', 'John Doe');


$pos->payment($card);

$response = $pos->getResponse();

?>

    <div class="result">
        <h3 class="text-center text-<?= $pos->isSuccess() ? 'success' : 'danger'; ?>">
            <?= $pos->isSuccess() ? 'Payment is successful!' : 'Payment is not successful!'; ?>
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
            <a href="credit-card-form.php" class="btn btn-lg btn-info">&lt; Click to payment form</a>
        </div>
    </div>

<?php require '../../template/_footer.php';
