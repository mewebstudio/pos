# Türk bankaları için sanal pos paketi (PHP)

Bu paket ile amaçlanan; ortak bir arayüz sınıfı ile, tüm Türk banka sanal pos sistemlerinin kullanılabilmesidir.
EST altyapısı tam olarak test edilmiş ve kullanıma hazırdır.
Garanti Ödeme sistemi çalışmaktadır, fakat 3D ödeme kısmının üretim ortamında test edilmesi gerekiyor.
YapıKredi Posnet sistemi çalışmaktadır, fakat 3D ödeme kısmının üretim ortamında test edilmesi gerekiyor.

> EST altyapısında olan Akbank ve Ziraat bankası test edilmiştir.

### Özellikler
  - Standart E-Commerce modeliyle ödeme (model => regular)
  - 3D modeliyle ödeme (model => 3d)
  - 3D Pay modeliyle ödeme (model => 3d_pay)
  - Sipariş/Ödeme sorgulama (query)
  - Sipariş/Ödeme geçmişi sorgulama (history)
  - Sipariş/Para iadesi yapma (refund)
  - Sipariş iptal etme (cancel)

### Minimum Gereksinimler
  - PHP >= 7.1.3
  - ext-dom
  - ext-json
  - ext-openssl
  - ext-SimpleXML

### Kurulum
Test sunucunuz üzerinde;
```sh
$ mkdir pos-test && cd pos-test
$ composer require mews/pos
```

**config.php (Ayar dosyası)**
```php
<?php

require './vendor/autoload.php';

$host_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://$_SERVER[HTTP_HOST]";
$path = '/pos-test/';
$base_url = $host_url . $path;

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$ip = $request->getClientIp();

// API kullanıcı bilgileri
$account = [
    'bank'          => 'akbank',
    'model'         => 'regular',
    'client_id'     => 'XXXXXXXX',
    'username'      => 'XXXXXXXX',
    'password'      => 'XXXXXXXX',
    'env'           => 'test', // test veya production. test ise; API Test Url, production ise; API Production URL kullanılır.
];

// API kullanıcı hesabı ile paket bir değişkene aktarılıyor
try {
    $pos = new \Mews\Pos\Pos($account);
} catch (\Mews\Pos\Exceptions\BankNotFoundException $e) {
    var_dump($e->getCode(), $e->getMessage());
    exit();
} catch (\Mews\Pos\Exceptions\BankClassNullException $e) {
    var_dump($e->getCode(), $e->getMessage());
    exit();
}
```

**test.php (Test Dosyası)**
```php
<?php

require 'config.php';

// Sipariş bilgileri
$order = [
    'id'            => 'BENZERSIZ-SIPERIS-ID',
    'name'          => 'John Doe', // zorunlu değil
    'email'         => 'mail@customer.com', // zorunlu değil
    'user_id'       => '12', // zorunlu değil
    'amount'        => (double) 20, // Sipariş tutarı
    'installment'   => '0',
    'currency'      => 'TRY',
    'ip'            => $ip,
    'transaction'   => 'pay', // pay => Auth, pre PreAuth (Direkt satış için pay, ön provizyon için pre)
];

// Kredi kartı bilgieri
$card = new \Mews\Pos\Entity\Card\CreditCardPos('1111222233334444', '20', '01', '000');

// API kullanıcısı ile oluşturulan $pos değişkenine prepare metoduyla sipariş bilgileri gönderiliyor
$pos->prepare($order);

// Ödeme tamamlanıyor
$payment = $pos->payment($card);

// Ödeme başarılı mı?
$payment->isSuccess();
//veya
$pos->isSuccess();

// Ödeme başarısız mı?
$payment->isError();
//veya
$pos->isError();

// Sonuç çıktısı
var_dump($payment->response);

````

### Farklı Banka Sanal Poslarını Eklemek
Kendi projenizin dizinindeyken
```sh
$ cp ./vendor/mews/pos/config/pos.php ./pos_ayarlar.php
```
ya da;

Projenizde bir ayar dosyası oluşturup (pos_ayarlar.php gibi), paket içerisinde `./config/pos.php` dosyasının içeriğini buraya kopyalayın.

```php
<?php

return [
    // Para birimleri
    'currencies'    => [
        'TRY'       => 949,
        'USD'       => 840,
        'EUR'       => 978,
        'GBP'       => 826,
        'JPY'       => 392,
        'RUB'       => 643,
    ],

    // Banka sanal pos tanımlamaları
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

        // Yeni eklenen banka
        'isbank'    => [
            'name'  => 'İŞ BANKASI .A.S.',
            'class' => \Mews\Pos\EstPos::class, // Altyapı sınıfı
            'urls'  => [
                'production'    => 'xxxx', // API Url
                'test'          => 'xxxx', // API Test Url
                'gateway'       => [
                    'production'    => 'xxxx', // 3d Kapı Url
                    'test'          => 'xxxx', // 3d Test Kapı Url
                ],
            ]
        ],
    ]
];

```

Bundan sonra nesnemizi, yeni ayarlarımıza göre oluşturup kullanmamız gerekir. Örnek:
```php
$yeni_ayarlar = require './pos_ayarlar.php';
$pos = new \Mews\Pos\Pos($account, $yeni_ayarlar);
```

### Örnek Kodlar
`./pos/examples` dizini içerisinde.

### Yol Haritası
  - Dökümantasyon hazırlanacak
  - UnitTest yazılacak -> Bu hiçbir zaman olmayabilir, birisi el atarsa sevinirim :)

> Değerli yorum, öneri ve katkılarınızı bekliyorum.

License
----

MIT
