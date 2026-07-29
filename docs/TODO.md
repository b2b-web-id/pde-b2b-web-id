# TODO – Perubahan dan Rencana Selanjutnya

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

1. **Standarisasi Variabel Lingkungan**  
   - Pertimbangkan menggunakan file `.env` (tidak di‑commit) untuk menyimpan rahasia seperti kata sandi MySQL, sehingga `docker-compose.yml` dapat merujuk ke `${MYSQL_PASSWORD}` dsb.  
   - Ini akan memudahkan pengalihan antara lingkungan development, staging, dan production.

2. **Tambahkan Layanan Cache (Opsional)**  
   - Tambahkan layanan `redis` (atau `memcached`) ke `docker-compose.yml` untuk caching query dan menyessionsi.  
   - Konfigurasi komponen `cache` di `config/web.php` untuk menggunakan `yii\caching\RedisCache`.

3. **Peningkatan Keamanan**  
   - Dalam produksi, pastikan `YII_DEBUG=false` dan `YII_ENV=prod`.  
   - Pertimbangkan menggunakan Docker secrets atau Kubernetes Secrets untuk menyimpan kredensial database alih‑alih variabel lingkungan biasa.  
   - Batasi akses ke port MySQL hanya dari jaringan Docker (sudah dilakukan dengan tidak mengekspos port).

4. **CI/CD Dasar**  
   - Tambahkan alur kerja GitHub Actions yang:  
     * Membangun image Docker `php`.  
     * Menjalankan unit tests dan kode statis (PHP_CodeSniffer, PHPStan) dalam container.  
     * Push image ke registry (misalnya GitHub Packages atau Docker Hub) setelah berhasil.

5. **Dokumentasi Operasional**  
   - Buat panduan ops sederhana di `docs/operations.md` yang menjelaskan cara monitoring log (`docker compose logs -f`), memeriksa status container, melakukan backup/manual restore volume `db_data`, dan melakukan rollback image.  
   - Sertakan contoh perintah untuk membuka shell ke container (`docker compose exec php bash`) dan menjalankan perintah Yii (`docker compose exec php php yii help`).

6. **Uji Migrasi dan Seed Data**  
   - Verifikasi bahwa skrip migrasi yang ada (jika ada) berjalan tanpa error setelah `make dev` pertama kali.  
   - Jika diperlukan, buat seed data awal (misalnya daftar perusahaan IDX dasar) untuk memudahkan pengembangan awal.

7. **Review dan Penyempurnaan**  
   - Lakukan code review terhadap berkas‑berkas yang baru dibuat (docs, docker‑compose, Makefile).  
   - Pastikan semua tautan di dokumen masih valid dan contoh perintah dapat disalin‑tempel langsung ke terminal.

---

*Catatan*: File ini (`docs/TODO.md`) sebaiknya diperbarui setiap kali ada perubahan signifikan atau setelah suatu item dalam daftar selesai diselesaikan. Dengan begitu, tim akan selalu memiliki gambaran jelas tentang apa yang telah dilakukan dan apa yang masih perlu dikerjakan.

--- 

*Dibuat untuk tim PDE‑B2B‑WEB‑ID.*