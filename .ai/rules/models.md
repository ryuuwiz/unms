---
paths:
  - app/Models/Sysblas.php
---

# Models

## Batas Laju Pengiriman & Jeda Antar-Pesan harus konsisten satu sama lain
`limit_per_menit` dan `delay_detik`/`jitter_detik` bukan dua kontrol independen — keduanya harus merepresentasikan satu target throughput yang sama (`delay_detik ≈ 60 / limit_per_menit`). Jeda Antar-Pesan (Cache slot `sysblas-next-send-slot-*`) dicek LEBIH DULU dan blocking di `KirimWaBlastJob`, sebelum `RateLimiter` per `limit_per_menit` dicek. Jika `delay_detik` lebih longgar dari `60/limit_per_menit` detik, menaikkan `limit_per_menit` tidak berefek apa pun — throughput nyata tetap dibatasi oleh `delay_detik`. Default aman saat ini: `limit_per_menit=4`, `delay_detik=15` (lihat ADR 0035). Jangan ubah salah satu kolom tanpa menyesuaikan pasangannya. Validasi `limit_per_menit` dibatasi keras `max:20` di `app/Livewire/Sysblas/Koneksi/Index.php` sebagai pengaman anti-ban gateway self-hosted (WAHA/GOWA) — jangan longgarkan tanpa alasan kuat.
