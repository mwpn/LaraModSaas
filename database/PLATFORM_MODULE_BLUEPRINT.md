# Platform Module Blueprint

Dokumen ini jadi pegangan untuk memecah vertical platform menjadi domain yang rapi, konsisten, dan bisa diulang untuk platform lain.

Tujuan utama:

- mencegah semua fitur menumpuk di satu modul besar
- bikin boundary domain lebih jelas
- memudahkan refactor bertahap dari struktur feature-based ke module-based
- memisahkan domain teknis dari entitlement subscription
- menyediakan pola yang bisa dipakai ulang untuk `tirta`, `hotel`, `resto`, dan `netbilling`

---

## 1. Prinsip Utama

### 1.1 Pecah per domain inti, bukan per layar kecil

Yang direkomendasikan:

- `TirtaPelanggan`
- `TirtaCatatMeter`
- `TirtaBilling`
- `TirtaGangguan`
- `TirtaPengaturan`

Yang tidak direkomendasikan sebagai modul terpisah:

- `rayon` sendiri
- `golongan` sendiri
- `sambungan` sendiri
- `tarif` kecil-kecil per layar

Alasannya:

- domain terlalu kecil bikin dependency silang makin ribet
- controller, route, dan model jadi terlalu tersebar
- perubahan kecil butuh menyentuh terlalu banyak modul

### 1.2 Struktur data boleh lahir dulu, modularisasi bisa menyusul

Kalau bisnis lagi butuh jalan cepat:

1. bangun domain dan schema dulu
2. validasi alur bisnisnya
3. setelah stabil, refactor ke module package penuh

Jadi modularisasi di project ini boleh dilakukan bertahap:

- tahap 1: domain hidup dulu di `app/` + `resources/views/`
- tahap 2: setelah flow stabil, pindah ke `Modules/<DomainName>`

### 1.3 Satu platform bisa punya banyak modul domain

Contoh:

- platform `tirta` bukan satu modul raksasa
- platform `tirta` adalah kumpulan modul domain yang saling kerja sama

### 1.4 Modul domain tidak sama dengan modul subscription

Ini prinsip yang sangat penting untuk semua vertical.

Bedakan 3 hal berikut:

- `Domain Module`
- `Subscription Entitlement`
- `Tenant Active Module`

Artinya:

- `Domain Module` menjawab: fitur ini secara teknis masuk domain apa?
- `Subscription Entitlement` menjawab: package tenant berhak pakai modul apa?
- `Tenant Active Module` menjawab: dari modul yang diizinkan, mana yang benar-benar dinyalakan tenant?

Contoh di `hotel`:

- `HotelHousekeeping` adalah domain module
- package `Hotel Basic` bisa saja tidak mengizinkan `HotelHousekeeping`
- package `Hotel Pro` mengizinkan `HotelHousekeeping`, tapi tenant masih boleh memilih mengaktifkannya atau tidak

Jadi:

- satu platform bisa punya banyak modul domain
- satu package hanya mengizinkan sebagian modul
- satu tenant hanya mengaktifkan modul yang memang dibutuhkan

### 1.5 Required module dan optional module

Setiap platform idealnya punya 2 jenis modul:

- `required module`
- `optional module`

Contoh:

- `HotelReservasi` kemungkinan `required`
- `HotelHousekeeping` kemungkinan `optional`
- `HotelOtaChannel` kemungkinan `optional`
- `Payment Gateway` bisa `optional` atau `addon`

Ini penting supaya:

- tenant kecil tidak dipaksa menanggung fitur yang tidak dibutuhkan
- subscription/package bisa disusun lebih fleksibel
- add-on komersial jadi mudah dijual

---

## 2. Blueprint Final Tirta

### 2.1 Modul yang direkomendasikan

#### `TirtaPelanggan`

Tanggung jawab:

- master pelanggan
- master sambungan
- master rayon / unit / zona
- master golongan layanan
- hubungan pelanggan ke sambungan

Data inti:

- `service_areas`
- `service_categories`
- `customers`
- `service_connections`

Kenapa domain ini satu paket:

- pelanggan, sambungan, rayon, dan golongan adalah fondasi yang saling terkait erat
- modul lain seperti meter, gangguan, dan billing akan membaca data dari sini

