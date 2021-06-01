<?php

use Mews\Pos\Entity\Card\CreditCardVakifBank;
use Symfony\Component\HttpFoundation\RedirectResponse;

require '_config.php';

require '../../template/_header.php';

if ($request->getMethod() !== 'POST') {
    echo new RedirectResponse($baseUrl.'credit-card-form.php');
    exit();
}

$order = [
    'id'          => $_POST['order_id'],
    'name'        => $_POST['name'],
    'amount'      => $_POST['amount'],
    'currency'    => $_POST['currency'],
    'success_url' => $_POST['success_url'],
    'fail_url'    => $_POST['fail_url'],
    'rand'        => $_POST['rand'],
    'ip'          => $request->getClientIp(),
];

$redis->lPush('order', json_encode($order));

$card = new CreditCardVakifBank(
    $request->get('number'),
    $request->get('year'),
    $request->get('month'),
    $request->get('cvv'),
    $request->get('name'),
    $request->get('type')
);

$pos->prepare($order, $_POST['transaction'], $card);
try {
    $formData = $pos->get3DFormData();
} catch (\Exception $e) {
    echo $e->getMessage();
}
?>

    <span class="text-muted text-center">Redirecting...</span>
    <form method="post" action="<?= $formData['gateway']; ?>" class="redirect-form" role="form">
        <?php foreach ($formData['inputs'] as $key => $value) : ?>
            <input type="hidden" name="<?= $key; ?>" value="<?= $value; ?>">
        <?php endforeach; ?>
        <hr>
        <button class="btn btn-success" type="submit">Submit</button>
    </form>

<?php require '../../template/_footer.php';

