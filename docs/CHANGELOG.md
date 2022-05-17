# Changelog

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
