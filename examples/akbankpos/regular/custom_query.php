<?php

require '../../_common-codes/regular/custom_query.php';

function getCustomRequestData(): array
{
    return [
        [
            'txnCode'     => '1020',
            'order'       => [
                'orderTrackId' => 'ae15a6c8-467e-45de-b24c-b98821a42667',
            ],
            'payByLink'   => [
                'linkTxnCode'       => '3000',
                'linkTransferType'  => 'SMS',
                'mobilePhoneNumber' => '5321234567',
            ],
            'transaction' => [
                'amount'       => 1.00,
                'currencyCode' => 949,
                'motoInd'      => 0,
                'installCount' => 1,
            ],
        ],
        null,
    ];
}
