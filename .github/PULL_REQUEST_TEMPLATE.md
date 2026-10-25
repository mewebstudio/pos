## Açıklama

<!-- Ne değişti ve neden? Bir bug fix ise: hatalı davranış neydi, hangi input/koşulda tetikleniyordu? -->

## İlgili Issue

<!-- Varsa: Fixes #123 -->

## Değişiklik Tipi

- [ ] Bug fix
- [ ] Yeni özellik
- [ ] Refactor (davranış değişikliği yok)
- [ ] Dokümantasyon
- [ ] Diğer:

## Test

- [ ] Bu değişikliği **test ortamında** (banka test endpoint'leri ile) gerçek bir istek göndererek test ettim.
- [ ] Bu değişikliği **prod ortamında** gerçek bir istek göndererek test ettim.
- [ ] Bu değişiklik mevcut unit/functional testler tarafından yeterince kapsanıyor, ek olarak canlı bir istekle test edilmesine gerek yok.

Test edildiyse, hangi banka(lar)/gateway class(lar)ı ve hangi işlem tipi (NonSecure, 3DSecure, 3DPay, 3DHost, PreAuth/PostAuth, Cancel, Refund, Status, ...) ile test edildi?

<!-- örn. InterPos, 3DSecure ödeme + Cancel -->

## Checklist

- [ ] `composer cs-check` geçiyor
- [ ] `composer phpstan` geçiyor (examples/'a dokunduysam `phpstan-examples` de)
- [ ] `composer test` geçiyor
- [ ] Bir gateway'in request/response/crypt akışına dokunduysam functional regresyon kontrolünü (`bin/functional-test-check`) çalıştırdım
- [ ] Değiştirdiğim davranış için yeni/güncellenmiş testler ekledim
- [ ] Commit mesajları [Conventional Commits](https://www.conventionalcommits.org/) formatına uyuyor, geçmiş temiz

Detaylar için: [CONTRIBUTING.md](../CONTRIBUTING.md)
