# LaraModSaaS Starterkit

Starterkit Laravel SaaS dengan arsitektur Modular Monolith + Multi-Database tenancy.

## Arsitektur

- Framework inti: Laravel 13
- Modular monolith: `nwidart/laravel-modules`
- Multi-tenancy: `stancl/tenancy` v3
- Mode tenancy: satu tenant satu database MySQL
- Queue: Redis
- Queue monitoring: Laravel Horizon
- Auth: multi-guard `central` dan `tenant`
- Model baru: default siap UUID melalui `App\Traits\HasTenantUuid`

## Struktur Folder

```text
app/
  Http/Controllers/Central/     -> controller untuk central app
  Jobs/                         -> base job tenant-aware dan job sistem
  Models/                       -> model central seperti User dan Tenant
  Providers/                    -> App, Tenancy, Horizon service provider
  Traits/                       -> trait global seperti HasTenantUuid

Modules/
  BaseFeature/                  -> contoh modul bisnis
    app/
    routes/

config/
  auth.php                      -> multi-auth central vs tenant
  horizon.php                   -> monitoring worker Redis
  modules.php                   -> routing generator/migration modular
  tenancy.php                   -> konfigurasi multi-db tenancy
  queue.php                     -> koneksi Redis queue dan redis-central

database/
  migrations/
    central/                    -> migrasi database master `laramodsaas`
    tenant/                     -> migrasi tenant yang dijalankan saat provisioning
  seeders/
    TenantDatabaseSeeder.php    -> seed data default tenant baru

routes/
  web.php                       -> central routes hanya untuk central domain
  tenant.php                    -> tenant routes dengan tenancy middleware
```

## Setup Lokal Laragon

1. Siapkan database MySQL central:

```text
DB Name: laramodsaas
DB User: root
DB Pass: dodolgarut
DB Host: 127.0.0.1
```

2. Pastikan `.env` memakai central DB di atas dan queue Redis:

```env
APP_URL=http://laramodsaas-starterkit.test
CENTRAL_DOMAIN=laramodsaas-starterkit.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laramodsaas
DB_USERNAME=root
DB_PASSWORD=dodolgarut

QUEUE_CONNECTION=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
SESSION_DOMAIN=.laramodsaas-starterkit.test
```

3. Tambahkan host manual Windows di `C:\Windows\System32\drivers\etc\hosts`:

```text
127.0.0.1 laramodsaas-starterkit.test
127.0.0.1 majujaya.laramodsaas-starterkit.test
```

4. Jalankan central migration:

```bash
php artisan migrate --path=database/migrations/central --realpath --force
```

5. Registrasikan tenant dari central app:

```bash
curl -X POST http://laramodsaas-starterkit.test/register ^
  -H "Accept: application/json" ^
  -d "business_name=Maju Jaya" ^
  -d "subdomain=majujaya"
```

6. Setelah tenant dibuat, tenancy akan:

- membuat database `tenant_majujaya`
- menjalankan semua migrasi di `database/migrations/tenant`
- menjalankan `Database\Seeders\TenantDatabaseSeeder`

## Queue Dan Horizon

### Queue Tenant-Aware

- Koneksi queue default memakai Redis melalui `QUEUE_CONNECTION=redis`.
- `stancl/tenancy` v3 memakai `QueueTenancyBootstrapper`, jadi saat job didispatch di konteks tenant, payload queue otomatis membawa `tenant_id`.
- Ketika worker mengeksekusi job itu, tenancy otomatis diinisialisasi lagi ke database tenant yang sesuai sebelum `handle()` berjalan.
- Base job tenant-aware disediakan di `app/Jobs/TenantAwareJob.php`.
- Stub job Laravel dan stub job modular diarahkan untuk extend base class ini.

### Horizon

- Horizon sudah terpasang dan scaffolding telah dipublish.
- Dashboard default tersedia pada path `/horizon`.
- Untuk environment non-local, gate Horizon mengizinkan akses jika guard `central` sudah terautentikasi.
- Untuk local Windows di starterkit ini, Redis client memakai `predis` agar command Redis/Horizon tetap bisa boot walau ekstensi `phpredis` tidak tersedia.
- Karena Horizon membutuhkan `ext-pcntl` dan `ext-posix`, worker Horizon dipakai untuk Linux production. Pada Windows local, package tetap tersedia untuk konfigurasi dan route dashboard, tetapi proses supervisor production sebaiknya dijalankan di server Linux.

Perintah penting:

```bash
php artisan horizon
php artisan horizon:status
php artisan horizon:terminate
```

## Rate Limiter Tenant

- Semua route tenant di `routes/tenant.php` sudah memakai `throttle:tenant`.
- Rate limiter `tenant` didefinisikan di `app/Providers/AppServiceProvider.php`.
- Limit default: `60 request / menit / tenant subdomain`.
- Isolasi limiter menggunakan fragmen subdomain, sehingga traffic tenant `majujaya` tidak mengganggu tenant lain.

## Multi-Auth

- Guard `web` dan `central` memakai provider `users` untuk database central.
- Guard `tenant` memakai provider `tenant_users`.
- Saat request berjalan di subdomain tenant, provider `tenant_users` otomatis membaca model `User` dari database tenant aktif.

## Membuat Modul Baru

1. Generate modul:

```bash
php artisan module:make Sales
```

2. Tambahkan route, service, dan model di `Modules/Sales`.

3. Untuk model baru di dalam modul:

- gunakan stub model yang sudah dipublish
- model baru otomatis memakai `App\Traits\HasTenantUuid`
- primary key default akan berupa UUID string

4. Untuk job queue tenant:

- generate job queued
- arahkan class job untuk extend `App\Jobs\TenantAwareJob`
- dispatch job dari konteks tenant agar payload queue membawa `tenant_id`

5. Untuk migrasi bisnis tenant:

- simpan migrasi baru di `database/migrations/tenant`
- jangan simpan migrasi bisnis tenant di `database/migrations/central`
- konfigurasi `modules.php` starterkit ini sudah diarahkan agar generator migration modul menuju path tenant

## Perintah Penting

Central migration:

```bash
php artisan migrate --path=database/migrations/central --realpath --force
```

Lihat route:

```bash
php artisan route:list
```

Jalankan worker Redis biasa:

```bash
php artisan queue:work redis --queue=default
```

Jalankan Horizon:

```bash
php artisan horizon
```

## Catatan Produksi

- Production yang disarankan: Linux + Redis + MySQL + Supervisor/Systemd.
- Horizon supervisor sebaiknya hanya berjalan di central environment production.
- Domain wildcard DNS dan web server harus mengarah ke `*.laramodsaas-starterkit.test` untuk local dev, dan ke domain SaaS yang sesuai pada production.
- Job sistem pusat yang tidak boleh membawa konteks tenant dapat diarahkan ke koneksi `redis-central`.
