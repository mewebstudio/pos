# Türk bankaları için sanal pos paketi (PHP)

[![Version](https://poser.pugx.org/mews/pos/version)](https://packagist.org/packages/mews/pos)
[![Monthly Downloads](https://poser.pugx.org/mews/pos/d/monthly)](https://packagist.org/packages/mews/pos)
[![License](https://poser.pugx.org/mews/pos/license)](https://packagist.org/packages/mews/pos)
[![PHP Version Require](https://poser.pugx.org/mews/pos/require/php)](https://packagist.org/packages/mews/pos)

Bu paket ile amaçlanan; ortak bir arayüz sınıfı ile, tüm Türk banka sanal pos
sistemlerinin kullanılabilmesidir.

### Desteklenen Payment Gateway'ler / Bankalar:

| Gateway                                                    | Desktekleyen<br/>bankalar                                                      | Desteklenen<br/>Ödeme Tipleri                                                                               | Desteklenen Sorgular                                                                                                        |
|------------------------------------------------------------|--------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------|
| Tosla<br><sup>(eski AKÖde)</sup>                           | ?                                                                              | NonSecure<br/>3DPay<br/>3DHost                                                                              | İptal<br/>İade<br/>Durum sorgulama<br/>Sipariş Tarihçesini sorgulama<br/>Özel Sorgu<br/>Taksit Oranları<br/>Taksit Fiyatları |
| IyzicoPos                                                  | Iyzico                                                                         | NonSecure<br/>3DSecure<br/>3DHost                                                                           | İptal<br/>İade (v2 API)<br/>Durum sorgulama<br/>Sipariş Tarihçesini sorgulama<br/>Geçmiş İşlemleri sorgulama<br/>Özel Sorgu<br/>Taksit Fiyatları<br/>BIN Sorgulama |
| PayTrPos                                                   | PayTr                                                                          | NonSecure<br/>3DPay<sup>(Direkt API)</sup><br/>3DHost<sup>(iFrame API)</sup>                                | İade<br/>Durum sorgulama<br/>Geçmiş İşlemleri sorgulama<br/>Özel Sorgu<br/>Taksit Oranları<br/>BIN Sorgulama                |
| ParamPos                                                   | ?                                                                              | NonSecure<br/>3DSecure<br/>3DPay<br/><sup>(test edilmesi gerekiyor)</sup>                                   | İptal<br/>İade<br/>Durum sorgulama<br/>Geçmiş İşlemleri sorgulama<br/>Özel Sorgu<br/>Taksit Oranları<br/>BIN Sorgulama      |
| Param3DHostPos                                             | ?                                                                              | 3DHost<br/><sup>(test edilmesi gerekiyor)</sup>                                                             |                                                                                                                             |
| AkbankPos <br/><sup>(Akbank'ın yeni altyapısı)</sup>       | Akbank                                                                         | NonSecure<br/>3DSecure<br/>3DPay<br/>3DHost<br/>Tekrarlanan Ödeme                                            | İptal<br/>İade<br/>Sipariş Tarihçesini sorgulama<br/>Geçmiş İşlemleri sorgulama<br/>Özel Sorgu                              |
| AssecoPos<br/><sup>(Asseco/Payten)</sup><br/>eski EstV3Pos | Akbank<br/>TEB<br/>İşbank<br/>Şekerbank<br/>Halkbank<br/>Finansbank<br/>Ziraat | NonSecure<br/>3DSecure<br/>3DPay<br/>3DHost<br/>3DPayHost<br/>Tekrarlanan Ödeme                             | İptal<br/>İade<br/>Durum sorgulama<br/>Sipariş Tarihçesini sorgulama<br/>Özel Sorgu                                         |
| PayFlex MPI VPOS V4                                        | Ziraat<br/>Vakıfbank VPOS 7/24<br/>İşbank                                      | NonSecure<br/>3DSecure<br/>Tekrarlanan Ödeme                                                                | İptal<br/>İade<br/>Durum sorgulama<br/>Özel Sorgu                                                                           |
| PayFlex<br/>Common Payment V4<br/><sup>(Ortak Ödeme)</sup> | Ziraat<br/>Vakıfbank<br/>İşbank                                                | 3DPay<br/>3DHost                                                                                            | Özel Sorgu                                                                                                                  |
| Garanti Virtual POS                                        | Garanti                                                                        | NonSecure<br/>3DSecure<br/>3DPay<br/>3DHost<br/>Tekrarlanan Ödeme                                           | İptal<br/>İade<br/>Durum sorgulama<br/>Sipariş Tarihçesini sorgulama<br/>Geçmiş İşlemleri sorgulama<br/>Özel Sorgu<br/>BIN Sorgulama |
| PosNet                                                     | YapıKredi                                                                      | NonSecure<br/>3DSecure<br/>                                                                                 | İptal<br/>İade<br/>Durum sorgulama<br/>Özel Sorgu                                                                           |
| PosNetV1<br/><sup>(JSON API)</sup>                         | Albaraka Türk                                                                  | NonSecure<br/>3DSecure                                                                                      | İptal<br/>İade<br/>Durum sorgulama<br/>Özel Sorgu                                                                           |
| PayFor                                                     | Finansbank<br/>Enpara<br>Ziraat Katılım                                        | NonSecure<br/>3DSecure<br/>3DPay<br/>3DHost                                                                 | İptal<br/>İade<br/>Durum sorgulama<br/>Sipariş Tarihçesini sorgulama<br/>Geçmiş İşlemleri sorgulama<br/>Özel Sorgu          |
| InterPOS                                                   | Deniz bank                                                                     | NonSecure<br/>3DSecure<br/>3DPay<br/>3DHost                                                                 | İptal<br/>İade<br/>Durum sorgulama<br/>Özel Sorgu                                                                           |
| Kuveyt POS<br/><sub>TDV2.0.0</sub>                         | Kuveyt Türk                                                                    | NonSecure<br/>3DSecure                                                                                      |                                                                                                                             |
| Kuveyt POS<br/><sub>TDV2.0.0</sub><br/><sub>SOAP API</sub> | Kuveyt Türk                                                                    |                                                                                                             | İptal<br/>İade<br/>Durum sorgulama<br/>Özel Sorgu                                                                           |
| VakifKatilimPos                                            | Vakıf Katılım                                                                  | NonSecure <sup>(test edilmesi gerekiyor)</sup><br/>3DSecure<br/>3DHost <sup>(test edilmesi gerekiyor)</sup> | İptal<br/>İade<br/>Durum sorgulama<br/>Sipariş Tarihçesini sorgulama<br/>Geçmiş İşlemleri sorgulama<br/>Özel Sorgu          |

### Ana başlıklar

- [Özellikler](#özellikler)
- [Changelog](./docs/CHANGELOG.md)
- [v1'den v2'ye Geçiş Kılavuzu](./docs/UPGRADE-v2.md)
- [Minimum Gereksinimler](#minimum-gereksinimler)
- [Kurulum](#kurulum)
- [Farklı Banka Sanal Poslarını Eklemek](#farklı-banka-sanal-poslarını-eklemek)
- [Örnek Kodlar](#örnek-kodlar)
    - [3DSecure, 3DPay ve 3DHost Ödeme Örneği](./docs/THREED-PAYMENT-EXAMPLE.md)
    - [PayTR 3DPay ve 3DHost Ödeme Örneği](./docs/THREED-PAYMENT-PAYTR_EXAMPLE.md)
    - [3DSecure, 3DPay ve 3DHost Modal Box ile Ödeme Örneği](docs/THREED-PAYMENT-IN-IFRAME-OR-POPUP-EXAMPLE.md)
    - [QR Code ile Ödeme Örneği](./docs/QR-CODE-PAYMENT-EXAMPLE.md)
    - [Non Secure Ödeme Örneği](./docs/NON-SECURE-PAYMENT-EXAMPLE.md)
    - [Ön otorizasyon ve Ön otorizasyon kapama](./docs/PRE-AUTH-POST-EXAMPLE.md)
    - [Ödeme İptal](./docs/CANCEL-EXAMPLE.md)
    - [Ödeme İade](./docs/REFUND-EXAMPLE.md)
    - [Ödeme Durum Sorgulama](./docs/STATUS-EXAMPLE.md)
    - [Tarihçe Sorgulama](./docs/HISTORY-EXAMPLE.md)
    - [Özel Sorgular](./docs/CUSTOM-QUERY-EXAMPLE.md)
    - [Taksit Oranları ve Fiyatları Sorgulama](./docs/INSTALLMENT-RATES-PRICES-EXAMPLE.md)
    - [BIN Sorgulama](./docs/BIN-LIST-EXAMPLE.md)

- [Popup Window'da veya iframe içinde ödeme yapma](#popup-windowda-veya-iframe-içinde-ödeme-yapma)
- [Troubleshoots](#troubleshoots)
- [Genel Kültür](#genel-kültür)
- [Docker ile Örnek Kodların Denenmesi](#docker-ile-örnek-kodların-denenmesi)

### Özellikler

- Non Secure E-Commerce modeliyle ödeme (`PosInterface::MODEL_NON_SECURE`)
- 3D Secure modeliyle ödeme (`PosInterface::MODEL_3D_SECURE`)
- 3D Pay modeliyle ödeme (`PosInterface::MODEL_3D_PAY`)
- 3D Host modeliyle ödeme (`PosInterface::MODEL_3D_HOST`)
- Sipariş/Ödeme durum sorgulama (`PosInterface::TX_TYPE_STATUS`)
- Sipariş Tarihçesini sorgulama (`PosInterface::TX_TYPE_ORDER_HISTORY`)
- Sipariş/Para iadesi yapma (`PosInterface::TX_TYPE_REFUND`
  ve `PosInterface::TX_TYPE_PARTIAL_REFUND`)
- Sipariş iptal etme (`PosInterface::TX_TYPE_CANCEL`)
- Geçmiş işlemleri sorgulama (`PosQueryInterface::TX_TYPE_HISTORY`)
- Özel Sorgular (`PosQueryInterface::TX_TYPE_CUSTOM_QUERY`)
- Taksit oranları sorgulama (`PosQueryInterface::TX_TYPE_INSTALLMENT_RATES`)
- Taksit fiyatları hesaplama (`PosQueryInterface::TX_TYPE_INSTALLMENT_PRICES`)
- BIN sorgulama (`PosQueryInterface::QUERY_TYPE_BIN_LIST`)
- API istek verilerinin Listener'lerle gateway API'na gönderilmeden önce değiştirebilme
- Farklı Para birimler ile ödeme desteği
- Tekrarlanan (Recurring) ödeme talimatları
- [PSR-3](https://www.php-fig.org/psr/psr-3/) logger desteği
- [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP Client desteği

#### Farklı Gateway'ler Tek İşlem Akışı

* Bir (**3DSecure**, **3DPay**, **3DHost**, **NonSecure**) ödeme modelden
  diğerine geçiş çok az değişiklik gerektirir.
* Aynı tip işlem için farklı POS Gateway'lerden dönen değerler aynı formata
  normalize edilmiş durumda.
  Yani kod güncellemenize gerek yok.
* Aynı tip işlem için farklı Gateway'lere gönderilecek değerler de genel olarak
  aynı formatta olacak şekilde normalize edilmiştir.

### Minimum Gereksinimler

- PHP >= 8.0
- ext-dom
- ext-json
- ext-openssl
- ext-libxml
- ext-zlib
- ext-SimpleXML
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
PSR-18 HTTP Client standardına uyan herhangi bir kütüphane kullanılabilir.
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

Test ortamda geliştirecekseniz test ayarları da kopyalayınız:

```sh
$ cp ./vendor/mews/pos/config/pos_test.php ./pos_test_ayarlar.php
```

Kopyaladıktan sonra ayarlardaki kullanmayacağınız banka ayarları silebilirsiniz.

Bundan sonra `Pos` nesnemizi, yeni ayarlarımıza göre oluşturup kullanmamız
gerekir.
Örnek:

```php
// 1. Banka hesabı oluşturunuz (her banka için farklı bir factory metodu vardır)
$account = \Mews\Pos\Factory\AccountFactory::createAkbankPosAccount(
    'akbank-pos',
    'MERCHANT_SAFE_ID',
    'TERMINAL_SAFE_ID',
    'SECRET_KEY'
);

// 2. Event dispatcher oluşturunuz (symfony/event-dispatcher önerilir)
// Elinizde farklı PSR-14 uygumlu EventDispatcher var ise kullanabilirsiniz.
$eventDispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();

// 3. Ayarları yükleyip ilgili bankanın dilimini alınız
$yeniAyarlar = require __DIR__ . '/pos_prod_ayarlar.php';
// veya test ortamı için $yeniAyarlar = require __DIR__ . '/pos_test_ayarlar.php';
$bankAyarlari = $yeniAyarlar['banks'][$account->getBankName()];

// 4. Gateway nesnesini oluşturunuz
$pos = \Mews\Pos\Factory\PosFactory::create($account, $bankAyarlari, $eventDispatcher);
```

_Kütüphanede yer alan `pos_production.php` ve `pos_test.php` ayar dosyaları
projenizde direk kullanmayınız!
Yukarda belirtildiği gibi kopyalayarak kullanmanız tavsiye edilir._

### Farklı Banka Sanal Poslarını Eklemek

Projenize kopyaladığınız `./pos_prod_ayarlar.php` dosyasına farklı banka ayarı
eklemek için alttaki örneği kullanabilirsiniz.

```php
<?php

return [
    // Banka sanal pos tanımlamaları
    'banks'         => [
        'akbank'    => [
            // AKBANK T.A.S.
            'class' => \Mews\Pos\Gateway\AssecoPos::class,
            'lang'  => \Mews\Pos\PosInterface::LANG_TR, // optional
            'gateway_endpoints'  => [
                'payment_api'     => 'https://www.sanalakpos.com/fim/api',
                'gateway_3d'      => 'https://www.sanalakpos.com/fim/est3Dgate',
                'gateway_3d_host' => 'https://sanalpos.sanalakpos.com.tr/fim/est3Dgate',
            ],
        ],

        // Yeni eklenen banka
        'isbank'    => [ // unique bir isim vermeniz gerekir.
            // İŞ BANKASI .A.S.
            'class' => \Mews\Pos\Gateway\AssecoPos::class, // Altyapı sınıfı
            'lang'  => \Mews\Pos\PosInterface::LANG_TR, // optional
            'gateway_endpoints'  => [
                'payment_api'     => 'https://sanalpos.isbank.com.tr/fim/api',
                'gateway_3d'      => 'https://sanalpos.isbank.com.tr/fim/est3Dgate',
            ],
        ],
    ]
];
```

## Örnek Kodlar

Örnekleri `/docs` ve `/examples` dizini içerisinde bulabilirsiniz.

`/examples` kodları çalıştırmak için [alttaki bölüme](#docker-ile-örnek-kodların-denenmesi)  bakınız.
### Popup Window'da veya iframe içinde ödeme yapma

Müşteriyi banka sayfasına redirect etmeden **iframe** üzerinden veya **popup
window**
üzerinden ödeme akışı
[examples'da](./examples)
ve [/docs'da](docs/THREED-PAYMENT-IN-IFRAME-OR-POPUP-EXAMPLE.md) 3D
ödeme ile örnek PHP ve JS kodlar yer almaktadır.



## Troubleshoots

### Session sıfırlanması

Cookie session kullandığınızda, kullanıcı gatewayden geri websitenize
yönlendirildiğinde session sıfırlanabilir.
Response'da `samesite` değeri set etmeniz
gerekiyor. [çözüm](https://stackoverflow.com/a/51128675/4896948).

### Shared hosting'lerde IP tanımsız hatası

- Shared hosting'lerde Cpanel'de gördüğünüz IP'den farklı olarak fiziksel
  sunucun bir tane daha IP'si olur.
  O IP adresi cPanel'de gözükmez, hosting firmanızdan sorup öğrenmeniz
  gerekmekte.
  Bu hatayı alırsanız hosting firmanın verdiği IP adresine de banka gateway'i
  tarafından izin verilmesini sağlayın.

### Debugging

Kütüphane [PSR-3](https://www.php-fig.org/psr/psr-3/) standarta uygun logger
uygulamayı destekler.
Örnekler: https://packagist.org/providers/psr/log-implementation .

Monolog logger kullanım örneği:

```shell
composer require monolog/monolog
```

```php
$handler = new \Monolog\Handler\StreamHandler(__DIR__.'/../var/log/pos.log', \Psr\Log\LogLevel::DEBUG);
$logger = new \Monolog\Logger('pos', [$handler]);
$pos = \Mews\Pos\Factory\PosFactory::create(
    $account,
    $config['banks'][$account->getBankName()],
    $eventDispatcher,
    null,
    null,
    $logger
);
```

## Genel Kültür

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
  isteği gönderilebilir.
  Bu işlem için kullanıcıdan kredi kart bilgisi _alınmaz_.
  Onun yerine bazı gateway'ler `orderId` değeri istenir,
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

## Docker ile Örnek Kodların Denenmesi

1. Makinenizde Docker kurulu olması gerekir.
2. Örnekleri çalıştırmadan önce banka hesap bilgilerini ortam değişkenleri
   aracılığıyla sağlamanız gerekmektedir: `examples/.env.dist` dosyasını
   `examples/.env` olarak kopyalayın:
   ```shell
   cp examples/.env.dist examples/.env
   ```
3. `examples/.env` dosyasını açıp kullanmak istediğiniz banka(lar)a ait hesap
   bilgilerini (merchant ID, şifre, API anahtarı vb.) doldurun.
4. Projenin root klasöründe `docker-compose up -d` komutu çalıştırınız.
5. docker container'de `composer install` çalıştırınız.
   ```shell
   docker compose exec -it web composer install
   ```

**Note**: localhost port 80 boş olması gerekiyor.
Sorunsuz çalışması durumda kod örneklerine http://localhost/asseco/3d/index.php
şekilde erişebilirsiniz.
http://localhost/ URL projenin `examples` klasörünün içine bakar.

### Unit testler çalıştırma

Projenin root klasöründe bu satırı çalıştırmanız gerekiyor

```sh
$ docker compose exec -it web composer test
```

> Değerli yorum, öneri ve katkılarınız için teşekkür ederiz.
>
> Sorun bulursanız veya eklenmesi gereken POS sistemi varsa lütfen issue
> oluşturun.

License
----

MIT