#### `TirtaCatatMeter`

Tanggung jawab:

- periode pembacaan meter
- input baca meter
- histori pemakaian
- validasi anomali / lonjakan
- workflow petugas lapangan

Data inti yang nantinya lahir di domain ini:

- `meter_reading_periods`
- `meter_readings`
- `meter_reading_logs`
- `usage_anomalies`

Ketergantungan:

- membaca `service_connections` dari `TirtaPelanggan`

#### `TirtaBilling`

Tanggung jawab:

- skema tarif
- tier tarif
- kalkulasi pemakaian
- generate tagihan
- denda
- piutang
- status pembayaran

Data inti:

- `tariff_schemes`
- `tariff_scheme_tiers`
- tabel invoice / billing bulanan
- tabel penalty / surcharge
- tabel payment allocation

Ketergantungan:

- membaca sambungan dari `TirtaPelanggan`
- membaca hasil pemakaian dari `TirtaCatatMeter`

#### `TirtaGangguan`

Tanggung jawab:

- tiket gangguan pelanggan
- status pengerjaan
- prioritas
- assign petugas
- histori tindak lanjut

Data inti:

- `service_tickets`
- `ticket_status_logs`
- `ticket_assignments`

Ketergantungan:

- membaca pelanggan dan sambungan dari `TirtaPelanggan`

#### `TirtaPengaturan`

Tanggung jawab:

- pengaturan tenant Tirta yang sifatnya global
- label istilah lokal tenant
- preferensi format nomor sambungan
- periode default catat meter
- aturan operasional dasar

Contoh isi:

- tenant mau nyebut `rayon`, `unit`, `wilayah`, atau `cabang`
- panjang nomor sambungan default
- aturan nomor otomatis
- default golongan saat bikin sambungan
- default periode baca meter

Catatan:

- ini bukan tempat data master operasional harian
- ini khusus parameter global untuk tenant Tirta

---

## 3. Boundary Antar Modul Tirta

### 3.1 Sumber data utama

Urutan domain yang jadi fondasi:

1. `TirtaPelanggan`
2. `TirtaCatatMeter`
3. `TirtaBilling`
4. `TirtaGangguan`
5. `TirtaPengaturan`

### 3.2 Dependency rule

Aturan arah dependency yang direkomendasikan:

- `TirtaPelanggan` tidak boleh bergantung ke `TirtaBilling`
- `TirtaCatatMeter` boleh bergantung ke `TirtaPelanggan`
- `TirtaBilling` boleh bergantung ke `TirtaPelanggan` dan `TirtaCatatMeter`
- `TirtaGangguan` boleh bergantung ke `TirtaPelanggan`
- `TirtaPengaturan` boleh dibaca oleh semua domain, tapi tidak menjadi tempat transaksi

Kalau disederhanakan:

- `Pelanggan` jadi upstream
- `Meter`, `Billing`, `Gangguan` jadi downstream

### 3.3 Aturan model bisnis

- satu pelanggan bisa punya banyak sambungan
- golongan sebaiknya menempel ke sambungan, bukan ke pelanggan
- nomor sambungan bukan primary key teknis database
- ID internal tetap pakai UUID / internal key
- nomor sambungan adalah business code yang aman ditampilkan ke tenant

---

## 4. Subscription-Aware Architecture

Bagian ini menambahkan layer komersial dan runtime di atas modularisasi domain.

### 4.1 Lima konsep inti

Untuk platform yang fleksibel, model besarnya disarankan punya 5 konsep:

- `Platform`
- `Platform Module Catalog`
- `Package / Subscription`
- `Package Module Entitlements`
- `Tenant Module Activation`

Penjelasan:

#### `Platform`

Menentukan vertical bisnis tenant:

- `tirta`
- `hotel`
- `resto`
- `netbilling`

#### `Platform Module Catalog`

Daftar semua modul yang tersedia untuk sebuah platform.

Contoh untuk `hotel`:

- `HotelReservasi`
- `HotelHousekeeping`
- `HotelOtaChannel`
- `HotelBilling`
- `HotelPaymentGateway`

Field yang ideal untuk katalog ini:

- `module_code`
- `module_name`
- `platform_type`
- `domain_group`
- `is_required`
- `is_default_enabled`
- `is_addon`
- `subscription_visible`
- `depends_on`

