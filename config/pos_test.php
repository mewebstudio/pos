<?php

return [
    'banks' => [
        'akbank-pos'           => [
            'name'              => 'AKBANK T.A.S.',
            'class'             => Mews\Pos\Gateways\AkbankPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos',
                'gateway_3d'      => 'https://virtualpospaymentgatewaypre.akbank.com/securepay',
                'gateway_3d_host' => 'https://virtualpospaymentgatewaypre.akbank.com/payhosting',
            ],
        ],
        'param-pos'            => [
            'name'              => 'TURK Elektronik Para A.Ş',
            'class'             => Mews\Pos\Gateways\ParamPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
                // API URL for 3D host payment
                'payment_api_2'   => 'https://test-pos.param.com.tr/to.ws/Service_Odeme.asmx',
                'gateway_3d_host' => 'https://test-pos.param.com.tr/default.aspx',
            ],
        ],
        'payten_v3_hash'       => [
            'name'              => 'AKBANK T.A.S.',
            'class'             => Mews\Pos\Gateways\EstV3Pos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://entegrasyon.asseco-see.com.tr/fim/api',
                'gateway_3d'  => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
            ],
        ],
        'akbank'               => [
            'name'              => 'AKBANK T.A.S.',
            'class'             => Mews\Pos\Gateways\EstV3Pos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://entegrasyon.asseco-see.com.tr/fim/api',
                'gateway_3d'  => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
            ],
        ],
        'tosla'                => [
            'name'              => 'AkÖde A.Ş.',
            'class'             => Mews\Pos\Gateways\ToslaPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://prepentegrasyon.tosla.com/api/Payment',
                'gateway_3d'      => 'https://prepentegrasyon.tosla.com/api/Payment/ProcessCardForm',
                'gateway_3d_host' => 'https://prepentegrasyon.tosla.com/api/Payment/threeDSecure',
            ],
        ],
        'yapikredi'            => [
            'name'              => 'Yapıkredi',
            'class'             => Mews\Pos\Gateways\PosNet::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://setmpos.ykb.com/PosnetWebService/XML',
                'gateway_3d'  => 'https://setmpos.ykb.com/3DSWebService/YKBPaymentService',
            ],
        ],
        'albaraka'             => [
            'name'              => 'Albaraka',
            'class'             => Mews\Pos\Gateways\PosNetV1Pos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://epostest.albarakaturk.com.tr/ALBMerchantService/MerchantJSONAPI.svc',
                'gateway_3d'  => 'https://epostest.albarakaturk.com.tr/ALBSecurePaymentUI/SecureProcess/SecureVerification.aspx',
            ],
        ],
        'garanti'              => [
            'name'              => 'Garanti',
            'class'             => Mews\Pos\Gateways\GarantiPos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://sanalposprovtest.garantibbva.com.tr/VPServlet',
                'gateway_3d'  => 'https://sanalposprovtest.garantibbva.com.tr/servlet/gt3dengine',
            ],
        ],
        'qnbfinansbank-payfor' => [
            'name'              => 'QNBFinansbank-PayFor',
            'class'             => Mews\Pos\Gateways\PayForPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://vpostest.qnbfinansbank.com/Gateway/XMLGate.aspx',
                'gateway_3d'      => 'https://vpostest.qnbfinansbank.com/Gateway/Default.aspx',
                'gateway_3d_host' => 'https://vpostest.qnbfinansbank.com/Gateway/3DHost.aspx',
            ],
        ],
        'vakifbank'            => [
            'name'              => 'VakifBank-VPOS',
            'class'             => Mews\Pos\Gateways\PayFlexV4Pos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://onlineodemetest.vakifbank.com.tr:4443/VposService/v3/Vposreq.aspx',
                'gateway_3d'  => 'https://3dsecuretest.vakifbank.com.tr:4443/MPIAPI/MPI_Enrollment.aspx',
                'query_api'   => 'https://onlineodemetest.vakifbank.com.tr:4443/UIService/Search.aspx',
            ],
        ],
        'ziraat-vpos'          => [
            'name'              => 'Ziraat Bankası',
            'class'             => Mews\Pos\Gateways\PayFlexV4Pos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://preprod.payflex.com.tr/Ziraatbank/VposWeb/v3/Vposreq.aspx',
                'gateway_3d'  => 'https://preprod.payflex.com.tr/ZiraatBank/MpiWeb/MPI_Enrollment.aspx',
                'query_api'   => 'https://sanalpos.ziraatbank.com.tr/v4/UIWebService/Search.aspx',
            ],
        ],
        'vakifbank-cp'         => [
            'name'              => 'VakifBank-PayFlex-Common-Payment',
            'class'             => Mews\Pos\Gateways\PayFlexCPV4Pos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://cptest.vakifbank.com.tr/CommonPayment/api/VposTransaction',
                'gateway_3d'  => 'https://cptest.vakifbank.com.tr/CommonPayment/api/RegisterTransaction',
            ],
        ],
        'denizbank'            => [
            'name'              => 'DenizBank-InterPos',
            'class'             => Mews\Pos\Gateways\InterPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
                'gateway_3d'      => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
                'gateway_3d_host' => 'https://test.inter-vpos.com.tr/mpi/3DHost.aspx',
            ],
        ],
        'kuveytpos'            => [
            'name'              => 'kuveyt-pos',
            'class'             => Mews\Pos\Gateways\KuveytPos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home',
                'gateway_3d'  => 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelPayGate',
                'query_api'   => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc?wsdl',
            ],
        ],
        'vakif-katilim'        => [
            'name'              => 'Vakıf Katılım',
            'class'             => Mews\Pos\Gateways\VakifKatilimPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home',
                'gateway_3d'      => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelPayGate',
                'gateway_3d_host' => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/CommonPaymentPage/CommonPaymentPage',
            ],
        ],
    ],
];
