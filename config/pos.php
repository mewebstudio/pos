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
            'class' => Mews\Pos\EstPos::class,
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
            'class' => Mews\Pos\EstPos::class,
            'urls'  => [
                'production'    => 'https://sanalpos2.ziraatbank.com.tr/fim/api',
                'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/api',
                'gateway'       => [
                    'production'    => 'https://sanalpos2.ziraatbank.com.tr/fim/est3dgate',
                    'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                ],
            ]
        ],
        'finansbank'    => [
            'name'  => 'QNB Finansbank',
            'class' => Mews\Pos\EstPos::class,
            'urls'  => [
                'production'    => 'https://www.fbwebpos.com/fim/api',
                'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/api',
                'gateway'       => [
                    'production'    => 'https://www.fbwebpos.com/fim/est3dgate',
                    'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                ],
            ]
        ],
        'halkbank'    => [
            'name'  => 'Halkbank',
            'class' => Mews\Pos\EstPos::class,
            'urls'  => [
                'production'    => 'https://sanalpos.halkbank.com.tr/fim/api',
                'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/api',
                'gateway'       => [
                    'production'    => 'https://sanalpos.halkbank.com.tr/fim/est3dgate',
                    'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                ],
            ]
        ],
        'teb'    => [
            'name'  => 'TEB',
            'class' => Mews\Pos\EstPos::class,
            'urls'  => [
                'production'    => 'https://sanalpos.teb.com.tr/fim/api',
                'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/api',
                'gateway'       => [
                    'production'    => 'https://sanalpos.teb.com.tr/fim/est3Dgate',
                    'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                ],
            ]
        ],
        'isbank'    => [
            'name'  => 'İşbank',
            'class' => Mews\Pos\EstPos::class,
            'urls'  => [
                'production'    => 'https://sanalpos.isbank.com.tr/fim/api',
                'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/api',
                'gateway'       => [
                    'production'    => 'https://sanalpos.isbank.com.tr/fim/est3Dgate',
                    'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                ],
            ]
        ],
        'isbank-payflex'    => [
            'name'  => 'İşbank - PayFlex',
            'class' => Mews\Pos\PayFlex::class,
            'urls'  => [
                'production'    => 'https://trx.payflex.com.tr/VposWeb/v3/Vposreq.aspx',
                'test'          => 'https://sanalpos.innova.com.tr/ISBANK_v4/VposWeb/v3/Vposreq.aspx',
                'gateway'       => [
                    'production'    => 'https://mpi.vpos.isbank.com.tr/MPIEnrollment.aspx',
                    'test'          => 'https://sanalpos.innova.com.tr/ISBANK/MpiWeb/Enrollment.aspx',
                ],
            ]
        ],
        'yapikredi' => [
            'name'  => 'Yapıkredi',
            'class' => Mews\Pos\PosNet::class,
            'urls'  => [
                'production'    => 'https://www.posnet.ykb.com/PosnetWebService/XML',
                'test'          => 'https://setmpos.ykb.com/PosnetWebService/XML',
                'gateway'       => [
                    'production'    => 'https://www.posnet.ykb.com/3DSWebService/YKBPaymentService',
                    'test'          => 'https://setmpos.ykb.com/3DSWebService/YKBPaymentService',
                ],
            ],
			'order' => [
			    'id_total_length' => 24,
				'id_length' => 20,
				'id_3d_prefix' => 'TDSC',
                'id_3d_pay_prefix' => '', //?
                'id_regular_prefix' => '' //?
			]
        ],
        'garanti' => [
            'name'  => 'Garanti',
            'class' => Mews\Pos\GarantiPos::class,
            'urls'  => [
                'production'    => 'https://sanalposprov.garanti.com.tr/VPServlet',
                'test'          => 'https://sanalposprovtest.garanti.com.tr/VPServlet',
                'gateway'       => [
                    'production'    => 'https://sanalposprov.garanti.com.tr/servlet/gt3dengine',
                    'test'          => 'https://sanalposprovtest.garanti.com.tr/servlet/gt3dengine',
                ],
            ]
        ]
    ],

];
