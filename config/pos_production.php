<?php

/**
 * NOT! Bu dosya örnek amaçlıdır. Canlı ortamda kopyasını oluşturup, kopyasını kullanınız!
 */
return [
    'banks' => [
        'akbank-pos'           => [
            'name'              => 'AKBANK T.A.S.',
            'class'             => Mews\Pos\Gateways\AkbankPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://api.akbank.com/api/v1/payment/virtualpos',
                'gateway_3d'      => 'https://virtualpospaymentgateway.akbank.com/securepay',
                'gateway_3d_host' => 'https://virtualpospaymentgateway.akbank.com/payhosting',
            ],
        ],
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
            'class' => Mews\Pos\Gateways\EstV3Pos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://www.sanalakpos.com/fim/api',
                'gateway_3d'      => 'https://www.sanalakpos.com/fim/est3Dgate',
                'gateway_3d_host' => 'https://sanalpos.sanalakpos.com.tr/fim/est3Dgate',
            ],
        ],
        'tosla'               => [
            'name'  => 'AkÖde A.Ş.',
            'class' => Mews\Pos\Gateways\ToslaPos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://entegrasyon.tosla.com/api/Payment',
                'gateway_3d'      => 'https://entegrasyon.tosla.com/api/Payment/ProcessCardForm',
                'gateway_3d_host' => 'https://entegrasyon.tosla.com/api/Payment/threeDSecure',
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
            'class' => Mews\Pos\Gateways\EstV3Pos::class,
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
                'query_api'       => 'https://onlineodeme.vakifbank.com.tr:4443/UIService/Search.aspx',
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
        'ziraat-estpos'          => [
            'name'  => 'Ziraat Bankası Payten',
            'class' => Mews\Pos\Gateways\EstV3Pos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://sanalpos2.ziraatbank.com.tr/fim/api',
                'gateway_3d'      => 'https://sanalpos2.ziraatbank.com.tr/fim/est3Dgate',
            ],
        ],
        'vakifbank-cp'         => [
            'name'  => 'VakifBank-PayFlex-Common-Payment',
            'class' => Mews\Pos\Gateways\PayFlexCPV4Pos::class,
            'gateway_endpoints'  => [
                'payment_api' => 'https://cpweb.vakifbank.com.tr/CommonPayment/api/VposTransaction',
                'gateway_3d'  => 'https://cpweb.vakifbank.com.tr/CommonPayment/api/RegisterTransaction',
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
                'payment_api' => 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/Home',
                'gateway_3d'  => 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/Home/ThreeDModelPayGate',
                'query_api'   => 'https://boa.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc?wsdl',
            ],
        ],
        'vakif-katilim' => [
            'name'              => 'Vakıf Katılım',
            'class'             => Mews\Pos\Gateways\VakifKatilimPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home',
                'gateway_3d'      => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelPayGate',
                'gateway_3d_host' => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/CommonPaymentPage/CommonPaymentPage',
            ],
        ],
        'param-pos'            => [
            'name'              => 'TURK Elektronik Para A.Ş',
            'class'             => Mews\Pos\Gateways\ParamPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://posws.param.com.tr/turkpos.ws/service_turkpos_prod.asmx',
                // API URL for 3D host payment
                'payment_api_2'   => 'https://pos.param.com.tr/Tahsilat/to.ws/Service_Odeme.asmx',
                'gateway_3d_host' => 'https://pos.param.com.tr/Tahsilat/Default.aspx',
            ],
        ],
    ],
];
