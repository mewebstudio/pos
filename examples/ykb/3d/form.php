<?php

require '_config.php';

require '../../template/_header.php';

if ($request->getMethod() !== 'POST') {
    echo new \Symfony\Component\HttpFoundation\RedirectResponse($baseUrl);
    exit();
}

$order = [
    'id'            => $_POST['order_id'],
    'name'          => $_POST['name'],
    'amount'        => $_POST['amount'],
    'currency'      => $_POST['currency'],
    'transaction'   => $_POST['transaction'],
    'success_url'   => $_POST['success_url'],
    'fail_url'      => $_POST['fail_url'],
    'lang'          => $_POST['lang'],
];

$redis->lPush('order', json_encode($order));

$card = new \Mews\Pos\Entity\Card\CreditCardPosNet(
    $request->get('number'),
    $request->get('year'),
    $request->get('month'),
    $request->get('cvv'),
    $request->get('name'),
    $request->get('type')
);

$pos->prepare($order, $card);

$form_data = $pos->get3dFormData();
?>

<span class="text-muted text-center">Redirecting...</span>
<form method="post" action="<?php echo $form_data['gateway']; ?>" class="redirect-form" role="form">
    <?php foreach ($form_data['inputs'] as $key => $value): ?>
        <input type="hidden" name="<?php echo $key; ?>" value="<?php echo $value; ?>">
    <?php endforeach; ?>
    <hr>
    <button class="btn btn-success" type="submit">Submit</button>
</form>

<?php require '../../template/_footer.php'; ?>
