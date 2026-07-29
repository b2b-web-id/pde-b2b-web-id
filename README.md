# PDE-B2B-WEB-ID

Proyek ini menyediakan aplikasi web berbasis Yii 2 untuk mengakses data pasar modal Indonesia Stock Exchange (IDX). Aplikasi dijalankan dalam lingkungan Docker dengan layanan MySQL dan PHP‑Apache yang terisolasi dalam jaringan internal.

## Dokumentasi

- [Instalasi](docs/installation.md) – panduan instalasi (Docker dan manual)
- [Database](docs/database.md) – konfigurasi basis data, inisialisasi, migrasi, backup, dan peningkatan performa
- [Deployment](docs/deployment.md) – petunjuk penerapan ke produksi
- [Arsitektur](docs/architecture.md) – gambaran arsitektur tinggi komponen
- [Pengembangan](docs/development.md) – panduan pengembangan hari‑hari
- [TODO](docs/TODO.md) – daftar tugas dan rencana selanjutnya

## Instalasi Cepat (Developer)

```bash
make dev
```

Perintah tersebut akan memperbarui submodule, meng‑up kontainer Docker, menginstal dependensi Composer, dan menjalankan migrasi basis data. Setelah selesai, buka <http://localhost/> di browser Anda.

## Lisensi

Lihat file [LICENSE](LICENSE) untuk detail.