<?php

require '../../_common-codes/regular/custom_query.php';

function getCustomRequestData(): array
{
    return [
        [
            'SecureType'     => 'Inquiry',
            'TxnType'        => 'ParaPuanInquiry',
            'Pan'            => '4155650100416111',
            'Expiry'         => '0125',
            'Cvv2'           => '123',
        ],
        null,
    ];
}
