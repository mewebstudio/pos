<?php

use Mews\Pos\PosInterface;

require '_config.php';
$templateTitle = 'Cancel Order';
require '../../_templates/_header.php';

$order = [
    'id' => $session->get('ref_ret_num'), //ReferenceTransactionId
    'ip' => $request->getClientIp(),
];
$transaction = PosInterface::TX_CANCEL;

// Cancel Order
$pos->cancel($order);

$response = $pos->getResponse();

require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
