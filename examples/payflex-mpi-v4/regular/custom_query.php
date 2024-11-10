<?php

require '../../_common-codes/regular/custom_query.php';

function getCustomRequestData(): array
{
    return [
        [
            'TransactionType' => 'CampaignSearch',
            'TransactionId'   => date('Ymd').strtoupper(substr(uniqid(sha1(time()), true), 0, 4)),
        ],
        null,
    ];
}
