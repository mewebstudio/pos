# Changelog

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
