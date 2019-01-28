<?php

require '_config.php';

require '../../template/_header.php';

if ($request->getMethod() !== 'POST') {
    echo new \Symfony\Component\HttpFoundation\RedirectResponse($base_url);
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

$_SESSION['order'] = $order;

$card = [
    'type'      => $_POST['type'],
    'name'      => $_POST['name'],
    'number'    => $_POST['number'],
    'month'     => $_POST['month'],
    'year'      => $_POST['year'],
    'cvv'       => $_POST['cvv'],
];

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
