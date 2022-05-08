# Türk bankaları için sanal pos paketi (PHP)

Bu paket ile amaçlanan; ortak bir arayüz sınıfı ile, tüm Türk banka sanal pos sistemlerinin kullanılabilmesidir.

- **EST POS** (Asseco) altyapısı tam olarak test edilmiş ve kullanıma hazırdır. Akbank, TEB ve Ziraat bankası test edilmiştir.

- **Garanti Virtual POS** ödeme sistemi çalışmaktadır, fakat 3D ödeme kısmının üretim ortamında test edilmesi gerekiyor.

- **YapıKredi PosNet** sistemi 3D ödeme çalışmaktadır, fakat `cancel`, `refund` işlemleri test edilmedi. 

- **Finansbank PayFor** sanal pos sistemini desteklemektedir, Finansbank'ın IP kısıtlaması olmadığı için localhost'ta test `examples` klasöründeki örnek kodları çalıştırabilirsiniz.

- **VakifBank GET 7/24 MPI ve VPOS 7/24** 3D Secure ödemesi çalışır durumda, diğer işlemlerde sorunlar ortaya çıktıkça giderilecek.

- **InterPOS (Deniz bank)** destegi eklenmistir, test edildikce, sorunlari bulundukca hatalar giderilecek.

- **Kuveyt POS** 3d secure ödeme desteği eklenmiştir, test edildikçe, sorunları bulundukça hatalar giderilecek.

