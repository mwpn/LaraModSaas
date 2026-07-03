# DEPLOYMENT (Ubuntu VPS)

Dokumen ini fokus pada deploy produksi untuk arsitektur:
- Central domain: `domain.tld`
- Tenant subdomain wildcard: `*.domain.tld`
- Central DB: `laramodsaas`
- Tenant DB: `tenant_{id}` (dibuat otomatis saat register tenant)

## 1) Nginx Wildcard + PHP-FPM

### A. Server block (HTTP)

Simpan sebagai: `/etc/nginx/sites-available/laramodsaas`

```nginx
server {
    listen 80;
    listen [::]:80;

    server_name domain.tld *.domain.tld;

    root /var/www/laramodsaas/public;
    index index.php;

    client_max_body_size 32m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Aktifkan:

```bash
sudo ln -s /etc/nginx/sites-available/laramodsaas /etc/nginx/sites-enabled/laramodsaas
sudo nginx -t
sudo systemctl reload nginx
```

### B. SSL Wildcard (Let’s Encrypt DNS Challenge)

Install Certbot (snap):

```bash
sudo snap install core
sudo snap refresh core
sudo snap install --classic certbot
sudo ln -s /snap/bin/certbot /usr/bin/certbot
```

Ambil sertifikat wildcard (manual DNS challenge):

```bash
sudo certbot certonly --manual --preferred-challenges dns \
  -d domain.tld -d "*.domain.tld"
```

Ikuti instruksi Certbot untuk membuat TXT record:
`_acme-challenge.domain.tld`.

### C. Server block (HTTPS)

Setelah sertifikat terbit, ubah server block jadi SSL (atau buat server block baru) dengan path sertifikat:

```nginx
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;

    server_name domain.tld *.domain.tld;

    ssl_certificate     /etc/letsencrypt/live/domain.tld/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/domain.tld/privkey.pem;

    root /var/www/laramodsaas/public;
    index index.php;

    client_max_body_size 32m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}

server {
    listen 80;
    listen [::]:80;
    server_name domain.tld *.domain.tld;
    return 301 https://$host$request_uri;
}
```

Reload:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## 2) Supervisor: Horizon (Queue Abadi)

Pastikan Redis aktif:

```bash
sudo systemctl enable --now redis-server
```

Install Supervisor:

```bash
sudo apt-get update
sudo apt-get install -y supervisor
sudo systemctl enable --now supervisor
```

Simpan file: `/etc/supervisor/conf.d/laramodsaas-horizon.conf`

```ini
[program:laramodsaas-horizon]
process_name=%(program_name)s
directory=/var/www/laramodsaas
command=/usr/bin/php artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/laramodsaas-horizon.log
stopwaitsecs=3600
```

Apply:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

Deploy step (biar worker reload halus):

```bash
php artisan horizon:terminate
```

## 3) First Deploy Commands (Clone → Prod Ready)

Contoh urutan minimal (Ubuntu 22.04/24.04):

```bash
sudo apt-get update
sudo apt-get install -y nginx mysql-client redis-server git unzip \
  php8.3-fpm php8.3-cli php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-mysql

cd /var/www
sudo git clone <REPO_URL> laramodsaas
cd laramodsaas

sudo chown -R www-data:www-data /var/www/laramodsaas

composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan storage:link

php artisan config:clear
php artisan route:clear
php artisan view:clear

php artisan migrate --path=database/migrations/central --realpath --force

php artisan optimize
```

Catatan `.env` minimum (tidak perlu mengatur `saas_type`):
- `APP_URL=https://domain.tld`
- `CENTRAL_DOMAIN=domain.tld`
- DB central: `DB_DATABASE=laramodsaas`
- `SESSION_DOMAIN=.domain.tld`
- Redis/Queue: `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `REDIS_CLIENT=phpredis` (atau `predis` jika dipilih)

## 4) Minimal Hardening Checklist

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw --force enable
sudo systemctl enable --now fail2ban || true
```

Wajib produksi:
- Set `APP_ENV=production`, `APP_DEBUG=false`
- Pastikan `storage/` dan `bootstrap/cache/` writable oleh `www-data`
- Jalankan Horizon via Supervisor (bukan manual)

