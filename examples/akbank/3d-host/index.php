<?php

use Mews\Pos\Entity\Card\CreditCardEstPos;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\EstPos;
use Symfony\Component\HttpFoundation\RedirectResponse;

require '_config.php';

require '../../template/_header.php';

$orderId = date('Ymd').strtoupper(substr(uniqid(sha1(time())), 0, 4));

$amount = (float) 32.0;
$installment = '0';

$successUrl = $baseUrl.'response.php';
$failUrl = $baseUrl.'response.php';

$rand = microtime();

$order = [
    'id'          => $orderId,
    'email'       => 'mail@customer.com', // optional
    'name'        => 'John Doe', // optional
    'amount'      => $amount,
    'installment' => $installment,
    'currency'    => 'TRY',
    'ip'          => $ip,
    'success_url' => $successUrl,
    'fail_url'    => $failUrl,
    'lang'        => EstPos::LANG_TR,
    'rand'        => $rand,
];

$redis->lPush('order', json_encode($order));

$pos->prepare($order, AbstractGateway::TX_PAY);

$formData = $pos->get3DFormData();
?>

    <form method="post" action="<?= $formData['gateway']; ?>" class="redirect-form" role="form">
        <?php foreach ($formData['inputs'] as $key => $value) : ?>
            <input type="hidden" name="<?= $key; ?>" value="<?= $value; ?>">
        <?php endforeach; ?>
        <div class="text-center">Redirecting...</div>
        <hr>
        <div class="form-group text-center">
            <button type="submit" class="btn btn-lg btn-block btn-success">Submit</button>
        </div>
    </form>

<?php require '../../template/_footer.php';