#### `Package / Subscription`

Mewakili produk komersial yang dijual ke tenant.

Contoh:

- `Hotel Basic`
- `Hotel Standard`
- `Hotel Enterprise`

#### `Package Module Entitlements`

Menentukan modul apa saja yang boleh dipakai oleh package tertentu.

Contoh:

- `Hotel Basic` boleh `Reservasi`
- `Hotel Basic` tidak boleh `Housekeeping`
- `Hotel Enterprise` boleh `Reservasi`, `Housekeeping`, `OTA`, `Payment Gateway`

#### `Tenant Module Activation`

Menentukan modul mana yang benar-benar aktif di tenant runtime.

Contoh:

- package tenant mengizinkan `Housekeeping`
- tenant memutuskan tidak mengaktifkan `Housekeeping`
- hasil akhirnya: entitlement ada, tapi module runtime tetap off

### 4.2 Alur keputusan modul

Urutan logika yang disarankan:

1. `platform` menentukan modul kandidat
2. `package` menentukan modul yang diizinkan
3. `tenant setting` menentukan modul yang diaktifkan
4. aplikasi hanya menampilkan route, menu, dan fitur untuk modul yang aktif

Kalau disederhanakan:

```text
Platform -> Candidate Modules
Package -> Allowed Modules
Tenant -> Enabled Modules
Runtime -> Visible Features
```

### 4.3 Kenapa ini penting

Model ini penting karena kebutuhan tenant berbeda-beda.

Contoh:

- hotel kecil mungkin tidak butuh `Housekeeping`
- hotel tertentu mungkin tidak mau pakai `OTA Channel`
- resto kecil mungkin tidak butuh inventory kompleks
- tenant tirta tertentu mungkin tidak butuh `Gangguan` di fase awal

Kalau modul domain dan subscription dicampur jadi satu:

- package sulit disusun
- addon sulit dijual
- menu tenant sulit dikontrol
- biaya langganan sulit dipetakan ke fitur nyata

### 4.4 Aturan arsitektur yang direkomendasikan

- domain module tetap jadi pusat struktur codebase
- subscription tidak menentukan struktur file, hanya entitlement bisnis
- tenant activation tidak membuat modul baru, hanya menyalakan atau mematikan modul yang sudah ada
- required module otomatis aktif jika package valid
- optional module hanya aktif jika package mengizinkan dan tenant memilih menyalakannya

### 4.5 Contoh kasus hotel

#### Hotel A

- platform: `hotel`
- package: `Hotel Basic`
- allowed modules:
  - `HotelReservasi`
  - `HotelPaymentGateway`
- enabled modules:
  - `HotelReservasi`
- disabled modules:
  - `HotelPaymentGateway`
  - `HotelHousekeeping`
  - `HotelOtaChannel`

#### Hotel B

- platform: `hotel`
- package: `Hotel Standard`
- allowed modules:
  - `HotelReservasi`
  - `HotelPaymentGateway`
  - `HotelHousekeeping`
- enabled modules:
  - `HotelReservasi`
  - `HotelHousekeeping`
- disabled modules:
  - `HotelPaymentGateway`
  - `HotelOtaChannel`

#### Hotel C

- platform: `hotel`
- package: `Hotel Enterprise`
- allowed modules:
  - `HotelReservasi`
  - `HotelHousekeeping`
  - `HotelOtaChannel`
  - `HotelPaymentGateway`
- enabled modules:
  - semuanya aktif

### 4.6 Mapping ke sistem package yang sudah ada

Di project ini sudah ada fondasi:

- platform type
- active modules
- package catalog
- package feature catalog

Arah pengembangannya:

- `module catalog` jadi master teknis lintas platform
- `package` menentukan entitlement modul
- `tenant active modules` menjadi konfigurasi runtime tenant
- UI subscription nanti tinggal membaca 3 layer itu

---

## 5. Mapping Requirement Tirta Saat Ini

Requirement yang sudah disepakati:

- tenant mengatur sendiri data pelanggan
- tenant mengatur sendiri rayon / unit
- tenant mengatur sendiri golongan
- tenant mengatur sendiri sambungan
- satu pelanggan bisa punya lebih dari satu sambungan
- tenant mengatur sendiri skema tarif

