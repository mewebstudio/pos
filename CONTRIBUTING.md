# Katkıda Bulunma Rehberi

Bu doküman, küçük/rutin bir değişiklik için (bug fix, mapper düzeltmesi, yeni
test, dokümantasyon güncellemesi) günlük iş akışını anlatır.

Yeni bir banka gateway'i eklemek çok daha büyük bir iştir (her factory'ye yeni
bir kayıt eklemek gerekir) ve ayrıca koordine edilir. Böyle bir şey
planlıyorsanız, koda başlamadan önce bir issue açın.

Aşağıdaki komutlar Docker container'ı içinde çalıştırılmalıdır
([`CLAUDE.md`](./CLAUDE.md)'de belirtildiği gibi):

```bash
docker compose exec -it web <komut>
```

Kısalık için bu dokümandaki komutlar bu prefix olmadan yazılmıştır (örn.
`composer cs-check` yerine `composer cs-check`) — çalıştırırken kendiniz
ekleyin.

## İçindekiler

- [İlk kurulum](#ilk-kurulum)
- [Altın kural: MR açmadan önce lokalde her şey yeşil olmalı](#altın-kural-mr-açmadan-önce-lokalde-her-şey-yeşil-olmalı)
- [Lokal pipeline, detaylı](#lokal-pipeline-detaylı)
  - [1. Code style (`cs-check` / `cs-fix`)](#1-code-style-cs-check--cs-fix)
  - [2. Statik analiz (`phpstan`)](#2-statik-analiz-phpstan)
  - [3. Unit testler (`test`)](#3-unit-testler-test)
  - [4. Functional testler (sadece bir gateway'e dokunduysanız)](#4-functional-testler-sadece-bir-gatewaye-dokunduysanız)
- [Git hook'ları](#git-hookları)
- [Değişikliğiniz için test yazmak](#değişikliğiniz-için-test-yazmak)
- [Commit mesajları ve git geçmişini temiz tutmak](#commit-mesajları-ve-git-geçmişini-temiz-tutmak)
- [Merge request açmak](#merge-request-açmak)
- [Hızlı referans](#hızlı-referans)

## İlk kurulum

```bash
cp examples/.env.dist examples/.env
# sadece functional test yapmayı planladığınız banka(lar) için bilgileri doldurun
docker compose up -d --build
docker compose exec -it web composer install
```

Unit testler, code style ve statik analiz için gerçek banka bilgisine
gerek yoktur — sadece functional testler gerçek (test) banka endpoint'lerine
istek atar.

Ayrıca git hook'larını da kurun — bkz. [Git hook'ları](#git-hookları).

## Altın kural: MR açmadan önce lokalde her şey yeşil olmalı

**CI'ın çalıştırdığı her kontrol, push etmeden önce kendi makinenizde de
geçmeli.** Bir code style uyarısını veya statik analiz hatasını lokalde
yakalamak saniyeler sürer; aynı şeyi CI push ettikten sonra yakalamak CI
turu + reviewer'ın dikkatine mal olur.

Lokal pipeline, sırasıyla:

```bash
composer cs-check     # 1. code style
composer phpstan      # 2. statik analiz
composer test         # 3. unit testler
```

Veya ilk üçünü bir arada çalıştırın:

```bash
composer do-checks     # cs-check + phpstan + phpstan-examples + test
```

Değişikliğiniz bir gateway'in request/response/crypt akışına dokunuyorsa,
push etmeden önce [functional test regresyon kontrolünü](#4-functional-testler-sadece-bir-gatewaye-dokunduysanız)
de çalıştırın.

## Lokal pipeline, detaylı

### 1. Code style (`cs-check` / `cs-fix`)

Proje PSR-12'yi `php-cs-fixer` ile takip eder, buna ek olarak bazı kurallar
vardır (`.php-cs-fixer.dist.php`) — örneğin class element sıralaması
(constant → property → constructor → method) ve dikey hizalı PHPDoc.

```bash
composer cs-check   # dry-run, ne değişeceğinin diff'ini gösterir
composer cs-fix      # değişiklikleri uygular
```

Formatlamayı elle yapmak yerine her zaman `cs-fix` çalıştırın — deterministiktir
ve review'da formatlama tartışmasını önler.

### 2. Statik analiz (`phpstan`)

Level 8, en sıkı preset. Hem `src/`'e hem de examples'a karşı çalıştırın:

```bash
composer phpstan
composer phpstan-examples   # sadece examples/'a dokunduysanız gerekli
```

Yeni kod `phpstan-baseline.neon`'a ekleme yapmadan geçmeli. Yeni bir hatayı
kaybolması için baseline'a eklemeyin — type sorununu asıl düzeltin. Baseline
sadece eski, önceden var olan hataları affetmek için var, yeni hataları
biriktirmek için değil.

### 3. Unit testler (`test`)

```bash
composer test
```

Geliştirirken daha dar çalıştırmalar:

```bash
# tek bir test dosyası
./vendor/bin/phpunit tests/Unit/Crypt/InterPosCryptTest.php

# herhangi bir yerdeki tek bir test metodu
./vendor/bin/phpunit --filter testVerifyHashReturnsFalseOnMismatch
```

### 4. Functional testler (sadece bir gateway'e dokunduysanız)

Functional testler gerçek banka test endpoint'lerine istek atar ve
`examples/.env`'de bilgi gerektirir. Her değişiklik için gerekli değildir,
ama bir `RequestDataMapper`, `ResponseDataMapper`, `Crypt` veya `Gateway`
sınıfını değiştirdiyseniz, değişiklikten önce ve sonra o banka için regresyon
kontrolü çalıştırın:

```bash
# değişiklikten önce (veya master'da)
./vendor/bin/phpunit --testsuite=functional --log-junit var/functional-test-results.xml
php bin/functional-test-check --update

# değişiklikten sonra
./vendor/bin/phpunit --testsuite=functional --log-junit var/functional-test-results.xml
php bin/functional-test-check InterPos   # class ismine göre filtre
```

İkinci çalıştırma, baseline'a göre yeni geçen veya yeni başarısız olan her
testi raporlar. Yeni başarısız olan bir test regresyondur — kasıtlı olarak
dokunmadığınız bir banka için bile olsa, push etmeden önce düzeltin.

## Git hook'ları

Proje, pipeline'ın bir kısmını otomatik çalıştırmak için `captainhook`
kullanır (Composer ile kurulur), böylece her adımı elle hatırlamanıza gerek
kalmaz:

| Hook | Çalıştırdığı |
|---|---|
| `pre-commit` | `composer cs-check` + `composer phpstan` |
| `pre-push` | `composer do-checks` (cs-check + phpstan + phpstan-examples + test) |

Hook'lar `composer install` ile otomatik aktif olmaz — bir kere elle kurmanız
gerekir:

```bash
vendor/bin/captainhook install
```

(Container içinde: `docker compose exec -it web vendor/bin/captainhook install`)

Bu, `captainhook.json`'daki konfigürasyona göre `.git/hooks/` altına gerçek
git hook'larını yazar. Repo'yu her yeni clone'ladığınızda bir kere
çalıştırmanız yeterlidir.

Hook'ları **bypass etmeyin** (`--no-verify`) — bir hook başarısız oluyorsa bu
gerçek bir sinyaldir, kontrolü atlamak yerine asıl sorunu düzeltin. Hook'lar
functional testleri çalıştırmaz — bunlar ekstra kurulum (banka bilgileri)
gerektirdiği için her commit'i bloklamaması adına elle çalıştırılır, yukarıdaki
adımlara bakın.

## Değişikliğiniz için test yazmak

Tam konvansiyonlar (dosya konumları, `@covers`, mocking kuralları, data
provider'lar, assertion stili) [`CLAUDE.md`](./CLAUDE.md#unit-testing)'de
anlatılıyor — bir test dosyası yazmadan önce o bölümü okuyun. Kısa özet:

- Unit test dosyaları `src/` altındaki yapıyı `tests/Unit/` altında aynen
  tekrarlar (`src/Crypt/InterPosCrypt.php` → `tests/Unit/Crypt/InterPosCryptTest.php`).
- Her erişilebilir branch bir test case ile kapsanmalı — eklediğiniz her yeni
  `if`/`else`, early `return` veya `switch` dalı, sadece happy path'i geçmek
  yerine yeni bir assertion veya yeni bir data provider satırı olarak
  görünmeli.
- `assertEquals()` değil `assertSame()` kullanın.
- Sadece interface/abstract bağımlılıkları mock'layın (`CryptInterface`,
  logger'lar vs.) — value object'leri (örn. account'ları) asla mock'lamayın,
  bunları `AccountFactory` ile oluşturun.
- Sıfırdan bir test dosyası başlatıyorsanız `/gen-test` slash komutu bu
  konvansiyonlara uygun bir iskelet oluşturur.

Bir gateway değişikliği için ayrıca, o banka için henüz yoksa ilgili
[zorunlu fail-case testlerini](./CLAUDE.md#required-fail-case-tests) de
functional test suite'ine ekleyin.

## Commit mesajları ve git geçmişini temiz tutmak

Bu repo [Conventional Commits](https://www.conventionalcommits.org/)
formatını takip eder. Son geçmiş örnek niteliğinde:

```
fix: add missing hash verification in 3DPay/3DHost callbacks for GarantiPos, InterPos, PayForPos
feat(testMode): give warning when test_mode config is set when needed
refactor(PosFactory): accept single-bank config slice instead of full config
tests(coverage): added more coverage.
chore(docs): update CHANGELOG.md
```

Değişikliğe uyan bir tip seçin (`fix`, `feat`, `refactor`, `tests`, `chore`,
`docs`), isteğe bağlı olarak dokunduğunuz class/alanı parantez içinde scope
olarak ekleyin, ve subject satırında *ne* değiştiğini anlatın. Subject
satırını kısa tutun; diff'ten anlaşılmayan bir sebep varsa body'ye yazın.

Git geçmişini temiz tutmaya özen gösterin:

- MR'ınızı mantıklı, tek bir amaca hizmet eden commit'lere bölün — "wip",
  "fix typo", "address review comments" gibi birbirini düzeltip duran
  commit'leri biriktirmek yerine, mümkünse `git commit --amend` veya
  `git rebase -i` ile birleştirip temizleyin.
- Review sonrası düzeltmeleri yeni, anlamlı commit'ler olarak ekleyin veya
  mevcut commit'e amend edip force-push yapın — ekip ile konuşulmuş bir
  tercih yoksa ikisi de kabul edilir, ama "fix review comment" gibi anlamsız
  commit yığınları bırakmayın.
- Birbirini bozan/düzelten commit'leri (örn. bir değişken ekleyip bir sonraki
  commit'te adını düzeltmek) ayrı commit'ler olarak bırakmak yerine
  birleştirin.
- `master`'ı merge etmek yerine, mümkünse branch'ınızı `master` üzerine
  rebase edin — bu, geçmişi daha okunabilir tutar ve gereksiz merge
  commit'lerini önler.

## Merge request açmak

MR açmadan önce:

- [ ] `composer cs-check` geçiyor
- [ ] `composer phpstan` geçiyor (examples/'a dokunduysanız `phpstan-examples` de)
- [ ] `composer test` geçiyor
- [ ] Bir gateway'in request/response/crypt akışına dokunduysanız functional
      regresyon kontrolü çalıştırıldı
- [ ] Değiştirdiğiniz davranış için yeni testler eklendi
- [ ] Commit mesajları yukarıdaki konvansiyona uyuyor, geçmiş temiz

MR açıklamasında ne değiştiğini ve *neden* değiştiğini yazın — diff zaten
neyin değiştiğini gösteriyor; reviewer'ın asıl ihtiyacı olan sebep, özellikle
bug fix'lerde (gözlemlenen hatalı davranış neydi, hangi input/koşul tetikliyordu).

## Hızlı referans

```bash
# ilk kurulum
cp examples/.env.dist examples/.env
docker compose up -d --build
docker compose exec -it web composer install
docker compose exec -it web vendor/bin/captainhook install

# her değişiklikten önce, push etmeden
docker compose exec -it web composer cs-check
docker compose exec -it web composer phpstan
docker compose exec -it web composer test

# sadece gateway değişiklikleri için
docker compose exec -it web ./vendor/bin/phpunit --testsuite=functional --log-junit var/functional-test-results.xml
docker compose exec -it web php bin/functional-test-check <BankName>

# style sorunlarını otomatik düzeltmek için
docker compose exec -it web composer cs-fix
```
