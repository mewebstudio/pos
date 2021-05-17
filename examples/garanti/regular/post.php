<?php

use Mews\Pos\Exceptions\BankClassNullException;
use Mews\Pos\Exceptions\BankNotFoundException;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Symfony\Component\HttpFoundation\Request;

require '../../_main_config.php';

$path = '/garanti/regular/';
$baseUrl = $hostUrl.$path;

$request = Request::createFromGlobals();
$ip = $request->getClientIp();

$account = [
    'bank'        => 'garanti',
    'model'       => 'regular',
    'client_id'   => '7000679',
    'terminal_id' => '30691297',
    'username'    => 'PROVAUT',
    'password'    => '123qweASD/',
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
    'id'          => '201810231553',
    'amount'      => '1',
    'ref_ret_num' => '829603332856',
    'ip'          => $ip,
];

try {
    $pos->prepare($order, AbstractGateway::TX_POST_PAY);
} catch (\Mews\Pos\Exceptions\UnsupportedTransactionTypeException $e) {
    dump($e->getCode(), $e->getMessage());
}

$payment = $pos->payment();

$response = $payment->getResponse();
?>

    <div class="result">
        <h3 class="text-center text-<?= $response->proc_return_code == '00' ? 'success' : 'danger'; ?>">
            <?= $response->proc_return_code == '00' ? 'Post Auth Order is successful!' : 'Post Auth Order is not successful!'; ?>
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
