# Platform Subscription Codebase Mapping

Dokumen ini memetakan desain database subscription-aware ke codebase yang sekarang.

Fokus utama:

- menunjukkan file mana yang saat ini jadi sumber baca/tulis subscription
- menentukan layer baru yang perlu dibuat
- menentukan file lama mana yang harus dipangkas tanggung jawabnya
- memberi urutan refactor yang aman tanpa merusak flow existing

---

## 1. Ringkasan Arsitektur Existing

Saat ini arsitektur subscription tersebar di 4 titik utama:

- `CentralSetting`
  jadi katalog modul, katalog package, default package, billing component, dan helper invoice
- `Tenant`
  jadi pembaca metadata subscription tenant dari `tenants.data`
- `SuperAdminTenantController`
  jadi pusat assignment package, update subscription, generate invoice, dan dashboard billing
- `AppServiceProvider`
  jadi titik injeksi modul runtime ke sistem `nwidart/modules`

Artinya, refactor nanti tidak cukup hanya bikin migration.

Refactor harus menyentuh:

- model
- service layer
- controller central
- middleware runtime tenant
- view composer / provider
- form central panel

---

## 2. Peta Titik Baca/Tulis Saat Ini

## 2.1 `CentralSetting` sebagai service raksasa

File utama:

- [CentralSetting.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/app/Models/CentralSetting.php)

Tanggung jawab saat ini:

- `moduleCatalog()`
- `moduleCatalogForView()`
- `activeModules()`
- `setActiveModules()`
- `runtimeEnabledModules()`
- `packageCatalog()`
- `defaultPackageCatalog()`
- `setPackageCatalog()`
- `defaultPackageCode()`
- `setDefaultPackageCode()`
- `findPackage()`
- `packageCatalogForView()`
- `packageBillingEstimate()`
- `packageBillingInvoice()`

Masalahnya:

- model setting jadi terlalu banyak tahu soal domain subscription
- katalog modul masih hardcoded
- package masih JSON
- helper billing package bercampur dengan key-value settings umum

Kesimpulan:

- `CentralSetting` perlu dipersempit kembali jadi key-value settings umum
- logic subscription harus dipindah ke service / model relasional baru

## 2.2 `Tenant` membaca subscription dari `tenants.data`

File utama:

- [Tenant.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/app/Models/Tenant.php)

Method yang sekarang membaca metadata JSON:

- `packageCode()`
- `subscriptionStatus()`
- `subscriptionStartsAt()`
- `subscriptionExpiresAt()`
- `subscriptionGraceUntil()`
- `billingUsageSnapshot()`
- `billingInvoices()`
- `latestBillingInvoice()`
- `oldestCollectibleInvoice()`
- `hasAccessBlock()`
- `accessBlockMeta()`

Masalahnya:

- model tenant bercampur dengan domain subscription dan invoice
- semua ini masih bergantung pada struktur `tenants.data`
- sulit dipakai ulang saat data dipindah ke tabel relasional

Kesimpulan:

- `Tenant` nanti sebaiknya baca lewat relation seperti `subscription()`
- method helper boleh tetap ada, tapi sumber datanya pindah ke model subscription baru

## 2.3 `SuperAdminTenantController` jadi simpul semuanya

File utama:

- [SuperAdminTenantController.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/app/Http/Controllers/Central/SuperAdminTenantController.php)

Area yang paling terdampak:

- list tenant + package summary
- billing dashboard
- assign package tenant
- update billing tenant
- generate invoice tenant
- query invoice dari tenant metadata

Contoh method yang paling terdampak:

- `index()`
- `billingDashboard()`
- `assignPackage()`
- `updateBilling()`
- `generateInvoice()`
- `tenantPackageCode()`
- `tenantBillingSummary()`
- `generateDueInvoiceRecords()`
- `dueInvoicePeriods()`

Masalahnya:

- controller terlalu banyak business logic
- controller tahu detail package catalog JSON dan invoice storage sekaligus
- perubahan schema subscription akan memukul file ini paling keras

Kesimpulan:

- controller ini harus dipecah ke service layer
- controller hanya jadi orchestrator request/response

## 2.4 `PackageSettingsController` masih full JSON catalog

File utama:

- [PackageSettingsController.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/app/Http/Controllers/Central/PackageSettingsController.php)

File view terkait:

- [packages.blade.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/resources/views/central/packages.blade.php)
- [package-form.blade.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/resources/views/central/package-form.blade.php)
- [package-form-fields.blade.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/resources/views/central/partials/package-form-fields.blade.php)

