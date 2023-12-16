<?php
/**
 * NOT! Bu dosya örnek amaçlıdır. Canlı ortamda kopyasını oluşturup, kopyasını kullanınız!
 */
return [
    'banks' => [
        'akbankv3'             => [
            'name'  => 'AKBANK T.A.S.',
            'class' => Mews\Pos\Gateways\EstV3Pos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://www.sanalakpos.com/fim/api',
                'gateway_3d'      => 'https://www.sanalakpos.com/fim/est3Dgate',
                'gateway_3d_host' => 'https://sanalpos.sanalakpos.com.tr/fim/est3Dgate',
            ],
        ],
        'akbank'               => [
            'name'  => 'AKBANK T.A.S.',
            'class' => Mews\Pos\Gateways\EstPos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://www.sanalakpos.com/fim/api',
                'gateway_3d'      => 'https://www.sanalakpos.com/fim/est3Dgate',
                'gateway_3d_host' => 'https://sanalpos.sanalakpos.com.tr/fim/est3Dgate',
            ],
        ],
        'akode'               => [
            'name'  => 'AKBANK T.A.S.',
            'class' => Mews\Pos\Gateways\AkOdePos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://api.akodepos.com/api/Payment',
                'gateway_3d'      => 'https://api.akodepos.com/api/Payment/ProcessCardForm',
                'gateway_3d_host' => 'https://api.akodepos.com/api/Payment/threeDSecure',
            ],
        ],
        'finansbank'           => [
            'name'  => 'QNB Finansbank',
            'class' => Mews\Pos\Gateways\EstV3Pos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://www.fbwebpos.com/fim/api',
                'gateway_3d'      => 'https://www.fbwebpos.com/fim/est3dgate',
            ],
        ],
        'halkbank'             => [
            'name'  => 'Halkbank',
            'class' => Mews\Pos\Gateways\EstV3Pos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://sanalpos.halkbank.com.tr/fim/api',
                'gateway_3d'      => 'https://sanalpos.halkbank.com.tr/fim/est3dgate',
            ],
        ],
        'teb'                  => [
            'name'  => 'TEB',
            'class' => Mews\Pos\Gateways\EstV3Pos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://sanalpos.teb.com.tr/fim/api',
                'gateway_3d'      => 'https://sanalpos.teb.com.tr/fim/est3Dgate',
            ],
        ],
        'isbank'               => [
            'name'  => 'İşbank T.A.S.',
            'class' => Mews\Pos\Gateways\EstPos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://sanalpos.isbank.com.tr/fim/api',
                'gateway_3d'      => 'https://sanalpos.isbank.com.tr/fim/est3Dgate',
            ],
        ],
        'sekerbank'            => [
            'name'  => 'Şeker Bank',
            'class' => Mews\Pos\Gateways\EstV3Pos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://sanalpos.sekerbank.com.tr/fim/api',
                'gateway_3d'      => 'https://sanalpos.sekerbank.com.tr/fim/est3Dgate',
            ],
        ],
        'yapikredi'            => [
            'name'  => 'Yapıkredi',
            'class' => Mews\Pos\Gateways\PosNet::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://posnet.yapikredi.com.tr/PosnetWebService/XML',
                'gateway_3d'      => 'https://posnet.yapikredi.com.tr/3DSWebService/YKBPaymentService',
            ],
        ],
        'albaraka'             => [
            'name'  => 'Albaraka',
            'class' => Mews\Pos\Gateways\PosNetV1Pos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://epos.albarakaturk.com.tr/ALBMerchantService/MerchantJSONAPI.svc',
                'gateway_3d'      => 'https://epos.albarakaturk.com.tr/ALBSecurePaymentUI/SecureProcess/SecureVerification.aspx',
            ],
        ],
        'garanti'              => [
            'name'  => 'Garanti',
            'class' => Mews\Pos\Gateways\GarantiPos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://sanalposprov.garanti.com.tr/VPServlet',
                'gateway_3d'      => 'https://sanalposprov.garanti.com.tr/servlet/gt3dengine',
            ],
        ],
        'qnbfinansbank-payfor' => [
            'name'  => 'QNBFinansbank-PayFor',
            'class' => Mews\Pos\Gateways\PayForPos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://vpos.qnbfinansbank.com/Gateway/XMLGate.aspx',
                'gateway_3d'      => 'https://vpos.qnbfinansbank.com/Gateway/Default.aspx',
                'gateway_3d_host' => 'https://vpos.qnbfinansbank.com/Gateway/3DHost.aspx',
            ],
        ],
        'vakifbank'            => [
            'name'  => 'VakifBank-VPOS',
            'class' => Mews\Pos\Gateways\PayFlexV4Pos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://onlineodeme.vakifbank.com.tr:4443/VposService/v3/Vposreq.aspx',
                'gateway_3d'      => 'https://3dsecure.vakifbank.com.tr:4443/MPIAPI/MPI_Enrollment.aspx',
                'query_api'       => 'https://sanalpos.vakifbank.com.tr/v4/UIWebService/Search.aspx',
            ],
        ],
        'ziraat-vpos'          => [
            'name'  => 'Ziraat Bankası',
            'class' => Mews\Pos\Gateways\PayFlexV4Pos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://sanalpos.ziraatbank.com.tr/v4/v3/Vposreq.aspx',
                'gateway_3d'      => 'https://mpi.ziraatbank.com.tr/Enrollment.aspx',
                'query_api'       => 'https://sanalpos.ziraatbank.com.tr/v4/UIWebService/Search.aspx',
            ],
        ],
        'vakifbank-cp'         => [
            'name'  => 'VakifBank-PayFlex-Common-Payment',
            'class' => Mews\Pos\Gateways\PayFlexCPV4Pos::class,
            'gateway_endpoints'  => [
                'payment_api' => 'https://cpweb.vakifbank.com.tr/CommonPayment/api/RegisterTransaction',
                'gateway_3d'  => 'https://cpweb.vakifbank.com.tr/CommonPayment/SecurePayment',
                'query_api'   => 'https://cpweb.vakifbank.com.tr/CommonPayment/api/VposTransaction',
            ],
        ],
        'denizbank'            => [
            'name'  => 'DenizBank-InterPos',
            'class' => Mews\Pos\Gateways\InterPos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://inter-vpos.com.tr/mpi/Default.aspx',
                'gateway_3d'      => 'https://inter-vpos.com.tr/mpi/Default.aspx',
                'gateway_3d_host' => 'https://inter-vpos.com.tr/mpi/3DHost.aspx',
            ],
        ],
        'kuveytpos'            => [
            'name'  => 'kuveyt-pos',
            'class' => Mews\Pos\Gateways\KuveytPos::class,
            'gateway_endpoints'  => [
                'payment_api' => 'https://boa.kuveytturk.com.tr/sanalposservice/Home/ThreeDModelProvisionGate',
                'gateway_3d'  => 'https://boa.kuveytturk.com.tr/sanalposservice/Home/ThreeDModelPayGatex',
                'query_api'   => 'https://boa.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc?wsdl',
            ],
        ],
    ],
];