Pemetaan ke domain:

### Masuk `TirtaPelanggan`

- pelanggan
- rayon / unit
- golongan
- sambungan
- relasi pelanggan ke sambungan

### Masuk `TirtaBilling`

- skema tarif flat
- skema tarif bertingkat
- skema tarif blok awal lalu lanjut per kubik
- tarif default

Catatan transisi:

- saat ini `tariff_schemes` dan `tariff_scheme_tiers` sudah hidup sebagai fondasi data
- secara arsitektur final, tabel dan logic ini nantinya diposisikan sebagai domain `TirtaBilling`

---

## 6. Rencana Refactor Bertahap

### Tahap A: Build domain dulu

Tujuan:

- fitur hidup dan tervalidasi
- schema stabil
- user flow jelas

Boleh dikerjakan di:

- `app/Http/Controllers/Tenant`
- `app/Models/Tirta`
- `resources/views/modules/basefeature/tirta`
- `database/migrations/tenant`

### Tahap B: Rapikan per domain

Tujuan:

- logic dipisah per domain
- service class mulai muncul
- naming lebih konsisten

Contoh:

- controller pelanggan dipisah dari controller billing
- view pelanggan dipisah dari view meter
- helper kalkulasi tarif dipindah ke service class

### Tahap C: Naik kelas jadi module package

Kalau domain sudah matang, pindahkan ke:

```text
Modules/
  TirtaPelanggan/
  TirtaCatatMeter/
  TirtaBilling/
  TirtaGangguan/
  TirtaPengaturan/
```

Isi minimal tiap modul:

```text
Modules/TirtaPelanggan/
  app/
    Http/Controllers/
    Models/
    Services/
  routes/
  resources/views/
  module.json
```

Catatan:

- migrasi tenant boleh tetap di `database/migrations/tenant` lebih dulu untuk menjaga satu pintu migrasi tenant
- kalau suatu saat butuh migrasi per modul, baru dipisah setelah pipeline migrasinya matang

---

## 7. Aturan Praktis Saat Memecah Jadi Modul

### 6.1 Kapan sebuah domain layak dipecah jadi modul sendiri

Suatu domain layak jadi modul kalau:

- punya tabel sendiri yang jelas
- punya route sendiri yang cukup banyak
- punya UI kerja sendiri
- punya service / rules sendiri
- dipakai berulang di banyak screen

### 6.2 Kapan jangan dipecah

Jangan jadikan modul terpisah kalau hanya:

- lookup kecil
- helper kecil
- subform kecil
- satu tabel yang cuma dipakai parent domain

Contoh:

- `rayon`
- `golongan`
- `status sambungan`

Itu lebih baik tetap jadi bagian dari `TirtaPelanggan`, bukan modul mandiri.

### 6.3 Naming convention

Disarankan untuk nama modul:

- `TirtaPelanggan`
- `TirtaCatatMeter`
- `TirtaBilling`
- `TirtaGangguan`
- `TirtaPengaturan`

Disarankan untuk route name:

- `tenant.tirta.pelanggan.*`
- `tenant.tirta.meter.*`
- `tenant.tirta.billing.*`
- `tenant.tirta.gangguan.*`
- `tenant.tirta.settings.*`

Disarankan untuk namespace:

- `Modules\TirtaPelanggan\...`
- `Modules\TirtaBilling\...`

Jangan pakai snake case untuk nama class / namespace PHP seperti:

- `tirta_pelanggan`
- `tirta_catat_meter`

Snake case masih boleh untuk slug, key config, atau folder asset kalau memang dibutuhkan.

---

## 8. Template Blueprint Untuk Platform Lain

Pola ini bisa dipakai ulang ke vertical lain.

### 7.1 Hotel

Contoh domain:

- `HotelReservasi`
- `HotelFrontOffice`
- `HotelBilling`
- `HotelHousekeeping`
- `HotelPengaturan`

Contoh modul optional yang bisa dikontrol subscription:

- `HotelHousekeeping`
- `HotelOtaChannel`
- `HotelPaymentGateway`
- `HotelRestaurantPos`

### 7.2 Resto

Contoh domain:

