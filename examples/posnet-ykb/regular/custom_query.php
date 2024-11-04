<?php

require '../../_common-codes/regular/custom_query.php';

function getCustomRequestData(): array
{
    return [
        [
            'pointUsage' => [
                'amount'       => '250',
                'lpAmount'     => '40',
                'ccno'         => '4048090000000001',
                'currencyCode' => 'TL',
                'expDate'      => '2411',
                'orderID'      => 'PKPPislemleriNT000000001',
            ],
        ],
        null,
    ];
}
