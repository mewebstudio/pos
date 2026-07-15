<?php

/**
 * gateway_configs.test_mode ayarı:
 * - PayTrPos ve GarantiPos: bu ayar istek verisini aktif olarak değiştirir (bankaya test/prod göstergesi gönderir).
 * - Diğer tüm gateway'ler: test ve production ortamı ayrımı yalnızca aşağıdaki endpoint URL'leri ile yapılır;
 *   bu gateway'ler için test_mode ayarlamak bir etkisi olmaz ve logger'da uyarı mesajı oluşturur.
 */

return [
    'banks' => [
        'akbank-pos'            => [
            // AKBANK T.A.S.
            'class'             => \Mews\Pos\Gateway\AkbankPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos',
                'gateway_3d'      => 'https://virtualpospaymentgatewaypre.akbank.com/securepay',
                'gateway_3d_host' => 'https://virtualpospaymentgatewaypre.akbank.com/payhosting',
            ],
        ],
        'param-pos'             => [
            // TURK Elektronik Para A.Ş
            'class'             => \Mews\Pos\Gateway\ParamPos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
            ],
        ],
        'param-3d-host-pos'     => [
            // TURK Elektronik Para A.Ş
            'class'             => \Mews\Pos\Gateway\Param3DHostPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://test-pos.param.com.tr/to.ws/Service_Odeme.asmx',
                'gateway_3d_host' => 'https://test-pos.param.com.tr/default.aspx',
            ],
        ],
        'asseco'        => [
            // Asseco/Payten
            'class'             => \Mews\Pos\Gateway\AssecoPos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://entegrasyon.asseco-see.com.tr/fim/api',
                'gateway_3d'  => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
            ],
        ],
        'tosla'                 => [
            // AkÖde A.Ş.
            'class'             => \Mews\Pos\Gateway\ToslaPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://prepentegrasyon.tosla.com/api/Payment',
                'gateway_3d'      => 'https://prepentegrasyon.tosla.com/api/Payment/ProcessCardForm',
                'gateway_3d_host' => 'https://prepentegrasyon.tosla.com/api/Payment/threeDSecure',
            ],
        ],
        'paytr'                 => [
            // PayTR
            'class'             => \Mews\Pos\Gateway\PayTrPos::class,
            'gateway_configs'   => [
                'lang'      => \Mews\Pos\PosInterface::LANG_TR,
                'test_mode' => true,
            ],
            'gateway_endpoints' => [
                'payment_api'     => 'https://www.paytr.com',
                'gateway_3d'      => 'https://www.paytr.com/odeme',
                'gateway_3d_host' => 'https://www.paytr.com/odeme/guvenli',
            ],
        ],
        'yapikredi'             => [
            // Yapıkredi
            'class'             => \Mews\Pos\Gateway\PosNetPos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://setmpos.ykb.com/PosnetWebService/XML',
                'gateway_3d'  => 'https://setmpos.ykb.com/3DSWebService/YKBPaymentService',
            ],
        ],
        'albaraka'              => [
            // Albaraka
            'class'             => \Mews\Pos\Gateway\PosNetV1Pos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://epostest.albarakaturk.com.tr/ALBMerchantService/MerchantJSONAPI.svc',
                'gateway_3d'  => 'https://epostest.albarakaturk.com.tr/ALBSecurePaymentUI/SecureProcess/SecureVerification.aspx',
            ],
        ],
        'garanti'               => [
            // Garanti
            'class'             => \Mews\Pos\Gateway\GarantiPos::class,
            'gateway_configs'   => [
                'lang'      => \Mews\Pos\PosInterface::LANG_TR,
                // GarantiPos'u test ortamda test edebilmek için zorunlu.
                'test_mode' => true,
            ],
            'gateway_endpoints' => [
                'payment_api' => 'https://sanalposprovtest.garantibbva.com.tr/VPServlet',
                'gateway_3d'  => 'https://sanalposprovtest.garantibbva.com.tr/servlet/gt3dengine',
            ],
        ],
        'qnbfinansbank-payfor'  => [
            // QNBFinansbank-PayFor
            'class'             => \Mews\Pos\Gateway\PayForPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://vpostest.qnb.com.tr/Gateway/XMLGate.aspx',
                'gateway_3d'      => 'https://vpostest.qnb.com.tr/Gateway/Default.aspx',
                'gateway_3d_host' => 'https://vpostest.qnb.com.tr/Gateway/3DHost.aspx',
            ],
        ],
        'ziraat-katilim-payfor' => [
            // ZiraatKatilim-PayFor
            'class'             => \Mews\Pos\Gateway\PayForPos::class,
            'gateway_configs'   => [
                'lang'                  => \Mews\Pos\PosInterface::LANG_TR,
                // Ziraat Katilim için hash kontrolü çalışmıyor. O yüzden devre dışı bırakıyoruz.
                'disable_3d_hash_check' => true,
            ],
            'gateway_endpoints' => [
                'payment_api'     => 'https://payfortestziraatkatilim.cordisnetwork.com/Mpi/XMLGate.aspx',
                'gateway_3d'      => 'https://payfortestziraatkatilim.cordisnetwork.com/Mpi/Default.aspx',
                'gateway_3d_host' => 'https://payfortestziraatkatilim.cordisnetwork.com/Mpi/3DHost.aspx',
            ],
        ],
        'vakifbank'             => [
            // VakifBank-VPOS
            'class'             => \Mews\Pos\Gateway\PayFlexV4Pos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://onlineodemetest.vakifbank.com.tr:4443/VposService/v3/Vposreq.aspx',
                'gateway_3d'  => 'https://3dsecuretest.vakifbank.com.tr:4443/MPIAPI/MPI_Enrollment.aspx',
                'query_api'   => 'https://onlineodemetest.vakifbank.com.tr:4443/UIService/Search.aspx',
            ],
        ],
        'ziraat-vpos'           => [
            // Ziraat Bankası
            'class'             => \Mews\Pos\Gateway\PayFlexV4Pos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://preprod.payflex.com.tr/Ziraatbank/VposWeb/v3/Vposreq.aspx',
                'gateway_3d'  => 'https://preprod.payflex.com.tr/ZiraatBank/MpiWeb/MPI_Enrollment.aspx',
                'query_api'   => 'https://sanalpos.ziraatbank.com.tr/v4/UIWebService/Search.aspx',
            ],
        ],
        'vakifbank-cp'          => [
            // VakifBank-PayFlex-Common-Payment
            'class'             => \Mews\Pos\Gateway\PayFlexCPV4Pos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://cptest.vakifbank.com.tr/CommonPayment/api',
            ],
        ],
        'denizbank'             => [
            // DenizBank-InterPos
            'class'             => \Mews\Pos\Gateway\InterPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
                'gateway_3d'      => 'https://test.inter-vpos.com.tr/mpi/Default.aspx',
                'gateway_3d_host' => 'https://test.inter-vpos.com.tr/mpi/3DHost.aspx',
            ],
        ],
        'kuveytpos'             => [
            // kuveyt-pos
            'class'             => \Mews\Pos\Gateway\KuveytPos::class,
            'gateway_configs'   => [
                'lang' => \Mews\Pos\PosInterface::LANG_TR,
            ],
            'gateway_endpoints' => [
                'payment_api' => 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home',
                'query_api'   => 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic',
            ],
        ],
        'vakif-katilim'         => [
            // Vakıf Katılım
            'class'             => \Mews\Pos\Gateway\VakifKatilimPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home',
                'gateway_3d_host' => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/CommonPaymentPage/CommonPaymentPage',
            ],
        ],
        'iyzico'                => [
            // Iyzico
            'class'             => \Mews\Pos\Gateway\IyzicoPos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://sandbox-api.iyzipay.com',
                'query_api'   => 'https://sandbox-api.iyzipay.com/v2/reporting/payment',
            ],
        ],
    ],
];