- `RestoPelanggan`
- `RestoOrder`
- `RestoKasir`
- `RestoInventory`
- `RestoPengaturan`

Contoh modul optional:

- `RestoInventory`
- `RestoKitchenDisplay`
- `RestoDeliveryAggregator`
- `RestoPaymentGateway`

### 7.3 Netbilling

Contoh domain:

- `NetPelanggan`
- `NetProvisioning`
- `NetBilling`
- `NetGangguan`
- `NetPengaturan`

Contoh modul optional:

- `NetMikrotikAutomation`
- `NetOltMonitoring`
- `NetPaymentGateway`
- `NetTicketing`

Kesamaan pola:

- selalu ada domain `master`
- selalu ada domain `transaksi operasional`
- selalu ada domain `billing`
- selalu ada domain `pengaturan`
- selalu ada pemisahan antara `candidate modules`, `allowed modules`, dan `enabled modules`

---

## 9. Template Proses Modularisasi Platform Baru

Kalau nanti bikin platform baru, urutannya disarankan begini:

### Step 1: Tentukan domain inti

Pisahkan:

- master data
- transaksi utama
- billing / monetisasi
- support / ticketing
- pengaturan

### Step 2: Tentukan katalog modul platform

Tanya:

- modul apa yang wajib untuk platform ini?
- modul apa yang opsional?
- modul apa yang cocok dijual sebagai addon?

Output step ini:

- daftar `candidate modules`
- flag `required` vs `optional`
- relasi dependency antar modul

### Step 3: Tentukan upstream-downstream

Tanya:

- domain mana yang jadi sumber data utama?
- domain mana yang cuma membaca data domain lain?

### Step 4: Tentukan entitlement subscription

Tanya:

- package apa saja yang akan dijual?
- tiap package boleh modul apa?
- apakah tenant boleh menyalakan / mematikan modul yang sudah diizinkan?

Output step ini:

- matrix `package -> allowed modules`
- aturan `required module`
- aturan `addon module`

### Step 5: Build schema dan flow inti dulu

Jangan langsung pecah jadi 10 modul.

Bangun dulu:

- tabel utama
- relasi utama
- UI dasar
- validasi utama

### Step 6: Rapikan boundary

Setelah flow jalan:

- pindah query berat ke service
- kurangi coupling antar controller
- samakan naming route, model, dan view

### Step 7: Pecah jadi module package

Baru setelah:

- domain jelas
- fitur sudah hidup
- schema sudah stabil

---

## 10. Keputusan Arsitektur Saat Ini

Keputusan yang dipakai untuk project ini:

- Tirta akan diarahkan ke modularisasi per domain inti
- modularisasi harus sadar subscription sejak awal
- modul domain, package entitlement, dan tenant activation harus dipisahkan secara konsep
- implementasi boleh bertahap, tidak harus langsung semua dipindah ke `Modules/`
- fondasi yang sudah terlanjur dibangun di layer aplikasi tetap sah selama boundary domainnya jelas
- refactor ke module package dilakukan setelah flow bisnis stabil

Keputusan ini sengaja dipilih supaya:

- development tetap cepat
- struktur jangka panjang tetap sehat
- subscription package lebih fleksibel untuk tenant kecil dan tenant besar
- platform lain nanti bisa mengikuti pola yang sama

---

## 11. Ringkasan Final

Untuk platform `tirta`, blueprint yang direkomendasikan adalah:

```text
TirtaPelanggan
  - rayon / unit
  - golongan
  - pelanggan
  - sambungan

TirtaCatatMeter
  - periode baca meter
  - input baca meter
  - histori pemakaian
  - anomali

TirtaBilling
  - skema tarif
  - tier tarif
  - generate tagihan
  - denda
  - pembayaran

TirtaGangguan
  - tiket gangguan
  - assign petugas
  - histori penanganan

TirtaPengaturan
  - istilah lokal
  - format nomor
  - default operasional
```

Dan untuk semua platform, layer arsitekturnya disarankan selalu begini:

```text
Platform
  -> Candidate Modules
Package / Subscription
  -> Allowed Modules
Tenant Activation
  -> Enabled Modules
Runtime UI / Route / Feature
  -> Visible Features
```

Kalau mau lanjut implementasi:

