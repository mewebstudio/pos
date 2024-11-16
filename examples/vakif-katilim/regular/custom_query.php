<?php

require '../../_common-codes/regular/custom_query.php';

function getCustomRequestData(): array
{
    return [
        [
            'MerchantOrderId'                  => '2126497214',
            'InstallmentCount'                 => '0',
            'Amount'                           => '120',
            'DisplayAmount'                    => '120',
            'FECAmount'                        => '0',
            'FECCurrencyCode'                  => '0949',
            'Addresses'                        => [
                'VPosAddressContract' => [
                    'Type'        => '1',
                    'Name'        => 'Mahmut Sami YAZAR',
                    'PhoneNumber' => '324234234234',
                    'OrderId'     => '0',
                    'AddressId'   => '12',
                    'Email'       => 'mahmutsamiyazar@hotmail.com',
                ],
            ],
            'CardNumber'                       => '5353550000958906',
            'CardExpireDateYear'               => '23',
            'CardExpireDateMonth'              => '01',
            'CardCVV2'                         => '741',
            'CardHolderName'                   => 'Hasan Karacan',
            'DebtId'                           => '0',
            'SurchargeAmount'                  => '0',
            'SGKDebtAmount'                    => '0',
            'InstallmentMaturityCommisionFlag' => '0',
            'TransactionSecurity'              => '1',
            'CardGuid'                         => 'AA9588EF350C480FBE5CAD40A463AF00',
        ],
        'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/MailOrderSale',
    ];
}
