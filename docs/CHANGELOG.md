# Changelog

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
  AbstractCreditCard::CARD_TYPE_VISA, //bankaya göre zorunlu
  );
  ```
  daha fazla örnek için `examples` klasöre bakınız.