1. pertahankan blueprint ini sebagai acuan domain
2. tambahkan catalog modul dan entitlement subscription per platform
3. bangun fitur per domain sesuai urutan prioritas
4. setelah stabil, refactor domain ke module package penuh

Itu pendekatan yang paling aman, scalable, dan enak diulang untuk vertical lain.

---

## 12. Matrix Modul per Platform

Bagian ini adalah matrix operasional yang bisa dipakai saat:

- menyusun package
- menyusun entitlement
- menyusun menu tenant
- menyusun prioritas development

### 12.1 Matrix kandidat modul `Tirta`

| Module Code | Domain Module | Tipe | Default | Addon | Depends On | Catatan |
| --- | --- | --- | --- | --- | --- | --- |
| `tirta_pelanggan` | `TirtaPelanggan` | required | on | no | - | Fondasi pelanggan, sambungan, rayon, golongan |
| `tirta_catat_meter` | `TirtaCatatMeter` | optional | off | no | `tirta_pelanggan` | Untuk petugas lapangan dan histori pemakaian |
| `tirta_billing` | `TirtaBilling` | optional | off | no | `tirta_pelanggan`, `tirta_catat_meter` | Tarif, tagihan, denda, pembayaran |
| `tirta_gangguan` | `TirtaGangguan` | optional | off | yes | `tirta_pelanggan` | Tiket gangguan dan penanganan |
| `tirta_pengaturan` | `TirtaPengaturan` | required | on | no | - | Parameter global tenant Tirta |

### 12.2 Matrix kandidat modul `Hotel`

| Module Code | Domain Module | Tipe | Default | Addon | Depends On | Catatan |
| --- | --- | --- | --- | --- | --- | --- |
| `hotel_reservasi` | `HotelReservasi` | required | on | no | - | Modul inti booking dan room plan |
| `hotel_front_office` | `HotelFrontOffice` | required | on | no | `hotel_reservasi` | Check-in, check-out, guest folio |
| `hotel_billing` | `HotelBilling` | required | on | no | `hotel_reservasi`, `hotel_front_office` | Billing utama hotel |
| `hotel_housekeeping` | `HotelHousekeeping` | optional | off | no | `hotel_reservasi` | Cocok untuk hotel menengah ke atas |
| `hotel_ota_channel` | `HotelOtaChannel` | optional | off | yes | `hotel_reservasi` | Sinkron OTA seperti Traveloka/Booking |
| `hotel_payment_gateway` | `HotelPaymentGateway` | optional | off | yes | `hotel_billing` | Pembayaran online dan settlement |
| `hotel_restaurant_pos` | `HotelRestaurantPos` | optional | off | yes | `hotel_billing` | Untuk hotel yang punya outlet F&B |
| `hotel_pengaturan` | `HotelPengaturan` | required | on | no | - | Config operasional hotel |

### 12.3 Matrix kandidat modul `Resto`

| Module Code | Domain Module | Tipe | Default | Addon | Depends On | Catatan |
| --- | --- | --- | --- | --- | --- | --- |
| `resto_order` | `RestoOrder` | required | on | no | - | Order dine-in / takeaway |
| `resto_kasir` | `RestoKasir` | required | on | no | `resto_order` | POS dan pembayaran |
| `resto_pelanggan` | `RestoPelanggan` | optional | off | no | `resto_order` | CRM dan riwayat pelanggan |
| `resto_inventory` | `RestoInventory` | optional | off | yes | `resto_order` | Bahan baku dan stok |
| `resto_kitchen_display` | `RestoKitchenDisplay` | optional | off | yes | `resto_order` | Display kitchen / production queue |
| `resto_delivery_aggregator` | `RestoDeliveryAggregator` | optional | off | yes | `resto_order` | Integrasi delivery platform |
| `resto_payment_gateway` | `RestoPaymentGateway` | optional | off | yes | `resto_kasir` | Payment online |
| `resto_pengaturan` | `RestoPengaturan` | required | on | no | - | Config outlet dan operasional |

### 12.4 Matrix kandidat modul `Netbilling`

