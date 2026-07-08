# Platform Subscription Database Design

Dokumen ini menurunkan blueprint modularisasi menjadi desain database yang siap dipakai untuk implementasi bertahap.

Fokus dokumen ini:

- memecah katalog modul dari JSON menjadi tabel relasional
- memecah package dan entitlement dari `central_settings`
- merapikan aktivasi modul per tenant
- menjaga kompatibilitas dengan arsitektur multi-platform dan multi-tenant yang sudah ada

---

## 1. Kondisi Saat Ini

Saat ini fondasi subscription sudah hidup, tapi sebagian besar masih tersimpan dalam bentuk JSON.

### 1.1 Yang sudah ada

- `central_settings`
  menyimpan `platform_saas_type`, `active_modules`, `package_catalog`, `default_package_code`, dan setting lain
- `tenants`
  menyimpan `id`, `name`, `saas_type`, lalu metadata billing/subscription lain ada di kolom `data` JSON
- `Tenant` model
  sudah membaca:
  - `package_code`
  - `subscription_status`
  - `subscription_starts_at`
  - `subscription_expires_at`
  - `subscription_grace_until`
  - `billing_usage`
  - `billing_invoices`

### 1.2 Kelemahan kondisi sekarang

- `module catalog` masih hardcoded di [CentralSetting.php](file:///www/wwwroot/aircloud.biz.id/laramodsaas/app/Models/CentralSetting.php)
- `package catalog` masih JSON di `central_settings`
- relasi `package -> modules` belum relasional
- relasi `package -> features` belum relasional
- aktivasi modul tenant belum punya tabel khusus
- invoice subscription tenant masih menumpuk di `tenants.data`
- sulit query analitik seperti:
  - package mana paling banyak dipakai
  - modul mana paling sering diaktifkan
  - tenant hotel mana yang punya entitlement `OTA`

### 1.3 Target desain

Desain baru harus bisa menjawab:

- platform ini punya modul kandidat apa saja?
- package ini mengizinkan modul apa saja?
- tenant ini aktif di package mana?
- tenant ini benar-benar menyalakan modul apa?
- addon apa yang dibeli tenant?
- fitur package apa yang aktif?

---

## 2. Prinsip Desain

### 2.1 Semua yang menentukan entitlement bisnis harus ada di central DB

Yang harus tinggal di central:

- katalog platform
- katalog modul
- package
- entitlement package
- addon purchase
- status subscription tenant
- aktivasi modul tenant

Alasannya:

- ini adalah sumber kebenaran bisnis/commercial
- tenant DB tidak boleh jadi sumber truth untuk hak akses subscription

### 2.2 Tenant DB fokus ke data operasional domain

Contoh:

- `tirta` tenant DB menyimpan pelanggan, sambungan, meter, tagihan air
- `hotel` tenant DB menyimpan reservasi, kamar, housekeeping

Tenant DB tidak perlu menyimpan definisi package pusat sebagai data utama.

### 2.3 JSON lama boleh dipertahankan sementara sebagai compatibility layer

Migrasi paling aman:

1. tabel relasional dibuat dulu
2. data lama tetap dibaca
3. aplikasi mulai menulis ke tabel baru
4. setelah stabil, pembacaan JSON lama dipensiunkan

---

## 3. Boundary Central vs Tenant

### 3.1 Central DB

Tabel yang direkomendasikan di central:

- `platform_module_catalog`
- `subscription_packages`
- `subscription_package_modules`
- `subscription_package_features`
- `subscription_package_limits`
- `tenant_subscriptions`
- `tenant_module_states`
- `tenant_subscription_addons`
- `tenant_subscription_invoices`
- `tenant_subscription_invoice_lines`

### 3.2 Tenant DB

Tetap menyimpan data operasional vertical:

- `tirta`: pelanggan, sambungan, meter, tarif, tiket gangguan
- `hotel`: reservasi, kamar, housekeeping, pembayaran operasional
- `resto`: order, kasir, inventory
- `netbilling`: pelanggan, provisioning, billing, gangguan

### 3.3 Central settings yang masih layak tetap jadi key-value

Masih wajar tetap di `central_settings`:

- branding pusat
- payment method global
- notification channels
- automation rules
- template pesan global

Yang sebaiknya dikeluarkan dari `central_settings`:

- `active_modules`
- `package_catalog`
- `default_package_code`

---

## 4. Tabel Central yang Direkomendasikan

## 4.1 `platform_module_catalog`

Tujuan:

- jadi master semua modul kandidat lintas platform
- menggantikan `CentralSetting::moduleCatalog()`

Kolom yang direkomendasikan:

| Kolom | Tipe | Catatan |
| --- | --- | --- |
| `id` | bigint / uuid | primary key |
| `module_code` | string unique | contoh: `hotel_housekeeping`, `tirta_billing` |
| `module_name` | string | contoh: `HotelHousekeeping` |
| `platform_type` | string indexed | `hotel`, `tirta`, `resto`, `netbilling`, `universal` |
| `domain_group` | string nullable | contoh: `operations`, `billing`, `support`, `settings` |
| `label` | string | label UI |
| `description` | text nullable | deskripsi modul |
| `is_required` | boolean | modul wajib platform |
| `is_default_enabled` | boolean | default on saat tenant dibuat jika diizinkan |
| `is_addon` | boolean | cocok dijual sebagai addon |
| `subscription_visible` | boolean | tampil di matrix package |
| `depends_on` | json nullable | daftar `module_code` dependency |
| `sort_order` | integer | urutan tampil |
| `is_active` | boolean | master enable/disable dari pusat |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Constraint yang disarankan:

- unique `module_code`
- index `platform_type`, `is_active`

Catatan:

- `depends_on` boleh tetap JSON di tahap awal supaya implementasinya cepat
- kalau nanti dependency makin kompleks, bisa dipisah ke tabel `platform_module_dependencies`

## 4.2 `subscription_packages`

Tujuan:

- menggantikan `package_catalog` JSON
- menyimpan identitas utama package

Kolom yang direkomendasikan:

| Kolom | Tipe | Catatan |
| --- | --- | --- |
| `id` | bigint / uuid | primary key |
| `package_code` | string unique | contoh: `hotel-basic`, `tirta-operasional` |
| `platform_type` | string indexed | package spesifik per platform |
| `label` | string | nama package |
| `description` | text nullable | deskripsi |
| `billing_cycle` | string | `monthly`, `quarterly`, `yearly` |
| `base_price` | bigint | harga dasar |
| `currency` | string default `IDR` | mata uang |
| `is_enabled` | boolean | package aktif |
| `is_highlighted` | boolean | package unggulan |
| `is_default` | boolean | default untuk tenant baru platform itu |
| `sort_order` | integer | urutan |
| `grace_days` | integer default 3 | grace billing subscription |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Constraint:

- unique `package_code`
- unique parsial idealnya: satu `platform_type` hanya satu `is_default = true`

## 4.3 `subscription_package_modules`

Tujuan:

- menyimpan entitlement modul per package

Kolom:

| Kolom | Tipe | Catatan |
| --- | --- | --- |
| `id` | bigint | primary key |
| `package_id` | foreign key | ke `subscription_packages` |
| `module_id` | foreign key | ke `platform_module_catalog` |
| `access_mode` | string | `included`, `optional`, `addon_only` |
| `is_enabled_by_default` | boolean | default runtime tenant |
| `notes` | string nullable | catatan internal |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Constraint:

- unique `package_id + module_id`

Catatan:

- `included` artinya package langsung mengizinkan modul
- `optional` artinya boleh dipakai tenant tapi tidak harus aktif
- `addon_only` artinya butuh pembelian addon agar allowed

## 4.4 `subscription_package_features`

Tujuan:

- menggantikan `features` JSON di package
- menyimpan feature flags non-module

Kolom:

| Kolom | Tipe | Catatan |
| --- | --- | --- |
| `id` | bigint | primary key |
| `package_id` | foreign key | ke `subscription_packages` |
| `feature_code` | string | contoh: `custom_domain`, `api_access` |
| `is_enabled` | boolean | status feature |
| `config` | json nullable | config tambahan jika perlu |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Constraint:

- unique `package_id + feature_code`

Catatan:

- kalau nanti feature catalog mau dirapikan, bisa tambah master `subscription_feature_catalog`
- untuk tahap awal, `feature_code` string sudah cukup

## 4.5 `subscription_package_limits`

Tujuan:

- memecah `limits` JSON package

Kolom:

| Kolom | Tipe | Catatan |
| --- | --- | --- |
| `id` | bigint | primary key |
| `package_id` | foreign key | ke `subscription_packages` |
| `limit_code` | string | contoh: `max_admin_users`, `max_customers` |
| `limit_value` | bigint nullable | null = unlimited |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Constraint:

- unique `package_id + limit_code`

Catatan:

- lebih fleksibel daripada kolom fix
- platform lain bisa tambah limit baru tanpa ubah schema

## 4.6 `tenant_subscriptions`

Tujuan:

- memindahkan metadata subscription dari `tenants.data`
- jadi sumber truth status subscription tenant

Kolom:

| Kolom | Tipe | Catatan |
| --- | --- | --- |
| `id` | bigint / uuid | primary key |
| `tenant_id` | string indexed | FK logis ke `tenants.id` |
| `platform_type` | string indexed | redundansi terkontrol untuk query cepat |
| `package_id` | foreign key nullable | package aktif tenant |
| `package_code_snapshot` | string | snapshot saat assign |
| `status` | string | `trial`, `active`, `grace`, `expired`, `suspended` |
| `starts_at` | timestamp nullable | awal langganan |
| `expires_at` | timestamp nullable | akhir langganan |
| `grace_until` | timestamp nullable | akhir grace |
| `assigned_at` | timestamp nullable | waktu package dipasang |
| `assigned_by` | foreign key nullable | user pusat yang assign |
| `billing_usage_snapshot` | json nullable | snapshot cepat jika masih dibutuhkan |
| `meta` | json nullable | ruang transisi |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Constraint:

- unique `tenant_id`

Catatan:

- satu tenant satu subscription aktif utama
- riwayat perubahan package nanti bisa dipisah ke tabel history jika diperlukan

## 4.7 `tenant_module_states`

Tujuan:

- menyimpan modul runtime tenant yang benar-benar aktif atau tidak
- inilah tabel kunci untuk menu gating

Kolom:

| Kolom | Tipe | Catatan |
| --- | --- | --- |
| `id` | bigint | primary key |
| `tenant_id` | string indexed | tenant |
| `module_id` | foreign key | referensi ke katalog modul |
| `status` | string | `enabled`, `disabled`, `blocked` |
| `enabled_source` | string | `required`, `package_default`, `tenant_toggle`, `addon`, `system` |
| `reason_code` | string nullable | contoh: `not_allowed`, `dependency_missing`, `addon_missing` |
| `is_allowed` | boolean | hasil entitlement package/addon |
| `enabled_at` | timestamp nullable | kapan aktif |
| `disabled_at` | timestamp nullable | kapan nonaktif |
| `meta` | json nullable | catatan teknis |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Constraint:

- unique `tenant_id + module_id`

Catatan:

- tabel ini menyimpan hasil final runtime
- route/menu cukup baca `is_allowed = true` dan `status = enabled`

## 4.8 `tenant_subscription_addons`

Tujuan:

- mencatat addon yang dibeli tenant

Kolom:

| Kolom | Tipe | Catatan |
| --- | --- | --- |
| `id` | bigint | primary key |
| `tenant_id` | string indexed | tenant |
| `module_id` | foreign key | modul addon |
| `status` | string | `active`, `expired`, `cancelled` |
| `billing_cycle` | string | jika addon berbayar berkala |
| `price` | bigint | harga addon |
| `starts_at` | timestamp nullable | mulai addon |
| `expires_at` | timestamp nullable | akhir addon |
| `purchased_at` | timestamp nullable | waktu beli |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Constraint:

- unique bisa `tenant_id + module_id + status(active)` secara logis

## 4.9 `tenant_subscription_invoices`

Tujuan:

- memindahkan `billing_invoices` dari `tenants.data`
- mendukung query dan rekonsiliasi yang lebih rapi

Kolom:

| Kolom | Tipe | Catatan |
| --- | --- | --- |
| `id` | bigint / uuid | primary key |
| `tenant_id` | string indexed | tenant |
| `invoice_number` | string unique | nomor invoice subscription |
| `period_key` | string | contoh `2026-07` |
| `period_label` | string | label periode |
| `package_id` | foreign key nullable | package saat invoice dibuat |
| `package_code_snapshot` | string | snapshot package |
| `status` | string | `draft`, `issued`, `paid`, `overdue`, `void` |
| `currency` | string default `IDR` | mata uang |
| `setup_fee_total` | bigint | total setup fee |
| `monthly_total` | bigint | total recurring |
| `invoice_total` | bigint | grand total |
| `issued_at` | timestamp nullable | tanggal terbit |
| `due_at` | timestamp nullable | jatuh tempo |
| `paid_at` | timestamp nullable | tanggal bayar |
| `payment_meta` | json nullable | qris/manual transfer/meta lain |
| `usage_snapshot` | json nullable | snapshot usage saat invoice diterbitkan |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

## 4.10 `tenant_subscription_invoice_lines`

Tujuan:

- menyimpan detail komponen invoice subscription

Kolom:

| Kolom | Tipe | Catatan |
| --- | --- | --- |
| `id` | bigint | primary key |
| `invoice_id` | foreign key | ke `tenant_subscription_invoices` |
| `line_code` | string | contoh `monthly_base`, `per_customer` |
| `label` | string | label line |
| `kind` | string | `flat`, `fixed_usage`, `percentage_usage` |
| `quantity` | decimal / bigint | qty |
| `amount` | bigint | nominal per unit |
| `rate` | decimal nullable | khusus percentage |
| `line_total` | bigint | total line |
| `meta` | json nullable | snapshot tambahan |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

---

## 5. Tabel Tambahan Opsional

## 5.1 `platform_module_dependencies`

Pakai tabel ini kalau dependency modul ingin dinormalisasi.

| Kolom | Tipe | Catatan |
| --- | --- | --- |
| `id` | bigint | primary key |
| `module_id` | foreign key | modul target |
| `depends_on_module_id` | foreign key | modul dependency |

Kalau fase awal masih ingin cepat, cukup pakai kolom JSON `depends_on`.

## 5.2 `tenant_subscription_histories`

Pakai kalau ingin audit riwayat package tenant.

| Kolom | Tipe | Catatan |
| --- | --- | --- |
| `id` | bigint | primary key |
| `tenant_id` | string indexed | tenant |
| `old_package_id` | foreign key nullable | package lama |
| `new_package_id` | foreign key nullable | package baru |
| `action` | string | `assigned`, `upgraded`, `downgraded`, `renewed` |
| `notes` | text nullable | catatan |
| `performed_by` | foreign key nullable | admin pusat |
| `created_at` | timestamp | waktu aksi |

---

## 6. Relasi Utama

Relasi yang direkomendasikan:

```text
subscription_packages
  -> hasMany subscription_package_modules
  -> hasMany subscription_package_features
  -> hasMany subscription_package_limits

platform_module_catalog
  -> hasMany subscription_package_modules
  -> hasMany tenant_module_states
  -> hasMany tenant_subscription_addons

tenants
  -> hasOne tenant_subscriptions
  -> hasMany tenant_module_states
  -> hasMany tenant_subscription_invoices
  -> hasMany tenant_subscription_addons

tenant_subscription_invoices
  -> hasMany tenant_subscription_invoice_lines
```

---

## 7. Status Model yang Direkomendasikan

## 7.1 Status subscription tenant

Pilihan status:

- `trial`
- `active`
- `grace`
- `expired`
- `suspended`

Catatan:

- `suspended` sebaiknya diperlakukan sebagai override manual dari pusat
- boleh tetap disinkronkan dengan status suspend tenant yang sekarang

## 7.2 Status modul tenant

Pilihan status:

- `enabled`
- `disabled`
- `blocked`

Makna:

- `enabled`
  modul aktif dan boleh tampil
- `disabled`
  modul boleh ada tapi dimatikan
- `blocked`
  modul tidak bisa aktif karena dependency/addon/subscription issue

## 7.3 Access mode package-module

Pilihan:

- `included`
- `optional`
- `addon_only`

Makna:

- `included`
  package langsung memberi hak pakai
- `optional`
  package mengizinkan, tenant memutuskan aktif/tidak
- `addon_only`
  package dasar tidak cukup, tenant harus membeli addon

---

## 8. Rekomendasi Sumber Truth

Urutan sumber truth yang disarankan:

### 8.1 Modul kandidat platform

Sumber truth:

- `platform_module_catalog`

Jangan lagi dari hardcoded `CentralSetting::moduleCatalog()`

### 8.2 Package dan entitlement

Sumber truth:

- `subscription_packages`
- `subscription_package_modules`
- `subscription_package_features`
- `subscription_package_limits`

### 8.3 Status subscription tenant

Sumber truth:

- `tenant_subscriptions`

### 8.4 Modul runtime tenant

Sumber truth:

- `tenant_module_states`

### 8.5 Invoice subscription tenant

Sumber truth:

- `tenant_subscription_invoices`
- `tenant_subscription_invoice_lines`

---

## 9. Mana yang Tetap di Tabel `tenants`

Supaya tidak terlalu banyak join untuk identitas inti tenant, beberapa field tetap layak ada di `tenants`.

Tetap di `tenants`:

- `id`
- `name`
- `saas_type`

Opsional tetap di `tenants` untuk performa/readability:

- `status` manual suspend

Yang sebaiknya tidak lagi jadi sumber truth utama di `tenants.data`:

- `package_code`
- `subscription_status`
- `subscription_starts_at`
- `subscription_expires_at`
- `subscription_grace_until`
- `billing_usage`
- `billing_invoices`

Kalau butuh kompatibilitas sementara:

- field-field itu masih boleh disinkronkan sebagai snapshot transisi

---

## 10. Migration Strategy yang Paling Aman

## 10.1 Fase 1

Buat tabel baru dulu:

- `platform_module_catalog`
- `subscription_packages`
- `subscription_package_modules`
- `subscription_package_features`
- `subscription_package_limits`
- `tenant_subscriptions`
- `tenant_module_states`

Tanpa menghapus JSON lama.

## 10.2 Fase 2

Buat command backfill:

- baca `CentralSetting::moduleCatalog()`
- baca `CentralSetting::packageCatalog()`
- baca `tenants.data`
- isi tabel baru

Command yang direkomendasikan nanti misalnya:

```bash
php artisan app:backfill-subscription-schema
```

## 10.3 Fase 3

Ubah layer baca aplikasi:

- package page baca dari tabel baru
- tenant detail baca subscription dari tabel baru
- runtime gating baca `tenant_module_states`

## 10.4 Fase 4

Setelah semua stabil:

- JSON lama jadi fallback read-only
- lalu dipensiunkan bertahap

---

## 11. Mapping dari Struktur Lama ke Struktur Baru

| Sumber Lama | Tujuan Baru |
| --- | --- |
| `CentralSetting::moduleCatalog()` | `platform_module_catalog` |
| `central_settings.package_catalog` | `subscription_packages` + tabel turunannya |
| `central_settings.default_package_code` | `subscription_packages.is_default` |
| `central_settings.active_modules` | `platform_module_catalog.is_active` atau master selection per platform |
| `tenants.data.package_code` | `tenant_subscriptions.package_id` + `package_code_snapshot` |
| `tenants.data.subscription_status` | `tenant_subscriptions.status` |
| `tenants.data.subscription_*` | `tenant_subscriptions.starts_at/expires_at/grace_until` |
| `tenants.data.billing_invoices` | `tenant_subscription_invoices` + `tenant_subscription_invoice_lines` |
| toggle runtime tenant yang masih belum ada tabel | `tenant_module_states` |

---

## 12. Rekomendasi Implementasi Bertahap

Urutan implementasi yang paling gue saranin:

1. buat `platform_module_catalog`
2. buat `subscription_packages` dan entitlement tables
3. buat `tenant_subscriptions`
4. buat `tenant_module_states`
5. pindahkan UI package settings ke tabel relasional
6. pindahkan tenant detail subscription ke tabel relasional
7. baru terakhir pindahkan `billing_invoices`

Kenapa urutan ini:

- module catalog dan package entitlement adalah fondasi
- tenant activation baru masuk akal setelah package beres
- invoice migration paling rawan, jadi aman ditaruh belakangan

---

## 13. Rekomendasi Praktis untuk Project Ini

Kalau lihat kondisi codebase sekarang, rekomendasi paling aman:

- jangan langsung sentuh invoice subscription lama dulu
- fokus dulu ke:
  - `platform_module_catalog`
  - `subscription_packages`
  - `subscription_package_modules`
  - `tenant_subscriptions`
  - `tenant_module_states`

Itu sudah cukup untuk:

- active modules per tenant
- package entitlement per platform
- addon readiness
- menu/runtime gating

Sementara:

- `billing_invoices` lama bisa tetap jalan
- migrasi invoice bisa nyusul setelah entitlement stabil

---

## 14. Ringkasan Final

Desain database yang paling enak untuk project ini adalah:

### Central DB sebagai pusat entitlement

- `platform_module_catalog`
- `subscription_packages`
- `subscription_package_modules`
- `subscription_package_features`
- `subscription_package_limits`
- `tenant_subscriptions`
- `tenant_module_states`
- `tenant_subscription_addons`

### Tenant DB tetap fokus operasional

- simpan data domain vertical
- jangan jadi sumber truth subscription

### Strategi migrasi

- buat tabel baru
- backfill dari JSON lama
- ubah reader/writer aplikasi bertahap
- pensiunkan JSON lama setelah stabil

Itu pendekatan paling aman karena:

- cocok dengan arsitektur sekarang
- minim risiko bongkar flow yang sudah hidup
- siap untuk `hotel`, `tirta`, `resto`, dan `netbilling`
- siap untuk model `required`, `optional`, dan `addon`
