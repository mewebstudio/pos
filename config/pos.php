<?php

return [

    // Currencies
    'currencies'    => [
        'TRY'       => 949,
        'USD'       => 840,
        'EUR'       => 978,
        'GBP'       => 826,
        'JPY'       => 392,
        'RUB'       => 643,
    ],

    // Banks
    'banks'         => [
        'akbank'    => [
            'name'  => 'AKBANK T.A.S.',
            'class' => \Mews\Pos\EstPos::class,
            'urls'  => [
                'production'    => 'https://www.sanalakpos.com/fim/api',
                'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/api',
                'gateway'       => [
                    'production'    => 'https://www.sanalakpos.com/fim/est3Dgate',
                    'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                ],
            ]
        ],
        'ziraat'    => [
            'name'  => 'Ziraat Bankası',
            'class' => \Mews\Pos\EstPos::class,
            'urls'  => [
                'production'    => 'https://sanalpos2.ziraatbank.com.tr/fim/api',
                'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/api',
                'gateway'       => [
                    'production'    => 'https://sanalpos2.ziraatbank.com.tr/fim/est3dgate',
                    'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                ],
            ]
        ],
        'isbank'    => [
            'name'  => 'İşbank',
            'class' => \Mews\Pos\EstPos::class,
            'urls'  => [
                'production'    => 'https://sanalpos.isbank.com.tr/fim/api',
                'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/api',
                'gateway'       => [
                    'production'    => 'https://sanalpos.isbank.com.tr/fim/est3Dgate',
                    'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                ],
            ]
        ],
        'yapikredi' => [
            'name'  => 'Yapıkredi',
            'class' => \Mews\Pos\PosNet::class,
            'urls'  => [
                'production'    => 'https://posnet.yapikredi.com.tr/PosnetWebService/XML',
                'test'          => 'http://setmpos.ykb.com/PosnetWebService/XML',
                'gateway'       => [
                    'production'    => 'http://posnet.ykb.com/3DSWebService/YKBPaymentService',
                    'test'          => 'http://setmpos.ykb.com/3DSWebService/YKBPaymentService',
                ],
            ]
        ],
        'garanti' => [
            'name'  => 'Garanti',
            'class' => \Mews\Pos\GarantiPos::class,
            'urls'  => [
                'production'    => 'https://sanalposprov.garanti.com.tr/VPServlet',
                'test'          => 'https://sanalposprovtest.garanti.com.tr/VPServlet',
                'gateway'       => [
                    'production'    => 'https://sanalposprov.garanti.com.tr/servlet/gt3dengine',
                    'test'          => 'https://sanalposprovtest.garanti.com.tr/servlet/gt3dengine',
                ],
            ]
        ],
    ],

];