| Module Code | Domain Module | Tipe | Default | Addon | Depends On | Catatan |
| --- | --- | --- | --- | --- | --- | --- |
| `net_pelanggan` | `NetPelanggan` | required | on | no | - | Data pelanggan dan layanan |
| `net_provisioning` | `NetProvisioning` | required | on | no | `net_pelanggan` | Aktivasi layanan dan paket |
| `net_billing` | `NetBilling` | required | on | no | `net_pelanggan`, `net_provisioning` | Billing internet utama |
| `net_gangguan` | `NetGangguan` | optional | off | no | `net_pelanggan` | Tiket gangguan pelanggan |
| `net_mikrotik_automation` | `NetMikrotikAutomation` | optional | off | yes | `net_provisioning`, `net_billing` | Isolir / enable otomatis |
| `net_olt_monitoring` | `NetOltMonitoring` | optional | off | yes | `net_provisioning` | Monitoring OLT / ODP |
| `net_payment_gateway` | `NetPaymentGateway` | optional | off | yes | `net_billing` | Pembayaran online |
| `net_pengaturan` | `NetPengaturan` | required | on | no | - | Config operasional ISP |

### 12.5 Arti kolom matrix

- `Tipe = required`
  modul wajib tersedia untuk platform itu
- `Tipe = optional`
  modul bukan inti dan bisa dimatikan
- `Default = on`
  modul otomatis aktif saat tenant dibuat jika package mengizinkan
- `Addon = yes`
  modul cocok dijual terpisah dari package utama
- `Depends On`
  modul upstream yang harus tersedia lebih dulu

---

## 13. Matrix Subscription Archetype

Bagian ini bukan harga final, tapi template paket yang bisa dipakai saat menyusun produk komersial.

### 13.1 Matrix subscription `Hotel`

| Package | Allowed Modules | Cocok Untuk | Catatan |
| --- | --- | --- | --- |
| `Hotel Basic` | `hotel_reservasi`, `hotel_front_office`, `hotel_billing` | Penginapan kecil | Fokus operasional inti tanpa kompleksitas tambahan |
| `Hotel Standard` | `Hotel Basic` + `hotel_housekeeping`, `hotel_payment_gateway` | Hotel menengah | Mulai butuh housekeeping dan pembayaran digital |
| `Hotel Enterprise` | `Hotel Standard` + `hotel_ota_channel`, `hotel_restaurant_pos` | Hotel besar / chain | Cocok untuk operasional multi-channel |

### 13.2 Matrix subscription `Tirta`

| Package | Allowed Modules | Cocok Untuk | Catatan |
| --- | --- | --- | --- |
| `Tirta Basic` | `tirta_pelanggan`, `tirta_pengaturan` | KPSPAM kecil | Fokus master data dan pengaturan dasar |
| `Tirta Operasional` | `Tirta Basic` + `tirta_catat_meter`, `tirta_billing` | Pamsimas / Bumdes aktif | Sudah siap catat meter dan billing |
| `Tirta Lengkap` | `Tirta Operasional` + `tirta_gangguan` | Perumda / unit besar | Tambah workflow tiket layanan |

### 13.3 Matrix subscription `Resto`

| Package | Allowed Modules | Cocok Untuk | Catatan |
| --- | --- | --- | --- |
| `Resto Basic` | `resto_order`, `resto_kasir`, `resto_pengaturan` | Outlet kecil | POS inti |
| `Resto Standard` | `Resto Basic` + `resto_pelanggan`, `resto_payment_gateway` | Outlet berkembang | Mulai butuh CRM dan payment online |
| `Resto Enterprise` | `Resto Standard` + `resto_inventory`, `resto_kitchen_display`, `resto_delivery_aggregator` | Multi outlet | Operasional kitchen dan inventory lebih kompleks |

### 13.4 Matrix subscription `Netbilling`

| Package | Allowed Modules | Cocok Untuk | Catatan |
| --- | --- | --- | --- |
| `Net Basic` | `net_pelanggan`, `net_provisioning`, `net_billing`, `net_pengaturan` | ISP kecil | Billing inti dan provisioning dasar |
| `Net Standard` | `Net Basic` + `net_gangguan`, `net_payment_gateway` | ISP berkembang | Tambah ticketing dan payment online |
| `Net Enterprise` | `Net Standard` + `net_mikrotik_automation`, `net_olt_monitoring` | ISP lebih besar | Otomasi jaringan dan monitoring |