Yang terjadi sekarang:

- package dibaca dari `CentralSetting::packageCatalog()`
- module selection dibaca dari `CentralSetting::moduleCatalogForView()`
- submit form menulis balik satu blob JSON package

Kesimpulan:

- controller ini nanti harus membaca dari model relasional package
- view bisa tetap dipakai sebagian besar, tapi source datanya diganti

## 2.5 `PlatformSettingsController` masih pegang active modules platform

File utama:

- [PlatformSettingsController.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/app/Http/Controllers/Central/PlatformSettingsController.php)

Titik yang terdampak:

- `edit()`
- `update()`

Saat ini masih pakai:

- `CentralSetting::activeModules()`
- `CentralSetting::moduleCatalogForView()`
- `CentralSetting::syncActiveModulesForPlatform()`
- `CentralSetting::setActiveModules()`

Masalah:

- active module platform masih diperlakukan seperti setting tunggal
- padahal ke depan harus dibaca dari `platform_module_catalog`

Kesimpulan:

- halaman settings tetap bisa jadi UI pengelola module catalog platform
- tapi backing datanya harus pindah ke tabel modul

## 2.6 `AppServiceProvider` menginjeksi modul runtime tenant

File utama:

- [AppServiceProvider.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/app/Providers/AppServiceProvider.php)

Titik penting:

- event listener `TenancyBootstrapped`
- `CentralSetting::runtimeEnabledModules($saasType)`
- `config(['modules.statuses' => $statuses])`

Masalah:

- runtime tenant masih ditentukan dari active modules platform, bukan dari entitlement tenant
- tenant A dan tenant B pada platform yang sama akan terlihat identik, padahal nanti bisa beda modul aktif

Kesimpulan:

- titik ini nanti harus membaca `tenant_module_states`
- ini adalah salah satu titik runtime paling penting untuk refactor

## 2.7 `EnsureTenantIsActive` masih baca block dari `Tenant`

File utama:

- [EnsureTenantIsActive.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/app/Http/Middleware/EnsureTenantIsActive.php)

Dependensi saat ini:

- `tenant()->hasAccessBlock()`
- `tenant()->accessBlockMeta()`

Kesimpulan:

- middleware tetap dipakai
- tapi source block logic nantinya pindah ke relation subscription tenant

## 2.8 `TenantProvisioningService` assign package default saat create tenant

File utama:

- [TenantProvisioningService.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/app/Services/Central/TenantProvisioningService.php)

Titik penting:

- `CentralSetting::defaultPackageCode($saasType)`
- `package_code`
- `package_assigned_at`

Kesimpulan:

- provisioning nanti harus sekaligus membuat record `tenant_subscriptions`
- dan mengisi `tenant_module_states` default

---

## 3. Layer Baru yang Direkomendasikan

Supaya refactor tidak lagi bertumpu di `CentralSetting`, layer baru yang disarankan:

## 3.1 Model baru

Disarankan buat model central baru:

- `App\Models\Central\PlatformModule`
- `App\Models\Central\SubscriptionPackage`
- `App\Models\Central\SubscriptionPackageModule`
- `App\Models\Central\SubscriptionPackageFeature`
- `App\Models\Central\SubscriptionPackageLimit`
- `App\Models\Central\TenantSubscription`
- `App\Models\Central\TenantModuleState`
- `App\Models\Central\TenantSubscriptionAddon`
- opsional belakangan:
  - `App\Models\Central\TenantSubscriptionInvoice`
  - `App\Models\Central\TenantSubscriptionInvoiceLine`

Catatan:

- namespace `Central\...` bikin boundary lebih jelas
- semua model ini harus pakai central connection

## 3.2 Service baru

Disarankan minimal ada service ini:

- `PlatformModuleCatalogService`
- `SubscriptionPackageService`
- `TenantSubscriptionService`
- `TenantModuleActivationService`
- `TenantEntitlementResolver`
- opsional belakangan:
  - `TenantSubscriptionInvoiceService`

Tanggung jawab ringkas:

- `PlatformModuleCatalogService`
  CRUD katalog modul platform
- `SubscriptionPackageService`
  CRUD package, features, limits, modules
- `TenantSubscriptionService`
  assign package, ubah status, hitung expiry, summary tenant
- `TenantModuleActivationService`
  sinkron modul tenant dari package + addon + toggle tenant
- `TenantEntitlementResolver`
  jawaban runtime: modul ini `candidate`, `allowed`, `enabled`, atau `blocked`

## 3.3 Presenter / DTO ringan

