# Little Survey

Little Survey adalah aplikasi pengelolaan survei: admin mengelola proyek, kandidat, DPT, pertanyaan, dan responden; responden mengisi survei melalui tautan; admin memantau jawaban dan hasilnya.

## Fitur utama

- Login admin dan pengelolaan proyek survei.
- Pengelolaan kandidat, DPT, pertanyaan, dan responden per proyek.
- Tautan survei publik untuk responden.
- Pemantauan statistik dan jawaban, termasuk ekspor data yang tersedia di halaman terkait.
- Penyimpanan default SQLite.

## Persyaratan

Untuk pengembangan lokal tanpa Docker:

- PHP 8.3 atau lebih baru beserta ekstensi Laravel yang dibutuhkan (termasuk SQLite, Intl, Mbstring, dan ZIP).
- Composer.
- Node.js 22 atau lebih baru dan npm.
- SQLite.

Untuk menjalankan dengan Docker, dibutuhkan Docker Engine dan Docker Compose v2.

## Menjalankan secara lokal

```bash
git clone <URL-REPOSITORY>
cd little-survey
cp .env.example .env
composer install
npm ci
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm run build
```

Isi `ADMIN_NAME`, `ADMIN_EMAIL`, dan `ADMIN_PASSWORD` di `.env`, lalu buat/perbarui akun admin:

```bash
php artisan db:seed --class=AdminSeeder
```

Seeder tersebut mengikuti rancangan aplikasi dengan satu akun admin. Jangan jalankan ulang dengan kredensial yang berbeda jika database sudah berisi akun atau data admin yang ingin dipertahankan.

Jalankan aplikasi:

```bash
php artisan serve
```

Buka `http://localhost:8000`. Untuk pengembangan frontend dengan hot reload, jalankan `npm run dev` di terminal lain.

## Cara menggunakan aplikasi

1. Masuk melalui `/masuk` menggunakan akun admin.
2. Buat atau pilih proyek dari halaman proyek.
3. Di dalam proyek, siapkan kandidat, data DPT, pertanyaan, dan responden.
4. Bagikan tautan survei kepada responden.
5. Pantau statistik dan jawaban dari menu proyek; gunakan fitur ekspor pada halaman yang menyediakannya.
6. Atur pengaturan proyek dan publikasikan hasil jika sudah siap.

Halaman survei publik menggunakan tautan token `/s/{token}`. Halaman hasil publik menggunakan kode proyek `/hasil/{kode-proyek}`.

## Menjalankan dengan Docker

Compose lokal membangun image production dan menyimpan database serta file unggahan di Docker volumes.

```bash
cp .env.example .env
docker compose build
docker compose run --rm app php artisan key:generate --show
```

Masukkan key yang dihasilkan ke `APP_KEY` di `.env`. Atur `APP_URL` (default lokal: `http://localhost:8080`) serta, bila ingin langsung membuat akun admin, `ADMIN_NAME`, `ADMIN_EMAIL`, dan `ADMIN_PASSWORD`. Jalankan:

```bash
docker compose up -d
docker compose ps
```

Buka `http://localhost:8080`. Perintah yang berguna:

```bash
docker compose logs -f app
docker compose restart app
docker compose down
```

Data tersimpan di volume `sqlite-data` dan `app-storage`. Jangan gunakan `docker compose down -v` kecuali memang ingin menghapus database dan file unggahan secara permanen.

## Production dan deployment otomatis

Alur deployment memakai GitHub Actions dan GitHub Container Registry (GHCR):

1. Pull request ke `master` menjalankan validasi Composer, test Laravel, dan build frontend.
2. Push ke `master` yang lulus CI membangun image production bertag SHA commit dan `latest`, lalu mengirimnya ke GHCR.
3. Workflow mengirim `docker-compose.production.yml` ke server melalui SSH, menarik image bertag SHA terbaru, dan mengganti container.

Container **tidak menjalankan `git pull`**. Source code dibangun menjadi image immutable di CI; server menarik image baru dari registry. Database SQLite dan unggahan tetap ada di named volumes ketika container diganti.

### Persiapan server

Siapkan server Linux dengan Docker Engine, plugin Docker Compose v2, akses SSH, dan port aplikasi yang dapat dijangkau (default `8080`). Buat direktori deployment dan `.env` di server:

```bash
sudo install -d -o "$USER" -g "$USER" /opt/little-survey
cd /opt/little-survey
touch .env
chmod 600 .env
```

Isi `.env` production di server, jangan commit file ini:

```dotenv
APP_KEY=base64:<key-yang-dihasilkan>
APP_URL=https://survey.example.com
APP_PORT=8080
ADMIN_NAME=Administrator
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=<password-kuat>
TRUSTED_PROXIES=
```

Buat `APP_KEY` dengan `php artisan key:generate --show` pada checkout lokal atau gunakan perintah key generation pada bagian Docker. Simpan nilainya dengan aman dan jangan menggantinya setelah aplikasi mulai digunakan. `APP_URL` harus memakai URL publik aplikasi. Jika aplikasi berada di belakang reverse proxy, atur `TRUSTED_PROXIES` sesuai alamat proxy. Konfigurasikan HTTPS pada reverse proxy di depan container.

Image GHCR sebaiknya tetap private. Buat GitHub Personal Access Token (classic) dengan izin `read:packages`, lalu login ke GHCR **sebagai user SSH deployment** di server:

```bash
docker login ghcr.io --username <GITHUB-USERNAME>
```

Masukkan token saat diminta password. Pastikan user SSH yang dipakai workflow dapat menjalankan Docker tanpa prompt interaktif dan memiliki akses tulis ke `/opt/little-survey`.

### Pengaturan GitHub Actions

Tambahkan repository secrets berikut di **Settings → Secrets and variables → Actions**:

| Secret | Isi |
| --- | --- |
| `PRODUCTION_HOST` | Hostname atau alamat IP server |
| `PRODUCTION_USER` | User SSH deployment |
| `PRODUCTION_SSH_KEY` | Private key SSH untuk user deployment |
| `PRODUCTION_KNOWN_HOSTS` | Baris host key server untuk `known_hosts`; verifikasi fingerprint sebelum menyimpannya |

Workflow menggunakan branch `master` sebagai branch production dan direktori `/opt/little-survey`. Setelah secret terpasang, push ke `master` akan otomatis menjalankan CI, mengirim image ke GHCR, lalu deploy ke server. Pastikan package GHCR dapat dibaca oleh user deployment yang telah login di server.

### Operasi production

Di server, periksa status dan log dengan:

```bash
cd /opt/little-survey
docker compose ps
docker compose logs -f app
```

Setiap deploy mempertahankan volume database dan unggahan. Buat backup berkala untuk volume tersebut; jangan menghapus volume saat membersihkan container.

## Test dan build

Jalankan test Laravel:

```bash
php artisan test --compact
```

Build asset frontend:

```bash
npm ci
npm run build
```

## Stack

- Laravel 13, PHP 8.4, dan Livewire 4.
- Vite, Tailwind CSS 4, Node.js 22.
- SQLite sebagai database default.
- Docker production: PHP-FPM, Nginx, dan Supervisor.
