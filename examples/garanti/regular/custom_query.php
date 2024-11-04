<?php

require '../../_common-codes/regular/custom_query.php';

function getCustomRequestData(): array
{
    return [
        [
            'Version'     => 'v0.00',
            'Customer'    => [
                'IPAddress'    => '1.1.111.111',
                'EmailAddress' => 'Cem@cem.com',
            ],
            'Order'       => [
                'OrderID'     => 'SISTD5A61F1682E745B28871872383ABBEB1',
                'GroupID'     => '',
                'Description' => '',
            ],
            'Transaction' => [
                'Type'   => 'bininq',
                'Amount' => '1',
                'BINInq' => [
                    'Group'    => 'A',
                    'CardType' => 'A',
                ],
            ],
        ],
        null,
    ];
}