Supaya view tidak terlalu bergantung pada model mentah, boleh tambah DTO/presenter:

- `PackageViewData`
- `TenantSubscriptionSummary`
- `TenantModuleRuntimeMap`

Ini opsional, tapi bakal bantu banget saat transisi.

---

## 4. Mapping Model Lama ke Model Baru

## 4.1 Dari `CentralSetting::moduleCatalog()` ke `PlatformModule`

Sekarang:

- hardcoded array di `CentralSetting`

Target:

- query `PlatformModule::query()->wherePlatformType(...)`

Dampak file:

- [CentralSetting.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/app/Models/CentralSetting.php)
- [PlatformSettingsController.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/app/Http/Controllers/Central/PlatformSettingsController.php)
- [PackageSettingsController.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/app/Http/Controllers/Central/PackageSettingsController.php)
- [AppServiceProvider.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/app/Providers/AppServiceProvider.php)

## 4.2 Dari `CentralSetting::packageCatalog()` ke `SubscriptionPackage`

Sekarang:

- package satu platform disimpan blob JSON

Target:

- `SubscriptionPackage` + relations:
  - modules
  - features
  - limits

Dampak file:

- [PackageSettingsController.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/app/Http/Controllers/Central/PackageSettingsController.php)
- [SuperAdminTenantController.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/app/Http/Controllers/Central/SuperAdminTenantController.php)
- [TenantProvisioningService.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/app/Services/Central/TenantProvisioningService.php)

## 4.3 Dari `Tenant::packageCode()` ke relation `subscription`

Sekarang:

- package code diambil dari `tenants.data`

Target:

- `$tenant->subscription?->package`

Strategy:

- method `packageCode()` boleh tetap ada sementara
- tapi internalnya baca relation subscription dulu
- fallback ke metadata lama hanya untuk masa transisi

## 4.4 Dari `CentralSetting::runtimeEnabledModules()` ke `TenantModuleState`

Sekarang:

- modul runtime tenant diambil dari active modules platform

Target:

- `TenantEntitlementResolver::enabledModuleNames($tenant)`

Ini perubahan paling penting untuk kasus:

- hotel A tanpa housekeeping
- hotel B dengan housekeeping
- hotel C tanpa OTA

---

## 5. Mapping Controller ke Service Baru

## 5.1 `PackageSettingsController`

Sekarang:

- controller langsung membaca `CentralSetting`
- controller langsung menyusun payload package

Target:

- controller baca/tulis lewat `SubscriptionPackageService`

Mapping method:

- `index()` -> `SubscriptionPackageService::catalogForView($platformType)`
- `create()` -> `SubscriptionPackageService::packageFormSkeleton($platformType)`
- `edit()` -> `SubscriptionPackageService::findForEdit($packageCode, $platformType)`
- `store()` -> `SubscriptionPackageService::create($platformType, $payload)`
- `update()` -> `SubscriptionPackageService::update($packageCode, $platformType, $payload)`
- `setDefault()` -> `SubscriptionPackageService::markAsDefault($packageCode, $platformType)`
- `destroy()` -> `SubscriptionPackageService::delete($packageCode, $platformType)`

## 5.2 `PlatformSettingsController`

Sekarang:

- page settings platform sekaligus mengelola active modules global

Target:

- `PlatformModuleCatalogService`

Mapping method:

- `edit()` -> ambil module catalog dari tabel
- `update()` -> sinkron master module availability per platform

Catatan:

- setting branding/payment/notification tetap boleh di `CentralSetting`
- yang dipindah hanya urusan catalog modul platform

## 5.3 `SuperAdminTenantController`

Ini file yang paling perlu dipisah.

Subdomain yang sebaiknya dikeluarkan:

- package assignment
- subscription status update
- billing summary subscription
- invoice generation subscription
- runtime module summary tenant

Mapping service:

- `assignPackage()` -> `TenantSubscriptionService::assignPackage($tenant, $packageCode)`
- `updateBilling()` -> `TenantSubscriptionService::updateLifecycle($tenant, $payload)`
- `tenantPackageCode()` -> pindah ke `TenantSubscriptionService`
- `tenantBillingSummary()` -> pindah ke `TenantSubscriptionService`
- `generateDueInvoiceRecords()` -> nanti pindah ke `TenantSubscriptionInvoiceService`

## 5.4 `TenantProvisioningService`

Sekarang:

- assign `package_code` langsung ke tenant metadata

Target flow:

1. create tenant
2. buat `tenant_subscriptions`
3. resolve package default by platform
4. seed `tenant_module_states`