### Ana başlıklar
- [Özellikler](#zellikler)
- [Latest updates](#latest-updates)
- [Minimum Gereksinimler](#minimum-gereksinimler)
- [Kurulum](#kurulum)
- [Farklı Banka Sanal Poslarını Eklemek](#farkl-gatewayler-tek-ilem-ak)
- [Örnek Kodlar](#rnek-kodlar)
- [Troubleshoots](#troubleshoots)
- [Genel Kültür](#genel-kltr)
- [Docker ile test ortamı](#docker-ile-test-ortam)

### Özellikler
  - Standart E-Commerce modeliyle ödeme (`AbstractGateway::MODEL_NON_SECURE`)
  - 3D Secure modeliyle ödeme (`AbstractGateway::MODEL_3D_SECURE`)
  - 3D Pay modeliyle ödeme (`AbstractGateway::MODEL_3D_PAY`)
  - 3D Host modeliyle ödeme (`AbstractGateway::MODEL_3D_HOST`)
  - Sipariş/Ödeme sorgulama (`AbstractGateway::TX_STATUS`)
  - Sipariş/Ödeme geçmişi sorgulama (`AbstractGateway::TX_HISTORY`)
  - Sipariş/Para iadesi yapma (`AbstractGateway::TX_REFUND`)
  - Sipariş iptal etme (`AbstractGateway::TX_CANCEL`)

#### Farklı Gateway'ler Tek işlem akışı
* Farklı bankaya geçiş yapmak için sadece doğru `AccountFactory` method'u kullanarak account degistirmek yeterli.
* **3D**, **3DPay**, **3DHost** ödemeler arasında geçiş yapmak için tek yapmanız gereken Account konfigurasyonunda account tipini değiştirmek (`AbstractGateway::MODEL_3D_PAY` vs.). İşlem akışı aynı olduğu için kod değiştirmenize gerek kalmıyor.
* Aynı tip işlem için farklı POS Gateway'lerden dönen değerler aynı formata normalize edilmiş durumda. Yani kod güncellemenize gerek yok.
* Aynı tip işlem için farklı Gateway gönderilecek değerler de genel olarak aynı formatta olacak şekilde normalize edişmiştir. 

### Latest updates

Son yapılan değişiklikler için [`CHANGELOG`](./docs/CHANGELOG.md).

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

// API kullanıcı bilgileri
$account = \Mews\Pos\Factory\AccountFactory::createEstPosAccount(
'akbank', //pos config'deki ayarın index name'i
'yourClientID', 
'yourKullaniciAdi',
'yourSifre',
AbstractGateway::MODEL_3D_SECURE, //storetype
'yourStoreKey',
AbstractGateway::LANG_TR
);

// API kullanıcı hesabı ile paket bir değişkene aktarılıyor
try {
    $pos = \Mews\Pos\Factory\PosFactory::createPosGateway($account);
    //değere göre API URL'leri test veya production değerler kullanılır.
    $pos->setTestMode(true);
} catch (\Mews\Pos\Exceptions\BankNotFoundException | \Mews\Pos\Exceptions\BankClassNullException $e) {
    dd($e));
}
```
 
**form.php (kullanıcıdan kredi kart bilgileri alındıktan sonra)**
```php
<?php

require 'config.php';

// Sipariş bilgileri
$order = [
    'id'          => 'BENZERSIZ-SIPERIS-ID',
    'amount'      => 1.01,
    'currency'    => 'TRY', //TRY|USD|EUR, optional. default: TRY
    'installment' => 0, //0 ya da 1'den büyük değer, optional. default: 0

    //MODEL_3D_SECURE, MODEL_3D_PAY, MODEL_3D_HOST odemeler icin zorunlu
    //Success ve Fail URL'ler farklı olabilir ama kütüphane success ve fail için aynı kod çalıştırır.
    'success_url' => 'https://example.com/response.php',
    'fail_url'    => 'https://example.com/response.php',

    //gateway'e gore zorunlu olan degerler
    'ip'          => $ip, //EstPos, Garanti, KuveytPos, VakifBank
    'email'       => 'mail@customer.com', // EstPos, Garanti, KuveytPos, VakifBank
    'name'        => 'John Doe', // EstPos, Garanti
    'user_id'     => 'Müşteri ID', // EstPos
    'rand'        => md5(uniqid(time())), // EstPos, Garanti, PayFor, InterPos, VakifBank. Rastegele değer.
    
    //lang degeri verilmezse account (EstPosAccount) dili kullanılacak
    'lang' => AbstractGateway::LANG_TR, //LANG_TR|LANG_EN. Kullanıcının yönlendirileceği banka gateway sayfasının ve gateway'den dönen mesajların dili.
];
$session->set('order', $order);
    
// Kredi kartı bilgieri
$card = \Mews\Pos\Factory\CreditCardFactory::create(
    $pos,
    '4444555566667777',
    '25',
    '12',
    '123',
    'john',
    AbstractCreditCard::CARD_TYPE_VISA, //bankaya göre zorunlu
  );

// API kullanıcısı ile oluşturulan $pos değişkenine prepare metoduyla sipariş bilgileri tanımlanıyor.
$pos->prepare($order, \Mews\Pos\Gateways\AbstractGateway::TX_PAY, $card);

try {
    // $formData icerigi form olarak banka gateway'ne yonlendirilir.
    // /examples/template/_redirect_form.php bakınız.
    $formData = $pos->get3DFormData();
} catch (\Throwable $e) {
    dd($e);
}
````
**response.php (gateway'den döndükten sonra)**
```php
<?php

require 'config.php';

$order = $session->get('order');

$pos->prepare($order, \Mews\Pos\Gateways\AbstractGateway::TX_PAY);

// Ödeme tamamlanıyor,
// Ödeme modeli (3D Secure, 3D Pay, 3D Host, Non Secure) $account tarafında belirlenir.
// $card değeri Non Secure modelde ve Vakıfbank için 3DPay ve 3DSecure ödemede zorunlu.
$pos->payment($card);

// Ödeme başarılı mı?
$pos->isSuccess();

// Sonuç çıktısı
dump($pos->getResponse());
//response içeriği için /examples/template/_payment_response.php dosyaya bakınız.
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

## Örnek Kodlar
`/examples` dizini içerisinde.

3D ödeme örnek kodlar genel olarak kart bilgilerini website sunucusuna POST eder (`index.php` => `form.php`),
ondan sonra da işlenip gateway'e yönlendiriliyor.
Bu şekilde farklı bankalar arası implementation degişmemesi sağlanmakta (ortak kredi kart formu ve aynı işlem akışı).
Genel olarak kart bilgilerini, website sunucusuna POST yapmadan,
direk gateway'e yönlendirecek şekilde kullanılabilinir (genelde, banka örnek kodları bu şekilde implement edilmiş).
Fakat,
- birden fazla bank seçenegi olunca veya müşteri banka degiştirmek istediginde kart bilgi formunu ona göre güncellemeniz gerekecek.
- üstelik YKB POSNet ve VakıfBank POS kart bilgilerini website sunucusu tarafından POST edilmesini gerektiriyor.


## Troubleshoots
### Session sıfırlanması
Cookie session kullanığınızda, kullanıcı gatewayden geri websitenize yönlendirilidiğinde session'nin sıfırlanabilir.
Bu durumda PHP header'de `samesite=None` set etmeyi deneyin. Bu header'ı set ettikten sonra,
taraycıda yeni session oluşturun, çünkü bu değişiklik var olan session'i etkilemez.
###Shared hosting'lerde IP tanımsız hatası
Shared hosting'lerde Cpanel'de gördüğünüz IP'den farklı olarak fiziksel sunucun bir tane daha IP'si olur.
O IP adres Cpanel'de gözükmez, hosting firmanızdan sorup öğrenmeniz gerekmekte.
Bu hatayı alırsanız hosting firmanın verdiği IP adrese'de banka gateway'i tarafından izin verilmesini sağlayın.

## Genel Kültür
### NonSecure, 3D Secure, 3DPay ve 3DHost ödeme modeller arasındaki farklar
- **3D** - Bankaya göre farklı isimler verilebilir, örn. 3D Full. Gateway'den (3D şifre girdiginiz sayfadan) döndükten sonra ödemeyi tamamlamak için banka gateway'ne 1 istek daha (_provizyon_ isteği) gönderir.
Bu isteği göndermeden ödeme tamamlanmaz.
- **3DPay** - Bankaya göre farklı isimler verilebilir, örn. 3D Half. Gateway'den (3D şifre girdiginiz sayfadan) döndükten sonra ödeme bitmiş sayılır. 3D ödeme yapıldığı gibi ekstra provizyon istek gönderilmez.
- **3DHost** - Kredi kart girişi için kullanıcı bankanın sayfasına yönledirilir, kredi kart bilgileri girdikten sonra bankanın 3D gateway sayfasına yönlendirilir, ordan da websitenize geri yönlendirilir. Yönlendirme sonucunda ödeme tamanlanmış olur. 
- **NonSecure** - Ödeme işlemi kullanıcı 3D onay işlemi yapmadan gerçekleşir.
- **NonSecure, 3D ve 3DPay** - Ödemede kredi kart bilgisi websiteniz tarafından alınır. **3DHost** ödemede ise banka websayfasından alınır.

### Otorizasyon, Ön Otorizasyon, Ön Provizyon Kapama İşlemler arasındaki farklar
- **Otorizasyon** - bildiğimiz ve genel olarak kullandığımız işlem. Tek seferde ödeme işlemi biter.
Bu işlem için kullanıcıdan hep kredi kart bilgisini _alınır_.
İşlemin kütüphanedeli karşılığı `AbstractGateway::TX_PAY`
- **Ön Otorizasyon** - müşteriden parayı direk çekmek yerine, işlem sonucunda para bloke edilir.
Bu işlem için kullanıcıdan hep kredi kart bilgisini _alınır_.
İşlemin kütüphanedeli karşılığı `AbstractGateway::TX_PRE_PAY`
- **Ön Provizyon Kapama** - ön provizyon sonucunda bloke edilen miktarın satışını tamamlar.
Ön otorizasyon yapıldıktan sonra, örneğin 1 hafta sonra, Post Otorizasyon isteği gönderilebilinir.
Bu işlem için kullanıcıdan kredi kart bilgisi _alınmaz_.
Onun yerine bazı gateway'ler `orderId` degeri isteri, bazıları ise ön provizyon sonucu ödenen banka tarafındaki `orderId`'yi ister.
Satıcı _ön otorizasyon_ isteği iptal etmek isterse de `cancel` (Post Otorizasyon) isteği gönderir.
Post Otorizasyon İşlemin kütüphanedeli karşılığı `AbstractGateway::TX_POST_PAY`
- Bu 3 çeşit işlemler bütün ödeme modelleri (NonSecure, 3D, 3DPay ve 3DHost) tarafından desteklenir.

### Refund ve Cancel işlemler arasındaki farklar
- **Refund** - Tamamlanan ödemeyi iade etmek için kullanılır.
Bu işlemi yapabilmek için ödeme yapıldıktan belli bir süre _sonra_ (örn. 12 saat) yapılabilir.
İade işlemi için _miktar zorunlu_, çünkü ödenen ve iade edilen miktarı aynı olmayabilir.
İşlemin kütüphanedeli karşılığı `AbstractGateway::TX_REFUND`
- **Cancel** - Tamamlanan ödemeyi iptal etmek için kullanılır.
Belli bir süre _içinde_ (örn. 12 saat)  yapılması gerekir. Gateway'ler tarafında değişen bu süreyi geçerse `refund` işlemi kullanmak zorundasınız.
Genel olarak _miktar_ bilgisi _istenmez_, ancak bazı Gateway'ler ister.
İşlemin kütüphanedeli karşılığı `AbstractGateway::TX_CANCEL`

## Docker ile test ortamı
Makinenizde Docker kurulu olmasi gerekiyor.
Projenin root klasöründe `docker-compose up` komutu çalıştırmanız yeterli.
**Note**: localhost port 80 boş olması gerekiyor.
Sorunsuz çalışması durumda kod örneklerine http://localhost/akbank/3d/index.php şekilde erişebilirsiniz.
http://localhost/ URL projenin `examples` klasörünün içine bakar.

## Yol Haritası
  - Dökümantasyon hazırlanacak

> Değerli yorum, öneri ve katkılarınızı 
> 
> Sorun bulursanız veya eklenmesi gereken POS sistemi varsa lütfen issue oluşturun.

License
----

MIT
