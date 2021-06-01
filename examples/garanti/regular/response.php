<?php

use Mews\Pos\Entity\Card\CreditCardGarantiPos;
use Mews\Pos\Gateways\AbstractGateway;
use Symfony\Component\HttpFoundation\RedirectResponse;

require '_config.php';

require '../../template/_header.php';

if ($request->getMethod() !== 'POST') {
    echo new RedirectResponse($baseUrl);
    exit();
}

$orderId = date('Ymd').strtoupper(substr(uniqid(sha1(time())), 0, 4));
$amount = (float) 1;

$order = [
    'id'          => $orderId,
    'name'        => 'John Doe', // optional
    'email'       => 'mail@customer.com', // optional
    'user_id'     => '12', // optional
    'amount'      => $amount,
    'installment' => '1',
    'currency'    => 'TRY',
    'ip'          => $ip,
];

$pos->prepare($order, AbstractGateway::TX_PAY);

$card = new CreditCardGarantiPos(
    $request->get('number'),
    $request->get('year'),
    $request->get('month'),
    $request->get('cvv')
);

$pos->payment($card);

$response = $pos->getResponse();
?>

    <div class="result">
        <h3 class="text-center text-<?= $pos->isSuccess() ? 'success' : 'danger'; ?>">
            <?= $pos->isSuccess() ? 'Payment is successful!' : 'Payment is not successful!'; ?>
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
            <dd class="col-sm-9"><?= $response->order_id ? $response->order_id : '-'; ?></dd>
        </dl>
        <hr>
        <dl class="row">
            <dt class="col-sm-3">Group ID:</dt>
            <dd class="col-sm-9"><?= $response->group_id ? $response->group_id : '-'; ?></dd>
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
            <dt class="col-sm-3">RetrefNum:</dt>
            <dd class="col-sm-9"><?= $response->ret_ref_num ? $response->ret_ref_num : '-'; ?></dd>
        </dl>
        <hr>
        <dl class="row">
            <dt class="col-sm-3">HashData:</dt>
            <dd class="col-sm-9"><?= $response->hash_data ? $response->hash_data : '-'; ?></dd>
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
            <a href="index.php" class="btn btn-lg btn-info">&lt; Click to payment form</a>
        </div>
    </div>

<?php require '../../template/_footer.php';
