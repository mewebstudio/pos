# Türk bankaları için sanal pos paketi (PHP)

Bu paket ile amaçlanan; ortak bir arayüz sınıfı ile, tüm Türk banka sanal pos sistemlerinin kullanılabilmesidir.

EST altyapısı tam olarak test edilmiş ve kullanıma hazırdır. Garanti Ödeme sistemi çalışmaktadır, fakat 3D ödeme kısmının üretim ortamında test edilmesi gerekiyor.

YapıKredi Posnet sistemi 3D ödeme çalışmaktadır, fakat `cancel`, `refund` işlemleri test edilmedi. 

Finansbank'ın PayFor sanal pos sistemini desteklemektedir, Finansbank'ın IP kısıtlaması olmadığı için localhost'ta test `examples` klasöründeki örnek kodları çalıştırabilirsiniz.

VakifBank GET 7/24 MPI ve VPOS 7/24 eklendi ama test ortami olmadigi icin test edilemedi, gelen geri donuslere gore hatalar giderilecek. 
> EST altyapısında olan Akbank, TEB ve Ziraat bankası test edilmiştir.

### Özellikler
  - Standart E-Commerce modeliyle ödeme (model => regular)
  - 3D modeliyle ödeme (model => 3d)
  - 3D Pay modeliyle ödeme (model => 3d_pay)
  - Sipariş/Ödeme sorgulama (status)
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

### Unit testler çalıştırma
Projenin root klasoründe bu satırı çalıştırmanız gerekiyor
```sh
$ ./vendor/bin/phpunit tests
```


**config.php (Ayar dosyası)**
```php
<?php

require './vendor/autoload.php';

$host_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://$_SERVER[HTTP_HOST]";
$path = '/';
$base_url = $host_url . $path;

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$ip = $request->getClientIp();

// API kullanıcı bilgileri
$account = \Mews\Pos\Factory\AccountFactory::createEstPosAccount('akbank', 'XXXXXXX', 'XXXXXXX', 'XXXXXXX', '3d', 'XXXXXXX', \Mews\Pos\Gateways\EstPos::LANG_TR);

// API kullanıcı hesabı ile paket bir değişkene aktarılıyor
try {
    $pos = \Mews\Pos\Factory\PosFactory::createPosGateway($account);
    //değere göre API URL'leri test veya production değerler kullanılır.
    $pos->setTestMode(true);
} catch (\Mews\Pos\Exceptions\BankNotFoundException $e) {
    dump($e->getCode(), $e->getMessage());
    exit();
} catch (\Mews\Pos\Exceptions\BankClassNullException $e) {
    dump($e->getCode(), $e->getMessage());
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
    'amount'        => (float) 20, // Sipariş tutarı
    'installment'   => '0',
    'currency'      => 'TRY',
    'ip'            => $ip,
];

// Kredi kartı bilgieri
$card = new \Mews\Pos\Entity\Card\CreditCardEstPos('1111222233334444', '20', '01', '000');

// API kullanıcısı ile oluşturulan $pos değişkenine prepare metoduyla sipariş bilgileri gönderiliyor
$pos->prepare($order, \Mews\Pos\Gateways\AbstractGateway::TX_PAY);

// Ödeme tamamlanıyor, $card zorunlu değil.
$pos->payment($card);

// Ödeme başarılı mı?
$pos->isSuccess();

// Ödeme başarısız mı?
$pos->isError();

// Sonuç çıktısı
dump($pos->getResponse());

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
    
    //param birimleri Gateway'ler icinde tanımlıdır, özel bir mapping istemediğiniz sürece boş bırakınız
    'currencies'    => [
//        'TRY'       => 949,
//        'USD'       => 840,
    ],

    // Banka sanal pos tanımlamaları
    'banks'         => [
        'akbank'    => [
            'name'  => 'AKBANK T.A.S.',
            'class' => \Mews\Pos\Gateways\EstPos::class,
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
            'class' => \Mews\Pos\Gateways\EstPos::class, // Altyapı sınıfı
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
//yeni ayar yolu ya da degeri
$yeni_ayarlar = require './pos_ayarlar.php';
$pos = \Mews\Pos\Factory\PosFactory::createPosGateway($account, $yeni_ayarlar);
```

### Örnek Kodlar
`./pos/examples` dizini içerisinde.

### Docker ile test ortamı
Makinenizde Docker kurulu olmasi gerekiyor. 
Projenin root klasöründe `docker-compose up` komutu çalıştırmanız yeterli.
**Note**: localhost port 80 boş olması gerekiyor. 
Sorunsuz çalışması durumda kod örneklerine http://localhost/akbank/3d/index.php şekilde erişebilirsiniz.
http://localhost/ URL projenin `examples` klasörünün içine bakar.

### Yol Haritası
  - Dökümantasyon hazırlanacak

> Değerli yorum, öneri ve katkılarınızı 
> 
> Sorun bulursanız veya eklenmesi gereken POS sistemi varsa lütfen issue oluşturun.

License
----

MIT