Service tambahan yang dipanggil:

- `TenantSubscriptionService`
- `TenantModuleActivationService`

---

## 6. Mapping Runtime Tenant

## 6.1 Runtime module gating

Titik runtime sekarang:

- [AppServiceProvider.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/app/Providers/AppServiceProvider.php)

Sekarang logic-nya:

1. ambil `saas_type`
2. ambil `CentralSetting::runtimeEnabledModules($saasType)`
3. aktifkan status modul di `config('modules.statuses')`

Target logic:

1. ambil tenant aktif
2. baca `tenant_subscriptions`
3. baca `tenant_module_states`
4. resolve daftar modul `enabled`
5. set `config('modules.statuses')`

Service yang ideal:

- `TenantEntitlementResolver::enabledModuleNames(Tenant $tenant): array`

## 6.2 Runtime access block

Titik sekarang:

- [EnsureTenantIsActive.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/app/Http/Middleware/EnsureTenantIsActive.php)

Target:

- middleware tetap memanggil `$tenant->hasAccessBlock()`
- tapi method itu nanti membaca:
  - relation `subscription`
  - invoice subscription relasional

Jadi public interface `Tenant` boleh dipertahankan, source datanya yang berubah.

## 6.3 Menu dan widget tenant

Walaupun belum seluruhnya dibuat, arah refactor-nya jelas:

- sidebar / widget tenant tidak lagi hanya bergantung pada platform type
- tapi bergantung pada:
  - `saas_type`
  - `allowed modules`
  - `enabled modules`

Target helper yang enak:

- `tenant_has_module('hotel_housekeeping')`
- atau service container `app(TenantEntitlementResolver::class)->isEnabled($tenant, 'hotel_housekeeping')`

---

## 7. Mapping View yang Terdampak

## 7.1 Central settings page

File:

- [settings.blade.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/resources/views/central/settings.blade.php)

Yang berubah:

- source `activeModules`
- source `moduleCatalog`

Yang tetap:

- payment settings
- notification settings
- automation settings
- branding experience

## 7.2 Package pages

File:

- [packages.blade.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/resources/views/central/packages.blade.php)
- [package-form.blade.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/resources/views/central/package-form.blade.php)
- [package-form-fields.blade.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/resources/views/central/partials/package-form-fields.blade.php)

Yang berubah:

- data package
- data module catalog
- data features
- default package logic

Yang kemungkinan masih bisa dipakai:

- layout form
- tab structure
- UI input limits/features/modules

## 7.3 Tenant detail page

File:

- [tenant-detail.blade.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/resources/views/central/tenant-detail.blade.php)

Yang berubah:

- source package tenant
- source subscription status
- source billing usage
- nanti source module runtime tenant

Yang bagus ditambah nanti:

- panel `Allowed Modules`
- panel `Enabled Modules`
- panel `Blocked Modules`

## 7.4 Tenant list dan billing dashboard

File:

- [tenants.blade.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/resources/views/central/tenants.blade.php)
- [billing-dashboard.blade.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/resources/views/central/billing-dashboard.blade.php)

Yang berubah:

- source billing summary
- source package label
- source subscription status
- nanti bisa ditambah ringkasan active addon / active module

---

## 8. Compatibility Layer yang Disarankan

Supaya refactor aman, perlu compatibility layer sementara.

## 8.1 Tetap pertahankan method lama di `Tenant`

Contoh:

- `packageCode()`
- `subscriptionStatus()`
- `billingInvoices()`

Tapi urutan bacanya diubah:

1. baca relation baru dulu
2. kalau belum ada data relasional, fallback ke metadata lama

Ini bikin:

- controller lama tidak langsung rusak
- migrasi bisa bertahap

## 8.2 `CentralSetting` jadi facade transisi

Selama masa transisi, `CentralSetting` boleh tetap expose method lama seperti:

- `findPackage()`
- `packageCatalogForView()`

Tapi internalnya sebaiknya mulai delegasi ke service relasional baru.

Jadi:

- controller lama tetap jalan
- implementasi bawahnya pelan-pelan pindah ke tabel

## 8.3 Invoice subscription dipindah paling akhir

Jangan refactor invoice di awal.

Alasannya:

- paling banyak titik sentuh
- ada integrasi manual transfer dan public invoice
- risiko regression lebih besar

---

## 9. Urutan Refactor File yang Paling Aman

Urutan yang gue rekomendasikan:

### Step 1

Buat migration + model:

- `PlatformModule`
- `SubscriptionPackage`
- `SubscriptionPackageModule`
- `TenantSubscription`
- `TenantModuleState`

### Step 2

Buat service baru:

- `PlatformModuleCatalogService`
- `SubscriptionPackageService`
- `TenantSubscriptionService`
- `TenantModuleActivationService`
- `TenantEntitlementResolver`

### Step 3

Ubah `PackageSettingsController` supaya baca tabel relasional.

Alasan:

- impact-nya besar tapi masih aman
- belum menyentuh runtime tenant langsung

### Step 4

Ubah `PlatformSettingsController` untuk module catalog platform.

### Step 5

Ubah `TenantProvisioningService` agar create subscription relasional saat tenant dibuat.

### Step 6

Ubah `SuperAdminTenantController`:

- assign package
- subscription summary
- module states

### Step 7

Ubah `AppServiceProvider`:

- runtime module gating baca `tenant_module_states`

### Step 8

Baru migrasikan invoice subscription relasional.

---

## 10. Risiko dan Titik Waspada

## 10.1 Platform module vs installed modules

Sekarang `AppServiceProvider` masih merge:

- installed modules fisik dari `app('modules')->all()`
- enabled modules dari setting

Ke depan harus dibedakan:

- modul fisik terpasang di codebase
- modul secara bisnis diizinkan tenant

Artinya runtime final harus cek dua hal:

1. modul ada secara teknis
2. modul enabled secara entitlement

## 10.2 Central package assignment validation

Sekarang validasi `assignPackage()` masih:

- `Rule::in(array_keys(CentralSetting::packageCatalog()))`

Ke depan validasi harus:

- package itu ada
- package platform-nya cocok dengan tenant `saas_type`
- package aktif

## 10.3 Tenant saas switch

Method:

- `switchSaas()` di [SuperAdminTenantController.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/app/Http/Controllers/Central/SuperAdminTenantController.php)

Titik rawan:

- kalau tenant ganti `saas_type`, package lama bisa jadi tidak valid
- module states lama bisa jadi nyasar

Jadi nanti saat switch platform harus ada flow:

1. reset / remap package
2. recalculate module states
3. audit log perubahan

## 10.4 Backfill existing tenant data

Karena data sekarang ada di metadata tenant:

- package code
- status subscription
- billing usage
- invoice

Maka nanti saat transisi:

- jangan ubah reader sebelum backfill selesai
- perlu command backfill dan sanity checker

---

## 11. Target Bentuk Codebase Setelah Refactor Awal

Minimal bentuk yang sehat setelah fase awal:

```text
app/
  Models/
    Central/
      PlatformModule.php
      SubscriptionPackage.php
      SubscriptionPackageModule.php
      SubscriptionPackageFeature.php
      SubscriptionPackageLimit.php
      TenantSubscription.php
      TenantModuleState.php
  Services/
    Central/
      PlatformModuleCatalogService.php
      SubscriptionPackageService.php
      TenantSubscriptionService.php
      TenantModuleActivationService.php
      TenantEntitlementResolver.php
```

Controller yang paling ramping setelah refactor:

- `PackageSettingsController`
  hanya form orchestration
- `PlatformSettingsController`
  hanya orchestration setting + module catalog
- `SuperAdminTenantController`
  hanya orchestration tenant admin

---

## 12. Rekomendasi Final

Kalau mengikuti kondisi codebase sekarang, strategi terbaik adalah:

### Jangan mulai dari runtime dulu

Karena runtime tenant paling sensitif.

### Mulai dari catalog dan package layer

Karena itu fondasi:

- `platform_module_catalog`
- `subscription_packages`
- `subscription_package_modules`

### Lalu pindah tenant subscription

Setelah package layer beres:

- `tenant_subscriptions`
- `tenant_module_states`

### Baru terakhir sentuh invoice dan public payment flow

Karena area itu paling banyak dependensi.

---

## 13. Ringkasan Eksekusi

Mapping paling praktis untuk codebase ini adalah:

1. pecah `CentralSetting` dari domain subscription
2. pindahkan package catalog ke model relasional
3. pindahkan tenant subscription ke tabel khusus
4. pindahkan runtime module gating ke `TenantEntitlementResolver`
5. pertahankan compatibility layer di `Tenant` dan `CentralSetting`
6. migrasikan invoice subscription paling akhir

Pendekatan ini paling aman karena:

- tidak memaksa rewrite besar sekaligus
- tetap nyambung dengan flow existing
- bisa diuji per layer
- siap untuk model package modular lintas platform
