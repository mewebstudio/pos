# Türk bankaları için sanal pos paketi (PHP)

[![Version](https://poser.pugx.org/mews/pos/version)](https://packagist.org/packages/mews/pos)
[![Monthly Downloads](https://poser.pugx.org/mews/pos/d/monthly)](https://packagist.org/packages/mews/pos)
[![License](https://poser.pugx.org/mews/pos/license)](https://packagist.org/packages/mews/pos)
[![PHP Version Require](https://poser.pugx.org/mews/pos/require/php)](https://packagist.org/packages/mews/pos)

Bu paket ile amaçlanan; ortak bir arayüz sınıfı ile, tüm Türk banka sanal pos
sistemlerinin kullanılabilmesidir.

### Deskteklenen Payment Gateway'ler / Bankalar:

| Gateway                                                                                                                    | Desktekleyen<br/>bankalar                                                      | Desteklenen<br/>Ödeme Tipleri                                                                               | Desteklenen Sorgular                                                                                               |
|----------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------|
| Tosla<br><sup>(eski AKÖde)</sup>                                                                                           | ?                                                                              | NonSecure<br/>3DPay<br/>3DHost                                                                              | İptal<br/>İade<br/>Durum sorgulama<br/>Sipariş Tarihçesini sorgulama<br/>Özel Sorgu                                |
| ParamPos                                                                                                                   | ?                                                                              | NonSecure<br/>3DSecure<br/>3DPay<br/>3DHost <sup>(test edilmesi gerekiyor)</sup>                            | İptal<br/>İade<br/>Durum sorgulama<br/>Geçmiş İşlemleri sorgulama<br/>Özel Sorgu                                   |
| AkbankPos <br/><sup>(Akbankın yeni altyapısı)</sup>                                                                        | Akbank                                                                         | NonSecure<br/>3DSecur<br/>3DPay<br/>3DHost<br/>Tekrarlanan Ödeme                                            | İptal<br/>İade<br/>Sipariş Tarihçesini sorgulama<br/>Geçmiş İşlemleri sorgulama<br/>Özel Sorgu                     |
| EstV3Pos<br/><sup>(Asseco/Payten)</sup><br/><sup>Eski `EstPos` altyapının<br/>sha512 hash algoritmasıyla uygulaması.</sup> | Akbank<br/>TEB<br/>İşbank<br/>Şekerbank<br/>Halkbank<br/>Finansbank<br/>Ziraat | NonSecure<br/>3DSecure<br/>3DPay<br/>3DHost<br/>3DPayHost<br/>Tekrarlanan Ödeme                             | İptal<br/>İade<br/>Durum sorgulama<br/>Sipariş Tarihçesini sorgulama<br/>Özel Sorgu                                |
| PayFlex MPI VPOS V4                                                                                                        | Ziraat<br/>Vakıfbank VPOS 7/24<br/>İşbank                                      | NonSecure<br/>3DSecure<br/>Tekrarlanan Ödeme                                                                | İptal<br/>İade<br/>Durum sorgulama<br/>Özel Sorgu                                                                  |
| PayFlex<br/>Common Payment V4<br/><sup>(Ortak Ödeme)</sup>                                                                 | Ziraat<br/>Vakıfbank<br/>İşbank                                                | 3DPay<br/>3DHost                                                                                            | Özel Sorgu                                                                                                         |
| Garanti Virtual POS                                                                                                        | Garanti                                                                        | NonSecure<br/>3DSecure<br/>3DPay<br/>3DHost<br/>Tekrarlanan Ödeme                                           | İptal<br/>İade<br/>Durum sorgulama<br/>Sipariş Tarihçesini sorgulama<br/>Geçmiş İşlemleri sorgulama<br/>Özel Sorgu |
| PosNet                                                                                                                     | YapıKredi                                                                      | NonSecure<br/>3DSecure<br/>                                                                                 | İptal<br/>İade<br/>Durum sorgulama<br/>Özel Sorgu                                                                  |
| PosNetV1<br/><sup>(JSON API)</sup>                                                                                         | Albaraka Türk                                                                  | NonSecure<br/>3DSecure                                                                                      | İptal<br/>İade<br/>Durum sorgulama<br/>Özel Sorgu                                                                  |
| PayFor                                                                                                                     | Finansbank<br/>Enpara                                                          | NonSecure<br/>3DSecure<br/>3DPay<br/>3DHost                                                                 | İptal<br/>İade<br/>Durum sorgulama<br/>Sipariş Tarihçesini sorgulama<br/>Geçmiş İşlemleri sorgulama<br/>Özel Sorgu |
| InterPOS                                                                                                                   | Deniz bank                                                                     | NonSecure<br/>3DSecure<br/>3DPay<br/>3DHost                                                                 | İptal<br/>İade<br/>Durum sorgulama<br/>Özel Sorgu                                                                  |
| Kuveyt POS<br/><sub>TDV2.0.0</sub>                                                                                         | Kuveyt Türk                                                                    | NonSecure<br/>3DSecure                                                                                      | İptal<br/>İade<br/>Durum sorgulama<br/>(SOAP API)<br/>Özel Sorgu                                                   |
| VakifKatilimPos                                                                                                            | Vakıf Katılım                                                                  | NonSecure <sup>(test edilmesi gerekiyor)</sup><br/>3DSecure<br/>3DHost <sup>(test edilmesi gerekiyor)</sup> | İptal<br/>İade<br/>Durum sorgulama<br/>Sipariş Tarihçesini sorgulama<br/>Geçmiş İşlemleri sorgulama<br/>Özel Sorgu |

### Ana başlıklar

- [Özellikler](#ozellikler)
- [Changelog](./docs/CHANGELOG.md)
- [Minimum Gereksinimler](#minimum-gereksinimler)
- [Kurulum](#kurulum)
- [Farklı Banka Sanal Poslarını Eklemek](#farkli-banka-sanal-poslarini-eklemek)
- [Ornek Kodlar](#ornek-kodlar)
    - [3DSecure, 3DPay ve 3DHost Ödeme Örneği](./docs/THREED-PAYMENT-EXAMPLE.md)
    - [3DSecure, 3DPay ve 3DHost Modal Box ile Ödeme Örneği](./docs/THREED-SECURE-AND-PAY-PAYMENT-IN-MODALBOX-EXAMPLE.md)
    - [Non Secure Ödeme Örneği](./docs/NON-SECURE-PAYMENT-EXAMPLE.md)
    - [Ön otorizasyon ve Ön otorizasyon kapama](./docs/PRE-AUTH-POST-EXAMPLE.md)
    - [Ödeme İptal](./docs/CANCEL-EXAMPLE.md)
    - [Ödeme İade](./docs/REFUND-EXAMPLE.md)
    - [Ödeme Durum Sorgulama](./docs/STATUS-EXAMPLE.md)
    - [Özel Sorgular](./docs/CUSTOM-QUERY-EXAMPLE.md)

- [Popup Windowda veya Iframe icinde odeme yapma](#popup-windowda-veya-iframe-icinde-odeme-yapma)
- [Troubleshoots](#troubleshoots)
- [Genel Kültür](#genel-kultur)
- [Docker ile test ortamı](#docker-ile-test-ortami)

### Ozellikler

- Non Secure E-Commerce modeliyle ödeme (`PosInterface::MODEL_NON_SECURE`)
- 3D Secure modeliyle ödeme (`PosInterface::MODEL_3D_SECURE`)
- 3D Pay modeliyle ödeme (`PosInterface::MODEL_3D_PAY`)
- 3D Host modeliyle ödeme (`PosInterface::MODEL_3D_HOST`)
- Sipariş/Ödeme durum sorgulama (`PosInterface::TX_TYPE_STATUS`)
- Sipariş Tarihçesini sorgulama
  sorgulama (`PosInterface::TX_TYPE_ORDER_HISTORY`)
- Geçmiş işlemleri sorgulama (`PosInterface::TX_TYPE_HISTORY`)
- Sipariş/Para iadesi yapma (`PosInterface::TX_TYPE_REFUND`
  ve `PosInterface::TX_TYPE_PARTIAL_REFUND`)
- Sipariş iptal etme (`PosInterface::TX_TYPE_CANCEL`)
- Özel Sorgular (`PosInterface::TX_TYPE_CUSTOM_QUERY`)
- API istek verilerinin gateway API'na gönderilmeden önce değiştirebilme
- Farklı Para birimler ile ödeme desteği
- Tekrarlanan (Recurring) ödeme talimatları
- [PSR-3](https://www.php-fig.org/psr/psr-3/) logger desteği
- [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP Client desteği

#### Farkli Gateway'ler Tek islem akisi

* Bir (**3DSecure**, **3DPay**, **3DHost**, **NonSecure**) ödeme modelden
  diğerine geçiş çok az değişiklik gerektirir.
* Aynı tip işlem için farklı POS Gateway'lerden dönen değerler aynı formata
  normalize edilmiş durumda.
  Yani kod güncellemenize gerek yok.
* Aynı tip işlem için farklı Gateway'lere gönderilecek değerler de genel olarak
  aynı formatta olacak şekilde normalize edilmiştir.

### Minimum Gereksinimler

- PHP >= 7.4
- ext-dom
- ext-json
- ext-openssl
- ext-libxml
- ext-zlib
- ext-SimpleXML
- ext-soap (sadece KuveytPos için)
- [PSR-18](https://packagist.org/providers/psr/http-client-implementation): HTTP
  Client
- [PSR-14](https://packagist.org/providers/psr/event-dispatcher-implementation):
  Event Dispatcher

### Kurulum

#### Frameworks

- **Symfony** kurulum
  için [mews/pos-bundle](https://github.com/mewebstudio/PosBundle)
  kullanabilirsiniz.
- **Laravel** kurulum
  için [mews/laravel-pos](https://github.com/mewebstudio/laravel-pos)
  kullanabilirsiniz.

#### Basic kurulum

```sh
$ composer require symfony/event-dispatcher mews/pos
```

Kütüphane belli bir HTTP Client'ile zorunlu bağımlılığı yoktur.
PSR-18 HTTP Client standarta uyan herhangi bir kütüphane kullanılabilinir.
Projenizde zaten kurulu PSR-18 uygulaması varsa otomatik onu kullanır.

Veya hızlı başlangıç için:

```sh
$ composer require php-http/curl-client nyholm/psr7 symfony/event-dispatcher mews/pos
```

Diğer PSR-18 uygulamasını sağlayan
kütüphaneler: https://packagist.org/providers/psr/http-client-implementation

Sonra kendi projenizin dizinindeyken alttaki komutu çalıştırarak
ayarlar dosyasını projenize kopyalayınız.

```sh
$ cp ./vendor/mews/pos/config/pos_production.php ./pos_prod_ayarlar.php
```

Test ortamda geliştirecekseniz test ayarları da kopyalanız:

```sh
$ cp ./vendor/mews/pos/config/pos_test.php ./pos_test_ayarlar.php
```

Kopyaladıktan sonra ayarlardaki kullanmayacağınız banka ayarları silebilirsiniz.

Bundan sonra `Pos` nesnemizi, yeni ayarlarımıza göre oluşturup kullanmamız
gerekir.
Örnek:

```php
$yeniAyarlar = require __DIR__ . '/pos_prod_ayarlar.php';
// veya test ortamı için $yeniAyarlar = require __DIR__ . '/pos_test_ayarlar.php';

$pos = \Mews\Pos\Factory\PosFactory::createPosGateway($account, $yeniAyarlar, $eventDispatcher);
```

_Kütüphanede yer alan `pos_production.php` ve `pos_test.php` ayar dosyaları
projenizde direk kullanmayınız!
Yukarda belirtildiği gibi kopyalayarak kullanmanız tavsiye edilir._

### Farkli Banka Sanal Poslarini Eklemek

Projenize kopyaladığınız `./pos_prod_ayarlar.php` dosyasına farklı banka ayarı
eklemek için alttaki örneği kullanabilirsiniz.

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
        'isbank'    => [ // unique bir isim vermeniz gerekir.
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

## Ornek Kodlar

Örnekleri `/docs` ve `/examples` dizini içerisinde bulabilirsiniz.

3D ödeme örnek kodlar genel olarak kart bilgilerini website sunucusuna POST
eder (`index.php` => `form.php`),
ondan sonra da işlenip gateway'e yönlendiriliyor.
Bu şekilde farklı bankalar arası implementation degişmemesi sağlanmakta (ortak
kredi kart formu ve aynı işlem akışı).
Genel olarak kart bilgilerini, website sunucusuna POST yapmadan,
direk gateway'e yönlendirecek şekilde kullanılabilinir (genelde, banka örnek
kodları bu şekilde implement edilmiş).
Fakat

- birden fazla bank seçeneği olunca veya müşteri banka degiştirmek istediğinde
  kart bilgi formunu ona göre güncellemeniz gerekecek.
- üstelik YKB POSNet, Vakıf Katılım ve VakıfBank POS kart bilgilerini website
  sunucusu
  tarafından POST edilmesini gerektiriyor.

### Popup Windowda veya Iframe icinde odeme yapma

Müşteriyi banka sayfasına redirect etmeden **iframe** üzerinden veya **popup
window**
üzerinden ödeme akışı
[examples'da](./examples)
ve [/docs'da](./docs/THREED-SECURE-AND-PAY-PAYMENT-IN-MODALBOX-EXAMPLE.md) 3D
ödeme ile örnek PHP ve JS kodlar yer almaktadır.

#### Dikkat edilmesi gerekenler

- Popup window taraycı tarafından engellenebilir bu yüzden onun yerine
  modal box içinde iframe kullanılması tavsiye edilir.

## Troubleshoots

### Session sıfırlanması

Cookie session kullanığınızda, kullanıcı gatewayden geri websitenize
yönlendirilidiğinde session sıfırlanabilir.
Response'da `samesite` değeri set etmeniz
gerekiyor. [çözüm](https://stackoverflow.com/a/51128675/4896948).

### Shared hosting'lerde IP tanımsız hatası

- Shared hosting'lerde Cpanel'de gördüğünüz IP'den farklı olarak fiziksel
  sunucun bir tane daha IP'si olur.
  O IP adres Cpanel'de gözükmez, hosting firmanızdan sorup öğrenmeniz
  gerekmekte.
  Bu hatayı alırsanız hosting firmanın verdiği IP adrese'de banka gateway'i
  tarafından izin verilmesini sağlayın.
- kütüphane ortam değerini de kontrol etmeyi unutmayınız.
    - test ortamı için `$pos->setTestMode(true);`
    - canlı ortam için `$pos->setTestMode(false);` (default olarak `false`)

  _ortam değeri hem bankaya istek gönderirken hem de gelen isteği işlerken doğru
  deger olması gerekiyor._

### Debugging

Kütüphane [PSR-3](https://www.php-fig.org/psr/psr-3/) standarta uygun logger
uygulamayı destekler.
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

Ödeme modelleri hakkında bilgi edinmek
istiyorsanız [bu makaleyi](https://medium.com/p/fa5cd016999c)
inceleyebilirsiniz.

### Otorizasyon, Ön Otorizasyon, Ön Provizyon Kapama İşlemler arasındaki farklar

- **Otorizasyon** - bildiğimiz ve genel olarak kullandığımız işlem. Tek seferde
  ödeme işlemi biter.
  Bu işlem için kullanıcıdan hep kredi kart bilgisini _alınır_.
  İşlemin kütüphanedeki karşılığı `PosInterface::TX_TYPE_PAY_AUTH`
- **Ön Otorizasyon** - müşteriden parayı direk çekmek yerine, işlem sonucunda
  para bloke edilir.
  Bu işlem için kullanıcıdan hep kredi kart bilgisini _alınır_.
  İşlemin kütüphanedeki karşılığı `PosInterface::TX_TYPE_PAY_PRE_AUTH`
- **Ön Provizyon Kapama** - ön provizyon sonucunda bloke edilen miktarın
  çekimini gerçekleştirir.
  Ön otorizasyon yapıldıktan sonra, örneğin 1 hafta sonra, Post Otorizasyon
  isteği gönderilebilinir.
  Bu işlem için kullanıcıdan kredi kart bilgisi _alınmaz_.
  Onun yerine bazı gateway'ler `orderId` degeri istenir,
  bazıları ise ön provizyon sonucu dönen banka tarafındaki `orderId`'yi ister.
  Satıcı _ön otorizasyon_ isteği iptal etmek isterse de `cancel` isteği
  gönderir.
  Post Otorizasyon İşlemin kütüphanedeki
  karşılığı `PosInterface::TX_TYPE_PAY_POST_AUTH`.
  Bu işlem **sadece NonSecure** ödeme modeliyle gerçekleşir.
- `TX_TYPE_PAY_AUTH` vs `TX_TYPE_PAY_PRE_AUTH` işlemler genelde bütün ödeme
  modelleri
  (NonSecure, 3DSecure, 3DPay ve 3DHost) tarafından desteklenir.

### Refund ve Cancel işlemler arasındaki farklar

- **Refund** - Tamamlanan ödemeyi iade etmek için kullanılır.
  Bu işlem bazı gatewaylerde sadece gün kapandıktan _sonra_ yapılabilir.
  İade işlemi için _miktar zorunlu_, çünkü ödenen ve iade edilen miktarı aynı
  olmayabilir.
  İşlemin kütüphanedeki karşılığı `PosInterface::TX_TYPE_REFUND`
- **Cancel** - Tamamlanan ödemeyi iptal etmek için kullanılır.
  Ödeme yapıldıktan sonra gün kapanmadan yapılabilir. Gün kapandıktan
  sonra `refund` işlemi kullanmak zorundasınız.
  Genel olarak _miktar_ bilgisi _istenmez_, ancak bazı Gateway'ler ister.
  İşlemin kütüphanedeki karşılığı `PosInterface::TX_TYPE_CANCEL`

## Docker ile test ortami

1. Makinenizde Docker kurulu olması gerekir.
2. Projenin root klasöründe `docker-compose up -d` komutu çalıştırınız.
3. docker container'de `composer install` çalıştırınız.

**Note**: localhost port 80 boş olması gerekiyor.
Sorunsuz çalışması durumda kod örneklerine http://localhost/payten/3d/index.php
şekilde erişebilirsiniz.
http://localhost/ URL projenin `examples` klasörünün içine bakar.

### Unit testler çalıştırma

Projenin root klasoründe bu satırı çalıştırmanız gerekiyor

```sh
$ composer test
```

> Değerli yorum, öneri ve katkılarınızı
>
> Sorun bulursanız veya eklenmesi gereken POS sistemi varsa lütfen issue
> oluşturun.

License
----

MIT
