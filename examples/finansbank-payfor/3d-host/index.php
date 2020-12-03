<?php

require '_config.php';

require '../../template/_header.php';

$orderId = date('Ymd').strtoupper(substr(uniqid(sha1(time())), 0, 4));

$amount = (float) 100.01;

$successUrl = $baseUrl.'response.php';
$failUrl = $baseUrl.'response.php';

$rand = microtime();

$order = [
    'id'                => $orderId,
    'email'             => 'mail@customer.com', // optional
    'name'              => 'John Doe', // optional
    'amount'            => $amount,
    'installment'       => '0',
    'currency'          => 'TRY',
    'ip'                => $ip,
    'success_url'       => $successUrl,
    'fail_url'          => $failUrl,
    'rand'              => $rand,
];
$redis->lPush('order', json_encode($order));

$pos->prepare($order, \Mews\Pos\Gateways\AbstractGateway::TX_PAY);

$form_data = $pos->get3DFormData();
?>

<form method="post" action="<?php echo $form_data['gateway']; ?>" class="redirect-form" role="form">
    <?php foreach ($form_data['inputs'] as $key => $value): ?>
        <input type="hidden" name="<?php echo $key; ?>" value="<?php echo $value; ?>">
    <?php endforeach; ?>
    <div class="text-center">Redirecting...</div>
    <hr>
    <div class="form-group text-center">
        <button type="submit" class="btn btn-lg btn-block btn-success">Submit</button>
    </div>
</form>

<?php require '../../template/_footer.php'; ?>
