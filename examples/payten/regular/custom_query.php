<?php

require '../../_common-codes/regular/custom_query.php';

function getCustomRequestData(): array
{
    return [
        [
            'Type'     => 'Query',
            'Number'   => '4242424242424242',
            'Expires'  => '10.2028',
            'Extra'    => [
                'IMECECARDQUERY' => null,
            ],
        ],
        null,
    ];
}
