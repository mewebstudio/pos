<?php

use Mews\Pos\Exceptions\BankClassNullException;
use Mews\Pos\Exceptions\BankNotFoundException;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Symfony\Component\HttpFoundation\Request;

require '../../_main_config.php';

$path = '/ykb/regular/';
$baseUrl = $hostUrl.$path;

$request = Request::createFromGlobals();
$ip = $request->getClientIp();

$account = [
    'bank'        => 'yapikredi',
    'model'       => 'regular',
    'client_id'   => '6706598320',
    'terminal_id' => '67322946',
    'posnet_id'   => '27426',
    'env'         => 'test',
];

$templateTitle = 'Post Auth Order';

require '../../template/_header.php';

try {
    $pos = PosFactory::createPosGateway($account);
    $pos->setTestMode(true);
} catch (BankNotFoundException $e) {
    dump($e->getCode(), $e->getMessage());
} catch (BankClassNullException $e) {
    dump($e->getCode(), $e->getMessage());
}

$order = [
    'id'           => '2018102949E0',
    'host_ref_num' => '018711533790000181',
    'amount'       => '100',
    'currency'     => 'TRY',
    'installment'  => '2',
];

try {
    $pos->prepare($order, AbstractGateway::TX_POST_PAY);
} catch (\Mews\Pos\Exceptions\UnsupportedTransactionTypeException $e) {
    dump($e->getCode(), $e->getMessage());
}

$pos->payment();

$response = $pos->getResponse();
?>

    <div class="result">
        <h3 class="text-center text-<?= $pos->isSuccess() ? 'success' : 'danger'; ?>">
            <?= $pos->isSuccess() ? 'Post Auth Order is successful!' : 'Post Auth Order is not successful!'; ?>
        </h3>
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
