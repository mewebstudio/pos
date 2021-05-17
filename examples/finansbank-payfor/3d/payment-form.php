<?php

require '_config.php';

require '../../template/_header.php';

$orderId = date('Ymd').strtoupper(substr(uniqid(sha1(time())), 0, 4));

$amount = (float) 100.01;

$successUrl = $baseUrl.'response.php';
$failUrl = $baseUrl.'response.php';

$rand = microtime();

$order = [
    'id'          => $orderId,
    'email'       => 'mail@customer.com', // optional
    'name'        => 'John Doe', // optional
    'amount'      => $amount,
    'installment' => '0',
    'currency'    => 'TRY',
    'ip'          => $ip,
    'success_url' => $successUrl,
    'fail_url'    => $failUrl,
    'rand'        => $rand,
];

$pos->prepare($order, \Mews\Pos\Gateways\AbstractGateway::TX_PAY);
//$order['hash'] = $pos->create3D();
$redis->lPush('order', json_encode($order));
$redis->lPush('transaction', \Mews\Pos\Gateways\AbstractGateway::TX_PAY);

$formData = $pos->get3DFormData();

?>

    <form method="post" action="<?= $formData['gateway']; ?>" role="form">
        <?php foreach ($formData['inputs'] as $key => $value) : ?>
            <input type="hidden" name="<?= $key; ?>" value="<?= $value; ?>">
        <?php endforeach; ?>
        <div class="row">
            <div class="form-group col-sm-12">
                <label for="number">Card Number</label>
                <input type="text" name="Pan" id="number" class="form-control input-lg" placeholder="Credit card number"
                       value="4155650100416111">
            </div>
            <div class="form-group col-sm-12">
                <label for="number">Card Holder Name</label>
                <input type="text" name="CardHolderName" id="number" class="form-control input-lg" placeholder=""
                       value="John Doe">
            </div>
            <div class="form-group col-sm-6">
                <label for="number">Expiration Date (format: mmyy)</label>
                <input type="text" name="Expiry" id="number" class="form-control input-lg" placeholder="format: mmyy"
                       value="0122">
            </div>
            <div class="form-group col-sm-6">
                <label for="cvv">Cvv</label>
                <input type="text" name="Cvv2" id="cvv" class="form-control input-lg" placeholder="Cvv" value="123">
            </div>
        </div>
        <hr>
        <div class="form-group text-center">
            <button type="submit" class="btn btn-lg btn-block btn-success">
                Pay <?= $order['amount'] ?> <?= $order['currency']; ?></button>
        </div>
    </form>

<?php require '../../template/_footer.php';
