<?php

return [
    //if you need to use custom keys for currency mapping, otherwise leave empty
    'currencies'    => [
//        'TRY'       => '949',
//        'USD'       => '840',
    ],
    // Banks
    'banks'         => [
        'akbankv3'    => [
            'name'  => 'AKBANK T.A.S.',
            'class' => Mews\Pos\Gateways\EstV3Pos::class,
            'urls'  => [
                'production'    => 'https://www.sanalakpos.com/fim/api',
                'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/api',
                'gateway'       => [
                    'production'    => 'https://www.sanalakpos.com/fim/est3Dgate',
                    'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                ],
                'gateway_3d_host'       => [
                    'production'    => 'https://sanalpos.sanalakpos.com.tr/fim/est3Dgate',
                    'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                ],
            ],
        ],
        'akbank'    => [
            'name'  => 'AKBANK T.A.S.',
            'class' => Mews\Pos\Gateways\EstPos::class,
            'urls'  => [
                'production'    => 'https://www.sanalakpos.com/fim/api',
                'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/api',
                'gateway'       => [
                    'production'    => 'https://www.sanalakpos.com/fim/est3Dgate',
                    'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                ],
                'gateway_3d_host'       => [
                    'production'    => 'https://sanalpos.sanalakpos.com.tr/fim/est3Dgate',
                    'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                ],
            ],
        ],
        'finansbank'    => [
            'name'  => 'QNB Finansbank',
            'class' => Mews\Pos\Gateways\EstV3Pos::class,
            'urls'  => [
                'production'    => 'https://www.fbwebpos.com/fim/api',
                'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/api',
                'gateway'       => [
                    'production'    => 'https://www.fbwebpos.com/fim/est3dgate',
                    'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                ],
            ],
        ],
        'halkbank'    => [
            'name'  => 'Halkbank',
            'class' => Mews\Pos\Gateways\EstV3Pos::class,
            'urls'  => [
                'production'    => 'https://sanalpos.halkbank.com.tr/fim/api',
                'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/api',
                'gateway'       => [
                    'production'    => 'https://sanalpos.halkbank.com.tr/fim/est3dgate',
                    'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                ],
            ],
        ],
        'teb'    => [
            'name'  => 'TEB',
            'class' => Mews\Pos\Gateways\EstV3Pos::class,
            'urls'  => [
                'production'    => 'https://sanalpos.teb.com.tr/fim/api',
                'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/api',
                'gateway'       => [
                    'production'    => 'https://sanalpos.teb.com.tr/fim/est3Dgate',
                    'test'          => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                ],
            ],
        ],
        'isbank'    => [
            'name'  => 'İşbank T.A.S.',
            'class' => Mews\Pos\Gateways\EstPos::class,
            'urls'  => [
                'production'    => 'https://sanalpos.isbank.com.tr/fim/api',
                'test'          => 'https://istest.asseco-see.com.tr/fim/api',
                'gateway'       => [
                    'production'    => 'https://sanalpos.isbank.com.tr/fim/est3Dgate',
                    'test'          => 'https://istest.asseco-see.com.tr/fim/est3Dgate',
                ],
                'gateway_3d_host'       => [
                    'production'    => 'https://sanalpos.isbank.com.tr/fim/est3Dgate',
                    'test'          => 'https://istest.asseco-see.com.tr/fim/est3Dgate',
                ],
            ],
        ],
        'sekerbank' => [
            'name' => 'Şeker Bank',
            'class' => Mews\Pos\Gateways\EstV3Pos::class,
            'urls' => [
                'production' => 'https://sanalpos.sekerbank.com.tr/fim/api',
                'test' => 'https://entegrasyon.asseco-see.com.tr/fim/api',
                'gateway' => [
                    'production' => 'https://sanalpos.sekerbank.com.tr/fim/est3Dgate',
                    'test' => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                ],
                'gateway_3d_host' => [
                    'production' => 'https://sanalpos.sekerbank.com.tr/fim/est3Dgate',
                    'test' => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
                ],
            ],
        ],
        'yapikredi' => [
            'name'  => 'Yapıkredi',
            'class' => Mews\Pos\Gateways\PosNet::class,
            'urls'  => [
                'production'    => 'https://posnet.yapikredi.com.tr/PosnetWebService/XML',
                'test'          => 'https://setmpos.ykb.com/PosnetWebService/XML',
                'gateway'       => [
                    'production'    => 'https://posnet.yapikredi.com.tr/3DSWebService/YKBPaymentService',
                    'test'          => 'https://setmpos.ykb.com/3DSWebService/YKBPaymentService',
                ],
            ],
        ],
        'albaraka' => [
            'name'  => 'Albaraka',
            'class' => Mews\Pos\Gateways\PosNetV1Pos::class,
            'urls'  => [
                'production'    => 'https://epos.albarakaturk.com.tr/ALBMerchantService/MerchantJSONAPI.svc',
                'test'          => 'https://epostest.albarakaturk.com.tr/ALBMerchantService/MerchantJSONAPI.svc',
                'gateway'       => [
                    'production'    => 'https://epos.albarakaturk.com.tr/ALBSecurePaymentUI/SecureProcess/SecureVerification.aspx',
                    'test'          => 'https://epostest.albarakaturk.com.tr/ALBSecurePaymentUI/SecureProcess/SecureVerification.aspx',
                ],
            ],
        ],
        'garanti' => [
            'name'  => 'Garanti',
            'class' => Mews\Pos\Gateways\GarantiPos::class,
            'urls'  => [
                'production'    => 'https://sanalposprov.garanti.com.tr/VPServlet',
                'test'          => 'https://sanalposprovtest.garantibbva.com.tr/VPServlet',
                'gateway'       => [
                    'production'    => 'https://sanalposprov.garanti.com.tr/servlet/gt3dengine',
                    'test'          => 'https://sanalposprovtest.garantibbva.com.tr/servlet/gt3dengine',
                ],
            ],
        ],
        'qnbfinansbank-payfor' => [
            'name'  => 'QNBFinansbank-PayFor',
            'class' => Mews\Pos\Gateways\PayForPos::class,
            'urls'  => [
                'production'    => 'https://vpos.qnbfinansbank.com/Gateway/XMLGate.aspx',
                'test'          => 'https://vpostest.qnbfinansbank.com/Gateway/XMLGate.aspx',
                'gateway'       => [
                    'production'    => 'https://vpos.qnbfinansbank.com/Gateway/Default.aspx',
                    'test'          => 'https://vpostest.qnbfinansbank.com/Gateway/Default.aspx',
                ],
                'gateway_3d_host'       => [
                    'production'    => 'https://vpos.qnbfinansbank.com/Gateway/3DHost.aspx',
                    'test'          => 'https://vpostest.qnbfinansbank.com/Gateway/3DHost.aspx',
                ],
            ],
        ],
        'vakifbank' => [
            'name'  => 'VakifBank-VPOS',
            'class' => Mews\Pos\Gateways\PayFlexV4Pos::class,
            'urls'  => [
                'production'    => 'https://onlineodeme.vakifbank.com.tr:4443/VposService/v3/Vposreq.aspx',
                'test'          => 'https://onlineodemetest.vakifbank.com.tr:4443/VposService/v3/Vposreq.aspx',
                'gateway'       => [
                    'production'    => 'https://3dsecure.vakifbank.com.tr:4443/MPIAPI/MPI_Enrollment.aspx',
                    'test'          => 'https://3dsecuretest.vakifbank.com.tr:4443/MPIAPI/MPI_Enrollment.aspx',
                ],
                'query'       => [
                    // todo update with the correct ones
                    'production'    => 'https://sanalpos.vakifbank.com.tr/v4/UIWebService/Search.aspx',
                    'test'          => 'https://sanalpos.vakifbank.com.tr/v4/UIWebService/Search.aspx',
                ],
            ],
        ],
        'ziraat-vpos' => [
            'name'  => 'Ziraat Bankası',
            'class' => Mews\Pos\Gateways\PayFlexV4Pos::class,
            'urls'  => [
                'production'    => 'https://sanalpos.ziraatbank.com.tr/v4/v3/Vposreq.aspx',
                'test'          => 'https://preprod.payflex.com.tr/Ziraatbank/VposWeb/v3/Vposreq.aspx',
                'gateway'       => [
                    'production'    => 'https://mpi.ziraatbank.com.tr/Enrollment.aspx',
                    'test'          => 'https://preprod.payflex.com.tr/ZiraatBank/MpiWeb/MPI_Enrollment.aspx',
                ],
                'query'       => [
                    'production'    => 'https://sanalpos.ziraatbank.com.tr/v4/UIWebService/Search.aspx',
                    // todo update with the correct one
                    'test'          => 'https://sanalpos.ziraatbank.com.tr/v4/UIWebService/Search.aspx',
                ],
            ],
        ],
        'vakifbank-cp' => [
            'name'  => 'VakifBank-PayFlex-Common-Payment',
            'class' => Mews\Pos\Gateways\PayFlexCPV4Pos::class,
            'urls'  => [
                'production'    => 'https://cpweb.vakifbank.com.tr/CommonPayment/api/RegisterTransaction',
                'test'          => 'https://cptest.vakifbank.com.tr/CommonPayment/api/RegisterTransaction',
                'gateway'       => [
                    'production'    => 'https://cpweb.vakifbank.com.tr/CommonPayment/SecurePayment',
                    'test'          => 'https://cptest.vakifbank.com.tr/CommonPayment/SecurePayment',
                ],
                'query'       => [
                    'production'    => 'https://cpweb.vakifbank.com.tr/CommonPayment/api/VposTransaction',
                    'test'          => 'https://cptest.vakifbank.com.tr/CommonPayment/api/VposTransaction',
                ],
            ],
        ],
        'denizbank' => [
            'name'  => 'DenizBank-InterPos',
            'class' => Mews\Pos\Gateways\InterPos::class,
            'urls'  => [
                'production'    => 'https://inter-vpos.com.tr/mpi/Default.aspx',
                'test'          => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
                'gateway'       => [
                    'production'    => 'https://inter-vpos.com.tr/mpi/Default.aspx',
                    'test'          => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
                ],
                'gateway_3d_host'       => [
                    'production'    => 'https://inter-vpos.com.tr/mpi/3DHost.aspx',
                    'test'          => 'https://test.inter-vpos.com.tr/mpi/3DHost.aspx',
                ],
            ],
        ],
        'kuveytpos' => [
            'name'  => 'kuveyt-pos',
            'class' => Mews\Pos\Gateways\KuveytPos::class,
            'urls'  => [
                'production'    => 'https://boa.kuveytturk.com.tr/sanalposservice/Home/ThreeDModelProvisionGate',
                'test'          => 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelProvisionGate',
                'gateway'       => [
                    'production'    => 'https://boa.kuveytturk.com.tr/sanalposservice/Home/ThreeDModelPayGate',
                    'test'          => 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelPayGate',
                ],
                'query'       => [
                    'production'    => 'https://boa.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc?wsdl',
                    'test'          => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc?wsdl',
                ],
            ],
        ],
    ],
];
