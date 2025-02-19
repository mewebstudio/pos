# Changelog
## [1.6.0] - 2025-02-19

### New Features
- Param POS altyapı desteği eklendi. (issue #258)

### Changed
- Kütüphanenin bazı hatalı kullanım durumlarını önlemek için
  `\Mews\Pos\PosInterface::get3DFormData()` method'a yeni parametre eklendi.
- `\Mews\Pos\PosInterface::get3DFormData()` method'u alt yapı göre artık HTML string dönebilir.
- **Hash** hesaplama fonksiyonları ve fonksiyonların kullanıldığı yerler refactor edildi.
- Bankadan gelen response'un XML'mi veya HTML'mi olduğu kontrolü iyileştirildi.
- PayFlexCPV4 - `gateway_endpoints` konfigurasyonu değiştirildi ve `query_api` değer kaldırıldı.
- PayFlexCPV4 - ödeme durum sorgulama, iptal, iade gibi bu alt yapı tarafından desteklenmeyen işlemlerin kodları kaldırıldı.

### Fixed
- issue #254 - **KuveytPos** ve **VakifKatilim** undefined index _MerchantOrderId_ hatası.
- issue #249 - **AkbankPos** amount alanı patterne uymuyor hatası.
- PayFlexCPV4 - response'i decode edememe sorunu.
- PayFlexCPV4 - hatalı 3D ödemenin durumunu sorgulama isteği göndermesi.

# Changelog
## [1.5.0] - 2024-11-11

### New Features
- [Custom Query](./CUSTOM-QUERY-EXAMPLE.md) desteği eklendi. (issue #250)

### Changed
- **VakifKatilimPos** - sipariş detay sorgusunda mapping iyileştirilmesi.

### Fixed
- Bazı gatewaylarin response'larında bankadan gelen verinin yer almaması.

## [1.4.0] - 2024-07-02

### New Features
- **GarantiPos** - geçmiş işlemleri sorgulama desteği. (PR #221).
- **VakifKatilimPos** - kısmi iade desteği. (issue #218)

### Fixed
- **VakifKatilimPos** - 3D Secure ödeme çalışmıyor.
- **VakifKatilimPos** - iptal işlemi çalışmıyor.

# Changelog
## [1.3.0] - 2024-05-24

### New Features
- **KuveytPos** kısmi iade desteği eklendi. (issue #205)
- **EstPos/Payten** ön otorizasyonu kapatırken daha fazla miktar desteği eklendi. (issue #171)

### Changed
- Dispatched event'lere **gatewayClass**, **order**, **paymenModel** verileri eklendi.

### Fixed
- **PosNet** - 3D Secure ödeme başarısız olduğunda `3d_all` boş olması giderildi.

# Changelog
## [1.2.0] - 2024-05-19

### New Features
- **EstPos** TROY kart desteği eklendi. (issue #205)

### Changed
- Symfony v4 ve v7 desteği eklendi
- Atılan exception'lar daha spesifik olacak şekilde refactor edildi.

## [1.1.0] - 2024-04-26

### New Features
- [Akbank POS](https://sanalpos-prep.akbank.com/) entegrasyonu eklendi. (issue #191)
- **Vakif Katılım POS** entegrasyonu eklendi. (issue #181)
- **KuveytPos** - TDV2.0.0 API'a upgrade edildi. (issue #172)
- **KuveytPos** - MODEL_NON_SECURE ödeme desteği eklendi.

### Changed
- **KuveytPos** ödeme durum sorgulama isteğinin response mapping'i iyileştirildi.
- **KuveytPos** iade işlemi için _PartialDrawback_ yerine artık _Drawback_ kullanılıyor.

### Fixed
- **KuveytPos** - iptal ve iade çalışmama sorunu çözüldü. (issue #159)

### Breaking Changes
- ayarlar dosyasında KuvetPos için `payment_api` değeri
  `https://sanalpos.kuveytturk.com.tr/ServiceGateWay/Home/ThreeDModelProvisionGate`

   yerine

  `https://sanalpos.kuveytturk.com.tr/ServiceGateWay/Home` kullanmanız gerekiyor.
- composer.json'a `ext-zlib` extension zorunluluğu eklendi.

## [1.0.0] - 2024-03-30
### New Features

- `/docs` altında örnek kodlar eklendi (issue #148).
- API istek verilerinin gateway API'na gönderilmeden önce değiştirebilme.
Bu özellik [psr/event-dispatcher-implementation](https://packagist.org/providers/psr/event-dispatcher-implementation)
uygulaması kullanılarak eklendi (issue #178).
Kullanım örnekleri için `/examples` ve `/docs` klasörüne bakınız.
Eklenen Eventler:
  - `\Mews\Pos\Event\Before3DFormHashCalculatedEvent`
  - `\Mews\Pos\Event\RequestDataPreparedEvent`
- **ToslaPos** (Ak Öde) entegrasonu (issue #160).
- Para birimleri için yeni constantlar eklendi (örn. `PosInterface::CURRENCY_TRY`)
- Yeni `\Mews\Pos\PosInterface::isSupportedTransaction()` methodu eklendi.
Bu method ile kütüphanenin ilgili gateway için hangi işlemleri desteklediğini kontrol edebilirsiniz.

### Changed
- Kütüphane PHP sürümü **v7.4**'e yükseltildi.
- Deprecated olan `VakifBankCPPos` ve `VakifBankPos` gateway sınıflar kaldırıldı.
Yerine `PayFlexCPV4Pos` ve `PayFlexV4Pos` kullanabilirsiniz.
- `AccountFactory::createVakifBankAccount()` method silindi, yerine `AccountFactory::createPayFlexAccount()` kullanabilirsiniz.
- Constant'lar `AbstractGateway` sınıfından `PosInterface`'e taşındı.
- Constant'lar `AbstractCreditCard` sınıfından `CreditCardInterface`'e taşındı.
- Config yapısı değişdi.
**Test** ve **Prod** ortamları için artık farklı config dosyalar kullanılması gerekiyor.
Bu değişim sonucunda `\Mews\Pos\PosInterface::setTestMode();` işleminin çok da önemi kalmadı.
Yine de **GarantiPos** için `setTestMode()` kullanılmalıdır. Yeni format için `/config` klasörüne bakınız.
- Bazı constant isimleri değişdi
  - `TX_PAY` => `TX_TYPE_PAY_AUTH`
  - `TX_PRE_PAY` => `TX_TYPE_PAY_PRE_AUTH`
  - `TX_POST_PAY` => `TX_TYPE_PAY_POST_AUTH`
- `\Mews\Pos\PosInterface::prepare()` methodu kaldırıldı.
- Pos sınıfları oluşturmak için kullanılan `\Mews\Pos\Factory\PosFactory::createPosGateway()`
methodu artık konfigürasyon yolunu (örn. `./config/pos_test.php`) kabul etmiyor.
Config verisi **array** olarak sağlanması gerekiyor.
- `\Mews\Pos\Factory\PosFactory::createPosGateway()`'a **EventDispatcher** parametresi eklendi.
- `$order` verisinden bazı zorunlu olmayan alanlar kaldırıldı:
  - email
  - name
  - user_id
  - rand (artık kütüphane kendisi oluşturuyor)

- _vftcode_ (PosNet), _koiCode_ (PosNet), _imece_ kart (EstPos), _extraData_ (EstPos),
_callbackUrl_ (EstPos) gibi ekstra değerler kütüphaneden kaldırıldı.
Yerine yeni eklenen eventlarla API isteklere ekstra değerler ekleyebilirsiniz.
Kullanım örneği için örnek kodlara bakınız.
- **Tekrarlanan ödeme** yapısı biraz değiştirildi (örnek kodlara bakınız).
- `$response = \Mews\Pos\PosInterface::getResponse();` veri yapısına birkaç ekstra veri eklendi.
Artık ödeme **iptal**, **iade**, **durum** sorgulama işlemleri yapabilmek için `$response` içindeki veriler yeterli.
- `PosInterface`'e ödeme durumu response'unda yer alan `order_status` alanı için yeni constant'lar
(örn: `PAYMENT_STATUS_ERROR`, `PAYMENT_STATUS_PAYMENT_COMPLETED`) tanıtıldı
ve bu yeni constant'ları kullanacak şekilde güncellemeler yapıldı.
- Yeni `PosInterface::orderHistory()` methodu eklendi.
Siparişe ayıt geçmiş işlemleri sorgulamak için bu yeni methodu kullanmanız gerekiyor.
- Eski `PosInterface::history()` methodu sipariş bilgisi olmadan tarih gibi kriterlerle yapılan işlemler sorgulanabilinir.
- `history` (yeni `orderHistory`) ve `status` işlemlerin input yapısı normalize edildi.
- `history` (yeni `orderHistory`) ve `status` işlemlerin response yapısı normalize edildi.
- `PayForPos`'un **history** response'u normalize edildi.
- `response` yapısında bazı parametre isimleri değişdi:
  - trans_id    => transaction_id
  - trans_time  => transaction_time
- `EstPos` ve `EstV3Pos` response'undan `extra` verisi kaldırıldı.
- `response` yapısına `installment_count` ve `transaction_time` değerleri eklendi.
- `CreditCardFactory::create()` method ismi `CreditCardFactory::createForGateway()` olarak değiştirildi.

### Fixed
- `PayFor` history response'i işlerken oluşan exception düzeltildi.
- Fix issue #176 - `EstPos` ve `EstV3Pos`'dan **callbackUrl** kaldırıldı.
- Fix issue #187 - 3D_SECURE ödemede 3D hash kontrolü artık MD/3D status kontrolünden sonra yapılıyor.

## [0.16.0] - 2023-11-20
### New Features
- **Asseco** - #167 3D form verisine `callbackUrl`eklendi.
  Order verisinde yer alan **fail_url** callbackUrl'a atanmakdadır.

## [0.15.0] - 2023-10-03
### New Features
- **GarantiPos** - `sha512` hashleme desteği eklendi.


## [0.14.0] - 2023-09-09
### New Features
- **İşbank Asseco** - İMECE kart ile ödeme desteği eklendi.

   İMECE kart ile ödeme yapabilmek için
  ```
   $order['is_imece_card'] = true;
  ```
  ekleyerek sipariş verisi oluşturulması gerekiyor.

## [0.13.0] - 2023-06-24
### New Features
- **PosNetV1** - JSON API desteği eklendi.
- **PayFlexV4** - (eski VakifbankPos) ödeme durum sorgulama desteği eklendi.
- Örnek kodlara (/examples) iframe'de ve pop window'da ödeme örnek kodları eklendi.

### Changed
- **VakifBankPos** deprecated. Yerine **PayFlexV4Pos** oluşturuldu.
- **VakifBankCPPos** deprecated. Yerine **PayFlexCPV4Pos** oluşturuldu.
- EstPosCrypt, InterPosCrypt, GarantiPosCrypt `check3DHash()` iyileştirme yapıldı.

## [0.12.0] - 2023-03-13
### New Features
- Vakıfbank Common Payment (Ortak Ödeme) gateway desteği eklendi (`VakifBankCPPos`).
  Sadece **3DPay** ve **3DHost** ödeme destekleri eklendi.
  Örnek kodlar `examples/vakifbank-cp/` altında yer almaktadır.

### Changed
- **EstPos** - `MODEL_3D_PAY_HOSTING` desteği eklendi @umuttaymaz.
- `get3DFormData()` - artık zorunlu _kart_ veya _sipariş_ bilgileri olmadan çağrıldığında istisna fırlatır.
- `get3DFormData()` - dönen değere HTTP methodu eklendi. Örn: `'method' => 'POST'` (ya da GET);

### Fixed
- **Vakifbank** - bazı _undefined index_ hatalar giderildi.
- `VakifBankPosRequestDataMapper` - `OrderDescription` tanımsız olma durumu giderildi.

## [0.11.0] - 2023-01-08
### Changed
- Response formatı **object** yerine artık **array** olarak değiştirildi, `$pos->getResponse();` kod artık array döner.
  - Ödeme response içeriği basitleştirildi, aşağıda listelenen alanlar response'dan **kaldırıldı**
    - `id` - bu alanın değeri hep `auth_code` ile aynıydi, yerine `auth_code`'u kullanmaya devam edebilirsiniz,
    - `host_ref_num` - bu alanın değeri hep `ref_ret_num` ile aynıydı, `ref_ret_num`'u kullanmaya devam edebilirsiniz,
    - `code`
    - `response`
    - `xid`
    - `campaign_url`
    - `hash`
    - `hash_data`
    - `rand`
  - `amount` alanı çoğu gateway responselarında normalize edildi ("1.000,1" => 1000.1), ve artık **float** deger döner.
- 3D ödemede hash değeri (`check3DHash()`) dogurlanamazsa artık `Mews\Pos\Exceptions\HashMismatchException` exception fırlatır.
- Bankadan gelen response'ları normalize eden kodlar yeni oluşturulan sınıflara tasındı.
    orn: `Mews\Pos\DataMapper\ResponseDataMapper\EstPosResponseDataMapper`
- Hash oluşturma ve dogrulama kodlar yeni olusturulan Crypt sınıflara taşındı. örn: `Mews\Pos\Crypt\EstPosCrypt`
- `Mews\Pos\Gateways\PosNetCrypt` sınıf tamamen kaldırıldı.

## [0.10.0] - 2022-10-22
### New Features
- `Mews\Pos\Gateways\EstV3Pos` eklendi.
   Bu yeni sınıf 3D ödemelerde Payten'nin `hashAlgorithm=v3`'i yani `SHA-512` ile hashlemeyi kullanır.
   Eski `Mews\Pos\Gateways\EstPos` uygulamadan yenisine geçis yapmak icin ayarlar dosyasında (`/config/pos.php`) bu satırı:
   ```php
   'class' => Mews\Pos\Gateways\EstPos::class
   ```
   buna degiştirmeniz yeterli:
   ```php
   'class' => Mews\Pos\Gateways\EstV3Pos::class
   ```
   Güvenlik nedenlerle `EstV3Pos`'u kullanmanız tavsiye edilir. `EstPos` ilerki tarihlerde kaldırılacak.
- Payten/Asseco - **tekrarlanan** (recurring) ödemelerin durum sorgulama ve iptal işlem desteği eklendi.
  Kullanım örnekleri için `/examples/akbank/` altındaki kodları kontrol ediniz.


## [0.9.0] - 2022-09-03
### Changed
- Eski Gateway'e özel (orn. CreditCardEstPos) Kredi Kart sınıfları kaldırıldı.
`0.6.0` versiyonda tanıtılan `Mews\Pos\Entity\Card\CreditCard` kullanılacak.
### New Features
- `guzzlehttp/guzzle` hard coupling kaldırıldı.
  Artık herhangi bir [PSR-18 HTTP Client](https://packagist.org/providers/psr/http-client-implementation) kullanılabılınır.
  Bu degisiklikle beraber PSR-18 ve PSR-7 client kütüphaneleri kendiniz composer require ile yüklemeniz gerekiyor.

  Örneğin:
  ```shell
  composer require php-http/curl-client nyholm/psr7 mews/pos
  ```
  Eğer projenizde zaten PSR-18 ve PSR-7 kütüphaneleri yüklü ise, otomatik onları bulur ve kullanır.
  Kodda bir degişiklik gerektirmez.

- Gateway sınıflara **PSR-3** logger desteği eklendi.

  Monolog logger kullanım örnegi:
  ```shell
  composer require monolog/monolog
  ```
  ```php
  $handler = new \Monolog\Handler\StreamHandler(__DIR__.'/../var/log/pos.log', \Psr\Log\LogLevel::DEBUG);
  $logger = new \Monolog\Logger('pos', [$handler]);
  $pos = \Mews\Pos\Factory\PosFactory::createPosGateway($account, null, null, $logger);
  ```

## [0.7.0] - 2022-05-18
### Changed
- `\Mews\Pos\PosInterface::prepare()` method artık sipariş verilerini (_currency, id, amount, installment, transaction type_) değiştirmez/formatlamaz.
  Sipariş verilerinin formatlanmasını artık Data Request Mapper'de (örn. `PosNetRequestDataMapper`) istek göndermeden önce yapılır.

  Önce:
  ```php
  protected function preparePaymentOrder(array $order)
  {
     $installment = 0;
     if (isset($order['installment']) && $order['installment'] > 1) {
         $installment = $order['installment'];
     }

      return (object) array_merge($order, [
          'id'          => self::formatOrderId($order['id']),
          'installment' => self::formatInstallment($installment),
          'amount'      => self::amountFormat($order['amount']),
          'currency'    => $this->mapCurrency($order['currency']),
      ]);
  }
  ```
  Şimdi:
  ```php
  protected function preparePaymentOrder(array $order)
  {
      return (object) array_merge($order, [
          'id'          => $order['id'],
          'installment' => $order['installment'] ?? 0,
          'amount'      => $order['amount'],
          'currency'    => $order['currency'] ?? 'TRY',
      ]);
  }
  ```
- **GarantiPos** - tekrarlanan (`recurring`) ödeme desteği teklendi.
- **EstPos** - IP adres artık zorunlu değil.
- Language degerleri artık Gateway bazlı tanımlanmıyor. Önceden gateway bazlı:
  ```php
  \Mews\Pos\Gateways\EstPos::LANG_TR;
  \Mews\Pos\Gateways\EstPos::LANG_EN;
  \Mews\Pos\Gateways\GarantiPos::LANG_TR;
  \Mews\Pos\Gateways\GarantiPos::LANG_EN;
  ...
  ```
  Şimdi sadece:
  ```php
  \Mews\Pos\Gateways\AbstractGateway::LANG_TR;
  \Mews\Pos\Gateways\AbstractGateway::LANG_EN;
  ```
- Siparişde `currency` ve `installment` artık zorunlu değil. Varsayılan olarak `currency=TRY`, `installment=0` olarak kabul edilir.
- Single Responsibility prensibe uygun olarak bütün gateway sınıflarında istek verilerini hazırlama Request Data Mapper'lere
  (`EstPosRequestDataMapper`, `GarantiPosRequestDataMapper`, `InterPosRequestDataMapper`, `KuveytPosRequestDataMapper`, `PayForPosRequestDataMapper`, `PosNetRequestDataMapper`, `VakifBankPosRequestDataMapper`) taşındı.
  Bununla birlikte bazı sabit değerler Gateway sınıflardan Request Data Mapper sınıflara taşındı.


## [0.6.0] - 2022-04-18
### Changed
- Kredi kart class'ları bütün gateway'ler için **tek** `Mews\Pos\Entity\Card\CreditCard` class'ı olacak şekilde güncellendi.
Gateway için ayrı ayrı olan (örn. `CreditCardEstPos`) CreditCard class'ları bir sonraki major release'de kaldırılacak.
- Kodunuzu yeni card class'ı kullanacak şekilde güncelleyiniz:
  ```php
  /** @var Mews\Pos\Entity\Card\CreditCard $card */
  $card = \Mews\Pos\Factory\CreditCardFactory::create(
  $pos, //pos gateway objesi
  '4444555566667777',
  '25',
  '12',
  '123',
  'john',
  CreditCardInterface::CARD_TYPE_VISA, //bankaya göre zorunlu
  );
  ```
  daha fazla örnek için `examples` klasöre bakınız.
