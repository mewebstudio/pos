<?php

return [
    'banks' => [
        'akbankv3'             => [
            'name'  => 'AKBANK T.A.S.',
            'class' => Mews\Pos\Gateways\EstV3Pos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://entegrasyon.asseco-see.com.tr/fim/api',
                'gateway_3d'      => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
            ],
        ],
        'akbank'               => [
            'name'  => 'AKBANK T.A.S.',
            'class' => Mews\Pos\Gateways\EstPos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://entegrasyon.asseco-see.com.tr/fim/api',
                'gateway_3d'      => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
            ],
        ],
        'akode'               => [
            'name'  => 'AKBANK T.A.S.',
            'class' => Mews\Pos\Gateways\AkOdePos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://ent.akodepos.com/api/Payment',
                'gateway_3d'      => 'https://ent.akodepos.com/api/Payment/ProcessCardForm',
                'gateway_3d_host' => 'https://ent.akodepos.com/api/Payment/threeDSecure',
            ],
        ],
        'yapikredi'            => [
            'name'  => 'Yapıkredi',
            'class' => Mews\Pos\Gateways\PosNet::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://setmpos.ykb.com/PosnetWebService/XML',
                'gateway_3d'      => 'https://setmpos.ykb.com/3DSWebService/YKBPaymentService',
            ],
        ],
        'albaraka'             => [
            'name'  => 'Albaraka',
            'class' => Mews\Pos\Gateways\PosNetV1Pos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://epostest.albarakaturk.com.tr/ALBMerchantService/MerchantJSONAPI.svc',
                'gateway_3d'      => 'https://epostest.albarakaturk.com.tr/ALBSecurePaymentUI/SecureProcess/SecureVerification.aspx',
            ],
        ],
        'garanti'              => [
            'name'  => 'Garanti',
            'class' => Mews\Pos\Gateways\GarantiPos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://sanalposprovtest.garantibbva.com.tr/VPServlet',
                'gateway_3d'      => 'https://sanalposprovtest.garantibbva.com.tr/servlet/gt3dengine',
            ],
        ],
        'qnbfinansbank-payfor' => [
            'name'  => 'QNBFinansbank-PayFor',
            'class' => Mews\Pos\Gateways\PayForPos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://vpostest.qnbfinansbank.com/Gateway/XMLGate.aspx',
                'gateway_3d'      => 'https://vpostest.qnbfinansbank.com/Gateway/Default.aspx',
                'gateway_3d_host' => 'https://vpostest.qnbfinansbank.com/Gateway/3DHost.aspx',
            ],
        ],
        'vakifbank'            => [
            'name'  => 'VakifBank-VPOS',
            'class' => Mews\Pos\Gateways\PayFlexV4Pos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://onlineodemetest.vakifbank.com.tr:4443/VposService/v3/Vposreq.aspx',
                'gateway_3d'      => 'https://3dsecuretest.vakifbank.com.tr:4443/MPIAPI/MPI_Enrollment.aspxs',
                'query_api'       => 'https://sanalpos.vakifbank.com.tr/v4/UIWebService/Search.aspx', // todo update with the correct one
            ],
        ],
        'ziraat-vpos'          => [
            'name'  => 'Ziraat Bankası',
            'class' => Mews\Pos\Gateways\PayFlexV4Pos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://preprod.payflex.com.tr/Ziraatbank/VposWeb/v3/Vposreq.aspx',
                'gateway_3d'      => 'https://preprod.payflex.com.tr/ZiraatBank/MpiWeb/MPI_Enrollment.aspx',
                'query_api'       => 'https://sanalpos.ziraatbank.com.tr/v4/UIWebService/Search.aspx',
            ],
        ],
        'vakifbank-cp'         => [
            'name'  => 'VakifBank-PayFlex-Common-Payment',
            'class' => Mews\Pos\Gateways\PayFlexCPV4Pos::class,
            'gateway_endpoints'  => [
                'payment_api' => 'https://cptest.vakifbank.com.tr/CommonPayment/api/RegisterTransaction',
                'gateway_3d'  => 'https://cptest.vakifbank.com.tr/CommonPayment/api/VposTransaction',
                'query_api'   => 'https://cptest.vakifbank.com.tr/CommonPayment/SecurePayment',
            ],
        ],
        'denizbank'            => [
            'name'  => 'DenizBank-InterPos',
            'class' => Mews\Pos\Gateways\InterPos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
                'gateway_3d'      => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
                'gateway_3d_host' => 'https://test.inter-vpos.com.tr/mpi/3DHost.aspx',
            ],
        ],
        'kuveytpos'            => [
            'name'  => 'kuveyt-pos',
            'class' => Mews\Pos\Gateways\KuveytPos::class,
            'gateway_endpoints'  => [
                'payment_api' => 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelProvisionGate',
                'gateway_3d'  => 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelPayGate',
                'query_api'   => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc?wsdl',
            ],
        ],
    ],
];
