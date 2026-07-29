# TODO – Perubahan dan Rencana Selanjutnya

## ✅ Perubahan yang Sudah Dilakukan

### Submodule
- Diperbarui submodule `pde-idx-app` dari `https://github.com/yht/pde-idx-web.git` ke `https://github.com/b2b-web-id/pde-idx-app.git` (branch `master`).
- Commit: `[master a23b830] Update submodule pde-idx-app to https://github.com/b2b-web-id/pde-idx-app.git master`

### Dokumentasi
Direktori `docs/` dibuat dan berisi lima panduan:
- `installation.md` – panduan instalasi (Docker dan manual).
- `database.md` – konfigurasi basis data, inisialisasi, migrasi, backup, dan peningkatan performa.
- `deployment.md` – petunjuk penerapan ke produksi (image produksi, variabel lingkungan, orchestrator Docker Compose/Kubernetes, skalabilitas, keamanan, backup, rollback).
- `architecture.md` – gambaran arsitektur tinggi komponen, aliran data, dan prinsip desain.
- `development.md` – panduan pengembangan hari‑hari: menjalankan lingkungan dev, menjalankan tes (Codeception), debug, profiling, gaya kode (PHP_CS_FIXER, PHPStan), dan kontribusi melalui pull‑request.

### Docker Compose
- File `docker-compose.yml` telah disusun sehingga:
  - Layanan `db` menggunakan image `mysql:5.7`, tidak mengekspos port ke host, menggunakan volume bernama `db_data` untuk persisten data.
  - Layanan `php` menggunakan image `yiisoftware/yii2-php:7.1-apache`, menjalankan sebagai pengguna host (`1000:1000`) untuk menghindari masalah izin, mengatur variabel lingkungan `APACHE_DOCUMENT_ROOT=/app/web` sehingga Apache melayani direktori `web/` aplikasi, dan mem‑mount kode sumber ke `/app`.
  - Hanya port `80` dari layanan `php` yang dipetakan ke host (yang dapat diubah dengan mudah).
  - Jaringan Docker bawaan (`pde-b2b-web-id_default`) digunakan agar layanan bisa saling berkomunikasi melalui nama layanan (`db` dan `php`).

### Lainnya
- File `.gitmodules` diperbarui untuk mencerminkan URL submodule baru.
- Pada beberapa saat, direktori `vendor/` di dalam submodule telah diperbaiki (penghapusan dan pemasangan ulang Composer sebagai root) untuk mengatasi masalah izin yang mencegah Composer menulis `installed.php`.

### Makefile
- ✅ **Buat `Makefile`**  
   - Telah dibuat dengan target `dev` yang menjalankan:  
     * `git submodule update --init --recursive`  
     * `docker compose up -d`  
     * `docker compose exec --user root php php composer install --no-dev --ignore-platform-reqs` (hanya jika `vendor/` belum ada)  
     * `docker compose exec php php yii migrate/up --interactive=0`  
   - Termasuk target pendukung: `up`, `down`, `logs`, `bash-php`, `bash-db`, `test`, `lint`, `reset`, `help`.  
   - Letakkan di root repository sehingga cukup `make dev` untuk lingkungan yang siap pakai.

## ✅ Checklist

- [x] Update submodule to https://github.com/b2b-web-id/pde-idx-app.git (master)
- [x] Add comprehensive documentation (installation, database, deployment, architecture, development, TODO)
- [x] Create Makefile with dev target
- [ ] Add .env template and .gitignore entry
- [ ] Add Redis service to docker-compose.yml
- [ ] Configure cache component in config/web.php
- [ ] Set production env vars (YII_DEBUG=false, YII_ENV=prod)
- [ ] Implement GitHub Actions CI/CD workflow
- [ ] Write ops guide (docs/operations.md)
- [ ] Verify migrations and seed data
- [ ] Review documentation and links

## 📌 Rencana Selanjutnya (TODO)

2. **Standarisasi Variabel Lingkungan**  
   - Pertimbangkan menggunakan file `.env` (tidak di‑commit) untuk menyimpan rahasia seperti kata sandi MySQL, sehingga `docker-compose.yml` dapat merujuk ke `${MYSQL_PASSWORD}` dsb.  
   - Ini akan memudahkan pengalihan antara lingkungan development, staging, dan production.

3. **Tambahkan Layanan Cache (Opsional)**  
   - Tambahkan layanan `redis` (atau `memcached`) ke `docker-compose.yml` untuk caching query dan menyessionsi.  
   - Konfigurasi komponen `cache` di `config/web.php` untuk menggunakan `yii\caching\RedisCache`.

4. **Peningkatan Keamanan**  
   - Dalam produksi, pastikan `YII_DEBUG=false` dan `YII_ENV=prod`.  
   - Pertimbangkan menggunakan Docker secrets atau Kubernetes Secrets untuk menyimpan kredensial database alih‑alih variabel lingkungan biasa.  
   - Batasi akses ke port MySQL hanya dari jaringan Docker (sudah dilakukan dengan tidak mengekspos port).

5. **CI/CD Dasar**  
   - Tambahkan alur kerja GitHub Actions yang:  
     * Membangun image Docker `php`.  
     * Menjalankan unit tests dan kode statis (PHP_CodeSniffer, PHPStan) dalam container.  
     * Push image ke registry (misalnya GitHub Packages atau Docker Hub) setelah berhasil.

6. **Dokumentasi Operasional**  
   - Buat panduan ops sederhana di `docs/operations.md` yang menjelaskan cara monitoring log (`docker compose logs -f`), memeriksa status container, melakukan backup/manual restore volume `db_data`, dan melakukan rollback image.  
   - Sertakan contoh perintah untuk membuka shell ke container (`docker compose exec php bash`) dan menjalankan perintah Yii (`docker compose exec php php yii help`).

7. **Uji Migrasi dan Seed Data**  
   - Verifikasi bahwa skrip migrasi yang ada (jika ada) berjalan tanpa error setelah `make dev` pertama kali.  
   - Jika diperlukan, buat seed data awal (misalnya daftar perusahaan IDX dasar) untuk memudahkan pengembangan awal.

8. **Review dan Penyempurnaan**  
   - Lakukan code review terhadap berkas‑berkas yang baru dibuat (docs, docker‑compose, Makefile).  
   - Pastikan semua tautan di dokumen masih valid dan contoh perintah dapat disalin‑tempel langsung ke terminal.

---

*Catatan*: File ini (`docs/TODO.md`) sebaiknya diperbarui setiap kali ada perubahan signifikan atau setelah suatu item dalam daftar selesai diselesaikan. Dengan begitu, tim akan selalu memiliki gambaran jelas tentang apa yang telah dilakukan dan apa yang masih perlu dikerjakan.

---

*Dibuat untuk tim PDE‑B2B‑WEB‑ID.*