### 13.5 Addon matrix

Modul tertentu lebih cocok dijual sebagai addon lintas package.

| Platform | Addon Module | Cocok Untuk |
| --- | --- | --- |
| `hotel` | `hotel_ota_channel` | Hotel yang butuh distribusi OTA |
| `hotel` | `hotel_payment_gateway` | Hotel yang ingin pembayaran online |
| `tirta` | `tirta_gangguan` | Tenant yang ingin ticketing layanan |
| `resto` | `resto_delivery_aggregator` | Resto yang fokus delivery |
| `netbilling` | `net_mikrotik_automation` | ISP yang ingin isolir / enable otomatis |

---

## 14. Matrix Aktivasi Tenant

Setelah tenant punya package, langkah berikutnya adalah aktivasi runtime.

### 14.1 Aturan aktivasi

| Kondisi | Hasil |
| --- | --- |
| Modul `required` dan package valid | otomatis `enabled` |
| Modul `optional` tapi tidak diizinkan package | paksa `disabled` |
| Modul `optional` diizinkan package dan default on | tenant boleh matikan jika rule bisnis mengizinkan |
| Modul `addon` belum dibeli | `disabled` |
| Modul punya dependency yang belum aktif | modul target tidak boleh aktif |

### 14.2 Matrix status modul per tenant

| Status | Arti |
| --- | --- |
| `candidate` | Modul tersedia di platform ini |
| `allowed` | Modul diizinkan oleh package tenant |
| `enabled` | Modul aktif dipakai tenant |
| `disabled` | Modul ada tapi dimatikan |
| `blocked` | Modul seharusnya ada, tapi dependency belum terpenuhi atau addon belum dibeli |

### 14.3 Contoh runtime `Hotel Basic`

| Module | Candidate | Allowed | Enabled | Catatan |
| --- | --- | --- | --- | --- |
| `hotel_reservasi` | yes | yes | yes | Required |
| `hotel_front_office` | yes | yes | yes | Required |
| `hotel_billing` | yes | yes | yes | Required |
| `hotel_housekeeping` | yes | no | no | Tidak termasuk package |
| `hotel_ota_channel` | yes | no | no | Addon belum dibeli |
| `hotel_payment_gateway` | yes | no / yes | no | Tergantung package atau addon |

### 14.4 Contoh runtime `Hotel Standard`

| Module | Candidate | Allowed | Enabled | Catatan |
| --- | --- | --- | --- | --- |
| `hotel_reservasi` | yes | yes | yes | Required |
| `hotel_front_office` | yes | yes | yes | Required |
| `hotel_billing` | yes | yes | yes | Required |
| `hotel_housekeeping` | yes | yes | yes / no | Tenant boleh pilih aktifkan |
| `hotel_payment_gateway` | yes | yes | yes / no | Tenant boleh pilih aktifkan |
| `hotel_ota_channel` | yes | no | no | Belum diizinkan |

### 14.5 Konsekuensi ke UI dan permission

Saat `enabled = false`, maka:

- menu modul tidak tampil
- route modul tidak diekspos ke tenant
- dashboard widget modul tidak dimunculkan
- billing usage modul tidak dihitung jika memang model bisnisnya usage-based

Saat `allowed = false`, maka:

- tenant tidak bisa menyalakan modul walaupun tahu URL-nya
- central panel bisa menampilkan status `Upgrade package untuk memakai modul ini`

---

## 15. Cara Pakai Matrix Ini di Project

Urutan praktis yang direkomendasikan:

1. tentukan `platform_type`
2. isi `candidate module catalog` untuk platform itu
3. susun `package -> allowed modules`
4. simpan `tenant -> enabled modules`
5. runtime membaca gabungan 3 layer itu untuk menentukan menu, route, dan fitur aktif

Kalau mau dihubungkan ke fondasi project yang sekarang:

- `CentralSetting::moduleCatalog()` bisa berkembang jadi katalog kandidat lintas platform
- `package catalog` bisa ditambah matrix entitlement modul
- metadata tenant bisa menyimpan `enabled_modules`
- middleware / helper runtime bisa membaca `allowed && enabled`

Dengan begitu:

- struktur modul tetap sehat
- package lebih fleksibel
- platform lain tinggal ikut pola yang sama
