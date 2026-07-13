# v1'den v2'ye Geçiş Kılavuzu

Bu belge, `mews/pos` kütüphanesinin v1'den v2'ye yükseltilmesi için yapılması gereken değişiklikleri açıklamaktadır.
İç mimariye ait değişiklikler dahil değildir; yalnızca kütüphaneyi kullanan uygulamaları etkileyen kırıcı değişiklikler ele alınmıştır.

---

## İçindekiler

- [Gereksinimler](#gereksinimler)
- [Kaldırılan Bağımlılık](#kaldırılan-bağımlılık)
- [1. Namespace Değişiklikleri](#1-namespace-değişiklikleri)
- [2. Gateway Sınıf İsimleri](#2-gateway-sınıf-isimleri)
- [3. Account Sınıf İsimleri](#3-account-sınıf-isimleri)
- [5. AbstractPosAccount Metot Değişiklikleri](#5-abstractposaccount-metot-değişiklikleri)
- [6. PosInterface — Kırıcı Değişiklikler](#6-posinterface--kırıcı-değişiklikler)
- [7. PosFactory Değişiklikleri](#7-posfactory-değişiklikleri)
- [8. Exception Değişiklikleri](#8-exception-değişiklikleri)
- [9. Config Dosyası Değişiklikleri](#9-config-dosyası-değişiklikleri)
- [10. Response Verisi Değişiklikleri](#10-response-verisi-değişiklikleri)
- [11. Gateway'e Özel Davranış Değişiklikleri](#11-gatewaye-özel-davranış-değişiklikleri)
- [Hızlı Referans: Kod Örnekleri](#hızlı-referans-kod-örnekleri)
- [Kontrol Listesi](#kontrol-listesi)

---

## Gereksinimler

| | v1 | v2 |
|---|---|---|
| Minimum PHP | 7.4 | **8.0** |

---

## Kaldırılan Bağımlılık

`symfony/http-foundation` artık bu kütüphanenin bağımlılığı değildir.

---

## 1. Namespace Değişiklikleri

### Gateway ve Exception namespace'leri

Çoğul yazılan namespace dizinleri tekil hale getirildi.

| v1 | v2 |
|---|---|
| `Mews\Pos\Gateways\*` | `Mews\Pos\Gateway\*` |
| `Mews\Pos\Exceptions\*` | `Mews\Pos\Exception\*` |

### Account ve Card namespace'leri (`Entity` → `Model`)

| v1 | v2 |
|---|---|
| `Mews\Pos\Entity\Account\*` | `Mews\Pos\Model\Account\*` |
| `Mews\Pos\Entity\Card\*` | `Mews\Pos\Model\Card\*` |

---

## 2. Gateway Sınıf İsimleri

| v1 | v2 |
|---|---|
| `Mews\Pos\Gateways\EstV3Pos` | `Mews\Pos\Gateway\AssecoPos` |
| `Mews\Pos\Gateways\EstPos` | **kaldırıldı** |
| `Mews\Pos\Gateways\PosNet` | `Mews\Pos\Gateway\PosNetPos` |

**Yeni gateway'ler:**
- `Mews\Pos\Gateway\IyzicoPos`
- `Mews\Pos\Gateway\Param3DHostPos` — `ParamPos`'un 3DHost ödeme akışı ayrı bir gateway'e taşındı; bu sayede `ParamPos` yapılandırması basitleşti ve her iki gateway bağımsız olarak konfigure edilebilir hale geldi.
- `Mews\Pos\Gateway\PayTrPos`

Config dosyalarındaki `class` anahtarlarını, `instanceof` kontrollerini ve `switch` ifadelerini güncelleyin.

---

## 3. Account Sınıf İsimleri

Tüm account sınıfları `Mews\Pos\Entity\Account\` altından `Mews\Pos\Model\Account\` altına taşındı.

| v1 | v2 |
|---|---|
| `Entity\Account\EstPosAccount` | `Model\Account\AssecoPosAccount` |
| `Entity\Account\KuveytPosAccount` | `Model\Account\BoaPosAccount` |
| `Entity\Account\PayFlexAccount` | `Model\Account\PayFlexPosAccount` |
| `Entity\Account\PayForAccount` | `Model\Account\PayForPosAccount` |
| `Entity\Account\PosNetAccount` | `Model\Account\PosNetPosAccount` |

---

## 4. AccountFactory Değişiklikleri

### 4a. Yeniden adlandırılan metotlar

| v1 | v2 |
|---|---|
| `createEstPosAccount()` | `createAssecoPosAccount()` |
| `createKuveytPosAccount()` | `createBoaPosAccount()` |
| `createPayForAccount()` | `createPayForPosAccount()` |
| `createPayFlexAccount()` | `createPayFlexPosAccount()` |
| `createPosNetAccount()` | `createPosNetPosAccount()` |

### 4b. `$lang` parametresi kaldırıldı

`$lang` parametresi tüm factory metotlarından kaldırıldı. Dil artık config dosyasından ayarlanmaktadır (bkz. [Bölüm 9](#9-config-dosyası-değişiklikleri)).

Etkilenen metotlar: `createAssecoPosAccount()`, `createAkbankPosAccount()`, `createBoaPosAccount()`,
`createGarantiPosAccount()`, `createInterPosAccount()`, `createPayForPosAccount()`, `createPosNetPosAccount()`

### 4c. `$model` parametresi kaldırıldı

Ödeme modeli (`$model` / `$paymentModel`) artık account'da saklanmıyor; her işlemde `$pos->payment($model, ...)` çağrısına parametre olarak geçiliyor.

```php
// v1
$account = AccountFactory::createAssecoPosAccount(
    'akbank', $clientId, $user, $pass,
    PosInterface::MODEL_3D_SECURE, // <-- kaldırıldı
    $storeKey,
    PosInterface::LANG_TR          // <-- kaldırıldı
);

// v2
$account = AccountFactory::createAssecoPosAccount(
    'akbank', $clientId, $user, $pass, $storeKey
);
```

### 4d. Etkilenen diğer metotlar için özet parametre değişiklikleri

| Metot | v1 | v2 |
|---|---|---|
| `createAssecoPosAccount()` | `($b,$c,$u,$p,$m,$sk,$lang)` | `($b,$c,$u,$p,$sk)` |
| `createBoaPosAccount()` | `($b,$m,$u,$c,$sk,$mo,$lang,$sub)` | `($b,$m,$u,$c,$sk,$sub)` |
| `createPayForPosAccount()` | `($b,$m,$u,$p,$mo,$sk,$lang,$mbr)` | `($b,$m,$u,$p,$sk,$mbr)` |
| `createPayFlexPosAccount()` | `($b,$m,$pw,$t,$mo,$mt,$sub)` | `($b,$m,$pw,$t,$mt,$sub)` |
| `createPosNetPosAccount()` | `($b,$m,$tid,$pid,$mo,$sk,$lang)` | `($b,$m,$tid,$pid,$sk)` |
| `createAkbankPosAccount()` | `($b,$m,$t,$sk,$lang,$sub)` | `($b,$m,$t,$sk,$sub)` |
| `createGarantiPosAccount()` | `($b,$m,$u,$p,$t,$mo,$sk,$ru,$rp,$lang)` | `($b,$m,$u,$p,$t,$sk,$ru,$rp)` |
| `createInterPosAccount()` | `($b,$s,$u,$p,$mo,$mp,$lang)` | `($b,$s,$u,$p,$mp)` |

### 4e. GarantiPos için parametre sırası değişti

`$lang` kaldırılmasıyla `$terminalId` beşinci sıraya taşındı.

```php
// v1
AccountFactory::createGarantiPosAccount($bank, $merchantId, $user, $pass, $lang, $terminalId, $storeKey, ...);

// v2 — $lang kaldırıldı, $terminalId 5. sıraya geldi
AccountFactory::createGarantiPosAccount($bank, $merchantId, $user, $pass, $terminalId, $storeKey, ...);
```

### 4f. Yeni yöntem: `createForGateway()` (config tabanlı)

Gateway sınıf adını ve kimlik bilgilerini bir dizi olarak geçerek hangi gateway olduğunu
kodunuza gömmeden account oluşturabilirsiniz. Framework entegrasyonlarında kullanışlıdır.

```php
$account = AccountFactory::createForGateway(
    \Mews\Pos\Gateway\GarantiPos::class,
    'garanti',
    [
        'merchant_id'   => '7000679',
        'user_name'     => 'PROVAUT',
        'user_password' => 'pass',
        'terminal_id'   => '30691298',
        'secret_key'    => 'store-key',
    ]
);
```

---

## 5. AbstractPosAccount Metot Değişiklikleri

| v1 | v2 |
|---|---|
| `getBank(): string` | `getBankName(): string` |
| `getLang(): string` | **kaldırıldı** |
| `getClientId(): string` | `getMerchantId(): string` |
| `getStoreKey(): ?string` | `getSecretKey(): string` |
| *(yalnızca alt sınıfta)* | `getTerminalId(): string` — temel sınıfa taşındı |

> **Dikkat:** `getSecretKey()` artık `string` döndürüyor (nullable değil).
> Secret key olmayan bir account üzerinde çağırmak Exception'a yol açar.
> `getTerminalId()` için de aynı durum geçerlidir.

---

## 5b. CreditCardInterface Metot Değişiklikleri

`getExpireYear()` ve `getExpireMonth()` metotları `CreditCardInterface`'den **kaldırıldı**.
Son kullanma tarihi için `getExpirationDate()` kullanın; bu metot `\DateTimeImmutable` döndürür.

```php
// v1
$year  = $card->getExpireYear();   // örn. "24"
$month = $card->getExpireMonth();  // örn. "06"

// v2
$expDate = $card->getExpirationDate(); // \DateTimeImmutable
$year    = $expDate->format('y');      // örn. "24"
$month   = $expDate->format('m');      // örn. "06"
```

`CreditCardInterface`'i kendiniz implemente ediyorsanız bu iki metodu sınıfınızdan kaldırın
ve `getExpirationDate(): \DateTimeImmutable` metodunu eklediğinizden emin olun.

---

## 6. PosInterface — Kırıcı Değişiklikler

### 6a. Tüm işlem metotları artık `array` döndürüyor

v1'de `$pos->payment()` gibi metotlar zincirleme yapılabilmek için `PosInterface` döndürüyordu; sonuç `getResponse()` ile alınıyordu.
v2'de metotlar doğrudan sonuç dizisini döndürüyor.

```php
// v1 — fluent interface
$pos->payment($paymentModel, $order, $txType, $card);
$response = $pos->getResponse();

// v2 — doğrudan dizi dönüyor
$response = $pos->payment($paymentModel, $order, $txType, $card);
```

Etkilenen metotlar: `payment()`, `makeRegularPayment()`, `makeRegularPostPayment()`,
`make3DPayment()`, `make3DPayPayment()`, `make3DHostPayment()`,
`refund()`, `cancel()`, `status()`, `orderHistory()`, `history()`, `customQuery()`

### 6b. 3D ödeme callback'lerinde Symfony `Request` nesnesi yerine düz dizi

v1'de `make3DPayment()` ve benzeri metotlar `symfony/http-foundation` paketinin `Request` nesnesini alıyordu.
v2'de bu bağımlılık kaldırıldı; bankadan gelen callback verileri düz PHP dizisi olarak geçilir.

### 6c. `payment()` — yeni `$gatewayResponseData` parametresi

3D ödeme modellerinde banka callback verisi artık `payment()` metoduna beşinci parametre olarak geçilebilir.

```php
// v2 imzası
$gatewayResponseData = $_POST; // 3D otorizasyon sonrası bankadan gelen yanıt verileri.
if ($pos::class === \Mews\Pos\Gateway\PayFlexCPV4Pos::class) {
    $gatewayResponseData = $_GET;
}
$response = $pos->payment(
    $paymentModel,          // örn: PosInterface::MODEL_3D_SECURE
    $order,
    $transactionType,
    $creditCard,            // NonSecure için zorunlu; 3DHost için null
    $gatewayResponseData    // 3D callback için $_POST veya $_GET, bankaya göre değişir, örnek kodlara bak
);
```

### 6d. `get3DFormData()` — `$createWithoutCard` varsayılanı değişti

`$createWithoutCard` parametresinin varsayılanı `true`'dan **`false`**'a değişti.
v1'de bu parametreyi açıkça geçmeden çağırdıysanız `true` geçmeniz gerekiyor.

```php
// v1 — varsayılan true idi
$formData = $pos->get3DFormData($order, $paymentModel, $txType, $card);

// v2 — varsayılan false; kart olmadan form oluşturmak için true geçin
$formData = $pos->get3DFormData($order, $paymentModel, $txType, $card, true);
```

Ayrıca yeni bir `$formFormat` parametresi eklendi:

```php
$formData = $pos->get3DFormData(
    $order,
    $paymentModel,
    $txType,
    $card,
    false,                          // $createWithoutCard
    PosInterface::FORM_FORMAT_ARRAY // veya FORM_FORMAT_HTML; null = gateway varsayılanı
);
```
- PayForPos Gateway'de IP kısıtlaması sorunu aşmak için `PosInterface::FORM_FORMAT_HTML` kullanabilirsiniz.
- IyzicoPos hem array hem de HTML döner, `$formFormat` ayarıyla istediğiniz formatta alabilirsiniz.
- Desteklenmeyen format belirtilirse `UnsupportedFormFormatException` fırlatılır.

### 6e. Gateway'e özel sipariş verileri artık `$order` dizisiyle geçiliyor

v1'de bazı gateway'ler (KuveytPos, IyzicoPos, PayTrPos) için alıcı bilgisi, fatura adresi ve
sepet içeriği gibi ekstra veriler `RequestDataPreparedEvent` listener'ı içinde API isteğine ekleniyor,
`$order` dizisi bu bilgileri taşımıyordu.

v2'de bu ekstra veriler `$order` dizisine dahil edilerek `payment()` ve `get3DFormData()` metodlarına geçiliyor.
Listener'a gerek kalmadan kütüphane bu alanları doğrudan API isteğine dahil ediyor.

```php
// v1 — ekstra veri event listener üzerinden ekleniyor
$eventDispatcher->addListener(RequestDataPreparedEvent::class, function (RequestDataPreparedEvent $event) {
    $data = $event->getRequestData();
    $data['buyer']           = ['email' => '...', 'name' => '...'];
    $data['billing_address'] = ['address' => '...'];
    $event->setRequestData($data);
});
$pos->get3DFormData($order, $paymentModel, $txType, $card);

// v2 — ekstra veri doğrudan $order dizisine ekleniyor
$order['buyer']           = ['email' => '...', 'name' => '...'];
$order['billing_address'] = ['address' => '...'];
$pos->get3DFormData($order, $paymentModel, $txType, $card);
```

Gateway'e göre gerekli ekstra alanlar:

| Gateway | Gerekli alanlar |
|---|---|
| **KuveytPos** | `payment_channel`, `buyer` (email, gsm_number_cc, gsm_number), `billing_address` (city, country, address, zip_code, state) — yalnızca `get3DFormData()` için gereklidir |
| **IyzicoPos** | `buyer`, `billing_address`, `shipping_address`, `basket_items`, `payment_channel` (NonSecure/3DSecure) veya `enabled_installments` (3DHost) |
| **PayTrPos** | `buyer` (email, name, gsm_number), `billing_address`, `basket_items` |

Ayrıntılar ve tam alan listesi için `examples/{gateway}/_payment_config.php` dosyalarına bakınız.

### 6f. KuveytPos ve VakifKatilimPos artık HTML string döndürüyor

v1'de bu iki gateway için `get3DFormData()` bankadan dönen HTML formu ayrıştırıp dizi olarak döndürüyordu.
v2'de HTML doğrudan string olarak döndürülüyor; ayrıştırma kaldırıldı.

| Gateway | v1 | v2 |
|---|---|---|
| `KuveytPos` | `array` (her zaman) | `string` HTML (her zaman); `FORM_FORMAT_ARRAY` desteklenmiyor |
| `VakifKatilimPos` | `array` (her zaman) | `string` HTML (3DSecure); `array` (3DHost) |

`get3DFormData()` çağrısından dönen değeri render ederken string kontrolü eklemeniz gerekiyor:

```php
$formData = $pos->get3DFormData($order, $paymentModel, $txType, $card);

if (is_string($formData)) {
    // KuveytPos / VakifKatilimPos (3DSecure): hazır HTML — doğrudan yazdırın
    echo $formData;
} else {
    // diğer gateway'ler: array
    // $formData['gateway'], $formData['method'], $formData['inputs'] alanlarını kullanın
}
```

Bu kontrol zaten `docs/THREED-PAYMENT-EXAMPLE.md` örnek kodunda yer almaktadır.

### 6g. PosQuery — `history()` ve `customQuery()` taşındı

`PosInterface::history()` ve `PosInterface::customQuery()` metotları **kaldırıldı**.
Bu işlemler artık `PosQueryInterface` üzerinden yürütülüyor.

#### Sabit adı değişiklikleri

| v1 / eski v2 sabiti | Yeni sabit |
|---|---|
| `PosInterface::TX_TYPE_HISTORY` | `PosQueryInterface::QUERY_TYPE_HISTORY` |
| `PosInterface::TX_TYPE_CUSTOM_QUERY` | `PosQueryInterface::QUERY_TYPE_CUSTOM_QUERY` |

#### Kullanım

`PosQueryFactory::create()` ile bir `PosQueryInterface` örneği oluşturun:

```php
use Mews\Pos\Factory\PosQueryFactory;

$posQuery = PosQueryFactory::create(
    $account,                                           // AbstractPosAccount — PosFactory'de kullandığınızın aynısı
    $config['banks'][$account->getBankName()],          // tek bankanın config dilimi
    $eventDispatcher,                                   // PSR-14 EventDispatcherInterface
    null,                                               // ?LoggerInterface — isteğe bağlı
);
```

```php
// Genel işlem geçmişi
// v1 / eski v2: $pos->history($data)
$response = $posQuery->history([
    'start_date' => new \DateTime('-1 month'),
    'end_date'   => new \DateTime(),
]);

// Ham API çağrısı
// v1 / eski v2: $pos->customQuery($requestData, $apiUrl)
$response = $posQuery->customQuery($requestData, $apiUrl);
```
---

### 6h. `setTestMode()` artık genel arayüzde yok

`setTestMode()` metodu artık dışarıdan çağrılamıyor.
Test modunu config dosyasından ayarlayın (bkz. [Bölüm 9](#9-config-dosyası-değişiklikleri)).

---

## 7. PosFactory Değişiklikleri

```php
// v1
PosFactory::createPosGateway(
    $account,
    $config,
    $eventDispatcher,
    $httpClient,  // kütüphaneye özgü HttpClient nesnesi
    $logger
);

// v2
PosFactory::create(
    $account,
    $config['banks'][$account->getBankName()],  // tek bankanın config dilimi
    $eventDispatcher,
    null,          // ?HttpClientStrategyInterface — null geçin; factory otomatik oluşturur
    null,          // ?ClientInterface (PSR-18) — özel SSL/proxy yapılandırması için geçilebilir
    $logger
);
```

v1'deki kütüphaneye özgü `$httpClient` parametresi kaldırıldı.
v2'de iki isteğe bağlı parametre eklendi:

- **4. parametre** `?HttpClientStrategyInterface $httpClientStrategy` — `null` geçin; factory banka için doğru stratejiyi otomatik seçer.
- **5. parametre** `?ClientInterface $httpClient` — PSR-18 uyumlu bir HTTP istemcisi. Kendi SSL sertifikanızı veya proxy yapılandırmanızı enjekte etmek istediğinizde kullanın; aksi hâlde `null` geçin.

---

## 8. Exception Değişiklikleri

### 8a. Yeniden adlandırılan exception'lar

Catch bloklarınızı güncelleyin:

| v1 | v2 |
|---|---|
| `Mews\Pos\Exception\BankClassNullException` | `Mews\Pos\Exception\GatewayClassNotConfiguredException` |
| `Mews\Pos\Exception\BankNotFoundException` | removed — `PosFactory::create()` no longer throws it |

```php
// v1
} catch (\Mews\Pos\Exception\BankNotFoundException | \Mews\Pos\Exception\BankClassNullException $e) {

// v2 — only GatewayClassNotConfiguredException remains; BankNotFoundException equivalent is gone
} catch (\Mews\Pos\Exception\GatewayClassNotConfiguredException $e) {
```

### 8b. Yeni `PosException` marker interface'i

Tüm kütüphane exception'ları artık `Mews\Pos\Exception\PosException` interface'ini implement ediyor.
Tüm kütüphane hatalarını tek bir catch bloğuyla yakalayabilirsiniz:

```php
use Mews\Pos\Exception\PosException;

try {
    $response = $pos->payment(...);
} catch (PosException $e) {
    // kütüphaneden fırlatılan tüm exception'ları yakalar
}
```

## 9. Config Dosyası Değişiklikleri

### 9a. Gateway sınıf adları

```php
// v1
'class' => Mews\Pos\Gateways\EstV3Pos::class,
'class' => Mews\Pos\Gateways\PosNet::class,

// v2
'class' => \Mews\Pos\Gateway\AssecoPos::class,
'class' => \Mews\Pos\Gateway\PosNetPos::class,
```

### 9b. `test_mode` `gateway_configs` içine taşındı

```php
// v1 (üst düzeyde)
'akbank' => [
    'test_mode' => true,
    // ...
]

// v2 — gateway_configs içinde olmalı
'akbank' => [
    'gateway_configs' => [
        'test_mode' => true,
    ],
    // ...
]
```

### 9c. `lang` `gateway_configs` içine taşındı

`$lang` parametresi factory metotlarından kaldırıldı; dil artık config'den okunur. Belirtilmezse `LANG_TR` kullanılır.

```php
// v1 (üst düzeyde)
'akbank' => [
    'lang' => \Mews\Pos\PosInterface::LANG_TR,
    // ...
]

// v2 — gateway_configs içinde
'akbank' => [
    'gateway_configs' => [
        'lang' => \Mews\Pos\PosInterface::LANG_TR,
    ],
    // ...
]
```

### 9d. ParamPos yapılandırması değişti — Param3DHostPos eklendi

v1'de `ParamPos` 3DHost ödemesi için `payment_api_2` ve `gateway_3d_host` endpoint'lerini de kendi
yapılandırmasında tutuyordu. v2'de bu akış `Param3DHostPos` adında ayrı bir gateway'e taşındı;
`ParamPos` yalnızca kendi API endpoint'ini içeriyor.

**ParamPos** yapılandırmasından `payment_api_2` ve `gateway_3d_host` anahtarlarını kaldırın:

```php
// v1
'param-pos' => [
    'class'             => Mews\Pos\Gateways\ParamPos::class,
    'gateway_endpoints' => [
        'payment_api'     => 'https://...param.com.tr/.../service_turkpos.asmx',
        'payment_api_2'   => 'https://...param.com.tr/.../Service_Odeme.asmx', // kaldırıldı
        'gateway_3d_host' => 'https://...param.com.tr/default.aspx',           // kaldırıldı
    ],
],

// v2
'param-pos' => [
    'class'             => \Mews\Pos\Gateway\ParamPos::class,
    'gateway_endpoints' => [
        'payment_api' => 'https://...param.com.tr/.../service_turkpos.asmx',
    ],
],
'param-3d-host-pos' => [
    'class'             => \Mews\Pos\Gateway\Param3DHostPos::class,
    'gateway_endpoints' => [
        'payment_api'     => 'https://...param.com.tr/.../Service_Odeme.asmx',
        'gateway_3d_host' => 'https://...param.com.tr/default.aspx',
    ],
],
```

3DHost ödemelerini `ParamPos` üzerinden yapıyorsanız artık `Param3DHostPos` kullanmanız gerekiyor.
Account oluşturmak için `AccountFactory::createParamPosAccount()` her iki gateway için de kullanılabilir.

### 9e. `currencies` config anahtarı kaldırıldı

v1'de config dosyasına opsiyonel bir üst düzey `currencies` anahtarı ekleyerek para birimi
eşleştirmelerini özelleştirmek mümkündü:

```php
// v1 — artık desteklenmiyor
return [
    'currencies' => [
        \Mews\Pos\PosInterface::CURRENCY_TRY => '949',
        \Mews\Pos\PosInterface::CURRENCY_USD => '840',
    ],
    'banks' => [ ... ],
];
```

v2'de para birimi eşleştirmeleri her gateway'in kendi `RequestValueMapper` sınıfı içinde
sabit olarak tanımlıdır; config üzerinden özelleştirilemez.
Config dosyanızda `currencies` anahtarı varsa kaldırın.

### 9f. Bazı gateway'lerde kaldırılan endpoint'ler

Aşağıdaki gateway'lerin `gateway_3d` endpoint'i gateway tarafından `payment_api` URL'inden türetildiği için
config'den kaldırıldı. Yapılandırmanızda bu anahtarlar varsa silin.

| Gateway | Kaldırılan anahtar |
|---|---|
| `KuveytPos` | `gateway_3d` |
| `VakifKatilimPos` | `gateway_3d` |

`PayFlexCPV4Pos` için hem `gateway_3d` kaldırıldı hem de `payment_api` URL'i kısaltıldı
(path suffix'leri gateway tarafından dahili olarak ekleniyor):

```php
// v1
'vakifbank-cp' => [
    'class'             => Mews\Pos\Gateways\PayFlexCPV4Pos::class,
    'gateway_endpoints' => [
        'payment_api' => 'https://cptest.vakifbank.com.tr/CommonPayment/api/VposTransaction',
        'gateway_3d'  => 'https://cptest.vakifbank.com.tr/CommonPayment/api/RegisterTransaction',
    ],
],

// v2 — gateway_3d kaldırıldı, payment_api kısaltıldı
'vakifbank-cp' => [
    'class'             => \Mews\Pos\Gateway\PayFlexCPV4Pos::class,
    'gateway_endpoints' => [
        'payment_api' => 'https://cptest.vakifbank.com.tr/CommonPayment/api',
    ],
],
```

### 9g. `name` config anahtarı kaldırıldı

Banka config dosyalarındaki `name` anahtarı hiçbir zaman kodda kullanılmıyordu ve kaldırıldı.
Kendi config dosyanızda tanımlıysa silin:

```php
// v1 / eski yapılandırma — name kaldırın
'akbank' => [
    'name'  => 'AKBANK T.A.S.',   // <-- bu satırı silin
    'class' => \Mews\Pos\Gateway\AssecoPos::class,
    // ...
],
```

---

## 10. Response Verisi Değişiklikleri

### `status_detail` kaldırıldı

`status_detail` alanı tüm response tiplerinden kaldırıldı.
Bu alan v1'de `proc_return_code` değerinin insan-okunabilir karşılığını döndürüyordu
(örn. `"insufficient_balance"`, `"expired_card"`).

Etkilenen response tipleri: ödeme (`payment`), iptal (`cancel`), iade (`refund`),
durum sorgulama (`status`), sipariş tarihçesi (`orderHistory`).

```php
// v1 — status_detail her response'da yer alıyordu
$response['status_detail']; // örn. "insufficient_balance"

// v2 — bu alan artık yok; proc_return_code üzerinden kendiniz yorumlayın
$response['proc_return_code']; // örn. "51"
```

### `md_status_detail` kaldırıldı

3D ödeme response'larında yer alan `md_status_detail` alanı da kaldırıldı.
3D otorizasyon sonucunu değerlendirmek için `md_status` ve `md_error_message` alanlarını kullanın.

---

## 11. Gateway'e Özel Davranış Değişiklikleri

### KuveytPos — ödeme için zorunlu ekstra `$order` alanları

v1'de KuveytPos ödemeleri için gerekli olan alıcı bilgisi, ödeme kanalı ve fatura adresi
`RequestDataPreparedEvent` listener'ı içinde API isteğine ekleniyordu; `$order` dizisi bu
bilgileri taşımıyordu.

v2'de bu ekstra veriler doğrudan `$order` dizisine dahil edilerek `payment()` ve
`get3DFormData()` metodlarına geçiliyor:

```php
$order = [
    'id'              => '...',
    'amount'          => 1.01,
    // ... diğer zorunlu alanlar ...

    // KuveytPos zorunlu ekstra alanlar:
    'payment_channel' => '02', // 01 = Mobil, 02 = Web Browser
    'buyer'           => [
        'email'         => 'musteri@example.com',
        'gsm_number_cc' => '90',        // ülke kodu
        'gsm_number'    => '5001234567', // abone numarası
    ],
    'billing_address' => [
        'city'     => 'İstanbul',
        'country'  => '792',  // ISO 3166-1 sayısal (Türkiye = 792)
        'address'  => 'Örnek Mahallesi, Örnek Caddesi No:1',
        'zip_code' => '34000',
        'state'    => '34',   // ISO 3166-2 il kodu
    ],
];

$pos->payment(PosInterface::MODEL_3D_SECURE, $order, PosInterface::TX_TYPE_PAY_AUTH, $card);
```

Bu alanlar yalnızca `get3DFormData()` için gereklidir (3D ödeme formu oluşturma aşaması).
3D callback sonrası çağrılan `payment()` ve NonSecure `payment()` bu alanları gerektirmez.
Ayrıntılar için `examples/kuveytpos/_payment_config.php` dosyasına bakınız.

### PosNet — iptal işleminde işlem tipi artık dinamik

v1'de PosNet iptal isteğinde işlem tipi (`transaction`) sabit olarak `sale` gönderiliyordu.
Bu durum ön otorizasyon (`TX_TYPE_PAY_PRE_AUTH`) iptallerinin çalışmamasına yol açıyordu.

v2'de iptal isteğindeki işlem tipi `$order['transaction_type']` değerinden alınır.
Belirtilmezse varsayılan olarak `PosInterface::TX_TYPE_PAY_AUTH` (sale) kullanılır.

```php
// Sıradan satış iptali — transaction_type belirtmek gerekmez; varsayılan TX_TYPE_PAY_AUTH
$cancelOrder = [
    'id'            => $lastResponse['order_id'],
    'payment_model' => $lastResponse['payment_model'],
    'ref_ret_num'   => $lastResponse['ref_ret_num'],
];

// Ön otorizasyon (pre-auth) iptali — transaction_type zorunlu
$cancelOrder = [
    'id'               => $lastResponse['order_id'],
    'payment_model'    => $lastResponse['payment_model'],
    'ref_ret_num'      => $lastResponse['ref_ret_num'],
    'transaction_type' => PosInterface::TX_TYPE_PAY_PRE_AUTH,
];

$response = $pos->cancel($cancelOrder);
```

---

## Hızlı Referans: Kod Örnekleri

### Gateway oluşturma

```php
// v1
$account = AccountFactory::createEstPosAccount(
    'akbank', $clientId, $user, $pass,
    PosInterface::MODEL_3D_SECURE,
    $storeKey,
    PosInterface::LANG_TR
);

$pos = PosFactory::createPosGateway($account, $config, $eventDispatcher);

// v2
$account = AccountFactory::createAssecoPosAccount(
    'akbank', $clientId, $user, $pass, $storeKey
);

$pos = PosFactory::create($account, $config['banks'][$account->getBankName()], $eventDispatcher);
```

### NonSecure ödeme

```php
// v1
$pos->payment($paymentModel, $order, $txType, $card);
$response = $pos->getResponse();

// v2
$gatewayResponseData = $_POST; // 3D otorizasyon sonrası bankadan gelen yanıt verileri.
if ($pos::class === \Mews\Pos\Gateway\PayFlexCPV4Pos::class) {
    $gatewayResponseData = $_GET;
}
$response = $pos->payment($paymentModel, $order, $txType, $card);
```

### 3D ödeme — form oluşturma

```php
// v1 — $createWithoutCard varsayılanı true idi
$formData = $pos->get3DFormData($order, $paymentModel, $txType, $card);

// v2 — $createWithoutCard varsayılanı false; davranış aynı kalsın istiyorsanız:
$formData = $pos->get3DFormData($order, $paymentModel, $txType, $card, false);
```

### 3D ödeme — callback işleme

```php
// v1 — Symfony Request nesnesi
$pos->make3DPayment($request, $order, $txType);
$response = $pos->getResponse();

// v2 — düz dizi, payment() üzerinden
$response = $pos->payment($paymentModel, $order, $txType, $card, $_POST);
```

### İptal

```php
// v1
$pos->cancel($order);
$response = $pos->getResponse();

// v2
$response = $pos->cancel($order);
```

### İade

```php
// v1
$pos->refund($order);
$response = $pos->getResponse();

// v2
$response = $pos->refund($order);
```

### Durum sorgulama

```php
// v1
$pos->status($order);
$response = $pos->getResponse();

// v2
$response = $pos->status($order);
```

### Exception yakalama

```php
// v1
} catch (\Mews\Pos\Exception\BankNotFoundException | \Mews\Pos\Exception\BankClassNullException $e) {

// GatewayClassNotConfiguredException veya PosException kullanın
} catch (\Mews\Pos\Exception\GatewayClassNotConfiguredException $e) {
// veya tüm kütüphane hatalarını yakalamak için:
} catch (\Mews\Pos\Exception\PosException $e) {
```

---

## Kontrol Listesi

- [ ] `composer.json`'da PHP sürüm gereksinimini `>=8.0` yaptınız mı?
- [ ] `Mews\Pos\Gateways\*` → `Mews\Pos\Gateway\*` (`use` ifadeleri)
- [ ] `Mews\Pos\Exceptions\*` → `Mews\Pos\Exception\*` (`use` ifadeleri)
- [ ] `Mews\Pos\Entity\Account\*` → `Mews\Pos\Model\Account\*`
- [ ] `Mews\Pos\Entity\Card\*` → `Mews\Pos\Model\Card\*`
- [ ] Gateway sınıf isimleri güncellendi mi? (`EstPos`/`EstV3Pos` → `AssecoPos`, `PosNet` → `PosNetPos`)
- [ ] `AccountFactory` metot isimleri güncellendi mi?
- [ ] `$lang` ve `$model` parametreleri factory çağrılarından kaldırıldı mı?
- [ ] GarantiPos için parametre sırası güncellendi mi?
- [ ] `$pos->payment()` ve diğer metotların dönüş değerleri artık doğrudan kullanılıyor mu? (`getResponse()` kaldırıldı)
- [ ] 3D callback'lerde `Request` nesnesi yerine `$_POST` / `$_GET` dizisi geçiliyor mu?
- [ ] `get3DFormData()` çağrılarında `$createWithoutCard` parametresi kontrol edildi mi?
- [ ] Config dosyalarındaki `name` anahtarı kaldırıldı mı?
- [ ] Config dosyalarında `test_mode` ve `lang` `gateway_configs` içine taşındı mı?
- [ ] `ParamPos` yapılandırmasından `payment_api_2` ve `gateway_3d_host` kaldırıldı mı? 3DHost için `Param3DHostPos` eklendi mi?
- [ ] `KuveytPos` ve `VakifKatilimPos` yapılandırmasından `gateway_3d` kaldırıldı mı?
- [ ] `PayFlexCPV4Pos` yapılandırmasından `gateway_3d` kaldırıldı mı ve `payment_api` URL'i kısaltıldı mı?
- [ ] Config dosyasındaki üst düzey `currencies` anahtarı kaldırıldı mı?
- [ ] KuveytPos, IyzicoPos, PayTrPos için ekstra sipariş verileri (`buyer`, `billing_address`, `basket_items` vb.) `$order` dizisine taşındı mı? (v1'de event listener'dan ekleniyor)
- [ ] `get3DFormData()` dönüş değeri `is_string()` kontrolüyle işleniyor mu? (KuveytPos ve VakifKatilimPos artık HTML string döndürüyor)
- [ ] `$response['status_detail']` kullanan kod kaldırıldı mı?
- [ ] 3D ödeme response'larında `$response['md_status_detail']` kullanan kod kaldırıldı mı?
- [ ] `setTestMode()` çağrıları kaldırıldı mı?
- [ ] Catch bloklarındaki `BankClassNullException` → `GatewayClassNotConfiguredException` güncellendi mi? (`BankNotFoundException` artık kütüphane tarafından fırlatılmıyor — bu catch bloklarını kaldırın)
- [ ] `HttpClientFactory` kullanımı kaldırıldı mı? (v2'de yok; `PosFactory` bunu dahili olarak yönetiyor)
- [ ] `PosFactory::createPosGateway()` → `PosFactory::create()` olarak yeniden adlandırıldı mı?
- [ ] `PosFactory::create()` çağrısında 4. parametre `null` (veya `HttpClientStrategyInterface`), 5. parametre `null` (veya PSR-18 `ClientInterface`) olarak güncellendi mi?
- [ ] `getBank()` → `getBankName()`, `getClientId()` → `getMerchantId()`, `getStoreKey()` → `getSecretKey()` güncellendi mi?
- [ ] `getLang()` çağrıları kaldırıldı mı?
- [ ] `CreditCardInterface::getExpireYear()` ve `getExpireMonth()` çağrıları `getExpirationDate()` kullanacak şekilde güncellendi mi?
- [ ] `$pos->history(...)` → `PosQueryFactory::create(...)->history(...)` olarak güncellendi mi?
- [ ] `$pos->customQuery(...)` → `PosQueryFactory::create(...)->customQuery(...)` olarak güncellendi mi?
- [ ] `PosInterface::TX_TYPE_HISTORY` → `PosQueryInterface::QUERY_TYPE_HISTORY` olarak güncellendi mi?
- [ ] `PosInterface::TX_TYPE_CUSTOM_QUERY` → `PosQueryInterface::QUERY_TYPE_CUSTOM_QUERY` olarak güncellendi mi?
