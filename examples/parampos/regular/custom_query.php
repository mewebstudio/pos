<?php

require '../../_common-codes/regular/custom_query.php';

function getCustomRequestData(): array
{
    return [
        [
            // API hesap bilgileri kutuphane tarafindan otomatik eklenir.
            'TP_Ozel_Oran_Liste' => [
                '@xmlns' => 'https://turkpos.com.tr/',
            ],
        ],
        null,
    ];
}
