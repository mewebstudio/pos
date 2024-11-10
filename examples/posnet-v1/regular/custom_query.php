<?php

require '../../_common-codes/regular/custom_query.php';

function getCustomRequestData(): array
{
    return [
        [
            'MACParams'              => 'MerchantNo:TerminalNo:CardNo:Cvc2:ExpireDate',
            'CipheredData'           => null,
            'DealerData'             => null,
            'IsEncrypted'            => 'N',
            'PaymentFacilitatorData' => null,
            'AdditionalInfoData'     => null,
            'CardInformationData'    => [
                'CardHolderName' => 'deneme deneme',
                'CardNo'         => '5400619360964581',
                'Cvc2'           => '056',
                'ExpireDate'     => '2001',
            ],
            'IsMailOrder'            => null,
            'IsRecurring'            => null,
            'IsTDSecureMerchant'     => 'Y',
            'PaymentInstrumentType'  => 'CARD',
            'ThreeDSecureData'       => null,
        ],
        'https://epostest.albarakaturk.com.tr/ALBMerchantService/MerchantJSONAPI.svc/PointInquiry',
    ];
}
