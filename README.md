# Türk bankaları için sanal pos paketi (PHP)

[![Version](http://poser.pugx.org/mews/pos/version)](https://packagist.org/packages/mews/pos)
[![Monthly Downloads](http://poser.pugx.org/mews/pos/d/monthly)](https://packagist.org/packages/mews/pos)
[![License](http://poser.pugx.org/mews/pos/license)](https://packagist.org/packages/mews/pos)
[![PHP Version Require](http://poser.pugx.org/mews/pos/require/php)](https://packagist.org/packages/mews/pos)


Bu paket ile amaçlanan; ortak bir arayüz sınıfı ile, tüm Türk banka sanal pos sistemlerinin kullanılabilmesidir.

### Deskteklenen Payment Gateway'ler / Bankalar:

- **AKÖde**

  Desktekleyen bankalar: Akbank

  Desteklenen özellikler:
    - NonSecure, 3DPay ve 3DHost ödeme
    - Ödeme İptal ve İade
    - Ödeme durum sorgulama
    - Tarihçe sorgulama


- **EST POS** (Asseco/Payten)

    Desktekleyen bankalar: Akbank, TEB, İşbank, Şekerbank, Halkbank ve Finansbank

    Desteklenen özellikler:
    - NonSecure, 3DSecure, 3DHost ve 3DPay ödeme
    - Ödeme İptal ve İade
    - Ödeme durum sorgulama
    - Tarihçe sorgulama


- **EST POS V3** EstPos altyapının daha güvenli (sha512) hash algoritmasıyla uygulaması.

   Desktekleyen bankalar: Akbank, TEB, İşbank, Şekerbank, Halkbank ve Finansbank.


- **PayFlex MPI VPOS V4**

  Desktekleyen bankalar: Ziraat, Vakıfbank ve İşbank.

  Desteklenen özellikler:
    - NonSecure, 3DSecure ödeme
    - Ödeme İptal ve İade
    - Ödeme durum sorgulama


- **PayFlex Common Payment V4 (Ortak Ödeme)**

  Desktekleyen bankalar: Ziraat, Vakıfbank ve İşbank.

  Desteklenen özellikler:
    - NonSecure, 3DHost ve 3DPay ödeme
    - Ödeme İptal ve İade


- **Garanti Virtual POS**

  Desteklenen özellikler:
    - NonSecure, 3DSecure, 3DHost ve 3DPay ödeme
    - Ödeme İptal ve İade
    - Ödeme durum sorgulama
    - Tarihçe sorgulama


- **PosNet**

  Desktekleyen bankalar: YapıKredi.

  Desteklenen özellikler:
    - NonSecure, 3DSecure ödeme
    - Ödeme İptal ve İade
    - Ödeme durum sorgulama


- **PosNetV1 (JSON API)**

  Desktekleyen bankalar: Albaraka Türk.

  Desteklenen özellikler:
    - NonSecure, 3DSecure ödeme
    - Ödeme İptal ve İade
    - Ödeme durum sorgulama


- **Finansbank PayFor** (Enpara dahil)

  Desteklenen özellikler:
    - NonSecure, 3DSecure, 3DHost ve 3DPay ödeme
    - Ödeme İptal ve İade
    - Ödeme durum sorgulama
    - Tarihçe sorgulama


- **InterPOS (Deniz bank)**

  Desteklenen özellikler:
    - NonSecure, 3DSecure, 3DHost ve 3DPay ödeme
    - Ödeme İptal ve İade
    - Ödeme durum sorgulama


- **Kuveyt POS**

  Desteklenen özellikler:
    - 3DSecure ödeme
    - Ödeme İptal ve İade (SOAP)
    - Ödeme durum sorgulama (SOAP)

### Ana başlıklar
- [Özellikler](#ozellikler)
- [Latest updates](#latest-updates)
- [Minimum Gereksinimler](#minimum-gereksinimler)
- [Kurulum](#kurulum)
- [Farklı Banka Sanal Poslarını Eklemek](#farkli-gatewayler-tek-islem-akisi)
- [Ornek Kodlar](#ornek-kodlar)
  - [3D Secure ve 3D Pay Ödeme Örneği](./docs/THREED-SECURE-AND-PAY-PAYMENT-EXAMPLE.md)
  - [3D Host Ödeme Örneği](./docs/THREED-HOST-PAYMENT-EXAMPLE.md)
  - [Non Secure Ödeme Örneği](./docs/NON-SECURE-PAYMENT-EXAMPLE.md)
  - [Ön otorizasyon ve Ön otorizasyon kapama](./docs/PRE-AUTH-POST-EXAMPLE.md)
  - [Ödeme İptal](./docs/CANCEL-EXAMPLE.md)
  - [Ödeme İade](./docs/REFUND-EXAMPLE.md)
  - [Ödeme Durum Sorgulama](./docs/STATUS-EXAMPLE.md)

- [Popup Windowda veya Iframe icinde odeme yapma](#popup-windowda-veya-iframe-icinde-odeme-yapma)
- [Troubleshoots](#troubleshoots)
- [Genel Kültür](#genel-kultur)
- [Docker ile test ortamı](#docker-ile-test-ortami)

### Ozellikler
  - Standart E-Commerce modeliyle ödeme (`PosInterface::MODEL_NON_SECURE`)
  - 3D Secure modeliyle ödeme (`PosInterface::MODEL_3D_SECURE`)
  - 3D Pay modeliyle ödeme (`PosInterface::MODEL_3D_PAY`)
  - 3D Host modeliyle ödeme (`PosInterface::MODEL_3D_HOST`)
  - Sipariş/Ödeme durum sorgulama (`PosInterface::TX_TYPE_STATUS`)
  - Sipariş/Ödeme geçmişi sorgulama (`PosInterface::TX_TYPE_HISTORY`)
  - Sipariş/Para iadesi yapma (`PosInterface::TX_TYPE_REFUND`)
  - Sipariş iptal etme (`PosInterface::TX_TYPE_CANCEL`)
  - Farklı Para birimler ile ödeme desteği
  - Tekrarlanan (Recurring) ödeme talimatları
  - [PSR-3](https://www.php-fig.org/psr/psr-3/) logger desteği
  - [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP Client desteği

#### Farkli Gateway'ler Tek islem akisi
* Farklı bankaya geçiş yapmak için sadece doğru `AccountFactory` method'u kullanarak account degistirmek yeterli.
* **3D**, **3DPay**, **3DHost** ödemeler arasında geçiş yapmak için tek yapmanız gereken
Account konfigurasyonunda account tipini değiştirmek (`PosInterface::MODEL_3D_PAY` vs.).
İşlem akışı aynı olduğu için kod değiştirmenize gerek kalmıyor.
* Aynı tip işlem için farklı POS Gateway'lerden dönen değerler aynı formata normalize edilmiş durumda.
Yani kod güncellemenize gerek yok.
* Aynı tip işlem için farklı Gateway'lere gönderilecek değerler de genel olarak
aynı formatta olacak şekilde normalize edilmiştir.

### Latest updates

Son yapılan değişiklikler için [`CHANGELOG`](./docs/CHANGELOG.md).

### Minimum Gereksinimler
  - PHP >= 7.4
  - ext-dom
  - ext-json
  - ext-openssl
  - ext-SimpleXML
  - ext-soap (sadece KuveytPos için)
  - PSR-18 HTTP Client

### Kurulum
```sh
$ composer require mews/pos
```
Kütüphane belli bir HTTP Client'ile zorunlu bağımlılığı yoktur.
PSR-18 HTTP Client standarta uyan herhangi bir kütüphane kullanılabilinir.
Projenizde zaten kurulu PSR-18 uygulaması varsa otomatik onu kullanır.

Veya hızlı başlangıç için:
```sh
$ composer require php-http/curl-client nyholm/psr7 mews/pos
```
Diğer PSR-18 uygulamasını sağlayan kütühaneler: https://packagist.org/providers/psr/http-client-implementation

### Farkli Banka Sanal Poslarını Eklemek
Kendi projenizin dizinindeyken
```sh
$ cp ./vendor/mews/pos/config/pos_production.php ./pos_ayarlar.php
```
ya da;

Projenizde bir ayar dosyası oluşturup (`pos_ayarlar.php` gibi),
paket içerisinde `./config/pos_production.php` dosyasının içeriğini buraya kopyalayın.

```php
<?php

return [
    // Banka sanal pos tanımlamaları
    'banks'         => [
        'akbank'    => [
            'name'  => 'AKBANK T.A.S.',
            'class' => Mews\Pos\Gateways\EstV3Pos::class,
            'gateway_endpoints'  => [
                'payment_api'     => 'https://www.sanalakpos.com/fim/api',
                'gateway_3d'      => 'https://www.sanalakpos.com/fim/est3Dgate',
                'gateway_3d_host' => 'https://sanalpos.sanalakpos.com.tr/fim/est3Dgate',
            ],
        ],

        // Yeni eklenen banka
        'isbank'    => [
            'name'  => 'İŞ BANKASI .A.S.',
            'class' => \Mews\Pos\Gateways\EstV3Pos::class, // Altyapı sınıfı
            'gateway_endpoints'  => [
                'payment_api'     => 'https://sanalpos.isbank.com.tr/fim/api',
                'gateway_3d'      => 'https://sanalpos.isbank.com.tr/fim/est3Dgate',
            ],
        ],
    ]
];

```

Bundan sonra nesnemizi, yeni ayarlarımıza göre oluşturup kullanmamız gerekir. Örnek:
```php
//yeni ayar yolu ya da degeri
$yeniAyarlar = require './pos_ayarlar.php';
$pos = \Mews\Pos\Factory\PosFactory::createPosGateway($account, $yeniAyarlar);
```

## Ornek Kodlar
`/examples` dizini içerisinde.

3D ödeme örnek kodlar genel olarak kart bilgilerini website sunucusuna POST eder (`index.php` => `form.php`),
ondan sonra da işlenip gateway'e yönlendiriliyor.
Bu şekilde farklı bankalar arası implementation degişmemesi sağlanmakta (ortak kredi kart formu ve aynı işlem akışı).
Genel olarak kart bilgilerini, website sunucusuna POST yapmadan,
direk gateway'e yönlendirecek şekilde kullanılabilinir (genelde, banka örnek kodları bu şekilde implement edilmiş).
Fakat,
- birden fazla bank seçenegi olunca veya müşteri banka degiştirmek istediginde kart bilgi formunu ona göre güncellemeniz gerekecek.
- üstelik YKB POSNet ve VakıfBank POS kart bilgilerini website sunucusu tarafından POST edilmesini gerektiriyor.

### Popup Windowda veya Iframe icinde odeme yapma
Redirection yapmadan iframe üzerinden veya Popup window içinde ödeme akışı
`/examples/` içinde 3D ödeme ile örnek PHP ve JS kodlar yer almaktadır.
Özellikle şu alttaki dosyalarda:
- [_redirect_iframe_or_popup_window_form.php](examples%2F_templates%2F_redirect_iframe_or_popup_window_form.php) -
  bu dosyanin içerigi Popup Window'da veya iframe içinde yüklenir
  ve JS ile içindeki form bankanın 3D gatewayine gönderilermek üzere otomatik olarak submit edilir.
- [form.php](examples%2F_common-codes%2F3d%2Fform.php) - kullanıcıdan
   kredi kart bilgileri ve ödeme akış tercihi (iframe, popup window) alındıktan sonra
   bu dosyada tercih edilen odeme akışa göre
  - redirekt yapılır
  - popup window açılır
  - bootstrap modal box içinde iframe açılır
- [_payment_response.php](examples%2F_templates%2F_payment_response.php) -
  banktan dönüşde bu dosyadaki kodlar çalışır. Eğer _iframe/popup window_
  üzerinden ödeme yapılıyorsa bu dosyanın içeriği de *iframe/popup window*da çalışır ve
  JS ile current window'un _iframe_'de mı veya _popup window_'da mı oldugunu kontrol eder.
  Popup window'da ve iframe'de ise _parent_ window'a (yani `form.php`'ye)
  `postMessage` API ile banktan dönen cevabı gönderir.
  `form.php` postMessage API'dan gelen mesaji işler ve kullanıcıya gösterir.

#### Dikkkat edilmesi gerekenler
- Popup window taraycı tarafından engellenebilir bu yüzden onun yerine
  modal box içinde iframe kullanılması tavsiye edilir.

## Troubleshoots
### Session sıfırlanması
Cookie session kullanığınızda, kullanıcı gatewayden geri websitenize yönlendirilidiğinde session sıfırlanabilir.
Response'da `samesite` değeri set etmeniz gerekiyor. [çözüm](https://stackoverflow.com/a/51128675/4896948).
### Shared hosting'lerde IP tanımsız hatası
- Shared hosting'lerde Cpanel'de gördüğünüz IP'den farklı olarak fiziksel sunucun bir tane daha IP'si olur.
O IP adres Cpanel'de gözükmez, hosting firmanızdan sorup öğrenmeniz gerekmekte.
Bu hatayı alırsanız hosting firmanın verdiği IP adrese'de banka gateway'i tarafından izin verilmesini sağlayın.
- kutuphane ortam degerini de kontrol etmeyi unutmayiniz, ortama gore bankanin URL'leri degisir.
  - test ortam icin `$pos->setTestMode(true);`
  - canli ortam icin `$pos->setTestMode(false);` (default olarak `false`)

  _ortam degeri hem bankaya istek gonderirken hem de gelen istegi islerken dogru deger olmasi gerekiyor._

### Debugging
Kütühane [PSR-3](https://www.php-fig.org/psr/psr-3/) standarta uygun logger uygulamayı destekler.
Örnekler: https://packagist.org/providers/psr/log-implementation .

Monolog logger kullanım örnegi:
```shell
composer require monolog/monolog
```
```php
$handler = new \Monolog\Handler\StreamHandler(__DIR__.'/../var/log/pos.log', \Psr\Log\LogLevel::DEBUG);
$logger = new \Monolog\Logger('pos', [$handler]);
$pos = \Mews\Pos\Factory\PosFactory::createPosGateway(
    $account,
    $config,
    $eventDispatcher,
    null,
    $logger
);
```

## Genel Kultur
### NonSecure, 3D Secure, 3DPay ve 3DHost ödeme modeller arasındaki farklar
- **3D** - Bankaya göre farklı isimler verilebilir, örn. 3D Full. Gateway'den (3D şifre girdiginiz sayfadan) döndükten sonra ödemeyi tamamlamak için banka gateway'ne 1 istek daha (_provizyon_ isteği) gönderir.
Bu isteği göndermeden ödeme tamamlanmaz.
- **3DPay** - Bankaya göre farklı isimler verilebilir, örn. 3D Half. Gateway'den (3D şifre girdiginiz sayfadan) döndükten sonra ödeme bitmiş sayılır. 3D ödemede yapıldığı gibi ekstra provizyon istek gönderilmez.
- **3DHost** - Kredi kart girişi için kullanıcı bankanın sayfasına yönledirilir, kredi kart bilgileri girdikten sonra bankanın 3D gateway sayfasına yönlendirilir, ordan da websitenize geri yönlendirilir. Yönlendirme sonucunda ödeme tamanlanmış olur.
- **NonSecure** - Ödeme işlemi kullanıcı 3D onay işlemi yapmadan gerçekleşir.
- **NonSecure, 3D ve 3DPay** - Ödemede kredi kart bilgisi websiteniz tarafından alınır. **3DHost** ödemede ise banka websayfasından alınır.

### Otorizasyon, Ön Otorizasyon, Ön Provizyon Kapama İşlemler arasındaki farklar
- **Otorizasyon** - bildiğimiz ve genel olarak kullandığımız işlem. Tek seferde ödeme işlemi biter.
Bu işlem için kullanıcıdan hep kredi kart bilgisini _alınır_.
İşlemin kütüphanedeki karşılığı `PosInterface::TX_TYPE_PAY_AUTH`
- **Ön Otorizasyon** - müşteriden parayı direk çekmek yerine, işlem sonucunda para bloke edilir.
Bu işlem için kullanıcıdan hep kredi kart bilgisini _alınır_.
İşlemin kütüphanedeki karşılığı `PosInterface::TX_TYPE_PAY_PRE_AUTH`
- **Ön Provizyon Kapama** - ön provizyon sonucunda bloke edilen miktarın satışını tamamlar.
Ön otorizasyon yapıldıktan sonra, örneğin 1 hafta sonra, Post Otorizasyon isteği gönderilebilinir.
Bu işlem için kullanıcıdan kredi kart bilgisi _alınmaz_.
Onun yerine bazı gateway'ler `orderId` degeri isteri, bazıları ise ön provizyon sonucu dönen banka tarafındaki `orderId`'yi ister.
Satıcı _ön otorizasyon_ isteği iptal etmek isterse de `cancel` isteği gönderir.
Post Otorizasyon İşlemin kütüphanedeki karşılığı `PosInterface::TX_TYPE_PAY_POST_AUTH`
- Bu 3 çeşit işlemler bütün ödeme modelleri (NonSecure, 3D, 3DPay ve 3DHost) tarafından desteklenir.

### Refund ve Cancel işlemler arasındaki farklar
- **Refund** - Tamamlanan ödemeyi iade etmek için kullanılır.
Bu işlem gün kapandıktan _sonra_ yapılabilir.
İade işlemi için _miktar zorunlu_, çünkü ödenen ve iade edilen miktarı aynı olmayabilir.
İşlemin kütüphanedeki karşılığı `PosInterface::TX_TYPE_REFUND`
- **Cancel** - Tamamlanan ödemeyi iptal etmek için kullanılır.
Ödeme yapıldıktan sonra gün kapanmadan yapılabilir. Gün kapandıktan sonra `refund` işlemi kullanmak zorundasınız.
Genel olarak _miktar_ bilgisi _istenmez_, ancak bazı Gateway'ler ister.
İşlemin kütüphanedeki karşılığı `PosInterface::TX_TYPE_CANCEL`

## Docker ile test ortami
1. Makinenizde Docker kurulu olmasi gerekiyor.
2. Projenin root klasöründe `docker-compose up -d` komutu çalıştırınız.
3. ```sh
    $ composer require php-http/curl-client nyholm/psr7 mews/pos
    ```
**Note**: localhost port 80 boş olması gerekiyor.
Sorunsuz çalışması durumda kod örneklerine http://localhost/akbank/3d/index.php şekilde erişebilirsiniz.
http://localhost/ URL projenin `examples` klasörünün içine bakar.

### Unit testler çalıştırma
Projenin root klasoründe bu satırı çalıştırmanız gerekiyor
```sh
$ ./vendor/bin/phpunit
```


> Değerli yorum, öneri ve katkılarınızı
>
> Sorun bulursanız veya eklenmesi gereken POS sistemi varsa lütfen issue oluşturun.

License
----

MIT
