# Pemantauan Sesi PPP didorong lewat Laravel Reverb di container yang sama

**Status**: Accepted
**Date**: 2026-10-02

Status PPP di halaman Detail Pelanggan sebelumnya di-poll oleh setiap tab browser (`wire:poll.20s`), sehingga beban ke RouterOS naik sebanding dengan jumlah tab yang terbuka. RouterOS API tidak punya push, jadi pembacaan dipindah ke satu job terjadwal: setiap 10 detik job membaca `/ppp/active` sekali per Router online, membandingkannya dengan snapshot sebelumnya, dan menyiarkan event berisi `layanan_id` saja ke private channel `pelanggan.{id}`. Saat event diterima, client mengambil status lengkap lewat Livewire. Selama WebSocket putus, halaman kembali ke `wire:poll` dengan interval lama, dan saat tersambung lagi status diambil ulang sekali.

Server WebSocket-nya Laravel Reverb, dijalankan sebagai program supervisord di dalam container aplikasi dan di-proxy oleh Caddy di domain staf (melanjutkan ADR-0036, satu service).

## Considered Options

- `beyondcode/laravel-websockets`: diarsipkan dan tidak mendukung Laravel 11+.
- Pusher/Soketi yang di-host: menambah layanan eksternal tanpa manfaat dibanding Reverb.
- Script `on-up`/`on-down` di PPP profile yang memanggil webhook (push sungguhan, latensi di bawah 1 detik): harus dipasang di semua router dan profile, dan membuka endpoint webhook untuk router. Ditunda sampai latensi 10 detik terbukti kurang.
- Hanya memantau router yang sedang ditonton (presence channel): lebih hemat, tetapi rumit. Satu query per router per 10 detik dianggap cukup murah pada skala sekarang.
