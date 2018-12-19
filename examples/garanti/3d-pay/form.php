<?php

require '_config.php';

require '../../template/_header.php';

if ($request->getMethod() !== 'POST') {
    echo new \Symfony\Component\HttpFoundation\RedirectResponse($base_url);
    exit();
}

$order_id = date('Ymd') . strtoupper(substr(uniqid(sha1(time())),0,4));

$amount = (double) 1;
$instalment = '0';

$success_url = $base_url . 'response.php';
$fail_url = $base_url . 'response.php';

$transaction = 'pay'; // pay => Auth, pre PreAuth
$transaction_type = $pos->bank->types[$transaction];

$rand = microtime();

$order = [
    'id'                => $order_id,
    'email'             => 'mail@customer.com', // optional
    'name'              => 'John Doe', // optional
    'amount'            => $amount,
    'installment'       => $instalment,
    'currency'          => 'TRY',
    'ip'                => $ip,
    'success_url'       => $success_url,
    'fail_url'          => $fail_url,
    'transaction'       => $transaction,
    'lang'              => 'tr',
    'rand'              => $rand,
];

$_SESSION['order'] = $order;

$card = [
    'name'      => $request->get('name'),
    'type'      => $request->get('type'),
    'number'    => $request->get('number'),
    'month'     => $request->get('month'),
    'year'      => $request->get('year'),
    'cvv'       => $request->get('cvv'),
];

$pos->prepare($order, $card);

$form_data = $pos->get3dFormData();
?>

<form method="post" action="<?php echo $form_data['gateway']; ?>" class="redirect-form" role="form">
    <?php foreach ($form_data['inputs'] as $key => $value): ?>
    <input type="hidden" name="<?php echo $key; ?>" value="<?php echo $value; ?>">
    <?php endforeach; ?>
    <div class="text-center">Redirecting...</div>
    <hr>
    <pre><?php print_r($form_data) ?></pre>
    <div class="form-group text-center">
        <button type="submit" class="btn btn-lg btn-block btn-success">Submit</button>
    </div>
</form>

<?php require '../../template/_footer.php'; ?>
